<?php
/**
 * FRESH SCENTS - Functions za Msaada (Helper Functions)
 * Mfumo wa Ndani wa Duka - hakuna sehemu ya wateja.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Tuma majibu ya JSON na kusimamisha script */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Safisha maandishi kutoka kwa mtumiaji */
function clean($str) {
    return trim(htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'));
}

/** Tengeneza namba ya kipekee ya mauzo, mfano FS-8X2K91 */
function generate_sale_code($pdo) {
    do {
        $code = 'FS-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = $pdo->prepare("SELECT id FROM sales WHERE sale_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

/** Angalia kama mtumiaji ameingia */
function require_admin_login() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

/** Angalia kama mtumiaji ni super admin */
function is_super_admin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}

/** Angalia kama mtumiaji ni "worker" (Mfanyakazi mwenye ruhusa chache) */
function is_worker() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'worker';
}

/** Angalia kama ni admin au super_admin (siyo worker) */
function is_admin_or_above() {
    return in_array($_SESSION['admin_role'] ?? '', ['super_admin', 'admin'], true);
}

/** Jina la cheo kwa Kiswahili */
function role_label($role) {
    return [
        'super_admin' => 'Msimamizi Mkuu',
        'admin' => 'Msimamizi',
        'worker' => 'Mfanyakazi',
    ][$role] ?? $role;
}

/** Zuia ukurasa kwa cheo maalum tu. Mfano: require_role(['super_admin','admin']); */
function require_role($allowedRoles) {
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;max-width:480px;margin:90px auto;text-align:center;line-height:1.6">
            <h2 style="color:#c0392b">Huna Ruhusa</h2>
            <p>Huna ruhusa ya kuona ukurasa huu. Wasiliana na Msimamizi Mkuu kama unadhani hii ni kosa.</p>
            <a href="dashboard.php" style="color:#8a6d1f;font-weight:700">&larr; Rudi Dashibodi</a>
            </div>');
    }
}

/** Pata mpangilio (setting) mmoja kutoka database */
function get_setting($pdo, $key, $default = '') {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ? $row['setting_value'] : $default;
    return $cache[$key];
}

/** Sasisha mpangilio */
function set_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

/** Fomati fedha kwa TZS */
function money($amount) {
    return number_format((float)$amount, 0) . ' TZS';
}

/** Fomati tarehe kwa Kiswahili (rahisi) */
function fdate($datetime) {
    if (!$datetime) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

/** Kwa API: hakikisha mtumiaji ameingia, la sivyo rudisha JSON 401 */
function require_login_api() {
    if (empty($_SESSION['admin_id'])) {
        json_response(['success' => false, 'message' => 'Tafadhali ingia kwanza.', 'logged_in' => false], 401);
    }
}

/** Kwa API: zuia kwa cheo maalum, rudisha JSON 403 badala ya kufa na HTML */
function require_role_api($allowedRoles) {
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        json_response(['success' => false, 'message' => 'Huna ruhusa ya kufanya hili.'], 403);
    }
}

/** Soma mwili wa JSON wa ombi (kwa maombi ya fetch yanayotuma JSON) */
function read_json_body() {
    $raw = json_decode(file_get_contents('php://input'), true);
    return is_array($raw) ? $raw : [];
}

/**
 * Kokotoa "from"/"to"/"label"/"year" kwa kipindi kilichochaguliwa
 * (daily/weekly/monthly/yearly/custom). Kinatumika na kurasa ZOTE zenye
 * kichujio cha muda (Stock, Matumizi, Malipo ya Wafanyakazi, Uharibifu,
 * Mauzo, Ripoti) ili mahesabu yawe sawa kila mahali (financial accuracy).
 */
function resolve_period_range($pdo, $period, $customFrom = null, $customTo = null, $year = null) {
    $currentYear = (int)date('Y');
    $selectedYear = (int)($year ?? $currentYear);
    if ($selectedYear < 2000 || $selectedYear > $currentYear + 50) $selectedYear = $currentYear;

    switch ($period) {
        case 'weekly':
            $from = date('Y-m-d', strtotime('monday this week'));
            $to = date('Y-m-d', strtotime('sunday this week'));
            $label = 'Wiki Hii (' . date('d/m', strtotime($from)) . ' - ' . date('d/m/Y', strtotime($to)) . ')';
            break;
        case 'monthly':
            $from = date('Y-m-01');
            $to = date('Y-m-t');
            $label = 'Mwezi Huu (' . date('F Y') . ')';
            break;
        case 'yearly':
            $from = $selectedYear . '-01-01';
            $to = $selectedYear . '-12-31';
            $label = ($selectedYear === $currentYear ? 'Mwaka Huu' : 'Mwaka') . ' (' . $selectedYear . ')';
            break;
        case 'custom':
            $from = $customFrom ?: date('Y-m-01');
            $to = $customTo ?: date('Y-m-d');
            $label = 'Kipindi Maalum (' . date('d/m/Y', strtotime($from)) . ' - ' . date('d/m/Y', strtotime($to)) . ')';
            break;
        case 'daily':
        default:
            $period = 'daily';
            $from = date('Y-m-d');
            $to = date('Y-m-d');
            $label = 'Leo (' . date('d/m/Y') . ')';
            break;
    }

    // Kalenda ya miaka: nyuma na mbeleni (mfumo utatumika miaka mingi ijayo),
    // pamoja na miaka yoyote halisi yenye taarifa kwenye database.
    $yearsStmt = $pdo->query("
        SELECT YEAR(created_at) y FROM sales
        UNION SELECT YEAR(expense_date) FROM expenses
        UNION SELECT YEAR(damage_date) FROM damages
        UNION SELECT YEAR(pay_date) FROM salary_payments
        UNION SELECT YEAR(stock_date) FROM stock_ins
    ");
    $dataYears = array_map('intval', array_column($yearsStmt->fetchAll(), 'y'));
    $rangeStart = min(array_merge([2020, $selectedYear], $dataYears));
    $rangeEnd = max(array_merge([$currentYear + 15, $selectedYear], $dataYears));
    $availableYears = range($rangeEnd, $rangeStart, -1);

    return [
        'period' => $period,
        'from' => $from,
        'to' => $to,
        'label' => $label,
        'year' => $selectedYear,
        'available_years' => $availableYears,
    ];
}

/**
 * Kokotoa thamani ya stock (bei ya ununuzi na kuuza) na faida inayotarajiwa,
 * KAMA ILIVYOKUWA hadi tarehe fulani ($asOfDate).
 *
 * NJIA: Tunaanzia na stock_qty ya SASA HIVI (ambayo ndiyo ukweli sahihi wa
 * mwisho), kisha tunarudisha nyuma ("undo") matukio yote yaliyotokea BAADA
 * ya $asOfDate pekee (stock zilizoongezwa, mauzo, na uharibifu). Njia hii ni
 * sahihi zaidi kuliko kujumlisha kutoka mwanzo, kwa sababu baadhi ya bidhaa
 * huwekewa idadi ya awali ya stock wakati zinapoundwa (kwenye ukurasa wa
 * Bidhaa) bila kuwa na rekodi ya "stock_ins" - kujumlisha kutoka mwanzo
 * kunaweza kupunguza makosa idadi kwa bidhaa hizo.
 *
 * KUMBUKA: Bei zinazotumika ni BEI ZA SASA za bidhaa, kwa sababu mfumo
 * hauhifadhi historia ya mabadiliko ya bei ya nyuma.
 */
function stock_snapshot($pdo, $asOfDate) {
    $stmt = $pdo->prepare("
        SELECT pv.id, pv.ml, pv.price, pv.cost_price, p.name, p.brand,
            (pv.stock_qty
                - COALESCE(future_in.total_in, 0)
                + COALESCE(future_sold.total_sold, 0)
                + COALESCE(future_dmg.total_damaged, 0)
            ) AS stock_qty
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        LEFT JOIN (
            SELECT variant_id, SUM(quantity) total_in FROM stock_ins WHERE stock_date > ? GROUP BY variant_id
        ) future_in ON future_in.variant_id = pv.id
        LEFT JOIN (
            SELECT si.variant_id, SUM(si.qty) total_sold FROM sale_items si
            JOIN sales s ON si.sale_id = s.id WHERE DATE(s.created_at) > ? GROUP BY si.variant_id
        ) future_sold ON future_sold.variant_id = pv.id
        LEFT JOIN (
            SELECT variant_id, SUM(quantity) total_damaged FROM damages
            WHERE is_stock_item = 1 AND damage_date > ? GROUP BY variant_id
        ) future_dmg ON future_dmg.variant_id = pv.id
        WHERE p.status = 'active'
        ORDER BY p.name, pv.ml
    ");
    $stmt->execute([$asOfDate, $asOfDate, $asOfDate]);
    $rows = $stmt->fetchAll();

    $buyingValue = 0;
    $sellingValue = 0;
    $totalQty = 0;
    $totalProducts = 0;
    foreach ($rows as &$row) {
        $row['stock_qty'] = max(0, (int)$row['stock_qty']);
        $buyingValue += $row['stock_qty'] * (float)$row['cost_price'];
        $sellingValue += $row['stock_qty'] * (float)$row['price'];
        $totalQty += $row['stock_qty'];
        if ($row['stock_qty'] > 0) $totalProducts++;
    }
    unset($row);

    return [
        'rows' => $rows,
        'as_of' => $asOfDate,
        'buying_value' => $buyingValue,
        'selling_value' => $sellingValue,
        'expected_profit' => $sellingValue - $buyingValue,
        'total_qty' => $totalQty,
        'total_products' => $totalProducts,
    ];
}

/**
 * Rekodi kitendo kwenye "Activity Log" (rejesta ya matukio ya mfumo), ili
 * kufuatilia ni nani alifanya nini na lini. Hii haisimamishi kitendo kikuu
 * hata kama uandikaji wa logi mwenyewe utashindwa kwa sababu yoyote.
 *
 * $module: 'sales','stock','damages','payroll','expenses','auth','products','admins'
 * $action: 'create','update','delete','login','logout'
 * $description: maelezo mafupi ya kibinadamu (Kiswahili) ya kilichotokea
 */
function log_activity($pdo, $module, $action, $description) {
    try {
        $adminId = $_SESSION['admin_id'] ?? null;
        $adminName = $_SESSION['admin_name'] ?? 'Mfumo';
        $pdo->prepare("INSERT INTO activity_log (admin_id, admin_name, module, action, description) VALUES (?,?,?,?,?)")
            ->execute([$adminId, $adminName, $module, $action, $description]);
    } catch (Exception $e) {
        // Usisimamishe kitendo kikuu endapo uandikaji wa logi utashindwa.
    }
}
