<?php
/**
 * Complete Google Registration — saves company name and creates account
 */

require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/db.php';
require_once '../../../helpers/auth.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    unset($_SESSION['csrf_token']);
    $newToken = generate_csrf_token();
    echo '<div class="alert alert-danger">Invalid security token. Please try again.</div>';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($newToken) . '" hx-swap-oob="outerHTML:[name=csrf_token]">';
    exit;
}

if (empty($_SESSION['google_pending'])) {
    echo '<div class="alert alert-danger">Google session expired. Please try again.</div>';
    exit;
}

$companyName = sanitize_input($_POST['company_name'] ?? '');
if ($companyName === '') {
    echo '<div class="alert alert-danger">Company name is required.</div>';
    exit;
}

$locationType = $_POST['location_type'] ?? 'restaurant';
$validLocationTypes = ['restaurant', 'professional', 'affiliate'];
if (!in_array($locationType, $validLocationTypes)) {
    $locationType = 'restaurant';
}

$gp        = $_SESSION['google_pending'];
$googleId  = $gp['google_id'];
$email     = $gp['email'];
$firstName = $gp['first_name'];
$lastName  = $gp['last_name'];

$pdo = db();

// Generate slug
$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $companyName), '-'));
$slugBase = $slug;
$slugSuffix = 1;
while (true) {
    $check = $pdo->prepare("SELECT id FROM companies WHERE slug = ?");
    $check->execute([$slug]);
    if (!$check->fetch()) break;
    $slug = $slugBase . '-' . $slugSuffix;
    $slugSuffix++;
}

$pdo->beginTransaction();
try {
    // 1. Create user
    $stmt = $pdo->prepare(
        "INSERT INTO users (first_name, last_name, email, password_hash, google_id, auth_provider, company_name, user_type, is_affiliate, is_platform_admin, is_active, created_at)
         VALUES (?, ?, ?, '!GOOGLE', ?, 'google', ?, 'system_owner', 1, 0, 1, NOW())"
    );
    $stmt->execute([$firstName, $lastName, $email, $googleId, $companyName]);
    $userId = (int)$pdo->lastInsertId();

    // 2. Create company with location_type
    $stmt = $pdo->prepare(
        "INSERT INTO companies (name, slug, email, timezone, location_type, is_active, created_at)
         VALUES (?, ?, ?, 'America/Chicago', ?, 1, NOW())"
    );
    $stmt->execute([$companyName, $slug, $email, $locationType]);
    $companyId = (int)$pdo->lastInsertId();

    // 3. Assign user as owner/admin
    $pdo->prepare(
        "INSERT INTO user_companies (user_id, company_id, role, is_active) VALUES (?, ?, 'admin', 1)"
    )->execute([$userId, $companyId]);

    // 4. Create default settings (template scope only — keys the app actually reads)
    $pdo->prepare(
        "INSERT INTO settings (company_id, setting_key, setting_value) VALUES
            (?, 'confirmation_email_enabled', '1'),
            (?, 'reminder_email_enabled', '1'),
            (?, 'reminder_hours_before', '24'),
            (?, 'cancellation_email_enabled', '1'),
            (?, 'cancellation_policy', 'Please cancel at least 24 hours before your appointment time.')"
    )->execute(array_fill(0, 5, $companyId));

    $pdo->commit();

    // Clear pending session
    unset($_SESSION['google_pending']);

    // Log in
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$userId]);
    $userData = $user->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        login_user($userData, false);
        header('HX-Redirect: /app.php');
        echo '<div class="alert alert-success">Account created! Redirecting...</div>';
    }

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Google registration error: ' . $e->getMessage());
    echo '<div class="alert alert-danger">Failed to create account. Please try again later.</div>';
}
