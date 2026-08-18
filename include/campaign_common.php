<?php

if (defined('ROOT')) {
    include_once ROOT . '/include/customer_tag.php';
}


if (!function_exists('campaignH')) {
    function campaignH($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('campaignJson')) {
    function campaignJson($value)
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

if (!function_exists('campaignCsrfToken')) {
    function campaignCsrfToken($key)
    {
        $key = 'campaign_csrf_' . preg_replace('/[^a-z0-9_]/i', '_', (string) $key);
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$key];
    }
}

if (!function_exists('campaignVerifyCsrf')) {
    function campaignVerifyCsrf($key, $token)
    {
        return hash_equals(campaignCsrfToken($key), (string) $token);
    }
}

if (!function_exists('campaignSetPopup')) {
    function campaignSetPopup($message, $returnUrl, $act = 'ErrMO')
    {
        $_SESSION['campaign_common_popup'] = array(
            'message' => (string) $message,
            'return_url' => (string) $returnUrl,
            'act' => (string) $act,
        );
    }
}

if (!function_exists('campaignRenderPopupScript')) {
    function campaignRenderPopupScript($pageTitle, $defaultReturnUrl)
    {
        if (empty($_SESSION['campaign_common_popup']) || !is_array($_SESSION['campaign_common_popup'])) {
            return;
        }

        $popup = $_SESSION['campaign_common_popup'];
        unset($_SESSION['campaign_common_popup']);

        $message = isset($popup['message']) ? (string) $popup['message'] : '';
        $returnUrl = isset($popup['return_url']) && $popup['return_url'] !== '' ? (string) $popup['return_url'] : (string) $defaultReturnUrl;
        $act = isset($popup['act']) && $popup['act'] !== '' ? (string) $popup['act'] : 'ErrMO';

        echo '<script>confirmationDialog("", ' . campaignJson($message) . ', ' . campaignJson($pageTitle) . ', "", ' . campaignJson($returnUrl) . ', ' . campaignJson($act) . ');</script>';
    }
}

if (!function_exists('campaignResolveBackUrl')) {
    function campaignResolveBackUrl($fallbackUrl)
    {
        $fallbackUrl = trim((string) $fallbackUrl);
        $requestedBackUrl = '';

        if (isset($_REQUEST['back'])) {
            $requestedBackUrl = trim((string) $_REQUEST['back']);
        } else if (isset($_REQUEST['back_url'])) {
            $requestedBackUrl = trim((string) $_REQUEST['back_url']);
        }

        if ($requestedBackUrl !== '') {
            if (function_exists('commonSafeBackUrl')) {
                return commonSafeBackUrl($requestedBackUrl, $fallbackUrl);
            }

            return $requestedBackUrl;
        }

        if (function_exists('commonResolveBackUrl')) {
            return commonResolveBackUrl($fallbackUrl);
        }

        return $fallbackUrl;
    }
}

if (!function_exists('campaignBuildUrl')) {
    function campaignBuildUrl($baseUrl, $params = array())
    {
        $baseUrl = trim((string) $baseUrl);
        $queryParams = array();

        foreach ((array) $params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $queryParams[(string) $key] = (string) $value;
        }

        if (empty($queryParams)) {
            return $baseUrl;
        }

        return $baseUrl . (strpos($baseUrl, '?') === false ? '?' : '&') . http_build_query($queryParams);
    }
}

if (!function_exists('campaignAudit')) {
    function campaignAudit($connect, $pageTitle, $action, $message, $query = '', $table = '')
    {
        audit_log(array(
            'log_act' => $action,
            'cdate' => date_dis,
            'ctime' => time_dis,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'act_msg' => $message,
            'query_rec' => $query,
            'query_table' => $table !== '' ? $table : CAMPAIGN,
            'page' => $pageTitle,
            'connect' => $connect,
        ));
    }
}

if (!function_exists('campaignTableExists')) {
    function campaignTableExists($conn, $tblName)
    {
        if (!($conn instanceof mysqli) || trim((string) $tblName) === '') {
            return false;
        }

        $safeTable = $conn->real_escape_string((string) $tblName);
        $result = $conn->query("SHOW TABLES LIKE '" . $safeTable . "'");
        return ($result && $result->num_rows > 0);
    }
}

if (!function_exists('campaignColumnExists')) {
    function campaignColumnExists($conn, $tblName, $columnName)
    {
        if (!campaignTableExists($conn, $tblName)) {
            return false;
        }

        $safeColumn = $conn->real_escape_string((string) $columnName);
        $result = $conn->query("SHOW COLUMNS FROM `" . str_replace('`', '``', (string) $tblName) . "` LIKE '" . $safeColumn . "'");
        return ($result && $result->num_rows > 0);
    }
}

if (!function_exists('campaignFirstColumn')) {
    function campaignFirstColumn($conn, $tblName, $columns)
    {
        foreach ((array) $columns as $column) {
            if (campaignColumnExists($conn, $tblName, $column)) {
                return $column;
            }
        }

        return '';
    }
}

if (!function_exists('campaignFetchCampaign')) {
    function campaignFetchCampaign($connect, $campaignId)
    {
        $campaignId = (int) $campaignId;
        if ($campaignId <= 0) {
            return array();
        }

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
}

if (!function_exists('campaignRenderBadge')) {
    function campaignRenderBadge($campaign)
    {
        $name = isset($campaign['campaign_name']) ? $campaign['campaign_name'] : '';
        $periodStart = campaignDateValue($campaign['period_start_date'] ?? '');
        $periodEnd = campaignDateValue($campaign['period_end_date'] ?? '');
        $parts = array_filter(array($name, trim($periodStart . ($periodEnd !== '' ? ' - ' . $periodEnd : ''))), 'strlen');
        echo '<div class="campaign-title-badge-wrap"><span class="badge bg-secondary campaign-title-badge">' . campaignH(implode(' | ', $parts)) . '</span></div>';
    }
}

if (!function_exists('campaignBackButtonJs')) {
    function campaignBackButtonJs($fallbackUrl = '', $preferHistory = true)
    {
        global $SITEURL;

        $fallbackUrl = trim((string) $fallbackUrl);
        if ($fallbackUrl === '') {
            $fallbackUrl = isset($_SERVER['HTTP_REFERER']) && trim((string) $_SERVER['HTTP_REFERER']) !== ''
                ? trim((string) $_SERVER['HTTP_REFERER'])
                : ($SITEURL . '/campaign/campaign_table.php');
        }

        if (!$preferHistory) {
            return 'window.location.href=' . json_encode($fallbackUrl) . ';';
        }

        return 'if(window.history.length > 1){window.history.back();}else{window.location.href=' . json_encode($fallbackUrl) . ';}';
    }
}

if (!function_exists('campaignRenderBackButton')) {
    function campaignRenderBackButton($fallbackUrl = '', $preferHistory = true)
    {
        echo '<div class="campaign-back-action-row mobile-sticky-form-actions-target d-flex justify-content-center flex-wrap mt-4">'
            . '<button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="button" name="actionBtn" id="backBtn" value="back" onclick="' . campaignH(campaignBackButtonJs($fallbackUrl, $preferHistory)) . '">Back</button>'
            . '</div>';
    }
}

if (!function_exists('campaignFetchUsers')) {
    function campaignFetchUsers($connect)
    {
        $rows = array();
        $result = getData('*', '', '', USR_USER, $connect);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = trim((string) (isset($row['name']) && $row['name'] !== '' ? $row['name'] : (isset($row['username']) ? $row['username'] : $id)));
                if ($id > 0) {
                    $rows[] = array('id' => $id, 'name' => $name);
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('campaignFetchSimpleOptions')) {
    function campaignFetchSimpleOptions($connect, $tblName, $nameColumn = 'name')
    {
        $rows = array();
        if (!campaignTableExists($connect, $tblName) || !campaignColumnExists($connect, $tblName, $nameColumn)) {
            return $rows;
        }

        $result = getData('id,`' . $nameColumn . '`', '', '', $tblName, $connect);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = isset($row[$nameColumn]) ? trim((string) $row[$nameColumn]) : '';
                if ($id > 0 && $name !== '') {
                    $rows[] = array('id' => $id, 'name' => $name);
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('campaignResolveLookupName')) {
    function campaignResolveLookupName($connect, $tblName, $id)
    {
        $id = trim((string) $id);
        if ($id === '' || $id === '0' || !campaignTableExists($connect, $tblName) || !campaignColumnExists($connect, $tblName, 'name')) {
            return $id;
        }

        $safeId = $connect->real_escape_string($id);
        $result = getData('name', "id='" . $safeId . "'", 'LIMIT 1', $tblName, $connect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return isset($row['name']) ? (string) $row['name'] : $id;
        }

        return $id;
    }
}

if (!function_exists('campaignRenderAutocompleteScript')) {
    function campaignRenderAutocompleteScript($configs)
    {
        echo '<script>';
        echo 'window.campaignAutocompleteConfigs = ' . campaignJson(array_values($configs)) . ';';
        ?>
        (function () {
            function normalizeText(value) {
                return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
            }

            function getWrapper(input) {
                if (!input) return null;
                return input.closest('.autocomplete') || input.parentElement || null;
            }

            function closeList(input) {
                var listId = input.getAttribute('data-list-id');
                if (!listId) return;
                var list = document.getElementById(listId);
                if (list) list.remove();
            }

            function findOption(options, id) {
                id = String(id || '');
                return (options || []).find(function (option) {
                    return String(option.id) === id;
                }) || null;
            }

            function applyOption(input, hidden, option) {
                if (!option) return;
                input.value = option.name;
                if (hidden) hidden.value = String(option.id);
                input.dispatchEvent(new Event('change', { bubbles: true }));
                if (hidden) hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function renderList(input, hidden, options) {
                closeList(input);
                if (!input || input.hasAttribute('readonly') || input.hasAttribute('disabled')) return;

                var keyword = normalizeText(input.value);
                if (!keyword) return;
                var filtered = (options || []).filter(function (option) {
                    return normalizeText(option.name).indexOf(keyword) !== -1;
                }).slice(0, 25);

                if (!filtered.length) return;

                var listId = 'searchResult_' + input.id;
                input.setAttribute('data-list-id', listId);
                var wrapper = getWrapper(input);
                if (!wrapper) return;

                var ul = document.createElement('ul');
                ul.className = 'searchResult';
                ul.id = listId;

                filtered.forEach(function (option) {
                    var li = document.createElement('li');
                    li.textContent = option.name;
                    li.setAttribute('value', option.id);
                    li.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        applyOption(input, hidden, option);
                        closeList(input);
                    });
                    ul.appendChild(li);
                });

                wrapper.appendChild(ul);
            }

            (window.campaignAutocompleteConfigs || []).forEach(function (config) {
                var input = document.getElementById(config.inputId);
                var hidden = document.getElementById(config.hiddenId);
                var options = config.options || [];
                if (!input) return;

                input.addEventListener('input', function () {
                    if (hidden) hidden.value = '';
                    renderList(input, hidden, options);
                });

                input.addEventListener('blur', function () {
                    setTimeout(function () {
                        var keyword = normalizeText(input.value);
                        var matched = options.find(function (option) {
                            return normalizeText(option.name) === keyword;
                        });
                        if (hidden) hidden.value = matched ? String(matched.id) : '';
                        closeList(input);
                    }, 120);
                });
            });
        })();
        <?php
        echo '</script>';
    }
}

if (!function_exists('campaignRenderMultiAutocompleteScript')) {
    function campaignRenderMultiAutocompleteScript($inputId, $hiddenName, $selectedContainerId, $options, $selectedIds)
    {
        ?>
        <script>
            (function () {
                var options = <?= campaignJson(array_values($options)) ?>;
                var selected = <?= campaignJson(array_values(array_map('intval', (array) $selectedIds))) ?>;
                var input = document.getElementById(<?= campaignJson($inputId) ?>);
                var container = document.getElementById(<?= campaignJson($selectedContainerId) ?>);
                var hiddenName = <?= campaignJson($hiddenName) ?>;

                function normalizeText(value) {
                    return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
                }

                function getWrapper() {
                    if (!input) return null;
                    return input.closest('.autocomplete') || input.parentElement || null;
                }

                function closeList() {
                    if (!input) return;
                    var list = document.getElementById('searchResult_' + input.id);
                    if (list) list.remove();
                }

                function optionById(id) {
                    id = String(id || '');
                    return options.find(function (option) {
                        return String(option.id) === id;
                    }) || null;
                }

                function renderSelected() {
                    if (!container) return;
                    container.innerHTML = '';

                    selected.forEach(function (id) {
                        var option = optionById(id);
                        if (!option) return;

                        var badge = document.createElement('span');
                        badge.className = 'badge bg-secondary me-1 mb-1';
                        badge.textContent = option.name + ' ';

                        var remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'btn btn-sm btn-link text-white p-0 ms-1';
                        remove.textContent = 'x';
                        remove.addEventListener('click', function () {
                            selected = selected.filter(function (value) {
                                return String(value) !== String(id);
                            });
                            renderSelected();
                        });

                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = hiddenName;
                        hidden.value = id;

                        badge.appendChild(remove);
                        container.appendChild(badge);
                        container.appendChild(hidden);
                    });
                }

                function addOption(option) {
                    if (!option) return;
                    if (selected.map(String).indexOf(String(option.id)) === -1) {
                        selected.push(parseInt(option.id, 10));
                    }
                    input.value = '';
                    closeList();
                    renderSelected();
                }

                function renderList() {
                    closeList();
                    if (!input || input.hasAttribute('readonly') || input.hasAttribute('disabled')) return;

                    var keyword = normalizeText(input.value);
                    if (!keyword) return;
                    var filtered = options.filter(function (option) {
                        return selected.map(String).indexOf(String(option.id)) === -1
                            && normalizeText(option.name).indexOf(keyword) !== -1;
                    }).slice(0, 25);

                    if (!filtered.length) return;

                    var ul = document.createElement('ul');
                    ul.className = 'searchResult';
                    ul.id = 'searchResult_' + input.id;
                    var wrapper = getWrapper();
                    if (!wrapper) return;

                    filtered.forEach(function (option) {
                        var li = document.createElement('li');
                        li.textContent = option.name;
                        li.setAttribute('value', option.id);
                        li.addEventListener('mousedown', function (event) {
                            event.preventDefault();
                            addOption(option);
                        });
                        ul.appendChild(li);
                    });

                    wrapper.appendChild(ul);
                }

                if (input) {
                    input.addEventListener('input', renderList);
                    input.addEventListener('blur', function () {
                        setTimeout(closeList, 120);
                    });
                }

                renderSelected();
            })();
        </script>
        <?php
    }
}

if (!function_exists('campaignNormalizeTextValue')) {
    function campaignNormalizeTextValue($value, $maxBytes = 65535)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('//u', $value) && function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($normalized !== false) {
                $value = $normalized;
            }
        }

        $maxBytes = (int) $maxBytes;
        if ($maxBytes > 0) {
            if (function_exists('mb_strcut')) {
                $value = mb_strcut($value, 0, $maxBytes, 'UTF-8');
            } else {
                $value = substr($value, 0, $maxBytes);
            }
        }

        return trim((string) $value);
    }
}

if (!function_exists('campaignCurrentUserId')) {
    function campaignCurrentUserId()
    {
        if (defined('USER_ID') && trim((string) USER_ID) !== '') {
            return (string) USER_ID;
        }

        return '0';
    }
}

if (!function_exists('campaignTableName')) {
    function campaignTableName($tblName)
    {
        return '`' . str_replace('`', '``', (string) $tblName) . '`';
    }
}

if (!function_exists('campaignFetchMessageShortcutOptions')) {
    function campaignFetchMessageShortcutOptions($connect)
    {
        $rows = array();
        if (!campaignTableExists($connect, MESSAGE_SHORTCUTS)) {
            return $rows;
        }

        $nameColumn = campaignFirstColumn($connect, MESSAGE_SHORTCUTS, array('shortcuts_tag', 'name', 'title'));
        $messageColumn = campaignFirstColumn($connect, MESSAGE_SHORTCUTS, array('shortcuts_message', 'message', 'description'));
        if ($nameColumn === '') {
            return $rows;
        }

        $selectSql = '`id`, `' . $nameColumn . '` AS shortcut_name';
        if ($messageColumn !== '') {
            $selectSql .= ', `' . $messageColumn . '` AS shortcut_message';
        }

        $result = getData($selectSql, '', '', MESSAGE_SHORTCUTS, $connect);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = campaignNormalizeTextValue($row['shortcut_name'] ?? '', 255);
                if ($id > 0 && $name !== '') {
                    $rows[] = array(
                        'id' => $id,
                        'name' => $name,
                        'preview' => campaignNormalizeTextValue(strip_tags((string) ($row['shortcut_message'] ?? '')), 65535),
                    );
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('campaignLookupShortcutById')) {
    function campaignLookupShortcutById($shortcutOptions, $shortcutId)
    {
        $shortcutId = (int) $shortcutId;
        foreach ((array) $shortcutOptions as $shortcutOption) {
            if ((int) ($shortcutOption['id'] ?? 0) === $shortcutId) {
                return $shortcutOption;
            }
        }

        return array();
    }
}

if (!function_exists('campaignDateValue')) {
    function campaignDateValue($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        $time = strtotime($value);
        return $time === false ? '' : date('Y-m-d', $time);
    }
}

if (!function_exists('campaignBuildCampaignCode')) {
    function campaignBuildCampaignCode($connect, $prefix = 'CMP')
    {
        if (!campaignColumnExists($connect, CAMPAIGN, 'campaign_code')) {
            return '';
        }

        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $prefix));
        if ($prefix === '') {
            $prefix = 'CMP';
        }

        for ($i = 0; $i < 50; $i++) {
            $code = $prefix . date('ymdHis') . ($i > 0 ? sprintf('%02d', $i) : '');
            $safeCode = $connect->real_escape_string($code);
            $result = mysqli_query($connect, "SELECT `id` FROM " . campaignTableName(CAMPAIGN) . " WHERE `campaign_code`='" . $safeCode . "' LIMIT 1");
            if (!$result || $result->num_rows === 0) {
                return $code;
            }
        }

        return $prefix . date('ymdHis') . mt_rand(100, 999);
    }
}

if (!function_exists('campaignGetFirstExistingColumn')) {
    function campaignGetFirstExistingColumn($conn, $tblName, $columns)
    {
        return campaignFirstColumn($conn, $tblName, (array) $columns);
    }
}

if (!function_exists('campaignOrderViewBaseUrl')) {
    function campaignOrderViewBaseUrl($platform)
    {
        $platformKey = strtolower(trim((string) $platform));
        if ($platformKey === 'shopee') {
            return 'shopee/shopee_order_req.php';
        }
        if ($platformKey === 'lazada') {
            return 'lazada_order_req.php';
        }
        if ($platformKey === 'website') {
            return 'website_order_request.php';
        }
        if ($platformKey === 'facebook') {
            return 'fb_order_req.php';
        }

        return '';
    }
}

if (!function_exists('campaignBuildOrderViewUrl')) {
    function campaignBuildOrderViewUrl($siteUrl, $platform, $orderId)
    {
        $base = campaignOrderViewBaseUrl($platform);
        $orderId = trim((string) $orderId);
        if ($base === '' || $orderId === '') {
            return '';
        }

        return rtrim((string) $siteUrl, '/') . '/' . $base . '?id=' . rawurlencode($orderId);
    }
}

if (!function_exists('campaignPurchasePlatformConfigs')) {
    function campaignPurchasePlatformConfigs($connect, $financeConnect)
    {
        return array(
            'Shopee' => array(
                'conn' => $financeConnect,
                'table' => defined('SHOPEE_SG_ORDER_REQ') ? SHOPEE_SG_ORDER_REQ : 'shopee_sg_order_request',
                'order_no_cols' => array('orderID', 'order_id', 'order_no'),
                // Shopee order table normally stores buyer as shopee_customer_info.id, so customer_lookup is required.
                'customer_cols' => array('buyer_username', 'buyer', 'customer_id', 'cust_id', 'customer_name'),
                // name/customer_name are free-text and not unique per person, but for many
                // manually-assigned campaign customers a name may be the only identifier on
                // file, so it stays in the lookup - campaignPurchaseLookupCustomerIds() caps
                // and discards any match that resolves to more than a handful of accounts,
                // which is what actually guards against the fan-out (see that function).
                'customer_lookup' => array(
                    'conn' => $financeConnect,
                    'table' => defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info',
                    'id_col' => 'id',
                    'match_cols' => array('buyer_username', 'contact_no', 'name', 'customer_name'),
                ),
                'date_cols' => array('date', 'order_date', 'create_date'),
                'time_cols' => array('time', 'create_time'),
                'amount_cols' => array('final_amt', 'price', 'total', 'amount'),
                'order_status_cols' => array('order_status'),
                'row_status_cols' => array('status'),
                'package_cols' => array('package', 'pkg'),
                'detail_cols' => array('package', 'remark'),
            ),
            'Lazada' => array(
                'conn' => $financeConnect,
                'table' => defined('LAZADA_ORDER_REQ') ? LAZADA_ORDER_REQ : 'lazada_order_request',
                'order_no_cols' => array('orderID', 'order_id', 'order_no', 'lazada_order_id'),
                'customer_cols' => array('buyer_username', 'buyer', 'customer_id', 'cust_id', 'name', 'customer_name', 'phone', 'contact'),
                'date_cols' => array('date', 'order_date', 'create_date'),
                'time_cols' => array('time', 'create_time'),
                'amount_cols' => array('final_amt', 'total', 'price', 'amount'),
                'order_status_cols' => array('order_status'),
                'row_status_cols' => array('status'),
                'package_cols' => array('package', 'pkg'),
                'detail_cols' => array('package', 'remark'),
            ),
            'Website' => array(
                'conn' => $financeConnect,
                'table' => defined('WEB_ORDER_REQ') ? WEB_ORDER_REQ : 'website_order_request',
                'order_no_cols' => array('order_id', 'orderID', 'order_no'),
                'customer_cols' => array('cust_id', 'cust_name', 'customer_id', 'name', 'phone', 'contact'),
                'date_cols' => array('date', 'order_date', 'create_date'),
                'time_cols' => array('time', 'create_time'),
                'amount_cols' => array('total', 'final_amt', 'price', 'amount'),
                'order_status_cols' => array('order_status'),
                'row_status_cols' => array('status'),
                'package_cols' => array('pkg', 'package'),
                'detail_cols' => array('pkg', 'package', 'remark'),
            ),
            'Facebook' => array(
                'conn' => $financeConnect,
                'table' => defined('FB_ORDER_REQ') ? FB_ORDER_REQ : 'facebook_order_request',
                'order_no_cols' => array('order_id', 'orderID', 'id'),
                'customer_cols' => array('name', 'contact', 'phone', 'customer_id', 'cust_id'),
                'date_cols' => array('date', 'order_date', 'create_date'),
                'time_cols' => array('time', 'create_time'),
                'amount_cols' => array('final_amt', 'price', 'total', 'amount'),
                'order_status_cols' => array('order_status'),
                'row_status_cols' => array('status'),
                'package_cols' => array('package', 'pkg'),
                'detail_cols' => array('package', 'remark'),
            ),
        );
    }
}

if (!function_exists('campaignPurchaseResolveConfig')) {
    function campaignPurchaseResolveConfig($connect, $financeConnect, $platform)
    {
        $configs = campaignPurchasePlatformConfigs($connect, $financeConnect);
        $platformKey = ucwords(strtolower(trim((string) $platform)));
        if (isset($configs[$platformKey])) {
            return $configs[$platformKey];
        }

        return array();
    }
}

if (!function_exists('campaignPurchaseReadCell')) {
    function campaignPurchaseReadCell($row, $column)
    {
        if ($column === '' || !is_array($row)) {
            return '';
        }

        return isset($row[$column]) ? (string) $row[$column] : '';
    }
}

if (!function_exists('campaignPurchaseQuoteColumn')) {
    function campaignPurchaseQuoteColumn($column)
    {
        return '`' . str_replace('`', '``', (string) $column) . '`';
    }
}

if (!function_exists('campaignPurchaseColumnType')) {
    function campaignPurchaseColumnType($conn, $tblName, $columnName)
    {
        static $cache = array();

        if (!($conn instanceof mysqli) || trim((string) $tblName) === '' || trim((string) $columnName) === '') {
            return '';
        }

        $cacheKey = spl_object_hash($conn) . '|' . $tblName . '|' . $columnName;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $safeTable = str_replace('`', '``', (string) $tblName);
        $safeColumn = $conn->real_escape_string((string) $columnName);
        $result = $conn->query("SHOW COLUMNS FROM `" . $safeTable . "` LIKE '" . $safeColumn . "'");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $cache[$cacheKey] = strtolower((string) ($row['Type'] ?? ''));
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = '';
        return '';
    }
}

if (!function_exists('campaignPurchaseColumnIsNumeric')) {
    function campaignPurchaseColumnIsNumeric($conn, $tblName, $columnName)
    {
        $columnType = campaignPurchaseColumnType($conn, $tblName, $columnName);
        return (bool) preg_match('/\b(int|decimal|float|double|real|numeric|bit)\b/i', $columnType);
    }
}

if (!function_exists('campaignPurchaseCleanMatchValues')) {
    function campaignPurchaseCleanMatchValues($values)
    {
        $clean = array();
        foreach ((array) $values as $value) {
            $value = trim(preg_replace('/\s+/', ' ', (string) $value));
            if ($value === '') {
                continue;
            }
            $clean[$value] = $value;
        }

        return array_values($clean);
    }
}

if (!function_exists('campaignPurchaseBaseCustomerMatchValues')) {
    function campaignPurchaseBaseCustomerMatchValues($campaignCustomer)
    {
        $values = array(
            $campaignCustomer['customer_id'] ?? '',
            $campaignCustomer['customer_name'] ?? '',
            $campaignCustomer['customer_contact'] ?? '',
            $campaignCustomer['customer_label'] ?? '',
        );

        return campaignPurchaseCleanMatchValues($values);
    }
}

if (!function_exists('campaignPurchaseLookupCustomerIds')) {
    function campaignPurchaseLookupCustomerIds($lookupConfig, $matchValues)
    {
        $ids = array();
        if (empty($lookupConfig) || empty($matchValues)) {
            return $ids;
        }

        $lookupConn = isset($lookupConfig['conn']) ? $lookupConfig['conn'] : null;
        $lookupTable = isset($lookupConfig['table']) ? trim((string) $lookupConfig['table']) : '';
        $lookupIdCol = isset($lookupConfig['id_col']) ? trim((string) $lookupConfig['id_col']) : 'id';
        if (!($lookupConn instanceof mysqli) || !campaignTableExists($lookupConn, $lookupTable) || !campaignColumnExists($lookupConn, $lookupTable, $lookupIdCol)) {
            return $ids;
        }

        $lookupMatchCols = array();
        foreach ((array) ($lookupConfig['match_cols'] ?? array()) as $matchCol) {
            if (campaignColumnExists($lookupConn, $lookupTable, $matchCol)) {
                $lookupMatchCols[] = $matchCol;
            }
        }

        if (empty($lookupMatchCols)) {
            return $ids;
        }

        $whereParts = array();
        foreach ($lookupMatchCols as $matchCol) {
            foreach ($matchValues as $matchValue) {
                $whereParts[] = campaignPurchaseQuoteColumn($matchCol) . "='" . $lookupConn->real_escape_string((string) $matchValue) . "'";
            }
        }

        if (empty($whereParts)) {
            return $ids;
        }

        $statusSql = campaignColumnExists($lookupConn, $lookupTable, 'status') ? " AND IFNULL(`status`, 'A') <> 'D'" : '';
        $sql = "SELECT " . campaignPurchaseQuoteColumn($lookupIdCol) . " AS lookup_id FROM " . campaignTableName($lookupTable) . " WHERE (" . implode(' OR ', $whereParts) . ")" . $statusSql . " LIMIT 200";
        $result = mysqli_query($lookupConn, $sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $lookupId = trim((string) ($row['lookup_id'] ?? ''));
                if ($lookupId !== '') {
                    $ids[$lookupId] = $lookupId;
                }
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('campaignPurchaseBuildCustomerMatchParts')) {
    function campaignPurchaseBuildCustomerMatchParts($orderConn, $orderTable, $config, $campaignCustomer)
    {
        $matchParts = array();
        $baseValues = campaignPurchaseBaseCustomerMatchValues($campaignCustomer);
        if (empty($baseValues)) {
            return $matchParts;
        }

        $lookupIds = campaignPurchaseLookupCustomerIds(isset($config['customer_lookup']) ? $config['customer_lookup'] : array(), $baseValues);
        foreach ($baseValues as $baseValue) {
            if (ctype_digit((string) $baseValue)) {
                $lookupIds[$baseValue] = $baseValue;
            }
        }
        $lookupIds = campaignPurchaseCleanMatchValues($lookupIds);

        $customerCols = array();
        foreach ((array) ($config['customer_cols'] ?? array()) as $candidateCol) {
            if (campaignColumnExists($orderConn, $orderTable, $candidateCol)) {
                $customerCols[] = $candidateCol;
            }
        }

        foreach ($customerCols as $customerCol) {
            $columnValues = $baseValues;
            $isNumericColumn = campaignPurchaseColumnIsNumeric($orderConn, $orderTable, $customerCol);

            if ($isNumericColumn) {
                $columnValues = $lookupIds;
            } else if (!empty($lookupIds) && in_array($customerCol, array('buyer', 'customer_id', 'cust_id'), true)) {
                $columnValues = array_merge($columnValues, $lookupIds);
            }

            $columnValues = campaignPurchaseCleanMatchValues($columnValues);
            foreach ($columnValues as $matchValue) {
                if ($isNumericColumn && !is_numeric($matchValue)) {
                    continue;
                }
                $matchParts[] = campaignPurchaseQuoteColumn($customerCol) . "='" . $orderConn->real_escape_string((string) $matchValue) . "'";
            }
        }

        return array_values(array_unique($matchParts));
    }
}

if (!function_exists('campaignPurchaseRowStatusWhere')) {
    function campaignPurchaseRowStatusWhere($conn, $table, $config)
    {
        $conditions = array();

        $rowStatusCol = campaignGetFirstExistingColumn($conn, $table, isset($config['row_status_cols']) ? $config['row_status_cols'] : array('status'));
        if ($rowStatusCol !== '') {
            $conditions[] = "IFNULL(" . campaignPurchaseQuoteColumn($rowStatusCol) . ", 'A') <> 'D'";
        }

        // Returned / closed-returned orders (order_status 'R'/'CR', shared across all
        // platform order tables via shopeeOmsStatusDefinitions) never count as a valid
        // campaign purchase or as proof of a prior purchase for repeat-customer detection.
        $orderStatusCol = campaignGetFirstExistingColumn($conn, $table, isset($config['order_status_cols']) ? $config['order_status_cols'] : array());
        if ($orderStatusCol !== '') {
            $conditions[] = "UPPER(TRIM(IFNULL(" . campaignPurchaseQuoteColumn($orderStatusCol) . ", ''))) NOT IN ('R', 'CR')";
        }

        return !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
    }
}

if (!function_exists('campaignPurchaseResolvePackageDisplayName')) {
    function campaignPurchaseResolvePackageDisplayName($connect, $packageValue)
    {
        $packageValue = trim((string) $packageValue);

        if ($packageValue === '') {
            return '';
        }

        $packageIds = array_filter(array_map('trim', explode(',', $packageValue)), function ($value) {
            return $value !== '';
        });

        $allPackageIdsAreNumeric = !empty($packageIds);
        foreach ($packageIds as $packageId) {
            if (!ctype_digit((string) $packageId)) {
                $allPackageIdsAreNumeric = false;
                break;
            }
        }

        if (!$allPackageIdsAreNumeric) {
            return $packageValue;
        }

        if (function_exists('commonResolvePackageNamesFromCsv')) {
            $resolvedName = commonResolvePackageNamesFromCsv($packageValue, $connect);
            if (trim((string) $resolvedName) !== '') {
                return $resolvedName;
            }
        }

        if (!defined('PKG') || !campaignTableExists($connect, PKG)) {
            return $packageValue;
        }

        $safePackageIds = array();
        foreach ($packageIds as $packageId) {
            $safePackageIds[] = (int) $packageId;
        }

        if (empty($safePackageIds)) {
            return $packageValue;
        }

        $packageNameMap = array();
        $packageSql = "SELECT `id`, `name` FROM " . campaignTableName(PKG) . " WHERE `id` IN (" . implode(',', $safePackageIds) . ")";
        $packageResult = mysqli_query($connect, $packageSql);

        if ($packageResult) {
            while ($packageRow = $packageResult->fetch_assoc()) {
                $packageNameMap[(int) ($packageRow['id'] ?? 0)] = trim((string) ($packageRow['name'] ?? ''));
            }
        }

        $packageNames = array();
        foreach ($safePackageIds as $packageId) {
            if (isset($packageNameMap[$packageId]) && $packageNameMap[$packageId] !== '') {
                $packageNames[] = $packageNameMap[$packageId];
            }
        }

        return !empty($packageNames) ? implode(', ', $packageNames) : $packageValue;
    }
}

if (!function_exists('campaignPurchaseExtractPackageIds')) {
    function campaignPurchaseExtractPackageIds($packageValue, $connect = null)
    {
        $packageValue = trim((string) $packageValue);
        if ($packageValue === '') {
            return array();
        }

        $ids = array();
        foreach (explode(',', $packageValue) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // If it's already a numeric ID, use it directly
            if (ctype_digit($part)) {
                $ids[(int) $part] = (int) $part;
            } elseif ($connect !== null && defined('PKG') && campaignTableExists($connect, PKG)) {
                // If it's a package name, try to look it up in the database
                $safePart = $connect->real_escape_string($part);
                $sql = "SELECT `id` FROM `" . PKG . "` WHERE `name`='" . $safePart . "' LIMIT 1";
                error_log("DEBUG campaignPurchaseExtractPackageIds: looking up part='" . $part . "' sql=" . $sql);
                $result = $connect->query($sql);
                error_log("DEBUG campaignPurchaseExtractPackageIds: query result: " . ($result ? "got result, num_rows=" . $result->num_rows : "failed"));
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $packageId = (int) ($row['id'] ?? 0);
                    error_log("DEBUG campaignPurchaseExtractPackageIds: found packageId=" . $packageId);
                    if ($packageId > 0) {
                        $ids[$packageId] = $packageId;
                    }
                } else {
                    error_log("DEBUG campaignPurchaseExtractPackageIds: NO MATCH for part='" . $part . "'");
                }
            } else {
                error_log("DEBUG campaignPurchaseExtractPackageIds: no connect or PKG not defined or table doesn't exist. connect=" . ($connect ? "yes" : "null") . " PKG=" . (defined('PKG') ? PKG : "undefined") . " exists=" . (defined('PKG') && $connect && campaignTableExists($connect, PKG) ? "yes" : "no"));
            }
        }

        error_log("DEBUG campaignPurchaseExtractPackageIds: final result for '" . $packageValue . "' = " . json_encode(array_values($ids)));
        return array_values($ids);
    }
}

if (!function_exists('campaignFetchCampaignPackageIds')) {
    function campaignFetchCampaignPackageIds($connect, $campaignId)
    {
        $packageIds = array();
        if (!defined('CAMPAIGN_PACKAGE') || !campaignTableExists($connect, CAMPAIGN_PACKAGE)) {
            return $packageIds;
        }

        $stmt = $connect->prepare("SELECT `package_id` FROM `" . CAMPAIGN_PACKAGE . "` WHERE `campaign_id` = ? AND `status` = 'A' ORDER BY `id` ASC");
        if (!$stmt) {
            return $packageIds;
        }

        $campaignId = (int) $campaignId;
        $stmt->bind_param('i', $campaignId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $packageId = (int) ($row['package_id'] ?? 0);
            if ($packageId > 0) {
                $packageIds[$packageId] = $packageId;
            }
        }
        $stmt->close();

        return array_values($packageIds);
    }
}

if (!function_exists('campaignReplaceCampaignPackageRows')) {
    function campaignReplaceCampaignPackageRows($connect, $campaignId, $packageIds)
    {
        // If the migration hasn't been run yet, there's nowhere to store the
        // selection - treat that as a no-op rather than failing the whole campaign
        // save (this runs inside a transaction alongside the main campaign save).
        if (!defined('CAMPAIGN_PACKAGE') || !campaignTableExists($connect, CAMPAIGN_PACKAGE)) {
            return true;
        }

        $campaignId = (int) $campaignId;
        $safeUserId = $connect->real_escape_string((string) campaignCurrentUserId());
        if (!$connect->query("UPDATE `" . CAMPAIGN_PACKAGE . "` SET `status` = 'D', `update_by` = '" . $safeUserId . "', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE `campaign_id` = " . $campaignId . " AND `status` = 'A'")) {
            return false;
        }

        $packageIds = array_values(array_unique(array_filter(array_map('intval', (array) $packageIds), function ($value) {
            return $value > 0;
        })));

        if (empty($packageIds)) {
            return true;
        }

        $stmt = $connect->prepare("INSERT INTO `" . CAMPAIGN_PACKAGE . "` (`campaign_id`, `package_id`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, CURDATE(), CURTIME(), 'A')");
        if (!$stmt) {
            return false;
        }

        $createBy = (string) campaignCurrentUserId();
        foreach ($packageIds as $packageId) {
            $stmt->bind_param('iis', $campaignId, $packageId, $createBy);
            if (!$stmt->execute()) {
                $stmt->close();
                return false;
            }
        }

        $stmt->close();
        return true;
    }
}

if (!function_exists('campaignPurchaseResolveOrderStatusDisplayName')) {
    function campaignPurchaseResolveOrderStatusDisplayName($statusCode)
    {
        $statusCode = trim((string) $statusCode);

        if ($statusCode === '') {
            return '';
        }

        if (function_exists('shopeeOmsGetStatusLabel')) {
            $statusLabel = shopeeOmsGetStatusLabel($statusCode);
            if (trim((string) $statusLabel) !== '') {
                return $statusLabel;
            }
        }

        if (function_exists('getOrderStatusLabel')) {
            $statusLabel = getOrderStatusLabel($statusCode);
            if (trim((string) $statusLabel) !== '' && trim((string) $statusLabel) !== $statusCode) {
                return $statusLabel;
            }
        }

        $statusMap = array(
            'P' => 'Pending To Pack',
            'SP' => 'Ship Processing',
            'WP' => 'Waiting Packing',
            'OC' => 'Order Received',
            'V' => 'Verified',
            'C' => 'Completed',
            'PR' => 'Parcel Received',
            'WAERD' => 'Waiting Assign Estimate Received Date',
            'AED' => 'Assigned Estimate Date',
            'WAFC' => 'Waiting After Final Check',
            'TS' => 'To Ship',
            'TP' => 'To Pack',
        );

        $upperStatusCode = strtoupper($statusCode);

        return isset($statusMap[$upperStatusCode]) ? $statusMap[$upperStatusCode] : $statusCode;
    }
}

if (!function_exists('campaignAutoDiscoverCustomersForPackages')) {
    function campaignAutoDiscoverCustomersForPackages($connect, $financeConnect, $campaign, $packageIds, $fromDate, $toDate)
    {
        $customers = array();
        if (empty($packageIds) || !($financeConnect instanceof mysqli)) {
            return $customers;
        }

        $campaignId = (int) ($campaign['id'] ?? 0);
        $configs = campaignPurchasePlatformConfigs($connect, $financeConnect);

        foreach ($configs as $platform => $config) {
            if (empty($config['table']) || !($config['conn'] instanceof mysqli)) {
                continue;
            }

            $table = (string) $config['table'];
            if (!campaignTableExists($config['conn'], $table)) {
                continue;
            }

            // Find all distinct customers who bought these packages
            $packageConditions = array();
            $packageCol = campaignGetFirstExistingColumn($config['conn'], $table, isset($config['package_cols']) ? $config['package_cols'] : array());
            if ($packageCol === '') {
                continue;
            }

            foreach ($packageIds as $pkgId) {
                $escapedId = $config['conn']->real_escape_string((string)$pkgId);
                $packageConditions[] = campaignPurchaseQuoteColumn($packageCol) . " LIKE '%" . $escapedId . "%'";
            }

            $dateCol = campaignGetFirstExistingColumn($config['conn'], $table, isset($config['date_cols']) ? $config['date_cols'] : array());
            if ($dateCol === '') {
                continue;
            }

            $customerCol = campaignGetFirstExistingColumn($config['conn'], $table, isset($config['customer_cols']) ? $config['customer_cols'] : array());
            if ($customerCol === '') {
                continue;
            }

            $safeFromDate = $config['conn']->real_escape_string(campaignDateValue($fromDate));
            $safeToDate = $config['conn']->real_escape_string(campaignDateValue($toDate));

            $sql = "SELECT DISTINCT " . campaignPurchaseQuoteColumn($customerCol) . " as customer_name FROM `" . $table . "`
                    WHERE (" . implode(' OR ', $packageConditions) . ")
                    AND DATE(" . campaignPurchaseQuoteColumn($dateCol) . ") >= '" . $safeFromDate . "'
                    AND DATE(" . campaignPurchaseQuoteColumn($dateCol) . ") <= '" . $safeToDate . "'
                    ORDER BY customer_name";

            $result = $config['conn']->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $custName = trim((string)($row['customer_name'] ?? ''));
                    if ($custName !== '' && !isset($customers[$custName])) {
                        // Create synthetic customer record for auto-discovered customers
                        $customers[$custName] = array(
                            'id' => 0,  // No campaign_customer id - this is auto-discovered
                            'campaign_id' => $campaignId,
                            'customer_name' => $custName,
                            'email' => '',
                            'phone' => '',
                            'platform' => strtolower($platform),
                            'status' => 'A',
                            '_auto_discovered' => true,
                        );
                    }
                }
            }
        }

        return array_values($customers);
    }
}

if (!function_exists('campaignPurchaseFetchOrdersForCustomer')) {
    function campaignPurchaseFetchOrdersForCustomer($connect, $financeConnect, $campaign, $campaignCustomer, $fromDate, $toDate, &$reasonOut = null)
    {
        $orders = array();
        $reasonOut = '';
        $platform = trim((string) ($campaignCustomer['platform'] ?? ''));
        $config = campaignPurchaseResolveConfig($connect, $financeConnect, $platform);
        if (empty($config) || empty($config['conn']) || empty($config['table'])) {
            $reasonOut = 'no_platform_config:' . $platform;
            return $orders;
        }

        $orderConn = $config['conn'];
        $table = (string) $config['table'];
        if (!($orderConn instanceof mysqli)) {
            $reasonOut = 'no_order_connection';
            return $orders;
        }
        if (!campaignTableExists($orderConn, $table)) {
            $reasonOut = 'order_table_missing:' . $table;
            return $orders;
        }

        $orderNoCol = campaignGetFirstExistingColumn($orderConn, $table, $config['order_no_cols']);
        $dateCol = campaignGetFirstExistingColumn($orderConn, $table, $config['date_cols']);
        $timeCol = campaignGetFirstExistingColumn($orderConn, $table, $config['time_cols']);
        $amountCol = campaignGetFirstExistingColumn($orderConn, $table, $config['amount_cols']);
        $orderStatusCol = campaignGetFirstExistingColumn($orderConn, $table, isset($config['order_status_cols']) ? $config['order_status_cols'] : array('order_status', 'status'));
        $packageCol = campaignGetFirstExistingColumn($orderConn, $table, $config['package_cols']);
        $detailCol = campaignGetFirstExistingColumn($orderConn, $table, $config['detail_cols']);

        if ($dateCol === '') {
            $reasonOut = 'no_date_column:' . $table;
            return $orders;
        }

        $matchParts = campaignPurchaseBuildCustomerMatchParts($orderConn, $table, $config, $campaignCustomer);
        if (empty($matchParts)) {
            $reasonOut = 'no_match_parts';
            return $orders;
        }

        // If the campaign has specific packages selected, only orders for those
        // packages should count toward this campaign's purchases/sales - otherwise a
        // customer's unrelated purchases in the same date window get counted too. An
        // empty selection means "no package restriction" (existing campaigns keep
        // their old behavior of counting every purchase in the period).
        $campaignPackageIds = campaignFetchCampaignPackageIds($connect, (int) ($campaign['id'] ?? 0));
        error_log("DEBUG campaignPurchaseFetchOrdersForCustomer: campaign_id=" . (int) ($campaign['id'] ?? 0) . " campaignPackageIds=" . json_encode($campaignPackageIds));

        $safeFromDate = $orderConn->real_escape_string(campaignDateValue($fromDate));
        $safeToDate = $orderConn->real_escape_string(campaignDateValue($toDate));
        if ($safeFromDate === '') {
            $safeFromDate = date('Y-m-01');
        }
        if ($safeToDate === '') {
            $safeToDate = date('Y-m-d');
        }

        $where = array(
            campaignPurchaseRowStatusWhere($orderConn, $table, $config),
            "DATE(" . campaignPurchaseQuoteColumn($dateCol) . ") >= '" . $safeFromDate . "'",
            "DATE(" . campaignPurchaseQuoteColumn($dateCol) . ") <= '" . $safeToDate . "'",
            '(' . implode(' OR ', $matchParts) . ')',
        );

        $selectColumns = array('`id`');
        foreach (array($orderNoCol, $dateCol, $timeCol, $amountCol, $orderStatusCol, $packageCol, $detailCol) as $column) {
            $quotedCol = campaignPurchaseQuoteColumn($column);
            if ($column !== '' && !in_array($quotedCol, $selectColumns, true)) {
                $selectColumns[] = $quotedCol;
            }
        }

        $sql = "SELECT " . implode(',', $selectColumns) . " FROM " . campaignTableName($table) . " WHERE " . implode(' AND ', $where) . " ORDER BY DATE(" . campaignPurchaseQuoteColumn($dateCol) . ") ASC, `id` ASC LIMIT 500";
        $result = mysqli_query($orderConn, $sql);
        if (!$result) {
            $reasonOut = 'query_failed:' . mysqli_error($orderConn);
            return $orders;
        }
        if ($result->num_rows === 0) {
            $reasonOut = 'query_ran_zero_rows:' . count($matchParts) . '_match_parts';
        }

        while ($row = $result->fetch_assoc()) {
            $orderId = campaignPurchaseReadCell($row, 'id');
            $orderNo = $orderNoCol !== '' ? campaignPurchaseReadCell($row, $orderNoCol) : '';
            if ($orderNo === '') {
                $orderNo = $orderId;
            }

            $orderDateRaw = $dateCol !== '' ? campaignPurchaseReadCell($row, $dateCol) : '';
            $orderTimeRaw = $timeCol !== '' ? campaignPurchaseReadCell($row, $timeCol) : '';
            $orderDateTime = trim($orderDateRaw . ' ' . $orderTimeRaw);
            if ($orderDateTime === '') {
                $orderDateTime = null;
            }

            $amount = $amountCol !== '' && is_numeric($row[$amountCol] ?? null) ? (float) $row[$amountCol] : 0.00;
            $detailText = $detailCol !== '' ? campaignNormalizeTextValue($row[$detailCol] ?? '', 65535) : '';
            $packageText = $packageCol !== '' ? campaignNormalizeTextValue($row[$packageCol] ?? '', 65535) : '';

            $orderPackageIds = campaignPurchaseExtractPackageIds($packageText, $connect);
            $packageId = null;

            if (!empty($campaignPackageIds)) {
                if (empty($orderPackageIds) || empty(array_intersect($orderPackageIds, $campaignPackageIds))) {
                    continue;
                }
                $matchingIds = array_intersect($orderPackageIds, $campaignPackageIds);
                $packageId = !empty($matchingIds) ? reset($matchingIds) : null;
            } elseif (!empty($orderPackageIds)) {
                $packageId = reset($orderPackageIds);
            }

            $orders[] = array(
                'platform' => $platform,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'order_detail' => $detailText !== '' ? $detailText : $packageText,
                'order_status' => $orderStatusCol !== '' ? campaignNormalizeTextValue($row[$orderStatusCol] ?? '', 100) : '',
                'order_amount' => $amount,
                'order_date' => $orderDateTime,
                'package_text' => $packageText !== '' ? $packageText : $detailText,
                'package_id' => $packageId,
            );
        }

        if (empty($orders) && !empty($campaignPackageIds)) {
            $reasonOut = 'no_orders_for_selected_packages';
        }

        return $orders;
    }
}

if (!function_exists('campaignPurchaseHasValidOrderBefore')) {
    function campaignPurchaseHasValidOrderBefore($connect, $financeConnect, $campaign, $campaignCustomer, $beforeDate)
    {
        $platform = trim((string) ($campaignCustomer['platform'] ?? ''));
        $config = campaignPurchaseResolveConfig($connect, $financeConnect, $platform);
        if (empty($config) || empty($config['conn']) || empty($config['table'])) {
            return false;
        }

        $orderConn = $config['conn'];
        $table = (string) $config['table'];
        if (!($orderConn instanceof mysqli) || !campaignTableExists($orderConn, $table)) {
            return false;
        }

        $dateCol = campaignGetFirstExistingColumn($orderConn, $table, $config['date_cols']);
        if ($dateCol === '') {
            return false;
        }

        $matchParts = campaignPurchaseBuildCustomerMatchParts($orderConn, $table, $config, $campaignCustomer);
        if (empty($matchParts)) {
            return false;
        }

        $safeBefore = $orderConn->real_escape_string(campaignDateValue($beforeDate));
        if ($safeBefore === '') {
            return false;
        }

        $sql = "SELECT `id` FROM " . campaignTableName($table) . " WHERE " . campaignPurchaseRowStatusWhere($orderConn, $table, $config) . " AND DATE(" . campaignPurchaseQuoteColumn($dateCol) . ") < '" . $safeBefore . "' AND (" . implode(' OR ', $matchParts) . ") LIMIT 1";
        $result = mysqli_query($orderConn, $sql);

        return ($result && $result->num_rows > 0);
    }
}

if (!function_exists('campaignBackfillPackageIds')) {
    function campaignBackfillPackageIds($connect, $campaignId)
    {
        if (!defined('CAMPAIGN_PURCHASE_RECORD') || !campaignTableExists($connect, CAMPAIGN_PURCHASE_RECORD)) {
            return;
        }

        $campaignPackageIds = campaignFetchCampaignPackageIds($connect, (int) $campaignId);
        if (empty($campaignPackageIds)) {
            return;
        }

        $nullPackageSql = "SELECT `id`, `package_text` FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A' AND `package_id` IS NULL";
        $nullPackageResult = mysqli_query($connect, $nullPackageSql);
        if (!$nullPackageResult) {
            return;
        }

        $updateStmt = $connect->prepare("UPDATE " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " SET `package_id`=? WHERE `id`=?");
        if (!$updateStmt) {
            return;
        }

        while ($row = $nullPackageResult->fetch_assoc()) {
            $recordId = (int) ($row['id'] ?? 0);
            $packageText = trim((string) ($row['package_text'] ?? ''));

            if ($recordId <= 0 || $packageText === '') {
                continue;
            }

            $orderPackageIds = campaignPurchaseExtractPackageIds($packageText, $connect);
            $matchingIds = array_intersect($orderPackageIds, $campaignPackageIds);
            $packageId = !empty($matchingIds) ? reset($matchingIds) : null;

            if ($packageId !== null) {
                $packageIdInt = (int) $packageId;
                $updateStmt->bind_param('ii', $packageIdInt, $recordId);
                $updateStmt->execute();
            }
        }

        $updateStmt->close();
    }
}

if (!function_exists('campaignRunPurchaseCheck')) {
    function campaignRunPurchaseCheck($connect, $financeConnect, $campaignId, $fromDate = '', $toDate = '')
    {
        $summary = array(
            'checked_customers' => 0,
            'orders_found' => 0,
            'records_inserted' => 0,
            'records_updated' => 0,
            'customers_purchased' => 0,
            'customers_not_purchased' => 0,
            'notes' => array(),
            'skip_reasons' => array(),
            'campaign_package_ids' => array(),
            'debug_info' => array(),
        );

        $campaign = campaignFetchCampaign($connect, $campaignId);
        if (empty($campaign)) {
            $summary['notes'][] = 'Campaign not found.';
            return $summary;
        }

        // Store campaign package IDs for debugging
        $summary['campaign_package_ids'] = campaignFetchCampaignPackageIds($connect, $campaignId);
        $summary['debug_info'][] = 'Campaign ID: ' . $campaignId . ', Selected Package IDs: [' . implode(', ', $summary['campaign_package_ids']) . ']';

        $periodStart = campaignDateValue($fromDate !== '' ? $fromDate : ($campaign['period_start_date'] ?? ''));
        $periodEnd = campaignDateValue($toDate !== '' ? $toDate : ($campaign['period_end_date'] ?? ''));
        if ($periodStart === '') {
            $periodStart = date('Y-m-01');
        }
        if ($periodEnd === '') {
            $periodEnd = date('Y-m-d');
        }

        if (!campaignTableExists($connect, CAMPAIGN_CUSTOMER) || !campaignTableExists($connect, CAMPAIGN_PURCHASE_RECORD)) {
            $summary['notes'][] = 'Campaign purchase tables are not ready. Please run insert_table.php.';
            return $summary;
        }

        // Drop any previously-stored purchase rows that no longer fall inside the
        // campaign's current period (e.g. the period dates were edited after an
        // earlier refresh), or that were recorded before returned/closed-returned
        // orders started being excluded, so the report never sums stale rows.
        $safePeriodStart = $connect->real_escape_string($periodStart);
        $safePeriodEnd = $connect->real_escape_string($periodEnd);
        mysqli_query($connect, "UPDATE " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . "
            SET `status`='D', `update_by`='" . $connect->real_escape_string((string) campaignCurrentUserId()) . "', `update_date`=CURDATE(), `update_time`=CURTIME()
            WHERE `campaign_id`='" . (int) $campaignId . "'
              AND `status`='A'
              AND (
                `order_date` IS NULL
                OR DATE(`order_date`) < '" . $safePeriodStart . "'
                OR DATE(`order_date`) > '" . $safePeriodEnd . "'
                OR UPPER(TRIM(IFNULL(`order_status`, ''))) IN ('R', 'CR')
              )");

        $customers = array();
        $customerSql = "SELECT * FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `status`='A' ORDER BY `id` ASC";
        $customerResult = mysqli_query($connect, $customerSql);
        if ($customerResult) {
            while ($customerRow = $customerResult->fetch_assoc()) {
                $customers[] = $customerRow;
            }
        }

        // If no customers added manually, auto-discover customers from Finance DB who bought campaign packages
        if (empty($customers) && !empty($summary['campaign_package_ids'])) {
            $summary['debug_info'][] = 'No manual customers found. Auto-discovering customers from Finance DB...';
            $customers = campaignAutoDiscoverCustomersForPackages($connect, $financeConnect, $campaign, $summary['campaign_package_ids'], $periodStart, $periodEnd);
            $summary['debug_info'][] = 'Auto-discovered ' . count($customers) . ' customers';
        }

        $insertStmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " (`campaign_id`,`campaign_customer_id`,`package_id`,`platform`,`order_id`,`order_no`,`order_detail`,`order_status`,`order_amount`,`order_date`,`package_text`,`customer_type`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),CURTIME(),'A')");
        $updateRecordStmt = $connect->prepare("UPDATE " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " SET `package_id`=?, `order_detail`=?, `order_status`=?, `order_amount`=?, `order_date`=?, `package_text`=?, `customer_type`=?, `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=?");
        $userId = campaignCurrentUserId();

        foreach ($customers as $customerRow) {
            $summary['checked_customers']++;
            $campaignCustomerId = (int) ($customerRow['id'] ?? 0);
            $isAutoDiscovered = !empty($customerRow['_auto_discovered']);

            // Skip only if not auto-discovered AND id is missing/invalid
            if (!$isAutoDiscovered && $campaignCustomerId <= 0) {
                continue;
            }

            $fetchReason = '';
            $orders = campaignPurchaseFetchOrdersForCustomer($connect, $financeConnect, $campaign, $customerRow, $periodStart, $periodEnd, $fetchReason);
            $hasOldOrder = campaignPurchaseHasValidOrderBefore($connect, $financeConnect, $campaign, $customerRow, $periodStart);
            $customerType = $hasOldOrder ? 'Return Customer' : 'New Customer';
            $purchaseStatus = empty($orders) ? 'Not Purchased' : 'Purchased';

            if (empty($orders)) {
                $summary['customers_not_purchased']++;
                if ($fetchReason !== '') {
                    $reasonKey = explode(':', $fetchReason)[0];
                    if (!isset($summary['skip_reasons'][$reasonKey])) {
                        $summary['skip_reasons'][$reasonKey] = 0;
                    }
                    $summary['skip_reasons'][$reasonKey]++;
                }
            } else {
                $summary['customers_purchased']++;
            }

            $confirmedRecordIds = array();

            foreach ($orders as $order) {
                $summary['orders_found']++;
                $safeOrderId = $connect->real_escape_string((string) ($order['order_id'] ?? ''));
                $safeOrderNo = $connect->real_escape_string((string) ($order['order_no'] ?? ''));
                $safePlatform = $connect->real_escape_string((string) ($order['platform'] ?? ($customerRow['platform'] ?? '')));
                $dupSql = "SELECT `id` FROM " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `campaign_customer_id`='" . $campaignCustomerId . "' AND `platform`='" . $safePlatform . "' AND (`order_id`='" . $safeOrderId . "' OR `order_no`='" . $safeOrderNo . "') AND `status`='A' LIMIT 1";
                $dupResult = mysqli_query($connect, $dupSql);
                $existingRecordId = 0;
                if ($dupResult && $dupResult->num_rows > 0) {
                    $existingRecordRow = $dupResult->fetch_assoc();
                    $existingRecordId = (int) ($existingRecordRow['id'] ?? 0);
                }

                $orderDetail = campaignNormalizeTextValue($order['order_detail'] ?? '', 65535);
                $orderStatus = campaignNormalizeTextValue($order['order_status'] ?? '', 100);
                $orderAmount = (float) ($order['order_amount'] ?? 0);
                $orderDate = trim((string) ($order['order_date'] ?? ''));
                $packageText = campaignNormalizeTextValue($order['package_text'] ?? '', 65535);
                $packageId = isset($order['package_id']) && $order['package_id'] !== null ? (int) $order['package_id'] : null;

                if ($existingRecordId > 0) {
                    if ($updateRecordStmt) {
                        $packageIdForBind = $packageId;
                        $updateRecordStmt->bind_param('isdsdssi', $packageIdForBind, $orderDetail, $orderStatus, $orderAmount, $orderDate, $packageText, $customerType, $userId, $existingRecordId);
                        if ($updateRecordStmt->execute()) {
                            $summary['records_updated']++;
                        }
                    }
                    $confirmedRecordIds[] = $existingRecordId;
                    continue;
                }

                if ($insertStmt) {
                    $platform = (string) ($order['platform'] ?? ($customerRow['platform'] ?? ''));
                    $orderId = (string) ($order['order_id'] ?? '');
                    $orderNo = (string) ($order['order_no'] ?? '');
                    $packageIdForBind = $packageId;
                    $insertStmt->bind_param('iiisssssdssss', $campaignId, $campaignCustomerId, $packageIdForBind, $platform, $orderId, $orderNo, $orderDetail, $orderStatus, $orderAmount, $orderDate, $packageText, $customerType, $userId);
                    if ($insertStmt->execute()) {
                        $summary['records_inserted']++;
                        $newRecordId = (int) $connect->insert_id;
                        if ($newRecordId > 0) {
                            $confirmedRecordIds[] = $newRecordId;
                        }
                    }
                }
            }

            // Reconcile: any purchase record still stored for this campaign customer
            // that this run did NOT re-confirm is stale (e.g. it was only ever a
            // false-positive match from a since-fixed matching rule, or the order fell
            // out of the period). Retire it instead of leaving it to inflate the report.
            //
            // Only do this when the current run actually found at least one order for
            // this customer. If it found none, that's ambiguous - it could genuinely be
            // "no purchase this period", or it could be a lookup/config problem - and
            // wiping previously-confirmed records on an ambiguous empty result risks
            // silently destroying real data. Leave existing records untouched in that
            // case; a later refresh that does find matches will still clean them up.
            if (!empty($confirmedRecordIds)) {
                $confirmedIdsSql = implode(',', array_map('intval', $confirmedRecordIds));
                mysqli_query($connect, "UPDATE " . campaignTableName(CAMPAIGN_PURCHASE_RECORD) . "
                    SET `status`='D', `update_by`='" . $connect->real_escape_string((string) $userId) . "', `update_date`=CURDATE(), `update_time`=CURTIME()
                    WHERE `campaign_id`='" . (int) $campaignId . "'
                      AND `campaign_customer_id`='" . $campaignCustomerId . "'
                      AND `status`='A'
                      AND `id` NOT IN (" . $confirmedIdsSql . ")");
            }

            $updateStmt = $connect->prepare("UPDATE " . campaignTableName(CAMPAIGN_CUSTOMER) . " SET `purchase_status`=?, `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `campaign_id`=?");
            if ($updateStmt) {
                $updateStmt->bind_param('ssii', $purchaseStatus, $userId, $campaignCustomerId, $campaignId);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }

        if ($insertStmt) {
            $insertStmt->close();
        }
        if ($updateRecordStmt) {
            $updateRecordStmt->close();
        }

        campaignBackfillPackageIds($connect, $campaignId);

        return $summary;
    }
}

if (!function_exists('campaignRuleDecodeJson')) {
    function campaignRuleDecodeJson($value, $default = array())
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('campaignRuleSettingJsonEncode')) {
    function campaignRuleSettingJsonEncode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('campaignRuleSettingReadSelectedIds')) {
    function campaignRuleSettingReadSelectedIds($name, $source = null)
    {
        $values = array();
        if (is_array($source)) {
            if (!isset($source[$name])) {
                return array();
            }
            $values = is_array($source[$name]) ? $source[$name] : array($source[$name]);
        } else {
            $values = (array) post($name) ?: array();
        }

        $ids = array();
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('campaignRuleSettingNormalizeConditionJson')) {
    function campaignRuleSettingNormalizeConditionJson($rawJson)
    {
        return campaignRuleConditionBuildJsonFromExisting($rawJson, array());
    }
}

if (!function_exists('campaignRuleConditionPlatformOptions')) {
    function campaignRuleConditionPlatformOptions()
    {
        return array(
            'shopee' => 'Shopee',
            'lazada' => 'Lazada',
            'website' => 'Website',
            'facebook' => 'Facebook',
        );
    }
}

if (!function_exists('campaignRuleConditionLastOrderOptions')) {
    function campaignRuleConditionLastOrderOptions()
    {
        return array(
            'any' => array(
                'label' => 'All',
                'summary_label' => 'All',
                'value' => 'any',
                'payload' => array(),
            ),
            'more_than_30' => array(
                'label' => 'More than 30 days',
                'summary_label' => '> 30 days',
                'value' => 'more_than_30',
                'payload' => array('operator' => 'more_than', 'days' => 30),
            ),
            'more_than_60' => array(
                'label' => 'More than 60 days',
                'summary_label' => '> 60 days',
                'value' => 'more_than_60',
                'payload' => array('operator' => 'more_than', 'days' => 60),
            ),
            'more_than_90' => array(
                'label' => 'More than 90 days',
                'summary_label' => '> 90 days',
                'value' => 'more_than_90',
                'payload' => array('operator' => 'more_than', 'days' => 90),
            ),
            'more_than_180' => array(
                'label' => 'More than 180 days',
                'summary_label' => '> 180 days',
                'value' => 'more_than_180',
                'payload' => array('operator' => 'more_than', 'days' => 180),
            ),
            'less_than_30' => array(
                'label' => 'Less than 30 days',
                'summary_label' => '< 30 days',
                'value' => 'less_than_30',
                'payload' => array('operator' => 'less_than', 'days' => 30),
            ),
            'less_than_60' => array(
                'label' => 'Less than 60 days',
                'summary_label' => '< 60 days',
                'value' => 'less_than_60',
                'payload' => array('operator' => 'less_than', 'days' => 60),
            ),
            'less_than_90' => array(
                'label' => 'Less than 90 days',
                'summary_label' => '< 90 days',
                'value' => 'less_than_90',
                'payload' => array('operator' => 'less_than', 'days' => 90),
            ),
            'no_order_record' => array(
                'label' => 'No order record',
                'summary_label' => 'No order record',
                'value' => 'no_order_record',
                'payload' => array('operator' => 'no_order_record'),
            ),
        );
    }
}

if (!function_exists('campaignRuleConditionEmpty')) {
    function campaignRuleConditionEmpty()
    {
        return array(
            'platforms' => array(),
            'tags' => array(),
            'brands' => array(),
            'last_order' => array(),
            'last_order_key' => 'any',
        );
    }
}

if (!function_exists('campaignRuleConditionSanitizeStringList')) {
    function campaignRuleConditionSanitizeStringList($values, $allowedMap = array())
    {
        $clean = array();
        $allowedKeys = array_keys((array) $allowedMap);
        foreach ((array) $values as $value) {
            $value = strtolower(trim((string) $value));
            if ($value === '') {
                continue;
            }
            if (!empty($allowedKeys) && !in_array($value, $allowedKeys, true)) {
                continue;
            }
            $clean[$value] = $value;
        }

        return array_values($clean);
    }
}

if (!function_exists('campaignRuleConditionSanitizeIdList')) {
    function campaignRuleConditionSanitizeIdList($values)
    {
        $clean = array();
        foreach ((array) $values as $value) {
            $value = trim((string) $value);
            if ($value === '' || !ctype_digit($value) || (int) $value <= 0) {
                continue;
            }
            $clean[$value] = $value;
        }

        return array_values($clean);
    }
}

if (!function_exists('campaignRuleConditionReadPostedValues')) {
    function campaignRuleConditionReadPostedValues($name, $source = null)
    {
        $values = array();
        if (is_array($source)) {
            if (!isset($source[$name])) {
                return array();
            }
            $values = is_array($source[$name]) ? $source[$name] : array($source[$name]);
        } else {
            $values = (array) post($name) ?: array();
        }

        $clean = array();
        foreach ($values as $value) {
            $clean[] = trim((string) $value);
        }

        return $clean;
    }
}

if (!function_exists('campaignRuleConditionResolveLastOrderKey')) {
    function campaignRuleConditionResolveLastOrderKey($lastOrder)
    {
        $lastOrder = is_array($lastOrder) ? $lastOrder : array();
        $operator = strtolower(trim((string) ($lastOrder['operator'] ?? '')));
        $days = isset($lastOrder['days']) ? (int) $lastOrder['days'] : 0;

        foreach (campaignRuleConditionLastOrderOptions() as $key => $option) {
            $payload = isset($option['payload']) && is_array($option['payload']) ? $option['payload'] : array();
            $payloadOperator = strtolower(trim((string) ($payload['operator'] ?? '')));
            $payloadDays = isset($payload['days']) ? (int) $payload['days'] : 0;
            if ($operator === $payloadOperator && $days === $payloadDays) {
                return $key;
            }
            if ($operator === 'no_order_record' && $payloadOperator === 'no_order_record') {
                return $key;
            }
        }

        return 'any';
    }
}

if (!function_exists('campaignRuleConditionResolveLastOrderValue')) {
    function campaignRuleConditionResolveLastOrderValue($selectedValue)
    {
        $selectedValue = strtolower(trim((string) $selectedValue));
        $options = campaignRuleConditionLastOrderOptions();
        if (!isset($options[$selectedValue])) {
            return array();
        }

        $payload = isset($options[$selectedValue]['payload']) && is_array($options[$selectedValue]['payload']) ? $options[$selectedValue]['payload'] : array();
        if (empty($payload) || ($payload['operator'] ?? '') === '') {
            return array();
        }

        return $payload;
    }
}

if (!function_exists('campaignRuleConditionDecodeForUi')) {
    function campaignRuleConditionDecodeForUi($rawJson)
    {
        $condition = campaignRuleConditionEmpty();
        $decoded = campaignRuleDecodeJson($rawJson, array());
        if (empty($decoded) || !is_array($decoded)) {
            return $condition;
        }

        $hasNewKeys = isset($decoded['platforms']) || isset($decoded['tags']) || isset($decoded['brands']) || isset($decoded['last_order']);
        if (!$hasNewKeys) {
            return $condition;
        }

        $condition['platforms'] = campaignRuleConditionSanitizeStringList($decoded['platforms'] ?? array(), campaignRuleConditionPlatformOptions());
        $condition['tags'] = campaignRuleConditionSanitizeIdList($decoded['tags'] ?? array());
        $condition['brands'] = campaignRuleConditionSanitizeIdList($decoded['brands'] ?? array());

        $lastOrder = isset($decoded['last_order']) && is_array($decoded['last_order']) ? $decoded['last_order'] : array();
        $lastOrderKey = campaignRuleConditionResolveLastOrderKey($lastOrder);
        $condition['last_order'] = $lastOrderKey === 'any' ? array() : campaignRuleConditionResolveLastOrderValue($lastOrderKey);
        $condition['last_order_key'] = $lastOrderKey;

        return $condition;
    }
}

if (!function_exists('campaignRuleConditionBuildJsonFromExisting')) {
    function campaignRuleConditionBuildJsonFromExisting($existingRawJson, $input = array())
    {
        $input = is_array($input) ? $input : array();

        $payload = array();
        $platforms = campaignRuleConditionSanitizeStringList($input['platforms'] ?? array(), campaignRuleConditionPlatformOptions());
        $tags = campaignRuleConditionSanitizeIdList($input['tags'] ?? array());
        $brands = campaignRuleConditionSanitizeIdList($input['brands'] ?? array());
        $lastOrderValue = campaignRuleConditionResolveLastOrderValue($input['last_order_key'] ?? 'any');
        $campaignPeriodRule = trim((string) ($input['campaign_period_rule'] ?? ''));
        $periodDays = isset($input['period_days']) ? (int) $input['period_days'] : 0;

        if (!empty($platforms)) {
            $payload['platforms'] = $platforms;
        }
        if (!empty($tags)) {
            $payload['tags'] = $tags;
        }
        if (!empty($brands)) {
            $payload['brands'] = $brands;
        }
        if (!empty($lastOrderValue)) {
            $payload['last_order'] = $lastOrderValue;
        }

        if ($campaignPeriodRule === 'Custom Days') {
            if ($periodDays > 0) {
                $payload['period_days'] = $periodDays;
            }
        }

        if (empty($payload)) {
            return '{}';
        }

        return campaignRuleSettingJsonEncode($payload);
    }
}

if (!function_exists('campaignRuleConditionBuildJsonFromPost')) {
    function campaignRuleConditionBuildJsonFromPost($source = null, $existingRawJson = '')
    {
        if (!is_array($source)) {
            $source = array(
                'target_platforms' => (array) post('target_platforms') ?: array(),
                'target_tags' => (array) post('target_tags') ?: array(),
                'target_brands' => (array) post('target_brands') ?: array(),
                'target_last_order' => post('target_last_order'),
                'campaign_period_rule' => post('campaign_period_rule'),
                'custom_period_days' => post('custom_period_days'),
            );
        }

        return campaignRuleConditionBuildJsonFromExisting($existingRawJson, array(
            'platforms' => campaignRuleConditionReadPostedValues('target_platforms', $source),
            'tags' => campaignRuleConditionReadPostedValues('target_tags', $source),
            'brands' => campaignRuleConditionReadPostedValues('target_brands', $source),
            'last_order_key' => trim((string) ($source['target_last_order'] ?? 'any')),
            'campaign_period_rule' => trim((string) ($source['campaign_period_rule'] ?? '')),
            'period_days' => (int) ($source['custom_period_days'] ?? 0),
        ));
    }
}

if (!function_exists('campaignFetchActiveSimpleOptions')) {
    function campaignFetchActiveSimpleOptions($connect, $tblName, $nameColumn = 'name')
    {
        $rows = array();
        if (!campaignTableExists($connect, $tblName) || !campaignColumnExists($connect, $tblName, $nameColumn)) {
            return $rows;
        }

        $where = campaignColumnExists($connect, $tblName, 'status') ? "`status`='A'" : '1=1';
        $sql = "SELECT `id`, `" . str_replace('`', '``', $nameColumn) . "` AS option_name FROM " . campaignTableName($tblName) . " WHERE " . $where . " ORDER BY option_name ASC, `id` ASC";
        $result = mysqli_query($connect, $sql);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = trim((string) ($row['option_name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $rows[] = array('id' => $id, 'name' => $name);
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('campaignRuleFetchActiveTagOptions')) {
    function campaignRuleFetchActiveTagOptions($connect)
    {
        if (function_exists('customerTagGetActiveTagOptions')) {
            $rows = array();
            foreach ((array) customerTagGetActiveTagOptions($connect) as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $name = trim((string) ($row['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $rows[] = array('id' => $id, 'name' => $name);
                }
            }
            return $rows;
        }

        return defined('TAG') ? campaignFetchActiveSimpleOptions($connect, TAG) : array();
    }
}

if (!function_exists('campaignRuleFetchActiveBrandOptions')) {
    function campaignRuleFetchActiveBrandOptions($connect)
    {
        return defined('BRAND') ? campaignFetchActiveSimpleOptions($connect, BRAND) : array();
    }
}

if (!function_exists('campaignRuleBuildOptionNameMap')) {
    function campaignRuleBuildOptionNameMap($options)
    {
        $map = array();
        foreach ((array) $options as $option) {
            $id = trim((string) ($option['id'] ?? ''));
            $name = trim((string) ($option['name'] ?? ''));
            if ($id !== '' && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }
}

if (!function_exists('campaignRuleResolveSelectedOptionNames')) {
    function campaignRuleResolveSelectedOptionNames($selectedIds, $nameMap)
    {
        $names = array();
        foreach ((array) $selectedIds as $selectedId) {
            $selectedId = trim((string) $selectedId);
            if ($selectedId !== '' && isset($nameMap[$selectedId])) {
                $names[] = $nameMap[$selectedId];
            }
        }

        return $names;
    }
}

if (!function_exists('campaignRuleConditionLastOrderLabel')) {
    function campaignRuleConditionLastOrderLabel($condition, $forSummary = true)
    {
        $condition = is_array($condition) ? $condition : campaignRuleConditionDecodeForUi($condition);
        $key = isset($condition['last_order_key']) ? (string) $condition['last_order_key'] : 'any';
        $options = campaignRuleConditionLastOrderOptions();
        if (!isset($options[$key])) {
            return '';
        }

        return $forSummary
            ? (string) ($options[$key]['summary_label'] ?? '')
            : (string) ($options[$key]['label'] ?? '');
    }
}

if (!function_exists('campaignRuleConditionSummaryItems')) {
    function campaignRuleConditionSummaryItems($connect, $rawJson)
    {
        $condition = campaignRuleConditionDecodeForUi($rawJson);
        $items = array();

        $platformOptions = campaignRuleConditionPlatformOptions();
        $platformNames = array();
        foreach ((array) $condition['platforms'] as $platformKey) {
            if (isset($platformOptions[$platformKey])) {
                $platformNames[] = $platformOptions[$platformKey];
            }
        }
        $items[] = array(
            'key' => 'platform',
            'label' => 'Platform: ' . (!empty($platformNames) ? implode(', ', $platformNames) : 'All'),
        );

        $tagNames = campaignRuleResolveSelectedOptionNames($condition['tags'], campaignRuleBuildOptionNameMap(campaignRuleFetchActiveTagOptions($connect)));
        $items[] = array(
            'key' => 'tag',
            'label' => 'Tag: ' . (!empty($tagNames) ? implode(', ', $tagNames) : 'All'),
        );

        $brandNames = campaignRuleResolveSelectedOptionNames($condition['brands'], campaignRuleBuildOptionNameMap(campaignRuleFetchActiveBrandOptions($connect)));
        $items[] = array(
            'key' => 'brand',
            'label' => 'Brand: ' . (!empty($brandNames) ? implode(', ', $brandNames) : 'All'),
        );

        $lastOrderLabel = campaignRuleConditionLastOrderLabel($condition, true);
        $items[] = array(
            'key' => 'last_order',
            'label' => 'Last Order: ' . ($lastOrderLabel !== '' ? $lastOrderLabel : 'All'),
        );

        return $items;
    }
}

if (!function_exists('campaignRuleConditionSummaryText')) {
    function campaignRuleConditionSummaryText($connect, $rawJson)
    {
        $parts = array();
        foreach (campaignRuleConditionSummaryItems($connect, $rawJson) as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        return empty($parts) ? 'Platform: All | Tag: All | Brand: All | Last Order: All' : implode(' | ', $parts);
    }
}

if (!function_exists('campaignRuleTargetPlatformConfigs')) {
    function campaignRuleTargetPlatformConfigs($connect, $financeConnect)
    {
        return array(
            'shopee' => array(
                'label' => 'Shopee',
                'customer_conn' => $financeConnect,
                'customer_table' => defined('SHOPEE_CUST_INFO') ? SHOPEE_CUST_INFO : 'shopee_customer_info',
                'order_conn' => $financeConnect,
                'order_table' => defined('SHOPEE_SG_ORDER_REQ') ? SHOPEE_SG_ORDER_REQ : 'shopee_sg_order_request',
                'display_cols' => array('buyer_username', 'name', 'customer_name'),
                'contact_cols' => array('contact_no', 'phone', 'contact'),
            ),
            'lazada' => array(
                'label' => 'Lazada',
                'customer_conn' => $connect,
                'customer_table' => defined('LAZADA_CUST_RCD') ? LAZADA_CUST_RCD : 'lazada_customer_record',
                'order_conn' => $connect,
                'order_table' => defined('LAZADA_ORDER_REQ') ? LAZADA_ORDER_REQ : 'lazada_order_request',
                'display_cols' => array('name', 'customer_name'),
                'contact_cols' => array('contact', 'phone', 'contact_no'),
            ),
            'website' => array(
                'label' => 'Website',
                'customer_conn' => $connect,
                'customer_table' => defined('WEB_CUST_RCD') ? WEB_CUST_RCD : 'website_customer_record',
                'order_conn' => $financeConnect,
                'order_table' => defined('WEB_ORDER_REQ') ? WEB_ORDER_REQ : 'website_order_request',
                'display_cols' => array('name', 'customer_name'),
                'contact_cols' => array('contact', 'phone', 'contact_no'),
            ),
            'facebook' => array(
                'label' => 'Facebook',
                'customer_conn' => $connect,
                'customer_table' => defined('FB_CUST_DEALS') ? FB_CUST_DEALS : 'facebook_customer_deals',
                'order_conn' => $financeConnect,
                'order_table' => defined('FB_ORDER_REQ') ? FB_ORDER_REQ : 'facebook_order_request',
                'display_cols' => array('name', 'customer_name'),
                'contact_cols' => array('contact', 'phone', 'contact_no'),
            ),
        );
    }
}

if (!function_exists('campaignRuleFetchActiveRows')) {
    function campaignRuleFetchActiveRows($conn, $tblName)
    {
        if (!($conn instanceof mysqli) || !campaignTableExists($conn, $tblName)) {
            return array();
        }

        $where = campaignColumnExists($conn, $tblName, 'status') ? "WHERE IFNULL(`status`, 'A') = 'A'" : '';
        $rows = array();
        $result = mysqli_query($conn, "SELECT * FROM " . campaignTableName($tblName) . " " . $where);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('campaignRuleResolveCustomerDisplayName')) {
    function campaignRuleResolveCustomerDisplayName($platformKey, $customerRow, $fallbackId = '')
    {
        $configs = campaignRuleTargetPlatformConfigs(null, null);
        $displayCols = isset($configs[$platformKey]['display_cols']) ? (array) $configs[$platformKey]['display_cols'] : array('name', 'customer_name');
        foreach ($displayCols as $column) {
            $value = trim((string) ($customerRow[$column] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return trim((string) $fallbackId);
    }
}

if (!function_exists('campaignRuleResolveCustomerContact')) {
    function campaignRuleResolveCustomerContact($platformKey, $customerRow)
    {
        $configs = campaignRuleTargetPlatformConfigs(null, null);
        $contactCols = isset($configs[$platformKey]['contact_cols']) ? (array) $configs[$platformKey]['contact_cols'] : array('contact', 'phone', 'contact_no');
        foreach ($contactCols as $column) {
            $value = trim((string) ($customerRow[$column] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('campaignRuleResolveCustomerBrandId')) {
    function campaignRuleResolveCustomerBrandId($connect, $customerRow, $seriesLookup)
    {
        $seriesId = 0;
        if (isset($customerRow['_resolved_series_id'])) {
            $seriesId = (int) $customerRow['_resolved_series_id'];
        } else if (function_exists('customerLabelResolveSeriesId')) {
            $seriesId = customerLabelResolveSeriesId($customerRow['series'] ?? '', $seriesLookup);
        }

        return ($seriesId > 0 && isset($seriesLookup['brand_by_id'][$seriesId])) ? (int) $seriesLookup['brand_by_id'][$seriesId] : 0;
    }
}

if (!function_exists('campaignRuleResolveOrderDateColumn')) {
    function campaignRuleResolveOrderDateColumn($connect, $financeConnect, $platformLabel, $orderConn, $orderTable)
    {
        $purchaseConfig = campaignPurchaseResolveConfig($connect, $financeConnect, $platformLabel);
        $dateCols = isset($purchaseConfig['date_cols']) ? (array) $purchaseConfig['date_cols'] : array('date', 'order_date', 'create_date');
        return campaignGetFirstExistingColumn($orderConn, $orderTable, $dateCols);
    }
}

if (!function_exists('campaignRuleResolveOrderAmountColumn')) {
    function campaignRuleResolveOrderAmountColumn($connect, $financeConnect, $platformLabel, $orderConn, $orderTable)
    {
        $purchaseConfig = campaignPurchaseResolveConfig($connect, $financeConnect, $platformLabel);
        $amountCols = isset($purchaseConfig['amount_cols']) ? (array) $purchaseConfig['amount_cols'] : array('final_amt', 'price', 'total', 'amount');
        return campaignGetFirstExistingColumn($orderConn, $orderTable, $amountCols);
    }
}

if (!function_exists('campaignRuleCustomerLastOrderMatches')) {
    function campaignRuleCustomerLastOrderMatches($lastOrder, $condition)
    {
        if (empty($condition) || !is_array($condition)) {
            return true;
        }

        $operator = strtolower(trim((string) ($condition['operator'] ?? '')));
        if ($operator === '') {
            return true;
        }

        $lastOrder = campaignDateValue($lastOrder);
        if ($operator === 'no_order_record') {
            return $lastOrder === '';
        }

        if ($lastOrder === '') {
            return false;
        }

        $days = max(0, (int) ($condition['days'] ?? 0));
        if ($days <= 0) {
            return true;
        }

        $daysDiff = (int) floor((strtotime(date('Y-m-d')) - strtotime($lastOrder)) / 86400);
        if ($operator === 'more_than') {
            return $daysDiff > $days;
        }
        if ($operator === 'less_than') {
            return $daysDiff < $days;
        }

        return true;
    }
}

if (!function_exists('campaignRuleCustomerMatchesCondition')) {
    function campaignRuleCustomerMatchesCondition($candidate, $condition)
    {
        $condition = is_array($condition) ? $condition : campaignRuleConditionDecodeForUi($condition);

        $selectedTagIds = array_map('intval', $condition['tags'] ?? array());
        if (!empty($selectedTagIds)) {
            $candidateTagIds = array_map('intval', $candidate['tag_ids'] ?? array());
            if (empty(array_intersect($selectedTagIds, $candidateTagIds))) {
                return false;
            }
        }

        $selectedBrandIds = array_map('intval', $condition['brands'] ?? array());
        if (!empty($selectedBrandIds) && !in_array((int) ($candidate['brand_id'] ?? 0), $selectedBrandIds, true)) {
            return false;
        }

        if (!campaignRuleCustomerLastOrderMatches($candidate['last_order_date'] ?? '', $condition['last_order'] ?? array())) {
            return false;
        }

        return true;
    }
}

if (!function_exists('campaignRuleBuildPlatformCustomers')) {
    function campaignRuleBuildPlatformCustomers($connect, $financeConnect, $platformKey, $condition)
    {
        $configs = campaignRuleTargetPlatformConfigs($connect, $financeConnect);
        if (!isset($configs[$platformKey])) {
            return array();
        }

        $config = $configs[$platformKey];
        $customerConn = $config['customer_conn'] ?? null;
        $orderConn = $config['order_conn'] ?? null;
        $customerTable = trim((string) ($config['customer_table'] ?? ''));
        $orderTable = trim((string) ($config['order_table'] ?? ''));
        if (!($customerConn instanceof mysqli) || !($orderConn instanceof mysqli) || $customerTable === '') {
            return array();
        }

        $customerRows = campaignRuleFetchActiveRows($customerConn, $customerTable);
        if (empty($customerRows)) {
            return array();
        }

        $seriesLookup = function_exists('customerLabelGetSeriesLookup') ? customerLabelGetSeriesLookup($connect) : array('brand_by_id' => array());
        $customerIndexes = function_exists('customerLabelBuildCustomerIndexes')
            ? customerLabelBuildCustomerIndexes($platformKey, $customerRows, $seriesLookup)
            : array('rows_by_id' => array());
        if (empty($customerIndexes['rows_by_id'])) {
            foreach ($customerRows as $customerRow) {
                $customerId = isset($customerRow['id']) ? (int) $customerRow['id'] : 0;
                if ($customerId > 0) {
                    $customerIndexes['rows_by_id'][$customerId] = $customerRow;
                }
            }
        }
        if (empty($customerIndexes['rows_by_id'])) {
            return array();
        }

        $customerIds = array_keys($customerIndexes['rows_by_id']);
        $tagMap = function_exists('customerTagGetCustomerTagMap') ? customerTagGetCustomerTagMap($connect, $platformKey, $customerIds) : array();
        $orderMetrics = array();
        foreach ($customerIds as $customerId) {
            $orderMetrics[(int) $customerId] = array(
                'last_order_date' => '',
                'total_order' => 0,
                'total_spent' => 0.0,
            );
        }

        if ($orderTable !== '' && campaignTableExists($orderConn, $orderTable)) {
            $orderRows = campaignRuleFetchActiveRows($orderConn, $orderTable);
            $dateColumn = campaignRuleResolveOrderDateColumn($connect, $financeConnect, $config['label'] ?? ucfirst($platformKey), $orderConn, $orderTable);
            $amountColumn = campaignRuleResolveOrderAmountColumn($connect, $financeConnect, $config['label'] ?? ucfirst($platformKey), $orderConn, $orderTable);

            foreach ($orderRows as $orderRow) {
                if (function_exists('customerLabelIsExcludedOrder') && customerLabelIsExcludedOrder($orderRow)) {
                    continue;
                }

                $customerId = function_exists('customerLabelResolveOrderCustomerId')
                    ? (int) customerLabelResolveOrderCustomerId($platformKey, $orderRow, $customerIndexes)
                    : 0;
                if ($customerId <= 0 || !isset($orderMetrics[$customerId])) {
                    continue;
                }

                $orderMetrics[$customerId]['total_order']++;
                if ($amountColumn !== '') {
                    $orderMetrics[$customerId]['total_spent'] += (float) ($orderRow[$amountColumn] ?? 0);
                }

                $orderDate = $dateColumn !== '' ? campaignDateValue($orderRow[$dateColumn] ?? '') : '';
                if ($orderDate !== '' && ($orderMetrics[$customerId]['last_order_date'] === '' || $orderDate > $orderMetrics[$customerId]['last_order_date'])) {
                    $orderMetrics[$customerId]['last_order_date'] = $orderDate;
                }
            }
        }

        $matched = array();
        foreach ($customerIndexes['rows_by_id'] as $customerId => $customerRow) {
            $sourceCustomerId = trim((string) ($customerRow['id'] ?? ''));
            if ($sourceCustomerId === '') {
                continue;
            }

            $candidate = array(
                'platform' => ucfirst($platformKey),
                'platform_key' => $platformKey,
                'customer_id' => $sourceCustomerId,
                'customer_name' => campaignRuleResolveCustomerDisplayName($platformKey, $customerRow, $sourceCustomerId),
                'customer_contact' => campaignRuleResolveCustomerContact($platformKey, $customerRow),
                'brand_id' => campaignRuleResolveCustomerBrandId($connect, $customerRow, $seriesLookup),
                'tag_ids' => array_map('intval', array_column((array) ($tagMap[(int) $customerId] ?? array()), 'tag_id')),
                'last_order_date' => $orderMetrics[(int) $customerId]['last_order_date'] ?? '',
                'total_order' => (int) ($orderMetrics[(int) $customerId]['total_order'] ?? 0),
                'total_spent' => (float) ($orderMetrics[(int) $customerId]['total_spent'] ?? 0),
            );

            if (!campaignRuleCustomerMatchesCondition($candidate, $condition)) {
                continue;
            }

            $matched[] = $candidate;
        }

        return $matched;
    }
}

if (!function_exists('campaignRuleBuildMatchedCustomers')) {
    function campaignRuleBuildMatchedCustomers($connect, $financeConnect, $conditionInput = array())
    {
        $condition = is_array($conditionInput) ? $conditionInput : campaignRuleConditionDecodeForUi($conditionInput);
        $selectedPlatforms = campaignRuleConditionSanitizeStringList($condition['platforms'] ?? array(), campaignRuleConditionPlatformOptions());
        if (empty($selectedPlatforms)) {
            $selectedPlatforms = array_keys(campaignRuleConditionPlatformOptions());
        }

        $rows = array();
        foreach ($selectedPlatforms as $platformKey) {
            foreach (campaignRuleBuildPlatformCustomers($connect, $financeConnect, $platformKey, $condition) as $candidate) {
                $rows[] = $candidate;
            }
        }

        return $rows;
    }
}

if (!function_exists('campaignRuleEstimateMatchedCustomers')) {
    function campaignRuleEstimateMatchedCustomers($connect, $financeConnect, $conditionInput = array())
    {
        return count(campaignRuleBuildMatchedCustomers($connect, $financeConnect, $conditionInput));
    }
}

if (!function_exists('campaignRuleSettingFetchRows')) {
    function campaignRuleSettingFetchRows($connect, $filters = array())
    {
        $rows = array();
        if (!($connect instanceof mysqli) || !campaignTableExists($connect, CAMPAIGN_RULE_SETTING)) {
            return $rows;
        }

        $where = array("`status`='A'");

        if (($filters['rule_name'] ?? '') !== '') {
            $where[] = "`rule_name` LIKE '%" . $connect->real_escape_string((string) $filters['rule_name']) . "%'";
        }
        if (($filters['rule_status'] ?? '') !== '') {
            $where[] = "`rule_status`='" . $connect->real_escape_string((string) $filters['rule_status']) . "'";
        }

        $sql = "SELECT * FROM " . campaignTableName(CAMPAIGN_RULE_SETTING) . " WHERE " . implode(' AND ', $where) . " ORDER BY `id` DESC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('campaignRuleSettingUserNames')) {
    function campaignRuleSettingUserNames($userOptions, $ids)
    {
        $names = array();
        $ids = array_map('intval', (array) $ids);
        foreach ($userOptions as $userOption) {
            if (in_array((int) ($userOption['id'] ?? 0), $ids, true)) {
                $names[] = (string) ($userOption['name'] ?? '');
            }
        }

        return implode(', ', array_filter($names, 'strlen'));
    }
}

if (!function_exists('campaignRuleSettingShortcutNames')) {
    function campaignRuleSettingShortcutNames($messageOptions, $items)
    {
        $names = array();
        foreach ((array) $items as $item) {
            $shortcutId = is_array($item) ? (int) ($item['shortcut_id'] ?? 0) : (int) $item;
            foreach ($messageOptions as $messageOption) {
                if ((int) ($messageOption['id'] ?? 0) === $shortcutId) {
                    $names[] = (string) ($messageOption['name'] ?? '');
                    break;
                }
            }
        }

        return implode(', ', array_filter($names, 'strlen'));
    }
}

if (!function_exists('campaignRuleTemplateValue')) {
    function campaignRuleTemplateValue($template, $periodStart = '')
    {
        $time = $periodStart !== '' ? strtotime($periodStart) : time();
        if ($time === false) {
            $time = time();
        }

        $replacements = array(
            '{month}' => date('F', $time),
            '{year}' => date('Y', $time),
            '{week}' => date('W', $time),
        );

        return strtr((string) $template, $replacements);
    }
}

if (!function_exists('campaignRuleResolvePeriod')) {
    function campaignRuleResolvePeriod($rule)
    {
        $periodRule = trim((string) ($rule['campaign_period_rule'] ?? 'Current Month'));
        $today = date('Y-m-d');

        if ($periodRule === 'Next Month') {
            return array(date('Y-m-01', strtotime('first day of next month')), date('Y-m-t', strtotime('first day of next month')));
        }

        if ($periodRule === 'Custom Days') {
            $condition = campaignRuleDecodeJson($rule['customer_condition_json'] ?? '', array());
            $days = isset($condition['period_days']) ? (int) $condition['period_days'] : 30;
            if ($days <= 0) {
                $days = 30;
            }
            return array($today, date('Y-m-d', strtotime('+' . ($days - 1) . ' days')));
        }

        return array(date('Y-m-01'), date('Y-m-t'));
    }
}

if (!function_exists('campaignRuleGeneratedKey')) {
    function campaignRuleGeneratedKey($ruleId, $periodStart, $periodEnd)
    {
        return (int) $ruleId . '|' . campaignDateValue($periodStart) . '|' . campaignDateValue($periodEnd);
    }
}

if (!function_exists('campaignRuleShouldRunToday')) {
    function campaignRuleShouldRunToday($rule, &$note = '')
    {
        $schedule = trim((string) ($rule['generate_schedule'] ?? ''));
        $generateDay = (int) ($rule['generate_day'] ?? 0);
        $todayDay = (int) date('j');

        if ($schedule === 'Daily') {
            return true;
        }

        if ($schedule === 'End Of Month') {
            return date('Y-m-d') === date('Y-m-t');
        }

        if ($schedule === 'Monthly Day') {
            return $generateDay > 0 && $generateDay === $todayDay;
        }

        if ($schedule === 'Monthly') {
            return $todayDay === 1;
        }

        if ($schedule === 'Weekly') {
            $note = 'Weekly rule is skipped unless generate_day is 1-7.';
            if ($generateDay >= 1 && $generateDay <= 7) {
                return (int) date('N') === $generateDay;
            }
            return false;
        }

        return false;
    }
}

if (!function_exists('campaignFetchRuleById')) {
    function campaignFetchRuleById($connect, $ruleId)
    {
        $ruleId = (int) $ruleId;
        if ($ruleId <= 0 || !campaignTableExists($connect, CAMPAIGN_RULE_SETTING)) {
            return array();
        }

        $result = mysqli_query($connect, "SELECT * FROM " . campaignTableName(CAMPAIGN_RULE_SETTING) . " WHERE `id`='" . $ruleId . "' AND `status`='A' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return is_array($row) ? $row : array();
        }

        return array();
    }
}

if (!function_exists('campaignRuleAssignCustomers')) {
    function campaignRuleAssignCustomers($connect, $financeConnect, $campaignId, $rule)
    {
        $assigned = 0;
        $condition = campaignRuleConditionDecodeForUi($rule['customer_condition_json'] ?? '');
        $userId = campaignCurrentUserId();
        $matchedCustomers = campaignRuleBuildMatchedCustomers($connect, $financeConnect, $condition);

        foreach ($matchedCustomers as $candidate) {
            $platform = trim((string) ($candidate['platform'] ?? ''));
            $customerId = campaignNormalizeTextValue($candidate['customer_id'] ?? '', 255);
            $customerName = campaignNormalizeTextValue($candidate['customer_name'] ?? '', 255);
            if ($platform === '' || $customerId === '' || $customerName === '') {
                continue;
            }

            $safePlatform = $connect->real_escape_string($platform);
            $safeCustomerId = $connect->real_escape_string($customerId);
            $exists = mysqli_query($connect, "SELECT `id` FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE `campaign_id`='" . (int) $campaignId . "' AND `platform`='" . $safePlatform . "' AND `customer_id`='" . $safeCustomerId . "' AND `status`='A' LIMIT 1");
            if ($exists && $exists->num_rows > 0) {
                continue;
            }

            $stmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_CUSTOMER) . " (`campaign_id`,`platform`,`customer_id`,`customer_name`,`customer_contact`,`last_order_date`,`total_order`,`total_spent`,`assign_source`,`purchase_status`,`follow_up_status`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?,?,?,?,'Rule','Pending','Pending',?,CURDATE(),CURTIME(),'A')");
            if ($stmt) {
                $customerContact = campaignNormalizeTextValue($candidate['customer_contact'] ?? '', 255);
                $lastOrderDate = campaignDateValue($candidate['last_order_date'] ?? '');
                $totalOrder = (int) ($candidate['total_order'] ?? 0);
                $totalSpent = (float) ($candidate['total_spent'] ?? 0);
                $stmt->bind_param('isssssids', $campaignId, $platform, $customerId, $customerName, $customerContact, $lastOrderDate, $totalOrder, $totalSpent, $userId);
                if ($stmt->execute()) {
                    $assigned++;
                }
                $stmt->close();
            }
        }

        return $assigned;
    }
}


if (!function_exists('campaignSyncFollowUpTasks')) {
    function campaignSyncFollowUpTasks($connect, $campaignId)
    {
        $campaignId = (int) $campaignId;
        $created = 0;
        $updated = 0;
        $deactivated = 0;
        if ($campaignId <= 0) {
            return array('created' => 0, 'updated' => 0, 'deactivated' => 0);
        }

        $userId = campaignCurrentUserId();
        $picIds = array();
        $picResult = mysqli_query($connect, "SELECT `user_id` FROM " . campaignTableName(CAMPAIGN_PIC) . " WHERE `campaign_id`='" . $campaignId . "' AND `status`='A' ORDER BY `id` ASC");
        if ($picResult) {
            while ($row = $picResult->fetch_assoc()) {
                $picId = (int) ($row['user_id'] ?? 0);
                if ($picId > 0) {
                    $picIds[] = $picId;
                }
            }
        }

        $customerIds = array();
        $customerResult = mysqli_query($connect, "SELECT `id` FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . " WHERE `campaign_id`='" . $campaignId . "' AND `status`='A' ORDER BY `id` ASC");
        if ($customerResult) {
            while ($row = $customerResult->fetch_assoc()) {
                $customerId = (int) ($row['id'] ?? 0);
                if ($customerId > 0) {
                    $customerIds[] = $customerId;
                }
            }
        }

        $messageRows = array();
        $messageResult = mysqli_query($connect, "SELECT `id`, `follow_up_date` FROM " . campaignTableName(CAMPAIGN_MESSAGE) . " WHERE `campaign_id`='" . $campaignId . "' AND `status`='A'  ORDER BY `sequence_no` ASC, `id` ASC");
        if ($messageResult) {
            while ($row = $messageResult->fetch_assoc()) {
                if ((int) ($row['id'] ?? 0) > 0 && campaignDateValue($row['follow_up_date'] ?? '') !== '') {
                    $messageRows[] = $row;
                }
            }
        }

        $existingTasks = array();
        $taskResult = mysqli_query($connect, "SELECT `id`, `campaign_customer_id`, `campaign_message_id`, `pic_user_id`, `follow_up_date` FROM " . campaignTableName(CAMPAIGN_FOLLOW_UP) . " WHERE `campaign_id`='" . $campaignId . "' AND `status`='A'");
        if ($taskResult) {
            while ($row = $taskResult->fetch_assoc()) {
                $existingTasks[(int) ($row['campaign_customer_id'] ?? 0) . ':' . (int) ($row['campaign_message_id'] ?? 0)] = $row;
            }
        }

        $validPairs = array();
        $picIndex = 0;
        $insertStmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_FOLLOW_UP) . " (`campaign_id`,`campaign_customer_id`,`campaign_message_id`,`pic_user_id`,`follow_up_date`,`follow_up_status`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?,'Pending',?,CURDATE(),CURTIME(),'A')");
        $updateStmt = $connect->prepare("UPDATE " . campaignTableName(CAMPAIGN_FOLLOW_UP) . " SET `pic_user_id`=?, `follow_up_date`=?, `notification_sent`='N', `notification_sent_date`=NULL, `notification_sent_time`=NULL, `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=?");
        $deactivateStmt = $connect->prepare("UPDATE " . campaignTableName(CAMPAIGN_FOLLOW_UP) . " SET `status`='D', `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=?");

        foreach ($messageRows as $messageRow) {
            $messageId = (int) ($messageRow['id'] ?? 0);
            $followUpDate = campaignDateValue($messageRow['follow_up_date'] ?? '');
            foreach ($customerIds as $customerId) {
                $pairKey = $customerId . ':' . $messageId;
                $validPairs[$pairKey] = true;
                $picUserId = !empty($picIds) ? $picIds[$picIndex % count($picIds)] : 0;
                $picIndex++;

                if (isset($existingTasks[$pairKey])) {
                    $taskRow = $existingTasks[$pairKey];
                    if ($updateStmt && ((int) ($taskRow['pic_user_id'] ?? 0) !== $picUserId || (string) ($taskRow['follow_up_date'] ?? '') !== $followUpDate)) {
                        $taskId = (int) ($taskRow['id'] ?? 0);
                        $updateStmt->bind_param('issi', $picUserId, $followUpDate, $userId, $taskId);
                        if ($updateStmt->execute()) {
                            $updated++;
                        }
                    }
                    continue;
                }

                if ($insertStmt) {
                    $insertStmt->bind_param('iiiiss', $campaignId, $customerId, $messageId, $picUserId, $followUpDate, $userId);
                    if ($insertStmt->execute()) {
                        $created++;
                    }
                }
            }
        }

        foreach ($existingTasks as $pairKey => $taskRow) {
            if (isset($validPairs[$pairKey])) {
                continue;
            }
            if ($deactivateStmt) {
                $taskId = (int) ($taskRow['id'] ?? 0);
                $deactivateStmt->bind_param('si', $userId, $taskId);
                if ($deactivateStmt->execute()) {
                    $deactivated++;
                }
            }
        }

        if ($insertStmt) $insertStmt->close();
        if ($updateStmt) $updateStmt->close();
        if ($deactivateStmt) $deactivateStmt->close();

        return array('created' => $created, 'updated' => $updated, 'deactivated' => $deactivated);
    }
}
if (!function_exists('campaignRuleGenerateCampaign')) {
    function campaignRuleGenerateCampaign($connect, $financeConnect, $ruleId, $force = true)
    {
        $result = array('ok' => 0, 'message' => '', 'campaign_id' => 0, 'generated_key' => '', 'assigned' => 0);
        $rule = campaignFetchRuleById($connect, $ruleId);
        if (empty($rule)) {
            $result['message'] = 'Rule not found.';
            return $result;
        }

        list($periodStart, $periodEnd) = campaignRuleResolvePeriod($rule);
        $generatedKey = campaignRuleGeneratedKey($ruleId, $periodStart, $periodEnd);
        $result['generated_key'] = $generatedKey;
        $safeKey = $connect->real_escape_string($generatedKey);
        $dup = mysqli_query($connect, "SELECT `campaign_id` FROM " . campaignTableName(CAMPAIGN_RULE_GENERATED_LOG) . " WHERE `generated_key`='" . $safeKey . "' AND `status`='A' LIMIT 1");
        if ($dup && $dup->num_rows > 0) {
            $dupRow = $dup->fetch_assoc();
            $result['campaign_id'] = (int) ($dupRow['campaign_id'] ?? 0);
            $result['message'] = 'Campaign already generated for this rule period.';
            return $result;
        }

        $userId = campaignCurrentUserId();
        $campaignName = campaignRuleTemplateValue($rule['campaign_name_template'] ?? 'Campaign {month} {year}', $periodStart);
        $description = 'Auto generated from campaign rule: ' . ($rule['rule_name'] ?? '');
        $stmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN) . " (`campaign_name`,`period_start_date`,`period_end_date`,`rule_setting_id`,`description`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?, ?,CURDATE(),CURTIME(),'A')");
        if (!$stmt) {
            $result['message'] = 'Unable to prepare campaign insert.';
            return $result;
        }

        $stmt->bind_param('sssiss', $campaignName, $periodStart, $periodEnd, $ruleId, $description, $userId);
        if (!$stmt->execute()) {
            $result['message'] = 'Unable to create campaign.';
            $stmt->close();
            return $result;
        }
        $campaignId = (int) $stmt->insert_id;
        $stmt->close();

        $picIds = campaignRuleDecodeJson($rule['default_pic_json'] ?? '', array());
        $picStmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_PIC) . " (`campaign_id`,`user_id`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,CURDATE(),CURTIME(),'A')");
        if ($picStmt) {
            foreach ((array) $picIds as $picId) {
                $picId = (int) $picId;
                if ($picId <= 0) {
                    continue;
                }
                $picStmt->bind_param('iis', $campaignId, $picId, $userId);
                $picStmt->execute();
            }
            $picStmt->close();
        }

        $shortcutOptions = campaignFetchMessageShortcutOptions($connect);
        $defaultMessages = campaignRuleDecodeJson($rule['default_message_json'] ?? '', array());
        $messageStmt = $connect->prepare("INSERT INTO " . campaignTableName(CAMPAIGN_MESSAGE) . " (`campaign_id`,`message_shortcut_id`,`message_title`,`message_preview`,`follow_up_date`,`sequence_no`,`create_by`,`create_date`,`create_time`,`status`) VALUES (?,?,?,?,?,?,?,CURDATE(),CURTIME(),'A')");
        if ($messageStmt) {
            $sequenceNo = 1;
            foreach ((array) $defaultMessages as $messageItem) {
                $shortcutId = is_array($messageItem) ? (int) ($messageItem['shortcut_id'] ?? 0) : (int) $messageItem;
                $shortcut = campaignLookupShortcutById($shortcutOptions, $shortcutId);
                $title = campaignNormalizeTextValue($shortcut['name'] ?? ('Message ' . $sequenceNo), 255);
                $preview = campaignNormalizeTextValue($shortcut['preview'] ?? '', 65535);
                $followUpDate = date('Y-m-d', strtotime($periodStart . ' +' . ($sequenceNo - 1) . ' days'));
                $messageStmt->bind_param('iisssis', $campaignId, $shortcutId, $title, $preview, $followUpDate, $sequenceNo, $userId);
                $messageStmt->execute();
                $sequenceNo++;
            }
            $messageStmt->close();
        }

        $assigned = campaignRuleAssignCustomers($connect, $financeConnect, $campaignId, $rule);
        if (function_exists('campaignSyncFollowUpTasks')) {
            campaignSyncFollowUpTasks($connect, $campaignId);
        } else if (function_exists('campaignMessageSyncFollowUpTasks')) {
            campaignMessageSyncFollowUpTasks($connect, $campaignId);
        }

        $safeGeneratedKey = $connect->real_escape_string($generatedKey);
        $safeRemark = $connect->real_escape_string('Generated campaign ' . $campaignName . '. Assigned customers: ' . $assigned);
        mysqli_query($connect, "INSERT INTO " . campaignTableName(CAMPAIGN_RULE_GENERATED_LOG) . " (`rule_setting_id`,`campaign_id`,`generated_key`,`generated_date`,`generated_time`,`remark`,`create_by`,`create_date`,`create_time`,`status`) VALUES ('" . (int) $ruleId . "','" . $campaignId . "','" . $safeGeneratedKey . "',CURDATE(),CURTIME(),'" . $safeRemark . "','" . $connect->real_escape_string($userId) . "',CURDATE(),CURTIME(),'A')");
        mysqli_query($connect, "UPDATE " . campaignTableName(CAMPAIGN_RULE_SETTING) . " SET `last_generated_date`=CURDATE(), `last_generated_time`=CURTIME(), `update_by`='" . $connect->real_escape_string($userId) . "', `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`='" . (int) $ruleId . "'");

        $result['ok'] = 1;
        $result['message'] = 'Campaign generated successfully.';
        $result['campaign_id'] = $campaignId;
        $result['assigned'] = $assigned;
        return $result;
    }
}

if (!function_exists('campaignCreateFollowUpNotification')) {
    function campaignCreateFollowUpNotification($connect, $taskRow)
    {
        if (function_exists('systemAlertCreateCampaignFollowUpAlert')) {
            return systemAlertCreateCampaignFollowUpAlert($connect, $taskRow) > 0;
        }

        return false;
    }
}

if (!function_exists('campaignPrepareFinalCustomerTag')) {
    function campaignPrepareFinalCustomerTag($connect, $campaignName, $isPurchased)
    {
        $tagName = ($isPurchased ? 'Purchased-' : 'Fail-order-') . campaignNormalizeTextValue($campaignName, 120);
        if (!defined('TAG') || !campaignTableExists($connect, TAG)) {
            return array('tag_name' => $tagName, 'tag_id' => 0, 'applied' => false);
        }

        if (function_exists('customerTagFindTagByName')) {
            $existingTag = customerTagFindTagByName($connect, $tagName);
            if (is_array($existingTag) && (int) ($existingTag['id'] ?? 0) > 0) {
                $tagId = (int) ($existingTag['id'] ?? 0);
                $tagStatus = trim((string) ($existingTag['status'] ?? ''));
                if ($tagStatus === 'A') {
                    return array('tag_name' => $tagName, 'tag_id' => $tagId, 'applied' => false);
                }

                $userId = campaignCurrentUserId();
                mysqli_query($connect, "UPDATE " . campaignTableName(TAG) . " SET `status`='A', `update_by`='" . $connect->real_escape_string($userId) . "', `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`='" . $tagId . "'");
                return array('tag_name' => $tagName, 'tag_id' => $tagId, 'applied' => false);
            }
        }

        $safeTag = $connect->real_escape_string($tagName);
        $userId = campaignCurrentUserId();
        mysqli_query($connect, "INSERT INTO " . campaignTableName(TAG) . " (`name`,`remark`,`create_by`,`create_date`,`create_time`,`status`) VALUES ('" . $safeTag . "','Campaign final tag','" . $connect->real_escape_string($userId) . "',CURDATE(),CURTIME(),'A')");
        return array('tag_name' => $tagName, 'tag_id' => (int) mysqli_insert_id($connect), 'applied' => false);
    }
}

if (!function_exists('campaignApplyFinalCustomerTags')) {
    function campaignApplyFinalCustomerTags($connect, $campaignId, $purchasedTag, $failedTag)
    {
        $summary = array('assigned' => 0, 'skipped' => 0);
        $campaignId = (int) $campaignId;
        $purchasedTagId = (int) ($purchasedTag['tag_id'] ?? 0);
        $failedTagId = (int) ($failedTag['tag_id'] ?? 0);

        if (
            $campaignId <= 0
            || $purchasedTagId <= 0
            || $failedTagId <= 0
            || !function_exists('customerTagAssignToCustomer')
            || !function_exists('customerTagRemoveFromCustomer')
            || !function_exists('customerTagTableExists')
            || !customerTagTableExists($connect)
        ) {
            return $summary;
        }

        $sql = "SELECT `id`, `platform`, `customer_id`, `purchase_status`
                FROM " . campaignTableName(CAMPAIGN_CUSTOMER) . "
                WHERE `campaign_id`='" . $campaignId . "' AND `status`='A'";
        $result = mysqli_query($connect, $sql);
        if (!$result instanceof mysqli_result) {
            return $summary;
        }

        while ($row = $result->fetch_assoc()) {
            $platform = strtolower(trim((string) ($row['platform'] ?? '')));
            $customerId = (int) ($row['customer_id'] ?? 0);
            $purchaseStatus = trim((string) ($row['purchase_status'] ?? ''));
            $isPurchased = strcasecmp($purchaseStatus, 'Purchased') === 0;
            $assignTagId = $isPurchased ? $purchasedTagId : $failedTagId;
            $removeTagId = $isPurchased ? $failedTagId : $purchasedTagId;

            if ($platform === '' || $customerId <= 0 || $assignTagId <= 0) {
                $summary['skipped']++;
                continue;
            }

            $assignResult = customerTagAssignToCustomer($connect, $platform, $customerId, $assignTagId);
            if (!empty($assignResult['success']) && empty($assignResult['already_active'])) {
                $summary['assigned']++;
            } elseif (empty($assignResult['success'])) {
                $summary['skipped']++;
                continue;
            }

            if ($removeTagId > 0) {
                customerTagRemoveFromCustomer($connect, $platform, $customerId, $removeTagId);
            }
        }

        return $summary;
    }
}

if (!function_exists('campaignFinalizeEndedCampaign')) {
    function campaignFinalizeEndedCampaign($connect, $financeConnect, $campaignId)
    {
        $campaign = campaignFetchCampaign($connect, $campaignId);
        if (empty($campaign)) {
            return array('ok' => 0, 'message' => 'Campaign not found.');
        }

        campaignRunPurchaseCheck($connect, $financeConnect, $campaignId);
        $campaignName = (string) ($campaign['campaign_name'] ?? 'Campaign');
        $purchasedTag = campaignPrepareFinalCustomerTag($connect, $campaignName, true);
        $failedTag = campaignPrepareFinalCustomerTag($connect, $campaignName, false);
        $tagSummary = campaignApplyFinalCustomerTags($connect, $campaignId, $purchasedTag, $failedTag);

        $userId = campaignCurrentUserId();
        $safeUser = $connect->real_escape_string($userId);
        $updateParts = array(
            "`update_by`='" . $safeUser . "'",
            "`update_date`=CURDATE()",
            "`update_time`=CURTIME()",
        );
        if (campaignColumnExists($connect, CAMPAIGN, 'campaign_status')) {
            array_unshift($updateParts, "`campaign_status`='Completed'");
        }
        mysqli_query($connect, "UPDATE " . campaignTableName(CAMPAIGN) . " SET " . implode(', ', $updateParts) . " WHERE `id`='" . (int) $campaignId . "' AND `status`='A'");

        return array(
            'ok' => 1,
            'message' => 'Campaign finalized. Assigned final tag to ' . (int) ($tagSummary['assigned'] ?? 0) . ' customer(s).',
            'purchased_tag' => $purchasedTag,
            'failed_tag' => $failedTag,
            'tag_summary' => $tagSummary,
        );
    }
}

?>
