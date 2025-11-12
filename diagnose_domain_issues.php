<?php
/**
 * Domain Issues Diagnostic Tool
 * Run this on Railway to check configuration
 * Access: https://www.educ-aid.site/diagnose_domain_issues.php
 */

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Domain Issues Diagnostic</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #0f0; background: #000; }
        .title { color: #0ff; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .ok { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .info { color: #00f; }
        pre { background: #222; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 EducAid Domain Issues Diagnostic</h1>
    <p>Domain: <strong><?php echo $_SERVER['HTTP_HOST'] ?? 'unknown'; ?></strong></p>
    <p>Date: <?php echo date('Y-m-d H:i:s'); ?></p>

    <div class="section">
        <div class="title">1. Environment Variables Check</div>
        
        <?php
        $requiredEnvVars = [
            'DATABASE_URL' => 'Database connection',
            'RECAPTCHA_V3_SITE_KEY' => 'reCAPTCHA v3 Site Key',
            'RECAPTCHA_V3_SECRET_KEY' => 'reCAPTCHA v3 Secret Key',
            'RECAPTCHA_V2_SITE_KEY' => 'reCAPTCHA v2 Site Key',
            'RECAPTCHA_V2_SECRET_KEY' => 'reCAPTCHA v2 Secret Key',
            'GEMINI_API_KEY' => 'Gemini AI API Key'
        ];

        foreach ($requiredEnvVars as $var => $description) {
            $value = getenv($var);
            if ($value && $value !== '') {
                $masked = substr($value, 0, 10) . '...' . substr($value, -5);
                echo "<p class='ok'>✅ {$var}: SET ({$masked})</p>";
            } else {
                echo "<p class='error'>❌ {$var}: NOT SET - {$description}</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <div class="title">2. reCAPTCHA Configuration</div>
        <?php
        require_once __DIR__ . '/config/recaptcha_config.php';
        
        echo "<p><strong>Active Site Key (v3):</strong> " . substr(RECAPTCHA_SITE_KEY, 0, 20) . "...</p>";
        echo "<p><strong>Active Site Key (v2):</strong> " . substr(RECAPTCHA_V2_SITE_KEY, 0, 20) . "...</p>";
        
        // Check if using test keys
        if (RECAPTCHA_SITE_KEY === '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI') {
            echo "<p class='warning'>⚠️ WARNING: Using Google's TEST SITE KEY - will not work in production!</p>";
        }
        
        if (RECAPTCHA_SECRET_KEY === '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe') {
            echo "<p class='warning'>⚠️ WARNING: Using Google's TEST SECRET KEY - will not work in production!</p>";
        }
        ?>
    </div>

    <div class="section">
        <div class="title">3. Gemini API Check</div>
        <?php
        $geminiKey = getenv('GEMINI_API_KEY');
        if ($geminiKey && $geminiKey !== '') {
            echo "<p class='ok'>✅ Gemini API Key is SET</p>";
            echo "<p>Key prefix: " . substr($geminiKey, 0, 10) . "...</p>";
            
            // Test API
            $testUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=' . urlencode($geminiKey);
            $testData = json_encode([
                'contents' => [['parts' => [['text' => 'Hello']]]]
            ]);
            
            $ch = curl_init($testUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                echo "<p class='ok'>✅ Gemini API is WORKING</p>";
            } else {
                echo "<p class='error'>❌ Gemini API returned HTTP {$httpCode}</p>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        } else {
            echo "<p class='error'>❌ Gemini API Key is NOT SET</p>";
        }
        ?>
    </div>

    <div class="section">
        <div class="title">4. Database Connection</div>
        <?php
        try {
            require_once __DIR__ . '/config/database.php';
            if (isset($connection) && $connection) {
                echo "<p class='ok'>✅ Database connection successful</p>";
                
                // Test query
                $result = pg_query($connection, "SELECT current_database(), version()");
                if ($result) {
                    $row = pg_fetch_assoc($result);
                    echo "<p>Database: " . htmlspecialchars($row['current_database']) . "</p>";
                    echo "<p>PostgreSQL version: " . htmlspecialchars($row['version']) . "</p>";
                }
            } else {
                echo "<p class='error'>❌ Database connection failed</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <div class="section">
        <div class="title">5. Server Information</div>
        <?php
        echo "<p><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</p>";
        echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
        echo "<p><strong>Host:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "</p>";
        echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "</p>";
        echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "</p>";
        ?>
    </div>

    <div class="section">
        <div class="title">6. File Permissions Check</div>
        <?php
        $checkDirs = [
            __DIR__ . '/uploads',
            __DIR__ . '/student_documents',
            __DIR__ . '/logs'
        ];
        
        foreach ($checkDirs as $dir) {
            if (file_exists($dir)) {
                $perms = substr(sprintf('%o', fileperms($dir)), -4);
                $writable = is_writable($dir);
                if ($writable) {
                    echo "<p class='ok'>✅ {$dir} - Writable (perms: {$perms})</p>";
                } else {
                    echo "<p class='error'>❌ {$dir} - Not writable (perms: {$perms})</p>";
                }
            } else {
                echo "<p class='warning'>⚠️ {$dir} - Does not exist</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <div class="title">7. Recommendations</div>
        <?php
        $issues = [];
        
        if (!getenv('RECAPTCHA_V3_SITE_KEY') || getenv('RECAPTCHA_V3_SITE_KEY') === '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI') {
            $issues[] = "Set production reCAPTCHA v3 keys in Railway environment variables";
        }
        
        if (!getenv('RECAPTCHA_V2_SITE_KEY') || getenv('RECAPTCHA_V2_SITE_KEY') === '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI') {
            $issues[] = "Set production reCAPTCHA v2 keys in Railway environment variables";
        }
        
        if (!getenv('GEMINI_API_KEY')) {
            $issues[] = "Set GEMINI_API_KEY in Railway environment variables";
        }
        
        if (empty($issues)) {
            echo "<p class='ok'>✅ No critical issues found!</p>";
        } else {
            echo "<p class='warning'>⚠️ Issues to fix:</p>";
            echo "<ol>";
            foreach ($issues as $issue) {
                echo "<li class='warning'>{$issue}</li>";
            }
            echo "</ol>";
        }
        ?>
    </div>

    <div class="section">
        <div class="title">8. Quick Fix Guide</div>
        <ol>
            <li>Go to Railway Dashboard → Your Project → Variables</li>
            <li>Add these environment variables:
                <pre>RECAPTCHA_V3_SITE_KEY=your_key_here
RECAPTCHA_V3_SECRET_KEY=your_secret_here
RECAPTCHA_V2_SITE_KEY=your_key_here
RECAPTCHA_V2_SECRET_KEY=your_secret_here
GEMINI_API_KEY=your_key_here</pre>
            </li>
            <li>Click "Deploy" to restart with new variables</li>
            <li>Refresh this page to verify</li>
        </ol>
        
        <p><strong>Get reCAPTCHA keys:</strong> <a href="https://www.google.com/recaptcha/admin" target="_blank">https://www.google.com/recaptcha/admin</a></p>
        <p><strong>Get Gemini API key:</strong> <a href="https://makersuite.google.com/app/apikey" target="_blank">https://makersuite.google.com/app/apikey</a></p>
    </div>

    <p style="margin-top: 40px; text-align: center; color: #666;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?> | 
        <a href="?" style="color: #0ff;">Refresh</a>
    </p>
</body>
</html>
