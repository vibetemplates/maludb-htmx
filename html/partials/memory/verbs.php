<?php
/**
 * Memory Elements — Verbs/Actions (CRUD placeholder, MaluDB SQL pending)
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$memKey = 'verbs';
$memTitle = 'Verbs/Actions';
$memSingular = 'Verb';
$memIcon = 'feather-zap';
$memColumns = ['Name', 'Category', 'Usage Count', 'Updated'];

require __DIR__ . '/_scaffold.php';
