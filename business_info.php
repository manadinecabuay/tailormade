<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'connection.php';
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- BUSINESS INFO --- //
    $businessName = mysqli_real_escape_string($conn, $_POST['business_name'] ?? '');
    $aboutUs = mysqli_real_escape_string($conn, $_POST['about_us'] ?? '');
    $contact = mysqli_real_escape_string($conn, $_POST['business_contact'] ?? '');
    $businessEmail = mysqli_real_escape_string($conn, $_POST['business_email'] ?? '');

    // Check required fields
    if ($businessName === "" || $aboutUs === "" || $contact === "" || $businessEmail === "") {
        die('Please fill out all required business fields.');
    }

    // --- HANDLE BUSINESS LOGO UPLOAD --- //
    $logo_path = null;
    $upload_dir = 'uploads/';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['logo_path']) && $_FILES['logo_path']['error'] === UPLOAD_ERR_OK) {
        $logo_name = basename($_FILES['logo_path']['name']);
        $tmp_name = $_FILES['logo_path']['tmp_name'];
        $ext = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($ext, $allowed)) {
            die("Invalid logo format. Allowed: jpg, jpeg, png, gif.");
        }

        $logo_path = $upload_dir . uniqid("logo_", true) . "." . $ext;

        if (!move_uploaded_file($tmp_name, $logo_path)) {
            die("Failed to upload logo.");
        }
    } else {
        // Default logo if none uploaded
        $logo_path = "uploads/default-logo.png";
    }

    // --- INSERT BUSINESS INFO (SAFE) --- //
    $sqlBusiness = "
        INSERT INTO business_info 
        (business_name, logo_path, about_us, contact, email, created_at, machine_availability_message)
        VALUES 
        ('$businessName', '$logo_path', '$aboutUs', '$contact', '$businessEmail', NOW(), NULL)
    ";

    if (!mysqli_query($conn, $sqlBusiness)) {
        die('Business insert error: ' . mysqli_error($conn));
    }

    $businessId = mysqli_insert_id($conn);

    // --- OWNER INFO --- //
    $firstName = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $lastName = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $ownerContact = mysqli_real_escape_string($conn, $_POST['owner_contact'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['owner_email'] ?? '');
    $password = $_POST['owner_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');

    // --- PASSWORD VALIDATION --- //
    if ($password !== $confirmPassword) {
        die('Passwords do not match.');
    }

    if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{6,}$/', $password)) {
        die('Password must contain at least 1 uppercase letter, 1 number, and 6 characters.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // --- OPTIONAL PROFILE PHOTO --- //
    $profilePath = NULL;
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];

    if (!empty($_FILES['profile_photo']['name'])) {
        $fileName = basename($_FILES['profile_photo']['name']);
        $tmpPath = $_FILES['profile_photo']['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($ext, $allowedExt)) {
            $profilePath = $upload_dir . uniqid('profile_', true) . "." . $ext;
            move_uploaded_file($tmpPath, $profilePath);
        }
    }

    // --- CHECK DUPLICATES --- //
    $resCheck = mysqli_query($conn, "SELECT id FROM owner WHERE username='$username' OR email='$email'");
    if (mysqli_num_rows($resCheck) > 0) {
        die('Username or email already exists.');
    }

    // --- INSERT OWNER --- //
    $sqlOwner = "
        INSERT INTO owner 
        (business_id, first_name, last_name, gender, age, address, contact, username, email, password, profile_photo, created_at, updated_at)
        VALUES 
        (
            $businessId,
            '$firstName',
            '$lastName',
            '$gender',
            $age,
            '$address',
            '$ownerContact',
            '$username',
            '$email',
            '$hashedPassword',
            " . ($profilePath ? "'$profilePath'" : "NULL") . ",
            NOW(),
            NOW()
        )
    ";

    if (mysqli_query($conn, $sqlOwner)) {
        $_SESSION['user_id'] = mysqli_insert_id($conn);
        $_SESSION['role'] = 'owner';
        header('Location: owner_homepage.html');
        exit;
    } else {
        die('Owner insert error: ' . mysqli_error($conn));
    }
}

// If not POST, display the form
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Business & Owner Registration</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: #f8f9fa;
      color: #333;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }

    /* Header */
    header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 20px 0;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 40px;
    }

    .logo {
      font-size: 24px;
      font-weight: 700;
      color: white;
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }

    .logo i {
      font-size: 28px;
    }

    .back-link {
      color: white;
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 25px;
      border: 2px solid white;
      transition: all 0.3s ease;
      font-weight: 500;
    }

    .back-link:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-2px);
    }

    .container {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-grow: 1;
      padding: 60px 20px;
    }

    .form-wrapper {
      background: white;
      border-radius: 30px;
      padding: 50px;
      max-width: 700px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      color: #333;
      animation: fadeInUp 0.6s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    h2 {
      text-align: center;
      margin-bottom: 15px;
      color: #667eea;
      letter-spacing: 1px;
      font-weight: 700;
      font-size: 32px;
    }

    .subtitle {
      text-align: center;
      color: #666;
      margin-bottom: 40px;
      font-size: 16px;
    }

    .section-title {
      margin: 35px 0 25px;
      font-size: 20px;
      font-weight: 700;
      color: #667eea;
      border-bottom: 3px solid #667eea;
      padding-bottom: 10px;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      font-size: 22px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      margin-bottom: 20px;
    }

    .form-group label {
      font-weight: 600;
      margin-bottom: 8px;
      color: #555;
      font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      padding: 14px 18px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 15px;
      background: #fff;
      color: #333;
      transition: all 0.3s ease;
      font-family: 'Poppins', sans-serif;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .btn {
      display: block;
      width: 100%;
      padding: 18px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 30px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: all 0.3s ease;
      margin-top: 40px;
      font-size: 18px;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
    }

    .btn:active {
      transform: translateY(-1px);
    }

    .error-message {
      color: #dc3545;
      font-size: 13px;
      margin-top: 6px;
      display: none;
      font-weight: 500;
    }

    textarea {
      resize: vertical;
      min-height: 80px;
    }

    input[type="file"] {
      padding: 10px;
      cursor: pointer;
    }

    input[type="file"]::-webkit-file-upload-button {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      margin-right: 10px;
    }

    input[type="file"]::-webkit-file-upload-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      .container {
        padding: 30px 15px;
      }

      .form-wrapper {
        padding: 35px 25px;
        border-radius: 20px;
      }

      h2 {
        font-size: 26px;
        margin-bottom: 25px;
      }

      .section-title {
        font-size: 1.1rem;
        margin: 25px 0 15px;
      }

      .form-group {
        margin-bottom: 18px;
      }

      .form-group input,
      .form-group select,
      .form-group textarea {
        padding: 12px 16px;
        font-size: 14px;
      }

      .btn {
        padding: 14px;
        font-size: 15px;
      }
    }

    @media (max-width: 480px) {
      .container {
        padding: 20px 10px;
      }

      .form-wrapper {
        padding: 30px 20px;
        border-radius: 15px;
      }

      h2 {
        font-size: 22px;
        margin-bottom: 20px;
        letter-spacing: 1px;
      }

      .section-title {
        font-size: 1rem;
        margin: 20px 0 12px;
      }

      .form-group {
        margin-bottom: 16px;
      }

      .form-group label {
        font-size: 13px;
        margin-bottom: 6px;
      }

      .form-group input,
      .form-group select,
      .form-group textarea {
        padding: 11px 14px;
        font-size: 13px;
      }

      .btn {
        padding: 13px;
        font-size: 14px;
        margin-top: 20px;
      }

      .error-message {
        font-size: 12px;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <div class="header-content">
      <a href="tailoring_system_features.php" class="logo">
        <i class="fa-solid fa-scissors"></i>
        TailorMade
      </a>
      <a href="tailoring_system_features.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Features
      </a>
    </div>
  </header>

  <div class="container">
    <div class="form-wrapper">
      <h2><i class="fa-solid fa-building"></i> Business Registration</h2>
      <p class="subtitle">Join TailorMade and start managing your tailoring shop today!</p>

      <form id="businessForm" method="POST" enctype="multipart/form-data">
        <!-- Business Info -->
        <div class="section-title">
          <i class="fa-solid fa-store"></i> Business Information
        </div>

        <div class="form-group">
          <label for="business_name">Business Name</label>
          <input type="text" id="business_name" name="business_name" required>
        </div>

        <div class="form-group">
          <label for="logo_path">Upload Logo</label>
          <input type="file" id="logo_path" name="logo_path" accept="image/*">
        </div>

        <div class="form-group">
          <label for="about_us">About Us</label>
          <textarea id="about_us" name="about_us" rows="3" required></textarea>
        </div>

        <div class="form-group">
          <label for="business_email">Business Email</label>
          <input type="email" id="business_email" name="business_email" required>
        </div>

        <div class="form-group">
          <label for="business_contact">Business Contact</label>
          <input type="text" id="business_contact" name="business_contact" required>
        </div>

        <!-- Owner Info -->
        <div class="section-title">
          <i class="fa-solid fa-user-tie"></i> Owner Information
        </div>

        <div class="form-group">
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name" required>
        </div>

        <div class="form-group">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name" required>
        </div>

        <div class="form-group">
          <label for="gender">Gender</label>
          <select id="gender" name="gender" required>
            <option value="" selected disabled>Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>

        <div class="form-group">
          <label for="age">Age</label>
          <input type="number" id="age" name="age" min="18" required>
        </div>

        <div class="form-group">
          <label for="address">Address</label>
          <textarea id="address" name="address" rows="2" required></textarea>
        </div>

        <div class="form-group">
          <label for="owner_contact">Contact</label>
          <input type="text" id="owner_contact" name="owner_contact" required>
        </div>

        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required>
          <div id="usernameError" class="error-message">Username is already taken. Please choose another.</div>
        </div>

        <div class="form-group">
          <label for="owner_email">Email</label>
          <input type="email" id="owner_email" name="owner_email" required>
        </div>

        <div class="form-group">
          <label for="owner_password">Password</label>
          <input type="password" id="owner_password" name="owner_password" required>
          <div id="passwordError" class="error-message">
            Password must be at least 8 characters, include one uppercase letter and one number.
          </div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <div class="form-group">
          <label for="profile_photo">Upload Profile Photo</label>
          <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
        </div>

        <button type="submit" class="btn">Register</button>
      </form>
    </div>
  </div>

  <script>
    const password = document.getElementById('owner_password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordError = document.getElementById('passwordError');
    const usernameInput = document.getElementById('username');
    const usernameError = document.getElementById('usernameError');

    // Password validation
    password.addEventListener('input', () => {
      const valid = /^(?=.*[A-Z])(?=.*\d).{8,}$/.test(password.value);
      passwordError.style.display = valid ? 'none' : 'block';
    });

    // Username uniqueness check
    let usernameTimeout;
    usernameInput.addEventListener('input', () => {
      usernameError.style.display = 'none';
      clearTimeout(usernameTimeout);
      const username = usernameInput.value.trim();
      if (!username) return;
      usernameTimeout = setTimeout(() => {
        fetch('check_username.php?username=' + encodeURIComponent(username))
          .then(res => res.json())
          .then(data => {
            if (!data.available) usernameError.style.display = 'block';
          })
          .catch(console.error);
      }, 500);
    });

    // Submit validation
    document.getElementById('businessForm').addEventListener('submit', e => {
      const validPwd = /^(?=.*[A-Z])(?=.*\d).{8,}$/.test(password.value);
      if (!validPwd) {
        alert("Invalid password. Please follow the requirements.");
        e.preventDefault();
        return;
      }
      if (password.value !== confirmPassword.value) {
        alert("Passwords do not match. Please try again.");
        e.preventDefault();
      }
      if (usernameError.style.display === 'block') {
        alert("Username is already taken. Please choose another.");
        e.preventDefault();
      }
    });
  </script>
</body>
</html>
