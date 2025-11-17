<?php
require_once '../init.php';

// Check if user is logged in (Authentication logic)
if (!is_admin()) {
    redirect_to(BASE_URL . '/admin/login.php');
}

$title = 'Calendar View';
include '../partials/site_header.php'; 
?>

<div class="container admin-wide" style="padding-top: 2rem;">
    <div class="crud-header-card">
        <h1>Research Calendar</h1>
        <p class="muted">Visualize project deadlines, faculty leaves, and funding due dates.</p>
    </div>

    <div id="calendar-app" class="panel" style="padding: 0;">
        <div class="calendar-header">
            <button id="prev-month" class="btn small">← Previous</button>
            <h2 id="current-month-year">November 2025</h2>
            <button id="next-month" class="btn small">Next →</button>
        </div>
        
        <div class="calendar-grid-header">
            <div>SUN</div>
            <div>MON</div>
            <div>TUE</div>
            <div>WED</div>
            <div>THU</div>
            <div>FRI</div>
            <div>SAT</div>
        </div>

        <div id="calendar-days" class="calendar-grid">
            </div>

    </div>

</div>

<?php 

?>

<?php include '../partials/site_footer.php'; ?>