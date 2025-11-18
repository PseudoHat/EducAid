<?php
/**
 * PayrollHistoryService
 * Lightweight helper to record and fetch student payroll assignment history.
 */
class PayrollHistoryService {
    private $connection;
    public function __construct($connection) { $this->connection = $connection; }

    /**
     * Record a payroll assignment for a student per academic year + semester.
     */
    public function record($studentId, $payrollNo, $academicYear, $semester, $snapshotId = null) {
        if (!$studentId || !$payrollNo || !$academicYear || !$semester) return false;
        $sql = "INSERT INTO distribution_payrolls (student_id, payroll_no, academic_year, semester, snapshot_id)\n                VALUES ($1,$2,$3,$4,$5)\n                ON CONFLICT (student_id, academic_year, semester)\n                DO UPDATE SET payroll_no = EXCLUDED.payroll_no, assigned_at = NOW(), snapshot_id = COALESCE(EXCLUDED.snapshot_id, distribution_payrolls.snapshot_id)";
        return pg_query_params($this->connection, $sql, [
            $studentId,
            $payrollNo,
            $academicYear,
            $semester,
            $snapshotId
        ]) !== false;
    }

    /** Fetch full payroll history ordered by assignment time */
    public function getHistory($studentId) {
        $res = pg_query_params($this->connection, "SELECT payroll_no, academic_year, semester, assigned_at FROM distribution_payrolls WHERE student_id=$1 ORDER BY assigned_at ASC", [$studentId]);
        return $res ? pg_fetch_all($res) ?: [] : [];
    }
}
?>