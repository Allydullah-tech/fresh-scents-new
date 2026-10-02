<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();
require_role_api(['super_admin', 'admin']);

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'list') {
    $employees = $pdo->query("SELECT * FROM employees ORDER BY status ASC, full_name ASC")->fetchAll();
    json_response(['success' => true, 'employees' => $employees]);
}

if ($action === 'save') {
    $id = (int)($data['id'] ?? 0);
    $full_name = clean($data['full_name'] ?? '');
    $position = clean($data['position'] ?? '');
    $phone = clean($data['phone'] ?? '');
    $salary = (float)($data['salary'] ?? 0);
    $hired_date = clean($data['hired_date'] ?? '');
    $status = $data['status'] === 'inactive' ? 'inactive' : 'active';

    if (!$full_name || !$position) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza jina na nafasi ya mfanyakazi.']);
    }

    if ($id) {
        $pdo->prepare("UPDATE employees SET full_name=?,position=?,phone=?,salary=?,hired_date=?,status=? WHERE id=?")
            ->execute([$full_name, $position, $phone, $salary, $hired_date ?: null, $status, $id]);
        json_response(['success' => true, 'message' => 'Taarifa za mfanyakazi zimesasishwa.']);
    } else {
        $pdo->prepare("INSERT INTO employees (full_name,position,phone,salary,hired_date,status) VALUES (?,?,?,?,?,?)")
            ->execute([$full_name, $position, $phone, $salary, $hired_date ?: null, $status]);
        json_response(['success' => true, 'message' => 'Mfanyakazi mpya ameongezwa.']);
    }
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    $pdo->prepare("DELETE FROM employees WHERE id=?")->execute([$id]);
    json_response(['success' => true, 'message' => 'Mfanyakazi amefutwa.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
