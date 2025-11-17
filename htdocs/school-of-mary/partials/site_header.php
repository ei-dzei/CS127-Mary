<?php
require_once __DIR__ . '/init.php';
$pageTitle = $pageTitle ?? 'Mary ';
$isAdmin   = is_admin();
$inAdmin   = in_admin_area();

/* ----- Active link helper ----- */
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = preg_replace('#^' . preg_quote(BASE_URL, '#') . '#', '', $uri);
$path = $path === '' ? '/' : $path; 
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle) ?> · School of Mary</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/public/smlogo.ico">
  <link rel="shortcut icon" href="<?= BASE_URL ?>/public/smlogo.ico" />

  <!-- Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles.css" />

  <!-- Scripts -->
  <script defer src="<?= BASE_URL ?>/assets/app.js"></script>
</head>

<body>
<header id="topbar" class="topbar">
  <div class="container topbar__inner">

    <a class="brand" href="<?= BASE_URL ?>/public/">
      <img class="brand__logo" src="<?= BASE_URL ?>/public/logo.png" alt="School of Mary">
      <span>School of Mary</span>
    </a>

    <nav class="mainnav">
      <!-- Public nav -->
      <a href="<?= BASE_URL ?>/public/" class="<?= current_path()=== BASE_URL.'/public/' || current_path()==='/public/' ? 'active' : '' ?>">Home</a>
      <a href="<?= BASE_URL ?>/public/faculty.php"   class="<?= strpos(current_path(), '/public/faculty') !== false ? 'active' : '' ?>">Faculty</a>
      <a href="<?= BASE_URL ?>/public/research.php"  class="<?= strpos(current_path(), '/public/research') !== false ? 'active' : '' ?>">Research</a>

      <?php if ($isAdmin): ?>
        <span class="divider" aria-hidden="true"></span>

        <!-- Admin dashboard -->
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= ($path==='/admin/dashboard.php' || $path==='/admin/' ) ? 'active' : '' ?>">Dashboard</a>

        <!-- Admin manage dropdown -->
        <div class="dropdown">
          <button class="btn small" type="button" aria-haspopup="true" aria-expanded="false">Manage ▾</button>
          <div class="dropdown__menu" role="menu">
            <a href="<?= BASE_URL ?>/admin/crud/faculty.php">Faculty</a>
            <a href="<?= BASE_URL ?>/admin/crud/research.php">Research</a>
            <a href="<?= BASE_URL ?>/admin/crud/assignment.php">Assignments</a>
            <a href="<?= BASE_URL ?>/admin/crud/agency.php">Agencies</a>
            <a href="<?= BASE_URL ?>/admin/crud/funding.php">Funding</a>
            <a href="<?= BASE_URL ?>/admin/audit_print.php">Audit (Print)</a>
          </div>
        </div>

        <a class="btn small" href="<?= BASE_URL ?>/admin/logout.php">Logout</a>
      <?php else: ?>
        <span class="divider" aria-hidden="true"></span>
        <a class="btn small" href="<?= BASE_URL ?>/admin/login.php">Admin Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($inAdmin): ?>
  <div class="admin-stripe">Admin Area</div>
<?php endif; ?>

<main class="container" style="padding-top:18px">