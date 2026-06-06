<?php
/**
 * MaluDB Setup — Verb Types (CRUD on maludb_verb_type, ← /v1/verb-types)
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';

requireAuth();
$pdo = db();

$rgKey              = 'verb-types';
$rgTitle            = 'Verb Types';
$rgSingular         = 'Verb Type';
$rgIcon             = 'feather-zap';
$rgTable            = 'maludb_verb_type';
$rgTypeCol          = 'verb_type';
$rgHasSemanticClass = true;

require __DIR__ . '/_registry-crud.php';
