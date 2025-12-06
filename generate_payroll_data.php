<?php
/**
 * Generate Payroll Data Script
 * 
 * This script aggregates employee data from:
 * - employee_daily_records (edr) - for overall_input
 * - quality_check_logs (qcl) - for quality_check, goods, and defects
 * - employees table - for employee details
 * 
 * And inserts/updates the payroll table with the aggregated data
 */

include 'connection.php';

// Set the date range for payroll calculation
// You can modify these dates as needed
$start_date = $_POST['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_POST['end_date'] ?? date('Y-m-d'); // Today
$price_per_piece = $_POST['price_per_piece'] ?? 0.00; // Price from admin input

// SQL Query to aggregate data from all sources
$sql = "
INSERT INTO payroll (
    employee_id,
    first_name,
    last_name,
    overall_input,
    overall_output,
    overall_defects,
    price_per_piece,
    total_salary,
    payroll_date,
    status
)
SELECT 
    e.id AS employee_id,
    e.first_name,
    e.last_name,
    
    -- Overall input from employee_daily_records
    COALESCE(SUM(edr.input_value), 0) AS overall_input,
    
    -- Overall output (goods) from quality_check_logs
    COALESCE(SUM(qcl.goods), 0) AS overall_output,
    
    -- Overall defects from quality_check_logs
    COALESCE(SUM(qcl.defects), 0) AS overall_defects,
    
    -- Price per piece (passed as parameter)
    ? AS price_per_piece,
    
    -- Total salary = overall_output * price_per_piece
    COALESCE(SUM(qcl.goods), 0) * ? AS total_salary,
    
    -- Payroll date (today)
    CURDATE() AS payroll_date,
    
    -- Status
    'pending' AS status
    
FROM employees e

-- Left join with employee_daily_records to get input values
LEFT JOIN employee_daily_records edr 
    ON e.id = edr.employee_id 
    AND edr.work_date BETWEEN ? AND ?

-- Left join with quality_check_logs to get quality check data
LEFT JOIN quality_check_logs qcl 
    ON e.id = qcl.employee_id 
    AND qcl.check_date BETWEEN ? AND ?

-- Group by employee to aggregate their data
GROUP BY e.id, e.first_name, e.last_name

-- Only include employees who have records in the date range
HAVING overall_input > 0 OR overall_output > 0

-- Optional: Update if record already exists for this employee and date
ON DUPLICATE KEY UPDATE
    overall_input = VALUES(overall_input),
    overall_output = VALUES(overall_output),
    overall_defects = VALUES(overall_defects),
    price_per_piece = VALUES(price_per_piece),
    total_salary = VALUES(total_salary),
    status = VALUES(status)
";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ddssss", 
    $price_per_piece,  // For price_per_piece
    $price_per_piece,  // For total_salary calculation
    $start_date,       // For edr.work_date start
    $end_date,         // For edr.work_date end
    $start_date,       // For qcl.check_date start
    $end_date          // For qcl.check_date end
);

if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "Payroll data generated successfully for $affected_rows employees",
        'affected_rows' => $affected_rows
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "Error generating payroll data: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>
