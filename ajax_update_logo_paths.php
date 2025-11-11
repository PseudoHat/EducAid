<?php
/**
 * AJAX: Update Database Logo Paths to Use Volume
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/permissions.php';

header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminRole = getCurrentAdminRole($connection);
if ($adminRole !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Super Admin only']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$volumePath = $input['volumePath'] ?? null;

if (!$volumePath || !is_dir($volumePath)) {
    echo json_encode(['success' => false, 'message' => 'Invalid volume path']);
    exit;
}

// Get all files in the volume directory
$files = array_filter(scandir($volumePath), function($file) {
    return preg_match('/\.(png|jpg|jpeg|gif|webp|svg)$/i', $file);
});

if (empty($files)) {
    echo json_encode(['success' => false, 'message' => 'No logo files found in volume']);
    exit;
}

// Logo name mapping
$logoMap = [
    'General_Trias_City_Logo.png' => 1,
    'Dasma_City_Logo.png' => 2,
    'Imus_City_Logo.png' => 3,
    'Bacoor_City_Logo.png' => 4,
    'Cavite_City_Logo.png' => 5,
    'Trece_Martires_City_Logo.png' => 6,
    'Tagaytay_City_Logo.png' => 7,
    'Carmona_Cavite_Logo.png' => 8,
    'Kawit_Logo.png' => 101,
    'Noveleta_Logo.png' => 102,
    'Rosario_Logo.jpg' => 103,
    'General_Mariano_Alvarez_Logo.png' => 104,
    'Silang_City_Logo.png' => 105,
    'Amadeo_Logo.png' => 106,
    'Indang_Logo.png' => 107,
    'Tanza,_Cavite_Logo.png' => 108,
    'Alfonso_Logo.png' => 109,
    'Gen_Emilio_Aguinaldo_Logo.png' => 110,
    'Magallanes_Logo.png' => 111,
    'Maragondon_Logo.png' => 112,
    'Mendez_Logo.png' => 113,
    'Naic_Logo.png' => 114,
    'Ternate_Logo.png' => 115,
];

pg_query($connection, "BEGIN");

$updated = 0;
$errors = [];

foreach ($files as $file) {
    if (!isset($logoMap[$file])) {
        $errors[] = "Unknown logo file: $file";
        continue;
    }
    
    $municipalityId = $logoMap[$file];
    $webPath = '/mnt/assets/City Logos/' . $file;
    
    $updateQuery = "UPDATE municipalities SET preset_logo_image = $1 WHERE municipality_id = $2";
    $result = pg_query_params($connection, $updateQuery, [$webPath, $municipalityId]);
    
    if ($result) {
        $updated++;
    } else {
        $errors[] = "Failed to update $file: " . pg_last_error($connection);
    }
}

if (empty($errors)) {
    pg_query($connection, "COMMIT");
    echo json_encode([
        'success' => true,
        'message' => "Successfully updated $updated municipalities",
        'updated' => $updated
    ]);
} else {
    pg_query($connection, "ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => 'Some updates failed: ' . implode(', ', $errors),
        'errors' => $errors
    ]);
}
?>
