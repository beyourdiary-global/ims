<?php
$pageTitle = "Brand";

include 'menuHeader.php';
include 'checkCurrentPagePin.php';

$tblName = BRAND;

//Current Page Action And Data ID
$dataID = !empty(input('id')) ? input('id') : post('id');
$act = !empty(input('act')) ? input('act') : post('act');
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';

//Page Redirect Link , Clean LocalStorage , Error Alert Msg 
$redirect_page = $SITEURL . '/brand_table.php';
$redirectLink = ("<script>location.href = '$redirect_page';</script>");
$clearLocalStorage = '<script>localStorage.clear();</script>';

//Check a current page pin is exist or not
$pageAction = getPageAction($act);
$pageActionTitle = $pageAction . " " . $pageTitle;
$pinAccess = checkCurrentPin($connect, $pageTitle);

//Checking The Page ID , Action , Pin Access Exist Or Not
if (!($dataID) && !($act) || !isActionAllowed($pageAction, $pinAccess))
    echo $redirectLink;

//Get The Data From Database
$row = [];
$rst = false;
if ($dataID) {
    $rst = getData('*', "id = '$dataID'", '', $tblName, $connect);
}

$companyOptions = [];
$companyResult = mysqli_query($connect, "SELECT id, name FROM " . COMPANY . " WHERE status = 'A' ORDER BY name ASC");
if ($companyResult) {
    while ($companyRow = $companyResult->fetch_assoc()) {
        $companyOptions[] = $companyRow;
    }
}

//Checking Data Error When Retrieved From Database
if ($dataID && (!$rst || !($row = $rst->fetch_assoc())) && $act != 'I') {
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
            $company = postSpaceFilter('company');
            $companyName = postSpaceFilter('companyName');
            $dataRemark = postSpaceFilter('currentDataRemark');

            $datafield = $oldvalarr = $chgvalarr = $newvalarr = array();

            if (isDuplicateRecord("name", $currentDataName, $tblName, $connect, $dataID)) {
                $err = "Duplicate record found for " . $pageTitle . " name.";
                break;
            }

            if (!$company || !is_numeric($company) || !isRecordExist(COMPANY, 'id', $company, $connect)) {
                $err = "Please select a company.";
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
                        $safeID = (int)$dataID; // Cast ID to int for safety

                        $query = "UPDATE " . $tblName . " SET name ='$safeName', company ='$safeCompany', remark ='$safeRemark', update_date = curdate(), update_time = curtime(), update_by ='" . USER_ID . "' WHERE id = '$safeID'";
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

                    <div class="form-group mb-3 autocomplete">
                        <label class="form-label" for="companyName">Company<span class="requireRed">*</span></label>
                        <input class="form-control" type="text" id="companyName" name="companyName" value="<?= htmlspecialchars($selectedCompanyName) ?>" <?php if ($act == '') echo 'readonly' ?> autocomplete="off">
                        <input type="hidden" id="company" name="company" value="<?= isset($row['company']) ? htmlspecialchars($row['company']) : '' ?>">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" for="currentDataRemark"><?php echo $pageTitle ?> Remark</label>
                        <textarea class="form-control" name="currentDataRemark" id="currentDataRemark" rows="3" <?php if ($act == '') echo 'readonly' ?>><?php if (isset($row['remark'])) echo $row['remark'] ?></textarea>
                    </div>

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
    var page = "<?= $pageTitle ?>";
    var action = "<?php echo isset($act) ? $act : ''; ?>";
    var companyOptions = <?php echo json_encode($companyOptions); ?>;

    checkCurrentPage(page, action);
    centerAlignment("formContainer");
    setButtonColor();
    preloader(300);

    const companyInput = document.getElementById('companyName');
    const companyIdInput = document.getElementById('company');

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
    }

    function optionId(id) {
        return parseInt(id, 10);
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
                companyInput.value = option.name;
                companyIdInput.value = String(optionId(option.id));
                closeAutocomplete(companyInput);
            });
            ul.appendChild(li);
        });

        companyInput.after(ul);
    }

    if (companyInput) {
        companyInput.addEventListener('input', function() {
            companyIdInput.value = '';
            renderAutocompleteList();
        });
        companyInput.addEventListener('change', syncCompanyIdByName);
        companyInput.addEventListener('blur', function() {
            setTimeout(function() {
                closeAutocomplete(companyInput);
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
    if (form && action) {
        form.addEventListener('submit', function(event) {
            if (event.submitter && event.submitter.value === 'back') {
                return;
            }

            syncCompanyIdByName();
            if (!companyIdInput.value) {
                event.preventDefault();
                const errorSpan = document.getElementById('errorSpan');
                if (errorSpan) {
                    errorSpan.textContent = 'Please select a valid company.';
                }
            }
        });
    }
</script>

</body>

</html>