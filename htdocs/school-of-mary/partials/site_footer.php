</main>

<footer class="footer">
    <div class="container footer__grid">
        
<div class="footer__col footer__brand">
            <p>School of Mary</p>
            <p class="footer__tagline">Excellence in Research and Innovation</p>
            <p class="footer__legal">
                Copyright &copy; <?= date('Y'); ?> School of Mary. All Rights Reserved.
            </p>
        </div>
        <div class="footer__col">
            <h4 class="footer__heading">Quick Links</h4>
            <div class="footer__inline-links">
                <a href="<?= BASE_URL ?>/public/">Home</a> |
                <a href="<?= BASE_URL ?>/public/faculty.php">Faculty</a> |
                <a href="<?= BASE_URL ?>/public/research.php">Research</a> |
                <a href="<?= BASE_URL ?>/admin/login.php">Admin Login</a>
            </div>
        </div>

        <div class="footer__col">
            <h4 class="footer__heading">Contact Us</h4>
            <ul class="footer__list footer__contact">
                <li><i class="icon">📞</i> (02) 8555-1234</li>
                <li><i class="icon">📧</i> info@somary.edu.ph</li>
                <li><i class="icon">📍</i> 123 Research Lane, City, 1000</li>
                <li><i class="icon">🕒</i> Mon - Fri: 8:00 AM - 5:00 PM</li>
            </ul>
        </div>
    </div>
</footer>

<div id="modal" class="modal" hidden>
  <div class="modal__dialog">
    <div class="modal__head">
      <h3 id="modal-title">Edit</h3>
      <button type="button" class="modal__close" data-close="modal" aria-label="Close">×</button>
    </div>

    <form id="modal-form" method="post" class="grid">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="">
      <div class="modal__actions">
        <button class="btn primary" type="submit">Save</button>
        <button class="btn" type="button" data-close="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<div id="overlay" class="overlay" hidden>
  <div class="overlay__backdrop" data-close="overlay"></div>

  <div class="overlay__dialog" role="dialog" aria-modal="true" aria-labelledby="overlay-title">
    <div class="overlay__header">
      <h3 id="overlay-title" class="overlay__title">Details</h3>
      <button class="overlay__close" type="button" aria-label="Close" data-close="overlay">×</button>
    </div>

    <div id="overlay-body" class="overlay__body">
      <div class="overlay__loading">Loading…</div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/print.css" media="print" />

<script src="<?= BASE_URL ?>/assets/app.js" defer></script>

</body>
</html>