<?php
$pageTitle = "Shopee Customer Record";
$currentPagePin = 85;

include_once '../include/list_page_header.php';
include_once ROOT . '/include/customer_tag.php';

$redirectPage = $SITEURL . '/shopee/shopee_cust_info.php';
$deleteRedirectPage = $SITEURL . '/shopee/shopee_cust_info_table.php';
$tableDataset = shopeeCustomerRecordGetListDataset($connect, $finance_connect, array_merge((array) $_GET, (array) $_POST));
$tableRows = isset($tableDataset['rows']) ? $tableDataset['rows'] : array();
$customerLabelMap = isset($tableDataset['label_map']) ? $tableDataset['label_map'] : array();
$customerTagMap = isset($tableDataset['tag_map']) ? $tableDataset['tag_map'] : array();
$lookupMaps = isset($tableDataset['lookup_maps']) && is_array($tableDataset['lookup_maps']) ? $tableDataset['lookup_maps'] : array();
$picLookupMap = isset($lookupMaps['pic']) && is_array($lookupMaps['pic']) ? $lookupMaps['pic'] : array();
$countryLookupMap = isset($lookupMaps['country']) && is_array($lookupMaps['country']) ? $lookupMaps['country'] : array();
$brandLookupMap = isset($lookupMaps['brand']) && is_array($lookupMaps['brand']) ? $lookupMaps['brand'] : array();
$seriesLookupMap = isset($lookupMaps['series']) && is_array($lookupMaps['series']) ? $lookupMaps['series'] : array();
// if (!$result) {
//     echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
//     echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
// }
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    
    $(document).ready(() => {
        createSortingTable('shopee_cust_info_table', { searching: true });
        initCustomerRecordTableFilters({
            tableId: 'shopee_cust_info_table',
            storageKey: 'shopee_customer_record_filters',
            panelStorageKey: 'shopee_customer_record_filter_panel_open',
            deferApply: true,
            selectFieldsMultiple: true,
            scopePaths: ['shopee/shopee_cust_info_table.php', 'shopee/shopee_cust_info.php'],
            filters: [
                { key: 'customer_label', label: 'Customer Label', attr: 'customer_label', type: 'select', placeholder: 'All Customer Labels' },
                { key: 'customer_tag', label: 'Tag', attr: 'customer_tag', type: 'select', placeholder: 'All Tags' },
                { key: 'country', label: 'Country', attr: 'country', type: 'select', placeholder: 'All Countries' },
                { key: 'brand', label: 'Brand', attr: 'brand', type: 'select', placeholder: 'All Brands' },
                { key: 'series', label: 'Series', attr: 'series', type: 'select', placeholder: 'All Series' },
                { key: 'sales_person', label: 'Sales Person In Charge', attr: 'sales_person', type: 'select', placeholder: 'All Sales Persons' }
            ]
        });
    });
</script>
<body>

    

    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">


                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i
                                class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?php echo $pageTitle ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                        href="<?= $redirectPage . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
                                        Record </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                if (empty($tableRows)) {
                    echo '<div class="text-center"><h4>No Result!</h4></div>';
                } else {
                    ?>
                    <table class="table table-striped" id="shopee_cust_info_table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">Shopee Buyer Username</th>
                                <th scope="col">Customer Label</th>
                                <th scope="col">Sales Person In Charge</th>
                                <th scope="col">Country</th>
                                <th scope="col">Brand</th>
                                <th scope="col">Series</th>
                                <th scope="col">Whatsapp / Contact Number</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($tableRows as $row) {
                                if (isset($row['buyer_username'], $row['id']) && !empty($row['buyer_username'])) {
                                    $customerLabelMeta = isset($customerLabelMap[(int) $row['id']]) ? $customerLabelMap[(int) $row['id']] : array();
                                    $customerTagRows = isset($customerTagMap[(int) $row['id']]) ? $customerTagMap[(int) $row['id']] : array();

                                    $picName = $countryName = $brandName = $seriesName = '';

                                    $picValue = isset($row['pic']) ? trim((string) $row['pic']) : '';
                                    if ($picValue !== '' && $picValue !== '0') {
                                        $picName = isset($picLookupMap[$picValue]) ? $picLookupMap[$picValue] : $picValue;
                                    }

                                    $countryValue = isset($row['country']) ? trim((string) $row['country']) : '';
                                    if ($countryValue !== '' && $countryValue !== '0') {
                                        $countryName = isset($countryLookupMap[$countryValue]) ? $countryLookupMap[$countryValue] : $countryValue;
                                    }

                                    $brandValue = isset($row['brand']) ? trim((string) $row['brand']) : '';
                                    if ($brandValue !== '' && $brandValue !== '0') {
                                        $brandName = isset($brandLookupMap[$brandValue]) ? $brandLookupMap[$brandValue] : $brandValue;
                                    }

                                    $seriesValue = isset($row['series']) ? trim((string) $row['series']) : '';
                                    if ($seriesValue !== '' && $seriesValue !== '0') {
                                        $seriesName = isset($seriesLookupMap[$seriesValue]) ? $seriesLookupMap[$seriesValue] : $seriesValue;
                                    }

                                    $filterAttributes = customerRecordBuildFilterDataAttributes(array(
                                        'customer_name' => isset($row['buyer_username']) ? $row['buyer_username'] : '',
                                        'customer_label' => customerRecordExtractLabelNames($customerLabelMeta),
                                        'customer_tag' => customerRecordExtractTagNames($customerTagRows),
                                        'sales_person' => $picName,
                                        'country' => $countryName,
                                        'brand' => $brandName,
                                        'series' => $seriesName,
                                    ));
                                    ?>

                                    <tr <?= $filterAttributes ?>>
                                        <th class="hideColumn" scope="row"><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></th>
                                        <th scope="row"><?= htmlspecialchars($num++, ENT_QUOTES, 'UTF-8') ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['buyer_username'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                            <?php
                                            $urbanismAction = getUrbanismMemberActionData(
                                                $connect,
                                                '',
                                                isset($row['buyer_username']) ? (string) $row['buyer_username'] : '',
                                                $deleteRedirectPage,
                                                $pageTitle
                                            );
                                            ?>
                                            <a
                                                class="btn <?= $urbanismAction['is_member'] ? 'btn-success' : 'btn-secondary' ?> me-1 <?= $urbanismAction['disabled'] ? 'disabled' : '' ?>"
                                                href="<?= htmlspecialchars($urbanismAction['url'], ENT_QUOTES, 'UTF-8') ?>"
                                                title="<?= htmlspecialchars($urbanismAction['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $urbanismAction['disabled'] ? 'onclick="return false;" aria-disabled="true"' : '' ?>><i class="<?= $urbanismAction['icon_class'] ?>"></i></a>
                                        </td>
                                        <td scope="row"><?= customerLabelRenderNameCell(isset($row['buyer_username']) ? $row['buyer_username'] : '', $customerLabelMeta) ?></td>
                                        <td scope="row"><?= customerLabelRenderSummaryCell($customerLabelMeta, $customerTagRows) ?></td>
                                        <td scope="row"><?= htmlspecialchars($picName, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?= htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?= htmlspecialchars($seriesName, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?= htmlspecialchars(isset($row['contact_no']) ? $row['contact_no'] : '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row"><?= htmlspecialchars(isset($row['remark']) ? $row['remark'] : '', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php }
                            } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">Shopee Buyer Username</th>
                                <th scope="col">Customer Label</th>
                                <th scope="col">Sales Person In Charge</th>
                                <th scope="col">Country</th>
                                <th scope="col">Brand</th>
                                <th scope="col">Series</th>
                                <th scope="col">Whatsapp / Contact Number</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>

        </div>

</body>


<script>
    /**
  oufei 20231014
  common.fun.js
  function(void)
  to solve the issue of dropdown menu displaying inside the table when table class include table-responsive
*/
    dropdownMenuDispFix();

    /**
      oufei 20231014
      common.fun.js
      function(id)
      to resize table with bootstrap 5 classes
    */
    datatableAlignment('shopee_cust_info_table');
    setButtonColor();
</script>

</html>
