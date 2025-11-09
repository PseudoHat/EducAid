<?php
/**
 * Report Generator - Core class for PDF and Excel report generation
 * Requires: tecnickcom/tcpdf, phpoffice/phpspreadsheet
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/report_filters.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;

class ReportGenerator {
    private $connection;
    private $filters;
    private $municipalityLogo = '';
    private $municipalityName = '';
    
    public function __construct($dbConnection) {
        $this->connection = $dbConnection;
        $this->filters = new ReportFilters($dbConnection);
    }
    
    /**
     * Set municipality context for headers
     */
    public function setMunicipalityContext($municipalityId) {
        $result = pg_query_params($this->connection, 
            "SELECT name, preset_logo_image FROM municipalities WHERE municipality_id = $1", 
            [$municipalityId]);
        if ($result && $row = pg_fetch_assoc($result)) {
            $this->municipalityName = $row['name'];
            $this->municipalityLogo = $row['preset_logo_image'];
        }
    }
    
    /**
     * Generate PDF Report
     */
    public function generatePDF($filterData, $reportType = 'student_list') {
        $this->filters->setFilters($filterData);
        $queryData = $this->filters->buildStudentQuery(true);
        
        $result = pg_query_params($this->connection, $queryData['query'], $queryData['params']);
        $students = pg_fetch_all($result) ?: [];
        
        // Initialize TCPDF
        $pdf = new \TCPDF('L', PDF_UNIT, 'LEGAL', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('EducAid System');
        $pdf->SetAuthor($this->municipalityName ?: 'EducAid Admin');
        $pdf->SetTitle('Student Report - ' . date('Y-m-d'));
        $pdf->SetSubject('Educational Assistance Report');
        
        // Set margins
        $pdf->SetMargins(10, 30, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Set font
        $pdf->SetFont('helvetica', '', 9);
        
        // Add page
        $pdf->AddPage();
        
        // Add header
        $this->pdfHeader($pdf, $filterData);
        
        // Generate content based on report type
        switch ($reportType) {
            case 'student_list':
                $this->generateStudentListPDF($pdf, $students);
                break;
            case 'statistics':
                $this->generateStatisticsPDF($pdf, $students);
                break;
            default:
                $this->generateStudentListPDF($pdf, $students);
        }
        
        // Add footer to all pages
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $pdf->setFooterMargin(PDF_MARGIN_FOOTER);
        
        // Clean all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output PDF
        $filename = 'EducAid_Report_' . date('Y-m-d_His') . '.pdf';
        $pdf->Output($filename, 'D');
        exit;
    }
    
    /**
     * PDF Header with municipality logo and title
     */
    private function pdfHeader($pdf, $filterData) {
        $pdf->SetY(5);
        
        // Logo (if available)
        if (!empty($this->municipalityLogo) && file_exists(__DIR__ . '/../' . $this->municipalityLogo)) {
            $pdf->Image(__DIR__ . '/../' . $this->municipalityLogo, 15, 7, 20, 20, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Header text
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 5, 'EDUCAID SCHOLARSHIP PROGRAM', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 12);
        if (!empty($this->municipalityName)) {
            $pdf->Cell(0, 5, strtoupper($this->municipalityName), 0, 1, 'C');
        }
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 4, 'Student Report - ' . date('F d, Y'), 0, 1, 'C');
        
        // Distribution credentials (if applicable)
        try {
            $distCredentials = $this->filters->getDistributionCredentials();
            if ($distCredentials && is_array($distCredentials) && !empty($distCredentials['distribution_id'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetTextColor(25, 118, 210); // Blue color
                
                $distInfo = 'Distribution: ' . htmlspecialchars($distCredentials['distribution_id']) . ' - ' . 
                           htmlspecialchars($distCredentials['academic_year']) . ' ' . 
                           htmlspecialchars($distCredentials['semester']);
                
                // Add date if available
                if (!empty($distCredentials['distribution_date'])) {
                    $distInfo .= ' | ' . htmlspecialchars($distCredentials['formatted_datetime']);
                }
                
                // Only add location if it exists
                if (!empty($distCredentials['distribution_location'])) {
                    $distInfo .= ' | Location: ' . htmlspecialchars($distCredentials['distribution_location']);
                }
                
                $pdf->Cell(0, 4, $distInfo, 0, 1, 'C');
                $pdf->SetTextColor(0, 0, 0); // Reset to black
            }
        } catch (Exception $e) {
            // Silently skip distribution credentials if there's an error
            error_log('PDF Header - Distribution credentials error: ' . $e->getMessage());
        }
        
        // Filter summary
        if (!empty($filterData)) {
            $filterSummary = $this->filters->getFilterSummary();
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 4, 'Filters: ' . implode(' | ', $filterSummary), 0, 1, 'C');
        }
        
        $pdf->Ln(3);
    }
    
    /**
     * PDF Footer with page numbers
     */
    private function pdfFooter($pdf) {
        $pdf->SetY(-15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
        $pdf->Cell(0, 10, 'Generated: ' . date('Y-m-d H:i:s'), 0, 0, 'R');
    }
    
    /**
     * Generate Student List PDF
     */
    private function generateStudentListPDF($pdf, $students) {
        $html = '<style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #1182FF; color: white; padding: 5px; font-weight: bold; font-size: 8px; text-align: left; }
            td { padding: 4px; font-size: 7px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>';
        
        $html .= '<table cellpadding="3">';
        $html .= '<thead><tr>
            <th width="5%"><b>No.</b></th>
            <th width="15%"><b>Student ID</b></th>
            <th width="20%"><b>Name</b></th>
            <th width="8%"><b>Gender</b></th>
            <th width="15%"><b>Barangay</b></th>
            <th width="15%"><b>University</b></th>
            <th width="12%"><b>Year Level</b></th>
            <th width="10%"><b>Status</b></th>
        </tr></thead><tbody>';
        
        $no = 1;
        foreach ($students as $student) {
            $fullName = trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'] . ' ' . ($student['extension_name'] ?: ''));
            
            $html .= '<tr>
                <td>' . $no++ . '</td>
                <td>' . htmlspecialchars($student['student_id']) . '</td>
                <td>' . htmlspecialchars($fullName) . '</td>
                <td>' . htmlspecialchars($student['sex'] ?: '-') . '</td>
                <td>' . htmlspecialchars($student['barangay'] ?: '-') . '</td>
                <td>' . htmlspecialchars($student['university'] ?: '-') . '</td>
                <td>' . htmlspecialchars($student['year_level'] ?: '-') . '</td>
                <td>' . htmlspecialchars($student['status_display']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Summary at bottom
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Total Students: ' . count($students), 0, 1, 'R');
    }
    
    /**
     * Generate Statistics PDF
     */
    private function generateStatisticsPDF($pdf, $students) {
        $stats = $this->filters->getStatistics();
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'STATISTICAL SUMMARY', 0, 1, 'L');
        $pdf->Ln(2);
        
        // Overall Statistics
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(241, 130, 255);
        $pdf->Cell(0, 7, 'Overall Statistics', 1, 1, 'L', true);
        
        $pdf->SetFont('helvetica', '', 9);
        $statsData = [
            ['Metric', 'Value'],
            ['Total Students', number_format($stats['total_students'])],
            ['Male Students', number_format($stats['male_count']) . ' (' . round($stats['male_count']/$stats['total_students']*100, 1) . '%)'],
            ['Female Students', number_format($stats['female_count']) . ' (' . round($stats['female_count']/$stats['total_students']*100, 1) . '%)'],
            ['Active Students', number_format($stats['active_count'])],
            ['Applicants', number_format($stats['applicant_count'])],
            ['Archived Students', number_format($stats['archived_count'])],
            ['Average GWA', $stats['avg_gwa'] ?: 'N/A'],
            ['Average Confidence Score', $stats['avg_confidence'] . '%'],
            ['Municipalities Covered', $stats['municipalities']],
            ['Barangays Covered', $stats['barangays']],
            ['Universities', $stats['universities']],
        ];
        
        foreach ($statsData as $row) {
            $pdf->Cell(80, 6, $row[0], 1, 0, 'L');
            $pdf->Cell(80, 6, $row[1], 1, 1, 'R');
        }
        
        $pdf->Ln(5);
        
        // Top Universities
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(241, 130, 255);
        $pdf->Cell(0, 7, 'Top 10 Universities by Student Count', 1, 1, 'L', true);
        
        $universityQuery = "
            SELECT u.name, COUNT(*) as student_count
            FROM students s
            INNER JOIN universities u ON u.university_id = s.university_id
            WHERE s.student_id IN (SELECT student_id FROM unnest(ARRAY[" . implode(',', array_map(function($s) { return "'" . pg_escape_string($s['student_id']) . "'"; }, $students)) . "]))
            GROUP BY u.name
            ORDER BY student_count DESC
            LIMIT 10
        ";
        
        $uniResult = pg_query($this->connection, $universityQuery);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(120, 6, 'University', 1, 0, 'L', true);
        $pdf->Cell(40, 6, 'Students', 1, 1, 'C', true);
        
        while ($uni = pg_fetch_assoc($uniResult)) {
            $pdf->Cell(120, 6, $uni['name'], 1, 0, 'L');
            $pdf->Cell(40, 6, $uni['student_count'], 1, 1, 'C');
        }
    }
    
    /**
     * Generate Excel Report with Charts
     */
    public function generateExcel($filterData, $includeCharts = true) {
        $this->filters->setFilters($filterData);
        $queryData = $this->filters->buildStudentQuery(true);
        
        $result = pg_query_params($this->connection, $queryData['query'], $queryData['params']);
        $students = pg_fetch_all($result) ?: [];
        
        $stats = $this->filters->getStatistics();
        
        $spreadsheet = new Spreadsheet();
        
        // ========== COVER SHEET ==========
        $coverSheet = $spreadsheet->getActiveSheet();
        $coverSheet->setTitle('Report Cover');
        
        // Cover page styling
        $coverSheet->getDefaultRowDimension()->setRowHeight(20);
        
        // Logo placeholder
        $row = 3;
        $coverSheet->mergeCells('A' . $row . ':G' . $row);
        $coverSheet->setCellValue('A' . $row, 'EDUCAID SCHOLARSHIP PROGRAM');
        $coverSheet->getStyle('A' . $row)->getFont()->setSize(24)->setBold(true);
        $coverSheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;
        
        $coverSheet->mergeCells('A' . $row . ':G' . $row);
        $coverSheet->setCellValue('A' . $row, strtoupper($this->municipalityName ?: 'Municipality'));
        $coverSheet->getStyle('A' . $row)->getFont()->setSize(18)->setBold(true);
        $coverSheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 3;
        
        $coverSheet->mergeCells('A' . $row . ':G' . $row);
        $coverSheet->setCellValue('A' . $row, 'COMPREHENSIVE STUDENT REPORT');
        $coverSheet->getStyle('A' . $row)->getFont()->setSize(20)->setBold(true);
        $coverSheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 4;
        
        // Report metadata
        $coverSheet->setCellValue('C' . $row, 'Report Generated:');
        $coverSheet->setCellValue('D' . $row, date('F d, Y - h:i A'));
        $coverSheet->getStyle('C' . $row)->getFont()->setBold(true);
        $row++;
        
        $coverSheet->setCellValue('C' . $row, 'Total Students:');
        $coverSheet->setCellValue('D' . $row, number_format($stats['total_students']));
        $coverSheet->getStyle('C' . $row)->getFont()->setBold(true);
        $row += 2;
        
        // Distribution Credentials (if distribution filter is applied)
        $distCredentials = $this->filters->getDistributionCredentials();
        if ($distCredentials) {
            $coverSheet->mergeCells('C' . $row . ':E' . $row);
            $coverSheet->setCellValue('C' . $row, 'DISTRIBUTION INFORMATION');
            $coverSheet->getStyle('C' . $row)->getFont()->setBold(true)->setSize(14);
            $coverSheet->getStyle('C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4CAF50');
            $coverSheet->getStyle('C' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
            $coverSheet->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            
            $coverSheet->setCellValue('C' . $row, 'Distribution ID:');
            $coverSheet->setCellValue('D' . $row, $distCredentials['distribution_id']);
            $coverSheet->getStyle('C' . $row)->getFont()->setBold(true);
            $row++;
            
            $coverSheet->setCellValue('C' . $row, 'Academic Year:');
            $coverSheet->setCellValue('D' . $row, $distCredentials['academic_year']);
            $coverSheet->getStyle('C' . $row)->getFont()->setBold(true);
            $row++;
            
            $coverSheet->setCellValue('C' . $row, 'Semester:');
            $coverSheet->setCellValue('D' . $row, $distCredentials['semester']);
            $coverSheet->getStyle('C' . $row)->getFont()->setBold(true);
            $row++;
            
            $coverSheet->setCellValue('C' . $row, 'Distribution Date:');
            $coverSheet->setCellValue('D' . $row, $distCredentials['formatted_date']);
            $coverSheet->getStyle('C' . $row)->getFont()->setBold(true);
            $coverSheet->getStyle('D' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1976D2'));
            $row++;
            
            $coverSheet->setCellValue('C' . $row, 'Location:');
            $coverSheet->setCellValue('D' . $row, $distCredentials['distribution_location'] ?: 'Not specified');
            $coverSheet->getStyle('C' . $row)->getFont()->setBold(true);
            $coverSheet->getStyle('D' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1976D2'));
            $coverSheet->getStyle('D' . $row)->getAlignment()->setWrapText(true);
            $row += 2;
        }
        
        $filterSummary = $this->filters->getFilterSummary();
        if (!empty($filterSummary)) {
            $coverSheet->mergeCells('C' . $row . ':E' . $row);
            $coverSheet->setCellValue('C' . $row, 'FILTERS APPLIED:');
            $coverSheet->getStyle('C' . $row)->getFont()->setBold(true)->setSize(12);
            $coverSheet->getStyle('C' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFEB3B');
            $row++;
            foreach ($filterSummary as $filter) {
                $coverSheet->mergeCells('C' . $row . ':F' . $row);
                $coverSheet->setCellValue('C' . $row, '• ' . $filter);
                $coverSheet->getStyle('C' . $row)->getAlignment()->setWrapText(true);
                $row++;
            }
        }
        
        // ========== STUDENT DETAILS SHEET ==========
        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Student Details');
        
        // Title
        $row = 1;
        $detailSheet->mergeCells('A' . $row . ':P' . $row);
        $detailSheet->setCellValue('A' . $row, 'COMPLETE STUDENT INFORMATION');
        $detailSheet->getStyle('A' . $row)->getFont()->setSize(16)->setBold(true);
        $detailSheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $detailSheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1182FF');
        $detailSheet->getStyle('A' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $row++;
        
        // Filter summary in header
        $filterSummary = $this->filters->getFilterSummary();
        if (!empty($filterSummary) && $filterSummary[0] !== 'No filters applied - showing all records') {
            $detailSheet->mergeCells('A' . $row . ':P' . $row);
            $detailSheet->setCellValue('A' . $row, 'FILTERS APPLIED: ' . implode(' • ', $filterSummary));
            $detailSheet->getStyle('A' . $row)->getFont()->setSize(10)->setItalic(true)->setBold(true);
            $detailSheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
            $detailSheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFEB3B');
            $detailSheet->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);
            $detailSheet->getRowDimension($row)->setRowHeight(40);
            $row++;
        }
        
        // Add blank row for spacing
        $row++;
        
        // Headers
        $headers = [
            'No.', 'Student ID', 'Last Name', 'First Name', 'Middle Name', 'Extension', 
            'Gender', 'Birth Date', 'Email', 'Mobile', 'Barangay', 'Municipality', 
            'University', 'Course', 'Year Level', 'Status'
        ];
        $col = 'A';
        foreach ($headers as $header) {
            $detailSheet->setCellValue($col . $row, $header);
            $col++;
        }
        
        // Style headers
        $headerRange = 'A' . $row . ':P' . $row;
        $detailSheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4A90E2');
        $detailSheet->getStyle($headerRange)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'))->setBold(true);
        $detailSheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $detailSheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $detailSheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        // Data rows
        $no = 1;
        foreach ($students as $student) {
            $detailSheet->setCellValue('A' . $row, $no++);
            
            // Set Student ID as TEXT to preserve formatting (especially for IDs starting with zeros or hyphens)
            // Use the student_id directly, fallback to showing available keys if blank for debugging
            $studentId = $student['student_id'] ?? 'N/A';
            if (empty(trim($studentId))) {
                // If student_id is blank, show a debug message or use alternative
                $studentId = 'ERROR: ID Missing';
            }
            $detailSheet->setCellValueExplicit('B' . $row, $studentId, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            
            $detailSheet->setCellValue('C' . $row, $student['last_name'] ?? '-');
            $detailSheet->setCellValue('D' . $row, $student['first_name'] ?? '-');
            $detailSheet->setCellValue('E' . $row, $student['middle_name'] ?: '-');
            $detailSheet->setCellValue('F' . $row, $student['extension_name'] ?: '-');
            $detailSheet->setCellValue('G' . $row, $student['sex'] ?: '-');
            $detailSheet->setCellValue('H' . $row, $student['bdate'] ?: '-');
            $detailSheet->setCellValue('I' . $row, $student['email'] ?: '-');
            $detailSheet->setCellValue('J' . $row, $student['mobile'] ?: '-');
            $detailSheet->setCellValue('K' . $row, $student['barangay'] ?: '-');
            $detailSheet->setCellValue('L' . $row, $student['municipality'] ?: '-');
            $detailSheet->setCellValue('M' . $row, $student['university'] ?: '-');
            $detailSheet->setCellValue('N' . $row, $student['course'] ?: '-');
            $detailSheet->setCellValue('O' . $row, $student['year_level'] ?: '-');
            $detailSheet->setCellValue('P' . $row, $student['status_display'] ?? '-');
            
            // Alternate row colors
            if ($no % 2 == 0) {
                $detailSheet->getStyle('A' . $row . ':P' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F8FF');
            }
            $row++;
        }
        
        // Add borders to all data
        $dataRange = 'A3:P' . ($row - 1);
        $detailSheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Auto-size columns
        foreach (range('A', 'P') as $col) {
            $detailSheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // ========== STATISTICS SHEET ==========
        $statsSheet = $spreadsheet->createSheet();
        $statsSheet->setTitle('Statistics Summary');
        
        $row = 2;
        $statsSheet->mergeCells('B' . $row . ':E' . $row);
        $statsSheet->setCellValue('B' . $row, 'STATISTICAL SUMMARY');
        $statsSheet->getStyle('B' . $row)->getFont()->setSize(18)->setBold(true);
        $statsSheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 3;
        
        // Total students card
        $statsSheet->mergeCells('B' . $row . ':C' . $row);
        $statsSheet->setCellValue('B' . $row, 'TOTAL STUDENTS');
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12);
        $statsSheet->getStyle('B' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1182FF');
        $statsSheet->getStyle('B' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        $row++;
        
        $statsSheet->mergeCells('B' . $row . ':C' . $row);
        $statsSheet->setCellValue('B' . $row, number_format($stats['total_students']));
        $statsSheet->getStyle('B' . $row)->getFont()->setSize(24)->setBold(true);
        $statsSheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row += 2;
        
        // Gender breakdown
        $statsSheet->setCellValue('B' . $row, 'GENDER DISTRIBUTION');
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        
        $malePercent = $stats['total_students'] > 0 ? ($stats['male_count'] / $stats['total_students']) * 100 : 0;
        $femalePercent = $stats['total_students'] > 0 ? ($stats['female_count'] / $stats['total_students']) * 100 : 0;
        
        $statsSheet->setCellValue('B' . $row, 'Male:');
        $statsSheet->setCellValue('C' . $row, number_format($stats['male_count']) . ' (' . number_format($malePercent, 1) . '%)');
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;
        
        $statsSheet->setCellValue('B' . $row, 'Female:');
        $statsSheet->setCellValue('C' . $row, number_format($stats['female_count']) . ' (' . number_format($femalePercent, 1) . '%)');
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row += 2;
        
        // Status breakdown
        $statsSheet->setCellValue('B' . $row, 'STATUS DISTRIBUTION');
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        
        $statsSheet->setCellValue('B' . $row, 'Active:');
        $statsSheet->setCellValue('C' . $row, number_format($stats['active_count']));
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;
        
        $statsSheet->setCellValue('B' . $row, 'Applicant:');
        $statsSheet->setCellValue('C' . $row, number_format($stats['applicant_count']));
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;
        
        $statsSheet->setCellValue('B' . $row, 'Archived:');
        $statsSheet->setCellValue('C' . $row, number_format($stats['archived_count']));
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row += 2;
        
        // Other metrics
        $statsSheet->setCellValue('B' . $row, 'Average Confidence Score:');
        $statsSheet->setCellValue('C' . $row, number_format($stats['avg_confidence'], 2) . '%');
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;
        
        $statsSheet->setCellValue('B' . $row, 'Municipalities:');
        $statsSheet->setCellValue('C' . $row, $stats['municipalities']);
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;
        
        $statsSheet->setCellValue('B' . $row, 'Barangays:');
        $statsSheet->setCellValue('C' . $row, $stats['barangays']);
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        $row++;
        
        $statsSheet->setCellValue('B' . $row, 'Universities:');
        $statsSheet->setCellValue('C' . $row, $stats['universities']);
        $statsSheet->getStyle('B' . $row)->getFont()->setBold(true);
        
        // Auto-size columns
        $statsSheet->getColumnDimension('B')->setWidth(30);
        $statsSheet->getColumnDimension('C')->setWidth(25);
        
        // Set active sheet to cover
        $spreadsheet->setActiveSheetIndex(0);
        
        // Save Excel file
        ob_end_clean();
        $filename = 'EducAid_Comprehensive_Report_' . date('Y-m-d_His') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Add statistics sheet to Excel workbook
     */
    private function addStatisticsSheet($spreadsheet, $students) {
        $statsSheet = $spreadsheet->createSheet();
        $statsSheet->setTitle('Statistics');
        
        $stats = $this->filters->getStatistics();
        
        // Title
        $statsSheet->mergeCells('A1:B1');
        $statsSheet->setCellValue('A1', 'STATISTICAL SUMMARY');
        $statsSheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        
        $row = 3;
        
        // Headers
        $statsSheet->setCellValue('A' . $row, 'Metric');
        $statsSheet->setCellValue('B' . $row, 'Value');
        $statsSheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $statsSheet->getStyle('A' . $row . ':B' . $row)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF1182FF');
        $statsSheet->getStyle('A' . $row . ':B' . $row)
            ->getFont()
            ->getColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
        
        $row++;
        
        // Data
        $statsData = [
            ['Total Students', $stats['total_students']],
            ['Male Students', $stats['male_count']],
            ['Female Students', $stats['female_count']],
            ['Active Students', $stats['active_count']],
            ['Applicants', $stats['applicant_count']],
            ['Archived Students', $stats['archived_count']],
            ['Average GWA', $stats['avg_gwa'] ?: 'N/A'],
            ['Average Confidence Score', $stats['avg_confidence'] . '%'],
            ['Municipalities Covered', $stats['municipalities']],
            ['Barangays Covered', $stats['barangays']],
            ['Universities', $stats['universities']],
        ];
        
        foreach ($statsData as $data) {
            $statsSheet->setCellValue('A' . $row, $data[0]);
            $statsSheet->setCellValue('B' . $row, $data[1]);
            $row++;
        }
        
        // Auto-size
        $statsSheet->getColumnDimension('A')->setWidth(30);
        $statsSheet->getColumnDimension('B')->setWidth(20);
        
        // Add borders
        $statsSheet->getStyle('A3:B' . ($row - 1))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }
    
    /**
     * Get report preview data (for AJAX)
     */
    public function getPreviewData($filterData, $limit = 50) {
        $this->filters->setFilters($filterData);
        $queryData = $this->filters->buildStudentQuery(true);
        
        // Add limit for preview
        $queryData['query'] .= " LIMIT $limit";
        
        $result = pg_query_params($this->connection, $queryData['query'], $queryData['params']);
        $students = pg_fetch_all($result) ?: [];
        
        $stats = $this->filters->getStatistics();
        
        return [
            'students' => $students,
            'stats' => $stats,
            'total' => $stats['total_students'],
            'preview_count' => count($students),
            'filter_summary' => $this->filters->getFilterSummary()
        ];
    }
}
