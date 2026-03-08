# System Design — Task Management Platform

## Architecture Overview

This application follows a **layered architecture** with clear separation of concerns:

```
┌─────────────────────────────────────────────────────────────────┐
│                     Client Layer                                │
│   Vanilla JS Search UI  ←→  Mobile Apps  ←→  Third-party       │
└─────────────────────┬───────────────────────────────────────────┘
                      │ HTTPS / JSON
┌─────────────────────▼───────────────────────────────────────────┐
│                  API Gateway / Load Balancer                    │
│              (Nginx + SSL Termination + Rate Limit)             │
└─────────────────────┬───────────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────────┐
│                  Laravel Application                            │
│  ┌──────────┐  ┌───────────┐  ┌────────────┐  ┌────────────┐  │
│  │Controller│→ │  Service   │→ │ Repository │→ │   Model    │  │
│  │  (HTTP)  │  │  (Logic)   │  │ (Data)     │  │ (Eloquent) │  │
│  └──────────┘  └─────┬─────┘  └────────────┘  └────────────┘  │
│                      │                                          │
│               ┌──────▼──────┐       ┌─────────────┐            │
│               │   Events    │──────→│ Queue Worker │            │
│               └─────────────┘       │  (Redis)     │            │
│                                     └──────┬──────┘            │
│                                            │                    │
│                              ┌─────────────▼──────────┐        │
│                              │  Notifications / Mail   │        │
│                              └─────────────────────────┘        │
└─────────────────────┬──────────────────┬────────────────────────┘
                      │                  │
          ┌───────────▼──┐         ┌─────▼─────┐
          │  PostgreSQL  │         │   Redis    │
          │   (Primary)  │         │  (Cache +  │
          │              │         │   Queues)  │
          └──────────────┘         └───────────┘
```

### Component Descriptions

| Component               | Role                                                                   |
| ----------------------- | ---------------------------------------------------------------------- |
| **Nginx**               | Reverse proxy, SSL termination, static file serving, rate limiting     |
| **Laravel Controllers** | Thin HTTP layer — validates input, delegates to Services, returns JSON |
| **Service Layer**       | Business logic, transaction orchestration, event dispatching           |
| **Repository Layer**    | Data access abstraction — Eloquent implementations behind interfaces   |
| **Eloquent Models**     | Domain entities, relationships, attribute casting                      |
| **Redis**               | Dual role: caching (search results, 5min TTL) + queue broker           |
| **Queue Workers**       | Process async jobs: notifications, email, external API calls           |
| **PostgreSQL**          | Primary data store with full-text search, transactions, indexes        |

---

## Database Schema

### Entity-Relationship Diagram

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│    users     │     │   projects   │     │  task_groups  │
├─────────────┤     ├──────────────┤     ├──────────────┤
│ id (PK)     │◄────│ user_id (FK) │     │ id (PK)      │
│ name        │     │ id (PK)      │◄────│ project_id   │
│ email       │     │ name         │     │ name         │
│ password    │     │ description  │     │ description  │
│ created_at  │     │ status       │     │ sort_order   │
│ updated_at  │     │ priority     │     │ color        │
└──────┬──────┘     │ due_date     │     │ created_at   │
       │            │ settings     │     │ updated_at   │
       │            │ deleted_at   │     └──────┬───────┘
       │            │ created_at   │            │
       │            │ updated_at   │            │
       │            └──────────────┘            │
       │                                        │
       │            ┌──────────────┐            │
       └───────────►│    tasks     │◄───────────┘
                    ├──────────────┤
                    │ id (PK)      │──┐ (self-ref)
                    │ task_group_id│  │
                    │ parent_id    │◄─┘
                    │ assigned_to  │
                    │ title        │    ┌──────────────┐
                    │ description  │    │ task_labels   │
                    │ status       │    ├──────────────┤
                    │ priority     │◄───│ task_id (FK)  │
                    │ sort_order   │    │ label_id (FK) │
                    │ est_hours    │    └──────┬───────┘
                    │ due_date     │           │
                    │ completed_at │    ┌──────▼───────┐
                    │ deleted_at   │    │    labels     │
                    │ created_at   │    ├──────────────┤
                    │ updated_at   │    │ id (PK)      │
                    └──────────────┘    │ name (UQ)    │
                                        │ color        │
                                        │ created_at   │
                                        │ updated_at   │
                                        └──────────────┘
```

### Key Design Decisions

- **Self-referencing `parent_id`** on `tasks` enables unlimited subtask depth
- **Soft deletes** on `projects` and `tasks` for data recovery
- **Full-text index** on `tasks.title + tasks.description` for efficient search
- **Composite indexes** on frequently queried columns (`user_id + status`, `assigned_to + status`)
- **JSON column** (`settings`) for flexible project configuration without schema changes

---

## API Structure

### Endpoints

| Method | URI                       | Auth       | Description                       |
| ------ | ------------------------- | ---------- | --------------------------------- |
| `POST` | `/api/v1/projects`        | ✅ Sanctum | Create project with nested data   |
| `GET`  | `/api/v1/tasks/search?q=` | Optional   | Search tasks by title/description |

### Example Request — `POST /api/v1/projects`

See [`docs/example-payload.json`](file:///C:/Users/PLAYHOUSE/Herd/jim-paul/docs/example-payload.json) for the full 4-level nested payload.

### Validation Hierarchy

```
project (name, description, status, priority, due_date, settings)
  └── task_groups[] (name, description, sort_order, color)         [max: 20]
        └── tasks[] (title, description, status, priority, ...)    [max: 50]
              ├── labels[] (name, color)                           [max: 10]
              └── subtasks[] (title, description, status, ...)     [max: 20]
                    └── labels[] (name, color)                     [max: 10]
```

### Error Handling & Rollback Strategy

- All DB writes wrapped in `DB::transaction()` — automatic rollback on any exception
- Validation failures return `422` with structured errors before any DB write
- Service exceptions return `500` with rollback confirmation
- Debug mode exposes error messages; production returns generic message
- All errors are logged with context (user_id, trace) for debugging

---

## Caching Strategy

| What           | Key Pattern                                          | TTL    | Invalidation          |
| -------------- | ---------------------------------------------------- | ------ | --------------------- |
| Search results | `task_search:{userId}:{md5(query)}:{perPage}:{page}` | 5 min  | On task create/update |
| Project data   | `project:{id}` (future)                              | 10 min | On project update     |

**In production with Redis:**

- Use **tagged caching** (`Cache::tags(['user:1:search'])`) for targeted invalidation
- Configure `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis` in `.env`

---

## Queue / Worker Design

```
Event: ProjectCreated
  └──► Listener: NotifyProjectMembers (ShouldQueue)
         ├── Retries: 3 attempts with 60s backoff
         ├── Failure: Logged with context, job stored in failed_jobs
         └── Action: Notify all assigned users (email/push)
```

- **Queue Driver:** Redis (production) / Database (local dev)
- **Workers:** Run via `php artisan queue:work redis --tries=3 --backoff=60`
- **Supervisor** manages worker processes in production (auto-restart on failure)
- **Horizon** (optional) for Redis queue monitoring dashboard

---

## Security Considerations

See the full security hardening guide: [`docs/security-hardening.md`](file:///C:/Users/PLAYHOUSE/Herd/jim-paul/docs/security-hardening.md)

Key measures:

- **Sanctum** token auth for API endpoints
- **Form Request** validation prevents injection and malformed input
- **Rate limiting** on API endpoints (configurable per route)
- **CORS** configuration restricted to known origins
- **Parameterized queries** via Eloquent (no raw SQL)
- **Security headers** (CSP, X-Frame-Options, HSTS)

---

## Deployment Strategy

### Infrastructure (AWS Example)

```
                    ┌─────────────┐
                    │   Route 53  │
                    │    (DNS)    │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │ CloudFront  │
                    │   (CDN)     │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │    ALB      │
                    │(Load Bal.)  │
                    └──┬──────┬───┘
                       │      │
               ┌───────▼┐  ┌─▼───────┐
               │ ECS/EC2│  │ ECS/EC2 │  (Auto-scaling app servers)
               │ App 1  │  │ App 2   │
               └───┬────┘  └────┬────┘
                   │            │
           ┌───────▼────────────▼───────┐
           │     ElastiCache (Redis)    │
           └────────────────────────────┘
           ┌────────────────────────────┐
           │      RDS (PostgreSQL)      │
           │    Primary + Read Replica  │
           └────────────────────────────┘
```

### CI/CD Pipeline

1. **Push to `main`** → GitHub Actions triggered
2. **Run tests** → `php artisan test --parallel`
3. **Lint** → `vendor/bin/pint --test`
4. **Build assets** → `npm run build`
5. **Docker build** → Push image to ECR
6. **Deploy** → Rolling update on ECS / zero-downtime with health checks
7. **Migrate** → `php artisan migrate --force` (single instance, before traffic shift)

### Environment Configuration

- `.env` per environment (local, staging, production) via AWS Secrets Manager
- `APP_DEBUG=false` in production
- `LOG_CHANNEL=cloudwatch` for centralized logging
- `QUEUE_CONNECTION=redis` with Horizon for monitoring
