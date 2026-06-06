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

## Decisions (verified 2026-06-06)

1. **In-place**: strip this repo in place. ✔
2. **Remove**: Restaurant module, Prospects/SalesCoach module, Affiliate module. ✔
3. **Keep**: Professional module screens (appointments, clients, services, availability, time off). ✔
4. **Unified screen**: every user gets the same screen regardless of role, product_type, location type, or settings — remove role/product-type branching from the UI. ✔
5. **Database**: PostgreSQL 17 + MaluDB extensions (per README). ✔
6. **Deferred**: AI integrations (Retell/Twilio/OpenAI/MCP) keep-vs-remove; tenant table renaming (`restaurants` → ?).

## Todo Items

### Phase 1 — Documentation reset
- [x] Replace `README.md` with template overview — "MaluDb Design Template for Claude Code" (stack, structure, getting started, Claude Code workflow)
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
- [x] Remove restaurant partials: `reservations/`, `tables/`, `guests/`, `waitlist/`, `sections/`, `booking/`
- [x] ~~Remove professional module~~ KEPT per decision — professional screens are the template's example module
- [x] Remove prospect/SalesCoach partials: `prospects/`, `products/`, `scoring/`, `sessions/`, `coaching-calendar/`, dashboard widgets (models cleanup still pending)
- [x] Remove affiliate partials: `partials/affiliate/`
- [ ] Remove/extract AI integration code (pending decision): `api/retell/`, `api/sms/`, `api/mcp/`, voice/SMS/OpenAI helpers, `scripts/`
- [ ] Remove `booking/`, `cron/` invoice jobs, domain landing pages
- [ ] Remove domain helpers: `restaurant.php`, `availability.php`, `voice-api.php`, etc.

### Phase 4 — Genericize what remains
- [x] De-brand `app.php`, `login.php`, `register.php` (MaluDB text brand) — `privacy.php`, `terms.php`, landing page still pending
- [x] Clean `app.php` navigation to one unified menu for all users; new generic dashboard showcase at `partials/dashboard/index.php`
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

---

# MaluDB Memory Pages Plan (2026-06-06)

## Context

`/var/www/v1` (reference copy, not deployed here) contains the 55 PHP endpoints of the
MaluDB REST API. These define the canonical field names, SQL, and DB facades for every
memory entity. The Memory Elements pages in `html/partials/memory/` will adapt that
endpoint logic to the HTMX-partial pattern, exactly as was already done for Projects
(`projects.php` ← `/v1/projects`). The live DB confirms: `maludb_person` view exists with
the same shape as `maludb_project`; `maludb_subject_type` (13 trigger-enforced values) and
`maludb_verb_type` (30 values) feed the type dropdowns.

## Phase A — Wire the five remaining Memory Element pages

- [x] **People** (`people.php` ← v1/projects pattern + `maludb_person` view): list/search
      (canonical_name, description ILIKE), create (INSERT maludb_subject, subject_type='person'),
      edit (name/description/classifier_md), archive/restore via archived_at — a near-clone of
      the wired Projects page.
- [x] **Subjects/Things** (`subjects.php` ← v1/subjects*): list with type badge +
      linked_verbs / related_subjects counts; Type dropdown from `maludb_subject_type`;
      create/edit (label, type, description, classifier_md); delete. Detail view (offcanvas or
      expandable row): linked verbs + related subjects (from `maludb_subject_relationship`).
- [x] **Verbs/Actions** (`verbs.php` ← v1/verbs*): list with linked_subjects count; Type
      dropdown from `maludb_verb_type`; create/edit (canonical_name, type, description,
      classifier_md); delete; detail shows linked subjects.
- [x] **Events/Episodes** (`episodes.php` ← v1/episodes*): list (title, kind, occurred_at,
      sensitivity, provenance) with kind filter from `maludb_episode_type`; create via
      `maludb_register_episode(...)` facade; edit title/summary/kind/occurred_at/occurred_until/
      sensitivity; delete. Needs a small `db_tx_core()`-style helper (transaction with
      maludb_core on search_path) added to the partials' shared include.
- [x] **Documents** (`documents.php` ← v1/documents*): list (title, document_type, media_type,
      size, created_at); upload form (file + title + description + document_type picker +
      project/subject links via `document_link_subject` graph facade); delete (document +
      source package + graph edges). Document type dropdown from `maludb_document_type`.

## Phase B — MaluDB Setup / maintenance forms (new nav group)

New sidebar heading **MALUDB SETUP** under Memory Elements, partials in
`html/partials/memory/setup/`:

- [x] **Episode Types** — full CRUD (label, description, display_order) on `maludb_episode_type`
- [x] **Document Types** — full CRUD on `maludb_document_type`
- [x] **Subject Types** — list view of `maludb_subject_type` (API is read-only; values are
      trigger-enforced)
- [x] **Verb Types** — list view of `maludb_verb_type` (read-only)
- [x] **Attribute Templates** — the form catalog (`maludb_attribute_template`): list filtered by
      applies_to/type_value, create (applies_to, type_value, attr_name, value_type, requirement,
      label, unit, allowed_values, default, display_order), delete (API has no PATCH — re-create
      to change)
- [x] **Memory Config** — model/embedding/prompt setup per namespace (`maludb_memory_model_config`
      read; configure via secret_set + register provider/alias + set_model_config facades);
      token stored encrypted, never displayed
- [ ] *(optional, decide)* Pools, Skills, Notes/Issues, Statements review queue
      (provenance=suggested accept/reject)

## Phase C — Wrap up

- [ ] Unique ids on every div (CLAUDE.md rule 11); `php -l` on all files
- [ ] Update `docs/activity.md`; commit & push

## Review

*(To be completed after work is done)*
