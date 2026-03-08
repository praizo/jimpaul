# Security Hardening — Laravel API

This guide outlines how to secure the Task Management Platform API against common attack vectors, using Laravel-specific middleware, configuration, and coding practices.

---

## 1. SQL Injection Protection

### Threat

Attackers inject malicious SQL via user input to read, modify, or delete database records.

### Laravel Defenses

**Eloquent ORM & Query Builder** — all queries use parameter binding by default:

```php
// ✅ SAFE — parameterized via Eloquent (used throughout our Repository layer)
$this->model->newQuery()
    ->where('title', 'LIKE', "%{$query}%")
    ->paginate($perPage);

// ❌ DANGEROUS — raw interpolation (NEVER do this)
DB::select("SELECT * FROM tasks WHERE title LIKE '%$query%'");
```

**Our approach:**

- Repository pattern ensures all data access goes through Eloquent — no raw SQL
- `StoreProjectRequest` validates and sanitizes all input before it reaches the Repository
- `assigned_to` validated with `exists:users,id` — prevents ID manipulation

### Configuration

```php
// config/database.php — enforce strict mode (MySQL)
'mysql' => [
    'strict' => true,  // Rejects ambiguous queries
],
```

---

## 2. XSS (Cross-Site Scripting) Prevention

### Threat

Attackers inject malicious scripts that execute in other users' browsers.

### Laravel Defenses

**Blade auto-escaping** — `{{ }}` syntax escapes all output:

```php
// ✅ SAFE — auto-escaped
{{ $task->title }}

// ❌ DANGEROUS — raw output
{!! $userInput !!}
```

**API Resource layer** — our `TaskResource`, `ProjectResource` return typed JSON; no HTML rendering.

**Frontend defense** — our `search.js` uses a dedicated `escapeHtml()` function:

```js
function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
}
```

### Security Headers Middleware

```php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'"
        );

        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
```

Register in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
})
```

---

## 3. CSRF Protection

### Threat

Attackers trick authenticated users into making unintended requests.

### Laravel Defenses

- **Web routes:** Laravel's `VerifyCsrfToken` middleware is automatic — all `POST/PUT/DELETE` forms require `@csrf` token
- **API routes:** Token-based auth (Sanctum) inherently prevents CSRF — the attacker cannot forge the `Authorization: Bearer <token>` header
- **SPA scenario:** Sanctum's cookie-based auth requires a `/sanctum/csrf-cookie` preflight request

```php
// For SPA authentication with CSRF protection:
// 1. Client calls GET /sanctum/csrf-cookie (sets XSRF-TOKEN cookie)
// 2. Client includes X-XSRF-TOKEN header in subsequent requests
// 3. Laravel validates the token automatically
```

### Configuration

```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),
```

---

## 4. Rate-Limit Bypass Prevention

### Threat

Attackers exhaust server resources or brute-force endpoints by exceeding request limits.

### Laravel Defenses

**Built-in rate limiter** in `bootstrap/app.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// In AppServiceProvider::boot()
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Stricter limit for auth endpoints
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

**Anti-bypass measures:**

- Rate limit by **user ID** (authenticated) AND **IP** (unauthenticated) — prevents single-IP flooding
- Use `X-Forwarded-For` awareness behind load balancers: `TrustProxies` middleware
- **Nginx-level rate limiting** as first defense (before request reaches Laravel):

```nginx
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
location /api/ {
    limit_req zone=api burst=20 nodelay;
}
```

- Return `429 Too Many Requests` with `Retry-After` header
- Log rate limit hits for anomaly detection

---

## 5. Token Theft Protection

### Threat

Attackers steal authentication tokens to impersonate users.

### Laravel Sanctum Defenses

```php
// Token creation with scoped abilities and expiration
$token = $user->createToken(
    name: 'api-access',
    abilities: ['projects:create', 'tasks:search'],
    expiresAt: now()->addHours(24),
);
```

**Protection layers:**

- **Short-lived tokens** (24h expiry) — limits the window of exploitation
- **Ability scoping** — stolen token can only perform specific actions
- **Token hashing** — tokens stored as SHA-256 hashes in `personal_access_tokens` table (plaintext never stored)
- **HTTPS only** — `Secure` cookie flag prevents transmission over HTTP
- **Token revocation** on logout:

```php
// Revoke current token
$request->user()->currentAccessToken()->delete();

// Revoke all tokens (password change / security event)
$request->user()->tokens()->delete();
```

### Configuration

```php
// config/sanctum.php
'expiration' => 1440, // 24 hours in minutes

// .env
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
```

---

## 6. Strengthening Authentication Flows

### Threat

Weak auth flows allow credential stuffing, brute-force, and session hijacking.

### Defenses

**Password hashing** — Laravel uses bcrypt with configurable cost:

```php
// .env
BCRYPT_ROUNDS=12  // Already configured in this project
```

**Login attempt throttling:**

```php
// In LoginController or via RateLimiter
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->input('email') . '|' . $request->ip()),
    ];
});
```

**Secure API login endpoint example:**

```php
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            // Generic message — don't reveal whether email exists
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = Auth::user();

        // Revoke existing tokens (single-session enforcement)
        $user->tokens()->delete();

        $token = $user->createToken(
            name: 'api-access',
            abilities: ['*'],
            expiresAt: now()->addHours(24),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'expires_at' => now()->addHours(24)->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
```

**Additional measures:**

- Force password reset after suspicious activity
- Implement **2FA** via `laravel/fortify` for high-security users
- Log all auth events for audit trail
- `SESSION_SAME_SITE=strict` prevents session cookies from being sent in cross-site requests

---

## Summary — Security Checklist

| Threat            | Protection                                  | Layer              |
| ----------------- | ------------------------------------------- | ------------------ |
| SQL Injection     | Eloquent ORM, parameterized queries         | Repository         |
| XSS               | Blade escaping, CSP headers, `escapeHtml()` | View / Middleware  |
| CSRF              | Sanctum token auth (API), `@csrf` (web)     | Middleware         |
| Rate-limit bypass | Per-user + per-IP limits, Nginx layer       | Middleware / Infra |
| Token theft       | Short expiry, hashing, HTTPS, revocation    | Auth / Config      |
| Weak auth         | Bcrypt, throttling, generic errors, 2FA     | Auth               |
