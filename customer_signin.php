<?php
session_start();
session_regenerate_id(true); // Prevent session fixation
error_log("Signin: Session started, ID: " . session_id(), 3, 'debug.log');

$error_message = "";
$account_error = "";
$user_role_error = "";

include("connection/connection.php");

// Capture return URL
$return_url = isset($_GET['return_url']) ? filter_var($_GET['return_url'], FILTER_SANITIZE_URL) : '';
if (empty($return_url)) {
    $return_url = isset($_SERVER['HTTP_REFERER']) ? filter_var($_SERVER['HTTP_REFERER'], FILTER_SANITIZE_URL) : 'index.php';
}
// Validate return_url
$allowed_domains = ['localhost', 'yourdomain.com']; // Replace with your domain
$parsed_url = parse_url($return_url);
if (!isset($parsed_url['host']) || !in_array($parsed_url['host'], $allowed_domains)) {
    $return_url = 'index.php';
}

if (isset($_POST["sign_in"])) {
    require("input_validation/input_sanitization.php");

    if (!function_exists('sanitizeUserRole')) {
        error_log("sanitizeUserRole() not defined in input_sanitization.php", 3, 'error.log');
        die("Error: sanitizeUserRole() function is not defined.");
    }

    $email = isset($_POST["email"]) ? trim(sanitizeEmail($_POST["email"])) : "";
    $password = isset($_POST["password"]) ? trim(sanitizePassword($_POST["password"])) : "";
    $user_role = isset($_POST["user_role"]) ? trim(sanitizeUserRole($_POST["user_role"])) : "";
    $posted_return_url = isset($_POST['return_url']) ? filter_var($_POST['return_url'], FILTER_SANITIZE_URL) : $return_url;

    if (empty($email) || empty($password) || empty($user_role)) {
        $error_message = "Email, password, and user role are required!";
    } elseif (!in_array($user_role, ['customer', 'trader', 'admin'])) {
        $user_role_error = "Invalid user role selected!";
    } else {
        $sql = "";
        if ($user_role === 'customer') {
            $sql = "SELECT HU.FIRST_NAME, HU.LAST_NAME, HU.USER_ID, HU.USER_PASSWORD, 
                           HU.USER_PROFILE_PICTURE, HU.USER_TYPE, C.VERIFIED_CUSTOMER
                    FROM CLECK_USER HU
                    JOIN CUSTOMER C ON HU.USER_ID = C.USER_ID
                    WHERE HU.USER_EMAIL = :email AND HU.USER_TYPE = 'customer'";
        } elseif ($user_role === 'trader') {
            $sql = "SELECT HU.FIRST_NAME, HU.LAST_NAME, HU.USER_ID, HU.USER_PASSWORD, 
                           HU.USER_PROFILE_PICTURE, HU.USER_TYPE, T.VERIFICATION_STATUS
                    FROM CLECK_USER HU
                    JOIN TRADER T ON HU.USER_ID = T.USER_ID
                    WHERE HU.USER_EMAIL = :email AND HU.USER_TYPE = 'trader'";
        } elseif ($user_role === 'admin') {
            $sql = "SELECT FIRST_NAME, LAST_NAME, USER_ID, USER_PASSWORD, 
                           USER_PROFILE_PICTURE, USER_TYPE
                    FROM CLECK_USER
                    WHERE USER_EMAIL = :email AND USER_TYPE = 'admin'";
        }

        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':email', $email);
        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            error_log("Signin query error: " . $error['message'], 3, 'error.log');
            $error_message = "An error occurred. Please try again.";
        } elseif ($row = oci_fetch_assoc($stmt)) {
    if (password_verify($password, $row["USER_PASSWORD"])) {
        // Remove verification checks for already verified accounts
        if ($user_role === 'customer' && !isset($row["VERIFIED_CUSTOMER"])) {
            $account_error = "Please verify your customer account!";
            $_SESSION['USER_ID'] = $row["USER_ID"];
            error_log("Customer not verified, redirecting to verification. USER_ID: " . $row["USER_ID"], 3, 'debug.log');
            header("Location: customer_verification.php");
            exit;
        } elseif ($user_role === 'trader' && !isset($row["VERIFICATION_STATUS"])) {
            $account_error = "Please verify your trader account!";
            $_SESSION['USER_ID'] = $row["USER_ID"];
            error_log("Trader not verified, redirecting to verification. USER_ID: " . $row["USER_ID"], 3, 'debug.log');
            header("Location: trader_verification.php");
            exit;
        } else {
            // Set session variables
            $_SESSION["USER_ID"] = $row["USER_ID"];
            $_SESSION["USER_TYPE"] = $row["USER_TYPE"];
            $_SESSION["FIRST_NAME"] = $row["FIRST_NAME"];
            $_SESSION["LAST_NAME"] = $row["LAST_NAME"];
            $_SESSION["USER_PROFILE_PICTURE"] = $row["USER_PROFILE_PICTURE"];
            
            // Handle "Remember me" and redirect
            if (isset($_POST["remember"])) {
                $token = bin2hex(random_bytes(32));
                setcookie("remember_token", $token, time() + (86400 * 30), "/", "", false, true);
            }
            
            // Redirect based on role
            if ($user_role === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($user_role === 'trader') {
                header("Location: trader_dashboard.php");
            } else {
                header("Location: index.php");  // Default redirect for customers
            }
            exit;
        }
    } else {
        $error_message = "Incorrect email or password!";
    }
        }
        oci_free_statement($stmt);
    }
    oci_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClickFax Traders - Sign In</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f8ff; }
        .signin-container {
            max-width: 500px; margin: 3rem auto; background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); padding: 2rem; border-radius: 8px;
        }
        .error-message { color: red; font-size: 0.875rem; margin-top: 0.25rem; text-align: center; }
    </style>
</head>
<body>
    <?php include('navbar.php'); ?>
    <section class="section">
        <div class="signin-container">
            <h2 class="title has-text-centered">Sign in to your account</h2>
            <?php if (!empty($error_message)) { ?>
                <p class="has-text-centered error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php } ?>
            <?php if (!empty($account_error)) { ?>
                <p class="has-text-centered error-message"><?php echo htmlspecialchars($account_error); ?></p>
            <?php } ?>
            <?php if (!empty($user_role_error)) { ?>
                <p class="has-text-centered error-message"><?php echo htmlspecialchars($user_role_error); ?></p>
            <?php } ?>
            <form method="POST" action="">
                <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
                <div class="field">
                    <label class="label">User Role</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="user_role" required>
                                <option value="">Select Role</option>
                                <option value="customer" <?php echo (isset($_POST['user_role']) && $_POST['user_role'] === 'customer') ? 'selected' : ''; ?>>Customer</option>
                                <option value="trader" <?php echo (isset($_POST['user_role']) && $_POST['user_role'] === 'trader') ? 'selected' : ''; ?>>Trader</option>
                                <option value="admin" <?php echo (isset($_POST['user_role']) && $_POST['user_role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <input class="input" type="email" name="email" placeholder="Email" required value="<?php echo isset($_COOKIE['email']) ? htmlspecialchars($_COOKIE['email']) : ''; ?>">
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <input class="input" type="password" name="password" placeholder="Password" required>
                    </div>
                </div>
                <div class="field is-grouped is-grouped-multiline">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="remember" <?php echo isset($_COOKIE['remember_token']) ? 'checked' : ''; ?>>
                            Remember me
                        </label>
                    </div>
                    <div class="control">
                        <a href="forgot_password.php" class="has-text-primary">Forgot password?</a>
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <button type="submit" name="sign_in" class="button is-primary is-fullwidth">Sign In</button>
                    </div>
                </div>
            </form>
            <div class="has-text-centered mt-4">
                <p class="has-text-grey">Don't have an account? <a href="customer_signup.php" class="has-text-primary">Sign Up as Customer</a></p>
                <p class="has-text-grey">Become a seller? <a href="trader_signup.php" class="has-text-primary">Sign Up as Trader</a></p>
            </div>
        </div>
    </section>
    <?php include('footer.php'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
            if ($navbarBurgers.length > 0) {
                $navbarBurgers.forEach(el => {
                    el.addEventListener('click', () => {
                        const target = el.dataset.target;
                        const $target = document.getElementById(target);
                        el.classList.toggle('is-active');
                        $target.classList.toggle('is-active');
                    });
                });
            }
        });
    </script>
</body>
</html>