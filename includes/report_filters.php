<?php
/**
 * Report Filters Helper
 * Builds dynamic SQL queries with parameterized filters for secure reporting
 */

class ReportFilters {
    private $connection;
    private $filters = [];
    private $whereConditions = ['1=1'];
    private $params = [];
    private $paramCount = 1;
    
    public function __construct($dbConnection) {
        $this->connection = $dbConnection;
    }
    
    /**
     * Set filters from request (GET/POST)
     */
    public function setFilters($requestData) {
        $this->filters = [
            'status' => $requestData['status'] ?? [],
            'gender' => $requestData['gender'] ?? '',
            'barangay_id' => $requestData['barangay_id'] ?? [],
            'municipality_id' => $requestData['municipality_id'] ?? '',
            'university_id' => $requestData['university_id'] ?? [],
            'year_level_id' => $requestData['year_level_id'] ?? [],
            'distribution_id' => $requestData['distribution_id'] ?? '',
            'academic_year' => $requestData['academic_year'] ?? '',
            'course_category' => $requestData['course_category'] ?? '',
            'date_from' => $requestData['date_from'] ?? '',
            'date_to' => $requestData['date_to'] ?? '',
            'include_archived' => $requestData['include_archived'] ?? false,
            'confidence_min' => $requestData['confidence_min'] ?? '',
            'confidence_max' => $requestData['confidence_max'] ?? '',
        ];
        
        // Handle multi-select arrays from form
        foreach (['status', 'barangay_id', 'university_id', 'year_level_id'] as $field) {
            if (!is_array($this->filters[$field])) {
                $this->filters[$field] = !empty($this->filters[$field]) ? [$this->filters[$field]] : [];
            }
            // Remove empty values
            $this->filters[$field] = array_filter($this->filters[$field], function($v) {
                return $v !== '' && $v !== null;
            });
        }
        
        return $this;
    }
    
    /**
     * Build filtered query for students
     */
    public function buildStudentQuery($includeJoins = true) {
        $this->whereConditions = ['1=1'];
        $this->params = [];
        $this->paramCount = 1;
        
        // Status filter (multi-select)
        if (!empty($this->filters['status'])) {
            $placeholders = [];
            foreach ($this->filters['status'] as $status) {
                $placeholders[] = "\$$this->paramCount";
                $this->params[] = $status;
                $this->paramCount++;
            }
            $this->whereConditions[] = "s.status IN (" . implode(',', $placeholders) . ")";
        } else {
            // Default: exclude archived unless explicitly included
            if (!$this->filters['include_archived']) {
                $this->whereConditions[] = "(s.is_archived = FALSE OR s.is_archived IS NULL)";
            }
        }
        
        // Gender filter
        if (!empty($this->filters['gender'])) {
            $this->whereConditions[] = "s.sex = \$$this->paramCount";
            $this->params[] = $this->filters['gender'];
            $this->paramCount++;
        }
        
        // Barangay filter (multi-select)
        if (!empty($this->filters['barangay_id'])) {
            $placeholders = [];
            foreach ($this->filters['barangay_id'] as $barangay) {
                $placeholders[] = "\$$this->paramCount";
                $this->params[] = $barangay;
                $this->paramCount++;
            }
            $this->whereConditions[] = "s.barangay_id IN (" . implode(',', $placeholders) . ")";
        }
        
        // Municipality filter
        if (!empty($this->filters['municipality_id'])) {
            $this->whereConditions[] = "s.municipality_id = \$$this->paramCount";
            $this->params[] = $this->filters['municipality_id'];
            $this->paramCount++;
        }
        
        // University filter (multi-select)
        if (!empty($this->filters['university_id'])) {
            $placeholders = [];
            foreach ($this->filters['university_id'] as $university) {
                $placeholders[] = "\$$this->paramCount";
                $this->params[] = $university;
                $this->paramCount++;
            }
            $this->whereConditions[] = "s.university_id IN (" . implode(',', $placeholders) . ")";
        }
        
        // Year level filter (multi-select)
        if (!empty($this->filters['year_level_id'])) {
            $placeholders = [];
            foreach ($this->filters['year_level_id'] as $yearLevel) {
                $placeholders[] = "\$$this->paramCount";
                $this->params[] = $yearLevel;
                $this->paramCount++;
            }
            $this->whereConditions[] = "s.year_level_id IN (" . implode(',', $placeholders) . ")";
        }
        
        // Distribution filter
        if (!empty($this->filters['distribution_id'])) {
            $this->whereConditions[] = "EXISTS (
                SELECT 1 FROM distribution_student_records dsr
                INNER JOIN distribution_snapshots ds ON ds.snapshot_id = dsr.snapshot_id
                WHERE dsr.student_id = s.student_id
                AND ds.snapshot_id = \$$this->paramCount
            )";
            $this->params[] = $this->filters['distribution_id'];
            $this->paramCount++;
        }
        
        // Academic year filter
        if (!empty($this->filters['academic_year'])) {
            $this->whereConditions[] = "s.current_academic_year = \$$this->paramCount";
            $this->params[] = $this->filters['academic_year'];
            $this->paramCount++;
        }
        
        // Course category filter removed - course mapping no longer used
        
        // Date range filter (registration date)
        if (!empty($this->filters['date_from'])) {
            $this->whereConditions[] = "s.application_date >= \$$this->paramCount";
            $this->params[] = $this->filters['date_from'];
            $this->paramCount++;
        }
        if (!empty($this->filters['date_to'])) {
            $this->whereConditions[] = "s.application_date <= \$$this->paramCount";
            $this->params[] = $this->filters['date_to'];
            $this->paramCount++;
        }
        
        // Confidence score range
        if (!empty($this->filters['confidence_min'])) {
            $this->whereConditions[] = "s.confidence_score >= \$$this->paramCount";
            $this->params[] = $this->filters['confidence_min'];
            $this->paramCount++;
        }
        if (!empty($this->filters['confidence_max'])) {
            $this->whereConditions[] = "s.confidence_score <= \$$this->paramCount";
            $this->params[] = $this->filters['confidence_max'];
            $this->paramCount++;
        }
        
        $whereClause = implode(' AND ', $this->whereConditions);
        
        // Build complete query
        if ($includeJoins) {
            $query = "
                SELECT 
                    s.student_id,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    s.extension_name,
                    s.sex,
                    s.bdate,
                    s.email,
                    s.mobile,
                    s.status,
                    s.course,
                    s.application_date,
                    s.confidence_score,
                    s.current_academic_year,
                    s.first_registered_academic_year,
                    s.expected_graduation_year,
                    s.school_student_id,
                    s.payroll_no AS latest_payroll,
                    ARRAY(SELECT dp.payroll_no FROM distribution_payrolls dp WHERE dp.student_id = s.student_id ORDER BY dp.assigned_at ASC) AS payroll_history,
                    b.name AS barangay,
                    m.name AS municipality,
                    u.name AS university,
                    yl.name AS year_level,
                    CASE 
                        WHEN s.is_archived THEN 'Archived'
                        ELSE INITCAP(s.status)
                    END AS status_display
                FROM students s
                LEFT JOIN barangays b ON s.barangay_id = b.barangay_id
                LEFT JOIN municipalities m ON s.municipality_id = m.municipality_id
                LEFT JOIN universities u ON s.university_id = u.university_id
                LEFT JOIN year_levels yl ON s.year_level_id = yl.year_level_id
                WHERE $whereClause
                ORDER BY s.last_name, s.first_name
            ";
        } else {
            $query = "
                SELECT COUNT(*) as total
                FROM students s
                WHERE $whereClause
            ";
        }
        
        return [
            'query' => $query,
            'params' => $this->params,
            'filters_applied' => $this->getAppliedFiltersCount()
        ];
    }
    
    /**
     * Get statistics for dashboard
     */
    public function getStatistics() {
        $whereClause = implode(' AND ', $this->whereConditions);
        
        $statsQuery = "
            SELECT 
                COUNT(*) as total_students,
                COUNT(CASE WHEN s.sex = 'Male' THEN 1 END) as male_count,
                COUNT(CASE WHEN s.sex = 'Female' THEN 1 END) as female_count,
                COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_count,
                COUNT(CASE WHEN s.status = 'applicant' THEN 1 END) as applicant_count,
                COUNT(CASE WHEN s.is_archived = TRUE THEN 1 END) as archived_count,
                ROUND(AVG(s.confidence_score), 2) as avg_confidence,
                COUNT(DISTINCT s.municipality_id) as municipalities,
                COUNT(DISTINCT s.barangay_id) as barangays,
                COUNT(DISTINCT s.university_id) as universities
            FROM students s
            WHERE " . implode(' AND ', $this->whereConditions);
        
        $result = pg_query_params($this->connection, $statsQuery, $this->params);
        return pg_fetch_assoc($result);
    }
    
    /**
     * Get distribution breakdown
     */
    public function getDistributionBreakdown() {
        $whereClause = implode(' AND ', $this->whereConditions);
        
        $query = "
            SELECT 
                ds.snapshot_id,
                ds.distribution_id,
                ds.academic_year,
                ds.semester,
                ds.finalized_at,
                COUNT(DISTINCT dsr.student_id) as student_count
            FROM distribution_snapshots ds
            INNER JOIN distribution_student_records dsr ON dsr.snapshot_id = ds.snapshot_id
            INNER JOIN students s ON s.student_id = dsr.student_id
            WHERE " . implode(' AND ', $this->whereConditions) . "
            GROUP BY ds.snapshot_id, ds.distribution_id, ds.academic_year, ds.semester, ds.finalized_at
            ORDER BY ds.finalized_at DESC
            LIMIT 10
        ";
        
        $result = pg_query_params($this->connection, $query, $this->params);
        $distributions = [];
        while ($row = pg_fetch_assoc($result)) {
            $distributions[] = $row;
        }
        return $distributions;
    }
    
    /**
     * Get count of applied filters
     */
    public function getAppliedFiltersCount() {
        $count = 0;
        foreach ($this->filters as $key => $value) {
            if ($key === 'include_archived') continue;
            if (is_array($value) && !empty($value)) $count++;
            if (is_string($value) && $value !== '') $count++;
        }
        return $count;
    }
    
    /**
     * Get human-readable filter summary
     */
    public function getFilterSummary() {
        $summary = [];
        
        if (!empty($this->filters['status'])) {
            $summary[] = 'Status: ' . implode(', ', array_map('ucfirst', $this->filters['status']));
        }
        if (!empty($this->filters['gender'])) {
            $summary[] = 'Gender: ' . $this->filters['gender'];
        }
        if (!empty($this->filters['municipality_id'])) {
            $result = pg_query_params($this->connection, 
                "SELECT name FROM municipalities WHERE municipality_id = $1", 
                [$this->filters['municipality_id']]);
            if ($row = pg_fetch_assoc($result)) {
                $summary[] = 'Municipality: ' . $row['name'];
            }
        }
        if (!empty($this->filters['barangay_id'])) {
            $placeholders = implode(',', array_map(function($i) { return '$' . ($i + 1); }, array_keys($this->filters['barangay_id'])));
            $result = pg_query_params($this->connection, 
                "SELECT name FROM barangays WHERE barangay_id IN ($placeholders)", 
                array_values($this->filters['barangay_id']));
            $names = [];
            while ($row = pg_fetch_assoc($result)) {
                $names[] = $row['name'];
            }
            if (!empty($names)) {
                $summary[] = 'Barangay: ' . implode(', ', $names);
            }
        }
        if (!empty($this->filters['university_id'])) {
            $placeholders = implode(',', array_map(function($i) { return '$' . ($i + 1); }, array_keys($this->filters['university_id'])));
            $result = pg_query_params($this->connection, 
                "SELECT name FROM universities WHERE university_id IN ($placeholders)", 
                array_values($this->filters['university_id']));
            $names = [];
            while ($row = pg_fetch_assoc($result)) {
                $names[] = $row['name'];
            }
            if (!empty($names)) {
                $summary[] = 'University: ' . implode(', ', $names);
            }
        }
        if (!empty($this->filters['year_level_id'])) {
            $placeholders = implode(',', array_map(function($i) { return '$' . ($i + 1); }, array_keys($this->filters['year_level_id'])));
            $result = pg_query_params($this->connection, 
                "SELECT name FROM year_levels WHERE year_level_id IN ($placeholders)", 
                array_values($this->filters['year_level_id']));
            $names = [];
            while ($row = pg_fetch_assoc($result)) {
                $names[] = $row['name'];
            }
            if (!empty($names)) {
                $summary[] = 'Year Level: ' . implode(', ', $names);
            }
        }
        if (!empty($this->filters['academic_year'])) {
            $summary[] = 'Academic Year: ' . $this->filters['academic_year'];
        }
        if (!empty($this->filters['distribution_id'])) {
            $result = pg_query_params($this->connection, 
                "SELECT 
                    distribution_id, 
                    academic_year, 
                    semester,
                    distribution_date,
                    location,
                    finalized_at
                FROM distribution_snapshots 
                WHERE snapshot_id = $1", 
                [$this->filters['distribution_id']]);
            if ($result && $row = pg_fetch_assoc($result)) {
                $distInfo = 'Distribution: ' . $row['distribution_id'] . ' (' . $row['academic_year'] . ' - ' . $row['semester'] . ')';
                
                // Add date if available
                if (!empty($row['distribution_date'])) {
                    $distInfo .= ' | Date: ' . date('F d, Y', strtotime($row['distribution_date']));
                }
                
                // Add location if available
                if (!empty($row['location'])) {
                    $distInfo .= ' | Location: ' . $row['location'];
                }
                
                $summary[] = $distInfo;
            }
        }
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $dateRange = 'Registration Date: ';
            if (!empty($this->filters['date_from'])) $dateRange .= 'From ' . $this->filters['date_from'] . ' ';
            if (!empty($this->filters['date_to'])) $dateRange .= 'To ' . $this->filters['date_to'];
            $summary[] = $dateRange;
        }
        if (!empty($this->filters['confidence_min']) || !empty($this->filters['confidence_max'])) {
            $confRange = 'Confidence Score: ';
            if (!empty($this->filters['confidence_min'])) $confRange .= 'Min ' . $this->filters['confidence_min'] . '% ';
            if (!empty($this->filters['confidence_max'])) $confRange .= 'Max ' . $this->filters['confidence_max'] . '%';
            $summary[] = $confRange;
        }
        if (!empty($this->filters['include_archived'])) {
            $summary[] = 'Including Archived Students';
        }
        
        return !empty($summary) ? $summary : ['No filters applied - showing all records'];
    }
    
    /**
     * Get detailed distribution credentials (date, time, location)
     * Returns array with distribution details or null if no distribution filter
     */
    public function getDistributionCredentials() {
        try {
            if (empty($this->filters['distribution_id'])) {
                return null;
            }
            
            $result = pg_query_params($this->connection, 
                "SELECT 
                    distribution_id, 
                    academic_year, 
                    semester,
                    distribution_date,
                    location,
                    finalized_at,
                    finalized_by,
                    notes
                FROM distribution_snapshots 
                WHERE snapshot_id = $1", 
                [$this->filters['distribution_id']]);
                
            if (!$result) {
                error_log('getDistributionCredentials - Database query failed: ' . pg_last_error($this->connection));
                return null;
            }
                
            if ($row = pg_fetch_assoc($result)) {
                return [
                    'distribution_id' => $row['distribution_id'] ?? '',
                    'academic_year' => $row['academic_year'] ?? '',
                    'semester' => $row['semester'] ?? '',
                    'distribution_date' => $row['distribution_date'] ?? null,
                    'distribution_location' => $row['location'] ?? '',
                    'finalized_at' => $row['finalized_at'] ?? null,
                    'finalized_by' => $row['finalized_by'] ?? null,
                    'notes' => $row['notes'] ?? '',
                    'formatted_date' => !empty($row['distribution_date']) ? date('F d, Y', strtotime($row['distribution_date'])) : 'Not specified',
                    'formatted_datetime' => !empty($row['distribution_date']) ? date('F d, Y', strtotime($row['distribution_date'])) : 'Not specified'
                ];
            }
            
            return null;
        } catch (Exception $e) {
            error_log('getDistributionCredentials - Exception: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get current filters array
     */
    public function getFilters() {
        return $this->filters;
    }
}
