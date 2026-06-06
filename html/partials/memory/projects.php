<?php
/**
 * Memory Elements — Projects (wired to MaluDB)
 *
 * A "project" is a subject with subject_type='project' (maludb_project is a
 * view of maludb_subject WHERE subject_type='project'). project id =
 * subject_id. Projects expose `name` (-> canonical_name).
 *
 * List + create SQL comes from the /v1/projects endpoint (requirements.md
 * §4.6). Update and archive/restore are inferred from the same model and
 * marked PROVISIONAL below — confirm against the MaluDB spec.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();

$pdo = db();
$action = $_REQUEST['action'] ?? '';
$selfUrl = '/partials/memory/projects.php';

/** Small alert fragment used above the list after actions. */
function projectsAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="projects-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** Render the projects list (optionally filtered by $q) with an optional alert. */
function renderProjectsList(PDO $pdo, string $selfUrl, string $q = '', string $alertHtml = ''): void
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
               FROM maludb_project
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
<div class="container-fluid p-4" id="projects-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="projects-header">
    <div id="projects-header-text">
      <h4 class="fw-bold mb-1" id="projects-title"><i class="feather-folder me-2"></i>Projects</h4>
      <p class="text-muted mb-0" id="projects-subtitle">Memory element &mdash; subjects with subject_type='project'</p>
    </div>
    <div id="projects-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              id="projects-btn-new">
        <i class="feather-plus me-1"></i>New Project
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="projects-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load projects: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <!-- List card -->
  <div class="card" id="projects-card">
    <div class="card-header d-flex align-items-center justify-content-between" id="projects-card-header">
      <h6 class="fw-bold mb-0" id="projects-card-title">All Projects</h6>
      <div class="w-25" id="projects-search-wrap">
        <input type="search" class="form-control form-control-sm" name="q"
               value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search projects&hellip;"
               hx-get="<?php echo $selfUrl; ?>"
               hx-trigger="input changed delay:400ms, search"
               hx-target="#page-content"
               hx-swap="innerHTML"
               id="projects-search">
      </div>
    </div>
    <div class="card-body p-0" id="projects-card-body">
      <div class="table-responsive" id="projects-table-wrap">
        <table class="table table-hover mb-0" id="projects-table">
          <thead id="projects-table-head">
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="projects-table-body">
            <?php if (empty($rows)): ?>
            <tr id="projects-row-empty">
              <td colspan="4" class="text-center text-muted py-5">
                <i class="feather-folder fs-3 d-block mb-2"></i>
                <?php echo $q !== '' ? 'No projects match your search.' : 'No projects yet. Click New Project to create one.'; ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $i => $row):
                $rowId = (int)$row['id'];
                $isArchived = !empty($row['archived_at']);
            ?>
            <tr id="projects-row-<?php echo $rowId; ?>" class="<?php echo $isArchived ? 'text-muted' : ''; ?>">
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
                        id="projects-row-<?php echo $rowId; ?>-edit"><i class="feather-edit-2"></i></button>
                <?php if ($isArchived): ?>
                <button class="btn btn-sm btn-icon" title="Restore"
                        hx-post="<?php echo $selfUrl; ?>?action=restore"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="projects-row-<?php echo $rowId; ?>-restore"><i class="feather-rotate-ccw"></i></button>
                <?php else: ?>
                <button class="btn btn-sm btn-icon" title="Archive"
                        hx-post="<?php echo $selfUrl; ?>?action=archive"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-confirm="Archive this project?"
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="projects-row-<?php echo $rowId; ?>-archive"><i class="feather-archive"></i></button>
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
    $id            = trim($_POST['id'] ?? '');
    $name          = trim($_POST['name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $classifierMd  = trim($_POST['classifier_md'] ?? '');
    $description   = $description === '' ? null : $description;
    $classifierMd  = $classifierMd === '' ? null : $classifierMd;

    if ($name === '') {
        $alert = projectsAlert('danger', 'Field "name" is required.');
    } else {
        try {
            if ($id === '') {
                // Create — SQL from /v1/projects POST:
                // a project is a subject of type 'project'; subject_id has no sequence.
                $stmt = $pdo->prepare(
                    "INSERT INTO maludb_subject
                         (subject_id, canonical_name, subject_type, description, classifier_md, created_at)
                     SELECT COALESCE(MAX(subject_id), 0) + 1, ?, 'project', ?, ?, now()
                       FROM maludb_subject
                     RETURNING subject_id AS id"
                );
                $stmt->execute([$name, $description, $classifierMd]);
                $alert = projectsAlert('success', 'Project "' . $name . '" created.');
            } else {
                // PROVISIONAL update — inferred from the same subject model.
                $stmt = $pdo->prepare(
                    "UPDATE maludb_subject
                        SET canonical_name = ?, description = ?, classifier_md = ?
                      WHERE subject_id = ? AND subject_type = 'project'"
                );
                $stmt->execute([$name, $description, $classifierMd, (int)$id]);
                $alert = projectsAlert('success', 'Project "' . $name . '" updated.');
            }
        } catch (Exception $e) {
            $alert = projectsAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    renderProjectsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * ARCHIVE / RESTORE — POST action=archive|restore (PROVISIONAL)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'archive' || $action === 'restore')) {
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'archive') {
            $stmt = $pdo->prepare(
                "UPDATE maludb_subject SET archived_at = now()
                  WHERE subject_id = ? AND subject_type = 'project'"
            );
            $verb = 'archived';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE maludb_subject SET archived_at = NULL
                  WHERE subject_id = ? AND subject_type = 'project'"
            );
            $verb = 'restored';
        }
        $stmt->execute([$id]);
        $alert = projectsAlert('success', 'Project ' . $verb . '.');
    } catch (Exception $e) {
        $alert = projectsAlert('danger', ucfirst($action) . ' failed: ' . $e->getMessage());
    }

    renderProjectsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (create / edit modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';
    $project = ['name' => '', 'description' => '', 'classifier_md' => ''];

    if ($isEdit) {
        try {
            $stmt = $pdo->prepare(
                "SELECT subject_id     AS id,
                        canonical_name AS name,
                        description,
                        classifier_md
                   FROM maludb_project
                  WHERE subject_id = ?"
            );
            $stmt->execute([(int)$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $project = $found;
            }
        } catch (Exception $e) {
            error_log('Project form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="projects-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="projects-modal-dialog">
        <div class="modal-content" id="projects-modal-content">
          <div class="modal-header" id="projects-modal-header">
            <h5 class="modal-title" id="projects-modal-title">
              <i class="feather-folder me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> Project
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content"
                hx-swap="innerHTML"
                id="projects-form">
            <div class="modal-body" id="projects-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="mb-3" id="projects-field-name-wrap">
                <label class="form-label" for="projects-field-name">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" id="projects-field-name"
                       value="<?php echo htmlspecialchars($project['name'] ?? ''); ?>"
                       placeholder="Project name" required>
              </div>
              <div class="mb-3" id="projects-field-description-wrap">
                <label class="form-label" for="projects-field-description">Description</label>
                <textarea class="form-control" name="description" id="projects-field-description" rows="3"
                          placeholder="Optional description"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
              </div>
              <div class="mb-0" id="projects-field-classifier-wrap">
                <label class="form-label" for="projects-field-classifier">Classifier (Markdown)</label>
                <textarea class="form-control font-monospace" name="classifier_md" id="projects-field-classifier" rows="5"
                          placeholder="Optional classifier markdown"><?php echo htmlspecialchars($project['classifier_md'] ?? ''); ?></textarea>
              </div>
            </div>
            <div class="modal-footer" id="projects-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="projects-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="projects-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
renderProjectsList($pdo, $selfUrl, $q);
