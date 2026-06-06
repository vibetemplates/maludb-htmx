<?php
/**
 * GET /api/v1/availability-rules.php — Recurring weekly availability windows
 */

require_once __DIR__ . '/_bootstrap.php';

api_require_method('GET');
$auth = api_authenticate();
$rid  = $auth['company_id'];

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT * FROM professional_availability_rules
     WHERE company_id = ? AND is_active = 1
     ORDER BY weekday ASC, start_time ASC"
);
$stmt->execute([$rid]);
$rows = $stmt->fetchAll();

$rules = array_map(function ($r) use ($dayNames) {
    return [
        'id'             => (int)$r['id'],
        'weekday'        => (int)$r['weekday'],
        'weekday_name'   => $dayNames[(int)$r['weekday']] ?? '',
        'start_time'     => $r['start_time'],
        'end_time'       => $r['end_time'],
        'location_type'  => $r['location_type'] ?? null,
        'location_label' => $r['location_label'] ?? null,
        'is_active'      => (bool)$r['is_active'],
    ];
}, $rows);

api_success(['rules' => $rules]);
