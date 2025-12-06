<?php
session_start();
include 'connection.php';

$error = '';
$success = '';

include 'business_function.php';

$business = getBusinessInfo($conn);

// Now you can access values like:
$business_name = $business['business_name'];
$logo_path = $business['logo_path'];
$about_us = $business['about_us'];
$email = $business['email'];
$contact = $business['contact'];
$machine_message = $business['machine_availability_message'];



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize inputs
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $age = intval($_POST['age']);
    $username = trim($_POST['username']);
    $city = trim($_POST['city']);
    $barangay = trim($_POST['barangay']);
    $house_block = trim($_POST['house_block'] ?? '');
    $complete_address = "Cavite, " . $city . ", " . $barangay . ($house_block ? ", " . $house_block : "");
    $contact = trim($_POST['contact']);
    $password = $_POST['password'];
    $cpass = $_POST['confirmpassword'];

    // Server-side validation
    $errors = [];

    // Fullname
    if (empty($fullname)) {
        $errors[] = "Full name is required.";
    }

    // Username must contain AT LEAST ONE NUMBER
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match('/[0-9]/', $username)) {
        $errors[] = "Username must contain at least one number.";
    }



    // Email validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
        $errors[] = "Email must be a valid Gmail address ending with @gmail.com.";
    }

    // Age (18+)
    if ($age < 18) {
        $errors[] = "You must be at least 18 years old to register.";
    }

    // Contact (numbers only)
    if (empty($contact)) {
        $errors[] = "Contact number is required.";
    } elseif (!preg_match('/^[0-9]+$/', $contact)) {
        $errors[] = "Contact number must contain numbers only.";
    }

    // Location
    if (empty($city)) {
        $errors[] = "City is required.";
    }
    if (empty($barangay)) {
        $errors[] = "Barangay is required.";
    }

    // Password (8+ chars, at least 1 special)
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }

    // Password match
    if ($password !== $cpass) {
        $errors[] = "Passwords do not match.";
    }

    // --- If no errors, insert user ---
    if (empty($errors)) {

        // Check if username exists
        $checkusername = $conn->prepare("SELECT customerID FROM customers WHERE username = ?");
        $checkusername->bind_param("s", $username);
        $checkusername->execute();
        $resultUsername = $checkusername->get_result();

        // Check if email exists
        $checkemail = $conn->prepare("SELECT customerID FROM customers WHERE email = ?");
        $checkemail->bind_param("s", $email);
        $checkemail->execute();
        $resultEmail = $checkemail->get_result();

        if ($resultUsername->num_rows > 0) {
            $error = "Username already exists. Please choose another.";
        } elseif ($resultEmail->num_rows > 0) {
            $error = "An account with this email already exists. Please use a different email or login to your existing account.";
        } else {

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO customers (full_name, age, email, username, address, contact, PASSWORD) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssss", $fullname, $age, $email, $username, $complete_address, $contact, $hashedPassword);

            if ($stmt->execute()) {
                $success = "Registration successful! Redirecting to login...";
                header("refresh:2;url=customer_login.php");
            } else {
                $error = "Registration failed. Please try again.";
            }

            $stmt->close();
        }

        $checkusername->close();
        $checkemail->close();
    } else {
        $error = implode("<br>", $errors);
    }

    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

  <?php include('theme_loader.php'); ?>

  <title>TailorMade - Register</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: white;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
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

    nav a:hover, nav a.active { 
      border: 2px solid white; 
      background: rgba(255, 255, 255, 0.1);
    }

    .register-container {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-grow: 1;
      padding: 40px 20px;
    }

    form {
      background: rgba(255, 255, 255, 0.95);
      padding: 40px;
      border-radius: 25px;
      width: 550px;
      max-width: 95%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      color: #333;
    }

    form h2 {
      text-align: center;
      margin-bottom: 30px;
      letter-spacing: 2px;
      font-weight: bold;
      color: var(--theme-bg);
      font-size: 28px;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--theme-bg);
      margin: 20px 0 15px;
      padding-bottom: 8px;
      border-bottom: 2px solid var(--theme-bg);
    }

    .input-group {
      display: flex;
      flex-direction: column;
      margin-bottom: 18px;
    }

    .input-group label {
      margin-bottom: 8px;
      font-size: 13px;
      font-weight: 600;
      color: #555;
    }

    .input-group input, .input-group select {
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
      color: #333;
      background: white;
      transition: all 0.3s ease;
    }

    .input-group input:focus, .input-group select:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-row {
      display: flex;
      gap: 15px;
    }

    .form-row .input-group { flex: 1; }

    button {
      margin-top: 20px;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      padding: 15px;
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

    p a {
      color: var(--theme-bg);
      font-weight: bold;
      text-decoration: none;
    }

    p a:hover { text-decoration: underline; }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      color: #333;
      padding: 35px;
      border-radius: 20px;
      text-align: center;
      width: 90%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-content h3 {
      margin-bottom: 15px;
      font-size: 22px;
      color: var(--theme-bg);
    }

    .modal-content p {
      margin-bottom: 25px;
      color: #666;
      line-height: 1.6;
    }

    .modal-content button {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      padding: 12px 35px;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: bold;
      width: auto;
    }

    .modal-content button:hover {
      transform: translateY(-2px);
    }

    .error-text {
      color: #e74c3c;
      font-size: 11px;
      margin-top: 5px;
      display: none;
      font-weight: 500;
    }

    .address-preview {
      background: #f8f9fa;
      padding: 12px;
      border-radius: 8px;
      margin-top: 10px;
      font-size: 13px;
      color: #666;
      border-left: 3px solid var(--theme-bg);
    }

    .address-preview strong {
      color: var(--theme-bg);
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
      header {
        padding: 15px 20px;
        flex-direction: column;
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
        gap: 8px;
      }

      nav a {
        margin: 0;
        padding: 8px 15px;
        font-size: 14px;
      }

      .register-container {
        padding: 20px 10px;
      }

      form {
        padding: 30px 20px;
        width: 100%;
        max-width: 100%;
        border-radius: 20px;
      }

      form h2 {
        font-size: 24px;
        margin-bottom: 25px;
      }

      .section-title {
        font-size: 15px;
        margin: 15px 0 12px;
      }

      .input-group {
        margin-bottom: 15px;
      }

      .input-group label {
        font-size: 12px;
        margin-bottom: 6px;
      }

      .input-group input, .input-group select {
        padding: 10px 12px;
        font-size: 13px;
      }

      .form-row {
        flex-direction: column;
        gap: 0;
      }

      .form-row .input-group {
        width: 100%;
      }

      button {
        padding: 12px;
        font-size: 15px;
        margin-top: 15px;
      }

      p {
        font-size: 13px;
        margin-top: 15px;
      }

      .modal-content {
        width: 95%;
        padding: 25px 20px;
        border-radius: 15px;
      }

      .modal-content h3 {
        font-size: 20px;
        margin-bottom: 12px;
      }

      .modal-content p {
        font-size: 13px;
        margin-bottom: 20px;
      }

      .modal-content button {
        padding: 10px 25px;
        font-size: 14px;
      }

      .address-preview {
        padding: 10px;
        font-size: 12px;
      }

      .error-text {
        font-size: 10px;
      }
    }

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

      form {
        padding: 25px 15px;
        border-radius: 15px;
      }

      form h2 {
        font-size: 20px;
        margin-bottom: 20px;
        letter-spacing: 1px;
      }

      .section-title {
        font-size: 14px;
        margin: 12px 0 10px;
      }

      .input-group label {
        font-size: 11px;
      }

      .input-group input, .input-group select {
        padding: 9px 10px;
        font-size: 12px;
      }

      button {
        padding: 11px;
        font-size: 14px;
      }

      .modal-content {
        padding: 20px 15px;
      }

      .modal-content h3 {
        font-size: 18px;
      }

      .modal-content p {
        font-size: 12px;
      }

      .modal-content button {
        padding: 9px 20px;
        font-size: 13px;
      }
    }
  </style>
</head>
<body>
  <header>
      <div class="logo">
      <img src="<?php echo $business ['logo_path']; ?>" alt="TailorMade Logo">
      <span class="logo-text">TailorMade</span>
  </div>

    </div>
    <nav>
      <a href="index.php">Home</a>
      <a href="index.php#about">About Us</a>
      <a href="index.php#contact">Contact Us</a>
      <a href="customer_login.php">Login</a>
    </nav>
  </header>

  <div class="register-container">
    <form id="regForm" action="customer_registration.php" method="POST">
      <h2>✨ REGISTER NOW</h2>

      <div class="section-title">👤 Personal Information</div>

      <div class="input-group">
        <label for="fullname">Full Name *</label>
        <input type="text" id="fullname" name="fullname" placeholder="Enter Full Name" required>
      </div>

      <div class="input-group">
        <label for="email">Email Address (must be @gmail.com)</label>
        <input type="email" id="email" name="email" placeholder="example@gmail.com" required>
        <span class="error-text" id="emailError">Email must be a valid Gmail address</span>
      </div>

      <div class="input-group">
        <label for="username">Username * (must contain at least 1 number)</label>
        <input type="text" id="username" name="username" placeholder="Enter Username" required>
        <span class="error-text" id="usernameError">Username must contain at least one number</span>
      </div>

      <div class="section-title">📍 Address Information</div>

      <div class="input-group">
        <label for="city">City/Municipality (Cavite)</label>
        <select id="city" name="city" required>
          <option value="">Select City/Municipality</option>
          <option value="Alfonso">Alfonso</option>
          <option value="Amadeo">Amadeo</option>
          <option value="Bacoor">Bacoor</option>
          <option value="Carmona">Carmona</option>
          <option value="Cavite City">Cavite City</option>
          <option value="Dasmariñas">Dasmariñas</option>
          <option value="General Emilio Aguinaldo">General Emilio Aguinaldo</option>
          <option value="General Mariano Alvarez">General Mariano Alvarez</option>
          <option value="General Trias">General Trias</option>
          <option value="Imus">Imus</option>
          <option value="Indang">Indang</option>
          <option value="Kawit">Kawit</option>
          <option value="Magallanes">Magallanes</option>
          <option value="Maragondon">Maragondon</option>
          <option value="Mendez">Mendez</option>
          <option value="Naic">Naic</option>
          <option value="Noveleta">Noveleta</option>
          <option value="Rosario">Rosario</option>
          <option value="Silang">Silang</option>
          <option value="Tagaytay">Tagaytay</option>
          <option value="Tanza">Tanza</option>
          <option value="Ternate">Ternate</option>
          <option value="Trece Martires">Trece Martires</option>
        </select>
      </div>

      <div class="input-group">
        <label for="barangay">Barangay </label>
        <select id="barangay" name="barangay" required disabled>
          <option value="">Select City First</option>
        </select>
      </div>

      <div class="input-group">
        <label for="house_block">House/Block No. (Optional)</label>
        <input type="text" id="house_block" name="house_block" placeholder="e.g., Block 5 Lot 10, House #123">
      </div>

      <div class="address-preview" id="addressPreview" style="display:none;">
        <strong>Complete Address:</strong><br>
        <span id="previewText"></span>
      </div>
      

      <div class="form-row">
        <div class="input-group">
          <label for="age">Age (18+)</label>
          <input type="number" id="age" name="age" placeholder="Age" min="18" required>
          <span class="error-text" id="ageError">Must be 18 or older</span>
        </div>

        <div class="input-group">
          <label for="contact">Contact # (numbers only)</label>
          <input type="text" id="contact" name="contact" placeholder="09XXXXXXXXX" maxlength="11" required>
          <span class="error-text" id="contactError">Numbers only</span>
        </div>
      </div>

      <div class="section-title">🔒 Account Security</div>

      <div class="form-row">
        <div class="input-group">
          <label for="password">Password * (8+ chars, 1 special)</label>
          <div style="position: relative;">
            <input type="password" id="password" name="password" placeholder="Password" required style="padding-right: 45px;">
            <span class="toggle-password" onclick="togglePassword('password')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--theme-bg); font-size: 18px;">👁️</span>
          </div>
          <span class="error-text" id="passwordError">Min 8 chars, 1 special character</span>
        </div>

        <div class="input-group">
          <label for="confirmpassword">Confirm Password *</label>
          <div style="position: relative;">
            <input type="password" id="confirmpassword" name="confirmpassword" placeholder="Confirm Password" required style="padding-right: 45px;">
            <span class="toggle-password" onclick="togglePassword('confirmpassword')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--theme-bg); font-size: 18px;">👁️</span>
          </div>
        </div>
      </div>

      <div class="input-group" style="margin-top: 20px;">
        <label style="display: flex; align-items: flex-start; cursor: pointer; font-size: 14px;">
          <input type="checkbox" id="agreeTerms" name="agreeTerms" required style="margin-right: 10px; margin-top: 3px; width: auto; cursor: pointer;">
          <span>I agree to the <a href="#" onclick="showTermsModal(); return false;" style="color: var(--theme-bg); text-decoration: underline; font-weight: 600;">Terms and Conditions</a></span>
        </label>
      </div>

      <button type="submit">REGISTER</button>

      <p>Already have an account? <a href="customer_login.php">LOG IN</a></p>
    </form>
  </div>

  <!-- Error Modal -->
  <div id="errorModal" class="modal">
    <div class="modal-content">
      <h3>⚠️ Validation Error</h3>
      <p id="errorMessage"></p>
      <button onclick="closeModal('errorModal')">OK</button>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal">
    <div class="modal-content">
      <h3>✅ Success!</h3>
      <p id="successMessage"></p>
      <button onclick="window.location.href='customer_login.php'">Go to Login</button>
    </div>
  </div>

  <?php if (!empty($error)): ?>
  <script>
    document.getElementById('errorMessage').textContent = <?php echo json_encode($error); ?>;
    document.getElementById('errorModal').style.display = 'flex';
  </script>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
  <script>
    document.getElementById('successMessage').textContent = <?php echo json_encode($success); ?>;
    document.getElementById('successModal').style.display = 'flex';
  </script>
  <?php endif; ?>

  <script>
    const barangays = {

  "Alfonso": [
    "Esperanza Ilaya","Esperanza Ibaba","Kaysuyo","Marahan I","Marahan II",
    "Matanda","Magsaysay","Noblanghangin","Poblacion I","Poblacion II",
    "Poblacion III","Poblacion IV","Pupsuan","Santol","Sikat","Sulsugin",
    "Taywanak Ilaya","Taywanak Ibaba","Upli"
  ],

  "Amadeo": [
    "Banaybanay","Barangay 1 (Pob.)","Barangay 2 (Pob.)","Barangay 3 (Pob.)",
    "Barangay 4 (Pob.)","Barangay 5 (Pob.)","Barangay 6 (Pob.)",
    "Barangay 7 (Pob.)","Barangay 8 (Pob.)","Buho","Dagatan","Halang",
    "Loma","Maymangga","Minantok Kanluran","Minantok Silangan","Pangil",
    "Salaban","Tamacan","Talanco"
  ],

  "Bacoor": [
    "Alima","Aniban I","Aniban II","Aniban III","Aniban IV","Aniban V","Banalo",
    "Bayanan","Campo Santo","Daang Bukid","Digman","Habay I","Habay II","Kaingin",
    "Ligas I","Ligas II","Ligas III","Mabolo I","Mabolo II","Mabolo III",
    "Maliksi I","Maliksi II","Maliksi III","Mambog I","Mambog II",
    "Mambog III","Mambog IV","Mambog V","Niog I","Niog II","Niog III",
    "Panapaan I","Panapaan II","Panapaan III","Panapaan IV","Panapaan V",
    "Panapaan VI","Panapaan VII","Panapaan VIII","Real I","Real II","Salinas I",
    "Salinas II","Salinas III","Salinas IV","Sineguelasan","Tabing Dagat","Talaba I",
    "Talaba II","Talaba III","Talaba IV","Talaba V","Talaba VI","Talaba VII",
    "Zapote I","Zapote II","Zapote III","Zapote IV","Zapote V"
  ],

  "Carmona": [
    "Bancal","Barangay 1 (Pob.)","Barangay 2 (Pob.)","Barangay 3 (Pob.)",
    "Barangay 4 (Pob.)","Barangay 5 (Pob.)","Barangay 6 (Pob.)",
    "Cabilang Baybay","Lantic","Mabuhay","Milagrosa","Mamarlao"
  ],

  "Cavite City": [
    "Barangay 1","Barangay 2","Barangay 3","Barangay 4","Barangay 5",
    "Barangay 6","Barangay 7","Barangay 8","Barangay 9","Barangay 10",
    "Barangay 11","Barangay 12","Barangay 13","Barangay 14","Barangay 15",
    "Barangay 16","Barangay 17","Barangay 18","Barangay 19","Barangay 20",
    "Barangay 21","Barangay 22","Barangay 23","Barangay 24","Barangay 25",
    "Barangay 26","Barangay 27","Barangay 28","Barangay 29","Barangay 30",
    "Barangay 31","Barangay 32","Barangay 33","Barangay 34","Barangay 35",
    "Barangay 36","Barangay 37","Barangay 38","Barangay 39","Barangay 40",
    "Barangay 41","Barangay 42","Barangay 43","Barangay 44","Barangay 45",
    "Barangay 46","Barangay 47","Barangay 48","Barangay 49","Barangay 50",
    "Barangay 51","Barangay 52","Barangay 53","Barangay 54","San Antonio"
  ],

  "Dasmariñas": [
    "Burol I","Burol II","Burol III","Emilio Aguinaldo","Fatima I","Fatima II",
    "Fatima III","H-2","Langkaan I","Langkaan II","Luzviminda I","Paliparan I",
    "Paliparan II","Paliparan III","Sabang","Salitran I","Salitran II",
    "Salitran III","Salitran IV","San Agustin I","San Agustin II","San Agustin III",
    "San Andres I","San Andres II","San Antonio I","San Antonio II","San Francisco I",
    "San Francisco II","San Isidro Labrador","San Jose I","San Lorenzo Ruiz I",
    "San Lorenzo Ruiz II","San Manuel I","San Mateo","San Miguel I","San Miguel II",
    "San Miguel III","San Nicolas I","San Nicolas II","San Roque","Santa Cristina I",
    "Santa Cristina II","Santa Cruz I","Santa Cruz II","Santa Fe","Santa Lucia",
    "Santa Maria","Santo Niño I","Santo Niño II","Victoria Reyes","Zone I","Zone II",
    "Zone III","Zone IV"
  ],

  "General Emilio Aguinaldo": [
    "Bactasan","Castaneda","Ipil","Kaypaaba","Lumipa","Poblacion I","Poblacion II",
    "Poblacion III"
  ],

  "General Mariano Alvarez": [
    "Cabilang Baybay","Filing","Fuga","General Mariano Alvarez","Macaria","Nicolasa",
    "Poblacion","San Francisco","San Gabriel","San Jose","San Juan","San Pedro",
    "San Rafael","San Isidro","San Gabriel","Santa Clara"
  ],

  "General Trias": [
    "Alingaro","Arnaldo","Bacao I","Bacao II","Bagumbayan","Biclatan","Buenavista I",
    "Buenavista II","Corregidor","Dulong Bayan","Gov. Ferrer I","Gov. Ferrer II",
    "Manggahan","Navarro","Pasong Camachile I","Pasong Camachile II","Pasong Kawayan I",
    "Pasong Kawayan II","Pinagtipunan","Prinza","Sampalucan","San Francisco","San Juan 1",
    "San Juan 2","Tapia"
  ],

  "Imus": [
    "Alapan I-A","Alapan I-B","Alapan II-A","Alapan II-B","Anabu I-A","Anabu I-B",
    "Anabu II-A","Anabu II-B","Anabu I-C","Anabu II-C","Anabu II-D","Bagong Silang",
    "Bayan Luma I","Bayan Luma II","Bayan Luma III","Bayan Luma IV","Bayan Luma V",
    "Bayan Luma VI","Bayan Luma VII","Bayan Luma VIII","Bucandala I","Bucandala II",
    "Bucandala III","Bucandala IV","Buhay na Tubig","Carsadang Bago I","Carsadang Bago II",
    "Green Estate","Magdalo","Maharlika","Malagasang I-A","Malagasang I-B",
    "Malagasang I-C","Malagasang II-A","Malagasang II-B","Malagasang II-C",
    "Medicion I-A","Medicion I-B","Medicion I-C","Medicion I-D","Pag-Asa I","Pag-Asa II",
    "Pag-Asa III","Palico I","Palico II","Palico III","Palico IV","Pinagbuklod",
    "Poblacion I-A","Poblacion I-B","Poblacion II-A","Poblacion II-B","Poblacion III-A",
    "Poblacion III-B","Poblacion IV-A","Poblacion IV-B","Tanzang Luma I","Tanzang Luma II",
    "Tanzang Luma III","Tanzang Luma IV","Tanzang Luma V","Toclong I-A","Toclong I-B"
  ],

  "Indang": [
    "Agus-us","Alulod","Banaba Cerca","Banaba Lejos","Buna Cerca","Buna Lejos I",
    "Buna Lejos II","Calumpang Cerca","Calumpang Lejos","Carasuchi","Daine I","Daine II",
    "Guyam Malaki","Guyam Munti","Harasan","Kayquit I","Kayquit II","Kayquit III",
    "Limbon","Lumampong Balagbag","Lumampong Halayhay","Mahabangkahoy Cerca",
    "Mahabangkahoy Lejos","Mataas na Lupa","Pulo","Tambo Balagbag","Tambo Kulit",
    "Tambo Ilaya I","Tambo Ilaya II","Tambo Ilaya III"
  ],

  "Kawit": [
    "Balsahan - Bisita","Batong Dalig","Binakayan - Aplaya","Binakayan - Kawit","Congbalay-Legaspi",
    "Gahak","Kaingen","Magdalo","Marulas","Panamitan","Pulvorista","San Sebastian",
    "Samala - Marquez","Santa Isabel","Tabon","Tramo - Bancaan","Toclong"
  ],

  "Magallanes": [
    "Bendita I","Bendita II","Kabulusan","Medina","Pacheco","Poblacion I","Poblacion II",
    "Poblacion III","Pulo","San Agustin","Tua"
  ],

  "Maragondon": [
    "Bucal I","Bucal II","Bucal III","Caingin","Garita I","Garita II",
    "Pantihan I","Pantihan II","Pantihan III","Pantihan IV","Poblacion I",
    "Poblacion II","Poblacion III","San Miguel I","San Miguel II","Talipusngo"
  ],

  "Mendez": [
    "Anuling Cerca I","Anuling Cerca II","Anuling Lejos I","Anuling Lejos II",
    "Asis","Banayad","Bukal","Galicia","Mendez North","Mendez South","Palocpoc I",
    "Palocpoc II","Panungyan I","Panungyan II","Poblacion I","Poblacion II","Poblacion III"
  ],

  "Naic": [
    "Bucana Malaki","Bucana Sasahan","Calubcub I","Calubcub II","Capt. C. Nazareno",
    "Gomez-Zamora", "Halang", "Humbac","Ibayo Estacion","Kanluran","Labac","Makina",
    "Molino","Munting Mapino","Palangue I","Palangue II","Palangue III",
    "Sabang","San Roque","Santulan","Timalan A","Timalan B","Villa Apolonia"
  ],

  "Noveleta": [
    "Magdiwang","Poblacion","Salcedo I","Salcedo II","San Antonio I","San Antonio II",
    "San Jose I","San Jose II","Santa Rosa I","Santa Rosa II"
  ],

  "Rosario": [
    "Bagbag I","Bagbag II","Kanluran","Ligtong I","Ligtong II","Ligtong III","Ligtong IV",
    "Sapa I","Sapa II","Silangan","Tejero","Wawa I","Wawa II"
  ],

  "Silang": [
    "Adlas","Anuling","Balete I","Balete II","Banaba","Batas","Biga I","Biga II",
    "Biluso","Bulihan","Buho","Hoyo","Iba","Inchican","Kalubkob","Kaong",
    "Lalaan I","Lalaan II","Litlit","Lumil","Maguyam","Malabag","Malaking Tatyao",
    "Mataas na Burol","Munting Ilog","Paligawan","Pasong Langka","Pooc I","Pooc II",
    "Pulong Bunga","Puting Kahoy","Sabutan","San Miguel","Santol"
  ],

  "Tagaytay": [
    "Asisan","Bagong Tubig","Dapdap East","Dapdap West","Francisco","Iruhin East",
    "Iruhin West","Kaybagal East","Kaybagal North","Kaybagal South","Maharlika East",
    "Maharlika West","Maitim II Central","Maitim II East","Maitim II West","Mendez Crossing East",
    "Mendez Crossing West","Neogan","Patutong Malaki North","Patutong Malaki South",
    "Sungay East","Sungay West","Tolentino East","Tolentino West","Zambal"
  ],

  "Tanza": [
    "Amaya I","Amaya II","Amaya III","Amaya IV","Amaya V","Amaya VI","Amaya VII",
    "Bagtas","Biga", "Biwas","Bucal","Calibuyo","Capipisa I","Capipisa II","Daang Amaya I",
    "Daang Amaya II","Daang Amaya III","Julugan I","Julugan II","Julugan III",
    "Julugan IV","Julugan V","Julugan VI","Julugan VII","Julugan VIII","Mulawin",
    "Punta","Sahud - Ulan","Sanja Mayor","Tres Cruses"
  ],

  "Ternate": [
    "Bucana","Poblacion I","Poblacion II","Poblacion III","San Juan I","San Juan II",
    "San Juan III","Santa Rosa I","Santa Rosa II"
  ],

  "Trece Martires": [
    "Aguado","Cabezas","Cabuco","Conchu","De Ocampo","Gregorio","Lapidario",
    "Luciano","Osorio","Perez","Sagbat","San Agustin","San Francisco"
  ]

};

    const form = document.getElementById('regForm');
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
    const houseBlockInput = document.getElementById('house_block');
    const addressPreview = document.getElementById('addressPreview');
    const previewText = document.getElementById('previewText');

    // Populate barangay dropdown when city is selected
    citySelect.addEventListener('change', function() {
      const selectedCity = this.value;
      barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
      
      if (selectedCity && barangays[selectedCity]) {
        barangaySelect.disabled = false;
        barangays[selectedCity].forEach(function(barangay) {
          const option = document.createElement('option');
          option.value = barangay;
          option.textContent = barangay;
          barangaySelect.appendChild(option);
        });
      } else {
        barangaySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="">Select City First</option>';
      }
      
      updateAddressPreview();
    });

    // Update address preview when any field changes
    barangaySelect.addEventListener('change', updateAddressPreview);
    houseBlockInput.addEventListener('input', updateAddressPreview);

    function updateAddressPreview() {
      const city = citySelect.value;
      const barangay = barangaySelect.value;
      const houseBlock = houseBlockInput.value.trim();
      
      if (city && barangay) {
        let address = 'Cavite, ' + city + ', ' + barangay;
        if (houseBlock) {
          address += ', ' + houseBlock;
        }
        previewText.textContent = address;
        addressPreview.style.display = 'block';
      } else {
        addressPreview.style.display = 'none';
      }
    }

    // Real-time validation
    document.getElementById('email').addEventListener('blur', function() {
      const email = this.value;
      const error = document.getElementById('emailError');
      if (!email.match(/^[a-zA-Z0-9._%+-]+@gmail\.com$/)) {
        error.style.display = 'block';
      } else {
        error.style.display = 'none';
      }
    });

    document.getElementById('username').addEventListener('blur', function() {
      const username = this.value;
      const error = document.getElementById('usernameError');
      if (!username.match(/[0-9]/)) {
        error.style.display = 'block';
      } else {
        error.style.display = 'none';
      }
    });

    document.getElementById('age').addEventListener('blur', function() {
      const age = parseInt(this.value);
      const error = document.getElementById('ageError');
      if (age < 18) {
        error.style.display = 'block';
      } else {
        error.style.display = 'none';
      }
    });

    document.getElementById('contact').addEventListener('input', function() {
      this.value = this.value.replace(/[^0-9]/g, '');
      const error = document.getElementById('contactError');
      if (!/^[0-9]+$/.test(this.value) && this.value !== '') {
        error.style.display = 'block';
      } else {
        error.style.display = 'none';
      }
    });

    document.getElementById('password').addEventListener('blur', function() {
      const password = this.value;
      const error = document.getElementById('passwordError');
      const specialChars = (password.match(/[!@#$%^&*(),.?":{}|<>]/g) || []).length;
      if (password.length < 8 || specialChars < 1) {
        error.style.display = 'block';
      } else {
        error.style.display = 'none';
      }
    });

    // Form submission validation
    form.addEventListener('submit', function(e) {
      const fullname = document.getElementById('fullname').value.trim();
      const email = document.getElementById('email').value.trim();
      const username = document.getElementById('username').value.trim();
      const city = document.getElementById('city').value;
      const barangay = document.getElementById('barangay').value;
      const age = parseInt(document.getElementById('age').value);
      const contact = document.getElementById('contact').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmpassword').value;

      let errors = [];

      // Check for empty required fields
      if (!fullname) {
        errors.push('❌ Full name is required.');
      }

      if (!email) {
        errors.push('❌ Email address is required.');
      } else if (!email.match(/^[a-zA-Z0-9._%+-]+@gmail\.com$/)) {
        errors.push('❌ Email must be a valid Gmail address ending with @gmail.com.');
      }

      if (!username) {
        errors.push('❌ Username is required.');
      } else if (!username.match(/[0-9]/)) {
        errors.push('❌ Username must contain at least one number.');
      }

      if (!city) {
        errors.push('❌ City/Municipality is required.');
      }

      if (!barangay) {
        errors.push('❌ Barangay is required.');
      }

      if (!age || isNaN(age)) {
        errors.push('❌ Age is required.');
      } else if (age < 18) {
        errors.push('❌ You must be at least 18 years old to register.');
      }

      if (!contact) {
        errors.push('❌ Contact number is required.');
      } else if (!/^[0-9]+$/.test(contact)) {
        errors.push('❌ Contact number must contain numbers only.');
      }

      if (!password) {
        errors.push('❌ Password is required.');
      } else {
        // Password validation - at least 8 chars and 1 special character
        const specialChars = (password.match(/[!@#$%^&*(),.?":{}|<>]/g) || []).length;
        if (password.length < 8) {
          errors.push('❌ Password must be at least 8 characters long.');
        }
        if (specialChars < 1) {
          errors.push('❌ Password must contain at least one special character.');
        }
      }

      if (!confirmPassword) {
        errors.push('❌ Please confirm your password.');
      } else if (password !== confirmPassword) {
        errors.push('❌ Passwords do not match!');
      }

      // If there are any errors, prevent submission and show modal
      if (errors.length > 0) {
        e.preventDefault();
        showModal('errorModal', errors.join('<br>'));
        return;
      }
    });

    function showModal(modalId, message) {
      document.getElementById(modalId === 'errorModal' ? 'errorMessage' : 'successMessage').innerHTML = message;
      document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    // Password toggle function
    function togglePassword(fieldId) {
      const field = document.getElementById(fieldId);
      const toggle = field.nextElementSibling;
      
      if (field.type === 'password') {
        field.type = 'text';
        toggle.textContent = '🙈';
      } else {
        field.type = 'password';
        toggle.textContent = '👁️';
      }
    }

    // Terms and Conditions Functions
    function showTermsModal() {
      document.getElementById('termsModal').style.display = 'block';
    }

    function closeTermsModal() {
      document.getElementById('termsModal').style.display = 'none';
    }

    function acceptTerms() {
      document.getElementById('agreeTerms').checked = true;
      closeTermsModal();
    }

    // Form validation for Terms checkbox
    document.getElementById('regForm').addEventListener('submit', function(e) {
      if (!document.getElementById('agreeTerms').checked) {
        e.preventDefault();
        alert('You must agree to the Terms and Conditions to register.');
        return false;
      }
    });
  </script>

  <!-- Terms and Conditions Modal -->
  <div id="termsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; overflow:auto; padding:20px;">
    <div style="background:white; max-width:700px; margin:30px auto; padding:30px; border-radius:12px; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
      <button onclick="closeTermsModal()" style="position:absolute; top:15px; right:20px; background:none; border:none; font-size:32px; cursor:pointer; font-color:var(--theme-bg); font-weight:300; line-height:1; padding:0; width:auto; transition:all 0.2s ease;">&times;</button>
      
      <h2 style="color:#333; margin-bottom:10px; font-size:24px;">TailorMade - Terms and Conditions</h2>
      <p style="color:#666; margin-bottom:20px; font-size:14px;"><strong>Last Updated:</strong> November 26, 2025</p>
      
      <div style="max-height:400px; overflow-y:auto; padding-right:15px; color:#333; line-height:1.7; font-size:14px;">
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">1. Acceptance of Terms</h3>
        <p style="margin-bottom:12px;">By registering for and using TailorMade's services, you agree to be bound by these Terms and Conditions. If you do not agree to these terms, please do not use our services.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">2. User Account</h3>
        <p style="margin-bottom:12px;">You must be at least 18 years old to register for an account. You are responsible for maintaining the confidentiality of your account credentials and agree to provide accurate, current, and complete information during registration. You must notify us immediately of any unauthorized use of your account.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">3. Service Orders</h3>
        <p style="margin-bottom:12px;">All orders are subject to acceptance and availability. The minimum order quantity is 500 garments and the maximum is 3,000 garments. Orders of 500-1,500 garments require at least 30 days for completion, while orders of 1,501-3,000 garments require at least 60 days for completion. You can only have one active order at a time, and orders can only be cancelled during the "Order Confirmation" status.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">4. Payment Terms</h3>
        <p style="margin-bottom:12px;">All prices are quoted in Philippine Pesos (₱). Payment terms will be communicated upon order confirmation. All prices are subject to change without prior notice.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">5. Privacy and Data Protection</h3>
        <p style="margin-bottom:12px;">We collect and store your personal information including your name, email, address, and contact number for order processing purposes. Your information will not be shared with third parties without your consent. We implement security measures to protect your data, and you have the right to request access to or deletion of your personal data at any time.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">6. Password Security</h3>
        <p style="margin-bottom:12px;">For security reasons, customers cannot reset passwords online. To reset your password, you must contact the business owner directly. This policy ensures the security of your account and order information.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">7. Order Tracking</h3>
        <p style="margin-bottom:12px;">You can track your order status through your account dashboard. Order statuses include Order Confirmation, Order Processing, Quality Check, Order Pack, Out for Delivery, and Completed. You will be notified of significant status changes throughout the production process.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">8. Quality Assurance</h3>
        <p style="margin-bottom:12px;">All garments undergo quality inspection before delivery. Defective items will be replaced or refunded according to our quality policy. Quality concerns must be reported within 7 days of delivery to be eligible for replacement or refund.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">9. Cancellation Policy</h3>
        <p style="margin-bottom:12px;">Orders can only be cancelled during the "Order Confirmation" status. Once production begins, orders cannot be cancelled. Cancelled orders will be marked as "Cancelled" in your order history for your records.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">10. Limitation of Liability</h3>
        <p style="margin-bottom:12px;">TailorMade is not liable for delays caused by circumstances beyond our control. Our liability is limited to the value of your order. We are not responsible for errors in specifications provided by the customer.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">11. Intellectual Property</h3>
        <p style="margin-bottom:12px;">All content on this platform is owned by TailorMade. You may not reproduce, distribute, or create derivative works without permission. Custom designs submitted by customers remain the property of the customer.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">12. Termination</h3>
        <p style="margin-bottom:12px;">We reserve the right to terminate accounts that violate these terms. You may request account deletion by contacting us directly. Terminated accounts will lose access to order history and tracking information.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">13. Changes to Terms</h3>
        <p style="margin-bottom:12px;">We reserve the right to modify these terms at any time. Continued use of our services constitutes acceptance of modified terms. Significant changes will be communicated via email to all registered users.</p>
        
        <h3 style="color:#333; font-size:16px; margin-top:15px; margin-bottom:8px;">14. Contact Information</h3>
        <p style="margin-bottom:12px;">For questions about these Terms and Conditions, please contact the business owner through the contact information provided on our website.</p>
        
        <hr style="margin:20px 0; border:none; border-top:1px solid #ddd;">
        
        <p style="font-weight:600; text-align:center; font-size:15px;">By clicking "I Agree and Accept", you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.</p>
      </div>
      
      <button onclick="acceptTerms()" type="button" style="width:100%; margin-top:20px; padding:14px; background:linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:15px;">I Agree and Accept</button>
    </div>
  </div>

</body>
</html>
