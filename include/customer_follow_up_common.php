<?php

if (defined('ROOT')) {
    include_once ROOT . '/include/customer_tag.php';
    include_once ROOT . '/include/system_alert_common.php';
    include_once ROOT . '/include/user_record_log.php';
}

if (!function_exists('customerFollowUpEscape')) {
    function customerFollowUpEscape($connect, $value)
    {
        return mysqli_real_escape_string($connect, (string) $value);
    }
}

if (!function_exists('customerFollowUpNowDate')) {
    function customerFollowUpNowDate()
    {
        return date('Y-m-d');
    }
}

if (!function_exists('customerFollowUpNowTime')) {
    function customerFollowUpNowTime()
    {
        return date('H:i:s');
    }
}

if (!function_exists('customerFollowUpNormalizePlatform')) {
    function customerFollowUpNormalizePlatform($platform)
    {
        $platform = strtolower(trim((string) $platform));
        $allowed = array('shopee', 'lazada', 'facebook', 'website', 'customer_info');
        return in_array($platform, $allowed, true) ? $platform : '';
    }
}

if (!function_exists('customerFollowUpNormalizeCaseSource')) {
    /**
     * 'order'    = case opened when an order was marked Received (the original flow).
     * 'customer' = case opened straight from a customer page follow-up entry, no order.
     */
    function customerFollowUpNormalizeCaseSource($caseSource)
    {
        $caseSource = strtolower(trim((string) $caseSource));
        return $caseSource === 'customer' ? 'customer' : 'order';
    }
}

if (!function_exists('customerFollowUpSupportsCaseSource')) {
    function customerFollowUpSupportsCaseSource($connect)
    {
        return $connect instanceof mysqli
            && function_exists('shopeeOmsTableHasColumn')
            && defined('dbname')
            && shopeeOmsTableHasColumn($connect, dbname, CUSTOMER_FOLLOW_UP, 'case_source');
    }
}

if (!function_exists('customerFollowUpGetMaxRoundNo')) {
    /**
     * The last round a case can reach. Completing it marks the case Done, and the lost
     * cron measures from that completion, so every place that means "the final round"
     * reads this rather than hardcoding the number.
     */
    function customerFollowUpGetMaxRoundNo()
    {
        return 20;
    }
}

if (!function_exists('customerFollowUpSupportsRoundSuperseded')) {
    function customerFollowUpSupportsRoundSuperseded($connect)
    {
        return $connect instanceof mysqli
            && function_exists('shopeeOmsTableHasColumn')
            && defined('dbname')
            && shopeeOmsTableHasColumn($connect, dbname, CUSTOMER_FOLLOW_UP_ROUND, 'superseded');
    }
}

if (!function_exists('customerFollowUpBuildCurrentRoundCondition')) {
    /**
     * Excludes rounds left behind by an earlier order on the same customer case. Without
     * it a case taken over by a newer order matches both the old and the new round of the
     * same number, which duplicates the case in every listing that joins on the round.
     */
    function customerFollowUpBuildCurrentRoundCondition($connect, $roundAlias = 'r')
    {
        if (!customerFollowUpSupportsRoundSuperseded($connect)) {
            return '';
        }

        $roundAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $roundAlias);
        $prefix = $roundAlias !== '' ? ('`' . $roundAlias . '`.') : '';

        return " AND IFNULL(" . $prefix . "`superseded`, 'N') <> 'Y'";
    }
}

if (!function_exists('customerFollowUpSupportsPreviousFollowUpInfo')) {
    function customerFollowUpSupportsPreviousFollowUpInfo($connect)
    {
        return $connect instanceof mysqli
            && function_exists('shopeeOmsTableHasColumn')
            && defined('dbname')
            && shopeeOmsTableHasColumn($connect, dbname, CUSTOMER_FOLLOW_UP, 'previous_follow_up_info');
    }
}

if (!function_exists('customerFollowUpDecodePreviousFollowUpInfo')) {
    function customerFollowUpDecodePreviousFollowUpInfo($rawValue)
    {
        $rawValue = trim((string) $rawValue);
        if ($rawValue === '') {
            return array();
        }

        $decoded = json_decode($rawValue, true);

        return is_array($decoded) ? $decoded : array();
    }
}

if (!function_exists('customerFollowUpIsOrderlessCase')) {
    function customerFollowUpIsOrderlessCase($followUpRow)
    {
        if (!is_array($followUpRow) || empty($followUpRow)) {
            return false;
        }

        if (isset($followUpRow['case_source'])
            && customerFollowUpNormalizeCaseSource($followUpRow['case_source']) === 'customer'
        ) {
            return true;
        }

        return (int) (isset($followUpRow['order_id']) ? $followUpRow['order_id'] : 0) <= 0;
    }
}

if (!function_exists('customerFollowUpNormalizeStatus')) {
    function customerFollowUpNormalizeStatus($status)
    {
        $status = strtolower(trim((string) $status));
        $map = array(
            'pending follow-up' => 'Pending Follow-Up',
            'pending_follow_up' => 'Pending Follow-Up',
            'pending approval' => 'Pending Approval',
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'missed follow-up' => 'Missed Follow-Up',
            'missed_follow_up' => 'Missed Follow-Up',
            'missed' => 'Missed Follow-Up',
            'done' => 'Done',
            'postponed' => 'Postponed',
            'lost' => 'Lost',
        );
        return isset($map[$status]) ? $map[$status] : trim((string) $status);
    }
}

if (!function_exists('customerFollowUpGetAdminAccessIds')) {
    function customerFollowUpGetAdminAccessIds()
    {
        return array(1, 2);
    }
}

if (!function_exists('customerFollowUpGetPinGroupId')) {
    function customerFollowUpGetPinGroupId()
    {
        return 151;
    }
}

if (!function_exists('customerFollowUpParsePinBlockMap')) {
    function customerFollowUpParsePinBlockMap($rawPins)
    {
        $pinMap = array();
        foreach (explode('+', (string) $rawPins) as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }

            $parts = explode(':', trim($entry, '[]'), 2);
            if (count($parts) !== 2) {
                continue;
            }

            $groupId = trim((string) $parts[0]);
            if ($groupId === '' || !ctype_digit($groupId)) {
                continue;
            }

            $pinIds = array();
            foreach (explode(',', (string) $parts[1]) as $pinId) {
                $pinId = trim((string) $pinId);
                if ($pinId !== '' && ctype_digit($pinId)) {
                    $pinIds[] = (int) $pinId;
                }
            }

            $pinMap[(int) $groupId] = array_values(array_unique($pinIds));
        }

        return $pinMap;
    }
}

if (!function_exists('customerFollowUpGetUserPinIdsForGroup')) {
    function customerFollowUpGetUserPinIdsForGroup($connect, $userId, $pinGroupId = 0)
    {
        $userId = (int) $userId;
        $pinGroupId = (int) $pinGroupId > 0 ? (int) $pinGroupId : customerFollowUpGetPinGroupId();
        if (!($connect instanceof mysqli) || $userId <= 0 || $pinGroupId <= 0) {
            return array();
        }

        $userResult = getData('access_id', "id = '" . $userId . "' AND status = 'A'", 'LIMIT 1', USR_USER, $connect);
        if (!$userResult || $userResult->num_rows === 0) {
            return array();
        }

        $userRow = $userResult->fetch_assoc();
        $accessId = isset($userRow['access_id']) ? (int) $userRow['access_id'] : 0;
        if ($accessId <= 0) {
            return array();
        }

        $pinGroupResult = getData('pins', "id = '" . $pinGroupId . "' AND status = 'A'", 'LIMIT 1', PIN_GRP, $connect);
        if (!$pinGroupResult || $pinGroupResult->num_rows === 0) {
            return array();
        }

        $pinGroupRow = $pinGroupResult->fetch_assoc();
        $allowedPinIds = array();
        foreach (explode(',', (string) (isset($pinGroupRow['pins']) ? $pinGroupRow['pins'] : '')) as $pinId) {
            $pinId = trim((string) $pinId);
            if ($pinId !== '' && ctype_digit($pinId)) {
                $allowedPinIds[] = (int) $pinId;
            }
        }

        $userGroupResult = getData('pins', "id = '" . $accessId . "'", 'LIMIT 1', 'user_group', $connect);
        if (!$userGroupResult || $userGroupResult->num_rows === 0) {
            return array();
        }

        $userGroupRow = $userGroupResult->fetch_assoc();
        $pinMap = customerFollowUpParsePinBlockMap(isset($userGroupRow['pins']) ? $userGroupRow['pins'] : '');
        $grantedPinIds = isset($pinMap[$pinGroupId]) ? $pinMap[$pinGroupId] : array();

        return array_values(array_unique(array_intersect($allowedPinIds, $grantedPinIds)));
    }
}

if (!function_exists('customerFollowUpUserHasPinAccess')) {
    function customerFollowUpUserHasPinAccess($connect, $userId, $pinId, $pinGroupId = 0)
    {
        return in_array((int) $pinId, customerFollowUpGetUserPinIdsForGroup($connect, $userId, $pinGroupId), true);
    }
}

if (!function_exists('customerFollowUpIsAdminUser')) {
    function customerFollowUpIsAdminUser($userGroupId = null)
    {
        if ($userGroupId === null && defined('USER_GROUP')) {
            $userGroupId = USER_GROUP;
        }

        return in_array((int) $userGroupId, customerFollowUpGetAdminAccessIds(), true);
    }
}

if (!function_exists('customerFollowUpGetUserDisplayName')) {
    function customerFollowUpGetUserDisplayName($connect, $userId)
    {
        static $cache = array();

        $userId = (int) $userId;
        if ($userId <= 0) {
            return '';
        }

        if (isset($cache[$userId])) {
            return $cache[$userId];
        }

        $result = getData('name,username', "id = '" . $userId . "'", 'LIMIT 1', USR_USER, $connect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (!empty($row['name'])) {
                $cache[$userId] = (string) $row['name'];
                return $cache[$userId];
            }
            if (!empty($row['username'])) {
                $cache[$userId] = (string) $row['username'];
                return $cache[$userId];
            }
        }

        $cache[$userId] = 'User #' . $userId;
        return $cache[$userId];
    }
}

if (!function_exists('customerFollowUpGetUserMetaMap')) {
    function customerFollowUpGetUserMetaMap($connect, $userIds)
    {
        $userMetaMap = array();
        $safeIds = array();

        foreach ((array) $userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0) {
                $safeIds[$userId] = $userId;
            }
        }

        if (empty($safeIds)) {
            return $userMetaMap;
        }

        $sql = "SELECT `id`, `name`, `username`, `access_id`
                FROM `" . USR_USER . "`
                WHERE `id` IN (" . implode(',', $safeIds) . ")
                  AND `status` = 'A'";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $userMetaMap;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $userId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($userId <= 0) {
                continue;
            }

            $displayName = trim((string) (isset($row['name']) && $row['name'] !== '' ? $row['name'] : $row['username']));
            if ($displayName === '') {
                $displayName = 'User #' . $userId;
            }

            $userMetaMap[$userId] = array(
                'id' => $userId,
                'display_name' => $displayName,
                'username' => isset($row['username']) ? (string) $row['username'] : '',
                'access_id' => isset($row['access_id']) ? (int) $row['access_id'] : 0,
            );
        }

        return $userMetaMap;
    }
}

if (!function_exists('customerFollowUpGetMessageShortcutOptions')) {
    function customerFollowUpGetMessageShortcutOptions($connect)
    {
        $rows = array();
        $result = getData('id,shortcuts_tag,shortcuts_message', '', '', MESSAGE_SHORTCUTS, $connect);
        if (!$result) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('customerFollowUpCleanText')) {
    function customerFollowUpCleanText($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        $value = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $value);
        $value = preg_replace('/<\s*\/\s*(p|div|li|tr|h[1-6])\s*>/i', "\n", $value);
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/\n{3,}/", "\n\n", (string) $value);
        $value = preg_replace('/[ \t]+/', ' ', (string) $value);
        $value = preg_replace('/[ \t]*\n[ \t]*/', "\n", (string) $value);

        return trim((string) $value);
    }
}

if (!function_exists('customerFollowUpIsEmptyDateValue')) {
    function customerFollowUpIsEmptyDateValue($value)
    {
        $value = trim((string) $value);
        return $value === '' || $value === '0000-00-00';
    }
}

if (!function_exists('customerFollowUpIsValidDateString')) {
    function customerFollowUpIsValidDateString($value)
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $timestamp = strtotime($value);
        return $timestamp !== false && date('Y-m-d', $timestamp) === $value;
    }
}

if (!function_exists('customerFollowUpNormalizeOptionalUserRecordLogContent')) {
    function customerFollowUpNormalizeOptionalUserRecordLogContent($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        $normalizedContent = '';
        if (function_exists('urlNormalizeSubmittedUserRecordLogContent')) {
            $normalizedContent = (string) urlNormalizeSubmittedUserRecordLogContent($content);
        } else {
            $normalizedContent = preg_replace('/\r\n|\r|\n/', '<br>', htmlspecialchars(strip_tags($content), ENT_QUOTES, 'UTF-8'));
        }

        $plainText = trim(html_entity_decode(strip_tags((string) $normalizedContent), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $plainText = str_replace("\xC2\xA0", ' ', $plainText);
        $plainText = trim($plainText);

        if ($plainText === '') {
            return '';
        }

        return $normalizedContent;
    }
}

if (!function_exists('customerFollowUpGetMessageShortcutById')) {
    function customerFollowUpGetMessageShortcutById($connect, $shortcutId)
    {
        $shortcutId = (int) $shortcutId;
        if ($shortcutId <= 0) {
            return array();
        }

        $result = getData('id,shortcuts_tag,shortcuts_message', "id = '" . $shortcutId . "'", 'LIMIT 1', MESSAGE_SHORTCUTS, $connect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $row['shortcuts_message_text'] = customerFollowUpCleanText(isset($row['shortcuts_message']) ? $row['shortcuts_message'] : '');
            return $row;
        }

        return array();
    }
}

if (!function_exists('customerFollowUpExtractPositiveIds')) {
    function customerFollowUpExtractPositiveIds($values)
    {
        if (function_exists('shopeeOmsExtractPositiveIds')) {
            return shopeeOmsExtractPositiveIds($values);
        }

        $ids = array();
        foreach (explode(',', (string) $values) as $value) {
            $value = trim((string) $value);
            if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('customerFollowUpResolveOrderPackageAndBrandIds')) {
    function customerFollowUpResolveOrderPackageAndBrandIds($connect, $platform, $orderRow = array())
    {
        $packageIds = array();
        $brandIds = array();
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderRow = is_array($orderRow) ? $orderRow : array();

        if (function_exists('customerLabelResolvePackageRows')) {
            foreach ((array) customerLabelResolvePackageRows($connect, $platform, $orderRow) as $packageRow) {
                $packageId = isset($packageRow['package_id']) ? (int) $packageRow['package_id'] : 0;
                if ($packageId > 0) {
                    $packageIds[] = $packageId;
                }
            }
        }

        foreach (array('brand', 'brand_id') as $brandField) {
            if (isset($orderRow[$brandField])) {
                $brandIds = array_merge($brandIds, customerFollowUpExtractPositiveIds($orderRow[$brandField]));
            }
        }

        if ($connect instanceof mysqli && empty($brandIds) && !empty($packageIds) && defined('PKG')) {
            $sql = "SELECT `brand`
                    FROM `" . PKG . "`
                    WHERE `id` IN (" . implode(',', array_map('intval', $packageIds)) . ")
                      AND `status` = 'A'";
            $result = mysqli_query($connect, $sql);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $brandIds = array_merge($brandIds, customerFollowUpExtractPositiveIds(isset($row['brand']) ? $row['brand'] : ''));
                }
            }
        }

        return array(
            'package_ids' => array_values(array_unique(array_filter(array_map('intval', $packageIds)))),
            'brand_ids' => array_values(array_unique(array_filter(array_map('intval', $brandIds)))),
        );
    }
}

if (!function_exists('customerFollowUpGetAttachmentRelativeDir')) {
    function customerFollowUpGetAttachmentRelativeDir($connect, $context = array())
    {
        $brandIds = isset($context['brand_ids']) ? (array) $context['brand_ids'] : array();
        $packageIds = isset($context['package_ids']) ? (array) $context['package_ids'] : array();

        if (function_exists('shopeeOmsBuildAirbillAttachmentRelativeDir')) {
            return shopeeOmsBuildAirbillAttachmentRelativeDir($connect, $brandIds, $packageIds, 'customer_follow_up');
        }

        return 'attachment/sqlaccount/' . substr((string) comYMD, 0, 4) . '/' . substr((string) comYMD, 4, 2) . '/customer_follow_up/';
    }
}

if (!function_exists('customerFollowUpNormalizeAttachmentPath')) {
    function customerFollowUpNormalizeAttachmentPath($attachmentPath)
    {
        $attachmentPath = trim(str_replace('\\', '/', (string) $attachmentPath), '/');

        if ($attachmentPath === '') {
            return '';
        }

        if (strpos($attachmentPath, '../') !== false || strpos($attachmentPath, '..\\') !== false) {
            return '';
        }

        return $attachmentPath;
    }
}

if (!function_exists('customerFollowUpStoreAttachmentUpload')) {
    function customerFollowUpStoreAttachmentUpload($fileInfo, $connect, $allowedExt = array('png', 'jpg', 'jpeg', 'pdf', 'webp'), $context = array())
    {
        if (!is_array($fileInfo) || !isset($fileInfo['tmp_name']) || !isset($fileInfo['name'])) {
            return array('success' => false, 'path' => '', 'message' => 'Attachment is required.');
        }

        if (is_array($fileInfo['name']) || is_array($fileInfo['tmp_name']) || (isset($fileInfo['error']) && is_array($fileInfo['error']))) {
            return array('success' => false, 'path' => '', 'message' => 'Only one attachment is allowed.');
        }

        $uploadError = isset($fileInfo['error']) ? (int) $fileInfo['error'] : UPLOAD_ERR_OK;
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return array('success' => false, 'path' => '', 'message' => 'Attachment is required.');
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to upload attachment.');
        }

        $originalName = basename((string) $fileInfo['name']);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            return array('success' => false, 'path' => '', 'message' => 'Only PNG, JPG, JPEG, WEBP, or PDF attachment is allowed.');
        }

        $relativeDir = customerFollowUpGetAttachmentRelativeDir($connect, $context);
        $targetFsDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($targetFsDir)) {
            @mkdir($targetFsDir, 0777, true);
        }

        if (!is_dir($targetFsDir)) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to create follow-up attachment directory.');
        }

        $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $safeBase = trim((string) $safeBase, '_');
        if ($safeBase === '') {
            $safeBase = 'follow_up_attachment';
        }

        $targetName = $safeBase . '_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $targetFile = $targetFsDir . $targetName;
        if (!move_uploaded_file((string) $fileInfo['tmp_name'], $targetFile)) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to upload attachment.');
        }

        return array(
            'success' => true,
            'path' => trim(str_replace('\\', '/', $relativeDir . $targetName), '/'),
            'message' => '',
        );
    }
}

if (!function_exists('customerFollowUpBuildAttachmentUrl')) {
    function customerFollowUpBuildAttachmentUrl($attachmentPath)
    {
        $attachmentPath = customerFollowUpNormalizeAttachmentPath($attachmentPath);
        if ($attachmentPath === '' || !defined('SITEURL')) {
            return '';
        }

        return rtrim((string) SITEURL, '/') . '/' . ltrim((string) $attachmentPath, '/');
    }
}

if (!function_exists('customerFollowUpReadFollowUpCase')) {
    function customerFollowUpReadFollowUpCase($connect, $followUpId)
    {
        $followUpId = (int) $followUpId;
        if ($followUpId <= 0) {
            return array();
        }

        $sql = "SELECT *
                FROM `" . CUSTOMER_FOLLOW_UP . "`
                WHERE `id` = " . $followUpId . "
                  AND `status` = 'A'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('customerFollowUpFetchActiveByOrderPlatform')) {
    function customerFollowUpFetchActiveByOrderPlatform($connect, $platform, $orderId)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderId = (int) $orderId;
        if ($platform === '' || $orderId <= 0) {
            return array();
        }

        $sql = "SELECT *
                FROM `" . CUSTOMER_FOLLOW_UP . "`
                WHERE `platform` = '" . customerFollowUpEscape($connect, $platform) . "'
                  AND `order_id` = " . $orderId . "
                  AND `status` = 'A'
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('customerFollowUpFetchRoundById')) {
    function customerFollowUpFetchRoundById($connect, $roundId)
    {
        $roundId = (int) $roundId;
        if ($roundId <= 0) {
            return array();
        }

        $sql = "SELECT *
                FROM `" . CUSTOMER_FOLLOW_UP_ROUND . "`
                WHERE `id` = " . $roundId . "
                  AND `status` = 'A'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('customerFollowUpFetchCurrentRound')) {
    function customerFollowUpFetchCurrentRound($connect, $followUpId, $roundNo = 0)
    {
        $followUpId = (int) $followUpId;
        if ($followUpId <= 0) {
            return array();
        }

        if ($roundNo <= 0) {
            $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
            $roundNo = isset($followUpRow['current_round_no']) ? (int) $followUpRow['current_round_no'] : 0;
        }

        if ($roundNo <= 0) {
            return array();
        }

        $sql = "SELECT *
                FROM `" . CUSTOMER_FOLLOW_UP_ROUND . "`
                WHERE `follow_up_id` = " . $followUpId . "
                  AND `round_no` = " . $roundNo . "
                  AND `status` = 'A'
                  " . customerFollowUpBuildCurrentRoundCondition($connect, '') . "
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('customerFollowUpCreateFollowUpCase')) {
    function customerFollowUpCreateFollowUpCase($connect, $data)
    {
        $platform = customerFollowUpNormalizePlatform(isset($data['platform']) ? $data['platform'] : '');
        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
        $caseSource = customerFollowUpNormalizeCaseSource(isset($data['case_source']) ? $data['case_source'] : '');
        if ($platform === '') {
            return 0;
        }
        // Customer-sourced cases are opened straight from a customer page and have no
        // order behind them; order-sourced cases still require a real order.
        if ($caseSource === 'order' && $orderId <= 0) {
            return 0;
        }
        if ($caseSource === 'customer' && (int) (isset($data['customer_id']) ? $data['customer_id'] : 0) <= 0) {
            return 0;
        }

        $customerType = trim((string) (isset($data['customer_type']) ? $data['customer_type'] : ''));
        $customerType = strtolower($customerType) === 'return' ? 'return' : 'new';
        $actorUserId = trim((string) (isset($data['create_by']) ? $data['create_by'] : (defined('USER_ID') ? USER_ID : '')));
        $createDate = isset($data['create_date']) ? (string) $data['create_date'] : customerFollowUpNowDate();
        $createTime = isset($data['create_time']) ? (string) $data['create_time'] : customerFollowUpNowTime();

        $hasCaseSourceColumn = customerFollowUpSupportsCaseSource($connect);

        $sql = "INSERT INTO `" . CUSTOMER_FOLLOW_UP . "` (
                    `platform`,
                    " . ($hasCaseSourceColumn ? "`case_source`,\n                    " : "") . "`order_id`,
                    `order_no`,
                    `customer_id`,
                    `customer_name`,
                    `customer_username`,
                    `package_name`,
                    `received_date`,
                    `customer_type`,
                    `purchase_count_snapshot`,
                    `current_round_no`,
                    `current_status`,
                    `contact_no`,
                    `assigned_user_id`,
                    `follow_up_started`,
                    `lost_tag_added`,
                    `lost_tag_id`,
                    `remark`,
                    `create_by`,
                    `create_date`,
                    `create_time`,
                    `update_by`,
                    `update_date`,
                    `update_time`,
                    `status`
                ) VALUES (
                    '" . customerFollowUpEscape($connect, $platform) . "',
                    " . ($hasCaseSourceColumn ? ("'" . customerFollowUpEscape($connect, $caseSource) . "',\n                    ") : "") . $orderId . ",
                    '" . customerFollowUpEscape($connect, isset($data['order_no']) ? $data['order_no'] : '') . "',
                    " . (int) (isset($data['customer_id']) ? $data['customer_id'] : 0) . ",
                    '" . customerFollowUpEscape($connect, isset($data['customer_name']) ? $data['customer_name'] : '') . "',
                    '" . customerFollowUpEscape($connect, isset($data['customer_username']) ? $data['customer_username'] : '') . "',
                    '" . customerFollowUpEscape($connect, isset($data['package_name']) ? $data['package_name'] : '') . "',
                    " . (trim((string) (isset($data['received_date']) ? $data['received_date'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['received_date']) . "'") : 'NULL') . ",
                    '" . customerFollowUpEscape($connect, $customerType) . "',
                    " . (int) (isset($data['purchase_count_snapshot']) ? $data['purchase_count_snapshot'] : 0) . ",
                    " . max(1, (int) (isset($data['current_round_no']) ? $data['current_round_no'] : 1)) . ",
                    '" . customerFollowUpEscape($connect, isset($data['current_status']) ? $data['current_status'] : '') . "',
                    " . (trim((string) (isset($data['contact_no']) ? $data['contact_no'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['contact_no']) . "'") : 'NULL') . ",
                    " . (int) (isset($data['assigned_user_id']) ? $data['assigned_user_id'] : 0) . ",
                    '" . customerFollowUpEscape($connect, isset($data['follow_up_started']) ? $data['follow_up_started'] : 'Y') . "',
                    '" . customerFollowUpEscape($connect, isset($data['lost_tag_added']) ? $data['lost_tag_added'] : 'N') . "',
                    " . ((int) (isset($data['lost_tag_id']) ? $data['lost_tag_id'] : 0) > 0 ? (int) $data['lost_tag_id'] : 'NULL') . ",
                    " . (trim((string) (isset($data['remark']) ? $data['remark'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['remark']) . "'") : 'NULL') . ",
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    '" . customerFollowUpEscape($connect, $createDate) . "',
                    '" . customerFollowUpEscape($connect, $createTime) . "',
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    '" . customerFollowUpEscape($connect, $createDate) . "',
                    '" . customerFollowUpEscape($connect, $createTime) . "',
                    'A'
                )";

        if (!mysqli_query($connect, $sql)) {
            return 0;
        }

        return (int) mysqli_insert_id($connect);
    }
}

if (!function_exists('customerFollowUpCreateFollowUpRound')) {
    function customerFollowUpCreateFollowUpRound($connect, $data)
    {
        $followUpId = isset($data['follow_up_id']) ? (int) $data['follow_up_id'] : 0;
        $roundNo = max(1, (int) (isset($data['round_no']) ? $data['round_no'] : 1));
        if ($followUpId <= 0) {
            return 0;
        }

        $actorUserId = trim((string) (isset($data['create_by']) ? $data['create_by'] : (defined('USER_ID') ? USER_ID : '')));
        $createDate = isset($data['create_date']) ? (string) $data['create_date'] : customerFollowUpNowDate();
        $createTime = isset($data['create_time']) ? (string) $data['create_time'] : customerFollowUpNowTime();

        $sql = "INSERT INTO `" . CUSTOMER_FOLLOW_UP_ROUND . "` (
                    `follow_up_id`,
                    `round_no`,
                    `stage_no`,
                    `next_follow_up_date`,
                    `previous_follow_up_date`,
                    `attachment`,
                    `message_shortcut_id`,
                    `message_shortcut_text`,
                    `contact_no`,
                    `approval_status`,
                    `reject_reason`,
                    `postpone_status`,
                    `postpone_reason`,
                    `postpone_reject_reason`,
                    `delay_reason`,
                    `missed_original_date`,
                    `completed_date`,
                    `round_status`,
                    `create_by`,
                    `create_date`,
                    `create_time`,
                    `update_by`,
                    `update_date`,
                    `update_time`,
                    `status`
                ) VALUES (
                    " . $followUpId . ",
                    " . $roundNo . ",
                    " . max(1, (int) (isset($data['stage_no']) ? $data['stage_no'] : $roundNo)) . ",
                    " . (trim((string) (isset($data['next_follow_up_date']) ? $data['next_follow_up_date'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['next_follow_up_date']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['previous_follow_up_date']) ? $data['previous_follow_up_date'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['previous_follow_up_date']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['attachment']) ? $data['attachment'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['attachment']) . "'") : 'NULL') . ",
                    " . ((int) (isset($data['message_shortcut_id']) ? $data['message_shortcut_id'] : 0) > 0 ? (int) $data['message_shortcut_id'] : 'NULL') . ",
                    " . (trim((string) (isset($data['message_shortcut_text']) ? $data['message_shortcut_text'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['message_shortcut_text']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['contact_no']) ? $data['contact_no'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['contact_no']) . "'") : 'NULL') . ",
                    '" . customerFollowUpEscape($connect, isset($data['approval_status']) ? $data['approval_status'] : 'pending') . "',
                    " . (trim((string) (isset($data['reject_reason']) ? $data['reject_reason'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['reject_reason']) . "'") : 'NULL') . ",
                    '" . customerFollowUpEscape($connect, isset($data['postpone_status']) ? $data['postpone_status'] : 'none') . "',
                    " . (trim((string) (isset($data['postpone_reason']) ? $data['postpone_reason'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['postpone_reason']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['postpone_reject_reason']) ? $data['postpone_reject_reason'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['postpone_reject_reason']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['delay_reason']) ? $data['delay_reason'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['delay_reason']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['missed_original_date']) ? $data['missed_original_date'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['missed_original_date']) . "'") : 'NULL') . ",
                    " . (trim((string) (isset($data['completed_date']) ? $data['completed_date'] : '')) !== '' ? ("'" . customerFollowUpEscape($connect, $data['completed_date']) . "'") : 'NULL') . ",
                    '" . customerFollowUpEscape($connect, isset($data['round_status']) ? $data['round_status'] : '') . "',
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    '" . customerFollowUpEscape($connect, $createDate) . "',
                    '" . customerFollowUpEscape($connect, $createTime) . "',
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    '" . customerFollowUpEscape($connect, $createDate) . "',
                    '" . customerFollowUpEscape($connect, $createTime) . "',
                    'A'
                )";

        if (!mysqli_query($connect, $sql)) {
            return 0;
        }

        return (int) mysqli_insert_id($connect);
    }
}

if (!function_exists('customerFollowUpCalculateMaxAllowedNextFollowUpDate')) {
    function customerFollowUpCalculateMaxAllowedNextFollowUpDate($followUpRow, $roundRow = array(), $today = '')
    {
        $today = trim((string) $today) !== '' ? trim((string) $today) : customerFollowUpNowDate();
        $baseDate = $today;
        $roundNo = max(1, (int) (isset($roundRow['round_no']) ? $roundRow['round_no'] : (isset($followUpRow['current_round_no']) ? $followUpRow['current_round_no'] : 1)));
        $customerType = strtolower(trim((string) (isset($followUpRow['customer_type']) ? $followUpRow['customer_type'] : 'new')));

        // The round-based caps below are all measured from an order's received date.
        // A customer-sourced case has no order, so there is nothing to measure from and
        // the date the user picks on the customer page is taken as-is.
        if (customerFollowUpIsOrderlessCase($followUpRow)) {
            return array(
                'success' => true,
                'max_date' => '',
                'rule_label' => 'Customer-sourced follow-up has no next follow-up date limit.',
            );
        }

        if ($customerType === 'return') {
            return array(
                'success' => true,
                'max_date' => date('Y-m-d', strtotime($today . ' +6 months')),
                'rule_label' => 'Return Customer max date = today + 6 months.',
            );
        }

        if ($roundNo === 1) {
            return array(
                'success' => true,
                'max_date' => date('Y-m-d', strtotime($today . ' +7 days')),
                'rule_label' => 'New Customer round 1 max date = today + 7 days.',
            );
        }

        if ($roundNo === 2) {
            return array(
                'success' => true,
                'max_date' => date('Y-m-d', strtotime($today . ' +14 days')),
                'rule_label' => 'New Customer round 2 max date = today + 14 days.',
            );
        }

        if ($roundNo === 3) {
            return array(
                'success' => true,
                'max_date' => date('Y-m-d', strtotime($today . ' +21 days')),
                'rule_label' => 'New Customer round 3 max date = today + 21 days.',
            );
        }

        $previousDate = trim((string) (isset($roundRow['previous_follow_up_date']) ? $roundRow['previous_follow_up_date'] : ''));
        if ($previousDate === '') {
            $previousDate = trim((string) (isset($followUpRow['received_date']) ? $followUpRow['received_date'] : ''));
        }
        if ($previousDate === '') {
            $previousDate = $baseDate;
        }

        return array(
            'success' => true,
            'max_date' => date('Y-m-d', strtotime($previousDate . ' +3 months')),
            'rule_label' => 'New Customer round 4 onwards max date = previous follow-up date + 3 months.',
        );
    }
}

if (!function_exists('customerFollowUpValidateRequiredFields')) {
    function customerFollowUpValidateRequiredFields($data)
    {
        $errors = array();

        if (empty($data['attachment'])) {
            $errors[] = 'Attachment is required.';
        }
        if ((int) (isset($data['message_shortcut_id']) ? $data['message_shortcut_id'] : 0) <= 0) {
            $errors[] = 'Message Shortcut is required.';
        }
        if (trim((string) (isset($data['next_follow_up_date']) ? $data['next_follow_up_date'] : '')) === '') {
            $errors[] = 'Next Follow-Up Date is required.';
        }

        return $errors;
    }
}

if (!function_exists('customerFollowUpValidateNextFollowUpDateLimit')) {
    function customerFollowUpValidateNextFollowUpDateLimit($followUpRow, $roundRow, $nextFollowUpDate)
    {
        $nextFollowUpDate = trim((string) $nextFollowUpDate);
        if ($nextFollowUpDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextFollowUpDate)) {
            return array('success' => false, 'message' => 'Next Follow-Up Date is invalid.');
        }

        $limitInfo = customerFollowUpCalculateMaxAllowedNextFollowUpDate($followUpRow, $roundRow);
        if (empty($limitInfo['success'])) {
            return array('success' => false, 'message' => 'Unable to calculate follow-up date limit.');
        }

        if (trim((string) $limitInfo['max_date']) === '') {
            return array(
                'success' => true,
                'message' => '',
                'max_date' => '',
                'rule_label' => isset($limitInfo['rule_label']) ? $limitInfo['rule_label'] : '',
            );
        }

        if ($nextFollowUpDate > $limitInfo['max_date']) {
            return array(
                'success' => false,
                'message' => 'Next Follow-Up Date cannot be later than ' . $limitInfo['max_date'] . '. ' . $limitInfo['rule_label'],
                'max_date' => $limitInfo['max_date'],
                'rule_label' => $limitInfo['rule_label'],
            );
        }

        return array(
            'success' => true,
            'message' => '',
            'max_date' => $limitInfo['max_date'],
            'rule_label' => $limitInfo['rule_label'],
        );
    }
}

if (!function_exists('customerFollowUpFormatStatusLabel')) {
    function customerFollowUpFormatStatusLabel($status)
    {
        $status = customerFollowUpNormalizeStatus($status);
        $classMap = array(
            'Pending Follow-Up' => 'bg-secondary',
            'Pending Approval' => 'bg-warning text-dark',
            'Approved' => 'bg-primary',
            'Rejected' => 'bg-danger',
            'Missed Follow-Up' => 'bg-dark',
            'Done' => 'bg-success',
            'Postponed' => 'bg-info text-dark',
            'Lost' => 'bg-secondary',
        );
        $badgeClass = isset($classMap[$status]) ? $classMap[$status] : 'bg-secondary';
        $safeStatus = htmlspecialchars($status !== '' ? $status : 'N/A', ENT_QUOTES, 'UTF-8');
        return '<span class="badge ' . $badgeClass . '">' . $safeStatus . '</span>';
    }
}

if (!function_exists('customerFollowUpResolveDisplayStatus')) {
    function customerFollowUpResolveDisplayStatus($followUpRow, $roundRow = array(), $today = '')
    {
        $today = trim((string) $today) !== '' ? trim((string) $today) : customerFollowUpNowDate();
        $postponeStatus = strtolower(trim((string) (isset($roundRow['postpone_status']) ? $roundRow['postpone_status'] : '')));
        if ($postponeStatus === 'pending') {
            return 'Pending Approval';
        }

        $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        $caseStatus = customerFollowUpNormalizeStatus(isset($followUpRow['current_status']) ? $followUpRow['current_status'] : '');

        if ($roundStatus !== '') {
            return $roundStatus;
        }

        if (in_array($caseStatus, array('Pending Approval', 'Approved', 'Rejected', 'Missed Follow-Up', 'Postponed', 'Lost'), true)) {
            return $caseStatus;
        }

        $nextFollowUpDate = trim((string) (isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : ''));
        if ($nextFollowUpDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextFollowUpDate) && $nextFollowUpDate > $today) {
            return 'Approved';
        }

        return 'Pending Follow-Up';
    }
}

if (!function_exists('customerFollowUpResolveRoundDisplayDate')) {
    function customerFollowUpResolveRoundDisplayDate($roundRow = array())
    {
        $nextFollowUpDate = trim((string) (isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : ''));
        if (!customerFollowUpIsEmptyDateValue($nextFollowUpDate)) {
            return $nextFollowUpDate;
        }

        $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if ($roundStatus !== '') {
            return '';
        }

        return trim((string) (isset($roundRow['previous_follow_up_date']) ? $roundRow['previous_follow_up_date'] : ''));
    }
}

if (!function_exists('customerFollowUpBuildEffectiveRoundDateSql')) {
    function customerFollowUpBuildEffectiveRoundDateSql($roundAlias = 'r')
    {
        $roundAlias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $roundAlias);
        if ($roundAlias === '') {
            $roundAlias = 'r';
        }

        return "COALESCE(CASE WHEN " . $roundAlias . ".`next_follow_up_date` IS NOT NULL THEN " . $roundAlias . ".`next_follow_up_date` ELSE NULL END, CASE WHEN IFNULL(TRIM(" . $roundAlias . ".`round_status`), '') = '' THEN " . $roundAlias . ".`previous_follow_up_date` ELSE NULL END)";
    }
}

if (!function_exists('customerFollowUpInsertActionLog')) {
    function customerFollowUpInsertActionLog($connect, $data)
    {
        $followUpId = isset($data['follow_up_id']) ? (int) $data['follow_up_id'] : 0;
        $roundId = isset($data['round_id']) ? (int) $data['round_id'] : 0;
        if ($followUpId <= 0) {
            return 0;
        }

        $oldValue = isset($data['old_value']) ? $data['old_value'] : array();
        $newValue = isset($data['new_value']) ? $data['new_value'] : array();
        $actionType = trim((string) (isset($data['action_type']) ? $data['action_type'] : ''));
        if ($actionType === '') {
            return 0;
        }

        $actionBy = trim((string) (isset($data['action_by']) ? $data['action_by'] : (defined('USER_ID') ? USER_ID : '')));
        $actionDate = isset($data['action_date']) ? (string) $data['action_date'] : customerFollowUpNowDate();
        $actionTime = isset($data['action_time']) ? (string) $data['action_time'] : customerFollowUpNowTime();
        $remark = trim((string) (isset($data['remark']) ? $data['remark'] : ''));

        $sql = "INSERT INTO `" . CUSTOMER_FOLLOW_UP_ACTION_LOG . "` (
                    `follow_up_id`,
                    `round_id`,
                    `action_type`,
                    `action_by`,
                    `action_date`,
                    `action_time`,
                    `old_value`,
                    `new_value`,
                    `remark`,
                    `status`
                ) VALUES (
                    " . $followUpId . ",
                    " . ($roundId > 0 ? $roundId : 'NULL') . ",
                    '" . customerFollowUpEscape($connect, $actionType) . "',
                    '" . customerFollowUpEscape($connect, $actionBy) . "',
                    '" . customerFollowUpEscape($connect, $actionDate) . "',
                    '" . customerFollowUpEscape($connect, $actionTime) . "',
                    " . (trim((string) json_encode($oldValue)) !== '' ? ("'" . customerFollowUpEscape($connect, json_encode($oldValue)) . "'") : 'NULL') . ",
                    " . (trim((string) json_encode($newValue)) !== '' ? ("'" . customerFollowUpEscape($connect, json_encode($newValue)) . "'") : 'NULL') . ",
                    " . ($remark !== '' ? ("'" . customerFollowUpEscape($connect, $remark) . "'") : 'NULL') . ",
                    'A'
                )";

        if (!mysqli_query($connect, $sql)) {
            return 0;
        }

        return (int) mysqli_insert_id($connect);
    }
}

if (!function_exists('customerFollowUpGetPlatformUserRecordLogColumn')) {
    function customerFollowUpGetPlatformUserRecordLogColumn($platform)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $map = array(
            'shopee' => 'shopee_cust_id',
            'lazada' => 'lazada_cust_id',
            'facebook' => 'facebook_cust_id',
            'website' => 'website_cust_id',
            'customer_info' => 'cust_id',
        );

        return isset($map[$platform]) ? $map[$platform] : '';
    }
}

if (!function_exists('customerFollowUpSupportsLogHistory')) {
    function customerFollowUpSupportsLogHistory($connect)
    {
        return function_exists('urlUserRecordLogColumnExists')
            && urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'follow_up_history');
    }
}

if (!function_exists('customerFollowUpBuildLogHistoryEntry')) {
    /**
     * One compact line's worth of "what this action did", kept alongside the entry so
     * every earlier state stays visible once the entry is reused.
     */
    function customerFollowUpBuildLogHistoryEntry($data)
    {
        $actionLabel = trim((string) (isset($data['action_label']) ? $data['action_label'] : ''));
        if ($actionLabel === '') {
            return array();
        }

        $details = array();
        $nextFollowUpDate = trim((string) (isset($data['next_follow_up_date']) ? $data['next_follow_up_date'] : ''));
        if ($nextFollowUpDate !== '') {
            $details[] = 'Follow-Up Date: ' . $nextFollowUpDate;
        }
        $roundNo = (int) (isset($data['round_no']) ? $data['round_no'] : 0);
        if ($roundNo > 0) {
            $details[] = 'Round: ' . $roundNo;
        }
        $historyRemark = trim((string) (isset($data['action_remark']) ? $data['action_remark'] : ''));
        if ($historyRemark !== '') {
            $details[] = $historyRemark;
        }

        return array(
            'time' => trim(customerFollowUpNowDate() . ' ' . customerFollowUpNowTime()),
            'action' => $actionLabel,
            'detail' => implode(' | ', $details),
            'by' => trim((string) (isset($data['actor_display_name']) ? $data['actor_display_name'] : '')),
        );
    }
}

if (!function_exists('customerFollowUpRenderLogHistory')) {
    function customerFollowUpRenderLogHistory($historyEntries)
    {
        if (!is_array($historyEntries) || empty($historyEntries)) {
            return '';
        }

        $lines = array('--- Follow-Up Update History ---');
        foreach ($historyEntries as $historyEntry) {
            if (!is_array($historyEntry)) {
                continue;
            }

            $line = '[' . trim((string) (isset($historyEntry['time']) ? $historyEntry['time'] : '')) . '] '
                . trim((string) (isset($historyEntry['action']) ? $historyEntry['action'] : ''));

            $detail = trim((string) (isset($historyEntry['detail']) ? $historyEntry['detail'] : ''));
            if ($detail !== '') {
                $line .= ' - ' . $detail;
            }

            $by = trim((string) (isset($historyEntry['by']) ? $historyEntry['by'] : ''));
            if ($by !== '') {
                $line .= ' (by ' . $by . ')';
            }

            $lines[] = $line;
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }
}

if (!function_exists('customerFollowUpFindRoundUserRecordLogId')) {
    /**
     * The entry already written for this follow-up round, which the next action on the
     * same round updates instead of inserting beside.
     */
    function customerFollowUpFindRoundUserRecordLogId($connect, $customerColumn, $customerId, $data)
    {
        $followUpId = (int) (isset($data['follow_up_id']) ? $data['follow_up_id'] : 0);
        $roundId = (int) (isset($data['round_id']) ? $data['round_id'] : 0);

        if ($followUpId <= 0 || $roundId <= 0 || $customerId <= 0) {
            return 0;
        }
        if (!function_exists('urlUserRecordLogColumnExists')
            || !urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'follow_up_id')
            || !urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'follow_up_round_id')
        ) {
            return 0;
        }

        $customerColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $customerColumn);
        $sql = "SELECT `id`
                FROM `" . USER_RECORD_LOG . "`
                WHERE `status` = 'A'
                  AND `" . $customerColumn . "` = " . $customerId . "
                  AND `follow_up_id` = " . $followUpId . "
                  AND `follow_up_round_id` = " . $roundId . "
                ORDER BY `id` DESC
                LIMIT 1";

        $result = mysqli_query($connect, $sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            return 0;
        }

        $row = mysqli_fetch_assoc($result);

        return isset($row['id']) ? (int) $row['id'] : 0;
    }
}

if (!function_exists('customerFollowUpUpdateRoundUserRecordLog')) {
    /**
     * Rewrites the round's entry with the latest action and the running history beneath
     * it, so the entry shows where the follow-up stands now and every step that got it
     * there. Returns the entry id.
     */
    function customerFollowUpUpdateRoundUserRecordLog($connect, $logId, $content, $attachment, $actorUserId, $data)
    {
        $logId = (int) $logId;
        if ($logId <= 0) {
            return 0;
        }

        $historyEntries = array();
        if (customerFollowUpSupportsLogHistory($connect)) {
            $historyResult = mysqli_query($connect, "SELECT `follow_up_history` FROM `" . USER_RECORD_LOG . "` WHERE `id` = " . $logId . " LIMIT 1");
            if ($historyResult && mysqli_num_rows($historyResult) > 0) {
                $historyRow = mysqli_fetch_assoc($historyResult);
                $decodedHistory = json_decode((string) (isset($historyRow['follow_up_history']) ? $historyRow['follow_up_history'] : ''), true);
                if (is_array($decodedHistory)) {
                    $historyEntries = $decodedHistory;
                }
            }
        }

        $historyEntry = customerFollowUpBuildLogHistoryEntry($data);
        if (!empty($historyEntry)) {
            $historyEntries[] = $historyEntry;
        }

        $historyText = customerFollowUpRenderLogHistory($historyEntries);
        $combinedContent = $historyText !== '' ? ($content . "\n\n" . $historyText) : $content;

        $updateParts = array(
            "`content` = '" . customerFollowUpEscape($connect, $combinedContent) . "'",
            "`updated_by` = '" . customerFollowUpEscape($connect, $actorUserId) . "'",
            "`updated_at` = NOW()",
        );

        if (trim((string) $attachment) !== '') {
            $updateParts[] = "`attachment` = '" . customerFollowUpEscape($connect, $attachment) . "'";
        }

        if (customerFollowUpSupportsLogHistory($connect)) {
            $updateParts[] = "`follow_up_history` = '" . customerFollowUpEscape($connect, json_encode($historyEntries)) . "'";
        }

        $nextFollowUpDate = trim((string) (isset($data['next_follow_up_date']) ? $data['next_follow_up_date'] : ''));
        if ($nextFollowUpDate !== ''
            && customerFollowUpIsValidDateString($nextFollowUpDate)
            && function_exists('urlUserRecordLogColumnExists')
            && urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'next_follow_up_date')
        ) {
            $updateParts[] = "`next_follow_up_date` = '" . customerFollowUpEscape($connect, $nextFollowUpDate) . "'";
        }

        $sql = "UPDATE `" . USER_RECORD_LOG . "`
                SET " . implode(', ', $updateParts) . "
                WHERE `id` = " . $logId . "
                LIMIT 1";

        return mysqli_query($connect, $sql) ? $logId : 0;
    }
}

if (!function_exists('customerFollowUpInsertReadableUserRecordLog')) {
    function customerFollowUpInsertReadableUserRecordLog($connect, $data)
    {
        $platform = isset($data['platform']) ? $data['platform'] : '';
        $customerColumn = customerFollowUpGetPlatformUserRecordLogColumn($platform);
        if ($customerColumn === '') {
            return 0;
        }

        $columnCheck = mysqli_query($connect, "SHOW COLUMNS FROM `" . USER_RECORD_LOG . "` LIKE '" . customerFollowUpEscape($connect, $customerColumn) . "'");
        if (!$columnCheck || $columnCheck->num_rows === 0) {
            return 0;
        }

        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : 0;
        $content = trim((string) (isset($data['content']) ? $data['content'] : ''));
        if ($content === '') {
            return 0;
        }

        $attachment = trim((string) (isset($data['attachment']) ? $data['attachment'] : ''));
        $actorUserId = trim((string) (isset($data['created_by']) ? $data['created_by'] : (defined('USER_ID') ? USER_ID : '')));

        // Approving, rejecting or rescheduling the same round is the same follow-up moving
        // on, not a new one, so those actions reuse the round's entry and append to its
        // history rather than filling the customer timeline with a row per click. Callers
        // that write a one-off message (tag assignment) leave this off and still insert.
        if (!empty($data['upsert'])) {
            $existingLogId = customerFollowUpFindRoundUserRecordLogId($connect, $customerColumn, $customerId, $data);
            if ($existingLogId > 0) {
                return customerFollowUpUpdateRoundUserRecordLog($connect, $existingLogId, $content, $attachment, $actorUserId, $data);
            }
        }

        $insertColumns = "`" . $customerColumn . "`, `content`, `attachment`, `created_by`, `created_at`, `updated_by`, `updated_at`, `status`";
        $insertValues = ($customerId > 0 ? $customerId : 'NULL') . ",
                    '" . customerFollowUpEscape($connect, $content) . "',
                    '" . customerFollowUpEscape($connect, $attachment) . "',
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    NOW(),
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    NOW(),
                    'A'";

        // The "Summary" box on the customer page always shows the summary column of the
        // most recently created USER_RECORD_LOG row for this customer. Without carrying the
        // existing summary forward here, this system-generated log row becomes the newest
        // row with a blank summary, making an already-saved Summary appear to disappear.
        if (function_exists('urlUserRecordLogColumnExists') && urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'summary')) {
            $existingSummary = function_exists('urlGetLatestUserRecordLogSummary')
                ? urlGetLatestUserRecordLogSummary($connect, USER_RECORD_LOG, array(
                    'customer_id' => $customerId,
                    'customer_column' => $customerColumn,
                    'customer_only' => true,
                ))
                : '';

            $insertColumns .= ", `summary`";
            $insertValues .= ",\n                    " . ($existingSummary !== '' ? ("'" . customerFollowUpEscape($connect, $existingSummary) . "'") : 'NULL');
        }

        // Carry the follow-up case, round and scheduled date onto the log row so the
        // customer page shows the same follow-up the Follow Up List does, and can link
        // back to it, instead of showing an unrelated note with an empty follow-up date.
        $followUpId = isset($data['follow_up_id']) ? (int) $data['follow_up_id'] : 0;
        $roundId = isset($data['round_id']) ? (int) $data['round_id'] : 0;
        $nextFollowUpDate = trim((string) (isset($data['next_follow_up_date']) ? $data['next_follow_up_date'] : ''));

        if ($followUpId > 0 && function_exists('urlUserRecordLogColumnExists')) {
            if (urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'follow_up_id')) {
                $insertColumns .= ", `follow_up_id`";
                $insertValues .= ",\n                    " . $followUpId;
            }
            if ($roundId > 0 && urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'follow_up_round_id')) {
                $insertColumns .= ", `follow_up_round_id`";
                $insertValues .= ",\n                    " . $roundId;
            }
        }

        if ($nextFollowUpDate !== ''
            && customerFollowUpIsValidDateString($nextFollowUpDate)
            && function_exists('urlUserRecordLogColumnExists')
            && urlUserRecordLogColumnExists($connect, USER_RECORD_LOG, 'next_follow_up_date')
        ) {
            $insertColumns .= ", `next_follow_up_date`";
            $insertValues .= ",\n                    '" . customerFollowUpEscape($connect, $nextFollowUpDate) . "'";
        }

        // Seed the history so the first action is listed too, not just the ones that
        // follow it.
        $historyEntry = customerFollowUpBuildLogHistoryEntry($data);
        if (!empty($historyEntry) && customerFollowUpSupportsLogHistory($connect)) {
            $insertColumns .= ", `follow_up_history`";
            $insertValues .= ",\n                    '" . customerFollowUpEscape($connect, json_encode(array($historyEntry))) . "'";
        }

        $sql = "INSERT INTO `" . USER_RECORD_LOG . "` (
                    " . $insertColumns . "
                ) VALUES (
                    " . $insertValues . "
                )";

        if (!mysqli_query($connect, $sql)) {
            return 0;
        }

        return (int) mysqli_insert_id($connect);
    }
}

if (!function_exists('customerFollowUpCreateNotificationRow')) {
    function customerFollowUpCreateNotificationRow($connect, $data)
    {
        $followUpId = isset($data['follow_up_id']) ? (int) $data['follow_up_id'] : 0;
        $notifyUserId = isset($data['notify_user_id']) ? (int) $data['notify_user_id'] : 0;
        if ($followUpId <= 0 || $notifyUserId <= 0) {
            return 0;
        }

        $roundId = isset($data['round_id']) ? (int) $data['round_id'] : 0;
        $notificationType = trim((string) (isset($data['notification_type']) ? $data['notification_type'] : ''));
        $createDate = trim((string) (isset($data['create_date']) ? $data['create_date'] : customerFollowUpNowDate()));
        $createTime = trim((string) (isset($data['create_time']) ? $data['create_time'] : customerFollowUpNowTime()));
        $allowDuplicate = !empty($data['allow_duplicate']);

        if (!$allowDuplicate) {
            $existingSql = "SELECT `id`
                            FROM `" . CUSTOMER_FOLLOW_UP_NOTIFICATION . "`
                            WHERE `follow_up_id` = " . $followUpId . "
                              AND IFNULL(`round_id`, 0) = " . $roundId . "
                              AND `notify_user_id` = " . $notifyUserId . "
                              AND `notification_type` = '" . customerFollowUpEscape($connect, $notificationType) . "'
                              AND IFNULL(`create_date`, '') = '" . customerFollowUpEscape($connect, $createDate) . "'
                              AND `status` = 'A'
                            ORDER BY `id` DESC
                            LIMIT 1";
            $existingResult = mysqli_query($connect, $existingSql);
            if ($existingResult && $existingResult->num_rows > 0) {
                $existingRow = mysqli_fetch_assoc($existingResult);
                $notificationId = isset($existingRow['id']) ? (int) $existingRow['id'] : 0;
                if ($notificationId > 0) {
                    customerFollowUpSyncNotificationToSystemAlert($connect, $notificationId);
                    return $notificationId;
                }
            }
        }

        $sql = "INSERT INTO `" . CUSTOMER_FOLLOW_UP_NOTIFICATION . "` (
                    `follow_up_id`,
                    `round_id`,
                    `notify_user_id`,
                    `notify_role`,
                    `notification_type`,
                    `title`,
                    `message`,
                    `is_read`,
                    `create_date`,
                    `create_time`,
                    `status`
                ) VALUES (
                    " . $followUpId . ",
                    " . ($roundId > 0 ? $roundId : 'NULL') . ",
                    " . $notifyUserId . ",
                    '" . customerFollowUpEscape($connect, isset($data['notify_role']) ? $data['notify_role'] : '') . "',
                    '" . customerFollowUpEscape($connect, $notificationType) . "',
                    '" . customerFollowUpEscape($connect, isset($data['title']) ? $data['title'] : '') . "',
                    '" . customerFollowUpEscape($connect, isset($data['message']) ? $data['message'] : '') . "',
                    '" . customerFollowUpEscape($connect, isset($data['is_read']) ? $data['is_read'] : 'N') . "',
                    '" . customerFollowUpEscape($connect, $createDate) . "',
                    '" . customerFollowUpEscape($connect, $createTime) . "',
                    'A'
                )";

        if (!mysqli_query($connect, $sql)) {
            return 0;
        }

        $notificationId = (int) mysqli_insert_id($connect);
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpSyncNotificationToSystemAlert($connect, $notificationId, array(
            'id' => $notificationId,
            'follow_up_id' => $followUpId,
            'round_id' => $roundId,
            'notify_user_id' => $notifyUserId,
            'notify_role' => isset($data['notify_role']) ? $data['notify_role'] : '',
            'notification_type' => $notificationType,
            'title' => isset($data['title']) ? $data['title'] : '',
            'message' => isset($data['message']) ? $data['message'] : '',
            'is_read' => isset($data['is_read']) ? $data['is_read'] : 'N',
            'create_date' => $createDate,
            'create_time' => $createTime,
            'status' => 'A',
        ), $followUpRow);

        return $notificationId;
    }
}

if (!function_exists('customerFollowUpGetAdminUsers')) {
    function customerFollowUpGetAdminUsers($connect)
    {
        $sql = "SELECT `id`, `name`, `username`, `access_id`
                FROM `" . USR_USER . "`
                WHERE `status` = 'A'
                ORDER BY `name` ASC, `username` ASC";
        $result = mysqli_query($connect, $sql);
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $userId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($userId > 0 && (customerFollowUpUserHasPinAccess($connect, $userId, 11) || customerFollowUpUserHasPinAccess($connect, $userId, 12))) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('customerFollowUpNotificationExists')) {
    function customerFollowUpNotificationExists($connect, $followUpId, $roundId, $notifyUserId, $notificationType, $createDate = '')
    {
        $followUpId = (int) $followUpId;
        $roundId = (int) $roundId;
        $notifyUserId = (int) $notifyUserId;
        $notificationType = trim((string) $notificationType);
        $createDate = trim((string) $createDate) !== '' ? trim((string) $createDate) : customerFollowUpNowDate();
        if (!($connect instanceof mysqli) || $followUpId <= 0 || $notifyUserId <= 0 || $notificationType === '') {
            return false;
        }

        $sql = "SELECT `id`
                FROM `" . CUSTOMER_FOLLOW_UP_NOTIFICATION . "`
                WHERE `follow_up_id` = " . $followUpId . "
                  AND IFNULL(`round_id`, 0) = " . $roundId . "
                  AND `notify_user_id` = " . $notifyUserId . "
                  AND `notification_type` = '" . customerFollowUpEscape($connect, $notificationType) . "'
                  AND IFNULL(`create_date`, '') = '" . customerFollowUpEscape($connect, $createDate) . "'
                  AND `status` = 'A'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('customerFollowUpCanUserManageCase')) {
    function customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect = null)
    {
        $assignedUserId = isset($followUpRow['assigned_user_id']) ? (int) $followUpRow['assigned_user_id'] : 0;
        return $assignedUserId > 0 && $assignedUserId === (int) $actorUserId;
    }
}

if (!function_exists('customerFollowUpUpdateRoundRecord')) {
    function customerFollowUpUpdateRoundRecord($connect, $roundId, $fields)
    {
        $roundId = (int) $roundId;
        if ($roundId <= 0 || !is_array($fields) || empty($fields)) {
            return false;
        }

        $assignments = array();
        foreach ($fields as $field => $value) {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $field);
            if ($field === '') {
                continue;
            }

            if ($value === null) {
                $assignments[] = "`" . $field . "` = NULL";
            } else {
                $assignments[] = "`" . $field . "` = '" . customerFollowUpEscape($connect, $value) . "'";
            }
        }

        if (empty($assignments)) {
            return false;
        }

        $sql = "UPDATE `" . CUSTOMER_FOLLOW_UP_ROUND . "`
                SET " . implode(', ', $assignments) . "
                WHERE `id` = " . $roundId . "
                  AND `status` = 'A'
                LIMIT 1";

        return mysqli_query($connect, $sql) ? true : false;
    }
}

if (!function_exists('customerFollowUpUpdateCaseRecord')) {
    function customerFollowUpUpdateCaseRecord($connect, $followUpId, $fields)
    {
        $followUpId = (int) $followUpId;
        if ($followUpId <= 0 || !is_array($fields) || empty($fields)) {
            return false;
        }

        $assignments = array();
        foreach ($fields as $field => $value) {
            $field = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $field);
            if ($field === '') {
                continue;
            }

            if ($value === null) {
                $assignments[] = "`" . $field . "` = NULL";
            } else {
                $assignments[] = "`" . $field . "` = '" . customerFollowUpEscape($connect, $value) . "'";
            }
        }

        if (empty($assignments)) {
            return false;
        }

        $sql = "UPDATE `" . CUSTOMER_FOLLOW_UP . "`
                SET " . implode(', ', $assignments) . "
                WHERE `id` = " . $followUpId . "
                  AND `status` = 'A'
                LIMIT 1";

        return mysqli_query($connect, $sql) ? true : false;
    }
}

if (!function_exists('customerFollowUpRoundSupportsApprovalComment')) {
    function customerFollowUpRoundSupportsApprovalComment($connect)
    {
        return $connect instanceof mysqli
            && function_exists('shopeeOmsTableHasColumn')
            && defined('dbname')
            && shopeeOmsTableHasColumn($connect, dbname, CUSTOMER_FOLLOW_UP_ROUND, 'approval_comment');
    }
}

if (!function_exists('customerFollowUpSanitizeApprovalComment')) {
    function customerFollowUpSanitizeApprovalComment($comment)
    {
        $comment = str_replace(array("\r\n", "\r"), "\n", (string) $comment);
        $comment = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', '', $comment);
        $comment = trim((string) $comment);

        if ($comment === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($comment, 0, 5000);
        }

        return substr($comment, 0, 5000);
    }
}

if (!function_exists('customerFollowUpBuildApprovalTransitionRemark')) {
    function customerFollowUpBuildApprovalTransitionRemark($roundRow = array(), $approvalComment = '')
    {
        $approvalComment = customerFollowUpSanitizeApprovalComment($approvalComment);

        $roundNo = max(1, (int) (isset($roundRow['round_no']) ? $roundRow['round_no'] : 1));
        $approvedDate = trim((string) (isset($roundRow['approved_date']) ? $roundRow['approved_date'] : ''));
        $approvedTime = trim((string) (isset($roundRow['approved_time']) ? $roundRow['approved_time'] : ''));
        $approvedDateTime = trim($approvedDate . ' ' . $approvedTime);
        if ($approvedDateTime === '') {
            $approvedDateTime = trim((string) customerFollowUpNowDate() . ' ' . (string) customerFollowUpNowTime());
        }

        $remark = 'Follow-up round ' . $roundNo . ' approved on ' . $approvedDateTime . '.';
        $remark .= "\nComment: " . ($approvalComment !== '' ? $approvalComment : '-');

        return $remark;
    }
}

if (!function_exists('customerFollowUpUpdateOrderApprovalTransitionRemark')) {
    function customerFollowUpUpdateOrderApprovalTransitionRemark($connect, $financeConnect, $followUpRow, $roundRow = array(), $approvalComment = '')
    {
        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli) || !defined('ORDER_STATUS_TRANSITION_LOG')) {
            return false;
        }

        $orderId = isset($followUpRow['order_id']) ? (int) $followUpRow['order_id'] : 0;
        $platform = customerFollowUpNormalizePlatform(isset($followUpRow['platform']) ? $followUpRow['platform'] : '');
        if ($orderId <= 0 || $platform === '') {
            return false;
        }

        $whereParts = array(
            "`order_id` = " . $orderId,
            "`status` = 'A'",
            "`to_status` = 'PR'",
            "`source_page` = 'Customer Follow-Up'",
        );

        $platformCondition = function_exists('shopeeOmsBuildLogPlatformCondition')
            ? trim((string) shopeeOmsBuildLogPlatformCondition($financeConnect, $platform, 'l'))
            : '';
        if ($platformCondition !== '') {
            $whereParts[] = $platformCondition;
        }

        $lookupSql = "SELECT `l`.`id`, `l`.`remark`
                FROM `" . ORDER_STATUS_TRANSITION_LOG . "` l
                WHERE " . implode(' AND ', $whereParts) . "
                ORDER BY `l`.`transition_at` DESC, `l`.`id` DESC
                LIMIT 1";
        $result = mysqli_query($financeConnect, $lookupSql);

        if (!$result || mysqli_num_rows($result) === 0) {
            $fallbackWhereParts = array(
                "`l`.`order_id` = " . $orderId,
                "`l`.`status` = 'A'",
            );
            if ($platformCondition !== '') {
                $fallbackWhereParts[] = $platformCondition;
            }

            $fallbackSql = "SELECT `l`.`id`, `l`.`remark`
                    FROM `" . ORDER_STATUS_TRANSITION_LOG . "` l
                    WHERE " . implode(' AND ', $fallbackWhereParts) . "
                    ORDER BY `l`.`transition_at` DESC, `l`.`id` DESC
                    LIMIT 1";
            $result = mysqli_query($financeConnect, $fallbackSql);
        }

        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }

        $historyRow = mysqli_fetch_assoc($result);
        $historyId = isset($historyRow['id']) ? (int) $historyRow['id'] : 0;
        if ($historyId <= 0) {
            return false;
        }

        $approvalRemark = customerFollowUpBuildApprovalTransitionRemark($roundRow, $approvalComment);
        $existingRemark = trim((string) (isset($historyRow['remark']) ? $historyRow['remark'] : ''));
        $updatedRemark = $existingRemark !== '' ? ($existingRemark . "\n" . $approvalRemark) : $approvalRemark;
        $updateSql = "UPDATE `" . ORDER_STATUS_TRANSITION_LOG . "`
                SET `remark` = '" . mysqli_real_escape_string($financeConnect, $updatedRemark) . "'
                WHERE `id` = " . $historyId . "
                LIMIT 1";

        return mysqli_query($financeConnect, $updateSql) ? true : false;
    }
}

if (!function_exists('customerFollowUpBuildReadableLogMessage')) {
    function customerFollowUpBuildReadableLogMessage($connect, $followUpRow, $roundRow, $actionType, $actionLabel, $newValue = array(), $remark = '', $actorUserId = '', $actionDate = '', $actionTime = '')
    {
        $actionType = trim((string) $actionType);
        $actionLabel = trim((string) $actionLabel);
        $remark = trim((string) $remark);
        $actionDate = trim((string) $actionDate);
        $actionTime = trim((string) $actionTime);
        $actorUserId = trim((string) $actorUserId);

        $platformMap = array(
            'shopee' => 'Shopee',
            'lazada' => 'Lazada',
            'facebook' => 'Facebook',
            'website' => 'Website',
            'customer_info' => 'Customer Info',
        );
        $appendLine = function (&$lines, $label, $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        };

        $orderNo = trim((string) (isset($followUpRow['order_no']) ? $followUpRow['order_no'] : ''));
        $customerName = trim((string) (isset($followUpRow['customer_name']) ? $followUpRow['customer_name'] : ''));
        $customerUsername = trim((string) (isset($followUpRow['customer_username']) ? $followUpRow['customer_username'] : ''));
        $customerLabel = $customerUsername;
        if ($customerName !== '' && $customerUsername !== '' && strcasecmp($customerName, $customerUsername) !== 0) {
            $customerLabel = $customerName . ' / ' . $customerUsername;
        } else if ($customerLabel === '') {
            $customerLabel = $customerName;
        }

        $platformKey = customerFollowUpNormalizePlatform(isset($followUpRow['platform']) ? $followUpRow['platform'] : '');
        $platformLabel = isset($platformMap[$platformKey]) ? $platformMap[$platformKey] : trim((string) (isset($followUpRow['platform']) ? $followUpRow['platform'] : ''));
        $roundNo = isset($roundRow['round_no']) ? (int) $roundRow['round_no'] : (isset($followUpRow['current_round_no']) ? (int) $followUpRow['current_round_no'] : 0);
        $contactNo = trim((string) (isset($newValue['contact_no']) && $newValue['contact_no'] !== '' ? $newValue['contact_no'] : (isset($roundRow['contact_no']) && $roundRow['contact_no'] !== '' ? $roundRow['contact_no'] : (isset($followUpRow['contact_no']) ? $followUpRow['contact_no'] : ''))));
        $nextFollowUpDate = trim((string) (isset($newValue['next_follow_up_date']) && $newValue['next_follow_up_date'] !== '' ? $newValue['next_follow_up_date'] : (isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : '')));
        $requestedNextFollowUpDate = trim((string) (isset($newValue['requested_next_follow_up_date']) ? $newValue['requested_next_follow_up_date'] : ''));
        $currentAssignedNextFollowUpDate = trim((string) (isset($newValue['current_next_follow_up_date']) && $newValue['current_next_follow_up_date'] !== '' ? $newValue['current_next_follow_up_date'] : (isset($newValue['old_next_follow_up_date']) ? $newValue['old_next_follow_up_date'] : '')));
        $approvedNextFollowUpDate = trim((string) (isset($newValue['approved_next_follow_up_date']) && $newValue['approved_next_follow_up_date'] !== '' ? $newValue['approved_next_follow_up_date'] : (strtolower($actionType) === 'approve_postponement' ? $nextFollowUpDate : '')));
        if (strtolower($actionType) === 'approve_postponement' && $approvedNextFollowUpDate !== '' && $approvedNextFollowUpDate === $nextFollowUpDate) {
            $nextFollowUpDate = '';
        }

        $actorDisplayName = $actorUserId;
        if ($actorUserId !== '' && ctype_digit($actorUserId)) {
            $actorDisplayName = customerFollowUpGetUserDisplayName($connect, (int) $actorUserId);
        }
        $actionDateTime = trim($actionDate . ' ' . $actionTime);

        $messageShortcutId = isset($roundRow['message_shortcut_id']) ? (int) $roundRow['message_shortcut_id'] : 0;
        if ($messageShortcutId <= 0 && isset($newValue['message_shortcut_id'])) {
            $messageShortcutId = (int) $newValue['message_shortcut_id'];
        }

        $messageShortcutLabel = trim((string) (isset($newValue['message_shortcut_label']) ? $newValue['message_shortcut_label'] : ''));
        $messageShortcutContent = trim((string) (isset($roundRow['message_shortcut_text']) && $roundRow['message_shortcut_text'] !== '' ? $roundRow['message_shortcut_text'] : (isset($newValue['message_shortcut_text']) ? $newValue['message_shortcut_text'] : '')));
        if (($messageShortcutLabel === '' || $messageShortcutContent === '') && $messageShortcutId > 0) {
            $messageShortcutRow = customerFollowUpGetMessageShortcutById($connect, $messageShortcutId);
            if ($messageShortcutLabel === '') {
                $messageShortcutLabel = trim((string) (isset($messageShortcutRow['shortcuts_tag']) ? $messageShortcutRow['shortcuts_tag'] : ''));
            }
            if ($messageShortcutContent === '') {
                $messageShortcutContent = trim((string) (isset($messageShortcutRow['shortcuts_message_text']) ? $messageShortcutRow['shortcuts_message_text'] : ''));
            }
        }

        $rejectReason = trim((string) (isset($newValue['reject_reason']) ? $newValue['reject_reason'] : ''));
        if ($rejectReason === '' && strtolower($actionType) === 'reject_follow_up') {
            $rejectReason = $remark;
        }

        $postponeReason = trim((string) (isset($newValue['postpone_reason']) ? $newValue['postpone_reason'] : ''));
        if ($postponeReason === '' && strtolower($actionType) === 'request_postponement') {
            $postponeReason = $remark;
        }

        $postponeRejectReason = trim((string) (isset($newValue['postpone_reject_reason']) ? $newValue['postpone_reject_reason'] : ''));
        if ($postponeRejectReason === '' && strtolower($actionType) === 'reject_postponement') {
            $postponeRejectReason = $remark;
        }

        $delayReason = trim((string) (isset($newValue['delay_reason']) && $newValue['delay_reason'] !== '' ? $newValue['delay_reason'] : (isset($roundRow['delay_reason']) ? $roundRow['delay_reason'] : '')));
        $missedOriginalDate = trim((string) (isset($newValue['missed_original_date']) && $newValue['missed_original_date'] !== '' ? $newValue['missed_original_date'] : (isset($roundRow['missed_original_date']) ? $roundRow['missed_original_date'] : '')));
        $lostTagName = trim((string) (isset($newValue['lost_tag_name']) ? $newValue['lost_tag_name'] : ''));
        $tagName = trim((string) (isset($newValue['tag_name']) ? $newValue['tag_name'] : ''));
        $tagAction = trim((string) (isset($newValue['tag_action']) ? $newValue['tag_action'] : ''));
        if ($tagAction === '' && $tagName !== '') {
            $tagAction = stripos($actionType, 'remove') !== false ? 'Remove' : 'Add';
        }

        // Every action reports the same shape: the case it happened on, what the follow-up
        // date moved from and to, why, then the message and who did it. Each action used to
        // invent its own field names - seven labels for a follow-up date and five for a
        // reason - which is what made a run of log entries hard to read side by side.
        $normalizedActionType = strtolower($actionType);
        $dateBefore = '';
        $dateAfter = $nextFollowUpDate;
        $reasonLabel = '';
        $reasonText = '';

        switch ($normalizedActionType) {
            case 'reject_follow_up':
                $reasonLabel = 'Reject';
                $reasonText = $rejectReason;
                break;
            case 'approve_follow_up':
                $reasonLabel = 'Comment';
                $reasonText = $remark;
                break;
            case 'request_postponement':
                $dateBefore = $currentAssignedNextFollowUpDate;
                $dateAfter = $requestedNextFollowUpDate;
                $reasonLabel = 'Postpone';
                $reasonText = $postponeReason;
                break;
            case 'reschedule_first_round_date':
                $dateBefore = $currentAssignedNextFollowUpDate;
                break;
            case 'approve_postponement':
                $dateBefore = $currentAssignedNextFollowUpDate;
                $dateAfter = $approvedNextFollowUpDate;
                break;
            case 'reject_postponement':
                $reasonLabel = 'Postpone Rejected';
                $reasonText = $postponeRejectReason;
                break;
            case 'mark_missed_follow_up':
            case 'save_delay_reason':
                $dateBefore = $missedOriginalDate;
                $reasonLabel = 'Delay';
                $reasonText = $delayReason;
                break;
        }

        $lines = array();
        $lines[] = 'Follow-Up Action: ' . ($actionLabel !== '' ? $actionLabel : 'N/A');

        // The case block is always printed in full, blanks included, so entries line up
        // when they are read one after another on the customer timeline.
        $lines[] = '';
        $lines[] = 'Order No: ' . ($orderNo !== '' ? $orderNo : '-');
        $lines[] = 'Customer / Username: ' . ($customerLabel !== '' ? $customerLabel : '-');
        $lines[] = 'Platform: ' . ($platformLabel !== '' ? $platformLabel : '-');
        $lines[] = 'Follow-Up Round: ' . ($roundNo > 0 ? $roundNo : '-');
        $lines[] = 'WhatsApp / Contact No: ' . ($contactNo !== '' ? $contactNo : '-');

        $changeLines = array();
        $appendLine($changeLines, 'Follow-Up Date (Before)', $dateBefore);
        $appendLine($changeLines, 'Follow-Up Date (After)', $dateAfter);
        if ($reasonText !== '') {
            $changeLines[] = 'Reason (' . $reasonLabel . '): ' . $reasonText;
        }
        if ($lostTagName !== '') {
            $changeLines[] = 'Tag (Lost): ' . $lostTagName;
        }
        if ($tagName !== '') {
            $changeLines[] = 'Tag (' . ($tagAction !== '' ? $tagAction : 'Update') . '): ' . $tagName;
        }
        if (!empty($changeLines)) {
            $lines[] = '';
            foreach ($changeLines as $changeLine) {
                $lines[] = $changeLine;
            }
        }

        if ($messageShortcutLabel !== '' || $messageShortcutContent !== '') {
            $lines[] = '';
            $lines[] = 'Message Shortcut: ' . ($messageShortcutLabel !== '' ? $messageShortcutLabel : '-');
            if ($messageShortcutContent !== '') {
                $lines[] = 'Message Shortcut Content:';
                $lines[] = $messageShortcutContent;
            }
        }

        $lines[] = '';
        $lines[] = 'Action By: ' . ($actorDisplayName !== '' ? $actorDisplayName : '-');
        $lines[] = 'Action Time: ' . ($actionDateTime !== '' ? $actionDateTime : '-');

        return implode("\n", $lines);
    }
}

if (!function_exists('customerFollowUpCreateActionArtifacts')) {
    function customerFollowUpCreateActionArtifacts($connect, $followUpRow, $roundRow, $actionType, $actionLabel, $oldValue, $newValue, $remark = '', $attachment = '', $actorUserId = null)
    {
        $actorUserId = trim((string) ($actorUserId !== null ? $actorUserId : (defined('USER_ID') ? USER_ID : 'SYSTEM')));
        $actionDate = customerFollowUpNowDate();
        $actionTime = customerFollowUpNowTime();

        customerFollowUpInsertActionLog($connect, array(
            'follow_up_id' => isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0,
            'round_id' => isset($roundRow['id']) ? (int) $roundRow['id'] : 0,
            'action_type' => $actionType,
            'action_by' => $actorUserId,
            'action_date' => $actionDate,
            'action_time' => $actionTime,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'remark' => $remark,
        ));

        if (function_exists('audit_log')) {
            $followUpId = isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0;
            $actorDisplayName = function_exists('customerFollowUpGetUserDisplayName')
                ? customerFollowUpGetUserDisplayName($connect, $actorUserId)
                : $actorUserId;

            audit_log(array(
                'log_act'     => 'edit',
                'uid'         => $actorUserId,
                'cby'         => $actorUserId,
                'cdate'       => $actionDate,
                'ctime'       => $actionTime,
                'query_rec'   => $followUpId,
                'query_table' => CUSTOMER_FOLLOW_UP,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'oldval'      => is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : (string) $oldValue,
                'changes'     => is_array($newValue) || is_object($newValue) ? json_encode($newValue) : (string) $newValue,
                'act_msg'     => trim($actorDisplayName . ' ' . $actionLabel . ' for follow-up case [<b> ID = ' . $followUpId . '</b> ]' . ($remark !== '' ? (' — ' . $remark) : '') . '.'),
            ));
        }

        customerFollowUpInsertReadableUserRecordLog($connect, array(
            'platform' => isset($followUpRow['platform']) ? $followUpRow['platform'] : '',
            'customer_id' => isset($followUpRow['customer_id']) ? (int) $followUpRow['customer_id'] : 0,
            'content' => customerFollowUpBuildReadableLogMessage($connect, $followUpRow, $roundRow, $actionType, $actionLabel, $newValue, $remark, $actorUserId, $actionDate, $actionTime),
            'attachment' => $attachment,
            'created_by' => $actorUserId,
            'follow_up_id' => $followUpId,
            'round_id' => isset($roundRow['id']) ? (int) $roundRow['id'] : 0,
            'next_follow_up_date' => isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : '',
            // Approve, reject and reschedule all land on the same round, so they update
            // that round's entry and append to its history instead of adding a row each.
            'upsert' => true,
            'action_label' => $actionLabel,
            'action_remark' => $remark,
            'round_no' => isset($roundRow['round_no']) ? (int) $roundRow['round_no'] : 0,
            'actor_display_name' => (function_exists('customerFollowUpGetUserDisplayName') && ctype_digit(trim((string) $actorUserId)))
                ? customerFollowUpGetUserDisplayName($connect, (int) $actorUserId)
                : (string) $actorUserId,
        ));
    }
}

if (!function_exists('customerFollowUpGetSystemActorId')) {
    function customerFollowUpGetSystemActorId($actorUserId = '')
    {
        $actorUserId = trim((string) $actorUserId);
        if ($actorUserId !== '') {
            return $actorUserId;
        }

        return defined('USER_ID') && trim((string) USER_ID) !== '' ? (string) USER_ID : 'SYSTEM';
    }
}

if (!function_exists('customerFollowUpGetExistingCustomerTagName')) {
    function customerFollowUpGetExistingCustomerTagName()
    {
        return 'EXISTING Customer';
    }
}

if (!function_exists('customerFollowUpGetNewCustomerTagName')) {
    function customerFollowUpGetNewCustomerTagName($receivedDate = '')
    {
        $receivedDate = trim((string) $receivedDate);
        $timestamp = $receivedDate !== '' ? strtotime($receivedDate) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }

        return 'NEW Customer (' . date('Y-m', $timestamp) . ')';
    }
}

if (!function_exists('customerFollowUpGetNoRepurchaseRoundThreeTagName')) {
    function customerFollowUpGetNoRepurchaseRoundThreeTagName()
    {
        return '第一次下单 - 还未回购';
    }
}

if (!function_exists('customerFollowUpGetRepurchasedRoundThreeTagName')) {
    function customerFollowUpGetRepurchasedRoundThreeTagName()
    {
        return '第一次下单 - 已第二次回购';
    }
}

if (!function_exists('customerFollowUpGetLostTagName')) {
    function customerFollowUpGetLostTagName($customerType, $lostType, $today = '')
    {
        $customerType = strtolower(trim((string) $customerType));
        $lostType = strtolower(trim((string) $lostType));
        $today = trim((string) $today) !== '' ? trim((string) $today) : customerFollowUpNowDate();
        $yearValue = date('Y', strtotime($today));

        if ($customerType === 'new' && $lostType === 'one_year') {
            return 'Customer Lost (' . $yearValue . ')';
        }
        if ($customerType === 'return') {
            return 'Customer Lost 6 Month NO Purchase (' . $yearValue . ')';
        }

        return 'Customer Lost 3 Month NO Purchase (' . $yearValue . ')';
    }
}

if (!function_exists('customerFollowUpReadTagRow')) {
    function customerFollowUpReadTagRow($connect, $tagId)
    {
        if (function_exists('customerTagGetTagById')) {
            $tagRow = customerTagGetTagById($connect, $tagId);
            return is_array($tagRow) ? $tagRow : array();
        }

        return array();
    }
}

if (!function_exists('customerFollowUpFindOrCreateTagId')) {
    function customerFollowUpFindOrCreateTagId($connect, $tagName, $actorUserId = '')
    {
        $tagName = trim((string) $tagName);
        if (!($connect instanceof mysqli) || $tagName === '') {
            return 0;
        }

        $actorUserId = customerFollowUpGetSystemActorId($actorUserId);
        $tagRow = function_exists('customerTagFindTagByName') ? customerTagFindTagByName($connect, $tagName) : null;
        if ($tagRow && isset($tagRow['id'])) {
            $tagId = (int) $tagRow['id'];
            $tagStatus = isset($tagRow['status']) ? strtoupper(trim((string) $tagRow['status'])) : 'A';
            if ($tagId > 0 && $tagStatus !== 'A') {
                mysqli_query(
                    $connect,
                    "UPDATE `" . TAG . "`
                     SET `status` = 'A',
                         `update_by` = '" . customerFollowUpEscape($connect, $actorUserId) . "',
                         `update_date` = CURDATE(),
                         `update_time` = CURTIME()
                     WHERE `id` = " . $tagId . "
                     LIMIT 1"
                );
            }

            return $tagId;
        }

        $sql = "INSERT INTO `" . TAG . "` (
                    `name`,
                    `remark`,
                    `create_by`,
                    `create_date`,
                    `create_time`,
                    `update_by`,
                    `update_date`,
                    `update_time`,
                    `status`
                ) VALUES (
                    '" . customerFollowUpEscape($connect, $tagName) . "',
                    'Auto created by customer follow-up module.',
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    CURDATE(),
                    CURTIME(),
                    '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    CURDATE(),
                    CURTIME(),
                    'A'
                )";

        if (!mysqli_query($connect, $sql)) {
            return 0;
        }

        $newTagId = (int) mysqli_insert_id($connect);
        if ($newTagId > 0 && function_exists('audit_log')) {
            audit_log(array(
                'log_act'     => 'add',
                'uid'         => $actorUserId,
                'cby'         => $actorUserId,
                'query_rec'   => $newTagId,
                'query_table' => TAG,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'newval'      => "name=$tagName",
                'act_msg'     => "Customer follow-up module auto-created tag [<b> ID = " . $newTagId . "</b> ] <b>" . $tagName . "</b>.",
            ));
        }

        return $newTagId;
    }
}

if (!function_exists('customerFollowUpCustomerHasTagId')) {
    function customerFollowUpCustomerHasTagId($connect, $platform, $customerId, $tagId)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $tagId = (int) $tagId;
        if (!($connect instanceof mysqli) || $platform === '' || $customerId <= 0 || $tagId <= 0 || !defined('CUS_TAG_ASSIGNMENT')) {
            return false;
        }

        $sql = "SELECT `id`
                FROM `" . CUS_TAG_ASSIGNMENT . "`
                WHERE `platform` = '" . customerFollowUpEscape($connect, $platform) . "'
                  AND `customer_id` = " . $customerId . "
                  AND `tag_id` = " . $tagId . "
                  AND `status` = 'A'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('customerFollowUpAssignTagById')) {
    function customerFollowUpAssignTagById($connect, $platform, $customerId, $tagId, $sourceType = '', $sourceId = 0, $actorUserId = '')
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $tagId = (int) $tagId;
        $actorUserId = customerFollowUpGetSystemActorId($actorUserId);
        if (!($connect instanceof mysqli) || $platform === '' || $customerId <= 0 || $tagId <= 0 || !defined('CUS_TAG_ASSIGNMENT')) {
            return array('success' => false, 'already_active' => false, 'changed' => false);
        }

        $checkSql = "SELECT `id`, `status`
                     FROM `" . CUS_TAG_ASSIGNMENT . "`
                     WHERE `platform` = '" . customerFollowUpEscape($connect, $platform) . "'
                       AND `customer_id` = " . $customerId . "
                       AND `tag_id` = " . $tagId . "
                     ORDER BY `id` DESC
                     LIMIT 1";
        $checkResult = mysqli_query($connect, $checkSql);
        if ($checkResult && $checkResult->num_rows > 0) {
            $existingRow = mysqli_fetch_assoc($checkResult);
            $assignmentId = isset($existingRow['id']) ? (int) $existingRow['id'] : 0;
            $statusValue = strtoupper(trim((string) (isset($existingRow['status']) ? $existingRow['status'] : '')));
            if ($assignmentId > 0 && $statusValue === 'A') {
                return array('success' => true, 'already_active' => true, 'changed' => false);
            }

            $updateSql = "UPDATE `" . CUS_TAG_ASSIGNMENT . "`
                          SET `status` = 'A',
                              `update_by` = '" . customerFollowUpEscape($connect, $actorUserId) . "',
                              `update_date` = CURDATE(),
                              `update_time` = CURTIME()
                          WHERE `id` = " . $assignmentId . "
                          LIMIT 1";
            $updateOk = mysqli_query($connect, $updateSql) ? true : false;
            if ($updateOk && function_exists('audit_log')) {
                audit_log(array(
                    'log_act'     => 'edit',
                    'uid'         => $actorUserId,
                    'cby'         => $actorUserId,
                    'query_rec'   => $assignmentId,
                    'query_table' => CUS_TAG_ASSIGNMENT,
                    'page'        => 'Customer Follow-Up',
                    'connect'     => $connect,
                    'changes'     => 'status=A',
                    'act_msg'     => "Customer follow-up module re-assigned tag [<b> ID = " . $tagId . "</b> ] to customer [<b> ID = " . $customerId . "</b> ] on <b>" . $platform . "</b>.",
                ));
            }

            return array(
                'success' => $updateOk,
                'already_active' => false,
                'changed' => true,
            );
        }

        $insertSql = "INSERT INTO `" . CUS_TAG_ASSIGNMENT . "` (
                        `platform`,
                        `customer_id`,
                        `tag_id`,
                        `create_by`,
                        `create_date`,
                        `create_time`,
                        `update_by`,
                        `update_date`,
                        `update_time`,
                        `status`
                    ) VALUES (
                        '" . customerFollowUpEscape($connect, $platform) . "',
                        " . $customerId . ",
                        " . $tagId . ",
                        '" . customerFollowUpEscape($connect, $actorUserId) . "',
                        CURDATE(),
                        CURTIME(),
                        '" . customerFollowUpEscape($connect, $actorUserId) . "',
                        CURDATE(),
                        CURTIME(),
                        'A'
                    )";

        $insertOk = mysqli_query($connect, $insertSql) ? true : false;
        if ($insertOk && function_exists('audit_log')) {
            audit_log(array(
                'log_act'     => 'add',
                'uid'         => $actorUserId,
                'cby'         => $actorUserId,
                'query_rec'   => (int) mysqli_insert_id($connect),
                'query_table' => CUS_TAG_ASSIGNMENT,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'newval'      => "platform=$platform, customer_id=$customerId, tag_id=$tagId",
                'act_msg'     => "Customer follow-up module assigned tag [<b> ID = " . $tagId . "</b> ] to customer [<b> ID = " . $customerId . "</b> ] on <b>" . $platform . "</b>.",
            ));
        }

        return array(
            'success' => $insertOk,
            'already_active' => false,
            'changed' => true,
        );
    }
}

if (!function_exists('customerFollowUpRemoveTagById')) {
    function customerFollowUpRemoveTagById($connect, $platform, $customerId, $tagId, $sourceType = '', $sourceId = 0, $actorUserId = '')
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $tagId = (int) $tagId;
        $actorUserId = customerFollowUpGetSystemActorId($actorUserId);
        if (!($connect instanceof mysqli) || $platform === '' || $customerId <= 0 || $tagId <= 0 || !defined('CUS_TAG_ASSIGNMENT')) {
            return array('success' => false, 'changed' => false);
        }

        $sql = "UPDATE `" . CUS_TAG_ASSIGNMENT . "`
                SET `status` = 'D',
                    `update_by` = '" . customerFollowUpEscape($connect, $actorUserId) . "',
                    `update_date` = CURDATE(),
                    `update_time` = CURTIME()
                WHERE `platform` = '" . customerFollowUpEscape($connect, $platform) . "'
                  AND `customer_id` = " . $customerId . "
                  AND `tag_id` = " . $tagId . "
                  AND `status` = 'A'";

        $result = mysqli_query($connect, $sql);
        $changed = $result ? mysqli_affected_rows($connect) > 0 : false;
        if ($changed && function_exists('audit_log')) {
            audit_log(array(
                'log_act'     => 'delete',
                'uid'         => $actorUserId,
                'cby'         => $actorUserId,
                'query_rec'   => "platform=$platform, customer_id=$customerId, tag_id=$tagId",
                'query_table' => CUS_TAG_ASSIGNMENT,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'act_msg'     => "Customer follow-up module removed tag [<b> ID = " . $tagId . "</b> ] from customer [<b> ID = " . $customerId . "</b> ] on <b>" . $platform . "</b>.",
            ));
        }

        return array(
            'success' => $result ? true : false,
            'changed' => $changed,
        );
    }
}

if (!function_exists('customerFollowUpLogTagChange')) {
    function customerFollowUpLogTagChange($connect, $followUpId, $roundId, $platform, $customerId, $tagId, $actionType, $userId, $remark = '')
    {
        $followUpId = (int) $followUpId;
        $roundId = (int) $roundId;
        $tagId = (int) $tagId;
        $customerId = (int) $customerId;
        if (!($connect instanceof mysqli) || $followUpId <= 0 || $tagId <= 0) {
            return;
        }

        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = $roundId > 0 ? customerFollowUpFetchRoundById($connect, $roundId) : customerFollowUpFetchCurrentRound($connect, $followUpId);
        $tagRow = customerFollowUpReadTagRow($connect, $tagId);
        $tagName = trim((string) (isset($tagRow['name']) ? $tagRow['name'] : ('Tag #' . $tagId)));
        $actionLabel = stripos($actionType, 'remove') !== false ? 'Removed customer tag' : 'Assigned customer tag';

        customerFollowUpCreateActionArtifacts(
            $connect,
            $followUpRow,
            $roundRow,
            $actionType,
            $actionLabel,
            array(),
            array(
                'platform' => $platform,
                'customer_id' => $customerId,
                'tag_id' => $tagId,
                'tag_action' => stripos($actionType, 'remove') !== false ? 'Remove' : 'Add',
                'tag_name' => $tagName,
            ),
            $remark !== '' ? $remark : $tagName,
            '',
            $userId
        );
    }
}

if (!function_exists('customerFollowUpGetActiveCustomerTags')) {
    function customerFollowUpGetActiveCustomerTags($connect, $platform, $customerId)
    {
        if (function_exists('customerTagGetActiveTags')) {
            return (array) customerTagGetActiveTags($connect, $platform, $customerId);
        }

        return array();
    }
}

if (!function_exists('customerFollowUpGetAssignedNewCustomerTagIds')) {
    function customerFollowUpGetAssignedNewCustomerTagIds($connect, $platform, $customerId)
    {
        $tagIds = array();
        foreach (customerFollowUpGetActiveCustomerTags($connect, $platform, $customerId) as $tagRow) {
            $tagName = trim((string) (isset($tagRow['name']) ? $tagRow['name'] : ''));
            $tagId = isset($tagRow['tag_id']) ? (int) $tagRow['tag_id'] : 0;
            if ($tagId > 0 && stripos($tagName, 'NEW Customer (') === 0) {
                $tagIds[] = $tagId;
            }
        }

        return array_values(array_unique($tagIds));
    }
}

if (!function_exists('customerFollowUpApplyCustomerTypeTags')) {
    function customerFollowUpApplyCustomerTypeTags($connect, $followUpRow, $roundRow = array(), $actorUserId = '')
    {
        $platform = customerFollowUpNormalizePlatform(isset($followUpRow['platform']) ? $followUpRow['platform'] : '');
        $customerId = isset($followUpRow['customer_id']) ? (int) $followUpRow['customer_id'] : 0;
        $followUpId = isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0;
        $roundId = isset($roundRow['id']) ? (int) $roundRow['id'] : 0;
        $customerType = strtolower(trim((string) (isset($followUpRow['customer_type']) ? $followUpRow['customer_type'] : 'new')));
        $actorUserId = customerFollowUpGetSystemActorId($actorUserId);
        if (!($connect instanceof mysqli) || $platform === '' || $customerId <= 0 || $followUpId <= 0) {
            return;
        }

        if ($customerType === 'return') {
            foreach (customerFollowUpGetAssignedNewCustomerTagIds($connect, $platform, $customerId) as $newTagId) {
                $removeResult = customerFollowUpRemoveTagById($connect, $platform, $customerId, $newTagId, 'customer_follow_up', $followUpId, $actorUserId);
                if (!empty($removeResult['success']) && !empty($removeResult['changed'])) {
                    customerFollowUpLogTagChange($connect, $followUpId, $roundId, $platform, $customerId, $newTagId, 'remove_tag_existing_customer', $actorUserId, 'Removed previous NEW Customer tag when customer is now returning.');
                }
            }

            $existingTagId = customerFollowUpFindOrCreateTagId($connect, customerFollowUpGetExistingCustomerTagName(), $actorUserId);
            if ($existingTagId > 0 && !customerFollowUpCustomerHasTagId($connect, $platform, $customerId, $existingTagId)) {
                $assignResult = customerFollowUpAssignTagById($connect, $platform, $customerId, $existingTagId, 'customer_follow_up', $followUpId, $actorUserId);
                if (!empty($assignResult['success'])) {
                    customerFollowUpLogTagChange($connect, $followUpId, $roundId, $platform, $customerId, $existingTagId, 'assign_tag_existing_customer', $actorUserId, 'Assigned EXISTING Customer tag.');
                }
            }

            return;
        }

        $newTagId = customerFollowUpFindOrCreateTagId($connect, customerFollowUpGetNewCustomerTagName(isset($followUpRow['received_date']) ? $followUpRow['received_date'] : ''), $actorUserId);
        if ($newTagId > 0 && !customerFollowUpCustomerHasTagId($connect, $platform, $customerId, $newTagId)) {
            $assignResult = customerFollowUpAssignTagById($connect, $platform, $customerId, $newTagId, 'customer_follow_up', $followUpId, $actorUserId);
            if (!empty($assignResult['success'])) {
                customerFollowUpLogTagChange($connect, $followUpId, $roundId, $platform, $customerId, $newTagId, 'assign_tag_new_customer', $actorUserId, 'Assigned NEW Customer follow-up tag.');
            }
        }
    }
}

if (!function_exists('customerFollowUpProcessAppealExtras')) {
    function customerFollowUpProcessAppealExtras($connect, $followUpRow, $roundRow, $formData, $actorUserId, $attachmentPath = '')
    {
        $existingTagIds = array();
        if (isset($formData['appeal_tag_ids']) && is_array($formData['appeal_tag_ids'])) {
            foreach ($formData['appeal_tag_ids'] as $existingTagIdValue) {
                $existingTagIdValue = (int) $existingTagIdValue;
                if ($existingTagIdValue > 0 && !in_array($existingTagIdValue, $existingTagIds, true)) {
                    $existingTagIds[] = $existingTagIdValue;
                }
            }
        } else {
            $legacyExistingTagId = (int) (isset($formData['appeal_tag_id']) ? $formData['appeal_tag_id'] : 0);
            if ($legacyExistingTagId > 0) {
                $existingTagIds[] = $legacyExistingTagId;
            }
        }

        $baseResult = customerFollowUpProcessTagAssignmentExtras(
            $connect,
            $followUpRow,
            $roundRow,
            array(
                'existing_tag_ids' => $existingTagIds,
                'new_tag_name' => isset($formData['appeal_new_tag_name']) ? $formData['appeal_new_tag_name'] : '',
                'user_record_log' => isset($formData['appeal_user_record_log']) ? $formData['appeal_user_record_log'] : '',
            ),
            $actorUserId,
            $attachmentPath,
            array(
                'context_invalid_message' => 'Customer context is invalid for appeal.',
                'selected_tag_unavailable_message' => 'Selected appeal tag is not available.',
                'assign_existing_failed_message' => 'Failed to assign the selected appeal tag.',
                'create_tag_failed_message' => 'Failed to create the new appeal tag.',
                'assign_new_failed_message' => 'Failed to assign the new appeal tag.',
                'save_user_record_log_failed_message' => 'Failed to save the appeal user record log.',
                'assign_action_type' => 'assign_tag_follow_up_appeal',
                'assign_action_remark_prefix' => 'Appeal assigned tag: ',
                'assign_action_empty_remark' => 'Appeal assigned existing tag.',
                'create_action_type' => 'create_tag_follow_up_appeal',
                'create_action_remark_prefix' => 'Appeal created and assigned tag: ',
            )
        );

        if (empty($baseResult['success']) || !isset($baseResult['details']) || !is_array($baseResult['details'])) {
            return $baseResult;
        }

        $baseDetails = $baseResult['details'];
        $baseResult['details'] = array(
            'appeal_existing_tag_id' => isset($baseDetails['existing_tag_id']) ? (int) $baseDetails['existing_tag_id'] : 0,
            'appeal_existing_tag_label' => isset($baseDetails['existing_tag_label']) ? (string) $baseDetails['existing_tag_label'] : '',
            'appeal_existing_tag_ids' => isset($baseDetails['existing_tag_ids']) && is_array($baseDetails['existing_tag_ids']) ? array_values($baseDetails['existing_tag_ids']) : array(),
            'appeal_existing_tag_labels' => isset($baseDetails['existing_tag_labels']) && is_array($baseDetails['existing_tag_labels']) ? array_values($baseDetails['existing_tag_labels']) : array(),
            'appeal_new_tag_name' => isset($baseDetails['new_tag_name']) ? (string) $baseDetails['new_tag_name'] : '',
            'appeal_user_record_log' => isset($baseDetails['user_record_log']) ? (string) $baseDetails['user_record_log'] : '',
        );

        return $baseResult;
    }
}

if (!function_exists('customerFollowUpProcessTagAssignmentExtras')) {
    function customerFollowUpProcessTagAssignmentExtras($connect, $followUpRow, $roundRow, $tagInput, $actorUserId, $attachmentPath = '', $options = array())
    {
        $existingTagIds = array();
        if (isset($tagInput['existing_tag_ids']) && is_array($tagInput['existing_tag_ids'])) {
            foreach ($tagInput['existing_tag_ids'] as $existingTagIdValue) {
                $existingTagIdValue = (int) $existingTagIdValue;
                if ($existingTagIdValue > 0 && !in_array($existingTagIdValue, $existingTagIds, true)) {
                    $existingTagIds[] = $existingTagIdValue;
                }
            }
        }

        $newTagName = trim((string) (isset($tagInput['new_tag_name']) ? $tagInput['new_tag_name'] : ''));
        $manualUserRecordLog = customerFollowUpNormalizeOptionalUserRecordLogContent(
            isset($tagInput['user_record_log']) ? $tagInput['user_record_log'] : ''
        );
        $resultDetails = array(
            'existing_tag_id' => !empty($existingTagIds) ? (int) $existingTagIds[0] : 0,
            'existing_tag_label' => '',
            'existing_tag_ids' => $existingTagIds,
            'existing_tag_labels' => array(),
            'new_tag_name' => $newTagName,
            'user_record_log' => $manualUserRecordLog,
        );

        if (empty($existingTagIds) && $newTagName === '' && $manualUserRecordLog === '') {
            return array('success' => true, 'message' => '', 'details' => $resultDetails);
        }

        $platform = customerFollowUpNormalizePlatform(isset($followUpRow['platform']) ? $followUpRow['platform'] : '');
        $customerId = isset($followUpRow['customer_id']) ? (int) $followUpRow['customer_id'] : 0;
        $followUpId = isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0;
        $roundId = isset($roundRow['id']) ? (int) $roundRow['id'] : 0;
        if (!($connect instanceof mysqli) || $platform === '' || $customerId <= 0 || $followUpId <= 0) {
            return array(
                'success' => false,
                'message' => isset($options['context_invalid_message']) ? (string) $options['context_invalid_message'] : 'Customer context is invalid for tag assignment.',
            );
        }

        $selectedTagUnavailableMessage = isset($options['selected_tag_unavailable_message']) ? (string) $options['selected_tag_unavailable_message'] : 'Selected tag is not available.';
        $assignExistingFailedMessage = isset($options['assign_existing_failed_message']) ? (string) $options['assign_existing_failed_message'] : 'Failed to assign the selected tag.';
        $createTagFailedMessage = isset($options['create_tag_failed_message']) ? (string) $options['create_tag_failed_message'] : 'Failed to create the new tag.';
        $assignNewFailedMessage = isset($options['assign_new_failed_message']) ? (string) $options['assign_new_failed_message'] : 'Failed to assign the new tag.';
        $saveUserRecordLogFailedMessage = isset($options['save_user_record_log_failed_message']) ? (string) $options['save_user_record_log_failed_message'] : 'Failed to save the user record log.';
        $assignActionType = isset($options['assign_action_type']) ? trim((string) $options['assign_action_type']) : 'assign_tag_follow_up_appeal';
        $assignActionRemarkPrefix = isset($options['assign_action_remark_prefix']) ? (string) $options['assign_action_remark_prefix'] : 'Assigned tag: ';
        $assignActionEmptyRemark = isset($options['assign_action_empty_remark']) ? (string) $options['assign_action_empty_remark'] : 'Assigned existing tag.';
        $createActionType = isset($options['create_action_type']) ? trim((string) $options['create_action_type']) : 'create_tag_follow_up_appeal';
        $createActionRemarkPrefix = isset($options['create_action_remark_prefix']) ? (string) $options['create_action_remark_prefix'] : 'Created and assigned tag: ';

        foreach ($existingTagIds as $existingTagId) {
            $existingTagRow = customerFollowUpReadTagRow($connect, $existingTagId);
            if (empty($existingTagRow) || strtoupper(trim((string) (isset($existingTagRow['status']) ? $existingTagRow['status'] : 'A'))) !== 'A') {
                return array('success' => false, 'message' => $selectedTagUnavailableMessage);
            }

            $assignExistingResult = customerFollowUpAssignTagById($connect, $platform, $customerId, $existingTagId, 'customer_follow_up', $followUpId, $actorUserId);
            if (empty($assignExistingResult['success'])) {
                return array('success' => false, 'message' => $assignExistingFailedMessage);
            }

            $existingTagName = trim((string) (isset($existingTagRow['name']) ? $existingTagRow['name'] : ''));
            if (!empty($assignExistingResult['changed'])) {
                customerFollowUpLogTagChange(
                    $connect,
                    $followUpId,
                    $roundId,
                    $platform,
                    $customerId,
                    $existingTagId,
                    $assignActionType,
                    (string) $actorUserId,
                    $existingTagName !== '' ? ($assignActionRemarkPrefix . $existingTagName) : $assignActionEmptyRemark
                );
            }

            if ($resultDetails['existing_tag_label'] === '') {
                $resultDetails['existing_tag_label'] = $existingTagName;
            }
            $resultDetails['existing_tag_labels'][] = $existingTagName;
        }

        if ($newTagName !== '') {
            $existingNamedTagRow = function_exists('customerTagFindTagByName') ? customerTagFindTagByName($connect, $newTagName) : null;
            if ($existingNamedTagRow && isset($existingNamedTagRow['id']) && (int) $existingNamedTagRow['id'] > 0) {
                return array(
                    'success' => false,
                    'message' => '',
                    'field_errors' => array(
                        'appeal_new_tag_name' => 'This tag name already exists. Please select it from Assign Existing Tag.',
                    ),
                );
            }

            $createdTagId = customerFollowUpFindOrCreateTagId($connect, $newTagName, (string) $actorUserId);
            if ($createdTagId <= 0) {
                return array('success' => false, 'message' => $createTagFailedMessage);
            }

            $assignNewResult = customerFollowUpAssignTagById($connect, $platform, $customerId, $createdTagId, 'customer_follow_up', $followUpId, $actorUserId);
            if (empty($assignNewResult['success'])) {
                return array('success' => false, 'message' => $assignNewFailedMessage);
            }

            if (!empty($assignNewResult['changed'])) {
                customerFollowUpLogTagChange(
                    $connect,
                    $followUpId,
                    $roundId,
                    $platform,
                    $customerId,
                    $createdTagId,
                    $createActionType,
                    (string) $actorUserId,
                    $createActionRemarkPrefix . $newTagName
                );
            }
        }

        if ($manualUserRecordLog !== '') {
            $userRecordLogId = customerFollowUpInsertReadableUserRecordLog($connect, array(
                'platform' => $platform,
                'customer_id' => $customerId,
                'content' => $manualUserRecordLog,
                'attachment' => trim((string) $attachmentPath),
                'created_by' => (string) $actorUserId,
                'follow_up_id' => $followUpId,
                'round_id' => $roundId,
            ));

            if ($userRecordLogId <= 0) {
                return array('success' => false, 'message' => $saveUserRecordLogFailedMessage);
            }
        }

        return array('success' => true, 'message' => '', 'details' => $resultDetails);
    }
}

if (!function_exists('customerFollowUpAssignCustomerTags')) {
    function customerFollowUpAssignCustomerTags($connect, $followUpId, $formData, $actorUserId, $actorUserGroupId)
    {
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        if (empty($followUpRow)) {
            return array('success' => false, 'message' => 'Follow-up case not found.');
        }

        if (!customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect)) {
            return array('success' => false, 'message' => 'You do not have permission to assign tags for this follow-up.');
        }

        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        $result = customerFollowUpProcessTagAssignmentExtras(
            $connect,
            $followUpRow,
            $roundRow,
            array(
                'existing_tag_ids' => isset($formData['appeal_tag_ids']) && is_array($formData['appeal_tag_ids']) ? $formData['appeal_tag_ids'] : array(),
                'new_tag_name' => isset($formData['appeal_new_tag_name']) ? $formData['appeal_new_tag_name'] : '',
                'user_record_log' => '',
            ),
            $actorUserId,
            '',
            array(
                'assign_action_type' => 'assign_tag_manual',
                'assign_action_remark_prefix' => 'Manually assigned tag: ',
                'assign_action_empty_remark' => 'Manually assigned existing tag.',
                'create_action_type' => 'create_tag_manual',
                'create_action_remark_prefix' => 'Manually created and assigned tag: ',
            )
        );

        if (empty($result['success']) || !isset($result['details']) || !is_array($result['details'])) {
            return $result;
        }

        $details = $result['details'];
        $hasAssignedExistingTag = !empty($details['existing_tag_ids']);
        $hasCreatedNewTag = trim((string) (isset($details['new_tag_name']) ? $details['new_tag_name'] : '')) !== '';
        if (!$hasAssignedExistingTag && !$hasCreatedNewTag) {
            return array('success' => false, 'message' => 'Please select at least one existing tag or create a new tag.');
        }

        $messageParts = array();
        $existingTagLabels = isset($details['existing_tag_labels']) && is_array($details['existing_tag_labels'])
            ? array_values(array_filter(array_map('trim', $details['existing_tag_labels']), function ($label) {
                return $label !== '';
            }))
            : array();
        if (!empty($existingTagLabels)) {
            $messageParts[] = 'Assigned tag(s) [' . implode(', ', $existingTagLabels) . '].';
        }
        if ($hasCreatedNewTag) {
            $messageParts[] = 'Created and assigned tag [' . trim((string) $details['new_tag_name']) . '].';
        }


        return array(
            'success' => true,
            'message' => !empty($messageParts) ? implode(' ', $messageParts) : 'Customer tag updated successfully.',
        );
    }
}

if (!function_exists('customerFollowUpBuildNotificationActionUrl')) {
    function customerFollowUpBuildNotificationActionUrl($followUpId, $roundId = 0, $notificationType = '')
    {
        if (function_exists('systemAlertBuildFollowUpActionUrl')) {
            return systemAlertBuildFollowUpActionUrl($followUpId, $roundId, $notificationType);
        }

        return '';
    }
}

if (!function_exists('customerFollowUpSyncNotificationToSystemAlert')) {
    function customerFollowUpSyncNotificationToSystemAlert($connect, $notificationId, $notificationData = array(), $followUpRow = array())
    {
        if (!function_exists('systemAlertCreateFromFollowUpNotification')) {
            return 0;
        }

        $notificationId = (int) $notificationId;
        if ($notificationId <= 0) {
            return 0;
        }

        if (empty($notificationData)) {
            $result = getData('*', "id = '" . $notificationId . "' AND status = 'A'", 'LIMIT 1', CUSTOMER_FOLLOW_UP_NOTIFICATION, $connect);
            if ($result && $result->num_rows > 0) {
                $notificationData = $result->fetch_assoc();
            }
        }

        return !empty($notificationData) ? systemAlertCreateFromFollowUpNotification($connect, $notificationData, $followUpRow) : 0;
    }
}

if (!function_exists('customerFollowUpRequiresDelayReasonBeforeMissedAction')) {
    function customerFollowUpRequiresDelayReasonBeforeMissedAction($roundRow)
    {
        $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        $delayReason = trim((string) (isset($roundRow['delay_reason']) ? $roundRow['delay_reason'] : ''));
        return $roundStatus === 'Missed Follow-Up' && $delayReason === '';
    }
}

if (!function_exists('customerFollowUpCanRequestPostponement')) {
    function customerFollowUpCanRequestPostponement($roundRow)
    {
        $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if ($roundStatus === 'Missed Follow-Up') {
            return !customerFollowUpRequiresDelayReasonBeforeMissedAction($roundRow);
        }

        return in_array($roundStatus, array('Approved', 'Postponed'), true);
    }
}

if (!function_exists('customerFollowUpCanRescheduleFirstRoundDirectly')) {
    function customerFollowUpCanRescheduleFirstRoundDirectly($roundRow)
    {
        $roundId = isset($roundRow['id']) ? (int) $roundRow['id'] : 0;
        $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        $postponeStatus = strtolower(trim((string) (isset($roundRow['postpone_status']) ? $roundRow['postpone_status'] : 'none')));

        return $roundId > 0
            && $postponeStatus !== 'pending'
            && !in_array($roundStatus, array('Done', 'Lost'), true);
    }
}

if (!function_exists('customerFollowUpFetchActionLogRows')) {
    function customerFollowUpFetchActionLogRows($connect, $followUpId, $limit = 20)
    {
        $followUpId = (int) $followUpId;
        $limit = max(1, (int) $limit);
        if (!($connect instanceof mysqli) || $followUpId <= 0) {
            return array();
        }

        $sql = "SELECT *
                FROM `" . CUSTOMER_FOLLOW_UP_ACTION_LOG . "`
                WHERE `follow_up_id` = " . $followUpId . "
                  AND `status` = 'A'
                ORDER BY `id` DESC
                LIMIT " . $limit;
        $result = mysqli_query($connect, $sql);
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('customerFollowUpDecodeActionLogPayload')) {
    function customerFollowUpDecodeActionLogPayload($payload)
    {
        if (is_array($payload)) {
            return $payload;
        }

        $payload = trim((string) $payload);
        if ($payload === '') {
            return array();
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : array();
    }
}

if (!function_exists('customerFollowUpFindLatestRoundActionLog')) {
    function customerFollowUpFindLatestRoundActionLog($actionLogRows, $roundId, $actionType = '')
    {
        $roundId = (int) $roundId;
        $actionType = strtolower(trim((string) $actionType));
        if ($roundId <= 0 || !is_array($actionLogRows)) {
            return array();
        }

        foreach ($actionLogRows as $row) {
            $rowRoundId = isset($row['round_id']) ? (int) $row['round_id'] : 0;
            if ($rowRoundId !== $roundId) {
                continue;
            }

            $rowActionType = strtolower(trim((string) (isset($row['action_type']) ? $row['action_type'] : '')));
            if ($actionType !== '' && $rowActionType !== $actionType) {
                continue;
            }

            $row['old_value_decoded'] = customerFollowUpDecodeActionLogPayload(isset($row['old_value']) ? $row['old_value'] : '');
            $row['new_value_decoded'] = customerFollowUpDecodeActionLogPayload(isset($row['new_value']) ? $row['new_value'] : '');
            return $row;
        }

        return array();
    }
}

if (!function_exists('customerFollowUpFetchActiveCasesByCustomer')) {
    function customerFollowUpFetchActiveCasesByCustomer($connect, $platform, $customerId)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        if (!($connect instanceof mysqli) || $platform === '' || $customerId <= 0) {
            return array();
        }

        $sql = "SELECT *
                FROM `" . CUSTOMER_FOLLOW_UP . "`
                WHERE `platform` = '" . customerFollowUpEscape($connect, $platform) . "'
                  AND `customer_id` = " . $customerId . "
                  AND `status` = 'A'
                ORDER BY `id` DESC";
        $result = mysqli_query($connect, $sql);
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('customerFollowUpCreateOrLoadCurrentRound')) {
    function customerFollowUpCreateOrLoadCurrentRound($connect, $followUpRow)
    {
        $roundRow = customerFollowUpFetchCurrentRound($connect, isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0, isset($followUpRow['current_round_no']) ? (int) $followUpRow['current_round_no'] : 1);
        if (!empty($roundRow)) {
            return $roundRow;
        }

        $roundId = customerFollowUpCreateFollowUpRound($connect, array(
            'follow_up_id' => isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0,
            'round_no' => isset($followUpRow['current_round_no']) ? (int) $followUpRow['current_round_no'] : 1,
            'stage_no' => isset($followUpRow['current_round_no']) ? (int) $followUpRow['current_round_no'] : 1,
            'previous_follow_up_date' => isset($followUpRow['received_date']) ? $followUpRow['received_date'] : null,
            'approval_status' => 'pending',
            'postpone_status' => 'none',
            'round_status' => '',
            'create_by' => defined('USER_ID') ? USER_ID : '',
        ));

        return $roundId > 0 ? customerFollowUpFetchRoundById($connect, $roundId) : array();
    }
}

if (!function_exists('customerFollowUpSubmitRound')) {
    function customerFollowUpSubmitRound($connect, $followUpId, $formData, $fileData, $actorUserId, $actorUserGroupId)
    {
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        if (empty($followUpRow)) {
            return array('success' => false, 'message' => 'Follow-up case not found.');
        }

        if (!customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect)) {
            return array('success' => false, 'message' => 'You do not have permission to submit this follow-up.');
        }

        $roundRow = customerFollowUpCreateOrLoadCurrentRound($connect, $followUpRow);
        if (empty($roundRow)) {
            return array('success' => false, 'message' => 'Unable to prepare follow-up round.');
        }

        $currentRoundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if ($currentRoundStatus === 'Pending Approval') {
            return array('success' => false, 'message' => 'This round is already pending admin approval.');
        }
        if (in_array($currentRoundStatus, array('Approved', 'Postponed', 'Done'), true)) {
            return array('success' => false, 'message' => 'This round is already submitted.');
        }

        $isResubmit = strtolower(trim((string) (isset($roundRow['round_status']) ? $roundRow['round_status'] : ''))) === 'rejected';
        $submitMode = strtolower(trim((string) (isset($formData['submit_mode']) ? $formData['submit_mode'] : '')));
        $isAppeal = $isResubmit && $submitMode === 'appeal';
        $existingAttachmentPath = trim((string) (isset($roundRow['attachment']) ? $roundRow['attachment'] : ''));
        $uploadContext = array();
        $financeConnect = isset($GLOBALS['finance_connect']) && $GLOBALS['finance_connect'] instanceof mysqli ? $GLOBALS['finance_connect'] : $connect;
        if (!empty($followUpRow['platform']) && !empty($followUpRow['order_id'])) {
            $uploadContext = customerFollowUpBuildReceivedOrderContext($connect, $financeConnect, (string) $followUpRow['platform'], (int) $followUpRow['order_id']);
        }

        if (is_array($fileData) && isset($fileData['error']) && is_array($fileData['error'])) {
            return array('success' => false, 'message' => 'Only one attachment is allowed.');
        }

        $uploadError = is_array($fileData) && isset($fileData['error']) ? (int) $fileData['error'] : UPLOAD_ERR_NO_FILE;
        if ($uploadError === UPLOAD_ERR_NO_FILE && $isResubmit && $existingAttachmentPath !== '') {
            $uploadResult = array(
                'success' => true,
                'path' => $existingAttachmentPath,
                'message' => '',
                'reused' => true
            );
        } else {
            $uploadResult = customerFollowUpStoreAttachmentUpload($fileData, $connect, array('png', 'jpg', 'jpeg', 'pdf', 'webp'), $uploadContext);
            if (empty($uploadResult['success'])) {
                return array('success' => false, 'message' => isset($uploadResult['message']) ? $uploadResult['message'] : 'Attachment is required.');
            }
        }
        $uploadedAttachmentPath = isset($uploadResult['path']) ? (string) $uploadResult['path'] : '';
        $uploadedNewAttachment = empty($uploadResult['reused']) && $uploadedAttachmentPath !== '';

        $messageShortcutId = (int) (isset($formData['message_shortcut_id']) ? $formData['message_shortcut_id'] : 0);
        $messageShortcutRow = customerFollowUpGetMessageShortcutById($connect, $messageShortcutId);
        if (empty($messageShortcutRow)) {
            if ($uploadedNewAttachment) {
                customerFollowUpDeleteAttachmentFile($uploadedAttachmentPath);
            }
            return array('success' => false, 'message' => 'Message Shortcut is required.');
        }

        $nextFollowUpDate = trim((string) (isset($formData['next_follow_up_date']) ? $formData['next_follow_up_date'] : ''));
        $contactNo = trim((string) (isset($formData['contact_no']) ? $formData['contact_no'] : ''));
        if ($contactNo === '') {
            $contactNo = trim((string) (isset($roundRow['contact_no']) ? $roundRow['contact_no'] : ''));
        }
        if ($contactNo === '') {
            $contactNo = trim((string) (isset($followUpRow['contact_no']) ? $followUpRow['contact_no'] : ''));
        }

        $requiredErrors = customerFollowUpValidateRequiredFields(array(
            'attachment' => isset($uploadResult['path']) ? $uploadResult['path'] : '',
            'message_shortcut_id' => $messageShortcutId,
            'next_follow_up_date' => $nextFollowUpDate,
        ));
        if (!empty($requiredErrors)) {
            if ($uploadedNewAttachment) {
                customerFollowUpDeleteAttachmentFile($uploadedAttachmentPath);
            }
            return array('success' => false, 'message' => implode(' ', $requiredErrors));
        }

        $dateValidation = customerFollowUpValidateNextFollowUpDateLimit($followUpRow, $roundRow, $nextFollowUpDate);
        if (empty($dateValidation['success'])) {
            if ($uploadedNewAttachment) {
                customerFollowUpDeleteAttachmentFile($uploadedAttachmentPath);
            }
            return $dateValidation;
        }

        $customerType = strtolower(trim((string) (isset($followUpRow['customer_type']) ? $followUpRow['customer_type'] : 'new')));
        $approvalStatus = $customerType === 'return' ? 'not_required' : 'pending';
        $roundStatus = $customerType === 'return' ? 'Approved' : 'Pending Approval';
        $followUpStatus = $roundStatus;

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $oldFollowUpState = $followUpRow;

        $roundUpdateFields = array(
            'next_follow_up_date' => $nextFollowUpDate,
            'attachment' => isset($uploadResult['path']) ? $uploadResult['path'] : '',
            'message_shortcut_id' => $messageShortcutId,
            'message_shortcut_text' => isset($messageShortcutRow['shortcuts_message_text']) ? $messageShortcutRow['shortcuts_message_text'] : '',
            'contact_no' => $contactNo !== '' ? $contactNo : null,
            'approval_status' => $approvalStatus,
            'reject_reason' => null,
            'postpone_status' => 'none',
            'postpone_reason' => null,
            'postpone_reject_reason' => null,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
            'round_status' => $roundStatus,
        );

        if (trim((string) (isset($roundRow['create_by']) ? $roundRow['create_by'] : '')) === '') {
            $roundUpdateFields['create_by'] = (string) $actorUserId;
            $roundUpdateFields['create_date'] = customerFollowUpNowDate();
            $roundUpdateFields['create_time'] = customerFollowUpNowTime();
        }

        $caseUpdateFields = array(
            'current_status' => $followUpStatus,
            'contact_no' => $contactNo !== '' ? $contactNo : null,
            'follow_up_started' => 'Y',
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        );

        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, isset($roundRow['id']) ? (int) $roundRow['id'] : 0, $roundUpdateFields);
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, $caseUpdateFields);
        if (!$roundUpdated || !$caseUpdated) {
            mysqli_rollback($connect);
            if ($uploadedNewAttachment) {
                customerFollowUpDeleteAttachmentFile($uploadedAttachmentPath);
            }
            return array('success' => false, 'message' => 'Failed to submit follow-up.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, isset($roundRow['id']) ? (int) $roundRow['id'] : 0);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $appealExtraLogData = array();

        $appealExtraResult = customerFollowUpProcessAppealExtras(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            $formData,
            (string) $actorUserId,
            isset($uploadResult['path']) ? $uploadResult['path'] : ''
        );

        if (empty($appealExtraResult['success'])) {
            mysqli_rollback($connect);
            if ($uploadedNewAttachment) {
                customerFollowUpDeleteAttachmentFile($uploadedAttachmentPath);
            }

            $failureMessage = isset($appealExtraResult['message']) ? trim((string) $appealExtraResult['message']) : '';
            $failureResult = array(
                'success' => false,
                'message' => $failureMessage !== '' ? $failureMessage : 'Failed to save follow-up tag or user record log.',
            );

            if (isset($appealExtraResult['field_errors']) && is_array($appealExtraResult['field_errors'])) {
                $failureResult['field_errors'] = $appealExtraResult['field_errors'];
            }

            return $failureResult;
        }

        $appealExtraLogData = isset($appealExtraResult['details']) && is_array($appealExtraResult['details'])
            ? $appealExtraResult['details']
            : array();

        $actionType = $isResubmit ? 'resubmit_rejected_follow_up' : 'submit_follow_up';
        $actionLabel = $isAppeal ? 'Submitted follow-up appeal' : ($isResubmit ? 'Resubmitted rejected follow-up' : 'Submitted follow-up');
        $previousContactNo = trim((string) (isset($oldRoundState['contact_no']) && $oldRoundState['contact_no'] !== '' ? $oldRoundState['contact_no'] : (isset($oldFollowUpState['contact_no']) ? $oldFollowUpState['contact_no'] : '')));
        $actionNewValue = array(
            'next_follow_up_date' => $nextFollowUpDate,
            'contact_no' => $contactNo,
            'message_shortcut_id' => $messageShortcutId,
            'message_shortcut_label' => isset($messageShortcutRow['shortcuts_tag']) ? $messageShortcutRow['shortcuts_tag'] : '',
            'message_shortcut_text' => isset($messageShortcutRow['shortcuts_message_text']) ? $messageShortcutRow['shortcuts_message_text'] : '',
            'approval_status' => $approvalStatus,
            'round_status' => $roundStatus,
            'reject_reason' => $isAppeal ? trim((string) (isset($oldRoundState['reject_reason']) ? $oldRoundState['reject_reason'] : '')) : '',
            'attachment_path' => isset($uploadResult['path']) ? (string) $uploadResult['path'] : '',
        );
        $hasExtraLogData = (
            !empty($appealExtraLogData['appeal_existing_tag_ids'])
            || trim((string) (isset($appealExtraLogData['appeal_new_tag_name']) ? $appealExtraLogData['appeal_new_tag_name'] : '')) !== ''
            || trim((string) (isset($appealExtraLogData['appeal_user_record_log']) ? $appealExtraLogData['appeal_user_record_log'] : '')) !== ''
        );

        if ($isAppeal || $hasExtraLogData) {
            $actionNewValue['appeal_existing_tag_id'] = isset($appealExtraLogData['appeal_existing_tag_id']) ? (int) $appealExtraLogData['appeal_existing_tag_id'] : 0;
            $actionNewValue['appeal_existing_tag_label'] = isset($appealExtraLogData['appeal_existing_tag_label']) ? (string) $appealExtraLogData['appeal_existing_tag_label'] : '';
            $actionNewValue['appeal_existing_tag_ids'] = isset($appealExtraLogData['appeal_existing_tag_ids']) && is_array($appealExtraLogData['appeal_existing_tag_ids']) ? array_values($appealExtraLogData['appeal_existing_tag_ids']) : array();
            $actionNewValue['appeal_existing_tag_labels'] = isset($appealExtraLogData['appeal_existing_tag_labels']) && is_array($appealExtraLogData['appeal_existing_tag_labels']) ? array_values($appealExtraLogData['appeal_existing_tag_labels']) : array();
            $actionNewValue['appeal_new_tag_name'] = isset($appealExtraLogData['appeal_new_tag_name']) ? (string) $appealExtraLogData['appeal_new_tag_name'] : '';
            $actionNewValue['appeal_user_record_log'] = isset($appealExtraLogData['appeal_user_record_log']) ? (string) $appealExtraLogData['appeal_user_record_log'] : '';
        }
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            $actionType,
            $actionLabel,
            $oldRoundState,
            $actionNewValue,
            '',
            isset($uploadResult['path']) ? $uploadResult['path'] : ''
        );

        if ($contactNo !== '' && $contactNo !== $previousContactNo) {
            customerFollowUpCreateActionArtifacts(
                $connect,
                $updatedFollowUpRow,
                $updatedRoundRow,
                'contact_number_edited',
                'Edited contact number',
                array('contact_no' => $previousContactNo),
                array('contact_no' => $contactNo)
            );
        }

        if ($customerType === 'new') {
            $adminUsers = customerFollowUpGetAdminUsers($connect);
            foreach ($adminUsers as $adminUser) {
                customerFollowUpCreateNotificationRow($connect, array(
                    'follow_up_id' => $followUpId,
                    'round_id' => isset($updatedRoundRow['id']) ? (int) $updatedRoundRow['id'] : 0,
                    'notify_user_id' => isset($adminUser['id']) ? (int) $adminUser['id'] : 0,
                    'notify_role' => 'admin',
                    'notification_type' => $actionType,
                    'allow_duplicate' => $isAppeal,
                    'title' => $isAppeal ? 'Follow-Up Appeal Pending Approval' : 'Follow-Up Pending Approval',
                    'message' => ($isAppeal ? 'Follow-up appeal for round ' : 'Follow-up round ') . (int) $updatedRoundRow['round_no'] . ' for order ' . trim((string) (isset($updatedFollowUpRow['order_no']) ? $updatedFollowUpRow['order_no'] : '')) . ' is pending approval.',
                ));
            }
        }

        if ($existingAttachmentPath !== '' && $existingAttachmentPath !== (isset($uploadResult['path']) ? $uploadResult['path'] : '') && empty($uploadResult['reused'])) {
            customerFollowUpDeleteAttachmentFile($existingAttachmentPath);
        }

        mysqli_commit($connect);
        return array('success' => true, 'message' => $isAppeal ? 'Rejected follow-up appeal submitted successfully.' : ($isResubmit ? 'Rejected follow-up resubmitted successfully.' : 'Follow-up submitted successfully.'));
    }
}

if (!function_exists('customerFollowUpApproveRound')) {
    function customerFollowUpApproveRound($connect, $followUpId, $approvalComment, $actorUserId, $actorUserGroupId, $financeConnect = null)
    {
        if (!customerFollowUpUserHasPinAccess($connect, $actorUserId, 11)) {
            return array('success' => false, 'message' => 'You do not have approval permission for Customer Follow-Up.');
        }

        $approvalComment = customerFollowUpSanitizeApprovalComment($approvalComment);
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (strtolower(trim((string) (isset($followUpRow['customer_type']) ? $followUpRow['customer_type'] : ''))) !== 'new') {
            return array('success' => false, 'message' => 'Only New Customer follow-up requires admin approval.');
        }

        $currentRoundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if ($currentRoundStatus !== 'Pending Approval') {
            return array('success' => false, 'message' => 'Only Pending Approval round can be approved.');
        }

        $transactionConnections = array($connect);
        if ($financeConnect instanceof mysqli) {
            $transactionConnections[] = $financeConnect;
        }

        if (!customerFollowUpBeginTransactions($transactionConnections)) {
            return array('success' => false, 'message' => 'Unable to start approval transaction.');
        }

        $oldRoundState = $roundRow;
        $roundUpdateFields = array(
            'approval_status' => 'approved',
            'round_status' => 'Approved',
            'approved_by' => (string) $actorUserId,
            'approved_date' => customerFollowUpNowDate(),
            'approved_time' => customerFollowUpNowTime(),
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        );
        if (customerFollowUpRoundSupportsApprovalComment($connect)) {
            $roundUpdateFields['approval_comment'] = $approvalComment !== '' ? $approvalComment : null;
        }

        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], $roundUpdateFields);
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'current_status' => 'Approved',
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated || !$caseUpdated) {
            customerFollowUpRollbackTransactions($transactionConnections);
            return array('success' => false, 'message' => 'Failed to approve follow-up.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'approve_follow_up',
            'Approved follow-up',
            $oldRoundState,
            array(
                'approval_status' => 'approved',
                'round_status' => 'Approved',
                'approval_comment' => $approvalComment,
            ),
            $approvalComment
        );

        if ($financeConnect instanceof mysqli && !customerFollowUpUpdateOrderApprovalTransitionRemark($connect, $financeConnect, $updatedFollowUpRow, $updatedRoundRow, $approvalComment)) {
            customerFollowUpRollbackTransactions($transactionConnections);
            return array('success' => false, 'message' => 'Failed to update the order status transition history remark.');
        }

        customerFollowUpCreateNotificationRow($connect, array(
            'follow_up_id' => $followUpId,
            'round_id' => (int) $updatedRoundRow['id'],
            'notify_user_id' => isset($updatedFollowUpRow['assigned_user_id']) ? (int) $updatedFollowUpRow['assigned_user_id'] : 0,
            'notify_role' => 'basic_user',
            'notification_type' => 'approve_follow_up',
            'allow_duplicate' => true,
            'title' => 'Follow-Up Approved',
            'message' => 'Follow-up round ' . (int) $updatedRoundRow['round_no'] . ' for order ' . trim((string) (isset($updatedFollowUpRow['order_no']) ? $updatedFollowUpRow['order_no'] : '')) . ' has been approved.',
        ));

        if (!customerFollowUpCommitTransactions($transactionConnections)) {
            customerFollowUpRollbackTransactions($transactionConnections);
            return array('success' => false, 'message' => 'Failed to finalize follow-up approval.');
        }

        return array('success' => true, 'message' => 'Follow-up approved successfully.');
    }
}

if (!function_exists('customerFollowUpRejectRound')) {
    function customerFollowUpRejectRound($connect, $followUpId, $rejectReason, $actorUserId, $actorUserGroupId)
    {
        if (!customerFollowUpUserHasPinAccess($connect, $actorUserId, 12)) {
            return array('success' => false, 'message' => 'You do not have decline permission for Customer Follow-Up.');
        }

        $rejectReason = trim((string) $rejectReason);
        if ($rejectReason === '') {
            return array('success' => false, 'message' => 'Reject reason is required.');
        }

        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (strtolower(trim((string) (isset($followUpRow['customer_type']) ? $followUpRow['customer_type'] : ''))) !== 'new') {
            return array('success' => false, 'message' => 'Only New Customer follow-up requires admin rejection.');
        }

        $currentRoundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if ($currentRoundStatus !== 'Pending Approval') {
            return array('success' => false, 'message' => 'Only Pending Approval round can be rejected.');
        }

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], array(
            'approval_status' => 'rejected',
            'reject_reason' => $rejectReason,
            'round_status' => 'Rejected',
            'approved_by' => (string) $actorUserId,
            'approved_date' => customerFollowUpNowDate(),
            'approved_time' => customerFollowUpNowTime(),
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'current_status' => 'Rejected',
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated || !$caseUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to reject follow-up.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'reject_follow_up',
            'Rejected follow-up',
            $oldRoundState,
            array(
                'approval_status' => 'rejected',
                'round_status' => 'Rejected',
                'reject_reason' => $rejectReason,
            ),
            $rejectReason
        );

        customerFollowUpCreateNotificationRow($connect, array(
            'follow_up_id' => $followUpId,
            'round_id' => (int) $updatedRoundRow['id'],
            'notify_user_id' => isset($updatedFollowUpRow['assigned_user_id']) ? (int) $updatedFollowUpRow['assigned_user_id'] : 0,
            'notify_role' => 'basic_user',
            'notification_type' => 'reject_follow_up',
            'allow_duplicate' => true,
            'title' => 'Follow-Up Rejected',
            'message' => 'Follow-up round ' . (int) $updatedRoundRow['round_no'] . ' for order ' . trim((string) (isset($updatedFollowUpRow['order_no']) ? $updatedFollowUpRow['order_no'] : '')) . ' was rejected. Reason: ' . $rejectReason,
        ));

        mysqli_commit($connect);
        return array('success' => true, 'message' => 'Follow-up rejected successfully.');
    }
}

if (!function_exists('customerFollowUpCanCompleteRound')) {
    function customerFollowUpCanCompleteRound($roundRow)
    {
        if (strtolower(trim((string) (isset($roundRow['postpone_status']) ? $roundRow['postpone_status'] : ''))) === 'pending') {
            return false;
        }

        $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        $nextFollowUpDate = trim((string) (isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : ''));
        if ($roundStatus === 'Missed Follow-Up' && customerFollowUpRequiresDelayReasonBeforeMissedAction($roundRow)) {
            return false;
        }

        return in_array($roundStatus, array('Approved', 'Postponed', 'Missed Follow-Up'), true)
            && $nextFollowUpDate !== ''
            && $nextFollowUpDate <= customerFollowUpNowDate();
    }
}

if (!function_exists('customerFollowUpCompleteCurrentRound')) {
    function customerFollowUpCompleteCurrentRound($connect, $followUpId, $actorUserId, $actorUserGroupId)
    {
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (!customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect)) {
            return array('success' => false, 'message' => 'You do not have permission to complete this follow-up.');
        }

        if (!customerFollowUpCanCompleteRound($roundRow)) {
            return array('success' => false, 'message' => 'This follow-up round is not due for completion yet.');
        }

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], array(
            'round_status' => 'Done',
            'completed_date' => customerFollowUpNowDate(),
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to complete follow-up.');
        }

        $nextRoundNo = (int) (isset($followUpRow['current_round_no']) ? $followUpRow['current_round_no'] : 1);
        if ($nextRoundNo < customerFollowUpGetMaxRoundNo()) {
            $nextRoundNo++;
            $nextRoundRow = customerFollowUpFetchCurrentRound($connect, $followUpId, $nextRoundNo);
            if (empty($nextRoundRow)) {
                customerFollowUpCreateFollowUpRound($connect, array(
                    'follow_up_id' => $followUpId,
                    'round_no' => $nextRoundNo,
                    'stage_no' => $nextRoundNo,
                    'next_follow_up_date' => isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : null,
                    'previous_follow_up_date' => isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : null,
                    'approval_status' => 'pending',
                    'postpone_status' => 'none',
                    'round_status' => '',
                    'create_by' => (string) $actorUserId,
                ));
            }

            $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
                'current_round_no' => $nextRoundNo,
                'current_status' => '',
                'update_by' => (string) $actorUserId,
                'update_date' => customerFollowUpNowDate(),
                'update_time' => customerFollowUpNowTime(),
            ));
        } else {
            $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
                'current_status' => 'Done',
                'update_by' => (string) $actorUserId,
                'update_date' => customerFollowUpNowDate(),
                'update_time' => customerFollowUpNowTime(),
            ));
        }

        if (!$caseUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to update follow-up case.');
        }

        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'complete_follow_up',
            'Completed follow-up',
            $oldRoundState,
            array('round_status' => 'Done', 'completed_date' => customerFollowUpNowDate())
        );

        customerFollowUpHandleRoundThreeCompletionTags($connect, isset($GLOBALS['finance_connect']) ? $GLOBALS['finance_connect'] : $connect, $updatedFollowUpRow, $updatedRoundRow, (string) $actorUserId);

        mysqli_commit($connect);
        return array('success' => true, 'message' => (int) $followUpRow['current_round_no'] >= customerFollowUpGetMaxRoundNo() ? ('Round ' . customerFollowUpGetMaxRoundNo() . ' completed. Follow-up case marked as Done.') : 'Follow-up completed and next round placeholder is ready.');
    }
}

if (!function_exists('customerFollowUpGetLatestPendingPostponeRequest')) {
    function customerFollowUpGetLatestPendingPostponeRequest($connect, $roundId)
    {
        $roundId = (int) $roundId;
        if ($roundId <= 0) {
            return array();
        }

        $sql = "SELECT `id`, `new_value`, `remark`, `action_date`, `action_time`
                FROM `" . CUSTOMER_FOLLOW_UP_ACTION_LOG . "`
                WHERE `round_id` = " . $roundId . "
                  AND `action_type` = 'request_postponement'
                  AND `status` = 'A'
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if (!$result || $result->num_rows === 0) {
            return array();
        }

        $row = mysqli_fetch_assoc($result);
        $payload = array();
        if (!empty($row['new_value'])) {
            $decoded = json_decode((string) $row['new_value'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $payload['log_id'] = isset($row['id']) ? (int) $row['id'] : 0;
        $payload['remark'] = isset($row['remark']) ? (string) $row['remark'] : '';
        return $payload;
    }
}

if (!function_exists('customerFollowUpSubmitMissingNextFollowUpDate')) {
    function customerFollowUpSubmitMissingNextFollowUpDate($connect, $followUpId, $nextFollowUpDate, $actorUserId, $actorUserGroupId)
    {
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (!customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect)) {
            return array('success' => false, 'message' => 'You do not have permission to submit the next follow-up date.');
        }

        $roundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if (in_array($roundStatus, array('Done', 'Lost'), true)) {
            return array('success' => false, 'message' => 'This follow-up round is already completed.');
        }

        if (!customerFollowUpIsEmptyDateValue(isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : '')) {
            return array('success' => false, 'message' => 'This round already has a next follow-up date.');
        }

        $nextFollowUpDate = trim((string) $nextFollowUpDate);
        if (!customerFollowUpIsValidDateString($nextFollowUpDate)) {
            return array('success' => false, 'message' => 'Next Follow-Up Date is invalid.');
        }

        $dateValidation = customerFollowUpValidateNextFollowUpDateLimit($followUpRow, $roundRow, $nextFollowUpDate);
        if (empty($dateValidation['success'])) {
            return $dateValidation;
        }

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], array(
            'next_follow_up_date' => $nextFollowUpDate,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to save the next follow-up date.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'submit_missing_next_follow_up_date',
            'Submitted missing next follow-up date',
            $oldRoundState,
            array(
                'next_follow_up_date' => $nextFollowUpDate,
            )
        );

        mysqli_commit($connect);
        return array('success' => true, 'message' => 'Next follow-up date submitted successfully.');
    }
}

if (!function_exists('customerFollowUpRequestPostponement')) {
    function customerFollowUpRequestPostponement($connect, $followUpId, $postponeReason, $requestedNextDate, $actorUserId, $actorUserGroupId)
    {
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (!customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect)) {
            return array('success' => false, 'message' => 'You do not have permission to request postponement.');
        }

        $postponeReason = trim((string) $postponeReason);
        if ($postponeReason === '') {
            return array('success' => false, 'message' => 'Postpone reason is required.');
        }

        $dateValidation = customerFollowUpValidateNextFollowUpDateLimit($followUpRow, $roundRow, $requestedNextDate);
        if (empty($dateValidation['success'])) {
            return $dateValidation;
        }

        if (!customerFollowUpCanRequestPostponement($roundRow)) {
            return array('success' => false, 'message' => 'This follow-up round is not available for postponement.');
        }

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], array(
            'postpone_status' => 'pending',
            'postpone_reason' => $postponeReason,
            'postpone_reject_reason' => null,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'current_status' => 'Pending Approval',
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated || !$caseUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to request postponement.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'request_postponement',
            'Requested postponement',
            $oldRoundState,
            array(
                'current_next_follow_up_date' => isset($oldRoundState['next_follow_up_date']) ? $oldRoundState['next_follow_up_date'] : '',
                'requested_next_follow_up_date' => $requestedNextDate,
                'postpone_reason' => $postponeReason,
            ),
            $postponeReason
        );

        $adminUsers = customerFollowUpGetAdminUsers($connect);
        foreach ($adminUsers as $adminUser) {
            customerFollowUpCreateNotificationRow($connect, array(
                'follow_up_id' => $followUpId,
                'round_id' => (int) $updatedRoundRow['id'],
                'notify_user_id' => isset($adminUser['id']) ? (int) $adminUser['id'] : 0,
                'notify_role' => 'admin',
                'notification_type' => 'request_postponement',
                'title' => 'Postponement Request Pending',
                'message' => 'Follow-up round ' . (int) $updatedRoundRow['round_no'] . ' for order ' . trim((string) (isset($updatedFollowUpRow['order_no']) ? $updatedFollowUpRow['order_no'] : '')) . ' requested postponement to ' . $requestedNextDate . '.',
            ));
        }

        mysqli_commit($connect);
        return array('success' => true, 'message' => 'Postponement request submitted successfully.');
    }
}

if (!function_exists('customerFollowUpRescheduleFirstRoundDate')) {
    function customerFollowUpRescheduleFirstRoundDate($connect, $followUpId, $requestedNextDate, $actorUserId, $actorUserGroupId)
    {
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (!customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect)) {
            return array('success' => false, 'message' => 'You do not have permission to reschedule this follow-up date.');
        }

        if (!customerFollowUpCanRescheduleFirstRoundDirectly($roundRow)) {
            return array('success' => false, 'message' => 'This follow-up round is not available for direct reschedule.');
        }

        $requestedNextDate = trim((string) $requestedNextDate);
        if (!customerFollowUpIsValidDateString($requestedNextDate)) {
            return array('success' => false, 'message' => 'New Next Follow-Up Date is invalid.');
        }

        $currentNextDate = trim((string) (isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : ''));
        if ($currentNextDate === $requestedNextDate) {
            return array('success' => false, 'message' => 'New Next Follow-Up Date must be different from the current date.');
        }

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], array(
            'next_follow_up_date' => $requestedNextDate,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated || !$caseUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to reschedule the follow-up date.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'reschedule_follow_up_date',
            'Rescheduled follow-up date',
            $oldRoundState,
            array(
                'current_next_follow_up_date' => $currentNextDate,
                'next_follow_up_date' => $requestedNextDate,
            )
        );

        mysqli_commit($connect);
        return array('success' => true, 'message' => 'Follow-up date rescheduled successfully.');
    }
}

if (!function_exists('customerFollowUpApprovePostponement')) {
    function customerFollowUpApprovePostponement($connect, $followUpId, $actorUserId, $actorUserGroupId)
    {
        if (!customerFollowUpUserHasPinAccess($connect, $actorUserId, 11)) {
            return array('success' => false, 'message' => 'You do not have approval permission for Customer Follow-Up.');
        }

        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (strtolower(trim((string) (isset($roundRow['postpone_status']) ? $roundRow['postpone_status'] : ''))) !== 'pending') {
            return array('success' => false, 'message' => 'No pending postponement request found.');
        }

        $pendingRequest = customerFollowUpGetLatestPendingPostponeRequest($connect, (int) $roundRow['id']);
        $requestedNextDate = trim((string) (isset($pendingRequest['requested_next_follow_up_date']) ? $pendingRequest['requested_next_follow_up_date'] : ''));
        if ($requestedNextDate === '') {
            return array('success' => false, 'message' => 'Requested postponement date is missing.');
        }

        $dateValidation = customerFollowUpValidateNextFollowUpDateLimit($followUpRow, $roundRow, $requestedNextDate);
        if (empty($dateValidation['success'])) {
            return $dateValidation;
        }

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $oldNextDate = isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : null;
        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], array(
            'missed_original_date' => $oldNextDate !== '' ? $oldNextDate : null,
            'next_follow_up_date' => $requestedNextDate,
            'postpone_status' => 'approved',
            'round_status' => 'Postponed',
            'approved_by' => (string) $actorUserId,
            'approved_date' => customerFollowUpNowDate(),
            'approved_time' => customerFollowUpNowTime(),
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'current_status' => 'Postponed',
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated || !$caseUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to approve postponement.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'approve_postponement',
            'Approved postponement',
            $oldRoundState,
            array(
                'next_follow_up_date' => $requestedNextDate,
                'approved_next_follow_up_date' => $requestedNextDate,
                'postpone_status' => 'approved',
                'round_status' => 'Postponed',
            )
        );

        customerFollowUpCreateNotificationRow($connect, array(
            'follow_up_id' => $followUpId,
            'round_id' => (int) $updatedRoundRow['id'],
            'notify_user_id' => isset($updatedFollowUpRow['assigned_user_id']) ? (int) $updatedFollowUpRow['assigned_user_id'] : 0,
            'notify_role' => 'basic_user',
            'notification_type' => 'approve_postponement',
            'title' => 'Postponement Approved',
            'message' => 'Postponement for round ' . (int) $updatedRoundRow['round_no'] . ' was approved. New next follow-up date: ' . $requestedNextDate . '.',
        ));

        mysqli_commit($connect);
        return array('success' => true, 'message' => 'Postponement approved successfully.');
    }
}

if (!function_exists('customerFollowUpRejectPostponement')) {
    function customerFollowUpRejectPostponement($connect, $followUpId, $rejectReason, $actorUserId, $actorUserGroupId)
    {
        if (!customerFollowUpUserHasPinAccess($connect, $actorUserId, 12)) {
            return array('success' => false, 'message' => 'You do not have decline permission for Customer Follow-Up.');
        }

        $rejectReason = trim((string) $rejectReason);
        if ($rejectReason === '') {
            return array('success' => false, 'message' => 'Postponement rejection reason is required.');
        }

        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        mysqli_begin_transaction($connect);

        $oldRoundState = $roundRow;
        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], array(
            'postpone_status' => 'rejected',
            'postpone_reject_reason' => $rejectReason,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        $fallbackStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if ($fallbackStatus === '') {
            $fallbackStatus = 'Approved';
        }
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'current_status' => $fallbackStatus,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));

        if (!$roundUpdated || !$caseUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to reject postponement.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'reject_postponement',
            'Rejected postponement',
            $oldRoundState,
            array(
                'postpone_status' => 'rejected',
                'postpone_reject_reason' => $rejectReason,
            ),
            $rejectReason
        );

        customerFollowUpCreateNotificationRow($connect, array(
            'follow_up_id' => $followUpId,
            'round_id' => (int) $updatedRoundRow['id'],
            'notify_user_id' => isset($updatedFollowUpRow['assigned_user_id']) ? (int) $updatedFollowUpRow['assigned_user_id'] : 0,
            'notify_role' => 'basic_user',
            'notification_type' => 'reject_postponement',
            'title' => 'Postponement Rejected',
            'message' => 'Postponement request for round ' . (int) $updatedRoundRow['round_no'] . ' was rejected. Reason: ' . $rejectReason,
        ));

        mysqli_commit($connect);
        return array('success' => true, 'message' => 'Postponement rejected successfully.');
    }
}

if (!function_exists('customerFollowUpSaveDelayReason')) {
    function customerFollowUpSaveDelayReason($connect, $followUpId, $delayReason, $actorUserId, $actorUserGroupId, $nextFollowUpDate = '')
    {
        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        if (empty($followUpRow) || empty($roundRow)) {
            return array('success' => false, 'message' => 'Follow-up round not found.');
        }

        if (!customerFollowUpCanUserManageCase($followUpRow, $actorUserId, $actorUserGroupId, $connect)) {
            return array('success' => false, 'message' => 'You do not have permission to update delay reason.');
        }

        if (customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '') !== 'Missed Follow-Up') {
            return array('success' => false, 'message' => 'Delay reason is only required for missed follow-up.');
        }

        $delayReason = trim((string) $delayReason);
        if ($delayReason === '') {
            return array('success' => false, 'message' => 'Delay reason is required.');
        }

        $nextFollowUpDate = trim((string) $nextFollowUpDate);

        if (!customerFollowUpIsValidDateString($nextFollowUpDate)) {
            return array('success' => false, 'message' => 'Next Follow-Up Date is invalid.');
        }

        $dateValidation = customerFollowUpValidateNextFollowUpDateLimit($followUpRow, $roundRow, $nextFollowUpDate);
        if (empty($dateValidation['success'])) {
            return $dateValidation;
        }

        mysqli_begin_transaction($connect);
        $oldRoundState = $roundRow;

        $roundUpdateData = array(
            'delay_reason' => $delayReason,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        );

        $roundUpdateData['next_follow_up_date'] = $nextFollowUpDate;

        $newValue = array(
            'delay_reason' => $delayReason,
            'next_follow_up_date' => $nextFollowUpDate,
        );

        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], $roundUpdateData);

        if (!$roundUpdated) {
            mysqli_rollback($connect);
            return array('success' => false, 'message' => 'Failed to save delay reason.');
        }

        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            'save_delay_reason',
            'Saved delay reason and next follow-up date',
            $oldRoundState,
            $newValue,
            $delayReason,
            '',
            $actorUserId
        );

        mysqli_commit($connect);

        return array(
            'success' => true,
            'message' => 'Delay reason and next follow-up date saved successfully.',
        );
    }
}

if (!function_exists('customerFollowUpHasRepurchase')) {
    function customerFollowUpHasRepurchase($connect, $financeConnect, $platform, $customerId, $afterDate, $excludeOrderId = 0)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $excludeOrderId = (int) $excludeOrderId;
        $afterDate = trim((string) $afterDate);
        if ($platform === '' || $customerId <= 0 || $afterDate === '') {
            return false;
        }

        $sourceConfig = function_exists('shopeeOmsGetOrderSourceConfig') ? shopeeOmsGetOrderSourceConfig($platform) : array();
        $platformConfig = function_exists('customerLabelGetPlatformConfig') ? customerLabelGetPlatformConfig($platform) : array();
        $orderConnect = function_exists('shopeeOmsGetOrderSourceDbConnection')
            ? shopeeOmsGetOrderSourceDbConnection($connect, $financeConnect, $sourceConfig)
            : $connect;
        $customerConnect = customerFollowUpResolveDbConnection($connect, $financeConnect, isset($platformConfig['customer_db']) ? $platformConfig['customer_db'] : 'cms');
        if (!($orderConnect instanceof mysqli) || !($customerConnect instanceof mysqli) || empty($sourceConfig['table']) || empty($platformConfig['customer_table'])) {
            return false;
        }

        $customerRows = array();
        $customerResult = getData('*', "status = 'A'", '', $platformConfig['customer_table'], $customerConnect);
        if ($customerResult) {
            while ($customerRow = $customerResult->fetch_assoc()) {
                $customerRows[] = $customerRow;
            }
        }

        $seriesLookup = function_exists('customerLabelGetSeriesLookup') ? customerLabelGetSeriesLookup($connect) : array();
        $customerIndexes = function_exists('customerLabelBuildCustomerIndexes')
            ? customerLabelBuildCustomerIndexes($platform, $customerRows, $seriesLookup)
            : array();
        if (empty($customerIndexes['rows_by_id'])) {
            return false;
        }

        $orderResult = getData('*', "status = 'A'", '', $sourceConfig['table'], $orderConnect);
        if (!$orderResult) {
            return false;
        }

        $afterTimestamp = strtotime($afterDate . ' 23:59:59');
        while ($orderRow = $orderResult->fetch_assoc()) {
            if (function_exists('customerLabelIsExcludedOrder') && customerLabelIsExcludedOrder($orderRow)) {
                continue;
            }

            if ($excludeOrderId > 0 && (int) (isset($orderRow['id']) ? $orderRow['id'] : 0) === $excludeOrderId) {
                continue;
            }

            $resolvedCustomerId = function_exists('customerLabelResolveOrderCustomerId')
                ? customerLabelResolveOrderCustomerId($platform, $orderRow, $customerIndexes)
                : 0;
            if ($resolvedCustomerId !== $customerId) {
                continue;
            }

            $candidateTimestamp = customerFollowUpResolveComparableOrderTimestamp($orderRow, $sourceConfig);
            if ($candidateTimestamp > 0 && $afterTimestamp !== false && $candidateTimestamp > $afterTimestamp) {
                return true;
            }

            $candidateDate = trim((string) (isset($orderRow[isset($sourceConfig['date_field']) ? $sourceConfig['date_field'] : 'create_date']) ? $orderRow[isset($sourceConfig['date_field']) ? $sourceConfig['date_field'] : 'create_date'] : ''));
            if ($candidateDate !== '' && $candidateDate > $afterDate) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('customerFollowUpHandleRoundThreeCompletionTags')) {
    function customerFollowUpHandleRoundThreeCompletionTags($connect, $financeConnect, $followUpRow, $roundRow, $actorUserId = '')
    {
        $customerType = strtolower(trim((string) (isset($followUpRow['customer_type']) ? $followUpRow['customer_type'] : 'new')));
        $roundNo = isset($roundRow['round_no']) ? (int) $roundRow['round_no'] : 0;
        if ($customerType !== 'new' || $roundNo !== 3) {
            return;
        }

        $platform = customerFollowUpNormalizePlatform(isset($followUpRow['platform']) ? $followUpRow['platform'] : '');
        $customerId = isset($followUpRow['customer_id']) ? (int) $followUpRow['customer_id'] : 0;
        $followUpId = isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0;
        $receivedDate = trim((string) (isset($followUpRow['received_date']) ? $followUpRow['received_date'] : ''));
        if ($platform === '' || $customerId <= 0 || $followUpId <= 0 || $receivedDate === '') {
            return;
        }

        $actorUserId = customerFollowUpGetSystemActorId($actorUserId);
        $hasRepurchase = customerFollowUpHasRepurchase(
            $connect,
            $financeConnect,
            $platform,
            $customerId,
            $receivedDate,
            isset($followUpRow['order_id']) ? (int) $followUpRow['order_id'] : 0
        );
        $tagName = $hasRepurchase ? customerFollowUpGetRepurchasedRoundThreeTagName() : customerFollowUpGetNoRepurchaseRoundThreeTagName();
        $tagId = customerFollowUpFindOrCreateTagId($connect, $tagName, $actorUserId);
        if ($tagId <= 0 || customerFollowUpCustomerHasTagId($connect, $platform, $customerId, $tagId)) {
            return;
        }

        $assignResult = customerFollowUpAssignTagById($connect, $platform, $customerId, $tagId, 'customer_follow_up', $followUpId, $actorUserId);
        if (!empty($assignResult['success'])) {
            customerFollowUpLogTagChange(
                $connect,
                $followUpId,
                isset($roundRow['id']) ? (int) $roundRow['id'] : 0,
                $platform,
                $customerId,
                $tagId,
                $hasRepurchase ? 'assign_tag_round3_repurchase' : 'assign_tag_round3_no_repurchase',
                $actorUserId,
                $hasRepurchase ? 'Assigned repurchase tag after round 3 completion.' : 'Assigned no repurchase tag after round 3 completion.'
            );
        }
    }
}

if (!function_exists('customerFollowUpHandleRepurchaseOnNewOrder')) {
    function customerFollowUpHandleRepurchaseOnNewOrder($connect, $financeConnect, $platform, $orderId, $customerId, $orderDate, $actorUserId = '')
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderId = (int) $orderId;
        $customerId = (int) $customerId;
        $orderDate = trim((string) $orderDate);
        $actorUserId = customerFollowUpGetSystemActorId($actorUserId);
        if ($platform === '' || $orderId <= 0 || $customerId <= 0 || $orderDate === '') {
            return 0;
        }

        $loggedCount = 0;
        foreach (customerFollowUpFetchActiveCasesByCustomer($connect, $platform, $customerId) as $followUpRow) {
            $followUpId = isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0;
            if ($followUpId <= 0) {
                continue;
            }

            $existingLogSql = "SELECT `id`
                               FROM `" . CUSTOMER_FOLLOW_UP_ACTION_LOG . "`
                               WHERE `follow_up_id` = " . $followUpId . "
                                 AND `action_type` = 'repurchase_detected'
                                 AND `status` = 'A'
                                 AND `new_value` LIKE '%\"new_order_id\":" . $orderId . "%'
                               LIMIT 1";
            $existingLogResult = mysqli_query($connect, $existingLogSql);
            if ($existingLogResult && $existingLogResult->num_rows > 0) {
                continue;
            }

            $roundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
            customerFollowUpCreateActionArtifacts(
                $connect,
                $followUpRow,
                $roundRow,
                'repurchase_detected',
                'Repurchase detected',
                array(),
                array(
                    'new_order_id' => $orderId,
                    'repurchase_date' => $orderDate,
                ),
                'TODO Phase 2 integration point: call this helper from new valid order creation flow.',
                '',
                $actorUserId
            );
            $loggedCount++;
        }

        return $loggedCount;
    }
}

if (!function_exists('customerFollowUpProcessDueNotifications')) {
    function customerFollowUpProcessDueNotifications($connect, $financeConnect, $targetDate = '')
    {
        $targetDate = trim((string) $targetDate) !== '' ? trim((string) $targetDate) : customerFollowUpNowDate();
        $effectiveDateSql = customerFollowUpBuildEffectiveRoundDateSql('r');
        $summary = array(
            'due_count' => 0,
            'notification_count' => 0,
            'skipped_duplicate_count' => 0,
        );

        $sql = "SELECT
                    f.*,
                    r.`id` AS `round_id`,
                    r.`round_no`,
                    r.`next_follow_up_date`,
                    r.`previous_follow_up_date`,
                    " . $effectiveDateSql . " AS `effective_next_follow_up_date`,
                    r.`round_status`
                FROM `" . CUSTOMER_FOLLOW_UP . "` f
                INNER JOIN `" . CUSTOMER_FOLLOW_UP_ROUND . "` r
                    ON r.`follow_up_id` = f.`id`
                   AND r.`round_no` = f.`current_round_no`
                   AND r.`status` = 'A'
                   " . customerFollowUpBuildCurrentRoundCondition($connect, 'r') . "
                WHERE f.`status` = 'A'
                  AND " . $effectiveDateSql . " = '" . customerFollowUpEscape($connect, $targetDate) . "'
                  AND (
                        LOWER(r.`round_status`) IN ('approved', 'postponed')
                        OR IFNULL(TRIM(r.`round_status`), '') = ''
                  )
                  AND LOWER(IFNULL(r.`postpone_status`, 'none')) <> 'pending'
                  AND f.`assigned_user_id` IS NOT NULL
                  AND f.`assigned_user_id` > 0";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $summary;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $summary['due_count']++;
            $followUpId = isset($row['id']) ? (int) $row['id'] : 0;
            $roundId = isset($row['round_id']) ? (int) $row['round_id'] : 0;
            $notifyUserId = isset($row['assigned_user_id']) ? (int) $row['assigned_user_id'] : 0;
            if (customerFollowUpNotificationExists($connect, $followUpId, $roundId, $notifyUserId, 'due_follow_up', $targetDate)) {
                $summary['skipped_duplicate_count']++;
                continue;
            }

            customerFollowUpCreateNotificationRow($connect, array(
                'follow_up_id' => $followUpId,
                'round_id' => $roundId,
                'notify_user_id' => $notifyUserId,
                'notify_role' => 'basic_user',
                'notification_type' => 'due_follow_up',
                'title' => 'Follow-Up Due Today',
                'message' => 'Follow-up round ' . (int) (isset($row['round_no']) ? $row['round_no'] : 0) . ' for order ' . trim((string) (isset($row['order_no']) ? $row['order_no'] : '')) . ' is due today.',
                'create_date' => $targetDate,
            ));

            customerFollowUpCreateActionArtifacts(
                $connect,
                $row,
                array(
                    'id' => $roundId,
                    'round_no' => isset($row['round_no']) ? (int) $row['round_no'] : 0,
                    'next_follow_up_date' => isset($row['effective_next_follow_up_date']) ? $row['effective_next_follow_up_date'] : '',
                ),
                'due_follow_up_notice',
                'Created due follow-up notice',
                array(),
                array('next_follow_up_date' => $targetDate),
                'System created due follow-up notification.',
                '',
                'SYSTEM'
            );

            $summary['notification_count']++;
        }

        return $summary;
    }
}

if (!function_exists('customerFollowUpProcessMissedRounds')) {
    function customerFollowUpProcessMissedRounds($connect, $financeConnect, $today = '')
    {
        $today = trim((string) $today) !== '' ? trim((string) $today) : customerFollowUpNowDate();
        $effectiveDateSql = customerFollowUpBuildEffectiveRoundDateSql('r');
        $summary = array(
            'processed_count' => 0,
            'skipped_count' => 0,
            'notification_count' => 0,
        );

        $sql = "SELECT
                    f.*,
                    r.`id` AS `round_id`,
                    r.`round_no`,
                    r.`next_follow_up_date`,
                    r.`previous_follow_up_date`,
                    " . $effectiveDateSql . " AS `effective_next_follow_up_date`,
                    r.`missed_original_date`,
                    r.`delay_reason`,
                    r.`round_status`
                FROM `" . CUSTOMER_FOLLOW_UP . "` f
                INNER JOIN `" . CUSTOMER_FOLLOW_UP_ROUND . "` r
                    ON r.`follow_up_id` = f.`id`
                   AND r.`round_no` = f.`current_round_no`
                   AND r.`status` = 'A'
                   " . customerFollowUpBuildCurrentRoundCondition($connect, 'r') . "
                WHERE f.`status` = 'A'
                  AND " . $effectiveDateSql . " IS NOT NULL
                  AND " . $effectiveDateSql . " < '" . customerFollowUpEscape($connect, $today) . "'
                  AND (
                        LOWER(r.`round_status`) IN ('approved', 'postponed')
                        OR IFNULL(TRIM(r.`round_status`), '') = ''
                  )
                  AND LOWER(IFNULL(r.`postpone_status`, 'none')) <> 'pending'";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $summary;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $followUpId = isset($row['id']) ? (int) $row['id'] : 0;
            $roundId = isset($row['round_id']) ? (int) $row['round_id'] : 0;
            if ($followUpId <= 0 || $roundId <= 0) {
                $summary['skipped_count']++;
                continue;
            }

            mysqli_begin_transaction($connect);
            $roundUpdated = customerFollowUpUpdateRoundRecord($connect, $roundId, array(
                'round_status' => 'Missed Follow-Up',
                'missed_original_date' => trim((string) (isset($row['missed_original_date']) ? $row['missed_original_date'] : '')) !== ''
                    ? $row['missed_original_date']
                    : (isset($row['effective_next_follow_up_date']) ? $row['effective_next_follow_up_date'] : null),
                'update_by' => 'SYSTEM',
                'update_date' => $today,
                'update_time' => customerFollowUpNowTime(),
            ));
            $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
                'current_status' => 'Missed Follow-Up',
                'update_by' => 'SYSTEM',
                'update_date' => $today,
                'update_time' => customerFollowUpNowTime(),
            ));

            if (!$roundUpdated || !$caseUpdated) {
                mysqli_rollback($connect);
                $summary['skipped_count']++;
                continue;
            }

            $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
            $updatedRoundRow = customerFollowUpFetchRoundById($connect, $roundId);
            customerFollowUpCreateActionArtifacts(
                $connect,
                $updatedFollowUpRow,
                $updatedRoundRow,
                'mark_missed_follow_up',
                'Marked missed follow-up',
                $row,
                array(
                    'round_status' => 'Missed Follow-Up',
                    'missed_original_date' => isset($updatedRoundRow['missed_original_date']) ? $updatedRoundRow['missed_original_date'] : '',
                ),
                'System marked the round as missed follow-up.',
                '',
                'SYSTEM'
            );

            $adminUsers = customerFollowUpGetAdminUsers($connect);
            foreach ($adminUsers as $adminUser) {
                $notifyUserId = isset($adminUser['id']) ? (int) $adminUser['id'] : 0;
                if ($notifyUserId <= 0) {
                    continue;
                }

                if (!customerFollowUpNotificationExists($connect, $followUpId, $roundId, $notifyUserId, 'missed_follow_up', $today)) {
                    customerFollowUpCreateNotificationRow($connect, array(
                        'follow_up_id' => $followUpId,
                        'round_id' => $roundId,
                        'notify_user_id' => $notifyUserId,
                        'notify_role' => 'admin',
                        'notification_type' => 'missed_follow_up',
                        'title' => 'Missed Follow-Up',
                        'message' => 'Follow-up round ' . (int) (isset($updatedRoundRow['round_no']) ? $updatedRoundRow['round_no'] : 0) . ' for order ' . trim((string) (isset($updatedFollowUpRow['order_no']) ? $updatedFollowUpRow['order_no'] : '')) . ' is missed by the assigned user.',
                        'create_date' => $today,
                    ));
                    $summary['notification_count']++;
                }
            }

            mysqli_commit($connect);
            $summary['processed_count']++;
        }

        return $summary;
    }
}

if (!function_exists('customerFollowUpProcessLostCases')) {
    function customerFollowUpProcessLostCases($connect, $financeConnect, $today = '')
    {
        $today = trim((string) $today) !== '' ? trim((string) $today) : customerFollowUpNowDate();
        $summary = array(
            'checked_count' => 0,
            'lost_tagged_count' => 0,
            'skipped_repurchased_count' => 0,
            'skipped_duplicate_count' => 0,
        );

        $sql = "SELECT
                    f.*,
                    r.`id` AS `round_id`,
                    r.`completed_date`
                FROM `" . CUSTOMER_FOLLOW_UP . "` f
                INNER JOIN `" . CUSTOMER_FOLLOW_UP_ROUND . "` r
                    ON r.`follow_up_id` = f.`id`
                   AND r.`round_no` = " . customerFollowUpGetMaxRoundNo() . "
                   AND r.`status` = 'A'
                   AND LOWER(r.`round_status`) = 'done'
                   " . customerFollowUpBuildCurrentRoundCondition($connect, 'r') . "
                WHERE f.`status` = 'A'
                  AND LOWER(IFNULL(f.`current_status`, '')) <> 'lost'
                  AND IFNULL(f.`lost_tag_added`, 'N') <> 'Y'";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $summary;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $summary['checked_count']++;
            $customerType = strtolower(trim((string) (isset($row['customer_type']) ? $row['customer_type'] : 'new')));
            $referenceDate = trim((string) (isset($row['completed_date']) && $row['completed_date'] !== '' ? $row['completed_date'] : (isset($row['received_date']) ? $row['received_date'] : '')));
            $receivedDate = trim((string) (isset($row['received_date']) ? $row['received_date'] : ''));
            $lostType = '';

            if ($customerType === 'new' && $receivedDate !== '' && strtotime($receivedDate . ' +1 year') <= strtotime($today)) {
                $lostType = 'one_year';
            } else if ($customerType === 'new' && $referenceDate !== '' && strtotime($referenceDate . ' +3 months') <= strtotime($today)) {
                $lostType = 'three_month';
            } else if ($customerType === 'return' && $referenceDate !== '' && strtotime($referenceDate . ' +6 months') <= strtotime($today)) {
                $lostType = 'six_month';
            } else {
                continue;
            }

            if (customerFollowUpHasRepurchase(
                $connect,
                $financeConnect,
                isset($row['platform']) ? $row['platform'] : '',
                isset($row['customer_id']) ? (int) $row['customer_id'] : 0,
                $receivedDate !== '' ? $receivedDate : $referenceDate,
                isset($row['order_id']) ? (int) $row['order_id'] : 0
            )) {
                $summary['skipped_repurchased_count']++;
                continue;
            }

            $tagName = customerFollowUpGetLostTagName($customerType, $lostType, $today);
            $tagId = customerFollowUpFindOrCreateTagId($connect, $tagName, 'SYSTEM');
            if ($tagId <= 0) {
                $summary['skipped_duplicate_count']++;
                continue;
            }

            if (customerFollowUpCustomerHasTagId($connect, isset($row['platform']) ? $row['platform'] : '', isset($row['customer_id']) ? (int) $row['customer_id'] : 0, $tagId)) {
                $summary['skipped_duplicate_count']++;
                continue;
            }

            mysqli_begin_transaction($connect);
            $assignResult = customerFollowUpAssignTagById(
                $connect,
                isset($row['platform']) ? $row['platform'] : '',
                isset($row['customer_id']) ? (int) $row['customer_id'] : 0,
                $tagId,
                'customer_follow_up',
                isset($row['id']) ? (int) $row['id'] : 0,
                'SYSTEM'
            );
            $caseUpdated = !empty($assignResult['success']) && customerFollowUpUpdateCaseRecord($connect, (int) $row['id'], array(
                'current_status' => 'Lost',
                'lost_tag_added' => 'Y',
                'lost_tag_id' => $tagId,
                'update_by' => 'SYSTEM',
                'update_date' => $today,
                'update_time' => customerFollowUpNowTime(),
            ));

            if (empty($assignResult['success']) || !$caseUpdated) {
                mysqli_rollback($connect);
                $summary['skipped_duplicate_count']++;
                continue;
            }

            $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, (int) $row['id']);
            $updatedRoundRow = customerFollowUpFetchRoundById($connect, isset($row['round_id']) ? (int) $row['round_id'] : 0);
            customerFollowUpLogTagChange(
                $connect,
                (int) $row['id'],
                isset($row['round_id']) ? (int) $row['round_id'] : 0,
                isset($row['platform']) ? $row['platform'] : '',
                isset($row['customer_id']) ? (int) $row['customer_id'] : 0,
                $tagId,
                'assign_lost_customer_tag',
                'SYSTEM',
                'Assigned lost customer tag.'
            );
            customerFollowUpCreateActionArtifacts(
                $connect,
                $updatedFollowUpRow,
                $updatedRoundRow,
                'mark_lost_customer',
                'Marked customer as lost',
                $row,
                array(
                    'current_status' => 'Lost',
                    'lost_tag_id' => $tagId,
                    'lost_tag_name' => $tagName,
                ),
                'System marked the customer as lost.',
                '',
                'SYSTEM'
            );

            foreach (customerFollowUpGetAdminUsers($connect) as $adminUser) {
                $notifyUserId = isset($adminUser['id']) ? (int) $adminUser['id'] : 0;
                if ($notifyUserId <= 0) {
                    continue;
                }

                customerFollowUpCreateNotificationRow($connect, array(
                    'follow_up_id' => (int) $row['id'],
                    'round_id' => isset($row['round_id']) ? (int) $row['round_id'] : 0,
                    'notify_user_id' => $notifyUserId,
                    'notify_role' => 'admin',
                    'notification_type' => 'lost_customer_tag_added',
                    'title' => 'Lost Customer Tag Added',
                    'message' => 'Lost tag [' . $tagName . '] was added for order ' . trim((string) (isset($row['order_no']) ? $row['order_no'] : '')) . '.',
                    'create_date' => $today,
                ));
            }

            mysqli_commit($connect);
            $summary['lost_tagged_count']++;
        }

        return $summary;
    }
}

if (!function_exists('customerFollowUpFetchOpenCaseByCustomer')) {
    /**
     * The customer's one live follow-up case, if they have one. Cases already finished
     * (Done) or written off (Lost) are not reused, so a returning customer starts fresh
     * instead of reopening a closed record.
     */
    function customerFollowUpFetchOpenCaseByCustomer($connect, $platform, $customerId)
    {
        foreach (customerFollowUpFetchActiveCasesByCustomer($connect, $platform, $customerId) as $caseRow) {
            $caseStatus = strtolower(trim((string) (isset($caseRow['current_status']) ? $caseRow['current_status'] : '')));
            if (in_array($caseStatus, array('done', 'lost'), true)) {
                continue;
            }

            return $caseRow;
        }

        return array();
    }
}

if (!function_exists('customerFollowUpTakeOverCaseWithNewOrder')) {
    /**
     * Points an existing customer case at a newly received order and advances it to the
     * next round, since rounds track the customer's follow-up history rather than one
     * order's. The rounds already recorded stay in the table, flagged as superseded so
     * they keep their history without being mistaken for the current round, and the
     * follow-up date being replaced is snapshotted onto the case and written to the action
     * log as an explicit overwrite.
     *
     * Returns the follow-up id on success, 0 when the caller should fall back to creating
     * a separate case.
     */
    function customerFollowUpTakeOverCaseWithNewOrder($connect, $existingCaseRow, $newOrderData)
    {
        $followUpId = isset($existingCaseRow['id']) ? (int) $existingCaseRow['id'] : 0;
        if (!($connect instanceof mysqli) || $followUpId <= 0) {
            return 0;
        }

        // Without the superseded flag the old rounds would still answer as the current
        // round, so leave the case alone rather than corrupting it.
        if (!customerFollowUpSupportsRoundSuperseded($connect)) {
            return 0;
        }

        $actorUserId = (int) (isset($newOrderData['actor_user_id']) ? $newOrderData['actor_user_id'] : 0);
        $newOrderId = (int) (isset($newOrderData['order_id']) ? $newOrderData['order_id'] : 0);
        if ($actorUserId <= 0 || $newOrderId <= 0) {
            return 0;
        }

        $currentRoundRow = customerFollowUpFetchCurrentRound($connect, $followUpId);
        $previousInfo = array(
            'order_id' => (int) (isset($existingCaseRow['order_id']) ? $existingCaseRow['order_id'] : 0),
            'order_no' => trim((string) (isset($existingCaseRow['order_no']) ? $existingCaseRow['order_no'] : '')),
            'package_name' => trim((string) (isset($existingCaseRow['package_name']) ? $existingCaseRow['package_name'] : '')),
            'received_date' => trim((string) (isset($existingCaseRow['received_date']) ? $existingCaseRow['received_date'] : '')),
            'round_no' => (int) (isset($currentRoundRow['round_no']) ? $currentRoundRow['round_no'] : (isset($existingCaseRow['current_round_no']) ? $existingCaseRow['current_round_no'] : 0)),
            'next_follow_up_date' => trim((string) (isset($currentRoundRow['next_follow_up_date']) ? $currentRoundRow['next_follow_up_date'] : '')),
            'round_status' => customerFollowUpNormalizeStatus(isset($currentRoundRow['round_status']) ? $currentRoundRow['round_status'] : ''),
            'replaced_on' => customerFollowUpNowDate(),
        );

        $today = customerFollowUpNowDate();
        $nowTime = customerFollowUpNowTime();
        $receivedDate = trim((string) (isset($newOrderData['received_date']) ? $newOrderData['received_date'] : '')) !== ''
            ? trim((string) $newOrderData['received_date'])
            : $today;

        // Rounds count the customer's follow-up history, not one order's, so the new order
        // continues the sequence, up to the module-wide ceiling. A case that completes the
        // final round is marked Done and is no longer reused, so the cap only bites here
        // while that last round is still open.
        $previousRoundNo = max(1, (int) (isset($existingCaseRow['current_round_no']) ? $existingCaseRow['current_round_no'] : 1));
        $nextRoundNo = min(customerFollowUpGetMaxRoundNo(), $previousRoundNo + 1);

        $supersedeSql = "UPDATE `" . CUSTOMER_FOLLOW_UP_ROUND . "`
                         SET `superseded` = 'Y',
                             `update_by` = '" . customerFollowUpEscape($connect, (string) $actorUserId) . "',
                             `update_date` = '" . customerFollowUpEscape($connect, $today) . "',
                             `update_time` = '" . customerFollowUpEscape($connect, $nowTime) . "'
                         WHERE `follow_up_id` = " . $followUpId . "
                           AND `status` = 'A'
                           AND IFNULL(`superseded`, 'N') <> 'Y'";
        if (!mysqli_query($connect, $supersedeSql)) {
            return 0;
        }

        $caseFields = array(
            'order_id' => $newOrderId,
            'order_no' => trim((string) (isset($newOrderData['order_no']) ? $newOrderData['order_no'] : '')),
            'package_name' => trim((string) (isset($newOrderData['package_name']) ? $newOrderData['package_name'] : '')),
            'received_date' => $receivedDate,
            'customer_type' => strtolower(trim((string) (isset($newOrderData['customer_type']) ? $newOrderData['customer_type'] : ''))) === 'return' ? 'return' : 'new',
            'purchase_count_snapshot' => (int) (isset($newOrderData['purchase_count_snapshot']) ? $newOrderData['purchase_count_snapshot'] : 0),
            'current_round_no' => $nextRoundNo,
            'current_status' => '',
            'follow_up_started' => 'Y',
            'update_by' => (string) $actorUserId,
            'update_date' => $today,
            'update_time' => $nowTime,
        );

        $newCustomerName = trim((string) (isset($newOrderData['customer_name']) ? $newOrderData['customer_name'] : ''));
        if ($newCustomerName !== '') {
            $caseFields['customer_name'] = $newCustomerName;
        }
        $newCustomerUsername = trim((string) (isset($newOrderData['customer_username']) ? $newOrderData['customer_username'] : ''));
        if ($newCustomerUsername !== '') {
            $caseFields['customer_username'] = $newCustomerUsername;
        }
        $newContactNo = trim((string) (isset($newOrderData['contact_no']) ? $newOrderData['contact_no'] : ''));
        if ($newContactNo !== '') {
            $caseFields['contact_no'] = $newContactNo;
        }
        if (customerFollowUpSupportsPreviousFollowUpInfo($connect)) {
            $caseFields['previous_follow_up_info'] = json_encode($previousInfo);
        }

        if (!customerFollowUpUpdateCaseRecord($connect, $followUpId, $caseFields)) {
            return 0;
        }

        $newRoundId = customerFollowUpCreateFollowUpRound($connect, array(
            'follow_up_id' => $followUpId,
            'round_no' => $nextRoundNo,
            'stage_no' => $nextRoundNo,
            'previous_follow_up_date' => $receivedDate,
            'approval_status' => 'pending',
            'postpone_status' => 'none',
            'round_status' => '',
            'create_by' => (string) $actorUserId,
        ));

        if ($newRoundId <= 0) {
            return 0;
        }

        $overwriteRemark = 'Overwrite follow up date: order '
            . ($previousInfo['order_no'] !== '' ? $previousInfo['order_no'] : ('#' . $previousInfo['order_id']))
            . ' round ' . max(1, (int) $previousInfo['round_no'])
            . ' was following up on ' . ($previousInfo['next_follow_up_date'] !== '' ? $previousInfo['next_follow_up_date'] : 'no date')
            . ', replaced by order ' . ($caseFields['order_no'] !== '' ? $caseFields['order_no'] : ('#' . $newOrderId))
            . ' received on ' . $receivedDate
            . ' (now round ' . $nextRoundNo . ').';

        customerFollowUpInsertActionLog($connect, array(
            'follow_up_id' => $followUpId,
            'round_id' => $newRoundId,
            'action_type' => 'overwrite_follow_up_date',
            'old_value' => $previousInfo,
            'new_value' => array(
                'order_id' => $newOrderId,
                'order_no' => $caseFields['order_no'],
                'received_date' => $receivedDate,
                'round_no' => $nextRoundNo,
            ),
            'remark' => $overwriteRemark,
            'action_by' => (string) $actorUserId,
        ));

        if (function_exists('audit_log')) {
            $actorDisplayName = function_exists('customerFollowUpGetUserDisplayName')
                ? customerFollowUpGetUserDisplayName($connect, $actorUserId)
                : (string) $actorUserId;

            audit_log(array(
                'log_act'     => 'edit',
                'uid'         => $actorUserId,
                'cby'         => $actorUserId,
                'query_rec'   => $followUpId,
                'query_table' => CUSTOMER_FOLLOW_UP,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'oldval'      => json_encode($previousInfo),
                'changes'     => json_encode($caseFields),
                'act_msg'     => $actorDisplayName . ' overwrote the follow-up on case [<b> ID = ' . $followUpId . '</b> ] with a newly received order.',
            ));
        }

        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpFetchRoundById($connect, $newRoundId);
        if (!empty($followUpRow)) {
            customerFollowUpApplyCustomerTypeTags($connect, $followUpRow, $roundRow, (string) $actorUserId);
        }

        return $followUpId;
    }
}

if (!function_exists('customerFollowUpStartFromReceivedOrder')) {
    function customerFollowUpStartFromReceivedOrder($connect, $platform, $orderId, $orderNo, $customerId, $customerUsername, $packageName, $receivedDate, $purchaseCountSnapshot, $currentUserId, $customerName = '', $contactNo = '')
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderId = (int) $orderId;
        $purchaseCountSnapshot = (int) $purchaseCountSnapshot;
        $currentUserId = (int) $currentUserId;

        if ($platform === '' || $orderId <= 0 || $currentUserId <= 0) {
            return 0;
        }

        $existingRow = customerFollowUpFetchActiveByOrderPlatform($connect, $platform, $orderId);
        if (!empty($existingRow)) {
            return (int) $existingRow['id'];
        }

        $customerType = $purchaseCountSnapshot >= 1 ? 'return' : 'new';

        // A customer gets one follow-up record, not one per order. When the customer
        // already has an open case, the new order takes it over: the case moves to the new
        // order and moves on to the next round, with the follow-up it was carrying kept both as a
        // snapshot on the record and as an action log entry.
        $openCustomerCase = customerFollowUpFetchOpenCaseByCustomer($connect, $platform, (int) $customerId);
        if (!empty($openCustomerCase)) {
            $takeoverFollowUpId = customerFollowUpTakeOverCaseWithNewOrder($connect, $openCustomerCase, array(
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'customer_name' => $customerName,
                'customer_username' => $customerUsername,
                'package_name' => $packageName,
                'received_date' => $receivedDate,
                'customer_type' => $customerType,
                'purchase_count_snapshot' => $purchaseCountSnapshot,
                'contact_no' => $contactNo,
                'actor_user_id' => $currentUserId,
            ));

            if ($takeoverFollowUpId > 0) {
                return $takeoverFollowUpId;
            }
        }

        $followUpId = customerFollowUpCreateFollowUpCase($connect, array(
            'platform' => $platform,
            'order_id' => $orderId,
            'order_no' => $orderNo,
            'customer_id' => (int) $customerId,
            'customer_name' => $customerName,
            'customer_username' => $customerUsername,
            'package_name' => $packageName,
            'received_date' => $receivedDate,
            'customer_type' => $customerType,
            'purchase_count_snapshot' => $purchaseCountSnapshot,
            'current_round_no' => 1,
            'current_status' => '',
            'contact_no' => $contactNo,
            'assigned_user_id' => $currentUserId,
            'follow_up_started' => 'Y',
            'create_by' => (string) $currentUserId,
        ));

        if ($followUpId <= 0) {
            return 0;
        }

        $roundId = customerFollowUpCreateFollowUpRound($connect, array(
            'follow_up_id' => $followUpId,
            'round_no' => 1,
            'stage_no' => 1,
            'previous_follow_up_date' => $receivedDate,
            'approval_status' => 'pending',
            'postpone_status' => 'none',
            'round_status' => '',
            'create_by' => (string) $currentUserId,
        ));

        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = $roundId > 0 ? customerFollowUpFetchRoundById($connect, $roundId) : array();
        if (!empty($followUpRow)) {
            customerFollowUpApplyCustomerTypeTags($connect, $followUpRow, $roundRow, (string) $currentUserId);
        }

        if (function_exists('audit_log')) {
            $actorDisplayName = function_exists('customerFollowUpGetUserDisplayName')
                ? customerFollowUpGetUserDisplayName($connect, $currentUserId)
                : (string) $currentUserId;

            audit_log(array(
                'log_act'     => 'add',
                'uid'         => $currentUserId,
                'cby'         => $currentUserId,
                'query_rec'   => $followUpId,
                'query_table' => CUSTOMER_FOLLOW_UP,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'newval'      => "platform=$platform, order_id=$orderId, customer_type=$customerType",
                'act_msg'     => $actorDisplayName . " started a customer follow-up case [<b> ID = " . $followUpId . "</b> ] for order <b>" . $orderNo . "</b> (" . $platform . ").",
            ));
        }

        // This helper is used by the Confirm Received follow-up flow and can be reused by other received-order entry points.
        // Keep using purchase_count_snapshot captured at Confirm Received time so later order changes do not retroactively change customer_type.
        return $followUpId;
    }
}

if (!function_exists('customerFollowUpStartFromCustomer')) {
    /**
     * Opens a follow-up case that belongs to a customer rather than to an order, for
     * follow-ups entered straight from a customer page. The case carries order_id = 0
     * and case_source = 'customer'; everything downstream (rounds, approvals, tags,
     * notifications, the cron jobs) treats it like any other case.
     */
    function customerFollowUpStartFromCustomer($connect, $platform, $customerId, $currentUserId, $options = array())
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $currentUserId = (int) $currentUserId;

        if ($platform === '' || $customerId <= 0 || $currentUserId <= 0) {
            return 0;
        }

        $today = customerFollowUpNowDate();
        $followUpId = customerFollowUpCreateFollowUpCase($connect, array(
            'platform' => $platform,
            'case_source' => 'customer',
            'order_id' => 0,
            'order_no' => '',
            'customer_id' => $customerId,
            'customer_name' => isset($options['customer_name']) ? $options['customer_name'] : '',
            'customer_username' => isset($options['customer_username']) ? $options['customer_username'] : '',
            'package_name' => '',
            'received_date' => $today,
            'customer_type' => isset($options['customer_type']) ? $options['customer_type'] : 'new',
            'purchase_count_snapshot' => 0,
            'current_round_no' => 1,
            'current_status' => '',
            'contact_no' => isset($options['contact_no']) ? $options['contact_no'] : '',
            'assigned_user_id' => $currentUserId,
            'follow_up_started' => 'Y',
            'create_by' => (string) $currentUserId,
        ));

        if ($followUpId <= 0) {
            return 0;
        }

        customerFollowUpCreateFollowUpRound($connect, array(
            'follow_up_id' => $followUpId,
            'round_no' => 1,
            'stage_no' => 1,
            'previous_follow_up_date' => $today,
            'approval_status' => 'pending',
            'postpone_status' => 'none',
            'round_status' => '',
            'create_by' => (string) $currentUserId,
        ));

        if (function_exists('audit_log')) {
            $actorDisplayName = function_exists('customerFollowUpGetUserDisplayName')
                ? customerFollowUpGetUserDisplayName($connect, $currentUserId)
                : (string) $currentUserId;

            audit_log(array(
                'log_act'     => 'add',
                'uid'         => $currentUserId,
                'cby'         => $currentUserId,
                'query_rec'   => $followUpId,
                'query_table' => CUSTOMER_FOLLOW_UP,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'newval'      => "platform=$platform, customer_id=$customerId, case_source=customer",
                'act_msg'     => $actorDisplayName . ' started a customer follow-up case [<b> ID = ' . $followUpId . '</b> ] from the customer page (' . $platform . ').',
            ));
        }

        return $followUpId;
    }
}

if (!function_exists('customerFollowUpResolveCaseForCustomerLog')) {
    /**
     * Picks which follow-up case a customer page entry belongs to:
     *   1. an explicitly chosen case, when the user selected one;
     *   2. the customer's open case, since a customer only ever has one;
     *   3. otherwise a new customer-sourced case.
     * Cases that are already closed (Done / Lost) are never reused.
     *
     * Scheduling a date from the customer page is treated as a scheduling action rather
     * than a case action, so it is deliberately not gated on customerFollowUpCanUserManageCase():
     * that rule still guards submitting, appealing and postponing a round, but blocking a
     * date here would leave whoever is on the customer page unable to record anything,
     * since one customer now has exactly one case to write to. Who made the change is
     * recorded on the action log instead.
     */
    function customerFollowUpResolveCaseForCustomerLog($connect, $platform, $customerId, $requestedFollowUpId, $actorUserId, $options = array())
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerId = (int) $customerId;
        $requestedFollowUpId = (int) $requestedFollowUpId;

        if ($platform === '' || $customerId <= 0) {
            return array();
        }

        if ($requestedFollowUpId > 0) {
            $requestedRow = customerFollowUpReadFollowUpCase($connect, $requestedFollowUpId);
            if (empty($requestedRow)
                || customerFollowUpNormalizePlatform(isset($requestedRow['platform']) ? $requestedRow['platform'] : '') !== $platform
                || (int) (isset($requestedRow['customer_id']) ? $requestedRow['customer_id'] : 0) !== $customerId
            ) {
                return array();
            }

            return $requestedRow;
        }

        // A customer has at most one live case, so reuse it rather than opening a second
        // record, whoever it happens to be assigned to.
        $openCase = customerFollowUpFetchOpenCaseByCustomer($connect, $platform, $customerId);
        if (!empty($openCase)) {
            return $openCase;
        }

        $newFollowUpId = customerFollowUpStartFromCustomer($connect, $platform, $customerId, (int) $actorUserId, $options);

        return $newFollowUpId > 0 ? customerFollowUpReadFollowUpCase($connect, $newFollowUpId) : array();
    }
}

if (!function_exists('customerFollowUpAdjustCaseRound')) {
    /**
     * Sets the case's round by hand, which is what the customer page's follow-up count
     * edits. Moving the round down is allowed but flagged: it rewinds the follow-up
     * progress the module otherwise only ever advances, so the caller gets a warning back
     * and the admins get a notification, on top of the from/to entry in the action log.
     *
     * Returns array('success', 'message', 'warning', 'old_round_no', 'new_round_no').
     */
    function customerFollowUpAdjustCaseRound($connect, $followUpRow, $newRoundNo, $actorUserId)
    {
        $result = array('success' => false, 'message' => '', 'warning' => '', 'old_round_no' => 0, 'new_round_no' => 0);

        $followUpId = isset($followUpRow['id']) ? (int) $followUpRow['id'] : 0;
        $actorUserId = (int) $actorUserId;
        $newRoundNo = (int) $newRoundNo;
        $maxRoundNo = customerFollowUpGetMaxRoundNo();

        if (!($connect instanceof mysqli) || $followUpId <= 0 || $actorUserId <= 0) {
            return $result;
        }

        if ($newRoundNo < 1 || $newRoundNo > $maxRoundNo) {
            $result['message'] = 'Follow-Up Round must be between 1 and ' . $maxRoundNo . '.';
            return $result;
        }

        $oldRoundNo = max(1, (int) (isset($followUpRow['current_round_no']) ? $followUpRow['current_round_no'] : 1));
        $result['old_round_no'] = $oldRoundNo;
        $result['new_round_no'] = $newRoundNo;

        if ($oldRoundNo === $newRoundNo) {
            $result['success'] = true;
            return $result;
        }

        $today = customerFollowUpNowDate();
        $nowTime = customerFollowUpNowTime();

        if (!customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'current_round_no' => $newRoundNo,
            'update_by' => (string) $actorUserId,
            'update_date' => $today,
            'update_time' => $nowTime,
        ))) {
            $result['message'] = 'Unable to update the follow-up round.';
            return $result;
        }

        // The round the case now points at has to exist, or the listing and the crons have
        // no current round to read.
        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $roundRow = customerFollowUpCreateOrLoadCurrentRound($connect, $updatedFollowUpRow);

        $isRewind = $newRoundNo < $oldRoundNo;
        $remark = 'Follow-Up Round changed from ' . $oldRoundNo . ' to ' . $newRoundNo . '.';
        if ($isRewind) {
            $remark .= ' This moves the follow-up backwards.';
        }

        customerFollowUpInsertActionLog($connect, array(
            'follow_up_id' => $followUpId,
            'round_id' => isset($roundRow['id']) ? (int) $roundRow['id'] : 0,
            'action_type' => 'adjust_follow_up_round',
            'old_value' => array('round_no' => $oldRoundNo),
            'new_value' => array('round_no' => $newRoundNo),
            'remark' => $remark,
            'action_by' => (string) $actorUserId,
        ));

        $actorDisplayName = customerFollowUpGetUserDisplayName($connect, $actorUserId);

        if (function_exists('audit_log')) {
            audit_log(array(
                'log_act'     => 'edit',
                'uid'         => $actorUserId,
                'cby'         => $actorUserId,
                'query_rec'   => $followUpId,
                'query_table' => CUSTOMER_FOLLOW_UP,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'oldval'      => 'current_round_no=' . $oldRoundNo,
                'changes'     => 'current_round_no=' . $newRoundNo,
                'act_msg'     => $actorDisplayName . ' changed the follow-up round on case [<b> ID = ' . $followUpId . '</b> ] from ' . $oldRoundNo . ' to ' . $newRoundNo . '.',
            ));
        }

        if ($isRewind) {
            $result['warning'] = 'Follow-Up Round was moved back from ' . $oldRoundNo . ' to ' . $newRoundNo . '. The change has been recorded.';

            foreach (customerFollowUpGetAdminUsers($connect) as $adminUser) {
                $notifyUserId = isset($adminUser['id']) ? (int) $adminUser['id'] : 0;
                if ($notifyUserId <= 0) {
                    continue;
                }

                customerFollowUpCreateNotificationRow($connect, array(
                    'follow_up_id' => $followUpId,
                    'round_id' => isset($roundRow['id']) ? (int) $roundRow['id'] : 0,
                    'notify_user_id' => $notifyUserId,
                    'notify_role' => 'admin',
                    'notification_type' => 'follow_up_round_moved_back',
                    'title' => 'Follow-Up Round Moved Back',
                    'message' => $actorDisplayName . ' changed the follow-up round from ' . $oldRoundNo . ' to ' . $newRoundNo . ' on case ID ' . $followUpId . '.',
                    'create_date' => $today,
                ));
            }
        }

        $result['success'] = true;
        return $result;
    }
}

if (!function_exists('customerFollowUpSyncFromCustomerLog')) {
    /**
     * Bridge used by the customer page User Record Log: a log entry that carries a Next
     * Follow-Up Date becomes a real follow-up round in the Follow Up List, instead of a
     * note that only lives on the customer page.
     *
     * Returns array('follow_up_id' => int, 'round_id' => int, 'message' => string,
     * 'warning' => string).
     */
    function customerFollowUpSyncFromCustomerLog($connect, $data)
    {
        $result = array('follow_up_id' => 0, 'round_id' => 0, 'message' => '', 'warning' => '');

        if (!($connect instanceof mysqli)) {
            return $result;
        }

        $platform = customerFollowUpNormalizePlatform(isset($data['platform']) ? $data['platform'] : '');
        $customerId = (int) (isset($data['customer_id']) ? $data['customer_id'] : 0);
        $nextFollowUpDate = trim((string) (isset($data['next_follow_up_date']) ? $data['next_follow_up_date'] : ''));
        $actorUserId = (int) (isset($data['actor_user_id']) ? $data['actor_user_id'] : (defined('USER_ID') ? USER_ID : 0));

        // The round can be edited on its own, so a request carrying only a round still has
        // work to do even with no date.
        $requestedRoundNo = isset($data['follow_up_round']) && trim((string) $data['follow_up_round']) !== ''
            ? (int) $data['follow_up_round']
            : 0;
        $hasDate = $nextFollowUpDate !== '' && customerFollowUpIsValidDateString($nextFollowUpDate);

        if ($platform === '' || $customerId <= 0 || $actorUserId <= 0) {
            return $result;
        }
        if (!$hasDate && $requestedRoundNo <= 0) {
            return $result;
        }

        $requestedFollowUpId = isset($data['follow_up_id']) ? (int) $data['follow_up_id'] : 0;
        $followUpRow = customerFollowUpResolveCaseForCustomerLog(
            $connect,
            $platform,
            $customerId,
            $requestedFollowUpId,
            $actorUserId,
            array(
                'customer_name' => isset($data['customer_name']) ? $data['customer_name'] : '',
                'customer_username' => isset($data['customer_username']) ? $data['customer_username'] : '',
                'contact_no' => isset($data['contact_no']) ? $data['contact_no'] : '',
                'actor_user_group_id' => isset($data['actor_user_group_id'])
                    ? $data['actor_user_group_id']
                    : (defined('USER_GROUP') ? USER_GROUP : null),
            )
        );

        if (empty($followUpRow)) {
            $result['message'] = $requestedFollowUpId > 0
                ? 'That follow-up case does not belong to this customer.'
                : 'Unable to resolve a follow-up case for this customer.';
            return $result;
        }

        $followUpId = (int) $followUpRow['id'];
        $result['follow_up_id'] = $followUpId;

        // Apply the round first: the date rules below are measured against the round the
        // case is on, so they must see the round the user just set.
        if ($requestedRoundNo > 0) {
            $roundAdjustment = customerFollowUpAdjustCaseRound($connect, $followUpRow, $requestedRoundNo, $actorUserId);
            if (empty($roundAdjustment['success'])) {
                $result['message'] = trim((string) $roundAdjustment['message']) !== ''
                    ? $roundAdjustment['message']
                    : 'Unable to update the follow-up round.';
                return $result;
            }

            if (trim((string) $roundAdjustment['warning']) !== '') {
                $result['warning'] = $roundAdjustment['warning'];
            }

            $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        }

        $roundRow = customerFollowUpCreateOrLoadCurrentRound($connect, $followUpRow);
        if (empty($roundRow)) {
            $result['message'] = 'Unable to prepare the follow-up round.';
            return $result;
        }

        $roundId = (int) $roundRow['id'];
        $result['round_id'] = $roundId;

        if (!$hasDate) {
            return $result;
        }

        $dateValidation = customerFollowUpValidateNextFollowUpDateLimit($followUpRow, $roundRow, $nextFollowUpDate);
        if (empty($dateValidation['success'])) {
            $result['message'] = isset($dateValidation['message']) ? $dateValidation['message'] : 'Next Follow-Up Date is invalid.';
            return $result;
        }

        $previousNextFollowUpDate = trim((string) (isset($roundRow['next_follow_up_date']) ? $roundRow['next_follow_up_date'] : ''));
        if ($previousNextFollowUpDate === $nextFollowUpDate) {
            return $result;
        }

        $updateFields = array('next_follow_up_date' => $nextFollowUpDate);

        $messageShortcutId = (int) (isset($data['message_shortcut_id']) ? $data['message_shortcut_id'] : 0);
        if ($messageShortcutId > 0) {
            $shortcutRow = customerFollowUpGetMessageShortcutById($connect, $messageShortcutId);
            if (!empty($shortcutRow)) {
                $updateFields['message_shortcut_id'] = $messageShortcutId;
                $updateFields['message_shortcut_text'] = isset($shortcutRow['shortcuts_message_text'])
                    ? $shortcutRow['shortcuts_message_text']
                    : '';
            }
        }

        // The attachment is deliberately not copied across: user record log rows store an
        // encoded list of their own uploads, while a round stores one follow-up attachment
        // path, and mixing the two formats would break the Follow Up List preview links.

        $contactNo = trim((string) (isset($data['contact_no']) ? $data['contact_no'] : ''));
        if ($contactNo !== '') {
            $updateFields['contact_no'] = $contactNo;
        }

        $updateFields['update_by'] = (string) $actorUserId;
        $updateFields['update_date'] = customerFollowUpNowDate();
        $updateFields['update_time'] = customerFollowUpNowTime();

        if (!customerFollowUpUpdateRoundRecord($connect, $roundId, $updateFields)) {
            $result['message'] = 'Unable to save the next follow-up date on the follow-up round.';
            return $result;
        }

        // The customer page lets anyone schedule the date, so make it visible in the log
        // when that was not the person the case is assigned to.
        $actionRemark = trim((string) (isset($data['remark']) ? $data['remark'] : ''));
        $assignedUserId = (int) (isset($followUpRow['assigned_user_id']) ? $followUpRow['assigned_user_id'] : 0);
        if ($assignedUserId !== $actorUserId) {
            $actorLabel = customerFollowUpGetUserDisplayName($connect, $actorUserId);
            $assignedLabel = $assignedUserId > 0
                ? customerFollowUpGetUserDisplayName($connect, $assignedUserId)
                : 'nobody';
            $actionRemark = trim($actionRemark . ' Scheduled by ' . $actorLabel . ', who is not the assigned user (' . $assignedLabel . ').');
        }

        customerFollowUpInsertActionLog($connect, array(
            'follow_up_id' => $followUpId,
            'round_id' => $roundId,
            'action_type' => 'schedule_next_follow_up_from_customer_page',
            'old_value' => array(
                'next_follow_up_date' => $previousNextFollowUpDate,
                'assigned_user_id' => $assignedUserId,
            ),
            'new_value' => array('next_follow_up_date' => $nextFollowUpDate),
            'remark' => $actionRemark,
            'action_by' => (string) $actorUserId,
        ));

        if (function_exists('audit_log')) {
            $actorDisplayName = function_exists('customerFollowUpGetUserDisplayName')
                ? customerFollowUpGetUserDisplayName($connect, $actorUserId)
                : (string) $actorUserId;

            audit_log(array(
                'log_act'     => 'edit',
                'uid'         => $actorUserId,
                'cby'         => $actorUserId,
                'query_rec'   => $followUpId,
                'query_table' => CUSTOMER_FOLLOW_UP,
                'page'        => 'Customer Follow-Up',
                'connect'     => $connect,
                'oldval'      => 'next_follow_up_date=' . $previousNextFollowUpDate,
                'changes'     => 'next_follow_up_date=' . $nextFollowUpDate,
                'act_msg'     => $actorDisplayName . ' scheduled the next follow-up date for case [<b> ID = ' . $followUpId . '</b> ] from the customer page.',
            ));
        }

        return $result;
    }
}

if (!function_exists('customerFollowUpDeleteAttachmentFile')) {
    function customerFollowUpDeleteAttachmentFile($attachmentPath)
    {
        $attachmentPath = customerFollowUpNormalizeAttachmentPath($attachmentPath);
        if ($attachmentPath === '') {
            return;
        }

        $targetFile = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $attachmentPath);
        if (is_file($targetFile)) {
            @unlink($targetFile);
        }
    }
}

if (!function_exists('customerFollowUpResolveDbConnection')) {
    function customerFollowUpResolveDbConnection($connect, $financeConnect, $dbKey)
    {
        $dbKey = strtolower(trim((string) $dbKey));
        return $dbKey === 'finance' ? $financeConnect : $connect;
    }
}

if (!function_exists('customerFollowUpGetUniqueConnections')) {
    function customerFollowUpGetUniqueConnections($connections)
    {
        $unique = array();
        $seen = array();

        foreach ((array) $connections as $connection) {
            if (!($connection instanceof mysqli)) {
                continue;
            }

            $hash = function_exists('spl_object_hash') ? spl_object_hash($connection) : md5((string) $connection->thread_id);
            if (isset($seen[$hash])) {
                continue;
            }

            $seen[$hash] = true;
            $unique[] = $connection;
        }

        return $unique;
    }
}

if (!function_exists('customerFollowUpBeginTransactions')) {
    function customerFollowUpBeginTransactions($connections)
    {
        foreach (customerFollowUpGetUniqueConnections($connections) as $connection) {
            if (!mysqli_begin_transaction($connection)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('customerFollowUpCommitTransactions')) {
    function customerFollowUpCommitTransactions($connections)
    {
        foreach (customerFollowUpGetUniqueConnections($connections) as $connection) {
            if (!mysqli_commit($connection)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('customerFollowUpRollbackTransactions')) {
    function customerFollowUpRollbackTransactions($connections)
    {
        foreach (customerFollowUpGetUniqueConnections($connections) as $connection) {
            @mysqli_rollback($connection);
        }
    }
}

if (!function_exists('customerFollowUpFetchSingleRow')) {
    function customerFollowUpFetchSingleRow($dbConnect, $tblName, $whereClause)
    {
        if (!($dbConnect instanceof mysqli) || trim((string) $tblName) === '' || trim((string) $whereClause) === '') {
            return array();
        }

        $result = getData('*', '(' . $whereClause . ") AND `status` = 'A'", 'LIMIT 1', $tblName, $dbConnect);
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return array();
    }
}

if (!function_exists('customerFollowUpResolveCustomerRowFromOrder')) {
    function customerFollowUpResolveCustomerRowFromOrder($connect, $financeConnect, $platform, $orderRow)
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderRow = is_array($orderRow) ? $orderRow : array();
        $platformConfig = function_exists('customerLabelGetPlatformConfig') ? customerLabelGetPlatformConfig($platform) : array();
        if ($platform === '' || empty($platformConfig) || empty($platformConfig['customer_table'])) {
            return array();
        }

        $customerConnect = customerFollowUpResolveDbConnection($connect, $financeConnect, isset($platformConfig['customer_db']) ? $platformConfig['customer_db'] : 'cms');
        if (!($customerConnect instanceof mysqli)) {
            return array();
        }

        if ($platform === 'facebook') {
            $nameValue = trim((string) (isset($orderRow['name']) ? $orderRow['name'] : ''));
            $fbLinkValue = trim((string) (isset($orderRow['fb_link']) ? $orderRow['fb_link'] : ''));
            if ($nameValue === '' && $fbLinkValue === '') {
                return array();
            }

            $whereParts = array();
            if ($nameValue !== '') {
                $whereParts[] = "`name` = '" . mysqli_real_escape_string($customerConnect, $nameValue) . "'";
            } else {
                $whereParts[] = "IFNULL(`name`, '') = ''";
            }
            if ($fbLinkValue !== '') {
                $whereParts[] = "`fb_link` = '" . mysqli_real_escape_string($customerConnect, $fbLinkValue) . "'";
            } else {
                $whereParts[] = "IFNULL(`fb_link`, '') = ''";
            }

            return customerFollowUpFetchSingleRow($customerConnect, $platformConfig['customer_table'], implode(' AND ', $whereParts));
        }

        $orderCustomerField = isset($platformConfig['order_customer_field']) ? trim((string) $platformConfig['order_customer_field']) : '';
        $orderCustomerValue = $orderCustomerField !== '' && isset($orderRow[$orderCustomerField]) ? trim((string) $orderRow[$orderCustomerField]) : '';
        if ($orderCustomerValue === '') {
            return array();
        }

        if (ctype_digit($orderCustomerValue)) {
            $customerRow = customerFollowUpFetchSingleRow($customerConnect, $platformConfig['customer_table'], "`id` = '" . (int) $orderCustomerValue . "'");
            if (!empty($customerRow)) {
                return $customerRow;
            }
        }

        $lookupFieldMap = array(
            'shopee' => 'buyer_username',
            'lazada' => 'lcr_id',
            'website' => 'cust_id',
        );
        $lookupField = isset($lookupFieldMap[$platform]) ? $lookupFieldMap[$platform] : '';
        if ($lookupField === '') {
            return array();
        }

        return customerFollowUpFetchSingleRow(
            $customerConnect,
            $platformConfig['customer_table'],
            "`" . $lookupField . "` = '" . mysqli_real_escape_string($customerConnect, $orderCustomerValue) . "'"
        );
    }
}

if (!function_exists('customerFollowUpResolveCustomerIdFromOrder')) {
    function customerFollowUpResolveCustomerIdFromOrder($platform, $orderRow, $customerRow = array())
    {
        if (!empty($customerRow['id'])) {
            return (int) $customerRow['id'];
        }

        return 0;
    }
}

if (!function_exists('customerFollowUpResolveCustomerUsername')) {
    function customerFollowUpResolveCustomerUsername($connect, $financeConnect, $platform, $orderRow, $customerRow = array())
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderRow = is_array($orderRow) ? $orderRow : array();
        $customerRow = is_array($customerRow) ? $customerRow : array();
        $displayName = function_exists('shopeeOmsGetOrderCustomerNameText')
            ? trim((string) shopeeOmsGetOrderCustomerNameText($connect, $financeConnect, $orderRow, $platform))
            : '';

        if ($platform === 'shopee' && $displayName === '' && isset($customerRow['buyer_username'])) {
            $displayName = trim((string) $customerRow['buyer_username']);
        }

        if ($displayName === '') {
            if ($platform === 'facebook') {
                $displayName = trim((string) (isset($orderRow['name']) ? $orderRow['name'] : ''));
            } else if (isset($orderRow['cust_name'])) {
                $displayName = trim((string) $orderRow['cust_name']);
            } else if (isset($orderRow['customer_name'])) {
                $displayName = trim((string) $orderRow['customer_name']);
            }
        }

        return $displayName;
    }
}

if (!function_exists('customerFollowUpResolveCustomerName')) {
    function customerFollowUpResolveCustomerName($platform, $orderRow, $customerRow = array(), $customerUsername = '')
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $customerRow = is_array($customerRow) ? $customerRow : array();
        $customerUsername = trim((string) $customerUsername);

        if ($platform === 'facebook') {
            return trim((string) (isset($customerRow['name']) && $customerRow['name'] !== '' ? $customerRow['name'] : (isset($orderRow['name']) ? $orderRow['name'] : $customerUsername)));
        }
        if ($platform === 'website' || $platform === 'lazada') {
            return trim((string) (isset($customerRow['name']) && $customerRow['name'] !== '' ? $customerRow['name'] : (isset($orderRow['cust_name']) ? $orderRow['cust_name'] : $customerUsername)));
        }

        return trim((string) (isset($customerRow['buyer_username']) && $customerRow['buyer_username'] !== '' ? $customerRow['buyer_username'] : $customerUsername));
    }
}

if (!function_exists('customerFollowUpResolveContactNo')) {
    function customerFollowUpResolveContactNo($platform, $orderRow, $customerRow = array(), $existingFollowUpRow = array(), $existingRoundRow = array())
    {
        $candidates = array();

        if (!empty($existingRoundRow['contact_no'])) {
            $candidates[] = $existingRoundRow['contact_no'];
        }
        if (!empty($existingFollowUpRow['contact_no'])) {
            $candidates[] = $existingFollowUpRow['contact_no'];
        }

        $customerFieldMap = array(
            'shopee' => array('contact_no'),
            'lazada' => array('phone', 'ship_rec_contact'),
            'facebook' => array('contact', 'ship_rec_contact'),
            'website' => array('contact', 'ship_rec_contact'),
        );
        $orderFieldMap = array(
            'lazada' => array('cust_phone', 'ship_rec_contact'),
            'facebook' => array('contact', 'ship_rec_contact'),
            'website' => array('shipping_contact'),
        );

        if (isset($customerFieldMap[$platform])) {
            foreach ($customerFieldMap[$platform] as $fieldName) {
                if (!empty($customerRow[$fieldName])) {
                    $candidates[] = $customerRow[$fieldName];
                }
            }
        }

        if (isset($orderFieldMap[$platform])) {
            foreach ($orderFieldMap[$platform] as $fieldName) {
                if (!empty($orderRow[$fieldName])) {
                    $candidates[] = $orderRow[$fieldName];
                }
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('customerFollowUpResolveComparableOrderTimestamp')) {
    function customerFollowUpResolveComparableOrderTimestamp($orderRow, $sourceConfig)
    {
        $dateField = isset($sourceConfig['date_field']) && trim((string) $sourceConfig['date_field']) !== ''
            ? trim((string) $sourceConfig['date_field'])
            : 'create_date';
        $timeField = isset($orderRow['time']) ? 'time' : (isset($orderRow['create_time']) ? 'create_time' : '');
        $dateValue = trim((string) (isset($orderRow[$dateField]) ? $orderRow[$dateField] : ''));
        $timeValue = $timeField !== '' && isset($orderRow[$timeField]) ? trim((string) $orderRow[$timeField]) : '00:00:00';

        if ($dateValue === '') {
            return 0;
        }

        $timestamp = strtotime($dateValue . ' ' . ($timeValue !== '' ? $timeValue : '00:00:00'));
        return $timestamp !== false ? (int) $timestamp : 0;
    }
}

if (!function_exists('customerFollowUpIsPreviousPurchaseRow')) {
    function customerFollowUpIsPreviousPurchaseRow($candidateRow, $currentOrderRow, $sourceConfig)
    {
        $candidateTimestamp = customerFollowUpResolveComparableOrderTimestamp($candidateRow, $sourceConfig);
        $currentTimestamp = customerFollowUpResolveComparableOrderTimestamp($currentOrderRow, $sourceConfig);
        $candidateId = isset($candidateRow['id']) ? (int) $candidateRow['id'] : 0;
        $currentId = isset($currentOrderRow['id']) ? (int) $currentOrderRow['id'] : 0;

        if ($currentTimestamp <= 0 || $candidateTimestamp <= 0) {
            return $candidateId > 0 && $currentId > 0 ? $candidateId < $currentId : true;
        }

        if ($candidateTimestamp < $currentTimestamp) {
            return true;
        }

        return $candidateTimestamp === $currentTimestamp && $candidateId > 0 && $currentId > 0 && $candidateId < $currentId;
    }
}

if (!function_exists('customerFollowUpCountPreviousPurchases')) {
    function customerFollowUpCountPreviousPurchases($connect, $financeConnect, $platform, $sourceConfig, $orderRow, $customerRow = array())
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $sourceConfig = is_array($sourceConfig) ? $sourceConfig : array();
        $orderConnect = function_exists('shopeeOmsGetOrderSourceDbConnection')
            ? shopeeOmsGetOrderSourceDbConnection($connect, $financeConnect, $sourceConfig)
            : $connect;
        $platformConfig = function_exists('customerLabelGetPlatformConfig') ? customerLabelGetPlatformConfig($platform) : array();
        $customerConnect = customerFollowUpResolveDbConnection($connect, $financeConnect, isset($platformConfig['customer_db']) ? $platformConfig['customer_db'] : 'cms');
        if (!($orderConnect instanceof mysqli) || !($customerConnect instanceof mysqli) || empty($sourceConfig['table']) || empty($platformConfig['customer_table'])) {
            return 0;
        }

        $currentOrderId = isset($orderRow['id']) ? (int) $orderRow['id'] : 0;
        $seriesLookup = function_exists('customerLabelGetSeriesLookup') ? customerLabelGetSeriesLookup($connect) : array();
        $customerRows = array();
        $customerResult = getData('*', "status = 'A'", '', $platformConfig['customer_table'], $customerConnect);
        if ($customerResult) {
            while ($customerResultRow = $customerResult->fetch_assoc()) {
                $customerRows[] = $customerResultRow;
            }
        }

        $customerIndexes = function_exists('customerLabelBuildCustomerIndexes')
            ? customerLabelBuildCustomerIndexes($platform, $customerRows, $seriesLookup)
            : array();
        if (empty($customerIndexes['rows_by_id'])) {
            return 0;
        }

        $targetCustomerId = !empty($customerRow['id'])
            ? (int) $customerRow['id']
            : (function_exists('customerLabelResolveOrderCustomerId') ? customerLabelResolveOrderCustomerId($platform, $orderRow, $customerIndexes) : 0);
        if ($targetCustomerId <= 0) {
            return 0;
        }

        $orderRows = array();
        $orderResult = getData('*', "status = 'A'", '', $sourceConfig['table'], $orderConnect);
        if ($orderResult) {
            while ($orderResultRow = $orderResult->fetch_assoc()) {
                $orderRows[] = $orderResultRow;
            }
        }

        $count = 0;
        foreach ($orderRows as $candidateRow) {
            if (function_exists('customerLabelIsExcludedOrder') && customerLabelIsExcludedOrder($candidateRow)) {
                continue;
            }
            if ((int) (isset($candidateRow['id']) ? $candidateRow['id'] : 0) === $currentOrderId) {
                continue;
            }
            if (!customerFollowUpIsPreviousPurchaseRow($candidateRow, $orderRow, $sourceConfig)) {
                continue;
            }
            $resolvedCustomerId = function_exists('customerLabelResolveOrderCustomerId')
                ? customerLabelResolveOrderCustomerId($platform, $candidateRow, $customerIndexes)
                : 0;
            if ($resolvedCustomerId === $targetCustomerId) {
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('customerFollowUpBuildReceivedOrderContext')) {
    function customerFollowUpBuildReceivedOrderContext($connect, $financeConnect, $platform, $orderId, $orderRow = array())
    {
        $platform = customerFollowUpNormalizePlatform($platform);
        $orderId = (int) $orderId;
        if ($platform === '' || $orderId <= 0) {
            return array();
        }

        $sourceConfig = function_exists('shopeeOmsGetOrderSourceConfig') ? shopeeOmsGetOrderSourceConfig($platform) : array();
        if (empty($sourceConfig)) {
            return array();
        }

        $orderConnect = function_exists('shopeeOmsGetOrderSourceDbConnection')
            ? shopeeOmsGetOrderSourceDbConnection($connect, $financeConnect, $sourceConfig)
            : $connect;
        if (!($orderConnect instanceof mysqli)) {
            return array();
        }

        if (!is_array($orderRow) || empty($orderRow)) {
            $orderRow = function_exists('shopeeOmsLoadOrder') ? shopeeOmsLoadOrder($orderConnect, $orderId, $sourceConfig) : array();
        }
        if (empty($orderRow)) {
            return array();
        }

        $orderRow['__oms_platform'] = $platform;
        $existingFollowUpRow = customerFollowUpFetchActiveByOrderPlatform($connect, $platform, $orderId);
        $existingRoundRow = !empty($existingFollowUpRow) ? customerFollowUpFetchCurrentRound($connect, (int) $existingFollowUpRow['id']) : array();
        $customerRow = customerFollowUpResolveCustomerRowFromOrder($connect, $financeConnect, $platform, $orderRow);
        $customerId = customerFollowUpResolveCustomerIdFromOrder($platform, $orderRow, $customerRow);
        $customerUsername = customerFollowUpResolveCustomerUsername($connect, $financeConnect, $platform, $orderRow, $customerRow);
        $customerName = customerFollowUpResolveCustomerName($platform, $orderRow, $customerRow, $customerUsername);
        $packageSummaryData = function_exists('shopeeOmsBuildOrderProductSummaryBySource')
            ? shopeeOmsBuildOrderProductSummaryBySource($connect, $orderRow, $platform)
            : array();
        $packageName = is_array($packageSummaryData)
            ? trim((string) (isset($packageSummaryData['bundle_name']) && $packageSummaryData['bundle_name'] !== ''
                ? $packageSummaryData['bundle_name']
                : (isset($packageSummaryData['package_summary']) ? $packageSummaryData['package_summary'] : '')))
            : trim((string) $packageSummaryData);
        if ($packageName === '') {
            $packageField = isset($sourceConfig['package_field']) ? trim((string) $sourceConfig['package_field']) : '';
            $packageName = $packageField !== '' && isset($orderRow[$packageField]) ? trim((string) $orderRow[$packageField]) : '';
        }

        $purchaseCountSnapshot = !empty($existingFollowUpRow)
            ? (int) (isset($existingFollowUpRow['purchase_count_snapshot']) ? $existingFollowUpRow['purchase_count_snapshot'] : 0)
            : customerFollowUpCountPreviousPurchases($connect, $financeConnect, $platform, $sourceConfig, $orderRow, $customerRow);
        $customerType = !empty($existingFollowUpRow)
            ? strtolower(trim((string) (isset($existingFollowUpRow['customer_type']) ? $existingFollowUpRow['customer_type'] : 'new')))
            : ($purchaseCountSnapshot >= 1 ? 'return' : 'new');
        $currentRoundNo = !empty($existingFollowUpRow)
            ? max(1, (int) (isset($existingFollowUpRow['current_round_no']) ? $existingFollowUpRow['current_round_no'] : 1))
            : 1;
        $receivedDate = !empty($existingFollowUpRow) && !empty($existingFollowUpRow['received_date'])
            ? (string) $existingFollowUpRow['received_date']
            : customerFollowUpNowDate();
        $contactNo = customerFollowUpResolveContactNo($platform, $orderRow, $customerRow, $existingFollowUpRow, $existingRoundRow);
        $roundStatus = customerFollowUpNormalizeStatus(isset($existingRoundRow['round_status']) ? $existingRoundRow['round_status'] : '');
        $packageBrandData = customerFollowUpResolveOrderPackageAndBrandIds($connect, $platform, $orderRow);

        $maxDateInfo = customerFollowUpCalculateMaxAllowedNextFollowUpDate(
            array(
                'customer_type' => $customerType,
                'current_round_no' => $currentRoundNo,
                'received_date' => $receivedDate,
            ),
            array(
                'round_no' => $currentRoundNo,
                'previous_follow_up_date' => !empty($existingRoundRow['previous_follow_up_date']) ? $existingRoundRow['previous_follow_up_date'] : $receivedDate,
            ),
            customerFollowUpNowDate()
        );

        $blockMessage = '';
        if (in_array($roundStatus, array('Pending Approval', 'Approved', 'Postponed', 'Done'), true)) {
            $blockMessage = 'This order already has an active follow-up submission for round ' . $currentRoundNo . '.';
        }

        return array(
            'platform' => $platform,
            'order_id' => $orderId,
            'source_config' => $sourceConfig,
            'order_connect' => $orderConnect,
            'order_row' => $orderRow,
            'order_no' => function_exists('shopeeOmsGetOrderCodeValue') ? shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig) : (string) $orderId,
            'customer_row' => $customerRow,
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'customer_username' => $customerUsername !== '' ? $customerUsername : $customerName,
            'package_name' => $packageName,
            'package_ids' => isset($packageBrandData['package_ids']) ? $packageBrandData['package_ids'] : array(),
            'brand_ids' => isset($packageBrandData['brand_ids']) ? $packageBrandData['brand_ids'] : array(),
            'received_date' => $receivedDate,
            'purchase_count_snapshot' => $purchaseCountSnapshot,
            'customer_type' => $customerType === 'return' ? 'return' : 'new',
            'current_round_no' => $currentRoundNo,
            'contact_no' => $contactNo,
            'existing_follow_up' => $existingFollowUpRow,
            'existing_round' => $existingRoundRow,
            'round_status' => $roundStatus,
            'max_date' => isset($maxDateInfo['max_date']) ? (string) $maxDateInfo['max_date'] : '',
            'rule_label' => isset($maxDateInfo['rule_label']) ? (string) $maxDateInfo['rule_label'] : '',
            'block_message' => $blockMessage,
        );
    }
}

if (!function_exists('customerFollowUpSubmitReceivedOrderAndTransition')) {
    function customerFollowUpSubmitReceivedOrderAndTransition($connect, $financeConnect, $platform, $orderId, $formData, $fileData, $actorUserId, $actorUserGroupId, $options = array())
    {
        $context = customerFollowUpBuildReceivedOrderContext($connect, $financeConnect, $platform, $orderId);
        if (empty($context)) {
            return array('success' => false, 'message' => 'Order information for follow-up could not be loaded.');
        }

        if (trim((string) (isset($context['block_message']) ? $context['block_message'] : '')) !== '') {
            return array('success' => false, 'message' => (string) $context['block_message']);
        }

        $uploadResult = customerFollowUpStoreAttachmentUpload($fileData, $connect, array('png', 'jpg', 'jpeg', 'pdf', 'webp'), $context);
        if (empty($uploadResult['success'])) {
            return array('success' => false, 'message' => isset($uploadResult['message']) ? $uploadResult['message'] : 'Attachment is required.');
        }

        $messageShortcutId = (int) (isset($formData['message_shortcut_id']) ? $formData['message_shortcut_id'] : 0);
        $messageShortcutRow = customerFollowUpGetMessageShortcutById($connect, $messageShortcutId);
        if (empty($messageShortcutRow)) {
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => 'Message Shortcut is required.');
        }

        $nextFollowUpDate = trim((string) (isset($formData['next_follow_up_date']) ? $formData['next_follow_up_date'] : ''));
        $contactNo = trim((string) (isset($formData['contact_no']) ? $formData['contact_no'] : ''));
        if ($contactNo === '') {
            $contactNo = trim((string) (isset($context['contact_no']) ? $context['contact_no'] : ''));
        }

        $requiredErrors = customerFollowUpValidateRequiredFields(array(
            'attachment' => isset($uploadResult['path']) ? $uploadResult['path'] : '',
            'message_shortcut_id' => $messageShortcutId,
            'next_follow_up_date' => $nextFollowUpDate,
        ));
        if (!empty($requiredErrors)) {
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => implode(' ', $requiredErrors));
        }

        $dateValidation = customerFollowUpValidateNextFollowUpDateLimit(
            array(
                'customer_type' => isset($context['customer_type']) ? $context['customer_type'] : 'new',
                'current_round_no' => isset($context['current_round_no']) ? (int) $context['current_round_no'] : 1,
                'received_date' => isset($context['received_date']) ? $context['received_date'] : customerFollowUpNowDate(),
            ),
            array(
                'round_no' => isset($context['current_round_no']) ? (int) $context['current_round_no'] : 1,
                'previous_follow_up_date' => !empty($context['existing_round']['previous_follow_up_date']) ? $context['existing_round']['previous_follow_up_date'] : (isset($context['received_date']) ? $context['received_date'] : customerFollowUpNowDate()),
            ),
            $nextFollowUpDate
        );
        if (empty($dateValidation['success'])) {
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return $dateValidation;
        }

        $followUpId = !empty($context['existing_follow_up']['id']) ? (int) $context['existing_follow_up']['id'] : 0;
        $isNewCase = $followUpId <= 0;
        $transactionConnections = customerFollowUpGetUniqueConnections(array($connect, $financeConnect, isset($context['order_connect']) ? $context['order_connect'] : null));

        if (!customerFollowUpBeginTransactions($transactionConnections)) {
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => 'Unable to start follow-up transaction.');
        }

        $rollbackNeeded = true;
        $oldRoundState = array();
        $oldFollowUpState = array();

        if ($followUpId <= 0) {
            $followUpId = customerFollowUpStartFromReceivedOrder(
                $connect,
                isset($context['platform']) ? $context['platform'] : '',
                isset($context['order_id']) ? (int) $context['order_id'] : 0,
                isset($context['order_no']) ? $context['order_no'] : '',
                isset($context['customer_id']) ? (int) $context['customer_id'] : 0,
                isset($context['customer_username']) ? $context['customer_username'] : '',
                isset($context['package_name']) ? $context['package_name'] : '',
                isset($context['received_date']) ? $context['received_date'] : customerFollowUpNowDate(),
                isset($context['purchase_count_snapshot']) ? (int) $context['purchase_count_snapshot'] : 0,
                (int) $actorUserId,
                isset($context['customer_name']) ? $context['customer_name'] : '',
                $contactNo
            );
            if ($followUpId <= 0) {
                customerFollowUpRollbackTransactions($transactionConnections);
                customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
                return array('success' => false, 'message' => 'Unable to create follow-up case.');
            }
        }

        $followUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        if (empty($followUpRow)) {
            customerFollowUpRollbackTransactions($transactionConnections);
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => 'Follow-up case not found.');
        }

        $roundRow = customerFollowUpCreateOrLoadCurrentRound($connect, $followUpRow);
        if (empty($roundRow)) {
            customerFollowUpRollbackTransactions($transactionConnections);
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => 'Unable to prepare follow-up round.');
        }

        $currentRoundStatus = customerFollowUpNormalizeStatus(isset($roundRow['round_status']) ? $roundRow['round_status'] : '');
        if (in_array($currentRoundStatus, array('Pending Approval', 'Approved', 'Postponed', 'Done'), true)) {
            customerFollowUpRollbackTransactions($transactionConnections);
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => 'This order already has an active follow-up submission.');
        }

        $oldRoundState = $roundRow;
        $oldFollowUpState = $followUpRow;
        $isResubmit = strtolower(trim((string) (isset($roundRow['round_status']) ? $roundRow['round_status'] : ''))) === 'rejected';
        $customerType = strtolower(trim((string) (isset($followUpRow['customer_type']) ? $followUpRow['customer_type'] : 'new')));
        $approvalStatus = $customerType === 'return' ? 'not_required' : 'pending';
        $roundStatus = $customerType === 'return' ? 'Approved' : 'Pending Approval';

        $roundUpdateFields = array(
            'next_follow_up_date' => $nextFollowUpDate,
            'attachment' => isset($uploadResult['path']) ? $uploadResult['path'] : '',
            'message_shortcut_id' => $messageShortcutId,
            'message_shortcut_text' => isset($messageShortcutRow['shortcuts_message_text']) ? $messageShortcutRow['shortcuts_message_text'] : '',
            'contact_no' => $contactNo !== '' ? $contactNo : null,
            'approval_status' => $approvalStatus,
            'reject_reason' => null,
            'postpone_status' => 'none',
            'postpone_reason' => null,
            'postpone_reject_reason' => null,
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
            'round_status' => $roundStatus,
        );
        if (customerFollowUpRoundSupportsApprovalComment($connect)) {
            $roundUpdateFields['approval_comment'] = null;
        }

        $roundUpdated = customerFollowUpUpdateRoundRecord($connect, (int) $roundRow['id'], $roundUpdateFields);
        $caseUpdated = customerFollowUpUpdateCaseRecord($connect, $followUpId, array(
            'current_status' => $roundStatus,
            'contact_no' => $contactNo !== '' ? $contactNo : null,
            'follow_up_started' => 'Y',
            'update_by' => (string) $actorUserId,
            'update_date' => customerFollowUpNowDate(),
            'update_time' => customerFollowUpNowTime(),
        ));
        if (!$roundUpdated || !$caseUpdated) {
            customerFollowUpRollbackTransactions($transactionConnections);
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => 'Failed to save follow-up round.');
        }

        $transitionOptions = array(
            'actor_user_id' => $actorUserId,
            'actor_user_group_id' => $actorUserGroupId,
            'source_page' => isset($options['source_page']) ? $options['source_page'] : 'Customer Follow-Up',
            'remark' => isset($options['transition_remark']) && trim((string) $options['transition_remark']) !== ''
                ? $options['transition_remark']
                : shopeeOmsBuildParcelReceivedRemark($connect, $actorUserId, 'user'),
            'platform' => isset($context['platform']) ? $context['platform'] : $platform,
            'allow_auto_follow_up' => false,
        );
        $transitionResult = shopeeOmsExecuteTransition($connect, $financeConnect, (int) $orderId, 'PR', $transitionOptions);
        if (empty($transitionResult['success'])) {
            customerFollowUpRollbackTransactions($transactionConnections);
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array(
                'success' => false,
                'message' => isset($transitionResult['message']) ? (string) $transitionResult['message'] : 'Unable to confirm parcel received.',
            );
        }

        $updatedFollowUpRow = customerFollowUpReadFollowUpCase($connect, $followUpId);
        $updatedRoundRow = customerFollowUpFetchRoundById($connect, (int) $roundRow['id']);
        $actionType = $isResubmit ? 'resubmit_rejected_follow_up' : 'submit_follow_up';
        $actionLabel = $isResubmit ? 'Resubmitted rejected follow-up' : 'Submitted follow-up';
        $previousContactNo = trim((string) (isset($oldRoundState['contact_no']) && $oldRoundState['contact_no'] !== '' ? $oldRoundState['contact_no'] : (isset($oldFollowUpState['contact_no']) ? $oldFollowUpState['contact_no'] : '')));

        customerFollowUpCreateActionArtifacts(
            $connect,
            $updatedFollowUpRow,
            $updatedRoundRow,
            $actionType,
            $actionLabel,
            $oldRoundState,
            array(
                'next_follow_up_date' => $nextFollowUpDate,
                'contact_no' => $contactNo,
                'message_shortcut_id' => $messageShortcutId,
                'message_shortcut_label' => isset($messageShortcutRow['shortcuts_tag']) ? $messageShortcutRow['shortcuts_tag'] : '',
                'message_shortcut_text' => isset($messageShortcutRow['shortcuts_message_text']) ? $messageShortcutRow['shortcuts_message_text'] : '',
                'approval_status' => $approvalStatus,
                'round_status' => $roundStatus,
            ),
            '',
            isset($uploadResult['path']) ? $uploadResult['path'] : ''
        );

        if ($contactNo !== '' && $contactNo !== $previousContactNo) {
            customerFollowUpCreateActionArtifacts(
                $connect,
                $updatedFollowUpRow,
                $updatedRoundRow,
                'contact_number_edited',
                'Edited contact number',
                array('contact_no' => $previousContactNo),
                array('contact_no' => $contactNo)
            );
        }

        if ($customerType === 'new') {
            $adminUsers = customerFollowUpGetAdminUsers($connect);
            foreach ($adminUsers as $adminUser) {
                customerFollowUpCreateNotificationRow($connect, array(
                    'follow_up_id' => $followUpId,
                    'round_id' => isset($updatedRoundRow['id']) ? (int) $updatedRoundRow['id'] : 0,
                    'notify_user_id' => isset($adminUser['id']) ? (int) $adminUser['id'] : 0,
                    'notify_role' => 'admin',
                    'notification_type' => $actionType,
                    'title' => 'Follow-Up Pending Approval',
                    'message' => 'Follow-up round ' . (int) $updatedRoundRow['round_no'] . ' for order ' . trim((string) (isset($updatedFollowUpRow['order_no']) ? $updatedFollowUpRow['order_no'] : '')) . ' is pending approval.',
                ));
            }
        }

        if (!customerFollowUpCommitTransactions($transactionConnections)) {
            customerFollowUpRollbackTransactions($transactionConnections);
            customerFollowUpDeleteAttachmentFile(isset($uploadResult['path']) ? $uploadResult['path'] : '');
            return array('success' => false, 'message' => 'Follow-up submission was saved but commit failed.');
        }

        $rollbackNeeded = false;
        $orderRowAfter = function_exists('shopeeOmsLoadOrder')
            ? shopeeOmsLoadOrder(isset($context['order_connect']) ? $context['order_connect'] : null, (int) $orderId, isset($context['source_config']) ? $context['source_config'] : array())
            : array();

        return array(
            'success' => true,
            'message' => $customerType === 'return'
                ? 'Follow-up submitted and order moved to Parcel Received.'
                : 'Follow-up submitted for admin approval and order moved to Parcel Received.',
            'follow_up_id' => $followUpId,
            'follow_up_row' => $updatedFollowUpRow,
            'round_row' => $updatedRoundRow,
            'transition_result' => $transitionResult,
            'order_row_after' => $orderRowAfter,
            'source_config' => isset($context['source_config']) ? $context['source_config'] : array(),
            'order_no' => isset($context['order_no']) ? $context['order_no'] : '',
            'is_new_case' => $isNewCase,
        );
    }
}
