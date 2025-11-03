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
// Accept either RECAPTCHA_V2_SITE_KEY / RECAPTCHA_V2_SECRET_KEY or the older RECAPTCHA_SITE_KEY / RECAPTCHA_SECRET_KEY

// Try v2-specific env vars first, then fall back to generic names some deploy UIs use.
$siteKeyV2 = getenv('RECAPTCHA_V2_SITE_KEY');
$siteKeyAlt = getenv('RECAPTCHA_SITE_KEY');
$secretV2 = getenv('RECAPTCHA_V2_SECRET_KEY');
$secretAlt = getenv('RECAPTCHA_SECRET_KEY');

// Fallback (only if env vars are not provided). Replace with your local dev keys if needed.
$fallbackSiteKey = '6LcQ9NArAAAAALTbYBJn1b2iG9MJcJ6SnA3b6x53';
$fallbackSecret  = 'D';

// Choose the first non-empty value and remember which env var provided it for logging.
$siteKey = ($siteKeyV2 !== false && $siteKeyV2 !== '') ? $siteKeyV2 : (($siteKeyAlt !== false && $siteKeyAlt !== '') ? $siteKeyAlt : $fallbackSiteKey);
$siteKeySource = ($siteKeyV2 !== false && $siteKeyV2 !== '') ? 'RECAPTCHA_V2_SITE_KEY' : (($siteKeyAlt !== false && $siteKeyAlt !== '') ? 'RECAPTCHA_SITE_KEY' : 'fallback');

$secret = ($secretV2 !== false && $secretV2 !== '') ? $secretV2 : (($secretAlt !== false && $secretAlt !== '') ? $secretAlt : $fallbackSecret);
$secretSource = ($secretV2 !== false && $secretV2 !== '') ? 'RECAPTCHA_V2_SECRET_KEY' : (($secretAlt !== false && $secretAlt !== '') ? 'RECAPTCHA_SECRET_KEY' : 'fallback');

define('RECAPTCHA_V2_SITE_KEY', $siteKey);
define('RECAPTCHA_V2_SECRET_KEY', $secret);

// Log which env vars supplied the keys (do not log actual secret values).
if (php_sapi_name() !== 'cli') {
	error_log(sprintf('reCAPTCHA: site key source=%s, secret key source=%s', $siteKeySource, $secretSource));
}

// Warning when running without a valid secret (helps debug staging issues)
if (defined('RECAPTCHA_V2_SECRET_KEY') && RECAPTCHA_V2_SECRET_KEY === 'D') {
	// Avoid printing in production; write to a local debug log if available
	if (php_sapi_name() !== 'cli') {
		error_log('Warning: RECAPTCHA_V2_SECRET_KEY is using the placeholder value. Set RECAPTCHA_V2_SECRET_KEY or RECAPTCHA_SECRET_KEY in env vars.');
	}
}
?>