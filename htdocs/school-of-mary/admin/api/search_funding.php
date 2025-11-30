<?php
    require_once __DIR__ . '/../../partials/init.php'; 
    
    /* Filters / Sorting / Pagination */
    $q    = trim($_GET['q'] ?? '');
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
            <table>
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
                <tr>
                    <td>' . (int)$row['FUNDING_ID'] . '</td>
                    <td>' . htmlspecialchars($row['RESEARCH_TITLE']) . '</td>
                    <td>' . htmlspecialchars($row['AGENCY_NAME']) . '</td>
                    <td>' . ( $row['FUNDING_AMOUNT'] !== null ? '₱' . number_format((float)$row['FUNDING_AMOUNT'], 2) : '—') . '</td>
                    <td>' . htmlspecialchars((string)$row['DATE_FUNDED']) . '</td>
                    <td class="actions-cell">
                        <button
                            type="button"
                            class="btn small js-edit"
                            data-id="' . (int)$row['FUNDING_ID'] . '"
                            data-research="' . (int)$row['RESEARCH_ID'] . '"
                            data-agency="' . (int)$row['AGENCY_ID'] . '"
                            data-amount="' . htmlspecialchars((string)$row['FUNDING_AMOUNT'], ENT_QUOTES) . '"
                            data-date="'.htmlspecialchars((string)$row['DATE_FUNDED'], ENT_QUOTES) .'"
                          >Edit</button> 
                        <form method="post" action="' . $base . '" onsubmit="return confirm(\'Are you sure you want to delete this funding?\');" style="display:inline">
                            <input type="hidden" name="csrf" value="' . $CSRF . '">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="FUNDING_ID" value="' . (int)$row['FUNDING_ID'] . '">
                            <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
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