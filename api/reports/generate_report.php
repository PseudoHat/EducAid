<?php
/**
 * Report Generation API Endpoint
 * Handles preview and export requests for reports
 */

// Suppress all output and errors before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

require_once __DIR__ . '/../../includes/CSRFProtection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin authentication check
if (!isset($_SESSION['admin_username'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

include __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/report_generator.php';

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Verify CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRFProtection::validateToken('generate_report', $_POST['csrf_token'] ?? '', false)) {
            throw new Exception('Invalid CSRF token');
        }
    }
    
    // Get admin context
    $adminId = $_SESSION['admin_id'] ?? null;
    $adminMunicipalityId = null;
    $adminRole = 'sub_admin';
    
    if ($adminId) {
        $admRes = pg_query_params($connection, "SELECT municipality_id, role FROM admins WHERE admin_id = $1", [$adminId]);
        if ($admRes && pg_num_rows($admRes)) {
            $admRow = pg_fetch_assoc($admRes);
            $adminMunicipalityId = $admRow['municipality_id'];
            $adminRole = $admRow['role'];
        }
    }
    
    // Initialize report generator
    $reportGen = new ReportGenerator($connection);
    
    // Set municipality context if admin is not super_admin
    if ($adminRole !== 'super_admin' && $adminMunicipalityId) {
        $reportGen->setMunicipalityContext($adminMunicipalityId);
        // Force municipality filter for sub-admins
        $_POST['municipality_id'] = $adminMunicipalityId;
        $_GET['municipality_id'] = $adminMunicipalityId;
    } elseif (!empty($_POST['municipality_id']) || !empty($_GET['municipality_id'])) {
        $reportGen->setMunicipalityContext($_POST['municipality_id'] ?? $_GET['municipality_id']);
    }
    
    switch ($action) {
        case 'preview':
            // Set JSON header for preview
            header('Content-Type: application/json');
            
            // Get preview data (limited to 50 records)
            $filterData = $_POST;
            unset($filterData['action'], $filterData['csrf_token']);
            
            $previewData = $reportGen->getPreviewData($filterData, 50);
            
            // Log audit trail
            pg_query_params($connection, 
                "INSERT INTO audit_logs (user_id, user_type, username, event_type, event_category, action_description, metadata) 
                 VALUES ($1, $2, $3, $4, $5, $6, $7)",
                [
                    $adminId,
                    'admin',
                    $_SESSION['admin_username'],
                    'report_preview',
                    'reporting',
                    'Previewed report with filters',
                    json_encode(['filters' => $filterData, 'result_count' => $previewData['total']])
                ]
            );
            
            // Clean output buffer and send JSON
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'data' => $previewData
            ]);
            break;
            
        case 'export_pdf':
            // Generate and download PDF
            $filterData = $_POST;
            unset($filterData['action'], $filterData['csrf_token']);
            
            $reportType = $_POST['report_type'] ?? 'student_list';
            
            // Log audit trail
            pg_query_params($connection, 
                "INSERT INTO audit_logs (user_id, user_type, username, event_type, event_category, action_description, metadata) 
                 VALUES ($1, $2, $3, $4, $5, $6, $7)",
                [
                    $adminId,
                    'admin',
                    $_SESSION['admin_username'],
                    'report_export',
                    'reporting',
                    'Exported PDF report',
                    json_encode(['filters' => $filterData, 'report_type' => $reportType])
                ]
            );
            
            // This will output the PDF directly and exit
            $reportGen->generatePDF($filterData, $reportType);
            break;
            
        case 'export_excel':
            // Generate and download Excel
            $filterData = $_POST;
            unset($filterData['action'], $filterData['csrf_token']);
            
            // Log audit trail
            pg_query_params($connection, 
                "INSERT INTO audit_logs (user_id, user_type, username, event_type, event_category, action_description, metadata) 
                 VALUES ($1, $2, $3, $4, $5, $6, $7)",
                [
                    $adminId,
                    'admin',
                    $_SESSION['admin_username'],
                    'report_export',
                    'reporting',
                    'Exported Excel report',
                    json_encode(['filters' => $filterData])
                ]
            );
            
            // This will output the Excel file directly and exit
            $reportGen->generateExcel($filterData, true);
            break;
            
        case 'get_statistics':
            // Get statistics only
            $filterData = $_GET;
            unset($filterData['action']);
            
            $filters = new ReportFilters($connection);
            $filters->setFilters($filterData);
            $filters->buildStudentQuery(false);
            
            $stats = $filters->getStatistics();
            $distributions = $filters->getDistributionBreakdown();
            
            // Clean output buffer and send JSON
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'distributions' => $distributions,
                'filter_summary' => $filters->getFilterSummary()
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    // Log error
    error_log('Report API Error: ' . $e->getMessage());
}
