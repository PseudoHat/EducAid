<?php
/**
 * API Endpoint: Get Graduating Students List
 * Returns JSON array of graduating students
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include __DIR__ . '/../../config/database.php';
include __DIR__ . '/../../includes/permissions.php';

// Check if user is super admin
$admin_role = getCurrentAdminRole($connection);
if ($admin_role !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

// Get graduating students from session (if available from modal)
$graduates = $_SESSION['pending_graduates'] ?? [];

// If not in session, fetch from database
if (empty($graduates)) {
    $current_year_query = pg_query($connection, "SELECT value FROM config WHERE key = 'current_academic_year'");
    if ($current_year_query) {
        $year_row = pg_fetch_assoc($current_year_query);
        $current_academic_year = $year_row['value'] ?? '';
        
        // Fetch ALL graduating students from years BEFORE the current academic year
        if (!empty($current_academic_year)) {
            $graduates_query = pg_query_params($connection,
                "SELECT student_id, first_name, last_name, current_year_level, status_academic_year, 
                        email, status
                 FROM students
                 WHERE is_graduating = TRUE
                   AND status_academic_year < $1
                   AND status IN ('active', 'applicant')
                   AND (is_archived = FALSE OR is_archived IS NULL)
                 ORDER BY status_academic_year DESC, current_year_level DESC, last_name",
                [$current_academic_year]
            );
            
            if ($graduates_query) {
                while ($grad = pg_fetch_assoc($graduates_query)) {
                    $graduates[] = $grad;
                }
            }
        }
    }
}

echo json_encode($graduates);
pg_close($connection);
