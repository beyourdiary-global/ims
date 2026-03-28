<?php
// Include init.php to access all dynamic global configurations
include_once 'init.php'; 

// --- SECURITY CHECK: Ensure only logged-in users can run this script ---
if (!isset($_SESSION['userid']) || empty($_SESSION['userid'])) {
    die("<h3 style='color:red;'> Unauthorized access! You must be logged in to run this script.</h3>");
}

// 1. Database Credentials (loaded from init.php)
$dbhost     = dbhost;
$dbUser     = dbuser;
$dbpwd      = dbpwd;
$db_cms     = dbname;
$db_fin     = dbFinance;

// Separate the port from the host if it exists (e.g. "127.0.0.1:3306")
$dbport = 3306;
if (strpos($dbhost, ':') !== false) {
    list($dbhost, $dbport) = explode(':', $dbhost);
}

// 2. Connect to MySQL Server (Create DB if not exists)
$conn = new mysqli($dbhost, $dbUser, $dbpwd, "", $dbport);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2.1 Select Financial Database
if (!$conn->select_db($db_fin)) {
    die('Unable to select database `' . $db_fin . '`: ' . $conn->error);
}

// ==========================================
// HELPER FUNCTIONS (FIXED)
// ==========================================

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
    } else {
        echo "<p style='color:green;'>Verified column `$columnName` already exists in `$tableName`.</p>";
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
    } else {
        echo "<p style='color:green;'>Verified column `$columnName` is already removed from `$tableName`.</p>";
    }
}

function alterColumnToVarcharIfInt($conn, $dbName, $tableName, $columnName, $varcharLen = 255)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    
    // Explicitly select DATA_TYPE
    $sql = "SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn' LIMIT 1";
    $rst = $conn->query($sql);

    if (!$rst || $rst->num_rows === 0) {
        echo "<p style='color:orange;'>Column `$columnName` not found in `$tableName` to alter.</p>";
        return;
    }

    $row = $rst->fetch_assoc();
    if ($row) {
        $row = array_change_key_case($row, CASE_LOWER);
    }
    
    if (isset($row['data_type'])) {
        $dataType = strtolower((string) $row['data_type']);
        if (strpos($dataType, 'int') !== false) {
            $alterSql = "ALTER TABLE `$tableName` MODIFY COLUMN `$columnName` VARCHAR(" . (int) $varcharLen . ") NULL";
            if ($conn->query($alterSql)) {
                echo "<p style='color:blue;'>Updated `$tableName`.`$columnName` to VARCHAR(" . (int) $varcharLen . ").</p>";
            } else {
                echo "<p style='color:red;'>Failed updating `$tableName`.`$columnName`: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified `$tableName`.`$columnName` is already non-integer ($dataType).</p>";
        }
    }
}

function alterColumnToTextIfVarchar($conn, $dbName, $tableName, $columnName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn' LIMIT 1";
    $rst = $conn->query($sql);

    if (!$rst || $rst->num_rows === 0) {
        echo "<p style='color:orange;'>Column `$columnName` not found in `$tableName` to alter.</p>";
        return;
    }

    $row = $rst->fetch_assoc();
    if ($row) {
        $row = array_change_key_case($row, CASE_LOWER);
    }

    if (isset($row['data_type'])) {
        $dataType = strtolower((string) $row['data_type']);
        if ($dataType === 'varchar') {
            $alterSql = "ALTER TABLE `$tableName` MODIFY COLUMN `$columnName` TEXT NULL";
            if ($conn->query($alterSql)) {
                echo "<p style='color:blue;'>Updated `$tableName`.`$columnName` to TEXT.</p>";
            } else {
                echo "<p style='color:red;'>Failed updating `$tableName`.`$columnName` to TEXT: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified `$tableName`.`$columnName` is already non-varchar ($dataType).</p>";
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
    } else {
        echo "<p style='color:green;'>Verified index `$indexName` is already removed from `$tableName`.</p>";
    }
}

function normalizeShopeePins($pinStr)
{
    $cleanPins = preg_replace('/\+?\[(128|129|130):[^\]]*\]/', '', (string) $pinStr);
    $cleanPins = preg_replace('/\+{2,}/', '+', $cleanPins);
    return trim($cleanPins, '+');
}

function ensureSingleShopeePinForGroup($conn, $groupId, $shopeePinBlock)
{
    $groupId = (int) $groupId;
    $query = "SELECT `pins` FROM `user_group` WHERE `id` = {$groupId} LIMIT 1";
    $result = $conn->query($query);
    if (!$result || $result->num_rows === 0) {
        return;
    }

    $row = $result->fetch_assoc();
    $basePins = normalizeShopeePins($row['pins']);
    $updatedPins = $basePins === '' ? $shopeePinBlock : ($basePins . '+' . $shopeePinBlock);
    $safePins = $conn->real_escape_string($updatedPins);
    if ($conn->query("UPDATE `user_group` SET `pins` = '{$safePins}' WHERE `id` = {$groupId}")) {
        echo "<p style='color:green;'>Verified Shopee Pins for Group ID: {$groupId}.</p>";
    }
}

function updatePinBlockForGroup($conn, $groupId, $pinId, $pinAccessList)
{
    $groupId = (int) $groupId;
    $pinId = (int) $pinId;
    $pinBlock = '[' . $pinId . ':' . $pinAccessList . ']';

    $query = "SELECT `pins` FROM `user_group` WHERE `id` = {$groupId} LIMIT 1";
    $result = $conn->query($query);
    if (!$result || $result->num_rows === 0) {
        echo "<p style='color:red;'>Failed updating Pin {$pinId}: user group {$groupId} not found.</p>";
        return;
    }

    $row = $result->fetch_assoc();
    $currentPins = isset($row['pins']) ? (string) $row['pins'] : '';
    $cleanPins = preg_replace('/\+?\[' . $pinId . ':[^\]]*\]/', '', $currentPins);
    $cleanPins = preg_replace('/\+{2,}/', '+', (string) $cleanPins);
    $cleanPins = trim((string) $cleanPins, '+');

    $updatedPins = $cleanPins === '' ? $pinBlock : ($cleanPins . '+' . $pinBlock);
    $safePins = $conn->real_escape_string($updatedPins);

    if ($conn->query("UPDATE `user_group` SET `pins` = '{$safePins}' WHERE `id` = {$groupId}")) {
        echo "<p style='color:green;'>Verified Pin {$pinId} access ({$pinAccessList}) for Group ID: {$groupId}.</p>";
    } else {
        echo "<p style='color:red;'>Failed updating Pin {$pinId} for Group ID {$groupId}: " . $conn->error . "</p>";
    }
}

// ==========================================
// STOCK ORDER REQUEST TABLE CREATION
// ==========================================

$createStockOrderRequestTableSql = "CREATE TABLE IF NOT EXISTS `stock_order_request` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT NOT NULL,
    `invoice_no` TEXT DEFAULT NULL,
    `invoice_date` DATE DEFAULT NULL,
    `request_date` DATE NOT NULL,
    `courier_id` VARCHAR(100) DEFAULT NULL,
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
    `package_group_key` VARCHAR(120) DEFAULT NULL,
    `package_desc` TEXT DEFAULT NULL,
    `package_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `packageQty` INT NOT NULL DEFAULT 1,
    `productQty` INT NOT NULL DEFAULT 1,
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

$createStockInOrderTableSql = "CREATE TABLE IF NOT EXISTS `stock_in_order` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_id` INT NOT NULL,
    `order_number` VARCHAR(120) NOT NULL,
    `stock_in_date` DATE NOT NULL,
    `attachment` TEXT DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    KEY `idx_order_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createStockInOrderTableSql)) {
    echo "<p style='color:blue;'>Table `stock_in_order` is ready.</p>";
} else {
    echo "<p style='color:red;'>Error creating `stock_in_order`: " . $conn->error . "</p>";
}

$createStockInItemTableSql = "CREATE TABLE IF NOT EXISTS `stock_in_order_item` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stock_in_order_id` INT NOT NULL,
    `product_id` VARCHAR(100) DEFAULT NULL,
    `package_id` INT NOT NULL DEFAULT 0,
    `product_quantity` VARCHAR(255) NOT NULL DEFAULT '1',
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    KEY `idx_stock_in_order_id` (`stock_in_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createStockInItemTableSql)) {
    echo "<p style='color:blue;'>Table `stock_in_order_item` is ready.</p>";
} else {
    echo "<p style='color:red;'>Error creating `stock_in_order_item`: " . $conn->error . "</p>";
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
addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'package_group_key', "ALTER TABLE `stock_order_request_item` ADD COLUMN `package_group_key` VARCHAR(120) DEFAULT NULL AFTER `package_id`");
addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'package_price', "ALTER TABLE `stock_order_request_item` ADD COLUMN `package_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `package_desc`");

if (!columnExists($conn, $db_fin, 'stock_order_request_item', 'packageQty')) {
    if (columnExists($conn, $db_fin, 'stock_order_request_item', 'qty')) {
        if ($conn->query("ALTER TABLE `stock_order_request_item` CHANGE COLUMN `qty` `packageQty` INT NOT NULL DEFAULT 1")) {
            echo "<p style='color:blue;'>Changed `qty` to `packageQty` in `stock_order_request_item`.</p>";
        }
    } else {
        addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'packageQty', "ALTER TABLE `stock_order_request_item` ADD COLUMN `packageQty` INT NOT NULL DEFAULT 1 AFTER `package_desc`");
    }
} else {
    echo "<p style='color:green;'>Verified column `packageQty` already exists in `stock_order_request_item`.</p>";
}

addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'productQty', "ALTER TABLE `stock_order_request_item` ADD COLUMN `productQty` INT NOT NULL DEFAULT 1 AFTER `packageQty`");

if ($conn->query("UPDATE `stock_order_request_item` SET `productQty`=`packageQty` WHERE IFNULL(`productQty`,0)<=0")) {
    echo "<p style='color:green;'>Verified `productQty` equals `packageQty` where missing.</p>";
}

dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_no', "ALTER TABLE `stock_order_request` DROP COLUMN `request_no`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_by', "ALTER TABLE `stock_order_request` DROP COLUMN `request_by`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_no', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_no`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_by', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_by`");
dropIndexIfExists($conn, $db_fin, 'stock_order_request', 'uq_sor_request_no', "ALTER TABLE `stock_order_request` DROP INDEX `uq_sor_request_no`");

dropColumnIfExists($conn, $db_fin, 'stock_in_order', 'stock_order_request_id', "ALTER TABLE `stock_in_order` DROP COLUMN `stock_order_request_id`");
dropIndexIfExists($conn, $db_fin, 'stock_in_order', 'uq_stock_order_request_id', "ALTER TABLE `stock_in_order` DROP INDEX `uq_stock_order_request_id`");

// Ensure courier_id type is consistent with CMS courier.id (varchar).
alterColumnToVarcharIfInt($conn, $db_fin, 'stock_order_request', 'courier_id', 100);

// Ensure Shopee Order Request supports storing multiple package/brand IDs as CSV.
alterColumnToVarcharIfInt($conn, $db_fin, 'shopee_sg_order_request', 'package', 255);
alterColumnToVarcharIfInt($conn, $db_fin, 'shopee_sg_order_request', 'brand', 255);
addColumnIfMissing($conn, $db_fin, 'shopee_customer_info', 'contact_no', "ALTER TABLE `shopee_customer_info` ADD COLUMN `contact_no` VARCHAR(30) DEFAULT NULL AFTER `series`");
addColumnIfMissing($conn, $db_fin, 'shopee_ads_topup_transaction', 'attachment', "ALTER TABLE `shopee_ads_topup_transaction` ADD COLUMN `attachment` VARCHAR(255) DEFAULT NULL AFTER `pay_meth`");

// Ensure Stock In item supports CSV products and quantities.
alterColumnToVarcharIfInt($conn, $db_fin, 'stock_in_order_item', 'product_id', 100);
alterColumnToVarcharIfInt($conn, $db_fin, 'stock_in_order_item', 'product_quantity', 255);
addColumnIfMissing($conn, $db_fin, 'stock_in_order', 'attachment', "ALTER TABLE `stock_in_order` ADD COLUMN `attachment` TEXT DEFAULT NULL AFTER `stock_in_date`");
alterColumnToTextIfVarchar($conn, $db_fin, 'stock_in_order', 'attachment');

addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'currency', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `currency` VARCHAR(10) DEFAULT NULL AFTER `date`");
addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'total_gst', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `total_gst` DECIMAL(10,2) DEFAULT '0.00' AFTER `currency`");
addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'total_amount', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `total_amount` DECIMAL(10,2) DEFAULT '0.00' AFTER `total_gst`");

if ($conn->query("ALTER TABLE `jt_transaction_backup` ENGINE=InnoDB")) {
    echo "<p style='color:green;'>Verified `jt_transaction_backup` ENGINE=InnoDB.</p>";
}

$createJtTransactionItemsTableSql = "CREATE TABLE IF NOT EXISTS `jt_transaction_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `service_type` VARCHAR(255) DEFAULT NULL,
    `shipments_count` INT DEFAULT '0',
    `total_weight_kg` DECIMAL(10,2) DEFAULT '0.00',
    `standard_charge` DECIMAL(10,2) DEFAULT '0.00',
    `extra_charges` DECIMAL(10,2) DEFAULT '0.00',
    `nett_charge` DECIMAL(10,2) DEFAULT '0.00',
    INDEX `idx_jt_transaction_items_transaction_id` (`transaction_id`),
    CONSTRAINT `fk_jt_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `jt_transaction_backup`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createJtTransactionItemsTableSql)) {
    echo "<p style='color:blue;'>Table `jt_transaction_items` is ready.</p>";
} else {
    echo "<p style='color:red;'>Error creating `jt_transaction_items`: " . $conn->error . "</p>";
}

$createJtTransactionExtraChargesTableSql = "CREATE TABLE IF NOT EXISTS `jt_transaction_extra_charges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `type` VARCHAR(50) DEFAULT NULL,
    `rate` DECIMAL(5,2) DEFAULT '0.00',
    `amount` DECIMAL(10,2) DEFAULT '0.00',
    `gst_paid` DECIMAL(10,2) DEFAULT '0.00',
    INDEX `idx_jt_transaction_extra_charges_transaction_id` (`transaction_id`),
    CONSTRAINT `fk_jt_extra_charges_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `jt_transaction_backup`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createJtTransactionExtraChargesTableSql)) {
    echo "<p style='color:blue;'>Table `jt_transaction_extra_charges` is ready.</p>";
} else {
    echo "<p style='color:red;'>Error creating `jt_transaction_extra_charges`: " . $conn->error . "</p>";
}

// ==========================================
// PIN GROUPS & CMS DATABASE UPDATE
// ==========================================

if ($conn->select_db($db_cms)) {
    $createCompanyTableSql = "CREATE TABLE IF NOT EXISTS `company` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(100) NOT NULL,
    `id_no` VARCHAR(20) NOT NULL,
    `address1` VARCHAR(60) NOT NULL,
    `address2` VARCHAR(60) NOT NULL,
    `address3` VARCHAR(60) NOT NULL,
    `address4` VARCHAR(60) NOT NULL,
    `postcode` VARCHAR(10) NOT NULL,
    `city` VARCHAR(50) NOT NULL,
    `state` VARCHAR(50) NOT NULL,
    `country` CHAR(2) NOT NULL,
    `phone1` VARCHAR(200) NOT NULL,
    `sales_tax_no` VARCHAR(25) DEFAULT NULL,
    `service_tax_no` VARCHAR(25) DEFAULT NULL,
    `tin` VARCHAR(14) NOT NULL,
    `id_type` TINYINT NOT NULL DEFAULT 0,
    `tourism_no` VARCHAR(17) DEFAULT NULL,
    `sic` VARCHAR(10) DEFAULT NULL,
    `income` VARCHAR(3) DEFAULT NULL,
    `submission_type` VARCHAR(100) NOT NULL,
    `irbm_classification` VARCHAR(3) NOT NULL,
    `tax_exemption_reason` VARCHAR(300) DEFAULT NULL,
    `sql_account_id` INT NOT NULL DEFAULT 0,
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

    $createSqlAccountTableSql = "CREATE TABLE IF NOT EXISTS `sql_account` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createSqlAccountTableSql)) {
        echo "<p style='color:blue;'>Table `sql_account` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Error creating `sql_account`: " . $conn->error . "</p>";
    }

    $createPurchaseOrderTableSql = "CREATE TABLE IF NOT EXISTS `purchase_order` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doc_date` DATE DEFAULT NULL,
    `doc_no` VARCHAR(20) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `company_name` VARCHAR(100) NOT NULL,
    `description_hdr` VARCHAR(200) DEFAULT NULL,
    `seq` INT NOT NULL DEFAULT 1,
    `account` VARCHAR(10) DEFAULT NULL,
    `item_code` VARCHAR(30) NOT NULL,
    `description_dtl` VARCHAR(200) DEFAULT NULL,
    `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `uom` VARCHAR(10) DEFAULT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `sql_account_id` INT NOT NULL DEFAULT 0,
    `remark` TEXT DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    KEY `idx_po_doc_no` (`doc_no`),
    KEY `idx_po_company_name` (`company_name`),
    KEY `idx_po_sql_account_id` (`sql_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createPurchaseOrderTableSql)) {
        echo "<p style='color:blue;'>Table `purchase_order` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Error creating `purchase_order`: " . $conn->error . "</p>";
    }

    $createTokenSettingTableSql = "CREATE TABLE IF NOT EXISTS `token_setting` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `bot_token` VARCHAR(255) NOT NULL,
    `remark` TEXT DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTokenSettingTableSql)) {
        echo "<p style='color:blue;'>Table `token_setting` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Error creating `token_setting`: " . $conn->error . "</p>";
    }

    $createUserRecordLogTableSql = "CREATE TABLE IF NOT EXISTS `user_record_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `content` TEXT NOT NULL,
    `attachment` VARCHAR(255) DEFAULT NULL,
    `created_by` VARCHAR(30) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by` VARCHAR(30) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    KEY `idx_url_created_at` (`created_at`),
    KEY `idx_url_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createUserRecordLogTableSql)) {
        echo "<p style='color:blue;'>Table `user_record_log` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Error creating `user_record_log`: " . $conn->error . "</p>";
    }

    dropColumnIfExists($conn, $db_cms, 'token_setting', 'chat_id', "ALTER TABLE `token_setting` DROP COLUMN `chat_id`");

    addColumnIfMissing($conn, $db_cms, 'user', 'main_report_supervisor', "ALTER TABLE `user` ADD COLUMN `main_report_supervisor` INT DEFAULT NULL AFTER `access_id`");
    addColumnIfMissing($conn, $db_cms, 'user', 'second_report_supervisor', "ALTER TABLE `user` ADD COLUMN `second_report_supervisor` INT DEFAULT NULL AFTER `main_report_supervisor`");

    addColumnIfMissing($conn, $db_cms, 'sql_account', 'status', "ALTER TABLE `sql_account` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `name`");

    if (!columnExists($conn, $db_cms, 'company', 'id_no')) {
        if (columnExists($conn, $db_cms, 'company', 'reg_no')) {
            if ($conn->query("ALTER TABLE `company` CHANGE COLUMN `reg_no` `id_no` VARCHAR(20) NOT NULL DEFAULT ''")) {
                echo "<p style='color:blue;'>Changed `reg_no` to `id_no` in `company` table.</p>";
            }
        } else {
            addColumnIfMissing($conn, $db_cms, 'company', 'id_no', "ALTER TABLE `company` ADD COLUMN `id_no` VARCHAR(20) NOT NULL DEFAULT '' AFTER `code`");
        }
    } else {
        echo "<p style='color:green;'>Verified column `id_no` already exists in `company` table.</p>";
    }

    addColumnIfMissing($conn, $db_cms, 'company', 'address1', "ALTER TABLE `company` ADD COLUMN `address1` VARCHAR(60) NOT NULL DEFAULT '' AFTER `id_no`");
    addColumnIfMissing($conn, $db_cms, 'company', 'address2', "ALTER TABLE `company` ADD COLUMN `address2` VARCHAR(60) NOT NULL DEFAULT '' AFTER `address1`");
    addColumnIfMissing($conn, $db_cms, 'company', 'address3', "ALTER TABLE `company` ADD COLUMN `address3` VARCHAR(60) NOT NULL DEFAULT '' AFTER `address2`");
    addColumnIfMissing($conn, $db_cms, 'company', 'address4', "ALTER TABLE `company` ADD COLUMN `address4` VARCHAR(60) NOT NULL DEFAULT '' AFTER `address3`");
    addColumnIfMissing($conn, $db_cms, 'company', 'postcode', "ALTER TABLE `company` ADD COLUMN `postcode` VARCHAR(10) NOT NULL DEFAULT '' AFTER `address4`");
    addColumnIfMissing($conn, $db_cms, 'company', 'city', "ALTER TABLE `company` ADD COLUMN `city` VARCHAR(50) NOT NULL DEFAULT '' AFTER `postcode`");
    addColumnIfMissing($conn, $db_cms, 'company', 'state', "ALTER TABLE `company` ADD COLUMN `state` VARCHAR(50) NOT NULL DEFAULT '' AFTER `city`");
    addColumnIfMissing($conn, $db_cms, 'company', 'country', "ALTER TABLE `company` ADD COLUMN `country` CHAR(2) NOT NULL DEFAULT '' AFTER `state`");
    addColumnIfMissing($conn, $db_cms, 'company', 'phone1', "ALTER TABLE `company` ADD COLUMN `phone1` VARCHAR(200) NOT NULL DEFAULT '' AFTER `country`");
    addColumnIfMissing($conn, $db_cms, 'company', 'sales_tax_no', "ALTER TABLE `company` ADD COLUMN `sales_tax_no` VARCHAR(25) DEFAULT NULL AFTER `phone1`");
    addColumnIfMissing($conn, $db_cms, 'company', 'service_tax_no', "ALTER TABLE `company` ADD COLUMN `service_tax_no` VARCHAR(25) DEFAULT NULL AFTER `sales_tax_no`");
    addColumnIfMissing($conn, $db_cms, 'company', 'tin', "ALTER TABLE `company` ADD COLUMN `tin` VARCHAR(14) NOT NULL DEFAULT '' AFTER `service_tax_no`");
    addColumnIfMissing($conn, $db_cms, 'company', 'id_type', "ALTER TABLE `company` ADD COLUMN `id_type` TINYINT NOT NULL DEFAULT 0 AFTER `tin`");
    addColumnIfMissing($conn, $db_cms, 'company', 'tourism_no', "ALTER TABLE `company` ADD COLUMN `tourism_no` VARCHAR(17) DEFAULT NULL AFTER `id_type`");
    addColumnIfMissing($conn, $db_cms, 'company', 'sic', "ALTER TABLE `company` ADD COLUMN `sic` VARCHAR(10) DEFAULT NULL AFTER `tourism_no`");
    addColumnIfMissing($conn, $db_cms, 'company', 'income', "ALTER TABLE `company` ADD COLUMN `income` VARCHAR(3) DEFAULT NULL AFTER `sic`");
    addColumnIfMissing($conn, $db_cms, 'company', 'submission_type', "ALTER TABLE `company` ADD COLUMN `submission_type` VARCHAR(100) NOT NULL DEFAULT '' AFTER `income`");
    addColumnIfMissing($conn, $db_cms, 'company', 'irbm_classification', "ALTER TABLE `company` ADD COLUMN `irbm_classification` VARCHAR(3) NOT NULL DEFAULT '' AFTER `submission_type`");
    addColumnIfMissing($conn, $db_cms, 'company', 'tax_exemption_reason', "ALTER TABLE `company` ADD COLUMN `tax_exemption_reason` VARCHAR(300) DEFAULT NULL AFTER `irbm_classification`");
    addColumnIfMissing($conn, $db_cms, 'company', 'sql_account_id', "ALTER TABLE `company` ADD COLUMN `sql_account_id` INT NOT NULL DEFAULT 0 AFTER `tax_exemption_reason`");

    addColumnIfMissing($conn, $db_cms, 'brand', 'company', "ALTER TABLE `brand` ADD COLUMN `company` INT DEFAULT NULL AFTER `name`");

    // Package schema backfill for import/export compatibility.
    addColumnIfMissing($conn, $db_cms, 'package', 'item_code', "ALTER TABLE `package` ADD COLUMN `item_code` VARCHAR(100) DEFAULT NULL AFTER `name`");
    addColumnIfMissing($conn, $db_cms, 'package', 'item_description', "ALTER TABLE `package` ADD COLUMN `item_description` TEXT DEFAULT NULL AFTER `item_code`");

    // 1. Insert new Pin Groups (125, 126 & 127)
    $sqlInsertPins = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (125, 'Stock In', '1,2,3,4,5,6', 'Stock In Management', '1', CURDATE(), CURTIME(), 'A'),
    (126, 'Stock Order Request', '1,2,3,4,5', 'Stock Order Request Management', '1', CURDATE(), CURTIME(), 'A'),
    (127, 'Company', '1,2,3,4,5,6', 'Company Management', '1', CURDATE(), CURTIME(), 'A'),
    (135, 'Purchase Order', '1,2,3,4,5,6', 'Purchase Order Management', '1', CURDATE(), CURTIME(), 'A'),
    (136, 'User Record Log', '1,2,3,4,5,6', 'User Record Log Management', '1', CURDATE(), CURTIME(), 'A')";
    
    if ($conn->query($sqlInsertPins)) {
        echo "<p style='color:green;'>Verified Pin groups 125, 126, 127, 135 & 136 exist in CMS database.</p>";
    }

    $sqlUpdateCompanyPin = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[127:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[127:%'";
    if ($conn->query($sqlUpdateCompanyPin)) {
        echo "<p style='color:green;'>Verified Pin 127 access for Super Admin.</p>";
    }

    $sqlUpdatePurchaseOrderPin = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[135:%'";
    if ($conn->query($sqlUpdatePurchaseOrderPin)) {
        echo "<p style='color:green;'>Verified Pin 135 access for Super Admin.</p>";
    }

    // 2. Update Super Admin (id=1) safely
    $sqlUpdateAdmin1 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[125:1,2,3,4,5,6]+[126:1,2,3,4,5]+[127:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[125:%'";
    if ($conn->query($sqlUpdateAdmin1)) {
        echo "<p style='color:green;'>Verified Pin 125, 126, 127 access for Super Admin.</p>";
    }

    $sqlUpdateAdmin1PurchaseOrder = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[135:%'";
    if ($conn->query($sqlUpdateAdmin1PurchaseOrder)) {
        echo "<p style='color:green;'>Verified Purchase Order Pin access for Super Admin.</p>";
    }

    // 3. Sync Admin Group (id=2) with Super Admin
    $sqlUpdateAdmin2 = "UPDATE `user_group` SET `pins` = (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t) WHERE `id` = 2";
    if ($conn->query($sqlUpdateAdmin2)) {
        echo "<p style='color:green;'>Verified synced pins for Admin.</p>";
    }

    // 4. Sync Basic User Group (id=3)
    $sqlUpdateBasic = "UPDATE `user_group` SET `pins` = (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t) WHERE `id` = 3";
    if ($conn->query($sqlUpdateBasic)) {
        echo "<p style='color:green;'>Verified synced pins for Basic User.</p>";
    }

    $sqlUpdateAdmin2_135 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[135:%'";
    if ($conn->query($sqlUpdateAdmin2_135)) {
        echo "<p style='color:green;'>Verified Pin 135 access for Admin.</p>";
    }

    $sqlUpdateAdmin1_136 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[136:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[136:%'";
    if ($conn->query($sqlUpdateAdmin1_136)) {
         echo "<p style='color:green;'>Verified Pin 136 access for Super Admin.</p>";
    }

    $sqlUpdateAdmin2_136 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[136:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[136:%'";
    if ($conn->query($sqlUpdateAdmin2_136)) {
         echo "<p style='color:green;'>Verified Pin 136 access for Admin.</p>";
    }

    $sqlUpdateBasic_135 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[135:%'";
    if ($conn->query($sqlUpdateBasic_135)) {
        echo "<p style='color:green;'>Verified Pin 135 access for Basic User.</p>";
    }

    $sqlUpdateBasic_136 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[136:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[136:%'";
    if ($conn->query($sqlUpdateBasic_136)) {
         echo "<p style='color:green;'>Verified Pin 136 access for Basic User.</p>";
    }
} else {
    echo "<p style='color:red;'>Failed to select CMS database to update pin groups.</p>";
}

// --- START: SHOPEE ROLE-BASED PIN GROUPS ---
if ($conn->select_db($db_cms)) {
    // 1. Insert new Shopee Pin Groups (128, 129, 130)
    $sqlInsertShopeePins = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (128, 'Shopee Processing Order', '1,2,3,4,5,6', 'Basic User Shopee View', '1', CURDATE(), CURTIME(), 'A'),
    (129, 'Shopee Verify Order', '1,2,3,4,5,6,14', 'Admin Shopee View', '1', CURDATE(), CURTIME(), 'A'),
    (130, 'Shopee All Orders', '1,2,3,4,5,6,14,15', 'Superadmin Shopee View', '1', CURDATE(), CURTIME(), 'A')
    ON DUPLICATE KEY UPDATE
        `name` = VALUES(`name`),
        `pins` = VALUES(`pins`),
        `remark` = VALUES(`remark`),
        `status` = 'A'";
    
    if ($conn->query($sqlInsertShopeePins)) {
        echo "<p style='color:green;'>Verified Shopee Role Pins (128, 129, 130) in CMS database.</p>";
    }

    // 2. Enforce one Shopee role pin per user group.
    ensureSingleShopeePinForGroup($conn, 1, '[130:1,2,3,4,5,6,14,15]');
    ensureSingleShopeePinForGroup($conn, 2, '[129:1,2,3,4,5,6,14]');
    ensureSingleShopeePinForGroup($conn, 3, '[128:1,2,3,4,5,6]');
}
// --- END: SHOPEE ROLE-BASED PIN GROUPS ---

// --- START: IMPORT SHORTCUT PIN GROUP ---
if ($conn->select_db($db_cms)) {
    $sqlInsertImportShortcut = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (131, 'Import Shortcut', '1', 'Import Shortcut Access', '1', CURDATE(), CURTIME(), 'A')";
    
    if ($conn->query($sqlInsertImportShortcut)) {
        echo "<p style='color:green;'>Verified Pin group 131 (Import Shortcut) in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 131: " . $conn->error . "</p>";
    }

    $sqlUpdateAdmin1_131 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[131:1]') WHERE `id` = 1 AND `pins` NOT LIKE '%[131:%'";
    if ($conn->query($sqlUpdateAdmin1_131)) {
        echo "<p style='color:green;'>Verified Import Shortcut access for Super Admin.</p>";
    }

    $sqlUpdateAdmin2_131 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[131:1]') WHERE `id` = 2 AND `pins` NOT LIKE '%[131:%'";
    if ($conn->query($sqlUpdateAdmin2_131)) {
        echo "<p style='color:green;'>Verified Import Shortcut access for Admin.</p>";
    }

    $sqlUpdateBasic_131 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[131:1]') WHERE `id` = 3 AND `pins` NOT LIKE '%[131:%'";
    if ($conn->query($sqlUpdateBasic_131)) {
        echo "<p style='color:green;'>Verified Import Shortcut access for Basic User.</p>";
    }
}
// --- END: IMPORT SHORTCUT PIN GROUP ---

// --- START: SQL ACCOUNT PIN GROUP ---
if ($conn->select_db($db_cms)) {
    $sqlInsertSqlAccountPin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (132, 'SQL Account', '1,2,3,4,5,6', 'SQL Account Management', '1', CURDATE(), CURTIME(), 'A')";

    if ($conn->query($sqlInsertSqlAccountPin)) {
        echo "<p style='color:green;'>Verified Pin group 132 (SQL Account) in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 132: " . $conn->error . "</p>";
    }

    $sqlUpdateAdmin1_132 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[132:%'";
    if ($conn->query($sqlUpdateAdmin1_132)) {
        echo "<p style='color:green;'>Verified SQL Account access for Super Admin.</p>";
    }

    $sqlUpdateAdmin2_132 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[132:%'";
    if ($conn->query($sqlUpdateAdmin2_132)) {
        echo "<p style='color:green;'>Verified SQL Account access for Admin.</p>";
    }

    $sqlUpdateBasic_132 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[132:%'";
    if ($conn->query($sqlUpdateBasic_132)) {
        echo "<p style='color:green;'>Verified SQL Account access for Basic User.</p>";
    }
}
// --- END: SQL ACCOUNT PIN GROUP ---

// --- START: TOKEN SETTING PIN GROUP ---
if ($conn->select_db($db_cms)) {
    $sqlInsertTokenSettingPin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (133, 'Token Setting', '1,2,3,4,5,6', 'Token Setting Management', '1', CURDATE(), CURTIME(), 'A')";

    if ($conn->query($sqlInsertTokenSettingPin)) {
        echo "<p style='color:green;'>Verified Pin group 133 (Token Setting) in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 133: " . $conn->error . "</p>";
    }

    $sqlUpdateAdmin1_133 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[133:%'";
    if ($conn->query($sqlUpdateAdmin1_133)) {
        echo "<p style='color:green;'>Verified Token Setting access for Super Admin.</p>";
    }

    $sqlUpdateAdmin2_133 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[133:%'";
    if ($conn->query($sqlUpdateAdmin2_133)) {
        echo "<p style='color:green;'>Verified Token Setting access for Admin.</p>";
    }

    $sqlUpdateBasic_133 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[133:%'";
    if ($conn->query($sqlUpdateBasic_133)) {
        echo "<p style='color:green;'>Verified Token Setting access for Basic User.</p>";
    }
}
// --- END: TOKEN SETTING PIN GROUP ---

// --- START: USER PROFILE PIN GROUP ---
if ($conn->select_db($db_cms)) {
    $sqlInsertUserProfilePin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (134, 'User Profile', '1,2', 'User Profile View/Edit', '1', CURDATE(), CURTIME(), 'A')";

    if ($conn->query($sqlInsertUserProfilePin)) {
        echo "<p style='color:green;'>Verified Pin group 134 (User Profile) in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 134: " . $conn->error . "</p>";
    }

    $sqlUpdateAdmin1_134 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[134:1,2]') WHERE `id` = 1 AND `pins` NOT LIKE '%[134:%'";
    if ($conn->query($sqlUpdateAdmin1_134)) {
        echo "<p style='color:green;'>Verified User Profile access for Super Admin.</p>";
    }

    $sqlUpdateAdmin2_134 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[134:1,2]') WHERE `id` = 2 AND `pins` NOT LIKE '%[134:%'";
    if ($conn->query($sqlUpdateAdmin2_134)) {
        echo "<p style='color:green;'>Verified User Profile access for Admin.</p>";
    }

    $sqlUpdateBasic_134 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[134:1,2]') WHERE `id` = 3 AND `pins` NOT LIKE '%[134:%'";
    if ($conn->query($sqlUpdateBasic_134)) {
        echo "<p style='color:green;'>Verified User Profile access for Basic User.</p>";
    }
}
// --- END: USER PROFILE PIN GROUP ---

// --- START: J&T BACKUP RECORD PIN UPDATE (ID 88) ---
if ($conn->select_db($db_cms)) {
    $sqlUpdateJtBackupPinGroup = "UPDATE `pin_group` SET `pins` = '1,2,3,4,5,6', `status` = 'A' WHERE `id` = 88";
    if ($conn->query($sqlUpdateJtBackupPinGroup)) {
        echo "<p style='color:green;'>Verified Pin group 88 (J&T Backup Record) updated to 1,2,3,4,5,6.</p>";
    } else {
        echo "<p style='color:red;'>Failed updating Pin group 88: " . $conn->error . "</p>";
    }

    updatePinBlockForGroup($conn, 1, 88, '1,2,3,4,5,6');
    updatePinBlockForGroup($conn, 2, 88, '1,2,3,4,5,6');
    updatePinBlockForGroup($conn, 3, 88, '1,2,3,4,5,6');
}
// --- END: J&T BACKUP RECORD PIN UPDATE (ID 88) ---

// --- START: FACEBOOK CUSTOMER RECORD (DEALS) PIN UPDATE (ID 75) ---
if ($conn->select_db($db_cms)) {
    $sqlUpdateFbDealsPinGroup = "UPDATE `pin_group` SET `pins` = '1,2,3,4,5,6', `status` = 'A' WHERE `id` = 75";
    if ($conn->query($sqlUpdateFbDealsPinGroup)) {
        echo "<p style='color:green;'>Verified Pin group 75 (Facebook Customer Record Deals) updated to 1,2,3,4,5,6.</p>";
    } else {
        echo "<p style='color:red;'>Failed updating Pin group 75: " . $conn->error . "</p>";
    }

    updatePinBlockForGroup($conn, 1, 75, '1,2,3,4,5,6');
    updatePinBlockForGroup($conn, 2, 75, '1,2,3,4,5,6');
    updatePinBlockForGroup($conn, 3, 75, '1,2,3,4,5,6');
}
// --- END: FACEBOOK CUSTOMER RECORD (DEALS) PIN UPDATE (ID 75) ---

// --- START: SHOPEE CUSTOMER RECORD PIN UPDATE (ID 85) ---
if ($conn->select_db($db_cms)) {
    $sqlUpdateShopeeCustPinGroup = "UPDATE `pin_group` SET `pins` = '1,2,3,4,5,6', `status` = 'A' WHERE `id` = 85";
    if ($conn->query($sqlUpdateShopeeCustPinGroup)) {
        echo "<p style='color:green;'>Verified Pin group 85 (Shopee Customer Record) updated to 1,2,3,4,5,6.</p>";
    } else {
        echo "<p style='color:red;'>Failed updating Pin group 85: " . $conn->error . "</p>";
    }

    updatePinBlockForGroup($conn, 1, 85, '1,2,3,4,5,6');
    updatePinBlockForGroup($conn, 2, 85, '1,2,3,4,5,6');
    updatePinBlockForGroup($conn, 3, 85, '1,2,3,4,5,6');
}
// --- END: SHOPEE CUSTOMER RECORD PIN UPDATE (ID 85) ---

echo "<h3>Stock Order Request financial schema setup complete.</h3>";
$conn->close();