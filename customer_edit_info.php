<?php

include('connection.php');
include 'session_customer.php'; ?>

<?php
include 'business_function.php';

$business = getBusinessInfo($conn);

if (!isset($_SESSION['customerID'])) {
    header("Location: customer_login.php?status=error&msg=" . urlencode("Please login first"));
    exit;
}

$customer_id = $_SESSION['customerID'];

// Fetch customer data
$stmt = $conn->prepare("SELECT username, email, password FROM customers WHERE customerID = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->bind_result($username, $email, $password);
$stmt->fetch();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TailorMade - Account</title>

  <?php include('theme_loader.php'); ?>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
    min-height: 100vh;
  }

  .navbar {
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

    .navbar ul {
      list-style: none;
      display: flex;
      gap: 20px;
    }

    .navbar ul li a {
      text-decoration: none;
      color: white;
      padding: 10px 20px;
      border-radius: 25px;
      transition: all 0.3s ease;
      font-weight: 500;
      border: 2px solid transparent;
    }

    .navbar ul li a:hover, .navbar ul li a.active {
      border: 2px solid white;
      background: rgba(255, 255, 255, 0.1);
    }

  .account-container {
    background: rgba(255, 255, 255, 0.95);
    padding: 50px;
    border-radius: 30px;
    max-width: 550px;
    margin: 80px auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  }

  .account-container h2 {
    text-align: center;
    margin-bottom: 35px;
    font-size: 28px;
    letter-spacing: 1px;
    color: var(--theme-bg);
    font-weight: 700;
  }

  .account-container h2 i {
    font-size: 24px;
    margin-right: 10px;
  }

  .form-group {
    margin-bottom: 20px;
  }

  .form-group label {
    display: block;
    font-size: 14px;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
  }

  .form-group input {
    width: 100%;
    padding: 14px 18px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    outline: none;
    font-size: 15px;
    background: #fff;
    color: #333;
    transition: all 0.3s ease;
  }

  .form-group input:focus {
    border-color: var(--theme-bg);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }

  .form-group input[readonly] {
    background: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
    border-color: #dee2e6;
  }

  .error-message {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
    display: none;
  }

  .error-message.show {
    display: block;
  }

  .password-requirements {
    font-size: 12px;
    margin-top: 8px;
    color: #666;
    display: none;
  }

  .password-requirements.show {
    display: block;
  }

  .password-requirements div {
    margin: 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .password-requirements i {
    font-size: 10px;
  }

  .password-requirements .valid {
    color: #28a745;
  }

  .password-requirements .invalid {
    color: #dc3545;
  }

  .success-message {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border: 2px solid #28a745;
    color: #155724;
    padding: 15px 20px;
    border-radius: 15px;
    margin-bottom: 25px;
    font-size: 14px;
    text-align: center;
    font-weight: 500;
    box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
    display: none;
  }

  .success-message.show {
    display: block;
    animation: slideDown 0.3s ease;
  }

  @keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .btn-edit {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
  }

  .btn-save {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    display: none;
  }

  .btn-cancel-edit {
    background: transparent;
    color: #666;
    border: 2px solid #ddd;
    display: none;
  }

  .btn-cancel-edit:hover {
    border-color: #999;
    color: #333;
  }

  .buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 35px;
  }

  .buttons button {
    padding: 14px 35px;
    border-radius: 25px;
    border: none;
    font-weight: 700;
    cursor: pointer;
    background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
    color: var(--theme-font);
    transition: all 0.3s ease;
    font-size: 15px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .buttons button:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
  }

  /* Mobile Responsive Styles */
  @media (max-width: 768px) {
    .navbar {
      padding: 15px 20px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .logo img {
      width: 40px;
      height: 40px;
    }

    .logo-text {
      font-size: 18px;
    }

    .navbar ul {
      width: 100%;
      justify-content: space-around;
      gap: 10px;
      flex-wrap: wrap;
    }

    .navbar ul li a {
      padding: 8px 15px;
      font-size: 14px;
    }

    .account-container {
      margin: 40px auto;
      padding: 35px 25px;
      max-width: 95%;
      border-radius: 20px;
    }

    .account-container h2 {
      font-size: 24px;
      margin-bottom: 25px;
    }

    .account-container h2 i {
      font-size: 20px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      font-size: 13px;
      margin-bottom: 6px;
    }

    .form-group input {
      padding: 12px 16px;
      font-size: 14px;
    }

    .info-note {
      padding: 12px 16px;
      font-size: 13px;
      margin-bottom: 20px;
    }

    .buttons {
      margin-top: 25px;
      flex-direction: column;
    }

    .buttons button {
      width: 100%;
      padding: 12px 30px;
      font-size: 14px;
    }
  }

  @media (max-width: 480px) {
    .navbar {
      padding: 12px 15px;
    }

    .logo img {
      width: 35px;
      height: 35px;
    }

    .logo-text {
      font-size: 16px;
    }

    .navbar ul {
      gap: 5px;
    }

    .navbar ul li a {
      padding: 6px 12px;
      font-size: 13px;
    }

    .account-container {
      margin: 30px auto;
      padding: 30px 20px;
      border-radius: 15px;
    }

    .account-container h2 {
      font-size: 20px;
      margin-bottom: 20px;
      letter-spacing: 0.5px;
    }

    .account-container h2 i {
      font-size: 18px;
      margin-right: 8px;
    }

    .form-group {
      margin-bottom: 14px;
    }

    .form-group label {
      font-size: 12px;
      margin-bottom: 5px;
    }

    .form-group input {
      padding: 11px 14px;
      font-size: 13px;
      border-radius: 10px;
    }

    .info-note {
      padding: 10px 14px;
      font-size: 12px;
      margin-bottom: 18px;
      border-radius: 12px;
    }

    .buttons {
      margin-top: 20px;
    }

    .buttons button {
      padding: 11px 25px;
      font-size: 13px;
    }
  }
  </style>
</head>
<body>
  <div class="navbar">
     <div class="logo">
    <img src="<?php echo $business ['logo_path']; ?>" alt="TailorMade Logo">
    <span class="logo-text">TailorMade</span>
  </div>

    <ul>
      <li><a href="user_home.php">Home</a></li>
      <li><a href="orders.php">Order</a></li>
      <li><a href="track.php">Track</a></li>
      <li><a href="customer_edit_info.php" class="active">Account</a></li>
    </ul>
  </div>

  <div class="account-container">
    <h2><i class="fa-solid fa-user"></i> ACCOUNT INFORMATION</h2>
    
    <div id="successMessage" class="success-message">
      <i class="fa-solid fa-check-circle"></i> <span id="successText"></span>
    </div>

    <form id="updateForm" onsubmit="return saveChanges(event)">
      <div class="form-group">
        <label>Username</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" readonly required minlength="3">
        <div id="usernameError" class="error-message"></div>
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
      </div>

      <div class="form-group">
        <label>New Password (leave blank to keep current)</label>
        <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" readonly>
        <div id="passwordError" class="error-message"></div>
        <div id="passwordRequirements" class="password-requirements">
          <div id="req-length"><i class="fa-solid fa-circle"></i> At least 8 characters</div>
          <div id="req-capital"><i class="fa-solid fa-circle"></i> At least 1 capital letter</div>
          <div id="req-number"><i class="fa-solid fa-circle"></i> At least 1 number</div>
        </div>
      </div>

      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password" readonly>
        <div id="confirmError" class="error-message"></div>
      </div>

      <div class="buttons">
        <button type="button" id="btnEdit" class="btn-edit" onclick="enableEditing()">
          <i class="fa-solid fa-pen-to-square"></i> EDIT INFO
        </button>
        <button type="submit" id="btnSave" class="btn-save">
          <i class="fa-solid fa-save"></i> SAVE CHANGES
        </button>
        <button type="button" id="btnCancelEdit" class="btn-cancel-edit" onclick="cancelEditing()">
          <i class="fa-solid fa-times"></i> CANCEL
        </button>
        <button type="button" onclick="showLogoutModal()">
          <i class="fa-solid fa-right-from-bracket"></i> LOGOUT
        </button>
      </div>
    </form>
  </div>

  <!-- Logout Confirmation Modal -->
  <div id="logoutModal" class="logout-modal-overlay" style="display: none;">
    <div class="logout-modal">
      <div class="logout-modal-icon">
        <i class="fa-solid fa-right-from-bracket"></i>
      </div>
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to logout?</p>
      <div class="logout-modal-buttons">
        <button onclick="confirmLogout()" class="btn-logout">Logout</button>
        <button onclick="closeLogoutModal()" class="btn-cancel">Cancel</button>
      </div>
    </div>
  </div>

  <style>
  .logout-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .logout-modal {
    background: white;
    padding: 30px;
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

  .logout-modal-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 20px;
    background: rgba(102, 126, 234, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .logout-modal-icon i {
    font-size: 28px;
    color: var(--theme-bg);
  }

  .logout-modal h3 {
    margin: 0 0 10px;
    font-size: 20px;
    font-weight: 600;
    color: #333;
  }

  .logout-modal p {
    font-size: 14px;
    margin: 0 0 25px;
    color: #999;
    line-height: 1.5;
  }

  .logout-modal-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
  }

  .logout-modal-buttons button {
    flex: 1;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .btn-logout {
    background: var(--theme-bg);
    color: white;
    border: none;
  }

  .btn-logout:hover {
    opacity: 0.9;
    transform: translateY(-2px);
  }

  .btn-cancel {
    background: transparent;
    color: #666;
    border: 2px solid #ddd;
  }

  .btn-cancel:hover {
    border-color: #999;
    color: #333;
    transform: translateY(-2px);
  }

  @media (max-width: 480px) {
    .logout-modal {
      padding: 25px 20px;
      max-width: 320px;
    }
    
    .logout-modal h3 {
      font-size: 18px;
    }
    
    .logout-modal p {
      font-size: 13px;
    }
  }
  </style>

  <script>
    let isEditing = false;
    const originalUsername = "<?php echo htmlspecialchars($username); ?>";

    // Password validation
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordRequirements = document.getElementById('passwordRequirements');

    newPasswordInput.addEventListener('input', function() {
      const password = this.value;
      
      if (password.length === 0) {
        passwordRequirements.classList.remove('show');
        return;
      }

      passwordRequirements.classList.add('show');

      // Check length
      const lengthReq = document.getElementById('req-length');
      if (password.length >= 8) {
        lengthReq.classList.add('valid');
        lengthReq.classList.remove('invalid');
        lengthReq.querySelector('i').className = 'fa-solid fa-check-circle';
      } else {
        lengthReq.classList.add('invalid');
        lengthReq.classList.remove('valid');
        lengthReq.querySelector('i').className = 'fa-solid fa-circle';
      }

      // Check capital letter
      const capitalReq = document.getElementById('req-capital');
      if (/[A-Z]/.test(password)) {
        capitalReq.classList.add('valid');
        capitalReq.classList.remove('invalid');
        capitalReq.querySelector('i').className = 'fa-solid fa-check-circle';
      } else {
        capitalReq.classList.add('invalid');
        capitalReq.classList.remove('valid');
        capitalReq.querySelector('i').className = 'fa-solid fa-circle';
      }

      // Check number
      const numberReq = document.getElementById('req-number');
      if (/[0-9]/.test(password)) {
        numberReq.classList.add('valid');
        numberReq.classList.remove('invalid');
        numberReq.querySelector('i').className = 'fa-solid fa-check-circle';
      } else {
        numberReq.classList.add('invalid');
        numberReq.classList.remove('valid');
        numberReq.querySelector('i').className = 'fa-solid fa-circle';
      }
    });

    function enableEditing() {
      isEditing = true;
      
      // Enable username and password fields
      document.getElementById('username').removeAttribute('readonly');
      document.getElementById('newPassword').removeAttribute('readonly');
      document.getElementById('confirmPassword').removeAttribute('readonly');
      
      // Toggle buttons
      document.getElementById('btnEdit').style.display = 'none';
      document.getElementById('btnSave').style.display = 'block';
      document.getElementById('btnCancelEdit').style.display = 'block';
      
      // Focus on username
      document.getElementById('username').focus();
    }

    function cancelEditing() {
      isEditing = false;
      
      // Reset fields
      document.getElementById('username').value = originalUsername;
      document.getElementById('newPassword').value = '';
      document.getElementById('confirmPassword').value = '';
      
      // Make fields readonly again
      document.getElementById('username').setAttribute('readonly', true);
      document.getElementById('newPassword').setAttribute('readonly', true);
      document.getElementById('confirmPassword').setAttribute('readonly', true);
      
      // Clear errors
      document.getElementById('usernameError').classList.remove('show');
      document.getElementById('passwordError').classList.remove('show');
      document.getElementById('confirmError').classList.remove('show');
      document.getElementById('successMessage').classList.remove('show');
      passwordRequirements.classList.remove('show');
      
      // Toggle buttons
      document.getElementById('btnEdit').style.display = 'block';
      document.getElementById('btnSave').style.display = 'none';
      document.getElementById('btnCancelEdit').style.display = 'none';
    }

    function validatePassword(password) {
      if (password.length === 0) return true; // Allow empty (no change)
      
      if (password.length < 8) {
        return 'Password must be at least 8 characters long';
      }
      if (!/[A-Z]/.test(password)) {
        return 'Password must contain at least 1 capital letter';
      }
      if (!/[0-9]/.test(password)) {
        return 'Password must contain at least 1 number';
      }
      return true;
    }

    function saveChanges(event) {
      event.preventDefault();

      // Clear previous errors
      document.getElementById('usernameError').classList.remove('show');
      document.getElementById('passwordError').classList.remove('show');
      document.getElementById('confirmError').classList.remove('show');
      document.getElementById('successMessage').classList.remove('show');

      const username = document.getElementById('username').value.trim();
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;

      let hasError = false;

      // Validate username
      if (username.length < 3) {
        document.getElementById('usernameError').textContent = 'Username must be at least 3 characters';
        document.getElementById('usernameError').classList.add('show');
        hasError = true;
      }

      // Validate password if provided
      if (newPassword.length > 0) {
        const passwordValidation = validatePassword(newPassword);
        if (passwordValidation !== true) {
          document.getElementById('passwordError').textContent = passwordValidation;
          document.getElementById('passwordError').classList.add('show');
          hasError = true;
        }

        // Check if passwords match
        if (newPassword !== confirmPassword) {
          document.getElementById('confirmError').textContent = 'Passwords do not match';
          document.getElementById('confirmError').classList.add('show');
          hasError = true;
        }
      }

      if (hasError) {
        return false;
      }

      // Submit via AJAX
      const formData = new FormData();
      formData.append('username', username);
      if (newPassword.length > 0) {
        formData.append('newPassword', newPassword);
      }

      fetch('update_customer_credentials.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('successText').textContent = data.message;
          document.getElementById('successMessage').classList.add('show');
          
          // Clear password fields
          document.getElementById('newPassword').value = '';
          document.getElementById('confirmPassword').value = '';
          passwordRequirements.classList.remove('show');

          // Make fields readonly again
          document.getElementById('username').setAttribute('readonly', true);
          document.getElementById('newPassword').setAttribute('readonly', true);
          document.getElementById('confirmPassword').setAttribute('readonly', true);
          
          // Toggle buttons
          document.getElementById('btnEdit').style.display = 'block';
          document.getElementById('btnSave').style.display = 'none';
          document.getElementById('btnCancelEdit').style.display = 'none';
          
          isEditing = false;

          // Scroll to top to show success message
          window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
          if (data.field === 'username') {
            document.getElementById('usernameError').textContent = data.message;
            document.getElementById('usernameError').classList.add('show');
          } else {
            document.getElementById('passwordError').textContent = data.message;
            document.getElementById('passwordError').classList.add('show');
          }
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('passwordError').textContent = 'An error occurred. Please try again.';
        document.getElementById('passwordError').classList.add('show');
      });

      return false;
    }

    function showLogoutModal() {
      document.getElementById('logoutModal').style.display = 'flex';
    }

    function closeLogoutModal() {
      document.getElementById('logoutModal').style.display = 'none';
    }

    function confirmLogout() {
      window.location.href = 'logout_customer.php';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
      if (e.target.id === 'logoutModal') {
        closeLogoutModal();
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeLogoutModal();
      }
    });
  </script>

</body>
</html>


