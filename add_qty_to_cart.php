<?php
require("session/session.php");
$user_id = $_SESSION["USER_ID"] ?? null;

if (!$user_id) {
    header("Location: customer_signin.php");
    exit;
}

include("connection/connection.php");

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid CSRF token");
}

$cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$cart_id || !$product_id || !in_array($action, ['increase', 'decrease'])) {
    header("Location: cart.php");
    exit;
}

// Verify cart belongs to user
$check_sql = "SELECT c.cart_id 
              FROM CART c 
              JOIN CUSTOMER cu ON c.customer_id = cu.customer_id 
              WHERE c.cart_id = :cart_id AND cu.user_id = :user_id";
$check_stmt = oci_parse($conn, $check_sql);
oci_bind_by_name($check_stmt, ':cart_id', $cart_id);
oci_bind_by_name($check_stmt, ':user_id', $user_id);
oci_execute($check_stmt);
if (!oci_fetch($check_stmt)) {
    oci_free_statement($check_stmt);
    oci_close($conn);
    header("Location: cart.php");
    exit;
}
oci_free_statement($check_stmt);

// Update quantity
if ($action == 'increase') {
    $sql = "UPDATE CART_ITEM ci 
            SET no_of_products = no_of_products + 1 
            WHERE cart_id = :cart_id AND product_id = :product_id 
            AND (SELECT SUM(no_of_products) FROM CART_ITEM WHERE cart_id = :cart_id) < 20";
} else {
    $sql = "UPDATE CART_ITEM ci 
            SET no_of_products = no_of_products - 1 
            WHERE cart_id = :cart_id AND product_id = :product_id AND no_of_products > 1";
}

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':cart_id', $cart_id);
oci_bind_by_name($stmt, ':product_id', $product_id);
oci_execute($stmt);
oci_free_statement($stmt);
oci_close($conn);

header("Location: cart.php");
exit;
?>