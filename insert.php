<?php 
require('connection/conn.php');
require('model/queries.php');

/** submit **/

if (isset($_POST['submit'])) {

$user_name  = $_POST['uname'];
$email      = $_POST['email'];
$pw         = $_POST['pwd'];
$pw         = md5($pw);
$fname      = $_POST['firstname'];
$mname      = $_POST['mname'];
$lname      = $_POST['lname'];
$sql        = insert_query($user_name,$email,$pw,$fname,$mname,$lname);
$query      = mysqli_query($db,$sql); 


if ($query) {
    header('location:tbl_bootstrap.php');
        }
}

/** end of submit **/


/** truncate **/

if(isset($_POST['truncate'])) {
        $sql    = burahin_lahat();
        $query  = mysqli_query($db,$sql);
        if ($query) {
                echo "<script>alert('records deleted!');</script>";
        }
}

/** end of truncate **/


?>