<?php 
    require_once __DIR__ . '/../../partials/init.php';
        
    /* ------------------------- Filters + Sorting + Pagination ------------------------- */
    $q      = trim($_GET['q'] ?? '');
    $sort   = $_GET['sort'] ?? 'id_desc'; // id_desc|id_asc|date_desc|date_asc|faculty_asc|faculty_desc|research_asc|research_desc
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $per    = 5;
    $offset = ($page - 1) * $per;
    $CSRF = csrf_token();
    $base = app_url('/admin/crud/assignment.php');
    $sortMap = [
    'id_desc'        => 'a.ASSIGNMENT_ID DESC',
    'id_asc'         => 'a.ASSIGNMENT_ID ASC',
    'date_desc'      => 'a.DATE_ASSIGNED DESC, a.ASSIGNMENT_ID DESC',
    'date_asc'       => 'a.DATE_ASSIGNED ASC, a.ASSIGNMENT_ID ASC',
    'faculty_asc'    => 'f.FACULTY_LNAME ASC, f.FACULTY_FNAME ASC, a.ASSIGNMENT_ID DESC',
    'faculty_desc'   => 'f.FACULTY_LNAME DESC, f.FACULTY_FNAME DESC, a.ASSIGNMENT_ID DESC',
    'research_asc'   => 'r.RESEARCH_TITLE ASC, a.ASSIGNMENT_ID DESC',
    'research_desc'  => 'r.RESEARCH_TITLE DESC, a.ASSIGNMENT_ID DESC',
    ];
    $orderSql = $sortMap[$sort] ?? $sortMap['id_desc'];

    $baseSql = "FROM ASSIGNMENT a
                JOIN FACULTY f ON a.FACULTY_ID = f.FACULTY_ID
                JOIN RESEARCH r ON a.RESEARCH_ID = r.RESEARCH_ID
                JOIN ROLE ro ON a.ROLE_ID = ro.ROLE_ID
                WHERE 1=1";
    $params = [];
    if ($q !== '') {
    $baseSql .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR r.RESEARCH_TITLE LIKE ?)";
    $params = ["%$q%", "%$q%", "%$q%"];
    }

    // total
    $stmtCount = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // rows
    $sql = "SELECT a.ASSIGNMENT_ID, a.FACULTY_ID, a.RESEARCH_ID, a.ROLE_ID, a.DATE_ASSIGNED,
                CONCAT(f.FACULTY_LNAME, ', ', f.FACULTY_FNAME) AS FACULTY_NAME,
                r.RESEARCH_TITLE,
                ro.ROLE_DESCRIPTION
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
                        <th>Faculty</th>
                        <th>Research</th>
                        <th>Role</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
    
    if ($rows) {
        foreach ($rows as $row) {
            $panel .= '
                <tr>
                    <td>' . (int)$row['ASSIGNMENT_ID'] . '</td>
                    <td>' . htmlspecialchars($row['FACULTY_NAME']) . '</td>
                    <td>' . htmlspecialchars($row['RESEARCH_TITLE']) . '</td>
                    <td>' . htmlspecialchars($row['ROLE_DESCRIPTION']) . '</td>
                    <td>' . htmlspecialchars($row['DATE_ASSIGNED']) . '</td>
                    <td class="actions-cell">
                        <button
                            type="button"
                            class="btn small js-edit"
                            data-id="' . (int)$row['ASSIGNMENT_ID'] . '"
                            data-faculty="' . (int)$row['FACULTY_ID'] . '"
                            data-research="' . (int)$row['RESEARCH_ID'] . '"
                            data-role="' . htmlspecialchars($row['ROLE_ID'], ENT_QUOTES) . '"
                            data-date="' . htmlspecialchars($row['DATE_ASSIGNED'], ENT_QUOTES) . '"
                        >Edit</button>

                        <form method="post" action="' . $base . '" onsubmit="return confirm(\'Delete this record?\');" style="display:inline">
                            <input type="hidden" name="csrf" value="' . $CSRF . '">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="ASSIGNMENT_ID" value="' . (int)$row['ASSIGNMENT_ID'] . '">
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
        // keep q/sort when paging
        $qs = function($p) use ($q, $sort) {
            $parts = ['page='.$p];
            if ($q !== '')   $parts[]='q='.rawurlencode($q);
            if ($sort !== '')$parts[]='sort='.rawurlencode($sort);
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