<?php 
    require_once __DIR__ . '/../../partials/init.php';
    
    // --- Configuration ---
    $perPage = 6;
    $page    = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $offset  = ($page - 1) * $perPage;

    // --- Input and Filter Initialization ---
    $q      = trim($_GET['q'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $from   = trim($_GET['from'] ?? '');
    $to     = trim($_GET['to'] ?? '');

    /* Build WHERE clause with named parameters */
    $where   = [];
    $params  = [];

    if ($q !== '') {
        $where[]        = "re.RESEARCH_TITLE LIKE :q";
        $params[':q']   = "%{$q}%";
    }
    if ($status !== '') {
        $where[]           = "re.RESEARCH_STATUS = :status";
        $params[':status'] = $status;
    }
    if ($from !== '') {
        $where[]         = "re.RESEARCH_STARTDATE >= :from";
        $params[':from'] = $from;
    }
    if ($to !== '') {
        // Find records that ended by this date OR are Ongoing (NULL ENDDATE) but started before or on this date
        $where[]        = "(re.RESEARCH_ENDDATE <= :to OR (re.RESEARCH_ENDDATE IS NULL AND re.RESEARCH_STARTDATE <= :to2))";
        $params[':to']  = $to;
        $params[':to2'] = $to;
    }

    $whereSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

    // --- 1. Count Total Rows ---
    $countSql = "SELECT COUNT(*) FROM RESEARCH re WHERE 1=1 {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    
    // Bind parameters for COUNT query
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    
    $countStmt->execute();
    $totalRows  = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    // --- 2. Fetch Paginated Rows ---
    $listSql = "
        SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS,
                re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE
        FROM RESEARCH re
        WHERE 1=1 {$whereSql}
        ORDER BY re.RESEARCH_STARTDATE DESC, re.RESEARCH_ID DESC
        LIMIT :limit OFFSET :offset
    ";
    $listStmt = $pdo->prepare($listSql);
    
    // Bind parameters for LIST query
    foreach ($params as $k => $v) {
        $listStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $listStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $listStmt->execute();
    $rows  = $listStmt->fetchAll();
    $total = count($rows);

    $cards = "";

    // --- 3. Generate Research Cards HTML ---
    if (!$rows) {
        $cards = '<div class="panel">No matching research.</div>';
    } else {
        foreach ($rows as $row) {
            $endDate = $row['RESEARCH_ENDDATE'] 
                ? ' · <i class="bi bi-calendar-check-fill"></i> End: ' . htmlspecialchars($row['RESEARCH_ENDDATE']) 
                : '';

            $cards .= '
            <div class="card">
                <div class="card__icon">
                    <i class="bi bi-journal-code" style="font-size: 2rem;"></i>
                </div>
                <div class="card__content">
                    <h3 class="card__title">' . htmlspecialchars($row['RESEARCH_TITLE']) .'</h3>
                    <p class="card__desc">Status: ' . htmlspecialchars($row['RESEARCH_STATUS']) . '</p>
                    <div class="card__meta">
                        <i class="bi bi-calendar-date-fill"></i> Start: ' . htmlspecialchars($row['RESEARCH_STARTDATE']) . $endDate . '
                    </div>
                    <div class="card__actions">
                        <button class="btn small"
                        data-read-more
                        data-type="research"
                        data-id="' . (int)$row['RESEARCH_ID']. '">
                        Read More
                        </button>
                    </div>
                </div>
            </div>';
        }
    }
    
    // --- 4. Generate Pagination HTML ---
    $pagination = "";
    $queryParams = $_GET;
    unset($queryParams["page"]);
    $baseQuery = http_build_query($queryParams);
    $baseUrl = "?" . ($baseQuery ? $baseQuery . "&" : "");
    $maxPage = 5;

    // Previous button
    if ($page > 1) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . ($page-1) . '" class="page-btn" title="Previous page">&#x276E;</a>';
    }

    // Page range logic
    $start = max(1, $page - floor($maxPage/2));
    $end = min($totalPages, $start + $maxPage - 1);

    if ($end - $start < $maxPage - 1) {
        $start = max(1, $end - $maxPage + 1);
    }

    // Start ellipses/page 1
    if ($start > 1) {
        $pagination .= '<a href="' . $baseUrl . 'page=1" class="page-btn">1</a>';
        if ($start > 3) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . max(1, $page - 5) . '" class="page-btn" title = "Jump backward 5 pages">...</a>';
        }
        if ($start == 3) {
            $pagination .= '<a href="' . $baseUrl . 'page=2" class="page-btn">2</a>';
        }
    }

    // Numbered pages
    for ($i = $start; $i <= $end; $i++) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . $i . '" class="page-btn ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
    }

    // End ellipses/last page
    if ($end < $totalPages) {
        if ($end == $totalPages - 2) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . ($totalPages - 1) . '" class="page-btn">' . ($totalPages - 1) . '</a>';
        }
        if ($end < $totalPages - 2) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . min($totalPages, $page + 5) . '" class="page-btn" title ="Jump forward 5 pages">...</a>';
        }
        $pagination .= '<a href="' . $baseUrl . 'page=' . $totalPages . '" class="page-btn">' . $totalPages . '</a>';
    }

    // Next button
    if ($page < $totalPages) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . ($page+1) . '" class="page-btn" title= "Next page ">&#x276F;</a>';
    }

    // --- 5. Final Output ---
    $output = '<p class="muted" style="margin:6px 0 12px;">Showing ' .(int)$totalRows .  ($totalRows===1 ? " project" : " projects").'</p>';
    $output .= '<div class = "cards">' . $cards . '</div>';
    $output .='<div class = "pagination">'.$pagination.'</div>';
    
    echo $output;
?>