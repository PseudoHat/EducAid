<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
if (!isset($_SESSION['student_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}
$studentId = $_SESSION['student_id'];
require_once __DIR__ . '/../../config/database.php';

$historyRes = pg_query_params($connection, "SELECT payroll_no, academic_year, semester, assigned_at FROM distribution_payrolls WHERE student_id=$1 ORDER BY assigned_at ASC", [$studentId]);
$history = $historyRes ? pg_fetch_all($historyRes) ?: [] : [];

echo json_encode(['success'=>true,'history'=>$history]);
if ($connection) { pg_close($connection); }
