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
  if (!v_decimal_nullable($_POST['FUNDING_AMOUNT'] ?? '')) guardFail('Invalid amount');
  if (!v_date_nullable($_POST['DATE_FUNDED'] ?? '')) guardFail('Invalid date');

  $sql = "INSERT INTO FUNDING (RESEARCH_ID, AGENCY_ID, FUNDING_AMOUNT, DATE_FUNDED) VALUES (?,?,?,?)";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'],
    $_POST['AGENCY_ID'],
    ($_POST['FUNDING_AMOUNT'] ?? '') !== '' ? $_POST['FUNDING_AMOUNT'] : null,
    ($_POST['DATE_FUNDED'] ?? '')   !== '' ? $_POST['DATE_FUNDED']   : null
  ]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'FUNDING']);

  redirect_to('/admin/crud/funding.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['FUNDING_ID'] ?? '')) guardFail('Missing ID');
  if (!v_int($_POST['RESEARCH_ID'] ?? '') || !v_int($_POST['AGENCY_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_decimal_nullable($_POST['FUNDING_AMOUNT'] ?? '')) guardFail('Invalid amount');
  if (!v_date_nullable($_POST['DATE_FUNDED'] ?? '')) guardFail('Invalid date');

  $sql = "UPDATE FUNDING
          SET RESEARCH_ID=?, AGENCY_ID=?, FUNDING_AMOUNT=?, DATE_FUNDED=?
          WHERE FUNDING_ID=?";
  $pdo->prepare($sql)->execute([
    $_POST['RESEARCH_ID'],
    $_POST['AGENCY_ID'],
    ($_POST['FUNDING_AMOUNT'] ?? '') !== '' ? $_POST['FUNDING_AMOUNT'] : null,
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

$sortMap = [
  'date_desc'    => 'fu.DATE_FUNDED DESC, fu.FUNDING_ID DESC',
  'date_asc'     => 'fu.DATE_FUNDED ASC, fu.FUNDING_ID ASC',
  'amount_desc'  => 'fu.FUNDING_AMOUNT DESC, fu.FUNDING_ID DESC',
  'amount_asc'   => 'fu.FUNDING_AMOUNT ASC, fu.FUNDING_ID ASC',
  'title_asc'    => 're.RESEARCH_TITLE ASC, fu.FUNDING_ID DESC',
  'agency_asc'   => 'ag.AGENCY_NAME ASC, fu.FUNDING_ID DESC',
  'id_desc'      => 'fu.FUNDING_ID DESC',
  'id_asc'       => 'fu.FUNDING_ID ASC',
];
$orderSql = $sortMap[$sort] ?? $sortMap['date_desc'];

$baseSql = "FROM FUNDING fu
            JOIN RESEARCH re ON fu.RESEARCH_ID=re.RESEARCH_ID
            JOIN AGENCY  ag ON fu.AGENCY_ID  =ag.AGENCY_ID
            WHERE 1=1";
$params = [];
if ($q !== '') {
  $baseSql .= " AND (re.RESEARCH_TITLE LIKE ? OR ag.AGENCY_NAME LIKE ?)";
  $params = ["%$q%","%$q%"];
}

/* Count for pagination */
$stmtCnt = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
$stmtCnt->execute($params);
$total = (int)$stmtCnt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $per));

/* Page rows */
$sql = "SELECT fu.*, re.RESEARCH_TITLE, ag.AGENCY_NAME
        $baseSql
        ORDER BY $orderSql
        LIMIT $per OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
</style>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Funding</h1>
  <p class="muted" style="margin-bottom:10px;">Manage funding rows. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=FUNDING">Export CSV</a>
  </div>

  <!-- Filter / Sort -->
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

<section class="panel crud-form-card" style="margin-bottom:16px;">
  <h3 style="margin-top:0">Create Funding</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach($research as $r): ?>
          <option value="<?= (int)$r['RESEARCH_ID']; ?>"><?= htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 4">
      <label>Agency</label>
      <select class="input" name="AGENCY_ID" required>
        <?php foreach($agencies as $a): ?>
          <option value="<?= (int)$a['AGENCY_ID']; ?>"><?= htmlspecialchars($a['AGENCY_NAME']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 2">
      <label>Amount (₱)</label>
      <input class="input" type="number" step="0.01" name="FUNDING_AMOUNT">
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Date Funded</label>
      <input class="input" type="date" name="DATE_FUNDED">
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
          <th>ID</th><th>Research</th><th>Agency</th><th>Amount</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $row): ?>
          <tr>
            <td><?= (int)$row['FUNDING_ID']; ?></td>
            <td><?= htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
            <td><?= htmlspecialchars($row['AGENCY_NAME']); ?></td>
            <td><?= $row['FUNDING_AMOUNT'] !== null ? '₱' . number_format((float)$row['FUNDING_AMOUNT'], 2) : '—'; ?></td>
            <td><?= htmlspecialchars((string)$row['DATE_FUNDED']); ?></td>
            <td class="actions-cell">
              <button
                type="button"
                class="btn small js-edit"
                data-id="<?= (int)$row['FUNDING_ID']; ?>"
                data-research="<?= (int)$row['RESEARCH_ID']; ?>"
                data-agency="<?= (int)$row['AGENCY_ID']; ?>"
                data-amount="<?= htmlspecialchars((string)$row['FUNDING_AMOUNT'], ENT_QUOTES); ?>"
                data-date="<?= htmlspecialchars((string)$row['DATE_FUNDED'], ENT_QUOTES); ?>"
              >Edit</button>

              <form method="post" onsubmit="return confirm('Delete funding row?');" style="display:inline">
                <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="FUNDING_ID" value="<?= (int)$row['FUNDING_ID']; ?>">
                <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" style="text-align:center;color:#666;">No records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="pagination">
    <?php
      $qs = function($p) use ($q, $sort) {
        $parts = ['page='.$p];
        if ($q   !== '') $parts[]='q='.rawurlencode($q);
        if ($sort!== '') $parts[]='sort='.rawurlencode($sort);
        return implode('&',$parts);
      };
      $base = app_url('/admin/crud/funding.php');
    ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(max(1,$page-1)); ?>" title = "Previous Page">&#x276E;</a>
    <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a class="page-btn <?= $i===$page?'active':''; ?>" href="<?= $base.'?'.$qs($i); ?>"><?= $i; ?></a>
    <?php endfor; ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(min($totalPages,$page+1)); ?>" title = "Next Page">&#x276F;</a>
  </div>
</section>

<!-- --------- Modal HTML --------- -->
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
          <input class="input" id="m_amount" type="number" step="0.01" name="FUNDING_AMOUNT">
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
// Modal controller
(function(){
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
    amtI.value  = payload.amount || '';
    dateI.value = payload.date || '';
    modal.hidden = false;
  }
  function close(){ modal.hidden = true; }

  document.querySelectorAll('.js-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      open({
        id: btn.dataset.id,
        research: btn.dataset.research,
        agency: btn.dataset.agency,
        amount: btn.dataset.amount,
        date: btn.dataset.date
      });
    });
  });

  modal.addEventListener('click', e=>{ if (e.target.dataset.close) close(); });
  window.addEventListener('keydown', e=>{ if (!modal.hidden && e.key === 'Escape') close(); });
})();
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
