<?php
/**
 * GET /api/v1/time-off.php?start_date=2026-04-01&end_date=2026-04-30
 * List time-off blocks within a date range.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

api_require_method('GET');
$auth = api_authenticate();
$rid  = $auth['restaurant_id'];

$startDate = api_query('start_date');
$endDate   = api_query('end_date');

if (!$startDate || !$endDate) {
    api_error('start_date and end_date are required.', 'VALIDATION_ERROR', 400);
}

$profile = getProfessionalProfile($rid);
$tz = new DateTimeZone($profile['timezone'] ?? 'America/New_York');

$rangeStart = $startDate . ' 00:00:00';
$rangeEnd   = $endDate . ' 23:59:59';

$blocks = getProfessionalTimeOffBlocks($rid, $rangeStart, $rangeEnd);

$formatted = array_map(function ($b) {
    return [
        'id'         => (int)$b['id'],
        'starts_at'  => $b['starts_at'],
        'ends_at'    => $b['ends_at'],
        'reason'     => $b['reason'] ?? null,
        'notes'      => $b['notes'] ?? null,
        'is_all_day' => (bool)($b['is_all_day'] ?? false),
    ];
}, $blocks);

api_success(['time_off' => $formatted]);
