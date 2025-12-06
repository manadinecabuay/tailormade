<?php
session_start();
include('connection.php');

// Check if the request has both 'action' and 'orderID' parameters
if (isset($_POST['action']) && isset($_POST['orderID'])) {
  $orderID = intval($_POST['orderID']);
  $action = $_POST['action'];

  // Fetch full order details from the database
  $orderData = $conn->query("
    SELECT 
      o.orderID,
      o.garment_type,
      o.fabric_type,
      o.garment_price,
      o.due_date,
      o.customer_id,
      c.full_name,
      c.contact,
      c.address
    FROM orders o
    JOIN customers c ON o.customer_id = c.customerID
    WHERE o.orderID = $orderID
  ");

  // Check if the order exists before doing any action
  if ($orderData && $orderData->num_rows > 0) {
    $order = $orderData->fetch_assoc();

    // ✅ IF USER CLICKED "CONFIRM"
    if ($action === 'confirmed') {
      // Check if there's already an active confirmed order (not Complete)
      $activeOrderCheck = $conn->prepare("SELECT orderID, order_status FROM confirmed_order WHERE order_status != 'Complete' LIMIT 1");
      $activeOrderCheck->execute();
      $activeOrderResult = $activeOrderCheck->get_result();
      
      if ($activeOrderResult->num_rows > 0) {
        $activeOrder = $activeOrderResult->fetch_assoc();
        $_SESSION['error_message'] = "Cannot confirm new order. There is already an active order (Order #{$activeOrder['orderID']}) with status '{$activeOrder['order_status']}'. Please complete the current order first.";
        echo json_encode([
          'success' => false, 
          'message' => "Cannot confirm new order. There is already an active order (Order #{$activeOrder['orderID']}) being processed. Please complete it first."
        ]);
        $activeOrderCheck->close();
        $conn->close();
        exit;
      }
      $activeOrderCheck->close();
      
      // Update the order status to 'Order Processing' (next stage after confirmation)
      $updateStmt = $conn->prepare("UPDATE orders SET status = 'Order Processing' WHERE orderID = ?");
      $updateStmt->bind_param("i", $orderID);
      
      if ($updateStmt->execute()) {
        // Insert into confirmed_order table
        $insertStmt = $conn->prepare("
          INSERT INTO confirmed_order (orderID, customer, garment_type, fabric_type, due_date, order_status)
          VALUES (?, ?, ?, ?, ?, 'Order Confirmation')
        ");
        $insertStmt->bind_param("issss", 
          $order['orderID'], 
          $order['full_name'], 
          $order['garment_type'], 
          $order['fabric_type'], 
          $order['due_date']
        );
        $insertStmt->execute();
        $insertStmt->close();
        
        // Create notification for customer
        $notificationMessage = "Your order #{$orderID} for {$order['garment_type']} has been confirmed and is now being processed!";
        $notificationStmt = $conn->prepare("
          INSERT INTO customer_notifications (customer_id, order_id, notification_type, message)
          VALUES (?, ?, 'order_confirmed', ?)
        ");
        $notificationStmt->bind_param("iis", $order['customer_id'], $orderID, $notificationMessage);
        $notificationStmt->execute();
        $notificationStmt->close();
        
        $_SESSION['success_message'] = "Order #{$orderID} confirmed successfully! It's now visible in Order Status for tracking.";
        echo json_encode(['success' => true, 'message' => 'Order confirmed successfully']);
      } else {
        $_SESSION['error_message'] = "Failed to confirm order #{$orderID}. Please try again.";
        echo json_encode(['success' => false, 'message' => 'Failed to confirm order']);
      }
      $updateStmt->close();

    // ❌ IF USER CLICKED "CANCEL"
    } elseif ($action === 'cancelled') {
      // Insert into cancelled_orders table
      $insertCancelledStmt = $conn->prepare("
        INSERT INTO cancelled_orders (orderID, customer_name, garment_type, fabric_type, garment_price, due_date, cancelled_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
      ");
      $insertCancelledStmt->bind_param("isssds", 
        $order['orderID'], 
        $order['full_name'], 
        $order['garment_type'], 
        $order['fabric_type'],
        $order['garment_price'],
        $order['due_date']
      );
      
      // Update the order status to 'Cancelled'
      $cancelStmt = $conn->prepare("UPDATE orders SET status = 'Cancelled', updated_at = NOW() WHERE orderID = ?");
      $cancelStmt->bind_param("i", $orderID);
      
      if ($cancelStmt->execute() && $insertCancelledStmt->execute()) {
        $_SESSION['success_message'] = "Order #{$orderID} has been cancelled and moved to Cancelled Orders.";
        echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);
      } else {
        $_SESSION['error_message'] = "Failed to cancel order #{$orderID}. Please try again.";
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
      }
      $cancelStmt->close();
      $insertCancelledStmt->close();
    }

  } else {
    $_SESSION['error_message'] = "Order not found or already processed.";
    echo json_encode(['success' => false, 'message' => 'Order not found']);
  }

  $conn->close();
} else {
  echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
