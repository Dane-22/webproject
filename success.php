<?php
require('connection/conn.php'); 
include 'includes/ui_header.php';

// Check if the user is logged in
$id = isset($_SESSION['id']) ? $_SESSION['id'] : null;

// Fetch user details if logged in
if ($id) {
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
}

// Validate order_id
if (!isset($_GET['order_id'])) { 
    die("Order ID is missing."); 
} 

$order_id = intval($_GET['order_id']); 

// Fetch order details
$order_sql = "SELECT 
                name, contact_number, street_address, barangay, 
                city, state, postal_code, total_price, 
                payment_method, proof_of_payment, created_at 
              FROM orders1 
              WHERE order_id = ?";
$order_stmt = $db->prepare($order_sql);

if (!$order_stmt) { 
    die("Error preparing statement: " . $db->error); 
} 

$order_stmt->bind_param("i", $order_id); 
$order_stmt->execute(); 
$order_result = $order_stmt->get_result(); 

if (!$order_result || $order_result->num_rows === 0) { 
    die("Order not found."); 
} 

$order = $order_result->fetch_object();
$customer_name = htmlspecialchars($order->name); 
$contact_number = htmlspecialchars($order->contact_number); 
$street_address = htmlspecialchars($order->street_address); 
$barangay = htmlspecialchars($order->barangay); 
$city = htmlspecialchars($order->city); 
$state = htmlspecialchars($order->state); 
$postal_code = htmlspecialchars($order->postal_code); 
$total_price = $order->total_price; 
$payment_method = $order->payment_method; 
$proof_of_payment = $order->proof_of_payment; 
$order_date = $order->created_at;

// Fetch order items
$items_sql = "SELECT 
                oi.product_id, oi.quantity, oi.price,
                p.name AS product_name, p.image,
                u.fname AS seller_fname, u.lname AS seller_lname
              FROM `order_items` oi
              JOIN products p ON oi.product_id = p.id
              JOIN users u ON p.user_id = u.id
              WHERE oi.order_id = ?";
$items_stmt = $db->prepare($items_sql);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt #<?= $order_id ?> | DMMMSU MARKETPLACE</title>
    <link rel="icon" href="assets/images/dmmmsu.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .receipt-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .receipt-header h1 {
            color: #007bff;
            margin: 0;
        }
        .receipt-header .order-number {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .customer-info {
            margin: 25px 0;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 10px;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        .items-table th {
            background-color: #007bff;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .total-row {
            font-weight: 600;
            background-color: #e9ecef !important;
        }
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .seller-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        @media print {
            .action-buttons {
                display: none;
            }
            body {
                padding: 0;
                background: white;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
            }
        }
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .info-label {
                margin-top: 10px;
            }
        }
        .btn-success {
    background-color: #28a745;
    color: white;
}

    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>Order Receipt</h1>
            <div class="order-number">Order #<?= $order_id ?></div>
        </div>
        
        <div class="customer-info">
            <div class="info-grid">
                <div class="info-label">Customer Name:</div>
                <div><?= $customer_name ?></div>
                
                <div class="info-label">Contact Number:</div>
                <div><?= $contact_number ?></div>
                
                <div class="info-label">Delivery Address:</div>
                <div>
                    <?= $street_address ?>, <?= $barangay ?><br>
                    <?= $city ?>, <?= $state ?> <?= $postal_code ?>
                </div>
                
                <div class="info-label">Payment Method:</div>
                <div><?= $payment_method ?></div>
                
                <div class="info-label">Order Date:</div>
                <div><?= date('F j, Y g:i A', strtotime($order_date)) ?></div>
            </div>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img src="uploads/<?= htmlspecialchars($item['image']) ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                 class="item-image">
                            <div>
                                <?= htmlspecialchars($item['product_name']) ?>
                                <div class="seller-info">
                                    Sold by: <?= htmlspecialchars($item['seller_fname'] . ' ' . htmlspecialchars($item['seller_lname'])) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>₱<?= number_format($item['price'], 2) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">Total:</td>
                    <td>₱<?= number_format($total_price, 2) ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- <div class="action-buttons">
            <a href="javascript:window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Receipt
            </a> -->
            <a href="user.php" class="btn btn-success">
    <i class="fas fa-shopping-bag"></i> Continue Shopping
</a>
        </div>
    </div>
    
    <script>
        // Clear cart after successful order viewing
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('cartItems');
        }
        <?php if (isset($_SESSION['cart'])): ?>
            fetch('clear_cart.php')
                .catch(error => console.error('Error clearing cart:', error));
        <?php endif; ?>
    </script>
</body>
</html>