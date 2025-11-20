<?php
// Printable audit log
$pageTitle = 'Audit Log (Print View)';

require_once __DIR__ . '/../partials/init.php';
if (!is_admin()) { redirect_to('/admin/login.php'); }
[$AUDIT_ID, $AUDIT_TIME] = audit_resolve_cols($pdo);
// Resolve audit table PK/timestamp columns
function audit_resolve_cols(PDO $pdo): array {
  $idCandidates   = ['ID','id','log_id','audit_id'];
  $timeCandidates = ['CREATED_AT','created_at','logged_at','timestamp','createdOn'];
  foreach ($idCandidates as $idCol) {
    foreach ($timeCandidates as $tCol) {
      try {
        $pdo->query("SELECT {$idCol} AS ID, {$tCol} AS CREATED_AT FROM AUDIT_LOG ORDER BY {$idCol} DESC LIMIT 1");
        return [$idCol, $tCol];
      } catch (Throwable $e) {}
    }
  }
  return ['ID', 'CREATED_AT'];
}

/* --- Filters --- */
$actor  = trim($_GET['actor'] ?? '');
$action = trim($_GET['action'] ?? '');
$table  = trim($_GET['table'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$PAGE_SIZE = 100;
$offset = ($page - 1) * $PAGE_SIZE;

// /* Named parameters only */
// $sqlBase = " FROM AUDIT_LOG WHERE 1=1 ";
// $params = [];
// if ($actor !== '')  { $sqlBase .= " AND ACTOR LIKE :actor";             $params[':actor'] = "%{$actor}%"; }
// if ($action !== '') { $sqlBase .= " AND ACTION_ENUM = :action";         $params[':action'] = $action; }
// if ($table !== '')  { $sqlBase .= " AND TABLE_NAME = :table";           $params[':table']  = $table; }
// if ($from  !== '')  { $sqlBase .= " AND DATE({$AUDIT_TIME}) >= :from";  $params[':from']   = $from; }
// if ($to    !== '')  { $sqlBase .= " AND DATE({$AUDIT_TIME}) <= :to";    $params[':to']     = $to; }

// /* Count */
// $stmtCnt = $pdo->prepare("SELECT COUNT(*) ".$sqlBase);
// $stmtCnt->execute($params);
// $total = (int)$stmtCnt->fetchColumn();
// $totalPages = max(1, (int)ceil($total / $PAGE_SIZE));

// /* Fetch page rows */
// $sql = "
//   SELECT {$AUDIT_ID} AS ID, {$AUDIT_TIME} AS CREATED_AT, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
//   $sqlBase
//   ORDER BY {$AUDIT_ID} DESC
//   LIMIT :lim OFFSET :off
// ";
// $stmt = $pdo->prepare($sql);
// foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
// $stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
// $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
// $stmt->execute();
// $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tables  = ['FACULTY','RESEARCH','AGENCY','FUNDING','ASSIGNMENT'];
$actions = ['CREATE','UPDATE','DELETE'];

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

/* Updated layout for three buttons */
.filter-bar .btn-action{ min-width:110px; } 
@media (max-width: 720px){ 
  .filter-bar .btn-action{ width:100%; } 
  .filter-bar .field:last-child { 
      grid-column: span 12; 
  }
}
</style>

<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Audit Log — Print View</h1>
  <form method="get" class="grid filter-bar">
    <div class="field" style="grid-column:span 2;">
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
    
    <div class="field" style="grid-column:span 2; display:flex; align-items:flex-end;">
      <a class="btn-action btn-ghost" style="width:100%;" href="<?= app_url('/admin/audit_print.php'); ?>">Clear</a>
    </div>

    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <button class="btn-action btn-ghost" type="button" onclick="window.print()" style="width:100%;">Print</button>
    </div>
    
    <div class="field" style="grid-column:span 1; display:flex; align-items:flex-end;">
      <button class="btn-action btn-primary" type="submit">Apply</button>
    </div>

  </form>
</section>

<!-- Printable content -->
<section class="panel fade-in" id="panel" style="background:#fff;"></section>
<script> 
  //Search
  const auditPanel = document.querySelector('#panel');
  const actorInput = document.querySelector('input[name="actor"]');
  const actionSelect = document.querySelector('select[name="action"]');
  const tableSelect = document.querySelector('select[name="table"]');
  const fromInput = document.querySelector('input[name="from"]');
  const toInput = document.querySelector('input[name="to"]');

  let timer = null;
  
  // fetch func
  function fetchResults(page) {
    const actor = actorInput.value;
    const action = actionSelect.value;
    const table = tableSelect.value;
    const from = fromInput.value;
    const to = toInput.value;
    const url = `../api/search_audit.php?actor=${encodeURIComponent(actor)}&action=${encodeURIComponent(action)}&table=${encodeURIComponent(table)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&page=${page}`;
    
    auditPanel.innerHTML = "<div class='loading'>Loading...</div>";
    
    fetch(url)
      .then(res => res.text())
      .then(html => {
        auditPanel.innerHTML = html;
        attachPaginationEvents();
      })
      .catch(err => {
        auditPanel.innerHTML = "<div class='error'>Failed to load results.</div>";
        console.error("Error:", err);
      });
  }
  
  // debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  }
  actorInput.addEventListener('input', handleLiveInput);
  actionSelect.addEventListener('change', () => fetchResults(1));
  tableSelect.addEventListener('change', () => fetchResults(1));
  fromInput.addEventListener('input', handleLiveInput);
  toInput.addEventListener('input', handleLiveInput);
  
  function attachPaginationEvents() {
    const links = document.querySelectorAll('.page-btn');
    
    links.forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const url = new URL(this.href);
        const page = url.searchParams.get('page') || 1;
        fetchResults(page);
      });
    });
  }
  fetchResults(1); 
</script>
<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
