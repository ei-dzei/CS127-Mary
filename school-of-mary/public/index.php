<?php
$pageTitle = 'Welcome';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../partials/header.php';
?>

<section class="hero">
  <div class="container">
    <h1>Discover Faculty & Research at the <span style="color:var(--crimson)">School of Mary</span></h1>
    <p>Browse faculty profiles, explore ongoing and completed research, and learn about our academic community. 
       This public directory is powered by our Faculty Research Management System.</p>

    <div class="search-panel">
      <form action="/public/faculty.php" method="get" class="grid">
        <div class="field" style="grid-column: span 5">
          <label for="q">Search faculty or research (by name/title)</label>
          <input class="input" type="text" id="q" name="q" placeholder="e.g., Miguel or Computer" />
        </div>
        <div class="field" style="grid-column: span 3">
          <label for="dept">Department</label>
          <select id="dept" name="dept">
            <option value="">All</option>
            <?php
              $stmt = $pdo->query("SELECT DEPT_ID, DEPT_SPECIALIZATION FROM DEPARTMENT ORDER BY DEPT_SPECIALIZATION");
              foreach ($stmt as $row){
                echo '<option value="'.htmlspecialchars($row['DEPT_ID']).'">'.htmlspecialchars($row['DEPT_SPECIALIZATION']).'</option>';
              }
            ?>
          </select>
        </div>
        <div class="field" style="grid-column: span 3">
          <label for="rank">Rank</label>
          <select id="rank" name="rank">
            <option value="">All</option>
            <?php
              $stmt = $pdo->query("SELECT RANK_ID, RANK_DESCRIPTION FROM `RANK` ORDER BY RANK_LEVEL");
              foreach ($stmt as $row){
                echo '<option value="'.htmlspecialchars($row['RANK_ID']).'">'.htmlspecialchars($row['RANK_DESCRIPTION']).'</option>';
              }
            ?>
          </select>
        </div>
        <div class="field" style="grid-column: span 1; display:flex; align-items:flex-end;">
          <button class="btn" type="submit">Search</button>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="container" id="admin" style="margin-top:28px">
  <div class="detail">
    <h2 style="margin:0 0 8px">Admin Access</h2>
    <p class="muted">Admins can sign in to manage faculty, research, agencies, funding, and assignments (CRUD).</p>
    <p style="margin-top:10px"><a class="btn" href="/public/research.php?admin=1">Go to Admin landing</a></p>
  </div>
</section>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
