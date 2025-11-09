<?php
$pageTitle = 'Research';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../partials/header.php';

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
  // details
  $id = (int) $_GET['id'];

  $q1 = "SELECT * FROM RESEARCH WHERE RESEARCH_ID = ?";
  $stmt = $pdo->prepare($q1); $stmt->execute([$id]);
  $r = $stmt->fetch();
  if (!$r){ echo "<div class='container'><p>Not found.</p></div>"; require_once __DIR__ . '/../partials/footer.php'; exit; }

  // faculty team
  $q2 = "SELECT f.FACULTY_ID, f.FACULTY_FNAME, f.FACULTY_LNAME, a.ROLE_ID
         FROM ASSIGNMENT a
         JOIN FACULTY f ON a.FACULTY_ID = f.FACULTY_ID
         WHERE a.RESEARCH_ID = ?
         ORDER BY FIELD(a.ROLE_ID,'LR','CR') DESC, f.FACULTY_LNAME";
  $st2 = $pdo->prepare($q2); $st2->execute([$id]); $team = $st2->fetchAll();

  // funding summary
  $q3 = "SELECT SUM(FUNDING_AMOUNT) AS total, COUNT(*) AS grants FROM FUNDING WHERE RESEARCH_ID = ?";
  $st3 = $pdo->prepare($q3); $st3->execute([$id]); $fund = $st3->fetch();

  ?>
  <section class="container">
    <div class="detail">
      <h2 style="margin:0"><?php echo htmlspecialchars($r['RESEARCH_TITLE']); ?></h2>
      <div class="meta" style="margin:8px 0">
        Status: <span class="pill"><?php echo htmlspecialchars($r['RESEARCH_STATUS']); ?></span>
        &nbsp;·&nbsp; Start: <?php echo htmlspecialchars($r['RESEARCH_STARTDATE']); ?>
        <?php if($r['RESEARCH_ENDDATE']) echo " · End: ".htmlspecialchars($r['RESEARCH_ENDDATE']); ?>
      </div>

      <h3>Team</h3>
      <?php if(!$team){ echo "<p class='muted'>No assignments yet.</p>"; } ?>
      <ul>
        <?php foreach ($team as $m): ?>
          <li>
            <a href="/public/faculty.php?id=<?php echo (int)$m['FACULTY_ID']; ?>">
              <?php echo htmlspecialchars($m['FACULTY_LNAME'].', '.$m['FACULTY_FNAME']); ?>
            </a> — Role: <?php echo htmlspecialchars($m['ROLE_ID']); ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <h3>Funding</h3>
      <p class="muted">Grants: <?php echo (int)$fund['grants']; ?> · Total:
        ₱<?php echo number_format((float)$fund['total'], 2); ?></p>
    </div>
  </section>
  <?php
  require_once __DIR__ . '/../partials/footer.php';
  exit;
}

// directory
$q     = isset($_GET['q']) ? trim($_GET['q']) : '';
$status= isset($_GET['status']) ? trim($_GET['status']) : '';
$from  = isset($_GET['from']) ? trim($_GET['from']) : '';
$to    = isset($_GET['to']) ? trim($_GET['to']) : '';

$sql = "SELECT RESEARCH_ID, RESEARCH_TITLE, RESEARCH_STATUS, RESEARCH_STARTDATE, RESEARCH_ENDDATE
        FROM RESEARCH WHERE 1=1";
$params = [];

if ($q!==''){ $sql.=" AND RESEARCH_TITLE LIKE ?"; $params[] = "%{$q}%"; }
if ($status!==''){ $sql.=" AND RESEARCH_STATUS = ?"; $params[] = $status; }
if ($from!==''){ $sql.=" AND RESEARCH_STARTDATE >= ?"; $params[] = $from; }
if ($to!==''){ $sql.=" AND (RESEARCH_ENDDATE <= ? OR (RESEARCH_ENDDATE IS NULL AND RESEARCH_STARTDATE <= ?))"; $params[]=$to; $params[]=$to; }

$sql .= " ORDER BY RESEARCH_STARTDATE DESC LIMIT 60";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

// status options from lookup
$statuses = $pdo->query("SELECT STATUS_CODE, STATUS_LABEL FROM RESEARCH_STATUS ORDER BY STATUS_LABEL")->fetchAll();
?>
<section class="container">
  <h1>Research</h1>
  <form action="" method="get" class="search-panel grid">
    <div class="field" style="grid-column: span 4">
      <label for="q">Title contains</label>
      <input class="input" id="q" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="e.g., Cybersecurity" />
    </div>
    <div class="field" style="grid-column: span 3">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <?php foreach ($statuses as $s):
          $sel = ($status===$s['STATUS_CODE'])?' selected':'';
          echo '<option value="'.htmlspecialchars($s['STATUS_CODE']).'"'.$sel.'>'.htmlspecialchars($s['STATUS_LABEL']).'</option>';
        endforeach; ?>
      </select>
    </div>
    <div class="field" style="grid-column: span 2">
      <label for="from">Start from</label>
      <input class="input" type="date" id="from" name="from" value="<?php echo htmlspecialchars($from); ?>"/>
    </div>
    <div class="field" style="grid-column: span 2">
      <label for="to">End by</label>
      <input class="input" type="date" id="to" name="to" value="<?php echo htmlspecialchars($to); ?>"/>
    </div>
    <div class="field" style="grid-column: span 1; display:flex; align-items:flex-end;">
      <button class="btn" type="submit">Search</button>
    </div>
  </form>

  <div class="meta" style="margin-top:12px">
    <?php echo count($rows); ?> projects match your criteria
  </div>

  <div class="cards" style="margin-top:16px">
    <?php foreach ($rows as $r): ?>
      <div class="card">
        <div>
          <h3><?php echo htmlspecialchars($r['RESEARCH_TITLE']); ?></h3>
          <div class="meta">
            <span class="pill"><?php echo htmlspecialchars($r['RESEARCH_STATUS']); ?></span>
            &nbsp;·&nbsp; Start: <?php echo htmlspecialchars($r['RESEARCH_STARTDATE']); ?>
            <?php if($r['RESEARCH_ENDDATE']) echo " · End: ".htmlspecialchars($r['RESEARCH_ENDDATE']); ?>
          </div>
        </div>
        <div class="card-actions">
          <a class="btn" href="/public/research.php?id=<?php echo (int)$r['RESEARCH_ID']; ?>">View details</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
