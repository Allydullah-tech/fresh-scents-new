<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();
require_role_api(['super_admin']);

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

$fields = ['shop_name', 'shop_tagline', 'shop_phone', 'shop_address'];

if ($action === 'get') {
    $settings = [];
    foreach ($fields as $f) { $settings[$f] = get_setting($pdo, $f); }
    json_response(['success' => true, 'settings' => $settings]);
}

if ($action === 'save') {
    foreach ($fields as $f) {
        set_setting($pdo, $f, clean($data[$f] ?? ''));
    }
    json_response(['success' => true, 'message' => 'Mipangilio imehifadhiwa kikamilifu.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
