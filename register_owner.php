<?php
include 'connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Collect inputs
    $firstName = mysqli_real_escape_string($conn, $_POST['first_name']);
    $lastName = mysqli_real_escape_string($conn, $_POST['last_name']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $age = intval($_POST['age']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Get business ID from session
    if (!isset($_SESSION['business_id'])) {
        die('Business info not found. Please add your business first.');
    }
    $businessId = $_SESSION['business_id'];

    // Handle profile photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = basename($_FILES['profile_photo']['name']);
        $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $newFileName = uniqid('profile_', true) . '.' . $fileExt;
        $destination = $uploadDir . $newFileName;

        $allowedTypes = ['jpg','jpeg','png','gif'];
        if (!in_array($fileExt, $allowedTypes)) die('Only JPG, JPEG, PNG, GIF allowed.');

        if (!move_uploaded_file($fileTmpPath, $destination)) die('Error uploading profile photo.');
    } else {
        die('Profile photo is required.');
    }

    // Insert owner
    $sql = "INSERT INTO owner
        (first_name, last_name, gender, age, contact, username, email, password, profile_photo, business_id, created_at, updated_at)
        VALUES 
        ('$firstName', '$lastName', '$gender', $age, '$contact', '$username', '$email', '$password', '$destination', $businessId, NOW(), NOW())";

    if (mysqli_query($conn, $sql)) {
        echo "<p>Owner registered successfully!</p>";
        echo "<p><a href='login.html'>Go to Login</a></p>";
        unset($_SESSION['business_id']); // clear session
    } else {
        echo "Database error: " . mysqli_error($conn);
    }

    mysqli_close($conn);

} else {
    header('Location: register_owner.html');
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register Business - Owner's Info</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      min-height: 100vh;
      overflow-x: hidden;
    }

    .form-wrapper {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 25px;
      padding: 50px 60px;
      max-width: 950px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      text-align: left;
      color: #333;
    }

    h2 {
      text-align: center;
      margin-bottom: 35px;
      letter-spacing: 2px;
      color: #667eea;
      font-weight: bold;
      font-size: 32px;
    }

    .section-title {
      font-style: italic;
      font-size: 15px;
      margin-bottom: 25px;
      display: block;
      color: #667eea;
      font-weight: 600;
    }

    label {
      font-size: 14px;
      color: #555;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .form-control {
      border-radius: 10px;
      font-size: 15px;
      background: #fff;
      color: #333;
      border: 2px solid #e0e0e0;
      box-shadow: none;
      height: 48px;
      padding: 14px 18px;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      border-color: #667eea;
      outline: none;
    }

    .form-check-input:checked {
      background-color: #667eea;
      border-color: #667eea;
    }

    .form-check-label {
      color: #555;
    }

    .photo-box {
      display: flex;
      flex-direction: column;
      align-items: center;
      background: #fff;
      border-radius: 15px;
      padding: 25px;
      text-align: center;
      color: #333;
      font-size: 14px;
      width: 220px;
      height: auto;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      border: 2px solid #e0e0e0;
    }

    .photo-box img {
      width: 120px;
      height: 120px;
      margin-bottom: 15px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #667eea;
    }

    .photo-box span {
      font-weight: 600;
      color: #667eea;
      margin-bottom: 10px;
    }

    .btn, .upload-btn {
      margin-top: 20px;
      padding: 14px 24px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-size: 14px;
    }

    .btn:hover, .upload-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    }

    #photoInput {
      display: none;
    }

    @media (max-width: 768px) {
      .form-wrapper {
        padding: 30px;
      }

      .photo-box {
        width: 180px;
        height: auto;
        margin-top: 25px;
      }
    }

    .is-invalid {
      border-color: #dc3545 !important;
      box-shadow: 0 0 3px rgba(220, 53, 69, 0.4);
    }

    .alert {
      padding: 15px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      font-weight: 500;
    }

    .alert-success {
      background-color: #d1e7dd;
      color: #0f5132;
      border: 2px solid #badbcc;
    }

    .alert-danger {
      background-color: #f8d7da;
      color: #842029;
      border: 2px solid #f5c2c7;
    }

    .text-danger {
      color: #dc3545 !important;
      font-weight: 500;
    }

  </style>
</head>
<body>
  <div class="container py-5">
    <div class="form-wrapper mx-auto">
      <h2>BUSINESS OWNER'S INFORMATION</h2>
      <span class="section-title">OWNER BASIC DETAILS:</span>

      <form id="ownerForm" action="register_owner.php" method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="business_id" id="businessIdInput" value="" />

        <div class="row g-4 align-items-start">
          <!-- Left side -->
          <div class="col-md-8">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="first_name">First Name:</label>
                <input type="text" class="form-control" id="first_name" name="first_name" required>
              </div>
              <div class="col-md-6">
                <label for="last_name">Last Name:</label>
                <input type="text" class="form-control" id="last_name" name="last_name" required>
              </div>

              <div class="col-md-6">
                <label>Gender:</label>
                <div class="d-flex gap-3 ms-5">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="gender" value="Male" id="male" required>
                    <label class="form-check-label" for="male">Male</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="gender" value="Female" id="female" required>
                    <label class="form-check-label" for="female">Female</label>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <label for="age">Age:</label>
                <input type="number" class="form-control" id="age" name="age" required>
              </div>

              <div class="col-12">
                <label for="address">Address:</label>
                <input type="text" class="form-control" id="address" name="address" required>
              </div>

              <div class="col-md-6">
                <label for="contact">Contact Number:</label>
                <input type="text" class="form-control" id="contact" name="contact" required>
              </div>

              <div class="col-md-6">
                <label for="username">Username:</label>
                <input type="text" class="form-control" id="username" name="username" required>
                <small id="usernameMsg" class="text-danger"></small>
              </div>

              <div class="col-12">
                <label for="email">Email:</label>
                <input type="email" class="form-control" id="email" name="email" required>
              </div>

              <div class="col-md-6">
                <label for="ownerPassword">Password:</label>
                <input type="password" class="form-control" id="ownerPassword" name="password" minlength="8" required>
              </div>
              <div class="col-md-6">
                <label for="confirmPassword">Confirm Password:</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" minlength="8" required>
              </div>
            </div>
          </div>

          <!-- Right side photo -->
          <div class="col-md-4 d-flex justify-content-center">
            <div class="photo-box mt-0">
              <img id="photoPreview" src="https://cdn-icons-png.flaticon.com/512/847/847969.png" alt="Photo Preview" />
              <span>Profile</span>
              <button type="button" class="upload-btn mt-2" onclick="document.getElementById('photoInput').click();">Add Photo</button>
              <input type="file" name="photo" id="photoInput" accept="image/*" onchange="previewPhoto(event)" />
            </div>
          </div>
        </div>

        <button type="submit" class="btn mt-4 w-100">SAVE & CONTINUE</button>
      </form>
    </div>
  </div>

 <script>
  // --- Auto-fill business ID from URL ---
  const params = new URLSearchParams(window.location.search);
  const businessId = params.get('business_id');
  if (businessId) document.getElementById('businessIdInput').value = businessId;

  // --- Preview selected photo ---
  function previewPhoto(event) {
    const file = event.target.files[0];
    if (file) {
      document.getElementById('photoPreview').src = URL.createObjectURL(file);
    }
  }

  const form = document.getElementById('ownerForm');
  const usernameInput = document.getElementById('username');
  const usernameMsg = document.createElement('div');
  const pw = document.getElementById('ownerPassword');
  const cpw = document.getElementById('confirmPassword');
  const emailInput = document.getElementById('email');

  // Add message containers below relevant inputs
  usernameInput.insertAdjacentElement('afterend', usernameMsg);
  usernameMsg.className = 'text-danger small mt-1';

  const pwMsg = document.createElement('div');
  pw.insertAdjacentElement('afterend', pwMsg);
  pwMsg.className = 'text-danger small mt-1';

  const cpwMsg = document.createElement('div');
  cpw.insertAdjacentElement('afterend', cpwMsg);
  cpwMsg.className = 'text-danger small mt-1';

  const emailMsg = document.createElement('div');
  emailInput.insertAdjacentElement('afterend', emailMsg);
  emailMsg.className = 'text-danger small mt-1';

  const formMsg = document.createElement('div');
  form.insertAdjacentElement('afterbegin', formMsg);
  formMsg.className = 'alert text-center fw-semibold';
  formMsg.style.display = 'none';

  // --- Username validation ---
  usernameInput.addEventListener('input', async () => {
    const username = usernameInput.value.trim();
    const usernamePattern = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]+$/;

    if (!usernamePattern.test(username)) {
      usernameMsg.textContent = "Username must contain both letters and numbers.";
      usernameInput.classList.add("is-invalid");
      return;
    }

    // AJAX check for uniqueness
    const response = await fetch('check_username.php?username=' + encodeURIComponent(username));
    const result = await response.json();

    if (result.exists) {
      usernameMsg.textContent = "Username is already taken.";
      usernameInput.classList.add("is-invalid");
    } else {
      usernameMsg.textContent = "";
      usernameInput.classList.remove("is-invalid");
    }
  });

  // --- Password validation ---
  pw.addEventListener('input', () => {
    const valid = /^(?=.*[A-Z])(?=.*\d).{8,}$/.test(pw.value);
    if (!valid) {
      pwMsg.textContent = "Password must have at least 8 characters, 1 uppercase letter, and 1 number.";
      pw.classList.add("is-invalid");
    } else {
      pwMsg.textContent = "";
      pw.classList.remove("is-invalid");
    }
  });

  // --- Confirm password validation ---
  cpw.addEventListener('input', () => {
    if (pw.value !== cpw.value) {
      cpwMsg.textContent = "Passwords do not match.";
      cpw.classList.add("is-invalid");
    } else {
      cpwMsg.textContent = "";
      cpw.classList.remove("is-invalid");
    }
  });

  // --- Handle form submission ---
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Reset messages
    formMsg.style.display = 'none';
    formMsg.textContent = '';
    formMsg.classList.remove('alert-danger', 'alert-success');

    const formData = new FormData(form);

    try {
      const response = await fetch(form.action, { method: 'POST', body: formData });
      const result = await response.json();

      if (result.status === "success") {
        formMsg.textContent = result.message || "✅ Registration successful!";
        formMsg.classList.add('alert-success');
        formMsg.style.display = 'block';

        form.reset();
        document.getElementById('photoPreview').src = "https://cdn-icons-png.flaticon.com/512/847/847969.png";

        setTimeout(() => window.location.href = "superadmin_dashboard.php", 1000);
      } else {
        // Handle specific field errors
        const msg = result.message.toLowerCase();
        formMsg.style.display = 'block';
        formMsg.classList.add('alert-danger');
        formMsg.textContent = "⚠️ " + result.message;

        if (msg.includes('username')) usernameMsg.textContent = result.message;
        else usernameMsg.textContent = "";

        if (msg.includes('password')) pwMsg.textContent = result.message;
        else pwMsg.textContent = "";

        if (msg.includes('email')) emailMsg.textContent = result.message;
        else emailMsg.textContent = "";

        if (msg.includes('match')) {
          cpwMsg.textContent = "Passwords do not match.";
          pw.value = "";
          cpw.value = "";
        }
      }
    } catch (err) {
      console.error(err);
      formMsg.textContent = "🚫 Server connection error. Please try again.";
      formMsg.classList.add('alert-danger');
      formMsg.style.display = 'block';
    }
  });
</script>

</body>
</html>

