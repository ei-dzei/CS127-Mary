<?php
require_once __DIR__ . '/..//../config/utils.php';
require_once __DIR__ . '/../auth.php';
require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin — School of Mary</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/../assets/admin.css">
<link rel="stylesheet" href="/../assets/modal.css">
<link rel="stylesheet" href="/../assets/notify.css">
<link rel="stylesheet" href="/../assets/forms.css">
</head>
<body>
<div class="container">
  <aside class="sidebar">
    <div class="brand">
      <img src="/../public/logo.png" alt="">
      <div>School of Mary</div>
    </div>
    <nav class="nav">
      <a href="/../dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF'])==='dashboard.php'?'active':'';?>">Dashboard</a>
      <div class="muted" style="padding:8px 10px 6px">Data</div>
      <a href="/../crud/faculty.php"   class="<?php echo basename($_SERVER['PHP_SELF'])==='faculty.php'?'active':'';?>">Faculty</a>
      <a href="/../crud/research.php"  class="<?php echo basename($_SERVER['PHP_SELF'])==='research.php'?'active':'';?>">Research</a>
      <a href="/../crud/assignment.php"class="<?php echo basename($_SERVER['PHP_SELF'])==='assignment.php'?'active':'';?>">Assignment</a>
      <a href="/../crud/agency.php"    class="<?php echo basename($_SERVER['PHP_SELF'])==='agency.php'?'active':'';?>">Agencies</a>
      <a href="/../crud/funding.php"   class="<?php echo basename($_SERVER['PHP_SELF'])==='funding.php'?'active':'';?>">Funding</a>
      <div class="muted" style="padding:8px 10px 6px">System</div>
      <a href="/../audit_print.php" target="_blank">Audit Log (Print)</a>
    </nav>
  </aside>
  <div class="main">
    <header class="topbar">
      <div class="muted">Admin</div>
      <div style="display:flex;gap:8px;align-items:center">
        <span class="muted"><?php echo htmlspecialchars($_SESSION['admin_user']); ?></span>
        <a class="btn" href="/../admin/logout.php">Logout</a>
      </div>
    </header>
    <main class="content">
