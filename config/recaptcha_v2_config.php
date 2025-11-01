<?php
/**
 * Google reCAPTCHA v2 Configuration for Landing Page
 * 
 * To get your v2 keys:
 * 1. Go to https://www.google.com/recaptcha/admin/create
 * 2. Choose reCAPTCHA v2 ("I'm not a robot" Checkbox)
 * 3. Add your domain (localhost for development)
 * 4. Copy the Site Key and Secret Key below
 * 
 * IMPORTANT: v2 keys are different from v3 keys!
 */

// Prefer environment variables for keys (do NOT commit real secrets to the repo).
// Set RECAPTCHA_V2_SITE_KEY and RECAPTCHA_V2_SECRET_KEY in your deployment environment (Railway / Heroku / etc.).

$envSiteKey = getenv('RECAPTCHA_V2_SITE_KEY');
$envSecret  = getenv('RECAPTCHA_V2_SECRET_KEY');

// Fallback (only if env vars are not provided). Replace with your local dev keys if needed.
$fallbackSiteKey = '6LcQ9NArAAAAALTbYBJn1b2iG9MJcJ6SnA3b6x53';
$fallbackSecret  = 'D';

define('RECAPTCHA_V2_SITE_KEY', $envSiteKey !== false && $envSiteKey !== '' ? $envSiteKey : $fallbackSiteKey);
define('RECAPTCHA_V2_SECRET_KEY', $envSecret !== false && $envSecret !== '' ? $envSecret : $fallbackSecret);

// Warning when running without a valid secret (helps debug staging issues)
if (defined('RECAPTCHA_V2_SECRET_KEY') && RECAPTCHA_V2_SECRET_KEY === 'D') {
	// Avoid printing in production; write to a local debug log if available
	if (php_sapi_name() !== 'cli') {
		error_log('Warning: RECAPTCHA_V2_SECRET_KEY is using the placeholder value. Set RECAPTCHA_V2_SECRET_KEY in env vars.');
	}
}
?>