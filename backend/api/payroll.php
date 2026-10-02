<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();
require_role_api(['super_admin', 'admin']);

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'data') {
    $periodInput = clean($_GET['period'] ?? 'monthly');
    $customFrom = clean($_GET['from'] ?? date('Y-m-01'));
    $customTo = clean($_GET['to'] ?? date('Y-m-d'));
    $yearInput = $_GET['year'] ?? null;
    $range = resolve_period_range($pdo, $periodInput, $customFrom, $customTo, $yearInput);

    $employees = $pdo->query("SELECT * FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();

    $stmt = $pdo->prepare("SELECT sp.*, e.full_name, e.position FROM salary_payments sp
        JOIN employees e ON sp.employee_id=e.id
        WHERE sp.pay_date BETWEEN ? AND ?
        ORDER BY sp.pay_date DESC");
    $stmt->execute([$range['from'], $range['to']]);
    $history = $stmt->fetchAll();

    $totalPaid = array_sum(array_column($history, 'amount'));

    json_response([
        'success' => true,
        'employees' => $employees,
        'history' => $history,
        'total_paid' => (float)$totalPaid,
        'total_monthly_salary' => (float)array_sum(array_column($employees, 'salary')),
        'period' => $range['period'],
        'label' => $range['label'],
        'from' => $range['from'],
        'to' => $range['to'],
        'year' => $range['year'],
        'available_years' => $range['available_years'],
    ]);
}

if ($action === 'record') {
    $employee_id = (int)($data['employee_id'] ?? 0);
    $amount = (float)($data['amount'] ?? 0);
    $pay_month = clean($data['pay_month'] ?? '');
    $pay_date = clean($data['pay_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    if (!$employee_id || $amount <= 0 || !$pay_month) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza taarifa zote muhimu.']);
    }

    $pdo->prepare("INSERT INTO salary_payments (employee_id, amount, pay_month, pay_date, notes, paid_by) VALUES (?,?,?,?,?,?)")
        ->execute([$employee_id, $amount, $pay_month, $pay_date, $notes, $_SESSION['admin_id']]);

    $empStmt = $pdo->prepare("SELECT full_name FROM employees WHERE id=?");
    $empStmt->execute([$employee_id]);
    $empName = $empStmt->fetch()['full_name'] ?? '';
    log_activity($pdo, 'payroll', 'create', "Amerekodi malipo ya mshahara wa $empName - " . money($amount) . " ($pay_month).");

    json_response(['success' => true, 'message' => 'Malipo ya mshahara yamerekodiwa kikamilifu.']);
}

if ($action === 'update') {
    $id = (int)($data['id'] ?? 0);
    $amount = (float)($data['amount'] ?? 0);
    $pay_month = clean($data['pay_month'] ?? '');
    $pay_date = clean($data['pay_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    if (!$id || $amount <= 0 || !$pay_month) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza taarifa zote muhimu.']);
    }

    $stmt = $pdo->prepare("SELECT sp.*, e.full_name FROM salary_payments sp JOIN employees e ON sp.employee_id=e.id WHERE sp.id=?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Malipo hayakupatikana.']);

    $pdo->prepare("UPDATE salary_payments SET amount=?, pay_month=?, pay_date=?, notes=? WHERE id=?")
        ->execute([$amount, $pay_month, $pay_date, $notes, $id]);

    log_activity($pdo, 'payroll', 'update', "Amesasisha malipo ya mshahara wa {$existing['full_name']} kuwa " . money($amount) . " ($pay_month).");
    json_response(['success' => true, 'message' => 'Malipo yamesasishwa.']);
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT sp.*, e.full_name FROM salary_payments sp JOIN employees e ON sp.employee_id=e.id WHERE sp.id=?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Malipo hayakupatikana.']);

    $pdo->prepare("DELETE FROM salary_payments WHERE id=?")->execute([$id]);

    log_activity($pdo, 'payroll', 'delete', "Amefuta malipo ya mshahara wa {$existing['full_name']} (" . money($existing['amount']) . ", {$existing['pay_month']}).");
    json_response(['success' => true, 'message' => 'Malipo yamefutwa.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
