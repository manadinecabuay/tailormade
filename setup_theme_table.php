<?php
// Run this file once to create the theme_settings table
include('connection.php');

$sql = "
CREATE TABLE IF NOT EXISTS theme_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bg_color VARCHAR(7) DEFAULT '#5c5f66',
    font_color VARCHAR(7) DEFAULT '#FFFFFF',
    button_color VARCHAR(7) DEFAULT '#0b1531',
    container_color VARCHAR(7) DEFAULT '#d9d9d9',
    sidebar_font_color VARCHAR(7) DEFAULT '#000000',
    sidebar_hover VARCHAR(7) DEFAULT '#bfbfbf',
    sidebar_hover_font VARCHAR(7) DEFAULT '#000000',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table theme_settings created successfully<br>";
    
    // Insert default theme
    $check = $conn->query("SELECT id FROM theme_settings WHERE id = 1");
    if ($check->num_rows == 0) {
        $insert = "INSERT INTO theme_settings (id, bg_color, font_color, button_color, container_color, sidebar_font_color, sidebar_hover, sidebar_hover_font) 
                   VALUES (1, '#5c5f66', '#FFFFFF', '#0b1531', '#d9d9d9', '#000000', '#bfbfbf', '#000000')";
        if ($conn->query($insert) === TRUE) {
            echo "Default theme inserted successfully<br>";
        }
    } else {
        echo "Default theme already exists<br>";
    }
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
echo "<br>Setup complete! You can now use the customize.php page.";
?>
