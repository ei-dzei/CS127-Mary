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
$sort = trim($_GET['sort'] ?? 'date_desc');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 5;
$offset = ($page - 1) * $per;

/* Header after handlers */
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
    <a class="btn-action btn-ghost" href="<?= app_url('/admin/api/export.php'); ?>?table=FUNDING">Export CSV</a>
    <button class="btn-action btn-primary" id="create-funding">+ Create Funding</button>
  </div>
  <h1 style="margin-bottom:8px;">Funding</h1>
  <p class="muted" style="margin-bottom:10px;">Manage funding rows. CSV import/export below.</p>

  

  <form method="get" class="grid filter-bar" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 7">
      <label>Search (research or agency)</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Order</label>
      <select class="input" name="sort">
        <option value="date_desc"   <?= $sort==='date_desc'?'selected':''; ?>>Date (Newest First)</option>
        <option value="date_asc"    <?= $sort==='date_asc'?'selected':''; ?>>Date (Oldest First)</option>
        <option value="amount_desc" <?= $sort==='amount_desc'?'selected':''; ?>>Amount (High → Low)</option>
        <option value="amount_asc"  <?= $sort==='amount_asc'?'selected':''; ?>>Amount (Low → High)</option>
        <option value="title_asc"   <?= $sort==='title_asc'?'selected':''; ?>>Research (A–Z)</option>
        <option value="agency_asc"  <?= $sort==='agency_asc'?'selected':''; ?>>Agency (A–Z)</option>
        <option value="id_desc"     <?= $sort==='id_desc'?'selected':''; ?>>ID (Newest First)</option>
        <option value="id_asc"      <?= $sort==='id_asc'?'selected':''; ?>>ID (Oldest First)</option>
      </select>
    </div>
    <div class="field" style="grid-column: span 2; display:flex; align-items:flex-end; gap:10px">
      <button class="btn-action btn-primary" type="submit">Filter</button>
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/crud/funding.php'); ?>">Clear</a>
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
          <input class="input" id="f_amt" type="number" step="0.01" name="FUNDING_AMOUNT" min="0">
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
  //Search
  const fundingPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const sortSelect = document.querySelector('select[name="sort"]');
  let timer = null;
  
  // fetch func
  function fetchResults(page) {
    const q = queryInput.value;
    const sort = sortSelect.value;
    const url = `../api/search_funding.php?q=${encodeURIComponent(q)}&sort=${encodeURIComponent(sort)}&page=${page}`;
    
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
  sortSelect.addEventListener('change', () => fetchResults(1));
  
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