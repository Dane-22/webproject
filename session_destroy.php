<?php 

// Start the session to access session variables
session_start();

// Unset all session variables (this removes all session data)
session_unset();

// Destroy the session on the server side
session_destroy();

// Optionally, delete the session cookie (if your application relies on cookies for session management)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/'); // Set cookie expiration time to one hour ago
}

// Redirect the user to the login page (or another page if needed)
header("Location:new_user.php"); // Change this to the page you want to redirect to
exit;
?>

?>