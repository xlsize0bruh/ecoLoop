<?php
session_start();
if (isset($_SESSION['user']) && !isset($_GET['home'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EcoLoop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:ital,wght@0,300;0,400;0,500;0,700;0,900;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="Style.css?v=2">
  <link rel="icon" href="/favicon.png" type="image/png">
</head>
<body>
  
  <!-- Auth Modal Overlay -->
  <div id="auth-modal" class="modal-overlay">
    <div class="glass-card modal-card">
      <button id="close-modal" class="close-btn">&times;</button>
      
      <div class="modal-split">
        <div class="modal-image">
          <img src="ee7f6d5b496baf6f362f7a67c045a59b.jpg" alt="White Flowers">
        </div>
        
        <div class="modal-form-container">
          <div id="signup-view">
            <div class="auth-header">
              <img src="favicon.png" alt="EcoLoop Logo" class="auth-logo">
              <h2>Create an Account</h2>
              <p>Join EcoLoop and start trading.</p>
            </div>
            <form class="auth-form" id="signup-form">
              <div class="input-group">
                <label for="signup-username">Username</label>
                <input type="text" id="signup-username" placeholder="Create a username" required>
              </div>
              <div class="input-group">
                <label for="signup-pincode">6-digit Pincode</label>
                <input type="text" id="signup-pincode" placeholder="e.g. 110001" required pattern="[0-9]{6}">
              </div>
              <div class="input-group">
                <label for="signup-password">Password</label>
                <input type="password" id="signup-password" placeholder="Create a strong password (min 8 chars)" required minlength="8">
              </div>
              <div id="signup-error" style="color: #ff6b6b; font-size: 0.85rem; display: none;"></div>
              <button type="submit" class="primary-btn auth-submit">Sign Up <span class="btn-icon">&#x2192;</span></button>
            </form>
            <div class="auth-footer">
              <p>Already have an account? <a href="#" id="show-signin">Login</a></p>
            </div>
          </div>

          <div id="signin-view" style="display: none;">
            <div class="auth-header">
              <img src="favicon.png" alt="EcoLoop Logo" class="auth-logo">
              <h2>Welcome Back</h2>
              <p>Login to continue to EcoLoop.</p>
            </div>
            <form class="auth-form" id="signin-form">
              <div class="input-group">
                <label for="signin-username">Username</label>
                <input type="text" id="signin-username" placeholder="Enter your username" required>
              </div>
              <div class="input-group">
                <label for="signin-password">Password</label>
                <input type="password" id="signin-password" placeholder="Enter your password" required>
              </div>
              <div id="signin-error" style="color: #ff6b6b; font-size: 0.85rem; display: none;"></div>
              <button type="submit" class="primary-btn auth-submit">Login <span class="btn-icon">&#x2192;</span></button>
            </form>
            <div class="auth-footer">
              <p>Don't have an account? <a href="#" id="show-signup">Sign Up</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="page-container">
    <header class="top-nav">
      <div class="nav-right">
        <a href="#" id="nav-signin" class="nav-btn">Login</a>
      </div>
    </header>

    <main class="top-section">
      <h1>Ready to give things<br>a new <span class="inline-icon"></span> purpose?</h1>
      <p class="description">
        There comes a moment when you realize you already have more than you need. Instead of letting useful things sit unused, EcoLoop helps you give them a new purpose—by trading what you have for what you need and building something meaningful together.
      </p>
      <a href="dashboard.php" id="explore-btn" class="primary-btn">Explore EcoLoop <span class="btn-icon">&#x2197;</span></a>
      
      <!-- Infinite Marquee -->
      <div class="hero-marquee-container">
        <div class="hero-marquee">
          <span>Zero Waste</span>
          <span>Community Driven</span>
          <span>Barter & Trade</span>
          <span>Sustainable Living</span>
          <span>Eco-Friendly</span>
          <span>Circular Economy</span>
          <span>Upcycle Materials</span>
          <!-- Duplicate for seamless loop -->
          <span aria-hidden="true">Zero Waste</span>
          <span aria-hidden="true">Community Driven</span>
          <span aria-hidden="true">Barter & Trade</span>
          <span aria-hidden="true">Sustainable Living</span>
          <span aria-hidden="true">Eco-Friendly</span>
          <span aria-hidden="true">Circular Economy</span>
          <span aria-hidden="true">Upcycle Materials</span>
        </div>
      </div>
    </main>
    
    <footer class="bottom-glass-card">
      <div class="footer-top">
        <span class="copyright">© Copyright 2026</span>
        <div class="line"></div>
        <span class="rights">All Rights Reserved</span>
      </div>
      <nav class="footer-nav">
        <a href="#">Home</a>
        <a href="#">About</a>
        <a href="#">Service</a>
        <a href="#">Platform</a>
        <a href="#">How It Works</a>
        <a href="#">Contact</a>
      </nav>
      <h2 class="huge-footer-title">EcoLoop</h2>
    </footer>
  </div>

  <script type="module" src="/Script.js"></script>
</body>
</html>
