<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/partials/admin_header.php';

/* KPI counts */
$k_faculty   = $pdo->query("SELECT COUNT(*) FROM FACULTY")->fetchColumn();
$k_research  = $pdo->query("SELECT COUNT(*) FROM RESEARCH")->fetchColumn();
$k_ongoing   = $pdo->query("SELECT COUNT(*) FROM RESEARCH WHERE RESEARCH_STATUS='ONGOING'")->fetchColumn();
$k_completed = $pdo->query("SELECT COUNT(*) FROM RESEARCH WHERE RESEARCH_STATUS='COMPLETED'")->fetchColumn();

/* Recent activity (last 12) */
$recent = $pdo->query("SELECT * FROM AUDIT_LOG ORDER BY LOG_ID DESC LIMIT 12")->fetchAll();

/* Recent assignments (joined) */
$assign = $pdo->query("
  SELECT a.ASSIGNMENT_ID, a.DATE_ASSIGNED, f.FACULTY_LNAME, f.FACULTY_FNAME, r.RESEARCH_TITLE, a.ROLE_ID
  FROM ASSIGNMENT a
  JOIN FACULTY f ON a.FACULTY_ID=f.FACULTY_ID
  JOIN RESEARCH r ON a.RESEARCH_ID=r.RESEARCH_ID
  ORDER BY a.ASSIGNMENT_ID DESC LIMIT 8
")->fetchAll();
?>

<h1>Dashboard</h1>

<div class="statgrid" style="margin-top:10px">
  <div class="stat"><div>Total Faculty</div><div class="kpi"><?php echo (int)$k_faculty; ?></div></div>
  <div class="stat"><div>Total Research</div><div class="kpi"><?php echo (int)$k_research; ?></div></div>
  <div class="stat"><div>Ongoing Projects</div><div class="kpi"><?php echo (int)$k_ongoing; ?></div></div>
  <div class="stat"><div>Completed Projects</div><div class="kpi"><?php echo (int)$k_completed; ?></div></div>
</div>

<div class="statgrid" style="margin-top:16px">
  <div class="panel" style="grid-column: span 7">
    <h3 style="margin-top:0">Latest Assignments</h3>
    <table style="width:100%;border-collapse:collapse">
      <thead><tr><th align="left">ID</th><th align="left">Faculty</th><th align="left">Research</th><th align="left">Role</th><th align="left">Date</th></tr></thead>
      <tbody>
        <?php foreach($assign as $row): ?>
          <tr>
            <td><?php echo (int)$row['ASSIGNMENT_ID']; ?></td>
            <td><?php echo htmlspecialchars($row['FACULTY_LNAME'].', '.$row['FACULTY_FNAME']); ?></td>
            <td><?php echo htmlspecialchars($row['RESEARCH_TITLE']); ?></td>
            <td><?php echo htmlspecialchars($row['ROLE_ID']); ?></td>
            <td><?php echo htmlspecialchars($row['DATE_ASSIGNED']); ?></td>
          </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <div class="panel" style="grid-column: span 5">
    <h3 style="margin-top:0">Recent Activity</h3>
    <div id="activity">
      <?php foreach ($recent as $log): ?>
        <div class="meta">#<?php echo (int)$log['LOG_ID']; ?> · <?php echo htmlspecialchars($log['ACTION_ENUM']); ?> on <?php echo htmlspecialchars($log['TABLE_NAME']); ?> (<?php echo htmlspecialchars($log['PK_VALUE']); ?>) — <?php echo htmlspecialchars($log['CREATED_AT']); ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
  // Trial for being real-time, poll every 7s for new logs
  setInterval(async () => {
    try{
      const res = await fetch('/admin/recent.php');
      document.getElementById('activity').innerHTML = await res.text();
    }catch(e){}
  }, 7000);
</script>

<a class="btn" href="/admin/audit_print.php" target="_blank">Print Audit Log</a>

<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
