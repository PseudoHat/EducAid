<?php
// Test the history endpoint directly
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/CSRFProtection.php';

// Simulate being logged in as super admin
$_SESSION['admin_id'] = 1;
$_SESSION['role'] = 'super_admin';

// Generate a CSRF token
$token = CSRFProtection::generateToken('cms_content');

echo "<h2>History Endpoint Test</h2>";
echo "<p>CSRF Token: <code>{$token}</code></p>";

// Test the endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8001/website/ajax_get_landing_history.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'block' => '',
    'limit' => 10,
    'action_type' => ''
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-CSRF-Token: ' . $token
]);

// Share the same session
$cookie = session_name() . '=' . session_id();
curl_setopt($ch, CURLOPT_COOKIE, $cookie);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>Response (HTTP {$http_code}):</h3>";
echo "<pre>";
$decoded = json_decode($response, true);
if ($decoded) {
    echo json_encode($decoded, JSON_PRETTY_PRINT);
} else {
    echo htmlspecialchars($response);
}
echo "</pre>";

// Also test direct query
echo "<h3>Direct Database Query Test:</h3>";
$sql = "SELECT audit_id, block_key, action_type, created_at 
        FROM landing_content_audit 
        WHERE municipality_id=1 
        ORDER BY audit_id DESC 
        LIMIT 10";
$res = @pg_query($connection, $sql);

if ($res) {
    echo "<p>✅ Query successful! Found " . pg_num_rows($res) . " records</p>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Audit ID</th><th>Block Key</th><th>Action</th><th>Created At</th></tr>";
    while ($row = pg_fetch_assoc($res)) {
        echo "<tr>";
        echo "<td>{$row['audit_id']}</td>";
        echo "<td>{$row['block_key']}</td>";
        echo "<td>{$row['action_type']}</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Query failed: " . pg_last_error($connection) . "</p>";
}
?>
