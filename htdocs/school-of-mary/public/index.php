<?php
$pageTitle = 'Welcome';
require_once __DIR__ . '/../partials/site_header.php';

/* Pull words or highlights for cards */
$featuredResearch = $pdo->query("
  SELECT RESEARCH_ID, RESEARCH_TITLE, RESEARCH_STATUS, RESEARCH_STARTDATE, RESEARCH_ENDDATE
  FROM RESEARCH
  ORDER BY RESEARCH_STARTDATE DESC
  LIMIT 6
")->fetchAll();

$featuredFaculty = $pdo->query("
  SELECT FACULTY_ID, FACULTY_FNAME, FACULTY_LNAME, FACULTY_EMAIL
  FROM FACULTY
  ORDER BY FACULTY_LNAME
  LIMIT 6
")->fetchAll();
?>

<!-- HERO -->
<section class="panel fade-in" style="background: linear-gradient(135deg, #003366 0%, #0b5394 100%); color:#fff; padding:28px; border:none;">
  <div class="container" style="display:grid; grid-template-columns: 1.2fr .8fr; gap: 18px; align-items:center;">
    <div>
      <h1 style="font-family:'Patua One', serif; font-size: clamp(28px, 5vw, 44px); line-height:1.1; margin-bottom:8px;">
        Research & Faculty at the <span style="color:#ffcc00">School of Mary</span>
      </h1>
      <p style="max-width: 780px; opacity:.95; margin-bottom:14px;">
        Explore our people and projects. Search across faculty, departments, and research initiatives.
        Admins can sign in to manage content; the public can browse and filter everything read-only.
      </p>

      <!-- quick search strips -->
      <div class="panel" style="background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.2);">
        <form action="/public/faculty.php" method="get" class="grid" style="gap:10px">
          <div class="field" style="grid-column: span 8">
            <label for="qf" style="color:#fff">Search faculty (name or email)</label>
            <input class="input" id="qf" name="q" placeholder="e.g., Santos or maria@..." />
          </div>
          <div class="field" style="grid-column: span 2">
            <label>&nbsp;</label>
            <button class="btn" style="width:100%">Find Faculty</button>
          </div>
          <div class="field" style="grid-column: span 2">
            <label>&nbsp;</label>
            <a class="btn" href="/public/faculty.php" style="width:100%; background:#234b7a">Browse All</a>
          </div>
        </form>

        <form action="/public/research.php" method="get" class="grid" style="gap:10px; margin-top:10px">
          <div class="field" style="grid-column: span 8">
            <label for="qr" style="color:#fff">Search research (title)</label>
            <input class="input" id="qr" name="q" placeholder="e.g., AI, Climate, IoT..." />
          </div>
          <div class="field" style="grid-column: span 2">
            <label>&nbsp;</label>
            <button class="btn" style="width:100%">Find Research</button>
          </div>
          <div class="field" style="grid-column: span 2">
            <label>&nbsp;</label>
            <a class="btn" href="/public/research.php" style="width:100%; background:#234b7a">Browse All</a>
          </div>
        </form>
      </div>
    </div>

    <div class="panel" style="background:#fff; color:#111;">
      <h3 style="margin-top:0; font-family:'Patua One', serif;">Admin Access</h3>
      <?php if (!is_admin()): ?>
        <p class="muted">Admins can sign in to manage faculty, research, agencies, funding, and assignments.</p>
        <a class="btn" href="/admin/login.php">Admin Login</a>
      <?php else: ?>
        <p class="muted">You are signed in as admin.</p>
        <div style="display:flex; gap:8px; flex-wrap:wrap">
          <a class="btn small" href="/admin/dashboard.php">Dashboard</a>
          <a class="btn small" href="/admin/crud/faculty.php">Faculty</a>
          <a class="btn small" href="/admin/crud/research.php">Research</a>
          <a class="btn small" href="/admin/crud/assignment.php">Assignments</a>
          <a class="btn small" href="/admin/crud/agency.php">Agencies</a>
          <a class="btn small" href="/admin/crud/funding.php">Funding</a>
          <a class="btn small" href="/admin/audit_print.php">Audit (Print)</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- FEATURED RESEARCH -->
<section class="container fade-in" style="margin-top: 18px;">
  <div class="panel" style="border:none; background:transparent; box-shadow:none; padding:0;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px;">
      <h2 style="font-family:'Patua One', serif;">Featured Research</h2>
      <a class="btn small" href="/public/research.php">Browse all</a>
    </div>

    <div class="grid" style="gap: 12px">
      <?php foreach ($featuredResearch as $r): ?>
        <a class="panel slide-up" href="/public/research.php?id=<?php echo (int)$r['RESEARCH_ID']; ?>" style="grid-column: span 4; text-decoration:none; color:inherit;">
          <h3 style="margin-top:0;"><?php echo htmlspecialchars($r['RESEARCH_TITLE']); ?></h3>
          <div class="muted" style="font-size:.95rem; margin-top:4px;">
            <span class="pill" style="background:#eef4ff; border:1px solid #cdd8f0; padding:2px 8px; border-radius:999px;">
              <?php echo htmlspecialchars($r['RESEARCH_STATUS']); ?>
            </span>
            <span style="margin-left:6px;">
              Start: <?php echo htmlspecialchars($r['RESEARCH_STARTDATE']); ?>
              <?php if ($r['RESEARCH_ENDDATE']) echo " · End: ".htmlspecialchars($r['RESEARCH_ENDDATE']); ?>
            </span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FEATURED FACULTY -->
<section class="container fade-in" style="margin-top: 10px; margin-bottom: 24px;">
  <div class="panel" style="border:none; background:transparent; box-shadow:none; padding:0;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px;">
      <h2 style="font-family:'Patua One', serif;">Meet Our Faculty</h2>
      <a class="btn small" href="/public/faculty.php">Browse all</a>
    </div>

    <div class="grid" style="gap: 12px">
      <?php foreach ($featuredFaculty as $f): ?>
        <a class="panel slide-up" href="/public/faculty.php?id=<?php echo (int)$f['FACULTY_ID']; ?>" style="grid-column: span 4; text-decoration:none; color:inherit;">
          <h3 style="margin-top:0;"><?php echo htmlspecialchars($f['FACULTY_LNAME'].', '.$f['FACULTY_FNAME']); ?></h3>
          <div class="muted" style="font-size:.95rem; margin-top:4px;">
            <?php echo htmlspecialchars($f['FACULTY_EMAIL']); ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>
