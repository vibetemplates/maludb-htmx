<?php
/**
 * Company (tenant) context helpers for multi-tenant SaaS
 *
 * Formerly helpers/restaurant.php — the tenant tables were genericized to
 * companies / user_companies (2026-06-06). The legacy restaurants /
 * user_restaurants tables still exist in the database but belong to another
 * application and are never touched here.
 */

require_once __DIR__ . '/db.php';

/**
 * Get the current company_id from session
 */
function getCompanyId() {
    return $_SESSION['current_company_id'] ?? null;
}

/**
 * Set the current company_id in session
 */
function setCompanyId($id) {
    $_SESSION['current_company_id'] = (int) $id;
}

/**
 * Get a company record by ID
 */
function getCompany($id) {
    $stmt = db()->prepare("SELECT * FROM companies WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get a company record by slug
 */
function getCompanyBySlug($slug) {
    $stmt = db()->prepare("SELECT * FROM companies WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

/**
 * Get all companies a user has access to.
 * Super-admins get ALL active companies with admin role.
 */
function getUserCompanies($userId) {
    $pdo = db();

    // Check if user is a super-admin (by users.role column)
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $roleStmt->execute([$userId]);
    $userRow = $roleStmt->fetch();

    if ($userRow && $userRow['role'] === 'super-admin') {
        // Super-admins see affiliate locations in setup
        $stmt = $pdo->query(
            "SELECT c.*, 'admin' as role
             FROM companies c
             WHERE c.is_active = 1 AND c.location_type = 'affiliate' AND c.status = 'in-setup'
             ORDER BY c.name"
        );
        return $stmt->fetchAll();
    }

    // Get companies the user is directly linked to
    $stmt = $pdo->prepare(
        "SELECT c.*, uc.role
         FROM companies c
         JOIN user_companies uc ON c.id = uc.company_id
         WHERE uc.user_id = ? AND uc.is_active = 1 AND c.is_active = 1
         ORDER BY c.name"
    );
    $stmt->execute([$userId]);
    $companies = $stmt->fetchAll();

    // For affiliate users, also include companies created under their affiliate
    $affiliateIds = array_column(
        array_filter($companies, fn($c) => $c['location_type'] === 'affiliate'),
        'id'
    );
    if ($affiliateIds) {
        $placeholders = implode(',', array_fill(0, count($affiliateIds), '?'));
        $affStmt = $pdo->prepare(
            "SELECT c.*, 'admin' as role
             FROM companies c
             WHERE c.affiliate_id IN ($placeholders) AND c.is_active = 1
             ORDER BY c.name"
        );
        $affStmt->execute($affiliateIds);
        $existingIds = array_column($companies, 'id');
        foreach ($affStmt->fetchAll() as $c) {
            if (!in_array($c['id'], $existingIds)) {
                $companies[] = $c;
            }
        }
        usort($companies, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    }

    return $companies;
}

/**
 * Get a user's role for a specific company
 */
function getUserRole($userId, $companyId) {
    $stmt = db()->prepare(
        "SELECT role FROM user_companies
         WHERE user_id = ? AND company_id = ? AND is_active = 1"
    );
    $stmt->execute([$userId, $companyId]);
    $row = $stmt->fetch();
    return $row ? $row['role'] : null;
}

/**
 * Set PHP's default timezone to the company's configured timezone.
 * All subsequent date() calls will use this timezone automatically.
 */
function applyCompanyTimezone($companyId): void
{
    $stmt = db()->prepare("SELECT timezone FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $row = $stmt->fetch();
    $tz = $row['timezone'] ?? 'America/Chicago';
    if ($tz && in_array($tz, timezone_identifiers_list())) {
        date_default_timezone_set($tz);
        db()->exec("SET TIME ZONE " . db()->quote($tz));
    }
}

/**
 * Strip a phone number to digits only (removes +, spaces, dashes, parens)
 */
function normalizePhone(string $phone): string
{
    return preg_replace('/\D/', '', $phone);
}

/**
 * Get a per-company setting value (settings table), with per-request cache
 */
function getCompanySetting($companyId, $key, $default = null) {
    static $cache = [];
    $cacheKey = $companyId . ':' . $key;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = db()->prepare("SELECT setting_value FROM settings WHERE company_id = ? AND setting_key = ?");
    $stmt->execute([$companyId, $key]);
    $row = $stmt->fetch();
    $value = $row ? $row['setting_value'] : $default;
    $cache[$cacheKey] = $value;
    return $value;
}

/* ============================================================================
 * DEPRECATED — legacy-module support only. Remove together with the orphaned
 * domain modules (platform/, billing/, voice/SMS webhooks) in template-
 * conversion Phase 3. New code must use the company-named functions above.
 * ========================================================================== */

function getRestaurantId() { return getCompanyId(); }
function setRestaurantId($id) { setCompanyId($id); }
function getRestaurant($id) { return getCompany($id); }
function getRestaurantBySlug($slug) { return getCompanyBySlug($slug); }
function getUserRestaurants($userId) { return getUserCompanies($userId); }
function applyRestaurantTimezone($companyId): void { applyCompanyTimezone($companyId); }

/**
 * Legacy (reservations module): 6-digit confirmation code.
 * Queries the legacy reservations table — untouched by the rename.
 */
function generateConfirmationCode(int $restaurantId, string $date): string
{
    $dayOfYear = str_pad((int)date('z', strtotime($date)) + 1, 3, '0', STR_PAD_LEFT);
    $pdo = db();
    $maxAttempts = 20;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $suffix = str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $code = $dayOfYear . $suffix;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reservations WHERE restaurant_id = ? AND confirmation_code = ?"
        );
        $stmt->execute([$restaurantId, $code]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $code;
        }
    }
    // Extremely unlikely fallback
    return $dayOfYear . str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Legacy (voice/SMS webhooks): look up a tenant by phone number.
 * Reads the companies copy so behavior matches the rest of this app.
 */
function getRestaurantByPhone(string $phone)
{
    $digits = normalizePhone($phone);
    if ($digits === '') {
        return false;
    }

    // Try exact digits match first, then match last 10 digits (strip country code)
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE is_active = 1 AND phone IS NOT NULL AND phone != ''");
    $stmt->execute();
    $companies = $stmt->fetchAll();

    $digits10 = strlen($digits) > 10 ? substr($digits, -10) : $digits;

    foreach ($companies as $c) {
        $cDigits = normalizePhone($c['phone']);
        $cDigits10 = strlen($cDigits) > 10 ? substr($cDigits, -10) : $cDigits;
        if ($cDigits === $digits || $cDigits10 === $digits10) {
            return $c;
        }
    }
    return false;
}
