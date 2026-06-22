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

        echo '<script>showNotification(' . json_encode((string) $message) . ', "success");setTimeout(function(){location.href=' . json_encode($target) . ';}, 1200);</script>';
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
        $result = getData('name,username', "id='" . urlEsc($connect, $uid) . "'", 'LIMIT 1', $safeUserTable, $connect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
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
    function urlGetUserRecordLogCustomerColumn($dbConnect, $tblName, $preferredColumn = '')
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

        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tblName);
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

if (!function_exists('urlEncodeAttachmentPathForUrl')) {
    function urlEncodeAttachmentPathForUrl($path)
    {
        $path = trim(str_replace('\\', '/', (string) $path), '/');
        if ($path === '') {
            return '';
        }

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        if (empty($segments)) {
            return '';
        }

        return implode('/', array_map('rawurlencode', $segments));
    }
}

if (!function_exists('urlBuildUserRecordLogAttachmentUrl')) {
    function urlBuildUserRecordLogAttachmentUrl($attachmentValue, $uploadWebDir = '')
    {
        $attachmentValue = trim(str_replace('\\', '/', (string) $attachmentValue));
        if ($attachmentValue === '' || !defined('SITEURL')) {
            return '';
        }

        if (preg_match('#^https?://#i', $attachmentValue)) {
            return $attachmentValue;
        }

        $normalizedPath = ltrim($attachmentValue, '/');
        if (strpos($normalizedPath, '/') === false) {
            $normalizedPath = trim((string) $uploadWebDir, '/\\') . '/' . $normalizedPath;
        }

        $encodedPath = urlEncodeAttachmentPathForUrl($normalizedPath);
        if ($encodedPath === '') {
            return '';
        }

        return rtrim((string) SITEURL, '/') . '/' . $encodedPath;
    }
}

if (!function_exists('urlUserRecordLogColumnExists')) {
    function urlUserRecordLogColumnExists($dbConnect, $tblName, $columnName)
    {
        static $cache = array();

        if (!($dbConnect instanceof mysqli)) {
            return false;
        }

        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tblName);
        $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string) $columnName);
        if ($safeTable === '' || $safeColumn === '') {
            return false;
        }

        $cacheKey = spl_object_hash($dbConnect) . '|' . $safeTable . '|' . $safeColumn;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $result = mysqli_query($dbConnect, "SHOW COLUMNS FROM `" . $safeTable . "` LIKE '" . $safeColumn . "'");
        $cache[$cacheKey] = ($result && $result->num_rows > 0);
        return $cache[$cacheKey];
    }
}

if (!function_exists('urlGetUserRecordLogAttachmentsColumnName')) {
    function urlGetUserRecordLogAttachmentsColumnName()
    {
        return 'attachments';
    }
}

if (!function_exists('urlNormalizeUserRecordLogAttachmentList')) {
    function urlNormalizeUserRecordLogAttachmentList($attachments)
    {
        $normalized = array();
        $seen = array();
        $values = is_array($attachments) ? $attachments : array($attachments);

        foreach ($values as $value) {
            $value = trim(str_replace('\\', '/', (string) $value));
            if ($value === '') {
                continue;
            }

            if (!isset($seen[$value])) {
                $normalized[] = $value;
                $seen[$value] = true;
            }
        }

        return $normalized;
    }
}

if (!function_exists('urlDecodeUserRecordLogAttachmentList')) {
    function urlDecodeUserRecordLogAttachmentList($attachmentValue, $attachmentsValue = '')
    {
        $attachments = array();
        $attachmentsValue = trim((string) $attachmentsValue);
        if ($attachmentsValue !== '') {
            $decoded = json_decode($attachmentsValue, true);
            if (is_array($decoded)) {
                $attachments = $decoded;
            }
        }

        $attachmentValue = trim((string) $attachmentValue);
        if (empty($attachments) && $attachmentValue !== '') {
            $decodedAttachment = json_decode($attachmentValue, true);
            if (is_array($decodedAttachment)) {
                $attachments = $decodedAttachment;
            } else {
                $attachments[] = $attachmentValue;
            }
        }

        return urlNormalizeUserRecordLogAttachmentList($attachments);
    }
}

if (!function_exists('urlEncodeUserRecordLogAttachmentList')) {
    function urlEncodeUserRecordLogAttachmentList($attachments)
    {
        return json_encode(urlNormalizeUserRecordLogAttachmentList($attachments));
    }
}

if (!function_exists('urlEncodeUserRecordLogAttachmentColumnValue')) {
    function urlEncodeUserRecordLogAttachmentColumnValue($attachments)
    {
        $attachments = urlNormalizeUserRecordLogAttachmentList($attachments);
        if (empty($attachments)) {
            return '';
        }

        if (count($attachments) === 1) {
            return (string) $attachments[0];
        }

        return json_encode($attachments);
    }
}

if (!function_exists('urlGetPrimaryUserRecordLogAttachment')) {
    function urlGetPrimaryUserRecordLogAttachment($attachments)
    {
        $attachments = urlNormalizeUserRecordLogAttachmentList($attachments);
        return !empty($attachments) ? (string) $attachments[0] : '';
    }
}

if (!function_exists('urlUserRecordLogHasAttachments')) {
    function urlUserRecordLogHasAttachments($attachmentValue, $attachmentsValue = '')
    {
        return !empty(urlDecodeUserRecordLogAttachmentList($attachmentValue, $attachmentsValue));
    }
}

if (!function_exists('urlGetUserRecordLogAttachmentExtension')) {
    function urlGetUserRecordLogAttachmentExtension($attachmentPath)
    {
        $cleanPath = trim((string) $attachmentPath);
        if ($cleanPath === '') {
            return '';
        }

        return strtolower((string) pathinfo($cleanPath, PATHINFO_EXTENSION));
    }
}

if (!function_exists('urlIsImageUserRecordLogAttachment')) {
    function urlIsImageUserRecordLogAttachment($attachmentPath)
    {
        $ext = urlGetUserRecordLogAttachmentExtension($attachmentPath);
        return in_array($ext, array('png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'), true);
    }
}

if (!function_exists('urlBuildUserRecordLogAttachmentPreviewGrid')) {
    function urlBuildUserRecordLogAttachmentPreviewGrid($attachments, $uploadWebDir = '')
    {
        $attachments = urlNormalizeUserRecordLogAttachmentList($attachments);
        if (empty($attachments)) {
            return '';
        }

        $items = array();
        foreach ($attachments as $attachment) {
            $href = urlBuildUserRecordLogAttachmentUrl($attachment, $uploadWebDir);
            if ($href === '') {
                continue;
            }

            $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            $safeFile = htmlspecialchars((string) $attachment, ENT_QUOTES, 'UTF-8');
            $fileLabel = basename(str_replace('\\', '/', (string) $attachment));
            $safeLabel = htmlspecialchars((string) $fileLabel, ENT_QUOTES, 'UTF-8');

            if (urlIsImageUserRecordLogAttachment($attachment)) {
                $items[] = '<button type="button" class="url-attachment-thumb url-view-attachment-btn" data-url="' . $safeHref . '" data-file="' . $safeFile . '" aria-label="Preview attachment ' . $safeLabel . '">'
                    . '<img src="' . $safeHref . '" alt="' . $safeLabel . '">'
                    . '</button>';
                continue;
            }

            $ext = strtoupper(urlGetUserRecordLogAttachmentExtension($attachment));
            if ($ext === '') {
                $ext = 'FILE';
            }

            $items[] = '<button type="button" class="url-attachment-thumb url-attachment-thumb-file url-view-attachment-btn" data-url="' . $safeHref . '" data-file="' . $safeFile . '" aria-label="Preview attachment ' . $safeLabel . '">'
                . '<span class="url-attachment-file-ext">' . htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') . '</span>'
                . '<span class="url-attachment-file-name">' . $safeLabel . '</span>'
                . '</button>';
        }

        if (empty($items)) {
            return '';
        }

        return '<div class="url-attachment-preview-grid">' . implode('', $items) . '</div>';
    }
}

if (!function_exists('urlLooksLikeUserRecordLogHtml')) {
    function urlLooksLikeUserRecordLogHtml($content)
    {
        return preg_match('/<\s*(p|br|div|span|strong|b|em|i|u|ul|ol|li|blockquote|h[1-4])\b/i', (string) $content) === 1;
    }
}

if (!function_exists('urlSanitizeUserRecordLogHtmlNode')) {
    function urlSanitizeUserRecordLogHtmlNode($node, $allowedTags)
    {
        if (!($node instanceof DOMNode)) {
            return;
        }

        $children = array();
        foreach ($node->childNodes as $childNode) {
            $children[] = $childNode;
        }

        foreach ($children as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower((string) $childNode->nodeName);
                if (!in_array($tagName, $allowedTags, true)) {
                    while ($childNode->firstChild) {
                        $node->insertBefore($childNode->firstChild, $childNode);
                    }
                    $node->removeChild($childNode);
                    continue;
                }

                while ($childNode->attributes && $childNode->attributes->length > 0) {
                    $childNode->removeAttributeNode($childNode->attributes->item(0));
                }
            }

            urlSanitizeUserRecordLogHtmlNode($childNode, $allowedTags);
        }
    }
}

if (!function_exists('urlSanitizeUserRecordLogHtml')) {
    function urlSanitizeUserRecordLogHtml($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        $allowedTags = array('p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'span', 'div', 'h1', 'h2', 'h3', 'h4');

        if (!class_exists('DOMDocument')) {
            return preg_replace('/\r\n|\r|\n/', '<br>', htmlspecialchars(strip_tags($content), ENT_QUOTES, 'UTF-8'));
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $html = '<!DOCTYPE html><html><body>' . $content . '</body></html>';
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $body = $dom->getElementsByTagName('body')->item(0);

        if ($body instanceof DOMNode) {
            urlSanitizeUserRecordLogHtmlNode($body, $allowedTags);

            $sanitized = '';
            foreach ($body->childNodes as $childNode) {
                $sanitized .= $dom->saveHTML($childNode);
            }

            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
            return trim((string) $sanitized);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);
        return preg_replace('/\r\n|\r|\n/', '<br>', htmlspecialchars(strip_tags($content), ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('urlNormalizeSubmittedUserRecordLogContent')) {
    function urlNormalizeSubmittedUserRecordLogContent($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        if (urlLooksLikeUserRecordLogHtml($content)) {
            return urlSanitizeUserRecordLogHtml($content);
        }

        return preg_replace('/\r\n|\r|\n/', '<br>', htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('urlGetUserRecordLogContentPlainText')) {
    function urlGetUserRecordLogContentPlainText($content)
    {
        return trim(html_entity_decode(strip_tags((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

if (!function_exists('urlRenderUserRecordLogContentHtml')) {
    function urlRenderUserRecordLogContentHtml($content)
    {
        $content = (string) $content;
        if (trim($content) === '') {
            return '';
        }

        if (urlLooksLikeUserRecordLogHtml($content)) {
            return urlSanitizeUserRecordLogHtml($content);
        }

        return preg_replace('/\r\n|\r|\n/', '<br>', htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
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

        $tblName = defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info';
        $result = getData('*', "id='" . $customerId . "'", 'LIMIT 1', $tblName, $financeConnect);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
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
            $returnUrl = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/users/user_record_log.php';
        }

        $ajaxUrl = isset($options['ajax_url']) ? trim((string) $options['ajax_url']) : (rtrim((string) $GLOBALS['SITEURL'], '/') . '/users/user_record_log.php');
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
        $result = mysqli_query($dbConnect, $sql);
        if (!$result || $result->num_rows === 0) {
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
        while ($row = $result->fetch_assoc()) {
            $count++;
            $recordId = isset($row['id']) ? (int) $row['id'] : 0;
            $content = isset($row['content']) ? $row['content'] : '';
            $attachment = isset($row['attachment']) ? $row['attachment'] : '';
            $attachmentList = urlDecodeUserRecordLogAttachmentList($attachment);
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

            $attachmentPreviewHtml = urlBuildUserRecordLogAttachmentPreviewGrid($attachmentList, $uploadWebDir);

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
            $html .= '      <div class="mb-0 url-log-content-wrap"><strong>Content:</strong><div class="url-log-content mt-2">' . urlRenderUserRecordLogContentHtml($content) . '</div></div>';
            if ($attachmentPreviewHtml !== '') {
                $html .= '      <div class="url-attachment-action ms-auto"><div class="url-attachment-title">Attachment</div>' . $attachmentPreviewHtml . '</div>';
            }
            $html .= '    </div>';
            $html .= '    <textarea class="url-edit-content d-none">' . htmlspecialchars((string) $content, ENT_QUOTES, 'UTF-8') . '</textarea>';
            $html .= '    <textarea class="url-edit-attachments d-none">' . htmlspecialchars(urlEncodeUserRecordLogAttachmentList($attachmentList), ENT_QUOTES, 'UTF-8') . '</textarea>';
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
        $content = urlNormalizeSubmittedUserRecordLogContent(isset($_POST['content']) ? (string) $_POST['content'] : '');
        if (urlGetUserRecordLogContentPlainText($content) === '') {
            if ($urlIsFallback) {
                urlFallbackResponse('Content is required.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Content is required.'));
        }

        $submittedAttachments = array();
        if (isset($_POST['existing_attachments'])) {
            $submittedAttachments = urlDecodeUserRecordLogAttachmentList('', (string) $_POST['existing_attachments']);
        } else if (isset($_POST['existing_attachment'])) {
            $submittedAttachments = urlNormalizeUserRecordLogAttachmentList($_POST['existing_attachment']);
        }

        $uploadedAttachments = array();
        $allowed = array('png', 'jpg', 'jpeg', 'webp', 'pdf');
        $maxFileSize = 10 * 1024 * 1024;
        $mimeByExt = array(
            'png' => array('image/png'),
            'jpg' => array('image/jpeg'),
            'jpeg' => array('image/jpeg'),
            'webp' => array('image/webp'),
            'pdf' => array('application/pdf'),
        );

        $fileEntries = array();
        if (isset($_FILES['attachment'])) {
            $fileField = $_FILES['attachment'];
            $fileNames = isset($fileField['name']) ? $fileField['name'] : array();
            if (is_array($fileNames)) {
                $totalFiles = count($fileNames);
                for ($fileIdx = 0; $fileIdx < $totalFiles; $fileIdx++) {
                    $fileEntries[] = array(
                        'name' => isset($fileField['name'][$fileIdx]) ? $fileField['name'][$fileIdx] : '',
                        'tmp_name' => isset($fileField['tmp_name'][$fileIdx]) ? $fileField['tmp_name'][$fileIdx] : '',
                        'size' => isset($fileField['size'][$fileIdx]) ? (int) $fileField['size'][$fileIdx] : 0,
                        'error' => isset($fileField['error'][$fileIdx]) ? (int) $fileField['error'][$fileIdx] : UPLOAD_ERR_NO_FILE,
                    );
                }
            } else {
                $fileEntries[] = array(
                    'name' => isset($fileField['name']) ? $fileField['name'] : '',
                    'tmp_name' => isset($fileField['tmp_name']) ? $fileField['tmp_name'] : '',
                    'size' => isset($fileField['size']) ? (int) $fileField['size'] : 0,
                    'error' => isset($fileField['error']) ? (int) $fileField['error'] : UPLOAD_ERR_NO_FILE,
                );
            }
        }

        foreach ($fileEntries as $fileEntry) {
            $uploadError = isset($fileEntry['error']) ? (int) $fileEntry['error'] : UPLOAD_ERR_NO_FILE;
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($uploadError !== UPLOAD_ERR_OK) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Failed to upload attachment.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Failed to upload attachment.'));
            }

            $origName = isset($fileEntry['name']) ? (string) $fileEntry['name'] : '';
            $tmpPath = isset($fileEntry['tmp_name']) ? (string) $fileEntry['tmp_name'] : '';
            $fileSize = isset($fileEntry['size']) ? (int) $fileEntry['size'] : 0;
            $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed, true)) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Attachment must be png, jpg, jpeg, webp or pdf.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Attachment must be png, jpg, jpeg, webp or pdf.'));
            }
            if ($fileSize <= 0 || $fileSize > $maxFileSize) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Attachment size is invalid or exceeds 10MB.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Attachment size is invalid or exceeds 10MB.'));
            }

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

            $uploadedAttachments[] = $newName;
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

            if (isset($_POST['existing_attachments'])) {
                $attachmentNames = $submittedAttachments;
            } else {
                $attachmentNames = urlDecodeUserRecordLogAttachmentList(isset($currentRow['attachment']) ? $currentRow['attachment'] : '');
            }
            if (!empty($uploadedAttachments)) {
                $attachmentNames = urlNormalizeUserRecordLogAttachmentList(array_merge($attachmentNames, $uploadedAttachments));
            } else {
                $attachmentNames = urlNormalizeUserRecordLogAttachmentList($attachmentNames);
            }

            $attachmentValue = urlEncodeUserRecordLogAttachmentColumnValue($attachmentNames);
            $updateParts = array(
                "content='" . urlEsc($dbConnect, $content) . "'",
                "attachment='" . urlEsc($dbConnect, $attachmentValue) . "'",
            );
            $updateParts[] = "updated_by='" . urlEsc($dbConnect, USER_ID) . "'";
            $updateParts[] = "updated_at=NOW()";

            $sql = "UPDATE " . $tblName . " SET
                " . implode(",
                ", $updateParts) . "
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
            if ($oldAttachment !== $attachmentValue) {
                $editFields[] = 'attachment';
                $editOld[] = $oldAttachment;
                $editNew[] = $attachmentValue;
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

        $attachmentNames = urlNormalizeUserRecordLogAttachmentList($uploadedAttachments);
        $attachmentValue = urlEncodeUserRecordLogAttachmentColumnValue($attachmentNames);
        $insertColumns = array(
            $customerColumn,
            'content',
            'attachment',
        );
        $insertValues = array(
            $customerIdSql,
            "'" . urlEsc($dbConnect, $content) . "'",
            "'" . urlEsc($dbConnect, $attachmentValue) . "'",
        );
        $insertColumns = array_merge($insertColumns, array('created_by', 'created_at', 'updated_by', 'updated_at', 'status'));
        $insertValues = array_merge($insertValues, array(
            "'" . urlEsc($dbConnect, USER_ID) . "'",
            'NOW()',
            "'" . urlEsc($dbConnect, USER_ID) . "'",
            'NOW()',
            "'A'",
        ));

        $sql = "INSERT INTO " . $tblName . " (
                " . implode(",
                ", $insertColumns) . "
            ) VALUES (
                " . implode(",
                ", $insertValues) . "
            )";
        $ok = mysqli_query($dbConnect, $sql);
        $newId = (int) $dbConnect->insert_id;
        $addFields = array('content', 'attachment');
        $addNew = array($content, $attachmentValue);
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
            'ajaxUrl' => isset($context['ajax_url']) ? (string) $context['ajax_url'] : (rtrim((string) $GLOBALS['SITEURL'], '/') . '/users/user_record_log.php'),
            'customerId' => isset($context['customer_id']) ? (int) $context['customer_id'] : 0,
            'customerColumn' => isset($context['customer_column']) ? (string) $context['customer_column'] : '',
            'pathReturn' => $pathReturn,
            'siteUrl' => rtrim((string) $GLOBALS['SITEURL'], '/'),
            'uploadWebDir' => trim((string) urlGetUserRecordLogUploadWebDir(), '/'),
            'confirmationPageName' => 'User Record Log',
        );
        $configJson = json_encode($config);
        $moduleActionUrl = rtrim((string) $GLOBALS['SITEURL'], '/') . '/users/user_record_log.php';
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

                .user-record-log-module .url-log-content-wrap {
                    flex: 1 1 260px;
                    min-width: 0;
                }

                .user-record-log-module .url-log-content {
                    line-height: 1.65;
                    word-break: break-word;
                }

                .user-record-log-module .url-log-content p:last-child,
                .user-record-log-module .url-log-content ul:last-child,
                .user-record-log-module .url-log-content ol:last-child,
                .user-record-log-module .url-log-content blockquote:last-child,
                .user-record-log-module .url-log-content h1:last-child,
                .user-record-log-module .url-log-content h2:last-child,
                .user-record-log-module .url-log-content h3:last-child,
                .user-record-log-module .url-log-content h4:last-child {
                    margin-bottom: 0;
                }

                .user-record-log-module .url-log-content ul,
                .user-record-log-module .url-log-content ol {
                    padding-left: 1.4rem;
                }

                .user-record-log-module .url-log-content blockquote {
                    margin: 0 0 1rem;
                    padding-left: 0.9rem;
                    border-left: 3px solid #d6d9e0;
                    color: #566071;
                }

                .user-record-log-module .url-attachment-action {
                    flex: 0 0 170px;
                    width: 170px;
                    max-width: 100%;
                }

                .user-record-log-module .url-attachment-title {
                    font-weight: 600;
                    font-size: 0.875rem;
                    margin-bottom: 0.5rem;
                }

                .user-record-log-module .url-attachment-preview-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 8px;
                }

                .user-record-log-module .url-attachment-thumb {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 100%;
                    min-height: 78px;
                    border: 1px solid #d7dce5;
                    border-radius: 12px;
                    background: #ffffff;
                    overflow: hidden;
                    cursor: pointer;
                    padding: 0;
                    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
                }

                .user-record-log-module .url-attachment-thumb:hover {
                    transform: translateY(-1px);
                    border-color: #6f93ff;
                    box-shadow: 0 8px 18px rgba(37, 74, 158, 0.12);
                }

                .user-record-log-module .url-attachment-thumb img {
                    width: 100%;
                    height: 100%;
                    max-height: 132px;
                    object-fit: cover;
                    display: block;
                }

                .user-record-log-module .url-attachment-thumb-file {
                    flex-direction: column;
                    gap: 6px;
                    padding: 10px 8px;
                    text-align: center;
                }

                .user-record-log-module .url-attachment-file-ext {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 48px;
                    height: 28px;
                    padding: 0 10px;
                    border-radius: 999px;
                    background: #eef4ff;
                    color: #2756c4;
                    font-size: 0.75rem;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                }

                .user-record-log-module .url-attachment-file-name {
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                    font-size: 0.78rem;
                    line-height: 1.35;
                    color: #3f4b5d;
                    word-break: break-word;
                }

                .user-record-log-module .url-editor-note {
                    margin-top: 0.5rem;
                    color: #6d7685;
                    font-size: 0.82rem;
                }

                .user-record-log-module .si-attach-wrap {
                    border: 1px solid #e2e2e2;
                    border-radius: 8px;
                    padding: 12px;
                }

                .user-record-log-module .si-attach-preview {
                    min-height: 180px;
                    border: 1px dashed #d0d0d0;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #fafafa;
                    padding: 10px;
                }

                .user-record-log-module .si-attach-preview img {
                    max-width: 100%;
                    max-height: 260px;
                    object-fit: contain;
                }

                .user-record-log-module .si-attachment-input-row {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .user-record-log-module .si-attachment-input-row .user-record-log-attachment-input {
                    flex: 1;
                }

                .user-record-log-module .url-existing-attachment-list {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                }

                .user-record-log-module .url-existing-attachment-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .user-record-log-module .url-existing-attachment-link {
                    word-break: break-word;
                }

                .user-record-log-module .url-remove-existing-attachment-btn {
                    border: 0;
                    background: transparent;
                    color: #d22b2b;
                    padding: 0;
                    line-height: 1;
                    cursor: pointer;
                }

                .user-record-log-module .url-edit-preview-item {
                    position: relative;
                    display: inline-flex;
                }

                .user-record-log-module .url-edit-preview-open-btn {
                    width: 120px;
                    min-height: 120px;
                }

                .user-record-log-module .url-edit-preview-item img {
                    cursor: pointer;
                }

                .user-record-log-module .url-edit-preview-remove-btn {
                    position: absolute;
                    top: -8px;
                    right: -8px;
                    width: 24px;
                    height: 24px;
                    border-radius: 999px;
                    border: 0;
                    background: #d22b2b;
                    color: #ffffff;
                    font-size: 14px;
                    line-height: 24px;
                    text-align: center;
                    cursor: pointer;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
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

                .user-record-log-module .tox-tinymce {
                    border-radius: 8px;
                }

                body.url-modal-open {
                    overflow: hidden;
                }

                @media (max-width: 767.98px) {
                    .user-record-log-module .url-attachment-action {
                        flex: 1 1 100%;
                        width: 100%;
                    }

                    .user-record-log-module .url-attachment-preview-grid {
                        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                    }
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
                        <input type="hidden" name="existing_attachments" id="url_existing_attachments" value="[]">
                        <input type="hidden" name="customer_id" value="<?php echo isset($context['customer_id']) ? (int) $context['customer_id'] : 0; ?>">
                        <input type="hidden" name="customer_column" value="<?php echo htmlspecialchars(isset($context['customer_column']) ? (string) $context['customer_column'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($pathReturn, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label class="form-label" for="url_content">Content</label>
                            <textarea class="form-control" id="url_content" name="content" rows="4" required></textarea>
                            <div class="url-editor-note">Supports paragraphs, bold text, emoji, and bullet or numbered lists.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="url_attachment">Attachment</label>
                            <div class="si-attach-wrap">
                                <div class="row g-3 align-items-start">
                                    <div class="col-12 col-md-6">
                                        <div id="url_attachment_inputs">
                                            <div class="mb-2 si-attachment-input-row">
                                                <input class="form-control user-record-log-attachment-input" type="file" name="attachment[]" id="url_attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,application/pdf">
                                                <button class="mt-1 add-user-record-log-attachment-btn" id="action_menu_btn" type="button" title="Add another attachment"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                            </div>
                                        </div>
                                        <small class="text-muted">Click + to add more attachments. Supports image and PDF files.</small>
                                        <div class="mt-2" id="url_existing_attachment_links"></div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="si-attach-preview">
                                            <div id="url_attachment_img_list" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;"></div>
                                            <span id="url_attachment_placeholder" class="text-muted">Image / PDF preview</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
        <script src="<?php echo htmlspecialchars(rtrim((string) $GLOBALS['SITEURL'], '/') . '/header/tinymce/tinymce.min.js?v=' . @filemtime(ROOT . '/header/tinymce/tinymce.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <script src="<?php echo htmlspecialchars(rtrim((string) $GLOBALS['SITEURL'], '/') . '/js/users/user_record_log.js?v=' . @filemtime(ROOT . '/js/user_record_log.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php
    }
}
