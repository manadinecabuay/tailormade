<?php
session_start();
include('connection.php'); // Database connection

// ✅ Step 1: Check if customer is logged in
if (!isset($_SESSION['customerID'])) {
    echo "<script>
            alert('You must be logged in to place an order.');
            window.location.href = 'login.php';
          </script>";
    exit();
}

$customer_id = $_SESSION['customerID'];

// ✅ Fetch customer info for autofill
$customer = ['full_name' => '', 'address' => '', 'contact' => '']; // initialize defaults

$stmt = $conn->prepare("SELECT full_name, address, contact FROM customers WHERE customerID = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $customer = $result->fetch_assoc();
}
// Handle if no customer record found
if (!$customer) {
    echo "<script>
            alert('Customer record not found.');
            window.location.href = 'logout.php';
          </script>";
    exit();
}

// ✅ Step 3: Process Order Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ✅ Sanitize and validate input data
    $fullname = trim($_POST['fullname'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $fabric_type = trim($_POST['fabric_type'] ?? '');
    $garment_type = trim($_POST['garment_type'] ?? '');
    $total_garments = intval($_POST['total_garments'] ?? 0);
    $due_date = $_POST['due_date'] ?? '';
    $garment_price = floatval($_POST['garment_price'] ?? 0);
    $contact = trim($_POST['contact'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // ✅ Basic validation
    if (empty($fullname) || empty($address) || empty($fabric_type) || empty($garment_type) || 
        $total_garments <= 0 || empty($due_date) || $garment_price <= 0 || empty($contact)) {
        echo "<script>
                alert('Error: Please fill in all required fields with valid values.');
                window.history.back();
              </script>";
        exit();
    }

    // ✅ SERVER-SIDE ORDER VALIDATION SYSTEM
    // Calculate days difference between today and due date
    $today = new DateTime();
    $today->setTime(0, 0, 0); // Set to start of day for accurate comparison
    $due_date_obj = new DateTime($due_date);
    $due_date_obj->setTime(0, 0, 0); // Set to start of day for accurate comparison
    
    // Check if due date is in the future
    if ($due_date_obj <= $today) {
        echo "<script>
                alert('Error: Due date must be in the future. Please select a valid date.');
                window.history.back();
              </script>";
        exit();
    }
    
    // Calculate days difference (positive number for future dates)
    $interval = $today->diff($due_date_obj);
    $days_difference = $interval->days;

    // ✅ VALIDATION RULE 1: Orders with MORE than 1500 garments require at least 30 days
    if ($total_garments > 1500 && $days_difference < 30) {
        echo "<script>
                alert('Error: Orders with more than 1,500 garments require at least 30 days lead time. Please select a later due date.');
                window.history.back();
              </script>";
        exit();
    }

    // ✅ VALIDATION RULE 2: Orders with 500-1500 garments: 15-30 days only
    if ($total_garments >= 500 && $total_garments <= 1500) {
        if ($days_difference < 15) {
            echo "<script>
                    alert('Error: Orders with 500-1500 garments require at least 15 days lead time.');
                    window.history.back();
                  </script>";
            exit();
        }
        if ($days_difference > 30) {
            echo "<script>
                    alert('Error: Orders with 500-1500 garments cannot exceed 30 days lead time.');
                    window.history.back();
                  </script>";
            exit();
        }
    }

    // ✅ Calculate total price
    $total_price = $total_garments * $garment_price;

    // ✅ Try to insert with total_price first, fallback to without it if column doesn't exist
    $stmt = $conn->prepare("INSERT INTO orders 
        (customer_id, fullname, address, fabric_type, garment_type, total_garments, due_date, garment_price, contact, message, total_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        // If total_price column doesn't exist, try without it
        $stmt = $conn->prepare("INSERT INTO orders 
            (customer_id, fullname, address, fabric_type, garment_type, total_garments, due_date, garment_price, contact, message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "isssissdss",
            $customer_id,
            $fullname,
            $address,
            $fabric_type,
            $garment_type,
            $total_garments,
            $due_date,
            $garment_price,
            $contact,
            $message
        );
    } else {
        // Use the version with total_price
        $stmt->bind_param(
            "isssissdssd",
            $customer_id,
            $fullname,
            $address,
            $fabric_type,
            $garment_type,
            $total_garments,
            $due_date,
            $garment_price,
            $contact,
            $message,
            $total_price
        );
    }

    // ✅ Execute and check result
    if ($stmt->execute()) {
        $order_id = $conn->insert_id;
        
        // ✅ Create notification for owner about new order
        $notif_message = "New order #$order_id from $fullname for $total_garments $garment_type";
        $notif_link = "superadmin_order.php?order_id=$order_id";
        
        $notif_stmt = $conn->prepare("INSERT INTO owner_notifications (notification_type, order_id, message, link_url) VALUES ('new_order', ?, ?, ?)");
        $notif_stmt->bind_param("iss", $order_id, $notif_message, $notif_link);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        echo "<script>
                alert('Order placed successfully!');
                window.location.href = 'orders.php';
              </script>";
    } else {
        echo "<script>
                alert('Error placing order: " . addslashes($stmt->error) . "');
                window.history.back();
              </script>";
    }

    $stmt->close();
    $conn->close();
}
?>
