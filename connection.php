<?php
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "tailormadedb";

    $conn = new mysqli($servername, $username, $password, $database);

    if($conn->connect_error) {
        die("Connection Failed: " .$conn->connect_error);
    }
?>
