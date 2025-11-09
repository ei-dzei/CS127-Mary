<?php

?>
</main>

<footer class="footer">
  <div class="container footer__inner">
    <div>© <?php echo date('Y'); ?> School of Mary</div>
    <nav class="footernav">
      <a href="/public/">Home</a>
      <a href="/public/faculty.php">Faculty</a>
      <a href="/public/research.php">Research</a>
      <?php if (!is_admin()): ?>
        <a href="/admin/login.php">Admin Login</a>
      <?php else: ?>
        <a href="/admin/dashboard.php">Dashboard</a>
      <?php endif; ?>
    </nav>
  </div>
</footer>

<!-- Shared Modal (used globally on CRUD pages) -->
<div id="modal" class="modal" hidden>
  <div class="modal__dialog">
    <div class="modal__head">
      <h3 id="modal-title">Edit</h3>
      <button type="button" class="modal__close" data-close="modal" aria-label="Close">×</button>
    </div>

    <!-- This form is reused. -->
    <form id="modal-form" method="post" class="grid">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
      <input type="hidden" name="action" value="">
      <!-- fields are injected by each page (via JS or inline <script>) -->

      <div class="modal__actions">
        <button class="btn primary" type="submit">Save</button>
        <button class="btn" type="button" data-close="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Print stylesheet (formal/professional for paper) -->
<link rel="stylesheet" href="/assets/print.css" media="print" />

</body>
</html>
