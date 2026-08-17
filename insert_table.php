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

if (!$conn->set_charset('utf8mb4')) {
    die("Failed setting database connection charset to utf8mb4: " . $conn->error);
}

// 2.1 Select Financial Database
if (!$conn->select_db($db_fin)) {
    die('Unable to select database `' . $db_fin . '`: ' . $conn->error);
}

// ==========================================
// HELPER FUNCTIONS (FIXED)
// ==========================================

function columnExists($conn, $dbName, $tblName, $columnName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn' LIMIT 1";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

function addColumnIfMissing($conn, $dbName, $tblName, $columnName, $alterSql)
{
    if (!columnExists($conn, $dbName, $tblName, $columnName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Added column `$columnName` to `$tblName`.</p>";
        } else {
            echo "<p style='color:red;'>Failed adding `$columnName` to `$tblName`: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green;'>Verified column `$columnName` already exists in `$tblName`.</p>";
    }
}

function dropColumnIfExists($conn, $dbName, $tblName, $columnName, $alterSql)
{
    if (columnExists($conn, $dbName, $tblName, $columnName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Dropped column `$columnName` from `$tblName`.</p>";
        } else {
            echo "<p style='color:red;'>Failed dropping `$columnName` from `$tblName`: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green;'>Verified column `$columnName` is already removed from `$tblName`.</p>";
    }
}

function alterColumnToVarcharIfInt($conn, $dbName, $tblName, $columnName, $varcharLen = 255)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeColumn = $conn->real_escape_string($columnName);

    $sql = "SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn' LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        echo "<p style='color:orange;'>Column `$columnName` not found in `$tblName` to alter.</p>";
        return;
    }

    $row = $result->fetch_assoc();
    if ($row) {
        $row = array_change_key_case($row, CASE_LOWER);
    }

    if (isset($row['data_type'])) {
        $dataType = strtolower((string) $row['data_type']);
        if (strpos($dataType, 'int') !== false) {
            $alterSql = "ALTER TABLE `$tblName` MODIFY COLUMN `$columnName` VARCHAR(" . (int) $varcharLen . ") NULL";
            if ($conn->query($alterSql)) {
                echo "<p style='color:blue;'>Updated `$tblName`.`$columnName` to VARCHAR(" . (int) $varcharLen . ").</p>";
            } else {
                echo "<p style='color:red;'>Failed updating `$tblName`.`$columnName`: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified `$tblName`.`$columnName` is already non-integer ($dataType).</p>";
        }
    }
}

function alterColumnToTextIfVarchar($conn, $dbName, $tblName, $columnName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn' LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        echo "<p style='color:orange;'>Column `$columnName` not found in `$tblName` to alter.</p>";
        return;
    }

    $row = $result->fetch_assoc();
    if ($row) {
        $row = array_change_key_case($row, CASE_LOWER);
    }

    if (isset($row['data_type'])) {
        $dataType = strtolower((string) $row['data_type']);
        if ($dataType === 'varchar') {
            $alterSql = "ALTER TABLE `$tblName` MODIFY COLUMN `$columnName` TEXT NULL";
            if ($conn->query($alterSql)) {
                echo "<p style='color:blue;'>Updated `$tblName`.`$columnName` to TEXT.</p>";
            } else {
                echo "<p style='color:red;'>Failed updating `$tblName`.`$columnName` to TEXT: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified `$tblName`.`$columnName` is already non-varchar ($dataType).</p>";
        }
    }
}

function ensureVarcharColumnLengthAtLeast($conn, $dbName, $tblName, $columnName, $minLength = 255, $defaultValue = '')
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeColumn = $conn->real_escape_string($columnName);
    $qualifiedTable = "`" . str_replace('`', '``', $dbName) . "`.`" . str_replace('`', '``', $tblName) . "`";
    $result = $conn->query("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
        FROM information_schema.columns
        WHERE table_schema='$safeDb' AND table_name='$safeTable' AND column_name='$safeColumn'
        LIMIT 1");

    if (!$result || $result->num_rows === 0) {
        echo "<p style='color:orange;'>Column `$columnName` not found in `$tblName` to verify length.</p>";
        return;
    }

    $row = $result->fetch_assoc();
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
            echo "<p style='color:blue;'>Verified `$tblName`.`$columnName` supports VARCHAR(" . (int) $minLength . ").</p>";
        } else {
            echo "<p style='color:red;'>Failed updating `$tblName`.`$columnName` length: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green;'>Verified `$tblName`.`$columnName` already supports VARCHAR(" . (int) $currentLength . ").</p>";
    }
}

function insertTableEnsureOrderReportPins($cmsConn)
{
    $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (155, 'Shopee Report', '1', 'Shopee order report view access', '1', CURDATE(), CURTIME(), 'A'),
        (156, 'Facebook Report', '1', 'Facebook order report view access', '1', CURDATE(), CURTIME(), 'A'),
        (157, 'Website Report', '1', 'Website order report view access', '1', CURDATE(), CURTIME(), 'A'),
        (158, 'Lazada Report', '1', 'Lazada order report view access', '1', CURDATE(), CURTIME(), 'A'),
        (166, 'Stock Order Request Report', '1', 'Stock order request report view access', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";

    if ($cmsConn->query($pinGroupSql)) {
        echo "<p style='color:green;'><strong>Order Report pin setup:</strong> Verified pin groups 155-158 and 166 for Shopee Report, Facebook Report, Website Report, Lazada Report, and Stock Order Request Report.</p>";
    } else {
        echo "<p style='color:red;'><strong>Order Report pin setup:</strong> Failed creating pin groups 155-158 and 166: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
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
        foreach (array(155, 156, 157, 158, 166) as $pinGroupId) {
            $updatedPins = addAccessToPinBlock($updatedPins, $pinGroupId, array(1));
        }

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Order Report pin setup granted View access for pin groups 155-158 and 166 to `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Order Report pin setup failed updating `user_group` id " . (int) $groupId . ": " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Order Report pin setup verified View access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
    }
}

function insertTableEnsureLuckyDrawPins($cmsConn)
{
    $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (159, 'Lucky Draw', '1,2,3,4,5,6', 'Lucky Draw admin management', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";

    if ($cmsConn->query($pinGroupSql)) {
        echo "<p style='color:green;'><strong>Lucky Draw pin setup:</strong> Verified pin group 159 for Lucky Draw.</p>";
    } else {
        echo "<p style='color:red;'><strong>Lucky Draw pin setup:</strong> Failed creating pin group 159: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    foreach (array(1, 2) as $groupId) {
        $userGroupResult = $cmsConn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>Lucky Draw pin setup skipped `user_group` id " . (int) $groupId . " because the group was not found.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 159, array(1, 2, 3, 4, 5, 6));

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Lucky Draw pin setup granted access for pin group 159 to `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Lucky Draw pin setup failed updating `user_group` id " . (int) $groupId . ": " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Lucky Draw pin setup verified access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
    }
}

function insertTableEnsureLazadaImportPin($cmsConn)
{
    $pinGroupId = 93;

    $pinGroupResult = $cmsConn->query("SELECT `pins` FROM `pin_group` WHERE `id` = " . (int) $pinGroupId . " LIMIT 1");
    if ($pinGroupResult && $pinGroupResult->num_rows > 0) {
        $pinGroupRow = $pinGroupResult->fetch_assoc();
        $currentPins = isset($pinGroupRow['pins']) ? (string) $pinGroupRow['pins'] : '';
        $updatedPins = addPinAccessIds($currentPins, array(5));

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `pin_group` SET `pins` = '" . $safePins . "', `status` = 'A' WHERE `id` = " . (int) $pinGroupId)) {
                echo "<p style='color:green;'>Lazada Import pin setup verified Import access for pin group 93.</p>";
            } else {
                echo "<p style='color:red;'>Lazada Import pin setup failed updating pin group 93: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Lazada Import pin setup verified pin group 93 already has Import access.</p>";
        }
    } else {
        $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
            (93, 'Lazada Order Request', '1,2,3,4,5', 'Lazada order request access with import', '1', CURDATE(), CURTIME(), 'A')";

        if ($cmsConn->query($pinGroupSql)) {
            echo "<p style='color:green;'>Lazada Import pin setup created pin group 93 with Import access.</p>";
        } else {
            echo "<p style='color:red;'>Lazada Import pin setup failed creating pin group 93: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }

    foreach (array(1, 2) as $groupId) {
        $userGroupResult = $cmsConn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>Lazada Import pin setup skipped `user_group` id " . (int) $groupId . " because the group was not found.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 93, array(5));

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Lazada Import pin setup granted Import access for pin group 93 to `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Lazada Import pin setup failed updating `user_group` id " . (int) $groupId . ": " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Lazada Import pin setup verified Import access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
    }
}

function insertTableEnsureWebsiteOrderImportPin($cmsConn)
{
    $pinGroupId = 92;
    $pinGroupResult = $cmsConn->query("SELECT `pins` FROM `pin_group` WHERE `id` = " . (int) $pinGroupId . " LIMIT 1");

    if ($pinGroupResult && $pinGroupResult->num_rows > 0) {
        $pinGroupRow = $pinGroupResult->fetch_assoc();
        $currentPins = isset($pinGroupRow['pins']) ? (string) $pinGroupRow['pins'] : '';
        $updatedPins = addPinAccessIds($currentPins, array(5));
        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `pin_group` SET `pins` = '" . $safePins . "', `status` = 'A' WHERE `id` = " . (int) $pinGroupId)) {
                echo "<p style='color:green;'>Website Order Import pin setup verified Import access for pin group 92.</p>";
            } else {
                echo "<p style='color:red;'>Website Order Import pin setup failed updating pin group 92: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Website Order Import pin setup verified pin group 92 already has Import access.</p>";
        }
    } else {
        $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
            (92, 'Website Order Request', '1,2,3,4,5', 'Website order request access with import', '1', CURDATE(), CURTIME(), 'A')";
        if ($cmsConn->query($pinGroupSql)) {
            echo "<p style='color:green;'>Website Order Import pin setup created pin group 92 with Import access.</p>";
        } else {
            echo "<p style='color:red;'>Website Order Import pin setup failed creating pin group 92: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }

    $userGroupResult = $cmsConn->query("SELECT `id`, `pins` FROM `user_group` ORDER BY `id` ASC");
    if (!$userGroupResult) {
        echo "<p style='color:red;'>Website Order Import pin setup failed loading all user groups: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    } else {
        while ($userGroupRow = $userGroupResult->fetch_assoc()) {
            $groupId = isset($userGroupRow['id']) ? (int) $userGroupRow['id'] : 0;
            if ($groupId <= 0) {
                continue;
            }

            $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
            $updatedPins = addAccessToPinBlock($currentPins, $pinGroupId, array(5));
            if ($updatedPins !== $currentPins) {
                $safePins = $cmsConn->real_escape_string($updatedPins);
                if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                    echo "<p style='color:green;'>Website Order Import pin setup granted Import access for pin group 92 to `user_group` id " . (int) $groupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Website Order Import pin setup failed updating `user_group` id " . (int) $groupId . ": " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
                }
            } else {
                echo "<p style='color:green;'>Website Order Import pin setup verified Import access already exists for pin group 92 to `user_group` id " . (int) $groupId . ".</p>";
            }
        }
    }
}

function insertTableEnsurePackageParentSkuColumns($cmsConn, $dbCms)
{
    if (!($cmsConn instanceof mysqli)) {
        return;
    }

    $safeDb = $cmsConn->real_escape_string($dbCms);
    $qualifiedPackageTable = "`" . str_replace("`", "``", $dbCms) . "`.`package`";

    $tableResult = $cmsConn->query("SELECT 1
        FROM information_schema.tables
        WHERE table_schema = '" . $safeDb . "'
          AND table_name = 'package'
        LIMIT 1");

    if (!$tableResult || $tableResult->num_rows === 0) {
        echo "<p style='color:red;'>Package parent SKU setup failed: `package` table does not exist in `" . htmlspecialchars($dbCms, ENT_QUOTES, 'UTF-8') . "`.</p>";
        return;
    }

    $packageColumns = array(
        'platform_item_id' => array(
            'definition' => 'TEXT DEFAULT NULL',
            'after' => 'item_code',
        ),
        'parent_package_id' => array(
            'definition' => 'INT DEFAULT NULL',
            'after' => 'product',
        ),
    );

    foreach ($packageColumns as $columnName => $columnConfig) {
        $safeColumnName = $cmsConn->real_escape_string($columnName);
        $columnResult = $cmsConn->query("SELECT 1
            FROM information_schema.columns
            WHERE table_schema = '" . $safeDb . "'
              AND table_name = 'package'
              AND column_name = '" . $safeColumnName . "'
            LIMIT 1");

        if ($columnResult && $columnResult->num_rows > 0) {
            echo "<p style='color:green;'>Verified `package`.`" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "` already exists.</p>";
            continue;
        }

        $afterSql = '';
        $afterColumnName = isset($columnConfig['after']) ? trim((string) $columnConfig['after']) : '';
        if ($afterColumnName !== '') {
            $safeAfterColumnName = $cmsConn->real_escape_string($afterColumnName);
            $afterResult = $cmsConn->query("SELECT 1
                FROM information_schema.columns
                WHERE table_schema = '" . $safeDb . "'
                  AND table_name = 'package'
                  AND column_name = '" . $safeAfterColumnName . "'
                LIMIT 1");

            if ($afterResult && $afterResult->num_rows > 0) {
                $afterSql = " AFTER `" . str_replace("`", "``", $afterColumnName) . "`";
            }
        }

        $alterColumnSql = "ALTER TABLE " . $qualifiedPackageTable . "
            ADD COLUMN `" . str_replace("`", "``", $columnName) . "` " . $columnConfig['definition'] . $afterSql;

        if ($cmsConn->query($alterColumnSql)) {
            echo "<p style='color:green;'>Added `package`.`" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "`.</p>";
        } else {
            echo "<p style='color:red;'>Failed adding `package`.`" . htmlspecialchars($columnName, ENT_QUOTES, 'UTF-8') . "`: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
        }
    }

    $indexResult = $cmsConn->query("SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = '" . $safeDb . "'
          AND table_name = 'package'
          AND index_name = 'idx_package_parent_package_id'
        LIMIT 1");

    if ($indexResult && $indexResult->num_rows > 0) {
        echo "<p style='color:green;'>Verified `package`.`idx_package_parent_package_id` already exists.</p>";
        return;
    }

    if ($cmsConn->query("ALTER TABLE " . $qualifiedPackageTable . " ADD INDEX `idx_package_parent_package_id` (`parent_package_id`)")) {
        echo "<p style='color:green;'>Added `package`.`idx_package_parent_package_id`.</p>";
    } else {
        echo "<p style='color:red;'>Failed adding `package`.`idx_package_parent_package_id`: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }
}

function insertTableEnsureSupplierInvoiceSetup($conn, $cmsConn, $dbFin)
{
    $supplierInvoiceTable = defined('SUPPLIER_INVOICE') ? SUPPLIER_INVOICE : 'supplier_invoice';
    $supplierInvoiceQrTable = defined('SUPPLIER_INVOICE_QR') ? SUPPLIER_INVOICE_QR : 'supplier_invoice_qr';

    $createInvoiceSql = "CREATE TABLE IF NOT EXISTS `" . $dbFin . "`.`" . $supplierInvoiceTable . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_no` VARCHAR(100) NOT NULL,
        `doc_date` DATE NOT NULL,
        `description` TEXT DEFAULT NULL,
        `control_account` VARCHAR(9) DEFAULT NULL,
        `code` VARCHAR(9) DEFAULT NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `odr` VARCHAR(255) DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_supplier_invoice_doc_no` (`doc_no`),
        KEY `idx_supplier_invoice_doc_date` (`doc_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createInvoiceSql)) {
        echo "<p style='color:green;'>Verified table `" . htmlspecialchars($supplierInvoiceTable, ENT_QUOTES, 'UTF-8') . "`.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating table `" . htmlspecialchars($supplierInvoiceTable, ENT_QUOTES, 'UTF-8') . "`: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    $createQrSql = "CREATE TABLE IF NOT EXISTS `" . $dbFin . "`.`" . $supplierInvoiceQrTable . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `supplier_invoice_id` INT NOT NULL,
        `qr_url` TEXT NOT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_supplier_invoice_qr_invoice_id` (`supplier_invoice_id`),
        KEY `idx_supplier_invoice_qr_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createQrSql)) {
        echo "<p style='color:green;'>Verified table `" . htmlspecialchars($supplierInvoiceQrTable, ENT_QUOTES, 'UTF-8') . "`.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating table `" . htmlspecialchars($supplierInvoiceQrTable, ENT_QUOTES, 'UTF-8') . "`: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    if (!($cmsConn instanceof mysqli)) {
        return;
    }

    $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (167, 'Supplier Invoice', '1,2,3,4,5,6', 'Supplier invoice access', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";

    if ($cmsConn->query($pinGroupSql)) {
        echo "<p style='color:green;'>Verified pin group 167 for Supplier Invoice.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating pin group 167: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    foreach (array(1, 2) as $groupId) {
        $userGroupResult = $cmsConn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>Supplier Invoice pin setup skipped `user_group` id " . (int) $groupId . " because the group was not found.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 167, array(1, 2, 3, 4, 5, 6));

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Supplier Invoice pin setup granted access to `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Supplier Invoice pin setup failed updating `user_group` id " . (int) $groupId . ": " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Supplier Invoice pin setup verified for `user_group` id " . (int) $groupId . ".</p>";
        }
    }
}

function insertTableEnsureSupplierPaymentSetup($conn, $cmsConn, $dbFin)
{
    $supplierPaymentTable = defined('SUPPLIER_PAYMENT') ? SUPPLIER_PAYMENT : 'supplier_payment';

    $createPaymentSql = "CREATE TABLE IF NOT EXISTS `" . $dbFin . "`.`" . $supplierPaymentTable . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `doc_date` DATE NOT NULL,
        `code` VARCHAR(9) NOT NULL,
        `bill_no` VARCHAR(100) NOT NULL,
        `description` TEXT NOT NULL,
        `quantity` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `add_sst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_supplier_payment_doc_date` (`doc_date`),
        KEY `idx_supplier_payment_code` (`code`),
        KEY `idx_supplier_payment_bill_no` (`bill_no`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createPaymentSql)) {
        echo "<p style='color:green;'>Verified table `" . htmlspecialchars($supplierPaymentTable, ENT_QUOTES, 'UTF-8') . "`.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating table `" . htmlspecialchars($supplierPaymentTable, ENT_QUOTES, 'UTF-8') . "`: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    if (!($cmsConn instanceof mysqli)) {
        return;
    }

    $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (169, 'Supplier Payment', '1,2,3,4,5,6', 'Supplier payment access', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";

    if ($cmsConn->query($pinGroupSql)) {
        echo "<p style='color:green;'>Verified pin group 169 for Supplier Payment.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating pin group 169: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    foreach (array(1, 2) as $groupId) {
        $userGroupResult = $cmsConn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>Supplier Payment pin setup skipped `user_group` id " . (int) $groupId . " because the group was not found.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 169, array(1, 2, 3, 4, 5, 6));

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Supplier Payment pin setup granted access to `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Supplier Payment pin setup failed updating `user_group` id " . (int) $groupId . ": " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Supplier Payment pin setup verified for `user_group` id " . (int) $groupId . ".</p>";
        }
    }
}

function insertTableEnsureFbAdsWhtSubmissionSetup($conn, $cmsConn, $dbFin)
{
    $submissionTable = defined('FB_ADS_WHT_SUBMISSION') ? FB_ADS_WHT_SUBMISSION : 'facebook_ads_topup_wht_submission';
    $createSubmissionSql = "CREATE TABLE IF NOT EXISTS `" . $dbFin . "`.`" . $submissionTable . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `submission_ref` VARCHAR(60) NOT NULL,
        `source_transaction_id` INT NOT NULL,
        `payment_date` DATE DEFAULT NULL,
        `transaction_id` VARCHAR(255) DEFAULT NULL,
        `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `sst` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `remark` TEXT DEFAULT NULL,
        `attachment` VARCHAR(255) DEFAULT NULL,
        `submission_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_fb_ads_wht_submission_ref` (`submission_ref`),
        KEY `idx_fb_ads_wht_source_transaction` (`source_transaction_id`, `status`),
        KEY `idx_fb_ads_wht_submission_status` (`submission_status`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createSubmissionSql)) {
        echo "<p style='color:green;'>Verified table `" . htmlspecialchars($submissionTable, ENT_QUOTES, 'UTF-8') . "`.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating table `" . htmlspecialchars($submissionTable, ENT_QUOTES, 'UTF-8') . "`: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    if (!($cmsConn instanceof mysqli)) {
        return;
    }

    $pinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
        (168, 'FB-Ads WHT Submission', '1,2,3,4', 'Facebook Ads WHT submission access', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";

    if ($cmsConn->query($pinGroupSql)) {
        echo "<p style='color:green;'>Verified pin group 168 for FB-Ads WHT Submission.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating pin group 168: " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }

    foreach (array(1, 2) as $groupId) {
        $userGroupResult = $cmsConn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>FB-Ads WHT Submission pin setup skipped `user_group` id " . (int) $groupId . " because the group was not found.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = addAccessToPinBlock($currentPins, 168, array(1, 2, 3, 4));

        if ($updatedPins !== $currentPins) {
            $safePins = $cmsConn->real_escape_string($updatedPins);
            if ($cmsConn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>FB-Ads WHT Submission pin setup granted access to `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>FB-Ads WHT Submission pin setup failed updating `user_group` id " . (int) $groupId . ": " . htmlspecialchars($cmsConn->error, ENT_QUOTES, 'UTF-8') . "</p>";
            }
        } else {
            echo "<p style='color:green;'>FB-Ads WHT Submission pin setup verified for `user_group` id " . (int) $groupId . ".</p>";
        }
    }
}

$cmsConn = new mysqli($dbhost, $dbUser, $dbpwd, $db_cms, $dbport);
if ($cmsConn->connect_error) {
    insertTableEnsureSupplierInvoiceSetup($conn, null, $db_fin);
    insertTableEnsureSupplierPaymentSetup($conn, null, $db_fin);
    insertTableEnsureFbAdsWhtSubmissionSetup($conn, null, $db_fin);
    echo "<p style='color:red;'><strong>Order Report pin setup:</strong> Failed connecting to CMS database `" . htmlspecialchars($db_cms, ENT_QUOTES, 'UTF-8') . "`: " . htmlspecialchars($cmsConn->connect_error, ENT_QUOTES, 'UTF-8') . "</p>";
} else {
    insertTableEnsureSupplierInvoiceSetup($conn, $cmsConn, $db_fin);
    insertTableEnsureSupplierPaymentSetup($conn, $cmsConn, $db_fin);
    insertTableEnsureFbAdsWhtSubmissionSetup($conn, $cmsConn, $db_fin);
    insertTableEnsurePackageParentSkuColumns($cmsConn, $db_cms);
    migrationEnsureTableUnicodeInnoDb($cmsConn, $db_cms, PKG);
    migrationEnsureTableUnicodeInnoDb($cmsConn, $db_cms, WEB_CUST_RCD);
    migrationEnsureIndex($cmsConn, $db_cms, WEB_CUST_RCD, 'idx_customer_website_name_status', "ALTER TABLE `" . WEB_CUST_RCD . "` ADD INDEX `idx_customer_website_name_status` (`status`, `name`(191))", "Verified `" . WEB_CUST_RCD . "` customer name lookup index.");
    migrationEnsureIndex($cmsConn, $db_cms, WEB_CUST_RCD, 'idx_customer_website_shipping_name_status', "ALTER TABLE `" . WEB_CUST_RCD . "` ADD INDEX `idx_customer_website_shipping_name_status` (`status`, `ship_rec_name`(191))", "Verified `" . WEB_CUST_RCD . "` shipping name lookup index.");
    insertTableEnsureOrderReportPins($cmsConn);
    insertTableEnsureLuckyDrawPins($cmsConn);
    insertTableEnsureLazadaImportPin($cmsConn);
    insertTableEnsureWebsiteOrderImportPin($cmsConn);
}

function indexExists($conn, $dbName, $tblName, $indexName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeIndex = $conn->real_escape_string($indexName);
    $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema='$safeDb' AND table_name='$safeTable' AND index_name='$safeIndex' LIMIT 1";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

function dropIndexIfExists($conn, $dbName, $tblName, $indexName, $alterSql)
{
    if (indexExists($conn, $dbName, $tblName, $indexName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:blue;'>Dropped index `$indexName` from `$tblName`.</p>";
        } else {
            echo "<p style='color:red;'>Failed dropping index `$indexName` from `$tblName`: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green;'>Verified index `$indexName` is already removed from `$tblName`.</p>";
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
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'received_date', "ALTER TABLE `lazada_order_request` ADD COLUMN `received_date` DATE DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `lazada_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `lazada_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_cms, 'lazada_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `lazada_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");
} else {
    echo "<p style='color:red;'>Unable to select CMS database `" . $db_cms . "` for Lazada Estimate Received Date columns.</p>";
}

if ($conn->select_db($db_fin)) {
    migrationEnsureTableUnicodeInnoDb($conn, $db_fin, 'website_order_request');

    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date` DATE DEFAULT NULL AFTER `remark`");
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'received_date', "ALTER TABLE `facebook_order_request` ADD COLUMN `received_date` DATE DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_fin, 'facebook_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `facebook_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");

    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date` DATE DEFAULT NULL AFTER `remark`");
    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'received_date', "ALTER TABLE `website_order_request` ADD COLUMN `received_date` DATE DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_fin, 'website_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `website_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");

    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date` DATE DEFAULT NULL AFTER `remark`");
    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'received_date', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `received_date` DATE DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date_assigned_by', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date_assigned_by` VARCHAR(30) DEFAULT NULL AFTER `estimated_received_date`");
    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date_assigned_date', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date_assigned_date` DATE DEFAULT NULL AFTER `estimated_received_date_assigned_by`");
    addColumnIfMissing($conn, $db_fin, 'shopee_sg_order_request', 'estimated_received_date_assigned_time', "ALTER TABLE `shopee_sg_order_request` ADD COLUMN `estimated_received_date_assigned_time` TIME DEFAULT NULL AFTER `estimated_received_date_assigned_date`");
} else {
    echo "<p style='color:red;'>Unable to select Finance database `" . $db_fin . "` for Estimate Received Date columns.</p>";
}
// addColumnIfMissing($conn, $db_fin, 'shopee_customer_info', 'contact_no', "ALTER TABLE `shopee_customer_info` ADD COLUMN `contact_no` VARCHAR(30) DEFAULT NULL AFTER `series`");
// addColumnIfMissing($conn, $db_fin, 'shopee_ads_topup_transaction', 'attachment', "ALTER TABLE `shopee_ads_topup_transaction` ADD COLUMN `attachment` VARCHAR(255) DEFAULT NULL AFTER `pay_meth`");
addColumnIfMissing($conn, $db_fin, MERCHANT, 'control_account', "ALTER TABLE `" . MERCHANT . "` ADD COLUMN `control_account` VARCHAR(9) DEFAULT NULL AFTER `business_no`");
addColumnIfMissing($conn, $db_fin, MERCHANT, 'code', "ALTER TABLE `" . MERCHANT . "` ADD COLUMN `code` VARCHAR(9) DEFAULT NULL AFTER `control_account`");
if (columnExists($conn, $db_fin, MERCHANT, 'control_account')) {
    if ($conn->query("ALTER TABLE `" . MERCHANT . "` MODIFY COLUMN `control_account` VARCHAR(9) DEFAULT NULL")) {
        echo "<p style='color:green;'>Verified `" . MERCHANT . "`.`control_account` supports format 123-ABC01.</p>";
    } else {
        echo "<p style='color:red;'>Failed updating `" . MERCHANT . "`.`control_account`: " . $conn->error . "</p>";
    }
}
if (columnExists($conn, $db_fin, MERCHANT, 'code')) {
    if ($conn->query("ALTER TABLE `" . MERCHANT . "` MODIFY COLUMN `code` VARCHAR(9) DEFAULT NULL")) {
        echo "<p style='color:green;'>Verified `" . MERCHANT . "`.`code` supports format 123-ABC01.</p>";
    } else {
        echo "<p style='color:red;'>Failed updating `" . MERCHANT . "`.`code`: " . $conn->error . "</p>";
    }
}

addColumnIfMissing($conn, $db_fin, 'stock_in_order', 'stock_type', "ALTER TABLE `stock_in_order` ADD COLUMN `stock_type` VARCHAR(20) NOT NULL DEFAULT 'Stock In' AFTER `attachment`");

if ($conn->select_db($db_fin)) {
    addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'stock_order_image', "ALTER TABLE `stock_order_request` ADD COLUMN `stock_order_image` VARCHAR(255) DEFAULT NULL AFTER `attachment`");
    addColumnIfMissing($conn, $db_fin, 'stock_order_request', 'e_invoicing_status', "ALTER TABLE `stock_order_request` ADD COLUMN `e_invoicing_status` BOOLEAN NOT NULL DEFAULT FALSE AFTER `qr_image`");
} else {
    echo "<p style='color:red;'>Unable to select Finance database `" . $db_fin . "` for `stock_order_request.stock_order_image`.</p>";
}

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
    addColumnIfMissing($conn, $db_cms, WHSE, 'telegram_token_setting_id', "ALTER TABLE `" . $safeCmsDb . "`.`" . WHSE . "` ADD COLUMN `telegram_token_setting_id` INT DEFAULT NULL AFTER `name`");
    if (!indexExists($conn, $db_cms, WHSE, 'idx_warehouse_telegram_token_setting_id')) {
        if ($conn->query("ALTER TABLE `" . $safeCmsDb . "`.`" . WHSE . "` ADD INDEX `idx_warehouse_telegram_token_setting_id` (`telegram_token_setting_id`)")) {
            echo "<p style='color:blue;'>Added index `idx_warehouse_telegram_token_setting_id` to `" . WHSE . "`.</p>";
        } else {
            echo "<p style='color:red;'>Failed adding index `idx_warehouse_telegram_token_setting_id` to `" . WHSE . "`: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green;'>Verified index `idx_warehouse_telegram_token_setting_id` already exists in `" . WHSE . "`.</p>";
    }

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

// function migrationTableExists($conn, $dbName, $tblName)
// {
//     $safeDb = $conn->real_escape_string($dbName);
//     $safeTable = $conn->real_escape_string($tblName);
//     $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' LIMIT 1";
//     $result = $conn->query($sql);
//     return ($result && $result->num_rows > 0);
// }

// function migrationColumnExists($conn, $dbName, $tblName, $columnName)
// {
//     $safeDb = $conn->real_escape_string($dbName);
//     $safeTable = $conn->real_escape_string($tblName);
//     $safeColumn = $conn->real_escape_string($columnName);
//     $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND column_name='" . $safeColumn . "' LIMIT 1";
//     $result = $conn->query($sql);
//     return ($result && $result->num_rows > 0);
// }

// function migrationIndexExists($conn, $dbName, $tblName, $indexName)
// {
//     $safeDb = $conn->real_escape_string($dbName);
//     $safeTable = $conn->real_escape_string($tblName);
//     $safeIndex = $conn->real_escape_string($indexName);
//     $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND index_name='" . $safeIndex . "' LIMIT 1";
//     $result = $conn->query($sql);
//     return ($result && $result->num_rows > 0);
// }

function migrationTableExists($conn, $dbName, $tblName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' LIMIT 1";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

function migrationColumnExists($conn, $dbName, $tblName, $columnName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeColumn = $conn->real_escape_string($columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND column_name='" . $safeColumn . "' LIMIT 1";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

function migrationIndexExists($conn, $dbName, $tblName, $indexName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeIndex = $conn->real_escape_string($indexName);
    $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' AND index_name='" . $safeIndex . "' LIMIT 1";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

function migrationEnsureColumn($conn, $dbName, $tblName, $columnName, $alterSql, $successMessage)
{
    if (!migrationColumnExists($conn, $dbName, $tblName, $columnName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:green;'>" . $successMessage . "</p>";
        } else {
            echo "<p style='color:red;'>Failed altering `" . $tblName . "` for column `" . $columnName . "`: " . $conn->error . "</p>";
        }
    }
}

function migrationEnsureColumnWithPreferredAfter($conn, $dbName, $tblName, $columnName, $columnDefinitionSql, $afterColumnName, $successMessage)
{
    if (migrationColumnExists($conn, $dbName, $tblName, $columnName)) {
        return;
    }

    $qualifiedTable = "`" . str_replace('`', '``', $dbName) . "`.`" . str_replace('`', '``', $tblName) . "`";
    $alterSql = "ALTER TABLE " . $qualifiedTable . " ADD COLUMN `" . str_replace('`', '``', $columnName) . "` " . trim((string) $columnDefinitionSql);

    if ($afterColumnName !== '' && migrationColumnExists($conn, $dbName, $tblName, $afterColumnName)) {
        $alterSql .= " AFTER `" . str_replace('`', '``', $afterColumnName) . "`";
    }

    if ($conn->query($alterSql)) {
        echo "<p style='color:green;'>" . $successMessage . "</p>";
    } else {
        echo "<p style='color:red;'>Failed altering `" . $tblName . "` for column `" . $columnName . "`: " . $conn->error . "</p>";
    }
}

function migrationEnsureColumnAfter($conn, $dbName, $tblName, $columnName, $afterColumnName, $modifySql, $successMessage)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
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
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return;
    }

    $row = $result->fetch_assoc();
    $columnPosition = isset($row['column_position']) ? (int) $row['column_position'] : 0;
    $afterPosition = isset($row['after_position']) ? (int) $row['after_position'] : 0;

    if ($columnPosition !== ($afterPosition + 1)) {
        if ($conn->query($modifySql)) {
            echo "<p style='color:blue;'>" . $successMessage . "</p>";
        } else {
            echo "<p style='color:red;'>Failed repositioning `" . $tblName . "`.`" . $columnName . "`: " . $conn->error . "</p>";
        }
    }
}

function migrationEnsureDecimalColumn($conn, $dbName, $tblName, $columnName, $definitionSql, $successMessage)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("SELECT `DATA_TYPE`, `NUMERIC_SCALE` FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = '" . $safeDb . "' AND `TABLE_NAME` = '" . $safeTable . "' AND `COLUMN_NAME` = '" . $safeColumn . "' LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        return;
    }

    $row = $result->fetch_assoc();
    if (strtolower((string) ($row['DATA_TYPE'] ?? '')) === 'decimal' && (int) ($row['NUMERIC_SCALE'] ?? -1) === 4) {
        return;
    }

    $qualifiedTable = "`" . str_replace('`', '``', $dbName) . "`.`" . str_replace('`', '``', $tblName) . "`";
    $qualifiedColumn = "`" . str_replace('`', '``', $columnName) . "`";
    if ($conn->query("ALTER TABLE " . $qualifiedTable . " MODIFY COLUMN " . $qualifiedColumn . " " . trim((string) $definitionSql))) {
        echo "<p style='color:blue;'>" . $successMessage . "</p>";
    } else {
        echo "<p style='color:red;'>Failed changing type for `" . $tblName . "`.`" . $columnName . "`: " . $conn->error . "</p>";
    }
}

function migrationEnsureIndex($conn, $dbName, $tblName, $indexName, $alterSql, $successMessage)
{
    if (!migrationIndexExists($conn, $dbName, $tblName, $indexName)) {
        if ($conn->query($alterSql)) {
            echo "<p style='color:green;'>" . $successMessage . "</p>";
        } else {
            echo "<p style='color:red;'>Failed altering `" . $tblName . "` for index `" . $indexName . "`: " . $conn->error . "</p>";
        }
    }
}

function migrationGetTableEngine($conn, $dbName, $tblName)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $sql = "SELECT ENGINE FROM information_schema.tables WHERE table_schema='" . $safeDb . "' AND table_name='" . $safeTable . "' LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return '';
    }

    $row = $result->fetch_assoc();
    return isset($row['ENGINE']) ? strtoupper((string) $row['ENGINE']) : '';
}

function migrationGetTableRowCount($conn, $dbName, $tblName)
{
    if (!migrationTableExists($conn, $dbName, $tblName)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS total_count FROM `" . $dbName . "`.`" . $tblName . "`";
    $result = $conn->query($sql);
    if ($result && ($row = $result->fetch_assoc())) {
        return isset($row['total_count']) ? (int) $row['total_count'] : 0;
    }

    return 0;
}

function migrationFlagEnabled($value)
{
    $value = strtolower(trim((string) $value));
    return in_array($value, array('1', 'true', 'yes', 'y', 'on'), true);
}

function migrationEnsureTableUnicodeInnoDb($conn, $dbName, $tblName)
{
    if (!migrationTableExists($conn, $dbName, $tblName)) {
        echo "<p style='color:orange;'>Skipped Unicode/InnoDB migration for `" . htmlspecialchars($tblName, ENT_QUOTES, 'UTF-8') . "` because the table does not exist.</p>";
        return;
    }

    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $tableSql = "SELECT ENGINE, TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = '" . $safeDb . "'
          AND TABLE_NAME = '" . $safeTable . "'
        LIMIT 1";
    $tableResult = $conn->query($tableSql);

    if (!$tableResult || !($tableRow = $tableResult->fetch_assoc())) {
        echo "<p style='color:red;'>Failed reading `" . htmlspecialchars($tblName, ENT_QUOTES, 'UTF-8') . "` engine/collation: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
        return;
    }

    $columnSql = "SELECT COUNT(*) AS invalid_column_count
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '" . $safeDb . "'
          AND TABLE_NAME = '" . $safeTable . "'
          AND CHARACTER_SET_NAME IS NOT NULL
          AND (CHARACTER_SET_NAME <> 'utf8mb4' OR COLLATION_NAME <> 'utf8mb4_unicode_ci')";
    $columnResult = $conn->query($columnSql);
    $columnRow = $columnResult ? $columnResult->fetch_assoc() : null;
    $invalidColumnCount = $columnRow ? (int) $columnRow['invalid_column_count'] : 0;

    $isInnoDb = strtoupper((string) $tableRow['ENGINE']) === 'INNODB';
    $hasUnicodeCollation = strtolower((string) $tableRow['TABLE_COLLATION']) === 'utf8mb4_unicode_ci';

    if ($isInnoDb && $hasUnicodeCollation && $invalidColumnCount === 0) {
        echo "<p style='color:green;'>Verified `" . htmlspecialchars($tblName, ENT_QUOTES, 'UTF-8') . "` uses ENGINE=InnoDB and utf8mb4_unicode_ci.</p>";
        return;
    }

    $safeDbIdentifier = str_replace('`', '``', $dbName);
    $safeTableIdentifier = str_replace('`', '``', $tblName);
    $alterSql = "ALTER TABLE `" . $safeDbIdentifier . "`.`" . $safeTableIdentifier . "`
        ENGINE=InnoDB,
        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    if ($conn->query($alterSql)) {
        echo "<p style='color:green;'>Converted `" . htmlspecialchars($tblName, ENT_QUOTES, 'UTF-8') . "` to ENGINE=InnoDB with utf8mb4_unicode_ci for all text columns.</p>";
    } else {
        echo "<p style='color:red;'>Failed converting `" . htmlspecialchars($tblName, ENT_QUOTES, 'UTF-8') . "` to InnoDB/utf8mb4: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
    }
}

function migrationEnsureTableEngineInnoDb($conn, $dbName, $tblName, $options = array())
{
    $options = is_array($options) ? $options : array();
    $allowConvert = !empty($options['allow_convert']);
    $requireFlag = !empty($options['require_flag']);
    $flagName = isset($options['flag_name']) ? trim((string) $options['flag_name']) : '';
    $warningLabel = isset($options['warning_label']) ? trim((string) $options['warning_label']) : $tblName;

    if (!migrationTableExists($conn, $dbName, $tblName)) {
        echo "<p style='color:orange;'>Skipped ENGINE check for `" . $tblName . "` because the table does not exist yet.</p>";
        return;
    }

    $engine = migrationGetTableEngine($conn, $dbName, $tblName);
    if ($engine === 'INNODB') {
        echo "<p style='color:green;'>Verified `" . $tblName . "` ENGINE=InnoDB.</p>";
        return;
    }

    if (!$allowConvert) {
        echo "<p style='color:orange;'>`" . $tblName . "` is currently `" . htmlspecialchars($engine !== '' ? $engine : 'UNKNOWN', ENT_QUOTES, 'UTF-8') . "`. Manual InnoDB conversion is still required for Lucky Draw transactions.</p>";
        return;
    }

    if ($requireFlag) {
        $flagValue = '';
        if ($flagName !== '') {
            if (input($flagName) !== '') {
                $flagValue = input($flagName);
            } else if (filter_has_var(INPUT_POST, $flagName)) {
                $flagValue = post($flagName);
            }
        }

        if (!migrationFlagEnabled($flagValue)) {
            $rowCount = migrationGetTableRowCount($conn, $dbName, $tblName);
            echo "<p style='color:orange;'>" . htmlspecialchars($warningLabel, ENT_QUOTES, 'UTF-8') . " remains `" . htmlspecialchars($engine !== '' ? $engine : 'UNKNOWN', ENT_QUOTES, 'UTF-8') . "` with " . $rowCount . " row(s). To convert intentionally, rerun insert_table.php with `" . htmlspecialchars($flagName, ENT_QUOTES, 'UTF-8') . "=1` after live verification.</p>";
            return;
        }
    }

    if ($conn->query("ALTER TABLE `" . $dbName . "`.`" . $tblName . "` ENGINE=InnoDB")) {
        echo "<p style='color:green;'>Verified `" . $tblName . "` converted to ENGINE=InnoDB.</p>";
    } else {
        echo "<p style='color:red;'>Failed converting `" . $tblName . "` to InnoDB: " . $conn->error . "</p>";
    }
}

function migrationGetSettingValue($conn, $dbName, $tblName, $settingKey)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
    $safeKey = $conn->real_escape_string($settingKey);
    $sql = "SELECT `setting_value` FROM `" . $safeDb . "`.`" . $safeTable . "` WHERE `setting_key` = '" . $safeKey . "' LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    return isset($row['setting_value']) ? (string) $row['setting_value'] : null;
}

function migrationUpsertSetting($conn, $dbName, $tblName, $settingKey, $settingValue, $remark, $actorUserId)
{
    $safeDb = $conn->real_escape_string($dbName);
    $safeTable = $conn->real_escape_string($tblName);
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

function migrationBuildCustomerFollowUpAlertActionUrl($followUpId, $roundId = 0, $notificationType = '')
{
    $followUpId = (int) $followUpId;
    if ($followUpId <= 0) {
        return '';
    }

    $params = array(
        'follow_up_id' => $followUpId,
    );

    $roundId = (int) $roundId;
    if ($roundId > 0) {
        $params['round_id'] = $roundId;
    }

    $notificationType = trim((string) $notificationType);
    if ($notificationType !== '') {
        $params['notification_type'] = $notificationType;
    }

    return siteUrlWithQuery(ROUTE_CUSTOMER_FOLLOW_UP_LIST, $params);
}

function migrationBuildCustomerFollowUpAlertActionUrlFromExisting($actionUrl)
{
    $actionUrl = trim((string) $actionUrl);
    if ($actionUrl === '') {
        return '';
    }

    $queryString = (string) parse_url($actionUrl, PHP_URL_QUERY);
    if ($queryString === '' && strpos($actionUrl, '?') !== false) {
        $queryString = substr($actionUrl, strpos($actionUrl, '?') + 1);
    }

    if ($queryString === '') {
        return '';
    }

    $params = array();
    parse_str($queryString, $params);

    $followUpId = isset($params['follow_up_id']) ? (int) $params['follow_up_id'] : 0;
    $roundId = isset($params['round_id']) ? (int) $params['round_id'] : 0;
    $notificationType = isset($params['notification_type']) ? (string) $params['notification_type'] : '';

    return migrationBuildCustomerFollowUpAlertActionUrl($followUpId, $roundId, $notificationType);
}

function migrationRepairCustomerFollowUpAlertActionUrls($conn, $dbName, $systemAlertTable, $notificationTable, $settingsTable, $actorUserId)
{
    $settingKey = 'customer_follow_up_alert_action_url_fix_v1';

    if (!migrationTableExists($conn, $dbName, $settingsTable)) {
        echo "<p style='color:orange;'>Skipped Customer Follow-Up alert action URL repair because the run-once settings table is unavailable.</p>";
        return;
    }

    $existingMarker = migrationGetSettingValue($conn, $dbName, $settingsTable, $settingKey);
    if ($existingMarker !== null && trim((string) $existingMarker) !== '') {
        echo "<p style='color:green;'>Verified Customer Follow-Up alert action URL repair already ran (`" . htmlspecialchars($settingKey, ENT_QUOTES, 'UTF-8') . "`).</p>";
        return;
    }

    if (
        !migrationTableExists($conn, $dbName, $systemAlertTable)
        || !migrationTableExists($conn, $dbName, $notificationTable)
    ) {
        echo "<p style='color:orange;'>Skipped Customer Follow-Up alert action URL repair because required tables are missing.</p>";
        return;
    }

    $safeDb = str_replace('`', '``', $dbName);
    $safeSystemAlertTable = str_replace('`', '``', $systemAlertTable);
    $safeNotificationTable = str_replace('`', '``', $notificationTable);
    $safeRelatedTable = $conn->real_escape_string($notificationTable);

    $sql = "SELECT sam.`id`, sam.`action_url`, sam.`related_id`,
                   cfun.`follow_up_id`, cfun.`round_id`, cfun.`notification_type`
            FROM `{$safeDb}`.`{$safeSystemAlertTable}` sam
            LEFT JOIN `{$safeDb}`.`{$safeNotificationTable}` cfun
                ON sam.`related_table` = '" . $safeRelatedTable . "'
               AND sam.`related_id` = cfun.`id`
               AND cfun.`status` = 'A'
            WHERE sam.`module_key` = 'customer_follow_up'
              AND sam.`status` = 'A'";
    $result = $conn->query($sql);

    if (!$result) {
        echo "<p style='color:red;'>Failed reading Customer Follow-Up system alerts for action URL repair: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
        return;
    }

    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $scannedCount = count($rows);
    $updatedCount = 0;
    $unchangedCount = 0;
    $skippedCount = 0;

    $updateSql = "UPDATE `{$safeDb}`.`{$safeSystemAlertTable}` SET `action_url` = ? WHERE `id` = ? LIMIT 1";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        echo "<p style='color:red;'>Failed preparing Customer Follow-Up action URL repair statement: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</p>";
        return;
    }

    $startedTransaction = false;

    try {
        $conn->begin_transaction();
        $startedTransaction = true;

        foreach ($rows as $row) {
            $alertId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($alertId <= 0) {
                $skippedCount++;
                continue;
            }

            $expectedUrl = '';
            $followUpId = isset($row['follow_up_id']) ? (int) $row['follow_up_id'] : 0;
            if ($followUpId > 0) {
                $expectedUrl = migrationBuildCustomerFollowUpAlertActionUrl(
                    $followUpId,
                    isset($row['round_id']) ? (int) $row['round_id'] : 0,
                    isset($row['notification_type']) ? (string) $row['notification_type'] : ''
                );
            } else {
                $expectedUrl = migrationBuildCustomerFollowUpAlertActionUrlFromExisting(
                    isset($row['action_url']) ? (string) $row['action_url'] : ''
                );
            }

            if ($expectedUrl === '') {
                $skippedCount++;
                continue;
            }

            $currentUrl = trim((string) (isset($row['action_url']) ? $row['action_url'] : ''));
            if ($currentUrl === $expectedUrl) {
                $unchangedCount++;
                continue;
            }

            $updateStmt->bind_param('si', $expectedUrl, $alertId);
            if (!$updateStmt->execute()) {
                throw new Exception('Failed updating system alert id ' . $alertId . ': ' . $updateStmt->error);
            }

            $updatedCount++;
        }

        $markerValue = 'updated=' . $updatedCount . ';unchanged=' . $unchangedCount . ';skipped=' . $skippedCount . ';scanned=' . $scannedCount;
        $markerRemark = 'One-time repair for wrong Customer Follow-Up system alert action_url path.';
        if (!migrationUpsertSetting($conn, $dbName, $settingsTable, $settingKey, $markerValue, $markerRemark, $actorUserId)) {
            throw new Exception('Failed writing repair marker `' . $settingKey . '`: ' . $conn->error);
        }

        $conn->commit();
        echo "<p style='color:green;'>Customer Follow-Up alert action URL repair completed. Scanned: " . (int) $scannedCount . ", updated: " . (int) $updatedCount . ", unchanged: " . (int) $unchangedCount . ", skipped: " . (int) $skippedCount . ".</p>";
    } catch (Exception $exception) {
        if ($startedTransaction) {
            $conn->rollback();
        }
        echo "<p style='color:red;'>Customer Follow-Up alert action URL repair failed: " . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    }

    $updateStmt->close();
}

function migrationZeroReturnedOrderFinancialValues($conn, $settingsDbName, $settingsTable, $actorUserId)
{
    $settingKey = 'oms_return_status_zero_amounts_v1';

    if (!migrationTableExists($conn, $settingsDbName, $settingsTable)) {
        echo "<p style='color:orange;'>Skipped returned-order financial reset because the run-once settings table is unavailable.</p>";
        return;
    }

    $existingMarker = migrationGetSettingValue($conn, $settingsDbName, $settingsTable, $settingKey);
    if ($existingMarker !== null && trim((string) $existingMarker) !== '') {
        echo "<p style='color:green;'>Verified returned-order financial reset already ran (`" . htmlspecialchars($settingKey, ENT_QUOTES, 'UTF-8') . "`).</p>";
        return;
    }

    $orderConfigs = array(
        array(
            'label' => 'Shopee',
            'db' => dbFinance,
            'table' => SHOPEE_SG_ORDER_REQ,
            'fields' => array('price', 'voucher', 'act_shipping_fee', 'service_fee', 'trans_fee', 'ams_fee', 'fees', 'final_amt'),
        ),
        array(
            'label' => 'Lazada',
            'db' => dbname,
            'table' => LAZADA_ORDER_REQ,
            'fields' => array('item_price_credit', 'commision', 'other_discount', 'pay_fee', 'final_income'),
        ),
        array(
            'label' => 'Facebook',
            'db' => dbFinance,
            'table' => FB_ORDER_REQ,
            'fields' => array('price'),
        ),
        array(
            'label' => 'Website',
            'db' => dbFinance,
            'table' => WEB_ORDER_REQ,
            'fields' => array('price', 'shipping', 'discount', 'total'),
        ),
    );

    $summaries = array();
    $totalReturnedRows = 0;
    $totalUpdatedRows = 0;
    $startedTransaction = false;

    try {
        $conn->begin_transaction();
        $startedTransaction = true;

        foreach ($orderConfigs as $config) {
            $dbName = isset($config['db']) ? (string) $config['db'] : '';
            $tableName = isset($config['table']) ? (string) $config['table'] : '';
            $label = isset($config['label']) ? (string) $config['label'] : $tableName;
            if ($dbName === '' || $tableName === '' || !migrationTableExists($conn, $dbName, $tableName)) {
                $summaries[] = $label . ': skipped (table missing)';
                continue;
            }

            if (!columnExists($conn, $dbName, $tableName, 'order_status')) {
                $summaries[] = $label . ': skipped (order_status missing)';
                continue;
            }

            $availableFields = array();
            foreach ((array) $config['fields'] as $fieldName) {
                $fieldName = trim((string) $fieldName);
                if ($fieldName !== '' && columnExists($conn, $dbName, $tableName, $fieldName)) {
                    $availableFields[] = $fieldName;
                }
            }

            if (empty($availableFields)) {
                $summaries[] = $label . ': skipped (no financial fields)';
                continue;
            }

            $safeDbName = str_replace('`', '``', $dbName);
            $safeTableName = str_replace('`', '``', $tableName);
            $countSql = "SELECT COUNT(*) AS total_count
                FROM `" . $safeDbName . "`.`" . $safeTableName . "`
                WHERE UPPER(TRIM(COALESCE(`order_status`, ''))) IN ('R', 'CR')";
            $countResult = $conn->query($countSql);
            if (!$countResult) {
                throw new Exception('Failed counting returned rows for `' . $tableName . '`: ' . $conn->error);
            }

            $countRow = $countResult->fetch_assoc();
            $returnedRows = isset($countRow['total_count']) ? (int) $countRow['total_count'] : 0;
            $totalReturnedRows += $returnedRows;

            $assignments = array();
            foreach ($availableFields as $fieldName) {
                $assignments[] = "`" . str_replace('`', '``', $fieldName) . "` = '0.00'";
            }

            $updateSql = "UPDATE `" . $safeDbName . "`.`" . $safeTableName . "`
                SET " . implode(', ', $assignments) . "
                WHERE UPPER(TRIM(COALESCE(`order_status`, ''))) IN ('R', 'CR')";
            if (!$conn->query($updateSql)) {
                throw new Exception('Failed resetting returned-order financial values for `' . $tableName . '`: ' . $conn->error);
            }

            $updatedRows = max(0, (int) $conn->affected_rows);
            $totalUpdatedRows += $updatedRows;
            $summaries[] = $label . ': returned=' . $returnedRows . ', updated=' . $updatedRows;
        }

        $markerValue = 'returned=' . $totalReturnedRows . ';updated=' . $totalUpdatedRows;
        if (!empty($summaries)) {
            $markerValue .= ';details=' . implode(' | ', $summaries);
        }
        $markerRemark = 'One-time reset of financial values to 0.00 for OMS orders already in Return or Closed-Returned status.';
        if (!migrationUpsertSetting($conn, $settingsDbName, $settingsTable, $settingKey, $markerValue, $markerRemark, $actorUserId)) {
            throw new Exception('Failed writing run-once marker `' . $settingKey . '`: ' . $conn->error);
        }

        $conn->commit();
        echo "<p style='color:green;'>Returned-order financial reset completed. " . htmlspecialchars(implode(' | ', $summaries), ENT_QUOTES, 'UTF-8') . ".</p>";
    } catch (Exception $exception) {
        if ($startedTransaction) {
            $conn->rollback();
        }
        echo "<p style='color:red;'>Returned-order financial reset failed: " . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    }
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

migrationEnsureColumn($conn, $db_cms, USER_RECORD_LOG, 'summary', "ALTER TABLE `{$db_cms}`.`" . USER_RECORD_LOG . "` ADD COLUMN `summary` TEXT DEFAULT NULL AFTER `attachment`", "Verified `" . USER_RECORD_LOG . "` includes `summary`.");
migrationEnsureColumn($conn, $db_cms, USER_RECORD_LOG, 'attachment_sequence', "ALTER TABLE `{$db_cms}`.`" . USER_RECORD_LOG . "` ADD COLUMN `attachment_sequence` TEXT DEFAULT NULL AFTER `attachment`", "Verified `" . USER_RECORD_LOG . "` includes `attachment_sequence`.");
migrationEnsureColumn($conn, $db_cms, USER_RECORD_LOG, 'message_shortcut_id', "ALTER TABLE `{$db_cms}`.`" . USER_RECORD_LOG . "` ADD COLUMN `message_shortcut_id` INT DEFAULT NULL AFTER `summary`", "Verified `" . USER_RECORD_LOG . "` includes `message_shortcut_id`.");
migrationEnsureColumn($conn, $db_cms, USER_RECORD_LOG, 'next_follow_up_date', "ALTER TABLE `{$db_cms}`.`" . USER_RECORD_LOG . "` ADD COLUMN `next_follow_up_date` DATE DEFAULT NULL AFTER `content`", "Verified `" . USER_RECORD_LOG . "` includes `next_follow_up_date`.");
migrationEnsureColumn($conn, $db_cms, USER_RECORD_LOG, 'follow_up_times', "ALTER TABLE `{$db_cms}`.`" . USER_RECORD_LOG . "` ADD COLUMN `follow_up_times` VARCHAR(255) DEFAULT NULL AFTER `next_follow_up_date`", "Verified `" . USER_RECORD_LOG . "` includes `follow_up_times`.");
migrationEnsureColumn($conn, $db_cms, USER_RECORD_LOG, 'follow_up_day', "ALTER TABLE `{$db_cms}`.`" . USER_RECORD_LOG . "` ADD COLUMN `follow_up_day` VARCHAR(255) DEFAULT NULL AFTER `follow_up_times`", "Verified `" . USER_RECORD_LOG . "` includes `follow_up_day`.");
migrationEnsureColumn($conn, $db_cms, USER_RECORD_LOG, 'is_system_record', "ALTER TABLE `{$db_cms}`.`" . USER_RECORD_LOG . "` ADD COLUMN `is_system_record` TINYINT(1) DEFAULT NULL AFTER `updated_at`", "Verified `" . USER_RECORD_LOG . "` includes `is_system_record`.");

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

$orderDeleteApprovalRequestTable = defined('ORDER_DELETE_APPROVAL_REQUEST') ? ORDER_DELETE_APPROVAL_REQUEST : 'order_delete_approval_request';
$createOrderDeleteApprovalRequestSql = "CREATE TABLE IF NOT EXISTS `{$db_cms}`.`{$orderDeleteApprovalRequestTable}` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `module_key` VARCHAR(80) NOT NULL DEFAULT '',
    `platform` VARCHAR(30) DEFAULT NULL,
    `source_db` VARCHAR(20) NOT NULL DEFAULT '',
    `source_table` VARCHAR(120) NOT NULL DEFAULT '',
    `source_order_id` INT NOT NULL DEFAULT 0,
    `source_order_label` VARCHAR(255) DEFAULT NULL,
    `request_user_id` INT NOT NULL DEFAULT 0,
    `request_user_group_id` INT DEFAULT NULL,
    `supervisor_user_ids` VARCHAR(255) DEFAULT NULL,
    `request_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `approval_remark` TEXT DEFAULT NULL,
    `reject_reason` TEXT DEFAULT NULL,
    `decision_user_id` INT DEFAULT NULL,
    `decision_date` DATE DEFAULT NULL,
    `decision_time` TIME DEFAULT NULL,
    `executed_user_id` INT DEFAULT NULL,
    `executed_date` DATE DEFAULT NULL,
    `executed_time` TIME DEFAULT NULL,
    `create_by` VARCHAR(30) DEFAULT NULL,
    `create_date` DATE DEFAULT NULL,
    `create_time` TIME DEFAULT NULL,
    `update_by` VARCHAR(30) DEFAULT NULL,
    `update_date` DATE DEFAULT NULL,
    `update_time` TIME DEFAULT NULL,
    `status` CHAR(1) NOT NULL DEFAULT 'A',
    PRIMARY KEY (`id`),
    KEY `idx_odar_source_pending` (`module_key`, `source_order_id`, `request_status`, `status`),
    KEY `idx_odar_request_user_pending` (`request_user_id`, `request_status`, `status`),
    KEY `idx_odar_request_status` (`request_status`, `status`),
    KEY `idx_odar_decision_user` (`decision_user_id`, `request_status`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($createOrderDeleteApprovalRequestSql)) {
    echo "<p style='color:green;'>Verified `{$orderDeleteApprovalRequestTable}` is ready in `{$db_cms}`.</p>";
} else {
    echo "<p style='color:red;'>Failed creating `{$orderDeleteApprovalRequestTable}`: " . $conn->error . "</p>";
}

migrationEnsureIndex($conn, $db_cms, $orderDeleteApprovalRequestTable, 'idx_odar_source_pending', "ALTER TABLE `{$db_cms}`.`{$orderDeleteApprovalRequestTable}` ADD INDEX `idx_odar_source_pending` (`module_key`, `source_order_id`, `request_status`, `status`)", "Verified `{$orderDeleteApprovalRequestTable}` source pending index.");
migrationEnsureIndex($conn, $db_cms, $orderDeleteApprovalRequestTable, 'idx_odar_request_user_pending', "ALTER TABLE `{$db_cms}`.`{$orderDeleteApprovalRequestTable}` ADD INDEX `idx_odar_request_user_pending` (`request_user_id`, `request_status`, `status`)", "Verified `{$orderDeleteApprovalRequestTable}` requester pending index.");
migrationEnsureIndex($conn, $db_cms, $orderDeleteApprovalRequestTable, 'idx_odar_request_status', "ALTER TABLE `{$db_cms}`.`{$orderDeleteApprovalRequestTable}` ADD INDEX `idx_odar_request_status` (`request_status`, `status`)", "Verified `{$orderDeleteApprovalRequestTable}` request status index.");
migrationEnsureIndex($conn, $db_cms, $orderDeleteApprovalRequestTable, 'idx_odar_decision_user', "ALTER TABLE `{$db_cms}`.`{$orderDeleteApprovalRequestTable}` ADD INDEX `idx_odar_decision_user` (`decision_user_id`, `request_status`, `status`)", "Verified `{$orderDeleteApprovalRequestTable}` decision user index.");

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

function customizeBotMsgInsertGetContexts()
{
    return array(
        'shopee' => array('label' => 'Shopee', 'parse_mode' => 'plain'),
        'lazada' => array('label' => 'Lazada', 'parse_mode' => 'plain'),
        'website' => array('label' => 'Website', 'parse_mode' => 'plain'),
        'facebook' => array('label' => 'Facebook', 'parse_mode' => 'plain'),
        'stock_order_request' => array('label' => 'Stock Order Request', 'parse_mode' => 'html'),
    );
}

function customizeBotMsgInsertCreateLine($componentKey, $text, $sortOrder = 0, $options = array())
{
    $options = is_array($options) ? $options : array();
    $builderText = isset($options['builder_text']) ? (string) $options['builder_text'] : (string) $text;
    $builderMode = strtolower(trim((string) ($options['builder_mode'] ?? 'editable')));
    if ($builderMode !== 'readonly') {
        $builderMode = 'editable';
    }
    $textPrefix = isset($options['text_prefix']) ? (string) $options['text_prefix'] : '';
    $textSuffix = isset($options['text_suffix']) ? (string) $options['text_suffix'] : '';
    $lockedText = isset($options['locked_text']) ? (string) $options['locked_text'] : '';
    $useBuilderText = strtoupper(trim((string) ($options['use_builder_text'] ?? ($builderMode === 'readonly' ? 'N' : 'Y')))) === 'N' ? 'N' : 'Y';
    $joinWithPrevious = strtoupper(trim((string) ($options['join_with_previous'] ?? 'N'))) === 'Y' ? 'Y' : 'N';

    return array(
        'component_key' => (string) $componentKey,
        'type' => 'line',
        'text' => (string) $text,
        'default_text' => (string) $text,
        'builder_text' => $builderText,
        'default_builder_text' => $builderText,
        'builder_mode' => $builderMode,
        'text_prefix' => $textPrefix,
        'text_suffix' => $textSuffix,
        'locked_text' => $lockedText,
        'use_builder_text' => $useBuilderText,
        'join_with_previous' => $joinWithPrevious,
        'lines' => 0,
        'default_lines' => 0,
        'sort_order' => (int) $sortOrder,
        'default_order' => (int) $sortOrder,
        'removed' => 'N',
    );
}

function customizeBotMsgInsertInferBuilderText($fullText, $defaultComponent = array())
{
    $fullText = str_replace("\r\n", "\n", (string) $fullText);
    $defaultBuilderText = isset($defaultComponent['default_builder_text']) ? (string) $defaultComponent['default_builder_text'] : '';
    $textPrefix = isset($defaultComponent['text_prefix']) ? (string) $defaultComponent['text_prefix'] : '';
    $textSuffix = isset($defaultComponent['text_suffix']) ? (string) $defaultComponent['text_suffix'] : '';
    $builderText = $fullText;

    if ($textPrefix !== '' && substr($builderText, 0, strlen($textPrefix)) === $textPrefix) {
        $builderText = substr($builderText, strlen($textPrefix));
    }

    if ($textSuffix !== '' && substr($builderText, -strlen($textSuffix)) === $textSuffix) {
        $builderText = substr($builderText, 0, strlen($builderText) - strlen($textSuffix));
    }

    $builderText = preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '', $builderText);
    $builderText = preg_replace('/[ \t]{2,}/', ' ', (string) $builderText);
    $builderText = trim((string) $builderText);

    if ($builderText === '' && $defaultBuilderText !== '') {
        return $defaultBuilderText;
    }

    return $builderText;
}

function customizeBotMsgInsertComposeLineText($component)
{
    if (!is_array($component)) {
        return '';
    }

    $useBuilderText = strtoupper(trim((string) ($component['use_builder_text'] ?? 'Y'))) === 'N' ? 'N' : 'Y';
    if ($useBuilderText === 'N') {
        if (array_key_exists('locked_text', $component)) {
            return (string) $component['locked_text'];
        }

        return (string) ($component['text'] ?? '');
    }

    return (string) ($component['text_prefix'] ?? '')
        . (string) ($component['builder_text'] ?? ($component['text'] ?? ''))
        . (string) ($component['text_suffix'] ?? '');
}

function customizeBotMsgInsertCreateSpacer($componentKey, $lines = 1, $sortOrder = 0)
{
    $lines = (int) $lines;
    if ($lines < 1 || $lines > 3) {
        $lines = 1;
    }

    return array(
        'component_key' => (string) $componentKey,
        'type' => 'spacer',
        'text' => '',
        'default_text' => '',
        'lines' => $lines,
        'default_lines' => $lines,
        'sort_order' => (int) $sortOrder,
        'default_order' => (int) $sortOrder,
        'removed' => 'N',
    );
}

function customizeBotMsgInsertGetDefaultComponents($context)
{
    $contexts = customizeBotMsgInsertGetContexts();
    if (!isset($contexts[$context])) {
        return array();
    }

    if ($context === 'stock_order_request') {
        return array(
            customizeBotMsgInsertCreateLine('warehouse_value', 'Warehouse [{{warehouse_name}}]', 10, array('builder_text' => 'Warehouse [{{warehouse_name}}]', 'builder_mode' => 'readonly', 'locked_text' => 'Warehouse [{{warehouse_name}}]', 'use_builder_text' => 'N')),
            customizeBotMsgInsertCreateLine('invoice_id_label', 'Invoice ID:', 20),
            customizeBotMsgInsertCreateLine('invoice_id_value', '<b>{{invoice_no}}</b>', 30, array('builder_text' => '{{invoice_no}}', 'builder_mode' => 'readonly', 'locked_text' => '<b>{{invoice_no}}</b>', 'use_builder_text' => 'N', 'join_with_previous' => 'Y')),
            customizeBotMsgInsertCreateLine('invoice_date_label', 'Invoice Date:', 40),
            customizeBotMsgInsertCreateLine('invoice_date_value', '<b>{{invoice_date}}</b>', 50, array('builder_text' => '{{invoice_date}}', 'builder_mode' => 'readonly', 'locked_text' => '<b>{{invoice_date}}</b>', 'use_builder_text' => 'N', 'join_with_previous' => 'Y')),
            customizeBotMsgInsertCreateLine('package_label', 'Package:', 60),
            customizeBotMsgInsertCreateLine('package_value', '{{package_summary}}', 70, array('builder_text' => '{{package_summary}}', 'builder_mode' => 'readonly', 'locked_text' => '{{package_summary}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y')),
            customizeBotMsgInsertCreateSpacer('package_spacer', 1, 80),
            customizeBotMsgInsertCreateLine('product_label', 'Product:', 90),
            customizeBotMsgInsertCreateLine('product_lines', '{{product_lines_html}}', 100, array('builder_text' => '{{product_lines_html}}', 'builder_mode' => 'readonly', 'locked_text' => '{{product_lines_html}}', 'use_builder_text' => 'N')),
            customizeBotMsgInsertCreateSpacer('product_spacer', 1, 110),
            customizeBotMsgInsertCreateLine('order_link_label', 'Link:', 120),
            customizeBotMsgInsertCreateLine('order_link_value', '{{order_link_html}}', 130, array('builder_text' => '{{order_link_html}}', 'builder_mode' => 'readonly', 'locked_text' => '{{order_link_html}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y')),
        );
    }

    $platformLabel = $contexts[$context]['label'];
    $customerLabel = $context === 'shopee' ? 'Shopee Buyer Username' : $platformLabel . ' Customer';
    $orderLabel = $context === 'shopee' ? 'Shopee OID' : $platformLabel . ' Order ID';

    $components = array(
        customizeBotMsgInsertCreateLine('warehouse_value', '【{{warehouse_name}}】', 10, array('builder_text' => '{{warehouse_name}}', 'builder_mode' => 'readonly', 'locked_text' => '【{{warehouse_name}}】', 'use_builder_text' => 'N')),
        customizeBotMsgInsertCreateLine('customer_label', $customerLabel . ':', 20),
        customizeBotMsgInsertCreateLine('customer_value', '{{buyer_username}}', 30, array('builder_text' => '{{buyer_username}}', 'builder_mode' => 'readonly', 'locked_text' => '{{buyer_username}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y')),
        customizeBotMsgInsertCreateSpacer('customer_spacer', 1, 40),
        customizeBotMsgInsertCreateLine('package_label', 'Package:', 50),
        customizeBotMsgInsertCreateLine('package_lines', '{{package_lines_block}}', 60, array('builder_text' => '{{package_lines_block}}', 'builder_mode' => 'readonly', 'locked_text' => '{{package_lines_block}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y')),
        customizeBotMsgInsertCreateSpacer('package_spacer', 1, 70),
        customizeBotMsgInsertCreateLine('product_label', 'Product Details:', 80),
        customizeBotMsgInsertCreateSpacer('product_intro_spacer', 1, 90),
        customizeBotMsgInsertCreateLine('product_lines', '{{product_details_block}}', 100, array('builder_text' => '{{product_details_block}}', 'builder_mode' => 'readonly', 'locked_text' => '{{product_details_block}}', 'use_builder_text' => 'N')),
    );

    if ($context === 'shopee') {
        $components[] = customizeBotMsgInsertCreateSpacer('delivery_section_spacer', 1, 110);
        $components[] = customizeBotMsgInsertCreateLine('delivery_header', '[Delivery Info]', 120);
        $components[] = customizeBotMsgInsertCreateSpacer('delivery_header_spacer', 1, 130);
        $components[] = customizeBotMsgInsertCreateLine('order_label', $orderLabel . ':', 140);
        $components[] = customizeBotMsgInsertCreateLine('order_value', '{{order_code}}', 150, array('builder_text' => '{{order_code}}', 'builder_mode' => 'readonly', 'locked_text' => '{{order_code}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y'));
        $components[] = customizeBotMsgInsertCreateSpacer('order_line_spacer', 1, 160);
        $components[] = customizeBotMsgInsertCreateLine('customer_name_label', 'Customer Name:', 170);
        $components[] = customizeBotMsgInsertCreateLine('customer_name_value', '{{customer_name}}', 180, array('builder_text' => '{{customer_name}}', 'builder_mode' => 'readonly', 'locked_text' => '{{customer_name}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y'));
        $components[] = customizeBotMsgInsertCreateSpacer('customer_name_spacer', 1, 190);
        $components[] = customizeBotMsgInsertCreateLine('customer_address_label', 'Customer Address:', 200);
        $components[] = customizeBotMsgInsertCreateLine('customer_address_value', '{{customer_address}}', 210, array('builder_text' => '{{customer_address}}', 'builder_mode' => 'readonly', 'locked_text' => '{{customer_address}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y'));
    } else {
        $components[] = customizeBotMsgInsertCreateSpacer('order_section_spacer', 1, 110);
        $components[] = customizeBotMsgInsertCreateLine('order_label', $orderLabel . ':', 120);
        $components[] = customizeBotMsgInsertCreateLine('order_value', '{{order_code}}', 130, array('builder_text' => '{{order_code}}', 'builder_mode' => 'readonly', 'locked_text' => '{{order_code}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y'));
        $components[] = customizeBotMsgInsertCreateSpacer('airbill_spacer', 1, 140);
        $components[] = customizeBotMsgInsertCreateLine('airbill_label', 'Airbill:', 150);
        $components[] = customizeBotMsgInsertCreateLine('airbill_value', '{{airbill_no}}', 160, array('builder_text' => '{{airbill_no}}', 'builder_mode' => 'readonly', 'locked_text' => '{{airbill_no}}', 'use_builder_text' => 'N', 'join_with_previous' => 'Y'));
    }

    $components[] = customizeBotMsgInsertCreateSpacer('stock_out_link_spacer', 1, 220);
    $components[] = customizeBotMsgInsertCreateLine('stock_out_link_label', 'Warehouse Stock-out Link:', 230);
    $components[] = customizeBotMsgInsertCreateLine('stock_out_link_value', '{{warehouse_stock_out_link}}', 240, array('builder_text' => '{{warehouse_stock_out_link}}', 'builder_mode' => 'readonly', 'locked_text' => '{{warehouse_stock_out_link}}', 'use_builder_text' => 'N'));

    return $components;
}

function customizeBotMsgInsertBuildTemplateBody($components)
{
    $lines = array();
    foreach ((array) $components as $component) {
        if (!is_array($component)) {
            continue;
        }
        if ((string) ($component['removed'] ?? 'N') === 'Y') {
            continue;
        }

        if ((string) ($component['type'] ?? 'line') === 'spacer') {
            $blankLines = (int) ($component['lines'] ?? 1);
            if ($blankLines < 1 || $blankLines > 3) {
                $blankLines = 1;
            }
            for ($blankIndex = 0; $blankIndex < $blankLines; $blankIndex++) {
                $lines[] = '';
            }
            continue;
        }

        $textParts = explode("\n", str_replace("\r\n", "\n", customizeBotMsgInsertComposeLineText($component)));
        $joinWithPrevious = strtoupper(trim((string) ($component['join_with_previous'] ?? 'N'))) === 'Y';
        if ($joinWithPrevious && !empty($textParts) && !empty($lines)) {
            $firstPart = array_shift($textParts);
            $lineIndex = count($lines) - 1;
            $separator = ($lines[$lineIndex] !== '' && $firstPart !== '') ? ' ' : '';
            $lines[$lineIndex] .= $separator . $firstPart;
        }
        foreach ($textParts as $textPart) {
            $lines[] = $textPart;
        }
    }

    return implode("\n", $lines);
}

function customizeBotMsgInsertGetSampleData($context)
{
    if ($context === 'stock_order_request') {
        $orderLink = 'https://crm.beyourdiary.com/stock/warehouse_stock_in_scan.php?t=SOR-240708-01';
        $safeOrderLink = htmlspecialchars($orderLink, ENT_QUOTES, 'UTF-8');

        return array(
            'warehouse_name' => 'HQ Warehouse',
            'invoice_no' => 'SOR-240708-01',
            'invoice_date' => '2026-07-08',
            'package_summary' => 'Vv Rosselady 3 box FREE Rosselady 10 box + Gold Zie + Mother\'s Day promo 2% (MY) x1',
            'product_lines_html' => '1. Vv Roseladys <b>x2 boxes</b><br>2. Urbaniiz Candy Meonx <b>x1 boxes</b>',
            'order_link' => $orderLink,
            'order_link_html' => '<a href="' . $safeOrderLink . '">' . $safeOrderLink . '</a>',
        );
    }

    $contexts = customizeBotMsgInsertGetContexts();
    $platformLabel = $contexts[$context]['label'];
    $orderCode = '65477567';
    if ($context === 'lazada') {
        $orderCode = 'LZD-240708-7788';
    } elseif ($context === 'facebook') {
        $orderCode = 'FB-240708-2255';
    } elseif ($context === 'website') {
        $orderCode = 'WEB-240708-1109';
    }

    return array(
        'warehouse_name' => 'HQ Warehouse',
        'buyer_username' => $context === 'shopee' ? 'bsg' : 'Glenda Chia OFFICE',
        'customer_name' => 'Glenda Chia OFFICE',
        'customer_address' => "Menara Sah Bhd, 1st Floor, No. 15, Lot 8733, Block 16,\nGreen Height Commercial Centre,\nNew Airport Road, Kuching, Kuching,\nSarawak 93258",
        'order_code' => $orderCode,
        'package_lines_block' => 'Vv Rosselady 3 box FREE Rosselady 10 box + Gold Zie + Mother\'s Day promo 2% (MY) x1',
        'product_details_block' => "1. Vv Roseladys x2 boxes\n2. Urbaniiz Candy Meonx x1 boxes",
        'package_summary' => 'Vv Rosselady 3 box FREE Rosselady 10 box + Gold Zie + Mother\'s Day promo 2% (MY) x1',
        'airbill_no' => 'MYA240708001',
        'warehouse_stock_out_link' => 'https://crm.beyourdiary.com/warehouse_stock_in_scan.php?t=7da5c5231b434c6edc95ec28f4ea59a3',
    );
}

function customizeBotMsgInsertRenderTemplate($templateBody, $data, $parseMode = 'plain')
{
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($data, $parseMode) {
        $key = isset($matches[1]) ? (string) $matches[1] : '';
        $value = array_key_exists($key, $data) ? $data[$key] : '';
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $value = (string) $value;

        if ($parseMode === 'html') {
            if (substr($key, -5) === '_html') {
                return $value;
            }

            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }, str_replace("\r\n", "\n", (string) $templateBody));
}

function customizeBotMsgInsertGetSeedRows()
{
    $seedRows = array();
    $contexts = customizeBotMsgInsertGetContexts();
    foreach ($contexts as $context => $contextConfig) {
        $components = customizeBotMsgInsertGetDefaultComponents($context);
        $templateBody = customizeBotMsgInsertBuildTemplateBody($components);
        $previewSample = customizeBotMsgInsertRenderTemplate($templateBody, customizeBotMsgInsertGetSampleData($context), $contextConfig['parse_mode']);

        $templateName = ucfirst(str_replace('_', ' ', $context)) . ' Default Template';
        $remark = 'Default ' . strtolower($contextConfig['label']) . ' bot message template';
        if ($context === 'stock_order_request') {
            $templateName = 'Default Stock Order Request Message';
            $remark = 'Default stock order request Telegram template';
        }

        $seedRows[$context] = array(
            'template_name' => $templateName,
            'message_context' => $context,
            'parse_mode' => $contextConfig['parse_mode'],
            'template_body' => $templateBody,
            'components_json' => json_encode($components, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'preview_sample' => $previewSample,
            'remark' => $remark,
        );
    }

    return $seedRows;
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
//         `attachments` TEXT DEFAULT NULL,
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
//
// } else {
//     echo "<p style='color:red;'>Failed selecting CMS database for user record log migration.</p>";
// }

if ($conn->select_db($db_cms)) {
    migrationEnsureTableUnicodeInnoDb($conn, $db_cms, AUDIT_LOG);
} else {
    echo "<p style='color:red;'>Failed selecting CMS database for `" . AUDIT_LOG . "` Unicode/InnoDB migration.</p>";
}

if ($conn->select_db($db_cms)) {
    if (migrationTableExists($conn, $db_cms, USER_RECORD_LOG)) {
        if (migrationColumnExists($conn, $db_cms, USER_RECORD_LOG, 'attachment')) {
            if ($conn->query("ALTER TABLE `" . USER_RECORD_LOG . "` MODIFY COLUMN `attachment` TEXT DEFAULT NULL")) {
                echo "<p style='color:green;'>Verified column `attachment` in `" . USER_RECORD_LOG . "` supports multiple attachments in CMS database.</p>";
            } else {
                echo "<p style='color:red;'>Failed updating `attachment` column in `" . USER_RECORD_LOG . "` in CMS database: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:orange;'>Skipped `attachment` column update because column `attachment` was not found in `" . USER_RECORD_LOG . "`.</p>";
        }
    } else {
        echo "<p style='color:orange;'>Skipped `attachment` column update because `" . USER_RECORD_LOG . "` was not found in CMS database `" . $db_cms . "`.</p>";
    }

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

    $createCustomizeBotMsgTableSql = "CREATE TABLE IF NOT EXISTS `" . CUSTOMIZE_BOT_MSG . "` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `template_name` VARCHAR(150) NOT NULL,
        `message_context` VARCHAR(50) NOT NULL,
        `parse_mode` VARCHAR(20) NOT NULL DEFAULT 'plain',
        `template_body` LONGTEXT DEFAULT NULL,
        `components_json` LONGTEXT DEFAULT NULL,
        `preview_sample` LONGTEXT DEFAULT NULL,
        `is_default` CHAR(1) NOT NULL DEFAULT 'N',
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(255) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(255) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        PRIMARY KEY (`id`),
        KEY `idx_customize_bot_msg_context_status` (`message_context`, `status`),
        KEY `idx_customize_bot_msg_default_context_status` (`is_default`, `message_context`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($createCustomizeBotMsgTableSql)) {
        echo "<p style='color:green;'>Verified table `" . CUSTOMIZE_BOT_MSG . "` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CUSTOMIZE_BOT_MSG . "`: " . $conn->error . "</p>";
    }

    $createCustomizeBotMsgOrderTableSql = "CREATE TABLE IF NOT EXISTS `" . CUSTOMIZE_BOT_MSG_ORDER . "` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `message_context` VARCHAR(50) NOT NULL,
        `order_table` VARCHAR(100) NOT NULL,
        `order_id` INT NOT NULL,
        `template_id` INT NOT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_context_order` (`message_context`, `order_table`, `order_id`),
        KEY `idx_template_id` (`template_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($createCustomizeBotMsgOrderTableSql)) {
        echo "<p style='color:green;'>Verified table `" . CUSTOMIZE_BOT_MSG_ORDER . "` is ready.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CUSTOMIZE_BOT_MSG_ORDER . "`: " . $conn->error . "</p>";
    }

    $customizeBotMsgSeedRows = customizeBotMsgInsertGetSeedRows();
    foreach ($customizeBotMsgSeedRows as $context => $seedRow) {
        $safeContext = $conn->real_escape_string((string) $context);
        $conn->query("UPDATE `" . CUSTOMIZE_BOT_MSG . "` SET `status` = 'A' WHERE `status` = 'D' AND `is_default` = 'Y' AND `message_context` = '" . $safeContext . "'");
        $checkSql = "SELECT `id`, `is_default`, `status`
            FROM `" . CUSTOMIZE_BOT_MSG . "`
            WHERE `message_context` = '" . $safeContext . "'
              AND `status` = 'A'
            ORDER BY `is_default` DESC, `id` ASC";
        $checkResult = $conn->query($checkSql);

        if ($checkResult && $checkResult->num_rows > 0) {
            $defaultTemplateId = 0;
            while ($existingTemplateRow = $checkResult->fetch_assoc()) {
                if ($defaultTemplateId <= 0 && isset($existingTemplateRow['id'])) {
                    $defaultTemplateId = (int) $existingTemplateRow['id'];
                }
            }

            if ($defaultTemplateId > 0) {
                $conn->query("UPDATE `" . CUSTOMIZE_BOT_MSG . "` SET `status` = 'A' WHERE `status` = 'D' AND `is_default` = 'Y' AND `message_context` = '" . $safeContext . "'");
                $conn->query("UPDATE `" . CUSTOMIZE_BOT_MSG . "` SET `is_default` = 'N' WHERE `message_context` = '" . $safeContext . "' AND `status` = 'A'");
                $conn->query("UPDATE `" . CUSTOMIZE_BOT_MSG . "` SET `is_default` = 'Y', `status` = 'A' WHERE `id` = " . $defaultTemplateId . " LIMIT 1");
            }

            echo "<p style='color:green;'>Verified default customize bot message exists for context `" . htmlspecialchars((string) $context, ENT_QUOTES, 'UTF-8') . "`.</p>";
            continue;
        }

        $stmt = $conn->prepare(
            "INSERT INTO `" . CUSTOMIZE_BOT_MSG . "`
                (`template_name`, `message_context`, `parse_mode`, `template_body`, `components_json`, `preview_sample`, `is_default`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, 'Y', ?, '1', CURDATE(), CURTIME(), 'A')"
        );

        if (!$stmt) {
            echo "<p style='color:red;'>Failed preparing seed insert for customize bot message context `" . htmlspecialchars((string) $context, ENT_QUOTES, 'UTF-8') . "`: " . $conn->error . "</p>";
            continue;
        }

        $templateName = (string) $seedRow['template_name'];
        $messageContext = (string) $seedRow['message_context'];
        $parseMode = (string) $seedRow['parse_mode'];
        $templateBody = (string) $seedRow['template_body'];
        $componentsJson = (string) $seedRow['components_json'];
        $previewSample = (string) $seedRow['preview_sample'];
        $remark = (string) $seedRow['remark'];
        $stmt->bind_param('sssssss', $templateName, $messageContext, $parseMode, $templateBody, $componentsJson, $previewSample, $remark);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Seeded default customize bot message for context `" . htmlspecialchars((string) $context, ENT_QUOTES, 'UTF-8') . "`.</p>";
        } else {
            echo "<p style='color:red;'>Failed seeding customize bot message for context `" . htmlspecialchars((string) $context, ENT_QUOTES, 'UTF-8') . "`: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }

    if ($conn->query($createTaskProjectSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_PROJECT . "` for task projects.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_PROJECT . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_PROJECT,
        'priority_status_id_1',
        "ALTER TABLE `" . TASK_PROJECT . "` ADD COLUMN `priority_status_id_1` INT DEFAULT NULL AFTER `board_background_color`",
        "Verified `" . TASK_PROJECT . "` includes `priority_status_id_1`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_PROJECT,
        'priority_status_id_2',
        "ALTER TABLE `" . TASK_PROJECT . "` ADD COLUMN `priority_status_id_2` INT DEFAULT NULL AFTER `priority_status_id_1`",
        "Verified `" . TASK_PROJECT . "` includes `priority_status_id_2`."
    );

    $createTaskStatusSql = "CREATE TABLE IF NOT EXISTS `" . TASK_COLUMN . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT DEFAULT NULL,
        `name` VARCHAR(150) NOT NULL,
        `color` VARCHAR(20) NOT NULL DEFAULT '#dfe1e6',
        `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y',
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
        `description_color_html` MEDIUMTEXT DEFAULT NULL,
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
        `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y',
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
        `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y',
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
        `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y',
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

    $createTaskItemLinkSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_LINK . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NOT NULL,
        `source_item_id` INT NOT NULL,
        `target_item_id` INT NOT NULL,
        `relation_type` VARCHAR(80) NOT NULL,
        `remark` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_project_source_status` (`project_id`, `source_item_id`, `status`),
        KEY `idx_project_target_status` (`project_id`, `target_item_id`, `status`),
        KEY `idx_relation_type` (`relation_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($createTaskItemLinkSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_LINK . "` for task item linked-work-item relations.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_LINK . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'project_id',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `project_id` INT NOT NULL AFTER `id`",
        "Verified `" . TASK_ITEM_LINK . "` includes `project_id`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'source_item_id',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `source_item_id` INT NOT NULL AFTER `project_id`",
        "Verified `" . TASK_ITEM_LINK . "` includes `source_item_id`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'target_item_id',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `target_item_id` INT NOT NULL AFTER `source_item_id`",
        "Verified `" . TASK_ITEM_LINK . "` includes `target_item_id`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'relation_type',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `relation_type` VARCHAR(80) NOT NULL AFTER `target_item_id`",
        "Verified `" . TASK_ITEM_LINK . "` includes `relation_type`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'remark',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `remark` VARCHAR(255) DEFAULT NULL AFTER `relation_type`",
        "Verified `" . TASK_ITEM_LINK . "` includes `remark`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'create_by',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `create_by` VARCHAR(30) DEFAULT NULL AFTER `remark`",
        "Verified `" . TASK_ITEM_LINK . "` includes `create_by`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'create_date',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `create_by`",
        "Verified `" . TASK_ITEM_LINK . "` includes `create_date`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'create_time',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`",
        "Verified `" . TASK_ITEM_LINK . "` includes `create_time`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'update_by',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `update_by` VARCHAR(30) DEFAULT NULL AFTER `create_time`",
        "Verified `" . TASK_ITEM_LINK . "` includes `update_by`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'update_date',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `update_date` DATE DEFAULT NULL AFTER `update_by`",
        "Verified `" . TASK_ITEM_LINK . "` includes `update_date`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'update_time',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `update_time` TIME DEFAULT NULL AFTER `update_date`",
        "Verified `" . TASK_ITEM_LINK . "` includes `update_time`."
    );
    migrationEnsureColumn(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'status',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `update_time`",
        "Verified `" . TASK_ITEM_LINK . "` includes `status`."
    );
    migrationEnsureIndex(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'idx_project_source_status',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD INDEX `idx_project_source_status` (`project_id`, `source_item_id`, `status`)",
        "Verified `" . TASK_ITEM_LINK . "` includes `idx_project_source_status`."
    );
    migrationEnsureIndex(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'idx_project_target_status',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD INDEX `idx_project_target_status` (`project_id`, `target_item_id`, `status`)",
        "Verified `" . TASK_ITEM_LINK . "` includes `idx_project_target_status`."
    );
    migrationEnsureIndex(
        $conn,
        $db_cms,
        TASK_ITEM_LINK,
        'idx_relation_type',
        "ALTER TABLE `" . $db_cms . "`.`" . TASK_ITEM_LINK . "` ADD INDEX `idx_relation_type` (`relation_type`)",
        "Verified `" . TASK_ITEM_LINK . "` includes `idx_relation_type`."
    );

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
        `comment_color_html` MEDIUMTEXT DEFAULT NULL,
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
        `reply_color_html` MEDIUMTEXT DEFAULT NULL,
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

    $createTaskItemWorklogSql = "CREATE TABLE IF NOT EXISTS `" . TASK_ITEM_WORKLOG . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `item_id` INT NOT NULL,
        `duration_seconds` INT NOT NULL DEFAULT 0,
        `started_date` DATE DEFAULT NULL,
        `started_time` TIME DEFAULT NULL,
        `work_description_html` MEDIUMTEXT DEFAULT NULL,
        `work_description_text` TEXT DEFAULT NULL,
        `remaining_seconds_snapshot` INT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_task_item_worklog_main` (`item_id`, `status`, `started_date`, `started_time`),
        KEY `idx_task_item_worklog_created` (`create_date`, `create_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createTaskItemWorklogSql)) {
        echo "<p style='color:green;'>Verified table `" . TASK_ITEM_WORKLOG . "` for task item worklogs.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . TASK_ITEM_WORKLOG . "`: " . $conn->error . "</p>";
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
        `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y',
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
    migrationEnsureColumn($conn, $db_cms, TASK_ITEM, 'description_color_html', "ALTER TABLE `" . TASK_ITEM . "` ADD COLUMN `description_color_html` MEDIUMTEXT DEFAULT NULL AFTER `description`", "Verified `" . TASK_ITEM . "` includes `description_color_html`.");
    migrationEnsureColumn($conn, $db_cms, TASK_ITEM, 'remaining_estimate_seconds', "ALTER TABLE `" . TASK_ITEM . "` ADD COLUMN `remaining_estimate_seconds` INT DEFAULT NULL AFTER `original_estimate`", "Verified `" . TASK_ITEM . "` includes `remaining_estimate_seconds`.");
    migrationEnsureColumn($conn, $db_cms, TASK_ITEM_COMMENT, 'comment_color_html', "ALTER TABLE `" . TASK_ITEM_COMMENT . "` ADD COLUMN `comment_color_html` MEDIUMTEXT DEFAULT NULL AFTER `comment_html`", "Verified `" . TASK_ITEM_COMMENT . "` includes `comment_color_html`.");
    migrationEnsureColumn($conn, $db_cms, TASK_ITEM_COMMENT_REPLY, 'reply_color_html', "ALTER TABLE `" . TASK_ITEM_COMMENT_REPLY . "` ADD COLUMN `reply_color_html` MEDIUMTEXT DEFAULT NULL AFTER `reply_html`", "Verified `" . TASK_ITEM_COMMENT_REPLY . "` includes `reply_color_html`.");
    migrationEnsureIndex($conn, $db_cms, TASK_ITEM, 'idx_task_item_project', "ALTER TABLE `" . TASK_ITEM . "` ADD INDEX `idx_task_item_project` (`project_id`, `column_id`, `sort_order`)", "Verified `" . TASK_ITEM . "` project index.");
    migrationEnsureIndex($conn, $db_cms, TASK_ITEM, 'idx_task_item_board', "ALTER TABLE `" . TASK_ITEM . "` ADD INDEX `idx_task_item_board` (`project_id`, `status`, `column_id`, `sort_order`, `id`)", "Verified `" . TASK_ITEM . "` board-load index.");

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

    $createCampaignPackageSql = "CREATE TABLE IF NOT EXISTS `" . CAMPAIGN_PACKAGE . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `campaign_id` INT NOT NULL,
        `package_id` INT NOT NULL,
        `create_by` VARCHAR(30),
        `create_date` DATE,
        `create_time` TIME,
        `update_by` VARCHAR(30),
        `update_date` DATE,
        `update_time` TIME,
        `status` CHAR(1) DEFAULT 'A',
        KEY `idx_campaign_package_campaign_id` (`campaign_id`),
        KEY `idx_campaign_package_package_id` (`package_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createCampaignPackageSql)) {
        echo "<p style='color:green;'>Verified table `" . CAMPAIGN_PACKAGE . "` for Campaign Package.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . CAMPAIGN_PACKAGE . "`: " . $conn->error . "</p>";
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

    // Composite (status, campaign_id) indexes so the campaign_table.php per-campaign
    // aggregate subqueries (WHERE status='A' GROUP BY campaign_id) can be satisfied
    // with an index range scan instead of a full table scan + filesort.
    migrationEnsureIndex($conn, $db_cms, CAMPAIGN_PIC, 'idx_campaign_pic_status_campaign', "ALTER TABLE `" . CAMPAIGN_PIC . "` ADD INDEX `idx_campaign_pic_status_campaign` (`status`, `campaign_id`)", "Verified `" . CAMPAIGN_PIC . "` status/campaign index.");
    migrationEnsureIndex($conn, $db_cms, CAMPAIGN_CUSTOMER, 'idx_campaign_customer_status_campaign', "ALTER TABLE `" . CAMPAIGN_CUSTOMER . "` ADD INDEX `idx_campaign_customer_status_campaign` (`status`, `campaign_id`)", "Verified `" . CAMPAIGN_CUSTOMER . "` status/campaign index.");
    migrationEnsureIndex($conn, $db_cms, CAMPAIGN_FOLLOW_UP, 'idx_campaign_follow_up_status_campaign', "ALTER TABLE `" . CAMPAIGN_FOLLOW_UP . "` ADD INDEX `idx_campaign_follow_up_status_campaign` (`status`, `campaign_id`)", "Verified `" . CAMPAIGN_FOLLOW_UP . "` status/campaign index.");
    migrationEnsureIndex($conn, $db_cms, CAMPAIGN_PURCHASE_RECORD, 'idx_campaign_purchase_status_campaign', "ALTER TABLE `" . CAMPAIGN_PURCHASE_RECORD . "` ADD INDEX `idx_campaign_purchase_status_campaign` (`status`, `campaign_id`)", "Verified `" . CAMPAIGN_PURCHASE_RECORD . "` status/campaign index.");


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

    $campaignDefaultAccessByUserGroup = array(
        1 => array(
            153 => array(1, 2, 3, 4),
            154 => array(1, 2, 3, 4),
        ),
        2 => array(
            153 => array(1, 2, 3, 4),
            154 => array(1, 2, 3, 4),
        ),
    );

    foreach ($campaignDefaultAccessByUserGroup as $groupId => $pinAccessMap) {
        $userGroupResult = $conn->query("SELECT `pins` FROM `user_group` WHERE `id` = " . (int) $groupId . " LIMIT 1");
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            echo "<p style='color:orange;'>`user_group` id " . (int) $groupId . " not found. Skipped Campaign pin assignment.</p>";
            continue;
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $currentPins = isset($userGroupRow['pins']) ? (string) $userGroupRow['pins'] : '';
        $updatedPins = $currentPins;

        foreach ($pinAccessMap as $pinGroupId => $pinAccessList) {
            $updatedPins = addAccessToPinBlock($updatedPins, $pinGroupId, $pinAccessList);
        }

        if ($updatedPins !== $currentPins) {
            $safePins = $conn->real_escape_string($updatedPins);
            if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . (int) $groupId)) {
                echo "<p style='color:green;'>Verified Campaign pin access for `user_group` id " . (int) $groupId . ".</p>";
            } else {
                echo "<p style='color:red;'>Failed updating Campaign pin access for `user_group` id " . (int) $groupId . ": " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color:green;'>Verified Campaign pin access already exists for `user_group` id " . (int) $groupId . ".</p>";
        }
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
        (151, 'Customer Follow-Up', '1,11,12', 'Customer follow-up approval and log access', '1', CURDATE(), CURTIME(), 'A'),
        (160, 'Customer Dashboard', '1', 'Customer Dashboard view access', '1', CURDATE(), CURTIME(), 'A'),
        (161, 'Daily Follow Up Report', '1', 'Customer user record log daily activity reporting', '1', CURDATE(), CURTIME(), 'A'),
        (162, 'Member Point', '1', 'Member point customer summary view access', '1', CURDATE(), CURTIME(), 'A'),
        (163, 'Member Redeem Setting', '1,2,3,4', 'Member redeem gift setting management', '1', CURDATE(), CURTIME(), 'A'),
        (164, 'Member Bonus Management', '1,2,3,4', 'Member bonus tier and special bonus management', '1', CURDATE(), CURTIME(), 'A'),
        (165, 'Customize Bot Message', '1,2,3,4', 'Customize bot message template management', '1', CURDATE(), CURTIME(), 'A')
        ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`),
            `pins` = VALUES(`pins`),
            `remark` = VALUES(`remark`),
            `status` = 'A'";
    if ($conn->query($taskPinGroupSql)) {
        echo "<p style='color:green;'>Verified pin groups 136-151 and 160-164 for task, customer, product label, OMS page management, Customer Dashboard access, Daily Follow Up Report access, Member Point access, Member Redeem Setting access, and Member Bonus Management access.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating pin groups 136-151 and 160-164: " . $conn->error . "</p>";
    }

    $omsPagePinGroupUpdateSql = "UPDATE `pin_group`
        SET `name` = CASE `id`
                WHEN 146 THEN 'Waiting To Pack'
                WHEN 147 THEN 'Arrival Management'
                WHEN 148 THEN 'Daily Flow Report'
                WHEN 149 THEN 'Flow Setting'
                WHEN 150 THEN 'Customer Daily Report'
                WHEN 151 THEN 'Customer Follow-Up'
                WHEN 160 THEN 'Customer Dashboard'
                WHEN 161 THEN 'Daily Follow Up Report'
                WHEN 162 THEN 'Member Point'
                WHEN 163 THEN 'Member Redeem Setting'
                WHEN 164 THEN 'Member Bonus Management'
                ELSE `name`
            END,
            `remark` = CASE `id`
                WHEN 146 THEN 'OMS warehouse scan flow'
                WHEN 147 THEN 'OMS arrival management workflow'
                WHEN 148 THEN 'OMS daily flow reporting'
                WHEN 149 THEN 'OMS flow setting management'
                WHEN 150 THEN 'Customer daily edit activity reporting'
                WHEN 151 THEN 'Customer follow-up approval and log access'
                WHEN 160 THEN 'Customer Dashboard view access'
                WHEN 161 THEN 'Customer user record log daily activity reporting'
                WHEN 162 THEN 'Member point customer summary view access'
                WHEN 163 THEN 'Member redeem gift setting management'
                WHEN 164 THEN 'Member bonus tier and special bonus management'
                ELSE `remark`
            END,
            `status` = 'A'
        WHERE `id` IN (146,147,148,149,150,151,160,161,162,163,164)";
    if ($conn->query($omsPagePinGroupUpdateSql)) {
        echo "<p style='color:green;'>Verified pin group names for Waiting To Pack, Arrival Management, Daily Flow Report, Flow Setting, Customer Daily Report, Customer Follow-Up, Customer Dashboard, Daily Follow Up Report, Member Point, Member Redeem Setting, and Member Bonus Management.</p>";
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
            $updatedPins = addAccessToPinBlock($updatedPins, 160, array(1));
            $updatedPins = addAccessToPinBlock($updatedPins, 162, array(1));
            $updatedPins = addAccessToPinBlock($updatedPins, 163, array(1, 2, 3, 4));
            $updatedPins = addAccessToPinBlock($updatedPins, 164, array(1, 2, 3, 4));
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

    $customizeBotMsgGroupResult = $conn->query("SELECT `id`, `pins` FROM `user_group` WHERE `status` = 'A'");
    if ($customizeBotMsgGroupResult) {
        while ($customizeBotMsgGroupRow = $customizeBotMsgGroupResult->fetch_assoc()) {
            $groupId = isset($customizeBotMsgGroupRow['id']) ? (int) $customizeBotMsgGroupRow['id'] : 0;
            if ($groupId <= 0) {
                continue;
            }

            $currentPins = isset($customizeBotMsgGroupRow['pins']) ? (string) $customizeBotMsgGroupRow['pins'] : '';
            $updatedPins = addAccessToPinBlock($currentPins, 165, array(1, 2, 3, 4));

            if ($updatedPins !== $currentPins) {
                $safePins = $conn->real_escape_string($updatedPins);
                if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . $groupId)) {
                    echo "<p style='color:green;'>Verified Customize Bot Message pin access for `user_group` id " . $groupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Failed updating Customize Bot Message pin access for `user_group` id " . $groupId . ": " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color:green;'>Verified Customize Bot Message pin access already exists for `user_group` id " . $groupId . ".</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Failed reading active `user_group` rows for Customize Bot Message pin assignment: " . $conn->error . "</p>";
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
                $updatedPins = addAccessToPinBlock($updatedPins, 161, array(1));
            } else {
                // Other user groups only get View access.
                $updatedPins = addAccessToPinBlock($currentPins, 151, array(1));
                $updatedPins = removePinBlockById($updatedPins, 161);
            }

            if ($updatedPins !== $currentPins) {
                $safePins = $conn->real_escape_string($updatedPins);
                if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . $groupId)) {
                    echo "<p style='color:green;'>Verified Customer Follow-Up and Daily Follow Up Report pin access for `user_group` id " . $groupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Failed updating Customer Follow-Up / Daily Follow Up Report pin access for `user_group` id " . $groupId . ": " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color:green;'>Verified Customer Follow-Up and Daily Follow Up Report pin access already matches `user_group` id " . $groupId . ".</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Failed reading `user_group` for Customer Follow-Up / Daily Follow Up Report pin assignment: " . $conn->error . "</p>";
    }

    $memberPointGroupResult = $conn->query("SELECT `id`, `pins` FROM `user_group`");
    if ($memberPointGroupResult) {
        while ($memberPointGroupRow = $memberPointGroupResult->fetch_assoc()) {
            $groupId = isset($memberPointGroupRow['id']) ? (int) $memberPointGroupRow['id'] : 0;
            if ($groupId <= 0) {
                continue;
            }

            $currentPins = isset($memberPointGroupRow['pins']) ? (string) $memberPointGroupRow['pins'] : '';
            if (in_array($groupId, array(1, 2), true)) {
                $updatedPins = addAccessToPinBlock($currentPins, 162, array(1));
            } else {
                // Preserve any Member Point assignment configured for other user groups.
                $updatedPins = $currentPins;
            }

            if ($updatedPins !== $currentPins) {
                $safePins = $conn->real_escape_string($updatedPins);
                if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . $groupId)) {
                    echo "<p style='color:green;'>Verified Member Point pin access for `user_group` id " . $groupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Failed updating Member Point pin access for `user_group` id " . $groupId . ": " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color:green;'>Verified Member Point pin access already matches `user_group` id " . $groupId . ".</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Failed reading `user_group` for Member Point pin assignment: " . $conn->error . "</p>";
    }

    $memberRedeemGroupResult = $conn->query("SELECT `id`, `pins` FROM `user_group`");
    if ($memberRedeemGroupResult) {
        while ($memberRedeemGroupRow = $memberRedeemGroupResult->fetch_assoc()) {
            $groupId = isset($memberRedeemGroupRow['id']) ? (int) $memberRedeemGroupRow['id'] : 0;
            if ($groupId <= 0) {
                continue;
            }

            $currentPins = isset($memberRedeemGroupRow['pins']) ? (string) $memberRedeemGroupRow['pins'] : '';
            if (in_array($groupId, array(1, 2), true)) {
                $updatedPins = addAccessToPinBlock($currentPins, 163, array(1, 2, 3, 4));
            } else {
                $updatedPins = removePinBlockById($currentPins, 163);
            }

            if ($updatedPins !== $currentPins) {
                $safePins = $conn->real_escape_string($updatedPins);
                if ($conn->query("UPDATE `user_group` SET `pins` = '" . $safePins . "' WHERE `id` = " . $groupId)) {
                    echo "<p style='color:green;'>Verified Member Redeem Setting pin access for `user_group` id " . $groupId . ".</p>";
                } else {
                    echo "<p style='color:red;'>Failed updating Member Redeem Setting pin access for `user_group` id " . $groupId . ": " . $conn->error . "</p>";
                }
            } else {
                echo "<p style='color:green;'>Verified Member Redeem Setting pin access already matches `user_group` id " . $groupId . ".</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>Failed reading `user_group` for Member Redeem Setting pin assignment: " . $conn->error . "</p>";
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

    migrationRepairCustomerFollowUpAlertActionUrls(
        $conn,
        $db_cms,
        $systemAlertMessageTable,
        $customerFollowUpNotificationTable,
        ORDER_FLOW_SETTING,
        isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : 'SYSTEM'
    );
    migrationZeroReturnedOrderFinancialValues(
        $conn,
        $db_cms,
        ORDER_FLOW_SETTING,
        isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : 'SYSTEM'
    );

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
    migrationEnsureColumn($conn, $db_fin, SHOPEE_SG_ORDER_REQ, 'saver_program_fee', "ALTER TABLE `" . SHOPEE_SG_ORDER_REQ . "` ADD COLUMN `saver_program_fee` DECIMAL(10,2) NOT NULL DEFAULT '0.00' AFTER `ams_fee`", "Verified `" . SHOPEE_SG_ORDER_REQ . "` includes `saver_program_fee`.");
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

    $createMemberPointLedgerSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . MEMBER_POINT_LEDGER . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `platform` VARCHAR(30) NOT NULL,
        `customer_id` INT NOT NULL,
        `customer_label` VARCHAR(200) DEFAULT NULL,
        `record_type` VARCHAR(30) NOT NULL DEFAULT 'order',
        `source_key` VARCHAR(120) NOT NULL,
        `source_order_id` INT DEFAULT NULL,
        `source_order_code` VARCHAR(120) DEFAULT NULL,
        `order_date` DATE DEFAULT NULL,
        `bonus_month` CHAR(7) DEFAULT NULL,
        `order_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `point_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
        `base_points` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        `bonus_points` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        `total_points` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        `point_status` VARCHAR(30) DEFAULT NULL,
        `usage_scope` VARCHAR(120) DEFAULT NULL,
        `expiry_date` DATE DEFAULT NULL,
        `remark` TEXT DEFAULT NULL,
        `metadata_json` LONGTEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_member_point_customer_source` (`platform`, `customer_id`, `source_key`),
        KEY `idx_member_point_customer_status` (`platform`, `customer_id`, `status`),
        KEY `idx_member_point_expiry` (`expiry_date`, `status`),
        KEY `idx_member_point_order_date` (`order_date`, `status`),
        KEY `idx_member_point_order` (`source_order_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createMemberPointLedgerSql)) {
        echo "<p style='color:green;'>Verified table `" . MEMBER_POINT_LEDGER . "` for member point ledger rows.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . MEMBER_POINT_LEDGER . "`: " . $conn->error . "</p>";
    }

    $createMemberRedeemSettingSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . MEMBER_REDEEM_SETTING . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `point_tier` INT NOT NULL DEFAULT 0,
        `redeemable_gift` VARCHAR(255) NOT NULL,
        `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `selling_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `cost_ratio` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        `remark` TEXT DEFAULT NULL,
        `shopee_lazada_redeem_order` INT NOT NULL DEFAULT 0,
        `private_redeem_order` INT NOT NULL DEFAULT 0,
        `display_order` INT NOT NULL DEFAULT 0,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_member_redeem_point_status` (`point_tier`, `status`),
        KEY `idx_member_redeem_display_status` (`display_order`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createMemberRedeemSettingSql)) {
        echo "<p style='color:green;'>Verified table `" . MEMBER_REDEEM_SETTING . "` for member redeem setting rows.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . MEMBER_REDEEM_SETTING . "`: " . $conn->error . "</p>";
    }

    $createMemberPointTransactionSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . MEMBER_POINT_TRANSACTION . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `platform` VARCHAR(20) NOT NULL,
        `customer_id` INT NOT NULL,
        `customer_label` VARCHAR(255) DEFAULT NULL,
        `transaction_type` VARCHAR(30) NOT NULL DEFAULT 'earn',
        `wallet_type` VARCHAR(30) NOT NULL DEFAULT 'frozen',
        `points_change` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
        `source_platform` VARCHAR(20) DEFAULT NULL,
        `source_table` VARCHAR(80) DEFAULT NULL,
        `source_record_id` INT DEFAULT NULL,
        `source_key` VARCHAR(150) NOT NULL,
        `reference_label` VARCHAR(255) DEFAULT NULL,
        `redeem_setting_id` INT DEFAULT NULL,
        `expiry_date` DATE DEFAULT NULL,
        `metadata_json` LONGTEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_member_point_tx_source_key` (`source_key`),
        KEY `idx_member_point_tx_platform_customer_expiry` (`platform`, `customer_id`, `status`, `expiry_date`),
        KEY `idx_member_point_tx_wallet` (`platform`, `customer_id`, `wallet_type`, `status`, `expiry_date`),
        KEY `idx_member_point_tx_source_record` (`source_table`, `source_record_id`, `status`),
        KEY `idx_member_point_tx_redeem_setting` (`redeem_setting_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createMemberPointTransactionSql)) {
        echo "<p style='color:green;'>Verified table `" . MEMBER_POINT_TRANSACTION . "` for shared member point transactions.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . MEMBER_POINT_TRANSACTION . "`: " . $conn->error . "</p>";
    }

    $createMemberPointMemberStateSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . MEMBER_POINT_MEMBER_STATE . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `platform` VARCHAR(20) NOT NULL,
        `customer_id` INT NOT NULL,
        `customer_label` VARCHAR(255) DEFAULT NULL,
        `current_tier_key` VARCHAR(30) NOT NULL DEFAULT 'normal',
        `current_tier_label` VARCHAR(80) NOT NULL DEFAULT 'Normal Member',
        `qualifying_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `private_point_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0300,
        `monthly_bonus_points` INT NOT NULL DEFAULT 0,
        `last_bonus_month` CHAR(7) DEFAULT NULL,
        `last_evaluated_month` CHAR(7) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_member_point_state_customer` (`platform`, `customer_id`),
        KEY `idx_member_point_state_tier` (`current_tier_key`, `status`),
        KEY `idx_member_point_state_bonus_month` (`last_bonus_month`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createMemberPointMemberStateSql)) {
        echo "<p style='color:green;'>Verified table `" . MEMBER_POINT_MEMBER_STATE . "` for member point tier state rows.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . MEMBER_POINT_MEMBER_STATE . "`: " . $conn->error . "</p>";
    }

    $createMemberBonusTierSettingSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . MEMBER_BONUS_TIER_SETTING . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tier_key` VARCHAR(40) NOT NULL,
        `tier_name` VARCHAR(120) NOT NULL,
        `requirement_type` VARCHAR(40) NOT NULL DEFAULT 'register',
        `minimum_purchase_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `private_point_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0300,
        `marketplace_point_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0300,
        `bonus_points` INT NOT NULL DEFAULT 0,
        `bonus_frequency` VARCHAR(20) NOT NULL DEFAULT 'monthly',
        `remark` TEXT DEFAULT NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_member_bonus_tier_key` (`tier_key`),
        KEY `idx_member_bonus_tier_display` (`display_order`, `status`),
        KEY `idx_member_bonus_tier_requirement` (`requirement_type`, `minimum_purchase_amount`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createMemberBonusTierSettingSql)) {
        echo "<p style='color:green;'>Verified table `" . MEMBER_BONUS_TIER_SETTING . "` for member bonus tier settings.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . MEMBER_BONUS_TIER_SETTING . "`: " . $conn->error . "</p>";
    }

    $createMemberBonusSpecialSettingSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . MEMBER_BONUS_SPECIAL_SETTING . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `bonus_key` VARCHAR(40) NOT NULL,
        `bonus_name` VARCHAR(120) NOT NULL,
        `minimum_purchase_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `minimum_purchase_times` INT NOT NULL DEFAULT 0,
        `bonus_points` INT NOT NULL DEFAULT 0,
        `remark` TEXT DEFAULT NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uq_member_bonus_special_key` (`bonus_key`),
        KEY `idx_member_bonus_special_display` (`display_order`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createMemberBonusSpecialSettingSql)) {
        echo "<p style='color:green;'>Verified table `" . MEMBER_BONUS_SPECIAL_SETTING . "` for member bonus special settings.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . MEMBER_BONUS_SPECIAL_SETTING . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn($conn, $db_cms, MEMBER_POINT_TRANSACTION, 'wallet_type', "ALTER TABLE `" . MEMBER_POINT_TRANSACTION . "` ADD COLUMN `wallet_type` VARCHAR(30) NOT NULL DEFAULT 'frozen' AFTER `transaction_type`", "Verified `" . MEMBER_POINT_TRANSACTION . "` includes `wallet_type`.");
    migrationEnsureIndex($conn, $db_cms, MEMBER_POINT_TRANSACTION, 'idx_member_point_tx_wallet', "ALTER TABLE `" . MEMBER_POINT_TRANSACTION . "` ADD INDEX `idx_member_point_tx_wallet` (`platform`, `customer_id`, `wallet_type`, `status`, `expiry_date`)", "Verified `" . MEMBER_POINT_TRANSACTION . "` wallet type index.");
    migrationEnsureDecimalColumn($conn, $db_cms, MEMBER_POINT_LEDGER, 'base_points', 'DECIMAL(12,4) NOT NULL DEFAULT 0.0000', "Updated `" . MEMBER_POINT_LEDGER . "`.`base_points` to four-decimal points.");
    migrationEnsureDecimalColumn($conn, $db_cms, MEMBER_POINT_LEDGER, 'bonus_points', 'DECIMAL(12,4) NOT NULL DEFAULT 0.0000', "Updated `" . MEMBER_POINT_LEDGER . "`.`bonus_points` to four-decimal points.");
    migrationEnsureDecimalColumn($conn, $db_cms, MEMBER_POINT_LEDGER, 'total_points', 'DECIMAL(12,4) NOT NULL DEFAULT 0.0000', "Updated `" . MEMBER_POINT_LEDGER . "`.`total_points` to four-decimal points.");
    migrationEnsureDecimalColumn($conn, $db_cms, MEMBER_POINT_TRANSACTION, 'points_change', 'DECIMAL(12,4) NOT NULL DEFAULT 0.0000', "Updated `" . MEMBER_POINT_TRANSACTION . "`.`points_change` to four-decimal points.");

    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'airbill_no', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `airbill_no` VARCHAR(150) DEFAULT NULL AFTER `order_status`", "Verified `" . FB_ORDER_REQ . "` includes `airbill_no`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'airbill_attachment', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `airbill_attachment` TEXT DEFAULT NULL AFTER `airbill_no`", "Verified `" . FB_ORDER_REQ . "` includes `airbill_attachment`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'stock_out_warehouse_id', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `stock_out_warehouse_id` INT DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . FB_ORDER_REQ . "` includes `stock_out_warehouse_id`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'delay_remark', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `delay_remark` TEXT DEFAULT NULL AFTER `stock_out_warehouse_id`", "Verified `" . FB_ORDER_REQ . "` includes `delay_remark`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'member_point_platform', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `member_point_platform` VARCHAR(20) DEFAULT NULL AFTER `delay_remark`", "Verified `" . FB_ORDER_REQ . "` includes `member_point_platform`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'member_point_customer_id', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `member_point_customer_id` INT DEFAULT NULL AFTER `member_point_platform`", "Verified `" . FB_ORDER_REQ . "` includes `member_point_customer_id`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'member_point_customer_label', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `member_point_customer_label` VARCHAR(255) DEFAULT NULL AFTER `member_point_customer_id`", "Verified `" . FB_ORDER_REQ . "` includes `member_point_customer_label`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'member_point_redeem_id', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `member_point_redeem_id` INT DEFAULT NULL AFTER `member_point_customer_label`", "Verified `" . FB_ORDER_REQ . "` includes `member_point_redeem_id`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'member_point_redeem_points', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `member_point_redeem_points` INT NOT NULL DEFAULT 0 AFTER `member_point_redeem_id`", "Verified `" . FB_ORDER_REQ . "` includes `member_point_redeem_points`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'member_point_transaction_id', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `member_point_transaction_id` INT DEFAULT NULL AFTER `member_point_redeem_points`", "Verified `" . FB_ORDER_REQ . "` includes `member_point_transaction_id`.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'idx_fb_order_status', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD INDEX `idx_fb_order_status` (`order_status`)", "Verified `" . FB_ORDER_REQ . "` status index.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'idx_fb_order_airbill', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD INDEX `idx_fb_order_airbill` (`airbill_no`)", "Verified `" . FB_ORDER_REQ . "` airbill index.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'idx_fb_order_member_point_customer', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD INDEX `idx_fb_order_member_point_customer` (`member_point_platform`, `member_point_customer_id`)", "Verified `" . FB_ORDER_REQ . "` member point customer index.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'idx_fb_order_member_point_transaction', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD INDEX `idx_fb_order_member_point_transaction` (`member_point_transaction_id`)", "Verified `" . FB_ORDER_REQ . "` member point transaction index.");

    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'airbill_no', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `airbill_no` VARCHAR(150) DEFAULT NULL AFTER `order_status`", "Verified `" . WEB_ORDER_REQ . "` includes `airbill_no`.");
    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'airbill_attachment', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `airbill_attachment` TEXT DEFAULT NULL AFTER `airbill_no`", "Verified `" . WEB_ORDER_REQ . "` includes `airbill_attachment`.");
    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'stock_out_warehouse_id', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `stock_out_warehouse_id` INT DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . WEB_ORDER_REQ . "` includes `stock_out_warehouse_id`.");
    migrationEnsureColumn($conn, $db_fin, WEB_ORDER_REQ, 'delay_remark', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD COLUMN `delay_remark` TEXT DEFAULT NULL AFTER `stock_out_warehouse_id`", "Verified `" . WEB_ORDER_REQ . "` includes `delay_remark`.");
    migrationEnsureIndex($conn, $db_fin, WEB_ORDER_REQ, 'idx_web_order_status', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD INDEX `idx_web_order_status` (`order_status`)", "Verified `" . WEB_ORDER_REQ . "` status index.");
    migrationEnsureIndex($conn, $db_fin, WEB_ORDER_REQ, 'idx_web_order_code', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD INDEX `idx_web_order_code` (`order_id`)", "Verified `" . WEB_ORDER_REQ . "` order code index.");
    migrationEnsureIndex($conn, $db_fin, WEB_ORDER_REQ, 'idx_web_order_airbill', "ALTER TABLE `" . WEB_ORDER_REQ . "` ADD INDEX `idx_web_order_airbill` (`airbill_no`)", "Verified `" . WEB_ORDER_REQ . "` airbill index.");

    $createLuckyDrawPrizeSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . LUCKY_DRAW_PRIZE . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `prize_name` VARCHAR(190) NOT NULL,
        `prize_type` VARCHAR(30) NOT NULL DEFAULT 'voucher',
        `voucher_code` VARCHAR(255) DEFAULT NULL,
        `prize_image` VARCHAR(255) DEFAULT NULL,
        `weight` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
        `total_stock` INT NOT NULL DEFAULT 0,
        `reserved_stock` INT NOT NULL DEFAULT 0,
        `assigned_stock` INT NOT NULL DEFAULT 0,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y',
        `label_color` CHAR(7) DEFAULT NULL,
        `package_id` INT DEFAULT NULL,
        `country_id` INT DEFAULT NULL,
        `brand_id` INT DEFAULT NULL,
        `series_id` INT DEFAULT NULL,
        `fb_page_id` INT DEFAULT NULL,
        `channel_id` INT DEFAULT NULL,
        `pay_method_id` INT DEFAULT NULL,
        `stock_out_warehouse_id` INT DEFAULT NULL,
        `sales_pic_user_id` INT DEFAULT NULL,
        `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `remark` TEXT DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_lucky_draw_prize_enabled` (`is_enabled`, `status`),
        KEY `idx_lucky_draw_prize_label_color` (`label_color`, `status`),
        KEY `idx_lucky_draw_prize_package` (`package_id`, `stock_out_warehouse_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createLuckyDrawPrizeSql)) {
        echo "<p style='color:green;'>Verified table `" . LUCKY_DRAW_PRIZE . "` for Lucky Draw prizes.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . LUCKY_DRAW_PRIZE . "`: " . $conn->error . "</p>";
    }

    migrationEnsureColumn($conn, $db_cms, LUCKY_DRAW_PRIZE, 'voucher_code', "ALTER TABLE `" . LUCKY_DRAW_PRIZE . "` ADD COLUMN `voucher_code` VARCHAR(255) DEFAULT NULL AFTER `prize_type`", "Verified `" . LUCKY_DRAW_PRIZE . "` includes `voucher_code`.");

    $createLuckyDrawLogSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . LUCKY_DRAW_DRAW_LOG . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `member_id_hmac` CHAR(64) NOT NULL,
        `member_display_name` VARCHAR(190) DEFAULT NULL,
        `birthday_yymmdd` CHAR(6) DEFAULT NULL,
        `ip_hmac` CHAR(64) DEFAULT NULL,
        `prize_id` INT NOT NULL,
        `prize_name_snapshot` VARCHAR(255) DEFAULT NULL,
        `prize_type_snapshot` VARCHAR(30) DEFAULT NULL,
        `facebook_order_request_id` INT DEFAULT NULL,
        `redeem_reference` VARCHAR(60) DEFAULT NULL,
        `draw_state` VARCHAR(30) NOT NULL DEFAULT 'won',
        `claim_state` VARCHAR(30) NOT NULL DEFAULT 'awaiting_claim',
        `email_state` VARCHAR(30) NOT NULL DEFAULT 'awaiting_claim',
        `claim_token_hash` CHAR(64) DEFAULT NULL,
        `claim_email` VARCHAR(190) DEFAULT NULL,
        `reservation_expires_at` DATETIME DEFAULT NULL,
        `email_locked_at` DATETIME DEFAULT NULL,
        `email_lock_token` VARCHAR(64) DEFAULT NULL,
        `sent_at` DATETIME DEFAULT NULL,
        `retry_count` INT NOT NULL DEFAULT 0,
        `failure_message` VARCHAR(255) DEFAULT NULL,
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        UNIQUE KEY `uniq_lucky_draw_member` (`member_id_hmac`),
        KEY `idx_lucky_draw_claim_token` (`claim_token_hash`, `status`),
        KEY `idx_lucky_draw_prize_state` (`claim_state`, `email_state`, `status`),
        KEY `idx_lucky_draw_reservation_expiry` (`reservation_expires_at`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createLuckyDrawLogSql)) {
        echo "<p style='color:green;'>Verified table `" . LUCKY_DRAW_DRAW_LOG . "` for Lucky Draw draw logs.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . LUCKY_DRAW_DRAW_LOG . "`: " . $conn->error . "</p>";
    }

    $createLuckyDrawVirtualWinnerSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . LUCKY_DRAW_VIRTUAL_WINNER . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `display_name` VARCHAR(190) DEFAULT NULL,
        `display_prize` VARCHAR(190) DEFAULT NULL,
        `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y',
        `create_by` VARCHAR(30) DEFAULT NULL,
        `create_date` DATE DEFAULT NULL,
        `create_time` TIME DEFAULT NULL,
        `update_by` VARCHAR(30) DEFAULT NULL,
        `update_date` DATE DEFAULT NULL,
        `update_time` TIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_lucky_draw_virtual_board` (`is_enabled`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createLuckyDrawVirtualWinnerSql)) {
        echo "<p style='color:green;'>Verified table `" . LUCKY_DRAW_VIRTUAL_WINNER . "` for Lucky Draw virtual board.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . LUCKY_DRAW_VIRTUAL_WINNER . "`: " . $conn->error . "</p>";
    }
    migrationEnsureColumn($conn, $db_cms, LUCKY_DRAW_VIRTUAL_WINNER, 'is_enabled', "ALTER TABLE `" . LUCKY_DRAW_VIRTUAL_WINNER . "` ADD COLUMN `is_enabled` CHAR(1) NOT NULL DEFAULT 'Y' AFTER `display_prize`", "Verified `" . LUCKY_DRAW_VIRTUAL_WINNER . "` includes `is_enabled`.");
    migrationEnsureIndex($conn, $db_cms, LUCKY_DRAW_VIRTUAL_WINNER, 'idx_lucky_draw_virtual_board', "ALTER TABLE `" . LUCKY_DRAW_VIRTUAL_WINNER . "` ADD INDEX `idx_lucky_draw_virtual_board` (`is_enabled`, `status`)", "Verified `" . LUCKY_DRAW_VIRTUAL_WINNER . "` enabled/status index.");

    $createLuckyDrawRequestLogSql = "CREATE TABLE IF NOT EXISTS `" . $db_cms . "`.`" . LUCKY_DRAW_REQUEST_LOG . "` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `request_type` VARCHAR(30) DEFAULT NULL,
        `member_id_hmac` CHAR(64) DEFAULT NULL,
        `ip_hmac` CHAR(64) DEFAULT NULL,
        `request_state` VARCHAR(30) DEFAULT NULL,
        `created_at` DATETIME DEFAULT NULL,
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        KEY `idx_lucky_draw_request_window` (`request_type`, `created_at`, `status`),
        KEY `idx_lucky_draw_request_member` (`member_id_hmac`, `created_at`),
        KEY `idx_lucky_draw_request_ip` (`ip_hmac`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($createLuckyDrawRequestLogSql)) {
        echo "<p style='color:green;'>Verified table `" . LUCKY_DRAW_REQUEST_LOG . "` for Lucky Draw request logs.</p>";
    } else {
        echo "<p style='color:red;'>Failed creating `" . LUCKY_DRAW_REQUEST_LOG . "`: " . $conn->error . "</p>";
    }
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'lucky_draw_log_id', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `lucky_draw_log_id` INT DEFAULT NULL AFTER `airbill_attachment`", "Verified `" . FB_ORDER_REQ . "` includes `lucky_draw_log_id`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'redeem_source', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `redeem_source` VARCHAR(60) DEFAULT NULL AFTER `lucky_draw_log_id`", "Verified `" . FB_ORDER_REQ . "` includes `redeem_source`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'redeem_reference', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `redeem_reference` VARCHAR(80) DEFAULT NULL AFTER `redeem_source`", "Verified `" . FB_ORDER_REQ . "` includes `redeem_reference`.");
    migrationEnsureColumn($conn, $db_fin, FB_ORDER_REQ, 'claim_email', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD COLUMN `claim_email` VARCHAR(190) DEFAULT NULL AFTER `redeem_reference`", "Verified `" . FB_ORDER_REQ . "` includes `claim_email`.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'uniq_fb_order_lucky_draw_log_id', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD UNIQUE KEY `uniq_fb_order_lucky_draw_log_id` (`lucky_draw_log_id`)", "Verified `" . FB_ORDER_REQ . "` duplicate Lucky Draw order protection.");
    migrationEnsureIndex($conn, $db_fin, FB_ORDER_REQ, 'idx_fb_order_redeem_reference', "ALTER TABLE `" . FB_ORDER_REQ . "` ADD INDEX `idx_fb_order_redeem_reference` (`redeem_source`, `redeem_reference`)", "Verified `" . FB_ORDER_REQ . "` redeem reference index.");

    migrationEnsureTableEngineInnoDb($conn, $db_fin, FB_ORDER_REQ, array(
        'allow_convert' => true,
        'require_flag' => true,
        'flag_name' => 'lucky_draw_fb_order_innodb',
        'warning_label' => 'facebook_order_request',
    ));

} else {
    echo "<p style='color:red;'>Failed selecting finance database for OMS migration.</p>";
}

if ($conn->select_db($db_cms)) {
    migrationEnsureColumnWithPreferredAfter($conn, $db_cms, 'package', 'platform_item_id', "TEXT DEFAULT NULL", 'item_code', "Verified `package` includes `platform_item_id`.");
    migrationEnsureColumnWithPreferredAfter($conn, $db_cms, 'package', 'parent_package_id', "INT DEFAULT NULL", 'product', "Verified `package` includes `parent_package_id`.");
    migrationEnsureIndex($conn, $db_cms, 'package', 'idx_package_parent_package_id', "ALTER TABLE `" . $db_cms . "`.`package` ADD INDEX `idx_package_parent_package_id` (`parent_package_id`)", "Verified `package` parent SKU lookup index.");
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
