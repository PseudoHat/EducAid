<?php
include __DIR__ . '/config/database.php';

$query = "SELECT column_name, data_type 
          FROM information_schema.columns 
          WHERE table_name = 'students' 
          AND column_name LIKE '%date%' OR column_name LIKE '%birth%'
          ORDER BY ordinal_position";

$result = pg_query($connection, $query);
echo "<pre>";
echo "Students table columns related to birth/date:\n\n";
while ($row = pg_fetch_assoc($result)) {
    echo $row['column_name'] . " - " . $row['data_type'] . "\n";
}
echo "</pre>";

// Also check year_levels
echo "<hr><pre>";
echo "Year Levels:\n\n";
$ylQuery = "SELECT year_level_id, name FROM year_levels ORDER BY sort_order";
$ylResult = pg_query($connection, $ylQuery);
while ($row = pg_fetch_assoc($ylResult)) {
    echo $row['year_level_id'] . " - " . $row['name'] . "\n";
}
echo "</pre>";
?>
