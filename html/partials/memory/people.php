<?php
/**
 * Memory Elements — People (CRUD placeholder, MaluDB SQL pending)
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$memKey = 'people';
$memTitle = 'People';
$memSingular = 'Person';
$memIcon = 'feather-users';
$memColumns = ['Name', 'Role', 'Organization', 'Updated'];

require __DIR__ . '/_scaffold.php';
