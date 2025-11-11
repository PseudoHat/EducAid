<?php
/**
 * CSRF Helper - Enhanced error handling with token refresh
 * Use this instead of directly calling CSRFProtection::validateToken()
 * in AJAX endpoints to provide better user experience
 */

require_once __DIR__ . '/CSRFProtection.php';

class CSRFHelper {
    
    /**
     * Validate token and return JSON response with new token on failure
     * 
     * @param string $form_name Form identifier
     * @param string $token Token to validate
     * @param bool $consume Whether to consume token (default: true)
     * @return bool True if valid, false otherwise (sends JSON and exits on failure)
     */
    public static function validateOrFail($form_name, $token, $consume = true) {
        $valid = CSRFProtection::validateToken($form_name, $token, $consume);
        
        if (!$valid) {
            // Generate a new token for retry
            $newToken = CSRFProtection::generateToken($form_name);
            
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'csrf_failed',
                'message' => 'Security validation failed. Please try again.',
                'new_token' => $newToken, // Provide new token for automatic retry
                'requires_refresh' => false // Set to true if full page reload needed
            ]);
            exit;
        }
        
        return true;
    }
    
    /**
     * Get CSRF token from request (POST, header, or GET)
     * 
     * @return string Token or empty string if not found
     */
    public static function getTokenFromRequest() {
        return $_POST['csrf_token'] 
            ?? $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? $_GET['csrf_token'] 
            ?? '';
    }
    
    /**
     * Validate token from request with better error handling
     * 
     * @param string $form_name Form identifier
     * @param bool $consume Whether to consume token (default: true)
     * @return bool Always returns true or exits with JSON error
     */
    public static function validateRequest($form_name, $consume = true) {
        $token = self::getTokenFromRequest();
        return self::validateOrFail($form_name, $token, $consume);
    }
}
