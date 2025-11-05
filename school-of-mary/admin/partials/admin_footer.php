    </main>
  </div>
</div>

<!-- Reusable Modal + feedback roots -->
<div id="modal-overlay" class="modal-overlay" style="display:none">
  <div class="modal">
    <div class="modal-head">
      <h3 id="modal-title" style="margin:0">Edit</h3>
      <button id="modal-x" class="modal-close btn" type="button">✕</button>
    </div>
    <form id="modal-form" method="post" class="modal-body grid">
      <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="update">
    </form>
    <div class="modal-foot">
      <button type="submit" form="modal-form" class="btn primary">Save changes</button>
    </div>
  </div>
</div>
<div id="spinner-overlay"><div class="spinner"></div></div>
<div id="toast-root" aria-live="polite" aria-atomic="true"></div>

<script src="/../assets/admin.js"></script>
</body>
</html>
