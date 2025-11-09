<?php
/**
 * Simple PDF Generation Test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting PDF test...<br>";

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set admin session for testing
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'test_admin';

echo "Session set...<br>";

// Include dependencies
include __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/report_generator.php';

echo "Dependencies loaded...<br>";

try {
    // Create report generator
    $reportGen = new ReportGenerator($connection);
    
    echo "Report generator created...<br>";
    
    // Set municipality context (General Trias = 1)
    $reportGen->setMunicipalityContext(1);
    
    echo "Municipality context set...<br>";
    
    // Simple filter - just status active
    $filterData = [
        'municipality_id' => '1',
        'status' => ['active']
    ];
    
    echo "Filters prepared...<br>";
    echo "Attempting to generate PDF...<br>";
    
    // Generate PDF (this will output and exit)
    $reportGen->generatePDF($filterData, 'student_list');
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "Trace: " . $e->getTraceAsString();
}
