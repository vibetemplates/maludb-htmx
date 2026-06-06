# MaluDb Design Template for Claude Code

## Overview

This repository is a starter/design template for building multi-tenant web applications on a traditional LAMP stack with HTMX and Bootstrap 5, developed with Claude Code. It provides the generic infrastructure every project needs — authentication, multi-tenant accounts and roles, navigation permissions, settings, teams, dashboards, and the HTMX partial architecture — so new projects start from a working application shell instead of an empty folder.

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+ (no framework, PDO prepared statements) |
| Database | PostgreSQL (PDO singleton connection) |
| Web server | Apache 2.4+ (`html/` is the document root) |
| Frontend | HTMX 2.0 + Bootstrap 5.3 + Bootstrap Icons |
| Pattern | Server-rendered HTMX partials (`html/partials/[module]/[action].php`) |

See `tech-stack.md` for full details and rationale.

## Repository Structure

```
/var/www/
├── html/              # Web server document root (all deployed files)
│   ├── partials/      # HTMX partial endpoints, one folder per module
│   ├── api/           # API endpoints
│   └── assets/        # CSS, JS, images, vendor libraries
├── config/            # Configuration (gitignored; copy from *.sample.php)
├── helpers/           # Shared PHP utilities (auth, session, CSRF, db, ui, dates)
├── models/            # Database model classes
├── design/            # Bootstrap 5 design reference — READ ONLY, do not modify
├── docs/              # Documentation and activity log
├── tasks/             # Claude Code planning documents (todo.md)
├── sql/               # Database schema
└── logs/              # Application logs (gitignored)
```

## Getting Started

1. **Clone the repository** into your web root (e.g. `/var/www`).

2. **Create local config files** from the committed samples (these are gitignored and must never be committed):

   ```bash
   cp config/database.sample.php config/database.php
   cp config/google-oauth.sample.php config/google-oauth.php
   ```

   Fill in your database credentials and (optionally) Google OAuth credentials.

3. **Create the database** and load the schema from `sql/`.

4. **Point Apache** at `html/` as the document root.

5. **Verify** by visiting `/test-db.php` to confirm the database connection, then `/login.php`.

## Working with Claude Code

This template is designed to be driven by Claude Code. The workflow rules live in `CLAUDE.md`:

- Plans are written to `tasks/todo.md` and verified before work begins
- Every action is logged in `docs/activity.md`
- The `design/` folder is the visual reference and is never modified
- Changes are kept as simple as possible and pushed to git when complete
- Every `div` in HTML files carries a unique `id` so styling changes can be requested by id

## Key Documents

- `CLAUDE.md` — Claude Code workflow rules for this repository
- `tech-stack.md` — Full technology stack documentation
- `database-documentation.md` — Database schema documentation
- `tasks/todo.md` — Current plan and progress
- `docs/activity.md` — Chronological log of all changes
