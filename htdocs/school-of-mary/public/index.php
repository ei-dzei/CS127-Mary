<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../partials/init.php';

$pageTitle = 'Home';
require_once __DIR__ . '/../partials/site_header.php';
?>

<section class="panel fade-in" style="margin-top: 20px; text-align: center;">
  <h1 style="font-family: 'Patua One', serif; font-size: 2.4rem; margin-bottom: 0.5em;">
    Welcome to the School of Mary Research Portal
  </h1>
  <p style="max-width: 700px; margin: 0 auto 1.5em; line-height: 1.8;">
    Explore our community of scholars and discover their ongoing research, publications,
    and contributions. This portal serves both the public and our administrators — 
    providing transparent, real-time access to academic information.
  </p>

  <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
    <a href="<?= BASE_URL ?>/public/faculty.php" class="btn large">View Faculty</a>
    <a href="<?= BASE_URL ?>/public/research.php" class="btn large">View Research</a>
  </div>
</section>

<section class="fade-in" style="margin-top: 3rem;">
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

<section class="fade-in" style="margin-top: 3rem;">
  <div class="panel" style="text-align: left;">
    <h2 style="font-family: 'Patua One', serif;">Admin Access</h2>
    <p>
      Authorized staff may log in to the Admin Dashboard to view and manage database records in real time.
      The dashboard includes tools for CRUD operations, CSV imports/exports, and printable audit logs for compliance.
    </p>
    <a href="<?= BASE_URL ?>/admin/login.php" class="btn" style="margin-top: 1rem;">Go to Admin Login</a>
  </div>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
