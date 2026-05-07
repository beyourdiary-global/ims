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

if (!function_exists('urlGetUserRecordLogCustomerColumn')) {
    function urlGetUserRecordLogCustomerColumn($dbConnect, $tableName, $preferredColumn = '')
    {
        static $cache = array();

        $allowedColumns = array('cust_id', 'shopee_cust_id', 'facebook_cust_id', 'website_cust_id', 'lazada_cust_id', 'urbanism_member_id');
        $preferredColumn = trim((string) $preferredColumn);
        if (!in_array($preferredColumn, $allowedColumns, true)) {
            $preferredColumn = '';
        }

        if (!($dbConnect instanceof mysqli)) {
            return $preferredColumn !== '' ? $preferredColumn : 'shopee_cust_id';
        }

        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tableName);
        if ($safeTable === '') {
            return $preferredColumn !== '' ? $preferredColumn : 'shopee_cust_id';
        }

        $cacheKey = spl_object_hash($dbConnect) . '|' . $safeTable . '|' . $preferredColumn;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $candidateColumns = array();
        if ($preferredColumn !== '') {
            $candidateColumns[] = $preferredColumn;
        }
        foreach ($allowedColumns as $allowedColumn) {
            if (!in_array($allowedColumn, $candidateColumns, true)) {
                $candidateColumns[] = $allowedColumn;
            }
        }

        foreach ($candidateColumns as $candidateColumn) {
            $columnRst = mysqli_query($dbConnect, "SHOW COLUMNS FROM `" . $safeTable . "` LIKE '" . $candidateColumn . "'");
            if ($columnRst && $columnRst->num_rows > 0) {
                $cache[$cacheKey] = $candidateColumn;
                return $candidateColumn;
            }
        }

        $legacyCustomerIdRst = mysqli_query($dbConnect, "SHOW COLUMNS FROM `" . $safeTable . "` LIKE 'customer_id'");
        if ($legacyCustomerIdRst && $legacyCustomerIdRst->num_rows > 0) {
            $cache[$cacheKey] = 'customer_id';
            return 'customer_id';
        }

        $legacyCustIdRst = mysqli_query($dbConnect, "SHOW COLUMNS FROM `" . $safeTable . "` LIKE 'cust_id'");
        if ($legacyCustIdRst && $legacyCustIdRst->num_rows > 0) {
            $cache[$cacheKey] = 'cust_id';
            return 'cust_id';
        }

        $fallbackColumn = $preferredColumn !== '' ? $preferredColumn : 'shopee_cust_id';
        $cache[$cacheKey] = $fallbackColumn;
        return $fallbackColumn;
    }
}

if (!function_exists('urlSanitizeUserRecordLogCustomerColumn')) {
    function urlSanitizeUserRecordLogCustomerColumn($column)
    {
        $column = trim((string) $column);
        $allowedColumns = array('cust_id', 'shopee_cust_id', 'facebook_cust_id', 'website_cust_id', 'lazada_cust_id', 'urbanism_member_id');
        if (in_array($column, $allowedColumns, true)) {
            return $column;
        }

        return '';
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
        $customerColumn = '';
        if (isset($options['customer_column'])) {
            $customerColumn = urlSanitizeUserRecordLogCustomerColumn($options['customer_column']);
        } else if (isset($_REQUEST['customer_column'])) {
            $customerColumn = urlSanitizeUserRecordLogCustomerColumn($_REQUEST['customer_column']);
        }
        $customerOnly = isset($options['customer_only']) ? (bool) $options['customer_only'] : ($customerId > 0);
        $customerRow = array();
        $customerLookupConnect = isset($options['customer_lookup_connect']) && ($options['customer_lookup_connect'] instanceof mysqli)
            ? $options['customer_lookup_connect']
            : $financeConnect;

        if ($customerId > 0 && $customerLabel === '' && $customerLookupConnect instanceof mysqli) {
            $customerRow = urlFetchShopeeCustomerRow($customerLookupConnect, $customerId);
            if (!empty($customerRow) && $customerLabel === '') {
                $customerLabel = urlGetShopeeCustomerLabel($customerRow, $customerId);
            }
        }

        if ($customerId > 0 && $customerLabel === '') {
            $customerLabel = 'Customer #' . $customerId;
        }

        return array(
            'customer_id' => $customerId,
            'customer_label' => $customerLabel,
            'customer_column' => $customerColumn,
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
        $dbConnect = $financeConnect instanceof mysqli ? $financeConnect : $connect;
        $uploadWebDir = urlGetUserRecordLogUploadWebDir();
        $keyword = isset($_POST['keyword']) ? trim((string) $_POST['keyword']) : '';
        $filterDate = isset($_POST['filter_date']) ? trim((string) $_POST['filter_date']) : '';
        $filterUser = isset($_POST['filter_user']) ? trim((string) $_POST['filter_user']) : '';
        $filterAttachment = isset($_POST['filter_attachment']) ? trim((string) $_POST['filter_attachment']) : '';
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
        $pageSize = isset($_POST['page_size']) ? (int) $_POST['page_size'] : 10;
        $customerId = isset($context['customer_id']) ? (int) $context['customer_id'] : 0;
        $customerColumn = isset($context['customer_column']) ? trim((string) $context['customer_column']) : 'cust_id';
        if ($customerColumn === '') {
            $customerColumn = 'cust_id';
        }
        $customerOnly = !empty($context['customer_only']);

        if ($page < 1) {
            $page = 1;
        }

        $allowedPageSizes = array(10, 25, 50, 100, -1);
        if (!in_array($pageSize, $allowedPageSizes, true)) {
            $pageSize = 10;
        }

        $where = array("status='A'");
        if ($customerId > 0) {
            $where[] = $customerColumn . "='" . $customerId . "'";
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
            $where[] = "content LIKE '%" . urlEsc($dbConnect, $keyword) . "%'";
        }
        if ($filterDate !== '') {
            $where[] = "DATE(created_at)='" . urlEsc($dbConnect, $filterDate) . "'";
        }
        if ($filterUser !== '') {
            $where[] = "created_by='" . urlEsc($dbConnect, $filterUser) . "'";
        }
        if ($filterAttachment === 'Y') {
            $where[] = "IFNULL(attachment,'') <> ''";
        } else if ($filterAttachment === 'N') {
            $where[] = "IFNULL(attachment,'') = ''";
        }

        $whereSql = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) AS total_count FROM " . $tblName . " WHERE " . $whereSql;
        $countRst = mysqli_query($dbConnect, $countSql);
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

        if ($pageSize === -1) {
            $page = 1;
            $totalPages = 1;
            $offset = 0;
            $effectivePageSize = $totalCount;
            $sql = "SELECT * FROM " . $tblName . " WHERE " . $whereSql . " ORDER BY created_at DESC, id DESC";
        } else {
            $totalPages = (int) ceil($totalCount / $pageSize);
            if ($totalPages < 1) {
                $totalPages = 1;
            }
            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $offset = ($page - 1) * $pageSize;
            $effectivePageSize = $pageSize;
            $sql = "SELECT * FROM " . $tblName . " WHERE " . $whereSql . " ORDER BY created_at DESC, id DESC LIMIT " . $pageSize . " OFFSET " . $offset;
        }
        $rst = mysqli_query($dbConnect, $sql);
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
        $displayNo = $offset + 1;
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
                $editBtn = '<button type="button" class="btn btn-sm btn-rounded btn-warning url-edit-btn" data-id="' . $recordId . '">Edit User Log</button>';
            }

            $isSameAuditInfo = (
                trim((string) $createdAt) === trim((string) $updatedAt) &&
                trim((string) $createdBy) === trim((string) $updatedBy)
            );

            $createdMeta = 'Created: ' . htmlspecialchars((string) $createdAt, ENT_QUOTES, 'UTF-8') . ' by ' . htmlspecialchars((string) $createdBy, ENT_QUOTES, 'UTF-8');
            $updatedMeta = 'Updated: ' . htmlspecialchars((string) $updatedAt, ENT_QUOTES, 'UTF-8') . ' by ' . htmlspecialchars((string) $updatedBy, ENT_QUOTES, 'UTF-8');
            $auditMeta = $createdMeta;
            if (!$isSameAuditInfo && trim((string) $updatedAt) !== '') {
                $auditMeta .= ' <span class="url-meta-sep">|</span> ' . $updatedMeta;
            }

            $attachmentButtonHtml = '';
            if ($attachment !== '') {
                $href = rtrim((string) $GLOBALS['SITEURL'], '/') . '/' . ltrim($uploadWebDir, '/') . rawurlencode($attachment);
                $attachmentButtonHtml = '<button type="button" class="btn btn-sm btn-rounded btn-outline-primary url-view-attachment-btn" data-url="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" data-file="' . htmlspecialchars((string) $attachment, ENT_QUOTES, 'UTF-8') . '">View Attachment</button>';
            }

            $rowClass = ($count % 2 === 1) ? ' url-row-odd' : ' url-row-even';
            $html .= '<div class="card mb-3 url-log-row' . $rowClass . '">';
            $html .= '  <div class="card-header">';
            $html .= '    <div><strong>#' . $displayNo . '</strong> <span class="ms-2 text-muted small">' . $auditMeta . '</span></div>';
            $html .= '    <div class="d-flex align-items-center gap-2 mt-2">';
            $html .= '      <button type="button" class="btn btn-sm btn-rounded btn-info text-white url-toggle-btn" data-target="url-body-' . $recordId . '">Collapse/Expand</button>';
            $html .= $editBtn;
            $html .= '    </div>';
            $html .= '  </div>';
            $html .= '  <div id="url-body-' . $recordId . '" class="card-body">';
            $html .= '    <div class="url-content-row d-flex justify-content-between align-items-start gap-2 flex-wrap">';
            $html .= '      <div class="mb-0"><strong>Content:</strong><br>' . nl2br(htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8')) . '</div>';
            if ($attachmentButtonHtml !== '') {
                $html .= '      <div class="url-attachment-action ms-auto">' . $attachmentButtonHtml . '</div>';
            }
            $html .= '    </div>';
            $html .= '    <input type="hidden" class="url-edit-content" value="' . htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '    <input type="hidden" class="url-edit-attachment" value="' . htmlspecialchars((string) $attachment, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '  </div>';
            $html .= '</div>';

            $displayNo++;
        }

        return array(
            'count' => $count,
            'total' => $totalCount,
            'page' => $page,
            'page_size' => $pageSize,
            'effective_page_size' => $effectivePageSize,
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
        $dbConnect = $connect instanceof mysqli ? $connect : $financeConnect;
        $requestedCustomerColumn = '';
        if (isset($options['customer_column'])) {
            $requestedCustomerColumn = urlSanitizeUserRecordLogCustomerColumn($options['customer_column']);
        } else if (isset($_REQUEST['customer_column'])) {
            $requestedCustomerColumn = urlSanitizeUserRecordLogCustomerColumn($_REQUEST['customer_column']);
        }
        $customerColumn = urlGetUserRecordLogCustomerColumn($dbConnect, $tblName, $requestedCustomerColumn);
        $context = urlResolveUserRecordLogContext($connect, $financeConnect, $options);
        $context['customer_column'] = $customerColumn;
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
            $payload = urlBuildListHtml($connect, $dbConnect, $tblName, $context);
            urlJsonResponse(array(
                'ok' => 1,
                'count' => isset($payload['count']) ? (int) $payload['count'] : 0,
                'total' => isset($payload['total']) ? (int) $payload['total'] : 0,
                'page' => isset($payload['page']) ? (int) $payload['page'] : 1,
                'page_size' => isset($payload['page_size']) ? (int) $payload['page_size'] : 10,
                'effective_page_size' => isset($payload['effective_page_size']) ? (int) $payload['effective_page_size'] : 10,
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
            $currentRst = getData('*', "id='" . $recordId . "' AND status='A'", 'LIMIT 1', $tblName, $dbConnect);
            if (!$currentRst || $currentRst->num_rows === 0) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Record not found.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Record not found.'));
            }

            $currentRow = $currentRst->fetch_assoc();
            $currentCustomerId = isset($currentRow[$customerColumn]) ? (int) $currentRow[$customerColumn] : 0;
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
                content='" . urlEsc($dbConnect, $content) . "',
                attachment='" . urlEsc($dbConnect, $attachmentName) . "',
                updated_by='" . urlEsc($dbConnect, USER_ID) . "',
                updated_at=NOW()
                WHERE id='" . $recordId . "'";
            $ok = mysqli_query($dbConnect, $sql);

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

            $customerAuditValue = $currentCustomerId > 0 ? (string) $currentCustomerId : 'Empty Value';
            $baseEditActMsg = function_exists('actMsgLog')
                ? actMsgLog($recordId, $editFields, '', $editOld, $editNew, $tblName, 'Edit', (!empty($ok) ? '' : mysqli_error($dbConnect)))
                : (USER_NAME . ' edited User Record Log [ID=' . $recordId . ']');
            $editActMsg = rtrim($baseEditActMsg) . ' [' . $customerColumn . ' : ' . $customerAuditValue . ']';

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
                'act_msg' => $editActMsg,
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
        " . $customerColumn . ",
                content,
                attachment,
                created_by,
                created_at,
                updated_by,
                updated_at,
                status
            ) VALUES (
                " . $customerIdSql . ",
                '" . urlEsc($dbConnect, $content) . "',
                '" . urlEsc($dbConnect, $attachmentName) . "',
                '" . urlEsc($dbConnect, USER_ID) . "',
                NOW(),
                '" . urlEsc($dbConnect, USER_ID) . "',
                NOW(),
                'A'
            )";
        $ok = mysqli_query($dbConnect, $sql);
        $newId = (int) $dbConnect->insert_id;
        $addFields = array('content', 'attachment');
        $addNew = array($content, $attachmentName);
        $customerAuditValue = (int) $context['customer_id'] > 0 ? (string) ((int) $context['customer_id']) : 'Empty Value';
        $baseAddActMsg = function_exists('actMsgLog')
            ? actMsgLog($newId, $addFields, $addNew, '', '', $tblName, 'Add', (!empty($ok) ? '' : mysqli_error($dbConnect)))
            : (USER_NAME . ' added User Record Log [ID=' . $newId . ']');
        $addActMsg = rtrim($baseAddActMsg) . ' [' . $customerColumn . ' : ' . $customerAuditValue . ']';

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
            'act_msg' => $addActMsg,
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
        $dbConnect = $connect instanceof mysqli ? $connect : $financeConnect;
        $requestedCustomerColumn = '';
        if (isset($options['customer_column'])) {
            $requestedCustomerColumn = urlSanitizeUserRecordLogCustomerColumn($options['customer_column']);
        } else if (isset($options['context']) && is_array($options['context']) && isset($options['context']['customer_column'])) {
            $requestedCustomerColumn = urlSanitizeUserRecordLogCustomerColumn($options['context']['customer_column']);
        }
        $customerColumn = urlGetUserRecordLogCustomerColumn($dbConnect, $tblName, $requestedCustomerColumn);
        $context = isset($options['context']) && is_array($options['context'])
            ? $options['context']
            : urlResolveUserRecordLogContext($connect, $financeConnect, $options);
        $context['customer_column'] = $customerColumn;
        $sectionHeading = isset($options['section_heading']) ? (string) $options['section_heading'] : '';
        $showScopeNote = !isset($options['show_scope_note']) || (bool) $options['show_scope_note'];
        $pathReturn = isset($context['return_url']) ? (string) $context['return_url'] : '';
        $initialList = urlBuildListHtml($connect, $dbConnect, $tblName, $context);
        $config = array(
            'ajaxUrl' => isset($context['ajax_url']) ? (string) $context['ajax_url'] : (rtrim((string) $GLOBALS['SITEURL'], '/') . '/user_record_log.php'),
            'customerId' => isset($context['customer_id']) ? (int) $context['customer_id'] : 0,
            'customerColumn' => isset($context['customer_column']) ? (string) $context['customer_column'] : '',
            'pathReturn' => $pathReturn,
            'confirmationPageName' => 'User Record Log',
        );
        $configJson = json_encode($config);
        $moduleActionUrl = rtrim((string) $GLOBALS['SITEURL'], '/') . '/user_record_log.php';
        ?>
        <div class="user-record-log-module mt-4">
            <style>
                .user-record-log-module .btn {
                    text-transform: none !important;
                }

                .user-record-log-module .url-log-row.url-row-odd .card-header,
                .user-record-log-module .url-log-row.url-row-odd .card-body {
                    background-color: #ffffff;
                }

                .user-record-log-module .url-log-row.url-row-even .card-header,
                .user-record-log-module .url-log-row.url-row-even .card-body {
                    background-color: #F7F3E1;
                }

                .user-record-log-module .url-content-row {
                    width: 100%;
                }

                .user-record-log-module .url-content-row > div:first-child {
                    flex: 1 1 260px;
                }

                .user-record-log-module .url-attachment-action {
                    flex: 0 0 auto;
                }

                .user-record-log-module .url-filter-label-nowrap {
                    white-space: nowrap;
                }

                .user-record-log-module .url-attachment-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 1050;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .user-record-log-module .url-attachment-modal-dialog {
                    position: relative;
                    width: min(1200px, 96vw);
                    height: min(88vh, 900px);
                    background: #ffffff;
                    border-radius: 8px;
                    padding: 18px;
                }

                .user-record-log-module .url-attachment-modal-close {
                    position: absolute;
                    top: 2px;
                    right: 10px;
                    border: 0;
                    background: transparent;
                    font-size: 28px;
                    line-height: 1;
                    color: #333333;
                    cursor: pointer;
                }

                .user-record-log-module .url-attachment-preview-content {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding-top: 12px;
                }

                .user-record-log-module .url-attachment-preview-content img,
                .user-record-log-module .url-attachment-preview-content iframe {
                    width: 100%;
                    height: 100%;
                    border: 0;
                    object-fit: contain;
                }

                body.url-modal-open {
                    overflow: hidden;
                }
            </style>
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
                    <h5 class="mb-3">Add / Edit User Log</h5>
                    <form id="url_form" method="post" action="<?php echo htmlspecialchars($moduleActionUrl, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="url_action" value="save">
                        <input type="hidden" name="record_id" id="url_record_id" value="0">
                        <input type="hidden" name="existing_attachment" id="url_existing_attachment" value="">
                        <input type="hidden" name="customer_id" value="<?php echo isset($context['customer_id']) ? (int) $context['customer_id'] : 0; ?>">
                        <input type="hidden" name="customer_column" value="<?php echo htmlspecialchars(isset($context['customer_column']) ? (string) $context['customer_column'] : '', ENT_QUOTES, 'UTF-8'); ?>">
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
                            <button type="submit" class="btn btn-sm btn-rounded btn-primary" id="url_submit_btn">Save User Log</button>
                            <button type="button" class="btn btn-sm btn-rounded btn-secondary" id="url_cancel_edit_btn" style="display:none;">Cancel Edit</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Search &amp; Filter</h5>
                    <div class="row g-3 url-filter-row">
                        <div class="col-md-3">
                            <label class="form-label" for="url_keyword">Keyword (content)</label>
                            <input class="form-control" type="text" id="url_keyword" placeholder="Type keyword...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="url_filter_date">Date</label>
                            <input class="form-control" type="date" id="url_filter_date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="url_filter_user">User</label>
                            <select class="form-select" id="url_filter_user">
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
                        <div class="col-md-3">
                            <label class="form-label url-filter-label-nowrap" for="url_filter_attachment">Has Attachment</label>
                            <select class="form-select" id="url_filter_attachment">
                                <option value="">All</option>
                                <option value="Y">Yes</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="row g-2">
                                <div class="col-6 col-md-auto d-grid">
                                    <button type="button" class="btn btn-sm btn-rounded btn-info text-white w-100" id="url_apply_filter_btn">Apply</button>
                                </div>
                                <div class="col-6 col-md-auto d-grid">
                                    <button type="button" class="btn btn-sm btn-rounded btn-secondary w-100" id="url_reset_filter_btn">Reset</button>
                                </div>
                                <div class="col-12 col-md-auto d-grid">
                                    <button type="button" class="btn btn-sm btn-rounded btn-outline-primary w-100" id="url_expand_all_btn">Expand All</button>
                                </div>
                                <div class="col-12 col-md-auto d-grid">
                                    <button type="button" class="btn btn-sm btn-rounded btn-outline-primary w-100" id="url_collapse_all_btn">Collapse All</button>
                                </div>
                            </div>
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
                            <option value="-1">All</option>
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

            <div id="url_attachment_preview_modal" class="url-attachment-modal d-none" role="dialog" aria-modal="true" aria-label="Attachment preview">
                <div class="url-attachment-modal-dialog">
                    <button type="button" class="url-attachment-modal-close" id="url_attachment_modal_close" aria-label="Close">&times;</button>
                    <div id="url_attachment_preview_content" class="url-attachment-preview-content"></div>
                </div>
            </div>
        </div>

        <script>
            window.__USER_RECORD_LOG_CONFIG = <?php echo $configJson ? $configJson : '{}'; ?>;
        </script>
        <script src="<?php echo htmlspecialchars(rtrim((string) $GLOBALS['SITEURL'], '/') . '/js/user_record_log.js?v=' . @filemtime(ROOT . '/js/user_record_log.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php
    }
}
