<?php 

session_start();
require('connection/conn.php');
require('model/function.php'); // Include the logActivity function

// Check if the user is logged in
if (isset($_SESSION['id'])) {
    $userId = $_SESSION['id']; // Get the user ID from the session
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Unknown'; // Get the user role from the session

    // Log the logout activity
    logActivity($db, $userId, "Logged out from the system as a user with role: $role");
}

// Destroy the session
session_destroy();

// Redirect to the login page
echo "<script>";
echo "window.location.href='auth-signin.php';";
echo "</script>";

?>