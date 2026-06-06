<?php
/**
 * MaluDB Setup — Verb Types (read-only)
 *
 * The v1 API exposes maludb_verb_type read-only (/v1/verb-types).
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';

requireAuth();
$pdo = db();

$rows = [];
$loadError = '';
try {
    $rows = $pdo->query(
        "SELECT verb_type, display_name, semantic_class, description, sort_order, system_defined
           FROM maludb_verb_type
          ORDER BY sort_order, verb_type"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $loadError = $e->getMessage();
}
?>
<div class="container-fluid p-4" id="verb-types-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="verb-types-header">
    <div id="verb-types-header-text">
      <h4 class="fw-bold mb-1" id="verb-types-title"><i class="feather-zap me-2"></i>Verb Types</h4>
      <p class="text-muted mb-0" id="verb-types-subtitle">MaluDB setup &mdash; read-only registry of verb semantics</p>
    </div>
    <div id="verb-types-header-actions">
      <span class="badge bg-soft-secondary text-secondary" id="verb-types-readonly-badge"><i class="feather-lock me-1"></i>Read-only</span>
    </div>
  </div>

  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="verb-types-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load verb types: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="card" id="verb-types-card">
    <div class="card-body p-0" id="verb-types-card-body">
      <div class="table-responsive" id="verb-types-table-wrap">
        <table class="table table-hover mb-0" id="verb-types-table">
          <thead id="verb-types-table-head">
            <tr>
              <th>Type</th>
              <th>Display Name</th>
              <th>Semantic Class</th>
              <th>Description</th>
              <th class="text-center">Order</th>
              <th class="text-center">System</th>
            </tr>
          </thead>
          <tbody id="verb-types-table-body">
            <?php foreach ($rows as $i => $row): ?>
            <tr id="verb-types-row-<?php echo $i + 1; ?>">
              <td><code><?php echo htmlspecialchars($row['verb_type']); ?></code></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($row['display_name'] ?? ''); ?></td>
              <td>
                <?php if (!empty($row['semantic_class'])): ?>
                <span class="badge bg-soft-warning text-warning"><?php echo htmlspecialchars($row['semantic_class']); ?></span>
                <?php endif; ?>
              </td>
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
