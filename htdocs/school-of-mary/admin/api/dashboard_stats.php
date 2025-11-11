<?php
// Dashboard stats (admin only)
require_once __DIR__ . '/../../partials/init.php';

header('Content-Type: application/json');

if (!is_admin()) {
  http_response_code(403);
  echo json_encode(['error' => 'forbidden']);
  exit;
}

// KPI counts
$kpi = [
  'faculty'    => (int)$pdo->query("SELECT COUNT(*) FROM FACULTY")->fetchColumn(),
  'research'   => (int)$pdo->query("SELECT COUNT(*) FROM RESEARCH")->fetchColumn(),
  'agencies'   => (int)$pdo->query("SELECT COUNT(*) FROM AGENCY")->fetchColumn(),
  'funding'    => (int)$pdo->query("SELECT COUNT(*) FROM FUNDING")->fetchColumn(),
  'assignment' => (int)$pdo->query("SELECT COUNT(*) FROM ASSIGNMENT")->fetchColumn(),
];

// Research by status
$byStatus = $pdo->query("
  SELECT RESEARCH_STATUS AS status, COUNT(*) AS cnt
  FROM RESEARCH
  GROUP BY RESEARCH_STATUS
  ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Funding per month (last 12 months)
$fundingMonthly = $pdo->query("
  SELECT DATE_FORMAT(DATE_FUNDED, '%Y-%m') AS ym,
         SUM(FUNDING_AMOUNT) AS total
  FROM FUNDING
  WHERE DATE_FUNDED IS NOT NULL
        AND DATE_FUNDED >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
  GROUP BY ym
  ORDER BY ym ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Top departments by faculty count
$deptTop = $pdo->query("
  SELECT d.DEPT_SPECIALIZATION AS department, COUNT(*) AS cnt
  FROM FACULTY f
  JOIN DEPARTMENT d ON d.DEPT_ID = f.DEPT_ID
  GROUP BY d.DEPT_ID
  ORDER BY cnt DESC
  LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Most funded research (top 5)
$topResearch = $pdo->query("
  SELECT re.RESEARCH_ID, re.RESEARCH_TITLE,
         COALESCE(SUM(fu.FUNDING_AMOUNT),0) AS total
  FROM RESEARCH re
  LEFT JOIN FUNDING fu ON fu.RESEARCH_ID = re.RESEARCH_ID
  GROUP BY re.RESEARCH_ID
  ORDER BY total DESC
  LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'kpi'            => $kpi,
  'researchStatus' => $byStatus,
  'fundingMonthly' => $fundingMonthly,
  'deptTop'        => $deptTop,
  'topResearch'    => $topResearch,
  'generatedAt'    => date('c'),
]);
exit;
