-- ============================================================================
-- Tenant rename: restaurants → companies (COPY, not rename) — 2026-06-06
-- ============================================================================
--
-- The legacy `restaurants` and `user_restaurants` tables are used by ANOTHER
-- application and must not be renamed, altered, or dropped. This script:
--
--   1. Creates `companies` / `user_companies` as faithful copies (structure +
--      data, same ids) of restaurants / user_restaurants.
--   2. Repoints the 9 template tables (settings, professional_profiles,
--      professional_services, professional_availability_rules,
--      professional_time_off, professional_clients, professional_appointments,
--      todos, api_tokens) at companies: restaurant_id → company_id, FKs moved
--      from restaurants(id) to companies(id). (api_tokens has no FK — column
--      rename only.) Confirmed: the other application does not touch these 9.
--
-- After this runs, the two table sets diverge by design: the other app keeps
-- writing restaurants/user_restaurants; this app uses companies/user_companies.
-- Legacy tables (reservations, guests, restaurant_prompts, ...) keep their FKs
-- to restaurants and are not touched.
--
-- One-shot script (not idempotent) — runs in a single transaction.
--   psql -h <host> -U <user> -d <db> -f docs/sql/companies.sql
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1a. companies — copy of restaurants (structure + data + id sequence)
-- ----------------------------------------------------------------------------

CREATE TABLE companies (
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
CREATE INDEX idx_companies_active ON companies (is_active);

INSERT INTO companies (id, name, slug, phone, email, address_line1, address_line2,
                       city, state, postal_code, website, timezone, location_type,
                       is_active, status, affiliate_id, billing_user, prepay_balance,
                       created_at, updated_at)
SELECT id, name, slug, phone, email, address_line1, address_line2,
       city, state, postal_code, website, timezone, location_type,
       is_active, status, affiliate_id, billing_user, prepay_balance,
       created_at, updated_at
FROM restaurants
ORDER BY id;

SELECT setval('companies_id_seq', COALESCE((SELECT MAX(id) FROM companies), 0) + 1, false);

-- ----------------------------------------------------------------------------
-- 1b. user_companies — copy of user_restaurants (restaurant_id → company_id)
-- ----------------------------------------------------------------------------

CREATE TABLE user_companies (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER     NOT NULL REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
    company_id INTEGER     NOT NULL REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE,
    role       VARCHAR(10) NOT NULL DEFAULT 'admin',
    is_active  SMALLINT    NOT NULL DEFAULT 1,
    created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_user_company UNIQUE (user_id, company_id)
);
CREATE INDEX idx_user_companies_company ON user_companies (company_id);
CREATE INDEX idx_user_companies_role ON user_companies (role);

INSERT INTO user_companies (id, user_id, company_id, role, is_active, created_at, updated_at)
SELECT id, user_id, restaurant_id, role, is_active, created_at, updated_at
FROM user_restaurants
ORDER BY id;

SELECT setval('user_companies_id_seq', COALESCE((SELECT MAX(id) FROM user_companies), 0) + 1, false);

-- ----------------------------------------------------------------------------
-- 2. Repoint the 9 template tables: restaurant_id → company_id, FK → companies
--    (index/constraint names with "restaurant" renamed to match)
-- ----------------------------------------------------------------------------

-- settings
ALTER TABLE settings RENAME COLUMN restaurant_id TO company_id;
ALTER TABLE settings RENAME CONSTRAINT uk_restaurant_setting TO uk_company_setting;
ALTER TABLE settings DROP CONSTRAINT fk_settings_restaurant;
ALTER TABLE settings ADD CONSTRAINT fk_settings_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- professional_profiles
ALTER TABLE professional_profiles RENAME COLUMN restaurant_id TO company_id;
ALTER TABLE professional_profiles RENAME CONSTRAINT uk_prof_profiles_restaurant TO uk_prof_profiles_company;
ALTER TABLE professional_profiles DROP CONSTRAINT fk_prof_profiles_restaurant;
ALTER TABLE professional_profiles ADD CONSTRAINT fk_prof_profiles_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- professional_services
ALTER TABLE professional_services RENAME COLUMN restaurant_id TO company_id;
ALTER INDEX idx_prof_services_restaurant_active RENAME TO idx_prof_services_company_active;
ALTER INDEX idx_prof_services_restaurant_public RENAME TO idx_prof_services_company_public;
ALTER TABLE professional_services DROP CONSTRAINT fk_prof_services_restaurant;
ALTER TABLE professional_services ADD CONSTRAINT fk_prof_services_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- professional_availability_rules
ALTER TABLE professional_availability_rules RENAME COLUMN restaurant_id TO company_id;
ALTER INDEX idx_prof_availability_restaurant_day RENAME TO idx_prof_availability_company_day;
ALTER TABLE professional_availability_rules DROP CONSTRAINT fk_prof_availability_restaurant;
ALTER TABLE professional_availability_rules ADD CONSTRAINT fk_prof_availability_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- professional_time_off
ALTER TABLE professional_time_off RENAME COLUMN restaurant_id TO company_id;
ALTER INDEX idx_prof_time_off_restaurant_range RENAME TO idx_prof_time_off_company_range;
ALTER TABLE professional_time_off DROP CONSTRAINT fk_prof_time_off_restaurant;
ALTER TABLE professional_time_off ADD CONSTRAINT fk_prof_time_off_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- professional_clients
ALTER TABLE professional_clients RENAME COLUMN restaurant_id TO company_id;
ALTER INDEX idx_prof_clients_restaurant_name  RENAME TO idx_prof_clients_company_name;
ALTER INDEX idx_prof_clients_restaurant_email RENAME TO idx_prof_clients_company_email;
ALTER INDEX idx_prof_clients_restaurant_phone RENAME TO idx_prof_clients_company_phone;
ALTER TABLE professional_clients DROP CONSTRAINT fk_prof_clients_restaurant;
ALTER TABLE professional_clients ADD CONSTRAINT fk_prof_clients_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- professional_appointments
ALTER TABLE professional_appointments RENAME COLUMN restaurant_id TO company_id;
ALTER INDEX idx_prof_appointments_restaurant_start       RENAME TO idx_prof_appointments_company_start;
ALTER INDEX idx_prof_appointments_restaurant_status_date RENAME TO idx_prof_appointments_company_status_date;
ALTER TABLE professional_appointments DROP CONSTRAINT fk_prof_appointments_restaurant;
ALTER TABLE professional_appointments ADD CONSTRAINT fk_prof_appointments_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- todos
ALTER TABLE todos RENAME COLUMN restaurant_id TO company_id;
ALTER INDEX idx_todos_restaurant_user RENAME TO idx_todos_company_user;
ALTER TABLE todos DROP CONSTRAINT fk_todos_restaurant;
ALTER TABLE todos ADD CONSTRAINT fk_todos_company
    FOREIGN KEY (company_id) REFERENCES companies(id) ON UPDATE CASCADE ON DELETE CASCADE;

-- api_tokens (no FK on this column, live or in install.sql — column rename only)
ALTER TABLE api_tokens RENAME COLUMN restaurant_id TO company_id;

COMMIT;
