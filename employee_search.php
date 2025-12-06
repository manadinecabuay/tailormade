
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors in JSON response
header('Content-Type: application/json');

try {
    include('connection.php');

    // Get search query
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';

    if (empty($query)) {
        echo json_encode([]);
        exit;
    }

    // Search for employees by name or ID
    $search_term = "%{$query}%";
    
    // Query using actual database structure with new name fields
    // Restrict to only active employees
    $stmt = $conn->prepare("
        SELECT 
            id, 
            first_name,
            last_name,
            status, 
            contact,
            work_date,
            input
        FROM employees
        WHERE status = 'active'
          AND (first_name LIKE ? 
           OR last_name LIKE ? 
           OR CONCAT(first_name, ' ', last_name) LIKE ?
           OR CAST(id AS CHAR) LIKE ?)
        ORDER BY first_name ASC, last_name ASC
        LIMIT 10
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        // Format work date
        $work_date = $row['work_date'] ? date('M d, Y', strtotime($row['work_date'])) : 'N/A';
        
        // Construct full name from parts
        $full_name = trim($row['first_name'] . ' ' . $row['last_name']);
        
        $employees[] = [
            'id' => $row['id'],
            'full_name' => $full_name,
            'status' => ucfirst($row['status']),
            'work_date' => $work_date
        ];
    }

    echo json_encode($employees);

    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?>