<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'status') {
    $count = (int) $pdo->query("SELECT COUNT(*) c FROM admins")->fetch()['c'];
    json_response([
        'success' => true,
        'installed' => $count > 0,
        'shop_name' => get_setting($pdo, 'shop_name', 'FRESH SCENTS'),
        'shop_tagline' => get_setting($pdo, 'shop_tagline', 'Mfumo wa Ndani wa Duka'),
    ]);
}

if ($action === 'create') {
    $count = (int) $pdo->query("SELECT COUNT(*) c FROM admins")->fetch()['c'];
    if ($count > 0) {
        json_response(['success' => false, 'message' => 'Mfumo tayari umeshasakinishwa.']);
    }

    $full_name = clean($data['full_name'] ?? '');
    $username = clean($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $confirm = $data['confirm_password'] ?? '';
    $sec_q = clean($data['security_question'] ?? '');
    $sec_a = clean($data['security_answer'] ?? '');
    $shop_name = clean($data['shop_name'] ?? 'FRESH SCENTS');
    $shop_phone = clean($data['shop_phone'] ?? '');
    $shop_address = clean($data['shop_address'] ?? '');

    if (!$full_name || !$username || !$password || !$sec_q || !$sec_a) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza sehemu zote muhimu.']);
    }
    if (strlen($password) < 6) {
        json_response(['success' => false, 'message' => 'Password lazima iwe na herufi/namba angalau 6.']);
    }
    if ($password !== $confirm) {
        json_response(['success' => false, 'message' => 'Password hazifanani.']);
    }

    $stmt = $pdo->prepare("INSERT INTO admins (full_name, username, password_hash, security_question, security_answer_hash, role)
        VALUES (?,?,?,?,?, 'super_admin')");
    $stmt->execute([
        $full_name, $username,
        password_hash($password, PASSWORD_DEFAULT),
        $sec_q,
        password_hash(strtolower(trim($sec_a)), PASSWORD_DEFAULT),
    ]);

    set_setting($pdo, 'shop_name', $shop_name ?: 'FRESH SCENTS');
    if ($shop_phone) set_setting($pdo, 'shop_phone', $shop_phone);
    if ($shop_address) set_setting($pdo, 'shop_address', $shop_address);
    set_setting($pdo, 'installed', '1');

    json_response(['success' => true, 'message' => 'Akaunti ya msimamizi imetengenezwa kikamilifu!']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
