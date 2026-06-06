<?php
/**
 * Memory Elements — Model Prompts (CRUD on public.client_model_prompt)
 *
 * Per-model extraction prompt + LLM connection, replacing what v1/memory_ingest.php read
 * from LocalDatabase::modelPrompt($model): system_prompt, api_format/base_url/api_key
 * (via the client_token FK — managed on the Token Setup page), model_identifier,
 * max_tokens, generation_params.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireAuth();
$pdo = db();

$selfUrl = '/partials/memory/model-prompts.php';
$action = $_REQUEST['action'] ?? '';

function mpAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="model-prompts-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** value => label options from client_token for the token dropdown. */
function mpTokenOptions(PDO $pdo): array
{
    try {
        $rows = $pdo->query(
            "SELECT token_id, token_name, api_format FROM client_token ORDER BY token_name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $options = [];
        foreach ($rows as $r) {
            $options[(int)$r['token_id']] = $r['token_name'] . ' (' . $r['api_format'] . ')';
        }
        return $options;
    } catch (Exception $e) {
        error_log('mpTokenOptions failed: ' . $e->getMessage());
        return [];
    }
}

$renderList = function (PDO $pdo, string $alertHtml = '') use ($selfUrl): void {
    $rows = [];
    $loadError = '';
    try {
        $rows = $pdo->query(
            "SELECT p.model, p.model_identifier, p.max_tokens, p.updated_at,
                    t.token_name, t.api_format
               FROM client_model_prompt p
               JOIN client_token t ON t.token_id = p.token_id
              ORDER BY p.model"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="model-prompts-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="model-prompts-header">
    <div id="model-prompts-header-text">
      <h4 class="fw-bold mb-1" id="model-prompts-title"><i class="feather-message-square me-2"></i>Model Prompts</h4>
      <p class="text-muted mb-0" id="model-prompts-subtitle">Per-model extraction prompt and connection used by memory ingest</p>
    </div>
    <div id="model-prompts-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container" hx-swap="innerHTML"
              id="model-prompts-btn-new">
        <i class="feather-plus me-1"></i>New Model Prompt
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="model-prompts-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load model prompts: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="card" id="model-prompts-card">
    <div class="card-body p-0" id="model-prompts-card-body">
      <div class="table-responsive" id="model-prompts-table-wrap">
        <table class="table table-hover mb-0" id="model-prompts-table">
          <thead id="model-prompts-table-head">
            <tr>
              <th>Model</th>
              <th>Token</th>
              <th>Model Identifier</th>
              <th class="text-center">Max Tokens</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="model-prompts-table-body">
            <?php if (empty($rows)): ?>
            <tr id="model-prompts-row-empty">
              <td colspan="5" class="text-center text-muted py-5">
                <i class="feather-message-square fs-3 d-block mb-2"></i>No model prompts configured yet.
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row): $rowKey = htmlspecialchars(rawurlencode($row['model'])); ?>
            <tr id="model-prompts-row-<?php echo $rowKey; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['model']); ?></td>
              <td><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($row['token_name'] . ' (' . $row['api_format'] . ')'); ?></span></td>
              <td><?php echo $row['model_identifier'] === null || $row['model_identifier'] === '' ? '—' : htmlspecialchars($row['model_identifier']); ?></td>
              <td class="text-center"><?php echo (int)$row['max_tokens']; ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-1" id="model-prompts-row-<?php echo $rowKey; ?>-actions">
                  <button class="btn btn-sm btn-icon" title="Edit"
                          hx-get="<?php echo $selfUrl; ?>?action=form&model=<?php echo $rowKey; ?>"
                          hx-target="#modal-container" hx-swap="innerHTML"
                          id="model-prompts-row-<?php echo $rowKey; ?>-edit"><i class="feather-edit-2"></i></button>
                  <button class="btn btn-sm btn-icon" title="Delete"
                          hx-post="<?php echo $selfUrl; ?>?action=delete"
                          hx-vals='{"model": "<?php echo htmlspecialchars($row['model']); ?>"}'
                          hx-confirm="Delete the prompt for this model?"
                          hx-target="#page-content" hx-swap="innerHTML"
                          id="model-prompts-row-<?php echo $rowKey; ?>-delete"><i class="feather-trash-2"></i></button>
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
    $origModel    = trim($_POST['orig_model'] ?? '');   // '' on create
    $model        = trim($_POST['model'] ?? '');
    $tokenId      = (int)($_POST['token_id'] ?? 0);
    $identifier   = trim($_POST['model_identifier'] ?? '');
    $identifier   = $identifier === '' ? null : $identifier;
    $systemPrompt = trim($_POST['system_prompt'] ?? '');
    $maxTokens    = (int)($_POST['max_tokens'] ?? 0) ?: 4096;
    $genParams    = trim($_POST['generation_params'] ?? '');
    $genParams    = $genParams === '' ? '{}' : $genParams;

    if ($model === '' || $tokenId <= 0 || $systemPrompt === '') {
        $alert = mpAlert('danger', 'Model, token and system prompt are required.');
    } elseif (!is_array(json_decode($genParams, true))) {
        $alert = mpAlert('danger', 'Generation params must be a valid JSON object.');
    } else {
        try {
            if ($origModel === '') {
                $stmt = $pdo->prepare(
                    "INSERT INTO client_model_prompt (model, token_id, model_identifier, system_prompt, max_tokens, generation_params)
                     VALUES (?, ?, ?, ?, ?, ?::jsonb)"
                );
                $stmt->execute([$model, $tokenId, $identifier, $systemPrompt, $maxTokens, $genParams]);
                $alert = mpAlert('success', 'Prompt for model "' . $model . '" created.');
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE client_model_prompt
                        SET model = ?, token_id = ?, model_identifier = ?, system_prompt = ?,
                            max_tokens = ?, generation_params = ?::jsonb, updated_at = now()
                      WHERE model = ?"
                );
                $stmt->execute([$model, $tokenId, $identifier, $systemPrompt, $maxTokens, $genParams, $origModel]);
                $alert = mpAlert('success', 'Prompt for model "' . $model . '" updated.');
            }
        } catch (Exception $e) {
            $alert = (strpos($e->getMessage(), '23505') !== false || stripos($e->getMessage(), 'duplicate') !== false)
                ? mpAlert('danger', 'A prompt for model "' . $model . '" already exists.')
                : mpAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    $renderList($pdo, $alert);
    exit;
}

/* DELETE — POST action=delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $model = trim($_POST['model'] ?? '');
    try {
        $stmt = $pdo->prepare("DELETE FROM client_model_prompt WHERE model = ?");
        $stmt->execute([$model]);
        $alert = $stmt->rowCount() > 0
            ? mpAlert('success', 'Model prompt deleted.')
            : mpAlert('danger', 'Model prompt not found.');
    } catch (Exception $e) {
        $alert = mpAlert('danger', 'Delete failed: ' . $e->getMessage());
    }
    $renderList($pdo, $alert);
    exit;
}

/* FORM — GET action=form */
if ($action === 'form') {
    $origModel = trim($_GET['model'] ?? '');
    $isEdit = $origModel !== '';
    $row = ['model' => '', 'token_id' => 0, 'model_identifier' => '', 'system_prompt' => '', 'max_tokens' => 4096, 'generation_params' => '{}'];
    if ($isEdit) {
        try {
            $stmt = $pdo->prepare(
                "SELECT model, token_id, model_identifier, system_prompt, max_tokens, generation_params
                   FROM client_model_prompt WHERE model = ?"
            );
            $stmt->execute([$origModel]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $row = $found;
            }
        } catch (Exception $e) {
            error_log('Model prompt form load failed: ' . $e->getMessage());
        }
    }
    $tokenOptions = mpTokenOptions($pdo);
    ?>
    <div class="modal fade" tabindex="-1" id="model-prompts-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="model-prompts-modal-dialog">
        <div class="modal-content" id="model-prompts-modal-content">
          <div class="modal-header" id="model-prompts-modal-header">
            <h5 class="modal-title" id="model-prompts-modal-title">
              <i class="feather-message-square me-2"></i><?php echo $isEdit ? 'Edit' : 'New'; ?> Model Prompt
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content" hx-swap="innerHTML"
                id="model-prompts-form">
            <div class="modal-body" id="model-prompts-modal-body">
              <input type="hidden" name="orig_model" value="<?php echo htmlspecialchars($origModel); ?>">
              <?php if (empty($tokenOptions)): ?>
              <div class="alert alert-warning" id="model-prompts-no-tokens">
                <i class="feather-alert-triangle me-2"></i>No provider tokens configured yet — add one on the Token Setup page first.
              </div>
              <?php endif; ?>
              <div class="row" id="model-prompts-field-row-top">
                <div class="col-md-6 mb-3" id="model-prompts-field-model-wrap">
                  <label class="form-label" for="model-prompts-field-model">Model <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="model" id="model-prompts-field-model"
                         value="<?php echo htmlspecialchars($row['model'] ?? ''); ?>"
                         placeholder="e.g. chatgpt-4o" required>
                </div>
                <div class="col-md-6 mb-3" id="model-prompts-field-token-wrap">
                  <label class="form-label" for="model-prompts-field-token">Token <span class="text-danger">*</span></label>
                  <select class="form-select" name="token_id" id="model-prompts-field-token" required>
                    <option value="">Select a token…</option>
                    <?php foreach ($tokenOptions as $value => $optLabel): ?>
                    <option value="<?php echo $value; ?>" <?php echo (int)($row['token_id'] ?? 0) === $value ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($optLabel); ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row" id="model-prompts-field-row-mid">
                <div class="col-md-8 mb-3" id="model-prompts-field-identifier-wrap">
                  <label class="form-label" for="model-prompts-field-identifier">Model Identifier <span class="text-muted fs-12">(provider's id; defaults to model)</span></label>
                  <input type="text" class="form-control" name="model_identifier" id="model-prompts-field-identifier"
                         value="<?php echo htmlspecialchars((string)($row['model_identifier'] ?? '')); ?>"
                         placeholder="e.g. gpt-4o-2024-08-06">
                </div>
                <div class="col-md-4 mb-3" id="model-prompts-field-maxtokens-wrap">
                  <label class="form-label" for="model-prompts-field-maxtokens">Max Tokens</label>
                  <input type="number" class="form-control" name="max_tokens" id="model-prompts-field-maxtokens"
                         value="<?php echo (int)($row['max_tokens'] ?? 4096); ?>">
                </div>
              </div>
              <div class="mb-3" id="model-prompts-field-prompt-wrap">
                <label class="form-label" for="model-prompts-field-prompt">System Prompt <span class="text-danger">*</span></label>
                <textarea class="form-control font-monospace" name="system_prompt" id="model-prompts-field-prompt" rows="8"
                          placeholder="The extraction SYSTEM prompt sent to the model" required><?php echo htmlspecialchars($row['system_prompt'] ?? ''); ?></textarea>
              </div>
              <div class="mb-0" id="model-prompts-field-genparams-wrap">
                <label class="form-label" for="model-prompts-field-genparams">Generation Params <span class="text-muted fs-12">(JSON object)</span></label>
                <textarea class="form-control font-monospace" name="generation_params" id="model-prompts-field-genparams" rows="2"
                          placeholder='{"temperature": 0}'><?php echo htmlspecialchars($row['generation_params'] ?? '{}'); ?></textarea>
              </div>
            </div>
            <div class="modal-footer" id="model-prompts-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="model-prompts-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="model-prompts-form-submit"><?php echo $isEdit ? 'Update' : 'Create'; ?></button>
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
