<?php
$pageTitle = "Customer Info";
$currentPagePin = 38;

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
include_once ROOT . '/include/customer_tag.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = CUS_INFO;
$pinAccess = checkCurrentPin($connect, $pageTitle);

$_SESSION['act'] = '';
$_SESSION['viewChk'] = '';
$_SESSION['delChk'] = '';
$num = 1;   // numbering

$redirect_page = $SITEURL . '/customerInfo.php';
$deleteRedirectPage = $SITEURL . '/customerInfoTable.php';

$result = getData('*', '', '', $tblName, $connect);
$tableRows = array();
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $tableRows[] = $row;
    }
}

$customerIds = array();
foreach ($tableRows as $row) {
    if (isset($row['id'])) {
        $customerIds[] = (int) $row['id'];
    }
}
$customerTagMap = customerTagGetCustomerTagMap($connect, 'customer_info', $customerIds);

// if (!$result) {
//     echo "<script type='text/javascript'>alert('Sorry, currently network temporary fail, please try again later.');</script>";
//     echo "<script>location.href ='$SITEURL/dashboard.php';</script>";
// }
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<script>
    preloader(300);

    $(document).ready(() => {
        createSortingTable('table', { searching: true });
        initCustomerRecordTableFilters({
            tableId: 'table',
            storageKey: 'customer_info_filters',
            panelStorageKey: 'customer_info_filter_panel_open',
            deferApply: true,
            selectFieldsMultiple: true,
            scopePaths: ['customerInfoTable.php', 'customerInfo.php'],
            filters: [
                { key: 'customer_label', label: 'Customer Label', attr: 'customer_label', type: 'select', placeholder: 'All Customer Labels' },
                { key: 'customer_tag', label: 'Tag', attr: 'customer_tag', type: 'select', placeholder: 'All Tags' },
                { key: 'country', label: 'Country', attr: 'country', type: 'select', placeholder: 'All Countries' },
                { key: 'sales_person', label: 'Sales Person In Charge', attr: 'sales_person', type: 'select', placeholder: 'All Sales Persons' },
                { key: 'gender', label: 'Gender', attr: 'gender', type: 'select', placeholder: 'All Genders' }
            ]
        });
    });
</script>

<style>
    .btn {
        padding: 0.2rem 0.5rem;
        font-size: 0.75rem;
        margin: 3px;
    }

    .btn-container {
        white-space: nowrap;
    }
</style>

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
                                        <?php echo $pageTitle ?> </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                if (!($result instanceof mysqli_result)) {
                    echo '<div class="text-center"><h4>No Result!</h4></div>';
                } else if (empty($tableRows)) {
                    echo '<div class="text-center"><h4>No Result!</h4></div>';
                } else {
                    $segmentationCache = array();
                    $countryCache = array();
                    $personInChargeCache = array();
                    ?>
                    <table class="table table-striped" id="table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Phone Number</th>
                                <th scope="col">Gender</th>
                                <th scope="col">Birthday</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            foreach ($tableRows as $row) {
                                if (isset($row['name'], $row['id']) && !empty($row['name'])) { ?>
                                    <?php
                                    $customerFullName = trim((string) (($row['name'] ?? '') . ' ' . ($row['last_name'] ?? '')));

                                    $customerLabelName = '';
                                    $segmentationValue = isset($row['default_segmentation']) ? trim((string) $row['default_segmentation']) : '';
                                    if ($segmentationValue !== '') {
                                        if (isset($segmentationCache[$segmentationValue])) {
                                            $customerLabelName = $segmentationCache[$segmentationValue];
                                        } else {
                                            $segmentationRst = getData('name', "id='" . mysqli_real_escape_string($connect, $segmentationValue) . "'", 'LIMIT 1', CUR_SEGMENTATION, $connect);
                                            if ($segmentationRst && $segmentationRst->num_rows > 0) {
                                                $customerLabelName = $segmentationRst->fetch_assoc()['name'];
                                            } else {
                                                $customerLabelName = $segmentationValue;
                                            }
                                            $segmentationCache[$segmentationValue] = $customerLabelName;
                                        }
                                    }

                                    $customerTagRows = isset($customerTagMap[(int) $row['id']]) ? $customerTagMap[(int) $row['id']] : array();
                                    $customerTagNames = customerRecordExtractTagNames($customerTagRows);
                                    $tagValue = isset($row['tags']) ? trim((string) $row['tags']) : '';
                                    if (empty($customerTagNames) && $tagValue !== '') {
                                        $tagRst = getData('name', "id='" . mysqli_real_escape_string($connect, $tagValue) . "'", 'LIMIT 1', TAG, $connect);
                                        if ($tagRst && $tagRst->num_rows > 0) {
                                            $customerTagNames = customerRecordNormalizeFilterValues(array($tagRst->fetch_assoc()['name']));
                                        } else {
                                            $customerTagNames = customerRecordNormalizeFilterValues(array($tagValue));
                                        }
                                    }

                                    $countryName = '';
                                    $countryValue = isset($row['shipping_country_region']) ? trim((string) $row['shipping_country_region']) : '';
                                    if ($countryValue !== '') {
                                        if (isset($countryCache[$countryValue])) {
                                            $countryName = $countryCache[$countryValue];
                                        } else {
                                            $countryRst = getData('nicename', "id='" . mysqli_real_escape_string($connect, $countryValue) . "'", 'LIMIT 1', COUNTRIES, $connect);
                                            if ($countryRst && $countryRst->num_rows > 0) {
                                                $countryName = $countryRst->fetch_assoc()['nicename'];
                                            } else {
                                                $countryName = $countryValue;
                                            }
                                            $countryCache[$countryValue] = $countryName;
                                        }
                                    }

                                    $personInChargeName = '';
                                    $personInChargeValue = isset($row['person_in_charges']) ? trim((string) $row['person_in_charges']) : '';
                                    if ($personInChargeValue !== '') {
                                        if (isset($personInChargeCache[$personInChargeValue])) {
                                            $personInChargeName = $personInChargeCache[$personInChargeValue];
                                        } else {
                                            $personInChargeRst = getData('name', "id='" . mysqli_real_escape_string($connect, $personInChargeValue) . "'", 'LIMIT 1', USR_USER, $connect);
                                            if ($personInChargeRst && $personInChargeRst->num_rows > 0) {
                                                $personInChargeName = $personInChargeRst->fetch_assoc()['name'];
                                            } else {
                                                $personInChargeName = $personInChargeValue;
                                            }
                                            $personInChargeCache[$personInChargeValue] = $personInChargeName;
                                        }
                                    }

                                    $filterAttributes = customerRecordBuildFilterDataAttributes(array(
                                        'customer_name' => $customerFullName,
                                        'customer_label' => $customerLabelName,
                                        'customer_tag' => $customerTagNames,
                                        'country' => $countryName,
                                        'sales_person' => $personInChargeName,
                                        'gender' => isset($row['gender']) ? $row['gender'] : '',
                                    ));
                                    ?>
                                    <tr <?= $filterAttributes ?>>
                                        <th class="hideColumn" scope="row"><?= $row['id'] ?></th>
                                        <th scope="row"><?= $num++; ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['last_name'], $row['email'], $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                            <?php
                                            $urbanismAction = getUrbanismMemberActionData(
                                                $connect,
                                                '',
                                                $customerFullName,
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
                                            <?php if (isset($row['name'], $row['last_name']))
                                                echo $row['name'] . " " . $row['last_name'] ?>
                                            </td>
                                            <td scope="row"><?php if (isset($row['email']))
                                                echo $row['email'] ?></td>
                                            <td scope="row">
                                            <?php if (isset($row['phone_country'], $row['phone_number']))
                                                echo $row['phone_country'] . $row['phone_number'] ?>
                                            </td>
                                            <td scope="row"><?php if (isset($row['gender']))
                                                echo $row['gender'] ?></td>
                                            <td scope="row"><?php if (isset($row['birthday']))
                                                echo $row['birthday'] ?></td>
                                        </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col">S/N</th>
                                <th scope="col" id="action_col">Action</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Phone Number</th>
                                <th scope="col">Gender</th>
                                <th scope="col">Birthday</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        //Initial Page And Action Value
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        //to solve the issue of dropdown menu displaying inside the table when table class include table-responsive
        dropdownMenuDispFix();
        //to resize table with bootstrap 5 classes
        datatableAlignment('table');
        setButtonColor();
    </script>
</body>

</html>
