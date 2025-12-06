<?php
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    // --- VALIDATION ---

    // Username must contain at least one number
    if (!preg_match('/[0-9]/', $username)) {
        echo "<script>alert('Username must contain at least one number.'); window.history.back();</script>";
        exit;
    }

    // Password must be 8+ chars, one uppercase letter, one number
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        echo "<script>alert('Password must be at least 8 characters long and include at least one uppercase letter and one number.'); window.history.back();</script>";
        exit;
    }

    // Check if username already exists
    $check = $conn->prepare("SELECT id FROM supervisors WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('Username already exists! Please choose another.'); window.history.back();</script>";
        exit;
    }
    $check->close();

    // --- FETCH BUSINESS ID ---
    $businessResult = $conn->query("SELECT id FROM business_info LIMIT 1");
    if ($businessResult->num_rows > 0) {
        $business = $businessResult->fetch_assoc();
        $business_id = $business['id'];
    } else {
        echo "<script>alert('No business record found in database! Please add one to business_info first.'); window.history.back();</script>";
        exit;
    }

    // --- INSERT SUPERVISOR ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO supervisors (first_name, last_name, username, password, email, phone, address, business_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $first_name, $last_name, $username, $hashed_password, $email, $phone, $address, $business_id);

    if ($stmt->execute()) {
        echo "<script>alert('Supervisor registered successfully!'); window.location= 'login_admins.html';</script>";
    } else {
        echo "<script>alert('Error registering supervisor. Please try again.'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
