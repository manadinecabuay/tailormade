<?php
session_start();
if (!isset($_SESSION['owner_id'])) {
    header("Location: owner_homepage.php");
    exit();
}
readfile("owner_homepage.html");
?>
