<?php
require_once '../../../helpers/session.php';
require_once '../../../helpers/csrf.php';
require_once '../../../helpers/validation.php';
require_once '../../../helpers/db.php';
require_once '../../../helpers/notifications.php';
require_once '../../../models/User.php';

init_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process forgot password request

    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo '<div class="alert alert-danger" id="forgot-password-error-csrf">
                <i class="feather-alert-triangle-fill"></i>
                Invalid security token. Please refresh the page and try again.
              </div>';
        exit;
    }

    // Validate required fields
    if (empty($_POST['email'])) {
        echo '<div class="alert alert-danger" id="forgot-password-error-required">
                <i class="feather-alert-triangle-fill"></i>
                Please enter your email address.
              </div>';
        exit;
    }

    // Sanitize input
    $email = sanitize_input($_POST['email']);

    // Validate email format
    if (!validate_email($email)) {
        echo '<div class="alert alert-danger" id="forgot-password-error-email">
                <i class="feather-alert-triangle-fill"></i>
                Please enter a valid email address.
              </div>';
        exit;
    }

    // Generate reset token (always show success message for security)
    $userModel = new User();
    $token = $userModel->createPasswordResetToken($email);

    if ($token) {
        // Send the reset link from the user's own tenant (falls back to PHP
        // mail() inside sendEmail() when no MailerSend key is configured).
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $resetUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . urlencode($token);

        $companyStmt = db()->prepare(
            "SELECT uc.company_id FROM user_companies uc
               JOIN users u ON u.id = uc.user_id
              WHERE u.email = ? AND uc.is_active = 1
              ORDER BY uc.id LIMIT 1"
        );
        $companyStmt->execute([$email]);
        $companyId = (int)($companyStmt->fetchColumn() ?: 0);

        $htmlBody = '<p>We received a request to reset your password.</p>'
                  . '<p><a href="' . htmlspecialchars($resetUrl) . '">Click here to choose a new password</a>.</p>'
                  . '<p>This link expires in 1 hour. If you did not request a reset, you can ignore this email.</p>';
        $textBody = "We received a request to reset your password.\n\n"
                  . "Open this link to choose a new password (expires in 1 hour):\n{$resetUrl}\n\n"
                  . "If you did not request a reset, you can ignore this email.";

        if (!sendEmail($companyId, $email, 'Reset your password', $htmlBody, $textBody)) {
            error_log("Password reset email failed to send for {$email}");
        }
    }

    // Always show success message (don't reveal if email exists)
    echo '<div class="alert alert-success" id="forgot-password-success">
            <i class="feather-check-circle-fill"></i>
            <strong>Check your email!</strong><br>
            If an account exists with this email, you will receive password reset instructions.
            <br><small class="text-muted">The link will expire in 1 hour.</small>
          </div>';

    exit;

} else {
    // GET request - not supported
    http_response_code(405);
    echo '<div class="alert alert-warning" id="forgot-password-error-method">
            Invalid request method.
          </div>';
}
