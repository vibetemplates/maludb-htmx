# Template Conversion Plan — HTMX/PHP/Bootstrap Starter Template

## Goal

Convert this application (ZozoCal restaurant reservations + professional scheduling +
SalesCoach/prospect features) into a clean, generic starter template (`maludb-htmx`)
for launching new projects. Keep the generic infrastructure; remove domain-specific
features; genericize branding, config, and the database schema.

> Note: the previous contents of this file (MySQL → PostgreSQL migration plan) were
> preserved in `tasks/postgres-migration-todo.md`.

## Codebase Review Summary (2026-06-06)

The repo contains several overlapping product generations:

| Layer | What it is | Status |
|---|---|---|
| Restaurant reservations (ZozoCal) | Original app: reservations, tables, waitlist, guests, sections | Domain-specific, remove |
| Professional scheduling | `product_type='professional'` mode: appointments, clients, services, availability | Domain-specific, remove |
| SalesCoach Pro / prospects | Prospects, products, scoring, agents, affiliate program | Domain-specific, remove |
| AI integrations | Retell voice, Twilio SMS, OpenAI SMS/email agents, MCP servers | Decision needed (keep as optional module vs. remove) |
| Generic core | Auth/session/CSRF, multi-tenant model, nav permissions, settings, teams/orgs, billing/plans, notifications, dashboard shell, HTMX partial pattern, Bootstrap/Kobie design system | **Keep — this IS the template** |

Key findings:
- **Stale/conflicting docs**: `README.md` describes SalesCoach Pro build prompts; `requirements.md` is the professional-scheduling spec; `old_requirements.md` is SalesCoach; `design-notes.md` is an unrelated LangGraph research-agent doc; restaurant requirements in 3 formats. All need replacing with template docs.
- **Hardcoded secrets**: `config/database.php` (PostgreSQL host/user/password), `config/google-oauth.php` (client ID/secret). Must move to an untracked local config / env pattern with committed `*.sample` files.
- **Branding**: "ZozoCal"/"Drajeo", zozocal.com, logo/favicon hardcoded across `login.php`, `app.php`, `privacy.php`, landing pages.
- **DB mismatch**: docs/schema say MySQL/MariaDB, but `config/database.php` points at PostgreSQL (per the partially-completed migration in `tasks/postgres-migration-todo.md`). Template must pick one (or document both).
- **Tenant naming**: the tenant root table is `restaurants` (with `user_restaurants`). Renaming to something generic (`accounts`) is the "right" template shape but is a large refactor — decision needed.

## Open Decisions (need verification before work starts)

1. **In-place vs. fresh**: strip this repo in place (preserves history/CLAUDE workflow) — recommended — or assemble a clean copy?
2. **Tenant naming**: keep `restaurants`/`user_restaurants` internally (simple, zero-risk) or rename to `accounts`/`user_accounts` (cleaner template, big refactor)?
3. **AI integrations** (Retell/Twilio/OpenAI/MCP): remove entirely, or keep as documented optional modules?
4. **Database target**: PostgreSQL (current config) or MySQL/MariaDB (docs, original schema)?
5. **Billing/plans/affiliate**: keep billing+plans as template features? Affiliate program seems product-specific — remove?

## Todo Items

### Phase 1 — Documentation reset
- [ ] Replace `README.md` with template overview (stack, structure, getting started)
- [ ] Remove stale product docs: `old_requirements.md`, `requirements.md`, `design-notes.md`, restaurant requirements (md/docx), SalesCoach prompts, `docs/retell-*`, `docs/mcp-pro-prompt-snippet.md`, `docs/kobie-migration-verification.md` (per decisions above)
- [ ] Keep/update: `tech-stack.md`, `CLAUDE.md`, `design/` (untouched, per rules)
- [ ] Write `docs/template-guide.md`: how to start a new project from this template

### Phase 2 — Config & secrets *(done early, 2026-06-06 — before first push)*
- [x] `config/database.php` gitignored; committed `config/database.sample.php` with placeholder credentials
- [x] `config/google-oauth.php` gitignored; committed `config/google-oauth.sample.php`
- [x] Added `.gitignore`: local config, `logs/*` (webhook logs contain API keys), `.claude/settings.local.json`, `.DS_Store`, downloads
- [x] Removed hardcoded Retell API keys from `helpers/retell-auth.php` (env fallback) and `scripts/launch-agent.php` (env vars + startup check)
- [x] Verified: full-tree scan of all committable files finds no credentials (DB password, Google secret, Retell/OpenAI/Twilio key patterns)
- [ ] Add a single `config/app.php` for branding: app name, support email, logo path, color theme

### Phase 3 — Remove domain modules (html/)
- [ ] Remove restaurant partials: `reservations/`, `tables/`, `guests/`, `waitlist/`, `sections/`, meal-status
- [ ] Remove professional module: `partials/professional/`, `pro-booking/`, professional helpers
- [ ] Remove prospect/SalesCoach module: `prospects/`, `products/`, `scoring/`, `agents/`, `sessions/`, `coaching-calendar/`, related models
- [ ] Remove affiliate module (pending decision)
- [ ] Remove/extract AI integration code (pending decision): `api/retell/`, `api/sms/`, `api/mcp/`, voice/SMS/OpenAI helpers, `scripts/`
- [ ] Remove `booking/`, `cron/` invoice jobs, domain landing pages
- [ ] Remove domain helpers: `restaurant.php`, `availability.php`, `voice-api.php`, etc.

### Phase 4 — Genericize what remains
- [ ] De-brand: replace ZozoCal/Drajeo references with `config/app.php` values in `login.php`, `register.php`, `app.php`, `privacy.php`, `terms.php`, landing page
- [ ] Clean `app.php` navigation down to generic items (Dashboard, Tasks, Calendar, Team, Settings, Admin)
- [ ] Tenant naming refactor (pending decision #2)
- [ ] Verify every kept page loads without removed dependencies
- [ ] Ensure all div tags in kept HTML files have unique ids (CLAUDE.md rule 11)

### Phase 5 — Template database schema
- [ ] Produce one canonical `sql/template_schema.sql` with only generic tables (users, tenants, user↔tenant roles, settings, nav_permissions, teams, tasks, events, activity, notifications, plans/subscriptions per decision)
- [ ] Remove stale SQL: `restaurant_reservations.sql`, `docs/migrations/001-salescoach-schema.sql`, domain files in `docs/sql/`
- [ ] Update `database-documentation.md` to match the template schema

### Phase 6 — Verify & wrap up
- [ ] Smoke-test: register → login → dashboard → settings → team CRUD → logout
- [ ] Test utilities (`test-db.php`, `test-email.php`) work or are removed
- [ ] Update `docs/activity.md`, commit and push
- [ ] Add Review section below

## Review

*(To be completed after work is done)*
