<?php
$pageTitle = 'Faculty (Admin)';

// Core
require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

// Auth & CSRF
if (!is_admin()) { redirect_to('/admin/login.php'); }
csrf_check();

/* ---------- Actions ---------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_varchar($_POST['FACULTY_FNAME'] ?? '', 50)) guardFail('Invalid first name');
  if (!v_char_nullable($_POST['FACULTY_INITIAL'] ?? '', 2)) guardFail('Invalid initial');
  if (!v_varchar($_POST['FACULTY_LNAME'] ?? '', 50)) guardFail('Invalid last name');
  if (!v_email($_POST['FACULTY_EMAIL'] ?? '')) guardFail('Invalid email');
  if (!v_enum_exists($pdo, '`RANK`', 'RANK_ID', $_POST['RANK_ID'] ?? null)) guardFail('Invalid rank');
  if (!v_enum_exists($pdo, 'DEPARTMENT', 'DEPT_ID', $_POST['DEPT_ID'] ?? null)) guardFail('Invalid department');

  $pdo->prepare("
    INSERT INTO FACULTY (FACULTY_FNAME, FACULTY_INITIAL, FACULTY_LNAME, FACULTY_EMAIL, RANK_ID, DEPT_ID)
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

if ($action === 'delete') {
  if (!v_int($_POST['FACULTY_ID'] ?? '')) guardFail('Missing ID');
  $pdo->prepare("DELETE FROM FACULTY WHERE FACULTY_ID=?")->execute([$_POST['FACULTY_ID']]);
  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'FACULTY', $_POST['FACULTY_ID']]);
  redirect_to('/admin/crud/faculty.php?ok=1');
}

/* ---------- Lookups ---------- */
$ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll(PDO::FETCH_ASSOC);
$depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Filters / Sorting / Pagination ---------- */
$q    = trim($_GET['q'] ?? '');
$rank = trim($_GET['rank'] ?? '');
$dept = trim($_GET['dept'] ?? '');
$sort = trim($_GET['sort'] ?? 'name_asc');
$page = max(1, (int)($_GET['page'] ?? 1));
$PAGE_SIZE = 5;
$offset = ($page - 1) * $PAGE_SIZE;

$sortMap = [
  'id_desc'   => 'f.FACULTY_ID DESC',
  'id_asc'    => 'f.FACULTY_ID ASC',
  'name_asc'  => 'f.FACULTY_LNAME ASC, f.FACULTY_FNAME ASC',
  'name_desc' => 'f.FACULTY_LNAME DESC, f.FACULTY_FNAME DESC',
  'email_asc' => 'f.FACULTY_EMAIL ASC',
  'email_desc'=> 'f.FACULTY_EMAIL DESC',
  'rank_asc'  => 'r.RANK_LEVEL ASC, f.FACULTY_LNAME ASC',
  'dept_asc'  => 'd.DEPT_SPECIALIZATION ASC, f.FACULTY_LNAME ASC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['name_asc'];

$where = "WHERE 1=1";
$params = [];
if ($q !== '') {
  $where .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR f.FACULTY_EMAIL LIKE ?)";
  array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($rank !== '') { $where .= " AND f.RANK_ID = ?"; $params[] = $rank; }
if ($dept !== '') { $where .= " AND f.DEPT_ID = ?"; $params[] = $dept; }

$countSql = "SELECT COUNT(*)
             FROM FACULTY f
             JOIN `RANK` r ON f.RANK_ID=r.RANK_ID
             JOIN DEPARTMENT d ON f.DEPT_ID=d.DEPT_ID
             $where";
$stmtCnt = $pdo->prepare($countSql);
$stmtCnt->execute($params);
$total = (int)$stmtCnt->fetchColumn();
$pages = max(1, (int)ceil($total / $PAGE_SIZE));

$sql = "SELECT f.*, r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
        FROM FACULTY f
        JOIN `RANK` r ON f.RANK_ID=r.RANK_ID
        JOIN DEPARTMENT d ON f.DEPT_ID=d.DEPT_ID
        $where
        ORDER BY $orderBy
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) { $stmt->bindValue($i++, $p, PDO::PARAM_STR); }
$stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// View
require_once __DIR__ . '/../../partials/site_header.php';
$CSRF = csrf_token();
?>

<style>
/* buttons parity (Filter / Clear) */
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

/* actions cell */
.actions-cell { display:flex; flex-wrap:wrap; align-items:center; gap:8px 10px; white-space:normal; min-width:360px; }
.actions-cell .input, .actions-cell select{ max-width:220px; }

/* pager */
.pager{ display:flex; gap:8px; align-items:center; margin-top:10px; }
.pager a, .pager span{
  display:inline-flex; min-width:32px; height:32px; padding:0 10px;
  border:1px solid #d7e1ef; border-radius:18px; align-items:center; justify-content:center;
  text-decoration:none; color:#234b7a; background:#fff;
}
.pager .active{ background:#234b7a; color:#fff; border-color:#234b7a; }
.pager .disabled{ opacity:.5; pointer-events:none; }

/* modal */
.modal[hidden]{display:none!important;}
.modal{
  position:fixed; inset:0; z-index:2000;
  display:grid; place-items:center;
  background:rgba(0,0,0,.45); animation:modalFade .18s ease-out;
}
@keyframes modalFade{from{opacity:0}to{opacity:1}}
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
  #modal-form .field--fname   { grid-column: span 4; }
  #modal-form .field--initial { grid-column: span 2; }
  #modal-form .field--lname   { grid-column: span 6; }
  #modal-form .field--email   { grid-column: span 6; }
  #modal-form .field--rank    { grid-column: span 3; }
  #modal-form .field--dept    { grid-column: span 3; }
}
</style>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Faculty</h1>
  <p class="muted" style="margin-bottom:8px;">Create, update, delete; inline Rank/Department edits; CSV import/export.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=FACULTY">Export CSV</a>
    <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="FACULTY">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 4">
      <label>Search (name or email)</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q) ?>">
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Rank</label>
      <select class="input" name="rank">
        <option value="">All</option>
        <?php foreach($ranks as $r){ $sel=$rank===$r['RANK_ID']?' selected':''; echo '<option'.$sel.' value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Department</label>
      <select class="input" name="dept">
        <option value="">All</option>
        <?php foreach($depts as $d){ $sel=$dept===$d['DEPT_ID']?' selected':''; echo '<option'.$sel.' value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 2">
      <label>Order</label>
      <select class="input" name="sort">
        <?php
          $opts = [
            'name_asc'  => 'Name (A–Z)',
            'name_desc' => 'Name (Z–A)',
            'id_desc'   => 'ID (newest first)',
            'id_asc'    => 'ID (oldest first)',
            'email_asc' => 'Email (A–Z)',
            'email_desc'=> 'Email (Z–A)',
            'rank_asc'  => 'Rank',
            'dept_asc'  => 'Department',
          ];
          foreach ($opts as $val=>$label) {
            $sel = $sort === $val ? ' selected' : '';
            echo "<option value=\"$val\"$sel>".htmlspecialchars($label)."</option>";
          }
        ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 12; display:flex; gap:10px; justify-content:flex-end">
      <button class="btn-action btn-primary" type="submit">Filter</button>
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/crud/faculty.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<section class="panel crud-form-card" style="margin-bottom:16px;">
  <h3 style="margin-top:0">Create Faculty</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?= $CSRF ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 3"><label>First name</label><input class="input" name="FACULTY_FNAME" required></div>
    <div class="field" style="grid-column: span 2"><label>Initial</label><input class="input" name="FACULTY_INITIAL" maxlength="2"></div>
    <div class="field" style="grid-column: span 3"><label>Last name</label><input class="input" name="FACULTY_LNAME" required></div>
    <div class="field" style="grid-column: span 4"><label>Email</label><input class="input" type="email" name="FACULTY_EMAIL" required></div>
    <div class="field" style="grid-column: span 3">
      <label>Rank</label>
      <select class="input" name="RANK_ID" required>
        <?php foreach($ranks as $r){ echo '<option value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Department</label>
      <select class="input" name="DEPT_ID" required>
        <?php foreach($depts as $d){ echo '<option value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?>
      </select>
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
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Rank</th><th>Dept</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($rows as $row): ?>
        <tr>
          <td><?= (int)$row['FACULTY_ID']; ?></td>
          <td><?= htmlspecialchars($row['FACULTY_LNAME'].', '.$row['FACULTY_FNAME'].($row['FACULTY_INITIAL'] ? ' '.$row['FACULTY_INITIAL'] : '')); ?></td>
          <td><?= htmlspecialchars($row['FACULTY_EMAIL']); ?></td>
          <td><?= htmlspecialchars($row['RANK_DESCRIPTION']); ?></td>
          <td><?= htmlspecialchars($row['DEPT_SPECIALIZATION']); ?></td>
          <td class="actions-cell">
            <!-- inline quick save for rank/department -->
            <form method="post" style="display:inline-flex;gap:6px;align-items:center">
              <input type="hidden" name="csrf" value="<?= $CSRF ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="FACULTY_ID" value="<?= (int)$row['FACULTY_ID']; ?>">
              <input type="hidden" name="FACULTY_FNAME" value="<?= htmlspecialchars($row['FACULTY_FNAME']); ?>">
              <input type="hidden" name="FACULTY_INITIAL" value="<?= htmlspecialchars($row['FACULTY_INITIAL']); ?>">
              <input type="hidden" name="FACULTY_LNAME" value="<?= htmlspecialchars($row['FACULTY_LNAME']); ?>">
              <input type="hidden" name="FACULTY_EMAIL" value="<?= htmlspecialchars($row['FACULTY_EMAIL']); ?>">

              <select name="RANK_ID" class="input" style="width:160px">
                <?php foreach($ranks as $r){ $sel=($r['RANK_ID']===$row['RANK_ID'])?' selected':''; echo '<option'.$sel.' value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?>
              </select>
              <select name="DEPT_ID" class="input" style="width:220px">
                <?php foreach($depts as $d){ $sel=($d['DEPT_ID']===$row['DEPT_ID'])?' selected':''; echo '<option'.$sel.' value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?>
              </select>
              <button class="btn small">Save</button>
            </form>

            <!-- modal edit trigger -->
            <button
              class="btn small"
              data-modal="edit" data-title="Edit Faculty"
              data-template="#tpl-edit-<?= (int)$row['FACULTY_ID'];?>"
              data-action="update"
              data-hidden-FACULTY_ID="<?= (int)$row['FACULTY_ID']; ?>"
            >Edit</button>

            <!-- delete -->
            <form method="post" onsubmit="return confirm('Delete faculty?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= $CSRF ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="FACULTY_ID" value="<?= (int)$row['FACULTY_ID']; ?>">
              <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
            </form>

            <!-- modal template -->
            <template id="tpl-edit-<?= (int)$row['FACULTY_ID'];?>">
              <div class="grid">
                <div class="field field--fname"><label>First name</label><input class="input" name="FACULTY_FNAME" value="<?= htmlspecialchars($row['FACULTY_FNAME']); ?>" required></div>
                <div class="field field--initial"><label>Initial</label><input class="input" name="FACULTY_INITIAL" maxlength="2" value="<?= htmlspecialchars($row['FACULTY_INITIAL']); ?>"></div>
                <div class="field field--lname"><label>Last name</label><input class="input" name="FACULTY_LNAME" value="<?= htmlspecialchars($row['FACULTY_LNAME']); ?>" required></div>
                <div class="field field--email"><label>Email</label><input class="input" type="email" name="FACULTY_EMAIL" value="<?= htmlspecialchars($row['FACULTY_EMAIL']); ?>" required></div>
                <div class="field field--rank">
                  <label>Rank</label>
                  <select class="input" name="RANK_ID" required>
                    <?php foreach($ranks as $r){ $sel=($r['RANK_ID']===$row['RANK_ID'])?' selected':''; echo '<option'.$sel.' value="'.$r['RANK_ID'].'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>'; } ?>
                  </select>
                </div>
                <div class="field field--dept">
                  <label>Department</label>
                  <select class="input" name="DEPT_ID" required>
                    <?php foreach($depts as $d){ $sel=($d['DEPT_ID']===$row['DEPT_ID'])?' selected':''; echo '<option'.$sel.' value="'.$d['DEPT_ID'].'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>'; } ?>
                  </select>
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

  <!-- Pagination (5/page) -->
  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php
        $base  = app_url('/admin/crud/faculty.php');
        $qs    = 'q='.urlencode($q).'&rank='.urlencode($rank).'&dept='.urlencode($dept).'&sort='.urlencode($sort);
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

<!-- Reusable modal -->
<div class="modal" id="modal" hidden>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal__head">
      <div id="modal-title">Edit</div>
      <button type="button" class="modal__close" aria-label="Close" id="modal-close">×</button>
    </div>
    <form id="modal-form" method="post">
      <!-- dynamic fields injected here -->
      <div class="modal__actions">
        <button class="btn btn-primary" type="submit">Save</button>
        <button class="btn btn-ghost" type="button" id="modal-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const csrf = <?= json_encode($CSRF) ?>;
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

    // inject hidden
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
