<?php
$pageTitle = 'Research | Admin';

// Core
require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

$flashError = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); // Clear the session variable immediately after retrieval

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
  try {
    $pdo->prepare("DELETE FROM RESEARCH WHERE RESEARCH_ID=?")->execute([$_POST['RESEARCH_ID']]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'RESEARCH', $_POST['RESEARCH_ID']]);
      redirect_to('/admin/crud/research.php?ok=1');
  } catch (PDOException $e) {
    $errorMessage = "Cannot delete research. This record is referenced by other data (i.e. assignment and funding). Please delete dependent records first.";    
    $_SESSION['error_message'] = $errorMessage;
    
    redirect_to('/admin/crud/research.php');
  }
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
    font-family: 'Newsreader', serif;
}

/* --- SEARCH BAR AND TOGGLE STYLES (Full Width, Matching Look) --- */
.searchbox {
  font-family: 'Newsreader', serif;
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
    font-family: 'Newsreader', serif;
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
    grid-template-columns: repeat(3, 1fr); 
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
/* ------------------------------------------- */
/* END: NEW HEADER AND FILTER BAR STYLES */
/* ------------------------------------------- */

/* TABLE */
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
table th:first-child,
table td:first-child {
  width: 60px;
  min-width: 60px;
  max-width: 60px;
}
table th:nth-child(2),
table td:nth-child(2) {
  width: 625px;
  min-width: 300px;
  max-width: 625px;
}
table th:nth-child(3),table td:nth-child(3){
  width: 180px; 
  max-width: 180px;
}
table th:nth-child(4),table td:nth-child(4) {
  width: 120px; 
  max-width: 120px;
}
table th:nth-child(5),table td:nth-child(5){
  width: 200px; 
  max-width: 200px;
}

.table-clickable tbody tr:hover{background: #c7d2e4;}
.btn svg {
    vertical-align: middle;
    margin-right: 2px; 
    margin-bottom: 2px; 
} 
/* Action buttons parity */
.btn-action{
  font-size: 0.8rem; 
  display:inline-flex;align-items:center;justify-content:center;
  min-width:130px;height:40px;padding:0 16px;
  border-radius:8px;border:1px solid var(--color-accent);
  font-weight:600;text-decoration:none;cursor:pointer; 
  transition:background .2s ease,color .2s ease,transform .06s ease,box-shadow .15s ease;
}
.btn-ghost{
  font-size: 0.8rem; 
  background:#fff;
  color: var(--color-accent);
  border-color: rgba(11,83,148,.35);
}
.btn-action:active{ transform: translateY(1px); }
.btn-primary{ background: var(--color-accent); color:#fff;  }
.btn-primary:hover{ filter:brightness(.94); box-shadow:0 4px 10px rgba(0,0,0,.06); }
.btn-ghost:hover{ background: rgba(11,83,148,.05); }
</style>

<section class="panel fade-in crud-header-card">
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; float:inline-end">
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=RESEARCH">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3v10" />
          <path d="M8 7l4-4 4 4" />
          <path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
        </svg>
        <span style="margin-left:5px; font-size: 0.8rem">Export CSV</span>
      </a>
      <button class="btn-action btn-primary" id="create-research" style="font-size:0.8rem; font-family: 'Newsreader', serif;">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
        </svg>
        Create Research
      </button>
  </div>
  <h1>Research</h1>
  <p class="muted">Manage research project directory by tracking their details, status, and timelines.</p>
  
  <form method="get" class="filterbar" style="margin-top:10px;">
    <div class="searchbox" style="font-family: 'Newsreader', serif;">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
      </svg>
      <input class="input" type="search" name="q" value="<?= htmlspecialchars($q); ?>" placeholder="Search research project" style="width:70%; font-family: 'Newsreader', serif;"/>
      <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
      </button>
    </div>
    <div id ="filter-dropdown">
      <div id="filter-options">
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
          <input class="input" type="date" name="to" value="<?= htmlspecialchars($to); ?>">
        </div>
      </div>
      <div class="clear-btn-container">
        <button class="btn-primary" id="clear-btn" type="button" style="font-size:0.8rem; font-family: 'Newsreader', serif;">Clear Filters</button>
      </div>
    </div>
    <button class="sort-toggle-btn" id="sort-btn" title="Sort" type="button">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrows-sort"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 9l4 -4l4 4m-4 -4v14" /><path d="M21 15l-4 4l-4 -4m4 4v-14" /></svg>
        </button>
    <div class="field" id="sort-dropdown" onchange="closeSort()">
      <fieldset>
        <legend>Sort by:</legend>
        <div>
          <input type="radio" name="sort"  value="start_desc" <?= $sort==='start_desc'?'checked':''; ?>>
          <label>Start Date (Newest)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="start_asc"  <?= $sort==='start_asc'?'checked':''; ?>>
          <label>Start Date (Oldest)</label><br>
        </div>
        <div>
          <input type="radio" name="sort" value="end_desc"   <?= $sort==='end_desc'?'checked':''; ?>>
          <label>End Date (Newest)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="end_asc"    <?= $sort==='end_asc'?'checked':''; ?>>
          <label>End Date (Oldest)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="title_asc"  <?= $sort==='title_asc'?'checked':''; ?>>
          <label>Title (A–Z)</label>
        </div>   
        <div>
          <input type="radio" name="sort" value="title_desc"  <?= $sort==='title_desc'?'checked':''; ?>>
          <label>Title (Z–A)</label>
        </div> 
        <div>
          <input type="radio" name="sort" value="status_asc" <?= $sort==='status_asc'?'checked':''; ?>>
          <label>Status (A–Z)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="id_desc"    <?= $sort==='id_desc'?'checked':''; ?>>
          <label>ID (Newest)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="id_asc"     <?= $sort==='id_asc'?'checked':''; ?>>
          <label>ID (Oldest)</label>
        </div>
      </fieldset>
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
          <label for="m_status">Status</label>
          <select class="input" id="m_status" name="RESEARCH_STATUS" required>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= htmlspecialchars($s['STATUS_CODE'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($s['STATUS_LABEL']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_start">Start</label>
          <input class="input" id="m_start" type="date" name="RESEARCH_STARTDATE" required>
        </div>
        <div class="field">
          <label for="m_end">End</label>
          <input class="input" id="m_end" type="date" name="RESEARCH_ENDDATE">
        </div>
      </div>

      <div class="admin-modal__actions">
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
        <button class="btn wide btn-primary" type="submit">Save</button>
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
  const researchPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const statusSelect = document.querySelector('select[name="status"]');
  const fromInput = document.querySelector('input[name="from"]');
  const toInput = document.querySelector('input[name="to"]');
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
      queryInput.value = '';
      statusSelect.value = '';
      fromInput.value = '';
      toInput.value = '';
      
      // Fetch results to show the unfiltered list
      fetchResults(1);

      // Hide the filter panel
      filterDropdown.style.display = "none";
  }

  function getSelectedSort() {
    const checkedRadio = document.querySelector('input[name="sort"]:checked');
    return checkedRadio ? checkedRadio.value : 'start_desc'; 
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
  form.addEventListener('submit', function(e) {
    validateDates('m_start', 'm_end', e);
  })
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
    const sort = getSelectedSort();
    const isOngoing = status === 'ONGOING';
    const url = `../api/search_research.php?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&sort=${encodeURIComponent(sort)}&page=${page}`;    
    
    researchPanel.innerHTML = "<div class='loading'>Loading...</div>";
    
    fetch(url)
      .then(res => res.text())
      .then(html => {
        if(isOngoing) {
          toInput.disabled = true;
          toInput.style.cursor = 'not-allowed';
        } else {
          toInput.disabled = false;
          toInput.style.cursor = 'initial';
        }
        researchPanel.innerHTML = html;
        attachPaginationEvents();
        attachEditButtons(); 
        attachTableRowEvents();
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