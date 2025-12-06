<?php
include 'connection.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Please fill in all fields."]);
        exit;
    }

    $user = null;
    $role = null;
    $userId = null;

    // 1️⃣ Check Owner table
    $stmt = $conn->prepare("SELECT id, password FROM owner WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $user = $row;
            $role = 'owner';
            $userId = $row['id'];
        }
    }
    $stmt->close();

    // 2️⃣ Check Supervisors table (if not found in owner)
    if (!$user) {
        $stmt = $conn->prepare("SELECT id, password, status FROM supervisors WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // Check if supervisor is active
            if ($row['status'] !== 'active') {
                echo json_encode(["status" => "error", "message" => "Your account is inactive. Please contact the administrator."]);
                exit;
            }
            
            if (password_verify($password, $row['password'])) {
                $user = $row;
                $role = 'supervisor';
                $userId = $row['id'];
            }
        }
        $stmt->close();
    }

    // 3️⃣ Check Customers table (if not found in owner or supervisor)
    if (!$user) {
        $stmt = $conn->prepare("SELECT customerID, password FROM customers WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $user = $row;
                $role = 'customer';
                $userId = $row['customerID'];
            }
        }
        $stmt->close();
    }

    // 4️⃣ Process login based on detected role
    if ($user && $role) {
        switch($role) {
            case 'owner':
                // Use SUPERADMIN_SESSION for owner
                session_name('SUPERADMIN_SESSION');
                session_start();
                
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'owner';
                $_SESSION['id'] = $userId;
                $_SESSION['owner_id'] = $userId;

                // Generate session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // Save token to database
                $update = $conn->prepare("UPDATE owner SET session_token = ? WHERE id = ?");
                $update->bind_param("si", $session_token, $userId);
                $update->execute();
                $update->close();

                $redirect = "superadmin_dashboard.php";
                break;
                
            case 'supervisor':
                // Use ADMIN_SESSION for supervisor
                session_name('ADMIN_SESSION');
                session_start();
                
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'supervisor';
                $_SESSION['id'] = $userId;
                $_SESSION['supervisor_id'] = $userId;

                // Generate session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // Save token to database
                $update = $conn->prepare("UPDATE supervisors SET session_token = ? WHERE id = ?");
                $update->bind_param("si", $session_token, $userId);
                $update->execute();
                $update->close();

                $redirect = "admin_dashboard.php";
                break;
                
            case 'customer':
                // Use CUSTOMER_SESSION for customer
                session_name('CUSTOMER_SESSION');
                session_start();
                
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'customer';
                $_SESSION['customerID'] = $userId;

                $redirect = "user_home.php";
                break;
                
            default:
                echo json_encode(["status" => "error", "message" => "Invalid user type."]);
                exit;
        }

        echo json_encode(["status" => "success", "redirect" => $redirect]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
    }

    $conn->close();
}
?>
