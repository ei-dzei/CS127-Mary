<?php 
    require_once __DIR__ . '/../../partials/init.php';
    /* ------------------------- Filters + Sorting + Pagination ------------------------- */
    $q      = trim($_GET['q'] ?? '');
    $type   = trim($_GET['type'] ?? '');
    $sort   = $_GET['sort'] ?? 'name_asc';      // name_asc|name_desc|id_asc|id_desc|recent_desc
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $per    = 5;
    $offset = ($page - 1) * $per;
    $orderSql = "a.AGENCY_NAME ASC";
    switch ($sort) {
        case 'name_asc':   $orderSql = "a.AGENCY_NAME ASC"; break;
        case 'name_desc':  $orderSql = "a.AGENCY_NAME DESC"; break;
        case 'id_asc':     $orderSql = "a.AGENCY_ID ASC";    break;
        case 'id_desc':    $orderSql = "a.AGENCY_ID DESC";   break;
    }

    $baseSql = "FROM AGENCY a LEFT JOIN TYPE_AGENCY t ON a.AGENCY_TYPE = t.TYPE_CODE WHERE 1=1";
    $params = [];
    if ($q !== '')    { $baseSql .= " AND a.AGENCY_NAME LIKE ?"; $params[] = "%$q%"; }
    if ($type !== '') { $baseSql .= " AND a.AGENCY_TYPE = ?";    $params[] = $type;  }

    $total = (int)$pdo->prepare("SELECT COUNT(*) ".$baseSql)->execute($params) ?: 0;
    $stmtCount = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $sql = "SELECT a.AGENCY_ID, a.AGENCY_NAME, a.AGENCY_TYPE, a.AGENCY_CONTACTINFO, t.TYPE_LABEL
            $baseSql
            ORDER BY $orderSql
            LIMIT $per OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = max(1, (int)ceil($total / $per));

    $output = '';
    $panel = '';
    $panel .= '
        <h3 style="margin-top:0">Records</h3>
        <div class="table-scroll">
            <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Contact</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>';
            foreach ($rows as $row) {
                $panel .= '<tr>
                <td>'. (int)$row['AGENCY_ID'] . '</td>
                <td>' . htmlspecialchars($row['AGENCY_NAME']) . '</td>
                <td>' . htmlspecialchars($row['TYPE_LABEL']) . '</td>
                <td>' . htmlspecialchars($row['AGENCY_CONTACTINFO']) . '</td>
                <td class="actions-cell">
                    <button
                    type="button"
                    class="btn small btn-edit js-edit"
                    data-id="' . (int)$row['AGENCY_ID'] . '"
                    data-name="' . htmlspecialchars($row['AGENCY_NAME'], ENT_QUOTES) . '"
                    data-type="' . htmlspecialchars($row['AGENCY_TYPE'], ENT_QUOTES) . '"
                    data-contact="' . htmlspecialchars($row['AGENCY_CONTACTINFO'], ENT_QUOTES) . '"
                    >Edit</button>

                    <form method="post" action="' . app_url('/admin/crud/agency.php') . '" onsubmit="return confirm(\'Are you sure you want to delete this agency?\');" style="display:inline">
                    <input type="hidden" name="csrf" value="' . csrf_token() . '">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="AGENCY_ID" value="' .  (int)$row['AGENCY_ID'] . '">
                    <button class="btn small btn-delete">Delete</button>
                    </form>
                </td>
                </tr>';
            }
            $panel .= '
            </tbody>
            </table>
        </div>';
    $pagination = "";
        $qs = function($p) use ($q, $type, $sort) {
            $parts = ['page='.$p];
            if ($q !== '')   $parts[]='q='.rawurlencode($q);
            if ($type !== '')$parts[]='type='.rawurlencode($type);
            if ($sort !== '')$parts[]='sort='.rawurlencode($sort);
            return implode('&',$parts);
        };
        $base = app_url('/admin/crud/agency.php');
        
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