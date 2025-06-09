<?php
ob_start(); // Start output buffering
require('connection/conn.php'); 
include 'includes/ui_header.php';

if (!isset($_SESSION['id'])) {
    header('Location: auth-signup.php'); // Redirect to signup if not logged in
    exit;
}

$id = $_SESSION['id'];

// Fetch user details
$sql = "SELECT fname, mname, lname FROM users WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_object();
    $fname = htmlspecialchars($row->fname);
    $mname = htmlspecialchars($row->mname);
    $lname = htmlspecialchars($row->lname);
}

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Function to get product details
function getProductDetails($db, $product_id) {
    $sql = "SELECT id, name, price, image, user_id, product_quantity FROM products WHERE id = ?"; // Fixed line
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}



// Handle product removal
if (isset($_GET['remove'])) {
    $product_id = intval($_GET['remove']);
    
    // Remove from session
    unset($_SESSION['cart'][$product_id]);
    
    // Remove from database cart table
    $delete_sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
    $delete_stmt = $db->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $id, $product_id);
    $delete_stmt->execute();
    
    header('Location: cart.php'); // Redirect to avoid form resubmission
    exit();
}

// Handle quantity update with stock validation
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $product_id => $quantity) {
        // Fetch the available stock for the product
        $sql = "SELECT product_quantity FROM products WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            $available_stock = $product['product_quantity'];

            // Ensure the quantity does not exceed the available stock
            $_SESSION['cart'][$product_id] = min($available_stock, max(1, intval($quantity))); // Set quantity to the max available stock if exceeded
        }
    }
    header('Location: cart.php'); // Redirect to avoid form resubmission
    exit();
}

// Fetch cart items from the session
$cartItems = $_SESSION['cart'];

// Fetch product details
$products = [];
$totalPrice = 0;

// Fetch product details for items in the cart
foreach ($cartItems as $product_id => $quantity) {
    $sql = "SELECT p.id, p.name, p.price, p.image, p.user_id, p.product_quantity FROM products p WHERE p.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        $product['quantity'] = $quantity;
        $product['total'] = $product['price'] * $quantity;
        $totalPrice += $product['total'];
        $products[] = $product;
    }
}

// Reverse the products array to implement LIFO
$products = array_reverse($products);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - DMMMSU MARKETPLACE</title>
    <link rel="stylesheet" href="assets/css/cart.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
</head>
<body>
    <div class="container">
        <h1 class="cart-title">Shopping Cart</h1>
        <div class="cart-container">
            <?php if (empty($products)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty</p>
                    <a href="user.php" class="action-button continue-shopping">Continue Shopping</a>
 </div>
            <?php else: ?>
                <form id="cart-form" action="cart.php" method="POST">
                    <div class="cart-items">
                        <?php foreach ($products as $product): ?>
                            <div class="cart-item">
                                <input type="checkbox" name="selected_products[]" value="<?php echo $product['id']; ?>" class="product-checkbox">
                                <img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                <div class="cart-item-details">
                                    <h3 class="cart-item-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="cart-item-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="cart-item-stock">Available stock: <?php echo $product['product_quantity']; ?> units</div>
                                </div>
                                <input type="number" name="quantity[<?php echo $product['id']; ?>]" 
                                       value="<?php echo $product['quantity']; ?>" 
                                       min="1" max="<?php echo $product['product_quantity']; ?>" 
                                       class="quantity-input" 
                                       data-price="<?php echo $product['price']; ?>"> <!-- Added data-price -->
                                <button type="button" onclick="window.location.href='cart.php?remove=<?php echo $product['id']; ?>'" class="remove-button">
                                    <i class="fas fa-trash-alt"></i>
                                </button>   
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="cart-summary">
                        <div class="total-price">Total: ₱<span id="total-amount"><?php echo number_format($totalPrice, 2); ?></span></div>
                        <div class="cart-actions">
                            <a href="user.php" class="action-button continue-shopping">Continue Shopping</a>
                            <button type="button" id="proceed-checkout" class="action-button checkout">Proceed to Checkout</button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Update grand total on quantity change
            $('#cart-form input[name^="quantity["]').on('input', function() {
                var grandTotal = 0;
                $('.cart-item').each(function() {
                    var quantity = parseInt($(this).find('input[name^="quantity"]').val()) || 0;
                    var price = parseFloat($(this).find('input[name^="quantity"]').data('price')); // Correctly retrieves price
                    var total = quantity * price;

                    // Ensure only one ₱ symbol for each item total
                    $(this).find('.cart-item-price').text('₱' + total.toFixed(2));

                    grandTotal += total;
                });
                // Ensure only one ₱ symbol for grand total
                $('#total-amount').text('₱' + grandTotal.toFixed(2));
            });

            // Capture selected products and quantities, and navigate to checkout
            $('#proceed-checkout').click(function() {
                var selectedProducts = [];
                var quantities = [];
                var isValid = true;
                
                $('.product-checkbox:checked').each(function() {
                    selectedProducts.push($(this).val());
                    var quantity = $(this).closest('.cart-item').find('input[name^="quantity["]').val();
                    quantities.push(quantity);

                    // Validate quantity against stock
                    var availableStock = parseInt($(this).closest('.cart-item').find('input[name^="quantity["]').data('max-stock'));
                    if (parseInt(quantity) > availableStock) {
                        alert("Quantity for " + $(this).closest('.cart-item').find('.cart-item-title').text() + " exceeds available stock.");
                        isValid = false;
                    }
                });

                if (isValid) {
                    if (selectedProducts.length > 0) {
                        var productIds = selectedProducts.join(',');
                        var productQuantities = quantities.join(',');
                        window.location.href = 'checkout.php?id=' + encodeURIComponent(productIds) + '&quantity=' + encodeURIComponent(productQuantities);
                    } else {
                        alert("Please select at least one product to proceed.");
                    }
                }
            });
        });
    </script>
</body>
</html>