<?php
/**
 * Memory Elements — People (wired to MaluDB)
 *
 * A "person" is a subject with subject_type='person' (maludb_person is a view
 * of maludb_subject WHERE subject_type='person'). person id = subject_id.
 * Same model as Projects (projects.php ← /v1/projects).
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$action = $_REQUEST['action'] ?? '';
$selfUrl = '/partials/memory/people.php';

/** Small alert fragment used above the list after actions. */
function peopleAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="people-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** Render the people list (optionally filtered by $q) with an optional alert. */
function renderPeopleList(PDO $pdo, string $selfUrl, string $q = '', string $alertHtml = ''): void
{
    $rows = [];
    $loadError = '';
    try {
        $where  = '';
        $params = [];
        if ($q !== '') {
            $where    = "WHERE canonical_name ILIKE ? OR description ILIKE ?";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $stmt = $pdo->prepare(
            "SELECT subject_id     AS id,
                    canonical_name AS name,
                    description,
                    classifier_md,
                    archived_at
               FROM maludb_person
               $where
              ORDER BY canonical_name
              LIMIT 200"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="people-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="people-header">
    <div id="people-header-text">
      <h4 class="fw-bold mb-1" id="people-title"><i class="feather-users me-2"></i>People</h4>
      <p class="text-muted mb-0" id="people-subtitle">Memory element &mdash; subjects with subject_type='person'</p>
    </div>
    <div id="people-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              id="people-btn-new">
        <i class="feather-plus me-1"></i>New Person
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="people-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load people: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <!-- List card -->
  <div class="card" id="people-card">
    <div class="card-header d-flex align-items-center justify-content-between" id="people-card-header">
      <h6 class="fw-bold mb-0" id="people-card-title">All People</h6>
      <div class="w-25" id="people-search-wrap">
        <input type="search" class="form-control form-control-sm" name="q"
               value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search people&hellip;"
               hx-get="<?php echo $selfUrl; ?>"
               hx-trigger="input changed delay:400ms, search"
               hx-target="#page-content"
               hx-swap="innerHTML"
               id="people-search">
      </div>
    </div>
    <div class="card-body p-0" id="people-card-body">
      <div class="table-responsive" id="people-table-wrap">
        <table class="table table-hover mb-0" id="people-table">
          <thead id="people-table-head">
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="people-table-body">
            <?php if (empty($rows)): ?>
            <tr id="people-row-empty">
              <td colspan="4" class="text-center text-muted py-5">
                <i class="feather-users fs-3 d-block mb-2"></i>
                <?php echo $q !== '' ? 'No people match your search.' : 'No people yet. Click New Person to create one.'; ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row):
                $rowId = (int)$row['id'];
                $isArchived = !empty($row['archived_at']);
            ?>
            <tr id="people-row-<?php echo $rowId; ?>" class="<?php echo $isArchived ? 'text-muted' : ''; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars(mb_strimwidth((string)($row['description'] ?? ''), 0, 90, '…')); ?></td>
              <td>
                <?php if ($isArchived): ?>
                <span class="badge bg-soft-secondary text-secondary">Archived</span>
                <?php else: ?>
                <span class="badge bg-soft-success text-success">Active</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-icon" title="Edit"
                        hx-get="<?php echo $selfUrl; ?>?action=form&id=<?php echo $rowId; ?>"
                        hx-target="#modal-container" hx-swap="innerHTML"
                        id="people-row-<?php echo $rowId; ?>-edit"><i class="feather-edit-2"></i></button>
                <?php if ($isArchived): ?>
                <button class="btn btn-sm btn-icon" title="Restore"
                        hx-post="<?php echo $selfUrl; ?>?action=restore"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="people-row-<?php echo $rowId; ?>-restore"><i class="feather-rotate-ccw"></i></button>
                <?php else: ?>
                <button class="btn btn-sm btn-icon" title="Archive"
                        hx-post="<?php echo $selfUrl; ?>?action=archive"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-confirm="Archive this person?"
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="people-row-<?php echo $rowId; ?>-archive"><i class="feather-archive"></i></button>
                <?php endif; ?>
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
}

/* ---------------------------------------------------------------------
 * SAVE (create / update) — POST action=save
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $id           = trim($_POST['id'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $classifierMd = trim($_POST['classifier_md'] ?? '');
    $description  = $description === '' ? null : $description;
    $classifierMd = $classifierMd === '' ? null : $classifierMd;

    if ($name === '') {
        $alert = peopleAlert('danger', 'Field "name" is required.');
    } else {
        try {
            if ($id === '') {
                // Create — a person is a subject of type 'person'; subject_id has no sequence.
                $stmt = $pdo->prepare(
                    "INSERT INTO maludb_subject
                         (subject_id, canonical_name, subject_type, description, classifier_md, created_at)
                     SELECT COALESCE(MAX(subject_id), 0) + 1, ?, 'person', ?, ?, now()
                       FROM maludb_subject
                     RETURNING subject_id AS id"
                );
                $stmt->execute([$name, $description, $classifierMd]);
                $alert = peopleAlert('success', 'Person "' . $name . '" created.');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE maludb_subject
                        SET canonical_name = ?, description = ?, classifier_md = ?
                      WHERE subject_id = ? AND subject_type = 'person'"
                );
                $stmt->execute([$name, $description, $classifierMd, (int)$id]);
                $alert = peopleAlert('success', 'Person "' . $name . '" updated.');
            }
        } catch (Exception $e) {
            $alert = peopleAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    renderPeopleList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * ARCHIVE / RESTORE — POST action=archive|restore
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'archive' || $action === 'restore')) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'archive') {
            $stmt = $pdo->prepare(
                "UPDATE maludb_subject SET archived_at = now()
                  WHERE subject_id = ? AND subject_type = 'person'"
            );
            $verb = 'archived';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE maludb_subject SET archived_at = NULL
                  WHERE subject_id = ? AND subject_type = 'person'"
            );
            $verb = 'restored';
        }
        $stmt->execute([$id]);
        $alert = peopleAlert('success', 'Person ' . $verb . '.');
    } catch (Exception $e) {
        $alert = peopleAlert('danger', ucfirst($action) . ' failed: ' . $e->getMessage());
    }

    renderPeopleList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (create / edit modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';
    $person = ['name' => '', 'description' => '', 'classifier_md' => ''];

    if ($isEdit) {
        try {
            $stmt = $pdo->prepare(
                "SELECT subject_id     AS id,
                        canonical_name AS name,
                        description,
                        classifier_md
                   FROM maludb_person
                  WHERE subject_id = ?"
            );
            $stmt->execute([(int)$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $person = $found;
            }
        } catch (Exception $e) {
            error_log('Person form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="people-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="people-modal-dialog">
        <div class="modal-content" id="people-modal-content">
          <div class="modal-header" id="people-modal-header">
            <h5 class="modal-title" id="people-modal-title">
              <i class="feather-users me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> Person
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content"
                hx-swap="innerHTML"
                id="people-form">
            <div class="modal-body" id="people-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="mb-3" id="people-field-name-wrap">
                <label class="form-label" for="people-field-name">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" id="people-field-name"
                       value="<?php echo htmlspecialchars($person['name'] ?? ''); ?>"
                       placeholder="Person name" required>
              </div>
              <div class="mb-3" id="people-field-description-wrap">
                <label class="form-label" for="people-field-description">Description</label>
                <textarea class="form-control" name="description" id="people-field-description" rows="3"
                          placeholder="Optional description"><?php echo htmlspecialchars($person['description'] ?? ''); ?></textarea>
              </div>
              <div class="mb-0" id="people-field-classifier-wrap">
                <label class="form-label" for="people-field-classifier">Classifier (Markdown)</label>
                <textarea class="form-control font-monospace" name="classifier_md" id="people-field-classifier" rows="5"
                          placeholder="Optional classifier markdown"><?php echo htmlspecialchars($person['classifier_md'] ?? ''); ?></textarea>
              </div>
            </div>
            <div class="modal-footer" id="people-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="people-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="people-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
renderPeopleList($pdo, $selfUrl, $q);
