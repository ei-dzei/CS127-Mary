<?php ?>
</main>

<footer class="footer">
  <div class="container footer__inner">
    <div>© <?= date('Y') ?> School of Mary</div>
  </div>
</footer>

<!-- ============================
     Admin Modal
============================= -->
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

<!-- ============================
     Read More Overlay (Faculty / Research)
============================= -->
<div id="overlay" class="overlay" hidden>
  <div class="overlay__backdrop" data-close="overlay"></div>

  <div class="overlay__dialog" role="dialog" aria-modal="true" aria-labelledby="overlay-title">
    <!-- Close button inside header -->
    <div class="overlay__header">
      <h3 id="overlay-title" class="overlay__title">Details</h3>
      <button class="overlay__close" type="button" aria-label="Close" data-close="overlay">×</button>
    </div>

    <!-- Body content dynamically filled by app.js -->
    <div id="overlay-body" class="overlay__body">
      <div class="overlay__loading">Loading…</div>
    </div>
  </div>
</div>

<!-- Print stylesheet -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/print.css" media="print" />

<script src="<?= BASE_URL ?>/assets/app.js" defer></script>

</body>
</html>
