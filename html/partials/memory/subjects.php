<?php
/**
 * Memory Elements — Subjects/Things (CRUD placeholder, MaluDB SQL pending)
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$memKey = 'subjects';
$memTitle = 'Subjects/Things';
$memSingular = 'Subject';
$memIcon = 'feather-box';
$memColumns = ['Name', 'Category', 'References', 'Updated'];

require __DIR__ . '/_scaffold.php';
