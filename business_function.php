<?php

function getBusinessInfo($conn) {

    $sql = "SELECT * FROM business_info LIMIT 1";
    $res = $conn->query($sql);

    // If no data found, return default safe values
    if (!$res || $res->num_rows == 0) {
        return [
            'business_name' => "My Business",
            'logo_path' => "uploads/default-logo.png",
            'about_us' => "No information available.",
            'contact' => "N/A",
            'email' => "N/A",
            'machine_availability_message' => "",
        ];
    }

    $row = $res->fetch_assoc();

    // Return safe values even if DB fields are NULL
    return [
        'business_name' => $row['business_name'] ?? "My Business",
        'logo_path' => $row['logo_path'] ?? "uploads/default-logo.png",
        'about_us' => $row['about_us'] ?? "No information available.",
        'contact' => $row['contact'] ?? "N/A",
        'email' => $row['email'] ?? "N/A",
        'machine_availability_message' => $row['machine_availability_message'] ?? "",
    ];
}
?>
