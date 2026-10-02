<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();

$today = date('Y-m-d');

$todaySales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) t FROM sales WHERE DATE(created_at) = '$today'")->fetch()['t'];
$todayQtyStmt = $pdo->prepare("SELECT COALESCE(SUM(si.qty),0) q FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) = ?");
$todayQtyStmt->execute([$today]);
$todayQty = $todayQtyStmt->fetch()['q'];

$todayExpStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE expense_date = ?");
$todayExpStmt->execute([$today]);
$todayExpenses = $todayExpStmt->fetch()['t'];

$todayDamageStmt = $pdo->prepare("SELECT COALESCE(SUM(loss_value),0) t FROM damages WHERE damage_date = ?");
$todayDamageStmt->execute([$today]);
$todayDamage = $todayDamageStmt->fetch()['t'];

$todayNetProfit = $todaySales - $todayExpenses - $todayDamage;

$lowStock = $pdo->query("SELECT p.name, pv.ml, pv.price, pv.stock_qty FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    WHERE pv.stock_qty <= 5 AND pv.stock_qty > 0 AND p.status='active' ORDER BY pv.stock_qty ASC LIMIT 6")->fetchAll();

$outOfStock = $pdo->query("SELECT p.name, p.brand, pv.ml FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    WHERE pv.stock_qty <= 0 AND p.status='active' ORDER BY p.name ASC LIMIT 10")->fetchAll();

// "Current Stock Value" - kadirio la haraka la thamani ya stock ya SASA (bei za sasa)
$stockSnap = stock_snapshot($pdo, $today);

$response = [
    'success' => true,
    'today' => [
        'sales' => (float)$todaySales,
        'qty' => (int)$todayQty,
        'expenses' => (float)$todayExpenses,
        'damage_loss' => (float)$todayDamage,
        'net_profit' => (float)$todayNetProfit,
    ],
    'low_stock' => $lowStock,
    'out_of_stock' => $outOfStock,
    'stock_value' => [
        'selling_value' => (float)$stockSnap['selling_value'],
        'buying_value' => (float)$stockSnap['buying_value'],
        'expected_profit' => (float)$stockSnap['expected_profit'],
        'total_products' => (int)$stockSnap['total_products'],
    ],
];

if (is_admin_or_above()) {
    $totalProducts = $pdo->query("SELECT COUNT(*) c FROM products WHERE status='active'")->fetch()['c'];
    $recentSales = $pdo->query("SELECT sale_code, customer_name, total_amount FROM sales ORDER BY created_at DESC LIMIT 8")->fetchAll();

    // Shughuli za Hivi Karibuni - changanya Mauzo + Uharibifu + Stock Mpya, panga kwa muda
    $actSales = $pdo->query("SELECT 'sale' AS type, sale_code AS ref, total_amount AS amount,
        customer_name AS detail, created_at FROM sales ORDER BY created_at DESC LIMIT 6")->fetchAll();

    $actDamages = $pdo->query("SELECT 'damage' AS type,
        COALESCE(p.name, d.item_name) AS ref, d.loss_value AS amount,
        CASE WHEN d.is_stock_item = 1 THEN CONCAT(d.quantity, ' kipande(vipande)') ELSE 'Nje ya Stock' END AS detail,
        d.created_at
        FROM damages d
        LEFT JOIN product_variants pv ON d.variant_id = pv.id
        LEFT JOIN products p ON pv.product_id = p.id
        ORDER BY d.created_at DESC LIMIT 6")->fetchAll();

    $actStockIns = $pdo->query("SELECT 'stock_in' AS type,
        CONCAT(p.name, ' (', pv.ml, 'ml)') AS ref, (s.quantity * s.cost_price) AS amount,
        CONCAT('Idadi: ', s.quantity) AS detail, s.created_at
        FROM stock_ins s
        JOIN product_variants pv ON s.variant_id = pv.id
        JOIN products p ON pv.product_id = p.id
        ORDER BY s.created_at DESC LIMIT 6")->fetchAll();

    $activity = array_merge($actSales, $actDamages, $actStockIns);
    usort($activity, function ($a, $b) { return strtotime($b['created_at']) <=> strtotime($a['created_at']); });
    $activity = array_slice($activity, 0, 10);

    $response['total_products'] = (int)$totalProducts;
    $response['recent_sales'] = $recentSales;
    $response['recent_activity'] = $activity;
}

json_response($response);
