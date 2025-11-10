<?php  ?>
</main>

<footer class="footer">
  <div class="container footer__inner">
    <div>© <?= date('Y') ?> School of Mary</div>
    <nav class="footernav">
      <a href="<?= BASE_URL ?>/">Home</a>
      <a href="<?= BASE_URL ?>/public/faculty.php">Faculty</a>
      <a href="<?= BASE_URL ?>/public/research.php">Research</a>
      <a href="<?= BASE_URL ?>/public/agencies.php">Agencies</a>
      <?php if (!is_admin()): ?>
        <a href="<?= BASE_URL ?>/admin/login.php">Admin Login</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a>
      <?php endif; ?>
    </nav>
  </div>
</footer>

<!-- Modal -->
<div id="modal" class="modal" hidden>
  <div class="modal__dialog">
    <div class="modal__head">
      <h3 id="modal-title">Edit</h3>
      <button type="button" class="modal__close" data-close="modal" aria-label="Close">×</button>
    </div>

    <form id="modal-form" method="post" class="grid">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <input type="hidden" name="action" value="">
      <!-- Page-specific inputs gets injected -->
      <div class="modal__actions">
        <button class="btn primary" type="submit">Save</button>
        <button class="btn" type="button" data-close="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Print stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/print.css" media="print" />

</body>
</html>
