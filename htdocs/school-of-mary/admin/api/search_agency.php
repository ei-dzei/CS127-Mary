<?php 
    require_once __DIR__ . '/../../partials/init.php';
    /* ------------------------- Filters + Sorting + Pagination ------------------------- */
    $q      = trim($_GET['q'] ?? '');
    $type   = trim($_GET['type'] ?? '');
    $sort   = $_GET['sort'] ?? 'name_asc';      // name_asc|name_desc|id_asc|id_desc|recent_desc
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $per    = 5;
    $offset = ($page - 1) * $per;
?>