<?php
/**
 * Memory Elements — Events/Episodes (wired to MaluDB)
 *
 * Adapted from /v1/episodes and /v1/episodes/{id} (maludb_core 0.82.0).
 * Episodes are rows in the writable maludb_episode view. Create goes through
 * the search-path-safe facade maludb_register_episode(...); reads and writes
 * of the view run inside maludbTxCore() so the facade can resolve its malu$*
 * base tables + RLS grants. Kind values come from maludb_episode_type;
 * sensitivity ∈ {public,internal,restricted,prohibited} is DB-enforced.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/_db.php';

requireAuth();

$pdo = db();
$action = $_REQUEST['action'] ?? '';
$selfUrl = '/partials/memory/episodes.php';

const EPISODE_SENSITIVITIES = ['public', 'internal', 'restricted', 'prohibited'];

function episodesAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="episodes-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** Render the episodes list (filtered by $q / $kind) with an optional alert. */
function renderEpisodesList(PDO $pdo, string $selfUrl, string $q = '', string $kind = '', string $alertHtml = ''): void
{
    $rows = [];
    $loadError = '';
    $kindOptions = maludbTypeOptions($pdo, 'maludb_episode_type', 'episode_type', 'episode_type', 'display_order');
    try {
        $clauses = [];
        $params  = [];
        if ($kind !== '') { $clauses[] = "episode_kind = ?"; $params[] = $kind; }
        if ($q !== '') {
            $clauses[] = "(title ILIKE ? OR summary ILIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $where = $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '';

        $rows = maludbTxCore($pdo, function (PDO $pdo) use ($where, $params) {
            $stmt = $pdo->prepare(
                "SELECT episode_id AS id, episode_kind AS kind, title, summary,
                        occurred_at, occurred_until, sensitivity, provenance, created_at
                   FROM maludb_episode
                   $where
                  ORDER BY occurred_at DESC NULLS LAST, episode_id DESC
                  LIMIT 200"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="episodes-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="episodes-header">
    <div id="episodes-header-text">
      <h4 class="fw-bold mb-1" id="episodes-title"><i class="feather-activity me-2"></i>Events/Episodes</h4>
      <p class="text-muted mb-0" id="episodes-subtitle">Memory element &mdash; first-class events with provenance</p>
    </div>
    <div id="episodes-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              id="episodes-btn-new">
        <i class="feather-plus me-1"></i>New Episode
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="episodes-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load episodes: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <!-- List card -->
  <div class="card" id="episodes-card">
    <div class="card-header d-flex align-items-center justify-content-between gap-2" id="episodes-card-header">
      <h6 class="fw-bold mb-0" id="episodes-card-title">All Episodes</h6>
      <div class="d-flex gap-2 w-50 justify-content-end" id="episodes-filters">
        <select class="form-select form-select-sm w-auto" name="kind" id="episodes-kind-filter"
                hx-get="<?php echo $selfUrl; ?>"
                hx-include="#episodes-search"
                hx-target="#page-content" hx-swap="innerHTML">
          <option value="">All kinds</option>
          <?php foreach ($kindOptions as $value => $optLabel): ?>
          <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $kind === $value ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($optLabel); ?>
          </option>
          <?php endforeach; ?>
        </select>
        <input type="search" class="form-control form-control-sm w-50" name="q"
               value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search episodes&hellip;"
               hx-get="<?php echo $selfUrl; ?>"
               hx-include="#episodes-kind-filter"
               hx-trigger="input changed delay:400ms, search"
               hx-target="#page-content"
               hx-swap="innerHTML"
               id="episodes-search">
      </div>
    </div>
    <div class="card-body p-0" id="episodes-card-body">
      <div class="table-responsive" id="episodes-table-wrap">
        <table class="table table-hover mb-0" id="episodes-table">
          <thead id="episodes-table-head">
            <tr>
              <th>Title</th>
              <th>Kind</th>
              <th>Occurred</th>
              <th>Sensitivity</th>
              <th>Provenance</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="episodes-table-body">
            <?php if (empty($rows)): ?>
            <tr id="episodes-row-empty">
              <td colspan="6" class="text-center text-muted py-5">
                <i class="feather-activity fs-3 d-block mb-2"></i>
                <?php echo ($q !== '' || $kind !== '') ? 'No episodes match your filters.' : 'No episodes yet. Click New Episode to create one.'; ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row):
                $rowId = (int)$row['id'];
                $occurred = !empty($row['occurred_at']) ? date('M j, Y g:i A', strtotime($row['occurred_at'])) : '—';
                $sensBadge = [
                    'public'     => 'bg-soft-success text-success',
                    'internal'   => 'bg-soft-primary text-primary',
                    'restricted' => 'bg-soft-warning text-warning',
                    'prohibited' => 'bg-soft-danger text-danger',
                ][$row['sensitivity'] ?? ''] ?? 'bg-soft-secondary text-secondary';
            ?>
            <tr id="episodes-row-<?php echo $rowId; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
              <td><span class="badge bg-soft-info text-info"><?php echo htmlspecialchars($row['kind'] ?? ''); ?></span></td>
              <td class="fs-12"><?php echo htmlspecialchars($occurred); ?></td>
              <td><span class="badge <?php echo $sensBadge; ?>"><?php echo htmlspecialchars($row['sensitivity'] ?? ''); ?></span></td>
              <td class="fs-12 text-muted"><?php echo htmlspecialchars($row['provenance'] ?? ''); ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-icon" title="Edit"
                        hx-get="<?php echo $selfUrl; ?>?action=form&id=<?php echo $rowId; ?>"
                        hx-target="#modal-container" hx-swap="innerHTML"
                        id="episodes-row-<?php echo $rowId; ?>-edit"><i class="feather-edit-2"></i></button>
                <button class="btn btn-sm btn-icon" title="Delete"
                        hx-post="<?php echo $selfUrl; ?>?action=delete"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-confirm="Delete this episode?"
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="episodes-row-<?php echo $rowId; ?>-delete"><i class="feather-trash-2"></i></button>
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

/** Convert a datetime-local input value to a timestamptz string or null. */
function episodeTs(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : str_replace('T', ' ', $value);
}

/* ---------------------------------------------------------------------
 * SAVE (create / update) — POST action=save
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $id            = trim($_POST['id'] ?? '');
    $title         = trim($_POST['title'] ?? '');
    $kind          = trim($_POST['kind'] ?? '') ?: 'activity';
    $summary       = trim($_POST['summary'] ?? '');
    $summary       = $summary === '' ? null : $summary;
    $occurredAt    = episodeTs($_POST['occurred_at'] ?? '');
    $occurredUntil = episodeTs($_POST['occurred_until'] ?? '');
    $sensitivity   = trim($_POST['sensitivity'] ?? '') ?: 'internal';
    if (!in_array($sensitivity, EPISODE_SENSITIVITIES, true)) {
        $sensitivity = 'internal';
    }

    if ($title === '') {
        $alert = episodesAlert('danger', 'Field "title" is required.');
    } else {
        try {
            if ($id === '') {
                // Create via the search-path-safe facade (provenance defaults to 'provided').
                maludbTxCore($pdo, function (PDO $pdo) use ($kind, $title, $summary, $occurredAt, $occurredUntil, $sensitivity) {
                    $stmt = $pdo->prepare(
                        "SELECT maludb_register_episode(
                                    p_episode_kind   => ?,
                                    p_title          => ?,
                                    p_summary        => ?,
                                    p_payload_jsonb  => '{}'::jsonb,
                                    p_occurred_at    => ?::timestamptz,
                                    p_occurred_until => ?::timestamptz,
                                    p_sensitivity    => ?,
                                    p_provenance     => 'provided'
                                ) AS id"
                    );
                    $stmt->execute([$kind, $title, $summary, $occurredAt, $occurredUntil, $sensitivity]);
                    return (int)$stmt->fetchColumn();
                });
                $alert = episodesAlert('success', 'Episode "' . $title . '" created.');
            } else {
                maludbTxCore($pdo, function (PDO $pdo) use ($id, $kind, $title, $summary, $occurredAt, $occurredUntil, $sensitivity) {
                    $stmt = $pdo->prepare(
                        "UPDATE maludb_episode
                            SET title = ?, episode_kind = ?, summary = ?,
                                occurred_at = ?::timestamptz, occurred_until = ?::timestamptz,
                                sensitivity = ?
                          WHERE episode_id = ?"
                    );
                    $stmt->execute([$title, $kind, $summary, $occurredAt, $occurredUntil, $sensitivity, (int)$id]);
                });
                $alert = episodesAlert('success', 'Episode "' . $title . '" updated.');
            }
        } catch (Exception $e) {
            $alert = episodesAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    renderEpisodesList($pdo, $selfUrl, '', '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * DELETE — POST action=delete  (/v1/episodes/{id} DELETE)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $deleted = maludbTxCore($pdo, function (PDO $pdo) use ($id) {
            $stmt = $pdo->prepare("DELETE FROM maludb_episode WHERE episode_id = ?");
            $stmt->execute([$id]);
            return $stmt->rowCount();
        });
        $alert = $deleted > 0
            ? episodesAlert('success', 'Episode deleted.')
            : episodesAlert('danger', 'Episode not found.');
    } catch (Exception $e) {
        $alert = episodesAlert('danger', 'Delete failed: ' . $e->getMessage());
    }

    renderEpisodesList($pdo, $selfUrl, '', '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (create / edit modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';
    $episode = ['title' => '', 'kind' => 'activity', 'summary' => '',
                'occurred_at' => '', 'occurred_until' => '', 'sensitivity' => 'internal'];
    $kindOptions = maludbTypeOptions($pdo, 'maludb_episode_type', 'episode_type', 'episode_type', 'display_order');

    if ($isEdit) {
        try {
            $found = maludbTxCore($pdo, function (PDO $pdo) use ($id) {
                $stmt = $pdo->prepare(
                    "SELECT episode_id AS id, episode_kind AS kind, title, summary,
                            occurred_at, occurred_until, sensitivity
                       FROM maludb_episode
                      WHERE episode_id = ?"
                );
                $stmt->execute([(int)$id]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            });
            if ($found) {
                $episode = $found;
                // datetime-local inputs need Y-m-d\TH:i
                foreach (['occurred_at', 'occurred_until'] as $f) {
                    $episode[$f] = !empty($episode[$f]) ? date('Y-m-d\TH:i', strtotime($episode[$f])) : '';
                }
            }
        } catch (Exception $e) {
            error_log('Episode form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="episodes-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="episodes-modal-dialog">
        <div class="modal-content" id="episodes-modal-content">
          <div class="modal-header" id="episodes-modal-header">
            <h5 class="modal-title" id="episodes-modal-title">
              <i class="feather-activity me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> Episode
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content"
                hx-swap="innerHTML"
                id="episodes-form">
            <div class="modal-body" id="episodes-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="row" id="episodes-form-row-1">
                <div class="col-md-7 mb-3" id="episodes-field-title-wrap">
                  <label class="form-label" for="episodes-field-title">Title <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="title" id="episodes-field-title"
                         value="<?php echo htmlspecialchars($episode['title'] ?? ''); ?>"
                         placeholder="What happened" required>
                </div>
                <div class="col-md-5 mb-3" id="episodes-field-kind-wrap">
                  <label class="form-label" for="episodes-field-kind">Kind</label>
                  <?php if (!empty($kindOptions)): ?>
                  <select class="form-select" name="kind" id="episodes-field-kind">
                    <?php foreach ($kindOptions as $value => $optLabel): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($episode['kind'] ?? '') === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($optLabel); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <?php else: ?>
                  <input type="text" class="form-control" name="kind" id="episodes-field-kind"
                         value="<?php echo htmlspecialchars($episode['kind'] ?? 'activity'); ?>" placeholder="activity">
                  <?php endif; ?>
                </div>
              </div>
              <div class="mb-3" id="episodes-field-summary-wrap">
                <label class="form-label" for="episodes-field-summary">Summary</label>
                <textarea class="form-control" name="summary" id="episodes-field-summary" rows="3"
                          placeholder="Optional summary"><?php echo htmlspecialchars($episode['summary'] ?? ''); ?></textarea>
              </div>
              <div class="row" id="episodes-form-row-2">
                <div class="col-md-4 mb-3" id="episodes-field-occurred-wrap">
                  <label class="form-label" for="episodes-field-occurred">Occurred At</label>
                  <input type="datetime-local" class="form-control" name="occurred_at" id="episodes-field-occurred"
                         value="<?php echo htmlspecialchars($episode['occurred_at'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3" id="episodes-field-until-wrap">
                  <label class="form-label" for="episodes-field-until">Occurred Until</label>
                  <input type="datetime-local" class="form-control" name="occurred_until" id="episodes-field-until"
                         value="<?php echo htmlspecialchars($episode['occurred_until'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3" id="episodes-field-sensitivity-wrap">
                  <label class="form-label" for="episodes-field-sensitivity">Sensitivity</label>
                  <select class="form-select" name="sensitivity" id="episodes-field-sensitivity">
                    <?php foreach (EPISODE_SENSITIVITIES as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo ($episode['sensitivity'] ?? 'internal') === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer" id="episodes-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="episodes-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="episodes-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
$kind = trim($_GET['kind'] ?? '');
renderEpisodesList($pdo, $selfUrl, $q, $kind);
