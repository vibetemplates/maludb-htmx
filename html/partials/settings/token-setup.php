<?php
/**
 * Admin — Token Setup (CRUD on public.client_token)
 *
 * LLM provider connections + API keys, replacing the API server's local MySQL store that
 * v1/memory_ingest.php read via LocalDatabase::modelPrompt(). Client tokens live in the
 * public schema with the rest of the user application tables — never in maludb_*.
 * The api_key is entered once and never echoed back; on edit, leave blank to keep current.
 *
 * Future: client_api_token (sha256-hashed, shown-once) for the planned MCP server
 * interfacing with Retell AI will be managed from this page too.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();
$pdo = db();

$selfUrl = '/partials/settings/token-setup.php';
$action = $_REQUEST['action'] ?? '';

const TS_API_FORMATS = ['openai', 'anthropic'];

function tsAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="token-setup-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

$renderList = function (PDO $pdo, string $alertHtml = '') use ($selfUrl): void {
    $rows = [];
    $loadError = '';
    try {
        $rows = $pdo->query(
            "SELECT t.token_id, t.token_name, t.api_format, t.base_url, t.updated_at,
                    (SELECT COUNT(*) FROM client_model_prompt p WHERE p.token_id = t.token_id) AS prompt_count
               FROM client_token t
              ORDER BY t.token_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="token-setup-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="token-setup-header">
    <div id="token-setup-header-text">
      <h4 class="fw-bold mb-1" id="token-setup-title"><i class="feather-key me-2"></i>Token Setup</h4>
      <p class="text-muted mb-0" id="token-setup-subtitle">LLM provider connections &mdash; API keys are stored once and never displayed again</p>
    </div>
    <div id="token-setup-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container" hx-swap="innerHTML"
              id="token-setup-btn-new">
        <i class="feather-plus me-1"></i>New Token
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="token-setup-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load tokens: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="card" id="token-setup-card">
    <div class="card-body p-0" id="token-setup-card-body">
      <div class="table-responsive" id="token-setup-table-wrap">
        <table class="table table-hover mb-0" id="token-setup-table">
          <thead id="token-setup-table-head">
            <tr>
              <th>Name</th>
              <th>API Format</th>
              <th>Base URL</th>
              <th>API Key</th>
              <th class="text-center">Prompts</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="token-setup-table-body">
            <?php if (empty($rows)): ?>
            <tr id="token-setup-row-empty">
              <td colspan="6" class="text-center text-muted py-5">
                <i class="feather-key fs-3 d-block mb-2"></i>No provider tokens configured yet.
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row): $rowId = (int)$row['token_id']; ?>
            <tr id="token-setup-row-<?php echo $rowId; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['token_name']); ?></td>
              <td><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($row['api_format']); ?></span></td>
              <td><?php echo htmlspecialchars(mb_strimwidth((string)$row['base_url'], 0, 50, '…')); ?></td>
              <td class="text-muted">••••••••</td>
              <td class="text-center"><?php echo (int)$row['prompt_count']; ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1" id="token-setup-row-<?php echo $rowId; ?>-actions">
                  <button class="btn btn-sm btn-icon" title="Edit"
                          hx-get="<?php echo $selfUrl; ?>?action=form&id=<?php echo $rowId; ?>"
                          hx-target="#modal-container" hx-swap="innerHTML"
                          id="token-setup-row-<?php echo $rowId; ?>-edit"><i class="feather-edit-2"></i></button>
                  <button class="btn btn-sm btn-icon" title="Delete"
                          hx-post="<?php echo $selfUrl; ?>?action=delete"
                          hx-vals='{"id": "<?php echo $rowId; ?>"}'
                          hx-confirm="Delete this provider token?"
                          hx-target="#page-content" hx-swap="innerHTML"
                          id="token-setup-row-<?php echo $rowId; ?>-delete"><i class="feather-trash-2"></i></button>
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
    $id        = trim($_POST['id'] ?? '');
    $name      = trim($_POST['token_name'] ?? '');
    $apiFormat = trim($_POST['api_format'] ?? '');
    $baseUrl   = trim($_POST['base_url'] ?? '');
    $apiKey    = (string)($_POST['api_key'] ?? '');   // never echoed back

    if ($name === '' || $baseUrl === '' || !in_array($apiFormat, TS_API_FORMATS, true)) {
        $alert = tsAlert('danger', 'Name, API format (openai or anthropic) and base URL are required.');
    } elseif ($id === '' && $apiKey === '') {
        $alert = tsAlert('danger', 'The API key is required for a new token.');
    } else {
        try {
            if ($id === '') {
                $stmt = $pdo->prepare(
                    "INSERT INTO client_token (token_name, api_format, base_url, api_key) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$name, $apiFormat, $baseUrl, $apiKey]);
                $alert = tsAlert('success', 'Token "' . $name . '" created.');
            } elseif ($apiKey === '') {
                // Edit with key left blank — keep the stored key.
                $stmt = $pdo->prepare(
                    "UPDATE client_token SET token_name = ?, api_format = ?, base_url = ?, updated_at = now() WHERE token_id = ?"
                );
                $stmt->execute([$name, $apiFormat, $baseUrl, (int)$id]);
                $alert = tsAlert('success', 'Token "' . $name . '" updated (key unchanged).');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE client_token SET token_name = ?, api_format = ?, base_url = ?, api_key = ?, updated_at = now() WHERE token_id = ?"
                );
                $stmt->execute([$name, $apiFormat, $baseUrl, $apiKey, (int)$id]);
                $alert = tsAlert('success', 'Token "' . $name . '" updated.');
            }
        } catch (Exception $e) {
            $alert = (strpos($e->getMessage(), '23505') !== false || stripos($e->getMessage(), 'duplicate') !== false)
                ? tsAlert('danger', 'A token named "' . $name . '" already exists.')
                : tsAlert('danger', 'Save failed: ' . $e->getMessage());
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
        $stmt = $pdo->prepare("DELETE FROM client_token WHERE token_id = ?");
        $stmt->execute([$id]);
        $alert = $stmt->rowCount() > 0
            ? tsAlert('success', 'Token deleted.')
            : tsAlert('danger', 'Token not found.');
    } catch (Exception $e) {
        $alert = (strpos($e->getMessage(), '23503') !== false || stripos($e->getMessage(), 'foreign key') !== false)
            ? tsAlert('danger', 'This token is used by one or more model prompts — reassign or delete those first.')
            : tsAlert('danger', 'Delete failed: ' . $e->getMessage());
    }
    $renderList($pdo, $alert);
    exit;
}

/* FORM — GET action=form */
if ($action === 'form') {
    $id = trim($_GET['id'] ?? '');
    $isEdit = $id !== '';
    $row = ['token_name' => '', 'api_format' => 'openai', 'base_url' => ''];
    if ($isEdit) {
        try {
            // api_key is intentionally NOT selected — it is never sent back to the browser.
            $stmt = $pdo->prepare("SELECT token_name, api_format, base_url FROM client_token WHERE token_id = ?");
            $stmt->execute([(int)$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $row = $found;
            }
        } catch (Exception $e) {
            error_log('Token form load failed: ' . $e->getMessage());
        }
    }
    ?>
    <div class="modal fade" tabindex="-1" id="token-setup-modal">
      <div class="modal-dialog modal-dialog-centered" id="token-setup-modal-dialog">
        <div class="modal-content" id="token-setup-modal-content">
          <div class="modal-header" id="token-setup-modal-header">
            <h5 class="modal-title" id="token-setup-modal-title">
              <i class="feather-key me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> Token
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content" hx-swap="innerHTML"
                id="token-setup-form">
            <div class="modal-body" id="token-setup-modal-body">
              <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
              <div class="mb-3" id="token-setup-field-name-wrap">
                <label class="form-label" for="token-setup-field-name">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="token_name" id="token-setup-field-name"
                       value="<?php echo htmlspecialchars($row['token_name'] ?? ''); ?>"
                       placeholder="e.g. openai-prod" required>
              </div>
              <div class="mb-3" id="token-setup-field-format-wrap">
                <label class="form-label" for="token-setup-field-format">API Format <span class="text-danger">*</span></label>
                <select class="form-select" name="api_format" id="token-setup-field-format">
                  <?php foreach (TS_API_FORMATS as $f): ?>
                  <option value="<?php echo $f; ?>" <?php echo ($row['api_format'] ?? '') === $f ? 'selected' : ''; ?>><?php echo $f; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3" id="token-setup-field-baseurl-wrap">
                <label class="form-label" for="token-setup-field-baseurl">Base URL <span class="text-danger">*</span></label>
                <input type="url" class="form-control" name="base_url" id="token-setup-field-baseurl"
                       value="<?php echo htmlspecialchars($row['base_url'] ?? ''); ?>"
                       placeholder="e.g. https://api.openai.com/v1" required>
              </div>
              <div class="mb-0" id="token-setup-field-apikey-wrap">
                <label class="form-label" for="token-setup-field-apikey">API Key <?php echo $isEdit ? '' : '<span class="text-danger">*</span>'; ?></label>
                <input type="password" class="form-control" name="api_key" id="token-setup-field-apikey"
                       placeholder="<?php echo $isEdit ? 'Leave blank to keep current key' : 'Provider API key'; ?>"
                       autocomplete="new-password" <?php echo $isEdit ? '' : 'required'; ?>>
              </div>
            </div>
            <div class="modal-footer" id="token-setup-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="token-setup-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="token-setup-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
