<?php
/**
 * MaluDB Setup — Episode Types (CRUD on maludb_episode_type, ← /v1/episode-types)
 */
require_once __DIR__ . '/../../../../helpers/auth.php';
require_once __DIR__ . '/../../../../helpers/db.php';

requireAuth();
$pdo = db();

$stKey      = 'episode-types';
$stTitle    = 'Episode Types';
$stSingular = 'Episode Type';
$stIcon     = 'feather-activity';
$stTable    = 'maludb_episode_type';
$stIdCol    = 'episode_type_id';
$stLabelCol = 'episode_type';

require __DIR__ . '/_type-crud.php';
