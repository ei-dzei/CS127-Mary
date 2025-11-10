<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../partials/site_header.php';

if (!is_admin()) {
  header('Location: /admin/login.php');
  exit;
}

/* KPIs */
$kpi = [
  'faculty'    => (int)$pdo->query("SELECT COUNT(*) FROM FACULTY")->fetchColumn(),
  'research'   => (int)$pdo->query("SELECT COUNT(*) FROM RESEARCH")->fetchColumn(),
  'agencies'   => (int)$pdo->query("SELECT COUNT(*) FROM AGENCY")->fetchColumn(),
  'funding'    => (int)$pdo->query("SELECT COUNT(*) FROM FUNDING")->fetchColumn(),
  'assignment' => (int)$pdo->query("SELECT COUNT(*) FROM ASSIGNMENT")->fetchColumn(),
];

/* Top research by total funding */
$topResearch = $pdo->query("
  SELECT re.RESEARCH_ID, re.RESEARCH_TITLE,
         COALESCE(SUM(fu.FUNDING_AMOUNT),0) AS total
  FROM RESEARCH re
  LEFT JOIN FUNDING fu ON fu.RESEARCH_ID = re.RESEARCH_ID
  GROUP BY re.RESEARCH_ID
  ORDER BY total DESC
  LIMIT 5
")->fetchAll();

/* Latest 10 audit rows */
$audit = $pdo->query("
  SELECT ID, CREATED_AT, ACTOR, ACTION_ENUM, TABLE_NAME, PK_VALUE
  FROM AUDIT_LOG
  ORDER BY ID DESC
  LIMIT 10
")->fetchAll();

?>
<section class="panel fade-in">
  <h1 style="margin-bottom:8px;">Admin Dashboard</h1>
  <p class="muted">Overview of your database and the latest changes in real time.</p>
</section>

<section class="container fade-in" style="margin-bottom: 16px;">
  <div class="grid">
    <div class="panel" style="grid-column: span 2; background:#fff;">
      <h3 style="margin-top:0;">Faculty</h3>
      <div style="font-size:1.8rem; font-weight:700;"><?php echo number_format($kpi['faculty']); ?></div>
      <a class="btn small" href="/admin/crud/faculty.php" style="margin-top:8px;">Manage</a>
    </div>

    <div class="panel" style="grid-column: span 2; background:#fff;">
      <h3 style="margin-top:0;">Research</h3>
      <div style="font-size:1.8rem; font-weight:700;"><?php echo number_format($kpi['research']); ?></div>
      <a class="btn small" href="/admin/crud/research.php" style="margin-top:8px;">Manage</a>
    </div>

    <div class="panel" style="grid-column: span 2; background:#fff;">
      <h3 style="margin-top:0;">Agencies</h3>
      <div style="font-size:1.8rem; font-weight:700;"><?php echo number_format($kpi['agencies']); ?></div>
      <a class="btn small" href="/admin/crud/agency.php" style="margin-top:8px;">Manage</a>
    </div>

    <div class="panel" style="grid-column: span 2; background:#fff;">
      <h3 style="margin-top:0;">Funding Rows</h3>
      <div style="font-size:1.8rem; font-weight:700;"><?php echo number_format($kpi['funding']); ?></div>
      <a class="btn small" href="/admin/crud/funding.php" style="margin-top:8px;">Manage</a>
    </div>

    <div class="panel" style="grid-column: span 2; background:#fff;">
      <h3 style="margin-top:0;">Assignments</h3>
      <div style="font-size:1.8rem; font-weight:700;"><?php echo number_format($kpi['assignment']); ?></div>
      <a class="btn small" href="/admin/crud/assignment.php" style="margin-top:8px;">Manage</a>
    </div>

    <div class="panel" style="grid-column: span 2; background:#fff;">
      <h3 style="margin-top:0;">Audit (Print)</h3>
      <p class="muted" style="margin:0 0 8px;">Formal printable log of changes.</p>
      <a class="btn small" href="/admin/audit_print.php" target="_blank">Open Print View</a>
    </div>
  </div>
</section>

<section class="container fade-in" style="margin-bottom: 22px;">
  <div class="grid">
    <!-- Top Research by Funding -->
    <div class="panel" style="grid-column: span 6; background:#fff;">
      <h3 style="margin-top:0;">Top Research by Total Funding</h3>
      <?php if (!$topResearch): ?>
        <div class="muted">No data.</div>
      <?php else: ?>
        <table style="margin-top:8px;">
          <thead><tr><th>Research</th><th>Total Funding</th></tr></thead>
          <tbody>
            <?php foreach ($topResearch as $tr): ?>
              <tr>
                <td>
                  <a href="/public/research.php?id=<?php echo (int)$tr['RESEARCH_ID']; ?>">
                    <?php echo htmlspecialchars($tr['RESEARCH_TITLE']); ?>
                  </a>
                </td>
                <td><?php echo '₱' . number_format($tr['total'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Live Activity Stream -->
    <div class="panel" style="grid-column: span 6; background:#fff;">
      <h3 style="margin-top:0;">Live Activity</h3>
      <table id="audit-table" style="margin-top:8px;">
        <thead>
          <tr>
            <th style="width:80px;">ID</th>
            <th style="width:160px;">When</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Table</th>
            <th>PK</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($audit as $row): ?>
            <tr data-id="<?php echo (int)$row['ID']; ?>">
              <td><?php echo (int)$row['ID']; ?></td>
              <td><?php echo htmlspecialchars($row['CREATED_AT']); ?></td>
              <td><?php echo htmlspecialchars($row['ACTOR']); ?></td>
              <td><?php echo htmlspecialchars($row['ACTION_ENUM']); ?></td>
              <td><?php echo htmlspecialchars($row['TABLE_NAME']); ?></td>
              <td><?php echo htmlspecialchars($row['PK_VALUE']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" id="audit-hint" style="margin-top:6px;">Auto-refreshing every 5 seconds…</p>
    </div>
  </div>
</section>

<script>
  // Light polling for live audit updates
  (function () {
    const table = document.getElementById('audit-table').querySelector('tbody');
    let latestId = 0;

    function initLatest() {
      const first = table.querySelector('tr[data-id]');
      if (first) latestId = parseInt(first.getAttribute('data-id'), 10) || 0;
    }
    initLatest();

    async function poll() {
      try {
        const res = await fetch('/admin/audit_feed.php?after=' + encodeURIComponent(latestId), { credentials: 'same-origin' });
        if (!res.ok) return;
        const rows = await res.json();
        if (Array.isArray(rows) && rows.length) {
          // prepend new rows
          rows.forEach((r) => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-id', r.ID);
            tr.innerHTML = `
              <td>${r.ID}</td>
              <td>${r.CREATED_AT}</td>
              <td>${r.ACTOR}</td>
              <td>${r.ACTION_ENUM}</td>
              <td>${r.TABLE_NAME}</td>
              <td>${r.PK_VALUE}</td>
            `;
            table.insertBefore(tr, table.firstChild);
          });
          latestId = Math.max(latestId, ...rows.map(r => parseInt(r.ID, 10)));
          window.SOM && SOM.toast('Live log updated');
        }
      } catch (e) {
        // silent fail to avoid console spam in class demos
      }
    }

    setInterval(poll, 5000);
  })();
</script>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
