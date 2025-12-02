<?php
    require_once __DIR__ . '/../../partials/init.php'; 
    
    /* Filters / Sorting / Pagination */
    $q    = trim($_GET['q'] ?? '');
    $minFunding = isset($_GET['min_funding']) ? (float)$_GET['min_funding'] : 0;
    $maxFunding = isset($_GET['max_funding']) ? (float)$_GET['max_funding'] : 99999999.99;
    $sort = trim($_GET['sort'] ?? 'date_desc');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = 5;
    $offset = ($page - 1) * $per;
    $CSRF = csrf_token();
    $base = app_url('/admin/crud/funding.php');

    $sortMap = [
    'date_desc'    => 'fu.DATE_FUNDED DESC, fu.FUNDING_ID DESC',
    'date_asc'     => 'fu.DATE_FUNDED ASC, fu.FUNDING_ID ASC',
    'amount_desc'  => 'fu.FUNDING_AMOUNT DESC, fu.FUNDING_ID DESC',
    'amount_asc'   => 'fu.FUNDING_AMOUNT ASC, fu.FUNDING_ID ASC',
    'title_asc'    => 're.RESEARCH_TITLE ASC, fu.FUNDING_ID DESC',
    'agency_asc'   => 'ag.AGENCY_NAME ASC, fu.FUNDING_ID DESC',
    'id_desc'      => 'fu.FUNDING_ID DESC',
    'id_asc'       => 'fu.FUNDING_ID ASC',
    ];
    $orderSql = $sortMap[$sort] ?? $sortMap['date_desc'];

    $baseSql = "FROM FUNDING fu
                JOIN RESEARCH re ON fu.RESEARCH_ID=re.RESEARCH_ID
                JOIN AGENCY  ag ON fu.AGENCY_ID  =ag.AGENCY_ID
                WHERE 1=1";
    $params = [];
    if ($q !== '') {
    $baseSql .= " AND (re.RESEARCH_TITLE LIKE ? OR ag.AGENCY_NAME LIKE ?)";
    $params = ["%$q%","%$q%"];
    }
    if ($minFunding > 0) {
        $baseSql .= " AND fu.FUNDING_AMOUNT >= ?";
        $params[] = $minFunding;
    }
    if ($maxFunding < 99999999.99) {
        $baseSql .= " AND fu.FUNDING_AMOUNT <= ?";
        $params[] = $maxFunding;
    }
    $baseSql .= " AND fu.FUNDING_AMOUNT IS NOT NULL";
    /* Count for pagination */
    $stmtCnt = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
    $stmtCnt->execute($params);
    $total = (int)$stmtCnt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $per));

    /* Page rows */
    $sql = "SELECT fu.*, re.RESEARCH_TITLE, ag.AGENCY_NAME
            $baseSql
            ORDER BY $orderSql
            LIMIT $per OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = '';
    $panel = '';
    $panel .= '
        <h3 style="margin-top:0">Records</h3>
        <div class="table-scroll">
            <table class="table-clickable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Research</th>
                        <th>Agency</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
    
    if ($rows) {
        foreach ($rows as $row) {
            $panel .= '
                <tr style="cursor:pointer;" data-href="' . BASE_URL . '/public/research.php?id=' . (int)$row["RESEARCH_ID"] . '">
                    <td>' . (int)$row['FUNDING_ID'] . '</td>
                    <td>' . htmlspecialchars($row['RESEARCH_TITLE']) . '</td>
                    <td>' . htmlspecialchars($row['AGENCY_NAME']) . '</td>
                    <td>' . ( $row['FUNDING_AMOUNT'] !== null ? '₱' . number_format((float)$row['FUNDING_AMOUNT'], 2) : '—') . '</td>
                    <td>' . htmlspecialchars((string)$row['DATE_FUNDED']) . '</td>
                    <td class="actions-cell">
                        <button
                            type="button"
                            class="btn small btn-edit js-edit"
                            data-id="' . (int)$row['FUNDING_ID'] . '"
                            data-research="' . (int)$row['RESEARCH_ID'] . '"
                            data-agency="' . (int)$row['AGENCY_ID'] . '"
                            data-amount="' . htmlspecialchars((string)$row['FUNDING_AMOUNT'], ENT_QUOTES) . '"
                            data-date="'.htmlspecialchars((string)$row['DATE_FUNDED'], ENT_QUOTES) .'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-pen" viewBox="0 0 16 16">
                            <path d="m13.498.795.149-.149a1.207 1.207 0 1 1 1.707 1.708l-.149.148a1.5 1.5 0 0 1-.059 2.059L4.854 14.854a.5.5 0 0 1-.233.131l-4 1a.5.5 0 0 1-.606-.606l1-4a.5.5 0 0 1 .131-.232l9.642-9.642a.5.5 0 0 0-.642.056L6.854 4.854a.5.5 0 1 1-.708-.708L9.44.854A1.5 1.5 0 0 1 11.5.796a1.5 1.5 0 0 1 1.998-.001m-.644.766a.5.5 0 0 0-.707 0L1.95 11.756l-.764 3.057 3.057-.764L14.44 3.854a.5.5 0 0 0 0-.708z"/>
                            </svg>
                            Edit</button> 
                        <form method="post" action="' . $base . '" onsubmit="return confirm(\'Are you sure you want to delete this funding?\');" style="display:inline">
                            <input type="hidden" name="csrf" value="' . $CSRF . '">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="FUNDING_ID" value="' . (int)$row['FUNDING_ID'] . '">
                            <button class="btn small btn-delete">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
                            <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/>
                            </svg>
                            Delete</button>
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
        $qs = function($p) use ($q, $sort) {
        $parts = ['page='.$p];
        if ($q   !== '') $parts[]='q='.rawurlencode($q);
        if ($sort!== '') $parts[]='sort='.rawurlencode($sort);
        return implode('&',$parts);
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