<?php
$currentPagePin = 0;
$pageTitle = "Shopee Order Request";
$sorIsAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($sorIsAjaxRequest) {
    ob_start();
}

include_once '../menuHeader.php';
include_once '../checkCurrentPagePin.php';
include_once ROOT . '/include/customer_follow_up_common.php';
include_once ROOT . '/include/shopee_order_detail_pdf_common.php';

$tblName = SHOPEE_SG_ORDER_REQ;
$sorCustomerNameColumnExists = shopeeOmsTableHasColumn($finance_connect, dbFinance, $tblName, 'customer_name');
$orderDeleteApprovalModuleKey = 'shopee_order_request';
$orderDeleteApprovalState = orderDeleteApprovalInitPageState();
$orderDeleteApprovalMode = !empty($orderDeleteApprovalState['approval_mode']);
$orderDeleteApprovalRequestId = isset($orderDeleteApprovalState['request_id']) ? (int) $orderDeleteApprovalState['request_id'] : 0;
$dataId = isset($orderDeleteApprovalState['data_id']) ? $orderDeleteApprovalState['data_id'] : '';
$act = isset($orderDeleteApprovalState['act']) ? $orderDeleteApprovalState['act'] : '';
$orderDeleteApprovalPanelHtml = isset($orderDeleteApprovalState['panel_html']) ? (string) $orderDeleteApprovalState['panel_html'] : '';

if (empty($_SESSION['shopee_order_follow_up_csrf'])) {
    $_SESSION['shopee_order_follow_up_csrf'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['shopee_order_verify_pdf_csrf'])) {
    $_SESSION['shopee_order_verify_pdf_csrf'] = bin2hex(random_bytes(32));
}

$pageAction = getPageAction($act);
$allowed_ext = array("png", "jpg", "jpeg", "pdf");

// Redirect directly to role page to avoid extra router history entries.
$redirectPage = $SITEURL . '/shopee/shopee_processing_order.php';
if (in_array('130', GlobalPin)) {
    $redirectPage = $SITEURL . '/shopee/shopee_order_req_table.php';
} else if (in_array('129', GlobalPin)) {
    $redirectPage = $SITEURL . '/shopee/shopee_verify.php';
}
$requestedReturnUrl = trim((string) input('return_url'));
if ($requestedReturnUrl === '') {
    $requestedReturnUrl = trim((string) post('return_url'));
}
$back_redirect_page = $requestedReturnUrl !== ''
    ? commonSafeBackUrl($requestedReturnUrl, $redirectPage)
    : commonResolveBackUrl($redirectPage);
if ($requestedReturnUrl !== '') {
    $redirectPage = $back_redirect_page;
}
$redirectLink = '<script>location.href=' . json_encode($redirectPage) . ';</script>';
$clearLocalStorage = <<<'HTML'
<script>
(function () {
    try {
        if (typeof window.localStorage === 'undefined') {
            return;
        }

        var preservedValues = {};
        for (var i = 0; i < window.localStorage.length; i++) {
            var storageKey = window.localStorage.key(i);
            if (storageKey && storageKey.indexOf('shopee_airbill_delivery_info_') === 0) {
                preservedValues[storageKey] = window.localStorage.getItem(storageKey);
            }
        }

        window.localStorage.clear();
        Object.keys(preservedValues).forEach(function (storageKey) {
            window.localStorage.setItem(storageKey, preservedValues[storageKey]);
        });
    } catch (error) {
    }
})();
</script>
HTML;
$sorStatusOptions = function_exists('shopeeOmsGetEditableStatusOptions') ? shopeeOmsGetEditableStatusOptions() : array('P' => 'To Ship', 'TP' => 'To Pack', 'SP' => 'Shipped', 'WAERD' => 'Waiting Assign Estimate Received Date');
$sorAirbillAttachmentPath = img_server . 'shopee_airbill_attachment/';
$sorAirbillAttachmentUrl = rtrim((string) $SITEURL, '/') . '/' . trim((string) $sorAirbillAttachmentPath, '/\\') . '/';
$sorLocalTelegramFailureMessage = '';
$sorPopupErrorMessage = '';
$sorIsLiveSite = isset($siteOrlocalMode) ? (bool) $siteOrlocalMode : true;
$sorPrepareAjaxJsonResponse = function () use ($sorIsAjaxRequest) {
    if (!$sorIsAjaxRequest) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
};
$sorSkipSaveBeforeStatusUpdate = post('skipSaveBeforeStatus') === '1';
$sorSaveBeforeStatusOnly = $sorIsAjaxRequest && post('saveBeforeStatusOnly') === '1';
$pendingStatusUpdate = shopeeOmsNormalizeStatusCode(post('updateStatusBtn'));
$sorConfirmReceiveWithFollowUp = postSpaceFilter('confirmReceiveFollowUpBtn') === '1';
$sorShouldSaveBeforeStatusUpdate = $pendingStatusUpdate !== '' && $act === 'E' && !$sorSkipSaveBeforeStatusUpdate && !$sorSaveBeforeStatusOnly;
$sorTriggerStatusTransitionAfterSave = false;
$sorFollowUpShortcutOptions = customerFollowUpGetMessageShortcutOptions($connect);
$sorBotMsgContext = 'shopee';
$sorBotMsgOrderTable = SHOPEE_SG_ORDER_REQ;
$sorBotMsgTemplateOptions = customizeBotMsgGetTemplateOptions($connect, $sorBotMsgContext);
$sorBotMsgDefaultTemplateId = customizeBotMsgGetDefaultTemplateId($connect, $sorBotMsgContext);
$sorBotMsgTemplateNameMap = array();
foreach ($sorBotMsgTemplateOptions as $sorBotMsgTemplateOption) {
    $templateOptionId = isset($sorBotMsgTemplateOption['id']) ? (int) $sorBotMsgTemplateOption['id'] : 0;
    if ($templateOptionId > 0) {
        $sorBotMsgTemplateNameMap[$templateOptionId] = isset($sorBotMsgTemplateOption['template_name']) ? (string) $sorBotMsgTemplateOption['template_name'] : ('Template #' . $templateOptionId);
    }
}
$sorExistingBotMsgTemplateId = ($dataId && $act !== 'I')
    ? customizeBotMsgGetOrderTemplateId($connect, $sorBotMsgContext, $sorBotMsgOrderTable, (int) $dataId)
    : 0;
$sorOriginalBotMsgTemplateId = $sorExistingBotMsgTemplateId > 0 ? $sorExistingBotMsgTemplateId : $sorBotMsgDefaultTemplateId;
$sorBuildLocalTelegramFailureMessage = function ($notifyResult) use ($sorIsLiveSite) {
    if ($sorIsLiveSite || !is_array($notifyResult) || !empty($notifyResult['sent'])) {
        return '';
    }

    $reason = trim((string) (isset($notifyResult['message']) ? $notifyResult['message'] : ''));
    if ($reason === '') {
        $reason = 'Unknown Telegram send failure.';
    }

    return "Telegram message failed to send.\nReason: " . $reason;
};
$sorHandleStatusTransition = function ($newStatus) use ($connect, $finance_connect, $dataId, $pageTitle, $cdate, $ctime, $tblName, $redirectPage, $sorBuildLocalTelegramFailureMessage, $sorIsAjaxRequest, $sorPrepareAjaxJsonResponse) {
    $newStatus = shopeeOmsNormalizeStatusCode($newStatus);
    $transitionRemark = 'Order Status Update to ' . shopeeOmsGetStatusLabel($newStatus);
    $statusUpdateFallbackMessage = $newStatus === 'TP'
        ? 'Airbill is required when Order Status is To Pack.'
        : 'Unable to update order status.';
    $transitionResult = shopeeOmsExecuteTransition($connect, $finance_connect, (int) $dataId, $newStatus, array(
        'actor_user_id' => USER_ID,
        'actor_user_group_id' => USER_GROUP,
        'source_page' => $pageTitle,
        'remark' => $transitionRemark,
    ));

    if (!empty($transitionResult['success'])) {
        $transitionResult['message'] = $transitionRemark;
        if (isset($transitionResult['new_status']) && (string) $transitionResult['new_status'] === 'TP') {
            $notifyResult = isset($transitionResult['step_a_result']['notify_result']) && is_array($transitionResult['step_a_result']['notify_result'])
                ? $transitionResult['step_a_result']['notify_result']
                : array();
            $localTelegramFailureMessage = $sorBuildLocalTelegramFailureMessage($notifyResult);
            if ($localTelegramFailureMessage !== '') {
                $transitionResult['message'] .= "\n\n" . $localTelegramFailureMessage;
            }
        }

        $oldStatus = isset($transitionResult['old_status']) ? (string) $transitionResult['old_status'] : '';
        $newStatusCode = isset($transitionResult['new_status']) ? (string) $transitionResult['new_status'] : '';
        $queryStatusUpdate = "OMS transition " . $oldStatus . " -> " . $newStatusCode;
        $log = [
            'log_act'      => 'edit',
            'cdate'        => $cdate,
            'ctime'        => $ctime,
            'uid'          => USER_ID,
            'cby'          => USER_ID,
            'query_rec'    => $queryStatusUpdate,
            'query_table'  => $tblName,
            'page'         => $pageTitle,
            'connect'      => $connect,
            'oldval'       => 'order_status: ' . $oldStatus,
            'changes'      => 'order_status: ' . $newStatusCode,
            'act_msg'      => USER_NAME . " updated Shopee order #" . (int) $dataId . " from " . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . " to " . htmlspecialchars($newStatusCode, ENT_QUOTES, 'UTF-8') . ".",
        ];
        audit_log($log);
        if ($sorIsAjaxRequest) {
            $sorPrepareAjaxJsonResponse();
            echo json_encode(array(
                'success' => true,
                'message' => (string) $transitionResult['message'],
                'redirect_url' => (string) $redirectPage,
            ));
            exit;
        }

        echo '<script>alert(' . json_encode((string) $transitionResult['message']) . '); window.location.replace(' . json_encode((string) $redirectPage) . ');</script>';
        exit;
    }

    if ($sorIsAjaxRequest) {
        $sorPrepareAjaxJsonResponse();
        echo json_encode(array(
            'success' => false,
            'message' => (string) (isset($transitionResult['message']) ? $transitionResult['message'] : $statusUpdateFallbackMessage),
        ));
        exit;
    }
    return array(
        'success' => false,
        'message' => (string) (isset($transitionResult['message']) ? $transitionResult['message'] : $statusUpdateFallbackMessage),
    );
};
$sorLogOmsTransitionAudit = function ($transitionResult) use ($pageTitle, $cdate, $ctime, $tblName, $connect, $dataId) {
    $oldStatus = isset($transitionResult['old_status']) ? (string) $transitionResult['old_status'] : '';
    $newStatusCode = isset($transitionResult['new_status']) ? (string) $transitionResult['new_status'] : '';
    $queryStatusUpdate = "OMS transition " . $oldStatus . " -> " . $newStatusCode;
    audit_log(array(
        'log_act' => 'edit',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => $queryStatusUpdate,
        'query_table' => $tblName,
        'page' => $pageTitle,
        'connect' => $connect,
        'oldval' => 'order_status: ' . $oldStatus,
        'changes' => 'order_status: ' . $newStatusCode,
        'act_msg' => USER_NAME . " updated Shopee order #" . (int) $dataId . " from " . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . " to " . htmlspecialchars($newStatusCode, ENT_QUOTES, 'UTF-8') . ".",
    ));
};
$sorWriteVerifyAuditLog = function ($queryRecord, $oldValue, $changeValue, $message) use ($pageTitle, $cdate, $ctime, $tblName, $connect) {
    audit_log(array(
        'log_act' => 'edit',
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'query_rec' => (string) $queryRecord,
        'query_table' => $tblName,
        'page' => $pageTitle,
        'connect' => $connect,
        'oldval' => (string) $oldValue,
        'changes' => (string) $changeValue,
        'act_msg' => (string) $message,
    ));
};
$sorHandleConfirmReceiveWithFollowUp = function () use ($connect, $finance_connect, $dataId, $pageTitle, $cdate, $ctime, $tblName, $redirectPage, $sorIsAjaxRequest, $sorPrepareAjaxJsonResponse) {
    $postedCsrfToken = (string) post('shopee_order_follow_up_csrf');
    if (!hash_equals((string) $_SESSION['shopee_order_follow_up_csrf'], $postedCsrfToken)) {
        $message = 'Invalid follow-up session token. Please refresh and try again.';
        if ($sorIsAjaxRequest) {
            $sorPrepareAjaxJsonResponse();
            echo json_encode(array('success' => false, 'message' => $message));
            exit;
        }
        echo '<script>alert(' . json_encode($message) . ');</script>';
        exit;
    }

    $actorRemark = shopeeOmsBuildParcelReceivedRemark($connect, USER_ID, 'user');
    $submitResult = customerFollowUpSubmitReceivedOrderAndTransition(
        $connect,
        $finance_connect,
        'shopee',
        (int) $dataId,
        array(
            'message_shortcut_id' => postSpaceFilter('follow_up_message_shortcut_id'),
            'next_follow_up_date' => postSpaceFilter('follow_up_next_follow_up_date'),
            'contact_no' => postSpaceFilter('follow_up_contact_no'),
        ),
        isset($_FILES['follow_up_attachment']) ? $_FILES['follow_up_attachment'] : array(),
        USER_ID,
        USER_GROUP,
        array(
            'source_page' => $pageTitle,
            'transition_remark' => $actorRemark,
        )
    );

    if (!empty($submitResult['success'])) {
        $sourceConfig = isset($submitResult['source_config']) ? $submitResult['source_config'] : shopeeOmsGetOrderSourceConfig('shopee');
        $orderRow = isset($submitResult['order_row_after']) && !empty($submitResult['order_row_after'])
            ? $submitResult['order_row_after']
            : shopeeOmsLoadOrder(shopeeOmsGetOrderSourceDbConnection($connect, $finance_connect, $sourceConfig), (int) $dataId, $sourceConfig);
        $transitionResult = isset($submitResult['transition_result']) ? $submitResult['transition_result'] : array();
        $oldStatus = isset($transitionResult['old_status']) ? (string) $transitionResult['old_status'] : '';
        $newStatusCode = isset($transitionResult['new_status']) ? (string) $transitionResult['new_status'] : 'PR';
        $queryStatusUpdate = "OMS transition " . $oldStatus . " -> " . $newStatusCode;
        audit_log(array(
            'log_act' => 'edit',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => $queryStatusUpdate,
            'query_table' => $tblName,
            'page' => $pageTitle,
            'connect' => $connect,
            'oldval' => 'order_status: ' . $oldStatus,
            'changes' => 'order_status: ' . $newStatusCode,
            'act_msg' => USER_NAME . " updated Shopee order #" . (int) $dataId . " from " . htmlspecialchars($oldStatus, ENT_QUOTES, 'UTF-8') . " to " . htmlspecialchars($newStatusCode, ENT_QUOTES, 'UTF-8') . ".",
        ));

        if ($sorIsAjaxRequest) {
            $sorPrepareAjaxJsonResponse();
            echo json_encode(array(
                'success' => true,
                'message' => isset($submitResult['message']) ? (string) $submitResult['message'] : 'Follow-up submitted successfully.',
                'redirect_url' => (string) $redirectPage,
            ));
            exit;
        }

        echo '<script>alert(' . json_encode((string) (isset($submitResult['message']) ? $submitResult['message'] : 'Follow-up submitted successfully.')) . '); window.location.replace(' . json_encode((string) $redirectPage) . ');</script>';
        exit;
    }

    if ($sorIsAjaxRequest) {
        $sorPrepareAjaxJsonResponse();
        echo json_encode(array(
            'success' => false,
            'message' => (string) (isset($submitResult['message']) ? $submitResult['message'] : 'Unable to confirm parcel received.'),
        ));
        exit;
    }

    echo '<script>alert(' . json_encode((string) (isset($submitResult['message']) ? $submitResult['message'] : 'Unable to confirm parcel received.')) . ');</script>';
    exit;
};

// to display data to input
if ($dataId) { //edit/remove/view
    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $finance_connect);

    if ($result != false && $result->num_rows > 0) {
        $dataExisted = 1;
        $row = $result->fetch_assoc();
        $row = shopeeOmsApplyReturnedOrderFinancials($row, 'shopee');
        $rememberedDeliveryInfo = shopeeOmsGetRememberedWarehouseDeliveryInfo('shopee', (int) $dataId);
        if ((!isset($sor_customer_name) || trim((string) $sor_customer_name) === '') && isset($rememberedDeliveryInfo['customer_name'])) {
            $sor_customer_name = trim((string) $rememberedDeliveryInfo['customer_name']);
        }
        if ((!isset($sor_customer_address) || trim((string) $sor_customer_address) === '') && isset($rememberedDeliveryInfo['customer_address'])) {
            $sor_customer_address = trim((string) $rememberedDeliveryInfo['customer_address']);
        }
    } else {
        // If $result is false or no data found ($act==null)
        $errorExist = 1;
        $_SESSION['tempValConfirmBox'] = true;
        $act = "F";
    }
}

$sorFollowUpModalContext = isset($row) && is_array($row)
    ? customerFollowUpBuildReceivedOrderContext($connect, $finance_connect, 'shopee', (int) $dataId, $row)
    : array();

$sorWarehouseRows = shopeeOmsLoadActiveWarehouses($connect);
$sorWarehouseOptionMap = array();
foreach ($sorWarehouseRows as $warehouseRow) {
    $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0;
    if ($warehouseId > 0) {
        $sorWarehouseOptionMap[$warehouseId] = isset($warehouseRow['name']) ? (string) $warehouseRow['name'] : ('Warehouse #' . $warehouseId);
    }
}
$sorWarehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect);
$sorDefaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect, $sorWarehouseRows);
$sorBuildStoredPackageRows = function ($orderRow) use ($connect) {
    $packageRows = array();
    if (!is_array($orderRow)) {
        return $packageRows;
    }

    $snapshotRows = shopeeOmsDecodePackageQtySnapshot(isset($orderRow['package_qty_json']) ? $orderRow['package_qty_json'] : '');
    if (!empty($snapshotRows)) {
        $packageIds = array();
        foreach ($snapshotRows as $snapshotRow) {
            $packageId = isset($snapshotRow['package_id']) ? (int) $snapshotRow['package_id'] : 0;
            if ($packageId > 0) {
                $packageIds[] = $packageId;
            }
        }

        $packageNameMap = !empty($packageIds) ? shopeeOmsGetPackageNameMap($connect, $packageIds) : array();
        foreach ($snapshotRows as $snapshotRow) {
            $packageId = isset($snapshotRow['package_id']) ? (int) $snapshotRow['package_id'] : 0;
            $packageName = trim((string) (isset($snapshotRow['package_name']) ? $snapshotRow['package_name'] : ''));
            if ($packageName === '' && $packageId > 0 && isset($packageNameMap[$packageId])) {
                $packageName = (string) $packageNameMap[$packageId];
            }

            $qty = isset($snapshotRow['qty']) ? (int) $snapshotRow['qty'] : 1;
            if ($qty <= 0) {
                $qty = 1;
            }

            for ($i = 0; $i < $qty; $i++) {
                $packageRows[] = array(
                    'id' => $packageId > 0 ? $packageId : '',
                    'name' => $packageName,
                );
            }
        }

        if (!empty($packageRows)) {
            return $packageRows;
        }
    }

    $selectedPkgIds = array_filter(array_map('trim', explode(',', (string) (isset($orderRow['package']) ? $orderRow['package'] : ''))), 'strlen');
    foreach ($selectedPkgIds as $pkgId) {
        $pkgIdInt = (int) $pkgId;
        $pkgName = '';
        if ($pkgIdInt > 0) {
            $pkgRst = getData('name', "id = '$pkgIdInt'", 'LIMIT 1', PKG, $connect);
            if ($pkgRst && $pkgRst->num_rows > 0) {
                $pkgData = $pkgRst->fetch_assoc();
                $pkgName = $pkgData['name'];
            }
        }

        $packageRows[] = array('id' => $pkgIdInt, 'name' => $pkgName);
    }

    return $packageRows;
};

if (!($dataId) && !($act)) {
    renderNotificationScript('Invalid action.', 'error', $redirectPage, 1200, true);
    exit;
}

$sorHandleVerifyWorkflowRequest = function () use (
    $connect,
    $finance_connect,
    $dataId,
    $tblName,
    $row,
    $redirectPage,
    $pageTitle,
    $sorPrepareAjaxJsonResponse,
    $sorLogOmsTransitionAudit,
    $sorWriteVerifyAuditLog
) {
    $sorPrepareAjaxJsonResponse();
    $verifyRedirectUrl = trim((string) post('sor_verify_redirect_url'));

    $postedCsrfToken = (string) post('shopee_order_verify_pdf_csrf');
    if (!hash_equals((string) $_SESSION['shopee_order_verify_pdf_csrf'], $postedCsrfToken)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid verify session token. Please refresh and try again.'));
        exit;
    }

    if (empty($row) || !is_array($row)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid order.'));
        exit;
    }

    if (!shopeeOmsTableHasColumn($finance_connect, dbFinance, $tblName, 'order_detail_pdf')) {
        echo json_encode(array('success' => false, 'message' => 'Order Detail PDF column is missing. Please run insert_table.php first.'));
        exit;
    }

    $verifyAction = trim((string) post('sor_verify_order_action'));
    $freshOrderRow = shopeeOmsLoadOrder($finance_connect, (int) $dataId, shopeeOmsResolveOrderSourceConfig('shopee'));
    if (empty($freshOrderRow)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid order.'));
        exit;
    }
    $currentStatus = shopeeOmsNormalizeStatusCode(isset($freshOrderRow['order_status']) ? $freshOrderRow['order_status'] : '');
    if (!shopeeOmsHasTransitionPermission($connect, $currentStatus, 'V', USER_GROUP, $freshOrderRow, USER_ID)) {
        echo json_encode(array('success' => false, 'message' => 'You are not allowed to verify this order.'));
        exit;
    }

    if ($verifyAction === 'compare_pdf') {
        if (!isset($_FILES['sor_order_detail_pdf'])) {
            echo json_encode(array('success' => false, 'message' => 'Please upload a Shopee Order Detail PDF.'));
            exit;
        }

        $uploadValidation = shopeeOrderDetailPdfValidateUpload($_FILES['sor_order_detail_pdf']);
        if (empty($uploadValidation['success'])) {
            echo json_encode(array('success' => false, 'message' => isset($uploadValidation['message']) ? (string) $uploadValidation['message'] : 'Failed to read the Order Detail PDF.'));
            exit;
        }

        $parseResult = shopeeOrderDetailPdfParse(
            $connect,
            $finance_connect,
            isset($uploadValidation['raw_content']) ? $uploadValidation['raw_content'] : '',
            postSpaceFilter('sor_order_detail_pdf_client_text')
        );
        if (empty($parseResult['success'])) {
            echo json_encode(array('success' => false, 'message' => isset($parseResult['message']) ? (string) $parseResult['message'] : 'Failed to parse the uploaded PDF.'));
            exit;
        }

        $latestOrderRow = shopeeOmsLoadOrder($finance_connect, (int) $dataId, shopeeOmsResolveOrderSourceConfig('shopee'));
        $comparisonRows = shopeeOrderDetailPdfBuildComparisonRows(
            $connect,
            $finance_connect,
            !empty($latestOrderRow) ? $latestOrderRow : $freshOrderRow,
            isset($parseResult['parsed']) ? $parseResult['parsed'] : array()
        );

        echo json_encode(array(
            'success' => true,
            'message' => empty($comparisonRows)
                ? 'PDF compared successfully. No different fields were found.'
                : 'PDF compared successfully. Please confirm the final values before verifying.',
            'comparison_rows' => $comparisonRows,
        ));
        exit;
    }

    if ($verifyAction === 'finalize_pdf_verified' || $verifyAction === 'direct_verified') {
        $fieldUpdates = array();
        if ($verifyAction === 'finalize_pdf_verified') {
            $finalValuesJson = (string) post('sor_verify_final_values');
            $finalValues = json_decode($finalValuesJson, true);
            if (!is_array($finalValues)) {
                $finalValues = array();
            }

            $pdfPath = '';
            if (isset($_FILES['sor_order_detail_pdf']) && is_array($_FILES['sor_order_detail_pdf']) && !empty($_FILES['sor_order_detail_pdf']['name'])) {
                $uploadResult = shopeeOrderDetailPdfStoreUpload($_FILES['sor_order_detail_pdf'], $connect, $finance_connect, $freshOrderRow);
                if (empty($uploadResult['success'])) {
                    echo json_encode(array('success' => false, 'message' => isset($uploadResult['message']) ? (string) $uploadResult['message'] : 'Failed to save the Order Detail PDF.'));
                    exit;
                }
                $pdfPath = isset($uploadResult['path']) ? (string) $uploadResult['path'] : '';
            }

            if ($pdfPath === '') {
                $pdfPath = trim((string) post('sor_verify_pdf_path'));
            }
            if ($pdfPath === '') {
                $pdfPath = trim((string) (isset($freshOrderRow['order_detail_pdf']) ? $freshOrderRow['order_detail_pdf'] : ''));
            }
            if ($pdfPath === '') {
                echo json_encode(array('success' => false, 'message' => 'Please upload the Order Detail PDF first.'));
                exit;
            }

            $preparedUpdates = shopeeOrderDetailPdfPrepareFinalUpdates($finance_connect, $freshOrderRow, $finalValues, $pdfPath);
            $fieldUpdates = isset($preparedUpdates['updates']) && is_array($preparedUpdates['updates']) ? $preparedUpdates['updates'] : array();
            $historyChanges = isset($preparedUpdates['history_changes']) && is_array($preparedUpdates['history_changes']) ? $preparedUpdates['history_changes'] : array();
            $validationError = isset($preparedUpdates['validation_error']) ? trim((string) $preparedUpdates['validation_error']) : '';
            $fieldErrors = isset($preparedUpdates['field_errors']) && is_array($preparedUpdates['field_errors']) ? $preparedUpdates['field_errors'] : array();
            if ($validationError !== '') {
                echo json_encode(array('success' => false, 'message' => $validationError, 'field_errors' => $fieldErrors));
                exit;
            }
        } else {
            $historyChanges = array();
            $sorWriteVerifyAuditLog(
                'Shopee verify workflow direct verified',
                '',
                'Order status update to Verify',
                USER_NAME . " used direct verified for Shopee order #" . (int) $dataId . "."
            );
        }

        $transitionResult = shopeeOmsExecuteTransition($connect, $finance_connect, (int) $dataId, 'V', array(
            'actor_user_id' => USER_ID,
            'actor_user_group_id' => USER_GROUP,
            'source_page' => $pageTitle,
            'remark' => 'Order Status Update to Verify',
            'field_updates' => $fieldUpdates,
            'platform' => 'shopee',
        ));

        if (empty($transitionResult['success'])) {
            echo json_encode(array(
                'success' => false,
                'message' => (string) (isset($transitionResult['message']) ? $transitionResult['message'] : 'Unable to update order status.'),
            ));
            exit;
        }

        if (!empty($historyChanges)) {
            shopeeOmsLogOrderEditHistory(
                $finance_connect,
                (int) $dataId,
                isset($freshOrderRow['orderID']) ? $freshOrderRow['orderID'] : '',
                $historyChanges,
                USER_ID,
                USER_GROUP,
                $pageTitle
            );

            $changePairs = array();
            $oldValuePairs = array();
            foreach ($historyChanges as $historyChange) {
                $fieldLabel = isset($historyChange['field_label']) ? (string) $historyChange['field_label'] : (string) $historyChange['field_name'];
                $oldValuePairs[] = $fieldLabel . ': ' . (isset($historyChange['old_value']) ? (string) $historyChange['old_value'] : '');
                $changePairs[] = $fieldLabel . ': ' . (isset($historyChange['old_value']) ? (string) $historyChange['old_value'] : '') . ' -> ' . (isset($historyChange['new_value']) ? (string) $historyChange['new_value'] : '');
            }
            if (!empty($changePairs)) {
                $sorWriteVerifyAuditLog(
                    'Shopee verify workflow field value changes',
                    implode("\n", $oldValuePairs),
                    implode("\n", $changePairs),
                    USER_NAME . " updated Shopee order detail values during verify workflow for order #" . (int) $dataId . "."
                );
            }
        }

        $sorLogOmsTransitionAudit($transitionResult);

        echo json_encode(array(
            'success' => true,
            'message' => $verifyAction === 'direct_verified'
                ? 'Order verified successfully.'
                : 'Order Detail PDF values saved and order verified successfully.',
            'redirect_url' => $verifyRedirectUrl !== '' ? $verifyRedirectUrl : (string) $redirectPage,
        ));
        exit;
    }

    echo json_encode(array('success' => false, 'message' => 'Invalid verify action.'));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sorIsAjaxRequest && trim((string) post('sor_verify_order_action')) !== '') {
    $sorHandleVerifyWorkflowRequest();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && post('submit')) {
    $scr_username = trim((string) post('scr_username'));
    $scr_pic_name = trim((string) post('scr_pic'));
    $scr_country_name = trim((string) post('scr_country'));
    $scr_brand_name = trim((string) post('scr_brand'));
    $scr_series_name = trim((string) post('scr_series'));
    $scr_resolve_lookup_id = function ($rawId, $displayValue, $tblName, $columnName) use ($connect) {
        $rawId = trim((string) $rawId);
        if ($rawId !== '' && ctype_digit($rawId) && (int) $rawId > 0) {
            return $rawId;
        }

        $displayValue = trim((string) $displayValue);
        if ($displayValue === '') {
            return '';
        }

        $safeDisplayValue = mysqli_real_escape_string($connect, $displayValue);
        $lookupRst = getData('id', $columnName . " = '$safeDisplayValue'", 'LIMIT 1', $tblName, $connect);
        if ($lookupRst && $lookupRst->num_rows > 0) {
            $lookupRow = $lookupRst->fetch_assoc();
            return isset($lookupRow['id']) ? trim((string) $lookupRow['id']) : '';
        }

        return '';
    };
    $scr_pic = $scr_resolve_lookup_id(post('scr_pic_hidden'), $scr_pic_name, USR_USER, 'name');
    $scr_country = $scr_resolve_lookup_id(post('scr_country_hidden'), $scr_country_name, COUNTRIES, 'nicename');
    $scr_brand = $scr_resolve_lookup_id(post('scr_brand_hidden'), $scr_brand_name, BRAND, 'name');
    $scr_series = $scr_resolve_lookup_id(post('scr_series_hidden'), $scr_series_name, BRD_SERIES, 'name');
    $duplicate_check_query = "SELECT * FROM shopee_customer_info WHERE buyer_username = '$scr_username'";
    $duplicate_result = mysqli_query($finance_connect, $duplicate_check_query);
    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();
    $newCustomerRequiredFields = array(
        'Shopee Buyer Username' => $scr_username,
        'Sales Person In Charge' => $scr_pic,
        'Country' => $scr_country,
        'Brand' => $scr_brand,
        'Series' => $scr_series,
    );

    if (!in_array('', $newCustomerRequiredFields, true)) {
        if (mysqli_num_rows($duplicate_result) > 0) {
            echo "<script>alert('Error: Duplicate Customer ID found!');</script>";
        } else {
           $insert_query = "INSERT INTO ".SHOPEE_CUST_INFO." (buyer_username, pic, country, brand, series,create_by,create_date,create_time) 
                             VALUES ('$scr_username', '$scr_pic', '$scr_country', '$scr_brand', '$scr_series','" . USER_ID . "',curdate(),curtime())";
            $insertCustomerId = 0;

            if (mysqli_query($finance_connect, $insert_query)) {
                $insertCustomerId = (int) mysqli_insert_id($finance_connect);
                echo "<script>alert('New record created successfully');</script>";
                generateDBData(SHOPEE_CUST_INFO, $finance_connect);
                shopeeCustomerRecordClearListCache();
            } else {
                echo "<script>alert('Error: " . $insert_query . "<br>" . mysqli_error($connect) . "');</script>";
            }
            
           
            //check values
            if ($scr_username) {
                array_push($newvalarr, $scr_username);
                array_push($datafield, 'Shopee Buyer Username');
            }
            if ($scr_pic) {
                array_push($newvalarr, $scr_pic);
                array_push($datafield, 'Sales Person In Charge');
            }
            if ($scr_country) {
                array_push($newvalarr, $scr_country);
                array_push($datafield, 'Country');
            }
            if ($scr_brand) {
                array_push($newvalarr, $scr_brand);
                array_push($datafield, 'Brand');
            }
            if ($scr_series) {
                array_push($newvalarr, $scr_series);
                array_push($datafield, 'Series');
            }

             if (isset($insert_query)) {
                $log = [
                    'log_act' => 'add',
                    'cdate' => $cdate,
                    'ctime' => $ctime,
                    'uid' => USER_ID,
                    'cby' => USER_ID,
                    'query_rec' => $insert_query,
                    'query_table' => SHOPEE_CUST_INFO,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];
                $log['newval'] = implodeWithComma($newvalarr);
                $log['act_msg'] = actMsgLog($insertCustomerId, $datafield, $newvalarr, '', '', SHOPEE_CUST_INFO, 'add', ($insertCustomerId > 0 ? '' : 'Failed to create Shopee customer record.'));
                
                audit_log($log);
             }
        }
    } else {
        echo "<script>alert('Please fill in all required fields for the new customer record.');</script>";
    }
}

if ($pendingStatusUpdate !== '' && !$sorShouldSaveBeforeStatusUpdate) {
    if ($pendingStatusUpdate === 'PR' && $sorConfirmReceiveWithFollowUp) {
        $sorHandleConfirmReceiveWithFollowUp();
    }
    $sorTransitionResult = $sorHandleStatusTransition($pendingStatusUpdate);
    if (is_array($sorTransitionResult) && empty($sorTransitionResult['success'])) {
        $transitionErrorState = shopeeOmsResolveStatusTransitionErrorState(
            $pendingStatusUpdate,
            isset($sorTransitionResult['message']) ? $sorTransitionResult['message'] : '',
            'Unable to update order status.'
        );
        if ($transitionErrorState['stock_out_warehouse_err'] !== '') {
            $stock_out_warehouse_err = $transitionErrorState['stock_out_warehouse_err'];
        }
        $sorPopupErrorMessage = $transitionErrorState['popup_error_message'];
    }
}

$sorExecuteDeleteOrder = orderDeleteApprovalBuildStandardDeleteCallback(array(
    'data_connect' => $finance_connect,
    'audit_connect' => $connect,
    'table_name' => $tblName,
    'page_title' => $pageTitle,
    'fallback_data_id' => (int) $dataId,
    'label_field' => 'orderID',
));

$orderDeleteApprovalPanelHtml = orderDeleteApprovalHandlePageFlow(array(
    'connect' => $connect,
    'request_id' => $orderDeleteApprovalRequestId,
    'module_key' => $orderDeleteApprovalModuleKey,
    'data_id' => (int) $dataId,
    'current_user_id' => (int) USER_ID,
    'page_title' => $pageTitle,
    'redirect_page' => $redirectPage,
    'clear_local_storage' => $clearLocalStorage,
    'approval_mode' => $orderDeleteApprovalMode,
    'delete_callback' => $sorExecuteDeleteOrder,
));

if (post('returnActionBtn')) {
    $returnType = postSpaceFilter('return_type');
    $returnRemark = postSpaceFilter('return_remark');
    $returnResult = shopeeOmsHandleReturn($connect, $finance_connect, (int) $dataId, $returnType, $returnRemark, USER_ID, USER_GROUP, $pageTitle);
    if (!empty($returnResult['success'])) {
        $log = [
            'log_act' => 'edit',
            'cdate' => $cdate,
            'ctime' => $ctime,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => 'OMS return ' . $returnType,
            'query_table' => $tblName,
            'page' => $pageTitle,
            'connect' => $connect,
            'changes' => 'return_type: ' . $returnType,
            'act_msg' => USER_NAME . " marked Shopee order #" . (int) $dataId . " as returned (" . htmlspecialchars($returnType, ENT_QUOTES, 'UTF-8') . ").",
        ];
        audit_log($log);
        echo '<script>alert(' . json_encode((string) $returnResult['message']) . '); window.location.replace(' . json_encode((string) $redirectPage) . ');</script>';
        exit;
    }

    echo '<script>alert(' . json_encode((string) (isset($returnResult['message']) ? $returnResult['message'] : 'Unable to save return action.')) . ');</script>';
}

if (post('actionBtn') || $sorShouldSaveBeforeStatusUpdate) {
    $action = post('actionBtn');
    if ($action === '' && $sorShouldSaveBeforeStatusUpdate) {
        $action = 'updRecord';
    }
    $errorMsg = '';
    $buildShopeeOrderReqSaveErrorMessage = function ($statusSaveErrorCandidates, $fallbackErrorMsg = '') {
        foreach ((array) $statusSaveErrorCandidates as $candidateMessage) {
            if (trim((string) $candidateMessage) !== '') {
                return (string) $candidateMessage;
            }
        }
        if (trim((string) $fallbackErrorMsg) !== '') {
            return trim((string) $fallbackErrorMsg);
        }
        return 'Unable to save edited order details.';
    };

    $resolveMultiIds = function ($hiddenInput, $nameInput, $tblName) use ($connect) {
        $resolved = array();

        if (!is_array($hiddenInput)) {
            $hiddenInput = explode(',', (string) $hiddenInput);
        }

        foreach ($hiddenInput as $idVal) {
            $idVal = trim((string) $idVal);
            if ($idVal !== '' && ctype_digit($idVal) && (int) $idVal > 0) {
                $resolved[] = (string) ((int) $idVal);
            }
        }

        if (!is_array($nameInput)) {
            $nameInput = array($nameInput);
        }

        foreach ($nameInput as $nameVal) {
            $nameVal = trim((string) $nameVal);
            if ($nameVal === '') {
                continue;
            }

            $escapedName = mysqli_real_escape_string($connect, $nameVal);
            $nameRst = getData('id', "name = '$escapedName'", 'LIMIT 1', $tblName, $connect);
            if ($nameRst && $nameRst->num_rows > 0) {
                $nameRow = $nameRst->fetch_assoc();
                $resolvedId = (int) $nameRow['id'];
                if ($resolvedId > 0) {
                    $resolved[] = (string) $resolvedId;
                }
            }
        }

        $resolved = array_values(array_unique($resolved));
        return implode(',', $resolved);
    };
    $normalizeAmount = function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $numericValue = (float) $value;
        if (abs($numericValue) < 0.00001) {
            $numericValue = 0.0;
        }

        return number_format($numericValue, 2, '.', '');
    };

    $sor_acc = postSpaceFilter('sor_acc');
    $sor_curr = postSpaceFilter('sor_curr_hidden');
    $sor_order = postSpaceFilter('sor_order');
    $sor_date = postSpaceFilter('sor_date');
    $sor_time = postSpaceFilter('sor_time');
    $sor_pkg = $resolveMultiIds(
        postSpaceFilter('sor_pkg_hidden') ?: array(),
        postSpaceFilter('sor_pkg') ?: array(),
        PKG
    );

    $sor_brand = $resolveMultiIds(
        postSpaceFilter('sor_brand_hidden') ?: array(),
        postSpaceFilter('sor_brand') ?: array(),
        BRAND
    );
    $sor_user = postSpaceFilter('sor_user_hidden');
    $sor_pay = postSpaceFilter('sor_pay');
    $sor_pic = postSpaceFilter('sor_pic_hidden');
    $sor_price = postSpaceFilter('sor_price');
    $sor_voucher = postSpaceFilter('sor_voucher');
    $sor_shipping = postSpaceFilter('sor_shipping');
    $sor_serv = postSpaceFilter('sor_serv');
    $sor_trans = postSpaceFilter('sor_trans');
    $sor_ams = postSpaceFilter('sor_ams');
    $sor_saver_program_fee = postSpaceFilter('sor_saver_program_fee');
    $postedSorFees = postSpaceFilter('sor_fees');
    $postedSorFinal = postSpaceFilter('sor_final');
    $sor_price = $normalizeAmount($sor_price);
    $sor_voucher = $normalizeAmount($sor_voucher);
    $sor_shipping = $normalizeAmount($sor_shipping);
    $sor_serv = $normalizeAmount($sor_serv);
    $sor_trans = $normalizeAmount($sor_trans);
    $sor_ams = $normalizeAmount($sor_ams);
    $sor_saver_program_fee = $normalizeAmount($sor_saver_program_fee);
    $computedSorFees = (float) ($sor_serv === '' ? 0 : $sor_serv)
        + (float) ($sor_trans === '' ? 0 : $sor_trans)
        + (float) ($sor_ams === '' ? 0 : $sor_ams)
        + (float) ($sor_saver_program_fee === '' ? 0 : $sor_saver_program_fee);
    $normalizedPostedSorFees = $normalizeAmount($postedSorFees);
    $sor_fees = $normalizedPostedSorFees === ''
        ? number_format($computedSorFees, 2, '.', '')
        : $normalizedPostedSorFees;
    $computedSorFinal = (float) ($sor_price === '' ? 0 : $sor_price)
        - (float) ($sor_voucher === '' ? 0 : $sor_voucher)
        - (float) ($sor_shipping === '' ? 0 : $sor_shipping)
        - $computedSorFees;
    $normalizedPostedSorFinal = $normalizeAmount($postedSorFinal);
    $sor_final = $normalizedPostedSorFinal === ''
        ? number_format($computedSorFinal, 2, '.', '')
        : $normalizedPostedSorFinal;
    $sor_remark = postSpaceFilter('sor_remark');
    $sor_order_status = shopeeOmsNormalizeStatusCode(postSpaceFilter('sor_order_status'));
    if ($sor_order_status === '') {
        $sor_order_status = isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P';
    }
    $sorFinancialStatusForSave = $pendingStatusUpdate !== '' ? $pendingStatusUpdate : $sor_order_status;
    if ($sorFinancialStatusForSave === '' && isset($row['order_status'])) {
        $sorFinancialStatusForSave = shopeeOmsNormalizeStatusCode($row['order_status']);
    }
    if (shopeeOmsIsReturnedStatus($sorFinancialStatusForSave)) {
        $sor_price = '0.00';
        $sor_voucher = '0.00';
        $sor_shipping = '0.00';
        $sor_serv = '0.00';
        $sor_trans = '0.00';
        $sor_ams = '0.00';
        $sor_saver_program_fee = '0.00';
        $sor_fees = '0.00';
        $sor_final = '0.00';
    }
    $sorCurrentEffectiveWarehouseId = isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $sorDefaultWarehouseId) : $sorDefaultWarehouseId;
    $sor_stock_out_warehouse_id = shopeeOmsNormalizeWarehouseId(postSpaceFilter('sor_stock_out_warehouse_id'));
    if ($sor_stock_out_warehouse_id <= 0) {
        $sor_stock_out_warehouse_id = $sorDefaultWarehouseId;
    }
    $sorStockOutWarehouseEditable = $action === 'addRecord'
        ? true
        : shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : '');
    if (!$sorStockOutWarehouseEditable && $action === 'updRecord') {
        $sor_stock_out_warehouse_id = $sorCurrentEffectiveWarehouseId;
    }
    $sor_update_airbill = strtolower(trim((string) postSpaceFilter('sor_update_airbill')));
    if ($sor_update_airbill === '') {
        $sor_update_airbill = 'yes';
    }
    $sor_airbill = postSpaceFilter('sor_airbill');
    $sor_customer_name = postSpaceFilter('sor_customer_name');
    $sor_customer_address = postSpaceFilter('sor_customer_address');
    $sor_bot_msg_template_id = (int) postSpaceFilter('sor_bot_msg_template_id');
    if ($sor_bot_msg_template_id <= 0 || !isset($sorBotMsgTemplateNameMap[$sor_bot_msg_template_id])) {
        $sor_bot_msg_template_id = $sorBotMsgDefaultTemplateId;
    }
    if ((int) $dataId > 0 && ($sor_customer_name !== '' || $sor_customer_address !== '')) {
        shopeeOmsRememberWarehouseDeliveryInfo('shopee', (int) $dataId, array(
            'customer_name' => $sor_customer_name,
            'customer_address' => $sor_customer_address,
        ));
    }
    $sorAirbillHasPendingUpload = isset($_FILES["sor_airbill_attachment"])
        && isset($_FILES["sor_airbill_attachment"]["size"])
        && (int) $_FILES["sor_airbill_attachment"]["size"] > 0;
    $sorAirbillPendingUploadName = $sorAirbillHasPendingUpload && isset($_FILES["sor_airbill_attachment"]["name"])
        ? basename((string) $_FILES["sor_airbill_attachment"]["name"])
        : '';
    $sorExistingAirbillAttachmentValue = isset($row['airbill_attachment']) ? trim((string) $row['airbill_attachment']) : '';
    $sorPostedAirbillAttachmentValue = trim((string) post('sor_airbill_attachment_value'));
    $sor_airbill_attachment = '';

    if ($action === 'updRecord' && $sorExistingAirbillAttachmentValue !== '') {
        $sor_airbill_attachment = $sorExistingAirbillAttachmentValue;
    }

    if (
        $action === 'updRecord'
        && $sorPostedAirbillAttachmentValue !== ''
        && $sorPostedAirbillAttachmentValue === $sorExistingAirbillAttachmentValue
    ) {
        $sor_airbill_attachment = $sorPostedAirbillAttachmentValue;
    }
    $packageQtySnapshot = shopeeOmsBuildPackageQtySnapshotFromInputs(
        postSpaceFilter('sor_pkg_hidden') ?: array(),
        postSpaceFilter('sor_pkg') ?: array(),
        $connect
    );
    $packageQtySnapshotJson = !empty($packageQtySnapshot) ? json_encode($packageQtySnapshot) : '';

    $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

    switch ($action) {
        case 'addRecord':
        case 'updRecord':
            $sorAirbillUploadReady = $sor_update_airbill === 'yes' && $sorAirbillHasPendingUpload;

            if ($sor_update_airbill !== 'yes') {
                if ($action === 'updRecord') {
                    $sor_airbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
                    $sor_airbill_attachment = isset($row['airbill_attachment']) ? (string) $row['airbill_attachment'] : '';
                    $sor_customer_name = $sorCustomerNameColumnExists && isset($row['customer_name']) ? (string) $row['customer_name'] : '';
                    $sor_customer_address = isset($row['customer_address']) ? (string) $row['customer_address'] : '';
                } else {
                    $sor_airbill = '';
                    $sor_airbill_attachment = '';
                    $sor_customer_name = '';
                    $sor_customer_address = '';
                }
            }

            if (!$sor_acc) {
                $acc_err = "Shopee Account cannot be empty.";
                $error = 1;
            }
            if (!$sor_curr) {
                $curr_err = "Currency cannot be empty.";
                $error = 1;
            }
            if (!$sor_order) {
                $order_err = "Order ID cannot be empty.";
                $error = 1;
            }
            if (!$sor_date) {
                $date_err = "Date cannot be empty.";
                $error = 1;
            }
            if (!$sor_time) {
                $time_err = "Time cannot be empty.";
                $error = 1;
            }
            if (!$sor_pkg) {
                $pkg_err = "Package cannot be empty.";
                $error = 1;
            }
            if (!$sor_brand) {
                $brand_err = "Brand cannot be empty.";
                $error = 1;
            }
            if (!$sor_user) {
                $user_err = "Shopee Buyer Username cannot be empty.";
                $error = 1;
            }
            if (!$sor_pay) {
                $pay_err = "Buyer Payment Method cannot be empty.";
                $error = 1;
            }
            if (!$sor_pic) {
                $pic_err = "Person In Charge cannot be empty.";
                $error = 1;
            }
            if (!$sor_price) {
                $price_err = "Product Price cannot be empty.";
                $error = 1;
            }
            if ($sorStockOutWarehouseEditable) {
                if ($sor_stock_out_warehouse_id <= 0) {
                    $stock_out_warehouse_err = "Stock Out Warehouse cannot be empty.";
                    $error = 1;
                } else if (!isset($sorWarehouseOptionMap[$sor_stock_out_warehouse_id])) {
                    $stock_out_warehouse_err = "Please select a valid active Stock Out Warehouse.";
                    $error = 1;
                }
            }

            $effectiveAirbill = $sor_airbill;
            if ($action === 'updRecord' && $sor_update_airbill !== 'yes') {
                $effectiveAirbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
            }
            $statusValidation = shopeeOmsValidateInitialStatusAndAirbill($sor_order_status, $effectiveAirbill);
            if (!$statusValidation['valid']) {
                $airbill_err = $statusValidation['message'];
                $error = 1;
            }
            if ($sor_update_airbill === 'yes') {
                if (trim((string) $sor_airbill) === '') {
                    $airbill_err = "Airbill No cannot be empty when Update Airbill is enabled.";
                    $error = 1;
                }
                if (trim((string) $sor_customer_address) === '') {
                    $customer_address_err = "Customer Address cannot be empty when Update Airbill is enabled.";
                    $error = 1;
                }
                $sorEffectiveAirbillAttachmentForValidation = $sor_airbill_attachment;
                if ($sorAirbillHasPendingUpload && $sorAirbillPendingUploadName !== '') {
                    $sorEffectiveAirbillAttachmentForValidation = $sorAirbillPendingUploadName;
                }

                if (trim((string) $sorEffectiveAirbillAttachmentForValidation) === '') {
                    $airbill_attachment_err = "Airbill Attachment cannot be empty when Update Airbill is enabled.";
                    $error = 1;
                }
            }

            $sorShouldValidateWarehouseStockBeforeSave = false;
            $sorCurrentOrderStatusForValidation = isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : '';

            if ($action === 'addRecord' && $sor_order_status === 'TP') {
                $sorShouldValidateWarehouseStockBeforeSave = true;
            } else if ($action === 'updRecord' && $pendingStatusUpdate === 'TP') {
                $sorShouldValidateWarehouseStockBeforeSave = true;
            } else if ($action === 'updRecord' && $sorCurrentOrderStatusForValidation === 'TP') {
                $sorShouldValidateWarehouseStockBeforeSave = true;
            }

            if (!isset($error) && $sorShouldValidateWarehouseStockBeforeSave) {
                $sorWarehouseStockValidation = shopeeOmsValidateWarehouseStockForOrder($connect, $finance_connect, array(
                    'package' => $sor_pkg,
                    'package_qty_json' => $packageQtySnapshotJson,
                    'stock_out_warehouse_id' => $sor_stock_out_warehouse_id,
                ), array(
                    'platform' => 'shopee',
                ));
                if (empty($sorWarehouseStockValidation['success'])) {
                    $stock_out_warehouse_err = isset($sorWarehouseStockValidation['message']) ? (string) $sorWarehouseStockValidation['message'] : 'Selected warehouse does not have enough stock.';
                    $error = 1;
                }
            }

            if (isset($error)) {
                if ($action === 'updRecord' && $sorSaveBeforeStatusOnly) {
                    $sorPrepareAjaxJsonResponse();
                    echo json_encode(array(
                        'success' => false,
                        'message' => $buildShopeeOrderReqSaveErrorMessage(array(
                            isset($airbill_err) ? $airbill_err : '',
                            isset($airbill_attachment_err) ? $airbill_attachment_err : '',
                            isset($customer_address_err) ? $customer_address_err : '',
                            isset($stock_out_warehouse_err) ? $stock_out_warehouse_err : '',
                            isset($pkg_err) ? $pkg_err : '',
                            isset($brand_err) ? $brand_err : '',
                            isset($user_err) ? $user_err : '',
                            isset($pay_err) ? $pay_err : '',
                            isset($pic_err) ? $pic_err : '',
                            isset($price_err) ? $price_err : '',
                            isset($order_err) ? $order_err : '',
                            isset($date_err) ? $date_err : '',
                            isset($time_err) ? $time_err : '',
                            isset($curr_err) ? $curr_err : '',
                            isset($acc_err) ? $acc_err : '',
                        ), $errorMsg),
                    ));
                    exit;
                }
                break;
            }

            if ($sorAirbillUploadReady) {
                $uploadResult = shopeeOmsStoreAirbillAttachmentUpload(
                    $_FILES["sor_airbill_attachment"],
                    $connect,
                    $sor_brand,
                    $sor_pkg,
                    'shopee_order_request',
                    $allowed_ext
                );

                if (!empty($uploadResult['success'])) {
                    $sor_airbill_attachment = isset($uploadResult['path']) ? (string) $uploadResult['path'] : '';
                } else {
                    $airbill_attachment_err = isset($uploadResult['message']) ? (string) $uploadResult['message'] : "Failed to upload the airbill attachment.";
                    $error = 1;
                }
            }

            if (isset($error)) {
                if ($action === 'updRecord' && $sorSaveBeforeStatusOnly) {
                    $sorPrepareAjaxJsonResponse();
                    echo json_encode(array(
                        'success' => false,
                        'message' => $buildShopeeOrderReqSaveErrorMessage(array(
                            isset($airbill_err) ? $airbill_err : '',
                            isset($airbill_attachment_err) ? $airbill_attachment_err : '',
                            isset($customer_address_err) ? $customer_address_err : '',
                            isset($stock_out_warehouse_err) ? $stock_out_warehouse_err : '',
                            isset($pkg_err) ? $pkg_err : '',
                            isset($brand_err) ? $brand_err : '',
                            isset($user_err) ? $user_err : '',
                            isset($pay_err) ? $pay_err : '',
                            isset($pic_err) ? $pic_err : '',
                            isset($price_err) ? $price_err : '',
                            isset($order_err) ? $order_err : '',
                            isset($date_err) ? $date_err : '',
                            isset($time_err) ? $time_err : '',
                            isset($curr_err) ? $curr_err : '',
                            isset($acc_err) ? $acc_err : '',
                        ), $errorMsg),
                    ));
                    exit;
                }
                break;
            }

            if ($action == 'addRecord') {
                try {
                    $requiresInitialShippedAutoMove = ($sor_order_status === 'SP');
                    $startedFinanceTransaction = false;
                    if ($requiresInitialShippedAutoMove) {
                        mysqli_begin_transaction($finance_connect);
                        $startedFinanceTransaction = true;
                    }

                    //check values
                    if ($sor_acc) {
                        array_push($newvalarr, $sor_acc);
                        array_push($datafield, 'shopee account');
                    }
                    if ($sor_curr) {
                        array_push($newvalarr, $sor_curr);
                        array_push($datafield, 'currency');
                    }

                    if ($sor_order) {
                        array_push($newvalarr, $sor_order);
                        array_push($datafield, 'order ID');
                    }

                    if ($sor_date) {
                        array_push($newvalarr, $sor_date);
                        array_push($datafield, 'date');
                    }

                    if ($sor_time) {
                        array_push($newvalarr, $sor_time);
                        array_push($datafield, 'time');
                    }

                    if ($sor_pkg) {
                        array_push($newvalarr, $sor_pkg);
                        array_push($datafield, 'package');
                    }

                    if ($sor_brand) {
                        array_push($newvalarr, $sor_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($sor_user) {
                        array_push($newvalarr, $sor_user);
                        array_push($datafield, 'buyer username');
                    }

                    if ($sor_pay) {
                        array_push($newvalarr, $sor_pay);
                        array_push($datafield, 'buyer payment method');
                    }

                    if ($sor_pic) {
                        array_push($newvalarr, $sor_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($sor_price) {
                        array_push($newvalarr, $sor_price);
                        array_push($datafield, 'price');
                    }
                    if ($sor_stock_out_warehouse_id > 0) {
                        array_push($newvalarr, isset($sorWarehouseOptionMap[$sor_stock_out_warehouse_id]) ? $sorWarehouseOptionMap[$sor_stock_out_warehouse_id] : ('Warehouse #' . $sor_stock_out_warehouse_id));
                        array_push($datafield, 'stock_out_warehouse_id');
                    }

                    if ($sor_voucher) {
                        array_push($newvalarr, $sor_voucher);
                        array_push($datafield, 'voucher');
                    }

                    if ($sor_shipping) {
                        array_push($newvalarr, $sor_shipping);
                        array_push($datafield, 'actual shipping');
                    }

                    if ($sor_serv) {
                        array_push($newvalarr, $sor_serv);
                        array_push($datafield, 'service fee');
                    }

                    if ($sor_trans) {
                        array_push($newvalarr, $sor_trans);
                        array_push($datafield, 'transaction fee');
                    }

                    if ($sor_ams) {
                        array_push($newvalarr, $sor_ams);
                        array_push($datafield, 'AMS fee');
                    }

                    if ($sor_saver_program_fee) {
                        array_push($newvalarr, $sor_saver_program_fee);
                        array_push($datafield, 'Saver Programme Fee');
                    }

                    if ($sor_fees) {
                        array_push($newvalarr, $sor_fees);
                        array_push($datafield, 'fees and charges');
                    }

                    if ($sor_final) {
                        array_push($newvalarr, $sor_final);
                        array_push($datafield, 'final amount');
                    }

                    if ($sor_remark) {
                        array_push($newvalarr, $sor_remark);
                        array_push($datafield, 'remark');
                    }
                    if ($sor_order_status) {
                        array_push($newvalarr, $sor_order_status);
                        array_push($datafield, 'order_status');
                    }
                    if ($effectiveAirbill !== '') {
                        array_push($newvalarr, $effectiveAirbill);
                        array_push($datafield, 'airbill_no');
                    }
                    if ($sorCustomerNameColumnExists && $sor_customer_name !== '') {
                        array_push($newvalarr, $sor_customer_name);
                        array_push($datafield, 'customer_name');
                    }
                    if ($sor_customer_address !== '') {
                        array_push($newvalarr, $sor_customer_address);
                        array_push($datafield, 'customer_address');
                    }

                    $safeSorAcc = mysqli_real_escape_string($finance_connect, $sor_acc);
                    $safeSorCurr = mysqli_real_escape_string($finance_connect, $sor_curr);
                    $safeSorOrder = mysqli_real_escape_string($finance_connect, $sor_order);
                    $safeSorDate = mysqli_real_escape_string($finance_connect, $sor_date);
                    $safeSorTime = mysqli_real_escape_string($finance_connect, $sor_time);
                    $safeSorPkg = mysqli_real_escape_string($finance_connect, $sor_pkg);
                    $safePackageQtySnapshotJson = mysqli_real_escape_string($finance_connect, $packageQtySnapshotJson);
                    $safeSorBrand = mysqli_real_escape_string($finance_connect, $sor_brand);
                    $safeSorUser = mysqli_real_escape_string($finance_connect, $sor_user);
                    $safeSorPay = mysqli_real_escape_string($finance_connect, $sor_pay);
                    $safeSorPic = mysqli_real_escape_string($finance_connect, $sor_pic);
                    $safeSorPrice = mysqli_real_escape_string($finance_connect, $sor_price);
                    $safeSorVoucher = mysqli_real_escape_string($finance_connect, $sor_voucher);
                    $safeSorShipping = mysqli_real_escape_string($finance_connect, $sor_shipping);
                    $safeSorServ = mysqli_real_escape_string($finance_connect, $sor_serv);
                    $safeSorTrans = mysqli_real_escape_string($finance_connect, $sor_trans);
                    $safeSorAms = mysqli_real_escape_string($finance_connect, $sor_ams);
                    $safeSorSaverProgramFee = mysqli_real_escape_string($finance_connect, $sor_saver_program_fee);
                    $safeSorFees = mysqli_real_escape_string($finance_connect, $sor_fees);
                    $safeSorFinal = mysqli_real_escape_string($finance_connect, $sor_final);
                    $safeSorRemark = mysqli_real_escape_string($finance_connect, $sor_remark);
                    $safeSorStatus = mysqli_real_escape_string($finance_connect, $sor_order_status);
                    $safeStockOutWarehouseId = (int) $sor_stock_out_warehouse_id;
                    $safeAirbill = mysqli_real_escape_string($finance_connect, $effectiveAirbill);
                    $safeAirbillAttachment = mysqli_real_escape_string($finance_connect, $sor_airbill_attachment);
                    $safeCustomerName = mysqli_real_escape_string($finance_connect, $sor_customer_name);
                    $safeCustomerAddress = mysqli_real_escape_string($finance_connect, $sor_customer_address);
                    $customerNameColumnSql = $sorCustomerNameColumnExists ? 'customer_name,' : '';
                    $customerNameValueSql = $sorCustomerNameColumnExists ? ("'$safeCustomerName',") : '';

                    $query = "INSERT INTO " . $tblName . " (shopee_acc,currency,orderID,date,time,package,package_qty_json,brand,buyer,buyer_pay_meth,pic," . $customerNameColumnSql . "customer_address,price,voucher,act_shipping_fee,service_fee,trans_fee,ams_fee,saver_program_fee,fees,final_amt,airbill_no,airbill_attachment,stock_out_warehouse_id,remark,order_status,latest_transition_at,create_by,create_date,create_time) VALUES ('$safeSorAcc','$safeSorCurr','$safeSorOrder','$safeSorDate','$safeSorTime','$safeSorPkg','$safePackageQtySnapshotJson','$safeSorBrand','$safeSorUser','$safeSorPay','$safeSorPic'," . $customerNameValueSql . "'$safeCustomerAddress','$safeSorPrice','$safeSorVoucher','$safeSorShipping','$safeSorServ','$safeSorTrans','$safeSorAms','$safeSorSaverProgramFee','$safeSorFees','$safeSorFinal','$safeAirbill','$safeAirbillAttachment'," . $safeStockOutWarehouseId . ",'$safeSorRemark','$safeSorStatus',NOW(),'" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($finance_connect, $query);
                    if (!$returnData) {
                        throw new Exception('Database Error: ' . mysqli_error($finance_connect));
                    }

                    if ($returnData) {
                        $dataId = (int) mysqli_insert_id($finance_connect);
                        customizeBotMsgSaveOrderTemplate($connect, $sorBotMsgContext, $sorBotMsgOrderTable, $dataId, $sor_bot_msg_template_id);
                        shopeeOmsRememberWarehouseDeliveryInfo('shopee', $dataId, array(
                            'customer_name' => $sor_customer_name,
                            'customer_address' => $sor_customer_address,
                        ));
                        shopeeOmsLogTransition($finance_connect, array(
                            'order_id' => $dataId,
                            'order_code' => $sor_order,
                            'from_status' => '',
                            'to_status' => $sor_order_status,
                            'transition_action' => 'manual_add',
                            'user_id' => USER_ID,
                            'user_group_id' => USER_GROUP,
                            'remark' => 'Manual add with initial status.',
                            'source_page' => $pageTitle,
                        ));

                        if ($sor_order_status === 'TP') {
                            $freshOrderRow = shopeeOmsLoadOrder($finance_connect, $dataId);
                            if ($sor_customer_name !== '') {
                                $freshOrderRow['customer_name'] = $sor_customer_name;
                            }
                            $tokenResult = shopeeOmsCreateWarehouseToken($connect, $finance_connect, $freshOrderRow, USER_ID);
                            if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                                $notifyResult = shopeeOmsSendWarehouseNotification($connect, $finance_connect, $tokenResult['token_row'], $tokenResult['notification'], $pageTitle);
                                $sorLocalTelegramFailureMessage = $sorBuildLocalTelegramFailureMessage($notifyResult);
                                if (!empty($notifyResult['sent'])) {
                                    mysqli_query($finance_connect, "UPDATE `" . $tblName . "` SET `step_a_sent_at` = NOW() WHERE id = " . $dataId . " LIMIT 1");
                                }
                            }
                        } else if ($requiresInitialShippedAutoMove) {
                            $initialShippedResult = shopeeOmsFinalizeInitialShippedOrder($connect, $finance_connect, $dataId, USER_ID, USER_GROUP, $pageTitle);
                            if (empty($initialShippedResult['success'])) {
                                throw new Exception(isset($initialShippedResult['message']) ? $initialShippedResult['message'] : 'Unable to process initial Shipped status.');
                            }
                        }

                        if ($startedFinanceTransaction) {
                            mysqli_commit($finance_connect);
                            $startedFinanceTransaction = false;
                        }
                    }
                    $_SESSION['tempValConfirmBox'] = true;
                } catch (Exception $e) {
                    if (isset($startedFinanceTransaction) && $startedFinanceTransaction) {
                        mysqli_rollback($finance_connect);
                    }
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            } else {
                try {
                    // take old value
                    $result = getData('*', "id = '$dataId'", 'LIMIT 1', $tblName, $finance_connect);
                    $row = $result->fetch_assoc();
                    $existingStoredStockOutWarehouseId = isset($row['stock_out_warehouse_id']) ? shopeeOmsNormalizeWarehouseId($row['stock_out_warehouse_id']) : 0;
                    $existingEffectiveStockOutWarehouseId = shopeeOmsResolveStockOutWarehouseId($connect, $row, $sorDefaultWarehouseId);
                    $updatedStoredStockOutWarehouseId = $existingStoredStockOutWarehouseId;
                    $stockOutWarehouseSqlAssignment = '';

                    if ($sor_update_airbill !== 'yes') {
                        $sor_airbill = isset($row['airbill_no']) ? (string) $row['airbill_no'] : '';
                        $sor_airbill_attachment = isset($row['airbill_attachment']) ? (string) $row['airbill_attachment'] : '';
                    }

                    // check value
                    if ($row['shopee_acc'] != $sor_acc) {
                        array_push($oldvalarr, $row['shopee_acc']);
                        array_push($chgvalarr, $sor_acc);
                        array_push($datafield, 'shopee_acc');
                    }
                    if ($row['currency'] != $sor_curr) {
                        array_push($oldvalarr, $row['currency']);
                        array_push($chgvalarr, $sor_curr);
                        array_push($datafield, 'currency');
                    }

                    if ($row['orderID'] != $sor_order) {
                        array_push($oldvalarr, $row['orderID']);
                        array_push($chgvalarr, $sor_order);
                        array_push($datafield, 'orderID');
                    }

                    if ($row['date'] != $sor_date) {
                        array_push($oldvalarr, $row['date']);
                        array_push($chgvalarr, $sor_date);
                        array_push($datafield, 'date');
                    }

                    if ($row['time'] != $sor_time) {
                        array_push($oldvalarr, $row['time']);
                        array_push($chgvalarr, $sor_time);
                        array_push($datafield, 'time');
                    }

                    if ($row['package'] != $sor_pkg) {
                        array_push($oldvalarr, $row['package']);
                        array_push($chgvalarr, $sor_pkg);
                        array_push($datafield, 'package');
                    }

                    if ($row['brand'] != $sor_brand) {
                        array_push($oldvalarr, $row['brand']);
                        array_push($chgvalarr, $sor_brand);
                        array_push($datafield, 'brand');
                    }

                    if ($row['buyer'] != $sor_user) {
                        array_push($oldvalarr, $row['buyer']);
                        array_push($chgvalarr, $sor_user);
                        array_push($datafield, 'buyer');
                    }

                    if ($row['buyer_pay_meth'] != $sor_pay) {
                        array_push($oldvalarr, $row['buyer_pay_meth']);
                        array_push($chgvalarr, $sor_pay);
                        array_push($datafield, 'buyer_pay_meth');
                    }

                    if ($row['pic'] != $sor_pic) {
                        array_push($oldvalarr, $row['pic']);
                        array_push($chgvalarr, $sor_pic);
                        array_push($datafield, 'pic');
                    }

                    if ($row['price'] != $sor_price) {
                        array_push($oldvalarr, $row['price']);
                        array_push($chgvalarr, $sor_price);
                        array_push($datafield, 'price');
                    }

                    if ($row['voucher'] != $sor_voucher) {
                        array_push($oldvalarr, $row['voucher']);
                        array_push($chgvalarr, $sor_voucher);
                        array_push($datafield, 'voucher');
                    }

                    if ($row['act_shipping_fee'] != $sor_shipping) {
                        array_push($oldvalarr, $row['act_shipping_fee']);
                        array_push($chgvalarr, $sor_shipping);
                        array_push($datafield, 'act_shipping_fee');
                    }

                    if ($row['service_fee'] != $sor_serv) {
                        array_push($oldvalarr, $row['service_fee']);
                        array_push($chgvalarr, $sor_serv);
                        array_push($datafield, 'service fee');
                    }

                    if ($row['trans_fee'] != $sor_trans) {
                        array_push($oldvalarr, $row['trans_fee']);
                        array_push($chgvalarr, $sor_trans);
                        array_push($datafield, 'transaction fee');
                    }

                    if ($row['ams_fee'] != $sor_ams) {
                        array_push($oldvalarr, $row['ams_fee']);
                        array_push($chgvalarr, $sor_ams);
                        array_push($datafield, 'ams_fee');
                    }

                    if ((string) ($row['saver_program_fee'] ?? '') != (string) $sor_saver_program_fee) {
                        array_push($oldvalarr, isset($row['saver_program_fee']) ? $row['saver_program_fee'] : '0.00');
                        array_push($chgvalarr, $sor_saver_program_fee);
                        array_push($datafield, 'Saver Programme Fee');
                    }

                    if ($row['fees'] != $sor_fees) {
                        array_push($oldvalarr, $row['fees']);
                        array_push($chgvalarr, $sor_fees);
                        array_push($datafield, 'fees n charges');
                    }

                    if ($row['final_amt'] != $sor_final) {
                        array_push($oldvalarr, $row['final_amt']);
                        array_push($chgvalarr, $sor_final);
                        array_push($datafield, 'final amount');
                    }

                    if ($row['remark'] != $sor_remark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $sor_remark == '' ? 'Empty Value' : $sor_remark);
                        array_push($datafield, 'remark');
                    }

                    if ((string) (isset($row['package_qty_json']) ? $row['package_qty_json'] : '') !== (string) $packageQtySnapshotJson) {
                        array_push($oldvalarr, trim((string) (isset($row['package_qty_json']) ? $row['package_qty_json'] : '')) !== '' ? 'Package snapshot updated' : 'Empty Value');
                        array_push($chgvalarr, trim((string) $packageQtySnapshotJson) !== '' ? 'Package snapshot updated' : 'Empty Value');
                        array_push($datafield, 'package_qty_json');
                    }

                    if ((string) (isset($row['airbill_no']) ? $row['airbill_no'] : '') !== (string) $sor_airbill) {
                        array_push($oldvalarr, trim((string) (isset($row['airbill_no']) ? $row['airbill_no'] : '')) !== '' ? $row['airbill_no'] : 'Empty Value');
                        array_push($chgvalarr, trim((string) $sor_airbill) !== '' ? $sor_airbill : 'Empty Value');
                        array_push($datafield, 'airbill_no');
                    }

                    if ((string) (isset($row['airbill_attachment']) ? $row['airbill_attachment'] : '') !== (string) $sor_airbill_attachment) {
                        array_push($oldvalarr, trim((string) (isset($row['airbill_attachment']) ? $row['airbill_attachment'] : '')) !== '' ? $row['airbill_attachment'] : 'Empty Value');
                        array_push($chgvalarr, trim((string) $sor_airbill_attachment) !== '' ? $sor_airbill_attachment : 'Empty Value');
                        array_push($datafield, 'airbill_attachment');
                    }

                    if ($sorCustomerNameColumnExists && (string) (isset($row['customer_name']) ? $row['customer_name'] : '') !== (string) $sor_customer_name) {
                        array_push($oldvalarr, trim((string) (isset($row['customer_name']) ? $row['customer_name'] : '')) !== '' ? $row['customer_name'] : 'Empty Value');
                        array_push($chgvalarr, trim((string) $sor_customer_name) !== '' ? $sor_customer_name : 'Empty Value');
                        array_push($datafield, 'customer_name');
                    }

                    if ((string) (isset($row['customer_address']) ? $row['customer_address'] : '') !== (string) $sor_customer_address) {
                        array_push($oldvalarr, trim((string) (isset($row['customer_address']) ? $row['customer_address'] : '')) !== '' ? $row['customer_address'] : 'Empty Value');
                        array_push($chgvalarr, trim((string) $sor_customer_address) !== '' ? $sor_customer_address : 'Empty Value');
                        array_push($datafield, 'customer_address');
                    }
                    if ((int) $sorOriginalBotMsgTemplateId !== (int) $sor_bot_msg_template_id) {
                        array_push($oldvalarr, isset($sorBotMsgTemplateNameMap[$sorOriginalBotMsgTemplateId]) ? $sorBotMsgTemplateNameMap[$sorOriginalBotMsgTemplateId] : 'Empty Value');
                        array_push($chgvalarr, isset($sorBotMsgTemplateNameMap[$sor_bot_msg_template_id]) ? $sorBotMsgTemplateNameMap[$sor_bot_msg_template_id] : 'Empty Value');
                        array_push($datafield, 'bot_message_template');
                    }
                    if ($sorStockOutWarehouseEditable) {
                        if ($existingStoredStockOutWarehouseId > 0) {
                            $updatedStoredStockOutWarehouseId = $sor_stock_out_warehouse_id;
                        } else if ($sor_stock_out_warehouse_id > 0 && $sor_stock_out_warehouse_id !== $sorDefaultWarehouseId) {
                            $updatedStoredStockOutWarehouseId = $sor_stock_out_warehouse_id;
                        }

                        if ($existingStoredStockOutWarehouseId !== $updatedStoredStockOutWarehouseId) {
                            $oldWarehouseName = shopeeOmsResolveWarehouseNameById($connect, $existingEffectiveStockOutWarehouseId, $sorDefaultWarehouseId, $sorWarehouseNameMap);
                            $newWarehouseName = shopeeOmsResolveWarehouseNameById($connect, $updatedStoredStockOutWarehouseId, $sorDefaultWarehouseId, $sorWarehouseNameMap);
                            array_push($oldvalarr, $oldWarehouseName !== '' ? $oldWarehouseName : 'Empty Value');
                            array_push($chgvalarr, $newWarehouseName !== '' ? $newWarehouseName : 'Empty Value');
                            array_push($datafield, 'stock_out_warehouse_id');
                            $stockOutWarehouseSqlAssignment = "stock_out_warehouse_id = " . ($updatedStoredStockOutWarehouseId > 0 ? $updatedStoredStockOutWarehouseId : 'NULL') . ", ";
                        }
                    }

                    // convert into string
                    $oldval = implode(",", $oldvalarr);
                    $chgval = implode(",", $chgvalarr);
                    $_SESSION['tempValConfirmBox'] = true;

                    if (count($oldvalarr) > 0 && count($chgvalarr) > 0) {
                        $query = "UPDATE " . $tblName . " SET ";
                        $query .= "shopee_acc = '$sor_acc', ";
                        $query .= "currency = '$sor_curr', ";
                        $query .= "orderID = '$sor_order', ";
                        $query .= "date = '$sor_date', ";
                        $query .= "time = '$sor_time', ";
                        $query .= "package = '$sor_pkg', ";
                        $query .= "package_qty_json = '" . mysqli_real_escape_string($finance_connect, $packageQtySnapshotJson) . "', ";
                        $query .= "brand = '$sor_brand', ";
                        $query .= "buyer = '$sor_user', ";
                        $query .= "buyer_pay_meth = '$sor_pay', ";
                        $query .= "pic = '$sor_pic', ";
                        if ($sorCustomerNameColumnExists) {
                            $query .= "customer_name = '" . mysqli_real_escape_string($finance_connect, $sor_customer_name) . "', ";
                        }
                        $query .= "customer_address = '" . mysqli_real_escape_string($finance_connect, $sor_customer_address) . "', ";
                        $query .= "price = '$sor_price', ";
                        $query .= "voucher = '$sor_voucher', ";
                        $query .= "act_shipping_fee = '$sor_shipping', ";
                        $query .= "service_fee = '$sor_serv', ";
                        $query .= "trans_fee = '$sor_trans', ";
                        $query .= "ams_fee = '$sor_ams', ";
                        $query .= "saver_program_fee = '$sor_saver_program_fee', ";
                        $query .= "fees = '$sor_fees', ";
                        $query .= "final_amt = '$sor_final', ";
                        $query .= "airbill_no = '" . mysqli_real_escape_string($finance_connect, $sor_airbill) . "', ";
                        $query .= "airbill_attachment = '" . mysqli_real_escape_string($finance_connect, $sor_airbill_attachment) . "', ";
                        $query .= $stockOutWarehouseSqlAssignment;
                        $query .= "remark = '" . mysqli_real_escape_string($finance_connect, $sor_remark) . "', ";
                        $query .= "update_by = '" . USER_ID . "', ";
                        $query .= "update_date = curdate(), ";
                        $query .= "update_time = curtime() ";
                        $query .= "WHERE id = '$dataId'"; // Specify your condition here

                        $returnData = mysqli_query($finance_connect, $query);
                        if ($returnData) {
                            customizeBotMsgSaveOrderTemplate($connect, $sorBotMsgContext, $sorBotMsgOrderTable, (int) $dataId, $sor_bot_msg_template_id);
                            $newValuesForHistory = array(
                                'orderID' => $sor_order,
                                'customer_address' => $sor_customer_address,
                                'package' => $sor_pkg,
                                'package_qty_json' => $packageQtySnapshotJson,
                                'brand' => $sor_brand,
                                'buyer' => $sor_user,
                                'buyer_pay_meth' => $sor_pay,
                                'price' => $sor_price,
                                'stock_out_warehouse_id' => $updatedStoredStockOutWarehouseId,
                                'airbill_no' => $sor_airbill,
                                'airbill_attachment' => $sor_airbill_attachment,
                                'remark' => $sor_remark,
                                'estimated_received_date' => isset($row['estimated_received_date']) ? $row['estimated_received_date'] : '',
                                'delay_remark' => isset($row['delay_remark']) ? $row['delay_remark'] : '',
                                'order_status' => isset($row['order_status']) ? $row['order_status'] : '',
                            );
                            if ($sorCustomerNameColumnExists) {
                                $newValuesForHistory['customer_name'] = $sor_customer_name;
                            }
                            $orderChanges = shopeeOmsDetectOrderChanges($connect, $row, $newValuesForHistory);
                            shopeeOmsLogOrderEditHistory($finance_connect, (int) $dataId, $sor_order, $orderChanges, USER_ID, USER_GROUP, $pageTitle);
                        } else {
                            $error = 1;
                            $errorMsg = trim((string) mysqli_error($finance_connect)) !== ''
                                ? 'Unable to save edited order details: ' . mysqli_error($finance_connect)
                                : 'Unable to save edited order details.';
                            $act = "F";
                        }

                    } else {
                        $act = 'NC';
                    }
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    $act = "F";
                }
            }

            if ($action === 'updRecord' && $sorShouldSaveBeforeStatusUpdate && !isset($error) && (($act === 'NC') || !empty($returnData))) {
                $sorTriggerStatusTransitionAfterSave = true;
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
                    'query_table' => $tblName,
                    'page' => $pageTitle,
                    'connect' => $connect,
                ];

                if ($pageAction == 'Add') {
                    $log['newval'] = implodeWithComma($newvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval'] = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                }
                audit_log($log);
            }

            if ($action === 'updRecord' && $sorSaveBeforeStatusOnly) {
                $statusSaveErrorMessage = $buildShopeeOrderReqSaveErrorMessage(array(
                    isset($airbill_err) ? $airbill_err : '',
                    isset($airbill_attachment_err) ? $airbill_attachment_err : '',
                    isset($customer_address_err) ? $customer_address_err : '',
                    isset($stock_out_warehouse_err) ? $stock_out_warehouse_err : '',
                    isset($pkg_err) ? $pkg_err : '',
                    isset($brand_err) ? $brand_err : '',
                    isset($user_err) ? $user_err : '',
                    isset($pay_err) ? $pay_err : '',
                    isset($pic_err) ? $pic_err : '',
                    isset($price_err) ? $price_err : '',
                    isset($order_err) ? $order_err : '',
                    isset($date_err) ? $date_err : '',
                    isset($time_err) ? $time_err : '',
                    isset($curr_err) ? $curr_err : '',
                    isset($acc_err) ? $acc_err : '',
                ), $errorMsg);
                $sorPrepareAjaxJsonResponse();
                if (isset($error) || $act === 'F') {
                    echo json_encode(array(
                        'success' => false,
                        'message' => $statusSaveErrorMessage,
                    ));
                    exit;
                }

                echo json_encode(array(
                    'success' => true,
                    'message' => !empty($returnData) ? 'Order details updated successfully.' : 'No changes were made.',
                    'has_changes' => !empty($returnData),
                ));
                exit;
            }

            if ($action === 'updRecord' && $sorShouldSaveBeforeStatusUpdate) {
                if ($sorTriggerStatusTransitionAfterSave) {
                    unset($_SESSION['tempValConfirmBox']);
                    if ($pendingStatusUpdate === 'PR' && $sorConfirmReceiveWithFollowUp) {
                        $sorHandleConfirmReceiveWithFollowUp();
                    }
                    $sorTransitionResult = $sorHandleStatusTransition($pendingStatusUpdate);
                    if (is_array($sorTransitionResult) && empty($sorTransitionResult['success'])) {
                        $transitionErrorState = shopeeOmsResolveStatusTransitionErrorState(
                            $pendingStatusUpdate,
                            isset($sorTransitionResult['message']) ? $sorTransitionResult['message'] : '',
                            'Unable to update order status.'
                        );
                        if ($transitionErrorState['stock_out_warehouse_err'] !== '') {
                            $stock_out_warehouse_err = $transitionErrorState['stock_out_warehouse_err'];
                        }
                        $sorPopupErrorMessage = $transitionErrorState['popup_error_message'];
                        break;
                    }
                }

                $statusSaveErrorMessage = $buildShopeeOrderReqSaveErrorMessage(array(
                    isset($airbill_err) ? $airbill_err : '',
                    isset($airbill_attachment_err) ? $airbill_attachment_err : '',
                    isset($customer_address_err) ? $customer_address_err : '',
                    isset($stock_out_warehouse_err) ? $stock_out_warehouse_err : '',
                    isset($pkg_err) ? $pkg_err : '',
                    isset($brand_err) ? $brand_err : '',
                    isset($user_err) ? $user_err : '',
                    isset($pay_err) ? $pay_err : '',
                    isset($pic_err) ? $pic_err : '',
                    isset($price_err) ? $price_err : '',
                    isset($order_err) ? $order_err : '',
                    isset($date_err) ? $date_err : '',
                    isset($time_err) ? $time_err : '',
                    isset($curr_err) ? $curr_err : '',
                    isset($acc_err) ? $acc_err : '',
                ), $errorMsg);

                if ($sorIsAjaxRequest) {
                    $sorPrepareAjaxJsonResponse();
                    echo json_encode(array(
                        'success' => false,
                        'message' => $statusSaveErrorMessage,
                    ));
                    exit;
                }

                echo '<script>alert(' . json_encode((string) $statusSaveErrorMessage) . ');</script>';
                exit;
            }

            break;
    }
}


if (post('act') == 'D') {
    $id = post('id');
    if ($id) {
        try {
            $result = getData('*', "id = '$id'", 'LIMIT 1', $tblName, $finance_connect);
            if (!$result || $result->num_rows === 0) {
                renderNotificationScript('Order record was not found.', 'error', $redirectPage, 1200, true);
                exit;
            }

            $row = $result->fetch_assoc();
            $dataId = (int) $row['id'];
            $deleteLabel = isset($row['orderID']) ? trim((string) $row['orderID']) : '';
            if ($deleteLabel === '') {
                $deleteLabel = 'Order #' . $dataId;
            }

            $deleteApprovalResult = orderDeleteApprovalRequestDelete($connect, $orderDeleteApprovalModuleKey, $dataId, $deleteLabel, $pageTitle);
            if (!empty($deleteApprovalResult['direct_delete'])) {
                $deleteResult = $sorExecuteDeleteOrder(array(
                    'source_order_id' => $dataId,
                    'source_order_label' => $deleteLabel,
                ));
                renderNotificationScript(
                    $deleteResult['message'],
                    !empty($deleteResult['success']) ? 'success' : 'error',
                    $redirectPage,
                    1200,
                    true
                );
                exit;
            }

            renderNotificationScript(
                $deleteApprovalResult['message'],
                isset($deleteApprovalResult['notification_type']) ? $deleteApprovalResult['notification_type'] : (!empty($deleteApprovalResult['success']) ? 'success' : 'error'),
                $redirectPage,
                1200,
                true
            );
            exit;
        } catch (Exception $e) {
            renderNotificationScript($e->getMessage(), 'error', $redirectPage, 1200, true);
            exit;
        }
    }
}

//view
if (($dataId) && !($act) && (USER_ID != '') && empty($_SESSION['viewChk']) && empty($_SESSION['delChk'])) {
    $_SESSION['viewChk'] = 1;

    if (isset($errorExist)) {
        $viewActMsg = USER_NAME . " fail to viewed the data [<b> ID = " . $dataId . "</b> ] from <b><i>$tblName Table</i></b>.";
    } else {
        $viewActMsg = USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . (isset($row['orderID']) ? $row['orderID'] : $dataId) . "</b> from <b><i>$tblName Table</i></b>.";
    }

    $log = [
        'log_act' => $pageAction,
        'cdate' => $cdate,
        'ctime' => $ctime,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $viewActMsg,
        'page' => $pageTitle,
        'connect' => $connect,
    ];

    audit_log($log);
}

$urbanismBadgeSeedName = '';
if (isset($row['buyer']) && trim((string) $row['buyer']) !== '') {
    $urbanismBadgeSeedName = trim((string) $row['buyer']);
}
if ($urbanismBadgeSeedName === '' && postSpaceFilter('sor_user_hidden') !== '') {
    $urbanismBadgeSeedName = trim((string) postSpaceFilter('sor_user_hidden'));
}
if ($urbanismBadgeSeedName === '' && postSpaceFilter('sor_user') !== '') {
    $urbanismBadgeSeedName = trim((string) postSpaceFilter('sor_user'));
}

// Some Shopee orders persist buyer as SHOPEE_CUST_INFO.id instead of username.
// Resolve to buyer_username first so Urbanism member matching is stable.
if ($urbanismBadgeSeedName !== '' && ctype_digit($urbanismBadgeSeedName)) {
    $buyerId = (int) $urbanismBadgeSeedName;
    if ($buyerId > 0) {
        $buyerRst = getData('buyer_username', "id='" . $buyerId . "'", 'LIMIT 1', SHOPEE_CUST_INFO, $finance_connect);
        if ($buyerRst && $buyerRst->num_rows > 0) {
            $buyerRow = $buyerRst->fetch_assoc();
            if (isset($buyerRow['buyer_username']) && trim((string) $buyerRow['buyer_username']) !== '') {
                $urbanismBadgeSeedName = trim((string) $buyerRow['buyer_username']);
            }
        }
    }
}

$urbanismBadgeAction = getUrbanismMemberActionData(
    $connect,
    '',
    $urbanismBadgeSeedName,
    $redirectPage,
    $pageTitle
);

$transitionHistoryRows = array();
$editHistoryRows = array();
if (isset($row['id']) && (int) $row['id'] > 0) {
    shopeeOmsBackfillParcelReceivedTransitionRemarks($connect, $finance_connect);
    $transitionHistoryRows = shopeeOmsFetchTransitionHistory($finance_connect, (int) $row['id']);
    $editHistoryRows = shopeeOmsFetchEditHistory($finance_connect, (int) $row['id']);
}

?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="../css/main.css">
    <script src="../finance/header/js/pdf.min.js"></script>
    <script src="../finance/header/js/tesseract.min.js"></script>
    <script src="../js/pdf_airbill_parser.js"></script>
    <style>
        .shopee-airbill-row {
            align-items: flex-start;
        }

        .shopee-airbill-toggle-col {
            display: flex;
            flex-direction: column;
        }

        .shopee-airbill-toggle-field {
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            margin-top: 0;
            padding: 0;
        }

        .shopee-airbill-toggle-label {
            margin: 0;
        }

        @media (max-width: 767px) {
            .shopee-airbill-toggle-col {
                margin-top: 0;
            }
        }

        .shopee-airbill-toggle {
            position: relative;
            width: 54px;
            height: 28px;
            display: inline-flex;
            align-items: center;
        }

        .shopee-airbill-toggle input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .shopee-airbill-toggle-slider {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
            border-radius: 999px;
            background: #31343a;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle-slider::before {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ffffff;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle-slider::after {
            content: "\f00d";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            color: #ffffff;
            font-size: 0.62rem;
            position: absolute;
            right: 10px;
            top: 8px;
            transition: all 0.18s ease;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider {
            background: #6f922f;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider::before {
            left: 29px;
        }

        .shopee-airbill-toggle input:checked + .shopee-airbill-toggle-slider::after {
            content: "\f00c";
            right: 32px;
        }

        .shopee-inline-error {
            display: block;
            margin-top: 6px;
            color: #dc3545;
            font-size: 0.875rem;
        }

        .shopee-inline-invalid {
            border-color: #dc3545 !important;
        }

        .shopee-airbill-extract-status {
            display: block;
            margin-top: 6px;
            font-size: 0.875rem;
            color: #6c757d;
        }

        .shopee-airbill-extract-status.is-error {
            color: #dc3545;
        }

        .shopee-airbill-preview-media {
            width: 100%;
            max-width: 520px;
        }

        #sorVerifyOrderModal .modal-footer .btn,
        #sorVerifyOrderModal .modal-header .btn,
        #sorVerifyOrderModal .modal-body .btn {
            text-transform: none !important;
        }

        .shopee-verify-method-card {
            border: 1px solid #d9e2ef;
            border-radius: 14px;
            background: #fff;
            padding: 20px 22px;
            width: 100%;
            text-align: left;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .shopee-verify-method-card:hover {
            transform: translateY(-1px);
        }

        .shopee-verify-method-card-success {
            border-color: #31b45a;
            box-shadow: 0 0 0 2px rgba(49, 180, 90, 0.08);
        }

        .shopee-verify-method-card-primary {
            border-color: #2f6fdd;
            box-shadow: 0 0 0 2px rgba(47, 111, 221, 0.08);
        }

        .shopee-airbill-preview-media img,
        .shopee-airbill-preview-media iframe {
            width: 100%;
            border: 1px solid #d9e2ef;
            border-radius: 10px;
            background: #fff;
        }

        .shopee-airbill-preview-media img {
            height: auto;
            display: block;
        }

        .shopee-airbill-preview-media iframe {
            min-height: 520px;
        }

        .shopee-airbill-current-attachment {
            display: flex;
            flex-wrap: wrap;
            gap: 0 4px;
            max-width: 100%;
        }

        .shopee-airbill-current-attachment-value {
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        @media (max-width: 767px) {
            .shopee-airbill-current-attachment {
                display: block;
            }

            .shopee-airbill-current-attachment-label,
            .shopee-airbill-current-attachment-value {
                display: block;
                max-width: 100%;
            }
        }

    </style>
</head>

<body>
    <div id="shopeeOrderReqBreadcrumbWrap" class="d-flex flex-column my-3 ms-3">
        <p><a href="<?= htmlspecialchars((string) $back_redirect_page, ENT_QUOTES, 'UTF-8') ?>">
                <?= $pageTitle ?>
            </a> <i class="fa-solid fa-chevron-right fa-xs"></i>
            <?php
            echo displayPageAction($act, $pageTitle);
            ?>
        </p>

    </div>

    <div id="formContainer" class="container d-flex justify-content-center">
        <div class="col-6 col-md-6 formWidthAdjust">
            <form id="FORForm" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars((string) $back_redirect_page, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group mb-5">
                    <div class="order-title-row">
                        <h2 class="mb-0"><?php echo displayPageAction($act, $pageTitle); ?></h2>
                        <?php if ($act == 'E'): ?>
                            <span id="order-status">Order Status: <?= getOrderStatusLabel($row['order_status']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="order-badge-row text-end mt-2">
                        <a
                            class="btn btn-sm <?= $urbanismBadgeAction['is_member'] ? 'btn-success' : 'btn-outline-secondary' ?> <?= $urbanismBadgeAction['disabled'] ? 'disabled' : '' ?>"
                            href="<?= htmlspecialchars($urbanismBadgeAction['url'], ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= htmlspecialchars($urbanismBadgeAction['title'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $urbanismBadgeAction['disabled'] ? 'onclick="return false;" aria-disabled="true"' : '' ?>><i class="<?= htmlspecialchars($urbanismBadgeAction['icon_class'], ENT_QUOTES, 'UTF-8') ?>"></i></a>
                    </div>
                  
                </div>

                <div id="err_msg" class="mb-3">
                    <span class="mt-n2" style="font-size: 21px;">
                        <?php if (isset($err1))
                            echo $err1; ?>
                    </span>
                </div>

                <?php echo $orderDeleteApprovalPanelHtml; ?>

                <div class="form-group">
                    <div class="row">
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_acc_label" for="sor_acc">Shopee Account
                                <span class="requireRed">*</span></label>
                            <select class="form-select" id="sor_acc" name="sor_acc" <?php if ($act == '')
                                echo 'disabled' ?>>
                                    <option value="0" disabled selected>Select Shopee Account</option>
                                    <?php
                            $query = "SELECT * FROM " . SHOPEE_ACC . " WHERE `status` = 'A' ORDER BY `name` ASC";
                            $acc_result = $finance_connect->query($query);
                            if ($acc_result->num_rows >= 1) {
                                $acc_result->data_seek(0);
                                while ($row3 = $acc_result->fetch_assoc()) {
                                    $selected = "";
                                    if (isset($dataExisted, $row['shopee_acc']) && !isset($sor_acc)) {
                                        $selected = $row['shopee_acc'] == $row3['id'] ? " selected" : "";
                                    } else if (isset($sor_acc)) {
                                        $selected = $sor_acc == $row3['id'] ? " selected" : "";
                                    }
                                    echo "<option value=\"" . $row3['id'] . "\"$selected>" . $row3['name'] . "</option>";
                                }
                            } else {
                                echo "<option value=\"0\">None</option>";
                            }

                            ?>
                            </select>
                            <?php if (isset($acc_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $acc_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_curr_lbl" for="sor_curr">Currency<span
                                    class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);
                            if (isset($row['currency']))
                                $echoVal = $row['currency'];

                            if (isset($echoVal)) {
                                $curr_rst = getData('*', "id = '$echoVal'", '', CUR_UNIT, $connect);
                                $curr_row = $curr_rst ? $curr_rst->fetch_assoc() : [];
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_curr" id="sor_curr" disabled value="<?php echo !empty($echoVal) ? $curr_row['unit'] : '' ?>">
                            <input type="hidden" name="sor_curr_hidden" id="sor_curr_hidden" value="<?php echo (isset($row['currency'])) ? $row['currency'] : ''; ?>">
                            <?php if (isset($curr_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $curr_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_order_lbl" for="sor_order">Order ID<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="text" name="sor_order" id="sor_order" value="<?php
                            if (isset($dataExisted) && isset($row['orderID']) && !isset($sor_order)) {
                                echo $row['orderID'];
                            } else if (isset($sor_order)) {
                                echo $sor_order;
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($order_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $order_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_date_label" for="sor_date">Date<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="date" name="sor_date" id="sor_date" value="<?php
                            if (isset($dataExisted) && isset($row['date']) && !isset($sor_date)) {
                                echo $row['date'];
                            } else if (isset($sor_date)) {
                                echo $sor_date;
                            } else {
                                echo date('Y-m-d');
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
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_time_label" for="sor_time">Time<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="time" name="sor_time" id="sor_time" value="<?php
                            if (isset($dataExisted) && isset($row['time']) && !isset($sor_time)) {
                                echo !empty($row['time']) ? date('H:i', strtotime($row['time'])) : '';
                            } else if (isset($sor_time)) {
                                echo !empty($sor_time) ? date('H:i', strtotime($sor_time)) : '';
                            } else {
                                echo date('H:i');
                            }
                            ?>" placeholder="HH:MM" pattern="[0-9]{2}:[0-9]{2}" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($time_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $time_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_pkg_lbl" for="sor_pkg_hidden">Package<span
                                    class="requireRed">*</span></label>
                            <?php
                            $selectedPkgIds = array();
                            $postedPkgNames = postSpaceFilter('sor_pkg') ?: array();
                            $postedPkgIds = postSpaceFilter('sor_pkg_hidden') ?: array();
                            if (isset($sor_pkg) && $sor_pkg !== '') {
                                $selectedPkgIds = array_filter(array_map('trim', explode(',', $sor_pkg)), 'strlen');
                            }

                            $pkgRows = array();
                            if (!empty($selectedPkgIds)) {
                                foreach ($selectedPkgIds as $pkgId) {
                                    $pkgIdInt = (int) $pkgId;
                                    $pkgName = '';
                                    if ($pkgIdInt > 0) {
                                        $pkgRst = getData('name', "id = '$pkgIdInt'", 'LIMIT 1', PKG, $connect);
                                        if ($pkgRst && $pkgRst->num_rows > 0) {
                                            $pkgData = $pkgRst->fetch_assoc();
                                            $pkgName = $pkgData['name'];
                                        }
                                    }
                                    $pkgRows[] = array('id' => $pkgIdInt, 'name' => $pkgName);
                                }
                            } else if (isset($row) && is_array($row)) {
                                $pkgRows = $sorBuildStoredPackageRows($row);
                            } else if (!empty($postedPkgNames)) {
                                foreach ($postedPkgNames as $idx => $pkgName) {
                                    $postedPkgId = isset($postedPkgIds[$idx]) ? (int) $postedPkgIds[$idx] : 0;
                                    $pkgRows[] = array('id' => $postedPkgId, 'name' => trim((string) $pkgName));
                                }
                            }

                            if (empty($pkgRows)) {
                                $pkgRows[] = array('id' => '', 'name' => '');
                            }
                            ?>
                            <div id="sor_pkg_container">
                                <?php foreach ($pkgRows as $pkgIndex => $pkgRow) { ?>
                                    <div class="input-group mb-2 sor-pkg-row autocomplete">
                                        <input class="form-control sor-pkg-input" type="text" name="sor_pkg[]"
                                            id="sor_pkg_<?php echo $pkgIndex; ?>"
                                            data-hidden-target="sor_pkg_hidden_<?php echo $pkgIndex; ?>"
                                            value="<?php echo htmlspecialchars($pkgRow['name']); ?>" <?php if ($act == '') echo 'disabled'; ?>>
                                        <input type="hidden" class="sor-pkg-hidden" name="sor_pkg_hidden[]"
                                            id="sor_pkg_hidden_<?php echo $pkgIndex; ?>"
                                            value="<?php echo htmlspecialchars((string) $pkgRow['id']); ?>">
                                        <?php if ($act != '' && $pkgIndex > 0) { ?>
                                            <button type="button" class="btn btn-outline-danger sor-remove-row-btn" data-row-type="pkg" title="Remove Package">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($act != '') { ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="add_pkg_btn">+ Add Package</button>
                            <?php } ?>
                            <?php if (isset($pkg_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $pkg_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_brand_lbl" for="sor_brand_hidden">Brand<span
                                    class="requireRed">*</span></label>
                            <?php
                            $selectedBrandIds = array();
                            $postedBrandNames = postSpaceFilter('sor_brand') ?: array();
                            $postedBrandIds = postSpaceFilter('sor_brand_hidden') ?: array();
                            if (isset($sor_brand) && $sor_brand !== '') {
                                $selectedBrandIds = array_filter(array_map('trim', explode(',', $sor_brand)), 'strlen');
                            } else if (isset($row['brand']) && $row['brand'] !== '') {
                                $selectedBrandIds = array_filter(array_map('trim', explode(',', $row['brand'])), 'strlen');
                            }

                            $brandRows = array();
                            if (!empty($selectedBrandIds)) {
                                foreach ($selectedBrandIds as $brandId) {
                                    $brandIdInt = (int) $brandId;
                                    $brandName = '';
                                    if ($brandIdInt > 0) {
                                        $brandRst = getData('name', "id = '$brandIdInt'", 'LIMIT 1', BRAND, $connect);
                                        if ($brandRst && $brandRst->num_rows > 0) {
                                            $brandData = $brandRst->fetch_assoc();
                                            $brandName = $brandData['name'];
                                        }
                                    }
                                    $brandRows[] = array('id' => $brandIdInt, 'name' => $brandName);
                                }
                            } else if (!empty($postedBrandNames)) {
                                foreach ($postedBrandNames as $idx => $brandName) {
                                    $postedBrandId = isset($postedBrandIds[$idx]) ? (int) $postedBrandIds[$idx] : 0;
                                    $brandRows[] = array('id' => $postedBrandId, 'name' => trim((string) $brandName));
                                }
                            }

                            if (empty($brandRows)) {
                                $brandRows[] = array('id' => '', 'name' => '');
                            }
                            ?>
                            <div id="sor_brand_container">
                                <?php foreach ($brandRows as $brandIndex => $brandRow) { ?>
                                    <div class="input-group mb-2 sor-brand-row autocomplete">
                                        <input class="form-control sor-brand-input" type="text" name="sor_brand[]"
                                            id="sor_brand_<?php echo $brandIndex; ?>"
                                            data-hidden-target="sor_brand_hidden_<?php echo $brandIndex; ?>"
                                            value="<?php echo htmlspecialchars($brandRow['name']); ?>" <?php if ($act == '') echo 'disabled'; ?>>
                                        <input type="hidden" class="sor-brand-hidden" name="sor_brand_hidden[]"
                                            id="sor_brand_hidden_<?php echo $brandIndex; ?>"
                                            value="<?php echo htmlspecialchars((string) $brandRow['id']); ?>">
                                        <?php if ($act != '' && $brandIndex > 0) { ?>
                                            <button type="button" class="btn btn-outline-danger sor-remove-row-btn" data-row-type="brand" title="Remove Brand">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($act != '') { ?>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="add_brand_btn">+ Add Brand</button>
                            <?php } ?>
                            <?php if (isset($brand_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $brand_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row shopee-airbill-row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label form_lbl" for="sor_order_status">Initial Order Status<span class="requireRed">*</span></label>
                            <?php
                            $currentOrderStatusValue = isset($sor_order_status) && trim((string) $sor_order_status) !== ''
                                ? $sor_order_status
                                : (isset($row['order_status']) ? shopeeOmsNormalizeStatusCode($row['order_status']) : 'P');
                            ?>
                            <?php if ($act === 'I') { ?>
                                <select class="form-select" id="sor_order_status" name="sor_order_status">
                                    <?php foreach ($sorStatusOptions as $statusCode => $statusLabel) { ?>
                                        <option value="<?= htmlspecialchars($statusCode) ?>" <?= $currentOrderStatusValue === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars($statusLabel) ?></option>
                                    <?php } ?>
                                </select>
                            <?php } else { ?>
                                <input class="form-control" type="text" value="<?= htmlspecialchars(shopeeOmsGetStatusLabel($currentOrderStatusValue)) ?>" readonly>
                                <input type="hidden" id="sor_order_status" name="sor_order_status" value="<?= htmlspecialchars($currentOrderStatusValue) ?>">
                            <?php } ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label form_lbl" for="sor_stock_out_warehouse_id">Stock Out Warehouse<span class="requireRed">*</span></label>
                            <?php
                            $currentStockOutWarehouseId = isset($sor_stock_out_warehouse_id) && (int) $sor_stock_out_warehouse_id > 0
                                ? (int) $sor_stock_out_warehouse_id
                                : (isset($row) ? shopeeOmsResolveStockOutWarehouseId($connect, $row, $sorDefaultWarehouseId) : $sorDefaultWarehouseId);
                            $isStockOutWarehouseEditableForForm = $act !== '' && ($act === 'I' || shopeeOmsIsStockOutWarehouseEditable(isset($row['order_status']) ? $row['order_status'] : ''));
                            if ($isStockOutWarehouseEditableForForm && $currentStockOutWarehouseId > 0 && !isset($sorWarehouseOptionMap[$currentStockOutWarehouseId])) {
                                $currentStockOutWarehouseId = $sorDefaultWarehouseId;
                            }
                            if ($currentStockOutWarehouseId <= 0 && !empty($sorWarehouseRows)) {
                                $currentStockOutWarehouseId = (int) $sorWarehouseRows[0]['id'];
                            }
                            $currentStockOutWarehouseName = shopeeOmsResolveWarehouseNameById($connect, $currentStockOutWarehouseId, $sorDefaultWarehouseId, $sorWarehouseNameMap);
                            ?>
                            <?php if ($isStockOutWarehouseEditableForForm) { ?>
                                <select class="form-select" id="sor_stock_out_warehouse_id" name="sor_stock_out_warehouse_id">
                                    <?php foreach ($sorWarehouseRows as $warehouseRow) { ?>
                                        <?php $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0; ?>
                                        <option value="<?= $warehouseId ?>" <?= $currentStockOutWarehouseId === $warehouseId ? 'selected' : '' ?>><?= htmlspecialchars((string) $warehouseRow['name']) ?></option>
                                    <?php } ?>
                                </select>
                            <?php } else { ?>
                                <input class="form-control" type="text" readonly value="<?= htmlspecialchars($currentStockOutWarehouseName) ?>">
                            <?php } ?>
                            <?php if (isset($stock_out_warehouse_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $stock_out_warehouse_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_bot_msg_template_id">Bot Message Template</label>
                            <?php
                            $currentSorBotMsgTemplateId = post('actionBtn')
                                ? (int) postSpaceFilter('sor_bot_msg_template_id')
                                : ($sorOriginalBotMsgTemplateId > 0 ? (int) $sorOriginalBotMsgTemplateId : (int) $sorBotMsgDefaultTemplateId);
                            if ($currentSorBotMsgTemplateId <= 0) {
                                $currentSorBotMsgTemplateId = (int) $sorBotMsgDefaultTemplateId;
                            }
                            ?>
                            <select class="form-select" id="sor_bot_msg_template_id" name="sor_bot_msg_template_id" <?= $act == '' ? 'disabled' : '' ?>>
                                <?php foreach ($sorBotMsgTemplateOptions as $sorBotMsgTemplateOption) { ?>
                                    <?php $templateOptionId = isset($sorBotMsgTemplateOption['id']) ? (int) $sorBotMsgTemplateOption['id'] : 0; ?>
                                    <option value="<?= $templateOptionId ?>" <?= $currentSorBotMsgTemplateId === $templateOptionId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $sorBotMsgTemplateOption['template_name'] . (customizeBotMsgIsDefaultRow($sorBotMsgTemplateOption) ? ' (Default)' : ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3 shopee-airbill-toggle-col">
                            <?php
                            $hasSavedAirbillData = false;
                            if (isset($row['airbill_no']) && trim((string) $row['airbill_no']) !== '') {
                                $hasSavedAirbillData = true;
                            }
                            if (isset($row['airbill_attachment']) && trim((string) $row['airbill_attachment']) !== '') {
                                $hasSavedAirbillData = true;
                            }
                            $updateAirbillValue = isset($sor_update_airbill) && trim((string) $sor_update_airbill) !== ''
                                ? strtolower(trim((string) $sor_update_airbill))
                                : (
                                    isset($row['update_airbill']) && trim((string) $row['update_airbill']) !== ''
                                        ? strtolower(trim((string) $row['update_airbill']))
                                        : ($hasSavedAirbillData ? 'yes' : ($act === 'I' ? 'yes' : 'no'))
                                );
                            if ($updateAirbillValue !== 'yes' && $hasSavedAirbillData) {
                                $updateAirbillValue = 'yes';
                            } else if ($updateAirbillValue !== 'yes') {
                                $updateAirbillValue = 'no';
                            }
                            ?>
                            <input type="hidden" id="sor_update_airbill" name="sor_update_airbill" value="<?= htmlspecialchars($updateAirbillValue) ?>">
                            <label class="form-label form_lbl shopee-airbill-toggle-label" for="sor_update_airbill_toggle">Update Airbill?</label>
                            <div class="shopee-airbill-toggle-field">
                                <label class="shopee-airbill-toggle mb-0" for="sor_update_airbill_toggle">
                                    <input type="checkbox" id="sor_update_airbill_toggle" <?= $updateAirbillValue === 'yes' ? 'checked' : '' ?> <?= $act == '' ? 'disabled' : '' ?>>
                                    <span class="shopee-airbill-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" for="sor_airbill">Airbill No<span class="requireRed">*</span></label>
                            <input class="form-control" type="text" name="sor_airbill" id="sor_airbill" value="<?php
                                if (isset($sor_airbill)) {
                                    echo htmlspecialchars($sor_airbill);
                                } else if (isset($row['airbill_no'])) {
                                    echo htmlspecialchars($row['airbill_no']);
                                }
                            ?>" <?= $act == '' ? 'disabled' : '' ?>>
                            <?php if (isset($airbill_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $airbill_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="sor_airbill_attachment">Airbill Attachment<span class="requireRed">*</span></label>
                            <input class="form-control" type="file" name="sor_airbill_attachment" id="sor_airbill_attachment" <?= $act == '' ? 'disabled' : '' ?>>
                            <small id="sor_airbill_extract_status" class="shopee-airbill-extract-status"></small>
                            <?php
                            $currentAirbillAttachmentValue = '';
                            $currentAirbillAttachmentHiddenValue = '';
                            $currentAirbillAttachmentLabel = 'Current Attachment:';
                            $currentAirbillAttachmentIsPendingUpload = false;
                            $savedAirbillAttachmentValue = isset($row['airbill_attachment']) ? trim((string) $row['airbill_attachment']) : '';

                            if ($savedAirbillAttachmentValue !== '') {
                                $currentAirbillAttachmentValue = $savedAirbillAttachmentValue;
                                $currentAirbillAttachmentHiddenValue = $savedAirbillAttachmentValue;
                            }

                            if (
                                isset($sorAirbillPendingUploadName)
                                && trim((string) $sorAirbillPendingUploadName) !== ''
                                && isset($error)
                            ) {
                                $currentAirbillAttachmentValue = (string) $sorAirbillPendingUploadName;
                                $currentAirbillAttachmentLabel = 'Selected Attachment:';
                                $currentAirbillAttachmentIsPendingUpload = true;
                            }
                            ?>
                            <?php if ($currentAirbillAttachmentValue !== '') { ?>
                                <div id="err_msg">
                                    <span class="mt-n1 shopee-airbill-current-attachment">
                                        <span class="shopee-airbill-current-attachment-label"><?php echo htmlspecialchars($currentAirbillAttachmentLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="shopee-airbill-current-attachment-value"><?php echo htmlspecialchars($currentAirbillAttachmentValue, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                </div>
                            <?php } ?>
                            <?php if (isset($airbill_attachment_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $airbill_attachment_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-center justify-content-md-end px-4">
                                <?php
                                $sorAirbillAttachmentSrc = '';
                                if (!$currentAirbillAttachmentIsPendingUpload && $currentAirbillAttachmentHiddenValue !== '') {
                                    $storedAttachment = trim(str_replace('\\', '/', $currentAirbillAttachmentHiddenValue), '/');
                                    if ($storedAttachment !== '') {
                                        if (strpos($storedAttachment, 'attachment/') === 0) {
                                            $sorAirbillAttachmentSrc = rtrim((string) $SITEURL, '/') . '/' . $storedAttachment;
                                        } else {
                                            $sorAirbillAttachmentSrc = $sorAirbillAttachmentUrl . basename($storedAttachment);
                                        }
                                    }
                                }

                                $sorAirbillAttachmentExt = '';
                                if ($sorAirbillAttachmentSrc !== '') {
                                    $sorAirbillAttachmentExt = strtolower(pathinfo(parse_url($sorAirbillAttachmentSrc, PHP_URL_PATH), PATHINFO_EXTENSION));
                                }
                                ?>

                                <?php if ($sorAirbillAttachmentSrc !== '') { ?>
                                    <div id="sor_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media">
                                        <?php if (in_array($sorAirbillAttachmentExt, array('png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'), true)) { ?>
                                            <img id="sor_airbill_attachment_preview_img"
                                                 src="<?php echo htmlspecialchars($sorAirbillAttachmentSrc, ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="Airbill Attachment Preview">
                                        <?php } else if ($sorAirbillAttachmentExt === 'pdf') { ?>
                                            <iframe id="sor_airbill_attachment_preview_pdf"
                                                    src="<?php echo htmlspecialchars($sorAirbillAttachmentSrc, ENT_QUOTES, 'UTF-8'); ?>"
                                                    title="Airbill Attachment Preview"></iframe>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <div id="sor_airbill_attachment_preview_wrap" class="shopee-airbill-preview-media" style="display:none;"></div>
                                <?php } ?>

                                <input type="hidden" name="sor_airbill_attachment_value" id="sor_airbill_attachment_value" value="<?php echo htmlspecialchars($currentAirbillAttachmentHiddenValue, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="sor_customer_name" id="sor_customer_name" value="<?php
                                    if (isset($sor_customer_name)) {
                                        echo htmlspecialchars($sor_customer_name, ENT_QUOTES, 'UTF-8');
                                    } else if ($sorCustomerNameColumnExists && isset($row['customer_name'])) {
                                        echo htmlspecialchars((string) $row['customer_name'], ENT_QUOTES, 'UTF-8');
                                    }
                                ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" for="sor_customer_address">Customer Address<span class="requireRed">*</span></label>
                            <textarea class="form-control" name="sor_customer_address" id="sor_customer_address" rows="2" <?= $act == '' ? 'disabled' : '' ?> <?= isset($updateAirbillValue) && $updateAirbillValue === 'yes' ? 'required' : '' ?>><?php
                                if (isset($sor_customer_address)) {
                                    echo htmlspecialchars($sor_customer_address);
                                } else if (isset($row['customer_address'])) {
                                    echo htmlspecialchars($row['customer_address']);
                                }
                            ?></textarea>
                            <?php if (isset($customer_address_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1"><?php echo $customer_address_err; ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_user_lbl" for="sor_user">Shopee Buyer
                                Username<span class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);
                            $buyerDisplayValue = '';
                            $buyerProfileUrl = '';
                            if (isset($row['buyer']))
                                $echoVal = $row['buyer'];

                            if (isset($echoVal)) {
                                $safeEchoVal = mysqli_real_escape_string($finance_connect, (string) $echoVal);
                                $user_rst = getData('*', "id = '$safeEchoVal'", 'LIMIT 1', SHOPEE_CUST_INFO, $finance_connect);
                                $user_row = $user_rst ? $user_rst->fetch_assoc() : [];
                                if (isset($user_row['buyer_username'])) {
                                    $buyerDisplayValue = $user_row['buyer_username'];
                                } else {
                                    $buyerDisplayValue = $echoVal;
                                }

                                if ((int) $echoVal > 0) {
                                    $buyerProfileUrl = rtrim((string) SITEURL, '/') . '/shopee/shopee_cust_info.php?id=' . (int) $echoVal;
                                }
                            }
                            ?>
                            <?php if ($act == '' && $buyerProfileUrl !== '') { ?>
                                <div class="form-control-plaintext pt-1">
                                    <a href="<?php echo htmlspecialchars($buyerProfileUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="fw-bold" style="text-decoration: none !important;">
                                        <?php echo htmlspecialchars($buyerDisplayValue, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </div>
                            <?php } else { ?>
                                <div class="d-flex align-items-center gap-2 flex-nowrap">
                                    <input class="form-control" type="text" name="sor_user" id="sor_user" <?php if ($act == '')
                                        echo 'disabled' ?>
                                            value="<?php echo !empty($echoVal) ? htmlspecialchars($buyerDisplayValue, ENT_QUOTES, 'UTF-8') : '' ?>">
                                </div>
                            <?php } ?>
                            <input type="hidden" name="sor_user_hidden" id="sor_user_hidden"
                                value="<?php echo (isset($row['buyer'])) ? htmlspecialchars((string) $row['buyer'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                            <?php if (isset($user_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $user_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_pay_label" for="sor_pay">Buyer Payment Method
                                <span class="requireRed">*</span></label>
                            <select class="form-select" id="sor_pay" name="sor_pay" <?php if ($act == '')
                                echo 'disabled' ?>>
                                    <option value="0" disabled selected>Select Payment Method</option>
                                    <?php
                            $query = "SELECT * FROM " . PAY_MTHD_SHOPEE . " ORDER BY `name` ASC ";
                            $acc_result = $finance_connect->query($query);
                            if ($acc_result->num_rows >= 1) {
                                $acc_result->data_seek(0);
                                while ($row4 = $acc_result->fetch_assoc()) {
                                    $selected = "";
                                    if (isset($dataExisted, $row['buyer_pay_meth']) && !isset($sor_pay)) {
                                        $selected = $row['buyer_pay_meth'] == $row4['id'] ? " selected" : "";
                                    } else if (isset($sor_pay)) {
                                        $selected = $sor_pay == $row4['id'] ? " selected" : "";
                                    }
                                    echo "<option value=\"" . $row4['id'] . "\"$selected>" . $row4['name'] . "</option>";
                                }
                            } else {
                                echo "<option value=\"0\">None</option>";
                            }

                            ?>
                            </select>
                            <?php if (isset($pay_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $pay_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php if ($act != ''){ ?>
                <div class="col-md-4 mb-3">
                    <button type="button" onclick="toggleNewBuyer()">Create New Customer ID</button>
                </div>
                <div id="myForm" novalidate>
                <div id="new_customer_section" style="display: none;">

                <div class="row">
                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_username">Shopee Buyer Username<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_username" name="scr_username" data-new-customer-required="1">
                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_pic">Sales Person In Charge<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_pic" name="scr_pic" data-new-customer-required="1">                        
                        <input class="form-control" type="hidden" id="scr_pic_hidden" name="scr_pic_hidden">

                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_country">Country<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_country" name="scr_country" data-new-customer-required="1">
                        <input class="form-control" type="hidden" id="scr_country_hidden" name="scr_country_hidden">
                    </div>
                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_brand">Brand<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_brand" name="scr_brand" data-new-customer-required="1"> <input class="form-control" type="hidden" id="scr_brand_hidden" name="scr_brand_hidden">
                    </div>

                    <div class="col-md-4 mb-3 autocomplete">
                        <label class="form-label form_lbl" for="scr_series">Series<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="scr_series" name="scr_series" data-new-customer-required="1"><input class="form-control" type="hidden" id="scr_series_hidden" name="scr_series_hidden">
                    </div>
                </div>
                <button type="button" id="new_customer_submit_btn">Submit</button>
                    </div>
                </div>
                <?php }?>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-6 mb-3 autocomplete">
                            <label class="form-label form_lbl" id="sor_pic_lbl" for="sor_pic">Person In
                                Charge<span class="requireRed">*</span></label>
                            <?php
                            unset($echoVal);
                            $picDisplayValue = '';

                            if (isset($row['pic']))
                                $echoVal = $row['pic'];

                            if (isset($echoVal)) {
                                $user_rst = getData('name', "id = '$echoVal'", '', USR_USER, $connect);
                                $user_row = $user_rst ? $user_rst->fetch_assoc() : [];
                                if (isset($user_row['name'])) {
                                    $picDisplayValue = $user_row['name'];
                                } else {
                                    $picDisplayValue = $echoVal;
                                }
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_pic" id="sor_pic" <?php if ($act == '')
                                echo 'disabled' ?> value="<?php echo !empty($echoVal) ? $picDisplayValue : '' ?>">
                            <input type="hidden" name="sor_pic_hidden" id="sor_pic_hidden"
                                value="<?php echo (isset($row['pic'])) ? $row['pic'] : ''; ?>">


                            <?php if (isset($pic_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $pic_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_price_lbl" for="sor_price">Price<span
                                    class="requireRed">*</span></label>
                            <input class="form-control" type="number" step="0.01" name="sor_price" id="sor_price" value="<?php
                            if (isset($dataExisted) && isset($row['price']) && !isset($sor_price)) {
                                echo $row['price'];
                            } else if (isset($sor_price)) {
                                echo $sor_price;
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($price_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $price_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_voucher_lbl" for="sor_voucher">Voucher </label>
                            <input class="form-control" type="number" step="0.01" name="sor_voucher" id="sor_voucher"
                                value="<?php
                                if (isset($dataExisted) && isset($row['voucher']) && !isset($sor_voucher)) {
                                    echo $row['voucher'];
                                } else if (isset($sor_voucher)) {
                                    echo $sor_voucher;
                                }
                                ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                            <?php if (isset($voucher_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $voucher_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label form_lbl" id="sor_shipping_lbl" for="sor_shipping">Actual Shipping
                            </label>
                            <input class="form-control" type="number" step="0.01" name="sor_shipping" id="sor_shipping"
                                value="<?php
                                if (isset($dataExisted) && isset($row['act_shipping_fee']) && !isset($sor_shipping)) {
                                    echo $row['act_shipping_fee'];
                                } else if (isset($sor_shipping)) {
                                    echo $sor_shipping;
                                } else {
                                    echo '0';
                                }
                                ?>" <?php if ($act == '')
                                    echo 'disabled' ?>>
                            <?php if (isset($shipping_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $shipping_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <hr />
                <div class="form-group mb-4">
                    <h3>
                        Commission Fees
                    </h3>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label form_lbl" id="sor_serv_lbl" for="sor_serv">Service Fee
                                (incl. GST)</label>
                            <input class="form-control" type="number" step="0.01" name="sor_serv" id="sor_serv" value="<?php
                            if (isset($dataExisted) && isset($row['service_fee']) && !isset($sor_serv)) {
                                echo $row['service_fee'];
                            } else if (isset($sor_serv)) {
                                echo $sor_serv;
                            } else {
                                echo '0';
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($service_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $service_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label form_lbl" id="sor_trans_lbl" for="sor_trans">Transaction Fee
                                (incl. GST)</label>
                            <input class="form-control" type="number" step="0.01" name="sor_trans" id="sor_trans" value="<?php
                            if (isset($dataExisted) && isset($row['trans_fee']) && !isset($sor_trans)) {
                                echo $row['trans_fee'];
                            } else if (isset($sor_trans)) {
                                echo $sor_trans;
                            } else {
                                echo '0';
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($trans_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $trans_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label form_lbl" id="sor_ams_lbl" for="sor_ams">AMS Commission
                                Fee</label>
                            <input class="form-control" type="number" step="0.01" name="sor_ams" id="sor_ams" value="<?php
                            if (isset($dataExisted) && isset($row['ams_fee']) && !isset($sor_ams)) {
                                echo $row['ams_fee'];
                            } else if (isset($sor_ams)) {
                                echo $sor_ams;
                            } else {
                                echo '0';
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                            <?php if (isset($ams_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $ams_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label form_lbl" id="sor_saver_program_fee_lbl" for="sor_saver_program_fee">Saver Programme Fee</label>
                            <input class="form-control" type="number" step="0.01" name="sor_saver_program_fee" id="sor_saver_program_fee" value="<?php
                            if (isset($dataExisted) && isset($row['saver_program_fee']) && !isset($sor_saver_program_fee)) {
                                echo $row['saver_program_fee'];
                            } else if (isset($sor_saver_program_fee)) {
                                echo $sor_saver_program_fee;
                            } else {
                                echo '0';
                            }
                            ?>" <?php if ($act == '')
                                echo 'disabled' ?>>
                        </div>

                    </div>
                </div>
                <hr />
                <div class="form-group mb-3">
                    <div class="row">
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_fees_lbl" for="sor_fees">Charges &
                                Fees</label>
                            <input class="form-control" type="number" step="0.01" name="sor_fees" id="sor_fees" value="<?php
                            if (isset($dataExisted) && isset($row['fees']) && !isset($sor_fees)) {
                                echo $row['fees'];
                            } else if (isset($sor_fees)) {
                                echo $sor_fees;
                            } else {
                                echo '0';
                            }
                            ?>" readonly>
                            <?php if (isset($fees_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $fees_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md mb-3">
                            <label class="form-label form_lbl" id="sor_final_lbl" for="sor_final">Final
                                Amount</label>
                            <input class="form-control" type="number" step="0.01" name="sor_final" id="sor_final" value="<?php
                            if (isset($dataExisted) && isset($row['final_amt']) && !isset($sor_final)) {
                                echo $row['final_amt'];
                            } else if (isset($sor_final)) {
                                echo $sor_final;
                            } else {
                                echo '0';
                            }
                            ?>">
                            <?php if (isset($final_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $final_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label form_lbl" id="sor_remark_lbl" for="sor_remark">Remark</label>
                    <textarea class="form-control" name="sor_remark" id="sor_remark" rows="3" <?php if ($act == '')
                        echo 'disabled' ?>><?php if (isset($dataExisted) && isset($row['remark']))
                        echo $row['remark'] ?></textarea>
                    <?php
                    $sorOrderDetailPdfPath = isset($row['order_detail_pdf']) ? trim((string) $row['order_detail_pdf']) : '';
                    $sorOrderDetailPdfUrl = $sorOrderDetailPdfPath !== '' ? shopeeOmsBuildAirbillAttachmentUrl($sorOrderDetailPdfPath) : '';
                    $sorOrderDetailPdfExt = $sorOrderDetailPdfUrl !== ''
                        ? strtolower(pathinfo((string) parse_url($sorOrderDetailPdfUrl, PHP_URL_PATH), PATHINFO_EXTENSION))
                        : '';
                    ?>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>
                    <div class="mt-3">
                        <label class="form-label form_lbl">Order Detail PDF</label>
                        <input class="form-control" type="text" readonly value="<?= htmlspecialchars($sorOrderDetailPdfPath, ENT_QUOTES, 'UTF-8') ?>">
                        <?php if ($sorOrderDetailPdfUrl !== '' && $sorOrderDetailPdfExt === 'pdf') { ?>
                            <div class="shopee-airbill-preview-media mt-3">
                                <iframe src="<?= htmlspecialchars($sorOrderDetailPdfUrl, ENT_QUOTES, 'UTF-8') ?>" title="Order Detail PDF Preview"></iframe>
                            </div>
                        <?php } else { ?>
                            <div class="text-muted mt-2">No Order Detail PDF uploaded.</div>
                        <?php } ?>
                    </div>
                    </div>
                <?php
                if(isset($row['order_status'])){
                if($row['order_status'] == 'SP'){
                    ?>
                <div class="form-group mb-4">
                    <h3>
                        Tracking Details
                    </h3>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_courier_lbl" for="sor_courier">Courier</label>
                            <?php
                           
                            if (isset($row['orderID']))
                            $echoVal = $row['orderID'];
                            $echoVal2 = '';
                            $courier_rst2 = getData('courier_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);

                            $courier_row2 = $courier_rst2 ? $courier_rst2->fetch_assoc() : [];
                            if (!empty($courier_row2['courier_id']))
                            $echoVal2 = $courier_row2['courier_id'];
                       
                            $courier_rst = getData('name', "id = '$echoVal2'", '', COURIER, $connect);
                            $courier_row = $courier_rst ? $courier_rst->fetch_assoc() : [];
                      
                            if (isset($courier_row['name'])) {
                                $courier_name = $courier_row['name'];
                            } else {
                                $courier_name = '';
                            }
                            ?>
                            <input class="form-control" type="text" name="sor_courier" id="sor_courier" value="<?php echo !empty($echoVal2) ? $courier_name : ''; ?>" disabled ?>

                            <?php if (isset($courier_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $courier_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label form_lbl" id="sor_track_lbl" for="sor_track">Tracking Number</label>
                            
                            <?php
                             $tracking_rst = getData('tracking_id', "order_id = '$echoVal'", '', OFFICIAL_PROCESS_ORDER, $connect);
                            $tracking_row = $tracking_rst ? $tracking_rst->fetch_assoc() : [];
                            if (isset($tracking_row['tracking_id'])) {
                                $tracking_id = $tracking_row['tracking_id'];
                            } else {
                                $tracking_id = '';
                            }
                             ?>
                             <input class="form-control" type="text"  name="sor_track" id="sor_track" value="<?php echo !empty($echoVal) ? $tracking_id : ''; ?>" disabled ?>
                            <?php if (isset($tracking_err)) { ?>
                                <div id="err_msg">
                                    <span class="mt-n1">
                                        <?php echo $tracking_err; ?>
                                    </span>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="col-md-4 mb-4 d-flex align-items-end">
                            <label>&nbsp;</label><br>
                            <?php
                   
                            $tracking_rst2 = getData('tracking_link', "id = '$echoVal2'", '', COURIER, $connect);
                            $track_row = $tracking_rst2 ? $tracking_rst2->fetch_assoc() : [];
                      
                            if (isset($track_row['tracking_link'])) {
                                $tracking_link = $track_row['tracking_link'];
                                
                            } else {
                                $tracking_link = '';
                            }
                            ?>
                            
                            <a href="<?php echo $tracking_link; ?>" id="trackOrderBtn" class="track-order-btn" data-tracking-id="<?php echo $tracking_id; ?>" target="_blank">Track Order</a>

                            
                        </div>
                    </div>
                </div>
                <?php }} ?>
                <?php if ($act !== 'I' && (!empty($transitionHistoryRows) || !empty($editHistoryRows))) { ?>
                <div class="form-group mb-4">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <h3>Status Transition History</h3>
                            <?php if (!empty($transitionHistoryRows)) { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Date Time</th>
                                                <th>Transition</th>
                                                <th>Action By</th>
                                                <th>Remark</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transitionHistoryRows as $historyRow) { ?>
                                                <?php
                                                $historyUserDisplayName = commonResolveUserDisplayName(
                                                    $connect,
                                                    isset($historyRow['user_id']) ? (string) $historyRow['user_id'] : 'SYSTEM'
                                                );
                                                if (trim((string) $historyUserDisplayName) === '') {
                                                    $historyUserDisplayName = 'SYSTEM';
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) (isset($historyRow['transition_at']) ? $historyRow['transition_at'] : '')) ?></td>
                                                    <td><?= htmlspecialchars(shopeeOmsGetStatusLabel(isset($historyRow['from_status']) ? $historyRow['from_status'] : '')) ?> -> <?= htmlspecialchars(shopeeOmsGetStatusLabel(isset($historyRow['to_status']) ? $historyRow['to_status'] : '')) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span><?= htmlspecialchars((string) $historyUserDisplayName) ?></span>
                                                            <?= shopeeOmsRenderUserGroupBadge($connect, isset($historyRow['user_group_id']) ? (int) $historyRow['user_group_id'] : 0) ?>
                                                        </div>
                                                    </td>
                                                    <td><?= nl2br(htmlspecialchars((string) (isset($historyRow['remark']) ? $historyRow['remark'] : ''))) ?></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-light border">No transition history found.</div>
                            <?php } ?>
                        </div>
                        <div class="col-12">
                            <h3>Modified Order History</h3>
                            <?php if (!empty($editHistoryRows)) { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Date Time</th>
                                                <th>Field</th>
                                                <th>Updated By</th>
                                                <th>Change</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($editHistoryRows as $historyRow) { ?>
                                                <?php
                                                $editHistoryUserDisplayName = commonResolveUserDisplayName(
                                                    $connect,
                                                    isset($historyRow['user_id']) ? (string) $historyRow['user_id'] : 'SYSTEM'
                                                );
                                                if (trim((string) $editHistoryUserDisplayName) === '') {
                                                    $editHistoryUserDisplayName = 'SYSTEM';
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars((string) (isset($historyRow['change_at']) ? $historyRow['change_at'] : '')) ?></td>
                                                    <td><?= htmlspecialchars((string) (isset($historyRow['field_label']) && trim((string) $historyRow['field_label']) !== '' ? $historyRow['field_label'] : $historyRow['field_name'])) ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <span><?= htmlspecialchars((string) $editHistoryUserDisplayName) ?></span>
                                                            <?= shopeeOmsRenderUserGroupBadge($connect, isset($historyRow['user_group_id']) ? (int) $historyRow['user_group_id'] : 0) ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="text-decoration: line-through; color: #9b1c1c;"><?= nl2br(htmlspecialchars((string) (isset($historyRow['old_value']) ? $historyRow['old_value'] : ''))) ?></div>
                                                        <div style="color: #198754; font-weight: 600;"><?= nl2br(htmlspecialchars((string) (isset($historyRow['new_value']) ? $historyRow['new_value'] : ''))) ?></div>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-light border">No modified order history found.</div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column mobile-sticky-form-actions-target shopee-order-action-row">
                        <?php
                        if (isset($row['order_status'])) {
                            $statusCode = shopeeOmsNormalizeStatusCode($row['order_status']);
                            $canMoveToPack = shopeeOmsHasTransitionPermission($connect, $statusCode, 'TP', USER_GROUP, $row, USER_ID);
                            $canConfirmReceive = shopeeOmsHasTransitionPermission($connect, $statusCode, 'PR', USER_GROUP, $row, USER_ID);
                            $canVerify = shopeeOmsHasTransitionPermission($connect, $statusCode, 'V', USER_GROUP, $row, USER_ID);
                            $canComplete = shopeeOmsHasTransitionPermission($connect, $statusCode, 'C', USER_GROUP, $row, USER_ID);
                            $canReturn = shopeeOmsHasTransitionPermission($connect, $statusCode, 'CR', USER_GROUP, $row, USER_ID);

                            if ($statusCode === 'P' && $canMoveToPack) {
                                echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="TP" formnovalidate>MOVE TO TO PACK</button>';
                            } else if ($statusCode === 'WR' && $canConfirmReceive) {
                                echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn p-2 sor-confirm-receive-trigger" type="button"
                                    data-order-code="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['order_no']) ? $sorFollowUpModalContext['order_no'] : ''), ENT_QUOTES, 'UTF-8') . '"
                                    data-customer-name="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['customer_name']) ? $sorFollowUpModalContext['customer_name'] : ''), ENT_QUOTES, 'UTF-8') . '"
                                    data-customer-username="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['customer_username']) ? $sorFollowUpModalContext['customer_username'] : ''), ENT_QUOTES, 'UTF-8') . '"
                                    data-package-name="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['package_name']) ? $sorFollowUpModalContext['package_name'] : ''), ENT_QUOTES, 'UTF-8') . '"
                                    data-received-date="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['received_date']) ? $sorFollowUpModalContext['received_date'] : customerFollowUpNowDate()), ENT_QUOTES, 'UTF-8') . '"
                                    data-purchase-count="' . (int) (isset($sorFollowUpModalContext['purchase_count_snapshot']) ? $sorFollowUpModalContext['purchase_count_snapshot'] : 0) . '"
                                    data-customer-type="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['customer_type']) ? $sorFollowUpModalContext['customer_type'] : 'new'), ENT_QUOTES, 'UTF-8') . '"
                                    data-contact-no="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['contact_no']) ? $sorFollowUpModalContext['contact_no'] : ''), ENT_QUOTES, 'UTF-8') . '"
                                    data-max-date="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['max_date']) ? $sorFollowUpModalContext['max_date'] : ''), ENT_QUOTES, 'UTF-8') . '"
                                    data-rule-label="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['rule_label']) ? $sorFollowUpModalContext['rule_label'] : ''), ENT_QUOTES, 'UTF-8') . '"
                                    data-block-message="' . htmlspecialchars((string) (isset($sorFollowUpModalContext['block_message']) ? $sorFollowUpModalContext['block_message'] : ''), ENT_QUOTES, 'UTF-8') . '">CONFIRM PARCEL RECEIVED</button>';
                            } else if ($statusCode === 'WAFC' && $canVerify) {
                                echo '<button type="button" class="btn btn-lg btn-rounded btn-success mx-2 mb-2 submitBtn p-2 sor-verify-order-trigger">VERIFIED</button>';
                            } else if ($statusCode === 'V' && $canComplete) {
                                echo '<button class="btn btn-lg btn-rounded btn-success mx-2 mb-2 submitBtn p-2" name="updateStatusBtn" value="C" formnovalidate>FINALIZE COMPLETE</button>';
                            }

                            if ($statusCode === 'R' && $canReturn) {
                                echo '<button type="button" class="btn btn-lg btn-rounded btn-warning mx-2 mb-2 p-2" onclick="submitReturnAction(\'restock\')">RETURN RESTOCK</button>';
                                echo '<button type="button" class="btn btn-lg btn-rounded btn-danger mx-2 mb-2 p-2" onclick="submitReturnAction(\'damaged\')">RETURN DAMAGED</button>';
                            }
                        }
                        
                    switch ($act) {
                        case 'I':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="addRecord">Add Record</button>';
                            break;
                        case 'E':
                            echo '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" name="actionBtn" id="actionBtn" value="updRecord">Edit Record</button>';
                            break;
                    }
                    ?>
                    <div class="modal fade" id="sorFollowUpModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="sorFollowUpModalTitle">Customer Follow-Up</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body text-start">
                                    <input type="hidden" name="shopee_order_follow_up_csrf" value="<?= htmlspecialchars((string) $_SESSION['shopee_order_follow_up_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="confirmReceiveFollowUpBtn" id="sor_confirm_receive_follow_up_flag" value="">

                                    <div style="background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                                        <div class="d-flex justify-content-between gap-3 mb-1"><span class="text-muted">Order ID</span><span id="sor_follow_up_order_code_text"></span></div>
                                        <div class="d-flex justify-content-between gap-3 mb-1"><span class="text-muted">Customer</span><span id="sor_follow_up_customer_text"></span></div>
                                        <div class="d-flex justify-content-between gap-3 mb-1"><span class="text-muted">Package</span><span id="sor_follow_up_package_text"></span></div>
                                        <div class="d-flex justify-content-between gap-3 mb-1"><span class="text-muted">Received Date</span><span id="sor_follow_up_received_date_text"></span></div>
                                        <div class="d-flex justify-content-between gap-3"><span class="text-muted">Customer Type</span><span id="sor_follow_up_customer_type_text"></span></div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="sor_follow_up_attachment">Screenshot / Attachment</label>
                                        <input type="file" class="form-control" id="sor_follow_up_attachment" name="follow_up_attachment">
                                        <div id="sor_follow_up_attachment_preview_wrap" class="shopee-airbill-preview-media" style="display:none;">
                                            <img id="sor_follow_up_attachment_preview_img" alt="Follow-Up Attachment Preview">
                                        </div>
                                        <div class="text-muted small mt-2 d-none" id="sor_follow_up_attachment_preview_note">Preview is available for image files only.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="sor_follow_up_message_shortcut_id">Message Shortcut</label>
                                        <select class="form-select" id="sor_follow_up_message_shortcut_id" name="follow_up_message_shortcut_id">
                                            <option value="">Select Message Shortcut</option>
                                            <?php foreach ($sorFollowUpShortcutOptions as $shortcutRow) {
                                                $shortcutId = isset($shortcutRow['id']) ? (int) $shortcutRow['id'] : 0;
                                                if ($shortcutId <= 0) {
                                                    continue;
                                                }
                                                $shortcutLabel = trim((string) (isset($shortcutRow['shortcuts_tag']) ? $shortcutRow['shortcuts_tag'] : ''));
                                                ?>
                                                <option value="<?= $shortcutId ?>"><?= htmlspecialchars($shortcutLabel !== '' ? $shortcutLabel : ('Shortcut #' . $shortcutId), ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="sor_follow_up_next_date">Next Follow-Up Date</label>
                                        <input type="date" class="form-control" id="sor_follow_up_next_date" name="follow_up_next_follow_up_date">
                                        <small class="text-muted" id="sor_follow_up_rule_hint"></small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">WhatsApp / Contact Number</label>
                                        <div id="sor_follow_up_contact_display_wrap" class="d-none align-items-center justify-content-between gap-2" style="padding:0.625rem 0.75rem;border:1px solid #dee2e6;border-radius:0.375rem;background:#f8f9fa;">
                                            <div id="sor_follow_up_contact_display_text"></div>
                                            <button type="button" class="btn btn-link p-0 text-decoration-none" id="sor_follow_up_contact_edit_btn" title="Edit Contact Number">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                        </div>
                                        <div id="sor_follow_up_contact_input_wrap">
                                            <input type="text" class="form-control" id="sor_follow_up_contact_no" name="follow_up_contact_no" placeholder="Enter Contact Number">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="sor_follow_up_submit_btn" name="updateStatusBtn" value="PR" style="text-transform: none !important;">Submit & Confirm Received</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="sorVerifyOrderModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Verify Order</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body text-start">
                                    <input type="hidden" id="sor_verify_order_csrf" value="<?= htmlspecialchars((string) $_SESSION['shopee_order_verify_pdf_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" id="sor_verify_pdf_path" value="<?= isset($row['order_detail_pdf']) ? htmlspecialchars((string) $row['order_detail_pdf'], ENT_QUOTES, 'UTF-8') : '' ?>">

                                    <div id="sorVerifyOrderChoiceView">
                                        <div class="border rounded-3 p-3 bg-light mb-4">Choose verification method</div>
                                        <div class="d-grid gap-3">
                                            <button type="button" class="shopee-verify-method-card shopee-verify-method-card-success" id="sor_direct_verified_choice_btn">
                                                <div class="fw-bold fs-5 text-success mb-1">VERIFIED</div>
                                                <div class="text-muted">Mark this order as verified without uploading a PDF.</div>
                                            </button>
                                            <button type="button" class="shopee-verify-method-card shopee-verify-method-card-primary" id="sor_upload_pdf_choice_btn">
                                                <div class="fw-bold fs-5 text-primary mb-1">UPLOAD PDF TO VERIFIED</div>
                                                <div class="text-muted">Upload a Shopee Order Detail PDF and verify by comparing details.</div>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row g-4 d-none" id="sorVerifyOrderPdfView">
                                        <div class="col-lg-4">
                                            <div class="border rounded-3 p-3 bg-light h-100">
                                                <h6 class="mb-3">Upload PDF</h6>
                                                <div class="mb-3">
                                                    <label class="form-label" for="sor_order_detail_pdf">Shopee Order Detail PDF</label>
                                                    <input type="file" class="form-control" id="sor_order_detail_pdf" accept=".pdf,application/pdf">
                                                    <small class="text-muted d-block mt-2" id="sor_order_detail_pdf_status">Only PDF file is allowed.</small>
                                                </div>
                                                <div class="d-grid gap-2">
                                                    <button type="button" class="btn btn-outline-primary" id="sor_compare_pdf_btn">Upload PDF & Compare</button>
                                                    <button type="button" class="btn btn-outline-secondary d-none" id="sor_reload_pdf_btn">Reload PDF to Verified</button>
                                                </div>
                                                <div class="mt-3">
                                                    <label class="form-label">Saved PDF Path</label>
                                                    <input type="text" class="form-control" id="sor_order_detail_pdf_path_display" readonly value="<?= isset($row['order_detail_pdf']) ? htmlspecialchars((string) $row['order_detail_pdf'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="border rounded-3 p-3 h-100">
                                                <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                                    <h6 class="mb-0">Comparison Result</h6>
                                                    <span class="badge bg-light text-dark border" id="sor_verify_compare_badge">No PDF loaded</span>
                                                </div>
                                                <div id="sor_verify_compare_message" class="alert alert-light border">Upload the Shopee Order Detail PDF first, then review only the different fields before verifying.</div>
                                                <div class="table-responsive d-none" id="sor_verify_compare_table_wrap">
                                                    <table class="table table-bordered align-middle">
                                                        <thead>
                                                            <tr>
                                                                <th>Field Name</th>
                                                                <th>Current Value</th>
                                                                <th>PDF Value</th>
                                                                <th>Editable Final Value</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="sor_verify_compare_rows"></tbody>
                                                    </table>
                                                </div>
                                                <div id="sor_verify_pdf_preview_empty" class="alert alert-light border mb-0">No Order Detail PDF uploaded.</div>
                                                <div id="sor_verify_pdf_preview_wrap" class="shopee-airbill-preview-media d-none">
                                                    <iframe id="sor_verify_pdf_preview_iframe" src="" title="Order Detail PDF Preview"></iframe>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary d-none" id="sor_verify_back_btn">Back</button>
                                    <button type="button" class="btn btn-success d-none" id="sor_update_to_verified_btn" disabled>Save Edited Info and Update to Verified</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="return_type" id="return_type" value="">
                    <input type="hidden" name="return_remark" id="return_remark" value="">
                    <button type="button" class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" name="backBtn" id="backBtn"
                        onclick="location.href = <?= htmlspecialchars(json_encode($back_redirect_page), ENT_QUOTES, 'UTF-8') ?>;">Back</button>
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
        if ($sorLocalTelegramFailureMessage !== '') {
            echo '<script>alert(' . json_encode($sorLocalTelegramFailureMessage) . ');</script>';
        }
        echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirectPage . '","' . $act . '");</script>';
    }
    if ($sorPopupErrorMessage !== '') {
        echo '<script>document.addEventListener("DOMContentLoaded", function () { confirmationDialog("", ' . json_encode($sorPopupErrorMessage) . ', ' . json_encode((string) $pageTitle) . ', "", "", "ErrMO"); });</script>';
    }
    ?>
    <script>
        function ensureShopeeOrderReqActionPopup() {
            var popupElement = document.getElementById('shopeeOrderReqActionPopup');
            if (popupElement) {
                return popupElement;
            }

            var popupWrap = document.createElement('div');
            popupWrap.innerHTML =
                '<div class="modal fade" id="shopeeOrderReqActionPopup" tabindex="-1" aria-hidden="true">' +
                    '<div class="modal-dialog modal-dialog-centered" style="font-family:\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">' +
                        '<div class="modal-content">' +
                            '<div class="modal-body fs-6 mt-3">' +
                                '<p id="shopeeOrderReqActionPopupTitle" style="text-align:center; font-weight:bold; font-size:25px; margin-bottom:0;"></p>' +
                            '</div>' +
                            '<div class="modal-footer d-flex justify-content-center mt-n3" style="border-top:0px;">' +
                                '<button type="button" class="btn" data-bs-dismiss="modal" style="border:1px solid #FF9B44; background-color:#FFFFFF; color:#FF9B44; box-shadow:0 0 !important; border-radius:24px; text-transform:none;">Continue</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(popupWrap.firstChild);

            return document.getElementById('shopeeOrderReqActionPopup');
        }

        function showShopeeOrderReqPopupMessage(message, onClose) {
            var popupMessage = String(message || '').trim();
            if (!popupMessage) {
                return;
            }

            if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                window.alert(popupMessage);
                if (typeof onClose === 'function') {
                    onClose();
                }
                return;
            }

            var popupElement = ensureShopeeOrderReqActionPopup();
            if (!popupElement) {
                window.alert(popupMessage);
                if (typeof onClose === 'function') {
                    onClose();
                }
                return;
            }

            var titleNode = document.getElementById('shopeeOrderReqActionPopupTitle');
            if (titleNode) {
                titleNode.textContent = popupMessage;
            }

            var popupModal = bootstrap.Modal.getOrCreateInstance(popupElement, {
                keyboard: false,
                backdrop: 'static'
            });

            if (typeof onClose === 'function') {
                var handleHidden = function () {
                    popupElement.removeEventListener('hidden.bs.modal', handleHidden);
                    onClose();
                };
                popupElement.addEventListener('hidden.bs.modal', handleHidden);
            }

            popupModal.show();
        }

        function bindShopeeOrderReqStatusButtons() {
            var form = document.getElementById('FORForm');
            if (!form) {
                return;
            }

            var statusButtons = form.querySelectorAll('button[name="updateStatusBtn"]');
            if (!statusButtons.length) {
                return;
            }

            var resetStatusButtons = function () {
                statusButtons.forEach(function (statusButton) {
                    delete statusButton.dataset.submitting;
                    statusButton.disabled = false;
                });
            };

            form.addEventListener('invalid', function () {
                resetStatusButtons();
            }, true);

            window.addEventListener('pageshow', function () {
                resetStatusButtons();
            });

            statusButtons.forEach(function (button) {
                button.addEventListener('click', function (event) {
                    if (button.dataset.submitting === '1') {
                        event.preventDefault();
                        return;
                    }
                    button.dataset.submitting = '1';
                    statusButtons.forEach(function (statusButton) {
                        if (statusButton !== button) {
                            statusButton.disabled = true;
                        }
                    });
                });
            });
        }

        function bindShopeeOrderReqVerifyModal() {
            var triggerButton = document.querySelector('.sor-verify-order-trigger');
            var modalElement = document.getElementById('sorVerifyOrderModal');
            if (!triggerButton || !modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return;
            }

            var modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
            var csrfField = document.getElementById('sor_verify_order_csrf');
            var choiceView = document.getElementById('sorVerifyOrderChoiceView');
            var pdfView = document.getElementById('sorVerifyOrderPdfView');
            var fileInput = document.getElementById('sor_order_detail_pdf');
            var statusNode = document.getElementById('sor_order_detail_pdf_status');
            var directChoiceBtn = document.getElementById('sor_direct_verified_choice_btn');
            var uploadChoiceBtn = document.getElementById('sor_upload_pdf_choice_btn');
            var compareBtn = document.getElementById('sor_compare_pdf_btn');
            var reloadBtn = document.getElementById('sor_reload_pdf_btn');
            var backBtn = document.getElementById('sor_verify_back_btn');
            var updateBtn = document.getElementById('sor_update_to_verified_btn');
            var compareBadge = document.getElementById('sor_verify_compare_badge');
            var compareMessage = document.getElementById('sor_verify_compare_message');
            var compareTableWrap = document.getElementById('sor_verify_compare_table_wrap');
            var compareRows = document.getElementById('sor_verify_compare_rows');
            var pdfPathField = document.getElementById('sor_verify_pdf_path');
            var pdfPathDisplay = document.getElementById('sor_order_detail_pdf_path_display');
            var previewWrap = document.getElementById('sor_verify_pdf_preview_wrap');
            var previewIframe = document.getElementById('sor_verify_pdf_preview_iframe');
            var previewEmpty = document.getElementById('sor_verify_pdf_preview_empty');

            var latestComparisonRows = [];
            var localPreviewUrl = '';
            var canFinalizeVerify = false;

            function setBusyState(isBusy) {
                [directChoiceBtn, uploadChoiceBtn, compareBtn, reloadBtn, backBtn].forEach(function (button) {
                    if (!button) {
                        return;
                    }
                    button.disabled = !!isBusy;
                });
                if (updateBtn) {
                    updateBtn.disabled = !!isBusy || !canFinalizeVerify;
                }
            }

            function resetLocalPreviewUrl() {
                if (localPreviewUrl) {
                    try {
                        URL.revokeObjectURL(localPreviewUrl);
                    } catch (error) {
                    }
                    localPreviewUrl = '';
                }
            }

            function showChoiceView() {
                if (choiceView) {
                    choiceView.classList.remove('d-none');
                }
                if (pdfView) {
                    pdfView.classList.add('d-none');
                }
                if (backBtn) {
                    backBtn.classList.add('d-none');
                }
                if (updateBtn) {
                    updateBtn.classList.add('d-none');
                }
            }

            function showPdfView() {
                if (choiceView) {
                    choiceView.classList.add('d-none');
                }
                if (pdfView) {
                    pdfView.classList.remove('d-none');
                }
                if (backBtn) {
                    backBtn.classList.remove('d-none');
                }
                if (updateBtn) {
                    updateBtn.classList.remove('d-none');
                }
            }

            function resetComparisonState() {
                latestComparisonRows = [];
                canFinalizeVerify = false;
                resetLocalPreviewUrl();
                if (compareRows) {
                    compareRows.innerHTML = '';
                }
                if (compareTableWrap) {
                    compareTableWrap.classList.add('d-none');
                }
                if (compareMessage) {
                    compareMessage.className = 'alert alert-light border';
                    compareMessage.textContent = 'Upload the Shopee Order Detail PDF first, then review only the different fields before verifying.';
                }
                if (compareBadge) {
                    compareBadge.textContent = 'No PDF loaded';
                }
                if (updateBtn) {
                    updateBtn.disabled = true;
                }
                if (reloadBtn) {
                    reloadBtn.classList.add('d-none');
                }
            }

            function updatePdfPreview(pdfUrl) {
                if (!previewWrap || !previewIframe || !previewEmpty) {
                    return;
                }

                if (pdfUrl) {
                    previewIframe.src = pdfUrl;
                    previewWrap.classList.remove('d-none');
                    previewEmpty.classList.add('d-none');
                } else {
                    previewIframe.removeAttribute('src');
                    previewWrap.classList.add('d-none');
                    previewEmpty.classList.remove('d-none');
                }
            }

            function renderFinalInput(row) {
                var wrapper = document.createElement('div');
                var inputType = row.input_type || 'text';
                if (inputType === 'select') {
                    var select = document.createElement('select');
                    select.className = 'form-select sor-verify-final-value';
                    select.setAttribute('data-field-name', row.field_name);
                    var options = row.options || {};
                    Object.keys(options).forEach(function (optionKey) {
                        var option = document.createElement('option');
                        option.value = optionKey;
                        option.textContent = options[optionKey];
                        if (String(row.final_value) === String(optionKey)) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                    wrapper.appendChild(select);
                    return wrapper;
                }

                var input = document.createElement('input');
                input.type = (inputType === 'number' || inputType === 'date' || inputType === 'time') ? inputType : 'text';
                input.step = inputType === 'number' ? '0.01' : '';
                input.className = 'form-control sor-verify-final-value';
                input.setAttribute('data-field-name', row.field_name);
                input.value = row.final_value || '';
                wrapper.appendChild(input);
                return wrapper;
            }

            function renderComparisonRows(rows) {
                latestComparisonRows = Array.isArray(rows) ? rows : [];
                if (!compareRows || !compareTableWrap || !compareMessage || !compareBadge) {
                    return;
                }

                compareRows.innerHTML = '';
                if (!latestComparisonRows.length) {
                    compareTableWrap.classList.add('d-none');
                    compareMessage.className = 'alert alert-success border';
                    compareMessage.textContent = 'No different fields were found. You can continue with Save Edited Info and Update to Verified.';
                    compareBadge.textContent = 'No differences';
                    canFinalizeVerify = true;
                    if (updateBtn) {
                        updateBtn.disabled = false;
                    }
                    return;
                }

                latestComparisonRows.forEach(function (row) {
                    var tr = document.createElement('tr');

                    var fieldTd = document.createElement('td');
                    fieldTd.textContent = row.field_label || row.field_name || '';
                    tr.appendChild(fieldTd);

                    var currentTd = document.createElement('td');
                    currentTd.textContent = row.current_value || '';
                    tr.appendChild(currentTd);

                    var pdfTd = document.createElement('td');
                    pdfTd.textContent = row.pdf_value || '';
                    tr.appendChild(pdfTd);

                    var finalTd = document.createElement('td');
                    finalTd.appendChild(renderFinalInput(row));
                    tr.appendChild(finalTd);

                    compareRows.appendChild(tr);
                });

                compareTableWrap.classList.remove('d-none');
                compareMessage.className = 'alert alert-warning border';
                compareMessage.textContent = 'Only the different fields are shown below. Please confirm the final values before verifying.';
                compareBadge.textContent = latestComparisonRows.length + ' difference' + (latestComparisonRows.length > 1 ? 's' : '');
                canFinalizeVerify = true;
                if (updateBtn) {
                    updateBtn.disabled = false;
                }
            }

            function collectFinalValues() {
                var values = {};
                modalElement.querySelectorAll('.sor-verify-final-value').forEach(function (field) {
                    values[field.getAttribute('data-field-name') || ''] = field.value;
                });
                return values;
            }

            function sendVerifyRequest(actionName, formData) {
                return fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json();
                });
            }

            function extractPdfClientText(file) {
                if (!file || typeof pdfjsLib === 'undefined') {
                    return Promise.resolve('');
                }

                pdfjsLib.GlobalWorkerOptions.workerSrc = '../finance/header/js/pdf.worker.min.js';
                return file.arrayBuffer().then(function (buffer) {
                    return pdfjsLib.getDocument({ data: buffer }).promise;
                }).then(function (pdfDoc) {
                    var pagePromises = [];
                    for (var pageNumber = 1; pageNumber <= pdfDoc.numPages; pageNumber++) {
                        pagePromises.push(
                            pdfDoc.getPage(pageNumber).then(function (page) {
                                return page.getTextContent().then(function (textContent) {
                                    return textContent.items.map(function (item) {
                                        return String(item.str || '').trim();
                                    }).filter(Boolean).join(' ');
                                });
                            })
                        );
                    }
                    return Promise.all(pagePromises).then(function (pages) {
                        return pages.join("\n");
                    });
                }).catch(function () {
                    return '';
                });
            }

            function handleVerifySuccess(result) {
                modalInstance.hide();
                showShopeeOrderReqActionPopup(result && result.message ? result.message : 'Order verified successfully.', function () {
                    if (result && result.redirect_url) {
                        window.location.replace(result.redirect_url);
                    }
                });
            }

            triggerButton.addEventListener('click', function () {
                resetComparisonState();
                setStatus('Only PDF file is allowed.', false);
                if (fileInput) {
                    fileInput.value = '';
                }
                if (pdfPathField && pdfPathDisplay) {
                    pdfPathDisplay.value = pdfPathField.value || '';
                    updatePdfPreview('');
                } else {
                    updatePdfPreview('');
                }
                showChoiceView();
                modalInstance.show();
            });

            if (uploadChoiceBtn) {
                uploadChoiceBtn.addEventListener('click', function () {
                    showPdfView();
                });
            }

            if (backBtn) {
                backBtn.addEventListener('click', function () {
                    showChoiceView();
                });
            }

            compareBtn.addEventListener('click', function () {
                var selectedFile = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (!selectedFile) {
                    setStatus('Please choose the Shopee Order Detail PDF first.', true);
                    return;
                }
                if (!/\.pdf$/i.test(String(selectedFile.name || ''))) {
                    setStatus('Only PDF file is allowed.', true);
                    return;
                }

                setBusyState(true);
                setStatus('Extracting PDF text and comparing...', false);
                extractPdfClientText(selectedFile).then(function (clientPdfText) {
                    var formData = new FormData();
                    formData.append('sor_verify_order_action', 'compare_pdf');
                    formData.append('shopee_order_verify_pdf_csrf', csrfField ? csrfField.value : '');
                    formData.append('sor_order_detail_pdf_client_text', clientPdfText || '');
                    formData.append('sor_order_detail_pdf', selectedFile);

                    return sendVerifyRequest('compare_pdf', formData);
                }).then(function (result) {
                    setBusyState(false);
                    if (!result || !result.success) {
                        setStatus(result && result.message ? result.message : 'Failed to compare the uploaded PDF.', true);
                        return;
                    }

                    setStatus(result.message || 'PDF compared successfully.', false);
                    resetLocalPreviewUrl();
                    localPreviewUrl = URL.createObjectURL(selectedFile);
                    updatePdfPreview(localPreviewUrl);
                    renderComparisonRows(result.comparison_rows || []);
                    if (reloadBtn) {
                        reloadBtn.classList.remove('d-none');
                    }
                }).catch(function () {
                    setBusyState(false);
                    setStatus('Failed to compare the uploaded PDF.', true);
                });
            });

            if (reloadBtn) {
                reloadBtn.addEventListener('click', function () {
                    compareBtn.click();
                });
            }

            directChoiceBtn.addEventListener('click', function () {
                if (!window.confirm('Are you sure you want to verify this order?')) {
                    return;
                }
                setBusyState(true);
                var formData = new FormData();
                formData.append('sor_verify_order_action', 'direct_verified');
                formData.append('shopee_order_verify_pdf_csrf', csrfField ? csrfField.value : '');

                sendVerifyRequest('direct_verified', formData).then(function (result) {
                    setBusyState(false);
                    if (!result || !result.success) {
                        setStatus(result && result.message ? result.message : 'Failed to verify the order.', true);
                        return;
                    }
                    handleVerifySuccess(result);
                }).catch(function () {
                    setBusyState(false);
                    setStatus('Failed to verify the order.', true);
                });
            });

            updateBtn.addEventListener('click', function () {
                if (!(fileInput && fileInput.files && fileInput.files[0]) && (!pdfPathField || !pdfPathField.value)) {
                    setStatus('Please upload the Order Detail PDF first.', true);
                    return;
                }

                setBusyState(true);
                var formData = new FormData();
                formData.append('sor_verify_order_action', 'finalize_pdf_verified');
                formData.append('shopee_order_verify_pdf_csrf', csrfField ? csrfField.value : '');
                formData.append('sor_verify_pdf_path', pdfPathField.value || '');
                formData.append('sor_verify_final_values', JSON.stringify(collectFinalValues()));
                if (fileInput && fileInput.files && fileInput.files[0]) {
                    formData.append('sor_order_detail_pdf', fileInput.files[0]);
                }

                sendVerifyRequest('finalize_pdf_verified', formData).then(function (result) {
                    setBusyState(false);
                    if (!result || !result.success) {
                        setStatus(result && result.message ? result.message : 'Failed to update and verify the order.', true);
                        return;
                    }
                    handleVerifySuccess(result);
                }).catch(function () {
                    setBusyState(false);
                    setStatus('Failed to update and verify the order.', true);
                });
            });

            modalElement.addEventListener('hidden.bs.modal', function () {
                setBusyState(false);
                resetLocalPreviewUrl();
                updatePdfPreview('');
                showChoiceView();
            });
        }

        function bindShopeeOrderReqFollowUpModal() {
            var form = document.getElementById('FORForm');
            var triggerButton = document.querySelector('.sor-confirm-receive-trigger');
            var modalElement = document.getElementById('sorFollowUpModal');
            var followUpFlag = document.getElementById('sor_confirm_receive_follow_up_flag');
            if (!form || !triggerButton || !modalElement || !followUpFlag) {
                return;
            }

            var modalInstance = typeof bootstrap !== 'undefined' && bootstrap.Modal
                ? bootstrap.Modal.getOrCreateInstance(modalElement)
                : null;
            var attachmentInput = document.getElementById('sor_follow_up_attachment');
            var attachmentPreviewWrap = document.getElementById('sor_follow_up_attachment_preview_wrap');
            var attachmentPreviewImage = document.getElementById('sor_follow_up_attachment_preview_img');
            var attachmentPreviewNote = document.getElementById('sor_follow_up_attachment_preview_note');
            var shortcutInput = document.getElementById('sor_follow_up_message_shortcut_id');
            var nextDateInput = document.getElementById('sor_follow_up_next_date');
            var ruleHint = document.getElementById('sor_follow_up_rule_hint');
            var contactDisplayWrap = document.getElementById('sor_follow_up_contact_display_wrap');
            var contactDisplayText = document.getElementById('sor_follow_up_contact_display_text');
            var contactInputWrap = document.getElementById('sor_follow_up_contact_input_wrap');
            var contactInput = document.getElementById('sor_follow_up_contact_no');
            var contactEditBtn = document.getElementById('sor_follow_up_contact_edit_btn');
            var submitBtn = document.getElementById('sor_follow_up_submit_btn');
            var currentAttachmentPreviewUrl = null;

            var clearAttachmentPreview = function () {
                if (currentAttachmentPreviewUrl) {
                    URL.revokeObjectURL(currentAttachmentPreviewUrl);
                    currentAttachmentPreviewUrl = null;
                }
                if (attachmentPreviewImage) {
                    attachmentPreviewImage.removeAttribute('src');
                }
                if (attachmentPreviewWrap) {
                    attachmentPreviewWrap.style.display = 'none';
                }
                if (attachmentPreviewNote) {
                    attachmentPreviewNote.classList.add('d-none');
                }
            };

            if (attachmentInput && attachmentPreviewWrap && attachmentPreviewImage && attachmentPreviewNote) {
                attachmentInput.addEventListener('change', function () {
                    var file = attachmentInput.files && attachmentInput.files[0] ? attachmentInput.files[0] : null;
                    if (!file) {
                        clearAttachmentPreview();
                        return;
                    }

                    if (currentAttachmentPreviewUrl) {
                        URL.revokeObjectURL(currentAttachmentPreviewUrl);
                        currentAttachmentPreviewUrl = null;
                    }

                    if (file.type.indexOf('image/') === 0) {
                        currentAttachmentPreviewUrl = URL.createObjectURL(file);
                        attachmentPreviewImage.src = currentAttachmentPreviewUrl;
                        attachmentPreviewWrap.style.display = 'block';
                        attachmentPreviewNote.classList.add('d-none');
                        return;
                    }

                    attachmentPreviewImage.removeAttribute('src');
                    attachmentPreviewWrap.style.display = 'none';
                    attachmentPreviewNote.classList.remove('d-none');
                });

                window.addEventListener('beforeunload', clearAttachmentPreview);
            }

            triggerButton.addEventListener('click', function () {
                var blockMessage = triggerButton.getAttribute('data-block-message') || '';
                if (blockMessage) {
                    window.alert(blockMessage);
                    return;
                }

                followUpFlag.value = '';
                document.getElementById('sorFollowUpModalTitle').textContent = 'Customer Follow-Up - ' + (triggerButton.getAttribute('data-order-code') || '');
                document.getElementById('sor_follow_up_order_code_text').textContent = triggerButton.getAttribute('data-order-code') || '-';
                document.getElementById('sor_follow_up_customer_text').textContent = triggerButton.getAttribute('data-customer-username') || triggerButton.getAttribute('data-customer-name') || '-';
                document.getElementById('sor_follow_up_package_text').textContent = triggerButton.getAttribute('data-package-name') || '-';
                document.getElementById('sor_follow_up_received_date_text').textContent = triggerButton.getAttribute('data-received-date') || '';
                document.getElementById('sor_follow_up_customer_type_text').textContent =
                    (triggerButton.getAttribute('data-customer-type') || 'new') === 'return'
                        ? 'Return Customer (' + (triggerButton.getAttribute('data-purchase-count') || '0') + ' previous purchase)'
                        : 'New Customer';

                if (attachmentInput) {
                    attachmentInput.value = '';
                    attachmentInput.required = true;
                }
                clearAttachmentPreview();
                if (shortcutInput) {
                    shortcutInput.value = '';
                    shortcutInput.required = true;
                }
                if (nextDateInput) {
                    nextDateInput.value = '';
                    nextDateInput.max = triggerButton.getAttribute('data-max-date') || '';
                    nextDateInput.required = true;
                }
                if (ruleHint) {
                    ruleHint.textContent = triggerButton.getAttribute('data-rule-label') || '';
                }

                var contactNo = triggerButton.getAttribute('data-contact-no') || '';
                if (contactInput) {
                    contactInput.value = contactNo;
                }
                if (contactNo) {
                    contactDisplayText.textContent = contactNo;
                    contactDisplayWrap.classList.remove('d-none');
                    contactDisplayWrap.classList.add('d-flex');
                    contactInputWrap.classList.add('d-none');
                } else {
                    contactDisplayWrap.classList.add('d-none');
                    contactDisplayWrap.classList.remove('d-flex');
                    contactInputWrap.classList.remove('d-none');
                }

                if (modalInstance) {
                    modalInstance.show();
                }
            });

            if (contactEditBtn) {
                contactEditBtn.addEventListener('click', function () {
                    contactDisplayWrap.classList.add('d-none');
                    contactDisplayWrap.classList.remove('d-flex');
                    contactInputWrap.classList.remove('d-none');
                    if (contactInput) {
                        contactInput.focus();
                    }
                });
            }

            if (submitBtn) {
                submitBtn.addEventListener('click', function () {
                    followUpFlag.value = '1';
                });
            }

            modalElement.addEventListener('hidden.bs.modal', function () {
                followUpFlag.value = '';
                clearAttachmentPreview();
            });
        }

        function toggleAirbillFields() {
            var updateAirbill = document.getElementById('sor_update_airbill');
            var updateAirbillToggle = document.getElementById('sor_update_airbill_toggle');
            var airbillNo = document.getElementById('sor_airbill');
            var airbillAttachment = document.getElementById('sor_airbill_attachment');
            var customerAddress = document.getElementById('sor_customer_address');
            var existingAttachment = document.getElementById('sor_airbill_attachment_value');
            if (!updateAirbill || !updateAirbillToggle || !airbillNo || !airbillAttachment || !customerAddress) {
                return;
            }

            updateAirbill.value = updateAirbillToggle.checked ? 'yes' : 'no';
            var enabled = updateAirbillToggle.checked;
            var readOnlyMode = "<?= $act ?>" === '';
            airbillNo.disabled = readOnlyMode || !enabled;
            airbillAttachment.disabled = readOnlyMode || !enabled;
            customerAddress.disabled = readOnlyMode || !enabled;
            airbillNo.required = enabled;
            customerAddress.required = enabled;
            airbillAttachment.required = enabled && (!existingAttachment || existingAttachment.value.trim() === '');
        }

        function submitReturnAction(returnType) {
            var form = document.getElementById('FORForm');
            var returnTypeField = document.getElementById('return_type');
            var returnRemarkField = document.getElementById('return_remark');
            if (!form || !returnTypeField || !returnRemarkField) {
                return;
            }

            var remark = window.prompt('Return remark (' + returnType + '):', '');
            if (remark === null) {
                return;
            }

            returnTypeField.value = returnType;
            returnRemarkField.value = remark;
            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'returnActionBtn';
            actionInput.value = '1';
            form.appendChild(actionInput);
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function () {
            toggleAirbillFields();
            bindShopeeOrderReqFollowUpModal();
            bindShopeeOrderReqStatusButtons();
            bindShopeeOrderReqVerifyModal();

            var airbillFileInput = document.getElementById('sor_airbill_attachment');
            var airbillPreviewWrap = document.getElementById('sor_airbill_attachment_preview_wrap');
            var currentAirbillPreviewUrl = null;
            var airbillPreviewStorageKey = 'shopee_order_req_airbill_preview_<?= (int) $dataId > 0 ? (int) $dataId : 'new' ?>';
            var shouldRestorePendingAirbillPreview = <?= (!empty($currentAirbillAttachmentIsPendingUpload) && isset($error)) ? 'true' : 'false' ?>;
            var pendingAirbillPreviewFileName = <?= json_encode(!empty($currentAirbillAttachmentIsPendingUpload) ? (string) $currentAirbillAttachmentValue : '') ?>;

            if (airbillFileInput && airbillPreviewWrap) {
                var clearAirbillPreviewObjectUrl = function () {
                    if (currentAirbillPreviewUrl) {
                        URL.revokeObjectURL(currentAirbillPreviewUrl);
                        currentAirbillPreviewUrl = null;
                    }
                };

                var hideAirbillPreview = function () {
                    airbillPreviewWrap.innerHTML = '';
                    airbillPreviewWrap.style.display = 'none';
                };

                var renderAirbillPreview = function (fileUrl, fileType, fileName) {
                    var resolvedType = String(fileType || '').toLowerCase();
                    var resolvedName = String(fileName || '').toLowerCase();

                    airbillPreviewWrap.innerHTML = '';
                    airbillPreviewWrap.style.display = 'block';

                    if (resolvedType.indexOf('image/') === 0 || /\.(png|jpg|jpeg|webp|gif|svg)$/i.test(resolvedName)) {
                        var imageNode = document.createElement('img');
                        imageNode.id = 'sor_airbill_attachment_preview_img';
                        imageNode.src = fileUrl;
                        imageNode.alt = 'Airbill Attachment Preview';
                        airbillPreviewWrap.appendChild(imageNode);
                        return true;
                    }

                    if (resolvedType === 'application/pdf' || /\.pdf$/i.test(resolvedName)) {
                        var iframeNode = document.createElement('iframe');
                        iframeNode.id = 'sor_airbill_attachment_preview_pdf';
                        iframeNode.src = fileUrl;
                        iframeNode.title = 'Airbill Attachment Preview';
                        airbillPreviewWrap.appendChild(iframeNode);
                        return true;
                    }

                    hideAirbillPreview();
                    return false;
                };

                var saveAirbillPreviewToSession = function (file) {
                    if (!file || typeof FileReader === 'undefined' || typeof window.sessionStorage === 'undefined') {
                        return;
                    }

                    var fileName = file.name || '';
                    var fileType = file.type || '';
                    var isSupportedPreview = fileType.indexOf('image/') === 0 || fileType === 'application/pdf' || /\.(png|jpg|jpeg|webp|gif|svg|pdf)$/i.test(fileName);

                    if (!isSupportedPreview) {
                        return;
                    }

                    var reader = new FileReader();
                    reader.onload = function (event) {
                        try {
                            window.sessionStorage.setItem(airbillPreviewStorageKey, JSON.stringify({
                                name: fileName,
                                type: fileType,
                                dataUrl: event.target.result
                            }));
                        } catch (storageError) {
                            // Browser storage may be full. Current-page preview still works.
                        }
                    };
                    reader.readAsDataURL(file);
                };

                var restoreAirbillPreviewFromSession = function () {
                    if (!shouldRestorePendingAirbillPreview || typeof window.sessionStorage === 'undefined') {
                        return;
                    }

                    try {
                        var storedPreview = window.sessionStorage.getItem(airbillPreviewStorageKey);
                        if (!storedPreview) {
                            return;
                        }

                        var previewData = JSON.parse(storedPreview);
                        if (!previewData || !previewData.dataUrl) {
                            return;
                        }

                        if (
                            pendingAirbillPreviewFileName &&
                            previewData.name &&
                            String(previewData.name) !== String(pendingAirbillPreviewFileName)
                        ) {
                            return;
                        }

                        renderAirbillPreview(previewData.dataUrl, previewData.type || '', previewData.name || pendingAirbillPreviewFileName);
                    } catch (restoreError) {
                        // Ignore invalid stored preview data.
                    }
                };

                airbillFileInput.addEventListener('change', function () {
                    var file = airbillFileInput.files && airbillFileInput.files[0] ? airbillFileInput.files[0] : null;

                    clearAirbillPreviewObjectUrl();

                    if (typeof window.sessionStorage !== 'undefined') {
                        try {
                            window.sessionStorage.removeItem(airbillPreviewStorageKey);
                        } catch (storageError) {
                        }
                    }

                    if (!file) {
                        hideAirbillPreview();
                        return;
                    }

                    var fileUrl = URL.createObjectURL(file);
                    currentAirbillPreviewUrl = fileUrl;

                    if (!renderAirbillPreview(fileUrl, file.type || '', file.name || '')) {
                        clearAirbillPreviewObjectUrl();
                        return;
                    }

                    saveAirbillPreviewToSession(file);
                });

                restoreAirbillPreviewFromSession();
                window.addEventListener('beforeunload', clearAirbillPreviewObjectUrl);
            }

            if (window.shopeeOmsAirbillPdfAutofill) {
                window.shopeeOmsAirbillPdfAutofill.bind({
                    fileInputSelector: '#sor_airbill_attachment',
                    airbillNoSelector: '#sor_airbill',
                    customerNameSelector: '#sor_customer_name',
                    customerAddressSelector: '#sor_customer_address',
                    statusSelector: '#sor_airbill_extract_status',
                    localStorageKey: <?= (int) $dataId > 0 ? "'shopee_airbill_delivery_info_" . (int) $dataId . "'" : "''" ?>,
                    workerSrc: '../finance/header/js/pdf.worker.min.js',
                    errorClass: 'is-error'
                });
            }
            var updateAirbillToggle = document.getElementById('sor_update_airbill_toggle');
            if (updateAirbillToggle) {
                updateAirbillToggle.addEventListener('change', toggleAirbillFields);
            }

                var newCustomerForm = document.getElementById('myForm');
                if (newCustomerForm) {
                    var outerOrderForm = document.getElementById('FORForm');
                    var newCustomerSubmitBtn = document.getElementById('new_customer_submit_btn');
                    var newCustomerFields = newCustomerForm.querySelectorAll('[data-new-customer-required="1"]');
                    var newCustomerLookupFields = [
                        { textId: 'scr_pic', hiddenId: 'scr_pic_hidden', label: 'Sales Person In Charge' },
                        { textId: 'scr_country', hiddenId: 'scr_country_hidden', label: 'Country' },
                        { textId: 'scr_brand', hiddenId: 'scr_brand_hidden', label: 'Brand' },
                        { textId: 'scr_series', hiddenId: 'scr_series_hidden', label: 'Series' }
                    ];

                function validateNewCustomerForm() {
                    var firstInvalidField = null;
                    newCustomerFields.forEach(function (field) {
                        clearNewCustomerInlineError(field);
                        if (field.disabled) {
                            return;
                        }

                        if (field.value.trim() === '') {
                            showNewCustomerInlineError(field, 'This field is required.');
                            if (!firstInvalidField) {
                                firstInvalidField = field;
                            }
                        }
                    });

                    newCustomerLookupFields.forEach(function (config) {
                        var textField = document.getElementById(config.textId);
                        var hiddenField = document.getElementById(config.hiddenId);
                        if (!textField || !hiddenField || textField.disabled) {
                            return;
                        }

                        if (textField.value.trim() !== '' && hiddenField.value.trim() === '') {
                            showNewCustomerInlineError(textField, config.label + ' must be selected from the suggestion list.');
                            if (!firstInvalidField) {
                                firstInvalidField = textField;
                            }
                        }
                    });

                    if (firstInvalidField) {
                        firstInvalidField.focus();
                        return false;
                    }

                    return true;
                }

                newCustomerFields.forEach(function (field) {
                    field.addEventListener('input', function () {
                        if (field.value.trim() !== '') {
                            clearNewCustomerInlineError(field);
                        }
                    });
                });

                newCustomerLookupFields.forEach(function (config) {
                    var textField = document.getElementById(config.textId);
                    var hiddenField = document.getElementById(config.hiddenId);
                    if (!textField || !hiddenField) {
                        return;
                    }

                    textField.addEventListener('input', function () {
                        hiddenField.value = '';
                    });
                });

                function submitNewCustomerForm() {
                    if (!validateNewCustomerForm()) {
                        return;
                    }
                    if (!outerOrderForm) {
                        return;
                    }
                    var existingSubmitMarker = outerOrderForm.querySelector('input[data-new-customer-submit="1"]');
                    if (existingSubmitMarker) {
                        existingSubmitMarker.remove();
                    }
                    var submitMarker = document.createElement('input');
                    submitMarker.type = 'hidden';
                    submitMarker.name = 'submit';
                    submitMarker.value = 'Submit';
                    submitMarker.setAttribute('data-new-customer-submit', '1');
                    outerOrderForm.appendChild(submitMarker);
                    HTMLFormElement.prototype.submit.call(outerOrderForm);
                }

                if (newCustomerSubmitBtn) {
                    newCustomerSubmitBtn.addEventListener('click', submitNewCustomerForm);
                }
            }
        });


        var page = "<?= $pageTitle ?>";
        var action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer", { disableAbsoluteCentering: true });
        setButtonColor();
        preloader(300, action);

        <?php
        include "../js/shopee_order_req.js"
            ?>
    </script>

</body>

</html>
