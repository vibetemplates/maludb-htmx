<?php
require_once '../helpers/auth.php';
require_once '../helpers/db.php';

requireAuth();
$user = get_user();
$businessName = $_SESSION['current_company_name'] ?? 'My Business';
$currentRole = $_SESSION['current_role'] ?? '';
$businessPhone = '';
if (!empty($_SESSION['current_company_id'])) {
    $r = getCompany($_SESSION['current_company_id']);
    if ($r && !empty($r['phone'])) {
        $digits = preg_replace('/\D/', '', $r['phone']);
        if (strlen($digits) === 10) {
            $businessPhone = '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
        } elseif (strlen($digits) === 11 && $digits[0] === '1') {
            $businessPhone = '(' . substr($digits, 1, 3) . ') ' . substr($digits, 4, 3) . '-' . substr($digits, 7);
        } else {
            $businessPhone = $r['phone'];
        }
    }
}
// Header title shows the Display Name from Professional Settings
// (professional_profiles.display_name). Fallback avoids the companies
// table — the template should lean on it as little as possible.
$headerDisplayName = 'Company Not Setup';
if (!empty($_SESSION['current_company_id'])) {
    try {
        $dnStmt = db()->prepare("SELECT display_name FROM professional_profiles WHERE company_id = ?");
        $dnStmt->execute([$_SESSION['current_company_id']]);
        $dn = $dnStmt->fetchColumn();
        if ($dn !== false && trim((string)$dn) !== '') {
            $headerDisplayName = $dn;
        }
    } catch (Exception $e) {
        error_log('Header display name lookup failed: ' . $e->getMessage());
    }
}
$businesses = getUserCompanies($user['id']);
$hasMultipleBusinesses = count($businesses) > 1;

// Unified template shell: every user gets the same screen and the same
// navigation regardless of role, product type, or settings.
$defaultPageContentPath = '/partials/dashboard/index.php';
$userSettingsPath = '/partials/professional/settings.php';
$appMetaDescription = 'MaluDB Design Template';
$appTitleSuffix = 'MaluDB Template';
$appFooterLabel = 'MaluDB Design Template';
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?php echo htmlspecialchars($appMetaDescription); ?>" />
    <title><?php echo htmlspecialchars($businessName); ?> - <?php echo htmlspecialchars($appTitleSuffix); ?></title>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon-vt.png" />

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css" />

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="assets/vendor/kobie-vendors.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/vendor/feather.min.css" />

    <!-- Kobie Theme CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/kobie-theme.min.css" />

    <!-- Overlay Scrollbar CSS -->
    <link rel="stylesheet" href="assets/vendor/overlay-scroll/OverlayScrollbars.min.css" />

    <!-- Custom Overrides (must be last) -->
    <link rel="stylesheet" type="text/css" href="assets/css/kobie-custom.css" />

    <!-- HTMX Library -->
    <script src="https://unpkg.com/htmx.org@2.0.8"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
  </head>

  <body>

    <!-- Page wrapper starts -->
    <div class="page-wrapper" id="app-page-wrapper">

      <!-- Main container starts -->
      <div class="main-container" id="app-main-container">

        <!-- Sidebar wrapper starts -->
        <nav id="kobie-sidebar" class="nxl-navigation">
          <div id="kobie-nav-wrapper" class="navbar-wrapper">

            <!-- Logo starts -->
            <div id="kobie-logo-area" class="m-header">
              <a href="app.php" class="b-brand">
                <img src="/assets/images/logo.png" alt="MaluDB" class="logo logo-lg" style="height: 40px; width: auto;" id="brand-logo-lg">
                <span class="logo logo-sm fw-bold fs-5 text-primary" id="brand-text-sm">M</span>
              </a>
            </div>
            <!-- Logo ends -->

            <!-- Navigation menu starts -->
            <div id="kobie-nav-menu" class="navbar-content">
              <ul class="nxl-navbar" id="sidebar-nav-list">
                <!-- Unified navigation: identical for every user -->

                <!-- SCHEDULING section -->
                <li class="nxl-item nxl-caption" id="nav-caption-scheduling"><label>SCHEDULING</label></li>
                <li class="nxl-item active current-page" id="nav-dashboard">
                  <a href="#" class="nxl-link" hx-get="/partials/dashboard/index.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-home"></i></span>
                    <span class="nxl-mtext">Dashboard</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-appointments">
                  <a href="#" class="nxl-link" hx-get="/partials/professional/calendar.php?view=upcoming" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-clock"></i></span>
                    <span class="nxl-mtext">Appointments</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-calendar">
                  <a href="#" class="nxl-link" hx-get="/partials/professional/calendar.php?view=week" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-calendar"></i></span>
                    <span class="nxl-mtext">Calendar</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-todos">
                  <a href="#" class="nxl-link" hx-get="/partials/todos/list.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-check-square"></i></span>
                    <span class="nxl-mtext">Todo List</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-memory-documents">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/documents.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-file-text"></i></span>
                    <span class="nxl-mtext">Documents</span>
                  </a>
                </li>

                <!-- BUSINESS section -->
                <li class="nxl-item nxl-caption" id="nav-caption-business"><label>BUSINESS</label></li>
                <li class="nxl-item" id="nav-clients">
                  <a href="#" class="nxl-link" hx-get="/partials/professional/clients.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-users"></i></span>
                    <span class="nxl-mtext">Clients</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-services">
                  <a href="#" class="nxl-link" hx-get="/partials/professional/services.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-briefcase"></i></span>
                    <span class="nxl-mtext">Services</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-availability">
                  <a href="#" class="nxl-link" hx-get="/partials/professional/availability.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-clock"></i></span>
                    <span class="nxl-mtext">Availability</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-time-off">
                  <a href="#" class="nxl-link" hx-get="/partials/professional/time-off.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-moon"></i></span>
                    <span class="nxl-mtext">Time Off</span>
                  </a>
                </li>

                <!-- MEMORY ELEMENTS section -->
                <li class="nxl-item nxl-caption" id="nav-caption-memory"><label>MEMORY ELEMENTS</label></li>
                <li class="nxl-item" id="nav-memory-projects">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/projects.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-folder"></i></span>
                    <span class="nxl-mtext">Projects</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-memory-people">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/people.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-users"></i></span>
                    <span class="nxl-mtext">People</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-memory-episodes">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/episodes.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-activity"></i></span>
                    <span class="nxl-mtext">Events/Episodes</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-memory-subjects">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/subjects.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-box"></i></span>
                    <span class="nxl-mtext">Subjects/Things</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-memory-verbs">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/verbs.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-zap"></i></span>
                    <span class="nxl-mtext">Verbs/Actions</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-model-prompts">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/model-prompts.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-message-square"></i></span>
                    <span class="nxl-mtext">Model Prompts</span>
                  </a>
                </li>

                <!-- MALUDB SETUP section -->
                <li class="nxl-item nxl-caption" id="nav-caption-maludb-setup"><label>MALUDB SETUP</label></li>
                <li class="nxl-item" id="nav-setup-episode-types">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/setup/episode-types.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-tag"></i></span>
                    <span class="nxl-mtext">Episode Types</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-setup-document-types">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/setup/document-types.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-bookmark"></i></span>
                    <span class="nxl-mtext">Document Types</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-setup-subject-types">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/setup/subject-types.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-grid"></i></span>
                    <span class="nxl-mtext">Subject Types</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-setup-verb-types">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/setup/verb-types.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-list"></i></span>
                    <span class="nxl-mtext">Verb Types</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-setup-attribute-templates">
                  <a href="#" class="nxl-link" hx-get="/partials/memory/setup/attribute-templates.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-sliders"></i></span>
                    <span class="nxl-mtext">Attribute Templates</span>
                  </a>
                </li>

                <!-- ADMIN section -->
                <li class="nxl-item nxl-caption" id="nav-caption-admin"><label>ADMIN</label></li>
                <li class="nxl-item" id="nav-settings">
                  <a href="#" class="nxl-link" hx-get="/partials/professional/settings.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-settings"></i></span>
                    <span class="nxl-mtext">Settings</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-notifications">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/notifications.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-bell"></i></span>
                    <span class="nxl-mtext">Notifications</span>
                  </a>
                </li>
                <li class="nxl-item" id="nav-token-setup">
                  <a href="#" class="nxl-link" hx-get="/partials/settings/token-setup.php" hx-target="#page-content">
                    <span class="nxl-micon"><i class="feather-key"></i></span>
                    <span class="nxl-mtext">Token Setup</span>
                  </a>
                </li>

                <!-- Logout (always visible) -->
                <li class="nxl-item nxl-caption" id="nav-caption-spacer"><label></label></li>
                <li class="nxl-item" id="nav-logout">
                  <a href="/logout.php" class="nxl-link text-danger">
                    <span class="nxl-micon"><i class="feather-log-out"></i></span>
                    <span class="nxl-mtext">Logout</span>
                  </a>
                </li>
              </ul>
            </div>
            <!-- Navigation menu ends -->

          </div>
        </nav>
        <!-- Sidebar wrapper ends -->

        <!-- App container starts -->
        <div id="kobie-content-wrapper" class="nxl-container">

          <!-- App header starts -->
          <header id="kobie-header" class="nxl-header">
            <div class="header-wrapper" id="header-wrapper">

              <!-- Header left starts -->
              <div class="header-left d-flex align-items-center gap-4" id="header-left">
                <!-- Mobile toggler -->
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                  <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box">
                      <div class="hamburger-inner"></div>
                    </div>
                  </div>
                </a>

                <!-- Navigation toggle -->
                <div class="nxl-navigation-toggle" id="nav-toggle-container">
                  <a href="javascript:void(0);" id="vertical-nav-toggle">
                    <i class="feather-align-left"></i>
                  </a>
                </div>

                <!-- Page title -->
                <div class="d-flex align-items-center" id="header-title-area">
                  <h5 class="fw-bold text-white m-0" id="page-title"><?php echo htmlspecialchars($headerDisplayName); ?><?php if ($businessPhone): ?> <span class="fw-normal fs-6 ms-2" id="header-phone"><?php echo htmlspecialchars($businessPhone); ?></span><?php endif; ?></h5>
                </div>
              </div>
              <!-- Header left ends -->

              <!-- Header right starts -->
              <div class="header-right ms-auto" id="header-right">
                <div class="d-flex align-items-center" id="header-right-items">

                  <!-- Global loading indicator -->
                  <div class="htmx-indicator me-3" id="global-loading-indicator">
                    <div class="spinner-border spinner-border-sm text-white" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                  </div>

                  <!-- Business switcher (if user has multiple businesses) -->
                  <?php if ($hasMultipleBusinesses): ?>
                  <div class="dropdown me-3" id="business-switcher">
                    <a class="dropdown-toggle d-flex align-items-center text-white"
                       href="#!" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <i class="feather-repeat fs-5 me-2"></i>
                      <span class="fw-bold d-none d-md-inline" id="current-business-name"><?php echo htmlspecialchars($businessName); ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg" style="min-width: 20rem;" id="business-switcher-menu">
                      <?php foreach ($businesses as $r): ?>
                      <a class="dropdown-item d-flex align-items-center <?php echo ($r['id'] == ($_SESSION['current_company_id'] ?? 0)) ? 'active' : ''; ?>"
                         href="#"
                         hx-post="/partials/auth/switch-company.php"
                         hx-vals='{"company_id": <?php echo (int)$r['id']; ?>}'
                         hx-target="#page-content"
                         id="business-switch-<?php echo (int)$r['id']; ?>">
                        <i class="feather-home fs-5 me-2"></i>
                        <?php echo htmlspecialchars($r['name']); ?>
                        <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($r['role']); ?></span>
                      </a>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <!-- User dropdown -->
                  <div class="dropdown" id="kobie-user-dropdown">
                    <a id="userSettings" class="dropdown-toggle d-flex py-2 align-items-center text-white"
                       href="#!" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                      <div class="text-truncate d-lg-flex flex-column d-none ms-2" id="user-info-display">
                        <span class="fw-bold fs-18"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        <span class="fs-11 text-white-50"><?php echo ucfirst(htmlspecialchars($currentRole)); ?></span>
                      </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg" id="user-dropdown-menu">
                      <a class="dropdown-item d-flex align-items-center" href="#"
                         hx-get="<?php echo htmlspecialchars($userSettingsPath); ?>" hx-target="#page-content" hx-swap="innerHTML"
                         id="user-menu-settings">
                        <i class="feather-settings fs-5 me-2"></i>Settings
                      </a>
                      <div class="dropdown-divider"></div>
                      <div class="mx-3 mt-2 d-grid" id="user-menu-logout">
                        <a href="/logout.php" class="btn btn-primary">Logout</a>
                      </div>
                    </div>
                  </div>

                </div>
              </div>
              <!-- Header right ends -->

            </div>
          </header>
          <!-- App header ends -->

          <!-- App body starts -->
          <main class="nxl-content" id="page-content"
                hx-get="<?php echo htmlspecialchars($defaultPageContentPath); ?>"
                hx-trigger="load">
            <div class="main-content" id="main-content-inner">
              <!-- Content will be loaded here via HTMX -->
            </div>
          </main>
          <!-- App body ends -->

          <!-- App footer starts -->
          <footer id="kobie-footer" class="nxl-footer">
            <div class="footer-wrapper" id="footer-wrapper">
              <p class="mb-0 text-muted" id="footer-text">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($appFooterLabel); ?></p>
            </div>
          </footer>
          <!-- App footer ends -->

        </div>
        <!-- App container ends -->

      </div>
      <!-- Main container ends -->

    </div>
    <!-- Page wrapper ends -->

    <!-- JavaScript Files -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/moment.min.js"></script>

    <!-- Perfect Scrollbar JS -->
    <script src="assets/vendor/perfect-scrollbar.min.js"></script>

    <!-- NXL Navigation JS -->
    <script src="assets/js/nxl-navigation.min.js"></script>

    <!-- Overlay Scroll JS -->
    <script src="assets/vendor/overlay-scroll/jquery.overlayScrollbars.min.js"></script>
    <script src="assets/vendor/overlay-scroll/custom-scrollbar.js"></script>

    <!-- Apex Charts -->
    <script src="assets/vendor/apex/apexcharts.min.js"></script>

    <!-- Custom JS -->
    <script src="assets/js/custom.js"></script>

    <!-- HTMX Navigation Handler -->
    <script>
      // Handle navigation active state
      document.addEventListener('htmx:afterRequest', function(evt) {
        var requestTarget = evt.detail && evt.detail.target ? evt.detail.target : null;
        if (requestTarget && requestTarget.id === 'page-content') {
          document.querySelectorAll('.nxl-navbar .nxl-item').forEach(function(li) {
            li.classList.remove('active', 'current-page');
          });
          var trigger = evt.detail.elt;
          if (trigger && trigger.closest('.nxl-item')) {
            trigger.closest('.nxl-item').classList.add('active', 'current-page');
          }
        }
      });

      // Reinitialize Bootstrap components after HTMX swap
      document.addEventListener('htmx:afterSwap', function(evt) {
        var swapTarget = evt.detail && evt.detail.target ? evt.detail.target : null;
        if (swapTarget && swapTarget.id === 'page-content') {
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
          tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });
        }
      });

      // Handle company switch — reload page to refresh sidebar permissions
      document.body.addEventListener('companySwitched', function() {
        window.location.reload();
      });

      // Global loading indicator
      document.body.addEventListener('htmx:beforeRequest', function() {
        document.getElementById('global-loading-indicator').style.display = 'block';
      });
      document.body.addEventListener('htmx:afterRequest', function() {
        document.getElementById('global-loading-indicator').style.display = 'none';
      });

      // Auto-show modals loaded into HTMX modal targets
      document.body.addEventListener('htmx:afterSwap', function(evt) {
        var swapTarget = evt.detail && evt.detail.target ? evt.detail.target : null;
        if (!swapTarget) {
          return;
        }

        var modal = swapTarget.querySelector('.modal');
        if (modal && modal.classList.contains('fade') && !modal.classList.contains('show')) {
          var bsModal = new bootstrap.Modal(modal);
          bsModal.show();
          modal.addEventListener('shown.bs.modal', function() {
            var firstInput = modal.querySelector('input:not([type="hidden"]), textarea, select');
            if (firstInput) firstInput.focus();
          });
          modal.addEventListener('hidden.bs.modal', function() {
            swapTarget.innerHTML = '';
          });
        }
      });

      // Close modal event
      document.body.addEventListener('closeModal', function() {
        var modals = document.querySelectorAll('.modal.show');
        modals.forEach(function(modal) {
          var bsModal = bootstrap.Modal.getInstance(modal);
          if (bsModal) bsModal.hide();
        });
        ['modal-container', 'professional-modal-container'].forEach(function(containerId) {
          var container = document.getElementById(containerId);
          if (container) {
            container.innerHTML = '';
          }
        });
      });
    </script>

    <!-- Modal Container for HTMX-loaded modals -->
    <div id="modal-container"></div>
    <div id="professional-modal-container"></div>

  </body>

</html>
