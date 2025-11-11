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
csrf_check();

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
$PAGE_SIZE = 5;
$offset = ($page - 1) * $PAGE_SIZE;

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
$orderBy = $sortMap[$sort] ?? $sortMap['date_desc'];

$where = "WHERE 1=1";
$params = [];
if ($q !== '') {
  $where .= " AND (re.RESEARCH_TITLE LIKE ? OR ag.AGENCY_NAME LIKE ?)";
  $params = ["%$q%","%$q%"];
}

/* Count for pagination */
$countSql = "SELECT COUNT(*)
             FROM FUNDING fu
             JOIN RESEARCH re ON fu.RESEARCH_ID=re.RESEARCH_ID
             JOIN AGENCY  ag ON fu.AGENCY_ID  =ag.AGENCY_ID
             $where";
$stmtCnt = $pdo->prepare($countSql);
$i=1; foreach ($params as $p){ $stmtCnt->bindValue($i++, $p, PDO::PARAM_STR); }
$stmtCnt->execute();
$total = (int)$stmtCnt->fetchColumn();
$pages = max(1, (int)ceil($total / $PAGE_SIZE));

/* Page rows */
$sql = "SELECT fu.*, re.RESEARCH_TITLE, ag.AGENCY_NAME
        FROM FUNDING fu
        JOIN RESEARCH re ON fu.RESEARCH_ID=re.RESEARCH_ID
        JOIN AGENCY  ag ON fu.AGENCY_ID  =ag.AGENCY_ID
        $where
        ORDER BY $orderBy
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
$i=1; foreach ($params as $p){ $stmt->bindValue($i++, $p, PDO::PARAM_STR); }
$stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Header after handlers */
require_once __DIR__ . '/../../partials/site_header.php';
$CSRF = csrf_token();
?>

<style>
/* Buttons parity */
.btn-action{
  display:inline-flex; align-items:center; justify-content:center;
  min-width:130px; height:40px; padding:0 16px;
  border-radius:8px; border:1px solid var(--color-accent);
  font-weight:600; text-decoration:none; cursor:pointer;
  transition:background .2s ease, color .2s ease, transform .06s ease, box-shadow .15s ease;
}
.btn-action:active{ transform:translateY(1px); }
.btn-primary{ background:var(--color-accent); color:#fff; }
.btn-primary:hover{ filter:brightness(.94); box-shadow:0 4px 10px rgba(0,0,0,.06); }
.btn-ghost{ background:#fff; color:var(--color-accent); border-color:rgba(11,83,148,.35); }
.btn-ghost:hover{ background:rgba(11,83,148,.05); }

/* Table & actions */
.table-scroll{ overflow-x:auto; }
.actions-cell{ display:flex; flex-wrap:wrap; gap:8px 10px; align-items:center; white-space:normal; }

/* Pagination */
.pager{ display:flex; gap:8px; align-items:center; margin-top:10px; }
.pager a, .pager span{
  display:inline-flex; min-width:32px; height:32px; padding:0 10px;
  border:1px solid #d7e1ef; border-radius:18px; align-items:center; justify-content:center;
  text-decoration:none; color:#234b7a; background:#fff;
}
.pager .active{ background:#234b7a; color:#fff; border-color:#234b7a; }
.pager .disabled{ opacity:.5; pointer-events:none; }

/* Modal */
.modal[hidden]{display:none!important;}
.modal{
  position:fixed; inset:0; z-index:2000;
  display:grid; place-items:center;
  background:rgba(0,0,0,.45);
}
.modal__dialog{
  width:min(960px, 92vw);
  max-height:82vh; overflow:auto;
  background:#fff; border:1px solid #e5eaf0; border-radius:14px;
  box-shadow:0 22px 50px rgba(0,0,0,.18);
}
.modal__head{
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 18px; background:#f6f8fb; border-bottom:1px solid #e5eaf0;
}
#modal-title{ font-weight:800; font-size:1.1rem; color:#0b1426; }
.modal__close{
  border:none; background:#fff; width:36px; height:36px; border-radius:8px; cursor:pointer;
  border:1px solid #e6ebf2;
}
.modal__close:hover{ background:#f1f5fa; }
#modal-form{ padding:16px 18px; }
#modal-form .grid{ grid-template-columns: repeat(12, 1fr); gap:1rem; }
#modal-form .field{ grid-column: span 12; }
#modal-form .input, #modal-form select{ width:100%; }
.modal__actions{ display:flex; gap:10px; justify-content:flex-end; padding:10px 18px; border-top:1px dashed #e5eaf0; }

@media (min-width: 768px){
  #modal-form .field--research { grid-column: span 6; }
  #modal-form .field--agency  { grid-column: span 4; }
  #modal-form .field--amount  { grid-column: span 2; }
  #modal-form .field--date    { grid-column: span 3; }
}
</style>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Funding</h1>
  <p class="muted" style="margin-bottom:8px;">Manage funding rows. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=FUNDING">Export CSV</a>
    <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="FUNDING">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid" style="margin-bottom:8px;">
    <div class="field" style="grid-column: span 7">
      <label>Search (research or agency)</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Order</label>
      <select class="input" name="sort">
        <?php
          $opts = [
            'date_desc'   => 'Date (newest first)',
            'date_asc'    => 'Date (oldest first)',
            'amount_desc' => 'Amount (high → low)',
            'amount_asc'  => 'Amount (low → high)',
            'title_asc'   => 'Research (A–Z)',
            'agency_asc'  => 'Agency (A–Z)',
            'id_desc'     => 'ID (newest first)',
            'id_asc'      => 'ID (oldest first)',
          ];
          foreach ($opts as $val=>$lab){
            $sel = $sort === $val ? ' selected' : '';
            echo "<option value=\"$val\"$sel>".htmlspecialchars($lab)."</option>";
          }
        ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 12; display:flex; gap:10px; justify-content:flex-end; align-items:flex-end;">
      <?php $base = app_url('/admin/crud/funding.php'); ?>
      <button class="btn-action btn-primary" type="submit">Filter</button>
      <a class="btn-action btn-ghost" href="<?= $base; ?>">Clear</a>
    </div>
  </form>
</section>

<section class="panel crud-form-card" style="margin-bottom:16px;">
  <h3 style="margin-top:0">Create Funding</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?= $CSRF = csrf_token(); ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach($research as $r){ echo '<option value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 4">
      <label>Agency</label>
      <select class="input" name="AGENCY_ID" required>
        <?php foreach($agencies as $a){ echo '<option value="'.$a['AGENCY_ID'].'">'.htmlspecialchars($a['AGENCY_NAME']).'</option>'; } ?>
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

    <div class="field" style="grid-column: span 12">
      <button class="btn" style="width:100%;">Add</button>
    </div>
  </form>
</section>

<section class="panel">
  <h3 style="margin-top:0">Records</h3>
  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>ID</th><th>Research</th><th>Agency</th><th>Amount</th><th>Date</th><th>Actions</th></tr>
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
            <button class="btn small"
                    data-modal="edit" data-title="Edit Funding"
                    data-template="#tpl-edit-<?= (int)$row['FUNDING_ID']; ?>"
                    data-action="update"
                    data-hidden-FUNDING_ID="<?= (int)$row['FUNDING_ID']; ?>">
              Edit
            </button>

            <form method="post" onsubmit="return confirm('Delete funding row?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= $CSRF ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="FUNDING_ID" value="<?= (int)$row['FUNDING_ID']; ?>">
              <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
            </form>

            <template id="tpl-edit-<?= (int)$row['FUNDING_ID']; ?>">
              <div class="grid">
                <div class="field field--research">
                  <label>Research</label>
                  <select class="input" name="RESEARCH_ID" required>
                    <?php foreach($research as $r){
                      $sel = ($r['RESEARCH_ID'] === $row['RESEARCH_ID']) ? ' selected' : '';
                      echo '<option'.$sel.' value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>';
                    } ?>
                  </select>
                </div>
                <div class="field field--agency">
                  <label>Agency</label>
                  <select class="input" name="AGENCY_ID" required>
                    <?php foreach($agencies as $a){
                      $sel = ($a['AGENCY_ID'] === $row['AGENCY_ID']) ? ' selected' : '';
                      echo '<option'.$sel.' value="'.$a['AGENCY_ID'].'">'.htmlspecialchars($a['AGENCY_NAME']).'</option>';
                    } ?>
                  </select>
                </div>
                <div class="field field--amount">
                  <label>Amount (₱)</label>
                  <input class="input" type="number" step="0.01" name="FUNDING_AMOUNT"
                         value="<?= htmlspecialchars((string)$row['FUNDING_AMOUNT']); ?>">
                </div>
                <div class="field field--date">
                  <label>Date Funded</label>
                  <input class="input" type="date" name="DATE_FUNDED" value="<?= htmlspecialchars((string)$row['DATE_FUNDED']); ?>">
                </div>
              </div>
            </template>
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
  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php
        $base  = app_url('/admin/crud/funding.php');
        $qs    = 'q='.urlencode($q).'&sort='.urlencode($sort);
        $prev  = $page - 1;
        $next  = $page + 1;
      ?>
      <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : "{$base}?{$qs}&page={$prev}" ?>">Prev</a>
      <?php for ($i=1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= "{$base}?{$qs}&page={$i}" ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $page >= $pages ? '#' : "{$base}?{$qs}&page={$next}" ?>">Next</a>
    </div>
  <?php endif; ?>
</section>

<!-- Modal -->
<div class="modal" id="modal" hidden>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal__head">
      <div id="modal-title">Edit</div>
      <button type="button" class="modal__close" aria-label="Close" id="modal-close">×</button>
    </div>
    <form id="modal-form" method="post">
      <div class="modal__actions">
        <button class="btn btn-primary" type="submit">Save</button>
        <button class="btn btn-ghost" type="button" id="modal-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const csrf  = <?= json_encode($CSRF) ?>;
  const modal = document.getElementById('modal');
  const form  = document.getElementById('modal-form');
  const title = document.getElementById('modal-title');
  const closeBtn  = document.getElementById('modal-close');
  const cancelBtn = document.getElementById('modal-cancel');

  function openModal(html, opts){
    form.querySelectorAll(':scope > :not(.modal__actions)').forEach(n => n.remove());
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    form.insertBefore(wrap.firstElementChild, form.querySelector('.modal__actions'));

    const addHidden = (n,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    addHidden('csrf', csrf);
    addHidden('action', opts.action || 'update');
    if (opts.hidden) Object.entries(opts.hidden).forEach(([k,v]) => addHidden(k,v));

    title.textContent = opts.title || 'Edit';
    modal.hidden = false;
  }
  function closeModal(){ modal.hidden = true; }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('button[data-modal]');
    if (!btn) return;
    const tpl = document.querySelector(btn.getAttribute('data-template'));
    if (!tpl) return;

    const hidden = {};
    for (const a of btn.attributes){
      if (a.name.startsWith('data-hidden-')){
        hidden[a.name.replace('data-hidden-','')] = a.value;
      }
    }
    openModal(tpl.innerHTML, {
      title: btn.getAttribute('data-title') || 'Edit',
      action: btn.getAttribute('data-action') || 'update',
      hidden
    });
  });

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });
})();
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
