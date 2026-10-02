<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();

use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;

$data = read_json_body();
$action = $_GET['action'] ?? ($data['action'] ?? ($_POST['action'] ?? ''));

if ($action === 'current') {
    // "today" = stock ya SASA HIVI (live). Vinginevyo, tunatumia kichujio cha
    // muda (siku/wiki/mwezi/mwaka/maalum) kuonyesha stock kama ilivyokuwa
    // MWISHONI mwa kipindi hicho (angalia stock_snapshot() kwa maelezo zaidi).
    $periodInput = clean($_GET['period'] ?? 'today');
    $customFrom = clean($_GET['from'] ?? date('Y-m-01'));
    $customTo = clean($_GET['to'] ?? date('Y-m-d'));
    $yearInput = $_GET['year'] ?? null;
    $exportFormat = clean($_GET['export'] ?? '');

    $normalizedPeriod = $periodInput === 'today' ? 'daily' : $periodInput;
    $range = resolve_period_range($pdo, $normalizedPeriod, $customFrom, $customTo, $yearInput);
    $asOfDate = $periodInput === 'today' ? date('Y-m-d') : $range['to'];
    $label = $periodInput === 'today' ? 'Sasa Hivi (Leo)' : $range['label'];

    $snapshot = stock_snapshot($pdo, $asOfDate);

    if ($exportFormat === 'pdf') {
        export_stock_summary_pdf($snapshot, $label, $range);
        exit;
    }
    if ($exportFormat === 'excel') {
        export_stock_summary_excel($snapshot, $label, $range);
        exit;
    }

    json_response([
        'success' => true,
        'stock' => $snapshot['rows'],
        'as_of' => $snapshot['as_of'],
        'period' => $range['period'],
        'label' => $label,
        'from' => $range['from'],
        'to' => $range['to'],
        'year' => $range['year'],
        'available_years' => $range['available_years'],
        'summary' => [
            'buying_value' => $snapshot['buying_value'],
            'selling_value' => $snapshot['selling_value'],
            'expected_profit' => $snapshot['expected_profit'],
            'total_products' => $snapshot['total_products'],
            'total_qty' => $snapshot['total_qty'],
        ],
    ]);
}

/* =========================================================
   BIDHAA ZINAZOKARIBIA KUISHA (LOW STOCK) - Onyesha / Pakua PDF & Excel
========================================================= */
if ($action === 'low_stock') {
    $threshold = 5;
    $lowStock = $pdo->query("SELECT p.name, p.brand, pv.ml, pv.price, pv.cost_price, pv.stock_qty
        FROM product_variants pv JOIN products p ON pv.product_id = p.id
        WHERE pv.stock_qty <= $threshold AND p.status = 'active'
        ORDER BY pv.stock_qty ASC, p.name ASC")->fetchAll();

    $exportFormat = clean($_GET['export'] ?? '');
    if ($exportFormat === 'pdf') {
        export_low_stock_pdf($lowStock, $threshold);
        exit;
    }
    if ($exportFormat === 'excel') {
        export_low_stock_excel($lowStock, $threshold);
        exit;
    }

    json_response(['success' => true, 'low_stock' => $lowStock, 'threshold' => $threshold]);
}

if ($action === 'variants_for_add') {
    require_role_api(['super_admin', 'admin']);
    $variants = $pdo->query("SELECT pv.id, pv.ml, pv.stock_qty, p.name, p.brand FROM product_variants pv
        JOIN products p ON pv.product_id = p.id ORDER BY p.name, pv.ml")->fetchAll();
    json_response(['success' => true, 'variants' => $variants]);
}

if ($action === 'history') {
    require_role_api(['super_admin', 'admin']);
    $history = $pdo->query("SELECT s.*, pv.ml, p.name AS product_name FROM stock_ins s
        JOIN product_variants pv ON s.variant_id = pv.id
        JOIN products p ON pv.product_id = p.id
        ORDER BY s.created_at DESC LIMIT 100")->fetchAll();
    json_response(['success' => true, 'history' => $history]);
}

if ($action === 'add') {
    require_role_api(['super_admin', 'admin']);
    $variant_id = (int)($data['variant_id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 0);
    $cost_price = (float)($data['cost_price'] ?? 0);
    $supplier = clean($data['supplier'] ?? '');
    $stock_date = clean($data['stock_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    if (!$variant_id || $quantity <= 0) {
        json_response(['success' => false, 'message' => 'Tafadhali chagua bidhaa na uweke idadi sahihi.']);
    }

    $pdo->prepare("INSERT INTO stock_ins (variant_id, quantity, cost_price, supplier, stock_date, notes, added_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([$variant_id, $quantity, $cost_price, $supplier, $stock_date, $notes, $_SESSION['admin_id']]);
    $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id=?")->execute([$quantity, $variant_id]);
    if ($cost_price > 0) {
        $pdo->prepare("UPDATE product_variants SET cost_price = ? WHERE id=?")->execute([$cost_price, $variant_id]);
    }

    $vStmt = $pdo->prepare("SELECT p.name, pv.ml FROM product_variants pv JOIN products p ON pv.product_id=p.id WHERE pv.id=?");
    $vStmt->execute([$variant_id]);
    $vInfo = $vStmt->fetch();
    log_activity($pdo, 'stock', 'create', "Ameongeza stock ya {$vInfo['name']} ({$vInfo['ml']}ml): idadi $quantity.");

    json_response(['success' => true, 'message' => 'Stock imeongezwa kikamilifu kwenye ukubwa (ML) uliochagua.']);
}

if ($action === 'update_stock_in') {
    require_role_api(['super_admin', 'admin']);
    $id = (int)($data['id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 0);
    $cost_price = (float)($data['cost_price'] ?? 0);
    $supplier = clean($data['supplier'] ?? '');
    $stock_date = clean($data['stock_date'] ?? date('Y-m-d'));
    $notes = clean($data['notes'] ?? '');

    if (!$id || $quantity <= 0) {
        json_response(['success' => false, 'message' => 'Tafadhali weka idadi sahihi.']);
    }

    $stmt = $pdo->prepare("SELECT s.*, p.name AS product_name, pv.ml FROM stock_ins s
        JOIN product_variants pv ON s.variant_id = pv.id
        JOIN products p ON pv.product_id = p.id WHERE s.id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Rekodi ya stock haikupatikana.']);

    $variantId = $existing['variant_id'];
    $oldQty = (int)$existing['quantity'];
    $delta = $quantity - $oldQty;

    $vStmt = $pdo->prepare("SELECT stock_qty FROM product_variants WHERE id = ?");
    $vStmt->execute([$variantId]);
    $v = $vStmt->fetch();
    if (($v['stock_qty'] + $delta) < 0) {
        json_response(['success' => false, 'message' => 'Haiwezekani kupunguza idadi hii — sehemu ya stock hii tayari imeuzwa au kutumika. Stock ya sasa: ' . $v['stock_qty']]);
    }

    $pdo->prepare("UPDATE stock_ins SET quantity=?, cost_price=?, supplier=?, stock_date=?, notes=? WHERE id=?")
        ->execute([$quantity, $cost_price, $supplier, $stock_date, $notes, $id]);
    $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id=?")->execute([$delta, $variantId]);
    if ($cost_price > 0) {
        $pdo->prepare("UPDATE product_variants SET cost_price = ? WHERE id=?")->execute([$cost_price, $variantId]);
    }

    log_activity($pdo, 'stock', 'update', "Amesasisha rekodi ya stock ya {$existing['product_name']} ({$existing['ml']}ml): idadi kutoka $oldQty kwenda $quantity.");
    json_response(['success' => true, 'message' => 'Rekodi ya stock imesasishwa.']);
}

if ($action === 'delete_stock_in') {
    require_role_api(['super_admin', 'admin']);
    $id = (int)($_GET['id'] ?? $data['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT s.*, p.name AS product_name, pv.ml FROM stock_ins s
        JOIN product_variants pv ON s.variant_id = pv.id
        JOIN products p ON pv.product_id = p.id WHERE s.id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) json_response(['success' => false, 'message' => 'Rekodi ya stock haikupatikana.']);

    $vStmt = $pdo->prepare("SELECT stock_qty FROM product_variants WHERE id = ?");
    $vStmt->execute([$existing['variant_id']]);
    $v = $vStmt->fetch();
    if (($v['stock_qty'] - $existing['quantity']) < 0) {
        json_response(['success' => false, 'message' => 'Haiwezekani kufuta rekodi hii — sehemu ya stock hii tayari imeuzwa au kutumika.']);
    }

    $pdo->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id=?")->execute([$existing['quantity'], $existing['variant_id']]);
    $pdo->prepare("DELETE FROM stock_ins WHERE id=?")->execute([$id]);

    log_activity($pdo, 'stock', 'delete', "Amefuta rekodi ya stock ya {$existing['product_name']} ({$existing['ml']}ml), idadi {$existing['quantity']}.");
    json_response(['success' => true, 'message' => 'Rekodi ya stock imefutwa, na stock imepunguzwa ipasavyo.']);
}

/* =========================================================
   EXCEL IMPORT / TEMPLATE / EXPORT kwa Add Stock
========================================================= */

// Pakua kiolezo (template) cha Excel
if ($action === 'template') {
    require_role_api(['super_admin', 'admin']);
    require_once __DIR__ . '/../vendor/simplexlsxgen/SimpleXLSXGen.php';

    $rows = [
        ['Jina la Bidhaa (Product/Perfume Name)', 'Idadi (Quantity)', 'Ukubwa ML (Unit)', 'Bei ya Ununuzi Jumla - Hiari (Amount/Cost Price)', 'Bei ya Kuuza (Selling Price)', 'Hali (Status)'],
        ['Fresh Scents Royal Oud', 10, 50, 220000, 32000, ''],
        ['Perfume Mpya Kabisa (mfano)', 5, 30, '', 25000, ''],
    ];
    $xlsx = SimpleXLSXGen::fromArray($rows, 'Kiolezo cha Stock');
    $xlsx->downloadAs('kiolezo_stock_fresh_scents.xlsx');
    exit;
}

// Soma faili la Excel/CSV lililopakiwa na urudishe "preview" kabla ya kuthibitisha
if ($action === 'import_preview') {
    require_role_api(['super_admin', 'admin']);
    require_once __DIR__ . '/../vendor/simplexlsx/SimpleXLSX.php';

    if (empty($_FILES['file']['tmp_name'])) {
        json_response(['success' => false, 'message' => 'Tafadhali chagua faili la Excel/CSV kwanza.']);
    }

    $tmpPath = $_FILES['file']['tmp_name'];
    $origName = $_FILES['file']['name'] ?? 'stock.xlsx';
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    $rawRows = [];
    if ($ext === 'csv') {
        if (($fh = fopen($tmpPath, 'r')) !== false) {
            while (($r = fgetcsv($fh)) !== false) $rawRows[] = $r;
            fclose($fh);
        }
    } else {
        $xlsx = SimpleXLSX::parse($tmpPath);
        if (!$xlsx) {
            json_response(['success' => false, 'message' => 'Imeshindikana kusoma faili hili. Hakikisha ni faili halisi la Excel (.xlsx) au .csv, lililotengenezwa kutoka kwenye kiolezo.']);
        }
        $rawRows = $xlsx->rows();
    }

    if (count($rawRows) < 2) {
        json_response(['success' => false, 'message' => 'Faili halina data ya kutosha. Hakikisha safu ya kwanza ni vichwa vya habari na kuna angalau bidhaa moja chini yake.']);
    }

    [$rows, $errorCount] = build_stock_import_rows($pdo, $rawRows);

    json_response(['success' => true, 'rows' => $rows, 'has_errors' => $errorCount > 0]);
}

// Thibitisha na uingize rekodi za stock kutoka kwenye "preview" (baada ya mtumiaji kuhariri kama ni lazima)
if ($action === 'import_confirm') {
    require_role_api(['super_admin', 'admin']);
    $rows = $data['rows'] ?? [];
    if (!$rows || !is_array($rows)) {
        json_response(['success' => false, 'message' => 'Hakuna data ya kuingiza.']);
    }

    // Thibitisha upya kila mstari (usitegemee data ya zamani ya preview)
    $clean = [];
    foreach ($rows as $row) {
        $name = clean($row['name'] ?? '');
        $ml = (int)($row['ml'] ?? 0);
        $quantity = (int)($row['quantity'] ?? 0);
        $sellingPrice = (float)($row['selling_price'] ?? 0);
        $amount = (float)($row['amount'] ?? 0);

        $errs = [];
        if (!$name) $errs[] = 'Jina la bidhaa halipo.';
        if ($ml <= 0) $errs[] = 'Ukubwa (ML) sio sahihi.';
        if ($quantity <= 0) $errs[] = 'Idadi sio sahihi.';
        if ($sellingPrice <= 0) $errs[] = 'Bei ya kuuza sio sahihi.';

        if ($errs) {
            json_response(['success' => false, 'message' => 'Kuna hitilafu kwenye mstari wa "' . ($name ?: '—') . '": ' . implode(' ', $errs)]);
        }

        // Unganisha mistari inayofanana (jina + ml sawa) ili kuepuka kuongeza mara mbili
        $key = mb_strtolower(trim($name)) . '|' . $ml;
        if (isset($clean[$key])) {
            $clean[$key]['quantity'] += $quantity;
            $clean[$key]['amount'] += $amount;
            $clean[$key]['selling_price'] = $sellingPrice; // tumia bei ya mwisho iliyowekwa
        } else {
            $clean[$key] = ['name' => $name, 'ml' => $ml, 'quantity' => $quantity, 'amount' => $amount, 'selling_price' => $sellingPrice];
        }
    }

    $pdo->beginTransaction();
    try {
        $newCount = 0;
        $existingCount = 0;

        foreach ($clean as $row) {
            $costPerUnit = ($row['amount'] > 0 && $row['quantity'] > 0) ? round($row['amount'] / $row['quantity'], 2) : null;

            [$product, $variant] = stock_import_resolve($pdo, $row['name'], $row['ml']);

            if (!$product) {
                // Bidhaa mpya kabisa
                $stmt = $pdo->prepare("INSERT INTO products (name, status, created_by) VALUES (?, 'active', ?)");
                $stmt->execute([$row['name'], $_SESSION['admin_id']]);
                $productId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, ml, price, cost_price, stock_qty) VALUES (?,?,?,?,?)");
                $stmt->execute([$productId, $row['ml'], $row['selling_price'], $costPerUnit ?: 0, $row['quantity']]);
                $variantId = $pdo->lastInsertId();
                $newCount++;
            } elseif (!$variant) {
                // Bidhaa ipo, lakini ukubwa (ML) huu ni mpya kwa bidhaa hiyo
                $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, ml, price, cost_price, stock_qty) VALUES (?,?,?,?,?)");
                $stmt->execute([$product['id'], $row['ml'], $row['selling_price'], $costPerUnit ?: 0, $row['quantity']]);
                $variantId = $pdo->lastInsertId();
                $existingCount++;
            } else {
                // Bidhaa na ukubwa (ML) vipo tayari — ongeza tu idadi
                $updateSql = "UPDATE product_variants SET stock_qty = stock_qty + ?, price = ?";
                $params = [$row['quantity'], $row['selling_price']];
                if ($costPerUnit !== null) {
                    $updateSql .= ", cost_price = ?";
                    $params[] = $costPerUnit;
                }
                $updateSql .= " WHERE id = ?";
                $params[] = $variant['id'];
                $pdo->prepare($updateSql)->execute($params);
                $variantId = $variant['id'];
                $existingCount++;
            }

            $pdo->prepare("INSERT INTO stock_ins (variant_id, quantity, cost_price, supplier, stock_date, notes, added_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$variantId, $row['quantity'], $costPerUnit ?: 0, 'Excel Import', date('Y-m-d'), 'Imeongezwa kupitia Excel Import', $_SESSION['admin_id']]);
        }

        $pdo->commit();
        log_activity($pdo, 'stock', 'create', "Ameingiza stock kupitia Excel Import: bidhaa mpya $newCount, bidhaa/ukubwa zilizoongezwa stock $existingCount.");
        json_response(['success' => true, 'message' => "Uingizaji umekamilika! Bidhaa mpya: $newCount, Bidhaa/ukubwa zilizoongezwa stock: $existingCount."]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Imeshindikana kuingiza stock: ' . $e->getMessage()]);
    }
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);

/**
 * Tafuta bidhaa (na ukubwa/ML wake kama upo) kwa jina.
 * Inarudisha [$product, $variant] — $variant ni null kama bidhaa ipo lakini ML hiyo bado haipo.
 */
function stock_import_resolve($pdo, $name, $ml) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
    $stmt->execute([$name]);
    $product = $stmt->fetch();
    if (!$product) return [null, null];

    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND ml = ? LIMIT 1");
    $stmt->execute([$product['id'], $ml]);
    $variant = $stmt->fetch();
    return [$product, $variant ?: null];
}

/**
 * Geuza safu ghafi (kutoka Excel/CSV) kuwa rekodi zenye muundo tayari kwa "preview",
 * ikiwa ni pamoja na uthibitishaji wa awali na hali (Mpya / Ipo Tayari).
 */
function build_stock_import_rows($pdo, $rawRows) {
    $header = array_map(function ($h) { return mb_strtolower(trim((string)$h)); }, $rawRows[0]);

    $findCol = function ($keywords) use ($header) {
        foreach ($header as $idx => $h) {
            foreach ($keywords as $kw) {
                if (strpos($h, $kw) !== false) return $idx;
            }
        }
        return null;
    };

    $colName = $findCol(['jina', 'product', 'name', 'perfume']);
    $colQty = $findCol(['idadi', 'quantity', 'qty']);
    $colMl = $findCol(['ukubwa', 'ml', 'unit', 'unity']);
    $colAmount = $findCol(['ununuzi', 'amount', 'cost']);
    $colSelling = $findCol(['kuuza', 'selling']);

    // Kama vichwa havikutambulika, tumia mpangilio wa kiolezo chetu: A-F
    if ($colName === null) $colName = 0;
    if ($colQty === null) $colQty = 1;
    if ($colMl === null) $colMl = 2;
    if ($colAmount === null) $colAmount = 3;
    if ($colSelling === null) $colSelling = 4;

    $rows = [];
    $errorCount = 0;

    for ($i = 1; $i < count($rawRows); $i++) {
        $r = $rawRows[$i];
        $name = trim((string)($r[$colName] ?? ''));
        if ($name === '' && count(array_filter($r, function ($v) { return trim((string)$v) !== ''; })) === 0) {
            continue; // safu tupu kabisa - ruka
        }

        $qtyRaw = trim((string)($r[$colQty] ?? ''));
        $mlRaw = trim((string)($r[$colMl] ?? ''));
        $amountRaw = trim((string)($r[$colAmount] ?? ''));
        $sellingRaw = trim((string)($r[$colSelling] ?? ''));

        $quantity = (int)preg_replace('/[^0-9\-]/', '', $qtyRaw);
        preg_match('/[0-9]+(\.[0-9]+)?/', $mlRaw, $mlMatch);
        $ml = isset($mlMatch[0]) ? (int)round((float)$mlMatch[0]) : 0;
        $amount = (float)preg_replace('/[^0-9.\-]/', '', $amountRaw);
        $sellingPrice = (float)preg_replace('/[^0-9.\-]/', '', $sellingRaw);

        $errors = [];
        if ($name === '') $errors[] = 'Jina la bidhaa halipo.';
        if ($quantity <= 0) $errors[] = 'Idadi haipo au si sahihi.';
        if ($ml <= 0) $errors[] = 'Ukubwa (ML) haipo au si sahihi.';
        if ($sellingPrice <= 0) $errors[] = 'Bei ya kuuza haipo au si sahihi.';

        $status = 'new';
        if ($name !== '' && $ml > 0) {
            [$product, $variant] = stock_import_resolve($pdo, $name, $ml);
            if ($product) $status = 'existing';
        }

        if ($errors) $errorCount++;

        $rows[] = [
            'row' => $i + 1,
            'name' => $name,
            'quantity' => $quantity,
            'ml' => $ml,
            'amount' => $amount,
            'selling_price' => $sellingPrice,
            'status' => $status,
            'errors' => $errors,
        ];
    }

    return [$rows, $errorCount];
}

/** Chapisha muhtasari wa Stock (kama ilivyokuwa mwishoni mwa kipindi) kama PDF */
function export_stock_summary_pdf($snapshot, $label, $range) {
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

    $pdf = new FPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(12, 12, 12);
    $t = function ($s) { return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s); };

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 9, $t('FRESH SCENTS'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, $t('Stock: ' . $label), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 6, $t('Kama ilivyokuwa: ' . $range['from'] . ' hadi ' . $range['to'] . '  |  Imetolewa: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(245, 236, 210);
    $summaryRows = [
        ['Idadi ya Bidhaa Zenye Stock', (string)$snapshot['total_products']],
        ['Jumla ya Vipande Stoo', (string)$snapshot['total_qty']],
        ['Thamani ya Stock (Bei ya Ununuzi)', money($snapshot['buying_value'])],
        ['Thamani ya Stock (Bei ya Kuuza)', money($snapshot['selling_value'])],
        ['Faida Inayotarajiwa', money($snapshot['expected_profit'])],
    ];
    $pdf->SetFont('Arial', '', 10);
    foreach ($summaryRows as $sr) {
        $pdf->Cell(95, 8, $t($sr[0]), 1, 0, 'L', true);
        $pdf->Cell(80, 8, $t($sr[1]), 1, 1, 'R');
    }
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 236, 210);
    $pdf->Cell(70, 7, 'Bidhaa', 1, 0, 'L', true);
    $pdf->Cell(20, 7, 'ML', 1, 0, 'C', true);
    $pdf->Cell(28, 7, 'Bei Kuuza', 1, 0, 'R', true);
    $pdf->Cell(28, 7, 'Bei Ununuzi', 1, 0, 'R', true);
    $pdf->Cell(24, 7, 'Idadi', 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 8.5);
    if ($snapshot['rows']) {
        foreach ($snapshot['rows'] as $row) {
            if ($row['stock_qty'] <= 0) continue;
            if ($pdf->GetY() > 270) { $pdf->AddPage(); }
            $pdf->Cell(70, 6.5, $t($row['name'] . ($row['brand'] ? ' (' . $row['brand'] . ')' : '')), 1);
            $pdf->Cell(20, 6.5, $row['ml'] . 'ml', 1, 0, 'C');
            $pdf->Cell(28, 6.5, money($row['price']), 1, 0, 'R');
            $pdf->Cell(28, 6.5, money($row['cost_price']), 1, 0, 'R');
            $pdf->Cell(24, 6.5, (string)$row['stock_qty'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(170, 8, $t('Hakuna stock kwenye kipindi hiki.'), 1, 1, 'C');
    }
    $pdf->SetFont('Arial', 'I', 7.5);
    $pdf->Cell(170, 5, $t('Kumbuka: bei zinazotumika hapa ni bei za SASA za bidhaa.'), 0, 1);

    while (ob_get_level()) ob_end_clean();
    $filename = 'stock_' . $range['period'] . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
}

/** Chapisha muhtasari wa Stock (kama ilivyokuwa mwishoni mwa kipindi) kama Excel (.xlsx) */
function export_stock_summary_excel($snapshot, $label, $range) {
    require_once __DIR__ . '/../vendor/simplexlsxgen/SimpleXLSXGen.php';

    $sheet = [
        ['FRESH SCENTS - STOCK: ' . $label],
        ['Kama ilivyokuwa', $range['from'] . ' hadi ' . $range['to']],
        ['Imetolewa', date('d/m/Y H:i')],
        [],
        ['Idadi ya Bidhaa Zenye Stock', $snapshot['total_products']],
        ['Jumla ya Vipande Stoo', $snapshot['total_qty']],
        ['Thamani ya Stock (Bei ya Ununuzi)', $snapshot['buying_value']],
        ['Thamani ya Stock (Bei ya Kuuza)', $snapshot['selling_value']],
        ['Faida Inayotarajiwa', $snapshot['expected_profit']],
        [],
        ['Bidhaa', 'Brand', 'ML', 'Bei ya Kuuza', 'Bei ya Ununuzi', 'Idadi'],
    ];
    foreach ($snapshot['rows'] as $row) {
        if ($row['stock_qty'] <= 0) continue;
        $sheet[] = [$row['name'], $row['brand'] ?: '-', $row['ml'], $row['price'], $row['cost_price'], $row['stock_qty']];
    }

    $xlsx = SimpleXLSXGen::fromArray($sheet, 'Stock');
    $filename = 'stock_' . $range['period'] . '_' . date('Ymd_His') . '.xlsx';
    $xlsx->downloadAs($filename);
}

/** Chapisha orodha ya "Bidhaa Zinazokaribia Kuisha" (Low Stock) kama faili la PDF */
function export_low_stock_pdf($rows, $threshold) {
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

    $pdf = new FPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(12, 12, 12);

    $t = function ($s) { return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s); };

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 9, $t('FRESH SCENTS'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 7, $t('Bidhaa Zinazokaribia Kuisha (Low Stock)'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 6, $t('Kiwango: Stock <= ' . $threshold . '  |  Imetolewa: ' . date('d/m/Y H:i')), 0, 1, 'C');
    $pdf->Ln(6);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 236, 210);
    $pdf->Cell(65, 7, 'Bidhaa', 1, 0, 'L', true);
    $pdf->Cell(20, 7, 'ML', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Bei ya Kuuza', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'Bei ya Ununuzi', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'Stock Iliyopo', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    if ($rows) {
        foreach ($rows as $row) {
            if ($pdf->GetY() > 270) { $pdf->AddPage(); }
            $pdf->Cell(65, 6.5, $t($row['name'] . ($row['brand'] ? ' (' . $row['brand'] . ')' : '')), 1);
            $pdf->Cell(20, 6.5, $row['ml'] . 'ml', 1, 0, 'C');
            $pdf->Cell(30, 6.5, money($row['price']), 1, 0, 'R');
            $pdf->Cell(30, 6.5, money($row['cost_price']), 1, 0, 'R');
            $pdf->SetTextColor(200, 30, 30);
            $pdf->Cell(30, 6.5, (string)$row['stock_qty'], 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }
    } else {
        $pdf->Cell(175, 8, $t('Bidhaa zote ziko kwenye kiwango kizuri cha stock.'), 1, 1, 'C');
    }

    while (ob_get_level()) ob_end_clean();
    $filename = 'bidhaa_zinazokaribia_kuisha_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
}

/** Chapisha orodha ya "Bidhaa Zinazokaribia Kuisha" (Low Stock) kama faili la Excel (.xlsx) */
function export_low_stock_excel($rows, $threshold) {
    require_once __DIR__ . '/../vendor/simplexlsxgen/SimpleXLSXGen.php';

    $sheet = [
        ['FRESH SCENTS - BIDHAA ZINAZOKARIBIA KUISHA (LOW STOCK)'],
        ['Kiwango kilichotumika', 'Bidhaa zenye stock ya ' . $threshold . ' au chini'],
        ['Imetolewa', date('d/m/Y H:i')],
        [],
        ['Bidhaa', 'Brand', 'ML', 'Bei ya Kuuza', 'Bei ya Ununuzi', 'Stock Iliyopo'],
    ];
    foreach ($rows as $row) {
        $sheet[] = [$row['name'], $row['brand'] ?: '-', $row['ml'], $row['price'], $row['cost_price'], $row['stock_qty']];
    }

    $xlsx = SimpleXLSXGen::fromArray($sheet, 'Low Stock');
    $filename = 'bidhaa_zinazokaribia_kuisha_' . date('Ymd_His') . '.xlsx';
    $xlsx->downloadAs($filename);
}
