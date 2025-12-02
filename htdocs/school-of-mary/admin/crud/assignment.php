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
$role   = trim($_GET['role'] ?? '');
$sort   = $_GET['sort'] ?? 'id_desc'; // id_desc|id_asc|date_desc|date_asc|faculty_asc|faculty_desc|research_asc|research_desc
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 5;
$offset = ($page - 1) * $per;
$CSRF = csrf_token();
require_once __DIR__ . '/../../partials/site_header.php';
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
    width: min(100%, 240px); 
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 15px;
}
#filter-options {
    display: grid;
    grid-template-columns: repeat(1, 1fr); 
    gap: 10px;
    margin-bottom: 15px;
}
/* CLEAR BUTTON */
.clear-btn-container {
    text-align: left;
}
.clear-btn-container .btn-primary {
    background-color: #0b5394;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    width: 100%;
}
.clear-btn-container .btn-primary:hover {
    background-color: #0b5394;
}
/* SORT BUTTON */
.sort-toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: 1px solid #c7d2e4;
  border-radius: 8px;
  cursor: pointer;
  color: #4b5563;
  padding: 0;
  width: 40px; 
  height: 57px; 
  flex-shrink: 0; 
}
.sort-toggle-btn:hover {
  color: #1f2937;
  background: rgba(0, 0, 0, 0.05);
  border-radius: 6px;
}
#sort-dropdown {
    display: none; 
    position: absolute;
    top: 100%; 
    right: 0;
    margin-top: 8px;
    width: min(100%, 220px); 
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 15px;
}
#sort-dropdown fieldset {
  border: none;
  padding: 0;
  margin: 0;
}
#sort-dropdown fieldset legend {
  font-weight: 600;
  margin-bottom: 10px;
  color: #4b5563;
}
#sort-dropdown fieldset > div {
  display: flex;
  align-items: center;  
  gap: 8px;
  padding: 6px 0;
}
#sort-dropdown fieldset > div input[type="radio"] {
  margin: 0;
  cursor: pointer;
  -ms-transform: scale(1.5); /* make button larger */
  -webkit-transform: scale(1.5); 
  transform: scale(1.5);
}
#sort-dropdown fieldset > div label {
  cursor: pointer;
  margin: 0;
}
/* VIEW,EDIT,DELETE */
.btn-view{
  background: (--color-accent);
  border-color: (--color-accent);
}
.btn-edit {
  background: #64748b;
  border-color: #64748b;
  color:white;
}
.btn-edit:hover {
  background: #5b6878ff;
  border-color: 5b6878ff;
}
.btn-delete {
  background: #dc2626;
  border-color: #dc2626;
  color: white;
}
.btn-delete:hover {
  background: rgba(175, 35, 35, 1)
}

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
.modal-grid .input, .modal-grid select{width:100%; padding:10px 14px; font-size:16px;}
.admin-modal__actions{display:flex; gap:10px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eef2f6;}
.btn.wide { min-width: 160px; }

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

/* --- TABLE --- */
.table-scroll table {
    table-layout: fixed; 
    width: 100%;
    scrollbar-width: none; /*hide scrollbar*/
    -ms-overflow-style: none; /*hide scrollbar*/
}
.table-scroll::-webkit-scrollbar {
  display: none; /*hide scrollbar*/
}
.table-scroll table td { 
    height: 50px;
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
    width: 180px;
    text-align: left;
    /* IMPORTANT: Remove truncation rules for Role to make it fully visible */
    white-space: normal; 
    overflow: visible; 
    text-overflow: clip; 
}
.table-scroll table th:nth-child(5), /* Date column */
.table-scroll table td:nth-child(5) {
    width: 120px; /* Date width fixed */
    text-align: center;
}
.table-scroll table th:nth-child(6), /* Actions column */
.table-scroll table td:nth-child(6) {
    width: 180px; /* Actions width fixed */
}
/* Research Title column (3rd child) is left flexible to take up maximum remaining space. */
.table-scroll table th:nth-child(3),
.table-scroll table td:nth-child(3) {
    text-align: left;
}
.table-clickable tbody tr:hover{background: #c7d2e4;}
.btn svg {
    vertical-align: middle;
    margin-right: 2px; 
    margin-bottom: 2px; 
} 
/* --- End of Fix --- */
</style>

<section class="panel fade-in crud-header-card">
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; float:inline-end">
    <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=ASSIGNMENT">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3v10" />
        <path d="M8 7l4-4 4 4" />
        <path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
      </svg>
      <span style="margin-left:5px; font-size: 0.8rem;">Export CSV</span>
    </a>
    <button class="btn-action btn-primary" id="create-assignment" style="font-size:0.8rem">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
      </svg> 
      Create Assignment
    </button>
  </div>
  <h1 style="margin: 0;">Assignments</h1>
  <p class="muted" style="margin: 4px 0 0 0;">Manage who is assigned to which research and in what role. CSV import/export below.</p>
  <form method="get" class="filterbar" style="margin-top:10px; margin-bottom:10px;">
    <div class="searchbox">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
      </svg>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>" placeholder="Search using faculty or research title...">
      <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button" >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
      </button>
    </div>
    <div id="filter-dropdown">
      <div id="filter-options">
        <div class="field">
            <label>Role</label>
            <select class="input" name="role">
              <option value="">All</option>
              <?php foreach ($roles as $r):
                $sel = ($role === $r['ROLE_ID']) ? ' selected' : '';
                echo '<option'.$sel.' value="'.htmlspecialchars($r['ROLE_ID'], ENT_QUOTES).'">'.htmlspecialchars($r['ROLE_DESCRIPTION']).'</option>';
              endforeach; ?>
            </select>
          </div>
      </div>
      <div class="clear-btn-container">
        <button class="btn-primary" id="clear-btn" type="button" >Clear Filters</button>
      </div>
    </div>
    <button class="sort-toggle-btn" id="sort-btn" title="Sort" type="button">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrows-sort"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 9l4 -4l4 4m-4 -4v14" /><path d="M21 15l-4 4l-4 -4m4 4v-14" /></svg>
    </button>
    
    <div class="field" id="sort-dropdown" onchange="closeSort()">
      <fieldset>
        <legend>Sort by:</legend>
        <div>
          <input type="radio" name="sort" value="id_desc" <?= $sort==='id_desc'?'checked':''; ?>>
          <label>ID (Newest First)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="id_asc" <?= $sort==='id_asc'?'checked':''; ?>>
          <label>ID (Oldest First)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="date_desc" <?= $sort==='date_desc'?'checked':''; ?>>
          <label>Date Assigned (Newest)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="date_asc" <?= $sort==='date_asc'?'checked':''; ?>>
          <label>Date Assigned (Oldest)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="faculty_asc" <?= $sort==='faculty_asc'?'checked':''; ?>>
          <label>Faculty (A–Z)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="faculty_desc" <?= $sort==='faculty_desc'?'checked':''; ?>>
          <label>Faculty (Z–A)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="research_asc" <?= $sort==='research_asc'?'checked':''; ?>>
          <label>Research (A–Z)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="research_desc" <?= $sort==='research_desc'?'checked':''; ?>>
          <label>Research (Z–A)</label>
        </div>
      </fieldset>
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
  const roleSelect = document.querySelector('select[name="role"]');
  const sortRadios = document.querySelectorAll('input[name="sort"]');
  const filterDropdown = document.querySelector('#filter-dropdown');
  const filterButton = document.querySelector('#filter-btn');
  const sortDropdown = document.querySelector('#sort-dropdown');
  const sortButton = document.querySelector('#sort-btn');
  const clearFiltersButton = document.querySelector('#clear-btn');
  let timer = null;
  
  function attachTableRowEvents() {
    const tableRows = document.querySelectorAll(".table-clickable tbody tr");
    
    tableRows.forEach(row => {
      row.addEventListener("click", function(e) {
        if (e.target.closest('.actions-cell')) {
          return;
        }
        
        const href = this.dataset.href;
        if (href) {
          window.location.href = href;
        }
      });
    });
  }
  // Toggle visibility of the filter dropdown
  function toggleFilters(e) {
      if (e) e.preventDefault();
      e.stopPropagation();
      sortDropdown.style.display = "none";
      if (filterDropdown.style.display === "none" || filterDropdown.style.display === "") {
        filterDropdown.style.display = "block";
      } else {
        filterDropdown.style.display = "none";
      }
  }
  function toggleSort(e) {
      if (e) e.preventDefault();
      e.stopPropagation();
      filterDropdown.style.display = "none";
      if (sortDropdown.style.display === "none" || sortDropdown.style.display === "") {
        sortDropdown.style.display = "block";
      } else {
        sortDropdown.style.display = "none";
      }
  }
  document.addEventListener('click', function(e) {
  if (!filterDropdown.contains(e.target) && e.target !== filterButton) {
    filterDropdown.style.display = "none";
  }
  });
  document.addEventListener('click', function(e) {
  if (!sortDropdown.contains(e.target) && e.target !== sortButton) {
    sortDropdown.style.display = "none";
  }
  });
  function closeSort() {
    sortDropdown.style.display = "none";
  }
  // Clear button function
  function clearFilters(e) {
      if (e) e.preventDefault();
      // Reset inputs
      roleSelect.value = '';
      
      // Fetch results to show the unfiltered list
      fetchResults(1);

      // Hide the filter panel
      filterDropdown.style.display = "none";
  }

  function getSelectedSort() {
    const checkedRadio = document.querySelector('input[name="sort"]:checked');
    return checkedRadio ? checkedRadio.value : 'name_asc'; 
  }
  
  sortRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      fetchResults(1);
      closeSort(); 
    });
  });
  clearFiltersButton.addEventListener('click', clearFilters);
  filterButton.addEventListener('click', toggleFilters);
  sortButton.addEventListener('click', toggleSort);

  // fetch func
  function fetchResults(page) {
    const q = queryInput.value;
    const role = roleSelect.value;
    const sort = getSelectedSort();
    const url = `../api/search_assignment.php?q=${encodeURIComponent(q)}&role=${encodeURIComponent(role)}&sort=${encodeURIComponent(sort)}&page=${page}`;
    assignmentPanel.innerHTML = "<div class='loading'>Loading...</div>";
    fetch(url)
      .then(res => res.text())
      .then(html => {
        assignmentPanel.innerHTML = html;
        attachPaginationEvents();
        attachEditButtons(); 
        attachTableRowEvents();
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
  roleSelect.addEventListener('change', () => fetchResults(1));
  
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