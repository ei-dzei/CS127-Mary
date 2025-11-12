<?php
// Printable audit log
$pageTitle = 'Audit Log (Print View)';

// Core first (sessions, DB, helpers)
require_once __DIR__ . '/../partials/init.php';

if (!is_admin()) { redirect_to('/admin/login.php'); }

// Resolve audit table PK/timestamp columns and alias them
function audit_resolve_cols(PDO $pdo): array {
  $idCandidates   = ['ID','id','log_id','audit_id'];
  $timeCandidates = ['CREATED_AT','created_at','logged_at','timestamp','createdOn'];

  foreach ($idCandidates as $idCol) {
    foreach ($timeCandidates as $tCol) {
      try {
        $pdo->query("SELECT {$idCol} AS ID, {$tCol} AS CREATED_AT FROM AUDIT_LOG ORDER BY {$idCol} DESC LIMIT 1");
        return [$idCol, $tCol]; 
      } catch (Throwable $e) { /* try next */ }
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

/* Build WHERE with named params */
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
$pages = max(1, (int)ceil($total / $PAGE_SIZE));

/* Page rows (named params for limit/offset too) */
$sql = "
  SELECT {$AUDIT_ID} AS ID, {$AUDIT_TIME} AS CREATED_AT, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
  $sqlBase
  ORDER BY {$AUDIT_ID} DESC
  LIMIT :lim OFFSET :off
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Utility: options for selects */
$tables  = ['FACULTY','RESEARCH','AGENCY','FUNDING','ASSIGNMENT'];
$actions = ['CREATE','UPDATE','DELETE','IMPORT'];

// Include header AFTER all logic
require_once __DIR__ . '/../partials/site_header.php';
?>

<style>
/* --------- Filter row helpers --------- */
.filter-bar .btn, .filter-bar .clear-btn { min-width: 140px; }
@media (max-width: 720px){
  .filter-bar .btn, .filter-bar .clear-btn { width:100%; }
}

/* --------- Action buttons in the filter row --------- */
.btn-action{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:130px;height:40px;padding:0 16px;
  border-radius:8px;border:1px solid var(--color-accent);
  font-weight:600;text-decoration:none;cursor:pointer;
  transition:background .2s ease,color .2s ease,transform .06s ease,box-shadow .15s ease;
}
.btn-action:active{ transform: translateY(1px); }
.btn-primary{
  background: var(--color-accent);
  color:#fff;
}
.btn-primary:hover{ filter:brightness(.94); box-shadow:0 4px 10px rgba(0,0,0,.06); }
.btn-ghost{
  background:#fff;
  color: var(--color-accent);
  border-color: rgba(11,83,148,.35);
}
.btn-ghost:hover{ background: rgba(11,83,148,.05); }
</style>

<!-- Screen-only filter panel (hidden when printing by print.css) -->
<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Audit Log — Print View</h1>
  <p class="muted" style="margin-bottom:10px;">
    Use filters then press <strong>Ctrl/Cmd + P</strong> to print. This page uses formal print styles automatically.
  </p>
  <form method="get" class="grid filter-bar">
    <div class="field" style="grid-column:span 3;">
      <label>Actor</label>
      <input class="input" name="actor" value="<?php echo htmlspecialchars($actor); ?>" placeholder="admin, user id, etc." />
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>Action</label>
      <select class="input" name="action">
        <option value="">All</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?php echo $a; ?>"<?php if ($action===$a) echo ' selected'; ?>><?php echo $a; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>Table</label>
      <select class="input" name="table">
        <option value="">All</option>
        <?php foreach ($tables as $t): ?>
          <option value="<?php echo $t; ?>"<?php if ($table===$t) echo ' selected'; ?>><?php echo $t; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>From</label>
      <input class="input" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
    </div>
    <div class="field" style="grid-column:span 2;">
      <label>To</label>
      <input class="input" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
    </div>
    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <button class="btn-action btn-primary" type="submit">Apply</button>
    </div>
    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <a class="btn-action btn-ghost" href="<?php echo app_url('/admin/audit_print.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<!-- Printable content -->
<section class="panel fade-in" style="background:#fff;">
  <div class="container" style="width:100%;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:10px;">
      <div>
        <h2 style="font-family:'Patua One', serif; margin:0;">School of Mary — Audit Log</h2>
        <div class="muted">
          Generated: <?php echo date('Y-m-d H:i:s'); ?>
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
      <div class="muted">Records: <?php echo number_format($total); ?></div>
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
              <td><?php echo (int)$r['ID']; ?></td>
              <td><?php echo htmlspecialchars($r['CREATED_AT']); ?></td>
              <td><?php echo htmlspecialchars($r['ACTOR']); ?></td>
              <td><?php echo htmlspecialchars($r['ACTION_ENUM']); ?></td>
              <td><?php echo htmlspecialchars($r['TABLE_NAME']); ?></td>
              <td><?php echo htmlspecialchars($r['PK_VALUE']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <div class="pagination">
          <?php
            $base = app_url('/admin/audit_print.php');
            $qs = "actor=".urlencode($actor)
                ."&action=".urlencode($action)
                ."&table=".urlencode($table)
                ."&from=".urlencode($from)
                ."&to=".urlencode($to);
            $prev = $page - 1; $next = $page + 1;
          ?>
          <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : "{$base}?{$qs}&page={$prev}"; ?>">&laquo;</a>
          <?php for ($i=1; $i<= $pages; $i++): ?>
            <a class="page-btn <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo "{$base}?{$qs}&page={$i}"; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <a class="page-btn <?php echo $page >= $pages ? 'disabled' : ''; ?>" href="<?php echo $page >= $pages ? '#' : "{$base}?{$qs}&page={$next}"; ?>">&raquo;</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
