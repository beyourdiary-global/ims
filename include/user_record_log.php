<?php

if (!function_exists('urlServerLog')) {
    function urlServerLog($message, $context = array())
    {
        return;
    }
}

if (!function_exists('urlJsonResponse')) {
    function urlJsonResponse($payload)
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('urlFallbackResponse')) {
    function urlFallbackResponse($message, $isSuccess, $returnUrl = '')
    {
        $target = trim((string) $returnUrl);
        if ($target === '') {
            $target = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : (string) $_SERVER['PHP_SELF'];
        }

        echo '<script>alert(' . json_encode((string) $message) . ');location.href=' . json_encode($target) . ';</script>';
        exit;
    }
}

if (!function_exists('urlEsc')) {
    function urlEsc($conn, $val)
    {
        return mysqli_real_escape_string($conn, (string) $val);
    }
}

if (!function_exists('urlGetUserName')) {
    function urlGetUserName($connect, $uid)
    {
        static $cache = array();

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
}

if (!function_exists('urlWithin3Days')) {
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
}

if (!function_exists('urlGetUserRecordLogTableName')) {
    function urlGetUserRecordLogTableName()
    {
        return defined('USER_RECORD_LOG') ? USER_RECORD_LOG : 'user_record_log';
    }
}

if (!function_exists('urlGetUserRecordLogUploadWebDir')) {
    function urlGetUserRecordLogUploadWebDir()
    {
        $imgServer = defined('img_server') ? img_server : '';
        return trim((string) $imgServer, '/') . '/user_record_log/';
    }
}

if (!function_exists('urlEnsureUserRecordLogUploadDirectory')) {
    function urlEnsureUserRecordLogUploadDirectory()
    {
        $rootDir = defined('ROOT') ? ROOT : (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(__DIR__));
        $uploadWebDir = urlGetUserRecordLogUploadWebDir();
        $uploadDir = rtrim((string) $rootDir, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadWebDir);

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                error_log('Failed to create upload directory: ' . $uploadDir);
            }
        }

        return array(
            'upload_web_dir' => $uploadWebDir,
            'upload_dir' => $uploadDir,
        );
    }
}

if (!function_exists('urlFetchShopeeCustomerRow')) {
    function urlFetchShopeeCustomerRow($financeConnect, $customerId)
    {
        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            return array();
        }

        $tableName = defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info';
        $rst = getData('*', "id='" . $customerId . "'", 'LIMIT 1', $tableName, $financeConnect);
        if ($rst && $rst->num_rows > 0) {
            return $rst->fetch_assoc();
        }

        return array();
    }
}

if (!function_exists('urlGetShopeeCustomerLabel')) {
    function urlGetShopeeCustomerLabel($customerRow, $customerId = 0)
    {
        if (!empty($customerRow['buyer_username'])) {
            return (string) $customerRow['buyer_username'];
        }

        $customerId = (int) $customerId;
        return $customerId > 0 ? ('Customer #' . $customerId) : '';
    }
}

if (!function_exists('urlResolveUserRecordLogContext')) {
    function urlResolveUserRecordLogContext($connect, $financeConnect, $options = array())
    {
        $customerId = 0;
        if (array_key_exists('customer_id', $options)) {
            $customerId = (int) $options['customer_id'];
        } else if (isset($_REQUEST['customer_id'])) {
            $customerId = (int) $_REQUEST['customer_id'];
        }

        $returnUrl = isset($options['return_url']) ? trim((string) $options['return_url']) : '';
        if ($returnUrl === '' && isset($_REQUEST['return_url'])) {
            $returnUrl = trim((string) $_REQUEST['return_url']);
        }
        if ($returnUrl === '') {
            $returnUrl = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/user_record_log.php';
        }

        $ajaxUrl = isset($options['ajax_url']) ? trim((string) $options['ajax_url']) : (rtrim((string) $GLOBALS['SITEURL'], '/') . '/user_record_log.php');
        $customerLabel = isset($options['customer_label']) ? trim((string) $options['customer_label']) : '';
        $customerOnly = isset($options['customer_only']) ? (bool) $options['customer_only'] : ($customerId > 0);
        $customerRow = array();

        if ($customerId > 0) {
            $customerRow = urlFetchShopeeCustomerRow($financeConnect, $customerId);
            if (!empty($customerRow)) {
                if ($customerLabel === '') {
                    $customerLabel = urlGetShopeeCustomerLabel($customerRow, $customerId);
                }
            } else {
                $customerId = 0;
                $customerOnly = false;
            }
        }

        return array(
            'customer_id' => $customerId,
            'customer_label' => $customerLabel,
            'customer_row' => $customerRow,
            'return_url' => $returnUrl,
            'ajax_url' => $ajaxUrl,
            'customer_only' => $customerOnly,
        );
    }
}

if (!function_exists('urlBuildListHtml')) {
    function urlBuildListHtml($connect, $financeConnect, $tblName, $context = array())
    {
        $uploadWebDir = urlGetUserRecordLogUploadWebDir();
        $keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
        $filterDate = isset($_POST['filter_date']) ? trim((string) $_POST['filter_date']) : '';
        $filterUser = isset($_POST['filter_user']) ? trim((string) $_POST['filter_user']) : '';
        $filterAttachment = isset($_POST['filter_attachment']) ? trim((string) $_POST['filter_attachment']) : '';
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $pageSize = isset($_POST['page_size']) ? (int) $_POST['page_size'] : 10;
        $customerId = isset($context['customer_id']) ? (int) $context['customer_id'] : 0;
        $customerOnly = !empty($context['customer_only']);

        if ($page < 1) {
            $page = 1;
        }

        $allowedPageSizes = array(10, 25, 50, 100);
        if (!in_array($pageSize, $allowedPageSizes, true)) {
            $pageSize = 10;
        }

        $where = array("status='A'");
        if ($customerId > 0) {
            $where[] = "customer_id='" . $customerId . "'";
        } else if ($customerOnly) {
            return array(
                'count' => 0,
                'total' => 0,
                'page' => 1,
                'page_size' => $pageSize,
                'total_pages' => 1,
                'html' => '<div class="alert alert-secondary">No records / No results found</div>'
            );
        }

        if ($keyword !== '') {
            $where[] = "content LIKE '%" . urlEsc($financeConnect, $keyword) . "%'";
        }
        if ($filterDate !== '') {
            $where[] = "DATE(created_at)='" . urlEsc($financeConnect, $filterDate) . "'";
        }
        if ($filterUser !== '') {
            $where[] = "created_by='" . urlEsc($financeConnect, $filterUser) . "'";
        }
        if ($filterAttachment === 'Y') {
            $where[] = "IFNULL(attachment,'') <> ''";
        } else if ($filterAttachment === 'N') {
            $where[] = "IFNULL(attachment,'') = ''";
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) AS total_count FROM " . $tblName . " WHERE " . $whereSql;
        $countRst = mysqli_query($financeConnect, $countSql);
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
        $sql = "SELECT * FROM " . $tblName . " WHERE " . $whereSql . " ORDER BY created_at DESC, id DESC LIMIT " . $pageSize . " OFFSET " . $offset;
        $rst = mysqli_query($financeConnect, $sql);
        if (!$rst || $rst->num_rows === 0) {
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
            $recordId = isset($row['id']) ? (int) $row['id'] : 0;
            $content = isset($row['content']) ? $row['content'] : '';
            $attachment = isset($row['attachment']) ? $row['attachment'] : '';
            $createdAt = isset($row['created_at']) ? $row['created_at'] : '';
            $updatedAt = isset($row['updated_at']) ? $row['updated_at'] : '';
            $createdBy = urlGetUserName($connect, isset($row['created_by']) ? $row['created_by'] : '');
            $updatedBy = urlGetUserName($connect, isset($row['updated_by']) ? $row['updated_by'] : '');
            $canEdit = urlWithin3Days($createdAt);
            $editBtn = '';

            if ($canEdit) {
                $editBtn = '<button type="button" class="btn btn-sm btn-rounded btn-warning url-edit-btn" data-id="' . $recordId . '">Edit</button>';
            }

            $attachmentHtml = '<span class="text-muted">No attachment</span>';
            if ($attachment !== '') {
                $href = rtrim((string) $GLOBALS['SITEURL'], '/') . '/' . ltrim($uploadWebDir, '/') . rawurlencode($attachment);
                $attachmentHtml = '<a target="_blank" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">View Attachment</a>';
            }

            $html .= '<div class="card mb-3">';
            $html .= '  <div class="card-header d-flex justify-content-between align-items-center">';
            $html .= '    <div><strong>#' . $recordId . '</strong> <span class="ms-2 text-muted">Created: ' . htmlspecialchars((string) $createdAt, ENT_QUOTES, 'UTF-8') . ' by ' . htmlspecialchars((string) $createdBy, ENT_QUOTES, 'UTF-8') . '</span></div>';
            $html .= '    <div>';
            $html .= '      <button type="button" class="btn btn-sm btn-rounded btn-info text-white url-toggle-btn" data-target="url-body-' . $recordId . '">Collapse/Expand</button> ';
            $html .= $editBtn;
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
            'html' => $html,
        );
    }
}

if (!function_exists('urlHandleUserRecordLogRequest')) {
    function urlHandleUserRecordLogRequest($connect, $financeConnect, $options = array())
    {
        $tblName = isset($options['table_name']) ? $options['table_name'] : urlGetUserRecordLogTableName();
        $pageTitle = isset($options['page_title']) ? (string) $options['page_title'] : 'User Record Log';
        $context = urlResolveUserRecordLogContext($connect, $financeConnect, $options);
        $uploadMeta = urlEnsureUserRecordLogUploadDirectory();
        $uploadDir = $uploadMeta['upload_dir'];
        $urlAction = '';
        $urlIsFallback = false;

        if (isset($_POST['url_action'])) {
            $urlAction = trim((string) $_POST['url_action']);
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
            $urlAction = 'save';
            $urlIsFallback = true;
        }

        if ($urlAction === '') {
            return $context;
        }

        if ($urlAction === 'list') {
            $payload = urlBuildListHtml($connect, $financeConnect, $tblName, $context);
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

        if ($urlAction !== 'save') {
            if ($urlIsFallback) {
                urlFallbackResponse('Invalid action.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Invalid action.'));
        }

        $recordId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
        $content = isset($_POST['content']) ? trim((string) $_POST['content']) : '';
        if ($content === '') {
            if ($urlIsFallback) {
                urlFallbackResponse('Content is required.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Content is required.'));
        }

        $attachmentName = isset($_POST['existing_attachment']) ? trim((string) $_POST['existing_attachment']) : '';
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK && $_FILES['attachment']['size'] > 0) {
            $origName = $_FILES['attachment']['name'];
            $tmpPath = $_FILES['attachment']['tmp_name'];
            $fileSize = isset($_FILES['attachment']['size']) ? (int) $_FILES['attachment']['size'] : 0;
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = array('png', 'jpg', 'jpeg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt');
            $maxFileSize = 10 * 1024 * 1024;

            if (!in_array($ext, $allowed, true)) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Attachment format is not allowed.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Attachment format is not allowed.'));
            }
            if ($fileSize <= 0 || $fileSize > $maxFileSize) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Attachment size is invalid or exceeds 10MB.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Attachment size is invalid or exceeds 10MB.'));
            }

            $mimeByExt = array(
                'png' => array('image/png'),
                'jpg' => array('image/jpeg'),
                'jpeg' => array('image/jpeg'),
                'pdf' => array('application/pdf'),
                'txt' => array('text/plain', 'application/octet-stream'),
                'doc' => array('application/msword', 'application/octet-stream'),
                'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'),
                'xls' => array('application/vnd.ms-excel', 'application/octet-stream'),
                'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'),
            );
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo ? finfo_file($finfo, $tmpPath) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                $allowedMime = isset($mimeByExt[$ext]) ? $mimeByExt[$ext] : array();
                if ($detectedMime === false) {
                    $detectedMime = '';
                }
                if (!empty($allowedMime) && !in_array((string) $detectedMime, $allowedMime, true)) {
                    if ($urlIsFallback) {
                        urlFallbackResponse('Attachment MIME type is not allowed.', false, $context['return_url']);
                    }
                    urlJsonResponse(array('ok' => 0, 'message' => 'Attachment MIME type is not allowed.'));
                }
            }

            $newName = 'record_' . date('Ymd_His') . '_' . USER_ID . '_' . mt_rand(1000, 9999) . '.' . $ext;
            if (!move_uploaded_file($tmpPath, $uploadDir . $newName)) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Failed to upload attachment.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Failed to upload attachment.'));
            }

            $attachmentName = $newName;
        }

        if ($recordId > 0) {
            $currentRst = getData('*', "id='" . $recordId . "' AND status='A'", 'LIMIT 1', $tblName, $financeConnect);
            if (!$currentRst || $currentRst->num_rows === 0) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Record not found.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Record not found.'));
            }

            $currentRow = $currentRst->fetch_assoc();
            $currentCustomerId = isset($currentRow['customer_id']) ? (int) $currentRow['customer_id'] : 0;
            if (!empty($context['customer_only']) && (int) $context['customer_id'] !== $currentCustomerId) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Record not found.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Record not found.'));
            }
            if (!urlWithin3Days(isset($currentRow['created_at']) ? $currentRow['created_at'] : '')) {
                if ($urlIsFallback) {
                    urlFallbackResponse('This record can no longer be edited (more than 3 days).', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'This record can no longer be edited (more than 3 days).'));
            }

            $sql = "UPDATE " . $tblName . " SET
                content='" . urlEsc($financeConnect, $content) . "',
                attachment='" . urlEsc($financeConnect, $attachmentName) . "',
                updated_by='" . urlEsc($financeConnect, USER_ID) . "',
                updated_at=NOW()
                WHERE id='" . $recordId . "'";
            $ok = mysqli_query($financeConnect, $sql);

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

            $log = array(
                'log_act' => 'Edit',
                'cdate' => $GLOBALS['cdate'],
                'ctime' => $GLOBALS['ctime'],
                'uid' => USER_ID,
                'cby' => USER_ID,
                'query_rec' => $sql,
                'query_table' => $tblName,
                'oldval' => implodeWithComma($editOld),
                'changes' => implodeWithComma($editNew),
                'newval' => '',
                'act_msg' => function_exists('actMsgLog')
                    ? actMsgLog($recordId, $editFields, '', $editOld, $editNew, $tblName, 'Edit', (!empty($ok) ? '' : mysqli_error($financeConnect)))
                    : (USER_NAME . ' edited User Record Log [ID=' . $recordId . ']'),
                'page' => $pageTitle,
                'connect' => $connect,
            );
            audit_log($log);

            if (!$ok) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Failed to update record.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Failed to update record.'));
            }

            if ($urlIsFallback) {
                urlFallbackResponse('Record updated successfully.', true, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 1, 'message' => 'Record updated successfully.'));
        }

        $customerIdSql = 'NULL';
        if ((int) $context['customer_id'] > 0) {
            $customerIdSql = "'" . (int) $context['customer_id'] . "'";
        }

        $sql = "INSERT INTO " . $tblName . " (
                customer_id,
                content,
                attachment,
                created_by,
                created_at,
                updated_by,
                updated_at,
                status
            ) VALUES (
                " . $customerIdSql . ",
                '" . urlEsc($financeConnect, $content) . "',
                '" . urlEsc($financeConnect, $attachmentName) . "',
                '" . urlEsc($financeConnect, USER_ID) . "',
                NOW(),
                '" . urlEsc($financeConnect, USER_ID) . "',
                NOW(),
                'A'
            )";
        $ok = mysqli_query($financeConnect, $sql);
        $newId = (int) $financeConnect->insert_id;
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
                ? actMsgLog($newId, $addFields, $addNew, '', '', $tblName, 'Add', (!empty($ok) ? '' : mysqli_error($financeConnect)))
                : (USER_NAME . ' added User Record Log [ID=' . $newId . ']'),
            'page' => $pageTitle,
            'connect' => $connect,
        );
        audit_log($log);

        if (!$ok) {
            if ($urlIsFallback) {
                urlFallbackResponse('Failed to add record.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Failed to add record.'));
        }

        if ($urlIsFallback) {
            urlFallbackResponse('Record added successfully.', true, $context['return_url']);
        }
        urlJsonResponse(array('ok' => 1, 'message' => 'Record added successfully.'));
    }
}

if (!function_exists('urlRenderUserRecordLogModule')) {
    function urlRenderUserRecordLogModule($connect, $financeConnect, $options = array())
    {
        $tblName = isset($options['table_name']) ? $options['table_name'] : urlGetUserRecordLogTableName();
        $context = isset($options['context']) && is_array($options['context'])
            ? $options['context']
            : urlResolveUserRecordLogContext($connect, $financeConnect, $options);
        $sectionHeading = isset($options['section_heading']) ? (string) $options['section_heading'] : '';
        $showScopeNote = !isset($options['show_scope_note']) || (bool) $options['show_scope_note'];
        $pathReturn = isset($context['return_url']) ? (string) $context['return_url'] : '';
        $initialList = urlBuildListHtml($connect, $financeConnect, $tblName, $context);
        $config = array(
            'ajaxUrl' => isset($context['ajax_url']) ? (string) $context['ajax_url'] : (rtrim((string) $GLOBALS['SITEURL'], '/') . '/user_record_log.php'),
            'customerId' => isset($context['customer_id']) ? (int) $context['customer_id'] : 0,
            'pathReturn' => $pathReturn,
            'confirmationPageName' => 'User Record Log',
        );
        $configJson = json_encode($config);
        $moduleActionUrl = rtrim((string) $GLOBALS['SITEURL'], '/') . '/user_record_log.php';
        ?>
        <div class="user-record-log-module mt-4">
            <?php if ($sectionHeading !== '') { ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                    <h5 class="mb-0"><?php echo htmlspecialchars($sectionHeading, ENT_QUOTES, 'UTF-8'); ?></h5>
                    <?php if ($showScopeNote && !empty($context['customer_label'])) { ?>
                        <span class="text-muted small">Customer: <?php echo htmlspecialchars((string) $context['customer_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php } ?>
                </div>
            <?php } ?>

            <div id="url_alert" class="alert d-none"></div>

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Add / Edit Record</h5>
                    <form id="url_form" method="post" action="<?php echo htmlspecialchars($moduleActionUrl, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="url_action" value="save">
                        <input type="hidden" name="record_id" id="url_record_id" value="0">
                        <input type="hidden" name="existing_attachment" id="url_existing_attachment" value="">
                        <input type="hidden" name="customer_id" value="<?php echo isset($context['customer_id']) ? (int) $context['customer_id'] : 0; ?>">
                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($pathReturn, ENT_QUOTES, 'UTF-8'); ?>">

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
                    <h5 class="mb-3">Search &amp; Filter</h5>
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
                                $uSql = 'SELECT id,name,username FROM ' . $safeUserTable . " WHERE status='A' ORDER BY name ASC";
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
            <div id="url_list_container"><?php echo isset($initialList['html']) ? $initialList['html'] : ''; ?></div>
            <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
                <div id="url_paging_summary" class="dataTables_info" role="status" aria-live="polite"></div>
                <div id="url_pagination" class="dataTables_paginate paging_simple_numbers"></div>
            </div>
        </div>

        <script>
            window.__USER_RECORD_LOG_CONFIG = <?php echo $configJson ? $configJson : '{}'; ?>;
        </script>
        <script src="<?php echo htmlspecialchars(rtrim((string) $GLOBALS['SITEURL'], '/') . '/js/user_record_log.js?v=' . @filemtime(ROOT . '/js/user_record_log.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php
    }
}