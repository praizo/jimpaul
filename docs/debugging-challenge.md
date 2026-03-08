# Debugging Challenge — Intermittent Multi-Layer Bug

## Scenario

The **project creation** endpoint (`POST /api/v1/projects`) fails intermittently. Users report that sometimes the project is created without all task groups, or assigned users don't receive notifications. The bug manifests differently depending on:

- **Cache state** — Stale search results appear after project creation
- **Race conditions** — Duplicate tasks created when multiple users submit simultaneously
- **Queue timing** — Notifications arrive hours late or not at all
- **Browser differences** — Search UI works in Chrome but results flicker in Firefox

---

## Debugging Process

### Step 1: Replication Strategy

**Goal:** Reproduce the bug consistently in a controlled environment.

**Approach:**

1. **Gather evidence first** — Collect user reports with timestamps, browser versions, request IDs
2. **Check production logs** for the time window:
    ```bash
    php artisan pail --filter="ProjectService" --filter="ERROR"
    ```
3. **Reproduce locally per symptom:**

    | Symptom              | Replication Method                                              |
    | -------------------- | --------------------------------------------------------------- |
    | Missing task groups  | Send the exact payload from the bug report via Postman          |
    | Stale search results | Create project → immediately search → check if new tasks appear |
    | Duplicate tasks      | Open 2 browser tabs, submit same payload simultaneously         |
    | Late notifications   | Create project → check `jobs` table and `failed_jobs` table     |
    | Firefox flickering   | Open search UI in Firefox DevTools, throttle network to Slow 3G |

4. **Environment parity** — Use the same database driver (PostgreSQL, not SQLite) and Redis locally:
    ```bash
    # docker-compose for local parity
    docker compose up -d postgres redis
    php artisan migrate:fresh --seed
    ```

---

### Step 2: Logs & Instrumentation

**Goal:** Add observability to pinpoint where failures occur.

**A. Structured logging already in place:**

Our `ProjectService` logs key events:

```php
Log::info('Project created successfully', [
    'project_id' => $project->id,
    'user_id' => $userId,
    'task_groups_count' => $project->taskGroups->count(),
    'total_tasks' => $project->taskGroups->sum(fn ($group) => $group->tasks->count()),
]);
```

**B. Add granular instrumentation for debugging:**

```php
// In ProjectService::createTaskGroups()
Log::debug('Creating task group', [
    'project_id' => $project->id,
    'group_name' => $groupData['name'],
    'tasks_count' => count($groupData['tasks'] ?? []),
]);

// In ProjectService::createTasks()
Log::debug('Creating task', [
    'task_group_id' => $taskGroupId,
    'parent_id' => $parentId,
    'title' => $taskData['title'],
]);
```

**C. Monitor the queue:**

```bash
# Check pending jobs
php artisan queue:monitor redis:default --max=100

# Check failed jobs
php artisan queue:failed

# Retry a failed job to observe error
php artisan queue:retry <job-id>
```

**D. Database query logging:**

```php
// In AppServiceProvider::boot() — TEMPORARY, dev only
DB::listen(function ($query) {
    if ($query->time > 100) { // Slow query threshold: 100ms
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time,
            'bindings' => $query->bindings,
        ]);
    }
});
```

---

### Step 3: Code Isolation

**Goal:** Narrow down the exact layer causing each symptom.

**A. Cache-related bug (stale search results):**

```php
// Test 1: Bypass cache entirely
// In TaskSearchService::search(), temporarily:
return $this->taskRepository->search($query, $userId, $perPage);
// If results are now correct → cache invalidation is the issue
```

**Root cause hypothesis:** `invalidateUserCache()` uses `Cache::flush()` which is too broad and may not work with the `database` cache driver in dev. In production with Redis, tagged caching isn't configured.

**B. Race condition (duplicate tasks):**

```php
// Test 2: Add database-level lock
DB::transaction(function () use ($validatedData, $userId) {
    // Advisory lock on user to prevent concurrent project creation
    DB::statement('SELECT pg_advisory_xact_lock(?)', [$userId]);

    // ... create project
});
```

**Root cause hypothesis:** Two concurrent requests pass validation simultaneously. Without a lock, both insert records before either commits.

**C. Queue timing (late/missing notifications):**

```bash
# Test 3: Process jobs synchronously
QUEUE_CONNECTION=sync php artisan tinker
# Then trigger project creation — if notification arrives immediately, the queue worker is the bottleneck
```

**Root cause hypothesis:** Queue worker is not running, or crashed and Supervisor hasn't restarted it. Alternatively, the `$tries` and `$backoff` configuration causes long delays.

**D. Browser differences (Firefox flickering):**

```js
// Test 4: In search.js, add logging
console.log("Fetch started:", query);
console.log("Fetch completed:", json.data.length, "results");

// Check if AbortController behavior differs between browsers
// Firefox may handle abort differently, causing a brief render of stale results
```

**Root cause hypothesis:** Firefox processes microtasks differently. When a new fetch starts and the previous one is aborted, Firefox may briefly render the `AbortError` state before the new results arrive.

---

### Step 4: Root Cause Identification

After isolation testing, the root causes are:

| Symptom              | Root Cause                                                                              | Layer        |
| -------------------- | --------------------------------------------------------------------------------------- | ------------ |
| Stale search results | `Cache::flush()` doesn't target specific keys; `database` driver doesn't support tags   | Cache/Config |
| Duplicate tasks      | No application-level or DB-level locking for concurrent writes                          | Database     |
| Late notifications   | Queue worker restart delay after OOM; `$backoff = 60` causes 3-minute worst case        | Queue/Infra  |
| Firefox flickering   | `AbortController.abort()` resolves differently in Firefox; stale render before new data | Frontend     |

---

### Step 5: Final Fix

**Fix 1 — Cache invalidation (targeted):**

```php
// TaskSearchService.php — use pattern-based key deletion instead of flush
public function invalidateUserCache(int $userId): void
{
    // Delete all cache keys matching this user's search pattern
    // With Redis: use SCAN + DEL for pattern matching
    $pattern = "task_search:{$userId}:*";

    if (config('cache.default') === 'redis') {
        $keys = Redis::keys(config('cache.prefix') . $pattern);
        if (! empty($keys)) {
            Redis::del($keys);
        }
    }
}
```

**Fix 2 — Race condition (advisory lock):**

```php
// ProjectService.php — add advisory lock inside transaction
public function createProjectWithNestedData(array $validatedData, int $userId): Project
{
    return DB::transaction(function () use ($validatedData, $userId) {
        // PostgreSQL advisory lock prevents concurrent creation by same user
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$userId]);

        // ... rest of creation logic
    });
}
```

For MySQL: use `GET_LOCK('project_create_' . $userId, 10)` instead.

**Fix 3 — Queue reliability:**

```ini
# Supervisor config — ensure worker restarts on OOM/crash
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php artisan queue:work redis --tries=3 --backoff=10 --max-time=3600 --memory=128
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/worker.log
stopwaitsecs=3600
```

Changes:

- Reduced `$backoff` from `60` to `10` seconds (faster retry)
- Added `--max-time=3600` (worker restarts hourly to prevent memory leaks)
- `numprocs=2` for redundancy

**Fix 4 — Firefox flickering:**

```js
// search.js — guard against rendering during abort transition
async function fetchResults(query) {
    if (abortController) {
        abortController.abort();
    }
    abortController = new AbortController();
    const currentController = abortController; // Capture reference

    try {
        const response = await fetch(url, { signal: currentController.signal });
        const json = await response.json();

        // Only render if this is still the active request
        if (currentController !== abortController) return;

        // ... render results
    } catch (err) {
        if (err.name === "AbortError") return;
        if (currentController !== abortController) return; // Guard stale errors
        showError(err.message);
    }
}
```

The key fix is the `currentController !== abortController` guard — it ensures that only the most recent request can update the UI, preventing the flickering caused by Firefox's different microtask scheduling.

---

## Summary

| Phase          | Actions                                                                      |
| -------------- | ---------------------------------------------------------------------------- |
| **Replicate**  | Collect timestamps, reproduce per-symptom locally, ensure environment parity |
| **Instrument** | Structured logs at each layer, query monitoring, queue inspection            |
| **Isolate**    | Bypass cache, add DB locks, switch to sync queue, add browser logging        |
| **Identify**   | Map each symptom to its root cause and specific layer                        |
| **Fix**        | Targeted cache deletion, advisory locks, Supervisor tuning, fetch guard      |
