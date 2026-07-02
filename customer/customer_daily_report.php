<?php
$currentPagePin = 150;
$pageTitle = 'Customer Daily Report';
$displayPageTitle = 'Customer Daily Report';
$disablePinGroupPageTitleSync = true;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$pinGroupPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($pinGroupPageTitle !== '') {
    $pageTitle = $pinGroupPageTitle;
    $displayPageTitle = $pinGroupPageTitle;
}

$reportAccess = checkPinByGroupId($connect, $currentPagePin);
$canViewPage = isActionAllowed('View', $reportAccess);
if (!$canViewPage) {
    echo '<script>alert("You do not have permission to view Customer Daily Report."); location.replace("dashboard.php");</script>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && USER_ID) {
    $safeAuditUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeAuditPageTitle = htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8');
    audit_log(array(
        'log_act' => 'View',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $safeAuditUserName . " viewed the page <b>" . $safeAuditPageTitle . "</b>.",
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

$selectedMonth = trim((string) input('month'));
$selectedYear = trim((string) input('year'));
$selectedDate = trim((string) input('date'));
$currentYear = date('Y');
$selectedMonth = ($selectedMonth === '' || preg_match('/^(0[1-9]|1[0-2])$/', $selectedMonth)) ? $selectedMonth : date('m');
$selectedYear = ($selectedYear === '' || preg_match('/^\d{4}$/', $selectedYear)) ? $selectedYear : $currentYear;
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) ? $selectedDate : '';

$platformConfigs = customerDailyReportGetPlatformConfigs();
$platformTabs = array('all' => 'All');
foreach ($platformConfigs as $platformKey => $platformConfig) {
    $platformTabs[$platformKey] = isset($platformConfig['label']) ? (string) $platformConfig['label'] : ucfirst((string) $platformKey);
}
$customerTagAssignmentTable = defined('CUS_TAG_ASSIGNMENT') ? CUS_TAG_ASSIGNMENT : 'customer_tag_assignment';

$activePlatform = customerDailyReportNormalizePlatformKey(input('platform'), true);
if ($activePlatform === '') {
    $activePlatform = 'all';
}

$detailRows = array();
$reportError = '';
$supportedTables = customerDailyReportGetSupportedTables();
$supportedAuditTables = $supportedTables;
$supportedAuditTables[] = $customerTagAssignmentTable;
$supportedAuditTables = array_values(array_unique(array_filter($supportedAuditTables, 'strlen')));

$matchPlatformKeyFromLabel = function ($customerLabel) use ($platformConfigs) {
    $customerLabel = trim((string) $customerLabel);
    foreach ($platformConfigs as $platformKey => $platformConfig) {
        $pageTitle = isset($platformConfig['page_title']) ? trim((string) $platformConfig['page_title']) : '';
        if ($pageTitle !== '' && stripos($customerLabel, $pageTitle) === 0) {
            return $platformKey;
        }
    }

    return '';
};

$extractCustomerNameFromLabel = function ($customerLabel) {
    $customerLabel = trim((string) $customerLabel);
    if (preg_match('/\[(.*?)\]/', $customerLabel, $matches)) {
        return trim((string) $matches[1]);
    }

    return '';
};

$parseTagAuditDetails = function ($auditRow) use ($matchPlatformKeyFromLabel, $extractCustomerNameFromLabel) {
    if (!is_array($auditRow)) {
        return null;
    }

    $plainMessage = html_entity_decode(strip_tags((string) ($auditRow['action_message'] ?? '')), ENT_QUOTES, 'UTF-8');
    $queryRecord = (string) ($auditRow['query_record'] ?? '');
    $platformKey = '';
    $recordId = 0;

    if (preg_match("/platform\\s*=\\s*'([^']+)'/i", $queryRecord, $platformMatches)) {
        $platformKey = customerDailyReportNormalizePlatformKey($platformMatches[1]);
    }
    if (preg_match("/customer_id\\s*=\\s*'?(\\d+)'?/i", $queryRecord, $recordMatches)) {
        $recordId = (int) $recordMatches[1];
    }

    $customerLabel = '';
    $oldValue = 'Empty Value';
    $newValue = 'Empty Value';
    $actionType = '';

    if (preg_match('/assigned customer tag \[(.*?)\] to (.*?) \(ID = (\d+)\)\.?/i', $plainMessage, $matches)) {
        $actionType = 'Assign Tag';
        $newValue = trim((string) $matches[1]);
        $customerLabel = trim((string) $matches[2]);
        if ($recordId <= 0) {
            $recordId = (int) $matches[3];
        }
    } else if (preg_match('/changed customer tag from \[(.*?)\] to \[(.*?)\] for (.*?) \(ID = (\d+)\)\.?/i', $plainMessage, $matches)) {
        $actionType = 'Change Tag';
        $oldValue = trim((string) $matches[1]);
        $newValue = trim((string) $matches[2]);
        $customerLabel = trim((string) $matches[3]);
        if ($recordId <= 0) {
            $recordId = (int) $matches[4];
        }
    } else if (preg_match('/removed customer tag \[(.*?)\] from (.*?) \(ID = (\d+)\)\.?/i', $plainMessage, $matches)) {
        $actionType = 'Remove Tag';
        $oldValue = trim((string) $matches[1]);
        $customerLabel = trim((string) $matches[2]);
        if ($recordId <= 0) {
            $recordId = (int) $matches[3];
        }
    } else {
        return null;
    }

    if ($platformKey === '' && $customerLabel !== '') {
        $platformKey = $matchPlatformKeyFromLabel($customerLabel);
    }

    return array(
        'platform_key' => $platformKey,
        'record_id' => $recordId,
        'customer_name' => $extractCustomerNameFromLabel($customerLabel),
        'action_type' => $actionType,
        'activity_kind' => 'tag',
        'change_details' => array(
            array(
                'field_name' => 'Customer Tag',
                'old_value' => $oldValue !== '' ? $oldValue : 'Empty Value',
                'new_value' => $newValue !== '' ? $newValue : 'Empty Value',
            ),
        ),
    );
};

$parseDeleteAuditDetails = function ($auditRow, $platformKey) {
    if (!is_array($auditRow)) {
        return null;
    }

    $plainMessage = html_entity_decode(strip_tags((string) ($auditRow['action_message'] ?? '')), ENT_QUOTES, 'UTF-8');
    $deletedName = '';
    if (preg_match('/deleted the data\s*\[\s*ID\s*=\s*\d+\s*\]\s*(.*?)\s*from\s+/i', $plainMessage, $matches)) {
        $deletedName = trim((string) $matches[1]);
    }

    return array(
        'action_type' => 'Delete',
        'activity_kind' => 'record',
        'deleted_name' => $deletedName,
        'change_details' => array(
            array(
                'field_name' => customerDailyReportGetDeleteFieldLabel($platformKey),
                'old_value' => $deletedName !== '' ? $deletedName : 'Empty Value',
                'new_value' => 'Empty Value',
            ),
        ),
    );
};

$buildDetailRow = function ($auditRow, $platformKey, $recordId, $customerMeta, $userMeta, $changeDetails, $actionType, $activityKind, $fallbackCustomerName = '') use ($platformConfigs) {
    $platformConfig = isset($platformConfigs[$platformKey]) ? $platformConfigs[$platformKey] : array();
    $customerName = isset($customerMeta['display_name']) ? (string) $customerMeta['display_name'] : '';
    if ($customerName === '' || strpos($customerName, 'Record #') === 0 || $customerName === 'Unknown Record') {
        if (trim((string) $fallbackCustomerName) !== '') {
            $customerName = trim((string) $fallbackCustomerName);
        } else if ($recordId > 0) {
            $customerName = 'Record #' . $recordId;
        } else {
            $customerName = 'Unknown Record';
        }
    }

    return array(
        'audit_id' => isset($auditRow['id']) ? (int) $auditRow['id'] : 0,
        'audit_action' => isset($auditRow['log_action']) ? (int) $auditRow['log_action'] : 0,
        'activity_kind' => $activityKind,
        'action_type' => $actionType,
        'edit_datetime' => trim((string) ($auditRow['create_date'] ?? '') . ' ' . (string) ($auditRow['create_time'] ?? '')),
        'edit_date' => (string) ($auditRow['create_date'] ?? ''),
        'edit_time' => (string) ($auditRow['create_time'] ?? ''),
        'user_id' => isset($auditRow['user_id']) ? (int) $auditRow['user_id'] : 0,
        'user_name' => isset($userMeta['display_name']) ? (string) $userMeta['display_name'] : ((isset($auditRow['user_id']) && (int) $auditRow['user_id'] > 0) ? ('User #' . (int) $auditRow['user_id']) : 'Unknown User'),
        'user_group_badge_html' => isset($userMeta['group_badge_html']) ? (string) $userMeta['group_badge_html'] : '',
        'platform_key' => $platformKey,
        'platform_label' => isset($platformConfig['label']) ? (string) $platformConfig['label'] : ucfirst((string) $platformKey),
        'record_id' => (int) $recordId,
        'customer_name' => $customerName,
        'record_url' => isset($customerMeta['record_url']) ? (string) $customerMeta['record_url'] : '',
        'change_details' => $changeDetails,
    );
};

if (!empty($supportedAuditTables)) {
    $safeSelectedDate = mysqli_real_escape_string($connect, $selectedDate);
    $safeTables = array();
    foreach ($supportedAuditTables as $supportedTable) {
        $safeTables[] = "'" . mysqli_real_escape_string($connect, (string) $supportedTable) . "'";
    }

    $dateFilters = array();
    if ($selectedDate !== '') {
        $dateFilters[] = "`create_date` = '" . $safeSelectedDate . "'";
    } else {
        if ($selectedMonth !== '') {
            $dateFilters[] = "MONTH(`create_date`) = '" . mysqli_real_escape_string($connect, $selectedMonth) . "'";
        }
        if ($selectedYear !== '') {
            $dateFilters[] = "YEAR(`create_date`) = '" . mysqli_real_escape_string($connect, $selectedYear) . "'";
        }
    }

    $reportQuery = "SELECT
            `id`,
            `user_id`,
            `log_action`,
            `action_message`,
            `query_record`,
            `query_table`,
            `old_value`,
            `new_value`,
            `changes`,
            `create_date`,
            `create_time`
        FROM `" . AUDIT_LOG . "`
        WHERE `log_action` IN ('" . (int) get_allowed_audit_actions('edit') . "', '" . (int) get_allowed_audit_actions('add') . "', '" . (int) get_allowed_audit_actions('delete') . "')
            AND `query_table` IN (" . implode(',', $safeTables) . ")
            " . (!empty($dateFilters) ? "AND " . implode(' AND ', $dateFilters) : '') . "
            AND LOWER(`action_message`) NOT LIKE '%fail to%'
        ORDER BY `create_date` DESC, `create_time` DESC, `id` DESC";

    $reportResult = mysqli_query($connect, $reportQuery);
    if (!$reportResult) {
        $reportError = mysqli_error($connect);
    } else {
        $rawRows = array();
        $userIds = array();

        while ($auditRow = mysqli_fetch_assoc($reportResult)) {
            $rawRows[] = $auditRow;
            $userIds[] = isset($auditRow['user_id']) ? (int) $auditRow['user_id'] : 0;
        }

        $userMetaMap = customerDailyReportLoadUserMetaMap($connect, $userIds);

        foreach ($rawRows as $auditRow) {
            $userId = isset($auditRow['user_id']) ? (int) $auditRow['user_id'] : 0;
            $userMeta = isset($userMetaMap[$userId]) ? $userMetaMap[$userId] : array();
            $queryTable = isset($auditRow['query_table']) ? (string) $auditRow['query_table'] : '';

            if ($queryTable === $customerTagAssignmentTable) {
                $tagAuditDetails = $parseTagAuditDetails($auditRow);
                if ($tagAuditDetails === null) {
                    continue;
                }

                $platformKey = isset($tagAuditDetails['platform_key']) ? (string) $tagAuditDetails['platform_key'] : '';
                $recordId = isset($tagAuditDetails['record_id']) ? (int) $tagAuditDetails['record_id'] : 0;
                if ($platformKey === '') {
                    continue;
                }

                $customerMeta = customerDailyReportGetCustomerMeta($connect, $finance_connect, $platformKey, $recordId);
                $detailRows[] = $buildDetailRow(
                    $auditRow,
                    $platformKey,
                    $recordId,
                    $customerMeta,
                    $userMeta,
                    isset($tagAuditDetails['change_details']) ? $tagAuditDetails['change_details'] : array(),
                    isset($tagAuditDetails['action_type']) ? (string) $tagAuditDetails['action_type'] : 'Change Tag',
                    'tag',
                    isset($tagAuditDetails['customer_name']) ? (string) $tagAuditDetails['customer_name'] : ''
                );
                continue;
            }

            $platformConfig = customerDailyReportGetPlatformConfigByTable($queryTable);
            if (empty($platformConfig)) {
                continue;
            }

            $platformKey = isset($platformConfig['platform']) ? (string) $platformConfig['platform'] : '';
            if ($platformKey === '') {
                continue;
            }

            $recordId = customerDailyReportExtractRecordId($auditRow);
            $customerMeta = customerDailyReportGetCustomerMeta($connect, $finance_connect, $platformKey, $recordId);
            $auditAction = isset($auditRow['log_action']) ? (int) $auditRow['log_action'] : 0;
            $parsedChangeDetails = customerDailyReportParseChangeDetails($connect, $finance_connect, $auditRow);

            if ($auditAction === (int) get_allowed_audit_actions('delete')) {
                $deleteAuditDetails = $parseDeleteAuditDetails($auditRow, $platformKey);
                $detailRows[] = $buildDetailRow(
                    $auditRow,
                    $platformKey,
                    $recordId,
                    $customerMeta,
                    $userMeta,
                    isset($deleteAuditDetails['change_details']) ? $deleteAuditDetails['change_details'] : array(),
                    'Delete',
                    'record',
                    isset($deleteAuditDetails['deleted_name']) ? (string) $deleteAuditDetails['deleted_name'] : ''
                );
                continue;
            }

            if ($auditAction === (int) get_allowed_audit_actions('add')) {
                $primaryFieldLabel = customerDailyReportGetPrimaryCustomerFieldLabel($platformKey);
                $selectedAddDetail = null;

                foreach ($parsedChangeDetails as $changeDetail) {
                    if (customerDailyReportNormalizeFieldKey($changeDetail['field_name'] ?? '') === customerDailyReportNormalizeFieldKey($primaryFieldLabel)) {
                        $selectedAddDetail = $changeDetail;
                        break;
                    }
                }

                if ($selectedAddDetail === null) {
                    $selectedAddDetail = array(
                        'field_name' => $primaryFieldLabel,
                        'old_value' => 'Empty Value',
                        'new_value' => isset($customerMeta['display_name']) ? (string) $customerMeta['display_name'] : '',
                    );
                }

                $detailRows[] = $buildDetailRow(
                    $auditRow,
                    $platformKey,
                    $recordId,
                    $customerMeta,
                    $userMeta,
                    array($selectedAddDetail),
                    'Add',
                    'record',
                    ''
                );
                continue;
            }

            $detailRows[] = $buildDetailRow(
                $auditRow,
                $platformKey,
                $recordId,
                $customerMeta,
                $userMeta,
                $parsedChangeDetails,
                'Edit',
                'record',
                ''
            );
        }
    }
}

$buildSummaryRows = function ($rows) use ($platformConfigs) {
    $summaryMap = array();

    foreach ($rows as $row) {
        $userKey = (string) ($row['user_id'] ?? 0);
        if (!isset($summaryMap[$userKey])) {
            $summaryMap[$userKey] = array(
                'user_name' => isset($row['user_name']) ? (string) $row['user_name'] : 'Unknown User',
                'total_records' => 0,
                'total_tag_actions' => 0,
                'breakdown' => array(),
            );

            foreach ($platformConfigs as $platformKey => $platformConfig) {
                $summaryMap[$userKey]['breakdown'][$platformKey] = 0;
            }
        }

        if (isset($row['activity_kind']) && (string) $row['activity_kind'] === 'tag') {
            $summaryMap[$userKey]['total_tag_actions']++;
        } else {
            $summaryMap[$userKey]['total_records']++;
        }
        $platformKey = isset($row['platform_key']) ? (string) $row['platform_key'] : '';
        if ($platformKey !== '' && isset($summaryMap[$userKey]['breakdown'][$platformKey])) {
            $summaryMap[$userKey]['breakdown'][$platformKey]++;
        }
    }

    $summaryRows = array_values($summaryMap);
    usort($summaryRows, function ($left, $right) {
        $countCompare = (($right['total_records'] ?? 0) + ($right['total_tag_actions'] ?? 0)) <=> (($left['total_records'] ?? 0) + ($left['total_tag_actions'] ?? 0));
        if ($countCompare !== 0) {
            return $countCompare;
        }

        $recordCompare = ($right['total_records'] ?? 0) <=> ($left['total_records'] ?? 0);
        if ($recordCompare !== 0) {
            return $recordCompare;
        }

        return strcasecmp((string) ($left['user_name'] ?? ''), (string) ($right['user_name'] ?? ''));
    });

    return $summaryRows;
};

$summarySourceRowsByPlatform = array('all' => $detailRows);
foreach ($platformConfigs as $platformKey => $platformConfig) {
    $summarySourceRowsByPlatform[$platformKey] = array_values(array_filter($detailRows, function ($row) use ($platformKey) {
        return isset($row['platform_key']) && (string) $row['platform_key'] === $platformKey;
    }));
}

$summaryRowsByPlatform = array();
foreach ($summarySourceRowsByPlatform as $platformKey => $rows) {
    $summaryRowsByPlatform[$platformKey] = $buildSummaryRows($rows);
}

$detailTableRows = array();
foreach ($detailRows as $detailRow) {
    $changeDetails = isset($detailRow['change_details']) && is_array($detailRow['change_details']) ? $detailRow['change_details'] : array();
    if (empty($changeDetails)) {
        $detailTableRows[] = $detailRow;
        continue;
    }

    foreach ($changeDetails as $changeDetail) {
        $detailTableRows[] = array(
            'audit_id' => isset($detailRow['audit_id']) ? (int) $detailRow['audit_id'] : 0,
            'audit_action' => isset($detailRow['audit_action']) ? (int) $detailRow['audit_action'] : 0,
            'edit_datetime' => isset($detailRow['edit_datetime']) ? (string) $detailRow['edit_datetime'] : '',
            'edit_date' => isset($detailRow['edit_date']) ? (string) $detailRow['edit_date'] : '',
            'edit_time' => isset($detailRow['edit_time']) ? (string) $detailRow['edit_time'] : '',
            'user_name' => isset($detailRow['user_name']) ? (string) $detailRow['user_name'] : 'Unknown User',
            'user_group_badge_html' => isset($detailRow['user_group_badge_html']) ? (string) $detailRow['user_group_badge_html'] : '',
            'platform_key' => isset($detailRow['platform_key']) ? (string) $detailRow['platform_key'] : '',
            'platform_label' => isset($detailRow['platform_label']) ? (string) $detailRow['platform_label'] : '',
            'record_id' => isset($detailRow['record_id']) ? (int) $detailRow['record_id'] : 0,
            'customer_name' => isset($detailRow['customer_name']) ? (string) $detailRow['customer_name'] : '',
            'record_url' => isset($detailRow['record_url']) ? (string) $detailRow['record_url'] : '',
            'field_name' => isset($changeDetail['field_name']) ? (string) $changeDetail['field_name'] : '',
            'action_type' => isset($detailRow['action_type']) ? (string) $detailRow['action_type'] : 'Edit',
            'old_value' => isset($changeDetail['old_value']) ? (string) $changeDetail['old_value'] : '',
            'new_value' => isset($changeDetail['new_value']) ? (string) $changeDetail['new_value'] : '',
        );
    }
}

$detailRowsByPlatform = array('all' => $detailTableRows);
foreach ($platformConfigs as $platformKey => $platformConfig) {
    $detailRowsByPlatform[$platformKey] = array_values(array_filter($detailTableRows, function ($row) use ($platformKey) {
        return isset($row['platform_key']) && (string) $row['platform_key'] === $platformKey;
    }));
}

$h = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .customer-daily-report-stack {
            display: flex;
            flex-direction: column;
            gap: 36px;
        }

        .customer-daily-report-card {
            background: #ffffff;
            border: 1px solid #e7edf4;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
            padding: 18px 20px;
        }

        .customer-daily-report-panel > .customer-daily-report-card + .customer-daily-report-card {
            margin-top: 30px;
        }

        .customer-daily-report-subtitle,
        .customer-daily-report-empty {
            color: #667085;
        }

        .customer-daily-report-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .customer-daily-report-reset-btn {
            border: 1px solid #d0d5dd;
            background: #ffffff;
            border-radius: 20px;
            min-width: 160px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .customer-daily-report-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .customer-daily-report-tab-btn {
            border: 1px solid #d0d5dd;
            border-radius: 999px;
            background: #ffffff;
            color: #344054;
            padding: 8px 16px;
            font-weight: 600;
            transition: all .2s ease;
        }

        .customer-daily-report-tab-btn.is-active {
            background: #2f5be6;
            border-color: #2f5be6;
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(47, 91, 230, .2);
        }

        .customer-daily-report-tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            margin-left: 8px;
            padding: 0 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .18);
            font-size: 12px;
        }

        .customer-daily-report-tab-btn:not(.is-active) .customer-daily-report-tab-count {
            background: #eef2ff;
            color: #2f5be6;
        }

        .customer-daily-report-panel {
            display: none;
        }

        .customer-daily-report-panel.is-active {
            display: block;
        }

        .customer-daily-report-section-title {
            margin-bottom: 14px;
        }

        .customer-daily-report-table {
            margin-bottom: 0;
        }

        .customer-daily-report-table th,
        .customer-daily-report-table td {
            vertical-align: top;
        }

        .customer-daily-report-breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .customer-daily-report-breakdown-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eef4ff;
            border: 1px solid #d9e6ff;
            color: #2f5be6;
            font-size: 12px;
            font-weight: 600;
        }

        .customer-daily-report-table-wrap {
            width: 100%;
        }

        .customer-daily-report-updated-by {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 180px;
        }

        .customer-daily-report-updated-by-name {
            color: #101828;
            font-weight: 500;
        }

        .customer-daily-report-change-cell {
            min-width: 260px;
        }

        .customer-daily-report-change-old,
        .customer-daily-report-change-new,
        .customer-daily-report-field-cell {
            display: block;
            word-break: normal;
            overflow-wrap: normal;
            white-space: normal;
        }

        .customer-daily-report-change-old {
            color: #c62828;
            text-decoration: line-through;
        }

        .customer-daily-report-change-new {
            color: #0a8f4d;
        }

        .customer-daily-report-change-old + .customer-daily-report-change-new {
            margin-top: 4px;
        }

        .customer-daily-report-action-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: inherit;
            text-decoration: none;
            font-weight: 600;
        }

        .customer-daily-report-action-link:hover {
            color: #2f5be6;
            text-decoration: none;
        }

        .customer-daily-report-id-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            padding: 4px 10px;
            border-radius: 8px;
            background: #eef4ff;
            border: 1px solid #d9e6ff;
            color: #2f5be6;
            font-weight: 600;
        }

        .customer-daily-report-empty-value {
            color: #98a2b3;
        }

        @media (max-width: 767px) {
            .customer-daily-report-card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    

    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11 py-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div>
                        <h2 class="mb-1"><?= $h($displayPageTitle) ?></h2>
                        <div class="customer-daily-report-subtitle">Daily customer create, edit, delete, and tag activity grouped by user across all supported customer platforms.</div>
                    </div>
                </div>

                <div class="customer-daily-report-stack">
                    <div class="customer-daily-report-card">
                        <form method="get" id="customerDailyReportFilterForm">
                            <input type="hidden" name="platform" id="customerDailyReportPlatformInput" value="<?= $h($activePlatform) ?>">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-2">
                                    <input class="form-control" type="date" name="date" id="customerDailyReportDate" value="<?= $h($selectedDate) ?>">
                                </div>
                                <div class="col-12 col-md-2">
                                    <select class="form-select" name="month" id="customerDailyReportMonth">
                                        <option value="">Select Month</option>
                                        <?php for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) { ?>
                                            <?php
                                            $monthValue = str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT);
                                            $monthLabel = date('F', mktime(0, 0, 0, $monthNumber, 1));
                                            ?>
                                            <option value="<?= $h($monthValue) ?>" <?= $monthValue === $selectedMonth ? 'selected' : '' ?>><?= $h($monthLabel) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-2">
                                    <select class="form-select" name="year" id="customerDailyReportYear">
                                        <option value="">Select Year</option>
                                        <?php for ($yearValue = (int) $currentYear; $yearValue >= ((int) $currentYear - 5); $yearValue--) { ?>
                                            <option value="<?= $h($yearValue) ?>" <?= (string) $yearValue === $selectedYear ? 'selected' : '' ?>><?= $h($yearValue) ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-2">
                                    <a class="btn btn-outline-secondary w-100 customer-daily-report-reset-btn" href="<?= $h($_SERVER['PHP_SELF']) ?>">Reset Filters</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="customer-daily-report-card">
                        <div class="customer-daily-report-tabs" id="customerDailyReportTabs">
                            <?php foreach ($platformTabs as $platformKey => $platformLabel) { ?>
                                <?php $tabRows = isset($detailRowsByPlatform[$platformKey]) ? $detailRowsByPlatform[$platformKey] : array(); ?>
                                <button
                                    type="button"
                                    class="customer-daily-report-tab-btn <?= $platformKey === $activePlatform ? 'is-active' : '' ?>"
                                    data-platform="<?= $h($platformKey) ?>">
                                    <?= $h($platformLabel) ?>
                                    <span class="customer-daily-report-tab-count"><?= $h(count($tabRows)) ?></span>
                                </button>
                            <?php } ?>
                        </div>
                    </div>

                    <?php if ($reportError !== '') { ?>
                        <div class="alert alert-danger mb-0"><?= $h($reportError) ?></div>
                    <?php } ?>

                    <?php foreach ($platformTabs as $platformKey => $platformLabel) { ?>
                        <?php
                        $panelSummaryRows = isset($summaryRowsByPlatform[$platformKey]) ? $summaryRowsByPlatform[$platformKey] : array();
                        $panelDetailRows = isset($detailRowsByPlatform[$platformKey]) ? $detailRowsByPlatform[$platformKey] : array();
                        ?>
                        <div class="customer-daily-report-panel <?= $platformKey === $activePlatform ? 'is-active' : '' ?>" data-platform-panel="<?= $h($platformKey) ?>">
                            <div class="customer-daily-report-card">
                                <h5 class="customer-daily-report-section-title">Summary by User</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped customer-daily-report-table">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Total Created / Edited / Deleted Customer Records</th>
                                                <th>Total Assigned / Changed / Removed Customer Tag</th>
                                                <th>Breakdown by Platform</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($panelSummaryRows)) { ?>
                                                <?php foreach ($panelSummaryRows as $summaryRow) { ?>
                                                    <tr>
                                                        <td><?= $h($summaryRow['user_name'] ?? 'Unknown User') ?></td>
                                                        <td><span class="customer-daily-report-id-badge"><?= $h($summaryRow['total_records'] ?? 0) ?></span></td>
                                                        <td><span class="customer-daily-report-id-badge"><?= $h($summaryRow['total_tag_actions'] ?? 0) ?></span></td>
                                                        <td>
                                                            <div class="customer-daily-report-breakdown">
                                                                <?php foreach (($summaryRow['breakdown'] ?? array()) as $breakdownPlatformKey => $breakdownCount) { ?>
                                                                    <?php if ((int) $breakdownCount <= 0) { continue; } ?>
                                                                    <span class="customer-daily-report-breakdown-badge">
                                                                        <?= $h($platformTabs[$breakdownPlatformKey] ?? ucfirst((string) $breakdownPlatformKey)) ?>: <?= $h($breakdownCount) ?>
                                                                    </span>
                                                                <?php } ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="customer-daily-report-card">
                                <h5 class="customer-daily-report-section-title">Customer Record Details</h5>
                                <div class="customer-daily-report-table-wrap">
                                    <table class="table table-striped customer-daily-report-table" id="customerDailyReportTable_<?= $h($platformKey) ?>">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Date Time</th>
                                                <th>Platform</th>
                                                <th>Customer Record ID</th>
                                                <th>Customer Name / Username</th>
                                                <th>Field</th>
                                                <th>Action Type</th>
                                                <th>Created / Updated By</th>
                                                <th>Change</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($panelDetailRows)) { ?>
                                                <?php foreach ($panelDetailRows as $detailRow) { ?>
                                                    <?php
                                                    $oldValue = isset($detailRow['old_value']) ? trim((string) $detailRow['old_value']) : '';
                                                    $newValue = isset($detailRow['new_value']) ? trim((string) $detailRow['new_value']) : '';
                                                    $hasOldValue = $oldValue !== '' && strcasecmp($oldValue, 'Empty Value') !== 0;
                                                    $hasNewValue = $newValue !== '' && strcasecmp($newValue, 'Empty Value') !== 0;
                                                    $actionTypeLabel = isset($detailRow['action_type']) ? (string) $detailRow['action_type'] : 'Edit';
                                                    ?>
                                                    <tr>
                                                        <td></td>
                                                        <td><?= $h($detailRow['edit_datetime'] ?? '') ?></td>
                                                        <td><?= $h($detailRow['platform_label'] ?? '') ?></td>
                                                        <td>
                                                            <?php if (!empty($detailRow['record_url'])) { ?>
                                                                <a class="customer-daily-report-action-link" href="<?= $h($detailRow['record_url']) ?>"><?= $h($detailRow['record_id'] ?? '') ?></a>
                                                            <?php } else { ?>
                                                                <?= $h($detailRow['record_id'] ?? '') ?>
                                                            <?php } ?>
                                                        </td>
                                                        <td><?= $h($detailRow['customer_name'] ?? '') ?></td>
                                                        <td>
                                                            <span class="customer-daily-report-field-cell"><?= $h($detailRow['field_name'] ?? '') ?></span>
                                                        </td>
                                                        <td><?= $h($actionTypeLabel) ?></td>
                                                        <td>
                                                            <div class="customer-daily-report-updated-by">
                                                                <span class="customer-daily-report-updated-by-name"><?= $h($detailRow['user_name'] ?? 'Unknown User') ?></span>
                                                                <?php if (!empty($detailRow['user_group_badge_html'])) { ?>
                                                                    <?= $detailRow['user_group_badge_html'] ?>
                                                                <?php } ?>
                                                            </div>
                                                        </td>
                                                        <td class="customer-daily-report-change-cell">
                                                            <?php if ($hasOldValue) { ?>
                                                                <span class="customer-daily-report-change-old"><?= $h($oldValue) ?></span>
                                                            <?php } ?>
                                                            <?php if ($hasNewValue) { ?>
                                                                <span class="customer-daily-report-change-new"><?= $h($newValue) ?></span>
                                                            <?php } ?>
                                                            <?php if (!$hasOldValue && !$hasNewValue) { ?>
                                                                <span class="customer-daily-report-empty-value"><?= $h($newValue !== '' ? $newValue : 'Empty Value') ?></span>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var reportTables = {};
            var platformInput = document.getElementById("customerDailyReportPlatformInput");
            var filterForm = document.getElementById("customerDailyReportFilterForm");
            var dateInput = document.getElementById("customerDailyReportDate");
            var monthSelect = document.getElementById("customerDailyReportMonth");
            var yearSelect = document.getElementById("customerDailyReportYear");
            var tabButtons = document.querySelectorAll(".customer-daily-report-tab-btn");
            var tabPanels = document.querySelectorAll(".customer-daily-report-panel");

            document.querySelectorAll(".customer-daily-report-table[id]").forEach(function (tableElement) {
                reportTables[tableElement.id] = createSortingTable(tableElement.id, {
                    order: [[1, "desc"]],
                    columnDefs: [
                        {
                            orderable: false,
                            searchable: false,
                            targets: [0],
                        },
                    ],
                });
                datatableAlignment(tableElement.id);

                reportTables[tableElement.id].on("draw", function () {
                    var pageInfo = reportTables[tableElement.id].page.info();
                    reportTables[tableElement.id].column(0, { page: "current" }).nodes().each(function (cell, index) {
                        cell.innerHTML = pageInfo.start + index + 1;
                    });
                });
                reportTables[tableElement.id].draw(false);
            });

            function setActivePlatform(platformKey) {
                if (platformInput) {
                    platformInput.value = platformKey;
                }

                tabButtons.forEach(function (button) {
                    button.classList.toggle("is-active", button.getAttribute("data-platform") === platformKey);
                });

                tabPanels.forEach(function (panel) {
                    panel.classList.toggle("is-active", panel.getAttribute("data-platform-panel") === platformKey);
                });

                var activeTable = document.getElementById("customerDailyReportTable_" + platformKey);
                if (activeTable && reportTables[activeTable.id] && reportTables[activeTable.id].columns) {
                    reportTables[activeTable.id].columns.adjust();
                }
            }

            tabButtons.forEach(function (button) {
                button.addEventListener("click", function () {
                    setActivePlatform(button.getAttribute("data-platform") || "all");
                });
            });

            [dateInput, monthSelect, yearSelect].forEach(function (filterSelect) {
                if (!filterSelect || !filterForm) {
                    return;
                }

                filterSelect.addEventListener("change", function () {
                    filterForm.submit();
                });
            });

            setActivePlatform("<?= $h($activePlatform) ?>");
        })();

        checkCurrentPage("<?= $h($displayPageTitle) ?>", " ");
        dropdownMenuDispFix();
        setButtonColor();
    </script>
</body>
</html>
