<?php
include 'connection.php';

header('Content-Type: application/json');

if(isset($_GET['username'])){
    $username = mysqli_real_escape_string($conn, $_GET['username']);
    $res = mysqli_query($conn, "SELECT id FROM owners WHERE username='$username'");
    if(mysqli_num_rows($res) > 0){
        echo json_encode(['available'=>false]);
    } else {
        echo json_encode(['available'=>true]);
    }
} else {
    echo json_encode(['available'=>false]);
}
?>
