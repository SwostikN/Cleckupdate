<?php
session_start();
error_log("Check_out: Session ID: " . session_id() . ", USER_ID: " . ($_SESSION['USER_ID'] ?? 'unset') . ", USER_TYPE: " . ($_SESSION['USER_TYPE'] ?? 'unset') . ", POST: " . print_r($_POST, true) . " at " . date('Y-m-d H:i:s'), 3, 'debug.log');

// Validate session
if (!isset($_SESSION['USER_ID']) || empty($_SESSION['USER_ID']) || !isset($_SESSION['USER_TYPE']) || $_SESSION['USER_TYPE'] !== 'customer') {
    error_log("Check_out: Invalid USER_ID or USER_TYPE, redirecting to signin", 3, 'debug.log');
    header("Location: customer_signin.php?return_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

include("connection/connection.php");

// Check if form data is set via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'], $_POST['customerid'], $_POST['cartid'], $_POST['number_product'], $_POST['total_price'], $_POST['discount'])) {
    $cart_id = filter_var($_POST['cartid'], FILTER_SANITIZE_NUMBER_INT);
    $total_price = filter_var($_POST['total_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $total_products = filter_var($_POST['number_product'], FILTER_SANITIZE_NUMBER_INT);
    $customer_id = filter_var($_POST['customerid'], FILTER_SANITIZE_NUMBER_INT);
    $discount = filter_var($_POST['discount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    // Validate inputs
    if (!$cart_id || !$total_products || !$total_price || !$customer_id) {
        error_log("Check_out: Invalid form data - cart_id: $cart_id, total_products: $total_products, total_price: $total_price, customer_id: $customer_id", 3, 'debug.log');
        header("Location: error_page.php?error=Invalid form data");
        exit;
    }

    // Verify customer_id exists
    $sql = "SELECT COUNT(*) FROM CUSTOMER WHERE CUSTOMER_ID = :customer_id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':customer_id', $customer_id);
    oci_execute($stmt);
    $row = oci_fetch_row($stmt);
    $customer_exists = $row[0];
    oci_free_statement($stmt);
    if (!$customer_exists) {
        error_log("Check_out: Invalid customer_id: $customer_id", 3, 'debug.log');
        header("Location: error_page.php?error=Invalid customer");
        exit;
    }

    // Verify cart has items
    $sql = "SELECT COUNT(*) FROM CART_ITEM WHERE CART_ID = :cart_id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':cart_id', $cart_id);
    oci_execute($stmt);
    $row = oci_fetch_row($stmt);
    $cart_items_count = $row[0];
    oci_free_statement($stmt);
    if ($cart_items_count == 0) {
        error_log("Check_out: No items in cart_id: $cart_id", 3, 'debug.log');
        header("Location: error_page.php?error=Empty cart");
        exit;
    }

    // Begin transaction
    $stmtBeginTransaction = oci_parse($conn, "BEGIN");
    if (!oci_execute($stmtBeginTransaction)) {
        $e = oci_error($conn);
        error_log("Check_out: Begin Transaction Error: " . $e['message'], 3, 'debug.log');
        header("Location: error_page.php?error=Transaction failed");
        exit;
    }

    // Insert into ORDER_PRODUCT
    $sqlInsertOrderProduct = "
        BEGIN
            INSERT INTO ORDER_PRODUCT (NO_OF_PRODUCT, ORDER_STATUS, TOTAL_PRICE, SLOT_ID, CUSTOMER_ID, ORDER_DATE, ORDER_TIME, DISCOUNT_AMOUNT, CART_ID) 
            VALUES (:total_products, 0, :total_price, 0, :customer_id, SYSDATE, SYSTIMESTAMP, :discount, :cart_id)
            RETURNING ORDER_PRODUCT_ID INTO :order_product_id;
        END;";
    $stmtInsertOrderProduct = oci_parse($conn, $sqlInsertOrderProduct);
    if (!$stmtInsertOrderProduct) {
        $e = oci_error($conn);
        error_log("Check_out: Parse Error for ORDER_PRODUCT: " . $e['message'], 3, 'debug.log');
        oci_execute(oci_parse($conn, "ROLLBACK"));
        header("Location: error_page.php?error=Database error");
        exit;
    }

    oci_bind_by_name($stmtInsertOrderProduct, ":total_products", $total_products);
    oci_bind_by_name($stmtInsertOrderProduct, ":total_price", $total_price);
    oci_bind_by_name($stmtInsertOrderProduct, ":discount", $discount);
    oci_bind_by_name($stmtInsertOrderProduct, ":customer_id", $customer_id);
    oci_bind_by_name($stmtInsertOrderProduct, ":cart_id", $cart_id);
    oci_bind_by_name($stmtInsertOrderProduct, ":order_product_id", $order_product_id, -1, OCI_B_INT);

    if (!oci_execute($stmtInsertOrderProduct)) {
        $e = oci_error($stmtInsertOrderProduct);
        error_log("Check_out: Insert ORDER_PRODUCT Error: " . $e['message'], 3, 'debug.log');
        oci_execute(oci_parse($conn, "ROLLBACK"));
        header("Location: error_page.php?error=Order creation failed");
        exit;
    }

    // Fetch ORDER_PRODUCT_ID
    oci_fetch($stmtInsertOrderProduct);
    if (!$order_product_id) {
        error_log("Check_out: Failed to retrieve ORDER_PRODUCT_ID", 3, 'debug.log');
        oci_execute(oci_parse($conn, "ROLLBACK"));
        header("Location: error_page.php?error=Order ID retrieval failed");
        exit;
    }
    oci_free_statement($stmtInsertOrderProduct);

    error_log("Check_out: Inserted into ORDER_PRODUCT, ORDER_PRODUCT_ID: $order_product_id", 3, 'debug.log');

    // Loop through the products in the cart
    $sql = "SELECT ci.NO_OF_PRODUCTS, ci.PRODUCT_ID, p.PRODUCT_PRICE 
            FROM CART_ITEM ci
            JOIN PRODUCT p ON ci.PRODUCT_ID = p.PRODUCT_ID
            WHERE ci.CART_ID = :cart_id";
    $stmtSelectProducts = oci_parse($conn, $sql);
    if (!$stmtSelectProducts) {
        $e = oci_error($conn);
        error_log("Check_out: Parse Error for CART_ITEM: " . $e['message'], 3, 'debug.log');
        oci_execute(oci_parse($conn, "ROLLBACK"));
        header("Location: error_page.php?error=Cart retrieval failed");
        exit;
    }

    oci_bind_by_name($stmtSelectProducts, ":cart_id", $cart_id);
    if (!oci_execute($stmtSelectProducts)) {
        $e = oci_error($stmtSelectProducts);
        error_log("Check_out: Execute Error for CART_ITEM: " . $e['message'], 3, 'debug.log');
        oci_execute(oci_parse($conn, "ROLLBACK"));
        header("Location: error_page.php?error=Cart retrieval failed");
        exit;
    }

    while ($row = oci_fetch_assoc($stmtSelectProducts)) {
        $product_qty = $row['NO_OF_PRODUCTS'];
        $product_id = $row['PRODUCT_ID'];
        $product_price = $row['PRODUCT_PRICE'];

        // Get discount
        $selectDiscountSql = "SELECT DISCOUNT_PERCENT FROM DISCOUNT WHERE PRODUCT_ID = :productId";
        $selectDiscountStmt = oci_parse($conn, $selectDiscountSql);
        oci_bind_by_name($selectDiscountStmt, ':productId', $product_id);
        if (!oci_execute($selectDiscountStmt)) {
            $e = oci_error($selectDiscountStmt);
            error_log("Check_out: Execute Error for DISCOUNT: " . $e['message'], 3, 'debug.log');
            oci_execute(oci_parse($conn, "ROLLBACK"));
            header("Location: error_page.php?error=Discount retrieval failed");
            exit;
        }

        $discount_row = oci_fetch_assoc($selectDiscountStmt);
        $discountPercent = $discount_row ? $discount_row['DISCOUNT_PERCENT'] : 0;
        oci_free_statement($selectDiscountStmt);

        $discountAmount = ($product_price * $discountPercent) / 100;
        $discountedPrice = $product_price - $discountAmount;

        // Insert into ORDER_DETAILS
        $sqlInsertOrderDetails = "INSERT INTO ORDER_DETAILS (ORDER_PRODUCT_ID, PRODUCT_ID, PRODUCT_QTY, PRODUCT_PRICE, TRADER_USER_ID) 
                                 VALUES (:order_product_id, :product_id, :product_qty, :product_price, (SELECT USER_ID FROM PRODUCT WHERE PRODUCT_ID = :product_id))";
        $stmtInsertOrderDetails = oci_parse($conn, $sqlInsertOrderDetails);
        if (!$stmtInsertOrderDetails) {
            $e = oci_error($conn);
            error_log("Check_out: Parse Error for ORDER_DETAILS: " . $e['message'], 3, 'debug.log');
            oci_execute(oci_parse($conn, "ROLLBACK"));
            header("Location: error_page.php?error=Order details insertion failed");
            exit;
        }

        oci_bind_by_name($stmtInsertOrderDetails, ":order_product_id", $order_product_id);
        oci_bind_by_name($stmtInsertOrderDetails, ":product_id", $product_id);
        oci_bind_by_name($stmtInsertOrderDetails, ":product_qty", $product_qty);
        oci_bind_by_name($stmtInsertOrderDetails, ":product_price", $discountedPrice);
        if (!oci_execute($stmtInsertOrderDetails)) {
            $e = oci_error($stmtInsertOrderDetails);
            error_log("Check_out: Execute Error for ORDER_DETAILS: " . $e['message'], 3, 'debug.log');
            oci_execute(oci_parse($conn, "ROLLBACK"));
            header("Location: error_page.php?error=Order details insertion failed");
            exit;
        }
        oci_free_statement($stmtInsertOrderDetails);

        // Delete from CART_ITEM
        $sqlDeleteCartItem = "DELETE FROM CART_ITEM WHERE CART_ID = :cart_id AND PRODUCT_ID = :product_id";
        $stmtDeleteCartItem = oci_parse($conn, $sqlDeleteCartItem);
        oci_bind_by_name($stmtDeleteCartItem, ":cart_id", $cart_id);
        oci_bind_by_name($stmtDeleteCartItem, ":product_id", $product_id);
        if (!oci_execute($stmtDeleteCartItem)) {
            $e = oci_error($stmtDeleteCartItem);
            error_log("Check_out: Execute Error for CART_ITEM Delete: " . $e['message'], 3, 'debug.log');
            oci_execute(oci_parse($conn, "ROLLBACK"));
            header("Location: error_page.php?error=Cart item deletion failed");
            exit;
        }
        oci瞎_free_statement($stmtDeleteCartItem);

        // Update PRODUCT_QUANTITY
        $updateSql = "UPDATE PRODUCT SET PRODUCT_QUANTITY = PRODUCT_QUANTITY - :no_of_products WHERE PRODUCT_ID = :product_id";
        $updateStmt = oci_parse($conn, $updateSql);
        oci_bind_by_name($updateStmt, ':no_of_products', $product_qty);
        oci_bind_by_name($updateStmt, ':product_id', $product_id);
        if (!oci_execute($updateStmt)) {
            $e = oci_error($updateStmt);
            error_log("Check_out: Execute Error for PRODUCT Update: " . $e['message'], 3, 'debug.log');
            oci_execute(oci_parse($conn, "ROLLBACK"));
            header("Location: error_page.php?error=Product quantity update failed");
            exit;
        }
        oci_free_statement($updateStmt);
    }

    oci_free_statement($stmtSelectProducts);

    // Commit transaction
    $stmtCommit = oci_parse($conn, "COMMIT");
    if (!oci_execute($stmtCommit)) {
        $e = oci_error($conn);
        error_log("Check_out: Commit Error: " . $e['message'], 3, 'debug.log');
        header("Location: error_page.php?error=Transaction commit failed");
        exit;
    }
    oci_free_statement($stmtCommit);

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
            OP.ORDER_PRODUCT_ID = :order_id
    ";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':order_id', $order_product_id);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Check_out: Execute Error for ORDER_DETAILS Fetch: " . $e['message'], 3, 'debug.log');
        header("Location: error_page.php?error=Order details fetch failed");
        exit;
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
        UPDATE 
            ORDER_PRODUCT 
        SET 
            TOTAL_PRICE = :total_amount,
            DISCOUNT_AMOUNT = :discount_amount
        WHERE 
            ORDER_PRODUCT_ID = :order_id
    ";
    $updateStmt = oci_parse($conn, $updateSql);
    oci_bind_by_name($updateStmt, ':total_amount', $totalAmount);
    oci_bind_by_name($updateStmt, ':discount_amount', $discountAmount);
    oci_bind_by_name($updateStmt, ':order_id', $order_product_id);
    if (!oci_execute($updateStmt)) {
        $e = oci_error($updateStmt);
        error_log("Check_out: Execute Error for ORDER_PRODUCT Update: " . $e['message'], 3, 'debug.log');
        header("Location: error_page.php?error=Order update failed");
        exit;
    }
    oci_free_statement($updateStmt);

    // Update PRODUCT table for out-of-stock items
    $sql = "UPDATE PRODUCT 
            SET STOCK_AVAILABLE = 'no', IS_DISABLED = 0 
            WHERE PRODUCT_QUANTITY < 1";
    $stmt = oci_parse($conn, $sql);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        error_log("Check_out: Execute Error for PRODUCT Stock Update: " . $e['message'], 3, 'debug.log');
        header("Location: error_page.php?error=Stock update failed");
        exit;
    }
    oci_free_statement($stmt);

    // Close the connection
    oci_close($conn);

    // Redirect to slot_time.php
    $url = "slot_time.php?customerid=$customer_id&order_id=$order_product_id&cartid=$cart_id&number_product=$total_products&total_price=$totalAmount&discount=$discountAmount";
    error_log("Check_out: Redirecting to $url", 3, 'debug.log');
    header("Location: slot_time.php?customerid=$customer_id&order_id=$order_product_id&cartid=$cart_id&number_product=$total_products&total_price=$totalAmount&discount=$discountAmount");
    exit;
} else {
    error_log("Check_out: Invalid request or missing POST data", 3, 'debug.log');
    header("Location: error_page.php?error=Invalid request");
    exit;
}
?>