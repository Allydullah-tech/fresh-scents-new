<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();
require_role_api(['super_admin', 'admin']);

use Shuchkin\SimpleXLSXGen;

$periodInput = clean($_GET['period'] ?? 'daily');
$customFrom = clean($_GET['from'] ?? date('Y-m-01'));
$customTo = clean($_GET['to'] ?? date('Y-m-d'));
$yearInput = $_GET['year'] ?? null;
$exportFormat = clean($_GET['export'] ?? '');

$range = resolve_period_range($pdo, $periodInput, $customFrom, $customTo, $yearInput);
$period = $range['period'];
$from = $range['from'];
$to = $range['to'];
$label = $range['label'];
$selectedYear = $range['year'];
$availableYears = $range['available_years'];

$salesStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) t, COUNT(*) c FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
$salesStmt->execute([$from, $to]);
$salesRow = $salesStmt->fetch();

$qtyStmt = $pdo->prepare("SELECT COALESCE(SUM(si.qty),0) q FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE DATE(s.created_at) BETWEEN ? AND ?");
$qtyStmt->execute([$from, $to]);
$totalQty = $qtyStmt->fetch()['q'];

$expStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) t FROM expenses WHERE expense_date BETWEEN ? AND ?");
$expStmt->execute([$from, $to]);
$totalExpenses = $expStmt->fetch()['t'];

$damageStmt = $pdo->prepare("SELECT COALESCE(SUM(loss_value),0) t, COALESCE(SUM(quantity),0) q FROM damages WHERE damage_date BETWEEN ? AND ?");
$damageStmt->execute([$from, $to]);
$damageRow = $damageStmt->fetch();

$salaryStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) t FROM salary_payments WHERE pay_date BETWEEN ? AND ?");
$salaryStmt->execute([$from, $to]);
$totalSalaries = $salaryStmt->fetch()['t'];

$netProfit = $salesRow['t'] - $totalExpenses - $damageRow['t'];

$topProductsStmt = $pdo->prepare("SELECT si.product_name, si.ml, SUM(si.qty) qty, SUM(si.subtotal) total
    FROM sale_items si JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY si.product_name, si.ml ORDER BY qty DESC LIMIT 8");
$topProductsStmt->execute([$from, $to]);
$topProducts = $topProductsStmt->fetchAll();

$salesListStmt = $pdo->prepare("SELECT s.*, a.full_name AS admin_name FROM sales s
    LEFT JOIN admins a ON s.recorded_by = a.id
    WHERE DATE(s.created_at) BETWEEN ? AND ? ORDER BY s.created_at DESC");
$salesListStmt->execute([$from, $to]);
$salesList = $salesListStmt->fetchAll();

// Thamani ya stock KAMA ILIVYOKUWA mwisho wa kipindi kilichochaguliwa (tarehe "$to"),
// ili takwimu hizi ziendane na kipindi husika (bei zinazotumika ni bei za sasa - angalia
// maelezo kwenye stock_snapshot()).
$stock = stock_snapshot($pdo, $to);

$report = [
    'success' => true,
    'period' => $period,
    'label' => $label,
    'from' => $from,
    'to' => $to,
    'year' => $selectedYear,
    'available_years' => $availableYears,
    'total_sales' => (float)$salesRow['t'],
    'total_transactions' => (int)$salesRow['c'],
    'total_qty' => (int)$totalQty,
    'total_expenses' => (float)$totalExpenses,
    'total_damage_value' => (float)$damageRow['t'],
    'total_damage_qty' => (int)$damageRow['q'],
    'total_salaries_paid' => (float)$totalSalaries,
    'net_profit' => (float)$netProfit,
    'stock_buying_value' => (float)$stock['buying_value'],
    'stock_selling_value' => (float)$stock['selling_value'],
    'stock_expected_profit' => (float)$stock['expected_profit'],
    'stock_total_products' => (int)$stock['total_products'],
    'stock_total_qty' => (int)$stock['total_qty'],
    'top_products' => $topProducts,
    'sales_list' => $salesList,
];

if ($exportFormat === 'pdf') {
    export_report_pdf($report);
    exit;
}
if ($exportFormat === 'excel') {
    export_report_excel($report);
    exit;
}

json_response($report);

/** Chapisha ripoti kama faili la PDF (linatumia bidhaa/mauzo yaliyochujwa kwa kipindi husika) */
function export_report_pdf($r) {
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

    $pdf = new FPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(12, 12, 12);

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 9, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'FRESH SCENTS'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Ripoti: ' . $r['label']), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Kipindi: ' . $r['from'] . ' hadi ' . $r['to'] . '  |  Imetolewa: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Muhtasari wa Fedha (Kipindi Kilichochaguliwa)'), 0, 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(245, 236, 210);
    $summaryRows = [
        ['Jumla ya Mauzo', money($r['total_sales'])],
        ['Idadi ya Miamala', (string)$r['total_transactions']],
        ['Manukato Yaliyouzwa', (string)$r['total_qty']],
        ['Jumla ya Matumizi', money($r['total_expenses'])],
        ['Hasara ya Uharibifu (' . $r['total_damage_qty'] . ' vipande)', money($r['total_damage_value'])],
        ['Mishahara Iliyolipwa', money($r['total_salaries_paid'])],
        ['Faida Halisi', money($r['net_profit'])],
    ];
    $pdf->SetFont('Arial', '', 10);
    foreach ($summaryRows as $sr) {
        $pdf->Cell(95, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $sr[0]), 1, 0, 'L', true);
        $pdf->Cell(80, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $sr[1]), 1, 1, 'R');
    }
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Thamani ya Stock (Mwisho wa Kipindi)'), 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $stockRows = [
        ['Idadi ya Bidhaa Zenye Stock', (string)$r['stock_total_products']],
        ['Jumla ya Vipande Stoo', (string)$r['stock_total_qty']],
        ['Thamani ya Stock (Bei ya Ununuzi)', money($r['stock_buying_value'])],
        ['Thamani ya Stock (Bei ya Kuuza)', money($r['stock_selling_value'])],
        ['Faida Inayotarajiwa (Stock ya Sasa)', money($r['stock_expected_profit'])],
    ];
    foreach ($stockRows as $sr) {
        $pdf->Cell(95, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $sr[0]), 1, 0, 'L', true);
        $pdf->Cell(80, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $sr[1]), 1, 1, 'R');
    }
    $pdf->SetFont('Arial', 'I', 7.5);
    $pdf->Cell(175, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Kumbuka: bei zinazotumika hapa ni bei za SASA za bidhaa.'), 0, 1);
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Bidhaa Zinazouzwa Zaidi'), 0, 1);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 236, 210);
    $pdf->Cell(95, 7, 'Bidhaa', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Idadi', 1, 0, 'C', true);
    $pdf->Cell(45, 7, 'Jumla', 1, 1, 'R', true);
    $pdf->SetFont('Arial', '', 9);
    if ($r['top_products']) {
        foreach ($r['top_products'] as $tp) {
            if ($pdf->GetY() > 270) { $pdf->AddPage(); }
            $pdf->Cell(95, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $tp['product_name'] . ' (' . $tp['ml'] . 'ml)'), 1);
            $pdf->Cell(35, 7, (string)$tp['qty'], 1, 0, 'C');
            $pdf->Cell(45, 7, money($tp['total']), 1, 1, 'R');
        }
    } else {
        $pdf->Cell(175, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Hakuna data kwa kipindi hiki.'), 1, 1, 'C');
    }
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Orodha ya Mauzo kwenye Kipindi Hiki'), 0, 1);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 236, 210);
    $pdf->Cell(35, 7, 'Namba', 1, 0, 'L', true);
    $pdf->Cell(55, 7, 'Mteja', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Kiasi', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'Aliyeuza', 1, 0, 'L', true);
    $pdf->Cell(20, 7, 'Tarehe', 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 8.5);
    if ($r['sales_list']) {
        foreach ($r['sales_list'] as $s) {
            if ($pdf->GetY() > 270) { $pdf->AddPage(); }
            $pdf->Cell(35, 6.5, $s['sale_code'], 1);
            $pdf->Cell(55, 6.5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s['customer_name'] ?: '-'), 1);
            $pdf->Cell(35, 6.5, money($s['total_amount']), 1, 0, 'R');
            $pdf->Cell(30, 6.5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s['admin_name'] ?: '-'), 1);
            $pdf->Cell(20, 6.5, date('d/m/y', strtotime($s['created_at'])), 1, 1);
        }
    } else {
        $pdf->Cell(175, 7, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Hakuna mauzo katika kipindi hiki.'), 1, 1, 'C');
    }

    while (ob_get_level()) ob_end_clean();
    $filename = 'ripoti_' . $r['period'] . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
}

/** Chapisha ripoti kama faili la Excel (.xlsx) */
function export_report_excel($r) {
    require_once __DIR__ . '/../vendor/simplexlsxgen/SimpleXLSXGen.php';

    $summary = [
        ['FRESH SCENTS - RIPOTI'],
        ['Kipindi', $r['label']],
        ['Tarehe', $r['from'] . ' hadi ' . $r['to']],
        [],
        ['Jumla ya Mauzo', $r['total_sales']],
        ['Idadi ya Miamala', $r['total_transactions']],
        ['Manukato Yaliyouzwa', $r['total_qty']],
        ['Jumla ya Matumizi', $r['total_expenses']],
        ['Hasara ya Uharibifu', $r['total_damage_value']],
        ['Mishahara Iliyolipwa', $r['total_salaries_paid']],
        ['Faida Halisi', $r['net_profit']],
        [],
        ['THAMANI YA STOCK (mwisho wa kipindi, bei za sasa)'],
        ['Idadi ya Bidhaa Zenye Stock', $r['stock_total_products']],
        ['Jumla ya Vipande Stoo', $r['stock_total_qty']],
        ['Thamani ya Stock (Bei ya Ununuzi)', $r['stock_buying_value']],
        ['Thamani ya Stock (Bei ya Kuuza)', $r['stock_selling_value']],
        ['Faida Inayotarajiwa (Stock ya Sasa)', $r['stock_expected_profit']],
    ];

    $topProducts = [['Bidhaa', 'ML', 'Idadi', 'Jumla']];
    foreach ($r['top_products'] as $tp) {
        $topProducts[] = [$tp['product_name'], $tp['ml'], $tp['qty'], $tp['total']];
    }

    $sales = [['Namba', 'Mteja', 'Simu', 'Malipo', 'Kiasi', 'Aliyeuza', 'Tarehe']];
    foreach ($r['sales_list'] as $s) {
        $sales[] = [$s['sale_code'], $s['customer_name'] ?: '-', $s['customer_phone'] ?: '-', $s['payment_method'], $s['total_amount'], $s['admin_name'] ?: '-', $s['created_at']];
    }

    $xlsx = SimpleXLSXGen::fromArray($summary, 'Muhtasari');
    $xlsx->addSheet($topProducts, 'Bidhaa Bora');
    $xlsx->addSheet($sales, 'Mauzo');

    $filename = 'ripoti_' . $r['period'] . '_' . date('Ymd_His') . '.xlsx';
    $xlsx->downloadAs($filename);
}
