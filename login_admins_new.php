<?php
include 'connection.php';
include 'business_function.php';

$business = getBusinessInfo($conn);
$business_name = $business['business_name'];
$logo_path = $business['logo_path'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | <?php echo $business_name; ?></title>

  <?php include('theme_loader.php'); ?>

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: white;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .container {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
      min-height: 100vh;
      width: 100%;
    }

    .form-wrapper {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 30px;
      padding: 50px 60px;
      max-width: 500px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.5s ease;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    h2 { 
      text-align: center; 
      margin-bottom: 35px; 
      color: var(--theme-bg); 
      letter-spacing: 2px;
      font-size: 32px;
      font-weight: 700;
    }

    label { 
      display: block; 
      font-size: 14px; 
      color: #555; 
      margin-bottom: 8px;
      font-weight: 600;
    }

    input, select { 
      width: 100%; 
      padding: 14px 18px; 
      border-radius: 12px; 
      border: 2px solid #e0e0e0; 
      margin-bottom: 20px; 
      font-size: 15px; 
      background: white; 
      color: #333; 
      transition: all 0.3s ease;
      font-family: inherit;
    }

    input:focus, select:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .password-container {
      position: relative;
    }

    .toggle-password {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #0b0b0bff;
      font-size: 18px;
    }

    .checkbox-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      gap: 10px;
    }

    .checkbox-row label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      color: #666;
      cursor: pointer;
      font-weight: 500;
    }

    .checkbox-row input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: var(--theme-bg);
      cursor: pointer;
      margin: 0;
    }

    .checkbox-row a {
      font-size: 14px;
      color: var(--theme-bg);
      text-decoration: none;
      transition: 0.3s;
      white-space: nowrap;
      font-weight: 600;
    }

    .checkbox-row a:hover {
      text-decoration: underline;
      opacity: 0.8;
    }

    .btn {
      display: block;
      width: 100%;
      padding: 16px;
      margin-top: 20px;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border-radius: 25px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: all 0.3s ease;
      font-size: 16px;
    }

    .btn:hover { 
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }

    p { 
      text-align: center; 
      margin-top: 20px; 
      font-size: 14px;
      color: #666;
    }

    p a { 
      color: var(--theme-bg); 
      font-weight: 600; 
      text-decoration: none; 
    }

    p a:hover { 
      text-decoration: underline;
      opacity: 0.8;
    }

    @media (max-width: 768px) { 
      .form-wrapper { padding: 30px; max-width: 90%; } 
    }

    /* Enhanced Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: none;
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
      background: white;
      padding: 30px 30px 0 30px;
      border-radius: 12px;
      text-align: center;
      width: 90%;
      max-width: 380px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
      animation: slideInModal 0.3s ease;
      position: relative;
    }

    @keyframes slideInModal {
      from { transform: scale(0.9) translateY(-20px); opacity: 0; }
      to { transform: scale(1) translateY(0); opacity: 1; }
    }

    .modal-icon {
      width: 60px;
      height: 60px;
      margin: 0 auto 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      font-weight: bold;
    }

    .modal-icon.success { 
      background: rgba(40, 167, 69, 0.15);
      color: #28a745;
    }
    
    .modal-icon.error { 
      background: rgba(220, 53, 69, 0.15);
      color: #dc3545;
    }

    .modal-content h3 {
      color: #333;
      margin-bottom: 10px;
      font-size: 20px;
      font-weight: 600;
    }

    .modal-content p {
      color: #999;
      margin-bottom: 25px;
      line-height: 1.5;
      font-size: 14px;
    }

    .modal-btn {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 0 0 12px 12px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
      margin: 0 -30px;
      width: calc(100% + 60px);
    }

    .modal-btn.error {
      background: #dc3545;
      color: white;
    }

    .modal-btn.error:hover {
      background: #c82333;
    }

    .modal-btn.success {
      background: #28a745;
      color: white;
    }

    .modal-btn.success:hover {
      background: #218838;
    }

    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 0.8s linear infinite;
      margin-left: 10px;
      vertical-align: middle;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    @media (max-width: 768px) { 
      .form-wrapper { padding: 40px 30px; max-width: 90%; }
      h2 { font-size: 26px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="form-wrapper">
      <h2>🔐 Admin Login</h2>
      <form id="loginForm" method="POST" action="login_admins.php">
        <label for="role">Select Role</label>
        <select name="role" id="role" required>
          <option value="">Select Role</option>
          <option value="owner">Owner</option>
          <option value="supervisor">Supervisor</option>
        </select>

        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter your username" required>

        <div style="position: relative;">
        <input type="password" id="password" name="password" placeholder="Password" required>
        <button type="button" id="togglePassword" 
            style="position:absolute; right:10px; top:35%; transform:translateY(-50%);
                 border:none; color:#000; font-weight:bold; cursor:pointer;">Show
        </button>
        </div>


        <div class="checkbox-row">
          <label><input type="checkbox" id="remember"> Remember me</label>
          <a href="#">Forgot Password?</a>
        </div>

        <button class="btn" type="submit">Login</button>

        <p>Don't have an account? <a href="supervisor_register.html">Register</a></p>
      </form>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-icon success">✓</div>
      <h3>Success</h3>
      <p id="successMessage">Successfully logged in and redirecting...</p>
    </div>
  </div>

  <!-- Error Modal -->
  <div id="errorModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-icon error">✕</div>
      <h3>Error</h3>
      <p id="errorMessage">Incorrect password or account does not exist.</p>
      <button class="modal-btn error" onclick="closeModal('errorModal')">OK</button>
    </div>
  </div>

  <script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        togglePassword.textContent = isPassword ? 'Hide' : 'Show';
    });

    // Handle login via fetch
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);

      const response = await fetch('login_admins.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      if (result.status === 'success') {
        showModal('successModal', 'Redirecting to your dashboard...');
        setTimeout(() => window.location.href = result.redirect, 1500);
      } else {
        showModal('errorModal', result.message);
      }
    });

    // Modal functions
    function showModal(modalId, message) {
      const modal = document.getElementById(modalId);
      const messageElement = modal.querySelector('p');
      messageElement.textContent = message;
      modal.style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
      if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
      }
    });

    // Check for session expired
    const params = new URLSearchParams(window.location.search);
    if (params.get('error') === 'session_expired') {
      showModal('errorModal', 'Your session has expired due to inactivity. Please log in again.');
    }
  </script>
</body>
</html>
