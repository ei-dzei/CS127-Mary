<?php
$pageTitle = 'Faculty (Admin)';

require_once __DIR__ . '/../../partials/init.php';
require_once __DIR__ . '/../../validators.php';

$flashError = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']); 

if (!is_admin()) { redirect_to('/admin/login.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); }

/* ------------------------- Actions ------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'create') {
  if (!v_varchar($_POST['FACULTY_FNAME'] ?? '', 50)) guardFail('Invalid first name');
  if (!v_char_nullable($_POST['FACULTY_INITIAL'] ?? '', 2)) guardFail('Invalid initial');
  if (!v_varchar($_POST['FACULTY_LNAME'] ?? '', 50)) guardFail('Invalid last name');
  if (!v_email($_POST['FACULTY_EMAIL'] ?? '')) guardFail('Invalid email');
  if (!v_enum_exists($pdo, '`RANK`', 'RANK_ID', $_POST['RANK_ID'] ?? null)) guardFail('Invalid rank');
  if (!v_enum_exists($pdo, 'DEPARTMENT', 'DEPT_ID', $_POST['DEPT_ID'] ?? null)) guardFail('Invalid department');

  $pdo->prepare("
    INSERT INTO FACULTY (FACULTY_FNAME,FACULTY_INITIAL,FACULTY_LNAME,FACULTY_EMAIL,RANK_ID,DEPT_ID)
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

// ------------------------- DELETE ACTION (UPDATED for Pop-up) -------------------------
if ($action === 'delete') {
  if (!v_int($_POST['FACULTY_ID'] ?? '')) guardFail('Missing ID');
  
  try {
    // Attempt the delete operation
    $pdo->prepare("DELETE FROM FACULTY WHERE FACULTY_ID=?")->execute([$_POST['FACULTY_ID']]);

    // If successful, log the action and set a success flash message
    $pdo->prepare("INSERT INTO AUDIT_LOG (ACTOR,ACTION_ENUM,TABLE_NAME,PK_VALUE) VALUES (?,?,?,?)")
        ->execute([$_SESSION['admin_user'], 'DELETE', 'FACULTY', $_POST['FACULTY_ID']]);
    
    // Redirect on success (with ?ok=1 flag)
    redirect_to('/admin/crud/faculty.php?ok=1'); 

  } catch (PDOException $e) {
    // Catch the database error (Foreign Key Constraint violation causes this)
    
    // Define the user-friendly error message
    $errorMessage = "Cannot delete faculty. This record is referenced by other data (e.g., courses or schedules). Please delete dependent records first.";
    
    // Set the error message into a session variable (FLASH MESSAGE)
    $_SESSION['error_message'] = $errorMessage;
    
    // Redirect back to the page to trigger the JavaScript alert
    redirect_to('/admin/crud/faculty.php');
  }
}
// ------------------------- END DELETE ACTION -------------------------

/* ------------------------- Lookups ------------------------- */
$ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll(PDO::FETCH_ASSOC);
$depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------- Filters + Sorting + Pagination ------------------------- */
$q    = trim($_GET['q'] ?? '');
$rank = trim($_GET['rank'] ?? '');
$dept = trim($_GET['dept'] ?? '');
$sort = $_GET['sort'] ?? 'name_asc'; // name_asc|name_desc|id_asc|id_desc|email_asc|email_desc|rank_asc|dept_asc
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 5;
$offset = ($page - 1) * $per;

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
$orderSql = $sortMap[$sort] ?? $sortMap['name_asc'];

$baseSql = "FROM FACULTY f
            JOIN `RANK` r ON f.RANK_ID=r.RANK_ID
            JOIN DEPARTMENT d ON f.DEPT_ID=d.DEPT_ID
            WHERE 1=1";
$params = [];
if ($q !== '') {
  $baseSql .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR f.FACULTY_EMAIL LIKE ?)";
  array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($rank !== '') { $baseSql .= " AND f.RANK_ID = ?"; $params[] = $rank; }
if ($dept !== '') { $baseSql .= " AND f.DEPT_ID = ?"; $params[] = $dept; }

$stmtCount = $pdo->prepare("SELECT COUNT(*) ".$baseSql);
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();

$sql = "SELECT f.*, r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
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
.modal-grid{ display:grid; grid-template-columns: 1fr 120px 1fr 1fr; gap:16px; }
@media (max-width: 900px){ .modal-grid{ grid-template-columns: 1fr; } }
.modal-grid .field{display:flex; flex-direction:column; gap:6px;}
.modal-grid .input, .modal-grid select{width:100%; padding:12px 14px; font-size:16px;}
.admin-modal__actions{display:flex; gap:10px; justify-content:flex-end; padding:12px 18px; border-top:1px solid #eef2f6;}
.btn.wide { min-width: 160px; }

/* Filter bar action buttons */
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
.btn-ghost{ background:#fff; color: var(--color-accent); border-color: rgba(11,83,148,.35); }
.btn-ghost:hover{ background: rgba(11,83,148,.05); }
</style>

<section class="panel fade-in crud-header-card">
  <h1 style="margin-bottom:8px;">Faculty</h1>
  <p class="muted" style="margin-bottom:10px;">Create, update, delete records. CSV import/export below.</p>

  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
    <a class="btn small" href="<?= app_url('/admin/api/export.php'); ?>?table=FACULTY">Export CSV</a>
  </div>

  <form method="get" class="grid filter-bar" style="margin-bottom:10px;">
    <div class="field" style="grid-column: span 4">
      <label>Search (name or email)</label>
      <input class="input" name="q" value="<?= htmlspecialchars($q); ?>">
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Rank</label>
      <select class="input" name="rank">
        <option value="">All</option>
        <?php foreach ($ranks as $r):
          $sel = ($rank === $r['RANK_ID']) ? ' selected' : '';
          echo '<option'.$sel.' value="'.htmlspecialchars($r['RANK_ID'], ENT_QUOTES).'">'.htmlspecialchars($r['RANK_DESCRIPTION']).'</option>';
        endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Department</label>
      <select class="input" name="dept">
        <option value="">All</option>
        <?php foreach ($depts as $d):
          $sel = ($dept === $d['DEPT_ID']) ? ' selected' : '';
          echo '<option'.$sel.' value="'.htmlspecialchars($d['DEPT_ID'], ENT_QUOTES).'">'.htmlspecialchars($d['DEPT_SPECIALIZATION']).'</option>';
        endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2">
      <label>Order</label>
      <select class="input" name="sort">
        <option value="name_asc"  <?= $sort==='name_asc'?'selected':''; ?>>Name (A–Z)</option>
        <option value="name_desc" <?= $sort==='name_desc'?'selected':''; ?>>Name (Z–A)</option>
        <option value="id_asc"    <?= $sort==='id_asc'?'selected':''; ?>>ID (Oldest First)</option>
        <option value="id_desc"   <?= $sort==='id_desc'?'selected':''; ?>>ID (Newest First)</option>
        <option value="email_asc" <?= $sort==='email_asc'?'selected':''; ?>>Email (A–Z)</option>
        <option value="email_desc"<?= $sort==='email_desc'?'selected':''; ?>>Email (Z–A)</option>
        <option value="rank_asc"  <?= $sort==='rank_asc'?'selected':''; ?>>Rank</option>
        <option value="dept_asc"  <?= $sort==='dept_asc'?'selected':''; ?>>Department</option>
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
    <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
    <input type="hidden" name="action" value="create">

    <div class="field" style="grid-column: span 3"><label>First name</label><input class="input" name="FACULTY_FNAME" required maxlength="50"></div>
    
    <div class="field" style="grid-column: span 2"><label>Initial</label><input class="input" name="FACULTY_INITIAL" maxlength="2"></div>
    
    <div class="field" style="grid-column: span 3"><label>Last name</label><input class="input" name="FACULTY_LNAME" required maxlength="50"></div>
    
    <div class="field" style="grid-column: span 4"><label>Email</label><input class="input" type="email" name="FACULTY_EMAIL" required maxlength="255"></div>
    <div class="field" style="grid-column: span 3">
      <label>Rank</label>
      <select class="input" name="RANK_ID" required>
        <?php foreach ($ranks as $r): ?>
          <option value="<?= htmlspecialchars($r['RANK_ID'], ENT_QUOTES); ?>"><?= htmlspecialchars($r['RANK_DESCRIPTION']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 3">
      <label>Department</label>
      <select class="input" name="DEPT_ID" required>
        <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d['DEPT_ID'], ENT_QUOTES); ?>"><?= htmlspecialchars($d['DEPT_SPECIALIZATION']); ?></option>
        <?php endforeach; ?>
      </select>
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
        <th>ID</th><th>Name</th><th>Email</th><th>Rank</th><th>Dept</th><th>Actions</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= (int)$row['FACULTY_ID']; ?></td>
          <td><?= htmlspecialchars($row['FACULTY_LNAME'].', '.$row['FACULTY_FNAME'].($row['FACULTY_INITIAL']?' '.$row['FACULTY_INITIAL']:'')); ?></td>
          <td><?= htmlspecialchars($row['FACULTY_EMAIL']); ?></td>
          <td><?= htmlspecialchars($row['RANK_DESCRIPTION']); ?></td>
          <td><?= htmlspecialchars($row['DEPT_SPECIALIZATION']); ?></td>
          <td class="actions-cell">
            <button
              type="button"
              class="btn small js-edit"
              data-id="<?= (int)$row['FACULTY_ID']; ?>"
              data-fname="<?= htmlspecialchars($row['FACULTY_FNAME'], ENT_QUOTES); ?>"
              data-initial="<?= htmlspecialchars($row['FACULTY_INITIAL'], ENT_QUOTES); ?>"
              data-lname="<?= htmlspecialchars($row['FACULTY_LNAME'], ENT_QUOTES); ?>"
              data-email="<?= htmlspecialchars($row['FACULTY_EMAIL'], ENT_QUOTES); ?>"
              data-rank="<?= htmlspecialchars($row['RANK_ID'], ENT_QUOTES); ?>"
              data-dept="<?= htmlspecialchars($row['DEPT_ID'], ENT_QUOTES); ?>"
            >Edit</button>

            <form method="post" onsubmit="return confirm('Delete faculty?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="FACULTY_ID" value="<?= (int)$row['FACULTY_ID']; ?>">
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

  <div class="pagination">
    <?php
      $qs = function($p) use ($q, $rank, $dept, $sort) {
        $parts = ['page='.$p];
        if ($q   !== '') $parts[]='q='.rawurlencode($q);
        if ($rank!== '') $parts[]='rank='.rawurlencode($rank);
        if ($dept!== '') $parts[]='dept='.rawurlencode($dept);
        if ($sort!== '') $parts[]='sort='.rawurlencode($sort);
        return implode('&',$parts);
      };
      $base = app_url('/admin/crud/faculty.php');
    ?>
    <?php if ($page > 1): ?>
      <a class="page-btn" href="<?= $base.'?'.$qs(max(1,$page-1)); ?>" title = "Previous Page">&#x276E;</a>
    <?php endif; ?>

    <?php
      $maxPage = 5;
      $start = max(1, $page - floor($maxPage / 2));
      $end = min($totalPages, $start + $maxPage - 1);

      if ($end - $start < $maxPage - 1) {
        $start = max(1, $end - $maxPage + 1);
      } 
    ?>
    <?php if ($start > 1): ?>
      <a href="<?= $base.'?'.$qs(1); ?>" class="page-btn" >1</a>
      <?php if ($start > 3): ?>
        <a href="<?= $base.'?'.$qs(max(1,$page - 5)) ?>" class="page-btn" title="Jump backward 5 pages">...</a>        
      <?php endif; ?>
      <?php if ($start == 3): ?>
              <a href="<?= $base.'?'.$qs(2); ?>" class="page-btn" >2</a>       
      <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start;$i <= $end;$i++): ?>
      <a class="page-btn <?= $i== $page?'active':''; ?>" href="<?= $base.'?'.$qs($i); ?>"><?= $i; ?></a>
    <?php endfor; ?>
    
    <?php if ($end < $totalPages): ?>
      <?php if ($end == $totalPages - 2):?>
        <a href="<?= $base.'?'.$qs($totalPages - 1); ?>" class="page-btn" > <?=$totalPages - 1?></a>
      <?php endif; ?>
      <?php if ($end < $totalPages - 2): ?>
        <a href="<?= $base.'?'.$qs(min($totalPages,$page + 5)); ?>"class="page-btn" title="Jump forward 5 pages">...</a>
      <?php endif; ?>
        <a href="<?= $base.'?'.$qs($totalPages); ?>" class="page-btn" > <?=$totalPages?></a>
    <?php endif; ?>

    <?php  if ($page < $totalPages): ?>
    <a class="page-btn" href="<?= $base.'?'.$qs(min($totalPages,$page+1)); ?>" title = "Next Page">&#x276F;</a>
    <?php  endif;?>
  </div>
</section>

<div class="admin-modal" id="facultyModal" hidden>
  <div class="admin-modal__backdrop" data-close="1"></div>
  <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="facultyModalTitle">
    <div class="admin-modal__head">
      <h3 class="admin-modal__title" id="facultyModalTitle">Edit Faculty</h3>
      <button class="admin-modal__close" type="button" data-close="1">✕</button>
    </div>
    <form class="admin-modal__body" method="post">
      <input type="hidden" name="csrf" value="<?= $CSRF; ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="FACULTY_ID" id="m_id">

      <div class="modal-grid">
        <div class="field">
          <label for="m_fname">First name</label>
          <input class="input" id="m_fname" name="FACULTY_FNAME" required maxlength="50">
        </div>
        <div class="field">
          <label for="m_initial">Initial</label>
          <input class="input" id="m_initial" name="FACULTY_INITIAL" maxlength="2">
        </div>
        <div class="field">
          <label for="m_lname">Last name</label>
          <input class="input" id="m_lname" name="FACULTY_LNAME" required maxlength="50">
        </div>
        <div class="field">
          <label for="m_email">Email</label>
          <input class="input" id="m_email" type="email" name="FACULTY_EMAIL" required maxlength="255">
        </div>
        <div class="field">
          <label for="m_rank">Rank</label>
          <select class="input" id="m_rank" name="RANK_ID" required>
            <?php foreach ($ranks as $r): ?>
              <option value="<?= htmlspecialchars($r['RANK_ID'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($r['RANK_DESCRIPTION']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="m_dept">Department</label>
          <select class="input" id="m_dept" name="DEPT_ID" required>
            <?php foreach ($depts as $d): ?>
              <option value="<?= htmlspecialchars($d['DEPT_ID'], ENT_QUOTES); ?>">
                <?= htmlspecialchars($d['DEPT_SPECIALIZATION']); ?>
              </option>
            <?php endforeach; ?>
          </select>
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
  const modal = document.getElementById('facultyModal');
  const form  = modal.querySelector('form');
  const idI   = document.getElementById('m_id');
  const fnI   = document.getElementById('m_fname');
  const inI   = document.getElementById('m_initial');
  const lnI   = document.getElementById('m_lname');
  const emI   = document.getElementById('m_email');
  const rkI   = document.getElementById('m_rank');
  const dpI   = document.getElementById('m_dept');

  function open(payload){
    idI.value = payload.id;
    fnI.value = payload.fname || '';
    inI.value = payload.initial || '';
    lnI.value = payload.lname || '';
    emI.value = payload.email || '';
    rkI.value = payload.rank || '';
    dpI.value = payload.dept || '';

    form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
    form.querySelectorAll('.error-message').forEach(el => el.remove());
    
    modal.hidden = false;
  }
  function close(){ modal.hidden = true; }

  document.querySelectorAll('.js-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      open({
        id: btn.dataset.id,
        fname: btn.dataset.fname,
        initial: btn.dataset.initial,
        lname: btn.dataset.lname,
        email: btn.dataset.email,
        rank: btn.dataset.rank,
        dept: btn.dataset.dept
      });
    });
  });

  modal.addEventListener('click', e=>{ if (e.target.dataset.close) close(); });
  window.addEventListener('keydown', e=>{ if (!modal.hidden && e.key === 'Escape') close(); });
})();
</script>

<script>
const flashError = '<?= htmlspecialchars(addslashes($flashError ?? '')); ?>'; 

if (flashError) {
    // Show the browser pop-up alert with the error message
    alert('Error: ' + flashError);
}
</script>
<?php require_once __DIR__ . '/../../partials/site_footer.php'; ?>