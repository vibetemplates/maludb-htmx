<?php
/**
 * MaluDB Setup — Subject Types (CRUD on maludb_subject_type, ← /v1/subject-types)
 *
 * Values are trigger-enforced on maludb_subject rows.
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';

requireAuth();
$pdo = db();

$rgKey              = 'subject-types';
$rgTitle            = 'Subject Types';
$rgSingular         = 'Subject Type';
$rgIcon             = 'feather-box';
$rgTable            = 'maludb_subject_type';
$rgTypeCol          = 'subject_type';
$rgHasSemanticClass = false;

require __DIR__ . '/_registry-crud.php';
