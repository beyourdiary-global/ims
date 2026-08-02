<?php
ob_start();

$pageTitle = "Barcode Generate";
$currentPagePin = 22;

include '../menuHeader.php';
include ROOT . '/checkCurrentPagePin.php';
include ROOT . '/include/access.php';
include ROOT . '/header/phpqrcode/qrlib.php';

$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if ($resolvedPageTitle !== '') {
    $pageTitle = $resolvedPageTitle;
}

$pinAccess = checkCurrentPin($connect, $pageTitle);

if (!function_exists('barcodeGeneratorRedirectToDashboard')) {
    function barcodeGeneratorRedirectToDashboard($siteUrl, $message = 'Sorry, currently network temporary fail, please try again later.')
    {
        renderNotificationScript($message, 'error', $siteUrl . '/dashboard.php');
        exit;
    }
}

if (!function_exists('barcodeGeneratorBuildQrImageDataUri')) {
    function barcodeGeneratorBuildQrImageDataUri($qrCodeUrl, $errorCorrectionLevel, $matrixPointSize)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ims_qr_');
        if ($tempFile === false) {
            return '';
        }

        QRcode::png($qrCodeUrl, $tempFile, $errorCorrectionLevel, $matrixPointSize, 2);
        $pngBinary = @file_get_contents($tempFile);
        @unlink($tempFile);

        if ($pngBinary === false || $pngBinary === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($pngBinary);
    }
}

$tblname = PROD;
$product_id = input('id');
$act = input('act');

if ($product_id) {
    $result = getData('*', "id = '$product_id'", '', $tblname, $connect);

    if ($result != false) {
        $dataExisted = 1;
        $row = $result->fetch_assoc();
    } else {
        barcodeGeneratorRedirectToDashboard($SITEURL);
    }
}

$errorCorrectionLevel = 'H';
$matrixPointSize = 2;

if (post('actionBtn')) {
    $action = post('actionBtn');

    switch ($action) {
        case 'generate':
            $product_name = postSpaceFilter('product');
            $product = postSpaceFilter('product_hidden');
            $page_no = postSpaceFilter('page_no');
            $warehouse = postSpaceFilter('warehouse');

            if (!$product && $product == '') {
                $err = 'Please select the product to generate barcode.';
            }

            if (!$page_no || !($page_no != '0')) {
                $err2 = 'Page Number cannot be empty or less than 1.';
            }

            if ($warehouse == 'noValue') {
                $err3 = 'Please select the warehouse to generate barcode';
            }

            if (($product && $product) && ($page_no || ($page_no != '0')) && ($warehouse != 'noValue')) {
                $rst_projInfo = getData("barcode_prefix,barcode_next_number", "id='1'", '', PROJ, $connect);
                if (!$rst_projInfo) {
                    barcodeGeneratorRedirectToDashboard($SITEURL);
                }

                $projInfo = $rst_projInfo->fetch_assoc();

                if ($projInfo) {
                    $barcode_next_number = (int) $projInfo['barcode_next_number'];
                    $finalBarcodeNo = $barcode_next_number + (int) $page_no;

                    echo '<div id="printArea" class="container2">';
                    for ($x = 1; $x <= (int) $page_no; $x++) {
                        $usr_id = $_SESSION['userid'];
                        $currentBarcodeNo = $barcode_next_number + $x;
                        $qrCodeUrl = siteUrlPath('stock/stockRecord.php') . '?barcode=' . rawurlencode((string) $currentBarcodeNo) . '&prdid=' . rawurlencode((string) $product) . '&whseid=' . rawurlencode((string) $warehouse) . '&usr_id=' . rawurlencode((string) $usr_id);
                        $qrImageDataUri = barcodeGeneratorBuildQrImageDataUri($qrCodeUrl, $errorCorrectionLevel, $matrixPointSize);

                        echo '<div class="column">';
                        if ($qrImageDataUri !== '') {
                            echo '<img src="' . $qrImageDataUri . '" alt="Barcode ' . htmlspecialchars((string) $currentBarcodeNo, ENT_QUOTES, 'UTF-8') . '" />';
                        }
                        echo '<p class="title">' . htmlspecialchars((string) $product_name, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars((string) $currentBarcodeNo, ENT_QUOTES, 'UTF-8') . '</p>';
                        echo '</div>';
                    }

                    $sqlupd = "UPDATE projects SET barcode_next_number = '" . $finalBarcodeNo . "' WHERE id = '1'";
                    mysqli_query($connect, $sqlupd);

                    audit_log([
                        'log_act'     => 'edit',
                        'uid'         => USER_ID,
                        'cby'         => USER_ID,
                        'query_rec'   => $sqlupd,
                        'query_table' => 'projects',
                        'page'        => $pageTitle,
                        'connect'     => $connect,
                        'oldval'      => "barcode_next_number=$barcode_next_number",
                        'changes'     => "barcode_next_number=$finalBarcodeNo",
                        'act_msg'     => USER_NAME . " generated " . (int) $page_no . " barcode(s) for product [<b> ID = " . $product . "</b> ] and advanced barcode_next_number to <b>$finalBarcodeNo</b>.",
                    ]);

                    echo '<script>
                        window.onload = function() {
                            var header = document.querySelector(".sticky-top");
                            var form = document.querySelector("form");
                            if (header) {
                                header.style.display = "none";
                            }
                            if (form) {
                                form.style.display = "none";
                            }
                            window.print();
                        };
                        window.onafterprint = function() {
                            var container = document.querySelector("#printArea");
                            if (container) {
                                container.innerHTML = "";
                            }

                            var header = document.querySelector(".sticky-top");
                            var form = document.querySelector("form");
                            if (header) {
                                header.style.display = "block";
                            }
                            if (form) {
                                form.style.display = "block";
                            }
                        };
                    </script>';
                    echo '</div>';
                }
            }
            break;
        case 'back':
            break;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/barcode_generator.css">
</head>

<body>

    <div class="container d-flex justify-content-center mt-2">
        <div class="col-8 col-md-6">
            <form id="prodForm" method="post" action="">
                <div class="row">
                    <div class="col-12">
                        <div class="form-group my-5">
                            <h2>
                                Generate Barcode
                            </h2>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6">
                        <div class="form-group autocomplete mb-3">
                            <label class="form-label form_lbl" id="pkg_lbl" for="product">Product Name</label>
                            <input class="form-control" type="text" name="product" id="product" value="<?php
                                                                                                        unset($echoVal);
                                                                                                        if (isset($product) && $product != '') {
                                                                                                            $echoVal = $product;
                                                                                                        } else if (isset($dataExisted)) {
                                                                                                            $echoVal = $row['id'];
                                                                                                        }

                                                                                                        if (isset($echoVal)) {
                                                                                                            $productNameResult = getData('name', "id = '$echoVal'", '', $tblname, $connect);
                                                                                                            if (!$productNameResult) {
                                                                                                                barcodeGeneratorRedirectToDashboard($SITEURL);
                                                                                                            }
                                                                                                            $productNameRow = $productNameResult->fetch_assoc();
                                                                                                            if (isset($productNameRow['name'])) {
                                                                                                                echo htmlspecialchars((string) $productNameRow['name'], ENT_QUOTES, 'UTF-8');
                                                                                                            }
                                                                                                        }
                                                                                                        ?>">

                            <input type="hidden" name="product_hidden" id="product_hidden" value="<?php
                                                                                                    if (isset($product) && $product != '') {
                                                                                                        echo htmlspecialchars((string) $product, ENT_QUOTES, 'UTF-8');
                                                                                                    } else if (isset($dataExisted) && isset($row['id'])) {
                                                                                                        echo htmlspecialchars((string) $row['id'], ENT_QUOTES, 'UTF-8');
                                                                                                    }
                                                                                                    ?>">

                            <div id="err_msg">
                                <span class="mt-n1"><?php if (isset($err)) {
                                                        echo htmlspecialchars((string) $err, ENT_QUOTES, 'UTF-8');
                                                    } ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group autocomplete mb-3">
                            <label class="form-label form_lbl" id="page_no_lbl" for="page_no">Page No.</label>
                            <input class="form-control" type="text" name="page_no" id="page_no" value="<?php
                                                                                                        if (isset($page_no)) {
                                                                                                            echo htmlspecialchars((string) $page_no, ENT_QUOTES, 'UTF-8');
                                                                                                        }
                                                                                                        ?>">
                            <div id="err_msg">
                                <span class="mt-n1"><?php if (isset($err2)) {
                                                        echo htmlspecialchars((string) $err2, ENT_QUOTES, 'UTF-8');
                                                    } ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="form-group mb-3">
                            <label class="form-label form_lbl" id="warehouse_lbl" for="warehouse">Warehouse</label>
                            <select class="form-select" name="warehouse" id="warehouse">
                                <option value="noValue" <?php if (!isset($warehouse)) {
                                                            echo 'selected';
                                                        } ?>>--Please Choose--</option>
                                <?php
                                $rst_warehouse_list = getData("id,name", '', '', WHSE, $connect);
                                if (!$rst_warehouse_list) {
                                    barcodeGeneratorRedirectToDashboard($SITEURL);
                                }
                                while ($warehouse_list = $rst_warehouse_list->fetch_assoc()) {
                                    $whse_id = $warehouse_list['id'];
                                    $whse_name = $warehouse_list['name'];

                                    $selected = '';
                                    if (isset($warehouse) && $warehouse == $whse_id) {
                                        $selected = "selected";
                                    }

                                    echo '<option value="' . htmlspecialchars((string) $whse_id, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars((string) $whse_name, ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                                ?>
                            </select>
                            <div id="err_msg">
                                <span class="mt-n1"><?php if (isset($err3)) {
                                                        echo htmlspecialchars((string) $err3, ENT_QUOTES, 'UTF-8');
                                                    } ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-12">
                        <div class="form-group mb-3 d-flex justify-content-center">
                            <button class="btn btn-lg btn-rounded btn-primary mx-2" name="actionBtn" id="actionBtn" value="generate">Generate</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

</body>
<script>
    const page = "<?= $pageTitle ?>";
    const action = "<?php echo isset($act) ? $act : ''; ?>";

    checkCurrentPage(page, action);
    setButtonColor();

    $(document).ready(function() {
        var packageName = $("#product");

        packageName.keyup(function() {
            var param = {
                search: $(this).val(),
                searchType: 'name',
                elementID: $(this).attr('id'),
                hiddenElementID: $(this).attr('id') + '_hidden',
                dbTable: '<?= $tblname ?>'
            };
            searchInput(param, '<?= $SITEURL ?>');
        });
        packageName.change(function() {
            if ($(this).val() == '') {
                $('#' + $(this).attr('id') + '_hidden').val('');
            }
        });
    });
</script>

</html>
