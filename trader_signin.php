<?php
session_start();
$error_message = ""; // Declare the variable here
$account_error = ""; // Declare the variable here
$user_role_error = ""; // Declare the variable here

include("connection/connection.php");

if (isset($_POST["sign_in"])) {
    // Input Sanitization 
    require("input_validation/input_sanitization.php");

    // Check if $_POST["email"] exists before sanitizing
    $email = isset($_POST["email"]) ? sanitizeEmail($_POST["email"]) : "";

    // Check if $_POST["password"] exists before sanitizing
    $password = isset($_POST["password"]) ? sanitizePassword($_POST["password"]) : "";

    $remember = isset($_POST["remember"]) ? $_POST["remember"] : 0;
    $pass = $_POST["password"];

    // Prepare the SQL statement
    $sql = "SELECT 
        HU.FIRST_NAME, 
        HU.LAST_NAME, 
        HU.USER_ID, 
        HU.USER_PASSWORD, 
        HU.USER_PROFILE_PICTURE, 
        HU.USER_TYPE, 
        T.VERIFICATION_STATUS
    FROM 
        CLECK_USER HU
    JOIN 
        TRADER T ON HU.USER_ID = T.USER_ID
    WHERE 
        HU.USER_EMAIL = :email";

    // Prepare the OCI statement
    $stmt = oci_parse($conn, $sql);

    // Bind the email parameter
    oci_bind_by_name($stmt, ':email', $email);

    // Execute the statement
    if (oci_execute($stmt)) {
        // Fetch the result
        if ($row = oci_fetch_assoc($stmt)) {
            $first_name = $row['FIRST_NAME'];
            $last_name = $row['LAST_NAME'];
            $user_id = $row['USER_ID'];
            $passwords = $row['USER_PASSWORD'];
            $profile_picture = $row['USER_PROFILE_PICTURE'];
            $user_role = $row['USER_TYPE'];
            $status = $row["VERIFICATION_STATUS"];
            
            // Corrected password verification
            if ($password == $passwords && $user_role == "trader" && $status == 1) {
                if ($remember == 1) {
                    setcookie("email_trader", $email, time() + 60 * 60 * 24 * 30, "/");
                    setcookie("password_trader", $pass, time() + 60 * 60 * 24 * 30, "/");
                }
                // Registering session username
                $_SESSION["email"] = $email;
                $_SESSION["accesstime"] = date("ymdhis");
                $_SESSION["name"] = $first_name . " " . $last_name;
                $_SESSION["picture"] = $profile_picture;
                $_SESSION["userid"] = $user_id;
                $_SESSION["role"] = $user_role;
                header("Location: trader_dashboard/trader_dashboard.php");
                exit();
            } elseif ($status != 1) {
                $error_message = "Your account has been disabled.";
            } elseif ($user_role != "trader") {
                $error_message = "You are not a registered trader at HudderFoods.";
            } else {
                $error_message = "Incorrect password. Please try again!";
            }
        } else {
            $error_message = "User not found. Please check your email.";
        }
    } else {
        $error = oci_error($stmt);
        echo "Error executing SQL statement: " . $error['message'];
    }

    // Free the statement and close the connection
    oci_free_statement($stmt);
    oci_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="logo">
            <a href="index.html"><img src="logo.png" alt="Logo"></a>
        </div>
        <ul class="nav-links">
            <li><a href="shop.html">Shop</a></li>
            <li><a href="about.html">About Us</a></li>
            <li><a href="products.html">Products</a></li>
        </ul>
        <div class="nav-actions">
            <div class="cart">
                <a href="cart.html"><i class="fas fa-shopping-cart"></i> Cart (0)</a>
            </div>
            <div class="login">
                <a href="login.html">Login</a>
            </div>
            <div class="signup">
                <a href="traderregestier.html">Become a trader</a>
            </div>
        </div>
    </nav>

    <div class="content">
        <section class="login-section">
            <h1>Welcome Back</h1>
            <?php if (!empty($error_message)) echo "<p style='color: red;'>$error_message</p>"; ?>
            <div class="social-login">
                <button class="google-login">Sign with Google</button>
                <button class="facebook-login">Sign with Facebook</button>
            </div>
            <div class="or-separator">OR</div>
            <p>You have the option to Login in using either your email or phone number</p>
            <form method="POST" action="">
                <div class="form-group">
                    <input type="text" name="email" placeholder="Email or Phone Number" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="form-group">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">Remember Me</label>
                </div>
                <div class="form-group">
                    <input type="submit" name="sign_in" value="Login">
                </div>
            </form>
            <p>Don't have an account? <a href="traderregestier.html">Sign Up</a></p>
            <p>Become a seller</p>
        </section>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Cleckfax Traders</h3>
                <p>Email: [email protected]</p>
                <p>Phone: 646-675-5074</p>
                <p>3961 Smith Street, New York, United States</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Contact Us</h3>
                <form>
                    <input type="text" placeholder="Name" required>
                    <input type="email" placeholder="Email" required>
                    <textarea placeholder="Message" required></textarea>
                    <button type="submit">Send</button>
                </form>
            </div>
        </div>
    </footer>
</body>
</html>