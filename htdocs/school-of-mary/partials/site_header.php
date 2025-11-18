<?php
require_once __DIR__ . '/init.php';
$pageTitle = $pageTitle ?? 'School of Mary';
$isAdmin   = is_admin();
$inAdmin   = in_admin_area();

/* Active link helper */
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = preg_replace('#^' . preg_quote(BASE_URL, '#') . '#', '', $uri);
$path = $path === '' ? '/' : $path;

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
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/modal.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    /* =======================================
       THEME VARIABLES
       ======================================= */
    :root {
      /* Layout Specific */
      --sidebar-width: 230px; 
      --sidebar-collapsed-width: 0px; 
      
      /* Sidebar Colors (FIXED/UPDATED) */
      --color-sidebar-bg: #1d3557; 
      --color-sidebar-text: #f1f1f1; 
      --color-sidebar-hover: #26466d; 
      --color-sidebar-active: #457b9d; 
      --color-sidebar-sub-bg: #1f4062; 
    }

    /* =======================================
       RESET & BASE 
       ======================================= */
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f8f9fa;
        min-height: 100vh;
        position: relative;
    }

    /* =======================================
       APP LAYOUT (Fixed for Maximized Content)
       ======================================= */
    .app-wrapper {
        display: flex; 
        min-height: 100vh;
        position: relative;
    }
    .sidebar {
        width: var(--sidebar-width);
        flex-shrink: 0;
        height: 100vh;
        background: var(--color-sidebar-bg);
        padding: 20px 0;
        display: flex;
        flex-direction: column;
        position: fixed; 
        left: 0;
        top: 0;
        color: var(--color-sidebar-text);
        z-index: 1000;
        overflow-y: auto;
        transition: transform 0.3s ease-in-out, width 0.3s ease-in-out;
    }
    .main-content-area {
        flex-grow: 1; 
        margin-left: var(--sidebar-width); 
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        transition: margin-left 0.3s ease-in-out;
    }
    main.container {
        flex-grow: 1; 
        padding: 20px;
        width: 100%; 
    }
    .admin-stripe {
      padding: 10px;
      background: #ffd166;
      font-weight: bold;
      text-align: center;
    }

    /* =======================================
       SIDEBAR STYLING (Color Fixed)
       ======================================= */
    /* New Wrapper for Button and Logo */
    .sidebar .brand-wrapper {
        display: flex;
        align-items: center;
        padding: 0 20px 20px;
        gap: 10px;
    }
    .sidebar .brand {
        display: flex;
        align-items: center;
        padding: 0; /* Removed padding */
        gap: 10px;
        text-decoration: none; /* Ensure link decoration is off for the brand */
        color: var(--color-sidebar-text);
    }
    .sidebar .brand__logo {
        width: 40px;
        height: auto;
    }
    .sidebar a {
        padding: 12px 20px;
        display: block;
        text-decoration: none;
        color: var(--color-sidebar-text);
        font-size: 15px;
    }
    .sidebar a:hover {
        background: var(--color-sidebar-hover);
    }
    .sidebar .active {
        background: var(--color-sidebar-active);
        font-weight: bold;
    }
    .sidebar .sub-link {
        padding-left: 40px; 
        background: var(--color-sidebar-sub-bg);
    }
    .sidebar .sub-link:hover {
        background: var(--color-sidebar-hover);
    }
    .sidebar .sub-link.active {
        background: var(--color-sidebar-active);
    }
    .sidebar hr {
        border-color: rgba(255, 255, 255, 0.18);
        margin: 10px 0;
    }
    .sidebar .btn.small {
        background: var(--color-sidebar-active); 
        color: #fff;
        border: none;
        padding: 8px 20px;
        margin-top: 20px;
        margin-left: 20px;
        margin-right: 20px;
        display: block;
        text-align: center;
        border-radius: 4px;
        font-weight: bold;
        font-size: 15px;
    }
    .sidebar .btn.small:hover {
        background: #3e7099; 
    }
    .sidebar .btn.small a {
        display: inline;
        padding: 0;
        background: none;
    }

    /* =======================================
       TOGGLE BUTTON AND COLLAPSE LOGIC
       ======================================= */
    /* Styling the new internal toggle button (Hamburger icon) */
    #sidebar-toggle-internal {
        background: none;
        border: none;
        color: var(--color-sidebar-text); 
        font-size: 24px; 
        cursor: pointer;
        padding: 0; 
        margin-right: 10px; 
        transition: opacity 0.2s ease;
        line-height: 1; /* Ensure 3 lines show correctly */
    }
    #sidebar-toggle-internal:hover {
        opacity: 0.7;
    }

    /* Desktop Collapsed State */
    .app-wrapper.sidebar-closed .sidebar {
        width: var(--sidebar-collapsed-width);
        transform: translateX(-100%); 
    }
    .app-wrapper.sidebar-closed .main-content-area {
        margin-left: var(--sidebar-collapsed-width); 
    }
    
    /* Hide the external placeholder button */
    #sidebar-toggle {
        display: none !important;
    }


    /* Mobile/Tablet View */
    @media (max-width: 1024px) {
        /* Hide the internal button on mobile, as it conflicts with the overlay logic */
        #sidebar-toggle-internal {
            display: none; 
        }
        
        /* Add a mobile-specific external toggle button (required for mobile users to open the menu) */
        /* Use the old ID for this external button */
        #sidebar-toggle-mobile {
            display: block;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            padding: 8px 12px;
            background: var(--color-sidebar-active);
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .sidebar {
            transform: translateX(-100%); 
            box-shadow: 2px 0 5px rgba(0,0,0,0.5);
            width: var(--sidebar-width); 
        }
        .app-wrapper.sidebar-open .sidebar {
            transform: translateX(0); 
        }
        .main-content-area {
            margin-left: 0; 
        }
        .app-wrapper.sidebar-closed .sidebar {
            transform: translateX(-100%); 
        }
    }
  </style>

</head>

<body>

<button id="sidebar-toggle-mobile" class="sidebar-toggle-mobile">☰ Menu</button>


<div class="app-wrapper" id="app-wrapper">

    <aside class="sidebar" id="sidebar">

      <div class="brand-wrapper">
        <button id="sidebar-toggle-internal" aria-label="Toggle Menu">
            <span class="hamburger-icon">☰</span>
        </button>

        <a class="brand" href="<?= BASE_URL ?>/public/">
          <img class="brand__logo" src="<?= BASE_URL ?>/public/logo.png" alt="School of Mary">
          <span>School of Mary</span>
        </a>
      </div>
      
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