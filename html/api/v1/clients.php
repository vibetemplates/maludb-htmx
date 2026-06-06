<?php
/**
 * GET  /api/v1/clients.php                             — List clients
 * GET  /api/v1/clients.php?id=10                       — Single client + appointments
 * PUT  /api/v1/clients.php?id=10&action=preferences    — Update preferences
 */

require_once __DIR__ . '/_bootstrap.php';

$auth   = api_authenticate();
$rid    = $auth['company_id'];
$method = api_method();

if ($method === 'GET') {
    $id = api_query('id');
    if ($id) {
        handleGetClient($rid, (int)$id);
    } else {
        handleListClients($rid);
    }
} elseif ($method === 'PUT' && api_query('action') === 'preferences') {
    handleUpdatePreferences($rid, (int)api_query('id'));
} else {
    api_error('Method not allowed.', 'METHOD_NOT_ALLOWED', 405);
}

function handleListClients(int $rid): void {
    $search  = trim(api_query('search', ''));
    $page    = max(1, (int)api_query('page', 1));
    $perPage = min(100, max(1, (int)api_query('per_page', 50)));
    $offset  = ($page - 1) * $perPage;

    $pdo = db();
    $where  = ["company_id = ?"];
    $params = [$rid];

    if ($search !== '') {
        $like = "%{$search}%";
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $whereSQL = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM professional_clients WHERE {$whereSQL}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT * FROM professional_clients WHERE {$whereSQL}
         ORDER BY last_name ASC, first_name ASC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $clients = array_map('formatClient', $rows);

    api_success([
        'clients'  => $clients,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
    ]);
}

function handleGetClient(int $rid, int $id): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM professional_clients WHERE id = ? AND company_id = ? LIMIT 1");
    $stmt->execute([$id, $rid]);
    $client = $stmt->fetch();

    if (!$client) {
        api_error('Client not found.', 'NOT_FOUND', 404);
    }

    // Upcoming appointments
    $stmt = $pdo->prepare(
        "SELECT * FROM professional_appointments
         WHERE client_id = ? AND company_id = ? AND status IN ('pending','confirmed') AND start_at >= NOW()
         ORDER BY start_at ASC LIMIT 20"
    );
    $stmt->execute([$id, $rid]);
    $upcoming = $stmt->fetchAll();

    // Past appointments
    $stmt = $pdo->prepare(
        "SELECT * FROM professional_appointments
         WHERE client_id = ? AND company_id = ? AND (status IN ('completed','cancelled','no_show') OR start_at < NOW())
         ORDER BY start_at DESC LIMIT 20"
    );
    $stmt->execute([$id, $rid]);
    $past = $stmt->fetchAll();

    api_success([
        'client'                => formatClient($client),
        'upcoming_appointments' => array_map('formatClientAppointment', $upcoming),
        'past_appointments'     => array_map('formatClientAppointment', $past),
    ]);
}

function handleUpdatePreferences(int $rid, int $id): void {
    if (!$id) {
        api_error('id query parameter is required.', 'VALIDATION_ERROR', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM professional_clients WHERE id = ? AND company_id = ? LIMIT 1");
    $stmt->execute([$id, $rid]);
    $client = $stmt->fetch();

    if (!$client) {
        api_error('Client not found.', 'NOT_FOUND', 404);
    }

    $body    = api_json_body();
    $updates = [];
    $params  = [];
    $changed = [];

    if (isset($body['marketing_opt_in'])) {
        $updates[] = "marketing_opt_in = ?";
        $params[]  = $body['marketing_opt_in'] ? 1 : 0;
        $changed[] = 'marketing_opt_in';
    }
    if (isset($body['preferred_contact_method'])) {
        $val = $body['preferred_contact_method'];
        if (!in_array($val, ['email', 'phone', 'sms', ''], true)) {
            api_error('preferred_contact_method must be email, phone, sms, or empty.', 'VALIDATION_ERROR', 400);
        }
        $updates[] = "preferred_contact_method = ?";
        $params[]  = $val === '' ? null : $val;
        $changed[] = 'preferred_contact_method';
    }

    if (empty($updates)) {
        api_error('No preferences provided.', 'VALIDATION_ERROR', 400);
    }

    $params[] = $id;
    $pdo->prepare("UPDATE professional_clients SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

    api_success([
        'updated' => $changed,
        'message' => 'Preferences updated.',
    ]);
}

function formatClient(array $c): array {
    return [
        'id'                       => (int)$c['id'],
        'first_name'               => $c['first_name'],
        'last_name'                => $c['last_name'],
        'email'                    => $c['email'] ?? null,
        'phone'                    => $c['phone'] ?? null,
        'birth_date'               => $c['birth_date'] ?? null,
        'notes'                    => $c['notes'] ?? null,
        'service_address_line1'    => $c['service_address_line1'] ?? null,
        'service_city'             => $c['service_city'] ?? null,
        'service_state'            => $c['service_state'] ?? null,
        'service_postal_code'      => $c['service_postal_code'] ?? null,
        'preferred_contact_method' => $c['preferred_contact_method'] ?? null,
        'marketing_opt_in'         => (bool)($c['marketing_opt_in'] ?? false),
        'last_appointment_at'      => $c['last_appointment_at'] ?? null,
        'created_at'               => $c['created_at'] ?? '',
    ];
}

function formatClientAppointment(array $a): array {
    return [
        'id'                => (int)$a['id'],
        'confirmation_code' => $a['confirmation_code'],
        'status'            => $a['status'],
        'appointment_date'  => $a['appointment_date'],
        'start_at'          => $a['start_at'],
        'end_at'            => $a['end_at'],
        'service_name'      => $a['service_name'] ?? '',
        'duration_minutes'  => (int)($a['duration_minutes'] ?? 0),
        'price'             => $a['price'] ?? null,
    ];
}
