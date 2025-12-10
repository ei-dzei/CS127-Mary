<?php 
    // Initialization
    require_once __DIR__ . '/../../partials/init.php';
    
    // Research projects to show per page
    $perPage = 6;

    // Get current page number
    $page    = (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0) ? (int)$_GET['page'] : 1;
    
    // Calculate offset for SQL LIMIT clause
    $offset  = ($page - 1) * $perPage;

    // Retrieve filters inputs to handle missing parameters
    $q      = trim($_GET['q'] ?? '');      // Search keyword
    $status = trim($_GET['status'] ?? ''); // Research status
    $from   = trim($_GET['from'] ?? '');   // Start date filter
    $to     = trim($_GET['to'] ?? '');     // End date filter

    // We use an array to collect WHERE conditions, then implode them later.
    // Alternative to the "WHERE 1=1" method.
    $where   = [];
    $params  = [];

    // Filter by Title (Partial match)
    if ($q !== '') {
        $where[]        = "re.RESEARCH_TITLE LIKE :q";
        $params[':q']   = "%{$q}%"; // Wrap in wildcards for 'contains' search
    }

    // Filter by Exact Status
    if ($status !== '') {
        $where[]           = "re.RESEARCH_STATUS = :status";
        $params[':status'] = $status;
    }

    // Filter by Start Date (Projects starting on or after this date)
    if ($from !== '') {
        $where[]         = "re.RESEARCH_STARTDATE >= :from";
        $params[':from'] = $from;
    }

    // Filter by End Date
    if ($to !== '') {
        // Logic for me: Find records that ended by this date, 
        // OR are Ongoing (NULL ENDDATE) but started before/on this date.
        // This ensures we don't miss ongoing projects when filtering by time range.
        $where[]        = "(re.RESEARCH_ENDDATE <= :to OR (re.RESEARCH_ENDDATE IS NULL AND re.RESEARCH_STARTDATE <= :to2))";
        
        // We bind the same value to two different placeholders needed for the logic above
        $params[':to']  = $to;
        $params[':to2'] = $to;
    }

    // Convert array of conditions into a string prefixed with ' AND '
    // If array is empty, $whereSql becomes an empty string.
    $whereSql = $where ? (' AND ' . implode(' AND ', $where)) : '';

    // Count query for pagination
    // Count total matching records to determine total pages
    $countSql = "SELECT COUNT(*) FROM RESEARCH re WHERE 1=1 {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    
    // Bind all filter parameters
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    
    $countStmt->execute();
    $totalRows  = (int)$countStmt->fetchColumn();
    
    // Calculate total pages (always at least 1)
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    // Main data select query
    // Fetch the actual data sorted by newest Start Date
    $listSql = "
        SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS,
               re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE
        FROM RESEARCH re
        WHERE 1=1 {$whereSql}
        ORDER BY re.RESEARCH_STARTDATE DESC, re.RESEARCH_ID DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $listStmt = $pdo->prepare($listSql);
    
    // Bind filter parameters again
    foreach ($params as $k => $v) {
        $listStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    
    // Bind pagination limits strictly as Integers
    $listStmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
    
    $listStmt->execute();
    $rows  = $listStmt->fetchAll();
    $total = count($rows);

    // Research Cards
    $cards = "";

    if (!$rows) {
        $cards = '<div class="panel">No matching research.</div>';
    } else {
        foreach ($rows as $row) {
            // Conditional formatting: Only show End Date if it exists (not NULL)
            $endDate = $row['RESEARCH_ENDDATE'] 
                ? ' · <i class="bi bi-calendar-check-fill"></i> End: ' . htmlspecialchars($row['RESEARCH_ENDDATE']) 
                : '';

            // Build the card HTML
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
                        <a class="btn small" href="' . BASE_URL . '/public/research.php?id=' . (int)$row["RESEARCH_ID"] . '">
                        Read More
                    </a>
                    </div>
                </div>
            </div>';
        }
    }
    
    // Pagination Links
    $pagination = "";
    
    // Preserve existing filters (q, status, from, to) in pagination links
    $queryParams = $_GET;
    unset($queryParams["page"]); // Remove current page so we don't append it twice
    $baseQuery = http_build_query($queryParams);
    
    // Build base URL
    $baseUrl = "?" . ($baseQuery ? $baseQuery . "&" : "");
    $maxPage = 5; // Window size for pagination buttons

    // Previous Button
    if ($page > 1) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . ($page-1) . '" class="page-btn" title="Previous page">&#x276E;</a>';
    }

    // Calculate Window Start/End
    $start = max(1, $page - floor($maxPage/2));
    $end = min($totalPages, $start + $maxPage - 1);

    // Adjust window if we are near the end
    if ($end - $start < $maxPage - 1) {
        $start = max(1, $end - $maxPage + 1);
    }

    // First Page & Leading Ellipsis
    if ($start > 1) {
        $pagination .= '<a href="' . $baseUrl . 'page=1" class="page-btn">1</a>';
        if ($start > 3) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . max(1, $page - 5) . '" class="page-btn" title = "Jump backward 5 pages">...</a>';
        }
        if ($start == 3) {
            $pagination .= '<a href="' . $baseUrl . 'page=2" class="page-btn">2</a>';
        }
    }

    // Numbered Pages Loop
    for ($i = $start; $i <= $end; $i++) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . $i . '" class="page-btn ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
    }

    // Last Page & Trailing Ellipsis
    if ($end < $totalPages) {
        if ($end == $totalPages - 2) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . ($totalPages - 1) . '" class="page-btn">' . ($totalPages - 1) . '</a>';
        }
        if ($end < $totalPages - 2) {
            $pagination .= '<a href="' . $baseUrl . 'page=' . min($totalPages, $page + 5) . '" class="page-btn" title ="Jump forward 5 pages">...</a>';
        }
        $pagination .= '<a href="' . $baseUrl . 'page=' . $totalPages . '" class="page-btn">' . $totalPages . '</a>';
    }

    // Next Button
    if ($page < $totalPages) {
        $pagination .= '<a href="' . $baseUrl . 'page=' . ($page+1) . '" class="page-btn" title= "Next page ">&#x276F;</a>';
    }

    // Status line
    $output = '<p class="muted" style="margin:6px 0 12px;">Showing ' .(int)$totalRows .  ($total===1 ? " research project" : " research projects") . ' | Page '.$page.' of '. ($totalPages ===0? ' 1' : $totalPages) . '</p>';
    // Append Cards
    $output .= '<div class = "cards">' . $cards . '</div>';
    // Append Pagination
    $output .='<div class = "pagination">'.$pagination.'</div>';
  
  echo $output; 
?>