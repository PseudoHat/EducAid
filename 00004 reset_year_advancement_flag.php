<?php
/**
 * Reset Year Advancement Flag
 * Allows year advancement to run again for testing
 */

include 'config/database.php';

echo "=== Resetting Year Advancement Flag ===\n\n";

$query = "
UPDATE academic_years 
SET year_levels_advanced = FALSE,
    advanced_at = NULL,
    advanced_by = NULL
WHERE is_current = TRUE
RETURNING year_code, year_levels_advanced;
";

$result = pg_query($connection, $query);

if ($result) {
    $row = pg_fetch_assoc($result);
    echo "✓ Successfully reset year advancement flag\n\n";
    echo "Academic Year: " . $row['year_code'] . "\n";
    echo "Year Levels Advanced: " . ($row['year_levels_advanced'] === 't' ? 'TRUE' : 'FALSE') . "\n\n";
    echo "You can now run year advancement again!\n";
} else {
    echo "✗ Error: " . pg_last_error($connection) . "\n";
}

pg_close($connection);
?>
