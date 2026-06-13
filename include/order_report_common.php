<?php

if (!function_exists('orderReportGetPlatformConfig')) {
    function orderReportGetPlatformConfig($platform)
    {
        $platform = shopeeOmsNormalizePlatformKey($platform);
        $sourceConfig = shopeeOmsGetOrderSourceConfig($platform);
        if ($platform === '' || empty($sourceConfig)) {
            return array();
        }

        $configs = array(
            'shopee' => array(
                'platform' => 'shopee',
                'label' => 'Shopee',
                'page_title' => 'Shopee Order Report',
                'date_field' => 'date',
                'time_field' => 'time',
                'order_code_field' => 'orderID',
                'customer_name_field' => 'buyer',
                'package_field' => 'package',
                'brand_field' => 'brand',
                'warehouse_field' => 'stock_out_warehouse_id',
                'payment_field' => 'buyer_pay_meth',
                'status_field' => 'order_status',
                'final_amount_field' => 'final_amt',
                'voucher_field' => 'voucher',
                'service_fee_field' => 'service_fee',
                'transaction_fee_field' => 'trans_fee',
                'aws_commission_fee_field' => 'ams_fee',
                'charges_and_fees_field' => 'fees',
                'price_field' => 'price',
                'fallback_code_prefix' => 'SHP',
                'detail_url' => '/shopee/shopee_order_req.php',
                'path_prefix' => '../',
                'table_id' => 'shopee_order_report_detail_table',
                'db' => 'finance',
            ),
            'facebook' => array(
                'platform' => 'facebook',
                'label' => 'Facebook',
                'page_title' => 'Facebook Order Report',
                'date_field' => 'create_date',
                'time_field' => 'create_time',
                'order_code_field' => '',
                'customer_name_field' => 'name',
                'package_field' => 'package',
                'brand_field' => 'brand',
                'warehouse_field' => 'stock_out_warehouse_id',
                'payment_field' => 'pay_method',
                'status_field' => 'order_status',
                'final_amount_field' => 'price',
                'voucher_field' => '',
                'service_fee_field' => '',
                'transaction_fee_field' => '',
                'aws_commission_fee_field' => '',
                'charges_and_fees_field' => '',
                'price_field' => 'price',
                'fallback_code_prefix' => 'FB',
                'detail_url' => '/finance/fb_order_req.php',
                'path_prefix' => '../',
                'table_id' => 'facebook_order_report_detail_table',
                'db' => 'finance',
            ),
            'website' => array(
                'platform' => 'website',
                'label' => 'Website',
                'page_title' => 'Website Order Report',
                'date_field' => 'create_date',
                'time_field' => 'create_time',
                'order_code_field' => 'order_id',
                'customer_name_field' => 'cust_name',
                'package_field' => 'pkg',
                'brand_field' => 'brand',
                'warehouse_field' => 'stock_out_warehouse_id',
                'payment_field' => 'pay_method',
                'status_field' => 'order_status',
                'final_amount_field' => 'total',
                'voucher_field' => 'discount',
                'service_fee_field' => '',
                'transaction_fee_field' => '',
                'aws_commission_fee_field' => '',
                'charges_and_fees_field' => 'shipping',
                'price_field' => 'price',
                'fallback_code_prefix' => 'WEB',
                'detail_url' => '/finance/website_order_request.php',
                'path_prefix' => '../',
                'table_id' => 'website_order_report_detail_table',
                'db' => 'finance',
            ),
            'lazada' => array(
                'platform' => 'lazada',
                'label' => 'Lazada',
                'page_title' => 'Lazada Order Report',
                'date_field' => 'create_date',
                'time_field' => 'create_time',
                'order_code_field' => 'oder_number',
                'customer_name_field' => 'cust_name',
                'package_field' => 'pkg',
                'brand_field' => 'brand',
                'warehouse_field' => 'stock_out_warehouse_id',
                'payment_field' => 'pay_meth',
                'status_field' => 'order_status',
                'final_amount_field' => 'final_income',
                'voucher_field' => 'other_discount',
                'service_fee_field' => 'commision',
                'transaction_fee_field' => 'pay_fee',
                'aws_commission_fee_field' => '',
                'charges_and_fees_field' => '',
                'price_field' => 'item_price_credit',
                'fallback_code_prefix' => 'LAZ',
                'detail_url' => '/lazada_order_req.php',
                'path_prefix' => '',
                'table_id' => 'lazada_order_report_detail_table',
                'db' => 'cms',
            ),
        );

        if (!isset($configs[$platform])) {
            return array();
        }

        $config = array_merge($configs[$platform], $sourceConfig);
        if ((string) ($config['detail_url'] ?? '') === '' && trim((string) ($config['view_url'] ?? '')) !== '') {
            $config['detail_url'] = (string) $config['view_url'];
        }

        return $config;
    }
}

if (!function_exists('orderReportGetDbConnection')) {
    function orderReportGetDbConnection($connect, $financeConnect, $dbKey)
    {
        return $dbKey === 'finance' ? $financeConnect : $connect;
    }
}

if (!function_exists('orderReportFetchRows')) {
    function orderReportFetchRows($conn, $sql)
    {
        $rows = array();
        if (!($conn instanceof mysqli) || trim((string) $sql) === '') {
            return $rows;
        }

        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('orderReportEscape')) {
    function orderReportEscape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('orderReportSafeFloat')) {
    function orderReportSafeFloat($value)
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^0-9.\-]+/', '', (string) $value);
        return is_numeric($value) ? (float) $value : 0.0;
    }
}

if (!function_exists('orderReportFormatAmount')) {
    function orderReportFormatAmount($value)
    {
        return number_format((float) $value, 2, '.', ',');
    }
}

if (!function_exists('orderReportNormalizeReportType')) {
    function orderReportNormalizeReportType($reportType)
    {
        $reportType = strtolower(trim((string) $reportType));
        return in_array($reportType, array('daily', 'monthly', 'yearly'), true) ? $reportType : 'daily';
    }
}

if (!function_exists('orderReportReadArrayInput')) {
    function orderReportReadArrayInput($key)
    {
        if (!isset($_GET[$key]) || !is_array($_GET[$key])) {
            return array();
        }

        $values = array();
        foreach ((array) $_GET[$key] as $value) {
            $value = trim(strip_tags((string) $value));
            if ($value === '' || strlen($value) > 255 || isset($values[$value])) {
                continue;
            }

            $values[$value] = $value;
        }

        return array_values($values);
    }
}

if (!function_exists('orderReportValidateDateValue')) {
    function orderReportValidateDateValue($value, $defaultValue)
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if (!($date instanceof DateTimeImmutable) || $hasErrors || $date->format('Y-m-d') !== $value) {
            return $defaultValue;
        }

        return $date->format('Y-m-d');
    }
}

if (!function_exists('orderReportValidateMonthValue')) {
    function orderReportValidateMonthValue($value, $defaultValue)
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('Y-m', $value);
        $errors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if (!($date instanceof DateTimeImmutable) || $hasErrors || $date->format('Y-m') !== $value) {
            return $defaultValue;
        }

        return $date->format('Y-m');
    }
}

if (!function_exists('orderReportValidateYearValue')) {
    function orderReportValidateYearValue($value, $defaultValue)
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}$/', $value)) {
            return $defaultValue;
        }

        return $value;
    }
}

if (!function_exists('orderReportBuildState')) {
    function orderReportBuildState($platform = '')
    {
        $today = new DateTimeImmutable('today');
        $platform = preg_replace('/[^a-z0-9_]+/i', '_', strtolower(trim((string) $platform)));
        $sessionKey = 'order_report_filter_state_' . ($platform !== '' ? $platform : 'default');

        if (isset($_GET['reset']) && (string) $_GET['reset'] === '1') {
            unset($_SESSION[$sessionKey]);
        }

        $hasSearchRequest = isset($_GET['search']) && (string) $_GET['search'] === '1';

        if (!$hasSearchRequest && isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey])) {
            return $_SESSION[$sessionKey];
        }

        $reportType = orderReportNormalizeReportType(input('report_type'));
        $dateValue = orderReportValidateDateValue(input('report_date'), $today->format('Y-m-d'));
        $monthValue = orderReportValidateMonthValue(input('report_month'), $today->format('Y-m'));
        $yearValue = orderReportValidateYearValue(input('report_year'), $today->format('Y'));

        $state = array(
            'search_requested' => $hasSearchRequest,
            'report_type' => $reportType,
            'report_date' => $dateValue,
            'report_month' => $monthValue,
            'report_year' => $yearValue,
            'filters' => array(
                'package' => orderReportReadArrayInput('package'),
                'brand' => orderReportReadArrayInput('brand'),
                'warehouse' => orderReportReadArrayInput('warehouse'),
                'payment' => orderReportReadArrayInput('payment'),
                'customer_label' => orderReportReadArrayInput('customer_label'),
                'segmentation' => orderReportReadArrayInput('segmentation'),
                'level' => orderReportReadArrayInput('level'),
                'repeat' => orderReportReadArrayInput('repeat'),
            ),
        );

        if ($hasSearchRequest) {
            $_SESSION[$sessionKey] = $state;
        }

        return $state;
    }
}

if (!function_exists('orderReportBuildStateFromRequest')) {
    function orderReportBuildStateFromRequest($fallbackState = array())
    {
        $today = new DateTimeImmutable('today');
        $fallbackDate = isset($fallbackState['report_date']) ? (string) $fallbackState['report_date'] : $today->format('Y-m-d');
        $fallbackMonth = isset($fallbackState['report_month']) ? (string) $fallbackState['report_month'] : $today->format('Y-m');
        $fallbackYear = isset($fallbackState['report_year']) ? (string) $fallbackState['report_year'] : $today->format('Y');

        return array(
            'search_requested' => false,
            'report_type' => orderReportNormalizeReportType(input('report_type')),
            'report_date' => orderReportValidateDateValue(input('report_date'), $fallbackDate),
            'report_month' => orderReportValidateMonthValue(input('report_month'), $fallbackMonth),
            'report_year' => orderReportValidateYearValue(input('report_year'), $fallbackYear),
            'filters' => array(
                'package' => array(),
                'brand' => array(),
                'warehouse' => array(),
                'payment' => array(),
                'customer_label' => array(),
                'segmentation' => array(),
                'level' => array(),
                'repeat' => array(),
            ),
        );
    }
}

if (!function_exists('orderReportGetPeriodKeyFromState')) {
    function orderReportGetPeriodKeyFromState($state)
    {
        $reportType = isset($state['report_type']) ? orderReportNormalizeReportType($state['report_type']) : 'daily';
        if ($reportType === 'monthly') {
            return trim((string) ($state['report_month'] ?? ''));
        }

        if ($reportType === 'yearly') {
            return trim((string) ($state['report_year'] ?? ''));
        }

        return trim((string) ($state['report_date'] ?? ''));
    }
}

if (!function_exists('orderReportBuildDateWhereSql')) {
    function orderReportBuildDateWhereSql($conn, $fieldName, $state)
    {
        $fieldName = trim((string) $fieldName);
        if ($fieldName === '') {
            return '1=1';
        }

        $qualifiedField = "`" . str_replace('`', '``', $fieldName) . "`";
        $reportType = isset($state['report_type']) ? $state['report_type'] : 'daily';

        if ($reportType === 'monthly') {
            $safeMonth = mysqli_real_escape_string($conn, (string) $state['report_month']);
            return "DATE_FORMAT(" . $qualifiedField . ", '%Y-%m') = '" . $safeMonth . "'";
        }

        if ($reportType === 'yearly') {
            $safeYear = mysqli_real_escape_string($conn, (string) $state['report_year']);
            return "YEAR(" . $qualifiedField . ") = '" . $safeYear . "'";
        }

        $safeDate = mysqli_real_escape_string($conn, (string) $state['report_date']);
        return "DATE(" . $qualifiedField . ") = '" . $safeDate . "'";
    }
}

if (!function_exists('orderReportLoadLookupMap')) {
    function orderReportLoadLookupMap($conn, $tableName, $labelField = 'name')
    {
        $map = array();
        if (!($conn instanceof mysqli) || !defined($tableName) && $tableName === '') {
            return $map;
        }

        if (!tableExists($tableName, $conn)) {
            return $map;
        }

        $rows = orderReportFetchRows(
            $conn,
            "SELECT `id`, `" . str_replace('`', '``', $labelField) . "` AS `label` FROM `" . $tableName . "` WHERE `status` = 'A' ORDER BY `" . str_replace('`', '``', $labelField) . "` ASC"
        );

        foreach ($rows as $row) {
            $map[(string) ((int) $row['id'])] = isset($row['label']) ? (string) $row['label'] : '';
        }

        return $map;
    }
}

if (!function_exists('orderReportBuildReferenceMaps')) {
    function orderReportBuildReferenceMaps($connect, $financeConnect)
    {
        $brandMap = array();
        if (defined('BRAND') && tableExists(BRAND, $connect)) {
            $brandRows = orderReportFetchRows($connect, "SELECT `id`, `name` FROM `" . BRAND . "` WHERE `status` = 'A' ORDER BY `name` ASC");
            foreach ($brandRows as $row) {
                $brandMap[(string) ((int) $row['id'])] = isset($row['name']) ? (string) $row['name'] : '';
            }
        }

        $packageMap = array();
        if (defined('PKG') && tableExists(PKG, $connect)) {
            $packageRows = orderReportFetchRows($connect, "SELECT `id`, `name` FROM `" . PKG . "` WHERE `status` = 'A' ORDER BY `name` ASC");
            foreach ($packageRows as $row) {
                $packageMap[(string) ((int) $row['id'])] = isset($row['name']) ? (string) $row['name'] : '';
            }
        }

        $paymentMap = array();
        if (defined('FIN_PAY_METH') && tableExists(FIN_PAY_METH, $financeConnect)) {
            $paymentRows = orderReportFetchRows($financeConnect, "SELECT `id`, `name` FROM `" . FIN_PAY_METH . "` WHERE `status` = 'A' ORDER BY `name` ASC");
            foreach ($paymentRows as $row) {
                $paymentMap[(string) ((int) $row['id'])] = isset($row['name']) ? (string) $row['name'] : '';
            }
        }

        return array(
            'brand_map' => $brandMap,
            'package_map' => $packageMap,
            'payment_map' => $paymentMap,
            'warehouse_map' => shopeeOmsLoadWarehouseNameMap($connect),
            'default_warehouse_id' => shopeeOmsGetDefaultWarehouseId($connect),
        );
    }
}

if (!function_exists('orderReportResolveOptionLabel')) {
    function orderReportResolveOptionLabel($rawValue, $lookupMap = array())
    {
        $rawValue = trim((string) $rawValue);
        if ($rawValue === '') {
            return '';
        }

        if (isset($lookupMap[$rawValue]) && trim((string) $lookupMap[$rawValue]) !== '') {
            return (string) $lookupMap[$rawValue];
        }

        return $rawValue;
    }
}

if (!function_exists('orderReportResolvePackageOptionsFromRow')) {
    function orderReportResolvePackageOptionsFromRow($connect, $platform, $orderRow, $packageMap)
    {
        $packageOptions = array();
        foreach ((array) customerLabelResolvePackageRows($connect, $platform, $orderRow) as $packageRow) {
            $packageId = isset($packageRow['package_id']) ? (int) $packageRow['package_id'] : 0;
            if ($packageId <= 0) {
                continue;
            }

            $rawValue = (string) $packageId;
            $label = isset($packageMap[$rawValue]) && trim((string) $packageMap[$rawValue]) !== ''
                ? (string) $packageMap[$rawValue]
                : (isset($packageRow['package_name']) ? trim((string) $packageRow['package_name']) : ('Package #' . $rawValue));
            $packageOptions[$rawValue] = $label;
        }

        return $packageOptions;
    }
}

if (!function_exists('orderReportBuildCustomerContext')) {
    function orderReportBuildCustomerContext($connect, $financeConnect, $platform, $orderRows)
    {
        $context = array(
            'customer_indexes' => array(
                'rows_by_id' => array(),
                'lookup' => array(),
                'composite' => array(),
            ),
            'customer_label_map' => array(),
        );

        $platformConfig = customerLabelGetPlatformConfig($platform);
        if (empty($platformConfig)) {
            return $context;
        }

        $seriesLookup = customerLabelGetSeriesLookup($connect);
        $customerConn = isset($platformConfig['customer_db']) && $platformConfig['customer_db'] === 'finance' ? $financeConnect : $connect;
        if (!($customerConn instanceof mysqli)) {
            return $context;
        }

        $customerRows = customerLabelFetchActiveRows($customerConn, $platformConfig['customer_table']);
        $customerIndexes = customerLabelBuildCustomerIndexes($platform, $customerRows, $seriesLookup);
        $customerIds = array();

        foreach ((array) $orderRows as $orderRow) {
            $customerId = customerLabelResolveOrderCustomerId($platform, $orderRow, $customerIndexes);
            if ($customerId > 0) {
                $customerIds[$customerId] = $customerId;
            }
        }

        $context['customer_indexes'] = $customerIndexes;
        $context['customer_label_map'] = customerLabelGetCustomerLabelMap($connect, $platform, array_values($customerIds));
        return $context;
    }
}

if (!function_exists('orderReportBuildSqlList')) {
    function orderReportBuildSqlList($conn, $values)
    {
        $safeValues = array();
        foreach ((array) $values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if (ctype_digit($value)) {
                $safeValues[] = (string) ((int) $value);
            } else {
                $safeValues[] = "'" . mysqli_real_escape_string($conn, $value) . "'";
            }
        }

        return array_values(array_unique($safeValues));
    }
}

if (!function_exists('orderReportBuildFieldFilterSql')) {
    function orderReportBuildFieldFilterSql($conn, $fieldName, $values, $allowCsv = false)
    {
        $fieldName = trim((string) $fieldName);
        $safeValues = orderReportBuildSqlList($conn, $values);
        if ($fieldName === '' || empty($safeValues)) {
            return '';
        }

        $qualifiedField = "`" . str_replace('`', '``', $fieldName) . "`";
        $parts = array($qualifiedField . ' IN (' . implode(',', $safeValues) . ')');

        if ($allowCsv) {
            foreach ((array) $values as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $parts[] = "FIND_IN_SET('" . mysqli_real_escape_string($conn, $value) . "', " . $qualifiedField . ") > 0";
            }
        }

        return '(' . implode(' OR ', array_values(array_unique($parts))) . ')';
    }
}

if (!function_exists('orderReportBuildBaseQuery')) {
    function orderReportBuildBaseQuery($conn, $tableName, $dateWhereSql, $extraConditions = array())
    {
        $conditions = array("`status` = 'A'");
        if (trim((string) $dateWhereSql) !== '') {
            $conditions[] = $dateWhereSql;
        }

        foreach ((array) $extraConditions as $condition) {
            $condition = trim((string) $condition);
            if ($condition !== '') {
                $conditions[] = $condition;
            }
        }

        return "SELECT * FROM `" . str_replace('`', '``', $tableName) . "` WHERE " . implode(' AND ', $conditions) . " ORDER BY `id` DESC";
    }
}

if (!function_exists('orderReportBuildOptionSets')) {
    function orderReportBuildOptionSets($connect, $platformConfig, $rows, $referenceMaps, $connectForOrders)
    {
        $optionSets = array(
            'package' => array(),
            'brand' => array(),
            'warehouse' => array(),
            'payment' => array(),
        );

        $brandMap = isset($referenceMaps['brand_map']) ? $referenceMaps['brand_map'] : array();
        $paymentMap = isset($referenceMaps['payment_map']) ? $referenceMaps['payment_map'] : array();
        $warehouseMap = isset($referenceMaps['warehouse_map']) ? $referenceMaps['warehouse_map'] : array();
        $defaultWarehouseId = isset($referenceMaps['default_warehouse_id']) ? (int) $referenceMaps['default_warehouse_id'] : 0;

        foreach ((array) $rows as $row) {
            foreach (orderReportResolvePackageOptionsFromRow($connect, $platformConfig['platform'], $row, isset($referenceMaps['package_map']) ? $referenceMaps['package_map'] : array()) as $rawValue => $label) {
                $optionSets['package'][$rawValue] = $label;
            }

            $brandField = isset($platformConfig['brand_field']) ? (string) $platformConfig['brand_field'] : '';
            $brandRaw = $brandField !== '' && isset($row[$brandField]) ? trim((string) $row[$brandField]) : '';
            if ($brandRaw !== '') {
                $optionSets['brand'][$brandRaw] = orderReportResolveOptionLabel($brandRaw, $brandMap);
            }

            $warehouseField = isset($platformConfig['warehouse_field']) ? (string) $platformConfig['warehouse_field'] : '';
            $warehouseRaw = $warehouseField !== '' && isset($row[$warehouseField]) ? trim((string) $row[$warehouseField]) : '';
            $resolvedWarehouseId = shopeeOmsResolveStockOutWarehouseId($connect, $warehouseRaw, $defaultWarehouseId);
            if ($resolvedWarehouseId > 0) {
                $optionSets['warehouse'][(string) $resolvedWarehouseId] = shopeeOmsResolveWarehouseNameById($connect, $resolvedWarehouseId, $defaultWarehouseId, $warehouseMap);
            }

            $paymentField = isset($platformConfig['payment_field']) ? (string) $platformConfig['payment_field'] : '';
            $paymentRaw = $paymentField !== '' && isset($row[$paymentField]) ? trim((string) $row[$paymentField]) : '';
            if ($paymentRaw !== '') {
                $optionSets['payment'][$paymentRaw] = orderReportResolveOptionLabel($paymentRaw, $paymentMap);
            }
        }

        foreach ($optionSets as $key => $set) {
            asort($set, SORT_NATURAL | SORT_FLAG_CASE);
            $optionSets[$key] = $set;
        }

        return $optionSets;
    }
}

if (!function_exists('orderReportSanitizeSelections')) {
    function orderReportSanitizeSelections($selectedValues, $allowedMap)
    {
        $sanitized = array();
        foreach ((array) $selectedValues as $value) {
            $value = trim((string) $value);
            if ($value !== '' && isset($allowedMap[$value])) {
                $sanitized[$value] = $value;
            }
        }

        return array_values($sanitized);
    }
}

if (!function_exists('orderReportResolveLabelNames')) {
    function orderReportResolveLabelNames($labelMeta)
    {
        return customerRecordExtractLabelNames($labelMeta);
    }
}

if (!function_exists('orderReportRenderPillList')) {
    function orderReportRenderPillList($values)
    {
        $values = array_values(array_filter(array_map('trim', (array) $values)));

        if (empty($values)) {
            return '-';
        }

        $html = '<div class="customer-tag-table-badge-group">';
        foreach ($values as $value) {
            $html .= '<span class="customer-tag-table-badge">' . orderReportEscape($value) . '</span>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('orderReportRenderCustomerLabelCell')) {
    function orderReportRenderCustomerLabelCell($row)
    {
        $labelNames = isset($row['customer_label_names']) ? (array) $row['customer_label_names'] : array();
        return orderReportRenderPillList($labelNames);
    }
}

if (!function_exists('orderReportRenderCustomerTypeLabelCell')) {
    function orderReportRenderCustomerTypeLabelCell($row, $labelType, $fallbackKey)
    {
        $labelMeta = isset($row['customer_label_meta']) ? (array) $row['customer_label_meta'] : array();

        if (isset($labelMeta[$labelType]) && function_exists('customerLabelRenderBadge')) {
            $badgeHtml = customerLabelRenderBadge($labelMeta[$labelType]);
            if (trim((string) $badgeHtml) !== '') {
                return '<div class="customer-label-summary-wrap">' . $badgeHtml . '</div>';
            }
        }

        $fallbackValue = isset($row[$fallbackKey]) ? trim((string) $row[$fallbackKey]) : '';
        return $fallbackValue !== '' ? orderReportRenderPillList(array($fallbackValue)) : '-';
    }
}

if (!function_exists('orderReportBuildRowMeta')) {
    function orderReportBuildRowMeta($connect, $financeConnect, $platformConfig, $rows, $referenceMaps)
    {
        $context = orderReportBuildCustomerContext($connect, $financeConnect, $platformConfig['platform'], $rows);
        $customerIndexes = isset($context['customer_indexes']) ? $context['customer_indexes'] : array();
        $customerLabelMap = isset($context['customer_label_map']) ? $context['customer_label_map'] : array();
        $brandMap = isset($referenceMaps['brand_map']) ? $referenceMaps['brand_map'] : array();
        $paymentMap = isset($referenceMaps['payment_map']) ? $referenceMaps['payment_map'] : array();
        $warehouseMap = isset($referenceMaps['warehouse_map']) ? $referenceMaps['warehouse_map'] : array();
        $defaultWarehouseId = isset($referenceMaps['default_warehouse_id']) ? (int) $referenceMaps['default_warehouse_id'] : 0;
        $packageMap = isset($referenceMaps['package_map']) ? $referenceMaps['package_map'] : array();
        $enrichedRows = array();

        foreach ((array) $rows as $row) {
            if (customerLabelIsExcludedOrder($row)) {
                continue;
            }

            $customerId = customerLabelResolveOrderCustomerId($platformConfig['platform'], $row, $customerIndexes);
            $labelMeta = $customerId > 0 && isset($customerLabelMap[$customerId]) ? $customerLabelMap[$customerId] : array();
            $labelNames = orderReportResolveLabelNames($labelMeta);
            $segmentationName = isset($labelMeta['segmentation']['name']) ? trim((string) $labelMeta['segmentation']['name']) : '';
            $levelName = isset($labelMeta['level']['name']) ? trim((string) $labelMeta['level']['name']) : '';
            $repeatName = isset($labelMeta['repeat']['name']) ? trim((string) $labelMeta['repeat']['name']) : '';

            $packageOptions = orderReportResolvePackageOptionsFromRow($connect, $platformConfig['platform'], $row, $packageMap);
            $packageLabels = array_values($packageOptions);
            $packageIds = array_keys($packageOptions);
            $brandField = isset($platformConfig['brand_field']) ? (string) $platformConfig['brand_field'] : '';
            $brandRaw = $brandField !== '' && isset($row[$brandField]) ? trim((string) $row[$brandField]) : '';
            $brandName = orderReportResolveOptionLabel($brandRaw, $brandMap);

            $paymentField = isset($platformConfig['payment_field']) ? (string) $platformConfig['payment_field'] : '';
            $paymentRaw = $paymentField !== '' && isset($row[$paymentField]) ? trim((string) $row[$paymentField]) : '';
            $paymentName = orderReportResolveOptionLabel($paymentRaw, $paymentMap);

            $warehouseId = shopeeOmsResolveStockOutWarehouseId($connect, $row, $defaultWarehouseId);
            $warehouseName = $warehouseId > 0 ? shopeeOmsResolveWarehouseNameById($connect, $warehouseId, $defaultWarehouseId, $warehouseMap) : '';
            $customerDisplayName = function_exists('shopeeOmsGetOrderCustomerNameText')
                ? trim((string) shopeeOmsGetOrderCustomerNameText($connect, $financeConnect, $row, $platformConfig['platform']))
                : '';
            $customerNameField = isset($platformConfig['customer_name_field']) ? (string) $platformConfig['customer_name_field'] : '';
            if ($customerDisplayName === '' && $customerNameField !== '' && isset($row[$customerNameField])) {
                $customerDisplayName = trim((string) $row[$customerNameField]);
            }
            $customerNameHtml = $customerDisplayName !== '' ? customerLabelRenderNameCell($customerDisplayName, $labelMeta) : '-';

            $detailUrl = function_exists('shopeeOmsGetOrderSourceViewUrl')
                ? shopeeOmsGetOrderSourceViewUrl($platformConfig['platform'], (int) $row['id'])
                : '';
            if ($detailUrl === '' && trim((string) ($platformConfig['detail_url'] ?? '')) !== '') {
                $detailUrl = rtrim((string) SITEURL, '/') . (string) $platformConfig['detail_url'] . '?id=' . (int) $row['id'];
            }
            $orderCodeField = isset($platformConfig['order_code_field']) ? (string) $platformConfig['order_code_field'] : '';
            $orderCode = $orderCodeField !== '' && isset($row[$orderCodeField]) && trim((string) $row[$orderCodeField]) !== ''
                ? trim((string) $row[$orderCodeField])
                : (strtoupper((string) $platformConfig['fallback_code_prefix']) . '-' . (int) $row['id']);

            $dateField = isset($platformConfig['date_field']) ? (string) $platformConfig['date_field'] : '';
            $timeField = isset($platformConfig['time_field']) ? (string) $platformConfig['time_field'] : '';
            $dateValue = $dateField !== '' && isset($row[$dateField]) ? trim((string) $row[$dateField]) : '';
            $timeValue = $timeField !== '' && isset($row[$timeField]) ? trim((string) $row[$timeField]) : '';
            $statusField = isset($platformConfig['status_field']) ? (string) $platformConfig['status_field'] : 'order_status';

            $metrics = orderReportResolveMetrics($platformConfig, $row);
            $enrichedRows[] = array(
                'row' => $row,
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'order_code' => $orderCode,
                'date_value' => $dateValue,
                'time_value' => $timeValue,
                'detail_url' => $detailUrl !== '' ? $detailUrl : '#',
                'customer_id' => $customerId,
                'customer_name' => $customerDisplayName,
                'customer_name_html' => $customerNameHtml,
                'customer_label_meta' => $labelMeta,
                'customer_label_names' => $labelNames,
                'customer_label_text' => empty($labelNames) ? '' : implode(', ', $labelNames),
                'segmentation_name' => $segmentationName,
                'level_name' => $levelName,
                'repeat_name' => $repeatName,
                'package_ids' => $packageIds,
                'package_names' => $packageLabels,
                'package_text' => empty($packageLabels) ? '' : implode(', ', $packageLabels),
                'brand_raw' => $brandRaw,
                'brand_name' => $brandName,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouseName,
                'payment_raw' => $paymentRaw,
                'payment_name' => $paymentName,
                'status_label' => getMarketplaceRequestStatusLabel(isset($row[$statusField]) ? $row[$statusField] : ''),
                'metrics' => $metrics,
            );
        }

        return array(
            'rows' => $enrichedRows,
            'customer_context' => $context,
        );
    }
}

if (!function_exists('orderReportResolveMetrics')) {
    function orderReportResolveMetrics($platformConfig, $row)
    {
        $finalAmount = isset($platformConfig['final_amount_field']) && $platformConfig['final_amount_field'] !== ''
            ? orderReportSafeFloat(isset($row[$platformConfig['final_amount_field']]) ? $row[$platformConfig['final_amount_field']] : 0)
            : 0.0;
        $voucher = isset($platformConfig['voucher_field']) && $platformConfig['voucher_field'] !== ''
            ? orderReportSafeFloat(isset($row[$platformConfig['voucher_field']]) ? $row[$platformConfig['voucher_field']] : 0)
            : 0.0;
        $serviceFee = isset($platformConfig['service_fee_field']) && $platformConfig['service_fee_field'] !== ''
            ? orderReportSafeFloat(isset($row[$platformConfig['service_fee_field']]) ? $row[$platformConfig['service_fee_field']] : 0)
            : 0.0;
        $transactionFee = isset($platformConfig['transaction_fee_field']) && $platformConfig['transaction_fee_field'] !== ''
            ? orderReportSafeFloat(isset($row[$platformConfig['transaction_fee_field']]) ? $row[$platformConfig['transaction_fee_field']] : 0)
            : 0.0;
        $awsCommissionFee = isset($platformConfig['aws_commission_fee_field']) && $platformConfig['aws_commission_fee_field'] !== ''
            ? orderReportSafeFloat(isset($row[$platformConfig['aws_commission_fee_field']]) ? $row[$platformConfig['aws_commission_fee_field']] : 0)
            : 0.0;
        $chargesAndFees = isset($platformConfig['charges_and_fees_field']) && $platformConfig['charges_and_fees_field'] !== ''
            ? orderReportSafeFloat(isset($row[$platformConfig['charges_and_fees_field']]) ? $row[$platformConfig['charges_and_fees_field']] : 0)
            : 0.0;

        return array(
            'order_count' => 1,
            'final_amount' => $finalAmount,
            'voucher' => $voucher,
            'service_fee' => $serviceFee,
            'transaction_fee' => $transactionFee,
            'aws_commission_fee' => $awsCommissionFee,
            'charges_and_fees' => $chargesAndFees,
            'final_commission_fees' => $serviceFee + $transactionFee + $awsCommissionFee + $chargesAndFees,
        );
    }
}

if (!function_exists('orderReportBuildLabelOptionSets')) {
    function orderReportBuildLabelOptionSets($enrichedRows)
    {
        $optionSets = array(
            'customer_label' => array(),
            'segmentation' => array(),
            'level' => array(),
            'repeat' => array(),
        );

        foreach ((array) $enrichedRows as $row) {
            foreach ((array) (isset($row['customer_label_names']) ? $row['customer_label_names'] : array()) as $labelName) {
                $labelName = trim((string) $labelName);
                if ($labelName !== '') {
                    $optionSets['customer_label'][$labelName] = $labelName;
                }
            }

            foreach (array('segmentation', 'level', 'repeat') as $key) {
                $value = isset($row[$key . '_name']) ? trim((string) $row[$key . '_name']) : '';
                if ($value !== '') {
                    $optionSets[$key][$value] = $value;
                }
            }
        }

        foreach ($optionSets as $key => $set) {
            asort($set, SORT_NATURAL | SORT_FLAG_CASE);
            $optionSets[$key] = $set;
        }

        return $optionSets;
    }
}

if (!function_exists('orderReportRowMatchesLabels')) {
    function orderReportRowMatchesLabels($row, $selectedFilters)
    {
        $labelSelections = isset($selectedFilters['customer_label']) ? (array) $selectedFilters['customer_label'] : array();
        if (!empty($labelSelections)) {
            $rowLabels = array_values(array_map('strtolower', (array) (isset($row['customer_label_names']) ? $row['customer_label_names'] : array())));
            $matched = false;
            foreach ($labelSelections as $labelName) {
                if (in_array(strtolower((string) $labelName), $rowLabels, true)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        foreach (array('segmentation', 'level', 'repeat') as $key) {
            $selectedValues = isset($selectedFilters[$key]) ? (array) $selectedFilters[$key] : array();
            if (empty($selectedValues)) {
                continue;
            }

            $rowValue = strtolower(trim((string) (isset($row[$key . '_name']) ? $row[$key . '_name'] : '')));
            if ($rowValue === '' || !in_array($rowValue, array_map('strtolower', $selectedValues), true)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('orderReportApplyLabelFilters')) {
    function orderReportApplyLabelFilters($enrichedRows, $selectedFilters)
    {
        $filteredRows = array();
        foreach ((array) $enrichedRows as $row) {
            if (orderReportRowMatchesLabels($row, $selectedFilters)) {
                $filteredRows[] = $row;
            }
        }

        return $filteredRows;
    }
}

if (!function_exists('orderReportApplyResolvedScalarFilters')) {
    function orderReportApplyResolvedScalarFilters($enrichedRows, $selectedFilters)
    {
        $packageSelections = array_values(array_map('strval', (array) ($selectedFilters['package'] ?? array())));
        $warehouseSelections = array_values(array_map('strval', (array) ($selectedFilters['warehouse'] ?? array())));
        if (empty($packageSelections) && empty($warehouseSelections)) {
            return array_values((array) $enrichedRows);
        }

        $filteredRows = array();
        foreach ((array) $enrichedRows as $row) {
            if (!empty($packageSelections)) {
                $rowPackageIds = array_values(array_map('strval', (array) ($row['package_ids'] ?? array())));
                if (empty(array_intersect($packageSelections, $rowPackageIds))) {
                    continue;
                }
            }

            if (!empty($warehouseSelections)) {
                $warehouseId = (string) ((int) ($row['warehouse_id'] ?? 0));
                if ($warehouseId === '0' || !in_array($warehouseId, $warehouseSelections, true)) {
                    continue;
                }
            }

            $filteredRows[] = $row;
        }

        return $filteredRows;
    }
}

if (!function_exists('orderReportSumMetrics')) {
    function orderReportSumMetrics($rows)
    {
        $totals = array(
            'order_count' => 0,
            'final_amount' => 0.0,
            'voucher' => 0.0,
            'service_fee' => 0.0,
            'transaction_fee' => 0.0,
            'aws_commission_fee' => 0.0,
            'charges_and_fees' => 0.0,
            'final_commission_fees' => 0.0,
        );

        foreach ((array) $rows as $row) {
            $metrics = isset($row['metrics']) ? (array) $row['metrics'] : array();
            foreach ($totals as $key => $value) {
                $totals[$key] += isset($metrics[$key]) ? (float) $metrics[$key] : 0.0;
            }
        }

        return $totals;
    }
}

if (!function_exists('orderReportInitBreakdownRow')) {
    function orderReportInitBreakdownRow()
    {
        return array(
            'order_count' => 0,
            'final_amount' => 0.0,
            'voucher' => 0.0,
            'service_fee' => 0.0,
            'transaction_fee' => 0.0,
            'aws_commission_fee' => 0.0,
            'charges_and_fees' => 0.0,
            'final_commission_fees' => 0.0,
        );
    }
}

if (!function_exists('orderReportAccumulateBreakdown')) {
    function orderReportAccumulateBreakdown(&$breakdownMap, $groupKey, $metrics)
    {
        $groupKey = trim((string) $groupKey);
        if ($groupKey === '') {
            $groupKey = 'Unassigned';
        }

        if (!isset($breakdownMap[$groupKey])) {
            $breakdownMap[$groupKey] = orderReportInitBreakdownRow();
        }

        foreach ($breakdownMap[$groupKey] as $key => $value) {
            $breakdownMap[$groupKey][$key] += isset($metrics[$key]) ? (float) $metrics[$key] : 0.0;
        }
    }
}

if (!function_exists('orderReportBuildBreakdowns')) {
    function orderReportBuildBreakdowns($rows)
    {
        $breakdowns = array(
            'package' => array(),
            'brand' => array(),
            'warehouse' => array(),
            'payment' => array(),
            'customer_label' => array(),
            'segmentation' => array(),
            'level' => array(),
            'repeat' => array(),
        );

        foreach ((array) $rows as $row) {
            $metrics = isset($row['metrics']) ? (array) $row['metrics'] : array();
            $packageNames = isset($row['package_names']) ? (array) $row['package_names'] : array();
            if (empty($packageNames)) {
                orderReportAccumulateBreakdown($breakdowns['package'], 'Unassigned', $metrics);
            } else {
                foreach ($packageNames as $packageName) {
                    orderReportAccumulateBreakdown($breakdowns['package'], $packageName, $metrics);
                }
            }

            orderReportAccumulateBreakdown($breakdowns['brand'], isset($row['brand_name']) ? $row['brand_name'] : '', $metrics);
            orderReportAccumulateBreakdown($breakdowns['warehouse'], isset($row['warehouse_name']) ? $row['warehouse_name'] : '', $metrics);
            orderReportAccumulateBreakdown($breakdowns['payment'], isset($row['payment_name']) ? $row['payment_name'] : '', $metrics);

            $labelNames = isset($row['customer_label_names']) ? (array) $row['customer_label_names'] : array();
            if (empty($labelNames)) {
                orderReportAccumulateBreakdown($breakdowns['customer_label'], 'Unassigned', $metrics);
            } else {
                foreach ($labelNames as $labelName) {
                    orderReportAccumulateBreakdown($breakdowns['customer_label'], $labelName, $metrics);
                }
            }

            orderReportAccumulateBreakdown($breakdowns['segmentation'], isset($row['segmentation_name']) ? $row['segmentation_name'] : '', $metrics);
            orderReportAccumulateBreakdown($breakdowns['level'], isset($row['level_name']) ? $row['level_name'] : '', $metrics);
            orderReportAccumulateBreakdown($breakdowns['repeat'], isset($row['repeat_name']) ? $row['repeat_name'] : '', $metrics);
        }

        foreach ($breakdowns as $dimension => $rowsByGroup) {
            uasort($rowsByGroup, function ($left, $right) {
                $leftAmount = isset($left['final_amount']) ? (float) $left['final_amount'] : 0.0;
                $rightAmount = isset($right['final_amount']) ? (float) $right['final_amount'] : 0.0;
                if ($leftAmount === $rightAmount) {
                    return ($right['order_count'] ?? 0) <=> ($left['order_count'] ?? 0);
                }

                return $rightAmount <=> $leftAmount;
            });
            $breakdowns[$dimension] = $rowsByGroup;
        }

        return $breakdowns;
    }
}

if (!function_exists('orderReportBuildTrendChartData')) {
    function orderReportBuildTrendChartData($rows, $state)
    {
        $reportType = isset($state['report_type']) ? (string) $state['report_type'] : 'daily';
        $labels = array();
        $salesSeries = array();
        $orderSeries = array();
        $indexMap = array();

        if ($reportType === 'monthly') {
            $monthDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) $state['report_month'] . '-01');
            if (!($monthDate instanceof DateTimeImmutable)) {
                $monthDate = new DateTimeImmutable('first day of this month');
            }
            $daysInMonth = (int) $monthDate->format('t');
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $key = sprintf('%02d', $day);
                $indexMap[$key] = count($labels);
                $labels[] = $key;
                $salesSeries[] = 0;
                $orderSeries[] = 0;
            }
        } elseif ($reportType === 'yearly') {
            for ($month = 1; $month <= 12; $month++) {
                $key = sprintf('%02d', $month);
                $indexMap[$key] = count($labels);
                $labels[] = date('M', mktime(0, 0, 0, $month, 1, (int) $state['report_year']));
                $salesSeries[] = 0;
                $orderSeries[] = 0;
            }
        } else {
            $indexMap[(string) $state['report_date']] = 0;
            $labels[] = (string) $state['report_date'];
            $salesSeries[] = 0;
            $orderSeries[] = 0;
        }

        foreach ((array) $rows as $row) {
            $dateValue = isset($row['date_value']) ? trim((string) $row['date_value']) : '';
            if ($dateValue === '') {
                continue;
            }

            $timestamp = strtotime($dateValue);
            if ($timestamp === false) {
                continue;
            }

            if ($reportType === 'monthly') {
                $key = date('d', $timestamp);
            } elseif ($reportType === 'yearly') {
                $key = date('m', $timestamp);
            } else {
                $key = date('Y-m-d', $timestamp);
            }

            if (!isset($indexMap[$key])) {
                $indexMap[$key] = count($labels);
                $labels[] = $key;
                $salesSeries[] = 0;
                $orderSeries[] = 0;
            }

            $index = (int) $indexMap[$key];
            $metrics = isset($row['metrics']) ? (array) $row['metrics'] : array();
            $salesSeries[$index] += isset($metrics['final_amount']) ? (float) $metrics['final_amount'] : 0.0;
            $orderSeries[$index] += isset($metrics['order_count']) ? (int) $metrics['order_count'] : 0;
        }

        return array(
            'labels' => $labels,
            'sales' => $salesSeries,
            'orders' => $orderSeries,
        );
    }
}

if (!function_exists('orderReportBuildRankingChartData')) {
    function orderReportBuildRankingChartData($breakdowns)
    {
        $rankingData = array();
        $dimensionLabels = array(
            'package' => 'Package',
            'brand' => 'Brand',
            'warehouse' => 'Warehouse',
            'payment' => 'Buyer Payment Method',
            'customer_label' => 'Customer Label',
        );

        foreach ($dimensionLabels as $key => $label) {
            $labels = array();
            $salesValues = array();
            $orderValues = array();
            $rowsByGroup = isset($breakdowns[$key]) ? (array) $breakdowns[$key] : array();
            $count = 0;
            foreach ($rowsByGroup as $groupName => $metrics) {
                $labels[] = (string) $groupName;
                $salesValues[] = isset($metrics['final_amount']) ? round((float) $metrics['final_amount'], 2) : 0;
                $orderValues[] = isset($metrics['order_count']) ? (int) $metrics['order_count'] : 0;
                $count++;
                if ($count >= 10) {
                    break;
                }
            }

            $rankingData[$key] = array(
                'label' => $label,
                'labels' => $labels,
                'sales' => $salesValues,
                'orders' => $orderValues,
            );
        }

        return $rankingData;
    }
}

if (!function_exists('orderReportBuildBreakdownPayload')) {
    function orderReportBuildBreakdownPayload($breakdowns)
    {
        $payload = array();
        foreach ((array) $breakdowns as $dimension => $rowsByGroup) {
            $payload[$dimension] = array();
            foreach ((array) $rowsByGroup as $groupName => $metrics) {
                $payload[$dimension][] = array(
                    'group' => (string) $groupName,
                    'order_count' => isset($metrics['order_count']) ? (int) $metrics['order_count'] : 0,
                    'final_amount' => isset($metrics['final_amount']) ? round((float) $metrics['final_amount'], 2) : 0.0,
                    'voucher' => isset($metrics['voucher']) ? round((float) $metrics['voucher'], 2) : 0.0,
                    'service_fee' => isset($metrics['service_fee']) ? round((float) $metrics['service_fee'], 2) : 0.0,
                    'transaction_fee' => isset($metrics['transaction_fee']) ? round((float) $metrics['transaction_fee'], 2) : 0.0,
                    'aws_commission_fee' => isset($metrics['aws_commission_fee']) ? round((float) $metrics['aws_commission_fee'], 2) : 0.0,
                    'charges_and_fees' => isset($metrics['charges_and_fees']) ? round((float) $metrics['charges_and_fees'], 2) : 0.0,
                    'final_commission_fees' => isset($metrics['final_commission_fees']) ? round((float) $metrics['final_commission_fees'], 2) : 0.0,
                );
            }
        }

        return $payload;
    }
}

if (!function_exists('orderReportSummarizeFiltersForAudit')) {
    function orderReportSummarizeFiltersForAudit($state)
    {
        $parts = array(
            'Report Type: ' . (isset($state['report_type']) ? ucfirst((string) $state['report_type']) : 'Daily'),
        );

        if (isset($state['report_type']) && $state['report_type'] === 'monthly') {
            $parts[] = 'Month: ' . (string) $state['report_month'];
        } elseif (isset($state['report_type']) && $state['report_type'] === 'yearly') {
            $parts[] = 'Year: ' . (string) $state['report_year'];
        } else {
            $parts[] = 'Date: ' . (string) $state['report_date'];
        }

        foreach ((array) (isset($state['filters']) ? $state['filters'] : array()) as $key => $values) {
            if (empty($values)) {
                continue;
            }
            $parts[] = ucwords(str_replace('_', ' ', $key)) . ': ' . implode(', ', $values);
        }

        return implode(' | ', $parts);
    }
}

if (!function_exists('orderReportWriteAuditLog')) {
    function orderReportWriteAuditLog($connect, $pageTitle, $action, $message)
    {
        if (!($connect instanceof mysqli) || !defined('USER_ID') || !USER_ID) {
            return;
        }

        $log = array(
            'log_act' => strtolower((string) $action),
            'page' => $pageTitle,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'cdate' => date('Y-m-d'),
            'ctime' => date('H:i:s'),
            'act_msg' => $message,
            'connect' => $connect,
        );

        audit_log($log);
    }
}

if (!function_exists('orderReportBuildFilterOptionSetsFromRows')) {
    function orderReportBuildFilterOptionSetsFromRows($connect, $financeConnect, $platformConfig, $rows, $referenceMaps, $orderConn)
    {
        $scalarOptionSets = orderReportBuildOptionSets(
            $connect,
            $platformConfig,
            $rows,
            $referenceMaps,
            $orderConn
        );

        $rowMeta = orderReportBuildRowMeta($connect, $financeConnect, $platformConfig, $rows, $referenceMaps);
        $enrichedRows = isset($rowMeta['rows']) ? $rowMeta['rows'] : array();
        $labelOptionSets = orderReportBuildLabelOptionSets($enrichedRows);

        return array_merge($scalarOptionSets, $labelOptionSets);
    }
}

if (!function_exists('orderReportBuildFilterOptionSetsForState')) {
    function orderReportBuildFilterOptionSetsForState($connect, $financeConnect, $platformConfig, $orderConn, $referenceMaps, $state)
    {
        $dateWhereSql = orderReportBuildDateWhereSql($orderConn, isset($platformConfig['date_field']) ? $platformConfig['date_field'] : '', $state);
        $rows = orderReportFetchRows($orderConn, orderReportBuildBaseQuery($orderConn, $platformConfig['table'], $dateWhereSql));

        return orderReportBuildFilterOptionSetsFromRows(
            $connect,
            $financeConnect,
            $platformConfig,
            $rows,
            $referenceMaps,
            $orderConn
        );
    }
}

if (!function_exists('orderReportBuildOptionSetsByPeriod')) {
    function orderReportBuildOptionSetsByPeriod($connect, $financeConnect, $platformConfig, $orderConn, $referenceMaps, $state, $currentOptionSets = array())
    {
        $result = array(
            'daily' => array(),
            'monthly' => array(),
            'yearly' => array(),
        );

        $periodKey = orderReportGetPeriodKeyFromState($state);
        if ($periodKey === '') {
            return $result;
        }

        $reportType = isset($state['report_type']) ? orderReportNormalizeReportType($state['report_type']) : 'daily';
        if (empty($currentOptionSets)) {
            $currentOptionSets = orderReportBuildFilterOptionSetsForState(
                $connect,
                $financeConnect,
                $platformConfig,
                $orderConn,
                $referenceMaps,
                $state
            );
        }

        $result[$reportType][$periodKey] = $currentOptionSets;

        return $result;
    }
}

if (!function_exists('orderReportBuildOptionSetPayload')) {
    function orderReportBuildOptionSetPayload($optionSets)
    {
        $payload = array();
        foreach ((array) $optionSets as $filterKey => $filterOptions) {
            $payload[$filterKey] = orderReportBuildMultiSelectOptionPayload($filterOptions);
        }

        return $payload;
    }
}

if (!function_exists('orderReportBuildView')) {
    function orderReportBuildView($connect, $financeConnect, $platform, $pageTitle)
    {
        $platformConfig = orderReportGetPlatformConfig($platform);
        if (empty($platformConfig)) {
            return array();
        }

        $state = orderReportBuildState($platform);
        $orderConn = orderReportGetDbConnection($connect, $financeConnect, isset($platformConfig['db']) ? $platformConfig['db'] : 'finance');
        $referenceMaps = orderReportBuildReferenceMaps($connect, $financeConnect);

        if (isset($_GET['order_report_option_sets']) && (string) $_GET['order_report_option_sets'] === '1') {
            $requestState = orderReportBuildStateFromRequest($state);
            $requestOptionSets = orderReportBuildFilterOptionSetsForState($connect, $financeConnect, $platformConfig, $orderConn, $referenceMaps, $requestState);

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }

            echo json_encode(array(
                'report_type' => isset($requestState['report_type']) ? $requestState['report_type'] : 'daily',
                'period_key' => orderReportGetPeriodKeyFromState($requestState),
                'option_sets' => orderReportBuildOptionSetPayload($requestOptionSets),
            ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }

        $dateWhereSql = orderReportBuildDateWhereSql($orderConn, isset($platformConfig['date_field']) ? $platformConfig['date_field'] : '', $state);
        $baseRows = orderReportFetchRows($orderConn, orderReportBuildBaseQuery($orderConn, $platformConfig['table'], $dateWhereSql));
        $scalarOptionSets = orderReportBuildOptionSets($connect, $platformConfig, $baseRows, $referenceMaps, $orderConn);

        foreach (array('package', 'brand', 'warehouse', 'payment') as $key) {
            $state['filters'][$key] = orderReportSanitizeSelections(isset($state['filters'][$key]) ? $state['filters'][$key] : array(), isset($scalarOptionSets[$key]) ? $scalarOptionSets[$key] : array());
        }

        $extraConditions = array();
        if (!empty($state['filters']['brand'])) {
            $extraConditions[] = orderReportBuildFieldFilterSql($orderConn, $platformConfig['brand_field'], $state['filters']['brand']);
        }
        if (!empty($state['filters']['payment'])) {
            $extraConditions[] = orderReportBuildFieldFilterSql($orderConn, $platformConfig['payment_field'], $state['filters']['payment']);
        }

        $filteredRows = orderReportFetchRows($orderConn, orderReportBuildBaseQuery($orderConn, $platformConfig['table'], $dateWhereSql, $extraConditions));
        $rowMeta = orderReportBuildRowMeta($connect, $financeConnect, $platformConfig, $filteredRows, $referenceMaps);
        $enrichedRows = isset($rowMeta['rows']) ? $rowMeta['rows'] : array();
        $resolvedScalarRows = orderReportApplyResolvedScalarFilters($enrichedRows, $state['filters']);
        $labelOptionSets = orderReportBuildLabelOptionSets($resolvedScalarRows);

        foreach (array('customer_label', 'segmentation', 'level', 'repeat') as $key) {
            $state['filters'][$key] = orderReportSanitizeSelections(isset($state['filters'][$key]) ? $state['filters'][$key] : array(), isset($labelOptionSets[$key]) ? $labelOptionSets[$key] : array());
        }

        $combinedOptionSets = array_merge($scalarOptionSets, $labelOptionSets);
        $optionSetsByPeriod = orderReportBuildOptionSetsByPeriod($connect, $financeConnect, $platformConfig, $orderConn, $referenceMaps, $state, $combinedOptionSets);
        $finalRows = orderReportApplyLabelFilters($resolvedScalarRows, $state['filters']);

        usort($finalRows, function ($a, $b) {
            $aDateTime = trim((string) ((isset($a['date_value']) ? $a['date_value'] : '') . ' ' . (isset($a['time_value']) ? $a['time_value'] : '')));
            $bDateTime = trim((string) ((isset($b['date_value']) ? $b['date_value'] : '') . ' ' . (isset($b['time_value']) ? $b['time_value'] : '')));

            $aTime = strtotime($aDateTime);
            $bTime = strtotime($bDateTime);

            if ($aTime === false) {
                $aTime = 0;
            }

            if ($bTime === false) {
                $bTime = 0;
            }

            if ($aTime === $bTime) {
                $aId = isset($a['id']) ? (int) $a['id'] : 0;
                $bId = isset($b['id']) ? (int) $b['id'] : 0;

                return $bId <=> $aId;
            }

            return $bTime <=> $aTime;
        });

        $totals = orderReportSumMetrics($finalRows);
        $breakdowns = orderReportBuildBreakdowns($finalRows);
        $trendData = orderReportBuildTrendChartData($finalRows, $state);
        $rankingData = orderReportBuildRankingChartData($breakdowns);
        $breakdownPayload = orderReportBuildBreakdownPayload($breakdowns);

        $safeUserName = orderReportEscape(defined('USER_NAME') ? USER_NAME : '');
        $safePageTitle = orderReportEscape($pageTitle);

        orderReportWriteAuditLog(
            $connect,
            $pageTitle,
            'view',
            $safeUserName . ' viewed ' . $safePageTitle . '.'
        );

        if (!empty($state['search_requested'])) {
            orderReportWriteAuditLog(
                $connect,
                $pageTitle,
                'search',
                $safeUserName . ' searched ' . $safePageTitle . ' with filters: ' . orderReportEscape(orderReportSummarizeFiltersForAudit($state)) . '.'
            );
        }

        return array(
            'page_title' => $pageTitle,
            'platform_config' => $platformConfig,
            'state' => $state,
            'option_sets' => $combinedOptionSets,
            'option_sets_by_period' => $optionSetsByPeriod,
            'rows' => $finalRows,
            'totals' => $totals,
            'trend_data' => $trendData,
            'ranking_data' => $rankingData,
            'breakdown_payload' => $breakdownPayload,
        );
    }
}

if (!function_exists('orderReportBuildMultiSelectOptionPayload')) {
    function orderReportBuildMultiSelectOptionPayload($options)
    {
        $payload = array();
        foreach ((array) $options as $value => $label) {
            $payload[] = array(
                'value' => (string) $value,
                'label' => (string) $label,
            );
        }

        return $payload;
    }
}

if (!function_exists('orderReportRenderMultiSelect')) {
    function orderReportRenderMultiSelect($fieldKey, $label, $options, $selectedValues, $placeholder)
    {
        $optionsJson = orderReportEscape(json_encode(orderReportBuildMultiSelectOptionPayload($options)));
        $selectedJson = orderReportEscape(json_encode(array_values((array) $selectedValues)));
        $fieldId = 'order_report_' . preg_replace('/[^a-z0-9_]+/i', '_', $fieldKey);

        echo '<div class="col-lg-3 col-md-6 col-12 mb-3">';
        echo '  <label class="form-label customer-record-filter-label" for="' . orderReportEscape($fieldId) . '">Filter by ' . orderReportEscape($label) . '</label>';
        echo '  <div id="' . orderReportEscape($fieldId) . '" class="js-order-report-multiselect dropdown campaign-filter-dropdown" data-name="' . orderReportEscape($fieldKey) . '[]" data-placeholder="' . orderReportEscape($placeholder) . '" data-options="' . $optionsJson . '" data-selected="' . $selectedJson . '"></div>';
        echo '</div>';
    }
}

if (!function_exists('orderReportRenderSummaryCard')) {
    function orderReportRenderSummaryCard($label, $value)
    {
        echo '<div class="col-12 col-md-6 col-xl-3 mb-3">';
        echo '  <div class="card h-100 shadow-sm border-0 order-report-summary-card">';
        echo '      <div class="card-body">';
        echo '          <div class="order-report-summary-label">' . orderReportEscape($label) . '</div>';
        echo '          <div class="order-report-summary-value">' . orderReportEscape($value) . '</div>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';
    }
}

if (!function_exists('orderReportRenderPage')) {
    function orderReportRenderPage($view)
    {
        if (empty($view) || empty($view['platform_config'])) {
            echo '<div class="container-fluid mt-3"><div class="alert alert-danger">Unable to load the order report configuration.</div></div>';
            return;
        }

        $pageTitle = isset($view['page_title']) ? (string) $view['page_title'] : 'Order Report';
        $platformConfig = isset($view['platform_config']) ? (array) $view['platform_config'] : array();
        $state = isset($view['state']) ? (array) $view['state'] : array();
        $rows = isset($view['rows']) ? (array) $view['rows'] : array();
        $totals = isset($view['totals']) ? (array) $view['totals'] : array();
        $optionSets = isset($view['option_sets']) ? (array) $view['option_sets'] : array();
        $optionSetsByPeriod = isset($view['option_sets_by_period']) ? (array) $view['option_sets_by_period'] : array();
        $runtimeOptionSetsByPeriod = array();

        foreach ($optionSetsByPeriod as $typeKey => $periodOptionSets) {
            $runtimeOptionSetsByPeriod[$typeKey] = array();

            foreach ((array) $periodOptionSets as $periodKey => $periodFilters) {
                $runtimeOptionSetsByPeriod[$typeKey][$periodKey] = array();

                foreach ((array) $periodFilters as $filterKey => $filterOptions) {
                    $runtimeOptionSetsByPeriod[$typeKey][$periodKey][$filterKey] = orderReportBuildMultiSelectOptionPayload($filterOptions);
                }
            }
        }
        $trendData = isset($view['trend_data']) ? (array) $view['trend_data'] : array();
        $rankingData = isset($view['ranking_data']) ? (array) $view['ranking_data'] : array();
        $breakdownPayload = isset($view['breakdown_payload']) ? (array) $view['breakdown_payload'] : array();
        $tableId = isset($platformConfig['table_id']) ? (string) $platformConfig['table_id'] : 'order_report_detail_table';
        $reportType = isset($state['report_type']) ? strtolower(trim((string) $state['report_type'])) : 'daily';
        $isDailyReport = ($reportType === 'daily');

        $chartPayload = array(
            'trend' => $trendData,
            'ranking' => $rankingData,
            'breakdowns' => $breakdownPayload,
            'report_type' => $reportType,
            'table_id' => $tableId,
            'option_sets_by_period' => $runtimeOptionSetsByPeriod,
            'option_sets_endpoint' => strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'),
        );

        $chartPayloadJson = json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $chartScriptPath = rtrim((string) SITEURL, '/') . '/header/js/chart.umd.min.js';
        $reportScriptPath = rtrim((string) SITEURL, '/') . '/js/order_report.js';
        $reportScriptVersion = @filemtime(ROOT . '/js/order_report.js');
        $reportScriptUrl = $reportScriptPath . ($reportScriptVersion ? ('?v=' . $reportScriptVersion) : '');

        echo '<div class="container-fluid d-flex justify-content-center mt-3">';
        echo '  <div class="col-12 col-md-11">';
        echo '      <div class="d-flex flex-column mb-3">';
        echo '          <div class="row">';
        echo '              <p><a href="' . orderReportEscape(rtrim((string) SITEURL, '/') . '/dashboard.php') . '">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> ' . orderReportEscape($pageTitle) . '</p>';
        echo '          </div>';
        echo '          <div class="row">';
        echo '              <div class="col-12 d-flex justify-content-between flex-wrap">';
        echo '                  <h2>' . orderReportEscape($pageTitle) . '</h2>';
        echo '              </div>';
        echo '          </div>';
        echo '      </div>';

        echo '      <style>';
        echo '          .order-report-filter-card, .order-report-chart-card, .order-report-breakdown-card { border: 0; box-shadow: 0 0.125rem 0.75rem rgba(0,0,0,0.08); }';
        echo '          .order-report-summary-card { background: linear-gradient(180deg, #ffffff 0%, #f7f9fc 100%); }';
        echo '          .order-report-summary-label { font-size: 0.82rem; color: #6c757d; text-transform: none; letter-spacing: normal; margin-bottom: 0.5rem; }';
        echo '          .order-report-summary-value { font-size: 1.45rem; font-weight: 700; color: #1f2937; word-break: break-word; }';
        echo '          .order-report-filter-actions { display:flex; gap:0.5rem; align-items:end; min-height:100%; }';
        echo '          .order-report-chart-stage { position: relative; height: 320px; min-height: 320px; }';
        echo '          .order-report-chart-stage canvas { width: 100% !important; height: 100% !important; display: block; }';
        echo '          .order-report-chart-empty, .order-report-breakdown-empty { min-height: 320px; display:flex; align-items:center; justify-content:center; color:#6c757d; background:#f8f9fa; border-radius:0.75rem; }';
        echo '          .order-report-chart-toolbar { display:flex; gap:0.5rem; flex-wrap:wrap; }';
        echo '          .order-report-chart-toolbar .btn.active { color:#fff; }';
        echo '          .order-report-filter-label { font-weight: 600; }';
        echo '          .order-report-detail-table td, .order-report-detail-table th { vertical-align: top; }';
        echo '          .order-report-label-list { display:flex; flex-wrap:wrap; gap:0.35rem; min-width: 160px; }';
        echo '          .order-report-label-pill { display:inline-flex; align-items:center; padding:0.2rem 0.55rem; border-radius:999px; font-size:0.78rem; background:#eef2ff; color:#334155; }';
        echo '          .order-report-detail-link { white-space: nowrap; }';
        echo '          .order-report-filter-panel { margin-bottom: 1rem; }';
        echo '          .order-report-filter-panel.is-open { display:block; }';
        echo '          .order-report-filter-panel > form { width:100%; }';
        echo '          .order-report-filter-panel .customer-record-filter-action-row { display:flex; width:100%; align-items:center; justify-content:space-between; gap:0.75rem; }';
        echo '          .order-report-filter-panel .customer-record-filter-apply { min-width: 108px; }';
        echo '          .order-report-filter-panel .customer-record-filter-action-row .filter-reset { margin-left:auto; }';
        echo '          .order-report-filter-card label, .order-report-filter-card .btn, .order-report-summary-label, .order-report-breakdown-card th, .order-report-detail-table th { text-transform: none !important; letter-spacing: normal !important; }';
        echo '      </style>';

                echo '      <button type="button" class="btn btn-info text-white mb-4" id="orderReportFilterToggle">Show/Hide Filters</button>';

        echo '      <div id="orderReportFilterPanel" class="mb-3 campaign-filter-panel is-open order-report-filter-panel">';
        echo '          <form method="get" class="row">';
        echo '              <input type="hidden" name="search" value="1">';

        echo '              <div class="col-lg-3 col-md-6 col-12 mb-3">';
        echo '                  <label class="form-label customer-record-filter-label" for="report_type">Report Type</label>';
        echo '                  <select class="form-select" name="report_type" id="report_type">';
        foreach (array('daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly') as $value => $label) {
            $selected = (isset($state['report_type']) ? $state['report_type'] : 'daily') === $value ? ' selected' : '';
            echo '                      <option value="' . orderReportEscape($value) . '"' . $selected . '>' . orderReportEscape($label) . '</option>';
        }
        echo '                  </select>';
        echo '              </div>';

        echo '              <div class="col-lg-3 col-md-6 col-12 mb-3 js-order-report-date-field" data-report-type="daily">';
        echo '                  <label class="form-label customer-record-filter-label" for="report_date">Date</label>';
        echo '                  <input type="date" class="form-control" id="report_date" name="report_date" value="' . orderReportEscape(isset($state['report_date']) ? $state['report_date'] : '') . '">';
        echo '              </div>';

        echo '              <div class="col-lg-3 col-md-6 col-12 mb-3 js-order-report-date-field" data-report-type="monthly">';
        echo '                  <label class="form-label customer-record-filter-label" for="report_month">Month</label>';
        echo '                  <input type="month" class="form-control" id="report_month" name="report_month" value="' . orderReportEscape(isset($state['report_month']) ? $state['report_month'] : '') . '">';
        echo '              </div>';

        echo '              <div class="col-lg-3 col-md-6 col-12 mb-3 js-order-report-date-field" data-report-type="yearly">';
        echo '                  <label class="form-label customer-record-filter-label" for="report_year">Year</label>';
        echo '                  <input type="number" min="2000" max="2100" step="1" class="form-control" id="report_year" name="report_year" value="' . orderReportEscape(isset($state['report_year']) ? $state['report_year'] : '') . '">';
        echo '              </div>';

        orderReportRenderMultiSelect('package', 'Package', isset($optionSets['package']) ? $optionSets['package'] : array(), isset($state['filters']['package']) ? $state['filters']['package'] : array(), 'All Packages');
        orderReportRenderMultiSelect('brand', 'Brand', isset($optionSets['brand']) ? $optionSets['brand'] : array(), isset($state['filters']['brand']) ? $state['filters']['brand'] : array(), 'All Brands');
        orderReportRenderMultiSelect('warehouse', 'Warehouse', isset($optionSets['warehouse']) ? $optionSets['warehouse'] : array(), isset($state['filters']['warehouse']) ? $state['filters']['warehouse'] : array(), 'All Warehouses');
        orderReportRenderMultiSelect('payment', 'Buyer Payment Method', isset($optionSets['payment']) ? $optionSets['payment'] : array(), isset($state['filters']['payment']) ? $state['filters']['payment'] : array(), 'All Payment Methods');
        orderReportRenderMultiSelect('customer_label', 'Customer Label', isset($optionSets['customer_label']) ? $optionSets['customer_label'] : array(), isset($state['filters']['customer_label']) ? $state['filters']['customer_label'] : array(), 'All Customer Labels');
        orderReportRenderMultiSelect('segmentation', 'Customer Segmentation', isset($optionSets['segmentation']) ? $optionSets['segmentation'] : array(), isset($state['filters']['segmentation']) ? $state['filters']['segmentation'] : array(), 'All Segmentations');
        orderReportRenderMultiSelect('level', 'Customer Level', isset($optionSets['level']) ? $optionSets['level'] : array(), isset($state['filters']['level']) ? $state['filters']['level'] : array(), 'All Levels');
        orderReportRenderMultiSelect('repeat', 'Customer Repeat', isset($optionSets['repeat']) ? $optionSets['repeat'] : array(), isset($state['filters']['repeat']) ? $state['filters']['repeat'] : array(), 'All Repeat Labels');

        echo '              <div class="col-lg-3 col-md-6 col-12 mb-3 d-flex align-items-end">';
        echo '                  <div class="customer-record-filter-action-row">';
        echo '                      <button type="submit" class="btn btn-outline-primary filter-reset customer-record-filter-apply">Search</button>';
        echo '                      <a href="' . orderReportEscape(strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') . '?reset=1') . '" class="btn btn-outline-danger filter-reset">Reset</a>';
        echo '                  </div>';
        echo '              </div>';

        echo '          </form>';
        echo '      </div>';

        echo '      <div class="row">';
        orderReportRenderSummaryCard('Total Orders', number_format((int) ($totals['order_count'] ?? 0)));
        orderReportRenderSummaryCard('Total Sales / Final Amount', orderReportFormatAmount($totals['final_amount'] ?? 0));
        orderReportRenderSummaryCard('Total Voucher', orderReportFormatAmount($totals['voucher'] ?? 0));
        orderReportRenderSummaryCard('Total Service Fee', orderReportFormatAmount($totals['service_fee'] ?? 0));
        orderReportRenderSummaryCard('Total Transaction Fee', orderReportFormatAmount($totals['transaction_fee'] ?? 0));
        orderReportRenderSummaryCard('Total AWS Commission Fee', orderReportFormatAmount($totals['aws_commission_fee'] ?? 0));
        orderReportRenderSummaryCard('Total Charges & Fees', orderReportFormatAmount($totals['charges_and_fees'] ?? 0));
        orderReportRenderSummaryCard('Total Final Commission Fees', orderReportFormatAmount($totals['final_commission_fees'] ?? 0));
        echo '      </div>';

        if (!$isDailyReport) {
            echo '      <div class="row">';
            echo '          <div class="col-12 col-xl-6 mb-4">';
            echo '              <div class="card order-report-chart-card h-100">';
            echo '                  <div class="card-body">';
            echo '                      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">';
            echo '                          <h5 class="mb-0">Sales / Order Trend</h5>';
            echo '                          <span class="text-muted small">Responsive chart powered by local Chart.js</span>';
            echo '                      </div>';
            echo '                      <div class="order-report-chart-stage">';
            echo '                          <div id="orderReportTrendEmpty" class="order-report-chart-empty d-none">No trend data for the selected filters.</div>';
            echo '                          <canvas id="orderReportTrendChart"></canvas>';
            echo '                      </div>';
            echo '                  </div>';
            echo '              </div>';
            echo '          </div>';
            echo '          <div class="col-12 col-xl-6 mb-4">';
            echo '              <div class="card order-report-chart-card h-100">';
            echo '                  <div class="card-body">';
            echo '                      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">';
            echo '                          <h5 class="mb-0">Ranking Breakdown</h5>';
            echo '                          <div class="order-report-chart-toolbar" id="orderReportRankingToolbar">';
            foreach (array('package' => 'Package', 'brand' => 'Brand', 'warehouse' => 'Warehouse', 'payment' => 'Payment', 'customer_label' => 'Customer Labels') as $key => $label) {
                $activeClass = $key === 'package' ? ' active btn-primary' : ' btn-outline-primary';
                echo '                              <button type="button" class="btn btn-sm order-report-ranking-btn' . $activeClass . '" data-dimension="' . orderReportEscape($key) . '" style="text-transform: none !important;">' . orderReportEscape($label) . '</button>';
            }
            echo '                          </div>';
            echo '                      </div>';
            echo '                      <div class="order-report-chart-stage">';
            echo '                          <div id="orderReportRankingEmpty" class="order-report-chart-empty d-none">No ranking data for the selected filters.</div>';
            echo '                          <canvas id="orderReportRankingChart"></canvas>';
            echo '                      </div>';
            echo '                  </div>';
            echo '              </div>';
            echo '          </div>';
            echo '      </div>';
        }


                if (!$isDailyReport) {
            echo '      <div class="card order-report-breakdown-card mb-4">';
            echo '          <div class="card-body">';
            echo '              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">';
            echo '                  <h5 class="mb-0">Grouped Summary Breakdown</h5>';
            echo '                  <div class="d-flex align-items-center gap-2">';
            echo '                      <label class="small text-muted mb-0" for="orderReportBreakdownDimension">Breakdown By</label>';
            echo '                      <select class="form-select form-select-sm" id="orderReportBreakdownDimension" style="min-width: 220px;">';
            foreach (array('package' => 'Package', 'brand' => 'Brand', 'warehouse' => 'Warehouse', 'payment' => 'Buyer Payment Method', 'customer_label' => 'Customer Label', 'segmentation' => 'Customer Segmentation', 'level' => 'Customer Level', 'repeat' => 'Customer Repeat') as $key => $label) {
                echo '                          <option value="' . orderReportEscape($key) . '">' . orderReportEscape($label) . '</option>';
            }
            echo '                      </select>';
            echo '                  </div>';
            echo '              </div>';
            echo '              <div id="orderReportBreakdownEmpty" class="order-report-breakdown-empty d-none">No grouped summary is available for the selected filters.</div>';
            echo '              <div class="table-responsive">';
            echo '                  <table class="table table-striped align-middle" id="orderReportBreakdownTable">';
            echo '                      <thead>';
            echo '                          <tr>';
            echo '                              <th>Group</th>';
            echo '                              <th>Total Orders</th>';
            echo '                              <th>Total Sales / Final Amount</th>';
            echo '                              <th>Total Voucher</th>';
            echo '                              <th>Total Service Fee</th>';
            echo '                              <th>Total Transaction Fee</th>';
            echo '                              <th>Total AWS Commission Fee</th>';
            echo '                              <th>Total Charges & Fees</th>';
            echo '                              <th>Total Final Commission Fees</th>';
            echo '                          </tr>';
            echo '                      </thead>';
            echo '                      <tbody></tbody>';
            echo '                  </table>';
            echo '              </div>';
            echo '          </div>';
            echo '      </div>';
        }

        echo '      <div class="card">';
        echo '          <div class="card-body">';
        echo '              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">';
        echo '                  <h5 class="mb-0">Detail Table</h5>';
        echo '                  <span class="text-muted small">' . orderReportEscape(count($rows)) . ' matching order(s)</span>';
        echo '              </div>';
        if (empty($rows)) {
            echo '          <div class="alert alert-light border mb-0">No orders matched the selected filters.</div>';
        } else {
            echo '          <div class="table-responsive">';
            echo '              <table class="table table-striped order-report-detail-table" id="' . orderReportEscape($tableId) . '">';
            echo '                  <thead>';
            echo '                      <tr>';
            echo '                          <th>S/N</th>';
            echo '                          <th>Order ID</th>';
            echo '                          <th>Date</th>';
            echo '                          <th>Customer</th>';
            echo '                          <th>Package</th>';
            echo '                          <th>Brand</th>';
            echo '                          <th>Warehouse</th>';
            echo '                          <th>Buyer Payment Method</th>';
            echo '                          <th>Customer Label</th>';
            echo '                          <th>Customer Segmentation</th>';
            echo '                          <th>Customer Level</th>';
            echo '                          <th>Customer Repeat</th>';
            echo '                          <th>Order Status</th>';
            echo '                          <th>Final Amount</th>';
            echo '                          <th>Voucher</th>';
            echo '                          <th>Service Fee</th>';
            echo '                          <th>Transaction Fee</th>';
            echo '                          <th>AWS Commission Fee</th>';
            echo '                          <th>Charges & Fees</th>';
            echo '                          <th>Final Commission Fees</th>';
            echo '                      </tr>';
            echo '                  </thead>';
            echo '                  <tbody>';
            $counter = 1;
            foreach ($rows as $row) {
                $metrics = isset($row['metrics']) ? (array) $row['metrics'] : array();
                echo '                  <tr>';
                echo '                      <td>' . orderReportEscape($counter++) . '</td>';
                echo '                      <td><a class="order-report-detail-link" href="' . orderReportEscape(isset($row['detail_url']) ? $row['detail_url'] : '#') . '">' . orderReportEscape(isset($row['order_code']) ? $row['order_code'] : '') . '</a></td>';
                echo '                      <td>' . orderReportEscape(trim((string) ((isset($row['date_value']) ? $row['date_value'] : '') . ' ' . (isset($row['time_value']) ? $row['time_value'] : '')))) . '</td>';
                echo '                      <td>' . (isset($row['customer_name_html']) ? $row['customer_name_html'] : '-') . '</td>';
                echo '                      <td>' . orderReportEscape(isset($row['package_text']) && $row['package_text'] !== '' ? $row['package_text'] : '-') . '</td>';
                echo '                      <td>' . orderReportEscape(isset($row['brand_name']) && $row['brand_name'] !== '' ? $row['brand_name'] : '-') . '</td>';
                echo '                      <td>' . orderReportEscape(isset($row['warehouse_name']) && $row['warehouse_name'] !== '' ? $row['warehouse_name'] : '-') . '</td>';
                echo '                      <td>' . orderReportEscape(isset($row['payment_name']) && $row['payment_name'] !== '' ? $row['payment_name'] : '-') . '</td>';
                echo '                      <td>' . orderReportRenderCustomerLabelCell($row) . '</td>';
                echo '                      <td>' . orderReportRenderCustomerTypeLabelCell($row, 'segmentation', 'segmentation_name') . '</td>';
                echo '                      <td>' . orderReportRenderCustomerTypeLabelCell($row, 'level', 'level_name') . '</td>';
                echo '                      <td>' . orderReportRenderCustomerTypeLabelCell($row, 'repeat', 'repeat_name') . '</td>';
                echo '                      <td>' . orderReportEscape(isset($row['status_label']) && $row['status_label'] !== '' ? $row['status_label'] : '-') . '</td>';
                echo '                      <td>' . orderReportEscape(orderReportFormatAmount(isset($metrics['final_amount']) ? $metrics['final_amount'] : 0)) . '</td>';
                echo '                      <td>' . orderReportEscape(orderReportFormatAmount(isset($metrics['voucher']) ? $metrics['voucher'] : 0)) . '</td>';
                echo '                      <td>' . orderReportEscape(orderReportFormatAmount(isset($metrics['service_fee']) ? $metrics['service_fee'] : 0)) . '</td>';
                echo '                      <td>' . orderReportEscape(orderReportFormatAmount(isset($metrics['transaction_fee']) ? $metrics['transaction_fee'] : 0)) . '</td>';
                echo '                      <td>' . orderReportEscape(orderReportFormatAmount(isset($metrics['aws_commission_fee']) ? $metrics['aws_commission_fee'] : 0)) . '</td>';
                echo '                      <td>' . orderReportEscape(orderReportFormatAmount(isset($metrics['charges_and_fees']) ? $metrics['charges_and_fees'] : 0)) . '</td>';
                echo '                      <td>' . orderReportEscape(orderReportFormatAmount(isset($metrics['final_commission_fees']) ? $metrics['final_commission_fees'] : 0)) . '</td>';
                echo '                  </tr>';
            }
            echo '                  </tbody>';
            echo '              </table>';
            echo '          </div>';
        }
        echo '          </div>';
        echo '      </div>';
        echo '  </div>';
        echo '</div>';

        echo '<script src="' . orderReportEscape($chartScriptPath) . '"></script>';
        echo '<script src="' . orderReportEscape($reportScriptUrl) . '"></script>';
        echo '<script>';
        echo 'window.orderReportPageConfig = ' . $chartPayloadJson . ';';
        echo 'window.orderReportPageState = ' . json_encode(array('report_type' => $reportType), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
        echo '</script>';
    }
}
