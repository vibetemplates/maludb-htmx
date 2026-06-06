<?php
/**
 * Template Dashboard — Generic Design Showcase
 *
 * The default landing screen for every user. Demonstrates the core design
 * elements of the MaluDB Design Template (stat cards, charts, tables,
 * badges) using real data scoped to the current business.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$user = get_user();
$firstName = htmlspecialchars($user['first_name'] ?? 'there');
$todayLabel = date('l, F j, Y');
$businessId = currentCompanyId();
$pdo = db();

// Safe scalar count: returns 0 if the query fails (e.g. table missing)
$safeCount = function (string $sql, array $params) use ($pdo): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('Dashboard stat query failed: ' . $e->getMessage());
        return 0;
    }
};

// --- Stat cards (real data) ---
$appointmentCount = $safeCount(
    "SELECT COUNT(*) FROM professional_appointments WHERE company_id = ?",
    [$businessId]
);
$clientCount = $safeCount(
    "SELECT COUNT(*) FROM professional_clients WHERE company_id = ?",
    [$businessId]
);
$todoCount = $safeCount(
    "SELECT COUNT(*) FROM todos WHERE company_id = ?",
    [$businessId]
);

// Total Records = all rows belonging to this business across the core tables
$totalRecords = $appointmentCount + $clientCount + $todoCount;

$appointmentsToday = $safeCount(
    "SELECT COUNT(*) FROM professional_appointments
      WHERE company_id = ? AND appointment_date = CURRENT_DATE
        AND status NOT IN ('cancelled')",
    [$businessId]
);

$openTasks = $safeCount(
    "SELECT COUNT(*) FROM todos WHERE company_id = ? AND status != 'completed'",
    [$businessId]
);

// --- Recent Items (real query: latest appointments with client names) ---
$recentItems = [];
try {
    $stmt = $pdo->prepare(
        "SELECT a.service_name, a.appointment_date, a.start_at, a.status,
                c.first_name, c.last_name
           FROM professional_appointments a
           LEFT JOIN professional_clients c ON c.id = a.client_id
          WHERE a.company_id = ?
          ORDER BY a.start_at DESC
          LIMIT 5"
    );
    $stmt->execute([$businessId]);
    $recentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Dashboard recent items query failed: ' . $e->getMessage());
}

// Status -> badge style mapping
$statusBadge = function (string $status): string {
    $map = [
        'confirmed' => 'bg-soft-success text-success',
        'completed' => 'bg-soft-info text-info',
        'pending'   => 'bg-soft-warning text-warning',
        'cancelled' => 'bg-soft-danger text-danger',
        'no_show'   => 'bg-soft-secondary text-secondary',
    ];
    return $map[$status] ?? 'bg-soft-primary text-primary';
};
?>

<div class="container-fluid p-4" id="dashboard-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="dashboard-header">
    <div id="dashboard-header-text">
      <h4 class="fw-bold mb-1" id="dashboard-title">Welcome back, <?php echo $firstName; ?></h4>
      <p class="text-muted mb-0" id="dashboard-subtitle"><?php echo $todayLabel; ?> &mdash; MaluDB Design Template</p>
    </div>
  </div>

  <!-- Stat cards row -->
  <div class="row g-3 mb-4" id="dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-3" id="stat-col-1">
      <div class="card stretch stretch-full" id="stat-card-1">
        <div class="card-body" id="stat-card-1-body">
          <div class="d-flex align-items-center justify-content-between" id="stat-card-1-inner">
            <div id="stat-card-1-text">
              <div class="text-muted fs-12 mb-1">Total Records</div>
              <h5 class="fw-bold mb-0" id="stat-value-records"><?php echo number_format($totalRecords); ?></h5>
            </div>
            <div class="avatar-text avatar-lg bg-primary text-white rounded" id="stat-card-1-icon"><i class="feather-database"></i></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3" id="stat-col-2">
      <div class="card stretch stretch-full" id="stat-card-2">
        <div class="card-body" id="stat-card-2-body">
          <div class="d-flex align-items-center justify-content-between" id="stat-card-2-inner">
            <div id="stat-card-2-text">
              <div class="text-muted fs-12 mb-1">Active Clients</div>
              <h5 class="fw-bold mb-0" id="stat-value-clients"><?php echo number_format($clientCount); ?></h5>
            </div>
            <div class="avatar-text avatar-lg bg-success text-white rounded" id="stat-card-2-icon"><i class="feather-users"></i></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3" id="stat-col-3">
      <div class="card stretch stretch-full" id="stat-card-3">
        <div class="card-body" id="stat-card-3-body">
          <div class="d-flex align-items-center justify-content-between" id="stat-card-3-inner">
            <div id="stat-card-3-text">
              <div class="text-muted fs-12 mb-1">Appointments Today</div>
              <h5 class="fw-bold mb-0" id="stat-value-appointments"><?php echo number_format($appointmentsToday); ?></h5>
            </div>
            <div class="avatar-text avatar-lg bg-warning text-white rounded" id="stat-card-3-icon"><i class="feather-calendar"></i></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3" id="stat-col-4">
      <div class="card stretch stretch-full" id="stat-card-4">
        <div class="card-body" id="stat-card-4-body">
          <div class="d-flex align-items-center justify-content-between" id="stat-card-4-inner">
            <div id="stat-card-4-text">
              <div class="text-muted fs-12 mb-1">Open Tasks</div>
              <h5 class="fw-bold mb-0" id="stat-value-tasks"><?php echo number_format($openTasks); ?></h5>
            </div>
            <div class="avatar-text avatar-lg bg-info text-white rounded" id="stat-card-4-icon"><i class="feather-check-square"></i></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Chart + activity row -->
  <div class="row g-3 mb-4" id="dashboard-middle-row">
    <div class="col-12 col-xl-8" id="chart-col">
      <div class="card stretch stretch-full" id="chart-card">
        <div class="card-header d-flex align-items-center justify-content-between" id="chart-card-header">
          <h6 class="fw-bold mb-0" id="chart-card-title">Activity Overview</h6>
          <div class="btn-group btn-group-sm" role="group" id="chart-range-buttons">
            <button type="button" class="btn btn-outline-secondary" id="chart-range-week">Week</button>
            <button type="button" class="btn btn-outline-secondary active" id="chart-range-month">Month</button>
            <button type="button" class="btn btn-outline-secondary" id="chart-range-year">Year</button>
          </div>
        </div>
        <div class="card-body" id="chart-card-body">
          <div id="dashboard-area-chart"></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-4" id="activity-col">
      <div class="card stretch stretch-full" id="activity-card">
        <div class="card-header" id="activity-card-header">
          <h6 class="fw-bold mb-0" id="activity-card-title">Recent Activity</h6>
        </div>
        <div class="card-body" id="activity-card-body">
          <ul class="list-unstyled mb-0" id="activity-list">
            <li class="d-flex mb-3" id="activity-item-1">
              <div class="avatar-text avatar-md bg-soft-primary text-primary rounded me-3" id="activity-item-1-icon"><i class="feather-user-plus"></i></div>
              <div id="activity-item-1-text">
                <div class="fw-semibold">New client added</div>
                <div class="text-muted fs-12">2 hours ago</div>
              </div>
            </li>
            <li class="d-flex mb-3" id="activity-item-2">
              <div class="avatar-text avatar-md bg-soft-success text-success rounded me-3" id="activity-item-2-icon"><i class="feather-check-circle"></i></div>
              <div id="activity-item-2-text">
                <div class="fw-semibold">Appointment completed</div>
                <div class="text-muted fs-12">4 hours ago</div>
              </div>
            </li>
            <li class="d-flex mb-3" id="activity-item-3">
              <div class="avatar-text avatar-md bg-soft-warning text-warning rounded me-3" id="activity-item-3-icon"><i class="feather-clock"></i></div>
              <div id="activity-item-3-text">
                <div class="fw-semibold">Appointment rescheduled</div>
                <div class="text-muted fs-12">Yesterday</div>
              </div>
            </li>
            <li class="d-flex mb-3" id="activity-item-4">
              <div class="avatar-text avatar-md bg-soft-info text-info rounded me-3" id="activity-item-4-icon"><i class="feather-mail"></i></div>
              <div id="activity-item-4-text">
                <div class="fw-semibold">Reminder email sent</div>
                <div class="text-muted fs-12">Yesterday</div>
              </div>
            </li>
            <li class="d-flex" id="activity-item-5">
              <div class="avatar-text avatar-md bg-soft-danger text-danger rounded me-3" id="activity-item-5-icon"><i class="feather-x-circle"></i></div>
              <div id="activity-item-5-text">
                <div class="fw-semibold">Appointment cancelled</div>
                <div class="text-muted fs-12">2 days ago</div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Table row -->
  <div class="row g-3" id="dashboard-table-row">
    <div class="col-12" id="table-col">
      <div class="card" id="table-card">
        <div class="card-header" id="table-card-header">
          <h6 class="fw-bold mb-0" id="table-card-title">Recent Items</h6>
        </div>
        <div class="card-body p-0" id="table-card-body">
          <div class="table-responsive" id="table-responsive-wrap">
            <table class="table table-hover mb-0" id="dashboard-table">
              <thead id="dashboard-table-head">
                <tr>
                  <th>Client</th>
                  <th>Service</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="dashboard-table-body">
                <?php if (empty($recentItems)): ?>
                <tr id="table-row-empty">
                  <td colspan="4" class="text-center text-muted py-4">No appointments yet.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($recentItems as $i => $item):
                    $clientName = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')) ?: 'Unknown client';
                    $dateLabel = !empty($item['start_at']) ? date('M j, Y g:i A', strtotime($item['start_at'])) : '';
                    $status = $item['status'] ?? '';
                ?>
                <tr id="table-row-<?php echo $i + 1; ?>">
                  <td class="fw-semibold"><?php echo htmlspecialchars($clientName); ?></td>
                  <td><?php echo htmlspecialchars($item['service_name'] ?? ''); ?></td>
                  <td><?php echo htmlspecialchars($dateLabel); ?></td>
                  <td><span class="badge <?php echo $statusBadge($status); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $status))); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<script id="dashboard-chart-script">
(function () {
  var el = document.getElementById('dashboard-area-chart');
  if (!el || typeof ApexCharts === 'undefined') return;
  var chart = new ApexCharts(el, {
    chart: { type: 'area', height: 300, toolbar: { show: false } },
    series: [
      { name: 'Created', data: [31, 40, 28, 51, 42, 60, 55, 48, 65, 58, 72, 68] },
      { name: 'Completed', data: [11, 32, 45, 32, 34, 52, 41, 44, 55, 49, 60, 63] }
    ],
    xaxis: { categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
    legend: { position: 'top', horizontalAlign: 'right' }
  });
  chart.render();
})();
</script>
