<?php
require_once __DIR__ . '/init.php';
$pageTitle = $pageTitle ?? 'School of Mary';
$isAdmin   = is_admin();
$inAdmin   = in_admin_area();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle) ?> · School of Mary</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="/public/smlogo.ico" />
  <link rel="shortcut icon" href="/public/smlogo.ico" />

  <!-- Styles -->
  <link rel="stylesheet" href="/assets/styles.css" />
  <link rel="stylesheet" href="/assets/modal.css" />

  <!-- Scripts -->
  <script defer src="/assets/app.js"></script>
</head>

<body>
<header id="topbar" class="topbar">
  <div class="container topbar__inner">
    <a class="brand" href="/public/">
      <img src="/public/logo.png" alt="School of Mary" class="brand__logo" />
      <span>School of Mary</span>
    </a>

    <nav class="mainnav">
      <a href="/public/" class="<?= current_path()==='/public/'?'active':'' ?>">Home</a>
      <a href="/public/faculty.php" class="<?= strpos(current_path(),'/public/faculty')!==false?'active':'' ?>">Faculty</a>
      <a href="/public/research.php" class="<?= strpos(current_path(),'/public/research')!==false?'active':'' ?>">Research</a>

      <?php if ($isAdmin): ?>
        <div class="divider"></div>
        <a href="/admin/dashboard.php" class="<?= current_path()==='/admin/dashboard.php'?'active':'' ?>">Dashboard</a>
        <div class="dropdown">
          <button class="btn small">Manage ▾</button>
          <div class="dropdown__menu">
            <a href="/admin/crud/faculty.php">Faculty</a>
            <a href="/admin/crud/research.php">Research</a>
            <a href="/admin/crud/assignment.php">Assignments</a>
            <a href="/admin/crud/agency.php">Agencies</a>
            <a href="/admin/crud/funding.php">Funding</a>
            <a href="/admin/audit_print.php">Audit (Print)</a>
          </div>
        </div>
        <a class="btn small" href="/admin/logout.php">Logout</a>
      <?php else: ?>
        <a class="btn small" href="/admin/login.php">Admin Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($inAdmin): ?>
  <div class="admin-stripe">Admin Area</div>
<?php endif; ?>

<main class="container" style="padding-top:18px">
