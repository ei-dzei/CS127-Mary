<?php
// ... (PHP initializers remain the same)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../partials/init.php';
$pageTitle = 'Home';
require_once __DIR__ . '/../partials/site_header.php';

// --- Placeholder for dynamic data (GET THESE FROM YOUR DATABASE) ---
// In a real application, these would be queried from the DB.
$stats = [
    'faculty' => 45,
    'projects' => 128,
    'funding' => '$5.2M'
];
?>

<section class="p-5 p-md-0 position-relative text-white" 
         style="background: url('bg1.png') center/cover no-repeat; min-height: 500px;">
    
    <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-75"></div>
    
    <div class="container d-flex align-items-center h-100 position-relative py-5">
        <div class="col-lg-8">
            <h1 class="display-3 fw-bold mb-3">
                Unlock Academic Excellence: The School of Mary Research Portal
            </h1>
            <p class="lead mb-4">
                Discover faculty expertise, explore groundbreaking studies, and trace the impact of funded research, 
                driving knowledge and innovation forward.
            </p>
            <div class="d-grid gap-3 d-sm-flex justify-content-start">
                <a href="<?= BASE_URL ?>/public/research.php" class="btn btn-warning btn-lg shadow">
                    <i class="bi bi-search me-2"></i> Start Exploring Research
                </a>
                <a href="<?= BASE_URL ?>/public/faculty.php" class="btn btn-outline-light btn-lg">
                    Meet Our Faculty
                </a>
            </div>
        </div>
    </div>
</section>

---

<section class="bg-light py-4 shadow-sm" aria-label="Portal Statistics">
    <div class="container">
        <div class="row text-center">
            
            <div class="col-md-4 mb-3 mb-md-0">
                <p class="display-4 fw-bold text-primary mb-0"><?= $stats['faculty'] ?>+</p>
                <p class="lead text-muted">Faculty Profiles</p>
            </div>
            
            <div class="col-md-4 mb-3 mb-md-0 border-start border-end">
                <p class="display-4 fw-bold text-primary mb-0"><?= $stats['projects'] ?>+</p>
                <p class="lead text-muted">Research Projects</p>
            </div>
            
            <div class="col-md-4">
                <p class="display-4 fw-bold text-primary mb-0"><?= $stats['funding'] ?></p>
                <p class="lead text-muted">Total Funding Secured</p>
            </div>
        </div>
    </div>
</section>

---

<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-2">Navigate Our Research Ecosystem</h2>
        <p class="lead text-center text-muted mb-5">
            Jump directly to the data that matters most.
        </p>
        
        <div class="row g-4">
            
            <div class="col-lg-4">
                <a href="<?= BASE_URL ?>/public/faculty.php" class="card h-100 shadow-lg border-0 text-decoration-none transition-hover">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-person-workspace h1 text-success mb-3"></i>
                        <h3 class="card-title h4">Faculty Expertise</h3>
                        <p class="card-text text-muted">View profiles, specializations, and publications by researcher.</p>
                        <span class="btn btn-sm btn-outline-success mt-2">Browse</span>
                    </div>
                </a>
            </div>

            <div class="col-lg-4">
                <a href="<?= BASE_URL ?>/public/research.php" class="card h-100 shadow-lg border-0 text-decoration-none transition-hover">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-journal-check h1 text-info mb-3"></i>
                        <h3 class="card-title h4">Ongoing Studies</h3>
                        <p class="card-text text-muted">Filter by status, date, and collaborators on all projects.</p>
                        <span class="btn btn-sm btn-outline-info mt-2">View Projects</span>
                    </div>
                </a>
            </div>

            <div class="col-lg-4">
                <a href="<?= BASE_URL ?>/public/agencies.php" class="card h-100 shadow-lg border-0 text-decoration-none transition-hover">
                    <div class="card-body text-center p-4">
                        <i class="bi bi-cash-stack h1 text-warning mb-3"></i>
                        <h3 class="card-title h4">Funding & Agencies</h3>
                        <p class="card-text text-muted">See the institutions and resources driving our research.</p>
                        <span class="btn btn-sm btn-outline-warning mt-2">Explore Funds</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

---

<section class="py-5 bg-light border-top">
    <div class="container">
        <div class="row g-4">
            
            <div class="col-md-6">
                <div class="alert alert-primary h-100 p-4" role="alert">
                    <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i> About the Portal</h4>
                    <p>
                        The **School of Mary Research Portal** is the central hub for managing and showcasing
                        academic achievements, faculty expertise, funding records, and collaborative agencies.
                        It is designed for public transparency and ease of access.
                    </p>
                </div>
            </div>

            <div class="col-md-6">
                <div class="alert alert-danger h-100 p-4 d-flex flex-column justify-content-between" role="alert">
                    <div>
                        <h4 class="alert-heading"><i class="bi bi-lock-fill me-2"></i> Administrative Access</h4>
                        <p>
                            Authorized administrators can securely log in to the Dashboard to manage records, 
                            perform CRUD operations, import data, and generate essential audit reports.
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>/admin/login.php" class="btn btn-danger mt-3 align-self-start">
                        Go to Admin Login
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../partials/site_footer.php'; ?>