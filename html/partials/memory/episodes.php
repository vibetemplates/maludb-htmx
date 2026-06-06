<?php
/**
 * Memory Elements — Events/Episodes (CRUD placeholder, MaluDB SQL pending)
 */
require_once __DIR__ . '/../../../helpers/auth.php';

requireAuth();

$memKey = 'episodes';
$memTitle = 'Events/Episodes';
$memSingular = 'Episode';
$memIcon = 'feather-activity';
$memColumns = ['Name', 'Occurred', 'Participants', 'Updated'];

require __DIR__ . '/_scaffold.php';
