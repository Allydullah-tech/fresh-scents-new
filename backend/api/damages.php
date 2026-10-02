<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'variants') {
    $variants = $pdo->query("SELECT pv.id, pv.ml, pv.stock_qty, pv.cost_price, pv.price, p.name, p.brand FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE p.status = 'active' AND pv.stock_qty > 0
        ORDER BY p.name, pv.ml")->fetchAll();
    json_response(['success' => true, 'variants' => $variants]);
}

if ($action === 'history') {
    $periodInput = clean($_GET['period'] ?? 'monthly');
    $customFrom = clean($_GET['from'] ?? date('Y-m-01'));
    $customTo = clean($_GET['to'] ?? date('Y-m-d'));
    $yearInput = $_GET['year'] ?? null;
    $range = resolve_period_range($pdo, $periodInput, $customFrom, $customTo, $yearInput);

    $stmt = $pdo->prepare("SELECT d.*, pv.ml AS variant_ml, p.name AS variant_product_name, a.full_name AS recorded_by_name
        FROM damages d
        LEFT JOIN product_variants pv ON d.variant_id = pv.id
        LEFT JOIN products p ON pv.product_id = p.id
        LEFT JOIN admins a ON d.recorded_by = a.id
        WHERE d.damage_date BETWEEN ? AND ?
        ORDER BY d.created_at DESC");
    $stmt->execute([$range['from'], $range['to']]);
    $history = $stmt->fetchAll();

    foreach ($history as &$h) {
        $isStock = (int)$h['is_stock_item'] === 1;
        $h['product_name'] = $isStock ? $h['variant_product_name'] : $h['item_name'];
        $h['ml'] = $isStock ? $h['variant_ml'] : null;
    }
    unset($h);

    $totalLoss = array_sum(array_column($history, 'loss_value'));
    $totalQty = array_sum(array_column($history, 'quantity'));

    json_response([
        'success' => true,
        'history' => $history,
        'total_loss' => $totalLoss,
        'total_qty' => (int)$totalQty,
        'period' => $range['period'],
        'label' => $range['label'],
        'from' => $range['from'],
        'to' => $range['to'],
        'year' => $range['year'],
        'available_years' => $range['available_years'],
    ]);
}

if ($action === 'record') {
    $sourceType = ($data['source_type'] ?? 'stock') === 'other' ? 'other' : 'stock';
    $quantity = max(1, (int)($data['quantity'] ?? 1));
    $reason = in_array($data['reason'] ?? '', ['expired', 'damaged', 'other']) ? $data['reason'] : 'damaged';
    $damage_date = clean($data['damage_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    if ($sourceType === 'stock') {
        $variant_id = (int)($data['variant_id'] ?? 0);

        if (!$variant_id) {
            json_response(['success' => false, 'message' => 'Tafadhali chagua bidhaa kwanza.']);
        }

        $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ?");
        $stmt->execute([$variant_id]);
        $v = $stmt->fetch();

        if (!$v) json_response(['success' => false, 'message' => 'Bidhaa haikupatikana.']);
        if ($v['stock_qty'] < $quantity) {
            json_response(['success' => false, 'message' => 'Idadi uliyoweka ni kubwa kuliko stock iliyopo (' . $v['stock_qty'] . ').']);
        }

        $lossValue = $v['cost_price'] > 0 ? $v['cost_price'] * $quantity : $v['price'] * $quantity;

        $pdo->prepare("INSERT INTO damages (variant_id, item_name, is_stock_item, quantity, reason, loss_value, damage_date, notes, recorded_by)
            VALUES (?, NULL, 1, ?, ?, ?, ?, ?, ?)")
            ->execute([$variant_id, $quantity, $reason, $lossValue, $damage_date, $notes, $_SESSION['admin_id']]);
        $pdo->prepare("UPDATE product_variants SET stock_qty = GREATEST(stock_qty - ?, 0) WHERE id = ?")
            ->execute([$quantity, $variant_id]);

        $pStmt = $pdo->prepare("SELECT p.name FROM product_variants pv JOIN products p ON pv.product_id=p.id WHERE pv.id=?");
        $pStmt->execute([$variant_id]);
        $pName = $pStmt->fetch()['name'] ?? '';
        log_activity($pdo, 'damages', 'create', "Amerekodi uharibifu wa $pName ({$v['ml']}ml), idadi $quantity, hasara " . money($lossValue) . ".");

        json_response(['success' => true, 'message' => 'Uharibifu umerekodiwa. Stock imepunguzwa kiotomatiki.']);
    } else {
        $item_name = clean($data['item_name'] ?? '');
        $amount = (float)($data['amount'] ?? 0);

        if (!$item_name) {
            json_response(['success' => false, 'message' => 'Tafadhali andika jina la kitu kilichoharibika.']);
        }
        if ($amount <= 0) {
            json_response(['success' => false, 'message' => 'Tafadhali weka thamani/kiasi sahihi kilichopotea.']);
        }

        $pdo->prepare("INSERT INTO damages (variant_id, item_name, is_stock_item, quantity, reason, loss_value, damage_date, notes, recorded_by)
            VALUES (NULL, ?, 0, ?, ?, ?, ?, ?, ?)")
            ->execute([$item_name, $quantity, $reason, $amount, $damage_date, $notes, $_SESSION['admin_id']]);

        log_activity($pdo, 'damages', 'create', "Amerekodi uharibifu wa kitu kisicho stock: $item_name, hasara " . money($amount) . ".");

        json_response(['success' => true, 'message' => 'Uharibifu wa kitu kisicho stock kimerekodiwa kikamilifu. Hakuna stock iliyopunguzwa.']);
    }
}

if ($action === 'update') {
    $id = (int)($data['id'] ?? 0);
    $quantity = max(1, (int)($data['quantity'] ?? 1));
    $reason = in_array($data['reason'] ?? '', ['expired', 'damaged', 'other']) ? $data['reason'] : 'damaged';
    $damage_date = clean($data['damage_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    $stmt = $pdo->prepare("SELECT d.*, p.name AS product_name FROM damages d
        LEFT JOIN product_variants pv ON d.variant_id = pv.id
        LEFT JOIN products p ON pv.product_id = p.id WHERE d.id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Rekodi ya uharibifu haikupatikana.']);

    if ((int)$existing['is_stock_item'] === 1) {
        $variant_id = $existing['variant_id'];
        $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ?");
        $vStmt->execute([$variant_id]);
        $v = $vStmt->fetch();
        if (!$v) json_response(['success' => false, 'message' => 'Bidhaa husika haipo tena.']);

        $oldQty = (int)$existing['quantity'];
        $delta = $quantity - $oldQty;
        if ($delta > 0 && $v['stock_qty'] < $delta) {
            json_response(['success' => false, 'message' => 'Stock haitoshi kuongeza idadi hii ya uharibifu. Iliyopo: ' . $v['stock_qty']]);
        }

        $lossValue = $v['cost_price'] > 0 ? $v['cost_price'] * $quantity : $v['price'] * $quantity;

        $pdo->prepare("UPDATE damages SET quantity=?, reason=?, loss_value=?, damage_date=?, notes=? WHERE id=?")
            ->execute([$quantity, $reason, $lossValue, $damage_date, $notes, $id]);
        $pdo->prepare("UPDATE product_variants SET stock_qty = GREATEST(stock_qty - ?, 0) WHERE id=?")
            ->execute([$delta, $variant_id]);

        log_activity($pdo, 'damages', 'update', "Amesasisha uharibifu wa {$existing['product_name']}: idadi kutoka $oldQty kwenda $quantity.");
        json_response(['success' => true, 'message' => 'Rekodi ya uharibifu imesasishwa.']);
    } else {
        $item_name = clean($data['item_name'] ?? $existing['item_name']);
        $amount = (float)($data['amount'] ?? $existing['loss_value']);

        if (!$item_name) json_response(['success' => false, 'message' => 'Tafadhali andika jina la kitu kilichoharibika.']);
        if ($amount <= 0) json_response(['success' => false, 'message' => 'Tafadhali weka thamani sahihi ya kilichopotea.']);

        $pdo->prepare("UPDATE damages SET item_name=?, quantity=?, reason=?, loss_value=?, damage_date=?, notes=? WHERE id=?")
            ->execute([$item_name, $quantity, $reason, $amount, $damage_date, $notes, $id]);

        log_activity($pdo, 'damages', 'update', "Amesasisha uharibifu wa kitu kisicho stock: $item_name.");
        json_response(['success' => true, 'message' => 'Rekodi ya uharibifu imesasishwa.']);
    }
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT d.*, p.name AS product_name FROM damages d
        LEFT JOIN product_variants pv ON d.variant_id = pv.id
        LEFT JOIN products p ON pv.product_id = p.id WHERE d.id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Rekodi ya uharibifu haikupatikana.']);

    if ((int)$existing['is_stock_item'] === 1 && $existing['variant_id']) {
        $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id=?")
            ->execute([$existing['quantity'], $existing['variant_id']]);
    }
    $pdo->prepare("DELETE FROM damages WHERE id=?")->execute([$id]);

    $label = (int)$existing['is_stock_item'] === 1 ? $existing['product_name'] : $existing['item_name'];
    log_activity($pdo, 'damages', 'delete', "Amefuta rekodi ya uharibifu: $label (" . money($existing['loss_value']) . "). Stock imerudishwa ikiwa ilikuwa bidhaa ya stock.");
    json_response(['success' => true, 'message' => 'Rekodi ya uharibifu imefutwa. Stock imerudishwa kama ilikuwa bidhaa ya stock.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
