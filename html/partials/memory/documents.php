<?php
/**
 * Memory Elements — Documents (wired to MaluDB)
 *
 * Adapted from /v1/documents and /v1/documents/{id} (requirements.md §4.4).
 * Bytes live in maludb_source_package (bytea); maludb_document holds metadata.
 *
 * Upload strategy:
 *  - Text files (valid UTF-8) go through the maludb_upload_document(...) facade,
 *    which also wires project/subject graph links (p_projects / p_subjects).
 *  - Binary files use the v1 direct-INSERT path (source package + document);
 *    graph links are skipped for binaries (facade is text-based).
 * Delete removes the document's svpor graph edges, the document, and its
 * source package — matching /v1/documents/{id} DELETE.
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';
require_once __DIR__ . '/_db.php';

requireAuth();

$pdo = db();
$action = $_REQUEST['action'] ?? '';
$selfUrl = '/partials/memory/documents.php';

function documentsAlert(string $type, string $message): string
{
    $icon = $type === 'success' ? 'feather-check-circle' : 'feather-alert-triangle';
    return '<div class="alert alert-' . $type . ' mb-3" id="documents-action-alert">'
         . '<i class="' . $icon . ' me-2"></i>' . htmlspecialchars($message) . '</div>';
}

/** "12.3 KB"-style size label. */
function documentSize(?int $bytes): string
{
    if ($bytes === null) return '—';
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

/** Build a PostgreSQL text[] literal from a comma-separated string. */
function pgTextArray(string $csv): string
{
    $items = [];
    foreach (explode(',', $csv) as $n) {
        $n = trim($n);
        if ($n !== '') {
            $items[$n] = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $n) . '"';
        }
    }
    return '{' . implode(',', $items) . '}';
}

/** Render the documents list (optionally filtered by $q) with an optional alert. */
function renderDocumentsList(PDO $pdo, string $selfUrl, string $q = '', string $alertHtml = ''): void
{
    $rows = [];
    $loadError = '';
    try {
        $where  = '';
        $params = [];
        if ($q !== '') {
            $where    = "WHERE d.title ILIKE ?";
            $params[] = '%' . $q . '%';
        }
        $stmt = $pdo->prepare(
            "SELECT d.document_id  AS id,
                    d.title,
                    d.media_type,
                    d.document_type,
                    d.metadata_jsonb->>'description' AS description,
                    sp.content_size,
                    d.created_at
               FROM maludb_document d
               LEFT JOIN maludb_source_package sp ON sp.source_package_id = d.source_package_id
               $where
              ORDER BY d.created_at DESC NULLS LAST, d.document_id DESC
              LIMIT 200"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $loadError = $e->getMessage();
    }
    ?>
<div class="container-fluid p-4" id="documents-container">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4" id="documents-header">
    <div id="documents-header-text">
      <h4 class="fw-bold mb-1" id="documents-title"><i class="feather-file-text me-2"></i>Documents</h4>
      <p class="text-muted mb-0" id="documents-subtitle">Memory element &mdash; source evidence with provenance</p>
    </div>
    <div id="documents-header-actions">
      <button class="btn btn-primary"
              hx-get="<?php echo $selfUrl; ?>?action=form"
              hx-target="#modal-container"
              hx-swap="innerHTML"
              id="documents-btn-new">
        <i class="feather-upload me-1"></i>Upload Document
      </button>
    </div>
  </div>

  <?php echo $alertHtml; ?>
  <?php if ($loadError !== ''): ?>
  <div class="alert alert-danger" id="documents-load-error">
    <i class="feather-alert-triangle me-2"></i>Could not load documents: <?php echo htmlspecialchars($loadError); ?>
  </div>
  <?php endif; ?>

  <!-- List card -->
  <div class="card" id="documents-card">
    <div class="card-header d-flex align-items-center justify-content-between" id="documents-card-header">
      <h6 class="fw-bold mb-0" id="documents-card-title">All Documents</h6>
      <div class="w-25" id="documents-search-wrap">
        <input type="search" class="form-control form-control-sm" name="q"
               value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Search documents&hellip;"
               hx-get="<?php echo $selfUrl; ?>"
               hx-trigger="input changed delay:400ms, search"
               hx-target="#page-content"
               hx-swap="innerHTML"
               id="documents-search">
      </div>
    </div>
    <div class="card-body p-0" id="documents-card-body">
      <div class="table-responsive" id="documents-table-wrap">
        <table class="table table-hover mb-0" id="documents-table">
          <thead id="documents-table-head">
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Media</th>
              <th>Size</th>
              <th>Created</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="documents-table-body">
            <?php if (empty($rows)): ?>
            <tr id="documents-row-empty">
              <td colspan="6" class="text-center text-muted py-5">
                <i class="feather-file-text fs-3 d-block mb-2"></i>
                <?php echo $q !== '' ? 'No documents match your search.' : 'No documents yet. Click Upload Document to add one.'; ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($rows as $row):
                $rowId = (int)$row['id'];
                $created = !empty($row['created_at']) ? date('M j, Y', strtotime($row['created_at'])) : '—';
            ?>
            <tr id="documents-row-<?php echo $rowId; ?>">
              <td class="fw-semibold"><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
              <td>
                <?php if (!empty($row['document_type'])): ?>
                <span class="badge bg-soft-info text-info"><?php echo htmlspecialchars($row['document_type']); ?></span>
                <?php else: ?>
                <span class="text-muted fs-12">&mdash;</span>
                <?php endif; ?>
              </td>
              <td class="fs-12 text-muted"><?php echo htmlspecialchars($row['media_type'] ?? ''); ?></td>
              <td class="fs-12"><?php echo documentSize($row['content_size'] === null ? null : (int)$row['content_size']); ?></td>
              <td class="fs-12"><?php echo htmlspecialchars($created); ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-icon" title="Delete"
                        hx-post="<?php echo $selfUrl; ?>?action=delete"
                        hx-vals='{"id": "<?php echo $rowId; ?>"}'
                        hx-confirm="Delete this document and its source package?"
                        hx-target="#page-content" hx-swap="innerHTML"
                        id="documents-row-<?php echo $rowId; ?>-delete"><i class="feather-trash-2"></i></button>
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
 * UPLOAD — POST action=save (multipart/form-data)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $alert = '';

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['file']['error'] ?? 'missing';
        $alert = documentsAlert('danger', ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
            ? 'Uploaded file exceeds the size limit.'
            : 'File upload failed — choose a file and try again.');
    } else {
        $bytes = file_get_contents($_FILES['file']['tmp_name']);
        if ($bytes === false) {
            $alert = documentsAlert('danger', 'Could not read the uploaded file.');
        } else {
            $filename     = trim($_POST['title'] ?? '') ?: ($_FILES['file']['name'] ?? 'upload');
            $mime         = trim((string)($_FILES['file']['type'] ?? '')) ?: 'application/octet-stream';
            $description  = trim($_POST['description'] ?? '');
            $description  = $description === '' ? null : $description;
            $documentType = trim($_POST['document_type'] ?? '');
            $documentType = $documentType === '' ? null : $documentType;
            $projectsArr  = pgTextArray($_POST['projects'] ?? '');
            $subjectsArr  = pgTextArray($_POST['subjects'] ?? '');
            $isText       = mb_check_encoding($bytes, 'UTF-8');

            try {
                if ($isText) {
                    // Text document — the facade stores it and wires the graph links.
                    maludbTxCore($pdo, function (PDO $pdo) use ($filename, $bytes, $mime, $projectsArr, $subjectsArr, $description, $documentType) {
                        $stmt = $pdo->prepare(
                            "SELECT maludb_upload_document(
                                        p_title          => ?,
                                        p_content_text   => ?,
                                        p_source_type    => 'document',
                                        p_media_type     => ?,
                                        p_projects       => ?::text[],
                                        p_subjects       => ?::text[],
                                        p_metadata_jsonb => ?::jsonb,
                                        p_document_type  => ?
                                    ) AS id"
                        );
                        $stmt->execute([
                            $filename, $bytes, $mime, $projectsArr, $subjectsArr,
                            json_encode(['description' => $description, 'filename' => $filename]),
                            $documentType,
                        ]);
                        return (int)$stmt->fetchColumn();
                    });
                    $alert = documentsAlert('success', 'Document "' . $filename . '" uploaded and linked.');
                } else {
                    // Binary document — v1 direct-INSERT path (bytea LOB bind); no graph links.
                    $size = strlen($bytes);
                    $hash = hash('sha256', $bytes);
                    $stmt = $pdo->prepare(
                        "INSERT INTO maludb_source_package
                             (source_type, content_bytes, media_type, content_size, content_hash, ingested_at)
                         VALUES ('document', ?, ?, ?, ?, now()) RETURNING source_package_id"
                    );
                    $stmt->bindValue(1, $bytes, PDO::PARAM_LOB);
                    $stmt->bindValue(2, $mime);
                    $stmt->bindValue(3, $size, PDO::PARAM_INT);
                    $stmt->bindValue(4, $hash);
                    $stmt->execute();
                    $spid = (int)$stmt->fetchColumn();

                    $stmt = $pdo->prepare(
                        "INSERT INTO maludb_document
                             (source_package_id, title, source_type, media_type, document_type, metadata_jsonb, created_at)
                         VALUES (?, ?, 'document', ?, ?, ?, now())"
                    );
                    $stmt->execute([
                        $spid, $filename, $mime, $documentType,
                        json_encode(['description' => $description, 'filename' => $filename]),
                    ]);
                    $alert = documentsAlert('success', 'Binary document "' . $filename . '" uploaded (graph links apply to text documents only).');
                }
            } catch (Exception $e) {
                $alert = documentsAlert('danger', 'Upload failed: ' . $e->getMessage());
            }
        }
    }

    header('HX-Trigger-After-Swap: closeModal');
    header('HX-Retarget: #page-content');
    renderDocumentsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * DELETE — POST action=delete  (/v1/documents/{id} DELETE)
 * ------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT source_package_id FROM maludb_document WHERE document_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $alert = documentsAlert('danger', 'Document not found.');
        } else {
            // Remove graph edges first (they do not cascade), then document + package.
            maludbTxCore($pdo, function (PDO $pdo) use ($id) {
                $stmt = $pdo->prepare(
                    "DELETE FROM maludb_svpor_statement WHERE subject_kind = 'document' AND subject_id = ?"
                );
                $stmt->execute([$id]);
            });
            $pdo->prepare("DELETE FROM maludb_document WHERE document_id = ?")->execute([$id]);
            if ($row['source_package_id'] !== null) {
                $pdo->prepare("DELETE FROM maludb_source_package WHERE source_package_id = ?")
                    ->execute([$row['source_package_id']]);
            }
            $alert = documentsAlert('success', 'Document deleted.');
        }
    } catch (Exception $e) {
        $alert = documentsAlert('danger', 'Delete failed: ' . $e->getMessage());
    }

    renderDocumentsList($pdo, $selfUrl, '', $alert);
    exit;
}

/* ---------------------------------------------------------------------
 * FORM (upload modal) — GET action=form
 * ------------------------------------------------------------------- */
if ($action === 'form') {
    $typeOptions = maludbTypeOptions($pdo, 'maludb_document_type', 'document_type', 'document_type', 'display_order');
    ?>
    <div class="modal fade" tabindex="-1" id="documents-modal">
      <div class="modal-dialog modal-dialog-centered modal-lg" id="documents-modal-dialog">
        <div class="modal-content" id="documents-modal-content">
          <div class="modal-header" id="documents-modal-header">
            <h5 class="modal-title" id="documents-modal-title">
              <i class="feather-upload me-2"></i>Upload Document
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form hx-post="<?php echo $selfUrl; ?>?action=save"
                hx-encoding="multipart/form-data"
                hx-target="#page-content"
                hx-swap="innerHTML"
                id="documents-form">
            <div class="modal-body" id="documents-modal-body">
              <div class="mb-3" id="documents-field-file-wrap">
                <label class="form-label" for="documents-field-file">File <span class="text-danger">*</span></label>
                <input type="file" class="form-control" name="file" id="documents-field-file" required>
              </div>
              <div class="row" id="documents-form-row-1">
                <div class="col-md-7 mb-3" id="documents-field-title-wrap">
                  <label class="form-label" for="documents-field-title">Title</label>
                  <input type="text" class="form-control" name="title" id="documents-field-title"
                         placeholder="Defaults to the file name">
                </div>
                <div class="col-md-5 mb-3" id="documents-field-type-wrap">
                  <label class="form-label" for="documents-field-type">Document Type</label>
                  <?php if (!empty($typeOptions)): ?>
                  <select class="form-select" name="document_type" id="documents-field-type">
                    <option value="">&mdash; none &mdash;</option>
                    <?php foreach ($typeOptions as $value => $optLabel): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($optLabel); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php else: ?>
                  <input type="text" class="form-control" name="document_type" id="documents-field-type" placeholder="Document type">
                  <?php endif; ?>
                </div>
              </div>
              <div class="mb-3" id="documents-field-description-wrap">
                <label class="form-label" for="documents-field-description">Description</label>
                <textarea class="form-control" name="description" id="documents-field-description" rows="2"
                          placeholder="Optional description"></textarea>
              </div>
              <div class="row" id="documents-form-row-2">
                <div class="col-md-6 mb-3" id="documents-field-projects-wrap">
                  <label class="form-label" for="documents-field-projects">Link to Projects</label>
                  <input type="text" class="form-control" name="projects" id="documents-field-projects"
                         placeholder="Comma-separated project names">
                </div>
                <div class="col-md-6 mb-3" id="documents-field-subjects-wrap">
                  <label class="form-label" for="documents-field-subjects">Link to Subjects</label>
                  <input type="text" class="form-control" name="subjects" id="documents-field-subjects"
                         placeholder="Comma-separated subject names">
                </div>
              </div>
              <div class="alert alert-light border fs-12 mb-0" id="documents-form-note">
                <i class="feather-info me-1"></i>Text files are stored via the maludb_upload_document facade and
                wired into the memory graph. Binary files are stored as source packages without graph links.
              </div>
            </div>
            <div class="modal-footer" id="documents-modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="documents-form-cancel">Cancel</button>
              <button type="submit" class="btn btn-primary" id="documents-form-submit">Upload</button>
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
renderDocumentsList($pdo, $selfUrl, $q);
