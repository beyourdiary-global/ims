<?php
// 1. Database Credentials
$dbhost     = '127.0.0.1';
$dbport     = 3306;
$dbUser     = 'beyourdi_cms'; // Change to 'root' if testing on local WAMP/XAMPP
$dbpwd      = 'Byd1234@Global'; // Change to '' if testing on local WAMP/XAMPP without password

$db_cms     = 'beyourdi_cms-uat';
$db_fin     = 'beyourdi_financial-uat';

// Check lock file to prevent running multiple times
$lock_file = __DIR__ . '/db_imported.lock';
if (file_exists($lock_file)) {
    die("Database setup already completed. To run again, please delete the 'db_imported.lock' file first.");
}

// 2. Connect to MySQL Server (Create DB if not exists)
$conn = new mysqli($dbhost, $dbUser, $dbpwd, "", $dbport);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to read and execute SQL file
function executeSQLFile($conn, $filePath) {
    if (!file_exists($filePath)) {
        echo "<p style='color:red;'>File not found: " . basename($filePath) . "</p>";
        return false;
    }
    
    // Read the file
    $sql = file_get_contents($filePath);
    if (empty(trim($sql))) return true;

    // Execute multi query
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
    }
    
    if ($conn->errno) {
        echo "<p style='color:red;'>Error importing " . basename($filePath) . ": " . $conn->error . "</p>";
        return false;
    }
    
    echo "<p style='color:green;'>Successfully imported: " . basename($filePath) . "</p>";
    return true;
}

echo "<h1>Database Setup script</h1>";

// 3. Create databases if they don't exist
$conn->query("CREATE DATABASE IF NOT EXISTS `$db_cms`");
$conn->query("CREATE DATABASE IF NOT EXISTS `$db_fin`");

// 4. Import CMS Database (Schema then Data)
echo "<h2>Importing CMS Database ($db_cms)...</h2>";
$conn->select_db($db_cms);
executeSQLFile($conn, __DIR__ . "/beyourdi_cms-uat.sql");
executeSQLFile($conn, __DIR__ . "/insert_beyourdi_cms-uat.sql");

// 5. Import Financial Database (Schema then Data)
echo "<h2>Importing Financial Database ($db_fin)...</h2>";
$conn->select_db($db_fin);
executeSQLFile($conn, __DIR__ . "/beyourdi_financial-uat.sql");
executeSQLFile($conn, __DIR__ . "/insert_beyourdi_financial-uat.sql");

// 6. Create a lock file to ensure it only runs once
file_put_contents($lock_file, date('Y-m-d H:i:s'));

echo "<hr><h2>All databases created and data inserted successfully!</h2>";
echo "<p>You can now log in using the credentials we set up.</p>";
$conn->close();
?>