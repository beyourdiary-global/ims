<?php
ob_start();
$pageTitle = "User Record Log";

include_once 'menuHeader.php';
include_once 'checkCurrentPagePin.php';

// Safe fallbacks to prevent PHP 8 Fatal Errors
$tblName = USER_RECORD_LOG;
$safeUserTable = USR_USER;
$imgServer = defined('img_server') ? img_server : '';
$rootDir = defined('ROOT') ? ROOT : $_SERVER['DOCUMENT_ROOT'];

$pinAccess = checkCurrentPin($connect, $pageTitle);
$uploadWebDir = trim((string) $imgServer, '/') . '/user_record_log/';
$uploadDir = rtrim((string) $rootDir, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadWebDir);
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>alert("You do not have permission to view this page.");location.href="' . $SITEURL . '/dashboard.php";</script>';
    exit;
}

$urlDebugFile = rtrim((string) $rootDir, '\\/') . DIRECTORY_SEPARATOR . 'user_record_log_debug.log';

function urlServerLog($message, $context = array())
{
    $file = isset($GLOBALS['urlDebugFile']) ? (string) $GLOBALS['urlDebugFile'] : '';
    if ($file === '') {
        return;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . (string) $message;
    if (!empty($context)) {
        $json = @json_encode($context);
        if ($json !== false) {
            $line .= ' ' . $json;
        }
    }
    $line .= PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND);
}

function urlJsonResponse($payload)
{
    while (ob_get_level() > 0) {
        @ob_clean();
        break;
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function urlFallbackResponse($message, $isSuccess)
{
    $safeMsg = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');
    $target = htmlspecialchars((string) $_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
    echo '<script>alert("' . $safeMsg . '");location.href="' . $target . '";</script>';
    exit;
}

function urlEsc($conn, $val)
{
    return mysqli_real_escape_string($conn, (string) $val);
}

function urlGetUserName($connect, $uid)
{
    static $cache = array(); // Store users in memory to prevent duplicate DB queries
    $uid = trim((string) $uid);
    if ($uid === '') {
        return '';
    }
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }
    $safeUserTable = defined('USR_USER') ? USR_USER : 'user';
    $rst = getData('name,username', "id='" . urlEsc($connect, $uid) . "'", 'LIMIT 1', $safeUserTable, $connect);
    if ($rst && $rst->num_rows > 0) {
        $row = $rst->fetch_assoc();
        if (!empty($row['name'])) {
            $cache[$uid] = $row['name'];
            return $row['name'];
        }
        if (!empty($row['username'])) {
            $cache[$uid] = $row['username'];
            return $row['username'];
        }
    }
    $cache[$uid] = $uid;
    return $uid;
}

function urlWithin3Days($createdAt)
{
    if (empty($createdAt)) {
        return false;
    }
    $createdTs = strtotime((string) $createdAt);
    if ($createdTs === false) {
        return false;
    }
    return (time() - $createdTs) <= (3 * 24 * 60 * 60);
}

function urlBuildListHtml($connect, $tblName, $pinAccess)
{
    $uploadWebDir = isset($GLOBALS['uploadWebDir']) ? (string) $GLOBALS['uploadWebDir'] : (trim((string) img_server, '/') . '/user_record_log/');

    $keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
    $filterDate = isset($_POST['filter_date']) ? trim((string) $_POST['filter_date']) : '';
    $filterUser = isset($_POST['filter_user']) ? trim((string) $_POST['filter_user']) : '';
    $filterAttachment = isset($_POST['filter_attachment']) ? trim((string) $_POST['filter_attachment']) : '';
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $pageSize = isset($_POST['page_size']) ? (int) $_POST['page_size'] : 10;

    if ($page < 1) {
        $page = 1;
    }

    $allowedPageSizes = array(10, 25, 50, 100);
    if (!in_array($pageSize, $allowedPageSizes, true)) {
        $pageSize = 10;
    }

    $where = array("status='A'");

    if ($keyword !== '') {
        $where[] = "content LIKE '%" . urlEsc($connect, $keyword) . "%'";
    }
    if ($filterDate !== '') {
        $where[] = "DATE(created_at)='" . urlEsc($connect, $filterDate) . "'";
    }
    if ($filterUser !== '') {
        $where[] = "created_by='" . urlEsc($connect, $filterUser) . "'";
    }
    if ($filterAttachment === 'Y') {
        $where[] = "IFNULL(attachment,'') <> ''";
    } else if ($filterAttachment === 'N') {
        $where[] = "IFNULL(attachment,'') = ''";
    }

    $whereSql = implode(' AND ', $where);
    $countSql = "SELECT COUNT(*) AS total_count FROM " . $tblName . " WHERE " . $whereSql;
    $countRst = mysqli_query($connect, $countSql);
    $totalCount = 0;
    if ($countRst && $countRst->num_rows > 0) {
        $countRow = $countRst->fetch_assoc();
        $totalCount = isset($countRow['total_count']) ? (int) $countRow['total_count'] : 0;
    }

    if ($totalCount <= 0) {
        return array(
            'count' => 0,
            'total' => 0,
            'page' => 1,
            'page_size' => $pageSize,
            'total_pages' => 1,
            'html' => '<div class="alert alert-secondary">No records / No results found</div>'
        );
    }

    $totalPages = (int) ceil($totalCount / $pageSize);
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $pageSize;
    // Use direct query here because getData() injects its own ORDER BY clause.
    $sql = "SELECT * FROM " . $tblName . " WHERE " . $whereSql . " ORDER BY created_at DESC, id DESC LIMIT " . $pageSize . " OFFSET " . $offset;
    $rst = mysqli_query($connect, $sql);

    if (!$rst || $rst->num_rows === 0) {
        if (!$rst) {
            urlServerLog('List query failed', array('error' => mysqli_error($connect), 'sql' => $sql));
        }
        return array(
            'count' => 0,
            'total' => $totalCount,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => $totalPages,
            'html' => '<div class="alert alert-secondary">No records / No results found</div>'
        );
    }

    $html = '';
    $count = 0;

    while ($row = $rst->fetch_assoc()) {
        $count++;
        $recordId = (int) $row['id'];
        $content = isset($row['content']) ? $row['content'] : '';
        $attachment = isset($row['attachment']) ? $row['attachment'] : '';
        $createdAt = isset($row['created_at']) ? $row['created_at'] : '';
        $updatedAt = isset($row['updated_at']) ? $row['updated_at'] : '';
        $createdBy = urlGetUserName($connect, isset($row['created_by']) ? $row['created_by'] : '');
        $updatedBy = urlGetUserName($connect, isset($row['updated_by']) ? $row['updated_by'] : '');

        $canEdit = urlWithin3Days($createdAt);
        $editBtn = '';
        if ($canEdit && isActionAllowed('Edit', $pinAccess)) {
            $editBtn = '<button type="button" class="btn btn-sm btn-rounded btn-warning url-edit-btn" data-id="' . $recordId . '">Edit</button>';
        }

        $attachmentHtml = '<span class="text-muted">No attachment</span>';
        if ($attachment !== '') {
            $href = $GLOBALS['SITEURL'] . '/' . $uploadWebDir . rawurlencode($attachment);
            $attachmentHtml = '<a target="_blank" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">View Attachment</a>';
        }

        $html .= '<div class="card mb-3">';
        $html .= '  <div class="card-header d-flex justify-content-between align-items-center">';
        $html .= '    <div><strong>#' . $recordId . '</strong> <span class="ms-2 text-muted">Created: ' . htmlspecialchars((string) $createdAt, ENT_QUOTES, 'UTF-8') . ' by ' . htmlspecialchars((string) $createdBy, ENT_QUOTES, 'UTF-8') . '</span></div>';
        $html .= '    <div>';
        $html .= '      <button type="button" class="btn btn-sm btn-rounded btn-info text-white url-toggle-btn" data-target="url-body-' . $recordId . '">Collapse/Expand</button> ';
        $html .=        $editBtn;
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '  <div id="url-body-' . $recordId . '" class="card-body">';
        $html .= '    <div class="mb-2"><strong>Content:</strong><br>' . nl2br(htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8')) . '</div>';
        $html .= '    <div class="mb-2"><strong>Attachment:</strong> ' . $attachmentHtml . '</div>';
        $html .= '    <div class="small text-muted">Updated: ' . htmlspecialchars((string) $updatedAt, ENT_QUOTES, 'UTF-8') . ' by ' . htmlspecialchars((string) $updatedBy, ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '    <input type="hidden" class="url-edit-content" value="' . htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '    <input type="hidden" class="url-edit-attachment" value="' . htmlspecialchars((string) $attachment, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '  </div>';
        $html .= '</div>';
    }

    return array(
        'count' => $count,
        'total' => $totalCount,
        'page' => $page,
        'page_size' => $pageSize,
        'total_pages' => $totalPages,
        'html' => $html
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hasUpload = (isset($_FILES['attachment']) && is_array($_FILES['attachment'])) ? 1 : 0;
    urlServerLog('Incoming POST request', array(
        'post_keys' => array_keys($_POST),
        'has_upload' => $hasUpload,
        'content_len' => isset($_POST['content']) ? strlen((string) $_POST['content']) : 0,
        'record_id' => isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0,
    ));
}

$urlAction = '';
$urlIsFallback = false;
if (isset($_POST['url_action'])) {
    $urlAction = trim((string) $_POST['url_action']);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    // Fallback for non-AJAX form submissions where JS may fail before adding url_action.
    $urlAction = 'save';
    $urlIsFallback = true;
    urlServerLog('Fallback activated: POST without url_action treated as save', array(
        'post_keys' => array_keys($_POST),
    ));
}

if ($urlAction !== '') {
    urlServerLog('Processing action', array('url_action' => $urlAction));

    if ($urlAction === 'list') {
        if (!isActionAllowed('View', $pinAccess)) {
            urlServerLog('Blocked list: no view permission');
            urlJsonResponse(array('ok' => 0, 'message' => 'No permission.'));
        }
        $payload = urlBuildListHtml($connect, $tblName, $pinAccess);
        urlServerLog('List completed', array('count' => isset($payload['count']) ? (int) $payload['count'] : 0));
        urlJsonResponse(array(
            'ok' => 1,
            'count' => isset($payload['count']) ? (int) $payload['count'] : 0,
            'total' => isset($payload['total']) ? (int) $payload['total'] : 0,
            'page' => isset($payload['page']) ? (int) $payload['page'] : 1,
            'page_size' => isset($payload['page_size']) ? (int) $payload['page_size'] : 10,
            'total_pages' => isset($payload['total_pages']) ? (int) $payload['total_pages'] : 1,
            'html' => isset($payload['html']) ? $payload['html'] : ''
        ));
    }

    if ($urlAction === 'save') {
        if (!isActionAllowed('Add', $pinAccess) && !isActionAllowed('Edit', $pinAccess)) {
            urlServerLog('Blocked save: no add/edit permission');
            if ($urlIsFallback) {
                urlFallbackResponse('No permission.', false);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'No permission.'));
        }

        $recordId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
        $content = isset($_POST['content']) ? trim((string) $_POST['content']) : '';
        urlServerLog('Save request payload', array(
            'record_id' => $recordId,
            'content_len' => strlen((string) $content),
            'existing_attachment' => isset($_POST['existing_attachment']) ? (string) $_POST['existing_attachment'] : '',
        ));
        if ($content === '') {
            urlServerLog('Save rejected: content empty');
            if ($urlIsFallback) {
                urlFallbackResponse('Content is required.', false);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Content is required.'));
        }

        $attachmentName = '';
        if (isset($_POST['existing_attachment'])) {
            $attachmentName = trim((string) $_POST['existing_attachment']);
        }

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK && $_FILES['attachment']['size'] > 0) {
            $origName = $_FILES['attachment']['name'];
            $tmpPath = $_FILES['attachment']['tmp_name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = array('png', 'jpg', 'jpeg', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt');
            urlServerLog('Attachment upload attempt', array(
                'name' => (string) $origName,
                'size' => isset($_FILES['attachment']['size']) ? (int) $_FILES['attachment']['size'] : 0,
                'ext' => (string) $ext,
            ));
            if (!in_array($ext, $allowed)) {
                urlServerLog('Attachment rejected: extension not allowed', array('ext' => (string) $ext));
                if ($urlIsFallback) {
                    urlFallbackResponse('Attachment format is not allowed.', false);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Attachment format is not allowed.'));
            }
            $newName = 'record_' . date('Ymd_His') . '_' . USER_ID . '_' . mt_rand(1000, 9999) . '.' . $ext;
            if (!move_uploaded_file($tmpPath, $uploadDir . $newName)) {
                urlServerLog('Attachment upload failed', array('target' => $uploadDir . $newName));
                if ($urlIsFallback) {
                    urlFallbackResponse('Failed to upload attachment.', false);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Failed to upload attachment.'));
            }
            $attachmentName = $newName;
            urlServerLog('Attachment upload success', array('saved_as' => $newName));
        }

        if ($recordId > 0) {
            if (!isActionAllowed('Edit', $pinAccess)) {
                urlJsonResponse(array('ok' => 0, 'message' => 'No edit permission.'));
            }

            $currentRst = getData('*', "id='" . $recordId . "' AND status='A'", 'LIMIT 1', $tblName, $connect);
            if (!$currentRst || $currentRst->num_rows === 0) {
                urlJsonResponse(array('ok' => 0, 'message' => 'Record not found.'));
            }
            $currentRow = $currentRst->fetch_assoc();
            if (!urlWithin3Days(isset($currentRow['created_at']) ? $currentRow['created_at'] : '')) {
                urlJsonResponse(array('ok' => 0, 'message' => 'This record can no longer be edited (more than 3 days).'));
            }

            $sql = "UPDATE " . $tblName . " SET
                content='" . urlEsc($connect, $content) . "',
                attachment='" . urlEsc($connect, $attachmentName) . "',
                updated_by='" . urlEsc($connect, USER_ID) . "',
                updated_at=NOW()
                WHERE id='" . $recordId . "'";
            $ok = mysqli_query($connect, $sql);
            if (!$ok) {
                urlServerLog('SQL update failed', array('error' => mysqli_error($connect), 'sql' => $sql));
            } else {
                urlServerLog('SQL update success', array('record_id' => $recordId));
            }

            $editFields = array();
            $editOld = array();
            $editNew = array();

            $oldContent = isset($currentRow['content']) ? (string) $currentRow['content'] : '';
            $oldAttachment = isset($currentRow['attachment']) ? (string) $currentRow['attachment'] : '';

            if ($oldContent !== $content) {
                $editFields[] = 'content';
                $editOld[] = $oldContent;
                $editNew[] = $content;
            }
            if ($oldAttachment !== $attachmentName) {
                $editFields[] = 'attachment';
                $editOld[] = $oldAttachment;
                $editNew[] = $attachmentName;
            }

            $editOldStr = implodeWithComma($editOld);
            $editNewStr = implodeWithComma($editNew);

            $log = array(
                'log_act' => 'Edit',
                'cdate' => $GLOBALS['cdate'],
                'ctime' => $GLOBALS['ctime'],
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => $sql,
                'query_table' => $tblName,
                'oldval' => $editOldStr,
                'changes' => $editNewStr,
                'newval' => '',
                'act_msg' => function_exists('actMsgLog')
                    ? actMsgLog($recordId, $editFields, '', $editOld, $editNew, $tblName, 'Edit', (!empty($ok) ? '' : mysqli_error($connect)))
                    : (USER_NAME . " edited User Record Log [ID=" . $recordId . "]"),
                'page' => $pageTitle,
                'connect' => $connect,
            );
            audit_log($log);

            if (!$ok) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Failed to update record.', false);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Failed to update record.'));
            }

            if ($urlIsFallback) {
                urlFallbackResponse('Record updated successfully.', true);
            }
            urlJsonResponse(array('ok' => 1, 'message' => 'Record updated successfully.'));
        }

        if (!isActionAllowed('Add', $pinAccess)) {
            if ($urlIsFallback) {
                urlFallbackResponse('No add permission.', false);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'No add permission.'));
        }

        $sql = "INSERT INTO " . $tblName . " (content, attachment, created_by, created_at, updated_by, updated_at, status)
            VALUES (
                '" . urlEsc($connect, $content) . "',
                '" . urlEsc($connect, $attachmentName) . "',
                '" . urlEsc($connect, USER_ID) . "',
                NOW(),
                '" . urlEsc($connect, USER_ID) . "',
                NOW(),
                'A'
            )";
        $ok = mysqli_query($connect, $sql);
        if (!$ok) {
            urlServerLog('SQL insert failed', array('error' => mysqli_error($connect), 'sql' => $sql));
        } else {
            urlServerLog('SQL insert success', array('insert_id' => (int) $connect->insert_id));
        }

        $newId = $connect->insert_id;
        $addFields = array('content', 'attachment');
        $addNew = array($content, $attachmentName);

        $log = array(
            'log_act' => 'Add',
            'cdate' => $GLOBALS['cdate'],
            'ctime' => $GLOBALS['ctime'],
            'uid' => USER_ID,
            'cby' => USER_ID,
            'query_rec' => $sql,
            'query_table' => $tblName,
            'oldval' => '',
            'changes' => '',
            'newval' => implodeWithComma($addNew),
            'act_msg' => function_exists('actMsgLog')
                ? actMsgLog($newId, $addFields, $addNew, '', '', $tblName, 'Add', (!empty($ok) ? '' : mysqli_error($connect)))
                : (USER_NAME . " added User Record Log [ID=" . $newId . "]"),
            'page' => $pageTitle,
            'connect' => $connect,
        );
        audit_log($log);

        if (!$ok) {
            if ($urlIsFallback) {
                urlFallbackResponse('Failed to add record.', false);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Failed to add record.'));
        }

        if ($urlIsFallback) {
            urlFallbackResponse('Record added successfully.', true);
        }
        urlJsonResponse(array('ok' => 1, 'message' => 'Record added successfully.'));
    }

    urlServerLog('Invalid action received', array('url_action' => $urlAction));
    if ($urlIsFallback) {
        urlFallbackResponse('Invalid action.', false);
    }
    urlJsonResponse(array('ok' => 0, 'message' => 'Invalid action.'));
}

$urlInitialList = urlBuildListHtml($connect, $tblName, $pinAccess);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
<div class="page-load-cover" style="display: block !important;">
    <div class="container-fluid d-flex justify-content-center mt-3">
        <div class="col-12 col-md-11">
            <div class="d-flex flex-column mb-3">
                <div class="row">
                    <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= $pageTitle ?></p>
                </div>
                <div class="row">
                    <div class="col-12 d-flex justify-content-between flex-wrap align-items-center">
                        <h2><?= $pageTitle ?></h2>
                    </div>
                </div>
            </div>

            <div id="url_alert" class="alert d-none"></div>

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Add / Edit Record</h5>
                    <form id="url_form" method="post" enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="record_id" id="url_record_id" value="0">
                        <input type="hidden" name="existing_attachment" id="url_existing_attachment" value="">

                        <div class="mb-3">
                            <label class="form-label" for="url_content">Content</label>
                            <textarea class="form-control" id="url_content" name="content" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="url_attachment">Attachment</label>
                            <input class="form-control" type="file" id="url_attachment" name="attachment">
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-sm btn-rounded btn-primary" id="url_submit_btn">Save Record</button>
                            <button type="button" class="btn btn-sm btn-rounded btn-secondary" id="url_cancel_edit_btn" style="display:none;">Cancel Edit</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Search & Filter</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="url_keyword">Keyword (content)</label>
                            <input class="form-control" type="text" id="url_keyword" placeholder="Type keyword...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="url_filter_date">Date</label>
                            <input class="form-control" type="date" id="url_filter_date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="url_filter_user">User</label>
                            <select class="form-control" id="url_filter_user">
                                <option value="">All Users</option>
                                <?php
                                $safeUserTable = defined('USR_USER') ? USR_USER : 'user';
                                $uSql = "SELECT id,name,username FROM " . $safeUserTable . " WHERE status='A' ORDER BY name ASC";
                                $uRst = mysqli_query($connect, $uSql);
                                if ($uRst && $uRst->num_rows > 0) {
                                    while ($u = $uRst->fetch_assoc()) {
                                        $uLabel = !empty($u['name']) ? $u['name'] : $u['username'];
                                        echo '<option value="' . htmlspecialchars((string) $u['id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $uLabel, ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="url_filter_attachment">Has Attachment</label>
                            <select class="form-control" id="url_filter_attachment">
                                <option value="">All</option>
                                <option value="Y">Yes</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-rounded btn-info text-white" id="url_apply_filter_btn">Apply</button>
                            <button type="button" class="btn btn-sm btn-rounded btn-secondary" id="url_reset_filter_btn">Reset</button>
                            <button type="button" class="btn btn-sm btn-rounded btn-outline-primary" id="url_expand_all_btn">Expand All</button>
                            <button type="button" class="btn btn-sm btn-rounded btn-outline-primary" id="url_collapse_all_btn">Collapse All</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                <div class="dataTables_length" id="url_dataTables_length">
                    <label class="mb-0">Show
                        <select id="url_page_size" aria-controls="url_list_container" class="form-select form-select-sm d-inline-block w-auto mx-2">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        entries
                    </label>
                </div>
            </div>

            <div id="url_loading" class="alert alert-info d-none">Loading...</div>
            <div id="url_list_container"><?= isset($urlInitialList['html']) ? $urlInitialList['html'] : '' ?></div>
            <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
                <div id="url_paging_summary" class="dataTables_info" role="status" aria-live="polite"></div>
                <div id="url_pagination" class="dataTables_paginate paging_simple_numbers"></div>
            </div>
        </div>
    </div>
</div>

<script>
        window.__USER_RECORD_LOG_CONFIG = {
                ajaxUrl: "<?= $SITEURL ?>/user_record_log.php"
        };
</script>
<script src="<?= $SITEURL ?>/js/user_record_log.js?v=<?= @filemtime(ROOT . '/js/user_record_log.js') ?>"></script>
</body>
</html>