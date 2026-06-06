<?php
/**
 * GET /api/v1/services.php          — List all services
 * GET /api/v1/services.php?id=1     — Get single service
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

api_require_method('GET');
$auth = api_authenticate();
$rid  = $auth['company_id'];

$serviceId = api_query('id');

// --- Single service ---
if ($serviceId !== null) {
    $service = getProfessionalService($rid, (int)$serviceId, ['allow_inactive' => true]);
    if (!$service) {
        api_error('Service not found.', 'NOT_FOUND', 404);
    }
    api_success(['service' => formatService($service)]);
}

// --- List services ---
$includeInactive = filter_var(api_query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);

$pdo = db();
$sql = "SELECT * FROM professional_services WHERE company_id = ?";
$params = [$rid];
if (!$includeInactive) {
    $sql .= " AND is_active = 1";
}
$sql .= " ORDER BY sort_order ASC, name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Attach effective buffers from profile
$profile = getProfessionalProfile($rid);
$services = array_map(function ($row) use ($profile) {
    $row['effective_buffer_before_minutes'] = ($row['buffer_before_minutes'] > 0)
        ? $row['buffer_before_minutes']
        : ($profile['default_buffer_before_minutes'] ?? 0);
    $row['effective_buffer_after_minutes'] = ($row['buffer_after_minutes'] > 0)
        ? $row['buffer_after_minutes']
        : ($profile['default_buffer_after_minutes'] ?? 0);
    return formatService($row);
}, $rows);

api_success([
    'services' => $services,
    'total'    => count($services),
]);

function formatService(array $s): array {
    return [
        'id'                => (int)$s['id'],
        'name'              => $s['name'],
        'description'       => $s['description'] ?? null,
        'duration_minutes'  => (int)$s['duration_minutes'],
        'buffer_before_minutes' => (int)($s['effective_buffer_before_minutes'] ?? $s['buffer_before_minutes']),
        'buffer_after_minutes'  => (int)($s['effective_buffer_after_minutes'] ?? $s['buffer_after_minutes']),
        'price'             => $s['price'],
        'currency_code'     => $s['currency_code'] ?? 'USD',
        'location_type'     => $s['location_type'] ?? null,
        'location_label'    => $s['location_label'] ?? null,
        'color'             => $s['color'] ?? null,
        'sort_order'        => (int)($s['sort_order'] ?? 0),
        'is_active'         => (bool)$s['is_active'],
        'is_public_bookable' => (bool)$s['is_public_bookable'],
    ];
}
