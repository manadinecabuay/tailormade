<?php
include('connection.php');

// Fetch theme settings
$theme_query = "SELECT * FROM theme_settings WHERE id = 1 LIMIT 1";
$theme_result = $conn->query($theme_query);

if ($theme_result && $theme_result->num_rows > 0) {
    $theme = $theme_result->fetch_assoc();
} else {
    // Default theme values
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sewers / Employees</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --theme-bg: <?= $theme['bg_color'] ?>;
      --theme-font: <?= $theme['font_color'] ?>;
      --theme-button: <?= $theme['button_color'] ?>;
      --theme-container: <?= $theme['container_color'] ?>;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: white;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
    }

    /* --- SEARCH BAR --- */
    .search-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
      z-index: 10;
      margin-bottom: 30px;
    }

    .search-box {
      display: flex;
      align-items: center;
      width: 600px;
      max-width: 90%;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 50px;
      padding: 8px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .search-box input {
      flex: 1;
      border: none;
      border-radius: 50px;
      padding: 14px 20px;
      font-size: 16px;
      outline: none;
      color: #333;
      background: transparent;
      font-family: inherit;
    }

    .search-box button {
      border: none;
      border-radius: 50px;
      padding: 14px 30px;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .search-box button:hover {
      transform: scale(1.05);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    /* --- SUGGESTIONS --- */
    #suggestions {
      position: absolute;
      top: 70px;
      width: 600px;
      max-width: 90%;
      background: rgba(255, 255, 255, 0.98);
      color: #333;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
      text-align: left;
      display: none;
      max-height: 300px;
      overflow-y: auto;
      z-index: 5;
    }

    #suggestions div {
      padding: 15px 25px;
      cursor: pointer;
      transition: all 0.2s ease;
      border-bottom: 1px solid #f0f0f0;
      font-weight: 500;
    }

    #suggestions div:last-child {
      border-bottom: none;
    }

    #suggestions div:hover {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: white;
    }

    /* --- HERO SECTION --- */
    .hero-container {
      position: relative;
      width: 100%;
      display: flex;
      justify-content: center;
      align-items: center;
      transition: opacity 0.6s ease, transform 0.6s ease;
      height: 400px;
    }

    .hero-content {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 30px;
      padding: 60px 80px;
      max-width: 800px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
      text-align: center;
    }

    .hero-content h1 {
      font-size: 48px;
      font-weight: 700;
      color: white;
      margin-bottom: 20px;
      letter-spacing: 1px;
    }

    .hero-content p {
      font-size: 18px;
      color: rgba(255, 255, 255, 0.9);
      line-height: 1.6;
    }

    .hero.fade-out {
      opacity: 0;
      transform: translateY(-20px);
      pointer-events: none;
    }

    /* --- EMPLOYEE CARD --- */
    .employee-card {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: rgba(255, 255, 255, 0.95);
      padding: 40px 60px;
      border-radius: 30px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      transition: all 0.4s ease;
      width: 450px;
      max-width: 90%;
      text-align: center;
      animation: slideIn 0.5s ease;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translate(-50%, -40%); }
      to { opacity: 1; transform: translate(-50%, -50%); }
    }

    .employee-card h3 {
      color: var(--theme-bg);
      margin-bottom: 20px;
      font-size: 28px;
      font-weight: 700;
    }

    .employee-card p {
      margin: 12px 0;
      font-size: 16px;
      color: #555;
      font-weight: 500;
    }

    .employee-card p strong {
      color: #333;
    }

    .employee-card button {
      margin-top: 25px;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border: none;
      padding: 14px 35px;
      border-radius: 50px;
      cursor: pointer;
      font-weight: 700;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 15px;
    }

    .employee-card button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .loading {
      text-align: center;
      color: white;
      margin-top: 20px;
      font-size: 18px;
      font-weight: 600;
    }

    /* --- EXIT BUTTON --- */
    .exit-btn {
      position: fixed;
      bottom: 20px;
      left: 20px;
      background: rgba(220, 53, 69, 0.1);
      color: #dc3545;
      border: 2px solid #dc3545;
      padding: 10px 20px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s ease;
      z-index: 1000;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
    }

    .exit-btn:hover {
      background: #dc3545;
      color: white;
      transform: translateX(-3px);
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
    }

    .exit-btn i {
      font-size: 16px;
    }

    /* --- VERIFICATION MODAL --- */
    .modal {
      display: none;
      position: fixed;
      z-index: 2000;
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
      padding: 40px;
      border-radius: 25px;
      width: 450px;
      max-width: 90%;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.3s ease;
    }

    .modal-content h3 {
      color: #333;
      margin-bottom: 25px;
      font-size: 26px;
      font-weight: 700;
    }

    .modal-content .form-group {
      margin-bottom: 20px;
      text-align: left;
    }

    .modal-content label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #555;
      font-size: 14px;
    }

    .modal-content input {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 15px;
      transition: all 0.3s ease;
    }

    .modal-content input:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .modal-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      margin-top: 30px;
    }

    .modal-btn {
      padding: 12px 30px;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 700;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      transition: all 0.3s ease;
    }

    .btn-verify {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
    }

    .btn-verify:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
    }

    .btn-cancel {
      background: #6c757d;
      color: white;
    }

    .btn-cancel:hover {
      background: #5a6268;
      transform: translateY(-2px);
    }

    .error-message {
      color: #dc3545;
      font-size: 14px;
      margin-top: 10px;
      display: none;
      font-weight: 600;
    }

    @media (max-width: 600px) {
      .search-box, #suggestions {
        width: 90%;
      }
      .hero-content {
        padding: 40px 30px;
      }
      .employee-card {
        width: 85%;
      }
      .exit-btn {
        bottom: 15px;
        left: 15px;
        font-size: 12px;
        padding: 8px 16px;
      }
      
      .exit-btn i {
        font-size: 14px;
      }
    }
  </style>
</head>

<body>
  <!-- Search Bar -->
  <div class="search-wrapper">
    <div class="search-box">
      <input type="text" id="employeeSearch" placeholder="Enter employee name or ID" autocomplete="off">
      <button onclick="searchEmployee()">Search</button>
    </div>
    <div id="suggestions"></div>
  </div>

  <!-- Hero & Employee Display Area -->
  <div class="hero-container">
    <section class="hero" id="heroSection">
      <div class="hero-content">
        <h1>Sewers / Employees</h1>
        <p>Search for employee information easily below.</p>
      </div>
    </section>

    <div id="employeeResult"></div>
  </div>

  <div id="loading" class="loading" style="display:none;">Loading...</div>

  <!-- Exit Button -->
  <button class="exit-btn" onclick="showExitModal()">
    <i class="fa-solid fa-arrow-left"></i>
    <span>Exit</span>
  </button>

  <!-- Admin Verification Modal -->
  <div id="exitModal" class="modal">
    <div class="modal-content">
      <h3>🔒 Admin Verification Required</h3>
      <p style="color: #666; margin-bottom: 25px;">Please enter admin credentials to exit</p>
      
      <form id="verificationForm" onsubmit="verifyAdmin(event)">
        <div class="form-group">
          <label for="adminUsername">Username</label>
          <input type="text" id="adminUsername" name="username" required autocomplete="off">
        </div>
        
        <div class="form-group">
          <label for="adminPassword">Password</label>
          <input type="password" id="adminPassword" name="password" required>
        </div>
        
        <div class="error-message" id="errorMessage">Invalid credentials. Please try again.</div>
        
        <div class="modal-buttons">
          <button type="submit" class="modal-btn btn-verify">Verify & Exit</button>
          <button type="button" class="modal-btn btn-cancel" onclick="closeExitModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const input = document.getElementById('employeeSearch');
    const suggestionsBox = document.getElementById('suggestions');
    const resultDiv = document.getElementById('employeeResult');
    const loadingDiv = document.getElementById('loading');
    const heroSection = document.getElementById('heroSection');

    // --- Fetch suggestions as user types ---
    input.addEventListener('input', async () => {
      const query = input.value.trim();

      // Show hero again if input is cleared
      if (!query) {
        suggestionsBox.style.display = 'none';
        heroSection.classList.remove('fade-out');
        resultDiv.innerHTML = '';
        return;
      }

      // Only search if query has at least 1 character
      if (query.length < 1) {
        suggestionsBox.style.display = 'none';
        return;
      }

      try {
        const res = await fetch(`employee_search.php?query=${encodeURIComponent(query)}`);
        
        // Check if response is ok
        if (!res.ok) {
          console.error('Server error:', res.status, res.statusText);
          suggestionsBox.innerHTML = '<div style="color:#ff7777; cursor:default;">Server error. Please try again.</div>';
          suggestionsBox.style.display = 'block';
          return;
        }

        const data = await res.json();
        console.log('Server response:', data); // Debug log

        // Check if response contains an error
        if (data.error) {
          console.error('PHP error:', data.error);
          suggestionsBox.innerHTML = `<div style="color:#ff7777; cursor:default;">Error: ${data.error}</div>`;
          suggestionsBox.style.display = 'block';
          return;
        }

        const employees = Array.isArray(data) ? data : [];
        console.log('Found employees:', employees.length); // Debug log

        suggestionsBox.innerHTML = '';
        
        if (employees.length === 0) {
          const noResult = document.createElement('div');
          noResult.textContent = 'No employees found';
          noResult.style.color = '#999';
          noResult.style.cursor = 'default';
          suggestionsBox.appendChild(noResult);
          suggestionsBox.style.display = 'block';
        } else {
          employees.forEach(emp => {
            const div = document.createElement('div');
            div.innerHTML = `<strong>${emp.full_name}</strong> <span style="color:#999; font-size:13px;">(ID: ${emp.id})</span>`;
            div.onclick = () => {
              input.value = emp.full_name;
              suggestionsBox.style.display = 'none';
              showEmployee(emp);
            };
            suggestionsBox.appendChild(div);
          });
          suggestionsBox.style.display = 'block';
        }
      } catch (err) {
        console.error('Fetch error:', err);
        suggestionsBox.innerHTML = `<div style="color:#ff7777; cursor:default;">Network error: ${err.message}</div>`;
        suggestionsBox.style.display = 'block';
      }
    });

    // --- Enter key triggers search ---
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        searchEmployee();
      }
    });

    // --- Manual search button ---
    async function searchEmployee() {
      const query = input.value.trim();
      if (!query) return;
      loadingDiv.style.display = 'block';

      const res = await fetch(`employee_search.php?query=${encodeURIComponent(query)}`);
      const employees = await res.json();
      loadingDiv.style.display = 'none';

      if (employees.length > 0) {
        showEmployee(employees[0]);
      } else {
        resultDiv.innerHTML = `<p style="color:#ff7777;">No employee found.</p>`;
      }
    }

    // --- Show employee card and hide hero ---
    function showEmployee(emp) {
      heroSection.classList.add('fade-out');
      resultDiv.innerHTML = `
        <div class="employee-card">
          <h3>${emp.full_name}</h3>
          <p><strong>ID:</strong> ${emp.id}</p>
          <p><strong>Status:</strong> ${emp.status}</p>
          <p><strong>Work Date:</strong> ${emp.work_date}</p>
          <button onclick="window.location.href='employee_record.php?id=${emp.id}'">View Record</button>
        </div>`;
    }

    // --- Close suggestions when clicking outside ---
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.search-wrapper')) {
        suggestionsBox.style.display = 'none';
      }
    });

    // --- Prevent suggestions from closing when clicking inside ---
    suggestionsBox.addEventListener('click', (e) => {
      e.stopPropagation();
    });

    // --- Exit Modal Functions ---
    function showExitModal() {
      document.getElementById('exitModal').style.display = 'flex';
      document.getElementById('errorMessage').style.display = 'none';
      document.getElementById('verificationForm').reset();
    }

    function closeExitModal() {
      document.getElementById('exitModal').style.display = 'none';
    }

    async function verifyAdmin(event) {
      event.preventDefault();
      
      const username = document.getElementById('adminUsername').value;
      const password = document.getElementById('adminPassword').value;
      const errorMsg = document.getElementById('errorMessage');
      
      try {
        const formData = new FormData();
        formData.append('username', username);
        formData.append('password', password);
        
        const response = await fetch('verify_admin_exit.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Redirect to superadmin dashboard on successful verification
          window.location.href = 'superadmin_dashboard.php';
        } else {
          // Show error message
          errorMsg.style.display = 'block';
          document.getElementById('adminPassword').value = '';
        }
      } catch (error) {
        console.error('Verification error:', error);
        errorMsg.textContent = 'An error occurred. Please try again.';
        errorMsg.style.display = 'block';
      }
    }

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
      const modal = document.getElementById('exitModal');
      if (e.target === modal) {
        closeExitModal();
      }
    });
  </script>
</body>
</html>
