<?php
/**
 * MaluDB Setup — shared type-table CRUD scaffold
 *
 * Included by episode-types.php / document-types.php after setting:
 *   $stKey (url-safe key), $stTitle, $stSingular, $stIcon,
 *   $stTable (view name), $stIdCol, $stLabelCol.
 *
 * Pattern from /v1/episode-types: label is case-insensitive unique (23505 →
 * friendly duplicate message); deleting a type does NOT affect rows already
 * tagged with that string (advisory picker lists, no FK).
 */

if (!isset($stKey, $stTitle, $stSingular, $stIcon, $stTable, $stIdCol, $stLabelCol)) {
    http_response_code(500);
    echo 'Type CRUD configuration missing.';
    exit;
}

$selfUrl = '/partials/memory/setup/' . $stKey . '.php';
$action = $_REQUEST['action'] ?? '';

$stAlert = function (string $type, string $message) use ($stKey): string {
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="' . $stKey . '-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
};

$renderList = function (PDO $pdo, string $alertHtml = '') use ($stKey, $stTitle, $stSingular, $stIcon, $stTable, $stIdCol, $stLabelCol, $selfUrl): void {
    $rows = [];
    $loadError = '';
    try {
        $rows = $pdo->query(
            "SELECT $stIdCol AS id, $stLabelCol AS label, description, display_order, created_at
               FROM $stTable
              ORDER BY display_order NULLS LAST, $stLabelCol"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="<?php echo $stKey; ?>-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="<?php echo $stKey; ?>-header">
    <div id="<?php echo $stKey; ?>-header-text">
      <h4 class="fw-bold mb-1" id="<?php echo $stKey; ?>-title"><i class="<?php echo $stIcon; ?> me-2"></i><?php echo htmlspecialchars($stTitle); ?></h4>
      <p class="text-muted mb-0" id="<?php echo $stKey; ?>-subtitle">MaluDB setup &mdash; advisory picker list (no FK; deleting does not affect tagged rows)</p>
    </div>
    <div id="<?php echo $stKey; ?>-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container" hx-swap="innerHTML"
              id="<?php echo $stKey; ?>-btn-new">
        <i class="feather-plus me-1"></i>New <?php echo htmlspecialchars($stSingular); ?>
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="<?php echo $stKey; ?>-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load <?php echo htmlspecialchars(strtolower($stTitle)); ?>: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="card" id="<?php echo $stKey; ?>-card">
    <div class="card-body p-0" id="<?php echo $stKey; ?>-card-body">
      <div class="table-responsive" id="<?php echo $stKey; ?>-table-wrap">
        <table class="table table-hover mb-0" id="<?php echo $stKey; ?>-table">
          <thead id="<?php echo $stKey; ?>-table-head">
            <tr>
              <th>Type</th>
              <th>Description</th>
              <th class="text-center">Order</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="<?php echo $stKey; ?>-table-body">
            <?php if (empty($rows)): ?>
            <tr id="<?php echo $stKey; ?>-row-empty">
              <td colspan="4" class="text-center text-muted py-5">
                <i class="<?php echo $stIcon; ?> fs-3 d-block mb-2"></i>No types defined yet.
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row): $rowId = (int)$row['id']; ?>
            <tr id="<?php echo $stKey; ?>-row-<?php echo $rowId; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['label'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars(mb_strimwidth((string)($row['description'] ?? ''), 0, 90, '…')); ?></td>
              <td class="text-center"><?php echo $row['display_order'] === null ? '—' : (int)$row['display_order']; ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1" id="<?php echo $stKey; ?>-row-<?php echo $rowId; ?>-actions">
                  <button class="btn btn-sm btn-icon" title="Edit"
                          hx-get="<?php echo $selfUrl; ?>?action=form&id=<?php echo $rowId; ?>"
                          hx-target="#modal-container" hx-swap="innerHTML"
                          id="<?php echo $stKey; ?>-row-<?php echo $rowId; ?>-edit"><i class="feather-edit-2"></i></button>
                  <button class="btn btn-sm btn-icon" title="Delete"
                          hx-post="<?php echo $selfUrl; ?>?action=delete"
                          hx-vals='{"id": "<?php echo $rowId; ?>"}'
                          hx-confirm="Delete this type from the picker list?"
                          hx-target="#page-content" hx-swap="innerHTML"
                          id="<?php echo $stKey; ?>-row-<?php echo $rowId; ?>-delete"><i class="feather-trash-2"></i></button>
                </div>
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
    <?php
};

/* SAVE — POST action=save */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $id           = trim($_POST['id'] ?? '');
    $label        = trim($_POST['label'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $description  = $description === '' ? null : $description;
    $displayOrder = trim($_POST['display_order'] ?? '');
    $displayOrder = $displayOrder === '' ? null : (int)$displayOrder;

    if ($label === '') {
        $alert = $stAlert('danger', 'The type label is required.');
    } else {
        try {
            if ($id === '') {
                $stmt = $pdo->prepare(
                    "INSERT INTO $stTable ($stLabelCol, description, display_order) VALUES (?, ?, ?)"
                );
                $stmt->execute([$label, $description, $displayOrder]);
                $alert = $stAlert('success', $stSingular . ' "' . $label . '" created.');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE $stTable SET $stLabelCol = ?, description = ?, display_order = ? WHERE $stIdCol = ?"
                );
                $stmt->execute([$label, $description, $displayOrder, (int)$id]);
                $alert = $stAlert('success', $stSingular . ' "' . $label . '" updated.');
            }
        } catch (Exception $e) {
            $alert = (strpos($e->getMessage(), '23505') !== false || stripos($e->getMessage(), 'duplicate') !== false)
                ? $stAlert('danger', 'A type named "' . $label . '" already exists (labels are case-insensitive unique).')
                : $stAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    $renderList($pdo, $alert);
    exit;
}

/* DELETE — POST action=delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM $stTable WHERE $stIdCol = ?");
        $stmt->execute([$id]);
        $alert = $stmt->rowCount() > 0
            ? $stAlert('success', $stSingular . ' deleted.')
            : $stAlert('danger', $stSingular . ' not found.');
    } catch (Exception $e) {
        $alert = $stAlert('danger', 'Delete failed: ' . $e->getMessage());
    }
    $renderList($pdo, $alert);
    exit;
}

/* FORM — GET action=form */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';
    $row = ['label' => '', 'description' => '', 'display_order' => ''];
    if ($isEdit) {
        try {
            $stmt = $pdo->prepare(
                "SELECT $stLabelCol AS label, description, display_order FROM $stTable WHERE $stIdCol = ?"
            );
            $stmt->execute([(int)$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $row = $found;
            }
        } catch (Exception $e) {
            error_log($stSingular . ' form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="<?php echo $stKey; ?>-modal">
      <div class="modal-dialog modal-dialog-centered" id="<?php echo $stKey; ?>-modal-dialog">
        <div class="modal-content" id="<?php echo $stKey; ?>-modal-content">
          <div class="modal-header" id="<?php echo $stKey; ?>-modal-header">
            <h5 class="modal-title" id="<?php echo $stKey; ?>-modal-title">
              <i class="<?php echo $stIcon; ?> me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> <?php echo htmlspecialchars($stSingular); ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content" hx-swap="innerHTML"
                id="<?php echo $stKey; ?>-form">
            <div class="modal-body" id="<?php echo $stKey; ?>-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="mb-3" id="<?php echo $stKey; ?>-field-label-wrap">
                <label class="form-label" for="<?php echo $stKey; ?>-field-label">Type Label <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="label" id="<?php echo $stKey; ?>-field-label"
                       value="<?php echo htmlspecialchars($row['label'] ?? ''); ?>" required>
              </div>
              <div class="mb-3" id="<?php echo $stKey; ?>-field-description-wrap">
                <label class="form-label" for="<?php echo $stKey; ?>-field-description">Description</label>
                <textarea class="form-control" name="description" id="<?php echo $stKey; ?>-field-description" rows="2"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
              </div>
              <div class="mb-0" id="<?php echo $stKey; ?>-field-order-wrap">
                <label class="form-label" for="<?php echo $stKey; ?>-field-order">Display Order</label>
                <input type="number" class="form-control" name="display_order" id="<?php echo $stKey; ?>-field-order"
                       value="<?php echo $row['display_order'] === null ? '' : htmlspecialchars((string)$row['display_order']); ?>">
              </div>
            </div>
            <div class="modal-footer" id="<?php echo $stKey; ?>-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="<?php echo $stKey; ?>-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="<?php echo $stKey; ?>-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php
    exit;
}

/* LIST (default) */
$renderList($pdo);
