<?php

// 1. Load the initialization file (adjust path if your folder structure differs)
// This connects to the database ($pdo) and starts the session
require_once __DIR__ . '/../../partials/init.php';

// 2. Security Check: Only admins should see this data
if (!is_admin()) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// 3. Set JSON Header
header('Content-Type: application/json');

try {
    // 4. Query the Database
    // We get the ID (for the link), Title (for the text), and Start Date (for placement)
    // We exclude records with NO start date so they don't break the calendar logic
    $stmt = $pdo->query("
        SELECT RESEARCH_ID, RESEARCH_TITLE, RESEARCH_STARTDATE 
        FROM RESEARCH 
        WHERE RESEARCH_STARTDATE IS NOT NULL AND RESEARCH_STARTDATE != ''
    ");
    
    $events = [];
    
    // 5. Format the data for the JavaScript
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id'    => (int)$row['RESEARCH_ID'],
            'title' => $row['RESEARCH_TITLE'],
            'start' => $row['RESEARCH_STARTDATE'] // Format: YYYY-MM-DD
        ];
    }

    // 6. Output JSON
    echo json_encode($events);

} catch (Exception $e) {
    // Handle database errors gracefully
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>