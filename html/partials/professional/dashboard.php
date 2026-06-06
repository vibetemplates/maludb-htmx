<?php
/**
 * Professional Dashboard
 *
 * Dashboard plus remaining placeholder views for professional mode.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

requireAuth();

$businessName = $_SESSION['current_company_name'] ?? 'Professional Business';
$todayLabel = date('l, F j, Y');
$view = $_GET['view'] ?? 'dashboard';
$companyId = currentCompanyId();
$professionalProfile = $companyId ? getProfessionalProfile($companyId) : null;
$publicBookingUrl = (
    $professionalProfile
    && (int)($professionalProfile['id'] ?? 0) > 0
    && !empty($professionalProfile['booking_slug'])
) ? '/pro-booking/index.php?professional=' . urlencode($professionalProfile['booking_slug']) : '';

$viewConfig = [
    'dashboard' => [
        'title' => 'Professional Dashboard',
        'subtitle' => 'Your scheduling workspace is now active in professional mode.',
        'icon' => 'feather-home',
        'phase' => 'Step 2 shell ready',
    ],
    'calendar' => [
        'title' => 'Calendar',
        'subtitle' => 'Use the main calendar screen to manage day, week, month, and upcoming appointment views.',
        'icon' => 'feather-calendar',
        'phase' => 'Live in navigation',
    ],
    'appointments' => [
        'title' => 'Appointments',
        'subtitle' => 'Use the appointment calendar screen to create, edit, reschedule, and update appointments.',
        'icon' => 'feather-clock',
        'phase' => 'Live in navigation',
    ],
    'clients' => [
        'title' => 'Clients',
        'subtitle' => 'Use the client directory to manage contact data, notes, and appointment history.',
        'icon' => 'feather-users',
        'phase' => 'Live in navigation',
    ],
    'services' => [
        'title' => 'Services',
        'subtitle' => 'This area will manage bookable services, duration, price, and buffers.',
        'icon' => 'feather-briefcase',
        'phase' => 'Planned for services CRUD step',
    ],
    'availability' => [
        'title' => 'Availability',
        'subtitle' => 'This area will manage weekly working hours and scheduling rules.',
        'icon' => 'feather-clock',
        'phase' => 'Planned for availability step',
    ],
    'time_off' => [
        'title' => 'Time Off',
        'subtitle' => 'This area will manage vacations, holidays, and manual blocked time.',
        'icon' => 'feather-moon',
        'phase' => 'Planned for time-off step',
    ],
    'settings' => [
        'title' => 'Professional Settings',
        'subtitle' => 'This area will manage the business profile, booking slug, and booking rules.',
        'icon' => 'feather-settings',
        'phase' => 'Planned for professional profile/settings step',
    ],
    'reports' => [
        'title' => 'Reports',
        'subtitle' => 'Use the reports screen to review appointment volume, revenue, client activity, and utilization.',
        'icon' => 'feather-bar-chart-2',
        'phase' => 'Live in navigation',
    ],
];

if (!isset($viewConfig[$view])) {
    $view = 'dashboard';
}

$currentView = $viewConfig[$view];
$isDashboardView = ($view === 'dashboard');

// Check company status for setup checklist
$currentCompanyStatus = 'active';
if ($companyId) {
    $statusStmt = db()->prepare("SELECT status FROM companies WHERE id = ?");
    $statusStmt->execute([$companyId]);
    $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
    if ($statusRow && !empty($statusRow['status'])) {
        $currentCompanyStatus = $statusRow['status'];
    }
}

$isInSetup = ($currentCompanyStatus === 'in-setup');

$pdo = db();
$userId = currentUserId();

// Build setup checklist when in-setup
$setupChecklist = [];
if ($isInSetup && $companyId) {
    $pdo = db();

    // 1. Setup Company Profile — check name, phone, email in companies
    $s = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE id = ? AND name IS NOT NULL AND name != '' AND phone IS NOT NULL AND phone != '' AND email IS NOT NULL AND email != ''");
    $s->execute([$companyId]);
    $setupChecklist[] = ['label' => 'Setup Company Profile', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-briefcase', 'link' => '/partials/professional/settings.php'];

    // 2. Add Services
    $s = $pdo->prepare("SELECT COUNT(*) FROM professional_services WHERE company_id = ? AND is_active = 1");
    $s->execute([$companyId]);
    $setupChecklist[] = ['label' => 'Add Services', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-clipboard', 'link' => '/partials/professional/services.php'];

    // 3. Setup Availability
    $s = $pdo->prepare("SELECT COUNT(*) FROM professional_availability_rules WHERE company_id = ? AND is_active = 1");
    $s->execute([$companyId]);
    $setupChecklist[] = ['label' => 'Setup Availability', 'done' => (int)$s->fetchColumn() > 0, 'icon' => 'feather-clock', 'link' => '/partials/professional/availability.php'];

    // 4. Invite Users (at least 2 users = creator + invited)
    $s = $pdo->prepare("SELECT COUNT(*) FROM user_companies WHERE company_id = ? AND is_active = 1");
    $s->execute([$companyId]);
    $setupChecklist[] = ['label' => 'Invite Users', 'done' => (int)$s->fetchColumn() >= 2, 'icon' => 'feather-user-plus', 'link' => '/partials/settings/users.php'];
}
?>

<div id="professional-dashboard-main" class="p-4">
    <div id="professional-dashboard-header" class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div id="professional-dashboard-header-copy">
            <h4 class="mb-1" id="professional-dashboard-title">
                <i class="<?php echo htmlspecialchars($currentView['icon']); ?> me-2"></i><?php echo htmlspecialchars($currentView['title']); ?>
            </h4>
            <p class="text-muted mb-1" id="professional-dashboard-subtitle">
                <?php echo htmlspecialchars($businessName); ?> &middot; <?php echo htmlspecialchars($todayLabel); ?>
            </p>
            <small class="text-muted" id="professional-dashboard-phase"><?php echo htmlspecialchars($currentView['phase']); ?></small>
        </div>
        <div id="professional-dashboard-header-actions" class="d-flex flex-wrap gap-2">
            <a href="#" class="btn btn-primary" id="professional-dashboard-action-appointments"
               hx-get="/partials/professional/appointment-form.php?date=<?php echo urlencode(date('Y-m-d')); ?>"
               hx-target="#professional-modal-container"
               hx-swap="innerHTML">
                <i class="feather-plus me-1"></i> New Appointment
            </a>
            <a href="#" class="btn btn-outline-primary" id="professional-dashboard-action-clients"
               hx-get="/partials/professional/client-form.php"
               hx-target="#professional-modal-container"
               hx-swap="innerHTML">
                <i class="feather-user-plus me-1"></i> New Client
            </a>
            <a href="#" class="btn btn-outline-secondary" id="professional-dashboard-action-time-off"
               hx-get="/partials/professional/time-off.php"
               hx-target="#page-content">
                <i class="feather-slash me-1"></i> Block Time
            </a>
            <?php if ($publicBookingUrl !== ''): ?>
            <a href="<?php echo htmlspecialchars($publicBookingUrl); ?>" class="btn btn-outline-success" id="professional-dashboard-action-booking-page" target="_blank" rel="noopener">
                <i class="feather-external-link me-1"></i> View Booking Page
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isDashboardView && $isInSetup): ?>
    <!-- Setup Checklist -->
    <div id="professional-dashboard-setup-checklist-row" class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" id="professional-dashboard-setup-card">
                <div class="card-body" id="professional-dashboard-setup-body">
                    <h5 class="mb-1" id="professional-dashboard-setup-title"><i class="feather-check-square me-2"></i>Setup Checklist</h5>
                    <p class="text-muted mb-4" id="professional-dashboard-setup-subtitle">Complete the following steps to get your company up and running.</p>
                    <div class="row g-3" id="professional-dashboard-setup-items">
                        <?php foreach ($setupChecklist as $ci => $item): ?>
                        <div class="col-md-6 col-xl-4" id="professional-dashboard-setup-item-<?php echo $ci; ?>">
                            <a href="#" class="text-decoration-none"
                               hx-get="<?php echo htmlspecialchars($item['link']); ?>"
                               hx-target="#page-content"
                               id="professional-dashboard-setup-link-<?php echo $ci; ?>">
                                <div class="d-flex align-items-center p-3 rounded border <?php echo $item['done'] ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle'; ?>"
                                     id="professional-dashboard-setup-box-<?php echo $ci; ?>">
                                    <div class="me-3" id="professional-dashboard-setup-icon-<?php echo $ci; ?>">
                                        <?php if ($item['done']): ?>
                                        <i class="feather-check-circle text-success" style="font-size: 1.5rem;"></i>
                                        <?php else: ?>
                                        <i class="feather-circle text-warning" style="font-size: 1.5rem;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div id="professional-dashboard-setup-label-<?php echo $ci; ?>">
                                        <h6 class="mb-0 <?php echo $item['done'] ? 'text-success' : 'text-dark'; ?>">
                                            <i class="<?php echo htmlspecialchars($item['icon']); ?> me-1"></i>
                                            <?php echo htmlspecialchars($item['label']); ?>
                                        </h6>
                                        <small class="<?php echo $item['done'] ? 'text-success' : 'text-muted'; ?>">
                                            <?php echo $item['done'] ? 'Complete' : 'Not started'; ?>
                                        </small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    $doneCount = count(array_filter($setupChecklist, fn($i) => $i['done']));
                    $totalCount = count($setupChecklist);
                    $pct = $totalCount > 0 ? round(($doneCount / $totalCount) * 100) : 0;
                    ?>
                    <div class="mt-4" id="professional-dashboard-setup-progress-wrap">
                        <div class="d-flex justify-content-between mb-1" id="professional-dashboard-setup-progress-labels">
                            <small class="fw-bold" id="professional-dashboard-setup-progress-text"><?php echo $doneCount; ?> of <?php echo $totalCount; ?> complete</small>
                            <small class="fw-bold" id="professional-dashboard-setup-progress-pct"><?php echo $pct; ?>%</small>
                        </div>
                        <div class="progress" style="height: 8px;" id="professional-dashboard-setup-progress-bar">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $pct; ?>%;" id="professional-dashboard-setup-progress-fill"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($isDashboardView): ?>
    <div id="professional-dashboard-stats-row" class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-today-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-today-card">
                <div class="card-body" id="professional-dashboard-stat-today-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-today-label">Today</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-today-value">0</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-today-copy">Use the calendar to manage today's appointments.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-upcoming-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-upcoming-card">
                <div class="card-body" id="professional-dashboard-stat-upcoming-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-upcoming-label">Upcoming</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-upcoming-value">0</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-upcoming-copy">The next client visits will be surfaced here.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-availability-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-availability-card">
                <div class="card-body" id="professional-dashboard-stat-availability-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-availability-label">Availability</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-availability-value">Active</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-availability-copy">Availability rules, blocked time, and slot checks are now active.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3" id="professional-dashboard-stat-build-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-stat-build-card">
                <div class="card-body" id="professional-dashboard-stat-build-body">
                    <h6 class="text-muted mb-2" id="professional-dashboard-stat-build-label">Build Status</h6>
                    <h3 class="mb-1" id="professional-dashboard-stat-build-value">Complete</h3>
                    <p class="mb-0 text-muted" id="professional-dashboard-stat-build-copy">Professional mode now includes reporting for appointments, revenue, client activity, cancellations, and utilization.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="professional-dashboard-content-row" class="row g-3">
        <div class="col-lg-8" id="professional-dashboard-overview-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-overview-card">
                <div class="card-body" id="professional-dashboard-overview-body">
                    <h5 class="mb-3" id="professional-dashboard-overview-title">Professional mode is now active</h5>
                    <p class="text-muted mb-3" id="professional-dashboard-overview-copy">
                        <?php echo htmlspecialchars($currentView['subtitle']); ?>
                    </p>
                    <p class="mb-0 text-muted" id="professional-dashboard-overview-copy-secondary">
                        Professional mode now includes settings, services, availability, blocked time, appointment management, the client CRM, the public booking page, client self-service appointment changes, appointment notifications, and reporting.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-4" id="professional-dashboard-links-col">
            <div class="card border-0 shadow-sm h-100" id="professional-dashboard-links-card">
                <div class="card-body" id="professional-dashboard-links-body">
                    <h5 class="mb-3" id="professional-dashboard-links-title">Build Sequence</h5>
                    <div id="professional-dashboard-links-wrap" class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-settings"
                           hx-get="/partials/professional/settings.php"
                           hx-target="#page-content">Professional Settings</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-services"
                           hx-get="/partials/professional/services.php"
                           hx-target="#page-content">Services</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-availability"
                           hx-get="/partials/professional/availability.php"
                           hx-target="#page-content">Availability</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-calendar"
                           hx-get="/partials/professional/calendar.php?view=week"
                           hx-target="#page-content">Calendar</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-clients"
                           hx-get="/partials/professional/clients.php"
                           hx-target="#page-content">Clients</a>
                        <a href="#" class="btn btn-outline-primary text-start" id="professional-dashboard-link-reports"
                           hx-get="/partials/professional/reports.php"
                           hx-target="#page-content">Reports</a>
                        <?php if ($publicBookingUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($publicBookingUrl); ?>" class="btn btn-outline-success text-start" id="professional-dashboard-link-booking-page" target="_blank" rel="noopener">Public Booking Page</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div id="professional-placeholder-row" class="row g-3">
        <div class="col-12" id="professional-placeholder-col">
            <div class="card border-0 shadow-sm" id="professional-placeholder-card">
                <div class="card-body" id="professional-placeholder-body">
                    <h5 class="mb-3" id="professional-placeholder-title"><?php echo htmlspecialchars($currentView['title']); ?></h5>
                    <p class="text-muted mb-3" id="professional-placeholder-copy">
                        <?php echo htmlspecialchars($currentView['subtitle']); ?>
                    </p>
                    <p class="mb-3 text-muted" id="professional-placeholder-copy-secondary">
                        This screen remains a placeholder while the remaining professional features are built, so professional users do not fall back into the company interface.
                    </p>
                    <div id="professional-placeholder-actions" class="d-flex flex-wrap gap-2">
                        <a href="#" class="btn btn-primary" id="professional-placeholder-back-dashboard"
                           hx-get="/partials/professional/dashboard.php"
                           hx-target="#page-content">Back to Dashboard</a>
                        <a href="#" class="btn btn-outline-primary" id="professional-placeholder-next-settings"
                           hx-get="/partials/professional/settings.php"
                           hx-target="#page-content">Open Professional Settings</a>
                        <a href="#" class="btn btn-outline-primary" id="professional-placeholder-next-calendar"
                           hx-get="/partials/professional/calendar.php?view=week"
                           hx-target="#page-content">Open Calendar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
