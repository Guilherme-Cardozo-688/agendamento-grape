<?php
echo "PHP Version: " . phpversion() . "\n\n";
echo "Extensions loaded:\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    echo "- $ext\n";
}

echo "\n\n=== SQLite Check ===\n";
if (extension_loaded('pdo_sqlite')) {
    echo "✓ PDO SQLite: ENABLED\n";
} else {
    echo "✗ PDO SQLite: DISABLED\n";
}

if (extension_loaded('sqlite3')) {
    echo "✓ SQLite3: ENABLED\n";
} else {
    echo "✗ SQLite3: DISABLED\n";
}

if (class_exists('PDO')) {
    echo "✓ PDO: ENABLED\n";
    $drivers = PDO::getAvailableDrivers();
    echo "Available PDO drivers: " . implode(', ', $drivers) . "\n";
} else {
    echo "✗ PDO: DISABLED\n";
}
?>

