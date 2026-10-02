<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'add_expense') {
    $title = clean($data['title'] ?? '');
    $category = clean($data['category'] ?? '');
    $amount = (float)($data['amount'] ?? 0);
    $expense_date = clean($data['expense_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    if (!$title || $amount <= 0) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza jina la tumizi na kiasi sahihi.']);
    }

    $pdo->prepare("INSERT INTO expenses (title, category, amount, expense_date, notes, created_by) VALUES (?,?,?,?,?,?)")
        ->execute([$title, $category, $amount, $expense_date, $notes, $_SESSION['admin_id']]);

    log_activity($pdo, 'expenses', 'create', "Ameongeza tumizi: $title (" . money($amount) . ").");
    json_response(['success' => true, 'message' => 'Tumizi limeongezwa kikamilifu.']);
}

if ($action === 'update_expense') {
    require_role_api(['super_admin', 'admin']);
    $id = (int)($data['id'] ?? 0);
    $title = clean($data['title'] ?? '');
    $category = clean($data['category'] ?? '');
    $amount = (float)($data['amount'] ?? 0);
    $expense_date = clean($data['expense_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    if (!$id || !$title || $amount <= 0) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza jina la tumizi na kiasi sahihi.']);
    }

    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Tumizi halikupatikana.']);

    $pdo->prepare("UPDATE expenses SET title=?, category=?, amount=?, expense_date=?, notes=? WHERE id=?")
        ->execute([$title, $category, $amount, $expense_date, $notes, $id]);

    log_activity($pdo, 'expenses', 'update', "Amesasisha tumizi: $title (" . money($amount) . ").");
    json_response(['success' => true, 'message' => 'Tumizi limesasishwa.']);
}

if ($action === 'report') {
    require_role_api(['super_admin', 'admin']);
    $periodInput = clean($_GET['period'] ?? 'monthly');
    $customFrom = clean($_GET['from'] ?? date('Y-m-01'));
    $customTo = clean($_GET['to'] ?? date('Y-m-d'));
    $yearInput = $_GET['year'] ?? null;
    $range = resolve_period_range($pdo, $periodInput, $customFrom, $customTo, $yearInput);
    $from = $range['from'];
    $to = $range['to'];

    $incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) t FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
    $incomeStmt->execute([$from, $to]);
    $income = $incomeStmt->fetch()['t'];

    $expStmt = $pdo->prepare("SELECT e.*, a.full_name AS added_by_name FROM expenses e LEFT JOIN admins a ON e.created_by=a.id WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC");
    $expStmt->execute([$from, $to]);
    $expenses = $expStmt->fetchAll();
    $totalExpenses = array_sum(array_column($expenses, 'amount'));

    json_response([
        'success' => true,
        'income' => (float)$income,
        'total_expenses' => (float)$totalExpenses,
        'profit' => (float)($income - $totalExpenses),
        'expenses' => $expenses,
        'period' => $range['period'],
        'label' => $range['label'],
        'from' => $from,
        'to' => $to,
        'year' => $range['year'],
        'available_years' => $range['available_years'],
    ]);
}

if ($action === 'delete_expense') {
    require_role_api(['super_admin', 'admin']);
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id=?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Tumizi halikupatikana.']);

    $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$id]);

    log_activity($pdo, 'expenses', 'delete', "Amefuta tumizi: {$existing['title']} (" . money($existing['amount']) . ").");
    json_response(['success' => true, 'message' => 'Tumizi limefutwa.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
