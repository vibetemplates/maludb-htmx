<?php
/**
 * MaluDB Setup — shared registry-table CRUD scaffold
 *
 * Like _type-crud.php, but for the text-keyed registries
 * (maludb_subject_type / maludb_verb_type). Included after setting:
 *   $rgKey (url-safe key), $rgTitle, $rgSingular, $rgIcon,
 *   $rgTable, $rgTypeCol (text primary key column),
 *   $rgHasSemanticClass (verb types only).
 *
 * Rows are identified by the text key itself (passed as "key").
 * New rows are created with system_defined = false.
 */

if (!isset($rgKey, $rgTitle, $rgSingular, $rgIcon, $rgTable, $rgTypeCol, $rgHasSemanticClass)) {
    http_response_code(500);
    echo 'Registry CRUD configuration missing.';
    exit;
}

$selfUrl = '/partials/memory/setup/' . $rgKey . '.php';
$action = $_REQUEST['action'] ?? '';

$rgAlert = function (string $type, string $message) use ($rgKey): string {
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="' . $rgKey . '-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
};

$renderList = function (PDO $pdo, string $alertHtml = '') use ($rgKey, $rgTitle, $rgSingular, $rgIcon, $rgTable, $rgTypeCol, $rgHasSemanticClass, $selfUrl): void {
    $rows = [];
    $loadError = '';
    $semanticCol = $rgHasSemanticClass ? ', semantic_class' : '';
    try {
        $rows = $pdo->query(
            "SELECT $rgTypeCol AS type, display_name{$semanticCol}, description, sort_order, system_defined
               FROM $rgTable
              ORDER BY sort_order, $rgTypeCol"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="<?php echo $rgKey; ?>-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="<?php echo $rgKey; ?>-header">
    <div id="<?php echo $rgKey; ?>-header-text">
      <h4 class="fw-bold mb-1" id="<?php echo $rgKey; ?>-title"><i class="<?php echo $rgIcon; ?> me-2"></i><?php echo htmlspecialchars($rgTitle); ?></h4>
      <p class="text-muted mb-0" id="<?php echo $rgKey; ?>-subtitle">MaluDB setup &mdash; registry of <?php echo htmlspecialchars(strtolower($rgTitle)); ?></p>
    </div>
    <div id="<?php echo $rgKey; ?>-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container" hx-swap="innerHTML"
              id="<?php echo $rgKey; ?>-btn-new">
        <i class="feather-plus me-1"></i>New <?php echo htmlspecialchars($rgSingular); ?>
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="<?php echo $rgKey; ?>-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load <?php echo htmlspecialchars(strtolower($rgTitle)); ?>: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="card" id="<?php echo $rgKey; ?>-card">
    <div class="card-body p-0" id="<?php echo $rgKey; ?>-card-body">
      <div class="table-responsive" id="<?php echo $rgKey; ?>-table-wrap">
        <table class="table table-hover mb-0" id="<?php echo $rgKey; ?>-table">
          <thead id="<?php echo $rgKey; ?>-table-head">
            <tr>
              <th>Type</th>
              <th>Display Name</th>
              <?php if ($rgHasSemanticClass): ?><th>Semantic Class</th><?php endif; ?>
              <th>Description</th>
              <th class="text-center">Order</th>
              <th class="text-center">System</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="<?php echo $rgKey; ?>-table-body">
            <?php if (empty($rows)): ?>
            <tr id="<?php echo $rgKey; ?>-row-empty">
              <td colspan="<?php echo $rgHasSemanticClass ? 7 : 6; ?>" class="text-center text-muted py-5">
                <i class="<?php echo $rgIcon; ?> fs-3 d-block mb-2"></i>No types defined yet.
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $i => $row): $rowKey = (string)$row['type']; ?>
            <tr id="<?php echo $rgKey; ?>-row-<?php echo $i + 1; ?>">
              <td><code><?php echo htmlspecialchars($rowKey); ?></code></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($row['display_name'] ?? ''); ?></td>
              <?php if ($rgHasSemanticClass): ?>
              <td>
                <?php if (!empty($row['semantic_class'])): ?>
                <span class="badge bg-soft-warning text-warning"><?php echo htmlspecialchars($row['semantic_class']); ?></span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
              <td class="fs-12"><?php echo htmlspecialchars(mb_strimwidth((string)($row['description'] ?? ''), 0, 90, '…')); ?></td>
              <td class="text-center"><?php echo $row['sort_order'] === null ? '—' : (int)$row['sort_order']; ?></td>
              <td class="text-center">
                <?php if (!empty($row['system_defined'])): ?>
                <span class="badge bg-soft-primary text-primary">System</span>
                <?php else: ?>
                <span class="badge bg-soft-secondary text-secondary">Custom</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-1" id="<?php echo $rgKey; ?>-row-<?php echo $i + 1; ?>-actions">
                  <button class="btn btn-sm btn-icon" title="Edit"
                          hx-get="<?php echo $selfUrl; ?>?action=form&key=<?php echo urlencode($rowKey); ?>"
                          hx-target="#modal-container" hx-swap="innerHTML"
                          id="<?php echo $rgKey; ?>-row-<?php echo $i + 1; ?>-edit"><i class="feather-edit-2"></i></button>
                  <button class="btn btn-sm btn-icon" title="Delete"
                          hx-post="<?php echo $selfUrl; ?>?action=delete"
                          hx-vals='<?php echo htmlspecialchars(json_encode(['key' => $rowKey]), ENT_QUOTES); ?>'
                          hx-confirm="Delete this type? Rows already tagged with it are not affected."
                          hx-target="#page-content" hx-swap="innerHTML"
                          id="<?php echo $rgKey; ?>-row-<?php echo $i + 1; ?>-delete"><i class="feather-trash-2"></i></button>
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
    $origKey       = trim($_POST['key'] ?? '');
    $type          = trim($_POST['type'] ?? '');
    $displayName   = trim($_POST['display_name'] ?? '');
    $displayName   = $displayName === '' ? null : $displayName;
    $semanticClass = trim($_POST['semantic_class'] ?? '');
    $semanticClass = $semanticClass === '' ? null : $semanticClass;
    $description   = trim($_POST['description'] ?? '');
    $description   = $description === '' ? null : $description;
    $sortOrder     = trim($_POST['sort_order'] ?? '');
    $sortOrder     = $sortOrder === '' ? null : (int)$sortOrder;

    if ($type === '') {
        $alert = $rgAlert('danger', 'The type value is required.');
    } else {
        try {
            // Duplicate guard (no unique constraint on these registries)
            $dupStmt = $pdo->prepare(
                "SELECT 1 FROM $rgTable WHERE lower($rgTypeCol) = lower(?) AND $rgTypeCol <> ?"
            );
            $dupStmt->execute([$type, $origKey]);
            if ($dupStmt->fetch()) {
                $alert = $rgAlert('danger', 'A type named "' . $type . '" already exists.');
            } elseif ($origKey === '') {
                if ($rgHasSemanticClass) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO $rgTable ($rgTypeCol, display_name, semantic_class, description, sort_order, system_defined, created_at)
                         VALUES (?, ?, ?, ?, ?, false, now())"
                    );
                    $stmt->execute([$type, $displayName, $semanticClass, $description, $sortOrder]);
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO $rgTable ($rgTypeCol, display_name, description, sort_order, system_defined, created_at)
                         VALUES (?, ?, ?, ?, false, now())"
                    );
                    $stmt->execute([$type, $displayName, $description, $sortOrder]);
                }
                $alert = $rgAlert('success', $rgSingular . ' "' . $type . '" created.');
            } else {
                if ($rgHasSemanticClass) {
                    $stmt = $pdo->prepare(
                        "UPDATE $rgTable SET $rgTypeCol = ?, display_name = ?, semantic_class = ?, description = ?, sort_order = ?
                          WHERE $rgTypeCol = ?"
                    );
                    $stmt->execute([$type, $displayName, $semanticClass, $description, $sortOrder, $origKey]);
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE $rgTable SET $rgTypeCol = ?, display_name = ?, description = ?, sort_order = ?
                          WHERE $rgTypeCol = ?"
                    );
                    $stmt->execute([$type, $displayName, $description, $sortOrder, $origKey]);
                }
                $alert = $rgAlert('success', $rgSingular . ' "' . $type . '" updated.');
            }
        } catch (Exception $e) {
            $alert = $rgAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    $renderList($pdo, $alert);
    exit;
}

/* DELETE — POST action=delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $key = trim($_POST['key'] ?? '');
    try {
        $stmt = $pdo->prepare("DELETE FROM $rgTable WHERE $rgTypeCol = ?");
        $stmt->execute([$key]);
        $alert = $stmt->rowCount() > 0
            ? $rgAlert('success', $rgSingular . ' deleted.')
            : $rgAlert('danger', $rgSingular . ' not found.');
    } catch (Exception $e) {
        $alert = $rgAlert('danger', 'Delete failed: ' . $e->getMessage());
    }
    $renderList($pdo, $alert);
    exit;
}

/* FORM — GET action=form */
if ($action === 'form') {
    $key = trim($_GET['key'] ?? '');
    $isEdit = $key !== '';
    $row = ['type' => '', 'display_name' => '', 'semantic_class' => '', 'description' => '', 'sort_order' => null];
    if ($isEdit) {
        try {
            $semanticCol = $rgHasSemanticClass ? ', semantic_class' : '';
            $stmt = $pdo->prepare(
                "SELECT $rgTypeCol AS type, display_name{$semanticCol}, description, sort_order
                   FROM $rgTable WHERE $rgTypeCol = ?"
            );
            $stmt->execute([$key]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $row = array_merge($row, $found);
            }
        } catch (Exception $e) {
            error_log($rgSingular . ' form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="<?php echo $rgKey; ?>-modal">
      <div class="modal-dialog modal-dialog-centered" id="<?php echo $rgKey; ?>-modal-dialog">
        <div class="modal-content" id="<?php echo $rgKey; ?>-modal-content">
          <div class="modal-header" id="<?php echo $rgKey; ?>-modal-header">
            <h5 class="modal-title" id="<?php echo $rgKey; ?>-modal-title">
              <i class="<?php echo $rgIcon; ?> me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> <?php echo htmlspecialchars($rgSingular); ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content" hx-swap="innerHTML"
                id="<?php echo $rgKey; ?>-form">
            <div class="modal-body" id="<?php echo $rgKey; ?>-modal-body">
              <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
              <div class="mb-3" id="<?php echo $rgKey; ?>-field-type-wrap">
                <label class="form-label" for="<?php echo $rgKey; ?>-field-type">Type <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="type" id="<?php echo $rgKey; ?>-field-type"
                       value="<?php echo htmlspecialchars($row['type'] ?? ''); ?>" required>
              </div>
              <div class="mb-3" id="<?php echo $rgKey; ?>-field-display-name-wrap">
                <label class="form-label" for="<?php echo $rgKey; ?>-field-display-name">Display Name</label>
                <input type="text" class="form-control" name="display_name" id="<?php echo $rgKey; ?>-field-display-name"
                       value="<?php echo htmlspecialchars($row['display_name'] ?? ''); ?>">
              </div>
              <?php if ($rgHasSemanticClass): ?>
              <div class="mb-3" id="<?php echo $rgKey; ?>-field-semantic-class-wrap">
                <label class="form-label" for="<?php echo $rgKey; ?>-field-semantic-class">Semantic Class</label>
                <input type="text" class="form-control" name="semantic_class" id="<?php echo $rgKey; ?>-field-semantic-class"
                       value="<?php echo htmlspecialchars($row['semantic_class'] ?? ''); ?>">
              </div>
              <?php endif; ?>
              <div class="mb-3" id="<?php echo $rgKey; ?>-field-description-wrap">
                <label class="form-label" for="<?php echo $rgKey; ?>-field-description">Description</label>
                <textarea class="form-control" name="description" id="<?php echo $rgKey; ?>-field-description" rows="2"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
              </div>
              <div class="mb-0" id="<?php echo $rgKey; ?>-field-order-wrap">
                <label class="form-label" for="<?php echo $rgKey; ?>-field-order">Sort Order</label>
                <input type="number" class="form-control" name="sort_order" id="<?php echo $rgKey; ?>-field-order"
                       value="<?php echo $row['sort_order'] === null ? '' : htmlspecialchars((string)$row['sort_order']); ?>">
              </div>
            </div>
            <div class="modal-footer" id="<?php echo $rgKey; ?>-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="<?php echo $rgKey; ?>-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="<?php echo $rgKey; ?>-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
