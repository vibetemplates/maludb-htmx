<?php
require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/db.php';
require_once '../../../helpers/auth.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="register-error-method">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    // Regenerate token and return updated form token so retry works without refresh
    unset($_SESSION['csrf_token']);
    $newToken = generate_csrf_token();
    echo '<div class="alert alert-danger" id="register-error-csrf">Invalid security token. Please try again.</div>';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($newToken) . '" hx-swap-oob="outerHTML:[name=csrf_token]">';
    exit;
}

$required = ['first_name', 'last_name', 'email', 'password', 'confirm_password', 'restaurant_name'];
$missing = validate_required($required, $_POST);

if (!empty($missing)) {
    echo '<div class="alert alert-danger" id="register-error-required">Please fill in all required fields.</div>';
    exit;
}

$firstName = sanitize_input($_POST['first_name']);
$lastName = sanitize_input($_POST['last_name']);
$email = sanitize_input($_POST['email']);
$restaurantName = sanitize_input($_POST['restaurant_name']);
$password = $_POST['password'];
$confirmPassword = $_POST['confirm_password'];
$locationType = $_POST['location_type'] ?? 'restaurant';

$validLocationTypes = ['restaurant', 'professional', 'affiliate'];
if (!in_array($locationType, $validLocationTypes)) {
    $locationType = 'restaurant';
}

if (!validate_email($email)) {
    echo '<div class="alert alert-danger" id="register-error-email">Please enter a valid email address.</div>';
    exit;
}

if ($password !== $confirmPassword) {
    echo '<div class="alert alert-danger" id="register-error-password-match">Passwords do not match.</div>';
    exit;
}

$passwordValidation = validate_password($password);
if (!$passwordValidation['valid']) {
    echo '<div class="alert alert-danger" id="register-error-password-strength">' . htmlspecialchars($passwordValidation['message']) . '</div>';
    exit;
}

$pdo = db();

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo '<div class="alert alert-danger" id="register-error-email-exists">An account with this email already exists. <a href="/login.php" class="alert-link">Sign in instead?</a></div>';
    exit;
}

// Generate slug from restaurant name
$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $restaurantName), '-'));
$slugBase = $slug;
$slugSuffix = 1;
while (true) {
    $check = $pdo->prepare("SELECT id FROM restaurants WHERE slug = ?");
    $check->execute([$slug]);
    if (!$check->fetch()) break;
    $slug = $slugBase . '-' . $slugSuffix;
    $slugSuffix++;
}

$pdo->beginTransaction();
try {
    // 1. Create user (non-invitation registrations are affiliates/system owners)
    $stmt = $pdo->prepare(
        "INSERT INTO users (first_name, last_name, email, password_hash, company_name, user_type, is_affiliate, is_platform_admin, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, 'system_owner', 1, 0, 1, NOW())"
    );
    $stmt->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $restaurantName]);
    $userId = (int)$pdo->lastInsertId();

    // 2. Create restaurant with location_type
    $stmt = $pdo->prepare(
        "INSERT INTO restaurants (name, slug, email, timezone, location_type, is_active, created_at)
         VALUES (?, ?, ?, 'America/Chicago', ?, 1, NOW())"
    );
    $stmt->execute([$restaurantName, $slug, $email, $locationType]);
    $restaurantId = (int)$pdo->lastInsertId();

    // 3. Assign user as owner/admin of the restaurant
    $pdo->prepare(
        "INSERT INTO user_restaurants (user_id, restaurant_id, role, is_active) VALUES (?, ?, 'admin', 1)"
    )->execute([$userId, $restaurantId]);

    // 4. Create default settings (template scope only — keys the app actually reads)
    $pdo->prepare(
        "INSERT INTO settings (restaurant_id, setting_key, setting_value) VALUES
            (?, 'confirmation_email_enabled', '1'),
            (?, 'reminder_email_enabled', '1'),
            (?, 'reminder_hours_before', '24'),
            (?, 'cancellation_email_enabled', '1'),
            (?, 'cancellation_policy', 'Please cancel at least 24 hours before your appointment time.')"
    )->execute(array_fill(0, 5, $restaurantId));

    $pdo->commit();

    // Log in the new user
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$userId]);
    $userData = $user->fetch(PDO::FETCH_ASSOC);

    if ($userData) {
        login_user($userData, false);
        header('HX-Redirect: /app.php');
        echo '<div class="alert alert-success" id="register-success">Account created! Redirecting...</div>';
    }

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Registration error: ' . $e->getMessage());
    echo '<div class="alert alert-danger" id="register-error-exception">Failed to create account. Please try again later.</div>';
}
