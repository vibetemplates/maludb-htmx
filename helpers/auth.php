<?php
/**
 * Authentication Helper
 *
 * Provides authentication and authorization functions
 * for the multi-tenant application
 *
 * Platform roles (users.role column):
 *   super-admin — Platform vendor administrator (access to all companies and users)
 *   affiliate   — Affiliate partner (referral dashboard, no company access)
 *   user        — Normal user (access determined by user_companies table)
 *
 * Company roles (user_companies.role column):
 *   admin       — Company administrator/purchaser (all setup, invite users)
 *   manager     — Company manager (all setup access)
 *   user        — Company staff
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/company.php';

/**
 * Check if user is authenticated
 * Redirects to login page if not authenticated
 */
function check_auth() {
    requireAuth();
    return true;
}

/**
 * Get current user ID from session
 */
function get_user_id() {
    return currentUserId();
}

/**
 * Get current user data from session
 */
function get_user() {
    session_start_once();
    return $_SESSION['user'] ?? null;
}

/**
 * Login user: authenticate, load company memberships, set session
 */
function login_user($user, $remember = false) {
    session_start_once();
    regenerate_session();

    // Load user's company memberships
    $companies = getUserCompanies($user['id']);
    $firstCompany = $companies[0] ?? null;

    $platformRole = $user['role'] ?? 'user';

    $sessionUser = [
        'id' => $user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role' => $platformRole,
        // Keep for backward compat during transition
        'is_platform_admin' => ($platformRole === 'super-admin') ? 1 : 0,
    ];

    $_SESSION['user'] = $sessionUser;
    $_SESSION['user_id'] = $sessionUser['id'];
    $_SESSION['logged_in_at'] = time();

    // Check if user is an affiliate (by platform role or affiliates table)
    $_SESSION['is_affiliate'] = ($platformRole === 'affiliate');
    $_SESSION['affiliate_id'] = null;
    if ($platformRole === 'affiliate') {
        $affStmt = db()->prepare("SELECT id FROM affiliates WHERE user_id = ? AND status = 'active'");
        $affStmt->execute([$user['id']]);
        $affData = $affStmt->fetch();
        $_SESSION['affiliate_id'] = $affData ? (int)$affData['id'] : null;
    }

    // Set current company context
    // Super-admins get all companies; pick the first if they have one assigned
    if ($platformRole === 'super-admin' && !$firstCompany) {
        // Super-admin with no direct company assignment — load first company in system
        $anyCompany = db()->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name LIMIT 1")->fetch();
        if ($anyCompany) {
            $_SESSION['current_company_id'] = (int)$anyCompany['id'];
            $_SESSION['current_role'] = 'admin';
            $_SESSION['current_company_name'] = $anyCompany['name'];
        }
    } elseif ($firstCompany) {
        $_SESSION['current_company_id'] = (int)$firstCompany['id'];
        $_SESSION['current_role'] = $firstCompany['role'];
        $_SESSION['current_company_name'] = $firstCompany['name'];
    }

    // Update last_login_at
    $stmt = db()->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Handle remember me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['remember_token'] = $token;
        setcookie(
            'remember_token',
            $token,
            time() + (30 * 24 * 60 * 60),
            '/',
            '',
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            true
        );
        return $token;
    }

    return null;
}

/**
 * Logout user and destroy session
 */
function logout_user() {
    session_start_once();
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    destroy_session();
}

/**
 * Switch the current company context for the logged-in user
 */
function switchCompany($companyId) {
    session_start_once();
    $userId = currentUserId();
    if (!$userId) return false;

    $company = getCompany($companyId);
    if (!$company) return false;

    // Super-admins can switch to any company with admin-level access
    if (isSuperAdmin()) {
        $_SESSION['current_company_id'] = (int)$companyId;
        $_SESSION['current_role'] = 'admin';
        $_SESSION['current_company_name'] = $company['name'];
        return true;
    }

    $role = getUserRole($userId, $companyId);
    if (!$role) return false;

    $_SESSION['current_company_id'] = (int)$companyId;
    $_SESSION['current_role'] = $role;
    $_SESSION['current_company_name'] = $company['name'];
    return true;
}

function session_start_once(): void {
    if (session_status() === PHP_SESSION_NONE) {
        init_session();
    }
}

function requireAuth(): void {
    session_start_once();
    if (empty($_SESSION['user'])) {
        if (isset($_SERVER['HTTP_HX_REQUEST'])) {
            http_response_code(401);
            header('HX-Redirect: /login.php');
        } else {
            header('Location: /login.php');
        }
        exit;
    }
    // Set timezone for all date() calls to use this company's timezone
    $cid = $_SESSION['current_company_id'] ?? null;
    if ($cid) {
        applyCompanyTimezone($cid);
    }
}

/**
 * Access guards — halt with 403 if role requirement not met
 */
function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied. Admin role required.</div>';
        exit;
    }
}

function requireManager(): void {
    requireAuth();
    if (!isManager()) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied. Manager role required.</div>';
        exit;
    }
}

function requireSuperAdmin(): void {
    requireAuth();
    if (!isSuperAdmin()) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Access denied. Super-admin required.</div>';
        exit;
    }
}

/**
 * Role checks for current restaurant context
 *
 * Hierarchy: super-admin > admin > manager > user
 */
function isAdmin(): bool {
    return ($_SESSION['current_role'] ?? '') === 'admin';
}

function isManager(): bool {
    $role = $_SESSION['current_role'] ?? '';
    return $role === 'admin' || $role === 'manager';
}

function isStaff(): bool {
    $role = $_SESSION['current_role'] ?? '';
    return in_array($role, ['admin', 'manager', 'user']);
}

function isSuperAdmin(): bool {
    return ($_SESSION['user']['role'] ?? '') === 'super-admin';
}

function isAffiliate(): bool {
    session_start_once();
    return ($_SESSION['is_affiliate'] ?? false) === true;
}

// Backward-compatible aliases
function isOwner(): bool { return isAdmin(); }
function isHost(): bool { return isStaff(); }
function isPlatformAdmin(): bool { return isSuperAdmin(); }
function requireOwner(): void { requireAdmin(); }
function requirePlatformAdmin(): void { requireSuperAdmin(); }

function currentUserId(): int {
    session_start_once();
    return (int)($_SESSION['user']['id'] ?? 0);
}

function currentCompanyId(): ?int {
    session_start_once();
    return isset($_SESSION['current_company_id']) ? (int)$_SESSION['current_company_id'] : null;
}

/**
 * Get the affiliate_id from the current company.
 * Returns 0 if the current company is not an affiliate location.
 */
function currentAffiliateId(): int {
    $companyId = currentCompanyId();
    if (!$companyId) return 0;

    $stmt = db()->prepare("SELECT id, affiliate_id, location_type FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $row = $stmt->fetch();
    if (!$row) return 0;

    // If this company IS the affiliate, use its own id
    if ($row['location_type'] === 'affiliate') {
        return (int)$row['id'];
    }

    // Otherwise use the linked affiliate_id
    return !empty($row['affiliate_id']) ? (int)$row['affiliate_id'] : 0;
}

/**
 * Get permitted navigation item IDs for the current user context.
 * Queries the nav_permissions table using NULL-means-any matching logic.
 */
function getPermittedNavItems(?string $userRole, ?string $restaurantRole, ?string $locationType): array {
    $sql = "SELECT DISTINCT nav_item_id FROM nav_permissions
            WHERE is_active = 1
              AND (user_role IS NULL OR user_role = ?)
              AND (restaurant_role IS NULL OR restaurant_role = ?)
              AND (location_type IS NULL OR location_type = ?)";
    $stmt = db()->prepare($sql);
    $stmt->execute([$userRole, $restaurantRole, $locationType]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}
