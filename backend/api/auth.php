<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'login') {
    $username = clean($data['username'] ?? '');
    $password = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        json_response(['success' => false, 'message' => 'Username au password si sahihi.']);
    }
    if ($admin['status'] !== 'active') {
        json_response(['success' => false, 'message' => 'Akaunti yako imezimwa. Wasiliana na msimamizi mkuu.']);
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];

    $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
    $pdo->prepare("INSERT INTO login_logs (admin_id, ip_address) VALUES (?, ?)")->execute([$admin['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
    log_activity($pdo, 'auth', 'login', "{$admin['full_name']} ameingia mfumoni.");

    json_response(['success' => true, 'message' => 'Umeingia kikamilifu!']);
}

if ($action === 'logout') {
    if (!empty($_SESSION['admin_id'])) {
        log_activity($pdo, 'auth', 'logout', ($_SESSION['admin_name'] ?? 'Mtumiaji') . ' ametoka mfumoni.');
    }
    session_unset();
    session_destroy();
    json_response(['success' => true]);
}

if ($action === 'me') {
    if (empty($_SESSION['admin_id'])) {
        json_response(['success' => false, 'logged_in' => false]);
    }
    $stmt = $pdo->prepare("SELECT id, full_name, username, email, role, security_question FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        session_unset(); session_destroy();
        json_response(['success' => false, 'logged_in' => false]);
    }
    json_response([
        'success' => true,
        'logged_in' => true,
        'admin' => $admin,
        'shop_name' => get_setting($pdo, 'shop_name', 'FRESH SCENTS'),
    ]);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
