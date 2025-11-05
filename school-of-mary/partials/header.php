<?php
if (!isset($pageTitle)) {
    $pageTitle = "School of Mary";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle); ?> — School of Mary</title>
    <link rel="icon" type="image/x-icon" href="/public/smlogo.ico">
    <link href="/assets/styles.css" rel="stylesheet"/>
</head>
<body>
    <header class="topbar">
        <div class="container">
            <a class="brand" href="/public/index.php">
                <img src="/public/logo.png" alt="School of Mary Logo" class="logo">
                <span class="logo"></span>
                <span class="brand-text">School of Mary</span>
            </a>
            <nav class="nav">
                <a href="/public/faculty.php">Faculty</a>
                <a href="/public/research.php">Research</a>
                <a class="admin-link" href="/public/index.php#admin">Admin</a>
            </nav>
        </div>
    </header>
    <main class="page">
        