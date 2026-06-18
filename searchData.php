<?php
include "include/common.php";
include "include/common_variable.php";
include "include/connection.php";

// Get the search parameter and column name from the POST request
$searchText = mysqli_real_escape_string($connect, trim((string) post('searchText')));
$searchType = trim((string) post('searchType'));
$searchCol = trim((string) post('searchCol'));
$tblname = trim((string) post('tblname'));
$isFinance = trim((string) post('isFin'));

if (!function_exists('searchDataIsValidIdentifier')) {
    function searchDataIsValidIdentifier($value)
    {
        return preg_match('/^[A-Za-z0-9_]+$/', (string) $value) === 1;
    }
}

if (!function_exists('searchDataNormalizeSelectList')) {
    function searchDataNormalizeSelectList($value)
    {
        $value = trim((string) $value);
        if ($value === '*') {
            return '*';
        }

        $columns = array_filter(array_map('trim', explode(',', $value)), 'strlen');
        if (empty($columns)) {
            return '';
        }

        foreach ($columns as $column) {
            if (!searchDataIsValidIdentifier($column)) {
                return '';
            }
        }

        return implode(', ', array_map(function ($column) {
            return '`' . $column . '`';
        }, $columns));
    }
}

if ($isFinance) {
    $db = $finance_connect;
} else {
    $db = $connect;
}

$normalizedSearchType = searchDataNormalizeSelectList($searchType);
$normalizedSearchCol = searchDataIsValidIdentifier($searchCol) ? $searchCol : '';
$normalizedTableName = searchDataIsValidIdentifier($tblname) ? $tblname : '';

if ($normalizedSearchType === '' || $normalizedSearchCol === '' || $normalizedTableName === '') {
    echo json_encode(['error' => 'Invalid search parameters']);
    exit;
}

// Build the query dynamically
$query = "SELECT $normalizedSearchType FROM `$normalizedTableName` WHERE `$normalizedSearchCol` = '$searchText' AND `status` = 'A' ";
$result = mysqli_query($db, $query);

if ($result) {
    // Fetch the result as an associative array
    $searchResult = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Return the search result as a JSON response
    echo json_encode($searchResult);
} else {
    // Handle the case where the query fails
    echo json_encode(['error' => 'Error executing query']);
}
?>
