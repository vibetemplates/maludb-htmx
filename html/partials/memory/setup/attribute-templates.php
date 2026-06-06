<?php
/**
 * MaluDB Setup — Attribute Templates (the form catalog)
 *
 * Adapted from /v1/attribute-templates (maludb_core 0.83.0+). The typed-property
 * catalog that drives forms: which attributes apply to a given node/edge type.
 *   applies_to  ∈ (episode_type, document_type, subject_type, verb)
 *   value_type  ∈ (timestamp, tstzrange, numeric, text, jsonb, reference)
 *   requirement ∈ (required, recommended, optional)
 * Create goes through maludb_attribute_template_create(...); the API surface has
 * no PATCH — re-create to change. Delete via maludb_attribute_template_delete or
 * the writable view. All access runs inside maludbTxCore().
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';
require_once __DIR__ . '/../_db.php';

requireAuth();

$pdo = db();
$action = $_REQUEST['action'] ?? '';
$selfUrl = '/partials/memory/setup/attribute-templates.php';

const AT_APPLIES_TO   = ['episode_type', 'document_type', 'subject_type', 'verb'];
const AT_VALUE_TYPES  = ['timestamp', 'tstzrange', 'numeric', 'text', 'jsonb', 'reference'];
const AT_REQUIREMENTS = ['required', 'recommended', 'optional'];

function atAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="attribute-templates-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** Render the templates list (filtered by $appliesTo) with an optional alert. */
function renderTemplatesList(PDO $pdo, string $selfUrl, string $appliesTo = '', string $alertHtml = ''): void
{
    $rows = [];
    $loadError = '';
    try {
        $where  = '';
        $params = [];
        if ($appliesTo !== '') {
            $where    = "WHERE applies_to = ?";
            $params[] = $appliesTo;
        }
        $rows = maludbTxCore($pdo, function (PDO $pdo) use ($where, $params) {
            $stmt = $pdo->prepare(
                "SELECT template_id AS id, applies_to, type_value, attr_name, value_type,
                        requirement, label, unit, display_order
                   FROM maludb_attribute_template
                   $where
                  ORDER BY applies_to, type_value, display_order NULLS LAST, attr_name
                  LIMIT 500"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        });
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="attribute-templates-container">

  <div class="d-flex align-items-center justify-content-between mb-4" id="attribute-templates-header">
    <div id="attribute-templates-header-text">
      <h4 class="fw-bold mb-1" id="attribute-templates-title"><i class="feather-sliders me-2"></i>Attribute Templates</h4>
      <p class="text-muted mb-0" id="attribute-templates-subtitle">MaluDB setup &mdash; the typed-property catalog that drives forms (no edit; re-create to change)</p>
    </div>
    <div id="attribute-templates-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container" hx-swap="innerHTML"
              id="attribute-templates-btn-new">
        <i class="feather-plus me-1"></i>New Template
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="attribute-templates-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load templates: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <div class="card" id="attribute-templates-card">
    <div class="card-header d-flex align-items-center justify-content-between" id="attribute-templates-card-header">
      <h6 class="fw-bold mb-0" id="attribute-templates-card-title">Catalog</h6>
      <select class="form-select form-select-sm w-auto" name="applies_to" id="attribute-templates-filter"
              hx-get="<?php echo $selfUrl; ?>"
              hx-target="#page-content" hx-swap="innerHTML">
        <option value="">All applies-to</option>
        <?php foreach (AT_APPLIES_TO as $a): ?>
        <option value="<?php echo $a; ?>" <?php echo $appliesTo === $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="card-body p-0" id="attribute-templates-card-body">
      <div class="table-responsive" id="attribute-templates-table-wrap">
        <table class="table table-hover mb-0" id="attribute-templates-table">
          <thead id="attribute-templates-table-head">
            <tr>
              <th>Applies To</th>
              <th>Type Value</th>
              <th>Attribute</th>
              <th>Value Type</th>
              <th>Requirement</th>
              <th>Label</th>
              <th>Unit</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="attribute-templates-table-body">
            <?php if (empty($rows)): ?>
            <tr id="attribute-templates-row-empty">
              <td colspan="8" class="text-center text-muted py-5">
                <i class="feather-sliders fs-3 d-block mb-2"></i>No attribute templates<?php echo $appliesTo !== '' ? ' for this filter' : ''; ?>.
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row):
                $rowId = (int)$row['id'];
                $reqBadge = [
                    'required'    => 'bg-soft-danger text-danger',
                    'recommended' => 'bg-soft-warning text-warning',
                    'optional'    => 'bg-soft-secondary text-secondary',
                ][$row['requirement'] ?? ''] ?? 'bg-soft-secondary text-secondary';
            ?>
            <tr id="attribute-templates-row-<?php echo $rowId; ?>">
              <td><span class="badge bg-soft-primary text-primary"><?php echo htmlspecialchars($row['applies_to']); ?></span></td>
              <td><code><?php echo htmlspecialchars($row['type_value']); ?></code></td>
              <td class="fw-semibold"><?php echo htmlspecialchars($row['attr_name']); ?></td>
              <td class="fs-12"><?php echo htmlspecialchars($row['value_type']); ?></td>
              <td><span class="badge <?php echo $reqBadge; ?>"><?php echo htmlspecialchars($row['requirement']); ?></span></td>
              <td class="fs-12"><?php echo htmlspecialchars($row['label'] ?? ''); ?></td>
              <td class="fs-12"><?php echo htmlspecialchars($row['unit'] ?? ''); ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-icon" title="Delete"
                        hx-post="<?php echo $selfUrl; ?>?action=delete"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-confirm="Delete this attribute template?"
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="attribute-templates-row-<?php echo $rowId; ?>-delete"><i class="feather-trash-2"></i></button>
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
 * CREATE — POST action=save (via maludb_attribute_template_create facade)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $appliesTo   = trim($_POST['applies_to'] ?? '');
    $typeValue   = trim($_POST['type_value'] ?? '');
    $attrName    = trim($_POST['attr_name'] ?? '');
    $valueType   = trim($_POST['value_type'] ?? '');
    $requirement = trim($_POST['requirement'] ?? '') ?: 'optional';
    $label       = trim($_POST['label'] ?? '');
    $label       = $label === '' ? null : $label;
    $description = trim($_POST['description'] ?? '');
    $description = $description === '' ? null : $description;
    $unit        = trim($_POST['unit'] ?? '');
    $unit        = $unit === '' ? null : $unit;
    $displayOrder = trim($_POST['display_order'] ?? '');
    $displayOrder = $displayOrder === '' ? null : (int)$displayOrder;

    $missing = [];
    foreach (['applies_to' => $appliesTo, 'type_value' => $typeValue,
              'attr_name' => $attrName, 'value_type' => $valueType] as $f => $v) {
        if ($v === '') $missing[] = $f;
    }

    if ($missing) {
        $alert = atAlert('danger', 'Required fields missing: ' . implode(', ', $missing) . '.');
    } else {
        try {
            maludbTxCore($pdo, function (PDO $pdo) use ($appliesTo, $typeValue, $attrName, $valueType, $requirement, $label, $description, $unit, $displayOrder) {
                $stmt = $pdo->prepare(
                    "SELECT maludb_attribute_template_create(
                                p_applies_to    => ?, p_type_value => ?, p_attr_name => ?, p_value_type => ?,
                                p_requirement   => ?, p_label => ?, p_description => ?, p_unit => ?,
                                p_allowed_values => NULL::jsonb, p_default_value => NULL::jsonb,
                                p_display_order => ?
                            ) AS id"
                );
                $stmt->execute([$appliesTo, $typeValue, $attrName, $valueType, $requirement, $label, $description, $unit, $displayOrder]);
                return (int)$stmt->fetchColumn();
            });
            $alert = atAlert('success', 'Attribute template "' . $attrName . '" created.');
        } catch (Exception $e) {
            $alert = atAlert('danger', 'Create failed: ' . $e->getMessage());
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    renderTemplatesList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * DELETE — POST action=delete
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        maludbTxCore($pdo, function (PDO $pdo) use ($id) {
            $stmt = $pdo->prepare("SELECT maludb_attribute_template_delete(?)");
            $stmt->execute([$id]);
        });
        $alert = atAlert('success', 'Attribute template deleted.');
    } catch (Exception $e) {
        $alert = atAlert('danger', 'Delete failed: ' . $e->getMessage());
    }
    renderTemplatesList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (create modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    ?>
    <div class="modal fade" tabindex="-1" id="attribute-templates-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="attribute-templates-modal-dialog">
        <div class="modal-content" id="attribute-templates-modal-content">
          <div class="modal-header" id="attribute-templates-modal-header">
            <h5 class="modal-title" id="attribute-templates-modal-title">
              <i class="feather-sliders me-2"></i>New Attribute Template
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-target="#page-content" hx-swap="innerHTML"
                id="attribute-templates-form">
            <div class="modal-body" id="attribute-templates-modal-body">
              <div class="row" id="attribute-templates-form-row-1">
                <div class="col-md-4 mb-3" id="at-field-applies-wrap">
                  <label class="form-label" for="at-field-applies">Applies To <span class="text-danger">*</span></label>
                  <select class="form-select" name="applies_to" id="at-field-applies" required>
                    <option value="">&mdash; choose &mdash;</option>
                    <?php foreach (AT_APPLIES_TO as $a): ?>
                    <option value="<?php echo $a; ?>"><?php echo $a; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4 mb-3" id="at-field-typevalue-wrap">
                  <label class="form-label" for="at-field-typevalue">Type Value <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="type_value" id="at-field-typevalue"
                         placeholder="e.g. Meeting, contract, person" required>
                </div>
                <div class="col-md-4 mb-3" id="at-field-attrname-wrap">
                  <label class="form-label" for="at-field-attrname">Attribute Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="attr_name" id="at-field-attrname"
                         placeholder="e.g. due_date, amount" required>
                </div>
              </div>
              <div class="row" id="attribute-templates-form-row-2">
                <div class="col-md-4 mb-3" id="at-field-valuetype-wrap">
                  <label class="form-label" for="at-field-valuetype">Value Type <span class="text-danger">*</span></label>
                  <select class="form-select" name="value_type" id="at-field-valuetype" required>
                    <option value="">&mdash; choose &mdash;</option>
                    <?php foreach (AT_VALUE_TYPES as $v): ?>
                    <option value="<?php echo $v; ?>"><?php echo $v; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4 mb-3" id="at-field-requirement-wrap">
                  <label class="form-label" for="at-field-requirement">Requirement</label>
                  <select class="form-select" name="requirement" id="at-field-requirement">
                    <?php foreach (AT_REQUIREMENTS as $r): ?>
                    <option value="<?php echo $r; ?>" <?php echo $r === 'optional' ? 'selected' : ''; ?>><?php echo $r; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4 mb-3" id="at-field-order-wrap">
                  <label class="form-label" for="at-field-order">Display Order</label>
                  <input type="number" class="form-control" name="display_order" id="at-field-order">
                </div>
              </div>
              <div class="row" id="attribute-templates-form-row-3">
                <div class="col-md-8 mb-3" id="at-field-label-wrap">
                  <label class="form-label" for="at-field-label">Label</label>
                  <input type="text" class="form-control" name="label" id="at-field-label" placeholder="Human-readable form label">
                </div>
                <div class="col-md-4 mb-3" id="at-field-unit-wrap">
                  <label class="form-label" for="at-field-unit">Unit</label>
                  <input type="text" class="form-control" name="unit" id="at-field-unit" placeholder="e.g. USD, hours">
                </div>
              </div>
              <div class="mb-0" id="at-field-description-wrap">
                <label class="form-label" for="at-field-description">Description</label>
                <textarea class="form-control" name="description" id="at-field-description" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer" id="attribute-templates-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="attribute-templates-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="attribute-templates-form-submit">Create</button>
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
$appliesTo = trim($_GET['applies_to'] ?? '');
renderTemplatesList($pdo, $selfUrl, $appliesTo);
