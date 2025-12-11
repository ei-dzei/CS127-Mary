<?php
// Load shared helpers
require_once __DIR__ . '/init.php';
$pageTitle = $pageTitle ?? 'Mary ';
$isAdmin   = is_admin();
$inAdmin   = in_admin_area();

// Active link helper
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = preg_replace('#^' . preg_quote(BASE_URL, '#') . '#', '', $uri);
$path = $path === '' ? '/' : $path;
// Fallback safety
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
    /* Global Styles */
    
    body {
        margin: 0;
        font-family: 'Newsreader', serif;
        background: #f8f9fa;
        min-height: 100vh; 
        position: relative;
    }

    /* Layout Container */
    .app-wrapper {
        display: flex; 
        min-height: 100vh;
        transition: all 0.3s ease; 
    }

    /* Sidebar (Base Styles) */
    .sidebar {
        width: 250px; 
        flex-shrink: 0;
        height: 100vh;
        background: #1d3557;
        padding: 0; 
        display: flex;
        flex-direction: column;
        position: fixed;
        left: 0;
        top: 0;
        color: #fff;
        z-index: 1000;
        transition: width 0.3s ease-in-out;
        white-space: nowrap;
        scrollbar-width: none; /*hide scrollbar*/
        -ms-overflow-style: none; /*hide scrollbar*/
        overflow: hidden;
    }
    .sidebar::-webkit-scrollbar {
        display: none; /*hide scrollbar*/
    }

    /* Main Content Area */
    .main-content-area {
        flex-grow: 1;
        margin-left: 250px; 
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        transition: margin-left 0.3s ease-in-out;
    }

    /* Sidebar Link Styles */
    .sidebar .brand {
        display: flex;
        align-items: center;
        height: 70px; 
        padding: 0 20px;
        background: #162a45; 
        gap: 15px;
    }
    .sidebar .brand__logo {
        width: 40px;
        min-width: 40px; 
        height: auto;
    }
    
    .sidebar-menu {
        padding: 0px 0;
        overflow-y: hidden;
        overflow-x: hidden;
        flex-grow: 1; 
        display: flex;
        flex-direction: column;
    }

    .sidebar a {
        padding: 15px 25px; 
        display: flex;
        align-items: center;
        gap: 15px;
        text-decoration: none;
        color: #b8c7d9;
        font-size: 16px;
        transition: all 0.2s;
        border-left: 4px solid transparent;
    }
    .sidebar a i {
        font-size: 1.3rem;
        min-width: 30px; 
        text-align: center;
        display: inline-block;
    }
    
    .sidebar a:hover {
        background: #26466d;
        color: #fff;
    }
    .sidebar .active {
        background: #2a4f78;
        color: #fff;
        border-left-color: #ffd166; 
    }
    .sidebar .sub-link {
        background: #182e4d;
    }

    .mt-auto-custom {
        margin-top: auto; 
        border-top: 1px solid #ffffff10;
    }

    /* The "Desktop Collapse" Logic */
    body.collapsed .sidebar {
        width: 80px; 
    }
    body.collapsed .main-content-area {
        margin-left: 80px;
    }

    body.collapsed .sidebar .link-text, 
    body.collapsed .sidebar .brand span {
        opacity: 0;
        pointer-events: none;
        display: none;
        font-family: 'Newsreader', serif;
        padding-left: 0;
        padding-right: 0;
        gap: 0;
    }

    /* Center the icons strictly when collapsed */
    body.collapsed .sidebar a {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
        gap: 0;
    }
    
    body.collapsed .sidebar a i {
        margin: 0;
    }

    /* The Collapse Toggle Button (Desktop) */
    .desktop-toggler {
        position: fixed;
        background: #162a45;
        left: 250px;
        top: 17px;
        padding: 10px;
        border: none;
        color: #ffd166; 
        height: 33px; 
        width: 25px;
        cursor: pointer;
        display: flex;
        text-align: center;
        justify-content: center;
        align-items: center;
        transition: background 0.3s ease;
        border-top: 1px solid #ffffff20;
        border-radius: 0 50% 50% 0;
        box-shadow: 2px 2px 8px rgba(0,0,0,0.2);
        transition: left 0.3s ease-in-out, background 0.3s ease;
    }
    .desktop-toggler:hover {background: #26466d;}
    .desktop-toggler i {
        font-size: 1rem; 
        font-weight: bold;
        transition: transform 0.3s ease;
    }
    body.collapsed .desktop-toggler {left: 80px;}
    body:not(.collapsed) .desktop-toggler .i {
        transform: rotate(180deg);
    }
    /* Mobile Logic (Max-width 1024px) */
    #mobile-toggle {
        display: none;
    }

    @media (max-width: 1024px) {
        /* Reset collapse logic on mobile */
        body.collapsed .sidebar { width: 250px; }
        body.collapsed .main-content-area { margin-left: 0; }
        body.collapsed .sidebar .link-text { display: inline; opacity: 1; }
        .desktop-toggler { display: none; }

        /* Show mobile toggle */
        #mobile-toggle {
            display: block;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            padding: 10px 14px;
            background: #1d3557; 
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .sidebar {
            transform: translateX(-100%); 
            width: 250px; 
        }
        .main-content-area {
            margin-left: 0; 
        }
        
        .app-wrapper.mobile-open .sidebar {
            transform: translateX(0); 
        }
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Memory Logic
        // Check if user previously preferred it 'expanded'
        const savedState = localStorage.getItem('sidebar-state');
        const body = document.body;
        const desktopBtn = document.getElementById('desktop-collapse-btn');
        const icon = desktopBtn ? desktopBtn.querySelector('i') : null;

        // If 'expanded' was saved in memory, remove the default 'collapsed' class
        if (savedState === 'expanded') {
            body.classList.remove('collapsed');
            // Update icon to the "collapse" arrow (left)
            if(icon) {
                icon.classList.remove('bi-chevron-right');
                icon.classList.add('bi-chevron-left');
            }
        } else {
            body.classList.add('collapsed');
            // Update icon to the "collapse" arrow (left)
            if(icon) {
                icon.classList.remove('bi-chevron-left');
                icon.classList.add('bi-chevron-right');
            }
        }

        // Mobile Toggle Logic
        const mobileBtn = document.getElementById('mobile-toggle');
        const appWrapper = document.getElementById('app-wrapper');
        
        if (mobileBtn) {
            mobileBtn.addEventListener('click', () => {
                appWrapper.classList.toggle('mobile-open');
            });
        }

        // Desktop Collapse Logic
        if (desktopBtn) {
            desktopBtn.addEventListener('click', () => {
                // Toggle the class
                body.classList.toggle('collapsed');
                
                // Determine new state for Memory and Icon
                const isNowCollapsed = body.classList.contains('collapsed');
                
                if (isNowCollapsed) {
                    // desktopBtn.style.left = "80px";
                    // It is now small
                    localStorage.setItem('sidebar-state', 'collapsed');
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                } else {
                    // It is now big
                    // desktopBtn.style.left = "250px";
                    localStorage.setItem('sidebar-state', 'expanded');
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-left');
                }
            });
        }
    });
  </script>
</head>

<body class="collapsed">

<div class="app-wrapper" id="app-wrapper">

    <aside class="sidebar" id="sidebar">
      <button class="desktop-toggler" id="desktop-collapse-btn" title="Toggle Sidebar">
        <i class="bi bi-chevron-right"></i>
      </button>
      <a class="brand" href="<?= BASE_URL ?>/public/">
        <img class="brand__logo" src="<?= BASE_URL ?>/public/logo.png" alt="Logo" onerror="this.style.display='none'">
        <span class="link-text">School of Mary</span>
      </a>

      <div class="sidebar-menu">

          <a href="<?= BASE_URL ?>/public/"
             class="<?= current_path()=== BASE_URL.'/public/' || current_path()==='/public/' ? 'active' : '' ?>">
             <i class="bi bi-house-door-fill"></i>
             <span class="link-text">Home</span>
          </a>

          <a href="<?= BASE_URL ?>/public/faculty.php"
             class="<?= strpos(current_path(), '/public/faculty') !== false ? 'active' : '' ?>">
             <i class="bi bi-people-fill"></i>
             <span class="link-text">Faculty</span>
          </a>

          <a href="<?= BASE_URL ?>/public/research.php"
             class="<?= strpos(current_path(), '/public/research') !== false ? 'active' : '' ?>">
             <i class="bi bi-journal-bookmark-fill"></i>
             <span class="link-text">Research</span>
          </a>

          <hr style="border-color:#ffffff10; margin:10px 20px;" />

          <?php if ($isAdmin): ?>
            
            <a href="<?= BASE_URL ?>/admin/dashboard.php"
               class="<?= ($path==='/admin/dashboard.php' || $path==='/admin/') ? 'active' : '' ?>">
               <i class="bi bi-speedometer2"></i>
               <span class="link-text">Dashboard</span>
            </a>

            <a href="<?= BASE_URL ?>/admin/crud/faculty.php" class="sub-link <?= strpos($path, '/admin/crud/faculty.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-badge"></i>
                <span class="link-text">Faculty</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/crud/research.php" class="sub-link <?= strpos($path, '/admin/crud/research.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-book"></i>
                <span class="link-text">Research</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/crud/assignment.php" class="sub-link <?= strpos($path, '/admin/crud/assignment.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-list-check"></i>
                <span class="link-text">Assignments</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/crud/agency.php" class="sub-link <?= strpos($path, '/admin/crud/agency.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-building"></i>
                <span class="link-text">Agencies</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/crud/funding.php" class="sub-link <?= strpos($path, '/admin/crud/funding.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                <span class="link-text">Funding</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/audit_print.php" class="sub-link <?= strpos($path, '/admin/audit_print.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-printer"></i>
                <span class="link-text">Audit Log</span>
            </a>

            <a class="mt-auto-custom" href="<?= BASE_URL ?>/admin/logout.php">
               <i class="bi bi-box-arrow-right"></i>
               <span class="link-text">Logout</span>
            </a>

          <?php else: ?>

            <a class="mt-auto-custom" href="<?= BASE_URL ?>/admin/login.php">
              <i class="bi bi-person-workspace"></i>
              <span class="link-text">Admin Login</span>
            </a>

          <?php endif; ?>
      </div>
    </aside>

    <div class="main-content-area">

        <?php if ($inAdmin): ?>
          <div class="admin-stripe">Admin Area</div>
        <?php endif; ?>

        <main class="container">