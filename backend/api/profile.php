<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM admins WHERE id=?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if ($action === 'get') {
    json_response(['success' => true, 'admin' => [
        'full_name' => $admin['full_name'],
        'username' => $admin['username'],
        'email' => $admin['email'],
        'security_question' => $admin['security_question'],
    ]]);
}

if ($action === 'update_info') {
    $full_name = clean($data['full_name'] ?? '');
    $username = clean($data['username'] ?? '');
    $email = clean($data['email'] ?? '');

    if (!$full_name || !$username) {
        json_response(['success' => false, 'message' => 'Jina na username havipaswi kuwa tupu.']);
    }

    $pdo->prepare("UPDATE admins SET full_name=?, username=?, email=? WHERE id=?")
        ->execute([$full_name, $username, $email, $_SESSION['admin_id']]);
    $_SESSION['admin_name'] = $full_name;

    json_response(['success' => true, 'message' => 'Taarifa zako zimesasishwa kikamilifu.']);
}

if ($action === 'update_password') {
    $current = $data['current_password'] ?? '';
    $new = $data['new_password'] ?? '';
    $confirm = $data['confirm_password'] ?? '';

    if (!password_verify($current, $admin['password_hash'])) {
        json_response(['success' => false, 'message' => 'Password ya sasa si sahihi.']);
    }
    if (strlen($new) < 6) {
        json_response(['success' => false, 'message' => 'Password mpya lazima iwe na herufi/namba angalau 6.']);
    }
    if ($new !== $confirm) {
        json_response(['success' => false, 'message' => 'Password mpya hazifanani.']);
    }

    $pdo->prepare("UPDATE admins SET password_hash=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
    json_response(['success' => true, 'message' => 'Password imebadilishwa kikamilifu.']);
}

if ($action === 'update_security') {
    $sec_q = clean($data['security_question'] ?? '');
    $sec_a = clean($data['security_answer'] ?? '');

    if (!$sec_q || !$sec_a) {
        json_response(['success' => false, 'message' => 'Jaza swali na jibu la usalama.']);
    }

    $pdo->prepare("UPDATE admins SET security_question=?, security_answer_hash=? WHERE id=?")
        ->execute([$sec_q, password_hash(strtolower(trim($sec_a)), PASSWORD_DEFAULT), $_SESSION['admin_id']]);
    json_response(['success' => true, 'message' => 'Swali la usalama limesasishwa kikamilifu.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
