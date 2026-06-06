-- ============================================================================
-- MaluDB HTMX Template — database installation script
-- ============================================================================
--
-- Prerequisites (done by you, before running this script):
--   1. Create a PostgreSQL database and a user that owns it:
--        CREATE USER myapp WITH PASSWORD '...';
--        CREATE DATABASE myapp OWNER myapp;
--   2. Install/enable the MaluDB memory functions in that database
--      (the maludb_core schema: maludb_* views, type tables, and facades such
--      as maludb_upload_document / maludb_memory_ingest_extraction). This
--      script does NOT create any maludb_* objects — they are system-owned.
--   3. Point config/database.php at the new database.
--
-- Then run this script as the application user:
--        psql -h <host> -U myapp -d myapp -f docs/sql/install.sql
--
-- What it creates: the application tables in the default schema (public) —
-- everything the template needs beyond the maludb install. Idempotent:
-- IF NOT EXISTS throughout, safe to re-run.
--
-- Seed data: a default admin login and its company are created at the bottom
-- of this script (idempotent — skipped if they already exist):
--       email:    admin@example.com
--       password: admin123        <-- CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN
-- Additional tenants are created by the registration flow; each registered
-- user becomes the admin of their own company.
--
-- Table groups:
--   Auth & tenancy ..... users, companies, user_companies, settings
--   Profile ............ professional_profiles
--   Scheduling ......... professional_services, professional_availability_rules,
--                        professional_time_off, professional_clients,
--                        professional_appointments
--   Tasks .............. todos
--   API auth ........... api_tokens (Bearer tokens for html/api/v1)
--   MaluDB client ...... client_token, client_model_prompt (LLM provider keys
--                        and per-model prompts — kept OUT of maludb_*)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Auth & tenancy
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id                SERIAL PRIMARY KEY,
    first_name        VARCHAR(100) NOT NULL,
    last_name         VARCHAR(100) NOT NULL,
    company_name      VARCHAR(120),
    email             VARCHAR(255) NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    google_id         VARCHAR(255),
    auth_provider     VARCHAR(20)  NOT NULL DEFAULT 'local',
    user_type         VARCHAR(20)  NOT NULL DEFAULT 'user',
    user_mode         VARCHAR(20),
    product_type      VARCHAR(25)  NOT NULL DEFAULT 'professional',
    is_platform_admin SMALLINT     NOT NULL DEFAULT 0,
    role              VARCHAR(20)  DEFAULT 'user',
    phone             VARCHAR(20),
    is_active         SMALLINT     NOT NULL DEFAULT 1,
    is_affiliate      SMALLINT     NOT NULL DEFAULT 0,
    referred_by       INTEGER      NOT NULL DEFAULT 0,
    referral_code     VARCHAR(5),
    last_login_at     TIMESTAMP,
    created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_email UNIQUE (email),
    CONSTRAINT idx_google_id UNIQUE (google_id)
);
CREATE INDEX IF NOT EXISTS idx_users_active ON users (is_active);

-- One-hour, single-use tokens for the forgot-password flow.
CREATE TABLE IF NOT EXISTS password_resets (
    id         SERIAL PRIMARY KEY,
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP    NOT NULL,
    used_at    TIMESTAMP,
    CONSTRAINT uk_password_resets_token UNIQUE (token)
);
CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets (email);

-- Tenant root: the generic business/account container. (Formerly named
-- "restaurants" — renamed to companies in the template, 2026-06-06.)
CREATE TABLE IF NOT EXISTS companies (
    id            SERIAL PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    slug          VARCHAR(100) NOT NULL,
    phone         VARCHAR(20),
    email         VARCHAR(255),
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city          VARCHAR(100),
    state         VARCHAR(50),
    postal_code   VARCHAR(20),
    website       VARCHAR(255),
    timezone      VARCHAR(50) NOT NULL DEFAULT 'America/Chicago',
    location_type VARCHAR(20) NOT NULL DEFAULT 'professional',
    is_active     SMALLINT    NOT NULL DEFAULT 1,
    status        VARCHAR(10) NOT NULL DEFAULT 'in-setup',
    affiliate_id  INTEGER     NOT NULL DEFAULT 0,
    billing_user  INTEGER     NOT NULL DEFAULT 0,
    prepay_balance NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    created_at    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_companies_slug UNIQUE (slug)
);
CREATE INDEX IF NOT EXISTS idx_companies_active ON companies (is_active);

CREATE TABLE IF NOT EXISTS user_companies (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER     NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    company_id INTEGER     NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    role       VARCHAR(10) NOT NULL DEFAULT 'admin',
    is_active  SMALLINT    NOT NULL DEFAULT 1,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_user_company UNIQUE (user_id, company_id)
);
CREATE INDEX IF NOT EXISTS idx_user_companies_company ON user_companies (company_id);
CREATE INDEX IF NOT EXISTS idx_user_companies_role ON user_companies (role);

-- Per-tenant key/value config. The UNIQUE on (company_id, setting_key) is
-- required by the ON CONFLICT upserts in the settings pages.
CREATE TABLE IF NOT EXISTS settings (
    id            SERIAL PRIMARY KEY,
    company_id    INTEGER      NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT,
    category      VARCHAR(50)  NOT NULL DEFAULT 'general',
    description   VARCHAR(255),
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_company_setting UNIQUE (company_id, setting_key)
);
CREATE INDEX IF NOT EXISTS idx_settings_category ON settings (company_id, category);

-- ----------------------------------------------------------------------------
-- Profile (business profile shown in the header / Settings page)
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS professional_profiles (
    id                            SERIAL PRIMARY KEY,
    company_id                    INTEGER      NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    owner_user_id                 INTEGER      NOT NULL REFERENCES users(id) ON UPDATE CASCADE,
    business_name                 VARCHAR(255) NOT NULL,
    display_name                  VARCHAR(255) NOT NULL,
    business_phone                VARCHAR(20),
    business_email                VARCHAR(255),
    timezone                      VARCHAR(64)  NOT NULL DEFAULT 'America/New_York',
    booking_slug                  VARCHAR(150) NOT NULL,
    slot_interval_minutes         SMALLINT     NOT NULL DEFAULT 30,
    default_buffer_before_minutes SMALLINT     NOT NULL DEFAULT 0,
    default_buffer_after_minutes  SMALLINT     NOT NULL DEFAULT 0,
    minimum_booking_notice_hours  SMALLINT     NOT NULL DEFAULT 2,
    maximum_booking_horizon_days  SMALLINT     NOT NULL DEFAULT 90,
    default_location_type         VARCHAR(30)  NOT NULL DEFAULT 'in_person',
    default_location_label        VARCHAR(255),
    booking_instructions          TEXT,
    cancellation_policy           TEXT,
    cancellation_notice_hours     SMALLINT     NOT NULL DEFAULT 24,
    is_public_booking_enabled     SMALLINT     NOT NULL DEFAULT 1,
    created_at                    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_prof_profiles_company UNIQUE (company_id),
    CONSTRAINT uk_prof_profiles_owner_user UNIQUE (owner_user_id),
    CONSTRAINT uk_prof_profiles_booking_slug UNIQUE (booking_slug)
);

-- ----------------------------------------------------------------------------
-- Scheduling
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS professional_services (
    id                    SERIAL PRIMARY KEY,
    company_id            INTEGER      NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    name                  VARCHAR(255) NOT NULL,
    description           TEXT,
    duration_minutes      SMALLINT     NOT NULL,
    buffer_before_minutes SMALLINT     NOT NULL DEFAULT 0,
    buffer_after_minutes  SMALLINT     NOT NULL DEFAULT 0,
    price                 NUMERIC(10,2),
    currency_code         VARCHAR(10)  NOT NULL DEFAULT 'USD',
    location_type         VARCHAR(30),
    location_label        VARCHAR(255),
    color                 VARCHAR(20),
    sort_order            INTEGER      NOT NULL DEFAULT 0,
    is_active             SMALLINT     NOT NULL DEFAULT 1,
    is_public_bookable    SMALLINT     NOT NULL DEFAULT 1,
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_prof_services_company_active ON professional_services (company_id, is_active, sort_order);
CREATE INDEX IF NOT EXISTS idx_prof_services_company_public ON professional_services (company_id, is_public_bookable, is_active);

CREATE TABLE IF NOT EXISTS professional_availability_rules (
    id             SERIAL PRIMARY KEY,
    company_id     INTEGER  NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    weekday        SMALLINT NOT NULL,                  -- 0=Sunday .. 6=Saturday
    start_time     TIME     NOT NULL,
    end_time       TIME     NOT NULL,
    location_type  VARCHAR(30),
    location_label VARCHAR(255),
    is_active      SMALLINT NOT NULL DEFAULT 1,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_prof_availability_company_day ON professional_availability_rules (company_id, weekday, is_active);

CREATE TABLE IF NOT EXISTS professional_time_off (
    id            SERIAL PRIMARY KEY,
    company_id    INTEGER   NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    starts_at     TIMESTAMP NOT NULL,
    ends_at       TIMESTAMP NOT NULL,
    reason        VARCHAR(255),
    notes         TEXT,
    is_all_day    SMALLINT  NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_prof_time_off_company_range ON professional_time_off (company_id, starts_at, ends_at);

CREATE TABLE IF NOT EXISTS professional_clients (
    id                       SERIAL PRIMARY KEY,
    company_id               INTEGER      NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    first_name               VARCHAR(120) NOT NULL,
    last_name                VARCHAR(120) NOT NULL,
    email                    VARCHAR(255),
    phone                    VARCHAR(20),
    birth_date               DATE,
    notes                    TEXT,
    internal_notes           TEXT,
    service_address_line1    VARCHAR(255),
    service_city             VARCHAR(100),
    service_state            VARCHAR(50),
    service_postal_code      VARCHAR(20),
    last_service_date        DATE,
    preferred_contact_method VARCHAR(20),
    marketing_opt_in         SMALLINT     NOT NULL DEFAULT 0,
    last_appointment_at      TIMESTAMP,
    created_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_prof_clients_company_name ON professional_clients (company_id, last_name, first_name);
CREATE INDEX IF NOT EXISTS idx_prof_clients_company_email ON professional_clients (company_id, email);
CREATE INDEX IF NOT EXISTS idx_prof_clients_company_phone ON professional_clients (company_id, phone);

CREATE TABLE IF NOT EXISTS professional_appointments (
    id                     SERIAL PRIMARY KEY,
    company_id             INTEGER      NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    professional_user_id   INTEGER      NOT NULL REFERENCES users(id) ON UPDATE CASCADE,
    client_id              INTEGER      NOT NULL REFERENCES professional_clients(id) ON UPDATE CASCADE,
    service_id             INTEGER      REFERENCES professional_services(id) ON UPDATE CASCADE ON DELETE SET NULL,
    status                 VARCHAR(30)  NOT NULL DEFAULT 'confirmed',   -- pending|confirmed|completed|cancelled|no_show
    source                 VARCHAR(30)  NOT NULL DEFAULT 'staff',
    appointment_date       DATE         NOT NULL,
    start_at               TIMESTAMP    NOT NULL,
    end_at                 TIMESTAMP    NOT NULL,
    service_name           VARCHAR(255) NOT NULL,
    duration_minutes       SMALLINT     NOT NULL,
    buffer_before_minutes  SMALLINT     NOT NULL DEFAULT 0,
    buffer_after_minutes   SMALLINT     NOT NULL DEFAULT 0,
    price                  NUMERIC(10,2),
    currency_code          VARCHAR(10)  NOT NULL DEFAULT 'USD',
    location_type          VARCHAR(30),
    location_label         VARCHAR(255),
    confirmation_code      VARCHAR(32),
    client_notes           TEXT,
    internal_notes         TEXT,
    cancelled_at           TIMESTAMP,
    completed_at           TIMESTAMP,
    service_contact_name   VARCHAR(140),
    service_address_1      VARCHAR(140),
    service_city           VARCHAR(80),
    service_state          VARCHAR(5),
    service_postal_code    VARCHAR(5),
    service_phone          VARCHAR(20),
    service_contact_method VARCHAR(3),
    created_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_prof_appointments_confirmation_code UNIQUE (confirmation_code)
);
CREATE INDEX IF NOT EXISTS idx_prof_appointments_company_start ON professional_appointments (company_id, start_at);
CREATE INDEX IF NOT EXISTS idx_prof_appointments_company_status_date ON professional_appointments (company_id, status, appointment_date, start_at);
CREATE INDEX IF NOT EXISTS idx_prof_appointments_provider_range ON professional_appointments (professional_user_id, start_at, end_at);
CREATE INDEX IF NOT EXISTS idx_prof_appointments_client ON professional_appointments (client_id, start_at);
CREATE INDEX IF NOT EXISTS idx_prof_appointments_service ON professional_appointments (service_id);

-- ----------------------------------------------------------------------------
-- Tasks
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS todos (
    id            SERIAL PRIMARY KEY,
    company_id    INTEGER      NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    user_id       INTEGER      NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    title         VARCHAR(255) NOT NULL,
    description   TEXT,
    due_date      DATE,
    priority      VARCHAR(20)  NOT NULL DEFAULT 'medium',   -- high|medium|low
    status        VARCHAR(20)  NOT NULL DEFAULT 'pending',  -- pending|in_progress|completed
    sort_order    INTEGER      NOT NULL DEFAULT 0,
    completed_at  TIMESTAMP,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_todos_company_user ON todos (company_id, user_id);
CREATE INDEX IF NOT EXISTS idx_todos_status ON todos (status);
CREATE INDEX IF NOT EXISTS idx_todos_due_date ON todos (due_date);

-- ----------------------------------------------------------------------------
-- API auth (Bearer tokens for html/api/v1 — sha256 hash only, never plaintext)
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS api_tokens (
    id            SERIAL PRIMARY KEY,
    user_id       INTEGER     NOT NULL,
    company_id    INTEGER     NOT NULL,
    token_hash    VARCHAR(64) NOT NULL,
    device_name   VARCHAR(100),
    expires_at    TIMESTAMP   NOT NULL,
    created_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_token_hash UNIQUE (token_hash)
);
CREATE INDEX IF NOT EXISTS idx_api_tokens_user ON api_tokens (user_id);
CREATE INDEX IF NOT EXISTS idx_api_tokens_expires ON api_tokens (expires_at);

-- ----------------------------------------------------------------------------
-- MaluDB client tables (LLM provider connections + per-model prompts).
-- Client tokens live HERE, never in system-owned maludb_* objects.
-- See docs/sql/client_tokens_model_prompts.sql for background.
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS client_token (
    token_id    SERIAL PRIMARY KEY,
    token_name  TEXT NOT NULL UNIQUE,         -- e.g. 'openai-prod'
    api_format  TEXT NOT NULL CHECK (api_format IN ('openai','anthropic')),
    base_url    TEXT NOT NULL,                -- e.g. https://api.openai.com/v1
    api_key     TEXT NOT NULL,                -- entered once; never echoed back to the UI
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS client_model_prompt (
    model             TEXT PRIMARY KEY,        -- e.g. 'chatgpt-4o'
    token_id          INT NOT NULL REFERENCES client_token(token_id),
    model_identifier  TEXT,                    -- provider's model id; falls back to model
    system_prompt     TEXT NOT NULL,
    max_tokens        INT NOT NULL DEFAULT 4096,
    generation_params JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ----------------------------------------------------------------------------
-- Seed data: default admin user + default company
--
-- Login: admin@example.com / admin123  — CHANGE THE PASSWORD AFTER FIRST LOGIN.
-- The password_hash below is bcrypt("admin123"); generate a replacement with:
--       php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
-- Idempotent: every insert no-ops if the row already exists.
-- ----------------------------------------------------------------------------

INSERT INTO users (first_name, last_name, email, password_hash, company_name,
                   user_type, is_active)
VALUES ('Admin', 'User', 'admin@example.com',
        '$2y$10$QEeE65W2oM700RMYFIJD2eoIpOho0rbOQGm8im6DI3d9v8cRD4ZVS',
        'Default Company', 'system_owner', 1)
ON CONFLICT (email) DO NOTHING;

INSERT INTO companies (name, slug, email, timezone, location_type, is_active)
VALUES ('Default Company', 'default-company', 'admin@example.com',
        'America/Chicago', 'professional', 1)
ON CONFLICT (slug) DO NOTHING;

-- Make the admin user the admin of the default company
INSERT INTO user_companies (user_id, company_id, role, is_active)
SELECT u.id, c.id, 'admin', 1
FROM users u, companies c
WHERE u.email = 'admin@example.com' AND c.slug = 'default-company'
ON CONFLICT (user_id, company_id) DO NOTHING;

-- Default per-company settings (same keys the registration flow seeds)
INSERT INTO settings (company_id, setting_key, setting_value)
SELECT c.id, s.setting_key, s.setting_value
FROM companies c,
     (VALUES
        ('confirmation_email_enabled', '1'),
        ('reminder_email_enabled', '1'),
        ('reminder_hours_before', '24'),
        ('cancellation_email_enabled', '1'),
        ('cancellation_policy', 'Please cancel at least 24 hours before your appointment time.')
     ) AS s(setting_key, setting_value)
WHERE c.slug = 'default-company'
ON CONFLICT (company_id, setting_key) DO NOTHING;

-- ============================================================================
-- Done. Log in as admin@example.com / admin123 (change the password), or
-- register additional users through /register.php — that flow creates each
-- user's company, admin membership, and default settings.
-- ============================================================================
