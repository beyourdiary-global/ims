<?php
/**
 * GET /api/customers.php
 *
 * Shopee customer records - the "Customer" half of the Daily Sales &
 * Customer Report. Same data source as shopee/shopee_cust_info_table.php.
 *
 * Params: q, limit, offset, tag_id, label_type, label_id
 */

define('CMS_API_ENTRY', 'customers');

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/customer_shape.php';

$keyRow = cmsApiAuthenticate($connect, 'read');

require_once ROOT . '/include/customer_tag.php';

$paging = cmsApiPaging(50, 500);
$search = cmsApiQueryValue('q', 190);
$tagId = cmsApiQueryInt('tag_id', 0, 0, null);
$labelType = strtolower(cmsApiQueryValue('label_type', 32));
$labelId = cmsApiQueryInt('label_id', 0, 0, null);

if (!in_array($labelType, array('segmentation', 'level', 'repeat'), true)) {
    $labelType = '';
}

// shopeeCustomerRecordBuildListDataset() applies its own label filter by
// reading label_type / label_id straight from $_GET. We filter ourselves
// below so the response stays predictable, so clear them first.
unset($_GET['label_type'], $_GET['label_id']);

$dataset = shopeeCustomerRecordBuildListDataset($connect, $finance_connect);

$rows = isset($dataset['rows']) && is_array($dataset['rows']) ? $dataset['rows'] : array();
$labelMap = isset($dataset['label_map']) && is_array($dataset['label_map']) ? $dataset['label_map'] : array();
$tagMap = isset($dataset['tag_map']) && is_array($dataset['tag_map']) ? $dataset['tag_map'] : array();
$lookupMaps = isset($dataset['lookup_maps']) && is_array($dataset['lookup_maps']) ? $dataset['lookup_maps'] : array();

$needle = $search === '' ? '' : strtolower($search);
$shaped = array();

foreach ($rows as $row) {
    $customerId = isset($row['id']) ? (int) $row['id'] : 0;
    if ($customerId <= 0) {
        continue;
    }

    $customerTags = isset($tagMap[$customerId]) ? $tagMap[$customerId] : array();

    if ($tagId > 0) {
        $tagHit = false;
        foreach ($customerTags as $tagRow) {
            if (isset($tagRow['tag_id']) && (int) $tagRow['tag_id'] === $tagId) {
                $tagHit = true;
                break;
            }
        }
        if (!$tagHit) {
            continue;
        }
    }

    if ($labelType !== '' && $labelId > 0) {
        $meta = isset($labelMap[$customerId][$labelType]) ? $labelMap[$customerId][$labelType] : null;
        if (!is_array($meta) || (int) $meta['id'] !== $labelId) {
            continue;
        }
    }

    if ($needle !== '') {
        $haystack = strtolower(
            (isset($row['buyer_username']) ? (string) $row['buyer_username'] : '') . ' ' .
            (isset($row['contact_no']) ? (string) $row['contact_no'] : '') . ' ' .
            (isset($row['remark']) ? (string) $row['remark'] : '')
        );
        if (strpos($haystack, $needle) === false) {
            continue;
        }
    }

    $shaped[] = cmsApiShapeCustomer(
        $row,
        $customerTags,
        isset($labelMap[$customerId]) ? $labelMap[$customerId] : array(),
        $lookupMaps
    );
}

cmsApiOk(
    cmsApiPage($shaped, $paging['limit'], $paging['offset']),
    cmsApiMeta('customers', $keyRow, array(
        'source_table' => defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info',
        'filters' => array(
            'q' => $search,
            'tag_id' => $tagId,
            'label_type' => $labelType,
            'label_id' => $labelId,
        ),
    ))
);
