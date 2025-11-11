<?php
$pageTitle = 'Assignments (Admin)';

// Core (sessions, DB, csrf, helpers)
require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

if (!function_exists('v_date')) {
  function v_date($s): bool {
    if (!is_string($s) || $s === '') return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
  }
}
if (!function_exists('v_date_nullable')) {
  function v_date_nullable($s): bool {
    if ($s === null || $s === '') return true;
    return v_date($s);
  }
}

// Auth & CSRF
if (!is_admin()) { redirect_to('/admin/login.php'); }
csrf_check();

/* ---------- Actions ---------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_int($_POST['FACULTY_ID'] ?? '') || !v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_varchar($_POST['ROLE_ID'] ?? '', 2)) guardFail('Invalid role');
  if (!v_date($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date');

  $pdo->prepare("INSERT INTO ASSIGNMENT (FACULTY_ID, RESEARCH_ID, ROLE_ID, DATE_ASSIGNED) VALUES (?,?,?,?)")
      ->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
      ->execute([$_SESSION['admin_user'], 'CREATE', 'ASSIGNMENT']);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

if ($action === 'update') {
  if (!v_int($_POST['ASSIGNMENT_ID'] ?? '')) guardFail('Missing ID');
  if (!v_int($_POST['FACULTY_ID'] ?? '') || !v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_varchar($_POST['ROLE_ID'] ?? '', 2)) guardFail('Invalid role');
  if (!v_date($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date');

  $pdo->prepare("UPDATE ASSIGNMENT SET FACULTY_ID=?, RESEARCH_ID=?, ROLE_ID=?, DATE_ASSIGNED=? WHERE ASSIGNMENT_ID=?")
      ->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED'], $_POST['ASSIGNMENT_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['ASSIGNMENT_ID'] ?? '')) guardFail('Missing ID');

  $pdo->prepare("DELETE FROM ASSIGNMENT WHERE ASSIGNMENT_ID=?")->execute([$_POST['ASSIGNMENT_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

/* ---------- Lookups ---------- */
$fac   = $pdo->query("SELECT FACULTY_ID, CONCAT(FACULTY_LNAME, ', ', FACULTY_FNAME) AS name FROM FACULTY ORDER BY FACULTY_LNAME")->fetchAll(PDO::FETCH_ASSOC);
$res   = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")->fetchAll(PDO::FETCH_ASSOC);
$roles = $pdo->query("SELECT ROLE_ID, ROLE_DESCRIPTION FROM ROLE ORDER BY ROLE_ID")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- Filters, Sorting, Pagination ---------- */
$q    = trim($_GET['q'] ?? '');
$sort = trim($_GET['sort'] ?? 'id_desc');
$page = max(1, (int)($_GET['page'] ?? 1));
$PAGE_SIZE = 5;
$offset = ($page - 1) * $PAGE_SIZE;

$sortMap = [
  'id_desc'        => 'a.ASSIGNMENT_ID DESC',
  'id_asc'         => 'a.ASSIGNMENT_ID ASC',
  'date_desc'      => 'a.DATE_ASSIGNED DESC, a.ASSIGNMENT_ID DESC',
  'date_asc'       => 'a.DATE_ASSIGNED ASC, a.ASSIGNMENT_ID ASC',
  'faculty_asc'    => 'f.FACULTY_LNAME ASC, f.FACULTY_FNAME ASC, a.ASSIGNMENT_ID DESC',
  'faculty_desc'   => 'f.FACULTY_LNAME DESC, f.FACULTY_FNAME DESC, a.ASSIGNMENT_ID DESC',
  'research_asc'   => 'r.RESEARCH_TITLE ASC, a.ASSIGNMENT_ID DESC',
  'research_desc'  => 'r.RESEARCH_TITLE DESC, a.ASSIGNMENT_ID DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['id_desc'];

$countSql = "SELECT COUNT(*) FROM ASSIGNMENT a
             JOIN FACULTY f ON a.FACULTY_ID = f.FACULTY_ID
             JOIN RESEARCH r ON a.RESEARCH_ID = r.RESEARCH_ID
             WHERE 1=1";
$params = [];
if ($q !== '') {
  $countSql .= " AND (f.FACULTY_LNAME LIKE ? OR r.RESEARCH_TITLE LIKE ?)";
  $params = ["%$q%", "%$q%"];
}
$stmtCnt = $pdo->prepare($countSql);
$stmtCnt->execute($params);
$total = (int)$stmtCnt->fetchColumn();
$pages = max(1, (int)ceil($total / $PAGE_SIZE));

$sql = "SELECT a.*, CONCAT(f.FACULTY_LNAME, ', ', f.FACULTY_FNAME) AS FACULTY_NAME, r.RESEARCH_TITLE
        FROM ASSIGNMENT a
        JOIN FACULTY f ON a.FACULTY_ID = f.FACULTY_ID
        JOIN RESEARCH r ON a.RESEARCH_ID = r.RESEARCH_ID
        WHERE 1=1";
if ($q !== '') {
  $sql .= " AND (f.FACULTY_LNAME LIKE ? OR r.RESEARCH_TITLE LIKE ?)";
}
$sql .= " ORDER BY $orderBy LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
if ($params) { // bind search params first
  $i = 1;
  foreach ($params as $p) { $stmt->bindValue($i++, $p, PDO::PARAM_STR); }
}
$stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// After handlers, render
require_once __DIR__ . '/../../partials/site_header.php';
$CSRF = csrf_token();
?>

<style>
/* ===== Uniform CRUD header/form cards ===== */
.crud-header-card, .crud-form-card { background:#fff; }

/* ===== Buttons parity (Filter / Clear) ===== */
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

/* ===== Actions cell matches ===== */
.actions-cell { display:flex; flex-wrap:wrap; align-items:center; gap:8px 10px; white-space:normal; min-width:340px; }
.actions-cell .input, .actions-cell select{ max-width:220px; }

/* ===== Pager pills ===== */
.pager{ display:flex; gap:8px; align-items:center; margin-top:10px; }
.pager a, .pager span{
  display:inline-flex; min-width:32px; height:32px; padding:0 10px;
  border:1px solid #d7e1ef; border-radius:18px; align-items:center; justify-content:center;
  text-decoration:none; color:#234b7a; background:#fff;
}
.pager .active{ background:#234b7a; color:#fff; border-color:#234b7a; }
.pager .disabled{ opacity:.5; pointer-events:none; }

/* ===== Modal ===== */
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
  #modal-form .field--faculty  { grid-column: span 6; }
  #modal-form .field--research { grid-column: span 6; }
  #modal-form .field--role     { grid-column: span 4; }
  #modal-form .field--date     { grid-column: span 4; }
}
</style>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Assignments</h1>
  <p class="muted" style="margin-bottom:8px;">Manage who is assigned to which research and in what role. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:8px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=ASSIGNMENT">Export CSV</a>
    <form method="post" action="<?= app_url('/admin/api/import.php'); ?>" enctype="multipart/form-data" style="display:inline-flex; gap:6px;">
      <input type="hidden" name="table" value="ASSIGNMENT">
      <input class="input" type="file" name="file" accept=".csv" required>
      <button class="btn small">Import CSV</button>
    </form>
  </div>

  <form method="get" class="grid" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 7">
      <label>Search (faculty last name or research title)</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>" />
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Order</label>
      <select class="input" name="sort">
        <?php
          $opts = [
            'id_desc'       => 'ID (newest first)',
            'id_asc'        => 'ID (oldest first)',
            'date_desc'     => 'Date Assigned (newest first)',
            'date_asc'      => 'Date Assigned (oldest first)',
            'faculty_asc'   => 'Faculty (A–Z)',
            'faculty_desc'  => 'Faculty (Z–A)',
            'research_asc'  => 'Research (A–Z)',
            'research_desc' => 'Research (Z–A)',
          ];
          foreach ($opts as $val=>$label) {
            $sel = $sort === $val ? ' selected' : '';
            echo "<option value=\"$val\"$sel>".htmlspecialchars($label)."</option>";
          }
        ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2; display:flex; align-items:flex-end; gap:10px">
      <button class="btn-action btn-primary" type="submit">Filter</button>
      <a class="btn-action btn-ghost" href="<?= app_url('/admin/crud/assignment.php'); ?>">Clear</a>
    </div>
  </form>
</section>

<section class="panel crud-form-card" style="margin-bottom:16px;">
  <h3 style="margin-top:0">Create Assignment</h3>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo $CSRF; ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 4">
      <label>Faculty</label>
      <select class="input" name="FACULTY_ID" required>
        <?php foreach($fac as $f){ echo '<option value="'.$f['FACULTY_ID'].'">'.htmlspecialchars($f['name']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach($res as $r){ echo '<option value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 2">
      <label>Role</label>
      <select class="input" name="ROLE_ID" required>
        <?php foreach($roles as $r){ echo '<option value="'.$r['ROLE_ID'].'">'.htmlspecialchars($r['ROLE_ID']).'</option>'; } ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Date Assigned</label>
      <input class="input" type="date" name="DATE_ASSIGNED" required>
    </div>

    <div class="field" style="grid-column: span 2; display:flex; align-items:flex-end">
      <button class="btn" type="submit" style="width:100%;">Add</button>
    </div>
  </form>
</section>

<section class="panel">
  <h3 style="margin-top:0">Records</h3>
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>ID</th><th>Faculty</th><th>Research</th><th>Role</th><th>Date</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $row): ?>
        <tr>
          <td><?php echo (int)$row['ASSIGNMENT_ID']; ?></td>
          <td><?php echo htmlspecialchars($row['FACULTY_NAME']); ?></td>
          <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
          <td><?php echo htmlspecialchars($row['ROLE_ID']); ?></td>
          <td><?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?></td>
          <td class="actions-cell">
            <!-- inline quick edit -->
            <form method="post" style="display:inline-flex; gap:6px; align-items:center">
              <input type="hidden" name="csrf" value="<?php echo $CSRF; ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">

              <select name="ROLE_ID" class="input" style="width:120px">
                <?php foreach($roles as $r){ $sel=($r['ROLE_ID']===$row['ROLE_ID'])?' selected':''; echo '<option'.$sel.' value="'.$r['ROLE_ID'].'">'.$r['ROLE_ID'].'</option>'; } ?>
              </select>

              <input type="date" name="DATE_ASSIGNED" class="input" style="width:170px" value="<?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?>">

              <input type="hidden" name="FACULTY_ID" value="<?php echo (int)$row['FACULTY_ID']; ?>">
              <input type="hidden" name="RESEARCH_ID" value="<?php echo (int)$row['RESEARCH_ID']; ?>">

              <button class="btn small">Save</button>
            </form>

            <!-- modal edit -->
            <button class="btn small"
                    data-modal="edit" data-title="Edit Assignment"
                    data-template="#tpl-edit-<?php echo (int)$row['ASSIGNMENT_ID'];?>"
                    data-action="update"
                    data-hidden-ASSIGNMENT_ID="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">
              Edit
            </button>

            <!-- delete -->
            <form method="post" onsubmit="return confirm('Delete this record?')" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo $CSRF; ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?php echo (int)$row['ASSIGNMENT_ID']; ?>">
              <button class="btn small" style="background:#b91c1c;border-color:#b91c1c">Delete</button>
            </form>

            <!-- modal template -->
            <template id="tpl-edit-<?php echo (int)$row['ASSIGNMENT_ID'];?>">
              <div class="grid">
                <div class="field field--faculty">
                  <label>Faculty</label>
                  <select class="input" name="FACULTY_ID" required>
                    <?php foreach($fac as $f){ $sel=($f['FACULTY_ID']===$row['FACULTY_ID'])?' selected':''; echo '<option'.$sel.' value="'.$f['FACULTY_ID'].'">'.htmlspecialchars($f['name']).'</option>'; } ?>
                  </select>
                </div>
                <div class="field field--research">
                  <label>Research</label>
                  <select class="input" name="RESEARCH_ID" required>
                    <?php foreach($res as $r){ $sel=($r['RESEARCH_ID']===$row['RESEARCH_ID'])?' selected':''; echo '<option'.$sel.' value="'.$r['RESEARCH_ID'].'">'.htmlspecialchars($r['RESEARCH_TITLE']).'</option>'; } ?>
                  </select>
                </div>
                <div class="field field--role">
                  <label>Role</label>
                  <select class="input" name="ROLE_ID" required>
                    <?php foreach($roles as $r){ $sel=($r['ROLE_ID']===$row['ROLE_ID'])?' selected':''; echo '<option'.$sel.' value="'.$r['ROLE_ID'].'">'.$r['ROLE_ID'].'</option>'; } ?>
                  </select>
                </div>
                <div class="field field--date">
                  <label>Date Assigned</label>
                  <input class="input" type="date" name="DATE_ASSIGNED" value="<?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?>" required>
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

  <!-- Pagination (5 per page) -->
  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php
        $base  = app_url('/admin/crud/assignment.php');
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

<!-- Reusable Modal -->
<div class="modal" id="modal" hidden>
  <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal__head">
      <div id="modal-title">Edit</div>
      <button type="button" class="modal__close" aria-label="Close" id="modal-close">×</button>
    </div>
    <form id="modal-form" method="post">
      <!-- dynamic fields injected before actions -->
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
    // remove previous content except actions row
    form.querySelectorAll(':scope > :not(.modal__actions)').forEach(n => n.remove());
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    form.insertBefore(wrap.firstElementChild, form.querySelector('.modal__actions'));

    // inject hidden fields
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
    const tplSel = btn.getAttribute('data-template');
    const tpl = document.querySelector(tplSel);
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
