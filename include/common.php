<?php

use PgSql\Lob;

function post($key)
{
	return isset($_POST[$key]) ? $_POST[$key] : '';
}

function postSpaceFilter($key)
{
	if (isset($_POST[$key])) {
		if (is_string($_POST[$key])) {
			return trim(isset($_POST[$key]) ? $_POST[$key] : '');
		} else if (is_array($_POST[$key])) {
			return array_map('trim', isset($_POST[$key]) ? $_POST[$key] : '');
		}
	}
}

function input($key)
{
	$results = '';
	if (isset($_GET[$key]) && !is_array($_GET[$key])) {
		$results = isset($_GET[$key]) && strlen($_GET[$key]) <= 256 ? /* globalSanitizeFilter($_GET[$key], $key) */ $_GET[$key] : '';
	}
	return xssFilter($results);
}


function searchInput($key)
{
	$input = input($key);
	//check the input query string with script tags will return empty string
	if (preg_match("/<script(.*?)>(.*?)<\/script>/is", $input) || preg_match("/<script(.*?)>/is", $input)) {
		return '';
	}
	$input = strip_tags($input);
	return trim(preg_replace('/[^(a-zA-Z0-9.()\-,\/)\&\'\"]+/i', ' ', $input));
}

function numberInput($key)
{
	$val = input($key);
	return isNumber($val) ? $val : '';
}


function isNumber($str)
{
	return preg_match("/^[0-9]+$/", $str);
}

function isEmail($str)
{
	return preg_match("/^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,})$/", $str);
}

function getSelfUrl()
{
	$s = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https' ? 's' : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 's' : '');
	$protocol = strLeft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/") . $s;
	$port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":" . $_SERVER["SERVER_PORT"]);
	$url = $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
	return parse_url($url);
}

function xssFilter($url)
{
	$pattern = "/(<script|<\/script|onstart|onfocus|onerror|onload|onmouseover|iframe|onblur|payload|onmousemove|prompt|\")/i";
	$url = urldecode($url);
	while (preg_match($pattern, $url)) {
		$url = preg_replace($pattern, '', $url);
	}
	return $url;
}

if (!function_exists('addDirToZip')) {
	function addDirToZip($dir, $zip, $basePath)
	{
		$files = scandir($dir);
		foreach ($files as $file) {
			if ($file == '.' || $file == '..') {
				continue;
			}
			$filePath = $dir . $file;
			if (is_file($filePath)) {
				// Add the file to the zip archive with a relative path.
				$relativePath = str_replace($basePath, '', $filePath);
				$zip->addFile($filePath, $relativePath);
			} elseif (is_dir($filePath)) {
				// Add the directory to the zip archive and recurse into it.
				$zip->addEmptyDir(str_replace($basePath, '', $filePath));
				addDirToZip($filePath . '/', $zip, $basePath);
			}
		}
	}
}

if (!function_exists('deleteDir')) {
	function deleteDir($dirPath)
	{
		if (!is_dir($dirPath)) {
			return;
		}
		$files = glob($dirPath . '*', GLOB_MARK);
		foreach ($files as $file) {
			if (is_dir($file)) {
				deleteDir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($dirPath);
	}
}

if (!function_exists('decodePdfStream')) {
    function decodePdfStream($stream)
    {
        $stream = (string) $stream;
        $compressedLength = strlen($stream);
        $maxCompressedSize = 5 * 1024 * 1024; // 5MB
        $maxDecodedSize = 20 * 1024 * 1024;   // 20MB

        if ($compressedLength === 0 || $compressedLength > $maxCompressedSize) {
            return false;
        }

        $decoded = @gzuncompress($stream, $maxDecodedSize);
        if ($decoded !== false && strlen($decoded) <= $maxDecodedSize) {
            return $decoded;
        }

        $decoded = @gzinflate($stream, $maxDecodedSize);
        if ($decoded !== false && strlen($decoded) <= $maxDecodedSize) {
            return $decoded;
        }

        if ($compressedLength > 6) {
            $trimmedStream = substr($stream, 2);
            if (strlen($trimmedStream) <= $maxCompressedSize) {
                $decoded = @gzinflate($trimmedStream, $maxDecodedSize);
                if ($decoded !== false && strlen($decoded) <= $maxDecodedSize) {
                    return $decoded;
                }
            }
        }

        return false;
    }
}

if (!function_exists('commonFormatAmountRm')) {
    function commonFormatAmountRm($val)
    {
        $num = is_numeric($val) ? (float) $val : 0;
        return number_format($num, 2, '.', '');
    }
}

if (!function_exists('financeGenerateTableRow')) {
    function financeGenerateTableRow($config, &$counters)
    {
        $id = isset($config['id']) ? $config['id'] : '';
        $summaryPage = isset($config['summary_page']) ? trim((string) $config['summary_page']) : '';
        $urlParamName = isset($config['url_param_name']) && trim((string) $config['url_param_name']) !== ''
            ? trim((string) $config['url_param_name'])
            : 'ids';
        $cells = isset($config['cells']) ? $config['cells'] : array();
        $amount = isset($config['amount']) ? $config['amount'] : 0;
        $amountDecimals = isset($config['amount_decimals']) ? (int) $config['amount_decimals'] : 2;
        $checkboxClass = isset($config['checkbox_class']) && trim((string) $config['checkbox_class']) !== ''
            ? trim((string) $config['checkbox_class'])
            : 'export';
        $checkboxValue = array_key_exists('checkbox_value', $config) ? $config['checkbox_value'] : $id;
        $idBeforeCheckbox = array_key_exists('id_before_checkbox', $config) ? (bool) $config['id_before_checkbox'] : true;
        $includeHiddenId = array_key_exists('include_hidden_id', $config) ? (bool) $config['include_hidden_id'] : true;

        if (!is_array($cells)) {
            $cells = array($cells);
        }

        $url = $summaryPage !== ''
            ? $summaryPage . '?' . rawurlencode($urlParamName) . '=' . rawurlencode((string) $id)
            : '#';

        $checkboxValueAttr = $checkboxValue === null ? '' : ' value="' . $checkboxValue . '"';

        echo '<tr onclick="window.location=\'' . $url . '\';" style="cursor:pointer;">';

        if ($idBeforeCheckbox && $includeHiddenId) {
            echo '<th class="hideColumn" scope="row">' . $id . '</th>';
        }

        echo ' <th class="text-center"><input type="checkbox" class="' . $checkboxClass . '"' . $checkboxValueAttr . '></th>';

        if (!$idBeforeCheckbox && $includeHiddenId) {
            echo '<th class="hideColumn" scope="row">' . $id . '</th>';
        }

        echo '<th scope="row">' . $counters++ . '</th>';

        foreach ($cells as $cell) {
            echo '<td scope="row">' . $cell . '</td>';
        }

        echo '<td scope="row">' . number_format((float) $amount, $amountDecimals, '.', '') . '</td>';
        echo '</tr>';
    }
}

if (!function_exists('commonResolvePackageNamesFromCsv')) {
    function commonResolvePackageNamesFromCsv($packageCsv, $connect)
    {
        $packageCsv = trim((string) $packageCsv);
        if ($packageCsv === '') {
            return '';
        }

        $packageIds = array_filter(array_map('trim', explode(',', $packageCsv)), function ($v) {
            return $v !== '';
        });

        $numericIds = array();
        foreach ($packageIds as $id) {
            if (ctype_digit((string) $id)) {
                $numericIds[] = (int) $id;
            }
        }

        $numericIds = array_values(array_unique($numericIds));
        if (empty($numericIds)) {
            return $packageCsv;
        }

        $sql = "SELECT id, name FROM " . PKG . " WHERE id IN (" . implode(',', $numericIds) . ")";
        $result = mysqli_query($connect, $sql);

        $idToName = array();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $idToName[(int) $row['id']] = isset($row['name']) ? $row['name'] : '';
            }
        }

        $names = array();
        foreach ($packageIds as $id) {
            if (!ctype_digit((string) $id)) {
                continue;
            }
            $intId = (int) $id;
            if (isset($idToName[$intId]) && $idToName[$intId] !== '') {
                $names[] = $idToName[$intId];
            }
        }

        return empty($names) ? $packageCsv : implode(', ', $names);
    }
}

if (!function_exists('commonResolvePaymentMethodName')) {
    function commonResolvePaymentMethodName($payMethodId, $financeConnect)
    {
        $payMethodId = trim((string) $payMethodId);
        if ($payMethodId === '' || !ctype_digit($payMethodId)) {
            return $payMethodId;
        }

        $result = getData('name', "id='" . mysqli_real_escape_string($financeConnect, $payMethodId) . "'", 'LIMIT 1', FIN_PAY_METH, $financeConnect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return isset($row['name']) ? (string) $row['name'] : $payMethodId;
        }

        return $payMethodId;
    }
}

if (!function_exists('commonSafeBackUrl')) {
    function commonSafeBackUrl($url, $fallback)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $fallback;
        }

        // Block dangerous / external-like URL
        if (preg_match('/^(javascript|data|vbscript):/i', $url) || strpos($url, '//') === 0) {
            return $fallback;
        }

        // Allow relative URL
        if (strpos($url, '/') === 0) {
            return $url;
        }

        // Allow same-domain absolute URL
        $siteHost = defined('SITEURL') ? parse_url(SITEURL, PHP_URL_HOST) : '';
        $urlHost = parse_url($url, PHP_URL_HOST);

        return ($siteHost && $urlHost && strtolower($siteHost) === strtolower($urlHost))
            ? $url
            : $fallback;
    }
}

if (!function_exists('commonResolveBackUrl')) {
    function commonResolveBackUrl($fallbackUrl)
    {
        $fallbackUrl = trim((string) $fallbackUrl);
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        return commonSafeBackUrl($referer, $fallbackUrl);
    }
}

if (!function_exists('renderNotificationScript')) {
    function renderNotificationScript($message, $type = 'info', $redirectUrl = '', $delayMs = 1200, $useReplace = false, $reload = false)
    {
        $allowedTypes = array('success', 'error', 'warning', 'info');
        $type = strtolower(trim((string) $type));
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        $messageJson = json_encode((string) $message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $typeJson = json_encode($type, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $redirectJson = json_encode((string) $redirectUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $delayMs = max(0, (int) $delayMs);
        $useReplace = $useReplace ? 'true' : 'false';
        $reload = $reload ? 'true' : 'false';

        echo '<script>(function(){'
            . 'var message=' . $messageJson . ';'
            . 'var type=' . $typeJson . ';'
            . 'var redirectUrl=' . $redirectJson . ';'
            . 'var delayMs=' . $delayMs . ';'
            . 'var useReplace=' . $useReplace . ';'
            . 'var shouldReload=' . $reload . ';'
            . 'function fallbackShowNotification(text, kind){'
                . 'var resolvedText=String(text==null?"":text).trim();'
                . 'if(!resolvedText){return;}'
                . 'var resolvedType=String(kind||"info").toLowerCase();'
                . 'var palette={success:{background:"#d1e7dd",border:"#badbcc",color:"#0f5132"},error:{background:"#f8d7da",border:"#f5c2c7",color:"#842029"},warning:{background:"#fff3cd",border:"#ffecb5",color:"#664d03"},info:{background:"#cff4fc",border:"#b6effb",color:"#055160"}};'
                . 'if(!palette[resolvedType]){resolvedType="info";}'
                . 'var host=document.getElementById("global-notification-host");'
                . 'if(!host){host=document.createElement("div");host.id="global-notification-host";host.setAttribute("aria-live","polite");host.style.position="fixed";host.style.top="16px";host.style.right="16px";host.style.zIndex="1080";host.style.display="flex";host.style.flexDirection="column";host.style.gap="10px";host.style.maxWidth="min(360px, calc(100vw - 32px))";(document.body||document.documentElement).appendChild(host);}'
                . 'var toast=document.createElement("div");'
                . 'toast.setAttribute("role","status");'
                . 'toast.style.background=palette[resolvedType].background;'
                . 'toast.style.border="1px solid "+palette[resolvedType].border;'
                . 'toast.style.borderRadius="10px";'
                . 'toast.style.boxShadow="0 10px 24px rgba(15, 23, 42, 0.14)";'
                . 'toast.style.color=palette[resolvedType].color;'
                . 'toast.style.fontSize="14px";'
                . 'toast.style.fontWeight="600";'
                . 'toast.style.lineHeight="1.4";'
                . 'toast.style.padding="12px 14px";'
                . 'toast.style.opacity="0";'
                . 'toast.style.transform="translateY(-8px)";'
                . 'toast.style.transition="opacity 0.2s ease, transform 0.2s ease";'
                . 'toast.textContent=resolvedText;'
                . 'host.appendChild(toast);'
                . 'window.requestAnimationFrame(function(){toast.style.opacity="1";toast.style.transform="translateY(0)";});'
                . 'window.setTimeout(function(){toast.style.opacity="0";toast.style.transform="translateY(-8px)";window.setTimeout(function(){if(toast.parentNode){toast.parentNode.removeChild(toast);}},220);},3200);'
            . '}'
            . 'var notify=typeof window.showNotification==="function"?window.showNotification:fallbackShowNotification;'
            . 'notify(message,type);'
            . 'if(shouldReload){window.setTimeout(function(){window.location.reload();},delayMs);return;}'
            . 'if(redirectUrl){window.setTimeout(function(){if(useReplace&&typeof window.location.replace==="function"){window.location.replace(redirectUrl);}else{window.location.href=redirectUrl;}},delayMs);}'
        . '})();</script>';
    }
}

if (!function_exists('resolveNotificationType')) {
    function resolveNotificationType($message, $defaultType = 'info')
    {
        $defaultType = strtolower(trim((string) $defaultType));
        if (!in_array($defaultType, array('success', 'error', 'warning', 'info'), true)) {
            $defaultType = 'info';
        }

        $normalized = strtolower(trim((string) $message));
        if ($normalized === '') {
            return $defaultType;
        }

        $successKeywords = array(
            'success',
            'successful',
            'completed',
            'complete',
            'created',
            'updated',
            'saved',
            'imported',
            'sent',
            'added'
        );
        foreach ($successKeywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                return 'success';
            }
        }

        $errorKeywords = array(
            'required',
            'missing',
            'invalid',
            'error',
            'failed',
            'unable',
            'sorry',
            'no permission',
            'security',
            'denied',
            'captcha'
        );
        foreach ($errorKeywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                return 'error';
            }
        }

        $warningKeywords = array(
            'please select',
            'please fill',
            'please wait',
            'please check',
            'refresh the page'
        );
        foreach ($warningKeywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                return 'warning';
            }
        }

        $infoKeywords = array(
            'no record',
            'not found',
            'no selected'
        );
        foreach ($infoKeywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                return 'info';
            }
        }

        return $defaultType;
    }
}

function redirect($addr, $alert = '')
{
	global $siteOrlocalMode;
	if ($alert)
		$_SESSION['global_flash_alert'] = $alert;

	$url = $addr;
	if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
		$url = str_ireplace(array('http://', 'https://'), '', $url);
		if (!preg_match('/^[a-z0-9-_]+\.(beyourdiary)\.com/i', $url) && $siteOrlocalMode)
			$addr = SITEURL;
	}
	header("Location:" . $addr);
	exit();
}

function myCurl($url, $ops = array())
{
	if (stripos($url, 'https://uat.cms.beyourdiary') !== false) {
		$url = str_replace('https://', 'http://', $url);
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);

	if (!array_key_exists('CURLOPT_RETURNTRANSFER', $ops))
		$ops['CURLOPT_RETURNTRANSFER'] = true;
	if (!array_key_exists('CURLOPT_CONNECTTIMEOUT', $ops))
		$ops['CURLOPT_CONNECTTIMEOUT'] = 1;
	if (!array_key_exists('CURLOPT_TIMEOUT', $ops))
		$ops['CURLOPT_TIMEOUT'] = 1;

	foreach ($ops as $op => $val) {
		curl_setopt($ch, constant($op), $val);
	}

	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}

function generateShortURL($url)
{
	$url = urlencode($url);
	$ch = curl_init();
	$timeout = 5;
	$result = '';
	curl_setopt($ch, CURLOPT_URL, 'http://api.bit.ly/v3/shorten?login=paustina&apiKey=' . BITLYKEY . '&uri=' . $url . '&format=txt');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	// Execute the post
	$result = curl_exec($ch);
	// Close the connection
	curl_close($ch);
	// Return the result

	return $result;
}

function googleShortURL($longurl)
{
	$data = array('dynamicLinkInfo' => array('dynamicLinkDomain' => 'beyourdiary.co', 'link' => $longurl));
	$data_string = json_encode($data);

	$ch = curl_init('https://firebasedynamiclinks.googleapis.com/v1/shortLinks?key=' . fbaseURLSHORTERNER);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt(
		$ch,
		CURLOPT_HTTPHEADER,
		array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($data_string)
		)
	);

	$result = curl_exec($ch);

	$decodeResult = json_decode($result);
	$firebaseShortURL = isset($decodeResult->shortLink) ? $decodeResult->shortLink : '';

	return $firebaseShortURL;
}

function strLeft($s1, $s2)
{
	return substr($s1, 0, strpos($s1, $s2));
}

function shippingDetail_Curl($ship_action, $postparam)
{
	global $curl_ship_domain;
	$return = '';
	$url = $curl_ship_domain . $ship_action;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postparam));
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	ob_start();
	$return = curl_exec($ch);
	ob_end_clean();
	curl_close($ch);
	return $return;
}

function convertobj($element)
{
	$return_arr = '';
	foreach ($element as $x => $value) {
		$return_arr[] = $value;
	}
	return $return_arr;
}

function convertCapitalCase($param)
{
	return trim(strtoupper($param));
}

function convertSmallerCase($param)
{
	return trim(strtolower($param));
}

function convertFirstEachWordCap($param)
{
	return trim(ucwords($param));
}

function isStatusFieldAvailable($tbl, $conn)
{
	$query = "SHOW COLUMNS FROM $tbl LIKE 'status'";
	$result = $conn->query($query);

	return $result && $result->num_rows > 0;
}

function isDuplicateRecord($fieldName, $fieldValue, $tbl, $connect, $primaryKeyValue)
{
	if ($fieldValue !== null) {
		$safeFieldValue = mysqli_real_escape_string($connect, (string) $fieldValue);
		$query = "SELECT COUNT(*) as count FROM `$tbl` WHERE `$fieldName` = '$safeFieldValue' AND `status` = 'A'";
		//Help to check the query where wrong
		// If editing an existing record, exclude the current record from the duplicate check
		if ($primaryKeyValue) {
			$safePrimaryKeyValue = mysqli_real_escape_string($connect, (string) $primaryKeyValue);
			$query .= " AND id != '$safePrimaryKeyValue'";
		}

		$result = mysqli_query($connect, $query);

		if ($result) {
			$row = mysqli_fetch_assoc($result);
			$count = $row['count'];
			return $count > 0; // If count is greater than 0, it's a duplicate
		}
	}

	return false;
}

//example: isDuplicateRecordWithConditions(['month', 'year'], [$btb_month, $btb_year], $tblName, $finance_connect, $dataId)
function isDuplicateRecordWithConditions($fields, $values, $tbl, $connect, $primaryKeyValue)
{
	if (count($fields) !== count($values) || empty($fields)) {
		return false; // Invalid input, return false
	}

	$conditions = array_combine($fields, $values);
	$conditions += ['status' => 'A']; // Add 'status' condition
	if ($primaryKeyValue)
		$conditions['id !='] = $primaryKeyValue; // Exclude current record if editing

    $whereParts = array();
    foreach ($conditions as $key => $value) {
        $operator = '=';
        $field = trim((string) $key);

        if (preg_match('/^(.+?)\s*(>=|<=|!=|<>|=|>|<)$/', $field, $matches)) {
            $field = trim((string) $matches[1]);
            $operator = $matches[2];
        }

        $whereParts[] = "`$field` $operator '" . mysqli_real_escape_string($connect, (string) $value) . "'";
    }

    $whereClause = implode(' AND ', $whereParts);

	$query = "SELECT COUNT(*) as count FROM `$tbl` WHERE $whereClause";
	$result = mysqli_query($connect, $query);

	return $result && mysqli_fetch_assoc($result)['count'] > 0;
}

function isRecordExist($tblName, $idType, $id, $connect)
{
	$idType = mysqli_real_escape_string($connect, $idType);
	$id = mysqli_real_escape_string($connect, $id);

	$query = "SELECT COUNT(*) AS record_count FROM $tblName WHERE $idType = '$id'";
	$result = mysqli_query($connect, $query);

	if ($result) {
		$row = mysqli_fetch_assoc($result);
		$recordCount = $row['record_count'];

		return $recordCount > 0;
	} else {
		return false;
	}
}


function tableExists($tblName, $conn)
{
	if (!$conn) {
		die("Database connection is not initialized.");
	}
	$result = $conn->query("SHOW TABLES LIKE '$tblName'");
	if (!$result)
		return;
	return $result && $result->num_rows > 0;
}

function getData($search_val, $val, $val2, $tbl, $conn)
{
	if (!tableExists($tbl, $conn)) {
		return false;
	} else {
		$statusAvailable = isStatusFieldAvailable($tbl, $conn);

		// WHERE clause
		if ($statusAvailable) {
			$chk_val = $val == '' ? "WHERE status = 'A' " : "WHERE status = 'A' AND $val ";
		} else {
			$chk_val = $val == '' ? "" : "WHERE $val";
		}

		// Build base query
		$query = "SELECT $search_val FROM $tbl $chk_val";

		// Check if $val2 contains a GROUP BY
		if (stripos($val2, "GROUP BY") !== false) {
			$query .= " $val2 ORDER BY id DESC";
		} else {
			$query .= " ORDER BY id DESC $val2";
		}

		// Execute
		$result = $conn->query($query);
    	 if (!$result) {
            error_log("Query failed: $query - Error: " . mysqli_error($conn));
            echo $query;exit;
        }
	}

	if (empty($result) || !is_object($result) || $result->num_rows == 0) {
		return false;
	} else {
		return $result;
	}
}

if (!function_exists('getPinGroupNameById')) {
    function getPinGroupNameById($connect, $pinGroupId)
    {
        $pinGroupId = (int) $pinGroupId;
        if ($pinGroupId <= 0) {
            return '';
        }

        $result = getData('name', "id = '$pinGroupId'", 'LIMIT 1', 'pin_group', $connect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (isset($row['name']) && trim((string) $row['name']) !== '') {
                return (string) $row['name'];
            }
        }

        return '';
    }
}


function generateDBData($tblname, $conn)
{
	$result = getData('*', '', '', $tblname, $conn);

	// Check if $result is a valid result set
	if ($result === false) {
		// Log the error or output debug information
		error_log("Error in getData() for table $tblname: " . $conn->error);
		return;
		// die("Failed to fetch data from table $tblname");

	}

	$data = array();
	while ($row = $result->fetch_assoc()) {
		$data[] = $row;
	}

	$encode_rst = json_encode($data);

	$path = ROOT . "/data/$tblname.json";

	$f = fopen($path, 'w');
	if ($f === false) {
		die("Failed to open file for writing: $path");
	}

	fwrite($f, $encode_rst);
	fclose($f);
}

function get_allowed_audit_actions($key = null) {
    $actions = [
        'view'     => 1,
        'edit'     => 2,
        'delete'   => 3,
        'add'      => 4,
        'import'   => 5,
        'export'   => 6,
        'login'    => 7,
        'logout'   => 8,
        'check'    => 9,
        'approval' => 10,
        'declined' => 11,
        'cancel'   => 12,
        'search'   => 13,
        'reset'    => 14,
    ];

    // If no key is passed, just return the full map
    if ($key === null) {
        return $actions;
    }

    // If the key is a string (action name), return the corresponding number
    if (is_string($key)) {
        $key = strtolower($key);
        return $actions[$key] ?? null;
    }

    // If the key is a number, return the corresponding action name
    if (is_int($key)) {
        $action_name = array_search($key, $actions, true);
        return $action_name !== false ? $action_name : null;
    }

    return null;
}

if (!function_exists('normalizeAuditLogValue')) {
    function normalizeAuditLogValue($value)
    {
        if (is_array($value)) {
            $normalized = array_map('normalizeAuditLogValue', $value);
            return implode(',', $normalized);
        }

        if ($value === null) {
            return 'Empty Value';
        }

        $value = trim((string) $value);
        return $value === '' ? 'Empty Value' : $value;
    }
}

if (!function_exists('auditLogEscape')) {
    function auditLogEscape($connect, $value)
    {
        if (!($connect instanceof mysqli)) {
            return '';
        }

        return mysqli_real_escape_string($connect, normalizeAuditLogValue($value));
    }
}


function audit_log($data = array())
{
    if (!is_array($data) || empty($data)) {
        return;
    }

    // --- BACKWARD COMPATIBILITY FIX ---
    // Try to use the passed connection. If missing, fall back to the global $connect.
    $connect = (isset($data['connect']) && $data['connect'] instanceof mysqli) 
        ? $data['connect'] 
        : (isset($GLOBALS['connect']) ? $GLOBALS['connect'] : null);

    // Abort if no valid database connection can be found at all
    if (!($connect instanceof mysqli)) {
        return;
    }
    // ----------------------------------

    $logAct = strtolower(trim((string) ($data['log_act'] ?? '')));
    $actionId = get_allowed_audit_actions($logAct);

    if ($actionId === null) {
        return;
    }

    $page = auditLogEscape($connect, $data['page'] ?? '');
    $uid = auditLogEscape($connect, $data['uid'] ?? '');
    $cby = auditLogEscape($connect, $data['cby'] ?? '');
    $cdate = auditLogEscape($connect, $data['cdate'] ?? date('Y-m-d'));
    $ctime = auditLogEscape($connect, $data['ctime'] ?? date('H:i:s'));
    $actMsg = auditLogEscape($connect, $data['act_msg'] ?? '');
    $queryRec = auditLogEscape($connect, $data['query_rec'] ?? '');
    $queryTable = auditLogEscape($connect, $data['query_table'] ?? '');
    $oldVal = auditLogEscape($connect, $data['oldval'] ?? '');
    $newVal = auditLogEscape($connect, $data['newval'] ?? '');
    $changes = auditLogEscape($connect, $data['changes'] ?? '');

    $screenType = in_array($logAct, array('login', 'logout'), true) ? 'Login Screen' : $page;

    $fields = array(
        'log_action' => (string) $actionId,
        'screen_type' => $screenType,
        'user_id' => $uid,
        'action_message' => $actMsg,
        'create_date' => $cdate,
        'create_time' => $ctime,
        'create_by' => $cby,
    );

    if (in_array($logAct, array('add', 'import'), true)) {
        $fields['query_record'] = $queryRec;
        $fields['query_table'] = $queryTable;
        $fields['new_value'] = $newVal;
    }

    if ($logAct === 'edit') {
        $fields['query_record'] = $queryRec;
        $fields['query_table'] = $queryTable;
        $fields['old_value'] = $oldVal;
        $fields['changes'] = $changes;
    }

    if (in_array($logAct, array('delete', 'approval', 'declined', 'cancel', 'search', 'reset'), true)) {
        $fields['query_record'] = $queryRec;
        $fields['query_table'] = $queryTable;
    }

    $columns = implode(', ', array_keys($fields));
    $values = array();
    foreach ($fields as $value) {
        $values[] = "'" . $value . "'";
    }

    $query = "INSERT INTO " . AUDIT_LOG . " (" . $columns . ") VALUES (" . implode(', ', $values) . ")";
    mysqli_query($connect, $query);
}

function formatTime($time)
{
	if (!empty($time)) {
		return date('Y-m-d H:i:s', strtotime($time));
	} else {
		return "Time is empty.";
	}
}

function getCountry($param, $connect)
{
	$all_country = array();

	$result = getData('*', '', '', 'countries', $connect);

	if ($result) {
		while ($row = $result->fetch_assoc()) {
			$all_country[$row['code']] = $row['name'];
		}
	}

	switch ($param) {
		case 'MY':
		case 'my':
			return 'Malaysia';
			break;
		case 'SG':
		case 'sg':
			return 'Singapore';
			break;
		case 'all':
		case 'All':
			return $all_country;
			break;
	}
}

function getCountryTelCode($param, $connect)
{
	$result = getData('*', 'code = "' . $param . '"', '', COUNTRIES, $connect);

	if ($result) {
		$row = $result->fetch_assoc();

		if ($row) {
			return "+" . $row['phonecode'];
		} else {
			return 'No data found';
		}
	} else {
		return 'Query failed';
	}
}

function getCurrencyUnit($param)
{
	switch ($param) {
		case 'MY':
		case 'my':
			return 'MYR';
			break;
		case 'SG':
		case 'sg':
			return 'SGD';
			break;
	}
}

function rate_checking($data = array())
{
	$action = "MPRateCheckingBulk";

	switch ($data['country']) {
		case "MY":
		case "my":
			$domain = EASYPARCEL_DOMAIN_MY;
			$auth = EASYPARCEL_AUTH_MY;
			$api = EASYPARCEL_API_MY;
			$bulk = array(
				array(
					'pick_code' => $data['postcode_from'],
					'pick_country' => $data['from'],
					'send_code' => $data['postcode_to'],
					'send_country' => $data['to'],
					'weight' => $data['weight'],
					'width' => '0',
					'length' => '0',
					'height' => '0',
					'date_coll' => '',
				),
			);
			$ex = `'exclude_fields'	=> array(
				'rates.*.pickup_point',
				),`;
			break;
		case "SG":
		case "sg":
			$domain = EASYPARCEL_DOMAIN_SG;
			$auth = EASYPARCEL_AUTH_SG;
			$api = EASYPARCEL_API_SG;
			$bulk = array(
				array(
					'pick_code' => $data['postcode_from'],
					'pick_country' => $data['from'],
					'send_code' => $data['postcode_to'],
					'send_country' => $data['to'],
					'weight' => $data['weight'],
					'width' => '0',
					'length' => '0',
					'height' => '0',
					'date_coll' => '',
				),
			);
			$ex = '';
			break;
		default:
			$domain = '';
			$api = '';
			$bulk = '';
			$ex = '';
	}

	$postparam = array(
		'authentication' => $auth,
		'api' => $api,
		'bulk' => $bulk,
		$ex
	);

	$url = $domain . $action;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postparam));
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	ob_start();
	$return = curl_exec($ch);
	ob_end_clean();
	curl_close($ch);

	$json = json_decode($return, true); // true for array
	return $json;
}

function make_order($data = array())
{
	$action = "MPSubmitOrderBulk";

	switch ($data['pick_country']) {
		case "MY":
		case "my":
			$domain = EASYPARCEL_DOMAIN_MY;
			$auth = EASYPARCEL_AUTH_MY;
			$api = EASYPARCEL_API_MY;
			$bulk = array(
				array(
					'weight' => $data['weight'],
					'width' => '0',
					'length' => '0',
					'height' => '0',
					'content' => $data['content'],
					'value' => $data['value'],
					'service_id' => $data['sid'],
					'pick_point' => $data['pick_point'],
					'pick_name' => $data['pick_name'],
					'pick_company' => $data['pick_company'],
					'pick_contact' => $data['pick_contact'],
					'pick_mobile' => $data['pick_mobile'],
					'pick_addr1' => $data['pick_addr1'],
					'pick_addr2' => $data['pick_addr2'],
					'pick_addr3' => '',
					'pick_addr4' => '',
					'pick_city' => $data['pick_city'],
					'pick_code' => $data['pick_code'],
					'pick_country' => $data['pick_country'],
					'send_point' => $data['send_point'],
					'send_name' => $data['send_name'],
					'send_company' => $data['send_company'],
					'send_contact' => $data['send_contact'],
					'send_mobile' => $data['send_mobile'],
					'send_addr1' => $data['send_addr1'],
					'send_addr2' => $data['send_addr2'],
					'send_addr3' => '',
					'send_addr4' => '',
					'send_city' => $data['send_city'],
					'send_code' => $data['send_code'],
					'send_country' => $data['send_country'],
					'collect_date' => $data['collect_date'],
					'sms' => '0',
					'send_email' => $data['send_email'],
					'hs_code' => '',
					'reference' => $data['reference']
				)
			);
			break;
		case "SG":
		case "sg":
			$domain = EASYPARCEL_DOMAIN_SG;
			$auth = EASYPARCEL_AUTH_SG;
			$api = EASYPARCEL_API_SG;
			$bulk = array(
				array(
					'weight' => $data['weight'],
					'width' => '0',
					'length' => '0',
					'height' => '0',
					'content' => $data['content'],
					'value' => $data['value'],
					'service_id' => $data['sid'],
					'pick_name' => $data['pick_name'],
					'pick_company' => $data['pick_company'],
					'pick_contact' => $data['pick_contact'],
					'pick_mobile' => $data['pick_mobile'],
					'pick_unit' => $data['pick_addr1'],
					'pick_code' => $data['pick_code'],
					'pick_country' => $data['pick_country'],
					'send_name' => $data['send_name'],
					'send_company' => $data['send_company'],
					'send_contact' => $data['send_contact'],
					'send_mobile' => $data['send_mobile'],
					'send_unit' => $data['send_addr1'],
					'send_addr1' => $data['send_addr1'],
					'send_code' => $data['send_code'],
					'send_country' => $data['send_country'],
					'collect_date' => $data['collect_date'],
					'reference' => $data['reference']
				)
			);
			break;
		default:
			$domain = '';
			$api = '';
			$bulk = '';
	}

	$postparam = array(
		'authentication' => $auth,
		'api' => $api,
		'bulk' => $bulk,
	);

	$url = $domain . $action;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postparam));
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	ob_start();
	$return = curl_exec($ch);
	ob_end_clean();
	curl_close($ch);

	$json = json_decode($return, true);
	return $json;
}

function make_order_payment($data = array())
{
	$action = "MPPayOrderBulk";

	switch ($data['country']) {
		case 'MY':
		case 'my':
			$domain = EASYPARCEL_DOMAIN_MY;
			$auth = EASYPARCEL_AUTH_MY;
			$api = EASYPARCEL_API_MY;
			$bulk = array(
				array(
					'order_no' => $data['order_number'],
				),
			);
			break;
		case 'SG':
		case 'sg':
			$domain = EASYPARCEL_DOMAIN_SG;
			$auth = EASYPARCEL_AUTH_SG;
			$api = EASYPARCEL_API_SG;
			$bulk = array(
				array(
					'order_no' => $data['order_number'],
				),
			);
			break;
	}

	$postparam = array(
		'authentication' => $auth,
		'api' => $api,
		'bulk' => $bulk
	);

	$url = $domain . $action;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postparam));
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	ob_start();
	$return = curl_exec($ch);
	ob_end_clean();
	curl_close($ch);

	$json = json_decode($return, true);
	return $json;
}


function displayPageAction($act, $page)
{
	switch ($act) {
		case 'I':
			return "Add $page";
		case 'E':
			return "Edit $page";
		default:
			return "View $page";
	}
}

function implodeWithComma($data)
{
    if (!is_array($data)) {
        return normalizeAuditLogValue($data);
    }

    $normalized = array_map('normalizeAuditLogValue', $data);
    return implode(',', $normalized);
}

function sanitizeAuditMessageValue($value)
{
    $normalized = normalizeAuditLogValue($value);
    $normalized = stripslashes((string) $normalized);
    $normalized = str_replace(array("\\`", "\\'", '\\"'), array('`', "'", '"'), $normalized);
    return trim($normalized);
}


function actMsgLog($id, $datafield = array(), $newvalarr = array(), $oldvalarr = array(), $chgvalarr = array(), $tblName, $action, $errorMsg)
{
	$action = strtolower($action);

	$actMsg = USER_NAME . (empty($errorMsg) ? " " : " fail to ") . $action . "  the data [ <b> ID = " . $id . " </b> ]";

	switch ($action) {
		case 'add':
            $fieldCount = min(sizeof($datafield), sizeof($newvalarr));
            for ($i = 0; $i < $fieldCount; $i++) {
                $fieldName = sanitizeAuditMessageValue($datafield[$i]);
                $newValue = htmlspecialchars(sanitizeAuditMessageValue($newvalarr[$i]), ENT_QUOTES, 'UTF-8');
				if ($i == 0)
                    $actMsg .= " [ <b> " . $fieldName . " </b> : <b>'" . $newValue . "'</b>  ]";
				else
                    $actMsg .= " , [ <b> " . $fieldName . " </b> : <b>'" . $newValue . "'</b> ]";
			}
			break;
		case 'edit':
            $fieldCount = sizeof($datafield);
            for ($i = 0; $i < $fieldCount; $i++) {
                $fieldName = sanitizeAuditMessageValue($datafield[$i]);
                $oldValue = htmlspecialchars(sanitizeAuditMessageValue(isset($oldvalarr[$i]) ? $oldvalarr[$i] : 'Empty Value'), ENT_QUOTES, 'UTF-8');
                $chgValue = htmlspecialchars(sanitizeAuditMessageValue(isset($chgvalarr[$i]) ? $chgvalarr[$i] : 'Empty Value'), ENT_QUOTES, 'UTF-8');
                $segment = " [ <b> " . $fieldName . " </b> : <b>'" . $oldValue . "'</b>";
                if (trim($chgValue) !== '' && strtolower(trim($chgValue)) !== 'empty value') {
                    $segment .= " to <b>'" . $chgValue . "'</b>";
                }
                $segment .= " ]";

                if ($i == 0)
                    $actMsg .= $segment;
                else
                    $actMsg .= " ," . $segment;
			}
			break;
	}

	$actMsg .= "  under <b><i>$tblName Table</i></b>.";
	(!empty($errorMsg)) ? $actMsg .= " ( " . str_replace('\'', '', $errorMsg) . " )" : '';

	return $actMsg;
}


// Function to update previous and final amounts for transactions
function updateTransAmt($finance_connect, $table_name, $fields, $uniqueKey)
{
	// Initialize an associative array to store previous amounts
	$prevAmounts = array();

	// Construct the query
	$query = "SELECT id, `type`, amount, " . implode(', ', $fields) . ", `status` FROM $table_name WHERE `status` <> 'D' ORDER BY id";
	$result = mysqli_query($finance_connect, $query);

	if (!$result) {
		die("Error reading records: " . mysqli_error($finance_connect));
	}

	// Loop through each transaction
	while ($row = mysqli_fetch_assoc($result)) {
		$id = $row['id'];
		$type = $row['type'];
		$amount = $row['amount'];

		// Create the key for the $prevAmounts array
		$keyParts = array_map(function ($field) use ($row) {
			return $row[$field];
		}, $uniqueKey);
		$key = implode('_', $keyParts);

		if (!isset($prevAmounts[$key])) {
			$prevAmounts[$key] = 0;
		}
		$prevFinalAmt = $prevAmounts[$key];

		// Calculate final_amt based on transaction type
		if ($type === 'Add') {
			$finalAmt = $prevFinalAmt + $amount;
		} else if ($type === 'Deduct') {
			$finalAmt = $prevFinalAmt - $amount;
		}

		// Update the row in the database
		$updateQuery = "UPDATE $table_name SET prev_amt ='$prevFinalAmt', final_amt ='$finalAmt' WHERE id = '$id'";
		$updateResult = mysqli_query($finance_connect, $updateQuery);

		if (!$updateResult) {
			die("Update failed: " . mysqli_error($finance_connect));
		}
		$prevAmounts[$key] = $finalAmt;
	}
	return true;
}


function insertNewMerchant($merchantName, $userId, $financeConnect)
{
	$query = "INSERT INTO " . MERCHANT . "(name,create_by,create_date,create_time) VALUES ('$merchantName','$userId',curdate(),curtime())";
	$queryResult = mysqli_query($financeConnect, $query);
	if ($queryResult) {
		return mysqli_insert_id($financeConnect);
	}
	return false;
}

function monthStringToNumber($monthString)
{
	$monthArray = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
	return array_search($monthString, $monthArray) + 1;
}

function monthNumberToString($monthNumber)
{
	$monthArray = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

	// Check if the monthNumber is valid
	if ($monthNumber >= 1 && $monthNumber <= 12) {
		return $monthArray[$monthNumber - 1];
	} else {
		// Handle invalid monthNumber
		return false;
	}
}

if (!function_exists('commonResolveUserDisplayName')) {
    function commonResolveUserDisplayName($connect, $userId)
    {
        static $userNameCache = array();

        $userId = trim((string) $userId);
        if ($userId === '' || !ctype_digit($userId)) {
            return '';
        }

        if (array_key_exists($userId, $userNameCache)) {
            return $userNameCache[$userId];
        }

        $userTable = defined('USR_USER') ? USR_USER : 'user';
        $result = getData('name', "id='" . mysqli_real_escape_string($connect, $userId) . "'", 'LIMIT 1', $userTable, $connect);
        $userName = '';

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $userName = isset($row['name']) ? trim((string) $row['name']) : '';
        }

        if ($userName === '') {
            $userName = $userId;
        }

        $userNameCache[$userId] = $userName;
        return $userName;
    }
}

if (!function_exists('commonFormatAuditDateTime')) {
    function commonFormatAuditDateTime($cdate, $ctime)
    {
        $cdate = trim((string) $cdate);
        $ctime = trim((string) $ctime);

        if (
            $cdate === '' ||
            $cdate === '0000-00-00' ||
            $ctime === '' ||
            $ctime === '00:00:00'
        ) {
            return '';
        }

        $dateTime = strtotime($cdate . ' ' . $ctime);
        if ($dateTime === false) {
            return trim($cdate . ' ' . $ctime);
        }

        return date('Y-m-d', $dateTime) . ' ' . date('G:i:s', $dateTime);
    }
}

if (!function_exists('commonRenderCreateUpdateInfo')) {
    function commonRenderCreateUpdateInfo($row, $connect, $act = '')
    {
        if (!is_array($row) || empty($row) || trim((string) $act) === 'I') {
            return '';
        }

        $lines = array();

        $createUserId = isset($row['create_by']) ? $row['create_by'] : '';
        $createDateTime = commonFormatAuditDateTime(
            isset($row['create_date']) ? $row['create_date'] : '',
            isset($row['create_time']) ? $row['create_time'] : ''
        );

        if ($createUserId !== '' && $createDateTime !== '') {
            $lines[] = 'Created by ' . commonResolveUserDisplayName($connect, $createUserId) . ' at ' . $createDateTime;
        }

        $updateUserId = isset($row['update_by']) ? $row['update_by'] : '';
        $updateDateTime = commonFormatAuditDateTime(
            isset($row['update_date']) ? $row['update_date'] : '',
            isset($row['update_time']) ? $row['update_time'] : ''
        );

        if ($updateUserId !== '' && $updateDateTime !== '') {
            $lines[] = 'Updated by ' . commonResolveUserDisplayName($connect, $updateUserId) . ' at ' . $updateDateTime;
        }

        if (empty($lines)) {
            return '';
        }

        $html = '<div class="common-create-update-info mt-2">';
        foreach ($lines as $line) {
            $html .= '<div class="small text-muted">' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}

function renderViewEditButton($action, $redirectPage, $row, $pinAccess, $act_2 = null)
{
	switch ($action) {
		case "View":
			if (isActionAllowed("View", $pinAccess)) {
				echo '<a class="btn btn-primary me-1" href="' . $redirectPage . '?id=' . $row['id'] . '"><i class="fas fa-eye"></i></a>';
			}
			break;
		case "Edit":
			if (isActionAllowed("Edit", $pinAccess)) {
				echo '<a class="btn btn-warning me-1" href="' . $redirectPage . '?id=' . $row['id'] . '&act=' . $act_2 . '"><i class="fas fa-edit"></i></a>';
			}
			break;
	}

}

function renderViewEditButtonByPin($action, $redirectPage, $row, $pinAccess, $act_2 = null)
{
	$redirectPage = (string) $redirectPage;
	$querySeparator = strpos($redirectPage, '?') === false ? '?' : '&';
	switch ($action) {
		case "1":
			if (in_array(1, $pinAccess)) {
				echo '<a class="btn btn-primary me-1" href="' . $redirectPage . $querySeparator . 'id=' . $row['id'] . '"><i class="fas fa-eye"></i></a>';
			}
			break;
		case "2":
			if (in_array(2, $pinAccess)) {
				echo '<a class="btn btn-warning me-1" href="' . $redirectPage . $querySeparator . 'id=' . $row['id'] . '&act=' . $act_2 . '"><i class="fas fa-edit"></i></a>';
			}
			break;
	}

}

function renderDeleteButtonByPin($pinAccess, $rowId, $rowName, $rowRemark, $pageTitle, $redirectPage, $deleteRedirectPage)
{
    // Check if Delete action is allowed
    if (isActionAllowed("3", $pinAccess)) {
        
    // 1. Prepare all arguments in a PHP array
        $args = [
            (string)$rowId,
            [(string)($rowName ?? ''), (string)($rowRemark ?? '')],
            (string)$pageTitle,
            (string)$redirectPage,
            (string)$deleteRedirectPage,
            'D'
        ];

        // 2. Encode JS arguments safely, including quotes and symbols in text fields.
        $payloadJson = json_encode(
            $args,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($payloadJson === false) {
            $payloadJson = '["",["",""],"","","","D"]';
        }

        $payloadB64 = base64_encode($payloadJson);
        $jsCall = "(function(){var a=JSON.parse(atob('" . $payloadB64 . "'));confirmationDialog(a[0],a[1],a[2],a[3],a[4],a[5]);})();";
        $safeOnclick = htmlspecialchars($jsCall, ENT_QUOTES, 'UTF-8');

        // Output Delete button
        echo '<a class="btn btn-danger" onclick="' . $safeOnclick . '"><i class="fas fa-trash-alt"></i></a>';    
    }
}

function renderDeleteButton($pinAccess, $rowId, $rowName, $rowRemark, $pageTitle, $redirectPage, $deleteRedirectPage)
{
    // Check if Delete action is allowed
    if (isActionAllowed("Delete", $pinAccess)) {
        
       // 1. Prepare all arguments in a PHP array
        $args = [
            (string)$rowId,
            [(string)($rowName ?? ''), (string)($rowRemark ?? '')],
            (string)$pageTitle,
            (string)$redirectPage,
            (string)$deleteRedirectPage,
            'D'
        ];

        // 2. Encode JS arguments safely, including quotes and symbols in text fields.
        $payloadJson = json_encode(
            $args,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($payloadJson === false) {
            $payloadJson = '["",["",""],"","","","D"]';
        }

        $payloadB64 = base64_encode($payloadJson);
        $jsCall = "(function(){var a=JSON.parse(atob('" . $payloadB64 . "'));confirmationDialog(a[0],a[1],a[2],a[3],a[4],a[5]);})();";
        $safeOnclick = htmlspecialchars($jsCall, ENT_QUOTES, 'UTF-8');

        // Output Delete button
        echo '<a class="btn btn-danger" onclick="' . $safeOnclick . '"><i class="fas fa-trash-alt"></i></a>';
    }
}

if (!function_exists('getUrbanismMemberActionData')) {
    function getUrbanismMemberActionData($connect, $seedId = '', $seedName = '', $returnPage = '', $returnLabel = '')
    {
        $seedId = trim((string) $seedId);
        $seedName = trim((string) $seedName);
        $returnPage = trim((string) $returnPage);
        $returnLabel = trim((string) $returnLabel);

        if ($returnPage !== '') {
            if (preg_match('/^https?:\/\//i', $returnPage)) {
                $parsedReturnPath = parse_url($returnPage, PHP_URL_PATH);
                if (is_string($parsedReturnPath) && $parsedReturnPath !== '') {
                    $returnPage = $parsedReturnPath;
                }
            }

            $returnPage = str_replace('\\', '/', $returnPage);
            $sitePathPrefix = '';
            if (defined('SITEURL')) {
                $siteParsedPath = parse_url(SITEURL, PHP_URL_PATH);
                if (is_string($siteParsedPath) && $siteParsedPath !== '') {
                    $sitePathPrefix = rtrim(str_replace('\\', '/', $siteParsedPath), '/');
                }
            }

            if ($sitePathPrefix !== '' && strpos($returnPage, $sitePathPrefix . '/') === 0) {
                $returnPage = substr($returnPage, strlen($sitePathPrefix) + 1);
            }

            $returnPage = ltrim($returnPage, '/');
        }

        $memberRow = null;

        if ($connect instanceof mysqli) {
            static $memberCacheById = array();
            static $memberCacheByName = array();

            $normalizedSeedName = strtolower(trim((string) $seedName));

            if ($normalizedSeedName !== '') {
                if (array_key_exists($normalizedSeedName, $memberCacheByName)) {
                    $memberRow = $memberCacheByName[$normalizedSeedName];
                } else {
                    $safeSeedName = mysqli_real_escape_string($connect, $normalizedSeedName);
                    $memberSql = "SELECT * FROM " . URBAN_CUST_REG . " WHERE LOWER(TRIM(name))='" . $safeSeedName . "' LIMIT 1";
                    $memberRst = mysqli_query($connect, $memberSql);

                    if ($memberRst && $memberRst->num_rows > 0) {
                        $memberRow = $memberRst->fetch_assoc();
                    } else {
                        $memberRow = null;
                    }

                    $memberCacheByName[$normalizedSeedName] = $memberRow;
                    if ($memberRow !== null) {
                        $memberIdKey = isset($memberRow['id']) ? trim((string) $memberRow['id']) : '';
                        if ($memberIdKey !== '') {
                            $memberCacheById[$memberIdKey] = $memberRow;
                        }
                        if ($seedId !== '') {
                            $memberCacheById[$seedId] = $memberRow;
                        }
                    }
                }
            }

            if ($memberRow === null && $seedId !== '' && $normalizedSeedName === '') {
                if (array_key_exists($seedId, $memberCacheById)) {
                    $memberRow = $memberCacheById[$seedId];
                } else {
                    $memberRst = null;
                    if (ctype_digit($seedId)) {
                        $memberRst = getData('*', "id='" . ((int) $seedId) . "'", 'LIMIT 1', URBAN_CUST_REG, $connect);
                    }

                    if ($memberRst === null) {
                        $safeSeedId = mysqli_real_escape_string($connect, strtolower(trim((string) $seedId)));
                        $memberSql = "SELECT * FROM " . URBAN_CUST_REG . " WHERE LOWER(TRIM(name))='" . $safeSeedId . "' LIMIT 1";
                        $memberRst = mysqli_query($connect, $memberSql);
                    }

                    if ($memberRst && $memberRst->num_rows > 0) {
                        $memberRow = $memberRst->fetch_assoc();
                    } else {
                        $memberRow = null;
                    }

                    $memberCacheById[$seedId] = $memberRow;
                    if ($memberRow !== null) {
                        $memberIdKey = isset($memberRow['id']) ? trim((string) $memberRow['id']) : '';
                        if ($memberIdKey !== '') {
                            $memberCacheById[$memberIdKey] = $memberRow;
                        }
                        $memberNameKey = strtolower(trim((string) (isset($memberRow['name']) ? $memberRow['name'] : '')));
                        if ($memberNameKey !== '') {
                            $memberCacheByName[$memberNameKey] = $memberRow;
                        }
                    }
                }
            }
        }

        $isMember = is_array($memberRow);
        $targetId = $isMember
            ? trim((string) ($memberRow['id'] ?? ''))
            : trim((string) ($seedName !== '' ? $seedName : $seedId));

        $params = array(
            'id' => $targetId,
            'act' => $isMember ? 'E' : 'I',
        );

        if ($returnPage !== '') {
            $_SESSION['urbanism_member_return_page'] = $returnPage;
        }

        if ($returnLabel !== '') {
            $_SESSION['urbanism_member_return_label'] = $returnLabel;
        }

        $url = '#';
        $disabled = true;
        if ($targetId !== '' && defined('SITEURL')) {
            $url = SITEURL . '/customer/urb_cust_reg.php?' . http_build_query($params);
            $disabled = false;
        }

        return array(
            'url' => $url,
            'is_member' => $isMember,
            'disabled' => $disabled,
            'icon_class' => $isMember ? 'fa-solid fa-address-card' : 'fas fa-users',
            'title' => $isMember ? 'Urbanism Member' : 'Register Urbanism Member',
        );
    }
}

if (!function_exists('normalizeOrderStatusKey')) {
    function normalizeOrderStatusKey($code)
    {
        return preg_replace('/[^a-z]/', '', strtolower(trim((string) $code)));
    }
}

function getOrderStatusLabel($code) {
    if (function_exists('shopeeOmsGetStatusLabel')) {
        return shopeeOmsGetStatusLabel($code);
    }

    return trim((string) $code);
}

if (!function_exists('getMarketplaceRequestStatusLabel')) {
    function getMarketplaceRequestStatusLabel($code)
    {
        if (function_exists('shopeeOmsGetMarketplaceStatusLabel')) {
            return shopeeOmsGetMarketplaceStatusLabel($code);
        }

        return trim((string) $code);
    }
}

if (!function_exists('customerLabelNormalizeType')) {
    function customerLabelNormalizeType($labelType)
    {
        $labelType = strtolower(trim((string) $labelType));
        $allowed = array('segmentation', 'level', 'repeat');
        return in_array($labelType, $allowed, true) ? $labelType : '';
    }
}

if (!function_exists('customerLabelGetTypeConfig')) {
    function customerLabelGetTypeConfig($labelType)
    {
        $typeConfigs = array(
            'segmentation' => array(
                'table' => CUR_SEGMENTATION,
                'title' => 'Customer Segmentation',
                'pin' => 29,
            ),
            'level' => array(
                'table' => CUS_LEVEL,
                'title' => 'Customer Level',
                'pin' => 142,
            ),
            'repeat' => array(
                'table' => CUS_REPEAT,
                'title' => 'Customer Repeat',
                'pin' => 143,
            ),
        );

        $labelType = customerLabelNormalizeType($labelType);
        return $labelType !== '' && isset($typeConfigs[$labelType]) ? $typeConfigs[$labelType] : array();
    }
}

if (!function_exists('customerLabelGetPlatformConfigs')) {
    function customerLabelGetPlatformConfigs()
    {
        return array(
            'shopee' => array(
                'label' => 'Shopee Customer Record',
                'record_url' => '/shopee/shopee_cust_info_table.php',
                'customer_table' => SHOPEE_CUST_INFO,
                'customer_db' => 'finance',
                'order_table' => SHOPEE_SG_ORDER_REQ,
                'order_db' => 'finance',
                'order_customer_field' => 'buyer',
                'order_series_field' => '',
                'customer_series_field' => 'series',
                'order_package_field' => 'package',
                'order_amount_field' => 'final_amt',
                'order_currency_field' => 'currency',
            ),
            'lazada' => array(
                'label' => 'Lazada Customer Record (Deals)',
                'record_url' => '/lazada_cust_rcd_table.php',
                'customer_table' => LAZADA_CUST_RCD,
                'customer_db' => 'cms',
                'order_table' => LAZADA_ORDER_REQ,
                'order_db' => 'cms',
                'order_customer_field' => 'cust_id',
                'order_series_field' => 'series',
                'customer_series_field' => 'series',
                'order_package_field' => 'pkg',
                'order_amount_field' => 'final_income',
                'order_currency_field' => 'curr_unit',
            ),
            'facebook' => array(
                'label' => 'Facebook Customer Record (Deals)',
                'record_url' => '/customer/fb_cust_deals_table.php',
                'customer_table' => FB_CUST_DEALS,
                'customer_db' => 'cms',
                'order_table' => FB_ORDER_REQ,
                'order_db' => 'finance',
                'order_customer_field' => '',
                'order_series_field' => 'series',
                'customer_series_field' => 'series',
                'order_package_field' => 'package',
                'order_amount_field' => 'price',
                'order_currency_field' => '',
            ),
            'website' => array(
                'label' => 'Website Customer Record (Deals)',
                'record_url' => '/customer/website_customer_record_table.php',
                'customer_table' => WEB_CUST_RCD,
                'customer_db' => 'cms',
                'order_table' => WEB_ORDER_REQ,
                'order_db' => 'finance',
                'order_customer_field' => 'cust_id',
                'order_series_field' => 'series',
                'customer_series_field' => 'series',
                'order_package_field' => 'pkg',
                'order_amount_field' => 'total',
                'order_currency_field' => 'currency',
            ),
        );
    }
}

if (!function_exists('customerDailyReportGetPlatformConfigs')) {
    function customerDailyReportGetPlatformConfigs()
    {
        return array(
            'shopee' => array(
                'platform' => 'shopee',
                'label' => 'Shopee',
                'page_title' => 'Shopee Customer Record',
                'table' => SHOPEE_CUST_INFO,
                'db' => 'finance',
                'record_url' => '/shopee/shopee_cust_info.php',
                'display_fields' => array('buyer_username'),
            ),
            'lazada' => array(
                'platform' => 'lazada',
                'label' => 'Lazada',
                'page_title' => 'Lazada Customer Record (Deals)',
                'table' => LAZADA_CUST_RCD,
                'db' => 'cms',
                'record_url' => '/finance/lazada_cust_rcd.php',
                'display_fields' => array('name'),
            ),
            'facebook' => array(
                'platform' => 'facebook',
                'label' => 'Facebook',
                'page_title' => 'Facebook Customer Record (Deals)',
                'table' => FB_CUST_DEALS,
                'db' => 'cms',
                'record_url' => '/customer/fb_cust_deals.php',
                'display_fields' => array('name'),
            ),
            'website' => array(
                'platform' => 'website',
                'label' => 'Website',
                'page_title' => 'Website Customer Record (Deals)',
                'table' => WEB_CUST_RCD,
                'db' => 'cms',
                'record_url' => '/customer/website_customer_record.php',
                'display_fields' => array('name'),
            ),
            'customer_info' => array(
                'platform' => 'customer_info',
                'label' => 'Customer Info',
                'page_title' => 'Customer Info',
                'table' => CUS_INFO,
                'db' => 'cms',
                'record_url' => '/customer/customerInfo.php',
                'display_fields' => array('name', 'last_name'),
            ),
        );
    }
}

if (!function_exists('customerDailyReportNormalizePlatformKey')) {
    function customerDailyReportNormalizePlatformKey($platformKey, $allowAll = false)
    {
        $platformKey = strtolower(trim((string) $platformKey));
        if ($allowAll && $platformKey === 'all') {
            return 'all';
        }

        $platformConfigs = customerDailyReportGetPlatformConfigs();
        return isset($platformConfigs[$platformKey]) ? $platformKey : '';
    }
}

if (!function_exists('customerDailyReportResolveDbConnect')) {
    function customerDailyReportResolveDbConnect($connect, $financeConnect, $dbKey)
    {
        return $dbKey === 'finance' ? $financeConnect : $connect;
    }
}

if (!function_exists('customerDailyReportGetPlatformConfigByTable')) {
    function customerDailyReportGetPlatformConfigByTable($tblName)
    {
        $tblName = trim((string) $tblName);
        foreach (customerDailyReportGetPlatformConfigs() as $platformConfig) {
            if (isset($platformConfig['table']) && (string) $platformConfig['table'] === $tblName) {
                return $platformConfig;
            }
        }

        return array();
    }
}

if (!function_exists('customerDailyReportGetSupportedTables')) {
    function customerDailyReportGetSupportedTables()
    {
        $tables = array();
        foreach (customerDailyReportGetPlatformConfigs() as $platformConfig) {
            if (!empty($platformConfig['table'])) {
                $tables[] = (string) $platformConfig['table'];
            }
        }

        return $tables;
    }
}

if (!function_exists('customerDailyReportExtractRecordId')) {
    function customerDailyReportExtractRecordId($auditRow)
    {
        if (!is_array($auditRow)) {
            return 0;
        }

        $actionMessage = html_entity_decode(strip_tags((string) ($auditRow['action_message'] ?? '')), ENT_QUOTES, 'UTF-8');
        if (preg_match('/\bID\s*=\s*(\d+)\b/i', $actionMessage, $matches)) {
            return (int) $matches[1];
        }

        $queryRecord = (string) ($auditRow['query_record'] ?? '');
        if (preg_match('/\bWHERE\s+id\s*=\s*\'?(\d+)\'?/i', $queryRecord, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\bid\s*=\s*\'?(\d+)\'?/i', $queryRecord, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}

if (!function_exists('customerDailyReportBuildChangeSummary')) {
    function customerDailyReportBuildChangeSummary($fieldName, $oldValue, $newValue)
    {
        $fieldName = trim((string) $fieldName);
        $oldValue = normalizeAuditLogValue($oldValue);
        $newValue = normalizeAuditLogValue($newValue);

        if ($oldValue === 'Empty Value' && $newValue !== 'Empty Value') {
            return $fieldName . ' set to "' . $newValue . '"';
        }

        if ($newValue === 'Empty Value') {
            return $fieldName . ' cleared from "' . $oldValue . '"';
        }

        return $fieldName . ' changed from "' . $oldValue . '" to "' . $newValue . '"';
    }
}

if (!function_exists('customerDailyReportGetFieldLabelMap')) {
    function customerDailyReportGetFieldLabelMap($platformKey)
    {
        $platformKey = customerDailyReportNormalizePlatformKey($platformKey);
        $fieldMaps = array(
            'shopee' => array(
                'buyer_username' => 'Shopee Buyer Username',
                'pic' => 'Sales Person In Charge',
                'country' => 'Country',
                'brand' => 'Brand',
                'series' => 'Series',
                'contact_no' => 'Whatsapp / Contact Number',
                'remark' => 'Remark',
            ),
            'lazada' => array(
                'lcr_id' => 'Customer ID',
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'pic' => 'Sales Person In Charge',
                'country' => 'Country',
                'brand' => 'Brand',
                'series' => 'Series',
                'shipping receiver name' => 'Receiver Name',
                'shipping receiver contact' => 'Receiver Contact',
                'shipping receiver address' => 'Receiver Address',
                'remark' => 'Remark',
            ),
            'facebook' => array(
                'name' => 'Name',
                'fb link' => 'Facebook Link',
                'facebook link' => 'Facebook Link',
                'contact' => 'Contact',
                'pic' => 'Sales Person In Charge',
                'country' => 'Country',
                'brand' => 'Brand',
                'series' => 'Series',
                'fb_page' => 'Facebook Page',
                'fb page' => 'Facebook Page',
                'channel' => 'Channel',
                'shipping receiver name' => 'Receiver Name',
                'shipping receiver contact' => 'Receiver Contact',
                'shipping receiver address' => 'Receiver Address',
                'remark' => 'Remark',
            ),
            'website' => array(
                'cust_id' => 'Customer ID',
                'name' => 'Name',
                'contact' => 'Contact',
                'cust_email' => 'Customer Email',
                'cust_birthday' => 'Customer Birthday',
                'pic' => 'Sales Person In Charge',
                'country' => 'Country',
                'brand' => 'Brand',
                'series' => 'Series',
                'shipping receiver name' => 'Receiver Name',
                'shipping receiver contact' => 'Receiver Contact',
                'shipping receiver address' => 'Receiver Address',
                'remark' => 'Remark',
            ),
            'customer_info' => array(
                'name' => 'First Name',
                'last_name' => 'Last Name',
                'gender' => 'Gender',
                'email' => 'Email',
                'birthday' => 'Birthday',
                'phone_country' => 'Phone Code',
                'phone_number' => 'Phone Number',
                'shipping_name' => 'Shipping First Name',
                'shipping_last_name' => 'Shipping Last Name',
                'shipping_contact_number' => 'Shipping Contact Number',
                'shipping_company' => 'Company',
                'shipping_address_1' => 'Address 1',
                'shipping_address_2' => 'Address 2',
                'shipping_country_region' => 'Country/Region',
                'shipping_city' => 'City',
                'shipping_state_province' => 'State/Province',
                'shipping_zip_code' => 'Zip Code',
                'default_segmentation' => 'Current Segmentation',
                'tags' => 'Tag',
                'person_in_charges' => 'Person In Charges',
            ),
        );

        return isset($fieldMaps[$platformKey]) ? $fieldMaps[$platformKey] : array();
    }
}

if (!function_exists('customerDailyReportNormalizeFieldKey')) {
    function customerDailyReportNormalizeFieldKey($fieldName)
    {
        $fieldName = trim(html_entity_decode(strip_tags((string) $fieldName), ENT_QUOTES, 'UTF-8'));
        $fieldName = preg_replace('/\s+/', ' ', $fieldName);
        return strtolower(trim((string) $fieldName));
    }
}

if (!function_exists('customerDailyReportLooksLikeIdNoise')) {
    function customerDailyReportLooksLikeIdNoise($fieldName)
    {
        $normalizedField = customerDailyReportNormalizeFieldKey($fieldName);
        if ($normalizedField === '') {
            return true;
        }

        if (strpos($normalizedField, 'id =') !== false || strpos($normalizedField, '[') !== false || strpos($normalizedField, ']') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('customerDailyReportHumanizeFieldLabel')) {
    function customerDailyReportHumanizeFieldLabel($fieldName)
    {
        $fieldName = trim((string) $fieldName);
        $fieldName = str_replace(array('_', '-'), ' ', $fieldName);
        $fieldName = preg_replace('/\s+/', ' ', $fieldName);
        return ucwords(strtolower(trim((string) $fieldName)));
    }
}

if (!function_exists('customerDailyReportGetDeleteFieldLabel')) {
    function customerDailyReportGetDeleteFieldLabel($platformKey)
    {
        return customerDailyReportGetPrimaryCustomerFieldLabel($platformKey);
    }
}

if (!function_exists('customerDailyReportGetPrimaryCustomerFieldLabel')) {
    function customerDailyReportGetPrimaryCustomerFieldLabel($platformKey)
    {
        $platformKey = customerDailyReportNormalizePlatformKey($platformKey);
        $fieldLabelMap = array(
            'shopee' => 'Shopee Buyer Username',
            'lazada' => 'Customer Name',
            'facebook' => 'Customer Name',
            'website' => 'Customer Name',
            'customer_info' => 'First Name',
        );

        return isset($fieldLabelMap[$platformKey]) ? $fieldLabelMap[$platformKey] : 'Customer Name';
    }
}

if (!function_exists('customerDailyReportResolveLookupValueById')) {
    function customerDailyReportResolveLookupValueById($dbConnect, $tblName, $rawValue, $displayField = 'name', $altDisplayField = '')
    {
        $rawValue = trim((string) $rawValue);
        if ($rawValue === '' || !($dbConnect instanceof mysqli) || $tblName === '') {
            return $rawValue;
        }

        $valueParts = array_map('trim', explode(',', $rawValue));
        $resolvedParts = array();
        foreach ($valueParts as $valuePart) {
            if ($valuePart === '' || strcasecmp($valuePart, 'Empty Value') === 0) {
                $resolvedParts[] = $valuePart;
                continue;
            }

            $resolvedValue = $valuePart;
            if (preg_match('/^\d+$/', $valuePart)) {
                $result = getData('*', "id = '" . mysqli_real_escape_string($dbConnect, $valuePart) . "'", 'LIMIT 1', $tblName, $dbConnect);
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if (isset($row[$displayField]) && trim((string) $row[$displayField]) !== '') {
                        $resolvedValue = trim((string) $row[$displayField]);
                    } else if ($altDisplayField !== '' && isset($row[$altDisplayField]) && trim((string) $row[$altDisplayField]) !== '') {
                        $resolvedValue = trim((string) $row[$altDisplayField]);
                    }
                }
            }

            $resolvedParts[] = $resolvedValue;
        }

        return implode(', ', $resolvedParts);
    }
}

if (!function_exists('customerDailyReportFormatPhoneCodeValue')) {
    function customerDailyReportFormatPhoneCodeValue($value)
    {
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'Empty Value') === 0) {
            return $value;
        }

        return strpos($value, '+') === 0 ? $value : ('+' . ltrim($value, '+'));
    }
}

if (!function_exists('customerDailyReportResolveFieldValue')) {
    function customerDailyReportResolveFieldValue($connect, $financeConnect, $platformKey, $fieldName, $fieldValue)
    {
        $platformKey = customerDailyReportNormalizePlatformKey($platformKey);
        $fieldKey = customerDailyReportNormalizeFieldKey($fieldName);
        $fieldValue = normalizeAuditLogValue($fieldValue);
        if ($fieldValue === 'Empty Value') {
            return $fieldValue;
        }

        switch ($fieldKey) {
            case 'pic':
            case 'sales_pic':
            case 'person_in_charges':
                return customerDailyReportResolveLookupValueById($connect, USR_USER, $fieldValue, 'name', 'username');

            case 'country':
            case 'shipping_country_region':
                return customerDailyReportResolveLookupValueById($connect, COUNTRIES, $fieldValue, 'nicename', 'name');

            case 'phone_country':
                return customerDailyReportFormatPhoneCodeValue(customerDailyReportResolveLookupValueById($connect, COUNTRIES, $fieldValue, 'phonecode', 'nicename'));

            case 'brand':
                return customerDailyReportResolveLookupValueById($connect, BRAND, $fieldValue, 'name');

            case 'series':
                return customerDailyReportResolveLookupValueById($connect, BRD_SERIES, $fieldValue, 'name');

            case 'fb_page':
            case 'fb page':
                return customerDailyReportResolveLookupValueById($financeConnect, FB_PAGE_ACC, $fieldValue, 'name');

            case 'channel':
                return customerDailyReportResolveLookupValueById($financeConnect, CHANEL_SC_MD, $fieldValue, 'name');

            case 'default_segmentation':
                return customerDailyReportResolveLookupValueById($connect, CUR_SEGMENTATION, $fieldValue, 'name');

            case 'tags':
                return customerDailyReportResolveLookupValueById($connect, TAG, $fieldValue, 'name');
        }

        return $fieldValue;
    }
}

if (!function_exists('customerDailyReportGetFieldLabel')) {
    function customerDailyReportGetFieldLabel($platformKey, $fieldName)
    {
        $fieldKey = customerDailyReportNormalizeFieldKey($fieldName);
        $fieldLabelMap = customerDailyReportGetFieldLabelMap($platformKey);
        if (isset($fieldLabelMap[$fieldKey]) && trim((string) $fieldLabelMap[$fieldKey]) !== '') {
            return trim((string) $fieldLabelMap[$fieldKey]);
        }

        return customerDailyReportHumanizeFieldLabel($fieldName);
    }
}

if (!function_exists('customerDailyReportNormalizeParsedAuditValue')) {
    function customerDailyReportNormalizeParsedAuditValue($value)
    {
        $value = sanitizeAuditMessageValue(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));

        if (strlen($value) >= 2) {
            $firstChar = substr($value, 0, 1);
            $lastChar = substr($value, -1);
            if (($firstChar === "'" && $lastChar === "'") || ($firstChar === '"' && $lastChar === '"')) {
                $value = substr($value, 1, -1);
            }
        }

        return normalizeAuditLogValue($value);
    }
}

if (!function_exists('customerDailyReportShouldSkipAuditField')) {
    function customerDailyReportShouldSkipAuditField($fieldName)
    {
        $fieldKey = customerDailyReportNormalizeFieldKey($fieldName);
        return in_array($fieldKey, array(
            'id',
            'status',
            'create_by',
            'create_date',
            'create_time',
            'update_by',
            'update_date',
            'update_time',
            'delete_by',
            'delete_date',
            'delete_time',
        ), true);
    }
}

if (!function_exists('customerDailyReportSplitSqlCsv')) {
    function customerDailyReportSplitSqlCsv($segment)
    {
        $parts = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", (string) $segment);
        return is_array($parts) ? $parts : array();
    }
}

if (!function_exists('customerDailyReportParseInsertQueryDetails')) {
    function customerDailyReportParseInsertQueryDetails($connect, $financeConnect, $platformKey, $queryRecord)
    {
        $details = array();
        $queryRecord = trim((string) $queryRecord);
        if ($queryRecord === '') {
            return $details;
        }

        if (!preg_match('/INSERT\s+INTO\s+.+?\((.*?)\)\s*VALUES\s*\((.*?)\)/is', $queryRecord, $matches)) {
            return $details;
        }

        $columns = customerDailyReportSplitSqlCsv($matches[1] ?? '');
        $values = customerDailyReportSplitSqlCsv($matches[2] ?? '');
        $pairCount = min(count($columns), count($values));

        for ($i = 0; $i < $pairCount; $i++) {
            $fieldName = trim((string) ($columns[$i] ?? ''));
            $fieldName = trim($fieldName, "` \t\n\r\0\x0B");
            if ($fieldName === '' || customerDailyReportShouldSkipAuditField($fieldName) || customerDailyReportLooksLikeIdNoise($fieldName)) {
                continue;
            }

            $rawValue = trim((string) ($values[$i] ?? ''));
            $rawValue = trim($rawValue, " \t\n\r\0\x0B");
            if (preg_match('/^(curdate\(\)|curtime\(\)|now\(\))$/i', $rawValue)) {
                continue;
            }

            $newValue = customerDailyReportNormalizeParsedAuditValue($rawValue);
            $newValue = normalizeAuditLogValue(customerDailyReportResolveFieldValue($connect, $financeConnect, $platformKey, $fieldName, $newValue));

            $details[] = array(
                'field_name' => customerDailyReportGetFieldLabel($platformKey, $fieldName),
                'old_value' => 'Empty Value',
                'new_value' => $newValue,
                'change_summary' => customerDailyReportBuildChangeSummary(customerDailyReportGetFieldLabel($platformKey, $fieldName), 'Empty Value', $newValue),
            );
        }

        return $details;
    }
}

if (!function_exists('customerDailyReportParseChangeDetails')) {
    function customerDailyReportParseChangeDetails($connect, $financeConnect, $auditRow)
    {
        $details = array();
        if (!is_array($auditRow)) {
            return $details;
        }

        $platformConfig = customerDailyReportGetPlatformConfigByTable(isset($auditRow['query_table']) ? $auditRow['query_table'] : '');
        $platformKey = isset($platformConfig['platform']) ? (string) $platformConfig['platform'] : '';
        $logAction = isset($auditRow['log_action']) ? (int) $auditRow['log_action'] : 0;
        $isAddAction = $logAction === (int) get_allowed_audit_actions('add');
        $actionMessage = (string) ($auditRow['action_message'] ?? '');
        $pattern = "/\\[\\s*<b>\\s*([^\\[\\]]*?)\\s*<\\/b>\\s*:\\s*<b>(.*?)<\\/b>(?:\\s*to\\s*<b>(.*?)<\\/b>)?\\s*\\]/is";

        if (preg_match_all($pattern, $actionMessage, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fieldName = trim(html_entity_decode(strip_tags((string) ($match[1] ?? '')), ENT_QUOTES, 'UTF-8'));
                if (customerDailyReportLooksLikeIdNoise($fieldName)) {
                    continue;
                }

                if ($isAddAction) {
                    $oldValue = 'Empty Value';
                    $newValue = customerDailyReportNormalizeParsedAuditValue($match[2] ?? '');
                } else {
                    $oldValue = customerDailyReportNormalizeParsedAuditValue($match[2] ?? '');
                    $newValue = array_key_exists(3, $match)
                        ? customerDailyReportNormalizeParsedAuditValue($match[3])
                        : 'Empty Value';
                }

                $oldValue = normalizeAuditLogValue(customerDailyReportResolveFieldValue($connect, $financeConnect, $platformKey, $fieldName, $oldValue));
                $newValue = normalizeAuditLogValue(customerDailyReportResolveFieldValue($connect, $financeConnect, $platformKey, $fieldName, $newValue));

                if ($fieldName === '') {
                    continue;
                }

                $details[] = array(
                    'field_name' => customerDailyReportGetFieldLabel($platformKey, $fieldName),
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'change_summary' => customerDailyReportBuildChangeSummary(customerDailyReportGetFieldLabel($platformKey, $fieldName), $oldValue, $newValue),
                );
            }
        }

        if (!empty($details)) {
            return $details;
        }

        if ($isAddAction) {
            $details = customerDailyReportParseInsertQueryDetails($connect, $financeConnect, $platformKey, $auditRow['query_record'] ?? '');
            if (!empty($details)) {
                return $details;
            }
        }

        $fallbackOldValue = $isAddAction
            ? 'Empty Value'
            : normalizeAuditLogValue($auditRow['old_value'] ?? '');
        $fallbackNewValue = $isAddAction
            ? normalizeAuditLogValue($auditRow['new_value'] ?? '')
            : normalizeAuditLogValue($auditRow['changes'] ?? '');
        return array(
            array(
                'field_name' => 'Audit Log',
                'old_value' => $fallbackOldValue,
                'new_value' => $fallbackNewValue,
                'change_summary' => 'See audit log message for the full field breakdown.',
            ),
        );
    }
}

if (!function_exists('customerDailyReportLoadUserNameMap')) {
    function customerDailyReportLoadUserNameMap($connect, $userIds)
    {
        $userNameMap = array();
        if (!($connect instanceof mysqli) || !is_array($userIds)) {
            return $userNameMap;
        }

        $safeIds = array();
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0) {
                $safeIds[$userId] = $userId;
            }
        }

        if (empty($safeIds)) {
            return $userNameMap;
        }

        $query = "SELECT `id`, `username` FROM `" . USR_USER . "` WHERE `id` IN (" . implode(',', $safeIds) . ")";
        $result = mysqli_query($connect, $query);
        if (!$result) {
            return $userNameMap;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $userId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($userId <= 0) {
                continue;
            }

            $userNameMap[$userId] = isset($row['username']) && trim((string) $row['username']) !== ''
                ? trim((string) $row['username'])
                : ('User #' . $userId);
        }

        return $userNameMap;
    }
}

if (!function_exists('customerDailyReportLoadUserMetaMap')) {
    function customerDailyReportLoadUserMetaMap($connect, $userIds)
    {
        $userMetaMap = array();
        if (!($connect instanceof mysqli) || !is_array($userIds)) {
            return $userMetaMap;
        }

        $safeIds = array();
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0) {
                $safeIds[$userId] = $userId;
            }
        }

        if (empty($safeIds)) {
            return $userMetaMap;
        }

        $query = "SELECT `id`, `name`, `username`, `access_id` FROM `" . USR_USER . "` WHERE `id` IN (" . implode(',', $safeIds) . ")";
        $result = mysqli_query($connect, $query);
        if (!$result) {
            return $userMetaMap;
        }

        $groupIds = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $userId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($userId <= 0) {
                continue;
            }

            $accessId = isset($row['access_id']) ? (int) $row['access_id'] : 0;
            if ($accessId > 0) {
                $groupIds[$accessId] = $accessId;
            }

            $displayName = '';
            if (isset($row['name']) && trim((string) $row['name']) !== '') {
                $displayName = trim((string) $row['name']);
            } else if (isset($row['username']) && trim((string) $row['username']) !== '') {
                $displayName = trim((string) $row['username']);
            } else {
                $displayName = 'User #' . $userId;
            }

            $userMetaMap[$userId] = array(
                'display_name' => $displayName,
                'username' => isset($row['username']) ? trim((string) $row['username']) : '',
                'access_id' => $accessId,
                'group_name' => '',
                'group_badge_html' => '',
            );
        }

        if (!empty($groupIds)) {
            $groupQuery = "SELECT `id`, `name`, `badge_color`, `badge_icon_class` FROM `" . USR_GRP . "` WHERE `id` IN (" . implode(',', $groupIds) . ")";
            $groupResult = mysqli_query($connect, $groupQuery);
            if ($groupResult) {
                $groupMetaMap = array();
                while ($groupRow = mysqli_fetch_assoc($groupResult)) {
                    $groupId = isset($groupRow['id']) ? (int) $groupRow['id'] : 0;
                    if ($groupId > 0) {
                        $groupMetaMap[$groupId] = array(
                            'name' => isset($groupRow['name']) ? (string) $groupRow['name'] : '',
                            'badge_color' => isset($groupRow['badge_color']) ? (string) $groupRow['badge_color'] : '',
                            'badge_icon_class' => isset($groupRow['badge_icon_class']) ? (string) $groupRow['badge_icon_class'] : '',
                        );
                    }
                }

                foreach ($userMetaMap as $userId => $userMeta) {
                    $groupId = isset($userMeta['access_id']) ? (int) $userMeta['access_id'] : 0;
                    if ($groupId <= 0 || !isset($groupMetaMap[$groupId])) {
                        continue;
                    }

                    $groupMeta = $groupMetaMap[$groupId];
                    $userMetaMap[$userId]['group_name'] = isset($groupMeta['name']) ? (string) $groupMeta['name'] : '';
                    $userMetaMap[$userId]['group_badge_html'] = shopeeOmsRenderUserGroupBadge($connect, $groupId);
                }
            }
        }

        return $userMetaMap;
    }
}

if (!function_exists('customerDailyReportGetDisplayNameFromRow')) {
    function customerDailyReportGetDisplayNameFromRow($row, $displayFields)
    {
        if (!is_array($row) || !is_array($displayFields)) {
            return '';
        }

        $parts = array();
        foreach ($displayFields as $fieldName) {
            $fieldName = (string) $fieldName;
            if (!isset($row[$fieldName])) {
                continue;
            }

            $fieldValue = trim((string) $row[$fieldName]);
            if ($fieldValue !== '') {
                $parts[] = $fieldValue;
            }
        }

        return trim(implode(' ', $parts));
    }
}

if (!function_exists('customerDailyReportGetCustomerMeta')) {
    function customerDailyReportGetCustomerMeta($connect, $financeConnect, $platformKey, $recordId)
    {
        static $customerMetaCache = array();

        $recordId = (int) $recordId;
        $platformKey = customerDailyReportNormalizePlatformKey($platformKey);
        $cacheKey = $platformKey . ':' . $recordId;
        if (isset($customerMetaCache[$cacheKey])) {
            return $customerMetaCache[$cacheKey];
        }

        $meta = array(
            'display_name' => $recordId > 0 ? ('Record #' . $recordId) : 'Unknown Record',
            'record_url' => '',
        );

        $platformConfigs = customerDailyReportGetPlatformConfigs();
        if ($platformKey === '' || !isset($platformConfigs[$platformKey])) {
            $customerMetaCache[$cacheKey] = $meta;
            return $meta;
        }

        $platformConfig = $platformConfigs[$platformKey];
        if ($recordId > 0) {
            $meta['record_url'] = rtrim((string) $GLOBALS['SITEURL'], '/') . (string) $platformConfig['record_url'] . '?id=' . $recordId;
        }

        $dbConnect = customerDailyReportResolveDbConnect($connect, $financeConnect, $platformConfig['db'] ?? 'cms');
        if (!($dbConnect instanceof mysqli) || $recordId <= 0) {
            $customerMetaCache[$cacheKey] = $meta;
            return $meta;
        }

        $tblName = isset($platformConfig['table']) ? (string) $platformConfig['table'] : '';
        if ($tblName === '') {
            $customerMetaCache[$cacheKey] = $meta;
            return $meta;
        }

        $result = getData('*', "id = '" . $recordId . "'", 'LIMIT 1', $tblName, $dbConnect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $displayName = customerDailyReportGetDisplayNameFromRow($row, $platformConfig['display_fields'] ?? array());
            if ($displayName !== '') {
                $meta['display_name'] = $displayName;
            }
        }

        $customerMetaCache[$cacheKey] = $meta;
        return $meta;
    }
}

if (!function_exists('shopeeOmsGetOrderSourceConfigs')) {
    function shopeeOmsGetOrderSourceConfigs()
    {
        return array(
            'shopee' => array(
                'platform' => 'shopee',
                'label' => 'Shopee',
                'table' => SHOPEE_SG_ORDER_REQ,
                'db' => 'finance',
                'db_name' => dbFinance,
                'order_code_field' => 'orderID',
                'customer_name_field' => 'buyer',
                'customer_label_mode' => 'shopee_buyer',
                'address_field' => 'customer_address',
                'package_field' => 'package',
                'package_qty_json_field' => 'package_qty_json',
                'airbill_no_field' => 'airbill_no',
                'airbill_attachment_field' => 'airbill_attachment',
                'warehouse_field' => 'stock_out_warehouse_id',
                'delay_remark_field' => 'delay_remark',
                'date_field' => 'date',
                'fallback_code_prefix' => 'SHP',
                'view_url' => '/shopee/shopee_order_req.php',
                'info_url' => '/shopee/shopee_order_request_info.php',
                'attachment_page_name' => 'shopee_order_request',
            ),
            'lazada' => array(
                'platform' => 'lazada',
                'label' => 'Lazada',
                'table' => LAZADA_ORDER_REQ,
                'db' => 'cms',
                'db_name' => dbname,
                'order_code_field' => 'oder_number',
                'customer_name_field' => 'cust_name',
                'address_field' => 'ship_rec_address',
                'package_field' => 'pkg',
                'package_qty_json_field' => '',
                'airbill_no_field' => 'airbill_no',
                'airbill_attachment_field' => 'airbill_attachment',
                'warehouse_field' => 'stock_out_warehouse_id',
                'delay_remark_field' => 'delay_remark',
                'date_field' => 'create_date',
                'fallback_code_prefix' => 'LAZ',
                'view_url' => '/finance/lazada_order_req.php',
                'info_url' => '/finance/lazada_order_request_info.php',
                'attachment_page_name' => 'lazada_order_request',
            ),
            'facebook' => array(
                'platform' => 'facebook',
                'label' => 'Facebook',
                'table' => FB_ORDER_REQ,
                'db' => 'finance',
                'db_name' => dbFinance,
                'order_code_field' => '',
                'customer_name_field' => 'name',
                'address_field' => 'ship_rec_add',
                'package_field' => 'package',
                'package_qty_json_field' => '',
                'airbill_no_field' => 'airbill_no',
                'airbill_attachment_field' => 'airbill_attachment',
                'warehouse_field' => 'stock_out_warehouse_id',
                'delay_remark_field' => 'delay_remark',
                'date_field' => 'create_date',
                'fallback_code_prefix' => 'FB',
                'view_url' => '/finance/fb_order_req.php',
                'info_url' => '/finance/fb_order_request_info.php',
                'attachment_page_name' => 'fb_order_request',
            ),
            'website' => array(
                'platform' => 'website',
                'label' => 'Website',
                'table' => WEB_ORDER_REQ,
                'db' => 'finance',
                'db_name' => dbFinance,
                'order_code_field' => 'order_id',
                'customer_name_field' => 'cust_name',
                'address_field' => 'shipping_address',
                'package_field' => 'pkg',
                'package_qty_json_field' => '',
                'airbill_no_field' => 'airbill_no',
                'airbill_attachment_field' => 'airbill_attachment',
                'warehouse_field' => 'stock_out_warehouse_id',
                'delay_remark_field' => 'delay_remark',
                'date_field' => 'create_date',
                'fallback_code_prefix' => 'WEB',
                'view_url' => '/finance/website_order_request.php',
                'info_url' => '/finance/website_order_request_info.php',
                'attachment_page_name' => 'website_order_request',
            ),
        );
    }
}

if (!function_exists('shopeeOmsNormalizePlatformKey')) {
    function shopeeOmsNormalizePlatformKey($platform, $allowAll = false)
    {
        $platform = strtolower(trim((string) $platform));
        if ($allowAll && $platform === 'all') {
            return 'all';
        }

        $configs = shopeeOmsGetOrderSourceConfigs();
        return isset($configs[$platform]) ? $platform : '';
    }
}

if (!function_exists('shopeeOmsGetOrderSourceConfig')) {
    function shopeeOmsGetOrderSourceConfig($platform)
    {
        $platform = shopeeOmsNormalizePlatformKey($platform);
        $configs = shopeeOmsGetOrderSourceConfigs();
        return $platform !== '' && isset($configs[$platform]) ? $configs[$platform] : array();
    }
}

if (!function_exists('shopeeOmsResolvePlatformFromTableName')) {
    function shopeeOmsResolvePlatformFromTableName($tblName)
    {
        $tblName = trim((string) $tblName);
        if ($tblName === '') {
            return '';
        }

        foreach (shopeeOmsGetOrderSourceConfigs() as $platform => $config) {
            if (isset($config['table']) && (string) $config['table'] === $tblName) {
                return $platform;
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOmsResolveOrderSourceConfig')) {
    function shopeeOmsResolveOrderSourceConfig($source = null, $fallbackPlatform = 'shopee')
    {
        if (is_array($source) && !empty($source['platform'])) {
            return shopeeOmsGetOrderSourceConfig($source['platform']);
        }

        $sourceValue = trim((string) $source);
        if ($sourceValue !== '') {
            $platform = shopeeOmsNormalizePlatformKey($sourceValue);
            if ($platform !== '') {
                return shopeeOmsGetOrderSourceConfig($platform);
            }

            $platform = shopeeOmsResolvePlatformFromTableName($sourceValue);
            if ($platform !== '') {
                return shopeeOmsGetOrderSourceConfig($platform);
            }
        }

        return shopeeOmsGetOrderSourceConfig($fallbackPlatform);
    }
}

if (!function_exists('shopeeOmsGetOrderSourceDbConnection')) {
    function shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig)
    {
        $sourceConfig = is_array($sourceConfig) ? $sourceConfig : array();
        $dbKey = isset($sourceConfig['db']) ? (string) $sourceConfig['db'] : 'finance';
        return $dbKey === 'cms' ? $cmsConnect : $financeConnect;
    }
}

if (!function_exists('shopeeOmsBuildQualifiedTableName')) {
    function shopeeOmsBuildQualifiedTableName($sourceConfig)
    {
        $sourceConfig = is_array($sourceConfig) ? $sourceConfig : array();
        $dbName = isset($sourceConfig['db_name']) ? trim((string) $sourceConfig['db_name']) : '';
        $tblName = isset($sourceConfig['table']) ? trim((string) $sourceConfig['table']) : '';
        if ($dbName === '' || $tblName === '') {
            return '';
        }

        return '`' . str_replace('`', '``', $dbName) . '`.`' . str_replace('`', '``', $tblName) . '`';
    }
}

if (!function_exists('shopeeOmsAttachOrderSourceMeta')) {
    function shopeeOmsAttachOrderSourceMeta($row, $platform, $sourceConfig = null)
    {
        $row = is_array($row) ? $row : array();
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($sourceConfig ?: $platform, $platform ?: 'shopee');
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : shopeeOmsNormalizePlatformKey($platform);
        if ($platform === '') {
            $platform = 'shopee';
            $sourceConfig = shopeeOmsGetOrderSourceConfig($platform);
        }

        $row['__oms_platform'] = $platform;
        $row['__oms_platform_label'] = isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platform);
        $row['__oms_table'] = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '';
        $row['__oms_db'] = isset($sourceConfig['db']) ? (string) $sourceConfig['db'] : '';
        return $row;
    }
}

if (!function_exists('shopeeOmsGetOrderSourcePlatform')) {
    function shopeeOmsGetOrderSourcePlatform($row, $fallbackPlatform = 'shopee')
    {
        if (is_array($row) && !empty($row['__oms_platform'])) {
            $platform = shopeeOmsNormalizePlatformKey($row['__oms_platform']);
            if ($platform !== '') {
                return $platform;
            }
        }

        return shopeeOmsNormalizePlatformKey($fallbackPlatform) ?: 'shopee';
    }
}

if (!function_exists('shopeeOmsTableHasColumn')) {
    function shopeeOmsTableHasColumn($connect, $dbName, $tblName, $columnName)
    {
        static $cache = array();

        $dbName = trim((string) $dbName);
        $tblName = trim((string) $tblName);
        $columnName = trim((string) $columnName);
        if (!($connect instanceof mysqli) || $dbName === '' || $tblName === '' || $columnName === '') {
            return false;
        }

        $cacheKey = $dbName . '|' . $tblName . '|' . $columnName;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $safeDbName = mysqli_real_escape_string($connect, $dbName);
        $safeTableName = mysqli_real_escape_string($connect, $tblName);
        $safeColumnName = mysqli_real_escape_string($connect, $columnName);
        $sql = "SELECT 1
            FROM information_schema.columns
            WHERE table_schema = '" . $safeDbName . "'
              AND table_name = '" . $safeTableName . "'
              AND column_name = '" . $safeColumnName . "'
            LIMIT 1";
        $result = mysqli_query($connect, $sql);
        $cache[$cacheKey] = ($result instanceof mysqli_result && mysqli_num_rows($result) > 0);
        return $cache[$cacheKey];
    }
}

if (!function_exists('shopeeOmsSourceHasColumn')) {
    function shopeeOmsSourceHasColumn($cmsConnect, $financeConnect, $sourceConfig, $columnName)
    {
        $sourceConfig = is_array($sourceConfig) ? $sourceConfig : array();
        $tblName = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '';
        $dbName = isset($sourceConfig['db_name']) ? (string) $sourceConfig['db_name'] : '';
        $conn = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
        return shopeeOmsTableHasColumn($conn, $dbName, $tblName, $columnName);
    }
}

if (!function_exists('shopeeOmsGetOrderCodeValue')) {
    function shopeeOmsGetOrderCodeValue($orderRow, $source = 'shopee')
    {
        $orderRow = is_array($orderRow) ? $orderRow : array();
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source, shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $fieldName = isset($sourceConfig['order_code_field']) ? trim((string) $sourceConfig['order_code_field']) : '';
        $orderCode = $fieldName !== '' && isset($orderRow[$fieldName]) ? trim((string) $orderRow[$fieldName]) : '';
        if ($orderCode !== '') {
            return $orderCode;
        }

        $fallbackPrefix = isset($sourceConfig['fallback_code_prefix']) ? trim((string) $sourceConfig['fallback_code_prefix']) : 'OMS';
        return $fallbackPrefix . '-' . (int) (isset($orderRow['id']) ? $orderRow['id'] : 0);
    }
}

if (!function_exists('shopeeOmsGetOrderSourceViewUrl')) {
    function shopeeOmsGetOrderSourceViewUrl($source, $orderId)
    {
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source);
        $viewUrl = isset($sourceConfig['view_url']) ? trim((string) $sourceConfig['view_url']) : '';

        if ($viewUrl === '' || !defined('SITEURL')) {
            return '';
        }

        return rtrim((string) SITEURL, '/') . $viewUrl . '?id=' . (int) $orderId;
    }
}


if (!function_exists('shopeeOmsGetOrderSourceInfoUrl')) {
    function shopeeOmsGetOrderSourceInfoUrl($source, $orderId)
    {
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source);
        $infoUrl = isset($sourceConfig['info_url']) ? trim((string) $sourceConfig['info_url']) : '';
        if ($infoUrl === '' || !defined('SITEURL')) {
            return '';
        }

        return rtrim((string) SITEURL, '/') . $infoUrl . '?id=' . (int) $orderId;
    }
}

if (!function_exists('shopeeOmsGetOrderCustomerNameText')) {
    function shopeeOmsGetOrderCustomerNameText($cmsConnect, $financeConnect, $orderRow, $source = 'shopee')
    {
        $orderRow = is_array($orderRow) ? $orderRow : array();
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source, shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
        $fieldName = isset($sourceConfig['customer_name_field']) ? (string) $sourceConfig['customer_name_field'] : '';
        $customerName = $fieldName !== '' && isset($orderRow[$fieldName]) ? trim((string) $orderRow[$fieldName]) : '';

        if ($platform === 'shopee' && $customerName !== '' && ctype_digit($customerName)) {
            $buyerRst = getData('buyer_username', "id='" . (int) $customerName . "'", 'LIMIT 1', SHOPEE_CUST_INFO, $financeConnect);
            if ($buyerRst && $buyerRst->num_rows > 0) {
                $buyerRow = $buyerRst->fetch_assoc();
                if (isset($buyerRow['buyer_username']) && trim((string) $buyerRow['buyer_username']) !== '') {
                    $customerName = trim((string) $buyerRow['buyer_username']);
                }
            }
        }

        return $customerName;
    }
}

if (!function_exists('customerLabelGetPlatformConfig')) {
    function customerLabelGetPlatformConfig($platform)
    {
        $configs = customerLabelGetPlatformConfigs();
        return isset($configs[$platform]) ? $configs[$platform] : array();
    }
}

if (!function_exists('customerLabelGetPlatformLabel')) {
    function customerLabelGetPlatformLabel($platform)
    {
        $config = customerLabelGetPlatformConfig($platform);
        return isset($config['label']) ? (string) $config['label'] : ucfirst((string) $platform);
    }
}

if (!function_exists('customerLabelGetPlatformRecordUrl')) {
    function customerLabelGetPlatformRecordUrl($platform)
    {
        $config = customerLabelGetPlatformConfig($platform);
        $baseUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
        $path = isset($config['record_url']) ? (string) $config['record_url'] : '';
        if ($baseUrl === '' || $path === '') {
            return $path;
        }

        return $baseUrl . $path;
    }
}

if (!function_exists('customerLabelSplitCsv')) {
    function customerLabelSplitCsv($value)
    {
        $parts = array_filter(array_map('trim', explode(',', (string) $value)), 'strlen');
        return array_values($parts);
    }
}

if (!function_exists('customerLabelNormalizeLookupKey')) {
    function customerLabelNormalizeLookupKey($value)
    {
        return strtolower(trim((string) $value));
    }
}

if (!function_exists('customerLabelSafeFloat')) {
    function customerLabelSafeFloat($value)
    {
        $value = str_replace(',', '', trim((string) $value));
        return is_numeric($value) ? (float) $value : 0.0;
    }
}

if (!function_exists('customerLabelResolveInt')) {
    function customerLabelResolveInt($value)
    {
        $value = trim((string) $value);
        return ctype_digit($value) ? (int) $value : 0;
    }
}

if (!function_exists('customerLabelFetchRows')) {
    function customerLabelFetchRows($conn, $sql)
    {
        $rows = array();
        if (!($conn instanceof mysqli)) {
            return $rows;
        }

        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('customerLabelFetchActiveRows')) {
    function customerLabelFetchActiveRows($conn, $tblName, $columns = '*', $extraWhere = '', $orderBy = 'ORDER BY id ASC')
    {
        if (!($conn instanceof mysqli) || $tblName === '' || !tableExists($tblName, $conn)) {
            return array();
        }

        $where = "WHERE status = 'A'";
        if ($extraWhere !== '') {
            $where .= " AND " . $extraWhere;
        }

        $sql = "SELECT " . $columns . " FROM `" . $tblName . "` " . $where . " " . $orderBy;
        return customerLabelFetchRows($conn, $sql);
    }
}

if (!function_exists('customerLabelGetCurrencyRateLookup')) {
    function customerLabelGetCurrencyRateLookup($connect)
    {
        $lookup = array();
        if (!($connect instanceof mysqli) || !defined('CURRENCIES') || !tableExists(CURRENCIES, $connect)) {
            return $lookup;
        }

        $rows = customerLabelFetchRows($connect, "SELECT `default_currency_unit`, `exchange_currency_unit`, `exchange_currency_rate` FROM `" . CURRENCIES . "` WHERE status = 'A'");
        foreach ($rows as $row) {
            $fromCurrency = customerLabelResolveInt(isset($row['default_currency_unit']) ? $row['default_currency_unit'] : '');
            $toCurrency = customerLabelResolveInt(isset($row['exchange_currency_unit']) ? $row['exchange_currency_unit'] : '');
            $rate = customerLabelSafeFloat(isset($row['exchange_currency_rate']) ? $row['exchange_currency_rate'] : 0);
            if ($fromCurrency > 0 && $toCurrency > 0 && $rate > 0) {
                if (!isset($lookup[$fromCurrency])) {
                    $lookup[$fromCurrency] = array();
                }
                $lookup[$fromCurrency][$toCurrency] = $rate;
            }
        }

        return $lookup;
    }
}

if (!function_exists('customerLabelConvertAmount')) {
    function customerLabelConvertAmount($amount, $fromCurrencyId, $toCurrencyId, $currencyRateLookup)
    {
        $amount = (float) $amount;
        $fromCurrencyId = (int) $fromCurrencyId;
        $toCurrencyId = (int) $toCurrencyId;

        if ($amount == 0 || $fromCurrencyId <= 0 || $toCurrencyId <= 0 || $fromCurrencyId === $toCurrencyId) {
            return $amount;
        }

        if (isset($currencyRateLookup[$fromCurrencyId][$toCurrencyId]) && $currencyRateLookup[$fromCurrencyId][$toCurrencyId] > 0) {
            return $amount * (float) $currencyRateLookup[$fromCurrencyId][$toCurrencyId];
        }

        if (isset($currencyRateLookup[$toCurrencyId][$fromCurrencyId]) && $currencyRateLookup[$toCurrencyId][$fromCurrencyId] > 0) {
            return $amount / (float) $currencyRateLookup[$toCurrencyId][$fromCurrencyId];
        }

        return $amount;
    }
}

if (!function_exists('customerLabelSumAmountsForCurrency')) {
    function customerLabelSumAmountsForCurrency($amountsByCurrency, $targetCurrencyId, $currencyRateLookup, $defaultCurrencyId = 1)
    {
        $targetCurrencyId = (int) $targetCurrencyId;
        if ($targetCurrencyId <= 0) {
            $targetCurrencyId = (int) $defaultCurrencyId;
        }

        $total = 0.0;
        foreach ((array) $amountsByCurrency as $currencyId => $amount) {
            $currencyId = (int) $currencyId;
            $total += customerLabelConvertAmount((float) $amount, $currencyId, $targetCurrencyId, $currencyRateLookup);
        }

        return $total;
    }
}

if (!function_exists('customerLabelGetSeriesLookup')) {
    function customerLabelGetSeriesLookup($connect)
    {
        $lookup = array(
            'by_id' => array(),
            'by_name' => array(),
            'brand_by_id' => array(),
        );

        if (!($connect instanceof mysqli) || !defined('BRD_SERIES') || !tableExists(BRD_SERIES, $connect)) {
            return $lookup;
        }

        $rows = customerLabelFetchRows($connect, "SELECT `id`, `name`, `brand` FROM `" . BRD_SERIES . "` WHERE status = 'A'");
        foreach ($rows as $row) {
            $seriesId = (int) $row['id'];
            $seriesName = isset($row['name']) ? trim((string) $row['name']) : '';
            $brandId = customerLabelResolveInt(isset($row['brand']) ? $row['brand'] : 0);
            if ($seriesId > 0) {
                $lookup['by_id'][$seriesId] = $seriesName;
                $lookup['brand_by_id'][$seriesId] = $brandId;
            }
            if ($seriesName !== '') {
                $lookup['by_name'][customerLabelNormalizeLookupKey($seriesName)] = $seriesId;
            }
        }

        return $lookup;
    }
}

if (!function_exists('customerLabelResolveSeriesId')) {
    function customerLabelResolveSeriesId($seriesValue, $seriesLookup)
    {
        $seriesId = customerLabelResolveInt($seriesValue);
        if ($seriesId > 0 && isset($seriesLookup['by_id'][$seriesId])) {
            return $seriesId;
        }

        $seriesKey = customerLabelNormalizeLookupKey($seriesValue);
        if ($seriesKey !== '' && isset($seriesLookup['by_name'][$seriesKey])) {
            return (int) $seriesLookup['by_name'][$seriesKey];
        }

        return 0;
    }
}

if (!function_exists('customerLabelBuildPackageBoxCountMap')) {
    function customerLabelBuildPackageBoxCountMap($connect, $packageIds)
    {
        $map = array();
        $packageIds = array_values(array_unique(array_filter(array_map('intval', (array) $packageIds))));
        if (!($connect instanceof mysqli) || empty($packageIds) || !defined('PKG') || !tableExists(PKG, $connect)) {
            return $map;
        }

        $sql = "SELECT `id`, `product`, `brand` FROM `" . PKG . "` WHERE `status` = 'A' AND `id` IN (" . implode(',', $packageIds) . ")";
        $rows = customerLabelFetchRows($connect, $sql);
        foreach ($rows as $row) {
            $packageId = (int) $row['id'];
            $products = customerLabelSplitCsv(isset($row['product']) ? $row['product'] : '');
            $map[$packageId] = array(
                'box_count' => count($products),
                'brand_id' => customerLabelResolveInt(isset($row['brand']) ? $row['brand'] : 0),
            );
        }

        return $map;
    }
}

if (!function_exists('customerLabelResolvePackageRows')) {
    function customerLabelResolvePackageRows($connect, $platform, $orderRow)
    {
        if ($platform === 'shopee' && function_exists('shopeeOmsResolveOrderPackageRows')) {
            return shopeeOmsResolveOrderPackageRows($connect, $orderRow);
        }

        $fieldName = $platform === 'facebook' ? 'package' : 'pkg';
        $rows = array();
        $packageIds = array();
        foreach (customerLabelSplitCsv(isset($orderRow[$fieldName]) ? $orderRow[$fieldName] : '') as $packageIdRaw) {
            $packageIdRaw = trim((string) $packageIdRaw);
            if ($packageIdRaw === '') {
                continue;
            }

            if (ctype_digit($packageIdRaw) && (int) $packageIdRaw > 0) {
                $packageId = (int) $packageIdRaw;
                $packageIds[] = $packageId;
                $rows[] = array(
                    'package_id' => $packageId,
                    'package_name' => '',
                    'qty' => 1,
                );
            } else {
                $rows[] = array(
                    'package_id' => 0,
                    'package_name' => $packageIdRaw,
                    'qty' => 1,
                );
            }
        }

        if (!empty($packageIds) && function_exists('shopeeOmsGetPackageNameMap')) {
            $packageNameMap = shopeeOmsGetPackageNameMap($connect, $packageIds);
            foreach ($rows as $idx => $row) {
                $packageId = isset($row['package_id']) ? (int) $row['package_id'] : 0;
                if ($packageId > 0 && empty($rows[$idx]['package_name']) && isset($packageNameMap[$packageId])) {
                    $rows[$idx]['package_name'] = (string) $packageNameMap[$packageId];
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('customerLabelGetOrderBoxMetrics')) {
    function customerLabelGetOrderBoxMetrics($connect, $platform, $orderRow, $packageBoxCountMap)
    {
        $metrics = array(
            'total' => 0.0,
            'by_brand' => array(),
        );
        $packageRows = customerLabelResolvePackageRows($connect, $platform, $orderRow);
        foreach ($packageRows as $packageRow) {
            $packageId = isset($packageRow['package_id']) ? (int) $packageRow['package_id'] : 0;
            $qty = isset($packageRow['qty']) ? customerLabelSafeFloat($packageRow['qty']) : 1;
            if ($qty <= 0) {
                $qty = 1;
            }

            if ($packageId > 0 && isset($packageBoxCountMap[$packageId])) {
                $packageInfo = (array) $packageBoxCountMap[$packageId];
                $boxQuantity = (float) (isset($packageInfo['box_count']) ? $packageInfo['box_count'] : 0) * $qty;
                $brandId = isset($packageInfo['brand_id']) ? (int) $packageInfo['brand_id'] : 0;
                $metrics['total'] += $boxQuantity;

                if ($brandId > 0) {
                    if (!isset($metrics['by_brand'][$brandId])) {
                        $metrics['by_brand'][$brandId] = 0.0;
                    }
                    $metrics['by_brand'][$brandId] += $boxQuantity;
                }
            }
        }

        return $metrics;
    }
}

if (!function_exists('customerLabelGetOrderBoxQuantity')) {
    function customerLabelGetOrderBoxQuantity($connect, $platform, $orderRow, $packageBoxCountMap)
    {
        $metrics = customerLabelGetOrderBoxMetrics($connect, $platform, $orderRow, $packageBoxCountMap);
        return isset($metrics['total']) ? (float) $metrics['total'] : 0.0;
    }
}

if (!function_exists('customerLabelBuildCustomerIndexes')) {
    function customerLabelBuildCustomerIndexes($platform, $rows, $seriesLookup)
    {
        $indexes = array(
            'rows_by_id' => array(),
            'lookup' => array(),
            'composite' => array(),
        );

        foreach ((array) $rows as $row) {
            $customerId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($customerId <= 0) {
                continue;
            }

            $row['_resolved_series_id'] = customerLabelResolveSeriesId(isset($row['series']) ? $row['series'] : '', $seriesLookup);
            $indexes['rows_by_id'][$customerId] = $row;

            if ($platform === 'shopee') {
                $lookupValue = isset($row['buyer_username']) ? $row['buyer_username'] : '';
                $lookupKey = customerLabelNormalizeLookupKey($lookupValue);
                if ($lookupKey !== '') {
                    $indexes['lookup'][$lookupKey] = $customerId;
                }
            } else if ($platform === 'lazada') {
                $lookupValue = isset($row['lcr_id']) ? $row['lcr_id'] : '';
                $lookupKey = customerLabelNormalizeLookupKey($lookupValue);
                if ($lookupKey !== '') {
                    $indexes['lookup'][$lookupKey] = $customerId;
                }
            } else if ($platform === 'website') {
                $lookupValue = isset($row['cust_id']) ? $row['cust_id'] : '';
                $lookupKey = customerLabelNormalizeLookupKey($lookupValue);
                if ($lookupKey !== '') {
                    $indexes['lookup'][$lookupKey] = $customerId;
                }
            } else if ($platform === 'facebook') {
                $compositeKey = customerLabelNormalizeLookupKey(isset($row['name']) ? $row['name'] : '') . '|' . customerLabelNormalizeLookupKey(isset($row['fb_link']) ? $row['fb_link'] : '');
                if ($compositeKey !== '|') {
                    $indexes['composite'][$compositeKey] = $customerId;
                }
            }
        }

        return $indexes;
    }
}

if (!function_exists('customerLabelResolveOrderCustomerId')) {
    function customerLabelResolveOrderCustomerId($platform, $orderRow, $customerIndexes)
    {
        if ($platform === 'facebook') {
            $compositeKey = customerLabelNormalizeLookupKey(isset($orderRow['name']) ? $orderRow['name'] : '') . '|' . customerLabelNormalizeLookupKey(isset($orderRow['fb_link']) ? $orderRow['fb_link'] : '');
            return isset($customerIndexes['composite'][$compositeKey]) ? (int) $customerIndexes['composite'][$compositeKey] : 0;
        }

        $fieldMap = array(
            'shopee' => 'buyer',
            'lazada' => 'cust_id',
            'website' => 'cust_id',
        );

        $fieldName = isset($fieldMap[$platform]) ? $fieldMap[$platform] : '';
        $rawValue = $fieldName !== '' && isset($orderRow[$fieldName]) ? trim((string) $orderRow[$fieldName]) : '';
        if ($rawValue === '') {
            return 0;
        }

        $directId = ctype_digit($rawValue) ? (int) $rawValue : 0;
        if ($directId > 0 && isset($customerIndexes['rows_by_id'][$directId])) {
            return $directId;
        }

        $lookupKey = customerLabelNormalizeLookupKey($rawValue);
        return isset($customerIndexes['lookup'][$lookupKey]) ? (int) $customerIndexes['lookup'][$lookupKey] : 0;
    }
}

if (!function_exists('customerLabelIsExcludedOrder')) {
    function customerLabelIsExcludedOrder($orderRow)
    {
        $recordStatus = isset($orderRow['status']) ? strtoupper(trim((string) $orderRow['status'])) : 'A';
        if ($recordStatus !== '' && $recordStatus !== 'A') {
            return true;
        }

        $statusValue = isset($orderRow['order_status']) ? (string) $orderRow['order_status'] : '';
        $normalizedCode = function_exists('shopeeOmsNormalizeStatusCode') ? shopeeOmsNormalizeStatusCode($statusValue) : trim((string) $statusValue);
        if (in_array($normalizedCode, array('R', 'CR'), true)) {
            return true;
        }

        $statusKey = normalizeOrderStatusKey($statusValue);
        $displayKey = normalizeOrderStatusKey(getMarketplaceRequestStatusLabel($statusValue));
        foreach (array($statusKey, $displayKey) as $key) {
            if ($key === 'deleted' || $key === 'return' || $key === 'closedreturned') {
                return true;
            }
            if (strpos($key, 'return') !== false || strpos($key, 'deleted') !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('customerLabelFetchRuleRows')) {
    function customerLabelFetchRuleRows($connect, $labelType)
    {
        $typeConfig = customerLabelGetTypeConfig($labelType);
        if (empty($typeConfig) || !isset($typeConfig['table'])) {
            return array();
        }

        $rows = customerLabelFetchActiveRows($connect, $typeConfig['table']);
        $seriesLookup = $labelType === 'segmentation' ? customerLabelGetSeriesLookup($connect) : array();
        foreach ($rows as &$row) {
            if ($labelType === 'segmentation') {
                $seriesId = customerLabelResolveInt(isset($row['brandSeries']) ? $row['brandSeries'] : 0);
                $row['_from'] = customerLabelSafeFloat(isset($row['boxFrom']) ? $row['boxFrom'] : 0);
                $row['_until'] = customerLabelSafeFloat(isset($row['boxUntil']) ? $row['boxUntil'] : 0);
                $row['_series_id'] = $seriesId;
                $row['_brand_id'] = $seriesId > 0 && isset($seriesLookup['brand_by_id'][$seriesId]) ? (int) $seriesLookup['brand_by_id'][$seriesId] : 0;
            } else if ($labelType === 'level') {
                $row['_from'] = customerLabelSafeFloat(isset($row['purchaseAmountFrom']) ? $row['purchaseAmountFrom'] : 0);
                $row['_until'] = customerLabelSafeFloat(isset($row['purchaseAmountUntil']) ? $row['purchaseAmountUntil'] : 0);
                $row['_currency_id'] = customerLabelResolveInt(isset($row['currency']) ? $row['currency'] : 0);
            } else if ($labelType === 'repeat') {
                $row['_from'] = customerLabelSafeFloat(isset($row['orderFrequencyFrom']) ? $row['orderFrequencyFrom'] : 0);
                $row['_until'] = customerLabelSafeFloat(isset($row['orderFrequencyUntil']) ? $row['orderFrequencyUntil'] : 0);
            }
        }
        unset($row);

        usort($rows, function ($left, $right) {
            $leftUntil = isset($left['_until']) ? (float) $left['_until'] : 0;
            $rightUntil = isset($right['_until']) ? (float) $right['_until'] : 0;
            if ($leftUntil === $rightUntil) {
                $leftFrom = isset($left['_from']) ? (float) $left['_from'] : 0;
                $rightFrom = isset($right['_from']) ? (float) $right['_from'] : 0;
                if ($leftFrom === $rightFrom) {
                    return (int) $right['id'] <=> (int) $left['id'];
                }
                return $rightFrom <=> $leftFrom;
            }
            return $rightUntil <=> $leftUntil;
        });

        return $rows;
    }
}

if (!function_exists('customerLabelFindMatchingRule')) {
    function customerLabelFindMatchingRule($metric, $rules)
    {
        foreach ((array) $rules as $rule) {
            $fromValue = isset($rule['_from']) ? (float) $rule['_from'] : 0;
            $untilValue = isset($rule['_until']) ? (float) $rule['_until'] : 0;
            if ($metric >= $fromValue && $metric <= $untilValue) {
                return $rule;
            }
        }

        return array();
    }
}

if (!function_exists('customerLabelBuildPlatformMetrics')) {
    function customerLabelBuildPlatformMetrics($connect, $financeConnect, $platform, $seriesLookup, $currencyRateLookup, $defaultCurrencyId = 1)
    {
        $platformConfig = customerLabelGetPlatformConfig($platform);
        if (empty($platformConfig)) {
            return array();
        }

        $customerConn = $platformConfig['customer_db'] === 'finance' ? $financeConnect : $connect;
        $orderConn = $platformConfig['order_db'] === 'finance' ? $financeConnect : $connect;
        if (!($customerConn instanceof mysqli) || !($orderConn instanceof mysqli)) {
            return array();
        }

        $customerRows = customerLabelFetchActiveRows($customerConn, $platformConfig['customer_table']);
        $customerIndexes = customerLabelBuildCustomerIndexes($platform, $customerRows, $seriesLookup);
        if (empty($customerIndexes['rows_by_id'])) {
            return array();
        }

        $orderRows = customerLabelFetchActiveRows($orderConn, $platformConfig['order_table']);
        $allPackageIds = array();
        foreach ($orderRows as $orderRow) {
            foreach (customerLabelResolvePackageRows($connect, $platform, $orderRow) as $packageRow) {
                $packageId = isset($packageRow['package_id']) ? (int) $packageRow['package_id'] : 0;
                if ($packageId > 0) {
                    $allPackageIds[] = $packageId;
                }
            }
        }
        $packageBoxCountMap = customerLabelBuildPackageBoxCountMap($connect, $allPackageIds);

        $metrics = array();
        foreach ($customerIndexes['rows_by_id'] as $customerId => $customerRow) {
            $metrics[$customerId] = array(
                'customer_id' => (int) $customerId,
                'series_id' => isset($customerRow['_resolved_series_id']) ? (int) $customerRow['_resolved_series_id'] : 0,
                'box_total' => 0.0,
                'box_by_brand' => array(),
                'order_count' => 0,
                'amounts_by_currency' => array(),
                'purchase_amount_default' => 0.0,
            );
        }

        foreach ($orderRows as $orderRow) {
            if (customerLabelIsExcludedOrder($orderRow)) {
                continue;
            }

            $customerId = customerLabelResolveOrderCustomerId($platform, $orderRow, $customerIndexes);
            if ($customerId <= 0 || !isset($metrics[$customerId])) {
                continue;
            }

            $orderBoxMetrics = customerLabelGetOrderBoxMetrics($connect, $platform, $orderRow, $packageBoxCountMap);
            $boxQuantity = isset($orderBoxMetrics['total']) ? (float) $orderBoxMetrics['total'] : 0.0;
            $metrics[$customerId]['box_total'] += $boxQuantity;
            foreach ((array) (isset($orderBoxMetrics['by_brand']) ? $orderBoxMetrics['by_brand'] : array()) as $brandId => $brandBoxQuantity) {
                $brandId = (int) $brandId;
                if ($brandId <= 0) {
                    continue;
                }
                if (!isset($metrics[$customerId]['box_by_brand'][$brandId])) {
                    $metrics[$customerId]['box_by_brand'][$brandId] = 0.0;
                }
                $metrics[$customerId]['box_by_brand'][$brandId] += (float) $brandBoxQuantity;
            }

            $metrics[$customerId]['order_count']++;

            $amountField = isset($platformConfig['order_amount_field']) ? (string) $platformConfig['order_amount_field'] : '';
            $amountValue = $amountField !== '' && isset($orderRow[$amountField]) ? customerLabelSafeFloat($orderRow[$amountField]) : 0.0;
            $currencyField = isset($platformConfig['order_currency_field']) ? (string) $platformConfig['order_currency_field'] : '';
            $currencyId = $currencyField !== '' && isset($orderRow[$currencyField]) ? customerLabelResolveInt($orderRow[$currencyField]) : 0;
            if ($currencyId <= 0) {
                $currencyId = (int) $defaultCurrencyId;
            }

            if (!isset($metrics[$customerId]['amounts_by_currency'][$currencyId])) {
                $metrics[$customerId]['amounts_by_currency'][$currencyId] = 0.0;
            }
            $metrics[$customerId]['amounts_by_currency'][$currencyId] += $amountValue;
        }

        foreach ($metrics as &$metric) {
            $metric['purchase_amount_default'] = customerLabelSumAmountsForCurrency(
                isset($metric['amounts_by_currency']) ? $metric['amounts_by_currency'] : array(),
                (int) $defaultCurrencyId,
                $currencyRateLookup,
                $defaultCurrencyId
            );
        }
        unset($metric);

        return $metrics;
    }
}

if (!function_exists('customerLabelBuildAssignmentRows')) {
    function customerLabelBuildAssignmentRows($connect, $financeConnect)
    {
        $defaultCurrencyId = 1;
        $seriesLookup = customerLabelGetSeriesLookup($connect);
        $currencyRateLookup = customerLabelGetCurrencyRateLookup($connect);
        $segmentationRules = customerLabelFetchRuleRows($connect, 'segmentation');
        $levelRules = customerLabelFetchRuleRows($connect, 'level');
        $repeatRules = customerLabelFetchRuleRows($connect, 'repeat');

        $assignmentRows = array();
        $summary = array(
            'processed_customers' => 0,
            'assignments_created' => 0,
            'platforms' => array(),
        );

        foreach (array_keys(customerLabelGetPlatformConfigs()) as $platform) {
            $metricsByCustomer = customerLabelBuildPlatformMetrics($connect, $financeConnect, $platform, $seriesLookup, $currencyRateLookup, $defaultCurrencyId);
            $summary['platforms'][$platform] = array(
                'customers' => count($metricsByCustomer),
                'assignments' => 0,
            );

            foreach ($metricsByCustomer as $customerId => $metric) {
                $summary['processed_customers']++;

                $segmentationMatch = array();
                foreach ($segmentationRules as $rule) {
                    $ruleBrandId = isset($rule['_brand_id']) ? (int) $rule['_brand_id'] : 0;
                    $boxMetric = $ruleBrandId > 0
                        ? (isset($metric['box_by_brand'][$ruleBrandId]) ? (float) $metric['box_by_brand'][$ruleBrandId] : 0.0)
                        : (float) $metric['box_total'];

                    if ($boxMetric >= (float) $rule['_from'] && $boxMetric <= (float) $rule['_until']) {
                        $segmentationMatch = $rule;
                        $segmentationMatch['_matched_box_quantity'] = $boxMetric;
                        break;
                    }
                }

                if (!empty($segmentationMatch)) {
                    $assignmentRows[] = array(
                        'label_type' => 'segmentation',
                        'label_id' => (int) $segmentationMatch['id'],
                        'platform' => $platform,
                        'customer_id' => (int) $customerId,
                        'purchase_amount' => (float) $metric['purchase_amount_default'],
                        'box_quantity' => isset($segmentationMatch['_matched_box_quantity']) ? (float) $segmentationMatch['_matched_box_quantity'] : (float) $metric['box_total'],
                        'order_count' => (int) $metric['order_count'],
                    );
                    $summary['assignments_created']++;
                    $summary['platforms'][$platform]['assignments']++;
                }

                foreach ($levelRules as $rule) {
                    $targetCurrencyId = isset($rule['_currency_id']) ? (int) $rule['_currency_id'] : $defaultCurrencyId;
                    $purchaseAmount = customerLabelSumAmountsForCurrency(
                        isset($metric['amounts_by_currency']) ? $metric['amounts_by_currency'] : array(),
                        $targetCurrencyId,
                        $currencyRateLookup,
                        $defaultCurrencyId
                    );
                    if ($purchaseAmount >= (float) $rule['_from'] && $purchaseAmount <= (float) $rule['_until']) {
                        $assignmentRows[] = array(
                            'label_type' => 'level',
                            'label_id' => (int) $rule['id'],
                            'platform' => $platform,
                            'customer_id' => (int) $customerId,
                            'purchase_amount' => (float) $purchaseAmount,
                            'box_quantity' => (float) $metric['box_total'],
                            'order_count' => (int) $metric['order_count'],
                        );
                        $summary['assignments_created']++;
                        $summary['platforms'][$platform]['assignments']++;
                        break;
                    }
                }

                $repeatMatch = customerLabelFindMatchingRule((float) $metric['order_count'], $repeatRules);
                if (!empty($repeatMatch)) {
                    $assignmentRows[] = array(
                        'label_type' => 'repeat',
                        'label_id' => (int) $repeatMatch['id'],
                        'platform' => $platform,
                        'customer_id' => (int) $customerId,
                        'purchase_amount' => (float) $metric['purchase_amount_default'],
                        'box_quantity' => (float) $metric['box_total'],
                        'order_count' => (int) $metric['order_count'],
                    );
                    $summary['assignments_created']++;
                    $summary['platforms'][$platform]['assignments']++;
                }
            }
        }

        return array(
            'rows' => $assignmentRows,
            'summary' => $summary,
        );
    }
}

if (!function_exists('customerLabelBuildAssignmentDataset')) {
    function customerLabelBuildAssignmentDataset($connect, $financeConnect)
    {
        $platformCountsTemplate = array();
        foreach (customerLabelGetPlatformConfigs() as $platform => $platformConfig) {
            $platformCountsTemplate[$platform] = 0;
        }

        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli)) {
            return array(
                'success' => false,
                'message' => 'Customer label source connections are not available.',
                'rows' => array(),
                'summary' => array(),
                'count_map' => array(
                    'segmentation' => array(),
                    'level' => array(),
                    'repeat' => array(),
                ),
                'breakdown_map' => array(
                    'segmentation' => array(),
                    'level' => array(),
                    'repeat' => array(),
                ),
                'filter_map' => array(),
                'customer_label_id_map' => array(),
            );
        }

        $buildResult = customerLabelBuildAssignmentRows($connect, $financeConnect);
        $assignmentRows = isset($buildResult['rows']) ? $buildResult['rows'] : array();
        $summary = isset($buildResult['summary']) ? $buildResult['summary'] : array();

        $countMap = array(
            'segmentation' => array(),
            'level' => array(),
            'repeat' => array(),
        );
        $breakdownMap = array(
            'segmentation' => array(),
            'level' => array(),
            'repeat' => array(),
        );
        $filterMap = array();
        $customerLabelIdMap = array();

        foreach ($assignmentRows as $assignmentRow) {
            $labelType = customerLabelNormalizeType(isset($assignmentRow['label_type']) ? $assignmentRow['label_type'] : '');
            $labelId = isset($assignmentRow['label_id']) ? (int) $assignmentRow['label_id'] : 0;
            $platform = isset($assignmentRow['platform']) ? trim((string) $assignmentRow['platform']) : '';
            $customerId = isset($assignmentRow['customer_id']) ? (int) $assignmentRow['customer_id'] : 0;
            if ($labelType === '' || $labelId <= 0 || $platform === '' || $customerId <= 0) {
                continue;
            }

            if (!isset($countMap[$labelType][$labelId])) {
                $countMap[$labelType][$labelId] = 0;
            }
            $countMap[$labelType][$labelId]++;

            if (!isset($breakdownMap[$labelType][$labelId])) {
                $breakdownMap[$labelType][$labelId] = $platformCountsTemplate;
            }
            if (!isset($breakdownMap[$labelType][$labelId][$platform])) {
                $breakdownMap[$labelType][$labelId][$platform] = 0;
            }
            $breakdownMap[$labelType][$labelId][$platform]++;

            if (!isset($filterMap[$platform])) {
                $filterMap[$platform] = array();
            }
            if (!isset($filterMap[$platform][$labelType])) {
                $filterMap[$platform][$labelType] = array();
            }
            if (!isset($filterMap[$platform][$labelType][$labelId])) {
                $filterMap[$platform][$labelType][$labelId] = array();
            }
            $filterMap[$platform][$labelType][$labelId][$customerId] = true;

            if (!isset($customerLabelIdMap[$platform])) {
                $customerLabelIdMap[$platform] = array();
            }
            if (!isset($customerLabelIdMap[$platform][$customerId])) {
                $customerLabelIdMap[$platform][$customerId] = array();
            }
            $customerLabelIdMap[$platform][$customerId][$labelType] = $labelId;
        }

        $summary['assignment_row_count'] = count($assignmentRows);
        return array(
            'success' => true,
            'message' => 'Customer label dataset prepared.',
            'rows' => $assignmentRows,
            'summary' => $summary,
            'count_map' => $countMap,
            'breakdown_map' => $breakdownMap,
            'filter_map' => $filterMap,
            'customer_label_id_map' => $customerLabelIdMap,
        );
    }
}

if (!function_exists('customerLabelGetRealtimeSyncCacheTtl')) {
    function customerLabelGetRealtimeSyncCacheTtl()
    {
        return 300;
    }
}

if (!function_exists('customerLabelGetRealtimeSyncCachePath')) {
    function customerLabelGetRealtimeSyncCachePath()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'customer_label_assignment_dataset.json';
    }
}

if (!function_exists('customerLabelReadRealtimeSyncCache')) {
    function customerLabelReadRealtimeSyncCache()
    {
        $cachePath = customerLabelGetRealtimeSyncCachePath();
        if ($cachePath === '' || !is_file($cachePath) || !is_readable($cachePath)) {
            return null;
        }

        $cacheJson = @file_get_contents($cachePath);
        if ($cacheJson === false || trim($cacheJson) === '') {
            return null;
        }

        $cachePayload = json_decode($cacheJson, true);
        if (!is_array($cachePayload)) {
            return null;
        }

        $expiresAt = isset($cachePayload['expires_at']) ? (int) $cachePayload['expires_at'] : 0;
        $dataset = isset($cachePayload['dataset']) && is_array($cachePayload['dataset']) ? $cachePayload['dataset'] : null;
        if ($expiresAt <= time() || !is_array($dataset)) {
            return null;
        }

        $dataset['cache_source'] = 'shared_ttl';
        $dataset['cache_expires_at'] = $expiresAt;
        return $dataset;
    }
}

if (!function_exists('customerLabelWriteRealtimeSyncCache')) {
    function customerLabelWriteRealtimeSyncCache($dataset)
    {
        if (!is_array($dataset) || empty($dataset['success'])) {
            return false;
        }

        $cachePath = customerLabelGetRealtimeSyncCachePath();
        $cacheDir = dirname($cachePath);
        if ($cachePath === '' || (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir))) {
            return false;
        }

        $ttl = customerLabelGetRealtimeSyncCacheTtl();
        $payload = array(
            'generated_at' => time(),
            'expires_at' => time() + max(1, (int) $ttl),
            'dataset' => $dataset,
        );

        $cacheJson = json_encode($payload);
        if ($cacheJson === false) {
            return false;
        }

        return @file_put_contents($cachePath, $cacheJson, LOCK_EX) !== false;
    }
}

if (!function_exists('customerLabelRefreshAssignments')) {
    function customerLabelRefreshAssignments($connect, $financeConnect, $actorName = 'system')
    {
        $dataset = customerLabelBuildAssignmentDataset($connect, $financeConnect);
        if (!empty($dataset['success'])) {
            customerLabelWriteRealtimeSyncCache($dataset);
            if (function_exists('shopeeCustomerRecordClearListCache')) {
                shopeeCustomerRecordClearListCache();
            }
        }
        return $dataset;
    }
}

if (!function_exists('customerLabelEnsureRealtimeSync')) {
    function customerLabelEnsureRealtimeSync($connect, $financeConnect = null)
    {
        static $hasSynced = false;
        static $lastResult = null;

        if ($hasSynced) {
            return $lastResult;
        }

        if (!($financeConnect instanceof mysqli)) {
            global $finance_connect;
            if ($financeConnect === null && isset($finance_connect) && $finance_connect instanceof mysqli) {
                $financeConnect = $finance_connect;
            }
        }

        $cachedDataset = customerLabelReadRealtimeSyncCache();
        if (is_array($cachedDataset)) {
            $lastResult = $cachedDataset;
            $hasSynced = true;
            return $lastResult;
        }

        if (!($financeConnect instanceof mysqli)) {
            return $lastResult;
        }

        $result = customerLabelRefreshAssignments($connect, $financeConnect, 'realtime_sync');
        if (is_array($result)) {
            $lastResult = $result;
            $hasSynced = true;
        }

        return $lastResult;
    }
}

if (!function_exists('customerLabelGetLabelCountMap')) {
    function customerLabelGetLabelCountMap($connect, $labelType)
    {
        $countMap = array();
        $labelType = customerLabelNormalizeType($labelType);
        if ($labelType === '' || !($connect instanceof mysqli)) {
            return $countMap;
        }

        $dataset = customerLabelEnsureRealtimeSync($connect);
        return isset($dataset['count_map'][$labelType]) ? (array) $dataset['count_map'][$labelType] : $countMap;
    }
}

if (!function_exists('customerLabelGetBreakdownCounts')) {
    function customerLabelGetBreakdownCounts($connect, $labelType, $labelId)
    {
        $counts = array();
        foreach (customerLabelGetPlatformConfigs() as $platform => $config) {
            $counts[$platform] = 0;
        }

        $labelType = customerLabelNormalizeType($labelType);
        $labelId = (int) $labelId;
        if ($labelType === '' || $labelId <= 0 || !($connect instanceof mysqli)) {
            return $counts;
        }

        $dataset = customerLabelEnsureRealtimeSync($connect);
        $breakdownMap = isset($dataset['breakdown_map'][$labelType][$labelId]) ? (array) $dataset['breakdown_map'][$labelType][$labelId] : array();
        foreach ($breakdownMap as $platform => $totalCount) {
            if (isset($counts[$platform])) {
                $counts[$platform] = (int) $totalCount;
            }
        }

        return $counts;
    }
}

if (!function_exists('customerLabelGetLabelMetaMap')) {
    function customerLabelGetLabelMetaMap($connect, $labelType, $labelIds)
    {
        $metaMap = array();
        $typeConfig = customerLabelGetTypeConfig($labelType);
        $labelIds = array_values(array_unique(array_filter(array_map('intval', (array) $labelIds))));
        if (empty($typeConfig) || empty($labelIds) || !($connect instanceof mysqli) || !tableExists($typeConfig['table'], $connect)) {
            return $metaMap;
        }

        $sql = "SELECT `id`, `name`, `colorCode` FROM `" . $typeConfig['table'] . "` WHERE `status` = 'A' AND `id` IN (" . implode(',', $labelIds) . ")";
        $rows = customerLabelFetchRows($connect, $sql);
        foreach ($rows as $row) {
            $metaMap[(int) $row['id']] = array(
                'id' => (int) $row['id'],
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'colorCode' => isset($row['colorCode']) ? (string) $row['colorCode'] : '',
            );
        }

        return $metaMap;
    }
}

if (!function_exists('customerLabelGetLabelMeta')) {
    function customerLabelGetLabelMeta($connect, $labelType, $labelId)
    {
        $metaMap = customerLabelGetLabelMetaMap($connect, $labelType, array((int) $labelId));
        return isset($metaMap[(int) $labelId]) ? $metaMap[(int) $labelId] : array();
    }
}

if (!function_exists('customerLabelGetFilteredCustomerIds')) {
    function customerLabelGetFilteredCustomerIds($connect, $platform, $labelType, $labelId)
    {
        $idMap = array();
        $labelType = customerLabelNormalizeType($labelType);
        $labelId = (int) $labelId;
        if ($labelType === '' || $labelId <= 0 || !($connect instanceof mysqli)) {
            return $idMap;
        }

        $dataset = customerLabelEnsureRealtimeSync($connect);
        $platform = trim((string) $platform);
        return isset($dataset['filter_map'][$platform][$labelType][$labelId]) ? (array) $dataset['filter_map'][$platform][$labelType][$labelId] : $idMap;
    }
}

if (!function_exists('customerLabelGetCustomerLabelMap')) {
    function customerLabelGetCustomerLabelMap($connect, $platform, $customerIds)
    {
        $labelMap = array();
        $customerIds = array_values(array_unique(array_filter(array_map('intval', (array) $customerIds))));
        if (empty($customerIds) || !($connect instanceof mysqli)) {
            return $labelMap;
        }

        $dataset = customerLabelEnsureRealtimeSync($connect);
        $platform = trim((string) $platform);
        $platformLabelIdMap = isset($dataset['customer_label_id_map'][$platform]) ? (array) $dataset['customer_label_id_map'][$platform] : array();
        $labelIdsByType = array(
            'segmentation' => array(),
            'level' => array(),
            'repeat' => array(),
        );
        foreach ($customerIds as $customerId) {
            $customerId = (int) $customerId;
            if ($customerId <= 0 || !isset($platformLabelIdMap[$customerId])) {
                continue;
            }
            foreach ((array) $platformLabelIdMap[$customerId] as $labelType => $labelId) {
                $labelType = customerLabelNormalizeType($labelType);
                $labelId = (int) $labelId;
                if ($labelType !== '' && $labelId > 0) {
                    $labelIdsByType[$labelType][$labelId] = $labelId;
                }
            }
        }

        $metaByType = array();
        foreach ($labelIdsByType as $labelType => $labelIds) {
            $metaByType[$labelType] = customerLabelGetLabelMetaMap($connect, $labelType, array_values($labelIds));
        }

        foreach ($customerIds as $customerId) {
            $customerId = (int) $customerId;
            if ($customerId <= 0 || !isset($platformLabelIdMap[$customerId])) {
                continue;
            }

            foreach ((array) $platformLabelIdMap[$customerId] as $labelType => $labelId) {
                $labelType = customerLabelNormalizeType($labelType);
                $labelId = (int) $labelId;
                if ($labelType === '' || !isset($metaByType[$labelType][$labelId])) {
                    continue;
                }

                if (!isset($labelMap[$customerId])) {
                    $labelMap[$customerId] = array();
                }
                $labelMap[$customerId][$labelType] = $metaByType[$labelType][$labelId];
            }
        }

        return $labelMap;
    }
}

if (!function_exists('customerLabelGetShopeeCustomerMetaMap')) {
    function customerLabelGetShopeeCustomerMetaMap($connect, $financeConnect, $buyerValues)
    {
        $metaMap = array(
            'by_id' => array(),
            'by_username' => array(),
        );
        if (!($connect instanceof mysqli) || !($financeConnect instanceof mysqli)) {
            return $metaMap;
        }

        $buyerValues = is_array($buyerValues) ? $buyerValues : array($buyerValues);
        $buyerIds = array();
        $buyerUsernames = array();

        foreach ($buyerValues as $buyerValue) {
            $buyerValue = trim((string) $buyerValue);
            if ($buyerValue === '') {
                continue;
            }

            if (ctype_digit($buyerValue)) {
                $buyerId = (int) $buyerValue;
                if ($buyerId > 0) {
                    $buyerIds[$buyerId] = $buyerId;
                }
            } else {
                $buyerUsernames[$buyerValue] = $buyerValue;
            }
        }

        if (empty($buyerIds) && empty($buyerUsernames)) {
            return $metaMap;
        }

        $whereParts = array();
        if (!empty($buyerIds)) {
            $whereParts[] = "`id` IN (" . implode(',', array_values($buyerIds)) . ")";
        }
        if (!empty($buyerUsernames)) {
            $safeUsernames = array();
            foreach ($buyerUsernames as $buyerUsername) {
                $safeUsernames[] = "'" . mysqli_real_escape_string($financeConnect, $buyerUsername) . "'";
            }
            $whereParts[] = "`buyer_username` IN (" . implode(',', $safeUsernames) . ")";
        }

        $customerRows = customerLabelFetchRows(
            $financeConnect,
            "SELECT `id`, `buyer_username` FROM `" . SHOPEE_CUST_INFO . "` WHERE `status` = 'A' AND (" . implode(' OR ', $whereParts) . ")"
        );

        $customerIds = array();
        foreach ($customerRows as $customerRow) {
            $customerId = isset($customerRow['id']) ? (int) $customerRow['id'] : 0;
            $buyerUsername = isset($customerRow['buyer_username']) ? trim((string) $customerRow['buyer_username']) : '';
            if ($customerId <= 0) {
                continue;
            }

            $metaMap['by_id'][$customerId] = array(
                'id' => $customerId,
                'buyer_username' => $buyerUsername,
                'label_meta' => array(),
            );
            if ($buyerUsername !== '') {
                $metaMap['by_username'][customerLabelNormalizeLookupKey($buyerUsername)] = $customerId;
            }
            $customerIds[] = $customerId;
        }

        $customerLabelMap = customerLabelGetCustomerLabelMap($connect, 'shopee', $customerIds);
        foreach ($customerLabelMap as $customerId => $labelMeta) {
            if (isset($metaMap['by_id'][$customerId])) {
                $metaMap['by_id'][$customerId]['label_meta'] = $labelMeta;
            }
        }

        return $metaMap;
    }
}

if (!function_exists('customerLabelResolveShopeeCustomerMeta')) {
    function customerLabelResolveShopeeCustomerMeta($connect, $financeConnect, $buyerValue, $fallbackDisplay = '', $shopeeCustomerMetaMap = null)
    {
        if (!is_array($shopeeCustomerMetaMap)) {
            $lookupValues = array();
            if (trim((string) $buyerValue) !== '') {
                $lookupValues[] = $buyerValue;
            }
            if (trim((string) $fallbackDisplay) !== '' && trim((string) $fallbackDisplay) !== trim((string) $buyerValue)) {
                $lookupValues[] = $fallbackDisplay;
            }
            $shopeeCustomerMetaMap = customerLabelGetShopeeCustomerMetaMap($connect, $financeConnect, $lookupValues);
        }

        $buyerValue = trim((string) $buyerValue);
        $fallbackDisplay = trim((string) $fallbackDisplay);

        if ($buyerValue !== '' && ctype_digit($buyerValue)) {
            $buyerId = (int) $buyerValue;
            if ($buyerId > 0 && isset($shopeeCustomerMetaMap['by_id'][$buyerId])) {
                return (array) $shopeeCustomerMetaMap['by_id'][$buyerId];
            }
        }

        $lookupDisplay = $fallbackDisplay !== '' ? $fallbackDisplay : $buyerValue;
        $lookupKey = customerLabelNormalizeLookupKey($lookupDisplay);
        if ($lookupKey !== '' && isset($shopeeCustomerMetaMap['by_username'][$lookupKey])) {
            $buyerId = (int) $shopeeCustomerMetaMap['by_username'][$lookupKey];
            if ($buyerId > 0 && isset($shopeeCustomerMetaMap['by_id'][$buyerId])) {
                return (array) $shopeeCustomerMetaMap['by_id'][$buyerId];
            }
        }

        return array();
    }
}

if (!function_exists('customerLabelRenderShopeeBuyerCell')) {
    function customerLabelRenderShopeeBuyerCell($connect, $financeConnect, $buyerValue, $fallbackDisplay = '', $shopeeCustomerMetaMap = null)
    {
        $buyerMeta = customerLabelResolveShopeeCustomerMeta($connect, $financeConnect, $buyerValue, $fallbackDisplay, $shopeeCustomerMetaMap);
        if (!empty($buyerMeta)) {
            $buyerName = isset($buyerMeta['buyer_username']) && trim((string) $buyerMeta['buyer_username']) !== ''
                ? (string) $buyerMeta['buyer_username']
                : (trim((string) $fallbackDisplay) !== '' ? (string) $fallbackDisplay : (string) $buyerValue);
            return customerLabelRenderNameCell($buyerName, isset($buyerMeta['label_meta']) ? $buyerMeta['label_meta'] : array());
        }

        $buyerValue = trim((string) $buyerValue);
        $fallbackDisplay = trim((string) $fallbackDisplay);
        $displayValue = $fallbackDisplay !== '' ? $fallbackDisplay : ($buyerValue !== '' ? $buyerValue : '-');
        return htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('customerLabelRenderBadge')) {
    function customerLabelRenderBadge($labelMeta)
    {
        $labelName = isset($labelMeta['name']) ? trim((string) $labelMeta['name']) : '';
        if ($labelName === '') {
            return '';
        }

        $badgeColor = isset($labelMeta['colorCode']) && trim((string) $labelMeta['colorCode']) !== '' ? (string) $labelMeta['colorCode'] : '#6c757d';
        return '<span class="customer-label-badge text-white" title="' .
            htmlspecialchars($labelName, ENT_QUOTES, 'UTF-8') .
            '" style="background-color:' .
            htmlspecialchars($badgeColor, ENT_QUOTES, 'UTF-8') .
            ';">' .
            '<span class="customer-label-badge-text">' .
            htmlspecialchars($labelName, ENT_QUOTES, 'UTF-8') .
            '</span>' .
            '</span>';
    }
}

if (!function_exists('customerLabelRenderCollapsibleBadgeGroup')) {
    function customerLabelRenderCollapsibleBadgeGroup($items, $wrapperClass = 'customer-label-summary-wrap', $visibleCount = 10)
    {
        $items = array_values(array_filter((array) $items, function ($item) {
            return trim((string) $item) !== '';
        }));

        if (empty($items)) {
            return '';
        }

        $visibleCount = (int) $visibleCount;
        if ($visibleCount <= 0) {
            $visibleCount = 10;
        }

        $wrapperClasses = trim((string) $wrapperClass) . ' js-customer-label-wrap';
        $html = '<span class="' . htmlspecialchars($wrapperClasses, ENT_QUOTES, 'UTF-8') . '" data-expanded="0">';

        foreach ($items as $index => $itemHtml) {
            $itemClasses = 'customer-label-item';
            if ($index >= $visibleCount) {
                $itemClasses .= ' customer-label-extra d-none';
            }

            $html .= '<span class="' . htmlspecialchars($itemClasses, ENT_QUOTES, 'UTF-8') . '">' . $itemHtml . '</span>';
        }

        if (count($items) > $visibleCount) {
            $html .= '<button type="button" class="customer-label-toggle-btn js-toggle-customer-labels">Show More</button>';
        }

        $html .= '</span>';

        return $html;
    }
}

if (!function_exists('customerLabelRenderNameCell')) {
    function customerLabelRenderNameCell($displayName, $customerLabelMeta)
    {
        $safeDisplayName = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
        $segmentationBadge = isset($customerLabelMeta['segmentation']) ? customerLabelRenderBadge($customerLabelMeta['segmentation']) : '';
        return '<span class="customer-name-label-wrap"><span class="customer-name-label-text">' . $safeDisplayName . '</span>' . $segmentationBadge . '</span>';
    }
}

if (!function_exists('customerLabelRenderInlineSegmentationBadge')) {
    function customerLabelRenderInlineSegmentationBadge($customerLabelMeta, $wrapperClass = 'customer-inline-segmentation-badge')
    {
        if (!isset($customerLabelMeta['segmentation'])) {
            return '';
        }

        $badgeHtml = customerLabelRenderBadge($customerLabelMeta['segmentation']);
        if ($badgeHtml === '') {
            return '';
        }

        return '<span class="' . htmlspecialchars(trim((string) $wrapperClass), ENT_QUOTES, 'UTF-8') . '">' . $badgeHtml . '</span>';
    }
}

// Customer tag helpers moved to include/customer_tag.php.

if (!function_exists('customerLabelRenderSummaryCell')) {
    function customerLabelRenderSummaryCell($customerLabelMeta, $customerTagRows = array())
    {
        $parts = array();
        if (isset($customerLabelMeta['level'])) {
            $parts[] = customerLabelRenderBadge($customerLabelMeta['level']);
        }
        if (isset($customerLabelMeta['repeat'])) {
            $parts[] = customerLabelRenderBadge($customerLabelMeta['repeat']);
        }
        $parts = array_merge($parts, customerTagRenderBadgeItems($customerTagRows, 'customer-tag-table-badge'));

        return customerLabelRenderCollapsibleBadgeGroup($parts, 'customer-label-summary-wrap');
    }
}

if (!function_exists('customerLabelRenderPageHeader')) {
    function customerLabelRenderPageHeader($customerLabelMeta)
    {
        $parts = array();

        if (isset($customerLabelMeta['level'])) {
            $parts[] = customerLabelRenderBadge($customerLabelMeta['level']);
        }

        if (isset($customerLabelMeta['repeat'])) {
            $parts[] = customerLabelRenderBadge($customerLabelMeta['repeat']);
        }

        if (empty($parts)) {
            return '';
        }

        return '<div class="customer-label-page-header mt-2">' .
            customerLabelRenderCollapsibleBadgeGroup($parts, 'customer-label-page-header-badges') .
            '</div>';
    }
}

if (!function_exists('customerRecordNormalizeFilterValues')) {
    function customerRecordNormalizeFilterValues($values)
    {
        $normalizedValues = array();

        foreach ((array) $values as $value) {
            $value = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES, 'UTF-8'));
            if ($value === '' || in_array($value, $normalizedValues, true)) {
                continue;
            }

            $normalizedValues[] = $value;
        }

        return $normalizedValues;
    }
}

if (!function_exists('customerRecordExtractLabelNames')) {
    function customerRecordExtractLabelNames($customerLabelMeta)
    {
        $labelNames = array();

        foreach (array('segmentation', 'level', 'repeat') as $labelType) {
            if (isset($customerLabelMeta[$labelType]['name'])) {
                $labelNames[] = $customerLabelMeta[$labelType]['name'];
            }
        }

        return customerRecordNormalizeFilterValues($labelNames);
    }
}

if (!function_exists('customerRecordExtractTagNames')) {
    function customerRecordExtractTagNames($customerTagRows)
    {
        $tagNames = array();

        foreach ((array) $customerTagRows as $tagRow) {
            if (isset($tagRow['name'])) {
                $tagNames[] = $tagRow['name'];
            }
        }

        return customerRecordNormalizeFilterValues($tagNames);
    }
}

if (!function_exists('customerRecordBuildFilterDataAttributes')) {
    function customerRecordBuildFilterDataAttributes($filters)
    {
        $attributes = array();

        foreach ((array) $filters as $key => $value) {
            $safeKey = preg_replace('/[^a-z0-9_-]+/i', '-', (string) $key);
            if ($safeKey === '') {
                continue;
            }

            $values = customerRecordNormalizeFilterValues(is_array($value) ? $value : array($value));
            $attributes[] = 'data-filter-' . $safeKey . '="' . htmlspecialchars(implode('||', $values), ENT_QUOTES, 'UTF-8') . '"';
        }

        return implode(' ', $attributes);
    }
}

if (!function_exists('customerLabelPrepareCustomerRows')) {
    function customerLabelPrepareCustomerRows($connect, $platform, $rows)
    {
        $rows = is_array($rows) ? $rows : array();
        $labelType = customerLabelNormalizeType(input('label_type'));
        $labelId = customerLabelResolveInt(input('label_id'));

        if ($labelType !== '' && $labelId > 0) {
            $allowedIds = customerLabelGetFilteredCustomerIds($connect, $platform, $labelType, $labelId);
            if (empty($allowedIds)) {
                $rows = array();
            } else {
                $rows = array_values(array_filter($rows, function ($row) use ($allowedIds) {
                    $customerId = isset($row['id']) ? (int) $row['id'] : 0;
                    return $customerId > 0 && isset($allowedIds[$customerId]);
                }));
            }
        }

        $customerIds = array();
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $customerIds[] = (int) $row['id'];
            }
        }

        return array(
            'rows' => $rows,
            'label_map' => customerLabelGetCustomerLabelMap($connect, $platform, $customerIds),
            'tag_map' => customerTagGetCustomerTagMap($connect, $platform, $customerIds),
            'active_filter_type' => $labelType,
            'active_filter_id' => $labelId,
        );
    }
}

if (!function_exists('shopeeCustomerRecordListCacheDir')) {
    function shopeeCustomerRecordListCacheDir()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'shopee_customer_record_list';
    }
}

if (!function_exists('shopeeCustomerRecordNormalizeCacheParams')) {
    function shopeeCustomerRecordNormalizeCacheParams($params = array())
    {
        if (!is_array($params)) {
            $params = array();
        }

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = shopeeCustomerRecordNormalizeCacheParams($value);
                continue;
            }

            if (is_bool($value)) {
                $params[$key] = $value ? '1' : '0';
                continue;
            }

            if ($value === null) {
                $params[$key] = '';
                continue;
            }

            $params[$key] = trim((string) $value);
        }

        ksort($params);
        return $params;
    }
}

if (!function_exists('shopeeCustomerRecordListCacheKey')) {
    function shopeeCustomerRecordListCacheKey($params = array())
    {
        $requestMeta = array(
            'script_name' => isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '',
            'params' => shopeeCustomerRecordNormalizeCacheParams($params),
        );

        $encodedMeta = json_encode($requestMeta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encodedMeta === false) {
            $encodedMeta = serialize($requestMeta);
        }

        return hash('sha256', (string) $encodedMeta);
    }
}

if (!function_exists('shopeeCustomerRecordListCachePath')) {
    function shopeeCustomerRecordListCachePath($params = array())
    {
        return shopeeCustomerRecordListCacheDir() . DIRECTORY_SEPARATOR . shopeeCustomerRecordListCacheKey($params) . '.json';
    }
}

if (!function_exists('shopeeCustomerRecordReadListCache')) {
    function shopeeCustomerRecordReadListCache($params = array())
    {
        $cachePath = shopeeCustomerRecordListCachePath($params);
        if ($cachePath === '' || !is_file($cachePath) || !is_readable($cachePath)) {
            return null;
        }

        $cacheJson = @file_get_contents($cachePath);
        if ($cacheJson === false || trim($cacheJson) === '') {
            return null;
        }

        $cachePayload = json_decode($cacheJson, true);
        if (!is_array($cachePayload)) {
            return null;
        }

        $dataset = isset($cachePayload['dataset']) && is_array($cachePayload['dataset']) ? $cachePayload['dataset'] : null;
        if (!is_array($dataset)) {
            return null;
        }

        $dataset['cache_source'] = 'file';
        $dataset['cache_expires_at'] = 0;
        return $dataset;
    }
}

if (!function_exists('shopeeCustomerRecordWriteListCache')) {
    function shopeeCustomerRecordWriteListCache($dataset, $params = array())
    {
        if (!is_array($dataset)) {
            return false;
        }

        $cacheDir = shopeeCustomerRecordListCacheDir();
        if ($cacheDir === '' || (!is_dir($cacheDir) && !@mkdir($cacheDir, 0777, true) && !is_dir($cacheDir))) {
            return false;
        }

        $cachePath = shopeeCustomerRecordListCachePath($params);
        $payload = array(
            'generated_at' => time(),
            'dataset' => $dataset,
        );

        $cacheJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($cacheJson === false) {
            return false;
        }

        return @file_put_contents($cachePath, $cacheJson, LOCK_EX) !== false;
    }
}

if (!function_exists('shopeeCustomerRecordClearListCache')) {
    function shopeeCustomerRecordClearListCache()
    {
        $cacheDir = shopeeCustomerRecordListCacheDir();
        if ($cacheDir === '' || !is_dir($cacheDir)) {
            return false;
        }

        $cleared = false;
        $cacheFiles = glob($cacheDir . DIRECTORY_SEPARATOR . '*.json');
        if ($cacheFiles === false) {
            return false;
        }

        foreach ($cacheFiles as $cacheFile) {
            if (!is_file($cacheFile)) {
                continue;
            }

            if (@unlink($cacheFile)) {
                $cleared = true;
            }
        }

        return $cleared;
    }
}

if (!function_exists('shopeeCustomerRecordResolveLookupMap')) {
    function shopeeCustomerRecordResolveLookupMap($connect, $rows, $fieldName, $tableName, $displayField, $altDisplayFields = array())
    {
        $rows = is_array($rows) ? $rows : array();
        $altDisplayFields = array_values(array_unique(array_filter(array_merge(array((string) $displayField), (array) $altDisplayFields))));
        $lookupMap = array();
        $lookupValues = array();

        foreach ($rows as $row) {
            $fieldValue = isset($row[$fieldName]) ? trim((string) $row[$fieldName]) : '';
            if ($fieldValue === '' || $fieldValue === '0' || isset($lookupValues[$fieldValue])) {
                continue;
            }

            $lookupValues[$fieldValue] = true;
        }

        foreach (array_keys($lookupValues) as $fieldValue) {
            $resolvedValue = $fieldValue;
            $safeValue = mysqli_real_escape_string($connect, (string) $fieldValue);
            $result = getData($displayField, "id='" . $safeValue . "'", 'LIMIT 1', $tableName, $connect);

            if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
                foreach ($altDisplayFields as $altField) {
                    $altField = trim((string) $altField);
                    if ($altField === '') {
                        continue;
                    }

                    $result = getData($displayField, $altField . "='" . $safeValue . "'", 'LIMIT 1', $tableName, $connect);
                    if ($result instanceof mysqli_result && $result->num_rows > 0) {
                        break;
                    }
                }
            }

            if ($result instanceof mysqli_result && $result->num_rows > 0) {
                $lookupRow = $result->fetch_assoc();
                $resolvedValue = isset($lookupRow[$displayField]) ? $lookupRow[$displayField] : $fieldValue;
            }

            $lookupMap[$fieldValue] = $resolvedValue;
        }

        return $lookupMap;
    }
}

if (!function_exists('shopeeCustomerRecordBuildListDataset')) {
    function shopeeCustomerRecordBuildListDataset($connect, $financeConnect)
    {
        $tableRows = array();
        $result = getData('*', '', '', SHOPEE_CUST_INFO, $financeConnect);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $tableRows[] = $row;
            }
        }

        $customerLabelData = customerLabelPrepareCustomerRows($connect, 'shopee', $tableRows);
        $tableRows = isset($customerLabelData['rows']) ? $customerLabelData['rows'] : array();

        return array(
            'rows' => $tableRows,
            'label_map' => isset($customerLabelData['label_map']) ? $customerLabelData['label_map'] : array(),
            'tag_map' => isset($customerLabelData['tag_map']) ? $customerLabelData['tag_map'] : array(),
            'lookup_maps' => array(
                'pic' => shopeeCustomerRecordResolveLookupMap($connect, $tableRows, 'pic', USR_USER, 'name', array('name')),
                'country' => shopeeCustomerRecordResolveLookupMap($connect, $tableRows, 'country', COUNTRIES, 'nicename', array('nicename', 'name')),
                'brand' => shopeeCustomerRecordResolveLookupMap($connect, $tableRows, 'brand', BRAND, 'name', array('name')),
                'series' => shopeeCustomerRecordResolveLookupMap($connect, $tableRows, 'series', BRD_SERIES, 'name', array('name')),
            ),
        );
    }
}

if (!function_exists('shopeeCustomerRecordGetListDataset')) {
    function shopeeCustomerRecordGetListDataset($connect, $financeConnect, $params = array())
    {
        if (empty($params)) {
            $params = array_merge((array) $_GET, (array) $_POST);
        }

        $cachedDataset = shopeeCustomerRecordReadListCache($params);
        if (is_array($cachedDataset)) {
            return $cachedDataset;
        }

        $dataset = shopeeCustomerRecordBuildListDataset($connect, $financeConnect);
        shopeeCustomerRecordWriteListCache($dataset, $params);
        $dataset['cache_source'] = 'rebuilt';
        $dataset['cache_expires_at'] = 0;
        return $dataset;
    }
}

if (!function_exists('customerLabelBuildBreakdownUrl')) {
    function customerLabelBuildBreakdownUrl($labelType, $labelId)
    {
        $labelType = customerLabelNormalizeType($labelType);
        $labelId = (int) $labelId;
        if ($labelType === '' || $labelId <= 0) {
            return '';
        }

        $baseUrl = defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
        if ($baseUrl === '') {
            return '';
        }

        return $baseUrl . '/customer/customer_label_breakdown.php?label_type=' . urlencode($labelType) . '&label_id=' . $labelId;
    }
}

if (!function_exists('customerLabelBuildRecordFilterUrl')) {
    function customerLabelBuildRecordFilterUrl($platform, $labelType, $labelId)
    {
        $labelType = customerLabelNormalizeType($labelType);
        $labelId = (int) $labelId;
        $recordUrl = customerLabelGetPlatformRecordUrl($platform);
        if ($recordUrl === '' || $labelType === '' || $labelId <= 0) {
            return '';
        }

        return $recordUrl . '?label_type=' . urlencode($labelType) . '&label_id=' . $labelId;
    }
}

if (!function_exists('validateEstimatedReceivedDate')) {
    function validateEstimatedReceivedDate($date, $orderContext = null)
    {
        $date = trim((string) $date);
        $dateRange = function_exists('shopeeOmsGetEstimatedReceivedDateRange')
            ? shopeeOmsGetEstimatedReceivedDateRange($orderContext)
            : array(
                'min_date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                'max_date' => (new DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d'),
            );
        $minDate = new DateTimeImmutable((string) $dateRange['min_date']);
        $maxDate = new DateTimeImmutable((string) $dateRange['max_date']);

        $result = array(
            'valid' => false,
            'message' => '',
            'normalized_date' => '',
            'min_date' => $minDate->format('Y-m-d'),
            'max_date' => $maxDate->format('Y-m-d'),
        );

        if ($date === '') {
            $result['message'] = 'Estimate Received Date is required.';
            return $result;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        $hasParseErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if (!($parsed instanceof DateTimeImmutable) || $hasParseErrors || $parsed->format('Y-m-d') !== $date) {
            $result['message'] = 'Estimate Received Date is invalid.';
            return $result;
        }

        if ($parsed < $minDate || $parsed > $maxDate) {
            $result['message'] = 'Estimate Received Date must be between ' . $result['min_date'] . ' and ' . $result['max_date'] . '.';
            return $result;
        }

        $result['valid'] = true;
        $result['normalized_date'] = $parsed->format('Y-m-d');
        return $result;
    }
}

if (!function_exists('validateReceivedDate')) {
    function validateReceivedDate($date)
    {
        $date = trim((string) $date);
        $result = array(
            'valid' => false,
            'message' => '',
            'normalized_date' => '',
        );

        if ($date === '') {
            $result['message'] = 'Received Date is required.';
            return $result;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        $hasParseErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
        if (!($parsed instanceof DateTimeImmutable) || $hasParseErrors || $parsed->format('Y-m-d') !== $date) {
            $result['message'] = 'Received Date is invalid.';
            return $result;
        }

        $result['valid'] = true;
        $result['normalized_date'] = $parsed->format('Y-m-d');
        return $result;
    }
}

if (!function_exists('shopeeOmsParseEstimatedDateBaseDate')) {
    function shopeeOmsParseEstimatedDateBaseDate($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = array('Y-m-d', 'd/m/Y', 'Y/m/d', 'Y-m-d H:i:s', 'Y-m-d H:i');
        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            $hasParseErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
            if ($parsed instanceof DateTimeImmutable && !$hasParseErrors) {
                return $parsed;
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }
}

if (!function_exists('shopeeOmsGetEstimatedReceivedDateRange')) {
    function shopeeOmsGetEstimatedReceivedDateRange($orderContext = null)
    {
        $baseDate = null;

        if (is_array($orderContext)) {
            $candidateFields = array('date', 'order_date', 'create_date');
            foreach ($candidateFields as $fieldName) {
                if (!isset($orderContext[$fieldName])) {
                    continue;
                }

                $baseDate = shopeeOmsParseEstimatedDateBaseDate($orderContext[$fieldName]);
                if ($baseDate instanceof DateTimeImmutable) {
                    break;
                }
            }
        } else if ($orderContext !== null) {
            $baseDate = shopeeOmsParseEstimatedDateBaseDate($orderContext);
        }

        if (!($baseDate instanceof DateTimeImmutable)) {
            $baseDate = new DateTimeImmutable('today');
        }

        $minDate = $baseDate->modify('+1 day');
        return array(
            'min_date' => $minDate->format('Y-m-d'),
            'max_date' => $baseDate->modify('+7 days')->format('Y-m-d'),
        );
    }
}

if (!function_exists('shouldShowEstimatedReceivedDateButton')) {
    function shouldShowEstimatedReceivedDateButton($row)
    {
        return function_exists('shopeeOmsShouldShowEstimatedReceivedDateButton')
            ? shopeeOmsShouldShowEstimatedReceivedDateButton($row)
            : false;
    }
}

if (!function_exists('assignEstimatedReceivedDate')) {
    function assignEstimatedReceivedDate($connect, $tblName, $orderId, $date, $currentUserId)
    {
        return function_exists('shopeeOmsAssignEstimatedReceivedDate')
            ? shopeeOmsAssignEstimatedReceivedDate($connect, $tblName, $orderId, $date, $currentUserId)
            : array(
                'success' => false,
                'message' => 'OMS date assignment helper is unavailable.',
            );
    }
}

if (!function_exists('shopeeOmsStatusDefinitions')) {
    function shopeeOmsStatusDefinitions()
    {
        return array(
            'P' => array(
                'label' => 'To Ship',
                'aliases' => array('p', 'pendingto', 'pendingtopack', 'toship'),
            ),
            'TP' => array(
                'label' => 'To Pack',
                'aliases' => array('tp', 'topack', 'waitingpacking', 'wp'),
            ),
            'SP' => array(
                'label' => 'Shipped',
                'aliases' => array('sp', 'processing', 'shipprocessingwarehouse', 'shipped'),
            ),
            'WAERD' => array(
                'label' => 'Waiting Assign Estimate Received Date',
                'aliases' => array('waerd', 'waitingassignestimatereceiveddate'),
            ),
            'WR' => array(
                'label' => 'Waiting Receive',
                'aliases' => array('wr', 'waitingreceive', 'aed', 'assignedestimateddate'),
            ),
            'PD' => array(
                'label' => 'Postponed',
                'aliases' => array('pd', 'postponed', 'delay', 'delayed'),
            ),
            'PR' => array(
                'label' => 'Parcel Received',
                'aliases' => array('pr', 'parcelreceived'),
            ),
            'WAFC' => array(
                'label' => 'Waiting Admin Final Check',
                'aliases' => array('wafc', 'waitingadminfinalcheck', 'oc', 'orderreceivedadminchecking', 'orderreceived'),
            ),
            'V' => array(
                'label' => 'Verify',
                'aliases' => array('v', 'verify', 'verified', 'verifiedasterchecking'),
            ),
            'C' => array(
                'label' => 'Complete',
                'aliases' => array('c', 'complete', 'completed'),
            ),
            'R' => array(
                'label' => 'Return',
                'aliases' => array('r', 'return'),
            ),
            'CR' => array(
                'label' => 'Closed-Returned',
                'aliases' => array('cr', 'closedreturned'),
            ),
        );
    }
}

if (!function_exists('shopeeOmsNormalizeStatusCode')) {
    function shopeeOmsNormalizeStatusCode($status)
    {
        $status = trim((string) $status);
        if ($status === '') {
            return '';
        }

        $definitions = shopeeOmsStatusDefinitions();
        $upperStatus = strtoupper($status);
        if (isset($definitions[$upperStatus])) {
            return $upperStatus;
        }

        $normalizedKey = normalizeOrderStatusKey($status);
        foreach ($definitions as $code => $definition) {
            if ($normalizedKey === normalizeOrderStatusKey($code)) {
                return $code;
            }

            $aliases = isset($definition['aliases']) && is_array($definition['aliases']) ? $definition['aliases'] : array();
            foreach ($aliases as $alias) {
                if ($normalizedKey === normalizeOrderStatusKey($alias)) {
                    return $code;
                }
            }
        }

        return $status;
    }
}

if (!function_exists('shopeeOmsGetStatusLabel')) {
    function shopeeOmsGetStatusLabel($status)
    {
        $statusCode = shopeeOmsNormalizeStatusCode($status);
        $definitions = shopeeOmsStatusDefinitions();
        if (isset($definitions[$statusCode]['label'])) {
            return (string) $definitions[$statusCode]['label'];
        }

        return trim((string) $status);
    }
}

if (!function_exists('shopeeOmsGetMarketplaceStatusLabel')) {
    function shopeeOmsGetMarketplaceStatusLabel($status)
    {
        $statusCode = shopeeOmsNormalizeStatusCode($status);
        if ($statusCode === 'P') {
            return 'To Ship';
        }
        if ($statusCode === 'TP') {
            return 'To Pack';
        }

        return shopeeOmsGetStatusLabel($statusCode);
    }
}

if (!function_exists('shopeeOmsGetStoredStatusVariants')) {
    function shopeeOmsGetStoredStatusVariants($status)
    {
        $status = trim((string) $status);
        if ($status === '') {
            return array();
        }

        $storedVariantsMap = array(
            'P' => array('P', 'To Ship'),
            'TP' => array('TP', 'To Pack'),
            'SP' => array('SP', 'Processing', 'Shipped'),
            'WAERD' => array('WAERD', 'Waiting Assign Estimate Received Date'),
            'WR' => array('WR', 'AED', 'Waiting Receive', 'Assigned Estimate Date'),
            'PD' => array('PD', 'Postponed'),
            'PR' => array('PR', 'Parcel Received'),
            'WAFC' => array('WAFC', 'OC', 'Waiting Admin Final Check', 'Order Received'),
            'V' => array('V', 'Verify', 'Verified'),
            'C' => array('C', 'Complete'),
            'R' => array('R', 'Return'),
            'CR' => array('CR', 'Closed-Returned'),
        );

        $statusCode = shopeeOmsNormalizeStatusCode($status);
        $candidates = array($status);
        if ($statusCode !== '' && isset($storedVariantsMap[$statusCode])) {
            $candidates = array_merge($candidates, $storedVariantsMap[$statusCode]);
        }

        $uniqueVariants = array();
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $variantKey = normalizeOrderStatusKey($candidate);
            if ($variantKey === '') {
                $variantKey = strtoupper($candidate);
            }

            if (!isset($uniqueVariants[$variantKey])) {
                $uniqueVariants[$variantKey] = $candidate;
            }
        }

        return array_values($uniqueVariants);
    }
}

if (!function_exists('shopeeOmsBuildOrderStatusFilterCondition')) {
    function shopeeOmsBuildOrderStatusFilterCondition($connect, $columnName, $status)
    {
        if (!($connect instanceof mysqli) || !preg_match('/^[A-Za-z0-9_]+$/', (string) $columnName)) {
            return '';
        }

        $variants = shopeeOmsGetStoredStatusVariants($status);
        if (empty($variants)) {
            return '';
        }

        $escapedValues = array();
        foreach ($variants as $variant) {
            $escapedValues[] = "'" . mysqli_real_escape_string($connect, $variant) . "'";
        }

        if (count($escapedValues) === 1) {
            return $columnName . " = " . $escapedValues[0];
        }

        return $columnName . " IN (" . implode(', ', $escapedValues) . ")";
    }
}

if (!function_exists('shopeeOmsBuildOrderStatusInCondition')) {
    function shopeeOmsBuildOrderStatusInCondition($connect, $columnName, $statuses)
    {
        if (!($connect instanceof mysqli) || !preg_match('/^[A-Za-z0-9_]+$/', (string) $columnName) || !is_array($statuses)) {
            return '';
        }

        $allVariants = array();
        foreach ($statuses as $status) {
            foreach (shopeeOmsGetStoredStatusVariants($status) as $variant) {
                $variantKey = normalizeOrderStatusKey($variant);
                if ($variantKey === '') {
                    $variantKey = strtoupper((string) $variant);
                }

                if (!isset($allVariants[$variantKey])) {
                    $allVariants[$variantKey] = "'" . mysqli_real_escape_string($connect, $variant) . "'";
                }
            }
        }

        if (empty($allVariants)) {
            return '';
        }

        return $columnName . " IN (" . implode(', ', array_values($allVariants)) . ")";
    }
}

if (!function_exists('shopeeOmsGetEditableStatusOptions')) {
    function shopeeOmsGetEditableStatusOptions()
    {
        $definitions = shopeeOmsStatusDefinitions();
        $editableKeys = array('P', 'TP', 'SP', 'WAERD', 'WR', 'PD', 'PR', 'WAFC', 'V', 'C', 'R', 'CR');
        $options = array();
        foreach ($editableKeys as $statusCode) {
            if (isset($definitions[$statusCode])) {
                $options[$statusCode] = (string) $definitions[$statusCode]['label'];
            }
        }

        return $options;
    }
}

if (!function_exists('shopeeOmsGetImportDefaultStatus')) {
    function shopeeOmsGetImportDefaultStatus($detectedStatus)
    {
        $statusCode = shopeeOmsNormalizeStatusCode($detectedStatus);
        $normalizedKey = normalizeOrderStatusKey($detectedStatus);

        if (in_array($normalizedKey, array('completed', 'delivered', 'orderreceived'), true)) {
            return 'WAERD';
        }

        if ($statusCode === 'WAFC') {
            return 'WAERD';
        }

        return $statusCode !== '' ? $statusCode : 'P';
    }
}

if (!function_exists('shopeeOmsValidateInitialStatusAndAirbill')) {
    function shopeeOmsValidateInitialStatusAndAirbill($status, $airbillNo)
    {
        $statusCode = shopeeOmsNormalizeStatusCode($status);
        $airbillNo = trim((string) $airbillNo);

        if ($statusCode === 'TP' && $airbillNo === '') {
            return array(
                'valid' => false,
                'message' => 'Airbill is required when Order Status is To Pack.',
            );
        }

        return array(
            'valid' => true,
            'message' => '',
        );
    }
}

if (!function_exists('shopeeOmsTransitionDefinitions')) {
    function shopeeOmsTransitionDefinitions()
    {
        return array(
            'P' => array(
                'TP' => array('action' => 'move_to_pack', 'requires_permission' => true, 'auto' => false),
            ),
            'TP' => array(
                'SP' => array('action' => 'warehouse_scan', 'requires_permission' => true, 'auto' => false),
            ),
            'SP' => array(
                'WAERD' => array('action' => 'auto_post_ship', 'requires_permission' => true, 'auto' => true),
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'WAERD' => array(
                'WR' => array('action' => 'assign_estimated_received_date', 'requires_permission' => true, 'auto' => false),
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'WR' => array(
                'PD' => array('action' => 'postpone_order', 'requires_permission' => true, 'auto' => false),
                'PR' => array('action' => 'confirm_parcel_received', 'requires_permission' => true, 'auto' => false),
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'PD' => array(
                'PR' => array('action' => 'confirm_parcel_received', 'requires_permission' => true, 'auto' => false),
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'PR' => array(
                'WAFC' => array('action' => 'auto_14_day_final_check', 'requires_permission' => false, 'auto' => true),
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'WAFC' => array(
                'V' => array('action' => 'admin_audit', 'requires_permission' => true, 'auto' => false),
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'V' => array(
                'C' => array('action' => 'finalize_complete', 'requires_permission' => true, 'auto' => false),
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'C' => array(
                'R' => array('action' => 'mark_return', 'requires_permission' => true, 'auto' => false),
            ),
            'R' => array(
                'CR' => array('action' => 'return_restock', 'requires_permission' => true, 'auto' => false),
            ),
        );
    }
}

if (!function_exists('shopeeOmsBuildTransitionKey')) {
    function shopeeOmsBuildTransitionKey($fromStatus, $toStatus)
    {
        return shopeeOmsNormalizeStatusCode($fromStatus) . '__' . shopeeOmsNormalizeStatusCode($toStatus);
    }
}

if (!function_exists('shopeeOmsGetConfigurableTransitions')) {
    function shopeeOmsGetConfigurableTransitions()
    {
        $transitions = array();
        foreach (shopeeOmsTransitionDefinitions() as $fromStatus => $targetRows) {
            foreach ($targetRows as $toStatus => $transitionInfo) {
                if (empty($transitionInfo['requires_permission'])) {
                    continue;
                }

                $transitions[] = array(
                    'key' => shopeeOmsBuildTransitionKey($fromStatus, $toStatus),
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'from_label' => shopeeOmsGetStatusLabel($fromStatus),
                    'to_label' => shopeeOmsGetStatusLabel($toStatus),
                    'action' => isset($transitionInfo['action']) ? (string) $transitionInfo['action'] : '',
                );
            }
        }

        return $transitions;
    }
}

if (!function_exists('shopeeOmsGetTransitionPermissionFallbackMap')) {
    function shopeeOmsGetTransitionPermissionFallbackMap()
    {
        return array(
            shopeeOmsBuildTransitionKey('WR', 'PD') => shopeeOmsBuildTransitionKey('WR', 'PR'),
            shopeeOmsBuildTransitionKey('PD', 'PR') => shopeeOmsBuildTransitionKey('WR', 'PR'),
            shopeeOmsBuildTransitionKey('PD', 'R') => shopeeOmsBuildTransitionKey('WR', 'R'),
        );
    }
}

if (!function_exists('shopeeOmsResolveTransitionPermissionFallbackKey')) {
    function shopeeOmsResolveTransitionPermissionFallbackKey($transitionKey)
    {
        $fallbackMap = shopeeOmsGetTransitionPermissionFallbackMap();
        return isset($fallbackMap[$transitionKey]) ? (string) $fallbackMap[$transitionKey] : '';
    }
}

if (!function_exists('shopeeOmsShouldShowEstimatedReceivedDateButton')) {
    function shopeeOmsShouldShowEstimatedReceivedDateButton($row)
    {
        if (!is_array($row)) {
            return false;
        }

        $statusCode = shopeeOmsNormalizeStatusCode(isset($row['order_status']) ? $row['order_status'] : '');
        $estimatedReceivedDate = trim((string) (isset($row['estimated_received_date']) ? $row['estimated_received_date'] : ''));
        return in_array($statusCode, array('WAERD', 'WR'), true) && $estimatedReceivedDate === '';
    }
}

if (!function_exists('shopeeOmsGetSetting')) {
    function shopeeOmsGetSetting($connect, $settingKey, $defaultValue = '')
    {
        if (!($connect instanceof mysqli) || !defined('ORDER_FLOW_SETTING')) {
            return $defaultValue;
        }

        $settingKey = trim((string) $settingKey);
        if ($settingKey === '') {
            return $defaultValue;
        }

        $safeSettingKey = mysqli_real_escape_string($connect, $settingKey);
        $sql = "SELECT setting_value FROM `" . ORDER_FLOW_SETTING . "` WHERE setting_key = '" . $safeSettingKey . "' AND status = 'A' ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return isset($row['setting_value']) ? (string) $row['setting_value'] : $defaultValue;
        }

        return $defaultValue;
    }
}

if (!function_exists('shopeeOmsSetSetting')) {
    function shopeeOmsSetSetting($connect, $settingKey, $settingValue, $remark = '', $userId = '')
    {
        if (!($connect instanceof mysqli) || !defined('ORDER_FLOW_SETTING')) {
            return false;
        }

        $settingKey = trim((string) $settingKey);
        if ($settingKey === '') {
            return false;
        }

        $safeKey = mysqli_real_escape_string($connect, $settingKey);
        $safeValue = mysqli_real_escape_string($connect, (string) $settingValue);
        $safeRemark = mysqli_real_escape_string($connect, (string) $remark);
        $safeUserId = mysqli_real_escape_string($connect, trim((string) $userId) !== '' ? trim((string) $userId) : '1');
        $sql = "INSERT INTO `" . ORDER_FLOW_SETTING . "` (`setting_key`, `setting_value`, `remark`, `create_by`, `create_date`, `create_time`, `status`)
            VALUES ('" . $safeKey . "', '" . $safeValue . "', '" . $safeRemark . "', '" . $safeUserId . "', CURDATE(), CURTIME(), 'A')
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`), `remark` = VALUES(`remark`), `status` = 'A', `update_by` = '" . $safeUserId . "', `update_date` = CURDATE(), `update_time` = CURTIME()";

        return (bool) mysqli_query($connect, $sql);
    }
}

if (!function_exists('shopeeOmsGetAssignmentScope')) {
    function shopeeOmsGetAssignmentScope($connect)
    {
        $scope = strtolower(trim((string) shopeeOmsGetSetting($connect, 'shopee_oms_assignment_scope', 'global')));
        return $scope === 'individual' ? 'individual' : 'global';
    }
}

if (!function_exists('shopeeOmsOrderBelongsToCurrentUser')) {
    function shopeeOmsOrderBelongsToCurrentUser($orderRow, $currentUserId)
    {
        if (!is_array($orderRow)) {
            return false;
        }

        return trim((string) (isset($orderRow['create_by']) ? $orderRow['create_by'] : '')) === trim((string) $currentUserId);
    }
}

if (!function_exists('shopeeOmsPassesAssignmentScope')) {
    function shopeeOmsPassesAssignmentScope($connect, $orderRow, $currentUserId, $currentUserGroupId)
    {
        if ((int) $currentUserGroupId === 1) {
            return true;
        }

        $scope = shopeeOmsGetAssignmentScope($connect);
        if ($scope !== 'individual') {
            return true;
        }

        return shopeeOmsOrderBelongsToCurrentUser($orderRow, $currentUserId);
    }
}

if (!function_exists('shopeeOmsHasTransitionPermission')) {
    function shopeeOmsHasTransitionPermission($connect, $fromStatus, $toStatus, $currentUserGroupId, $orderRow = array(), $currentUserId = '')
    {
        $fromStatus = shopeeOmsNormalizeStatusCode($fromStatus);
        $toStatus = shopeeOmsNormalizeStatusCode($toStatus);
        $currentUserGroupId = (int) $currentUserGroupId;

        if ($currentUserGroupId === 1) {
            return true;
        }

        if (!shopeeOmsPassesAssignmentScope($connect, $orderRow, $currentUserId, $currentUserGroupId)) {
            return false;
        }

        if (!($connect instanceof mysqli)) {
            return false;
        }

        $candidatePairs = array(array($fromStatus, $toStatus));
        $fallbackKey = shopeeOmsResolveTransitionPermissionFallbackKey(shopeeOmsBuildTransitionKey($fromStatus, $toStatus));
        if ($fallbackKey !== '') {
            $fallbackParts = explode('__', $fallbackKey, 2);
            if (count($fallbackParts) === 2) {
                $candidatePairs[] = array($fallbackParts[0], $fallbackParts[1]);
            }
        }

        foreach ($candidatePairs as $candidatePair) {
            $candidateFrom = isset($candidatePair[0]) ? (string) $candidatePair[0] : '';
            $candidateTo = isset($candidatePair[1]) ? (string) $candidatePair[1] : '';
            if ($candidateFrom === '' || $candidateTo === '') {
                continue;
            }

            $safeFromStatus = mysqli_real_escape_string($connect, $candidateFrom);
            $safeToStatus = mysqli_real_escape_string($connect, $candidateTo);
            $sql = "SELECT can_move FROM `" . ORDER_FLOW_TRANSITION_PERMISSION . "`
                WHERE module_key = 'shopee_oms'
                  AND from_status = '" . $safeFromStatus . "'
                  AND to_status = '" . $safeToStatus . "'
                  AND user_group_id = " . $currentUserGroupId . "
                  AND status = 'A'
                ORDER BY id DESC
                LIMIT 1";
            $result = mysqli_query($connect, $sql);
            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                return !empty($row['can_move']);
            }
        }

        return false;
    }
}

if (!function_exists('shopeeOmsLoadOrder')) {
    function shopeeOmsLoadOrder($connect, $orderId, $source = 'shopee')
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0 || !($connect instanceof mysqli)) {
            return array();
        }

        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source);
        $tblName = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ;
        $sql = "SELECT * FROM `" . $tblName . "` WHERE id = " . $orderId . " LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            return shopeeOmsAttachOrderSourceMeta((array) mysqli_fetch_assoc($result), isset($sourceConfig['platform']) ? $sourceConfig['platform'] : 'shopee', $sourceConfig);
        }

        return array();
    }
}

if (!function_exists('shopeeOmsLoadOrderByCode')) {
    function shopeeOmsLoadOrderByCode($connect, $orderCode, $source = 'shopee')
    {
        $orderCode = trim((string) $orderCode);
        if ($orderCode === '' || !($connect instanceof mysqli)) {
            return array();
        }

        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source);
        $tblName = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ;
        $fieldName = isset($sourceConfig['order_code_field']) ? trim((string) $sourceConfig['order_code_field']) : '';
        $safeOrderCode = mysqli_real_escape_string($connect, $orderCode);
        if ($fieldName !== '') {
            $sql = "SELECT * FROM `" . $tblName . "` WHERE `" . $fieldName . "` = '" . $safeOrderCode . "' LIMIT 1";
        } else {
            $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
            $prefix = strtoupper(trim((string) (isset($sourceConfig['fallback_code_prefix']) ? $sourceConfig['fallback_code_prefix'] : '')));
            $numericId = 0;
            if (preg_match('/^' . preg_quote($prefix, '/') . '\-(\d+)$/i', $orderCode, $matches)) {
                $numericId = (int) $matches[1];
            } else if ($platform === 'facebook' && preg_match('/^FB ORDER \#(\d+)$/i', $orderCode, $matches)) {
                $numericId = (int) $matches[1];
            } else if (ctype_digit($orderCode)) {
                $numericId = (int) $orderCode;
            }

            if ($numericId <= 0) {
                return array();
            }

            $sql = "SELECT * FROM `" . $tblName . "` WHERE id = " . $numericId . " LIMIT 1";
        }
        $result = mysqli_query($connect, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            return shopeeOmsAttachOrderSourceMeta((array) mysqli_fetch_assoc($result), isset($sourceConfig['platform']) ? $sourceConfig['platform'] : 'shopee', $sourceConfig);
        }

        return array();
    }
}

if (!function_exists('shopeeOmsLoadOrderByCodeAnyPlatform')) {
    function shopeeOmsLoadOrderByCodeAnyPlatform($cmsConnect, $financeConnect, $orderCode, $platform = '')
    {
        $orderCode = trim((string) $orderCode);
        if ($orderCode === '') {
            return array();
        }

        $platform = shopeeOmsNormalizePlatformKey($platform);
        $sourceConfigs = shopeeOmsGetOrderSourceConfigs();
        foreach ($sourceConfigs as $sourcePlatform => $sourceConfig) {
            if ($platform !== '' && $platform !== $sourcePlatform) {
                continue;
            }

            $conn = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
            $orderRow = shopeeOmsLoadOrderByCode($conn, $orderCode, $sourceConfig);
            if (!empty($orderRow)) {
                return $orderRow;
            }
        }

        return array();
    }
}

if (!function_exists('shopeeOmsResolveOrderSourceConfigFromTokenRow')) {
    function shopeeOmsResolveOrderSourceConfigFromTokenRow($cmsConnect, $financeConnect, $tokenRow, $fallbackPlatform = 'shopee')
    {
        $fallbackPlatform = shopeeOmsNormalizePlatformKey($fallbackPlatform) ?: 'shopee';
        $platform = shopeeOmsNormalizePlatformKey(isset($tokenRow['platform']) ? $tokenRow['platform'] : '');
        if ($platform !== '') {
            return shopeeOmsResolveOrderSourceConfig($platform, $fallbackPlatform);
        }

        $orderCode = trim((string) (isset($tokenRow['order_code']) ? $tokenRow['order_code'] : ''));
        if ($orderCode !== '') {
            $orderRow = shopeeOmsLoadOrderByCodeAnyPlatform($cmsConnect, $financeConnect, $orderCode);
            if (!empty($orderRow)) {
                return shopeeOmsResolveOrderSourceConfig(shopeeOmsGetOrderSourcePlatform($orderRow, $fallbackPlatform), $fallbackPlatform);
            }
        }

        return shopeeOmsResolveOrderSourceConfig($fallbackPlatform, $fallbackPlatform);
    }
}

if (!function_exists('shopeeOmsLoadOrderFromTokenRow')) {
    function shopeeOmsLoadOrderFromTokenRow($cmsConnect, $financeConnect, $tokenRow, &$resolvedSourceConfig = null, $fallbackPlatform = 'shopee')
    {
        $resolvedSourceConfig = shopeeOmsResolveOrderSourceConfigFromTokenRow($cmsConnect, $financeConnect, $tokenRow, $fallbackPlatform);
        $orderConnect = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $resolvedSourceConfig);
        $orderId = isset($tokenRow['order_id']) ? (int) $tokenRow['order_id'] : 0;
        if ($orderId > 0) {
            $orderRow = shopeeOmsLoadOrder($orderConnect, $orderId, $resolvedSourceConfig);
            if (!empty($orderRow)) {
                return $orderRow;
            }
        }

        $orderCode = trim((string) (isset($tokenRow['order_code']) ? $tokenRow['order_code'] : ''));
        if ($orderCode !== '') {
            $platform = isset($resolvedSourceConfig['platform']) ? (string) $resolvedSourceConfig['platform'] : '';
            $orderRow = shopeeOmsLoadOrderByCodeAnyPlatform($cmsConnect, $financeConnect, $orderCode, $platform);
            if (!empty($orderRow)) {
                $resolvedSourceConfig = shopeeOmsResolveOrderSourceConfig(shopeeOmsGetOrderSourcePlatform($orderRow, $fallbackPlatform), $fallbackPlatform);
                return $orderRow;
            }
        }

        return array();
    }
}

if (!function_exists('shopeeOmsNormalizePackageQtySnapshot')) {
    function shopeeOmsNormalizePackageQtySnapshot($rows)
    {
        $normalizedRows = array();
        $grouped = array();
        if (!is_array($rows)) {
            $rows = array();
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $packageId = isset($row['package_id']) ? (int) $row['package_id'] : 0;
            $packageName = trim((string) (isset($row['package_name']) ? $row['package_name'] : ''));
            $qty = isset($row['qty']) ? (int) $row['qty'] : 0;
            if ($qty <= 0) {
                $qty = 1;
            }

            if ($packageId <= 0 && $packageName === '') {
                continue;
            }

            $groupKey = $packageId > 0 ? ('id_' . $packageId) : ('name_' . strtolower($packageName));
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = array(
                    'package_id' => $packageId,
                    'package_name' => $packageName,
                    'qty' => 0,
                );
            }
            if ($grouped[$groupKey]['package_name'] === '' && $packageName !== '') {
                $grouped[$groupKey]['package_name'] = $packageName;
            }
            $grouped[$groupKey]['qty'] += $qty;
        }

        foreach ($grouped as $row) {
            $normalizedRows[] = $row;
        }

        return $normalizedRows;
    }
}

if (!function_exists('shopeeOmsDecodePackageQtySnapshot')) {
    function shopeeOmsDecodePackageQtySnapshot($rawSnapshot)
    {
        $rawSnapshot = trim((string) $rawSnapshot);
        if ($rawSnapshot === '') {
            return array();
        }

        $decoded = json_decode($rawSnapshot, true);
        if (!is_array($decoded)) {
            return array();
        }

        return shopeeOmsNormalizePackageQtySnapshot($decoded);
    }
}

if (!function_exists('shopeeOmsBuildPackageQtySnapshotFromInputs')) {
    function shopeeOmsBuildPackageQtySnapshotFromInputs($hiddenIds, $nameInputs, $connect)
    {
        if (!is_array($hiddenIds)) {
            $hiddenIds = explode(',', (string) $hiddenIds);
        }
        if (!is_array($nameInputs)) {
            $nameInputs = explode(',', (string) $nameInputs);
        }

        $rows = array();
        $rowCount = max(count($hiddenIds), count($nameInputs));
        for ($i = 0; $i < $rowCount; $i++) {
            $packageId = isset($hiddenIds[$i]) && ctype_digit((string) $hiddenIds[$i]) ? (int) $hiddenIds[$i] : 0;
            $packageName = trim((string) (isset($nameInputs[$i]) ? $nameInputs[$i] : ''));

            if ($packageId <= 0 && $packageName !== '' && ($connect instanceof mysqli)) {
                $safePackageName = mysqli_real_escape_string($connect, $packageName);
                $result = getData('id, name', "name = '" . $safePackageName . "'", 'LIMIT 1', PKG, $connect);
                if ($result && $result->num_rows > 0) {
                    $packageRow = $result->fetch_assoc();
                    $packageId = isset($packageRow['id']) ? (int) $packageRow['id'] : 0;
                    if ($packageName === '' && isset($packageRow['name'])) {
                        $packageName = (string) $packageRow['name'];
                    }
                }
            }

            if ($packageId <= 0 && $packageName === '') {
                continue;
            }

            $rows[] = array(
                'package_id' => $packageId,
                'package_name' => $packageName,
                'qty' => 1,
            );
        }

        return shopeeOmsNormalizePackageQtySnapshot($rows);
    }
}

if (!function_exists('shopeeOmsLoadTableSnapshotRows')) {
    function shopeeOmsLoadTableSnapshotRows($tblName)
    {
        $tblName = trim((string) $tblName);
        if ($tblName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $tblName) || !defined('ROOT')) {
            return array();
        }

        static $snapshotCache = array();
        if (array_key_exists($tblName, $snapshotCache)) {
            return $snapshotCache[$tblName];
        }

        $rootPath = rtrim((string) ROOT, '/\\');
        $candidatePaths = array(
            $rootPath . '/data/' . $tblName . '.json',
            $rootPath . '/' . $tblName . '.json',
        );

        foreach ($candidatePaths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $json = file_get_contents($path);
            if ($json === false || trim((string) $json) === '') {
                continue;
            }

            $rows = json_decode($json, true);
            if (is_array($rows)) {
                $snapshotCache[$tblName] = $rows;
                return $snapshotCache[$tblName];
            }
        }

        $snapshotCache[$tblName] = array();
        return $snapshotCache[$tblName];
    }
}

if (!function_exists('shopeeOmsGetPackageSnapshotMap')) {
    function shopeeOmsGetPackageSnapshotMap($packageIds)
    {
        $snapshotMap = array();
        if (!is_array($packageIds) || empty($packageIds)) {
            return $snapshotMap;
        }

        $safeIds = array();
        foreach ($packageIds as $packageId) {
            $packageId = (int) $packageId;
            if ($packageId > 0) {
                $safeIds[$packageId] = $packageId;
            }
        }

        if (empty($safeIds)) {
            return $snapshotMap;
        }

        $rows = shopeeOmsLoadTableSnapshotRows(PKG);
        foreach ($rows as $row) {
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($rowId > 0 && isset($safeIds[$rowId])) {
                $snapshotMap[$rowId] = array(
                    'name' => isset($row['name']) ? (string) $row['name'] : '',
                    'product' => isset($row['product']) ? (string) $row['product'] : '',
                );
            }
        }

        return $snapshotMap;
    }
}

if (!function_exists('shopeeOmsGetProductSnapshotNameMap')) {
    function shopeeOmsGetProductSnapshotNameMap($productIds)
    {
        $nameMap = array();
        if (!is_array($productIds) || empty($productIds)) {
            return $nameMap;
        }

        $safeIds = array();
        foreach ($productIds as $productId) {
            $productId = (int) $productId;
            if ($productId > 0) {
                $safeIds[$productId] = $productId;
            }
        }

        if (empty($safeIds)) {
            return $nameMap;
        }

        $rows = shopeeOmsLoadTableSnapshotRows(PROD);
        foreach ($rows as $row) {
            $rowId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($rowId > 0 && isset($safeIds[$rowId])) {
                $nameMap[$rowId] = isset($row['name']) ? (string) $row['name'] : '';
            }
        }

        return $nameMap;
    }
}

if (!function_exists('shopeeOmsGetPackageNameMap')) {
    function shopeeOmsGetPackageNameMap($connect, $packageIds)
    {
        $nameMap = array();
        if (!is_array($packageIds) || empty($packageIds)) {
            return $nameMap;
        }

        $safeIds = array();
        foreach ($packageIds as $packageId) {
            $packageId = (int) $packageId;
            if ($packageId > 0) {
                $safeIds[$packageId] = $packageId;
            }
        }

        if (empty($safeIds)) {
            return $nameMap;
        }

        if ($connect instanceof mysqli) {
            $idList = implode(',', $safeIds);
            $result = mysqli_query($connect, "SELECT id, name FROM `" . PKG . "` WHERE id IN (" . $idList . ")");
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $rowId = isset($row['id']) ? (int) $row['id'] : 0;
                    if ($rowId > 0) {
                        $nameMap[$rowId] = isset($row['name']) ? (string) $row['name'] : '';
                    }
                }
            }
        }

        $missingIds = array();
        foreach ($safeIds as $packageId) {
            if (!isset($nameMap[$packageId]) || trim((string) $nameMap[$packageId]) === '') {
                $missingIds[$packageId] = $packageId;
            }
        }

        if (!empty($missingIds)) {
            $snapshotMap = shopeeOmsGetPackageSnapshotMap($missingIds);
            foreach ($missingIds as $packageId) {
                if (isset($snapshotMap[$packageId]['name']) && trim((string) $snapshotMap[$packageId]['name']) !== '') {
                    $nameMap[$packageId] = (string) $snapshotMap[$packageId]['name'];
                }
            }
        }

        return $nameMap;
    }
}

if (!function_exists('shopeeOmsResolveOrderPackageRows')) {
    function shopeeOmsResolveOrderPackageRows($connect, $orderRow)
    {
        return shopeeOmsResolveOrderPackageRowsBySource($connect, $orderRow, 'shopee');
    }
}

if (!function_exists('shopeeOmsResolveOrderPackageRowsBySource')) {
    function shopeeOmsResolveOrderPackageRowsBySource($connect, $orderRow, $source = 'shopee')
    {
        if (!is_array($orderRow)) {
            return array();
        }

        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source, shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
        if ($platform !== 'shopee') {
            return customerLabelResolvePackageRows($connect, $platform, $orderRow);
        }

        $packageQtyJsonField = isset($sourceConfig['package_qty_json_field']) ? (string) $sourceConfig['package_qty_json_field'] : 'package_qty_json';
        $packageField = isset($sourceConfig['package_field']) ? (string) $sourceConfig['package_field'] : 'package';
        $snapshotRows = shopeeOmsDecodePackageQtySnapshot(isset($orderRow[$packageQtyJsonField]) ? $orderRow[$packageQtyJsonField] : '');
        if (!empty($snapshotRows)) {
            $packageIds = array();
            foreach ($snapshotRows as $snapshotRow) {
                if (!empty($snapshotRow['package_id'])) {
                    $packageIds[] = (int) $snapshotRow['package_id'];
                }
            }
            $packageNameMap = shopeeOmsGetPackageNameMap($connect, $packageIds);
            foreach ($snapshotRows as $idx => $snapshotRow) {
                if (empty($snapshotRows[$idx]['package_name']) && !empty($snapshotRow['package_id']) && isset($packageNameMap[(int) $snapshotRow['package_id']])) {
                    $snapshotRows[$idx]['package_name'] = $packageNameMap[(int) $snapshotRow['package_id']];
                }
            }

            return $snapshotRows;
        }

        $rows = array();
        $packageIds = array_filter(array_map('trim', explode(',', (string) (isset($orderRow[$packageField]) ? $orderRow[$packageField] : ''))), 'strlen');
        $packageNameMap = shopeeOmsGetPackageNameMap($connect, $packageIds);
        foreach ($packageIds as $packageIdRaw) {
            $packageId = (int) $packageIdRaw;
            if ($packageId <= 0) {
                continue;
            }

            $rows[] = array(
                'package_id' => $packageId,
                'package_name' => isset($packageNameMap[$packageId]) ? $packageNameMap[$packageId] : ('Package #' . $packageId),
                'qty' => 1,
            );
        }

        return shopeeOmsNormalizePackageQtySnapshot($rows);
    }
}

if (!function_exists('shopeeOmsBuildOrderProductSummary')) {
    function shopeeOmsBuildOrderProductSummary($connect, $orderRow)
    {
        return shopeeOmsBuildOrderProductSummaryBySource($connect, $orderRow, 'shopee');
    }
}

if (!function_exists('shopeeOmsBuildOrderProductSummaryBySource')) {
    function shopeeOmsBuildOrderProductSummaryBySource($connect, $orderRow, $source = 'shopee')
    {
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source, shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
        $packageRows = shopeeOmsResolveOrderPackageRowsBySource($connect, $orderRow, $sourceConfig);
        $packageSummary = array();
        $productQtyMap = array();
        $packageIds = array();
        foreach ($packageRows as $packageRow) {
            $packageId = isset($packageRow['package_id']) ? (int) $packageRow['package_id'] : 0;
            $packageName = trim((string) (isset($packageRow['package_name']) ? $packageRow['package_name'] : ''));
            $qty = isset($packageRow['qty']) ? (int) $packageRow['qty'] : 1;
            if ($qty <= 0) {
                $qty = 1;
            }

            if ($packageName !== '') {
                $packageSummary[] = $packageName . ' x' . $qty;
            }
            if ($packageId > 0) {
                $packageIds[$packageId] = $packageId;
            }
        }

        $packageProductMap = array();
        if (!empty($packageIds) && ($connect instanceof mysqli)) {
            $result = mysqli_query($connect, "SELECT id, name, product FROM `" . PKG . "` WHERE id IN (" . implode(',', $packageIds) . ")");
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $packageId = isset($row['id']) ? (int) $row['id'] : 0;
                    if ($packageId > 0) {
                        $packageProductMap[$packageId] = array(
                            'name' => isset($row['name']) ? (string) $row['name'] : '',
                            'product' => isset($row['product']) ? (string) $row['product'] : '',
                        );
                    }
                }
            }
        }

        if ($platform === 'shopee') {
            $missingPackageIds = array();
            foreach ($packageIds as $packageId) {
                if (!isset($packageProductMap[$packageId]) || trim((string) $packageProductMap[$packageId]['product']) === '') {
                    $missingPackageIds[$packageId] = $packageId;
                }
            }

            if (!empty($missingPackageIds)) {
                $packageSnapshotMap = shopeeOmsGetPackageSnapshotMap($missingPackageIds);
                foreach ($missingPackageIds as $packageId) {
                    if (isset($packageSnapshotMap[$packageId])) {
                        $packageProductMap[$packageId] = $packageSnapshotMap[$packageId];
                    }
                }
            }
        }

        foreach ($packageRows as $packageRow) {
            $packageId = isset($packageRow['package_id']) ? (int) $packageRow['package_id'] : 0;
            $qty = isset($packageRow['qty']) ? (int) $packageRow['qty'] : 1;
            if ($qty <= 0) {
                $qty = 1;
            }
            if ($packageId <= 0 || !isset($packageProductMap[$packageId])) {
                continue;
            }

            $productIds = array_filter(array_map('trim', explode(',', (string) $packageProductMap[$packageId]['product'])), 'strlen');
            foreach ($productIds as $productIdRaw) {
                $productId = (int) $productIdRaw;
                if ($productId <= 0) {
                    continue;
                }
                if (!isset($productQtyMap[$productId])) {
                    $productQtyMap[$productId] = 0;
                }
                $productQtyMap[$productId] += $qty;
            }
        }

        $productNameMap = array();
        if (!empty($productQtyMap) && ($connect instanceof mysqli)) {
            $result = mysqli_query($connect, "SELECT id, name FROM `" . PROD . "` WHERE id IN (" . implode(',', array_keys($productQtyMap)) . ")");
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $productId = isset($row['id']) ? (int) $row['id'] : 0;
                    if ($productId > 0) {
                        $productNameMap[$productId] = isset($row['name']) ? (string) $row['name'] : '';
                    }
                }
            }
        }

        if ($platform === 'shopee') {
            $missingProductIds = array();
            foreach (array_keys($productQtyMap) as $productId) {
                $productId = (int) $productId;
                if ($productId > 0 && (!isset($productNameMap[$productId]) || trim((string) $productNameMap[$productId]) === '')) {
                    $missingProductIds[$productId] = $productId;
                }
            }

            if (!empty($missingProductIds)) {
                $productSnapshotNameMap = shopeeOmsGetProductSnapshotNameMap($missingProductIds);
                foreach ($missingProductIds as $productId) {
                    if (isset($productSnapshotNameMap[$productId]) && trim((string) $productSnapshotNameMap[$productId]) !== '') {
                        $productNameMap[$productId] = (string) $productSnapshotNameMap[$productId];
                    }
                }
            }
        }

        $productSummary = array();
        $productSummaryRows = array();
        foreach ($productQtyMap as $productId => $qty) {
            $productLabel = (isset($productNameMap[$productId]) ? $productNameMap[$productId] : ('Product #' . $productId)) . ' x' . (int) $qty . ' boxes';
            $productSummary[] = $productLabel;
            $productSummaryRows[] = array(
                'product_id' => (int) $productId,
                'label' => $productLabel,
            );
        }

        $packageSummaryRows = array();
        foreach ($packageRows as $idx => $packageRow) {
            $packageSummaryRows[] = array(
                'package_id' => isset($packageRow['package_id']) ? (int) $packageRow['package_id'] : 0,
                'label' => isset($packageSummary[$idx]) ? (string) $packageSummary[$idx] : '',
            );
        }

        return array(
            'package_rows' => $packageRows,
            'package_summary' => implode(', ', $packageSummary),
            'package_lines' => $packageSummary,
            'package_summary_rows' => $packageSummaryRows,
            'product_lines' => $productSummary,
            'product_summary_rows' => $productSummaryRows,
            'product_qty_map' => $productQtyMap,
            'bundle_name' => implode(', ', $packageSummary),
        );
    }
}

if (!function_exists('shopeeOmsNormalizeWarehouseId')) {
    function shopeeOmsNormalizeWarehouseId($warehouseId)
    {
        $warehouseId = (int) $warehouseId;
        return $warehouseId > 0 ? $warehouseId : 0;
    }
}

if (!function_exists('shopeeOmsLoadActiveWarehouses')) {
    function shopeeOmsLoadActiveWarehouses($connect)
    {
        $rows = array();
        if (!($connect instanceof mysqli)) {
            return $rows;
        }

        $result = mysqli_query($connect, "SELECT id, name FROM `" . WHSE . "` WHERE status = 'A' ORDER BY name ASC");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $warehouseId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($warehouseId > 0) {
                    $rows[] = array(
                        'id' => $warehouseId,
                        'name' => isset($row['name']) ? (string) $row['name'] : ('Warehouse #' . $warehouseId),
                    );
                }
            }
        }

        return $rows;
    }
}

if (!function_exists('shopeeOmsLoadWarehouseNameMap')) {
    function shopeeOmsLoadWarehouseNameMap($connect, $activeOnly = false)
    {
        $nameMap = array();
        if (!($connect instanceof mysqli)) {
            return $nameMap;
        }

        $whereSql = $activeOnly ? " WHERE status = 'A'" : '';
        $result = mysqli_query($connect, "SELECT id, name FROM `" . WHSE . "`" . $whereSql . " ORDER BY name ASC");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $warehouseId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($warehouseId > 0) {
                    $nameMap[$warehouseId] = isset($row['name']) ? (string) $row['name'] : ('Warehouse #' . $warehouseId);
                }
            }
        }

        return $nameMap;
    }
}

if (!function_exists('commonWarehouseTelegramTokenColumnExists')) {
    function commonWarehouseTelegramTokenColumnExists($connect)
    {
        static $availabilityMap = array();

        if (!($connect instanceof mysqli)) {
            return false;
        }

        $cacheKey = spl_object_hash($connect);
        if (array_key_exists($cacheKey, $availabilityMap)) {
            return $availabilityMap[$cacheKey];
        }

        $result = @mysqli_query($connect, "SHOW COLUMNS FROM `" . WHSE . "` LIKE 'telegram_token_setting_id'");
        $availabilityMap[$cacheKey] = ($result && mysqli_num_rows($result) > 0);
        return $availabilityMap[$cacheKey];
    }
}

if (!function_exists('shopeeOmsLoadActiveTokenSettingOptions')) {
    function shopeeOmsLoadActiveTokenSettingOptions($connect)
    {
        $rows = array();
        if (!($connect instanceof mysqli)) {
            return $rows;
        }

        $sql = "SELECT id, name, bot_token, chat_id FROM `" . TOKEN_SETT . "` WHERE status = 'A' ORDER BY name ASC, id ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $tokenId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($tokenId <= 0) {
                    continue;
                }

                $rows[$tokenId] = array(
                    'id' => $tokenId,
                    'name' => isset($row['name']) && trim((string) $row['name']) !== '' ? (string) $row['name'] : ('Token #' . $tokenId),
                    'bot_token' => isset($row['bot_token']) ? (string) $row['bot_token'] : '',
                    'chat_id' => isset($row['chat_id']) ? (string) $row['chat_id'] : '',
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('shopeeOmsLoadTokenSettingNameMap')) {
    function shopeeOmsLoadTokenSettingNameMap($connect, $activeOnly = false)
    {
        $nameMap = array();
        if (!($connect instanceof mysqli)) {
            return $nameMap;
        }

        $sql = "SELECT id, name FROM `" . TOKEN_SETT . "`";
        if ($activeOnly) {
            $sql .= " WHERE status = 'A'";
        }
        $sql .= " ORDER BY name ASC, id ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $tokenId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($tokenId <= 0) {
                    continue;
                }

                $nameMap[$tokenId] = isset($row['name']) && trim((string) $row['name']) !== ''
                    ? (string) $row['name']
                    : ('Token #' . $tokenId);
            }
        }

        return $nameMap;
    }
}

if (!function_exists('shopeeOmsBuildWarehouseUsageByTokenSettingId')) {
    function shopeeOmsBuildWarehouseUsageByTokenSettingId($connect)
    {
        $usageMap = array();
        if (!($connect instanceof mysqli) || !commonWarehouseTelegramTokenColumnExists($connect)) {
            return $usageMap;
        }

        $sql = "SELECT id, name, telegram_token_setting_id
                FROM `" . WHSE . "`
                WHERE status = 'A'
                  AND telegram_token_setting_id IS NOT NULL
                  AND telegram_token_setting_id > 0
                ORDER BY name ASC, id ASC";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $tokenId = isset($row['telegram_token_setting_id']) ? (int) $row['telegram_token_setting_id'] : 0;
                if ($tokenId <= 0) {
                    continue;
                }

                if (!isset($usageMap[$tokenId])) {
                    $usageMap[$tokenId] = array();
                }

                $warehouseName = isset($row['name']) && trim((string) $row['name']) !== ''
                    ? (string) $row['name']
                    : ('Warehouse #' . (int) $row['id']);
                $usageMap[$tokenId][] = $warehouseName;
            }
        }

        foreach ($usageMap as $tokenId => $warehouseNames) {
            $usageMap[$tokenId] = array_values(array_unique($warehouseNames));
        }

        return $usageMap;
    }
}

if (!function_exists('shopeeOmsGetDefaultWarehouseId')) {
    function shopeeOmsGetDefaultWarehouseId($connect, $warehouseRows = null)
    {
        $configuredWarehouseId = (int) shopeeOmsGetSetting($connect, 'shopee_oms_default_warehouse_id', '0');
        if ($warehouseRows === null) {
            $warehouseRows = shopeeOmsLoadActiveWarehouses($connect);
        }
        if (!is_array($warehouseRows)) {
            $warehouseRows = array();
        }

        $firstActiveWarehouseId = 0;
        foreach ($warehouseRows as $warehouseRow) {
            $warehouseId = isset($warehouseRow['id']) ? (int) $warehouseRow['id'] : 0;
            if ($warehouseId <= 0) {
                continue;
            }

            if ($firstActiveWarehouseId <= 0) {
                $firstActiveWarehouseId = $warehouseId;
            }
            if ($configuredWarehouseId > 0 && $warehouseId === $configuredWarehouseId) {
                return $configuredWarehouseId;
            }
        }

        if ($firstActiveWarehouseId > 0) {
            return $firstActiveWarehouseId;
        }

        return $configuredWarehouseId > 0 ? $configuredWarehouseId : 0;
    }
}

if (!function_exists('shopeeOmsResolveWarehouseNameById')) {
    function shopeeOmsResolveWarehouseNameById($connect, $warehouseId, $defaultWarehouseId = null, $warehouseNameMap = null)
    {
        $warehouseId = shopeeOmsNormalizeWarehouseId($warehouseId);
        if ($defaultWarehouseId === null) {
            $defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect);
        }
        $defaultWarehouseId = shopeeOmsNormalizeWarehouseId($defaultWarehouseId);
        $resolvedWarehouseId = $warehouseId > 0 ? $warehouseId : $defaultWarehouseId;
        if ($resolvedWarehouseId <= 0) {
            return '';
        }

        if (!is_array($warehouseNameMap)) {
            $warehouseNameMap = shopeeOmsLoadWarehouseNameMap($connect);
        }

        return isset($warehouseNameMap[$resolvedWarehouseId]) && trim((string) $warehouseNameMap[$resolvedWarehouseId]) !== ''
            ? (string) $warehouseNameMap[$resolvedWarehouseId]
            : ('Warehouse #' . $resolvedWarehouseId);
    }
}

if (!function_exists('shopeeOmsResolveStockOutWarehouseId')) {
    function shopeeOmsResolveStockOutWarehouseId($connect, $orderRow, $defaultWarehouseId = null)
    {
        $storedWarehouseId = 0;
        if (is_array($orderRow) && isset($orderRow['stock_out_warehouse_id'])) {
            $storedWarehouseId = shopeeOmsNormalizeWarehouseId($orderRow['stock_out_warehouse_id']);
        } else if (!is_array($orderRow)) {
            $storedWarehouseId = shopeeOmsNormalizeWarehouseId($orderRow);
        }

        if ($storedWarehouseId > 0) {
            return $storedWarehouseId;
        }

        if ($defaultWarehouseId === null) {
            $defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($connect);
        }

        return shopeeOmsNormalizeWarehouseId($defaultWarehouseId);
    }
}

if (!function_exists('shopeeOmsResolveStockOutWarehouseName')) {
    function shopeeOmsResolveStockOutWarehouseName($connect, $orderRow, $defaultWarehouseId = null, $warehouseNameMap = null)
    {
        $resolvedWarehouseId = shopeeOmsResolveStockOutWarehouseId($connect, $orderRow, $defaultWarehouseId);
        return shopeeOmsResolveWarehouseNameById($connect, $resolvedWarehouseId, $defaultWarehouseId, $warehouseNameMap);
    }
}

if (!function_exists('shopeeOmsIsStockOutWarehouseEditable')) {
    function shopeeOmsIsStockOutWarehouseEditable($orderStatus)
    {
        $statusCode = shopeeOmsNormalizeStatusCode($orderStatus);
        return $statusCode === '' || in_array($statusCode, array('P', 'TP'), true);
    }
}

if (!function_exists('shopeeOmsGetWarehouseId')) {
    function shopeeOmsGetWarehouseId($connect)
    {
        return shopeeOmsGetDefaultWarehouseId($connect);
    }
}

if (!function_exists('commonGetWarehouseTelegramTokenSetting')) {
    function commonGetWarehouseTelegramTokenSetting($connect, $warehouseId)
    {
        $warehouseId = shopeeOmsNormalizeWarehouseId($warehouseId);
        if (!($connect instanceof mysqli) || $warehouseId <= 0 || !commonWarehouseTelegramTokenColumnExists($connect)) {
            return array();
        }

        $sql = "SELECT id, name, telegram_token_setting_id
                FROM `" . WHSE . "`
                WHERE id = " . $warehouseId . " AND status = 'A'
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return array(
                'warehouse_id' => isset($row['id']) ? (int) $row['id'] : $warehouseId,
                'warehouse_name' => isset($row['name']) && trim((string) $row['name']) !== '' ? (string) $row['name'] : ('Warehouse #' . $warehouseId),
                'telegram_token_setting_id' => isset($row['telegram_token_setting_id']) ? (int) $row['telegram_token_setting_id'] : 0,
            );
        }

        return array();
    }
}

if (!function_exists('shopeeOmsFindWarehouseTokenSetting')) {
    function shopeeOmsFindWarehouseTokenSetting($connect, $warehouseId)
    {
        $warehouseInfo = commonGetWarehouseTelegramTokenSetting($connect, $warehouseId);
        if (empty($warehouseInfo)) {
            return array();
        }

        $tokenSettingId = isset($warehouseInfo['telegram_token_setting_id']) ? (int) $warehouseInfo['telegram_token_setting_id'] : 0;
        if ($tokenSettingId <= 0) {
            $warehouseInfo['token_setting'] = array();
            return $warehouseInfo;
        }

        $sql = "SELECT * FROM `" . TOKEN_SETT . "` WHERE id = " . $tokenSettingId . " AND status = 'A' LIMIT 1";
        $result = mysqli_query($connect, $sql);
        $warehouseInfo['token_setting'] = ($result && ($row = mysqli_fetch_assoc($result))) ? (array) $row : array();
        return $warehouseInfo;
    }
}

if (!function_exists('shopeeOmsTelegramRequest')) {
    function shopeeOmsTelegramRequest($url, $payload, &$errorMessage = '', &$httpCode = 0)
    {
        $errorMessage = '';
        $httpCode = 0;
        $postData = is_array($payload) ? http_build_query($payload) : (string) $payload;
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 20,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        ));

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $errorMessage = 'Unable to reach Telegram API.';
            return false;
        }

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $headerLine, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }

        return $response;
    }
}

if (!function_exists('shopeeOmsTelegramMultipartRequest')) {
    function shopeeOmsTelegramMultipartRequest($url, $payload, &$errorMessage = '', &$httpCode = 0)
    {
        $errorMessage = '';
        $httpCode = 0;

        if (!function_exists('curl_init')) {
            $errorMessage = 'cURL is not available for Telegram file upload.';
            return false;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $errorMessage = 'Unable to initialize Telegram upload request.';
            return false;
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($ch, CURLOPT_USERAGENT, 'BeYourDiary-IMS-TelegramUpload/1.0');
        if (defined('CURLOPT_SAFE_UPLOAD')) {
            $usesLegacyUploadSyntax = false;
            if (is_array($payload)) {
                foreach ($payload as $payloadValue) {
                    if (is_string($payloadValue) && strpos($payloadValue, '@') === 0) {
                        $usesLegacyUploadSyntax = true;
                        break;
                    }
                }
            }
            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, !$usesLegacyUploadSyntax);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $errorMessage = (string) curl_error($ch);
            curl_close($ch);
            return false;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response;
    }
}

if (!function_exists('shopeeOmsBuildTelegramUploadValue')) {
    function shopeeOmsBuildTelegramUploadValue($filePath, $mimeType, $fileName)
    {
        $filePath = (string) $filePath;
        $mimeType = trim((string) $mimeType);
        $fileName = trim((string) $fileName);

        if ($filePath === '' || !is_file($filePath)) {
            return null;
        }

        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }
        if ($fileName === '') {
            $fileName = basename($filePath);
        }

        if (function_exists('curl_file_create')) {
            return curl_file_create($filePath, $mimeType, $fileName);
        }

        if (class_exists('CURLFile')) {
            return new CURLFile($filePath, $mimeType, $fileName);
        }

        return '@' . $filePath . ';filename=' . $fileName . ';type=' . $mimeType;
    }
}

if (!function_exists('shopeeOmsSendTelegramAttachment')) {
    function shopeeOmsSendTelegramAttachment($botToken, $chatId, $attachmentFsPath, &$errorMessage = '', &$httpCode = 0)
    {
        $errorMessage = '';
        $httpCode = 0;

        $attachmentFsPath = trim((string) $attachmentFsPath);
        if ($attachmentFsPath === '' || !is_file($attachmentFsPath) || !is_readable($attachmentFsPath)) {
            $errorMessage = 'Telegram attachment file is not readable.';
            return array(
                'success' => false,
                'method' => '',
                'response' => false,
            );
        }

        $mimeType = shopeeOmsDetectFileMimeType($attachmentFsPath);
        $fileName = basename($attachmentFsPath);
        $captionText = trim((string) $fileName);
        if ($captionText !== '') {
            $captionText = 'Airbill Attachment: ' . $captionText;
        }

        $ext = strtolower((string) pathinfo($attachmentFsPath, PATHINFO_EXTENSION));
        $uploadStrategies = array();
        if (in_array($ext, array('png', 'jpg', 'jpeg', 'webp'), true) || strpos($mimeType, 'image/') === 0) {
            $uploadStrategies[] = array(
                'endpoint' => 'sendPhoto',
                'field' => 'photo',
                'label' => 'photo',
            );
        }
        $uploadStrategies[] = array(
            'endpoint' => 'sendDocument',
            'field' => 'document',
            'label' => 'document',
        );

        $attemptErrors = array();
        foreach ($uploadStrategies as $strategy) {
            $uploadValue = shopeeOmsBuildTelegramUploadValue($attachmentFsPath, $mimeType, $fileName);
            if ($uploadValue === null) {
                $attemptErrors[] = 'Unable to build Telegram upload payload for ' . $strategy['label'] . '.';
                continue;
            }

            $apiUrl = TELEGRAM_API . $botToken . '/' . $strategy['endpoint'];
            $payload = array(
                'chat_id' => $chatId,
                'caption' => $captionText,
                $strategy['field'] => $uploadValue,
            );
            if ($strategy['endpoint'] === 'sendDocument') {
                $payload['disable_content_type_detection'] = false;
            }

            $attemptError = '';
            $attemptHttpCode = 0;
            $attemptResponse = shopeeOmsTelegramMultipartRequest($apiUrl, $payload, $attemptError, $attemptHttpCode);
            $attemptDecoded = json_decode((string) $attemptResponse, true);
            if (is_array($attemptDecoded) && !empty($attemptDecoded['ok'])) {
                $httpCode = $attemptHttpCode;
                return array(
                    'success' => true,
                    'method' => $strategy['label'],
                    'response' => $attemptResponse,
                );
            }

            $attemptDescription = shopeeOmsTelegramDescribeResponse($attemptResponse, $attemptError !== '' ? $attemptError : 'Telegram ' . $strategy['label'] . ' upload failed.');
            $attemptErrors[] = ucfirst($strategy['label']) . ' upload failed: ' . $attemptDescription . ($attemptHttpCode > 0 ? (' HTTP ' . $attemptHttpCode . '.') : '');
            if ($attemptHttpCode > 0) {
                $httpCode = $attemptHttpCode;
            }
        }

        $errorMessage = implode(' ', $attemptErrors);
        return array(
            'success' => false,
            'method' => '',
            'response' => false,
        );
    }
}

if (!function_exists('shopeeOmsSendTelegramAttachmentByUrl')) {
    function shopeeOmsSendTelegramAttachmentByUrl($botToken, $chatId, $attachmentUrl, $attachmentName = '', &$errorMessage = '', &$httpCode = 0)
    {
        $errorMessage = '';
        $httpCode = 0;

        $attachmentUrl = trim((string) $attachmentUrl);
        if ($attachmentUrl === '') {
            $errorMessage = 'Telegram attachment URL is empty.';
            return array(
                'success' => false,
                'method' => '',
                'response' => false,
            );
        }

        $attachmentName = trim((string) $attachmentName);
        if ($attachmentName === '') {
            $attachmentName = basename(parse_url($attachmentUrl, PHP_URL_PATH));
        }

        $captionText = $attachmentName !== '' ? 'Airbill Attachment: ' . $attachmentName : '';
        $ext = strtolower((string) pathinfo($attachmentName, PATHINFO_EXTENSION));
        $sendStrategies = array();
        if (in_array($ext, array('png', 'jpg', 'jpeg', 'webp'), true)) {
            $sendStrategies[] = array(
                'endpoint' => 'sendPhoto',
                'field' => 'photo',
                'label' => 'photo-url',
            );
        }
        $sendStrategies[] = array(
            'endpoint' => 'sendDocument',
            'field' => 'document',
            'label' => 'document-url',
        );

        $attemptErrors = array();
        foreach ($sendStrategies as $strategy) {
            $apiUrl = TELEGRAM_API . $botToken . '/' . $strategy['endpoint'];
            $payload = array(
                'chat_id' => $chatId,
                'caption' => $captionText,
                $strategy['field'] => $attachmentUrl,
            );
            if ($strategy['endpoint'] === 'sendDocument') {
                $payload['disable_content_type_detection'] = false;
            }

            $attemptError = '';
            $attemptHttpCode = 0;
            $attemptResponse = shopeeOmsTelegramRequest($apiUrl, $payload, $attemptError, $attemptHttpCode);
            $attemptDecoded = json_decode((string) $attemptResponse, true);
            if (is_array($attemptDecoded) && !empty($attemptDecoded['ok'])) {
                $httpCode = $attemptHttpCode;
                return array(
                    'success' => true,
                    'method' => $strategy['label'],
                    'response' => $attemptResponse,
                );
            }

            $attemptDescription = shopeeOmsTelegramDescribeResponse($attemptResponse, $attemptError !== '' ? $attemptError : 'Telegram ' . $strategy['label'] . ' upload failed.');
            $attemptErrors[] = ucfirst($strategy['label']) . ' upload failed: ' . $attemptDescription . ($attemptHttpCode > 0 ? (' HTTP ' . $attemptHttpCode . '.') : '');
            if ($attemptHttpCode > 0) {
                $httpCode = $attemptHttpCode;
            }
        }

        $errorMessage = implode(' ', $attemptErrors);
        return array(
            'success' => false,
            'method' => '',
            'response' => false,
        );
    }
}

if (!function_exists('shopeeOmsTelegramDescribeResponse')) {
    function shopeeOmsTelegramDescribeResponse($response, $defaultMessage = '')
    {
        $decoded = json_decode((string) $response, true);
        if (is_array($decoded) && isset($decoded['description']) && trim((string) $decoded['description']) !== '') {
            return trim((string) $decoded['description']);
        }

        return $defaultMessage;
    }
}

if (!function_exists('shopeeOmsDetectFileMimeType')) {
    function shopeeOmsDetectFileMimeType($filePath)
    {
        $filePath = (string) $filePath;
        if ($filePath === '') {
            return 'application/octet-stream';
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = @finfo_file($finfo, $filePath);
                @finfo_close($finfo);
                if (is_string($detected) && trim($detected) !== '') {
                    return trim($detected);
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($filePath);
            if (is_string($detected) && trim($detected) !== '') {
                return trim($detected);
            }
        }

        $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeMap = array(
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
        );

        return isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';
    }
}

if (!function_exists('shopeeOmsGetTokenSettingPageOptions')) {
    function shopeeOmsGetTokenSettingPageOptions()
    {
        return array(
            'Shopee Order Request' => 'Shopee Order Request',
            'Stock Order Request' => 'Stock Order Request',
            'Lazada Order Request' => 'Lazada Order Request',
            'Facebook Order Request' => 'Facebook Order Request',
            'Website Order Request' => 'Website Order Request',
        );
    }
}

if (!function_exists('shopeeOmsNormalizeTokenSettingPageValues')) {
    function shopeeOmsNormalizeTokenSettingPageValues($pageValues, $allowedOptions = null)
    {
        $allowedOptions = is_array($allowedOptions) ? $allowedOptions : shopeeOmsGetTokenSettingPageOptions();
        $rawValues = array();

        if (is_array($pageValues)) {
            $rawValues = $pageValues;
        } else {
            $pageValues = str_replace(array("\r", "\n"), ',', (string) $pageValues);
            $rawValues = explode(',', (string) $pageValues);
        }

        $selectedSet = array();
        $extraValues = array();
        foreach ($rawValues as $rawValue) {
            $value = trim((string) $rawValue);
            if ($value === '' || isset($selectedSet[$value])) {
                continue;
            }
            $selectedSet[$value] = true;
            if (!array_key_exists($value, $allowedOptions)) {
                $extraValues[] = $value;
            }
        }

        $normalized = array();
        foreach ($allowedOptions as $optionValue => $optionLabel) {
            if (isset($selectedSet[$optionValue])) {
                $normalized[] = (string) $optionValue;
            }
        }

        foreach ($extraValues as $extraValue) {
            $normalized[] = (string) $extraValue;
        }

        return $normalized;
    }
}

if (!function_exists('shopeeOmsGetTokenSettingPageDisplayText')) {
    function shopeeOmsGetTokenSettingPageDisplayText($pageValues, $separator = ', ')
    {
        $options = shopeeOmsGetTokenSettingPageOptions();
        $normalizedValues = shopeeOmsNormalizeTokenSettingPageValues($pageValues, $options);
        if (empty($normalizedValues)) {
            return '';
        }

        $labels = array();
        foreach ($normalizedValues as $normalizedValue) {
            $labels[] = isset($options[$normalizedValue]) ? (string) $options[$normalizedValue] : (string) $normalizedValue;
        }

        return implode($separator, $labels);
    }
}

if (!function_exists('shopeeOmsTokenSettingRowUsesPage')) {
    function shopeeOmsTokenSettingRowUsesPage($tokenRow, $pageName)
    {
        if (!is_array($tokenRow)) {
            return false;
        }

        $pageName = trim((string) $pageName);
        if ($pageName === '') {
            return false;
        }

        $pageValues = shopeeOmsNormalizeTokenSettingPageValues(isset($tokenRow['page_used']) ? $tokenRow['page_used'] : '');
        return in_array($pageName, $pageValues, true);
    }
}

if (!function_exists('shopeeOmsFindTokenSettingPageConflicts')) {
    function shopeeOmsFindTokenSettingPageConflicts($connect, $pageValues, $excludeId = 0)
    {
        $conflicts = array();
        if (!($connect instanceof mysqli)) {
            return $conflicts;
        }

        $selectedPages = shopeeOmsNormalizeTokenSettingPageValues($pageValues);
        if (empty($selectedPages)) {
            return $conflicts;
        }

        $excludeId = (int) $excludeId;
        $sql = "SELECT id, name, page_used FROM `" . TOKEN_SETT . "` WHERE status='A'";
        if ($excludeId > 0) {
            $sql .= " AND id <> '" . $excludeId . "'";
        }
        $sql .= " ORDER BY id DESC";
        $result = mysqli_query($connect, $sql);
        if (!$result) {
            return $conflicts;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $rowPages = shopeeOmsNormalizeTokenSettingPageValues(isset($row['page_used']) ? $row['page_used'] : '');
            $overlap = array_values(array_intersect($selectedPages, $rowPages));
            if (!empty($overlap)) {
                $conflicts[] = array(
                    'id' => isset($row['id']) ? (int) $row['id'] : 0,
                    'name' => isset($row['name']) ? (string) $row['name'] : '',
                    'pages' => $overlap,
                );
            }
        }

        return $conflicts;
    }
}

if (!function_exists('shopeeOmsFindPreferredTokenSetting')) {
    function shopeeOmsFindPreferredTokenSetting($connect, $pageName = '')
    {
        if (!($connect instanceof mysqli)) {
            return array();
        }

        $pageName = trim((string) $pageName);
        if ($pageName !== '' && function_exists('shopeeOmsIsTokenSettingPageFieldAvailable') && shopeeOmsIsTokenSettingPageFieldAvailable($connect)) {
            $result = mysqli_query($connect, "SELECT * FROM `" . TOKEN_SETT . "` WHERE status = 'A' ORDER BY id DESC");
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    if (shopeeOmsTokenSettingRowUsesPage($row, $pageName)) {
                        return (array) $row;
                    }
                }
            }
        }

        $sql = "SELECT * FROM `" . TOKEN_SETT . "` WHERE status = 'A' ORDER BY
            CASE
                WHEN LOWER(IFNULL(name, '')) LIKE '%warehouse%' OR LOWER(IFNULL(name, '')) LIKE '%stock%' THEN 0
                WHEN LOWER(IFNULL(remark, '')) LIKE '%warehouse%' OR LOWER(IFNULL(remark, '')) LIKE '%stock%' THEN 1
                ELSE 2
            END,
            id DESC
            LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            return (array) mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('shopeeOmsIsTokenSettingPageFieldAvailable')) {
    function shopeeOmsIsTokenSettingPageFieldAvailable($connect)
    {
        static $availabilityMap = array();

        if (!($connect instanceof mysqli)) {
            return false;
        }

        $cacheKey = spl_object_hash($connect);
        if (array_key_exists($cacheKey, $availabilityMap)) {
            return $availabilityMap[$cacheKey];
        }

        $result = @mysqli_query($connect, "SHOW COLUMNS FROM `" . TOKEN_SETT . "` LIKE 'page_used'");
        $availabilityMap[$cacheKey] = ($result && mysqli_num_rows($result) > 0);
        return $availabilityMap[$cacheKey];
    }
}

if (!function_exists('shopeeOmsResolveChatIdFromTokenRow')) {
    function shopeeOmsResolveChatIdFromTokenRow($tokenRow)
    {
        if (!is_array($tokenRow)) {
            return '';
        }

        $chatId = trim((string) (isset($tokenRow['chat_id']) ? $tokenRow['chat_id'] : ''));
        if ($chatId !== '') {
            return $chatId;
        }

        $remark = trim((string) (isset($tokenRow['remark']) ? $tokenRow['remark'] : ''));
        if ($remark !== '' && preg_match('/(?:chat[_\s-]*id|chat|channel)\s*[:=]\s*(@[a-z0-9_]{4,}|-?\d{5,})/i', $remark, $matches)) {
            return trim((string) $matches[1]);
        }

        return '';
    }
}

if (!function_exists('shopeeOmsResolveWarehouseNotificationTokenPage')) {
    function shopeeOmsResolveWarehouseNotificationTokenPage($sourcePage, $tokenRow = array(), $notificationInfo = array())
    {
        $sourcePage = trim((string) $sourcePage);
        $platform = '';

        if (is_array($notificationInfo) && isset($notificationInfo['platform'])) {
            $platform = shopeeOmsNormalizePlatformKey($notificationInfo['platform']);
        }
        if ($platform === '' && is_array($tokenRow) && isset($tokenRow['platform'])) {
            $platform = shopeeOmsNormalizePlatformKey($tokenRow['platform']);
        }

        if ($platform === 'shopee') {
            return 'Shopee Order Request';
        }

        return $sourcePage;
    }
}

if (!function_exists('shopeeOmsBuildWarehouseMessage')) {
    function shopeeOmsBuildWarehouseMessage($orderRow, $tokenValue, $connect, $buyerConnect = null, $source = 'shopee')
    {
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source, shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
        $platformLabel = isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($platform);
        $summary = shopeeOmsBuildOrderProductSummaryBySource($connect, $orderRow, $sourceConfig);
        if (!($buyerConnect instanceof mysqli)) {
            $buyerConnect = $connect;
        }
        $customerName = shopeeOmsGetOrderCustomerNameText($connect, $buyerConnect, $orderRow, $sourceConfig);
        $link = rtrim((string) SITEURL, '/') . '/stock/warehouse_stock_in_scan.php?t=' . rawurlencode((string) $tokenValue);
        $orderCode = shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig);
        $addressField = isset($sourceConfig['address_field']) ? (string) $sourceConfig['address_field'] : 'customer_address';
        $airbillField = isset($sourceConfig['airbill_no_field']) ? (string) $sourceConfig['airbill_no_field'] : 'airbill_no';
        $airbillAttachmentField = isset($sourceConfig['airbill_attachment_field']) ? (string) $sourceConfig['airbill_attachment_field'] : 'airbill_attachment';
        $airbillText = trim((string) (isset($orderRow[$airbillField]) ? $orderRow[$airbillField] : ''));
        $airbillAttachment = trim((string) (isset($orderRow[$airbillAttachmentField]) ? $orderRow[$airbillAttachmentField] : ''));
        $customerAddress = trim((string) (isset($orderRow[$addressField]) ? $orderRow[$addressField] : ''));
        $deliveryInfo = $platform === 'shopee' ? shopeeOmsExtractAirbillDeliveryInfoFromAttachment($airbillAttachment) : array();
        $rememberedDeliveryInfo = shopeeOmsGetRememberedWarehouseDeliveryInfo($platform, isset($orderRow['id']) ? $orderRow['id'] : 0);
        $deliveryCustomerName = isset($orderRow['customer_name']) ? trim((string) $orderRow['customer_name']) : '';
        // Intentionally avoid reading from $_POST here to keep message generation deterministic.
        if ($deliveryCustomerName === '' && isset($rememberedDeliveryInfo['customer_name'])) {
            $deliveryCustomerName = trim((string) $rememberedDeliveryInfo['customer_name']);
        }
        if ($deliveryCustomerName === '') {
            $deliveryCustomerName = trim((string) (isset($deliveryInfo['customer_name']) ? $deliveryInfo['customer_name'] : ''));
        }
        $deliveryCustomerAddress = $customerAddress;
        if ($deliveryCustomerAddress === '' && isset($rememberedDeliveryInfo['customer_address'])) {
            $deliveryCustomerAddress = trim((string) $rememberedDeliveryInfo['customer_address']);
        }
        if ($deliveryCustomerAddress === '') {
            $deliveryCustomerAddress = trim((string) (isset($deliveryInfo['customer_address']) ? $deliveryInfo['customer_address'] : ''));
        }

        $warehouseId = 0;
        $warehouseName = '';
        if (function_exists('shopeeOmsResolveStockOutWarehouseId') && function_exists('shopeeOmsResolveWarehouseNameById')) {
            $warehouseId = shopeeOmsResolveStockOutWarehouseId($connect, $orderRow);
            $warehouseName = shopeeOmsResolveWarehouseNameById($connect, $warehouseId);
        }

        $packageLines = array();
if (!empty($summary['package_lines']) && is_array($summary['package_lines'])) {
    foreach ($summary['package_lines'] as $packageLine) {
        $packageLine = trim((string) $packageLine);
        if ($packageLine !== '') {
            $packageLines[] = $packageLine;
        }
    }
}

        $productLines = array();
        if (!empty($summary['product_lines']) && is_array($summary['product_lines'])) {
            foreach ($summary['product_lines'] as $productLine) {
                $productLine = trim((string) $productLine);
                if ($productLine !== '') {
                    $productLines[] = $productLine;
                }
            }
        }

        $orderFieldLabel = $platform === 'shopee' ? 'Shopee OID' : $platformLabel . ' Order ID';
        $customerFieldLabel = $platform === 'shopee' ? 'Shopee Buyer Username' : $platformLabel . ' Customer';

        $lines = array();

        $lines[] = '【' . ($warehouseName !== '' ? $warehouseName : 'Warehouse Name') . '】';
        if ($platform !== 'shopee') {
            $lines[] = $orderFieldLabel . ': ' . ($orderCode !== '' ? $orderCode : '-');
            $lines[] = '';
        }
        $lines[] = $customerFieldLabel . ': ' . ($customerName !== '' ? $customerName : '-');
        $lines[] = '';

        if (count($packageLines) > 1) {
            $lines[] = 'Package:';
            foreach ($packageLines as $index => $packageLine) {
                $lines[] = ($index + 1) . '. ' . $packageLine;
            }
        } else {
            $lines[] = 'Package: ' . (!empty($packageLines) ? $packageLines[0] : '-');
        }

        $lines[] = '';

        $lines[] = 'Product Details:';
        $lines[] = '';

        if (!empty($productLines)) {
            foreach ($productLines as $index => $productLine) {
                if (count($productLines) > 1) {
                    $lines[] = ($index + 1) . '. ' . $productLine;
                } else {
                    $lines[] = $productLine;
                }
            }
        } else {
            $lines[] = '-';
        }

        if ($platform === 'shopee') {
            $lines[] = '';
            $lines[] = '[Delivery Info]';
            $lines[] = '';
            $lines[] = $orderFieldLabel . ': ' . ($orderCode !== '' ? $orderCode : '-');
            $lines[] = '';
            $lines[] = 'Customer Name: ' . ($deliveryCustomerName !== '' ? $deliveryCustomerName : '-');
            $lines[] = '';
            $lines[] = 'Customer Address: ' . ($deliveryCustomerAddress !== '' ? $deliveryCustomerAddress : '-');
        } else if ($airbillText !== '') {
            $lines[] = '';
            $lines[] = 'Airbill: ' . $airbillText;
        }

        $lines[] = '';

        $lines[] = 'Warehouse Stock-out Link:';
        $lines[] = $link !== '' ? $link : 'Warehouse Stock-out Already Completed';

        return array(
            'link' => $link,
            'text' => implode("\n", $lines),
            'buyer_username' => $customerName,
            'customer_name' => $platform === 'shopee' ? $deliveryCustomerName : $customerName,
            'customer_address' => $platform === 'shopee' ? $deliveryCustomerAddress : $customerAddress,
            'platform' => $platform,
            'warehouse_id' => $warehouseId,
            'warehouse_name' => $warehouseName,
            'package_summary' => isset($summary['package_summary']) ? $summary['package_summary'] : '',
            'product_summary' => !empty($summary['product_lines']) ? implode(', ', $summary['product_lines']) : '',
            'bundle_name' => isset($summary['bundle_name']) ? $summary['bundle_name'] : '',
            'airbill_attachment' => $airbillAttachment,
        );
    }
}

if (!function_exists('shopeeOmsNormalizeAttachmentRelativePath')) {
    function shopeeOmsNormalizeAttachmentRelativePath($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^https?://[^/]+/#i', '', $path);
        $path = preg_replace('#^/?images_server/#i', '', $path);
        $path = ltrim((string) $path, '/');

        if (stripos($path, 'attachment/') !== 0) {
            $pos = stripos($path, 'attachment/');
            if ($pos !== false) {
                $path = substr($path, $pos);
            }
        }

        if (strpos($path, 'attachment/') !== 0) {
            return '';
        }

        return $path;
    }
}

if (!function_exists('shopeeOmsExtractPositiveIds')) {
    function shopeeOmsExtractPositiveIds($rawValue)
    {
        $values = is_array($rawValue) ? $rawValue : explode(',', (string) $rawValue);
        $ids = array();
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && ctype_digit($value)) {
                $intValue = (int) $value;
                if ($intValue > 0) {
                    $ids[] = $intValue;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('shopeeOmsSanitizeSqlAccountFolderName')) {
    function shopeeOmsSanitizeSqlAccountFolderName($name)
    {
        $folder = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $name));
        $folder = trim((string) $folder, '_');
        return $folder !== '' ? $folder : 'sqlaccount';
    }
}

if (!function_exists('shopeeOmsResolveSqlAccountFolderFromBrandIds')) {
    function shopeeOmsResolveSqlAccountFolderFromBrandIds($connect, $brandIds)
    {
        if (!($connect instanceof mysqli)) {
            return 'sqlaccount';
        }

        $brandIds = shopeeOmsExtractPositiveIds($brandIds);
        if (empty($brandIds)) {
            return 'sqlaccount';
        }

        $brandIdList = implode(',', array_map('intval', $brandIds));
        $sql = "SELECT s.name AS sql_account_name
                FROM `" . BRAND . "` b
                LEFT JOIN `" . COMPANY . "` c ON c.id = b.company
                LEFT JOIN `" . SQL_ACC . "` s ON s.id = c.sql_account_id
                WHERE b.id IN (" . $brandIdList . ") AND b.status = 'A'
                ORDER BY FIELD(b.id, " . $brandIdList . ")
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return shopeeOmsSanitizeSqlAccountFolderName(isset($row['sql_account_name']) ? $row['sql_account_name'] : '');
        }

        return 'sqlaccount';
    }
}

if (!function_exists('shopeeOmsResolveSqlAccountFolderFromOrderData')) {
    function shopeeOmsResolveSqlAccountFolderFromOrderData($connect, $brandIds, $packageIds = array())
    {
        $folder = shopeeOmsResolveSqlAccountFolderFromBrandIds($connect, $brandIds);
        if ($folder !== 'sqlaccount') {
            return $folder;
        }

        if (!($connect instanceof mysqli)) {
            return 'sqlaccount';
        }

        $packageIds = shopeeOmsExtractPositiveIds($packageIds);
        if (empty($packageIds)) {
            return 'sqlaccount';
        }

        $packageIdList = implode(',', array_map('intval', $packageIds));
        $sql = "SELECT brand FROM `" . PKG . "` WHERE id IN (" . $packageIdList . ") ORDER BY FIELD(id, " . $packageIdList . ")";
        $result = mysqli_query($connect, $sql);
        if ($result) {
            $packageBrandIds = array();
            while ($row = mysqli_fetch_assoc($result)) {
                $packageBrandIds = array_merge($packageBrandIds, shopeeOmsExtractPositiveIds(isset($row['brand']) ? $row['brand'] : ''));
            }
            if (!empty($packageBrandIds)) {
                return shopeeOmsResolveSqlAccountFolderFromBrandIds($connect, $packageBrandIds);
            }
        }

        return 'sqlaccount';
    }
}

if (!function_exists('shopeeOmsBuildAirbillAttachmentRelativeDir')) {
    function shopeeOmsBuildAirbillAttachmentRelativeDir($connect, $brandIds, $packageIds = array(), $pageName = 'shopee_order_request')
    {
        $safePage = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $pageName);
        if ($safePage === '') {
            $safePage = 'shopee_order_request';
        }

        $sqlAccountFolder = shopeeOmsResolveSqlAccountFolderFromOrderData($connect, $brandIds, $packageIds);
        return 'attachment/' . $sqlAccountFolder . '/' . substr((string) comYMD, 0, 4) . '/' . substr((string) comYMD, 4, 2) . '/' . $safePage . '/';
    }
}

if (!function_exists('shopeeOmsStoreAirbillAttachmentUpload')) {
    function shopeeOmsStoreAirbillAttachmentUpload($fileInfo, $connect, $brandIds, $packageIds = array(), $pageName = 'shopee_order_request', $allowedExt = array('png', 'jpg', 'jpeg', 'pdf'))
    {
        if (!is_array($fileInfo) || !isset($fileInfo['tmp_name']) || !isset($fileInfo['name'])) {
            return array('success' => false, 'path' => '', 'message' => 'No file uploaded.');
        }

        $uploadError = isset($fileInfo['error']) ? (int) $fileInfo['error'] : UPLOAD_ERR_OK;
        if ($uploadError !== UPLOAD_ERR_OK) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to upload the airbill attachment.');
        }

        $originalName = basename((string) $fileInfo['name']);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return array('success' => false, 'path' => '', 'message' => 'Only allow PNG, JPG, JPEG or PDF file');
        }

        $relativeDir = shopeeOmsBuildAirbillAttachmentRelativeDir($connect, $brandIds, $packageIds, $pageName);
        $targetFsDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($targetFsDir)) {
            @mkdir($targetFsDir, 0777, true);
        }
        if (!is_dir($targetFsDir)) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to create airbill attachment directory.');
        }

        $baseName = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $safeBase = trim((string) $safeBase, '_');
        if ($safeBase === '') {
            $safeBase = 'airbill_attachment';
        }

        $targetName = $safeBase . ($ext !== '' ? '.' . $ext : '');
        $targetFile = $targetFsDir . $targetName;
        if (is_file($targetFile)) {
            $targetName = $safeBase . '_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . ($ext !== '' ? '.' . $ext : '');
            $targetFile = $targetFsDir . $targetName;
        }

        if (!move_uploaded_file((string) $fileInfo['tmp_name'], $targetFile)) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to upload the airbill attachment.');
        }

        return array(
            'success' => true,
            'path' => shopeeOmsNormalizeAttachmentRelativePath($relativeDir . $targetName),
            'message' => '',
        );
    }
}

if (!function_exists('shopeeOmsResolveAirbillAttachmentFsPath')) {
    function shopeeOmsResolveAirbillAttachmentFsPath($attachmentValue)
    {
        $attachmentValue = trim(str_replace('\\', '/', (string) $attachmentValue), '/');
        if ($attachmentValue === '') {
            return '';
        }

        $rootDir = defined('ROOT') ? rtrim((string) ROOT, '/\\') : '';
        if ($rootDir === '') {
            return '';
        }

        $normalizedAttachmentPath = shopeeOmsNormalizeAttachmentRelativePath($attachmentValue);
        if ($normalizedAttachmentPath !== '') {
            return $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedAttachmentPath);
        }

        $relativeDir = trim((string) (defined('img_server') ? img_server : '/images_server/'), '/\\') . '/shopee_airbill_attachment/';
        return $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir . basename($attachmentValue));
    }
}

if (!function_exists('shopeeOmsBuildAirbillAttachmentUrl')) {
    function shopeeOmsBuildAirbillAttachmentUrl($attachmentValue)
    {
        $attachmentValue = trim(str_replace('\\', '/', (string) $attachmentValue), '/');
        if ($attachmentValue === '' || !defined('SITEURL')) {
            return '';
        }

        if (strpos($attachmentValue, 'attachment/') === 0) {
            return rtrim((string) SITEURL, '/') . '/' . $attachmentValue;
        }

        return '';
    }
}

if (!function_exists('shopeeOmsRememberWarehouseDeliveryInfo')) {
    function shopeeOmsRememberWarehouseDeliveryInfo($platform, $orderId, $deliveryInfo = array())
    {
        $platform = shopeeOmsNormalizePlatformKey($platform);
        $orderId = (int) $orderId;
        if ($platform === '' || $orderId <= 0 || !is_array($deliveryInfo)) {
            return;
        }

        if (!isset($_SESSION['shopee_oms_warehouse_delivery_info']) || !is_array($_SESSION['shopee_oms_warehouse_delivery_info'])) {
            $_SESSION['shopee_oms_warehouse_delivery_info'] = array();
        }
        if (!isset($_SESSION['shopee_oms_warehouse_delivery_info'][$platform]) || !is_array($_SESSION['shopee_oms_warehouse_delivery_info'][$platform])) {
            $_SESSION['shopee_oms_warehouse_delivery_info'][$platform] = array();
        }

        $currentInfo = isset($_SESSION['shopee_oms_warehouse_delivery_info'][$platform][$orderId]) && is_array($_SESSION['shopee_oms_warehouse_delivery_info'][$platform][$orderId])
            ? $_SESSION['shopee_oms_warehouse_delivery_info'][$platform][$orderId]
            : array();

        foreach (array('customer_name', 'customer_address') as $fieldName) {
            $fieldValue = isset($deliveryInfo[$fieldName]) ? trim((string) $deliveryInfo[$fieldName]) : '';
            if ($fieldValue !== '') {
                $currentInfo[$fieldName] = $fieldValue;
            }
        }

        if (!empty($currentInfo)) {
            $currentInfo['updated_at'] = date('c');
            $_SESSION['shopee_oms_warehouse_delivery_info'][$platform][$orderId] = $currentInfo;
        }
    }
}

if (!function_exists('shopeeOmsGetRememberedWarehouseDeliveryInfo')) {
    function shopeeOmsGetRememberedWarehouseDeliveryInfo($platform, $orderId)
    {
        $platform = shopeeOmsNormalizePlatformKey($platform);
        $orderId = (int) $orderId;
        if ($platform === '' || $orderId <= 0) {
            return array();
        }

        if (!isset($_SESSION['shopee_oms_warehouse_delivery_info'][$platform][$orderId]) || !is_array($_SESSION['shopee_oms_warehouse_delivery_info'][$platform][$orderId])) {
            return array();
        }

        return $_SESSION['shopee_oms_warehouse_delivery_info'][$platform][$orderId];
    }
}

if (!function_exists('shopeeOmsNormalizePdfDeliveryText')) {
    function shopeeOmsNormalizePdfDeliveryText($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/u", "\n", (string) $text);
        $text = preg_replace("/[ \t]+/u", ' ', (string) $text);
        $text = preg_replace("/\n{2,}/u", "\n", (string) $text);
        return trim((string) $text);
    }
}

if (!function_exists('shopeeOmsExtractPdfRawStrings')) {
    function shopeeOmsExtractPdfRawStrings($rawPdfContent)
    {
        $rawPdfContent = (string) $rawPdfContent;
        if ($rawPdfContent === '') {
            return '';
        }

        $parts = array();
        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $rawPdfContent, $matches)) {
            foreach ((array) $matches[0] as $match) {
                $match = substr((string) $match, 1, -1);
                $match = preg_replace_callback('/\\([0-7]{1,3})/', function ($groups) {
                    $code = octdec($groups[1]) & 0xFF;
                    return chr($code);
                }, (string) $match);
                $match = str_replace(array('\n', '\r', '\t', '\(', '\)', '\\\\'), array("\n", "\n", ' ', '(', ')', '\\'), (string) $match);
                $match = shopeeOmsNormalizePdfDeliveryText($match);
                if ($match !== '') {
                    $parts[] = $match;
                }
            }
        }

        return trim(implode("\n", array_unique($parts)));
    }
}

if (!function_exists('shopeeOmsNormalizeDeliveryFieldValue')) {
    function shopeeOmsNormalizeDeliveryFieldValue($value, $collapseLines = true)
    {
        $value = shopeeOmsNormalizePdfDeliveryText($value);
        if ($collapseLines) {
            $value = preg_replace('/\s*\n\s*/u', ', ', (string) $value);
        }
        $value = preg_replace('/\s*,\s*/u', ', ', (string) $value);
        $value = preg_replace('/,\s*,+/u', ', ', (string) $value);
        return trim((string) $value, " ,\t\n\r\0\x0B");
    }
}

if (!function_exists('shopeeOmsExtractAirbillDeliveryInfoFromText')) {
    function shopeeOmsExtractAirbillDeliveryInfoFromText($sourceText)
    {
        $sourceText = shopeeOmsNormalizePdfDeliveryText($sourceText);
        if ($sourceText === '') {
            return array('customer_name' => '', 'customer_address' => '');
        }

        $recipientSection = '';
        if (preg_match_all('/Recipient\s+Details(?:\s*\([^)]+\))?\s*(.*?)(?=(?:Sender\s+Details|Order\s+Details|Scan\s+QR|Join\s+us\s+as\s+couriers|Join\s+us\s+as\s+couriers\s+or\s+sorters|$))/isu', $sourceText, $sectionMatches) && !empty($sectionMatches[1])) {
            foreach ((array) $sectionMatches[1] as $sectionMatch) {
                $sectionMatch = trim((string) $sectionMatch);
                if ($sectionMatch !== '') {
                    $recipientSection = $sectionMatch;
                }
            }
        }
        if ($recipientSection === '') {
            $recipientSection = $sourceText;
        }

        $extractLastMatch = function ($pattern, $text) {
            $value = '';
            if (preg_match_all($pattern, $text, $matches) && !empty($matches[1])) {
                foreach ((array) $matches[1] as $match) {
                    $match = trim((string) $match);
                    if ($match !== '') {
                        $value = $match;
                    }
                }
            }
            return $value;
        };

        $customerName = $extractLastMatch('/\bName\s*:\s*(.+?)(?=\b(?:Phone|Address|Postcode)\s*:|$)/isu', $recipientSection);
        $customerAddress = $extractLastMatch('/\bAddress\s*:\s*(.+?)(?=\b(?:Phone|Postcode|Name)\s*:|$)/isu', $recipientSection);
        $postcode = $extractLastMatch('/\bPostcode\s*:\s*([A-Za-z0-9\- ]{3,20})/iu', $recipientSection);

        $customerName = shopeeOmsNormalizeDeliveryFieldValue($customerName);
        $customerAddress = shopeeOmsNormalizeDeliveryFieldValue($customerAddress);
        $postcode = shopeeOmsNormalizeDeliveryFieldValue($postcode);

        if ($customerAddress !== '' && $postcode !== '') {
            $addressCompare = preg_replace('/\s+/u', '', strtolower($customerAddress));
            $postcodeCompare = preg_replace('/\s+/u', '', strtolower($postcode));
            if ($addressCompare !== '' && $postcodeCompare !== '' && strpos($addressCompare, $postcodeCompare) === false) {
                $customerAddress .= ', ' . $postcode;
            }
        }

        return array(
            'customer_name' => $customerName,
            'customer_address' => $customerAddress,
        );
    }
}

if (!function_exists('shopeeOmsExtractAirbillDeliveryInfoFromAttachment')) {
    function shopeeOmsExtractAirbillDeliveryInfoFromAttachment($attachmentValue)
    {
        $attachmentFsPath = shopeeOmsResolveAirbillAttachmentFsPath($attachmentValue);
        if ($attachmentFsPath === '' || !is_file($attachmentFsPath) || !is_readable($attachmentFsPath)) {
            return array('customer_name' => '', 'customer_address' => '');
        }

        if (strtolower((string) pathinfo($attachmentFsPath, PATHINFO_EXTENSION)) !== 'pdf') {
            return array('customer_name' => '', 'customer_address' => '');
        }

        $maxBytes = 5 * 1024 * 1024;
        $fileSize = @filesize($attachmentFsPath);
        if ($fileSize !== false && $fileSize > $maxBytes) {
            return array('customer_name' => '', 'customer_address' => '');
        }

        $rawPdfContent = @file_get_contents($attachmentFsPath);
        if ($rawPdfContent === false || (string) $rawPdfContent === '') {
            return array('customer_name' => '', 'customer_address' => '');
        }

        return shopeeOmsExtractAirbillDeliveryInfoFromText(shopeeOmsExtractPdfRawStrings($rawPdfContent));
    }
}

if (!function_exists('shopeeOmsRenderAirbillPdfAutofillScript')) {
    function shopeeOmsRenderAirbillPdfAutofillScript()
    {
        return '';
    }
}

if (!function_exists('shopeeOmsRenderAirbillAttachmentPreviewScript')) {
    function shopeeOmsRenderAirbillAttachmentPreviewScript()
    {
        return <<<'JS'
if (!window.shopeeOmsAirbillAttachmentPreview) {
    window.shopeeOmsAirbillAttachmentPreview = (function () {
        function bind(config) {
            config = config || {};
            var fileInput = document.querySelector(config.fileInputSelector || '');
            var previewWrap = document.querySelector(config.previewWrapSelector || '');
            if (!fileInput || !previewWrap) {
                return false;
            }

            var currentPreviewUrl = null;

            function clearPreviewObjectUrl() {
                if (currentPreviewUrl) {
                    URL.revokeObjectURL(currentPreviewUrl);
                    currentPreviewUrl = null;
                }
            }

            function hidePreview() {
                clearPreviewObjectUrl();
                previewWrap.innerHTML = '';
                previewWrap.style.display = 'none';
            }

            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                if (!file) {
                    hidePreview();
                    return;
                }

                clearPreviewObjectUrl();
                currentPreviewUrl = URL.createObjectURL(file);
                var fileName = String(file.name || '').toLowerCase();

                previewWrap.style.display = 'block';

                if ((file.type || '').indexOf('image/') === 0) {
                    previewWrap.innerHTML =
                        '<img src="' + currentPreviewUrl + '" alt="Airbill Attachment Preview">';
                } else if (file.type === 'application/pdf' || fileName.endsWith('.pdf')) {
                    previewWrap.innerHTML =
                        '<iframe src="' + currentPreviewUrl + '" title="Airbill Attachment Preview"></iframe>';
                } else {
                    hidePreview();
                }
            });

            window.addEventListener('beforeunload', clearPreviewObjectUrl);
            return true;
        }

        return {
            bind: bind
        };
    })();
}
JS;
    }
}

if (!function_exists('shopeeOmsGetClientIp')) {
    function shopeeOmsGetClientIp()
    {
        $candidates = array();
        $headerKeys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        );

        foreach ($headerKeys as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }
            $raw = trim((string) $_SERVER[$key]);
            if ($raw === '') {
                continue;
            }

            $parts = explode(',', $raw);
            foreach ($parts as $part) {
                $ip = trim((string) $part);
                if ($ip !== '') {
                    $candidates[] = $ip;
                }
            }
        }

        $firstValid = '';
        foreach ($candidates as $candidate) {
            if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
                continue;
            }
            if ($firstValid === '') {
                $firstValid = $candidate;
            }
            if (!shopeeOmsIsPrivateOrReservedIp($candidate)) {
                return $candidate;
            }
        }

        return $firstValid;
    }
}

if (!function_exists('shopeeOmsIsPrivateOrReservedIp')) {
    function shopeeOmsIsPrivateOrReservedIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

if (!function_exists('shopeeOmsLookupCountryCode')) {
    function shopeeOmsLookupCountryCode($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        if (shopeeOmsIsPrivateOrReservedIp($ip)) {
            return '';
        }

        static $cache = array();
        if (array_key_exists($ip, $cache)) {
            return $cache[$ip];
        }

        $code = '';
        if (function_exists('geoip_country_code_by_name')) {
            $geoipCode = @geoip_country_code_by_name($ip);
            if (is_string($geoipCode)) {
                $geoipCode = strtoupper(trim((string) $geoipCode));
                if (preg_match('/^[A-Z]{2}$/', $geoipCode)) {
                    $code = $geoipCode;
                }
            }
        }

        if ($code === '') {
            $url = IPAPI_URL . rawurlencode($ip) . '/country/';
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => 3,
                    'ignore_errors' => true,
                ),
                'ssl' => array(
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ),
            ));

            $resp = @file_get_contents($url, false, $context);
            if ($resp !== false) {
                $respCode = strtoupper(trim((string) $resp));
                if (preg_match('/^[A-Z]{2}$/', $respCode)) {
                    $code = $respCode;
                }
            }
        }

        $cache[$ip] = $code;
        return $code;
    }
}

if (!function_exists('shopeeOmsAuditLog')) {
    function shopeeOmsAuditLog($event, $message, $context = array())
    {
        global $connect, $cdate, $ctime;

        $safeEvent = trim((string) $event);
        $safeMessage = trim((string) $message);
        if ($safeEvent === '') {
            $safeEvent = 'scan';
        }
        if ($safeMessage === '') {
            $safeMessage = 'No message.';
        }

        $ctxText = '';
        if (is_array($context) && count($context) > 0) {
            $pairs = array();
            foreach ($context as $k => $v) {
                $pairs[] = (string) $k . '=' . (is_array($v) ? implode(',', $v) : (string) $v);
            }
            $ctxText = ' [' . implode('; ', $pairs) . ']';
        }

        $auditConn = null;
        if (isset($connect) && ($connect instanceof mysqli)) {
            $auditConn = $connect;
        } else {
            $auditConn = @mysqli_connect(dbhost, dbuser, dbpwd, dbname);
        }
        if (!($auditConn instanceof mysqli)) {
            return;
        }

        $auditMessage = $safeEvent . ': ' . $safeMessage . $ctxText;
        $userId = (defined('USER_ID') && USER_ID !== '' ? USER_ID : 'QR_PUBLIC');
        $logDate = !empty($cdate) ? $cdate : date('Y-m-d');
        $logTime = !empty($ctime) ? $ctime : date('H:i:s');
        $screenType = 'Shopee Order QR Scan';
        $logAction = 9;

        try {
            $sql = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, user_id, action_message, create_date, create_time, create_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($auditConn, $sql);
            if (!$stmt) {
                return;
            }
            mysqli_stmt_bind_param($stmt, 'issssss', $logAction, $screenType, $userId, $auditMessage, $logDate, $logTime, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } catch (Throwable $th) {
            return;
        }
    }
}

if (!function_exists('shopeeOmsCreateWarehouseToken')) {
    function shopeeOmsCreateWarehouseToken($cmsConnect, $financeConnect, $orderRow, $actorUserId = '', $source = 'shopee')
    {
        if (!is_array($orderRow) || !($financeConnect instanceof mysqli)) {
            return array(
                'success' => false,
                'message' => 'Order data is unavailable for Step A package generation.',
            );
        }

        $orderId = isset($orderRow['id']) ? (int) $orderRow['id'] : 0;
        if ($orderId <= 0) {
            return array(
                'success' => false,
                'message' => 'Invalid order ID for Step A package generation.',
            );
        }

        $sourceConfig = shopeeOmsResolveOrderSourceConfig($source, shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
        $tokenTableHasPlatform = shopeeOmsTableHasColumn($financeConnect, dbFinance, ORDER_WAREHOUSE_SCAN_TOKEN, 'platform');
        $safeOrderId = (int) $orderId;
        $existingConditions = array(
            "order_id = " . $safeOrderId,
            "token_type = 'stock_out'",
            "status = 'A'",
        );
        if ($tokenTableHasPlatform) {
            $existingConditions[] = "platform = '" . mysqli_real_escape_string($financeConnect, $platform) . "'";
        }
        $existingSql = "SELECT * FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE " . implode(' AND ', $existingConditions) . " ORDER BY id DESC LIMIT 1";
        $existingResult = mysqli_query($financeConnect, $existingSql);
        if ($existingResult && mysqli_num_rows($existingResult) > 0) {
            $existingRow = mysqli_fetch_assoc($existingResult);
            $messageInfo = shopeeOmsBuildWarehouseMessage($orderRow, isset($existingRow['token']) ? $existingRow['token'] : '', $cmsConnect, $financeConnect, $sourceConfig);
            return array(
                'success' => true,
                'message' => 'Warehouse token already exists.',
                'token_row' => $existingRow,
                'notification' => $messageInfo,
            );
        }

        try {
            $tokenValue = function_exists('random_bytes')
                ? bin2hex(random_bytes(24))
                : md5(uniqid((string) $orderId, true) . microtime(true));
        } catch (Exception $exception) {
            $tokenValue = md5(uniqid((string) $orderId, true) . microtime(true));
        }

        $messageInfo = shopeeOmsBuildWarehouseMessage($orderRow, $tokenValue, $cmsConnect, $financeConnect, $sourceConfig);
        $actorUserId = trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM';
        $safeOrderCode = mysqli_real_escape_string($financeConnect, shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig));
        $safeToken = mysqli_real_escape_string($financeConnect, $tokenValue);
        $safeCustomerName = mysqli_real_escape_string($financeConnect, (string) (isset($messageInfo['customer_name']) ? $messageInfo['customer_name'] : (isset($messageInfo['buyer_username']) ? $messageInfo['buyer_username'] : '')));
        $addressField = isset($sourceConfig['address_field']) ? (string) $sourceConfig['address_field'] : 'customer_address';
        $airbillAttachmentField = isset($sourceConfig['airbill_attachment_field']) ? (string) $sourceConfig['airbill_attachment_field'] : 'airbill_attachment';
        $safeCustomerAddress = mysqli_real_escape_string($financeConnect, (string) (isset($messageInfo['customer_address']) ? $messageInfo['customer_address'] : (isset($orderRow[$addressField]) ? $orderRow[$addressField] : '')));
        $safePackageSummary = mysqli_real_escape_string($financeConnect, (string) $messageInfo['package_summary']);
        $safeProductSummary = mysqli_real_escape_string($financeConnect, (string) $messageInfo['product_summary']);
        $safeAirbillAttachment = mysqli_real_escape_string($financeConnect, (string) (isset($orderRow[$airbillAttachmentField]) ? $orderRow[$airbillAttachmentField] : ''));
        $safePayload = mysqli_real_escape_string($financeConnect, (string) $messageInfo['text']);
        $safeActor = mysqli_real_escape_string($financeConnect, $actorUserId);
        $insertColumns = array('order_id', 'order_code', 'token', 'token_type', 'customer_name', 'customer_address', 'package_summary', 'product_summary', 'airbill_attachment', 'payload_text');
        $insertValues = array((string) $safeOrderId, "'" . $safeOrderCode . "'", "'" . $safeToken . "'", "'stock_out'", "'" . $safeCustomerName . "'", "'" . $safeCustomerAddress . "'", "'" . $safePackageSummary . "'", "'" . $safeProductSummary . "'", "'" . $safeAirbillAttachment . "'", "'" . $safePayload . "'");
        if ($tokenTableHasPlatform) {
            $insertColumns[] = 'platform';
            $insertValues[] = "'" . mysqli_real_escape_string($financeConnect, $platform) . "'";
        }
        $insertColumns = array_merge($insertColumns, array('create_by', 'create_date', 'create_time', 'status'));
        $insertValues = array_merge($insertValues, array("'" . $safeActor . "'", 'CURDATE()', 'CURTIME()', "'A'"));
        $insertSql = "INSERT INTO `" . ORDER_WAREHOUSE_SCAN_TOKEN . "`
            (`" . implode('`, `', $insertColumns) . "`)
            VALUES
            (" . implode(', ', $insertValues) . ")";

        if (!mysqli_query($financeConnect, $insertSql)) {
            return array(
                'success' => false,
                'message' => 'Unable to save warehouse token.',
            );
        }

        return array(
            'success' => true,
            'message' => 'Warehouse token generated successfully.',
            'token_row' => array(
                'id' => mysqli_insert_id($financeConnect),
                'order_id' => $orderId,
                'order_code' => shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig),
                'platform' => $platform,
                'token' => $tokenValue,
            ),
            'notification' => $messageInfo,
        );
    }
}

if (!function_exists('shopeeOmsSendWarehouseNotification')) {
    function shopeeOmsSendWarehouseNotification($cmsConnect, $financeConnect, $tokenRow, $notificationInfo, $sourcePage = '')
    {
        if (!is_array($tokenRow) || !isset($tokenRow['id']) || !is_array($notificationInfo)) {
            return array(
                'success' => false,
                'sent' => false,
                'message' => 'Warehouse notification payload is unavailable.',
            );
        }

        $resolvedWarehouseId = isset($notificationInfo['warehouse_id'])
            ? shopeeOmsNormalizeWarehouseId($notificationInfo['warehouse_id'])
            : 0;
        if ($resolvedWarehouseId <= 0) {
            $resolvedWarehouseId = shopeeOmsGetDefaultWarehouseId($cmsConnect);
        }

        $warehouseTokenInfo = shopeeOmsFindWarehouseTokenSetting($cmsConnect, $resolvedWarehouseId);
        $tokenSetting = isset($warehouseTokenInfo['token_setting']) && is_array($warehouseTokenInfo['token_setting'])
            ? $warehouseTokenInfo['token_setting']
            : array();
        if (empty($tokenSetting)) {
            $tokenNotSetMessage = 'Telegram Notification Bot is not set for this warehouse. Please update Warehouse setting.';
            if ($financeConnect instanceof mysqli) {
                $safeResultMessage = mysqli_real_escape_string($financeConnect, $tokenNotSetMessage);
                mysqli_query($financeConnect, "UPDATE `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` SET `sent_result` = '" . $safeResultMessage . "', `update_by` = 'SYSTEM', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE id = " . (int) $tokenRow['id'] . " LIMIT 1");
            }
            return array(
                'success' => true,
                'sent' => false,
                'message' => $tokenNotSetMessage,
            );
        }

        $botToken = trim((string) (isset($tokenSetting['bot_token']) ? $tokenSetting['bot_token'] : ''));
        $chatId = shopeeOmsResolveChatIdFromTokenRow($tokenSetting);
        if ($botToken === '' || $chatId === '') {
            return array(
                'success' => true,
                'sent' => false,
                'message' => 'Warehouse package generated. Telegram Bot Token or Chat ID is missing.',
            );
        }

        $errorMessage = '';
        $httpCode = 0;
        $attachmentValue = isset($notificationInfo['airbill_attachment']) ? (string) $notificationInfo['airbill_attachment'] : '';
        $attachmentFsPath = shopeeOmsResolveAirbillAttachmentFsPath($attachmentValue);
        $attachmentUrl = shopeeOmsBuildAirbillAttachmentUrl($attachmentValue);
        $hasReadableAttachment = ($attachmentFsPath !== '' && file_exists($attachmentFsPath) && is_readable($attachmentFsPath));
        $messageText = (string) (isset($notificationInfo['text']) ? $notificationInfo['text'] : '');
        $fallbackMessageText = $messageText;
        if ($attachmentUrl !== '') {
            $fallbackMessageText .= ($fallbackMessageText !== '' ? "\n" : '') . "Airbill Attachment Link:\n" . $attachmentUrl;
        }
        $response = false;
        $finalResponse = false;
        $documentSent = false;
        $messageSent = false;

        if ($hasReadableAttachment) {
            $uploadResult = shopeeOmsSendTelegramAttachment($botToken, $chatId, $attachmentFsPath, $errorMessage, $httpCode);
            $documentSent = !empty($uploadResult['success']);
            $response = isset($uploadResult['response']) ? $uploadResult['response'] : false;

            if (!$documentSent && $attachmentUrl !== '') {
                $urlUploadError = '';
                $urlUploadHttpCode = 0;
                $urlUploadResult = shopeeOmsSendTelegramAttachmentByUrl($botToken, $chatId, $attachmentUrl, basename($attachmentFsPath), $urlUploadError, $urlUploadHttpCode);
                if (!empty($urlUploadResult['success'])) {
                    $documentSent = true;
                    $response = isset($urlUploadResult['response']) ? $urlUploadResult['response'] : $response;
                    $httpCode = $urlUploadHttpCode > 0 ? $urlUploadHttpCode : $httpCode;
                } else if ($urlUploadError !== '') {
                    $errorMessage .= ($errorMessage !== '' ? ' ' : '') . $urlUploadError;
                    if ($urlUploadHttpCode > 0) {
                        $httpCode = $urlUploadHttpCode;
                    }
                }
            }

            if ($documentSent && $messageText !== '') {
                $messageUrl = TELEGRAM_API . $botToken . '/sendMessage';
                $messagePayload = array(
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'disable_web_page_preview' => false,
                );
                $messageError = '';
                $messageHttpCode = 0;
                $messageResponse = shopeeOmsTelegramRequest($messageUrl, $messagePayload, $messageError, $messageHttpCode);
                $messageDecoded = json_decode((string) $messageResponse, true);
                $messageSent = (is_array($messageDecoded) && !empty($messageDecoded['ok']));

                if (!$messageSent) {
                    $errorMessage = shopeeOmsTelegramDescribeResponse($messageResponse, $messageError !== '' ? $messageError : 'Telegram warehouse summary message was not sent.');
                    $httpCode = $messageHttpCode > 0 ? $messageHttpCode : $httpCode;
                    $finalResponse = $messageResponse;
                } else {
                    $finalResponse = $messageResponse;
                }
            } else {
                if (!$documentSent) {
                    $errorMessage = shopeeOmsTelegramDescribeResponse($response, $errorMessage !== '' ? $errorMessage : 'Telegram warehouse attachment upload was not sent.');
                }
                $messageSent = ($messageText === '');
                $finalResponse = $response;
            }

            if (!$documentSent && $messageText !== '') {
                $fallbackUrl = TELEGRAM_API . $botToken . '/sendMessage';
                $fallbackPayload = array(
                    'chat_id' => $chatId,
                    'text' => $fallbackMessageText,
                    'disable_web_page_preview' => false,
                );
                $fallbackError = '';
                $fallbackHttpCode = 0;
                $fallbackResponse = shopeeOmsTelegramRequest($fallbackUrl, $fallbackPayload, $fallbackError, $fallbackHttpCode);
                $fallbackDecoded = json_decode((string) $fallbackResponse, true);
                if (is_array($fallbackDecoded) && !empty($fallbackDecoded['ok'])) {
                    $finalResponse = $fallbackResponse;
                } else if ($fallbackError !== '' || $fallbackHttpCode > 0) {
                    $errorMessage .= ($errorMessage !== '' ? ' ' : '') . shopeeOmsTelegramDescribeResponse($fallbackResponse, $fallbackError !== '' ? $fallbackError : 'Telegram fallback summary message was not sent.');
                    if ($fallbackHttpCode > 0) {
                        $httpCode = $fallbackHttpCode;
                    }
                    $finalResponse = $fallbackResponse;
                }
            }
        } else {
            if ($attachmentUrl !== '') {
                $urlUploadError = '';
                $urlUploadHttpCode = 0;
                $urlUploadResult = shopeeOmsSendTelegramAttachmentByUrl($botToken, $chatId, $attachmentUrl, basename($attachmentValue), $urlUploadError, $urlUploadHttpCode);
                $documentSent = !empty($urlUploadResult['success']);
                $response = isset($urlUploadResult['response']) ? $urlUploadResult['response'] : false;
                if ($urlUploadError !== '') {
                    $errorMessage = $urlUploadError;
                    if ($urlUploadHttpCode > 0) {
                        $httpCode = $urlUploadHttpCode;
                    }
                }
            }

            if ($documentSent && $messageText !== '') {
                $messageUrl = TELEGRAM_API . $botToken . '/sendMessage';
                $messagePayload = array(
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'disable_web_page_preview' => false,
                );
                $messageError = '';
                $messageHttpCode = 0;
                $messageResponse = shopeeOmsTelegramRequest($messageUrl, $messagePayload, $messageError, $messageHttpCode);
                $messageDecoded = json_decode((string) $messageResponse, true);
                $messageSent = (is_array($messageDecoded) && !empty($messageDecoded['ok']));

                if (!$messageSent) {
                    $errorMessage = shopeeOmsTelegramDescribeResponse($messageResponse, $messageError !== '' ? $messageError : 'Telegram warehouse summary message was not sent.');
                    $httpCode = $messageHttpCode > 0 ? $messageHttpCode : $httpCode;
                    $finalResponse = $messageResponse;
                } else {
                    $finalResponse = $messageResponse;
                }
            } else {
                $apiUrl = TELEGRAM_API . $botToken . '/sendMessage';
                $payload = array(
                    'chat_id' => $chatId,
                    'text' => $fallbackMessageText,
                    'disable_web_page_preview' => false,
                );
                $response = shopeeOmsTelegramRequest($apiUrl, $payload, $errorMessage, $httpCode);
                $messageDecoded = json_decode((string) $response, true);
                $messageSent = (is_array($messageDecoded) && !empty($messageDecoded['ok']));
                if (!$messageSent) {
                    $errorMessage = shopeeOmsTelegramDescribeResponse($response, $errorMessage !== '' ? $errorMessage : 'Telegram warehouse notification was not sent.');
                }
                $finalResponse = $response;
            }
        }

        $isSent = $hasReadableAttachment
            ? ($documentSent && $messageSent)
            : $messageSent;
        $resultMessage = $isSent ? 'Telegram warehouse notification sent successfully.' : (($errorMessage !== '' ? $errorMessage : 'Telegram warehouse notification was not sent.') . ($httpCode > 0 ? (' HTTP ' . $httpCode . '.') : ''));

        if ($financeConnect instanceof mysqli) {
            $safeResultMessage = mysqli_real_escape_string($financeConnect, $resultMessage);
            mysqli_query($financeConnect, "UPDATE `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` SET `sent_result` = '" . $safeResultMessage . "', `update_by` = 'SYSTEM', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE id = " . (int) $tokenRow['id'] . " LIMIT 1");
        }

        return array(
            'success' => true,
            'sent' => $isSent,
            'message' => $resultMessage,
        );
    }
}

if (!function_exists('shopeeOmsLogTransition')) {
    function shopeeOmsLogTransition($connect, $data)
    {
        if (!($connect instanceof mysqli) || !is_array($data)) {
            return false;
        }

        $orderId = isset($data['order_id']) ? (int) $data['order_id'] : 0;
        if ($orderId <= 0) {
            return false;
        }

        $safeOrderCode = mysqli_real_escape_string($connect, (string) (isset($data['order_code']) ? $data['order_code'] : ''));
        $safeFromStatus = mysqli_real_escape_string($connect, (string) (isset($data['from_status']) ? $data['from_status'] : ''));
        $safeToStatus = mysqli_real_escape_string($connect, (string) (isset($data['to_status']) ? $data['to_status'] : ''));
        $safeAction = mysqli_real_escape_string($connect, (string) (isset($data['transition_action']) ? $data['transition_action'] : ''));
        $safeUserId = mysqli_real_escape_string($connect, trim((string) (isset($data['user_id']) ? $data['user_id'] : '')) !== '' ? trim((string) $data['user_id']) : 'SYSTEM');
        $userGroupId = isset($data['user_group_id']) ? (int) $data['user_group_id'] : 0;
        $safeRemark = mysqli_real_escape_string($connect, (string) (isset($data['remark']) ? $data['remark'] : ''));
        $safeSourcePage = mysqli_real_escape_string($connect, (string) (isset($data['source_page']) ? $data['source_page'] : ''));
        $safeRelatedRef = mysqli_real_escape_string($connect, (string) (isset($data['related_token_scan_id']) ? $data['related_token_scan_id'] : ''));
        $platform = shopeeOmsNormalizePlatformKey(isset($data['platform']) ? $data['platform'] : '') ?: 'shopee';
        $tableHasPlatform = shopeeOmsTableHasColumn($connect, dbFinance, ORDER_STATUS_TRANSITION_LOG, 'platform');
        $insertColumns = array('order_id', 'order_code', 'from_status', 'to_status', 'transition_action', 'user_id', 'user_group_id', 'remark', 'source_page', 'related_token_scan_id');
        $insertValues = array((string) $orderId, "'" . $safeOrderCode . "'", "'" . $safeFromStatus . "'", "'" . $safeToStatus . "'", "'" . $safeAction . "'", "'" . $safeUserId . "'", (string) $userGroupId, "'" . $safeRemark . "'", "'" . $safeSourcePage . "'", "'" . $safeRelatedRef . "'");
        if ($tableHasPlatform) {
            $insertColumns[] = 'platform';
            $insertValues[] = "'" . mysqli_real_escape_string($connect, $platform) . "'";
        }
        $insertColumns = array_merge($insertColumns, array('transition_at', 'create_date', 'create_time', 'status'));
        $insertValues = array_merge($insertValues, array('NOW()', 'CURDATE()', 'CURTIME()', "'A'"));

        $sql = "INSERT INTO `" . ORDER_STATUS_TRANSITION_LOG . "`
            (`" . implode('`, `', $insertColumns) . "`)
            VALUES
            (" . implode(', ', $insertValues) . ")";

        return (bool) mysqli_query($connect, $sql);
    }
}

if (!function_exists('shopeeOmsBuildParcelReceivedRemark')) {
    function shopeeOmsBuildParcelReceivedRemark($connect, $actorUserId, $fallbackName = 'user')
    {
        $fallbackName = trim((string) $fallbackName);
        if ($fallbackName === '') {
            $fallbackName = 'user';
        }

        $actorName = '';
        $actorUserId = trim((string) $actorUserId);
        if ($actorUserId !== '' && $connect instanceof mysqli) {
            $actorName = trim((string) commonResolveUserDisplayName($connect, $actorUserId));
            if ($actorName === $actorUserId && ctype_digit($actorUserId)) {
                $actorName = '';
            }
        }

        if ($actorName === '' || strcasecmp($actorName, 'SYSTEM') === 0) {
            $actorName = $fallbackName;
        }

        return 'Parcel received confirmed by ' . $actorName . '.';
    }
}

if (!function_exists('shopeeOmsShouldNormalizeParcelReceivedRemark')) {
    function shopeeOmsShouldNormalizeParcelReceivedRemark($remark)
    {
        $remark = trim((string) $remark);
        if ($remark === '') {
            return true;
        }

        if (preg_match('/^parcel received confirmed by .+\.$/i', $remark)) {
            return true;
        }

        $normalizedRemark = strtolower(preg_replace('/\s+/', ' ', $remark));
        return in_array($normalizedRemark, array(
            'order status update to parcel received',
            'parcel received confirmed',
        ), true);
    }
}

if (!function_exists('shopeeOmsBackfillParcelReceivedTransitionRemarks')) {
    function shopeeOmsBackfillParcelReceivedTransitionRemarks($cmsConnect, $financeConnect)
    {
        static $hasRunInRequest = false;

        if ($hasRunInRequest) {
            return true;
        }

        $hasRunInRequest = true;

        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli) || !defined('ORDER_STATUS_TRANSITION_LOG')) {
            return false;
        }

        $settingKey = 'shopee_oms_parcel_received_remark_backfill_v1';
        if (shopeeOmsGetSetting($cmsConnect, $settingKey, '') === 'done') {
            return true;
        }

        $sql = "SELECT `id`, `user_id`, `remark`
                FROM `" . ORDER_STATUS_TRANSITION_LOG . "`
                WHERE `status` = 'A'
                  AND `to_status` = 'PR'";
        $result = mysqli_query($financeConnect, $sql);
        if (!$result) {
            return false;
        }

        $updatedCount = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $currentRemark = isset($row['remark']) ? (string) $row['remark'] : '';
            if (!shopeeOmsShouldNormalizeParcelReceivedRemark($currentRemark)) {
                continue;
            }

            $normalizedRemark = shopeeOmsBuildParcelReceivedRemark(
                $cmsConnect,
                isset($row['user_id']) ? $row['user_id'] : '',
                'user'
            );
            if ($normalizedRemark === trim($currentRemark)) {
                continue;
            }

            $historyId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($historyId <= 0) {
                continue;
            }

            $updateSql = "UPDATE `" . ORDER_STATUS_TRANSITION_LOG . "`
                          SET `remark` = '" . mysqli_real_escape_string($financeConnect, $normalizedRemark) . "'
                          WHERE `id` = " . $historyId . " LIMIT 1";
            if (mysqli_query($financeConnect, $updateSql)) {
                $updatedCount++;
            }
        }

        shopeeOmsSetSetting(
            $cmsConnect,
            $settingKey,
            'done',
            'Backfilled parcel received transition remarks (' . $updatedCount . ' rows).',
            defined('USER_ID') ? USER_ID : '1'
        );

        return true;
    }
}

if (!function_exists('shopeeOmsBuildLogPlatformCondition')) {
    function shopeeOmsBuildLogPlatformCondition($connect, $platform, $alias = 'l')
    {
        $platform = shopeeOmsNormalizePlatformKey($platform) ?: 'shopee';
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', (string) $alias);
        if ($alias === '') {
            $alias = 'l';
        }

        if (!shopeeOmsTableHasColumn($connect, dbFinance, ORDER_STATUS_TRANSITION_LOG, 'platform')) {
            return $platform === 'shopee' ? '' : '1 = 0';
        }

        $qualifiedColumn = $alias . '.platform';
        if ($platform === 'shopee') {
            return "(" . $qualifiedColumn . " = 'shopee' OR " . $qualifiedColumn . " = '' OR " . $qualifiedColumn . " IS NULL)";
        }

        return $qualifiedColumn . " = '" . mysqli_real_escape_string($connect, $platform) . "'";
    }
}

if (!function_exists('shopeeOmsResolveStatusTransitionErrorDisplay')) {
    function shopeeOmsResolveStatusTransitionErrorDisplay($targetStatus, $message, $fallbackMessage = 'Unable to update order status.')
    {
        $resolvedMessage = trim((string) $message);
        if ($resolvedMessage === '') {
            $resolvedMessage = trim((string) $fallbackMessage);
        }
        if ($resolvedMessage === '') {
            $resolvedMessage = 'Unable to update order status.';
        }

        return array(
            'show_inline_stock_error' => shopeeOmsNormalizeStatusCode($targetStatus) === 'TP',
            'message' => $resolvedMessage,
        );
    }
}

if (!function_exists('shopeeOmsResolveStatusTransitionErrorState')) {
    function shopeeOmsResolveStatusTransitionErrorState($targetStatus, $message, $fallbackMessage = 'Unable to update order status.')
    {
        $display = shopeeOmsResolveStatusTransitionErrorDisplay($targetStatus, $message, $fallbackMessage);

        return array(
            'stock_out_warehouse_err' => !empty($display['show_inline_stock_error']) ? (string) $display['message'] : '',
            'popup_error_message' => !empty($display['show_inline_stock_error']) ? '' : (string) $display['message'],
        );
    }
}

if (!function_exists('shopeeOmsExecuteTransition')) {
    function shopeeOmsExecuteTransition($cmsConnect, $financeConnect, $orderId, $targetStatus, $options = array())
    {
        $orderId = (int) $orderId;
        $targetStatus = shopeeOmsNormalizeStatusCode($targetStatus);
        $options = is_array($options) ? $options : array();
        $resolvedSource = isset($options['platform']) ? $options['platform'] : (isset($options['table_name']) ? $options['table_name'] : 'shopee');
        if (isset($options['order_row']) && is_array($options['order_row']) && !empty($options['order_row']['__oms_platform'])) {
            $resolvedSource = $options['order_row']['__oms_platform'];
        }
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($resolvedSource, 'shopee');
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
        $tblName = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ;
        $tableDbName = isset($sourceConfig['db_name']) ? (string) $sourceConfig['db_name'] : dbFinance;
        $orderConnect = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
        $actorUserId = trim((string) (isset($options['actor_user_id']) ? $options['actor_user_id'] : (defined('USER_ID') ? USER_ID : 'SYSTEM')));
        if ($actorUserId === '') {
            $actorUserId = 'SYSTEM';
        }
        $actorUserGroupId = isset($options['actor_user_group_id']) ? (int) $options['actor_user_group_id'] : (defined('USER_GROUP') ? (int) USER_GROUP : 0);
        $sourcePage = trim((string) (isset($options['source_page']) ? $options['source_page'] : 'Shopee OMS'));
        $remark = trim((string) (isset($options['remark']) ? $options['remark'] : ''));
        $actionName = trim((string) (isset($options['action']) ? $options['action'] : ''));
        $relatedRef = trim((string) (isset($options['related_token_scan_id']) ? $options['related_token_scan_id'] : ''));
        $skipPermission = !empty($options['skip_permission']);
        $fieldUpdates = isset($options['field_updates']) && is_array($options['field_updates']) ? $options['field_updates'] : array();
        $allowAutoFollowUp = !isset($options['allow_auto_follow_up']) || !empty($options['allow_auto_follow_up']);

        if ($orderId <= 0) {
            return array('success' => false, 'message' => 'Invalid order ID.');
        }

        if ($targetStatus === '') {
            return array('success' => false, 'message' => 'Invalid target status.');
        }

        $orderRow = shopeeOmsLoadOrder($orderConnect, $orderId, $sourceConfig);
        if (empty($orderRow)) {
            return array('success' => false, 'message' => 'Order not found.');
        }

        $fromStatus = shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '');
        if ($fromStatus === $targetStatus) {
            return array('success' => false, 'message' => 'Order is already in ' . shopeeOmsGetStatusLabel($targetStatus) . ' status.');
        }

        $transitionDefinitions = shopeeOmsTransitionDefinitions();
        $transitionInfo = isset($transitionDefinitions[$fromStatus][$targetStatus]) ? $transitionDefinitions[$fromStatus][$targetStatus] : array();
        if (empty($transitionInfo)) {
            return array('success' => false, 'message' => 'Transition ' . shopeeOmsGetStatusLabel($fromStatus) . ' -> ' . shopeeOmsGetStatusLabel($targetStatus) . ' is not allowed.');
        }

        if ($actionName === '') {
            $actionName = isset($transitionInfo['action']) ? (string) $transitionInfo['action'] : 'status_transition';
        }

        $requiresPermission = !empty($transitionInfo['requires_permission']);
        if ($requiresPermission && !$skipPermission && !shopeeOmsHasTransitionPermission($cmsConnect, $fromStatus, $targetStatus, $actorUserGroupId, $orderRow, $actorUserId)) {
            return array('success' => false, 'message' => 'You are not allowed to perform this status transition.');
        }

        $airbillField = isset($sourceConfig['airbill_no_field']) && trim((string) $sourceConfig['airbill_no_field']) !== ''
            ? (string) $sourceConfig['airbill_no_field']
            : 'airbill_no';
        $effectiveAirbill = isset($fieldUpdates[$airbillField]) ? (string) $fieldUpdates[$airbillField] : (isset($orderRow[$airbillField]) ? $orderRow[$airbillField] : '');
        $airbillValidation = shopeeOmsValidateInitialStatusAndAirbill($targetStatus, $effectiveAirbill);
        if (!$airbillValidation['valid']) {
            return array('success' => false, 'message' => $airbillValidation['message']);
        }

        if ($targetStatus === 'TP') {
            $warehouseStockValidation = shopeeOmsValidateWarehouseStockForOrder($cmsConnect, $financeConnect, $orderRow, array(
                'platform' => $platform,
            ));
            if (empty($warehouseStockValidation['success'])) {
                return $warehouseStockValidation;
            }
        }

        $safeActorUserId = mysqli_real_escape_string($orderConnect, $actorUserId);
        $assignments = array(
            "order_status = '" . mysqli_real_escape_string($orderConnect, $targetStatus) . "'",
            "update_by = '" . $safeActorUserId . "'",
            "update_date = CURDATE()",
            "update_time = CURTIME()"
        );
        if (shopeeOmsTableHasColumn($orderConnect, $tableDbName, $tblName, 'latest_transition_at')) {
            $assignments[] = "latest_transition_at = NOW()";
        }
        foreach ($fieldUpdates as $fieldName => $fieldValue) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $fieldName)) {
                continue;
            }
            if (!shopeeOmsTableHasColumn($orderConnect, $tableDbName, $tblName, $fieldName)) {
                continue;
            }

            if ($fieldValue === null) {
                $assignments[] = "`" . $fieldName . "` = NULL";
            } else if (is_int($fieldValue) || is_float($fieldValue)) {
                $assignments[] = "`" . $fieldName . "` = " . $fieldValue;
            } else {
                $assignments[] = "`" . $fieldName . "` = '" . mysqli_real_escape_string($orderConnect, (string) $fieldValue) . "'";
            }
        }

        $updateSql = "UPDATE `" . $tblName . "` SET " . implode(', ', $assignments) . " WHERE id = " . $orderId . " LIMIT 1";
        if (!mysqli_query($orderConnect, $updateSql)) {
            if ($targetStatus === 'TP' && trim((string) (isset($orderRow[$airbillField]) ? $orderRow[$airbillField] : '')) === '') {
                return array('success' => false, 'message' => 'Airbill is required when Order Status is To Pack.');
            }

            return array('success' => false, 'message' => 'Unable to update order status.');
        }

        shopeeOmsLogTransition($financeConnect, array(
            'order_id' => $orderId,
            'order_code' => shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig),
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'transition_action' => $actionName,
            'user_id' => $actorUserId,
            'user_group_id' => $actorUserGroupId,
            'remark' => $remark,
            'source_page' => $sourcePage,
            'related_token_scan_id' => $relatedRef,
            'platform' => $platform,
        ));

        $stepAResult = array();
        if ($targetStatus === 'TP') {
            $freshOrderRow = shopeeOmsLoadOrder($orderConnect, $orderId, $sourceConfig);
            $tokenResult = shopeeOmsCreateWarehouseToken($cmsConnect, $financeConnect, $freshOrderRow, $actorUserId, $sourceConfig);
            if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                $notifyResult = shopeeOmsSendWarehouseNotification($cmsConnect, $financeConnect, $tokenResult['token_row'], $tokenResult['notification'], $sourcePage);
                if (!empty($notifyResult['sent'])) {
                    if (shopeeOmsTableHasColumn($orderConnect, $tableDbName, $tblName, 'step_a_sent_at')) {
                        mysqli_query($orderConnect, "UPDATE `" . $tblName . "` SET `step_a_sent_at` = NOW() WHERE id = " . $orderId . " LIMIT 1");
                    }
                }
                $stepAResult = array(
                    'token_result' => $tokenResult,
                    'notify_result' => $notifyResult,
                );
            }
        }

        $autoTransitionResult = array();
        if ($allowAutoFollowUp && $targetStatus === 'SP') {
            $autoTransitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, $orderId, 'WAERD', array(
                'actor_user_id' => $actorUserId,
                'actor_user_group_id' => $actorUserGroupId,
                'source_page' => $sourcePage,
                'remark' => 'Auto move after warehouse scan.',
                'action' => 'auto_post_ship',
                'skip_permission' => true,
                'allow_auto_follow_up' => false,
                'related_token_scan_id' => $relatedRef,
                'platform' => $platform,
            ));
        }

        return array(
            'success' => true,
            'message' => 'Order status updated to ' . shopeeOmsGetStatusLabel($targetStatus) . '.',
            'old_status' => $fromStatus,
            'new_status' => $targetStatus,
            'step_a_result' => $stepAResult,
            'auto_transition' => $autoTransitionResult,
        );
    }
}

if (!function_exists('shopeeOmsAssignEstimatedReceivedDate')) {
    function shopeeOmsAssignEstimatedReceivedDate($connect, $tblName, $orderId, $date, $currentUserId)
    {
        $tblName = trim((string) $tblName);
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($tblName, 'shopee');
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';

        global $finance_connect;
        $orderDb = $connect instanceof mysqli ? $connect : $finance_connect;
        if (!($orderDb instanceof mysqli)) {
            return array(
                'success' => false,
                'message' => 'Unable to connect to OMS order table.',
            );
        }

        $orderRow = shopeeOmsLoadOrder($orderDb, $orderId, $sourceConfig);
        if (empty($orderRow)) {
            return array(
                'success' => false,
                'message' => 'Order not found.',
            );
        }

        $validation = validateEstimatedReceivedDate($date, $orderRow);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => $validation['message'],
            );
        }

        $currentStatus = shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '');
        if (!in_array($currentStatus, array('WAERD', 'WR'), true)) {
            return array(
                'success' => false,
                'message' => 'This order is not in a status that allows Estimated Received Date updates.',
            );
        }

        $safeCurrentUserId = substr(trim((string) $currentUserId), 0, 30);
        $fieldUpdates = array(
            'estimated_received_date' => $validation['normalized_date'],
            'estimated_received_date_assigned_by' => $safeCurrentUserId,
            'estimated_received_date_assigned_date' => date('Y-m-d'),
            'estimated_received_date_assigned_time' => date('H:i:s'),
        );

        if ($currentStatus === 'WAERD') {
            $result = shopeeOmsExecuteTransition($GLOBALS['connect'], $finance_connect, $orderId, 'WR', array(
                'actor_user_id' => $safeCurrentUserId,
                'actor_user_group_id' => defined('USER_GROUP') ? (int) USER_GROUP : 0,
                'source_page' => 'Arrival Management',
                'remark' => 'Estimated Received Date assigned: ' . $validation['normalized_date'],
                'action' => 'assign_estimated_received_date',
                'field_updates' => $fieldUpdates,
                'allow_auto_follow_up' => false,
                'platform' => $platform,
            ));
            if (!empty($result['success'])) {
                $result['date'] = $validation['normalized_date'];
            }
            return $result;
        }

        $safeUser = mysqli_real_escape_string($orderDb, $safeCurrentUserId);
        $assignments = array(
            "`estimated_received_date` = '" . mysqli_real_escape_string($orderDb, $validation['normalized_date']) . "'",
            "`estimated_received_date_assigned_by` = '" . $safeUser . "'",
            "`estimated_received_date_assigned_date` = CURDATE()",
            "`estimated_received_date_assigned_time` = CURTIME()",
            "`update_by` = '" . $safeUser . "'",
            "`update_date` = CURDATE()",
            "`update_time` = CURTIME()",
        );
        if (shopeeOmsTableHasColumn($orderDb, isset($sourceConfig['db_name']) ? $sourceConfig['db_name'] : dbFinance, isset($sourceConfig['table']) ? $sourceConfig['table'] : $tblName, 'latest_transition_at')) {
            $assignments[] = "`latest_transition_at` = NOW()";
        }
        $updateSql = "UPDATE `" . (isset($sourceConfig['table']) ? $sourceConfig['table'] : $tblName) . "`
            SET " . implode(",\n                ", $assignments) . "
            WHERE id = " . (int) $orderId . "
            LIMIT 1";
        if (!mysqli_query($orderDb, $updateSql)) {
            return array(
                'success' => false,
                'message' => 'Unable to update Estimated Received Date.',
            );
        }

        shopeeOmsLogTransition($finance_connect, array(
            'order_id' => (int) $orderId,
            'order_code' => shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig),
            'from_status' => $currentStatus,
            'to_status' => $currentStatus,
            'transition_action' => 'assign_estimated_received_date',
            'user_id' => $safeCurrentUserId,
            'user_group_id' => defined('USER_GROUP') ? (int) USER_GROUP : 0,
            'remark' => 'Estimated Received Date assigned: ' . $validation['normalized_date'],
            'source_page' => 'Arrival Management',
            'platform' => $platform,
        ));

        return array(
            'success' => true,
            'message' => 'Estimate Received Date updated successfully.',
            'date' => $validation['normalized_date'],
            'old_status' => $currentStatus,
            'new_status' => $currentStatus,
        );
    }
}

if (!function_exists('shopeeOmsMoveToWafcWithReceivedDate')) {
    function shopeeOmsMoveToWafcWithReceivedDate($cmsConnect, $financeConnect, $orderId, $date, $options = array())
    {
        $options = is_array($options) ? $options : array();
        $validation = validateReceivedDate($date);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => $validation['message'],
            );
        }

        $resolvedSource = isset($options['platform']) ? $options['platform'] : (isset($options['table_name']) ? $options['table_name'] : 'shopee');
        $sourceConfig = shopeeOmsResolveOrderSourceConfig($resolvedSource, 'shopee');
        $orderConnect = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
        $tblName = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ;
        $dbName = isset($sourceConfig['db_name']) ? (string) $sourceConfig['db_name'] : dbFinance;

        if (!shopeeOmsTableHasColumn($orderConnect, $dbName, $tblName, 'received_date')) {
            return array(
                'success' => false,
                'message' => 'Received Date column is not available yet. Please run insert_table.php first.',
            );
        }

        $orderRow = shopeeOmsLoadOrder($orderConnect, $orderId, $sourceConfig);
        if (empty($orderRow)) {
            return array(
                'success' => false,
                'message' => 'Order not found.',
            );
        }

        $currentStatus = shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '');
        if ($currentStatus !== 'PR') {
            return array(
                'success' => false,
                'message' => 'Only Parcel Received orders can be moved to Waiting Admin Final Check with a Received Date.',
            );
        }

        $fieldUpdates = isset($options['field_updates']) && is_array($options['field_updates']) ? $options['field_updates'] : array();
        $fieldUpdates['received_date'] = $validation['normalized_date'];
        $options['field_updates'] = $fieldUpdates;
        $options['remark'] = 'Moved to Waiting Admin Final Check with received date ' . $validation['normalized_date'] . '.';
        $options['skip_permission'] = !isset($options['skip_permission']) ? true : !empty($options['skip_permission']);
        $options['allow_auto_follow_up'] = false;
        $options['platform'] = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';

        $result = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, $orderId, 'WAFC', $options);
        if (!empty($result['success'])) {
            $result['date'] = $validation['normalized_date'];
        }

        return $result;
    }
}

if (!function_exists('shopeeOmsHandleMoveToWafcWithReceivedDatePost')) {
    function shopeeOmsHandleMoveToWafcWithReceivedDatePost($cmsConnect, $financeConnect, $options = array())
    {
        $options = is_array($options) ? $options : array();
        $buttonName = isset($options['button_name']) && trim((string) $options['button_name']) !== '' ? trim((string) $options['button_name']) : 'move_to_wafc_with_received_date_btn';
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST[$buttonName])) {
            return false;
        }

        $redirectUrl = isset($options['redirect_url']) && trim((string) $options['redirect_url']) !== ''
            ? (string) $options['redirect_url']
            : (string) ($_SERVER['REQUEST_URI'] ?? '');
        $csrfFieldName = isset($options['csrf_field_name']) && trim((string) $options['csrf_field_name']) !== '' ? trim((string) $options['csrf_field_name']) : 'csrf_token';
        $csrfSessionKey = isset($options['csrf_session_key']) && trim((string) $options['csrf_session_key']) !== '' ? trim((string) $options['csrf_session_key']) : 'csrf_token';
        $submittedToken = isset($_POST[$csrfFieldName]) ? (string) $_POST[$csrfFieldName] : '';
        $sessionToken = isset($_SESSION[$csrfSessionKey]) ? (string) $_SESSION[$csrfSessionKey] : '';

        if (!hash_equals($sessionToken, $submittedToken)) {
            renderNotificationScript('Invalid session token. Please refresh the page and try again.', 'error', $redirectUrl, 1200, true);
            exit;
        }

        $orderIdField = isset($options['order_id_field']) && trim((string) $options['order_id_field']) !== '' ? trim((string) $options['order_id_field']) : 'force_wafc_id';
        $dateField = isset($options['date_field']) && trim((string) $options['date_field']) !== '' ? trim((string) $options['date_field']) : 'received_date';
        $orderId = isset($_POST[$orderIdField]) ? (int) $_POST[$orderIdField] : 0;
        $receivedDate = function_exists('postSpaceFilter') ? postSpaceFilter($dateField) : (isset($_POST[$dateField]) ? trim((string) $_POST[$dateField]) : '');
        $actorUserId = isset($options['actor_user_id']) ? (string) $options['actor_user_id'] : (defined('USER_ID') ? (string) USER_ID : '');
        $actorUserGroupId = isset($options['actor_user_group_id']) ? (int) $options['actor_user_group_id'] : (defined('USER_GROUP') ? (int) USER_GROUP : 0);
        $sourcePage = isset($options['source_page']) ? (string) $options['source_page'] : 'Shopee OMS';
        $platform = isset($options['platform']) ? (string) $options['platform'] : 'shopee';

        $wafcResult = shopeeOmsMoveToWafcWithReceivedDate($cmsConnect, $financeConnect, $orderId, $receivedDate, array(
            'actor_user_id' => $actorUserId,
            'actor_user_group_id' => $actorUserGroupId,
            'source_page' => $sourcePage,
            'action' => 'manual_force_wafc',
            'skip_permission' => true,
            'platform' => $platform,
        ));

        if (!empty($wafcResult['success'])) {
            $oldStatus = isset($wafcResult['old_status']) ? (string) $wafcResult['old_status'] : 'PR';
            $newStatus = isset($wafcResult['new_status']) ? (string) $wafcResult['new_status'] : 'WAFC';
            $savedReceivedDate = isset($wafcResult['date']) ? (string) $wafcResult['date'] : (string) $receivedDate;
            $sourceConfig = shopeeOmsResolveOrderSourceConfig($platform, 'shopee');
            $queryTable = isset($options['query_table']) && trim((string) $options['query_table']) !== ''
                ? (string) $options['query_table']
                : (isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ);
            $auditConnect = isset($options['audit_connect']) && $options['audit_connect'] instanceof mysqli ? $options['audit_connect'] : $cmsConnect;
            $platformLabel = isset($sourceConfig['platform']) ? ucfirst((string) $sourceConfig['platform']) : 'Shopee';
            $actorUserName = defined('USER_NAME') ? (string) USER_NAME : 'System User';
            $logDate = isset($options['cdate']) ? (string) $options['cdate'] : (isset($GLOBALS['cdate']) ? (string) $GLOBALS['cdate'] : date('Y-m-d'));
            $logTime = isset($options['ctime']) ? (string) $options['ctime'] : (isset($GLOBALS['ctime']) ? (string) $GLOBALS['ctime'] : date('H:i:s'));

            audit_log(array(
                'log_act' => 'edit',
                'page' => $sourcePage,
                'query_rec' => $orderId,
                'query_table' => $queryTable,
                'oldval' => 'order_status: ' . $oldStatus,
                'changes' => 'order_status: ' . $oldStatus . ' -> ' . $newStatus . ', received_date: ' . $savedReceivedDate,
                'uid' => $actorUserId,
                'act_msg' => $actorUserName . " moved " . $platformLabel . " order [ <b>ID = " . $orderId . "</b> ] from <b>" . $oldStatus . "</b> to <b>" . $newStatus . "</b> with Received Date <b>" . $savedReceivedDate . "</b>.",
                'cdate' => $logDate,
                'ctime' => $logTime,
                'cby' => $actorUserId,
                'connect' => $auditConnect,
            ));
        }

        $wafcMessage = (string) (isset($wafcResult['message']) ? $wafcResult['message'] : 'Unable to move order to WAFC.');
        renderNotificationScript($wafcMessage, resolveNotificationType($wafcMessage, 'info'), $redirectUrl, 1200, true);
        exit;
    }
}

if (!function_exists('shopeeOmsRenderReceivedDateModal')) {
    function shopeeOmsRenderReceivedDateModal($config = array())
    {
        $config = is_array($config) ? $config : array();
        $modalId = isset($config['modal_id']) && trim((string) $config['modal_id']) !== '' ? trim((string) $config['modal_id']) : 'receivedDateModal';
        $titleId = isset($config['title_id']) && trim((string) $config['title_id']) !== '' ? trim((string) $config['title_id']) : 'receivedDateTitle';
        $orderIdInputId = isset($config['order_id_input_id']) && trim((string) $config['order_id_input_id']) !== '' ? trim((string) $config['order_id_input_id']) : 'received_date_order_id';
        $dateInputId = isset($config['date_input_id']) && trim((string) $config['date_input_id']) !== '' ? trim((string) $config['date_input_id']) : 'received_date';
        $formAction = isset($config['form_action']) ? (string) $config['form_action'] : '';
        $csrfToken = isset($config['csrf_token']) ? (string) $config['csrf_token'] : (isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '');
        $buttonName = isset($config['button_name']) && trim((string) $config['button_name']) !== '' ? trim((string) $config['button_name']) : 'move_to_wafc_with_received_date_btn';
        $csrfFieldName = isset($config['csrf_field_name']) && trim((string) $config['csrf_field_name']) !== '' ? trim((string) $config['csrf_field_name']) : 'csrf_token';
        $orderIdField = isset($config['order_id_field']) && trim((string) $config['order_id_field']) !== '' ? trim((string) $config['order_id_field']) : 'force_wafc_id';
        $dateField = isset($config['date_field']) && trim((string) $config['date_field']) !== '' ? trim((string) $config['date_field']) : 'received_date';
        $heading = isset($config['heading']) && trim((string) $config['heading']) !== '' ? trim((string) $config['heading']) : 'Assign Received Date';
        $fieldLabel = isset($config['field_label']) && trim((string) $config['field_label']) !== '' ? trim((string) $config['field_label']) : 'Received Date';
        $cancelLabel = isset($config['cancel_label']) && trim((string) $config['cancel_label']) !== '' ? trim((string) $config['cancel_label']) : 'Cancel';
        $submitLabel = isset($config['submit_label']) && trim((string) $config['submit_label']) !== '' ? trim((string) $config['submit_label']) : 'Save & Move to Waiting Admin Final Check';
        $wrapperClass = isset($config['wrapper_class']) && trim((string) $config['wrapper_class']) !== '' ? trim((string) $config['wrapper_class']) : 'estimated-date-modal';
        $dialogClass = isset($config['dialog_class']) && trim((string) $config['dialog_class']) !== '' ? trim((string) $config['dialog_class']) : 'estimated-date-modal__dialog';
        $actionButtonClass = isset($config['action_button_class']) && trim((string) $config['action_button_class']) !== '' ? trim((string) $config['action_button_class']) : 'estimated-date-modal__action-btn';
        $closeButtonClass = isset($config['close_button_class']) && trim((string) $config['close_button_class']) !== '' ? trim((string) $config['close_button_class']) : 'estimated-date-modal__close-btn';
        $wrapperAttributes = isset($config['wrapper_attributes']) && trim((string) $config['wrapper_attributes']) !== '' ? ' ' . trim((string) $config['wrapper_attributes']) : '';
        $extraHiddenFields = isset($config['extra_hidden_fields']) && is_array($config['extra_hidden_fields']) ? $config['extra_hidden_fields'] : array();
        ?>
        <div id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8') ?>" data-received-date-modal="1"<?= $wrapperAttributes ?>>
            <div class="<?= htmlspecialchars($dialogClass, ENT_QUOTES, 'UTF-8') ?>">
                <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h5 class="mb-0" id="<?= htmlspecialchars($titleId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h5>
                        <button type="button" class="btn btn-sm btn-light px-2 <?= htmlspecialchars($closeButtonClass, ENT_QUOTES, 'UTF-8') ?>" data-received-date-modal-close="1" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <input type="hidden" name="<?= htmlspecialchars($csrfFieldName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="<?= htmlspecialchars($buttonName, ENT_QUOTES, 'UTF-8') ?>" value="1">
                    <input type="hidden" name="<?= htmlspecialchars($orderIdField, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($orderIdInputId, ENT_QUOTES, 'UTF-8') ?>" value="">
                    <?php foreach ($extraHiddenFields as $fieldName => $fieldValue) { ?>
                        <input type="hidden" name="<?= htmlspecialchars((string) $fieldName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $fieldValue, ENT_QUOTES, 'UTF-8') ?>">
                    <?php } ?>
                    <div class="mb-3">
                        <label class="form-label" for="<?= htmlspecialchars($dateInputId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?></label>
                        <input type="date" class="form-control" name="<?= htmlspecialchars($dateField, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($dateInputId, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary <?= htmlspecialchars($actionButtonClass, ENT_QUOTES, 'UTF-8') ?>" data-received-date-modal-close="1"><?= htmlspecialchars($cancelLabel, ENT_QUOTES, 'UTF-8') ?></button>
                        <button type="submit" class="btn btn-primary <?= htmlspecialchars($actionButtonClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('shopeeOmsRenderReceivedDateModalScript')) {
    function shopeeOmsRenderReceivedDateModalScript($config = array())
    {
        $config = is_array($config) ? $config : array();
        $modalId = isset($config['modal_id']) && trim((string) $config['modal_id']) !== '' ? trim((string) $config['modal_id']) : 'receivedDateModal';
        $titleId = isset($config['title_id']) && trim((string) $config['title_id']) !== '' ? trim((string) $config['title_id']) : 'receivedDateTitle';
        $orderIdInputId = isset($config['order_id_input_id']) && trim((string) $config['order_id_input_id']) !== '' ? trim((string) $config['order_id_input_id']) : 'received_date_order_id';
        $dateInputId = isset($config['date_input_id']) && trim((string) $config['date_input_id']) !== '' ? trim((string) $config['date_input_id']) : 'received_date';
        $triggerSelector = isset($config['trigger_selector']) && trim((string) $config['trigger_selector']) !== '' ? trim((string) $config['trigger_selector']) : '.btn-open-received-date-modal';
        $titlePrefix = isset($config['title_prefix']) ? (string) $config['title_prefix'] : 'Assign Received Date for ';
        $defaultTitle = isset($config['default_title']) ? (string) $config['default_title'] : 'Assign Received Date';
        ?>
        <script>
            (function () {
                var modalId = <?= json_encode($modalId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var titleId = <?= json_encode($titleId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var orderIdInputId = <?= json_encode($orderIdInputId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var dateInputId = <?= json_encode($dateInputId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var triggerSelector = <?= json_encode($triggerSelector, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var titlePrefix = <?= json_encode($titlePrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                var defaultTitle = <?= json_encode($defaultTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

                function getModal() {
                    return document.getElementById(modalId);
                }

                function openModal(orderId, orderCode) {
                    var modal = getModal();
                    var title = document.getElementById(titleId);
                    var orderIdInput = document.getElementById(orderIdInputId);
                    var dateInput = document.getElementById(dateInputId);
                    if (!modal || !title || !orderIdInput || !dateInput) {
                        return;
                    }

                    title.textContent = orderCode ? (titlePrefix + orderCode) : defaultTitle;
                    orderIdInput.value = orderId || '';
                    dateInput.value = '';
                    modal.classList.add('is-open');
                }

                function closeModal() {
                    var modal = getModal();
                    if (modal) {
                        modal.classList.remove('is-open');
                    }
                }

                document.addEventListener('click', function (event) {
                    var trigger = event.target.closest(triggerSelector);
                    if (trigger) {
                        openModal(trigger.getAttribute('data-order-id') || '', trigger.getAttribute('data-order-code') || '');
                        return;
                    }

                    var modal = getModal();
                    if (!modal) {
                        return;
                    }

                    if (event.target === modal) {
                        closeModal();
                        return;
                    }

                    var closeBtn = event.target.closest('[data-received-date-modal-close="1"]');
                    if (closeBtn && modal.contains(closeBtn)) {
                        closeModal();
                    }
                });
            })();
        </script>
        <?php
    }
}

if (!function_exists('shopeeOmsResolveIdCsvToNames')) {
    function shopeeOmsResolveIdCsvToNames($connect, $tblName, $csvValue)
    {
        $csvValue = trim((string) $csvValue);
        if ($csvValue === '' || !($connect instanceof mysqli)) {
            return '';
        }

        $idMap = array();
        foreach (explode(',', $csvValue) as $idRaw) {
            $idValue = (int) trim((string) $idRaw);
            if ($idValue > 0) {
                $idMap[$idValue] = $idValue;
            }
        }
        if (empty($idMap)) {
            return $csvValue;
        }

        $result = mysqli_query($connect, "SELECT id, name FROM `" . $tblName . "` WHERE id IN (" . implode(',', $idMap) . ")");
        $nameMap = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rowId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($rowId > 0) {
                    $nameMap[$rowId] = isset($row['name']) ? (string) $row['name'] : '';
                }
            }
        }

        $names = array();
        foreach ($idMap as $idValue) {
            $names[] = isset($nameMap[$idValue]) && trim((string) $nameMap[$idValue]) !== '' ? $nameMap[$idValue] : ('#' . $idValue);
        }

        return implode(', ', $names);
    }
}

if (!function_exists('shopeeOmsGetImportantEditableFields')) {
    function shopeeOmsGetImportantEditableFields()
    {
        return array(
            'orderID' => 'Order ID',
            'customer_name' => 'Customer Name',
            'customer_phone' => 'Customer Phone',
            'customer_address' => 'Customer Address',
            'package' => 'Package',
            'package_qty_json' => 'Package Details',
            'brand' => 'Brand',
            'buyer' => 'Buyer Username',
            'buyer_pay_meth' => 'Buyer Payment Method',
            'price' => 'Product Price',
            'stock_out_warehouse_id' => 'Stock Out Warehouse',
            'airbill_no' => 'Airbill',
            'airbill_attachment' => 'Airbill Attachment',
            'remark' => 'Remark',
            'estimated_received_date' => 'Estimated Received Date',
            'delay_remark' => 'Delay Remark',
            'order_status' => 'Order Status',
        );
    }
}

if (!function_exists('shopeeOmsBuildOrderFieldDisplayValue')) {
    function shopeeOmsBuildOrderFieldDisplayValue($connect, $fieldName, $fieldValue)
    {
        $fieldValue = is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : json_encode($fieldValue);

        if ($fieldName === 'order_status') {
            $fieldValue = trim((string) $fieldValue);
            if ($fieldValue === '') {
                return '';
            }
            return shopeeOmsGetStatusLabel($fieldValue);
        }

        if ($fieldName === 'package') {
            $fieldValue = trim((string) $fieldValue);
            if ($fieldValue === '') {
                return '';
            }
            return shopeeOmsResolveIdCsvToNames($connect, PKG, $fieldValue);
        }

        if ($fieldName === 'brand') {
            $fieldValue = trim((string) $fieldValue);
            if ($fieldValue === '') {
                return '';
            }
            return shopeeOmsResolveIdCsvToNames($connect, BRAND, $fieldValue);
        }

        if ($fieldName === 'stock_out_warehouse_id') {
            return shopeeOmsResolveWarehouseNameById($connect, $fieldValue, shopeeOmsGetDefaultWarehouseId($connect));
        }

        $fieldValue = trim((string) $fieldValue);
        if ($fieldValue === '') {
            return '';
        }

        if ($fieldName === 'package_qty_json') {
            $rows = shopeeOmsDecodePackageQtySnapshot($fieldValue);
            $parts = array();
            foreach ($rows as $row) {
                $packageName = trim((string) (isset($row['package_name']) ? $row['package_name'] : ''));
                if ($packageName === '' && !empty($row['package_id'])) {
                    $packageName = shopeeOmsResolveIdCsvToNames($connect, PKG, (string) $row['package_id']);
                }
                $qty = isset($row['qty']) ? (int) $row['qty'] : 1;
                $parts[] = ($packageName !== '' ? $packageName : ('Package #' . (int) $row['package_id'])) . ' x' . $qty;
            }
            return implode(', ', $parts);
        }

        return $fieldValue;
    }
}

if (!function_exists('shopeeOmsDetectOrderChanges')) {
    function shopeeOmsDetectOrderChanges($connect, $oldRow, $newValues)
    {
        $changes = array();
        $importantFields = shopeeOmsGetImportantEditableFields();
        foreach ($importantFields as $fieldName => $fieldLabel) {
            $oldValueRaw = isset($oldRow[$fieldName]) ? $oldRow[$fieldName] : '';
            $newValueRaw = isset($newValues[$fieldName]) ? $newValues[$fieldName] : $oldValueRaw;

            if ((string) $oldValueRaw === (string) $newValueRaw) {
                continue;
            }

            $changes[] = array(
                'field_name' => $fieldName,
                'field_label' => $fieldLabel,
                'old_value' => shopeeOmsBuildOrderFieldDisplayValue($connect, $fieldName, $oldValueRaw),
                'new_value' => shopeeOmsBuildOrderFieldDisplayValue($connect, $fieldName, $newValueRaw),
            );
        }

        return $changes;
    }
}

if (!function_exists('shopeeOmsLogOrderEditHistory')) {
    function shopeeOmsLogOrderEditHistory($financeConnect, $orderId, $orderCode, $changes, $userId, $userGroupId, $sourcePage = 'Shopee Order Request', $platform = 'shopee')
    {
        if (!($financeConnect instanceof mysqli) || empty($changes) || !is_array($changes)) {
            return false;
        }

        $safeUserId = mysqli_real_escape_string($financeConnect, trim((string) $userId) !== '' ? trim((string) $userId) : 'SYSTEM');
        $safeOrderCode = mysqli_real_escape_string($financeConnect, (string) $orderCode);
        $safeSourcePage = mysqli_real_escape_string($financeConnect, (string) $sourcePage);
        $tableHasPlatform = shopeeOmsTableHasColumn($financeConnect, dbFinance, ORDER_EDIT_HISTORY, 'platform');
        $safePlatform = mysqli_real_escape_string($financeConnect, shopeeOmsNormalizePlatformKey($platform) ?: 'shopee');
        foreach ($changes as $changeRow) {
            $safeFieldName = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['field_name']) ? $changeRow['field_name'] : ''));
            $safeFieldLabel = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['field_label']) ? $changeRow['field_label'] : ''));
            $safeOldValue = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['old_value']) ? $changeRow['old_value'] : ''));
            $safeNewValue = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['new_value']) ? $changeRow['new_value'] : ''));
            $insertColumns = array('order_id', 'order_code', 'field_name', 'field_label', 'old_value', 'new_value', 'user_id', 'user_group_id', 'source_page');
            $insertValues = array((string) ((int) $orderId), "'" . $safeOrderCode . "'", "'" . $safeFieldName . "'", "'" . $safeFieldLabel . "'", "'" . $safeOldValue . "'", "'" . $safeNewValue . "'", "'" . $safeUserId . "'", (string) ((int) $userGroupId), "'" . $safeSourcePage . "'");
            if ($tableHasPlatform) {
                $insertColumns[] = 'platform';
                $insertValues[] = "'" . $safePlatform . "'";
            }
            $insertColumns = array_merge($insertColumns, array('change_at', 'create_date', 'create_time', 'status'));
            $insertValues = array_merge($insertValues, array('NOW()', 'CURDATE()', 'CURTIME()', "'A'"));
            $sql = "INSERT INTO `" . ORDER_EDIT_HISTORY . "`
                (`" . implode('`, `', $insertColumns) . "`)
                VALUES
                (" . implode(', ', $insertValues) . ")";
            mysqli_query($financeConnect, $sql);
        }

        return true;
    }
}

if (!function_exists('shopeeOmsFetchEditHistory')) {
    function shopeeOmsFetchEditHistory($financeConnect, $orderId, $platform = 'shopee')
    {
        $rows = array();
        if (!($financeConnect instanceof mysqli) || (int) $orderId <= 0) {
            return $rows;
        }

        $conditions = array(
            "order_id = " . (int) $orderId,
            "status = 'A'",
        );
        if (shopeeOmsTableHasColumn($financeConnect, dbFinance, ORDER_EDIT_HISTORY, 'platform')) {
            $safePlatform = mysqli_real_escape_string($financeConnect, shopeeOmsNormalizePlatformKey($platform) ?: 'shopee');
            if ($safePlatform === 'shopee') {
                $conditions[] = "(platform = '" . $safePlatform . "' OR platform = '' OR platform IS NULL)";
            } else {
                $conditions[] = "platform = '" . $safePlatform . "'";
            }
        }
        $sql = "SELECT * FROM `" . ORDER_EDIT_HISTORY . "` WHERE " . implode(' AND ', $conditions) . " ORDER BY change_at DESC, id DESC";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('shopeeOmsFetchTransitionHistory')) {
    function shopeeOmsFetchTransitionHistory($financeConnect, $orderId, $platform = 'shopee')
    {
        $rows = array();
        if (!($financeConnect instanceof mysqli) || (int) $orderId <= 0) {
            return $rows;
        }

        $conditions = array(
            "order_id = " . (int) $orderId,
            "status = 'A'",
        );
        if (shopeeOmsTableHasColumn($financeConnect, dbFinance, ORDER_STATUS_TRANSITION_LOG, 'platform')) {
            $safePlatform = mysqli_real_escape_string($financeConnect, shopeeOmsNormalizePlatformKey($platform) ?: 'shopee');
            if ($safePlatform === 'shopee') {
                $conditions[] = "(platform = '" . $safePlatform . "' OR platform = '' OR platform IS NULL)";
            } else {
                $conditions[] = "platform = '" . $safePlatform . "'";
            }
        }
        $sql = "SELECT * FROM `" . ORDER_STATUS_TRANSITION_LOG . "` WHERE " . implode(' AND ', $conditions) . " ORDER BY transition_at DESC, id DESC";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('shopeeOmsGetUserGroupMeta')) {
    function shopeeOmsGetUserGroupMeta($connect, $userGroupId)
    {
        $userGroupId = (int) $userGroupId;
        if ($userGroupId <= 0 || !($connect instanceof mysqli)) {
            return array(
                'name' => 'User Group',
                'badge_color' => '#6c757d',
                'badge_icon_class' => 'fa-solid fa-user-group',
            );
        }

        $result = getData('id, name, badge_color, badge_icon_class', "id = '" . $userGroupId . "'", 'LIMIT 1', USR_GRP, $connect);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return array(
                'name' => isset($row['name']) ? (string) $row['name'] : 'User Group',
                'badge_color' => isset($row['badge_color']) && trim((string) $row['badge_color']) !== '' ? (string) $row['badge_color'] : '#6c757d',
                'badge_icon_class' => isset($row['badge_icon_class']) && trim((string) $row['badge_icon_class']) !== '' ? (string) $row['badge_icon_class'] : 'fa-solid fa-user-group',
            );
        }

        return array(
            'name' => 'User Group',
            'badge_color' => '#6c757d',
            'badge_icon_class' => 'fa-solid fa-user-group',
        );
    }
}

if (!function_exists('shopeeOmsRenderUserGroupBadge')) {
    function shopeeOmsRenderUserGroupBadge($connect, $userGroupId)
    {
        $groupMeta = shopeeOmsGetUserGroupMeta($connect, $userGroupId);
        $safeColor = htmlspecialchars((string) $groupMeta['badge_color'], ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars((string) $groupMeta['name'], ENT_QUOTES, 'UTF-8');
        $safeIcon = htmlspecialchars((string) $groupMeta['badge_icon_class'], ENT_QUOTES, 'UTF-8');

        return '<span class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill text-white" style="background-color:' . $safeColor . ';font-size:12px;"><i class="' . $safeIcon . '"></i><span>' . $safeName . '</span></span>';
    }
}

if (!function_exists('shopeeOmsGetDailyFlowReport')) {
    function shopeeOmsGetDailyFlowReport($cmsConnect, $financeConnect, $dateFrom, $dateTo, $fromStatus = '', $toStatus = '', $orderCode = '', $warehouseId = 0, $platform = '', $exactDate = '', $monthFilter = '', $yearFilter = '', $actorUserId = 0)
    {
        $summary = array();
        $details = array();
        if (!($financeConnect instanceof mysqli)) {
            return array('summary' => $summary, 'details' => $details);
        }

        $dateFrom = trim((string) $dateFrom);
        $dateTo = trim((string) $dateTo);
        $exactDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $exactDate)) ? trim((string) $exactDate) : '';
        $monthFilter = preg_match('/^(0[1-9]|1[0-2])$/', trim((string) $monthFilter)) ? trim((string) $monthFilter) : '';
        $yearFilter = preg_match('/^\d{4}$/', trim((string) $yearFilter)) ? trim((string) $yearFilter) : '';

        $platform = shopeeOmsNormalizePlatformKey($platform);
        $warehouseId = shopeeOmsNormalizeWarehouseId($warehouseId);
        $actorUserId = (int) $actorUserId;
        $defaultWarehouseId = $cmsConnect instanceof mysqli ? shopeeOmsGetDefaultWarehouseId($cmsConnect) : 0;
        foreach (shopeeOmsGetOrderSourceConfigs() as $sourcePlatform => $sourceConfig) {
            if ($platform !== '' && $platform !== $sourcePlatform) {
                continue;
            }

            $qualifiedOrderTable = shopeeOmsBuildQualifiedTableName($sourceConfig);
            if ($qualifiedOrderTable === '') {
                continue;
            }

            $conditions = array();
            $conditions[] = "l.status = 'A'";
            if ($actorUserId > 0) {
                $conditions[] = "l.user_id = " . $actorUserId;
            }
            if ($exactDate !== '') {
                $conditions[] = "DATE(l.transition_at) = '" . mysqli_real_escape_string($financeConnect, $exactDate) . "'";
            } else {
                if ($dateFrom !== '') {
                    $conditions[] = "DATE(l.transition_at) >= '" . mysqli_real_escape_string($financeConnect, $dateFrom) . "'";
                }
                if ($dateTo !== '') {
                    $conditions[] = "DATE(l.transition_at) <= '" . mysqli_real_escape_string($financeConnect, $dateTo) . "'";
                }
                if ($monthFilter !== '') {
                    $conditions[] = "MONTH(DATE(l.transition_at)) = '" . mysqli_real_escape_string($financeConnect, $monthFilter) . "'";
                }
                if ($yearFilter !== '') {
                    $conditions[] = "YEAR(DATE(l.transition_at)) = '" . mysqli_real_escape_string($financeConnect, $yearFilter) . "'";
                }
            }
            $platformCondition = shopeeOmsBuildLogPlatformCondition($financeConnect, $sourcePlatform, 'l');
            if ($platformCondition !== '') {
                $conditions[] = $platformCondition;
            }
            if (trim((string) $fromStatus) !== '') {
                $conditions[] = "l.from_status = '" . mysqli_real_escape_string($financeConnect, shopeeOmsNormalizeStatusCode($fromStatus)) . "'";
            }
            if (trim((string) $toStatus) !== '') {
                $conditions[] = "l.to_status = '" . mysqli_real_escape_string($financeConnect, shopeeOmsNormalizeStatusCode($toStatus)) . "'";
            }
            if (trim((string) $orderCode) !== '') {
                $safeOrderCode = mysqli_real_escape_string($financeConnect, trim((string) $orderCode));
                $conditions[] = "l.order_code LIKE '%" . $safeOrderCode . "%'";
            }
            if ($warehouseId > 0) {
                $warehouseField = isset($sourceConfig['warehouse_field']) && trim((string) $sourceConfig['warehouse_field']) !== ''
                    ? trim((string) $sourceConfig['warehouse_field'])
                    : 'stock_out_warehouse_id';
                $conditions[] = "COALESCE(NULLIF(o.`" . $warehouseField . "`, 0), " . (int) $defaultWarehouseId . ") = " . $warehouseId;
            }

            $whereSql = implode(' AND ', $conditions);
            $logFromSql = "`" . ORDER_STATUS_TRANSITION_LOG . "` l
                LEFT JOIN " . $qualifiedOrderTable . " o ON o.id = l.order_id";
            $summarySql = "SELECT l.from_status, l.to_status, COUNT(*) AS total_count, MAX(l.transition_at) AS last_transition_time
                FROM " . $logFromSql . "
                WHERE " . $whereSql . "
                GROUP BY l.from_status, l.to_status
                ORDER BY total_count DESC, l.from_status ASC, l.to_status ASC";
            $summaryResult = mysqli_query($financeConnect, $summarySql);
            if ($summaryResult) {
                while ($row = mysqli_fetch_assoc($summaryResult)) {
                    $transitionKey = $sourcePlatform . '__' . shopeeOmsBuildTransitionKey(isset($row['from_status']) ? $row['from_status'] : '', isset($row['to_status']) ? $row['to_status'] : '');
                    $summary[] = array(
                        'transition_key' => $transitionKey,
                        'platform' => $sourcePlatform,
                        'platform_label' => isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($sourcePlatform),
                        'from_status' => isset($row['from_status']) ? (string) $row['from_status'] : '',
                        'to_status' => isset($row['to_status']) ? (string) $row['to_status'] : '',
                        'from_label' => shopeeOmsGetStatusLabel(isset($row['from_status']) ? $row['from_status'] : ''),
                        'to_label' => shopeeOmsGetStatusLabel(isset($row['to_status']) ? $row['to_status'] : ''),
                        'total_count' => isset($row['total_count']) ? (int) $row['total_count'] : 0,
                        'last_transition_time' => isset($row['last_transition_time']) ? (string) $row['last_transition_time'] : '',
                    );
                }
            }

            $detailSql = "SELECT l.id, l.order_id, l.order_code, l.from_status, l.to_status, l.transition_action, l.user_id, l.user_group_id, l.transition_at, l.remark
                FROM " . $logFromSql . "
                WHERE " . $whereSql . "
                ORDER BY l.transition_at DESC, l.id DESC";
            $detailResult = mysqli_query($financeConnect, $detailSql);
            if ($detailResult) {
                while ($row = mysqli_fetch_assoc($detailResult)) {
                    $transitionKey = $sourcePlatform . '__' . shopeeOmsBuildTransitionKey(isset($row['from_status']) ? $row['from_status'] : '', isset($row['to_status']) ? $row['to_status'] : '');
                    if (!isset($details[$transitionKey])) {
                        $details[$transitionKey] = array();
                    }
                    $row['platform'] = $sourcePlatform;
                    $row['platform_label'] = isset($sourceConfig['label']) ? (string) $sourceConfig['label'] : ucfirst($sourcePlatform);
                    $row['order_view_url'] = shopeeOmsGetOrderSourceViewUrl($sourceConfig, isset($row['order_id']) ? (int) $row['order_id'] : 0);
                    $details[$transitionKey][] = $row;
                }
            }
        }

        usort($summary, function ($a, $b) {
            $countDiff = (int) ($b['total_count'] ?? 0) <=> (int) ($a['total_count'] ?? 0);
            if ($countDiff !== 0) {
                return $countDiff;
            }

            $platformCompare = strcmp((string) ($a['platform_label'] ?? ''), (string) ($b['platform_label'] ?? ''));
            if ($platformCompare !== 0) {
                return $platformCompare;
            }

            return strcmp((string) ($a['transition_key'] ?? ''), (string) ($b['transition_key'] ?? ''));
        });

        return array(
            'summary' => $summary,
            'details' => $details,
        );
    }
}

if (!function_exists('shopeeOmsRunOverduePostponedAutoMove')) {
    function shopeeOmsRunOverduePostponedAutoMove($cmsConnect, $financeConnect, $platform = '')
    {
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli)) {
            return 0;
        }

        $movedCount = 0;
        $todayYmd = date('Y-m-d');
        $platform = shopeeOmsNormalizePlatformKey($platform);
        foreach (shopeeOmsGetOrderSourceConfigs() as $sourcePlatform => $sourceConfig) {
            if ($platform !== '' && $platform !== $sourcePlatform) {
                continue;
            }

            $orderConnect = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
            if (!($orderConnect instanceof mysqli)) {
                continue;
            }

            $tblName = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : '';
            $delayRemarkField = isset($sourceConfig['delay_remark_field']) && trim((string) $sourceConfig['delay_remark_field']) !== ''
                ? trim((string) $sourceConfig['delay_remark_field'])
                : 'delay_remark';
            $statusCondition = shopeeOmsBuildOrderStatusInCondition($orderConnect, 'order_status', array('WR'));
            if ($tblName === '' || $statusCondition === '') {
                continue;
            }

            $selectFields = array('id', 'estimated_received_date');
            if (shopeeOmsTableHasColumn($orderConnect, isset($sourceConfig['db_name']) ? $sourceConfig['db_name'] : dbFinance, $tblName, $delayRemarkField)) {
                $selectFields[] = $delayRemarkField;
            }

            $sql = "SELECT " . implode(', ', $selectFields) . "
                FROM `" . $tblName . "`
                WHERE status = 'A'
                  AND " . $statusCondition;
            $result = mysqli_query($orderConnect, $sql);
            if (!$result) {
                continue;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $row = shopeeOmsAttachOrderSourceMeta((array) $row, $sourcePlatform, $sourceConfig);
                $estimatedReceivedDate = trim((string) (isset($row['estimated_received_date']) ? $row['estimated_received_date'] : ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $estimatedReceivedDate)) {
                    continue;
                }
                if ($estimatedReceivedDate >= $todayYmd) {
                    continue;
                }

                $delayRemark = trim((string) (isset($row[$delayRemarkField]) ? $row[$delayRemarkField] : ''));
                if ($delayRemark === '') {
                    $delayRemark = 'Auto postponed: estimated received date passed without Confirm Received action.';
                }

                $transitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, (int) $row['id'], 'PD', array(
                    'actor_user_id' => 'SYSTEM',
                    'actor_user_group_id' => 1,
                    'source_page' => 'OMS Housekeeping',
                    'remark' => 'Auto move after estimate received date passed without Confirm Received.',
                    'action' => 'auto_postpone_overdue',
                    'skip_permission' => true,
                    'allow_auto_follow_up' => false,
                    'field_updates' => array(
                        $delayRemarkField => $delayRemark,
                    ),
                    'platform' => $sourcePlatform,
                ));
                if (!empty($transitionResult['success'])) {
                    $movedCount++;
                }
            }
        }

        return $movedCount;
    }
}

if (!function_exists('shopeeOmsEnsureRealtimePostponedSync')) {
    function shopeeOmsEnsureRealtimePostponedSync($cmsConnect, $financeConnect)
    {
        static $hasRun = false;
        if ($hasRun) {
            return 0;
        }

        $hasRun = true;
        return shopeeOmsRunOverduePostponedAutoMove($cmsConnect, $financeConnect);
    }
}

if (!function_exists('shopeeOmsRunFourteenDayAutoMove')) {
    function shopeeOmsRunFourteenDayAutoMove($cmsConnect, $financeConnect, $platform = '')
    {
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli)) {
            return 0;
        }

        $movedCount = 0;
        $platform = shopeeOmsNormalizePlatformKey($platform);
        foreach (shopeeOmsGetOrderSourceConfigs() as $sourcePlatform => $sourceConfig) {
            if ($platform !== '' && $platform !== $sourcePlatform) {
                continue;
            }

            $qualifiedOrderTable = shopeeOmsBuildQualifiedTableName($sourceConfig);
            if ($qualifiedOrderTable === '') {
                continue;
            }

            $platformCondition = shopeeOmsBuildLogPlatformCondition($financeConnect, $sourcePlatform, 'l');
            $transitionWhere = "l.status = 'A' AND l.to_status IN ('PR', 'Parcel Received')";
            if ($platformCondition !== '') {
                $transitionWhere .= " AND " . $platformCondition;
            }

            $latestTransitionExprParts = array("t.last_transition_at");
            if (shopeeOmsSourceHasColumn($cmsConnect, $financeConnect, $sourceConfig, 'latest_transition_at')) {
                $latestTransitionExprParts[] = "o.latest_transition_at";
            }
            $latestTransitionExprParts[] = "CONCAT(o.update_date, ' ', o.update_time)";
            $latestTransitionExprParts[] = "CONCAT(o.create_date, ' ', o.create_time)";
            $latestTransitionExpr = "COALESCE(" . implode(', ', $latestTransitionExprParts) . ")";
            $statusCondition = shopeeOmsBuildOrderStatusInCondition($financeConnect, 'order_status', array('PR'));
            if ($statusCondition !== '') {
                $statusCondition = preg_replace('/\border_status\b/', 'o.order_status', $statusCondition);
            }
            if ($statusCondition === '') {
                continue;
            }
            $sql = "SELECT o.id
                FROM " . $qualifiedOrderTable . " o
                LEFT JOIN (
                    SELECT l.order_id, MAX(l.transition_at) AS last_transition_at
                    FROM `" . ORDER_STATUS_TRANSITION_LOG . "` l
                    WHERE " . $transitionWhere . "
                    GROUP BY l.order_id
                ) t ON t.order_id = o.id
                WHERE o.status = 'A'
                  AND " . $statusCondition . "
                  AND " . $latestTransitionExpr . " <= DATE_SUB(NOW(), INTERVAL 14 DAY)";
            $result = mysqli_query($financeConnect, $sql);
            if (!$result) {
                continue;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $transitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, (int) $row['id'], 'WAFC', array(
                    'actor_user_id' => 'SYSTEM',
                    'actor_user_group_id' => 1,
                    'source_page' => 'OMS Housekeeping',
                    'remark' => 'Auto move after 14 days in Parcel Received.',
                    'action' => 'auto_14_day_final_check',
                    'skip_permission' => true,
                    'allow_auto_follow_up' => false,
                    'platform' => $sourcePlatform,
                ));
                if (!empty($transitionResult['success'])) {
                    $movedCount++;
                }
            }
        }

        return $movedCount;
    }
}

if (!function_exists('shopeeOmsBuildWarehouseStockShortageMessage')) {
    function shopeeOmsBuildWarehouseStockShortageMessage($warehouseName, $shortages)
    {
        $shortages = is_array($shortages) ? $shortages : array();
        if (empty($shortages)) {
            return '';
        }

        $warehouseName = trim((string) $warehouseName);
        $warehouseSubject = $warehouseName !== ''
            ? 'Selected warehouse "' . $warehouseName . '"'
            : 'Selected warehouse';

        $parts = array();
        foreach ($shortages as $shortage) {
            if (!is_array($shortage)) {
                continue;
            }

            $productLabel = trim((string) (isset($shortage['product_label']) ? $shortage['product_label'] : ''));
            $productId = isset($shortage['product_id']) ? (int) $shortage['product_id'] : 0;
            if ($productLabel === '') {
                $productLabel = $productId > 0 ? ('Product #' . $productId) : 'this product';
            }

            $requiredQty = isset($shortage['required_qty']) ? (int) $shortage['required_qty'] : 0;
            $availableQty = isset($shortage['available_qty']) ? (int) $shortage['available_qty'] : 0;
            if ($availableQty < 0) {
                $availableQty = 0;
            }

            $parts[] = $productLabel . ' (required: ' . $requiredQty . ', available: ' . $availableQty . ')';
        }

        if (empty($parts)) {
            return $warehouseSubject . ' does not have enough stock.';
        }

        return $warehouseSubject . ' does not have enough stock for ' . implode(', ', $parts) . '.';
    }
}

if (!function_exists('shopeeOmsValidateWarehouseStockForOrder')) {
    function shopeeOmsValidateWarehouseStockForOrder($cmsConnect, $financeConnect, $orderRow, $options = array())
    {
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli) || !is_array($orderRow)) {
            return array('success' => false, 'message' => 'Unable to connect to warehouse inventory.');
        }

        $options = is_array($options) ? $options : array();
        $resolvedSource = isset($options['platform']) ? $options['platform'] : shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee');
        if (!empty($orderRow['__oms_platform'])) {
            $resolvedSource = $orderRow['__oms_platform'];
        }

        $sourceConfig = shopeeOmsResolveOrderSourceConfig($resolvedSource, 'shopee');
        $productSummary = shopeeOmsBuildOrderProductSummaryBySource($cmsConnect, $orderRow, $sourceConfig);
        $productQtyMap = isset($productSummary['product_qty_map']) && is_array($productSummary['product_qty_map'])
            ? $productSummary['product_qty_map']
            : array();
        if (empty($productQtyMap)) {
            return array('success' => false, 'message' => 'No product item found for this order package.');
        }

        $defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($cmsConnect);
        $warehouseId = isset($options['warehouse_id'])
            ? shopeeOmsNormalizeWarehouseId($options['warehouse_id'])
            : shopeeOmsResolveStockOutWarehouseId($cmsConnect, $orderRow, $defaultWarehouseId);
        if ($warehouseId <= 0) {
            return array('success' => false, 'message' => 'Stock Out Warehouse cannot be empty.');
        }

        $warehouseName = shopeeOmsResolveWarehouseNameById($cmsConnect, $warehouseId, $defaultWarehouseId);
        $productNameMap = array();
        $productIds = array();
        foreach (array_keys($productQtyMap) as $productId) {
            $productId = (int) $productId;
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }

        if (!empty($productIds)) {
            $productResult = mysqli_query($cmsConnect, "SELECT id, name FROM `" . PROD . "` WHERE id IN (" . implode(',', $productIds) . ")");
            if ($productResult) {
                while ($productRow = mysqli_fetch_assoc($productResult)) {
                    $resolvedProductId = isset($productRow['id']) ? (int) $productRow['id'] : 0;
                    if ($resolvedProductId > 0) {
                        $productNameMap[$resolvedProductId] = isset($productRow['name']) ? trim((string) $productRow['name']) : '';
                    }
                }
            }
        }

        $shortages = array();
        foreach ($productQtyMap as $productId => $requiredQty) {
            $productId = (int) $productId;
            $requiredQty = (int) $requiredQty;
            if ($productId <= 0 || $requiredQty <= 0) {
                continue;
            }

            $availableQty = 0;
            $batches = siGetAvailableFifoStockInBatches($financeConnect, $warehouseId, $productId, 0, 0);
            foreach ($batches as $batch) {
                $availableQty += isset($batch['available_quantity']) ? (int) $batch['available_quantity'] : 0;
            }

            if ($availableQty < $requiredQty) {
                $shortages[] = array(
                    'product_id' => $productId,
                    'product_label' => isset($productNameMap[$productId]) && $productNameMap[$productId] !== ''
                        ? $productNameMap[$productId]
                        : ('Product #' . $productId),
                    'required_qty' => $requiredQty,
                    'available_qty' => $availableQty,
                );
            }
        }

        if (!empty($shortages)) {
            return array(
                'success' => false,
                'message' => shopeeOmsBuildWarehouseStockShortageMessage($warehouseName, $shortages),
                'shortages' => $shortages,
                'warehouse_id' => $warehouseId,
                'warehouse_name' => $warehouseName,
            );
        }

        return array(
            'success' => true,
            'message' => '',
            'shortages' => array(),
            'warehouse_id' => $warehouseId,
            'warehouse_name' => $warehouseName,
        );
    }
}

if (!function_exists('shopeeOmsDeductInventoryForOrder')) {
    function shopeeOmsDeductInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId = 'SYSTEM', $scanReference = '', $attachments = array())
    {
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli) || !is_array($orderRow)) {
            return array('success' => false, 'message' => 'Unable to connect to warehouse inventory.');
        }

        $sourceConfig = shopeeOmsResolveOrderSourceConfig(shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $productSummary = shopeeOmsBuildOrderProductSummaryBySource($cmsConnect, $orderRow, $sourceConfig);
        $productQtyMap = isset($productSummary['product_qty_map']) && is_array($productSummary['product_qty_map']) ? $productSummary['product_qty_map'] : array();
        if (empty($productQtyMap)) {
            return array('success' => false, 'message' => 'No product item found for this order package.');
        }

        $defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($cmsConnect);
        $warehouseId = shopeeOmsResolveStockOutWarehouseId($cmsConnect, $orderRow, $defaultWarehouseId);
        $warehouseName = shopeeOmsResolveWarehouseNameById($cmsConnect, $warehouseId, $defaultWarehouseId);
        $orderCode = shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig);
        $attachmentValue = siAttachmentEncodeList($attachments);

        $productNameMap = array();
        $productIds = array();
        foreach (array_keys($productQtyMap) as $productId) {
            $productId = (int) $productId;
            if ($productId > 0) {
                $productIds[$productId] = $productId;
            }
        }
        if (!empty($productIds)) {
            $productResult = mysqli_query($cmsConnect, "SELECT id, name FROM `" . PROD . "` WHERE id IN (" . implode(',', $productIds) . ")");
            if ($productResult) {
                while ($productRow = mysqli_fetch_assoc($productResult)) {
                    $resolvedProductId = isset($productRow['id']) ? (int) $productRow['id'] : 0;
                    if ($resolvedProductId > 0) {
                        $productNameMap[$resolvedProductId] = isset($productRow['name']) ? trim((string) $productRow['name']) : '';
                    }
                }
            }
        }

        if (!siEnsureStockOutBatchUsageTable($financeConnect)) {
            return array('success' => false, 'message' => 'Failed to prepare stock out batch usage table.');
        }

        mysqli_begin_transaction($financeConnect);
        try {
            $allocationsByProduct = array();
            foreach ($productQtyMap as $productId => $qty) {
                $productId = (int) $productId;
                $qty = (int) $qty;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $productLabel = isset($productNameMap[$productId]) && $productNameMap[$productId] !== ''
                    ? $productNameMap[$productId]
                    : ('product #' . $productId);
                $allocationsByProduct[$productId] = siAllocateStockOutQuantityAcrossFifoBatches(
                    $financeConnect,
                    $warehouseId,
                    $productId,
                    $qty,
                    0,
                    0,
                    $productLabel,
                    $warehouseName
                );
            }

            $safeActor = mysqli_real_escape_string($financeConnect, $actorUserId);
            $safeOrderCode = mysqli_real_escape_string($financeConnect, $orderCode);
            $safeAttachment = mysqli_real_escape_string($financeConnect, $attachmentValue);
            $insertOrderSql = "INSERT INTO `stock_in_order`
                (`warehouse_id`, `order_number`, `stock_in_date`, `attachment`, `stock_type`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                (" . (int) $warehouseId . ", '" . $safeOrderCode . "', NOW(), '" . $safeAttachment . "', 'Stock Out', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($financeConnect, $insertOrderSql)) {
                throw new Exception('Failed to save stock out record.');
            }

            $stockOutOrderId = (int) mysqli_insert_id($financeConnect);
            foreach ($productQtyMap as $productId => $qty) {
                $productId = (int) $productId;
                $qty = (int) $qty;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $insertItemSql = "INSERT INTO `stock_in_order_item`
                    (`stock_in_order_id`, `product_id`, `package_id`, `product_quantity`, `create_by`, `create_date`, `create_time`, `status`)
                    VALUES
                    (" . $stockOutOrderId . ", " . $productId . ", 0, " . $qty . ", '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
                if (!mysqli_query($financeConnect, $insertItemSql)) {
                    throw new Exception('Failed to save stock out item.');
                }

                $stockOutItemId = (int) mysqli_insert_id($financeConnect);
                siInsertStockOutBatchUsageRows(
                    $financeConnect,
                    $stockOutOrderId,
                    $stockOutItemId,
                    isset($allocationsByProduct[$productId]) ? $allocationsByProduct[$productId] : array(),
                    $actorUserId
                );
            }

            mysqli_commit($financeConnect);
            return array(
                'success' => true,
                'message' => 'Warehouse inventory deducted successfully.',
                'item_ids' => array_keys($allocationsByProduct),
                'product_qty_map' => $productQtyMap,
                'stock_out_order_id' => $stockOutOrderId,
            );
        } catch (Exception $exception) {
            mysqli_rollback($financeConnect);
            return array(
                'success' => false,
                'message' => $exception->getMessage(),
            );
        }
    }
}

if (!function_exists('shopeeOmsFinalizeInitialShippedOrder')) {
    function shopeeOmsFinalizeInitialShippedOrder($cmsConnect, $financeConnect, $orderId, $actorUserId = 'SYSTEM', $actorUserGroupId = 0, $sourcePage = 'Shopee OMS')
    {
        $orderId = (int) $orderId;
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli) || $orderId <= 0) {
            return array('success' => false, 'message' => 'Unable to process initial Shipped status.');
        }

        $orderRow = shopeeOmsLoadOrder($financeConnect, $orderId, 'shopee');
        if (empty($orderRow)) {
            return array('success' => false, 'message' => 'Order not found.');
        }

        $currentStatus = shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '');
        if ($currentStatus !== 'SP') {
            return array('success' => false, 'message' => 'Initial shipped handling only supports Shipped orders.');
        }

        $deductResult = shopeeOmsDeductInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId, 'initial_shipped_status');
        if (empty($deductResult['success'])) {
            return $deductResult;
        }

        $transitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, $orderId, 'WAERD', array(
            'actor_user_id' => $actorUserId,
            'actor_user_group_id' => (int) $actorUserGroupId,
            'source_page' => $sourcePage,
            'remark' => 'Auto move after initial Shipped status.',
            'action' => 'auto_post_ship',
            'skip_permission' => true,
            'allow_auto_follow_up' => false,
        ));
        if (empty($transitionResult['success'])) {
            return $transitionResult;
        }

        return array(
            'success' => true,
            'message' => 'Warehouse inventory deducted and order moved to ' . shopeeOmsGetStatusLabel('WAERD') . '.',
            'deduct_result' => $deductResult,
            'transition_result' => $transitionResult,
        );
    }
}

if (!function_exists('shopeeOmsBulkMoveCurrentShippedOrdersToWaerd')) {
    function shopeeOmsBulkMoveCurrentShippedOrdersToWaerd($cmsConnect, $financeConnect, $actorUserId = 'SYSTEM', $actorUserGroupId = 0, $sourcePage = 'Shopee OMS', $options = array())
    {
        $options = is_array($options) ? $options : array();
        $skipPermission = !empty($options['skip_permission']);
        $sourceConfig = shopeeOmsResolveOrderSourceConfig(isset($options['platform']) ? $options['platform'] : 'shopee');
        $platform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : 'shopee';
        $orderConnect = shopeeOmsGetOrderSourceDbConnection($cmsConnect, $financeConnect, $sourceConfig);
        $tblName = isset($sourceConfig['table']) ? (string) $sourceConfig['table'] : SHOPEE_SG_ORDER_REQ;

        if (!($cmsConnect instanceof mysqli) || !($orderConnect instanceof mysqli)) {
            return array(
                'success' => false,
                'message' => 'Unable to bulk update Shipped orders.',
                'matched_count' => 0,
                'updated_count' => 0,
                'failed_count' => 0,
                'results' => array(),
            );
        }

        $statusCondition = shopeeOmsBuildOrderStatusFilterCondition($orderConnect, 'order_status', 'SP');
        if ($statusCondition === '') {
            return array(
                'success' => false,
                'message' => 'Unable to resolve the current Shipped status filter.',
                'matched_count' => 0,
                'updated_count' => 0,
                'failed_count' => 0,
                'results' => array(),
            );
        }

        $orderCodeField = isset($sourceConfig['order_code_field']) && trim((string) $sourceConfig['order_code_field']) !== ''
            ? '`' . $sourceConfig['order_code_field'] . '` AS order_code'
            : "CONCAT('" . mysqli_real_escape_string($orderConnect, strtoupper((string) (isset($sourceConfig['fallback_code_prefix']) ? $sourceConfig['fallback_code_prefix'] : 'OMS'))) . "-', id) AS order_code";
        $sql = "SELECT id, " . $orderCodeField . ", order_status FROM `" . $tblName . "` WHERE status = 'A' AND " . $statusCondition . " ORDER BY id ASC";
        $result = mysqli_query($orderConnect, $sql);
        if (!$result) {
            return array(
                'success' => false,
                'message' => 'Unable to load current Shipped orders.',
                'matched_count' => 0,
                'updated_count' => 0,
                'failed_count' => 0,
                'results' => array(),
            );
        }

        $matchedRows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $matchedRows[] = (array) $row;
        }

        if (empty($matchedRows)) {
            return array(
                'success' => true,
                'message' => 'No current Shipped orders found to update.',
                'matched_count' => 0,
                'updated_count' => 0,
                'failed_count' => 0,
                'results' => array(),
            );
        }

        $updatedCount = 0;
        $failedCount = 0;
        $transitionResults = array();

        foreach ($matchedRows as $orderRow) {
            $orderId = isset($orderRow['id']) ? (int) $orderRow['id'] : 0;
            if ($orderId <= 0) {
                continue;
            }

            $transitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, $orderId, 'WAERD', array(
                'actor_user_id' => $actorUserId,
                'actor_user_group_id' => (int) $actorUserGroupId,
                'source_page' => $sourcePage,
                'remark' => 'Bulk move current Shipped orders to Waiting Assign Estimate Received Date.',
                'action' => 'bulk_post_ship_sync',
                'skip_permission' => $skipPermission,
                'allow_auto_follow_up' => false,
                'platform' => $platform,
            ));

            $transitionResults[] = array(
                'order_id' => $orderId,
                'order_code' => isset($orderRow['order_code']) ? (string) $orderRow['order_code'] : '',
                'old_status' => isset($orderRow['order_status']) ? (string) $orderRow['order_status'] : '',
                'result' => $transitionResult,
            );

            if (!empty($transitionResult['success'])) {
                $updatedCount++;
            } else {
                $failedCount++;
            }
        }

        $success = $failedCount === 0;
        $message = $updatedCount > 0
            ? 'Updated ' . $updatedCount . ' current Shipped order(s) to ' . shopeeOmsGetStatusLabel('WAERD') . '.'
            : 'No current Shipped orders were updated.';

        if ($failedCount > 0) {
            $message .= ' ' . $failedCount . ' order(s) failed to update.';
        }

        return array(
            'success' => $success,
            'message' => $message,
            'matched_count' => count($matchedRows),
            'updated_count' => $updatedCount,
            'failed_count' => $failedCount,
            'results' => $transitionResults,
        );
    }
}

if (!function_exists('shopeeOmsRestockInventoryForOrder')) {
    function shopeeOmsRestockInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId = 'SYSTEM')
    {
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli) || !is_array($orderRow)) {
            return array('success' => false, 'message' => 'Unable to connect to warehouse inventory.');
        }

        $sourceConfig = shopeeOmsResolveOrderSourceConfig(shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee'));
        $productSummary = shopeeOmsBuildOrderProductSummaryBySource($cmsConnect, $orderRow, $sourceConfig);
        $productQtyMap = isset($productSummary['product_qty_map']) && is_array($productSummary['product_qty_map']) ? $productSummary['product_qty_map'] : array();
        if (empty($productQtyMap)) {
            return array('success' => false, 'message' => 'No product item found for this order package.');
        }

        $orderCode = shopeeOmsGetOrderCodeValue($orderRow, $sourceConfig);

        $warehouseId = shopeeOmsResolveStockOutWarehouseId($cmsConnect, $orderRow, shopeeOmsGetDefaultWarehouseId($cmsConnect));
        $safeActor = mysqli_real_escape_string($financeConnect, trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM');
        $restockOrderNumber = mysqli_real_escape_string($financeConnect, 'OMS-RETURN-' . $orderCode . '-' . date('YmdHis'));

        mysqli_begin_transaction($financeConnect);
        try {
            $insertOrderSql = "INSERT INTO `stock_in_order`
                (`warehouse_id`, `order_number`, `stock_in_date`, `attachment`, `stock_type`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                (" . (int) $warehouseId . ", '" . $restockOrderNumber . "', CURDATE(), '', 'Stock In', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($financeConnect, $insertOrderSql)) {
                throw new Exception('Unable to restock warehouse inventory.');
            }

            $stockInOrderId = (int) mysqli_insert_id($financeConnect);
            foreach ($productQtyMap as $productId => $qty) {
                $productId = (int) $productId;
                $qty = (int) $qty;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $insertItemSql = "INSERT INTO `stock_in_order_item`
                    (`stock_in_order_id`, `product_id`, `package_id`, `product_quantity`, `create_by`, `create_date`, `create_time`, `status`)
                    VALUES
                    (" . $stockInOrderId . ", " . $productId . ", 0, " . $qty . ", '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
                if (!mysqli_query($financeConnect, $insertItemSql)) {
                    throw new Exception('Unable to restock warehouse inventory.');
                }
            }

            mysqli_commit($financeConnect);
            return array(
                'success' => true,
                'message' => 'Warehouse inventory restocked successfully.',
                'stock_in_order_id' => $stockInOrderId,
                'product_qty_map' => $productQtyMap,
            );
        } catch (Exception $exception) {
            mysqli_rollback($financeConnect);
            return array('success' => false, 'message' => $exception->getMessage());
        }
    }
}

if (!function_exists('shopeeOmsProcessWarehouseScanByToken')) {
    function shopeeOmsProcessWarehouseScanByToken($cmsConnect, $financeConnect, $tokenValue, $actorUserId = 'QR_PUBLIC', $actorUserGroupId = 0, $sourcePage = 'Warehouse Stock-out Scan', $attachments = array())
    {
        $tokenValue = trim((string) $tokenValue);
        if ($tokenValue === '' || !($financeConnect instanceof mysqli)) {
            return array('success' => false, 'message' => 'Invalid warehouse scan token.');
        }

        $safeToken = mysqli_real_escape_string($financeConnect, $tokenValue);
        $sql = "SELECT * FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE token = '" . $safeToken . "' AND token_type = 'stock_out' AND status = 'A' ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($financeConnect, $sql);
        if (!$result || mysqli_num_rows($result) === 0) {
            return array('success' => false, 'message' => 'Warehouse scan token was not found.');
        }

        $tokenRow = mysqli_fetch_assoc($result);
        if (isset($tokenRow['used_at']) && trim((string) $tokenRow['used_at']) !== '') {
            return array('success' => false, 'message' => 'This warehouse stock-out scan link has already been used.');
        }

        $sourceConfig = null;
        $orderRow = shopeeOmsLoadOrderFromTokenRow($cmsConnect, $financeConnect, $tokenRow, $sourceConfig, 'shopee');
        if (empty($orderRow)) {
            return array('success' => false, 'message' => 'Order linked to this scan token was not found.');
        }
        $tokenPlatform = isset($sourceConfig['platform']) ? (string) $sourceConfig['platform'] : shopeeOmsGetOrderSourcePlatform($orderRow, 'shopee');

        if (shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '') !== 'TP') {
            return array('success' => false, 'message' => 'This order is no longer waiting for warehouse stock-out.');
        }

        if ((int) $actorUserGroupId > 0 && !shopeeOmsHasTransitionPermission($cmsConnect, isset($orderRow['order_status']) ? $orderRow['order_status'] : '', 'SP', $actorUserGroupId, $orderRow, $actorUserId)) {
            return array('success' => false, 'message' => 'You do not have permission to perform this warehouse stock-out scan.');
        }

        $deductResult = shopeeOmsDeductInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId, $tokenValue, $attachments);
        if (empty($deductResult['success'])) {
            return $deductResult;
        }

        $safeActor = mysqli_real_escape_string($financeConnect, trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'QR_PUBLIC');
        mysqli_query($financeConnect, "UPDATE `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` SET `used_at` = NOW(), `used_by` = '" . $safeActor . "', `used_source` = '" . mysqli_real_escape_string($financeConnect, $sourcePage) . "', `update_by` = '" . $safeActor . "', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE id = " . (int) $tokenRow['id'] . " LIMIT 1");

        $transitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, (int) $orderRow['id'], 'SP', array(
            'actor_user_id' => $actorUserId,
            'actor_user_group_id' => (int) $actorUserGroupId,
            'source_page' => $sourcePage,
            'remark' => 'Warehouse stock-out QR scan completed.',
            'action' => 'warehouse_scan',
            'skip_permission' => ((int) $actorUserGroupId <= 0),
            'field_updates' => array(
                'warehouse_scan_at' => date('Y-m-d H:i:s'),
                'warehouse_scan_by' => $actorUserId,
                'warehouse_scan_ref' => $tokenValue,
            ),
            'related_token_scan_id' => $tokenValue,
            'platform' => $tokenPlatform,
        ));
        if (is_array($transitionResult)) {
            $transitionResult['stock_out_order_id'] = isset($deductResult['stock_out_order_id']) ? (int) $deductResult['stock_out_order_id'] : 0;
        }
        return $transitionResult;
    }
}

if (!function_exists('shopeeOmsHandleReturn')) {
    function shopeeOmsHandleReturn($cmsConnect, $financeConnect, $orderId, $returnType, $remark, $actorUserId, $actorUserGroupId, $sourcePage = 'Shopee Order Request')
    {
        $returnType = strtolower(trim((string) $returnType));
        if (!in_array($returnType, array('restock', 'damaged'), true)) {
            return array('success' => false, 'message' => 'Invalid return type.');
        }

        $orderRow = shopeeOmsLoadOrder($financeConnect, $orderId);
        if (empty($orderRow)) {
            return array('success' => false, 'message' => 'Order not found.');
        }

        $currentStatus = shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '');
        if ($currentStatus !== 'R') {
            return array('success' => false, 'message' => 'Please mark the order as Return before choosing Restock or Damaged.');
        }

        if (!shopeeOmsHasTransitionPermission($cmsConnect, $currentStatus, 'CR', $actorUserGroupId, $orderRow, $actorUserId)) {
            return array('success' => false, 'message' => 'You are not allowed to close this order as returned.');
        }

        $inventoryEffect = 'inventory_loss';
        if ($returnType === 'restock') {
            $restockResult = shopeeOmsRestockInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId);
            if (empty($restockResult['success'])) {
                return $restockResult;
            }
            $inventoryEffect = 'inventory_restock';
        }

        $transitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, $orderId, 'CR', array(
            'actor_user_id' => $actorUserId,
            'actor_user_group_id' => $actorUserGroupId,
            'source_page' => $sourcePage,
            'remark' => trim((string) $remark),
            'action' => $returnType === 'restock' ? 'return_restock' : 'return_damaged',
            'allow_auto_follow_up' => false,
        ));
        if (empty($transitionResult['success'])) {
            return $transitionResult;
        }

        $safeOrderCode = mysqli_real_escape_string($financeConnect, (string) (isset($orderRow['orderID']) ? $orderRow['orderID'] : ''));
        $safeStatusBefore = mysqli_real_escape_string($financeConnect, $currentStatus);
        $safeRemark = mysqli_real_escape_string($financeConnect, trim((string) $remark));
        $safeUserId = mysqli_real_escape_string($financeConnect, trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM');
        $safeSourcePage = mysqli_real_escape_string($financeConnect, (string) $sourcePage);
        $safeReturnType = mysqli_real_escape_string($financeConnect, $returnType);
        $safeInventoryEffect = mysqli_real_escape_string($financeConnect, $inventoryEffect);
        mysqli_query($financeConnect, "INSERT INTO `" . ORDER_RETURN_LOG . "`
            (`order_id`, `order_code`, `status_before`, `status_after`, `return_type`, `inventory_effect`, `remark`, `user_id`, `user_group_id`, `source_page`, `action_at`, `create_date`, `create_time`, `status`)
            VALUES
            (" . (int) $orderId . ", '" . $safeOrderCode . "', '" . $safeStatusBefore . "', 'CR', '" . $safeReturnType . "', '" . $safeInventoryEffect . "', '" . $safeRemark . "', '" . $safeUserId . "', " . (int) $actorUserGroupId . ", '" . $safeSourcePage . "', NOW(), CURDATE(), CURTIME(), 'A')");

        return array(
            'success' => true,
            'message' => 'Return action saved successfully.',
            'inventory_effect' => $inventoryEffect,
        );
    }
}

if (!function_exists('sorGenerateRequestNo')) {
    function sorGenerateRequestNo($connect)
    {
        return 'SOR' . date('YmdHis') . mt_rand(1000, 9999);
    }
}

if (!function_exists('sorBase64UrlEncode')) {
    function sorBase64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('sorBase64UrlDecode')) {
    function sorBase64UrlDecode($data)
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('sorEncodeToken')) {
    function sorEncodeToken($requestId)
    {
        $payload = $requestId . '|' . time();
        $key = hash('sha256', SITEURL . '|stock_order_request', true);

        if (function_exists('openssl_encrypt')) {
            // 1. Generate a secure, random IV
            $ivLength = openssl_cipher_iv_length('AES-256-CBC');
            $iv = openssl_random_pseudo_bytes($ivLength);
            
            $encrypted = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($encrypted !== false) {
                // 2. Prepend the random IV to the encrypted data so it can be extracted later
                return sorBase64UrlEncode($iv . $encrypted);
            }
        }

        return sorBase64UrlEncode($payload);
    }
}

if (!function_exists('sorDecodeToken')) {
    // Added an expiry window (86400 seconds = 24 hours)
    function sorDecodeToken($token, $expirySeconds = 86400)
    {
        $key = hash('sha256', SITEURL . '|stock_order_request', true);
        $decoded = sorBase64UrlDecode($token);

        if ($decoded === false || $decoded === null || $decoded === '') {
            return 0;
        }

        $plain = '';

        if (function_exists('openssl_decrypt')) {
            $ivLength = openssl_cipher_iv_length('AES-256-CBC');
            // Ensure the decoded string is at least as long as the IV
            if (strlen($decoded) > $ivLength) {
                // 3. Extract the random IV from the front, and the ciphertext from the back
                $iv = substr($decoded, 0, $ivLength);
                $ciphertext = substr($decoded, $ivLength);
                
                $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                if ($decrypted !== false) {
                    $plain = $decrypted;
                }
            }
        }

        // Fallback for unencrypted payloads (if openssl fails)
        if ($plain === '') {
            $plain = $decoded;
        }

        if (strpos($plain, '|') !== false) {
            $parts = explode('|', $plain);
            $requestId = (int) $parts[0];
            $timestamp = isset($parts[1]) ? (int) $parts[1] : 0;
            
            // 4. Validate Expiration (Check if current time minus creation time is within the allowed window)
            if ($timestamp > 0 && (time() - $timestamp) <= $expirySeconds) {
                return $requestId;
            }
        }

        return 0; // Token is invalid, tampered with, or expired
    }
}

if (!function_exists('sorResolveTrackingMySlug')) {
    /**
     * Map courier name/tracking number to tracking.my URL slug.
     * Tracking number pattern is checked first because imported/scanned labels
     * may have the wrong courier selected in CMS.
     */
    function sorResolveTrackingMySlug($courierName, $trackingNo)
    {
        $courierName = strtolower(trim((string) $courierName));
        $trackingNo = strtoupper(trim((string) $trackingNo));

        // SPX Malaysia airbill format, example: MY064857959876.
        // Check this before courier name because CMS courier may be selected as J&T.
        if (preg_match('/^MY\d{10,14}$/', $trackingNo)) {
            return 'shopee';
        }

        if (preg_match('/^SPXMY|^SPX/i', $trackingNo)) {
            return 'shopee';
        }

        if (preg_match('/^MYJZ/i', $trackingNo)) {
            return 'dhl-ecommerce';
        }

        if (preg_match('/^JT/i', $trackingNo)) {
            return 'jt';
        }

        if (preg_match('/^NV/i', $trackingNo)) {
            return 'ninjavan';
        }

        if (preg_match('/^MY[A-Z]{2}\d/i', $trackingNo)) {
            return 'dhl-ecommerce';
        }

        if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $trackingNo)) {
            return 'pos';
        }

        if (preg_match('/^\d{10,}$/', $trackingNo)) {
            return 'pos';
        }

        $nameMap = array(
            'spx' => 'shopee',
            'spx express' => 'shopee',
            'shopee express' => 'shopee',
            'shopee' => 'shopee',
            'dhl' => 'dhl-ecommerce',
            'dhl ecommerce' => 'dhl-ecommerce',
            'dhl e-commerce' => 'dhl-ecommerce',
            'pos malaysia' => 'pos',
            'pos laju' => 'pos',
            'poslaju' => 'pos',
            'j&t' => 'jt',
            'j&t express' => 'jt',
            'jnt' => 'jt',
            'jnt express' => 'jt',
            'best express' => 'best',
            'citylink' => 'citylink',
            'citylink express' => 'citylink',
            'ninja van' => 'ninjavan',
            'ninjavan' => 'ninjavan',
            'gdex' => 'gdex',
            'flash express' => 'flash',
            'abx express' => 'abx',
            'skynet' => 'skynet',
        );

        foreach ($nameMap as $key => $slug) {
            if (strpos($courierName, $key) !== false) {
                return $slug;
            }
        }

        return '';
    }
}

if (!function_exists('sorBuildTrackingUrl')) {
    function sorBuildTrackingUrl($trackingLink, $trackingNo)
    {
        $trackingLink = trim((string) $trackingLink);
        $trackingNo = trim((string) $trackingNo);

        if ($trackingLink === '' || $trackingNo === '') {
            return '';
        }

        if (strpos($trackingLink, '{tracking}') !== false) {
            return str_replace('{tracking}', rawurlencode($trackingNo), $trackingLink);
        }

        $lastChar = substr($trackingLink, -1);
        if ($lastChar === '=' || $lastChar === '/' || $lastChar === '?') {
            return $trackingLink . rawurlencode($trackingNo);
        }

        if (strpos($trackingLink, '?') !== false) {
            return $trackingLink . '&tracking=' . rawurlencode($trackingNo);
        }

        return $trackingLink . '?tracking=' . rawurlencode($trackingNo);
    }
}

if (!function_exists('sorFetchTrackingStatus')) {
    function sorFetchTrackingStatus($trackingUrl)
    {
        if ($trackingUrl === '') {
            return '';
        }

        $opts = array(
            'http' => array(
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36\r\n" .
                            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                            "Accept-Language: en-US,en;q=0.5\r\n",
                'timeout' => 15,
                'follow_location' => true,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        );
        $ctx = stream_context_create($opts);
        $body = @file_get_contents($trackingUrl, false, $ctx);

        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('/^HTTP\/[\d.]+ (\d+)/', $hdr, $m)) {
                    $httpCode = (int) $m[1];
                }
            }
        }

        $timestamp = date('Y-m-d H:i:s');

        if ($body === false || $body === null || $body === '') {
            return "Unable to retrieve tracking status (HTTP $httpCode). [$timestamp]";
        }

        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $title = trim(html_entity_decode(strip_tags($matches[1])));
        }

        // Strip <script> blocks to avoid matching JS dictionary keywords (e.g. tracking.my pages).
        $cleanBody = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $body);
        $bodyLower = strtolower($cleanBody);
        $keywords = array(
            'shipment canceled', 'shipment cancelled',
            'cancelled', 'canceled',
            'returned to sender',
            'exception',
            'delivered', 'out for delivery', 'in transit',
            'picked up', 'shipment information received',
            'customs', 'clearance event',
            'arrived at facility', 'departed facility',
        );
        $found = '';
        foreach ($keywords as $keyword) {
            if (strpos($bodyLower, $keyword) !== false) {
                $found = $keyword;
                break;
            }
        }

        $parts = array();
        if ($found !== '') {
            $parts[] = 'Detected: ' . ucwords($found);
        }
        $parts[] = 'Synced: ' . $timestamp;

        return implode(' | ', $parts);
    }
}

// --- Minimal WebSocket helpers for tracking.my ---
if (!function_exists('sorWsEncode')) {
    /** Encode a text payload into a WebSocket frame (client-masked). */
    function sorWsEncode($payload) {
        $len = strlen($payload);
        $frame = chr(0x81); // FIN + text opcode
        if ($len < 126) {
            $frame .= chr(0x80 | $len);
        } elseif ($len < 65536) {
            $frame .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $len);
        }
        $mask = openssl_random_pseudo_bytes(4);
        $frame .= $mask;
        for ($i = 0; $i < $len; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }
        return $frame;
    }
}

if (!function_exists('sorWsDecode')) {
    /** Read one WebSocket frame from a socket; handles ping/pong automatically. */
    function sorWsDecode($socket) {
        $header = @fread($socket, 2);
        if ($header === false || strlen($header) < 2) return '';
        $opcode = ord($header[0]) & 0x0F;
        $masked = (ord($header[1]) & 0x80) !== 0;
        $len = ord($header[1]) & 0x7F;
        if ($len === 126) {
            $ext = @fread($socket, 2);
            if ($ext === false || strlen($ext) < 2) return '';
            $unpacked = unpack('n', $ext);
            $len = $unpacked[1];
        } elseif ($len === 127) {
            $ext = @fread($socket, 8);
            if ($ext === false || strlen($ext) < 8) return '';
            $unpacked = unpack('J', $ext);
            $len = $unpacked[1];
        }
        if ($len > 2097152) return ''; // sanity: max 2 MB
        $mask = '';
        if ($masked) {
            $mask = @fread($socket, 4);
            if ($mask === false) $mask = '';
        }
        $payload = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = @fread($socket, min($remaining, 8192));
            if ($chunk === false || $chunk === '') break;
            $payload .= $chunk;
            $remaining -= strlen($chunk);
        }
        if ($masked && strlen($mask) === 4) {
            for ($i = 0; $i < strlen($payload); $i++) {
                $payload[$i] = $payload[$i] ^ $mask[$i % 4];
            }
        }
        // Ping â†’ reply pong, then read next frame
        if ($opcode === 0x9) {
            @fwrite($socket, chr(0x8A) . chr(strlen($payload)) . $payload);
            return sorWsDecode($socket);
        }
        if ($opcode === 1) return $payload; // text frame
        return '';
    }
}

if (!function_exists('sorFetchTrackingMyWebSocket')) {
    /**
     * Fetch tracking status from tracking.my WebSocket.
     * More compatible with live hosting by trying tls:// and ssl://,
     * adding SNI headers, and reading multiple WebSocket frames.
     */
    function sorFetchTrackingMyWebSocket($courierName, $trackingNo, &$rawJson = null)
    {
        $rawJson = null;
        $trackingNo = trim((string) $trackingNo);
        if ($trackingNo === '') {
            return '';
        }

        $slug = sorResolveTrackingMySlug($courierName, $trackingNo);
        if ($slug === '') {
            return '';
        }

        $pageUrl = 'https://www.tracking.my/' . $slug . '/' . rawurlencode($trackingNo);
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36\r\n" .
                            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n" .
                            "Accept-Language: en-US,en;q=0.9\r\n" .
                            "Connection: close\r\n",
                'timeout' => 20,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'SNI_enabled' => true,
                'SNI_server_name' => 'www.tracking.my',
                'peer_name' => 'www.tracking.my',
            ),
        );

        $body = @file_get_contents($pageUrl, false, stream_context_create($opts));
        if ($body === false || trim((string) $body) === '') {
            return '';
        }

        $wsMessage = '';
        if (preg_match('/socket\.send\(\s*"([^"]+)"\s*\)/', $body, $m)) {
            $wsMessage = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        } else if (preg_match("/socket\.send\(\s*'([^']+)'\s*\)/", $body, $m)) {
            $wsMessage = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        $wsCheck = json_decode($wsMessage, true);
        if (!is_array($wsCheck) || !isset($wsCheck['action'])) {
            return '';
        }

        $socketTargets = array(
            'tls://www.tracking.my:443',
            'ssl://www.tracking.my:443',
        );

        $data = '';
        foreach ($socketTargets as $socketTarget) {
            $wsKey = base64_encode(openssl_random_pseudo_bytes(16));
            $ctx = stream_context_create(array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'SNI_enabled' => true,
                    'SNI_server_name' => 'www.tracking.my',
                    'peer_name' => 'www.tracking.my',
                ),
            ));

            $errno = 0;
            $errstr = '';
            $sock = @stream_socket_client($socketTarget, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
            if (!$sock) {
                continue;
            }

            stream_set_timeout($sock, 15);

            $handshake = "GET /websocket HTTP/1.1\r\n" .
                "Host: www.tracking.my\r\n" .
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Key: " . $wsKey . "\r\n" .
                "Sec-WebSocket-Version: 13\r\n" .
                "Origin: https://www.tracking.my\r\n" .
                "Pragma: no-cache\r\n" .
                "Cache-Control: no-cache\r\n" .
                "\r\n";

            @fwrite($sock, $handshake);

            $resp = '';
            while (!feof($sock)) {
                $line = @fgets($sock, 2048);
                if ($line === false) {
                    break;
                }

                $resp .= $line;
                if ($line === "\r\n") {
                    break;
                }
            }

            if (strpos($resp, '101') === false) {
                @fclose($sock);
                continue;
            }

            @fwrite($sock, sorWsEncode($wsMessage));

            $startedAt = time();
            for ($i = 0; $i < 8; $i++) {
                if ((time() - $startedAt) > 15) {
                    break;
                }

                $frameData = sorWsDecode($sock);
                if ($frameData === '') {
                    continue;
                }

                $decoded = json_decode($frameData, true);
                if (is_array($decoded) && isset($decoded['result']) && is_array($decoded['result'])) {
                    $data = $frameData;
                    break;
                }
            }

            @fclose($sock);

            if ($data !== '') {
                break;
            }
        }

        if ($data === '') {
            return '';
        }

        $result = json_decode($data, true);
        $rawJson = $data;

        if (!is_array($result) || !isset($result['result']) || !is_array($result['result'])) {
            return '';
        }

        $latestStatus = '';
        $latestContent = '';

        foreach ($result['result'] as $event) {
            if (!is_array($event)) {
                continue;
            }

            $evStatus = isset($event['status']) ? strtolower(trim((string) $event['status'])) : '';
            if ($evStatus === '' || $evStatus === 'sponsored') {
                continue;
            }

            $latestStatus = $evStatus;
            $latestContent = isset($event['content']) ? trim((string) $event['content']) : '';
            break;
        }

        if ($latestStatus === '') {
            return '';
        }

        $contentLower = strtolower($latestContent);
        $contentKeywords = array(
            'parcel has been delivered' => 'Delivered',
            'delivered' => 'Delivered',
            'out for delivery' => 'Out for Delivery',
            'in transit' => 'In Transit',
            'picked up' => 'Picked Up',
            'returned to sender' => 'Returned to Sender',
            'return' => 'Returned',
            'cancel' => 'Cancelled',
            'preparing' => 'Shipment Information Received',
            'information received' => 'Shipment Information Received',
        );

        $displayStatus = ucwords(str_replace('_', ' ', $latestStatus));
        foreach ($contentKeywords as $needle => $label) {
            if (strpos($contentLower, $needle) !== false) {
                $displayStatus = $label;
                break;
            }
        }

        return $displayStatus . ' | Synced: ' . date('Y-m-d H:i:s') . ' | Source: tracking.my';
    }
}

if (!function_exists('sorGetEasyParcelConfig')) {
    function sorGetEasyParcelConfig($countryCode)
    {
        $countryCode = strtoupper(trim((string) $countryCode));
        if ($countryCode === 'SG') {
            return array(
                'domain' => EASYPARCEL_DOMAIN_SG,
                'auth' => EASYPARCEL_AUTH_SG,
                'api' => EASYPARCEL_API_SG,
            );
        }

        return array(
            'domain' => EASYPARCEL_DOMAIN_MY,
            'auth' => EASYPARCEL_AUTH_MY,
            'api' => EASYPARCEL_API_MY,
        );
    }
}

if (!function_exists('sorFetchTrackingStatusEasyParcel')) {
    function sorFetchTrackingStatusEasyParcel($trackingNo, $countryCode)
    {
        $trackingNo = trim((string) $trackingNo);
        if ($trackingNo === '') {
            return '';
        }

        $cfg = sorGetEasyParcelConfig($countryCode);

        // Demo credentials cannot track real parcels â€” skip entirely.
        if (stripos($cfg['domain'], 'demo.connect') !== false) {
            return '';
        }

        $url = $cfg['domain'] . 'EPTrackingBulk';
        $postparam = array(
            'authentication' => $cfg['auth'],
            'api' => $cfg['api'],
            'bulk' => array(
                array('awb' => $trackingNo),
            ),
        );

        $postData = http_build_query($postparam);
        $opts = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 15,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        );
        $ctx = stream_context_create($opts);
        $response = @file_get_contents($url, false, $ctx);

        if ($response === false || $response === null || $response === '') {
            return 'EasyParcel tracking unavailable: request failed';
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            return 'EasyParcel tracking response invalid.';
        }

        // EasyParcel returns api_status "Success" (string) or "0" (numeric) on success.
        $apiStatus = isset($json['api_status']) ? (string) $json['api_status'] : '';
        $isSuccess = ($apiStatus === '' || $apiStatus === '0' || strtolower($apiStatus) === 'success');
        if (!$isSuccess) {
            $msg = isset($json['error']) ? (string) $json['error'] : 'Unknown EasyParcel error';
            return 'EasyParcel tracking failed: ' . $msg;
        }

        $status = '';
        if (isset($json['result'][0]['latest_status'])) {
            $status = (string) $json['result'][0]['latest_status'];
        } else if (isset($json['result'][0]['status'])) {
            $status = (string) $json['result'][0]['status'];
        } else if (isset($json['result'][0]['detail'][0]['content'])) {
            $status = (string) $json['result'][0]['detail'][0]['content'];
        } else if (isset($json['result'][0]['detail'][0]['status'])) {
            $status = (string) $json['result'][0]['detail'][0]['status'];
        }

        $status = trim($status);
        // EasyParcel uses "--" as a placeholder when it has no tracking data yet.
        if ($status === '' || $status === '--') {
            return '';
        }

        return $status . ' | Synced: ' . date('Y-m-d H:i:s') . ' | Source: EasyParcel';
    }
}

if (!function_exists('sorRefreshTrackingStatus')) {
    function sorRefreshTrackingStatus($financeConnect, $requestId, &$message = '', $cmsConnect = null)
    {
        $requestId = (int) $requestId;
        if ($requestId <= 0) {
            $message = 'Invalid request id.';
            return false;
        }

        $requestSql = "SELECT id, tracking_no, courier_id
               FROM stock_order_request
               WHERE id = '$requestId' AND status = 'A'";

        $requestRst = mysqli_query($financeConnect, $requestSql);
        if (!$requestRst || !($row = mysqli_fetch_assoc($requestRst))) {
            $message = 'Order request not found.';
            return false;
        }

        $trackingNo = isset($row['tracking_no']) ? trim((string) $row['tracking_no']) : '';
        $courierId = isset($row['courier_id']) ? (int) $row['courier_id'] : 0;

        $trackingLink = '';
        $courierNameForSlug = '';
        $courierCountryCode = 'MY';
        $lookupConnect = $cmsConnect ? $cmsConnect : $financeConnect;
        if ($courierId > 0) {
            $courierSql = "SELECT tracking_link, country, name FROM " . COURIER . " WHERE id = '$courierId' LIMIT 1";
            $courierRst = mysqli_query($lookupConnect, $courierSql);
            if ($courierRst && ($courierRow = mysqli_fetch_assoc($courierRst))) {
                $trackingLink = isset($courierRow['tracking_link']) ? trim((string) $courierRow['tracking_link']) : '';
                $courierNameForSlug = isset($courierRow['name']) ? trim((string) $courierRow['name']) : '';
                $courierCountryId = isset($courierRow['country']) ? (int) $courierRow['country'] : 0;
                if ($courierCountryId > 0) {
                    $countrySql = "SELECT code FROM " . COUNTRIES . " WHERE id = '$courierCountryId' LIMIT 1";
                    $countryRst = mysqli_query($lookupConnect, $countrySql);
                    if ($countryRst && ($countryRow = mysqli_fetch_assoc($countryRst))) {
                        $courierCountryCode = isset($countryRow['code']) ? strtoupper(trim((string) $countryRow['code'])) : 'MY';
                    }
                }
            }
        }

        if ($trackingNo === '') {
            $message = 'Missing tracking number.';
            return false;
        }

        $trackingUrl = sorBuildTrackingUrl($trackingLink, $trackingNo);
        $statusText = '';

        // SPX Malaysia tracking numbers can be scanned/imported while the CMS courier is still wrong.
        // Try tracking.my first for this pattern.
        if (preg_match('/^MY\d{10,14}$/i', $trackingNo)) {
            $statusText = sorFetchTrackingMyWebSocket($courierNameForSlug, $trackingNo);
        }

        if ($statusText === '') {
            $statusText = sorFetchTrackingStatusEasyParcel($trackingNo, $courierCountryCode);
        }

        // Fallback to courier tracking page scrape when EasyParcel cannot provide a usable status.
        $epFailed = ($statusText === '' || stripos($statusText, 'failed:') !== false || stripos($statusText, 'unavailable') !== false || stripos($statusText, 'no status') !== false);
        if ($epFailed && $trackingUrl !== '') {
            $statusText = sorFetchTrackingStatus($trackingUrl);
        }

        // Secondary fallback: try tracking.my WebSocket API for structured data.
        // Also runs if the previous step only returned an error/failure message.
        $statusIsUsable = (stripos($statusText, 'Detected:') !== false)
            || (stripos($statusText, 'Source:') !== false);
        $statusIsError = (stripos($statusText, 'Unable to retrieve') !== false)
            || (stripos($statusText, 'unavailable') !== false)
            || (stripos($statusText, 'failed') !== false);

        if ((!$statusIsUsable || $statusIsError) && $trackingNo !== '') {
            $wsStatus = sorFetchTrackingMyWebSocket($courierNameForSlug, $trackingNo);
            if ($wsStatus !== '') {
                $statusText = $wsStatus;
            } else {
                $slug = sorResolveTrackingMySlug($courierNameForSlug, $trackingNo);
                if ($slug !== '') {
                    $altUrl = 'https://www.tracking.my/' . $slug . '/' . rawurlencode($trackingNo);
                    $altStatus = sorFetchTrackingStatus($altUrl);
                    if (stripos($altStatus, 'Detected:') !== false) {
                        $statusText = $altStatus . ' | Source: tracking.my';
                    }
                }

                if ($statusText === '' || stripos($statusText, 'Unable to retrieve') !== false) {
                    $statusText = 'Tracking unavailable | Synced: ' . date('Y-m-d H:i:s');
                }
            }
        }

        $safeStatus = mysqli_real_escape_string($financeConnect, $statusText);
        $safeUrl = mysqli_real_escape_string($financeConnect, $trackingUrl);

        $updateBy = defined('USER_ID') && USER_ID !== '' ? USER_ID : 'cron';
        $updateSql = "UPDATE stock_order_request
              SET tracking_status = '$safeStatus',
              tracking_last_sync = NOW(),
              update_by = '" . $updateBy . "',
              update_date = CURDATE(),
              update_time = CURTIME()
              WHERE id = '$requestId'";

        if (!mysqli_query($financeConnect, $updateSql)) {
            $message = 'Failed to update tracking status.';
            return false;
        }

        $message = "Tracking refreshed successfully.";
        return true;
    }
}

if (!function_exists('siEsc')) {
    function siEsc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('siLoadWarehouses')) {
    function siLoadWarehouses($connect)
    {
        $rows = array();
        $result = mysqli_query($connect, "SELECT id, name FROM " . WHSE . " WHERE status='A' ORDER BY name ASC");
        if ($result) {
            while ($r = mysqli_fetch_assoc($result)) {
                $rows[] = array('id' => (int) $r['id'], 'name' => (string) $r['name']);
            }
        }
        return $rows;
    }
}

if (!function_exists('siLoadProducts')) {
    function siLoadProducts($connect)
    {
        $rows = array();
        $result = mysqli_query($connect, "SELECT id, name FROM " . PROD . " WHERE status='A' ORDER BY name ASC");
        if ($result) {
            while ($r = mysqli_fetch_assoc($result)) {
                $rows[] = array('id' => (int) $r['id'], 'name' => (string) $r['name']);
            }
        }
        return $rows;
    }
}

if (!function_exists('siLoadPackages')) {
    function siLoadPackages($connect)
    {
        $rows = array();
        $result = mysqli_query($connect, "SELECT id, name, product FROM " . PKG . " WHERE status='A' ORDER BY name ASC");
        if ($result) {
            while ($r = mysqli_fetch_assoc($result)) {
                $productIds = array();
                $csv = isset($r['product']) ? (string) $r['product'] : '';
                if ($csv !== '') {
                    foreach (explode(',', $csv) as $raw) {
                        $prodId = (int) trim((string) $raw);
                        if ($prodId > 0) {
                            $productIds[] = $prodId;
                        }
                    }
                }
                $rows[] = array(
                    'id' => (int) $r['id'],
                    'name' => (string) $r['name'],
                    'product_ids' => array_values(array_unique($productIds)),
                );
            }
        }
        return $rows;
    }
}

if (!function_exists('siBuildNameMaps')) {
    function siBuildNameMaps($rows)
    {
        $idToName = array();
        $nameToId = array();
        foreach ($rows as $r) {
            $id = isset($r['id']) ? (int) $r['id'] : 0;
            $name = isset($r['name']) ? (string) $r['name'] : '';
            if ($id <= 0 || $name === '') {
                continue;
            }
            $idToName[$id] = $name;
            $nameToId[strtolower(trim($name))] = $id;
        }
        return array($idToName, $nameToId);
    }
}

if (!function_exists('siBuildPackageProductMap')) {
    function siBuildPackageProductMap($packages)
    {
        $map = array();
        foreach ($packages as $p) {
            $pkgId = isset($p['id']) ? (int) $p['id'] : 0;
            $productIds = isset($p['product_ids']) && is_array($p['product_ids']) ? $p['product_ids'] : array();
            $map[$pkgId] = $productIds;
        }
        return $map;
    }
}

if (!function_exists('siPackageMatchesProduct')) {
    function siPackageMatchesProduct($packageProductMap, $packageId, $productId)
    {
        $packageId = (int) $packageId;
        $productId = (int) $productId;
        if ($packageId <= 0 || $productId <= 0) {
            return false;
        }
        $allowed = isset($packageProductMap[$packageId]) ? $packageProductMap[$packageId] : array();
        if (!is_array($allowed) || count($allowed) === 0) {
            return false;
        }
        return in_array($productId, $allowed, true);
    }
}

if (!function_exists('siResolveProductIdFromPackage')) {
    function siResolveProductIdFromPackage($packageProductMap, $packageId)
    {
        $packageId = (int) $packageId;
        if ($packageId <= 0) {
            return 0;
        }
        $allowed = isset($packageProductMap[$packageId]) ? $packageProductMap[$packageId] : array();
        if (!is_array($allowed) || count($allowed) !== 1) {
            return 0;
        }
        return (int) $allowed[0];
    }
}

if (!function_exists('siAttachmentDecodeList')) {
    function siAttachmentDecodeList($rawValue)
    {
        $rawValue = trim((string) $rawValue);
        if ($rawValue === '') {
            return array();
        }

        $list = array();
        $isJsonArray = false; // Track if it's successfully parsed as JSON

        if ($rawValue !== '' && substr($rawValue, 0, 1) === '[') {
            $decoded = json_decode($rawValue, true);
            if (is_array($decoded)) {
                $isJsonArray = true; // Mark as valid JSON array
                foreach ($decoded as $path) {
                    $p = trim((string) $path);
                    if ($p !== '') {
                        $list[] = $p;
                    }
                }
            }
        }

        // Only fallback to using the raw value if it wasn't a valid JSON array
        if (!$isJsonArray && count($list) === 0) {
            $list[] = $rawValue;
        }

        $uniq = array();
        foreach ($list as $path) {
            $uniq[$path] = true;
        }
        return array_keys($uniq);
    }
}

if (!function_exists('siAttachmentEncodeList')) {
    function siAttachmentEncodeList($paths)
    {
        if (!is_array($paths)) {
            $paths = siAttachmentDecodeList($paths);
        }

        $clean = array();
        foreach ($paths as $path) {
            $p = trim((string) $path);
            if ($p !== '') {
                $clean[$p] = true;
            }
        }

        $final = array_keys($clean);
        if (count($final) === 0) {
            return '';
        }
        return json_encode($final);
    }
}

if (!function_exists('siNormalizeStockType')) {
    function siNormalizeStockType($value)
    {
        $value = strtolower(trim((string) $value));
        return $value === 'stock out' ? 'Stock Out' : 'Stock In';
    }
}

if (!function_exists('siBuildProductQtyMap')) {
    function siBuildProductQtyMap($items)
    {
        $map = array();
        foreach ((array) $items as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : (isset($item['product_quantity']) ? (int) $item['product_quantity'] : 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($map[$productId])) {
                $map[$productId] = 0;
            }
            $map[$productId] += $qty;
        }
        return $map;
    }
}

if (!function_exists('siTableExistsByName')) {
    function siTableExistsByName($connect, $tblName)
    {
        $tblName = trim((string) $tblName);
        if (!($connect instanceof mysqli) || $tblName === '') {
            return false;
        }

        $sql = "SHOW TABLES LIKE '" . mysqli_real_escape_string($connect, $tblName) . "'";
        $result = mysqli_query($connect, $sql);
        return ($result instanceof mysqli_result && mysqli_num_rows($result) > 0);
    }
}

if (!function_exists('siColumnExistsByName')) {
    function siColumnExistsByName($connect, $tblName, $columnName)
    {
        $tblName = trim((string) $tblName);
        $columnName = trim((string) $columnName);
        if (!($connect instanceof mysqli) || $tblName === '' || $columnName === '') {
            return false;
        }

        $sql = "SHOW COLUMNS FROM `" . str_replace('`', '``', $tblName) . "` LIKE '" . mysqli_real_escape_string($connect, $columnName) . "'";
        $result = mysqli_query($connect, $sql);
        return ($result instanceof mysqli_result && mysqli_num_rows($result) > 0);
    }
}

if (!function_exists('siIndexExistsByName')) {
    function siIndexExistsByName($connect, $tblName, $indexName)
    {
        $tblName = trim((string) $tblName);
        $indexName = trim((string) $indexName);
        if (!($connect instanceof mysqli) || $tblName === '' || $indexName === '') {
            return false;
        }

        $sql = "SHOW INDEX FROM `" . str_replace('`', '``', $tblName) . "` WHERE Key_name = '" . mysqli_real_escape_string($connect, $indexName) . "'";
        $result = mysqli_query($connect, $sql);
        return ($result instanceof mysqli_result && mysqli_num_rows($result) > 0);
    }
}

if (!function_exists('siEnsureStockOutBatchUsageTable')) {
    function siEnsureStockOutBatchUsageTable($financeConnect)
    {
        static $ready = array();

        if (!($financeConnect instanceof mysqli)) {
            return false;
        }

        $dbName = '';
        $dbResult = mysqli_query($financeConnect, "SELECT DATABASE() AS db_name");
        if ($dbResult && ($dbRow = mysqli_fetch_assoc($dbResult))) {
            $dbName = isset($dbRow['db_name']) ? (string) $dbRow['db_name'] : '';
        }
        $cacheKey = $dbName !== '' ? $dbName : 'default';
        if (isset($ready[$cacheKey]) && $ready[$cacheKey] === true) {
            return true;
        }

        $tblName = STOCK_OUT_BATCH_USAGE;
        $createSql = "CREATE TABLE IF NOT EXISTS `" . $tblName . "` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `stock_out_order_id` INT NOT NULL,
            `stock_out_item_id` INT NOT NULL,
            `stock_in_order_id` INT NOT NULL,
            `stock_in_item_id` INT NOT NULL,
            `product_id` INT NOT NULL DEFAULT 0,
            `package_id` INT NOT NULL DEFAULT 0,
            `used_quantity` INT NOT NULL DEFAULT 0,
            `create_by` VARCHAR(30) DEFAULT NULL,
            `create_date` DATE DEFAULT NULL,
            `create_time` TIME DEFAULT NULL,
            `status` CHAR(1) NOT NULL DEFAULT 'A',
            KEY `idx_sobu_stock_out_order_item` (`stock_out_order_id`, `stock_out_item_id`, `status`),
            KEY `idx_sobu_stock_in_order_item` (`stock_in_order_id`, `stock_in_item_id`, `status`),
            KEY `idx_sobu_product_package_status` (`product_id`, `package_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!mysqli_query($financeConnect, $createSql)) {
            return false;
        }

        $columnSqlMap = array(
            'stock_out_order_id' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `stock_out_order_id` INT NOT NULL AFTER `id`",
            'stock_out_item_id' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `stock_out_item_id` INT NOT NULL AFTER `stock_out_order_id`",
            'stock_in_order_id' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `stock_in_order_id` INT NOT NULL AFTER `stock_out_item_id`",
            'stock_in_item_id' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `stock_in_item_id` INT NOT NULL AFTER `stock_in_order_id`",
            'product_id' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `product_id` INT NOT NULL DEFAULT 0 AFTER `stock_in_item_id`",
            'package_id' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `package_id` INT NOT NULL DEFAULT 0 AFTER `product_id`",
            'used_quantity' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `used_quantity` INT NOT NULL DEFAULT 0 AFTER `package_id`",
            'create_by' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `create_by` VARCHAR(30) DEFAULT NULL AFTER `used_quantity`",
            'create_date' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `create_date` DATE DEFAULT NULL AFTER `create_by`",
            'create_time' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `create_time` TIME DEFAULT NULL AFTER `create_date`",
            'status' => "ALTER TABLE `" . $tblName . "` ADD COLUMN `status` CHAR(1) NOT NULL DEFAULT 'A' AFTER `create_time`",
        );

        foreach ($columnSqlMap as $columnName => $alterSql) {
            if (!siColumnExistsByName($financeConnect, $tblName, $columnName)) {
                if (!mysqli_query($financeConnect, $alterSql)) {
                    return false;
                }
            }
        }

        $indexSqlMap = array(
            'idx_sobu_stock_out_order_item' => "ALTER TABLE `" . $tblName . "` ADD INDEX `idx_sobu_stock_out_order_item` (`stock_out_order_id`, `stock_out_item_id`, `status`)",
            'idx_sobu_stock_in_order_item' => "ALTER TABLE `" . $tblName . "` ADD INDEX `idx_sobu_stock_in_order_item` (`stock_in_order_id`, `stock_in_item_id`, `status`)",
            'idx_sobu_product_package_status' => "ALTER TABLE `" . $tblName . "` ADD INDEX `idx_sobu_product_package_status` (`product_id`, `package_id`, `status`)",
        );

        foreach ($indexSqlMap as $indexName => $alterSql) {
            if (!siIndexExistsByName($financeConnect, $tblName, $indexName)) {
                if (!mysqli_query($financeConnect, $alterSql)) {
                    return false;
                }
            }
        }

        $ready[$cacheKey] = true;
        return true;
    }
}

if (!function_exists('siLoadUserNameMap')) {
    function siLoadUserNameMap($connect)
    {
        $map = array();
        if (!($connect instanceof mysqli)) {
            return $map;
        }

        $result = mysqli_query($connect, "SELECT id, name FROM `" . USR_USER . "` WHERE status='A'");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $userId = isset($row['id']) ? trim((string) $row['id']) : '';
                if ($userId !== '') {
                    $map[$userId] = isset($row['name']) ? (string) $row['name'] : $userId;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('siBuildProductQtyLines')) {
    function siBuildProductQtyLines($items, $productNameMap = array())
    {
        $grouped = array();

        foreach ((array) $items as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            $packageId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
            $qty = isset($item['qty']) ? (int) $item['qty'] : (isset($item['product_quantity']) ? (int) $item['product_quantity'] : 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $key = $productId . '|' . $packageId;
            if (!isset($grouped[$key])) {
                $grouped[$key] = array(
                    'product_id' => $productId,
                    'package_id' => $packageId,
                    'qty' => 0,
                );
            }
            $grouped[$key]['qty'] += $qty;
        }

        $lines = array();
        foreach ($grouped as $row) {
            $productId = (int) $row['product_id'];
            $label = isset($productNameMap[$productId]) && trim((string) $productNameMap[$productId]) !== ''
                ? (string) $productNameMap[$productId]
                : ('Product #' . $productId);
            $lines[] = $label . ' x ' . (int) $row['qty'];
        }

        return $lines;
    }
}

if (!function_exists('siFetchFlatRows')) {
    function siFetchFlatRows($financeConnect, $orderTable, $itemTable, $stockTypeFilter = '')
    {
        $rows = array();
        $stockTypeFilter = trim((string) $stockTypeFilter);
        $whereSql = "WHERE o.status='A'";
        if (strcasecmp($stockTypeFilter, 'Stock Out') === 0) {
            $whereSql .= " AND COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') = 'Stock Out'";
        } else if (strcasecmp($stockTypeFilter, 'Stock In') === 0) {
            $whereSql .= " AND COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') <> 'Stock Out'";
        }

        $sql = "SELECT
                    o.id AS order_id,
                    i.id AS item_id,
                    o.warehouse_id,
                    o.order_number,
                    o.stock_in_date,
                    o.attachment,
                    COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') AS stock_type,
                    o.create_by,
                    o.create_date,
                    o.create_time,
                    o.update_by,
                    o.update_date,
                    o.update_time,
                    i.product_id,
                    i.package_id,
                    i.product_quantity
                FROM `" . $orderTable . "` o
                INNER JOIN `" . $itemTable . "` i ON i.stock_in_order_id=o.id AND i.status='A'
                " . $whereSql . "
                ORDER BY o.id DESC, i.id ASC";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($r = mysqli_fetch_assoc($result)) {
                $productRaw = isset($r['product_id']) ? trim((string) $r['product_id']) : '';
                $qtyRaw = isset($r['product_quantity']) ? trim((string) $r['product_quantity']) : '';
                $productParts = array_map('trim', explode(',', $productRaw));
                $qtyParts = array_map('trim', explode(',', $qtyRaw));
                $max = max(count($productParts), count($qtyParts));

                if ($max <= 1) {
                    $rows[] = array(
                        'order_id' => (int) $r['order_id'],
                        'item_id' => (int) $r['item_id'],
                        'warehouse_id' => (int) $r['warehouse_id'],
                        'order_number' => (string) $r['order_number'],
                        'stock_in_date' => (string) $r['stock_in_date'],
                        'attachment' => (string) (isset($r['attachment']) ? $r['attachment'] : ''),
                        'stock_type' => siNormalizeStockType(isset($r['stock_type']) ? $r['stock_type'] : ''),
                        'create_by' => isset($r['create_by']) ? (string) $r['create_by'] : '',
                        'create_date' => isset($r['create_date']) ? (string) $r['create_date'] : '',
                        'create_time' => isset($r['create_time']) ? (string) $r['create_time'] : '',
                        'update_by' => isset($r['update_by']) ? (string) $r['update_by'] : '',
                        'update_date' => isset($r['update_date']) ? (string) $r['update_date'] : '',
                        'update_time' => isset($r['update_time']) ? (string) $r['update_time'] : '',
                        'product_id' => (int) $productRaw,
                        'package_id' => (int) $r['package_id'],
                        'product_quantity' => (int) $qtyRaw,
                    );
                    continue;
                }

                for ($idx = 0; $idx < $max; $idx++) {
                    $pid = isset($productParts[$idx]) ? (int) $productParts[$idx] : 0;
                    $qty = isset($qtyParts[$idx]) ? (int) $qtyParts[$idx] : 0;
                    if ($pid <= 0 && $qty <= 0) {
                        continue;
                    }
                    $rows[] = array(
                        'order_id' => (int) $r['order_id'],
                        'item_id' => (int) $r['item_id'],
                        'warehouse_id' => (int) $r['warehouse_id'],
                        'order_number' => (string) $r['order_number'],
                        'stock_in_date' => (string) $r['stock_in_date'],
                        'attachment' => (string) (isset($r['attachment']) ? $r['attachment'] : ''),
                        'stock_type' => siNormalizeStockType(isset($r['stock_type']) ? $r['stock_type'] : ''),
                        'create_by' => isset($r['create_by']) ? (string) $r['create_by'] : '',
                        'create_date' => isset($r['create_date']) ? (string) $r['create_date'] : '',
                        'create_time' => isset($r['create_time']) ? (string) $r['create_time'] : '',
                        'update_by' => isset($r['update_by']) ? (string) $r['update_by'] : '',
                        'update_date' => isset($r['update_date']) ? (string) $r['update_date'] : '',
                        'update_time' => isset($r['update_time']) ? (string) $r['update_time'] : '',
                        'product_id' => $pid,
                        'package_id' => (int) $r['package_id'],
                        'product_quantity' => $qty,
                    );
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('siGetStockOutUsageRowsByOrderIds')) {
    function siGetStockOutUsageRowsByOrderIds($financeConnect, $orderIds = array(), $usageTable = '')
    {
        $rows = array();
        if (!($financeConnect instanceof mysqli) || empty($orderIds) || !siEnsureStockOutBatchUsageTable($financeConnect)) {
            return $rows;
        }

        $usageTable = trim((string) $usageTable);
        if ($usageTable === '') {
            $usageTable = STOCK_OUT_BATCH_USAGE;
        }

        $cleanIds = array();
        foreach ((array) $orderIds as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId > 0) {
                $cleanIds[$orderId] = $orderId;
            }
        }

        if (empty($cleanIds)) {
            return $rows;
        }

        $sql = "SELECT
                    u.id,
                    u.stock_out_order_id,
                    u.stock_out_item_id,
                    u.stock_in_order_id,
                    u.stock_in_item_id,
                    u.product_id,
                    u.package_id,
                    u.used_quantity,
                    u.create_by,
                    u.create_date,
                    u.create_time,
                    u.status,
                    o.order_number AS stock_in_order_number,
                    o.stock_in_date AS stock_in_order_date
                FROM `" . $usageTable . "` u
                INNER JOIN `stock_in_order` o ON o.id = u.stock_in_order_id
                WHERE u.status='A'
                  AND u.stock_out_order_id IN (" . implode(',', $cleanIds) . ")
                ORDER BY u.stock_out_order_id ASC, u.stock_out_item_id ASC, o.stock_in_date ASC, u.stock_in_order_id ASC, u.stock_in_item_id ASC";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('siGetStockInUsedQtyMap')) {
    function siGetStockInUsedQtyMap($financeConnect, $stockInOrderId)
    {
        $map = array();
        $stockInOrderId = (int) $stockInOrderId;
        if ($stockInOrderId <= 0 || !($financeConnect instanceof mysqli) || !siEnsureStockOutBatchUsageTable($financeConnect)) {
            return $map;
        }

        $sql = "SELECT stock_in_item_id, SUM(used_quantity) AS used_qty
            FROM `" . STOCK_OUT_BATCH_USAGE . "`
            WHERE stock_in_order_id = " . $stockInOrderId . "
              AND status='A'
            GROUP BY stock_in_item_id";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $itemId = isset($row['stock_in_item_id']) ? (int) $row['stock_in_item_id'] : 0;
                if ($itemId > 0) {
                    $map[$itemId] = isset($row['used_qty']) ? (int) $row['used_qty'] : 0;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('siGetAvailableFifoStockInBatches')) {
    function siGetAvailableFifoStockInBatches($financeConnect, $warehouseId, $productId, $packageId = 0, $excludeStockOutOrderId = 0)
    {
        $batches = array();
        $warehouseId = (int) $warehouseId;
        $productId = (int) $productId;
        $packageId = (int) $packageId;
        $excludeStockOutOrderId = (int) $excludeStockOutOrderId;

        if (!($financeConnect instanceof mysqli) || $productId <= 0 || !siEnsureStockOutBatchUsageTable($financeConnect)) {
            return $batches;
        }

        $usedQtyExpr = $excludeStockOutOrderId > 0
            ? "SUM(CASE WHEN u.stock_out_order_id <> " . $excludeStockOutOrderId . " THEN u.used_quantity ELSE 0 END)"
            : "SUM(u.used_quantity)";

        $sql = "SELECT
                    i.id AS stock_in_item_id,
                    i.stock_in_order_id,
                    o.order_number,
                    o.stock_in_date,
                    i.product_id,
                    i.package_id,
                    CAST(IFNULL(i.product_quantity, 0) AS SIGNED) AS original_quantity,
                    IFNULL(" . $usedQtyExpr . ", 0) AS used_quantity
                FROM `stock_in_order_item` i
                INNER JOIN `stock_in_order` o ON o.id = i.stock_in_order_id
                LEFT JOIN `" . STOCK_OUT_BATCH_USAGE . "` u
                    ON u.stock_in_item_id = i.id
                   AND u.status = 'A'
                WHERE i.status='A'
                  AND o.status='A'
                  AND COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') <> 'Stock Out'
                  AND i.product_id = " . $productId;
        if ($warehouseId > 0) {
            $sql .= " AND o.warehouse_id = " . $warehouseId;
        }
        if ($packageId > 0) {
            $sql .= " AND i.package_id = " . $packageId;
        }
        $sql .= " GROUP BY i.id, i.stock_in_order_id, o.order_number, o.stock_in_date, i.product_id, i.package_id, i.product_quantity
                  HAVING (CAST(IFNULL(i.product_quantity, 0) AS SIGNED) - IFNULL(" . $usedQtyExpr . ", 0)) > 0
                  ORDER BY o.stock_in_date ASC, o.id ASC, i.id ASC";

        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $originalQty = isset($row['original_quantity']) ? (int) $row['original_quantity'] : 0;
                $usedQty = isset($row['used_quantity']) ? (int) $row['used_quantity'] : 0;
                $availableQty = $originalQty - $usedQty;
                if ($availableQty <= 0) {
                    continue;
                }

                $row['available_quantity'] = $availableQty;
                $batches[] = $row;
            }
        }

        return $batches;
    }
}

if (!function_exists('siAllocateStockOutQuantityAcrossFifoBatches')) {
    function siAllocateStockOutQuantityAcrossFifoBatches($financeConnect, $warehouseId, $productId, $requiredQty, $packageId = 0, $excludeStockOutOrderId = 0, $productLabel = '', $warehouseLabel = '')
    {
        $warehouseId = (int) $warehouseId;
        $productId = (int) $productId;
        $requiredQty = (int) $requiredQty;
        $packageId = (int) $packageId;
        $excludeStockOutOrderId = (int) $excludeStockOutOrderId;

        if ($productId <= 0 || $requiredQty <= 0) {
            return array();
        }

        $batches = siGetAvailableFifoStockInBatches($financeConnect, $warehouseId, $productId, $packageId, $excludeStockOutOrderId);
        $availableQty = 0;
        foreach ($batches as $batch) {
            $availableQty += isset($batch['available_quantity']) ? (int) $batch['available_quantity'] : 0;
        }

        if ($availableQty < $requiredQty) {
            $message = ($warehouseLabel !== '' ? $warehouseLabel . ' ' : '') . 'not enough warehouse stock';
            if ($productLabel !== '') {
                $message .= ' for ' . $productLabel;
            } else {
                $message .= ' for product #' . $productId;
            }
            $message .= '.';
            throw new Exception($message);
        }

        $allocations = array();
        $remainingQty = $requiredQty;
        foreach ($batches as $batch) {
            if ($remainingQty <= 0) {
                break;
            }

            $availableBatchQty = isset($batch['available_quantity']) ? (int) $batch['available_quantity'] : 0;
            if ($availableBatchQty <= 0) {
                continue;
            }

            $usedQty = min($remainingQty, $availableBatchQty);
            $allocations[] = array(
                'stock_in_order_id' => isset($batch['stock_in_order_id']) ? (int) $batch['stock_in_order_id'] : 0,
                'stock_in_item_id' => isset($batch['stock_in_item_id']) ? (int) $batch['stock_in_item_id'] : 0,
                'stock_in_order_number' => isset($batch['order_number']) ? (string) $batch['order_number'] : '',
                'stock_in_date' => isset($batch['stock_in_date']) ? (string) $batch['stock_in_date'] : '',
                'product_id' => $productId,
                'package_id' => $packageId,
                'used_quantity' => $usedQty,
            );
            $remainingQty -= $usedQty;
        }

        if ($remainingQty > 0) {
            throw new Exception('Unable to allocate stock out quantity across FIFO batches.');
        }

        return $allocations;
    }
}

if (!function_exists('siInsertStockOutBatchUsageRows')) {
    function siInsertStockOutBatchUsageRows($financeConnect, $stockOutOrderId, $stockOutItemId, $allocations, $actorUserId = 'SYSTEM')
    {
        $stockOutOrderId = (int) $stockOutOrderId;
        $stockOutItemId = (int) $stockOutItemId;
        $actorUserId = trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM';

        if ($stockOutOrderId <= 0 || $stockOutItemId <= 0 || !($financeConnect instanceof mysqli) || !siEnsureStockOutBatchUsageTable($financeConnect)) {
            throw new Exception('Failed to prepare stock out batch usage.');
        }

        $safeActor = mysqli_real_escape_string($financeConnect, $actorUserId);
        foreach ((array) $allocations as $allocation) {
            $stockInOrderId = isset($allocation['stock_in_order_id']) ? (int) $allocation['stock_in_order_id'] : 0;
            $stockInItemId = isset($allocation['stock_in_item_id']) ? (int) $allocation['stock_in_item_id'] : 0;
            $productId = isset($allocation['product_id']) ? (int) $allocation['product_id'] : 0;
            $packageId = isset($allocation['package_id']) ? (int) $allocation['package_id'] : 0;
            $usedQty = isset($allocation['used_quantity']) ? (int) $allocation['used_quantity'] : 0;
            if ($stockInOrderId <= 0 || $stockInItemId <= 0 || $productId <= 0 || $usedQty <= 0) {
                continue;
            }

            $insertSql = "INSERT INTO `" . STOCK_OUT_BATCH_USAGE . "`
                (`stock_out_order_id`, `stock_out_item_id`, `stock_in_order_id`, `stock_in_item_id`, `product_id`, `package_id`, `used_quantity`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                (" . $stockOutOrderId . ", " . $stockOutItemId . ", " . $stockInOrderId . ", " . $stockInItemId . ", " . $productId . ", " . $packageId . ", " . $usedQty . ", '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($financeConnect, $insertSql)) {
                throw new Exception('Failed to save stock out batch usage.');
            }
        }
    }
}

if (!function_exists('siDeactivateStockOutBatchUsageRowsByOrder')) {
    function siDeactivateStockOutBatchUsageRowsByOrder($financeConnect, $stockOutOrderId)
    {
        $stockOutOrderId = (int) $stockOutOrderId;
        if ($stockOutOrderId <= 0 || !($financeConnect instanceof mysqli) || !siEnsureStockOutBatchUsageTable($financeConnect)) {
            return false;
        }

        return (bool) mysqli_query(
            $financeConnect,
            "UPDATE `" . STOCK_OUT_BATCH_USAGE . "` SET status='D' WHERE stock_out_order_id = " . $stockOutOrderId . " AND status='A'"
        );
    }
}

if (!function_exists('siBuildSourceOrderLinkMap')) {
    function siBuildSourceOrderLinkMap($cmsConnect, $financeConnect, $orderNumbers)
    {
        $map = array();
        $seen = array();

        foreach ((array) $orderNumbers as $orderNumber) {
            $orderNumber = trim((string) $orderNumber);
            if ($orderNumber === '' || isset($seen[$orderNumber])) {
                continue;
            }
            $seen[$orderNumber] = true;

            $orderRow = shopeeOmsLoadOrderByCodeAnyPlatform($cmsConnect, $financeConnect, $orderNumber);
            if (!empty($orderRow)) {
                $platform = shopeeOmsGetOrderSourcePlatform($orderRow);
                $sourceOrderId = isset($orderRow['id']) ? (int) $orderRow['id'] : 0;
                $map[$orderNumber] = array(
                    'url' => $sourceOrderId > 0 ? shopeeOmsGetOrderSourceViewUrl($platform, $sourceOrderId) : '',
                    'platform' => $platform,
                    'platform_label' => isset($orderRow['__oms_platform_label']) ? (string) $orderRow['__oms_platform_label'] : ucfirst($platform),
                    'order_id' => $sourceOrderId,
                );
                continue;
            }

            $map[$orderNumber] = array(
                'url' => '',
                'platform' => '',
                'platform_label' => '',
                'order_id' => 0,
            );
        }

        return $map;
    }
}

if (!function_exists('siBuildStockOutBatchUsageLine')) {
    function siBuildStockOutBatchUsageLine($usageRow, $productNameMap = array())
    {
        $stockInOrderNumber = trim((string) (isset($usageRow['stock_in_order_number']) ? $usageRow['stock_in_order_number'] : ''));
        $stockInOrderId = isset($usageRow['stock_in_order_id']) ? (int) $usageRow['stock_in_order_id'] : 0;
        $productId = isset($usageRow['product_id']) ? (int) $usageRow['product_id'] : 0;
        $usedQty = isset($usageRow['used_quantity']) ? (int) $usageRow['used_quantity'] : 0;
        $stockInDate = trim((string) (isset($usageRow['stock_in_order_date']) ? $usageRow['stock_in_order_date'] : (isset($usageRow['stock_in_date']) ? $usageRow['stock_in_date'] : '')));

        $label = $stockInOrderNumber !== '' ? $stockInOrderNumber : (string) $stockInOrderId;
        $productName = isset($productNameMap[$productId]) && trim((string) $productNameMap[$productId]) !== ''
            ? (string) $productNameMap[$productId]
            : ('Product #' . $productId);

        return 'Stock In #' . $label . ' / ' . $stockInDate . ' / ' . $productName . ' x ' . $usedQty;
    }
}

if (!function_exists('siDeductWarehouseInventoryQtyMap')) {
    function siDeductWarehouseInventoryQtyMap($financeConnect, $warehouseId, $productQtyMap, $actorUserId = 'SYSTEM')
    {
        $warehouseId = (int) $warehouseId;
        $actorUserId = trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM';

        foreach ((array) $productQtyMap as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $selectSql = "SELECT i.id, i.product_quantity
                FROM `stock_in_order_item` i
                INNER JOIN `stock_in_order` o ON o.id = i.stock_in_order_id
                WHERE i.status = 'A'
                  AND o.status = 'A'
                  AND COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') <> 'Stock Out'
                  AND i.product_id = " . $productId . "
                  AND i.product_quantity > 0";
            if ($warehouseId > 0) {
                $selectSql .= " AND o.warehouse_id = " . $warehouseId;
            }
            $selectSql .= " ORDER BY o.stock_in_date ASC, o.id ASC, i.id ASC";

            $selectResult = mysqli_query($financeConnect, $selectSql);
            $stockRows = array();
            $availableQty = 0;
            if ($selectResult) {
                while ($row = mysqli_fetch_assoc($selectResult)) {
                    $itemId = isset($row['id']) ? (int) $row['id'] : 0;
                    $itemQty = isset($row['product_quantity']) ? (int) $row['product_quantity'] : 0;
                    if ($itemId > 0 && $itemQty > 0) {
                        $stockRows[] = array(
                            'id' => $itemId,
                            'qty' => $itemQty,
                        );
                        $availableQty += $itemQty;
                    }
                }
            }

            if ($availableQty < $qty) {
                throw new Exception('Warehouse stock is not enough for product #' . $productId . '.');
            }

            $safeActor = mysqli_real_escape_string($financeConnect, $actorUserId);
            $remainingQty = $qty;
            foreach ($stockRows as $stockRow) {
                if ($remainingQty <= 0) {
                    break;
                }

                $itemId = (int) $stockRow['id'];
                $currentQty = (int) $stockRow['qty'];
                $deductQty = min($remainingQty, $currentQty);
                $newQty = $currentQty - $deductQty;

                $updateSql = "UPDATE `stock_in_order_item`
                    SET `product_quantity` = " . $newQty . ",
                        `update_by` = '" . $safeActor . "',
                        `update_date` = CURDATE(),
                        `update_time` = CURTIME()
                    WHERE id = " . $itemId . "
                      AND status = 'A'
                    LIMIT 1";
                if (!mysqli_query($financeConnect, $updateSql)) {
                    throw new Exception('Failed to deduct warehouse stock.');
                }

                $remainingQty -= $deductQty;
            }
        }

        return true;
    }
}

if (!function_exists('siRestoreWarehouseInventoryQtyMap')) {
    function siRestoreWarehouseInventoryQtyMap($financeConnect, $warehouseId, $productQtyMap, $actorUserId = 'SYSTEM', $referenceOrderNumber = '')
    {
        $warehouseId = (int) $warehouseId;
        $actorUserId = trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM';
        $safeActor = mysqli_real_escape_string($financeConnect, $actorUserId);
        $referenceOrderNumber = trim((string) $referenceOrderNumber);

        foreach ((array) $productQtyMap as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            $selectSql = "SELECT i.id, i.product_quantity
                FROM `stock_in_order_item` i
                INNER JOIN `stock_in_order` o ON o.id = i.stock_in_order_id
                WHERE i.status = 'A'
                  AND o.status = 'A'
                  AND COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') <> 'Stock Out'
                  AND i.product_id = " . $productId;
            if ($warehouseId > 0) {
                $selectSql .= " AND o.warehouse_id = " . $warehouseId;
            }
            $selectSql .= " ORDER BY o.stock_in_date DESC, o.id DESC, i.id DESC LIMIT 1";

            $selectResult = mysqli_query($financeConnect, $selectSql);
            if ($selectResult && ($row = mysqli_fetch_assoc($selectResult))) {
                $itemId = isset($row['id']) ? (int) $row['id'] : 0;
                $currentQty = isset($row['product_quantity']) ? (int) $row['product_quantity'] : 0;
                if ($itemId > 0) {
                    $newQty = $currentQty + $qty;
                    $updateSql = "UPDATE `stock_in_order_item`
                        SET `product_quantity` = " . $newQty . ",
                            `update_by` = '" . $safeActor . "',
                            `update_date` = CURDATE(),
                            `update_time` = CURTIME()
                        WHERE id = " . $itemId . "
                          AND status = 'A'
                        LIMIT 1";
                    if (!mysqli_query($financeConnect, $updateSql)) {
                        throw new Exception('Failed to restore warehouse stock.');
                    }
                    continue;
                }
            }

            $adjustOrderNumber = 'STOCK-OUT-EDIT-RESTORE-' . ($referenceOrderNumber !== '' ? preg_replace('/[^A-Za-z0-9\-]/', '-', $referenceOrderNumber) : date('YmdHis')) . '-' . mt_rand(1000, 9999);
            $safeAdjustOrderNumber = mysqli_real_escape_string($financeConnect, $adjustOrderNumber);
            $insertOrderSql = "INSERT INTO `stock_in_order`
                (`warehouse_id`, `order_number`, `stock_in_date`, `attachment`, `stock_type`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                (" . $warehouseId . ", '" . $safeAdjustOrderNumber . "', CURDATE(), '', 'Stock In', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($financeConnect, $insertOrderSql)) {
                throw new Exception('Failed to restore warehouse stock.');
            }

            $stockInOrderId = (int) mysqli_insert_id($financeConnect);
            $insertItemSql = "INSERT INTO `stock_in_order_item`
                (`stock_in_order_id`, `product_id`, `package_id`, `product_quantity`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                (" . $stockInOrderId . ", " . $productId . ", 0, " . $qty . ", '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
            if (!mysqli_query($financeConnect, $insertItemSql)) {
                throw new Exception('Failed to restore warehouse stock.');
            }
        }

        return true;
    }
}

if (!function_exists('siSaveOrder')) {
    function siSaveOrder($financeConnect, $orderTable, $itemTable, $warehouseId, $stockInDate, $orderNumber, $items, $attachmentPath = '')
    {
        $warehouseId = (int) $warehouseId;
        $orderNumber = trim((string) $orderNumber);
        $stockInDate = trim((string) $stockInDate);
        $attachmentPath = siAttachmentEncodeList($attachmentPath);

        if ($warehouseId <= 0 || $orderNumber === '' || $stockInDate === '' || $attachmentPath === '' || count($items) === 0) {
            return array(false, 'Missing required fields.');
        }

        mysqli_begin_transaction($financeConnect);

        try {
            $safeOrderNumber = mysqli_real_escape_string($financeConnect, $orderNumber);
            $safeDate = mysqli_real_escape_string($financeConnect, $stockInDate);
            $safeAttachment = mysqli_real_escape_string($financeConnect, $attachmentPath);

            $insertOrderSql = "INSERT INTO `" . $orderTable . "`
                (warehouse_id, order_number, stock_in_date, attachment, stock_type, create_by, create_date, create_time, status)
                VALUES
                ('" . $warehouseId . "', '" . $safeOrderNumber . "', '" . $safeDate . "', '" . $safeAttachment . "', 'Stock In', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";

            if (!mysqli_query($financeConnect, $insertOrderSql)) {
                throw new Exception('Failed to save stock in order.');
            }

            $stockInOrderId = (int) mysqli_insert_id($financeConnect);

            foreach ($items as $item) {
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                $packageId = isset($item['package_id']) ? (int) $item['package_id'] : 0;
                $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $insertItemSql = "INSERT INTO `" . $itemTable . "`
                    (stock_in_order_id, product_id, package_id, product_quantity, create_by, create_date, create_time, status)
                    VALUES
                    ('" . $stockInOrderId . "', '" . $productId . "', '" . $packageId . "', '" . $qty . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";

                if (!mysqli_query($financeConnect, $insertItemSql)) {
                    throw new Exception('Failed to save stock in item.');
                }
            }

            mysqli_commit($financeConnect);
            return array(true, 'Stock In saved successfully.');
        } catch (Exception $ex) {
            mysqli_rollback($financeConnect);
            return array(false, $ex->getMessage());
        }
    }
}

if (!function_exists('siFetchFlatRows')) {
    function siFetchFlatRows($financeConnect, $orderTable, $itemTable)
    {
        $rows = array();
        $sql = "SELECT
                    o.id AS order_id,
                    i.id AS item_id,
                    o.warehouse_id,
                    o.order_number,
                    o.stock_in_date,
                    o.attachment,
                    COALESCE(NULLIF(TRIM(o.stock_type), ''), 'Stock In') AS stock_type,
                    o.create_by,
                    o.create_date,
                    o.create_time,
                    o.update_by,
                    o.update_date,
                    o.update_time,
                    i.product_id,
                    i.package_id,
                    i.product_quantity
                FROM `" . $orderTable . "` o
                INNER JOIN `" . $itemTable . "` i ON i.stock_in_order_id=o.id AND i.status='A'
                WHERE o.status='A'
                ORDER BY o.id DESC, i.id ASC";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($r = mysqli_fetch_assoc($result)) {
                $productRaw = isset($r['product_id']) ? trim((string) $r['product_id']) : '';
                $qtyRaw = isset($r['product_quantity']) ? trim((string) $r['product_quantity']) : '';
                $productParts = array_map('trim', explode(',', $productRaw));
                $qtyParts = array_map('trim', explode(',', $qtyRaw));
                $max = max(count($productParts), count($qtyParts));

                if ($max <= 1) {
                    $rows[] = array(
                        'order_id' => (int) $r['order_id'],
                        'item_id' => (int) $r['item_id'],
                        'warehouse_id' => (int) $r['warehouse_id'],
                        'order_number' => (string) $r['order_number'],
                        'stock_in_date' => (string) $r['stock_in_date'],
                        'attachment' => (string) (isset($r['attachment']) ? $r['attachment'] : ''),
                        'stock_type' => siNormalizeStockType(isset($r['stock_type']) ? $r['stock_type'] : ''),
                        'create_by' => isset($r['create_by']) ? (string) $r['create_by'] : '',
                        'create_date' => isset($r['create_date']) ? (string) $r['create_date'] : '',
                        'create_time' => isset($r['create_time']) ? (string) $r['create_time'] : '',
                        'update_by' => isset($r['update_by']) ? (string) $r['update_by'] : '',
                        'update_date' => isset($r['update_date']) ? (string) $r['update_date'] : '',
                        'update_time' => isset($r['update_time']) ? (string) $r['update_time'] : '',
                        'product_id' => (int) $productRaw,
                        'package_id' => (int) $r['package_id'],
                        'product_quantity' => (int) $qtyRaw,
                    );
                    continue;
                }

                for ($idx = 0; $idx < $max; $idx++) {
                    $pid = isset($productParts[$idx]) ? (int) $productParts[$idx] : 0;
                    $qty = isset($qtyParts[$idx]) ? (int) $qtyParts[$idx] : 0;
                    if ($pid <= 0 && $qty <= 0) {
                        continue;
                    }
                    $rows[] = array(
                        'order_id' => (int) $r['order_id'],
                        'item_id' => (int) $r['item_id'],
                        'warehouse_id' => (int) $r['warehouse_id'],
                        'order_number' => (string) $r['order_number'],
                        'stock_in_date' => (string) $r['stock_in_date'],
                        'attachment' => (string) (isset($r['attachment']) ? $r['attachment'] : ''),
                        'stock_type' => siNormalizeStockType(isset($r['stock_type']) ? $r['stock_type'] : ''),
                        'create_by' => isset($r['create_by']) ? (string) $r['create_by'] : '',
                        'create_date' => isset($r['create_date']) ? (string) $r['create_date'] : '',
                        'create_time' => isset($r['create_time']) ? (string) $r['create_time'] : '',
                        'update_by' => isset($r['update_by']) ? (string) $r['update_by'] : '',
                        'update_date' => isset($r['update_date']) ? (string) $r['update_date'] : '',
                        'update_time' => isset($r['update_time']) ? (string) $r['update_time'] : '',
                        'product_id' => $pid,
                        'package_id' => (int) $r['package_id'],
                        'product_quantity' => $qty,
                    );
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('siExportExcel')) {
    function siExportExcel($rows, $warehouseNameMap, $productNameMap)
    {
        if (!class_exists('CodexWorld\\PhpXlsxGenerator')) {
            include_once ROOT . '/header/PhpXlsxGenerator/PhpXlsxGenerator.php';
        }

        $excelData = array(
            array('Item ID', 'Warehouse', 'Stock In Date', 'Order Number', 'Product Name', 'Product Quantity')
        );

        foreach ($rows as $row) {
            $warehouseName = isset($warehouseNameMap[(int) $row['warehouse_id']]) ? $warehouseNameMap[(int) $row['warehouse_id']] : '';
            $productName = isset($productNameMap[(int) $row['product_id']]) ? $productNameMap[(int) $row['product_id']] : '';

            $excelData[] = array(
                (string) $row['item_id'],
                (string) $warehouseName,
                (string) $row['stock_in_date'],
                (string) $row['order_number'],
                (string) $productName,
                (string) $row['product_quantity'],
            );
        }

        $fileName = 'stock_in_export_' . date('Ymd_His') . '.xlsx';
        $xlsx = \CodexWorld\PhpXlsxGenerator::fromArray($excelData, 'Stock In');
        $xlsx->downloadAs($fileName);
        exit;
    }
}

if (!function_exists('siParseExcelLikeRows')) {
    function siParseExcelLikeRows($filePath, $fileName = '')
    {
        $rows = array();

        $ext = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            $sharedStringsXml = false;
            $sheetXml = false;

            if (class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === true) {
                    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $entry = $zip->getNameIndex($i);
                        if (preg_match('/xl\/worksheets\/sheet\d+\.xml/i', (string) $entry)) {
                            $sheetXml = $zip->getFromName($entry);
                            break;
                        }
                    }
                    $zip->close();
                }
            }

            // Windows fallback when ZipArchive is unavailable in runtime.
            if (!$sheetXml) {
                $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'si_xlsx_' . uniqid();
                if (@mkdir($tempDir)) {
                    $cmd = 'tar -xf ' . escapeshellarg($filePath) . ' -C ' . escapeshellarg($tempDir) . ' 2>&1';
                    @shell_exec($cmd);

                    $ssPath = $tempDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml';
                    if (file_exists($ssPath)) {
                        $sharedStringsXml = @file_get_contents($ssPath);
                    }

                    $wsDir = $tempDir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR;
                    if (is_dir($wsDir)) {
                        $wsFiles = @scandir($wsDir);
                        if (is_array($wsFiles)) {
                            foreach ($wsFiles as $wsFile) {
                                if (preg_match('/^sheet\d+\.xml$/i', (string) $wsFile)) {
                                    $sheetXml = @file_get_contents($wsDir . $wsFile);
                                    break;
                                }
                            }
                        }
                    }

                    $deleteDir = function ($dir) use (&$deleteDir) {
                        if (!is_dir($dir)) return;
                        $items = @scandir($dir);
                        if (!is_array($items)) return;
                        foreach ($items as $item) {
                            if ($item === '.' || $item === '..') continue;
                            $path = $dir . DIRECTORY_SEPARATOR . $item;
                            if (is_dir($path)) {
                                $deleteDir($path);
                            } else {
                                @unlink($path);
                            }
                        }
                        @rmdir($dir);
                    };
                    $deleteDir($tempDir);
                }
            }

            if ($sheetXml) {
                $sharedStrings = array();
                if ($sharedStringsXml !== false) {
                    $ssObj = @simplexml_load_string($sharedStringsXml);
                    if ($ssObj && isset($ssObj->si)) {
                        foreach ($ssObj->si as $si) {
                            $val = '';
                            if (isset($si->t)) {
                                $val .= (string) $si->t;
                            } elseif (isset($si->r)) {
                                foreach ($si->r as $r) {
                                    if (isset($r->t)) {
                                        $val .= (string) $r->t;
                                    }
                                }
                            }
                            $sharedStrings[] = $val;
                        }
                    }
                }

                $sheetObj = @simplexml_load_string($sheetXml);
                if ($sheetObj && isset($sheetObj->sheetData->row)) {
                    $matrix = array();
                    foreach ($sheetObj->sheetData->row as $row) {
                        $rowData = array();
                        $colIndex = 0;
                        foreach ($row->c as $c) {
                            $rAttr = (string) $c['r'];
                            if ($rAttr !== '') {
                                $letters = preg_replace('/[0-9]/', '', $rAttr);
                                $idx = 0;
                                $len = strlen((string) $letters);
                                for ($j = 0; $j < $len; $j++) {
                                    $idx = ($idx * 26) + (ord($letters[$j]) - 64);
                                }
                                $idx -= 1;
                            } else {
                                $idx = $colIndex;
                            }

                            while ($colIndex < $idx) {
                                $rowData[$colIndex] = '';
                                $colIndex++;
                            }

                            $v = (string) $c->v;
                            $t = isset($c['t']) ? (string) $c['t'] : '';
                            if ($t === 's') {
                                $v = isset($sharedStrings[(int) $v]) ? $sharedStrings[(int) $v] : '';
                            } elseif ($t === 'inlineStr') {
                                $v = isset($c->is->t) ? (string) $c->is->t : '';
                            }

                            $rowData[$colIndex] = $v;
                            $colIndex++;
                        }
                        $matrix[] = $rowData;
                    }

                    if (count($matrix) > 0) {
                        $header = isset($matrix[0]) ? $matrix[0] : array();
                        for ($i = 1; $i < count($matrix); $i++) {
                            $cells = isset($matrix[$i]) ? $matrix[$i] : array();
                            $assoc = array();
                            foreach ($header as $idx => $name) {
                                $key = strtolower(trim((string) $name));
                                if ($key === '') continue;
                                $assoc[$key] = isset($cells[$idx]) ? trim((string) $cells[$idx]) : '';
                            }
                            if (count($assoc) > 0) {
                                $rows[] = $assoc;
                            }
                        }
                        return $rows;
                    }
                }
            }
        }

        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return $rows;
        }

        if (stripos($content, '<table') !== false) {
            $prev = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (@$dom->loadHTML($content)) {
                $trs = $dom->getElementsByTagName('tr');
                $header = array();
                foreach ($trs as $tr) {
                    $cells = array();
                    foreach ($tr->childNodes as $child) {
                        if ($child->nodeName === 'th' || $child->nodeName === 'td') {
                            $cells[] = trim((string) $child->textContent);
                        }
                    }
                    if (count($cells) === 0) {
                        continue;
                    }
                    if (count($header) === 0) {
                        $header = $cells;
                        continue;
                    }
                    $assoc = array();
                    foreach ($header as $idx => $name) {
                        $assoc[strtolower(trim((string) $name))] = isset($cells[$idx]) ? trim((string) $cells[$idx]) : '';
                    }
                    $rows[] = $assoc;
                }
            }
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return $rows;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if (!$lines || count($lines) < 2) {
            return $rows;
        }

        $header = preg_split('/\t/', (string) $lines[0]);
        if (!is_array($header) || count($header) === 0) {
            return $rows;
        }

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim((string) $lines[$i]);
            if ($line === '') {
                continue;
            }
            $cells = preg_split('/\t/', $line);
            $assoc = array();
            foreach ($header as $idx => $name) {
                $assoc[strtolower(trim((string) $name))] = isset($cells[$idx]) ? trim((string) $cells[$idx]) : '';
            }
            $rows[] = $assoc;
        }

        return $rows;
    }
}

if (!function_exists('siFindOrderIdByFields')) {
    function siFindOrderIdByFields($financeConnect, $orderTable, $warehouseId, $stockInDate, $orderNumber)
    {
        $warehouseId = (int) $warehouseId;
        $safeDate = mysqli_real_escape_string($financeConnect, (string) $stockInDate);
        $safeOrderNo = mysqli_real_escape_string($financeConnect, (string) $orderNumber);
        $sql = "SELECT id FROM `" . $orderTable . "` WHERE status='A' AND warehouse_id='" . $warehouseId . "' AND stock_in_date='" . $safeDate . "' AND order_number='" . $safeOrderNo . "' AND COALESCE(NULLIF(TRIM(stock_type), ''), 'Stock In')='Stock In' LIMIT 1";
        $result = mysqli_query($financeConnect, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            return (int) $row['id'];
        }
        return 0;
    }
}

if (!function_exists('orderDeleteApprovalGetTableName')) {
    function orderDeleteApprovalGetTableName()
    {
        return defined('ORDER_DELETE_APPROVAL_REQUEST') ? ORDER_DELETE_APPROVAL_REQUEST : 'order_delete_approval_request';
    }
}

if (!function_exists('orderDeleteApprovalGetModuleConfigs')) {
    function orderDeleteApprovalGetModuleConfigs()
    {
        return array(
            'website_order_request' => array(
                'title' => 'Website Order Request',
                'platform' => 'website',
                'source_db' => 'finance',
                'source_table' => defined('WEB_ORDER_REQ') ? WEB_ORDER_REQ : 'website_order_request',
                'page_path' => ROUTE_FINANCE_WEBSITE_ORDER_REQUEST,
                'table_path' => ROUTE_FINANCE_WEBSITE_ORDER_REQUEST_TABLE,
            ),
            'facebook_order_request' => array(
                'title' => 'Facebook Order Request',
                'platform' => 'facebook',
                'source_db' => 'finance',
                'source_table' => defined('FB_ORDER_REQ') ? FB_ORDER_REQ : 'facebook_order_request',
                'page_path' => ROUTE_FINANCE_FB_ORDER_REQ,
                'table_path' => ROUTE_FINANCE_FB_ORDER_REQ_TABLE,
            ),
            'lazada_order_request' => array(
                'title' => 'Lazada Order Request',
                'platform' => 'lazada',
                'source_db' => 'cms',
                'source_table' => defined('LAZADA_ORDER_REQ') ? LAZADA_ORDER_REQ : 'lazada_order_request',
                'page_path' => ROUTE_FINANCE_LAZADA_ORDER_REQ,
                'table_path' => ROUTE_FINANCE_LAZADA_ORDER_REQ_TABLE,
            ),
            'shopee_order_request' => array(
                'title' => 'Shopee Order Request',
                'platform' => 'shopee',
                'source_db' => 'finance',
                'source_table' => defined('SHOPEE_SG_ORDER_REQ') ? SHOPEE_SG_ORDER_REQ : 'shopee_sg_order_request',
                'page_path' => ROUTE_SHOPEE_ORDER_REQ,
                'table_path' => ROUTE_SHOPEE_ORDER_REQ_TABLE,
            ),
            'stock_order_request' => array(
                'title' => 'Stock Order Request',
                'platform' => 'stock',
                'source_db' => 'finance',
                'source_table' => defined('STOCK_ORDER_REQ') ? STOCK_ORDER_REQ : 'stock_order_request',
                'page_path' => ROUTE_STOCK_ORDER_REQUEST,
                'table_path' => ROUTE_STOCK_ORDER_REQUEST_TABLE,
            ),
        );
    }
}

if (!function_exists('orderDeleteApprovalGetModuleConfig')) {
    function orderDeleteApprovalGetModuleConfig($moduleKey)
    {
        $moduleKey = trim((string) $moduleKey);
        $configs = orderDeleteApprovalGetModuleConfigs();
        return isset($configs[$moduleKey]) ? $configs[$moduleKey] : array();
    }
}

if (!function_exists('orderDeleteApprovalInitPageState')) {
    function orderDeleteApprovalInitPageState()
    {
        $approvalMode = input('approval_mode') == '1' || post('approval_mode') == '1';
        $dataId = input('id');
        if ($dataId === '' || $dataId === null) {
            $dataId = post('id');
        }

        $act = input('act');
        if ($act === '' || $act === null) {
            $act = post('act');
        }

        if ($approvalMode) {
            $act = '';
        }

        return array(
            'approval_mode' => $approvalMode,
            'request_id' => (int) (!empty(input('approval_request_id')) ? input('approval_request_id') : post('approval_request_id')),
            'data_id' => $dataId,
            'act' => $act,
            'panel_html' => '',
        );
    }
}

if (!function_exists('orderDeleteApprovalGetBaseUrl')) {
    function orderDeleteApprovalGetBaseUrl()
    {
        return defined('SITEURL') ? rtrim((string) SITEURL, '/') : '';
    }
}

if (!function_exists('orderDeleteApprovalNormalizeUserIds')) {
    function orderDeleteApprovalNormalizeUserIds($userIds)
    {
        $normalized = array();
        foreach ((array) $userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0) {
                $normalized[$userId] = $userId;
            }
        }

        return array_values($normalized);
    }
}

if (!function_exists('orderDeleteApprovalParseSupervisorIds')) {
    function orderDeleteApprovalParseSupervisorIds($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return array();
        }

        return orderDeleteApprovalNormalizeUserIds(array_map('trim', explode(',', $value)));
    }
}

if (!function_exists('orderDeleteApprovalSerializeSupervisorIds')) {
    function orderDeleteApprovalSerializeSupervisorIds($userIds)
    {
        return implode(',', orderDeleteApprovalNormalizeUserIds($userIds));
    }
}

if (!function_exists('orderDeleteApprovalBuildPageUrl')) {
    function orderDeleteApprovalBuildPageUrl($moduleKey, $sourceOrderId, $requestId = 0, $approvalMode = false)
    {
        $config = orderDeleteApprovalGetModuleConfig($moduleKey);
        if (empty($config)) {
            $baseUrl = orderDeleteApprovalGetBaseUrl();
            return $baseUrl !== '' ? ($baseUrl . '/dashboard.php') : 'dashboard.php';
        }

        $url = orderDeleteApprovalGetBaseUrl() . (string) $config['page_path'];
        $params = array(
            'id' => (int) $sourceOrderId,
        );

        if ($approvalMode) {
            $params['approval_mode'] = 1;
        }
        if ((int) $requestId > 0) {
            $params['approval_request_id'] = (int) $requestId;
        }

        $queryString = http_build_query($params);
        return $queryString !== '' ? ($url . '?' . $queryString) : $url;
    }
}

if (!function_exists('orderDeleteApprovalBuildTableUrl')) {
    function orderDeleteApprovalBuildTableUrl($moduleKey)
    {
        $config = orderDeleteApprovalGetModuleConfig($moduleKey);
        if (empty($config)) {
            $baseUrl = orderDeleteApprovalGetBaseUrl();
            return $baseUrl !== '' ? ($baseUrl . '/dashboard.php') : 'dashboard.php';
        }

        return orderDeleteApprovalGetBaseUrl() . (string) $config['table_path'];
    }
}

if (!function_exists('orderDeleteApprovalResolveDisplayName')) {
    function orderDeleteApprovalResolveDisplayName($connect, $userId, $fallbackPrefix = 'User', $emptyValue = '-')
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return $emptyValue;
        }

        $displayName = trim((string) commonResolveUserDisplayName($connect, $userId));
        return $displayName !== '' ? $displayName : ($fallbackPrefix . ' #' . $userId);
    }
}

if (!function_exists('orderDeleteApprovalGetSourceOrderLabel')) {
    function orderDeleteApprovalGetSourceOrderLabel($requestRow, $sourceOrderId = 0, $fallbackPrefix = '')
    {
        $sourceOrderLabel = is_array($requestRow) ? trim((string) (isset($requestRow['source_order_label']) ? $requestRow['source_order_label'] : '')) : '';
        if ($sourceOrderLabel === '' && (int) $sourceOrderId > 0) {
            $sourceOrderLabel = ($fallbackPrefix !== '' ? $fallbackPrefix : '') . (int) $sourceOrderId;
        }

        return $sourceOrderLabel;
    }
}

if (!function_exists('orderDeleteApprovalSqlValueOrNull')) {
    function orderDeleteApprovalSqlValueOrNull($connect, $value)
    {
        $value = trim((string) $value);
        return $value !== '' ? ("'" . mysqli_real_escape_string($connect, $value) . "'") : 'NULL';
    }
}

if (!function_exists('orderDeleteApprovalReadUserRow')) {
    function orderDeleteApprovalReadUserRow($connect, $userId)
    {
        $userId = (int) $userId;
        if (!($connect instanceof mysqli) || $userId <= 0) {
            return array();
        }

        if (function_exists('systemAlertReadUserRow')) {
            return (array) systemAlertReadUserRow($connect, $userId);
        }

        $result = getData('*', "id = '" . $userId . "'", 'LIMIT 1', USR_USER, $connect);
        if ($result && $result->num_rows > 0) {
            return (array) $result->fetch_assoc();
        }

        return array();
    }
}

if (!function_exists('orderDeleteApprovalResolveSupervisorIdsForUser')) {
    function orderDeleteApprovalResolveSupervisorIdsForUser($connect, $userId)
    {
        $userRow = orderDeleteApprovalReadUserRow($connect, $userId);
        if (empty($userRow)) {
            return array();
        }

        if (function_exists('systemAlertResolveUserSupervisorIds')) {
            return orderDeleteApprovalNormalizeUserIds(systemAlertResolveUserSupervisorIds($connect, $userRow));
        }

        $candidateFields = array(
            'main_report_supervisor',
            'report_supervisor',
            'supervisor_id',
            'leader_id',
            'report_to',
            'second_report_supervisor',
        );

        $supervisorIds = array();
        foreach ($candidateFields as $fieldName) {
            if (!empty($userRow[$fieldName])) {
                $supervisorIds[] = (int) $userRow[$fieldName];
            }
        }

        return orderDeleteApprovalNormalizeUserIds($supervisorIds);
    }
}

if (!function_exists('orderDeleteApprovalReadRequest')) {
    function orderDeleteApprovalReadRequest($connect, $requestId)
    {
        $requestId = (int) $requestId;
        if (!($connect instanceof mysqli) || $requestId <= 0) {
            return array();
        }

        $tableName = orderDeleteApprovalGetTableName();
        $sql = "SELECT * FROM `" . $tableName . "` WHERE `id` = " . $requestId . " AND `status` = 'A' LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return (array) mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('orderDeleteApprovalReadPendingRequestBySource')) {
    function orderDeleteApprovalReadPendingRequestBySource($connect, $moduleKey, $sourceOrderId)
    {
        $moduleKey = trim((string) $moduleKey);
        $sourceOrderId = (int) $sourceOrderId;
        if (!($connect instanceof mysqli) || $moduleKey === '' || $sourceOrderId <= 0) {
            return array();
        }

        $tableName = orderDeleteApprovalGetTableName();
        $safeModuleKey = mysqli_real_escape_string($connect, $moduleKey);
        $sql = "SELECT *
                FROM `" . $tableName . "`
                WHERE `module_key` = '" . $safeModuleKey . "'
                  AND `source_order_id` = " . $sourceOrderId . "
                  AND `request_status` = 'pending'
                  AND `status` = 'A'
                ORDER BY `id` DESC
                LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && $result->num_rows > 0) {
            return (array) mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('orderDeleteApprovalCanUserReviewRequest')) {
    function orderDeleteApprovalCanUserReviewRequest($requestRow, $moduleKey, $sourceOrderId, $userId)
    {
        $moduleKey = trim((string) $moduleKey);
        $sourceOrderId = (int) $sourceOrderId;
        $userId = (int) $userId;
        if (!is_array($requestRow) || empty($requestRow) || $moduleKey === '' || $sourceOrderId <= 0 || $userId <= 0) {
            return false;
        }

        if (trim((string) (isset($requestRow['module_key']) ? $requestRow['module_key'] : '')) !== $moduleKey) {
            return false;
        }

        if ((int) (isset($requestRow['source_order_id']) ? $requestRow['source_order_id'] : 0) !== $sourceOrderId) {
            return false;
        }

        $supervisorIds = orderDeleteApprovalParseSupervisorIds(isset($requestRow['supervisor_user_ids']) ? $requestRow['supervisor_user_ids'] : '');
        return in_array($userId, $supervisorIds, true);
    }
}

if (!function_exists('orderDeleteApprovalCanUserAccessRequestView')) {
    function orderDeleteApprovalCanUserAccessRequestView($requestRow, $moduleKey, $sourceOrderId, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !is_array($requestRow) || empty($requestRow)) {
            return false;
        }

        if (orderDeleteApprovalCanUserReviewRequest($requestRow, $moduleKey, $sourceOrderId, $userId)) {
            return true;
        }

        return (int) (isset($requestRow['request_user_id']) ? $requestRow['request_user_id'] : 0) === $userId;
    }
}

if (!function_exists('orderDeleteApprovalBuildDeletedMessage')) {
    function orderDeleteApprovalBuildDeletedMessage($requestRow, $moduleKey, $sourceOrderId)
    {
        $moduleKey = trim((string) $moduleKey);
        $sourceOrderId = (int) $sourceOrderId;
        $config = orderDeleteApprovalGetModuleConfig($moduleKey);
        $sourceOrderLabel = orderDeleteApprovalGetSourceOrderLabel($requestRow, $sourceOrderId);

        if ($moduleKey === 'stock_order_request') {
            return 'The Stock order request ' . $sourceOrderLabel . ' already deleted';
        }

        $platformLabel = '';
        if (!empty($config) && isset($config['platform'])) {
            $platformLabel = ucwords(str_replace('_', ' ', trim((string) $config['platform'])));
        }
        if ($platformLabel === '' && !empty($config) && isset($config['title'])) {
            $platformLabel = trim((string) $config['title']);
        }
        if ($platformLabel === '') {
            $platformLabel = 'Selected';
        }

        return 'The ' . $platformLabel . ' order ' . $sourceOrderLabel . ' already deleted';
    }
}

if (!function_exists('orderDeleteApprovalShowDeletedPopup')) {
    function orderDeleteApprovalShowDeletedPopup($requestRow, $moduleKey, $sourceOrderId, $pageTitle, $redirectPage, $clearLocalStorage = '')
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        unset($_SESSION['tempValConfirmBox']);

        $message = orderDeleteApprovalBuildDeletedMessage($requestRow, $moduleKey, $sourceOrderId);
        if ($clearLocalStorage !== '') {
            echo $clearLocalStorage;
        }

        echo '<script>confirmationDialog("", ' . json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', ' . json_encode((string) $pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', "", ' . json_encode((string) $redirectPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', "ErrMO");</script>';
        exit;
    }
}

if (!function_exists('orderDeleteApprovalWriteAuditLog')) {
    function orderDeleteApprovalWriteAuditLog($connect, $pageTitle, $logAct, $message, $queryRecord = '', $queryTable = '')
    {
        if (!($connect instanceof mysqli) || !defined('USER_ID') || USER_ID === '') {
            return;
        }

        audit_log(array(
            'log_act' => $logAct,
            'cdate' => date('Y-m-d'),
            'ctime' => date('H:i:s'),
            'uid' => USER_ID,
            'cby' => USER_ID,
            'act_msg' => $message,
            'query_rec' => $queryRecord,
            'query_table' => $queryTable,
            'page' => $pageTitle,
            'connect' => $connect,
        ));
    }
}

if (!function_exists('orderDeleteApprovalResolveActor')) {
    function orderDeleteApprovalResolveActor($connect, $requestRow = array())
    {
        $actorUserId = defined('USER_ID') ? (int) USER_ID : 0;
        $actorUserName = defined('USER_NAME') ? trim((string) USER_NAME) : '';

        if (is_array($requestRow) && !empty($requestRow)) {
            $requestUserId = isset($requestRow['request_user_id']) ? (int) $requestRow['request_user_id'] : 0;
            if ($requestUserId > 0) {
                $actorUserId = $requestUserId;
                $resolvedName = orderDeleteApprovalResolveDisplayName($connect, $requestUserId, 'User', '');
                if ($resolvedName !== '') {
                    $actorUserName = $resolvedName;
                }
            }
        }

        if ($actorUserName === '') {
            $actorUserName = $actorUserId > 0 ? ('User #' . $actorUserId) : 'System';
        }

        return array(
            'user_id' => $actorUserId,
            'user_name' => $actorUserName,
        );
    }
}

if (!function_exists('orderDeleteApprovalSoftDeleteSingleRecord')) {
    function orderDeleteApprovalSoftDeleteSingleRecord($dataConnect, $auditConnect, $tableName, $sourceOrderId, $sourceOrderLabel, $pageTitle, $requestRow = array(), $idColumn = 'id')
    {
        $sourceOrderId = (int) $sourceOrderId;
        $sourceOrderLabel = trim((string) $sourceOrderLabel);
        $tableName = trim((string) $tableName);
        $idColumn = trim((string) $idColumn);

        if (!($dataConnect instanceof mysqli) || !($auditConnect instanceof mysqli) || $tableName === '' || $sourceOrderId <= 0) {
            return array('success' => false, 'message' => 'Invalid order delete request.');
        }

        if ($idColumn === '') {
            $idColumn = 'id';
        }

        $safeIdColumn = mysqli_real_escape_string($dataConnect, $idColumn);
        $query = "UPDATE " . $tableName . " SET status = 'D' WHERE " . $safeIdColumn . " = '" . $sourceOrderId . "'";
        $deleteSuccess = mysqli_query($dataConnect, $query);
        $deleteError = $deleteSuccess ? '' : mysqli_error($dataConnect);

        $actor = orderDeleteApprovalResolveActor($auditConnect, $requestRow);
        $logMessage = $actor['user_name'] . ' ' . ($deleteSuccess ? 'deleted' : 'failed to delete') . ' the data [<b> ID = ' . $sourceOrderId . '</b> ] <b>' . htmlspecialchars($sourceOrderLabel, ENT_QUOTES, 'UTF-8') . '</b> from <b><i>' . htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') . ' Table</i></b>.';
        if (!$deleteSuccess && $deleteError !== '') {
            $logMessage .= ' ( ' . htmlspecialchars($deleteError, ENT_QUOTES, 'UTF-8') . ' )';
        }

        audit_log(array(
            'log_act' => 'delete',
            'cdate' => date('Y-m-d'),
            'ctime' => date('H:i:s'),
            'uid' => $actor['user_id'],
            'cby' => $actor['user_id'],
            'act_msg' => $logMessage,
            'query_rec' => $query,
            'query_table' => $tableName,
            'page' => $pageTitle,
            'connect' => $auditConnect,
        ));

        if (!$deleteSuccess) {
            return array('success' => false, 'message' => $deleteError !== '' ? $deleteError : 'Unable to delete this order.');
        }

        return array('success' => true, 'message' => 'Order deleted successfully.');
    }
}

if (!function_exists('orderDeleteApprovalResolveSourceOrderId')) {
    function orderDeleteApprovalResolveSourceOrderId($requestRow = array(), $fallbackDataId = 0)
    {
        $deleteDataId = 0;
        if (is_array($requestRow) && isset($requestRow['source_order_id'])) {
            $deleteDataId = (int) $requestRow['source_order_id'];
        }
        if ($deleteDataId <= 0) {
            $deleteDataId = (int) $fallbackDataId;
        }

        return $deleteDataId;
    }
}

if (!function_exists('orderDeleteApprovalExecuteStandardSoftDelete')) {
    function orderDeleteApprovalExecuteStandardSoftDelete($config = array(), $requestRow = array())
    {
        $config = is_array($config) ? $config : array();
        $dataConnect = isset($config['data_connect']) ? $config['data_connect'] : null;
        $auditConnect = isset($config['audit_connect']) ? $config['audit_connect'] : null;
        $tableName = isset($config['table_name']) ? (string) $config['table_name'] : '';
        $pageTitle = isset($config['page_title']) ? (string) $config['page_title'] : '';
        $fallbackDataId = isset($config['fallback_data_id']) ? (int) $config['fallback_data_id'] : 0;
        $labelField = isset($config['label_field']) ? trim((string) $config['label_field']) : '';
        $notFoundMessage = isset($config['not_found_message']) && trim((string) $config['not_found_message']) !== ''
            ? trim((string) $config['not_found_message'])
            : 'Order record was not found.';

        $deleteDataId = orderDeleteApprovalResolveSourceOrderId($requestRow, $fallbackDataId);
        if (!($dataConnect instanceof mysqli) || !($auditConnect instanceof mysqli) || $tableName === '' || $deleteDataId <= 0) {
            return array('success' => false, 'message' => 'Invalid order delete request.');
        }

        $deleteResult = getData('*', "id = '" . $deleteDataId . "'", 'LIMIT 1', $tableName, $dataConnect);
        if (!$deleteResult || $deleteResult->num_rows === 0) {
            return array('success' => false, 'message' => $notFoundMessage);
        }

        $deleteRow = $deleteResult->fetch_assoc();
        $deleteLabel = $labelField !== '' && isset($deleteRow[$labelField]) ? trim((string) $deleteRow[$labelField]) : '';
        if ($deleteLabel === '') {
            $deleteLabel = 'Order #' . $deleteDataId;
        }

        $deleteResponse = orderDeleteApprovalSoftDeleteSingleRecord(
            $dataConnect,
            $auditConnect,
            $tableName,
            $deleteDataId,
            $deleteLabel,
            $pageTitle,
            $requestRow
        );
        if (!empty($deleteResponse['success'])) {
            $_SESSION['delChk'] = 1;
        }

        return $deleteResponse;
    }
}

if (!function_exists('orderDeleteApprovalBuildStandardDeleteCallback')) {
    function orderDeleteApprovalBuildStandardDeleteCallback($config = array())
    {
        $config = is_array($config) ? $config : array();

        return function ($requestRow = array()) use ($config) {
            return orderDeleteApprovalExecuteStandardSoftDelete($config, $requestRow);
        };
    }
}

if (!function_exists('orderDeleteApprovalCreateSupervisorAlerts')) {
    function orderDeleteApprovalCreateSupervisorAlerts($connect, $requestRow)
    {
        if (!($connect instanceof mysqli) || !is_array($requestRow) || empty($requestRow) || !function_exists('systemAlertCreateOnce')) {
            return;
        }

        $requestId = isset($requestRow['id']) ? (int) $requestRow['id'] : 0;
        $moduleKey = isset($requestRow['module_key']) ? (string) $requestRow['module_key'] : '';
        $config = orderDeleteApprovalGetModuleConfig($moduleKey);
        if ($requestId <= 0 || empty($config)) {
            return;
        }

        $sourceOrderId = isset($requestRow['source_order_id']) ? (int) $requestRow['source_order_id'] : 0;
        $sourceOrderLabel = orderDeleteApprovalGetSourceOrderLabel($requestRow, $sourceOrderId);
        $requestUserId = isset($requestRow['request_user_id']) ? (int) $requestRow['request_user_id'] : 0;
        $requestUserName = orderDeleteApprovalResolveDisplayName($connect, $requestUserId);

        $message = $requestUserName . ' requested delete for ' . (string) $config['title'];
        if ($sourceOrderLabel !== '') {
            $message .= ' ' . $sourceOrderLabel;
        }
        $message .= '.';

        foreach (orderDeleteApprovalParseSupervisorIds(isset($requestRow['supervisor_user_ids']) ? $requestRow['supervisor_user_ids'] : '') as $supervisorUserId) {
            systemAlertCreateOnce($connect, array(
                'module_key' => 'order_delete_approval',
                'notification_type' => 'order_delete_pending_approval',
                'target_user_id' => $supervisorUserId,
                'target_user_group_id' => function_exists('systemAlertGetUserGroupId') ? systemAlertGetUserGroupId($connect, $supervisorUserId) : 0,
                'title' => 'Order Delete Request',
                'message' => $message,
                'action_url' => orderDeleteApprovalBuildPageUrl($moduleKey, $sourceOrderId, $requestId, true),
                'action_label' => 'Review Request',
                'related_table' => orderDeleteApprovalGetTableName(),
                'related_id' => $requestId,
                'related_platform' => isset($config['platform']) ? (string) $config['platform'] : '',
                'display_date' => date('Y-m-d'),
                'create_by' => defined('USER_ID') ? USER_ID : 'SYSTEM',
                'create_date' => date('Y-m-d'),
                'create_time' => date('H:i:s'),
            ));
        }
    }
}

if (!function_exists('orderDeleteApprovalNotifyRequester')) {
    function orderDeleteApprovalNotifyRequester($connect, $requestRow, $notificationType)
    {
        if (!($connect instanceof mysqli) || !is_array($requestRow) || empty($requestRow) || !function_exists('systemAlertCreateOnce')) {
            return;
        }

        $requestId = isset($requestRow['id']) ? (int) $requestRow['id'] : 0;
        $requestUserId = isset($requestRow['request_user_id']) ? (int) $requestRow['request_user_id'] : 0;
        $moduleKey = isset($requestRow['module_key']) ? (string) $requestRow['module_key'] : '';
        $config = orderDeleteApprovalGetModuleConfig($moduleKey);
        if ($requestId <= 0 || $requestUserId <= 0 || empty($config)) {
            return;
        }

        $sourceOrderLabel = orderDeleteApprovalGetSourceOrderLabel($requestRow, isset($requestRow['source_order_id']) ? (int) $requestRow['source_order_id'] : 0);
        $actorUserId = isset($requestRow['decision_user_id']) ? (int) $requestRow['decision_user_id'] : 0;
        $actorName = orderDeleteApprovalResolveDisplayName($connect, $actorUserId, 'User', 'Supervisor');

        $title = 'Order Delete Request';
        $message = '';
        $actionUrl = orderDeleteApprovalBuildTableUrl($moduleKey);
        $actionLabel = 'Open Table';
        if ($notificationType === 'order_delete_approved') {
            $message = $actorName . ' approved your delete request ' . (string) $config['title'];
            if ($sourceOrderLabel !== '') {
                $message .= ' ' . $sourceOrderLabel;
            }
            $message .= '.';
        } else if ($notificationType === 'order_delete_rejected') {
            $message = $actorName . ' rejected your delete request for ' . (string) $config['title'];
            if ($sourceOrderLabel !== '') {
                $message .= ' ' . $sourceOrderLabel;
            }
            $remark = trim((string) (isset($requestRow['reject_reason']) ? $requestRow['reject_reason'] : ''));
            if ($remark === '') {
                $remark = trim((string) (isset($requestRow['approval_remark']) ? $requestRow['approval_remark'] : ''));
            }
            if ($remark !== '') {
                $message .= ' Remark: ' . $remark;
            }
            $message .= '.';
            $actionUrl = orderDeleteApprovalBuildPageUrl($moduleKey, (int) (isset($requestRow['source_order_id']) ? $requestRow['source_order_id'] : 0));
            $actionLabel = 'Open Order';
        } else {
            return;
        }

        systemAlertCreateOnce($connect, array(
            'module_key' => 'order_delete_approval',
            'notification_type' => $notificationType,
            'target_user_id' => $requestUserId,
            'target_user_group_id' => function_exists('systemAlertGetUserGroupId') ? systemAlertGetUserGroupId($connect, $requestUserId) : 0,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
            'related_table' => orderDeleteApprovalGetTableName(),
            'related_id' => $requestId,
            'related_platform' => isset($config['platform']) ? (string) $config['platform'] : '',
            'display_date' => date('Y-m-d'),
            'create_by' => defined('USER_ID') ? USER_ID : 'SYSTEM',
            'create_date' => date('Y-m-d'),
            'create_time' => date('H:i:s'),
        ));
    }
}

if (!function_exists('orderDeleteApprovalRequestDelete')) {
    function orderDeleteApprovalRequestDelete($connect, $moduleKey, $sourceOrderId, $sourceOrderLabel, $pageTitle)
    {
        $sourceOrderId = (int) $sourceOrderId;
        $sourceOrderLabel = trim((string) $sourceOrderLabel);
        $moduleKey = trim((string) $moduleKey);
        if (!($connect instanceof mysqli) || $moduleKey === '' || $sourceOrderId <= 0 || !defined('USER_ID') || (int) USER_ID <= 0) {
            return array(
                'success' => false,
                'direct_delete' => false,
                'notification_type' => 'error',
                'message' => 'Unable to prepare delete request.',
            );
        }

        $config = orderDeleteApprovalGetModuleConfig($moduleKey);
        if (empty($config)) {
            return array(
                'success' => false,
                'direct_delete' => false,
                'notification_type' => 'error',
                'message' => 'Delete request configuration is unavailable.',
            );
        }

        $pendingRequest = orderDeleteApprovalReadPendingRequestBySource($connect, $moduleKey, $sourceOrderId);
        if (!empty($pendingRequest)) {
            return array(
                'success' => false,
                'direct_delete' => false,
                'notification_type' => 'warning',
                'message' => 'Delete request is already pending for this order.',
                'request_row' => $pendingRequest,
            );
        }

        $supervisorIds = orderDeleteApprovalResolveSupervisorIdsForUser($connect, (int) USER_ID);
        if (empty($supervisorIds)) {
            return array(
                'success' => true,
                'direct_delete' => true,
                'notification_type' => 'success',
                'message' => '',
            );
        }

        $tableName = orderDeleteApprovalGetTableName();
        $safeModuleKey = mysqli_real_escape_string($connect, $moduleKey);
        $safePlatform = mysqli_real_escape_string($connect, isset($config['platform']) ? (string) $config['platform'] : '');
        $safeSourceDb = mysqli_real_escape_string($connect, isset($config['source_db']) ? (string) $config['source_db'] : '');
        $safeSourceTable = mysqli_real_escape_string($connect, isset($config['source_table']) ? (string) $config['source_table'] : '');
        $safeSupervisorIds = mysqli_real_escape_string($connect, orderDeleteApprovalSerializeSupervisorIds($supervisorIds));
        $requestUserId = (int) USER_ID;
        $requestUserGroupId = defined('USER_GROUP') ? (int) USER_GROUP : 0;
        $safeCreateBy = mysqli_real_escape_string($connect, (string) USER_ID);

        $sql = "INSERT INTO `" . $tableName . "` (
                    `module_key`,
                    `platform`,
                    `source_db`,
                    `source_table`,
                    `source_order_id`,
                    `source_order_label`,
                    `request_user_id`,
                    `request_user_group_id`,
                    `supervisor_user_ids`,
                    `request_status`,
                    `create_by`,
                    `create_date`,
                    `create_time`,
                    `update_by`,
                    `update_date`,
                    `update_time`,
                    `status`
                ) VALUES (
                    '" . $safeModuleKey . "',
                    '" . $safePlatform . "',
                    '" . $safeSourceDb . "',
                    '" . $safeSourceTable . "',
                    " . $sourceOrderId . ",
                    " . orderDeleteApprovalSqlValueOrNull($connect, $sourceOrderLabel) . ",
                    " . $requestUserId . ",
                    " . ($requestUserGroupId > 0 ? $requestUserGroupId : 'NULL') . ",
                    '" . $safeSupervisorIds . "',
                    'pending',
                    '" . $safeCreateBy . "',
                    CURDATE(),
                    CURTIME(),
                    '" . $safeCreateBy . "',
                    CURDATE(),
                    CURTIME(),
                    'A'
                )";

        if (!mysqli_query($connect, $sql)) {
            return array(
                'success' => false,
                'direct_delete' => false,
                'notification_type' => 'error',
                'message' => 'Failed to create delete request.',
            );
        }

        $requestId = (int) mysqli_insert_id($connect);
        $requestRow = orderDeleteApprovalReadRequest($connect, $requestId);
        if (!empty($requestRow)) {
            orderDeleteApprovalCreateSupervisorAlerts($connect, $requestRow);
        }

        $requestUserName = defined('USER_NAME') && trim((string) USER_NAME) !== ''
            ? trim((string) USER_NAME)
            : orderDeleteApprovalResolveDisplayName($connect, $requestUserId);

        $auditMessage = $requestUserName . ' submitted delete request for ' . (string) $config['title'];
        if ($sourceOrderLabel !== '') {
            $auditMessage .= ' <b>' . htmlspecialchars($sourceOrderLabel, ENT_QUOTES, 'UTF-8') . '</b>';
        }
        $auditMessage .= '.';
        orderDeleteApprovalWriteAuditLog($connect, $pageTitle, 'request', $auditMessage, $sql, $tableName);

        return array(
            'success' => true,
            'direct_delete' => false,
            'notification_type' => 'success',
            'message' => 'Delete request has been sent to supervisor.',
            'request_row' => $requestRow,
        );
    }
}

if (!function_exists('orderDeleteApprovalGetDecisionConfig')) {
    function orderDeleteApprovalGetDecisionConfig($decisionType)
    {
        $configs = array(
            'approve' => array(
                'request_status' => 'executed',
                'remark_field' => 'approval_remark',
                'log_act' => 'approval',
                'notification_type' => 'order_delete_approved',
                'success_message' => 'Delete request approved and order deleted successfully.',
                'action_label' => 'approved',
                'permission_message' => 'You do not have permission to approve this delete request.',
                'requires_delete' => true,
            ),
            'reject' => array(
                'request_status' => 'rejected',
                'remark_field' => 'reject_reason',
                'log_act' => 'declined',
                'notification_type' => 'order_delete_rejected',
                'success_message' => 'Delete request rejected successfully.',
                'action_label' => 'rejected',
                'permission_message' => 'You do not have permission to reject this delete request.',
                'requires_delete' => false,
            ),
        );

        $decisionType = trim((string) $decisionType);
        return isset($configs[$decisionType]) ? $configs[$decisionType] : array();
    }
}

if (!function_exists('orderDeleteApprovalProcessDecision')) {
    function orderDeleteApprovalProcessDecision($connect, $requestId, $moduleKey, $sourceOrderId, $decisionRemark, $pageTitle, $decisionType, $executeDeleteCallback = null)
    {
        $decisionConfig = orderDeleteApprovalGetDecisionConfig($decisionType);
        if (empty($decisionConfig)) {
            return array('success' => false, 'message' => 'Invalid delete request action.');
        }

        $requestRow = orderDeleteApprovalReadRequest($connect, $requestId);
        if (empty($requestRow)) {
            return array('success' => false, 'message' => 'Delete request was not found.');
        }
        if (!orderDeleteApprovalCanUserReviewRequest($requestRow, $moduleKey, $sourceOrderId, (int) USER_ID)) {
            return array('success' => false, 'message' => $decisionConfig['permission_message']);
        }
        if (trim((string) (isset($requestRow['request_status']) ? $requestRow['request_status'] : '')) !== 'pending') {
            return array('success' => false, 'message' => 'This delete request is no longer pending.');
        }

        if (!empty($decisionConfig['requires_delete'])) {
            if (!is_callable($executeDeleteCallback)) {
                return array('success' => false, 'message' => 'Delete executor is unavailable.');
            }

            $deleteResult = call_user_func($executeDeleteCallback, $requestRow);
            if (!is_array($deleteResult) || empty($deleteResult['success'])) {
                return array(
                    'success' => false,
                    'message' => is_array($deleteResult) && isset($deleteResult['message']) ? (string) $deleteResult['message'] : 'Unable to delete this order.',
                );
            }
        }

        $decisionRemark = trim((string) $decisionRemark);
        $tableName = orderDeleteApprovalGetTableName();
        $safeUpdateBy = mysqli_real_escape_string($connect, (string) USER_ID);
        $sqlParts = array(
            "`request_status` = '" . $decisionConfig['request_status'] . "'",
            "`" . $decisionConfig['remark_field'] . "` = " . orderDeleteApprovalSqlValueOrNull($connect, $decisionRemark),
            "`decision_user_id` = " . (int) USER_ID,
            "`decision_date` = CURDATE()",
            "`decision_time` = CURTIME()",
            "`update_by` = '" . $safeUpdateBy . "'",
            "`update_date` = CURDATE()",
            "`update_time` = CURTIME()",
        );
        if ($decisionConfig['request_status'] === 'executed') {
            $sqlParts[] = "`executed_user_id` = " . (int) USER_ID;
            $sqlParts[] = "`executed_date` = CURDATE()";
            $sqlParts[] = "`executed_time` = CURTIME()";
        }

        $sql = "UPDATE `" . $tableName . "`
                SET " . implode(",\n                    ", $sqlParts) . "
                WHERE `id` = " . (int) $requestId . "
                  AND `request_status` = 'pending'
                  AND `status` = 'A'
                LIMIT 1";

        if (!mysqli_query($connect, $sql)) {
            return array(
                'success' => false,
                'message' => $decisionConfig['request_status'] === 'executed'
                    ? 'Order was deleted, but the delete request could not be finalized.'
                    : 'Failed to reject delete request.',
            );
        }

        $updatedRequestRow = orderDeleteApprovalReadRequest($connect, $requestId);
        if (!empty($updatedRequestRow)) {
            orderDeleteApprovalNotifyRequester($connect, $updatedRequestRow, $decisionConfig['notification_type']);
        }

        $config = orderDeleteApprovalGetModuleConfig($moduleKey);
        $sourceOrderLabel = orderDeleteApprovalGetSourceOrderLabel($requestRow, $sourceOrderId);
        $actorName = defined('USER_NAME') && trim((string) USER_NAME) !== ''
            ? trim((string) USER_NAME)
            : orderDeleteApprovalResolveDisplayName($connect, (int) USER_ID, 'User', 'Supervisor');
        $auditMessage = $actorName . ' ' . $decisionConfig['action_label'] . ' delete request for ' . (isset($config['title']) ? (string) $config['title'] : 'Order');
        if ($sourceOrderLabel !== '') {
            $auditMessage .= ' <b>' . htmlspecialchars($sourceOrderLabel, ENT_QUOTES, 'UTF-8') . '</b>';
        }
        if ($decisionRemark !== '') {
            $auditMessage .= '. Remark: ' . htmlspecialchars($decisionRemark, ENT_QUOTES, 'UTF-8');
        }
        $auditMessage .= '.';
        orderDeleteApprovalWriteAuditLog($connect, $pageTitle, $decisionConfig['log_act'], $auditMessage, $sql, $tableName);

        return array('success' => true, 'message' => $decisionConfig['success_message']);
    }
}

if (!function_exists('orderDeleteApprovalApproveRequest')) {
    function orderDeleteApprovalApproveRequest($connect, $requestId, $moduleKey, $sourceOrderId, $decisionRemark, $pageTitle, $executeDeleteCallback)
    {
        return orderDeleteApprovalProcessDecision($connect, $requestId, $moduleKey, $sourceOrderId, $decisionRemark, $pageTitle, 'approve', $executeDeleteCallback);
    }
}

if (!function_exists('orderDeleteApprovalRejectRequest')) {
    function orderDeleteApprovalRejectRequest($connect, $requestId, $moduleKey, $sourceOrderId, $decisionRemark, $pageTitle)
    {
        return orderDeleteApprovalProcessDecision($connect, $requestId, $moduleKey, $sourceOrderId, $decisionRemark, $pageTitle, 'reject');
    }
}

if (!function_exists('orderDeleteApprovalRenderDecisionPanel')) {
    function orderDeleteApprovalRenderDecisionPanel($connect, $requestRow, $moduleKey, $sourceOrderId, $currentUserId)
    {
        if (!is_array($requestRow) || empty($requestRow)) {
            return '';
        }

        $moduleKey = trim((string) $moduleKey);
        $sourceOrderId = (int) $sourceOrderId;
        $currentUserId = (int) $currentUserId;
        if ($moduleKey === '' || $sourceOrderId <= 0) {
            return '';
        }

        $requestStatus = trim((string) (isset($requestRow['request_status']) ? $requestRow['request_status'] : ''));
        $sourceOrderLabel = orderDeleteApprovalGetSourceOrderLabel($requestRow, $sourceOrderId, '#');
        $requestUserId = isset($requestRow['request_user_id']) ? (int) $requestRow['request_user_id'] : 0;
        $requestUserName = orderDeleteApprovalResolveDisplayName($connect, $requestUserId);

        $canReview = orderDeleteApprovalCanUserReviewRequest($requestRow, $moduleKey, $sourceOrderId, $currentUserId);
        $decisionUserId = isset($requestRow['decision_user_id']) ? (int) $requestRow['decision_user_id'] : 0;
        $decisionUserName = orderDeleteApprovalResolveDisplayName($connect, $decisionUserId);

        $statusClass = 'alert-info';
        $statusLabel = ucwords(str_replace('_', ' ', $requestStatus));
        $detailHtml = '';

        if ($requestStatus === 'pending') {
            $statusClass = 'alert-warning';
            if ($canReview) {
                $detailHtml = '
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label class="form-label" for="order_delete_decision_remark">Remark</label>
                            <textarea class="form-control" id="order_delete_decision_remark" name="decision_remark" rows="3" placeholder="Optional remark"></textarea>
                        </div>
                        <div class="col-12 d-flex flex-wrap justify-content-center gap-3">
                            <button type="submit" name="approveDeleteApproval" value="1" class="btn btn-success mt-1" style="border-radius:999px; padding:12px 26px; font-size:16px; font-weight:600; line-height:1.2; min-width:190px; box-shadow:0 8px 18px rgba(0, 0, 0, 0.12); text-transform:none;">Approve Delete</button>
                            <button type="submit" name="rejectDeleteApproval" value="1" class="btn btn-danger mt-1" style="border-radius:999px; padding:12px 26px; font-size:16px; font-weight:600; line-height:1.2; min-width:190px; box-shadow:0 8px 18px rgba(0, 0, 0, 0.12); text-transform:none;">Reject Delete</button>
                        </div>
                    </div>';
            } else {
                $detailHtml = '<p class="mb-0 mt-2">This delete request is pending supervisor review.</p>';
            }
        } else if ($requestStatus === 'rejected') {
            $statusClass = 'alert-danger';
            $rejectRemark = trim((string) (isset($requestRow['reject_reason']) ? $requestRow['reject_reason'] : ''));
            if ($rejectRemark === '') {
                $rejectRemark = trim((string) (isset($requestRow['approval_remark']) ? $requestRow['approval_remark'] : ''));
            }
            $detailHtml = '<p class="mb-0 mt-2"><strong>Rejected By:</strong> ' . htmlspecialchars($decisionUserName, ENT_QUOTES, 'UTF-8');
            if ($rejectRemark !== '') {
                $detailHtml .= '<br><strong>Remark:</strong> ' . htmlspecialchars($rejectRemark, ENT_QUOTES, 'UTF-8');
            }
            $detailHtml .= '</p>';
        } else if ($requestStatus === 'executed') {
            $statusClass = 'alert-success';
            $approvalRemark = trim((string) (isset($requestRow['approval_remark']) ? $requestRow['approval_remark'] : ''));
            $detailHtml = '<p class="mb-0 mt-2"><strong>Approved By:</strong> ' . htmlspecialchars($decisionUserName, ENT_QUOTES, 'UTF-8');
            if ($approvalRemark !== '') {
                $detailHtml .= '<br><strong>Remark:</strong> ' . htmlspecialchars($approvalRemark, ENT_QUOTES, 'UTF-8');
            }
            $detailHtml .= '</p>';
        }

        $html = '
            <div class="alert ' . $statusClass . ' mb-4" role="alert">
                <div class="d-flex flex-column gap-1">
                    <strong>Delete Request - ' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</strong>
                    <span><strong>Requested By:</strong> ' . htmlspecialchars($requestUserName, ENT_QUOTES, 'UTF-8') . '</span>
                    <span><strong>Order:</strong> ' . htmlspecialchars($sourceOrderLabel, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
                <input type="hidden" name="approval_request_id" value="' . (int) (isset($requestRow['id']) ? $requestRow['id'] : 0) . '">
                <input type="hidden" name="approval_mode" value="1">
                ' . $detailHtml . '
            </div>';

        return $html;
    }
}

if (!function_exists('orderDeleteApprovalHandlePageFlow')) {
    function orderDeleteApprovalHandlePageFlow($config = array())
    {
        $config = is_array($config) ? $config : array();
        $connect = isset($config['connect']) ? $config['connect'] : null;
        $requestId = isset($config['request_id']) ? (int) $config['request_id'] : 0;
        $moduleKey = isset($config['module_key']) ? trim((string) $config['module_key']) : '';
        $dataId = isset($config['data_id']) ? (int) $config['data_id'] : 0;
        $currentUserId = isset($config['current_user_id']) ? (int) $config['current_user_id'] : 0;
        $pageTitle = isset($config['page_title']) ? (string) $config['page_title'] : '';
        $redirectPage = isset($config['redirect_page']) ? (string) $config['redirect_page'] : '';
        $clearLocalStorage = isset($config['clear_local_storage']) ? (string) $config['clear_local_storage'] : '';
        $orderDeleteApprovalMode = !empty($config['approval_mode']);
        $deleteCallback = isset($config['delete_callback']) ? $config['delete_callback'] : null;
        $decisionRemark = isset($config['decision_remark']) ? trim((string) $config['decision_remark']) : trim((string) postSpaceFilter('decision_remark'));
        $panelHtml = '';

        if ($orderDeleteApprovalMode && $dataId > 0) {
            $requestRow = orderDeleteApprovalReadRequest($connect, $requestId);
            if (
                empty($requestRow) ||
                !orderDeleteApprovalCanUserAccessRequestView($requestRow, $moduleKey, $dataId, $currentUserId)
            ) {
                renderNotificationScript('You do not have permission to review this delete request.', 'error', $redirectPage, 1200, true);
                exit;
            }

            if (trim((string) (isset($requestRow['request_status']) ? $requestRow['request_status'] : '')) === 'executed') {
                orderDeleteApprovalShowDeletedPopup(
                    $requestRow,
                    $moduleKey,
                    $dataId,
                    $pageTitle,
                    $redirectPage,
                    $clearLocalStorage
                );
            }

            $panelHtml = orderDeleteApprovalRenderDecisionPanel(
                $connect,
                $requestRow,
                $moduleKey,
                $dataId,
                $currentUserId
            );
        }

        if (post('approveDeleteApproval')) {
            $approvalResult = orderDeleteApprovalApproveRequest(
                $connect,
                $requestId,
                $moduleKey,
                $dataId,
                $decisionRemark,
                $pageTitle,
                $deleteCallback
            );
            renderNotificationScript(
                $approvalResult['message'],
                !empty($approvalResult['success']) ? 'success' : 'error',
                $redirectPage,
                1200,
                true
            );
            exit;
        }

        if (post('rejectDeleteApproval')) {
            $rejectResult = orderDeleteApprovalRejectRequest(
                $connect,
                $requestId,
                $moduleKey,
                $dataId,
                $decisionRemark,
                $pageTitle
            );
            renderNotificationScript(
                $rejectResult['message'],
                !empty($rejectResult['success']) ? 'success' : 'error',
                $redirectPage,
                1200,
                true
            );
            exit;
        }

        return $panelHtml;
    }
}
