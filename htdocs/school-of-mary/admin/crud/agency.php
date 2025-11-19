<?php
$pageTitle = 'Agencies (Admin)';

require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

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

$orderSql = "a.AGENCY_NAME ASC";
switch ($sort) {
  case 'name_desc':  $orderSql = "a.AGENCY_NAME DESC"; break;
  case 'id_asc':     $orderSql = "a.AGENCY_ID ASC";    break;
  case 'id_desc':    $orderSql = "a.AGENCY_ID DESC";   break;
  case 'recent_desc':$orderSql = "a.AGENCY_ID DESC";   break;
}

$baseSql = "FROM AGENCY a LEFT JOIN TYPE_AGENCY t ON a.AGENCY_TYPE = t.TYPE_CODE WHERE 1=1";
$params = [];
if ($q !== '')    { $baseSql .= " AND a.AGENCY_NAME LIKE ?"; $params[] = "%$q%"; }
if ($type !== '') { $baseSql .= " AND a.AGENCY_TYPE = ?";    $params[] = $type;  }

$total = (int)$pdo->prepare("SELECT COUNT(*) ".$baseSql)->execute($params) ?: 0;
$stmtCount = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

$sql = "SELECT a.AGENCY_ID, a.AGENCY_NAME, a.AGENCY_TYPE, a.AGENCY_CONTACTINFO, t.TYPE_LABEL
        $baseSql
        ORDER BY $orderSql
        LIMIT $per OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = max(1, (int)ceil($total / $per));

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
    min-width:130px;height:40px;padding:0 16px;
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

/* Removed the input:invalid:not(:focus):not(:placeholder-shown)[type="email"] style here */
</style>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Agencies</h1>
  <p class="muted" style="margin-bottom:10px;">Manage agencies and their types. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=AGENCY">Export CSV</a>
    <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="AGENCY">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid filter-bar" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 5">
      <label>Name</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Type</label>
      <select class="input" name="type">
        <option value="">All</option>
        <?php foreach ($types as $t):
          $sel = ($type === $t['TYPE_CODE']) ? ' selected' : '';
          echo '<option'.$sel.' value="'.$t['TYPE_CODE'].'">'.htmlspecialchars($t['TYPE_LABEL']).'</option>';
        endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2">
      <label>Order</label>
      <select class="input" name="sort">
        <option value="name_asc"  <?= $sort==='name_asc'?'selected':''; ?>>Name (A–Z)</option>
        <option value="name_desc" <?= $sort==='name_desc'?'selected':''; ?>>Name (Z–A)</option>
        <option value="id_asc"    <?= $sort==='id_asc'?'selected':''; ?>>ID (Low→High)</option>
        <option value="id_desc"   <?= $sort==='id_desc'?'selected':''; ?>>ID (High→Low)</option>
        <option value="recent_desc" <?= $sort==='recent_desc'?'selected':''; ?>>Newest First</option>
      </select>
    </div>
    <div class="field" style="grid-column: span 2; display:flex; align-items:flex-end; gap:10px">
      <button class="btn-action btn-primary" type="submit">Filter</button>
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/crud/agency.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<section class="panel crud-form-card" style="margin-bottom:16px;">
  <h3 style="margin-top:0">Create Agency</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 6">
      <label>Name</label>
      <input class="input" name="AGENCY_NAME" required maxlength="255">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Type</label>
      <select class="input" name="AGENCY_TYPE" required>
        <?php foreach ($types as $t): ?>
          <option value="<?= $t['TYPE_CODE']; ?>"><?= htmlspecialchars($t['TYPE_LABEL']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Contact (Email)</label>
      <input class="input" type="email" name="AGENCY_CONTACTINFO" required maxlength="100">
    </div>

    <div class="field" style="grid-column: span 12; display:flex; justify-content:flex-end;">
      <button class="btn wide">Add</button>
    </div>
  </form>
</section>

<section class="panel">
  <h3 style="margin-top:0">Records</h3>
  <div class="table-scroll">
    <table>
      <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Type</th>
        <th>Contact</th>
        <th>Actions</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= (int)$row['AGENCY_ID']; ?></td>
          <td><?= htmlspecialchars($row['AGENCY_NAME']); ?></td>
          <td><?= htmlspecialchars($row['TYPE_LABEL']); ?></td>
          <td><?= htmlspecialchars($row['AGENCY_CONTACTINFO']); ?></td>
          <td class="actions-cell">
            <button
              type="button"
              class="btn small js-edit"
              data-id="<?= (int)$row['AGENCY_ID']; ?>"
              data-name="<?= htmlspecialchars($row['AGENCY_NAME'], ENT_QUOTES); ?>"
              data-type="<?= htmlspecialchars($row['AGENCY_TYPE'], ENT_QUOTES); ?>"
              data-contact="<?= htmlspecialchars($row['AGENCY_CONTACTINFO'], ENT_QUOTES); ?>"
            >Edit</button>

            <form method="post" onsubmit="return confirm('Delete agency?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= csrf_token(); ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="AGENCY_ID" value="<?= (int)$row['AGENCY_ID']; ?>">
              <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="pagination">
    <?php
      // keep q/type/sort when paging
      $qs = function($p) use ($q, $type, $sort) {
        $parts = ['page='.$p];
        if ($q !== '')   $parts[]='q='.rawurlencode($q);
        if ($type !== '')$parts[]='type='.rawurlencode($type);
        if ($sort !== '')$parts[]='sort='.rawurlencode($sort);
        return implode('&',$parts);
      };
      $base = app_url('/admin/crud/agency.php');
    ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(max(1,$page-1)); ?>">&laquo;</a>
    <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a class="page-btn <?= $i===$page?'active':''; ?>" href="<?= $base.'?'.$qs($i); ?>"><?= $i; ?></a>
    <?php endfor; ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(min($totalPages,$page+1)); ?>">&raquo;</a>
  </div>
</section>

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

<script>
// Modal controller
(function(){
  const modal = document.getElementById('agencyModal');
  const form  = modal.querySelector('form');
  const id    = document.getElementById('m_id');
  const nameI = document.getElementById('m_name');
  const typeI = document.getElementById('m_type');
  const contI = document.getElementById('m_contact'); // Contact field

  function open(payload){
    id.value    = payload.id;
    nameI.value = payload.name || '';
    typeI.value = payload.type || '';
    contI.value = payload.contact || '';

    // Clear any previous error states for the contact field
    // (This line is less critical now that the red outline CSS is removed,
    // but good practice if you re-introduce custom validation styling later).
    contI.classList.remove('input-error');

    modal.hidden = false;
  }
  function close(){ modal.hidden = true; }

  // open buttons
  document.querySelectorAll('.js-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      open({
        id: btn.dataset.id,
        name: btn.dataset.name,
        type: btn.dataset.type,
        contact: btn.dataset.contact
      });
    });
  });

  // close handlers
  modal.addEventListener('click', e=>{
    if (e.target.dataset.close) close();
  });
  window.addEventListener('keydown', e=>{
    if (!modal.hidden && e.key === 'Escape') close();
  });
})();
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>