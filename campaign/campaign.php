<?php
$pageTitle = "Campaign";
$currentPagePin = 153;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign_common.php';

$resolvedPageTitle = getPinGroupNameById($connect, $currentPagePin);
if (!empty($resolvedPageTitle)) {
    $pageTitle = $resolvedPageTitle;
}

$tblName = CAMPAIGN;
$redirectPage = $SITEURL . '/campaign/campaign_table.php';
$pinAccess = checkCurrentPin($connect, $pageTitle);

$dataId = (int) (!empty(input('id')) ? input('id') : post('id'));
$act = !empty(input('act')) ? input('act') : post('act');
$isAdd = $act === $act_1;
$isEdit = $act === $act_2;
$isView = !$isAdd && !$isEdit;
$pageAction = $isAdd ? 'Add' : ($isEdit ? 'Edit' : 'View');
$pageActionTitle = displayPageAction($act, $pageTitle);

if (($isAdd && !isActionAllowed('Add', $pinAccess)) || ($isEdit && !isActionAllowed('Edit', $pinAccess)) || ($isView && !isActionAllowed('View', $pinAccess))) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

if (!$isAdd && $dataId <= 0) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

if (empty($_SESSION['campaign_form_csrf_token'])) {
    $_SESSION['campaign_form_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['campaign_form_csrf_token'];

function campaignFormAudit($connect, $pageTitle, $action, $message, $query = '', $oldValue = '', $newValue = '', $changes = '')
{
    audit_log(array(
        'log_act' => $action,
        'cdate' => date_dis,
        'ctime' => time_dis,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $message,
        'query_rec' => $query,
        'query_table' => CAMPAIGN,
        'oldval' => $oldValue,
        'newval' => $newValue,
        'changes' => $changes,
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

function campaignFetchRow($connect, $campaignId)
{
    $stmt = $connect->prepare("SELECT * FROM `" . CAMPAIGN . "` WHERE `id` = ? AND `status` = 'A' LIMIT 1");
    if (!$stmt) {
        return array();
    }

    $stmt->bind_param('i', $campaignId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : array();
    $stmt->close();

    return is_array($row) ? $row : array();
}

function campaignFetchPicIds($connect, $campaignId)
{
    $picIds = array();
    $stmt = $connect->prepare("SELECT `user_id` FROM `" . CAMPAIGN_PIC . "` WHERE `campaign_id` = ? AND `status` = 'A' ORDER BY `id` ASC");
    if (!$stmt) {
        return $picIds;
    }

    $stmt->bind_param('i', $campaignId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && ($row = $result->fetch_assoc())) {
        $picIds[] = isset($row['user_id']) ? (int) $row['user_id'] : 0;
    }
    $stmt->close();

    return array_values(array_filter(array_unique($picIds)));
}

function campaignNormalizePicId($rawPicId)
{
    $picId = (int) $rawPicId;
    return $picId > 0 ? $picId : 0;
}

function campaignHasDuplicateCode($connect, $campaignCode, $excludeId = 0)
{
    if (!campaignColumnExists($connect, CAMPAIGN, 'campaign_code')) {
        return false;
    }

    $excludeId = (int) $excludeId;
    $stmt = $connect->prepare("SELECT `id` FROM `" . CAMPAIGN . "` WHERE `campaign_code` = ? AND `status` = 'A' AND `id` <> ? LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $campaignCode, $excludeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasDuplicate = ($result && $result->num_rows > 0);
    $stmt->close();

    return $hasDuplicate;
}

function campaignIsValidDateValue($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function campaignReplacePicRows($connect, $campaignId, $picIds)
{
    $campaignId = (int) $campaignId;
    $safeUserId = $connect->real_escape_string((string) USER_ID);
    if (!$connect->query("UPDATE `" . CAMPAIGN_PIC . "` SET `status` = 'D', `update_by` = '" . $safeUserId . "', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE `campaign_id` = " . $campaignId . " AND `status` = 'A'")) {
        return false;
    }

    $stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN_PIC . "` (`campaign_id`, `user_id`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, CURDATE(), CURTIME(), 'A')");
    if (!$stmt) {
        return false;
    }

    $createBy = (string) USER_ID;
    foreach ($picIds as $picId) {
        $picId = (int) $picId;
        $stmt->bind_param('iis', $campaignId, $picId, $createBy);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
    }

    $stmt->close();
    return true;
}

$row = $isAdd ? array() : campaignFetchRow($connect, $dataId);
if (!$isAdd && empty($row)) {
    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

$selectedPicIds = $isAdd ? array() : campaignFetchPicIds($connect, $dataId);
$selectedPicId = !empty($selectedPicIds) ? (int) $selectedPicIds[0] : 0;
$selectedPicName = '';
$userRows = campaignFetchUsers($connect);
foreach ($userRows as $userRow) {
    if ((int) $userRow['id'] === $selectedPicId) {
        $selectedPicName = (string) $userRow['name'];
        break;
    }
}

$packageOptions = array();
if (defined('PKG') && campaignTableExists($connect, PKG)) {
    $packageResult = mysqli_query($connect, "SELECT `id`, `name` FROM `" . PKG . "` WHERE `status` = 'A' ORDER BY `name` ASC");
    if ($packageResult) {
        while ($packageRow = $packageResult->fetch_assoc()) {
            $packageOptions[] = array(
                'id' => (int) ($packageRow['id'] ?? 0),
                'name' => trim((string) ($packageRow['name'] ?? '')),
            );
        }
    }
}
$selectedPackageIds = $isAdd ? array() : campaignFetchCampaignPackageIds($connect, $dataId);

$errors = array();
$formValues = array(
    'campaign_name' => isset($row['campaign_name']) ? $row['campaign_name'] : '',
    'period_start_date' => isset($row['period_start_date']) ? $row['period_start_date'] : '',
    'period_end_date' => isset($row['period_end_date']) ? $row['period_end_date'] : '',
    'description' => isset($row['description']) ? $row['description'] : '',
);

if (post('actionBtn') === 'saveCampaign') {
    $postedToken = (string) post('csrf_token');
    $formValues['campaign_name'] = trim((string) post('campaign_name'));
    $formValues['period_start_date'] = trim((string) post('period_start_date'));
    $formValues['period_end_date'] = trim((string) post('period_end_date'));
    $formValues['description'] = trim((string) post('description'));
    $selectedPicName = trim((string) post('pic_user_search'));
    $selectedPicId = campaignNormalizePicId(post('pic_user_id'));
    $selectedPackageIds = array_values(array_unique(array_filter(array_map('intval', (array) post('package_ids') ?: array()), function ($value) {
        return $value > 0;
    })));

    if (!hash_equals($csrfToken, $postedToken)) {
        $errors[] = 'Invalid security token. Please try again.';
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

    if ($formValues['period_start_date'] !== '' && !campaignIsValidDateValue($formValues['period_start_date'])) {
        $errors[] = 'Period start date is invalid.';
    }

    if ($formValues['period_end_date'] !== '' && !campaignIsValidDateValue($formValues['period_end_date'])) {
        $errors[] = 'Period end date is invalid.';
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
                $stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN . "` (`campaign_name`, `period_start_date`, `period_end_date`, `description`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), 'A')");
                if (!$stmt) {
                    throw new Exception($connect->error);
                }

                $createBy = (string) USER_ID;
                $stmt->bind_param('sssss', $formValues['campaign_name'], $formValues['period_start_date'], $formValues['period_end_date'], $formValues['description'], $createBy);
                $saveSuccess = $stmt->execute();
                $dataId = $connect->insert_id;
                $stmt->close();
                $queryForAudit = 'INSERT INTO ' . CAMPAIGN . ' campaign_name=' . $formValues['campaign_name'];
            } else {
                $stmt = $connect->prepare("UPDATE `" . CAMPAIGN . "` SET `campaign_name` = ?, `period_start_date` = ?, `period_end_date` = ?, `description` = ?, `update_by` = ?, `update_date` = CURDATE(), `update_time` = CURTIME() WHERE `id` = ? AND `status` = 'A'");
                if (!$stmt) {
                    throw new Exception($connect->error);
                }

                $updateBy = (string) USER_ID;
                $stmt->bind_param('sssssi', $formValues['campaign_name'], $formValues['period_start_date'], $formValues['period_end_date'], $formValues['description'], $updateBy, $dataId);
                $saveSuccess = $stmt->execute();
                $stmt->close();
                $queryForAudit = 'UPDATE ' . CAMPAIGN . ' id=' . $dataId;
            }

            if (!$saveSuccess || $dataId <= 0 || !campaignReplacePicRows($connect, $dataId, array($selectedPicId))) {
                throw new Exception('Unable to save Campaign PIC rows.');
            }

            if (!campaignReplaceCampaignPackageRows($connect, $dataId, $selectedPackageIds)) {
                throw new Exception('Unable to save Campaign Package rows.');
            }

            $connect->commit();
            campaignFormAudit(
                $connect,
                $pageTitle,
                $isAdd ? 'add' : 'edit',
                USER_NAME . ' ' . strtolower($isAdd ? 'added' : 'edited') . ' Campaign [<b> ID = ' . $dataId . '</b> ] <b>' . campaignH($formValues['campaign_name']) . '</b>.',
                $queryForAudit,
                '',
                $isAdd ? implode(', ', $formValues) : '',
                $isEdit ? implode(', ', $formValues) : ''
            );

            campaignSetPopup('Successful ' . ($isAdd ? 'Add' : 'Edit') . ' Campaign', $redirectPage, 'ErrMO');
            echo '<script>location.href = "' . $redirectPage . '";</script>';
            exit();
        } catch (Exception $e) {
            $connect->rollback();
            $errors[] = 'Failed to save Campaign. ' . $e->getMessage();
            campaignFormAudit($connect, $pageTitle, $isAdd ? 'add' : 'edit', USER_NAME . ' failed to save Campaign.', $queryForAudit);
        }
    }
}

if ($isView && $dataId > 0 && USER_ID && empty($_SESSION['campaign_view_' . $dataId])) {
    $_SESSION['campaign_view_' . $dataId] = 1;
    campaignFormAudit($connect, $pageTitle, 'view', USER_NAME . " viewed the data [<b> ID = " . $dataId . "</b> ] <b>" . campaignH($formValues['campaign_name']) . "</b> from <b><i>" . CAMPAIGN . " Table</i></b>.");
}

$readonlyAttr = $isView ? 'readonly' : '';
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<body>
    

    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p>
                <a href="<?= $redirectPage ?>"><?= campaignH($pageTitle) ?></a>
                <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?= campaignH($pageActionTitle) ?>
            </p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-12 col-md-8 formWidthAdjust">
                <form id="form" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= campaignH($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= (int) $dataId ?>">
                    <input type="hidden" name="act" value="<?= campaignH($act) ?>">

                    <div class="form-group mb-4">
                        <h2><?= campaignH($pageActionTitle) ?></h2>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="campaign_name">Campaign Name*</label>
                                <input class="form-control" type="text" name="campaign_name" id="campaign_name" value="<?= campaignH($formValues['campaign_name']) ?>" <?= $readonlyAttr ?> required autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="period_start_date">Period Start Date*</label>
                                <input class="form-control" type="date" name="period_start_date" id="period_start_date" value="<?= campaignH($formValues['period_start_date']) ?>" <?= $readonlyAttr ?> required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="period_end_date">Period End Date*</label>
                                <input class="form-control" type="date" name="period_end_date" id="period_end_date" value="<?= campaignH($formValues['period_end_date']) ?>" <?= $readonlyAttr ?> required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl" for="pic_user_search">Person In Charge*</label>
                                <div class="autocomplete">
                                    <input class="form-control" type="text" id="pic_user_search" value="<?= campaignH($selectedPicName) ?>" <?= $readonlyAttr ?> autocomplete="off">
                                    <input type="hidden" name="pic_user_id" id="pic_user_id" value="<?= (int) $selectedPicId ?>">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label form_lbl">Package(s)</label>
                                <div class="dropdown campaign-filter-dropdown">
                                    <button class="campaign-filter-toggle" type="button" id="campaignPackageToggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" <?= $isView ? 'disabled' : '' ?>>
                                        <span id="campaignPackageToggleLabel">All Packages (No Restriction)</span>
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="campaignPackageToggle" style="min-width: 300px;">
                                        <div class="px-2 py-1">
                                            <input type="text" class="form-control form-control-sm campaign-package-search" placeholder="Search packages..." <?= $isView ? 'disabled' : '' ?>>
                                        </div>
                                        <div class="dropdown-divider"></div>
                                        <div class="campaign-package-list" style="max-height: 300px; overflow-y: auto;">
                                            <?php if (empty($packageOptions)): ?>
                                                <div class="px-2 py-1 text-muted">No packages found.</div>
                                            <?php endif; ?>
                                            <?php foreach ($packageOptions as $packageOption): ?>
                                                <div class="form-check px-2 py-1 campaign-package-item" data-package-name="<?= campaignH(strtolower($packageOption['name'])) ?>">
                                                    <input class="form-check-input campaign-package-checkbox" type="checkbox" name="package_ids[]" value="<?= (int) $packageOption['id'] ?>" id="campaign_package_<?= (int) $packageOption['id'] ?>" <?= in_array((int) $packageOption['id'], $selectedPackageIds, true) ? 'checked' : '' ?> <?= $isView ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="campaign_package_<?= (int) $packageOption['id'] ?>"><?= campaignH($packageOption['name']) ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">Leave empty to count purchases of any package in the Campaign Report. Selecting one or more packages limits the report to only those packages' purchases.</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label form_lbl" for="description">Remark</label>
                        <textarea class="form-control" name="description" id="description" rows="4" <?= $readonlyAttr ?>><?= campaignH($formValues['description']) ?></textarea>
                    </div>

                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php if (!$isView): ?>
                            <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" type="submit" name="actionBtn" id="actionBtn" value="saveCampaign"><?= $isAdd ? 'Add Campaign' : 'Edit Campaign' ?></button>
                        <?php endif; ?>
                        <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="button" name="actionBtn" id="backBtn" value="back" onclick="<?= campaignH(campaignBackButtonJs($redirectPage)) ?>">Back</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        const page = "<?= campaignH($pageTitle) ?>";
        const action = "<?= campaignH($act) ?>";

        checkCurrentPage(page, action);
        centerAlignment("formContainer");
        setButtonColor();
        preloader(300, action);

        (function () {
            var toggleLabel = document.getElementById('campaignPackageToggleLabel');
            var searchInput = document.querySelector('.campaign-package-search');
            var checkboxes = document.querySelectorAll('.campaign-package-checkbox');
            var packageItems = document.querySelectorAll('.campaign-package-item');

            if (!toggleLabel || !checkboxes.length) {
                return;
            }

            function updatePackageToggleLabel() {
                var checkedCount = document.querySelectorAll('.campaign-package-checkbox:checked').length;
                toggleLabel.textContent = checkedCount > 0
                    ? checkedCount + ' package(s) selected'
                    : 'All Packages (No Restriction)';
            }

            function filterPackages() {
                if (!searchInput) return;
                var searchText = searchInput.value.toLowerCase().trim();
                packageItems.forEach(function (item) {
                    var packageName = item.getAttribute('data-package-name');
                    var matches = searchText === '' || packageName.indexOf(searchText) !== -1;
                    item.style.display = matches ? '' : 'none';
                });
            }

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', updatePackageToggleLabel);
            });

            if (searchInput) {
                searchInput.addEventListener('input', filterPackages);
            }

            updatePackageToggleLabel();
        })();
    </script>
    <?php
    campaignRenderAutocompleteScript(array(
        array(
            'inputId' => 'pic_user_search',
            'hiddenId' => 'pic_user_id',
            'options' => $userRows,
        ),
    ));
    if (!empty($errors)) {
        echo '<script>confirmationDialog("", ' . campaignJson(implode("\\n", $errors)) . ', ' . campaignJson($pageTitle) . ', "", ' . campaignJson($redirectPage) . ', "ErrMO");</script>';
    }
    ?>
</body>

</html>
