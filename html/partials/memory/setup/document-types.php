<?php
/**
 * MaluDB Setup — Document Types (CRUD on maludb_document_type, ← /v1/document-types)
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';

requireAuth();
$pdo = db();

$stKey      = 'document-types';
$stTitle    = 'Document Types';
$stSingular = 'Document Type';
$stIcon     = 'feather-file-text';
$stTable    = 'maludb_document_type';
$stIdCol    = 'document_type_id';
$stLabelCol = 'document_type';

require __DIR__ . '/_type-crud.php';
