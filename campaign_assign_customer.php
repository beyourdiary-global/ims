<?php
$pageTitle = "Assign Customer";
$currentPagePin = 153;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';
include_once ROOT . '/include/customer_tag.php';

$campaignId = (int) input('campaign_id');
if ($campaignId <= 0) {
    $campaignId = (int) post('campaign_id');
}

$campaign = campaignFetchCampaign($connect, $campaignId);
if (empty($campaign)) {
    echo '<script>location.href = "' . $SITEURL . '/campaign_table.php";</script>';
    exit();
}

$pinAccess = checkCurrentPin($connect, 'Campaign');
if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$canManage = isActionAllowed('Add', $pinAccess) || isActionAllowed('Edit', $pinAccess);
$csrfToken = campaignCsrfToken('assign_customer');
$backUrl = $SITEURL . '/campaign_table.php';
$pageUrl = $SITEURL . '/campaign_assign_customer.php?campaign_id=' . $campaignId;

if (!function_exists('customerRecordNormalizeFilterValues')) {
    function customerRecordNormalizeFilterValues($values)
    {
        $result = array();
        $seen = array();
        foreach ((array) $values as $value) {
            if (is_array($value)) {
                foreach (customerRecordNormalizeFilterValues($value) as $subValue) {
                    $key = strtolower($subValue);
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $result[] = $subValue;
                    }
                }
                continue;
            }

            $value = trim(preg_replace('/\s+/', ' ', (string) $value));
            if ($value === '') {
                continue;
            }

            $key = strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $value;
        }

        return $result;
    }
}

if (!function_exists('customerRecordExtractTagNames')) {
    function customerRecordExtractTagNames($tagRows)
    {
        $names = array();
        foreach ((array) $tagRows as $tagRow) {
            if (is_array($tagRow) && isset($tagRow['name'])) {
                $names[] = $tagRow['name'];
            } else if (is_string($tagRow)) {
                $names[] = $tagRow;
            }
        }

        return customerRecordNormalizeFilterValues($names);
    }
}

if (!function_exists('customerRecordExtractLabelNames')) {
    function customerRecordExtractLabelNames($labelMeta)
    {
        $names = array();
        foreach ((array) $labelMeta as $labelRow) {
            if (is_array($labelRow) && isset($labelRow['name'])) {
                $names[] = $labelRow['name'];
            } else if (is_string($labelRow)) {
                $names[] = $labelRow;
            }
        }

        return customerRecordNormalizeFilterValues($names);
    }
}

if (!function_exists('customerRecordBuildFilterDataAttributes')) {
    function customerRecordBuildFilterDataAttributes($filterValues)
    {
        $attributes = array();
        foreach ((array) $filterValues as $key => $values) {
            $cleanValues = customerRecordNormalizeFilterValues((array) $values);
            $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $key);
            $attributes[] = 'data-filter-' . $safeKey . '="' . campaignH(implode('||', $cleanValues)) . '"';
        }

        return implode(' ', $attributes);
    }
}

if (!function_exists('customerLabelRenderCollapsibleBadgeGroup')) {
    function customerLabelRenderCollapsibleBadgeGroup($items, $wrapperClass = 'customer-tag-badge-group')
    {
        $items = array_filter((array) $items, function ($item) {
            return trim((string) $item) !== '';
        });

        if (empty($items)) {
            return '';
        }

        return '<span class="' . campaignH($wrapperClass) . '">' . implode(' ', $items) . '</span>';
    }
}

function campaignAssignRenderTagRowsAsBadges($tagRows)
{
    $tagRows = is_array($tagRows) ? $tagRows : array();
    if (empty($tagRows)) {
        return '';
    }

    if (function_exists('customerTagRenderBadges')) {
        return customerTagRenderBadges($tagRows, 'customer-tag-table-badge-group', 'customer-tag-table-badge');
    }

    $items = array();
    foreach ($tagRows as $tagRow) {
        $tagName = is_array($tagRow) && isset($tagRow['name']) ? trim((string) $tagRow['name']) : trim((string) $tagRow);
        if ($tagName !== '') {
            $items[] = '<span class="customer-tag-table-badge">' . campaignH($tagName) . '</span>';
        }
    }

    return empty($items) ? '' : '<span class="customer-tag-table-badge-group">' . implode(' ', $items) . '</span>';
}

function campaignAssignOptionMatch($haystack, $needle)
{
    $needle = strtolower(trim((string) $needle));
    if ($needle === '') {
        return true;
    }

    return strpos(strtolower((string) $haystack), $needle) !== false;
}

function campaignAssignNormalizeKey($value)
{
    return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
}

function campaignAssignEmptySummary()
{
    return array(
        'last_order_date' => '',
        'total_order' => 0,
        'total_spent' => 0.00,
        'packages' => array(),
        'brands' => array(),
        'orders' => array(),
    );
}

function campaignAssignRecalculateSummary($summary)
{
    $summary = is_array($summary) ? $summary : campaignAssignEmptySummary();
    $orders = isset($summary['orders']) && is_array($summary['orders']) ? $summary['orders'] : array();

    $lastOrderDate = '';
    $totalSpent = 0.00;
    $packages = array();
    $brands = array();

    foreach ($orders as $order) {
        $totalSpent += (float) ($order['amount'] ?? 0);

        $orderDate = trim((string) ($order['date'] ?? ''));
        if ($orderDate !== '' && ($lastOrderDate === '' || $orderDate > $lastOrderDate)) {
            $lastOrderDate = $orderDate;
        }

        $packages = array_merge($packages, (array) ($order['packages'] ?? array()));
        $brands = array_merge($brands, (array) ($order['brands'] ?? array()));
    }

    $summary['last_order_date'] = $lastOrderDate;
    $summary['total_order'] = count($orders);
    $summary['total_spent'] = $totalSpent;
    $summary['packages'] = customerRecordNormalizeFilterValues($packages);
    $summary['brands'] = customerRecordNormalizeFilterValues($brands);

    return $summary;
}

function campaignAssignMergeSummary($baseSummary, $additionalSummary)
{
    $baseSummary = is_array($baseSummary) ? $baseSummary : campaignAssignEmptySummary();
    $additionalSummary = is_array($additionalSummary) ? $additionalSummary : campaignAssignEmptySummary();

    if (!isset($baseSummary['orders']) || !is_array($baseSummary['orders'])) {
        $baseSummary['orders'] = array();
    }

    $additionalOrders = isset($additionalSummary['orders']) && is_array($additionalSummary['orders']) ? $additionalSummary['orders'] : array();
    foreach ($additionalOrders as $orderKey => $orderData) {
        if (!isset($baseSummary['orders'][$orderKey])) {
            $baseSummary['orders'][$orderKey] = $orderData;
        }
    }

    return campaignAssignRecalculateSummary($baseSummary);
}

function campaignAssignCollectOptionValues(&$target, $values)
{
    foreach (customerRecordNormalizeFilterValues((array) $values) as $value) {
        $target[$value] = $value;
    }
}

function campaignAssignCustomerPayload($row)
{
    $payload = array(
        'platform' => isset($row['platform']) ? (string) $row['platform'] : '',
        'customer_id' => isset($row['customer_id']) ? (string) $row['customer_id'] : '',
        'customer_name' => isset($row['customer_name']) ? (string) $row['customer_name'] : '',
        'customer_contact' => isset($row['customer_contact']) ? (string) $row['customer_contact'] : '',
        'customer_label' => isset($row['customer_label']) ? (string) $row['customer_label'] : '',
        'customer_tags' => isset($row['customer_tags']) ? (string) $row['customer_tags'] : '',
        'last_order_date' => isset($row['last_order_date']) ? (string) $row['last_order_date'] : '',
        'total_order' => isset($row['total_order']) ? (int) $row['total_order'] : 0,
        'total_spent' => isset($row['total_spent']) ? (float) $row['total_spent'] : 0,
    );

    $json = json_encode($payload);
    return $json === false ? '' : base64_encode($json);
}

function campaignAssignDecodePayload($payload)
{
    $decodedJson = base64_decode((string) $payload, true);
    if ($decodedJson === false || $decodedJson === '') {
        return array();
    }

    $decoded = json_decode($decodedJson, true);
    return is_array($decoded) ? $decoded : array();
}

function campaignAssignPlatformConfigs($connect, $finance_connect)
{
    return array(
        'Shopee' => array(
            'conn' => $finance_connect,
            'table' => defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info',
            'platform_key' => 'shopee',
            'id_cols' => array('id'),
            'customer_id_cols' => array('buyer_username', 'id'),
            'name_cols' => array('buyer_username', 'name', 'customer_name'),
            'contact_cols' => array('contact_no', 'phone', 'contact'),
            'brand_cols' => array('brand'),
            'order_conn' => $finance_connect,
            'order_table' => defined('SHOPEE_SG_ORDER_REQ') ? SHOPEE_SG_ORDER_REQ : 'shopee_sg_order_request',
            'order_no_cols' => array('orderID', 'order_id', 'id'),
            'order_customer_cols' => array('buyer'),
            'order_date_cols' => array('date', 'order_date', 'create_date'),
            'order_amount_cols' => array('final_amt', 'price', 'total_price', 'total', 'amount'),
            'order_package_cols' => array('package', 'pkg', 'package_id'),
            'order_brand_cols' => array('brand', 'brand_id'),
        ),
        'Lazada' => array(
            'conn' => $connect,
            'table' => defined('LAZADA_CUST_RCD') ? LAZADA_CUST_RCD : 'customer_lazada_deals_transaction',
            'platform_key' => 'lazada',
            'id_cols' => array('id'),
            'customer_id_cols' => array('lcr_id', 'id'),
            'name_cols' => array('name', 'customer_name'),
            'contact_cols' => array('phone', 'contact', 'ship_rec_contact'),
            'brand_cols' => array('brand'),
            'order_conn' => $connect,
            'order_table' => defined('LAZADA_ORDER_REQ') ? LAZADA_ORDER_REQ : 'lazada_order_request',
            'order_no_cols' => array('oder_number', 'order_number', 'order_id', 'id'),
            'order_customer_cols' => array('cust_id', 'cust_name', 'cust_phone', 'ship_rec_contact'),
            'order_date_cols' => array('date', 'order_date', 'create_date', 'update_date'),
            'order_amount_cols' => array('final_income', 'item_price_credit', 'total', 'price', 'amount'),
            'order_package_cols' => array('pkg', 'package', 'package_id'),
            'order_brand_cols' => array('brand', 'brand_id'),
        ),
        'Website' => array(
            'conn' => $connect,
            'table' => defined('WEB_CUST_RCD') ? WEB_CUST_RCD : 'customer_website_deals_transaction',
            'platform_key' => 'website',
            'id_cols' => array('id'),
            'customer_id_cols' => array('cust_id', 'id'),
            'name_cols' => array('name', 'customer_name'),
            'contact_cols' => array('contact', 'phone', 'ship_rec_contact'),
            'brand_cols' => array('brand'),
            'order_conn' => $finance_connect,
            'order_table' => defined('WEB_ORDER_REQ') ? WEB_ORDER_REQ : 'website_order_request',
            'order_no_cols' => array('order_id', 'orderID', 'id'),
            'order_customer_cols' => array('cust_id', 'cust_name', 'cust_email', 'shipping_contact', 'shipping_name'),
            'order_date_cols' => array('date', 'order_date', 'create_date', 'update_date'),
            'order_amount_cols' => array('total', 'final_amt', 'final_amount', 'price', 'amount'),
            'order_package_cols' => array('pkg', 'package', 'package_id'),
            'order_brand_cols' => array('brand', 'brand_id'),
        ),
        'Facebook' => array(
            'conn' => $connect,
            'table' => defined('FB_CUST_DEALS') ? FB_CUST_DEALS : 'customer_facebook_deals_transaction',
            'platform_key' => 'facebook',
            'id_cols' => array('id'),
            'customer_id_cols' => array('id', 'name'),
            'name_cols' => array('name', 'customer_name'),
            'contact_cols' => array('contact', 'phone', 'ship_rec_contact'),
            'brand_cols' => array('brand'),
            'order_conn' => $finance_connect,
            'order_table' => defined('FB_ORDER_REQ') ? FB_ORDER_REQ : 'facebook_order_request',
            'order_no_cols' => array('order_id', 'orderID', 'id'),
            'order_customer_cols' => array('name', 'contact', 'ship_rec_contact', 'ship_rec_name'),
            'order_date_cols' => array('date', 'order_date', 'create_date', 'update_date'),
            'order_amount_cols' => array('price', 'total', 'final_amt', 'final_amount', 'amount'),
            'order_package_cols' => array('package', 'pkg', 'package_id'),
            'order_brand_cols' => array('brand', 'brand_id'),
        ),
    );
}

function campaignAssignRowValue($row, $columns)
{
    foreach ((array) $columns as $column) {
        if (isset($row[$column]) && trim((string) $row[$column]) !== '') {
            return (string) $row[$column];
        }
    }

    return '';
}

function campaignAssignNormalizeDateValue($value)
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }

    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
        return $matches[1];
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date('Y-m-d', $timestamp);
}

function campaignAssignSplitCsvValues($value)
{
    $parts = preg_split('/[,|]+/', (string) $value);
    $values = array();
    foreach ((array) $parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $values[] = $part;
        }
    }

    return $values;
}

function campaignAssignResolveLookupValues($connect, $tableName, $value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return array();
    }

    $parts = campaignAssignSplitCsvValues($value);
    if (empty($parts)) {
        return array($value);
    }

    if (!campaignTableExists($connect, $tableName) || !campaignColumnExists($connect, $tableName, 'name')) {
        return customerRecordNormalizeFilterValues($parts);
    }

    $allNumeric = true;
    foreach ($parts as $part) {
        if (!ctype_digit((string) $part)) {
            $allNumeric = false;
            break;
        }
    }

    if (!$allNumeric) {
        return customerRecordNormalizeFilterValues($parts);
    }

    $ids = array_values(array_unique(array_map('intval', $parts)));
    if (empty($ids)) {
        return array();
    }

    $rows = array();
    $sql = "SELECT `id`, `name` FROM `" . str_replace('`', '``', $tableName) . "` WHERE `id` IN (" . implode(',', $ids) . ")";
    if (campaignColumnExists($connect, $tableName, 'status')) {
        $sql .= " AND `status`='A'";
    }
    $result = mysqli_query($connect, $sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[(int) ($row['id'] ?? 0)] = trim((string) ($row['name'] ?? ''));
        }
    }

    $names = array();
    foreach ($ids as $id) {
        if (isset($rows[$id]) && $rows[$id] !== '') {
            $names[] = $rows[$id];
        }
    }

    return !empty($names) ? customerRecordNormalizeFilterValues($names) : customerRecordNormalizeFilterValues($parts);
}

function campaignAssignResolvePackageValues($connect, $value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return array();
    }

    if (function_exists('commonResolvePackageNamesFromCsv') && defined('PKG')) {
        $resolved = commonResolvePackageNamesFromCsv($value, $connect);
        if (trim((string) $resolved) !== '') {
            return customerRecordNormalizeFilterValues(campaignAssignSplitCsvValues($resolved));
        }
    }

    return defined('PKG') ? campaignAssignResolveLookupValues($connect, PKG, $value) : customerRecordNormalizeFilterValues(campaignAssignSplitCsvValues($value));
}

function campaignAssignResolveBrandValues($connect, $value)
{
    return defined('BRAND') ? campaignAssignResolveLookupValues($connect, BRAND, $value) : customerRecordNormalizeFilterValues(campaignAssignSplitCsvValues($value));
}

function campaignAssignBuildCustomerLookupMap($config)
{
    $lookupMap = array();
    $customerConn = isset($config['conn']) ? $config['conn'] : null;
    $customerTable = isset($config['table']) ? (string) $config['table'] : '';

    if (!($customerConn instanceof mysqli) || !campaignTableExists($customerConn, $customerTable)) {
        return $lookupMap;
    }

    $neededColumns = array_values(array_unique(array_filter(array_merge(
        (array) ($config['id_cols'] ?? array()),
        (array) ($config['customer_id_cols'] ?? array()),
        (array) ($config['name_cols'] ?? array()),
        (array) ($config['contact_cols'] ?? array())
    ), function ($column) use ($customerConn, $customerTable) {
        $column = trim((string) $column);
        return $column !== '' && campaignColumnExists($customerConn, $customerTable, $column);
    })));

    if (empty($neededColumns)) {
        return $lookupMap;
    }

    $selectColumns = array();
    foreach ($neededColumns as $column) {
        $selectColumns[] = "`" . str_replace('`', '``', $column) . "`";
    }

    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM `" . str_replace('`', '``', $customerTable) . "`";
    if (campaignColumnExists($customerConn, $customerTable, 'status')) {
        $sql .= " WHERE `status`='A'";
    }

    $result = mysqli_query($customerConn, $sql);
    if (!($result instanceof mysqli_result)) {
        return $lookupMap;
    }

    while ($row = $result->fetch_assoc()) {
        $rowKeys = array();
        foreach (array_merge($config['id_cols'], $config['customer_id_cols'], $config['name_cols'], $config['contact_cols']) as $column) {
            if (isset($row[$column]) && trim((string) $row[$column]) !== '') {
                $rowKeys[] = campaignAssignNormalizeKey($row[$column]);
            }
        }
        $rowKeys = array_values(array_unique(array_filter($rowKeys)));

        foreach ($rowKeys as $rowKey) {
            if ($rowKey === '') {
                continue;
            }
            if (!isset($lookupMap[$rowKey])) {
                $lookupMap[$rowKey] = array();
            }
            $lookupMap[$rowKey] = array_values(array_unique(array_merge($lookupMap[$rowKey], $rowKeys)));
        }
    }

    return $lookupMap;
}

function campaignAssignFetchActiveOrderRows($orderConn, $orderTable, $config = array())
{
    if (!($orderConn instanceof mysqli) || !campaignTableExists($orderConn, $orderTable)) {
        return array();
    }

    $neededColumns = array_values(array_unique(array_filter(array_merge(
        array('id'),
        (array) ($config['order_no_cols'] ?? array()),
        (array) ($config['order_customer_cols'] ?? array()),
        (array) ($config['order_date_cols'] ?? array()),
        (array) ($config['order_amount_cols'] ?? array()),
        (array) ($config['order_package_cols'] ?? array()),
        (array) ($config['order_brand_cols'] ?? array())
    ), function ($column) use ($orderConn, $orderTable) {
        $column = trim((string) $column);
        return $column !== '' && campaignColumnExists($orderConn, $orderTable, $column);
    })));

    if (empty($neededColumns)) {
        return array();
    }

    $selectColumns = array();
    foreach ($neededColumns as $column) {
        $selectColumns[] = "`" . str_replace('`', '``', $column) . "`";
    }

    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM `" . str_replace('`', '``', (string) $orderTable) . "`";
    if (campaignColumnExists($orderConn, $orderTable, 'status')) {
        $sql .= " WHERE `status`='A'";
    }
    if (campaignColumnExists($orderConn, $orderTable, 'id')) {
        $sql .= " ORDER BY `id` DESC";
    }

    $rows = array();
    $result = mysqli_query($orderConn, $sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function campaignAssignAddOrderToSummaryMap(&$summaryMap, $keys, $orderKey, $orderAmount, $orderDate, $packageValues, $brandValues)
{
    $keys = array_values(array_unique(array_filter(array_map('campaignAssignNormalizeKey', (array) $keys))));
    if (empty($keys) || $orderKey === '') {
        return;
    }

    $orderData = array(
        'amount' => (float) $orderAmount,
        'date' => (string) $orderDate,
        'packages' => customerRecordNormalizeFilterValues($packageValues),
        'brands' => customerRecordNormalizeFilterValues($brandValues),
    );

    foreach ($keys as $key) {
        if (!isset($summaryMap[$key])) {
            $summaryMap[$key] = campaignAssignEmptySummary();
        }

        $summaryMap[$key]['orders'][$orderKey] = $orderData;
        $summaryMap[$key] = campaignAssignRecalculateSummary($summaryMap[$key]);
    }
}

function campaignAssignBuildOrderSummaryMap($config, $connect)
{
    $summaryMap = array();
    $orderConn = isset($config['order_conn']) ? $config['order_conn'] : null;
    $orderTable = isset($config['order_table']) ? $config['order_table'] : '';
    if (!($orderConn instanceof mysqli) || !campaignTableExists($orderConn, $orderTable)) {
        return $summaryMap;
    }

    $customerLookupMap = campaignAssignBuildCustomerLookupMap($config);
    $orderRows = campaignAssignFetchActiveOrderRows($orderConn, $orderTable, $config);

    foreach ($orderRows as $order) {
        $orderId = campaignAssignRowValue($order, array('id'));
        $orderNo = campaignAssignRowValue($order, isset($config['order_no_cols']) ? $config['order_no_cols'] : array('orderID', 'order_id', 'oder_number', 'id'));
        $orderKey = strtolower((string) $orderTable) . '|' . ($orderId !== '' ? $orderId : $orderNo);
        if ($orderKey === strtolower((string) $orderTable) . '|') {
            continue;
        }

        $orderKeys = array();
        foreach ((array) ($config['order_customer_cols'] ?? array()) as $column) {
            if (isset($order[$column]) && trim((string) $order[$column]) !== '') {
                $valueKey = campaignAssignNormalizeKey($order[$column]);
                $orderKeys[] = $valueKey;
                if (isset($customerLookupMap[$valueKey])) {
                    $orderKeys = array_merge($orderKeys, $customerLookupMap[$valueKey]);
                }
            }
        }

        if (empty($orderKeys)) {
            continue;
        }

        $orderAmount = (float) campaignAssignRowValue($order, $config['order_amount_cols']);
        $orderDate = campaignAssignNormalizeDateValue(campaignAssignRowValue($order, $config['order_date_cols']));
        $packageValue = campaignAssignRowValue($order, $config['order_package_cols']);
        $brandValue = campaignAssignRowValue($order, $config['order_brand_cols']);

        campaignAssignAddOrderToSummaryMap(
            $summaryMap,
            $orderKeys,
            $orderKey,
            $orderAmount,
            $orderDate,
            campaignAssignResolvePackageValues($connect, $packageValue),
            campaignAssignResolveBrandValues($connect, $brandValue)
        );
    }

    return $summaryMap;
}

function campaignAssignUpsertCustomer($connect, $campaignId, $customer)
{
    $campaignId = (int) $campaignId;
    $platform = trim((string) ($customer['platform'] ?? ''));
    $customerId = trim((string) ($customer['customer_id'] ?? ''));
    if ($campaignId <= 0 || $platform === '' || $customerId === '') {
        return false;
    }

    $select = $connect->prepare("SELECT `id`, `status` FROM `" . CAMPAIGN_CUSTOMER . "` WHERE `campaign_id` = ? AND `platform` = ? AND `customer_id` = ? LIMIT 1");
    if (!$select) {
        return false;
    }

    $select->bind_param('iss', $campaignId, $platform, $customerId);
    $select->execute();
    $existing = $select->get_result();
    $existingRow = ($existing && $existing->num_rows > 0) ? $existing->fetch_assoc() : array();
    $select->close();

    $customerName = (string) ($customer['customer_name'] ?? '');
    $customerContact = (string) ($customer['customer_contact'] ?? '');
    $customerLabel = (string) ($customer['customer_label'] ?? '');
    $customerTags = (string) ($customer['customer_tags'] ?? '');
    $lastOrderDate = trim((string) ($customer['last_order_date'] ?? ''));
    $totalOrder = (int) ($customer['total_order'] ?? 0);
    $totalSpent = (float) ($customer['total_spent'] ?? 0);
    $userId = (string) USER_ID;
    $lastOrderDateSql = $lastOrderDate !== '' ? $lastOrderDate : null;

    if (!empty($existingRow)) {
        $id = (int) $existingRow['id'];
        $stmt = $connect->prepare("UPDATE `" . CAMPAIGN_CUSTOMER . "` SET `customer_name`=?, `customer_contact`=?, `customer_label`=?, `customer_tags`=?, `last_order_date`=?, `total_order`=?, `total_spent`=?, `assign_source`='Manual', `purchase_status`='Pending', `follow_up_status`='Pending', `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME(), `status`='A' WHERE `id`=?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sssssidsi', $customerName, $customerContact, $customerLabel, $customerTags, $lastOrderDateSql, $totalOrder, $totalSpent, $userId, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    $stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN_CUSTOMER . "` (`campaign_id`, `platform`, `customer_id`, `customer_name`, `customer_contact`, `customer_label`, `customer_tags`, `last_order_date`, `total_order`, `total_spent`, `assign_source`, `purchase_status`, `follow_up_status`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Manual', 'Pending', 'Pending', ?, CURDATE(), CURTIME(), 'A')");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('isssssssids', $campaignId, $platform, $customerId, $customerName, $customerContact, $customerLabel, $customerTags, $lastOrderDateSql, $totalOrder, $totalSpent, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function campaignAssignInsertCustomer($connect, $campaignId, $customer)
{
    return campaignAssignUpsertCustomer($connect, $campaignId, $customer);
}

if (post('actionBtn') === 'assignCustomers' || post('actionBtn') === 'removeCustomers') {
    if (!$canManage || !campaignVerifyCsrf('assign_customer', post('csrf_token'))) {
        campaignSetPopup('Unable to update campaign customers.', $pageUrl, 'ErrMO');
        echo '<script>location.href = "' . $pageUrl . '";</script>';
        exit();
    }

    if (post('actionBtn') === 'assignCustomers') {
        $selected = post('selected_customers');
        $count = 0;
        foreach ((array) $selected as $payload) {
            $customer = campaignAssignDecodePayload($payload);
            if (!empty($customer) && campaignAssignUpsertCustomer($connect, $campaignId, $customer)) {
                $count++;
            }
        }
        $syncSummary = campaignSyncFollowUpTasks($connect, $campaignId);
        campaignAudit($connect, $pageTitle, 'add', USER_NAME . " assigned " . $count . " campaign customer(s) and synced follow-up tasks (created " . (int) $syncSummary['created'] . ", updated " . (int) $syncSummary['updated'] . ", deactivated " . (int) $syncSummary['deactivated'] . ").", '', CAMPAIGN_CUSTOMER);
        campaignSetPopup('Assigned ' . $count . ' customer(s).', $pageUrl, 'ErrMO');
    } else {
        $selectedIds = array_map('intval', (array) post('assigned_customer_ids'));
        $count = 0;
        foreach ($selectedIds as $assignedId) {
            if ($assignedId <= 0) {
                continue;
            }
            $stmt = $connect->prepare("UPDATE `" . CAMPAIGN_CUSTOMER . "` SET `status`='D', `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `campaign_id`=?");
            if ($stmt) {
                $userId = (string) USER_ID;
                $stmt->bind_param('sii', $userId, $assignedId, $campaignId);
                if ($stmt->execute()) {
                    $count++;
                }
                $stmt->close();
            }
        }
        $syncSummary = campaignSyncFollowUpTasks($connect, $campaignId);
        campaignAudit($connect, $pageTitle, 'delete', USER_NAME . " removed " . $count . " campaign customer(s) and synced follow-up tasks (created " . (int) $syncSummary['created'] . ", updated " . (int) $syncSummary['updated'] . ", deactivated " . (int) $syncSummary['deactivated'] . ").", '', CAMPAIGN_CUSTOMER);
        campaignSetPopup('Removed ' . $count . ' customer(s).', $pageUrl, 'ErrMO');
    }

    echo '<script>location.href = "' . $pageUrl . '";</script>';
    exit();
}

$platformConfigs = campaignAssignPlatformConfigs($connect, isset($finance_connect) ? $finance_connect : $connect);
$assignedRows = array();
$assignedLookup = array();
$assignedResult = $connect->query("SELECT * FROM `" . CAMPAIGN_CUSTOMER . "` WHERE `campaign_id` = " . (int) $campaignId . " AND `status` = 'A' ORDER BY `id` DESC");
if ($assignedResult) {
    while ($assignedRow = $assignedResult->fetch_assoc()) {
        $assignedRows[] = $assignedRow;
        $assignedLookup[strtolower((string) ($assignedRow['platform'] ?? '')) . '||' . campaignAssignNormalizeKey($assignedRow['customer_id'] ?? '')] = true;
    }
}

$customerRows = array();
$tagFilterOptions = array();
$packageFilterOptions = array();
$brandFilterOptions = array();
$platformFilterOptions = array();

foreach ($platformConfigs as $platformName => $config) {
    $customerConn = $config['conn'];
    $customerTable = $config['table'];
    if (!($customerConn instanceof mysqli) || !campaignTableExists($customerConn, $customerTable)) {
        continue;
    }

    $customerResult = getData('*', '', '', $customerTable, $customerConn);
    $platformRows = array();
    if ($customerResult instanceof mysqli_result) {
        while ($platformRow = $customerResult->fetch_assoc()) {
            $platformRows[] = $platformRow;
        }
    }

    if (empty($platformRows)) {
        continue;
    }

    $labelData = customerLabelPrepareCustomerRows($connect, $config['platform_key'], $platformRows);
    $labelMap = isset($labelData['label_map']) ? $labelData['label_map'] : array();
    $tagMap = isset($labelData['tag_map']) ? $labelData['tag_map'] : array();
    $orderSummaryMap = campaignAssignBuildOrderSummaryMap($config, $connect);

    foreach ($platformRows as $platformRow) {
        $internalId = campaignAssignRowValue($platformRow, $config['id_cols']);
        $customerId = campaignAssignRowValue($platformRow, $config['customer_id_cols']);
        $customerName = campaignAssignRowValue($platformRow, $config['name_cols']);
        if ($customerId === '') {
            $customerId = $internalId;
        }
        if ($customerName === '') {
            $customerName = $customerId;
        }
        if ($customerId === '' || $customerName === '') {
            continue;
        }

        $tags = isset($tagMap[(int) $internalId]) ? customerRecordExtractTagNames($tagMap[(int) $internalId]) : array();
        $brandValues = array();
        $rowBrandValue = campaignAssignRowValue($platformRow, $config['brand_cols']);
        if ($rowBrandValue !== '') {
            $resolvedBrandName = campaignResolveLookupName($connect, BRAND, $rowBrandValue);
            if ($resolvedBrandName !== '') {
                $brandValues[] = $resolvedBrandName;
            }
        }

        $customerContact = campaignAssignRowValue($platformRow, $config['contact_cols']);
        $summary = campaignAssignEmptySummary();
        $summaryKeys = array_unique(array_filter(array(
            campaignAssignNormalizeKey($internalId),
            campaignAssignNormalizeKey($customerId),
            campaignAssignNormalizeKey($customerName),
            campaignAssignNormalizeKey($customerContact),
        )));
        foreach ($summaryKeys as $summaryKey) {
            if (isset($orderSummaryMap[$summaryKey])) {
                $summary = campaignAssignMergeSummary($summary, $orderSummaryMap[$summaryKey]);
            }
        }

        $brandValues = customerRecordNormalizeFilterValues(array_merge($brandValues, $summary['brands']));
        $packageValues = customerRecordNormalizeFilterValues($summary['packages']);
        $isAssigned = isset($assignedLookup[strtolower($platformName) . '||' . campaignAssignNormalizeKey($customerId)]);
        if ($isAssigned) {
            continue;
        }

        $tagText = implode(', ', $tags);
        $brandText = implode(', ', $brandValues);
        $packageText = implode(', ', $packageValues);
        $statusText = 'Unassigned';

        campaignAssignCollectOptionValues($tagFilterOptions, $tags);
        campaignAssignCollectOptionValues($packageFilterOptions, $packageValues);
        campaignAssignCollectOptionValues($brandFilterOptions, $brandValues);
        $platformFilterOptions[$platformName] = $platformName;

        $customerRows[] = array(
            'platform' => $platformName,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_contact' => $customerContact,
            'customer_label' => implode(', ', isset($labelMap[(int) $internalId]) ? customerRecordExtractLabelNames($labelMap[(int) $internalId]) : array()),
            'customer_tags' => $tagText,
            'customer_tag_values' => $tags,
            'customer_tag_rows' => isset($tagMap[(int) $internalId]) ? $tagMap[(int) $internalId] : array(),
            'brand' => $brandText,
            'brand_values' => $brandValues,
            'package' => $packageText,
            'package_values' => $packageValues,
            'last_order_date' => $summary['last_order_date'],
            'total_order' => $summary['total_order'],
            'total_spent' => number_format((float) $summary['total_spent'], 2, '.', ''),
            'status_text' => $statusText,
        );
    }
}

usort($customerRows, function ($leftRow, $rightRow) {
    $leftPlatform = strtolower((string) ($leftRow['platform'] ?? ''));
    $rightPlatform = strtolower((string) ($rightRow['platform'] ?? ''));
    if ($leftPlatform !== $rightPlatform) {
        return strcmp($leftPlatform, $rightPlatform);
    }

    return strcmp(
        strtolower((string) ($leftRow['customer_name'] ?? '')),
        strtolower((string) ($rightRow['customer_name'] ?? ''))
    );
});
ksort($tagFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
ksort($packageFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
ksort($brandFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
ksort($platformFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .campaign-filter-panel {
            display: none;
        }

        .campaign-filter-panel.is-open {
            display: flex;
        }

        .campaign-filter-dropdown .campaign-filter-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: .25rem;
            min-height: 38px;
            color: #495057;
            font-weight: 400;
        }

        .campaign-filter-dropdown .campaign-filter-toggle:focus {
            box-shadow: none;
        }

        .campaign-filter-dropdown .dropdown-menu {
            width: 100%;
            max-height: 240px;
            overflow-y: auto;
            padding: .75rem;
        }

        .campaign-filter-dropdown .form-check {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding-left: 0;
            margin-bottom: .6rem;
        }

        .campaign-filter-dropdown .form-check-input {
            flex: 0 0 auto;
            position: static;
            margin-left: 0;
            margin-top: 4px;
        }

        .campaign-filter-dropdown .form-check-label {
            flex: 1;
            line-height: 1.4;
            white-space: normal;
            word-break: break-word;
            cursor: pointer;
        }

        .campaign-filter-dropdown .form-check:last-child {
            margin-bottom: 0;
        }

        .campaign-assign-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 0.5rem;
        }

        .campaign-assign-section-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .campaign-assign-remove-btn {
            box-shadow: 0 0 !important;
            font-size: 18px;
            text-transform: none !important;
        }

        .campaign-assign-primary-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 0.5rem;
        }

        .campaign-tag-cell .customer-tag-table-badge-group,
        .campaign-tag-cell .customer-tag-badge-group {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem;
        }

        .campaign-tag-cell .customer-tag-table-badge,
        .campaign-tag-cell .customer-tag-badge {
            display: inline-flex;
            align-items: center;
            border: 1px solid #bcd3ff;
            background: #eef5ff;
            color: #2f5fb3;
            border-radius: 999px;
            padding: .18rem .55rem;
            font-size: .85rem;
            line-height: 1.2;
            margin: .08rem .15rem .08rem 0;
            white-space: nowrap;
        }
    </style>
</head>
<script>
    preloader(300);
    $(document).ready(function () {
        createSortingTable('campaign_customer_search_table', {
            searching: true,
            order: [[1, 'asc']],
            columnDefs: [
                { orderable: false, searchable: false, targets: [0] }
            ]
        });
    });
</script>
<body class="campaign-assign-page">
    <div class="pre-load-center"><div class="preloader"></div></div>
    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/campaign_table.php">Campaign</a> <i class="fa-solid fa-chevron-right fa-xs"></i> Assign Customer</p>
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <div>
                                <h2>Assign Customer</h2>
                                <?php campaignRenderBadge($campaign); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 mb-3 customer-record-filter-toolbar-row">
                    <div class="customer-record-filter-toolbar">
                        <button type="button" class="btn btn-info customer-record-filter-toggle" id="campaign_customer_filter_toggle">Show/Hide Filters</button>
                    </div>
                </div>

                <div class="row mb-3 campaign-filter-panel" id="campaign_customer_filter_panel">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Filter by Tag</label>
                        <div class="dropdown campaign-filter-dropdown">
                            <button class="campaign-filter-toggle" type="button" id="campaignFilterTag" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">All Tags</button>
                            <div class="dropdown-menu" aria-labelledby="campaignFilterTag">
                                <?php foreach ($tagFilterOptions as $tagOption): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campaign-filter-checkbox" type="checkbox" value="<?= campaignH($tagOption) ?>" id="filter_tag_<?= md5($tagOption) ?>" data-filter-key="tag" data-placeholder="All Tags">
                                        <label class="form-check-label" for="filter_tag_<?= md5($tagOption) ?>"><?= campaignH($tagOption) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Filter by Package</label>
                        <div class="dropdown campaign-filter-dropdown">
                            <button class="campaign-filter-toggle" type="button" id="campaignFilterPackage" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">All Packages</button>
                            <div class="dropdown-menu" aria-labelledby="campaignFilterPackage">
                                <?php foreach ($packageFilterOptions as $packageOption): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campaign-filter-checkbox" type="checkbox" value="<?= campaignH($packageOption) ?>" id="filter_package_<?= md5($packageOption) ?>" data-filter-key="package" data-placeholder="All Packages">
                                        <label class="form-check-label" for="filter_package_<?= md5($packageOption) ?>"><?= campaignH($packageOption) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Filter by Platform</label>
                        <div class="dropdown campaign-filter-dropdown">
                            <button class="campaign-filter-toggle" type="button" id="campaignFilterPlatform" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">All Platforms</button>
                            <div class="dropdown-menu" aria-labelledby="campaignFilterPlatform">
                                <?php foreach ($platformFilterOptions as $platformOption): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campaign-filter-checkbox" type="checkbox" value="<?= campaignH($platformOption) ?>" id="filter_platform_<?= md5($platformOption) ?>" data-filter-key="platform" data-placeholder="All Platforms">
                                        <label class="form-check-label" for="filter_platform_<?= md5($platformOption) ?>"><?= campaignH($platformOption) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Filter by Brand</label>
                        <div class="dropdown campaign-filter-dropdown">
                            <button class="campaign-filter-toggle" type="button" id="campaignFilterBrand" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">All Brands</button>
                            <div class="dropdown-menu" aria-labelledby="campaignFilterBrand">
                                <?php foreach ($brandFilterOptions as $brandOption): ?>
                                    <div class="form-check">
                                        <input class="form-check-input campaign-filter-checkbox" type="checkbox" value="<?= campaignH($brandOption) ?>" id="filter_brand_<?= md5($brandOption) ?>" data-filter-key="brand" data-placeholder="All Brands">
                                        <label class="form-check-label" for="filter_brand_<?= md5($brandOption) ?>"><?= campaignH($brandOption) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="campaign_filter_last_order_months">Last Order Less Than (months)</label>
                        <input class="form-control" type="number" min="0" id="campaign_filter_last_order_months" value="">
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button class="btn btn-outline-primary filter-reset me-2" type="button" id="campaign_filter_search_btn">Search</button>
                        <button class="btn btn-outline-danger filter-reset" type="button" id="campaign_filter_reset_btn">Reset</button>
                    </div>
                </div>

                <form method="post" id="campaignAssignForm">
                    <input type="hidden" name="csrf_token" value="<?= campaignH($csrfToken) ?>">
                    <input type="hidden" name="campaign_id" value="<?= (int) $campaignId ?>">
                    <input type="hidden" name="actionBtn" value="assignCustomers">
                    <div class="campaign-assign-primary-actions">
                        <div class="campaign-assign-section-actions">
                            <?php if ($canManage): ?>
                                <button class="btn btn-sm btn-rounded btn-primary" type="submit" name="addBtn" id="addBtn">Assign Selected</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <table class="table table-striped" id="campaign_customer_search_table">
                        <thead><tr><th class="text-center"><input type="checkbox" class="campaign-table-check-all" <?= $canManage ? '' : 'disabled' ?>></th><th>S/N</th><th>Platform</th><th>Customer Name / Username</th><th>Tag</th><th>Brand</th><th>Package</th><th>Last Order Date</th><th>Total Orders</th><th>Total Spent</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php $num = 1; foreach ($customerRows as $row): ?>
                                <?php
                                $filterAttributes = customerRecordBuildFilterDataAttributes(array(
                                    'tag' => $row['customer_tag_values'],
                                    'package' => $row['package_values'],
                                    'platform' => array($row['platform']),
                                    'brand' => $row['brand_values'],
                                ));
                                ?>
                                <tr <?= $filterAttributes ?> data-last-order-date="<?= campaignH($row['last_order_date']) ?>">
                                    <td><input type="checkbox" class="campaign-row-check campaign-table-row-check" name="selected_customers[]" value="<?= campaignH(campaignAssignCustomerPayload($row)) ?>" <?= $canManage ? '' : 'disabled' ?>></td>
                                    <td><?= $num++ ?></td>
                                    <td><?= campaignH($row['platform']) ?></td>
                                    <td><?= campaignH($row['customer_name']) ?></td>
                                    <td class="campaign-tag-cell"><?= campaignAssignRenderTagRowsAsBadges($row['customer_tag_rows'] ?? array()) ?></td>
                                    <td><?= campaignH($row['brand']) ?></td>
                                    <td><?= campaignH($row['package']) ?></td>
                                    <td><?= campaignH($row['last_order_date']) ?></td>
                                    <td><?= (int) $row['total_order'] ?></td>
                                    <td><?= campaignH($row['total_spent']) ?></td>
                                    <td><?= campaignH($row['status_text']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <?php campaignRenderBackButton($backUrl, false); ?>
            </div>
        </div>
    </div>
    <script>
        function updateCampaignTableCheckAll(tableElement) {
            var visibleCheckboxes = $(tableElement)
                .find('tbody tr:visible .campaign-table-row-check:enabled');
            var headerCheckbox = $(tableElement).find('thead .campaign-table-check-all');
            var allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.filter(':checked').length === visibleCheckboxes.length;
            headerCheckbox.prop('checked', allChecked);
        }

        function campaignAssignNormalizeValue(value) {
            return String(value == null ? '' : value).replace(/\s+/g, ' ').trim().toLowerCase();
        }

        function campaignAssignRowMatchesValues(rowNode, attrName, selectedValues) {
            if (!selectedValues.length) {
                return true;
            }

            var rowValues = getCustomerRecordRowFilterNormalizedValues(rowNode, attrName);
            return selectedValues.some(function (selectedValue) {
                return rowValues.indexOf(selectedValue) !== -1;
            });
        }

        function campaignAssignSelectedValues(filterKey) {
            return $('.campaign-filter-checkbox[data-filter-key="' + filterKey + '"]:checked').map(function () {
                return campaignAssignNormalizeValue($(this).val());
            }).get();
        }

        function campaignAssignUpdateFilterLabels() {
            $('.campaign-filter-dropdown').each(function () {
                var dropdown = $(this);
                var checkedBoxes = dropdown.find('.campaign-filter-checkbox:checked');
                var button = dropdown.find('.campaign-filter-toggle');
                var placeholder = dropdown.find('.campaign-filter-checkbox').first().data('placeholder') || 'All';

                if (!checkedBoxes.length) {
                    button.text(placeholder);
                } else if (checkedBoxes.length === 1) {
                    button.text(checkedBoxes.first().val());
                } else {
                    button.text(checkedBoxes.length + ' selected');
                }
            });
        }

        $(document).ready(function () {
            var tableElement = document.getElementById('campaign_customer_search_table');
            var tableApi = $('#campaign_customer_search_table').DataTable();
            var filterPanel = $('#campaign_customer_filter_panel');
            var filterButton = $('#campaign_customer_filter_toggle');

            var filterState = {
                tag: [],
                package: [],
                platform: [],
                brand: [],
                lastOrderMonths: 0
            };

            var searchFn = function (settings, rowData, dataIndex) {
                if (!settings || settings.nTable !== tableElement) {
                    return true;
                }

                var rowNode = getCustomerRecordFilterRowNode(settings, dataIndex, tableApi);
                if (!rowNode) {
                    return true;
                }

                if (!campaignAssignRowMatchesValues(rowNode, 'tag', filterState.tag)) {
                    return false;
                }
                if (!campaignAssignRowMatchesValues(rowNode, 'package', filterState.package)) {
                    return false;
                }
                if (!campaignAssignRowMatchesValues(rowNode, 'platform', filterState.platform)) {
                    return false;
                }
                if (!campaignAssignRowMatchesValues(rowNode, 'brand', filterState.brand)) {
                    return false;
                }

                if (filterState.lastOrderMonths > 0) {
                    var lastOrderDate = rowNode.getAttribute('data-last-order-date') || '';
                    if (!lastOrderDate) {
                        return false;
                    }

                    var cutoffDate = new Date();
                    cutoffDate.setMonth(cutoffDate.getMonth() - filterState.lastOrderMonths);
                    cutoffDate.setHours(0, 0, 0, 0);

                    var rowDate = new Date(lastOrderDate + 'T00:00:00');
                    if (isNaN(rowDate.getTime()) || rowDate < cutoffDate) {
                        return false;
                    }
                }

                return true;
            };

            $.fn.dataTable.ext.search.push(searchFn);
            tableElement.__campaignAssignSearchFn = searchFn;

            $(document).on('change', '.campaign-table-check-all', function (event) {
                event.preventDefault();
                var isChecked = $(this).prop('checked');
                var currentTable = $(this).closest('table');
                currentTable.find('tbody tr:visible .campaign-table-row-check:enabled').prop('checked', isChecked);
                currentTable.find('thead .campaign-table-check-all').prop('checked', isChecked);
            });

            $(document).on('change', '.campaign-table-row-check', function () {
                var currentTable = $(this).closest('table');
                updateCampaignTableCheckAll(currentTable);
            });

            filterButton.on('click', function () {
                filterPanel.toggleClass('is-open');
            });

            $('#campaign_filter_search_btn').on('click', function () {
                filterState.tag = campaignAssignSelectedValues('tag');
                filterState.package = campaignAssignSelectedValues('package');
                filterState.platform = campaignAssignSelectedValues('platform');
                filterState.brand = campaignAssignSelectedValues('brand');
                filterState.lastOrderMonths = parseInt($('#campaign_filter_last_order_months').val(), 10) || 0;
                filterPanel.addClass('is-open');
                tableApi.draw(false);
            });

            $('#campaign_filter_reset_btn').on('click', function () {
                $('.campaign-filter-checkbox').prop('checked', false);
                $('#campaign_filter_last_order_months').val('');
                filterState = {
                    tag: [],
                    package: [],
                    platform: [],
                    brand: [],
                    lastOrderMonths: 0
                };
                campaignAssignUpdateFilterLabels();
                filterPanel.addClass('is-open');
                tableApi.draw(false);
            });

            $('.campaign-filter-checkbox').on('change', campaignAssignUpdateFilterLabels);
            campaignAssignUpdateFilterLabels();
            tableApi.on('draw', function () {
                updateCampaignTableCheckAll($('#campaign_customer_search_table'));
            });
            updateCampaignTableCheckAll($('#campaign_customer_search_table'));
        });

        var page = "Campaign";
        var action = "";
        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('campaign_customer_search_table');
        setButtonColor();
    </script>
    <?php campaignRenderPopupScript($pageTitle, $pageUrl); ?>
</body>
</html>
