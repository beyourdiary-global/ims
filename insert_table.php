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

$createStockOrderRequestTableSql = "CREATE TABLE IF NOT EXISTS `stock_order_request` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_no` VARCHAR(80) NOT NULL,
    `warehouse_id` INT NOT NULL,
    `invoice_no` TEXT DEFAULT NULL,
    `invoice_date` DATE DEFAULT NULL,
    `request_date` DATE NOT NULL,
    `request_by` INT DEFAULT NULL,
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
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    UNIQUE KEY `uq_sor_request_no` (`request_no`)
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

echo "<h3>Stock Order Request financial schema setup complete.</h3>";

$conn->close();
