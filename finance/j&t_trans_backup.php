<?php
$pageTitle = "J&T Transaction Backup Record";
$isFinance = 1;

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';

$tblName = 'jt_transaction_backup';
$itemTable = JT_TRANS_ITEM;
$gstTable = JT_TRANS_GST;

$dataID = input('id');
$act = input('act');
$pageAction = getPageAction($act);
$allowed_ext = array("png", "jpg", "jpeg", "svg", "pdf");

$redirect_page = $SITEURL . '/finance/j&t_trans_backup_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

$img_path = '../' . img_server . 'finance/j&t_trans_backup/';
if (!file_exists($img_path)) {
    mkdir($img_path, 0777, true);
}

$deliveryRows = array(
    array(
        'service_type' => '',
        'shipments_count' => '',
        'total_weight_kg' => '',
        'standard_charge' => '',
        'extra_charges' => '',
        'nett_charge' => '',
    )
);

$gstRows = array(
    array(
        'gst_type' => '',
        'gst_rate' => '',
        'gst_amount' => '',
        'gst_paid' => '',
    )
);

$currencyOptions = array();
$currencyRst = getData('id, unit', '', '', CUR_UNIT, $connect);
if ($currencyRst && $currencyRst->num_rows > 0) {
    while ($currencyRow = $currencyRst->fetch_assoc()) {
        $currencyUnitVal = trim((string) $currencyRow['unit']);
        if ($currencyUnitVal !== '') {
            $currencyOptions[] = $currencyUnitVal;
        }
    }
}
$currencyOptionsNormalized = array();
foreach ($currencyOptions as $currencyOption) {
    $normalizedCurrency = strtoupper(trim((string) $currencyOption));
    if ($normalizedCurrency !== '') {
        $currencyOptionsNormalized[$normalizedCurrency] = true;
    }
}

// to display data to input
if ($dataID) { //edit/remove/view
    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $finance_connect);

    if ($rst != false && $rst->num_rows > 0) {
        $dataExisted = 1;
        $row = $rst->fetch_assoc();

        $deliveryRows = array();
        $deliveryRst = mysqli_query($finance_connect, "SELECT service_type, shipments_count, total_weight_kg, standard_charge, extra_charges, nett_charge FROM `" . $itemTable . "` WHERE transaction_id='" . (int) $dataID . "' ORDER BY id ASC");
        if ($deliveryRst) {
            while ($deliveryRow = mysqli_fetch_assoc($deliveryRst)) {
                $deliveryRows[] = array(
                    'service_type' => isset($deliveryRow['service_type']) ? (string) $deliveryRow['service_type'] : '',
                    'shipments_count' => isset($deliveryRow['shipments_count']) ? (string) $deliveryRow['shipments_count'] : '',
                    'total_weight_kg' => isset($deliveryRow['total_weight_kg']) ? (string) $deliveryRow['total_weight_kg'] : '',
                    'standard_charge' => isset($deliveryRow['standard_charge']) ? (string) $deliveryRow['standard_charge'] : '',
                    'extra_charges' => isset($deliveryRow['extra_charges']) ? (string) $deliveryRow['extra_charges'] : '',
                    'nett_charge' => isset($deliveryRow['nett_charge']) ? (string) $deliveryRow['nett_charge'] : '',
                );
            }
        }

        if (count($deliveryRows) === 0) {
            $deliveryRows[] = array(
                'service_type' => '',
                'shipments_count' => '',
                'total_weight_kg' => '',
                'standard_charge' => '',
                'extra_charges' => '',
                'nett_charge' => '',
            );
        }

        $gstRows = array();
        $gstRst = mysqli_query($finance_connect, "SELECT type, rate, amount, gst_paid FROM `" . $gstTable . "` WHERE transaction_id='" . (int) $dataID . "' ORDER BY id ASC");
        if ($gstRst) {
            while ($gstRow = mysqli_fetch_assoc($gstRst)) {
                $gstRows[] = array(
                    'gst_type' => isset($gstRow['type']) ? (string) $gstRow['type'] : '',
                    'gst_rate' => isset($gstRow['rate']) ? (string) $gstRow['rate'] : '',
                    'gst_amount' => isset($gstRow['amount']) ? (string) $gstRow['amount'] : '',
                    'gst_paid' => isset($gstRow['gst_paid']) ? (string) $gstRow['gst_paid'] : '',
                );
            }
        }

        if (count($gstRows) === 0) {
            $gstRows[] = array(
                'gst_type' => '',
                'gst_rate' => '',
                'gst_amount' => '',
                'gst_paid' => '',
            );
        }
    } else {
        // If $rst is false or no data found ($act==null)
        $errorExist = 1;
        $_SESSION['tempValConfirmBox'] = true;
        $act = "F";
    }
}

if (!($dataID) && !($act)) {
    echo '<script>
    alert("Invalid action.");
    window.location.href = "' . $redirect_page . '"; // Redirect to previous page
    </script>';
}

if (post('actionBtn')) {
    $action = post('actionBtn');

    $jt_inv_number = postSpaceFilter("jt_inv_number");

    $jt_inv_date = postSpaceFilter('jt_inv_date');
    $currency = strtoupper(trim((string) postSpaceFilter('currency')));
    $total_gst = trim((string) postSpaceFilter('total_gst'));
    $total_amount = trim((string) postSpaceFilter('total_amount'));

    $serviceTypeArr = isset($_POST['service_type']) ? postSpaceFilter('service_type') : array();
    $shipmentsCountArr = isset($_POST['shipments_count']) ? postSpaceFilter('shipments_count') : array();
    $totalWeightKgArr = isset($_POST['total_weight_kg']) ? postSpaceFilter('total_weight_kg') : array();
    $standardChargeArr = isset($_POST['standard_charge']) ? postSpaceFilter('standard_charge') : array();
    $extraChargesArr = isset($_POST['extra_charges']) ? postSpaceFilter('extra_charges') : array();
    $nettChargeArr = isset($_POST['nett_charge']) ? postSpaceFilter('nett_charge') : array();

    $gstTypeArr = isset($_POST['gst_type']) ? postSpaceFilter('gst_type') : array();
    $gstRateArr = isset($_POST['gst_rate']) ? postSpaceFilter('gst_rate') : array();
    $gstAmountArr = isset($_POST['gst_amount']) ? postSpaceFilter('gst_amount') : array();
    $gstPaidArr = isset($_POST['gst_paid']) ? postSpaceFilter('gst_paid') : array();

    if (!is_array($serviceTypeArr)) {
        $serviceTypeArr = array();
    }
    if (!is_array($shipmentsCountArr)) {
        $shipmentsCountArr = array();
    }
    if (!is_array($totalWeightKgArr)) {
        $totalWeightKgArr = array();
    }
    if (!is_array($standardChargeArr)) {
        $standardChargeArr = array();
    }
    if (!is_array($extraChargesArr)) {
        $extraChargesArr = array();
    }
    if (!is_array($nettChargeArr)) {
        $nettChargeArr = array();
    }

    if (!is_array($gstTypeArr)) {
        $gstTypeArr = array();
    }
    if (!is_array($gstRateArr)) {
        $gstRateArr = array();
    }
    if (!is_array($gstAmountArr)) {
        $gstAmountArr = array();
    }
    if (!is_array($gstPaidArr)) {
        $gstPaidArr = array();
    }

    $deliveryMax = max(count($serviceTypeArr), count($shipmentsCountArr), count($totalWeightKgArr), count($standardChargeArr), count($extraChargesArr), count($nettChargeArr));
    $gstMax = max(count($gstTypeArr), count($gstRateArr), count($gstAmountArr), count($gstPaidArr));

    $computedTotalGst = 0.0;
    for ($i = 0; $i < $gstMax; $i++) {
        $rate = isset($gstRateArr[$i]) ? (float) $gstRateArr[$i] : 0.0;
        $amount = isset($gstAmountArr[$i]) ? (float) $gstAmountArr[$i] : 0.0;
        $gstPaid = ($rate > 0) ? ($amount * ($rate / 100)) : 0.0;
        $gstPaidArr[$i] = number_format($gstPaid, 2, '.', '');
        $computedTotalGst += $gstPaid;
    }

    $computedTotalAmount = 0.0;
    for ($i = 0; $i < $deliveryMax; $i++) {
        $standardCharge = isset($standardChargeArr[$i]) ? (float) $standardChargeArr[$i] : 0.0;
        $rowGstPaid = isset($gstPaidArr[$i]) ? (float) $gstPaidArr[$i] : 0.0;
        $nettCharge = $standardCharge + $rowGstPaid;
        $nettChargeArr[$i] = number_format($nettCharge, 2, '.', '');
        $computedTotalAmount += $nettCharge;
    }

    $total_gst = number_format($computedTotalGst, 2, '.', '');
    $total_amount = number_format($computedTotalAmount, 2, '.', '');

    $deliveryRows = array();
    for ($i = 0; $i < $deliveryMax; $i++) {
        $deliveryRows[] = array(
            'service_type' => isset($serviceTypeArr[$i]) ? trim((string) $serviceTypeArr[$i]) : '',
            'shipments_count' => isset($shipmentsCountArr[$i]) ? trim((string) $shipmentsCountArr[$i]) : '',
            'total_weight_kg' => isset($totalWeightKgArr[$i]) ? trim((string) $totalWeightKgArr[$i]) : '',
            'standard_charge' => isset($standardChargeArr[$i]) ? trim((string) $standardChargeArr[$i]) : '',
            'extra_charges' => isset($extraChargesArr[$i]) ? trim((string) $extraChargesArr[$i]) : '',
            'nett_charge' => isset($nettChargeArr[$i]) ? trim((string) $nettChargeArr[$i]) : '0.00',
        );
    }
    if (count($deliveryRows) === 0) {
        $deliveryRows[] = array(
            'service_type' => '',
            'shipments_count' => '',
            'total_weight_kg' => '',
            'standard_charge' => '',
            'extra_charges' => '',
            'nett_charge' => '0.00',
        );
    }

    $gstRows = array();
    for ($i = 0; $i < $gstMax; $i++) {
        $gstRows[] = array(
            'gst_type' => isset($gstTypeArr[$i]) ? trim((string) $gstTypeArr[$i]) : '',
            'gst_rate' => isset($gstRateArr[$i]) ? trim((string) $gstRateArr[$i]) : '',
            'gst_amount' => isset($gstAmountArr[$i]) ? trim((string) $gstAmountArr[$i]) : '',
            'gst_paid' => isset($gstPaidArr[$i]) ? trim((string) $gstPaidArr[$i]) : '0.00',
        );
    }
    if (count($gstRows) === 0) {
        $gstRows[] = array(
            'gst_type' => '',
            'gst_rate' => '',
            'gst_amount' => '',
            'gst_paid' => '0.00',
        );
    }

    $jt_attach = null;
    if (isset($_FILES["jt_attach"]) && $_FILES["jt_attach"]["size"] != 0) {
        $jt_attach = $_FILES["jt_attach"]["name"];
    } elseif (isset($_POST['jt_attachmentValue'])) {
        $jt_attach = $_POST['jt_attachmentValue'];
    }

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addTransaction':
        case 'updTransaction':
            if ($_FILES["jt_attach"]["size"] != 0) {
                // move file
                $jt_file_name = $_FILES["jt_attach"]["name"];
                $jt_file_tmp_name = $_FILES["jt_attach"]["tmp_name"];
                $img_ext = pathinfo($jt_file_name, PATHINFO_EXTENSION);
                $img_ext_lc = strtolower($img_ext);

                if (in_array($img_ext_lc, $allowed_ext)) {
                    $highestNumber = 0;
                    $files = glob($img_path . $jt_inv_date . '_' . $img_ext);

                    foreach ($files as $file) {
                        $filename = basename($file);

                        // Adjust the regex to match the new file naming convention
                        if (preg_match('/' . preg_quote($jt_inv_date . '_', '/') . '_(\d+)\.' . preg_quote($img_ext, '/') . '$/', $filename, $matches)) {
                            $number = (int)$matches[1];
                            $highestNumber = max($highestNumber, $number);
                        }
                    }

                    $unique_id = $highestNumber + 1;
                    $new_file_name = $jt_inv_date . '_' . $unique_id . '.' . $img_ext_lc;

                    // Move the uploaded file
                    if (move_uploaded_file($jt_file_tmp_name, $img_path . $new_file_name)) {
                        $jt_attach = $new_file_name; // Update jt_attach with the new filename
                    } else {
                        $err2 = "Failed to upload the file.";
                    }
                } else {
                    $err2 = "Only allow PNG, JPG, JPEG, SVG or PDF file";
                }
            }

            $isDuplicate = false;
            $shouldCheckDuplicate = ($action === 'addTransaction');
            if ($action === 'updTransaction' && !empty($dataID)) {
                $existingDupRst = getData('number, date', "id = '" . mysqli_real_escape_string($finance_connect, $dataID) . "'", 'LIMIT 1', $tblName, $finance_connect);
                if ($existingDupRst && $existingDupRst->num_rows > 0) {
                    $existingDupRow = $existingDupRst->fetch_assoc();
                    $oldInvNumber = isset($existingDupRow['number']) ? trim((string) $existingDupRow['number']) : '';
                    $oldInvDate = isset($existingDupRow['date']) ? trim((string) $existingDupRow['date']) : '';
                    $shouldCheckDuplicate = ($oldInvNumber !== trim((string) $jt_inv_number) || $oldInvDate !== trim((string) $jt_inv_date));
                }
            }

            if ($shouldCheckDuplicate && $jt_inv_number && $jt_inv_date) {
                $safe_inv_number = mysqli_real_escape_string($finance_connect, $jt_inv_number);
                $safe_inv_date = mysqli_real_escape_string($finance_connect, $jt_inv_date);
                $dupQuery = "SELECT id FROM `" . $tblName . "` WHERE number = '$safe_inv_number' AND date = '$safe_inv_date' AND status = 'A'";
                if (!empty($dataID)) {
                    $dupQuery .= " AND id != '" . mysqli_real_escape_string($finance_connect, $dataID) . "'";
                }
                $dupResult = mysqli_query($finance_connect, $dupQuery);
                if ($dupResult && $dupResult->num_rows > 0) {
                    $isDuplicate = true;
                }
            }

            if (!$jt_inv_number) {
                $jt_inv_number_err = "Please specify the Invoice Number.";
                break;
            } else if (!$currency) {
                $currency_err = "Please specify the Invoice Currency.";
                break;
            } else if (!isset($currencyOptionsNormalized[$currency])) {
                $currency_err = "Invalid Invoice Currency. Please select a valid currency from the list.";
                break;
            } else if (!$jt_attach) {
                $attach_err = "Please attach the file.";
                break;
            } else if ($isDuplicate) {
                $jt_inv_number_err = "Duplicate record found for " . $pageTitle . " Invoice Number.";
                break;
            } else if ($action == 'addTransaction') {
                try {
                    mysqli_begin_transaction($finance_connect);

                    if ($jt_inv_number) {
                        array_push($newvalarr, $jt_inv_number);
                        array_push($datafield, 'number');
                    }
                    if ($jt_inv_date) {
                        array_push($newvalarr, $jt_inv_date);
                        array_push($datafield, 'date');
                    }

                    if ($jt_attach) {
                        array_push($newvalarr, $jt_attach);
                        array_push($datafield, 'attachment');
                    }

                    if ($currency) {
                        array_push($newvalarr, $currency);
                        array_push($datafield, 'currency');
                    }

                    array_push($newvalarr, ($total_gst !== '' ? $total_gst : '0.00'));
                    array_push($datafield, 'total_gst');

                    array_push($newvalarr, ($total_amount !== '' ? $total_amount : '0.00'));
                    array_push($datafield, 'total_amount');

                    $safeNumber = mysqli_real_escape_string($finance_connect, $jt_inv_number);
                    $safeDate = mysqli_real_escape_string($finance_connect, $jt_inv_date);
                    $safeAttach = mysqli_real_escape_string($finance_connect, $jt_attach);
                    $safeCurrency = mysqli_real_escape_string($finance_connect, $currency);
                    $safeTotalGst = (float) ($total_gst === '' ? 0 : $total_gst);
                    $safeTotalAmount = (float) ($total_amount === '' ? 0 : $total_amount);

                    $query = "INSERT INTO `jt_transaction_backup` (number, date, attachment, currency, total_gst, total_amount, create_by, create_date, create_time) VALUES ('" . $safeNumber . "', '" . $safeDate . "', '" . $safeAttach . "', '" . $safeCurrency . "', '" . $safeTotalGst . "', '" . $safeTotalAmount . "', '" . USER_ID . "', CURDATE(), CURTIME())";
                    $returnData = mysqli_query($finance_connect, $query);
                    if (!$returnData) {
                        throw new Exception(mysqli_error($finance_connect));
                    }

                    $dataID = mysqli_insert_id($finance_connect);

                    for ($i = 0; $i < count($serviceTypeArr); $i++) {
                        $serviceType = isset($serviceTypeArr[$i]) ? trim((string) $serviceTypeArr[$i]) : '';
                        if ($serviceType === '') {
                            continue;
                        }

                        $shipmentsCount = isset($shipmentsCountArr[$i]) ? (int) $shipmentsCountArr[$i] : 0;
                        $totalWeightKg = isset($totalWeightKgArr[$i]) ? (float) $totalWeightKgArr[$i] : 0;
                        $standardCharge = isset($standardChargeArr[$i]) ? (float) $standardChargeArr[$i] : 0;
                        $extraCharges = isset($extraChargesArr[$i]) ? (float) $extraChargesArr[$i] : 0;
                        $nettCharge = isset($nettChargeArr[$i]) ? (float) $nettChargeArr[$i] : 0;

                        $safeServiceType = mysqli_real_escape_string($finance_connect, $serviceType);

                        $insertItemSql = "INSERT INTO `jt_transaction_items` (transaction_id, service_type, shipments_count, total_weight_kg, standard_charge, extra_charges, nett_charge) VALUES ('" . (int) $dataID . "', '" . $safeServiceType . "', '" . $shipmentsCount . "', '" . $totalWeightKg . "', '" . $standardCharge . "', '" . $extraCharges . "', '" . $nettCharge . "')";
                        $insertItemRst = mysqli_query($finance_connect, $insertItemSql);
                        if (!$insertItemRst) {
                            throw new Exception(mysqli_error($finance_connect));
                        }
                    }

                    for ($i = 0; $i < count($gstTypeArr); $i++) {
                        $gstType = isset($gstTypeArr[$i]) ? trim((string) $gstTypeArr[$i]) : '';
                        if ($gstType === '') {
                            continue;
                        }

                        $gstRate = isset($gstRateArr[$i]) ? (float) $gstRateArr[$i] : 0;
                        $gstAmount = isset($gstAmountArr[$i]) ? (float) $gstAmountArr[$i] : 0;
                        $gstPaid = isset($gstPaidArr[$i]) ? (float) $gstPaidArr[$i] : 0;

                        $safeGstType = mysqli_real_escape_string($finance_connect, $gstType);

                        $insertGstSql = "INSERT INTO `jt_transaction_extra_charges` (transaction_id, type, rate, amount, gst_paid) VALUES ('" . (int) $dataID . "', '" . $safeGstType . "', '" . $gstRate . "', '" . $gstAmount . "', '" . $gstPaid . "')";
                        $insertGstRst = mysqli_query($finance_connect, $insertGstSql);
                        if (!$insertGstRst) {
                            throw new Exception(mysqli_error($finance_connect));
                        }
                    }

                    mysqli_commit($finance_connect);
                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    mysqli_rollback($finance_connect);
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $rst = getData('*', "id = '$dataID'", 'LIMIT 1', $tblName, $finance_connect);
                    $row = $rst->fetch_assoc();

                    // check value
                    if ($row['number'] != $jt_inv_number) {
                        array_push($oldvalarr, $row['number']);
                        array_push($chgvalarr, $jt_inv_number);
                        array_push($datafield, 'number');
                    }

                    if ($row['date'] != $jt_inv_date) {
                        array_push($oldvalarr, $row['date']);
                        array_push($chgvalarr, $jt_inv_date);
                        array_push($datafield, 'date');
                    }

                    $jt_attach = isset($jt_attach) ? $jt_attach : '';
                    if (($row['attachment'] != $jt_attach) && ($jt_attach != '')) {
                        array_push($oldvalarr, $row['attachment']);
                        array_push($chgvalarr, $jt_attach);
                        array_push($datafield, 'attachment');
                    }

                    $oldCurrency = isset($row['currency']) ? (string) $row['currency'] : '';
                    if ($oldCurrency !== $currency) {
                        array_push($oldvalarr, $oldCurrency);
                        array_push($chgvalarr, $currency);
                        array_push($datafield, 'currency');
                    }

                    $oldTotalGst = isset($row['total_gst']) ? (float) $row['total_gst'] : 0;
                    $newTotalGst = (float) ($total_gst === '' ? 0 : $total_gst);
                    if ($oldTotalGst !== $newTotalGst) {
                        array_push($oldvalarr, (string) $oldTotalGst);
                        array_push($chgvalarr, (string) $newTotalGst);
                        array_push($datafield, 'total_gst');
                    }

                    $oldTotalAmount = isset($row['total_amount']) ? (float) $row['total_amount'] : 0;
                    $newTotalAmount = (float) ($total_amount === '' ? 0 : $total_amount);
                    if ($oldTotalAmount !== $newTotalAmount) {
                        array_push($oldvalarr, (string) $oldTotalAmount);
                        array_push($chgvalarr, (string) $newTotalAmount);
                        array_push($datafield, 'total_amount');
                    }

                    $oldDeliverySnapshot = array();
                    $oldDeliveryRst = mysqli_query($finance_connect, "SELECT service_type, shipments_count, total_weight_kg, standard_charge, extra_charges, nett_charge FROM `" . $itemTable . "` WHERE transaction_id='" . (int) $dataID . "' ORDER BY id ASC");
                    if ($oldDeliveryRst) {
                        while ($oldDeliveryRow = mysqli_fetch_assoc($oldDeliveryRst)) {
                            $oldDeliverySnapshot[] =
                                trim((string) $oldDeliveryRow['service_type']) . '|' .
                                (string) ((int) $oldDeliveryRow['shipments_count']) . '|' .
                                number_format((float) $oldDeliveryRow['total_weight_kg'], 2, '.', '') . '|' .
                                number_format((float) $oldDeliveryRow['standard_charge'], 2, '.', '') . '|' .
                                number_format((float) $oldDeliveryRow['extra_charges'], 2, '.', '') . '|' .
                                number_format((float) $oldDeliveryRow['nett_charge'], 2, '.', '');
                        }
                    }

                    $newDeliverySnapshot = array();
                    for ($i = 0; $i < count($serviceTypeArr); $i++) {
                        $serviceType = isset($serviceTypeArr[$i]) ? trim((string) $serviceTypeArr[$i]) : '';
                        if ($serviceType === '') {
                            continue;
                        }
                        $newDeliverySnapshot[] = $serviceType . '|' .
                            (isset($shipmentsCountArr[$i]) ? (string) ((int) $shipmentsCountArr[$i]) : '0') . '|' .
                            (isset($totalWeightKgArr[$i]) ? number_format((float) $totalWeightKgArr[$i], 2, '.', '') : '0.00') . '|' .
                            (isset($standardChargeArr[$i]) ? number_format((float) $standardChargeArr[$i], 2, '.', '') : '0.00') . '|' .
                            (isset($extraChargesArr[$i]) ? number_format((float) $extraChargesArr[$i], 2, '.', '') : '0.00') . '|' .
                            (isset($nettChargeArr[$i]) ? number_format((float) $nettChargeArr[$i], 2, '.', '') : '0.00');
                    }

                    if (implode('||', $oldDeliverySnapshot) !== implode('||', $newDeliverySnapshot)) {
                        array_push($oldvalarr, implode(' || ', $oldDeliverySnapshot));
                        array_push($chgvalarr, implode(' || ', $newDeliverySnapshot));
                        array_push($datafield, 'delivery_items');
                    }

                    $oldGstSnapshot = array();
                    $oldGstRst = mysqli_query($finance_connect, "SELECT type, rate, amount, gst_paid FROM `" . $gstTable . "` WHERE transaction_id='" . (int) $dataID . "' ORDER BY id ASC");
                    if ($oldGstRst) {
                        while ($oldGstRow = mysqli_fetch_assoc($oldGstRst)) {
                            $oldGstSnapshot[] =
                                trim((string) $oldGstRow['type']) . '|' .
                                number_format((float) $oldGstRow['rate'], 2, '.', '') . '|' .
                                number_format((float) $oldGstRow['amount'], 2, '.', '') . '|' .
                                number_format((float) $oldGstRow['gst_paid'], 2, '.', '');
                        }
                    }

                    $newGstSnapshot = array();
                    for ($i = 0; $i < count($gstTypeArr); $i++) {
                        $gstType = isset($gstTypeArr[$i]) ? trim((string) $gstTypeArr[$i]) : '';
                        if ($gstType === '') {
                            continue;
                        }
                        $newGstSnapshot[] = $gstType . '|' .
                            (isset($gstRateArr[$i]) ? number_format((float) $gstRateArr[$i], 2, '.', '') : '0.00') . '|' .
                            (isset($gstAmountArr[$i]) ? number_format((float) $gstAmountArr[$i], 2, '.', '') : '0.00') . '|' .
                            (isset($gstPaidArr[$i]) ? number_format((float) $gstPaidArr[$i], 2, '.', '') : '0.00');
                    }

                    if (implode('||', $oldGstSnapshot) !== implode('||', $newGstSnapshot)) {
                        array_push($oldvalarr, implode(' || ', $oldGstSnapshot));
                        array_push($chgvalarr, implode(' || ', $newGstSnapshot));
                        array_push($datafield, 'gst_analysis');
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        mysqli_begin_transaction($finance_connect);

                        $safeNumber = mysqli_real_escape_string($finance_connect, $jt_inv_number);
                        $safeDate = mysqli_real_escape_string($finance_connect, $jt_inv_date);
                        $safeAttach = mysqli_real_escape_string($finance_connect, $jt_attach);
                        $safeCurrency = mysqli_real_escape_string($finance_connect, $currency);

                        $query = "UPDATE " . $tblName  . " SET number = '$safeNumber', date = '$safeDate', attachment ='$safeAttach', currency='$safeCurrency', total_gst='" . $newTotalGst . "', total_amount='" . $newTotalAmount . "', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '" . (int) $dataID . "'";
                        $returnData = mysqli_query($finance_connect, $query);
                        if (!$returnData) {
                            throw new Exception(mysqli_error($finance_connect));
                        }

                        $deleteItemsSql = "DELETE FROM `" . $itemTable . "` WHERE transaction_id='" . (int) $dataID . "'";
                        if (!mysqli_query($finance_connect, $deleteItemsSql)) {
                            throw new Exception(mysqli_error($finance_connect));
                        }

                        for ($i = 0; $i < count($serviceTypeArr); $i++) {
                            $serviceType = isset($serviceTypeArr[$i]) ? trim((string) $serviceTypeArr[$i]) : '';
                            if ($serviceType === '') {
                                continue;
                            }

                            $shipmentsCount = isset($shipmentsCountArr[$i]) ? (int) $shipmentsCountArr[$i] : 0;
                            $totalWeightKg = isset($totalWeightKgArr[$i]) ? (float) $totalWeightKgArr[$i] : 0;
                            $standardCharge = isset($standardChargeArr[$i]) ? (float) $standardChargeArr[$i] : 0;
                            $extraCharges = isset($extraChargesArr[$i]) ? (float) $extraChargesArr[$i] : 0;
                            $nettCharge = isset($nettChargeArr[$i]) ? (float) $nettChargeArr[$i] : 0;

                            $safeServiceType = mysqli_real_escape_string($finance_connect, $serviceType);
                            $insertItemSql = "INSERT INTO `" . $itemTable . "` (transaction_id, service_type, shipments_count, total_weight_kg, standard_charge, extra_charges, nett_charge) VALUES ('" . (int) $dataID . "', '" . $safeServiceType . "', '" . $shipmentsCount . "', '" . $totalWeightKg . "', '" . $standardCharge . "', '" . $extraCharges . "', '" . $nettCharge . "')";
                            if (!mysqli_query($finance_connect, $insertItemSql)) {
                                throw new Exception(mysqli_error($finance_connect));
                            }
                        }

                        $deleteGstSql = "DELETE FROM `" . $gstTable . "` WHERE transaction_id='" . (int) $dataID . "'";
                        if (!mysqli_query($finance_connect, $deleteGstSql)) {
                            throw new Exception(mysqli_error($finance_connect));
                        }

                        for ($i = 0; $i < count($gstTypeArr); $i++) {
                            $gstType = isset($gstTypeArr[$i]) ? trim((string) $gstTypeArr[$i]) : '';
                            if ($gstType === '') {
                                continue;
                            }

                            $gstRate = isset($gstRateArr[$i]) ? (float) $gstRateArr[$i] : 0;
                            $gstAmount = isset($gstAmountArr[$i]) ? (float) $gstAmountArr[$i] : 0;
                            $gstPaid = isset($gstPaidArr[$i]) ? (float) $gstPaidArr[$i] : 0;

                            $safeGstType = mysqli_real_escape_string($finance_connect, $gstType);
                            $insertGstSql = "INSERT INTO `" . $gstTable . "` (transaction_id, type, rate, amount, gst_paid) VALUES ('" . (int) $dataID . "', '" . $safeGstType . "', '" . $gstRate . "', '" . $gstAmount . "', '" . $gstPaid . "')";
                            if (!mysqli_query($finance_connect, $insertGstSql)) {
                                throw new Exception(mysqli_error($finance_connect));
                            }
                        }

                        mysqli_commit($finance_connect);
                    } else {
                        $_SESSION['tempValConfirmBox'] = true;
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    mysqli_rollback($finance_connect);
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


if (post('act') == 'D') {
    $id = post('id');
    if ($id) {
        try {
            // take name
            $rst = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $finance_connect);
            $row = $rst->fetch_assoc();

            $dataID = $row['id'];

            //SET the record status to 'D'
            deleteRecord($tblName, '', $dataID, $dataID, $finance_connect, $connect, $cdate, $ctime, $pageTitle);
            $_SESSION['delChk'] = 1;
        } catch (Exception $e) {
            echo 'Message: ' . $e->getMessage();
        }
    }
}

//view
if (($dataID) && !($act) && (USER_ID != '') && ($_SESSION['viewChk'] != 1) && ($_SESSION['delChk'] != 1)) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataID . "</b> ] from <b><i>$tblName Table</i></b>.";
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
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        .jt-auto-calc-field {
            background-color: #e9ecef;
        }

        .jt-service-type-col {
            min-width: 280px;
            width: 280px;
        }

        .jt-service-type-input {
            min-width: 260px;
        }

        .jt-gst-type-col {
            min-width: 180px;
            width: 180px;
        }

        .jt-gst-num-col {
            min-width: 130px;
        }

        .jt-gst-type-input {
            min-width: 160px;
        }

        .jt-gst-num-input {
            min-width: 110px;
        }

        @media (max-width: 767.98px) {
            #gstAnalysisTable {
                min-width: 700px;
            }

            #gstAnalysisTable th,
            #gstAnalysisTable td {
                white-space: nowrap;
            }
        }
    </style>

</head>

<body>
    <div class="d-flex flex-column my-3 ms-3">
        <p><a href="<?= $redirect_page ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?php
                                                                                                                    echo displayPageAction($act, $pageTitle);
                                                                                                                    ?>
        </p>

    </div>

    <div id="formContainer" class="container-fluid mt-2">
        <div class="col-12 col-md-12 formWidthAdjust">
            <form id="FATTForm" method="post" action="" enctype="multipart/form-data">
                <div class="form-group mb-5">
                    <h2>
                        <?php
                        echo displayPageAction($act, $pageTitle);
                        ?>
                    </h2>
                </div>
                <div class="row">
                <div class="col-12 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label form_lbl" id="jt_inv_number_lbl" for="swt_id">Invoice Number<span class="requireRed">*</span></label>
            <input class="form-control" type="text" name="jt_inv_number" id="jt_inv_number" value="<?php
                if (isset($dataExisted) && isset($row['number']) && !isset($jt_inv_number)) {
                    echo $row['number'];
                } else if (isset($jt_inv_number)) {
                    echo $jt_inv_number;
                }
            ?>" <?php if ($act == '') echo 'disabled' ?> autocomplete="off">
            <?php if (isset($jt_inv_number_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $jt_inv_number_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label form_lbl" id="jt_inv_date_label" for="jt_inv_date">Invoice Date<span class="requireRed">*</span></label>
            <input class="form-control" type="date" name="jt_inv_date" id="jt_inv_date" value="<?php
                if (isset($dataExisted) && isset($row['date']) && !isset($jt_inv_date)) {
                    echo $row['date'];
                } else if (isset($jt_inv_date)) {
                    echo $jt_inv_date;
                } else {
                    echo date('Y-m-d');
                }
            ?>" placeholder="YYYY-MM-DD" pattern="\d{4}-\d{2}-\d{2}" <?php if ($act == '') echo 'disabled' ?>>
            <?php if (isset($jt_inv_date_err)) { ?>
                <div id="err_msg">
                    <span class="mt-n1"><?php echo $jt_inv_date_err; ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</div>


                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="jt_attach_lbl" for="jt_attach">Attachment*</label>
                            <input class="form-control" type="file" name="jt_attach" id="jt_attach" <?php if ($act == '') echo 'disabled' ?>>

                            <?php if (isset($row['attachment']) && $row['attachment']) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo "Current Attachment: " . htmlspecialchars($row['attachment']); ?></span>
                                </div>
                                <input type="hidden" name="existing_attachment" value="<?php echo htmlspecialchars($row['attachment']); ?>">
                            <?php } ?>

                            <?php if (isset($attach_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $attach_err; ?></span>
                                </div>
                            <?php } ?>

                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-center justify-content-md-end px-4">
                                <?php
                                $attachmentSrc = '';

                                if (isset($dataExisted) && isset($row['attachment']) && !isset($jt_attach)) {
                                    $attachmentSrc = ($row['attachment'] == '' || $row['attachment'] == NULL) ? '' : $img_path . $row['attachment'];
                                } else if (isset($jt_attach)) {
                                    $attachmentSrc = $img_path . $jt_attach;
                                }
                                ?>
                                <img id="jt_attach_preview" name="jt_attach_preview" src="<?php echo $attachmentSrc; ?>" class="img-thumbnail" alt="Attachment Preview">
                                <input type="hidden" name="jt_attachmentValue" id="jt_attachmentValue" value="<?php if (isset($dataExisted) && isset($row['attachment']) && !isset($jt_attach)) {
                                                                                                                    echo $row['attachment'];
                                                                                                                } else if (isset($jt_attach)) {
                                                                                                                    echo $jt_attach;
                                                                                                                }

                                                                                                                ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-4">
                        <div class="form-group autocomplete mb-3">
                            <label class="form-label form_lbl" for="currency">Invoice Currency<span class="requireRed">*</span></label>
                            <?php
                                $selectedCurrency = '';
                                if (isset($currency) && trim((string) $currency) !== '') {
                                    $selectedCurrency = trim((string) $currency);
                                } else if (isset($dataExisted) && isset($row['currency']) && trim((string) $row['currency']) !== '') {
                                    $selectedCurrency = trim((string) $row['currency']);
                                }
                            ?>
                            <input class="form-control" type="text" name="currency" id="currency" list="currencyOptionsListMain" value="<?= htmlspecialchars((string) $selectedCurrency) ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off" onkeyup="jtCurrencySearch(this)">
                            <input type="hidden" id="currency_hidden" value="">
                            <datalist id="currencyOptionsListMain">
                                <?php foreach ($currencyOptions as $currencyOption) { ?>
                                    <option value="<?= htmlspecialchars((string) $currencyOption, ENT_QUOTES, 'UTF-8') ?>"></option>
                                <?php } ?>
                            </datalist>
                            <?php if (isset($currency_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $currency_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label form_lbl" for="total_gst">Total GST</label>
                            <input class="form-control jt-auto-calc-field" type="number" name="total_gst" id="total_gst" step="0.01" value="<?php
                                if (isset($total_gst)) {
                                    echo htmlspecialchars($total_gst);
                                } else if (isset($dataExisted) && isset($row['total_gst'])) {
                                    echo htmlspecialchars($row['total_gst']);
                                } else {
                                    echo '0.00';
                                }
                            ?>" readonly>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label form_lbl" for="total_amount">Total Amount Payable</label>
                            <input class="form-control jt-auto-calc-field" type="number" name="total_amount" id="total_amount" step="0.01" value="<?php
                                if (isset($total_amount)) {
                                    echo htmlspecialchars($total_amount);
                                } else if (isset($dataExisted) && isset($row['total_amount'])) {
                                    echo htmlspecialchars($row['total_amount']);
                                } else {
                                    echo '0.00';
                                }
                            ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="form-group mb-3">
                            <label class="form-label form_lbl">Delivery Services</label>
                            <div class="table-responsive mb-2">
                                <table class="table table-striped" id="deliveryItemsTable">
                                    <thead>
                                        <tr>
                                            <th scope="col" width="60">#</th>
                                            <th class="jt-service-type-col">Service Type</th>
                                            <th>Number of Shipments</th>
                                            <th>Total Weight in Kgs</th>
                                            <th>Standard Shipment Charge</th>
                                            <th>Extra Charges</th>
                                            <th>Nett Charge</th>
                                            <th scope="col" id="action_col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($deliveryRows as $idx => $deliveryRow) { ?>
                                        <tr>
                                            <td class="delivery-row-no"><?= (int) ($idx + 1) ?></td>
                                            <td><input class="form-control jt-service-type-input" type="text" name="service_type[]" value="<?= htmlspecialchars((string) $deliveryRow['service_type']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control" type="number" name="shipments_count[]" value="<?= htmlspecialchars((string) $deliveryRow['shipments_count']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control" type="number" step="0.01" name="total_weight_kg[]" value="<?= htmlspecialchars((string) $deliveryRow['total_weight_kg']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control delivery-standard-charge" type="number" step="0.01" name="standard_charge[]" value="<?= htmlspecialchars((string) $deliveryRow['standard_charge']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control" type="number" step="0.01" name="extra_charges[]" value="<?= htmlspecialchars((string) $deliveryRow['extra_charges']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control delivery-nett-charge jt-auto-calc-field" type="number" step="0.01" name="nett_charge[]" value="<?= htmlspecialchars((string) $deliveryRow['nett_charge']) ?>" readonly></td>
                                            <td class="delivery-action-cell" style="text-align:center;">
                                                <?php if ($act != '') { ?>
                                                    <?php if ($idx === 0) { ?>
                                                        <button class="mt-1" id="action_menu_btn" type="button" onclick="addDeliveryRow()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                                    <?php } else { ?>
                                                        <button class="mt-1 removeDeliveryRowBtn" id="action_menu_btn" type="button"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="form-group mb-3">
                            <label class="form-label form_lbl">Analysis of GST</label>
                            <div class="table-responsive mb-2">
                                <table class="table table-striped" id="gstAnalysisTable">
                                    <thead>
                                        <tr>
                                            <th scope="col" width="60">#</th>
                                            <th class="jt-gst-type-col">Type</th>
                                            <th class="jt-gst-num-col">Rate</th>
                                            <th class="jt-gst-num-col">Amount</th>
                                            <th class="jt-gst-num-col">GST Paid</th>
                                            <th scope="col" id="action_col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($gstRows as $idx => $gstRow) { ?>
                                        <tr>
                                            <td class="gst-row-no"><?= (int) ($idx + 1) ?></td>
                                            <td><input class="form-control jt-gst-type-input" type="text" name="gst_type[]" value="<?= htmlspecialchars((string) $gstRow['gst_type']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control jt-gst-num-input gst-rate" type="number" step="0.01" name="gst_rate[]" value="<?= htmlspecialchars((string) $gstRow['gst_rate']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control jt-gst-num-input gst-amount" type="number" step="0.01" name="gst_amount[]" value="<?= htmlspecialchars((string) $gstRow['gst_amount']) ?>" <?php if ($act == '') echo 'readonly' ?>></td>
                                            <td><input class="form-control jt-gst-num-input gst-paid jt-auto-calc-field" type="number" step="0.01" name="gst_paid[]" value="<?= htmlspecialchars((string) $gstRow['gst_paid']) ?>" readonly></td>
                                            <td class="gst-action-cell" style="text-align:center;">
                                                <?php if ($act != '') { ?>
                                                    <?php if ($idx === 0) { ?>
                                                        <button class="mt-1" id="action_menu_btn" type="button" onclick="addGstRow()"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                                    <?php } else { ?>
                                                        <button class="mt-1 removeGstRowBtn" id="action_menu_btn" type="button"><i class="fa-regular fa-trash-can fa-xl" style="color:#ff0000"></i></button>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                    <?php
                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="addTransaction">Add Transaction</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="updTransaction">Edit Transaction</button>';
                            break;
                    }
                    ?>
                    <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="actionBtn" id="actionBtn" value="back">Back</button>
                </div>
            </form>
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
        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ''; ?>";

        function jtCurrencySearch(element) {
            var param = {
                elementID: element.id,
                hiddenElementID: 'currency_hidden',
                search: element.value,
                searchType: 'unit',
                dbTable: '<?= CUR_UNIT ?>'
            };
            searchInput(param, '<?= $SITEURL ?>');
        }

        checkCurrentPage(page, action);
        setButtonColor();
        setAutofocus(action);
        preloader(300, action);

        <?php include "../js/j&t_trans_backup.js" ?>
    </script>

</body>

</html>