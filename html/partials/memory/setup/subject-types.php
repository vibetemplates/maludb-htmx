<?php
/**
 * MaluDB Setup — Subject Types (read-only)
 *
 * Values in maludb_subject_type are trigger-enforced on maludb_subject rows;
 * the v1 API exposes this list read-only (/v1/subject-types).
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';

requireAuth();
$pdo = db();

$rows = [];
$loadError = '';
try {
    $rows = $pdo->query(
        "SELECT subject_type, display_name, description, sort_order, system_defined
           FROM maludb_subject_type
          ORDER BY sort_order, subject_type"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $loadError = $e->getMessage();
}
?>
<div class="container-fluid p-4" id="subject-types-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="subject-types-header">
    <div id="subject-types-header-text">
      <h4 class="fw-bold mb-1" id="subject-types-title"><i class="feather-box me-2"></i>Subject Types</h4>
      <p class="text-muted mb-0" id="subject-types-subtitle">MaluDB setup &mdash; read-only registry (values are trigger-enforced on subjects)</p>
    </div>
    <div id="subject-types-header-actions">
      <span class="badge bg-soft-secondary text-secondary" id="subject-types-readonly-badge"><i class="feather-lock me-1"></i>Read-only</span>
    </div>
  </div>

  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="subject-types-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load subject types: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="card" id="subject-types-card">
    <div class="card-body p-0" id="subject-types-card-body">
      <div class="table-responsive" id="subject-types-table-wrap">
        <table class="table table-hover mb-0" id="subject-types-table">
          <thead id="subject-types-table-head">
            <tr>
              <th>Type</th>
              <th>Display Name</th>
              <th>Description</th>
              <th class="text-center">Order</th>
              <th class="text-center">System</th>
            </tr>
          </thead>
          <tbody id="subject-types-table-body">
            <?php foreach ($rows as $i => $row): ?>
            <tr id="subject-types-row-<?php echo $i + 1; ?>">
              <td><code><?php echo htmlspecialchars($row['subject_type']); ?></code></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($row['display_name'] ?? ''); ?></td>
              <td class="fs-12"><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
              <td class="text-center"><?php echo (int)$row['sort_order']; ?></td>
              <td class="text-center">
                <?php if (!empty($row['system_defined'])): ?>
                <span class="badge bg-soft-primary text-primary">System</span>
                <?php else: ?>
                <span class="badge bg-soft-secondary text-secondary">Custom</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
