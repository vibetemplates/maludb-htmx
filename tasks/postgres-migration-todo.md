# MySQL to PostgreSQL Migration Plan

## Overview

Migrate the ZozoCal database from MySQL/MariaDB (localhost:3306) to PostgreSQL (192.168.100.163:5432) using the MaluDB extensions. The PostgreSQL `zozocal` database already exists and accepts connections (verified via `html/pdo-test.php`).

## Scope Analysis

**PHP code impact is minimal** — most queries use standard SQL. Only 3 files have MySQL-specific query syntax:
- `html/partials/platform/save-account-option.php` — `ON DUPLICATE KEY UPDATE`
- `html/partials/platform/users.php` — `GROUP_CONCAT`
- `models/Activity.php` — `DATE_SUB`

**Schema conversion** is the bulk of the work — 25 SQL files need MySQL→PostgreSQL syntax changes.

**Config change** — `config/database.php` DSN, host, port, password, and MySQL-specific PDO options.

---

## Tasks

### 1. Update config/database.php
- [ ] Change DSN from `mysql:` to `pgsql:`
- [ ] Update host: `localhost` → `192.168.100.163`
- [ ] Update port: `3306` → `5432`
- [ ] Update password to match pdo-test.php credentials
- [ ] Remove `charset=utf8mb4` from DSN (add `sslmode=disable`)
- [ ] Remove `PDO::MYSQL_ATTR_INIT_COMMAND` option

### 2. Create PostgreSQL main schema
- [ ] Convert `restaurant_reservations.sql` to PostgreSQL syntax:
  - `AUTO_INCREMENT` → `SERIAL`
  - `INT UNSIGNED` → `INTEGER`
  - `TINYINT(1)` → `BOOLEAN`
  - `TINYINT UNSIGNED` / `SMALLINT UNSIGNED` → `SMALLINT`
  - `ENUM(...)` → `VARCHAR(...)` with CHECK constraints
  - `LONGTEXT` → `TEXT`
  - `JSON` → `JSONB`
  - Remove `ENGINE=InnoDB`, `DEFAULT CHARSET`, `COLLATE`
  - Remove `COMMENT '...'` (use `COMMENT ON` statements)
  - `ON UPDATE CURRENT_TIMESTAMP` → trigger function
  - Inline `INDEX` → `CREATE INDEX` statements
  - Prefix indexes like `idx_tags(100)` → standard indexes
  - `CREATE DATABASE` / `USE` → just the schema DDL

### 3. Convert supplementary SQL files
- [ ] Convert all `docs/sql/*.sql` files to PostgreSQL
- [ ] Convert `docs/migrations/001-salescoach-schema.sql`
- [ ] Convert `sql/multi-language-relay.sql`

### 4. Fix MySQL-specific PHP queries
- [ ] `save-account-option.php`: `ON DUPLICATE KEY UPDATE` → `ON CONFLICT (...) DO UPDATE SET`
- [ ] `platform/users.php`: `GROUP_CONCAT(CONCAT(...) SEPARATOR ', ')` → `STRING_AGG(... , ', ')`
- [ ] `models/Activity.php`: `DATE_SUB(NOW(), INTERVAL :days DAY)` → `NOW() - INTERVAL '1 day' * :days`

### 5. Run schema on PostgreSQL server
- [ ] Execute converted schema against `zozocal` database
- [ ] Verify all tables created successfully

### 6. Migrate existing data
- [ ] Export data from MySQL tables
- [ ] Import into PostgreSQL (handling type differences)

### 7. Test and verify
- [ ] Verify PDO connection works with new config
- [ ] Test basic CRUD operations
- [ ] Verify app loads correctly

---

## Review

*(To be filled after migration is complete)*
