<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();
require_role_api(['super_admin']);

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $periodInput = clean($_GET['period'] ?? 'monthly');
    $customFrom = clean($_GET['from'] ?? date('Y-m-01'));
    $customTo = clean($_GET['to'] ?? date('Y-m-d'));
    $yearInput = $_GET['year'] ?? null;
    $moduleFilter = clean($_GET['module'] ?? '');
    $search = clean($_GET['search'] ?? '');

    $range = resolve_period_range($pdo, $periodInput, $customFrom, $customTo, $yearInput);

    $sql = "SELECT * FROM activity_log WHERE DATE(created_at) BETWEEN ? AND ?";
    $params = [$range['from'], $range['to']];

    if ($moduleFilter) {
        $sql .= " AND module = ?";
        $params[] = $moduleFilter;
    }
    if ($search) {
        $sql .= " AND (description LIKE ? OR admin_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql .= " ORDER BY created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $modules = $pdo->query("SELECT DISTINCT module FROM activity_log ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);

    json_response([
        'success' => true,
        'logs' => $logs,
        'modules' => $modules,
        'period' => $range['period'],
        'label' => $range['label'],
        'from' => $range['from'],
        'to' => $range['to'],
        'year' => $range['year'],
        'available_years' => $range['available_years'],
    ]);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
