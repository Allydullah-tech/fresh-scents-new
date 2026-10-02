<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($action === 'variants') {
    $variants = $pdo->query("SELECT pv.id, pv.ml, pv.price, pv.stock_qty, p.name, p.brand
        FROM product_variants pv JOIN products p ON pv.product_id = p.id
        WHERE p.status = 'active' AND pv.stock_qty > 0
        ORDER BY p.name, pv.ml")->fetchAll();
    json_response(['success' => true, 'variants' => $variants]);
}

if ($action === 'recent') {
    $periodInput = clean($_GET['period'] ?? 'daily');
    $customFrom = clean($_GET['from'] ?? date('Y-m-01'));
    $customTo = clean($_GET['to'] ?? date('Y-m-d'));
    $yearInput = $_GET['year'] ?? null;
    $range = resolve_period_range($pdo, $periodInput, $customFrom, $customTo, $yearInput);

    $stmt = $pdo->prepare("SELECT s.*, a.full_name AS admin_name FROM sales s
        LEFT JOIN admins a ON s.recorded_by = a.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        ORDER BY s.created_at DESC");
    $stmt->execute([$range['from'], $range['to']]);
    $sales = $stmt->fetchAll();

    $totalSales = array_sum(array_column($sales, 'total_amount'));
    $byMethod = [];
    foreach ($sales as $s) {
        $m = $s['payment_method'];
        $byMethod[$m] = ($byMethod[$m] ?? 0) + (float)$s['total_amount'];
    }

    json_response([
        'success' => true,
        'sales' => $sales,
        'total_sales' => (float)$totalSales,
        'total_transactions' => count($sales),
        'average_sale' => count($sales) ? $totalSales / count($sales) : 0,
        'by_payment_method' => $byMethod,
        'period' => $range['period'],
        'label' => $range['label'],
        'from' => $range['from'],
        'to' => $range['to'],
        'year' => $range['year'],
        'available_years' => $range['available_years'],
    ]);
}

if ($action === 'record') {
    $items = $data['items'] ?? [];
    $customer_name = clean($data['customer_name'] ?? '');
    $customer_phone = clean($data['customer_phone'] ?? '');
    $payment_method = in_array($data['payment_method'] ?? '', ['cash', 'mobile_money', 'bank', 'other']) ? $data['payment_method'] : 'cash';
    $notes = clean($data['notes'] ?? '');

    if (!$items || !is_array($items)) {
        json_response(['success' => false, 'message' => 'Chagua angalau bidhaa moja kabla ya kurekodi mauzo.']);
    }

    $pdo->beginTransaction();
    try {
        $total = 0;
        $validated = [];
        foreach ($items as $it) {
            $vid = (int)($it['variant_id'] ?? 0);
            $qty = max(1, (int)($it['qty'] ?? 1));
            $stmt = $pdo->prepare("SELECT pv.*, p.name AS product_name, p.status AS product_status
                FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE pv.id = ?");
            $stmt->execute([$vid]);
            $v = $stmt->fetch();
            if (!$v || $v['product_status'] !== 'active') continue;
            if ($v['stock_qty'] < $qty) {
                throw new Exception('Stock haitoshi kwa ' . $v['product_name'] . ' (' . $v['ml'] . 'ml). Kilichopo: ' . $v['stock_qty']);
            }
            $subtotal = $v['price'] * $qty;
            $total += $subtotal;
            $validated[] = [
                'product_id' => $v['product_id'], 'variant_id' => $v['id'],
                'product_name' => $v['product_name'], 'ml' => $v['ml'],
                'unit_price' => $v['price'], 'qty' => $qty, 'subtotal' => $subtotal,
            ];
        }

        if (!$validated) throw new Exception('Bidhaa ulizochagua hazipatikani tena.');

        $sale_code = generate_sale_code($pdo);
        $stmt = $pdo->prepare("INSERT INTO sales (sale_code, customer_name, customer_phone, payment_method, total_amount, notes, recorded_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$sale_code, $customer_name ?: null, $customer_phone ?: null, $payment_method, $total, $notes, $_SESSION['admin_id']]);
        $saleId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, product_name, ml, unit_price, qty, subtotal) VALUES (?,?,?,?,?,?,?,?)");
        $stockStmt = $pdo->prepare("UPDATE product_variants SET stock_qty = GREATEST(stock_qty - ?, 0) WHERE id = ?");
        foreach ($validated as $v) {
            $itemStmt->execute([$saleId, $v['product_id'], $v['variant_id'], $v['product_name'], $v['ml'], $v['unit_price'], $v['qty'], $v['subtotal']]);
            $stockStmt->execute([$v['qty'], $v['variant_id']]);
        }

        $pdo->commit();
        log_activity($pdo, 'sales', 'create', "Amerekodi mauzo $sale_code (" . money($total) . ") kwa " . ($customer_name ?: 'mteja') . ".");
        json_response(['success' => true, 'message' => 'Mauzo yamerekodiwa kikamilifu!', 'sale_code' => $sale_code, 'total' => $total]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($action === 'update') {
    $id = (int)($data['id'] ?? 0);
    $customer_name = clean($data['customer_name'] ?? '');
    $customer_phone = clean($data['customer_phone'] ?? '');
    $payment_method = in_array($data['payment_method'] ?? '', ['cash', 'mobile_money', 'bank', 'other']) ? $data['payment_method'] : 'cash';
    $notes = clean($data['notes'] ?? '');

    if (!$id) json_response(['success' => false, 'message' => 'Mauzo hayakupatikana.']);

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Mauzo hayakupatikana.']);

    $pdo->prepare("UPDATE sales SET customer_name=?, customer_phone=?, payment_method=?, notes=? WHERE id=?")
        ->execute([$customer_name ?: null, $customer_phone ?: null, $payment_method, $notes, $id]);

    log_activity($pdo, 'sales', 'update', "Amesasisha taarifa za mauzo {$existing['sale_code']}.");
    json_response(['success' => true, 'message' => 'Taarifa za mauzo zimesasishwa. (Bidhaa/kiasi cha mauzo haziwezi kubadilishwa hapa — futa na urekodi upya kama kuna kosa la bidhaa.)']);
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();
    if (!$sale) json_response(['success' => false, 'message' => 'Mauzo hayakupatikana.']);

    $pdo->beginTransaction();
    try {
        $itemsStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $itemsStmt->execute([$id]);
        foreach ($itemsStmt->fetchAll() as $it) {
            $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")
                ->execute([$it['qty'], $it['variant_id']]);
        }
        $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$id]);
        $pdo->commit();

        log_activity($pdo, 'sales', 'delete', "Amefuta mauzo {$sale['sale_code']} (" . money($sale['total_amount']) . "). Stock ya bidhaa husika imerudishwa.");
        json_response(['success' => true, 'message' => 'Mauzo yamefutwa na stock ya bidhaa husika imerudishwa.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Imeshindikana kufuta mauzo: ' . $e->getMessage()]);
    }
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
