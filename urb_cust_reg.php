<?php
$currentPagePin = 0;
$pageTitle = "Urbanism Member";

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';
include_once ROOT . '/include/user_record_log.php';

$tblName = FB_CUST_DEALS;
$reg_tblName = URBAN_CUST_REG;

$dataID = input('id');
$act = input('act');

$pageAction = getPageAction($act);

$allowed_ext = array("png", "jpg", "jpeg", "svg", "pdf");

$default_initial_page = "Facebook Customer Record (Deals)";
$default_redirect_path = '/fb_cust_deals_table.php';
$returnPageInput = trim((string) input('return_page'));
$returnLabelInput = trim((string) input('return_label'));

$urbanismOrderSource = 'all';
$returnPageLower = strtolower($returnPageInput);
if ($returnPageLower !== '') {
    if (strpos($returnPageLower, 'shopee') !== false) {
        $urbanismOrderSource = 'shopee';
    } else if (strpos($returnPageLower, 'lazada') !== false) {
        $urbanismOrderSource = 'lazada';
    } else if (strpos($returnPageLower, 'website') !== false || strpos($returnPageLower, 'customerinfo') !== false) {
        $urbanismOrderSource = 'website';
    } else if (strpos($returnPageLower, 'fb_') !== false || strpos($returnPageLower, 'facebook') !== false) {
        $urbanismOrderSource = 'facebook';
    }
}

$redirect_path = $default_redirect_path;
if ($returnPageInput !== '') {
    $candidatePath = ltrim(str_replace('\\', '/', $returnPageInput), '/');
    if ($candidatePath !== '' && strpos($candidatePath, '..') === false && preg_match('/^[A-Za-z0-9_\/.\-]+\.php$/', $candidatePath)) {
        $redirect_path = '/' . $candidatePath;
    }
}

$initial_page_raw = $returnLabelInput !== '' ? $returnLabelInput : $default_initial_page;
$initial_page = htmlspecialchars($initial_page_raw, ENT_QUOTES, 'UTF-8');
$redirect_page = $SITEURL . $redirect_path;
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$img_path = img_server . 'urbanism_member_registration/';
$img_url = rtrim((string) $SITEURL, '/') . '/' . trim((string) $img_path, '/\\') . '/';
$img_fs_path = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . trim((string) $img_path, '/\\') . DIRECTORY_SEPARATOR;
$legacyUploadDir = trim((string) img_server, '/\\') . '/urbanism_member_registration';
if (!file_exists($img_fs_path)) {
    mkdir($img_fs_path, 0777, true);
}

$urbanismSeedName = trim((string) $dataID);
$urbanismSeedFbLink = '';

if ($dataID && $act== 'I') { //edit/remove/view
    $lookupCondition = "";
    if (ctype_digit((string) $dataID)) {
        $lookupCondition = "id='" . ((int) $dataID) . "'";
    } else {
        $escapedSeed = mysqli_real_escape_string($connect, (string) $dataID);
        $lookupCondition = "name='" . $escapedSeed . "'";
    }

    $rst = getData('*', $lookupCondition, 'LIMIT 1', $tblName, $connect);
    if ($rst != false && $rst->num_rows > 0) {
        $sourceRow = $rst->fetch_assoc();
        if (isset($sourceRow['name']) && trim((string) $sourceRow['name']) !== '') {
            $urbanismSeedName = trim((string) $sourceRow['name']);
        }
        if (isset($sourceRow['fb_link']) && trim((string) $sourceRow['fb_link']) !== '') {
            $urbanismSeedFbLink = trim((string) $sourceRow['fb_link']);
        }
    }
}else if ($dataID) { //edit/remove/view
    $lookupCondition = '';
    if (ctype_digit((string) $dataID)) {
        $lookupCondition = "id='" . ((int) $dataID) . "'";
    } else {
        $escapedDataID = mysqli_real_escape_string($connect, (string) $dataID);
        $lookupCondition = "name='" . $escapedDataID . "'";
    }

    $rst = getData('*', $lookupCondition, 'LIMIT 1', $reg_tblName, $connect);

    if (($rst == false || $rst->num_rows === 0) && !ctype_digit((string) $dataID)) {
        $normalizedDataID = strtolower(trim((string) $dataID));
        if ($normalizedDataID !== '') {
            $rst = getData('*', "LOWER(TRIM(name))='" . mysqli_real_escape_string($connect, $normalizedDataID) . "'", 'LIMIT 1', $reg_tblName, $connect);
        }
    }
 
    if ($rst != false && $rst->num_rows > 0) {
        $dataExisted = 1;
        $row = $rst->fetch_assoc();
        $urbanismSeedName = trim((string) $row['name']);
    } else {
        echo '<script>alert("Urbanism member record not found.");window.location.replace(' . json_encode($redirect_page) . ');</script>';
        exit;
    }
}

if (!($dataID) && !($act)) {
    echo '<script>
    alert("Invalid action.");
    window.location.href = "' . $redirect_page . '"; // Redirect to previous page
    </script>';
}

if ($urbanismSeedFbLink === '' && $urbanismSeedName !== '') {
    $seedNameEsc = mysqli_real_escape_string($connect, (string) $urbanismSeedName);
    $seedRst = getData('fb_link', "name='" . $seedNameEsc . "'", 'LIMIT 1', FB_CUST_DEALS, $connect);
    if ($seedRst && $seedRst->num_rows > 0) {
        $seedRow = $seedRst->fetch_assoc();
        if (isset($seedRow['fb_link']) && trim((string) $seedRow['fb_link']) !== '') {
            $urbanismSeedFbLink = trim((string) $seedRow['fb_link']);
        }
    }
}

if ($dataID && isset($_GET['open_order_id'])) {
    $openOrderId = (int) $_GET['open_order_id'];
    if ($openOrderId > 0) {
        $orderWhere = "id='" . $openOrderId . "' AND status='A'";
        if ($urbanismSeedName !== '') {
            $orderWhere .= " AND name='" . mysqli_real_escape_string($finance_connect, $urbanismSeedName) . "'";
            if ($urbanismSeedFbLink !== '') {
                $orderWhere .= " AND fb_link='" . mysqli_real_escape_string($finance_connect, $urbanismSeedFbLink) . "'";
            }
        } else {
            $orderWhere .= " AND 1=0";
        }

        $orderRst = getData('id,name,fb_link', $orderWhere, 'LIMIT 1', FB_ORDER_REQ, $finance_connect);
        if ($orderRst && $orderRst->num_rows > 0) {
            $orderRow = $orderRst->fetch_assoc();
            $orderNo = '#'. $openOrderId;
            if (!empty($orderRow['name'])) {
                $orderNo .= ' - ' . $orderRow['name'];
            }
            $log = [
                'log_act' => 'View',
                'cdate' => $cdate,
                'ctime' => $ctime,
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => "order_id=" . $openOrderId,
                'query_table' => FB_ORDER_REQ,
                'act_msg' => USER_NAME . " opened Facebook order detail [<b>" . $orderNo . "</b>] from <b><i>" . $pageTitle . "</i></b>.",
                'page' => $pageTitle,
                'connect' => $connect,
            ];
            audit_log($log);

            echo "<script>location.href='" . $SITEURL . "/finance/fb_order_req.php?id=" . $openOrderId . "&act=E';</script>";
            exit;
        }
    }
}

if (post('actionBtn')) {
    
    $action = post('actionBtn');

    $umr_name = postSpaceFilter('umr_name_hidden');
    $umr_ic = postSpaceFilter('umr_ic');
    $umr_add = postSpaceFilter('umr_add');
    $umr_date = postSpaceFilter('umr_date');
    $umr_attach = null;

    if (isset($_FILES["umr_attach"]) && $_FILES["umr_attach"]["size"] != 0) {
        $umr_attach = $_FILES["umr_attach"]["name"];
    } elseif (isset($_POST['umr_attachmentValue'])) {
        $umr_attach = $_POST['umr_attachmentValue'];
    }

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();
    $uploadedNewAttachment = false;

    switch ($action) {
        case 'addRecord':
        case 'updRecord':

            if ($_FILES["umr_attach"]["size"] != 0) {
                // move file
                $umr_file_name = $_FILES["umr_attach"]["name"];
                $umr_file_tmp_name = $_FILES["umr_attach"]["tmp_name"];
                $img_ext = pathinfo($umr_file_name, PATHINFO_EXTENSION);
                $img_ext_lc = strtolower($img_ext);

                if (in_array($img_ext_lc, $allowed_ext)) {
                    $highestNumber = 0;
                    $files = glob($img_fs_path . $umr_name . '_' . $umr_ic . '_' . $umr_date . '_*.' . $img_ext);

                    foreach ($files as $file) {
                        $filename = basename($file);

                        // Adjust the regex to match the new file naming convention
                        if (preg_match('/' . preg_quote($umr_name . '_' . $umr_ic . '_' . $umr_date, '/') . '_(\d+)\.' . preg_quote($img_ext, '/') . '$/', $filename, $matches)) {
                            $number = (int) $matches[1];
                            $highestNumber = max($highestNumber, $number);
                        }
                    }

                    $unique_id = $highestNumber + 1;
                    $new_file_name = $umr_name . '_' . $umr_ic . '_' . $umr_date . '_' . $unique_id . '.' . $img_ext_lc;

                    // Move the uploaded file
                    if (move_uploaded_file($umr_file_tmp_name, $img_fs_path . $new_file_name)) {
                        $umr_attach = $new_file_name; // Update $umr_attach with the new filename
                        $uploadedNewAttachment = true;
                    } else {
                        $err2 = "Failed to upload the file.";
                    }
                } else {
                    $err2 = "Only allow PNG, JPG, JPEG, SVG or PDF file";
                }
            }

            if (!$umr_name) {
                $name_err = "Name is required!";
                break;
            } else if (!$umr_ic) {
                $ic_err = "IC is required!";
                break;
            } else if (!$umr_date) {
                $date_err = "Date is required!";
                break;
            } else if (!$umr_add) {
                $pic_err = "Address is required!";
                break;
            } else if (!$umr_attach) {
                $attach_err = "Please attach a copy of your IC/Driving License.";
                break;
            } else if ($action == 'addRecord') {
                try {
                    //check values
                    if ($umr_name) {
                        array_push($newvalarr, $umr_name);
                        array_push($datafield, 'name');
                    }
                    if ($umr_ic) {
                        array_push($newvalarr, $umr_ic);
                        array_push($datafield, 'ic');
                    }

                    if ($umr_add) {
                        array_push($newvalarr, $umr_add);
                        array_push($datafield, 'address');
                    }

                    if ($umr_date) {
                        array_push($newvalarr, $umr_date);
                        array_push($datafield, 'date');
                    }

                    if ($umr_attach) {
                        array_push($newvalarr, $umr_attach);
                        array_push($datafield, 'attachment');
                    }

                    $query = "INSERT INTO " . $reg_tblName . "(name,ic,address,reg_date,attachment,create_by,create_date,create_time) VALUES ('$umr_name','$umr_ic','$umr_add','$umr_date','$umr_attach','" . USER_ID . "',curdate(),curtime())";
                    // Execute the query
                    $returnData = mysqli_query($connect, $query);
                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $escapedDataID = mysqli_real_escape_string($connect, (string) $dataID);
                    $currentWhere = '';
                    if (ctype_digit((string) $dataID)) {
                        $currentWhere = "id='" . ((int) $dataID) . "'";
                    } else {
                        $currentWhere = "name='" . $escapedDataID . "'";
                    }

                    $rst = getData('*', $currentWhere, 'LIMIT 1', $reg_tblName, $connect);
                    if (!$rst || $rst->num_rows === 0) {
                        throw new Exception('Urbanism member record not found.');
                    }
                    $row = $rst->fetch_assoc();
                    $currentRowId = isset($row['id']) ? (int) $row['id'] : 0;
                    if ($currentRowId <= 0) {
                        throw new Exception('Urbanism member record id is invalid.');
                    }

                    // check value
                    if ($row['name'] != $umr_name) {
                        array_push($oldvalarr, $row['name']);
                        array_push($chgvalarr, $umr_name);
                        array_push($datafield, 'name');
                    }

                    if ($row['ic'] != $umr_ic) {
                        array_push($oldvalarr, $row['ic']);
                        array_push($chgvalarr, $umr_ic);
                        array_push($datafield, 'fb ic');
                    }

                    if ($row['address'] != $umr_add) {
                        array_push($oldvalarr, $row['address']);
                        array_push($chgvalarr, $umr_add);
                        array_push($datafield, 'address');
                    }

                    if ($row['reg_date'] != $umr_date) {
                        array_push($oldvalarr, $row['reg_date']);
                        array_push($chgvalarr, $umr_date);
                        array_push($datafield, 'date');
                    }

                    if ($row['attachment'] != $umr_attach) {
                        array_push($oldvalarr, $row['attachment']);
                        array_push($chgvalarr, $umr_attach);
                        array_push($datafield, 'attachment');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $reg_tblName . " SET name = '$umr_name', ic = '$umr_ic', address = '$umr_add', reg_date = '$umr_date', attachment = '$umr_attach', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id='" . $currentRowId . "'";
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
                    'log_act' => $pageAction,
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $query,
                    'query_table' => $reg_tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, $newvalarr, '', '', $reg_tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataID, $datafield, '', $oldvalarr, $chgvalarr, $reg_tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }
                audit_log($log);
            }

            break;

        case 'back':
            echo $clearLocalStorage . ' ' . $redirectLink;
            break;
    }
}

$urbanismFormName = '';
if (isset($umr_name) && trim((string) $umr_name) !== '') {
    $urbanismFormName = trim((string) $umr_name);
} else if (isset($row['name']) && trim((string) $row['name']) !== '') {
    $urbanismFormName = trim((string) $row['name']);
} else if ($urbanismSeedName !== '') {
    $urbanismFormName = $urbanismSeedName;
}

$urbanismOrderFbLink = $urbanismSeedFbLink;
if ($urbanismOrderFbLink === '' && $urbanismFormName !== '') {
    $nameEsc = mysqli_real_escape_string($connect, (string) $urbanismFormName);
    $dealRst = getData('fb_link', "name='" . $nameEsc . "'", 'LIMIT 1', FB_CUST_DEALS, $connect);
    if ($dealRst && $dealRst->num_rows > 0) {
        $dealRow = $dealRst->fetch_assoc();
        if (isset($dealRow['fb_link']) && trim((string) $dealRow['fb_link']) !== '') {
            $urbanismOrderFbLink = trim((string) $dealRow['fb_link']);
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">

</head>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirect_page ?>">
                    <?= $initial_page ?>
                </a> <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?php
              
                echo displayPageAction($act, $pageTitle);
                ?>
            </p>

        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-6 col-md-6 formWidthAdjust">
                <form id="Form" method="post" action="" enctype="multipart/form-data">
                    <div class="form-group mb-5">
                        <h2>
                            <?php
                            echo displayPageAction($act, $pageTitle);
                            ?>
                        </h2>
                    </div>

                    <div id="err_msg" class="mb-3">
                        <span class="mt-n2" style="font-size: 21px;">
                            <?php if (isset($err1))
                                echo $err1; ?>
                        </span>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="umr_name_lbl" for="umr_name">Name<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="text" name="umr_name" id="umr_name" value="<?php echo htmlspecialchars($urbanismFormName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo 'disabled' ?>>
                                <input type="hidden" name="umr_name_hidden" id="umr_name_hidden"
                                    value="<?php echo htmlspecialchars($urbanismFormName, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if (isset($name_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $name_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="umr_ic_lbl" for="umr_ic">IC<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="text" name="umr_ic" id="umr_ic" value="<?php
                                if (isset($dataExisted) && isset($row['ic']) && !isset($umr_ic)) {
                                    echo $row['ic'];
                                } else if (isset($umr_ic)) {
                                    echo $umr_ic;
                                }
                                ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($ic_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $ic_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label form_lbl" id="umr_add_lbl" for="umr_add"> Address<span
                                        class="requireRed">*</span></label>
                                <input class="form-control" type="text" name="umr_add" id="umr_add" value="<?php
                                if (isset($dataExisted) && isset($row['address']) && !isset($umr_add)) {
                                    echo $row['address'];
                                } else if (isset($umr_add)) {
                                    echo $umr_add;
                                }
                                ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($add_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $add_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label form_lbl" id="umr_date_label" for="umr_date">Registration
                                    Date<span class="requireRed">*</span></label>
                                <input class="form-control" type="date" name="umr_date" id="umr_date" value="<?php
                                if (isset($dataExisted) && isset($row['reg_date']) && !isset($umr_date)) {
                                    echo $row['reg_date'];
                                } else if (isset($umr_date)) {
                                    echo $umr_date;
                                }
                                ?>" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}" <?php if ($act == '')
                                    echo 'disabled' ?>>
                                <?php if (isset($date_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $date_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>

                            </div>

                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" id="umr_attach_lbl" for="umr_attach">IC/Driving
                                    License Attachment*</label>
                                <input class="form-control" type="file" name="umr_attach" id="umr_attach" <?php if ($act == '')
                                    echo 'disabled' ?>>

                                <?php if (isset($row['attachment']) && $row['attachment']) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo "Current Attachment: " . htmlspecialchars($row['attachment']); ?>
                                        </span>
                                    </div>
                                    <input type="hidden" name="existing_attachment"
                                        value="<?php echo htmlspecialchars($row['attachment']); ?>">
                                <?php } ?>

                                <?php if (isset($attach_err)) { ?>
                                    <div id="err_msg">
                                        <span class="mt-n1">
                                            <?php echo $attach_err; ?>
                                        </span>
                                    </div>
                                <?php } ?>

                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex justify-content-center justify-content-md-end px-4">
                                    <?php
                                    $attachmentSrc = '';

                                    if (isset($dataExisted) && isset($row['attachment']) && !isset($umr_attach)) {
                                        if ($row['attachment'] == '' || $row['attachment'] == NULL) {
                                            $attachmentSrc = '';
                                        } else {
                                            $storedAttachment = trim(str_replace('\\', '/', (string) $row['attachment']), '/');
                                            if (strpos($storedAttachment, 'attachment/') === 0) {
                                                $attachmentSrc = rtrim((string) $SITEURL, '/') . '/' . $storedAttachment;
                                            } else {
                                                $attachmentSrc = $img_url . basename($storedAttachment);
                                            }
                                        }
                                    } else if (isset($umr_attach)) {
                                        $storedAttachment = trim(str_replace('\\', '/', (string) $umr_attach), '/');
                                        if (strpos($storedAttachment, 'attachment/') === 0) {
                                            $attachmentSrc = rtrim((string) $SITEURL, '/') . '/' . $storedAttachment;
                                        } else {
                                            $attachmentSrc = $img_url . basename($storedAttachment);
                                        }
                                    }
                                    ?>
                                    <img id="umr_attach_preview" name="umr_attach_preview"
                                        src="<?php echo $attachmentSrc; ?>" class="img-thumbnail"
                                        alt="Attachment Preview">
                                    <input type="hidden" name="umr_attachmentValue" id="umr_attachmentValue" value="<?php if (isset($row['attachment']))
                                        echo $row['attachment']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    if ($act === 'E' && $dataID && $urbanismFormName !== '') {
                        $orderRows = array();
                        $sumFinalAmount = 0.00;
                        $normalizedUrbanName = trim((string) $urbanismFormName);
                        $safeUrbanName = mysqli_real_escape_string($finance_connect, $normalizedUrbanName);
                        $safeUrbanNameCms = mysqli_real_escape_string($connect, $normalizedUrbanName);

                        // 1) Facebook order requests
                        if (($urbanismOrderSource === 'all' || $urbanismOrderSource === 'facebook') && $safeUrbanName !== '') {
                            $fbWhere = array();
                            $fbWhere[] = "name='" . $safeUrbanName . "'";
                            if ($urbanismOrderFbLink !== '') {
                                $fbWhere[] = "fb_link='" . mysqli_real_escape_string($finance_connect, trim((string) $urbanismOrderFbLink)) . "'";
                            }

                            $fbOrderSql = "SELECT * FROM " . FB_ORDER_REQ . " WHERE status='A' AND (" . implode(' OR ', $fbWhere) . ") ORDER BY id DESC";
                            $fbOrderRst = mysqli_query($finance_connect, $fbOrderSql);
                            if ($fbOrderRst && $fbOrderRst->num_rows > 0) {
                                while ($fbOrderRow = $fbOrderRst->fetch_assoc()) {
                                    $orderRows[] = array(
                                        'source' => 'facebook',
                                        'id' => isset($fbOrderRow['id']) ? (int) $fbOrderRow['id'] : 0,
                                        'order_no' => 'FB-' . (isset($fbOrderRow['id']) ? (int) $fbOrderRow['id'] : 0),
                                        'order_date' => isset($fbOrderRow['create_date']) ? (string) $fbOrderRow['create_date'] : '',
                                        'package' => commonResolvePackageNamesFromCsv(isset($fbOrderRow['package']) ? $fbOrderRow['package'] : '', $connect),
                                        'buyer_pay_method' => commonResolvePaymentMethodName(isset($fbOrderRow['pay_method']) ? $fbOrderRow['pay_method'] : '', $finance_connect),
                                        'fees' => '0.00',
                                        'final_amount' => isset($fbOrderRow['price']) ? (string) $fbOrderRow['price'] : '0.00',
                                        'detail_url' => $SITEURL . '/finance/fb_order_req.php?id=' . (isset($fbOrderRow['id']) ? (int) $fbOrderRow['id'] : 0) . '&act=E',
                                    );
                                    $sumFinalAmount += (float) (isset($fbOrderRow['price']) ? $fbOrderRow['price'] : 0);
                                }
                            } else if (!$fbOrderRst) {
                                error_log("Urbanism FB order list query failed: " . mysqli_error($finance_connect) . " SQL: " . $fbOrderSql);
                            }
                        }

                        // 2) Lazada order requests
                        if (($urbanismOrderSource === 'all' || $urbanismOrderSource === 'lazada') && $safeUrbanNameCms !== '') {
                            $lazadaWhere = "cust_name='" . $safeUrbanNameCms . "' OR ship_rec_name='" . $safeUrbanNameCms . "'";
                            $lazadaOrderSql = "SELECT * FROM " . LAZADA_ORDER_REQ . " WHERE status='A' AND (" . $lazadaWhere . ") ORDER BY id DESC";
                            $lazadaOrderRst = mysqli_query($connect, $lazadaOrderSql);
                            if ($lazadaOrderRst && $lazadaOrderRst->num_rows > 0) {
                                while ($lazadaOrderRow = $lazadaOrderRst->fetch_assoc()) {
                                    $orderRows[] = array(
                                        'source' => 'lazada',
                                        'id' => isset($lazadaOrderRow['id']) ? (int) $lazadaOrderRow['id'] : 0,
                                        'order_no' => isset($lazadaOrderRow['oder_number']) && trim((string) $lazadaOrderRow['oder_number']) !== ''
                                            ? (string) $lazadaOrderRow['oder_number']
                                            : ('LAZADA-' . (isset($lazadaOrderRow['id']) ? (int) $lazadaOrderRow['id'] : 0)),
                                        'order_date' => isset($lazadaOrderRow['create_date']) ? (string) $lazadaOrderRow['create_date'] : '',
                                        'package' => commonResolvePackageNamesFromCsv(isset($lazadaOrderRow['pkg']) ? $lazadaOrderRow['pkg'] : '', $connect),
                                        'buyer_pay_method' => commonResolvePaymentMethodName(isset($lazadaOrderRow['pay_meth']) ? $lazadaOrderRow['pay_meth'] : '', $finance_connect),
                                        'fees' => isset($lazadaOrderRow['pay_fee']) ? (string) $lazadaOrderRow['pay_fee'] : '0.00',
                                        'final_amount' => isset($lazadaOrderRow['final_income']) ? (string) $lazadaOrderRow['final_income'] : '0.00',
                                        'detail_url' => $SITEURL . '/lazada_order_req.php?id=' . (isset($lazadaOrderRow['id']) ? (int) $lazadaOrderRow['id'] : 0) . '&act=E',
                                    );
                                    $sumFinalAmount += (float) (isset($lazadaOrderRow['final_income']) ? $lazadaOrderRow['final_income'] : 0);
                                }
                            } else if (!$lazadaOrderRst) {
                                error_log("Urbanism Lazada order list query failed: " . mysqli_error($connect) . " SQL: " . $lazadaOrderSql);
                            }
                        }

                        // 3) Website order requests
                        if (($urbanismOrderSource === 'all' || $urbanismOrderSource === 'website') && $safeUrbanName !== '') {
                            $websiteWhere = "cust_name='" . $safeUrbanName . "' OR shipping_name='" . $safeUrbanName . "'";
                            $websiteOrderSql = "SELECT * FROM " . WEB_ORDER_REQ . " WHERE status='A' AND (" . $websiteWhere . ") ORDER BY id DESC";
                            $websiteOrderRst = mysqli_query($finance_connect, $websiteOrderSql);
                            if ($websiteOrderRst && $websiteOrderRst->num_rows > 0) {
                                while ($websiteOrderRow = $websiteOrderRst->fetch_assoc()) {
                                    $orderRows[] = array(
                                        'source' => 'website',
                                        'id' => isset($websiteOrderRow['id']) ? (int) $websiteOrderRow['id'] : 0,
                                        'order_no' => isset($websiteOrderRow['order_id']) && trim((string) $websiteOrderRow['order_id']) !== ''
                                            ? (string) $websiteOrderRow['order_id']
                                            : ('WEB-' . (isset($websiteOrderRow['id']) ? (int) $websiteOrderRow['id'] : 0)),
                                        'order_date' => isset($websiteOrderRow['create_date']) ? (string) $websiteOrderRow['create_date'] : '',
                                        'package' => commonResolvePackageNamesFromCsv(isset($websiteOrderRow['pkg']) ? $websiteOrderRow['pkg'] : '', $connect),
                                        'buyer_pay_method' => commonResolvePaymentMethodName(isset($websiteOrderRow['pay_method']) ? $websiteOrderRow['pay_method'] : '', $finance_connect),
                                        'fees' => isset($websiteOrderRow['shipping']) ? (string) $websiteOrderRow['shipping'] : '0.00',
                                        'final_amount' => isset($websiteOrderRow['total']) ? (string) $websiteOrderRow['total'] : '0.00',
                                        'detail_url' => $SITEURL . '/finance/website_order_request.php?id=' . (isset($websiteOrderRow['id']) ? (int) $websiteOrderRow['id'] : 0) . '&act=E',
                                    );
                                    $sumFinalAmount += (float) (isset($websiteOrderRow['total']) ? $websiteOrderRow['total'] : 0);
                                }
                            } else if (!$websiteOrderRst) {
                                error_log("Urbanism Website order list query failed: " . mysqli_error($finance_connect) . " SQL: " . $websiteOrderSql);
                            }
                        }

                        // 4) Shopee order requests (buyer may be username or SHOPEE_CUST_INFO.id)
                        $shopeeBuyerIds = array();
                        if (($urbanismOrderSource === 'all' || $urbanismOrderSource === 'shopee') && $safeUrbanName !== '') {
                            $buyerMapSql = "SELECT id FROM " . SHOPEE_CUST_INFO . " WHERE buyer_username='" . $safeUrbanName . "'";
                            $buyerMapRst = mysqli_query($finance_connect, $buyerMapSql);
                            if ($buyerMapRst && $buyerMapRst->num_rows > 0) {
                                while ($buyerMapRow = $buyerMapRst->fetch_assoc()) {
                                    $mappedId = isset($buyerMapRow['id']) ? (int) $buyerMapRow['id'] : 0;
                                    if ($mappedId > 0) {
                                        $shopeeBuyerIds[] = (string) $mappedId;
                                    }
                                }
                            }
                        }

                        $shopeeWhere = array();
                        if (($urbanismOrderSource === 'all' || $urbanismOrderSource === 'shopee') && $safeUrbanName !== '') {
                            $shopeeWhere[] = "buyer='" . $safeUrbanName . "'";
                        }
                        if (!empty($shopeeBuyerIds)) {
                            $safeBuyerIds = array();
                            foreach (array_unique($shopeeBuyerIds) as $buyerIdVal) {
                                if (ctype_digit((string) $buyerIdVal) && (int) $buyerIdVal > 0) {
                                    $safeBuyerIds[] = "'" . (int) $buyerIdVal . "'";
                                }
                            }
                            if (!empty($safeBuyerIds)) {
                                $shopeeWhere[] = "buyer IN (" . implode(',', $safeBuyerIds) . ")";
                            }
                        }

                        if (!empty($shopeeWhere)) {
                            $shopeeOrderSql = "SELECT * FROM " . SHOPEE_SG_ORDER_REQ . " WHERE status='A' AND (" . implode(' OR ', $shopeeWhere) . ") ORDER BY id DESC";
                            $shopeeOrderRst = mysqli_query($finance_connect, $shopeeOrderSql);
                            if ($shopeeOrderRst && $shopeeOrderRst->num_rows > 0) {
                                while ($shopeeOrderRow = $shopeeOrderRst->fetch_assoc()) {
                                    $orderRows[] = array(
                                        'source' => 'shopee',
                                        'id' => isset($shopeeOrderRow['id']) ? (int) $shopeeOrderRow['id'] : 0,
                                        'order_no' => isset($shopeeOrderRow['orderID']) ? (string) $shopeeOrderRow['orderID'] : ('SHOPEE-' . (isset($shopeeOrderRow['id']) ? (int) $shopeeOrderRow['id'] : 0)),
                                        'order_date' => isset($shopeeOrderRow['date']) ? (string) $shopeeOrderRow['date'] : '',
                                        'package' => commonResolvePackageNamesFromCsv(isset($shopeeOrderRow['package']) ? $shopeeOrderRow['package'] : '', $connect),
                                        'buyer_pay_method' => commonResolvePaymentMethodName(isset($shopeeOrderRow['buyer_pay_meth']) ? $shopeeOrderRow['buyer_pay_meth'] : '', $finance_connect),
                                        'fees' => isset($shopeeOrderRow['fees']) ? (string) $shopeeOrderRow['fees'] : '0.00',
                                        'final_amount' => isset($shopeeOrderRow['final_amt']) ? (string) $shopeeOrderRow['final_amt'] : '0.00',
                                        'detail_url' => $SITEURL . '/shopee/shopee_order_req.php?id=' . (isset($shopeeOrderRow['id']) ? (int) $shopeeOrderRow['id'] : 0) . '&act=E',
                                    );
                                    $sumFinalAmount += (float) (isset($shopeeOrderRow['final_amt']) ? $shopeeOrderRow['final_amt'] : 0);
                                }
                            } else if (!$shopeeOrderRst) {
                                error_log("Urbanism Shopee order list query failed: " . mysqli_error($finance_connect) . " SQL: " . $shopeeOrderSql);
                            }
                        }

                        usort($orderRows, function ($a, $b) {
                            $aDate = isset($a['order_date']) ? strtotime((string) $a['order_date']) : 0;
                            $bDate = isset($b['order_date']) ? strtotime((string) $b['order_date']) : 0;
                            if ($aDate === $bDate) {
                                return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
                            }
                            return $bDate <=> $aDate;
                        });
                    ?>
                    <div class="form-group mt-3">
                        <h5 class="mb-3">Order Records</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered mb-0" id="umr_order_tbl">
                                <thead>
                                    <tr>
                                        <th width="60">S/N</th>
                                        <th width="200">Action</th>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Package</th>
                                        <th>Buyer Payment Method</th>
                                        <th>Charges &amp; Fees</th>
                                        <th>Final Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orderRows)) {
                                        $orderSN = 1;
                                        foreach ($orderRows as $orderRow) {
                                            $orderNo = isset($orderRow['order_no']) ? $orderRow['order_no'] : '';
                                            $orderDate = isset($orderRow['order_date']) ? $orderRow['order_date'] : '';
                                            $orderPackage = isset($orderRow['package']) ? $orderRow['package'] : '';
                                            $buyerPayMethod = isset($orderRow['buyer_pay_method']) ? $orderRow['buyer_pay_method'] : '';
                                            $orderFees = isset($orderRow['fees']) ? $orderRow['fees'] : '0.00';
                                            $finalAmount = isset($orderRow['final_amount']) ? $orderRow['final_amount'] : '0.00';
                                            $detailUrl = isset($orderRow['detail_url']) ? (string) $orderRow['detail_url'] : '';
                                            ?>
                                            <tr>
                                                <td><?= $orderSN++ ?></td>
                                                <td>
                                                    <?php if ($detailUrl !== '') { ?>
                                                        <a class="btn btn-sm btn-rounded btn-primary" style="white-space:nowrap;" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">Show Order Detail</a>
                                                    <?php } else { ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php } ?>
                                                </td>
                                                <td><?= htmlspecialchars((string) $orderNo, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) $orderDate, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) $orderPackage, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string) $buyerPayMethod, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= commonFormatAmountRm($orderFees) ?></td>
                                                <td><?= commonFormatAmountRm($finalAmount) ?></td>
                                            </tr>
                                        <?php }
                                    } else { ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No order records found.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="7" class="text-end">Sub-Total (RM)</th>
                                        <th><?= commonFormatAmountRm($sumFinalAmount) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <?php } ?>

                </form>

                <?php
                /* User Record Log removed from Urbanism Member page */
                ?>

                <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                    <?php
                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" form="Form" name="actionBtn" id="actionBtn" value="addRecord">Add Record</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" form="Form" name="actionBtn" id="actionBtn" value="updRecord">Edit Record</button>';
                            break;
                    }
                    ?>
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" form="Form" name="actionBtn" id="actionBtn" value="back">Back</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    /*
        oufei 20231014
        common.fun.js
        function(title, subtitle, page name, ajax url path, redirect path, action)
        to show action dialog after finish certain action (eg. edit)
    */
    if (isset($_SESSION['tempValConfirmBox'])) {
        unset($_SESSION['tempValConfirmBox']);
        echo $clearLocalStorage;
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirect_page . '","' . $act . '");</script>';
    }
    ?>
    <script>
        (function () {
            function revealUrbanismPage() {
                var preloaders = document.querySelectorAll('.preloader');
                var preloadCenters = document.querySelectorAll('.pre-load-center');
                var pageCovers = document.querySelectorAll('.page-load-cover');

                for (var i = 0; i < preloaders.length; i++) {
                    preloaders[i].style.display = 'none';
                }
                for (var j = 0; j < preloadCenters.length; j++) {
                    preloadCenters[j].style.display = 'none';
                }
                for (var k = 0; k < pageCovers.length; k++) {
                    pageCovers[k].style.display = 'block';
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    setTimeout(revealUrbanismPage, 1200);
                });
            } else {
                setTimeout(revealUrbanismPage, 1200);
            }

            window.addEventListener('load', function () {
                setTimeout(revealUrbanismPage, 300);
            });
        })();
    </script>
    <script>
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        if (typeof checkCurrentPage === 'function') {
            checkCurrentPage(page, action);
        }

        if (typeof setButtonColor === 'function') {
            setButtonColor();
        }

        if (typeof preloader === 'function') {
            preloader(300, action);
        }

        <?php
        include "./js/urb_cust_reg.js"
            ?>
    </script>

</body>

</html>