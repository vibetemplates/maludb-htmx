<?php
/**
 * Memory Elements — Subjects/Things (wired to MaluDB)
 *
 * Adapted from /v1/subjects and /v1/subjects/{id} (requirements.md §4.1).
 * Live-schema mapping: subject_id->id, canonical_name->label, subject_type->type.
 * Verb links live in maludb_subject_verb (keyed by names); relationships in
 * maludb_subject_relationship. Type values come from maludb_subject_type
 * (trigger-enforced).
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/_db.php';

requireAuth();

$pdo = db();
$action = $_REQUEST['action'] ?? '';
$selfUrl = '/partials/memory/subjects.php';

function subjectsAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="subjects-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** Render the subjects list (optionally filtered by $q) with an optional alert. */
function renderSubjectsList(PDO $pdo, string $selfUrl, string $q = '', string $alertHtml = ''): void
{
    $rows = [];
    $loadError = '';
    try {
        $where  = '';
        $params = [];
        if ($q !== '') {
            $where    = "WHERE s.canonical_name ILIKE ? OR s.description ILIKE ?";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $stmt = $pdo->prepare(
            "SELECT s.subject_id     AS id,
                    s.canonical_name AS label,
                    s.subject_type   AS type,
                    s.description,
                    (SELECT count(*) FROM maludb_subject_verb sv
                       WHERE sv.subject_name = s.canonical_name) AS linked_verbs,
                    (SELECT count(*) FROM maludb_subject_relationship r
                       WHERE r.from_subject_id = s.subject_id
                          OR r.to_subject_id   = s.subject_id) AS related_subjects
               FROM maludb_subject s
               $where
              ORDER BY s.canonical_name
              LIMIT 200"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="subjects-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="subjects-header">
    <div id="subjects-header-text">
      <h4 class="fw-bold mb-1" id="subjects-title"><i class="feather-box me-2"></i>Subjects/Things</h4>
      <p class="text-muted mb-0" id="subjects-subtitle">Memory element &mdash; the SVPOR subject registry</p>
    </div>
    <div id="subjects-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              id="subjects-btn-new">
        <i class="feather-plus me-1"></i>New Subject
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="subjects-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load subjects: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <!-- List card -->
  <div class="card" id="subjects-card">
    <div class="card-header d-flex align-items-center justify-content-between" id="subjects-card-header">
      <h6 class="fw-bold mb-0" id="subjects-card-title">All Subjects</h6>
      <div class="w-25" id="subjects-search-wrap">
        <input type="search" class="form-control form-control-sm" name="q"
               value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search subjects&hellip;"
               hx-get="<?php echo $selfUrl; ?>"
               hx-trigger="input changed delay:400ms, search"
               hx-target="#page-content"
               hx-swap="innerHTML"
               id="subjects-search">
      </div>
    </div>
    <div class="card-body p-0" id="subjects-card-body">
      <div class="table-responsive" id="subjects-table-wrap">
        <table class="table table-hover mb-0" id="subjects-table">
          <thead id="subjects-table-head">
            <tr>
              <th>Label</th>
              <th>Type</th>
              <th>Description</th>
              <th class="text-center">Verbs</th>
              <th class="text-center">Related</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="subjects-table-body">
            <?php if (empty($rows)): ?>
            <tr id="subjects-row-empty">
              <td colspan="6" class="text-center text-muted py-5">
                <i class="feather-box fs-3 d-block mb-2"></i>
                <?php echo $q !== '' ? 'No subjects match your search.' : 'No subjects yet. Click New Subject to create one.'; ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row): $rowId = (int)$row['id']; ?>
            <tr id="subjects-row-<?php echo $rowId; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['label'] ?? ''); ?></td>
              <td>
                <?php if (!empty($row['type'])): ?>
                <span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($row['type']); ?></span>
                <?php else: ?>
                <span class="text-muted fs-12">&mdash;</span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars(mb_strimwidth((string)($row['description'] ?? ''), 0, 70, '…')); ?></td>
              <td class="text-center"><?php echo (int)$row['linked_verbs']; ?></td>
              <td class="text-center"><?php echo (int)$row['related_subjects']; ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-icon" title="Details"
                        hx-get="<?php echo $selfUrl; ?>?action=detail&id=<?php echo $rowId; ?>"
                        hx-target="#subjects-detail-<?php echo $rowId; ?>" hx-swap="outerHTML"
                        id="subjects-row-<?php echo $rowId; ?>-detail"><i class="feather-eye"></i></button>
                <button class="btn btn-sm btn-icon" title="Edit"
                        hx-get="<?php echo $selfUrl; ?>?action=form&id=<?php echo $rowId; ?>"
                        hx-target="#modal-container" hx-swap="innerHTML"
                        id="subjects-row-<?php echo $rowId; ?>-edit"><i class="feather-edit-2"></i></button>
                <button class="btn btn-sm btn-icon" title="Delete"
                        hx-post="<?php echo $selfUrl; ?>?action=delete"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-confirm="Delete this subject? Linked verb and relationship records may be affected."
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="subjects-row-<?php echo $rowId; ?>-delete"><i class="feather-trash-2"></i></button>
              </td>
            </tr>
            <tr id="subjects-detail-<?php echo $rowId; ?>" class="d-none"><td colspan="6"></td></tr>
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
    $verbs = [];
    $related = [];
    $error = '';
    try {
        $stmt = $pdo->prepare("SELECT canonical_name FROM maludb_subject WHERE subject_id = ?");
        $stmt->execute([$id]);
        $label = $stmt->fetchColumn();

        // Linked verbs — resolved by name through the compartment table.
        $stmt = $pdo->prepare(
            "SELECT v.verb_id AS id, v.canonical_name, v.verb_type AS type
               FROM maludb_subject_verb sv
               JOIN maludb_verb v ON v.canonical_name = sv.verb_name
              WHERE sv.subject_name = ?
              ORDER BY v.canonical_name"
        );
        $stmt->execute([$label]);
        $verbs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Related subjects — either endpoint of a relationship; show the "other" side.
        $stmt = $pdo->prepare(
            "SELECT from_subject_id, to_subject_id, from_subject_label, to_subject_label,
                    relationship_type, label AS relationship_label
               FROM maludb_subject_relationship
              WHERE from_subject_id = ? OR to_subject_id = ?
              ORDER BY relationship_id"
        );
        $stmt->execute([$id, $id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $outgoing = ((int)$r['from_subject_id'] === $id);
            $related[] = [
                'label'             => $outgoing ? $r['to_subject_label'] : $r['from_subject_label'],
                'relationship_type' => $r['relationship_type'],
                'relationship_label'=> $r['relationship_label'],
                'direction'         => $outgoing ? 'outgoing' : 'incoming',
            ];
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    ?>
    <tr id="subjects-detail-<?php echo $id; ?>" class="bg-light">
      <td colspan="6" id="subjects-detail-<?php echo $id; ?>-cell">
        <?php if ($error !== ''): ?>
        <div class="alert alert-danger mb-0" id="subjects-detail-<?php echo $id; ?>-error">Detail load failed: <?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
        <div class="row g-3 p-2" id="subjects-detail-<?php echo $id; ?>-row">
          <div class="col-md-6" id="subjects-detail-<?php echo $id; ?>-verbs">
            <div class="fw-semibold fs-12 text-muted mb-2">LINKED VERBS (<?php echo count($verbs); ?>)</div>
            <?php if (empty($verbs)): ?>
            <span class="text-muted fs-12">None</span>
            <?php else: foreach ($verbs as $v): ?>
            <span class="badge bg-soft-warning text-warning me-1 mb-1"><?php echo htmlspecialchars($v['canonical_name']); ?><?php echo $v['type'] ? ' · ' . htmlspecialchars($v['type']) : ''; ?></span>
            <?php endforeach; endif; ?>
          </div>
          <div class="col-md-6" id="subjects-detail-<?php echo $id; ?>-related">
            <div class="fw-semibold fs-12 text-muted mb-2">RELATED SUBJECTS (<?php echo count($related); ?>)</div>
            <?php if (empty($related)): ?>
            <span class="text-muted fs-12">None</span>
            <?php else: foreach ($related as $r): ?>
            <div class="fs-12 mb-1">
              <i class="feather-<?php echo $r['direction'] === 'outgoing' ? 'arrow-right' : 'arrow-left'; ?> me-1"></i>
              <span class="fw-semibold"><?php echo htmlspecialchars($r['label'] ?? ''); ?></span>
              <span class="text-muted">(<?php echo htmlspecialchars($r['relationship_label'] ?? $r['relationship_type'] ?? 'related'); ?>)</span>
            </div>
            <?php endforeach; endif; ?>
          </div>
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
    $label        = trim($_POST['label'] ?? '');
    $type         = trim($_POST['type'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $classifierMd = trim($_POST['classifier_md'] ?? '');
    $type         = $type === '' ? null : $type;
    $description  = $description === '' ? null : $description;
    $classifierMd = $classifierMd === '' ? null : $classifierMd;

    if ($label === '') {
        $alert = subjectsAlert('danger', 'Field "label" is required.');
    } else {
        try {
            if ($id === '') {
                // Create — subject_id has no sequence/default; derive inline (/v1/subjects POST).
                $stmt = $pdo->prepare(
                    "INSERT INTO maludb_subject
                         (subject_id, canonical_name, subject_type, description, classifier_md, created_at)
                     SELECT COALESCE(MAX(subject_id), 0) + 1, ?, ?, ?, ?, now()
                       FROM maludb_subject
                     RETURNING subject_id AS id"
                );
                $stmt->execute([$label, $type, $description, $classifierMd]);
                $alert = subjectsAlert('success', 'Subject "' . $label . '" created.');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE maludb_subject
                        SET canonical_name = ?, subject_type = ?, description = ?, classifier_md = ?
                      WHERE subject_id = ?"
                );
                $stmt->execute([$label, $type, $description, $classifierMd, (int)$id]);
                $alert = subjectsAlert('success', 'Subject "' . $label . '" updated.');
            }
        } catch (Exception $e) {
            $alert = subjectsAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    renderSubjectsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * DELETE — POST action=delete  (/v1/subjects/{id} DELETE)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("DELETE FROM maludb_subject WHERE subject_id = ?");
        $stmt->execute([$id]);
        $alert = $stmt->rowCount() > 0
            ? subjectsAlert('success', 'Subject deleted.')
            : subjectsAlert('danger', 'Subject not found.');
    } catch (Exception $e) {
        $alert = subjectsAlert('danger', 'Delete failed: ' . $e->getMessage());
    }

    renderSubjectsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (create / edit modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';
    $subject = ['label' => '', 'type' => '', 'description' => '', 'classifier_md' => ''];
    $typeOptions = maludbTypeOptions($pdo, 'maludb_subject_type', 'subject_type', 'display_name', 'sort_order');

    if ($isEdit) {
        try {
            $stmt = $pdo->prepare(
                "SELECT subject_id AS id, canonical_name AS label, subject_type AS type,
                        description, classifier_md
                   FROM maludb_subject
                  WHERE subject_id = ?"
            );
            $stmt->execute([(int)$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $subject = $found;
            }
        } catch (Exception $e) {
            error_log('Subject form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="subjects-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="subjects-modal-dialog">
        <div class="modal-content" id="subjects-modal-content">
          <div class="modal-header" id="subjects-modal-header">
            <h5 class="modal-title" id="subjects-modal-title">
              <i class="feather-box me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> Subject
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content"
                hx-swap="innerHTML"
                id="subjects-form">
            <div class="modal-body" id="subjects-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="row" id="subjects-form-row-1">
                <div class="col-md-7 mb-3" id="subjects-field-label-wrap">
                  <label class="form-label" for="subjects-field-label">Label <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="label" id="subjects-field-label"
                         value="<?php echo htmlspecialchars($subject['label'] ?? ''); ?>"
                         placeholder="Canonical name" required>
                </div>
                <div class="col-md-5 mb-3" id="subjects-field-type-wrap">
                  <label class="form-label" for="subjects-field-type">Type</label>
                  <?php if (!empty($typeOptions)): ?>
                  <select class="form-select" name="type" id="subjects-field-type">
                    <option value="">&mdash; none &mdash;</option>
                    <?php foreach ($typeOptions as $value => $optLabel): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($subject['type'] ?? '') === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($optLabel); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <?php else: ?>
                  <input type="text" class="form-control" name="type" id="subjects-field-type"
                         value="<?php echo htmlspecialchars($subject['type'] ?? ''); ?>" placeholder="Subject type">
                  <?php endif; ?>
                </div>
              </div>
              <div class="mb-3" id="subjects-field-description-wrap">
                <label class="form-label" for="subjects-field-description">Description</label>
                <textarea class="form-control" name="description" id="subjects-field-description" rows="3"
                          placeholder="Optional description"><?php echo htmlspecialchars($subject['description'] ?? ''); ?></textarea>
              </div>
              <div class="mb-0" id="subjects-field-classifier-wrap">
                <label class="form-label" for="subjects-field-classifier">Classifier (Markdown)</label>
                <textarea class="form-control font-monospace" name="classifier_md" id="subjects-field-classifier" rows="4"
                          placeholder="Optional classifier markdown"><?php echo htmlspecialchars($subject['classifier_md'] ?? ''); ?></textarea>
              </div>
            </div>
            <div class="modal-footer" id="subjects-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="subjects-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="subjects-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
renderSubjectsList($pdo, $selfUrl, $q);
