<?php
$pageTitle = "Campaign2";
$currentPagePin = 153;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign2_common.php';

$pinAccess = checkCurrentPin($connect, $pageTitle);

$dataId = (int) (!empty(input('id')) ? input('id') : post('id'));
$act = !empty(input('act')) ? input('act') : post('act');
$isAdd = $act === $act_1;
$isEdit = $act === $act_2;
$isView = !$isAdd && !$isEdit;
$pageActionTitle = displayPageAction($act, $pageTitle);

if (($isAdd && !isActionAllowed('Add', $pinAccess)) || ($isEdit && !isActionAllowed('Edit', $pinAccess)) || ($isView && !isActionAllowed('View', $pinAccess))) {
    echo '<script>location.href = "' . $SITEURL . '/campaign2/campaign2_list.php";</script>';
    exit();
}

if (!$isAdd && $dataId <= 0) {
    echo '<script>location.href = "' . $SITEURL . '/campaign2/campaign2_list.php";</script>';
    exit();
}

$csrfToken = campaign2CsrfToken('campaign2_form');
$redirectPage = $SITEURL . '/campaign2/campaign2_list.php';
$pageUrl = $SITEURL . '/campaign2/campaign2_form.php?id=' . (int) $dataId . '&act=' . campaignH($act);

$row = $isAdd ? array() : campaign2FetchCampaign($connect, $dataId);
if (!$isAdd && empty($row)) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

// 获取PIC用户
$selectedPicId = 0;
$selectedPicName = '';
if (!$isAdd && !empty($row)) {
    $picStmt = $connect->prepare("SELECT `user_id` FROM `" . CAMPAIGN2_PIC . "` WHERE `campaign_id`=? AND `status`='A' LIMIT 1");
    if ($picStmt) {
        $picStmt->bind_param('i', $dataId);
        $picStmt->execute();
        $picResult = $picStmt->get_result();
        if ($picResult && $picResult->num_rows > 0) {
            $picRow = $picResult->fetch_assoc();
            $selectedPicId = (int) ($picRow['user_id'] ?? 0);
        }
        $picStmt->close();
    }
}

$userRows = campaign2FetchUsers($connect);
foreach ($userRows as $userRow) {
    if ((int) $userRow['id'] === $selectedPicId) {
        $selectedPicName = (string) $userRow['name'];
        break;
    }
}

$errors = array();
$formValues = array(
    'campaign_name' => isset($row['campaign_name']) ? $row['campaign_name'] : '',
    'period_start_date' => isset($row['period_start_date']) ? $row['period_start_date'] : '',
    'period_end_date' => isset($row['period_end_date']) ? $row['period_end_date'] : '',
    'description' => isset($row['description']) ? $row['description'] : '',
);

// 处理Save
if (post('actionBtn') === 'saveCampaign') {
    $postedToken = (string) post('csrf_token');
    $formValues['campaign_name'] = trim((string) post('campaign_name'));
    $formValues['period_start_date'] = trim((string) post('period_start_date'));
    $formValues['period_end_date'] = trim((string) post('period_end_date'));
    $formValues['description'] = trim((string) post('description'));
    $selectedPicName = trim((string) post('pic_user_search'));
    $selectedPicId = (int) post('pic_user_id');

    if (!campaign2VerifyCsrf('campaign2_form', $postedToken)) {
        $errors[] = 'Invalid security token.';
    }

    if ($formValues['campaign_name'] === '') {
        $errors[] = 'Campaign Name is required.';
    }

    if ($formValues['period_start_date'] === '') {
        $errors[] = 'Period start date is required.';
    }

    if ($formValues['period_end_date'] === '') {
        $errors[] = 'Period end date is required.';
    }

    if ($formValues['period_start_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formValues['period_start_date'])) {
        $errors[] = 'Period start date format is invalid.';
    }

    if ($formValues['period_end_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formValues['period_end_date'])) {
        $errors[] = 'Period end date format is invalid.';
    }

    if ($formValues['period_start_date'] !== '' && $formValues['period_end_date'] !== '' && $formValues['period_start_date'] > $formValues['period_end_date']) {
        $errors[] = 'Period end date must be on or after start date.';
    }

    if ($selectedPicId <= 0) {
        $errors[] = 'Person In Charge is required.';
    }

    if (empty($errors)) {
        $connect->begin_transaction();
        $saveSuccess = false;
        $queryForAudit = '';

        try {
            if ($isAdd) {
                $stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN2 . "` (`campaign_name`, `period_start_date`, `period_end_date`, `description`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'A')");
                if (!$stmt) {
                    throw new Exception($connect->error);
                }

                $createBy = (string) USER_ID;
                $stmt->bind_param('sssss', $formValues['campaign_name'], $formValues['period_start_date'], $formValues['period_end_date'], $formValues['description'], $createBy);
                $saveSuccess = $stmt->execute();
                $dataId = $connect->insert_id;
                $stmt->close();
                $queryForAudit = 'INSERT INTO ' . CAMPAIGN2 . ' campaign_name=' . $formValues['campaign_name'];
            } else {
                $stmt = $connect->prepare("UPDATE `" . CAMPAIGN2 . "` SET `campaign_name`=?, `period_start_date`=?, `period_end_date`=?, `description`=?, `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `status`='A'");
                if (!$stmt) {
                    throw new Exception($connect->error);
                }

                $updateBy = (string) USER_ID;
                $stmt->bind_param('sssssi', $formValues['campaign_name'], $formValues['period_start_date'], $formValues['period_end_date'], $formValues['description'], $updateBy, $dataId);
                $saveSuccess = $stmt->execute();
                $stmt->close();
                $queryForAudit = 'UPDATE ' . CAMPAIGN2 . ' id=' . $dataId;
            }

            if (!$saveSuccess || $dataId <= 0) {
                throw new Exception('Unable to save Campaign.');
            }

            // 更新PIC
            $safeUserId = $connect->real_escape_string((string) USER_ID);
            $connect->query("UPDATE `" . CAMPAIGN2_PIC . "` SET `status`='D', `update_by`='" . $safeUserId . "', `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `campaign_id`=" . (int) $dataId . " AND `status`='A'");

            $stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN2_PIC . "` (`campaign_id`, `user_id`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, CURDATE(), CURTIME(), 'A')");
            if (!$stmt) {
                throw new Exception('Unable to save Campaign PIC.');
            }

            $createBy = (string) USER_ID;
            $stmt->bind_param('iis', $dataId, $selectedPicId, $createBy);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception('Unable to save Campaign PIC.');
            }
            $stmt->close();

            $connect->commit();
            campaign2Audit($connect, $pageTitle, $isAdd ? 'add' : 'edit', USER_NAME . ' ' . ($isAdd ? 'added' : 'edited') . ' Campaign2 [ID=' . $dataId . '] ' . $formValues['campaign_name'], $queryForAudit);

            campaign2SetPopup('Campaign ' . ($isAdd ? 'added' : 'edited') . ' successfully', $redirectPage, 'ErrMO');
            echo '<script>location.href = "' . $redirectPage . '";</script>';
            exit();
        } catch (Exception $e) {
            $connect->rollback();
            $errors[] = 'Failed to save Campaign. ' . $e->getMessage();
        }
    }
}

if ($isView && $dataId > 0 && USER_ID && empty($_SESSION['campaign2_view_' . $dataId])) {
    $_SESSION['campaign2_view_' . $dataId] = 1;
    campaign2Audit($connect, $pageTitle, 'view', USER_NAME . " viewed Campaign2 ID=" . $dataId . " " . $formValues['campaign_name']);
}

$readonlyAttr = $isView ? 'readonly' : '';
$periodEnded = !$isAdd && campaign2IsPeriodEnded($row);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .customer-panel { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .followup-panel { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .customer-row { padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .followup-row { padding: 10px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="page-load-cover">
        <div class="container-fluid px-4">
            <div class="row mt-3">
                <div class="col-12">
                    <p>
                        <a href="<?= $SITEURL ?>/campaign2/campaign2_list.php">Campaign2</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        <?= campaign2H($pageActionTitle) ?>
                    </p>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <h1><?= campaign2H($pageActionTitle) ?></h1>
            </div>

            <form id="form" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= campaign2H($csrfToken) ?>">
                <input type="hidden" name="id" value="<?= (int) $dataId ?>">
                <input type="hidden" name="act" value="<?= campaign2H($act) ?>">

                <div class="card mb-4">
                    <div class="card-header bg-white"><strong>Campaign Information</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Campaign Name *</label>
                                <input type="text" name="campaign_name" class="form-control" value="<?= campaign2H($formValues['campaign_name']) ?>" <?= $readonlyAttr ?> required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Person In Charge *</label>
                                <div class="autocomplete">
                                    <input type="text" id="pic_user_search" class="form-control" value="<?= campaign2H($selectedPicName) ?>" <?= $readonlyAttr ?> autocomplete="off">
                                    <input type="hidden" name="pic_user_id" id="pic_user_id" value="<?= (int) $selectedPicId ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period Start Date *</label>
                                <input type="date" name="period_start_date" class="form-control" value="<?= campaign2H($formValues['period_start_date']) ?>" <?= $readonlyAttr ?> required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period End Date *</label>
                                <input type="date" name="period_end_date" class="form-control" value="<?= campaign2H($formValues['period_end_date']) ?>" <?= $readonlyAttr ?> required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" <?= $readonlyAttr ?>><?= campaign2H($formValues['description']) ?></textarea>
                        </div>
                    </div>
                </div>

                <?php if (!$isAdd): ?>
                    <!-- Assigned Customers Panel -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <strong>Assigned Customers</strong>
                            <?php if (!$periodEnded && !$isView): ?>
                                <button type="button" class="btn btn-sm btn-primary float-end" id="btn-add-customer">Add Customer</button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div id="assigned-customers-container">
                                <!-- AJAX will load here -->
                                <p class="text-muted">Loading customers...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Follow-up Tasks Panel -->
                    <div class="card mb-4">
                        <div class="card-header bg-white"><strong>Follow-up Tasks</strong></div>
                        <div class="card-body">
                            <div id="followup-tasks-container">
                                <!-- AJAX will load here -->
                                <p class="text-muted">Loading follow-up tasks...</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group mt-4 d-flex justify-content-center">
                    <?php if (!$isView): ?>
                        <button type="submit" class="btn btn-lg btn-primary mx-2" name="actionBtn" value="saveCampaign">
                            <?= $isAdd ? 'Add Campaign' : 'Edit Campaign' ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-lg btn-secondary mx-2" onclick="window.location.href='<?= campaign2H($redirectPage) ?>'">Back</button>
                </div>
            </form>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mt-4">
                    <strong>Errors:</strong><br>
                    <?php foreach ($errors as $error): ?>
                        <?= campaign2H($error) ?><br>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        checkCurrentPage('<?= campaign2H($pageTitle) ?>', '<?= campaign2H($act) ?>');
        setButtonColor();

        <?php
        campaign2RenderAutocompleteScript(array(
            array(
                'inputId' => 'pic_user_search',
                'hiddenId' => 'pic_user_id',
                'options' => $userRows,
            ),
        ));
        ?>

        <?php if (!$isAdd): ?>
        // 加载已分配的客户
        function loadAssignedCustomers() {
            fetch('<?= $SITEURL ?>/campaign2/actions/customer_list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'campaign_id=<?= (int) $dataId ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('assigned-customers-container').innerHTML = data.html;
                }
            });
        }

        // 加载Follow-up Tasks
        function loadFollowupTasks() {
            fetch('<?= $SITEURL ?>/campaign2/actions/followup_list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'campaign_id=<?= (int) $dataId ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('followup-tasks-container').innerHTML = data.html;
                }
            });
        }

        // 页面加载时刷新列表
        document.addEventListener('DOMContentLoaded', function() {
            loadAssignedCustomers();
            loadFollowupTasks();
        });

        // Add Customer按钮
        document.getElementById('btn-add-customer')?.addEventListener('click', function() {
            // TODO: 弹出客户浏览/选择modal
            alert('客户选择功能待实现');
        });
        <?php endif; ?>
    </script>
    <?php campaign2RenderPopupScript($pageTitle, $redirectPage); ?>
</body>
</html>
