<?php

include('connection.php');
require 'superadmin_session.php';


// Fetch owner info
$owner_sql = "SELECT first_name, last_name, profile_photo FROM owner LIMIT 1";
$owner_res = $conn->query($owner_sql);
$owner = $owner_res ? $owner_res->fetch_assoc() : null;

$owner_first = $owner['first_name'] ?? 'Owner';
$owner_last = $owner['last_name'] ?? '';
$owner_name = trim($owner_first . ' ' . $owner_last);

$owner_photo = $owner['profile_photo'] ?? '';
$default_photo = 'assets/images/default_profile.png';
$photo_path = (!empty($owner_photo) && file_exists($owner_photo)) ? $owner_photo : $default_photo;

// Fetch owner's logo from business_info
$logo_path = 'tailor.jpg'; // default logo
$logo_sql = "SELECT logo_path FROM business_info LIMIT 1";
$logo_result = $conn->query($logo_sql);
if ($logo_result && $logo_result->num_rows > 0) {
    $logo_row = $logo_result->fetch_assoc();
    if (!empty($logo_row['logo_path']) && file_exists($logo_row['logo_path'])) {
        $logo_path = $logo_row['logo_path'];
    }
}


// Handle AJAX save request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'save_all') {
        $business_name = trim($_POST['business_name'] ?? '');
        $about_us = trim($_POST['about_us'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $machine_message = trim($_POST['machine_message'] ?? '');
        $machine_count = intval($_POST['machine_count'] ?? 0);
        
        // Handle logo upload
        $logo_path = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logo_path = $upload_dir . 'logo_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
        }
        
        // Get theme colors
        $bg_color = trim($_POST['bg_color'] ?? '');
        $font_color = trim($_POST['font_color'] ?? '');
        $button_color = trim($_POST['button_color'] ?? '');
        $container_color = trim($_POST['container_color'] ?? '');
        $sidebar_font_color = trim($_POST['sidebar_font_color'] ?? '');
        $sidebar_hover = trim($_POST['sidebar_hover'] ?? '');
        $sidebar_hover_font = trim($_POST['sidebar_hover_font'] ?? '');
        
        // Update business_info
        if ($logo_path) {
            $stmt = $conn->prepare("UPDATE business_info SET business_name = ?, about_us = ?, contact = ?, email = ?, machine_availability_message = ?, machine_count = ?, logo_path = ? WHERE id = 1");
            $stmt->bind_param("sssssis", $business_name, $about_us, $contact, $email, $machine_message, $machine_count, $logo_path);
        } else {
            $stmt = $conn->prepare("UPDATE business_info SET business_name = ?, about_us = ?, contact = ?, email = ?, machine_availability_message = ?, machine_count = ? WHERE id = 1");
            $stmt->bind_param("sssssi", $business_name, $about_us, $contact, $email, $machine_message, $machine_count);
        }
        
        if ($stmt->execute()) {
            // Save or update theme settings
            $theme_check = $conn->query("SELECT id FROM theme_settings WHERE id = 1");
            if ($theme_check->num_rows > 0) {
                $theme_stmt = $conn->prepare("UPDATE theme_settings SET bg_color = ?, font_color = ?, button_color = ?, container_color = ?, sidebar_font_color = ?, sidebar_hover = ?, sidebar_hover_font = ? WHERE id = 1");
            } else {
                $theme_stmt = $conn->prepare("INSERT INTO theme_settings (bg_color, font_color, button_color, container_color, sidebar_font_color, sidebar_hover, sidebar_hover_font) VALUES (?, ?, ?, ?, ?, ?, ?)");
            }
            $theme_stmt->bind_param("sssssss", $bg_color, $font_color, $button_color, $container_color, $sidebar_font_color, $sidebar_hover, $sidebar_hover_font);
            $theme_stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully!', 'logo_path' => $logo_path]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'load_settings') {
        $business = $conn->query("SELECT * FROM business_info WHERE id = 1")->fetch_assoc();
        $theme = $conn->query("SELECT * FROM theme_settings WHERE id = 1")->fetch_assoc();
        echo json_encode(['success' => true, 'business' => $business, 'theme' => $theme]);
        exit;
    }
}

// Fetch current business info
$business_info = $conn->query("SELECT * FROM business_info WHERE id = 1")->fetch_assoc();
$theme_settings = $conn->query("SELECT * FROM theme_settings WHERE id = 1")->fetch_assoc();

// Fetch owner info
$owner_sql = "SELECT first_name, last_name, profile_photo FROM owner LIMIT 1";
$owner_res = $conn->query($owner_sql);
$owner = $owner_res ? $owner_res->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customize Business</title>
  
  <!-- Include Business Info Helper -->
  <?php include('business_info_helper.php'); ?>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    html {
      scroll-behavior: smooth;
    }

    :root {
      --theme-bg: <?= $theme_settings['bg_color'] ?? '#5c5f66' ?>;
      --theme-font: <?= $theme_settings['font_color'] ?? '#FFFFFF' ?>;
      --theme-button: <?= $theme_settings['button_color'] ?? '#0b1531' ?>;
      --theme-container: <?= $theme_settings['container_color'] ?? '#d9d9d9' ?>;
      --theme-sidebar-font: <?= $theme_settings['sidebar_font_color'] ?? '#000000' ?>;
      --theme-sidebar-hover: <?= $theme_settings['sidebar_hover'] ?? '#bfbfbf' ?>;
      --theme-sidebar-hover-font: <?= $theme_settings['sidebar_hover_font'] ?? '#000000' ?>;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      display: flex;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      min-height: 100vh;
      overflow-x: hidden;
    }

    .sidebar {
      width: 260px;
      background:rgba(255,255,255,0.98);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(10px);
    }

    .sidebar-logo {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 25px 20px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .sidebar-logo img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--theme-bg);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .sidebar a {
      color: #333;
      text-decoration: none;
      padding: 18px 30px;
      display: flex;
      align-items: center;
      gap: 15px;
      font-weight: 500;
      font-size: 15px;
      transition: all 0.3s ease;
      border-left: 4px solid transparent;
    }

    .sidebar a:hover {
      background: var(--theme-sidebar-hover);
      border-left-color: var(--theme-bg);
      color: var(--theme-sidebar-hover-font);
    }

    .sidebar a.active {
      background: var(--theme-sidebar-hover);
      border-left-color: var(--theme-bg);
      color: var(--theme-sidebar-hover-font);
      font-weight: 500;
    }

    .sidebar a i {
      font-size: 18px;
      width: 24px;
      text-align: center;
    }

    .sidebar .bottom {
      border-top: 1px solid rgba(0, 0, 0, 0.1);
      padding: 20px;
    }

    .logout-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
      color: white;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      margin-top: 10px;
    }

    .logout-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
    }

    .logout-btn i {
      margin-right: 8px;
    }

    .main {
      flex: 1;
      padding: 0;
      overflow-y: auto;
      overflow-x: hidden;
      animation: fadeIn 0.4s ease-in-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .main-content {
      padding: 25px;
      max-width: 100%;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      background: rgba(255, 255, 255, 0.95);
      padding: 20px 25px;
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .header h2 {
      font-size: 28px;
      font-weight: 700;
      color: var(--theme-bg);
      letter-spacing: 0.5px;
    }

    .header-left h1 {
      font-size: 28px;
      font-weight: 700;
      color: var(--theme-bg);
      letter-spacing: 0.5px;
      margin-bottom: 5px;
    }

    .header-left h1 i {
      margin-right: 10px;
    }

    .header-left p {
      color: #666;
      font-size: 15px;
      margin: 0;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .header-right i {
      font-size: 20px;
      color: var(--theme-bg);
      cursor: pointer;
      transition: transform 0.3s ease;
    }

    .header-right i:hover {
      transform: scale(1.2);
    }

    .header-right img {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      border: 3px solid var(--theme-bg);
      object-fit: cover;
    }

    .header-right span {
      font-weight: 600;
      color: #333;
      font-size: 15px;
    }

    .edit-toggle-btn {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 6px;
      box-shadow: 0 3px 10px rgba(220, 53, 69, 0.3);
    }

    .edit-toggle-btn i {
      color: #ffc107;
      font-size: 14px;
    }

    .edit-toggle-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
    }

    .edit-toggle-btn.editing {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
    }

    .edit-toggle-btn.editing i {
      color: #fff;
    }

    .edit-toggle-btn.editing:hover {
      box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
    }

    input[readonly],
    textarea[readonly] {
      background: #f8f9fa !important;
      cursor: not-allowed;
      opacity: 0.7;
    }

    input[readonly]:focus,
    textarea[readonly]:focus {
      border-color: #e0e0e0 !important;
      box-shadow: none !important;
    }

    .file-input-label.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    .color-picker-group.disabled input[type="color"],
    .color-picker-group.disabled input[type="text"] {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    .color-option.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      justify-content: center;
      align-items: center;
      z-index: 9999;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background: #fff;
      padding: 40px;
      border-radius: 20px;
      width: 500px;
      max-width: 90%;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.3s ease;
      position: relative;
    }

    @keyframes slideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-content.confirm {
      border-top: 5px solid var(--theme-bg);
    }

    .modal-content.success {
      border-top: 5px solid #28a745;
    }

    .modal-content.error {
      border-top: 5px solid #dc3545;
    }

    .modal-icon {
      font-size: 70px;
      margin-bottom: 20px;
    }

    .modal-icon.confirm { color: var(--theme-bg); }
    .modal-icon.success { color: #28a745; }
    .modal-icon.error { color: #dc3545; }

    .modal-content h3 {
      color: #333;
      margin-bottom: 15px;
      font-size: 26px;
      font-weight: 700;
    }

    .modal-content p {
      color: #666;
      margin-bottom: 25px;
      line-height: 1.6;
      font-size: 15px;
    }

    .modal-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
    }

    .modal-btn {
      padding: 14px 32px;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .modal-btn-primary {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
    }

    .modal-btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .modal-btn-success {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
    }

    .modal-btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
    }

    .modal-btn-secondary {
      background: #f8f9fa;
      color: #333;
      border: 2px solid #e0e0e0;
    }

    .modal-btn-secondary:hover {
      background: #e9ecef;
      transform: translateY(-2px);
    }

    .close-modal {
      position: absolute;
      top: 15px;
      right: 20px;
      font-size: 28px;
      color: #999;
      cursor: pointer;
      transition: all 0.3s ease;
      background: none;
      border: none;
      padding: 0;
      width: 35px;
      height: 35px;
      line-height: 35px;
      font-weight: bold;
    }

    .close-modal:hover {
      color: #333;
      transform: scale(1.1);
    }

    /* User-Friendly Enhancements */
    .help-text {
      background: #e3f2fd;
      border-left: 3px solid #2196f3;
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 20px;
      color: #1565c0;
      font-size: 13px;
      line-height: 1.5;
    }

    .help-text i {
      margin-right: 6px;
      font-size: 14px;
    }

    .step-indicator {
      display: inline-block;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      width: 26px;
      height: 26px;
      border-radius: 50%;
      text-align: center;
      line-height: 26px;
      font-weight: 700;
      margin-right: 8px;
      font-size: 14px;
    }

    .section-description {
      color: #666;
      margin-bottom: 15px;
      font-size: 13px;
      line-height: 1.5;
    }

    .tooltip {
      position: relative;
      display: inline-block;
      margin-left: 5px;
      cursor: help;
    }

    .tooltip i {
      color: var(--theme-bg);
      font-size: 13px;
    }

    .tooltip .tooltiptext {
      visibility: hidden;
      width: 250px;
      background-color: #333;
      color: #fff;
      text-align: left;
      border-radius: 8px;
      padding: 12px;
      position: absolute;
      z-index: 1000;
      bottom: 125%;
      left: 50%;
      margin-left: -125px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 12px;
      line-height: 1.5;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .tooltip .tooltiptext::after {
      content: "";
      position: absolute;
      top: 100%;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: #333 transparent transparent transparent;
    }

    .tooltip:hover .tooltiptext {
      visibility: visible;
      opacity: 1;
    }

    .required-badge {
      background: #ff5252;
      color: white;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      margin-left: 8px;
    }

    .optional-badge {
      background: #9e9e9e;
      color: white;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      margin-left: 8px;
    }

    .quick-tips {
      background: #fff3cd;
      border-left: 3px solid #ffc107;
      padding: 12px 16px;
      border-radius: 6px;
      margin-top: 15px;
      color: #856404;
      font-size: 12px;
    }

    .quick-tips strong {
      display: block;
      margin-bottom: 6px;
      color: #856404;
      font-size: 13px;
    }

    .quick-tips ul {
      margin-left: 18px;
      margin-top: 6px;
    }

    .quick-tips li {
      margin-bottom: 4px;
      line-height: 1.4;
    }

    .content-card {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
      transition: all 0.3s ease;
      margin-bottom: 20px;
    }

    .content-card:hover {
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.25);
    }

    .section-title {
      font-size: 17px;
      font-weight: 700;
      margin-bottom: 18px;
      color: var(--theme-bg);
      letter-spacing: 0.5px;
      text-transform: uppercase;
      border-bottom: 2px solid var(--theme-bg);
      padding-bottom: 8px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: var(--theme-bg);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group textarea {
      width: 100%;
      max-width: 100%;
      padding: 10px 14px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 13px;
      background: #fff;
      color: #333;
      transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group textarea {
      min-height: 100px;
      resize: vertical;
      font-family: 'Poppins', sans-serif;
    }

    .color-picker-group {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .color-picker-group input[type="color"] {
      width: 60px;
      height: 45px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .color-picker-group input[type="color"]:hover {
      border-color: var(--theme-bg);
      transform: scale(1.05);
    }

    .color-picker-group input[type="text"] {
      width: 120px;
      padding: 10px 12px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      background: #f8f9fa;
      color: #333;
      font-weight: 600;
      font-family: monospace;
    }

    .color-palette-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 12px;
      margin-bottom: 25px;
    }

    .color-option {
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border: 3px solid transparent;
      background: #fff;
      position: relative;
    }

    .color-option:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
    }

    .color-option.active {
      border: 3px solid var(--theme-bg);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .color-option.active::after {
      content: '✓';
      position: absolute;
      top: 6px;
      right: 6px;
      background: var(--theme-bg);
      color: white;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 14px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    }

    .color-preview {
      height: 70px;
      width: 100%;
      position: relative;
    }

    .color-name {
      text-align: center;
      font-weight: 600;
      padding: 8px 6px;
      background: #fff;
      color: #333;
      font-size: 11px;
      letter-spacing: 0.3px;
      line-height: 1.2;
    }

    .save-btn {
      width: 100%;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border: none;
      padding: 18px 40px;
      border-radius: 15px;
      font-weight: 700;
      letter-spacing: 1.5px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
      margin-top: 30px;
      font-size: 15px;
      text-transform: uppercase;
    }

    .save-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
    }

    .preview-logo {
      max-width: 200px;
      max-height: 100px;
      margin-top: 15px;
      border-radius: 10px;
      border: 3px solid var(--theme-bg);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    }

    .message {
      padding: 18px 25px;
      border-radius: 15px;
      margin-bottom: 25px;
      display: none;
      font-weight: 600;
      letter-spacing: 0.5px;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .message.success {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
    }

    .message.error {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: white;
      box-shadow: 0 5px 20px rgba(220, 53, 69, 0.3);
    }

    .file-input-wrapper {
      position: relative;
      display: inline-block;
    }

    .file-input-wrapper input[type="file"] {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }

    .file-input-label {
      display: inline-block;
      padding: 12px 24px;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 600;
      font-size: 14px;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .file-input-label:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
    }
  </style>
</head>

<body>
  <div class="sidebar">
    <div>
      <div class="sidebar-logo">
        <img src="<?= htmlspecialchars($business_info['logo_path'] ?? 'tailor.jpg') ?>" alt="Business Logo" id="sidebar-logo-img">
      </div>
      <a href="superadmin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
      <a href="superadmin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
      <a href="superadmin_payroll.php"><i class="fa-solid fa-money-bill"></i> Payroll</a>
      <a href="superadmin_order.php"><i class="fa-solid fa-box"></i> Orders</a>
      <a href="superadmin_orderStatus.php"><i class="fa-solid fa-chart-bar"></i> Order Status</a>
      <a href="#" class="active"><i class="fa-solid fa-gear"></i> Customize</a>
    </div>
    <div class="bottom">
      <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </div>

  <div class="main">
    <div class="main-content">
      <div class="header">
        <div class="header-left">
          <h1><i class="fa-solid fa-gear"></i> Customization Settings</h1>
          <p>Personalize system preferences and display options</p>
        </div>
        <div class="header-right">
          <img src="<?= htmlspecialchars($owner['profile_photo'] ?? 'https://via.placeholder.com/45') ?>" alt="Profile">
          <span><?= htmlspecialchars($owner_name) ?></span>
        </div>
      </div>

      <div id="message" class="message"></div>

      <!-- Welcome Instructions -->
      <div class="help-text">
      <i class="fa-solid fa-lightbulb"></i>
      <strong>Welcome to Business Customization!</strong> Follow these simple steps to personalize your business profile and choose your perfect color theme. Click the lock button to enable editing before making changes!
    </div>

    <form id="customizeForm" enctype="multipart/form-data">
      <!-- Business Information Section -->
      <div class="content-card">
        <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <span class="step-indicator">1</span>
            <i class="fa-solid fa-building"></i> Business Information
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">Enter your business details that will be displayed throughout the application. All fields marked with * are required.</span>
            </span>
          </div>
          <button type="button" id="editToggleBtn" class="edit-toggle-btn" onclick="toggleEditMode()">
            <i class="fa-solid fa-lock"></i>
            <span id="editBtnText">Lock Mode</span>
          </button>
        </div>
        <p class="section-description">
          Tell us about your business. This information will be visible to your customers and employees.
        </p>
        
        <div class="form-group">
          <label for="business_name">
            <i class="fa-solid fa-store"></i> Business Name
            <span class="required-badge">Required</span>
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">Your official business name that will appear on all pages and documents.</span>
            </span>
          </label>
          <input type="text" id="business_name" name="business_name" value="<?= htmlspecialchars($business_info['business_name'] ?? '') ?>" required placeholder="e.g., Tailor Made Fashion">
        </div>

        <div class="form-group">
          <label for="about_us">
            <i class="fa-solid fa-info-circle"></i> About Us
            <span class="required-badge">Required</span>
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">A brief description of your business, services, and what makes you unique.</span>
            </span>
          </label>
          <textarea id="about_us" name="about_us" required placeholder="Tell customers about your business, your expertise, and what services you offer..."><?= htmlspecialchars($business_info['about_us'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label for="contact">
            <i class="fa-solid fa-phone"></i> Contact Number
            <span class="required-badge">Required</span>
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">Your primary business phone number for customer inquiries.</span>
            </span>
          </label>
          <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($business_info['contact'] ?? '') ?>" required placeholder="e.g., +1 (555) 123-4567">
        </div>

        <div class="form-group">
          <label for="email">
            <i class="fa-solid fa-envelope"></i> Email Address
            <span class="required-badge">Required</span>
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">Your business email address for official communications.</span>
            </span>
          </label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($business_info['email'] ?? '') ?>" required placeholder="e.g., contact@yourbusiness.com">
        </div>

        <div class="form-group">
          <label for="machine_message">
            <i class="fa-solid fa-message"></i> Machine Availability Message
            <span class="optional-badge">Optional</span>
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">Display a custom message about machine or equipment availability to your team.</span>
            </span>
          </label>
          <textarea id="machine_message" name="machine_message" placeholder="e.g., All machines are operational and ready for production..."><?= htmlspecialchars($business_info['machine_availability_message'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label for="machine_count">
            <i class="fa-solid fa-gears"></i> Number of Machines Available
            <span class="optional-badge">Optional</span>
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">Enter the total number of machines available for production. This will be displayed to customers when placing orders.</span>
            </span>
          </label>
          <input type="number" id="machine_count" name="machine_count" value="<?= htmlspecialchars($business_info['machine_count'] ?? '0') ?>" min="0" placeholder="e.g., 10">
        </div>

        <div class="form-group">
          <label for="logo">
            <i class="fa-solid fa-image"></i> Business Logo
            <span class="optional-badge">Optional</span>
            <span class="tooltip">
              <i class="fa-solid fa-circle-question"></i>
              <span class="tooltiptext">Upload your business logo (PNG, JPG, or GIF). Recommended size: 200x200 pixels.</span>
            </span>
          </label>
          <div class="file-input-wrapper">
            <label class="file-input-label">
              <i class="fa-solid fa-upload"></i> Choose Logo
              <input type="file" id="logo" name="logo" accept="image/*">
            </label>
          </div>
          <div id="logo-preview">
            <?php if (!empty($business_info['logo_path'])): ?>
              <img src="<?= htmlspecialchars($business_info['logo_path']) ?>" class="preview-logo" alt="Current Logo">
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Color Palette Section -->
      <div class="content-card">
        <div class="section-title">
          <span class="step-indicator">2</span>
          <i class="fa-solid fa-palette"></i> Choose Your Color Theme
          <span class="tooltip">
            <i class="fa-solid fa-circle-question"></i>
            <span class="tooltiptext">Select a color palette that matches your brand. Each theme includes coordinated colors for background, sidebar, and buttons.</span>
          </span>
        </div>
        <p class="section-description">
          Pick a color theme that represents your brand. Each option includes perfectly matched colors for all elements of your application.
        </p>
        <div class="color-palette-grid" id="colorPaletteGrid"></div>
        
        <div class="quick-tips">
          <strong><i class="fa-solid fa-star"></i> Quick Tips:</strong>
          <ul>
            <li>Click any color to see it applied instantly</li>
            <li>The selected theme will update your entire application</li>
            <li>You can still customize individual colors below if needed</li>
          </ul>
        </div>
      </div>

      <div class="content-card">
        <div class="section-title">
          <span class="step-indicator">3</span>
          <i class="fa-solid fa-sliders"></i> Fine-Tune Colors (Advanced)
          <span class="tooltip">
            <i class="fa-solid fa-circle-question"></i>
            <span class="tooltiptext">Customize individual colors if you want more control. These settings override the selected palette above.</span>
          </span>
        </div>
        <p class="section-description">
          Want more control? Adjust individual colors to perfectly match your brand. These settings will override the palette you selected above.
        </p>
        
        <div class="form-group">
          <label><i class="fa-solid fa-fill-drip"></i> Background Color</label>
          <div class="color-picker-group">
            <input type="color" id="bg_color" name="bg_color" value="<?= $theme_settings['bg_color'] ?? '#5c5f66' ?>">
            <input type="text" id="bg_color_text" value="<?= $theme_settings['bg_color'] ?? '#5c5f66' ?>" readonly>
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-font"></i> Font Color</label>
          <div class="color-picker-group">
            <input type="color" id="font_color" name="font_color" value="<?= $theme_settings['font_color'] ?? '#FFFFFF' ?>">
            <input type="text" id="font_color_text" value="<?= $theme_settings['font_color'] ?? '#FFFFFF' ?>" readonly>
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-square"></i> Button Color</label>
          <div class="color-picker-group">
            <input type="color" id="button_color" name="button_color" value="<?= $theme_settings['button_color'] ?? '#0b1531' ?>">
            <input type="text" id="button_color_text" value="<?= $theme_settings['button_color'] ?? '#0b1531' ?>" readonly>
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-box"></i> Container Color</label>
          <div class="color-picker-group">
            <input type="color" id="container_color" name="container_color" value="<?= $theme_settings['container_color'] ?? '#d9d9d9' ?>">
            <input type="text" id="container_color_text" value="<?= $theme_settings['container_color'] ?? '#d9d9d9' ?>" readonly>
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-bars"></i> Sidebar Font Color</label>
          <div class="color-picker-group">
            <input type="color" id="sidebar_font_color" name="sidebar_font_color" value="<?= $theme_settings['sidebar_font_color'] ?? '#000000' ?>">
            <input type="text" id="sidebar_font_color_text" value="<?= $theme_settings['sidebar_font_color'] ?? '#000000' ?>" readonly>
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-hand-pointer"></i> Sidebar Hover Background</label>
          <div class="color-picker-group">
            <input type="color" id="sidebar_hover" name="sidebar_hover" value="<?= $theme_settings['sidebar_hover'] ?? '#bfbfbf' ?>">
            <input type="text" id="sidebar_hover_text" value="<?= $theme_settings['sidebar_hover'] ?? '#bfbfbf' ?>" readonly>
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-text-height"></i> Sidebar Hover Font Color</label>
          <div class="color-picker-group">
            <input type="color" id="sidebar_hover_font" name="sidebar_hover_font" value="<?= $theme_settings['sidebar_hover_font'] ?? '#000000' ?>">
            <input type="text" id="sidebar_hover_font_text" value="<?= $theme_settings['sidebar_hover_font'] ?? '#000000' ?>" readonly>
          </div>
        </div>
      </div>

      <div class="quick-tips" style="margin-top: 30px;">
        <strong><i class="fa-solid fa-circle-info"></i> Before You Save:</strong>
        <ul>
          <li>Make sure all required fields are filled in</li>
          <li>Preview your changes by looking at the sidebar and forms</li>
          <li>Your changes will apply to all pages after saving</li>
          <li>You can always come back and make more changes later</li>
        </ul>
      </div>

      <button type="submit" class="save-btn">
        <i class="fa-solid fa-floppy-disk"></i> SAVE ALL CHANGES
      </button>
    </form>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="modal-overlay">
    <div class="modal-content confirm">
      <button class="close-modal" onclick="closeModal('confirmModal')">&times;</button>
      <div class="modal-icon confirm">❓</div>
      <h3>Ready to Save?</h3>
      <p id="confirmMessage">You're about to save all your business information and theme customizations. These changes will be applied across your entire application.</p>
      <p style="font-size: 13px; color: #999; margin-top: 10px;">Don't worry - you can always come back and make more changes later!</p>
      <div class="modal-buttons">
        <button class="modal-btn modal-btn-primary" id="confirmYes"><i class="fa-solid fa-check"></i> Yes, Save Changes</button>
        <button class="modal-btn modal-btn-secondary" onclick="closeModal('confirmModal')"><i class="fa-solid fa-xmark"></i> Cancel</button>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal-overlay">
    <div class="modal-content success">
      <button class="close-modal" onclick="closeSuccessModal()">&times;</button>
      <div class="modal-icon success">✅</div>
      <h3>All Set!</h3>
      <p id="successMessage">Your changes have been saved successfully and are now live across your application!</p>
      <p style="font-size: 13px; color: #999; margin-top: 10px;">The page will refresh to show your new customizations.</p>
      <div class="modal-buttons">
        <button class="modal-btn modal-btn-success" onclick="closeSuccessModal()"><i class="fa-solid fa-check"></i> Great!</button>
      </div>
    </div>
  </div>

  <!-- Error Modal -->
  <div id="errorModal" class="modal-overlay">
    <div class="modal-content error">
      <button class="close-modal" onclick="closeModal('errorModal')">&times;</button>
      <div class="modal-icon error">❌</div>
      <h3>Oops! Something Went Wrong</h3>
      <p id="errorMessage">We couldn't save your changes. Please try again.</p>
      <p style="font-size: 13px; color: #999; margin-top: 10px;">If the problem persists, please contact support.</p>
      <div class="modal-buttons">
        <button class="modal-btn modal-btn-secondary" onclick="closeModal('errorModal')"><i class="fa-solid fa-rotate-right"></i> Try Again</button>
      </div>
    </div>
  </div>

  <script>
    // Edit Mode Toggle
    let isEditMode = false;

    function toggleEditMode() {
      isEditMode = !isEditMode;
      const btn = document.getElementById('editToggleBtn');
      const btnText = document.getElementById('editBtnText');
      const btnIcon = btn.querySelector('i');
      
      // Get all form elements
      const inputs = document.querySelectorAll('#customizeForm input[type="text"], #customizeForm input[type="email"], #customizeForm input[type="number"], #customizeForm textarea');
      const colorInputs = document.querySelectorAll('#customizeForm input[type="color"]');
      const colorPickerGroups = document.querySelectorAll('.color-picker-group');
      const colorOptions = document.querySelectorAll('.color-option');
      const fileLabel = document.querySelector('.file-input-label');
      const saveBtn = document.querySelector('.save-btn');
      
      if (isEditMode) {
        // Enable editing
        inputs.forEach(input => input.removeAttribute('readonly'));
        colorInputs.forEach(input => input.disabled = false);
        colorPickerGroups.forEach(group => group.classList.remove('disabled'));
        colorOptions.forEach(option => option.classList.remove('disabled'));
        fileLabel.classList.remove('disabled');
        saveBtn.style.display = 'block';
        btn.classList.add('editing');
        btnIcon.className = 'fa-solid fa-pen-to-square';
        btnText.textContent = 'Modify';
      } else {
        // Disable editing
        inputs.forEach(input => input.setAttribute('readonly', 'readonly'));
        colorInputs.forEach(input => input.disabled = true);
        colorPickerGroups.forEach(group => group.classList.add('disabled'));
        colorOptions.forEach(option => option.classList.add('disabled'));
        fileLabel.classList.add('disabled');
        saveBtn.style.display = 'none';
        btn.classList.remove('editing');
        btnIcon.className = 'fa-solid fa-lock';
        btnText.textContent = 'Lock Mode';
      }
    }

    // Initialize locked state on page load
    window.addEventListener('DOMContentLoaded', () => {
      // Ensure page starts in locked mode
      const inputs = document.querySelectorAll('#customizeForm input[type="text"], #customizeForm input[type="email"], #customizeForm input[type="number"], #customizeForm textarea');
      const colorInputs = document.querySelectorAll('#customizeForm input[type="color"]');
      const colorPickerGroups = document.querySelectorAll('.color-picker-group');
      const colorOptions = document.querySelectorAll('.color-option');
      const fileLabel = document.querySelector('.file-input-label');
      const saveBtn = document.querySelector('.save-btn');
      
      // Set everything to locked/readonly
      inputs.forEach(input => input.setAttribute('readonly', 'readonly'));
      colorInputs.forEach(input => input.disabled = true);
      colorPickerGroups.forEach(group => group.classList.add('disabled'));
      colorOptions.forEach(option => option.classList.add('disabled'));
      fileLabel.classList.add('disabled');
      saveBtn.style.display = 'none';
    });

    // Modal Functions
    function showModal(modalId) {
      document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    function closeSuccessModal() {
      closeModal('successModal');
      location.reload();
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
      if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
      }
    });

    // Coordinated Color Palettes (Light shades removed, new colors added)
    const colorPalettes = [
      {
        name: "Dark Slate",
        bg_color: "#455054",
        font_color: "#FFFFFF",
        button_color: "#3a4347",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#455054",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Deep Navy",
        bg_color: "#0B2B26",
        font_color: "#FFFFFF",
        button_color: "#052117",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#0B2B26",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Midnight Blue",
        bg_color: "#052659",
        font_color: "#FFFFFF",
        button_color: "#031a3d",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#052659",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Olive Green",
        bg_color: "#55624A",
        font_color: "#FFFFFF",
        button_color: "#3d4635",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#55624A",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Deep Plum",
        bg_color: "#42354C",
        font_color: "#FFFFFF",
        button_color: "#2e2436",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#42354C",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Bronze Brown",
        bg_color: "#895D2B",
        font_color: "#FFFFFF",
        button_color: "#6b481f",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#895D2B",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Dark Teal",
        bg_color: "#25344F",
        font_color: "#FFFFFF",
        button_color: "#1a2538",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#25344F",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Sage Green",
        bg_color: "#EB5F7A",
        font_color: "#FFFFFF",
        button_color: "#d14563",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#EB5F7A",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Steel Blue",
        bg_color: "#414B9E",
        font_color: "#FFFFFF",
        button_color: "#323a7d",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#414B9E",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Charcoal",
        bg_color: "#1a1a1a",
        font_color: "#FFFFFF",
        button_color: "#0d0d0d",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#1a1a1a",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Teal Breeze",
        bg_color: "#308695",
        font_color: "#FFFFFF",
        button_color: "#266d7a",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#308695",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Purple Gradient",
        bg_color: "#667eea",
        font_color: "#FFFFFF",
        button_color: "#764ba2",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#667eea",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Ocean Blue",
        bg_color: "#4A90E2",
        font_color: "#FFFFFF",
        button_color: "#2E5C8A",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#4A90E2",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Deep Purple",
        bg_color: "#4D3A6B",
        font_color: "#FFFFFF",
        button_color: "#3a2b50",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#4D3A6B",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Slate Blue",
        bg_color: "#46596A",
        font_color: "#FFFFFF",
        button_color: "#354451",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#46596A",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Lavender Blue",
        bg_color: "#9393DE",
        font_color: "#FFFFFF",
        button_color: "#7575c4",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#9393DE",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Violet Purple",
        bg_color: "#945CD8",
        font_color: "#FFFFFF",
        button_color: "#7a47b8",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#945CD8",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Forest Green",
        bg_color: "#2D531A",
        font_color: "#FFFFFF",
        button_color: "#1f3a12",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#2D531A",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Coral Orange",
        bg_color: "#DF804D",
        font_color: "#FFFFFF",
        button_color: "#c66a38",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#DF804D",
        sidebar_hover_font: "#FFFFFF"
      },
      {
        name: "Plum Purple",
        bg_color: "#6E3B83",
        font_color: "#FFFFFF",
        button_color: "#562d68",
        container_color: "#ffffff",
        sidebar_font_color: "#333333",
        sidebar_hover: "#6E3B83",
        sidebar_hover_font: "#FFFFFF"
      }
    ];

    const colorPaletteGrid = document.getElementById("colorPaletteGrid");

    // Generate color palette options
    colorPalettes.forEach((palette, index) => {
      const option = document.createElement("div");
      option.classList.add("color-option");
      option.innerHTML = `
        <div class="color-preview" style="background: linear-gradient(135deg, ${palette.bg_color} 0%, ${palette.button_color} 100%);"></div>
        <div class="color-name">${palette.name}</div>
      `;
      option.addEventListener("click", () => {
        if (!isEditMode) {
          alert('Please enable editing mode first by clicking the lock button in the Business Information section.');
          return;
        }
        document.querySelectorAll(".color-option").forEach(c => c.classList.remove("active"));
        option.classList.add("active");
        applyTheme(palette);
      });
      colorPaletteGrid.appendChild(option);
    });

    // Apply theme to page
    function applyTheme(theme) {
      document.documentElement.style.setProperty("--theme-bg", theme.bg_color);
      document.documentElement.style.setProperty("--theme-font", theme.font_color);
      document.documentElement.style.setProperty("--theme-button", theme.button_color);
      document.documentElement.style.setProperty("--theme-container", theme.container_color);
      document.documentElement.style.setProperty("--theme-sidebar-font", theme.sidebar_font_color);
      document.documentElement.style.setProperty("--theme-sidebar-hover", theme.sidebar_hover);
      document.documentElement.style.setProperty("--theme-sidebar-hover-font", theme.sidebar_hover_font);

      // Update color pickers
      document.getElementById('bg_color').value = theme.bg_color;
      document.getElementById('bg_color_text').value = theme.bg_color;
      document.getElementById('font_color').value = theme.font_color;
      document.getElementById('font_color_text').value = theme.font_color;
      document.getElementById('button_color').value = theme.button_color;
      document.getElementById('button_color_text').value = theme.button_color;
      document.getElementById('container_color').value = theme.container_color;
      document.getElementById('container_color_text').value = theme.container_color;
      document.getElementById('sidebar_font_color').value = theme.sidebar_font_color;
      document.getElementById('sidebar_font_color_text').value = theme.sidebar_font_color;
      document.getElementById('sidebar_hover').value = theme.sidebar_hover;
      document.getElementById('sidebar_hover_text').value = theme.sidebar_hover;
      document.getElementById('sidebar_hover_font').value = theme.sidebar_hover_font;
      document.getElementById('sidebar_hover_font_text').value = theme.sidebar_hover_font;
    }

    // Sync color pickers with text inputs
    ['bg_color', 'font_color', 'button_color', 'container_color', 'sidebar_font_color', 'sidebar_hover', 'sidebar_hover_font'].forEach(id => {
      const colorInput = document.getElementById(id);
      const textInput = document.getElementById(id + '_text');
      
      colorInput.addEventListener('input', (e) => {
        if (!isEditMode) {
          e.preventDefault();
          return;
        }
        textInput.value = e.target.value;
        document.documentElement.style.setProperty('--theme-' + id.replace('_', '-'), e.target.value);
      });
    });

    // Preview logo changes (for logo-preview div only)
    document.getElementById('logo').addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
          const preview = document.getElementById('logo-preview');
          preview.innerHTML = `<img src="${event.target.result}" class="preview-logo" alt="Logo Preview">`;
        };
        reader.readAsDataURL(file);
      }
    });

    // Handle form submission
    let pendingFormData = null;

    document.getElementById('customizeForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      // Show confirmation modal
      showModal('confirmModal');
      
      // Store form data for later submission
      pendingFormData = new FormData(e.target);
      pendingFormData.append('action', 'save_all');
      
      // Add color values
      pendingFormData.append('bg_color', document.getElementById('bg_color').value);
      pendingFormData.append('font_color', document.getElementById('font_color').value);
      pendingFormData.append('button_color', document.getElementById('button_color').value);
      pendingFormData.append('container_color', document.getElementById('container_color').value);
      pendingFormData.append('sidebar_font_color', document.getElementById('sidebar_font_color').value);
      pendingFormData.append('sidebar_hover', document.getElementById('sidebar_hover').value);
      pendingFormData.append('sidebar_hover_font', document.getElementById('sidebar_hover_font').value);
    });

    // Confirm button handler
    document.getElementById('confirmYes').addEventListener('click', async () => {
      closeModal('confirmModal');
      
      if (!pendingFormData) return;

      try {
        const response = await fetch('customize.php', {
          method: 'POST',
          body: pendingFormData
        });

        const result = await response.json();
        
        if (result.success) {
          // Auto-lock after successful save
          if (isEditMode) {
            toggleEditMode();
          }
          
          document.getElementById('successMessage').textContent = result.message;
          showModal('successModal');
        } else {
          document.getElementById('errorMessage').textContent = result.message;
          showModal('errorModal');
        }
      } catch (error) {
        document.getElementById('errorMessage').textContent = 'An error occurred while saving your changes.';
        showModal('errorModal');
      }
      
      pendingFormData = null;
    });
  </script>
</body>
</html>
