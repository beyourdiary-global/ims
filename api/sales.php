<?php
/**
 * GET /api/sales.php
 *
 * Shopee order report - the "Sales" half of the Daily Sales & Customer
 * Report. Reuses the exact pipeline that shopee/shopee_order_report.php
 * renders, so the numbers match the CMS screen.
 *
 * Params: platform, date_from, date_to, include_rows, limit, offset
 */

define('CMS_API_ENTRY', 'sales');

require_once __DIR__ . '/lib/bootstrap.php';

$keyRow = cmsApiAuthenticate($connect, 'read');

require_once ROOT . '/include/order_report_common.php';

$platform = strtolower(cmsApiQueryValue('platform', 32));
if ($platform === '') {
    $platform = 'shopee';
}

$platformConfig = orderReportGetPlatformConfig($platform);
if (empty($platformConfig)) {
    cmsApiFail(400, 'unknown_platform', 'Unknown platform "' . $platform . '". Try shopee, facebook, website or lazada.');
}

$today = date('Y-m-d');
$dateFrom = orderReportValidateDateValue(cmsApiQueryValue('date_from', 10), $today);
$dateTo = orderReportValidateDateValue(cmsApiQueryValue('date_to', 10), $dateFrom);

if ($dateTo < $dateFrom) {
    $swap = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $swap;
}

$includeRows = cmsApiQueryInt('include_rows', 1, 0, 1) === 1;
$paging = cmsApiPaging(100, 1000);

$orderConn = orderReportGetDbConnection($connect, $finance_connect, isset($platformConfig['db']) ? $platformConfig['db'] : 'finance');
if (!($orderConn instanceof mysqli)) {
    cmsApiFail(503, 'db_unavailable', 'The report database connection is not available.');
}

$referenceMaps = orderReportBuildReferenceMaps($connect, $finance_connect);

// Build the same "status = A + date range" condition the report page builds.
$extraConditions = array();
$dateField = isset($platformConfig['date_field']) ? trim((string) $platformConfig['date_field']) : '';
if ($dateField !== '') {
    $safeFrom = mysqli_real_escape_string($orderConn, $dateFrom);
    $safeTo = mysqli_real_escape_string($orderConn, $dateTo);
    $extraConditions[] = 'DATE(`' . str_replace('`', '``', $dateField) . "`) BETWEEN '" . $safeFrom . "' AND '" . $safeTo . "'";
}

$sourceRows = orderReportFetchRows(
    $orderConn,
    orderReportBuildSourceQuery($orderConn, $platformConfig, '1=1', $extraConditions)
);

$rowMeta = orderReportBuildRowMeta($connect, $finance_connect, $platformConfig, $sourceRows, $referenceMaps);
$enrichedRows = isset($rowMeta['rows']) && is_array($rowMeta['rows']) ? $rowMeta['rows'] : array();

// Newest first, matching the on-screen table.
usort($enrichedRows, function ($a, $b) {
    $aTime = strtotime(trim((string) (isset($a['date_value']) ? $a['date_value'] : '') . ' ' . (isset($a['time_value']) ? $a['time_value'] : '')));
    $bTime = strtotime(trim((string) (isset($b['date_value']) ? $b['date_value'] : '') . ' ' . (isset($b['time_value']) ? $b['time_value'] : '')));

    if ($aTime === false) {
        $aTime = 0;
    }
    if ($bTime === false) {
        $bTime = 0;
    }
    if ($aTime === $bTime) {
        return (int) (isset($b['id']) ? $b['id'] : 0) <=> (int) (isset($a['id']) ? $a['id'] : 0);
    }

    return $bTime <=> $aTime;
});

$totals = orderReportSumMetrics($enrichedRows, $platformConfig);
$breakdowns = orderReportBuildBreakdowns($enrichedRows, $platformConfig);

$data = array(
    'platform' => $platform,
    'platform_label' => isset($platformConfig['label']) ? (string) $platformConfig['label'] : $platform,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'order_count' => count($enrichedRows),
    'totals' => $totals,
    'breakdowns' => orderReportBuildBreakdownPayload($breakdowns),
);

if ($includeRows) {
    $data['rows'] = cmsApiPage($enrichedRows, $paging['limit'], $paging['offset']);
}

cmsApiOk(
    $data,
    cmsApiMeta('sales', $keyRow, array(
        'source_table' => isset($platformConfig['table']) ? (string) $platformConfig['table'] : '',
        'source_db' => isset($platformConfig['db']) ? (string) $platformConfig['db'] : '',
        'include_rows' => $includeRows,
    ))
);
