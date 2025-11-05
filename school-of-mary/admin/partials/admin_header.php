<?php
if (!isset($pageTitle)) $pageTitle = 'Admin';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../guard.php';
require_admin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?php echo htmlspecialchars($pageTitle); ?> — Admin · School of Mary</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="/assets/styles.css" rel="stylesheet" />
  <style>
    .admin {
        display:flex;
        gap:18px
    }

    .side {
        width:240px;
        background:#fff;
        border-right:1px solid var(--border);
        min-height:100vh;
        padding:16px 12px;
        position:sticky;top:0
    }

    .side .brand-mini{
        margin-bottom:12px
    }

    .side a{
        display:block;
        padding:10px 12px;
        border-radius:10px;
        color:var(--ink)
    }

    .side a.active, .side a:hover{
        background:#f3f4f6
    }

    .content{
        flex:1;
        padding:24px
    }

    .statgrid{
        display:grid;
        grid-template-columns:repeat(12,1fr);
        gap:16px
    }

    .stat{
        grid-column: span 3;
        background:#fff;
        border:1px solid var(--border);
        border-radius:14px;
        box-shadow:var(--shadow);
        padding:16px
    }

    .stat .kpi{
        font-size:28px;
        font-weight:700
    }
    
    .panel{
        background:#fff;
        border:1px solid var(--border);
        border-radius:14px;
        box-shadow:var(--shadow);
        padding:16px
    }
  </style>
</head>
<body>
<div class="admin">
  <aside class="side">
    <div class="brand-mini">School of Mary</div>
    <div class="muted" style="margin-bottom:12px">Signed in as<br><b><?php echo htmlspecialchars($_SESSION['admin_user']); ?></b></div>
    <nav>
      <a href="/admin/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active':''; ?>">Dashboard</a>
      <a href="/admin/crud/assignment.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='assignment.php'?'active':''; ?>">Assignments</a>
      <a href="/admin/crud/faculty.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='faculty.php'?'active':''; ?>">Faculty</a>
      <a href="/admin/crud/research.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='research.php'?'active':''; ?>">Research</a>
      <a href="/admin/crud/agency.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='agency.php'?'active':''; ?>">Agencies</a>
      <a href="/admin/crud/funding.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='funding.php'?'active':''; ?>">Funding</a>
    </nav>
    <div style="margin-top:auto">
      <a href="/admin/logout.php">Logout</a>
    </div>
  </aside>
  <main class="content">
