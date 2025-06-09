
<?php
require('connection/conn.php'); // Ensure this file sets up $db for database connection
session_start();

// Check if the user is logged in
$user_id = isset($_SESSION['id']) && is_numeric($_SESSION['id']) ? $_SESSION['id'] : null;

// Cart items (from session)
$cart_items = $_SESSION['cart'] ?? array();

// If the user is logged in, validate the cart
if ($user_id && empty($cart_items)) {
    // If the cart is empty, redirect to the cart page
    header('Location: cart.php');
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch user details if logged in
if ($user_id) {
    $sql = "SELECT fname, mname, lname, contact_number FROM users WHERE id = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        die("Error preparing statement: " . $db->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_object();
        $fname = htmlspecialchars($row->fname);
        $mname = htmlspecialchars($row->mname);
        $lname = htmlspecialchars($row->lname);
        $contact_number = htmlspecialchars($row->contact_number); // Fetch contact number
    }
} else {
    // Default guest details or leave empty
    $fname = $mname = $lname = "";
    $contact_number = ""; // Default empty for guests
}

$total_price = 0;
$cart_details = [];

// Check if products are passed in the URL
if (isset($_GET['id']) && isset($_GET['quantity'])) {
    $ids = explode(',', $_GET['id']);
    $quantities = explode(',', $_GET['quantity']);

    foreach ($ids as $index => $id) {
        $id = intval($id);
        $quantity = intval($quantities[$index]);

        if ($quantity <= 0) {
            die("Invalid quantity for product ID: $id. Please check your cart or URL.");
        }

        $query = "
            SELECT p.id AS id, p.name AS name, p.price AS price, p.user_id AS user_id, p.image AS image, 
                   u.fname AS user_fname, u.lname AS user_lname
            FROM products p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id = ?";

        $stmt = $db->prepare($query);
        if (!$stmt) {
            die("Error preparing product query: " . $db->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $product_result = $stmt->get_result();

        if ($product_result && $product_result->num_rows > 0) {
            $product = $product_result->fetch_object();

            $total_price += $product->price * $quantity;

            $cart_details[] = [
            'id' => $product->id,          // product_id
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $quantity,       // quantity
            'total_price' => $product->price * $quantity,
            'user_id' => $product->user_id, // owner_id
            'user_fname' => $product->user_fname,
            'user_lname' => $product->user_lname
        ];
        } else {
            die("Product details not found for product ID: $id");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debugging: Print out POST data
    echo '<pre>';
    print_r($_POST); // This will print out all POST data for debugging
    echo '</pre>';
    
    $errors = [];
    $required_fields = ['full_name', 'contact_number', 'street_address', 'barangay', 'city', 'province', 'postal_code', 'payment_method'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
        }
    }

    // Validate file upload
    if (empty($_FILES['proof_of_payment']['name'])) {
        $errors[] = "Proof of Payment is required.";
    } else {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($_FILES['proof_of_payment']['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, and JPEG files are allowed.";
        }
    }

    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "<p style='color: red;'>$error</p>";
        }
        exit;
    }

 // Get and validate form data
 $full_name = htmlspecialchars($_POST['full_name'] ?? '');
 $contact_number = htmlspecialchars($_POST['contact_number'] ?? '');
 $street_address = htmlspecialchars($_POST['street_address'] ?? '');
 $postal_code = htmlspecialchars($_POST['postal_code'] ?? '');
 $payment_method = htmlspecialchars($_POST['payment_method'] ?? '');
 $reference_number = htmlspecialchars($_POST['reference_number'] ?? '');
 $payment_date_time = htmlspecialchars($_POST['payment_date_time'] ?? '');

 // Extract descriptive names from pipe-separated values
 $region_name = htmlspecialchars($_POST['region_name'] ?? '');
 $province_name = isset($_POST['province']) ? explode('|', $_POST['province'])[1] : '';
 $city_name = isset($_POST['city']) ? explode('|', $_POST['city'])[1] : '';
 $barangay_name = isset($_POST['barangay']) ? explode('|', $_POST['barangay'])[1] : '';
    $total_item_price = $product->price * $quantity;

    // Handle file upload
    $proof_of_payment = $_FILES['proof_of_payment']['name'];
    $upload_dir = "uploads/";
    $file_ext = pathinfo($proof_of_payment, PATHINFO_EXTENSION);
    $new_filename = uniqid() . '.' . $file_ext;

    if (!move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $upload_dir . $new_filename)) {
        die("Error uploading file. Please try again.");
    }

    $db->query("START TRANSACTION");

    try {
// Prepare the statement for inserting into orders1
$stmt = $db->prepare("INSERT INTO orders1 (
    name,
    contact_number,
    street_address,
    region,
    state,
    city,
    barangay,
    postal_code,
    total_price,
    payment_method,
    reference_number,
    payment_date_time,
    proof_of_payment,
    created_at,
    order_date,
    order_status,
    user_id
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'Pending', ?)");

if (!$stmt) {
    die("Error preparing statement: " . $db->error);
}

// Bind parameters
$stmt->bind_param("ssssssssdssssi", 
    $full_name,
    $contact_number, 
    $street_address,
    $region_name,
    $province_name,
    $city_name,
    $barangay_name,
    $postal_code,
    $total_price, // Ensure this is a double
    $payment_method,
    $reference_number,
    $payment_date_time, 
    $new_filename, 
    $user_id // Ensure this is an integer
);
if (!$stmt->execute()) {
    throw new Exception("Error executing order insert: " . $stmt->error);
}

        // Get the last inserted order ID
        $order_id = $db->insert_id;

        // Insert cart items into order_items table
        foreach ($cart_details as $item) {
            $item['total_item_price'] = $item['quantity'] * $item['price'];
            $status = 'Pending'; // Define status explicitly
            
            $sql = "INSERT INTO order_items (
                order_id,
                product_id, 
                quantity, 
                price, 
                owner_id,
                total_item_price,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt_order_items = $db->prepare($sql);

    if (!$stmt_order_items) {
        die("Error preparing order items statement: " . $db->error);
    }

     if (!$stmt_order_items->bind_param(
                "iidsids", 
                $order_id,
                $item['id'],
                $item['quantity'],
                $item['price'],
                $item['user_id'],
                $item['total_item_price'],
                $status
            )) {
                die("Error binding order items parameters: " . $stmt_order_items->error);
            }

            if (!$stmt_order_items->execute()) {
                throw new Exception("Error inserting order items: " . $stmt_order_items->error);
            }

            // Update product stock quantity
            $update_quantity_query = "UPDATE products SET product_quantity = product_quantity - ? WHERE id = ?";
            $stmt_update_quantity = $db->prepare($update_quantity_query);

            if (!$stmt_update_quantity) {
                die("Error preparing quantity update statement: " . $db->error);
            }

            if (!$stmt_update_quantity->bind_param("ii", $item['quantity'], $item['id'])) {
                die("Error binding parameters for quantity update: " . $stmt_update_quantity->error);
            }

            if (!$stmt_update_quantity->execute()) {
                throw new Exception("Error updating product quantity: " . $stmt_update_quantity->error);
            }
        }

        $db->commit();

        // Clear the cart session
        unset($_SESSION['cart']);

        // Redirect to success page
        header("Location: success.php?order_id=" . $order_id);
        exit();
    } catch (Exception $e) {
        $db->rollback();
        die("Transaction failed: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <meta charset="UTF-8">
    <title>Checkout - DMMMSU MARKETPLACE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="DMMMSU ATBI Marketplace">
    <meta name="keywords" content="admin templates, bootstrap admin templates, dashboard, responsive">
    <meta name="author" content="CodedThemes">
    <link rel="icon" href="assets/images/dmmmsu.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/user1.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="assets/css/checkout.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        .checkout-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            border-bottom: 2px solid #f8f9fa;
            padding-bottom: 15px;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .section-title {
            color: #3498db;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            height: 45px;
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }
        .address-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .address-row .form-group {
            flex: 1;
        }
        .btn-submit {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        .payment-method-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .product-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .product-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .total-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: #e74c3c;
        }
        .gcash-payment-container {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
    display: inline-flex;
    align-items: center;
    gap: 15px;
}

.gcash-qr-code {
    width: 50px;
    height: 50px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    object-fit: contain;
}

.payment-method-info {
    border: 1px solid #e0e0e0;
    background-color: #f8f9fa !important;
}

.gcash-number {
    font-size: 1.1rem;
    letter-spacing: 1px;
}
.cursor-pointer {
  cursor: pointer;
  transition: transform 0.2s;
}

.cursor-pointer:hover {
  transform: scale(1.05);
}

.modal-content {
  max-width: 300px;
  margin: 0 auto;
}
    </style>

  
</head>

<body>
<?php include 'includes/ui_header.php'; ?>
    
    <div class="checkout-container">
        <div class="checkout-header">
            <h2>Checkout</h2>
            <p class="text-muted">Complete your purchase</p>
        </div>
        
        <div class="product-summary">
            <h5 class="section-title">Order Summary</h5>
            <?php foreach ($cart_details as $item): ?>
                <div class="product-summary-item">
                    <span><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                    <span>₱<?php echo number_format($item['total_price'], 2); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="product-summary-item total-price">
                <span>Total Amount:</span>
                <span>₱<?php echo number_format($total_price, 2); ?></span>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="region_name" id="region_name">
        <input type="hidden" name="province_name" id="province_name">
        <input type="hidden" name="city_name" id="city_name">
        <input type="hidden" name="barangay_name" id="barangay_name">

        <form method="POST" enctype="multipart/form-data">
            <div class="form-section">
                <h5 class="section-title">Shipping Information</h5>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" name="full_name" 
                       value="<?php echo htmlspecialchars($fname . ' ' . $mname . ' ' . $lname); ?>" 
                       required placeholder="Full Name">
            </div>
            <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" name="contact_number" id="contact_number" 
                           value="<?php echo htmlspecialchars($contact_number); ?>" 
                           required placeholder="Contact Number">
                </div>

             <div class="address-row">
                    <div class="form-group">
                        <label for="region" class="form-label">Region</label>
                        <select id="region" name="region" class="form-select" required>
                            <option value="">Select Region</option>
                            <?php
                            $result = $db->query("SELECT * FROM refregion ORDER BY regDesc");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='".$row['regCode']."'>".$row['regDesc']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="province" class="form-label">Province</label>
                        <select id="province" name="province" class="form-select" required>
                            <option value="">Select Province</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="city" class="form-label">City/Municipality</label>
                        <select id="city" name="city" class="form-select" required>
                            <option value="">Select City/Municipality</option>
                        </select>
                    </div>
                </div>
                <div class="address-row">
                    <div class="form-group">
                        <label for="barangay" class="form-label">Barangay</label>
                        <select id="barangay" name="barangay" class="form-select" required>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="street_address" class="form-label">Street Address</label>
                        <input type="text" name="street_address" id="street_address" class="form-control" 
                               required placeholder="Street Address">
                    </div>
                    <div class="form-group">
                        <label for="postal_code" class="form-label">Postal Code</label>
                        <input type="number" name="postal_code" id="postal_code" class="form-control" 
                               required placeholder="Postal Code">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h5 class="section-title">Payment Information</h5>
                
                <div class="payment-method-info">
                    <p class="mb-3">Please pay exact amount here or scan this QR code:</p>
                    
                    <div class="d-flex align-items-center gap-3">
                        <!-- GCash Number -->
                        <div class="fs-5 fw-bold gcash-number">
                            (Daniel R.) 09668160595
                        </div>
                        
                        <!-- Clickable QR Code -->
                        <div class="ms-auto">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                                <img src="assets/images/gcash.jpg" 
                                     alt="Click to enlarge QR Code" 
                                     style="width: 60px; height: 60px;" 
                                     class="border rounded cursor-pointer">
                            </a>
                        </div>
                    </div>
                    
                    <input type="hidden" name="payment_method" value="Gcash">
                </div>
                <div class="form-group">
    <label for="reference_number" class="form-label">Reference Number</label>
    <input type="text" name="reference_number" id="reference_number" class="form-control" 
           required placeholder="Enter Reference Number" maxlength="15" 
           oninput="formatReferenceNumber(this)">
</div>

<script>
function formatReferenceNumber(input) {
    // Remove all non-alphanumeric characters
    let value = input.value.replace(/[^a-zA-Z0-9]/g, '');
    
    // Limit to 13 characters
    if (value.length > 14) {
        value = value.slice(0, 14);
    }

    // Format the value
    let formattedValue = '';
    if (value.length > 0) {
        formattedValue += value.slice(0, 4); // First 4 characters
    }
    if (value.length > 4) {
        formattedValue += ' ' + value.slice(4, 7); // Next 3 characters
    }
    if (value.length > 7) {
        formattedValue += ' ' + value.slice(7, 14); // Last 6 characters
    }

    // Set the formatted value back to the input
    input.value = formattedValue.trim();
}
</script>
                
                <div class="form-group">
                    <label for="payment_date_time" class="form-label">Date & Time of Payment</label>
                    <input type="datetime-local" name="payment_date_time" id="payment_date_time" class="form-control" 
                           required value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="proof_of_payment" class="form-label">Proof of Payment</label>
                    <input type="file" name="proof_of_payment" id="proof_of_payment" class="form-control" 
                           accept="image/*" required>
                    <small class="text-muted">Upload a screenshot of your payment transaction (JPG, PNG, JPEG only)</small>
                </div>
            </div>
            
            <button type="submit" class="btn-submit">
                <i class="fas fa-check-circle"></i> Place Order
            </button>
        </form>
    </div>
    
    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">GCash Payment QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="assets/images/dante.jpg" alt="GCash QR Code" class="img-fluid">
                    <p class="mt-3">Scan this QR code to pay via GCash</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Optional: Add click handler to close modal when clicking the image
        document.querySelector('.modal-image').addEventListener('click', function() {
            var modal = bootstrap.Modal.getInstance(document.getElementById('qrCodeModal'));
            modal.hide();
        });
    </script>

<script>
$(document).ready(function() {
    // Set region name when selected
    $('#region').change(function() {
        $('#region_name').val($(this).find('option:selected').text());
    });

    // Region changed - load provinces
    $('#region').change(function() {
        var regCode = $(this).val();
        $('#province').html('<option value="">Select Province</option>');
        $('#city').html('<option value="">Select City/Municipality</option>');
        $('#barangay').html('<option value="">Select Barangay</option>');
        
        if (regCode) {
            $.ajax({
                url: 'getProvince.php',
                type: 'POST',
                data: { regCode: regCode },
                success: function(data) {
                    $('#province').html(data);
                },
                error: function() {
                    alert('Error loading provinces. Please try again.');
                }
            });
        }
    });

    // Province changed - load cities and set name
    $('#province').change(function() {
        var parts = $(this).val().split('|');
        $('#province_name').val(parts[1] || '');
        
        var provCode = parts[0];
        $('#city').html('<option value="">Select City/Municipality</option>');
        $('#barangay').html('<option value="">Select Barangay</option>');
        
        if (provCode) {
            $.ajax({
                url: 'getMunicipality.php',
                type: 'POST',
                data: { provCode: provCode },
                success: function(data) {
                    $('#city').html(data);
                },
                error: function() {
                    alert('Error loading cities. Please try again.');
                }
            });
        }
    });

    // City changed - load barangays and set name
    $('#city').change(function() {
        var parts = $(this).val().split('|');
        $('#city_name').val(parts[1] || '');
        
        var citymunCode = parts[0];
        $('#barangay').html('<option value="">Select Barangay</option>');
        
        if (citymunCode) {
            $.ajax({
                url: 'getBarangay.php',
                type: 'POST',
                data: { citymunCode: citymunCode },
                success: function(data) {
                    $('#barangay').html(data);
                },
                error: function() {
                    alert('Error loading barangays. Please try again.');
                }
            });
        }
    });

    // Barangay changed - set name
    $('#barangay').change(function() {
        var parts = $(this).val().split('|');
        $('#barangay_name').val(parts[1] || '');
    });
});
</script>
</body>

</html>