<?php
$pageTitle = "Facebook Customer Record (Deals)";
$currentPagePin = 75;
$disablePinGroupPageTitleSync = true;

include_once 'include/list_page_header.php';
include_once ROOT . '/include/customer_tag.php';

$redirectPage = $SITEURL . '/fb_cust_deals.php';
$deleteRedirectPage = $SITEURL . '/fb_cust_deals_table.php';
$result = getData('*', '', '', FB_CUST_DEALS, $connect);
$tableRows = array();
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $tableRows[] = $row;
    }
}
$customerLabelData = customerLabelPrepareCustomerRows($connect, 'facebook', $tableRows);
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
        createSortingTable('fb_cust_deals', { searching: true });
        initCustomerRecordTableFilters({
            tableId: 'fb_cust_deals',
            storageKey: 'facebook_customer_record_filters',
            panelStorageKey: 'facebook_customer_record_filter_panel_open',
            deferApply: true,
            selectFieldsMultiple: true,
            scopePaths: ['fb_cust_deals_table.php', 'fb_cust_deals.php'],
            filters: [
                { key: 'customer_label', label: 'Customer Label', attr: 'customer_label', type: 'select', placeholder: 'All Customer Labels' },
                { key: 'customer_tag', label: 'Tag', attr: 'customer_tag', type: 'select', placeholder: 'All Tags' },
                { key: 'country', label: 'Country', attr: 'country', type: 'select', placeholder: 'All Countries' },
                { key: 'brand', label: 'Brand', attr: 'brand', type: 'select', placeholder: 'All Brands' },
                { key: 'series', label: 'Series', attr: 'series', type: 'select', placeholder: 'All Series' },
                { key: 'sales_person', label: 'Sales Person In Charge', attr: 'sales_person', type: 'select', placeholder: 'All Sales Persons' },
                { key: 'facebook_page', label: 'Facebook Page', attr: 'facebook_page', type: 'select', placeholder: 'All Facebook Pages' },
                { key: 'channel', label: 'Channel', attr: 'channel', type: 'select', placeholder: 'All Channels' }
            ]
        });
    });
</script>

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

                <table class="table table-striped" id="fb_cust_deals">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Name</th>
                            <th scope="col">Customer Label</th>
                            <th scope="col">Facebook Link</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Country</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Facebook Page</th>
                            <th scope="col">Channel</th>
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
                            $pic_name = $country_name = $brand_name = $series_name = $fb_page_name = $channel_name = '';

                            $q1 = getData('name', "id='" . $row['sales_pic'] . "'", '', USR_USER, $connect);
                            $pic = $q1 ? $q1->fetch_assoc() : null;
                            if ($pic) $pic_name = $pic['name'];
                            else $pic_name = isset($row['sales_pic']) ? (string) $row['sales_pic'] : '';

                            $q2 = getData('nicename', "id='" . $row['country'] . "'", '', COUNTRIES, $connect);
                            $country = $q2 ? $q2->fetch_assoc() : null;
                            if ($country) $country_name = $country['nicename'];
                            else $country_name = isset($row['country']) ? (string) $row['country'] : '';

                            $q3 = getData('name', "id='" . $row['brand'] . "'", '', BRAND, $connect);
                            $brand = $q3 ? $q3->fetch_assoc() : null;
                            if ($brand) $brand_name = $brand['name'];
                            else $brand_name = isset($row['brand']) ? (string) $row['brand'] : '';

                            $q4 = getData('name', "id='" . $row['series'] . "'", '', BRD_SERIES, $connect);
                            $series = $q4 ? $q4->fetch_assoc() : null;
                            if ($series) $series_name = $series['name'];
                            else $series_name = isset($row['series']) ? (string) $row['series'] : '';

                            //fb page
                            $q6 = getData('name', "id='" . $row['fb_page'] . "'", '', FB_PAGE_ACC, $finance_connect);
                            $fb_page = $q6 ? $q6->fetch_assoc() : null;
                            if ($fb_page) $fb_page_name = $fb_page['name'];
                            else $fb_page_name = isset($row['fb_page']) ? (string) $row['fb_page'] : '';

                            //channel
                            $q7 = getData('name', "id='" . $row['channel'] . "'", '', CHANEL_SC_MD, $finance_connect);
                            $channel = $q7 ? $q7->fetch_assoc() : null;
                            if ($channel) $channel_name = $channel['name'];
                            else $channel_name = isset($row['channel']) ? (string) $row['channel'] : '';

                            $filterAttributes = customerRecordBuildFilterDataAttributes(array(
                                'customer_name' => isset($row['name']) ? $row['name'] : '',
                                'customer_label' => customerRecordExtractLabelNames($customerLabelMeta),
                                'customer_tag' => customerRecordExtractTagNames($customerTagRows),
                                'sales_person' => $pic_name,
                                'country' => $country_name,
                                'brand' => $brand_name,
                                'series' => $series_name,
                                'facebook_page' => $fb_page_name,
                                'channel' => $channel_name,
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
                                <div class="d-flex align-items-center">
                                <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
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
                            </div>
                        </td>

                                <td scope="row">
                                    <?= customerLabelRenderNameCell(isset($row['name']) ? $row['name'] : '', $customerLabelMeta) ?>
                                </td>
                                <td scope="row"><?= customerLabelRenderSummaryCell($customerLabelMeta, $customerTagRows) ?></td>
                                <td scope="row">
                                    <?= $row['fb_link'] ?>
                                </td>
                                <td scope="row">
                                    <?= $row['contact'] ?>
                                </td>
                                <td scope="row">
                                    <?= $pic_name ?>
                                </td>
                                <td scope="row">
                                    <?= $country_name ?>
                                </td>
                                <td scope="row">
                                    <?= $brand_name ?>
                                </td>
                                <td scope="row">
                                    <?= $fb_page_name ?>
                                </td>
                                <td scope="row">
                                    <?= $channel_name ?>
                                </td>
                                <td scope="row">
                                    <?= $series_name ?>
                                </td>
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
                            <th scope="col">Name</th>
                            <th scope="col">Customer Label</th>
                            <th scope="col">Facebook Link</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Sales Person In Charge</th>
                            <th scope="col">Country</th>
                            <th scope="col">Brand</th>
                            <th scope="col">Facebook Page</th>
                            <th scope="col">Channel</th>
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
    datatableAlignment('fb_cust_deals');
</script>

</html>
