<?php
// Include the database connection
include("connection/connection.php");

// Check if required POST parameters are set
if (isset($_POST['cartid'], $_POST['total_price'], $_POST['number_product'], $_POST['customerid'])) {
    $cart_id = $_POST['cartid'];
    $total_price = $_POST['total_price'];
    $total_products = $_POST['number_product'];
    $customer_id = $_POST['customerid'];

    // Validate inputs
    if (empty($cart_id) || empty($total_price) || empty($total_products) || empty($customer_id)) {
        header("Location: error_page.php?error=Invalid input parameters");
        exit();
    }

    // Insert into ORDER_PRODUCT
    $sqlInsertOrderProduct = "
        BEGIN
            INSERT INTO ORDER_PRODUCT (NO_OF_PRODUCT, ORDER_STATUS, TOTAL_PRICE, SLOT_ID, CUSTOMER_ID, ORDER_DATE, ORDER_TIME, DISCOUNT_AMOUNT, CART_ID) 
            VALUES (:total_products, 0, :total_price, 0, :customer_id, SYSDATE, SYSTIMESTAMP, 0, :cart_id)
            RETURNING ORDER_PRODUCT_ID INTO :order_product_id;
        END;";
    $stmtInsertOrderProduct = oci_parse($conn, $sqlInsertOrderProduct);
    oci_bind_by_name($stmtInsertOrderProduct, ":total_products", $total_products);
    oci_bind_by_name($stmtInsertOrderProduct, ":total_price", $total_price);
    oci_bind_by_name($stmtInsertOrderProduct, ":customer_id", $customer_id);
    oci_bind_by_name($stmtInsertOrderProduct, ":cart_id", $cart_id);
    oci_bind_by_name($stmtInsertOrderProduct, ":order_product_id", $order_product_id, -1, OCI_B_INT);

    if (!oci_execute($stmtInsertOrderProduct)) {
        oci_rollback($conn);
        echo "Failed to insert data into ORDER_PRODUCT table.";
        oci_free_statement($stmtInsertOrderProduct);
        oci_close($conn);
        exit();
    }

    // Free the statement
    oci_free_statement($stmtInsertOrderProduct);

    // Debug statement
    echo "Data inserted into ORDER_PRODUCT table. ORDER_PRODUCT_ID: $order_product_id";

    // Loop through the products in the cart
    $sql = "SELECT ci.NO_OF_PRODUCTS, ci.PRODUCT_ID, p.PRODUCT_PRICE 
            FROM CART_ITEM ci
            JOIN PRODUCT p ON ci.PRODUCT_ID = p.PRODUCT_ID
            WHERE ci.CART_ID = :cart_id";
    $stmtSelectProducts = oci_parse($conn, $sql);
    oci_bind_by_name($stmtSelectProducts, ":cart_id", $cart_id);
    if (!oci_execute($stmtSelectProducts)) {
        oci_rollback($conn);
        echo "Failed to fetch cart items.";
        oci_free_statement($stmtSelectProducts);
        oci_close($conn);
        exit();
    }

    while ($row = oci_fetch_assoc($stmtSelectProducts)) {
        $product_qty = $row['NO_OF_PRODUCTS'];
        $product_id = $row['PRODUCT_ID'];
        $product_price = $row['PRODUCT_PRICE'];

        // Fetch discount
        $selectDiscountSql = "SELECT DISCOUNT_PERCENT FROM DISCOUNT WHERE PRODUCT_ID = :productId";
        $selectDiscountStmt = oci_parse($conn, $selectDiscountSql);
        oci_bind_by_name($selectDiscountStmt, ':productId', $product_id);
        oci_execute($selectDiscountStmt);
        $discount_row = oci_fetch_assoc($selectDiscountStmt);
        $discountPercent = $discount_row ? $discount_row['DISCOUNT_PERCENT'] : 0;
        oci_free_statement($selectDiscountStmt);

        $discountAmount = ($product_price * $discountPercent) / 100;
        $discountedPrice = $product_price - $discountAmount;

        // Insert into ORDER_DETAILS
        $sqlInsertOrderDetails = "
            INSERT INTO ORDER_DETAILS (ORDER_PRODUCT_ID, PRODUCT_ID, PRODUCT_QTY, PRODUCT_PRICE, TRADER_USER_ID) 
            VALUES (:order_product_id, :product_id, :product_qty, :product_price, 
                    (SELECT USER_ID FROM PRODUCT WHERE PRODUCT_ID = :product_id))";
        $stmtInsertOrderDetails = oci_parse($conn, $sqlInsertOrderDetails);
        oci_bind_by_name($stmtInsertOrderDetails, ":order_product_id", $order_product_id);
        oci_bind_by_name($stmtInsertOrderDetails, ":product_id", $product_id);
        oci_bind_by_name($stmtInsertOrderDetails, ":product_qty", $product_qty);
        oci_bind_by_name($stmtInsertOrderDetails, ":product_price", $discountedPrice);
        if (!oci_execute($stmtInsertOrderDetails)) {
            oci_rollback($conn);
            echo "Failed to insert into ORDER_DETAILS.";
            oci_free_statement($stmtInsertOrderDetails);
            oci_free_statement($stmtSelectProducts);
            oci_close($conn);
            exit();
        }
        oci_free_statement($stmtInsertOrderDetails);

        // Delete from CART_ITEM
        $sqlDeleteCartItem = "DELETE FROM CART_ITEM WHERE CART_ID = :cart_id AND PRODUCT_ID = :product_id";
        $stmtDeleteCartItem = oci_parse($conn, $sqlDeleteCartItem);
        oci_bind_by_name($stmtDeleteCartItem, ":cart_id", $cart_id);
        oci_bind_by_name($stmtDeleteCartItem, ":product_id", $product_id);
        if (!oci_execute($stmtDeleteCartItem)) {
            oci_rollback($conn);
            echo "Failed to delete from CART_ITEM.";
            oci_free_statement($stmtDeleteCartItem);
            oci_free_statement($stmtSelectProducts);
            oci_close($conn);
            exit();
        }
        oci_free_statement($stmtDeleteCartItem);

        // Update PRODUCT_QUANTITY
        $updateSql = "
            UPDATE PRODUCT 
            SET PRODUCT_QUANTITY = PRODUCT_QUANTITY - :no_of_products
            WHERE PRODUCT_ID = :product_id";
        $updateStmt = oci_parse($conn, $updateSql);
        oci_bind_by_name($updateStmt, ':no_of_products', $product_qty);
        oci_bind_by_name($updateStmt, ':product_id', $product_id);
        if (!oci_execute($updateStmt)) {
            oci_rollback($conn);
            echo "Failed to update PRODUCT_QUANTITY.";
            oci_free_statement($updateStmt);
            oci_free_statement($stmtSelectProducts);
            oci_close($conn);
            exit();
        }
        oci_free_statement($updateStmt);
    }

    oci_free_statement($stmtSelectProducts);

    // Fetch order details to calculate total amount and discount amount
    $sql = "
        SELECT 
            OP.PRODUCT_ID, 
            OP.PRODUCT_QTY, 
            OP.PRODUCT_PRICE, 
            P.PRODUCT_PRICE AS ACTUAL_PRICE
        FROM 
            ORDER_DETAILS OP
        JOIN 
            PRODUCT P ON OP.PRODUCT_ID = P.PRODUCT_ID
        WHERE 
            OP.ORDER_PRODUCT_ID = :order_id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':order_id', $order_product_id);
    if (!oci_execute($stmt)) {
        oci_rollback($conn);
        echo "Failed to fetch order details.";
        oci_free_statement($stmt);
        oci_close($conn);
        exit();
    }

    $totalAmount = 0;
    $discountAmount = 0;

    while ($row = oci_fetch_assoc($stmt)) {
        $totalAmount += $row['PRODUCT_QTY'] * $row['PRODUCT_PRICE'];
        $discountAmount += ($row['ACTUAL_PRICE'] - $row['PRODUCT_PRICE']) * $row['PRODUCT_QTY'];
    }
    oci_free_statement($stmt);

    // Update ORDER_PRODUCT with total amount and discount amount
    $updateSql = "
        UPDATE ORDER_PRODUCT 
        SET TOTAL_PRICE = :total_amount,
            DISCOUNT_AMOUNT = :discount_amount
        WHERE ORDER_PRODUCT_ID = :order_id";
    $updateStmt = oci_parse($conn, $updateSql);
    oci_bind_by_name($updateStmt, ':total_amount', $totalAmount);
    oci_bind_by_name($updateStmt, ':discount_amount', $discountAmount);
    oci_bind_by_name($updateStmt, ':order_id', $order_product_id);
    if (!oci_execute($updateStmt)) {
        oci_rollback($conn);
        echo "Failed to update ORDER_PRODUCT.";
        oci_free_statement($updateStmt);
        oci_close($conn);
        exit();
    }
    oci_free_statement($updateStmt);

    // Update PRODUCT table for out-of-stock items
    $sql = "UPDATE PRODUCT 
            SET STOCK_AVAILABLE = 'no', IS_DISABLED = 0 
            WHERE PRODUCT_QUANTITY < 1";
    $stmt = oci_parse($conn, $sql);
    if (!oci_execute($stmt)) {
        oci_rollback($conn);
        echo "Failed to update PRODUCT stock.";
        oci_free_statement($stmt);
        oci_close($conn);
        exit();
    }
    oci_free_statement($stmt);

    // Commit transaction
    oci_commit($conn);

    // Close the connection
    oci_close($conn);

    // Redirect to checkout page
    $url = "slot_time.php?customerid=$customer_id&order_id=$order_product_id&cartid=$cart_id&number_product=$total_products&total_price=$total_price&discount=$discountAmount";
    header("Location: $url");
    exit();
} else {
    // Redirect if required parameters are not set
    header("Location: error_page.php?error=Missing required parameters");
    exit();
}
?>