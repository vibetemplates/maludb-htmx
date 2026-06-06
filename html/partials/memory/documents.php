<?php
/**
 * Memory Elements — Documents (CRUD placeholder, MaluDB SQL pending)
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$memKey = 'documents';
$memTitle = 'Documents';
$memSingular = 'Document';
$memIcon = 'feather-file-text';
$memColumns = ['Name', 'Type', 'Source', 'Updated'];

require __DIR__ . '/_scaffold.php';
