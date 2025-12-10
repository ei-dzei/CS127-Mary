<?php
// Config & Error Handling
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Initialization
require_once __DIR__ . '/../partials/init.php';
// Page Title
$pageTitle = 'Home';
// Header
require_once __DIR__ . '/../partials/site_header.php';
?>

<!-- Hero Section -->
<section class="hero fade-in" aria-label="Featured highlights">

  <article class="hero__slide is-active"
           style="background: url('bg4.png') center/cover no-repeat;">
    <div class="hero__overlay"></div>
    <div class="hero__content">
      <div class="hero__logo-wrapper" >
          <img class="brand__logo" 
               src="<?= BASE_URL ?>/public/logo.png" 
               alt="School of Mary Logo" 
               onerror="this.style.display='none'"
               style="width: 230px; height: auto; display: block; margin: 0 auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.5));">
      </div>
      <h1 class="hero__title">School of Mary Faculty and Research Portal</h1>
      <p class="hero__subtitle">
        Excellence in Research and Innovation
      </p>
    </div>
  </article>

  <article class="hero__slide"
           style="background: url('bg5.png') center/cover no-repeat;">
    <div class="hero__overlay"></div>
    <div class="hero__content">
      <div class="hero__icon" aria-hidden="true"><i class="bi bi-people-fill"></i></div>
      <h2 class="hero__title">Discover Faculty and their Academic Contributions</h2>
      <p class="hero__subtitle">
        Discover faculty expertise, funded research projects, and their academic impact.
      </p>
      <div class="hero__actions">
        <a href="<?= BASE_URL ?>/public/faculty.php" class="btn">Explore Faculty</a>
      </div>
    </div>
  </article>

  <article class="hero__slide"
           style="background: url('bg6.png') center/cover no-repeat;">
    <div class="hero__overlay"></div>
    <div class="hero__content">
      <div class="hero__icon" aria-hidden="true"><i class="bi bi-journal-bookmark-fill"></i></div>
      <h2 class="hero__title">Funding & Partnerships</h2>
      <p class="hero__subtitle">
        Browse agencies supporting our research and how resources are allocated.
      </p>
      <div class="hero__actions">
        <a href="<?= BASE_URL ?>/public/research.php" class="btn">Explore Research</a>
      </div>
    </div>
  </article>

  <nav class="hero__dots" aria-label="Slides">
    <button class="is-active" aria-label="Slide 1" data-index="0"></button>
    <button aria-label="Slide 2" data-index="1"></button>
    <button aria-label="Slide 3" data-index="2"></button>
  </nav>
</section>

<!-- Features Section (About us) -->
<section class="feature">
  <div class="feature-card">
    <div class="feature-icon" aria-hidden="true"><i class="bi bi-info-circle-fill"></i></div>
    <h2 class="feature-title">About the Portal</h2>
    <p class="feature-text">
      The <strong>School of Mary Faculty and Research Portal</strong> provides a unified system for managing research,
      faculty information, funding records, and agencies involved in academic collaboration.
    </p>
  </div>
</section>

<section class="feature">
  <div class="feature-card">
    <div class="feature-icon" aria-hidden="true"><i class="bi bi-lock-fill"></i></div>
    <h2 class="feature-title">Admin Access</h2>
    <p class="feature-text">
      Authorized staff may log in to the Admin Dashboard to view and manage database records in real time.
    </p>
    <div class="feature-actions">
      <a href="<?= BASE_URL ?>/admin/login.php" class="btn btn--primary">Go to Admin Login</a>
    </div>
  </div>
</section>

<?php 
// Footer
require_once __DIR__ . '/../partials/site_footer.php'; 
?>