<?php

/**
 * Theme Loader - Loads and applies saved theme settings
 * Include this file in the <head> section of all pages to apply custom themes
 */

// Only load if connection is available
if (isset($conn)) {
    // Fetch theme settings from database
    $theme_query = "SELECT * FROM theme_settings WHERE id = 1 LIMIT 1";
    $theme_result = $conn->query($theme_query);
    
    if ($theme_result && $theme_result->num_rows > 0) {
        $theme = $theme_result->fetch_assoc();
    } else {
        // Default theme values if no settings found
        $theme = [
            'bg_color' => '#667eea',
            'font_color' => '#FFFFFF',
            'button_color' => '#764ba2',
            'container_color' => '#ffffff',
            'sidebar_font_color' => '#333333',
            'sidebar_hover' => '#667eea',
            'sidebar_hover_font' => '#FFFFFF'
        ];
    }
} else {
    // Fallback if no database connection
    $theme = [
        'bg_color' => '#667eea',
        'font_color' => '#FFFFFF',
        'button_color' => '#764ba2',
        'container_color' => '#ffffff',
        'sidebar_font_color' => '#333333',
        'sidebar_hover' => '#667eea',
        'sidebar_hover_font' => '#FFFFFF'
    ];
}
?>
<style>
    :root {
        --theme-bg: <?= $theme['bg_color'] ?>;
        --theme-font: <?= $theme['font_color'] ?>;
        --theme-button: <?= $theme['button_color'] ?>;
        --theme-container: <?= $theme['container_color'] ?>;
        --theme-sidebar-font: <?= $theme['sidebar_font_color'] ?>;
        --theme-sidebar-hover: <?= $theme['sidebar_hover'] ?>;
        --theme-sidebar-hover-font: <?= $theme['sidebar_hover_font'] ?>;
    }

    /* Apply theme to body background */
    body {
        background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%) !important;
        color: var(--theme-font);
    }

    /* Apply theme to sidebar */
    .sidebar {
        background: var(--theme-container) !important;
    }

    .sidebar a {
        color: var(--theme-sidebar-font) !important;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background-color: var(--theme-sidebar-hover) !important;
        color: var(--theme-sidebar-hover-font) !important;
    }

    /* Apply theme to buttons */
    button,
    .btn,
    .save-btn,
    .add-btn,
    .edit-btn,
    .action-btn,
    .dropdown,
    .clear-btn,
    .upload-btn,
    .update-btn,
    .modal-btn-primary {
        background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%) !important;
        color: var(--theme-font) !important;
    }

    /* Apply theme to headers and cards */
    .header {
        background: rgba(255, 255, 255, 0.95) !important;
    }

    .header h2 {
        color: var(--theme-bg) !important;
    }

    .header-card {
        background: var(--theme-button) !important;
        color: var(--theme-font) !important;
    }

    /* Apply theme to content cards */
    .content-card,
    .table-container,
    .update-status-section,
    .chart-box {
        background: rgba(255, 255, 255, 0.95) !important;
    }

    /* Apply theme to section titles */
    .section-title,
    .chart-header h3 {
        color: var(--theme-bg) !important;
        border-bottom-color: var(--theme-bg) !important;
    }

    /* Apply theme to form labels */
    .form-group label {
        color: var(--theme-bg) !important;
    }

    /* Apply theme to icons */
    .header-right i {
        color: var(--theme-bg) !important;
    }

    /* Apply theme to profile borders */
    .header-right img,
    .sidebar-logo img {
        border-color: var(--theme-bg) !important;
    }

    /* Apply theme to step indicators */
    .step-indicator {
        background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%) !important;
    }

    /* Apply theme to tooltips */
    .tooltip i {
        color: var(--theme-bg) !important;
    }

    /* Apply theme to color options */
    .color-option.active {
        border-color: var(--theme-bg) !important;
    }

    .color-option.active::after {
        background: var(--theme-bg) !important;
    }

    /* Apply theme to edit toggle button */
    .edit-toggle-btn {
        background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%) !important;
    }

    /* Apply theme to file input labels */
    .file-input-label {
        background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%) !important;
    }

    /* Apply theme to preview logo border */
    .preview-logo {
        border-color: var(--theme-bg) !important;
    }

    /* Apply theme to status container */
    .status-container {
        background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%) !important;
    }

    /* Apply theme to cards on dashboard */
    .card h3 {
        background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .card:hover {
        border-color: var(--theme-bg) !important;
    }
</style>
<?php
// Also fetch and store business info for global use
if (isset($conn)) {
    $business_query = "SELECT * FROM business_info WHERE id = 1 LIMIT 1";
    $business_result = $conn->query($business_query);
    
    if ($business_result && $business_result->num_rows > 0) {
        $global_business_info = $business_result->fetch_assoc();
    }
}
?>
