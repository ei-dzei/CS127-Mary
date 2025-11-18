<?php
$pageTitle = 'Research (Admin)';

// Core
require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

/* Local validators */
if (!function_exists('v_date')) {
  function v_date($s): bool {
    if (!is_string($s) || $s === '') return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
  }
}
if (!function_exists('v_date_nullable')) {
  function v_date_nullable($s): bool { return ($s === null || $s === '') ? true : v_date($s); }
}

// Auth & CSRF
if (!is_admin()) { redirect_to('/admin/login.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

/* Actions */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_varchar($_POST['RESEARCH_TITLE'] ?? '', 255)) guardFail('Invalid title');
  if (!v_date($_POST['RESEARCH_STARTDATE'] ?? ''))     guardFail('Invalid start date');
  // NOTE: Server-side validation still allows nullable end dates for all statuses.
  if (!v_date_nullable($_POST['RESEARCH_ENDDATE'] ?? '')) guardFail('Invalid end date');
  if (!v_enum_exists($pdo, 'RESEARCH_STATUS', 'STATUS_CODE', $_POST['RESEARCH_STATUS'] ?? null)) guardFail('Invalid status');

  // --- START: DATE COMPARISON CHECK (CREATE) ---
  $startDate = $_POST['RESEARCH_STARTDATE'];
  $endDate   = $_POST['RESEARCH_ENDDATE'] ?? '';

  if (!empty($endDate) && (strtotime($endDate) < strtotime($startDate))) {
    set_flash_message('error', 'The End Date cannot be earlier than the Start Date.');
    redirect_to('/admin/crud/research.php');
    exit;
  }
  // --- END: DATE COMPARISON CHECK (CREATE) ---

  $pdo->prepare("INSERT INTO RESEARCH (RESEARCH_TITLE, RESEARCH_STARTDATE, RESEARCH_ENDDATE, RESEARCH_STATUS) VALUES (?,?,?,?)")
      ->execute([
        $_POST['RESEARCH_TITLE'],
        $_POST['RESEARCH_STARTDATE'],
        ($_POST['RESEARCH_ENDDATE'] ?? '') !== '' ? $_POST['RESEARCH_ENDDATE'] : null,
        $_POST['RESEARCH_STATUS']
      ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'RESEARCH']);

  redirect_to('/admin/crud/research.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['RESEARCH_ID'] ?? ''))             guardFail('Missing ID');
  if (!v_varchar($_POST['RESEARCH_TITLE'] ?? '', 255)) guardFail('Invalid title');
  if (!v_date($_POST['RESEARCH_STARTDATE'] ?? ''))     guardFail('Invalid start date');
  if (!v_date_nullable($_POST['RESEARCH_ENDDATE'] ?? '')) guardFail('Invalid end date');
  if (!v_enum_exists($pdo, 'RESEARCH_STATUS', 'STATUS_CODE', $_POST['RESEARCH_STATUS'] ?? null)) guardFail('Invalid status');

  // --- START: DATE COMPARISON CHECK (UPDATE) ---
  $startDate = $_POST['RESEARCH_STARTDATE'];
  $endDate   = $_POST['RESEARCH_ENDDATE'] ?? ''; 

  if (!empty($endDate) && (strtotime($endDate) < strtotime($startDate))) {
    set_flash_message('error', 'The End Date cannot be earlier than the Start Date (Update Failed).');
    redirect_to('/admin/crud/research.php');
    exit;
  }
  // --- END: DATE COMPARISON CHECK (UPDATE) ---

  $pdo->prepare("UPDATE RESEARCH SET RESEARCH_TITLE=?, RESEARCH_STARTDATE=?, RESEARCH_ENDDATE=?, RESEARCH_STATUS=? WHERE RESEARCH_ID=?")
      ->execute([
        $_POST['RESEARCH_TITLE'],
        $_POST['RESEARCH_STARTDATE'],
        ($_POST['RESEARCH_ENDDATE'] ?? '') !== '' ? $_POST['RESEARCH_ENDDATE'] : null,
        $_POST['RESEARCH_STATUS'],
        $_POST['RESEARCH_ID']
      ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'RESEARCH', $_POST['RESEARCH_ID']]);

  redirect_to('/admin/crud/research.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing ID');
  $pdo->prepare("DELETE FROM RESEARCH WHERE RESEARCH_ID=?")->execute([$_POST['RESEARCH_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'RESEARCH', $_POST['RESEARCH_ID']]);
  redirect_to('/admin/crud/research.php?ok=1');
}

/* Lookups */
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")
                ->fetchAll(PDO::FETCH_ASSOC);

/* Filters / Sorting / Pagination */
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$sort   = $_GET['sort'] ?? 'start_desc';
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 5;
$offset = ($page - 1) * $per;

$sortMap = [
  'title_asc'  => 'RESEARCH_TITLE ASC, RESEARCH_ID DESC',
  'status_asc' => 'RESEARCH_STATUS ASC, RESEARCH_TITLE ASC',
  'start_desc' => 'RESEARCH_STARTDATE DESC, RESEARCH_ID DESC',
  'start_asc'  => 'RESEARCH_STARTDATE ASC, RESEARCH_ID ASC',
  'end_desc'   => 'RESEARCH_ENDDATE DESC, RESEARCH_ID DESC',
  'end_asc'    => 'RESEARCH_ENDDATE ASC, RESEARCH_ID ASC',
  'id_desc'    => 'RESEARCH_ID DESC',
  'id_asc'     => 'RESEARCH_ID ASC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['start_desc'];

$baseSql = "FROM RESEARCH WHERE 1=1";
$params  = [];
if ($q !== '')      { $baseSql .= " AND RESEARCH_TITLE LIKE ?";       $params[] = "%$q%"; }
if ($status !== '') { $baseSql .= " AND RESEARCH_STATUS = ?";         $params[] = $status; }
if ($from !== '')   { $baseSql .= " AND RESEARCH_STARTDATE >= ?";     $params[] = $from; }
if ($to !== '')     { $baseSql .= " AND (RESEARCH_ENDDATE <= ? OR (RESEARCH_ENDDATE IS NULL AND RESEARCH_STARTDATE <= ?))"; array_push($params, $to, $to); }

/* Count */
$stmtCnt = $pdo->prepare("SELECT COUNT(*) $baseSql");
$stmtCnt->execute($params);
$total = (int)$stmtCnt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per));

/* Page rows */
$sql = "SELECT * $baseSql ORDER BY $orderSql LIMIT $per OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* View header */
require_once __DIR__ . '/../../partials/site_header.php';
$CSRF = csrf_token();
?>

<style>
/* --------- Inline modal --------- */
.admin-modal[hidden]{display:none!important;}
.admin-modal{
  position:fixed; inset:0; z-index:3000;
  display:grid; place-items:center;
}
.admin-modal__backdrop{position:absolute; inset:0; background:rgba(0,0,0,.45); backdrop-filter:blur(2px);}
.admin-modal__dialog{
  position:relative; width:min(980px, 92%); max-height:84vh; overflow:auto;
  background:#fff; border:1px solid rgba(11,83,148,.18); border-radius:16px;
  box-shadow:0 30px 60px rgba(0,0,0,.25);
}
.admin-modal__head{padding:14px 18px; border-bottom:1px solid rgba(11,83,148,.12); background:linear-gradient(180deg,rgba(11,83,148,.06),rgba(11,83,148,.04))}
.admin-modal__title{margin:0; font-family:'Patua One',serif; color:#003366;}
.admin-modal__close{position:absolute; right:12px; top:10px; width:36px; height:36px; border-radius:10px; border:1px solid #e5eaf0; background:#fff;}
.admin-modal__close:hover{background:#f4f7fb}
.admin-modal__body{padding:18px;}
.modal-grid{
  display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:16px;
}
@media (max-width: 900px){ .modal-grid{ grid-template-columns: 1fr; } }
.modal-grid .field{display:flex; flex-direction:column; gap:6px;}
.modal-grid .input, .modal-grid select{width:100%; padding:12px 14px; font-size:16px;}
.admin-modal__actions{display:flex; gap:10px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eef2f6;}
.btn.wide { min-width: 160px; }
.filter-bar .btn, .filter-bar .clear-btn { min-width: 140px; }
@media (max-width: 720px){
  .filter-bar .btn, .filter-bar .clear-btn { width:100%; }
}

/* Action buttons parity */
.btn-action{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:130px;height:40px;padding:0 16px;
  border-radius:8px;border:1px solid var(--color-accent);
  font-weight:600;text-decoration:none;cursor:pointer;
  transition:background .2s ease,color .2s ease,transform .06s ease,box-shadow .15s ease;
}
.btn-action:active{ transform: translateY(1px); }
.btn-primary{ background: var(--color-accent); color:#fff; }
.btn-primary:hover{ filter:brightness(.94); box-shadow:0 4px 10px rgba(0,0,0,.06); }
.btn-ghost{
  background:#fff;
  color: var(--color-accent);
  border-color: rgba(11,83,148,.35);
}
.btn-ghost:hover{ background: rgba(11,83,148,.05); }
</style>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Research</h1>
  <p class="muted" style="margin-bottom:10px;">Manage research, status, and dates. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=RESEARCH">Export CSV</a>
    <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="table" value="RESEARCH">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid filter-bar" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 4"><label>Title</label><input class="input" name="q" value="<?= htmlspecialchars($q); ?>"></div>
    <div class="field" style="grid-column: span 3">
      <label>Status</label>
      <select class="input" name="status">
        <option value="">All</option>
        <?php foreach ($statuses as $s):
          $sel = ($status === $s['STATUS_CODE']) ? ' selected' : '';
          echo '<option'.$sel.' value="'.htmlspecialchars($s['STATUS_CODE'], ENT_QUOTES).'">'.htmlspecialchars($s['STATUS_LABEL']).'</option>';
        endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2"><label>Start from</label><input class="input" type="date" name="from" value="<?= htmlspecialchars($from); ?>"></div>
    <div class="field" style="grid-column: span 2"><label>End by</label><input class="input" type="date" name="to" value="<?= htmlspecialchars($to); ?>"></div>
    <div class="field" style="grid-column: span 1">
      <label>Order</label>
      <select class="input" name="sort">
        <option value="start_desc" <?= $sort==='start_desc'?'selected':''; ?>>Start (Newest)</option>
        <option value="start_asc"  <?= $sort==='start_asc'?'selected':''; ?>>Start (Oldest)</option>
        <option value="end_desc"   <?= $sort==='end_desc'?'selected':''; ?>>End (Newest)</option>
        <option value="end_asc"    <?= $sort==='end_asc'?'selected':''; ?>>End (Oldest)</option>
        <option value="title_asc"  <?= $sort==='title_asc'?'selected':''; ?>>Title (A–Z)</option>
        <option value="status_asc" <?= $sort==='status_asc'?'selected':''; ?>>Status (A–Z)</option>
        <option value="id_desc"    <?= $sort==='id_desc'?'selected':''; ?>>ID (Newest)</option>
        <option value="id_asc"     <?= $sort==='id_asc'?'selected':''; ?>>ID (Oldest)</option>
      </select>
    </div>
    <div class="field" style="grid-column: span 12; display:flex; gap:10px; justify-content:flex-end; align-items:flex-end;">
      <button class="btn-action btn-primary" type="submit">Filter</button>
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/crud/research.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<section class="panel crud-form-card" style="margin-bottom:16px;">
  <h3 style="margin-top:0">Create Research</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 6"><label>Title</label><input class="input" name="RESEARCH_TITLE" required maxlength="255"></div>
    <div class="field" style="grid-column: span 3">
      <label>Status</label>
      <select class="input" name="RESEARCH_STATUS" id="create_status" required>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= htmlspecialchars($s['STATUS_CODE'], ENT_QUOTES); ?>"><?= htmlspecialchars($s['STATUS_LABEL']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2"><label>Start</label><input class="input" type="date" name="RESEARCH_STARTDATE" id="create_startdate" required></div>
    <div class="field" style="grid-column: span 1"><label>End</label><input class="input" type="date" name="RESEARCH_ENDDATE" id="create_enddate"></div>
    
    <div class="field" style="grid-column: span 12; display:flex; justify-content:flex-end;">
      <button class="btn wide">Add</button>
    </div>
  </form>
</section>

<section class="panel">
  <h3 style="margin-top:0">Records</h3>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Title</th><th>Status</th><th>Start</th><th>End</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= (int)$row['RESEARCH_ID']; ?></td>
            <td><?= htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
            <td><?= htmlspecialchars($row['RESEARCH_STATUS']); ?></td>
            <td><?= htmlspecialchars($row['RESEARCH_STARTDATE']); ?></td>
            <td><?= htmlspecialchars((string)$row['RESEARCH_ENDDATE']); ?></td>
            <td class="actions-cell">
              <button
                type="button"
                class="btn small js-edit"
                data-id="<?= (int)$row['RESEARCH_ID']; ?>"
                data-title="<?= htmlspecialchars($row['RESEARCH_TITLE'], ENT_QUOTES); ?>"
                data-start="<?= htmlspecialchars($row['RESEARCH_STARTDATE'], ENT_QUOTES); ?>"
                data-end="<?= htmlspecialchars((string)$row['RESEARCH_ENDDATE'], ENT_QUOTES); ?>"
                data-status="<?= htmlspecialchars($row['RESEARCH_STATUS'], ENT_QUOTES); ?>"
              >Edit</button>

              <form method="post" onsubmit="return confirm('Delete research?');" style="display:inline">
                <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="RESEARCH_ID" value="<?= (int)$row['RESEARCH_ID']; ?>">
                <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" style="text-align:center;color:#666;">No records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="pagination">
    <?php
      $qs = function($p) use ($q, $status, $from, $to, $sort) {
        $parts = ['page='.$p];
        if ($q      !== '') $parts[]='q='.rawurlencode($q);
        if ($status !== '') $parts[]='status='.rawurlencode($status);
        if ($from   !== '') $parts[]='from='.rawurlencode($from);
        if ($to     !== '') $parts[]='to='.rawurlencode($to);
        if ($sort   !== '') $parts[]='sort='.rawurlencode($sort);
        return implode('&',$parts);
      };
      $base = app_url('/admin/crud/research.php');
    ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(max(1,$page-1)); ?>">&laquo;</a>
    <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a class="page-btn <?= $i===$page?'active':''; ?>" href="<?= $base.'?'.$qs($i); ?>"><?= $i; ?></a>
    <?php endfor; ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(min($totalPages,$page+1)); ?>">&raquo;</a>
  </div>
</section>

<div class="admin-modal" id="researchModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="researchModalTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="researchModalTitle">Edit Research</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="RESEARCH_ID" id="m_id">

      <div class="modal-grid">
        <div class="field">
          <label for="m_title">Title</label>
          <input class="input" id="m_title" name="RESEARCH_TITLE" required maxlength="255">
        </div>
        <div class="field">
          <label for="m_start">Start</label>
          <input class="input" id="m_start" type="date" name="RESEARCH_STARTDATE" required>
        </div>
        <div class="field">
          <label for="m_end">End</label>
          <input class="input" id="m_end" type="date" name="RESEARCH_ENDDATE">
        </div>
        <div class="field">
          <label for="m_status">Status</label>
          <select class="input" id="m_status" name="RESEARCH_STATUS" required>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= htmlspecialchars($s['STATUS_CODE'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($s['STATUS_LABEL']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="admin-modal__actions">
        <button class="btn wide" type="submit">Save</button>
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// Helper to get today's date in YYYY-MM-DD format
function getTodayDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// -------------------------------------------------------------
// Logic for Create Form (New requirement: status dictates end date)
// -------------------------------------------------------------
(function() {
    const statusSelect = document.getElementById('create_status');
    const endDateInput = document.getElementById('create_enddate');

    const COMPLETED_CODE = 'COMPLETED'; // <-- ADJUST IF YOUR CODE IS DIFFERENT
    const ONGOING_CODE   = 'ONGOING';   // <-- ADJUST IF YOUR CODE IS DIFFERENT

    function handleStatusChange() {
        const selectedStatus = statusSelect.value;
        const today = getTodayDate();

        if (selectedStatus === COMPLETED_CODE) {
            // Completed status: Automatically populate end date with today's date
            endDateInput.value = today;
            endDateInput.removeAttribute('required'); // Should not be needed if not present
        } else if (selectedStatus === ONGOING_CODE) {
            // Ongoing status: Clear the end date
            endDateInput.value = '';
            endDateInput.removeAttribute('required');
        } else {
            // Default/Other status: Do nothing, allow manual input
            endDateInput.removeAttribute('required');
        }
    }

    if (statusSelect && endDateInput) {
        statusSelect.addEventListener('change', handleStatusChange);
        // Run once on load in case a default status is pre-selected
        handleStatusChange();
    }
})();

// -------------------------------------------------------------
// Modal controller (Existing JS, handles opening/closing and data transfer)
// -------------------------------------------------------------
(function(){
  const modal = document.getElementById('researchModal');
  const form  = modal.querySelector('form');
  const idI   = document.getElementById('m_id');
  const tI    = document.getElementById('m_title');
  const sI    = document.getElementById('m_start');
  const eI    = document.getElementById('m_end');
  const stI   = document.getElementById('m_status');

  const COMPLETED_CODE = 'COMPLETED'; // <-- ADJUST IF YOUR CODE IS DIFFERENT
  const ONGOING_CODE   = 'ONGOING';   // <-- ADJUST IF YOUR CODE IS DIFFERENT

  function handleModalStatusChange() {
    const selectedStatus = stI.value;
    const today = getTodayDate();

    if (selectedStatus === COMPLETED_CODE) {
        // If status is changed to Completed, set end date to today if empty
        if (!eI.value) {
            eI.value = today;
        }
    } else if (selectedStatus === ONGOING_CODE) {
        // If status is changed to Ongoing, clear end date
        eI.value = '';
    }
  }

  function open(payload){
    idI.value = payload.id;
    tI.value  = payload.title || '';
    sI.value  = payload.start || '';
    eI.value  = payload.end || '';
    stI.value = payload.status || '';
    
    // Attach change listener when modal is opened
    stI.addEventListener('change', handleModalStatusChange);
    // Initial check on load
    handleModalStatusChange();
    
    modal.hidden = false;
  }
  function close(){ 
    // Remove listener when closing to avoid multiple listeners
    stI.removeEventListener('change', handleModalStatusChange);
    modal.hidden = true; 
  }

  document.querySelectorAll('.js-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      open({
        id: btn.dataset.id,
        title: btn.dataset.title,
        start: btn.dataset.start,
        end: btn.dataset.end,
        status: btn.dataset.status
      });
    });
  });

  modal.addEventListener('click', e=>{ if (e.target.dataset.close) close(); });
  window.addEventListener('keydown', e=>{ if (!modal.hidden && e.key === 'Escape') close(); });
})();
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>