<?php
$pageTitle = 'Assignments (Admin)';

require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

/* ------- Local validators (dates) ------- */
if (!function_exists('v_date')) {
  function v_date($s): bool {
    if (!is_string($s) || $s === '') return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
  }
}
if (!function_exists('v_date_nullable')) {
  function v_date_nullable($s): bool {
    if ($s === null || $s === '') return true;
    return v_date($s);
  }
}

/* ------- Auth & CSRF ------- */
if (!is_admin()) { redirect_to('/admin/login.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

/* ------------------------- Actions ------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_int($_POST['FACULTY_ID'] ?? '') || !v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_varchar($_POST['ROLE_ID'] ?? '', 2)) guardFail('Invalid role');
  if (!v_date($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date');

  $pdo->prepare("INSERT INTO ASSIGNMENT (FACULTY_ID, RESEARCH_ID, ROLE_ID, DATE_ASSIGNED) VALUES (?,?,?,?)")
      ->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'ASSIGNMENT']);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['ASSIGNMENT_ID'] ?? '')) guardFail('Missing ID');
  if (!v_int($_POST['FACULTY_ID'] ?? '') || !v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_varchar($_POST['ROLE_ID'] ?? '', 2)) guardFail('Invalid role');
  if (!v_date($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date');

  $pdo->prepare("UPDATE ASSIGNMENT SET FACULTY_ID=?, RESEARCH_ID=?, ROLE_ID=?, DATE_ASSIGNED=? WHERE ASSIGNMENT_ID=?")
      ->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED'], $_POST['ASSIGNMENT_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['ASSIGNMENT_ID'] ?? '')) guardFail('Missing ID');

  $pdo->prepare("DELETE FROM ASSIGNMENT WHERE ASSIGNMENT_ID=?")->execute([$_POST['ASSIGNMENT_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

/* ------------------------- Lookups ------------------------- */
$fac   = $pdo->query("SELECT FACULTY_ID, FACULTY_LNAME, FACULTY_FNAME FROM FACULTY ORDER BY FACULTY_LNAME, FACULTY_FNAME")->fetchAll(PDO::FETCH_ASSOC);
$res   = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")->fetchAll(PDO::FETCH_ASSOC);
$roles = $pdo->query("SELECT ROLE_ID, ROLE_DESCRIPTION FROM ROLE ORDER BY ROLE_ID")->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------- Filters + Sorting + Pagination ------------------------- */
$q      = trim($_GET['q'] ?? '');
$sort   = $_GET['sort'] ?? 'id_desc'; // id_desc|id_asc|date_desc|date_asc|faculty_asc|faculty_desc|research_asc|research_desc
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 5;
$offset = ($page - 1) * $per;
$CSRF = csrf_token();
require_once __DIR__ . '/../../partials/site_header.php';
?>

<style>
/* --------- Inline modal (Existing CSS) --------- */
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

/* MODIFIED: Modal grid for 4 fields */
.modal-grid{
  display:grid; 
  /* NEW: Use 4 equal columns for the four fields (Faculty, Research, Role, Date) */
  grid-template-columns: repeat(4, 1fr); 
  gap:16px;
}
@media (max-width: 900px){ 
  /* Ensure stacking for smaller screens */
  .modal-grid{ grid-template-columns: 1fr; } 
}

.modal-grid .field{display:flex; flex-direction:column; gap:6px;}
.modal-grid .input, .modal-grid select{width:100%; padding:12px 14px; font-size:16px;}
.admin-modal__actions{display:flex; gap:10px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eef2f6;}
.btn.wide { min-width: 160px; }


/* MODIFIED: Filter Bar Styles for Alignment and Grid */
.filter-bar {
  /* Use 12 columns for more flexible layout control */
  grid-template-columns: repeat(12, 1fr); 
  gap: 10px;
  /* Align all content items to the bottom baseline for vertical alignment */
  align-items: flex-end; 
}
.filter-bar .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
/* Assign column spans for desktop (Search, Order) */
/* Search field (1st child) */
.filter-bar .field:nth-child(1) { grid-column: span 8; } 
/* Order field (2nd child) */
.filter-bar .field:nth-child(2) { grid-column: span 2; } 

/* Container for Clear button */
.filter-bar .filter-actions {
    grid-column: span 2; /* Buttons: 2/12 columns (Remaining space) */
    display: flex;
    /* Since there is only one button, justify to start, so it stays near the Order field */
    justify-content: flex-start; 
    gap: 10px;
}
.filter-bar .btn-action { 
    min-width: 100px; 
    height: 40px; 
    /* Push button down by the label height + gap (approx 20px) for vertical alignment */
    margin-top: 20px; 
}


/* Tablet/Mobile Adjustments */
@media (max-width: 992px) {
  /* Tablet: Search full width, Order and Clear button share the next row */
  .filter-bar .field:nth-child(1) { grid-column: span 12; }
  .filter-bar .field:nth-child(2) { grid-column: span 9; } /* Order takes a larger portion */
  .filter-bar .filter-actions { 
      grid-column: span 3; /* Clear button takes smaller portion */
      justify-content: flex-end; /* Push clear button to the right */
      margin-top: 0; 
  }
  .filter-bar .btn-action {
      margin-top: 0; /* Clear manual top margin */
  }
}
@media (max-width: 720px){
  .filter-bar .field, .filter-bar .filter-actions { grid-column: span 12 !important; }
  .filter-bar .filter-actions { justify-content: center; } /* Center button when full width */
}
/* END: MODIFIED Filter Bar Styles */


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

/* --- Table Optimization for Assignments (Maximization Fix) --- */
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
    /* IMPORTANT: Remove truncation rules for ID to make it fully visible */
    white-space: normal; 
    overflow: visible; 
    text-overflow: clip; 
}
.table-scroll table th:nth-child(2), /* Faculty column */
.table-scroll table td:nth-child(2) {
    width: 160px; /* Faculty width fixed */
    text-align: left;
}
.table-scroll table th:nth-child(4), /* Role column */
.table-scroll table td:nth-child(4) {
    width: 80px; /* Role width fixed (e.g., CR, FC, RA, TW) */
    text-align: center;
    /* IMPORTANT: Remove truncation rules for Role to make it fully visible */
    white-space: normal; 
    overflow: visible; 
    text-overflow: clip; 
}
.table-scroll table th:nth-child(5), /* Date column */
.table-scroll table td:nth-child(5) {
    width: 100px; /* Date width fixed */
    text-align: center;
}
.table-scroll table th:nth-child(6), /* Actions column */
.table-scroll table td:nth-child(6) {
    width: 160px; /* Actions width fixed */
}
/* Research Title column (3rd child) is left flexible to take up maximum remaining space. */
.table-scroll table th:nth-child(3),
.table-scroll table td:nth-child(3) {
    text-align: left;
}
/* --- End of Fix --- */
</style>

<section class="panel fade-in crud-header-card">
  <div class="crud-header-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
    <div class="crud-header-text">
        <h1 style="margin: 0;">Assignments</h1>
        <p class="muted" style="margin: 4px 0 0 0;">Manage who is assigned to which research and in what role. CSV import/export below.</p>
    </div>
    <div class="crud-header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=ASSIGNMENT">Export CSV</a>
        <button class="btn-action btn-primary" id="create-assignment">+ Create Assignment</button>
    </div>
  </div>
  <form method="get" class="grid filter-bar" style="margin-top:10px; margin-bottom:10px;">
    <div class="field">
      <label>Search (faculty or research title)</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>">
    </div>
    <div class="field">
      <label>Order</label>
      <select class="input" name="sort">
        <option value="id_desc"       <?= $sort==='id_desc'?'selected':''; ?>>ID (Newest First)</option>
        <option value="id_asc"        <?= $sort==='id_asc'?'selected':''; ?>>ID (Oldest First)</option>
        <option value="date_desc"     <?= $sort==='date_desc'?'selected':''; ?>>Date Assigned (Newest)</option>
        <option value="date_asc"      <?= $sort==='date_asc'?'selected':''; ?>>Date Assigned (Oldest)</option>
        <option value="faculty_asc"   <?= $sort==='faculty_asc'?'selected':''; ?>>Faculty (A–Z)</option>
        <option value="faculty_desc"  <?= $sort==='faculty_desc'?'selected':''; ?>>Faculty (Z–A)</option>
        <option value="research_asc"  <?= $sort==='research_asc'?'selected':''; ?>>Research (A–Z)</option>
        <option value="research_desc" <?= $sort==='research_desc'?'selected':''; ?>>Research (Z–A)</option>
      </select>
    </div>
    <div class="filter-actions">
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/crud/assignment.php'); ?>">Clear</a>
    </div>
  </form>
</section>
<section class="panel" id="panel"></section>
<div class="admin-modal" id="createAssignmentModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="createAssignmentTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="createAssignmentTitle">Create New Assignment</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?=  $CSRF;?>">
      <input type="hidden" name="action" value="create">

      <div class="modal-grid">
        <div class="field">
          <label for="a_faculty">Faculty</label>
          <select class="input" id="a_faculty" name="FACULTY_ID" required>
            <option value="" disabled selected>Select Faculty</option>
            <?php foreach ($fac as $f): ?>
              <option value="<?= (int)$f['FACULTY_ID']; ?>">
                <?= htmlspecialchars($f['FACULTY_LNAME'].', '.$f['FACULTY_FNAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="a_research">Research</label>
          <select class="input" id="a_research" name="RESEARCH_ID" required>
            <option value="" disabled selected>Select Research</option>
            <?php foreach ($res as $r): ?>
              <option value="<?= (int)$r['RESEARCH_ID']; ?>"><?= htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="a_role">Role</label>
          <select class="input" id="a_role" name="ROLE_ID" required>
            <option value="" disabled selected>Select Role</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars($r['ROLE_ID'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($r['ROLE_DESCRIPTION']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="a_date">Date Assigned</label>
          <input class="input" id="a_date" type="date" name="DATE_ASSIGNED" required>
        </div>
      </div>
      <div class="admin-modal__actions">
        <button class="btn wide btn-primary" type="submit">Create Assignment</button>
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
      </div>
    </form>
  </div>
</div>
<div class="admin-modal" id="assignModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="assignModalTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="assignModalTitle">Edit Assignment</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="ASSIGNMENT_ID" id="m_id">

      <div class="modal-grid">
        <div class="field">
          <label for="m_faculty">Faculty</label>
          <select class="input" id="m_faculty" name="FACULTY_ID" required>
            <?php foreach ($fac as $f): ?>
              <option value="<?= (int)$f['FACULTY_ID']; ?>">
                <?= htmlspecialchars($f['FACULTY_LNAME'].', '.$f['FACULTY_FNAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_research">Research</label>
          <select class="input" id="m_research" name="RESEARCH_ID" required>
            <?php foreach ($res as $r): ?>
              <option value="<?= (int)$r['RESEARCH_ID']; ?>"><?= htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_role">Role</label>
          <select class="input" id="m_role" name="ROLE_ID" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars($r['ROLE_ID'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($r['ROLE_DESCRIPTION']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_date">Date Assigned</label>
          <input class="input" id="m_date" type="date" name="DATE_ASSIGNED" required>
        </div>
      </div>

      <div class="admin-modal__actions">
        <button class="btn wide btn-primary" type="submit">Save</button>
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
  const assignmentPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const sortSelect = document.querySelector('select[name="sort"]');
  let timer = null;
  
  // fetch func
  function fetchResults(page) {
    const q = queryInput.value;
    const sort = sortSelect.value;
    const url = `../api/search_assignment.php?q=${encodeURIComponent(q)}&sort=${encodeURIComponent(sort)}&page=${page}`;
    
    assignmentPanel.innerHTML = "<div class='loading'>Loading...</div>";
    
    fetch(url)
      .then(res => res.text())
      .then(html => {
        assignmentPanel.innerHTML = html;
        attachPaginationEvents();
        attachEditButtons(); 
      })
      .catch(err => {
        assignmentPanel.innerHTML = "<div class='error'>Failed to load results.</div>";
        console.error("Error:", err);
      });
  }
  
  // debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  }
  
  // Note: The form is still submitted when Enter is pressed in the search box, 
  // or when the Clear button is clicked, which also triggers a refresh/filter.
  queryInput.addEventListener('input', handleLiveInput);
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
  //Create Modal
  const createAssignmentModal = document.getElementById('createAssignmentModal');
  const a_faculty = document.getElementById('a_faculty');
  const a_research = document.getElementById('a_research');
  const a_role = document.getElementById('a_role');
  const a_date = document.getElementById('a_date');
  
  function openAssignmentModal() {
    // Reset selections to the default 'Select X' options 
    a_faculty.value = ''; 
    a_research.value = ''; 
    a_role.value = '';
    a_date.value = '';
  
    createAssignmentModal.hidden = false; 
  }

  function closeAssignmentModal() { 
    createAssignmentModal.hidden = true; 
  }
  
  document.getElementById('create-assignment').addEventListener('click', function() {
    openAssignmentModal();  
  });
      createAssignmentModal.addEventListener('click', e => {
    if (e.target.dataset.close) closeAssignmentModal();
  });
  
  window.addEventListener('keydown', e => {
    if (!createAssignmentModal.hidden && e.key === 'Escape') closeAssignmentModal();
  });
  // Edit Modal 
  const modal = document.getElementById('assignModal');
  const form  = modal.querySelector('form');
  const idI   = document.getElementById('m_id');
  const facI  = document.getElementById('m_faculty');
  const resI  = document.getElementById('m_research');
  const roleI = document.getElementById('m_role');
  const dateI = document.getElementById('m_date');

  function open(payload){
    idI.value   = payload.id;
    facI.value  = payload.faculty || '';
    resI.value  = payload.research || '';
    roleI.value = payload.role || '';
    dateI.value = payload.date || '';
    modal.hidden = false;
  }
  function close(){ modal.hidden = true; }

  function attachEditButtons() {
    const editButtons = document.querySelectorAll('.js-edit');
    
    editButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        open({
          id: this.dataset.id,
          faculty: this.dataset.faculty,
          research: this.dataset.research,
          role: this.dataset.role,
          date: this.dataset.date
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