<?php 
session_start();
include 'connection.php'; // ensure you have a working DB connection

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Please fill in all fields."]);
        exit;
    }

    // Try to find user in all tables
    $role = null;
    $row = null;
    
    // Check owner table
    $stmt = $conn->prepare("SELECT *, 'owner' as user_role FROM owner WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $role = 'owner';
    }
    $stmt->close();
    
    // Check supervisor table if not found
    if (!$role) {
        $stmt = $conn->prepare("SELECT *, 'supervisor' as user_role FROM supervisors WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $role = 'supervisor';
        }
        $stmt->close();
    }
    
    // Check customer table if not found
    if (!$role) {
        $stmt = $conn->prepare("SELECT *, 'customer' as user_role FROM customers WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $role = 'customer';
        }
        $stmt->close();
    }

    if ($row && password_verify($password, $row['password'])) {

        // ✅ Common session values
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $role;
        
        if ($role === 'customer') {
            $_SESSION['id'] = $row['customer_id'];
        } else {
            $_SESSION['id'] = $row['id'];
        }

            if ($role === 'supervisor') {
                $_SESSION['supervisor_id'] = $row['id'];
                $_SESSION['last_activity'] = time();

                // ✅ Generate a session token for single active session
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // ✅ Save token to database
                $update = $conn->prepare("UPDATE supervisors SET session_token = ? WHERE id = ?");
                $update->bind_param("si", $session_token, $row['id']);
                $update->execute();
                $update->close();

                $redirect = "admin_dashboard.php";
            } elseif ($role === 'owner') {
                $_SESSION['owner_id'] = $row['id'];
                $_SESSION['last_activity'] = time();

                // ✅ Generate a unique session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // ✅ Save the token in the database
                $update = $conn->prepare("UPDATE owner SET session_token = ? WHERE id = ?");
                $update->bind_param("si", $session_token, $row['id']);
                $update->execute();
                $update->close();

                $redirect = "superadmin_dashboard.php";
            } else { // ✅ Customer login
                $customerID = $row['customerID'];
                
                // ✅ Check if there's any existing "Order Processing" in confirmed_order table
                // Only the customer who owns the order can login
                $processingCheck = $conn->prepare("SELECT customerID FROM confirmed_order WHERE order_status = 'Order Processing' LIMIT 1");
                $processingCheck->execute();
                $processingResult = $processingCheck->get_result();
                
                if ($processingResult->num_rows > 0) {
                    $existingOrder = $processingResult->fetch_assoc();
                    $processingCheck->close();
                    
                    // If the existing order belongs to a different customer, block login
                    if ($existingOrder['customerID'] != $customerID) {
                        echo json_encode([
                            "status" => "error", 
                            "message" => "Cannot login at this time, there is currently an active order being processed."
                        ]);
                        $conn->close();
                        exit;
                    }
                }
                $processingCheck->close();
                
                $_SESSION['customer_id'] = $row['customerID'];
                $_SESSION['customerID'] = $row['customerID'];
                $_SESSION['last_activity'] = time();

                

                // ✅ Generate a unique session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['session_token'] = $session_token;

                // ✅ Save the token in the database
                $update = $conn->prepare("UPDATE customers SET session_token = ? WHERE customerID = ?");
                $update->bind_param("si", $session_token, $row['customerID']);
                $update->execute();
                $update->close();

                $redirect = "user_home.php";
            }

            echo json_encode(["status" => "success", "redirect" => $redirect]);

    } elseif ($row) {
        echo json_encode(["status" => "error", "message" => "Incorrect password."]);
    } else {
        echo json_encode(["status" => "error", "message" => "No account found with that username."]);
    }

    $conn->close();
}
?>