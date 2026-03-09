# Task Management Platform

A **Laravel 12** RESTful API for managing projects, task groups, tasks, subtasks, and labels — built with a clean **layered architecture** (Service → Repository → Model).

---

## ✨ Key Features

- **Deeply nested project creation** — Create a project with task groups, tasks, subtasks, and labels in a single atomic API call
- **Full-text task search** — Cached search across task titles and descriptions with pagination
- **Layered architecture** — Service, Repository (with interface contracts), and Eloquent Model layers
- **Transactional integrity** — All writes wrapped in `DB::transaction()` with automatic rollback
- **Event-driven notifications** — `ProjectCreated` event dispatches queued listeners for async processing
- **Security hardened** — Custom `SecurityHeaders` middleware (CSP, HSTS, X-Frame-Options, and more)
- **Sanctum authentication** — Token-based API auth for protected endpoints
- **Comprehensive validation** — Form Request classes with nested array validation rules

---

## 🏗️ Architecture

```
Controller (HTTP) → Service (Business Logic) → Repository (Data Access) → Model (Eloquent)
```

| Layer            | Responsibility                                       |
| ---------------- | ---------------------------------------------------- |
| **Controllers**  | Thin HTTP layer — validates, delegates, returns JSON |
| **Services**     | Business logic, transactions, event dispatching      |
| **Repositories** | Data access behind interface contracts               |
| **Models**       | Eloquent entities, relationships, attribute casting  |

### Domain Models

| Model       | Description                                                   |
| ----------- | ------------------------------------------------------------- |
| `User`      | Authentication entity with Sanctum tokens                     |
| `Project`   | Top-level container, owned by a user (soft deletes)           |
| `TaskGroup` | Organisational group within a project (sortable, color-coded) |
| `Task`      | Work item with self-referencing subtasks (soft deletes)       |
| `Label`     | Taggable labels attached to tasks via pivot table             |

---

## 📡 API Endpoints

All endpoints are prefixed with `/api/v1`.

| Method | URI                | Auth       | Description                       |
| ------ | ------------------ | ---------- | --------------------------------- |
| `POST` | `/projects`        | ✅ Sanctum | Create project with nested data   |
| `GET`  | `/tasks/search?q=` | Optional   | Search tasks by title/description |

---

## 🛠️ Tech Stack

| Tool              | Version |
| ----------------- | ------- |
| PHP               | ^8.2    |
| Laravel Framework | 12.x    |
| Pest (Testing)    | 4.x     |
| Vite              | 7.x     |
| Tailwind CSS      | 4.x     |
| SQLite (dev)      | —       |

---

## 🚀 Getting Started

### Prerequisites

- **PHP 8.2+** with required extensions
- **Composer**
- **Node.js & npm**
- [Laravel Herd](https://herd.laravel.com) (recommended) or any local PHP server

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd jim-paul

# Run the setup script (installs deps, generates key, runs migrations, builds assets)
composer setup
```

Or manually:

```bash
# 1. Install PHP dependencies
composer install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Run database migrations
php artisan migrate

# 4. Install and build frontend assets
npm install
npm run build
```

### Running the Application

```bash
# Start all services (server, queue worker, Vite) concurrently
composer dev
```

This spins up:

- **Laravel dev server** on `http://127.0.0.1:8000`
- **Queue worker** listening for jobs
- **Vite** dev server for hot-reloading

> If using **Laravel Herd**, the app is automatically available at `http://jim-paul.test`.

### Seeding the Database

```bash
php artisan db:seed
```

---

## 🧪 Testing

The project uses **Pest 4** for testing.

```bash
# Run all tests
php artisan test --compact

# Run a specific test
php artisan test --compact --filter=testName
```

---

## 🔒 Security

A custom `SecurityHeaders` middleware applies the following response headers:

| Header                      | Value                                      |
| --------------------------- | ------------------------------------------ |
| `X-Content-Type-Options`    | `nosniff`                                  |
| `X-Frame-Options`           | `DENY`                                     |
| `X-XSS-Protection`          | `1; mode=block`                            |
| `Referrer-Policy`           | `strict-origin-when-cross-origin`          |
| `Permissions-Policy`        | `camera=(), microphone=(), geolocation=()` |
| `Content-Security-Policy`   | Restrictive `default-src 'self'` policy    |
| `Strict-Transport-Security` | Enabled in production (HSTS with preload)  |

See [`docs/security-hardening.md`](docs/security-hardening.md) for the full security audit.

---

## 📁 Project Structure

```
app/
├── Events/               # Domain events (ProjectCreated)
├── Http/
│   ├── Controllers/Api/V1/  # Versioned API controllers
│   ├── Middleware/           # SecurityHeaders
│   ├── Requests/Api/        # Form Request validation
│   └── Resources/V1/        # Eloquent API Resources
├── Listeners/            # Queued event listeners
├── Models/               # Eloquent models (User, Project, Task, TaskGroup, Label)
├── Providers/            # Service providers (Repository bindings)
├── Repositories/
│   ├── Contracts/        # Repository interfaces
│   └── Eloquent/         # Concrete implementations
└── Services/             # Business logic (ProjectService, TaskSearchService)

database/
├── factories/            # Model factories for testing/seeding
├── migrations/           # Schema definitions
└── seeders/              # Database seeders

docs/
├── system-design.md      # Architecture & deployment diagrams
├── security-hardening.md # Security audit & hardening guide
├── debugging-challenge.md# Debugging scenarios & solutions
└── example-payload.json  # Sample API request payload

tests/
├── Feature/              # Feature (integration) tests
└── Unit/                 # Unit tests
```

---

## 📖 Documentation

| Document                                           | Description                                  |
| -------------------------------------------------- | -------------------------------------------- |
| [System Design](docs/system-design.md)             | Architecture, DB schema, caching, deployment |
| [Security Hardening](docs/security-hardening.md)   | Security audit and hardening measures        |
| [Debugging Challenge](docs/debugging-challenge.md) | Debugging scenarios and solutions            |
| [Example Payload](docs/example-payload.json)       | Sample nested project creation request       |

---

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
