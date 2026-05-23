# DevBoard API

> REST API backend for DevBoard — a SaaS-style Kanban project management tool.

[![API CI](https://github.com/Fayaj-Nakib/devboard-api/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/Fayaj-Nakib/devboard-api/actions/workflows/ci-cd.yml)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)](https://postgresql.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## What is DevBoard?

DevBoard is a full-stack project management tool inspired by tools like Linear and Trello. Teams can create **workspaces**, organise work into **projects**, and manage **tasks** on a drag-and-drop Kanban board.

This repository is the **Laravel 13 REST API**. The Next.js frontend lives in a separate repo — see [devboard-web](https://github.com/Fayaj-Nakib/devboard-web).

**Live demo:** https://devboard.vercel.app  
**API base URL:** https://devboard-api.onrender.com/api

---

## Tech Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 |
| Auth | Laravel Sanctum (token-based) |
| Database | PostgreSQL 16 (Neon on prod) |
| Queue | Database driver (sync on free tier) |
| Mail | Gmail SMTP / log driver (dev) |
| Containerisation | Docker (Render deployment) |
| CI/CD | GitHub Actions — PHPUnit + Pint |

---

## Architecture

```
┌──────────────────────────────────────────────────────┐
│                   Client Browser                     │
└──────────────────────┬───────────────────────────────┘
                       │  HTTPS
                       ▼
┌──────────────────────────────────────────────────────┐
│          Next.js 16 Frontend  (Vercel)               │
│   React 19 · Tailwind CSS 4 · @hello-pangea/dnd      │
└──────────────────────┬───────────────────────────────┘
                       │  REST + Bearer token
                       ▼
┌──────────────────────────────────────────────────────┐
│          Laravel 13 API  (Render / Docker)           │
│   Sanctum · Eloquent · Queue jobs · Mailable         │
└────────────┬─────────────────────────┬───────────────┘
             │                         │
             ▼                         ▼
┌─────────────────────┐   ┌────────────────────────┐
│  PostgreSQL (Neon)  │   │  Gmail SMTP (email)    │
└─────────────────────┘   └────────────────────────┘
```

---

## API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/register` | — | Register a new user |
| POST | `/api/auth/login` | — | Log in, receive token |
| POST | `/api/auth/logout` | ✓ | Revoke current token |
| GET | `/api/auth/me` | ✓ | Authenticated user profile |
| GET/POST | `/api/workspaces` | ✓ | List / create workspaces |
| GET/PATCH/DELETE | `/api/workspaces/{id}` | ✓ | Show / update / delete workspace |
| POST | `/api/workspaces/{id}/members` | ✓ | Add member to workspace |
| DELETE | `/api/workspaces/{id}/members/{user}` | ✓ | Remove member |
| GET/POST | `/api/workspaces/{ws}/projects` | ✓ | List / create projects |
| GET/PATCH/DELETE | `/api/workspaces/{ws}/projects/{id}` | ✓ | Show / update / delete project |
| GET/POST | `/api/workspaces/{ws}/projects/{p}/tasks` | ✓ | List / create tasks |
| GET/PATCH/DELETE | `/api/workspaces/{ws}/projects/{p}/tasks/{id}` | ✓ | Show / update / delete task |
| PATCH | `/api/workspaces/{ws}/projects/{p}/tasks/reorder` | ✓ | Reorder all tasks in a project |
| GET/POST | `/api/tasks/{task}/comments` | ✓ | List / add comments |
| DELETE | `/api/tasks/{task}/comments/{id}` | ✓ | Delete comment |
| GET | `/api/notifications` | ✓ | Unread notifications |
| PATCH | `/api/notifications/read-all` | ✓ | Mark all read |
| PATCH | `/api/notifications/{id}/read` | ✓ | Mark one read |

All protected routes require `Authorization: Bearer {token}` header.

---

## Local Setup

### Prerequisites

- PHP 8.3+
- Composer 2
- PostgreSQL 14+ running locally

### Steps

```bash
# 1. Clone
git clone https://github.com/Fayaj-Nakib/devboard-api.git
cd devboard-api

# 2. Install PHP dependencies
composer install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Set your database credentials in .env
#    DB_DATABASE=devboard
#    DB_USERNAME=postgres
#    DB_PASSWORD=your_password

# 5. Run migrations
php artisan migrate

# 6. Start the dev server
php artisan serve
```

The API will be available at `http://localhost:8000`.

### Running Tests

Tests require a separate PostgreSQL database (`devboard_test` by default — see `phpunit.xml`).

```bash
# Create the test database first
createdb devboard_test

# Run all tests
php artisan test

# Run a specific suite
php artisan test tests/Feature/AuthTest.php
php artisan test tests/Feature/TaskTest.php
```

There are 25 feature tests covering auth (10) and task management (15). All pass against a live PostgreSQL instance.

### Code Style

```bash
# Check style (Pint)
vendor/bin/pint --test

# Auto-fix
vendor/bin/pint
```

---

## Deployment

The API is deployed as a Docker container on **Render** connected to a **Neon** PostgreSQL database.

```bash
# Render reads render.yaml at the repo root automatically.
# Set these env vars manually in the Render dashboard:
#   APP_URL, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD,
#   MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, FRONTEND_URL
```

Key production settings (already in `render.yaml`):
- `QUEUE_CONNECTION=sync` — no background worker needed on the free tier
- `DB_SSLMODE=require` — Neon enforces SSL
- `MAIL_MAILER=smtp` — Gmail App Password for transactional email

---

## A Note on Blade Files

GitHub's language detector reports ~40% Blade because of a single email template at `resources/views/emails/task-assigned.blade.php`. This is a **pure JSON API** — there are no server-rendered HTML pages. The email template is the only Blade file in the project.

---

## Why These Choices?

**Laravel over Node/Express** — Eloquent ORM, Sanctum, artisan commands, and first-class queue support let a small team ship a complete backend quickly without glue code. The conventions are well-understood and the ecosystem is mature.

**Sanctum over Passport/JWT** — Sanctum token auth is simple, stateless, and sufficient for a SPA + API setup. No OAuth complexity needed for a v1.

**PostgreSQL over MySQL** — UUID primary keys, stricter type enforcement, and Neon's serverless free tier made Postgres the obvious choice. SQLite was ruled out because it silently accepts invalid UUIDs (a real bug this project hit during development).

**Neon (serverless Postgres)** — Free tier with no idle spin-down penalty, unlike a traditional VPS database.

**Render over Railway/Fly** — Docker-native deploys, free tier web services, and `render.yaml` for infrastructure-as-code.

---

## CI/CD

Two jobs run on every push and PR to `main` / `develop`:

1. **PHPUnit Tests** — spins up a Postgres 16 service container, runs `php artisan test`
2. **Code Style (Pint)** — runs `vendor/bin/pint --test`, fails on style violations

Add the badge to your GitHub profile by replacing `Fayaj-Nakib` in the badge URL at the top of this file.
