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
  if (!v_date_nullable($_POST['RESEARCH_ENDDATE'] ?? '')) guardFail('Invalid end date');
  if (!v_enum_exists($pdo, 'RESEARCH_STATUS', 'STATUS_CODE', $_POST['RESEARCH_STATUS'] ?? null)) guardFail('Invalid status');

  // --- START: DATE COMPARISON CHECK (CREATE) ---
  $startDate = $_POST['RESEARCH_STARTDATE'];
  $endDate   = $_POST['RESEARCH_ENDDATE'] ?? '';

  if (!empty($endDate) && (strtotime($endDate) < strtotime($startDate))) {
    // MODIFIED: Use set_flash_message for toast popup display
    //set_flash_message('error', 'The End Date cannot be earlier than the Start Date.');
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
    // MODIFIED: Use set_flash_message for toast popup display
    //set_flash_message('error', 'The End Date cannot be earlier than the Start Date (Update Failed).');
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
$CSRF = csrf_token();

/* View header */
require_once __DIR__ . '/../../partials/site_header.php';
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

/* --- Table Optimization (Maximization Fix for Research) --- */
.table-scroll table {
    table-layout: fixed; 
    width: 100%;
}

.table-scroll table td {
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis; 
}

/* Set explicit widths for non-flexible columns */
.table-scroll table th:nth-child(1), /* ID column */
.table-scroll table td:nth-child(1) {
    width: 60px; /* ID width fixed */
    text-align: left;
    white-space: normal; 
    overflow: visible; 
    text-overflow: clip; 
}
.table-scroll table th:nth-child(3), /* Status column */
.table-scroll table td:nth-child(3) {
    width: 100px; /* Status width fixed */
    text-align: center;
}
.table-scroll table th:nth-child(4), /* Start Date column */
.table-scroll table td:nth-child(4) {
    width: 100px; /* Start Date width fixed */
    text-align: center;
}
.table-scroll table th:nth-child(5), /* End Date column */
.table-scroll table td:nth-child(5) {
    width: 100px; /* End Date width fixed */
    text-align: center;
}
.table-scroll table th:nth-child(6), /* Actions column */
.table-scroll table td:nth-child(6) {
    width: 160px; /* Actions width fixed */
}
/* Title column (2nd child) is left flexible to take up maximum remaining space. */
.table-scroll table th:nth-child(2),
.table-scroll table td:nth-child(2) {
    text-align: left;
}
/* --- End of Fix --- */
</style>

<section class="panel fade-in crud-header-card">
  <button class="btn btn-action" id="create-research" style="float:inline-end">+ Create Research</button>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; float: inline-end">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=RESEARCH">Export CSV</a>
  </div>
  <h1 style="margin-bottom:8px;">Research</h1>
  <p class="muted" style="margin-bottom:10px;">Manage research, status, and dates. CSV export below.</p>

  

  <form method="get" class="grid filter-bar" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 4">
      <label>Title</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>"></div>
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
    <div class="field" style="grid-column: span 2">
      <label>Start from</label>
      <input class="input" type="date" name="from" value="<?= htmlspecialchars($from); ?>">
    </div>
    <div class="field" style="grid-column: span 2">
      <label>End by</label>
      <input class="input" type="date" name="to" value="<?= htmlspecialchars($to); ?>"></div>
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

<section class="panel" id="panel"></section>
<!-- Create Research Modal -->
<div class="admin-modal" id="createResearchModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="createResearchTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="createResearchTitle">Create New Research</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="action" value="create">

      <div class="modal-grid">
        <div class="field">
          <label for="r_title"> Title</label>
          <input class="input" id="r_title" name="RESEARCH_TITLE" required>
        </div>
        <div class="field">
          <label for="r_start">Start Date</label>
          <input class="input" id="r_start" type="date" name="RESEARCH_STARTDATE" required>
        </div>
        <div class="field">
          <label for="r_end">End Date</label>
          <input class="input" id="r_end" type="date" name="RESEARCH_ENDDATE">
        </div>
      </div>
      <div class="field">
        <label for="r_status">Status</label>
        <select class="input" id="r_status" name="RESEARCH_STATUS" required>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= htmlspecialchars($s['STATUS_CODE'], ENT_QUOTES); ?>"><?= htmlspecialchars($s['STATUS_LABEL']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="admin-modal__actions">
        <button class="btn wide" type="submit">Create Research</button>
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
      </div>
    </form>
  </div>
</div>
<!-- --------- Modal HTML --------- -->
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
  const researchPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const statusSelect = document.querySelector('select[name="status"]');
  const fromInput = document.querySelector('input[name="from"]');
  const toInput = document.querySelector('input[name="to"]');
  const sortSelect = document.querySelector('select[name="sort"]');
  let timer = null;

  // fetch func
  function fetchResults(page) {
    const q = queryInput.value;
    const status = statusSelect.value;
    const from = fromInput.value;
    const to = toInput.value;
    const sort = sortSelect.value;
    const url = `../api/search_research.php?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&sort=${encodeURIComponent(sort)}&page=${page}`;    
    
    researchPanel.innerHTML = "<div class='loading'>Loading...</div>";
    
    fetch(url)
      .then(res => res.text())
      .then(html => {
        researchPanel.innerHTML = html;
        attachPaginationEvents();
        attachEditButtons(); 
      })
      .catch(err => {
        researchPanel.innerHTML = "<div class='error'>Failed to load results.</div>";
        console.error("Error:", err);
      });
  }
  
  // debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  }
  queryInput.addEventListener('input', handleLiveInput);
  statusSelect.addEventListener('change', () => fetchResults(1));
  fromInput.addEventListener('input', handleLiveInput);
  toInput.addEventListener('input', handleLiveInput);
  sortSelect.addEventListener('change', () => fetchResults(1));
  
  // Attach pagination events
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
  //Create Research
  const createResearchModal = document.getElementById('createResearchModal');
  //const createAgencyForm = createAgencyModal.querySelector('form');
  const r_title = document.getElementById('r_title');
  const r_start = document.getElementById('r_start');
  const r_end = document.getElementById('r_end');
  const r_status = document.getElementById('r_status');
  
  function openResearchModal() {
    r_title.value = '';
    r_start.value = '';
    r_end.value = '';
    r_status.value = '';
  
    createResearchModal.hidden = false; 
  }

  function closeResearchModal() { 
    createResearchModal.hidden = true; 
  }
  
  document.getElementById('create-research').addEventListener('click', function() {
    openResearchModal(); 
  });
      
  createResearchModal.addEventListener('click', e => {
    if (e.target.dataset.close) closeResearchModal();
  });
  
  window.addEventListener('keydown', e => {
    if (!createResearchModal.hidden && e.key === 'Escape') closeResearchModal();
  });
// Edit Modal controller
// --- START: CLIENT-SIDE DATE VALIDATION ---

// Function to handle validation on Create/Edit forms
function validateResearchDates(startInput, endInput, event) {
    const startDate = startInput.value;
    const endDate = endInput.value;

    // Check only if both dates are provided
    if (startDate && endDate && (new Date(endDate) < new Date(startDate))) {
        alert('The End Date cannot be earlier than the Start Date.');
        event.preventDefault(); // Stop form submission
        return false;
    }
    return true;
}

// Function to handle validation on the Filter form
function validateFilterDates(startInput, endInput, event) {
    const startDate = startInput.value;
    const endDate = endInput.value;

    if (startDate && endDate && (new Date(startDate) > new Date(endDate))) {
        alert("The 'Start from' date cannot be later than the 'End by' date.");
        event.preventDefault(); // Stop form submission
        return false;
    }
    return true;
}


// 1. Attach validation to CREATE FORM submission
document.querySelector('section.crud-form-card form').addEventListener('submit', function(e) {
    const startInput = document.getElementById('research_startdate');
    const endInput = document.getElementById('research_enddate');
    validateResearchDates(startInput, endInput, e);
});

// 2. Attach validation to EDIT MODAL submission
document.querySelector('#researchModal form').addEventListener('submit', function(e) {
    const startInput = document.getElementById('m_start');
    const endInput = document.getElementById('m_end');
    validateResearchDates(startInput, endInput, e);
});

// 3. Attach validation to FILTER BAR submission
document.querySelector('form.filter-bar').addEventListener('submit', function(e) {
    // Get inputs directly by name for the filter bar
    const startInput = this.querySelector('input[name="from"]');
    const endInput = this.querySelector('input[name="to"]');
    validateFilterDates(startInput, endInput, e);
});

// --- END: CLIENT-SIDE DATE VALIDATION ---

// Modal controller (Existing JS, handles opening/closing and data transfer)
(function(){
  const modal = document.getElementById('researchModal');
  const form  = modal.querySelector('form');
  const idI   = document.getElementById('m_id');
  const tI    = document.getElementById('m_title');
  const sI    = document.getElementById('m_start');
  const eI    = document.getElementById('m_end');
  const stI   = document.getElementById('m_status');

  function open(payload){
    idI.value = payload.id;
    tI.value  = payload.title || '';
    sI.value  = payload.start || '';
    eI.value  = payload.end || '';
    stI.value = payload.status || '';
    
    // Clear any previous error states (best practice)
    tI.classList.remove('input-error');
    
    modal.hidden = false;
  }
  function close(){ modal.hidden = true; }
  
  function attachEditButtons() {
    const editButtons = document.querySelectorAll('.js-edit');
    
    editButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        open({
          id: this.dataset.id,
          title: this.dataset.title,
          start: this.dataset.start,
          end: this.dataset.end,
          status: this.dataset.status
        });
      });
    });
  }

  modal.addEventListener('click', e => {
    if (e.target.dataset.close) close();
  });
  
  window.addEventListener('keydown', e => {
    if (!modal.hidden && e.key === 'Escape') close();
  });
  fetchResults(1);
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>