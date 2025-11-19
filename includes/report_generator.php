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
            "SELECT name, preset_logo_image, custom_logo_image, COALESCE(use_custom_logo,false) AS use_custom_logo
             FROM municipalities WHERE municipality_id = $1",
            [$municipalityId]);
        if ($result && $row = pg_fetch_assoc($result)) {
            $this->municipalityName = $row['name'];
            if (!empty($row['use_custom_logo']) && !empty($row['custom_logo_image'])) {
                $this->municipalityLogo = $row['custom_logo_image'];
            } else {
                $this->municipalityLogo = $row['preset_logo_image'];
            }
        }
    }
    
    /**
     * Generate PDF Report
     */
    public function generatePDF($filterData, $reportType = 'student_list') {
        if (!empty($filterData['municipality_id'])) {
            $this->setMunicipalityContext($filterData['municipality_id']);
        }
        if (isset($filterData['report_type']) && !empty($filterData['report_type'])) {
            $reportType = $filterData['report_type'];
        }
        $this->filters->setFilters($filterData);
        $queryData = $this->filters->buildStudentQuery(true);
        
        $result = pg_query_params($this->connection, $queryData['query'], $queryData['params']);
        $students = pg_fetch_all($result) ?: [];

        // Determine payroll inclusion and mapping based on selected distribution (snapshot)
        $snapshotId = !empty($filterData['distribution_id']) ? $filterData['distribution_id'] : null;
        $includePayroll = !empty($snapshotId);
        $payrollMap = $includePayroll ? $this->getPayrollMapForSnapshot($snapshotId) : [];
        
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
                $this->generateStudentListPDF($pdf, $students, $includePayroll, $payrollMap);
                break;
            case 'applicants_master':
                $applicants = $this->getApplicantsForMunicipality($filterData);
                $this->generateApplicantsPDF($pdf, $applicants, $includePayroll, $payrollMap);
                break;
            case 'statistics':
                $this->generateStatisticsPDF($pdf, $students);
                break;
            default:
                $this->generateStudentListPDF($pdf, $students, $includePayroll, $payrollMap);
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
    private function generateStudentListPDF($pdf, $students, $includePayroll, $payrollMap = []) {
        $html = '<style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #1182FF; color: white; padding: 5px; font-weight: bold; font-size: 8px; text-align: left; }
            td { padding: 4px; font-size: 7px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>';

        // Define consistent column widths that sum to 100%
        if ($includePayroll) {
            $widths = [5, 12, 14, 22, 7, 14, 14, 6, 6]; // With Payroll
        } else {
            $widths = [5, 14, 26, 8, 16, 16, 7, 8];     // Without Payroll
        }
        
        $html .= '<table cellpadding="3">';
        $html .= '<thead><tr>';
        $html .= '<th width="' . $widths[0] . '%"><b>No.</b></th>';
        $html .= '<th width="' . $widths[1] . '%"><b>Student ID</b></th>';
        if ($includePayroll) {
            $html .= '<th width="' . $widths[2] . '%"><b>Payroll Number</b></th>';
            $nameIdx = 3; $afterName = 4; // index offsets when payroll exists
        } else {
            $nameIdx = 2; $afterName = 3; // index offsets when payroll not exists
        }
        $html .= '<th width="' . $widths[$nameIdx] . '%"><b>Name</b></th>';
        $html .= '<th width="' . $widths[$afterName] . '%"><b>Gender</b></th>';
        $html .= '<th width="' . $widths[$afterName+1] . '%"><b>Barangay</b></th>';
        $html .= '<th width="' . $widths[$afterName+2] . '%"><b>University</b></th>';
        $html .= '<th width="' . $widths[$afterName+3] . '%"><b>Year Level</b></th>';
        $html .= '<th width="' . $widths[$afterName+4] . '%"><b>Status</b></th>';
        $html .= '</tr></thead><tbody>';

        $no = 1;
        foreach ($students as $student) {
            $fullName = trim($student['first_name'] . ' ' . (($student['middle_name'] ?? '') ? ($student['middle_name'] . ' ') : '') . $student['last_name'] . ' ' . ($student['extension_name'] ?? ''));
            $html .= '<tr>';
            $html .= '<td width="' . $widths[0] . '%">' . $no++ . '</td>';
            $html .= '<td width="' . $widths[1] . '%">' . htmlspecialchars($student['student_id']) . '</td>';
            if ($includePayroll) {
                $pn = isset($payrollMap[$student['student_id']]) ? $payrollMap[$student['student_id']] : '-';
                $html .= '<td width="' . $widths[2] . '%">' . htmlspecialchars($pn) . '</td>';
            }
            $html .= '<td width="' . $widths[$nameIdx] . '%">' . htmlspecialchars($fullName) . '</td>';
            $html .= '<td width="' . $widths[$afterName] . '%">' . htmlspecialchars($student['sex'] ?? '-') . '</td>';
            $html .= '<td width="' . $widths[$afterName+1] . '%">' . htmlspecialchars($student['barangay'] ?? '-') . '</td>';
            $html .= '<td width="' . $widths[$afterName+2] . '%">' . htmlspecialchars($student['university'] ?? '-') . '</td>';
            $html .= '<td width="' . $widths[$afterName+3] . '%">' . htmlspecialchars($student['year_level'] ?? '-') . '</td>';
            $html .= '<td width="' . $widths[$afterName+4] . '%">' . htmlspecialchars($student['status_display'] ?? '-') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Summary at bottom
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Total Students: ' . count($students), 0, 1, 'R');
    }

    /**
     * Generate Applicants Master List PDF
     */
    private function generateApplicantsPDF($pdf, $applicants, $includePayroll, $payrollMap = []) {
        $html = '<style>
            table { border-collapse: collapse; width: 100%; }
            th { background-color: #dc3545; color: white; padding: 5px; font-weight: bold; font-size: 8px; text-align: left; }
            td { padding: 4px; font-size: 7px; border-bottom: 1px solid #ddd; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>';

        // Define widths summing to 100
        if ($includePayroll) {
            $widths = [5, 14, 16, 22, 8, 10, 15, 10]; // With Payroll
        } else {
            $widths = [5, 16, 30, 9, 12, 18, 10];     // Without Payroll
        }

        $html .= '<table cellpadding="3">';
        $html .= '<thead><tr>';
        $html .= '<th width="' . $widths[0] . '%"><b>No.</b></th>';
        $html .= '<th width="' . $widths[1] . '%"><b>Student ID</b></th>';
        if ($includePayroll) {
            $html .= '<th width="' . $widths[2] . '%"><b>Payroll Number</b></th>';
            $nameIdx = 3; $afterName = 4;
        } else {
            $nameIdx = 2; $afterName = 3;
        }
        $html .= '<th width="' . $widths[$nameIdx] . '%"><b>Name</b></th>';
        $html .= '<th width="' . $widths[$afterName] . '%"><b>Gender</b></th>';
        $html .= '<th width="' . $widths[$afterName+1] . '%"><b>Barangay</b></th>';
        $html .= '<th width="' . $widths[$afterName+2] . '%"><b>University</b></th>';
        $html .= '<th width="' . $widths[$afterName+3] . '%"><b>Year Level</b></th>';
        $html .= '</tr></thead><tbody>';

        $no = 1;
        foreach ($applicants as $row) {
            $fullName = trim(($row['last_name'] ?? '') . ', ' . ($row['first_name'] ?? '') . ' ' . (($row['middle_name'] ?? '') ? substr($row['middle_name'], 0, 1) . '.' : '') . ' ' . ($row['extension_name'] ?? ''));
            $html .= '<tr>';
            $html .= '<td width="' . $widths[0] . '%">' . $no++ . '</td>';
            $html .= '<td width="' . $widths[1] . '%">' . htmlspecialchars($row['student_id'] ?? '-') . '</td>';
            if ($includePayroll) {
                $pn = isset($payrollMap[$row['student_id'] ?? '']) ? $payrollMap[$row['student_id']] : '-';
                $html .= '<td width="' . $widths[2] . '%">' . htmlspecialchars($pn) . '</td>';
            }
            $html .= '<td width="' . $widths[$nameIdx] . '%">' . htmlspecialchars($fullName) . '</td>';
            $html .= '<td width="' . $widths[$afterName] . '%">' . htmlspecialchars($row['sex'] ?? '-') . '</td>';
            $html .= '<td width="' . $widths[$afterName+1] . '%">' . htmlspecialchars($row['barangay'] ?? '-') . '</td>';
            $html .= '<td width="' . $widths[$afterName+2] . '%">' . htmlspecialchars($row['university'] ?? '-') . '</td>';
            $html .= '<td width="' . $widths[$afterName+3] . '%">' . htmlspecialchars($row['year_level'] ?? '-') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Total Applicants: ' . count($applicants), 0, 1, 'R');
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
        if (!empty($filterData['municipality_id'])) {
            $this->setMunicipalityContext($filterData['municipality_id']);
        }
        $this->filters->setFilters($filterData);
        $queryData = $this->filters->buildStudentQuery(true);
        
        $result = pg_query_params($this->connection, $queryData['query'], $queryData['params']);
        $students = pg_fetch_all($result) ?: [];
        
        $stats = $this->filters->getStatistics();
        
        // Determine payroll inclusion and mapping based on selected distribution (snapshot)
        $snapshotId = !empty($filterData['distribution_id']) ? $filterData['distribution_id'] : null;
        $includePayroll = !empty($snapshotId);
        $payrollMap = $includePayroll ? $this->getPayrollMapForSnapshot($snapshotId) : [];
        
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
        $detailSheet->mergeCells('A' . $row . ':Q' . $row);
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
        
        // Headers (conditional Payroll column)
        $headers = ['No.', 'Student ID'];
        if ($includePayroll) { $headers[] = 'Payroll Number'; }
        $headers = array_merge($headers, ['Last Name', 'First Name', 'Middle Name', 'Extension', 'Gender', 'Birth Date', 'Email', 'Mobile', 'Barangay', 'Municipality', 'University', 'Course', 'Year Level', 'Status']);
        $colIndex = 1; // 1-based for Coordinate
        foreach ($headers as $header) {
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $row, $header);
            $colIndex++;
        }
        
        // Style headers
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = 'A' . $row . ':' . $lastCol . $row;
        $detailSheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4A90E2');
        $detailSheet->getStyle($headerRange)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'))->setBold(true);
        $detailSheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $detailSheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $detailSheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        // Data rows
        $no = 1;
        foreach ($students as $student) {
            $col = 1; // column index
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $no++);

            // Student ID as TEXT to preserve formatting
            $studentId = $student['student_id'] ?? 'N/A';
            if (empty(trim($studentId))) { $studentId = 'ERROR: ID Missing'; }
            $detailSheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $studentId, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Payroll Number as TEXT (conditional)
            if ($includePayroll) {
                $payrollNo = isset($payrollMap[$student['student_id']]) ? $payrollMap[$student['student_id']] : '-';
                $detailSheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $payrollNo !== '' ? $payrollNo : '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }

            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['last_name'] ?? '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['first_name'] ?? '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['middle_name'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['extension_name'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['sex'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['bdate'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['email'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['mobile'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['barangay'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['municipality'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['university'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['course'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['year_level'] ?: '-');
            $detailSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col++) . $row, $student['status_display'] ?? '-');

            // Alternate row colors
            if ($no % 2 == 0) {
                $detailSheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F8FF');
            }
            $row++;
        }
        
        // Add borders to all data
        $dataRange = 'A3:' . $lastCol . ($row - 1);
        $detailSheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Auto-size columns
        for ($c = 1; $c <= count($headers); $c++) {
            $detailSheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }
        
        // ========== APPLICANTS MASTER LIST (Municipality) ==========
        if (!empty($filterData['report_type']) && in_array($filterData['report_type'], ['comprehensive','applicants_master'])) {
            $applicantsSheet = $spreadsheet->createSheet();
            $applicantsSheet->setTitle('Applicants Master');
            $row = 1;
            $lastColApp = $includePayroll ? 'M' : 'L';
            $applicantsSheet->mergeCells('A' . $row . ':' . $lastColApp . $row);
            $applicantsSheet->setCellValue('A' . $row, 'APPLICANTS MASTER LIST - ' . ($this->municipalityName ?: 'Municipality'));
            $applicantsSheet->getStyle('A' . $row)->getFont()->setSize(14)->setBold(true);
            $row += 2;
            $appHeaders = ['No.', 'Student ID'];
            if ($includePayroll) { $appHeaders[] = 'Payroll Number'; }
            $appHeaders = array_merge($appHeaders, ['Last Name', 'First Name', 'Middle Name', 'Ext', 'Gender', 'Barangay', 'University', 'Year Level', 'Status']);
            $cIndex = 1;
            foreach ($appHeaders as $h) {
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIndex) . $row, $h);
                $cIndex++;
            }
            $applicantsSheet->getStyle('A' . $row . ':' . (\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($appHeaders))) . $row)->getFont()->setBold(true);
            $row++;
            $no = 1;
            $applicants = $this->getApplicantsForMunicipality($filterData);
            foreach ($applicants as $a) {
                $c = 1;
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $no++);
                $applicantsSheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['student_id'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                if ($includePayroll) {
                    $pn = isset($payrollMap[$a['student_id'] ?? '']) ? $payrollMap[$a['student_id']] : '-';
                    $applicantsSheet->setCellValueExplicit(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $pn !== '' ? $pn : '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['last_name'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['first_name'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['middle_name'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['extension_name'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['sex'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['barangay'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['university'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['year_level'] ?? '-');
                $applicantsSheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c++) . $row, $a['status_display'] ?? 'Applicant');
                $row++;
            }
            $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($appHeaders));
            for ($ci = 1; $ci <= count($appHeaders); $ci++) {
                $applicantsSheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
            }
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
        
        // If comprehensive, reorder and include charts optionally
        if (!empty($filterData['report_type']) && $filterData['report_type'] === 'comprehensive') {
            // Already added Cover, Details, Applicants (optional), Statistics
            // Add Barangay Breakdown
            if (!empty($filterData['municipality_id'])) {
                $munId = $filterData['municipality_id'];
                // Barangay breakdown
                $barangaySql = "
                    SELECT b.name AS barangay,
                           COUNT(*) AS total,
                           SUM(CASE WHEN s.status='active' THEN 1 ELSE 0 END) AS active,
                           SUM(CASE WHEN s.status='applicant' OR s.status='under_registration' THEN 1 ELSE 0 END) AS applicants,
                           SUM(CASE WHEN s.status='disabled' THEN 1 ELSE 0 END) AS disabled
                    FROM students s
                    LEFT JOIN barangays b ON b.barangay_id = s.barangay_id
                    WHERE s.municipality_id = $1
                    GROUP BY b.name
                    ORDER BY b.name";
                $barangayRes = pg_query_params($this->connection, $barangaySql, [$munId]);
                $barangaySheet = $spreadsheet->createSheet();
                $barangaySheet->setTitle('Barangay Breakdown');
                $r = 1;
                $barangaySheet->mergeCells('A' . $r . ':F' . $r);
                $barangaySheet->setCellValue('A' . $r, 'BARANGAY BREAKDOWN - ' . ($this->municipalityName ?: 'Municipality'));
                $barangaySheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(14);
                $r += 2;
                $headers = ['Barangay','Total','Active','Applicants','Disabled'];
                $c = 'A'; foreach ($headers as $h) { $barangaySheet->setCellValue($c.$r, $h); $c++; }
                $barangaySheet->getStyle('A' . $r . ':E' . $r)->getFont()->setBold(true);
                $r++;
                while ($row = pg_fetch_assoc($barangayRes)) {
                    $barangaySheet->setCellValue('A' . $r, $row['barangay'] ?: 'Unspecified');
                    $barangaySheet->setCellValue('B' . $r, (int)$row['total']);
                    $barangaySheet->setCellValue('C' . $r, (int)$row['active']);
                    $barangaySheet->setCellValue('D' . $r, (int)$row['applicants']);
                    $barangaySheet->setCellValue('E' . $r, (int)$row['disabled']);
                    $r++;
                }
                foreach (range('A','E') as $col) { $barangaySheet->getColumnDimension($col)->setAutoSize(true); }

                // University breakdown
                $univSql = "
                    SELECT u.name AS university,
                           COUNT(*) AS total,
                           SUM(CASE WHEN s.status='active' THEN 1 ELSE 0 END) AS active,
                           SUM(CASE WHEN s.status='applicant' OR s.status='under_registration' THEN 1 ELSE 0 END) AS applicants,
                           SUM(CASE WHEN s.status='disabled' THEN 1 ELSE 0 END) AS disabled
                    FROM students s
                    LEFT JOIN universities u ON u.university_id = s.university_id
                    WHERE s.municipality_id = $1
                    GROUP BY u.name
                    ORDER BY total DESC, u.name";
                $univRes = pg_query_params($this->connection, $univSql, [$munId]);
                $univSheet = $spreadsheet->createSheet();
                $univSheet->setTitle('University Breakdown');
                $r = 1;
                $univSheet->mergeCells('A' . $r . ':F' . $r);
                $univSheet->setCellValue('A' . $r, 'UNIVERSITY BREAKDOWN - ' . ($this->municipalityName ?: 'Municipality'));
                $univSheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(14);
                $r += 2;
                $headers = ['University','Total','Active','Applicants','Disabled'];
                $c = 'A'; foreach ($headers as $h) { $univSheet->setCellValue($c.$r, $h); $c++; }
                $univSheet->getStyle('A' . $r . ':E' . $r)->getFont()->setBold(true);
                $r++;
                while ($row = pg_fetch_assoc($univRes)) {
                    $univSheet->setCellValue('A' . $r, $row['university'] ?: 'Unspecified');
                    $univSheet->setCellValue('B' . $r, (int)$row['total']);
                    $univSheet->setCellValue('C' . $r, (int)$row['active']);
                    $univSheet->setCellValue('D' . $r, (int)$row['applicants']);
                    $univSheet->setCellValue('E' . $r, (int)$row['disabled']);
                    $r++;
                }
                foreach (range('A','E') as $col) { $univSheet->getColumnDimension($col)->setAutoSize(true); }

                // Distribution summary for municipality
                $distSql = "
                    SELECT ds.distribution_id, ds.academic_year, ds.semester, ds.finalized_at,
                           COUNT(r.student_id) FILTER (WHERE s.municipality_id = $1) AS recipients
                    FROM distribution_snapshots ds
                    LEFT JOIN distribution_student_records r ON r.snapshot_id = ds.snapshot_id
                    LEFT JOIN students s ON s.student_id = r.student_id
                    WHERE ds.finalized_at IS NOT NULL
                    GROUP BY ds.distribution_id, ds.academic_year, ds.semester, ds.finalized_at
                    ORDER BY ds.finalized_at DESC NULLS LAST";
                $distRes = pg_query_params($this->connection, $distSql, [$munId]);
                $distSheet = $spreadsheet->createSheet();
                $distSheet->setTitle('Distributions Summary');
                $r = 1;
                $distSheet->mergeCells('A' . $r . ':E' . $r);
                $distSheet->setCellValue('A' . $r, 'DISTRIBUTIONS SUMMARY - ' . ($this->municipalityName ?: 'Municipality'));
                $distSheet->getStyle('A' . $r)->getFont()->setBold(true)->setSize(14);
                $r += 2;
                $headers = ['Distribution ID','Academic Year','Semester','Finalized At','Recipients'];
                $c='A'; foreach ($headers as $h) { $distSheet->setCellValue($c.$r, $h); $c++; }
                $distSheet->getStyle('A' . $r . ':E' . $r)->getFont()->setBold(true);
                $r++;
                while ($row = pg_fetch_assoc($distRes)) {
                    $distSheet->setCellValue('A' . $r, $row['distribution_id']);
                    $distSheet->setCellValue('B' . $r, $row['academic_year']);
                    $distSheet->setCellValue('C' . $r, $row['semester']);
                    $distSheet->setCellValue('D' . $r, $row['finalized_at']);
                    $distSheet->setCellValue('E' . $r, (int)$row['recipients']);
                    $r++;
                }
                foreach (range('A','E') as $col) { $distSheet->getColumnDimension($col)->setAutoSize(true); }
            }
        }

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

    /**
     * Fetch applicants for the current municipality (based on filters' municipality_id)
     */
    private function getApplicantsForMunicipality($filterData) {
        $municipalityId = $filterData['municipality_id'] ?? null;
        if (!$municipalityId) {
            return [];
        }
        $params = [$municipalityId];
        // Build a query to get applicants (no payroll join here; payroll is resolved by selected distribution)
        $sql = "
            SELECT s.student_id, s.last_name, s.first_name, s.middle_name, s.extension_name,
                   s.sex, b.name as barangay, u.name as university, yl.name as year_level,
                   CASE 
                     WHEN s.status = 'active' THEN 'Active'
                     WHEN s.status = 'disabled' THEN 'Disabled'
                     ELSE 'Applicant'
                   END as status_display
            FROM students s
            LEFT JOIN barangays b ON b.barangay_id = s.barangay_id
            LEFT JOIN universities u ON u.university_id = s.university_id
            LEFT JOIN year_levels yl ON yl.year_level_id = s.year_level_id
            WHERE s.municipality_id = $1
              AND s.status IN ('applicant','under_registration')
            ORDER BY s.last_name, s.first_name
        ";
        $res = pg_query_params($this->connection, $sql, $params);
        return pg_fetch_all($res) ?: [];
    }

    /**
     * Build a student_id -> payroll_no map for a given distribution snapshot
     */
    private function getPayrollMapForSnapshot($snapshotId) {
        if (empty($snapshotId)) { return []; }
        $sql = "SELECT student_id, payroll_no FROM distribution_payrolls WHERE snapshot_id = $1";
        $res = pg_query_params($this->connection, $sql, [$snapshotId]);
        $map = [];
        if ($res) {
            while ($row = pg_fetch_assoc($res)) {
                $sid = $row['student_id'];
                if ($sid !== null) {
                    $map[$sid] = $row['payroll_no'];
                }
            }
        }
        return $map;
    }
}
