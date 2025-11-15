<?php
$pageTitle = 'Assignments (Admin)';

require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

/* ------- Local validators (dates) ------- */
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

/* ------- Auth & CSRF ------- */
if (!is_admin()) { redirect_to('/admin/login.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

/* ------------------------- Actions ------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_int($_POST['FACULTY_ID'] ?? '') || !v_int($_POST['RESEARCH_ID'] ?? '')) guardFail('Missing foreign keys');
  if (!v_varchar($_POST['ROLE_ID'] ?? '', 2)) guardFail('Invalid role');
  if (!v_date($_POST['DATE_ASSIGNED'] ?? '')) guardFail('Invalid date');

  $pdo->prepare("INSERT INTO ASSIGNMENT (FACULTY_ID, RESEARCH_ID, ROLE_ID, DATE_ASSIGNED) VALUES (?,?,?,?)")
      ->execute([$_POST['FACULTY_ID'], $_POST['RESEARCH_ID'], $_POST['ROLE_ID'], $_POST['DATE_ASSIGNED']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,LAST_INSERT_ID())")
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

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'UPDATE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

if ($action === 'delete') {
  if (!v_int($_POST['ASSIGNMENT_ID'] ?? '')) guardFail('Missing ID');

  $pdo->prepare("DELETE FROM ASSIGNMENT WHERE ASSIGNMENT_ID=?")->execute([$_POST['ASSIGNMENT_ID']]);

  $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
      ->execute([$_SESSION['admin_user'], 'DELETE', 'ASSIGNMENT', $_POST['ASSIGNMENT_ID']]);

  redirect_to('/admin/crud/assignment.php?ok=1');
}

/* ------------------------- Lookups ------------------------- */
$fac   = $pdo->query("SELECT FACULTY_ID, FACULTY_LNAME, FACULTY_FNAME FROM FACULTY ORDER BY FACULTY_LNAME, FACULTY_FNAME")->fetchAll(PDO::FETCH_ASSOC);
$res   = $pdo->query("SELECT RESEARCH_ID, RESEARCH_TITLE FROM RESEARCH ORDER BY RESEARCH_STARTDATE DESC")->fetchAll(PDO::FETCH_ASSOC);
$roles = $pdo->query("SELECT ROLE_ID, ROLE_DESCRIPTION FROM ROLE ORDER BY ROLE_ID")->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------- Filters + Sorting + Pagination ------------------------- */
$q      = trim($_GET['q'] ?? '');
$sort   = $_GET['sort'] ?? 'id_desc'; // id_desc|id_asc|date_desc|date_asc|faculty_asc|faculty_desc|research_asc|research_desc
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 5;
$offset = ($page - 1) * $per;

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
$orderSql = $sortMap[$sort] ?? $sortMap['id_desc'];

$baseSql = "FROM ASSIGNMENT a
            JOIN FACULTY f ON a.FACULTY_ID = f.FACULTY_ID
            JOIN RESEARCH r ON a.RESEARCH_ID = r.RESEARCH_ID
            WHERE 1=1";
$params = [];
if ($q !== '') {
  $baseSql .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR r.RESEARCH_TITLE LIKE ?)";
  $params = ["%$q%", "%$q%", "%$q%"];
}

// total
$stmtCount = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

// rows
$sql = "SELECT a.ASSIGNMENT_ID, a.FACULTY_ID, a.RESEARCH_ID, a.ROLE_ID, a.DATE_ASSIGNED,
               CONCAT(f.FACULTY_LNAME, ', ', f.FACULTY_FNAME) AS FACULTY_NAME,
               r.RESEARCH_TITLE
        $baseSql
        ORDER BY $orderSql
        LIMIT $per OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = max(1, (int)ceil($total / $per));

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
  display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px;
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
  <h1 style="margin-bottom:8px;">Assignments</h1>
  <p class="muted" style="margin-bottom:10px;">Manage who is assigned to which research and in what role. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=ASSIGNMENT">Export CSV</a>
  </div>

  <!-- Filter / Sort -->
  <form method="get" class="grid filter-bar" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 5">
      <label>Search (faculty or research title)</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Order</label>
      <select class="input" name="sort">
        <option value="id_desc"       <?= $sort==='id_desc'?'selected':''; ?>>ID (Newest First)</option>
        <option value="id_asc"        <?= $sort==='id_asc'?'selected':''; ?>>ID (Oldest First)</option>
        <option value="date_desc"     <?= $sort==='date_desc'?'selected':''; ?>>Date Assigned (Newest)</option>
        <option value="date_asc"      <?= $sort==='date_asc'?'selected':''; ?>>Date Assigned (Oldest)</option>
        <option value="faculty_asc"   <?= $sort==='faculty_asc'?'selected':''; ?>>Faculty (A–Z)</option>
        <option value="faculty_desc"  <?= $sort==='faculty_desc'?'selected':''; ?>>Faculty (Z–A)</option>
        <option value="research_asc"  <?= $sort==='research_asc'?'selected':''; ?>>Research (A–Z)</option>
        <option value="research_desc" <?= $sort==='research_desc'?'selected':''; ?>>Research (Z–A)</option>
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
    <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 4">
      <label>Faculty</label>
      <select class="input" name="FACULTY_ID" required>
        <?php foreach ($fac as $f): ?>
          <option value="<?= (int)$f['FACULTY_ID']; ?>">
            <?= htmlspecialchars($f['FACULTY_LNAME'].', '.$f['FACULTY_FNAME']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 6">
      <label>Research</label>
      <select class="input" name="RESEARCH_ID" required>
        <?php foreach ($res as $r): ?>
          <option value="<?= (int)$r['RESEARCH_ID']; ?>"><?= htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 2">
      <label>Role</label>
      <select class="input" name="ROLE_ID" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= htmlspecialchars($r['ROLE_ID'], ENT_QUOTES); ?>">
            <?= htmlspecialchars($r['ROLE_ID']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="grid-column: span 3">
      <label>Date Assigned</label>
      <input class="input" type="date" name="DATE_ASSIGNED" required>
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
        <th>Faculty</th>
        <th>Research</th>
        <th>Role</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= (int)$row['ASSIGNMENT_ID']; ?></td>
          <td><?= htmlspecialchars($row['FACULTY_NAME']); ?></td>
          <td><?= htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
          <td><?= htmlspecialchars($row['ROLE_ID']); ?></td>
          <td><?= htmlspecialchars($row['DATE_ASSIGNED']); ?></td>
          <td class="actions-cell">
            <button
              type="button"
              class="btn small js-edit"
              data-id="<?= (int)$row['ASSIGNMENT_ID']; ?>"
              data-faculty="<?= (int)$row['FACULTY_ID']; ?>"
              data-research="<?= (int)$row['RESEARCH_ID']; ?>"
              data-role="<?= htmlspecialchars($row['ROLE_ID'], ENT_QUOTES); ?>"
              data-date="<?= htmlspecialchars($row['DATE_ASSIGNED'], ENT_QUOTES); ?>"
            >Edit</button>

            <form method="post" onsubmit="return confirm('Delete this record?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="ASSIGNMENT_ID" value="<?= (int)$row['ASSIGNMENT_ID']; ?>">
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
      // keep q/sort when paging
      $qs = function($p) use ($q, $sort) {
        $parts = ['page='.$p];
        if ($q !== '')   $parts[]='q='.rawurlencode($q);
        if ($sort !== '')$parts[]='sort='.rawurlencode($sort);
        return implode('&',$parts);
      };
      $base = app_url('/admin/crud/assignment.php');
    ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(max(1,$page-1)); ?>">&#x276E;</a>
    <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a class="page-btn <?= $i===$page?'active':''; ?>" href="<?= $base.'?'.$qs($i); ?>"><?= $i; ?></a>
    <?php endfor; ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(min($totalPages,$page+1)); ?>">&#x276F;</a>
  </div>
</section>

<!-- --------- Modal HTML --------- -->
<div class="admin-modal" id="assignModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="assignModalTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="assignModalTitle">Edit Assignment</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="ASSIGNMENT_ID" id="m_id">

      <div class="modal-grid">
        <div class="field">
          <label for="m_faculty">Faculty</label>
          <select class="input" id="m_faculty" name="FACULTY_ID" required>
            <?php foreach ($fac as $f): ?>
              <option value="<?= (int)$f['FACULTY_ID']; ?>">
                <?= htmlspecialchars($f['FACULTY_LNAME'].', '.$f['FACULTY_FNAME']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_research">Research</label>
          <select class="input" id="m_research" name="RESEARCH_ID" required>
            <?php foreach ($res as $r): ?>
              <option value="<?= (int)$r['RESEARCH_ID']; ?>"><?= htmlspecialchars($r['RESEARCH_TITLE']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_role">Role</label>
          <select class="input" id="m_role" name="ROLE_ID" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= htmlspecialchars($r['ROLE_ID'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($r['ROLE_ID']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_date">Date Assigned</label>
          <input class="input" id="m_date" type="date" name="DATE_ASSIGNED" required>
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
  const modal = document.getElementById('assignModal');
  const form  = modal.querySelector('form');
  const idI   = document.getElementById('m_id');
  const facI  = document.getElementById('m_faculty');
  const resI  = document.getElementById('m_research');
  const roleI = document.getElementById('m_role');
  const dateI = document.getElementById('m_date');

  function open(payload){
    idI.value   = payload.id;
    facI.value  = payload.faculty || '';
    resI.value  = payload.research || '';
    roleI.value = payload.role || '';
    dateI.value = payload.date || '';
    modal.hidden = false;
  }
  function close(){ modal.hidden = true; }

  document.querySelectorAll('.js-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      open({
        id: btn.dataset.id,
        faculty: btn.dataset.faculty,
        research: btn.dataset.research,
        role: btn.dataset.role,
        date: btn.dataset.date
      });
    });
  });

  modal.addEventListener('click', e=>{ if (e.target.dataset.close) close(); });
  window.addEventListener('keydown', e=>{ if (!modal.hidden && e.key === 'Escape') close(); });
})();
</script>

<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>
