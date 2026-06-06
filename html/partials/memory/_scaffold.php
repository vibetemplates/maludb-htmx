<?php
/**
 * Memory Elements — shared CRUD scaffold (placeholder)
 *
 * Included by each entity partial (projects.php, people.php, ...) after it
 * sets: $memKey (url-safe key), $memTitle (plural label), $memSingular,
 * $memIcon (feather icon class), $memColumns (list column headers).
 *
 * The data layer is intentionally stubbed: every handler below has a
 * clearly marked "MALUDB SQL HERE" block where the real queries go.
 */

if (!isset($memKey, $memTitle, $memSingular, $memIcon, $memColumns)) {
    http_response_code(500);
    echo 'Scaffold configuration missing.';
    exit;
}

$selfUrl = '/partials/memory/' . $memKey . '.php';
$action = $_REQUEST['action'] ?? '';

/* ---------------------------------------------------------------------
 * SAVE (create / update) — POST action=save
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');

    // =====================================================================
    // MALUDB SQL HERE: insert (empty $id) or update (existing $id) the
    // entity ($memSingular) using $_POST fields.
    // =====================================================================
    ?>
    <div class="alert alert-info mb-0" id="<?php echo $memKey; ?>-save-alert">
      <i class="feather-info me-2"></i>
      <strong><?php echo htmlspecialchars($memSingular); ?></strong>
      <?php echo $id === '' ? 'create' : 'update'; ?> received
      (<?php echo htmlspecialchars($name !== '' ? $name : 'unnamed'); ?>) &mdash;
      data layer pending MaluDB SQL integration.
    </div>
    <div class="text-end mt-3" id="<?php echo $memKey; ?>-save-close-wrap">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="<?php echo $memKey; ?>-save-close">Close</button>
    </div>
    <?php
    exit;
}

/* ---------------------------------------------------------------------
 * DELETE — POST action=delete
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = trim($_POST['id'] ?? '');

    // =====================================================================
    // MALUDB SQL HERE: delete the entity ($memSingular) by $id.
    // =====================================================================
    ?>
    <div class="alert alert-warning m-3" id="<?php echo $memKey; ?>-delete-alert">
      <i class="feather-trash-2 me-2"></i>
      <strong><?php echo htmlspecialchars($memSingular); ?></strong> delete received
      (id: <?php echo htmlspecialchars($id !== '' ? $id : 'n/a'); ?>) &mdash;
      data layer pending MaluDB SQL integration.
    </div>
    <?php
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (create / edit modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';

    // =====================================================================
    // MALUDB SQL HERE: when $isEdit, load the entity ($memSingular)
    // record by $id and prefill the fields below.
    // =====================================================================
    ?>
    <div class="modal fade" tabindex="-1" id="<?php echo $memKey; ?>-modal">
      <div class="modal-dialog modal-dialog-centered" id="<?php echo $memKey; ?>-modal-dialog">
        <div class="modal-content" id="<?php echo $memKey; ?>-modal-content">
          <div class="modal-header" id="<?php echo $memKey; ?>-modal-header">
            <h5 class="modal-title" id="<?php echo $memKey; ?>-modal-title">
              <i class="<?php echo $memIcon; ?> me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> <?php echo htmlspecialchars($memSingular); ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#<?php echo $memKey; ?>-modal-body"
                hx-swap="innerHTML"
                id="<?php echo $memKey; ?>-form">
            <div class="modal-body" id="<?php echo $memKey; ?>-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="mb-3" id="<?php echo $memKey; ?>-field-name-wrap">
                <label class="form-label" for="<?php echo $memKey; ?>-field-name">Name</label>
                <input type="text" class="form-control" name="name" id="<?php echo $memKey; ?>-field-name" placeholder="<?php echo htmlspecialchars($memSingular); ?> name" required>
              </div>
              <div class="mb-3" id="<?php echo $memKey; ?>-field-description-wrap">
                <label class="form-label" for="<?php echo $memKey; ?>-field-description">Description</label>
                <textarea class="form-control" name="description" id="<?php echo $memKey; ?>-field-description" rows="3" placeholder="Optional description"></textarea>
              </div>
              <div class="alert alert-light border fs-12 mb-0" id="<?php echo $memKey; ?>-form-note">
                <i class="feather-info me-1"></i>Placeholder form &mdash; fields will be finalized with the MaluDB SQL.
              </div>
            </div>
            <div class="modal-footer" id="<?php echo $memKey; ?>-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="<?php echo $memKey; ?>-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="<?php echo $memKey; ?>-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php
    exit;
}

/* ---------------------------------------------------------------------
 * LIST (default view)
 * ------------------------------------------------------------------- */

// =====================================================================
// MALUDB SQL HERE: load the list of entity rows ($memTitle) into $rows,
// with search/paging as needed.
// =====================================================================
$rows = [];
?>
<div class="container-fluid p-4" id="<?php echo $memKey; ?>-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="<?php echo $memKey; ?>-header">
    <div id="<?php echo $memKey; ?>-header-text">
      <h4 class="fw-bold mb-1" id="<?php echo $memKey; ?>-title"><i class="<?php echo $memIcon; ?> me-2"></i><?php echo htmlspecialchars($memTitle); ?></h4>
      <p class="text-muted mb-0" id="<?php echo $memKey; ?>-subtitle">Memory element &mdash; <?php echo htmlspecialchars($memTitle); ?> registry</p>
    </div>
    <div id="<?php echo $memKey; ?>-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              id="<?php echo $memKey; ?>-btn-new">
        <i class="feather-plus me-1"></i>New <?php echo htmlspecialchars($memSingular); ?>
      </button>
    </div>
  </div>

  <!-- List card -->
  <div class="card" id="<?php echo $memKey; ?>-card">
    <div class="card-header d-flex align-items-center justify-content-between" id="<?php echo $memKey; ?>-card-header">
      <h6 class="fw-bold mb-0" id="<?php echo $memKey; ?>-card-title">All <?php echo htmlspecialchars($memTitle); ?></h6>
      <div class="w-25" id="<?php echo $memKey; ?>-search-wrap">
        <input type="search" class="form-control form-control-sm" placeholder="Search <?php echo htmlspecialchars(strtolower($memTitle)); ?>&hellip;" id="<?php echo $memKey; ?>-search">
      </div>
    </div>
    <div class="card-body p-0" id="<?php echo $memKey; ?>-card-body">
      <div class="table-responsive" id="<?php echo $memKey; ?>-table-wrap">
        <table class="table table-hover mb-0" id="<?php echo $memKey; ?>-table">
          <thead id="<?php echo $memKey; ?>-table-head">
            <tr>
              <?php foreach ($memColumns as $col): ?>
              <th><?php echo htmlspecialchars($col); ?></th>
              <?php endforeach; ?>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="<?php echo $memKey; ?>-table-body">
            <?php if (empty($rows)): ?>
            <tr id="<?php echo $memKey; ?>-row-empty">
              <td colspan="<?php echo count($memColumns) + 1; ?>" class="text-center text-muted py-5">
                <i class="<?php echo $memIcon; ?> fs-3 d-block mb-2"></i>
                No <?php echo htmlspecialchars(strtolower($memTitle)); ?> yet.
                <span class="d-block fs-12 mt-1">CRUD placeholder ready &mdash; awaiting MaluDB SQL integration.</span>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $i => $row): ?>
            <!-- Row template for when MaluDB SQL is wired in -->
            <tr id="<?php echo $memKey; ?>-row-<?php echo $i + 1; ?>">
              <?php foreach ($memColumns as $col): ?>
              <td><?php echo htmlspecialchars($row[strtolower($col)] ?? ''); ?></td>
              <?php endforeach; ?>
              <td class="text-end">
                <button class="btn btn-sm btn-icon"
                        hx-get="<?php echo $selfUrl; ?>?action=form&id=<?php echo htmlspecialchars($row['id'] ?? ''); ?>"
                        hx-target="#modal-container" hx-swap="innerHTML"
                        id="<?php echo $memKey; ?>-row-<?php echo $i + 1; ?>-edit"><i class="feather-edit-2"></i></button>
                <button class="btn btn-sm btn-icon"
                        hx-post="<?php echo $selfUrl; ?>?action=delete"
                        hx-vals='{"id": "<?php echo htmlspecialchars($row['id'] ?? ''); ?>"}'
                        hx-confirm="Delete this <?php echo htmlspecialchars(strtolower($memSingular)); ?>?"
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="<?php echo $memKey; ?>-row-<?php echo $i + 1; ?>-delete"><i class="feather-trash-2"></i></button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
