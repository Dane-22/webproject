<?php
session_start();

// Include your database connection
include 'connection/conn.php'; // Make sure to update the path as needed

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);
    
    // Insert the inquiry into the database
    $stmt = $conn->prepare("INSERT INTO contact_inquiries (name, email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $message);
    
    if ($stmt->execute()) {
        $_SESSION['contact_success'] = "Thank you for your message, $name! We will get back to you soon.";
    } else {
        $_SESSION['contact_success'] = "There was an error submitting your message. Please try again.";
    }
    
    $stmt->close();
    header("Location: contact.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <link rel="stylesheet" href="path/to/your/styles.css"> <!-- Link to your CSS file -->
</head>
<body>
    <div class="container">
        <h1>Contact Us</h1>

        <?php
        // Display success message if set
        if (isset($_SESSION['contact_success'])) {
            echo "<p class='success-message'>" . $_SESSION['contact_success'] . "</p>";
            unset($_SESSION['contact_success']); // Clear the message after displaying
        }
        ?>

        <form action="contact.php" method="POST">
            <div>
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="5" required></textarea>
            </div>
            <div>
                <button type="submit">Send Message</button>
            </div>
        </form>

        <h2>Contact Information</h2>
        <p>If you prefer to reach us directly, you can contact us at:</p>
        <p>Email: <a href="mailto:info@example.com">info@example.com</a></p>
        <p>Phone: +63 123 456 7890</p>
        <p>Address: 123 Main St, City, Country</p>

        <p><a href="user.php">Return to Home</a></p>
    </div>
</body>
</html>
