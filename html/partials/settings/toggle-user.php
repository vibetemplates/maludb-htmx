<?php
require_once '../../../helpers/auth.php';
require_once '../../../helpers/csrf.php';

requireAuth();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '<div class="alert alert-warning" id="toggle-user-method-error">Invalid request method.</div>';
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo '<div class="alert alert-danger" id="toggle-user-csrf-error">Invalid security token. Please refresh and try again.</div>';
    exit;
}

$companyId = currentCompanyId();
$userId = (int)($_POST['user_id'] ?? 0);

if (!$companyId || !$userId) {
    http_response_code(400);
    echo '<div class="alert alert-danger" id="toggle-user-invalid">Invalid request.</div>';
    exit;
}

// Prevent deactivating yourself
if ($userId === currentUserId()) {
    echo '<div class="alert alert-danger" id="toggle-user-self-error">You cannot deactivate yourself.</div>';
    exit;
}

$pdo = db();

// Verify membership exists for this company
$stmt = $pdo->prepare("SELECT is_active FROM user_companies WHERE user_id = ? AND company_id = ?");
$stmt->execute([$userId, $companyId]);
$membership = $stmt->fetch();

if (!$membership) {
    echo '<div class="alert alert-danger" id="toggle-user-notfound">User not found in this company.</div>';
    exit;
}

// Toggle is_active
$newStatus = $membership['is_active'] ? 0 : 1;
$stmt = $pdo->prepare("UPDATE user_companies SET is_active = ? WHERE user_id = ? AND company_id = ?");
$stmt->execute([$newStatus, $userId, $companyId]);

$statusLabel = $newStatus ? 'activated' : 'deactivated';

// Trigger refresh (must be before output)
header('HX-Trigger: refreshUserList');

echo '<div class="alert alert-success alert-dismissible fade show" id="toggle-user-success">
        <i class="feather-check-circle me-1"></i> Staff member ' . $statusLabel . ' successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>';
