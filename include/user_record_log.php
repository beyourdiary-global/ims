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

        $notificationType = $isSuccess ? 'success' : 'error';
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body><script>(function(){'
            . 'var message=' . json_encode((string) $message) . ';'
            . 'var target=' . json_encode($target) . ';'
            . 'var redirect=function(){window.location.href=target;};'
            . 'if(typeof window.showNotification==="function"){window.showNotification(message,' . json_encode($notificationType) . ');setTimeout(redirect,1200);return;}'
            . 'if(message){window.alert(message);}redirect();'
            . '})();</script></body></html>';
        exit;
    }
}

if (!function_exists('urlIsAjaxRequest')) {
    function urlIsAjaxRequest()
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }

        if (!empty($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }

        return false;
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

if (!function_exists('urlNormalizeUserRecordLogDateValue')) {
    function urlNormalizeUserRecordLogDateValue($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        $dateParts = explode('-', $value);
        if (count($dateParts) !== 3) {
            return '';
        }

        $year = (int) $dateParts[0];
        $month = (int) $dateParts[1];
        $day = (int) $dateParts[2];
        if (!checkdate($month, $day, $year)) {
            return '';
        }

        return $value;
    }
}

if (!function_exists('urlNormalizeUserRecordLogShortText')) {
    function urlNormalizeUserRecordLogShortText($value, $maxLength = 255)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $maxLength = (int) $maxLength;
        if ($maxLength < 1) {
            $maxLength = 255;
        }

        if (function_exists('mb_substr')) {
            return trim((string) mb_substr($value, 0, $maxLength, 'UTF-8'));
        }

        return trim((string) substr($value, 0, $maxLength));
    }
}

if (!function_exists('urlNormalizeUserRecordLogPlainText')) {
    function urlNormalizeUserRecordLogPlainText($value, $maxLength = 5000)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace("/\r\n|\r/", "\n", $value);
        $maxLength = (int) $maxLength;
        if ($maxLength < 1) {
            $maxLength = 5000;
        }

        if (function_exists('mb_substr')) {
            return trim((string) mb_substr($value, 0, $maxLength, 'UTF-8'));
        }

        return trim((string) substr($value, 0, $maxLength));
    }
}

if (!function_exists('urlRenderUserRecordLogPlainTextHtml')) {
    function urlRenderUserRecordLogPlainTextHtml($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
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

if (!function_exists('urlDecodeUserRecordLogAttachmentSequence')) {
    function urlDecodeUserRecordLogAttachmentSequence($attachmentValue, $attachmentSequenceValue = '')
    {
        $attachments = urlDecodeUserRecordLogAttachmentList($attachmentValue);
        $sequence = urlDecodeUserRecordLogAttachmentList('', $attachmentSequenceValue);
        if (empty($sequence)) {
            return $attachments;
        }

        $ordered = array();
        $remainingAttachments = array();
        foreach ($attachments as $attachment) {
            $remainingAttachments[$attachment] = true;
        }

        foreach ($sequence as $attachment) {
            if (isset($remainingAttachments[$attachment])) {
                $ordered[] = $attachment;
                unset($remainingAttachments[$attachment]);
            }
        }

        foreach ($attachments as $attachment) {
            if (isset($remainingAttachments[$attachment])) {
                $ordered[] = $attachment;
            }
        }

        return $ordered;
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
        foreach ($attachments as $attachmentIndex => $attachment) {
            $href = urlBuildUserRecordLogAttachmentUrl($attachment, $uploadWebDir);
            if ($href === '') {
                continue;
            }

            $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            $safeFile = htmlspecialchars((string) $attachment, ENT_QUOTES, 'UTF-8');
            $fileLabel = basename(str_replace('\\', '/', (string) $attachment));
            $safeLabel = htmlspecialchars((string) $fileLabel, ENT_QUOTES, 'UTF-8');

            if (urlIsImageUserRecordLogAttachment($attachment)) {
                $items[] = '<button type="button" class="url-attachment-thumb url-view-attachment-btn" data-url="' . $safeHref . '" data-file="' . $safeFile . '" data-index="' . (int) $attachmentIndex . '" aria-label="Preview attachment ' . $safeLabel . '">'
                    . '<span class="url-attachment-sequence">' . ((int) $attachmentIndex + 1) . '</span>'
                    . '<img src="' . $safeHref . '" alt="' . $safeLabel . '">'
                    . '</button>';
                continue;
            }

            $ext = strtoupper(urlGetUserRecordLogAttachmentExtension($attachment));
            if ($ext === '') {
                $ext = 'FILE';
            }

            $items[] = '<button type="button" class="url-attachment-thumb url-attachment-thumb-file url-view-attachment-btn" data-url="' . $safeHref . '" data-file="' . $safeFile . '" data-index="' . (int) $attachmentIndex . '" aria-label="Preview attachment ' . $safeLabel . '">'
                . '<span class="url-attachment-sequence">' . ((int) $attachmentIndex + 1) . '</span>'
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

if (!function_exists('urlBuildUserRecordLogCopyHtml')) {
    function urlBuildUserRecordLogCopyHtml($displayNo, $auditMetaText, $summary, $content, $attachments = array(), $uploadWebDir = '', $followUpFields = array())
    {
        $summary = trim((string) $summary);
        $content = trim((string) $content);
        $auditMetaText = trim((string) $auditMetaText);
        $attachments = urlNormalizeUserRecordLogAttachmentList($attachments);

        $parts = array();
        $parts[] = '<div>';
        $parts[] = '<div><strong>#' . (int) $displayNo . '</strong>';
        if ($auditMetaText !== '') {
            $parts[] = ' <span>' . htmlspecialchars($auditMetaText, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $parts[] = '</div>';

        if ($summary !== '') {
            $parts[] = '<div style="margin-top:12px;"><strong>Summary:</strong><div style="margin-top:6px;">' . urlRenderUserRecordLogPlainTextHtml($summary) . '</div></div>';
        }

        $parts[] = '<div style="margin-top:12px;"><strong>Content:</strong><div style="margin-top:6px;">' . urlRenderUserRecordLogContentHtml($content) . '</div></div>';

        if (!empty($attachments)) {
            $attachmentItems = array();
            foreach ($attachments as $attachment) {
                $href = urlBuildUserRecordLogAttachmentUrl($attachment, $uploadWebDir);
                $label = basename(str_replace('\\', '/', (string) $attachment));
                if ($href !== '') {
                    $attachmentItems[] = '<li><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></li>';
                } else {
                    $attachmentItems[] = '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</li>';
                }
            }

            if (!empty($attachmentItems)) {
                $parts[] = '<div style="margin-top:12px;"><strong>Attachment:</strong><ul style="margin:6px 0 0 18px; padding:0;">' . implode('', $attachmentItems) . '</ul></div>';
            }
        }

        if (!empty($followUpFields) && is_array($followUpFields)) {
            $followUpItems = array();
            foreach ($followUpFields as $label => $value) {
                $label = trim((string) $label);
                $value = trim((string) $value);
                if ($label === '' || $value === '') {
                    continue;
                }

                $followUpItems[] = '<div><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div>';
            }

            if (!empty($followUpItems)) {
                $parts[] = '<div style="margin-top:12px;">' . implode('', $followUpItems) . '</div>';
            }
        }

        $parts[] = '</div>';

        return implode('', $parts);
    }
}

if (!function_exists('urlBuildUserRecordLogCopyText')) {
    function urlBuildUserRecordLogCopyText($displayNo, $auditMetaText, $summary, $content, $attachments = array(), $uploadWebDir = '', $followUpFields = array())
    {
        $lines = array();
        $summary = trim((string) $summary);
        $content = trim((string) $content);
        $auditMetaText = trim((string) $auditMetaText);
        $attachments = urlNormalizeUserRecordLogAttachmentList($attachments);

        $headerLine = '#' . (int) $displayNo;
        if ($auditMetaText !== '') {
            $headerLine .= ' ' . $auditMetaText;
        }
        $lines[] = $headerLine;

        if ($summary !== '') {
            $lines[] = '';
            $lines[] = 'Summary:';
            $lines[] = urlGetUserRecordLogContentPlainText($summary);
        }

        $lines[] = '';
        $lines[] = 'Content:';
        $lines[] = urlGetUserRecordLogContentPlainText($content);

        if (!empty($attachments)) {
            $lines[] = '';
            $lines[] = 'Attachment:';
            foreach ($attachments as $attachment) {
                $href = urlBuildUserRecordLogAttachmentUrl($attachment, $uploadWebDir);
                $label = basename(str_replace('\\', '/', (string) $attachment));
                $lines[] = $href !== '' ? ($label . ': ' . $href) : $label;
            }
        }

        if (!empty($followUpFields) && is_array($followUpFields)) {
            $hasFollowUpValue = false;
            foreach ($followUpFields as $value) {
                if (trim((string) $value) !== '') {
                    $hasFollowUpValue = true;
                    break;
                }
            }

            if ($hasFollowUpValue) {
                $lines[] = '';
                foreach ($followUpFields as $label => $value) {
                    $label = trim((string) $label);
                    $value = trim((string) $value);
                    if ($label === '' || $value === '') {
                        continue;
                    }

                    $lines[] = $label . ': ' . $value;
                }
            }
        }

        return trim(implode("\n", $lines));
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

if (!function_exists('urlGetLatestUserRecordLogSummary')) {
    function urlGetLatestUserRecordLogSummary($dbConnect, $tblName, $context = array())
    {
        if (!($dbConnect instanceof mysqli)) {
            return '';
        }

        if (!urlUserRecordLogColumnExists($dbConnect, $tblName, 'summary')) {
            return '';
        }

        $customerId = isset($context['customer_id']) ? (int) $context['customer_id'] : 0;
        $customerOnly = !empty($context['customer_only']);
        $customerColumn = isset($context['customer_column']) ? trim((string) $context['customer_column']) : '';
        if ($customerColumn === '') {
            $customerColumn = 'cust_id';
        }

        $where = array("status='A'");
        if ($customerId > 0) {
            $where[] = "`" . preg_replace('/[^A-Za-z0-9_]/', '', $customerColumn) . "`='" . (int) $customerId . "'";
        } else if ($customerOnly) {
            return '';
        } else {
            return '';
        }

        $sql = "SELECT `summary`
                FROM `" . preg_replace('/[^A-Za-z0-9_]/', '', (string) $tblName) . "`
                WHERE " . implode(' AND ', $where) . "
                ORDER BY `updated_at` DESC, `id` DESC
                LIMIT 1";
        $result = mysqli_query($dbConnect, $sql);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return isset($row['summary']) ? trim((string) $row['summary']) : '';
        }

        return '';
    }
}

if (!function_exists('urlGetUserRecordLogMessageShortcutOptions')) {
    function urlGetUserRecordLogMessageShortcutOptions($dbConnect)
    {
        $rows = array();
        if (!($dbConnect instanceof mysqli) || !defined('MESSAGE_SHORTCUTS')) {
            return $rows;
        }

        $tableName = preg_replace('/[^A-Za-z0-9_]/', '', (string) MESSAGE_SHORTCUTS);
        if ($tableName === '') {
            return $rows;
        }

        $where = '';
        if (function_exists('urlUserRecordLogTableHasColumn') && urlUserRecordLogTableHasColumn($dbConnect, $tableName, 'status')) {
            $where = " WHERE `status` = 'A'";
        }

        $sql = "SELECT `id`, `shortcuts_tag`, `shortcuts_message`
                FROM `" . $tableName . "`" . $where . "
                ORDER BY `shortcuts_tag` ASC, `id` ASC";
        $result = mysqli_query($dbConnect, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $shortcutId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($shortcutId <= 0) {
                continue;
            }

            $label = trim((string) (isset($row['shortcuts_tag']) ? $row['shortcuts_tag'] : ''));
            if ($label === '') {
                $label = 'Shortcut #' . $shortcutId;
            }

            $rows[] = array(
                'id' => $shortcutId,
                'label' => $label,
                'message_html' => urlRenderUserRecordLogContentHtml(isset($row['shortcuts_message']) ? (string) $row['shortcuts_message'] : ''),
            );
        }

        return $rows;
    }
}

if (!function_exists('urlGetUserRecordLogMessageShortcutById')) {
    function urlGetUserRecordLogMessageShortcutById($dbConnect, $shortcutId)
    {
        $shortcutId = (int) $shortcutId;
        if (!($dbConnect instanceof mysqli) || !defined('MESSAGE_SHORTCUTS') || $shortcutId <= 0) {
            return array();
        }

        $result = getData('id,shortcuts_tag,shortcuts_message', "id='" . $shortcutId . "'", 'LIMIT 1', MESSAGE_SHORTCUTS, $dbConnect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $label = trim((string) (isset($row['shortcuts_tag']) ? $row['shortcuts_tag'] : ''));
            if ($label === '') {
                $label = 'Shortcut #' . $shortcutId;
            }

            return array(
                'id' => $shortcutId,
                'label' => $label,
                'message_html' => urlRenderUserRecordLogContentHtml(isset($row['shortcuts_message']) ? (string) $row['shortcuts_message'] : ''),
            );
        }

        return array();
    }
}

if (!function_exists('urlBuildListHtml')) {
    function urlBuildListHtml($connect, $financeConnect, $tblName, $context = array())
    {
        $dbConnect = $financeConnect instanceof mysqli ? $financeConnect : $connect;
        $uploadWebDir = urlGetUserRecordLogUploadWebDir();
        $keyword = trim((string) post('keyword'));
        $filterDate = trim((string) post('filter_date'));
        $filterUser = trim((string) post('filter_user'));
        $filterAttachment = trim((string) post('filter_attachment'));
        $page = (int) post('page');
        $pageSize = (int) post('page_size');
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
        $where[] = "(IFNULL(content,'') <> '' OR IFNULL(attachment,'') <> '')";

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
            $attachmentSequence = isset($row['attachment_sequence']) ? $row['attachment_sequence'] : '';
            $attachmentList = urlDecodeUserRecordLogAttachmentSequence($attachment, $attachmentSequence);
            $summary = isset($row['summary']) ? trim((string) $row['summary']) : '';
            $messageShortcutId = isset($row['message_shortcut_id']) ? (int) $row['message_shortcut_id'] : 0;
            $nextFollowUpDate = isset($row['next_follow_up_date']) ? trim((string) $row['next_follow_up_date']) : '';
            $followUpTimes = isset($row['follow_up_times']) ? trim((string) $row['follow_up_times']) : '';
            $followUpDay = isset($row['follow_up_day']) ? trim((string) $row['follow_up_day']) : '';
            $createdAt = isset($row['created_at']) ? $row['created_at'] : '';
            $updatedAt = isset($row['updated_at']) ? $row['updated_at'] : '';
            $createdBy = urlGetUserName($connect, isset($row['created_by']) ? $row['created_by'] : '');
            $updatedBy = urlGetUserName($connect, isset($row['updated_by']) ? $row['updated_by'] : '');
            $isSystemRecord = strcasecmp(trim((string) $createdBy), 'SYSTEM') === 0;
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
            $createdMetaText = 'Created: ' . trim((string) $createdAt) . ' by ' . trim((string) $createdBy);
            $updatedMetaText = 'Updated: ' . trim((string) $updatedAt) . ' by ' . trim((string) $updatedBy);
            $auditMeta = $createdMeta;
            $auditMetaText = $createdMetaText;
            if (!$isSameAuditInfo && trim((string) $updatedAt) !== '') {
                $auditMeta .= ' <span class="url-meta-sep">|</span> ' . $updatedMeta;
                $auditMetaText .= ' | ' . $updatedMetaText;
            }

            $attachmentPreviewHtml = urlBuildUserRecordLogAttachmentPreviewGrid($attachmentList, $uploadWebDir);
            $followUpMetaItems = array();
            $followUpCopyFields = array();
            if ($nextFollowUpDate !== '') {
                $followUpMetaItems[] = '<span><strong>Next Follow-Up Date:</strong> ' . htmlspecialchars($nextFollowUpDate, ENT_QUOTES, 'UTF-8') . '</span>';
                $followUpCopyFields['Next Follow-Up Date'] = $nextFollowUpDate;
            }
            if ($followUpTimes !== '') {
                $followUpMetaItems[] = '<span><strong>Follow-Up Times:</strong> ' . htmlspecialchars($followUpTimes, ENT_QUOTES, 'UTF-8') . '</span>';
                $followUpCopyFields['Follow-Up Times'] = $followUpTimes;
            }
            if ($followUpDay !== '') {
                $followUpMetaItems[] = '<span><strong>Follow-Up Day:</strong> ' . htmlspecialchars($followUpDay, ENT_QUOTES, 'UTF-8') . '</span>';
                $followUpCopyFields['Follow-Up Day'] = $followUpDay;
            }

            $copyHtml = urlBuildUserRecordLogCopyHtml($displayNo, $auditMetaText, $summary, $content, $attachmentList, $uploadWebDir, $followUpCopyFields);
            $copyText = urlBuildUserRecordLogCopyText($displayNo, $auditMetaText, $summary, $content, $attachmentList, $uploadWebDir, $followUpCopyFields);

            $rowClass = ($count % 2 === 1) ? ' url-row-odd' : ' url-row-even';
            if ($isSystemRecord) {
                $rowClass .= ' url-system-record';
            }
            $html .= '<div class="card mb-3 url-log-row' . $rowClass . '">';
            $html .= '  <div class="card-header">';
            $html .= '    <div class="d-flex justify-content-between align-items-center gap-2">';
            $html .= '      <div><strong>#' . $displayNo . '</strong> <span class="ms-2 text-muted small">' . $auditMeta . '</span></div>';
            if ($isSystemRecord) {
                $html .= '      <i class="fa-solid fa-file-waveform fa-xl url-system-record-icon" title="System record" aria-label="System record"></i>';
            }
            $html .= '    </div>';
            $html .= '    <div class="d-flex align-items-center gap-2 mt-2">';
            $html .= '      <button type="button" class="btn btn-sm btn-rounded btn-secondary url-copy-btn" title="Copy User Log">Copy</button>';
            $html .= '      <button type="button" class="btn btn-sm btn-rounded btn-info text-white url-toggle-btn" data-target="url-body-' . $recordId . '">Collapse/Expand</button>';
            $html .= $editBtn;
            $html .= '    </div>';
            $html .= '  </div>';
            $html .= '  <div id="url-body-' . $recordId . '" class="card-body">';
            $html .= '    <div class="url-content-row d-flex justify-content-between align-items-start gap-2 flex-wrap">';
            $html .= '      <div class="mb-0 url-log-content-wrap">';
            if ($summary !== '') {
                $html .= '      <div class="mb-3"><strong>Summary:</strong><div class="url-log-summary mt-2">' . urlRenderUserRecordLogPlainTextHtml($summary) . '</div></div>';
            }
            $html .= '      <strong>Content:</strong><div class="url-log-content mt-2">' . urlRenderUserRecordLogContentHtml($content) . '</div></div>';
            if ($attachmentPreviewHtml !== '') {
                $html .= '      <div class="url-attachment-action ms-auto"><div class="url-attachment-title">Attachment</div>' . $attachmentPreviewHtml . '</div>';
            }
            $html .= '    </div>';
            if (!empty($followUpMetaItems)) {
                $html .= '    <div class="url-log-extra-fields mt-3">' . implode('<span class="url-log-extra-sep">|</span>', $followUpMetaItems) . '</div>';
            }
            $html .= '    <textarea class="url-edit-summary d-none">' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</textarea>';
            $html .= '    <input type="hidden" class="url-edit-message-shortcut-id" value="' . $messageShortcutId . '">';
            $html .= '    <textarea class="url-copy-html d-none">' . htmlspecialchars($copyHtml, ENT_QUOTES, 'UTF-8') . '</textarea>';
            $html .= '    <textarea class="url-copy-text d-none">' . htmlspecialchars($copyText, ENT_QUOTES, 'UTF-8') . '</textarea>';
            $html .= '    <textarea class="url-edit-content d-none">' . htmlspecialchars(urlRenderUserRecordLogContentHtml($content), ENT_QUOTES, 'UTF-8') . '</textarea>';
            $html .= '    <textarea class="url-edit-attachments d-none">' . htmlspecialchars(urlEncodeUserRecordLogAttachmentList($attachmentList), ENT_QUOTES, 'UTF-8') . '</textarea>';
            $html .= '    <input type="hidden" class="url-edit-next-follow-up-date" value="' . htmlspecialchars($nextFollowUpDate, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '    <input type="hidden" class="url-edit-follow-up-times" value="' . htmlspecialchars($followUpTimes, ENT_QUOTES, 'UTF-8') . '">';
            $html .= '    <input type="hidden" class="url-edit-follow-up-day" value="' . htmlspecialchars($followUpDay, ENT_QUOTES, 'UTF-8') . '">';
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

        if (post('url_action') !== '') {
            $urlAction = trim((string) post('url_action'));
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST' && filter_has_var(INPUT_POST, 'content')) {
            $urlAction = 'save';
            $urlIsFallback = true;
        }

        if ($urlAction === 'save' && !$urlIsFallback && !urlIsAjaxRequest()) {
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

        if ($urlAction === 'save_summary') {
            if ((int) $context['customer_id'] <= 0) {
                if ($urlIsFallback) {
                    urlFallbackResponse('Summary requires a customer record.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Summary requires a customer record.'));
            }

            $summary = urlNormalizeUserRecordLogPlainText(post('summary'));
            $hasSummaryColumn = urlUserRecordLogColumnExists($dbConnect, $tblName, 'summary');
            if (!$hasSummaryColumn) {
                if ($urlIsFallback) {
                    urlFallbackResponse('User record log summary field is not ready yet. Please run insert_table.php first.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'User record log summary field is not ready yet. Please run insert_table.php first.'));
            }

            $customerId = (int) $context['customer_id'];
            $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', (string) $tblName);
            $safeCustomerColumn = preg_replace('/[^A-Za-z0-9_]/', '', (string) $customerColumn);
            if ($safeCustomerColumn === '') {
                $safeCustomerColumn = 'cust_id';
            }

            $latestSql = "SELECT * FROM `" . $safeTable . "` WHERE `status`='A' AND `" . $safeCustomerColumn . "`='" . $customerId . "' ORDER BY `updated_at` DESC, `id` DESC LIMIT 1";
            $latestRst = mysqli_query($dbConnect, $latestSql);
            $latestRow = ($latestRst && $latestRst->num_rows > 0) ? $latestRst->fetch_assoc() : array();

            if (!empty($latestRow)) {
                $latestId = isset($latestRow['id']) ? (int) $latestRow['id'] : 0;
                $oldSummary = isset($latestRow['summary']) ? trim((string) $latestRow['summary']) : '';
                $sql = "UPDATE `" . $safeTable . "` SET `summary`=" . ($summary !== '' ? ("'" . urlEsc($dbConnect, $summary) . "'") : 'NULL') . ", `updated_by`='" . urlEsc($dbConnect, USER_ID) . "', `updated_at`=NOW() WHERE `id`='" . $latestId . "'";
                $ok = mysqli_query($dbConnect, $sql);

                $editFields = array('summary');
                $editOld = array($oldSummary);
                $editNew = array($summary);
                $customerAuditValue = $customerId > 0 ? (string) $customerId : 'Empty Value';
                $baseEditActMsg = function_exists('actMsgLog')
                    ? actMsgLog($latestId, $editFields, '', $editOld, $editNew, $tblName, 'Edit', (!empty($ok) ? '' : mysqli_error($dbConnect)))
                    : (USER_NAME . ' edited User Record Log Summary [ID=' . $latestId . ']');
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
                        urlFallbackResponse('Failed to save summary.', false, $context['return_url']);
                    }
                    urlJsonResponse(array('ok' => 0, 'message' => 'Failed to save summary.'));
                }

                if ($urlIsFallback) {
                    urlFallbackResponse('Summary saved successfully.', true, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 1, 'message' => 'Summary saved successfully.'));
            }

            if ($summary === '') {
                if ($urlIsFallback) {
                    urlFallbackResponse('Summary saved successfully.', true, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 1, 'message' => 'Summary saved successfully.'));
            }

            $insertColumns = array(
                $customerColumn,
                'content',
                'attachment',
                'summary',
                'created_by',
                'created_at',
                'updated_by',
                'updated_at',
                'status',
            );
            $insertValues = array(
                "'" . $customerId . "'",
                "''",
                "''",
                "'" . urlEsc($dbConnect, $summary) . "'",
                "'" . urlEsc($dbConnect, USER_ID) . "'",
                'NOW()',
                "'" . urlEsc($dbConnect, USER_ID) . "'",
                'NOW()',
                "'A'",
            );

            if (urlUserRecordLogColumnExists($dbConnect, $tblName, 'next_follow_up_date')) {
                $insertColumns[] = 'next_follow_up_date';
                $insertValues[] = 'NULL';
            }
            if (urlUserRecordLogColumnExists($dbConnect, $tblName, 'follow_up_times')) {
                $insertColumns[] = 'follow_up_times';
                $insertValues[] = 'NULL';
            }
            if (urlUserRecordLogColumnExists($dbConnect, $tblName, 'follow_up_day')) {
                $insertColumns[] = 'follow_up_day';
                $insertValues[] = 'NULL';
            }

            $sql = "INSERT INTO `" . $safeTable . "` (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
            $ok = mysqli_query($dbConnect, $sql);
            $newId = (int) $dbConnect->insert_id;

            $addFields = array('summary');
            $addNew = array($summary);
            $customerAuditValue = $customerId > 0 ? (string) $customerId : 'Empty Value';
            $baseAddActMsg = function_exists('actMsgLog')
                ? actMsgLog($newId, $addFields, $addNew, '', '', $tblName, 'Add', (!empty($ok) ? '' : mysqli_error($dbConnect)))
                : (USER_NAME . ' added User Record Log Summary [ID=' . $newId . ']');
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
                    urlFallbackResponse('Failed to save summary.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Failed to save summary.'));
            }

            if ($urlIsFallback) {
                urlFallbackResponse('Summary saved successfully.', true, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 1, 'message' => 'Summary saved successfully.'));
        }

        if ($urlAction !== 'save') {
            if ($urlIsFallback) {
                urlFallbackResponse('Invalid action.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Invalid action.'));
        }

        $recordId = (int) post('record_id');
        $content = urlNormalizeSubmittedUserRecordLogContent((string) post('content'));
        if (urlGetUserRecordLogContentPlainText($content) === '') {
            if ($urlIsFallback) {
                urlFallbackResponse('Content is required.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Content is required.'));
        }

        $submittedNextFollowUpDate = trim((string) post('next_follow_up_date'));
        $nextFollowUpDate = '';
        if ($submittedNextFollowUpDate !== '') {
            $nextFollowUpDate = urlNormalizeUserRecordLogDateValue($submittedNextFollowUpDate);
            if ($nextFollowUpDate === '') {
                if ($urlIsFallback) {
                    urlFallbackResponse('Next Follow-Up Date is invalid.', false, $context['return_url']);
                }
                urlJsonResponse(array('ok' => 0, 'message' => 'Next Follow-Up Date is invalid.'));
            }
        }
        $summary = urlNormalizeUserRecordLogPlainText(post('summary'));
        $messageShortcutId = (int) post('message_shortcut_id');
        $followUpTimes = urlNormalizeUserRecordLogShortText(post('follow_up_times'));
        $followUpDay = urlNormalizeUserRecordLogShortText(post('follow_up_day'));
        $hasSummaryColumn = urlUserRecordLogColumnExists($dbConnect, $tblName, 'summary');
        $hasMessageShortcutIdColumn = urlUserRecordLogColumnExists($dbConnect, $tblName, 'message_shortcut_id');
        $hasNextFollowUpDateColumn = urlUserRecordLogColumnExists($dbConnect, $tblName, 'next_follow_up_date');
        $hasFollowUpTimesColumn = urlUserRecordLogColumnExists($dbConnect, $tblName, 'follow_up_times');
        $hasFollowUpDayColumn = urlUserRecordLogColumnExists($dbConnect, $tblName, 'follow_up_day');
        $hasAttachmentSequenceColumn = urlUserRecordLogColumnExists($dbConnect, $tblName, 'attachment_sequence');
        $followUpColumnsReady = $hasNextFollowUpDateColumn && $hasFollowUpTimesColumn && $hasFollowUpDayColumn;
        $hasFollowUpFieldInput = ($nextFollowUpDate !== '' || $followUpTimes !== '' || $followUpDay !== '');
        if ($hasFollowUpFieldInput && !$followUpColumnsReady) {
            if ($urlIsFallback) {
                urlFallbackResponse('User record log follow-up fields are not ready yet. Please run insert_table.php first.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'User record log follow-up fields are not ready yet. Please run insert_table.php first.'));
        }
        if ($summary !== '' && !$hasSummaryColumn) {
            if ($urlIsFallback) {
                urlFallbackResponse('User record log summary field is not ready yet. Please run insert_table.php first.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'User record log summary field is not ready yet. Please run insert_table.php first.'));
        }
        if ($messageShortcutId > 0 && !$hasMessageShortcutIdColumn) {
            if ($urlIsFallback) {
                urlFallbackResponse('User record log message shortcut field is not ready yet. Please run insert_table.php first.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'User record log message shortcut field is not ready yet. Please run insert_table.php first.'));
        }
        if ($messageShortcutId > 0 && empty(urlGetUserRecordLogMessageShortcutById($dbConnect, $messageShortcutId))) {
            if ($urlIsFallback) {
                urlFallbackResponse('Selected message shortcut is invalid.', false, $context['return_url']);
            }
            urlJsonResponse(array('ok' => 0, 'message' => 'Selected message shortcut is invalid.'));
        }

        $submittedAttachments = array();
        if (filter_has_var(INPUT_POST, 'existing_attachments')) {
            $submittedAttachments = urlDecodeUserRecordLogAttachmentList('', (string) post('existing_attachments'));
        } else if (filter_has_var(INPUT_POST, 'existing_attachment')) {
            $submittedAttachments = urlNormalizeUserRecordLogAttachmentList(post('existing_attachment'));
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

            if (filter_has_var(INPUT_POST, 'existing_attachments')) {
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
            $attachmentSequenceValue = urlEncodeUserRecordLogAttachmentList($attachmentNames);
            $updateParts = array(
                "content='" . urlEsc($dbConnect, $content) . "'",
                "attachment='" . urlEsc($dbConnect, $attachmentValue) . "'",
            );
            if ($hasAttachmentSequenceColumn) {
                $updateParts[] = "attachment_sequence='" . urlEsc($dbConnect, $attachmentSequenceValue) . "'";
            }
            if ($hasSummaryColumn) {
                $updateParts[] = "summary=" . ($summary !== '' ? ("'" . urlEsc($dbConnect, $summary) . "'") : 'NULL');
            }
            if ($hasMessageShortcutIdColumn) {
                $updateParts[] = "message_shortcut_id=" . ($messageShortcutId > 0 ? $messageShortcutId : 'NULL');
            }
            if ($hasNextFollowUpDateColumn) {
                $updateParts[] = "next_follow_up_date=" . ($nextFollowUpDate !== '' ? ("'" . urlEsc($dbConnect, $nextFollowUpDate) . "'") : 'NULL');
            }
            if ($hasFollowUpTimesColumn) {
                $updateParts[] = "follow_up_times=" . ($followUpTimes !== '' ? ("'" . urlEsc($dbConnect, $followUpTimes) . "'") : 'NULL');
            }
            if ($hasFollowUpDayColumn) {
                $updateParts[] = "follow_up_day=" . ($followUpDay !== '' ? ("'" . urlEsc($dbConnect, $followUpDay) . "'") : 'NULL');
            }
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
            $oldAttachmentSequence = isset($currentRow['attachment_sequence']) ? (string) $currentRow['attachment_sequence'] : '';
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
            if ($hasAttachmentSequenceColumn && $oldAttachmentSequence !== $attachmentSequenceValue) {
                $editFields[] = 'attachment_sequence';
                $editOld[] = $oldAttachmentSequence;
                $editNew[] = $attachmentSequenceValue;
            }
            if ($hasSummaryColumn) {
                $oldSummary = isset($currentRow['summary']) ? trim((string) $currentRow['summary']) : '';
                if ($oldSummary !== $summary) {
                    $editFields[] = 'summary';
                    $editOld[] = $oldSummary;
                    $editNew[] = $summary;
                }
            }
            if ($hasMessageShortcutIdColumn) {
                $oldMessageShortcutId = isset($currentRow['message_shortcut_id']) ? (int) $currentRow['message_shortcut_id'] : 0;
                if ($oldMessageShortcutId !== $messageShortcutId) {
                    $editFields[] = 'message_shortcut_id';
                    $editOld[] = $oldMessageShortcutId > 0 ? (string) $oldMessageShortcutId : '';
                    $editNew[] = $messageShortcutId > 0 ? (string) $messageShortcutId : '';
                }
            }
            if ($hasNextFollowUpDateColumn) {
                $oldNextFollowUpDate = isset($currentRow['next_follow_up_date']) ? trim((string) $currentRow['next_follow_up_date']) : '';
                if ($oldNextFollowUpDate !== $nextFollowUpDate) {
                    $editFields[] = 'next_follow_up_date';
                    $editOld[] = $oldNextFollowUpDate;
                    $editNew[] = $nextFollowUpDate;
                }
            }
            if ($hasFollowUpTimesColumn) {
                $oldFollowUpTimes = isset($currentRow['follow_up_times']) ? trim((string) $currentRow['follow_up_times']) : '';
                if ($oldFollowUpTimes !== $followUpTimes) {
                    $editFields[] = 'follow_up_times';
                    $editOld[] = $oldFollowUpTimes;
                    $editNew[] = $followUpTimes;
                }
            }
            if ($hasFollowUpDayColumn) {
                $oldFollowUpDay = isset($currentRow['follow_up_day']) ? trim((string) $currentRow['follow_up_day']) : '';
                if ($oldFollowUpDay !== $followUpDay) {
                    $editFields[] = 'follow_up_day';
                    $editOld[] = $oldFollowUpDay;
                    $editNew[] = $followUpDay;
                }
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
        $attachmentSequenceValue = urlEncodeUserRecordLogAttachmentList($attachmentNames);
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
        if ($hasSummaryColumn) {
            $insertColumns[] = 'summary';
            $insertValues[] = $summary !== '' ? ("'" . urlEsc($dbConnect, $summary) . "'") : 'NULL';
        }
        if ($hasAttachmentSequenceColumn) {
            $insertColumns[] = 'attachment_sequence';
            $insertValues[] = "'" . urlEsc($dbConnect, $attachmentSequenceValue) . "'";
        }
        if ($hasMessageShortcutIdColumn) {
            $insertColumns[] = 'message_shortcut_id';
            $insertValues[] = $messageShortcutId > 0 ? (string) $messageShortcutId : 'NULL';
        }
        if ($hasNextFollowUpDateColumn) {
            $insertColumns[] = 'next_follow_up_date';
            $insertValues[] = $nextFollowUpDate !== '' ? ("'" . urlEsc($dbConnect, $nextFollowUpDate) . "'") : 'NULL';
        }
        if ($hasFollowUpTimesColumn) {
            $insertColumns[] = 'follow_up_times';
            $insertValues[] = $followUpTimes !== '' ? ("'" . urlEsc($dbConnect, $followUpTimes) . "'") : 'NULL';
        }
        if ($hasFollowUpDayColumn) {
            $insertColumns[] = 'follow_up_day';
            $insertValues[] = $followUpDay !== '' ? ("'" . urlEsc($dbConnect, $followUpDay) . "'") : 'NULL';
        }
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
        if ($hasAttachmentSequenceColumn) {
            $addFields[] = 'attachment_sequence';
            $addNew[] = $attachmentSequenceValue;
        }
        if ($hasSummaryColumn) {
            $addFields[] = 'summary';
            $addNew[] = $summary;
        }
        if ($hasMessageShortcutIdColumn) {
            $addFields[] = 'message_shortcut_id';
            $addNew[] = $messageShortcutId > 0 ? (string) $messageShortcutId : '';
        }
        if ($hasNextFollowUpDateColumn) {
            $addFields[] = 'next_follow_up_date';
            $addNew[] = $nextFollowUpDate;
        }
        if ($hasFollowUpTimesColumn) {
            $addFields[] = 'follow_up_times';
            $addNew[] = $followUpTimes;
        }
        if ($hasFollowUpDayColumn) {
            $addFields[] = 'follow_up_day';
            $addNew[] = $followUpDay;
        }
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
        $currentSummary = urlGetLatestUserRecordLogSummary($dbConnect, $tblName, $context);
        if ($dbConnect instanceof mysqli && defined('MESSAGE_SHORTCUTS') && function_exists('generateDBData')) {
            generateDBData((string) MESSAGE_SHORTCUTS, $dbConnect);
        }
        $messageShortcutOptions = urlGetUserRecordLogMessageShortcutOptions($dbConnect);
        $config = array(
            'ajaxUrl' => isset($context['ajax_url']) ? (string) $context['ajax_url'] : (rtrim((string) $GLOBALS['SITEURL'], '/') . '/users/user_record_log.php'),
            'customerId' => isset($context['customer_id']) ? (int) $context['customer_id'] : 0,
            'customerColumn' => isset($context['customer_column']) ? (string) $context['customer_column'] : '',
            'pathReturn' => $pathReturn,
            'siteUrl' => rtrim((string) $GLOBALS['SITEURL'], '/'),
            'uploadWebDir' => trim((string) urlGetUserRecordLogUploadWebDir(), '/'),
            'currentSummary' => $currentSummary,
            'messageShortcuts' => $messageShortcutOptions,
            'messageShortcutTable' => defined('MESSAGE_SHORTCUTS') ? (string) MESSAGE_SHORTCUTS : '',
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

                .user-record-log-module .url-log-row.url-system-record .card-header {
                    background-color: #ffe6e6;
                    position: relative;
                }

                .user-record-log-module .url-system-record-icon {
                    position: absolute;
                    top: 50%;
                    right: 1rem;
                    transform: translateY(-50%);
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

                .user-record-log-module .url-log-content > p,
                .user-record-log-module .url-log-content > ul,
                .user-record-log-module .url-log-content > ol,
                .user-record-log-module .url-log-content > blockquote,
                .user-record-log-module .url-log-content > h1,
                .user-record-log-module .url-log-content > h2,
                .user-record-log-module .url-log-content > h3,
                .user-record-log-module .url-log-content > h4 {
                    margin-top: 0;
                    margin-bottom: 1rem;
                }

                .user-record-log-module .url-log-content > * + * {
                    margin-top: 0.9rem;
                }

                .user-record-log-module .url-log-content > h1,
                .user-record-log-module .url-log-content > h2,
                .user-record-log-module .url-log-content > h3,
                .user-record-log-module .url-log-content > h4 {
                    line-height: 1.35;
                    margin-top: 1.5rem !important;
                    margin-bottom: 1rem !important;
                }

                .user-record-log-module .url-log-content > h1:first-child,
                .user-record-log-module .url-log-content > h2:first-child,
                .user-record-log-module .url-log-content > h3:first-child,
                .user-record-log-module .url-log-content > h4:first-child {
                    margin-top: 0 !important;
                }

                .user-record-log-module .url-log-content > :last-child {
                    margin-bottom: 0 !important;
                }

                .user-record-log-module .url-log-content > p:has(> br:only-child) {
                    min-height: 1.25rem;
                }

                .user-record-log-module .url-log-content p {
                    margin: 0 !important;
                    padding-top: 0;
                    padding-bottom: 1rem;
                }

                .user-record-log-module .url-log-content p + p {
                    margin-top: 0 !important;
                    padding-top: 0.25rem;
                }

                .user-record-log-module .url-log-content h1,
                .user-record-log-module .url-log-content h2,
                .user-record-log-module .url-log-content h3,
                .user-record-log-module .url-log-content h4 {
                    line-height: 1.35;
                    margin: 0 !important;
                    padding-top: 1.25rem;
                    padding-bottom: 0.75rem;
                }

                .user-record-log-module .url-log-content > h1:first-child,
                .user-record-log-module .url-log-content > h2:first-child,
                .user-record-log-module .url-log-content > h3:first-child,
                .user-record-log-module .url-log-content > h4:first-child {
                    padding-top: 0;
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
                    position: relative;
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

                .user-record-log-module .url-attachment-sequence {
                    position: absolute;
                    top: 5px;
                    left: 5px;
                    z-index: 2;
                    min-width: 22px;
                    height: 22px;
                    padding: 0 6px;
                    border-radius: 999px;
                    background: rgba(20, 39, 75, 0.88);
                    color: #fff;
                    font-size: 0.72rem;
                    font-weight: 700;
                    line-height: 22px;
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

                .user-record-log-module .url-summary-wrap {
                    border: 1px solid #d9e1f2;
                    border-radius: 12px;
                    background: #f8fbff;
                    padding: 16px;
                }

                .user-record-log-module .url-summary-note {
                    color: #5e6c84;
                    font-size: 0.82rem;
                }

                .user-record-log-module .url-log-summary {
                    white-space: normal;
                    word-break: break-word;
                    line-height: 1.6;
                }

                .user-record-log-module .si-attach-wrap {
                    border: 1px solid #e2e2e2;
                    border-radius: 8px;
                    padding: 12px;
                }

                .user-record-log-module .url-attachment-form-footer {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                    margin-top: 14px;
                    padding-top: 14px;
                    border-top: 1px solid #eceff4;
                }

                .user-record-log-module .url-save-log-btn {
                    min-width: 168px;
                    padding: 0.72rem 1.35rem;
                    font-size: 0.98rem;
                    font-weight: 600;
                }

                .user-record-log-module .url-cancel-log-btn {
                    padding: 0.72rem 1.15rem;
                    font-size: 0.95rem;
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
                    align-items: flex-start;
                    gap: 6px;
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

                .user-record-log-module .url-attachment-sequence-controls {
                    display: flex;
                    gap: 4px;
                    flex: 0 0 auto;
                }

                .user-record-log-module .url-attachment-sequence-btn {
                    min-width: 30px;
                    padding: 0.25rem 0.45rem;
                }

                .user-record-log-module .url-attachment-sequence-label {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 24px;
                    font-size: 0.8rem;
                    font-weight: 700;
                    color: #2756c4;
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

                .user-record-log-module .url-log-extra-fields {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.5rem;
                    font-size: 0.9rem;
                    color: #4f5a6b;
                }

                .user-record-log-module .url-log-extra-sep {
                    color: #93a0b4;
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
                    width: min(1600px, 98vw);
                    height: min(94vh, 1100px);
                    background: #ffffff;
                    border-radius: 8px;
                    padding: 24px;
                    overflow: hidden;
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
                    min-height: 0;
                    flex-direction: column;
                    padding-top: 16px;
                }

                .user-record-log-module .url-attachment-preview-media {
                    display: flex;
                    flex: 1 1 auto;
                    align-items: center;
                    justify-content: center;
                    width: 100%;
                    min-height: 0;
                    overflow: hidden;
                }

                .user-record-log-module .url-attachment-preview-media img {
                    max-width: 100%;
                    max-height: 100%;
                    object-fit: contain;
                }

                .user-record-log-module .url-attachment-preview-media iframe {
                    width: 100%;
                    height: 100%;
                    border: 0;
                }

                .user-record-log-module .url-attachment-modal-navigation {
                    display: flex;
                    flex: 0 0 auto;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    width: 100%;
                    min-height: 48px;
                    margin-top: 12px;
                }

                .user-record-log-module .tox.tox-tinymce {
                    position: relative;
                    border: 2px solid #006ce7 !important;
                    border-radius: 10px;
                    box-shadow: none !important;
                    box-sizing: border-box;
                    overflow: hidden;
                    resize: vertical;
                    min-height: 280px;
                    max-height: none;
                }

                .user-record-log-module .url-editor-resize-handle {
                    position: absolute;
                    right: 5px;
                    bottom: 5px;
                    z-index: 10;
                    width: 30px;
                    height: 26px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 2px solid #006ce7;
                    border-radius: 6px;
                    background: #e4efff;
                    color: #006ce7;
                    box-shadow: 0 1px 4px rgba(9, 30, 66, 0.24);
                    font-size: 20px;
                    font-weight: 700;
                    line-height: 1;
                    pointer-events: none;
                }

                .user-record-log-module .tox.tox-tinymce.tox-edit-focus {
                    border: 2px solid #006ce7 !important;
                    box-shadow: none !important;
                }

                .user-record-log-module .tox.tox-tinymce.tox-edit-focus .tox-edit-area::before {
                    opacity: 0 !important;
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

                    .user-record-log-module .url-attachment-form-footer {
                        justify-content: center;
                    }

                    .user-record-log-module .url-save-log-btn,
                    .user-record-log-module .url-cancel-log-btn {
                        width: 100%;
                    }
                }
            </style>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="url-summary-wrap">
                        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                            <label class="form-label mb-0" for="url_summary">Summary</label>
                            <button type="button" class="btn btn-sm btn-rounded btn-outline-primary" id="url_summary_submit_btn">Save Summary</button>
                        </div>
                        <textarea class="form-control" id="url_summary" rows="4" placeholder="Enter summary"><?php echo htmlspecialchars($currentSummary, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            </div>

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
                        <input type="hidden" name="message_shortcut_id" id="url_message_shortcut_id" value="">
                        <input type="hidden" name="customer_id" value="<?php echo isset($context['customer_id']) ? (int) $context['customer_id'] : 0; ?>">
                        <input type="hidden" name="customer_column" value="<?php echo htmlspecialchars(isset($context['customer_column']) ? (string) $context['customer_column'] : '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($pathReturn, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label class="form-label" for="url_message_shortcut_label">Message Shortcut</label>
                            <div class="autocomplete">
                                <input type="text" class="form-control" id="url_message_shortcut_label" placeholder="Type to search message shortcut" autocomplete="off">
                            </div>
                            <div class="url-editor-note">Selecting a shortcut will replace the current content with the shortcut message.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="url_content">Content</label>
                            <textarea class="form-control" id="url_content" name="content" rows="4" required></textarea>
                            <div class="url-editor-note">Supports paragraphs, bold text, emoji, and bullet or numbered lists.</div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="url_next_follow_up_date">Next Follow-Up Date</label>
                                <input type="date" class="form-control" id="url_next_follow_up_date" name="next_follow_up_date">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="url_follow_up_times">Follow-Up Times</label>
                                <input type="text" class="form-control" id="url_follow_up_times" name="follow_up_times">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="url_follow_up_day">Follow-Up Day</label>
                                <input type="text" class="form-control" id="url_follow_up_day" name="follow_up_day">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="url_attachment">Attachment</label>
                            <div class="si-attach-wrap">
                                <div class="row g-3 align-items-start">
                                    <div class="col-12 col-md-6">
                                            <div id="url_attachment_inputs">
                                                <div class="mb-2 si-attachment-input-row">
                                                    <input class="form-control user-record-log-attachment-input" type="file" name="attachment[]" id="url_attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,application/pdf">
                                                    <div class="url-attachment-sequence-controls">
                                                        <button class="btn btn-sm btn-outline-secondary url-move-upload-attachment-btn" type="button" data-direction="up" title="Move attachment earlier" aria-label="Move attachment earlier">&#8593;</button>
                                                        <button class="btn btn-sm btn-outline-secondary url-move-upload-attachment-btn" type="button" data-direction="down" title="Move attachment later" aria-label="Move attachment later">&#8595;</button>
                                                        <button class="mt-1 add-user-record-log-attachment-btn" id="action_menu_btn" type="button" title="Add another attachment"><i class="fa-regular fa-square-plus fa-xl" style="color:#37c22e"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        <small class="text-muted">Click + to add more attachments. Use the arrows to arrange the upload sequence. Supports image and PDF files.</small>
                                        <div class="mt-2" id="url_existing_attachment_links"></div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="si-attach-preview">
                                            <div id="url_attachment_img_list" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;"></div>
                                            <span id="url_attachment_placeholder" class="text-muted">Image / PDF preview</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="url-attachment-form-footer">
                                    <button type="button" class="btn btn-sm btn-rounded btn-secondary url-cancel-log-btn" id="url_cancel_edit_btn" style="display:none;">Cancel Edit</button>
                                    <button type="submit" class="btn btn-rounded btn-primary url-save-log-btn" id="url_submit_btn">Save User Log</button>
                                </div>
                            </div>
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
        <script src="<?php echo htmlspecialchars(rtrim((string) $GLOBALS['SITEURL'], '/') . '/js/user_record_log.js?v=' . @filemtime(ROOT . '/js/user_record_log.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php
    }
}
