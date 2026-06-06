<?php
/**
 * Template Dashboard — Generic Design Showcase
 *
 * The default landing screen for every user. Demonstrates the core design
 * elements of the MaluDB Design Template: stat cards, charts, tables,
 * badges, buttons, and alerts — all using the Kobie/Bootstrap 5 theme.
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$user = get_user();
$firstName = htmlspecialchars($user['first_name'] ?? 'there');
$todayLabel = date('l, F j, Y');
?>

<div class="container-fluid p-4" id="dashboard-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="dashboard-header">
    <div id="dashboard-header-text">
      <h4 class="fw-bold mb-1" id="dashboard-title">Welcome back, <?php echo $firstName; ?></h4>
      <p class="text-muted mb-0" id="dashboard-subtitle"><?php echo $todayLabel; ?> &mdash; MaluDB Design Template</p>
    </div>
    <div id="dashboard-header-actions">
      <button class="btn btn-outline-secondary me-2" id="dashboard-btn-secondary"><i class="feather-download me-1"></i>Export</button>
      <button class="btn btn-primary" id="dashboard-btn-primary"><i class="feather-plus me-1"></i>New Item</button>
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
              <h5 class="fw-bold mb-0">1,248</h5>
            </div>
            <div class="avatar-text avatar-lg bg-primary text-white rounded" id="stat-card-1-icon"><i class="feather-database"></i></div>
          </div>
          <div class="mt-2 fs-12" id="stat-card-1-trend"><span class="text-success me-1"><i class="feather-trending-up"></i> 12.5%</span><span class="text-muted">vs last month</span></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3" id="stat-col-2">
      <div class="card stretch stretch-full" id="stat-card-2">
        <div class="card-body" id="stat-card-2-body">
          <div class="d-flex align-items-center justify-content-between" id="stat-card-2-inner">
            <div id="stat-card-2-text">
              <div class="text-muted fs-12 mb-1">Active Clients</div>
              <h5 class="fw-bold mb-0">86</h5>
            </div>
            <div class="avatar-text avatar-lg bg-success text-white rounded" id="stat-card-2-icon"><i class="feather-users"></i></div>
          </div>
          <div class="mt-2 fs-12" id="stat-card-2-trend"><span class="text-success me-1"><i class="feather-trending-up"></i> 4.2%</span><span class="text-muted">vs last month</span></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3" id="stat-col-3">
      <div class="card stretch stretch-full" id="stat-card-3">
        <div class="card-body" id="stat-card-3-body">
          <div class="d-flex align-items-center justify-content-between" id="stat-card-3-inner">
            <div id="stat-card-3-text">
              <div class="text-muted fs-12 mb-1">Appointments Today</div>
              <h5 class="fw-bold mb-0">14</h5>
            </div>
            <div class="avatar-text avatar-lg bg-warning text-white rounded" id="stat-card-3-icon"><i class="feather-calendar"></i></div>
          </div>
          <div class="mt-2 fs-12" id="stat-card-3-trend"><span class="text-danger me-1"><i class="feather-trending-down"></i> 2.1%</span><span class="text-muted">vs yesterday</span></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3" id="stat-col-4">
      <div class="card stretch stretch-full" id="stat-card-4">
        <div class="card-body" id="stat-card-4-body">
          <div class="d-flex align-items-center justify-content-between" id="stat-card-4-inner">
            <div id="stat-card-4-text">
              <div class="text-muted fs-12 mb-1">Open Tasks</div>
              <h5 class="fw-bold mb-0">23</h5>
            </div>
            <div class="avatar-text avatar-lg bg-info text-white rounded" id="stat-card-4-icon"><i class="feather-check-square"></i></div>
          </div>
          <div class="mt-2 fs-12" id="stat-card-4-trend"><span class="text-success me-1"><i class="feather-trending-up"></i> 8 closed</span><span class="text-muted">this week</span></div>
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
        <div class="card-header d-flex align-items-center justify-content-between" id="table-card-header">
          <h6 class="fw-bold mb-0" id="table-card-title">Recent Items</h6>
          <a href="#" class="btn btn-sm btn-outline-primary" id="table-view-all">View All</a>
        </div>
        <div class="card-body p-0" id="table-card-body">
          <div class="table-responsive" id="table-responsive-wrap">
            <table class="table table-hover mb-0" id="dashboard-table">
              <thead id="dashboard-table-head">
                <tr>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="dashboard-table-body">
                <tr id="table-row-1">
                  <td class="fw-semibold">Quarterly Review</td>
                  <td>Consultation</td>
                  <td>Jun 6, 2026</td>
                  <td><span class="badge bg-soft-success text-success">Confirmed</span></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-icon" id="table-row-1-edit"><i class="feather-edit-2"></i></button>
                    <button class="btn btn-sm btn-icon" id="table-row-1-delete"><i class="feather-trash-2"></i></button>
                  </td>
                </tr>
                <tr id="table-row-2">
                  <td class="fw-semibold">Initial Assessment</td>
                  <td>Intake</td>
                  <td>Jun 7, 2026</td>
                  <td><span class="badge bg-soft-warning text-warning">Pending</span></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-icon" id="table-row-2-edit"><i class="feather-edit-2"></i></button>
                    <button class="btn btn-sm btn-icon" id="table-row-2-delete"><i class="feather-trash-2"></i></button>
                  </td>
                </tr>
                <tr id="table-row-3">
                  <td class="fw-semibold">Follow-up Session</td>
                  <td>Standard</td>
                  <td>Jun 8, 2026</td>
                  <td><span class="badge bg-soft-primary text-primary">Scheduled</span></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-icon" id="table-row-3-edit"><i class="feather-edit-2"></i></button>
                    <button class="btn btn-sm btn-icon" id="table-row-3-delete"><i class="feather-trash-2"></i></button>
                  </td>
                </tr>
                <tr id="table-row-4">
                  <td class="fw-semibold">Strategy Workshop</td>
                  <td>Premium</td>
                  <td>Jun 9, 2026</td>
                  <td><span class="badge bg-soft-danger text-danger">Cancelled</span></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-icon" id="table-row-4-edit"><i class="feather-edit-2"></i></button>
                    <button class="btn btn-sm btn-icon" id="table-row-4-delete"><i class="feather-trash-2"></i></button>
                  </td>
                </tr>
                <tr id="table-row-5">
                  <td class="fw-semibold">Team Onboarding</td>
                  <td>Group</td>
                  <td>Jun 10, 2026</td>
                  <td><span class="badge bg-soft-info text-info">In Progress</span></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-icon" id="table-row-5-edit"><i class="feather-edit-2"></i></button>
                    <button class="btn btn-sm btn-icon" id="table-row-5-delete"><i class="feather-trash-2"></i></button>
                  </td>
                </tr>
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
