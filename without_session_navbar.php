<?php
include("connection/connection.php");
require("PHPMailer-master/trader_verify_email.php");

$query = '
    SELECT 
        TRADER.TRADER_ID, 
        TRADER.SHOP_NAME, 
        TRADER.TRADER_TYPE, 
        CLECK_USER.FIRST_NAME || \' \' || CLECK_USER.LAST_NAME AS NAME, 
        CLECK_USER.USER_EMAIL,
        PRODUCT_CATEGORY.CATEGORY_TYPE,
        SHOP.SHOP_ID
    FROM 
        TRADER
    JOIN 
        CLECK_USER ON TRADER.USER_ID = CLECK_USER.USER_ID
    JOIN 
        PRODUCT_CATEGORY ON TRADER.TRADER_TYPE = PRODUCT_CATEGORY.CATEGORY_ID
    JOIN 
        SHOP ON TRADER.USER_ID = SHOP.USER_ID
    WHERE 
        TRADER.VERIFICATION_STATUS = 1 
        AND TRADER.VERFIED_ADMIN = 1 
        AND TRADER.VERIFICATION_SEND = 0';

$stid = oci_parse($conn, $query);
if (!$stid) {
    $e = oci_error($conn);
    error_log("Query parse failed in without_session_navbar.php: " . $e['message'], 3, 'error.log');
}

oci_execute($stid);

while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
    $trader_id = $row['TRADER_ID'];
    $shop_name = $row['SHOP_NAME'];
    $trader_type = $row['TRADER_TYPE'];
    $name = $row['NAME'];
    $user_email = $row['USER_EMAIL'];
    $shop_category = $row['CATEGORY_TYPE'];
    $shop_id = $row['SHOP_ID'];
    sendApprovalEmail($user_email, $name, $shop_id, $trader_id, $shop_name, $shop_category);
}

oci_free_statement($stid);
oci_close($conn);

if (isset($_POST["search"])) {
    require("input_validation/input_sanitization.php");
    $search_text = isset($_POST["searchText"]) ? sanitizeFirstName($_POST["searchText"]) : "";
    header("Location: search_page.php?value=" . urlencode($search_text));
    exit;
}
?>
<header>
    <nav>
        <div class="container">
            <a href="index.php" class="logo"><img src="logo.png" alt="Cleckfax Traders Logo"></a>
            <div class="nav-links">
                <ul>
                    <li class="highlight"><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contactus.php">Contacts</a></li>
                </ul>
            </div>
            <div class="search">
                <form method="POST" action="" name="search_form" id="search_form">
                    <input type="text" name="searchText" placeholder="<?php echo isset($search_text) ? htmlspecialchars($search_text) : 'Search...'; ?>" id="searchText" required>
                    <input type="submit" value="Search" name="search" id="search">
                </form>
            </div>
            <div class="menu-toggle"><i class="fas fa-bars"></i></div>
            <div class="submenu">
                <ul>
                    <li class="highlight"><a href="index.php">Home</a></li>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contactus.php">Contacts</a></li>
                    <li><a href="category.php">Category</a></li>
                    <li><a href="customer_signin.php">Sign In</a></li>
                    <li><a href="customer_signup.php">Sign Up</a></li>
                </ul>
            </div>
            <div class="icons">
                <a href="customer_signin.php?return_url=wishlist.php" class="icon"><i class="fas fa-heart"></i></a>
                <a href="customer_signin.php?return_url=cart.php" class="icon"><i class="fas fa-shopping-cart"></i></a>
                <div class="nav-links">
                    <ul>
                        <li><a href="customer_signin.php">Sign In</a></li>
                        <li><a href="customer_signup.php">Sign Up</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</header>
<style>
    nav {
        background-color: #f5f5f5;
        padding: 0.5rem 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 1200px;
        margin: 0 auto;
    }
    .logo img {
        height: 40px;
    }
    .nav-links ul {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .nav-links li {
        margin: 0 1rem;
    }
    .nav-links a {
        text-decoration: none;
        color: #4a4a4a;
        font-weight: 500;
    }
    .nav-links li.highlight a {
        color: #48c774;
    }
    .search form {
        display: flex;
        align-items: center;
    }
    .search input[type="text"] {
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        width: 200px;
    }
    .search input[type="submit"] {
        padding: 0.5rem 1rem;
        background-color: #48c774;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-left: 0.5rem;
    }
    .menu-toggle {
        display: none;
        font-size: 1.5rem;
        cursor: pointer;
    }
    .submenu {
        display: none;
    }
    .icons {
        display: flex;
        align-items: center;
    }
    .icons a.icon {
        margin: 0 0.5rem;
        font-size: 1.5rem;
        color: #4a4a4a;
        text-decoration: none;
    }
    @media (max-width: 768px) {
        .nav-links, .search, .icons .nav-links {
            display: none;
        }
        .menu-toggle {
            display: block;
        }
        .submenu {
            display: block;
            position: absolute;
            top: 60px;
            left: 0;
            width: 100%;
            background-color: #f5f5f5;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .submenu ul {
            list-style: none;
            padding: 1rem;
        }
        .submenu li {
            margin: 0.5rem 0;
        }
        .submenu a {
            color: #4a4a4a;
            text-decoration: none;
        }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuToggle = document.querySelector('.menu-toggle');
        const submenu = document.querySelector('.submenu');
        menuToggle.addEventListener('click', () => {
            submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
        });
        document.querySelector('#search_form').addEventListener('submit', (e) => {
            const searchInput = document.querySelector('#searchText');
            if (!searchInput.value.trim()) {
                e.preventDefault();
                alert('Please enter a search term.');
            }
        });
    });
</script>