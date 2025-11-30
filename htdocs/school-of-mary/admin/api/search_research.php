<?php 
    require_once __DIR__ . '/../../partials/init.php';
    
    /* Filters / Sorting / Pagination */
    $q      = trim($_GET['q'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $from   = trim($_GET['from'] ?? '');
    $to     = trim($_GET['to'] ?? '');
    $sort   = $_GET['sort'] ?? 'start_desc';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $per    = 5;
    $offset = ($page - 1) * $per;
    $CSRF = csrf_token();
    $base = app_url('/admin/crud/research.php');
        
    $sortMap = [
    'title_asc'  => 'RESEARCH_TITLE ASC, RESEARCH_ID DESC',
    'title_desc'  => 'RESEARCH_TITLE desc, RESEARCH_ID DESC',
    'status_asc' => 'RESEARCH_STATUS ASC, RESEARCH_TITLE ASC',
    'start_desc' => 'RESEARCH_STARTDATE DESC, RESEARCH_ID DESC',
    'start_asc'  => 'RESEARCH_STARTDATE ASC, RESEARCH_ID ASC',
    'end_desc'   => 'RESEARCH_ENDDATE DESC, RESEARCH_ID DESC',
    'end_asc'    => 'RESEARCH_ENDDATE ASC, RESEARCH_ID ASC',
    'id_desc'    => 'RESEARCH_ID DESC',
    'id_asc'     => 'RESEARCH_ID ASC',
    ];
    $orderSql = $sortMap[$sort] ?? $sortMap['start_desc'];

    $baseSql = "FROM RESEARCH WHERE 1=1";
    $params  = [];
    if ($q !== '')      { $baseSql .= " AND RESEARCH_TITLE LIKE ?";       $params[] = "%$q%"; }
    if ($status !== '') { $baseSql .= " AND RESEARCH_STATUS = ?";         $params[] = $status; }
    if ($from !== '')   { $baseSql .= " AND RESEARCH_STARTDATE >= ?";     $params[] = $from; }
    if ($to !== '')     { $baseSql .= " AND (RESEARCH_ENDDATE <= ? OR (RESEARCH_ENDDATE IS NULL AND RESEARCH_STARTDATE <= ?))"; array_push($params, $to, $to); }

    /* Count */
    $stmtCnt = $pdo->prepare("SELECT COUNT(*) $baseSql");
    $stmtCnt->execute($params);
    $total = (int)$stmtCnt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $per));

    /* Page rows */
    $sql = "SELECT * $baseSql ORDER BY $orderSql LIMIT $per OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $output = '';
    $panel = '';
    $panel .= '
        <h3 style="margin-top:0">Records</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Title</th><th>Status</th><th>Start</th><th>End</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
    
    if ($rows) {
        foreach ($rows as $row) {
            $panel .= '
                <tr>
                    <td>' . (int)$row['RESEARCH_ID'] . '</td>
                    <td>' . htmlspecialchars($row['RESEARCH_TITLE']) . '</td>
                    <td>' . htmlspecialchars($row['RESEARCH_STATUS']) . '</td>
                    <td>' . htmlspecialchars($row['RESEARCH_STARTDATE']) . '</td>
                    <td>' . htmlspecialchars((string)$row['RESEARCH_ENDDATE']) . '</td>
                    <td class="actions-cell onclick="event.stopPropagation()">
                        <button class="btn small btn-view"> 
                            <a style="color:white; cursor:pointer;" href="' . BASE_URL . '/public/research.php?id=' . (int)$row["RESEARCH_ID"] . '">
                                View
                            </a>
                        </button>
                        <button
                            type="button"
                            class="btn btn-edit small js-edit"
                            data-id="' . (int)$row['RESEARCH_ID'] . '"
                            data-title="' . htmlspecialchars($row['RESEARCH_TITLE'], ENT_QUOTES) . '"
                            data-start="' . htmlspecialchars($row['RESEARCH_STARTDATE'], ENT_QUOTES) . '"
                            data-end="' . htmlspecialchars((string)$row['RESEARCH_ENDDATE'], ENT_QUOTES) . '"
                            data-status="' . htmlspecialchars($row['RESEARCH_STATUS'], ENT_QUOTES) . '"
                        >Edit</button>

                        <form method="post" action="' . $base . '" onsubmit="return confirm(\'Delete research?\');" style="display:inline">
                            <input type="hidden" name="csrf" value="' . $CSRF . '">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="RESEARCH_ID" value="' . (int)$row['RESEARCH_ID'] . '">
                            <button class="btn small btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>';
        }
    } else {
        $panel .= '
            <tr>
                <td colspan="6" style="text-align:center;color:#666;">No records found.</td>
            </tr>';
    }
    
    $panel .= '
                </tbody>
            </table>
        </div>';
    $pagination = '';
    $qs = function($p) use ($q, $status, $from, $to, $sort) {
        $parts = ['page='.$p];
        if ($q      !== '') $parts[] = 'q='.rawurlencode($q);
        if ($status !== '') $parts[] = 'status='.rawurlencode($status);
        if ($from   !== '') $parts[] = 'from='.rawurlencode($from);
        if ($to     !== '') $parts[] = 'to='.rawurlencode($to);
        if ($sort   !== '') $parts[] = 'sort='.rawurlencode($sort);
        return implode('&', $parts);
    };
    
    if ($page > 1) {
        $pagination .= '<a class="page-btn" href="' . $base.'?'.$qs(max(1,$page-1)) . '" title = "Previous Page">&#x276E;</a>';
    }

    $maxPage = 5;
    $start = max(1, $page - floor($maxPage / 2));
    $end = min($totalPages, $start + $maxPage - 1);

    if ($end - $start < $maxPage - 1) {
        $start = max(1, $end - $maxPage + 1);
    } 
    if ($start > 1) {
        $pagination .= '<a href="' . $base.'?'.$qs(1) . '" class="page-btn" >1</a>';
        if ($start > 3) {
            $pagination .= '<a href="' . $base.'?'.$qs(max(1,$page - 5)) . '" class="page-btn" title="Jump backward 5 pages">...</a>';        
        }
        if ($start == 3) {
            $pagination .= '<a href="' .$base.'?'.$qs(2) .'" class="page-btn" >2</a>';
        }
    }

    for ($i = $start;$i <= $end;$i++) {
        $pagination .= '<a class="page-btn '. ($i== $page ? 'active':'') . '" href="' . $base.'?'.$qs($i) . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end == $totalPages - 2) {
            $pagination .= '<a href="'. $base.'?'.$qs($totalPages - 1) . '" class="page-btn" > ' . $totalPages - 1 .'</a>';
        }
        if ($end < $totalPages - 2) {
            $pagination .= '<a href="'. $base.'?'.$qs(min($totalPages,$page + 5)) . '"class="page-btn" title="Jump forward 5 pages">...</a>';
        }
            $pagination .= '<a href="' . $base.'?'.$qs($totalPages) . '" class="page-btn" >' .$totalPages . '</a>';
    }

    if ($page < $totalPages) {
        $pagination .= '<a class="page-btn" href="' . $base.'?'.$qs(min($totalPages,$page+1)) . '" title = "Next Page">&#x276F;</a>';
    }

    $output .= $panel;
    $output .='<div class = "pagination">'.$pagination.'</div>';
    echo $output;
?>