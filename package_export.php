<?php
ob_start(); // Safely buffer all background HTML

$pageTitle = "Package Export";
include 'menuHeader.php';
include 'checkCurrentPagePin.php';

// 1. Load the Library
$libPath = 'header/PhpXlsxGenerator/PhpXlsxGenerator.php';
if (file_exists($libPath)) {
    require_once $libPath;
} else {
    ob_end_clean();
    echo "<script>alert('System Error: PhpXlsxGenerator library not found at $libPath'); window.history.back();</script>";
    exit();
}

// 2. Protected Mapping Function
if (!function_exists('getExportMapping')) {
    function getExportMapping($connect, $table, $idCol, $nameCol) {
        $map = [];
        $result = mysqli_query($connect, "SELECT `$idCol`, `$nameCol` FROM `$table`");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $map[$row[$idCol]] = $row[$nameCol];
            }
        }
        return $map;
    }
}

// Fetch mappings safely
$brandMap = getExportMapping($connect, BRAND, 'id', 'name');
$currencyMap = getExportMapping($connect, CUR_UNIT, 'id', 'unit');
$productMap = getExportMapping($connect, PROD, 'id', 'name');

$tblName = PKG;
$result = getData('*', '', '', $tblName, $connect);

if ($result && $result->num_rows > 0) {
    
    // Set up the Excel Header Row
    $excelData = array(
        array(
            'Package ID', 'Name', 'Item Code (SKU)', 'Item Description', 
            'Selling Price', 'Price Currency', 'Brand', 'Cost', 
            'Cost Currency', 'Agent Cost', 'Products Included', 
            'Barcode Slot Total', 'Remark'
        )
    );
    
    while ($row = $result->fetch_assoc()) {
        $brandName = isset($brandMap[$row['brand']]) ? $brandMap[$row['brand']] : '';
        $priceCurName = isset($currencyMap[$row['currency_unit']]) ? $currencyMap[$row['currency_unit']] : '';
        $costCurName = isset($currencyMap[$row['cost_curr']]) ? $currencyMap[$row['cost_curr']] : '';
        
        $productNamesList = [];
        if (!empty($row['product'])) {
            $productIds = explode(',', $row['product']);
            foreach ($productIds as $pid) {
                if (isset($productMap[$pid])) {
                    $productNamesList[] = $productMap[$pid];
                }
            }
        }
        $productString = implode(', ', $productNamesList);

        // Add the row to Excel array
        $excelData[] = array(
            isset($row['id']) ? (string)$row['id'] : '',
            isset($row['name']) ? (string)$row['name'] : '',
            isset($row['item_code']) ? (string)$row['item_code'] : '',
            isset($row['item_description']) ? (string)$row['item_description'] : '',
            isset($row['price']) ? (string)$row['price'] : '0.00',
            $priceCurName,
            $brandName,
            isset($row['cost']) ? (string)$row['cost'] : '0.00',
            $costCurName,
            isset($row['agent_cost']) ? (string)$row['agent_cost'] : '0.00',
            $productString,
            isset($row['barcode_slot_total']) ? (string)$row['barcode_slot_total'] : '0',
            isset($row['remark']) ? (string)$row['remark'] : ''
        );
    }
    
    // Wipe background HTML to keep the file pure
    ob_end_clean(); 
    $filename = "package_data_" . date('Y-m-d') . ".xlsx";
    
    // 3. GENERATE EXCEL WITH CORRECT NAMESPACE (\CodexWorld\PhpXlsxGenerator)
    $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData);
    $xlsx->downloadAs($filename);
    exit(); 
    
} else {
    ob_end_clean();
    echo "<script>alert('No data found to export.');window.location.href='package_table.php';</script>";
    exit();
}
?>