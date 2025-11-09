<?php
$pageTitle = 'Faculty';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../partials/header.php';

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
  // detailed view
  $id = (int) $_GET['id'];

  // faculty profile with rank + dept
  $sql = "SELECT f.*, r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION AS DEPARTMENT, d.DEPT_CLASSIFICATION
          FROM FACULTY f
          JOIN `RANK` r ON f.RANK_ID = r.RANK_ID
          JOIN DEPARTMENT d ON f.DEPT_ID = d.DEPT_ID
          WHERE f.FACULTY_ID = ?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$id]);
  $faculty = $stmt->fetch();

  if (!$faculty){ echo "<div class='container'><p>Not found.</p></div>"; require_once __DIR__ . '/../partials/footer.php'; exit; }

  // research list
  $sql2 = "SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS, re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE, a.ROLE_ID
           FROM ASSIGNMENT a
           JOIN RESEARCH re ON a.RESEARCH_ID = re.RESEARCH_ID
           WHERE a.FACULTY_ID = ?
           ORDER BY re.RESEARCH_STARTDATE DESC";
  $stmt2 = $pdo->prepare($sql2);
  $stmt2->execute([$id]);
  $projects = $stmt2->fetchAll();
  ?>
  <section class="container">
    <div class="detail">
      <div style="display:flex;gap:16px;align-items:center">
        <img class="avatar" src="/assets/placeholder-avatar.png" alt="" style="width:96px;height:96px;border-radius:999px;border:2px solid var(--border);object-fit:cover">
        <div>
          <h2 style="margin:0"><?php echo htmlspecialchars($faculty['FACULTY_FNAME'].' '.$faculty['FACULTY_LNAME']); ?></h2>
          <div class="meta">
            <?php echo htmlspecialchars($faculty['RANK_DESCRIPTION']); ?> ·
            <?php echo htmlspecialchars($faculty['DEPARTMENT']); ?> (<?php echo htmlspecialchars($faculty['DEPT_CLASSIFICATION']); ?>)
          </div>
          <div class="meta"><?php echo htmlspecialchars($faculty['FACULTY_EMAIL']); ?></div>
        </div>
      </div>

      <h3 style="margin-top:18px">Research Projects</h3>
      <?php if(!$projects){ echo "<p class='muted'>No research assignments found.</p>"; } ?>
      <div class="cards">
        <?php foreach ($projects as $p): ?>
          <div class="card" style="grid-column: span 12">
            <div>
              <h3><?php echo htmlspecialchars($p['RESEARCH_TITLE']); ?></h3>
              <div class="meta">
                Status: <span class="pill"><?php echo htmlspecialchars($p['RESEARCH_STATUS']); ?></span>
                &nbsp;·&nbsp;
                Start: <?php echo htmlspecialchars($p['RESEARCH_STARTDATE']); ?>
                <?php if($p['RESEARCH_ENDDATE']) echo " · End: ".htmlspecialchars($p['RESEARCH_ENDDATE']); ?>
                &nbsp;·&nbsp; Role: <?php echo htmlspecialchars($p['ROLE_ID']); ?>
              </div>
            </div>
            <div class="card-actions">
              <a class="btn" href="/public/research.php?id=<?php echo (int)$p['RESEARCH_ID']; ?>">View research</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php
  require_once __DIR__ . '/../partials/footer.php';
  exit;
}

// directory view
$q    = isset($_GET['q']) ? trim($_GET['q']) : '';
$dept = isset($_GET['dept']) ? trim($_GET['dept']) : '';
$rank = isset($_GET['rank']) ? trim($_GET['rank']) : '';

$sql = "SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_LNAME, f.FACULTY_EMAIL,
               r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
        FROM FACULTY f
        JOIN `RANK` r ON f.RANK_ID = r.RANK_ID
        JOIN DEPARTMENT d ON f.DEPT_ID = d.DEPT_ID
        WHERE 1=1";

$params = [];
if ($q !== '') {
  $sql .= " AND (f.FACULTY_FNAME LIKE ? OR f.FACULTY_LNAME LIKE ? OR f.FACULTY_EMAIL LIKE ?)";
  $params[] = "%{$q}%"; $params[] = "%{$q}%"; $params[] = "%{$q}%";
}
if ($dept !== '') { $sql .= " AND f.DEPT_ID = ?"; $params[] = $dept; }
if ($rank !== '') { $sql .= " AND f.RANK_ID = ?"; $params[] = $rank; }
$sql .= " ORDER BY f.FACULTY_LNAME, f.FACULTY_FNAME LIMIT 60";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<section class="container">
  <h1>Faculty</h1>
  <form action="" method="get" class="search-panel grid">
    <div class="field" style="grid-column: span 4">
      <label for="q">Prospect name (Faculty)</label>
      <input class="input" type="text" id="q" name="q" placeholder="Enter name or email" value="<?php echo htmlspecialchars($q); ?>" />
    </div>
    <div class="field" style="grid-column: span 4">
      <label for="dept">Department</label>
      <select id="dept" name="dept">
        <option value="">All</option>
        <?php
          $opts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll();
          foreach ($opts as $o){
            $sel = ($dept===$o['DEPT_ID']) ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($o['DEPT_ID']).'"'.$sel.'>'.htmlspecialchars($o['DEPT_SPECIALIZATION']).'</option>';
          }
        ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 3">
      <label for="rank">Rank</label>
      <select id="rank" name="rank">
        <option value="">All</option>
        <?php
          $opts = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll();
          foreach ($opts as $o){
            $sel = ($rank===$o['RANK_ID']) ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($o['RANK_ID']).'"'.$sel.'>'.htmlspecialchars($o['RANK_DESCRIPTION']).'</option>';
          }
        ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 1;display:flex;align-items:flex-end">
      <button class="btn" type="submit">Search</button>
    </div>
  </form>

  <div class="meta" style="margin-top:12px">
    <?php echo count($rows); ?> faculty match your criteria
  </div>

  <div class="cards" style="margin-top:16px">
    <?php foreach ($rows as $f): ?>
      <div class="card">
        <img class="avatar" src="/assets/placeholder-avatar.png" alt="">
        <div>
          <h3><?php echo htmlspecialchars($f['FACULTY_FNAME'].' '.$f['FACULTY_LNAME']); ?></h3>
          <div class="meta">
            <?php echo htmlspecialchars($f['RANK_DESCRIPTION']); ?> ·
            <?php echo htmlspecialchars($f['DEPT_SPECIALIZATION']); ?>
          </div>
          <div class="meta"><?php echo htmlspecialchars($f['FACULTY_EMAIL']); ?></div>
        </div>
        <div class="card-actions">
          <a class="btn" href="/public/faculty.php?id=<?php echo (int)$f['FACULTY_ID']; ?>">View profile</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
