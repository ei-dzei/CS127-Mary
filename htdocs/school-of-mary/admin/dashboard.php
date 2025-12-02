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
  // Format as: Last, First Initial
  $name = trim($last . ', ' . $first . ($init !== '' ? ' ' . $init : ''));
  return $name !== '' ? $name : 'Faculty #'.$facultyId;
}

/** Fetch a page of audit rows with robust column aliasing AND SORTING */
function fetch_audit_page(PDO $pdo, int $limit, int $offset, string $sortMode = 'newest'): array {
  $idCandidates   = ['ID', 'id', 'log_id', 'audit_id'];
  $timeCandidates = ['CREATED_AT', 'created_at', 'logged_at', 'timestamp', 'createdOn'];

  foreach ($idCandidates as $idCol) {
    foreach ($timeCandidates as $tCol) {
      try {
        // Determine Order Clause based on Sort Mode
        $orderSql = "{$idCol} DESC"; // Default
        switch ($sortMode) {
            case 'oldest':      $orderSql = "{$idCol} ASC"; break;
            case 'actor_asc':   $orderSql = "ACTOR ASC"; break;
            case 'actor_desc':  $orderSql = "ACTOR DESC"; break;
            case 'action_asc':  $orderSql = "ACTION_ENUM ASC"; break;
            case 'table_asc':   $orderSql = "TABLE_NAME ASC"; break;
        }

        $sql = "
          SELECT
            {$idCol} AS ID,
            {$tCol}  AS CREATED_AT,
            ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
          FROM AUDIT_LOG
          ORDER BY {$orderSql}
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

/* -------------------- Audit (paginated + sorted) -------------------- */
$log_page   = get_page('log_page');
$log_sort   = $_GET['log_sort'] ?? 'newest'; // Catch the sort parameter
$log_total  = count_audit($pdo);
$log_pages  = max(1, (int)ceil($log_total / $PAGE_SIZE));
$log_offset = ($log_page - 1) * $PAGE_SIZE;

// Pass $log_sort to the fetch function
$audit      = fetch_audit_page($pdo, $PAGE_SIZE, $log_offset, $log_sort);

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
  
  .welcome-message {
    color: var(--color-secondary, #f0b800); 
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 4px;
    display: block;
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
    align-items: center; 
  }
  .kpi-col { grid-column: span 4; }
  
  .kpi-card > div:first-child { 
    width: 100%; 
    text-align: center;
    margin-bottom: 10px;
  }
  
  .kpi-emoji { 
    font-size: 32px;
    line-height: 1;
    color: var(--color-primary, #1e4073);
  }
  .kpi-emoji i { vertical-align: middle; }
  
  .kpi-title { font-weight: 700; margin-top: 6px; }
  .kpi-value { font-size: 2rem; font-weight: 800; margin-top: 6px; }

  .btn-link {
    align-self: stretch;
    display: flex;
    justify-content: center;
    padding: 8px 10px;
    border-radius: 8px;
    background: var(--color-primary);
    color: #fff; 
    text-decoration: none; 
    font-weight: 600; 
    font-size: .95rem;
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
  #research-section, #faculty-section, #audit-section{ scroll-margin-top: 100px;}
  
  /* Header for Sections */
  .section-header {
    display:flex; 
    align-items:center; 
    gap:10px; 
    margin-bottom:10px;
    justify-content: space-between; 
  }
  .section-header-left {
      display: flex;
      align-items: center;
      gap: 10px;
  }

  .section-emoji {
    font-size: 24px;
    color: #444; 
  }
  .section-emoji i { vertical-align: middle; }

  /* Sort Button and Dropdown CSS */
  .sort-wrapper {
      position: relative;
  }
  .sort-toggle-btn {
    display: flex; align-items: center; justify-content: center;
    background: transparent; border: 1px solid #d1d5db;
    border-radius: 6px; cursor: pointer; color: #4b5563;
    width: 36px; height: 36px; padding: 0;
    transition: all .2s;
  }
  .sort-toggle-btn:hover { background: #f3f4f6; color: #1f2937; }
  
  .sort-dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 6px;
      width: 180px;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      z-index: 50;
      padding: 6px;
  }
  .sort-dropdown-menu.show { display: block; }

  .sort-option {
      display: block;
      width: 100%;
      text-align: left;
      padding: 8px 12px;
      border-radius: 6px;
      color: #374151;
      text-decoration: none;
      font-size: 0.9rem;
      box-sizing: border-box;
  }
  .sort-option:hover { background: #f3f4f6; }
  .sort-option.active { background: #eff6ff; color: #1d4ed8; font-weight: 600; }


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

  /* New Dual Column Layout */
  .dual-column-layout {
    grid-column: span 12;
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
    margin-top: 16px;
  }
  .research-col { grid-column: span 1; }
  .calendar-col { grid-column: span 1; }

  @media (max-width: 960px) {
    .kpi-col { grid-column: span 12; }
    .dual-column-layout {
      grid-template-columns: 1fr;
    }
    .research-col, .calendar-col { grid-column: span 1; }
  }
  
  /* Calendar Compact Overrides */
  .calendar-header .btn.small { padding: 4px 8px; }
  .calendar-header h2 { font-size: 1rem; }
  .calendar-grid-header > div { padding: 5px 3px; font-size: 0.8rem; }
  .calendar-day { min-height: 55px; padding: 3px; }
  .calendar-day-number { font-size: 0.9rem; margin-bottom: 2px; }
  .calendar-event { font-size: 0.7rem; padding: 1px 2px; }
  
  /* Live Clock */
  #live-clock {
    display: block;
    text-align: center;
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--color-primary); 
    padding-top: 10px;
    margin-top: 10px;
    border-top: 1px solid #eee;
  }

  .kpi-card, .section-card, .hero-card {
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .kpi-card:hover, .section-card:hover, .hero-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,.08);
    border-color: #dfe7f3;
  }
  .btn-link { transition: filter .2s ease, transform .06s ease, box-shadow .15s ease; }
  .btn-link:hover { filter: brightness(.94); box-shadow: 0 4px 10px rgba(0,0,0,.06); }
  .btn-link:active { transform: translateY(1px); }
  .list tr { transition: background-color .12s ease; }
  .list tbody tr:hover { background: #f7fbff; }
  
  @media (prefers-reduced-motion: reduce) {
    .kpi-card, .section-card, .hero-card,
    .btn-link, .page-btn, .list tr { transition: none !important; }
  }
</style>

<section class="container fade-in" style="margin-bottom: 16px;">
  <div class="dash-wrap">
    <div class="hero-card">
      <span class="welcome-message">Welcome, Admin!</span>
      <h1 style="margin:0 0 6px;">Admin Dashboard</h1>
      <p class="muted" style="margin:0;">Overview of your database and the latest changes in real time.</p>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji"><i class="bi bi-person-badge"></i></div>
        <div class="kpi-title">Faculty</div>
        <div class="kpi-value" id="kpi-faculty"><?= number_format($kpi['faculty']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/faculty.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji"><i class="bi bi-book"></i></div>
        <div class="kpi-title">Research</div>
        <div class="kpi-value" id="kpi-research"><?= number_format($kpi['research']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/research.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji"><i class="bi bi-building"></i></div>
        <div class="kpi-title">Agencies</div>
        <div class="kpi-value" id="kpi-agencies"><?= number_format($kpi['agencies']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/agency.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji"><i class="bi bi-cash-stack"></i></div>
        <div class="kpi-title">Fundings</div>
        <div class="kpi-value" id="kpi-funding"><?= number_format($kpi['funding']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/funding.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji"><i class="bi bi-list-check"></i></div>
        <div class="kpi-title">Assignments</div>
        <div class="kpi-value" id="kpi-assignment"><?= number_format($kpi['assignment']); ?></div>
      </div>
      <a class="btn-link" href="<?= app_url('/admin/crud/assignment.php'); ?>">Manage</a>
    </div>

    <div class="kpi-card kpi-col">
      <div>
        <div class="kpi-emoji"><i class="bi bi-printer"></i></div>
        <div class="kpi-title">Audit (Print)</div>
        <div class="muted-small">Formal printable log of changes</div>
      </div>
      <a class="btn-link" target="_blank" href="<?= app_url('/admin/audit_print.php'); ?>">Open Print View</a>
    </div>

    <div class="dual-column-layout">
      <div class="section-card research-col" id="research-section">
        <div class="section-header">
            <div class="section-header-left">
              <div class="section-emoji"><i class="bi bi-trophy"></i></div>
              <h3 style="margin:0;">Top Research by Funding</h3>
            </div>
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
          
          <div class="pagination">
            <?php
              $base  = app_url('/admin/dashboard.php');
              // Include current log_sort in other paginations too to preserve state
              $qs_tr = function($p) use ($tf_page, $log_page, $log_sort) {
                return 'tr_page='.$p.'&tf_page='.$tf_page.'&log_page='.$log_page.'&log_sort='.$log_sort;
              };
            ?>
            <?php if ($tr_page > 1): ?>
            <a class="page-btn" href="<?= $base.'?'.$qs_tr(max(1,$tr_page-1)); ?>#research-section" title = "Previous Page">&#x276E;</a>
            <?php endif; ?>
            <?php
              $tr_maxPage = 5;
              $tr_start = max(1, $tr_page - floor($tr_maxPage / 2));
              $tr_end = min($tr_pages, $tr_start + $tr_maxPage - 1);

              if ($tr_end - $tr_start < $tr_maxPage - 1) {
                $tr_start = max(1, $tr_end - $tr_maxPage + 1);
              } 
            ?>
            <?php if ($tr_start > 1): ?>
              <a href="<?= $base.'?'.$qs_tr(1); ?>#research-section" class="page-btn" >1</a>
              <?php if ($tr_start > 3): ?>
                <a href="<?= $base.'?'.$qs_tr(max(1,$tr_page - 5)) ?>#research-section" class="page-btn" title="Jump backward 5 pages">...</a>        
              <?php endif; ?>
              <?php if ($tr_start == 3): ?>
                      <a href="<?= $base.'?'.$qs_tr(2); ?>#research-section" class="page-btn" >2</a>       
              <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $tr_start; $i <= $tr_end; $i++): ?>
              <a class="page-btn <?= $i == $tr_page ? 'active' : '' ?>" href="<?= $base.'?'.$qs_tr($i); ?>#research-section"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($tr_end < $tr_pages): ?>
              <?php if ($tr_end == $tr_pages - 2):?>
                <a href="<?= $base.'?'.$qs_tr($tr_pages - 1); ?>#research-section" class="page-btn" > <?=$tr_pages - 1?></a>
              <?php endif; ?>
              <?php if ($tr_end < $tr_pages - 2): ?>
                <a href="<?= $base.'?'.$qs_tr(min($tr_pages,$tr_page + 5)); ?>#research-section"class="page-btn" title="Jump forward 5 pages">...</a>
              <?php endif; ?>
                <a href="<?= $base.'?'.$qs_tr($tr_pages); ?>#research-section" class="page-btn" > <?=$tr_pages?></a>
            <?php endif; ?>
            <?php  if ($tr_page < $tr_pages): ?>
              <a class="page-btn" href="<?= $base.'?'.$qs_tr(min($tr_pages,$tr_page+1)); ?>#research-section" title = "Next Page">&#x276F;</a>
            <?php  endif;?>
          </div>
        <?php endif; ?>  
      </div>

      <div class="section-card calendar-col">
        <div class="section-header">
            <div class="section-header-left">
                <div class="section-emoji"><i class="bi bi-calendar-check"></i></div>
                <h3 style="margin:0;">Calendar</h3>
            </div>
        </div>
        
        <div id="calendar-app" class="panel" style="padding: 0; border: none; box-shadow: none;">
            <div class="calendar-header">
                <button id="prev-month" class="btn small">←</button>
                <h2 id="current-month-year">Loading...</h2>
                <button id="next-month" class="btn small">→</button>
            </div>
            
            <div class="calendar-grid-header">
                <div>S</div><div>M</div><div>T</div><div>W</div><div>T</div><div>F</div><div>S</div>
            </div>

            <div id="calendar-days" class="calendar-grid">
            </div>
            
            <span id="live-clock">Loading Time...</span>
        </div>
      </div>  
    </div> 
    
    <div class="section-card" id="faculty-section">
      <div class="section-header">
        <div class="section-header-left">
            <div class="section-emoji"><i class="bi bi-people"></i></div>
            <h3 style="margin:0;">Top Faculty by Assignments</h3>
        </div>
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
        <div class="pagination">
          <?php
            $base  = app_url('/admin/dashboard.php');
            $qs_tf = function($p) use ($tr_page, $log_page, $log_sort) {
              return 'tr_page='.$tr_page.'&tf_page='.$p.'&log_page='.$log_page.'&log_sort='.$log_sort;
            };
          ?>
          <?php if ($tf_page > 1): ?>
            <a class="page-btn" href="<?= $base.'?'.$qs_tf(max(1,$tf_page-1)); ?>#faculty-section" title = "Previous Page">&#x276E;</a>
          <?php endif; ?>
          <?php
            $tf_maxPage = 5;
            $tf_start = max(1, $tf_page - floor($tf_maxPage / 2));
            $tf_end = min($tf_pages, $tf_start + $tf_maxPage - 1);

            if ($tf_end - $tf_start < $tf_maxPage - 1) {
              $tf_start = max(1, $tf_end - $tf_maxPage + 1);
            } 
          ?>
          <?php if ($tf_start > 1): ?>
            <a href="<?= $base.'?'.$qs_tf(1); ?>#faculty-section" class="page-btn" >1</a>
            <?php if ($tf_start > 3): ?>
              <a href="<?= $base.'?'.$qs_tf(max(1,$tf_page - 5)) ?>#faculty-section" class="page-btn" title="Jump backward 5 pages">...</a>        
            <?php endif; ?>
            <?php if ($tf_start == 3): ?>
                    <a href="<?= $base.'?'.$qs_tf(2); ?>#faculty-section" class="page-btn" >2</a>       
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $tf_start; $i <= $tf_end; $i++): ?>
            <a class="page-btn <?= $i === $tf_page ? 'active' : '' ?>" href="<?= $base.'?'.$qs_tf($i); ?>#faculty-section"><?= $i ?></a>
          <?php endfor; ?>

          <?php if ($tf_end < $tf_pages): ?>
            <?php if ($tf_end == $tf_pages - 2):?>
              <a href="<?= $base.'?'.$qs_tf($tf_pages - 1); ?>#faculty-section" class="page-btn" > <?=$tf_pages - 1?></a>
            <?php endif; ?>
            <?php if ($tf_end < $tf_pages - 2): ?>
              <a href="<?= $base.'?'.$qs_tf(min($tf_pages,$tf_page + 5)); ?>#faculty-section"class="page-btn" title="Jump forward 5 pages">...</a>
            <?php endif; ?>
              <a href="<?= $base.'?'.$qs_tf($tf_pages); ?>#faculty-section" class="page-btn" > <?=$tf_pages?></a>
          <?php endif; ?>
          <?php  if ($tf_page < $tf_pages): ?>
            <a class="page-btn" href="<?= $base.'?'.$qs_tf(min($tf_pages,$tf_page+1)); ?>#faculty-section" title = "Next Page">&#x276F;</a>
          <?php  endif;?>
        </div>
      <?php endif; ?>
    </div>

    <div class="section-card" id="audit-section">
      <div class="section-header">
        <div class="section-header-left">
            <div class="section-emoji"><i class="bi bi-receipt"></i></div>
            <h3 style="margin:0;">Live Activity</h3>
        </div>
        
        <div class="sort-wrapper">
             <button class="sort-toggle-btn" id="dash-sort-btn" title="Sort" type="button">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 9l4 -4l4 4m-4 -4v14" /><path d="M21 15l-4 4l-4 -4m4 4v-14" /></svg>
             </button>
             
             <div class="sort-dropdown-menu" id="dash-sort-menu">
                 <?php
                    // Helper to generate sort link (Resets log_page to 1)
                    $buildSortUrl = function($sortKey) use ($tr_page, $tf_page) {
                        return app_url('/admin/dashboard.php') . "?tr_page=$tr_page&tf_page=$tf_page&log_page=1&log_sort=$sortKey#audit-section";
                    };
                 ?>
                 <a href="<?= $buildSortUrl('newest'); ?>" class="sort-option <?= $log_sort==='newest'?'active':''; ?>">Newest First</a>
                 <a href="<?= $buildSortUrl('oldest'); ?>" class="sort-option <?= $log_sort==='oldest'?'active':''; ?>">Oldest First</a>
                 <a href="<?= $buildSortUrl('actor_asc'); ?>" class="sort-option <?= $log_sort==='actor_asc'?'active':''; ?>">Actor (A-Z)</a>
                 <a href="<?= $buildSortUrl('actor_desc'); ?>" class="sort-option <?= $log_sort==='actor_desc'?'active':''; ?>">Actor (Z-A)</a>
                 <a href="<?= $buildSortUrl('action_asc'); ?>" class="sort-option <?= $log_sort==='action_asc'?'active':''; ?>">Action (A-Z)</a>
                 <a href="<?= $buildSortUrl('table_asc'); ?>" class="sort-option <?= $log_sort==='table_asc'?'active':''; ?>">Table (A-Z)</a>
             </div>
        </div>
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
        <div class="pagination">
          <?php
            $base   = app_url('/admin/dashboard.php');
            // Include log_sort in pagination link to preserve sort order
            $qs_log = function($p) use ($tr_page, $tf_page, $log_sort) {
              return 'tr_page='.$tr_page.'&tf_page='.$tf_page.'&log_page='.$p.'&log_sort='.$log_sort;
            };
          ?>
          <?php if ($log_page > 1): ?>
            <a class="page-btn" href="<?= $base.'?'.$qs_log(max(1,$log_page-1)); ?>#audit-section" title = "Previous Page">&#x276E;</a>
          <?php endif; ?>
          <?php
            $log_maxPage = 5;
            $log_start = max(1, $log_page - floor($log_maxPage / 2));
            $log_end = min($log_pages, $log_start + $log_maxPage - 1);

            if ($log_end - $log_start < $log_maxPage - 1) {
              $log_start = max(1, $log_end - $log_maxPage + 1);
            } 
          ?>
          <?php if ($log_start > 1): ?>
            <a href="<?= $base.'?'.$qs_log(1); ?>#audit-section" class="page-btn" >1</a>
            <?php if ($log_start > 3): ?>
              <a href="<?= $base.'?'.$qs_log(max(1,$log_page - 5)) ?>#audit-section" class="page-btn" title="Jump backward 5 pages">...</a>        
            <?php endif; ?>
            <?php if ($log_start == 3): ?>
                    <a href="<?= $base.'?'.$qs_log(2); ?>#audit-section" class="page-btn" >2</a>       
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $log_start; $i <= $log_end; $i++): ?>
            <a class="page-btn <?= $i === $log_page ? 'active' : '' ?>" href="<?= $base.'?'.$qs_log($i); ?>#audit-section"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($log_end < $log_pages): ?>
            <?php if ($log_end == $log_pages - 2): ?>
              <a href="<?= $base.'?'.$qs_log($log_pages - 1); ?>#audit-section" class="page-btn"><?= $log_pages - 1 ?></a>
            <?php endif; ?>
            <?php if ($log_end < $log_pages - 2): ?>
              <a href="<?= $base.'?'.$qs_log(min($log_pages,$tf_page + 5)); ?>#audit-section"class="page-btn" title="Jump forward 5 pages">...</a>
            <?php endif; ?>
              <a href="<?= $base.'?'.$qs_log($log_pages); ?>#audit-section" class="page-btn" > <?=$log_pages?></a>
          <?php endif; ?>
          <?php  if ($log_page < $log_pages): ?>
            <a class="page-btn" href="<?= $base.'?'.$qs_log(min($log_pages,$log_page+1)); ?>#audit-section" title = "Next Page">&#x276F;</a>
          <?php  endif;?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>

<script>
(function(){
  const ENDPOINT = "<?= app_url('/admin/api/dashboard_stats.php'); ?>";
  const el = {
    fac:  document.getElementById('kpi-faculty'),
    res:  document.getElementById('kpi-research'),
    ag:   document.getElementById('kpi-agencies'),
    fund: document.getElementById('kpi-funding'),
    asg:  document.getElementById('kpi-assignment'),
    clock: document.getElementById('live-clock') 
  };
  function number(n){ return (Number(n)||0).toLocaleString(); }
  
  function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    if (el.clock) {
        el.clock.textContent = timeString;
    }
  }

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
  updateClock();
  setInterval(refreshKPIs, 60000); 
  setInterval(updateClock, 1000); 

  // Sort Dropdown Logic
  const sortBtn = document.getElementById('dash-sort-btn');
  const sortMenu = document.getElementById('dash-sort-menu');
  
  if(sortBtn && sortMenu) {
      sortBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          sortMenu.classList.toggle('show');
      });
      document.addEventListener('click', (e) => {
          if (!sortMenu.contains(e.target) && e.target !== sortBtn) {
              sortMenu.classList.remove('show');
          }
      });
  }

})();
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>