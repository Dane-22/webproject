<?php
require('connection/conn.php');
include 'includes/ui_header.php';

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['id']) || $_SESSION['role'] !== '') {
    header("Location: ../auth/session-destroy.php");

    exit;
}
// Check if the user is logged in

if (!isset($_SESSION['id'])) {
    header("Location: ../auth/session-destroy.php");
    exit;
}

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

// Search functionality
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the SQL statement with search and seller conditions
$sql = "SELECT p.*, u.uname 
        FROM products p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id IS NOT NULL";

if (!empty($searchQuery)) {
    $searchQuery = $db->real_escape_string($searchQuery);
    $sql .= " AND (p.name LIKE '%$searchQuery%' OR u.uname LIKE '%$searchQuery%')";
}

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DMMMSU MARKETPLACE</title>
    <link rel="icon" href="assets/images/dmmmsu.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/new_user.css">
    <style>
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

    <!-- <section class="hero-section">
        <div class="hero-content">
            <h1>WELCOME TO DMMMSU MARKETPLACE</h1>
            <p>Creating opportunities for our potential Agri-Aqua Entrepreneurs!!!</p>
        </div>
    </section> -->
<br>
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
                    <a href="user.php<?php echo !empty($searchQuery) ? '?search='.urlencode($searchQuery) : ''; ?>">All Products</a>
                </li>
                <?php while ($seller = $sellersResult->fetch_assoc()): ?>
                    <li class="category-item <?php echo ($currentSeller == $seller['user_id']) ? 'active' : ''; ?>">
                        <a href="user.php?seller=<?php echo $seller['user_id']; ?><?php echo !empty($searchQuery) ? '&search='.urlencode($searchQuery) : ''; ?>">
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
                                <a href='#'>
                            <?php else: ?>
                                <a href='product-details.php?id=<?php echo $product['id']; ?>'>
                            <?php endif; ?>
                                <img src='uploads/<?php echo htmlspecialchars($product['image']); ?>' alt='<?php echo htmlspecialchars($product['name']); ?>'>
                                <div class='product-info'>
                                    <div class='product-title'><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class='product-price'>₱<?php echo number_format($product['price'], 2); ?></div>
                                    <div class='product-quantity'>Stock: <?php echo $product['product_quantity']; ?></div>
                                </div>
                            </a>
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="no-products">No products available <?php echo $currentSeller ? 'for this seller' : 'at the moment'; ?>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
        document.addEventListener('DOMContentLoaded', function() {
            const toggleSidebarBtn = document.getElementById('toggleSidebar');
            const categorySidebar = document.getElementById('categorySidebar');
            const contentArea = document.getElementById('contentArea');
            
            if (toggleSidebarBtn && categorySidebar && contentArea) {
                toggleSidebarBtn.addEventListener('click', function() {
                    categorySidebar.classList.toggle('hidden');
                    contentArea.classList.toggle('sidebar-hidden');
                });
            }
        });
    </script>

</body>
</html>