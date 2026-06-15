<?php
$pageTitle = "Website Customer Record (Deals)";
$currentPagePin = 84;

include_once 'include/list_page_header.php';
include_once ROOT . '/include/customer_tag.php';

$redirect_page = $SITEURL . '/website_customer_record.php';
$deleteRedirectPage = $SITEURL . '/website_customer_record_table.php';
$result = getData('*', '', '', WEB_CUST_RCD, $connect);
$tableRows = array();
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $tableRows[] = $row;
    }
}
$customerLabelData = customerLabelPrepareCustomerRows($connect, 'website', $tableRows);
$tableRows = isset($customerLabelData['rows']) ? $customerLabelData['rows'] : array();
$customerLabelMap = isset($customerLabelData['label_map']) ? $customerLabelData['label_map'] : array();
$customerTagMap = isset($customerLabelData['tag_map']) ? $customerLabelData['tag_map'] : array();
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
</head>

<script>
    $(document).ready(() => {
        createSortingTable('web_cust_deals', { searching: true });
        initCustomerRecordTableFilters({
            tableId: 'web_cust_deals',
            storageKey: 'website_customer_record_filters',
            panelStorageKey: 'website_customer_record_filter_panel_open',
            deferApply: true,
            selectFieldsMultiple: true,
            scopePaths: ['website_customer_record_table.php', 'website_customer_record.php'],
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


<style>
    #addBtn,
    .btn-container 

    

    .customer-name-label-cell {
        white-space: nowrap;
    }

</style>

<body>

    <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">

        <div class="col-12 col-md-11">

            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i
                            class="fa-solid fa-chevron-right fa-xs"></i>
                        <?php echo $pageTitle ?>
                    </p>
                </div>

                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap">
                        <h2>
                            <?php echo $pageTitle ?>
                        </h2>
                        
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                        href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
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

                <table class="table table-striped" id="web_cust_deals">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Customer ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Customer Label</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Customer Email</th>
                            <th scope="col">Customer Birthday</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Country</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Series</th>
                            <th scope="col">Shipping Receiver Name</th>
                            <th scope="col">Shipping Receiver Address</th>
                            <th scope="col">Shipping Receiver Contact</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableRows as $row) {
                            $customerLabelMeta = isset($customerLabelMap[(int) $row['id']]) ? $customerLabelMap[(int) $row['id']] : array();
                            $customerTagRows = isset($customerTagMap[(int) $row['id']]) ? $customerTagMap[(int) $row['id']] : array();
                            $pic = $country = $brand = $series = '';
                            $q1 = getData('name', "id='" . $row['sales_pic'] . "'", '', USR_USER, $connect);
                            if ($q1) {
                                $pic = $q1->fetch_assoc();
                            }

                            $q2 = getData('nicename', "id='" . $row['country'] . "'", '', COUNTRIES, $connect);
                            if ($q2) {
                                $country = $q2->fetch_assoc();
                            }
                            $q3 = getData('name', "id='" . $row['brand'] . "'", '', BRAND, $connect);
                            if ($q3) {
                                $brand = $q3->fetch_assoc();
                            }

                            $q4 = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                            if ($q4) {
                                $series = $q4->fetch_assoc();
                            }

                            $picName = isset($pic['name']) ? $pic['name'] : (isset($row['sales_pic']) ? (string) $row['sales_pic'] : '');
                            $countryName = isset($country['nicename']) ? $country['nicename'] : (isset($row['country']) ? (string) $row['country'] : '');
                            $brandName = isset($brand['name']) ? $brand['name'] : (isset($row['brand']) ? (string) $row['brand'] : '');
                            $seriesName = isset($series['name']) ? $series['name'] : (isset($row['series']) ? (string) $row['series'] : '');
                            $filterAttributes = customerRecordBuildFilterDataAttributes(array(
                                'customer_id' => isset($row['cust_id']) ? $row['cust_id'] : '',
                                'customer_name' => isset($row['name']) ? $row['name'] : '',
                                'customer_label' => customerRecordExtractLabelNames($customerLabelMeta),
                                'customer_tag' => customerRecordExtractTagNames($customerTagRows),
                                'sales_person' => $picName,
                                'country' => $countryName,
                                'brand' => $brandName,
                                'series' => $seriesName,
                            ));

                            ?>

                            <tr <?= $filterAttributes ?>>
                                <th class="hideColumn" scope="row">
                                    <?= $row['id'] ?>
                                </th>
                                <th scope="row">
                                    <?= $num++; ?>
                                </th>
                                <td scope="row" class="btn-container">
                                    <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                    <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                    <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                    <?php
                                    $urbanismAction = getUrbanismMemberActionData(
                                        $connect,
                                        '',
                                        isset($row['name']) ? (string) $row['name'] : '',
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

                                <td scope="row">
                                    <?= $row['cust_id'] ?>
                                </td>

                                <td scope="row" class="customer-name-label-cell">
                                    <?= customerLabelRenderNameCell(isset($row['name']) ? $row['name'] : '', $customerLabelMeta) ?>
                                </td>

                                <td scope="row"><?= customerLabelRenderSummaryCell($customerLabelMeta, $customerTagRows) ?></td>

                                <td scope="row">
                                    <?= $row['contact'] ?>
                                </td>

                                <td scope="row">
                                    <?= $row['cust_email'] ?>
                                </td>

                                <td scope="row">
                                    <?= $row['cust_birthday'] ?>
                                </td>

                                <td scope="row"><?= $picName ?></td>

                                <td scope="row">
                                    <?= $countryName ?>
                                </td>

                                <td scope="row"><?= $brandName ?></td>

                                <td scope="row"><?= $seriesName ?></td>

                                <td scope="row">
                                    <?= $row['ship_rec_name'] ?>
                                </td>
                                <td scope="row">
                                    <?= $row['ship_rec_add'] ?>
                                </td>
                                <td scope="row">
                                    <?= $row['ship_rec_contact'] ?>
                                </td>
                                <td scope="row">
                                    <?= $row['remark'] ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Customer ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Customer Label</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Customer Email</th>
                            <th scope="col">Customer Birthday</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Country</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Series</th>
                            <th scope="col">Shipping Receiver Name</th>
                            <th scope="col">Shipping Receiver Address</th>
                            <th scope="col">Shipping Receiver Contact</th>
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
    datatableAlignment('web_cust_deals');
</script>

</html>
