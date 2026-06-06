<?php
/**
 * Memory Elements — Verbs/Actions (wired to MaluDB)
 *
 * Adapted from /v1/verbs and /v1/verbs/{id} (requirements.md §4.2).
 * Live-schema mapping: verb_id->id, verb_type->type. Subject links live in
 * maludb_subject_verb keyed by verb_name (= canonical_name). Type values come
 * from maludb_verb_type.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/_db.php';

requireAuth();

$pdo = db();
$action = $_REQUEST['action'] ?? '';
$selfUrl = '/partials/memory/verbs.php';

function verbsAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="verbs-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** Render the verbs list (optionally filtered by $q) with an optional alert. */
function renderVerbsList(PDO $pdo, string $selfUrl, string $q = '', string $alertHtml = ''): void
{
    $rows = [];
    $loadError = '';
    try {
        $where  = '';
        $params = [];
        if ($q !== '') {
            $where    = "WHERE v.canonical_name ILIKE ? OR v.description ILIKE ?";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $stmt = $pdo->prepare(
            "SELECT v.verb_id        AS id,
                    v.canonical_name AS canonical_name,
                    v.verb_type      AS type,
                    v.description,
                    (SELECT count(*) FROM maludb_subject_verb sv
                       WHERE sv.verb_name = v.canonical_name) AS linked_subjects
               FROM maludb_verb v
               $where
              ORDER BY v.canonical_name
              LIMIT 200"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="verbs-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="verbs-header">
    <div id="verbs-header-text">
      <h4 class="fw-bold mb-1" id="verbs-title"><i class="feather-zap me-2"></i>Verbs/Actions</h4>
      <p class="text-muted mb-0" id="verbs-subtitle">Memory element &mdash; the SVPOR verb registry</p>
    </div>
    <div id="verbs-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              id="verbs-btn-new">
        <i class="feather-plus me-1"></i>New Verb
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="verbs-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load verbs: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <!-- List card -->
  <div class="card" id="verbs-card">
    <div class="card-header d-flex align-items-center justify-content-between" id="verbs-card-header">
      <h6 class="fw-bold mb-0" id="verbs-card-title">All Verbs</h6>
      <div class="w-25" id="verbs-search-wrap">
        <input type="search" class="form-control form-control-sm" name="q"
               value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search verbs&hellip;"
               hx-get="<?php echo $selfUrl; ?>"
               hx-trigger="input changed delay:400ms, search"
               hx-target="#page-content"
               hx-swap="innerHTML"
               id="verbs-search">
      </div>
    </div>
    <div class="card-body p-0" id="verbs-card-body">
      <div class="table-responsive" id="verbs-table-wrap">
        <table class="table table-hover mb-0" id="verbs-table">
          <thead id="verbs-table-head">
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Description</th>
              <th class="text-center">Subjects</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="verbs-table-body">
            <?php if (empty($rows)): ?>
            <tr id="verbs-row-empty">
              <td colspan="5" class="text-center text-muted py-5">
                <i class="feather-zap fs-3 d-block mb-2"></i>
                <?php echo $q !== '' ? 'No verbs match your search.' : 'No verbs yet. Click New Verb to create one.'; ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row): $rowId = (int)$row['id']; ?>
            <tr id="verbs-row-<?php echo $rowId; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['canonical_name'] ?? ''); ?></td>
              <td>
                <?php if (!empty($row['type'])): ?>
                <span class="badge bg-soft-warning text-warning"><?php echo htmlspecialchars($row['type']); ?></span>
                <?php else: ?>
                <span class="text-muted fs-12">&mdash;</span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars(mb_strimwidth((string)($row['description'] ?? ''), 0, 70, '…')); ?></td>
              <td class="text-center"><?php echo (int)$row['linked_subjects']; ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-icon" title="Details"
                        hx-get="<?php echo $selfUrl; ?>?action=detail&id=<?php echo $rowId; ?>"
                        hx-target="#verbs-detail-<?php echo $rowId; ?>" hx-swap="outerHTML"
                        id="verbs-row-<?php echo $rowId; ?>-detail"><i class="feather-eye"></i></button>
                <button class="btn btn-sm btn-icon" title="Edit"
                        hx-get="<?php echo $selfUrl; ?>?action=form&id=<?php echo $rowId; ?>"
                        hx-target="#modal-container" hx-swap="innerHTML"
                        id="verbs-row-<?php echo $rowId; ?>-edit"><i class="feather-edit-2"></i></button>
                <button class="btn btn-sm btn-icon" title="Delete"
                        hx-post="<?php echo $selfUrl; ?>?action=delete"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-confirm="Delete this verb?"
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="verbs-row-<?php echo $rowId; ?>-delete"><i class="feather-trash-2"></i></button>
              </td>
            </tr>
            <tr id="verbs-detail-<?php echo $rowId; ?>" class="d-none"><td colspan="5"></td></tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
    <?php
}

/* ---------------------------------------------------------------------
 * DETAIL (expandable row) — GET action=detail
 * ------------------------------------------------------------------- */
if ($action === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    $subjects = [];
    $error = '';
    try {
        $stmt = $pdo->prepare("SELECT canonical_name FROM maludb_verb WHERE verb_id = ?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();

        // Linked subjects — resolved by name through the compartment table.
        $stmt = $pdo->prepare(
            "SELECT s.subject_id AS id, s.canonical_name AS label, s.subject_type AS type
               FROM maludb_subject_verb sv
               JOIN maludb_subject s ON s.canonical_name = sv.subject_name
              WHERE sv.verb_name = ?
              ORDER BY s.canonical_name"
        );
        $stmt->execute([$name]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    ?>
    <tr id="verbs-detail-<?php echo $id; ?>" class="bg-light">
      <td colspan="5" id="verbs-detail-<?php echo $id; ?>-cell">
        <?php if ($error !== ''): ?>
        <div class="alert alert-danger mb-0" id="verbs-detail-<?php echo $id; ?>-error">Detail load failed: <?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
        <div class="p-2" id="verbs-detail-<?php echo $id; ?>-inner">
          <div class="fw-semibold fs-12 text-muted mb-2">LINKED SUBJECTS (<?php echo count($subjects); ?>)</div>
          <?php if (empty($subjects)): ?>
          <span class="text-muted fs-12">None</span>
          <?php else: foreach ($subjects as $s): ?>
          <span class="badge bg-soft-primary text-primary me-1 mb-1"><?php echo htmlspecialchars($s['label']); ?><?php echo $s['type'] ? ' · ' . htmlspecialchars($s['type']) : ''; ?></span>
          <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php
    exit;
}

/* ---------------------------------------------------------------------
 * SAVE (create / update) — POST action=save
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $id           = trim($_POST['id'] ?? '');
    $name         = trim($_POST['canonical_name'] ?? '');
    $type         = trim($_POST['type'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $classifierMd = trim($_POST['classifier_md'] ?? '');
    $type         = $type === '' ? null : $type;
    $description  = $description === '' ? null : $description;
    $classifierMd = $classifierMd === '' ? null : $classifierMd;

    if ($name === '') {
        $alert = verbsAlert('danger', 'Field "canonical_name" is required.');
    } else {
        try {
            if ($id === '') {
                // Create — verb_id has no sequence/default; derive inline (/v1/verbs POST).
                $stmt = $pdo->prepare(
                    "INSERT INTO maludb_verb
                         (verb_id, canonical_name, verb_type, description, classifier_md, created_at)
                     SELECT COALESCE(MAX(verb_id), 0) + 1, ?, ?, ?, ?, now()
                       FROM maludb_verb
                     RETURNING verb_id AS id"
                );
                $stmt->execute([$name, $type, $description, $classifierMd]);
                $alert = verbsAlert('success', 'Verb "' . $name . '" created.');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE maludb_verb
                        SET canonical_name = ?, verb_type = ?, description = ?, classifier_md = ?
                      WHERE verb_id = ?"
                );
                $stmt->execute([$name, $type, $description, $classifierMd, (int)$id]);
                $alert = verbsAlert('success', 'Verb "' . $name . '" updated.');
            }
        } catch (Exception $e) {
            $alert = verbsAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    renderVerbsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * DELETE — POST action=delete  (/v1/verbs/{id} DELETE)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM maludb_verb WHERE verb_id = ?");
        $stmt->execute([$id]);
        $alert = $stmt->rowCount() > 0
            ? verbsAlert('success', 'Verb deleted.')
            : verbsAlert('danger', 'Verb not found.');
    } catch (Exception $e) {
        $alert = verbsAlert('danger', 'Delete failed: ' . $e->getMessage());
    }

    renderVerbsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (create / edit modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';
    $verb = ['canonical_name' => '', 'type' => '', 'description' => '', 'classifier_md' => ''];
    $typeOptions = maludbTypeOptions($pdo, 'maludb_verb_type', 'verb_type', 'display_name', 'sort_order');

    if ($isEdit) {
        try {
            $stmt = $pdo->prepare(
                "SELECT verb_id AS id, canonical_name, verb_type AS type, description, classifier_md
                   FROM maludb_verb
                  WHERE verb_id = ?"
            );
            $stmt->execute([(int)$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $verb = $found;
            }
        } catch (Exception $e) {
            error_log('Verb form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="verbs-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="verbs-modal-dialog">
        <div class="modal-content" id="verbs-modal-content">
          <div class="modal-header" id="verbs-modal-header">
            <h5 class="modal-title" id="verbs-modal-title">
              <i class="feather-zap me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> Verb
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content"
                hx-swap="innerHTML"
                id="verbs-form">
            <div class="modal-body" id="verbs-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="row" id="verbs-form-row-1">
                <div class="col-md-7 mb-3" id="verbs-field-name-wrap">
                  <label class="form-label" for="verbs-field-name">Canonical Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="canonical_name" id="verbs-field-name"
                         value="<?php echo htmlspecialchars($verb['canonical_name'] ?? ''); ?>"
                         placeholder="e.g. approves, manages, depends_on" required>
                </div>
                <div class="col-md-5 mb-3" id="verbs-field-type-wrap">
                  <label class="form-label" for="verbs-field-type">Type</label>
                  <?php if (!empty($typeOptions)): ?>
                  <select class="form-select" name="type" id="verbs-field-type">
                    <option value="">&mdash; none &mdash;</option>
                    <?php foreach ($typeOptions as $value => $optLabel): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($verb['type'] ?? '') === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($optLabel); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <?php else: ?>
                  <input type="text" class="form-control" name="type" id="verbs-field-type"
                         value="<?php echo htmlspecialchars($verb['type'] ?? ''); ?>" placeholder="Verb type">
                  <?php endif; ?>
                </div>
              </div>
              <div class="mb-3" id="verbs-field-description-wrap">
                <label class="form-label" for="verbs-field-description">Description</label>
                <textarea class="form-control" name="description" id="verbs-field-description" rows="3"
                          placeholder="Optional description"><?php echo htmlspecialchars($verb['description'] ?? ''); ?></textarea>
              </div>
              <div class="mb-0" id="verbs-field-classifier-wrap">
                <label class="form-label" for="verbs-field-classifier">Classifier (Markdown)</label>
                <textarea class="form-control font-monospace" name="classifier_md" id="verbs-field-classifier" rows="4"
                          placeholder="Optional classifier markdown"><?php echo htmlspecialchars($verb['classifier_md'] ?? ''); ?></textarea>
              </div>
            </div>
            <div class="modal-footer" id="verbs-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="verbs-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="verbs-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
$q = trim($_GET['q'] ?? '');
renderVerbsList($pdo, $selfUrl, $q);
