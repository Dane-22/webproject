<?php
session_start();
require 'connection/conn.php';

// Ensure POST data is set and validate
if (!isset($_POST['payment_method']) || empty($_POST['payment_method'])) {
    die("Payment method is required.");
}

if (!isset($_POST['total_price']) || !is_numeric($_POST['total_price']) || $_POST['total_price'] <= 0) {
    die("Invalid total price.");
}

// Retrieve and sanitize POST data
$payment_method = $db->real_escape_string($_POST['payment_method']);
$total_price = floatval($_POST['total_price']); // Ensure it is a float
$name = $db->real_escape_string($_POST['name']);
$contact_number = $db->real_escape_string($_POST['contact_number']);
$street_address = $db->real_escape_string($_POST['street_address']);
$suburb = $db->real_escape_string($_POST['suburb']);
$city = $db->real_escape_string($_POST['city']);
$state = $db->real_escape_string($_POST['state']);
$postal_code = $db->real_escape_string($_POST['postal_code']);

// Prepare the SQL statement
$sql = "INSERT INTO orders1 (name, contact_number, street_address, suburb, city, state, postal_code, payment_method, total_price, order_date, order_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')";

$stmt = $db->prepare($sql);

if ($stmt === false) {
    die("Error preparing statement: " . $db->error);
}

// Bind parameters and execute
$stmt->bind_param("ssssssssd", $name, $contact_number, $street_address, $suburb, $city, $state, $postal_code, $payment_method, $total_price);

if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

// Store order ID in session
$order_id = $db->insert_id;
$_SESSION['order_id'] = $order_id;

// Handle file upload (proof of payment)
if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] == UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['proof_of_payment']['tmp_name'];
    $file_name = basename($_FILES['proof_of_payment']['name']);
    $upload_dir = 'uploads/';
    
    // Ensure uploads directory exists and is writable
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Move uploaded file
    $file_path = $upload_dir . $file_name;
    if (move_uploaded_file($file_tmp, $file_path)) {
        // Optionally save the file path to the database or perform other actions
        // Example: update the order record with proof of payment path
        $update_sql = "UPDATE orders1 SET proof_of_payment = ? WHERE order_id = ?";
        $update_stmt = $db->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param("si", $file_path, $order_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
    } else {
        echo "Failed to upload proof of payment.";
    }
}

// Close the statement and connection
$stmt->close();
$db->close();

// Redirect to my-order.php to view the new order status
header('Location: my-order.php');
exit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Order Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #eaf0f2;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #ffffff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .btn-back {
            background-color: #28a745; /* Green color */
            color: white;
            border: none;
        }
        .btn-back:hover {
            background-color: #218838; /* Darker green */
        }
        .btn-success {
            background-color: #28a745; /* Green color */
            border: none;
        }
        .btn-success:hover {
            background-color: #218838; /* Darker green */
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <a href="checkout.php" class="btn btn-back btn-lg mb-4">
            <i class="fas fa-arrow-left"></i> Back to Checkout
        </a>

        <!-- Order Confirmation -->
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">Order Placed Successfully!</h4>
            <p>Your order has been placed successfully. Thank you for shopping with us.</p>
            <hr>
            <p class="mb-0">You can track your order status and view your order history in your account.</p>
        </div>
    </div>
    <!-- Include JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.min.js"></script>
    <!-- Font Awesome for Back Arrow Icon -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/js/all.min.js"></script>
</body>
</html>
