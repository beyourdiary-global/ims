<?php
include "../include/common.php";
include "../include/common_variable.php";
include "../include/connection.php";

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if (!function_exists('packageLookupRespond')) {
    function packageLookupRespond($payload)
    {
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('packageLookupPostSearchText')) {
    function packageLookupPostSearchText($key)
    {
        $value = postSpaceFilter($key);
        if (is_array($value)) {
            return '';
        }

        $value = strip_tags((string) $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}

$action = trim((string) post('action'));
if ($action === '') {
    packageLookupRespond(array('ok' => 0, 'message' => 'Missing action.'));
}

switch ($action) {
    case 'search_products':
        $searchText = packageLookupPostSearchText('searchText');
        if ($searchText === '') {
            packageLookupRespond(array());
        }

        $searchEscaped = mysqli_real_escape_string($connect, $searchText);
        $result = mysqli_query(
            $connect,
            "SELECT id, name
             FROM `" . PROD . "`
             WHERE status = 'A'
               AND name LIKE '%" . $searchEscaped . "%'
             ORDER BY CASE WHEN name = '" . $searchEscaped . "' THEN 0 ELSE 1 END, name ASC, id ASC
             LIMIT 20"
        );

        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = array(
                    'desc' => isset($row['name']) ? (string) $row['name'] : '',
                    'val' => isset($row['id']) ? (string) ((int) $row['id']) : '',
                );
            }
        }

        packageLookupRespond($rows);
        break;

    case 'search_parent_packages':
        $searchText = packageLookupPostSearchText('searchText');
        $excludePackageId = (int) post('excludePackageId');
        if ($searchText === '') {
            packageLookupRespond(array());
        }

        $searchEscaped = mysqli_real_escape_string($connect, $searchText);
        $excludeSql = $excludePackageId > 0 ? " AND id <> " . $excludePackageId : '';
        $result = mysqli_query(
            $connect,
            "SELECT id, name, item_code
             FROM `" . PKG . "`
             WHERE status = 'A'
               " . $excludeSql . "
               AND (
                    item_code LIKE '%" . $searchEscaped . "%'
                    OR name LIKE '%" . $searchEscaped . "%'
               )
             ORDER BY
                CASE
                    WHEN item_code = '" . $searchEscaped . "' THEN 0
                    WHEN name = '" . $searchEscaped . "' THEN 1
                    ELSE 2
                END,
                item_code ASC,
                name ASC,
                id ASC
             LIMIT 20"
        );

        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $itemCode = trim((string) (isset($row['item_code']) ? $row['item_code'] : ''));
                $name = trim((string) (isset($row['name']) ? $row['name'] : ''));
                $displayName = $name !== '' ? $name : $itemCode;
                if ($itemCode !== '') {
                    $displayName .= ' (Item Code: ' . $itemCode . ')';
                }
                $rows[] = array(
                    'desc' => $displayName,
                    'val' => isset($row['id']) ? (string) ((int) $row['id']) : '',
                    'select_text' => $name !== '' ? $name : $itemCode,
                );
            }
        }

        packageLookupRespond($rows);
        break;

    case 'get_product_details':
        $productId = (int) post('productId');
        if ($productId <= 0) {
            packageLookupRespond(array('ok' => 0, 'message' => 'Invalid product ID.'));
        }

        $result = mysqli_query(
            $connect,
            "SELECT
                p.id,
                p.name,
                p.weight,
                p.weight_unit,
                p.barcode_status,
                p.barcode_slot,
                wu.unit AS weight_unit_name
             FROM `" . PROD . "` p
             LEFT JOIN `" . WGT_UNIT . "` wu
                ON wu.id = p.weight_unit
               AND wu.status = 'A'
             WHERE p.id = " . $productId . "
               AND p.status = 'A'
             LIMIT 1"
        );

        if (!$result || mysqli_num_rows($result) === 0) {
            packageLookupRespond(array('ok' => 0, 'message' => 'Product not found.'));
        }

        $row = mysqli_fetch_assoc($result);
        packageLookupRespond(array(
            'ok' => 1,
            'product' => array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'weight' => isset($row['weight']) ? (string) $row['weight'] : '',
                'weight_unit' => isset($row['weight_unit']) ? (string) $row['weight_unit'] : '',
                'weight_unit_name' => isset($row['weight_unit_name']) ? (string) $row['weight_unit_name'] : '',
                'barcode_status' => isset($row['barcode_status']) && trim((string) $row['barcode_status']) !== '' ? (string) $row['barcode_status'] : '0',
                'barcode_slot' => isset($row['barcode_slot']) && trim((string) $row['barcode_slot']) !== '' ? (string) $row['barcode_slot'] : '0',
            ),
        ));
        break;
}

packageLookupRespond(array('ok' => 0, 'message' => 'Unsupported action.'));
