<?php
$pageTitle = 'Faculty';
require_once __DIR__ . '/../partials/site_header.php';

/* --- Lookups for filters --- */
$ranks = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL")->fetchAll();
$depts = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION")->fetchAll();

/* --- Detail view (if ?id=) --- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
  $stmt = $pdo->prepare("
    SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME, f.FACULTY_EMAIL,
           f.RANK_ID, r.RANK_DESCRIPTION,
           f.DEPT_ID, d.DEPT_SPECIALIZATION AS DEPARTMENT, d.DEPT_CLASSIFICATION
    FROM FACULTY f
    JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
    JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
    WHERE f.FACULTY_ID=?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $faculty = $stmt->fetch();

  if ($faculty) {
    // Research list for this faculty
    $rs = $pdo->prepare("
      SELECT re.RESEARCH_ID, re.RESEARCH_TITLE, re.RESEARCH_STATUS, re.RESEARCH_STARTDATE, re.RESEARCH_ENDDATE,
             a.ROLE_ID
      FROM ASSIGNMENT a
      JOIN RESEARCH re ON re.RESEARCH_ID = a.RESEARCH_ID
      WHERE a.FACULTY_ID=?
      ORDER BY re.RESEARCH_STARTDATE DESC
    ");
    $rs->execute([$id]);
    $projects = $rs->fetchAll();
  }
  ?>
  <section class="panel fade-in">
    <?php if (!$faculty): ?>
      <h1>Faculty</h1>
      <p class="muted">Record not found.</p>
      <p><a class="btn small" href="/public/faculty.php">Back to list</a></p>
    <?php else: ?>
      <a class="btn small" href="/public/faculty.php" style="float:right;margin-top:-4px;">← Back</a>
      <h1 style="margin-bottom:6px">
        <?php
          echo htmlspecialchars($faculty['FACULTY_LNAME'].', '.$faculty['FACULTY_FNAME']);
          if (!empty($faculty['FACULTY_INITIAL'])) echo ' '.htmlspecialchars($faculty['FACULTY_INITIAL']);
        ?>
      </h1>
      <div class="muted" style="margin-bottom:10px">
        <?php echo htmlspecialchars($faculty['RANK_DESCRIPTION']); ?> ·
        <?php echo htmlspecialchars($faculty['DEPARTMENT']); ?> (<?php echo htmlspecialchars($faculty['DEPT_CLASSIFICATION']); ?>)
      </div>
      <div class="panel" style="background:#fff;">
        <div class="grid">
          <div class="field" style="grid-column:span 6">
            <label>Email</label>
            <input class="input" value="<?php echo htmlspecialchars($faculty['FACULTY_EMAIL']); ?>" readonly />
          </div>
          <div class="field" style="grid-column:span 3">
            <label>Rank</label>
            <input class="input" value="<?php echo htmlspecialchars($faculty['RANK_DESCRIPTION']); ?>" readonly />
          </div>
          <div class="field" style="grid-column:span 3">
            <label>Department</label>
            <input class="input" value="<?php echo htmlspecialchars($faculty['DEPARTMENT']); ?>" readonly />
          </div>
        </div>
      </div>

      <h2 style="font-family:'Patua One',serif; margin-top:16px;">Research Projects</h2>
      <?php if (!$projects): ?>
        <div class="panel">No assignments found for this faculty.</div>
      <?php else: ?>
        <div class="grid" style="gap:12px">
          <?php foreach ($projects as $p): ?>
            <a class="panel slide-up" href="/public/research.php?id=<?php echo (int)$p['RESEARCH_ID']; ?>" style="grid-column:span 6; text-decoration:none; color:inherit;">
              <h3 style="margin-top:0"><?php echo htmlspecialchars($p['RESEARCH_TITLE']); ?></h3>
              <div class="muted" style="font-size:.95rem; margin-top:4px;">
                <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;">
                  <?php echo htmlspecialchars($p['RESEARCH_STATUS']); ?>
                </span>
                <span style="margin-left:6px;">
                  Start: <?php echo htmlspecialchars($p['RESEARCH_STARTDATE']); ?>
                  <?php if ($p['RESEARCH_ENDDATE']) echo " · End: ".htmlspecialchars($p['RESEARCH_ENDDATE']); ?>
                </span>
                <span style="margin-left:6px;">Role: <?php echo htmlspecialchars($p['ROLE_ID']); ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <?php
  require_once __DIR__ . '/../partials/site_footer.php';
  exit;
}

/* --- List view with filters --- */
$q    = trim($_GET['q'] ?? '');
$rank = trim($_GET['rank'] ?? '');
$dept = trim($_GET['dept'] ?? '');

$sql = "
  SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME, f.FACULTY_EMAIL,
         r.RANK_DESCRIPTION, d.DEPT_SPECIALIZATION
  FROM FACULTY f
  JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
  JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
  WHERE 1=1
";
$params = [];
if ($q !== '') {
  $sql .= " AND (f.FACULTY_LNAME LIKE ? OR f.FACULTY_FNAME LIKE ? OR f.FACULTY_EMAIL LIKE ?)";
  $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($rank !== '') {
  $sql .= " AND f.RANK_ID = ?";
  $params[] = $rank;
}
if ($dept !== '') {
  $sql .= " AND f.DEPT_ID = ?";
  $params[] = $dept;
}
$sql .= " ORDER BY f.FACULTY_LNAME, f.FACULTY_FNAME LIMIT 36";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Faculty</h1>
  <p class="muted" style="margin-bottom:10px;">Browse faculty or refine using search and filters.</p>

  <form method="get" class="grid" style="margin-bottom:8px;">
    <div class="field" style="grid-column:span 6">
      <label>Search (name or email)</label>
      <input class="input" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="e.g., Santos or maria@..." />
    </div>
    <div class="field" style="grid-column:span 3">
      <label>Rank</label>
      <select class="input" name="rank">
        <option value="">All</option>
        <?php foreach ($ranks as $r): ?>
          <option value="<?php echo htmlspecialchars($r['RANK_ID']); ?>"<?php if ($rank===$r['RANK_ID']) echo ' selected'; ?>>
            <?php echo htmlspecialchars($r['RANK_DESCRIPTION']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 3">
      <label>Department</label>
      <select class="input" name="dept">
        <option value="">All</option>
        <?php foreach ($depts as $d): ?>
          <option value="<?php echo htmlspecialchars($d['DEPT_ID']); ?>"<?php if ($dept===$d['DEPT_ID']) echo ' selected'; ?>>
            <?php echo htmlspecialchars($d['DEPT_SPECIALIZATION']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column:span 12; display:flex; gap:8px; align-items:flex-end;">
      <button class="btn">Apply</button>
      <a class="btn" href="/public/faculty.php" style="background:#234b7a">Clear</a>
    </div>
  </form>
</section>

<section class="container fade-in" style="margin-top:6px; margin-bottom:24px;">
  <?php if (!$rows): ?>
    <div class="panel">No matching faculty.</div>
  <?php else: ?>
    <div class="grid" style="gap:12px">
      <?php foreach ($rows as $row): ?>
        <div class="panel slide-up" style="grid-column: span 4;">
          <h3 style="margin-top:0">
            <?php
              echo htmlspecialchars($row['FACULTY_LNAME'].', '.$row['FACULTY_FNAME']);
              if (!empty($row['FACULTY_INITIAL'])) echo ' '.htmlspecialchars($row['FACULTY_INITIAL']);
            ?>
          </h3>
          <div class="muted" style="font-size:.95rem; margin-top:4px;">
            <?php echo htmlspecialchars($row['RANK_DESCRIPTION']); ?> ·
            <?php echo htmlspecialchars($row['DEPT_SPECIALIZATION']); ?><br/>
            <?php echo htmlspecialchars($row['FACULTY_EMAIL']); ?>
          </div>

          <button
            class="btn small"
            data-read-more
            data-type="faculty"
            data-id="<?php echo (int)$row['FACULTY_ID']; ?>"
            data-title="<?php echo htmlspecialchars($row['FACULTY_LNAME'].', '.$row['FACULTY_FNAME']); ?>">
            Read More
          </button>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
