<?php
// Include init.php to access all dynamic global configurations
include_once 'init.php'; 

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

function alterColumnToVarcharIfInt($conn, $dbName, $tableName, $columnName, $varcharLen = 255)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    
    // Explicitly select DATA_TYPE
    $sql = "SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn' LIMIT 1";
    $rst = $conn->query($sql);

    if (!$rst || $rst->num_rows === 0) {
        return;
    }

    $row = $rst->fetch_assoc();
    
    // Force all keys to lowercase to guarantee safe access regardless of MySQL configuration
    if ($row) {
        $row = array_change_key_case($row, CASE_LOWER);
    }
    
    // Safely check if the key exists before reading
    if (isset($row['data_type'])) {
        $dataType = strtolower((string) $row['data_type']);
        if (strpos($dataType, 'int') !== false) {
            $alterSql = "ALTER TABLE `$tableName` MODIFY COLUMN `$columnName` VARCHAR(" . (int) $varcharLen . ") NULL";
            if ($conn->query($alterSql)) {
                echo "<p style='color:blue;'>Updated `$tableName`.`$columnName` to VARCHAR(" . (int) $varcharLen . ").</p>";
            } else {
                echo "<p style='color:red;'>Failed updating `$tableName`.`$columnName`: " . $conn->error . "</p>";
            }
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
        return;
    }

    $row = $rst->fetch_assoc();
    if ($row) {
        $row = array_change_key_case($row, CASE_LOWER);
    }

    if (isset($row['data_type']) && strtolower((string) $row['data_type']) === 'varchar') {
        $alterSql = "ALTER TABLE `$tableName` MODIFY COLUMN `$columnName` TEXT NULL";
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Updated `$tableName`.`$columnName` to TEXT.</p>";
        } else {
            echo "<p style='color:red;'>Failed updating `$tableName`.`$columnName` to TEXT: " . $conn->error . "</p>";
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
    $conn->query("UPDATE `user_group` SET `pins` = '{$safePins}' WHERE `id` = {$groupId}");
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
        $conn->query("ALTER TABLE `stock_order_request_item` CHANGE COLUMN `qty` `packageQty` INT NOT NULL DEFAULT 1");
    } else {
        addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'packageQty', "ALTER TABLE `stock_order_request_item` ADD COLUMN `packageQty` INT NOT NULL DEFAULT 1 AFTER `package_desc`");
    }
}
addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'productQty', "ALTER TABLE `stock_order_request_item` ADD COLUMN `productQty` INT NOT NULL DEFAULT 1 AFTER `packageQty`");
$conn->query("UPDATE `stock_order_request_item` SET `productQty`=`packageQty` WHERE IFNULL(`productQty`,0)<=0");

dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_no', "ALTER TABLE `stock_order_request` DROP COLUMN `request_no`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_by', "ALTER TABLE `stock_order_request` DROP COLUMN `request_by`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_no', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_no`");
dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_by', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_by`");
dropIndexIfExists($conn, $db_fin, 'stock_order_request', 'uq_sor_request_no', "ALTER TABLE `stock_order_request` DROP INDEX `uq_sor_request_no`");

dropColumnIfExists($conn, $db_fin, 'stock_in_order', 'stock_order_request_id', "ALTER TABLE `stock_in_order` DROP COLUMN `stock_order_request_id`");
dropIndexIfExists($conn, $db_fin, 'stock_in_order', 'uq_stock_order_request_id', "ALTER TABLE `stock_in_order` DROP INDEX `uq_stock_order_request_id`");
if (!columnExists($conn, $db_fin, 'stock_in_order', 'stock_order_request_id')) {
    echo "<p style='color:blue;'>Verified `stock_in_order`.`stock_order_request_id` is removed.</p>";
}
if (!indexExists($conn, $db_fin, 'stock_in_order', 'uq_stock_order_request_id')) {
    echo "<p style='color:blue;'>Verified index `uq_stock_order_request_id` is removed from `stock_in_order`.</p>";
}

// Ensure courier_id type is consistent with CMS courier.id (varchar).
alterColumnToVarcharIfInt($conn, $db_fin, 'stock_order_request', 'courier_id', 100);

// Ensure Shopee Order Request supports storing multiple package/brand IDs as CSV.
alterColumnToVarcharIfInt($conn, $db_fin, 'shopee_sg_order_request', 'package', 255);
alterColumnToVarcharIfInt($conn, $db_fin, 'shopee_sg_order_request', 'brand', 255);

// Ensure Stock In item supports CSV products and quantities.
alterColumnToVarcharIfInt($conn, $db_fin, 'stock_in_order_item', 'product_id', 100);
alterColumnToVarcharIfInt($conn, $db_fin, 'stock_in_order_item', 'product_quantity', 255);
addColumnIfMissing($conn, $db_fin, 'stock_in_order', 'attachment', "ALTER TABLE `stock_in_order` ADD COLUMN `attachment` TEXT DEFAULT NULL AFTER `stock_in_date`");
alterColumnToTextIfVarchar($conn, $db_fin, 'stock_in_order', 'attachment');

addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'currency', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `currency` VARCHAR(10) DEFAULT NULL AFTER `date`");
addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'total_gst', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `total_gst` DECIMAL(10,2) DEFAULT '0.00' AFTER `currency`");
addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'total_amount', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `total_amount` DECIMAL(10,2) DEFAULT '0.00' AFTER `total_gst`");

$conn->query("ALTER TABLE `jt_transaction_backup` ENGINE=InnoDB");

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

    dropColumnIfExists($conn, $db_cms, 'token_setting', 'chat_id', "ALTER TABLE `token_setting` DROP COLUMN `chat_id`");

    addColumnIfMissing($conn, $db_cms, 'user', 'main_report_supervisor', "ALTER TABLE `user` ADD COLUMN `main_report_supervisor` INT DEFAULT NULL AFTER `access_id`");
    addColumnIfMissing($conn, $db_cms, 'user', 'second_report_supervisor', "ALTER TABLE `user` ADD COLUMN `second_report_supervisor` INT DEFAULT NULL AFTER `main_report_supervisor`");
    if (columnExists($conn, $db_cms, 'user', 'main_report_supervisor') && columnExists($conn, $db_cms, 'user', 'second_report_supervisor')) {
        echo "<p style='color:blue;'>Columns `main_report_supervisor` and `second_report_supervisor` are ready in `user` table.</p>";
    }

    addColumnIfMissing($conn, $db_cms, 'sql_account', 'status', "ALTER TABLE `sql_account` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `name`");

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

    // 4. Sync Basic User Group (id=3)
    $sqlUpdateBasic = "UPDATE `user_group` 
    SET `pins` = (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t) 
    WHERE `id` = 3";
    $conn->query($sqlUpdateBasic);

    if ($conn->query($sqlUpdateCompanyPin) && ($sqlUpdateAdmin1) && $conn->query($sqlUpdateAdmin2) && $conn->query($sqlUpdateBasic)) {
        echo "<p style='color:blue;'>Pin groups 125, 126 & 127 insert into Super Admin, Admin & Basic User in CMS database.</p>";
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
        echo "<p style='color:blue;'>Shopee Role Pins (128, 129, 130) ensured in CMS database.</p>";
    }

    // 2. Enforce one Shopee role pin per user group.
    ensureSingleShopeePinForGroup($conn, 1, '[130:1,2,3,4,5,6,14,15]');
    ensureSingleShopeePinForGroup($conn, 2, '[129:1,2,3,4,5,6,14]');
    ensureSingleShopeePinForGroup($conn, 3, '[128:1,2,3,4,5,6]');
}
// --- END: SHOPEE ROLE-BASED PIN GROUPS ---

// --- START: IMPORT SHORTCUT PIN GROUP ---
if ($conn->select_db($db_cms)) {
    // 1. Insert new Pin Group (131) with only pin '1'
    $sqlInsertImportShortcut = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (131, 'Import Shortcut', '1', 'Import Shortcut Access', '1', CURDATE(), CURTIME(), 'A')";
    
    if ($conn->query($sqlInsertImportShortcut)) {
        echo "<p style='color:blue;'>Pin group 131 (Import Shortcut) ensured in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 131: " . $conn->error . "</p>";
    }

    // 2. Assign Pin Group 131 to Super Admin (id=1)
    $sqlUpdateAdmin1_131 = "UPDATE `user_group` 
    SET `pins` = CONCAT(`pins`, '+[131:1]') 
    WHERE `id` = 1 AND `pins` NOT LIKE '%[131:%'";
    
    if ($conn->query($sqlUpdateAdmin1_131)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Import Shortcut access to Super Admin.</p>";
    }

    // 3. Assign Pin Group 131 to Admin (id=2)
    $sqlUpdateAdmin2_131 = "UPDATE `user_group` 
    SET `pins` = CONCAT(`pins`, '+[131:1]') 
    WHERE `id` = 2 AND `pins` NOT LIKE '%[131:%'";
    
    if ($conn->query($sqlUpdateAdmin2_131)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Import Shortcut access to Admin.</p>";
    }

    // 4. Assign Pin Group 131 to Basic User (id=3)
    $sqlUpdateBasic_131 = "UPDATE `user_group` 
    SET `pins` = CONCAT(`pins`, '+[131:1]') 
    WHERE `id` = 3 AND `pins` NOT LIKE '%[131:%'";
    
    if ($conn->query($sqlUpdateBasic_131)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Import Shortcut access to Basic User.</p>";
    }
}
// --- END: IMPORT SHORTCUT PIN GROUP ---

// --- START: IMPORT SHORTCUT PIN GROUP ---
if ($conn->select_db($db_cms)) {
    // 1. Insert new Pin Group (131) with only pin '1'
    $sqlInsertImportShortcut = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (131, 'Import Shortcut', '1', 'Import Shortcut Access', '1', CURDATE(), CURTIME(), 'A')";
    
    if ($conn->query($sqlInsertImportShortcut)) {
        echo "<p style='color:blue;'>Pin group 131 (Import Shortcut) ensured in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 131: " . $conn->error . "</p>";
    }

    // 2. Assign Pin Group 131 to Super Admin (id=1)
    $sqlUpdateAdmin1_131 = "UPDATE `user_group` 
    SET `pins` = CONCAT(`pins`, '+[131:1]') 
    WHERE `id` = 1 AND `pins` NOT LIKE '%[131:%'";
    
    if ($conn->query($sqlUpdateAdmin1_131)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Import Shortcut access to Super Admin.</p>";
    }

    // 3. Assign Pin Group 131 to Admin (id=2)
    $sqlUpdateAdmin2_131 = "UPDATE `user_group` 
    SET `pins` = CONCAT(`pins`, '+[131:1]') 
    WHERE `id` = 2 AND `pins` NOT LIKE '%[131:%'";
    
    if ($conn->query($sqlUpdateAdmin2_131)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Import Shortcut access to Admin.</p>";
    }

    // 4. Assign Pin Group 131 to Basic User (id=3)
    $sqlUpdateBasic_131 = "UPDATE `user_group` 
    SET `pins` = CONCAT(`pins`, '+[131:1]') 
    WHERE `id` = 3 AND `pins` NOT LIKE '%[131:%'";
    
    if ($conn->query($sqlUpdateBasic_131)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Import Shortcut access to Basic User.</p>";
    }
}
// --- END: IMPORT SHORTCUT PIN GROUP ---

// --- START: SQL ACCOUNT PIN GROUP ---
if ($conn->select_db($db_cms)) {
    // 1. Insert new Pin Group (132)
    $sqlInsertSqlAccountPin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (132, 'SQL Account', '1,2,3,4,5,6', 'SQL Account Management', '1', CURDATE(), CURTIME(), 'A')";

    if ($conn->query($sqlInsertSqlAccountPin)) {
        echo "<p style='color:blue;'>Pin group 132 (SQL Account) ensured in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 132: " . $conn->error . "</p>";
    }

    // 2. Assign Pin Group 132 to Super Admin (id=1)
    $sqlUpdateAdmin1_132 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]')
    WHERE `id` = 1 AND `pins` NOT LIKE '%[132:%'";

    if ($conn->query($sqlUpdateAdmin1_132)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted SQL Account access to Super Admin.</p>";
    }

    // 3. Assign Pin Group 132 to Admin (id=2)
    $sqlUpdateAdmin2_132 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]')
    WHERE `id` = 2 AND `pins` NOT LIKE '%[132:%'";

    if ($conn->query($sqlUpdateAdmin2_132)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted SQL Account access to Admin.</p>";
    }

    // 4. Assign Pin Group 132 to Basic User (id=3)
    $sqlUpdateBasic_132 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]')
    WHERE `id` = 3 AND `pins` NOT LIKE '%[132:%'";

    if ($conn->query($sqlUpdateBasic_132)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted SQL Account access to Basic User.</p>";
    }
}
// --- END: SQL ACCOUNT PIN GROUP ---

// --- START: TOKEN SETTING PIN GROUP ---
if ($conn->select_db($db_cms)) {
    // 1. Insert new Pin Group (133)
    $sqlInsertTokenSettingPin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (133, 'Token Setting', '1,2,3,4,5,6', 'Token Setting Management', '1', CURDATE(), CURTIME(), 'A')";

    if ($conn->query($sqlInsertTokenSettingPin)) {
        echo "<p style='color:blue;'>Pin group 133 (Token Setting) ensured in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 133: " . $conn->error . "</p>";
    }

    // 2. Assign Pin Group 133 to Super Admin (id=1)
    $sqlUpdateAdmin1_133 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]')
    WHERE `id` = 1 AND `pins` NOT LIKE '%[133:%'";

    if ($conn->query($sqlUpdateAdmin1_133)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Token Setting access to Super Admin.</p>";
    }

    // 3. Assign Pin Group 133 to Admin (id=2)
    $sqlUpdateAdmin2_133 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]')
    WHERE `id` = 2 AND `pins` NOT LIKE '%[133:%'";

    if ($conn->query($sqlUpdateAdmin2_133)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Token Setting access to Admin.</p>";
    }

    // 4. Assign Pin Group 133 to Basic User (id=3)
    $sqlUpdateBasic_133 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]')
    WHERE `id` = 3 AND `pins` NOT LIKE '%[133:%'";

    if ($conn->query($sqlUpdateBasic_133)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted Token Setting access to Basic User.</p>";
    }
}
// --- END: TOKEN SETTING PIN GROUP ---

// --- START: USER PROFILE PIN GROUP ---
if ($conn->select_db($db_cms)) {
    // 1. Insert new Pin Group (134)
    $sqlInsertUserProfilePin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    (134, 'User Profile', '1,2', 'User Profile View/Edit', '1', CURDATE(), CURTIME(), 'A')";

    if ($conn->query($sqlInsertUserProfilePin)) {
        echo "<p style='color:blue;'>Pin group 134 (User Profile) ensured in CMS database.</p>";
    } else {
        echo "<p style='color:red;'>Error creating Pin group 134: " . $conn->error . "</p>";
    }

    // 2. Assign Pin Group 134 to Super Admin (id=1)
    $sqlUpdateAdmin1_134 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[134:1,2]')
    WHERE `id` = 1 AND `pins` NOT LIKE '%[134:%'";
    if ($conn->query($sqlUpdateAdmin1_134)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted User Profile access to Super Admin.</p>";
    }

    // 3. Assign Pin Group 134 to Admin (id=2)
    $sqlUpdateAdmin2_134 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[134:1,2]')
    WHERE `id` = 2 AND `pins` NOT LIKE '%[134:%'";
    if ($conn->query($sqlUpdateAdmin2_134)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted User Profile access to Admin.</p>";
    }

    // 4. Assign Pin Group 134 to Basic User (id=3)
    $sqlUpdateBasic_134 = "UPDATE `user_group`
    SET `pins` = CONCAT(`pins`, '+[134:1,2]')
    WHERE `id` = 3 AND `pins` NOT LIKE '%[134:%'";
    if ($conn->query($sqlUpdateBasic_134)) {
        if ($conn->affected_rows > 0) echo "<p style='color:blue;'>Granted User Profile access to Basic User.</p>";
    }
}
// --- END: USER PROFILE PIN GROUP ---

echo "<h3>Stock Order Request financial schema setup complete.</h3>";
$conn->close();