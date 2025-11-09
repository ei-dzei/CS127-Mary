<?php require_once __DIR__ . '/partials/admin_header.php'; ?>
<h1>Dashboard</h1>

<div class="grid">
  <div class="card kpi" style="grid-column:span 3">
    <div class="muted">Faculty</div>
    <div class="num" id="kpi-faculty">—</div>
  </div>
  <div class="card kpi" style="grid-column:span 3">
    <div class="muted">Research</div>
    <div class="num" id="kpi-research">—</div>
  </div>
  <div class="card kpi" style="grid-column:span 3">
    <div class="muted">Assignments</div>
    <div class="num" id="kpi-assign">—</div>
  </div>
  <div class="card kpi" style="grid-column:span 3">
    <div class="muted">Total Funding (₱)</div>
    <div class="num" id="kpi-funding">—</div>
  </div>

  <div class="card" style="grid-column:span 8">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <strong>Recent Activity</strong>
      <a class="btn" href="/audit_print.php" target="_blank">Print Audit Log</a>
    </div>
    <table class="table">
      <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Table</th><th>PK</th></tr></thead>
      <tbody id="audit-tbody"><tr><td colspan="5" class="muted">Loading…</td></tr></tbody>
    </table>
  </div>

  <div class="card" style="grid-column:span 4">
    <strong>Quick Actions</strong>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
      <a class="btn" href="/crud/faculty.php">Manage Faculty</a>
      <a class="btn" href="/crud/research.php">Manage Research</a>
      <a class="btn" href="/crud/assignment.php">Manage Assignments</a>
      <a class="btn" href="/crud/agency.php">Manage Agencies</a>
      <a class="btn" href="/crud/funding.php">Manage Funding</a>
    </div>
  </div>
</div>

<script>
async function loadStats(){
  try{
    const res = await fetch('/api/dashboard_stats.php',{credentials:'same-origin'});
    const data = await res.json();
    document.getElementById('kpi-faculty').textContent  = data.counts.faculty;
    document.getElementById('kpi-research').textContent = data.counts.research;
    document.getElementById('kpi-assign').textContent   = data.counts.assignment;
    document.getElementById('kpi-funding').textContent  = (Math.round((data.counts.funding_total||0)*100)/100).toLocaleString();
    const tbody = document.getElementById('audit-tbody');
    tbody.innerHTML = '';
    data.audit.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${r.CREATED_AT}</td><td>${escapeHtml(r.ACTOR||'')}</td>
        <td>${escapeHtml(r.ACTION_ENUM||'')}</td><td>${escapeHtml(r.TABLE_NAME||'')}</td>
        <td>${escapeHtml(r.PK_VALUE||'')}</td>`;
      tbody.appendChild(tr);
    });
    if (!data.audit.length){
      const tr = document.createElement('tr'); tr.innerHTML = '<td colspan="5" class="muted">No activity yet.</td>'; tbody.appendChild(tr);
    }
  }catch(e){ console.error(e); }
}
function escapeHtml(s){return (s??'').toString().replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}
loadStats();
setInterval(loadStats, 10000); // “real-time” refresh every 10s
</script>

<?php require_once __DIR__ . '/partials/admin_footer.php'; ?>
