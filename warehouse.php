<?php
$currentPagePin = 16;
$pageTitle = "Warehouse";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = WHSE;
$pinAccess = checkCurrentPin($connect, $pageTitle);

$isStockBalanceView = (trim((string) input('view')) === 'stock_balance');
if ($isStockBalanceView) {
    $tablePage = $SITEURL . '/warehouse_table.php';
    $redirectLink = "<script>location.href='" . $tablePage . "';</script>";
    $warehouseId = !empty(input('id')) ? (int) input('id') : 0;

    if ($warehouseId <= 0 || !isActionAllowed('View', $pinAccess)) {
        echo $redirectLink;
        exit;
    }

    $warehouseRst = getData('*', "id='" . $warehouseId . "' AND status='A'", 'LIMIT 1', $tblName, $connect);
    if (!$warehouseRst || $warehouseRst->num_rows === 0) {
        echo $redirectLink;
        exit;
    }
    $warehouseRow = $warehouseRst->fetch_assoc();

    // To avoid running "SHOW COLUMNS" on every request, cache the detected column
    // in a static variable and, when possible, in the session.
    if (!function_exists('detectProductPriceColumn')) {
        function detectProductPriceColumn($connect)
        {
            // In-process cache for the current PHP execution context.
            static $cachedPriceColumn = null;
            if ($cachedPriceColumn !== null) {
                return $cachedPriceColumn;
            }

            // Session cache
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['prod_price_column'])) {
                $cachedPriceColumn = (string) $_SESSION['prod_price_column'];
                return $cachedPriceColumn;
            }

            $priceColumn = '';
            $priceCandidates = array('price', 'selling_price', 'sale_price', 'unit_price', 'cost', 'cost_price');
            $colRst = mysqli_query($connect, "SHOW COLUMNS FROM " . PROD);
            if ($colRst) {
                $availableCols = array();
                while ($col = mysqli_fetch_assoc($colRst)) {
                    $field = isset($col['Field']) ? strtolower(trim((string) $col['Field'])) : '';
                    if ($field !== '') {
                        $availableCols[$field] = true;
                    }
                }
                foreach ($priceCandidates as $candidate) {
                    if (isset($availableCols[$candidate])) {
                        $priceColumn = $candidate;
                        break;
                    }
                }
            }
            
            if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['prod_price_column'] = $priceColumn;
            }
            
            $cachedPriceColumn = $priceColumn;
            return $cachedPriceColumn;
        }
    }
    
    $priceColumn = detectProductPriceColumn($connect);

    $productMap = array();
    $productSql = "SELECT id, name";
    if ($priceColumn !== '') {
        $productSql .= ", `" . $priceColumn . "` AS unit_price";
    }
    $productSql .= " FROM " . PROD . " WHERE status='A'";

    $productRst = mysqli_query($connect, $productSql);
    if ($productRst) {
        while ($p = mysqli_fetch_assoc($productRst)) {
            $pid = isset($p['id']) ? (int) $p['id'] : 0;
            if ($pid <= 0) {
                continue;
            }
            $productMap[$pid] = array(
                'name' => isset($p['name']) ? (string) $p['name'] : '',
                'unit_price' => isset($p['unit_price']) ? (float) $p['unit_price'] : 0,
            );
        }
    }

    $stockRows = array();
    $grandTotalPrice = 0.00;
    if (function_exists('siEnsureStockOutBatchUsageTable')) {
        $sobuReady = function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['sobu_ready']);
        if (!$sobuReady && siEnsureStockOutBatchUsageTable($finance_connect) && function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['sobu_ready'] = 1;
        }
    }
    $stockSql = "SELECT
                    i.product_id,
                    SUM(
                        GREATEST(
                            CAST(IFNULL(i.product_quantity, 0) AS SIGNED) - IFNULL(u.used_qty, 0),
                            0
                        )
                    ) AS total_quantity,
                    MAX(
                        GREATEST(
                            IFNULL(
                                TIMESTAMP(
                                    COALESCE(i.update_date, o.update_date, i.create_date, o.create_date),
                                    COALESCE(i.update_time, o.update_time, i.create_time, o.create_time)
                                ),
                                '1000-01-01 00:00:00'
                            ),
                            IFNULL(u.last_used_at, '1000-01-01 00:00:00')
                        )
                    ) AS last_updated_at
                FROM `stock_in_order` o
                INNER JOIN `stock_in_order_item` i ON i.stock_in_order_id = o.id AND i.status='A'
                LEFT JOIN (
                    SELECT
                        stock_in_item_id,
                        SUM(used_quantity) AS used_qty,
                        MAX(TIMESTAMP(create_date, create_time)) AS last_used_at
                    FROM `" . STOCK_OUT_BATCH_USAGE . "`
                    WHERE status='A'
                    GROUP BY stock_in_item_id
                ) u ON u.stock_in_item_id = i.id
                WHERE o.status='A'
                  AND o.warehouse_id='" . $warehouseId . "'
                  AND COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') <> 'Stock Out'
                GROUP BY i.product_id
                HAVING SUM(
                    GREATEST(
                        CAST(IFNULL(i.product_quantity, 0) AS SIGNED) - IFNULL(u.used_qty, 0),
                        0
                    )
                ) > 0
                ORDER BY i.product_id ASC";
    $stockRst = mysqli_query($finance_connect, $stockSql);
    if ($stockRst) {
        while ($r = mysqli_fetch_assoc($stockRst)) {
            $productId = isset($r['product_id']) ? (int) $r['product_id'] : 0;
            $qty = isset($r['total_quantity']) ? (int) $r['total_quantity'] : 0;
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $productName = isset($productMap[$productId]['name']) ? (string) $productMap[$productId]['name'] : ('Product #' . $productId);
            $unitPrice = isset($productMap[$productId]['unit_price']) ? (float) $productMap[$productId]['unit_price'] : 0;
            $lineTotal = $unitPrice * $qty;
            $grandTotalPrice += $lineTotal;

            $lastUpdatedRaw = isset($r['last_updated_at']) ? (string) $r['last_updated_at'] : '';
            $lastUpdatedDisplay = '';
            if ($lastUpdatedRaw !== '') {
                $ts = strtotime($lastUpdatedRaw);
                $lastUpdatedDisplay = $ts !== false ? date('Y-m-d H:i:s', $ts) : $lastUpdatedRaw;
            }

            $stockRows[] = array(
                'product_name' => $productName,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal,
                'last_updated' => $lastUpdatedDisplay,
            );
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    </head>
    <body>
        <div class="pre-load-center">
            <div class="preloader"></div>
        </div>

        <div class="page-load-cover">
            <div class="d-flex flex-column my-3 ms-3">
                <p>
                    <a href="<?= $tablePage ?>">Warehouse</a>
                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                    View Stock Balance
                </p>
            </div>

            <div id="formContainer" class="container d-flex justify-content-center">
                <div class="col-12 col-md-10 formWidthAdjust">
                    <div class="form-group mb-4">
                        <h2>View Warehouse</h2>
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label">Warehouse Name</label>
                        <input class="form-control" type="text" value="<?= htmlspecialchars((string) $warehouseRow['name'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered" id="warehouseStockBalanceTable">
                            <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th>Product Name</th>
                                    <th width="140">Quantity</th>
                                    <th width="220">Product Cost Per Unit</th>
                                    <th width="180">Total Cost</th>
                                    <th width="220">Last Updated Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($stockRows) === 0) { ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No stock balance record found for this warehouse.</td>
                                    </tr>
                                <?php } else {
                                    $balNo = 1;
                                    foreach ($stockRows as $sRow) { ?>
                                        <tr>
                                            <td><?= $balNo++ ?></td>
                                            <td><?= htmlspecialchars((string) $sRow['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= (int) $sRow['quantity'] ?></td>
                                            <td><?= number_format((float) $sRow['unit_price'], 2, '.', '') ?></td>
                                            <td><?= number_format((float) $sRow['total_price'], 2, '.', '') ?></td>
                                            <td><?= htmlspecialchars((string) $sRow['last_updated'], ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                <?php }
                                } ?>
                            </tbody>
                            <?php if (count($stockRows) > 0) { ?>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Grand Total</th>
                                    <th><?= number_format((float) $grandTotalPrice, 2, '.', '') ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                            <?php } ?>
                        </table>
                    </div>

                    <?= commonRenderCreateUpdateInfo($warehouseRow, $connect, '') ?>

                    <div class="form-group mt-4 d-flex justify-content-center">
                        <form method="get" action="<?= $tablePage ?>" class="m-0">
                            <button class="btn btn-rounded btn-primary" type="submit" id="actionBtn">Back</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            var page = "Warehouse";
            var action = "View";

            checkCurrentPage(page, action);
            centerAlignment("formContainer");
            setButtonColor();
            preloader(300, action);
        </script>
    </body>
    </html>
    <?php
    exit;
}
$pinAccess = checkCurrentPin($connect, $pageTitle);

//Current Page Action And Data ID
$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirect_page = $SITEURL . '/warehouse_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;

//Checking The Page ID , Action , Pin Access Exist Or Not
if (!($dataID) && !($act) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

//Get The Data From Database
$rst = getData('*', "id = '$dataID'", '', $tblName, $connect);

//Checking Data Error When Retrieved From Database
if ($act != 'I' && (!$rst || !($row = $rst->fetch_assoc()))) {
    $errorExist = 1;
    // $_SESSION['tempValConfirmBox'] = true;
    $act = "F";
}

//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '', $dataID, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

//View Data
if ($dataID && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {

    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] <b>" . $row['name'] . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate'   => $cdate,
        'ctime'   => $ctime,
        'uid'     => USER_ID,
        'cby'     => USER_ID,
        'act_msg' => $viewActMsg,
        'page'    => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

//Edit And Add Data
if (post('actionBtn')) {

    $action = post('actionBtn');

    switch ($action) {
        case 'addData':
        case 'updData':

            $currentDataName = postSpaceFilter('currentDataName');

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataID)) {
                $err = "Duplicate record found for " . $pageTitle . " name.";
                break;
            }

            if ($action == 'addData') {
                try {
                    $_SESSION['tempValConfirmBox'] = true;

                    if ($currentDataName) {
                        array_push($newvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }

                    $query = "INSERT INTO " . $tblName . "(name,create_by,create_date,create_time) VALUES ('$currentDataName','" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($connect, $query);
                    $dataID = $connect->insert_id;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    if ($row['name'] != $currentDataName) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $currentDataName);
                        array_push($datafield, 'name');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        $query = "UPDATE " . $tblName . " SET name ='$currentDataName', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$dataID'";
                        $returnData = mysqli_query($connect, $query);
                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            // audit log
            if (isset($query)) {

                $log = [
                    'log_act'      => $pageAction,
                    'cdate'        => $cdate,
                    'ctime'        => $ctime,
                    'uid'          => USER_ID,
                    'cby'          => USER_ID,
                    'query_rec'    => $query,
                    'query_table'  => $tblName,
                    'page'         => $pageTitle,
                    'connect'      => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }
                audit_log($log);
            }

            break;

        case 'back':
            echo $clearLocalStorage . ' ' . $redirectLink;
            break;
    }
}

//Function(title, subtitle, page name, ajax url path, redirect path, action)
//To show action dialog after finish certain action (eg. edit)

if (isset($_SESSION['tempValConfirmBox'])) {
    unset($_SESSION['tempValConfirmBox']);
    echo $clearLocalStorage;
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
}

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">

        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php echo $pageActionTitle ?>
            </p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-8 col-md-6 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <div class="form-group mb-5">
                        <h2>
                            <?php echo $pageActionTitle ?>
                        </h2>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataName"><?php echo $pageTitle ?> Name</label>
                        <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?php if (isset($row['name'])) echo $row['name'] ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                        <div id="err_msg">
                            <span class="mt-n1" id="errorSpan"><?php if (isset($err)) echo $err; ?></span>
                        </div>
                    </div>

                    <?php if ($act != 'I' && isset($row) && is_array($row)) { ?>
                        <?= commonRenderCreateUpdateInfo($row, $connect, $act) ?>
                    <?php } ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        //Initial Page And Action Value
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ''; ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);
    </script>

</body>

</html>
