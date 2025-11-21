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

  // START: NEW SERVER-SIDE DATE VALIDATION (Kept for security)
  $startDate = $_POST['RESEARCH_STARTDATE'] ?? '';
  $endDate = $_POST['RESEARCH_ENDDATE'] ?? '';
  if ($endDate !== '' && strtotime($endDate) < strtotime($startDate)) {
      guardFail('End Date cannot be earlier than Start Date.');
  }
  // END: NEW SERVER-SIDE DATE VALIDATION

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

  // START: NEW SERVER-SIDE DATE VALIDATION (Kept for security)
  $startDate = $_POST['RESEARCH_STARTDATE'] ?? '';
  $endDate = $_POST['RESEARCH_ENDDATE'] ?? '';
  if ($endDate !== '' && strtotime($endDate) < strtotime($startDate)) {
      guardFail('End Date cannot be earlier than Start Date.');
  }
  // END: NEW SERVER-SIDE DATE VALIDATION

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
  /* MODIFIED: Increased max width to 1200px */
  position:relative; width:min(1200px, 92%); max-height:84vh; overflow:auto;
  background:#fff; border:1px solid rgba(11,83,148,.18); border-radius:16px;
  box-shadow:0 30px 60px rgba(0,0,0,.25);
}
.admin-modal__head{padding:14px 18px; border-bottom:1px solid rgba(11,83,148,.12); background:linear-gradient(180deg,rgba(11,83,148,.06),rgba(11,83,148,.04))}
.admin-modal__title{margin:0; font-family:'Patua One',serif; color:#003366;}
.admin-modal__close{position:absolute; right:12px; top:10px; width:36px; height:36px; border-radius:10px; border:1px solid #e5eaf0; background:#fff;}
.admin-modal__close:hover{background:#f4f7fb}
.admin-modal__body{padding:18px;}
.modal-grid{
  /* MODIFIED: Adjusted column ratios to give the title more space */
  display:grid; grid-template-columns: 3fr 1fr 1fr 1fr; gap:16px;
}
@media (max-width: 900px){ .modal-grid{ grid-template-columns: 1fr; } }
.modal-grid .field{display:flex; flex-direction:column; gap:6px;}
/* MODIFIED: Added style for disabled inputs */
.modal-grid .input:disabled, .modal-grid select:disabled{
  background-color: #e9ecef; 
  opacity: 0.6;
  cursor: not-allowed;
}
.modal-grid .input, .modal-grid select{width:100%; padding:12px 14px; font-size:16px;}
.admin-modal__actions{display:flex; gap:10px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eef2f6;}
.btn.wide { min-width: 160px; }

/* ------------------------------------------- */
/* START: NEW HEADER AND FILTER BAR STYLES */
/* ------------------------------------------- */

/* Alignment for Research header and Action buttons */
.crud-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px; /* Space between header and filter bar */
}
.crud-header-text {
    /* Container for H1 and P tag */
}
.crud-header-text h1 {
    margin: 0;
}
.crud-header-text p {
    margin: 4px 0 0 0;
}
.crud-header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Filter Bar: All inputs on one line */
.filter-bar {
  /* MODIFIED: Using 12-column grid for precise placement */
  grid-template-columns: repeat(12, 1fr); 
  gap: 10px;
}
.filter-bar .field {
    /* Ensure label and input stack within the field container */
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Column Spans for desktop view (12 columns total) */
.filter-bar .field:nth-child(1) { grid-column: span 3; } /* Title (3/12) */
.filter-bar .field:nth-child(2) { grid-column: span 2; } /* Status (2/12) */
.filter-bar .field:nth-child(3) { grid-column: span 2; } /* Start from (2/12) */
.filter-bar .field:nth-child(4) { grid-column: span 2; } /* End by (2/12) */
.filter-bar .field:nth-child(5) { grid-column: span 2; } /* Order (2/12) */

/* MODIFIED: Clear button placement and alignment */
.filter-bar .filter-actions { 
    grid-column: 12 / 13; /* Places the button in the very last column (column 12) */
    padding-top: 20px; /* Vertical alignment with inputs */
    display: flex; 
    justify-content: flex-end; /* Push the button to the right edge */
    align-items: flex-start; 
}
.filter-bar .filter-actions .btn-action {
    min-width: 100px; /* Tweak width to fit neatly in the smaller column */
    height: 40px; 
}

@media (max-width: 992px) {
  /* Tablet View: Stacking layout */
  .filter-bar { grid-template-columns: repeat(12, 1fr); align-items: stretch; }
  .filter-bar .field:nth-child(1) { grid-column: span 6; }
  .filter-bar .field:nth-child(2) { grid-column: span 6; }
  .filter-bar .field:nth-child(3) { grid-column: span 3; }
  .filter-bar .field:nth-child(4) { grid-column: span 3; }
  .filter-bar .field:nth-child(5) { grid-column: span 4; }
  .filter-bar .filter-actions { 
      grid-column: span 2; 
      padding-top: 0; 
      justify-content: flex-end; 
  }
}

@media (max-width: 720px){ 
  /* Mobile: Full-width stacking */
  .filter-bar .field, .filter-bar .filter-actions { 
      grid-column: span 12 !important; 
      justify-content: center;
  }
}

/* ------------------------------------------- */
/* END: NEW HEADER AND FILTER BAR STYLES */
/* ------------------------------------------- */


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

/* START: MODIFIED FOR ACTIONS COLUMN COMPACTNESS */
.crud-table td:last-child {
  /* Ensure the content is left-aligned and compact */
  text-align: left; 
  padding: 8px 10px; /* Reduced padding */
}

/* Tighter action buttons */
.crud-table .btn-action {
  min-width: 60px; /* Reduced min width */
  height: 32px; /* Reduced height */
  padding: 0 10px; /* Reduced padding inside */
  font-size: 0.9em; /* Smaller font */
  margin-right: 4px; /* Space between buttons */
}
/* END: MODIFIED FOR ACTIONS COLUMN COMPACTNESS */
</style>

<section class="panel fade-in crud-header-card">
  <div class="crud-header-top">
    <div class="crud-header-text">
        <h1>Research</h1>
        <p class="muted">Manage research, status, and dates. Use the fields below to filter the list.</p>
    </div>
    <div class="crud-header-actions">
        <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=RESEARCH">Export CSV</a>
        <button class="btn btn-action btn-primary" id="create-research">+ Create Research</button>
    </div>
  </div>
  <form method="get" class="grid filter-bar" style="margin-top:10px; margin-bottom:10px;">
    <div class="field">
      <label>Title (Live Search)</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>"></div>
    <div class="field">
      <label>Status</label>
      <select class="input" name="status">
        <option value="">All</option>
        <?php foreach ($statuses as $s):
          $sel = ($status === $s['STATUS_CODE']) ? ' selected' : '';
          echo '<option'.$sel.' value="'.htmlspecialchars($s['STATUS_CODE'], ENT_QUOTES).'">'.htmlspecialchars($s['STATUS_LABEL']).'</option>';
        endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Start from</label>
      <input class="input" type="date" name="from" value="<?= htmlspecialchars($from); ?>">
    </div>
    <div class="field">
      <label>End by</label>
      <input class="input" type="date" name="to" value="<?= htmlspecialchars($to); ?>"></div>
    <div class="field">
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
    <div class="filter-actions">
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/crud/research.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<section class="panel" id="panel"></section>
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
          <input class="input" id="r_title" name="RESEARCH_TITLE" required autofocus>
        </div>
        <div class="field">
          <label for="r_status">Status</label>
          <select class="input" id="r_status" name="RESEARCH_STATUS" required disabled>
            <option value="" disabled selected>Select Status</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= htmlspecialchars($s['STATUS_CODE'], ENT_QUOTES); ?>"><?= htmlspecialchars($s['STATUS_LABEL']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="r_start">Start Date</label>
          <input class="input" id="r_start" type="date" name="RESEARCH_STARTDATE" required disabled>
        </div>
        <div class="field">
          <label for="r_end">End Date</label>
          <input class="input" id="r_end" type="date" name="RESEARCH_ENDDATE" disabled>
        </div>
        
      </div>

      <div class="admin-modal__actions">
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
        <button class="btn wide btn-primary" type="submit">Create Research</button>
      </div>
    </form>
  </div>
</div>
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
          <input class="input" id="m_title" name="RESEARCH_TITLE" required>
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
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
        <button class="btn wide btn-primary" type="submit">Save</button>
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
  
  // CLIENT-SIDE DATE VALIDATION FUNCTION
  function validateDates(startDateId, endDateId, formEvent) {
    const startInput = document.getElementById(startDateId);
    const endInput = document.getElementById(endDateId);
    
    // Only compare if an end date is present and not disabled
    if (endInput.value && startInput.value && !endInput.disabled) {
      const startDate = new Date(startInput.value);
      const endDate = new Date(endInput.value);
      
      // Compare dates. Using getTime() ensures comparison is numerical
      if (endDate.getTime() < startDate.getTime()) {
        alert('Error: End Date cannot be earlier than the Start Date.');
        formEvent.preventDefault(); // Stop form submission
        endInput.focus();
        return false;
      }
    }
    return true;
  }
  
  // LOGIC FOR FIELD ENABLING/DISABLING AND MIN/MAX DATES
  function applyResearchConstraints(statusSelectElement, titleInput, startInput, endInput, isNewRecord) {
    const status = statusSelectElement.value;
    const isOngoing = status === 'ONGOING';
    const isStatusSelected = status !== '';

    // In Edit mode, Title is always enabled. In Create mode, Title enabling is handled by the title listener (see below)

    // 1. Start Date is enabled when a status is selected
    if (isNewRecord) {
        startInput.disabled = !isStatusSelected;
    } else {
        // Edit mode: Start Date is enabled as long as status is selected
        startInput.disabled = !isStatusSelected;
    }


    // 2. End Date logic based on status
    if (isOngoing) {
        // Case 1: Ongoing -> End Date is not clickable
        endInput.disabled = true;
        endInput.value = ''; // Clear value for ongoing
        endInput.removeAttribute('min'); // Clear min constraint
    } else if (isStatusSelected) {
        // Cases 2, 3, 4: Completed, Cancelled, Suspended -> Both dates clickable
        endInput.disabled = false;
    } else {
        // No status selected 
        endInput.disabled = true;
        endInput.removeAttribute('min');
    }

    // 3. End Date constraint: cannot be earlier than Start Date
    startInput.addEventListener('input', () => {
        // Set the min attribute on End Date to the value of Start Date
        if (startInput.value) {
           endInput.setAttribute('min', startInput.value);
        } else {
           endInput.removeAttribute('min');
        }
    });
    
    // Initial check in case the status is changed
    if (startInput.value) {
       endInput.setAttribute('min', startInput.value);
    }
  }

  // --- Create Modal Logic ---
  const createResearchModal = document.getElementById('createResearchModal');
  const r_title = document.getElementById('r_title');
  const r_start = document.getElementById('r_start');
  const r_end = document.getElementById('r_end');
  const r_status = document.getElementById('r_status');
  const createResearchForm = createResearchModal.querySelector('form');

  // Initial setup for create modal: Title is ENABLED, everything else DISABLED
  r_status.disabled = true;
  r_start.disabled = true;
  r_end.disabled = true;
  
  // NEW: Title input listener to enable Status
  r_title.addEventListener('input', () => {
      const titleHasText = r_title.value.trim() !== '';
      r_status.disabled = !titleHasText;
      
      // If title is cleared, disable everything downstream
      if (!titleHasText) {
          r_status.value = '';
          r_start.disabled = true;
          r_end.disabled = true;
      }
  });

  // Status change listener to enable/disable dates
  r_status.addEventListener('change', () => {
      // isNewRecord is true for the Create modal
      applyResearchConstraints(r_status, r_title, r_start, r_end, true);
  });
  
  function openCreateResearchModal() {
    // Reset values
    r_title.value = '';
    r_start.value = '';
    r_end.value = '';
    r_status.value = ''; // Forces 'Select Status' option
    
    // Reset disabled states to initial state: Title ENABLED, rest DISABLED
    r_title.disabled = false;
    r_status.disabled = true;
    r_start.disabled = true;
    r_end.disabled = true;
    r_end.removeAttribute('min');

    createResearchModal.hidden = false; 
  }

  function closeCreateResearchModal() { 
    createResearchModal.hidden = true; 
  }
  
  document.getElementById('create-research').addEventListener('click', function() {
    openCreateResearchModal(); 
  });
      
  createResearchModal.addEventListener('click', e => {
    if (e.target.dataset.close) closeCreateResearchModal();
  });
  
  window.addEventListener('keydown', e => {
    if (!createResearchModal.hidden && e.key === 'Escape') closeCreateResearchModal();
  });
  
  // Attach validation to CREATE form
  createResearchForm.addEventListener('submit', function(e) {
    validateDates('r_start', 'r_end', e);
  });


  // --- Edit Modal Logic (No change needed here, sequential rule only applies to Create) ---
  const modal = document.getElementById('researchModal');
  const form  = modal.querySelector('form');
  const idI   = document.getElementById('m_id');
  const tI    = document.getElementById('m_title');
  const sI    = document.getElementById('m_start');
  const eI    = document.getElementById('m_end');
  const stI   = document.getElementById('m_status');

  // Add change listener to m_status
  stI.addEventListener('change', () => {
      // For Edit modal, title is always enabled, so we pass false for isNewRecord
      applyResearchConstraints(stI, tI, sI, eI, false); 
  });
  
  // Add input listener to m_start for dynamic min date on m_end (already present)
  sI.addEventListener('input', () => {
      if (sI.value) {
         eI.setAttribute('min', sI.value);
      } else {
         eI.removeAttribute('min');
      }
  });
  
  function open(payload){
    idI.value = payload.id;
    tI.value  = payload.title || '';
    sI.value  = payload.start || '';
    eI.value  = payload.end || '';
    stI.value = payload.status || '';

    // Apply constraints immediately upon opening to set correct disabled states
    // Edit mode constraints: Title is enabled, Dates/Status are enabled/disabled based on current status.
    applyResearchConstraints(stI, tI, sI, eI, false); 
    
    modal.hidden = false;
  }
  function close(){ modal.hidden = true; }
  
  // Attach validation to UPDATE form
  form.addEventListener('submit', function(e) {
    validateDates('m_start', 'm_end', e);
  });
  
  
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