<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');
$identifier = clean($data['identifier'] ?? '');

function find_user($pdo, $identifier) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
    $stmt->execute([$identifier, $identifier]);
    return $stmt->fetch();
}

if ($action === 'find') {
    $user = find_user($pdo, $identifier);
    if (!$user) {
        json_response(['success' => false, 'message' => 'Hatujapata akaunti yenye taarifa hizo.']);
    }
    json_response(['success' => true, 'security_question' => $user['security_question']]);
}

if ($action === 'reset') {
    $answer = strtolower(trim($data['security_answer'] ?? ''));
    $new_password = $data['new_password'] ?? '';
    $confirm = $data['confirm_password'] ?? '';
    $user = find_user($pdo, $identifier);

    if (!$user) {
        json_response(['success' => false, 'message' => 'Hitilafu imetokea, jaribu tena.']);
    }
    if (!password_verify($answer, $user['security_answer_hash'])) {
        json_response(['success' => false, 'message' => 'Jibu la swali la usalama si sahihi.']);
    }
    if (strlen($new_password) < 6) {
        json_response(['success' => false, 'message' => 'Password mpya lazima iwe na herufi/namba angalau 6.']);
    }
    if ($new_password !== $confirm) {
        json_response(['success' => false, 'message' => 'Password hazifanani.']);
    }

    $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")
        ->execute([password_hash($new_password, PASSWORD_DEFAULT), $user['id']]);

    json_response(['success' => true, 'message' => 'Password yako imebadilishwa kikamilifu! Sasa unaweza kuingia kwa password mpya.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
