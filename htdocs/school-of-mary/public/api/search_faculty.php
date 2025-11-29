<?php 
    // FIX: Changed grave accent ` to single quote ' in the path.
    require_once __DIR__ . '/../../partials/init.php';

    /* --- Pagination setup --- */
    $perPage = 6;
    $page    = (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;
    $offset  = ($page - 1) * $perPage;
    
    /* --- List view --- */
    $q    = trim($_GET['q'] ?? '');
    $rank = trim($_GET['rank'] ?? '');
    $dept = trim($_GET['dept'] ?? '');
    
    /* Build WHERE and bind parameters (all named) */
    $where  = " WHERE 1=1 ";
    $params = [];

    if ($q !== '') {
        $where .= " AND (f.FACULTY_LNAME LIKE :q_like OR f.FACULTY_FNAME LIKE :q_like OR f.FACULTY_EMAIL LIKE :q_like)";
        // FIX: Use a single, unique named parameter for all LIKE conditions for simplicity.
        // PHP's PDO requires unique named parameters, even if the value is the same.
        // NOTE: The previous code had :q1, :q2, :q3 but the binding was repetitive, which is fine, 
        // but using a single placeholder is cleaner for simple LIKE searches.
        $params[':q_like'] = "%{$q}%"; // Changed from {$q}% to %{$q}% for more robust searching
    }
    if ($rank !== '') {
        $where .= " AND f.RANK_ID = :rank";
        $params[':rank'] = $rank;
    }
    if ($dept !== '') {
        $where .= " AND f.DEPT_ID = :dept";
        $params[':dept'] = $dept;
    }
    
    /* Count with the same filters (no ORDER/LIMIT) */
    $countSql = "
        SELECT COUNT(*)
        FROM FACULTY f
        JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
        JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
        " . $where;

    $countStmt = $pdo->prepare($countSql);
    // Use bindValue directly in the loop for binding all filter parameters
    foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
    $countStmt->execute();
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRows / $perPage);

    /* Paged SELECT using the same WHERE */
    $sql = "
        SELECT
            f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME, f.FACULTY_EMAIL,
            r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
        FROM FACULTY f
        JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
        JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
        " . $where . "
        ORDER BY f.FACULTY_LNAME, f.FACULTY_FNAME
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    
    /* Bind filter params */
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    
    /* Bind pagination as integers */
    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows  = $stmt->fetchAll();
    $total = count($rows);
    
    $output = "";
    $cards = "";
    if (!$rows) {
        $cards .= '<div class="panel">No matching faculty.</div>';
    } else {
        foreach ($rows as $row) {
            $cards .= '
            <div class="card">
                <div class="card__icon">
                    <i class="bi bi-person-circle" style="font-size: 2rem;"></i>
                </div>
                <div class="card__content">
                    <h3 class="card__title">' .
                        htmlspecialchars($row["FACULTY_LNAME"] . ", " . $row["FACULTY_FNAME"]) .
                    '</h3>
                    <p class="card__desc">' .
                        htmlspecialchars($row["RANK_DESCRIPTION"]) . ' · ' .
                        htmlspecialchars($row["DEPT_SPECIALIZATION"]) .
                    '</p>
                    <div class="card__meta">
                        <i class="bi bi-envelope-fill"></i> ' . htmlspecialchars($row["FACULTY_EMAIL"]) . '
                    </div>
                </div>
                <div class="card__actions">
                    <a class="btn small" href="' . BASE_URL . '/public/faculty.php?id=' . (int)$row["FACULTY_ID"] . '">
                        Read More
                    </a>
                </div>
            </div>';
        }
    }

    $pagination = "";
    $queryParams = $_GET;
    // Ensure 'page' is removed before rebuilding the query string
    unset($queryParams["page"]);
    $baseQuery = http_build_query($queryParams);
    $baseUrl = basename($_SERVER['PHP_SELF']) . "?" . ($baseQuery ? $baseQuery . "&" : "");
    $maxPage = 5;

    // The pagination logic here seems complex for a simple API result and is designed for a full-page reload, 
    // but we will keep the structure and just ensure the links are correct for AJAX use.

    if ($page > 1) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . ($page-1) . '" class="page-btn" title="Previous page">&#x276E;</a>';
    }

    $start = max(1, $page - floor($maxPage/2));
    $end = min($totalPages, $start + $maxPage - 1);

    if ($end - $start < $maxPage - 1) {
        $start = max(1, $end - $maxPage + 1);
    }

    if ($start > 1) {
        $pagination .= '<a href="' . $baseUrl . 'page=1" class="page-btn">1</a>';

        if ($start > 3) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . max(1, $page - 5) . '" class="page-btn" title = "Jump backward 5 pages">...</a>';
        }
        if ($start == 3) {
            $pagination .= '<a href="' . $baseUrl . 'page=2" class="page-btn">2</a>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . $i . '" class="page-btn ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end == $totalPages - 2) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . ($totalPages - 1) . '" class="page-btn">' . ($totalPages - 1) . '</a>';
        }
        if ($end < $totalPages - 2) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . min($totalPages, $page + 5) . '" class="page-btn" title ="Jump forward 5 pages">...</a>';
        }
        $pagination .= '<a href="' . $baseUrl . 'page=' . $totalPages . '" class="page-btn">' . $totalPages . '</a>';
    }

    if ($page < $totalPages) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . ($page+1) . '" class="page-btn" title= "Next page ">&#x276F;</a>';
    }


    $output = '
    <p class="muted" style="margin:6px 0 12px;"> Showing '  . (int)$totalRows . ($total===1 ? ' result' : ' results') .' | Page '.$page.' of '.  ($totalPages ===0? ' 1' : $totalPages)  .'</p>
    ';
    $output .='<div class = "cards">'.$cards.'</div>';
    $output .='<div class = "pagination">'.$pagination.'</div>';
    echo $output;
?>