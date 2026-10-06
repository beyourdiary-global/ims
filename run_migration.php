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

echo "====================\n";
echo "Done. 建议执行完从服务器删除本文件。\n";
