<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login_api();
require_role_api(['super_admin', 'admin']);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'list' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = clean($_GET['q'] ?? '');
    $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id=c.id";
    $params = [];
    if ($search) { $sql .= " WHERE p.name LIKE ? OR p.brand LIKE ?"; $params = ["%$search%", "%$search%"]; }
    $sql .= " ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    $variantsAll = $pdo->query("SELECT * FROM product_variants ORDER BY ml ASC")->fetchAll();
    $variantsByProduct = [];
    foreach ($variantsAll as $v) { $variantsByProduct[$v['product_id']][] = $v; }

    foreach ($products as &$p) {
        $p['variants'] = $variantsByProduct[$p['id']] ?? [];
        $p['total_stock'] = array_sum(array_column($p['variants'], 'stock_qty'));
    }
    unset($p);

    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    json_response(['success' => true, 'products' => $products, 'categories' => $categories]);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = clean($_POST['name'] ?? '');
    $brand = clean($_POST['brand'] ?? '');
    $description = clean($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
    $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    $variantIds = $_POST['variant_id'] ?? [];
    $variantMls = $_POST['variant_ml'] ?? [];
    $variantPrices = $_POST['variant_price'] ?? [];
    $variantStocks = $_POST['variant_stock'] ?? [];
    $deletedVariantIds = array_filter(explode(',', $_POST['deleted_variant_ids'] ?? ''));

    $variantRows = [];
    foreach ($variantMls as $i => $mlVal) {
        $ml = (int)$mlVal;
        $price = (float)($variantPrices[$i] ?? 0);
        $stock = (int)($variantStocks[$i] ?? 0);
        $vid = (int)($variantIds[$i] ?? 0);
        if ($ml > 0 && $price > 0) {
            $variantRows[] = ['id' => $vid, 'ml' => $ml, 'price' => $price, 'stock' => $stock];
        }
    }

    if (!$name) {
        json_response(['success' => false, 'message' => 'Tafadhali jaza jina la bidhaa.']);
    }
    if (!$variantRows) {
        json_response(['success' => false, 'message' => 'Ongeza angalau ukubwa (ML) mmoja na bei yake.']);
    }

    $imageName = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $imageName = 'p_' . time() . '_' . rand(100, 999) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $imageName);
        }
    }

    if ($id) {
        if ($imageName) {
            $stmt = $pdo->prepare("UPDATE products SET name=?,brand=?,description=?,category_id=?,status=?,image=? WHERE id=?");
            $stmt->execute([$name, $brand, $description, $category_id, $status, $imageName, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?,brand=?,description=?,category_id=?,status=? WHERE id=?");
            $stmt->execute([$name, $brand, $description, $category_id, $status, $id]);
        }
        $productId = $id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name,brand,description,category_id,status,image,created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$name, $brand, $description, $category_id, $status, $imageName, $_SESSION['admin_id']]);
        $productId = $pdo->lastInsertId();
    }

    if ($deletedVariantIds) {
        $in = implode(',', array_map('intval', $deletedVariantIds));
        $pdo->exec("DELETE FROM product_variants WHERE id IN ($in) AND product_id = $productId");
    }

    $insertVariant = $pdo->prepare("INSERT INTO product_variants (product_id, ml, price, stock_qty) VALUES (?,?,?,?)");
    $updateVariant = $pdo->prepare("UPDATE product_variants SET ml=?, price=?, stock_qty=? WHERE id=? AND product_id=?");
    foreach ($variantRows as $v) {
        if ($v['id']) {
            $updateVariant->execute([$v['ml'], $v['price'], $v['stock'], $v['id'], $productId]);
        } else {
            $insertVariant->execute([$productId, $v['ml'], $v['price'], $v['stock']]);
        }
    }

    json_response(['success' => true, 'message' => $id ? 'Bidhaa imesasishwa kikamilifu.' : 'Bidhaa mpya imeongezwa kikamilifu.']);
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    json_response(['success' => true, 'message' => 'Bidhaa imefutwa.']);
}

json_response(['success' => false, 'message' => 'Ombi halijaeleweka.'], 400);
