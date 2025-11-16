<?php
$pageTitle = 'Admin Dashboard';

// Load init (sessions, db, helpers) BEFORE any output
require_once __DIR__ . '/../partials/init.php';

// Require admin auth before rendering
if (!is_admin()) {
  redirect_to('/admin/login.php');
}

/* -------------------- Helpers -------------------- */
function get_page(string $param): int {
  $p = isset($_GET[$param]) ? (int)$_GET[$param] : 1;
  return $p > 0 ? $p : 1;
}
$PAGE_SIZE = 10;

/** Build a label for a faculty row given the ID */
function faculty_label(PDO $pdo, int $facultyId): string {
  $stmt = $pdo->prepare("SELECT FACULTY_FNAME, FACULTY_INITIAL, FACULTY_LNAME FROM FACULTY WHERE FACULTY_ID = ?");
  $stmt->execute([$facultyId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) return 'Faculty #'.$facultyId;
  $first = trim((string)($row['FACULTY_FNAME'] ?? ''));
  $init  = trim((string)($row['FACULTY_INITIAL'] ?? ''));
  $last  = trim((string)($row['FACULTY_LNAME'] ?? ''));
  $name = trim($last . ', ' . $first . ($init !== '' ? ' ' . $init : ''));
  return $name !== '' ? $name : 'Faculty #'.$facultyId;
}

/** Fetch a page of audit rows with robust column aliasing */
function fetch_audit_page(PDO $pdo, int $limit, int $offset): array {
  $idCandidates   = ['ID', 'id', 'log_id', 'audit_id'];
  $timeCandidates = ['CREATED_AT', 'created_at', 'logged_at', 'timestamp', 'createdOn'];

  foreach ($idCandidates as $idCol) {
    foreach ($timeCandidates as $tCol) {
      try {
        $sql = "
          SELECT
            {$idCol} AS ID,
            {$tCol}  AS CREATED_AT,
            ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
          FROM AUDIT_LOG
          ORDER BY {$idCol} DESC
          LIMIT :lim OFFSET :off
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $e) {}
    }
  }
  return [];
}
function count_audit(PDO $pdo): int {
  try {
    return (int)$pdo->query("SELECT COUNT(*) FROM AUDIT_LOG")->fetchColumn();
  } catch (Throwable $e) {
    return 0;
  }
}

/* -------------------- KPIs -------------------- */
$kpi = [
  'faculty'    => (int)$pdo->query("SELECT COUNT(*) FROM FACULTY")->fetchColumn(),
  'research'   => (int)$pdo->query("SELECT COUNT(*) FROM RESEARCH")->fetchColumn(),
  'agencies'   => (int)$pdo->query("SELECT COUNT(*) FROM AGENCY")->fetchColumn(),
  'funding'    => (int)$pdo->query("SELECT COUNT(*) FROM FUNDING")->fetchColumn(),
  'assignment' => (int)$pdo->query("SELECT COUNT(*) FROM ASSIGNMENT")->fetchColumn(),
];

/* -------------------- Top Research by Funding (paginated) -------------------- */
$tr_page   = get_page('tr_page');
$tr_total  = (int)$pdo->query("SELECT COUNT(*) FROM RESEARCH")->fetchColumn();
$tr_pages  = max(1, (int)ceil($tr_total / $PAGE_SIZE));
$tr_offset = ($tr_page - 1) * $PAGE_SIZE;

$tr_stmt = $pdo->prepare("
  SELECT re.RESEARCH_ID, re.RESEARCH_TITLE,
         COALESCE(SUM(fu.FUNDING_AMOUNT),0) AS total
  FROM RESEARCH re
  LEFT JOIN FUNDING fu ON fu.RESEARCH_ID = re.RESEARCH_ID
  GROUP BY re.RESEARCH_ID, re.RESEARCH_TITLE
  ORDER BY total DESC
  LIMIT :lim OFFSET :off
");
$tr_stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
$tr_stmt->bindValue(':off', $tr_offset, PDO::PARAM_INT);
$tr_stmt->execute();
$topResearch = $tr_stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- Top Faculty by Assignments (paginated) -------------------- */
$tf_page   = get_page('tf_page');
$tf_total  = (int)$pdo->query("SELECT COUNT(*) FROM FACULTY")->fetchColumn();
$tf_pages  = max(1, (int)ceil($tf_total / $PAGE_SIZE));
$tf_offset = ($tf_page - 1) * $PAGE_SIZE;

$tf_stmt = $pdo->prepare("
  SELECT f.FACULTY_ID, COUNT(a.ASSIGNMENT_ID) AS total_assignments
  FROM FACULTY f
  LEFT JOIN ASSIGNMENT a ON a.FACULTY_ID = f.FACULTY_ID
  GROUP BY f.FACULTY_ID
  ORDER BY total_assignments DESC
  LIMIT :lim OFFSET :off
");
$tf_stmt->bindValue(':lim', $PAGE_SIZE, PDO::PARAM_INT);
$tf_stmt->bindValue(':off', $tf_offset, PDO::PARAM_INT);
$tf_stmt->execute();
$topFaculty = $tf_stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------- Audit (paginated) -------------------- */
$log_page   = get_page('log_page');
$log_total  = count_audit($pdo);
$log_pages  = max(1, (int)ceil($log_total / $PAGE_SIZE));
$log_offset = ($log_page - 1) * $PAGE_SIZE;
$audit      = fetch_audit_page($pdo, $PAGE_SIZE, $log_offset);

require_once __DIR__ . '/../partials/site_header.php';
?>
<style>
  /* Grid */
  .dash-wrap {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 16px;
  }

  /* Header hero */
  .hero-card {
    grid-column: span 12;
    background: #fff;
    border: 1px solid var(--border, #e8e8e8);
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
    padding: 22px;
  }

  /* KPI cards */
  .kpi-card {
    background: #fff;
    border: 1px solid var(--border, #e8e8e8);
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
    padding: 18px;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .kpi-col { grid-column: span 4; }
  .kpi-emoji { font-size: 28px; line-height: 1; }
  .kpi-title { font-weight: 700; margin-top: 6px; }
  .kpi-value { font-size: 2rem; font-weight: 800; margin-top: 6px; }
  .btn-link {
    align-self: flex-start;
    display: inline-flex;
    padding: 6px 10px;
    border-radius: 8px;
    background: var(--color-primary);
    color: #fff; text-decoration: none; font-weight: 600; font-size: .95rem;
  }
  .btn-link:hover { filter: brightness(0.95); }

  .section-card {
    grid-column: span 12;
    background: #fff;
    border: 1px solid var(--border, #e8e8e8);
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
    padding: 15px;
  }
  .section-header {
    display:flex; align-items:center; gap:10px; margin-bottom:10px;
  }
  .section-emoji { font-size: 24px; }
  .list {
    width:100%;
    border-collapse: collapse;
  }
  .list tr td, .list tr th {
    padding: 8px 6px;
    border-bottom: 1px solid #eee;
    text-align: left;
  }
  .list tr:last-child td { border-bottom:none; }
  .muted-small { color:#666; font-size:.9rem; }

  @media (max-width: 960px) {
    .kpi-col { grid-column: span 12; }
  }
  .kpi-card,
  .section-card,
  .hero-card {
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .kpi-card:hover,
  .section-card:hover,
  .hero-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,.08);
    border-color: #dfe7f3;
  }
  .btn-link {
    transition: filter .2s ease, transform .06s ease, box-shadow .15s ease;
  }
  .btn-link:hover { filter: brightness(.94); box-shadow: 0 4px 10px rgba(0,0,0,.06); }
  .btn-link:active { transform: translateY(1px); }
  .list tr { transition: background-color .12s ease; }
  .list tbody tr:hover { background: #f7fbff; }
  .btn-link:focus, .page-btn:focus {
    outline: 2px solid #234b7a;
    outline-offset: 2px;
  }
  .kpi-card:focus-within, .section-card:focus-within {
    box-shadow: 0 0 0 2px #c7d5ef inset, 0 6px 18px rgba(0,0,0,.08);
  }
  @media (prefers-reduced-motion: reduce) {
    .kpi-card, .section-card, .hero-card,
    .btn-link, .page-btn, .list tr { transition: none !important; }
  }
</style>

<section class="container fade-in" style="margin-bottom: 16px;">
  <div class="dash-wrap">
    <!-- Header  -->
    <div class="hero-card">
      <h1 style="margin:0 0 6px;">Admin Dashboard</h1>
      <p class="muted" style="margin:0;">Overview of your database and the latest changes in real time.</p>
    </div>

    <!-- KPI cards  -->
    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji">👩‍🏫</div>
        <div class="kpi-title">Faculty</div>
        <div class="kpi-value" id="kpi-faculty"><?= number_format($kpi['faculty']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/faculty.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji">📚</div>
        <div class="kpi-title">Research</div>
        <div class="kpi-value" id="kpi-research"><?= number_format($kpi['research']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/research.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji">🏢</div>
        <div class="kpi-title">Agencies</div>
        <div class="kpi-value" id="kpi-agencies"><?= number_format($kpi['agencies']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/agency.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji">💰</div>
        <div class="kpi-title">Fundings</div>
        <div class="kpi-value" id="kpi-funding"><?= number_format($kpi['funding']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/funding.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji">✅</div>
        <div class="kpi-title">Assignments</div>
        <div class="kpi-value" id="kpi-assignment"><?= number_format($kpi['assignment']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/assignment.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji">🖨️</div>
        <div class="kpi-title">Audit (Print)</div>
        <div class="muted-small">Formal printable log of changes</div>
      </div>
      <a class="btn-link" target="_blank" href="<?= app_url('/admin/audit_print.php'); ?>">Open Print View</a>
    </div>

    <!-- Top Research by Total Funding -->
    <div class="section-card">
      <div class="section-header">
        <div class="section-emoji">🏆</div>
        <h3 style="margin:0;">Top Research by Total Funding</h3>
      </div>
      <?php if (!$topResearch): ?>
        <div class="muted">No data.</div>
      <?php else: ?>
        <table class="list">
          <thead>
            <tr>
              <th>#</th>
              <th>Research</th>
              <th>Total Funding</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $rankStart = $tr_offset + 1;
              foreach ($topResearch as $idx => $tr):
            ?>
              <tr>
                <td><?= $rankStart + $idx; ?></td>
                <td>
                  <a href="<?= app_url('/public/research.php'); ?>?id=<?= (int)$tr['RESEARCH_ID']; ?>">
                    <?= htmlspecialchars($tr['RESEARCH_TITLE']); ?>
                  </a>
                </td>
                <td><?= '₱' . number_format((float)$tr['total'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <!-- Pagination -->
        <div class="pagination">
          <?php
            $base  = app_url('/admin/dashboard.php');
            $qs_tr = function($p) use ($tf_page, $log_page) {
              return 'tr_page='.$p.'&tf_page='.$tf_page.'&log_page='.$log_page;
            };
          ?>
          <a class="page-btn" href="<?= $base.'?'.$qs_tr(max(1,$tr_page-1)); ?>">&#x276E;</a>
          <?php for ($i=1; $i<= $tr_pages; $i++): ?>
            <a class="page-btn <?= $i === $tr_page ? 'active' : '' ?>" href="<?= $base.'?'.$qs_tr($i); ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a class="page-btn" href="<?= $base.'?'.$qs_tr(min($tr_pages,$tr_page+1)); ?>">&#x276F;</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Top Faculty by Total Assignments -->
    <div class="section-card">
      <div class="section-header">
        <div class="section-emoji">👥</div>
        <h3 style="margin:0;">Top Faculty by Total Assignments</h3>
      </div>
      <?php if (!$topFaculty): ?>
        <div class="muted">No data.</div>
      <?php else: ?>
        <table class="list">
          <thead>
            <tr>
              <th>#</th>
              <th>Faculty</th>
              <th>Total Assignments</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $rankStart = $tf_offset + 1;
              foreach ($topFaculty as $idx => $f):
                $name = faculty_label($pdo, (int)$f['FACULTY_ID']);
            ?>
              <tr>
                <td><?= $rankStart + $idx; ?></td>
                <td>
                  <a href="<?= app_url('/public/faculty.php'); ?>?id=<?= (int)$f['FACULTY_ID']; ?>">
                    <?= htmlspecialchars($name); ?>
                  </a>
                </td>
                <td><?= (int)$f['total_assignments']; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <!-- Pagination -->
        <div class="pagination">
          <?php
            $base  = app_url('/admin/dashboard.php');
            $qs_tf = function($p) use ($tr_page, $log_page) {
              return 'tr_page='.$tr_page.'&tf_page='.$p.'&log_page='.$log_page;
            };
          ?>
          <a class="page-btn" href="<?= $base.'?'.$qs_tf(max(1,$tf_page-1)); ?>">&#x276E;</a>
          <?php for ($i=1; $i<= $tf_pages; $i++): ?>
            <a class="page-btn <?= $i === $tf_page ? 'active' : '' ?>" href="<?= $base.'?'.$qs_tf($i); ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a class="page-btn" href="<?= $base.'?'.$qs_tf(min($tf_pages,$tf_page+1)); ?>">&#x276F;</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Live Activity -->
    <div class="section-card">
      <div class="section-header">
        <div class="section-emoji">📜</div>
        <h3 style="margin:0;">Live Activity</h3>
      </div>
      <?php if (!$audit): ?>
        <div class="muted">No audit entries yet.</div>
      <?php else: ?>
        <table class="list" id="audit-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>When</th>
              <th>Actor</th>
              <th>Action</th>
              <th>Table</th>
              <th>PK</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($audit as $row): ?>
              <tr>
                <td><?= (int)$row['ID']; ?></td>
                <td><?= htmlspecialchars((string)$row['CREATED_AT']); ?></td>
                <td><?= htmlspecialchars((string)$row['ACTOR']); ?></td>
                <td><?= htmlspecialchars((string)$row['ACTION_ENUM']); ?></td>
                <td><?= htmlspecialchars((string)$row['TABLE_NAME']); ?></td>
                <td><?= htmlspecialchars((string)$row['PK_VALUE']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <!-- Pagination -->
        <div class="pagination">
          <?php
            $base   = app_url('/admin/dashboard.php');
            $qs_log = function($p) use ($tr_page, $tf_page) {
              return 'tr_page='.$tr_page.'&tf_page='.$tf_page.'&log_page='.$p;
            };
          ?>
          <a class="page-btn" href="<?= $base.'?'.$qs_log(max(1,$log_page-1)); ?>">&#x276E;</a>
          <?php for ($i=1; $i<= $log_pages; $i++): ?>
            <a class="page-btn <?= $i === $log_page ? 'active' : '' ?>" href="<?= $base.'?'.$qs_log($i); ?>"><?= $i ?></a>
          <?php endfor; ?>
          <a class="page-btn" href="<?= $base.'?'.$qs_log(min($log_pages,$log_page+1)); ?>">&#x276F;</a>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>

<!-- KPI auto-refresh via /admin/api/dashboard_stats.php -->
<script>
(function(){
  const ENDPOINT = "<?= app_url('/admin/api/dashboard_stats.php'); ?>";
  const el = {
    fac:  document.getElementById('kpi-faculty'),
    res:  document.getElementById('kpi-research'),
    ag:   document.getElementById('kpi-agencies'),
    fund: document.getElementById('kpi-funding'),
    asg:  document.getElementById('kpi-assignment')
  };
  function number(n){ return (Number(n)||0).toLocaleString(); }
  async function refreshKPIs(){
    try {
      const r = await fetch(ENDPOINT, { credentials: 'same-origin' });
      if (!r.ok) return;
      const d = await r.json();
      if (d && d.kpi) {
        if (el.fac)  el.fac.textContent  = number(d.kpi.faculty);
        if (el.res)  el.res.textContent  = number(d.kpi.research);
        if (el.ag)   el.ag.textContent   = number(d.kpi.agencies);
        if (el.fund) el.fund.textContent = number(d.kpi.funding);
        if (el.asg)  el.asg.textContent  = number(d.kpi.assignment);
      }
    } catch(e){ /* silent */ }
  }
  refreshKPIs();
  setInterval(refreshKPIs, 60000);
})();
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
