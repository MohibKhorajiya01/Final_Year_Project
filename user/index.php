<?php 
session_start();
?>
<!DOCTYPE html>
<html lang="en" prefix="og: http://ogp.me/ns#">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- Primary Meta Tags -->
  <title>EventEase - Event Booking & Management System | Plan, Book & Celebrate</title>
  <meta name="title" content="EventEase - Event Booking & Management System | Plan, Book & Celebrate">
  <meta name="description" content="EventEase - Your complete event booking and management solution. Book weddings, corporate events, parties, and more. Easy online booking with secure payment gateway. Plan your perfect event with EventEase.">
  <meta name="keywords" content="EventEase, Event Ease, event booking, event management, online event booking, wedding planning, corporate events, party booking, event booking system, book events online, event management software">
  <meta name="author" content="EventEase">
  <meta name="robots" content="index, follow">
  <meta name="language" content="English">
  <meta name="revisit-after" content="7 days">
  
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="http://localhost/Final_Year_Project/user/">
  <meta property="og:title" content="EventEase - Event Booking & Management System">
  <meta property="og:description" content="Your complete event booking and management solution. Book weddings, corporate events, parties, and more.">
  <meta property="og:image" content="http://localhost/Final_Year_Project/user/assets/1.logo.png">
  
  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="http://localhost/Final_Year_Project/user/">
  <meta property="twitter:title" content="EventEase - Event Booking & Management System">
  <meta property="twitter:description" content="Your complete event booking and management solution. Book weddings, corporate events, parties, and more.">
  <meta property="twitter:image" content="http://localhost/Final_Year_Project/user/assets/1.logo.png">
  
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/1.logo.png">
  <link rel="apple-touch-icon" href="assets/1.logo.png">
  
  <!-- Web App Manifest for Browser Search -->
  <link rel="manifest" href="manifest.json">
  <meta name="application-name" content="EventEase">
  <meta name="apple-mobile-web-app-title" content="EventEase">
  <meta name="msapplication-TileColor" content="#5a2ca0">
  <meta name="theme-color" content="#5a2ca0">
  
  <!-- Canonical URL -->
  <link rel="canonical" href="http://localhost/Final_Year_Project/user/">
  
  <!-- Additional SEO for Browser Search -->
  <meta name="format-detection" content="telephone=no">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/layout.css?v=2">
  
  <!-- Structured Data for SEO -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "EventEase",
    "alternateName": "Event Ease",
    "url": "http://localhost/Final_Year_Project/user/",
    "logo": "http://localhost/Final_Year_Project/user/assets/1.logo.png",
    "description": "EventEase - Your complete event booking and management solution. Book weddings, corporate events, parties, and more.",
    "contactPoint": {
      "@type": "ContactPoint",
      "email": "eventease99@gmail.com",
      "contactType": "Customer Service"
    },
    "sameAs": []
  }
  </script>

  <style>
    /* Reset and Body */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
      background-color: #f8f9fa;
      overflow-x: hidden;
      padding-top: 0 !important;
    }
    
    .site-footer {
      margin-top: 0 !important;
    }

    /* Slider Section - Full Page */
    .slider-section {
      position: relative;
      height: 100vh;
      overflow: hidden;
      margin-top: 0;
      padding-top: 78px;
    }
    
    @media (max-width: 768px) {
      .slider-section {
        padding-top: 68px;
      }
    }
    
    .slide {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      opacity: 0;
      transition: opacity 1s ease-in-out;
      background-size: cover;
      background-position: center;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 0 24px;
    }
    
    .slide.active {
      opacity: 1;
    }
    
    .slide-content {
      max-width: 720px;
      width: 100%;
      padding: 40px 30px;
      color: white;
      text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.7);
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 18px;
    }
    
    .slide-content h1 {
      font-size: 48px;
      margin-bottom: 10px;
      font-weight: 700;
      color: #fff;
    }
    
    .slide-content h2 {
      font-size: 42px;
      margin-bottom: 0;
      font-weight: 700;
      color: #fff;
    }
    
    .slide-content p {
      font-size: 22px;
      margin-bottom: 0;
      font-weight: 300;
    }
    
    .events-btn {
      background:  #5a2ca0;
      color: white;
      border: none;
      padding: 15px 40px;
      font-size: 18px;
      font-weight: 600;
      border-radius: 50px;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-block;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      margin: 0 auto;
    }
    
    .events-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
      background: #7d3cbd;
      color: #fff;
    }
    
    .slider-controls {
      position: absolute;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      z-index: 10;
    }
    
    .slider-dot {
      width: 14px;
      height: 14px;
      border-radius: 50%;
      background-color: rgba(255, 255, 255, 0.6);
      margin: 0 8px;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .slider-dot.active {
      background-color: #7d3cbd;
      transform: scale(1.2);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .slider-section {
        height: 100vh;
      }
      
      .slide {
        padding: 0 16px;
      }

      .slide-content {
        padding: 30px 20px;
        gap: 16px;
      }
      
      .slide-content h2 {
        font-size: 36px;
      }
      
      .slide-content p {
        font-size: 18px;
      }
      
      .events-btn {
        padding: 12px 30px;
        font-size: 16px;
      }
    }
    
    @media (max-width: 480px) {
      .slide-content h2 {
        font-size: 28px;
      }
      
      .slide-content {
        padding: 24px 15px;
        gap: 12px;
      }
      
      .slide-content p {
        font-size: 16px;
      }
      
      .events-btn {
        padding: 10px 25px;
        font-size: 14px;
      }
    }
  </style>
</head>
<body>

  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <!-- Slider Section -->
  <section class="slider-section">
    <div class="slider">
      <!-- Slide 1 -->
      <div class="slide active" style="background-image: linear-gradient(rgba(90, 44, 160, 0.3), rgba(125, 60, 189, 0.3)), url('assets/slider_1.jpg');">
        <div class="slide-content">
          <h2>Welcome to EventEase</h2>
          <p>Create Unforgettable Events & Plan your dream events with our professional services</p>
          <a href="events.php" class="events-btn">Explore Events</a>
        </div>
      </div>
      
      <!-- Slide 2 -->
      <div class="slide" style="background-image: linear-gradient(rgba(90, 44, 160, 0.3), rgba(125, 60, 189, 0.3)), url('assets/slider_2.jpg');">
        <div class="slide-content">
          <h2>Wedding Planning</h2>
          <p>Make your special day perfect with our wedding planning expertise</p>
          <a href="events.php" class="events-btn">Explore Events</a>
        </div>
      </div>
      
      <!-- Slide 3 -->
      <div class="slide" style="background-image: linear-gradient(rgba(90, 44, 160, 0.3), rgba(125, 60, 189, 0.3)), url('assets/slider_3.jpg');">
        <div class="slide-content">
          <h2>Corporate Events</h2>
          <p>Professional event management for your business needs</p>
          <a href="events.php" class="events-btn">Explore Events</a>
        </div>
      </div>
    </div>
    
    <!-- Slider Controls -->
    <div class="slider-controls">
      <div class="slider-dot active" data-slide="0"></div>
      <div class="slider-dot" data-slide="1"></div>
      <div class="slider-dot" data-slide="2"></div>
    </div>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/layout.js?v=2"></script>
  <script>
    // Slider functionality
    document.addEventListener('DOMContentLoaded', function() {
      const slides = document.querySelectorAll('.slide');
      const dots = document.querySelectorAll('.slider-dot');
      let currentSlide = 0;
      const slideInterval = 3000; // 3 seconds
      
      function showSlide(n) {
        slides.forEach(slide => {
          slide.classList.remove('active');
        });
        
        dots.forEach(dot => {
          dot.classList.remove('active');
        });
        
        slides[n].classList.add('active');
        dots[n].classList.add('active');
        
        currentSlide = n;
      }
      
      function nextSlide() {
        let next = currentSlide + 1;
        if (next >= slides.length) {
          next = 0;
        }
        showSlide(next);
      }
      
      let slideTimer = setInterval(nextSlide, slideInterval);
      
      dots.forEach(dot => {
        dot.addEventListener('click', function() {
          clearInterval(slideTimer);
          const slideIndex = parseInt(this.getAttribute('data-slide'));
          showSlide(slideIndex);
          slideTimer = setInterval(nextSlide, slideInterval);
        });
      });
    });
  </script>

</body>
</html>

