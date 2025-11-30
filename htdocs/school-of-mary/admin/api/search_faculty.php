<?php 
    require_once __DIR__ . '/../../partials/init.php';
    
    /* ------------------------- Filters + Sorting + Pagination ------------------------- */
    $q    = trim($_GET['q'] ?? '');
    $rank = trim($_GET['rank'] ?? '');
    $dept = trim($_GET['dept'] ?? '');
    $sort = $_GET['sort'] ?? 'name_asc'; // name_asc|name_desc|id_asc|id_desc|email_asc|email_desc|rank_asc|dept_asc
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = 5;
    $offset = ($page - 1) * $per;
    $CSRF = csrf_token();
    $base = app_url('/admin/crud/faculty.php');

    $sortMap = [
    'id_desc'   => 'f.FACULTY_ID DESC',
    'id_asc'    => 'f.FACULTY_ID ASC',
    'name_asc'  => 'f.FACULTY_LNAME ASC, f.FACULTY_FNAME ASC',
    'name_desc' => 'f.FACULTY_LNAME DESC, f.FACULTY_FNAME DESC',
    'email_asc' => 'f.FACULTY_EMAIL ASC',
    'email_desc'=> 'f.FACULTY_EMAIL DESC',
    'rank_asc'  => 'r.RANK_LEVEL ASC, f.FACULTY_LNAME ASC',
    'dept_asc'  => 'd.DEPT_SPECIALIZATION ASC, f.FACULTY_LNAME ASC',
    ];
    $orderSql = $sortMap[$sort] ?? $sortMap['name_asc'];

    $baseSql = "FROM FACULTY f
                JOIN `RANK` r ON f.RANK_ID=r.RANK_ID
                JOIN DEPARTMENT d ON f.DEPT_ID=d.DEPT_ID
                WHERE 1=1";
    $params = [];
    if ($q !== '') {
    $baseSql .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR f.FACULTY_EMAIL LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
    }
    if ($rank !== '') { $baseSql .= " AND f.RANK_ID = ?"; $params[] = $rank; }
    if ($dept !== '') { $baseSql .= " AND f.DEPT_ID = ?"; $params[] = $dept; }

    $stmtCount = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $sql = "SELECT f.*, r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
            $baseSql
            ORDER BY $orderSql
            LIMIT $per OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPages = max(1, (int)ceil($total / $per));
    $output = "";
    $panel = "";

    $panel .= '
        <h3 style="margin-top:0">Records</h3>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Email</th><th>Rank</th><th>Dept</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        if ($rows) {
        foreach ($rows as $row) {
            $panel .= '
                <tr>
                    <td>' . (int)$row['FACULTY_ID'] . '</td>
                    <td>' . htmlspecialchars($row['FACULTY_LNAME'] . ", " . $row['FACULTY_FNAME'] . ($row['FACULTY_INITIAL'] ? " " . $row['FACULTY_INITIAL'] : "")) . '</td>
                    <td>' . htmlspecialchars($row['FACULTY_EMAIL']) . '</td>
                    <td>' . htmlspecialchars($row['RANK_DESCRIPTION']) . '</td>
                    <td>' . htmlspecialchars($row['DEPT_SPECIALIZATION']) . '</td>

                    <td class="actions-cell" onclick="event.stopPropagation()">
                        <button class="btn small btn-view"> 
                            <a style="color:white; cursor:pointer;" href="' . BASE_URL . '/public/faculty.php?id=' . (int)$row["FACULTY_ID"] . '">
                                View
                            </a>
                        </button>
                        <button
                            type="button"
                            class="btn small btn-edit js-edit"
                            data-id="' . (int)$row['FACULTY_ID'] . '"
                            data-fname="' . htmlspecialchars($row['FACULTY_FNAME'], ENT_QUOTES) . '"
                            data-initial="' . htmlspecialchars($row['FACULTY_INITIAL'], ENT_QUOTES) . '"
                            data-lname="' . htmlspecialchars($row['FACULTY_LNAME'], ENT_QUOTES) . '"
                            data-email="' . htmlspecialchars($row['FACULTY_EMAIL'], ENT_QUOTES) . '"
                            data-rank="' . htmlspecialchars($row['RANK_ID'], ENT_QUOTES) . '"
                            data-dept="' . htmlspecialchars($row['DEPT_ID'], ENT_QUOTES) . '"
                        >Edit</button>

                        <form method="post"  action="' . $base . '" onsubmit="return confirm(\'Are you sure you want to delete this faculty?\');" style="display:inline">
                            <input type="hidden" name="csrf" value="' . $CSRF . '">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="FACULTY_ID" value="' . (int)$row['FACULTY_ID'] . '">
                            <button class="btn small btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
            ';
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

    $pagination = "";
    $qs = function($p) use ($q, $rank, $dept, $sort) {
        $parts = ['page='.$p];
        if ($q   !== '') $parts[]='q='.rawurlencode($q);
        if ($rank!== '') $parts[]='rank='.rawurlencode($rank);
        if ($dept!== '') $parts[]='dept='.rawurlencode($dept);
        if ($sort!== '') $parts[]='sort='.rawurlencode($sort);
        return implode('&',$parts);
    };
    // Previous button
    if ($page > 1) {
        $pagination .= '<a class="page-btn" href="' . $base.'?'.$qs(max(1,$page-1)) . '" title="Previous Page">&#x276E;</a>';
    }
    // Compute page range
    $maxPage = 5;
    $start = max(1, $page - floor($maxPage / 2));
    $end   = min($totalPages, $start + $maxPage - 1);

    if ($end - $start < $maxPage - 1) {
        $start = max(1, $end - $maxPage + 1);
    }
    // First page + ellipsis
    if ($start > 1) {
        $pagination .= '<a href="' . $base.'?'.$qs(1) . '" class="page-btn">1</a>';

        if ($start > 3) {
            $pagination .= '<a href="' . $base.'?'.$qs(max(1,$page - 5)) . '" class="page-btn" title="Jump backward 5 pages">...</a>';
        }
        if ($start == 3) {
            $pagination .= '<a href="' . $base.'?'.$qs(2) . '" class="page-btn">2</a>';
        }
    }
    // Loop pages
    for ($i = $start; $i <= $end; $i++) {
        $pagination .= '<a class="page-btn ' . ($i == $page ? 'active' : '') . '" href="' . $base.'?'.$qs($i) . '">' . $i . '</a>';
    }
    // Last page + ellipsis
    if ($end < $totalPages) {
        if ($end == $totalPages - 2) {
            $pagination .= '<a href="' . $base.'?'.$qs($totalPages - 1) . '" class="page-btn">' . ($totalPages - 1) . '</a>';
        }
        if ($end < $totalPages - 2) {
            $pagination .= '<a href="' . $base.'?'.$qs(min($totalPages,$page + 5)) . '" class="page-btn" title="Jump forward 5 pages">...</a>';
        }
        $pagination .= '<a href="' . $base.'?'.$qs($totalPages) . '" class="page-btn">' . $totalPages . '</a>';
    }

    // Next button
    if ($page < $totalPages) {
        $pagination .= '<a class="page-btn" href="' . $base.'?'.$qs(min($totalPages,$page+1)) . '" title="Next Page">&#x276F;</a>';
    }
    $output .= $panel;
    $output .='<div class = "pagination">'.$pagination.'</div>';
    echo $output;
?>