<?php
$currentPagePin = 161;
$pageTitle = 'Daily Follow Up Report';
$displayPageTitle = 'Daily Follow Up Report';
$disablePinGroupPageTitleSync = true;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/user_record_log.php';

$pinGroupPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($pinGroupPageTitle !== '') {
    $pageTitle = $pinGroupPageTitle;
    $displayPageTitle = $pinGroupPageTitle;
}

$reportAccess = checkPinByGroupId($connect, $currentPagePin);
$canViewPage = isActionAllowed('View', $reportAccess);
if (!$canViewPage) {
    echo '<script>alert("You do not have permission to view Daily Follow Up Report."); location.replace("dashboard.php");</script>';
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

if (!function_exists('dailyFollowUpReportGetPlatformConfigs')) {
    function dailyFollowUpReportGetPlatformConfigs()
    {
        $baseConfigs = function_exists('customerDailyReportGetPlatformConfigs')
            ? customerDailyReportGetPlatformConfigs()
            : array();

        $extraConfigs = array(
            'shopee' => array(
                'customer_column' => 'shopee_cust_id',
                'brand_field' => 'brand',
            ),
            'lazada' => array(
                'customer_column' => 'lazada_cust_id',
                'brand_field' => 'brand',
            ),
            'facebook' => array(
                'customer_column' => 'facebook_cust_id',
                'brand_field' => 'brand',
            ),
            'website' => array(
                'customer_column' => 'website_cust_id',
                'brand_field' => 'brand',
            ),
            'customer_info' => array(
                'label' => 'Whatsapp Customer',
                'customer_column' => 'cust_id',
                'brand_field' => '',
            ),
        );

        $platformConfigs = array();
        foreach ($extraConfigs as $platformKey => $extraConfig) {
            $platformConfigs[$platformKey] = array_merge(
                isset($baseConfigs[$platformKey]) && is_array($baseConfigs[$platformKey]) ? $baseConfigs[$platformKey] : array(),
                $extraConfig
            );
        }

        return $platformConfigs;
    }
}

if (!function_exists('dailyFollowUpReportGetPlatformLabel')) {
    function dailyFollowUpReportGetPlatformLabel($platformKey, $platformConfigs = null)
    {
        if (!is_array($platformConfigs)) {
            $platformConfigs = dailyFollowUpReportGetPlatformConfigs();
        }

        if (isset($platformConfigs[$platformKey]['label']) && trim((string) $platformConfigs[$platformKey]['label']) !== '') {
            return trim((string) $platformConfigs[$platformKey]['label']);
        }

        return ucfirst(trim((string) $platformKey));
    }
}

if (!function_exists('dailyFollowUpReportDetectPlatformKeyFromLogRow')) {
    function dailyFollowUpReportDetectPlatformKeyFromLogRow($row, $platformConfigs)
    {
        if (!is_array($row) || !is_array($platformConfigs)) {
            return '';
        }

        foreach ($platformConfigs as $platformKey => $platformConfig) {
            $customerColumn = isset($platformConfig['customer_column']) ? (string) $platformConfig['customer_column'] : '';
            if ($customerColumn === '') {
                continue;
            }

            $customerId = isset($row[$customerColumn]) ? (int) $row[$customerColumn] : 0;
            if ($customerId > 0) {
                return $platformKey;
            }
        }

        return '';
    }
}

if (!function_exists('dailyFollowUpReportGetCustomerIdFromLogRow')) {
    function dailyFollowUpReportGetCustomerIdFromLogRow($row, $platformKey, $platformConfigs)
    {
        if (!is_array($row) || !isset($platformConfigs[$platformKey])) {
            return 0;
        }

        $customerColumn = isset($platformConfigs[$platformKey]['customer_column']) ? (string) $platformConfigs[$platformKey]['customer_column'] : '';
        if ($customerColumn === '') {
            return 0;
        }

        return isset($row[$customerColumn]) ? (int) $row[$customerColumn] : 0;
    }
}

if (!function_exists('dailyFollowUpReportBuildPeriodLabel')) {
    function dailyFollowUpReportBuildPeriodLabel($selectedDate, $selectedMonth, $selectedYear, $todayYmd)
    {
        $selectedDate = trim((string) $selectedDate);
        $selectedMonth = trim((string) $selectedMonth);
        $selectedYear = trim((string) $selectedYear);
        $todayYmd = trim((string) $todayYmd);

        if ($selectedDate !== '') {
            if ($selectedDate === $todayYmd) {
                return 'Today';
            }

            return date('Y-m-d', strtotime($selectedDate));
        }

        if ($selectedMonth !== '' && $selectedYear !== '') {
            return date('F Y', strtotime($selectedYear . '-' . $selectedMonth . '-01'));
        }

        if ($selectedMonth !== '') {
            return date('F', mktime(0, 0, 0, (int) $selectedMonth, 1));
        }

        if ($selectedYear !== '') {
            return $selectedYear;
        }

        return 'Selected Period';
    }
}

if (!function_exists('dailyFollowUpReportBuildOrdinalLabel')) {
    function dailyFollowUpReportBuildOrdinalLabel($value)
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
            return $value;
        }

        $number = (int) $value;
        $lastTwoDigits = $number % 100;
        if ($lastTwoDigits >= 11 && $lastTwoDigits <= 13) {
            $suffix = 'th';
        } else {
            switch ($number % 10) {
                case 1:
                    $suffix = 'st';
                    break;
                case 2:
                    $suffix = 'nd';
                    break;
                case 3:
                    $suffix = 'rd';
                    break;
                default:
                    $suffix = 'th';
                    break;
            }
        }

        return $number . $suffix;
    }
}

if (!function_exists('dailyFollowUpReportFormatDayValue')) {
    function dailyFollowUpReportFormatDayValue($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return 'Day ' . $value;
        }

        return $value;
    }
}

if (!function_exists('dailyFollowUpReportFormatTimesValue')) {
    function dailyFollowUpReportFormatTimesValue($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return dailyFollowUpReportBuildOrdinalLabel($value) . ' times';
        }

        return $value;
    }
}

if (!function_exists('dailyFollowUpReportExtractFailHighlights')) {
    function dailyFollowUpReportExtractFailHighlights($summary, $contentPlain)
    {
        $candidateTexts = array(trim((string) $summary), trim((string) $contentPlain));

        foreach ($candidateTexts as $candidateText) {
            if ($candidateText !== '' && stripos($candidateText, 'fail') !== false) {
                return array('Fail to FLW UP due to Cannot restart conversation after 7 days.');
            }
        }

        return array();
    }
}

if (!function_exists('dailyFollowUpReportExtractContentDisplayText')) {
    function dailyFollowUpReportExtractContentDisplayText($contentPlain)
    {
        $contentPlain = trim((string) $contentPlain);
        if ($contentPlain === '') {
            return '';
        }

        $marker = 'Message Shortcut Content:';
        $markerPos = stripos($contentPlain, $marker);
        if ($markerPos === false) {
            return $contentPlain;
        }

        $displayText = trim(substr($contentPlain, $markerPos + strlen($marker)));
        return $displayText !== '' ? $displayText : $contentPlain;
    }
}

if (!function_exists('dailyFollowUpReportLoadCustomerRowsMap')) {
    function dailyFollowUpReportLoadCustomerRowsMap($connect, $financeConnect, $platformConfigs, $customerIdsByPlatform)
    {
        $customerRowsByPlatform = array();
        $brandIds = array();

        foreach ((array) $customerIdsByPlatform as $platformKey => $customerIdMap) {
            if (!isset($platformConfigs[$platformKey]) || !is_array($platformConfigs[$platformKey])) {
                continue;
            }

            $customerIds = array();
            foreach (array_keys((array) $customerIdMap) as $customerId) {
                $customerId = (int) $customerId;
                if ($customerId > 0) {
                    $customerIds[$customerId] = $customerId;
                }
            }

            if (empty($customerIds)) {
                continue;
            }

            $platformConfig = $platformConfigs[$platformKey];
            $dbConnect = function_exists('customerDailyReportResolveDbConnect')
                ? customerDailyReportResolveDbConnect($connect, $financeConnect, isset($platformConfig['db']) ? $platformConfig['db'] : 'cms')
                : $connect;
            $tableName = isset($platformConfig['table']) ? trim((string) $platformConfig['table']) : '';
            if (!($dbConnect instanceof mysqli) || $tableName === '' || preg_match('/^[A-Za-z0-9_]+$/', $tableName) !== 1) {
                continue;
            }

            $selectFields = array('id');
            foreach ((array) (isset($platformConfig['display_fields']) ? $platformConfig['display_fields'] : array()) as $fieldName) {
                $fieldName = trim((string) $fieldName);
                if ($fieldName !== '') {
                    $selectFields[] = $fieldName;
                }
            }

            $brandField = isset($platformConfig['brand_field']) ? trim((string) $platformConfig['brand_field']) : '';
            if ($brandField !== '') {
                $selectFields[] = $brandField;
            }

            $safeSelectFields = array();
            foreach (array_values(array_unique($selectFields)) as $fieldName) {
                if (preg_match('/^[A-Za-z0-9_]+$/', $fieldName) === 1) {
                    $safeSelectFields[] = '`' . $fieldName . '`';
                }
            }

            if (empty($safeSelectFields)) {
                continue;
            }

            $sql = "SELECT " . implode(', ', $safeSelectFields) . "
                FROM `" . $tableName . "`
                WHERE `id` IN (" . implode(',', $customerIds) . ")";
            $result = mysqli_query($dbConnect, $sql);
            if (!$result) {
                continue;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $customerId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($customerId <= 0) {
                    continue;
                }

                if (!isset($customerRowsByPlatform[$platformKey])) {
                    $customerRowsByPlatform[$platformKey] = array();
                }
                $customerRowsByPlatform[$platformKey][$customerId] = $row;

                if ($brandField !== '') {
                    $brandId = isset($row[$brandField]) ? (int) $row[$brandField] : 0;
                    if ($brandId > 0) {
                        $brandIds[$brandId] = $brandId;
                    }
                }
            }
        }

        $brandNameMap = array();
        if (!empty($brandIds)) {
            $brandSql = "SELECT `id`, `name` FROM `" . BRAND . "` WHERE `id` IN (" . implode(',', $brandIds) . ")";
            $brandResult = mysqli_query($connect, $brandSql);
            if ($brandResult) {
                while ($brandRow = mysqli_fetch_assoc($brandResult)) {
                    $brandId = isset($brandRow['id']) ? (int) $brandRow['id'] : 0;
                    if ($brandId > 0) {
                        $brandNameMap[$brandId] = isset($brandRow['name']) ? trim((string) $brandRow['name']) : '';
                    }
                }
            }
        }

        return array(
            'customer_rows' => $customerRowsByPlatform,
            'brand_names' => $brandNameMap,
        );
    }
}

$todayYmd = date('Y-m-d');
$currentYear = date('Y');
$hasExplicitDateFilter = isset($_GET['date']) || isset($_GET['month']) || isset($_GET['year']);
$selectedDate = trim((string) input('date'));
$selectedMonth = trim((string) input('month'));
$selectedYear = trim((string) input('year'));

$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) === 1 ? $selectedDate : '';
$selectedMonth = preg_match('/^(0[1-9]|1[0-2])$/', $selectedMonth) === 1 ? $selectedMonth : '';
$selectedYear = preg_match('/^\d{4}$/', $selectedYear) === 1 ? $selectedYear : '';

if (!$hasExplicitDateFilter && $selectedDate === '' && $selectedMonth === '' && $selectedYear === '') {
    $selectedDate = $todayYmd;
}

$platformConfigs = dailyFollowUpReportGetPlatformConfigs();
$periodLabel = dailyFollowUpReportBuildPeriodLabel($selectedDate, $selectedMonth, $selectedYear, $todayYmd);
$reportRows = array();
$customerIdsByPlatform = array();
$userIds = array();
$reportError = '';

$whereConditions = array(
    "`status` = 'A'",
    "(IFNULL(`content`, '') <> '' OR IFNULL(`summary`, '') <> '' OR IFNULL(`attachment`, '') <> '')",
);

if ($selectedDate !== '') {
    $whereConditions[] = "DATE(`created_at`) = '" . mysqli_real_escape_string($connect, $selectedDate) . "'";
} else {
    if ($selectedMonth !== '') {
        $whereConditions[] = "MONTH(`created_at`) = '" . mysqli_real_escape_string($connect, $selectedMonth) . "'";
    }
    if ($selectedYear !== '') {
        $whereConditions[] = "YEAR(`created_at`) = '" . mysqli_real_escape_string($connect, $selectedYear) . "'";
    }
}

$reportSql = "SELECT *
    FROM `" . USER_RECORD_LOG . "`
    WHERE " . implode(' AND ', $whereConditions) . "
    ORDER BY `created_at` DESC, `id` DESC";
$reportResult = mysqli_query($connect, $reportSql);
if ($reportResult instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($reportResult)) {
        $platformKey = dailyFollowUpReportDetectPlatformKeyFromLogRow($row, $platformConfigs);
        $customerId = $platformKey !== ''
            ? dailyFollowUpReportGetCustomerIdFromLogRow($row, $platformKey, $platformConfigs)
            : 0;

        if ($platformKey === '' || $customerId <= 0) {
            continue;
        }

        $reportRows[] = $row;

        if (!isset($customerIdsByPlatform[$platformKey])) {
            $customerIdsByPlatform[$platformKey] = array();
        }
        $customerIdsByPlatform[$platformKey][$customerId] = $customerId;

        $createdByUserId = isset($row['created_by']) ? (int) $row['created_by'] : 0;
        if ($createdByUserId > 0) {
            $userIds[$createdByUserId] = $createdByUserId;
        }

        $updatedByUserId = isset($row['updated_by']) ? (int) $row['updated_by'] : 0;
        if ($updatedByUserId > 0) {
            $userIds[$updatedByUserId] = $updatedByUserId;
        }
    }
} else {
    $reportError = 'Unable to load Daily Follow Up Report right now.';
}

$userMetaMap = function_exists('customerDailyReportLoadUserMetaMap')
    ? customerDailyReportLoadUserMetaMap($connect, array_values($userIds))
    : array();

$customerLoadResult = dailyFollowUpReportLoadCustomerRowsMap($connect, $finance_connect, $platformConfigs, $customerIdsByPlatform);
$customerRowsByPlatform = isset($customerLoadResult['customer_rows']) ? $customerLoadResult['customer_rows'] : array();
$brandNameMap = isset($customerLoadResult['brand_names']) ? $customerLoadResult['brand_names'] : array();

$detailRows = array();
$uniqueCustomerMap = array();
foreach ($reportRows as $row) {
    $platformKey = dailyFollowUpReportDetectPlatformKeyFromLogRow($row, $platformConfigs);
    $customerId = dailyFollowUpReportGetCustomerIdFromLogRow($row, $platformKey, $platformConfigs);
    if ($platformKey === '' || $customerId <= 0) {
        continue;
    }

    $platformConfig = isset($platformConfigs[$platformKey]) ? $platformConfigs[$platformKey] : array();
    $platformLabel = dailyFollowUpReportGetPlatformLabel($platformKey, $platformConfigs);
    $customerRow = isset($customerRowsByPlatform[$platformKey][$customerId]) ? $customerRowsByPlatform[$platformKey][$customerId] : array();
    $customerDisplayName = function_exists('customerDailyReportGetDisplayNameFromRow')
        ? customerDailyReportGetDisplayNameFromRow($customerRow, isset($platformConfig['display_fields']) ? (array) $platformConfig['display_fields'] : array())
        : '';
    if ($customerDisplayName === '') {
        $customerDisplayName = 'Record #' . $customerId;
    }

    $customerUrl = '';
    if (!empty($platformConfig['record_url'])) {
        $customerUrl = rtrim((string) $SITEURL, '/') . (string) $platformConfig['record_url'] . '?id=' . $customerId;
    }

    $brandField = isset($platformConfig['brand_field']) ? trim((string) $platformConfig['brand_field']) : '';
    $brandId = ($brandField !== '' && isset($customerRow[$brandField])) ? (int) $customerRow[$brandField] : 0;
    $brandName = $brandId > 0 && isset($brandNameMap[$brandId]) && trim((string) $brandNameMap[$brandId]) !== ''
        ? trim((string) $brandNameMap[$brandId])
        : '';

    $createdByUserId = isset($row['created_by']) ? (int) $row['created_by'] : 0;
    $createdByMeta = $createdByUserId > 0 && isset($userMetaMap[$createdByUserId]) ? $userMetaMap[$createdByUserId] : array();
    $followUpByName = isset($createdByMeta['display_name']) && trim((string) $createdByMeta['display_name']) !== ''
        ? trim((string) $createdByMeta['display_name'])
        : ($createdByUserId > 0 ? ('User #' . $createdByUserId) : 'Unknown User');

    $content = isset($row['content']) ? (string) $row['content'] : '';
    $summary = isset($row['summary']) ? trim((string) $row['summary']) : '';
    $contentPlain = function_exists('urlGetUserRecordLogContentPlainText')
        ? urlGetUserRecordLogContentPlainText($content)
        : trim(strip_tags($content));
    $contentDisplayText = dailyFollowUpReportExtractContentDisplayText($contentPlain);
    $attachments = function_exists('urlDecodeUserRecordLogAttachmentList')
        ? urlDecodeUserRecordLogAttachmentList(isset($row['attachment']) ? $row['attachment'] : '')
        : array();
    $failHighlights = dailyFollowUpReportExtractFailHighlights($summary, $contentPlain);

    $detailRows[] = array(
        'id' => isset($row['id']) ? (int) $row['id'] : 0,
        'created_at' => isset($row['created_at']) ? trim((string) $row['created_at']) : '',
        'platform_key' => $platformKey,
        'platform_label' => $platformLabel,
        'customer_id' => $customerId,
        'customer_name' => $customerDisplayName,
        'customer_url' => $customerUrl,
        'brand_name' => $brandName,
        'follow_up_day' => isset($row['follow_up_day']) ? trim((string) $row['follow_up_day']) : '',
        'follow_up_times' => isset($row['follow_up_times']) ? trim((string) $row['follow_up_times']) : '',
        'follow_up_day_display' => dailyFollowUpReportFormatDayValue(isset($row['follow_up_day']) ? $row['follow_up_day'] : ''),
        'follow_up_times_display' => dailyFollowUpReportFormatTimesValue(isset($row['follow_up_times']) ? $row['follow_up_times'] : ''),
        'follow_up_by' => $followUpByName,
        'follow_up_by_badge_html' => isset($createdByMeta['group_badge_html']) ? (string) $createdByMeta['group_badge_html'] : '',
        'summary' => $summary,
        'content' => $content,
        'content_plain' => $contentPlain,
        'content_display' => $contentDisplayText,
        'attachment_count' => count($attachments),
        'fail_highlights' => $failHighlights,
    );

    $uniqueCustomerMap[$platformKey . ':' . $customerId] = true;
}

$totalActivityCount = count($detailRows);
$updatedCustomerCount = count($uniqueCustomerMap);
$h = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $h($displayPageTitle) ?></title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .daily-follow-up-report-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 20px;
        }

        .daily-follow-up-report-summary {
            font-size: 1.05rem;
            font-weight: 600;
            color: #1f2937;
        }

        .daily-follow-up-report-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            align-items: end;
        }

        .daily-follow-up-report-followup-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 150px;
        }

        .daily-follow-up-report-mini-label {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .daily-follow-up-report-activity-cell {
            min-width: 360px;
        }

        .daily-follow-up-report-activity-block + .daily-follow-up-report-activity-block {
            margin-top: 14px;
        }

        .daily-follow-up-report-summary-text,
        .daily-follow-up-report-content-text {
            margin-top: 6px;
            line-height: 1.55;
            color: #111827;
            word-break: break-word;
        }

        .daily-follow-up-report-content-label {
            display: inline-block;
            margin-bottom: 2px;
            font-weight: 700;
            text-decoration: underline;
            color: #111827;
        }

        .daily-follow-up-report-muted {
            color: #6b7280;
        }

        .daily-follow-up-report-customer-link {
            font-weight: 600;
            text-decoration: none;
        }

        .daily-follow-up-report-customer-link:hover {
            text-decoration: underline;
        }

        .daily-follow-up-report-fail-list {
            margin: 8px 0 0 18px;
            padding: 0;
        }

        .daily-follow-up-report-fail-list li {
            margin: 0 0 4px;
            color: #111827;
            line-height: 1.5;
        }

        .daily-follow-up-report-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            background: #ecfeff;
            color: #0f766e;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .daily-follow-up-report-user {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .daily-follow-up-report-table td,
        .daily-follow-up-report-table th {
            vertical-align: top;
        }

        @media (max-width: 991px) {
            .daily-follow-up-report-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .daily-follow-up-report-filter-grid {
                grid-template-columns: 1fr;
            }

            .daily-follow-up-report-activity-cell {
                min-width: 240px;
            }
        }
    </style>
</head>
<body>
    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div>
                        <p class="mb-2">
                            <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                            <?= $h($displayPageTitle) ?>
                        </p>
                        <h2 class="mb-0"><?= $h($displayPageTitle) ?></h2>
                    </div>
                </div>

                <div class="daily-follow-up-report-card mb-4">
                    <form method="get" id="dailyFollowUpReportFilterForm" autocomplete="off">
                        <div class="daily-follow-up-report-filter-grid">
                            <div>
                                <label class="form-label" for="dailyFollowUpReportDate">Date</label>
                                <input class="form-control" type="date" name="date" id="dailyFollowUpReportDate" value="<?= $h($selectedDate) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="dailyFollowUpReportMonth">Month</label>
                                <select class="form-select" name="month" id="dailyFollowUpReportMonth">
                                    <option value="">All</option>
                                    <?php for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) { ?>
                                        <?php
                                        $monthValue = str_pad((string) $monthNumber, 2, '0', STR_PAD_LEFT);
                                        $monthLabel = date('F', mktime(0, 0, 0, $monthNumber, 1));
                                        ?>
                                        <option value="<?= $h($monthValue) ?>" <?= $monthValue === $selectedMonth ? 'selected' : '' ?>><?= $h($monthLabel) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="dailyFollowUpReportYear">Year</label>
                                <select class="form-select" name="year" id="dailyFollowUpReportYear">
                                    <option value="">All</option>
                                    <?php for ($yearValue = (int) $currentYear; $yearValue >= ((int) $currentYear - 5); $yearValue--) { ?>
                                        <option value="<?= $h($yearValue) ?>" <?= (string) $yearValue === $selectedYear ? 'selected' : '' ?>><?= $h($yearValue) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label d-block invisible">Reset</label>
                                <a class="btn btn-outline-secondary w-100" href="<?= $h($_SERVER['PHP_SELF']) ?>">Reset Filters</a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="daily-follow-up-report-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div class="daily-follow-up-report-summary">
                            <?= $h($periodLabel) ?> updated <?= $h($updatedCustomerCount) ?> customer(s) across <?= $h($totalActivityCount) ?> activity log(s)
                        </div>
                    </div>

                    <?php if ($reportError !== '') { ?>
                        <div class="alert alert-danger mb-0"><?= $h($reportError) ?></div>
                    <?php } else if (empty($detailRows)) { ?>
                        <div class="text-center"><h4>No Result!</h4></div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-striped daily-follow-up-report-table" id="daily_follow_up_report_table">
                                <thead>
                                    <tr>
                                        <th>S/N</th>
                                        <th>Customer</th>
                                        <th>Brand</th>
                                        <th>Follow Up Days &amp; Times</th>
                                        <th>Activity</th>
                                        <th>Platform</th>
                                        <th>Follow Up By</th>
                                        <th>Date Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detailRows as $detailRow) { ?>
                                        <?php
                                        $filterAttributes = function_exists('customerRecordBuildFilterDataAttributes')
                                            ? customerRecordBuildFilterDataAttributes(array(
                                                'follow_up_by' => isset($detailRow['follow_up_by']) ? $detailRow['follow_up_by'] : '',
                                                'platform' => isset($detailRow['platform_label']) ? $detailRow['platform_label'] : '',
                                                'brand' => isset($detailRow['brand_name']) ? $detailRow['brand_name'] : '',
                                            ))
                                            : '';
                                        ?>
                                        <tr <?= $filterAttributes ?>>
                                            <td></td>
                                            <td>
                                                <?php if (!empty($detailRow['customer_url'])) { ?>
                                                    <a class="daily-follow-up-report-customer-link" href="<?= $h($detailRow['customer_url']) ?>">
                                                        <?= $h($detailRow['customer_name']) ?>
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="fw-semibold"><?= $h($detailRow['customer_name']) ?></span>
                                                <?php } ?>
                                                <?php if (!empty($detailRow['fail_highlights'])) { ?>
                                                    <ul class="daily-follow-up-report-fail-list">
                                                        <?php foreach ($detailRow['fail_highlights'] as $failHighlight) { ?>
                                                            <li><?= $h($failHighlight) ?></li>
                                                        <?php } ?>
                                                    </ul>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($detailRow['brand_name'])) { ?>
                                                    <span class="daily-follow-up-report-badge"><?= $h($detailRow['brand_name']) ?></span>
                                                <?php } else { ?>
                                                    <span class="daily-follow-up-report-muted">-</span>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <div class="daily-follow-up-report-followup-meta">
                                                    <div><?= $h(isset($detailRow['follow_up_day_display']) ? $detailRow['follow_up_day_display'] : '-') ?></div>
                                                    <div><?= $h(isset($detailRow['follow_up_times_display']) ? $detailRow['follow_up_times_display'] : '-') ?></div>
                                                </div>
                                            </td>
                                            <td class="daily-follow-up-report-activity-cell">
                                                <?php if ($detailRow['summary'] !== '') { ?>
                                                    <div class="daily-follow-up-report-activity-block">
                                                        <div class="daily-follow-up-report-summary-text"><?= urlRenderUserRecordLogPlainTextHtml($detailRow['summary']) ?></div>
                                                    </div>
                                                <?php } ?>

                                                <?php if (!empty($detailRow['content_display'])) { ?>
                                                    <div class="daily-follow-up-report-activity-block">
                                                        <span class="daily-follow-up-report-content-label">Content:</span>
                                                        <div class="daily-follow-up-report-content-text"><?= urlRenderUserRecordLogContentHtml($detailRow['content_display']) ?></div>
                                                    </div>
                                                <?php } ?>

                                                <?php if ((int) $detailRow['attachment_count'] > 0) { ?>
                                                    <div class="daily-follow-up-report-activity-block">
                                                        <span class="daily-follow-up-report-mini-label">Attachment</span>
                                                        <div class="mt-2"><?= $h($detailRow['attachment_count']) ?> file(s)</div>
                                                    </div>
                                                <?php } ?>

                                                <?php if (
                                                    $detailRow['summary'] === ''
                                                    && empty($detailRow['content_display'])
                                                    && (int) $detailRow['attachment_count'] <= 0
                                                ) { ?>
                                                    <span class="daily-follow-up-report-muted">-</span>
                                                <?php } ?>
                                            </td>
                                            <td><?= $h($detailRow['platform_label']) ?></td>
                                            <td>
                                                <div class="daily-follow-up-report-user">
                                                    <span><?= $h($detailRow['follow_up_by']) ?></span>
                                                    <?php if (!empty($detailRow['follow_up_by_badge_html'])) { ?>
                                                        <?= $detailRow['follow_up_by_badge_html'] ?>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                            <td><?= $h($detailRow['created_at']) ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var tableId = "daily_follow_up_report_table";
            var reportTable = document.getElementById(tableId) ? createSortingTable(tableId, {
                order: [[7, "desc"]],
                columnDefs: [
                    {
                        orderable: false,
                        searchable: false,
                        targets: [0],
                    },
                ],
            }) : null;

            if (reportTable) {
                datatableAlignment(tableId);
                reportTable.on("draw", function () {
                    var pageInfo = reportTable.page.info();
                    reportTable.column(0, { page: "current" }).nodes().each(function (cell, index) {
                        cell.innerHTML = pageInfo.start + index + 1;
                    });
                });
                reportTable.draw(false);

                initCustomerRecordTableFilters({
                    tableId: tableId,
                    storageKey: "daily_follow_up_report_filters",
                    panelStorageKey: "daily_follow_up_report_filter_panel_open",
                    deferApply: true,
                    selectFieldsMultiple: true,
                    scopePaths: ["customer/customer_daily_follow_up_report.php"],
                    filters: [
                        { key: "follow_up_by", label: "Follow Up By", attr: "follow_up_by", type: "select", placeholder: "All User" },
                        { key: "platform", label: "Platform", attr: "platform", type: "select", placeholder: "All Platform" },
                        { key: "brand", label: "Brand", attr: "brand", type: "select", placeholder: "All Brands" }
                    ]
                });
            }

            var filterForm = document.getElementById("dailyFollowUpReportFilterForm");
            var dateInput = document.getElementById("dailyFollowUpReportDate");
            var monthSelect = document.getElementById("dailyFollowUpReportMonth");
            var yearSelect = document.getElementById("dailyFollowUpReportYear");

            if (dateInput && filterForm) {
                dateInput.addEventListener("change", function () {
                    if (dateInput.value !== "") {
                        if (monthSelect) {
                            monthSelect.value = "";
                        }
                        if (yearSelect) {
                            yearSelect.value = "";
                        }
                    }
                    filterForm.submit();
                });
            }

            [monthSelect, yearSelect].forEach(function (fieldNode) {
                if (!fieldNode || !filterForm) {
                    return;
                }

                fieldNode.addEventListener("change", function () {
                    if (dateInput) {
                        dateInput.value = "";
                    }
                    filterForm.submit();
                });
            });
        })();

        checkCurrentPage("<?= $h($displayPageTitle) ?>", " ");
        dropdownMenuDispFix();
        setButtonColor();
    </script>
</body>
</html>
