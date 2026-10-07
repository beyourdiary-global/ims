<?php
/**
 * run_migration.php — 一次性生产库迁移脚本
 * ------------------------------------------------------------------
 * 用法（在 cPanel 已 git pull 本文件后，浏览器访问）：
 *   https://cms.beyourdiary.com/run_migration.php?token=cms-migrate-2026
 *
 * 安全：必须带正确 token 才执行；执行完建议从服务器删除本文件，避免遗留危险入口。
 * 幂等：每条 ALTER 先查 information_schema，列/索引已存在就 SKIP，可反复执行。
 *
 * 涵盖：
 *   1) 生日三列（customer_info） + 抽奖相关列（lucky_draw_*）  —— 让 Lucky Draw 能开跑
 *   2) task_board_status.on_enter_assignee_mode  —— 任务1：move 到该 column 时改 assignee 的模式
 *      （注意：TASK_COLUMN 常量 = task_board_status，不是 task_column）
 *   3) user_record_log.log_type            —— 任务5：标记 tag 类变更，列表过滤掉
 */

define('MIGRATION_TOKEN', 'cms-migrate-2026');

if (!isset($_GET['token']) || $_GET['token'] !== MIGRATION_TOKEN) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Forbidden: invalid or missing token.\n");
}

// 抑制 init.php 可能产生的 HTML 输出，只保留我们自己的结果
ob_start();
require_once __DIR__ . '/init.php';
ob_end_clean();

if (!isset($connect) || !($connect instanceof mysqli)) {
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Error: database connection (\$connect) not available. init.php may require login; if so, use connection.php or adjust.\n");
}

header('Content-Type: text/plain; charset=UTF-8');

$res = mysqli_query($connect, 'SELECT DATABASE()');
$dbName = $res ? mysqli_fetch_row($res)[0] : '';

function migAddColumn($connect, $dbName, $table, $column, $definition)
{
    $escDb = mysqli_real_escape_string($connect, $dbName);
    $escTable = mysqli_real_escape_string($connect, $table);
    $escColumn = mysqli_real_escape_string($connect, $column);

    $check = mysqli_query(
        $connect,
        "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$escDb' AND TABLE_NAME = '$escTable' AND COLUMN_NAME = '$escColumn'"
    );
    if (!$check) {
        return "ERROR checking $table.$column: " . mysqli_error($connect) . "\n";
    }
    $row = mysqli_fetch_assoc($check);
    if ((int) $row['c'] > 0) {
        return "SKIP  $table.$column (already exists)\n";
    }
    if (mysqli_query($connect, "ALTER TABLE `$escTable` ADD COLUMN `$escColumn` $definition")) {
        return "OK    added $table.$column\n";
    }
    return "ERROR adding $table.$column: " . mysqli_error($connect) . "\n";
}

function migAddIndex($connect, $dbName, $table, $index, $definition)
{
    $escDb = mysqli_real_escape_string($connect, $dbName);
    $escTable = mysqli_real_escape_string($connect, $table);
    $escIndex = mysqli_real_escape_string($connect, $index);

    $check = mysqli_query(
        $connect,
        "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = '$escDb' AND TABLE_NAME = '$escTable' AND INDEX_NAME = '$escIndex'"
    );
    if (!$check) {
        return "ERROR checking index $table.$index: " . mysqli_error($connect) . "\n";
    }
    $row = mysqli_fetch_assoc($check);
    if ((int) $row['c'] > 0) {
        return "SKIP  index $table.$index (already exists)\n";
    }
    if (mysqli_query($connect, "ALTER TABLE `$escTable` ADD INDEX `$escIndex` $definition")) {
        return "OK    added index $table.$index\n";
    }
    return "ERROR adding index $table.$index: " . mysqli_error($connect) . "\n";
}

/**
 * Grant a pin group to a user group inside the `user_group`.`pins` blob,
 * which is stored as [+ separated [groupId:pinId,pinId] blocks]. Idempotent:
 * an already-present pin id is left alone and a no-op change reports SKIP.
 */
function migGrantPinGroupAccess($connect, $userGroupId, $pinGroupId, $pinIds)
{
    $userGroupId = (int) $userGroupId;
    $pinGroupId = (int) $pinGroupId;

    if ($userGroupId <= 0 || $pinGroupId <= 0) {
        return "ERROR invalid user_group / pin_group id\n";
    }

    $result = mysqli_query($connect, "SELECT `pins` FROM `user_group` WHERE `id` = " . $userGroupId . " LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        return "SKIP  user_group id " . $userGroupId . " not found\n";
    }

    $row = $result->fetch_assoc();
    $current = isset($row['pins']) ? (string) $row['pins'] : '';
    $targetKey = (string) $pinGroupId;
    $entries = array_filter(array_map('trim', explode('+', $current)), 'strlen');
    $rebuilt = array();
    $found = false;

    foreach ($entries as $entry) {
        $entry = trim($entry, '[]');
        $parts = explode(':', $entry, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $accessList = array_filter(array_map('trim', explode(',', $parts[1])), 'strlen');

        if ($key === $targetKey) {
            foreach ($pinIds as $pinId) {
                $pinId = (string) (int) $pinId;
                if ($pinId !== '0' && !in_array($pinId, $accessList, true)) {
                    $accessList[] = $pinId;
                }
            }
            $found = true;
        }

        $rebuilt[] = '[' . $key . ':' . implode(',', $accessList) . ']';
    }

    if (!$found) {
        $newList = array();
        foreach ($pinIds as $pinId) {
            $newList[] = (string) (int) $pinId;
        }
        $rebuilt[] = '[' . $targetKey . ':' . implode(',', $newList) . ']';
    }

    $updated = implode('+', $rebuilt);

    if ($updated === $current) {
        return "SKIP  user_group " . $userGroupId . " already has pin group " . $pinGroupId . "\n";
    }

    $safe = mysqli_real_escape_string($connect, $updated);
    if (mysqli_query($connect, "UPDATE `user_group` SET `pins` = '" . $safe . "' WHERE `id` = " . $userGroupId)) {
        return "OK    granted pin group " . $pinGroupId . " to user_group " . $userGroupId . "\n";
    }

    return "ERROR updating user_group " . $userGroupId . ": " . mysqli_error($connect) . "\n";
}

echo "Database: " . ($dbName === '' ? '(unknown)' : $dbName) . "\n";
echo "====================\n";

// ---- 1) 生日三列 + 抽奖相关列 ----
echo migAddColumn($connect, $dbName, 'customer_info', 'birthday_year', "SMALLINT DEFAULT NULL");
echo migAddColumn($connect, $dbName, 'customer_info', 'birthday_month', "TINYINT DEFAULT NULL");
echo migAddColumn($connect, $dbName, 'customer_info', 'birthday_day', "TINYINT DEFAULT NULL");

echo migAddColumn($connect, $dbName, 'lucky_draw_draw_log', 'customer_username', "VARCHAR(190) DEFAULT NULL");
echo migAddColumn($connect, $dbName, 'lucky_draw_draw_log', 'birthday_year', "SMALLINT DEFAULT NULL");
echo migAddColumn($connect, $dbName, 'lucky_draw_draw_log', 'birthday_month', "TINYINT DEFAULT NULL");
echo migAddColumn($connect, $dbName, 'lucky_draw_prize', 'voucher_code', "VARCHAR(255) DEFAULT NULL");
echo migAddColumn($connect, $dbName, 'lucky_draw_virtual_winner', 'is_enabled', "CHAR(1) NOT NULL DEFAULT 'Y'");
echo migAddIndex($connect, $dbName, 'lucky_draw_virtual_winner', 'idx_lucky_draw_virtual_board', "(`is_enabled`, `status`)");

// ---- 2) 任务1：move 到该 column 时改 assignee 的模式 ----
echo migAddColumn($connect, $dbName, TASK_COLUMN, 'on_enter_assignee_mode', "VARCHAR(16) NOT NULL DEFAULT 'keep' COMMENT 'keep|reporter|clear'");

// ---- 3) 任务5：标记 tag 类变更，列表过滤掉 ----
echo migAddColumn($connect, $dbName, 'user_record_log', 'log_type', "VARCHAR(32) DEFAULT NULL COMMENT 'tag=标签变更，列表过滤掉'");

// ---- 4) Facebook customer record birthday ----
echo migAddColumn($connect, $dbName, 'customer_facebook_deals_transaction', 'birthday_year', "SMALLINT DEFAULT NULL COMMENT 'Customer birthday year'");
echo migAddColumn($connect, $dbName, 'customer_facebook_deals_transaction', 'birthday_month', "TINYINT DEFAULT NULL COMMENT 'Customer birthday month 1-12'");
echo migAddColumn($connect, $dbName, 'customer_facebook_deals_transaction', 'birthday_day', "TINYINT DEFAULT NULL COMMENT 'Customer birthday day 1-31'");

// ---- 5) Lazada / Shopee customer birthday ----
echo migAddColumn($connect, $dbName, 'customer_lazada_deals_transaction', 'birthday_year', "SMALLINT DEFAULT NULL COMMENT 'Customer birthday year'");
echo migAddColumn($connect, $dbName, 'customer_lazada_deals_transaction', 'birthday_month', "TINYINT DEFAULT NULL COMMENT 'Customer birthday month 1-12'");
echo migAddColumn($connect, $dbName, 'customer_lazada_deals_transaction', 'birthday_day', "TINYINT DEFAULT NULL COMMENT 'Customer birthday day 1-31'");
if (isset($finance_connect) && ($finance_connect instanceof mysqli)) {
    echo migAddColumn($finance_connect, dbFinance, 'shopee_customer_info', 'birthday_year', "SMALLINT DEFAULT NULL COMMENT 'Customer birthday year'");
    echo migAddColumn($finance_connect, dbFinance, 'shopee_customer_info', 'birthday_month', "TINYINT DEFAULT NULL COMMENT 'Customer birthday month 1-12'");
    echo migAddColumn($finance_connect, dbFinance, 'shopee_customer_info', 'birthday_day', "TINYINT DEFAULT NULL COMMENT 'Customer birthday day 1-31'");
} else {
    echo "SKIP  shopee_customer_info.birthday (finance connection unavailable)\n";
}

// ---- 6) API keys (api/api_key) ----
// The REST API under /api authenticates with keys stored here. Only the
// SHA-256 hash of a key is kept; the plaintext is shown once at creation.
$apiKeyTable = 'api_key';
$escDbName = mysqli_real_escape_string($connect, $dbName);
$apiKeyCheck = mysqli_query(
    $connect,
    "SELECT COUNT(*) AS c FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = '$escDbName' AND TABLE_NAME = '$apiKeyTable'"
);
$apiKeyExists = false;
if ($apiKeyCheck && ($apiKeyRow = mysqli_fetch_assoc($apiKeyCheck))) {
    $apiKeyExists = ((int) $apiKeyRow['c'] > 0);
}
if ($apiKeyExists) {
    echo "SKIP  table $apiKeyTable (already exists)\n";
} else {
    $apiKeySql = "CREATE TABLE `$apiKeyTable` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(190) NOT NULL DEFAULT '',
        `key_hash` CHAR(64) NOT NULL,
        `key_prefix` VARCHAR(24) NOT NULL DEFAULT '',
        `scopes` VARCHAR(255) NOT NULL DEFAULT 'read',
        `status` CHAR(1) NOT NULL DEFAULT 'A',
        `created_at` DATETIME DEFAULT NULL,
        `created_by` VARCHAR(190) DEFAULT NULL,
        `last_used_at` DATETIME DEFAULT NULL,
        `last_used_ip` VARCHAR(64) DEFAULT NULL,
        `request_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_api_key_hash` (`key_hash`),
        KEY `idx_api_key_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    if (mysqli_query($connect, $apiKeySql)) {
        echo "OK    created table $apiKeyTable\n";
    } else {
        echo "ERROR creating table $apiKeyTable: " . mysqli_error($connect) . "\n";
    }
}


// ---- 7) API Key Manager pin group (Super Admin only) ----
// api/key_manager.php is gated by pin group 170 AND by user group 1.
// Creating the pin group here makes the "API Key Manager" menu entry
// and the page permission manageable from Users -> Pin Group later.
$apiKeyPinGroupId = 170;
$apiKeyPinGroupSql = "INSERT INTO `pin_group` (`id`, `name`, `pins`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES
    ($apiKeyPinGroupId, 'API Key Manager', '1,2,3,4', 'REST API key management (Super Admin only)', '1', CURDATE(), CURTIME(), 'A')
    ON DUPLICATE KEY UPDATE
        `name` = VALUES(`name`),
        `pins` = VALUES(`pins`),
        `remark` = VALUES(`remark`),
        `status` = 'A'";
if (mysqli_query($connect, $apiKeyPinGroupSql)) {
    echo "OK    verified pin group " . $apiKeyPinGroupId . " (API Key Manager)\n";
} else {
    echo "ERROR creating pin group " . $apiKeyPinGroupId . ": " . mysqli_error($connect) . "\n";
}
echo migGrantPinGroupAccess($connect, 1, $apiKeyPinGroupId, array(1, 2, 3, 4));

echo "====================\n";
echo "Done. 建议执行完从服务器删除本文件。\n";
