<?php
$isAjaxAnalysisRequest = (
    (isset($_GET['analysis_ajax']) && (string) $_GET['analysis_ajax'] === '1') ||
    (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    )
);

ob_start();
$currentPagePin = 160;
$pageTitle = 'Customer Dashboard';
$displayPageTitle = $pageTitle;
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
    renderNotificationScript('You do not have permission to view Customer Dashboard.', 'error', '../dashboard.php', 1200, true);
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

$currentYear = (int) date('Y');
$currentMonth = (int) date('n');
$currentWeekNumber = customerAnalysisResolveWeekNumber((int) date('j'));
$selectedYearlyYear = customerAnalysisNormalizeYear(isset($_GET['yearly_year']) ? $_GET['yearly_year'] : $currentYear);
$selectedDailyYear = customerAnalysisNormalizeYear(isset($_GET['daily_year']) ? $_GET['daily_year'] : $currentYear);
$selectedDailyMonth = customerAnalysisNormalizeMonth(isset($_GET['daily_month']) ? $_GET['daily_month'] : $currentMonth);
$selectedWeeklyYear = customerAnalysisNormalizeYear(isset($_GET['weekly_year']) ? $_GET['weekly_year'] : $currentYear);
$selectedWeeklyMonth = customerAnalysisNormalizeMonth(isset($_GET['weekly_month']) ? $_GET['weekly_month'] : $currentMonth);
$defaultWeeklyWeek = ($selectedWeeklyYear === $currentYear && $selectedWeeklyMonth === $currentMonth) ? $currentWeekNumber : 1;
$selectedWeeklyWeek = isset($_GET['weekly_week']) ? (int) $_GET['weekly_week'] : $defaultWeeklyWeek;

$monthlyDataset = customerAnalysisBuildMonthlyRows($connect, $finance_connect, $selectedYearlyYear);
$dailyDataset = customerAnalysisBuildDailyRows($connect, $finance_connect, $selectedDailyYear, $selectedDailyMonth);
$weeklyDataset = customerAnalysisBuildWeeklyRows($connect, $finance_connect, $selectedWeeklyYear, $selectedWeeklyMonth);

$weeklyRows = isset($weeklyDataset['rows']) && is_array($weeklyDataset['rows']) ? $weeklyDataset['rows'] : array();
$validWeeklyNumbers = array();
foreach ($weeklyRows as $weeklyRow) {
    $validWeeklyNumbers[] = isset($weeklyRow['week_number']) ? (int) $weeklyRow['week_number'] : 0;
}
$validWeeklyNumbers = array_values(array_unique(array_filter($validWeeklyNumbers)));
if (empty($validWeeklyNumbers)) {
    $selectedWeeklyWeek = 1;
} else if (!in_array($selectedWeeklyWeek, $validWeeklyNumbers, true)) {
    $selectedWeeklyWeek = in_array($defaultWeeklyWeek, $validWeeklyNumbers, true) ? $defaultWeeklyWeek : (int) $validWeeklyNumbers[0];
}

$selectedWeeklySummary = array();
foreach ($weeklyRows as $index => $weeklyRow) {
    $isSelected = (int) ($weeklyRow['week_number'] ?? 0) === $selectedWeeklyWeek;
    $weeklyRows[$index]['is_selected'] = $isSelected;
    if ($isSelected) {
        $selectedWeeklySummary = $weeklyRows[$index];
    }
}
$weeklyDataset['rows'] = $weeklyRows;

$monthOptions = array(
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
);

$yearOptions = array();
for ($yearOption = $currentYear - 5; $yearOption <= $currentYear + 1; $yearOption++) {
    $yearOptions[] = $yearOption;
}

$pageUrl = $SITEURL . '/customer/customer_analysis_dashboard.php';
$allFilters = array(
    'yearly_year' => $selectedYearlyYear,
    'daily_year' => $selectedDailyYear,
    'daily_month' => $selectedDailyMonth,
    'weekly_year' => $selectedWeeklyYear,
    'weekly_month' => $selectedWeeklyMonth,
    'weekly_week' => $selectedWeeklyWeek,
);

$h = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$jsonEncode = function ($value) {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
};

$displayMetric = function ($value) {
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 0, '.', ',');
};

$renderHiddenInputs = function ($excludeKeys = array()) use ($allFilters, $h) {
    foreach ($allFilters as $filterKey => $filterValue) {
        if (in_array($filterKey, $excludeKeys, true)) {
            continue;
        }

        echo '<input type="hidden" name="' . $h($filterKey) . '" value="' . $h($filterValue) . '">' . PHP_EOL;
    }
};

$selectedWeeklyLabel = isset($selectedWeeklySummary['week_label']) ? (string) $selectedWeeklySummary['week_label'] : 'No week selected';
$selectedWeeklyDateRange = '';
if (!empty($selectedWeeklySummary)) {
    $selectedWeeklyDateRange = trim((string) ($selectedWeeklySummary['start_date'] ?? '') . ' to ' . (string) ($selectedWeeklySummary['end_date'] ?? ''));
}

$weeklyChartLabels = isset($weeklyDataset['chart']['labels']) && is_array($weeklyDataset['chart']['labels']) ? $weeklyDataset['chart']['labels'] : array();
$weeklyChartNewBackgrounds = array();
$weeklyChartReturningBackgrounds = array();
foreach ($weeklyRows as $weeklyRow) {
    $isSelected = !empty($weeklyRow['is_selected']);
    $weeklyChartNewBackgrounds[] = $isSelected ? 'rgba(13, 110, 253, 0.9)' : 'rgba(13, 110, 253, 0.55)';
    $weeklyChartReturningBackgrounds[] = $isSelected ? 'rgba(255, 193, 7, 0.95)' : 'rgba(255, 193, 7, 0.6)';
}

$monthlyChartPayload = array(
    'labels' => $monthlyDataset['chart']['labels'] ?? array(),
    'newCustomerTotals' => $monthlyDataset['chart']['new_customer_totals'] ?? array(),
    'returningCustomerTotals' => $monthlyDataset['chart']['returning_customer_totals'] ?? array(),
    'title' => 'Monthly New vs Returning Customers',
);
$dailyChartPayload = array(
    'labels' => $dailyDataset['chart']['labels'] ?? array(),
    'newCustomerTotals' => $dailyDataset['chart']['new_customer_totals'] ?? array(),
    'returningCustomerTotals' => $dailyDataset['chart']['returning_customer_totals'] ?? array(),
    'title' => 'Daily New vs Returning Customers',
);
$weeklyChartPayload = array(
    'labels' => $weeklyChartLabels,
    'newCustomerTotals' => $weeklyDataset['chart']['new_customer_totals'] ?? array(),
    'returningCustomerTotals' => $weeklyDataset['chart']['returning_customer_totals'] ?? array(),
    'newBackgrounds' => $weeklyChartNewBackgrounds,
    'returningBackgrounds' => $weeklyChartReturningBackgrounds,
    'title' => 'Weekly New vs Returning Customers',
);

$renderYearSection = function () use ($h, $displayPageTitle, $pageUrl, $renderHiddenInputs, $yearOptions, $selectedYearlyYear, $monthlyDataset, $displayMetric) {
    ob_start();
    ?>
        <section id="customerAnalysisYearSection" class="customer-analysis-card customer-analysis-section">
            <div class="customer-analysis-heading">
                <div>
                    <h4 class="mb-1">Analysis by Year</h4>
                    <div class="customer-analysis-subtitle">Monthly view for the selected year. New and returning counts are calculated live from customer creation dates and matching orders.</div>
                </div>
            </div>

            <form method="get" action="<?= $h($pageUrl) ?>" class="customer-analysis-filter-form customer-analysis-auto-submit-form">
                <?php $renderHiddenInputs(array('yearly_year')); ?>
                <div class="customer-analysis-filter-grid">
                    <div>
                        <label class="form-label" for="yearly_year">Year</label>
                        <select class="form-select" id="yearly_year" name="yearly_year">
                            <?php foreach ($yearOptions as $yearOption): ?>
                                <option value="<?= $h($yearOption) ?>" <?= (int) $yearOption === (int) $selectedYearlyYear ? 'selected' : '' ?>><?= $h($yearOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <div class="customer-analysis-table-wrap table-responsive mb-3">
                <table id="customerAnalysisYearTable" class="table table-striped align-middle w-100 customer-analysis-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Month</th>
                            <th>Total New Customer per Month</th>
                            <th>Total Returning Customer per Month</th>
                            <th>Total estimate repeat order monthly</th>
                            <th>Total Success repeat order monthly</th>
                            <th>Total LOST Customer</th>
                            <th>Total Loyal Customer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($monthlyDataset['rows'] ?? array()) as $index => $row): ?>
                            <tr>
                                <td><?= $h($index + 1) ?></td>
                                <th scope="row"><?= $h($row['month_label'] ?? '') ?></th>
                                <td><?= $h($displayMetric($row['new_customer_total'] ?? 0)) ?></td>
                                <td><?= $h($displayMetric($row['returning_customer_total'] ?? 0)) ?></td>
                                <td><?= $h($displayMetric($row['estimated_repeat_order_total'] ?? null)) ?></td>
                                <td><?= $h($displayMetric($row['success_repeat_order_total'] ?? null)) ?></td>
                                <td><?= $h($displayMetric($row['lost_customer_total'] ?? null)) ?></td>
                                <td><?= $h($displayMetric($row['loyal_customer_total'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="customer-analysis-chart-wrap">
                <canvas id="customerAnalysisYearChart"></canvas>
            </div>
        </section>
    <?php

    return ob_get_clean();
};

$renderMonthSection = function () use ($h, $pageUrl, $renderHiddenInputs, $monthOptions, $selectedDailyMonth, $yearOptions, $selectedDailyYear, $dailyDataset, $displayMetric) {
    ob_start();
    ?>
        <section id="customerAnalysisMonthSection" class="customer-analysis-card customer-analysis-section">
            <div class="customer-analysis-heading">
                <div>
                    <h4 class="mb-1">Analysis by Monthly</h4>
                    <div class="customer-analysis-subtitle">Daily breakdown for the selected month. Cumulative totals restart from day 1 of the selected month.</div>
                </div>
            </div>

            <form method="get" action="<?= $h($pageUrl) ?>" class="customer-analysis-filter-form customer-analysis-auto-submit-form">
                <?php $renderHiddenInputs(array('daily_year', 'daily_month')); ?>
                <div class="customer-analysis-filter-grid">
                    <div>
                        <label class="form-label" for="daily_month">Month</label>
                        <select class="form-select" id="daily_month" name="daily_month">
                            <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                <option value="<?= $h($monthValue) ?>" <?= (int) $monthValue === (int) $selectedDailyMonth ? 'selected' : '' ?>><?= $h($monthLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="daily_year">Year</label>
                        <select class="form-select" id="daily_year" name="daily_year">
                            <?php foreach ($yearOptions as $yearOption): ?>
                                <option value="<?= $h($yearOption) ?>" <?= (int) $yearOption === (int) $selectedDailyYear ? 'selected' : '' ?>><?= $h($yearOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <div class="customer-analysis-table-wrap table-responsive mb-3">
                <table id="customerAnalysisMonthTable" class="table table-striped align-middle w-100 customer-analysis-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Date</th>
                            <th>Total New Customer per day</th>
                            <th>Cumulative New Customer</th>
                            <th>Total Returning Customer per day</th>
                            <th>Cumulative Returning Customer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ((array) ($dailyDataset['rows'] ?? array()) as $index => $row): ?>
                            <tr>
                                <td><?= $h($index + 1) ?></td>
                                <th scope="row"><?= $h($row['date_label'] ?? '') ?></th>
                                <td><?= $h($displayMetric($row['new_customer_total'] ?? 0)) ?></td>
                                <td><?= $h($displayMetric($row['cumulative_new_customer_total'] ?? 0)) ?></td>
                                <td><?= $h($displayMetric($row['returning_customer_total'] ?? 0)) ?></td>
                                <td><?= $h($displayMetric($row['cumulative_returning_customer_total'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="customer-analysis-chart-wrap">
                <canvas id="customerAnalysisMonthChart"></canvas>
            </div>
        </section>
    <?php

    return ob_get_clean();
};

$renderWeekSection = function () use ($h, $pageUrl, $renderHiddenInputs, $monthOptions, $selectedWeeklyMonth, $yearOptions, $selectedWeeklyYear, $weeklyRows, $selectedWeeklyWeek, $selectedWeeklySummary, $selectedWeeklyLabel, $selectedWeeklyDateRange, $displayMetric) {
    ob_start();
    ?>
        <section id="customerAnalysisWeekSection" class="customer-analysis-card customer-analysis-section">
            <div class="customer-analysis-heading">
                <div>
                    <h4 class="mb-1">Analysis by Weekly</h4>
                    <div class="customer-analysis-subtitle">Week ranges follow the fixed monthly buckets: Day 1-7, 8-14, 15-21, and 22-end of month.</div>
                </div>
                <?php if (!empty($selectedWeeklySummary)): ?>
                    <div class="customer-analysis-highlight">
                        <span>Focused week:</span>
                        <span><?= $h($selectedWeeklyLabel) ?></span>
                        <?php if ($selectedWeeklyDateRange !== ''): ?>
                            <span><?= $h('(' . $selectedWeeklyDateRange . ')') ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="get" action="<?= $h($pageUrl) ?>" class="customer-analysis-filter-form customer-analysis-auto-submit-form">
                <?php $renderHiddenInputs(array('weekly_year', 'weekly_month', 'weekly_week')); ?>
                <div class="customer-analysis-filter-grid">
                    <div>
                        <label class="form-label" for="weekly_month">Month</label>
                        <select class="form-select" id="weekly_month" name="weekly_month">
                            <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                                <option value="<?= $h($monthValue) ?>" <?= (int) $monthValue === (int) $selectedWeeklyMonth ? 'selected' : '' ?>><?= $h($monthLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="weekly_year">Year</label>
                        <select class="form-select" id="weekly_year" name="weekly_year">
                            <?php foreach ($yearOptions as $yearOption): ?>
                                <option value="<?= $h($yearOption) ?>" <?= (int) $yearOption === (int) $selectedWeeklyYear ? 'selected' : '' ?>><?= $h($yearOption) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="weekly_week">Week period</label>
                        <select class="form-select" id="weekly_week" name="weekly_week">
                            <?php foreach ($weeklyRows as $weeklyRow): ?>
                                <option value="<?= $h($weeklyRow['week_number'] ?? 0) ?>" <?= (int) ($weeklyRow['week_number'] ?? 0) === (int) $selectedWeeklyWeek ? 'selected' : '' ?>><?= $h($weeklyRow['week_label'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <div class="customer-analysis-table-wrap table-responsive mb-3">
                <table id="customerAnalysisWeekTable" class="table table-striped align-middle w-100 customer-analysis-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Week</th>
                            <th>Total New Customer per Week</th>
                            <th>Total Returning Customer per Week</th>
                            <th>Total estimate repeat order weekly</th>
                            <th>Total Success repeat order weekly</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weeklyRows as $index => $weeklyRow): ?>
                            <tr class="<?= !empty($weeklyRow['is_selected']) ? 'analysis-week-focus' : '' ?>">
                                <td><?= $h($index + 1) ?></td>
                                <th scope="row"><?= $h($weeklyRow['week_label'] ?? '') ?></th>
                                <td><?= $h($displayMetric($weeklyRow['new_customer_total'] ?? 0)) ?></td>
                                <td><?= $h($displayMetric($weeklyRow['returning_customer_total'] ?? 0)) ?></td>
                                <td><?= $h($displayMetric($weeklyRow['estimated_repeat_order_total'] ?? null)) ?></td>
                                <td><?= $h($displayMetric($weeklyRow['success_repeat_order_total'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="customer-analysis-chart-wrap">
                <canvas id="customerAnalysisWeekChart"></canvas>
            </div>
        </section>
    <?php

    return ob_get_clean();
};

$currentFilterQuery = http_build_query($allFilters);
$currentFilterUrl = $pageUrl . ($currentFilterQuery !== '' ? '?' . $currentFilterQuery : '');
if ($isAjaxAnalysisRequest) {
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo $jsonEncode(array(
        'success' => true,
        'sections' => array(
            'year' => $renderYearSection(),
            'month' => $renderMonthSection(),
            'week' => $renderWeekSection(),
        ),
        'charts' => array(
            'year' => $monthlyChartPayload,
            'month' => $dailyChartPayload,
            'week' => $weeklyChartPayload,
        ),
        'filters' => $allFilters,
        'url' => $currentFilterUrl,
    ));
    exit;
}

ob_end_flush();
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $h($SITEURL . '/css/main.css') ?>">
    <style>
        .customer-analysis-dashboard {
            background: #f6f8fb;
            min-height: 100vh;
        }

        .customer-analysis-stack {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .customer-analysis-card {
            background: #ffffff;
            border: 1px solid #e3e8ef;
            border-radius: 18px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
            padding: 22px;
        }

        .customer-analysis-heading {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .customer-analysis-subtitle,
        .customer-analysis-footnote {
            color: #667085;
        }

        .customer-analysis-filter-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 18px;
        }

        .customer-analysis-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            align-items: end;
        }

        .customer-analysis-chart-wrap {
            border: 1px solid #edf1f6;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            padding: 16px;
            min-height: 340px;
        }

        .customer-analysis-table-wrap {
            padding: 0;
            background: #ffffff;
        }

        .customer-analysis-table-wrap table {
            margin-bottom: 0;
        }

        .customer-analysis-table {
            --bs-table-border-color: transparent;
        }

        .customer-analysis-table thead th {
            border-bottom: 0;
            white-space: normal;
        }

        .customer-analysis-table tbody th,
        .customer-analysis-table tbody td {
            border-top: 0;
        }

        .analysis-week-focus td,
        .analysis-week-focus th {
            background: rgba(13, 110, 253, 0.08) !important;
        }

        .customer-analysis-highlight {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            background: #eef4ff;
            color: #114a8c;
            padding: 8px 14px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .customer-analysis-section-note {
            font-size: 0.92rem;
            margin-top: 10px;
        }

        .customer-analysis-section.is-loading {
            opacity: 0.6;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        @media (max-width: 991.98px) {
            .customer-analysis-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .customer-analysis-card {
                padding: 18px;
            }

            .customer-analysis-filter-grid {
                grid-template-columns: 1fr;
            }

            .customer-analysis-chart-wrap {
                min-height: 300px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid customer-analysis-dashboard py-4">
    <div class="col-12 col-xl-11 mx-auto customer-analysis-stack">
        <div class="customer-analysis-card">
            <div class="row">
                <p><a href="<?= $h($SITEURL . '/dashboard.php') ?>">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> Customer <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $h($displayPageTitle) ?></p>
            </div>
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-2"><?= $h($displayPageTitle) ?></h2>
                    <div class="customer-analysis-subtitle">Live customer and order activity across Shopee, Lazada, Facebook, and Website.</div>
                </div>
            </div>
        </div>

        <div id="customerAnalysisSections">
            <?= $renderYearSection() ?>
            <?= $renderMonthSection() ?>
            <?= $renderWeekSection() ?>
        </div>
    </div>
</div>

<script src="<?= $h(CHART_JS_LOCAL_PATH) ?>"></script>
<script>
const customerAnalysisConfig = <?= $jsonEncode(array(
    'ajaxUrl' => $pageUrl,
    'chartPayloads' => array(
        'year' => $monthlyChartPayload,
        'month' => $dailyChartPayload,
        'week' => $weeklyChartPayload,
    ),
)) ?>;
const customerAnalysisState = {
    charts: {},
    dataTables: {},
    currentRequestController: null,
    requestSequence: 0,
};
const dataTableConfigs = [
    { key: 'year', selector: '#customerAnalysisYearTable' },
    { key: 'month', selector: '#customerAnalysisMonthTable' },
    { key: 'week', selector: '#customerAnalysisWeekTable' },
];

const setLoadingState = (isLoading) => {
    document.querySelectorAll('.customer-analysis-section').forEach((sectionElement) => {
        sectionElement.classList.toggle('is-loading', isLoading);
    });

    document.querySelectorAll('.customer-analysis-auto-submit-form select').forEach((selectElement) => {
        selectElement.disabled = isLoading;
    });
};

const destroyDataTables = () => {
    Object.keys(customerAnalysisState.dataTables).forEach((tableKey) => {
        const tableInstance = customerAnalysisState.dataTables[tableKey];
        if (tableInstance && typeof tableInstance.destroy === 'function') {
            tableInstance.destroy();
        }
    });

    customerAnalysisState.dataTables = {};
};

const initDataTables = () => {
    if (typeof DataTable === 'undefined') {
        return;
    }

    dataTableConfigs.forEach((config) => {
        const tableElement = document.querySelector(config.selector);
        if (!tableElement) {
            return;
        }

        customerAnalysisState.dataTables[config.key] = new DataTable(tableElement, {
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            autoWidth: false,
        });
    });
};

const buildGroupedBarChart = (canvasId, payload, customColors = {}) => {
    if (typeof Chart === 'undefined') {
        return null;
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return null;
    }

    const defaultNewColor = 'rgba(13, 110, 253, 0.72)';
    const defaultReturningColor = 'rgba(255, 193, 7, 0.72)';

    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: payload.labels || [],
            datasets: [
                {
                    label: 'New Customer',
                    data: payload.newCustomerTotals || [],
                    backgroundColor: customColors.newBackgrounds || defaultNewColor,
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'Returning Customer',
                    data: payload.returningCustomerTotals || [],
                    backgroundColor: customColors.returningBackgrounds || defaultReturningColor,
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: payload.title || '',
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                    },
                },
            },
        },
    });
};

const destroyCharts = () => {
    Object.keys(customerAnalysisState.charts).forEach((chartKey) => {
        const chartInstance = customerAnalysisState.charts[chartKey];
        if (chartInstance && typeof chartInstance.destroy === 'function') {
            chartInstance.destroy();
        }
    });

    customerAnalysisState.charts = {};
};

const renderCharts = (chartPayloads) => {
    destroyCharts();

    customerAnalysisState.charts.year = buildGroupedBarChart('customerAnalysisYearChart', chartPayloads.year || {});
    customerAnalysisState.charts.month = buildGroupedBarChart('customerAnalysisMonthChart', chartPayloads.month || {});
    customerAnalysisState.charts.week = buildGroupedBarChart('customerAnalysisWeekChart', chartPayloads.week || {}, {
        newBackgrounds: (chartPayloads.week || {}).newBackgrounds || [],
        returningBackgrounds: (chartPayloads.week || {}).returningBackgrounds || [],
    });
};

const replaceSectionHtml = (sectionId, html) => {
    const currentSection = document.getElementById(sectionId);
    if (!currentSection || typeof html !== 'string' || html.trim() === '') {
        return;
    }

    const template = document.createElement('template');
    template.innerHTML = html.trim();
    const nextSection = template.content.firstElementChild;
    if (!nextSection) {
        return;
    }

    currentSection.replaceWith(nextSection);
};

const bindFilterForms = () => {
    const requestAnalysisUpdate = (parentForm) => {
        if (!parentForm) {
            return;
        }

        const browserUrl = new URL(customerAnalysisConfig.ajaxUrl, window.location.origin);
        const browserParams = new URLSearchParams(new FormData(parentForm));
        browserParams.forEach((value, key) => {
            browserUrl.searchParams.set(key, value);
        });

        if (customerAnalysisState.currentRequestController) {
            customerAnalysisState.currentRequestController.abort();
        }

        const requestUrl = new URL(browserUrl.toString());
        requestUrl.searchParams.set('analysis_ajax', '1');
        const requestController = new AbortController();
        const requestSequence = ++customerAnalysisState.requestSequence;
        customerAnalysisState.currentRequestController = requestController;

        setLoadingState(true);

        window.fetch(requestUrl.toString(), {
            credentials: 'same-origin',
            signal: requestController.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Failed to load Customer Dashboard.');
                }

                return response.json();
            })
            .then((payload) => {
                if (!payload || payload.success !== true) {
                    throw new Error('Invalid customer analysis response.');
                }

                if (requestSequence !== customerAnalysisState.requestSequence) {
                    return;
                }

                destroyDataTables();
                destroyCharts();

                replaceSectionHtml('customerAnalysisYearSection', payload.sections ? payload.sections.year : '');
                replaceSectionHtml('customerAnalysisMonthSection', payload.sections ? payload.sections.month : '');
                replaceSectionHtml('customerAnalysisWeekSection', payload.sections ? payload.sections.week : '');

                customerAnalysisConfig.chartPayloads = payload.charts || {};
                bindFilterForms();
                initDataTables();
                renderCharts(customerAnalysisConfig.chartPayloads);

                const nextUrl = payload.url || browserUrl.toString();
                window.history.replaceState({ customerAnalysisUrl: nextUrl }, '', nextUrl);
            })
            .catch((error) => {
                if (error && error.name === 'AbortError') {
                    return;
                }

                console.error('Customer analysis AJAX update failed.', error);
            })
            .finally(() => {
                if (requestSequence === customerAnalysisState.requestSequence) {
                    customerAnalysisState.currentRequestController = null;
                    setLoadingState(false);
                }
            });
    };

    document.querySelectorAll('.customer-analysis-auto-submit-form').forEach((formElement) => {
        if (formElement.getAttribute('data-analysis-submit-bound') === '1') {
            return;
        }

        formElement.addEventListener('submit', (event) => {
            event.preventDefault();
            requestAnalysisUpdate(formElement);
        });

        formElement.setAttribute('data-analysis-submit-bound', '1');
    });

    document.querySelectorAll('.customer-analysis-auto-submit-form select').forEach((selectElement) => {
        if (selectElement.getAttribute('data-analysis-bound') === '1') {
            return;
        }

        selectElement.addEventListener('change', () => {
            const parentForm = selectElement.form;
            requestAnalysisUpdate(parentForm);
        });

        selectElement.setAttribute('data-analysis-bound', '1');
    });
};

bindFilterForms();
initDataTables();
renderCharts(customerAnalysisConfig.chartPayloads);
</script>
</body>
</html>
