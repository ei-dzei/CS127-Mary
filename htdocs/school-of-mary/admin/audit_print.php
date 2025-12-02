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
.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.field label {
  font-weight: 500;
  color: #4b5563;
}
.field .input { 
  padding: 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  height: 38px; 
  box-sizing: border-box;
}

/* --- SEARCH BAR AND TOGGLE STYLES (Full Width, Matching Look) --- */
.searchbox {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 15px;
  background: #fff;
  border: 1px solid #c7d2e4;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  flex: 1; 
  box-sizing: border-box;
  width: auto;
  height: 57px;
}
.searchbox svg:first-child {
  color: #6b7280;
}
.searchbox input[type="search"] { 
  flex-grow: 1;
  border: none;
  padding: 0;
  height: 1.5em; 
  font-size: 1rem;
}
/* FILTER */
.filterbar {
  position: relative; 
  z-index: 10;
  overflow: visible;
}
.filter-toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  background: transparent;
  border: none;
  cursor: pointer;
  color: #4b5563; 
}
.filter-toggle-btn:hover {
  color: #1f2937;
}
#filter-dropdown {
  display: none; 
  position: absolute;
  top: 100%; 
  right: 0; 
  margin-top: 8px;
  width: min(100%, 550px); 
  background: #fff;
  border: 1px solid #c7d2e4;
  border-radius: 8px;
  box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  z-index: 9999;
  padding: 15px;
}
#filter-options {
  display: grid;
  grid-template-columns: repeat(4, 1fr); 
  gap: 10px;
  margin-bottom: 15px;
}
/* CLEAR BUTTON */
.clear-btn-container {
  text-align: left;
}
.clear-btn-container .btn-primary {
  background-color: #0b5394;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  width: 100%;
}
.clear-btn-container .btn-primary:hover {
  background-color: #0b5394;
}
.panel.fade-in:first-of-type {
  position: relative;
  z-index: 5000;
}
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
  .panel.fade-in:first-child, 
  .pagination,
  #print-button-section { 
    display: none !important;
  }
  body, .container {
    margin: 0 !important;
    padding: 0 !important;
  }
  .panel.fade-in:last-child {
    margin-top: 0 !important;
  }
  table {
    page-break-inside: auto; 
  }
  tr {
    page-break-inside: avoid; 
    page-break-after: auto;
  }
}
</style>

<section class="panel fade-in">
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; float:inline-end">
    <button class="btn-action btn-primary" onclick="window.print()" style="min-width:160px;">
      <span style="font-size:1.2em; margin-right:8px;">&#x1F5B6;&#xFE0F;</span> Print Audit Log
    </button>
  </div>
  <h1 style="margin-bottom:8px;">Audit Log — Print View</h1>
  <p class="muted" style="margin-bottom:10px;">Check changes made in database.</p>
  <form method="get" class="filterbar">
    <div class="searchbox">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
      </svg>
      <input class="input" name="actor" value="<?= htmlspecialchars($actor); ?>" placeholder="Search actor..." />
      <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button" >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
      </button>
    </div>
    <div id="filter-dropdown">
      <div id="filter-options">
          <div class="field">
            <label>Action</label>
            <select class="input" name="action">
              <option value="">All</option>
              <?php foreach ($actions as $a): ?>
                <option value="<?= $a; ?>"<?= $action===$a?' selected':''; ?>><?= $a; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Table</label>
            <select class="input" name="table">
              <option value="">All</option>
              <?php foreach ($tables as $t): ?>
                <option value="<?= $t; ?>"<?= $table===$t?' selected':''; ?>><?= $t; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>From</label>
            <input class="input" type="date" name="from" value="<?= htmlspecialchars($from); ?>">
          </div>
          <div class="field">
            <label>To</label>
            <input class="input" type="date" name="to" value="<?= htmlspecialchars($to); ?>">
          </div>
      </div>
      <div class="clear-btn-container">
        <button class="btn-primary" id="clear-btn" type="button" >Clear Filters</button>
      </div>
    </div>
  </form>
</section>

<section class="panel fade-in" style="background:#fff;">
  <div class="container" style="width:100%;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:10px;">
      <div>
        <h2 style="font-family:'Patua One', serif; margin:0;">School of Mary — Audit Log</h2>
        <div class="muted">
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
<script>
  const actorInput = document.querySelector('input[name="actor"]');
  const actionSelect = document.querySelector('select[name="action"]');
  const tableSelect = document.querySelector('select[name="table"]');
  const fromInput = document.querySelector('input[name="from"]');
  const toInput = document.querySelector('input[name="to"]');
  const filterDropdown = document.querySelector('#filter-dropdown');
  const filterButton = document.querySelector('#filter-btn');
  const clearFiltersButton = document.querySelector('#clear-btn');
  // Toggle visibility of the filter dropdown
  function toggleFilters(e) {
      if (e) e.preventDefault();
      e.stopPropagation();
      if (filterDropdown.style.display === "none" || filterDropdown.style.display === "") {
        filterDropdown.style.display = "block";
      } else {
        filterDropdown.style.display = "none";
      }
  }
  document.addEventListener('click', function(e) {
  if (!filterDropdown.contains(e.target) && e.target !== filterButton) {
    filterDropdown.style.display = "none";
  }
  });
  function clearFilters(e) {
      if (e) e.preventDefault();
      actorInput.value = '';
      actionSelect.value = '';
      tableSelect.value = '';
      fromInput.value = '';
      toInput.value = '';
      fetchResults(1);
      filterDropdown.style.display = "none";
  }
  clearFiltersButton.addEventListener('click', clearFilters);
  filterButton.addEventListener('click', toggleFilters);
</script>
<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>