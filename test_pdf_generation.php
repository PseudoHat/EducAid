<?php
// Test PDF generation directly
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/report_filters.php';
require_once __DIR__ . '/includes/report_generator.php';

// Simulate filter data
$filterData = [
    'municipality_id' => '1', // General Trias
    'status' => ['active'],
];

echo "<h2>Testing PDF Generation</h2>";
echo "<p>Filter Data: " . json_encode($filterData) . "</p>";

try {
    $reportGen = new ReportGenerator($connection);
    $reportGen->setMunicipalityContext($filterData['municipality_id']);
    
    echo "<p>ReportGenerator initialized successfully</p>";
    echo "<p>Attempting to generate PDF...</p>";
    
    // Try to generate PDF
    $reportGen->generatePDF($filterData);
    
    echo "<p style='color: green;'>PDF generated successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<p style='color: red;'><strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
