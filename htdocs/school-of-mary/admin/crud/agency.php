<?php
$pageTitle = 'Agencies | Admin';

require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

$flashError = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); // Clear the session variable immediately after retrieval

if (!is_admin()) { redirect_to('/admin/login.php'); }
// Note: csrf_check is moved inside the POST conditional for robustness,
// but is kept here based on your original code structure.
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

/* ------------------------- Actions ------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_varchar($_POST['AGENCY_NAME'] ?? '', 255)) guardFail('Invalid name');
  if (!v_enum_exists($pdo, 'TYPE_AGENCY', 'TYPE_CODE', $_POST['AGENCY_TYPE'] ?? '')) guardFail('Invalid type');
  // Changed to v_email for server-side validation based on your request.
  if (!v_email($_POST['AGENCY_CONTACTINFO'] ?? '')) guardFail('Invalid contact email address');
  // If you must use v_varchar, it should be:
  // if (!v_varchar($_POST['AGENCY_CONTACTINFO'] ?? '', 100)) guardFail('Invalid contact');

  $pdo->prepare("INSERT INTO AGENCY (AGENCY_NAME, AGENCY_TYPE, AGENCY_CONTACTINFO) VALUES (?,?,?)")
      ->execute([$_POST['AGENCY_NAME'], $_POST['AGENCY_TYPE'], $_POST['AGENCY_CONTACTINFO']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'AGENCY']);

  redirect_to('/admin/crud/agency.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing ID');
  if (!v_varchar($_POST['AGENCY_NAME'] ?? '', 255)) guardFail('Invalid name');
  if (!v_enum_exists($pdo, 'TYPE_AGENCY', 'TYPE_CODE', $_POST['AGENCY_TYPE'] ?? '')) guardFail('Invalid type');
  // Changed to v_email for server-side validation based on your request.
  if (!v_email($_POST['AGENCY_CONTACTINFO'] ?? '')) guardFail('Invalid contact email address');
  // If you must use v_varchar, it should be:
  // if (!v_varchar($_POST['AGENCY_CONTACTINFO'] ?? '', 100)) guardFail('Invalid contact');


  $pdo->prepare("UPDATE AGENCY SET AGENCY_NAME=?, AGENCY_TYPE=?, AGENCY_CONTACTINFO=? WHERE AGENCY_ID=?")
      ->execute([$_POST['AGENCY_NAME'], $_POST['AGENCY_TYPE'], $_POST['AGENCY_CONTACTINFO'], $_POST['AGENCY_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'AGENCY', $_POST['AGENCY_ID']]);

  redirect_to('/admin/crud/agency.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing ID');
  try {
    $pdo->prepare("DELETE FROM AGENCY WHERE AGENCY_ID=?")->execute([$_POST['AGENCY_ID']]);

    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'AGENCY', $_POST['AGENCY_ID']]);
      redirect_to('/admin/crud/agency.php?ok=1');
  } catch (PDOException $e) {
    $errorMessage = "Cannot delete agency. This record is referenced by other data (i.e. funding). Please delete dependent records first.";    
    $_SESSION['error_message'] = $errorMessage;
    
    redirect_to('/admin/crud/agency.php');
  }  
}

/* ------------------------- Lookups ------------------------- */
$types = $pdo->query("SELECT TYPE_CODE, TYPE_LABEL FROM TYPE_AGENCY ORDER BY TYPE_LABEL")->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------- Filters + Sorting + Pagination ------------------------- */
$q      = trim($_GET['q'] ?? '');
$type   = trim($_GET['type'] ?? '');
$sort   = $_GET['sort'] ?? 'name_asc';      // name_asc|name_desc|id_asc|id_desc|recent_desc
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 5;
$offset = ($page - 1) * $per;

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
    width: min(100%, 170px); 
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
.modal-grid{
  display:grid; grid-template-columns: 1fr 320px 1fr; gap:16px;
}
@media (max-width: 900px){ .modal-grid{ grid-template-columns: 1fr; } }
.modal-grid .field{display:flex; flex-direction:column; gap:6px;}
.modal-grid .input, .modal-grid select{width:100%; padding:12px 14px; font-size:16px;}
.admin-modal__actions{display:flex; gap:10px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eef2f6;}
.btn.wide { min-width: 160px; }
/* Action buttons in the filter row */
  .btn-action{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:130px;height:40px;padding:0 16px; /* Increased min-width for consistency */
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
  /* Removed #create-agency specific styling that broke the float */

/* ======================================= ADMIN CRUD PAGES (FIXED ALIGNMENT) ======================================= */

/* FIX 1: Ensure the entire table data cell aligns content to the top
   when a neighboring cell forces the row height to increase. */
td:has(.actions-cell) {
    vertical-align: top; 
}
.actions-cell {
  /* FIX 2: Align flex items to the top (start) of the container */
  display: flex;
  align-items: flex-start; 
  flex-wrap: nowrap;
  gap: 8px 10px;          
  white-space: nowrap;    
  min-width: 160px;      
}
.actions-cell .input,
.actions-cell select {
  max-width: 200px;
}
.actions-cell form { display: inline-flex; gap: 6px; align-items: center; }
.actions-cell .btn.small { padding: 6px 10px; }
@media (max-width: 1100px) {
  .actions-cell select[style*="width:200px"] { width: 160px !important; }
  .actions-cell select[style*="width:140px"] { width: 120px !important; }
}

/* --- Agency Table Specific Fixes --- */
.table-scroll table {
    table-layout: fixed; /* Use auto layout to allow column flexibility */
    width: 100%;
}
.table-scroll table td { 
    height: 50px;
    white-space: nowrap;
    overflow: hidden; 
    text-overflow: ellipsis; 
}
.table-scroll table th:nth-child(1), /* ID */
.table-scroll table td:nth-child(1) {
    width: 60px; /* Fixed width for ID */
    min-width: 60px;
}
.table-scroll table th:nth-child(2),
.table-scroll table td:nth-child(2) {
    width: 550px;
    min-width: 550px;
}
.table-scroll table th:nth-child(3), /* Type */
.table-scroll table td:nth-child(3) {
    width: 120px; /* Fixed width for Type (e.g., Government) */
    min-width: 120px;
}

.table-scroll table th:nth-child(4), /* Contact (Email) */
.table-scroll table td:nth-child(4) {
    /* Set a minimum width for the email address to reduce truncation */
    min-width: 250px; 
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table-scroll table th:nth-child(5), /* Actions */
.table-scroll table td:nth-child(5) {
    /* Set max-width for Actions to prevent it from taking too much space */
    width: 180px;
    min-width: 180px;
}
.btn svg {
    vertical-align: middle;
    margin-right: 2px; 
    margin-bottom: 2px; 
} 
</style>

<section class="panel fade-in crud-header-card">
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; float:inline-end">
    <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=AGENCY">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3v10" />
        <path d="M8 7l4-4 4 4" />
        <path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
      </svg>
      <span style="margin-left:5px; font-size: 0.8rem;">Export CSV</span>
    </a>
    <button class="btn-action btn-primary" id="create-agency" style="font-size:0.8rem">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
      </svg> 
      Create Agency
    </button>
  </div>
  <h1 style="margin-bottom:8px;">Agencies</h1>
  <p class="muted" style="margin-bottom:10px;">Manage agencies and their types.</p>
  
  <form method="get" class="filterbar" style="margin-bottom:10px;">
    <div class="searchbox">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
      </svg>
      <input class="input" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search agency..." style="width:70%" />
      <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button" >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
      </button>
    </div>
    <div id="filter-dropdown">
      <div id="filter-options">
        <div class="field">
          <label>Type</label>
          <select class="input" name="type">
            <option value="">All</option>
            <?php foreach ($types as $t):
              $sel = ($type === $t['TYPE_CODE']) ? ' selected' : '';
              echo '<option'.$sel.' value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>';
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
    <div class="field" id="sort-dropdown">
      <fieldset>
        <legend>Sort by:</legend>
        <div>
          <input type="radio" name="sort" value="name_asc" <?= $sort==='name_asc'?'checked':''; ?>>
          <label>Name (A–Z)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="name_desc" <?= $sort==='name_desc'?'checked':''; ?>>
          <label>Name (Z–A)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="id_asc" <?= $sort==='id_asc'?'checked':''; ?>>
          <label>ID (Oldest)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="id_desc" <?= $sort==='id_desc'?'checked':''; ?>>
          <label>ID (Newest)</label>
        </div>
      </fieldset>
    </div>
  </form>
</section>

<section class="panel" id="panel"></section>
<div class="admin-modal" id="createAgencyModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="createAgencyTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="createAgencyTitle">Create New Agency</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="action" value="create">

      <div class="modal-grid">
        <div class="field">
          <label for="a_name">Agency Name</label>
          <input class="input" id="a_name" name="AGENCY_NAME" required>
        </div>
        <div class="field">
          <label for="a_type">Type</label>
          <select class="input" id="a_type" name="AGENCY_TYPE" required>
            <?php foreach ($types as $t): ?>
              <option value="<?= $t['TYPE_CODE']; ?>"><?= htmlspecialchars($t['TYPE_LABEL']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="a_contact">Contact Email</label>
          <input class="input" id="a_contact" name="AGENCY_CONTACTINFO" required>
        </div>
      </div>

      <div class="admin-modal__actions">
        <button class="btn wide" type="submit">Create Agency</button>
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
      </div>
    </form>
  </div>
</div>
<div class="admin-modal" id="agencyModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="agencyModalTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="agencyModalTitle">Edit Agency</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="AGENCY_ID" id="m_id">

      <div class="modal-grid">
        <div class="field">
          <label for="m_name">Name</label>
          <input class="input" id="m_name" name="AGENCY_NAME" required maxlength="255">
        </div>
        <div class="field">
          <label for="m_type">Type</label>
          <select class="input" id="m_type" name="AGENCY_TYPE" required>
            <?php foreach ($types as $t): ?>
              <option value="<?= $t['TYPE_CODE']; ?>"><?= htmlspecialchars($t['TYPE_LABEL']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_contact">Contact (Email)</label>
          <input class="input" id="m_contact" type="email" name="AGENCY_CONTACTINFO" required maxlength="100">
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
  const agencyPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const typeSelect = document.querySelector('select[name="type"]');
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
      typeSelect.value = '';
      
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
    const type = typeSelect.value;
    const sort = getSelectedSort();
    const url = `../api/search_agency.php?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}&sort=${encodeURIComponent(sort)}&page=${page}`;
    
    agencyPanel.innerHTML = "<div class='loading'>Loading...</div>";
    
    fetch(url)
      .then(res => res.text())
      .then(html => {
        agencyPanel.innerHTML = html;
        attachPaginationEvents();
        attachEditButtons(); 
      })
      .catch(err => {
        agencyPanel.innerHTML = "<div class='error'>Failed to load results.</div>";
        console.error("Error:", err);
      });
  }
  
  // debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  }
  
  queryInput.addEventListener('input', handleLiveInput);
  typeSelect.addEventListener('change', () => fetchResults(1));
  
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
  //Create Agency
  const createAgencyModal = document.getElementById('createAgencyModal');
  //const createAgencyForm = createAgencyModal.querySelector('form');
  const a_name = document.getElementById('a_name');
  const a_type = document.getElementById('a_type');
  const a_contact = document.getElementById('a_contact');

  function openAgencyModal() {
    a_name.value = '';
    a_type.value = '';
    a_contact.value = '';
  
    createAgencyModal.hidden = false; 
  }

  function closeAgencyModal() { 
    createAgencyModal.hidden = true; 
  }
  
  document.getElementById('create-agency').addEventListener('click', function() {
    openAgencyModal();  
  });
      createAgencyModal.addEventListener('click', e => {
    if (e.target.dataset.close) closeAgencyModal();
  });
  
  window.addEventListener('keydown', e => {
    if (!createAgencyModal.hidden && e.key === 'Escape') closeAgencyModal();
  });
  // Modal controller
  const modal = document.getElementById('agencyModal');
  //const form = modal.querySelector('form');
  const id = document.getElementById('m_id');
  const nameI = document.getElementById('m_name');
  const typeI = document.getElementById('m_type');
  const contI = document.getElementById('m_contact'); // Contact field

  function openModal(payload) {
    id.value = payload.id;
    nameI.value = payload.name || '';
    typeI.value = payload.type || '';
    contI.value = payload.contact || '';

    // Clear any previous error states for the contact field
    contI.classList.remove('input-error');

    modal.hidden = false;
  }
  function closeModal() { modal.hidden = true; }

  function attachEditButtons() {
    const editButtons = document.querySelectorAll('.js-edit');
    
    editButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        openModal({
          id: this.dataset.id,
          name: this.dataset.name,
          type: this.dataset.type,
          contact: this.dataset.contact
        });
      });
    });
  }

  modal.addEventListener('click', e => {
    if (e.target.dataset.close) closeModal();
  });
  
  window.addEventListener('keydown', e => {
    if (!modal.hidden && e.key === 'Escape') closeModal();
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
  // Initial load
  fetchResults(1);
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>