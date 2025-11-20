<?php
require_once __DIR__ . '/init.php';
$pageTitle = $pageTitle ?? 'Mary ';
$isAdmin   = is_admin();
$inAdmin   = in_admin_area();

/* Active link helper */
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = preg_replace('#^' . preg_quote(BASE_URL, '#') . '#', '', $uri);
$path = $path === '' ? '/' : $path;

// Assuming you have a function to get the current path for active links
if (!function_exists('current_path')) {
    function current_path() {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    }
}
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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    /* ---------------------------------------------------------------- */
    /* INLINE STYLES (MODIFIED FOR CORRECT MAXIMIZED LAYOUT)            */
    /* ---------------------------------------------------------------- */
    
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f8f9fa;
        /* Ensures the viewport height is used */
        min-height: 100vh; 
        position: relative;
    }

    /* === 1. Layout Container === */
    .app-wrapper {
        /* This Flex container holds the fixed sidebar and the growing content area */
        display: flex; 
        min-height: 100vh;
    }

    /* === 2. Sidebar (Desktop View) === */
    .sidebar {
        width: 230px;
        flex-shrink: 0;
        height: 100vh;
        background: #1d3557;
        padding: 20px 0;
        display: flex;
        flex-direction: column;
        position: fixed; /* Lock sidebar position */
        left: 0;
        top: 0;
        color: #fff;
        z-index: 1000;
        overflow-y: auto;
        transition: transform 0.3s ease-in-out;
    }

    /* === 3. Main Content Area (NEW/FIXED) === */
    .main-content-area {
        /* The key to shifting and maximizing the height */
        flex-grow: 1; /* Occupy remaining horizontal space */
        margin-left: 230px; /* Shift content area over by sidebar width */
        min-height: 100vh;
        display: flex;
        flex-direction: column; /* Allows main content and footer to stack and stretch */
    }

    /* === 4. Main Content Container (inside the new area) === */
    main.container {
        flex-grow: 1; /* Content area takes available vertical space */
        padding: 20px;
        width: 100%; 
    }

    /* === 5. Sidebar Styles (Keep as originally defined) === */
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
    .sidebar .sub-link {
        padding-left: 40px; 
        background: #1f4062;
    }
    .sidebar .sub-link:hover {
        background: #26466d;
    }
    .sidebar .sub-link.active {
        background: #457b9d;
    }
    .admin-stripe {
      padding: 10px;
      background: #ffd166;
      font-weight: bold;
      text-align: center;
    }
    
    /* === 6. Mobile/Toggle Styles (CRITICAL) === */
    #sidebar-toggle {
        display: none; /* Hidden by default */
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1001;
        padding: 8px 12px;
        background: #457b9d; /* Use an accent color */
        color: #fff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    @media (max-width: 1024px) {
        #sidebar-toggle {
            display: block; /* Show button on small screens */
        }
        .sidebar {
            transform: translateX(-100%); /* Hide sidebar off-screen initially */
            box-shadow: 2px 0 5px rgba(0,0,0,0.5); /* Add shadow for overlay effect */
        }
        .app-wrapper.sidebar-open .sidebar {
            transform: translateX(0); /* Show sidebar when class is applied */
        }
        /* Remove margin shift on mobile */
        .main-content-area {
            margin-left: 0; 
        }
    }
    /* ----------------------------------------------------------------- */
  </style>

  <script defer src="<?= BASE_URL ?>/assets/app.js"></script>
</head>

<body>

<button id="sidebar-toggle">☰ Menu</button>

<div class="app-wrapper" id="app-wrapper">

    <aside class="sidebar" id="sidebar">

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

    <div class="main-content-area">

        <?php if ($inAdmin): ?>
          <div class="admin-stripe">Admin Area</div>
        <?php endif; ?>

        <main class="container">