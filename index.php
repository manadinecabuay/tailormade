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
  <title><?php echo $business['business_name']; ?> - Home</title>

  <?php include('theme_loader.php'); ?>

  <!-- Poppins Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

  <!-- Animate.css library -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

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
      gap: 13px;
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

    .hero {
      min-height: 90vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 60px;
      text-align: center;
      padding: 60px 40px;
      animation: fadeIn 1.2s ease forwards;
    }

    .hero h1 {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 60px 100px;
      border-radius: 30px;
      font-size: 52px;
      font-weight: 700;
      color: white;
      margin-top:180px;
      line-height: 1.4;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
      text-transform: lowercase;
      letter-spacing: 1px;
      animation: fadeIn 1.5s ease forwards;
    }

    .hero-buttons {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      justify-content: center;
      animation: fadeIn 1.8s ease forwards;
    }

    /* Background hero image */
    .hero-bg {
        position: absolute;
        padding-top:5px;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        background: url('tailor-bg.jpg') center/cover no-repeat;
        z-index: -1;
        opacity: 0.25;
        animation: fadeIn 2s ease forwards;
    }

    /* Floating hero image */
    .hero-image {
        width: 280px;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.4);
        animation: float 6s ease-in-out infinite;
    }

    .btn {
      background: white;
      color: var(--theme-bg);
      padding: 18px 50px;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 700;
      letter-spacing: 1.5px;
      font-size: 16px;
      display: inline-block;
      transition: all 0.3s ease;
      text-transform: uppercase;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .btn:hover {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }

    .btn-secondary {
      border: 2px solid white;
    }

    .btn-secondary:hover {
      background: white;
      color: var(--theme-bg);
      border: 2px solid white;
    }

    section { padding: 100px 60px; text-align: center; }

    section h2 {
      font-size: 42px;
      margin-bottom: 50px;
      letter-spacing: 2px;
      font-weight: 700;
      color: white;
      text-transform: uppercase;
    }

    #about {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(10px);
      animation: fadeIn 1.2s ease forwards;
    }

    #about p {
      font-size: 20px;
      line-height: 1.8;
      max-width: 900px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.1);
      padding: 50px 80px;
      border-radius: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    #contact {
      background: rgba(0, 0, 0, 0.15);
      animation: fadeIn 1.3s ease forwards;
    }

    #contact form {
      max-width: 600px;
      margin: 0 auto;
      text-align: left;
      background: rgba(255, 255, 255, 0.95);
      padding: 50px;
      border-radius: 30px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    footer {
      background: rgba(0, 0, 0, 0.15);
      padding: 30px;
      text-align: center;
      color: white;
      font-size: 14px;
      animation: fadeIn 1.5s ease forwards;
    }

    /* Floating animation */
    @keyframes float {
        0% { transform: translateY(0); }
        50% { transform: translateY(-18px); }
        100% { transform: translateY(0); }
    }

    /* Company Logo in About Section */
    .about-logo-container {
      display: flex;
      justify-content: center;
      margin-bottom: 40px;
      animation: fadeIn 1.5s ease forwards;
    }

    .about-company-logo {
      width: 180px;
      height: 180px;
      object-fit: cover;
      border-radius: 50%;
      border: 5px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
      transition: all 0.4s ease;
      animation: float 6s ease-in-out infinite;
    }

    .about-company-logo:hover {
      transform: scale(1.1) rotate(5deg);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
      border-color: rgba(255, 255, 255, 0.6);
    }

    /* Enhanced Contact Section */
    .contact-info-container {
      max-width: 700px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.1);
      padding: 50px 60px;
      border-radius: 30px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
    }

    .contact-item {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 30px;
      padding: 20px;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 15px;
      transition: all 0.3s ease;
    }

    .contact-item:hover {
      background: rgba(255, 255, 255, 0.15);
      transform: translateX(10px);
    }

    .contact-icon {
      font-size: 35px;
      min-width: 50px;
      text-align: center;
    }

    .contact-details {
      flex: 1;
      text-align: left;
    }

    .contact-label {
      font-size: 14px;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.7);
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 5px;
    }

    .contact-value {
      font-size: 20px;
      font-weight: 500;
      color: white;
      word-break: break-word;
    }

    .machine-status-box {
      margin-top: 20px;
      padding: 25px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 15px;
      border-left: 4px solid rgba(255, 255, 255, 0.5);
    }

    /* Scroll reveal animations */
    .fade-in { opacity: 0; transform: translateY(40px); transition: 1s ease; }
    section.visible { opacity: 1; transform: translateY(0); }

    .slide-up { opacity: 0; transform: translateY(40px); transition: 1s ease; }
    .slide-left { opacity: 0; transform: translateX(-40px); transition: 1s ease; }
    .slide-right { opacity: 0; transform: translateX(40px); transition: 1s ease; }

    /* WHY CUSTOMERS LOVE US */
.features {
    display: flex;
    justify-content: center;
    gap: 40px;
    flex-wrap: wrap;
}
.feature-card {
    width: 300px;
    background: #1c1c1c;
    border-radius: 20px;
    padding: 25px;
    text-align: center;
    transition: 0.4s;
    box-shadow: 0 0 15px rgba(0,0,0,0.5);
}
.feature-card:hover {
    transform: translateY(-10px) scale(1.03);
    box-shadow: 0 0 25px rgba(0,255,255,0.4);
}
.feature-card img {
    width: 100%;
    border-radius: 12px;
    margin-bottom: 15px;
}

    /* About section layout */
    .about-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 40px;
        flex-wrap: wrap;
    }

    .about-img {
        width: 400px;
        border-radius: 20px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }

    .about-text {
        max-width: 600px;
        background: rgba(255,255,255,0.1);
        padding: 40px;
        border-radius: 20px;
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

      .hero {
        min-height: 80vh;
        padding: 40px 20px;
        gap: 40px;
      }

      .hero h1 {
        font-size: 32px;
        padding: 40px 30px;
        margin-top: 80px;
        border-radius: 20px;
      }

      .hero-image {
        width: 200px;
      }

      .hero-buttons {
        flex-direction: column;
        gap: 15px;
        width: 100%;
        max-width: 300px;
      }

      .btn {
        padding: 15px 40px;
        font-size: 14px;
        width: 100%;
      }

      section {
        padding: 60px 20px;
      }

      section h2 {
        font-size: 28px;
        margin-bottom: 30px;
      }

      #about p {
        font-size: 16px;
        padding: 30px 25px;
        border-radius: 20px;
      }

      .features {
        gap: 25px;
      }

      .feature-card {
        width: 100%;
        max-width: 350px;
      }

      .about-container {
        flex-direction: column;
        gap: 30px;
      }

      .about-img {
        width: 100%;
        max-width: 350px;
      }

      .about-text {
        padding: 25px;
      }

      #contact form {
        padding: 30px 20px;
        border-radius: 20px;
      }

      footer {
        padding: 20px;
        font-size: 12px;
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

      .hero {
        min-height: 70vh;
        padding: 30px 15px;
        gap: 30px;
      }

      .hero h1 {
        font-size: 24px;
        padding: 30px 20px;
        margin-top: 60px;
        letter-spacing: 0.5px;
      }

      .hero-image {
        width: 160px;
      }

      .hero-buttons {
        max-width: 250px;
      }

      .btn {
        padding: 12px 30px;
        font-size: 13px;
        letter-spacing: 1px;
      }

      section {
        padding: 40px 15px;
      }

      section h2 {
        font-size: 24px;
        margin-bottom: 25px;
        letter-spacing: 1px;
      }

      #about p {
        font-size: 14px;
        line-height: 1.6;
        padding: 25px 20px;
      }

      .feature-card {
        padding: 20px;
      }

      .about-img {
        max-width: 280px;
      }

      .about-text {
        padding: 20px;
        font-size: 14px;
      }

      #contact p {
        font-size: 15px !important;
        padding: 0 10px;
      }

      footer {
        padding: 15px;
        font-size: 11px;
      }
    }

    /* Extra small devices (max-width: 360px) */
    @media (max-width: 360px) {
      .hero h1 {
        font-size: 20px;
        padding: 25px 15px;
      }

      .btn {
        padding: 10px 25px;
        font-size: 12px;
      }

      section h2 {
        font-size: 20px;
      }

      #about p {
        font-size: 13px;
        padding: 20px 15px;
      }
    }

    /* Landscape orientation adjustments */
    @media (max-height: 600px) and (orientation: landscape) {
      .hero {
        min-height: auto;
        padding: 30px 20px;
      }

      .hero h1 {
        margin-top: 20px;
        font-size: 28px;
        padding: 25px 40px;
      }

      .hero-image {
        width: 150px;
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
    <a href="index.php" class="active" >Home</a>
    <a href="index.php#about">About Us</a>
    <a href="index.php#contact">Contact Us</a>
    <a href="customer_login.php">Login</a>
  </nav>
</header>

<!-- HERO SECTION WITH BACKGROUND IMAGE -->
<section class="hero fade-in">
    <div class="hero-bg"></div>

    <h1 class="animate__animated animate__fadeInDown">
            tailored to fit, designed to impress
    </h1>


    <div class="hero-buttons animate__animated animate__fadeInUp">
        <a href="customer_registration.php" class="btn">Register Now</a>
        <a href="customer_login.php" class="btn btn-secondary">Login</a>
    </div>
</section>

<!-- WHY CHOOSE US -->
<section id="why" class="fade-in">
    <h2 class="animate__animated animate__fadeInUp">Why Customers Love Us</h2>

    <div class="features">
        <div class="feature-box slide-up">
            <img src="fast-service.jpg" alt="Fast">
            <h3>Fast Service</h3>
            <p>Quick turnaround time with excellent quality.</p>
        </div>

        <div class="feature-box slide-up" style="animation-delay:0.2s">
            <img src="quality.jpg" alt="Quality">
            <h3>High Quality</h3>
            <p>Expert tailors with years of experience.</p>
        </div>

        <div class="feature-box slide-up" style="animation-delay:0.4s">
            <img src="tracking.jpg" alt="Tracking">
            <h3>Order Tracking</h3>
            <p>Track your garments in real time.</p>
        </div>
    </div>
</section>


<section id="about" class="fade-in">
    <div class="about-logo-container">
      <img src="<?php echo $logo_path; ?>" alt="<?php echo $business_name; ?> Logo" class="about-company-logo">
    </div>
    <h2 class="animate__animated animate__fadeIn">✨ About Us</h2>
    <p style="font-size: 20px; line-height: 1.8; max-width: 900px; margin: 0 auto; background: rgba(255, 255, 255, 0.1); padding: 50px 80px; border-radius: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);">
        <?php echo nl2br($about_us); ?>
    </p>
</section>


<section id="contact">
  <h2 class="animate__animated animate__fadeIn">📧 Contact Us</h2>

  <div class="contact-info-container animate__animated animate__fadeInUp">
    
    <div class="contact-item">
      <div class="contact-icon">📧</div>
      <div class="contact-details">
        <div class="contact-label">Email</div>
        <div class="contact-value"><?php echo $email; ?></div>
      </div>
    </div>

    <div class="contact-item">
      <div class="contact-icon">📞</div>
      <div class="contact-details">
        <div class="contact-label">Contact</div>
        <div class="contact-value"><?php echo $contact; ?></div>
      </div>
    </div>

    <?php if ($machine_message): ?>
    <div class="machine-status-box">
      <div class="contact-item" style="margin-bottom: 0;">
        <div class="contact-icon">⚙️</div>
        <div class="contact-details">
          <div class="contact-label">Machine Status</div>
          <div class="contact-value" style="font-size: 16px; line-height: 1.6;">
            <?php echo nl2br($machine_message); ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</section>

<footer>
    <p>&copy; <?php echo date("Y"); ?> <?php echo $business_name; ?>. All rights reserved.</p>
</footer>

<script>
const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting){
            entry.target.classList.add('visible');
        }
    });
}, { threshold: 0.2 });

document.querySelectorAll('section, .slide-up, .slide-left, .slide-right')
    .forEach(el => observer.observe(el));
</script>

</body>
</html>
