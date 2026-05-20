<?php
$pageTitle = "Shopee Customer Record";
$currentPagePin = 85;
$isFinance = 1;
include '../menuHeader.php';
include '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$pinAccess = checkCurrentPin($connect, $pageTitle);

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/shopee/shopee_cust_info.php';
$deleteRedirectPage = $SITEURL . '/shopee/shopee_cust_info_table.php';
$result = getData('*', '', '', SHOPEE_CUST_INFO, $finance_connect);
$tableRows = array();
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $tableRows[] = $row;
    }
}
$customerLabelData = customerLabelPrepareCustomerRows($connect, 'shopee', $tableRows);
$tableRows = isset($customerLabelData['rows']) ? $customerLabelData['rows'] : array();
$customerLabelMap = isset($customerLabelData['label_map']) ? $customerLabelData['label_map'] : array();
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
    preloader(300);
    $(document).ready(() => {
        createSortingTable('shopee_cust_info_table');
    });
</script>



<body>

    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

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
                            // Caches to avoid repeated DB lookups for the same PIC and Country values
                            $picCache = [];
                            $countryCache = [];
                            
                            foreach ($tableRows as $row) {
                                if (isset($row['buyer_username'], $row['id']) && !empty($row['buyer_username'])) {

                                    $picName = $countryName = $brandName = $seriesName = '';

                                    $picValue = isset($row['pic']) ? trim((string) $row['pic']) : '';
                                    if ($picValue !== '' && $picValue !== '0') {
                                        if (isset($picCache[$picValue])) {
                                            // Use cached PIC display name
                                            $picName = $picCache[$picValue];
                                        } else {
                                            // Perform lookup once for this PIC value and cache the result
                                            $resolvedPicName = $picValue;
                                            $pic = getData('name', "id='" . $picValue . "'", 'LIMIT 1', USR_USER, $connect);
                                            if (!$pic || $pic->num_rows === 0) {
                                                $pic = getData('name', "name='" . $picValue . "'", 'LIMIT 1', USR_USER, $connect);
                                            }

                                            if ($pic && $pic->num_rows > 0) {
                                                $picRow = $pic->fetch_assoc();
                                                $resolvedPicName = $picRow['name'];
                                            }
                                            $picCache[$picValue] = $resolvedPicName;
                                            $picName = $resolvedPicName;
                                        }
                                    }

                                    $countryValue = isset($row['country']) ? trim((string) $row['country']) : '';
                                    if ($countryValue !== '' && $countryValue !== '0') {
                                        if (isset($countryCache[$countryValue])) {
                                            // Use cached Country display name
                                            $countryName = $countryCache[$countryValue];
                                        } else {
                                            // Perform lookup once for this Country value and cache the result
                                            $resolvedCountryName = $countryValue;
                                            $country = getData('nicename', "id='" . $countryValue . "'", 'LIMIT 1', COUNTRIES, $connect);
                                            if (!$country || $country->num_rows === 0) {
                                                $country = getData('nicename', "nicename='" . $countryValue . "'", 'LIMIT 1', COUNTRIES, $connect);
                                            }
                                            if (!$country || $country->num_rows === 0) {
                                                $country = getData('nicename', "name='" . $countryValue . "'", 'LIMIT 1', COUNTRIES, $connect);
                                            }

                                            if ($country && $country->num_rows > 0) {
                                                $countryRow = $country->fetch_assoc();
                                                $resolvedCountryName = $countryRow['nicename'];
                                            }
                                            $countryCache[$countryValue] = $resolvedCountryName;
                                            $countryName = $resolvedCountryName;
                                        }
                                    }

                                    $brandValue = isset($row['brand']) ? trim((string) $row['brand']) : '';
                                    if ($brandValue !== '' && $brandValue !== '0') {
                                        $brand = getData('name', "id='" . $brandValue . "'", 'LIMIT 1', BRAND, $connect);
                                        if (!$brand || $brand->num_rows === 0) {
                                            $brand = getData('name', "name='" . $brandValue . "'", 'LIMIT 1', BRAND, $connect);
                                        }
                                        if ($brand && $brand->num_rows > 0) {
                                            $brandRow = $brand->fetch_assoc();
                                            $brandName = $brandRow['name'];
                                        } else {
                                            $brandName = $brandValue;
                                        }
                                    }

                                    $seriesValue = isset($row['series']) ? trim((string) $row['series']) : '';
                                    if ($seriesValue !== '' && $seriesValue !== '0') {
                                        $series = getData('name', "id='" . $seriesValue . "'", 'LIMIT 1', BRD_SERIES, $connect);
                                        if (!$series || $series->num_rows === 0) {
                                            $series = getData('name', "name='" . $seriesValue . "'", 'LIMIT 1', BRD_SERIES, $connect);
                                        }
                                        if ($series && $series->num_rows > 0) {
                                            $seriesRow = $series->fetch_assoc();
                                            $seriesName = $seriesRow['name'];
                                        } else {
                                            $seriesName = $seriesValue;
                                        }
                                    }
                                    ?>

                                    <tr>
                                        <th class="hideColumn" scope="row"><?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') ?></th>
                                        <th scope="row"><?= htmlspecialchars($num++, ENT_QUOTES, 'UTF-8') ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['buyer_username'], $row['remark'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
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
                                        <td scope="row"><?= customerLabelRenderNameCell(isset($row['buyer_username']) ? $row['buyer_username'] : '', isset($customerLabelMap[(int) $row['id']]) ? $customerLabelMap[(int) $row['id']] : array()) ?></td>
                                        <td scope="row"><?= customerLabelRenderSummaryCell(isset($customerLabelMap[(int) $row['id']]) ? $customerLabelMap[(int) $row['id']] : array()) ?></td>
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
