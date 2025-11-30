<?php
$pageTitle = 'Faculty (Admin)';

require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

$flashError = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); // Clear the session variable immediately after retrieval

if (!is_admin()) { redirect_to('/admin/login.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

/* ------------------------- Actions ------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_varchar($_POST['FACULTY_FNAME'] ?? '', 50)) guardFail('Invalid first name');
  if (!v_char_nullable($_POST['FACULTY_INITIAL'] ?? '', 2)) guardFail('Invalid initial');
  if (!v_varchar($_POST['FACULTY_LNAME'] ?? '', 50)) guardFail('Invalid last name');
  if (!v_email($_POST['FACULTY_EMAIL'] ?? '')) guardFail('Invalid email');
  if (!v_enum_exists($pdo, '`RANK`', 'RANK_ID', $_POST['RANK_ID'] ?? null)) guardFail('Invalid rank');
  if (!v_enum_exists($pdo, 'DEPARTMENT', 'DEPT_ID', $_POST['DEPT_ID'] ?? null)) guardFail('Invalid department');

  $pdo->prepare("
    INSERT INTO FACULTY (FACULTY_FNAME,FACULTY_INITIAL,FACULTY_LNAME,FACULTY_EMAIL,RANK_ID,DEPT_ID)
    VALUES (?,?,?,?,?,?)
  ")->execute([
    $_POST['FACULTY_FNAME'], $_POST['FACULTY_INITIAL'], $_POST['FACULTY_LNAME'],
    $_POST['FACULTY_EMAIL'], $_POST['RANK_ID'], $_POST['DEPT_ID']
  ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'FACULTY']);

  redirect_to('/admin/crud/faculty.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['FACULTY_ID'] ?? '')) guardFail('Missing ID');
  if (!v_varchar($_POST['FACULTY_FNAME'] ?? '', 50)) guardFail('Invalid first name');
  if (!v_char_nullable($_POST['FACULTY_INITIAL'] ?? '', 2)) guardFail('Invalid initial');
  if (!v_varchar($_POST['FACULTY_LNAME'] ?? '', 50)) guardFail('Invalid last name');
  if (!v_email($_POST['FACULTY_EMAIL'] ?? '')) guardFail('Invalid email');
  if (!v_enum_exists($pdo, '`RANK`', 'RANK_ID', $_POST['RANK_ID'] ?? null)) guardFail('Invalid rank');
  if (!v_enum_exists($pdo, 'DEPARTMENT', 'DEPT_ID', $_POST['DEPT_ID'] ?? null)) guardFail('Invalid department');

  $pdo->prepare("
    UPDATE FACULTY
    SET FACULTY_FNAME=?, FACULTY_INITIAL=?, FACULTY_LNAME=?, FACULTY_EMAIL=?, RANK_ID=?, DEPT_ID=?
    WHERE FACULTY_ID=?
  ")->execute([
    $_POST['FACULTY_FNAME'], $_POST['FACULTY_INITIAL'], $_POST['FACULTY_LNAME'],
    $_POST['FACULTY_EMAIL'], $_POST['RANK_ID'], $_POST['DEPT_ID'], $_POST['FACULTY_ID']
  ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'FACULTY', $_POST['FACULTY_ID']]);

  redirect_to('/admin/crud/faculty.php?ok=1');
}

// ------------------------- DELETE ACTION (UPDATED for Pop-up Modal) -------------------------
if ($action === 'delete') {
  if (!v_int($_POST['FACULTY_ID'] ?? '')) guardFail('Missing ID');
  
  try {
    // Attempt the delete operation
    $pdo->prepare("DELETE FROM FACULTY WHERE FACULTY_ID=?")->execute([$_POST['FACULTY_ID']]);

    // If successful, log the action and set a success flash message
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'], 'DELETE', 'FACULTY', $_POST['FACULTY_ID']]);
    
    // Redirect on success (with ?ok=1 flag)
    redirect_to('/admin/crud/faculty.php?ok=1'); 

  } catch (PDOException $e) {
    // Catch the database error 
    $errorMessage = "Cannot delete faculty. This record is referenced by other data (i.e. assignment). Please delete dependent records first.";
    
    // Set the error message into a session variable (FLASH MESSAGE)
    $_SESSION['error_message'] = $errorMessage;
    
    // Redirect back to the page to trigger the JavaScript modal
    redirect_to('/admin/crud/faculty.php');
  }
}
// ------------------------- END DELETE ACTION -------------------------

/* ------------------------- Lookups ------------------------- */
$ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll(PDO::FETCH_ASSOC);
$depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------- Filters + Sorting + Pagination ------------------------- */
$q    = trim($_GET['q'] ?? '');
$rank = trim($_GET['rank'] ?? '');
$dept = trim($_GET['dept'] ?? '');
$sort = $_GET['sort'] ?? 'name_asc'; // name_asc|name_desc|id_asc|id_desc|email_asc|email_desc|rank_asc|dept_asc
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 5;
$offset = ($page - 1) * $per;

require_once __DIR__ . '/../../partials/site_header.php';
$CSRF = csrf_token();
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
    width: min(100%, 480px); 
    background: #fff;
    border: 1px solid #c7d2e4;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    z-index: 100;
    padding: 15px;
}
#filter-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr); 
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
    width: min(100%, 200px); 
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
.modal-grid{ display:grid; grid-template-columns: 1fr 120px 1fr 1fr; gap:16px; }
@media (max-width: 900px){ .modal-grid{ grid-template-columns: 1fr; } }
.modal-grid .field{display:flex; flex-direction:column; gap:6px;}
.modal-grid .field:nth-child(5) {grid-column: span 1;}
.modal-grid .field:nth-child(6) {grid-column: span 2;}
.modal-grid .input, .modal-grid select{width:100%; padding:8px 14px; font-size:16px;}
.admin-modal__actions{display:flex; gap:10px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eef2f6;}
.btn.wide { min-width: 160px; }

/* Filter bar action buttons */
.btn-action{
  display:inline-flex;align-items:center;justify-content:center;
  min-width:130px;height:40px;padding:0 16px;
  border-radius:8px;border:1px solid var(--color-accent);
  font-weight:600; font-size: 0.8rem;text-decoration:none;cursor:pointer;
  transition:background .2s ease,color .2s ease,transform .06s ease,box-shadow .15s ease;
}
.btn-action:active{ transform: translateY(1px); }
.btn-primary{ background: var(--color-accent); color:#fff; }
.btn-primary:hover{ filter:brightness(.94); box-shadow:0 4px 10px rgba(0,0,0,.06); }
.btn-ghost{ background:#fff; color: var(--color-accent); border-color: rgba(11,83,148,.35); }
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
    width: 50px; 
    /* IMPORTANT: Remove truncation rules for ID to make it fully visible */
    white-space: normal; 
    overflow: visible; 
    text-overflow: clip; 
}
.table-scroll table th:nth-child(6), /* Actions column */
.table-scroll table td:nth-child(6) {
    width: 200px; /* Adjust based on button size */
}
/* --- End of Fix --- */
</style>

<section class="panel fade-in crud-header-card">
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; float:inline-end">
    <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=FACULTY">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3v10" />
        <path d="M8 7l4-4 4 4" />
        <path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
      </svg>
      <span style="margin-left:5px">Export CSV</span>
    </a>
    <button class="btn-action btn-primary" id="create-faculty">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
      </svg>
      Create Faculty
    </button>
  </div>
  <h1 style="margin-bottom:8px;">Faculty</h1>
  <p class="muted" style="margin-bottom:10px;">Create, update, delete records. CSV export also available.</p>
  <form method="get" class="filterbar" style="margin-bottom:10px;">
      <div class = "searchbox">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
        </svg>
        <input class="input" type ="search" name="q" value="<?= htmlspecialchars($q); ?>" placeholder="Search using name or email...">
        <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button" >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
          </svg>
        </button>
      </div>
      
      <div id="filter-dropdown">
        <div id="filter-options">
          <div class="field">
            <label>Rank</label>
            <select class="input" name="rank">
              <option value="">All</option>
              <?php foreach ($ranks as $r):
                $sel = ($rank === $r['RANK_ID']) ? ' selected' : '';
                echo '<option'.$sel.' value="'.htmlspecialchars($r['RANK_ID'], ENT_QUOTES).'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>';
              endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Department</label>
            <select class="input" name="dept">
              <option value="">All</option>
              <?php foreach ($depts as $d):
                $sel = ($dept === $d['DEPT_ID']) ? ' selected' : '';
                echo '<option'.$sel.' value="'.htmlspecialchars($d['DEPT_ID'], ENT_QUOTES).'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>';
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
                <input type="radio" name="sort" value="name_asc"  <?= $sort==='name_asc'?'checked':''; ?>>
                <label>Name (A–Z)</label>
              </div>
              <div>
                <input type="radio" name="sort" value="name_desc" <?= $sort==='name_desc'?'checked':''; ?>>
                <label>Name (Z–A)</label><br>
              </div>
              <div>
                <input type="radio" name="sort" value="id_asc"    <?= $sort==='id_asc'?'checked':''; ?>>
                <label>ID (Oldest First)</label>
              </div>
              <div>
                <input type="radio" name="sort" value="id_desc"   <?= $sort==='id_desc'?'checked':''; ?>>
                <label>ID (Newest First)</label>
              </div>
              <div>
                <input type="radio" name="sort" value="email_asc" <?= $sort==='email_asc'?'checked':''; ?>>
                <label>Email (A–Z)</label>
              </div>
              <div>
                <input type="radio" name="sort" value="email_desc"<?= $sort==='email_desc'?'checked':''; ?>>
                <label>Email (Z–A)</label>
              </div>
              <div>
                <input type="radio" name="sort" value="rank_asc"  <?= $sort==='rank_asc'?'checked':''; ?>>
                <label>Rank</label>
              </div>
              <div>
                <input type="radio" name="sort" value="dept_asc"  <?= $sort==='dept_asc'?'checked':''; ?>>
                <label>Department</label>
              </div>
            </fieldset>
        </div>
  </form>
</section>

<section class="panel" id="panel"></section>
<div class="admin-modal" id="createFacultyModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="createFacultyTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="createFacultyTitle">Create New Faculty</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="create">

      <div class="modal-grid">
        <div class="field">
          <label for="f_first">First Name</label>
          <input class="input" id="f_first" name="FACULTY_FNAME" maxlength="50" required>
        </div>
        <div class="field">
          <label for="f_initial">Middle Initial</label>
          <input class="input" id="f_initial" name="FACULTY_INITIAL" maxlength="2" required>
        </div>
        <div class="field">
          <label for="f_last">Last Name</label>
          <input class="input" id="f_last" name="FACULTY_LNAME"  maxlength="50" required>
        </div>
        <div class="field">
          <label for="f_email">Contact Email</label>
          <input class="input" id="f_email" name="FACULTY_EMAIL" type="email" required>
        </div>
      </div>

      <div class="admin-modal__actions">
        <button class="btn wide" type="submit">Create Faculty</button>
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
      </div>
    </form>
  </div>
</div>
<div class="admin-modal" id="facultyModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="facultyModalTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="facultyModalTitle">Edit Faculty</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="FACULTY_ID" id="m_id">

      <div class="modal-grid">
        <div class="field">
          <label for="m_fname">First name</label>
          <input class="input" id="m_fname" name="FACULTY_FNAME" required maxlength="50">
        </div>
        <div class="field">
          <label for="m_initial">Initial</label>
          <input class="input" id="m_initial" name="FACULTY_INITIAL" maxlength="2">
        </div>
        <div class="field">
          <label for="m_lname">Last name</label>
          <input class="input" id="m_lname" name="FACULTY_LNAME" required maxlength="50">
        </div>
        <div class="field">
          <label for="m_email">Email</label>
          <input class="input" id="m_email" type="email" name="FACULTY_EMAIL" required maxlength="255">
        </div>
        <div class="field">
          <label for="m_rank">Rank</label>
          <select class="input" id="m_rank" name="RANK_ID" required>
            <?php foreach ($ranks as $r): ?>
              <option value="<?= htmlspecialchars($r['RANK_ID'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($r['RANK_DESCRIPTION']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_dept">Department</label>
          <select class="input" id="m_dept" name="DEPT_ID" required>
            <?php foreach ($depts as $d): ?>
              <option value="<?= htmlspecialchars($d['DEPT_ID'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($d['DEPT_SPECIALIZATION']); ?>
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
<div class="admin-modal" id="errorModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="errorModalTitle">
    <div class="admin-modal__head" style="background:rgba(185,28,28,.08); border-color:rgba(185,28,28,.2)">
      <h3 class="admin-modal__title" id="errorModalTitle" style="color:#b91c1c;">🛑 Error</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <div class="admin-modal__body">
      <p id="errorModalMessage" style="margin:0; font-size:16px;"></p>
    </div>
  </div>
</div>
<script>
  const facultyPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const rankSelect = document.querySelector('select[name="rank"]');
  const deptSelect = document.querySelector('select[name="dept"]');
  const sortRadios = document.querySelectorAll('input[name="sort"]');
  const filterDropdown = document.querySelector('#filter-dropdown');
  const filterButton = document.querySelector('#filter-btn');
  const sortDropdown = document.querySelector('#sort-dropdown');
  const sortButton = document.querySelector('#sort-btn');
  const clearFiltersButton = document.querySelector('#clear-btn');
  let timer = null;

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
      rankSelect.value = '';
      deptSelect.value = '';
      
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
    const rank = rankSelect.value;  
    const dept = deptSelect.value;
    const sort = getSelectedSort();
    const url = `../api/search_faculty.php?q=${encodeURIComponent(q)}&rank=${encodeURIComponent(rank)}&dept=${encodeURIComponent(dept)}&sort=${encodeURIComponent(sort)}&page=${page}`;
    
    facultyPanel.innerHTML = "<div class='loading'>Loading...</div>";
    
    fetch(url)
      .then(res => res.text())
      .then(html => {
        facultyPanel.innerHTML = html;
        attachPaginationEvents();
        attachEditButtons();  
      })
      .catch(err => {
        facultyPanel.innerHTML = "<div class='error'>Failed to load results.</div>";
        console.error("Error:", err);
      });
  }
  
  // debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  }
  
  queryInput.addEventListener('input', handleLiveInput);
  rankSelect.addEventListener('change', () => fetchResults(1));
  deptSelect.addEventListener('change', () => fetchResults(1));
  
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
  //Create Faculty
  const createFacultyModal = document.getElementById('createFacultyModal');
  const createFacultyForm = createFacultyModal.querySelector('form');
  const f_first = document.getElementById('f_first');
  const f_initial = document.getElementById('f_initial');
  const f_last = document.getElementById('f_last');
  const f_email = document.getElementById('f_email');

  function openFacultyModal() {
    f_first.value = '';
    f_initial.value = '';
    f_last.value = '';
    f_email.value = '';
    createFacultyModal.hidden = false;
  }
  function closeFacultyModal() {
    createFacultyModal.hidden = true;
  }
  document.getElementById('create-faculty').addEventListener('click', function() {
    openFacultyModal(); 
  });
      createFacultyModal.addEventListener('click', e => {
    if (e.target.dataset.close) closeFacultyModal();
  });
  
  window.addEventListener('keydown', e => {
    if (!createFacultyModal.hidden && e.key === 'Escape') closeFacultyModal();
  });
  // Edit Modal 
    const modal = document.getElementById('facultyModal');
    const form  = modal.querySelector('form');
    const idI   = document.getElementById('m_id');
    const fnI   = document.getElementById('m_fname');
    const inI   = document.getElementById('m_initial');
    const lnI   = document.getElementById('m_lname');
    const emI   = document.getElementById('m_email');
    const rkI   = document.getElementById('m_rank');
    const dpI   = document.getElementById('m_dept');

  function open(payload){
    idI.value = payload.id;
    fnI.value = payload.fname || '';
    inI.value = payload.initial || '';
    lnI.value = payload.lname || '';
    emI.value = payload.email || '';
    rkI.value = payload.rank || '';
    dpI.value = payload.dept || '';

    form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    form.querySelectorAll('.error-message').forEach(el => el.remove());
    
    modal.hidden = false;
  }  
  function close(){ modal.hidden = true; }
    
  function attachEditButtons() {
    const editButtons = document.querySelectorAll('.js-edit');
      
    editButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        open({
          id: this.dataset.id,
          fname: this.dataset.fname,
          initial: this.dataset.initial,
          lname: this.dataset.lname,
          email: this.dataset.email,
          rank: this.dataset.rank,
          dept: this.dataset.dept
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
    // Initial load
  fetchResults(1);

  modal.addEventListener('click', e=>{ if (e.target.dataset.close) close(); });
  window.addEventListener('keydown', e=>{ if (!modal.hidden && e.key === 'Escape') close(); });
//Error Modal
  (function(){
    const errorModal = document.getElementById('errorModal');
    const messageEl = document.getElementById('errorModalMessage');
    
    const flashError = '<?= htmlspecialchars(addslashes($flashError ?? '')); ?>'; 

    if (flashError) {
      messageEl.textContent = flashError;
      errorModal.hidden = false;
    }
    
    errorModal.addEventListener('click', e => { 
      if (e.target.dataset.close) {
        errorModal.hidden = true; 
      }
    });

    window.addEventListener('keydown', e => { 
      if (!errorModal.hidden && e.key === 'Escape') { 
        errorModal.hidden = true; 
      }
    });
  })();
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>

