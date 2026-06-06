<?php
/**
 * Memory Elements — Projects (CRUD placeholder, MaluDB SQL pending)
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$memKey = 'projects';
$memTitle = 'Projects';
$memSingular = 'Project';
$memIcon = 'feather-folder';
$memColumns = ['Name', 'Description', 'Status', 'Updated'];

require __DIR__ . '/_scaffold.php';
