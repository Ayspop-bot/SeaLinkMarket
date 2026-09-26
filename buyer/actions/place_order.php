<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/place_order.php
 * Purpose: Process checkout and insert records into order_tbl and order_item_tbl (supporting selected items only).
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/buyer/cart.php');
}

$buyer_id = (int)$_SESSION['user_id'];
$mode = trim((string)($_POST['mode'] ?? 'cart'));
$reorder_order_id = (int)($_POST['reorder_order_id'] ?? 0);
$checkout_redirect = '/buyer/checkout.php?mode=' . urlencode($mode);
if ($mode === 'reorder' && $reorder_order_id > 0) {
    $checkout_redirect .= '&order_id=' . $reorder_order_id;
}

$payment_method = trim((string)($_POST['payment_method'] ?? 'COD'));
$fulfillment_type = trim((string)($_POST['fulfillment_type'] ?? 'Delivery'));
$delivery_fee_per_seller = 50.00;

if (!in_array($payment_method, ['COD', 'GCash'], true)) {
    set_message('error', 'Invalid payment method.');
    redirect_path($checkout_redirect);
}

if (!in_array($fulfillment_type, ['Delivery', 'Pickup', 'Drop-off'], true)) {
    $fulfillment_type = 'Delivery';
}

/* ========== HANDLE SHIPPING ADDRESS ========== */
$address_id = (int)($_POST['address_id'] ?? 0);
$address_select = $_POST['address_id'] ?? '';

if ($address_select === 'new' || $address_id <= 0) {
    $street       = normalize_spaces($_POST['street'] ?? '');
    $municipality = normalize_spaces($_POST['municipality'] ?? 'Santa Fe');
    $province     = normalize_spaces($_POST['province'] ?? 'Romblon');
    $zip_code     = sanitize_input($_POST['zip_code'] ?? '5505');

    if ($street === '') {
        set_message('error', 'Please provide a valid delivery street/address.');
        redirect_path($checkout_redirect);
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO buyer_address_tbl (buyer_id, street, municipality, province, zip_code, is_default) VALUES (?, ?, ?, ?, ?, 0)");
    mysqli_stmt_bind_param($stmt, "issss", $buyer_id, $street, $municipality, $province, $zip_code);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        set_message('error', 'Failed to save address.');
        redirect_path($checkout_redirect);
    }
    $address_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
} else {
    // Validate that address belongs to this buyer
    $stmt = mysqli_prepare($conn, "SELECT address_id FROM buyer_address_tbl WHERE address_id = ? AND buyer_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $address_id, $buyer_id);
    mysqli_stmt_execute($stmt);
    $addr_check = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$addr_check) {
        set_message('error', 'Invalid delivery address selected.');
        redirect_path($checkout_redirect);
    }
}

/* ========== HANDLE GCASH SCREENSHOT UPLOAD ========== */
$gcash_screenshot_url = null;
if ($payment_method === 'GCash') {
    if (!isset($_FILES['gcash_screenshot']) || $_FILES['gcash_screenshot']['error'] !== UPLOAD_ERR_OK) {
        set_message('error', 'Please upload a screenshot of your GCash receipt.');
        redirect_path($checkout_redirect);
    }

    $tmp  = $_FILES['gcash_screenshot']['tmp_name'];
    $size = (int)$_FILES['gcash_screenshot']['size'];
    $max  = 2 * 1024 * 1024;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        set_message('error', 'GCash receipt must be a JPG or PNG image.');
        redirect_path($checkout_redirect);
    }

    if ($size > $max) {
        set_message('error', 'Receipt image must not exceed 2MB.');
        redirect_path($checkout_redirect);
    }

    $ext = ($mime === 'image/png') ? 'png' : 'jpg';
    $filename = 'gcash_' . $buyer_id . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dir = __DIR__ . '/../../uploads/gcash_screenshots/';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    if (!move_uploaded_file($tmp, $dir . $filename)) {
        set_message('error', 'Failed to save payment receipt.');
        redirect_path($checkout_redirect);
    }

    $gcash_screenshot_url = 'uploads/gcash_screenshots/' . $filename;
}

/* ========== FETCH ORDER ITEMS ========== */
$items = [];
$cart_item_ids_to_clear = [];

if ($mode === 'buynow') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $qty        = max(1, (int)($_POST['qty'] ?? 1));

    $stmt = mysqli_prepare($conn, "SELECT product_id, farmer_id, name, price, discounted_price, selling_deadline, stock_quantity FROM product_tbl WHERE product_id = ? AND status = 'Active'");
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$product || (int)$product['stock_quantity'] <= 0) {
        set_message('error', 'The product you are trying to buy is out of stock.');
        redirect_path('/buyer/market.php');
    }

    $qty = min($qty, (int)$product['stock_quantity']);
    $product['quantity'] = $qty;
    $items[] = $product;

} elseif ($mode === 'reorder') {
    $reorder_items_post = $_POST['reorder_items'] ?? [];

    if ($reorder_order_id > 0) {
        $ro_sql = "
            SELECT oi.product_id, oi.quantity, p.farmer_id, p.name, p.price, p.discounted_price, p.selling_deadline, p.stock_quantity
            FROM order_item_tbl oi
            JOIN product_tbl p ON p.product_id = oi.product_id
            WHERE oi.order_id = ? AND p.status = 'Active' AND p.stock_quantity > 0
        ";
        $stmt = mysqli_prepare($conn, $ro_sql);
        mysqli_stmt_bind_param($stmt, "i", $reorder_order_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $pid = (int)$row['product_id'];
            $req_qty = isset($reorder_items_post[$pid]) ? (int)$reorder_items_post[$pid] : (int)$row['quantity'];
            $row['quantity'] = min(max(1, $req_qty), (int)$row['stock_quantity']);
            $items[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

} else {
    // Mode = cart
    $selected_items_filter = [];
    if (!empty($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        foreach ($_POST['selected_items'] as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) $selected_items_filter[] = $sid;
        }
    }

    $where_cart = "c.buyer_id = ? AND p.status = 'Active' AND p.stock_quantity > 0";
    if (!empty($selected_items_filter)) {
        $in_clause = implode(',', $selected_items_filter);
        $where_cart .= " AND c.cart_item_id IN ($in_clause)";
    }

    $sql = "
        SELECT c.cart_item_id, c.product_id, c.quantity, p.farmer_id, p.name, p.price, p.discounted_price, p.selling_deadline, p.stock_quantity
        FROM cart_item_tbl c
        JOIN product_tbl p ON p.product_id = c.product_id
        WHERE {$where_cart}
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $buyer_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $cid = (int)$row['cart_item_id'];
        $item_qty = (int)$row['quantity'];
        if (!empty($_POST['cart_quantities'][$cid])) {
            $item_qty = (int)$_POST['cart_quantities'][$cid];
        }
        $row['quantity'] = min(max(1, $item_qty), (int)$row['stock_quantity']);
        $items[] = $row;
        $cart_item_ids_to_clear[] = $cid;
    }
    mysqli_stmt_close($stmt);
}

if (empty($items)) {
    set_message('error', 'No valid items found to place an order.');
    redirect_path($mode === 'reorder' ? '/buyer/orders.php' : '/buyer/cart.php');
}

/* ========== GROUP ITEMS BY FARMER ========== */
$farmer_groups = [];
foreach ($items as $item) {
    $fid = (int)$item['farmer_id'];
    $farmer_groups[$fid][] = $item;
}

/* ========== EXECUTE TRANSACTION ========== */
mysqli_autocommit($conn, false);
$success = true;

foreach ($farmer_groups as $farmer_id => $farmer_items) {
    $seller_items_total = 0;
    foreach ($farmer_items as $fi) {
        $eff_price = get_product_effective_price($fi['price'], $fi['discounted_price'] ?? null, $fi['selling_deadline'] ?? null);
        $seller_items_total += ($eff_price * (int)$fi['quantity']);
    }
    $subtotal_amount  = $seller_items_total;
    $platform_fee     = round($subtotal_amount * 0.02, 2);
    $farmer_payout    = $subtotal_amount - $platform_fee;
    $total_amount     = $subtotal_amount + $delivery_fee_per_seller;
    $payment_status = 'Pending';

    // Insert order_tbl with financial breakdown
    $sql = "
        INSERT INTO order_tbl 
        (buyer_id, farmer_id, address_id, order_date, subtotal_amount, platform_fee, farmer_payout, total_amount, shipping_fee, payment_method, payment_status, gcash_screenshot_url, fulfillment_type, order_status)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Placed')
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "iiidddddssss",
        $buyer_id,
        $farmer_id,
        $address_id,
        $subtotal_amount,
        $platform_fee,
        $farmer_payout,
        $total_amount,
        $delivery_fee_per_seller,
        $payment_method,
        $payment_status,
        $gcash_screenshot_url,
        $fulfillment_type
    );

    if (!mysqli_stmt_execute($stmt)) {
        $success = false;
        mysqli_stmt_close($stmt);
        break;
    }
    $order_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Insert order items & deduct stock
    foreach ($farmer_items as $fi) {
        $pid = (int)$fi['product_id'];
        $pqty = (int)$fi['quantity'];
        $pprice = get_product_effective_price($fi['price'], $fi['discounted_price'] ?? null, $fi['selling_deadline'] ?? null);

        // order_item_tbl
        $stmt = mysqli_prepare($conn, "INSERT INTO order_item_tbl (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iiid", $order_id, $pid, $pqty, $pprice);
        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
            mysqli_stmt_close($stmt);
            break 2;
        }
        mysqli_stmt_close($stmt);

        // Deduct stock from product_tbl
        $stmt = mysqli_prepare($conn, "UPDATE product_tbl SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE product_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $pqty, $pid);
        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
            mysqli_stmt_close($stmt);
            break 2;
        }
        mysqli_stmt_close($stmt);
    }
}

if ($success) {
    if ($mode === 'cart' && !empty($cart_item_ids_to_clear)) {
        // Clear only checked out items
        $in_ids = implode(',', $cart_item_ids_to_clear);
        mysqli_query($conn, "DELETE FROM cart_item_tbl WHERE buyer_id = $buyer_id AND cart_item_id IN ($in_ids)");
    }

    mysqli_commit($conn);
    mysqli_autocommit($conn, true);
    mysqli_close($conn);

    set_message('success', 'Thank You! Your order has been placed successfully.');
    redirect_path('/buyer/orders.php');
} else {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, true);
    mysqli_close($conn);

    set_message('Error', 'There is a problem placing your order. Try Again.');
    redirect_path($checkout_redirect);
}
