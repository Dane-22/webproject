<?php
session_start();
require('connection/conn.php');

// Get the current date (without time)
$currentDate = date('Y-m-d'); // Format: 2024-11-26

// Check if a record for today's date already exists
$sqlCheck = "SELECT id, daily_count FROM visitor_logs WHERE date = '$currentDate'";
$resultCheck = $db->query($sqlCheck);

// Error handling if the query fails
if (!$resultCheck) {
    die("Error: " . $db->error);
}

// If a record exists, update the daily_count
if ($resultCheck && $row = $resultCheck->fetch_assoc()) {
    $newCount = $row['daily_count'] + 1; // Increment the daily count
    $sqlUpdate = "UPDATE visitor_logs SET daily_count = $newCount WHERE id = {$row['id']}";
    if (!$db->query($sqlUpdate)) {
        die("Error: " . $db->error);
    }
} else {
    // If no record exists for today, insert a new record
    $sqlInsert = "INSERT INTO visitor_logs (date, daily_count) VALUES ('$currentDate', 1)";
    if (!$db->query($sqlInsert)) {
        die("Error: " . $db->error);
    }
}

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function updateCartCount() {
    return count($_SESSION['cart']);
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_product_id'])) {
    $product_id = intval($_POST['user_product_id']);
    $stmt = $db->prepare("DELETE FROM productreq WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
    $db->close();
    exit();
}

// Optional: Automatically delete products with quantity 0 from the database
// $deleteSql = "DELETE FROM products WHERE product_quantity = 0";
// $db->query($deleteSql);

// Fetch all distinct sellers for the category sidebar
$sellersSql = "SELECT DISTINCT u.id as user_id, u.uname 
               FROM products p 
               JOIN users u ON p.user_id = u.id 
               ORDER BY u.uname";
$sellersResult = $db->query($sellersSql);

// Check if a seller filter is applied
$currentSeller = isset($_GET['seller']) ? intval($_GET['seller']) : null;

// Modify the product query based on filter
$sql = "SELECT p.*, u.uname 
        FROM products p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id IS NOT NULL";
        
if ($currentSeller) {
    $sql .= " AND p.user_id = $currentSeller";
}

$sql .= " ORDER BY u.uname";

$stmt = $db->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="DMMMSU ATBI Marketplace">
    <meta name="keywords" content="admin templates, bootstrap admin templates, dashboard, responsive">
    <meta name="author" content="CodedThemes">
    <title>DMMMSU MARKETPLACE</title>
    <link rel="icon" href="assets/images/dmmmsu.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/new_user.css">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #218838;
            --accent-color: #17a2b8;
            --text-color: #333;
            --light-gray: #f8f9fa;
            --header-height: 70px;
            --header-height-mobile: 60px;
            --transition-speed: 0.3s;
        }

        header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: var(--header-height);
            transition: height var(--transition-speed) ease;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--text-color);
            font-weight: bold;
            transition: transform var(--transition-speed) ease;
        }

        .logo:hover {
            transform: translateY(-2px);
        }

        .logo img {
            height: 40px;
            margin-right: 10px;
            transition: height var(--transition-speed) ease;
        }

        .logo span {
            font-size: 1.2rem;
            white-space: nowrap;
        }

        .search-container {
            flex: 1;
            max-width: 500px;
            margin: 0 20px;
            position: relative;
        }

        #search-input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid var(--light-gray);
            border-radius: 25px;
            font-size: 1rem;
            transition: all var(--transition-speed) ease;
            background: var(--light-gray);
        }

        #search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        .right-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .cart-icon {
            position: relative;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all var(--transition-speed) ease;
        }

        .cart-icon:hover {
            background: var(--light-gray);
            transform: translateY(-2px);
        }

        .cart-icon i {
            font-size: 1.2rem;
            color: var(--text-color);
        }

        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary-color);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .auth-buttons {
            display: flex;
            gap: 10px;
        }

        .auth-button {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
            border: none;
        }

        .auth-button.signup {
            background: var(--primary-color);
            color: white;
        }

        .auth-button.login {
            background: var(--light-gray);
            color: var(--text-color);
        }

        .auth-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .auth-button.signup:hover {
            background: var(--secondary-color);
        }

        .auth-button.login:hover {
            background: #e9ecef;
        }

        body {
            padding-top: var(--header-height);
            transition: padding-top var(--transition-speed) ease;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .header-content {
                padding: 0 15px;
            }

            .search-container {
                max-width: 400px;
            }

            .auth-button {
                padding: 7px 18px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 768px) {
            :root {
                --header-height: var(--header-height-mobile);
            }

            .header-content {
                padding: 0 10px;
            }

            .logo img {
                height: 35px;
            }

            .logo span {
                font-size: 1rem;
            }

            .search-container {
                max-width: 300px;
                margin: 0 10px;
            }

            #search-input {
                padding: 10px 35px 10px 15px;
                font-size: 0.9rem;
            }

            .cart-icon, .auth-button {
                width: 35px;
                height: 35px;
            }

            .cart-icon i {
                font-size: 1.1rem;
            }

            .auth-buttons {
                gap: 8px;
            }

            .auth-button {
                padding: 6px 15px;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .header-content {
                padding: 0 8px;
            }

            .logo span {
                display: none;
            }

            .search-container {
                max-width: 200px;
                margin: 0 8px;
            }

            #search-input {
                padding: 8px 30px 8px 12px;
                font-size: 0.85rem;
            }

            .cart-icon, .auth-button {
                width: 32px;
                height: 32px;
            }

            .cart-icon i {
                font-size: 1rem;
            }

            .auth-buttons {
                gap: 5px;
            }

            .auth-button {
                padding: 5px 12px;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 360px) {
            .search-container {
                max-width: 150px;
            }

            #search-input {
                padding: 8px 25px 8px 10px;
                font-size: 0.8rem;
            }

            .auth-buttons {
                gap: 3px;
            }

            .auth-button {
                padding: 4px 10px;
                font-size: 0.7rem;
            }
        }

        /* Toggle Sidebar Button */
        .toggle-sidebar {
            position: fixed;
            left: 15px;
            top: 100px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
        }

        /* Category Sidebar */
        .category-sidebar {
            width: 250px;
            padding: 15px;
            background: #28a745;
            position: fixed;
            top: 150px;
            left: 0;
            height: calc(100vh - 150px);
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .category-sidebar.hidden {
            transform: translateX(-250px);
        }

        .category-title {
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 18px;
            color: white;
            padding-bottom: 10px;
        }

        .category-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .category-item {
            padding: 10px 15px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .category-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .category-item.active {
            background: #fff;
        }

        .category-item a {
            color: white;
            text-decoration: none;
            display: block;
            width: 100%;
            font-size: 14px;
            line-height: 1.4;
        }

        .category-item.active a {
            color: #28a745;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            margin-left: 250px;
            min-width: 300px;
            transition: margin-left 0.3s ease;
            padding: 20px;
        }

        .content-area.sidebar-hidden {
            margin-left: 0;
        }

        /* Product Grid Styles */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 30px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .product-card {
            width: 100%;
        }

        .product-card a {
            height: 100%;
        }

        /* Enhanced Responsive Styles */
        @media (max-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 20px;
                padding: 15px;
            }
            
            .product-card img {
                height: 220px;
            }
        }

        @media (max-width: 768px) {
            .category-sidebar {
                width: 100%;
                position: fixed;
                top: 80px;
                height: auto;
                max-height: calc(100vh - 80px);
            }
            
            .category-sidebar.hidden {
                transform: translateY(-100%);
            }
            
            .toggle-sidebar {
                top: 85px;
                left: 10px;
            }
            
            .content-area {
                margin-left: 0;
                padding: 10px;
                margin-top: 60px;
            }

            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 15px;
                padding: 10px;
            }

            .product-card img {
                height: 180px;
            }

            .product-info {
                padding: 12px;
            }

            .product-title {
                font-size: 15px;
            }

            .product-price {
                font-size: 17px;
            }
        }

        @media (max-width: 480px) {
            .category-sidebar {
                top: 70px;
            }
            
            .toggle-sidebar {
                top: 75px;
            }
            
            .content-area {
                padding: 5px;
                margin-top: 50px;
            }

            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                padding: 8px;
            }

            .product-card img {
                height: 140px;
            }

            .product-info {
                padding: 10px;
            }

            .product-title {
                font-size: 14px;
                margin-bottom: 5px;
            }

            .product-price {
                font-size: 15px;
                margin-bottom: 5px;
            }

            .product-quantity {
                font-size: 12px;
            }
        }

        @media (max-width: 360px) {
            .product-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                padding: 6px;
            }

            .product-card img {
                height: 120px;
            }
            
            .product-info {
                padding: 8px;
            }
            
            .product-title {
                font-size: 13px;
            }
            
            .product-price {
                font-size: 14px;
            }
            
            .product-quantity {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="" class="logo">
                <img src="assets/images/atbi-logo.png" alt="ATBI Logo">
                DMMMSU MARKETPLACE
            </a>
            
            <div class="search-container">
                <input type="text" id="search-input" placeholder="Search for products...">
            </div>
            
            <div class="right-section">
                <div class="cart-icon" onclick="window.location.href='auth-signup.php'">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count"><?php echo updateCartCount(); ?></span>
                </div>
                
                <div class="auth-buttons">
                    <button class="auth-button signup" onclick="window.location.href='auth-signup.php'">Sign Up</button>
                    <button class="auth-button login" onclick="window.location.href='auth-signin.php'">Login</button>
                </div>
            </div>
        </div>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <h1>WELCOME TO DMMMSU MARKETPLACE</h1>
            <p>Creating opportunities for our potential Agri-Aqua Entrepreneurs!!!</p>
        </div>
    </section>

    <div class="main-content-wrapper">
        <!-- Toggle Sidebar Button -->
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Category Sidebar -->
        <div class="category-sidebar" id="categorySidebar">
            <div class="category-title">Sellers</div>
            <ul class="category-list">
                <li class="category-item <?php echo !$currentSeller ? 'active' : ''; ?>">
                    <a href="new_user.php<?php echo !empty($searchQuery) ? '?search='.urlencode($searchQuery) : ''; ?>">All Sellers</a>
                </li>
                <?php while ($seller = $sellersResult->fetch_assoc()): ?>
                    <li class="category-item <?php echo ($currentSeller == $seller['user_id']) ? 'active' : ''; ?>">
                        <a href="new_user.php?seller=<?php echo $seller['user_id']; ?><?php echo !empty($searchQuery) ? '&search='.urlencode($searchQuery) : ''; ?>">
                            <?php echo htmlspecialchars($seller['uname']); ?>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
        
        <!-- Main Content Area -->
        <div class="content-area" id="contentArea">
            <div class="container">
                <?php if ($result->num_rows > 0): ?>
                    <?php
                    $current_user = '';
                    while ($product = $result->fetch_assoc()):
                        if ($current_user !== $product['uname']):
                            if ($current_user !== '') echo "</div>";
                            $current_user = $product['uname'];
                            // Only show seller title if showing all sellers
                            if (!$currentSeller) {
                                echo "<h2 class='seller-title'>" . htmlspecialchars($current_user) . "'s Products</h2>";
                            }
                            echo "<div class='product-grid'>";
                        endif;
                    ?>
<div class='product-card'>
    <?php if ($product['product_quantity'] == 0): ?>
        <a href='javascript:void(0);' onclick="showOutOfStock()">
            <img src='uploads/<?php echo htmlspecialchars($product['image']); ?>' alt='<?php echo htmlspecialchars($product['name']); ?>'>
            <div class='product-info'>
                <div class='product-title'><?php echo htmlspecialchars($product['name']); ?></div>
                <div class='product-price'>₱<?php echo number_format($product['price'], 2); ?></div>
                <div class='product-quantity'>Out of Stock</div>
            </div>
        </a>
    <?php else: ?>
        <a href='javascript:void(0);' onclick="checkLoginBeforeView(<?php echo $product['id']; ?>)">
            <img src='uploads/<?php echo htmlspecialchars($product['image']); ?>' alt='<?php echo htmlspecialchars($product['name']); ?>'>
            <div class='product-info'>
                <div class='product-title'><?php echo htmlspecialchars($product['name']); ?></div>
                <div class='product-price'>₱<?php echo number_format($product['price'], 2); ?></div>
                <div class='product-quantity'>Stock: <?php echo $product['product_quantity']; ?></div>
            </div>
        </a>
    <?php endif; ?>
</div>
                    <?php endwhile; ?>
                    </div> <!-- Close last product grid -->
                <?php else: ?>
                    <p class="no-products">No products available <?php echo $currentSeller ? 'for this seller' : 'at the moment'; ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">    
                <h3>About DMMMSU MARKETPLACE</h3>
                <p>DMMMSU MARKETPLACE is an innovative e-commerce platform developed by ATBI, connecting local sellers with buyers in a secure and user-friendly environment.</p>
                <div class="social-links">
                    <a href="https://www.facebook.com/DMMMSUATBI/following/"><i class="fab fa-facebook"></i></a>
                    <a href="https://www.instagram.com/dmmmsu_atbi?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Contact Information</h3>
                <div class="footer-contact">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Raois, Bacnotan, La Union</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>0917 873 5562</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>atbi@dmmmsu.edu.ph</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> DMMMSU MARKETPLACE. All rights reserved. | Powered by ATBI</p>
        </div>
    </footer>

    <!-- Required Js -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.6/umd/popper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script>
        function toggleCart() {
            var cartDropdown = document.getElementById('cart-dropdown');
            cartDropdown.classList.toggle('show');
        }

        function removeFromCart(productId) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                window.location.href = 'cart.php?action=remove&id=' + productId;
            }
        }

        document.addEventListener('click', function(event) {
            var cartDropdown = document.getElementById('cart-dropdown');
            var cartIcon = document.querySelector('.cart-icon');
            if (!cartIcon.contains(event.target) && !cartDropdown.contains(event.target)) {
                cartDropdown.classList.remove('show');
            }
        });
        
        function toggleDropdown() {
            var dropdownMenu = document.getElementById('user-dropdown-menu');
            dropdownMenu.classList.toggle('show');
        }
       
        // Enhanced Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSidebarBtn = document.getElementById('toggleSidebar');
            const categorySidebar = document.getElementById('categorySidebar');
            const contentArea = document.getElementById('contentArea');
            
            // Check saved state
            const sidebarHidden = localStorage.getItem('sidebarHidden') === 'true';
            
            // Apply initial state
            if (sidebarHidden) {
                categorySidebar.classList.add('hidden');
                contentArea.classList.add('sidebar-hidden');
                toggleSidebarBtn.classList.add('sidebar-hidden');
            }
            
            // Toggle sidebar on button click
            toggleSidebarBtn.addEventListener('click', function() {
                categorySidebar.classList.toggle('hidden');
                contentArea.classList.toggle('sidebar-hidden');
                toggleSidebarBtn.classList.toggle('sidebar-hidden');
                
                // Save state
                localStorage.setItem('sidebarHidden', categorySidebar.classList.contains('hidden'));
                
                // Animate icon rotation
                const icon = this.querySelector('i');
                if (categorySidebar.classList.contains('hidden')) {
                    icon.style.transform = 'rotate(90deg)';
                } else {
                    icon.style.transform = 'rotate(0deg)';
                }
            });
            
            // Search functionality
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterProducts(this.value);
                });
            }
        });

        function deleteUserProduct(productId) {
            if (confirm('Are you sure you want to permanently delete this product?')) {
                fetch('new_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        'user_product_id': productId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('user-row-' + productId).remove();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        
        function showOutOfStock() {
            alert("This product is currently out of stock.");
        }

        function checkLoginBeforeView(productId) {
            <?php if(isset($_SESSION['user_id'])): ?>
                // User is logged in, redirect to product page
                window.location.href = 'product-details.php?id=' + productId;
            <?php else: ?>
                // User is not logged in, show login prompt
                if(confirm('You need to log in first to view this product. Would you like to log in now?')) {
                    window.location.href = 'auth-signin.php?redirect=product-details.php?id=' + productId;
                }
            <?php endif; ?>
        }
        
        // Add responsive search functionality
        function filterProducts(query) {
            const productCards = document.querySelectorAll('.product-card');
            const sellerTitles = document.querySelectorAll('.seller-title');
            let visibleSellers = 0;
            
            // Convert query to lowercase for case-insensitive search
            query = query.toLowerCase();
            
            // First hide all product cards
            productCards.forEach(card => {
                const title = card.querySelector('.product-title').textContent.toLowerCase();
                const price = card.querySelector('.product-price').textContent.toLowerCase();
                
                if (title.includes(query) || price.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Check if any products are visible for each seller
            sellerTitles.forEach(title => {
                const sellerName = title.textContent.replace("'s Products", '');
                const sellerProducts = document.querySelectorAll(`.product-card:not([style*="display: none"])`);
                let hasVisibleProducts = false;
                
                sellerProducts.forEach(product => {
                    const productTitle = product.querySelector('.product-title').textContent;
                    if (productTitle.includes(sellerName)) {
                        hasVisibleProducts = true;
                    }
                });
                
                if (hasVisibleProducts) {
                    title.style.display = 'block';
                    visibleSellers++;
                } else {
                    title.style.display = 'none';
                }
            });
            
            // Show "No products found" message if no products are visible
            const noProductsMsg = document.querySelector('.no-products');
            if (noProductsMsg) {
                if (visibleSellers === 0) {
                    noProductsMsg.style.display = 'block';
                    noProductsMsg.textContent = 'No products found matching your search.';
                } else {
                    noProductsMsg.style.display = 'none';
                }
            }
        }
    </script>

</body>
</html>