<?php
$currentPagePin = 9;
$pageTitle = "Brand";

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById($connect, $currentPagePin);

$tblName = BRAND;

//Current Page Action And Data ID
$rawDataID = !empty(input('id')) ? input('id') : post('id');
$dataId = '';
if ($rawDataID !== '' && ctype_digit((string) $rawDataID)) {
    $dataId = (string) ((int) $rawDataID);
}
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirectPage = $SITEURL . '/product/brand_table.php';
$redirectLink = ("<script>location.href = '$redirectPage';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

//Checking The Page ID , Action , Pin Access Exist Or Not
if (($rawDataID !== '' && $dataId === '') || (!($dataId) && !($act)) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

//Get The Data From Database
$row = [];
$result = false;
if ($dataId) {
    $result = getData('*', "id = '$dataId'", '', $tblName, $connect);
}

$companyOptions = [];
$companyResult = mysqli_query($connect, "SELECT id, name FROM " . COMPANY . " WHERE status = 'A' ORDER BY name ASC");
if ($companyResult) {
    while ($companyRow = $companyResult->fetch_assoc()) {
        $companyOptions[] = $companyRow;
    }
}

//Checking Data Error When Retrieved From Database
if ($dataId && (!$result || !($row = $result->fetch_assoc())) && $act != 'I') {
    $errorExist = 1;
    // $_SESSION['tempValConfirmBox'] = true;
    $act = "F";
}

$selectedCompanyName = '';
if (!empty($row['company'])) {
    foreach ($companyOptions as $companyOption) {
        if ((string) $companyOption['id'] === (string) $row['company']) {
            $selectedCompanyName = $companyOption['name'];
            break;
        }
    }
}

if (post('actionBtn') && post('actionBtn') !== 'back') {
    $selectedCompanyName = postSpaceFilter('companyName');
}

$retainFormInput = ($_SERVER['REQUEST_METHOD'] === 'POST' && post('actionBtn') !== 'back');
$selectedCompanyId = isset($row['company']) ? $row['company'] : '';
$selectedCompanyLabel = $selectedCompanyName;
if ($retainFormInput) {
    $selectedCompanyName = isset($_POST['companyName']) ? trim((string)$_POST['companyName']) : $selectedCompanyName;
    $selectedCompanyId = isset($_POST['company']) ? trim((string)$_POST['company']) : '';
    $selectedCompanyLabel = isset($_POST['company_selected_name']) ? trim((string)$_POST['company_selected_name']) : '';

    // Always prefer the selected option label when a valid company ID is posted.
    if ($selectedCompanyId !== '' && ctype_digit((string)$selectedCompanyId)) {
        foreach ($companyOptions as $companyOption) {
            if ((string)$companyOption['id'] === (string)$selectedCompanyId) {
                $selectedCompanyName = $companyOption['name'];
                $selectedCompanyLabel = $companyOption['name'];
                break;
            }
        }
    } else if ($selectedCompanyLabel !== '') {
        // If ID is lost but user selected an option, keep the selected label instead of raw typed keyword.
        $selectedCompanyName = $selectedCompanyLabel;
    }
}

//Delete Data
if ($act == 'D') {
    deleteRecord($tblName, '', $dataId, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
    $_SESSION['delChk'] = 1;
}

//View Data
if ($dataId && !$act && USER_ID && !$_SESSION['viewChk'] && !$_SESSION['delChk']) {

    $_SESSION['viewChk'] = 1;

    $safeUserName = htmlspecialchars((string) USER_NAME, ENT_QUOTES, 'UTF-8');
    $safeDataID = htmlspecialchars((string) $dataId, ENT_QUOTES, 'UTF-8');
    $safeTblName = htmlspecialchars((string) $tblName, ENT_QUOTES, 'UTF-8');

    if (isset($errorExist)) {
        $viewActMsg = $safeUserName . " fail to viewed the data [<b> ID = " . $safeDataID . "</b> ] from <b><i>" . $safeTblName . " Table</i></b>.";
    } else {
        $safeRowName = htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8');
        $viewActMsg = $safeUserName . " viewed the data [<b> ID = " . $safeDataID . "</b> ] <b>" . $safeRowName . "</b> from <b><i>" . $safeTblName . " Table</i></b>.";
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
            $company = postSpaceFilter('company');
            $companyName = postSpaceFilter('companyName');
            $dataRemark = postSpaceFilter('currentDataRemark');
            $hasValidationError = false;

            $isBrandNameEmpty = !isset($_POST['currentDataName']) || trim((string)$currentDataName) === '';
            $isCompanyNameEmpty = !isset($_POST['companyName']) || trim((string)$companyName) === '';

            if ($isBrandNameEmpty) {
                $name_err = 'Brand Name is required!';
                $hasValidationError = true;
            }

            if ($isCompanyNameEmpty) {
                $company_err = 'Company is required!';
                $hasValidationError = true;
            } else {
                $isValidCompanySelection = false;
                $matchedCompanyName = '';

                if ($company !== '' && ctype_digit((string) $company)) {
                    $company = (string) ((int) $company);
                    foreach ($companyOptions as $companyOption) {
                        if ((string) $companyOption['id'] === $company) {
                            $matchedCompanyName = trim((string) $companyOption['name']);
                            break;
                        }
                    }

                    if ($matchedCompanyName !== '' && strcasecmp($matchedCompanyName, trim((string) $companyName)) === 0) {
                        $isValidCompanySelection = true;
                    }
                }

                if (!$isValidCompanySelection) {
                    $company_err = 'Please select valid company!';
                    $hasValidationError = true;
                }
            }

            if ($hasValidationError) {
                break;
            }

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataId)) {
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

                    if ($company) {
                        array_push($newvalarr, $company);
                        array_push($datafield, 'company');
                    }

                    if ($dataRemark) {
                        array_push($newvalarr, $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    // Escape strings for SQL to prevent injection
                    $safeName = mysqli_real_escape_string($connect, $currentDataName);
                    $safeCompany = mysqli_real_escape_string($connect, $company);
                    $safeRemark = mysqli_real_escape_string($connect, $dataRemark);

                    $query = "INSERT INTO " . $tblName . "(name,company,remark,create_by,create_date,create_time) VALUES ('$safeName','$safeCompany','$safeRemark','" . USER_ID . "',curdate(),curtime())";
                    $returnData = mysqli_query($connect, $query);
                    if ($returnData) {
                        $dataId = $connect->insert_id;
                        generateDBData($tblName, $connect);
                    }
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

                    if ($row['company'] != $company) {
                        array_push($oldvalarr, $row['company']);
                        array_push($chgvalarr, $company);
                        array_push($datafield, 'company');
                    }

                    if ($row['remark'] != $dataRemark) {
                        array_push($oldvalarr, $row['remark'] == '' ? 'Empty Value' : $row['remark']);
                        array_push($chgvalarr, $dataRemark == '' ? 'Empty Value' : $dataRemark);
                        array_push($datafield, 'remark');
                    }

                    $_SESSION['tempValConfirmBox'] = true;

                    if ($oldvalarr && $chgvalarr) {
                        // Escape strings for SQL to prevent injection
                        $safeName = mysqli_real_escape_string($connect, $currentDataName);
                        $safeCompany = mysqli_real_escape_string($connect, $company);
                        $safeRemark = mysqli_real_escape_string($connect, $dataRemark);
                        $safeID = (int)$dataId; // Cast ID to int for safety

                        $query = "UPDATE " . $tblName . " SET name ='$safeName', company ='$safeCompany', remark ='$safeRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$safeID'";
                        $returnData = mysqli_query($connect, $query);
                        if ($returnData) {
                            generateDBData($tblName, $connect);
                        }
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
                    $log['act_msg'] = actMsgLog($dataId, $datafield, $newvalarr, '', '', $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
                } else if ($pageAction == 'Edit') {
                    $log['oldval']  = implodeWithComma($oldvalarr);
                    $log['changes'] = implodeWithComma($chgvalarr);
                    $log['act_msg'] = actMsgLog($dataId, $datafield, '', $oldvalarr, $chgvalarr, $tblName, $pageAction, (isset($returnData) ? '' : $errorMsg));
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
    echo '<script>confirmationDialog("","","' . $pageTitle . '","","' . $redirectPage . '","' . $act . '");</script>';
}

$submittedForSave = in_array((string)post('actionBtn'), array('addData', 'updData'), true);
if ($submittedForSave) {
    if (!isset($name_err) && trim((string)post('currentDataName')) === '') {
        $name_err = 'Brand Name is required!';
    }
    if (!isset($company_err) && trim((string)post('companyName')) === '') {
        $company_err = 'Company is required!';
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<body>
    

    <div class="page-load-cover">

        <div class="d-flex flex-column my-3 ms-3">
            <p><a href="<?= $redirectPage ?>"><?= $pageTitle ?></a> <i class="fa-solid fa-chevron-right fa-xs"></i>
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
                        <label class="form-label form_lbl" for="currentDataName"><?php echo $pageTitle ?> Name<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" name="currentDataName" id="currentDataName" value="<?= htmlspecialchars($retainFormInput ? (isset($_POST['currentDataName']) ? trim((string)$_POST['currentDataName']) : '') : (isset($row['name']) ? $row['name'] : ''), ENT_QUOTES, 'UTF-8') ?>" <?php if ($act == '') echo 'readonly' ?> required autocomplete="off">
                        <div id="err_msg">
                            <span class="mt-n1" id="errorSpan"><?php if (isset($name_err)) echo $name_err; else if (isset($err)) echo $err; ?></span>
                        </div>
                    </div>

                    <div class="form-group mb-3 autocomplete">
                        <label class="form-label form_lbl" for="companyName">Company<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="companyName" name="companyName" value="<?= htmlspecialchars($selectedCompanyName) ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off" required>
                        <input type="hidden" id="company" name="company" value="<?= htmlspecialchars($selectedCompanyId, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="company_selected_name" name="company_selected_name" value="<?= htmlspecialchars($selectedCompanyLabel, ENT_QUOTES, 'UTF-8') ?>">
                        <div id="company_err_msg">
                            <span class="mt-n1" id="companyErrorSpan" style="color: red;"><?php if (isset($company_err)) echo $company_err; ?></span>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataRemark"><?php echo $pageTitle ?> Remark</label>
                        <textarea class="form-control" name="currentDataRemark" id="currentDataRemark" rows="3" <?php if ($act == '') echo 'readonly' ?>><?= htmlspecialchars($retainFormInput ? (isset($_POST['currentDataRemark']) ? trim((string)$_POST['currentDataRemark']) : '') : (isset($row['remark']) ? $row['remark'] : ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <?php echo commonRenderCreateUpdateInfo(isset($row) ? $row : array(), $connect, isset($act) ? $act : ''); ?>

                    <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                        <?php echo ($act) ? '<button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="' . $actionBtnValue . '">' . $pageActionTitle . '</button>' : ''; ?>
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back" formnovalidate>Back</button>
                    </div>
                </form>
            </div>
        </div>
</body>
<script>
    //Initial Page And Action Value
    const page = "<?= $pageTitle ?>";
    const action = "<?php echo isset($act) ? $act : ''; ?>";
    const companyOptions = <?php echo json_encode($companyOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    checkCurrentPage(page, action);
    centerAlignment("formContainer");
    setButtonColor();
    

    const companyInput = document.getElementById('companyName');
    const companyIdInput = document.getElementById('company');
    const companySelectedNameInput = document.getElementById('company_selected_name');

    function normalizeText(text) {
        return String(text || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function closeAutocomplete(input) {
        const listId = input.getAttribute('data-list-id');
        if (!listId) return;
        const list = document.getElementById(listId);
        if (list) list.remove();
    }

    function syncCompanyIdByName() {
        const value = normalizeText(companyInput.value);
        const matched = (companyOptions || []).find((option) => normalizeText(option.name) === value);
        companyIdInput.value = matched ? String(optionId(matched.id)) : '';
        if (companySelectedNameInput) {
            companySelectedNameInput.value = matched ? matched.name : '';
        }
    }

    function optionId(id) {
        return parseInt(id, 10);
    }

    function findCompanyById(id) {
        const normalizedId = String(id || '');
        return (companyOptions || []).find((option) => String(option.id) === normalizedId) || null;
    }

    function applySelectedCompany(option) {
        if (!option) return;
        companyInput.value = option.name;
        companyIdInput.value = String(optionId(option.id));
        if (companySelectedNameInput) {
            companySelectedNameInput.value = option.name;
        }
    }

    function renderAutocompleteList() {
        closeAutocomplete(companyInput);
        if (!companyInput || companyInput.hasAttribute('readonly')) return;

        const keyword = normalizeText(companyInput.value);
        if (!keyword) return;

        const filtered = (companyOptions || [])
            .filter((option) => normalizeText(option.name).indexOf(keyword) !== -1)
            .slice(0, 20);

        if (filtered.length === 0) return;

        const listId = 'searchResult_companyName';
        companyInput.setAttribute('data-list-id', listId);

        const ul = document.createElement('ul');
        ul.className = 'searchResult';
        ul.id = listId;
        ul.style.width = companyInput.offsetWidth + 'px';

        filtered.forEach((option) => {
            const li = document.createElement('li');
            li.textContent = option.name;
            li.addEventListener('mousedown', function(e) {
                e.preventDefault();
                applySelectedCompany(option);
                closeAutocomplete(companyInput);
            });
            li.addEventListener('click', function(e) {
                e.preventDefault();
                applySelectedCompany(option);
                closeAutocomplete(companyInput);
            });
            ul.appendChild(li);
        });

        companyInput.after(ul);
    }

    if (companyInput) {
        companyInput.addEventListener('input', function() {
            companyIdInput.value = '';
            if (companySelectedNameInput) {
                companySelectedNameInput.value = '';
            }
            renderAutocompleteList();
        });
        
        companyInput.addEventListener('change', syncCompanyIdByName);
        
        companyInput.addEventListener('blur', function() {
            setTimeout(function() {
                closeAutocomplete(companyInput);
                syncCompanyIdByName();
            }, 120);
        });

        document.addEventListener('click', function(e) {
            const wrapper = companyInput.closest('.autocomplete');
            if (wrapper && !wrapper.contains(e.target)) {
                closeAutocomplete(companyInput);
            }
        });
    }

    const form = document.getElementById('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.value === 'back') {
                return;
            }

            var errorSpan = document.getElementById('errorSpan');
            var companyErrorSpan = document.getElementById('companyErrorSpan');
            var hasError = false;

            // Prevent duplicate messages by resetting each field's single error span.
            if (errorSpan) {
                errorSpan.textContent = '';
            }
            if (companyErrorSpan) {
                companyErrorSpan.textContent = '';
            }

            var brandValue = document.getElementById('currentDataName') ? document.getElementById('currentDataName').value : '';
            var companyValue = companyInput ? companyInput.value : '';

            if (normalizeText(brandValue) === '') {
                hasError = true;
                if (errorSpan) {
                    errorSpan.textContent = 'Brand Name is required!';
                }
            }

            // On submit, always resolve company against available options.
            let matched = null;
            if (companyIdInput.value) {
                matched = findCompanyById(companyIdInput.value);
            }

            if (!matched) {
                syncCompanyIdByName();
                if (companyIdInput.value) {
                    matched = findCompanyById(companyIdInput.value);
                }
            }

            if (matched) {
                applySelectedCompany(matched);
            }

            if (normalizeText(companyValue) === '') {
                hasError = true;
                if (companyErrorSpan) {
                    companyErrorSpan.textContent = 'Company is required!';
                }
            } else if (!matched) {
                hasError = true;
                if (companyErrorSpan) {
                    companyErrorSpan.textContent = 'Please select valid company!';
                }
            }

            if (hasError) {
                // Keep user on page and show errors instantly.
                e.preventDefault();
            }
        });
    }

</script>

</body>

</html>
