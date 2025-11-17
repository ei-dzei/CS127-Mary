<?php
require_once __DIR__ . '/init.php';
$pageTitle = $pageTitle ?? 'School of Mary';
$isAdmin   = is_admin();
$inAdmin   = in_admin_area();

/* Active link helper */
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

  <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/public/smlogo.ico">
  <link rel="shortcut icon" href="<?= BASE_URL ?>/public/smlogo.ico" />

  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/modal.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    /* --- SIDE NAVIGATION (Inline Styles) --- */

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f8f9fa;
      /* Removed display: flex on body to prevent layout conflicts with position: fixed */
    }

    /* Sidebar container */
    .sidebar {
      width: 230px;
      height: 100vh; /* Full viewport height */
      background: #1d3557;
      padding: 20px 0;
      display: flex;
      flex-direction: column;
      position: fixed; /* CRITICAL: Keeps it locked on the left */
      left: 0;
      top: 0;
      color: #fff;
      z-index: 1000;
      overflow-y: auto;
    }

    .sidebar .brand {
      display: flex;
      align-items: center;
      padding: 0 20px 20px;
      gap: 10px;
    }

    .sidebar .brand__logo {
      width: 40px;
      height: auto;
    }

    /* Navigation links */
    .sidebar a {
      padding: 12px 20px;
      display: block;
      text-decoration: none;
      color: #f1f1f1;
      font-size: 15px;
    }

    .sidebar a:hover {
      background: #26466d;
    }

    .sidebar .active {
      background: #457b9d;
      font-weight: bold;
    }

    /* Styles for the former sub-items to give them an indented look */
    .sidebar .sub-link {
        padding-left: 40px; 
        background: #1f4062; /* Slightly different background for visual grouping */
    }
    .sidebar .sub-link:hover {
        background: #26466d;
    }
    .sidebar .sub-link.active {
        background: #457b9d;
    }


    /* Main container shift - CRITICAL: Pushes content over */
    main.container {
      margin-left: 250px !important; /* Sidebar width (230px) + margin */
      padding-top: 20px !important;
      width: calc(100% - 250px);
    }

    .admin-stripe {
      margin-left: 250px; /* Shift stripe over too */
      padding: 10px;
      background: #ffd166;
      font-weight: bold;
      text-align: center;
    }

  </style>

  <script defer src="<?= BASE_URL ?>/assets/app.js"></script>
</head>

<body>

<aside class="sidebar">

  <a class="brand" href="<?= BASE_URL ?>/public/">
    <img class="brand__logo" src="<?= BASE_URL ?>/public/logo.png" alt="School of Mary">
    <span>School of Mary</span>
  </a>

  <a href="<?= BASE_URL ?>/public/"
     class="<?= current_path()=== BASE_URL.'/public/' || current_path()==='/public/' ? 'active' : '' ?>">
     Home
  </a>

  <a href="<?= BASE_URL ?>/public/faculty.php"
     class="<?= strpos(current_path(), '/public/faculty') !== false ? 'active' : '' ?>">
     Faculty
  </a>

  <a href="<?= BASE_URL ?>/public/research.php"
     class="<?= strpos(current_path(), '/public/research') !== false ? 'active' : '' ?>">
     Research
  </a>

  <hr style="border-color:#ffffff30; margin:10px 0;" />

  <?php if ($isAdmin): ?>

    <a href="<?= BASE_URL ?>/admin/dashboard.php"
       class="<?= ($path==='/admin/dashboard.php' || $path==='/admin/') ? 'active' : '' ?>">
       Dashboard
    </a>

    <a href="<?= BASE_URL ?>/admin/crud/faculty.php" class="sub-link <?= strpos($path, '/admin/crud/faculty.php') !== false ? 'active' : '' ?>">
        Faculty
    </a>
    <a href="<?= BASE_URL ?>/admin/crud/research.php" class="sub-link <?= strpos($path, '/admin/crud/research.php') !== false ? 'active' : '' ?>">
        Research
    </a>
    <a href="<?= BASE_URL ?>/admin/crud/assignment.php" class="sub-link <?= strpos($path, '/admin/crud/assignment.php') !== false ? 'active' : '' ?>">
        Assignments
    </a>
    <a href="<?= BASE_URL ?>/admin/crud/agency.php" class="sub-link <?= strpos($path, '/admin/crud/agency.php') !== false ? 'active' : '' ?>">
        Agencies
    </a>
    <a href="<?= BASE_URL ?>/admin/crud/funding.php" class="sub-link <?= strpos($path, '/admin/crud/funding.php') !== false ? 'active' : '' ?>">
        Funding
    </a>
    <a href="<?= BASE_URL ?>/admin/audit_print.php" class="sub-link <?= strpos($path, '/admin/audit_print.php') !== false ? 'active' : '' ?>">
        Audit (Print)
    </a>

    <a class="btn small" style="margin-top:20px; padding-left:20px;"
       href="<?= BASE_URL ?>/admin/logout.php">
       Logout
    </a>

  <?php else: ?>

    <a class="btn small" href="<?= BASE_URL ?>/admin/login.php" style="padding-left:20px;">
      Admin Login
    </a>

  <?php endif; ?>

</aside>

<?php if ($inAdmin): ?>
  <div class="admin-stripe">Admin Area</div>
<?php endif; ?>

<main class="container">