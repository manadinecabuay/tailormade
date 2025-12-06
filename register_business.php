<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register A Business</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    * { 
      margin: 0; 
      padding: 0; 
      box-sizing: border-box; 
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    .container {
      position: relative;
      z-index: 1;
    }

    footer {
      position: relative;
      z-index: 1;
    }

    .container {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
      flex: 1;
      width: 100%;
    }

    .content-wrapper {
      background: #ffffff;
      border-radius: 30px;
      padding: 60px 80px;
      max-width: 600px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
      animation: slideIn 0.5s ease;
      text-align: center;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    h2 { 
      text-align: center; 
      margin-bottom: 40px; 
      color: #000;
      letter-spacing: 2px;
      font-size: 32px;
      font-weight: 700;
    }

    .btn {
      display: inline-block;
      padding: 16px 50px;
      background: #000;
      color: white;
      border-radius: 25px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: all 0.3s ease;
      font-size: 16px;
      text-decoration: none;
    }

    .btn:hover { 
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
      background: #333;
    }

    footer {
      width: 100%;
      text-align: center;
      padding: 20px;
      color: #666;
      font-size: 14px;
      background: rgba(255, 255, 255, 0.5);
    }

    @media (max-width: 768px) { 
      .content-wrapper { 
        padding: 40px 30px; 
        max-width: 90%; 
      }
      
      h2 { 
        font-size: 26px; 
      }

      .btn {
        padding: 14px 40px;
        font-size: 15px;
      }
    }

    @media (max-width: 480px) {
      .content-wrapper { 
        padding: 30px 20px; 
      }
      
      h2 { 
        font-size: 22px;
        margin-bottom: 30px;
      }

      .btn {
        padding: 12px 35px;
        font-size: 14px;
      }

      footer {
        font-size: 12px;
        padding: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="content-wrapper">
      <h2>Welcome Business Owner!</h2>
      <a href="business_info.html" class="btn">Register Business</a>
    </div>
  </div>

  <footer>
    All Rights Reserved 2025
  </footer>
</body>
</html>
