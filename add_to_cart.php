<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require("session/session.php");

$user_id = isset($_GET["userid"]) ? (int)$_GET["userid"] : 0;
$product_id = isset($_GET["productid"]) ? (int)$_GET["productid"] : 0;
$quantity = isset($_GET["quantity"]) ? (int)$_GET["quantity"] : 1;
$search_text = isset($_GET["searchtext"]) ? trim($_GET["searchtext"]) : "";

if (!$user_id) {
    header("Location: customer_signin.php");
    exit;
}

include("connection/connection.php");

// Get customer_id
$sql = "SELECT customer_id FROM CUSTOMER WHERE user_id = :user_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':user_id', $user_id);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);
$customer_id = $row ? $row['CUSTOMER_ID'] : null;
oci_free_statement($stmt);

if ($customer_id && $product_id) {
    // Get product price and discount
    $product_sql = "SELECT p.product_price, COALESCE(d.discount_percent, 0) AS discount_percent 
                    FROM PRODUCT p 
                    LEFT JOIN DISCOUNT d ON p.product_id = d.product_id 
                    WHERE p.product_id = :product_id";
    $product_stmt = oci_parse($conn, $product_sql);
    oci_bind_by_name($product_stmt, ':product_id', $product_id);
    oci_execute($product_stmt);
    $product_row = oci_fetch_assoc($product_stmt);
    $product_price = $product_row ? $product_row['PRODUCT_PRICE'] : 0;
    $discount_percent = $product_row ? $product_row['DISCOUNT_PERCENT'] : 0;
    $discounted_price = $product_price * (1 - $discount_percent / 100);
    oci_free_statement($product_stmt);

    if ($product_price) {
        // Check for existing cart
        $cart_sql = "SELECT cart_id FROM CART WHERE customer_id = :customer_id AND order_product_id IS NULL";
        $cart_stmt = oci_parse($conn, $cart_sql);
        oci_bind_by_name($cart_stmt, ':customer_id', $customer_id);
        oci_execute($cart_stmt);
        $cart_row = oci_fetch_assoc($cart_stmt);
        $cart_id = $cart_row ? $cart_row['CART_ID'] : null;
        oci_free_statement($cart_stmt);

        if (!$cart_id) {
            // Create new cart
            $cart_sql = "INSERT INTO CART (customer_id, order_product_id) 
                         VALUES (:customer_id, NULL) RETURNING cart_id INTO :cart_id";
            $cart_stmt = oci_parse($conn, $cart_sql);
            oci_bind_by_name($cart_stmt, ':customer_id', $customer_id);
            oci_bind_by_name($cart_stmt, ':cart_id', $cart_id, -1, OCI_B_INT);
            oci_execute($cart_stmt);
            oci_free_statement($cart_stmt);
        }

        // Check total products in cart
        $count_sql = "SELECT SUM(no_of_products) AS total_products 
                      FROM CART_ITEM WHERE cart_id = :cart_id";
        $count_stmt = oci_parse($conn, $count_sql);
        oci_bind_by_name($count_stmt, ':cart_id', $cart_id);
        oci_execute($count_stmt);
        $count_row = oci_fetch_assoc($count_stmt);
        $total_products = $count_row ? (int)$count_row['TOTAL_PRODUCTS'] : 0;
        oci_free_statement($count_stmt);

        if ($total_products + $quantity <= 20) {
            // Check if product exists in cart
            $item_sql = "SELECT no_of_products FROM CART_ITEM 
                         WHERE cart_id = :cart_id AND product_id = :product_id";
            $item_stmt = oci_parse($conn, $item_sql);
            oci_bind_by_name($item_stmt, ':cart_id', $cart_id);
            oci_bind_by_name($item_stmt, ':product_id', $product_id);
            oci_execute($item_stmt);
            $item_row = oci_fetch_assoc($item_stmt);
            $existing_quantity = $item_row ? (int)$item_row['NO_OF_PRODUCTS'] : 0;
            oci_free_statement($item_stmt);

            if ($existing_quantity) {
                // Update quantity
                $new_quantity = $existing_quantity + $quantity;
                $update_sql = "UPDATE CART_ITEM 
                               SET no_of_products = :no_of_products, product_price = :product_price 
                               WHERE cart_id = :cart_id AND product_id = :product_id";
                $update_stmt = oci_parse($conn, $update_sql);
                oci_bind_by_name($update_stmt, ':no_of_products', $new_quantity);
                oci_bind_by_name($update_stmt, ':product_price', $discounted_price);
                oci_bind_by_name($update_stmt, ':cart_id', $cart_id);
                oci_bind_by_name($update_stmt, ':product_id', $product_id);
                oci_execute($update_stmt);
                oci_free_statement($update_stmt);
            } else {
                // Insert new item
                $insert_sql = "INSERT INTO CART_ITEM (cart_id, product_id, no_of_products, product_price) 
                               VALUES (:cart_id, :product_id, :no_of_products, :product_price)";
                $insert_stmt = oci_parse($conn, $insert_sql);
                oci_bind_by_name($insert_stmt, ':cart_id', $cart_id);
                oci_bind_by_name($insert_stmt, ':product_id', $product_id);
                oci_bind_by_name($insert_stmt, ':no_of_products', $quantity);
                oci_bind_by_name($insert_stmt, ':product_price', $discounted_price);
                oci_execute($insert_stmt);
                oci_free_statement($insert_stmt);
            }
        } else {
            echo "Cart is full.";
        }
    } else {
        echo "Product not found.";
    }
} else {
    echo "Invalid customer or product.";
}

oci_close($conn);

// Redirect based on search_text
if ($search_text === "p") {
    header("Location: product_detail.php?productId=$product_id");
} elseif (!empty($search_text)) {
    header("Location: search_page.php?value=" . urlencode($search_text));
} else {
    header("Location: index.php");
}
exit;
?>