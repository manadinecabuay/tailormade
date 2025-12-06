<?php
include 'connection.php';
include 'business_function.php';

$business = getBusinessInfo($conn);

// Now you can access values like:
$business_name = $business['business_name'];
$logo_path = $business['logo_path'];
$about_us = $business['about_us'];
$email = $business['email'];
$contact = $business['contact'];
$machine_message = $business['machine_availability_message'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TailorMade - Login</title>

  <?php include('theme_loader.php'); ?>

  <style>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: white;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Fade-in animation on everything */
    .fade {
      opacity: 0;
      animation: fadeIn 1s ease forwards;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(25px); }
      to { opacity: 1; transform: translateY(0); }
    }

    header {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 20px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo img { 
      width: 45px;
      height: 45px;
      object-fit: cover;
      border-radius: 50%;
      border: 2px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .logo-text {
      font-size: 20px;
      font-weight: 700;
      color: white;
      letter-spacing: 0.5px;
    }

   nav a {
      margin: 0 15px;
      color: white;
      text-decoration: none;
      padding: 10px 20px;
      border: 2px solid transparent;
      border-radius: 25px;
      transition: all 0.3s ease;
      font-weight: 500;
    }

    nav a:hover { 
      border: 2px solid white; 
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-3px);
    }

    nav a.active {
      border: 2px solid white;
      background: rgba(255, 255, 255, 0.2);
      font-weight: 600;
    }

    nav a:active {
      transform: translateY(0);
      background: rgba(255, 255, 255, 0.3);
    }

    .form-container {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-grow: 1;
      padding: 40px 20px;
    }

    form {
      background: rgba(255, 255, 255, 0.95);
      padding: 50px;
      border-radius: 25px;
      width: 450px;
      max-width: 95%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      color: #333;
    }

    form h2 {
      text-align: center;
      margin-bottom: 35px;
      letter-spacing: 2px;
      font-weight: bold;
      color: var(--theme-bg);
      font-size: 32px;
    }

    .input-group {
      display: flex;
      flex-direction: column;
      margin-bottom: 25px;
    }

    .input-group label {
      margin-bottom: 8px;
      font-size: 14px;
      font-weight: 600;
      color: #555;
    }

    .input-group input {
      padding: 14px 18px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 15px;
      color: #333;
      transition: all 0.3s ease;
    }

    .input-group input:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    button {
      margin-top: 20px;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      padding: 16px;
      border: none;
      border-radius: 25px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
      font-size: 16px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
    }

    p {
      margin-top: 20px;
      font-size: 14px;
      text-align: center;
      color: #666;
    
    }

    .toggle-password {
      font-weight:bold;
      right: 15px;
      top: 45%;
      margin-top:22px;
      margin-right:3px;
      size:15px;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 12px;
      user-select: none;
      transition: all 0.3s ease;
    }

    .toggle-password:hover {
      transform: translateY(-50%) scale(1.2);
    }

    p a {
      color: var(--theme-bg);
      font-weight: bold;
      text-decoration: none;
    }

    p a:hover { text-decoration: underline; }

    .alert-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      animation: fadeIn 0.3s ease;
    }

    .alert-modal {
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

    .alert-modal h3 {
      color: #333;
      margin-bottom: 10px;
      font-size: 20px;
      font-weight: 600;
    }

    .alert-modal p {
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
      border: 3px solid rgba(40, 167, 69, 0.3);
      border-radius: 50%;
      border-top-color: #28a745;
      animation: spin 0.8s linear infinite;
      margin-left: 10px;
      vertical-align: middle;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* ========================================
       MOBILE RESPONSIVE STYLES
       ======================================== */
    
    /* Tablets and smaller (max-width: 768px) */
    @media (max-width: 768px) {
      header {
        flex-direction: column;
        padding: 15px 20px;
        gap: 15px;
      }

      .logo {
        gap: 10px;
      }

      .logo img {
        width: 40px;
        height: 40px;
      }

      .logo-text {
        font-size: 18px;
      }

      nav {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
      }

      nav a {
        margin: 0;
        padding: 8px 15px;
        font-size: 14px;
      }

      .form-container {
        padding: 30px 15px;
      }

      form {
        padding: 40px 30px;
        width: 100%;
        max-width: 450px;
      }

      form h2 {
        font-size: 28px;
        margin-bottom: 30px;
      }

      .input-group {
        margin-bottom: 20px;
      }

      .input-group input {
        padding: 12px 16px;
        font-size: 14px;
      }

      button {
        padding: 14px;
        font-size: 15px;
      }


    }

    /* Mobile phones (max-width: 480px) */
    @media (max-width: 480px) {
      header {
        padding: 12px 15px;
      }

      .logo img {
        width: 35px;
        height: 35px;
      }

      .logo-text {
        font-size: 16px;
      }

      nav a {
        padding: 6px 12px;
        font-size: 13px;
      }

      .form-container {
        padding: 20px 10px;
      }

      form {
        padding: 30px 20px;
        border-radius: 20px;
      }

      form h2 {
        font-size: 24px;
        margin-bottom: 25px;
        letter-spacing: 1px;
      }

      .input-group {
        margin-bottom: 18px;
      }

      .input-group label {
        font-size: 13px;
        margin-bottom: 6px;
      }

      .input-group input {
        padding: 11px 14px;
        font-size: 14px;
        border-radius: 8px;
      }

      .toggle-password {
        font-size: 11px;
        margin-top: 20px;
      }

      button {
        padding: 13px;
        font-size: 14px;
        margin-top: 15px;
        border-radius: 20px;
      }

      p {
        font-size: 13px;
        margin-top: 15px;
      }


    }

    /* Extra small devices (max-width: 360px) */
    @media (max-width: 360px) {
      form {
        padding: 25px 15px;
      }

      form h2 {
        font-size: 22px;
        margin-bottom: 20px;
      }

      .input-group input {
        padding: 10px 12px;
        font-size: 13px;
      }

      button {
        padding: 12px;
        font-size: 13px;
        letter-spacing: 0.5px;
      }

      .alert-modal {
        padding: 20px;
        max-width: 280px;
      }

      .alert-modal h3 {
        font-size: 18px;
      }

      .alert-modal p {
        font-size: 12px;
      }
    }

    /* Landscape orientation adjustments */
    @media (max-height: 600px) and (orientation: landscape) {
      .form-container {
        padding: 20px 15px;
      }

      form {
        padding: 25px 30px;
      }

      form h2 {
        font-size: 24px;
        margin-bottom: 20px;
      }

      .input-group {
        margin-bottom: 15px;
      }

      button {
        margin-top: 15px;
        padding: 12px;
      }

      p {
        margin-top: 15px;
      }
    }

    /* Very small height devices */
    @media (max-height: 500px) {
      header {
        padding: 10px 20px;
      }

      .form-container {
        padding: 15px 10px;
      }

      form {
        padding: 20px 25px;
      }

      form h2 {
        font-size: 20px;
        margin-bottom: 15px;
      }

      .input-group {
        margin-bottom: 12px;
      }

      .input-group input {
        padding: 10px 14px;
      }

      button {
        padding: 10px;
        margin-top: 10px;
      }

      p {
        margin-top: 10px;
        font-size: 12px;
      }
    }
  </style>
</head>
<body>
  <header> 
  <div class="logo">
    <img src="<?php echo $business['logo_path']; ?>" alt="TailorMade Logo">
    <span class="logo-text">TailorMade</span>
  </div>
  
    <nav>
      <a href="index.php">Home</a>
      <a href="index.php#about">About Us</a>
      <a href="index.php#contact">Contact Us</a>
      <a href="customer_login.php" class="active">Login</a>
    </nav>
  </header>

  <div class="form-container">
    <form action="login_customer.php" method="POST">
      <h2>LOGIN</h2>

      <div class="input-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"  value="<?php echo isset($_GET['user']) ? htmlspecialchars($_GET['user']) : ''; ?>" placeholder="Enter Username" required>
      </div>

      <div class="input-group" style="position: relative;">
        <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter Password" required " >
          <span style="position:absolute; right:10px; top:35%; transform:translateY(-50%); border:none; color:#0b0f39; cursor:pointer;" class="toggle-password" id="togglePassword">show</span>
      </div>

      <p>Don’t have an account? <a href="customer_registration.php">REGISTER</a></p>
      <button type="submit">LOGIN</button>
    </form>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="alert-overlay" style="display:none;">
    <div class="alert-modal">
      <div class="modal-icon success">✓</div>
      <h3>Success</h3>
      <p id="successMessage">Login successful! Redirecting...</p>
    </div>
  </div>

  <!-- Error Modal -->
  <div id="errorModal" class="alert-overlay" style="display:none;">
    <div class="alert-modal">
      <div class="modal-icon error">✕</div>
      <h3>Error</h3>
      <p id="errorMessage">Invalid username or password!</p>
      <p id="forgotPasswordLink" style="margin: 0 0 20px 0; font-size: 13px;">
        <a href="forgot_password.php" style="color: var(--theme-bg); font-weight: 600; text-decoration: none;">Forgot Password?</a>
      </p>
      <button class="modal-btn error" onclick="closeErrorModal()">OK</button>
    </div>
  </div>

  <script>
    
    // Check for status in URL
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('msg');
    const redirect = urlParams.get('redirect');
    const logoutMessage = urlParams.get('message');
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');

    // Password toggle functionality
    togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        togglePassword.textContent = isPassword ? 'hide' : 'show';
    });

    
    // Handle logout message
    if (logoutMessage === 'logged_out') {
      document.getElementById('successMessage').innerHTML = 'You have been logged out successfully!';
      document.getElementById('successModal').style.display = 'flex';
      setTimeout(() => {
        document.getElementById('successModal').style.display = 'none';
      }, 2500);
    }
    
    if (status === 'success') {
      const successMsg = message || 'Login successful! Redirecting';
      document.getElementById('successMessage').innerHTML = successMsg + '<span class="loading-spinner"></span>';
      document.getElementById('successModal').style.display = 'flex';
      
      // Determine redirect URL based on user type
      let redirectUrl = 'user_home.php'; // Default for customers
      if (redirect === 'superadmin') {
        redirectUrl = 'superadmin_dashboard.php';
      } else if (redirect === 'admin') {
        redirectUrl = 'admin_dashboard.php';
      }
      
      setTimeout(() => {
        window.location.href = redirectUrl;
      }, 2500);
    } else if (status === 'error') {
      document.getElementById('errorMessage').textContent = message || 'Invalid username or password!';
      document.getElementById('errorModal').style.display = 'flex';
    }

    // Clear URL parameters
    if (status || logoutMessage) {
      window.history.replaceState(null, null, window.location.pathname);
    }

    function closeErrorModal() {
      document.getElementById('errorModal').style.display = 'none';
    }

    function closeSuccessModal() {
      document.getElementById('successModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
      if (e.target.classList.contains('alert-overlay')) {
        e.target.style.display = 'none';
      }
      
    });
  </script>
</body>
</html>
