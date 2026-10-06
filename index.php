<?php
session_start();
$isGuest = !isset($_SESSION['user']);
$user = $isGuest ? null : $_SESSION['user'];

if (!$isGuest && !isset($_GET['home'])) {
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
  
  <?php include 'auth_modal.php'; ?>
  
  <div class="page-container">
    <header class="top-nav">
      <div class="nav-right">
        <?php if($isGuest): ?>
            <a href="#login" id="nav-signin" class="nav-btn">Login</a>
        <?php else: ?>
            <span style="color: white; margin-right: 15px; font-weight: 500;"><?php echo htmlspecialchars($user['username']); ?></span>
            <a href="dashboard.php" class="nav-btn" style="background: transparent; border: 1px solid white;">Marketplace</a>
            <a href="logout.php" class="nav-btn" style="margin-left: 10px;">Logout</a>
        <?php endif; ?>
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
