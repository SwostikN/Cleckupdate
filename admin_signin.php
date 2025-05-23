<?php
session_start();
$error_message = ""; // Declare the variable here

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
    $sql = "SELECT FIRST_NAME, LAST_NAME, USER_ID, USER_PASSWORD, USER_PROFILE_PICTURE, USER_TYPE
            FROM CLECK_USER
            WHERE USER_EMAIL = :email";

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

            if ($password == $passwords && $user_role == "Admin") {
                if ($remember == 1) {
                    setcookie("email_admin", $email, time() + 60 * 60 * 24 * 30, "/");
                    setcookie("password_admin", $pass, time() + 60 * 60 * 24 * 30, "/");
                }
                // Registering session username
                $_SESSION["email"] = $email;
                $_SESSION["accesstime"] = date("ymdhis");
                $_SESSION["name"] = $first_name . " " . $last_name;
                $_SESSION["picture"] = $profile_picture;
                $_SESSION["userid"] = $user_id;
                $_SESSION["usertype"] = $user_role;
                header("Location:admin_dashboard/admin_dashboard.php");
                exit();
            } else {
                $error_message = "Incorrect Password. Please try again!";
            }
        } else {
            $error_message = "Incorrect email. Please try again!";
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
    <title>Admin Sign In</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="without_session_navbar.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="customer_signin.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php
        include("without_session_navbar.php");
    ?>
    <div class="sign-in-container">
        <h2>Admin Sign In</h2>
        <?php
            if (!empty($error_message)) {
                echo "<p style='color: red;'>$error_message</p>";
            }
        ?>
        <form method="POST" id="trader_signin" name="trader_signin" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" placeholder="Enter your Email" required value="<?php if (isset($_COOKIE["email_admin"])) { echo $_COOKIE["email_admin"]; } ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}" title="Password must be at least 6 characters long and contain at least one lowercase letter, one uppercase letter, and one number" value="<?php if (isset($_COOKIE["password_admin"])) { echo $_COOKIE["password_admin"]; } ?>">
            </div>
            <div class="form-group">
                <label for="remember"><input type="checkbox" id="remember" name="remember" alt="Remember Me" value="1">Remember Me</label>
            </div>
            <div class="form-group">
                <input type="submit" value="Sign In" name="sign_in" id="sign_in">
            </div>
        </form>
    </div>
    <?php
        include("footer.php");
    ?>
    <script src="without_session_navbar.js"></script>
</body>
</html>
