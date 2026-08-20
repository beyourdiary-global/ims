<?php

if (defined('ROOT')) {
    include_once ROOT . '/include/customer_tag.php';
}

if (!function_exists('campaign2H')) {
    function campaign2H($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('campaign2Json')) {
    function campaign2Json($value)
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

if (!function_exists('campaign2CsrfToken')) {
    function campaign2CsrfToken($key)
    {
        $key = 'campaign2_csrf_' . preg_replace('/[^a-z0-9_]/i', '_', (string) $key);
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$key];
    }
}

if (!function_exists('campaign2VerifyCsrf')) {
    function campaign2VerifyCsrf($key, $token)
    {
        return hash_equals(campaign2CsrfToken($key), (string) $token);
    }
}

if (!function_exists('campaign2TableExists')) {
    function campaign2TableExists($conn, $tblName)
    {
        if (!($conn instanceof mysqli) || trim((string) $tblName) === '') {
            return false;
        }
        $safeTable = $conn->real_escape_string((string) $tblName);
        $result = $conn->query("SHOW TABLES LIKE '" . $safeTable . "'");
        return ($result && $result->num_rows > 0);
    }
}

if (!function_exists('campaign2ColumnExists')) {
    function campaign2ColumnExists($conn, $tblName, $columnName)
    {
        if (!campaign2TableExists($conn, $tblName)) {
            return false;
        }
        $safeColumn = $conn->real_escape_string((string) $columnName);
        $result = $conn->query("SHOW COLUMNS FROM `" . str_replace('`', '``', (string) $tblName) . "` LIKE '" . $safeColumn . "'");
        return ($result && $result->num_rows > 0);
    }
}

if (!function_exists('campaign2FetchCampaign')) {
    function campaign2FetchCampaign($connect, $campaignId)
    {
        $campaignId = (int) $campaignId;
        if ($campaignId <= 0) {
            return array();
        }

        if (!defined('CAMPAIGN2') || !campaign2TableExists($connect, CAMPAIGN2)) {
            return array();
        }

        $stmt = $connect->prepare("SELECT * FROM `" . CAMPAIGN2 . "` WHERE `id` = ? AND `status` = 'A' LIMIT 1");
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

if (!function_exists('campaign2IsPeriodEnded')) {
    function campaign2IsPeriodEnded($campaign)
    {
        if (empty($campaign) || !is_array($campaign)) {
            return false;
        }

        $periodEndDate = isset($campaign['period_end_date']) ? trim((string) $campaign['period_end_date']) : '';
        if ($periodEndDate === '' || $periodEndDate === '0000-00-00') {
            return false;
        }

        return strtotime($periodEndDate) < strtotime(date('Y-m-d'));
    }
}

if (!function_exists('campaign2DateValue')) {
    function campaign2DateValue($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        $time = strtotime($value);
        return $time === false ? '' : date('Y-m-d', $time);
    }
}

if (!function_exists('campaign2Audit')) {
    function campaign2Audit($connect, $pageTitle, $action, $message, $query = '', $table = '')
    {
        audit_log(array(
            'log_act' => $action,
            'cdate' => date_dis,
            'ctime' => time_dis,
            'uid' => USER_ID,
            'cby' => USER_ID,
            'act_msg' => $message,
            'query_rec' => $query,
            'query_table' => $table !== '' ? $table : CAMPAIGN2,
            'page' => $pageTitle,
            'connect' => $connect,
        ));
    }
}

if (!function_exists('campaign2SetPopup')) {
    function campaign2SetPopup($message, $returnUrl, $act = 'ErrMO')
    {
        $_SESSION['campaign2_popup'] = array(
            'message' => (string) $message,
            'return_url' => (string) $returnUrl,
            'act' => (string) $act,
        );
    }
}

if (!function_exists('campaign2RenderPopupScript')) {
    function campaign2RenderPopupScript($pageTitle, $defaultReturnUrl)
    {
        if (empty($_SESSION['campaign2_popup']) || !is_array($_SESSION['campaign2_popup'])) {
            return;
        }

        $popup = $_SESSION['campaign2_popup'];
        unset($_SESSION['campaign2_popup']);

        $message = isset($popup['message']) ? (string) $popup['message'] : '';
        $returnUrl = isset($popup['return_url']) && $popup['return_url'] !== '' ? (string) $popup['return_url'] : (string) $defaultReturnUrl;
        $act = isset($popup['act']) && $popup['act'] !== '' ? (string) $popup['act'] : 'ErrMO';

        echo '<script>confirmationDialog("", ' . campaign2Json($message) . ', ' . campaign2Json($pageTitle) . ', "", ' . campaign2Json($returnUrl) . ', ' . campaign2Json($act) . ');</script>';
    }
}

if (!function_exists('campaign2FetchUsers')) {
    function campaign2FetchUsers($connect)
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

if (!function_exists('campaign2BuildTagName')) {
    function campaign2BuildTagName($campaignName, $followUpDate, $tagType = 'Notify')
    {
        $campaignName = trim((string) $campaignName);
        $followUpDate = trim((string) $followUpDate);
        $tagType = in_array($tagType, array('Notify', 'Fail'), true) ? $tagType : 'Notify';

        if ($campaignName === '' || $followUpDate === '') {
            return '';
        }

        return $campaignName . ' | ' . $followUpDate . ' | ' . $tagType;
    }
}

if (!function_exists('campaign2EnsureAndAssignTag')) {
    function campaign2EnsureAndAssignTag($connect, $platform, $customerId, $campaignName, $followUpDate, $tagType = 'Notify')
    {
        $tagName = campaign2BuildTagName($campaignName, $followUpDate, $tagType);
        if ($tagName === '') {
            return false;
        }

        $existingTag = customerTagFindTagByName($connect, $tagName);
        if (!empty($existingTag)) {
            $tagId = (int) ($existingTag['id'] ?? 0);
        } else {
            $stmt = $connect->prepare("INSERT INTO `tag` (`name`, `remark`, `create_by`, `create_date`, `create_time`, `status`) VALUES (?, ?, ?, CURDATE(), CURTIME(), 'A')");
            if (!$stmt) {
                return false;
            }

            $remark = $tagType === 'Notify' ? 'Follow-up completed' : 'Follow-up overdue/failed';
            $createBy = (string) USER_ID;
            $stmt->bind_param('sss', $tagName, $remark, $createBy);
            if (!$stmt->execute()) {
                $stmt->close();
                return false;
            }

            $tagId = (int) $connect->insert_id;
            $stmt->close();
        }

        if ($tagId <= 0) {
            return false;
        }

        $result = customerTagAssignToCustomer($connect, $platform, $customerId, $tagId);
        return isset($result['success']) || isset($result['already_active']);
    }
}

if (!function_exists('campaign2UploadAttachment')) {
    function campaign2UploadAttachment($fieldName, $campaignName, $followUpDate, $customerName)
    {
        if (!isset($_FILES[$fieldName])) {
            return '';
        }

        $file = $_FILES[$fieldName];

        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return '';
        }

        $allowedExtensions = array('jpg', 'jpeg', 'png', 'webp', 'pdf');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            return '';
        }

        $campaignName = preg_replace('/[\\\\\/:\*\?"<>|]/', '', $campaignName);
        $campaignName = preg_replace('/\s+/', ' ', trim($campaignName));
        $followUpDate = preg_replace('/[\\\\\/:\*\?"<>|]/', '', $followUpDate);
        $followUpDate = preg_replace('/\s+/', ' ', trim($followUpDate));
        $customerName = preg_replace('/[\\\\\/:\*\?"<>|]/', '', $customerName);
        $customerName = preg_replace('/\s+/', ' ', trim($customerName));

        if ($campaignName === '' || $followUpDate === '' || $customerName === '') {
            return '';
        }

        $folderPath = ROOT . '/attachment/campaign2/' . $campaignName . '/' . $followUpDate . '/' . $customerName;

        if (!is_dir($folderPath)) {
            if (!@mkdir($folderPath, 0777, true)) {
                return '';
            }
        }

        $baseFilename = preg_replace('/\s+/', '_', trim(pathinfo($file['name'], PATHINFO_FILENAME)));
        $targetFilename = $baseFilename . '.' . $ext;
        $targetPath = $folderPath . '/' . $targetFilename;

        $counter = 1;
        while (file_exists($targetPath)) {
            $targetFilename = $baseFilename . '_' . $counter . '.' . $ext;
            $targetPath = $folderPath . '/' . $targetFilename;
            $counter++;
        }

        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            return '';
        }

        return 'attachment/campaign2/' . $campaignName . '/' . $followUpDate . '/' . $customerName . '/' . $targetFilename;
    }
}

if (!function_exists('campaign2NormalizeTextValue')) {
    function campaign2NormalizeTextValue($value, $maxBytes = 65535)
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

if (!function_exists('campaign2RenderAutocompleteScript')) {
    function campaign2RenderAutocompleteScript($configs)
    {
        echo '<script>';
        echo 'window.campaign2AutocompleteConfigs = ' . campaign2Json(array_values($configs)) . ';';
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

            (window.campaign2AutocompleteConfigs || []).forEach(function (config) {
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
