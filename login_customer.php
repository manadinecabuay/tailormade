<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    /* =====================================================
       1. SUPERADMIN LOGIN
    ===================================================== */
    $stmt_owner = $conn->prepare("SELECT id, username, password FROM owner WHERE username = ?");
    $stmt_owner->bind_param("s", $username);
    $stmt_owner->execute();
    $result_owner = $stmt_owner->get_result();

    if ($result_owner->num_rows > 0) {
        $owner = $result_owner->fetch_assoc();
        if (password_verify($password, $owner['password'])) {
            $_SESSION['owner_id'] = $owner['id'];
            $_SESSION['user_role'] = 'superadmin';
            header("Location: superadmin_dashboard.php");
            exit();
        }
        header("Location: customer_login.php?status=error&msg=" . urlencode("Invalid username or password!") . "&user=" . urlencode($username));
        exit();
    }


    /* =====================================================
       2. ADMIN LOGIN
    ===================================================== */
    $stmt_admin = $conn->prepare("SELECT id, username, password FROM supervisors WHERE username = ?");
    $stmt_admin->bind_param("s", $username);
    $stmt_admin->execute();
    $result_admin = $stmt_admin->get_result();

    if ($result_admin->num_rows > 0) {
        $admin = $result_admin->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['supervisor_id'] = $admin['id'];
            $_SESSION['user_role'] = 'admin';
            header("Location: admin_dashboard.php");
            exit();
        }
        header("Location: customer_login.php?status=error&msg=" . urlencode("Invalid username or password!") . "&user=" . urlencode($username));
        exit();
    }


    /* =====================================================
       3. CUSTOMER LOGIN
    ===================================================== */

  // Check if user is a customer
  $stmt = $conn->prepare("SELECT customerID, username, password FROM customers WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['password'])) {
      $customerID = $user['customerID'];
      
      // ✅ Check if there's any existing "Order Processing" in confirmed_order table
      // Only the customer who owns the order can login
      $processingCheck = $conn->prepare("SELECT orderID FROM confirmed_order WHERE order_status = 'Order Processing' LIMIT 1");
      $processingCheck->execute();
      $processingResult = $processingCheck->get_result();
      
      if ($processingResult->num_rows > 0) {
        $existingOrder = $processingResult->fetch_assoc();
        $processingCheck->close();
        
        // If the existing order belongs to a different customer, block login
        if ($existingOrder['customerID'] != $customerID) {
          header("Location: customer_login.php?status=error&msg=" . urlencode("Cannot login at this time, there is currently an active order being processed.") . "&user=" . urlencode($username));
          exit();
        }
      }
      $processingCheck->close();
      
      // Set session variables
      $_SESSION['customer_id'] = $user['customerID'];
      $_SESSION['customerID'] = $user['customerID']; // For compatibility
      $_SESSION['customer_username'] = $user['username'];
      $_SESSION['user_role'] = 'customer';
      
      // Redirect with success message
      header("Location: customer_login.php?status=success&msg=" . urlencode("Login successful! Redirecting..."));
      exit();
    } else {
      // Invalid password
      header("Location: customer_login.php?status=error&msg=" . urlencode("Invalid username or password!") . "&user=" . urlencode($username));
      exit();
    }
  } else {
    // User not found in any table
    header("Location: customer_login.php?status=error&msg=" . urlencode("Account not found. Please check your credentials or register."));
    exit();
  }

  $stmt->close();
  $stmt_owner->close();
  $stmt_admin->close();
  $conn->close();
}
?>
