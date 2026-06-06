<?php
/**
 * MaluDB Setup — Memory Config (model/embedding/prompt per namespace)
 *
 * Adapted from /v1/memory/config (maludb_core 0.91.0 self-service facades).
 * Read: maludb_memory_model_config(namespace) — secret_ref is the NAME, never
 * the token value. Configure: secret_set (token stored encrypted) → register
 * provider → register alias (base_url in runtime_params) → set_model_config,
 * all in ONE maludbTxCore() transaction. The token is never displayed,
 * logged, or echoed back.
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';
require_once __DIR__ . '/../_db.php';

requireAuth();

$pdo = db();
$selfUrl = '/partials/memory/setup/memory-config.php';
$namespace = trim($_REQUEST['namespace'] ?? '') ?: 'default';
$alertHtml = '';

const MC_PROVENANCES = ['provided', 'suggested', 'accepted', 'rejected'];
const MC_SENSITIVITIES = ['public', 'internal', 'restricted', 'prohibited'];

function mcAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="memory-config-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/* ---------------------------------------------------------------------
 * SAVE — POST: the 4-step facade sequence in one transaction
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namespace      = trim($_POST['namespace'] ?? '') ?: 'default';
    $secretName     = trim($_POST['secret_name'] ?? '');
    $token          = (string)($_POST['token'] ?? '');           // never echoed back
    $provName       = trim($_POST['provider_name'] ?? '');
    $provKind       = trim($_POST['provider_kind'] ?? '');
    $provAdapter    = trim($_POST['provider_adapter'] ?? '');
    $provAdapter    = $provAdapter === '' ? null : $provAdapter;
    $provSens       = trim($_POST['provider_sensitivity'] ?? '') ?: 'internal';
    $aliasName      = trim($_POST['alias_name'] ?? '');
    $aliasModel     = trim($_POST['alias_model'] ?? '');
    $aliasCtx       = trim($_POST['alias_context_length'] ?? '');
    $aliasCtx       = $aliasCtx === '' ? null : (int)$aliasCtx;
    $baseUrl        = trim($_POST['base_url'] ?? '');
    $embeddingModel = trim($_POST['embedding_model'] ?? '');
    $promptTemplate = trim($_POST['prompt_template'] ?? '');
    $promptTemplate = $promptTemplate === '' ? null : $promptTemplate;
    $defaultSubject = trim($_POST['default_subject_type'] ?? '') ?: 'other';
    $defaultProv    = trim($_POST['default_provenance'] ?? '') ?: 'suggested';

    // Shape validation mirroring /v1/memory/config
    $errors = [];
    if ($provName === '' || $provKind === '') $errors[] = 'provider name and kind are required';
    if ($aliasName === '' || $aliasModel === '') $errors[] = 'alias name and model identifier are required';
    if ($baseUrl === '') $errors[] = 'base URL is required';
    if ($embeddingModel === '') $errors[] = 'embedding model is required';
    if ($promptTemplate !== null && strpos($promptTemplate, '{{chunk}}') === false) {
        $errors[] = 'prompt template must contain the {{chunk}} placeholder';
    }
    if ($token !== '' && $secretName === '') $errors[] = 'secret name is required when a token is provided';

    if ($errors) {
        $alertHtml = mcAlert('danger', 'Cannot save: ' . implode('; ', $errors) . '.');
    } else {
        try {
            maludbTxCore($pdo, function (PDO $pdo) use (
                $namespace, $secretName, $token, $provName, $provKind, $provAdapter, $provSens,
                $aliasName, $aliasModel, $aliasCtx, $baseUrl, $embeddingModel, $promptTemplate,
                $defaultSubject, $defaultProv
            ) {
                // 1. store the token encrypted (only when provided; value never logged).
                if ($token !== '') {
                    $stmt = $pdo->prepare(
                        "SELECT secret_id FROM maludb_core.secret_set(p_name => ?, p_kind => 'provider', p_value => ?)"
                    );
                    $stmt->execute([$secretName, $token]);
                }
                // 2. register the provider (secret referenced by name, never inlined).
                $stmt = $pdo->prepare(
                    "SELECT maludb_register_model_provider(
                                p_name => ?, p_kind => ?, p_adapter_name => ?,
                                p_secret_ref => ?, p_data_sensitivity => ?) AS id"
                );
                $stmt->execute([$provName, $provKind, $provAdapter, $secretName === '' ? null : $secretName, $provSens]);
                // 3. register the alias (base_url rides in runtime_params).
                $stmt = $pdo->prepare(
                    "SELECT maludb_register_model_alias(
                                p_alias => ?, p_provider => ?, p_model_identifier => ?,
                                p_context_length => ?, p_runtime_params => jsonb_build_object('base_url', ?::text)) AS id"
                );
                $stmt->execute([$aliasName, $provName, $aliasModel, $aliasCtx, $baseUrl]);
                // 4. bind alias + prompt + embedding + defaults for this namespace.
                $stmt = $pdo->prepare(
                    "SELECT maludb_memory_set_model_config(
                                p_extraction_alias     => ?,
                                p_prompt_template      => ?,
                                p_embedding_model      => ?,
                                p_namespace            => ?,
                                p_generation_params    => '{}'::jsonb,
                                p_default_subject_type => ?,
                                p_default_provenance   => ?) AS cfg"
                );
                $stmt->execute([$aliasName, $promptTemplate, $embeddingModel, $namespace, $defaultSubject, $defaultProv]);
            });
            $alertHtml = mcAlert('success', 'Memory configuration for namespace "' . $namespace . '" saved.');
        } catch (Exception $e) {
            $alertHtml = mcAlert('danger', 'Save failed: ' . $e->getMessage());
        }
    }
}

/* ---------------------------------------------------------------------
 * READ current config for the namespace
 * ------------------------------------------------------------------- */
$config = null;
$loadError = '';
try {
    $row = maludbTxCore($pdo, function (PDO $pdo) use ($namespace) {
        $stmt = $pdo->prepare("SELECT maludb_memory_model_config(?) AS cfg");
        $stmt->execute([$namespace]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    });
    $config = ($row && $row['cfg'] !== null) ? json_decode($row['cfg'], true) : null;
} catch (Exception $e) {
    $loadError = $e->getMessage();
}

$subjectTypes = maludbTypeOptions($pdo, 'maludb_subject_type', 'subject_type', 'display_name', 'sort_order');
?>
<div class="container-fluid p-4" id="memory-config-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="memory-config-header">
    <div id="memory-config-header-text">
      <h4 class="fw-bold mb-1" id="memory-config-title"><i class="feather-cpu me-2"></i>Memory Config</h4>
      <p class="text-muted mb-0" id="memory-config-subtitle">MaluDB setup &mdash; model, embedding, and prompt configuration per namespace</p>
    </div>
    <div class="w-25" id="memory-config-namespace-wrap">
      <div class="input-group input-group-sm" id="memory-config-namespace-group">
        <span class="input-group-text">Namespace</span>
        <input type="text" class="form-control" name="namespace"
               value="<?php echo htmlspecialchars($namespace); ?>"
               hx-get="<?php echo $selfUrl; ?>"
               hx-trigger="change"
               hx-target="#page-content" hx-swap="innerHTML"
               id="memory-config-namespace">
      </div>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="memory-config-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load config: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="row g-3" id="memory-config-row">

    <!-- Current configuration -->
    <div class="col-12 col-xl-5" id="memory-config-current-col">
      <div class="card stretch stretch-full" id="memory-config-current-card">
        <div class="card-header" id="memory-config-current-header">
          <h6 class="fw-bold mb-0" id="memory-config-current-title">Current Configuration &mdash; "<?php echo htmlspecialchars($namespace); ?>"</h6>
        </div>
        <div class="card-body" id="memory-config-current-body">
          <?php if ($config === null): ?>
          <div class="text-center text-muted py-4" id="memory-config-none">
            <i class="feather-cpu fs-3 d-block mb-2"></i>
            No configuration set for this namespace yet.
          </div>
          <?php else: ?>
          <table class="table table-sm mb-0" id="memory-config-current-table">
            <tbody id="memory-config-current-tbody">
              <?php
              $fields = [
                  'extraction_alias'     => 'Extraction Alias',
                  'model_identifier'     => 'Model',
                  'provider_kind'        => 'Provider Kind',
                  'base_url'             => 'Base URL',
                  'secret_ref'           => 'Secret (name only)',
                  'embedding_model'      => 'Embedding Model',
                  'default_subject_type' => 'Default Subject Type',
                  'default_provenance'   => 'Default Provenance',
              ];
              foreach ($fields as $key => $fieldLabel): ?>
              <tr>
                <td class="text-muted fs-12 w-40"><?php echo $fieldLabel; ?></td>
                <td class="fw-semibold fs-12"><?php echo htmlspecialchars((string)($config[$key] ?? '—')); ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!empty($config['prompt_template'])): ?>
              <tr>
                <td class="text-muted fs-12">Prompt Template</td>
                <td><pre class="fs-12 mb-0" style="white-space: pre-wrap;" id="memory-config-prompt-preview"><?php echo htmlspecialchars(mb_strimwidth((string)$config['prompt_template'], 0, 400, '…')); ?></pre></td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Configure form -->
    <div class="col-12 col-xl-7" id="memory-config-form-col">
      <div class="card" id="memory-config-form-card">
        <div class="card-header" id="memory-config-form-header">
          <h6 class="fw-bold mb-0" id="memory-config-form-title">Configure</h6>
        </div>
        <div class="card-body" id="memory-config-form-body">
          <form hx-post="<?php echo $selfUrl; ?>" hx-target="#page-content" hx-swap="innerHTML" id="memory-config-form">
            <input type="hidden" name="namespace" value="<?php echo htmlspecialchars($namespace); ?>">

            <div class="fw-semibold fs-12 text-muted mb-2" id="mc-section-secret">SECRET</div>
            <div class="row" id="memory-config-secret-row">
              <div class="col-md-6 mb-3" id="mc-field-secretname-wrap">
                <label class="form-label" for="mc-field-secretname">Secret Name</label>
                <input type="text" class="form-control" name="secret_name" id="mc-field-secretname"
                       value="<?php echo htmlspecialchars((string)($config['secret_ref'] ?? '')); ?>"
                       placeholder="e.g. openai-prod">
              </div>
              <div class="col-md-6 mb-3" id="mc-field-token-wrap">
                <label class="form-label" for="mc-field-token">API Token</label>
                <input type="password" class="form-control" name="token" id="mc-field-token"
                       placeholder="Stored encrypted; leave blank to keep current" autocomplete="new-password">
              </div>
            </div>

            <div class="fw-semibold fs-12 text-muted mb-2" id="mc-section-provider">PROVIDER</div>
            <div class="row" id="memory-config-provider-row">
              <div class="col-md-4 mb-3" id="mc-field-provname-wrap">
                <label class="form-label" for="mc-field-provname">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="provider_name" id="mc-field-provname" placeholder="e.g. openai" required>
              </div>
              <div class="col-md-4 mb-3" id="mc-field-provkind-wrap">
                <label class="form-label" for="mc-field-provkind">Kind <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="provider_kind" id="mc-field-provkind"
                       value="<?php echo htmlspecialchars((string)($config['provider_kind'] ?? '')); ?>"
                       placeholder="e.g. openai_compatible" required>
              </div>
              <div class="col-md-4 mb-3" id="mc-field-provsens-wrap">
                <label class="form-label" for="mc-field-provsens">Data Sensitivity</label>
                <select class="form-select" name="provider_sensitivity" id="mc-field-provsens">
                  <?php foreach (MC_SENSITIVITIES as $s): ?>
                  <option value="<?php echo $s; ?>" <?php echo $s === 'internal' ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="fw-semibold fs-12 text-muted mb-2" id="mc-section-alias">MODEL ALIAS</div>
            <div class="row" id="memory-config-alias-row">
              <div class="col-md-4 mb-3" id="mc-field-aliasname-wrap">
                <label class="form-label" for="mc-field-aliasname">Alias <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="alias_name" id="mc-field-aliasname"
                       value="<?php echo htmlspecialchars((string)($config['extraction_alias'] ?? '')); ?>"
                       placeholder="e.g. extraction-default" required>
              </div>
              <div class="col-md-5 mb-3" id="mc-field-aliasmodel-wrap">
                <label class="form-label" for="mc-field-aliasmodel">Model Identifier <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="alias_model" id="mc-field-aliasmodel"
                       value="<?php echo htmlspecialchars((string)($config['model_identifier'] ?? '')); ?>"
                       placeholder="e.g. gpt-4.1-mini" required>
              </div>
              <div class="col-md-3 mb-3" id="mc-field-aliasctx-wrap">
                <label class="form-label" for="mc-field-aliasctx">Context Length</label>
                <input type="number" class="form-control" name="alias_context_length" id="mc-field-aliasctx" placeholder="optional">
              </div>
            </div>
            <div class="row" id="memory-config-url-row">
              <div class="col-md-7 mb-3" id="mc-field-baseurl-wrap">
                <label class="form-label" for="mc-field-baseurl">Base URL <span class="text-danger">*</span></label>
                <input type="url" class="form-control" name="base_url" id="mc-field-baseurl"
                       value="<?php echo htmlspecialchars((string)($config['base_url'] ?? '')); ?>"
                       placeholder="https://api.example.com/v1" required>
              </div>
              <div class="col-md-5 mb-3" id="mc-field-embedding-wrap">
                <label class="form-label" for="mc-field-embedding">Embedding Model <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="embedding_model" id="mc-field-embedding"
                       value="<?php echo htmlspecialchars((string)($config['embedding_model'] ?? '')); ?>"
                       placeholder="e.g. text-embedding-3-small" required>
              </div>
            </div>

            <div class="fw-semibold fs-12 text-muted mb-2" id="mc-section-extraction">EXTRACTION</div>
            <div class="mb-3" id="mc-field-prompt-wrap">
              <label class="form-label" for="mc-field-prompt">Prompt Template <span class="text-muted fs-12">(must contain {{chunk}})</span></label>
              <textarea class="form-control font-monospace" name="prompt_template" id="mc-field-prompt" rows="4"
                        placeholder="Optional — extraction prompt with the {{chunk}} placeholder"><?php echo htmlspecialchars((string)($config['prompt_template'] ?? '')); ?></textarea>
            </div>
            <div class="row" id="memory-config-defaults-row">
              <div class="col-md-6 mb-3" id="mc-field-defsubject-wrap">
                <label class="form-label" for="mc-field-defsubject">Default Subject Type</label>
                <select class="form-select" name="default_subject_type" id="mc-field-defsubject">
                  <?php foreach ($subjectTypes as $value => $optLabel): ?>
                  <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($config['default_subject_type'] ?? 'other') === $value ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($optLabel); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-3" id="mc-field-defprov-wrap">
                <label class="form-label" for="mc-field-defprov">Default Provenance</label>
                <select class="form-select" name="default_provenance" id="mc-field-defprov">
                  <?php foreach (MC_PROVENANCES as $p): ?>
                  <option value="<?php echo $p; ?>" <?php echo ($config['default_provenance'] ?? 'suggested') === $p ? 'selected' : ''; ?>><?php echo ucfirst($p); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="text-end" id="memory-config-form-actions">
              <button type="submit" class="btn btn-primary" id="memory-config-form-submit"><i class="feather-save me-1"></i>Save Configuration</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

</div>
