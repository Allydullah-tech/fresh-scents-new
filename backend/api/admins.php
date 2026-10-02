<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();
require_role_api(['super_admin']);

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'list') {
    $admins = $pdo->query("SELECT id, full_name, username, role, status, last_login, created_at FROM admins ORDER BY created_at ASC")->fetchAll();
    json_response(['success' => true, 'admins' => $admins]);
}

if ($action === 'add') {
    $full_name = clean($data['full_name'] ?? '');
    $username = clean($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $role = in_array($data['role'] ?? '', ['super_admin', 'admin', 'worker']) ? $data['role'] : 'worker';
    $sec_q = clean($data['security_question'] ?? '');
    $sec_a = clean($data['security_answer'] ?? '');

    $chk = $pdo->prepare("SELECT id FROM admins WHERE username=?");
    $chk->execute([$username]);

    if (!$full_name || !$username || !$password || !$sec_q || !$sec_a) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza sehemu zote.']);
    }
    if ($chk->fetch()) {
        json_response(['success' => false, 'message' => 'Username hii tayari inatumika.']);
    }
    if (strlen($password) < 6) {
        json_response(['success' => false, 'message' => 'Password lazima iwe na herufi/namba angalau 6.']);
    }

    $pdo->prepare("INSERT INTO admins (full_name, username, password_hash, security_question, security_answer_hash, role) VALUES (?,?,?,?,?,?)")
        ->execute([$full_name, $username, password_hash($password, PASSWORD_DEFAULT), $sec_q, password_hash(strtolower(trim($sec_a)), PASSWORD_DEFAULT), $role]);

    json_response(['success' => true, 'message' => 'Mtumiaji mpya (' . role_label($role) . ') ameongezwa kikamilifu.']);
}

if ($action === 'toggle') {
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    if ($id != $_SESSION['admin_id']) {
        $pdo->prepare("UPDATE admins SET status = IF(status='active','disabled','active') WHERE id=?")->execute([$id]);
    }
    json_response(['success' => true]);
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    if ($id != $_SESSION['admin_id']) {
        $pdo->prepare("DELETE FROM admins WHERE id=?")->execute([$id]);
    }
    json_response(['success' => true]);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
