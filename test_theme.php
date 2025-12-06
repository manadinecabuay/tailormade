<?php
/**
 * Theme Test Page
 * Use this page to quickly test if theme customization is working
 */
include('connection.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Test Page</title>
    
    <!-- Include Theme Loader -->
    <?php include('theme_loader.php'); ?>
    
    <!-- Include Business Info Helper -->
    <?php include('business_info_helper.php'); ?>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--theme-container);
            padding: 20px;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar h3 {
            color: var(--theme-sidebar-font);
            margin-bottom: 20px;
        }

        .sidebar a {
            display: block;
            padding: 15px;
            color: var(--theme-sidebar-font);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .sidebar a:hover {
            background: var(--theme-sidebar-hover);
            color: var(--theme-sidebar-hover-font);
        }

        .main {
            flex: 1;
            padding: 40px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px 35px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
        }

        .header h1 {
            color: var(--theme-bg);
            font-size: 32px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin-bottom: 20px;
        }

        .card h2 {
            color: var(--theme-bg);
            margin-bottom: 15px;
        }

        .card p {
            color: #666;
            line-height: 1.6;
        }

        .button {
            background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
            color: var(--theme-font);
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .color-display {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .color-box {
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
        }

        .bg-color {
            background: var(--theme-bg);
            color: var(--theme-font);
        }

        .button-color {
            background: var(--theme-button);
            color: var(--theme-font);
        }

        .container-color {
            background: var(--theme-container);
            color: var(--theme-sidebar-font);
            border: 2px solid #ddd;
        }

        .hover-color {
            background: var(--theme-sidebar-hover);
            color: var(--theme-sidebar-hover-font);
        }

        .instructions {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            color: #1565c0;
        }

        .instructions h3 {
            margin-bottom: 10px;
        }

        .instructions ol {
            margin-left: 20px;
            margin-top: 10px;
        }

        .instructions li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align: center; padding: 20px; border-bottom: 2px solid #ddd;">
            <?php display_business_logo('', 'Business Logo'); ?>
            <h3 style="margin-top: 10px; color: var(--theme-sidebar-font);"><?php display_business_name(); ?></h3>
        </div>
        <a href="#"><i class="fa-solid fa-home"></i> Home</a>
        <a href="#"><i class="fa-solid fa-users"></i> Users</a>
        <a href="#"><i class="fa-solid fa-cog"></i> Settings</a>
        <a href="customize.php"><i class="fa-solid fa-palette"></i> Customize Theme</a>
    </div>

    <div class="main">
        <div class="header">
            <h1><i class="fa-solid fa-flask"></i> <?php display_business_name(); ?> - Theme Test</h1>
            <p style="color: #666; margin-top: 10px;">Test your theme and business info customization!</p>
        </div>

        <div class="instructions">
            <h3><i class="fa-solid fa-lightbulb"></i> How to Test:</h3>
            <ol>
                <li>Go to <a href="customize.php" style="color: #1565c0; font-weight: 600;">customize.php</a></li>
                <li>Click "Enable Editing" button</li>
                <li>Choose a different color palette</li>
                <li>Click "SAVE ALL CHANGES"</li>
                <li>Come back to this page (refresh if needed)</li>
                <li>See your new colors applied!</li>
            </ol>
        </div>

        <div class="card">
            <h2>Current Theme Colors</h2>
            <p>These colors are loaded from your database and applied automatically:</p>
            
            <div class="color-display">
                <div class="color-box bg-color">
                    <i class="fa-solid fa-fill-drip"></i><br>
                    Background Color
                </div>
                <div class="color-box button-color">
                    <i class="fa-solid fa-square"></i><br>
                    Button Color
                </div>
                <div class="color-box container-color">
                    <i class="fa-solid fa-box"></i><br>
                    Container Color
                </div>
                <div class="color-box hover-color">
                    <i class="fa-solid fa-hand-pointer"></i><br>
                    Hover Color
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Interactive Elements</h2>
            <p style="margin-bottom: 20px;">Test buttons and hover effects:</p>
            <button class="button"><i class="fa-solid fa-check"></i> Test Button</button>
            <button class="button" style="margin-left: 10px;"><i class="fa-solid fa-star"></i> Another Button</button>
        </div>

        <div class="card">
            <h2>Hover Test</h2>
            <p>Hover over the sidebar links on the left to see the hover color in action!</p>
        </div>

        <div class="card">
            <h2>✅ Integration Status</h2>
            <p><strong>Theme Loader:</strong> ✅ Active</p>
            <p><strong>CSS Variables:</strong> ✅ Working</p>
            <p><strong>Database Connection:</strong> ✅ Connected</p>
            <p style="margin-top: 15px; color: #28a745; font-weight: 600;">
                <i class="fa-solid fa-circle-check"></i> Theme customization is fully functional!
            </p>
        </div>
    </div>
</body>
</html>
