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

function ensureVarcharColumnLengthAtLeast($conn, $dbName, $tableName, $columnName, $minLength = 255, $defaultValue = '')
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    $qualifiedTable = "`" . str_replace('`', '``', $dbName) . "`.`" . str_replace('`', '``', $tableName) . "`";
    $rst = $conn->query("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.columns
        WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn'
        LIMIT 1");

    if (!$rst || $rst->num_rows === 0) {
        echo "<p style='color:orange;'>Column `$columnName` not found in `$tableName` to verify length.</p>";
        return;
    }

    $row = $rst->fetch_assoc();
    if ($row) {
        $row = array_change_key_case($row, CASE_LOWER);
    }

    $dataType = isset($row['data_type']) ? strtolower((string) $row['data_type']) : '';
    $currentLength = isset($row['character_maximum_length']) ? (int) $row['character_maximum_length'] : 0;
    $isNullable = isset($row['is_nullable']) ? strtoupper((string) $row['is_nullable']) === 'YES' : false;
    $defaultSql = $defaultValue === null ? 'DEFAULT NULL' : "NOT NULL DEFAULT '" . $conn->real_escape_string((string) $defaultValue) . "'";

    if ($dataType !== 'varchar' || $currentLength < (int) $minLength || $isNullable) {
        $alterSql = "ALTER TABLE " . $qualifiedTable . " MODIFY COLUMN `" . str_replace('`', '``', $columnName) . "` VARCHAR(" . (int) $minLength . ") " . $defaultSql;
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Verified `$tableName`.`$columnName` supports VARCHAR(" . (int) $minLength . ").</p>";
        } else {
            echo "<p style='color:red;'>Failed updating `$tableName`.`$columnName` length: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green;'>Verified `$tableName`.`$columnName` already supports VARCHAR(" . (int) $currentLength . ").</p>";
    }
}

function insertTableParsePinBlocks($rawPins)
{
    $blocks = array();
    foreach (explode('+', (string) $rawPins) as $entry) {
        $entry = trim($entry);
        if ($entry === '') {
            continue;
        }

        $parts = explode(':', trim($entry, '[]'), 2);
        if (count($parts) !== 2) {
            continue;
        }

        $pinGroupId = trim((string) $parts[0]);
        if ($pinGroupId === '' || !ctype_digit($pinGroupId)) {
            continue;
        }

        $pinIds = array();
        foreach (explode(',', (string) $parts[1]) as $pinId) {
            $pinId = trim((string) $pinId);
            if ($pinId !== '' && ctype_digit($pinId)) {
                $pinIds[$pinId] = $pinId;
            }
        }

        $blocks[$pinGroupId] = array_values($pinIds);
    }

    return $blocks;
}

function insertTableBuildPinBlocks($blocks)
{
    $parts = array();
    foreach ((array) $blocks as $pinGroupId => $pinIds) {
        $pinGroupId = trim((string) $pinGroupId);
        if ($pinGroupId === '' || !ctype_digit($pinGroupId)) {
            continue;
        }

        $normalizedPinIds = array();
        foreach ((array) $pinIds as $pinId) {
            $pinId = trim((string) $pinId);
            if ($pinId !== '' && ctype_digit($pinId)) {
                $normalizedPinIds[$pinId] = $pinId;
            }
        }

        if (!empty($normalizedPinIds)) {
            $parts[] = '[' . $pinGroupId . ':' . implode(',', array_values($normalizedPinIds)) . ']';
        }
    }

    return implode('+', $parts);
}

function insertTableAddPinBlockAccess($rawPins, $pinGroupId, $pinIds)
{
    $blocks = insertTableParsePinBlocks($rawPins);
    $pinGroupId = (string) ((int) $pinGroupId);
    if (!isset($blocks[$pinGroupId])) {
        $blocks[$pinGroupId] = array();
    }

    foreach ((array) $pinIds as $pinId) {
        $pinId = trim((string) $pinId);
        if ($pinId !== '' && ctype_digit($pinId)) {
            $blocks[$pinGroupId][$pinId] = $pinId;
        }
    }

    return insertTableBuildPinBlocks($blocks);
}

function insertTableEnsureOrderReportPins($cmsConn)
{
    $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (155, 'Shopee Report', '1', 'Shopee order report view access', '1', CURDATE(), CURTIME(), 'A'),
        (156, 'Facebook Report', '1', 'Facebook order report view access', '1', CURDATE(), CURTIME(), 'A'),
        (157, 'Website Report', '1', 'Website order report view access', '1', CURDATE(), CURTIME(), 'A'),
        (158, 'Lazada Report', '1', 'Lazada order report view access', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";

    if ($cmsConn->query($pinGroupSql)) {
        echo "<p style='color:green;'><strong>Order Report pin setup:</strong> Verified pin groups 155-158 for Shopee Report, Facebook Report, Website Report, and Lazada Report.</p>";
    } else {
        echo "<p style='color:red;'><strong>Order Report pin setup:</strong> Failed creating pin groups 155-158: " . $cmsConn->error . "</p>";
    }

    foreach (array(1, 2) as $groupId) {
        $userGroupResult = $cmsConn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>Order Report pin setup skipped `user_group` id " . (int) $groupId . " because the group was not found.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = $currentPins;
        foreach (array(155, 156, 157, 158) as $pinGroupId) {
            $updatedPins = insertTableAddPinBlockAccess($updatedPins, $pinGroupId, array(1));
        }

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Order Report pin setup granted View access for pin groups 155-158 to `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Order Report pin setup failed updating `user_group` id " . (int) $groupId . ": " . $cmsConn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Order Report pin setup verified View access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
    }
}

$cmsConn = new mysqli($dbhost, $dbUser, $dbpwd, $db_cms, $dbport);
if ($cmsConn->connect_error) {
    echo "<p style='color:red;'><strong>Order Report pin setup:</strong> Failed connecting to CMS database `" . htmlspecialchars($db_cms, ENT_QUOTES, 'UTF-8') . "`: " . htmlspecialchars($cmsConn->connect_error, ENT_QUOTES, 'UTF-8') . "</p>";
} else {
    insertTableEnsureOrderReportPins($cmsConn);
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

$customerTagAssignmentTable = defined('CUS_TAG_ASSIGNMENT') ? CUS_TAG_ASSIGNMENT : 'customer_tag_assignment';
$createCustomerTagAssignmentTableSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`{$customerTagAssignmentTable}` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` VARCHAR(30) NOT NULL,
    `customer_id` INT NOT NULL,
    `tag_id` INT NOT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    UNIQUE KEY `uniq_platform_customer_tag` (`platform`, `customer_id`, `tag_id`),
    KEY `idx_platform_customer_status` (`platform`, `customer_id`, `status`),
    KEY `idx_tag_status` (`tag_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createCustomerTagAssignmentTableSql)) {
    echo "<p style='color:blue;'>Table `{$customerTagAssignmentTable}` is ready in `{$db_cms}`.</p>";
} else {
    echo "<p style='color:red;'>Error creating `{$customerTagAssignmentTable}` in `{$db_cms}`: " . $conn->error . "</p>";
}

$createTagTableSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`" . TAG . "` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL,
    `remark` TEXT DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    KEY `idx_tag_name_status` (`name`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($createTagTableSql)) {
    echo "<p style='color:green;'>Verified table `" . TAG . "` is ready with utf8mb4 support.</p>";
} else {
    echo "<p style='color:red;'>Failed creating `" . TAG . "`: " . $conn->error . "</p>";
}

$tagTableName = $conn->real_escape_string(TAG);
$tagSchemaName = $conn->real_escape_string($db_cms);
$tagCollationCheckSql = "SELECT TABLE_COLLATION
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '" . $tagSchemaName . "'
      AND TABLE_NAME = '" . $tagTableName . "'
    LIMIT 1";

$tagCollationResult = $conn->query($tagCollationCheckSql);

if ($tagCollationResult) {
    $tagCollationRow = $tagCollationResult->fetch_assoc();

    if ($tagCollationRow && $tagCollationRow['TABLE_COLLATION'] !== 'utf8mb4_unicode_ci') {
        $alterTagCollationSql = "ALTER TABLE `{$db_cms}`.`" . TAG . "`
            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        if ($conn->query($alterTagCollationSql)) {
            echo "<p style='color:green;'>Verified table `" . TAG . "` collation is utf8mb4_unicode_ci.</p>";
        } else {
            echo "<p style='color:red;'>Failed altering `" . TAG . "` collation: " . $conn->error . "</p>";
        }
    } elseif ($tagCollationRow) {
        echo "<p style='color:green;'>Verified table `" . TAG . "` collation is utf8mb4_unicode_ci.</p>";
    } else {
        echo "<p style='color:red;'>Failed verifying `" . TAG . "` collation: table metadata not found.</p>";
    }
} else {
    echo "<p style='color:red;'>Failed checking `" . TAG . "` collation: " . $conn->error . "</p>";
}

// function normalizeShopeePins($pinStr)
// {
//     $cleanPins = preg_replace('/\+?\[(128|129|130):[^\]]*\]/', '', (string) $pinStr);
//     $cleanPins = preg_replace('/\+{2,}/', '+', $cleanPins);
//     return trim($cleanPins, '+');
// }

// function ensureSingleShopeePinForGroup($conn, $groupId, $shopeePinBlock)
// {
//     $groupId = (int) $groupId;
//     $query = "SELECT `pins` FROM `user_group` WHERE `id` = {$groupId} LIMIT 1";
//     $result = $conn->query($query);
//     if (!$result || $result->num_rows === 0) {
//         return;
//     }

//     $row = $result->fetch_assoc();
//     $basePins = normalizeShopeePins($row['pins']);
//     $updatedPins = $basePins === '' ? $shopeePinBlock : ($basePins . '+' . $shopeePinBlock);
//     $safePins = $conn->real_escape_string($updatedPins);
//     if ($conn->query("UPDATE `user_group` SET `pins` = '{$safePins}' WHERE `id` = {$groupId}")) {
//         echo "<p style='color:green;'>Verified Shopee Pins for Group ID: {$groupId}.</p>";
//     }
// }

// function updatePinBlockForGroup($conn, $groupId, $pinId, $pinAccessList)
// {
//     $groupId = (int) $groupId;
//     $pinId = (int) $pinId;
//     $pinBlock = '[' . $pinId . ':' . $pinAccessList . ']';

//     $query = "SELECT `pins` FROM `user_group` WHERE `id` = {$groupId} LIMIT 1";
//     $result = $conn->query($query);
//     if (!$result || $result->num_rows === 0) {
//         echo "<p style='color:red;'>Failed updating Pin {$pinId}: user group {$groupId} not found.</p>";
//         return;
//     }

//     $row = $result->fetch_assoc();
//     $currentPins = isset($row['pins']) ? (string) $row['pins'] : '';
//     $cleanPins = preg_replace('/\+?\[' . $pinId . ':[^\]]*\]/', '', $currentPins);
//     $cleanPins = preg_replace('/\+{2,}/', '+', (string) $cleanPins);
//     $cleanPins = trim((string) $cleanPins, '+');

//     $updatedPins = $cleanPins === '' ? $pinBlock : ($cleanPins . '+' . $pinBlock);
//     $safePins = $conn->real_escape_string($updatedPins);

//     if ($conn->query("UPDATE `user_group` SET `pins` = '{$safePins}' WHERE `id` = {$groupId}")) {
//         echo "<p style='color:green;'>Verified Pin {$pinId} access ({$pinAccessList}) for Group ID: {$groupId}.</p>";
//     } else {
//         echo "<p style='color:red;'>Failed updating Pin {$pinId} for Group ID {$groupId}: " . $conn->error . "</p>";
//     }
// }

// // ==========================================
// // STOCK ORDER REQUEST TABLE CREATION
// // ==========================================

// $createStockOrderRequestTableSql = "CREATE TABLE IF NOT EXISTS `stock_order_request` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `warehouse_id` INT NOT NULL,
//     `invoice_no` TEXT DEFAULT NULL,
//     `invoice_date` DATE DEFAULT NULL,
//     `request_date` DATE NOT NULL,
//     `courier_id` VARCHAR(100) DEFAULT NULL,
//     `tracking_no` VARCHAR(120) DEFAULT NULL,
//     `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
//     `brand_id` INT DEFAULT NULL,
//     `company_id` INT DEFAULT NULL,
//     `tracking_status` TEXT DEFAULT NULL,
//     `tracking_last_sync` DATETIME DEFAULT NULL,
//     `attachment` VARCHAR(255) DEFAULT NULL,
//     `order_link_token` VARCHAR(255) DEFAULT NULL,
//     `qr_image` VARCHAR(255) DEFAULT NULL,
//     `remark` TEXT DEFAULT NULL,
//     `create_by` VARCHAR(30) DEFAULT NULL,
//     `create_date` DATE DEFAULT NULL,
//     `create_time` TIME DEFAULT NULL,
//     `update_by` VARCHAR(30) DEFAULT NULL,
//     `update_date` DATE DEFAULT NULL,
//     `update_time` TIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A'
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// if ($conn->query($createStockOrderRequestTableSql)) {
//     echo "<p style='color:blue;'>Table `stock_order_request` is ready.</p>";
// } else {
//     echo "<p style='color:red;'>Error creating `stock_order_request`: " . $conn->error . "</p>";
// }

// $createStockOrderRequestItemTableSql = "CREATE TABLE IF NOT EXISTS `stock_order_request_item` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `request_id` INT NOT NULL,
//     `product_id` INT DEFAULT NULL,
//     `package_id` INT NOT NULL,
//     `package_group_key` VARCHAR(120) DEFAULT NULL,
//     `package_desc` TEXT DEFAULT NULL,
//     `package_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
//     `packageQty` INT NOT NULL DEFAULT 1,
//     `productQty` INT NOT NULL DEFAULT 1,
//     `create_by` VARCHAR(30) DEFAULT NULL,
//     `create_date` DATE DEFAULT NULL,
//     `create_time` TIME DEFAULT NULL,
//     `update_by` VARCHAR(30) DEFAULT NULL,
//     `update_date` DATE DEFAULT NULL,
//     `update_time` TIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A',
//     KEY `idx_sor_item_request_id` (`request_id`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// if ($conn->query($createStockOrderRequestItemTableSql)) {
//     echo "<p style='color:blue;'>Table `stock_order_request_item` is ready.</p>";
// } else {
//     echo "<p style='color:red;'>Error creating `stock_order_request_item`: " . $conn->error . "</p>";
// }

// $createStockInOrderTableSql = "CREATE TABLE IF NOT EXISTS `stock_in_order` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `warehouse_id` INT NOT NULL,
//     `order_number` VARCHAR(120) NOT NULL,
//     `stock_in_date` DATE NOT NULL,
//     `attachment` TEXT DEFAULT NULL,
//     `create_by` VARCHAR(30) DEFAULT NULL,
//     `create_date` DATE DEFAULT NULL,
//     `create_time` TIME DEFAULT NULL,
//     `update_by` VARCHAR(30) DEFAULT NULL,
//     `update_date` DATE DEFAULT NULL,
//     `update_time` TIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A',
//     KEY `idx_order_number` (`order_number`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// if ($conn->query($createStockInOrderTableSql)) {
//     echo "<p style='color:blue;'>Table `stock_in_order` is ready.</p>";
// } else {
//     echo "<p style='color:red;'>Error creating `stock_in_order`: " . $conn->error . "</p>";
// }

// $createStockInItemTableSql = "CREATE TABLE IF NOT EXISTS `stock_in_order_item` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `stock_in_order_id` INT NOT NULL,
//     `product_id` VARCHAR(100) DEFAULT NULL,
//     `package_id` INT NOT NULL DEFAULT 0,
//     `product_quantity` VARCHAR(255) NOT NULL DEFAULT '1',
//     `create_by` VARCHAR(30) DEFAULT NULL,
//     `create_date` DATE DEFAULT NULL,
//     `create_time` TIME DEFAULT NULL,
//     `update_by` VARCHAR(30) DEFAULT NULL,
//     `update_date` DATE DEFAULT NULL,
//     `update_time` TIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A',
//     KEY `idx_stock_in_order_id` (`stock_in_order_id`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// if ($conn->query($createStockInItemTableSql)) {
//     echo "<p style='color:blue;'>Table `stock_in_order_item` is ready.</p>";
// } else {
//     echo "<p style='color:red;'>Error creating `stock_in_order_item`: " . $conn->error . "</p>";
// }

// // Backward-compatible ALTERs.
// addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'invoice_no', "ALTER TABLE `stock_order_request` ADD COLUMN `invoice_no` TEXT DEFAULT NULL AFTER `warehouse_id`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'invoice_date', "ALTER TABLE `stock_order_request` ADD COLUMN `invoice_date` DATE DEFAULT NULL AFTER `invoice_no`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'total_price', "ALTER TABLE `stock_order_request` ADD COLUMN `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `tracking_no`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'brand_id', "ALTER TABLE `stock_order_request` ADD COLUMN `brand_id` INT DEFAULT NULL AFTER `total_price`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'company_id', "ALTER TABLE `stock_order_request` ADD COLUMN `company_id` INT DEFAULT NULL AFTER `brand_id`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'product_id', "ALTER TABLE `stock_order_request_item` ADD COLUMN `product_id` INT DEFAULT NULL AFTER `request_id`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'brand_id', "ALTER TABLE `stock_order_request_item` ADD COLUMN `brand_id` INT DEFAULT NULL AFTER `product_id`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'company_id', "ALTER TABLE `stock_order_request_item` ADD COLUMN `company_id` INT DEFAULT NULL AFTER `brand_id`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'package_group_key', "ALTER TABLE `stock_order_request_item` ADD COLUMN `package_group_key` VARCHAR(120) DEFAULT NULL AFTER `package_id`");
// addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'package_price', "ALTER TABLE `stock_order_request_item` ADD COLUMN `package_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `package_desc`");

// if (!columnExists($conn, $db_fin, 'stock_order_request_item', 'packageQty')) {
//     if (columnExists($conn, $db_fin, 'stock_order_request_item', 'qty')) {
//         if ($conn->query("ALTER TABLE `stock_order_request_item` CHANGE COLUMN `qty` `packageQty` INT NOT NULL DEFAULT 1")) {
//             echo "<p style='color:blue;'>Changed `qty` to `packageQty` in `stock_order_request_item`.</p>";
//         }
//     } else {
//         addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'packageQty', "ALTER TABLE `stock_order_request_item` ADD COLUMN `packageQty` INT NOT NULL DEFAULT 1 AFTER `package_desc`");
//     }
// } else {
//     echo "<p style='color:green;'>Verified column `packageQty` already exists in `stock_order_request_item`.</p>";
// }

// addColumnIfMissing($conn, $db_fin, 'stock_order_request_item', 'productQty', "ALTER TABLE `stock_order_request_item` ADD COLUMN `productQty` INT NOT NULL DEFAULT 1 AFTER `packageQty`");

// if ($conn->query("UPDATE `stock_order_request_item` SET `productQty`=`packageQty` WHERE IFNULL(`productQty`,0)<=0")) {
//     echo "<p style='color:green;'>Verified `productQty` equals `packageQty` where missing.</p>";
// }

// dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_no', "ALTER TABLE `stock_order_request` DROP COLUMN `request_no`");
// dropColumnIfExists($conn, $db_fin, 'stock_order_request', 'request_by', "ALTER TABLE `stock_order_request` DROP COLUMN `request_by`");
// dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_no', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_no`");
// dropColumnIfExists($conn, $db_fin, 'stock_order_request_item', 'request_by', "ALTER TABLE `stock_order_request_item` DROP COLUMN `request_by`");
// dropIndexIfExists($conn, $db_fin, 'stock_order_request', 'uq_sor_request_no', "ALTER TABLE `stock_order_request` DROP INDEX `uq_sor_request_no`");

// dropColumnIfExists($conn, $db_fin, 'stock_in_order', 'stock_order_request_id', "ALTER TABLE `stock_in_order` DROP COLUMN `stock_order_request_id`");
// dropIndexIfExists($conn, $db_fin, 'stock_in_order', 'uq_stock_order_request_id', "ALTER TABLE `stock_in_order` DROP INDEX `uq_stock_order_request_id`");

// // Ensure courier_id type is consistent with CMS courier.id (varchar).
// alterColumnToVarcharIfInt($conn, $db_fin, 'stock_order_request', 'courier_id', 100);

// // Ensure Shopee Order Request supports storing multiple package/brand IDs as CSV.
// alterColumnToVarcharIfInt($conn, $db_fin, 'shopee_sg_order_request', 'package', 255);
// alterColumnToVarcharIfInt($conn, $db_fin, 'shopee_sg_order_request', 'brand', 255);
if ($conn->select_db($db_cms)) {
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'estimated_received_date', "ALTER TABLE `lazada_order_request` ADD COLUMN `estimated_received_date` DATE DEFAULT NULL AFTER `remark`");
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `lazada_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `lazada_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `lazada_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");
} else {
    echo "<p style='color:red;'>Unable to select CMS database `" . $db_cms . "` for Lazada Estimate Received Date columns.</p>";
}

if ($conn->select_db($db_fin)) {
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date` DATE DEFAULT NULL AFTER `remark`");
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");

    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date` DATE DEFAULT NULL AFTER `remark`");
    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");

    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date` DATE DEFAULT NULL AFTER `remark`");
    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");
} else {
    echo "<p style='color:red;'>Unable to select Finance database `" . $db_fin . "` for Estimate Received Date columns.</p>";
}
// addColumnIfMissing($conn, $db_fin, 'shopee_customer_info', 'contact_no', "ALTER TABLE `shopee_customer_info` ADD COLUMN `contact_no` VARCHAR(30) DEFAULT NULL AFTER `series`");
// addColumnIfMissing($conn, $db_fin, 'shopee_ads_topup_transaction', 'attachment', "ALTER TABLE `shopee_ads_topup_transaction` ADD COLUMN `attachment` VARCHAR(255) DEFAULT NULL AFTER `pay_meth`");

addColumnIfMissing($conn, $db_fin, 'stock_in_order', 'stock_type', "ALTER TABLE `stock_in_order` ADD COLUMN `stock_type` VARCHAR(20) NOT NULL DEFAULT 'Stock In' AFTER `attachment`");

$createStockOutBatchUsageTableSql = "CREATE TABLE IF NOT EXISTS `stock_out_batch_usage` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stock_out_order_id` INT NOT NULL,
    `stock_out_item_id` INT NOT NULL,
    `stock_in_order_id` INT NOT NULL,
    `stock_in_item_id` INT NOT NULL,
    `product_id` INT NOT NULL DEFAULT 0,
    `package_id` INT NOT NULL DEFAULT 0,
    `used_quantity` INT NOT NULL DEFAULT 0,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    KEY `idx_sobu_stock_out_order_item` (`stock_out_order_id`, `stock_out_item_id`, `status`),
    KEY `idx_sobu_stock_in_order_item` (`stock_in_order_id`, `stock_in_item_id`, `status`),
    KEY `idx_sobu_product_package_status` (`product_id`, `package_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createStockOutBatchUsageTableSql)) {
    echo "<p style='color:blue;'>Table `stock_out_batch_usage` is ready.</p>";
} else {
    echo "<p style='color:red;'>Error creating `stock_out_batch_usage`: " . $conn->error . "</p>";
}

migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'stock_out_order_id', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `stock_out_order_id` INT NOT NULL AFTER `id`", "Verified `stock_out_batch_usage` includes `stock_out_order_id`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'stock_out_item_id', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `stock_out_item_id` INT NOT NULL AFTER `stock_out_order_id`", "Verified `stock_out_batch_usage` includes `stock_out_item_id`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'stock_in_order_id', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `stock_in_order_id` INT NOT NULL AFTER `stock_out_item_id`", "Verified `stock_out_batch_usage` includes `stock_in_order_id`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'stock_in_item_id', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `stock_in_item_id` INT NOT NULL AFTER `stock_in_order_id`", "Verified `stock_out_batch_usage` includes `stock_in_item_id`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'product_id', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `product_id` INT NOT NULL DEFAULT 0 AFTER `stock_in_item_id`", "Verified `stock_out_batch_usage` includes `product_id`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'package_id', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `package_id` INT NOT NULL DEFAULT 0 AFTER `product_id`", "Verified `stock_out_batch_usage` includes `package_id`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'used_quantity', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `used_quantity` INT NOT NULL DEFAULT 0 AFTER `package_id`", "Verified `stock_out_batch_usage` includes `used_quantity`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'create_by', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `create_by` VARCHAR(30) DEFAULT NULL AFTER `used_quantity`", "Verified `stock_out_batch_usage` includes `create_by`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'create_date', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `create_by`", "Verified `stock_out_batch_usage` includes `create_date`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'create_time', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`", "Verified `stock_out_batch_usage` includes `create_time`.");
migrationEnsureColumn($conn, $db_fin, 'stock_out_batch_usage', 'status', "ALTER TABLE `stock_out_batch_usage` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `create_time`", "Verified `stock_out_batch_usage` includes `status`.");

migrationEnsureIndex($conn, $db_fin, 'stock_out_batch_usage', 'idx_sobu_stock_out_order_item', "ALTER TABLE `stock_out_batch_usage` ADD INDEX `idx_sobu_stock_out_order_item` (`stock_out_order_id`, `stock_out_item_id`, `status`)", "Verified `stock_out_batch_usage` stock out lookup index.");
migrationEnsureIndex($conn, $db_fin, 'stock_out_batch_usage', 'idx_sobu_stock_in_order_item', "ALTER TABLE `stock_out_batch_usage` ADD INDEX `idx_sobu_stock_in_order_item` (`stock_in_order_id`, `stock_in_item_id`, `status`)", "Verified `stock_out_batch_usage` stock in lookup index.");
migrationEnsureIndex($conn, $db_fin, 'stock_out_batch_usage', 'idx_sobu_product_package_status', "ALTER TABLE `stock_out_batch_usage` ADD INDEX `idx_sobu_product_package_status` (`product_id`, `package_id`, `status`)", "Verified `stock_out_batch_usage` product/package lookup index.");

// // Ensure Stock In item supports CSV products and quantities.
// alterColumnToVarcharIfInt($conn, $db_fin, 'stock_in_order_item', 'product_id', 100);
// alterColumnToVarcharIfInt($conn, $db_fin, 'stock_in_order_item', 'product_quantity', 255);
// addColumnIfMissing($conn, $db_fin, 'stock_in_order', 'attachment', "ALTER TABLE `stock_in_order` ADD COLUMN `attachment` TEXT DEFAULT NULL AFTER `stock_in_date`");
// alterColumnToTextIfVarchar($conn, $db_fin, 'stock_in_order', 'attachment');

// addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'currency', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `currency` VARCHAR(10) DEFAULT NULL AFTER `date`");
// addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'total_gst', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `total_gst` DECIMAL(10,2) DEFAULT '0.00' AFTER `currency`");
// addColumnIfMissing($conn, $db_fin, 'jt_transaction_backup', 'total_amount', "ALTER TABLE `jt_transaction_backup` ADD COLUMN `total_amount` DECIMAL(10,2) DEFAULT '0.00' AFTER `total_gst`");

// if ($conn->query("ALTER TABLE `jt_transaction_backup` ENGINE=InnoDB")) {
//     echo "<p style='color:green;'>Verified `jt_transaction_backup` ENGINE=InnoDB.</p>";
// }

// $createJtTransactionItemsTableSql = "CREATE TABLE IF NOT EXISTS `jt_transaction_items` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `transaction_id` INT NOT NULL,
//     `service_type` VARCHAR(255) DEFAULT NULL,
//     `shipments_count` INT DEFAULT '0',
//     `total_weight_kg` DECIMAL(10,2) DEFAULT '0.00',
//     `standard_charge` DECIMAL(10,2) DEFAULT '0.00',
//     `extra_charges` DECIMAL(10,2) DEFAULT '0.00',
//     `nett_charge` DECIMAL(10,2) DEFAULT '0.00',
//     INDEX `idx_jt_transaction_items_transaction_id` (`transaction_id`),
//     CONSTRAINT `fk_jt_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `jt_transaction_backup`(`id`) ON DELETE CASCADE
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// if ($conn->query($createJtTransactionItemsTableSql)) {
//     echo "<p style='color:blue;'>Table `jt_transaction_items` is ready.</p>";
// } else {
//     echo "<p style='color:red;'>Error creating `jt_transaction_items`: " . $conn->error . "</p>";
// }

// $createJtTransactionExtraChargesTableSql = "CREATE TABLE IF NOT EXISTS `jt_transaction_extra_charges` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `transaction_id` INT NOT NULL,
//     `type` VARCHAR(50) DEFAULT NULL,
//     `rate` DECIMAL(5,2) DEFAULT '0.00',
//     `amount` DECIMAL(10,2) DEFAULT '0.00',
//     `gst_paid` DECIMAL(10,2) DEFAULT '0.00',
//     INDEX `idx_jt_transaction_extra_charges_transaction_id` (`transaction_id`),
//     CONSTRAINT `fk_jt_extra_charges_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `jt_transaction_backup`(`id`) ON DELETE CASCADE
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// if ($conn->query($createJtTransactionExtraChargesTableSql)) {
//     echo "<p style='color:blue;'>Table `jt_transaction_extra_charges` is ready.</p>";
// } else {
//     echo "<p style='color:red;'>Error creating `jt_transaction_extra_charges`: " . $conn->error . "</p>";
// }

// // ==========================================
// // PIN GROUPS & CMS DATABASE UPDATE
// // ==========================================

// if ($conn->select_db($db_cms)) {
//     $createCompanyTableSql = "CREATE TABLE IF NOT EXISTS `company` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `name` VARCHAR(255) NOT NULL,
//     `code` VARCHAR(100) NOT NULL,
//     `id_no` VARCHAR(20) NOT NULL,
//     `address1` VARCHAR(60) NOT NULL,
//     `address2` VARCHAR(60) NOT NULL,
//     `address3` VARCHAR(60) NOT NULL,
//     `address4` VARCHAR(60) NOT NULL,
//     `postcode` VARCHAR(10) NOT NULL,
//     `city` VARCHAR(50) NOT NULL,
//     `state` VARCHAR(50) NOT NULL,
//     `country` CHAR(2) NOT NULL,
//     `phone1` VARCHAR(200) NOT NULL,
//     `sales_tax_no` VARCHAR(25) DEFAULT NULL,
//     `service_tax_no` VARCHAR(25) DEFAULT NULL,
//     `tin` VARCHAR(14) NOT NULL,
//     `id_type` TINYINT NOT NULL DEFAULT 0,
//     `tourism_no` VARCHAR(17) DEFAULT NULL,
//     `sic` VARCHAR(10) DEFAULT NULL,
//     `income` VARCHAR(3) DEFAULT NULL,
//     `submission_type` VARCHAR(100) NOT NULL,
//     `irbm_classification` VARCHAR(3) NOT NULL,
//     `tax_exemption_reason` VARCHAR(300) DEFAULT NULL,
//     `sql_account_id` INT NOT NULL DEFAULT 0,
//     `remark` TEXT DEFAULT NULL,
//     `create_by` VARCHAR(30) DEFAULT NULL,
//     `create_date` DATE DEFAULT NULL,
//     `create_time` TIME DEFAULT NULL,
//     `update_by` VARCHAR(30) DEFAULT NULL,
//     `update_date` DATE DEFAULT NULL,
//     `update_time` TIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A'
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

//     if ($conn->query($createCompanyTableSql)) {
//         echo "<p style='color:blue;'>Table `company` is ready.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating `company`: " . $conn->error . "</p>";
//     }

//     $createSqlAccountTableSql = "CREATE TABLE IF NOT EXISTS `sql_account` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `name` VARCHAR(255) NOT NULL,
//     `create_by` VARCHAR(30) DEFAULT NULL,
//     `create_date` DATE DEFAULT NULL,
//     `create_time` TIME DEFAULT NULL,
//     `update_by` VARCHAR(30) DEFAULT NULL,
//     `update_date` DATE DEFAULT NULL,
//     `update_time` TIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A'
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

//     if ($conn->query($createSqlAccountTableSql)) {
//         echo "<p style='color:blue;'>Table `sql_account` is ready.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating `sql_account`: " . $conn->error . "</p>";
//     }

//     $createPurchaseOrderTableSql = "CREATE TABLE IF NOT EXISTS `purchase_order` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `doc_date` DATE DEFAULT NULL,
//     `doc_no` VARCHAR(20) NOT NULL,
//     `code` VARCHAR(10) NOT NULL,
//     `company_name` VARCHAR(100) NOT NULL,
//     `description_hdr` VARCHAR(200) DEFAULT NULL,
//     `seq` INT NOT NULL DEFAULT 1,
//     `account` VARCHAR(10) DEFAULT NULL,
//     `item_code` VARCHAR(30) NOT NULL,
//     `description_dtl` VARCHAR(200) DEFAULT NULL,
//     `qty` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
//     `uom` VARCHAR(10) DEFAULT NULL,
//     `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
//     `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
//     `sql_account_id` INT NOT NULL DEFAULT 0,
//     `remark` TEXT DEFAULT NULL,
//     `create_by` VARCHAR(30) DEFAULT NULL,
//     `create_date` DATE DEFAULT NULL,
//     `create_time` TIME DEFAULT NULL,
//     `update_by` VARCHAR(30) DEFAULT NULL,
//     `update_date` DATE DEFAULT NULL,
//     `update_time` TIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A',
//     KEY `idx_po_doc_no` (`doc_no`),
//     KEY `idx_po_company_name` (`company_name`),
//     KEY `idx_po_sql_account_id` (`sql_account_id`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

//     if ($conn->query($createPurchaseOrderTableSql)) {
//         echo "<p style='color:blue;'>Table `purchase_order` is ready.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating `purchase_order`: " . $conn->error . "</p>";
//     }

    $safeCmsDb = str_replace('`', '``', $db_cms);

    $createTokenSettingTableSql = "CREATE TABLE IF NOT EXISTS `" . $safeCmsDb . "`.`token_setting` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `page_used` VARCHAR(255) NOT NULL,
    `bot_token` VARCHAR(255) NOT NULL,
    `chat_id` VARCHAR(100) DEFAULT '',
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

//     $createUserRecordLogTableSql = "CREATE TABLE IF NOT EXISTS `user_record_log` (
//     `id` INT AUTO_INCREMENT PRIMARY KEY,
//     `content` TEXT NOT NULL,
//     `attachment` VARCHAR(255) DEFAULT NULL,
//     `created_by` VARCHAR(30) DEFAULT NULL,
//     `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
//     `updated_by` VARCHAR(30) DEFAULT NULL,
//     `updated_at` DATETIME DEFAULT NULL,
//     `status` CHAR(1) NOT NULL DEFAULT 'A',
//     KEY `idx_url_created_at` (`created_at`),
//     KEY `idx_url_created_by` (`created_by`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

//     if ($conn->query($createUserRecordLogTableSql)) {
//         echo "<p style='color:blue;'>Table `user_record_log` is ready.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating `user_record_log`: " . $conn->error . "</p>";
//     }

// addColumnIfMissing($conn, $db_cms, 'token_setting', 'chat_id', "ALTER TABLE `" . $safeCmsDb . "`.`token_setting` ADD COLUMN `chat_id` VARCHAR(100) DEFAULT '' AFTER `bot_token`");
    addColumnIfMissing($conn, $db_cms, 'token_setting', 'page_used', "ALTER TABLE `" . $safeCmsDb . "`.`token_setting` ADD COLUMN `page_used` VARCHAR(255) NOT NULL DEFAULT '' AFTER `name`");
    ensureVarcharColumnLengthAtLeast($conn, $db_cms, 'token_setting', 'page_used', 255, '');

//     addColumnIfMissing($conn, $db_cms, 'user', 'main_report_supervisor', "ALTER TABLE `user` ADD COLUMN `main_report_supervisor` INT DEFAULT NULL AFTER `access_id`");
//     addColumnIfMissing($conn, $db_cms, 'user', 'second_report_supervisor', "ALTER TABLE `user` ADD COLUMN `second_report_supervisor` INT DEFAULT NULL AFTER `main_report_supervisor`");

//     addColumnIfMissing($conn, $db_cms, 'sql_account', 'status', "ALTER TABLE `sql_account` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `name`");

//     if (!columnExists($conn, $db_cms, 'company', 'id_no')) {
//         if (columnExists($conn, $db_cms, 'company', 'reg_no')) {
//             if ($conn->query("ALTER TABLE `company` CHANGE COLUMN `reg_no` `id_no` VARCHAR(20) NOT NULL DEFAULT ''")) {
//                 echo "<p style='color:blue;'>Changed `reg_no` to `id_no` in `company` table.</p>";
//             }
//         } else {
//             addColumnIfMissing($conn, $db_cms, 'company', 'id_no', "ALTER TABLE `company` ADD COLUMN `id_no` VARCHAR(20) NOT NULL DEFAULT '' AFTER `code`");
//         }
//     } else {
//         echo "<p style='color:green;'>Verified column `id_no` already exists in `company` table.</p>";
//     }

//     addColumnIfMissing($conn, $db_cms, 'company', 'address1', "ALTER TABLE `company` ADD COLUMN `address1` VARCHAR(60) NOT NULL DEFAULT '' AFTER `id_no`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'address2', "ALTER TABLE `company` ADD COLUMN `address2` VARCHAR(60) NOT NULL DEFAULT '' AFTER `address1`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'address3', "ALTER TABLE `company` ADD COLUMN `address3` VARCHAR(60) NOT NULL DEFAULT '' AFTER `address2`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'address4', "ALTER TABLE `company` ADD COLUMN `address4` VARCHAR(60) NOT NULL DEFAULT '' AFTER `address3`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'postcode', "ALTER TABLE `company` ADD COLUMN `postcode` VARCHAR(10) NOT NULL DEFAULT '' AFTER `address4`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'city', "ALTER TABLE `company` ADD COLUMN `city` VARCHAR(50) NOT NULL DEFAULT '' AFTER `postcode`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'state', "ALTER TABLE `company` ADD COLUMN `state` VARCHAR(50) NOT NULL DEFAULT '' AFTER `city`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'country', "ALTER TABLE `company` ADD COLUMN `country` CHAR(2) NOT NULL DEFAULT '' AFTER `state`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'phone1', "ALTER TABLE `company` ADD COLUMN `phone1` VARCHAR(200) NOT NULL DEFAULT '' AFTER `country`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'sales_tax_no', "ALTER TABLE `company` ADD COLUMN `sales_tax_no` VARCHAR(25) DEFAULT NULL AFTER `phone1`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'service_tax_no', "ALTER TABLE `company` ADD COLUMN `service_tax_no` VARCHAR(25) DEFAULT NULL AFTER `sales_tax_no`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'tin', "ALTER TABLE `company` ADD COLUMN `tin` VARCHAR(14) NOT NULL DEFAULT '' AFTER `service_tax_no`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'id_type', "ALTER TABLE `company` ADD COLUMN `id_type` TINYINT NOT NULL DEFAULT 0 AFTER `tin`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'tourism_no', "ALTER TABLE `company` ADD COLUMN `tourism_no` VARCHAR(17) DEFAULT NULL AFTER `id_type`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'sic', "ALTER TABLE `company` ADD COLUMN `sic` VARCHAR(10) DEFAULT NULL AFTER `tourism_no`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'income', "ALTER TABLE `company` ADD COLUMN `income` VARCHAR(3) DEFAULT NULL AFTER `sic`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'submission_type', "ALTER TABLE `company` ADD COLUMN `submission_type` VARCHAR(100) NOT NULL DEFAULT '' AFTER `income`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'irbm_classification', "ALTER TABLE `company` ADD COLUMN `irbm_classification` VARCHAR(3) NOT NULL DEFAULT '' AFTER `submission_type`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'tax_exemption_reason', "ALTER TABLE `company` ADD COLUMN `tax_exemption_reason` VARCHAR(300) DEFAULT NULL AFTER `irbm_classification`");
//     addColumnIfMissing($conn, $db_cms, 'company', 'sql_account_id', "ALTER TABLE `company` ADD COLUMN `sql_account_id` INT NOT NULL DEFAULT 0 AFTER `tax_exemption_reason`");

//     addColumnIfMissing($conn, $db_cms, 'brand', 'company', "ALTER TABLE `brand` ADD COLUMN `company` INT DEFAULT NULL AFTER `name`");

//     // Package schema backfill for import/export compatibility.
//     addColumnIfMissing($conn, $db_cms, 'package', 'item_code', "ALTER TABLE `package` ADD COLUMN `item_code` VARCHAR(100) DEFAULT NULL AFTER `name`");
//     addColumnIfMissing($conn, $db_cms, 'package', 'item_description', "ALTER TABLE `package` ADD COLUMN `item_description` TEXT DEFAULT NULL AFTER `item_code`");

//     // 1. Insert new Pin Groups (125, 126 & 127)
//     $sqlInsertPins = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
//     (125, 'Stock In', '1,2,3,4,5,6', 'Stock In Management', '1', CURDATE(), CURTIME(), 'A'),
//     (126, 'Stock Order Request', '1,2,3,4,5', 'Stock Order Request Management', '1', CURDATE(), CURTIME(), 'A'),
//     (127, 'Company', '1,2,3,4,5,6', 'Company Management', '1', CURDATE(), CURTIME(), 'A'),
//     (135, 'Purchase Order', '1,2,3,4,5,6', 'Purchase Order Management', '1', CURDATE(), CURTIME(), 'A'),
//     (136, 'User Record Log', '1,2,3,4,5,6', 'User Record Log Management', '1', CURDATE(), CURTIME(), 'A')";
    
//     if ($conn->query($sqlInsertPins)) {
//         echo "<p style='color:green;'>Verified Pin groups 125, 126, 127, 135 & 136 exist in CMS database.</p>";
//     }

//     $sqlUpdateCompanyPin = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[127:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[127:%'";
//     if ($conn->query($sqlUpdateCompanyPin)) {
//         echo "<p style='color:green;'>Verified Pin 127 access for Super Admin.</p>";
//     }

//     $sqlUpdatePurchaseOrderPin = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[135:%'";
//     if ($conn->query($sqlUpdatePurchaseOrderPin)) {
//         echo "<p style='color:green;'>Verified Pin 135 access for Super Admin.</p>";
//     }

//     // 2. Update Super Admin (id=1) safely
//     $sqlUpdateAdmin1 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[125:1,2,3,4,5,6]+[126:1,2,3,4,5]+[127:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[125:%'";
//     if ($conn->query($sqlUpdateAdmin1)) {
//         echo "<p style='color:green;'>Verified Pin 125, 126, 127 access for Super Admin.</p>";
//     }

//     $sqlUpdateAdmin1PurchaseOrder = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[135:%'";
//     if ($conn->query($sqlUpdateAdmin1PurchaseOrder)) {
//         echo "<p style='color:green;'>Verified Purchase Order Pin access for Super Admin.</p>";
//     }

//     // 3. Sync Admin Group (id=2) with Super Admin
//     $sqlUpdateAdmin2 = "UPDATE `user_group` SET `pins` = (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t) WHERE `id` = 2";
//     if ($conn->query($sqlUpdateAdmin2)) {
//         echo "<p style='color:green;'>Verified synced pins for Admin.</p>";
//     }

//     // 4. Sync Basic User Group (id=3)
//     $sqlUpdateBasic = "UPDATE `user_group` SET `pins` = (SELECT t.pins FROM (SELECT `pins` FROM `user_group` WHERE `id` = 1 LIMIT 1) AS t) WHERE `id` = 3";
//     if ($conn->query($sqlUpdateBasic)) {
//         echo "<p style='color:green;'>Verified synced pins for Basic User.</p>";
//     }

//     $sqlUpdateAdmin2_135 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[135:%'";
//     if ($conn->query($sqlUpdateAdmin2_135)) {
//         echo "<p style='color:green;'>Verified Pin 135 access for Admin.</p>";
//     }

//     $sqlUpdateAdmin1_136 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[136:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[136:%'";
//     if ($conn->query($sqlUpdateAdmin1_136)) {
//          echo "<p style='color:green;'>Verified Pin 136 access for Super Admin.</p>";
//     }

//     $sqlUpdateAdmin2_136 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[136:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[136:%'";
//     if ($conn->query($sqlUpdateAdmin2_136)) {
//          echo "<p style='color:green;'>Verified Pin 136 access for Admin.</p>";
//     }

//     $sqlUpdateBasic_135 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[135:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[135:%'";
//     if ($conn->query($sqlUpdateBasic_135)) {
//         echo "<p style='color:green;'>Verified Pin 135 access for Basic User.</p>";
//     }

//     $sqlUpdateBasic_136 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[136:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[136:%'";
//     if ($conn->query($sqlUpdateBasic_136)) {
//          echo "<p style='color:green;'>Verified Pin 136 access for Basic User.</p>";
//     }
// } else {
//     echo "<p style='color:red;'>Failed to select CMS database to update pin groups.</p>";
// }

// // --- START: SHOPEE ROLE-BASED PIN GROUPS ---
// if ($conn->select_db($db_cms)) {
//     // 1. Insert new Shopee Pin Groups (128, 129, 130)
//     $sqlInsertShopeePins = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
//     (128, 'Shopee Processing Order', '1,2,3,4,5,6', 'Basic User Shopee View', '1', CURDATE(), CURTIME(), 'A'),
//     (129, 'Shopee Verify Order', '1,2,3,4,5,6,14', 'Admin Shopee View', '1', CURDATE(), CURTIME(), 'A'),
//     (130, 'Shopee All Orders', '1,2,3,4,5,6,14,15', 'Superadmin Shopee View', '1', CURDATE(), CURTIME(), 'A')
//     ON DUPLICATE KEY UPDATE
//         `name` = VALUES(`name`),
//         `pins` = VALUES(`pins`),
//         `remark` = VALUES(`remark`),
//         `status` = 'A'";
    
//     if ($conn->query($sqlInsertShopeePins)) {
//         echo "<p style='color:green;'>Verified Shopee Role Pins (128, 129, 130) in CMS database.</p>";
//     }

//     // 2. Enforce one Shopee role pin per user group.
//     ensureSingleShopeePinForGroup($conn, 1, '[130:1,2,3,4,5,6,14,15]');
//     ensureSingleShopeePinForGroup($conn, 2, '[129:1,2,3,4,5,6,14]');
//     ensureSingleShopeePinForGroup($conn, 3, '[128:1,2,3,4,5,6]');
// }
// // --- END: SHOPEE ROLE-BASED PIN GROUPS ---

// // --- START: IMPORT SHORTCUT PIN GROUP ---
// if ($conn->select_db($db_cms)) {
//     $sqlInsertImportShortcut = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
//     (131, 'Import Shortcut', '1', 'Import Shortcut Access', '1', CURDATE(), CURTIME(), 'A')";
    
//     if ($conn->query($sqlInsertImportShortcut)) {
//         echo "<p style='color:green;'>Verified Pin group 131 (Import Shortcut) in CMS database.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating Pin group 131: " . $conn->error . "</p>";
//     }

//     $sqlUpdateAdmin1_131 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[131:1]') WHERE `id` = 1 AND `pins` NOT LIKE '%[131:%'";
//     if ($conn->query($sqlUpdateAdmin1_131)) {
//         echo "<p style='color:green;'>Verified Import Shortcut access for Super Admin.</p>";
//     }

//     $sqlUpdateAdmin2_131 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[131:1]') WHERE `id` = 2 AND `pins` NOT LIKE '%[131:%'";
//     if ($conn->query($sqlUpdateAdmin2_131)) {
//         echo "<p style='color:green;'>Verified Import Shortcut access for Admin.</p>";
//     }

//     $sqlUpdateBasic_131 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[131:1]') WHERE `id` = 3 AND `pins` NOT LIKE '%[131:%'";
//     if ($conn->query($sqlUpdateBasic_131)) {
//         echo "<p style='color:green;'>Verified Import Shortcut access for Basic User.</p>";
//     }
// }
// // --- END: IMPORT SHORTCUT PIN GROUP ---

// // --- START: SQL ACCOUNT PIN GROUP ---
// if ($conn->select_db($db_cms)) {
//     $sqlInsertSqlAccountPin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
//     (132, 'SQL Account', '1,2,3,4,5,6', 'SQL Account Management', '1', CURDATE(), CURTIME(), 'A')";

//     if ($conn->query($sqlInsertSqlAccountPin)) {
//         echo "<p style='color:green;'>Verified Pin group 132 (SQL Account) in CMS database.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating Pin group 132: " . $conn->error . "</p>";
//     }

//     $sqlUpdateAdmin1_132 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[132:%'";
//     if ($conn->query($sqlUpdateAdmin1_132)) {
//         echo "<p style='color:green;'>Verified SQL Account access for Super Admin.</p>";
//     }

//     $sqlUpdateAdmin2_132 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[132:%'";
//     if ($conn->query($sqlUpdateAdmin2_132)) {
//         echo "<p style='color:green;'>Verified SQL Account access for Admin.</p>";
//     }

//     $sqlUpdateBasic_132 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[132:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[132:%'";
//     if ($conn->query($sqlUpdateBasic_132)) {
//         echo "<p style='color:green;'>Verified SQL Account access for Basic User.</p>";
//     }
// }
// // --- END: SQL ACCOUNT PIN GROUP ---

// // --- START: TOKEN SETTING PIN GROUP ---
// if ($conn->select_db($db_cms)) {
//     $sqlInsertTokenSettingPin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
//     (133, 'Token Setting', '1,2,3,4,5,6', 'Token Setting Management', '1', CURDATE(), CURTIME(), 'A')";

//     if ($conn->query($sqlInsertTokenSettingPin)) {
//         echo "<p style='color:green;'>Verified Pin group 133 (Token Setting) in CMS database.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating Pin group 133: " . $conn->error . "</p>";
//     }

//     $sqlUpdateAdmin1_133 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]') WHERE `id` = 1 AND `pins` NOT LIKE '%[133:%'";
//     if ($conn->query($sqlUpdateAdmin1_133)) {
//         echo "<p style='color:green;'>Verified Token Setting access for Super Admin.</p>";
//     }

//     $sqlUpdateAdmin2_133 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]') WHERE `id` = 2 AND `pins` NOT LIKE '%[133:%'";
//     if ($conn->query($sqlUpdateAdmin2_133)) {
//         echo "<p style='color:green;'>Verified Token Setting access for Admin.</p>";
//     }

//     $sqlUpdateBasic_133 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[133:1,2,3,4,5,6]') WHERE `id` = 3 AND `pins` NOT LIKE '%[133:%'";
//     if ($conn->query($sqlUpdateBasic_133)) {
//         echo "<p style='color:green;'>Verified Token Setting access for Basic User.</p>";
//     }
// }
// // --- END: TOKEN SETTING PIN GROUP ---

// // --- START: USER PROFILE PIN GROUP ---
// if ($conn->select_db($db_cms)) {
//     $sqlInsertUserProfilePin = "INSERT IGNORE INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
//     (134, 'User Profile', '1,2', 'User Profile View/Edit', '1', CURDATE(), CURTIME(), 'A')";

//     if ($conn->query($sqlInsertUserProfilePin)) {
//         echo "<p style='color:green;'>Verified Pin group 134 (User Profile) in CMS database.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating Pin group 134: " . $conn->error . "</p>";
//     }

//     $sqlUpdateAdmin1_134 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[134:1,2]') WHERE `id` = 1 AND `pins` NOT LIKE '%[134:%'";
//     if ($conn->query($sqlUpdateAdmin1_134)) {
//         echo "<p style='color:green;'>Verified User Profile access for Super Admin.</p>";
//     }

//     $sqlUpdateAdmin2_134 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[134:1,2]') WHERE `id` = 2 AND `pins` NOT LIKE '%[134:%'";
//     if ($conn->query($sqlUpdateAdmin2_134)) {
//         echo "<p style='color:green;'>Verified User Profile access for Admin.</p>";
//     }

//     $sqlUpdateBasic_134 = "UPDATE `user_group` SET `pins` = CONCAT(`pins`, '+[134:1,2]') WHERE `id` = 3 AND `pins` NOT LIKE '%[134:%'";
//     if ($conn->query($sqlUpdateBasic_134)) {
//         echo "<p style='color:green;'>Verified User Profile access for Basic User.</p>";
//     }
// }
// // --- END: USER PROFILE PIN GROUP ---

// // --- START: J&T BACKUP RECORD PIN UPDATE (ID 88) ---
// if ($conn->select_db($db_cms)) {
//     $sqlUpdateJtBackupPinGroup = "UPDATE `pin_group` SET `pins` = '1,2,3,4,5,6', `status` = 'A' WHERE `id` = 88";
//     if ($conn->query($sqlUpdateJtBackupPinGroup)) {
//         echo "<p style='color:green;'>Verified Pin group 88 (J&T Backup Record) updated to 1,2,3,4,5,6.</p>";
//     } else {
//         echo "<p style='color:red;'>Failed updating Pin group 88: " . $conn->error . "</p>";
//     }

//     updatePinBlockForGroup($conn, 1, 88, '1,2,3,4,5,6');
//     updatePinBlockForGroup($conn, 2, 88, '1,2,3,4,5,6');
//     updatePinBlockForGroup($conn, 3, 88, '1,2,3,4,5,6');
// }
// // --- END: J&T BACKUP RECORD PIN UPDATE (ID 88) ---

// // --- START: FACEBOOK CUSTOMER RECORD (DEALS) PIN UPDATE (ID 75) ---
// if ($conn->select_db($db_cms)) {
//     $sqlUpdateFbDealsPinGroup = "UPDATE `pin_group` SET `pins` = '1,2,3,4,5,6', `status` = 'A' WHERE `id` = 75";
//     if ($conn->query($sqlUpdateFbDealsPinGroup)) {
//         echo "<p style='color:green;'>Verified Pin group 75 (Facebook Customer Record Deals) updated to 1,2,3,4,5,6.</p>";
//     } else {
//         echo "<p style='color:red;'>Failed updating Pin group 75: " . $conn->error . "</p>";
//     }

//     updatePinBlockForGroup($conn, 1, 75, '1,2,3,4,5,6');
//     updatePinBlockForGroup($conn, 2, 75, '1,2,3,4,5,6');
//     updatePinBlockForGroup($conn, 3, 75, '1,2,3,4,5,6');
// }
// // --- END: FACEBOOK CUSTOMER RECORD (DEALS) PIN UPDATE (ID 75) ---

// // --- START: SHOPEE CUSTOMER RECORD PIN UPDATE (ID 85) ---
// if ($conn->select_db($db_cms)) {
//     $sqlUpdateShopeeCustPinGroup = "UPDATE `pin_group` SET `pins` = '1,2,3,4,5,6', `status` = 'A' WHERE `id` = 85";
//     if ($conn->query($sqlUpdateShopeeCustPinGroup)) {
//         echo "<p style='color:green;'>Verified Pin group 85 (Shopee Customer Record) updated to 1,2,3,4,5,6.</p>";
//     } else {
//         echo "<p style='color:red;'>Failed updating Pin group 85: " . $conn->error . "</p>";
//     }

//     updatePinBlockForGroup($conn, 1, 85, '1,2,3,4,5,6');
//     updatePinBlockForGroup($conn, 2, 85, '1,2,3,4,5,6');
//     updatePinBlockForGroup($conn, 3, 85, '1,2,3,4,5,6');
// }
// // --- END: SHOPEE CUSTOMER RECORD PIN UPDATE (ID 85) ---

// echo "<h3>Stock Order Request financial schema setup complete.</h3>";

// function migrationTableExists($conn, $dbName, $tableName)
// {
//     $safeDb = $conn->real_escape_string($dbName);
//     $safeTable = $conn->real_escape_string($tableName);
//     $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' LIMIT 1";
//     $rst = $conn->query($sql);
//     return ($rst && $rst->num_rows > 0);
// }

// function migrationColumnExists($conn, $dbName, $tableName, $columnName)
// {
//     $safeDb = $conn->real_escape_string($dbName);
//     $safeTable = $conn->real_escape_string($tableName);
//     $safeColumn = $conn->real_escape_string($columnName);
//     $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND column_name='" . $safeColumn . "' LIMIT 1";
//     $rst = $conn->query($sql);
//     return ($rst && $rst->num_rows > 0);
// }

// function migrationIndexExists($conn, $dbName, $tableName, $indexName)
// {
//     $safeDb = $conn->real_escape_string($dbName);
//     $safeTable = $conn->real_escape_string($tableName);
//     $safeIndex = $conn->real_escape_string($indexName);
//     $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND index_name='" . $safeIndex . "' LIMIT 1";
//     $rst = $conn->query($sql);
//     return ($rst && $rst->num_rows > 0);
// }

function migrationTableExists($conn, $dbName, $tableName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' LIMIT 1";
    $rst = $conn->query($sql);
    return ($rst && $rst->num_rows > 0);
}

function migrationColumnExists($conn, $dbName, $tableName, $columnName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND column_name='" . $safeColumn . "' LIMIT 1";
    $rst = $conn->query($sql);
    return ($rst && $rst->num_rows > 0);
}

function migrationIndexExists($conn, $dbName, $tableName, $indexName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeIndex = $conn->real_escape_string($indexName);
    $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND index_name='" . $safeIndex . "' LIMIT 1";
    $rst = $conn->query($sql);
    return ($rst && $rst->num_rows > 0);
}

function migrationEnsureColumn($conn, $dbName, $tableName, $columnName, $alterSql, $successMessage)
{
    if (!migrationColumnExists($conn, $dbName, $tableName, $columnName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:green;'>" . $successMessage . "</p>";
        } else {
            echo "<p style='color:red;'>Failed altering `" . $tableName . "` for column `" . $columnName . "`: " . $conn->error . "</p>";
        }
    }
}

function migrationEnsureColumnAfter($conn, $dbName, $tableName, $columnName, $afterColumnName, $modifySql, $successMessage)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeColumn = $conn->real_escape_string($columnName);
    $safeAfterColumn = $conn->real_escape_string($afterColumnName);
    $sql = "SELECT c.ORDINAL_POSITION AS column_position, a.ORDINAL_POSITION AS after_position
        FROM information_schema.columns c
        LEFT JOIN information_schema.columns a
            ON a.table_schema = c.table_schema
            AND a.table_name = c.table_name
            AND a.column_name = '$safeAfterColumn'
        WHERE c.table_schema = '$safeDb'
            AND c.table_name = '$safeTable'
            AND c.column_name = '$safeColumn'
        LIMIT 1";
    $rst = $conn->query($sql);

    if (!$rst || $rst->num_rows === 0) {
        return;
    }

    $row = $rst->fetch_assoc();
    $columnPosition = isset($row['column_position']) ? (int) $row['column_position'] : 0;
    $afterPosition = isset($row['after_position']) ? (int) $row['after_position'] : 0;

    if ($columnPosition !== ($afterPosition + 1)) {
        if ($conn->query($modifySql)) {
            echo "<p style='color:blue;'>" . $successMessage . "</p>";
        } else {
            echo "<p style='color:red;'>Failed repositioning `" . $tableName . "`.`" . $columnName . "`: " . $conn->error . "</p>";
        }
    }
}

function migrationEnsureIndex($conn, $dbName, $tableName, $indexName, $alterSql, $successMessage)
{
    if (!migrationIndexExists($conn, $dbName, $tableName, $indexName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:green;'>" . $successMessage . "</p>";
        } else {
            echo "<p style='color:red;'>Failed altering `" . $tableName . "` for index `" . $indexName . "`: " . $conn->error . "</p>";
        }
    }
}

function migrationGetSettingValue($conn, $dbName, $tableName, $settingKey)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeKey = $conn->real_escape_string($settingKey);
    $sql = "SELECT `setting_value` FROM `" . $safeDb . "`.`" . $safeTable . "` WHERE `setting_key` = '" . $safeKey . "' LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    return isset($row['setting_value']) ? (string) $row['setting_value'] : null;
}

function migrationUpsertSetting($conn, $dbName, $tableName, $settingKey, $settingValue, $remark, $actorUserId)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tableName);
    $safeKey = $conn->real_escape_string($settingKey);
    $safeValue = $conn->real_escape_string($settingValue);
    $safeRemark = $conn->real_escape_string($remark);
    $safeActor = $conn->real_escape_string($actorUserId);

    $sql = "INSERT INTO `" . $safeDb . "`.`" . $safeTable . "` (`setting_key`, `setting_value`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
        VALUES ('" . $safeKey . "', '" . $safeValue . "', '" . $safeRemark . "', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `setting_value` = VALUES(`setting_value`),
            `remark` = VALUES(`remark`),
            `update_by` = '" . $safeActor . "',
            `update_date` = CURDATE(),
            `update_time` = CURTIME(),
            `status` = 'A'";

    return $conn->query($sql);
}

$customerFollowUpTable = defined('CUSTOMER_FOLLOW_UP') ? CUSTOMER_FOLLOW_UP : 'customer_follow_up';
$customerFollowUpRoundTable = defined('CUSTOMER_FOLLOW_UP_ROUND') ? CUSTOMER_FOLLOW_UP_ROUND : 'customer_follow_up_round';
$customerFollowUpActionLogTable = defined('CUSTOMER_FOLLOW_UP_ACTION_LOG') ? CUSTOMER_FOLLOW_UP_ACTION_LOG : 'customer_follow_up_action_log';
$customerFollowUpNotificationTable = defined('CUSTOMER_FOLLOW_UP_NOTIFICATION') ? CUSTOMER_FOLLOW_UP_NOTIFICATION : 'customer_follow_up_notification';

$createCustomerFollowUpSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`{$customerFollowUpTable}` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `platform` VARCHAR(30) NOT NULL DEFAULT '',
    `order_id` INT NOT NULL DEFAULT 0,
    `order_no` VARCHAR(120) DEFAULT NULL,
    `customer_id` INT NOT NULL DEFAULT 0,
    `customer_name` VARCHAR(150) DEFAULT NULL,
    `customer_username` VARCHAR(150) DEFAULT NULL,
    `package_name` VARCHAR(255) DEFAULT NULL,
    `received_date` DATE DEFAULT NULL,
    `customer_type` VARCHAR(20) NOT NULL DEFAULT '',
    `purchase_count_snapshot` INT NOT NULL DEFAULT 0,
    `current_round_no` TINYINT NOT NULL DEFAULT 1,
    `current_status` VARCHAR(30) NOT NULL DEFAULT '',
    `contact_no` VARCHAR(30) DEFAULT NULL,
    `assigned_user_id` INT DEFAULT NULL,
    `follow_up_started` CHAR(1) NOT NULL DEFAULT 'N',
    `lost_tag_added` CHAR(1) NOT NULL DEFAULT 'N',
    `lost_tag_id` INT DEFAULT NULL,
    `remark` TEXT DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    PRIMARY KEY (`id`),
    KEY `idx_cfu_platform_customer` (`platform`, `customer_id`, `status`),
    KEY `idx_cfu_order_id` (`order_id`, `status`),
    KEY `idx_cfu_assigned_user` (`assigned_user_id`, `status`),
    KEY `idx_cfu_current_status` (`current_status`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createCustomerFollowUpSql)) {
    echo "<p style='color:green;'>Verified `{$customerFollowUpTable}` is ready in `{$db_cms}`.</p>";
} else {
    echo "<p style='color:red;'>Failed creating `{$customerFollowUpTable}`: " . $conn->error . "</p>";
}

$customerFollowUpColumns = array(
    'platform' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `platform` VARCHAR(30) NOT NULL DEFAULT '' AFTER `id`",
    'order_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `order_id` INT NOT NULL DEFAULT 0 AFTER `platform`",
    'order_no' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `order_no` VARCHAR(120) DEFAULT NULL AFTER `order_id`",
    'customer_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `customer_id` INT NOT NULL DEFAULT 0 AFTER `order_no`",
    'customer_name' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `customer_name` VARCHAR(150) DEFAULT NULL AFTER `customer_id`",
    'customer_username' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `customer_username` VARCHAR(150) DEFAULT NULL AFTER `customer_name`",
    'package_name' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `package_name` VARCHAR(255) DEFAULT NULL AFTER `customer_username`",
    'received_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `received_date` DATE DEFAULT NULL AFTER `package_name`",
    'customer_type' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `customer_type` VARCHAR(20) NOT NULL DEFAULT '' AFTER `received_date`",
    'purchase_count_snapshot' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `purchase_count_snapshot` INT NOT NULL DEFAULT 0 AFTER `customer_type`",
    'current_round_no' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `current_round_no` TINYINT NOT NULL DEFAULT 1 AFTER `purchase_count_snapshot`",
    'current_status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `current_status` VARCHAR(30) NOT NULL DEFAULT '' AFTER `current_round_no`",
    'contact_no' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `contact_no` VARCHAR(30) DEFAULT NULL AFTER `current_status`",
    'assigned_user_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `assigned_user_id` INT DEFAULT NULL AFTER `contact_no`",
    'follow_up_started' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `follow_up_started` CHAR(1) NOT NULL DEFAULT 'N' AFTER `assigned_user_id`",
    'lost_tag_added' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `lost_tag_added` CHAR(1) NOT NULL DEFAULT 'N' AFTER `follow_up_started`",
    'lost_tag_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `lost_tag_id` INT DEFAULT NULL AFTER `lost_tag_added`",
    'remark' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `remark` TEXT DEFAULT NULL AFTER `lost_tag_id`",
    'create_by' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `create_by` VARCHAR(30) DEFAULT NULL AFTER `remark`",
    'create_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `create_by`",
    'create_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`",
    'update_by' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `update_by` VARCHAR(30) DEFAULT NULL AFTER `create_time`",
    'update_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `update_date` DATE DEFAULT NULL AFTER `update_by`",
    'update_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `update_time` TIME DEFAULT NULL AFTER `update_date`",
    'status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `update_time`",
);

foreach ($customerFollowUpColumns as $columnName => $alterSql) {
    migrationEnsureColumn($conn, $db_cms, $customerFollowUpTable, $columnName, $alterSql, "Verified `{$customerFollowUpTable}` includes `{$columnName}`.");
}

migrationEnsureIndex($conn, $db_cms, $customerFollowUpTable, 'idx_cfu_platform_customer', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD INDEX `idx_cfu_platform_customer` (`platform`, `customer_id`, `status`)", "Verified `{$customerFollowUpTable}` platform/customer lookup index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpTable, 'idx_cfu_order_id', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD INDEX `idx_cfu_order_id` (`order_id`, `status`)", "Verified `{$customerFollowUpTable}` order lookup index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpTable, 'idx_cfu_assigned_user', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD INDEX `idx_cfu_assigned_user` (`assigned_user_id`, `status`)", "Verified `{$customerFollowUpTable}` assigned user lookup index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpTable, 'idx_cfu_current_status', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpTable}` ADD INDEX `idx_cfu_current_status` (`current_status`, `status`)", "Verified `{$customerFollowUpTable}` status lookup index.");

$createCustomerFollowUpRoundSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`{$customerFollowUpRoundTable}` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `follow_up_id` INT NOT NULL DEFAULT 0,
    `round_no` TINYINT NOT NULL DEFAULT 1,
    `stage_no` TINYINT NOT NULL DEFAULT 1,
    `next_follow_up_date` DATE DEFAULT NULL,
    `previous_follow_up_date` DATE DEFAULT NULL,
    `attachment` VARCHAR(255) DEFAULT NULL,
    `message_shortcut_id` INT DEFAULT NULL,
    `message_shortcut_text` TEXT DEFAULT NULL,
    `contact_no` VARCHAR(30) DEFAULT NULL,
    `approval_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `approval_comment` TEXT DEFAULT NULL,
    `reject_reason` TEXT DEFAULT NULL,
    `postpone_status` VARCHAR(20) NOT NULL DEFAULT 'none',
    `postpone_reason` TEXT DEFAULT NULL,
    `postpone_reject_reason` TEXT DEFAULT NULL,
    `delay_reason` TEXT DEFAULT NULL,
    `missed_original_date` DATE DEFAULT NULL,
    `completed_date` DATE DEFAULT NULL,
    `round_status` VARCHAR(30) NOT NULL DEFAULT '',
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `approved_by` VARCHAR(30) DEFAULT NULL,
    `approved_date` DATE DEFAULT NULL,
    `approved_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    PRIMARY KEY (`id`),
    KEY `idx_cfur_follow_up_id` (`follow_up_id`, `status`),
    KEY `idx_cfur_round_lookup` (`follow_up_id`, `round_no`, `status`),
    KEY `idx_cfur_next_follow_up_date` (`next_follow_up_date`, `status`),
    KEY `idx_cfur_approval_status` (`approval_status`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createCustomerFollowUpRoundSql)) {
    echo "<p style='color:green;'>Verified `{$customerFollowUpRoundTable}` is ready in `{$db_cms}`.</p>";
} else {
    echo "<p style='color:red;'>Failed creating `{$customerFollowUpRoundTable}`: " . $conn->error . "</p>";
}

$customerFollowUpRoundColumns = array(
    'follow_up_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `follow_up_id` INT NOT NULL DEFAULT 0 AFTER `id`",
    'round_no' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `round_no` TINYINT NOT NULL DEFAULT 1 AFTER `follow_up_id`",
    'stage_no' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `stage_no` TINYINT NOT NULL DEFAULT 1 AFTER `round_no`",
    'next_follow_up_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `next_follow_up_date` DATE DEFAULT NULL AFTER `stage_no`",
    'previous_follow_up_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `previous_follow_up_date` DATE DEFAULT NULL AFTER `next_follow_up_date`",
    'attachment' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `attachment` VARCHAR(255) DEFAULT NULL AFTER `previous_follow_up_date`",
    'message_shortcut_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `message_shortcut_id` INT DEFAULT NULL AFTER `attachment`",
    'message_shortcut_text' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `message_shortcut_text` TEXT DEFAULT NULL AFTER `message_shortcut_id`",
    'contact_no' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `contact_no` VARCHAR(30) DEFAULT NULL AFTER `message_shortcut_text`",
    'approval_status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `approval_status` VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER `contact_no`",
    'approval_comment' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `approval_comment` TEXT DEFAULT NULL AFTER `approval_status`",
    'reject_reason' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `reject_reason` TEXT DEFAULT NULL AFTER `approval_comment`",
    'postpone_status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `postpone_status` VARCHAR(20) NOT NULL DEFAULT 'none' AFTER `reject_reason`",
    'postpone_reason' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `postpone_reason` TEXT DEFAULT NULL AFTER `postpone_status`",
    'postpone_reject_reason' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `postpone_reject_reason` TEXT DEFAULT NULL AFTER `postpone_reason`",
    'delay_reason' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `delay_reason` TEXT DEFAULT NULL AFTER `postpone_reject_reason`",
    'missed_original_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `missed_original_date` DATE DEFAULT NULL AFTER `delay_reason`",
    'completed_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `completed_date` DATE DEFAULT NULL AFTER `missed_original_date`",
    'round_status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `round_status` VARCHAR(30) NOT NULL DEFAULT '' AFTER `completed_date`",
    'create_by' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `create_by` VARCHAR(30) DEFAULT NULL AFTER `round_status`",
    'create_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `create_by`",
    'create_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`",
    'approved_by' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `approved_by` VARCHAR(30) DEFAULT NULL AFTER `create_time`",
    'approved_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `approved_date` DATE DEFAULT NULL AFTER `approved_by`",
    'approved_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `approved_time` TIME DEFAULT NULL AFTER `approved_date`",
    'update_by' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `update_by` VARCHAR(30) DEFAULT NULL AFTER `approved_time`",
    'update_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `update_date` DATE DEFAULT NULL AFTER `update_by`",
    'update_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `update_time` TIME DEFAULT NULL AFTER `update_date`",
    'status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `update_time`",
);

foreach ($customerFollowUpRoundColumns as $columnName => $alterSql) {
    migrationEnsureColumn($conn, $db_cms, $customerFollowUpRoundTable, $columnName, $alterSql, "Verified `{$customerFollowUpRoundTable}` includes `{$columnName}`.");
}

migrationEnsureIndex($conn, $db_cms, $customerFollowUpRoundTable, 'idx_cfur_follow_up_id', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD INDEX `idx_cfur_follow_up_id` (`follow_up_id`, `status`)", "Verified `{$customerFollowUpRoundTable}` follow-up lookup index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpRoundTable, 'idx_cfur_round_lookup', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD INDEX `idx_cfur_round_lookup` (`follow_up_id`, `round_no`, `status`)", "Verified `{$customerFollowUpRoundTable}` follow-up round lookup index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpRoundTable, 'idx_cfur_next_follow_up_date', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD INDEX `idx_cfur_next_follow_up_date` (`next_follow_up_date`, `status`)", "Verified `{$customerFollowUpRoundTable}` next follow-up date index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpRoundTable, 'idx_cfur_approval_status', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpRoundTable}` ADD INDEX `idx_cfur_approval_status` (`approval_status`, `status`)", "Verified `{$customerFollowUpRoundTable}` approval status index.");

$createCustomerFollowUpActionLogSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`{$customerFollowUpActionLogTable}` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `follow_up_id` INT NOT NULL DEFAULT 0,
    `round_id` INT DEFAULT NULL,
    `action_type` VARCHAR(50) NOT NULL DEFAULT '',
    `action_by` VARCHAR(30) DEFAULT NULL,
    `action_date` DATE DEFAULT NULL,
    `action_time` TIME DEFAULT NULL,
    `old_value` LONGTEXT DEFAULT NULL,
    `new_value` LONGTEXT DEFAULT NULL,
    `remark` TEXT DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    PRIMARY KEY (`id`),
    KEY `idx_cfual_follow_up_id` (`follow_up_id`, `status`),
    KEY `idx_cfual_round_id` (`round_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createCustomerFollowUpActionLogSql)) {
    echo "<p style='color:green;'>Verified `{$customerFollowUpActionLogTable}` is ready in `{$db_cms}`.</p>";
} else {
    echo "<p style='color:red;'>Failed creating `{$customerFollowUpActionLogTable}`: " . $conn->error . "</p>";
}

$customerFollowUpActionLogColumns = array(
    'follow_up_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `follow_up_id` INT NOT NULL DEFAULT 0 AFTER `id`",
    'round_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `round_id` INT DEFAULT NULL AFTER `follow_up_id`",
    'action_type' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `action_type` VARCHAR(50) NOT NULL DEFAULT '' AFTER `round_id`",
    'action_by' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `action_by` VARCHAR(30) DEFAULT NULL AFTER `action_type`",
    'action_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `action_date` DATE DEFAULT NULL AFTER `action_by`",
    'action_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `action_time` TIME DEFAULT NULL AFTER `action_date`",
    'old_value' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `old_value` LONGTEXT DEFAULT NULL AFTER `action_time`",
    'new_value' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `new_value` LONGTEXT DEFAULT NULL AFTER `old_value`",
    'remark' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `remark` TEXT DEFAULT NULL AFTER `new_value`",
    'status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `remark`",
);

foreach ($customerFollowUpActionLogColumns as $columnName => $alterSql) {
    migrationEnsureColumn($conn, $db_cms, $customerFollowUpActionLogTable, $columnName, $alterSql, "Verified `{$customerFollowUpActionLogTable}` includes `{$columnName}`.");
}

migrationEnsureIndex($conn, $db_cms, $customerFollowUpActionLogTable, 'idx_cfual_follow_up_id', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD INDEX `idx_cfual_follow_up_id` (`follow_up_id`, `status`)", "Verified `{$customerFollowUpActionLogTable}` follow-up action index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpActionLogTable, 'idx_cfual_round_id', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpActionLogTable}` ADD INDEX `idx_cfual_round_id` (`round_id`, `status`)", "Verified `{$customerFollowUpActionLogTable}` round action index.");

$createCustomerFollowUpNotificationSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`{$customerFollowUpNotificationTable}` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `follow_up_id` INT NOT NULL DEFAULT 0,
    `round_id` INT DEFAULT NULL,
    `notify_user_id` INT NOT NULL DEFAULT 0,
    `notify_role` VARCHAR(20) DEFAULT NULL,
    `notification_type` VARCHAR(50) NOT NULL DEFAULT '',
    `title` VARCHAR(255) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `is_read` CHAR(1) NOT NULL DEFAULT 'N',
    `read_date` DATE DEFAULT NULL,
    `read_time` TIME DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    PRIMARY KEY (`id`),
    KEY `idx_cfun_follow_up_id` (`follow_up_id`, `status`),
    KEY `idx_cfun_round_id` (`round_id`, `status`),
    KEY `idx_cfun_notify_user_read` (`notify_user_id`, `is_read`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createCustomerFollowUpNotificationSql)) {
    echo "<p style='color:green;'>Verified `{$customerFollowUpNotificationTable}` is ready in `{$db_cms}`.</p>";
} else {
    echo "<p style='color:red;'>Failed creating `{$customerFollowUpNotificationTable}`: " . $conn->error . "</p>";
}

$customerFollowUpNotificationColumns = array(
    'follow_up_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `follow_up_id` INT NOT NULL DEFAULT 0 AFTER `id`",
    'round_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `round_id` INT DEFAULT NULL AFTER `follow_up_id`",
    'notify_user_id' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `notify_user_id` INT NOT NULL DEFAULT 0 AFTER `round_id`",
    'notify_role' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `notify_role` VARCHAR(20) DEFAULT NULL AFTER `notify_user_id`",
    'notification_type' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `notification_type` VARCHAR(50) NOT NULL DEFAULT '' AFTER `notify_role`",
    'title' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `notification_type`",
    'message' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `message` TEXT DEFAULT NULL AFTER `title`",
    'is_read' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `is_read` CHAR(1) NOT NULL DEFAULT 'N' AFTER `message`",
    'read_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `read_date` DATE DEFAULT NULL AFTER `is_read`",
    'read_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `read_time` TIME DEFAULT NULL AFTER `read_date`",
    'create_date' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `read_time`",
    'create_time' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`",
    'status' => "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `create_time`",
);

foreach ($customerFollowUpNotificationColumns as $columnName => $alterSql) {
    migrationEnsureColumn($conn, $db_cms, $customerFollowUpNotificationTable, $columnName, $alterSql, "Verified `{$customerFollowUpNotificationTable}` includes `{$columnName}`.");
}

migrationEnsureIndex($conn, $db_cms, $customerFollowUpNotificationTable, 'idx_cfun_follow_up_id', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD INDEX `idx_cfun_follow_up_id` (`follow_up_id`, `status`)", "Verified `{$customerFollowUpNotificationTable}` follow-up notification index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpNotificationTable, 'idx_cfun_round_id', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD INDEX `idx_cfun_round_id` (`round_id`, `status`)", "Verified `{$customerFollowUpNotificationTable}` round notification index.");
migrationEnsureIndex($conn, $db_cms, $customerFollowUpNotificationTable, 'idx_cfun_notify_user_read', "ALTER TABLE `{$db_cms}`.`{$customerFollowUpNotificationTable}` ADD INDEX `idx_cfun_notify_user_read` (`notify_user_id`, `is_read`, `status`)", "Verified `{$customerFollowUpNotificationTable}` user read-state notification index.");

$systemAlertMessageTable = defined('SYSTEM_ALERT_MESSAGE') ? SYSTEM_ALERT_MESSAGE : 'system_alert_message';
$createSystemAlertMessageSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`{$systemAlertMessageTable}` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `module_key` VARCHAR(80) NOT NULL DEFAULT '',
    `notification_type` VARCHAR(80) NOT NULL DEFAULT '',
    `target_user_id` INT DEFAULT NULL,
    `target_user_group_id` INT DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `action_url` VARCHAR(255) DEFAULT NULL,
    `action_label` VARCHAR(120) DEFAULT NULL,
    `related_table` VARCHAR(120) DEFAULT NULL,
    `related_id` INT DEFAULT NULL,
    `related_platform` VARCHAR(30) DEFAULT NULL,
    `is_read` CHAR(1) NOT NULL DEFAULT 'N',
    `read_date` DATE DEFAULT NULL,
    `read_time` TIME DEFAULT NULL,
    `display_date` DATE DEFAULT NULL,
    `expire_date` DATE DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    PRIMARY KEY (`id`),
    KEY `idx_sam_target_user_read_status` (`target_user_id`, `is_read`, `status`),
    KEY `idx_sam_module_status` (`module_key`, `status`),
    KEY `idx_sam_display_date` (`display_date`),
    KEY `idx_sam_related_record` (`related_table`, `related_id`),
    KEY `idx_sam_notification_type` (`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createSystemAlertMessageSql)) {
    echo "<p style='color:green;'>Verified `{$systemAlertMessageTable}` is ready in `{$db_cms}`.</p>";
} else {
    echo "<p style='color:red;'>Failed creating `{$systemAlertMessageTable}`: " . $conn->error . "</p>";
}

$systemAlertMessageColumns = array(
    'module_key' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `module_key` VARCHAR(80) NOT NULL DEFAULT '' AFTER `id`",
    'notification_type' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `notification_type` VARCHAR(80) NOT NULL DEFAULT '' AFTER `module_key`",
    'target_user_id' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `target_user_id` INT DEFAULT NULL AFTER `notification_type`",
    'target_user_group_id' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `target_user_group_id` INT DEFAULT NULL AFTER `target_user_id`",
    'title' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `target_user_group_id`",
    'message' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `message` TEXT DEFAULT NULL AFTER `title`",
    'action_url' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `action_url` VARCHAR(255) DEFAULT NULL AFTER `message`",
    'action_label' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `action_label` VARCHAR(120) DEFAULT NULL AFTER `action_url`",
    'related_table' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `related_table` VARCHAR(120) DEFAULT NULL AFTER `action_label`",
    'related_id' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `related_id` INT DEFAULT NULL AFTER `related_table`",
    'related_platform' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `related_platform` VARCHAR(30) DEFAULT NULL AFTER `related_id`",
    'is_read' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `is_read` CHAR(1) NOT NULL DEFAULT 'N' AFTER `related_platform`",
    'read_date' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `read_date` DATE DEFAULT NULL AFTER `is_read`",
    'read_time' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `read_time` TIME DEFAULT NULL AFTER `read_date`",
    'display_date' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `display_date` DATE DEFAULT NULL AFTER `read_time`",
    'expire_date' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `expire_date` DATE DEFAULT NULL AFTER `display_date`",
    'create_by' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `create_by` VARCHAR(30) DEFAULT NULL AFTER `expire_date`",
    'create_date' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `create_by`",
    'create_time' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`",
    'status' => "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `create_time`",
);

foreach ($systemAlertMessageColumns as $columnName => $alterSql) {
    migrationEnsureColumn($conn, $db_cms, $systemAlertMessageTable, $columnName, $alterSql, "Verified `{$systemAlertMessageTable}` includes `{$columnName}`.");
}

migrationEnsureIndex($conn, $db_cms, $systemAlertMessageTable, 'idx_sam_target_user_read_status', "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD INDEX `idx_sam_target_user_read_status` (`target_user_id`, `is_read`, `status`)", "Verified `{$systemAlertMessageTable}` target user read-state index.");
migrationEnsureIndex($conn, $db_cms, $systemAlertMessageTable, 'idx_sam_module_status', "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD INDEX `idx_sam_module_status` (`module_key`, `status`)", "Verified `{$systemAlertMessageTable}` module index.");
migrationEnsureIndex($conn, $db_cms, $systemAlertMessageTable, 'idx_sam_display_date', "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD INDEX `idx_sam_display_date` (`display_date`)", "Verified `{$systemAlertMessageTable}` display date index.");
migrationEnsureIndex($conn, $db_cms, $systemAlertMessageTable, 'idx_sam_related_record', "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD INDEX `idx_sam_related_record` (`related_table`, `related_id`)", "Verified `{$systemAlertMessageTable}` related record index.");
migrationEnsureIndex($conn, $db_cms, $systemAlertMessageTable, 'idx_sam_notification_type', "ALTER TABLE `{$db_cms}`.`{$systemAlertMessageTable}` ADD INDEX `idx_sam_notification_type` (`notification_type`)", "Verified `{$systemAlertMessageTable}` notification type index.");

function removePinAccessIds($pinList, $removeIds = array(7, 8))
{
    $values = array_filter(array_map('trim', explode(',', (string) $pinList)), 'strlen');
    $removeLookup = array_fill_keys(array_map('strval', $removeIds), true);
    $filtered = array();

    foreach ($values as $value) {
        if (!isset($removeLookup[(string) $value])) {
            $filtered[] = $value;
        }
    }

    return implode(',', $filtered);
}

function removeAccessFromPinBlock($allPins, $targetPinId, $removeIds = array(7, 8))
{
    $targetPinId = (string) ((int) $targetPinId);
    $entries = array_filter(array_map('trim', explode('+', (string) $allPins)), 'strlen');
    $rebuilt = array();

    foreach ($entries as $entry) {
        $entry = trim($entry, '[]');
        $parts = explode(':', $entry, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $pinId = trim($parts[0]);
        $accessList = trim($parts[1]);

        if ($pinId === $targetPinId) {
            $accessList = removePinAccessIds($accessList, $removeIds);
        }

        $rebuilt[] = '[' . $pinId . ':' . $accessList . ']';
    }

    return implode('+', $rebuilt);
}

function removePinBlockById($allPins, $targetPinId)
{
    $targetPinId = (string) ((int) $targetPinId);
    $entries = array_filter(array_map('trim', explode('+', (string) $allPins)), 'strlen');
    $rebuilt = array();

    foreach ($entries as $entry) {
        $trimmedEntry = trim($entry, '[]');
        $parts = explode(':', $trimmedEntry, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $pinId = trim($parts[0]);
        if ($pinId === $targetPinId) {
            continue;
        }

        $rebuilt[] = '[' . $pinId . ':' . trim($parts[1]) . ']';
    }

    return implode('+', $rebuilt);
}

function addPinAccessIds($pinList, $addIds = array(6))
{
    $values = array_filter(array_map('trim', explode(',', (string) $pinList)), 'strlen');
    foreach ((array) $addIds as $addId) {
        $addValue = (string) ((int) $addId);
        if ($addValue !== '' && !in_array($addValue, $values, true)) {
            $values[] = $addValue;
        }
    }

    return implode(',', $values);
}

function addAccessToPinBlock($allPins, $targetPinId, $addIds = array(6))
{
    $targetPinId = (string) ((int) $targetPinId);
    $entries = array_filter(array_map('trim', explode('+', (string) $allPins)), 'strlen');
    $rebuilt = array();
    $found = false;

    foreach ($entries as $entry) {
        $entry = trim($entry, '[]');
        $parts = explode(':', $entry, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $pinId = trim($parts[0]);
        $accessList = trim($parts[1]);

        if ($pinId === $targetPinId) {
            $accessList = addPinAccessIds($accessList, $addIds);
            $found = true;
        }

        $rebuilt[] = '[' . $pinId . ':' . $accessList . ']';
    }

    if (!$found) {
        $rebuilt[] = '[' . $targetPinId . ':' . addPinAccessIds('', $addIds) . ']';
    }

    return implode('+', $rebuilt);
}

function setPinBlockAccess($allPins, $targetPinId, $accessIds)
{
    $targetPinId = (string) ((int) $targetPinId);
    $entries = array_filter(array_map('trim', explode('+', (string) $allPins)), 'strlen');
    $rebuilt = array();
    $found = false;
    $accessValues = array();

    foreach ((array) $accessIds as $accessId) {
        $accessValue = (string) ((int) $accessId);
        if ($accessValue !== '' && !in_array($accessValue, $accessValues, true)) {
            $accessValues[] = $accessValue;
        }
    }

    $accessList = implode(',', $accessValues);

    foreach ($entries as $entry) {
        $entry = trim($entry, '[]');
        $parts = explode(':', $entry, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $pinId = trim($parts[0]);
        if ($pinId === $targetPinId) {
            if ($found) {
                continue;
            }

            $rebuilt[] = '[' . $pinId . ':' . $accessList . ']';
            $found = true;
            continue;
        }

        $rebuilt[] = '[' . $pinId . ':' . trim($parts[1]) . ']';
    }

    if (!$found) {
        $rebuilt[] = '[' . $targetPinId . ':' . $accessList . ']';
    }

    return implode('+', $rebuilt);
}

function pinBlockHasAccessId($allPins, $targetPinId, $accessId)
{
    $targetPinId = (string) ((int) $targetPinId);
    $accessId = (string) ((int) $accessId);
    $entries = array_filter(array_map('trim', explode('+', (string) $allPins)), 'strlen');

    foreach ($entries as $entry) {
        $entry = trim($entry, '[]');
        $parts = explode(':', $entry, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $pinId = trim($parts[0]);
        if ($pinId !== $targetPinId) {
            continue;
        }

        $accessValues = array_filter(array_map('trim', explode(',', trim($parts[1]))), 'strlen');
        return in_array($accessId, $accessValues, true);
    }

    return false;
}

// if ($conn->select_db($db_fin)) {
//     if ($conn->query("DROP TABLE IF EXISTS `" . USER_RECORD_LOG . "`")) {
//         echo "<p style='color:green;'>Verified dropped `" . USER_RECORD_LOG . "` from financial database.</p>";
//     } else {
//         echo "<p style='color:red;'>Failed dropping `" . USER_RECORD_LOG . "` from financial database: " . $conn->error . "</p>";
//     }
// } else {
//     echo "<p style='color:red;'>Failed selecting financial database for user record log cleanup.</p>";
// }

// if ($conn->select_db($db_cms)) {
//     $createUserRecordLogTableSql = "CREATE TABLE IF NOT EXISTS `" . USER_RECORD_LOG . "` (
//         `id` INT AUTO_INCREMENT PRIMARY KEY,
//         `cust_id` INT DEFAULT NULL,
//         `shopee_cust_id` INT DEFAULT NULL,
//         `facebook_cust_id` INT DEFAULT NULL,
//         `website_cust_id` INT DEFAULT NULL,
//         `lazada_cust_id` INT DEFAULT NULL,
//         `urbanism_member_id` INT DEFAULT NULL,
//         `content` TEXT NOT NULL,
//         `attachment` VARCHAR(255) DEFAULT NULL,
//         `created_by` VARCHAR(30) DEFAULT NULL,
//         `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
//         `updated_by` VARCHAR(30) DEFAULT NULL,
//         `updated_at` DATETIME DEFAULT NULL,
//         `status` CHAR(1) NOT NULL DEFAULT 'A',
//         KEY `idx_url_created_at` (`created_at`),
//         KEY `idx_url_created_by` (`created_by`),
//         KEY `idx_url_cust_id` (`cust_id`),
//         KEY `idx_url_shopee_cust_id` (`shopee_cust_id`),
//         KEY `idx_url_facebook_cust_id` (`facebook_cust_id`),
//         KEY `idx_url_website_cust_id` (`website_cust_id`),
//         KEY `idx_url_lazada_cust_id` (`lazada_cust_id`),
//         KEY `idx_url_urbanism_member_id` (`urbanism_member_id`)
//     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

//     if ($conn->query($createUserRecordLogTableSql)) {
//         echo "<p style='color:blue;'>Table `" . USER_RECORD_LOG . "` is ready in CMS database.</p>";
//     } else {
//         echo "<p style='color:red;'>Error creating `" . USER_RECORD_LOG . "` in CMS database: " . $conn->error . "</p>";
//     }

//     $userRecordLogCustomerColumns = array(
//         'cust_id' => 'id',
//         'shopee_cust_id' => 'id',
//         'facebook_cust_id' => 'shopee_cust_id',
//         'website_cust_id' => 'facebook_cust_id',
//         'lazada_cust_id' => 'website_cust_id',
//         'urbanism_member_id' => 'lazada_cust_id',
//     );
//     foreach ($userRecordLogCustomerColumns as $customerColumn => $afterColumn) {
//         if (!migrationColumnExists($conn, $db_cms, USER_RECORD_LOG, $customerColumn)) {
//             if ($conn->query("ALTER TABLE `" . USER_RECORD_LOG . "` ADD COLUMN `" . $customerColumn . "` INT DEFAULT NULL AFTER `" . $afterColumn . "`")) {
//                 echo "<p style='color:blue;'>Added column `" . $customerColumn . "` to `" . USER_RECORD_LOG . "` in CMS database.</p>";
//             } else {
//                 echo "<p style='color:red;'>Failed adding `" . $customerColumn . "` to `" . USER_RECORD_LOG . "` in CMS database: " . $conn->error . "</p>";
//             }
//         } else {
//             echo "<p style='color:green;'>Verified column `" . $customerColumn . "` already exists in `" . USER_RECORD_LOG . "` in CMS database.</p>";
//         }

//         $customerIndex = 'idx_url_' . $customerColumn;
//         if (!migrationIndexExists($conn, $db_cms, USER_RECORD_LOG, $customerIndex)) {
//             if ($conn->query("ALTER TABLE `" . USER_RECORD_LOG . "` ADD INDEX `" . $customerIndex . "` (`" . $customerColumn . "`)")) {
//                 echo "<p style='color:blue;'>Added index `" . $customerIndex . "` to `" . USER_RECORD_LOG . "` in CMS database.</p>";
//             } else {
//                 echo "<p style='color:red;'>Failed adding index `" . $customerIndex . "` to `" . USER_RECORD_LOG . "` in CMS database: " . $conn->error . "</p>";
//             }
//         } else {
//             echo "<p style='color:green;'>Verified index `" . $customerIndex . "` already exists in `" . USER_RECORD_LOG . "` in CMS database.</p>";
//         }
//     }

//     if (migrationColumnExists($conn, $db_cms, USER_RECORD_LOG, 'customer_id') && migrationColumnExists($conn, $db_cms, USER_RECORD_LOG, 'shopee_cust_id')) {
//         if ($conn->query("UPDATE `" . USER_RECORD_LOG . "` SET `shopee_cust_id` = `customer_id` WHERE IFNULL(`shopee_cust_id`,0)=0 AND IFNULL(`customer_id`,0)>0")) {
//             echo "<p style='color:green;'>Verified migrated legacy `customer_id` values into `shopee_cust_id` in `" . USER_RECORD_LOG . "`.</p>";
//         } else {
//             echo "<p style='color:red;'>Failed migrating legacy `customer_id` values into `shopee_cust_id` in `" . USER_RECORD_LOG . "`: " . $conn->error . "</p>";
//         }
//     }

//     if (migrationColumnExists($conn, $db_cms, USER_RECORD_LOG, 'cust_id') && migrationColumnExists($conn, $db_cms, USER_RECORD_LOG, 'shopee_cust_id')) {
//         if ($conn->query("UPDATE `" . USER_RECORD_LOG . "` SET `shopee_cust_id` = `cust_id` WHERE IFNULL(`shopee_cust_id`,0)=0 AND IFNULL(`cust_id`,0)>0")) {
//             echo "<p style='color:green;'>Verified migrated legacy `cust_id` values into `shopee_cust_id` in `" . USER_RECORD_LOG . "`.</p>";
//         } else {
//             echo "<p style='color:red;'>Failed migrating legacy `cust_id` values into `shopee_cust_id` in `" . USER_RECORD_LOG . "`: " . $conn->error . "</p>";
//         }
//     }

// } else {
//     echo "<p style='color:red;'>Failed selecting CMS database for user record log migration.</p>";
// }

if ($conn->select_db($db_cms)) {
    $createTaskProjectSql = "CREATE TABLE IF NOT EXISTS `" . TASK_PROJECT . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(180) NOT NULL,
        `owner_user_id` INT DEFAULT NULL,
        `board_background_color` VARCHAR(20) NOT NULL DEFAULT '#f4f7fb',
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_project_owner` (`owner_user_id`),
        KEY `idx_task_project_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $createCustomerLevelTableSql = "CREATE TABLE IF NOT EXISTS `" . CUS_LEVEL . "` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        `colorCode` VARCHAR(12) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        `create_by` VARCHAR(255) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A',
        `purchaseAmountFrom` DECIMAL(10,2) DEFAULT NULL,
        `purchaseAmountUntil` DECIMAL(10,2) DEFAULT NULL,
        `currency` INT DEFAULT NULL,
        `remark` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1";

    if ($conn->query($createCustomerLevelTableSql)) {
        echo "<p style='color:green;'>Verified table `" . CUS_LEVEL . "` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CUS_LEVEL . "`: " . $conn->error . "</p>";
    }

    $createCustomerRepeatTableSql = "CREATE TABLE IF NOT EXISTS `" . CUS_REPEAT . "` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        `colorCode` VARCHAR(12) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        `create_by` VARCHAR(255) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'A',
        `orderFrequencyFrom` DECIMAL(10,2) DEFAULT NULL,
        `orderFrequencyUntil` DECIMAL(10,2) DEFAULT NULL,
        `remark` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1";

    if ($conn->query($createCustomerRepeatTableSql)) {
        echo "<p style='color:green;'>Verified table `" . CUS_REPEAT . "` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CUS_REPEAT . "`: " . $conn->error . "</p>";
    }

    $createMessageShortcutsTableSql = "CREATE TABLE IF NOT EXISTS `" . MESSAGE_SHORTCUTS . "` ( 
        `id` INT NOT NULL AUTO_INCREMENT,
        `shortcuts_tag` VARCHAR(120) NOT NULL,
        `shortcuts_message` MEDIUMTEXT DEFAULT NULL,
        `create_by` VARCHAR(255) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(255) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        PRIMARY KEY (`id`),
        KEY `idx_message_shortcuts_tag_status` (`shortcuts_tag`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($createMessageShortcutsTableSql)) {
        echo "<p style='color:green;'>Verified table `" . MESSAGE_SHORTCUTS . "` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . MESSAGE_SHORTCUTS . "`: " . $conn->error . "</p>";
    }

    // Ensure only the shortcuts_message column uses utf8mb4_unicode_ci.
    if (columnExists($conn, $db_cms, MESSAGE_SHORTCUTS, 'shortcuts_message')) {
        $alterShortcutMessageColumnSql = "ALTER TABLE `" . MESSAGE_SHORTCUTS . "`
            MODIFY COLUMN `shortcuts_message` MEDIUMTEXT
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            DEFAULT NULL";

        if ($conn->query($alterShortcutMessageColumnSql)) {
            echo "<p style='color:green;'>Verified `" . MESSAGE_SHORTCUTS . "`.`shortcuts_message` collation is utf8mb4_unicode_ci.</p>";
        } else {
            echo "<p style='color:red;'>Failed altering `" . MESSAGE_SHORTCUTS . "`.`shortcuts_message` collation: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:orange;'>Column `" . MESSAGE_SHORTCUTS . "`.`shortcuts_message` not found. Skipped collation update.</p>";
    }

    $messageShortcutsTableName = $conn->real_escape_string(MESSAGE_SHORTCUTS);
    $messageShortcutsSchemaName = $conn->real_escape_string($db_cms);
    $messageShortcutsCollationCheckSql = "SELECT TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '" . $messageShortcutsSchemaName . "'
          AND TABLE_NAME = '" . $messageShortcutsTableName . "'
        LIMIT 1";

    $messageShortcutsCollationResult = $conn->query($messageShortcutsCollationCheckSql);

    if ($messageShortcutsCollationResult) {
        $messageShortcutsCollationRow = $messageShortcutsCollationResult->fetch_assoc();

        if ($messageShortcutsCollationRow && $messageShortcutsCollationRow['TABLE_COLLATION'] !== 'utf8mb4_unicode_ci') {
            $alterMessageShortcutsCollationSql = "ALTER TABLE `" . MESSAGE_SHORTCUTS . "`
                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

            if ($conn->query($alterMessageShortcutsCollationSql)) {
                echo "<p style='color:green;'>Verified table `" . MESSAGE_SHORTCUTS . "` collation is utf8mb4_unicode_ci.</p>";
            } else {
                echo "<p style='color:red;'>Failed altering `" . MESSAGE_SHORTCUTS . "` collation: " . $conn->error . "</p>";
            }
        } elseif ($messageShortcutsCollationRow) {
            echo "<p style='color:green;'>Verified table `" . MESSAGE_SHORTCUTS . "` collation is utf8mb4_unicode_ci.</p>";
        } else {
            echo "<p style='color:red;'>Failed verifying `" . MESSAGE_SHORTCUTS . "` collation: table metadata not found.</p>";
        }
    } else {
        echo "<p style='color:red;'>Failed checking `" . MESSAGE_SHORTCUTS . "` collation: " . $conn->error . "</p>";
    }

    if ($conn->query($createTaskProjectSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_PROJECT . "` for task projects.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_PROJECT . "`: " . $conn->error . "</p>";
    }

    $createTaskStatusSql = "CREATE TABLE IF NOT EXISTS `" . TASK_COLUMN . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `name` VARCHAR(150) NOT NULL,
        `color` VARCHAR(20) NOT NULL DEFAULT '#dfe1e6',
        `sort_order` INT NOT NULL DEFAULT 0,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_status_sort` (`sort_order`),
        KEY `idx_task_status_project_sort` (`project_id`, `status`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskStatusSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_COLUMN . "` for task statuses.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_COLUMN . "`: " . $conn->error . "</p>";
    }

    $createTaskWorkTypeSql = "CREATE TABLE IF NOT EXISTS `" . TASK_WORK_TYPE . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `name` VARCHAR(80) NOT NULL,
        `svg_icon` VARCHAR(255) DEFAULT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_work_type_project` (`project_id`, `status`, `name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskWorkTypeSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_WORK_TYPE . "` for task work types.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_WORK_TYPE . "`: " . $conn->error . "</p>";
    }

    $createTaskProjectKeySql = "CREATE TABLE IF NOT EXISTS `" . TASK_PROJECT_KEY . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `project_key` VARCHAR(20) DEFAULT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_project_key` (`project_key`),
        KEY `idx_task_project_key_project` (`project_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskProjectKeySql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_PROJECT_KEY . "` for project key settings.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_PROJECT_KEY . "`: " . $conn->error . "</p>";
    }

    $createTaskProjectItemAccessSql = "CREATE TABLE IF NOT EXISTS `" . TASK_PROJECT_ITEM_ACCESS . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `can_add` TINYINT(1) NOT NULL DEFAULT 0,
        `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
        `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
        `allowed_work_type_ids` TEXT DEFAULT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_task_project_item_access` (`project_id`, `user_id`),
        KEY `idx_task_project_item_access_user` (`user_id`, `status`),
        KEY `idx_task_project_item_access_project` (`project_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskProjectItemAccessSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_PROJECT_ITEM_ACCESS . "` for project work item access.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_PROJECT_ITEM_ACCESS . "`: " . $conn->error . "</p>";
    }

    $createTaskProjectColumnAccessSql = "CREATE TABLE IF NOT EXISTS `" . TASK_PROJECT_COLUMN_ACCESS . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `column_key` VARCHAR(80) NOT NULL,
        `can_add` TINYINT(1) NOT NULL DEFAULT 0,
        `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
        `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_task_project_column_access` (`project_id`, `user_id`, `column_key`),
        KEY `idx_task_project_column_access_user` (`user_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskProjectColumnAccessSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_PROJECT_COLUMN_ACCESS . "` for project column access.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_PROJECT_COLUMN_ACCESS . "`: " . $conn->error . "</p>";
    }

    $createTaskProjectStatusAccessSql = "CREATE TABLE IF NOT EXISTS `" . TASK_PROJECT_STATUS_ACCESS . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `from_status_id` INT NOT NULL,
        `to_status_id` INT NOT NULL,
        `can_move` TINYINT(1) NOT NULL DEFAULT 0,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_task_project_status_access` (`project_id`, `user_id`, `from_status_id`, `to_status_id`),
        KEY `idx_task_project_status_access_user` (`user_id`, `status`),
        KEY `idx_task_project_status_access_project` (`project_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskProjectStatusAccessSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_PROJECT_STATUS_ACCESS . "` for project status access.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_PROJECT_STATUS_ACCESS . "`: " . $conn->error . "</p>";
    }

    $createTaskItemSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `column_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `project_key_id` INT DEFAULT NULL,
        `work_type_id` INT DEFAULT NULL,
        `assignee_user_id` INT DEFAULT NULL,
        `reporter_user_id` INT DEFAULT NULL,
        `priority` VARCHAR(20) NOT NULL DEFAULT 'Medium',
        `original_estimate` VARCHAR(80) DEFAULT NULL,
        `task_status` VARCHAR(80) DEFAULT NULL,
        `parent_item_id` INT DEFAULT NULL,
        `time_tracking` VARCHAR(120) DEFAULT NULL,
        `due_date` DATE DEFAULT NULL,
        `start_date` DATE DEFAULT NULL,
        `amendement_date` DATE DEFAULT NULL,
        `amendement_time` TIME DEFAULT NULL,
        `second_amendement_date` DATE DEFAULT NULL,
        `second_amendement_time` TIME DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_item_column` (`column_id`),
        KEY `idx_task_item_sort` (`sort_order`),
        KEY `idx_task_item_assignee` (`assignee_user_id`),
        KEY `idx_task_item_project_key` (`project_key_id`),
        KEY `idx_task_item_reporter` (`reporter_user_id`),
        KEY `idx_task_item_parent` (`parent_item_id`),
        KEY `idx_task_item_project` (`project_id`, `column_id`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM . "` for board tasks.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM . "`: " . $conn->error . "</p>";
    }

    $createTaskLabelSql = "CREATE TABLE IF NOT EXISTS `" . TASK_LABEL . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(120) NOT NULL,
        `color` VARCHAR(7) NOT NULL DEFAULT '#DCE8FF',
        `sort_order` INT NOT NULL DEFAULT 0,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_task_label_name` (`name`),
        KEY `idx_task_label_sort` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskLabelSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_LABEL . "` for task labels.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_LABEL . "`: " . $conn->error . "</p>";
    }

    $taskLabelColorRst = $conn->query("SHOW COLUMNS FROM `" . TASK_LABEL . "` LIKE 'color'");
    if ($taskLabelColorRst && $taskLabelColorRst->num_rows === 0) {
        if ($conn->query("ALTER TABLE `" . TASK_LABEL . "` ADD COLUMN `color` VARCHAR(7) NOT NULL DEFAULT '#DCE8FF' AFTER `name`")) {
            echo "<p style='color:green;'>Added `color` column to `" . TASK_LABEL . "`.</p>";
        } else {
            echo "<p style='color:red;'>Failed adding `color` to `" . TASK_LABEL . "`: " . $conn->error . "</p>";
        }
    }

    $createTaskStatusLabelSql = "CREATE TABLE IF NOT EXISTS `" . TASK_STATUS_LABEL . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(120) NOT NULL,
        `color` VARCHAR(7) NOT NULL DEFAULT '#DCE8FF',
        `sort_order` INT NOT NULL DEFAULT 0,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_task_status_label_name` (`name`),
        KEY `idx_task_status_label_sort` (`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskStatusLabelSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_STATUS_LABEL . "` for task status labels.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_STATUS_LABEL . "`: " . $conn->error . "</p>";
    }

    $taskStatusLabelColorRst = $conn->query("SHOW COLUMNS FROM `" . TASK_STATUS_LABEL . "` LIKE 'color'");
    if ($taskStatusLabelColorRst && $taskStatusLabelColorRst->num_rows === 0) {
        if ($conn->query("ALTER TABLE `" . TASK_STATUS_LABEL . "` ADD COLUMN `color` VARCHAR(7) NOT NULL DEFAULT '#DCE8FF' AFTER `name`")) {
            echo "<p style='color:green;'>Added `color` column to `" . TASK_STATUS_LABEL . "`.</p>";
        } else {
            echo "<p style='color:red;'>Failed adding `color` to `" . TASK_STATUS_LABEL . "`: " . $conn->error . "</p>";
        }
    }

    $createTaskItemLabelSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_LABEL . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `label_id` INT NOT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_task_item_label` (`item_id`,`label_id`),
        KEY `idx_task_item_label_item` (`item_id`),
        KEY `idx_task_item_label_label` (`label_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemLabelSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_LABEL . "` for task item labels.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_LABEL . "`: " . $conn->error . "</p>";
    }

    $createTaskItemAttachmentSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_ATTACHMENT . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_size` BIGINT DEFAULT NULL,
        `mime_type` VARCHAR(120) DEFAULT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_item_attachment_item` (`item_id`),
        KEY `idx_task_item_attachment_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemAttachmentSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_ATTACHMENT . "` for task item attachments.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_ATTACHMENT . "`: " . $conn->error . "</p>";
    }

    $createTaskItemUrlSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_URL . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `url` VARCHAR(500) NOT NULL,
        `link_text` VARCHAR(255) DEFAULT NULL,
        `title` VARCHAR(255) DEFAULT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_item_url_item` (`item_id`),
        KEY `idx_task_item_url_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemUrlSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_URL . "` for task item web links.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_URL . "`: " . $conn->error . "</p>";
    }

    $createTaskItemRelationSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_RELATION . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `parent_board_item_id` INT NOT NULL,
        `child_board_item_id` INT NOT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_task_item_relation_child` (`child_board_item_id`),
        KEY `idx_task_item_relation_parent` (`parent_board_item_id`),
        KEY `idx_task_item_relation_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemRelationSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_RELATION . "` for task item parent-child relations.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_RELATION . "`: " . $conn->error . "</p>";
    }

    $createTaskItemHistorySql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_HISTORY . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `event_type` VARCHAR(80) NOT NULL,
        `field_name` VARCHAR(120) DEFAULT NULL,
        `from_value` TEXT DEFAULT NULL,
        `to_value` TEXT DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_item_history_item` (`item_id`),
        KEY `idx_task_item_history_type` (`event_type`),
        KEY `idx_task_item_history_created` (`create_date`,`create_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemHistorySql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_HISTORY . "` for task item history.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_HISTORY . "`: " . $conn->error . "</p>";
    }

    $createTaskItemCommentSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_COMMENT . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `comment_html` MEDIUMTEXT DEFAULT NULL,
        `comment_text` TEXT DEFAULT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_item_comment_main` (`item_id`, `status`, `create_date`, `create_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemCommentSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_COMMENT . "` for task item comments.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_COMMENT . "`: " . $conn->error . "</p>";
    }

    $createTaskItemCommentReplySql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_COMMENT_REPLY . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `comment_id` INT NOT NULL,
        `reply_html` MEDIUMTEXT DEFAULT NULL,
        `reply_text` TEXT DEFAULT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_item_comment_reply_main` (`comment_id`, `status`, `create_date`, `create_time`),
        KEY `idx_task_item_comment_reply_item` (`item_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemCommentReplySql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_COMMENT_REPLY . "` for task item comment replies.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_COMMENT_REPLY . "`: " . $conn->error . "</p>";
    }

    // MIGRATION: Update indexes for existing Task Item Comment tables

    // 1. Migration for TASK_ITEM_COMMENT
    $checkCommentIdx = $conn->query("SHOW INDEX FROM `" . TASK_ITEM_COMMENT . "` WHERE Key_name = 'idx_task_item_comment_main'");
    if ($checkCommentIdx && $checkCommentIdx->num_rows === 0) {
        // Drop old legacy indexes (using @ to suppress errors in case they don't exist)
        @$conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT . "` DROP INDEX `idx_task_item_comment_item`");
        @$conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT . "` DROP INDEX `idx_task_item_comment_created`");
        
        // Add new optimized composite index
        $conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT . "` ADD INDEX `idx_task_item_comment_main` (`item_id`, `status`, `create_date`, `create_time`)");
    }

    // 2. Migration for TASK_ITEM_COMMENT_REPLY
    $checkReplyIdx = $conn->query("SHOW INDEX FROM `" . TASK_ITEM_COMMENT_REPLY . "` WHERE Key_name = 'idx_task_item_comment_reply_main'");
    if ($checkReplyIdx && $checkReplyIdx->num_rows === 0) {
        // Drop old legacy indexes
        @$conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT_REPLY . "` DROP INDEX `idx_task_item_comment_reply_item`");
        @$conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT_REPLY . "` DROP INDEX `idx_task_item_comment_reply_comment`");
        @$conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT_REPLY . "` DROP INDEX `idx_task_item_comment_reply_created`");
        
        // Add new optimized composite indexes
        $conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT_REPLY . "` ADD INDEX `idx_task_item_comment_reply_main` (`comment_id`, `status`, `create_date`, `create_time`)");
        $conn->query("ALTER TABLE `" . TASK_ITEM_COMMENT_REPLY . "` ADD INDEX `idx_task_item_comment_reply_item` (`item_id`, `status`)");
    }

    $createTaskSheetsSql = "CREATE TABLE IF NOT EXISTS `" . TASK_SHEETS . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `user_id` INT NOT NULL,
        `column_key` VARCHAR(120) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_sheets_user` (`user_id`, `status`, `sort_order`),
        KEY `idx_task_sheets_project_user` (`project_id`, `user_id`, `status`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskSheetsSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_SHEETS . "` for sheets column config.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_SHEETS . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn($conn, $db_cms, TASK_COLUMN, 'project_id', "ALTER TABLE `" . TASK_COLUMN . "` ADD COLUMN `project_id` INT DEFAULT NULL AFTER `id`", "Verified `" . TASK_COLUMN . "` includes `project_id`.");
    migrationEnsureColumn($conn, $db_cms, TASK_COLUMN, 'color', "ALTER TABLE `" . TASK_COLUMN . "` ADD COLUMN `color` VARCHAR(20) NOT NULL DEFAULT '#dfe1e6' AFTER `name`", "Verified `" . TASK_COLUMN . "` includes `color`.");
    migrationEnsureIndex($conn, $db_cms, TASK_COLUMN, 'idx_task_status_project_sort', "ALTER TABLE `" . TASK_COLUMN . "` ADD INDEX `idx_task_status_project_sort` (`project_id`, `status`, `sort_order`)", "Verified `" . TASK_COLUMN . "` project index.");

    migrationEnsureColumn($conn, $db_cms, TASK_WORK_TYPE, 'project_id', "ALTER TABLE `" . TASK_WORK_TYPE . "` ADD COLUMN `project_id` INT DEFAULT NULL AFTER `id`", "Verified `" . TASK_WORK_TYPE . "` includes `project_id`.");
    migrationEnsureIndex($conn, $db_cms, TASK_WORK_TYPE, 'idx_task_work_type_project', "ALTER TABLE `" . TASK_WORK_TYPE . "` ADD INDEX `idx_task_work_type_project` (`project_id`, `status`, `name`)", "Verified `" . TASK_WORK_TYPE . "` project index.");
    if (migrationIndexExists($conn, $db_cms, TASK_WORK_TYPE, 'uniq_task_work_type_name')) {
        @$conn->query("ALTER TABLE `" . TASK_WORK_TYPE . "` DROP INDEX `uniq_task_work_type_name`");
    }

    migrationEnsureColumn($conn, $db_cms, TASK_PROJECT_KEY, 'project_id', "ALTER TABLE `" . TASK_PROJECT_KEY . "` ADD COLUMN `project_id` INT DEFAULT NULL AFTER `id`", "Verified `" . TASK_PROJECT_KEY . "` includes `project_id`.");
    migrationEnsureIndex($conn, $db_cms, TASK_PROJECT_KEY, 'idx_task_project_key_project', "ALTER TABLE `" . TASK_PROJECT_KEY . "` ADD INDEX `idx_task_project_key_project` (`project_id`, `status`)", "Verified `" . TASK_PROJECT_KEY . "` project index.");

    migrationEnsureColumn($conn, $db_cms, TASK_ITEM, 'project_id', "ALTER TABLE `" . TASK_ITEM . "` ADD COLUMN `project_id` INT DEFAULT NULL AFTER `id`", "Verified `" . TASK_ITEM . "` includes `project_id`.");
    migrationEnsureIndex($conn, $db_cms, TASK_ITEM, 'idx_task_item_project', "ALTER TABLE `" . TASK_ITEM . "` ADD INDEX `idx_task_item_project` (`project_id`, `column_id`, `sort_order`)", "Verified `" . TASK_ITEM . "` project index.");

    migrationEnsureColumn($conn, $db_cms, TASK_SHEETS, 'project_id', "ALTER TABLE `" . TASK_SHEETS . "` ADD COLUMN `project_id` INT DEFAULT NULL AFTER `id`", "Verified `" . TASK_SHEETS . "` includes `project_id`.");
    migrationEnsureIndex($conn, $db_cms, TASK_SHEETS, 'idx_task_sheets_project_user', "ALTER TABLE `" . TASK_SHEETS . "` ADD INDEX `idx_task_sheets_project_user` (`project_id`, `user_id`, `status`, `sort_order`)", "Verified `" . TASK_SHEETS . "` project index.");

    $legacyProjectId = 0;
    $legacyProjectResult = $conn->query("SELECT `id` FROM `" . TASK_PROJECT . "` WHERE `status` = 'A' ORDER BY `id` ASC LIMIT 1");
    if ($legacyProjectResult && $legacyProjectResult->num_rows > 0) {
        $legacyProjectRow = $legacyProjectResult->fetch_assoc();
        $legacyProjectId = isset($legacyProjectRow['id']) ? (int) $legacyProjectRow['id'] : 0;
    }

    if ($legacyProjectId <= 0) {
        $createLegacyProjectSql = "INSERT INTO `" . TASK_PROJECT . "` (`name`, `owner_user_id`, `board_background_color`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
            VALUES ('Task Management', 1, '#f4f7fb', 'Default migrated task project', '1', CURDATE(), CURTIME(), 'A')";
        if ($conn->query($createLegacyProjectSql)) {
            $legacyProjectId = (int) $conn->insert_id;
            echo "<p style='color:green;'>Verified default task project created (ID " . $legacyProjectId . ").</p>";
        } else {
            echo "<p style='color:red;'>Failed creating default task project: " . $conn->error . "</p>";
        }
    }

    if ($legacyProjectId > 0) {
        $conn->query("UPDATE `" . TASK_COLUMN . "` SET `project_id` = " . $legacyProjectId . " WHERE IFNULL(`project_id`, 0) = 0 AND `status` = 'A'");
        $conn->query("UPDATE `" . TASK_WORK_TYPE . "` SET `project_id` = " . $legacyProjectId . " WHERE IFNULL(`project_id`, 0) = 0 AND `status` = 'A'");
        $conn->query("UPDATE `" . TASK_PROJECT_KEY . "` SET `project_id` = " . $legacyProjectId . " WHERE IFNULL(`project_id`, 0) = 0 AND `status` = 'A'");
        $conn->query("UPDATE `" . TASK_ITEM . "` SET `project_id` = " . $legacyProjectId . " WHERE IFNULL(`project_id`, 0) = 0 AND `status` = 'A'");
        $conn->query("UPDATE `" . TASK_SHEETS . "` SET `project_id` = " . $legacyProjectId . " WHERE IFNULL(`project_id`, 0) = 0 AND `status` = 'A'");

        $legacyProjectKeyId = 0;
        $legacyProjectKeyResult = $conn->query("SELECT `id` FROM `" . TASK_PROJECT_KEY . "` WHERE `status` = 'A' AND `project_id` = " . $legacyProjectId . " ORDER BY `id` DESC LIMIT 1");
        if ($legacyProjectKeyResult && $legacyProjectKeyResult->num_rows > 0) {
            $legacyProjectKeyRow = $legacyProjectKeyResult->fetch_assoc();
            $legacyProjectKeyId = isset($legacyProjectKeyRow['id']) ? (int) $legacyProjectKeyRow['id'] : 0;
        }

        if ($legacyProjectKeyId <= 0) {
            $seedLegacyProjectKeySql = "INSERT INTO `" . TASK_PROJECT_KEY . "` (`project_id`, `project_key`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES (" . $legacyProjectId . ", 'TASK', 'Default project key', '1', CURDATE(), CURTIME(), 'A')";
            if ($conn->query($seedLegacyProjectKeySql)) {
                $legacyProjectKeyId = (int) $conn->insert_id;
                echo "<p style='color:green;'>Verified default project key created for task project " . $legacyProjectId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed creating default project key for task project " . $legacyProjectId . ": " . $conn->error . "</p>";
            }
        }

        if ($legacyProjectKeyId > 0) {
            $conn->query("UPDATE `" . TASK_ITEM . "` SET `project_key_id` = " . $legacyProjectKeyId . " WHERE `project_id` = " . $legacyProjectId . " AND IFNULL(`project_key_id`, 0) = 0 AND `status` = 'A'");
        }

        $defaultStatusSeedCount = 0;
        $defaultStatusCountResult = $conn->query("SELECT COUNT(*) AS `cnt` FROM `" . TASK_COLUMN . "` WHERE `status` = 'A' AND `project_id` = " . $legacyProjectId);
        if ($defaultStatusCountResult && $defaultStatusCountResult->num_rows > 0) {
            $defaultStatusCountRow = $defaultStatusCountResult->fetch_assoc();
            $defaultStatusSeedCount = isset($defaultStatusCountRow['cnt']) ? (int) $defaultStatusCountRow['cnt'] : 0;
        }

        if ($defaultStatusSeedCount <= 0) {
            $seedTaskStatusSql = "INSERT INTO `" . TASK_COLUMN . "` (`project_id`, `name`, `color`, `sort_order`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
                (" . $legacyProjectId . ", 'To Do', '#dfe1e6', 1, 'Default status', '1', CURDATE(), CURTIME(), 'A'),
                (" . $legacyProjectId . ", 'In Progress', '#579dff', 2, 'Default status', '1', CURDATE(), CURTIME(), 'A'),
                (" . $legacyProjectId . ", 'Done', '#4bce97', 3, 'Default status', '1', CURDATE(), CURTIME(), 'A')";
            if ($conn->query($seedTaskStatusSql)) {
                echo "<p style='color:green;'>Verified default statuses seeded for task project " . $legacyProjectId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed seeding default statuses for task project " . $legacyProjectId . ": " . $conn->error . "</p>";
            }
        }

        $defaultWorkTypeSeedCount = 0;
        $defaultWorkTypeCountResult = $conn->query("SELECT COUNT(*) AS `cnt` FROM `" . TASK_WORK_TYPE . "` WHERE `status` = 'A' AND `project_id` = " . $legacyProjectId);
        if ($defaultWorkTypeCountResult && $defaultWorkTypeCountResult->num_rows > 0) {
            $defaultWorkTypeCountRow = $defaultWorkTypeCountResult->fetch_assoc();
            $defaultWorkTypeSeedCount = isset($defaultWorkTypeCountRow['cnt']) ? (int) $defaultWorkTypeCountRow['cnt'] : 0;
        }

        if ($defaultWorkTypeSeedCount <= 0) {
            $seedWorkTypeSql = "INSERT INTO `" . TASK_WORK_TYPE . "` (`project_id`, `name`, `svg_icon`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
                (" . $legacyProjectId . ", 'Task', 'svg_icon/10318.svg', 'Default Task Work Type', '1', CURDATE(), CURTIME(), 'A'),
                (" . $legacyProjectId . ", 'Epic', 'svg_icon/10307.svg', 'Default Epic Work Type', '1', CURDATE(), CURTIME(), 'A')";
            if ($conn->query($seedWorkTypeSql)) {
                echo "<p style='color:green;'>Verified default task work types (Task, Epic) for project " . $legacyProjectId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed seeding default task work types for project " . $legacyProjectId . ": " . $conn->error . "</p>";
            }
        }

        $legacyWorkTypeIdsCsv = '';
        $legacyWorkTypeIdsResult = $conn->query("SELECT GROUP_CONCAT(`id` ORDER BY `id` SEPARATOR ',') AS `ids` FROM `" . TASK_WORK_TYPE . "` WHERE `project_id` = " . $legacyProjectId . " AND `status` = 'A'");
        if ($legacyWorkTypeIdsResult && $legacyWorkTypeIdsResult->num_rows > 0) {
            $legacyWorkTypeIdsRow = $legacyWorkTypeIdsResult->fetch_assoc();
            $legacyWorkTypeIdsCsv = isset($legacyWorkTypeIdsRow['ids']) ? trim((string) $legacyWorkTypeIdsRow['ids']) : '';
        }

        if ($legacyWorkTypeIdsCsv !== '') {
            $safeLegacyWorkTypeIdsCsv = $conn->real_escape_string($legacyWorkTypeIdsCsv);
            $seedProjectWorkTypeAccessSql = "INSERT INTO `" . TASK_PROJECT_ITEM_ACCESS . "` (
                    `project_id`, `user_id`, `can_add`, `can_edit`, `can_delete`, `allowed_work_type_ids`,
                    `remark`, `create_by`, `create_date`, `create_time`, `status`
                )
                SELECT
                    " . $legacyProjectId . ",
                    `u`.`id`,
                    COALESCE(`existing`.`can_add`, 0),
                    COALESCE(`existing`.`can_edit`, 0),
                    COALESCE(`existing`.`can_delete`, 0),
                    '" . $safeLegacyWorkTypeIdsCsv . "',
                    'Seeded default work type access',
                    '1',
                    CURDATE(),
                    CURTIME(),
                    'A'
                FROM `user` AS `u`
                LEFT JOIN `" . TASK_PROJECT_ITEM_ACCESS . "` AS `existing`
                    ON `existing`.`project_id` = " . $legacyProjectId . "
                   AND `existing`.`user_id` = `u`.`id`
                WHERE `u`.`status` = 'A'
                ON DUPLICATE KEY UPDATE
                    `allowed_work_type_ids` = VALUES(`allowed_work_type_ids`),
                    `status` = 'A',
                    `update_by` = '1',
                    `update_date` = CURDATE(),
                    `update_time` = CURTIME()";

            if ($conn->query($seedProjectWorkTypeAccessSql)) {
                echo "<p style='color:green;'>Verified work type access seeded for all active users in project " . $legacyProjectId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed seeding work type access for project " . $legacyProjectId . ": " . $conn->error . "</p>";
            }
        }

        $seedAdminStatusAccessSql = "INSERT INTO `" . TASK_PROJECT_STATUS_ACCESS . "` (
                `project_id`, `user_id`, `from_status_id`, `to_status_id`, `can_move`,
                `remark`, `create_by`, `create_date`, `create_time`, `status`
            )
            SELECT
                " . $legacyProjectId . ",
                `u`.`id`,
                `c`.`id`,
                `c`.`id`,
                1,
                'Seeded admin status access',
                '1',
                CURDATE(),
                CURTIME(),
                'A'
            FROM `user` AS `u`
            INNER JOIN `" . TASK_COLUMN . "` AS `c`
                ON `c`.`project_id` = " . $legacyProjectId . "
               AND `c`.`status` = 'A'
            WHERE `u`.`status` = 'A'
              AND `u`.`access_id` IN (1, 2)
            ON DUPLICATE KEY UPDATE
                `can_move` = 1,
                `status` = 'A',
                `update_by` = '1',
                `update_date` = CURDATE(),
                `update_time` = CURTIME()";

        if ($conn->query($seedAdminStatusAccessSql)) {
            echo "<p style='color:green;'>Verified status access seeded for active users in access groups 1 and 2 for project " . $legacyProjectId . ".</p>";
        } else {
            echo "<p style='color:red;'>Failed seeding admin status access for project " . $legacyProjectId . ": " . $conn->error . "</p>";
        }
    }

    if ($conn->query("DELETE FROM `pin` WHERE `id` = 26")) {
        echo "<p style='color:green;'>Verified pin 26 (Create Project) removed.</p>";
    } else {
        echo "<p style='color:red;'>Failed removing pin 26 (Create Project): " . $conn->error . "</p>";
    }

    $orderWarehouseTransferLogTable = defined('ORDER_WAREHOUSE_TRANSFER_LOG') ? ORDER_WAREHOUSE_TRANSFER_LOG : 'order_warehouse_transfer_log';
    $createOrderWarehouseTransferLogSql = "CREATE TABLE IF NOT EXISTS `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `platform` VARCHAR(30) NOT NULL,
        `order_table` VARCHAR(120) NOT NULL,
        `order_id` INT NOT NULL,
        `order_code` VARCHAR(150) NOT NULL,
        `old_warehouse_id` INT NOT NULL,
        `new_warehouse_id` INT NOT NULL,
        `product_qty_json` LONGTEXT DEFAULT NULL,
        `old_batch_usage_json` LONGTEXT DEFAULT NULL,
        `new_batch_usage_json` LONGTEXT DEFAULT NULL,
        `idempotency_key` VARCHAR(64) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_owtl_order_lookup` (`platform`, `order_id`, `status`),
        KEY `idx_owtl_order_code` (`order_code`, `status`),
        KEY `idx_owtl_warehouse` (`old_warehouse_id`, `new_warehouse_id`, `status`),
        UNIQUE KEY `uniq_owtl_idempotency` (`idempotency_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createOrderWarehouseTransferLogSql)) {
        echo "<p style='color:green;'>Verified table `" . $orderWarehouseTransferLogTable . "` for warehouse transfer logs.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . $orderWarehouseTransferLogTable . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'platform', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `platform` VARCHAR(30) NOT NULL AFTER `id`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `platform`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'order_table', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `order_table` VARCHAR(120) NOT NULL AFTER `platform`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `order_table`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'order_id', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `order_id` INT NOT NULL AFTER `order_table`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `order_id`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'order_code', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `order_code` VARCHAR(150) NOT NULL AFTER `order_id`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `order_code`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'old_warehouse_id', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `old_warehouse_id` INT NOT NULL AFTER `order_code`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `old_warehouse_id`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'new_warehouse_id', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `new_warehouse_id` INT NOT NULL AFTER `old_warehouse_id`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `new_warehouse_id`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'product_qty_json', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `product_qty_json` LONGTEXT DEFAULT NULL AFTER `new_warehouse_id`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `product_qty_json`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'old_batch_usage_json', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `old_batch_usage_json` LONGTEXT DEFAULT NULL AFTER `product_qty_json`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `old_batch_usage_json`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'new_batch_usage_json', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `new_batch_usage_json` LONGTEXT DEFAULT NULL AFTER `old_batch_usage_json`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `new_batch_usage_json`.");
    dropColumnIfExists($conn, $db_fin, $orderWarehouseTransferLogTable, 'transfer_note', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` DROP COLUMN `transfer_note`");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'idempotency_key', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `idempotency_key` VARCHAR(64) DEFAULT NULL AFTER `new_batch_usage_json`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `idempotency_key`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'create_by', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `create_by` VARCHAR(30) DEFAULT NULL AFTER `idempotency_key`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `create_by`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'create_date', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `create_by`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `create_date`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'create_time', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `create_time`.");
    migrationEnsureColumn($conn, $db_fin, $orderWarehouseTransferLogTable, 'status', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `create_time`", "Verified `" . $orderWarehouseTransferLogTable . "` includes `status`.");

    migrationEnsureIndex($conn, $db_fin, $orderWarehouseTransferLogTable, 'idx_owtl_order_lookup', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD INDEX `idx_owtl_order_lookup` (`platform`, `order_id`, `status`)", "Verified `" . $orderWarehouseTransferLogTable . "` order lookup index.");
    migrationEnsureIndex($conn, $db_fin, $orderWarehouseTransferLogTable, 'idx_owtl_order_code', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD INDEX `idx_owtl_order_code` (`order_code`, `status`)", "Verified `" . $orderWarehouseTransferLogTable . "` order code index.");
    migrationEnsureIndex($conn, $db_fin, $orderWarehouseTransferLogTable, 'idx_owtl_warehouse', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD INDEX `idx_owtl_warehouse` (`old_warehouse_id`, `new_warehouse_id`, `status`)", "Verified `" . $orderWarehouseTransferLogTable . "` warehouse index.");
    migrationEnsureIndex($conn, $db_fin, $orderWarehouseTransferLogTable, 'uniq_owtl_idempotency', "ALTER TABLE `" . $db_fin . "`.`" . $orderWarehouseTransferLogTable . "` ADD UNIQUE INDEX `uniq_owtl_idempotency` (`idempotency_key`)", "Verified `" . $orderWarehouseTransferLogTable . "` idempotency index.");

    $transferPinSql = "INSERT INTO `pin` (`id`, `name`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (26, 'Transfer', 'Warehouse transfer action', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";
    if ($conn->query($transferPinSql)) {
        echo "<p style='color:green;'>Verified pin 26 for Transfer.</p>";
    } else {
        echo "<p style='color:red;'>Failed verifying pin 26 for Transfer: " . $conn->error . "</p>";
    }

    $orderWarehouseTransferPinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (152, 'Order Warehouse Transfer', '1,26', 'Warehouse transfer search and action', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";
    if ($conn->query($orderWarehouseTransferPinGroupSql)) {
        echo "<p style='color:green;'>Verified pin group 152 for Order Warehouse Transfer.</p>";
    } else {
        echo "<p style='color:red;'>Failed verifying pin group 152: " . $conn->error . "</p>";
    }

    foreach (array(1, 2) as $groupId) {
        $userGroupResult = $conn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>`user_group` id " . (int) $groupId . " not found. Skipped Order Warehouse Transfer pin assignment.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 152, array(1, 26));

        if ($updatedPins !== $currentPins) {
            $safePins = $conn->real_escape_string($updatedPins);
            if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Verified Order Warehouse Transfer pin access for `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed updating Order Warehouse Transfer pin access for `user_group` id " . (int) $groupId . ": " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified Order Warehouse Transfer pin access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
    }

    $createCampaignSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_name` VARCHAR(255),
        `period_start_date` DATE,
        `period_end_date` DATE,
        `rule_setting_id` INT DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN . "` for Campaign.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN . "`: " . $conn->error . "</p>";
    }

    migrationEnsureIndex($conn, $db_cms, CAMPAIGN, 'idx_campaign_rule_setting', "ALTER TABLE `" . CAMPAIGN . "` ADD INDEX `idx_campaign_rule_setting` (`rule_setting_id`)", "Verified `" . CAMPAIGN . "` rule setting index.");

    $createCampaignPicSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_PIC . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A',
        KEY `idx_campaign_pic_campaign_id` (`campaign_id`),
        KEY `idx_campaign_pic_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignPicSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_PIC . "` for Campaign PIC.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_PIC . "`: " . $conn->error . "</p>";
    }

    $createCampaignCustomerSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_CUSTOMER . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_id` INT NOT NULL,
        `platform` VARCHAR(30),
        `customer_id` VARCHAR(100),
        `customer_name` VARCHAR(255),
        `customer_contact` VARCHAR(100),
        `customer_label` TEXT DEFAULT NULL,
        `customer_tags` TEXT DEFAULT NULL,
        `last_order_date` DATE DEFAULT NULL,
        `total_order` INT DEFAULT 0,
        `total_spent` DECIMAL(12,2) DEFAULT 0.00,
        `assign_source` VARCHAR(50) DEFAULT 'Manual',
        `purchase_status` VARCHAR(30) DEFAULT 'Pending',
        `follow_up_status` VARCHAR(30) DEFAULT 'Pending',
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A',
        KEY `idx_campaign_customer_campaign_id` (`campaign_id`),
        KEY `idx_campaign_customer_platform` (`platform`),
        KEY `idx_campaign_customer_customer_id` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignCustomerSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_CUSTOMER . "` for Campaign Customer.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_CUSTOMER . "`: " . $conn->error . "</p>";
    }

    $createCampaignMessageSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_MESSAGE . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_id` INT NOT NULL,
        `message_shortcut_id` INT DEFAULT NULL,
        `message_title` VARCHAR(255),
        `message_preview` TEXT DEFAULT NULL,
        `follow_up_date` DATE,
        `sequence_no` INT DEFAULT 1,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A',
        KEY `idx_campaign_message_campaign_id` (`campaign_id`),
        KEY `idx_campaign_message_follow_up_date` (`follow_up_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignMessageSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_MESSAGE . "` for Campaign Message.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_MESSAGE . "`: " . $conn->error . "</p>";
    }

    $createCampaignFollowUpSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_FOLLOW_UP . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_id` INT NOT NULL,
        `campaign_customer_id` INT NOT NULL,
        `campaign_message_id` INT NOT NULL,
        `pic_user_id` INT DEFAULT NULL,
        `follow_up_date` DATE,
        `follow_up_status` VARCHAR(30) DEFAULT 'Pending',
        `screenshot_path` VARCHAR(255) DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `label_preview` VARCHAR(255) DEFAULT NULL,
        `completed_by` VARCHAR(30) DEFAULT NULL,
        `completed_date` DATE DEFAULT NULL,
        `completed_time` TIME DEFAULT NULL,
        `notification_sent` CHAR(1) DEFAULT 'N',
        `notification_sent_date` DATE DEFAULT NULL,
        `notification_sent_time` TIME DEFAULT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A',
        KEY `idx_campaign_follow_up_campaign_id` (`campaign_id`),
        KEY `idx_campaign_follow_up_customer_id` (`campaign_customer_id`),
        KEY `idx_campaign_follow_up_message_id` (`campaign_message_id`),
        KEY `idx_campaign_follow_up_date` (`follow_up_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignFollowUpSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_FOLLOW_UP . "` for Campaign Follow-Up.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_FOLLOW_UP . "`: " . $conn->error . "</p>";
    }

    $createCampaignPurchaseRecordSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_PURCHASE_RECORD . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_id` INT NOT NULL,
        `campaign_customer_id` INT NOT NULL,
        `platform` VARCHAR(30),
        `order_id` VARCHAR(100),
        `order_no` VARCHAR(150),
        `order_detail` TEXT DEFAULT NULL,
        `order_status` VARCHAR(100),
        `order_amount` DECIMAL(12,2) DEFAULT 0.00,
        `order_date` DATETIME DEFAULT NULL,
        `package_text` TEXT DEFAULT NULL,
        `customer_type` VARCHAR(30) DEFAULT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A',
        KEY `idx_campaign_purchase_campaign_id` (`campaign_id`),
        KEY `idx_campaign_purchase_customer_id` (`campaign_customer_id`),
        KEY `idx_campaign_purchase_platform` (`platform`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignPurchaseRecordSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_PURCHASE_RECORD . "` for Campaign Purchase Record.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_PURCHASE_RECORD . "`: " . $conn->error . "</p>";
    }

    migrationEnsureIndex($conn, $db_cms, CAMPAIGN_PURCHASE_RECORD, 'idx_campaign_purchase_unique_order', "ALTER TABLE `" . CAMPAIGN_PURCHASE_RECORD . "` ADD UNIQUE KEY `idx_campaign_purchase_unique_order` (`campaign_id`,`campaign_customer_id`,`platform`,`order_id`,`order_no`)", "Verified `" . CAMPAIGN_PURCHASE_RECORD . "` duplicate prevention index.");


    $createCampaignRuleSettingSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_RULE_SETTING . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `rule_name` VARCHAR(255),
        `generate_schedule` VARCHAR(100),
        `generate_day` VARCHAR(50) DEFAULT NULL,
        `campaign_name_template` VARCHAR(255),
        `campaign_period_rule` VARCHAR(100),
        `customer_condition_json` MEDIUMTEXT DEFAULT NULL,
        `default_pic_json` MEDIUMTEXT DEFAULT NULL,
        `default_message_json` MEDIUMTEXT DEFAULT NULL,
        `rule_status` VARCHAR(30) DEFAULT 'Active',
        `last_generated_date` DATE DEFAULT NULL,
        `last_generated_time` TIME DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignRuleSettingSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_RULE_SETTING . "` for Campaign Rule Setting.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_RULE_SETTING . "`: " . $conn->error . "</p>";
    }

    $createCampaignRuleGeneratedLogSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_RULE_GENERATED_LOG . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `rule_setting_id` INT NOT NULL,
        `campaign_id` INT DEFAULT NULL,
        `generated_key` VARCHAR(255),
        `generated_date` DATE,
        `generated_time` TIME,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `status` CHAR(1) DEFAULT 'A',
        KEY `idx_campaign_rule_log_rule_setting` (`rule_setting_id`),
        KEY `idx_campaign_rule_log_campaign_id` (`campaign_id`),
        KEY `idx_campaign_rule_log_generated_key` (`generated_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignRuleGeneratedLogSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_RULE_GENERATED_LOG . "` for Campaign Rule Generated Log.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_RULE_GENERATED_LOG . "`: " . $conn->error . "</p>";
    }

    migrationEnsureIndex($conn, $db_cms, CAMPAIGN_RULE_GENERATED_LOG, 'idx_campaign_rule_generated_key_unique', "ALTER TABLE `" . CAMPAIGN_RULE_GENERATED_LOG . "` ADD UNIQUE KEY `idx_campaign_rule_generated_key_unique` (`generated_key`)", "Verified `" . CAMPAIGN_RULE_GENERATED_LOG . "` generated key unique index.");


    $campaignPinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (153, 'Campaign', '1,2,3,4', 'Campaign management', '1', CURDATE(), CURTIME(), 'A'),
        (154, 'Campaign Rule Setting', '1,2,3,4', 'Campaign auto rule setting', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";
    if ($conn->query($campaignPinGroupSql)) {
        echo "<p style='color:green;'>Verified pin groups 153 Campaign and 154 Campaign Rule Setting.</p>";
    } else {
        echo "<p style='color:red;'>Failed verifying Campaign pin groups: " . $conn->error . "</p>";
    }

    $campaignGroupResult = $conn->query("SELECT `id`, `pins` FROM `user_group`");
    if ($campaignGroupResult) {
        while ($campaignGroupRow = $campaignGroupResult->fetch_assoc()) {
            $groupId = isset($campaignGroupRow['id']) ? (int) $campaignGroupRow['id'] : 0;
            if ($groupId <= 0) {
                continue;
            }

            $currentPins = isset($campaignGroupRow['pins']) ? (string) $campaignGroupRow['pins'] : '';
            if (in_array($groupId, array(1, 2), true)) {
                $updatedPins = setPinBlockAccess($currentPins, 153, array(1, 2, 3, 4));
                $updatedPins = setPinBlockAccess($updatedPins, 154, array(1, 2, 3, 4));
            } else {
                $updatedPins = removePinBlockById($currentPins, 153);
                $updatedPins = removePinBlockById($updatedPins, 154);
            }

            if ($updatedPins !== $currentPins) {
                $safePins = $conn->real_escape_string($updatedPins);
                if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                    echo "<p style='color:green;'>Verified Campaign pin access for `user_group` id " . (int) $groupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Failed updating Campaign pin access for `user_group` id " . (int) $groupId . ": " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color:green;'>Verified Campaign pin access already matches `user_group` id " . (int) $groupId . ".</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Failed reading `user_group` for Campaign pin assignment: " . $conn->error . "</p>";
    }

    $createLabelSql = "CREATE TABLE IF NOT EXISTS `" . LABEL . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(120) NOT NULL,
        `parent_label` INT DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_label_parent` (`parent_label`),
        KEY `idx_label_name_status` (`name`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createLabelSql)) {
        echo "<p style='color:green;'>Verified table `" . LABEL . "` for product labels.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . LABEL . "`: " . $conn->error . "</p>";
    }

    $taskPinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (136, 'Board', '1,2,3,4', 'Task Board Management', '1', CURDATE(), CURTIME(), 'A'),
        (137, 'Summary', '1', 'Task Summary Management', '1', CURDATE(), CURTIME(), 'A'),
        (138, 'Sheets', '1,2,3,4', 'Task Sheets Management', '1', CURDATE(), CURTIME(), 'A'),
        (139, 'Project Task', '1,2,3,4', 'Project task navigation', '1', CURDATE(), CURTIME(), 'A'),
        (140, 'Project Settings', '1,2,3,4', 'Project task settings management', '1', CURDATE(), CURTIME(), 'A'),
        (141, 'Project User Access', '1,2,3,4', 'Project task user access management', '1', CURDATE(), CURTIME(), 'A'),
        (142, 'Customer Level', '1,2,3,4', 'Customer level management', '1', CURDATE(), CURTIME(), 'A'),
        (143, 'Customer Repeat', '1,2,3,4', 'Customer repeat management', '1', CURDATE(), CURTIME(), 'A'),
        (144, 'Message Shortcuts', '1,2,3,4', 'Message shortcuts management', '1', CURDATE(), CURTIME(), 'A'),
        (145, 'Label', '1,2,3,4', 'Product label management', '1', CURDATE(), CURTIME(), 'A'),
        (146, 'Waiting To Pack', '1', 'OMS warehouse scan flow', '1', CURDATE(), CURTIME(), 'A'),
        (147, 'Arrival Management', '1,2,3,4', 'OMS arrival management workflow', '1', CURDATE(), CURTIME(), 'A'),
        (148, 'Daily Flow Report', '1', 'OMS daily flow reporting', '1', CURDATE(), CURTIME(), 'A'),
        (149, 'Flow Setting', '1,2,3,4', 'OMS flow setting management', '1', CURDATE(), CURTIME(), 'A'),
        (150, 'Customer Daily Report', '1', 'Customer daily edit activity reporting', '1', CURDATE(), CURTIME(), 'A'),
        (151, 'Customer Follow-Up', '1,11,12', 'Customer follow-up approval and log access', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";
    if ($conn->query($taskPinGroupSql)) {
        echo "<p style='color:green;'>Verified pin groups 136-151 for task, customer, product label, and OMS page management.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating pin groups 136-151: " . $conn->error . "</p>";
    }

    $omsPagePinGroupUpdateSql = "UPDATE `pin_group`
        SET `name` = CASE `id`
                WHEN 146 THEN 'Waiting To Pack'
                WHEN 147 THEN 'Arrival Management'
                WHEN 148 THEN 'Daily Flow Report'
                WHEN 149 THEN 'Flow Setting'
                WHEN 150 THEN 'Customer Daily Report'
                WHEN 151 THEN 'Customer Follow-Up'
                ELSE `name`
            END,
            `remark` = CASE `id`
                WHEN 146 THEN 'OMS warehouse scan flow'
                WHEN 147 THEN 'OMS arrival management workflow'
                WHEN 148 THEN 'OMS daily flow reporting'
                WHEN 149 THEN 'OMS flow setting management'
                WHEN 150 THEN 'Customer daily edit activity reporting'
                WHEN 151 THEN 'Customer follow-up approval and log access'
                ELSE `remark`
            END,
            `status` = 'A'
        WHERE `id` IN (146,147,148,149,150,151)";
    if ($conn->query($omsPagePinGroupUpdateSql)) {
        echo "<p style='color:green;'>Verified pin group names for Waiting To Pack, Arrival Management, Daily Flow Report, Flow Setting, Customer Daily Report, and Customer Follow-Up.</p>";
    } else {
        echo "<p style='color:red;'>Failed updating OMS pin group names: " . $conn->error . "</p>";
    }

    foreach (array(1, 2, 3) as $groupId) {
        $userGroupResult = $conn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>`user_group` id " . (int) $groupId . " not found. Skipped task pin assignment.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 136, array(1, 2, 3, 4));
        $updatedPins = addAccessToPinBlock($updatedPins, 137, array(1));
        $updatedPins = addAccessToPinBlock($updatedPins, 138, array(1, 2, 3, 4));
        $updatedPins = removeAccessFromPinBlock($updatedPins, 139, array(26));
        $updatedPins = removePinBlockById($updatedPins, 139);

        if ($groupId === 1 || $groupId === 2) {
            $updatedPins = addAccessToPinBlock($updatedPins, 139, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 140, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 141, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 142, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 143, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 145, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 146, array(1));
            $updatedPins = addAccessToPinBlock($updatedPins, 147, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 148, array(1));
            $updatedPins = addAccessToPinBlock($updatedPins, 150, array(1));
        }

        if ($groupId === 1) {
            $updatedPins = addAccessToPinBlock($updatedPins, 149, array(1, 2, 3, 4));
        }

        if ($updatedPins !== $currentPins) {
            $safePins = $conn->real_escape_string($updatedPins);
            if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Verified task pin access for `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed updating task pin access for `user_group` id " . (int) $groupId . ": " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified task pin access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
    }

    $messageShortcutAccessGroups = array();
    $messageShortcutUserResult = $conn->query("SELECT DISTINCT `access_id` FROM `user` WHERE `id` IN (1, 2) AND `status` = 'A'");
    if ($messageShortcutUserResult) {
        while ($messageShortcutUserRow = $messageShortcutUserResult->fetch_assoc()) {
            $accessId = isset($messageShortcutUserRow['access_id']) ? (int) $messageShortcutUserRow['access_id'] : 0;
            if ($accessId > 0) {
                $messageShortcutAccessGroups[] = $accessId;
            }
        }
    }

    if (empty($messageShortcutAccessGroups)) {
        $messageShortcutAccessGroups = array(1, 2);
    }

    $messageShortcutAccessGroups = array_values(array_unique($messageShortcutAccessGroups));

    foreach ($messageShortcutAccessGroups as $groupId) {
        $userGroupResult = $conn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>`user_group` id " . (int) $groupId . " not found. Skipped Message Shortcuts pin assignment.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 144, array(1, 2, 3, 4));

        if ($updatedPins !== $currentPins) {
            $safePins = $conn->real_escape_string($updatedPins);
            if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Verified Message Shortcuts pin access for `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed updating Message Shortcuts pin access for `user_group` id " . (int) $groupId . ": " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified Message Shortcuts pin access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
    }

    $customerFollowUpGroupResult = $conn->query("SELECT `id`, `pins` FROM `user_group`");
    if ($customerFollowUpGroupResult) {
        while ($customerFollowUpGroupRow = $customerFollowUpGroupResult->fetch_assoc()) {
            $groupId = isset($customerFollowUpGroupRow['id']) ? (int) $customerFollowUpGroupRow['id'] : 0;
            if ($groupId <= 0) {
                continue;
            }

            $currentPins = isset($customerFollowUpGroupRow['pins']) ? (string) $customerFollowUpGroupRow['pins'] : '';

            if (in_array($groupId, array(1, 2), true)) {
                // Admin groups keep full Customer Follow-Up access: View + Approve + Reject.
                $updatedPins = addAccessToPinBlock($currentPins, 151, array(1, 11, 12));
            } else {
                // Other user groups only get View access.
                $updatedPins = addAccessToPinBlock($currentPins, 151, array(1));
            }

            if ($updatedPins !== $currentPins) {
                $safePins = $conn->real_escape_string($updatedPins);
                if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . $groupId)) {
                    echo "<p style='color:green;'>Verified Customer Follow-Up pin access for `user_group` id " . $groupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Failed updating Customer Follow-Up pin access for `user_group` id " . $groupId . ": " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color:green;'>Verified Customer Follow-Up pin access already matches `user_group` id " . $groupId . ".</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Failed reading `user_group` for Customer Follow-Up pin assignment: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red;'>Failed selecting CMS database for task management migration.</p>";
}

if ($conn->select_db($db_cms)) {
    $createOmsSettingSql = "CREATE TABLE IF NOT EXISTS `" . ORDER_FLOW_SETTING . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(120) NOT NULL,
        `setting_value` TEXT DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_order_flow_setting_key` (`setting_key`),
        KEY `idx_order_flow_setting_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createOmsSettingSql)) {
        echo "<p style='color:green;'>Verified table `" . ORDER_FLOW_SETTING . "` for OMS settings.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . ORDER_FLOW_SETTING . "`: " . $conn->error . "</p>";
    }

    $createOmsPermissionSql = "CREATE TABLE IF NOT EXISTS `" . ORDER_FLOW_TRANSITION_PERMISSION . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `module_key` VARCHAR(60) NOT NULL DEFAULT 'shopee_oms',
        `transition_key` VARCHAR(120) NOT NULL,
        `from_status` VARCHAR(50) NOT NULL,
        `to_status` VARCHAR(50) NOT NULL,
        `user_group_id` INT NOT NULL,
        `can_move` TINYINT(1) NOT NULL DEFAULT 1,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_order_flow_transition_permission` (`module_key`, `from_status`, `to_status`, `user_group_id`),
        KEY `idx_order_flow_perm_group` (`user_group_id`, `can_move`, `status`),
        KEY `idx_order_flow_perm_transition` (`transition_key`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createOmsPermissionSql)) {
        echo "<p style='color:green;'>Verified table `" . ORDER_FLOW_TRANSITION_PERMISSION . "` for OMS transition permissions.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . ORDER_FLOW_TRANSITION_PERMISSION . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn($conn, $db_cms, USR_GRP, 'badge_color', "ALTER TABLE `" . USR_GRP . "` ADD COLUMN `badge_color` VARCHAR(20) NOT NULL DEFAULT '#6c757d' AFTER `name`", "Verified `" . USR_GRP . "` includes `badge_color`.");
    migrationEnsureColumn($conn, $db_cms, USR_GRP, 'badge_icon_class', "ALTER TABLE `" . USR_GRP . "` ADD COLUMN `badge_icon_class` VARCHAR(120) NOT NULL DEFAULT 'fa-solid fa-user-group' AFTER `badge_color`", "Verified `" . USR_GRP . "` includes `badge_icon_class`.");

    $defaultOmsSettings = array(
        array('shopee_oms_assignment_scope', 'global', 'OMS assignment scope: global or individual.'),
        array('shopee_oms_default_warehouse_id', '0', 'Default warehouse id used for OMS stock-out selection.'),
        array('shopee_oms_daily_report_main_supervisor_user_id', '0', 'Main supervisor user id for OMS daily email report.'),
        array('shopee_oms_daily_report_second_supervisor_user_id', '0', 'Second supervisor user id for OMS daily email report.')
    );
    foreach ($defaultOmsSettings as $settingRow) {
        $safeKey = $conn->real_escape_string((string) $settingRow[0]);
        $safeValue = $conn->real_escape_string((string) $settingRow[1]);
        $safeRemark = $conn->real_escape_string((string) $settingRow[2]);
        $seedSettingSql = "INSERT INTO `" . ORDER_FLOW_SETTING . "` (`setting_key`, `setting_value`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
            VALUES ('" . $safeKey . "', '" . $safeValue . "', '" . $safeRemark . "', '1', CURDATE(), CURTIME(), 'A')
            ON DUPLICATE KEY UPDATE `remark` = VALUES(`remark`), `status` = 'A'";
        if ($conn->query($seedSettingSql)) {
            echo "<p style='color:green;'>Verified OMS setting `" . $safeKey . "`.</p>";
        } else {
            echo "<p style='color:red;'>Failed seeding OMS setting `" . $safeKey . "`: " . $conn->error . "</p>";
        }
    }

    $defaultBadgeUpdates = array(
        1 => array('#b3261e', 'fa-solid fa-crown'),
        2 => array('#0d6efd', 'fa-solid fa-user-shield'),
        3 => array('#6c757d', 'fa-solid fa-user'),
        4 => array('#198754', 'fa-solid fa-user-tag')
    );
    foreach ($defaultBadgeUpdates as $groupId => $badgeInfo) {
        $safeColor = $conn->real_escape_string((string) $badgeInfo[0]);
        $safeIcon = $conn->real_escape_string((string) $badgeInfo[1]);
        if ($conn->query("UPDATE `" . USR_GRP . "` SET `badge_color` = CASE WHEN IFNULL(TRIM(`badge_color`), '') = '' THEN '" . $safeColor . "' ELSE `badge_color` END, `badge_icon_class` = CASE WHEN IFNULL(TRIM(`badge_icon_class`), '') = '' THEN '" . $safeIcon . "' ELSE `badge_icon_class` END WHERE `id` = " . (int) $groupId)) {
            echo "<p style='color:green;'>Verified default OMS badge for `user_group` id " . (int) $groupId . ".</p>";
        } else {
            echo "<p style='color:red;'>Failed updating default OMS badge for `user_group` id " . (int) $groupId . ": " . $conn->error . "</p>";
        }
    }

    $transitionSeeds = array(
        array('move_to_pack', 'P', 'TP'),
        array('warehouse_scan', 'TP', 'SP'),
        array('assign_estimated_received_date', 'WAERD', 'WR'),
        array('confirm_parcel_received', 'WR', 'PR'),
        array('admin_audit', 'WAFC', 'V'),
        array('finalize_complete', 'V', 'C'),
        array('return_restock', 'SP', 'CR'),
        array('return_restock', 'WAERD', 'CR'),
        array('return_restock', 'WR', 'CR'),
        array('return_restock', 'PR', 'CR'),
        array('return_restock', 'WAFC', 'CR'),
        array('return_restock', 'V', 'CR')
    );
    $userGroupSeedRst = $conn->query("SELECT `id` FROM `" . USR_GRP . "` WHERE `status` = 'A' ORDER BY `id` ASC");
    if ($userGroupSeedRst) {
        while ($userGroupSeedRow = $userGroupSeedRst->fetch_assoc()) {
            $userGroupId = isset($userGroupSeedRow['id']) ? (int) $userGroupSeedRow['id'] : 0;
            if ($userGroupId <= 0) {
                continue;
            }

            foreach ($transitionSeeds as $transitionSeed) {
                $safeTransitionKey = $conn->real_escape_string((string) $transitionSeed[0]);
                $safeFromStatus = $conn->real_escape_string((string) $transitionSeed[1]);
                $safeToStatus = $conn->real_escape_string((string) $transitionSeed[2]);
                $seedPermSql = "INSERT INTO `" . ORDER_FLOW_TRANSITION_PERMISSION . "` (`module_key`, `transition_key`, `from_status`, `to_status`, `user_group_id`, `can_move`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
                    VALUES ('shopee_oms', '" . $safeTransitionKey . "', '" . $safeFromStatus . "', '" . $safeToStatus . "', " . $userGroupId . ", 1, '', '1', CURDATE(), CURTIME(), 'A')
                    ON DUPLICATE KEY UPDATE `module_key` = `module_key`";
                if ($conn->query($seedPermSql)) {
                    echo "<p style='color:green;'>Verified OMS transition permission " . $safeFromStatus . " -> " . $safeToStatus . " for user group " . $userGroupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Failed seeding OMS transition permission " . $safeFromStatus . " -> " . $safeToStatus . " for user group " . $userGroupId . ": " . $conn->error . "</p>";
                }
            }
        }
    } else {
        echo "<p style='color:red;'>Failed loading active user groups for OMS permission seeding.</p>";
    }
} else {
    echo "<p style='color:red;'>Failed selecting CMS database for OMS migration.</p>";
}

if ($conn->select_db($db_fin)) {
    $createOmsTransitionLogSql = "CREATE TABLE IF NOT EXISTS `" . ORDER_STATUS_TRANSITION_LOG . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `order_code` VARCHAR(120) DEFAULT NULL,
        `platform` VARCHAR(30) DEFAULT NULL,
        `from_status` VARCHAR(50) DEFAULT NULL,
        `to_status` VARCHAR(50) DEFAULT NULL,
        `transition_action` VARCHAR(120) DEFAULT NULL,
        `user_id` VARCHAR(30) DEFAULT NULL,
        `user_group_id` INT DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `source_page` VARCHAR(150) DEFAULT NULL,
        `related_token_scan_id` VARCHAR(120) DEFAULT NULL,
        `transition_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_oms_transition_order` (`order_id`, `status`),
        KEY `idx_oms_transition_platform` (`platform`, `status`),
        KEY `idx_oms_transition_status` (`from_status`, `to_status`, `status`),
        KEY `idx_oms_transition_date` (`transition_at`),
        KEY `idx_oms_transition_group` (`user_group_id`, `status`),
        KEY `idx_oms_transition_token` (`related_token_scan_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createOmsTransitionLogSql)) {
        echo "<p style='color:green;'>Verified table `" . ORDER_STATUS_TRANSITION_LOG . "` for OMS transition logs.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . ORDER_STATUS_TRANSITION_LOG . "`: " . $conn->error . "</p>";
    }

    $createOmsEditHistorySql = "CREATE TABLE IF NOT EXISTS `" . ORDER_EDIT_HISTORY . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `order_code` VARCHAR(120) DEFAULT NULL,
        `platform` VARCHAR(30) DEFAULT NULL,
        `field_name` VARCHAR(120) NOT NULL,
        `field_label` VARCHAR(160) DEFAULT NULL,
        `old_value` LONGTEXT DEFAULT NULL,
        `new_value` LONGTEXT DEFAULT NULL,
        `user_id` VARCHAR(30) DEFAULT NULL,
        `user_group_id` INT DEFAULT NULL,
        `source_page` VARCHAR(150) DEFAULT NULL,
        `change_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_oms_edit_history_order` (`order_id`, `status`),
        KEY `idx_oms_edit_history_platform` (`platform`, `status`),
        KEY `idx_oms_edit_history_date` (`change_at`),
        KEY `idx_oms_edit_history_field` (`field_name`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createOmsEditHistorySql)) {
        echo "<p style='color:green;'>Verified table `" . ORDER_EDIT_HISTORY . "` for OMS edit history.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . ORDER_EDIT_HISTORY . "`: " . $conn->error . "</p>";
    }

    $createOmsReturnLogSql = "CREATE TABLE IF NOT EXISTS `" . ORDER_RETURN_LOG . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `order_code` VARCHAR(120) DEFAULT NULL,
        `platform` VARCHAR(30) DEFAULT NULL,
        `status_before` VARCHAR(50) DEFAULT NULL,
        `status_after` VARCHAR(50) DEFAULT NULL,
        `return_type` VARCHAR(40) DEFAULT NULL,
        `inventory_effect` VARCHAR(40) DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `user_id` VARCHAR(30) DEFAULT NULL,
        `user_group_id` INT DEFAULT NULL,
        `source_page` VARCHAR(150) DEFAULT NULL,
        `action_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_oms_return_order` (`order_id`, `status`),
        KEY `idx_oms_return_platform` (`platform`, `status`),
        KEY `idx_oms_return_date` (`action_at`),
        KEY `idx_oms_return_type` (`return_type`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createOmsReturnLogSql)) {
        echo "<p style='color:green;'>Verified table `" . ORDER_RETURN_LOG . "` for OMS return logs.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . ORDER_RETURN_LOG . "`: " . $conn->error . "</p>";
    }

    $createOmsWarehouseTokenSql = "CREATE TABLE IF NOT EXISTS `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `order_code` VARCHAR(120) DEFAULT NULL,
        `platform` VARCHAR(30) DEFAULT NULL,
        `token` VARCHAR(190) NOT NULL,
        `token_type` VARCHAR(30) NOT NULL DEFAULT 'stock_out',
        `customer_name` VARCHAR(200) DEFAULT NULL,
        `customer_address` LONGTEXT DEFAULT NULL,
        `package_summary` LONGTEXT DEFAULT NULL,
        `product_summary` LONGTEXT DEFAULT NULL,
        `airbill_attachment` LONGTEXT DEFAULT NULL,
        `payload_text` LONGTEXT DEFAULT NULL,
        `sent_result` LONGTEXT DEFAULT NULL,
        `used_at` DATETIME DEFAULT NULL,
        `used_by` VARCHAR(30) DEFAULT NULL,
        `used_source` VARCHAR(150) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_oms_warehouse_token` (`token`),
        KEY `idx_oms_warehouse_order` (`order_id`, `status`),
        KEY `idx_oms_warehouse_platform` (`platform`, `status`),
        KEY `idx_oms_warehouse_used` (`used_at`),
        KEY `idx_oms_warehouse_code` (`order_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createOmsWarehouseTokenSql)) {
        echo "<p style='color:green;'>Verified table `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` for OMS warehouse scan tokens.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . ORDER_WAREHOUSE_SCAN_TOKEN . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'airbill_no', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `airbill_no` VARCHAR(150) DEFAULT NULL AFTER `barcode_slot`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `airbill_no`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'airbill_attachment', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `airbill_attachment` TEXT DEFAULT NULL AFTER `airbill_no`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `airbill_attachment`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'stock_out_warehouse_id', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `stock_out_warehouse_id` INT DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `stock_out_warehouse_id`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'customer_name', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `customer_name` VARCHAR(200) DEFAULT NULL AFTER `buyer`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `customer_name`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'customer_address', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `customer_address` TEXT DEFAULT NULL AFTER `customer_name`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `customer_address`.");
    migrationEnsureColumnAfter($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'customer_address', 'customer_name', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` MODIFY COLUMN `customer_address` TEXT NULL AFTER `customer_name`", "Updated `" . SHOPEE_SG_ORDER_REQ . "`.`customer_address` to follow `customer_name`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'package_qty_json', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `package_qty_json` LONGTEXT DEFAULT NULL AFTER `package`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `package_qty_json`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'order_detail_pdf', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `order_detail_pdf` VARCHAR(255) DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `order_detail_pdf`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'latest_transition_at', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `latest_transition_at` DATETIME DEFAULT NULL AFTER `estimated_received_date_assigned_time`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `latest_transition_at`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'warehouse_scan_at', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `warehouse_scan_at` DATETIME DEFAULT NULL AFTER `latest_transition_at`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `warehouse_scan_at`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'warehouse_scan_by', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `warehouse_scan_by` VARCHAR(30) DEFAULT NULL AFTER `warehouse_scan_at`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `warehouse_scan_by`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'warehouse_scan_ref', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `warehouse_scan_ref` VARCHAR(120) DEFAULT NULL AFTER `warehouse_scan_by`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `warehouse_scan_ref`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'delay_remark', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `delay_remark` TEXT DEFAULT NULL AFTER `warehouse_scan_ref`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `delay_remark`.");
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'step_a_sent_at', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `step_a_sent_at` DATETIME DEFAULT NULL AFTER `delay_remark`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `step_a_sent_at`.");

    migrationEnsureIndex($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'idx_shopee_order_status', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD INDEX `idx_shopee_order_status` (`order_status`)", "Verified `" . SHOPEE_SG_ORDER_REQ . "` status index.");
    migrationEnsureIndex($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'idx_shopee_order_code', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD INDEX `idx_shopee_order_code` (`orderID`)", "Verified `" . SHOPEE_SG_ORDER_REQ . "` order code index.");
    migrationEnsureIndex($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'idx_shopee_order_airbill', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD INDEX `idx_shopee_order_airbill` (`airbill_no`)", "Verified `" . SHOPEE_SG_ORDER_REQ . "` airbill index.");
    migrationEnsureIndex($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'idx_shopee_order_transition_at', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD INDEX `idx_shopee_order_transition_at` (`latest_transition_at`)", "Verified `" . SHOPEE_SG_ORDER_REQ . "` transition timestamp index.");

    migrationEnsureColumn($conn, $db_fin, ORDER_STATUS_TRANSITION_LOG, 'platform', "ALTER TABLE `" . ORDER_STATUS_TRANSITION_LOG . "` ADD COLUMN `platform` VARCHAR(30) DEFAULT NULL AFTER `order_code`", "Verified `" . ORDER_STATUS_TRANSITION_LOG . "` includes `platform`.");
    migrationEnsureColumn($conn, $db_fin, ORDER_EDIT_HISTORY, 'platform', "ALTER TABLE `" . ORDER_EDIT_HISTORY . "` ADD COLUMN `platform` VARCHAR(30) DEFAULT NULL AFTER `order_code`", "Verified `" . ORDER_EDIT_HISTORY . "` includes `platform`.");
    migrationEnsureColumn($conn, $db_fin, ORDER_RETURN_LOG, 'platform', "ALTER TABLE `" . ORDER_RETURN_LOG . "` ADD COLUMN `platform` VARCHAR(30) DEFAULT NULL AFTER `order_code`", "Verified `" . ORDER_RETURN_LOG . "` includes `platform`.");
    migrationEnsureColumn($conn, $db_fin, ORDER_WAREHOUSE_SCAN_TOKEN, 'platform', "ALTER TABLE `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` ADD COLUMN `platform` VARCHAR(30) DEFAULT NULL AFTER `order_code`", "Verified `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` includes `platform`.");

    migrationEnsureIndex($conn, $db_fin, ORDER_STATUS_TRANSITION_LOG, 'idx_oms_transition_platform', "ALTER TABLE `" . ORDER_STATUS_TRANSITION_LOG . "` ADD INDEX `idx_oms_transition_platform` (`platform`, `status`)", "Verified `" . ORDER_STATUS_TRANSITION_LOG . "` platform index.");
    migrationEnsureIndex($conn, $db_fin, ORDER_EDIT_HISTORY, 'idx_oms_edit_history_platform', "ALTER TABLE `" . ORDER_EDIT_HISTORY . "` ADD INDEX `idx_oms_edit_history_platform` (`platform`, `status`)", "Verified `" . ORDER_EDIT_HISTORY . "` platform index.");
    migrationEnsureIndex($conn, $db_fin, ORDER_RETURN_LOG, 'idx_oms_return_platform', "ALTER TABLE `" . ORDER_RETURN_LOG . "` ADD INDEX `idx_oms_return_platform` (`platform`, `status`)", "Verified `" . ORDER_RETURN_LOG . "` platform index.");
    migrationEnsureIndex($conn, $db_fin, ORDER_WAREHOUSE_SCAN_TOKEN, 'idx_oms_warehouse_platform', "ALTER TABLE `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` ADD INDEX `idx_oms_warehouse_platform` (`platform`, `status`)", "Verified `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` platform index.");

    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'airbill_no', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `airbill_no` VARCHAR(150) DEFAULT NULL AFTER `order_status`", "Verified `" . FB_ORDER_REQ . "` includes `airbill_no`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'airbill_attachment', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `airbill_attachment` TEXT DEFAULT NULL AFTER `airbill_no`", "Verified `" . FB_ORDER_REQ . "` includes `airbill_attachment`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'stock_out_warehouse_id', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `stock_out_warehouse_id` INT DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . FB_ORDER_REQ . "` includes `stock_out_warehouse_id`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'delay_remark', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `delay_remark` TEXT DEFAULT NULL AFTER `stock_out_warehouse_id`", "Verified `" . FB_ORDER_REQ . "` includes `delay_remark`.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'idx_fb_order_status', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD INDEX `idx_fb_order_status` (`order_status`)", "Verified `" . FB_ORDER_REQ . "` status index.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'idx_fb_order_airbill', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD INDEX `idx_fb_order_airbill` (`airbill_no`)", "Verified `" . FB_ORDER_REQ . "` airbill index.");

    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'airbill_no', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `airbill_no` VARCHAR(150) DEFAULT NULL AFTER `order_status`", "Verified `" . WEB_ORDER_REQ . "` includes `airbill_no`.");
    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'airbill_attachment', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `airbill_attachment` TEXT DEFAULT NULL AFTER `airbill_no`", "Verified `" . WEB_ORDER_REQ . "` includes `airbill_attachment`.");
    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'stock_out_warehouse_id', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `stock_out_warehouse_id` INT DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . WEB_ORDER_REQ . "` includes `stock_out_warehouse_id`.");
    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'delay_remark', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `delay_remark` TEXT DEFAULT NULL AFTER `stock_out_warehouse_id`", "Verified `" . WEB_ORDER_REQ . "` includes `delay_remark`.");
    migrationEnsureIndex($conn, $db_fin, WEB_ORDER_REQ, 'idx_web_order_status', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD INDEX `idx_web_order_status` (`order_status`)", "Verified `" . WEB_ORDER_REQ . "` status index.");
    migrationEnsureIndex($conn, $db_fin, WEB_ORDER_REQ, 'idx_web_order_code', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD INDEX `idx_web_order_code` (`order_id`)", "Verified `" . WEB_ORDER_REQ . "` order code index.");
    migrationEnsureIndex($conn, $db_fin, WEB_ORDER_REQ, 'idx_web_order_airbill', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD INDEX `idx_web_order_airbill` (`airbill_no`)", "Verified `" . WEB_ORDER_REQ . "` airbill index.");

} else {
    echo "<p style='color:red;'>Failed selecting finance database for OMS migration.</p>";
}

if ($conn->select_db($db_cms)) {
    migrationEnsureColumn($conn, $db_cms, LAZADA_ORDER_REQ, 'airbill_no', "ALTER TABLE `" . LAZADA_ORDER_REQ . "` ADD COLUMN `airbill_no` VARCHAR(150) DEFAULT NULL AFTER `order_status`", "Verified `" . LAZADA_ORDER_REQ . "` includes `airbill_no`.");
    migrationEnsureColumn($conn, $db_cms, LAZADA_ORDER_REQ, 'airbill_attachment', "ALTER TABLE `" . LAZADA_ORDER_REQ . "` ADD COLUMN `airbill_attachment` TEXT DEFAULT NULL AFTER `airbill_no`", "Verified `" . LAZADA_ORDER_REQ . "` includes `airbill_attachment`.");
    migrationEnsureColumn($conn, $db_cms, LAZADA_ORDER_REQ, 'stock_out_warehouse_id', "ALTER TABLE `" . LAZADA_ORDER_REQ . "` ADD COLUMN `stock_out_warehouse_id` INT DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . LAZADA_ORDER_REQ . "` includes `stock_out_warehouse_id`.");
    migrationEnsureColumn($conn, $db_cms, LAZADA_ORDER_REQ, 'delay_remark', "ALTER TABLE `" . LAZADA_ORDER_REQ . "` ADD COLUMN `delay_remark` TEXT DEFAULT NULL AFTER `stock_out_warehouse_id`", "Verified `" . LAZADA_ORDER_REQ . "` includes `delay_remark`.");
    migrationEnsureIndex($conn, $db_cms, LAZADA_ORDER_REQ, 'idx_lazada_order_status', "ALTER TABLE `" . LAZADA_ORDER_REQ . "` ADD INDEX `idx_lazada_order_status` (`order_status`)", "Verified `" . LAZADA_ORDER_REQ . "` status index.");
    migrationEnsureIndex($conn, $db_cms, LAZADA_ORDER_REQ, 'idx_lazada_order_code', "ALTER TABLE `" . LAZADA_ORDER_REQ . "` ADD INDEX `idx_lazada_order_code` (`oder_number`)", "Verified `" . LAZADA_ORDER_REQ . "` order code index.");
    migrationEnsureIndex($conn, $db_cms, LAZADA_ORDER_REQ, 'idx_lazada_order_airbill', "ALTER TABLE `" . LAZADA_ORDER_REQ . "` ADD INDEX `idx_lazada_order_airbill` (`airbill_no`)", "Verified `" . LAZADA_ORDER_REQ . "` airbill index.");
} else {
    echo "<p style='color:red;'>Failed selecting CMS database for Lazada OMS migration.</p>";
}

// if ($conn->select_db($db_cms)) {
//     // Update Dashboard pin group (id=7): remove login/logout actions (7,8).
//     $dashboardPinResult = $conn->query("SELECT `pins` FROM `pin_group` WHERE `id` = 7 LIMIT 1");
//     if ($dashboardPinResult && $dashboardPinResult->num_rows > 0) {
//         $dashboardPinRow = $dashboardPinResult->fetch_assoc();
//         $updatedDashboardPins = removePinAccessIds($dashboardPinRow['pins'], array(7, 8));
//         $safeDashboardPins = $conn->real_escape_string($updatedDashboardPins);

//         if ($conn->query("UPDATE `pin_group` SET `pins` = '{$safeDashboardPins}' WHERE `id` = 7")) {
//             echo "<p style='color:green;'>Verified `pin_group` id 7 (Dashboard) removed pin access 7 and 8.</p>";
//         } else {
//             echo "<p style='color:red;'>Failed updating `pin_group` id 7: " . $conn->error . "</p>";
//         }
//     } else {
//         echo "<p style='color:orange;'>`pin_group` id 7 not found. Skipped dashboard pin update.</p>";
//     }

//     // Update all user groups: for pin block [7:...], remove access 7 and 8.
//     $userGroupResult = $conn->query("SELECT `id`, `pins` FROM `user_group`");
//     if ($userGroupResult) {
//         $updatedCount = 0;

//         while ($userGroupRow = $userGroupResult->fetch_assoc()) {
//             $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
//             $updatedPins = removeAccessFromPinBlock($currentPins, 7, array(7, 8));

//             if ($updatedPins !== $currentPins) {
//                 $safePins = $conn->real_escape_string($updatedPins);
//                 $groupId = (int) $userGroupRow['id'];

//                 if ($conn->query("UPDATE `user_group` SET `pins` = '{$safePins}' WHERE `id` = {$groupId}")) {
//                     $updatedCount++;
//                 } else {
//                     echo "<p style='color:red;'>Failed updating `user_group` id {$groupId}: " . $conn->error . "</p>";
//                 }
//             }
//         }

//         echo "<p style='color:green;'>Verified user groups updated for pin block [7:*], removed access 7 and 8. Updated rows: {$updatedCount}.</p>";
//     } else {
//         echo "<p style='color:red;'>Failed reading `user_group` table: " . $conn->error . "</p>";
//     }
// } else {
//     echo "<p style='color:red;'>Failed selecting CMS database for dashboard/user-group pin updates.</p>";
// }

// if ($conn->select_db($db_cms)) {
//     // Export action id is 6. Ensure these pin groups include 6 for Export button control.
//     $exportPinGroupIds = array(51, 59, 61, 65, 66, 69, 78, 87, 89, 92, 93, 123);
//     $pinIdSql = implode(',', $exportPinGroupIds);
//     $pinGroupRows = array();

//     $pinGroupResult = $conn->query("SELECT `id`, `pins` FROM `pin_group` WHERE `id` IN (" . $pinIdSql . ")");
//     if ($pinGroupResult) {
//         while ($pinGroupRow = $pinGroupResult->fetch_assoc()) {
//             $groupId = (int) $pinGroupRow['id'];
//             $pinGroupRows[$groupId] = isset($pinGroupRow['pins']) ? (string) $pinGroupRow['pins'] : '';

//             $updatedPins = addPinAccessIds($pinGroupRows[$groupId], array(6));
//             if ($updatedPins !== $pinGroupRows[$groupId]) {
//                 $safePins = $conn->real_escape_string($updatedPins);
//                 if ($conn->query("UPDATE `pin_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . $groupId)) {
//                     echo "<p style='color:green;'>Verified `pin_group` id " . $groupId . " updated with Export pin access 6.</p>";
//                     $pinGroupRows[$groupId] = $updatedPins;
//                 } else {
//                     echo "<p style='color:red;'>Failed updating `pin_group` id " . $groupId . " with Export pin access 6: " . $conn->error . "</p>";
//                 }
//             } else {
//                 echo "<p style='color:green;'>Verified `pin_group` id " . $groupId . " already contains Export pin access 6.</p>";
//             }
//         }
//     } else {
//         echo "<p style='color:red;'>Failed reading `pin_group` for Export pin access update: " . $conn->error . "</p>";
//     }

//     foreach ($exportPinGroupIds as $groupIdCheck) {
//         if (!isset($pinGroupRows[(int) $groupIdCheck])) {
//             echo "<p style='color:orange;'>`pin_group` id " . (int) $groupIdCheck . " not found. Skipped Export pin access update.</p>";
//         }
//     }

//     // Update only Super Admin (1) and Admin (2), as requested.
//     $targetUserGroupIds = array(1, 2);
//     foreach ($targetUserGroupIds as $userGroupId) {
//         $userGroupResult = $conn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $userGroupId . " LIMIT 1");
//         if (!$userGroupResult || $userGroupResult->num_rows === 0) {
//             echo "<p style='color:orange;'>`user_group` id " . (int) $userGroupId . " not found. Skipped Export pin access update.</p>";
//             continue;
//         }

//         $userGroupRow = $userGroupResult->fetch_assoc();
//         $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
//         $updatedPins = $currentPins;

//         foreach ($exportPinGroupIds as $exportPinGroupId) {
//             $updatedPins = addAccessToPinBlock($updatedPins, $exportPinGroupId, array(6));
//         }

//         if ($updatedPins !== $currentPins) {
//             $safePins = $conn->real_escape_string($updatedPins);
//             if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $userGroupId)) {
//                 echo "<p style='color:green;'>Verified `user_group` id " . (int) $userGroupId . " updated with Export pin access 6 for target pin groups.</p>";
//             } else {
//                 echo "<p style='color:red;'>Failed updating `user_group` id " . (int) $userGroupId . " for Export pin access 6: " . $conn->error . "</p>";
//                 continue;
//             }
//         } else {
//             echo "<p style='color:green;'>Verified `user_group` id " . (int) $userGroupId . " already contains Export pin access 6 for target pin groups.</p>";
//         }

//         $missingExportBlocks = array();
//         foreach ($exportPinGroupIds as $exportPinGroupId) {
//             if (!pinBlockHasAccessId($updatedPins, $exportPinGroupId, 6)) {
//                 $missingExportBlocks[] = (int) $exportPinGroupId;
//             }
//         }

//         if (empty($missingExportBlocks)) {
//             echo "<p style='color:green;'>Verified `user_group` id " . (int) $userGroupId . " has Export pin access 6 on all target pin groups.</p>";
//         } else {
//             echo "<p style='color:red;'>Verification failed for `user_group` id " . (int) $userGroupId . ". Missing Export pin access 6 on pin groups: " . implode(',', $missingExportBlocks) . "</p>";
//         }
//     }
// } else {
//     echo "<p style='color:red;'>Failed selecting CMS database for Export pin migration update.</p>";
// }

if ($conn->select_db($db_cms)) {
    $leavePinGroupIds = array(73, 71, 27, 24);
    $targetUserGroupIds = array(1, 2);

    foreach ($targetUserGroupIds as $userGroupId) {
        $userGroupResult = $conn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $userGroupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>`user_group` id " . (int) $userGroupId . " not found. Skipped leave pin cleanup.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = $currentPins;

        foreach ($leavePinGroupIds as $leavePinGroupId) {
            $updatedPins = removePinBlockById($updatedPins, $leavePinGroupId);
        }

        if ($updatedPins !== $currentPins) {
            $safePins = $conn->real_escape_string($updatedPins);
            if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $userGroupId)) {
                echo "<p style='color:green;'>Verified `user_group` id " . (int) $userGroupId . " removed leave pin groups 73, 71, 27, 24.</p>";
            } else {
                echo "<p style='color:red;'>Failed updating `user_group` id " . (int) $userGroupId . " for leave pin cleanup: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified `user_group` id " . (int) $userGroupId . " already does not contain leave pin groups 73, 71, 27, 24.</p>";
        }
    }

    $leavePinGroupIdSql = implode(',', array_map('intval', $leavePinGroupIds));
    $pinGroupResult = $conn->query("SELECT `id`, `status` FROM `pin_group` WHERE `id` IN (" . $leavePinGroupIdSql . ")");

    if ($pinGroupResult) {
        $foundPinGroups = array();
        $actorUserId = defined('USER_ID') ? (string) USER_ID : (isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '');
        $safeActorUserId = $conn->real_escape_string($actorUserId);

        while ($pinGroupRow = $pinGroupResult->fetch_assoc()) {
            $pinGroupId = (int) $pinGroupRow['id'];
            $foundPinGroups[$pinGroupId] = true;
            $currentStatus = isset($pinGroupRow['status']) ? (string) $pinGroupRow['status'] : '';

            if ($currentStatus === 'D') {
                echo "<p style='color:green;'>Verified `pin_group` id " . $pinGroupId . " is already soft deleted.</p>";
                continue;
            }

            $softDeleteSql = "UPDATE `pin_group`
                SET `status` = 'D',
                    `update_by` = '" . $safeActorUserId . "',
                    `update_date` = CURDATE(),
                    `update_time` = CURTIME()
                WHERE `id` = " . $pinGroupId;

            if ($conn->query($softDeleteSql)) {
                echo "<p style='color:green;'>Verified `pin_group` id " . $pinGroupId . " soft deleted.</p>";
            } else {
                echo "<p style='color:red;'>Failed soft deleting `pin_group` id " . $pinGroupId . ": " . $conn->error . "</p>";
            }
        }

        foreach ($leavePinGroupIds as $leavePinGroupId) {
            if (!isset($foundPinGroups[(int) $leavePinGroupId])) {
                echo "<p style='color:orange;'>`pin_group` id " . (int) $leavePinGroupId . " not found. Skipped soft delete.</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Failed reading `pin_group` for leave pin cleanup: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red;'>Failed selecting CMS database for leave pin cleanup.</p>";
}

$conn->close();
