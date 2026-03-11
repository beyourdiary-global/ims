<?php
// 1. Database Credentials
$dbhost     = '127.0.0.1';
$dbport     = 3306;
$dbUser     = 'beyourdi_cms';
$dbpwd      = 'Byd1234@Global';

$db_cms     = 'beyourdi_cms-uat';
$db_fin     = 'beyourdi_financial-uat';

// 2. Connect to MySQL Server (Create DB if not exists)
$conn = new mysqli($dbhost, $dbUser, $dbpwd, "", $dbport);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2.1 Update table structure for Package (Add Item Code and Description)
if (!$conn->select_db($db_fin)) {
    die('Unable to select database `' . $db_fin . '`: ' . $conn->error);
}

function columnExists($conn, $dbName, $tableName, $columnName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn' LIMIT 1";
    $rst = $conn->query($sql);
    return ($rst && $rst->num_rows > 0);
}

function addColumnIfMissing($conn, $dbName, $tableName, $columnName, $alterSql)
{
    if (!columnExists($conn, $dbName, $tableName, $columnName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Added column `$columnName` to `$tableName`.</p>";
        } else {
            echo "<p style='color:red;'>Failed adding `$columnName` to `$tableName`: " . $conn->error . "</p>";
        }
    }
}

function dropColumnIfExists($conn, $dbName, $tableName, $columnName, $alterSql)
{
    if (columnExists($conn, $dbName, $tableName, $columnName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Dropped column `$columnName` from `$tableName`.</p>";
        } else {
            echo "<p style='color:red;'>Failed dropping `$columnName` from `$tableName`: " . $conn->error . "</p>";
        }
    }
}

function indexExists($conn, $dbName, $tableName, $indexName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeIndex = $conn->real_escape_string($indexName);
    $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema='$safeDb' AND table_name='$safeTable' AND index_name='$safeIndex' LIMIT 1";
    $rst = $conn->query($sql);
    return ($rst && $rst->num_rows > 0);
}

function dropIndexIfExists($conn, $dbName, $tableName, $indexName, $alterSql)
{
    if (indexExists($conn, $dbName, $tableName, $indexName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Dropped index `$indexName` from `$tableName`.</p>";
        } else {
            echo "<p style='color:red;'>Failed dropping index `$indexName` from `$tableName`: " . $conn->error . "</p>";
        }
    }
}

$createStockOrderRequestTableSql = "CREATE TABLE IF NOT EXISTS `stock_order_request` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT NOT NULL,
    `invoice_no` TEXT DEFAULT NULL,
    `invoice_date` DATE DEFAULT NULL,
    `request_date` DATE NOT NULL,
    `courier_id` INT DEFAULT NULL,
    `tracking_no` VARCHAR(120) DEFAULT NULL,
    `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `brand_id` INT DEFAULT NULL,
    `company_id` INT DEFAULT NULL,
    `tracking_status` TEXT DEFAULT NULL,
    `tracking_last_sync` DATETIME DEFAULT NULL,
    `attachment` VARCHAR(255) DEFAULT NULL,
    `order_link_token` VARCHAR(255) DEFAULT NULL,
    `qr_image` VARCHAR(255) DEFAULT NULL,
    `remark` TEXT DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createStockOrderRequestTableSql)) {
    echo "<p style='color:blue;'>Table `stock_order_request` is ready.</p>";
} else {
    echo "<p style='color:red;'>Error creating `stock_order_request`: " . $conn->error . "</p>";
}

$createStockOrderRequestItemTableSql = "CREATE TABLE IF NOT EXISTS `stock_order_request_item` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `product_id` INT DEFAULT NULL,
    `package_id` INT NOT NULL,
    `package_desc` TEXT DEFAULT NULL,
    `qty` INT NOT NULL DEFAULT 1,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    KEY `idx_sor_item_request_id` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createStockOrderRequestItemTableSql)) {
    echo "<p style='color:blue;'>Table `stock_order_request_item` is ready.</p>";
} else {
    echo "<p style='color:red;'>Error creating `stock_order_request_item`: " . $conn->error . "</p>";
}

// Backward-compatible ALTERs.
addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'invoice_no', "ALTER TABLE `stock_order_request` ADD COLUMN `invoice_no` TEXT DEFAULT NULL AFTER `warehouse_id`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'invoice_date', "ALTER TABLE `stock_order_request` ADD COLUMN `invoice_date` DATE DEFAULT NULL AFTER `invoice_no`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'total_price', "ALTER TABLE `stock_order_request` ADD COLUMN `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `tracking_no`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'brand_id', "ALTER TABLE `stock_order_request` ADD COLUMN `brand_id` INT DEFAULT NULL AFTER `total_price`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'company_id', "ALTER TABLE `stock_order_request` ADD COLUMN `company_id` INT DEFAULT NULL AFTER `brand_id`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'product_id', "ALTER TABLE `stock_order_request_item` ADD COLUMN `product_id` INT DEFAULT NULL AFTER `request_id`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'brand_id', "ALTER TABLE `stock_order_request_item` ADD COLUMN `brand_id` INT DEFAULT NULL AFTER `product_id`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'company_id', "ALTER TABLE `stock_order_request_item` ADD COLUMN `company_id` INT DEFAULT NULL AFTER `brand_id`");

dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_no', "ALTER TABLE `stock_order_request` DROP COLUMN `request_no`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_by', "ALTER TABLE `stock_order_request` DROP COLUMN `request_by`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_no', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_no`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_by', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_by`");
dropIndexIfExists($conn, $db_fin, 'stock_order_request', 'uq_sor_request_no', "ALTER TABLE `stock_order_request` DROP INDEX `uq_sor_request_no`");

if ($conn->select_db($db_cms)) {
    $createCompanyTableSql = "CREATE TABLE IF NOT EXISTS `company` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(100) NOT NULL,
    `reg_no` VARCHAR(120) NOT NULL,
    `remark` TEXT DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCompanyTableSql)) {
        echo "<p style='color:blue;'>Table `company` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Error creating `company`: " . $conn->error . "</p>";
    }

    addColumnIfMissing($conn, $db_cms, 'brand', 'company', "ALTER TABLE `brand` ADD COLUMN `company` INT DEFAULT NULL AFTER `name`");

    // 1. Insert new Pin Groups (125, 126 & 127)
    $sqlInsertPins = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (125, 'Stock In', '1,2,3,4,5,6', 'Stock In Management', '1', CURDATE(), CURTIME(), 'A'),
    (126, 'Stock Order Request', '1,2,3,4,5', 'Stock Order Request Management', '1', CURDATE(), CURTIME(), 'A'),
    (127, 'Company', '1,2,3,4,5,6', 'Company Management', '1', CURDATE(), CURTIME(), 'A')";
    
    if ($conn->query($sqlInsertPins)) {
        echo "<p style='color:blue;'>Pin groups 125, 126 & 127 ensured in CMS database.</p>";
    }

    $sqlUpdateCompanyPin = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[127:1,2,3,4,5,6]')
    WHERE `id` = 1 AND `pins` NOT LIKE '%[127:%'";
    $conn->query($sqlUpdateCompanyPin);

    // 2. Update Super Admin (id=1) safely
    $sqlUpdateAdmin1 = "UPDATE `user_group` 
    SET `pins` = CONCAT(`pins`, '+[125:1,2,3,4,5,6]+[126:1,2,3,4,5]+[127:1,2,3,4,5,6]') 
    WHERE `id` = 1 AND `pins` NOT LIKE '%[125:%'";
    $conn->query($sqlUpdateAdmin1);

    // 3. Sync Admin Group (id=2) with Super Admin
    $sqlUpdateAdmin2 = "UPDATE `user_group` 
    SET `pins` = (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t) 
    WHERE `id` = 2";
    $conn->query($sqlUpdateAdmin2);
} else {
    echo "<p style='color:red;'>Failed to select CMS database to update pin groups.</p>";
}
echo "<h3>Stock Order Request financial schema setup complete.</h3>";

$conn->close();
