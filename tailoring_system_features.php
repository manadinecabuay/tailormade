<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TailorMade - Tailoring Shop Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      line-height: 1.6;
      color: #333;
      overflow-x: hidden;
    }

    /* Header/Navigation */
    header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 20px 0;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    nav {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 40px;
    }

    .logo {
      font-size: 28px;
      font-weight: 700;
      color: white;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logo i {
      font-size: 32px;
    }

    .nav-links {
      display: flex;
      gap: 30px;
      list-style: none;
    }

    .nav-links a {
      color: white;
      text-decoration: none;
      font-weight: 500;
      font-size: 16px;
      padding: 8px 16px;
      border-radius: 25px;
      transition: all 0.3s ease;
    }

    .nav-links a:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-2px);
    }

    .nav-links a.active {
      background: rgba(255, 255, 255, 0.3);
    }

    /* Hero Section */
    .hero {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 150px 40px 100px;
      text-align: center;
      margin-top: 70px;
    }

    .hero h1 {
      font-size: 48px;
      margin-bottom: 20px;
      animation: fadeInUp 1s ease;
    }

    .hero p {
      font-size: 20px;
      margin-bottom: 40px;
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
      animation: fadeInUp 1.2s ease;
    }

    .cta-button {
      display: inline-block;
      background: white;
      color: #667eea;
      padding: 16px 40px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 700;
      font-size: 18px;
      transition: all 0.3s ease;
      animation: fadeInUp 1.4s ease;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .cta-button:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }

    /* Features Section */
    .features {
      padding: 80px 40px;
      background: #f8f9fa;
    }

    .features h2 {
      text-align: center;
      font-size: 40px;
      margin-bottom: 20px;
      color: #667eea;
    }

    .features-subtitle {
      text-align: center;
      font-size: 18px;
      color: #666;
      margin-bottom: 60px;
      max-width: 700px;
      margin-left: auto;
      margin-right: auto;
    }

    .features-grid {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
      gap: 40px;
    }

    .feature-card {
      background: white;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      animation: fadeInUp 0.6s ease;
    }

    .feature-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 50px rgba(102, 126, 234, 0.2);
    }

    .feature-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 25px;
      font-size: 36px;
      color: white;
    }

    .feature-card h3 {
      font-size: 24px;
      margin-bottom: 15px;
      color: #333;
    }

    .feature-card p {
      color: #666;
      font-size: 16px;
      line-height: 1.8;
    }

    .feature-list {
      list-style: none;
      margin-top: 20px;
    }

    .feature-list li {
      padding: 8px 0;
      color: #555;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .feature-list li i {
      color: #667eea;
      font-size: 14px;
    }

    /* Animations */
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

    /* Footer */
    footer {
      background: #2d3748;
      color: white;
      text-align: center;
      padding: 30px 40px;
    }

    footer p {
      margin-bottom: 10px;
    }

    footer a {
      color: #667eea;
      text-decoration: none;
    }

    footer a:hover {
      text-decoration: underline;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      nav {
        flex-direction: column;
        gap: 20px;
        padding: 0 20px;
      }

      .nav-links {
        gap: 15px;
      }

      .hero {
        padding: 120px 20px 60px;
      }

      .hero h1 {
        font-size: 32px;
      }

      .hero p {
        font-size: 16px;
      }

      .features {
        padding: 60px 20px;
      }

      .features h2 {
        font-size: 32px;
      }

      .features-grid {
        grid-template-columns: 1fr;
        gap: 30px;
      }

      .feature-card {
        padding: 30px;
      }
    }

    @media (max-width: 480px) {
      .logo {
        font-size: 22px;
      }

      .nav-links a {
        font-size: 14px;
        padding: 6px 12px;
      }

      .hero h1 {
        font-size: 28px;
      }

      .hero p {
        font-size: 15px;
      }

      .cta-button {
        padding: 14px 30px;
        font-size: 16px;
      }

      .features h2 {
        font-size: 28px;
      }

      .feature-card h3 {
        font-size: 20px;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <nav>
      <div class="logo">
        <i class="fa-solid fa-scissors"></i>
        TailorMade
      </div>
      <ul class="nav-links">
        <li><a href="#home" class="active">Home</a></li>
        <li><a href="#features">Features</a></li>
        <li><a href="business_info.php">Register</a></li>
      </ul>
    </nav>
  </header>

  <!-- Hero Section -->
  <section id="home" class="hero">
    <h1>Transform Your Tailoring Business</h1>
    <p>Streamline operations, manage orders, track inventory, and grow your tailoring shop with our comprehensive management system.</p>
    <a href="business_info.php" class="cta-button">
      <i class="fa-solid fa-rocket"></i> Register Your Business
    </a>
  </section>

  <!-- Features Section -->
  <section id="features" class="features">
    <h2>Powerful Features for Your Tailoring Shop</h2>
    <p class="features-subtitle">Everything you need to manage your tailoring business efficiently and professionally</p>

    <div class="features-grid">
      <!-- Feature 1: Order Management -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-clipboard-list"></i>
        </div>
        <h3>Order Management</h3>
        <p>Efficiently manage all customer orders from creation to completion with our intuitive order tracking system.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Create and track orders</li>
          <li><i class="fa-solid fa-check-circle"></i> Real-time order status updates</li>
          <li><i class="fa-solid fa-check-circle"></i> Customer order history</li>
          <li><i class="fa-solid fa-check-circle"></i> Order notifications</li>
        </ul>
      </div>

      <!-- Feature 2: Customer Management -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-users"></i>
        </div>
        <h3>Customer Management</h3>
        <p>Build lasting relationships with your customers through comprehensive customer profiles and history tracking.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Customer profiles</li>
          <li><i class="fa-solid fa-check-circle"></i> Measurement records</li>
          <li><i class="fa-solid fa-check-circle"></i> Order history tracking</li>
          <li><i class="fa-solid fa-check-circle"></i> Customer preferences</li>
        </ul>
      </div>

      <!-- Feature 3: Employee Management -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-user-tie"></i>
        </div>
        <h3>Employee Management</h3>
        <p>Manage your team effectively with employee profiles, attendance tracking, and payroll management.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Employee profiles</li>
          <li><i class="fa-solid fa-check-circle"></i> Attendance tracking</li>
          <li><i class="fa-solid fa-check-circle"></i> Payroll management</li>
          <li><i class="fa-solid fa-check-circle"></i> Performance monitoring</li>
        </ul>
      </div>

      <!-- Feature 4: Inventory Management -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <h3>Inventory Management</h3>
        <p>Keep track of fabrics, materials, and supplies with automated inventory tracking and low-stock alerts.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Material tracking</li>
          <li><i class="fa-solid fa-check-circle"></i> Low stock alerts</li>
          <li><i class="fa-solid fa-check-circle"></i> Supplier management</li>
          <li><i class="fa-solid fa-check-circle"></i> Usage reports</li>
        </ul>
      </div>

      <!-- Feature 5: Quality Control -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-medal"></i>
        </div>
        <h3>Quality Control</h3>
        <p>Ensure every garment meets your standards with built-in quality check processes and approval workflows.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Quality inspection</li>
          <li><i class="fa-solid fa-check-circle"></i> Approval workflows</li>
          <li><i class="fa-solid fa-check-circle"></i> Defect tracking</li>
          <li><i class="fa-solid fa-check-circle"></i> Quality reports</li>
        </ul>
      </div>

      <!-- Feature 6: Dashboard & Analytics -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-chart-line"></i>
        </div>
        <h3>Dashboard & Analytics</h3>
        <p>Make data-driven decisions with comprehensive analytics and real-time business insights.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Sales analytics</li>
          <li><i class="fa-solid fa-check-circle"></i> Revenue tracking</li>
          <li><i class="fa-solid fa-check-circle"></i> Performance metrics</li>
          <li><i class="fa-solid fa-check-circle"></i> Custom reports</li>
        </ul>
      </div>

      <!-- Feature 7: Customization -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-palette"></i>
        </div>
        <h3>Business Customization</h3>
        <p>Personalize the system with your business branding, logo, and custom themes to match your shop's identity.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Custom branding</li>
          <li><i class="fa-solid fa-check-circle"></i> Logo upload</li>
          <li><i class="fa-solid fa-check-circle"></i> Theme customization</li>
          <li><i class="fa-solid fa-check-circle"></i> Business information</li>
        </ul>
      </div>

      <!-- Feature 8: Multi-User Access -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-user-shield"></i>
        </div>
        <h3>Multi-User Access</h3>
        <p>Secure role-based access for owners, supervisors, and employees with customizable permissions.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Role-based access</li>
          <li><i class="fa-solid fa-check-circle"></i> Secure authentication</li>
          <li><i class="fa-solid fa-check-circle"></i> Permission management</li>
          <li><i class="fa-solid fa-check-circle"></i> Activity logging</li>
        </ul>
      </div>

      <!-- Feature 9: Mobile Friendly -->
      <div class="feature-card">
        <div class="feature-icon">
          <i class="fa-solid fa-mobile-screen"></i>
        </div>
        <h3>Mobile Responsive</h3>
        <p>Access your business from anywhere with our fully responsive design that works on all devices.</p>
        <ul class="feature-list">
          <li><i class="fa-solid fa-check-circle"></i> Mobile optimized</li>
          <li><i class="fa-solid fa-check-circle"></i> Tablet friendly</li>
          <li><i class="fa-solid fa-check-circle"></i> Desktop compatible</li>
          <li><i class="fa-solid fa-check-circle"></i> Cross-browser support</li>
        </ul>
      </div>
    </div>

    <!-- Call to Action -->
    <div style="text-align: center; margin-top: 60px;">
      <a href="business_info.php" class="cta-button">
        <i class="fa-solid fa-rocket"></i> Get Started Today
      </a>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <p>&copy; 2024 TailorMade - Tailoring Shop Management System</p>
    <p>Already have an account? <a href="unified_login.php">Login Here</a></p>
  </footer>

  <script>
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Active nav link on scroll
    window.addEventListener('scroll', () => {
      const sections = document.querySelectorAll('section');
      const navLinks = document.querySelectorAll('.nav-links a');
      
      let current = '';
      sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (scrollY >= (sectionTop - 100)) {
          current = section.getAttribute('id');
        }
      });

      navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
          link.classList.add('active');
        }
      });
    });
  </script>
</body>
</html>
