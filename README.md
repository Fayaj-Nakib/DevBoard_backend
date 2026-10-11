# DevBoard API

> REST API backend for DevBoard — a SaaS-style Kanban project management tool.

[![API CI](https://github.com/Fayaj-Nakib/devboard-api/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/Fayaj-Nakib/devboard-api/actions/workflows/ci-cd.yml)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)](https://postgresql.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## Overview

DevBoard is a multi-tenant project management API in the vein of Linear or Jira: users create **workspaces**, organize work into **projects**, and manage **tasks** on a Kanban board with sprints, milestones, and a backlog. Every workspace is isolated from every other — access is gated by a shared role check on every project-scoped controller, verifying both the caller's role *and* that the project being accessed actually belongs to the workspace in the URL. On top of that sits an event-driven automation engine (trigger → queued action → logged result), HMAC-signed outbound webhooks with retries, a GitHub integration that syncs PR/issue state onto tasks, and TOTP-based two-factor auth. This repository is the Laravel 13 REST API; the Next.js frontend lives in a separate repo — see [devboard-web](https://github.com/Fayaj-Nakib/devboard-web).

**Live demo:** https://devboard-frontend.vercel.app
**API base URL:** https://devboard-backend-50cx.onrender.com/api
**Demo login:** _add a seeded demo account here (email / password) before sharing this README — none is documented yet._

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

Every API controller that takes `{workspace}` and `{project}` route parameters runs through `Controller::projectGate()` / `projectRoleGate()`, which checks the caller's role in the workspace **and** that the project actually belongs to it, before any query touches the database. See `app/Http/Controllers/Controller.php`.

---

## Features

- **Two-tier RBAC** — workspace-level roles (owner/admin/member/guest) plus a second, per-project tier (viewer/editor/manager) for guests who need scoped access to a single project without full workspace membership.
- **Event-driven automation engine** — per-project rules (5 trigger types: task created, status changed, due date reached, assignee added, comment added) fire queued actions (change status, assign user, add label, post comment, send notification), with a persisted success/failure log per execution.
- **Real-time updates** — task, comment, sprint, and milestone changes broadcast over private Pusher channels (per workspace/project/user), so connected clients update without polling.
- **Signed outbound webhooks** — HMAC-SHA256 signed payloads (`X-DevBoard-Signature`), a per-delivery audit trail, and a 3-attempt backoff schedule (30s/120s/300s) on failed deliveries.
- **GitHub integration** — connect a repo per project; inbound webhooks (verified via `X-Hub-Signature-256`) sync PR merge/close and issue-closed state back onto linked tasks.
- **TOTP two-factor authentication** — encrypted secrets and recovery codes (`pragmarx/google2fa-laravel`), with a login-challenge flow for 2FA-enabled accounts.
- Sprints, milestones, backlog, calendar/timeline/workload views, custom fields, time tracking, and CSV/JSON import-export round out the project-management surface.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Language | PHP 8.3 |
| Auth | Laravel Sanctum (token-based) + TOTP 2FA |
| Database | PostgreSQL 16 (Neon on prod) |
| Real-time | Pusher (private channels) |
| Queue | Database driver locally; **sync on Render's free tier** — queued jobs run inline in production, not on a background worker |
| Mail | Gmail SMTP / log driver (dev) |
| Containerization | Docker (Render deployment) |
| CI/CD | GitHub Actions — PHPUnit against a real Postgres service + Pint lint |

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

Tests run against an isolated database (`devboard_test` by default — see `phpunit.xml`), never the dev database. **Make sure `phpunit.xml`'s `DB_PASSWORD` matches your local Postgres password** — it ships with a placeholder that won't match a freshly-created `.env`.

```bash
# Create the test database first
createdb devboard_test

# Run all tests
php artisan test

# Run a specific suite
php artisan test tests/Feature/AuthTest.php
php artisan test tests/Feature/TaskTest.php
php artisan test tests/Feature/TenantIsolationTest.php
```

There are 76 feature tests: 10 covering auth, 15 covering task management, and 51 regression tests (`TenantIsolationTest`) asserting that no workspace can reach another workspace's projects, tasks, milestones, sprints, statuses, templates, automation rules, or stats. All pass against a live PostgreSQL instance.

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
- `QUEUE_CONNECTION=sync` — no background worker needed on the free tier, but it also means automation/webhook/notification jobs run synchronously on the request thread rather than asynchronously. Move to a paid plan with a worker process (and switch to `database` or `redis`) before relying on this for anything latency-sensitive.
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
