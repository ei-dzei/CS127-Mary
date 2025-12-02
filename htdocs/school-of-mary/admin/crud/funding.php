<?php
$pageTitle = 'Funding (Admin)';

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
if (!function_exists('v_decimal_nullable')) {
  function v_decimal_nullable($s): bool {
    if ($s === null || $s === '') return true;
    if (is_int($s) || is_float($s)) return true;
    if (!is_string($s)) return false;
    return (bool)preg_match('/^-?\d+(\.\d{1,2})?$/', trim($s));
  }
}

// Auth & CSRF
if (!is_admin()) { redirect_to('/admin/login.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

/* Actions */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_int($_POST['RESEARCH_ID'] ?? '') || !v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_decimal_nullable($_POST['FUNDING_AMOUNT'] ?? '')) guardFail('Invalid amount format');
  
  // *** NEW VALIDATION: Check for negative amount ***
  $amount = $_POST['FUNDING_AMOUNT'] ?? '';
  if ($amount !== '' && (float)$amount < 0) {
    guardFail('Funding amount cannot be negative');
  }
  
  if (!v_date_nullable($_POST['DATE_FUNDED'] ?? '')) guardFail('Invalid date');

  $sql = "INSERT INTO FUNDING (RESEARCH_ID, AGENCY_ID, FUNDING_AMOUNT, DATE_FUNDED) VALUES (?,?,?,?)";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'],
    $_POST['AGENCY_ID'],
    $amount !== '' ? $amount : null,
    ($_POST['DATE_FUNDED'] ?? '')   !== '' ? $_POST['DATE_FUNDED']   : null
  ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'FUNDING']);

  redirect_to('/admin/crud/funding.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['FUNDING_ID'] ?? '')) guardFail('Missing ID');
  if (!v_int($_POST['RESEARCH_ID'] ?? '') || !v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_decimal_nullable($_POST['FUNDING_AMOUNT'] ?? '')) guardFail('Invalid amount format');
  
  // *** NEW VALIDATION: Check for negative amount ***
  $amount = $_POST['FUNDING_AMOUNT'] ?? '';
  if ($amount !== '' && (float)$amount < 0) {
    guardFail('Funding amount cannot be negative');
  }

  if (!v_date_nullable($_POST['DATE_FUNDED'] ?? '')) guardFail('Invalid date');

  $sql = "UPDATE FUNDING
          SET RESEARCH_ID=?, AGENCY_ID=?, FUNDING_AMOUNT=?, DATE_FUNDED=?
          WHERE FUNDING_ID=?";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'],
    $_POST['AGENCY_ID'],
    $amount !== '' ? $amount : null,
    ($_POST['DATE_FUNDED'] ?? '')   !== '' ? $_POST['DATE_FUNDED']   : null,
    $_POST['FUNDING_ID']
  ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'FUNDING', $_POST['FUNDING_ID']]);

  redirect_to('/admin/crud/funding.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['FUNDING_ID'] ?? '')) guardFail('Missing ID');
  $pdo->prepare("DELETE FROM FUNDING WHERE FUNDING_ID=?")->execute([$_POST['FUNDING_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'FUNDING', $_POST['FUNDING_ID']]);
  redirect_to('/admin/crud/funding.php?ok=1');
}

/* Lookups */
$research = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")
                ->fetchAll(PDO::FETCH_ASSOC);
$agencies = $pdo->query("SELECT AGENCY_ID, AGENCY_NAME FROM AGENCY ORDER BY AGENCY_NAME")
                ->fetchAll(PDO::FETCH_ASSOC);

/* Filters / Sorting / Pagination */
$q    = trim($_GET['q'] ?? '');
$minFunding = isset($_GET['min_funding']) ? (float)$_GET['min_funding'] : 0;
$maxFunding = isset($_GET['max_funding']) ? (float)$_GET['max_funding'] : 99999999.99;
$sort = trim($_GET['sort'] ?? 'date_desc');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 5;
$offset = ($page - 1) * $per;

/* Header after handlers */
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
    width: min(100%, 300px); 
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
  display:grid; grid-template-columns: 1fr 1fr 140px 1fr; gap:16px;
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

/* CRUD Table Fixes for Funding */
.table-scroll table {
    /* Enforce table-layout: fixed for better column control, but use auto if content needs to wrap */
    table-layout: fixed; 
}
.table-scroll th:nth-child(1), .table-scroll td:nth-child(1) { width: 50px; }  /* ID */
.table-scroll th:nth-child(4), .table-scroll td:nth-child(4) { width: 120px; } /* Amount */
.table-scroll th:nth-child(5), .table-scroll td:nth-child(5) { width: 100px; } /* Date */
.table-scroll th:nth-child(6), .table-scroll td:nth-child(6) { width: 150px; } /* Actions */
/* Research (2nd) and Agency (3rd) share the remaining width */
</style>

<section class="panel fade-in crud-header-card">
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:12px; float:inline-end">
    <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=FUNDING">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3v10" />
        <path d="M8 7l4-4 4 4" />
        <path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
      </svg>
      <span style="margin-left:5px; font-size: 0.8rem;">Export CSV</span>
    </a>
    <button class="btn-action btn-primary" id="create-funding" style="font-size:0.8rem">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
      </svg> 
      Create Funding
    </button>
  </div>
  <h1 style="margin-bottom:8px;">Funding</h1>
  <p class="muted" style="margin-bottom:10px;">Manage funding rows. CSV import/export below.</p>

  <form method="get" class="filterbar" style="margin-bottom:10px;">
    <div class="searchbox">
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
      </svg>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>" placeholder="Search agency or research....">
      <button class="filter-toggle-btn" id="filter-btn" title="Filter" type="button" >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
      </button> 
    </div>
    <div id="filter-dropdown">
      <div class="funding-input" id="filter-options">
        <div class="field">
            <label>Minimum Funding</label>
            <input type="number" class="input min-input" value="0.00" step="0.01">
        </div>
        <div class="field">
            <label>Maximum Funding</label>
            <input type="number" class="input max-input" value="99999999.99" step="0.01">
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
          <input type="radio" name="sort" value="date_desc" <?= $sort==='date_desc'?'checked':''; ?>>
          <label>Date (Newest First)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="date_asc" <?= $sort==='date_asc'?'checked':''; ?>>
          <label>Date (Oldest First)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="amount_desc" <?= $sort==='amount_desc'?'checked':''; ?>>
          <label>Amount (High–Low)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="amount_asc" <?= $sort==='amount_asc'?'checked':''; ?>>
          <label>Amount (Low–High)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="title_asc" <?= $sort==='title_asc'?'checked':''; ?>>
          <label>Research (A–Z)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="agency_asc" <?= $sort==='agency_asc'?'checked':''; ?>>
          <label>Agency (A–Z)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="id_desc" <?= $sort==='id_desc'?'checked':''; ?>>
          <label>ID (Newest First)</label>
        </div>
        <div>
          <input type="radio" name="sort" value="id_asc" <?= $sort==='id_asc'?'checked':''; ?>>
          <label>ID (Oldest First)</label>
        </div>
      </fieldset>
    </div>
  </form>
</section>

<section class="panel" id="panel"></section>

<div class="admin-modal" id="createFundingModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="createFundingTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="createFundingTitle">Create New Funding</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="create">

      <div class="modal-grid">
        <div class="field">
          <label for="f_research">Research</label>
          <select class="input" id="f_research" name="RESEARCH_ID" required>
            <?php foreach($research as $r): ?>
              <option value="<?= (int)$r['RESEARCH_ID']; ?>"><?= htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="f_agency">Funding Agency</label>
          <select class="input" id="f_agency" name="AGENCY_ID" required>
            <?php foreach($agencies as $a): ?>
            <option value="<?= (int)$a['AGENCY_ID']; ?>"><?= htmlspecialchars($a['AGENCY_NAME']); ?></option>
          <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="f_amt">Amount (₱)</label>
          <input class="input" id="f_amt" type="number" name="FUNDING_AMOUNT" step="0.01" min="0" max="99999999.99">
        </div>
        <div class="field">
          <label for="f_date">Date Funded</label>
          <input class="input" id="f_date" type="date" name="DATE_FUNDED">
        </div>
      </div>
      <div class="admin-modal__actions">
        <button class="btn wide" type="submit">Create Funding</button>
        <button class="btn wide" type="button" data-close="1" style="background:#6b7280;border-color:#6b7280">Cancel</button>
      </div>
    </form>
  </div>
</div>
<div class="admin-modal" id="fundingModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="fundingModalTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="fundingModalTitle">Edit Funding</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="FUNDING_ID" id="m_id">

      <div class="modal-grid">
        <div class="field">
          <label for="m_research">Research</label>
          <select class="input" id="m_research" name="RESEARCH_ID" required>
            <?php foreach ($research as $r): ?>
              <option value="<?= (int)$r['RESEARCH_ID']; ?>"><?= htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="m_agency">Agency</label>
          <select class="input" id="m_agency" name="AGENCY_ID" required>
            <?php foreach ($agencies as $a): ?>
              <option value="<?= (int)$a['AGENCY_ID']; ?>"><?= htmlspecialchars($a['AGENCY_NAME']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="m_amount">Amount (₱)</label>
          <input class="input" id="m_amount" type="number" step="0.01" name="FUNDING_AMOUNT" min="0">
        </div>

        <div class="field">
          <label for="m_date">Date Funded</label>
          <input class="input" id="m_date" type="date" name="DATE_FUNDED">
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
  //Filter funding
   const fundingInputvalue = document.querySelectorAll(".funding-input input");
  let fundingGap = 100;

  // Adding event listeners to funding input elements
  for (let i = 0; i < fundingInputvalue.length; i++) {
    fundingInputvalue[i].addEventListener("input", e => {
      // Parse min and max values of the range input
      let minp = parseFloat(fundingInputvalue[0].value) || 0.00;
      let maxp = parseFloat(fundingInputvalue[1].value) || 9999999.99;
      let diff = maxp - minp

      // Validate the input values
      if (minp < 0) {
        alert("Minimum funding cannot be less than 0 pesos.");
        fundingInputvalue[0].value = 0;
        minp = 0;
      }
      if (maxp > 99999999.99) {
        alert("Maximum funding cannot be greater than 99999999.99 pesos.");
        fundingInputvalue[1].value = 99999999.99;
        maxp = 99999999.99;
      }
      if (minp > maxp - fundingGap) {
        alert("The minimum funding cannot be greater than maximum funding.");
        fundingInputvalue[0].value = maxp - fundingGap;
        minp = maxp - fundingGap;

        if (minp < 0) {
            fundingInputvalue[0].value = 0;
            minp = 0;
        }
      }
    });
  }
  fundingInputvalue.forEach(input => {
    input.addEventListener('input', handleLiveInput);
  });
  //Search
  const fundingPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const sortRadios = document.querySelectorAll('input[name="sort"]');
  const sortDropdown = document.querySelector('#sort-dropdown');
  const sortButton = document.querySelector('#sort-btn');
  const filterDropdown = document.querySelector('#filter-dropdown');
  const filterButton = document.querySelector('#filter-btn');
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
  function getSelectedSort() {
    const checkedRadio = document.querySelector('input[name="sort"]:checked');
    return checkedRadio ? checkedRadio.value : 'name_asc'; 
  }
  // Clear button function
  function clearFilters(e) {
      if (e) e.preventDefault();
      
      // Reset inputs
      queryInput.value = '';
      fundingInputvalue[0].value = '0';
      fundingInputvalue[1].value = '99999999.99';
      
      // Fetch results to show the unfiltered list
      fetchResults(1);

      // Hide the filter panel
      filterDropdown.style.display = "none";
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
    const minFunding = fundingInputvalue[0].value || 0;
    const maxFunding = fundingInputvalue[1].value || 99999999.99;
    const sort = getSelectedSort();
    const url = `../api/search_funding.php?q=${encodeURIComponent(q)}&min_funding=${minFunding}&max_funding=${maxFunding}&sort=${encodeURIComponent(sort)}&page=${page}`;
    
    fundingPanel.innerHTML = "<div class='loading'>Loading...</div>";
    
    fetch(url)
      .then(res => res.text())
      .then(html => {
        fundingPanel.innerHTML = html;
        attachPaginationEvents();
        attachEditButtons(); 
      })
      .catch(err => {
        fundingPanel.innerHTML = "<div class='error'>Failed to load results.</div>";
        console.error("Error:", err);
      });
  }
  
  // debounced input
  function handleLiveInput() {
    clearTimeout(timer);
    timer = setTimeout(() => fetchResults(1), 300);
  }
  queryInput.addEventListener('input', handleLiveInput);
  
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
  const createFundingModal = document.getElementById('createFundingModal');
  const f_research = document.getElementById('f_research');
  const f_agency = document.getElementById('f_agency');
  const f_amt = document.getElementById('f_amt');
  const f_date = document.getElementById('f_date');
  function openFundingModal() {
    f_research.value = '';
    f_agency.value = '';
    f_amt.value = '';
    f_date.value = '';
    createFundingModal.hidden = false; 
  }

  function closeFundingModal() { 
    createFundingModal.hidden = true; 
  }
  
  document.getElementById('create-funding').addEventListener('click', function() {
    openFundingModal();  
  });
      createFundingModal.addEventListener('click', e => {
    if (e.target.dataset.close) closeFundingModal();
  });
  
  window.addEventListener('keydown', e => {
    if (!createFundingModal.hidden && e.key === 'Escape') closeFundingModal();
  });
// Edit Modal 
  const modal = document.getElementById('fundingModal');
  const form  = modal.querySelector('form');
  const idI   = document.getElementById('m_id');
  const resI  = document.getElementById('m_research');
  const agI   = document.getElementById('m_agency');
  const amtI  = document.getElementById('m_amount');
  const dateI = document.getElementById('m_date');

  function open(payload){
    idI.value   = payload.id;
    resI.value  = payload.research || '';
    agI.value   = payload.agency || '';
    // The amount is explicitly set to empty string in PHP if null, 
    // so this line correctly handles both number and empty string for the number input.
    amtI.value  = payload.amount || ''; 
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
        research: this.dataset.research,
        agency: this.dataset.agency,
        amount: this.dataset.amount,
        date: this.dataset.date
        });
      });
    });
  }


  modal.addEventListener('click', e=>{ if (e.target.dataset.close) close(); });
  window.addEventListener('keydown', e=>{ if (!modal.hidden && e.key === 'Escape') close(); });
  // Initial load
  fetchResults(1);

</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>