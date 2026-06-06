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
6. **Deferred**: AI integrations (Retell/Twilio/OpenAI/MCP) keep-vs-remove. ~~Tenant table renaming~~ DONE 2026-06-06: `restaurants` → `companies` via COPY (see "Plan — 2026-06-06 (Tenant rename)" section below).

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
- [x] Tenant naming refactor — done 2026-06-06 (restaurants → companies COPY; see rename plan section below)
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

---

# Fix Batch — 2026-06-06 (todos 500, setup CRUD/buttons, nav cleanup)

## Findings

- **Todos 500 root cause (confirmed):** DB is PostgreSQL, but
  `html/partials/todos/list.php` uses MySQL-only `FIELD()` in its ORDER BY
  (verified live: `SQLSTATE[42883] Undefined function: field(...)`).
- **Stacked buttons:** in `html/partials/memory/setup/_type-crud.php` the
  Edit/Delete buttons sit loose in `<td class="text-end">`; template CSS for
  `.btn-icon` makes them stack. (Shared by episode-types & document-types.)
- **Subject/Verb types:** `maludb_subject_type` / `maludb_verb_type` have a
  text key (`subject_type` / `verb_type`) + `display_name`, `description`,
  `sort_order`, `system_defined` (verb also has `semantic_class`) — different
  shape from `_type-crud.php` (numeric id + label), so they get their own CRUD.

## Todos

- [x] 1. `todos/list.php`: replace `FIELD(priority,...)` with portable
      `CASE priority WHEN 'high' THEN 1 ...` expressions (3 spots in the sort map)
- [x] 2/3. `_type-crud.php`: wrap row action buttons in a
      `d-inline-flex gap-1` div so they sit side by side
      (fixes both episode-types and document-types lists)
- [x] 4a. `subject-types.php`: full CRUD (list + modal form + save + delete),
      keyed on `subject_type`, fields: type, display_name, description, sort_order
- [x] 4b. `verb-types.php`: same, keyed on `verb_type`, plus `semantic_class` field
- [x] 5. `app.php`: remove "Memory Config" nav item (file itself stays)
- [x] 6. `app.php`: remove "Reports", "Billing", the "PLATFORM" caption and all
      five PLATFORM items (partials stay; nav links only)
- [x] `php -l` all touched files, update `docs/activity.md`, commit & push

## Review

- **Todos 500 fixed**: `FIELD()` (MySQL-only) replaced with a portable CASE rank;
  all three sort variants + counts query verified live against PostgreSQL.
- **Buttons side-by-side**: shared `_type-crud.php` row actions now wrapped in
  `d-inline-flex gap-1` (episode-types + document-types).
- **Subject/Verb Types CRUD**: new shared `_registry-crud.php` scaffold
  (mirrors `_type-crud.php` conventions: htmx modal via `#modal-container`,
  `closeModal` trigger, duplicate guard); both pages converted to thin config
  includes. Full CRUD on all rows incl. system-defined (user decision);
  new rows insert `system_defined = false`.
- **Nav cleanup**: Memory Config, Reports, Billing, and the whole PLATFORM
  section removed from `app.php`; the partial files were left in place.
- **⚠ DB grants needed**: `maludb_subject_type` / `maludb_verb_type` views grant
  only SELECT to `zozocal` (verified: INSERT → `42501 permission denied`).
  The view owner must run
  `GRANT INSERT, UPDATE, DELETE ON maludb_subject_type, maludb_verb_type TO zozocal;`
  before saves/deletes on those two pages will succeed (until then they show a
  clean "Save failed: permission denied" alert).

## Review — 2026-06-06 (500-error fixes)

- [x] check-slots 500: `getProfessionalAppointmentBlocks()` MySQL `DATE_SUB`/`DATE_ADD` → Postgres `make_interval(mins => ...)` (helpers/professional-availability.php)
- [x] Remaining MySQL `FIELD()` sorts → portable `CASE` (html/api/v1/todos.php, html/api/mcp/pro-tools.php)
- [x] reports-data 500: `HAVING visit_count > 0` alias → full aggregate expression (Postgres disallows aliases in HAVING)
- [x] save-appointment 500: missing `vendor/` — ran `composer install --no-dev`; added `vendor/` to .gitignore

All queries verified directly against the live PostgreSQL database; slot generation returns results for a real service/restaurant pair. Note for the template-conversion effort: more MySQL-isms may lurk in not-yet-exercised code paths — grep for `DATE_SUB|DATE_ADD|FIELD(|IFNULL|GROUP_CONCAT|ON DUPLICATE` when touching a module.

## Review — 2026-06-06 (Business section button stacking)

- [x] Broadened `.table td .btn-icon` override to `.table td .btn { display: inline-flex; }` in kobie-custom.css — theme's global `.btn { display: flex }` stacked plain buttons in Services/Availability/Time Off table rows.

## Plan — 2026-06-06 (Side nav: Documents move, Token Setup, Model Prompts)

Confirmed with Ed: client tables go in the **public schema** (user-application tables, part
of the template); `api_key` stored plaintext (never echoed back to the UI); tokens.php-style
HTTP auth tokens are NOT needed now, but an MCP server interfacing with Retell AI is coming —
the Token Setup page and `client_token` design should leave room for a future
`client_api_token` (sha256-hashed, shown-once) table for MCP auth.

### Todo
- [x] 1. DDL: create `public.client_token` (LLM provider connections + API keys) and
        `public.client_model_prompt` (per-model system prompt → FK to client_token), mirroring
        what `v1/memory_ingest.php` reads from `LocalDatabase::modelPrompt()`. Save DDL to
        `docs/sql/client_tokens_model_prompts.sql` and run it against the live Postgres.
- [x] 2. `html/app.php`: move the Documents nav item (`#nav-memory-documents`) from MEMORY
        ELEMENTS to directly below Todo List (`#nav-todos`).
- [x] 3. `html/app.php`: remove Staff Users (`#nav-staff`); add "Token Setup"
        (`#nav-token-setup`) in the same ADMIN slot → `/partials/settings/token-setup.php`.
- [x] 4. `html/app.php`: add "Model Prompts" (`#nav-model-prompts`) below Verbs/Actions in
        MEMORY ELEMENTS → `/partials/memory/model-prompts.php`.
- [x] 5. Create `html/partials/settings/token-setup.php` — CRUD on `client_token`
        (name, api_format openai|anthropic, base_url, api_key as password field; key masked in
        list; modeled on the `_type-crud.php` setup-page pattern).
- [x] 6. Create `html/partials/memory/model-prompts.php` — CRUD on `client_model_prompt`
        (model, token dropdown, model_identifier, system_prompt, max_tokens,
        generation_params JSON).
- [x] 7. Log actions in docs/activity.md; commit and push.

### Review

All seven items complete. `v1/memory_ingest.php`'s MySQL dependency (`LocalDatabase::modelPrompt`)
is now reproducible client-side: `public.client_token` ⋈ `public.client_model_prompt` yields the
exact $cfg fields it passes to llm_complete(). Nav: Documents sits under Todo List, Token Setup
(feather-key) replaced Staff Users in ADMIN, Model Prompts (feather-message-square) sits under
Verbs/Actions. Both new pages follow the existing HTMX modal CRUD pattern with unique div ids.
API keys: required on create, write-only thereafter (never selected back into the form), masked
in the list. Deleting a token in use by prompts surfaces a friendly FK message.

Notes:
- `partials/settings/users.php` (Staff Users page) still exists — only the nav link was removed.
- Future: `client_api_token` (sha256-hashed, shown-once) for the planned Retell AI MCP server,
  to be managed from the same Token Setup page (noted in the DDL file header).
- An in-app ingest flow (replacing POST /v1/memory/ingest) can now be built on these tables —
  not in scope this round.

## Plan — 2026-06-06 (Minimum database setup / install script)

Confirmed with Ed: proceed with whatever is necessary.

### Todo
- [x] 1. Write `docs/sql/install.sql` — idempotent DDL for the 14 template tables (users,
        restaurants, user_restaurants, settings, professional_profiles, professional_services,
        professional_availability_rules, professional_time_off, professional_clients,
        professional_appointments, todos, api_tokens, client_token, client_model_prompt),
        faithful to the live schema incl. FKs/uniques/indexes. Header documents prerequisites
        (create DB/schema → install maludb memory functions → run script).
- [x] 2. Trim legacy inserts (sections, turn_times, operating_hours, affiliates) from
        html/partials/auth/register.php and google-complete.php; slim the seeded settings to
        keys the template actually reads.
- [x] 3. Fix MySQL `ON DUPLICATE KEY UPDATE` → Postgres `ON CONFLICT` in
        html/partials/settings/notifications.php.
- [x] 4. Verify: run install.sql into a scratch schema (all 14 tables created); php -l all
        changed files.
- [x] 5. docs/activity.md, commit, push.

### Review

`docs/sql/install.sql` defines the full minimum footprint: 14 tables (auth/tenancy 4,
profile 1, scheduling 5, todos 1, api_tokens 1, maludb-client 2), faithful to the live DDL
including FKs/uniques/indexes; idempotent. No static seed rows — registration seeds per-tenant
settings. Excluded deliberately: nav_permissions (no callers since the unified nav),
password_resets (forgot-password is a UI shell), all maludb_* (system install), all
restaurant/voice/SMS/affiliate legacy tables.

Code fixes required for a clean fresh install:
- register.php + google-complete.php no longer INSERT into sections/turn_times/
  operating_hours/affiliates; settings seed slimmed 15 → 5 keys the template reads.
  (One deviation from live: users.product_type default is 'professional' in install.sql.)
- notifications.php: 3× MySQL ON DUPLICATE KEY UPDATE → ON CONFLICT (restaurant_id,
  setting_key) DO UPDATE (page was broken on Postgres).

Verified: install.sql executed twice into a scratch schema (14 tables, idempotent);
registration-flow inserts, the ON CONFLICT upsert, and client token/prompt inserts all
exercised against the fresh schema, then rolled back and schema dropped.

---

# Plan — 2026-06-06 (Tenant rename: restaurants → companies, COPY not rename)

Resolves deferred decision #6 of the template-conversion plan.

## Decisions confirmed with Ed
- Generic tenant name: **companies** / **user_companies** (matches the existing
  "Company Not Setup" header fallback). Session key: `current_company_id`.
- **Do NOT rename/alter/drop the existing `restaurants` and `user_restaurants`
  tables** — another application uses them. Create copies instead; this app
  points at the copies. After the copy, the two table sets diverge by design.
- Full rename in app code (tables, columns, session keys, helpers, file names,
  UI labels).

## Scope rule
Only **live template code** gets renamed. Orphaned domain modules already slated
for deletion in Phase 3 of the template-conversion plan (platform/*, billing/*,
affiliates, retell/sms/email agents, zozocal landing, prospect helpers,
restaurant voice helpers) are NOT renamed — they keep their old references until
they are deleted. The final grep sweep excludes them.

## Open questions (verify before starting)
1. The 9 dependent template tables in install.sql carry a `restaurant_id` column
   FK'd to restaurants(id): settings, professional_profiles, professional_services,
   professional_availability_rules, professional_time_off, professional_clients,
   professional_appointments, todos, api_tokens.
   **Does the other application read/write any of these in the live DB?**
   - If NO (assumed): rename column to `company_id` + repoint FK to companies(id)
     in the live DB.
   - If YES for some: those keep `restaurant_id` in the live DB; only fresh
     installs get `company_id`.
2. `client_token` / `client_model_prompt` have no tenant column — unaffected. ✔

## Todo

### Phase 1 — Database (live DB + install.sql)
- [x] 1. `docs/sql/companies.sql`: Postgres script that
      (a) `CREATE TABLE companies (LIKE restaurants INCLUDING ALL)` + copy data
          (+ sequence sync),
      (b) `CREATE TABLE user_companies` (same shape as user_restaurants but
          column `company_id` FK→companies) + copy data,
      (c) per open question #1: on the 9 dependent tables, RENAME COLUMN
          restaurant_id → company_id, drop FK to restaurants, add FK to companies,
          rename affected indexes/constraints.
      Old tables untouched. Run against live DB.
- [x] 2. `docs/sql/install.sql`: replace restaurants/user_restaurants CREATEs with
      companies/user_companies; restaurant_id → company_id in all 9 dependent
      tables, FKs, uniques, indexes; update header comments. Re-verify in a
      scratch schema (idempotent, twice).

### Phase 2 — Core helpers & session
- [x] 3. `helpers/restaurant.php` → `helpers/company.php`: getCurrentCompanyId(),
      setCurrentCompany(), getCompany(), getUserCompanies(), getUserCompanyRole();
      queries against companies/user_companies. Update all `require` sites.
- [x] 4. `helpers/auth.php`: session key `current_restaurant_id` → `current_company_id`;
      switchRestaurant() → switchCompany(); queries to new tables; update comments.

### Phase 3 — App shell & auth flows
- [x] 5. `html/app.php`: company switcher (getRestaurant→getCompany calls, session
      key, hx-vals `company_id`, "restaurant" labels → "company").
- [x] 6. `partials/auth/switch-restaurant.php` → `switch-company.php` (+ the hx-post
      URL in app.php).
- [x] 7. `partials/auth/register.php`, `google-complete.php`, `forgot-password.php`:
      INSERT/SELECT against companies/user_companies; variable renames.

### Phase 4 — API (html/api/v1/)
- [x] 8. `_bootstrap.php`: join user_companies; auth array keys company_id/company_role
      (api_tokens column per open question #1).
- [x] 9. `auth.php`: token issue from user_companies; login response field company_id.
- [x] 10. Endpoint sweep: appointments, availability, availability-rules, calendar,
      clients, profile, services, time-off, todos — `restaurant_id` → `company_id`
      in SQL + variables.

### Phase 5 — Kept modules (settings, professional, todos, dashboard, pro-booking)
- [x] 11. `partials/settings/`: profile.php, save-profile.php, users.php, save-user.php,
      toggle-user.php, user-form.php, notifications.php (incl. its ON CONFLICT
      (restaurant_id,...) target), token-setup.php if touched.
- [x] 12. `partials/professional/` (all files) + helpers it uses:
      professional-availability.php, professional-booking.php,
      professional-notifications.php, send-professional-reminders.php.
- [x] 13. `partials/todos/` + `partials/dashboard/index.php`.
- [x] 14. `html/pro-booking/` (public booking pages for the kept module).

### Phase 6 — Verify & wrap up
- [x] 15. Grep sweep over live code (excluding design/, orphaned modules listed in
      the scope rule, and historical SQL): zero remaining
      restaurants|user_restaurants|restaurant_id|current_restaurant_id.
- [x] 16. `php -l` all touched files. Smoke-test live: login, company switcher,
      register, settings→profile/users, professional dashboard/calendar, todos,
      API token auth.
- [x] 17. docs/activity.md, commit & push.

## Explicitly NOT touched
- `restaurants` / `user_restaurants` tables in the live DB (other application)
- `design/` folder
- Orphaned domain modules pending Phase-3 deletion (see scope rule)

## Review

**Database (docs/sql/companies.sql — executed against live PostgreSQL):**
- `companies` (21 rows) and `user_companies` (13 rows) created as faithful copies of
  restaurants/user_restaurants — same ids, sequences synced. Old tables untouched
  (verified post-migration: 21/13 rows intact).
- `restaurant_id` → `company_id` renamed + FKs repointed to companies(id) on the 9
  template tables (settings, professional_profiles/services/availability_rules/
  time_off/clients/appointments, todos, api_tokens). Index/constraint names renamed
  to match. Legacy tables (reservations, guests, restaurant_prompts, invoices,
  activity_log, ...) keep their restaurant_id FKs to restaurants.
- install.sql fully genericized (companies/user_companies/company_id); re-verified by
  running it twice into a scratch schema (15 tables, idempotent), then dropped.

**Code (~75 files changed; php -l clean across all of them):**
- `helpers/restaurant.php` → `helpers/company.php`: getCompanyId/setCompanyId/
  getCompany/getCompanyBySlug/getUserCompanies/getUserRole/applyCompanyTimezone/
  getCompanySetting (new). Deprecated alias block (getRestaurant, getUserRestaurants,
  getRestaurantSetting in availability.php, switchRestaurant + currentRestaurantId in
  auth.php, ...) keeps the orphaned legacy modules limping until their Phase-3 deletion.
- Session keys: current_restaurant_id/name → current_company_id/name (auth.php,
  app.php, save-profile.php, all kept partials).
- app.php company switcher → switch-company.php with company_id hx-vals; registration
  forms post company_name; register/google-complete/forgot-password write
  companies/user_companies.
- api/v1: token auth joins user_companies; auth/context/response keys company_id +
  company_role; all 11 endpoint files swept.
- Kept modules swept: settings (profile/users/notifications/save-user/toggle-user/
  user-form), professional (all 26 partials + 4 helpers), todos, dashboard, pro-booking.
- Deferred AI-integration files (retell/sms/mcp webhooks, professional-voice-api,
  retell-auth, meal-status, retell-agent-import) got surgical SQL-only fixes so they
  run against the renamed tables; their legacy-table queries left untouched.

**Verified live:** login_user (company_id=3 set), getUserCompanies, getCompany,
getCompanySetting + deprecated alias, switchCompany, todos/settings/users/professional/
api-token queries, header display-name lookup, and the full registration-flow insert
chain incl. the ON CONFLICT (company_id, setting_key) upsert (transaction rolled back).

**Notes:**
- The two table sets diverge from now on: the other app writes restaurants/
  user_restaurants; this app reads/writes only companies/user_companies.
- The registration Business Type select still offers restaurant/professional/affiliate
  location_type values — domain leftover for the template-conversion plan (unified-
  screen decision #4), not part of this rename.
- profile.php's stale "/booking/?restaurant=slug" help text replaced with a neutral
  description (the /booking module was deleted earlier).

---

# Plan — 2026-06-06 (First release → github.com/maludb/native-lamp-vibetemplate)

Decisions (Ed): public repo, fresh history (single initial commit), strip process
artifacts (tasks/, docs/activity.md), stale product docs, AND the orphaned legacy
modules (finishing template-conversion Phase 3 in THIS repo first, then exporting).

## Phase A — finish legacy removal in this repo
- [x] A1. Delete orphan partials: platform/, billing/, messages/, events/; settings/
      legacy files (all except profile, save-profile, users, save-user, toggle-user,
      user-form, notifications, token-setup); landing/; cron/; downloads/retell-*;
      api/retell, api/sms, api/mcp, api/email; html root call/demo/info/sms-signup/
      test-db/test-email.php; scripts/
- [x] A2. Delete unused helpers: meal-status, openai-email, openai-sms,
      prospect-restaurant, retell-api, retell-auth, send-reminders,
      send-professional-reminders, date, functions, ui
- [x] A3. Move voiceFixDate() into professional-voice-api.php; delete voice-api.php
      (rest of it is legacy reservation voice code)
- [x] A4. Slim helpers/notifications.php to sendEmail() (+ twilio/company requires);
      delete helpers/availability.php (legacy engine; getCompanySetting already
      lives in company.php) — first verify no kept caller still uses
      getRestaurantSetting or the legacy send* functions
- [x] A5. Fix dead button: settings/notifications.php:254 hx-get email-templates.php
- [x] A6. Remove deprecated alias blocks (company.php, auth.php) if no callers remain
- [x] A7. Delete stale docs: requirements.md, old_requirements.md, design-notes.md,
      restaurant_reservations.sql, *.docx, docs/retell-*, docs/mcp-pro-prompt-
      snippet.md, docs/kobie-migration-verification.md, docs/migrations/,
      docs/sql legacy files (keep install.sql, companies.sql,
      client_tokens_model_prompts.sql)
- [x] A8. Verify: php -l all kept files; grep for requires/links to deleted files;
      live smoke test (login, nav targets, forgot-password sendEmail path)
- [x] A9. Update activity.md; commit & push to vibetemplates/maludb-htmx

## Phase B — release export
- [ ] B1. Secrets scan of the tracked tree (DB/Google/Retell/OpenAI/Twilio patterns)
- [ ] B2. git archive HEAD → staging dir; remove tasks/ and docs/activity.md
- [ ] B3. Fresh git init, single "Initial release" commit, push to
      https://github.com/maludb/native-lamp-vibetemplate.git (main)

## Review
*(to be completed)*
