<?php 
    /* --- Filters --- */
    $actor  = trim($_GET['actor'] ?? '');
    $action = trim($_GET['action'] ?? '');
    $table  = trim($_GET['table'] ?? '');
    $from   = trim($_GET['from'] ?? '');
    $to     = trim($_GET['to'] ?? '');
    // $sort   = trim($_GET['sort'] ?? 'ASC'); 

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $PAGE_SIZE = 5;
    $offset = ($page - 1) * $PAGE_SIZE;
    $base = app_url('/admin/audit_print.php');

    $sortMap = [
        'asc'    => 'audit_id asc',
        'desc'     => 'audit_id desc',
    ];
    // $orderSql = $sortMap[$sort] ?? $sortMap['asc'];
    /* Named parameters only */
    $sqlBase = " FROM AUDIT_LOG WHERE 1=1 ";
    $params = [];
    if ($actor !== '')  { $sqlBase .= " AND ACTOR LIKE :actor";             $params[':actor'] = "%{$actor}%"; }
    if ($action !== '') { $sqlBase .= " AND ACTION_ENUM = :action";         $params[':action'] = $action; }
    if ($table !== '')  { $sqlBase .= " AND TABLE_NAME = :table";           $params[':table']  = $table; }
    if ($from  !== '')  { $sqlBase .= " AND DATE({$AUDIT_TIME}) >= :from";  $params[':from']   = $from; }
    if ($to    !== '')  { $sqlBase .= " AND DATE({$AUDIT_TIME}) <= :to";    $params[':to']     = $to; }

    /* Count */
    $stmtCnt = $pdo->prepare("SELECT COUNT(*) ".$sqlBase);
    $stmtCnt->execute($params);
    $total = (int)$stmtCnt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $PAGE_SIZE));

    /* Fetch page rows */
    $sql = "
    SELECT {$AUDIT_ID} AS ID, {$AUDIT_TIME} AS CREATED_AT, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
    $sqlBase
    ORDER BY {$AUDIT_ID} ASC
    LIMIT :lim OFFSET :off
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tables  = ['FACULTY','RESEARCH','AGENCY','FUNDING','ASSIGNMENT'];
    $actions = ['CREATE','UPDATE','DELETE'];
    $output = '';
    $panel = '';
    $panel .= '
        <h3 style="margin-top:0">Audit Log</h3>
        <div class="muted" style="margin-bottom:10px;">
            Generated: ' . date('Y-m-d H:i:s');
    if ($actor || $action || $table || $from || $to) {
        $chips = [];
        if ($actor)  $chips[] = 'Actor: ' . htmlspecialchars($actor);
        if ($action) $chips[] = 'Action: ' . htmlspecialchars($action);
        if ($table)  $chips[] = 'Table: ' . htmlspecialchars($table);
        if ($from)   $chips[] = 'From: ' . htmlspecialchars($from);
        if ($to)     $chips[] = 'To: ' . htmlspecialchars($to);
        $panel .= ' · Filters: ' . implode(' · ', $chips);
    }
    $panel .= '</div>
        <div class="muted" style="margin-bottom:10px;">
            Records: ' . number_format($total);
    if ($total > 0) {
        $start = $offset + 1;
        $end = min($offset + $PAGE_SIZE, $total);
        $panel .= ' · Showing ' . number_format($start) . '–' . number_format($end);
    }
    $panel .= '</div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th style="width:160px;">When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>PK</th>
                    </tr>
                </thead>
                <tbody>';

    if ($rows) {
        foreach ($rows as $r) {
            $panel .= '
                    <tr>
                        <td>' . (int)$r['ID'] . '</td>
                        <td>' . htmlspecialchars($r['CREATED_AT']) . '</td>
                        <td>' . htmlspecialchars($r['ACTOR']) . '</td>
                        <td>' . htmlspecialchars($r['ACTION_ENUM']) . '</td>
                        <td>' . htmlspecialchars($r['TABLE_NAME']) . '</td>
                        <td>' . htmlspecialchars($r['PK_VALUE']) . '</td>
                    </tr>';
        }
    } else {
        $panel .= '
                    <tr>
                        <td colspan="6" style="text-align:center;color:#666;">No audit rows match your filters.</td>
                    </tr>';
    }

    $panel .= '
                </tbody>
            </table>
        </div>';
    $pagination = '';
        $qs = function($p) use ($actor,$action,$table, $from, $to) {
        $parts = ['page='.$p];
        if ($actor   !== '') $parts[]='actor='.rawurlencode($actor);
        if ($action   !== '') $parts[]='action='.rawurlencode($action);
        if ($table   !== '') $parts[]='table='.rawurlencode($table);
        if ($from   !== '') $parts[]='from='.rawurlencode($from);
        if ($to   !== '') $parts[]='to='.rawurlencode($to);
        //if ($sort!== '') $parts[]='sort='.rawurlencode($sort);
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