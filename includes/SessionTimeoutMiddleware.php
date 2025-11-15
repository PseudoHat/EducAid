<?php
/**
 * Session Timeout Middleware
 * 
 * Enforces idle timeout and absolute timeout for all authenticated sessions.
 * Updates last_activity timestamp and checks for timeout conditions.
 * 
 * Features:
 * - Idle timeout (configurable minutes of inactivity)
 * - Absolute timeout (max session duration)
 * - Activity tracking (updates on every request)
 * - Remember Me support (bypass timeouts for long-lived sessions)
 * 
 * @package EducAid
 * @version 1.0.0
 */

class SessionTimeoutMiddleware {
    private $db;
    private $idleTimeoutMinutes;
    private $absoluteTimeoutHours;
    private $warningBeforeLogoutSeconds;
    
    /**
     * Initialize middleware with database connection and timeout settings
     */
    public function __construct($db = null) {
        if ($db === null) {
            // Load database connection from config
            require_once __DIR__ . '/../config/database.php';
            global $connection;
            $this->db = $connection;
        } else {
            $this->db = $db;
        }
        
        // Load timeout settings from environment
        $this->idleTimeoutMinutes = (int) (getenv('SESSION_IDLE_TIMEOUT_MINUTES') ?: 30);
        $this->absoluteTimeoutHours = (int) (getenv('SESSION_ABSOLUTE_TIMEOUT_HOURS') ?: 8);
        $this->warningBeforeLogoutSeconds = (int) (getenv('SESSION_WARNING_BEFORE_LOGOUT_SECONDS') ?: 120);
    }
    
    /**
     * Process the session timeout logic
     * Called on every request to check and enforce timeouts
     * 
     * @return array Status information for frontend warnings
     */
    public function handle() {
        // Only process if user is logged in
        if (!$this->isAuthenticated()) {
            return ['status' => 'not_authenticated'];
        }
        
        // Skip timeout enforcement for admin sessions (admins have their own session management)
        if (isset($_SESSION['admin_id'])) {
            return ['status' => 'admin_session', 'skip_timeout' => true];
        }
        
        // Check if "Remember Me" is active (skip timeouts)
        if ($this->hasRememberMeToken()) {
            $this->updateActivity();
            return ['status' => 'remember_me_active'];
        }
        
        // Get session data
        $sessionData = $this->getSessionData();
        
        if (!$sessionData) {
            // Session not found in database - force logout
            $this->forceLogout('session_not_found');
            return ['status' => 'logged_out', 'reason' => 'session_not_found'];
        }
        
        // Check for absolute timeout (max session duration)
        if ($this->hasAbsoluteTimeout($sessionData['created_at'])) {
            $this->forceLogout('absolute_timeout');
            return ['status' => 'logged_out', 'reason' => 'absolute_timeout'];
        }
        
        // Check for idle timeout (inactivity)
        if ($this->hasIdleTimeout($sessionData['last_activity'])) {
            $this->forceLogout('idle_timeout');
            return ['status' => 'logged_out', 'reason' => 'idle_timeout'];
        }
        
        // Calculate time remaining until timeout
        $timeUntilIdleTimeout = $this->getTimeUntilIdleTimeout($sessionData['last_activity']);
        $timeUntilAbsoluteTimeout = $this->getTimeUntilAbsoluteTimeout($sessionData['created_at']);
        
        // Update last activity timestamp
        $this->updateActivity();
        
        // Return status for frontend warning system
        return [
            'status' => 'active',
            'idle_timeout_seconds' => $this->idleTimeoutMinutes * 60,
            'absolute_timeout_seconds' => $this->absoluteTimeoutHours * 3600,
            'time_until_idle_timeout' => $timeUntilIdleTimeout,
            'time_until_absolute_timeout' => $timeUntilAbsoluteTimeout,
            'warning_threshold' => $this->warningBeforeLogoutSeconds,
            'should_warn' => $timeUntilIdleTimeout <= $this->warningBeforeLogoutSeconds
        ];
    }
    
    /**
     * Check if user is authenticated
     */
    private function isAuthenticated() {
        return isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
    }
    
    /**
     * Check if Remember Me token exists
     */
    private function hasRememberMeToken() {
        return isset($_COOKIE['remember_me_token']) && !empty($_COOKIE['remember_me_token']);
    }
    
    /**
     * Get session data from database
     */
    private function getSessionData() {
        if (!isset($_SESSION['student_id'])) {
            return null;
        }
        
        try {
            $result = pg_query_params($this->db, 
                "SELECT session_id, student_id, created_at, last_activity, expires_at
                FROM student_active_sessions
                WHERE student_id = $1 
                AND session_id = $2
                LIMIT 1",
                [$_SESSION['student_id'], session_id()]
            );
            
            if ($result === false) {
                error_log("Session timeout middleware error: " . pg_last_error($this->db));
                return null;
            }
            
            return pg_fetch_assoc($result);
        } catch (Exception $e) {
            error_log("Session timeout middleware error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if absolute timeout has been reached
     * 
     * @param string $createdAt Session creation timestamp
     * @return bool True if session has exceeded max duration
     */
    private function hasAbsoluteTimeout($createdAt) {
        $createdTime = strtotime($createdAt);
        $maxSessionDuration = $this->absoluteTimeoutHours * 3600; // Convert hours to seconds
        $currentTime = time();
        
        return ($currentTime - $createdTime) > $maxSessionDuration;
    }
    
    /**
     * Check if idle timeout has been reached
     * 
     * @param string $lastActivity Last activity timestamp
     * @return bool True if session has been inactive too long
     */
    private function hasIdleTimeout($lastActivity) {
        $lastActivityTime = strtotime($lastActivity);
        $maxIdleDuration = $this->idleTimeoutMinutes * 60; // Convert minutes to seconds
        $currentTime = time();
        
        return ($currentTime - $lastActivityTime) > $maxIdleDuration;
    }
    
    /**
     * Calculate seconds until idle timeout
     */
    private function getTimeUntilIdleTimeout($lastActivity) {
        $lastActivityTime = strtotime($lastActivity);
        $maxIdleDuration = $this->idleTimeoutMinutes * 60;
        $elapsed = time() - $lastActivityTime;
        $remaining = $maxIdleDuration - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Calculate seconds until absolute timeout
     */
    private function getTimeUntilAbsoluteTimeout($createdAt) {
        $createdTime = strtotime($createdAt);
        $maxSessionDuration = $this->absoluteTimeoutHours * 3600;
        $elapsed = time() - $createdTime;
        $remaining = $maxSessionDuration - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Update last_activity timestamp in database
     */
    private function updateActivity() {
        if (!isset($_SESSION['student_id'])) {
            return;
        }
        
        try {
            $result = pg_query_params($this->db,
                "UPDATE student_active_sessions
                SET last_activity = NOW()
                WHERE student_id = $1 
                AND session_id = $2",
                [$_SESSION['student_id'], session_id()]
            );
            
            if ($result === false) {
                error_log("Failed to update activity: " . pg_last_error($this->db));
            }
        } catch (Exception $e) {
            error_log("Failed to update activity: " . $e->getMessage());
        }
    }
    
    /**
     * Force logout and destroy session
     * 
     * @param string $reason Reason for logout (for logging)
     */
    private function forceLogout($reason) {
        // Log the timeout event
        error_log("Session timeout - Reason: {$reason}, Student ID: " . ($_SESSION['student_id'] ?? 'unknown'));
        
        // Remove session from database
        if (isset($_SESSION['student_id'])) {
            try {
                $result = pg_query_params($this->db,
                    "DELETE FROM student_active_sessions
                    WHERE student_id = $1 
                    AND session_id = $2",
                    [$_SESSION['student_id'], session_id()]
                );
                
                if ($result === false) {
                    error_log("Failed to delete session: " . pg_last_error($this->db));
                }
            } catch (Exception $e) {
                error_log("Failed to delete session: " . $e->getMessage());
            }
        }
        
        // Destroy PHP session
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        // Redirect to login with timeout message
        // unified_login.php is in the root directory
        $redirectUrl = '/unified_login.php?timeout=' . urlencode($reason);
        
        // Handle AJAX requests differently
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status' => 'session_expired',
                'reason' => $reason,
                'redirect' => $redirectUrl
            ]);
            exit;
        }
        
        // Regular redirect
        header("Location: {$redirectUrl}");
        exit;
    }
    
    /**
     * Get timeout configuration for JavaScript
     * Used to initialize frontend warning system
     * 
     * @return array Configuration array
     */
    public function getTimeoutConfig() {
        return [
            'idle_timeout_minutes' => $this->idleTimeoutMinutes,
            'absolute_timeout_hours' => $this->absoluteTimeoutHours,
            'warning_before_logout_seconds' => $this->warningBeforeLogoutSeconds,
            'enabled' => true
        ];
    }
}
