<?php
$pageTitle = 'Agencies (Admin)';

require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

if (!is_admin()) { redirect_to('/admin/login.php'); }
csrf_check();

/* ------------------------- Actions ------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_varchar($_POST['AGENCY_NAME'] ?? '', 255)) guardFail('Invalid name');
  if (!v_enum_exists($pdo, 'TYPE_AGENCY', 'TYPE_CODE', $_POST['AGENCY_TYPE'] ?? '')) guardFail('Invalid type');
  if (!v_varchar($_POST['AGENCY_CONTACTINFO'] ?? '', 100)) guardFail('Invalid contact');

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
  if (!v_varchar($_POST['AGENCY_CONTACTINFO'] ?? '', 100)) guardFail('Invalid contact');

  $pdo->prepare("UPDATE AGENCY SET AGENCY_NAME=?, AGENCY_TYPE=?, AGENCY_CONTACTINFO=? WHERE AGENCY_ID=?")
      ->execute([$_POST['AGENCY_NAME'], $_POST['AGENCY_TYPE'], $_POST['AGENCY_CONTACTINFO'], $_POST['AGENCY_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'AGENCY', $_POST['AGENCY_ID']]);

  redirect_to('/admin/crud/agency.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing ID');

  $pdo->prepare("DELETE FROM AGENCY WHERE AGENCY_ID=?")->execute([$_POST['AGENCY_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'AGENCY', $_POST['AGENCY_ID']]);

  redirect_to('/admin/crud/agency.php?ok=1');
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
  display:grid; grid-template-columns: 1fr 320px 1fr; gap:16px;
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
/* Action buttons in the filter row */
  .btn-action{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:30px;height:40px;padding:0 16px;
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
  #create-agency{display: flex; flex: 1; float: right;}
</style>

<section class="panel fade-in crud-header-card">
    <button class="btn btn-action" id="create-agency" >+ Create Agency</button>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; flex: 1; float: right;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=AGENCY">Export CSV</a>
  </div>
  <h1 style="margin-bottom:8px;">Agencies</h1>
  <p class="muted" style="margin-bottom:10px;">Manage agencies and their types.</p>
  

  <!-- Filter / Sort -->
  <form method="get" class="grid filter-bar" style="margin-bottom:10px;">
    <div class="searchbox" style="grid-column: span 11" >
      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M10 18a8 8 0 1 1 6.32-3.1l4.39 4.39-1.42 1.42-4.39-4.39A7.98 7.98 0 0 1 10 18Zm0-2a6 6 0 1 0 0-12 6 6 0 0 0 0 12Z" fill="currentColor"/>
        </svg>
      <input class="input" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search agency..." style="width:70%" />
      <button class="btn-action btn-primary" type="button" id="filter-btn" onclick="showHide()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-filter" viewBox="0 0 16 16">
            <path d="M6 10.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m-2-3a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-11a.5.5 0 0 1-.5-.5"/>
          </svg>
      </button>
      <div id="filter-dropdown">
        <div class="field" style="grid-column: span 3">
        <label>Type</label>
        <select class="input" name="type">
          <option value="">All</option>
          <?php foreach ($types as $t):
            $sel = ($type === $t['TYPE_CODE']) ? ' selected' : '';
            echo '<option'.$sel.' value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>';
          endforeach; ?>
        </select>
        <div class="field" style="grid-column: span 2; display:flex; align-items:flex-end; gap:10px">
          <a class="btn-action btn-ghost" onclick="clearFilter()">Clear</a>
        </div>
      </div>
      </div>
    </div>
    <div class="field" style="grid-column: span 1; float: right; top: 0; margin: bottom 150px; vertical-align:text-top">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-arrows-sort"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 9l4 -4l4 4m-4 -4v14" /><path d="M21 15l-4 4l-4 -4m4 4v-14" /></svg>
      <select class="input" name="sort">
        <option>
          
        </option>
        <option value="name_asc"  <?= $sort==='name_asc'?'selected':''; ?>>Name (A–Z)</option>
        <option value="name_desc" <?= $sort==='name_desc'?'selected':''; ?>>Name (Z–A)</option>
        <option value="id_asc"    <?= $sort==='id_asc'?'selected':''; ?>>ID (Low→High)</option>
        <option value="id_desc"   <?= $sort==='id_desc'?'selected':''; ?>>ID (High→Low)</option>
        <option value="recent_desc" <?= $sort==='recent_desc'?'selected':''; ?>>Newest First</option>
      </select>
    </div>
    
  </form>
</section>

<section class="panel" id="panel"></section>
<!-- Create Agency Modal -->
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
<!-- --------- Modal HTML --------- -->
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
          <input class="input" id="m_name" name="AGENCY_NAME" required>
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
          <label for="m_contact">Contact</label>
          <input class="input" id="m_contact" name="AGENCY_CONTACTINFO" required>
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
  const agencyPanel = document.querySelector('#panel');
  const queryInput = document.querySelector('input[name="q"]');
  const typeSelect = document.querySelector('select[name="type"]');
  const sortSelect = document.querySelector('select[name="sort"]');
  let timer = null;
  
  function showHide() {
    var f = document.getElementById("filter-dropdown");
    if (f.style.display == "none") {
      f.style.display = "block";
    } else {
      f.style.display = "none";
    }
  }
  
  function clearFilter() {
    if((queryInput.value == '') && (typeSelect.value == '')) {
      return;
    } else {
      typeSelect.value = '';
      fetchResults(1);
    }
  }
  
  // fetch func
  function fetchResults(page) {
    const q = queryInput.value;
    const type = typeSelect.value;
    const sort = sortSelect.value;
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
  const contI = document.getElementById('m_contact');

  function openModal(payload) {
    id.value = payload.id;
    nameI.value = payload.name || '';
    typeI.value = payload.type || '';
    contI.value = payload.contact || '';
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
  
  // Initial load
  fetchResults(1);
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
