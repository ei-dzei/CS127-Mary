<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../partials/init.php';
$pageTitle = 'Home';
require_once __DIR__ . '/../partials/site_header.php';
?>

<!-- HERO / SLIDER -->
<section class="hero fade-in" aria-label="Featured highlights">

  <!-- Slide 1 -->
  <article class="hero__slide is-active"
           style="background: url('bg1.png') center/cover no-repeat;">
    <div class="hero__overlay"></div>
    <div class="hero__content">
      <div class="hero__icon" aria-hidden="true">🎓</div>
      <h1 class="hero__title">School of Mary Research Portal</h1>
      <p class="hero__subtitle">
        Discover faculty expertise, funded projects, and academic impact—all in one place.
      </p>
      <div class="hero__actions">
        <a href="<?= BASE_URL ?>/public/faculty.php" class="btn">Get Started</a>
        <a href="<?= BASE_URL ?>/public/research.php" class="btn" style="background:#0a4c80">Browse Research</a>
      </div>
    </div>
  </article>

  <!-- Slide 2 -->
  <article class="hero__slide"
           style="background: url('bg2.png') center/cover no-repeat;">
    <div class="hero__overlay"></div>
    <div class="hero__content">
      <div class="hero__icon" aria-hidden="true">🔬</div>
      <h2 class="hero__title">Explore Ongoing & Completed Studies</h2>
      <p class="hero__subtitle">
        Filter by status and date range, and dive into details on funding and collaborators.
      </p>
      <div class="hero__actions">
        <a href="<?= BASE_URL ?>/public/research.php" class="btn">View Studies</a>
      </div>
    </div>
  </article>

  <!-- Slide 3 -->
  <article class="hero__slide"
           style="background: url('bg3.png') center/cover no-repeat;">
    <div class="hero__overlay"></div>
    <div class="hero__content">
      <div class="hero__icon" aria-hidden="true">🤝</div>
      <h2 class="hero__title">Funding & Partnerships</h2>
      <p class="hero__subtitle">
        See agencies supporting our research and how resources are allocated.
      </p>
      <div class="hero__actions">
        <a href="<?= BASE_URL ?>/public/research.php" class="btn">View Studies</a>
      </div>
    </div>
  </article>

  <!-- Pagination dots -->
  <nav class="hero__dots" aria-label="Slides">
    <button class="is-active" aria-label="Slide 1" data-index="0"></button>
    <button aria-label="Slide 2" data-index="1"></button>
    <button aria-label="Slide 3" data-index="2"></button>
  </nav>
</section>

<!-- About us -->
<section class="fade-in" style="margin-top: 2rem;">
  <div class="panel" style="text-align: left;">
    <h2 style="font-family: 'Patua One', serif;">About the Portal</h2>
    <p>
      The <strong>School of Mary Research Portal</strong> provides a unified system for managing 
      research, faculty information, funding records, and agencies involved in academic collaboration.
      While the general public can browse and view data, administrators can securely log in to manage 
      records, ensuring accurate and up-to-date information.
    </p>
  </div>
</section>

<section class="fade-in" style="margin-top: 2rem;">
  <div class="panel" style="text-align: left;">
    <h2 style="font-family: 'Patua One', serif;">Admin Access</h2>
    <p>
      Authorized staff may log in to the Admin Dashboard to view and manage database records in real time.
      The dashboard includes tools for CRUD operations, CSV imports/exports, and printable audit logs.
    </p>
    <a href="<?= BASE_URL ?>/admin/login.php" class="btn" style="margin-top: 1rem;">Go to Admin Login</a>
  </div>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
