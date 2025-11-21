<?php
// Printable audit log
$pageTitle = 'Audit Log (Print View)';

require_once __DIR__ . '/../partials/init.php';
if (!is_admin()) { redirect_to('/admin/login.php'); }

// Resolve audit table PK/timestamp columns
function audit_resolve_cols(PDO $pdo): array {
  $idCandidates   = ['ID','id','log_id','audit_id'];
  $timeCandidates = ['CREATED_AT','created_at','logged_at','timestamp','createdOn'];
  foreach ($idCandidates as $idCol) {
    foreach ($timeCandidates as $tCol) {
      try {
        $pdo->query("SELECT {$idCol} AS ID, {$tCol} AS CREATED_AT FROM AUDIT_LOG ORDER BY {$idCol} ASC LIMIT 1");
        return [$idCol, $tCol];
      } catch (Throwable $e) {}
    }
  }
  return ['ID', 'CREATED_AT'];
}
[$AUDIT_ID, $AUDIT_TIME] = audit_resolve_cols($pdo);

/* --- Filters --- */
$actor  = trim($_GET['actor'] ?? '');
$action = trim($_GET['action'] ?? '');
$table  = trim($_GET['table'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$PAGE_SIZE = 100;
$offset = ($page - 1) * $PAGE_SIZE;

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
$actions = ['CREATE','UPDATE','DELETE','IMPORT'];

require_once __DIR__ . '/../partials/site_header.php';
?>

<style>
/* === Buttons === */
.btn-action{
  display:inline-flex; align-items:center; justify-content:center;
  min-width:130px; height:40px; padding:0 16px;
  border-radius:8px; border:1px solid var(--color-accent);
  font-weight:600; text-decoration:none; cursor:pointer;
  transition:background .2s ease, color .2s ease, transform .06s ease, box-shadow .15s ease;
}
.btn-action:active{ transform:translateY(1px); }
.btn-primary{ background:var(--color-accent); color:#fff; }
.btn-primary:hover{ filter:brightness(.94); box-shadow:0 4px 10px rgba(0,0,0,.06); }
.btn-ghost{ background:#fff; color:var(--color-accent); border-color:rgba(11,83,148,.35); }
.btn-ghost:hover{ background:rgba(11,83,148,.05); }

.filter-bar .btn-action{ min-width:140px; }
@media (max-width: 720px){ .filter-bar .btn-action{ width:100%; } }

/* === Print View Optimization === */
@media print {
  /* Hide the filter panel, print button section, and pagination */
  .panel.fade-in:first-child, /* This targets the first panel (Filters) */
  .pagination,
  #print-button-section { /* Using an ID for the whole section for clarity */
    display: none !important;
  }

  /* Remove margins and padding from the body/main container for better page utilization */
  body, .container {
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Ensure the printable content section starts at the top of the page */
  .panel.fade-in:last-child {
    margin-top: 0 !important;
  }

  /* Optional: Enhance table styling for print */
  table {
    page-break-inside: auto; /* Allow table to be split across pages */
  }
  tr {
    page-break-inside: avoid; /* Keep table rows intact */
    page-break-after: auto;
  }
}
</style>

<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Audit Log — Print View</h1>
  <p class="muted" style="margin-bottom:10px;">
    Use filters then press the **Print** button below. This page uses formal print styles automatically.
  </p>
  <form method="get" class="grid filter-bar">
    <div class="field" style="grid-column:span 3;">
      <label>Actor</label>
      <input class="input" name="actor" value="<?= htmlspecialchars($actor); ?>" placeholder="admin, user id, etc." />
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>Action</label>
      <select class="input" name="action">
        <option value="">All</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= $a; ?>"<?= $action===$a?' selected':''; ?>><?= $a; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>Table</label>
      <select class="input" name="table">
        <option value="">All</option>
        <?php foreach ($tables as $t): ?>
          <option value="<?= $t; ?>"<?= $table===$t?' selected':''; ?>><?= $t; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>From</label>
      <input class="input" type="date" name="from" value="<?= htmlspecialchars($from); ?>">
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>To</label>
      <input class="input" type="date" name="to" value="<?= htmlspecialchars($to); ?>">
    </div>
    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <button class="btn-action btn-primary" type="submit">Apply</button>
    </div>
    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/audit_print.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<section id="print-button-section" class="panel fade-in" style="margin-top:-10px; margin-bottom:15px; padding:15px 20px;">
  <button class="btn-action btn-primary" onclick="window.print()" style="min-width:160px;">
    <span style="font-size:1.2em; margin-right:8px;">&#x1F5B6;&#xFE0F;</span> Print Audit Log
  </button>
</section>

<section class="panel fade-in" style="background:#fff;">
  <div class="container" style="width:100%;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:10px;">
      <div>
        <h2 style="font-family:'Patua One', serif; margin:0;">School of Mary — Audit Log</h2>
        <div class="muted">
          Generated: <?= date('Y-m-d H:i:s'); ?>
          <?php if ($actor||$action||$table||$from||$to): ?>
            · Filters:
            <?php
              $chips=[];
              if ($actor)  $chips[]="Actor: ".htmlspecialchars($actor);
              if ($action) $chips[]="Action: ".htmlspecialchars($action);
              if ($table)  $chips[]="Table: ".htmlspecialchars($table);
              if ($from)   $chips[]="From: ".htmlspecialchars($from);
              if ($to)     $chips[]="To: ".htmlspecialchars($to);
              echo implode(' · ', $chips);
            ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="muted">
        Records: <?= number_format($total); ?>
        <?php if ($total > 0): ?>
          <?php $start = $offset + 1; $end = min($offset + $PAGE_SIZE, $total); ?>
          · Showing <?= number_format($start) ?>–<?= number_format($end) ?>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$rows): ?>
      <div class="panel" style="background:#f8fafc; border-color:#e5eaf0;">No audit rows match your filters.</div>
    <?php else: ?>
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
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['ID']; ?></td>
              <td><?= htmlspecialchars($r['CREATED_AT']); ?></td>
              <td><?= htmlspecialchars($r['ACTOR']); ?></td>
              <td><?= htmlspecialchars($r['ACTION_ENUM']); ?></td>
              <td><?= htmlspecialchars($r['TABLE_NAME']); ?></td>
              <td><?= htmlspecialchars($r['PK_VALUE']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="pagination">
      <?php
        $base = app_url('/admin/audit_print.php');
        $qs = "actor=".urlencode($actor)
            ."&action=".urlencode($action)
            ."&table=".urlencode($table)
            ."&from=".urlencode($from)
            ."&to=".urlencode($to);
        $prev = $page - 1;
        $next = $page + 1;

        $maxPage = 5;
        $start = max(1, $page - floor($maxPage / 2));
        $end = min($totalPages, $start + $maxPage - 1);
        if ($end - $start + 1 < $maxPage) {
          $start = max(1, $end - $maxPage + 1);
        }

        // Calculate jump pages
        $jumpBack = max(1, $page - $maxPage);
        $jumpNext = min($totalPages, $page + $maxPage);
      ?>

      <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= "{$base}?{$qs}&page={$prev}" ?>" title = "Previous Page">&#x276E;</a>
      <?php endif; ?>

      <?php if ($start > 1): ?>
        <a class="page-btn" href="<?= "{$base}?{$qs}&page={$jumpBack}" ?>" title="Jump backward 5 pages">...</a>
      <?php endif; ?>

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <a class="page-btn <?= $i == $page ? 'active' : '' ?>" 
          href="<?= "{$base}?{$qs}&page={$i}" ?>"><?= $i ?></a>
      <?php endfor; ?>

      <?php if ($end < $totalPages): ?>
        <a class="page-btn" href="<?= "{$base}?{$qs}&page={$jumpNext}" ?>" title="Jump forward 5 pages">...</a>
      <?php endif; ?>

      <?php  if ($page < $totalPages): ?>
      <a class="page-btn" href="<?= "{$base}?{$qs}&page={$next}" ?>" title = "Next Page">&#x276F;</a>
      <?php  endif;?>
    </div>

</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>