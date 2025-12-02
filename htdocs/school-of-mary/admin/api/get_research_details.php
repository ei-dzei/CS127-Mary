<?php
// admin/api/get_research_details.php
require_once __DIR__ . '/../../partials/init.php';

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// 1. Fetch Basic Info
$stmt = $pdo->prepare("SELECT * FROM RESEARCH WHERE RESEARCH_ID = ?");
$stmt->execute([$id]);
$research = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$research) {
    echo json_encode(['error' => 'Not found']);
    exit;
}

// 2. Fetch Assignments (Faculty)
$as = $pdo->prepare("
    SELECT a.ASSIGNMENT_ID, a.DATE_ASSIGNED, a.ROLE_ID,
            f.FACULTY_FNAME, f.FACULTY_INITIAL, f.FACULTY_LNAME,
            r.RANK_DESCRIPTION
    FROM ASSIGNMENT a
    JOIN FACULTY f ON f.FACULTY_ID = a.FACULTY_ID
    JOIN `RANK` r ON r.RANK_ID = f.RANK_ID
    WHERE a.RESEARCH_ID = ?
    ORDER BY a.DATE_ASSIGNED DESC
");
$as->execute([$id]);
$faculty = $as->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Funding
$fs = $pdo->prepare("
    SELECT fu.FUNDING_AMOUNT, fu.DATE_FUNDED, ag.AGENCY_NAME
    FROM FUNDING fu
    JOIN AGENCY ag ON ag.AGENCY_ID = fu.AGENCY_ID
    WHERE fu.RESEARCH_ID = ?
    ORDER BY fu.DATE_FUNDED DESC
");
$fs->execute([$id]);
$funding = $fs->fetchAll(PDO::FETCH_ASSOC);

// Return everything as JSON
header('Content-Type: application/json');
echo json_encode([
    'details' => $research,
    'faculty' => $faculty,
    'funding' => $funding
]);