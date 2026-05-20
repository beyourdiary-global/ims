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

        $rst = getData('name', "id='" . mysqli_real_escape_string($financeConnect, $payMethodId) . "'", 'LIMIT 1', FIN_PAY_METH, $financeConnect);
        if ($rst && $rst->num_rows > 0) {
            $row = $rst->fetch_assoc();
            return isset($row['name']) ? (string) $row['name'] : $payMethodId;
        }

        return $payMethodId;
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

//example: isDuplicateRecordWithConditions(['month', 'year'], [$btb_month, $btb_year], $tblName, $finance_connect, $dataID)
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


function tableExists($tableName, $conn)
{
	if (!$conn) {
		die("Database connection is not initialized.");
	}
	$result = $conn->query("SHOW TABLES LIKE '$tableName'");
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
            error_log("Query failed: $query â€” Error: " . mysqli_error($conn));
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

        $rst = getData('name', "id = '$pinGroupId'", 'LIMIT 1', 'pin_group', $connect);
        if ($rst && $rst->num_rows > 0) {
            $row = $rst->fetch_assoc();
            if (isset($row['name']) && trim((string) $row['name']) !== '') {
                return (string) $row['name'];
            }
        }

        return '';
    }
}


function generateDBData($tblname, $conn)
{
	$rst = getData('*', '', '', $tblname, $conn);

	// Check if $rst is a valid result set
	if ($rst === false) {
		// Log the error or output debug information
		error_log("Error in getData() for table $tblname: " . $conn->error);
		return;
		// die("Failed to fetch data from table $tblname");

	}

	$data = array();
	while ($row = $rst->fetch_assoc()) {
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

function renderViewEditButton($action, $redirect_page, $row, $pinAccess, $act_2 = null)
{
	switch ($action) {
		case "View":
			if (isActionAllowed("View", $pinAccess)) {
				echo '<a class="btn btn-primary me-1" href="' . $redirect_page . '?id=' . $row['id'] . '"><i class="fas fa-eye"></i></a>';
			}
			break;
		case "Edit":
			if (isActionAllowed("Edit", $pinAccess)) {
				echo '<a class="btn btn-warning me-1" href="' . $redirect_page . '?id=' . $row['id'] . '&act=' . $act_2 . '"><i class="fas fa-edit"></i></a>';
			}
			break;
	}

}

function renderViewEditButtonByPin($action, $redirect_page, $row, $pinAccess, $act_2 = null)
{
	switch ($action) {
		case "1":
			if (in_array(1, $pinAccess)) {
				echo '<a class="btn btn-primary me-1" href="' . $redirect_page . '?id=' . $row['id'] . '"><i class="fas fa-eye"></i></a>';
			}
			break;
		case "2":
			if (in_array(2, $pinAccess)) {
				echo '<a class="btn btn-warning me-1" href="' . $redirect_page . '?id=' . $row['id'] . '&act=' . $act_2 . '"><i class="fas fa-edit"></i></a>';
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
            $params['return_page'] = $returnPage;
        }

        if ($returnLabel !== '') {
            $params['return_label'] = $returnLabel;
        }

        $url = '#';
        $disabled = true;
        if ($targetId !== '' && defined('SITEURL')) {
            $url = SITEURL . '/urb_cust_reg.php?' . http_build_query($params);
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
                'record_url' => '/fb_cust_deals_table.php',
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
                'record_url' => '/website_customer_record_table.php',
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
    function customerLabelFetchActiveRows($conn, $tableName, $columns = '*', $extraWhere = '', $orderBy = 'ORDER BY id ASC')
    {
        if (!($conn instanceof mysqli) || $tableName === '' || !tableExists($tableName, $conn)) {
            return array();
        }

        $where = "WHERE status = 'A'";
        if ($extraWhere !== '') {
            $where .= " AND " . $extraWhere;
        }

        $sql = "SELECT " . $columns . " FROM `" . $tableName . "` " . $where . " " . $orderBy;
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
        $packageIds = customerLabelSplitCsv(isset($orderRow[$fieldName]) ? $orderRow[$fieldName] : '');
        $rows = array();
        foreach ($packageIds as $packageIdRaw) {
            $packageId = (int) $packageIdRaw;
            if ($packageId <= 0) {
                continue;
            }
            $rows[] = array(
                'package_id' => $packageId,
                'qty' => 1,
            );
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
        return '<span class="d-inline-flex align-items-center ms-1 px-2 py-1 rounded-pill text-white" style="background-color:' .
            htmlspecialchars($badgeColor, ENT_QUOTES, 'UTF-8') .
            ';font-size:13px;line-height:1;">' .
            htmlspecialchars($labelName, ENT_QUOTES, 'UTF-8') .
            '</span>';
    }
}

if (!function_exists('customerLabelRenderNameCell')) {
    function customerLabelRenderNameCell($displayName, $customerLabelMeta)
    {
        $safeDisplayName = htmlspecialchars((string) $displayName, ENT_QUOTES, 'UTF-8');
        $segmentationBadge = isset($customerLabelMeta['segmentation']) ? customerLabelRenderBadge($customerLabelMeta['segmentation']) : '';
        return '<span class="d-inline-flex align-items-center flex-nowrap">' . $safeDisplayName . ($segmentationBadge !== '' ? ' ' . $segmentationBadge : '') . '</span>';
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
        $tagBadgeHtml = customerTagRenderBadges($customerTagRows, 'customer-tag-table-badge-group', 'customer-tag-table-badge');
        if ($tagBadgeHtml !== '') {
            $parts[] = $tagBadgeHtml;
        }

        return empty($parts) ? '' : implode(' ', $parts);
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

        return $baseUrl . '/customer_label_breakdown.php?label_type=' . urlencode($labelType) . '&label_id=' . $labelId;
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
    function validateEstimatedReceivedDate($date)
    {
        $date = trim((string) $date);
        $today = new DateTimeImmutable('today');
        $minDate = $today->modify('+1 day');
        $maxDate = $today->modify('+10 days');

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

if (!function_exists('shouldShowEstimatedReceivedDateButton')) {
    function shouldShowEstimatedReceivedDateButton($row)
    {
        return function_exists('shopeeOmsShouldShowEstimatedReceivedDateButton')
            ? shopeeOmsShouldShowEstimatedReceivedDateButton($row)
            : false;
    }
}

if (!function_exists('assignEstimatedReceivedDate')) {
    function assignEstimatedReceivedDate($connect, $tableName, $orderId, $date, $currentUserId)
    {
        return function_exists('shopeeOmsAssignEstimatedReceivedDate')
            ? shopeeOmsAssignEstimatedReceivedDate($connect, $tableName, $orderId, $date, $currentUserId)
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
        if ($statusCode === 'P' || $statusCode === 'TP') {
            return 'Processing';
        }

        return shopeeOmsGetStatusLabel($statusCode);
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
                'WAERD' => array('action' => 'auto_post_ship', 'requires_permission' => false, 'auto' => true),
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
    function shopeeOmsLoadOrder($connect, $orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0 || !($connect instanceof mysqli)) {
            return array();
        }

        $sql = "SELECT * FROM `" . SHOPEE_SG_ORDER_REQ . "` WHERE id = " . $orderId . " LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            return (array) mysqli_fetch_assoc($result);
        }

        return array();
    }
}

if (!function_exists('shopeeOmsLoadOrderByCode')) {
    function shopeeOmsLoadOrderByCode($connect, $orderCode)
    {
        $orderCode = trim((string) $orderCode);
        if ($orderCode === '' || !($connect instanceof mysqli)) {
            return array();
        }

        $safeOrderCode = mysqli_real_escape_string($connect, $orderCode);
        $sql = "SELECT * FROM `" . SHOPEE_SG_ORDER_REQ . "` WHERE orderID = '" . $safeOrderCode . "' LIMIT 1";
        $result = mysqli_query($connect, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            return (array) mysqli_fetch_assoc($result);
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

if (!function_exists('shopeeOmsGetPackageNameMap')) {
    function shopeeOmsGetPackageNameMap($connect, $packageIds)
    {
        $nameMap = array();
        if (!($connect instanceof mysqli) || !is_array($packageIds) || empty($packageIds)) {
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

        return $nameMap;
    }
}

if (!function_exists('shopeeOmsResolveOrderPackageRows')) {
    function shopeeOmsResolveOrderPackageRows($connect, $orderRow)
    {
        if (!is_array($orderRow)) {
            return array();
        }

        $snapshotRows = shopeeOmsDecodePackageQtySnapshot(isset($orderRow['package_qty_json']) ? $orderRow['package_qty_json'] : '');
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
        $packageIds = array_filter(array_map('trim', explode(',', (string) (isset($orderRow['package']) ? $orderRow['package'] : ''))), 'strlen');
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
        $packageRows = shopeeOmsResolveOrderPackageRows($connect, $orderRow);
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

        $productSummary = array();
        foreach ($productQtyMap as $productId => $qty) {
            $productSummary[] = (isset($productNameMap[$productId]) ? $productNameMap[$productId] : ('Product #' . $productId)) . ' x' . (int) $qty;
        }

        return array(
            'package_rows' => $packageRows,
            'package_summary' => implode(', ', $packageSummary),
            'package_lines' => $packageSummary,
            'product_lines' => $productSummary,
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

            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/' . $strategy['endpoint'];
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
            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/' . $strategy['endpoint'];
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

if (!function_exists('shopeeOmsFindPreferredTokenSetting')) {
    function shopeeOmsFindPreferredTokenSetting($connect, $pageName = '')
    {
        if (!($connect instanceof mysqli)) {
            return array();
        }

        $pageName = trim((string) $pageName);
        if ($pageName !== '' && function_exists('shopeeOmsIsTokenSettingPageFieldAvailable') && shopeeOmsIsTokenSettingPageFieldAvailable($connect)) {
            $safePageName = mysqli_real_escape_string($connect, $pageName);
            $result = mysqli_query($connect, "SELECT * FROM `" . TOKEN_SETT . "` WHERE status = 'A' AND page_used = '" . $safePageName . "' ORDER BY id DESC LIMIT 1");
            if ($result && mysqli_num_rows($result) > 0) {
                return (array) mysqli_fetch_assoc($result);
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

if (!function_exists('shopeeOmsGetTokenSettingPageOptions')) {
    function shopeeOmsGetTokenSettingPageOptions()
    {
        return array(
            'Shopee Order Request' => 'Shopee Order Request',
            'Stock Order Request' => 'Stock Order Request',
        );
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

if (!function_exists('shopeeOmsBuildWarehouseMessage')) {
    function shopeeOmsBuildWarehouseMessage($orderRow, $tokenValue, $connect, $buyerConnect = null)
    {
        $summary = shopeeOmsBuildOrderProductSummary($connect, $orderRow);
        if (!($buyerConnect instanceof mysqli)) {
            $buyerConnect = $connect;
        }
        $customerName = trim((string) (isset($orderRow['buyer']) ? $orderRow['buyer'] : ''));
        if ($customerName !== '' && ctype_digit($customerName)) {
            $buyerRst = getData('buyer_username', "id='" . (int) $customerName . "'", 'LIMIT 1', SHOPEE_CUST_INFO, $buyerConnect);
            if ($buyerRst && $buyerRst->num_rows > 0) {
                $buyerRow = $buyerRst->fetch_assoc();
                if (isset($buyerRow['buyer_username']) && trim((string) $buyerRow['buyer_username']) !== '') {
                    $customerName = trim((string) $buyerRow['buyer_username']);
                }
            }
        }
        $link = rtrim((string) SITEURL, '/') . '/warehouse_stock_in_scan.php?t=' . rawurlencode((string) $tokenValue);
        $airbillText = trim((string) (isset($orderRow['airbill_no']) ? $orderRow['airbill_no'] : ''));
        $airbillAttachment = trim((string) (isset($orderRow['airbill_attachment']) ? $orderRow['airbill_attachment'] : ''));

        $lines = array();
        $lines[] = 'Shopee OID: ' . trim((string) (isset($orderRow['orderID']) ? $orderRow['orderID'] : ''));
        $lines[] = 'Shopee Buyer Username: ' . ($customerName !== '' ? $customerName : '-');
        $lines[] = 'Package: ' . (!empty($summary['bundle_name']) ? $summary['bundle_name'] : '-');
        $lines[] = 'Product Details: ' . (!empty($summary['product_lines']) ? implode(', ', $summary['product_lines']) : '-');
        if ($airbillText !== '') {
            $lines[] = 'Airbill: ' . $airbillText;
        }
        if ($airbillAttachment !== '') {
            $lines[] = 'Airbill Attachment: ' . basename($airbillAttachment);
        }
        $lines[] = 'Warehouse Stock-out Link: ' . $link;

        return array(
            'link' => $link,
            'text' => implode("\n", $lines),
            'buyer_username' => $customerName,
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

if (!function_exists('shopeeOmsRenderAirbillPdfAutofillScript')) {
    function shopeeOmsRenderAirbillPdfAutofillScript()
    {
        return <<<'JS'
if (!window.shopeeOmsAirbillPdfAutofill) {
    window.shopeeOmsAirbillPdfAutofill = (function () {
        function getPdfTextItemX(item) {
            return item && item.transform ? Number(item.transform[4]) || 0 : 0;
        }

        function getPdfTextItemY(item) {
            return item && item.transform ? Number(item.transform[5]) || 0 : 0;
        }

        function normalizePdfTextItem(item) {
            return String(item && item.str ? item.str : '').trim();
        }

        function sortPdfItemsForReading(items) {
            return items.slice().sort(function (a, b) {
                var yDiff = getPdfTextItemY(b) - getPdfTextItemY(a);
                if (Math.abs(yDiff) > 2) {
                    return yDiff;
                }
                return getPdfTextItemX(a) - getPdfTextItemX(b);
            });
        }

        function groupPdfItemsIntoLines(items) {
            var sortedItems = sortPdfItemsForReading(items);
            var lines = [];

            sortedItems.forEach(function (item) {
                var text = normalizePdfTextItem(item);
                if (text === '') {
                    return;
                }

                var itemY = getPdfTextItemY(item);
                var currentLine = lines.length > 0 ? lines[lines.length - 1] : null;
                if (!currentLine || Math.abs(currentLine.y - itemY) > 2) {
                    currentLine = {
                        y: itemY,
                        items: []
                    };
                    lines.push(currentLine);
                }

                currentLine.items.push(item);
            });

            return lines.map(function (line) {
                return line.items
                    .slice()
                    .sort(function (a, b) {
                        return getPdfTextItemX(a) - getPdfTextItemX(b);
                    })
                    .map(function (item) {
                        return normalizePdfTextItem(item);
                    })
                    .filter(function (text) {
                        return text !== '';
                    })
                    .join(' ')
                    .replace(/\s+,/g, ',')
                    .trim();
            }).filter(function (line) {
                return line !== '';
            });
        }

        function isLikelyAirbillCode(text) {
            var normalized = String(text || '').replace(/\s+/g, '').toUpperCase();
            if (normalized.length < 10) {
                return false;
            }
            if (!/[A-Z]/.test(normalized) || !/\d/.test(normalized)) {
                return false;
            }

            return /^(?:GDSP|MY)[A-Z0-9]{8,}$/.test(normalized) || /^[A-Z0-9]{10,}$/.test(normalized);
        }

        function extractAirbillCodeFromPdfItems(items, pageHeight) {
            var candidates = items
                .map(function (item) {
                    return {
                        text: normalizePdfTextItem(item).replace(/\s+/g, '').toUpperCase(),
                        x: getPdfTextItemX(item),
                        y: getPdfTextItemY(item)
                    };
                })
                .filter(function (item) {
                    return item.y >= (pageHeight * 0.65) && isLikelyAirbillCode(item.text);
                })
                .sort(function (a, b) {
                    if (Math.abs(b.y - a.y) > 2) {
                        return b.y - a.y;
                    }
                    return a.x - b.x;
                });

            return candidates.length > 0 ? candidates[0].text : '';
        }

        function extractRecipientAddressFromPdfItems(items, pageWidth) {
            var addressLabels = items
                .filter(function (item) {
                    return normalizePdfTextItem(item) === 'Address:' && getPdfTextItemX(item) <= (pageWidth * 0.2);
                })
                .sort(function (a, b) {
                    return getPdfTextItemY(b) - getPdfTextItemY(a);
                });

            if (addressLabels.length === 0) {
                return '';
            }

            var recipientAddressLabel = addressLabels[addressLabels.length - 1];
            var recipientPostcodeLabel = items
                .filter(function (item) {
                    return normalizePdfTextItem(item) === 'Postcode:' &&
                        getPdfTextItemX(item) <= (pageWidth * 0.2) &&
                        getPdfTextItemY(item) < getPdfTextItemY(recipientAddressLabel) - 10;
                })
                .sort(function (a, b) {
                    return getPdfTextItemY(b) - getPdfTextItemY(a);
                })[0] || null;

            var minX = getPdfTextItemX(recipientAddressLabel) + Number(recipientAddressLabel.width || 0) - 1;
            var minY = recipientPostcodeLabel ? getPdfTextItemY(recipientPostcodeLabel) + 8 : getPdfTextItemY(recipientAddressLabel) - 60;
            var maxY = getPdfTextItemY(recipientAddressLabel) + 1;
            var maxX = pageWidth * 0.62;
            var addressItems = items.filter(function (item) {
                var text = normalizePdfTextItem(item);
                if (text === '' || text === 'Address:' || text === 'Phone:' || text === 'Name:' || text === 'Postcode:') {
                    return false;
                }

                var itemX = getPdfTextItemX(item);
                var itemY = getPdfTextItemY(item);
                return itemX >= minX && itemX <= maxX && itemY <= maxY && itemY >= minY;
            });

            return groupPdfItemsIntoLines(addressItems).join('\n').trim();
        }

        function extractShopeeAirbillDataFromPdfItems(items, pageWidth, pageHeight) {
            return {
                airbillNo: extractAirbillCodeFromPdfItems(items, pageHeight),
                customerAddress: extractRecipientAddressFromPdfItems(items, pageWidth)
            };
        }

        function dispatchInputEvent(element) {
            if (!element) {
                return;
            }

            try {
                element.dispatchEvent(new Event('input', { bubbles: true }));
                element.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (error) {
            }
        }

        function bind(config) {
            config = config || {};
            var fileInput = document.querySelector(config.fileInputSelector || '');
            var airbillNo = document.querySelector(config.airbillNoSelector || '');
            var customerAddress = document.querySelector(config.customerAddressSelector || '');
            var statusNode = document.querySelector(config.statusSelector || '');
            if (!fileInput || !airbillNo || !customerAddress || !statusNode) {
                return false;
            }

            function setStatus(message, isError) {
                statusNode.textContent = message;
                if (config.errorClass) {
                    statusNode.classList.toggle(config.errorClass, !!isError);
                }
                if (config.normalClass) {
                    statusNode.classList.toggle(config.normalClass, !isError);
                }
            }

            if (typeof pdfjsLib === 'undefined') {
                setStatus('PDF extraction library failed to load on this page.', true);
                return false;
            }

            if (config.workerSrc) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = config.workerSrc;
            }

            if (fileInput.dataset.airbillPdfAutofillBound === '1') {
                return true;
            }

            function readFileAsArrayBuffer(file) {
                return new Promise(function (resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function (event) {
                        resolve(event.target.result);
                    };
                    reader.onerror = reject;
                    reader.readAsArrayBuffer(file);
                });
            }

            function loadPdfPageTextItems(file) {
                return readFileAsArrayBuffer(file).then(function (buffer) {
                    return pdfjsLib.getDocument({
                        data: new Uint8Array(buffer)
                    }).promise;
                }).then(function (pdfDoc) {
                    return pdfDoc.getPage(1).then(function (page) {
                        var viewport = page.getViewport({ scale: 1 });
                        return page.getTextContent().then(function (textContent) {
                            return {
                                items: (textContent.items || []).filter(function (item) {
                                    return normalizePdfTextItem(item) !== '';
                                }),
                                pageWidth: Number(viewport.width) || 0,
                                pageHeight: Number(viewport.height) || 0
                            };
                        });
                    });
                });
            }

            fileInput.addEventListener('change', function () {
                setStatus('', false);
                if (!this.files || !this.files[0]) {
                    return;
                }

                var selectedFile = this.files[0];
                if (!/\.pdf$/i.test(String(selectedFile.name || ''))) {
                    return;
                }

                setStatus('Extracting airbill number and address from PDF...', false);

                loadPdfPageTextItems(selectedFile).then(function (pdfData) {
                    var extractedData = extractShopeeAirbillDataFromPdfItems(
                        pdfData.items,
                        pdfData.pageWidth,
                        pdfData.pageHeight
                    );

                    if (extractedData.airbillNo !== '') {
                        airbillNo.value = extractedData.airbillNo;
                        dispatchInputEvent(airbillNo);
                    }
                    if (extractedData.customerAddress !== '') {
                        customerAddress.value = extractedData.customerAddress;
                        dispatchInputEvent(customerAddress);
                    }

                    if (extractedData.airbillNo !== '' || extractedData.customerAddress !== '') {
                        setStatus('Airbill PDF extracted successfully.', false);
                    } else {
                        setStatus('Unable to detect the airbill number or address from this PDF. Please fill them manually.', true);
                    }
                }).catch(function () {
                    setStatus('Unable to read this PDF. Please fill the airbill number and address manually.', true);
                });
            });

            fileInput.dataset.airbillPdfAutofillBound = '1';
            return true;
        }

        return {
            bind: bind,
            extractShopeeAirbillDataFromPdfItems: extractShopeeAirbillDataFromPdfItems
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
            $url = 'https://ipapi.co/' . rawurlencode($ip) . '/country/';
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
    function shopeeOmsCreateWarehouseToken($cmsConnect, $financeConnect, $orderRow, $actorUserId = '')
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

        $safeOrderId = (int) $orderId;
        $existingSql = "SELECT * FROM `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` WHERE order_id = " . $safeOrderId . " AND token_type = 'stock_out' AND status = 'A' ORDER BY id DESC LIMIT 1";
        $existingResult = mysqli_query($financeConnect, $existingSql);
        if ($existingResult && mysqli_num_rows($existingResult) > 0) {
            $existingRow = mysqli_fetch_assoc($existingResult);
            $messageInfo = shopeeOmsBuildWarehouseMessage($orderRow, isset($existingRow['token']) ? $existingRow['token'] : '', $cmsConnect, $financeConnect);
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

        $messageInfo = shopeeOmsBuildWarehouseMessage($orderRow, $tokenValue, $cmsConnect, $financeConnect);
        $actorUserId = trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM';
        $safeOrderCode = mysqli_real_escape_string($financeConnect, (string) (isset($orderRow['orderID']) ? $orderRow['orderID'] : ''));
        $safeToken = mysqli_real_escape_string($financeConnect, $tokenValue);
        $safeCustomerName = mysqli_real_escape_string($financeConnect, (string) (isset($messageInfo['buyer_username']) ? $messageInfo['buyer_username'] : ''));
        $safeCustomerAddress = mysqli_real_escape_string($financeConnect, (string) (isset($orderRow['customer_address']) ? $orderRow['customer_address'] : ''));
        $safePackageSummary = mysqli_real_escape_string($financeConnect, (string) $messageInfo['package_summary']);
        $safeProductSummary = mysqli_real_escape_string($financeConnect, (string) $messageInfo['product_summary']);
        $safeAirbillAttachment = mysqli_real_escape_string($financeConnect, (string) (isset($orderRow['airbill_attachment']) ? $orderRow['airbill_attachment'] : ''));
        $safePayload = mysqli_real_escape_string($financeConnect, (string) $messageInfo['text']);
        $safeActor = mysqli_real_escape_string($financeConnect, $actorUserId);
        $insertSql = "INSERT INTO `" . ORDER_WAREHOUSE_SCAN_TOKEN . "`
            (`order_id`, `order_code`, `token`, `token_type`, `customer_name`, `customer_address`, `package_summary`, `product_summary`, `airbill_attachment`, `payload_text`, `create_by`, `create_date`, `create_time`, `status`)
            VALUES
            (" . $safeOrderId . ", '" . $safeOrderCode . "', '" . $safeToken . "', 'stock_out', '" . $safeCustomerName . "', '" . $safeCustomerAddress . "', '" . $safePackageSummary . "', '" . $safeProductSummary . "', '" . $safeAirbillAttachment . "', '" . $safePayload . "', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";

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
                'order_code' => isset($orderRow['orderID']) ? $orderRow['orderID'] : '',
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

        $sourcePage = trim((string) (
            $sourcePage !== ''
                ? $sourcePage
                : (isset($notificationInfo['source_page']) ? $notificationInfo['source_page'] : '')
        ));
        $tokenSetting = shopeeOmsFindPreferredTokenSetting($cmsConnect, $sourcePage);
        if (empty($tokenSetting)) {
            $tokenNotSetMessage = $sourcePage !== ''
                ? 'Token not set yet, please set the Telegram token for ' . $sourcePage . '.'
                : 'Token not set yet, please set the Telegram token in Token Setting.';
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
                $messageUrl = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
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
                $fallbackUrl = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
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
                $messageUrl = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
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
                $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
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

        $sql = "INSERT INTO `" . ORDER_STATUS_TRANSITION_LOG . "`
            (`order_id`, `order_code`, `from_status`, `to_status`, `transition_action`, `user_id`, `user_group_id`, `remark`, `source_page`, `related_token_scan_id`, `transition_at`, `create_date`, `create_time`, `status`)
            VALUES
            (" . $orderId . ", '" . $safeOrderCode . "', '" . $safeFromStatus . "', '" . $safeToStatus . "', '" . $safeAction . "', '" . $safeUserId . "', " . $userGroupId . ", '" . $safeRemark . "', '" . $safeSourcePage . "', '" . $safeRelatedRef . "', NOW(), CURDATE(), CURTIME(), 'A')";

        return (bool) mysqli_query($connect, $sql);
    }
}

if (!function_exists('shopeeOmsExecuteTransition')) {
    function shopeeOmsExecuteTransition($cmsConnect, $financeConnect, $orderId, $targetStatus, $options = array())
    {
        $orderId = (int) $orderId;
        $targetStatus = shopeeOmsNormalizeStatusCode($targetStatus);
        $options = is_array($options) ? $options : array();
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

        $orderRow = shopeeOmsLoadOrder($financeConnect, $orderId);
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

        if (!$skipPermission && !shopeeOmsHasTransitionPermission($cmsConnect, $fromStatus, $targetStatus, $actorUserGroupId, $orderRow, $actorUserId)) {
            return array('success' => false, 'message' => 'You are not allowed to perform this status transition.');
        }

        $effectiveAirbill = isset($fieldUpdates['airbill_no']) ? (string) $fieldUpdates['airbill_no'] : (isset($orderRow['airbill_no']) ? $orderRow['airbill_no'] : '');
        $airbillValidation = shopeeOmsValidateInitialStatusAndAirbill($targetStatus, $effectiveAirbill);
        if (!$airbillValidation['valid']) {
            return array('success' => false, 'message' => $airbillValidation['message']);
        }

        $safeActorUserId = mysqli_real_escape_string($financeConnect, $actorUserId);
        $assignments = array(
            "order_status = '" . mysqli_real_escape_string($financeConnect, $targetStatus) . "'",
            "update_by = '" . $safeActorUserId . "'",
            "update_date = CURDATE()",
            "update_time = CURTIME()",
            "latest_transition_at = NOW()"
        );
        foreach ($fieldUpdates as $fieldName => $fieldValue) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $fieldName)) {
                continue;
            }

            if ($fieldValue === null) {
                $assignments[] = "`" . $fieldName . "` = NULL";
            } else if (is_int($fieldValue) || is_float($fieldValue)) {
                $assignments[] = "`" . $fieldName . "` = " . $fieldValue;
            } else {
                $assignments[] = "`" . $fieldName . "` = '" . mysqli_real_escape_string($financeConnect, (string) $fieldValue) . "'";
            }
        }

        $updateSql = "UPDATE `" . SHOPEE_SG_ORDER_REQ . "` SET " . implode(', ', $assignments) . " WHERE id = " . $orderId . " LIMIT 1";
        if (!mysqli_query($financeConnect, $updateSql)) {
            return array('success' => false, 'message' => 'Unable to update order status.');
        }

        shopeeOmsLogTransition($financeConnect, array(
            'order_id' => $orderId,
            'order_code' => isset($orderRow['orderID']) ? $orderRow['orderID'] : '',
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'transition_action' => $actionName,
            'user_id' => $actorUserId,
            'user_group_id' => $actorUserGroupId,
            'remark' => $remark,
            'source_page' => $sourcePage,
            'related_token_scan_id' => $relatedRef,
        ));

        $stepAResult = array();
        if ($targetStatus === 'TP') {
            $freshOrderRow = shopeeOmsLoadOrder($financeConnect, $orderId);
            $tokenResult = shopeeOmsCreateWarehouseToken($cmsConnect, $financeConnect, $freshOrderRow, $actorUserId);
            if (!empty($tokenResult['success']) && !empty($tokenResult['token_row']) && !empty($tokenResult['notification'])) {
                $notifyResult = shopeeOmsSendWarehouseNotification($cmsConnect, $financeConnect, $tokenResult['token_row'], $tokenResult['notification'], $sourcePage);
                if (!empty($notifyResult['sent'])) {
                    mysqli_query($financeConnect, "UPDATE `" . SHOPEE_SG_ORDER_REQ . "` SET `step_a_sent_at` = NOW() WHERE id = " . $orderId . " LIMIT 1");
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
    function shopeeOmsAssignEstimatedReceivedDate($connect, $tableName, $orderId, $date, $currentUserId)
    {
        $validation = validateEstimatedReceivedDate($date);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => $validation['message'],
            );
        }

        $tableName = trim((string) $tableName);
        if ($tableName !== SHOPEE_SG_ORDER_REQ) {
            return array(
                'success' => false,
                'message' => 'Estimated date assignment is only supported for Shopee OMS orders.',
            );
        }

        global $finance_connect;
        $financeDb = $connect instanceof mysqli ? $connect : $finance_connect;
        if (!($financeDb instanceof mysqli)) {
            return array(
                'success' => false,
                'message' => 'Unable to connect to Shopee OMS order table.',
            );
        }

        $orderRow = shopeeOmsLoadOrder($financeDb, $orderId);
        if (empty($orderRow)) {
            return array(
                'success' => false,
                'message' => 'Order not found.',
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
            $result = shopeeOmsExecuteTransition($GLOBALS['connect'], $financeDb, $orderId, 'WR', array(
                'actor_user_id' => $safeCurrentUserId,
                'actor_user_group_id' => defined('USER_GROUP') ? (int) USER_GROUP : 0,
                'source_page' => 'Arrival Management',
                'remark' => 'Estimated Received Date assigned: ' . $validation['normalized_date'],
                'action' => 'assign_estimated_received_date',
                'field_updates' => $fieldUpdates,
                'allow_auto_follow_up' => false,
            ));
            if (!empty($result['success'])) {
                $result['date'] = $validation['normalized_date'];
            }
            return $result;
        }

        $safeDate = mysqli_real_escape_string($financeDb, $validation['normalized_date']);
        $safeUser = mysqli_real_escape_string($financeDb, $safeCurrentUserId);
        $updateSql = "UPDATE `" . SHOPEE_SG_ORDER_REQ . "`
            SET `estimated_received_date` = '" . $safeDate . "',
                `estimated_received_date_assigned_by` = '" . $safeUser . "',
                `estimated_received_date_assigned_date` = CURDATE(),
                `estimated_received_date_assigned_time` = CURTIME(),
                `update_by` = '" . $safeUser . "',
                `update_date` = CURDATE(),
                `update_time` = CURTIME()
            WHERE id = " . (int) $orderId . "
            LIMIT 1";
        if (!mysqli_query($financeDb, $updateSql)) {
            return array(
                'success' => false,
                'message' => 'Unable to update Estimated Received Date.',
            );
        }

        return array(
            'success' => true,
            'message' => 'Estimate Received Date updated successfully.',
            'date' => $validation['normalized_date'],
            'old_status' => $currentStatus,
            'new_status' => $currentStatus,
        );
    }
}

if (!function_exists('shopeeOmsResolveIdCsvToNames')) {
    function shopeeOmsResolveIdCsvToNames($connect, $tableName, $csvValue)
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

        $result = mysqli_query($connect, "SELECT id, name FROM `" . $tableName . "` WHERE id IN (" . implode(',', $idMap) . ")");
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
            'customer_name' => 'Shopee Buyer Username',
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
    function shopeeOmsLogOrderEditHistory($financeConnect, $orderId, $orderCode, $changes, $userId, $userGroupId, $sourcePage = 'Shopee Order Request')
    {
        if (!($financeConnect instanceof mysqli) || empty($changes) || !is_array($changes)) {
            return false;
        }

        $safeUserId = mysqli_real_escape_string($financeConnect, trim((string) $userId) !== '' ? trim((string) $userId) : 'SYSTEM');
        $safeOrderCode = mysqli_real_escape_string($financeConnect, (string) $orderCode);
        $safeSourcePage = mysqli_real_escape_string($financeConnect, (string) $sourcePage);
        foreach ($changes as $changeRow) {
            $safeFieldName = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['field_name']) ? $changeRow['field_name'] : ''));
            $safeFieldLabel = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['field_label']) ? $changeRow['field_label'] : ''));
            $safeOldValue = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['old_value']) ? $changeRow['old_value'] : ''));
            $safeNewValue = mysqli_real_escape_string($financeConnect, (string) (isset($changeRow['new_value']) ? $changeRow['new_value'] : ''));
            $sql = "INSERT INTO `" . ORDER_EDIT_HISTORY . "`
                (`order_id`, `order_code`, `field_name`, `field_label`, `old_value`, `new_value`, `user_id`, `user_group_id`, `source_page`, `change_at`, `create_date`, `create_time`, `status`)
                VALUES
                (" . (int) $orderId . ", '" . $safeOrderCode . "', '" . $safeFieldName . "', '" . $safeFieldLabel . "', '" . $safeOldValue . "', '" . $safeNewValue . "', '" . $safeUserId . "', " . (int) $userGroupId . ", '" . $safeSourcePage . "', NOW(), CURDATE(), CURTIME(), 'A')";
            mysqli_query($financeConnect, $sql);
        }

        return true;
    }
}

if (!function_exists('shopeeOmsFetchEditHistory')) {
    function shopeeOmsFetchEditHistory($financeConnect, $orderId)
    {
        $rows = array();
        if (!($financeConnect instanceof mysqli) || (int) $orderId <= 0) {
            return $rows;
        }

        $sql = "SELECT * FROM `" . ORDER_EDIT_HISTORY . "` WHERE order_id = " . (int) $orderId . " AND status = 'A' ORDER BY change_at DESC, id DESC";
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
    function shopeeOmsFetchTransitionHistory($financeConnect, $orderId)
    {
        $rows = array();
        if (!($financeConnect instanceof mysqli) || (int) $orderId <= 0) {
            return $rows;
        }

        $sql = "SELECT * FROM `" . ORDER_STATUS_TRANSITION_LOG . "` WHERE order_id = " . (int) $orderId . " AND status = 'A' ORDER BY transition_at DESC, id DESC";
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
    function shopeeOmsGetDailyFlowReport($cmsConnect, $financeConnect, $dateFrom, $dateTo, $fromStatus = '', $toStatus = '', $orderCode = '', $warehouseId = 0)
    {
        $summary = array();
        $details = array();
        if (!($financeConnect instanceof mysqli)) {
            return array('summary' => $summary, 'details' => $details);
        }

        $dateFrom = trim((string) $dateFrom);
        $dateTo = trim((string) $dateTo);
        if ($dateFrom === '') {
            $dateFrom = date('Y-m-d');
        }
        if ($dateTo === '') {
            $dateTo = date('Y-m-d');
        }

        $warehouseId = shopeeOmsNormalizeWarehouseId($warehouseId);
        $defaultWarehouseId = $cmsConnect instanceof mysqli ? shopeeOmsGetDefaultWarehouseId($cmsConnect) : 0;
        $conditions = array();
        $conditions[] = "l.status = 'A'";
        $conditions[] = "DATE(l.transition_at) >= '" . mysqli_real_escape_string($financeConnect, $dateFrom) . "'";
        $conditions[] = "DATE(l.transition_at) <= '" . mysqli_real_escape_string($financeConnect, $dateTo) . "'";
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
            $conditions[] = "COALESCE(NULLIF(o.stock_out_warehouse_id, 0), " . (int) $defaultWarehouseId . ") = " . $warehouseId;
        }

        $whereSql = implode(' AND ', $conditions);
        $logFromSql = "`" . ORDER_STATUS_TRANSITION_LOG . "` l
            LEFT JOIN `" . SHOPEE_SG_ORDER_REQ . "` o ON o.id = l.order_id";
        $summarySql = "SELECT l.from_status, l.to_status, COUNT(*) AS total_count, MAX(l.transition_at) AS last_transition_time
            FROM " . $logFromSql . "
            WHERE " . $whereSql . "
            GROUP BY l.from_status, l.to_status
            ORDER BY total_count DESC, l.from_status ASC, l.to_status ASC";
        $summaryResult = mysqli_query($financeConnect, $summarySql);
        if ($summaryResult) {
            while ($row = mysqli_fetch_assoc($summaryResult)) {
                $transitionKey = shopeeOmsBuildTransitionKey(isset($row['from_status']) ? $row['from_status'] : '', isset($row['to_status']) ? $row['to_status'] : '');
                $summary[] = array(
                    'transition_key' => $transitionKey,
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
                $transitionKey = shopeeOmsBuildTransitionKey(isset($row['from_status']) ? $row['from_status'] : '', isset($row['to_status']) ? $row['to_status'] : '');
                if (!isset($details[$transitionKey])) {
                    $details[$transitionKey] = array();
                }
                $details[$transitionKey][] = $row;
            }
        }

        return array(
            'summary' => $summary,
            'details' => $details,
        );
    }
}

if (!function_exists('shopeeOmsRunOverduePostponedAutoMove')) {
    function shopeeOmsRunOverduePostponedAutoMove($cmsConnect, $financeConnect)
    {
        if (!($financeConnect instanceof mysqli)) {
            return 0;
        }

        $movedCount = 0;
        $todayYmd = date('Y-m-d');
        $sql = "SELECT id, delay_remark, estimated_received_date
            FROM `" . SHOPEE_SG_ORDER_REQ . "`
            WHERE status = 'A'
              AND order_status IN ('WR', 'AED', 'Waiting Receive', 'Assigned Estimate Date')";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $estimatedReceivedDate = trim((string) (isset($row['estimated_received_date']) ? $row['estimated_received_date'] : ''));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $estimatedReceivedDate)) {
                    continue;
                }
                if ($estimatedReceivedDate >= $todayYmd) {
                    continue;
                }

                $delayRemark = trim((string) (isset($row['delay_remark']) ? $row['delay_remark'] : ''));
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
                        'delay_remark' => $delayRemark,
                    ),
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
    function shopeeOmsRunFourteenDayAutoMove($cmsConnect, $financeConnect)
    {
        if (!($financeConnect instanceof mysqli)) {
            return 0;
        }

        $movedCount = 0;
        $sql = "SELECT id FROM `" . SHOPEE_SG_ORDER_REQ . "`
            WHERE status = 'A'
              AND order_status IN ('PR', 'Parcel Received')
              AND COALESCE(latest_transition_at, CONCAT(update_date, ' ', update_time), CONCAT(create_date, ' ', create_time)) <= DATE_SUB(NOW(), INTERVAL 14 DAY)";
        $result = mysqli_query($financeConnect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $transitionResult = shopeeOmsExecuteTransition($cmsConnect, $financeConnect, (int) $row['id'], 'WAFC', array(
                    'actor_user_id' => 'SYSTEM',
                    'actor_user_group_id' => 1,
                    'source_page' => 'OMS Housekeeping',
                    'remark' => 'Auto move after 14 days in Parcel Received.',
                    'action' => 'auto_14_day_final_check',
                    'skip_permission' => true,
                    'allow_auto_follow_up' => false,
                ));
                if (!empty($transitionResult['success'])) {
                    $movedCount++;
                }
            }
        }

        return $movedCount;
    }
}

if (!function_exists('shopeeOmsDeductInventoryForOrder')) {
    function shopeeOmsDeductInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId = 'SYSTEM', $scanReference = '')
    {
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli) || !is_array($orderRow)) {
            return array('success' => false, 'message' => 'Unable to connect to warehouse inventory.');
        }

        $productSummary = shopeeOmsBuildOrderProductSummary($cmsConnect, $orderRow);
        $productQtyMap = isset($productSummary['product_qty_map']) && is_array($productSummary['product_qty_map']) ? $productSummary['product_qty_map'] : array();
        if (empty($productQtyMap)) {
            return array('success' => false, 'message' => 'No product item found for this order package.');
        }

        $defaultWarehouseId = shopeeOmsGetDefaultWarehouseId($cmsConnect);
        $warehouseId = shopeeOmsResolveStockOutWarehouseId($cmsConnect, $orderRow, $defaultWarehouseId);
        $warehouseName = shopeeOmsResolveWarehouseNameById($cmsConnect, $warehouseId, $defaultWarehouseId);
        $orderCode = trim((string) (isset($orderRow['orderID']) ? $orderRow['orderID'] : ''));
        if ($orderCode === '') {
            $orderCode = 'OMS-' . (int) (isset($orderRow['id']) ? $orderRow['id'] : 0);
        }

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

        mysqli_begin_transaction($financeConnect);
        try {
            $updatedItemIds = array();
            foreach ($productQtyMap as $productId => $qty) {
                $productId = (int) $productId;
                $qty = (int) $qty;
                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                $selectSql = "SELECT i.id, i.product_quantity, i.stock_in_order_id
                    FROM `stock_in_order_item` i
                    INNER JOIN `stock_in_order` o ON o.id = i.stock_in_order_id
                    WHERE i.status = 'A'
                      AND o.status = 'A'
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
                    $productLabel = isset($productNameMap[$productId]) && $productNameMap[$productId] !== ''
                        ? $productNameMap[$productId]
                        : ('product #' . $productId);
                    $warehouseLabel = $warehouseName !== '' ? ($warehouseName) : '';
                    throw new Exception($warehouseLabel . ' not enough warehouse stock for ' . $productLabel . '.');
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

                    $updatedItemIds[] = $itemId;
                    $remainingQty -= $deductQty;
                }
            }

            mysqli_commit($financeConnect);
            return array(
                'success' => true,
                'message' => 'Warehouse inventory deducted successfully.',
                'item_ids' => $updatedItemIds,
                'product_qty_map' => $productQtyMap,
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

        $orderRow = shopeeOmsLoadOrder($financeConnect, $orderId);
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

if (!function_exists('shopeeOmsRestockInventoryForOrder')) {
    function shopeeOmsRestockInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId = 'SYSTEM')
    {
        if (!($cmsConnect instanceof mysqli) || !($financeConnect instanceof mysqli) || !is_array($orderRow)) {
            return array('success' => false, 'message' => 'Unable to connect to warehouse inventory.');
        }

        $productSummary = shopeeOmsBuildOrderProductSummary($cmsConnect, $orderRow);
        $productQtyMap = isset($productSummary['product_qty_map']) && is_array($productSummary['product_qty_map']) ? $productSummary['product_qty_map'] : array();
        if (empty($productQtyMap)) {
            return array('success' => false, 'message' => 'No product item found for this order package.');
        }

        $orderCode = trim((string) (isset($orderRow['orderID']) ? $orderRow['orderID'] : ''));
        if ($orderCode === '') {
            $orderCode = 'OMS-' . (int) (isset($orderRow['id']) ? $orderRow['id'] : 0);
        }

        $warehouseId = shopeeOmsResolveStockOutWarehouseId($cmsConnect, $orderRow, shopeeOmsGetDefaultWarehouseId($cmsConnect));
        $safeActor = mysqli_real_escape_string($financeConnect, trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'SYSTEM');
        $restockOrderNumber = mysqli_real_escape_string($financeConnect, 'OMS-RETURN-' . $orderCode . '-' . date('YmdHis'));

        mysqli_begin_transaction($financeConnect);
        try {
            $insertOrderSql = "INSERT INTO `stock_in_order`
                (`warehouse_id`, `order_number`, `stock_in_date`, `attachment`, `create_by`, `create_date`, `create_time`, `status`)
                VALUES
                (" . (int) $warehouseId . ", '" . $restockOrderNumber . "', CURDATE(), '', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
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
    function shopeeOmsProcessWarehouseScanByToken($cmsConnect, $financeConnect, $tokenValue, $actorUserId = 'QR_PUBLIC', $actorUserGroupId = 0, $sourcePage = 'Warehouse Stock-out Scan')
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

        $orderRow = shopeeOmsLoadOrder($financeConnect, isset($tokenRow['order_id']) ? (int) $tokenRow['order_id'] : 0);
        if (empty($orderRow)) {
            return array('success' => false, 'message' => 'Order linked to this scan token was not found.');
        }

        if (shopeeOmsNormalizeStatusCode(isset($orderRow['order_status']) ? $orderRow['order_status'] : '') !== 'TP') {
            return array('success' => false, 'message' => 'This order is no longer waiting for warehouse stock-out.');
        }

        if ((int) $actorUserGroupId > 0 && !shopeeOmsHasTransitionPermission($cmsConnect, isset($orderRow['order_status']) ? $orderRow['order_status'] : '', 'SP', $actorUserGroupId, $orderRow, $actorUserId)) {
            return array('success' => false, 'message' => 'You do not have permission to perform this warehouse stock-out scan.');
        }

        $deductResult = shopeeOmsDeductInventoryForOrder($cmsConnect, $financeConnect, $orderRow, $actorUserId, $tokenValue);
        if (empty($deductResult['success'])) {
            return $deductResult;
        }

        $safeActor = mysqli_real_escape_string($financeConnect, trim((string) $actorUserId) !== '' ? trim((string) $actorUserId) : 'QR_PUBLIC');
        mysqli_query($financeConnect, "UPDATE `" . ORDER_WAREHOUSE_SCAN_TOKEN . "` SET `used_at` = NOW(), `used_by` = '" . $safeActor . "', `used_source` = '" . mysqli_real_escape_string($financeConnect, $sourcePage) . "', `update_by` = '" . $safeActor . "', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE id = " . (int) $tokenRow['id'] . " LIMIT 1");

        return shopeeOmsExecuteTransition($cmsConnect, $financeConnect, (int) $orderRow['id'], 'SP', array(
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
        ));
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
     * Map courier name (from DB) to tracking.my URL slug.
     * Also tries to auto-detect from tracking number prefix.
     */
    function sorResolveTrackingMySlug($courierName, $trackingNo)
    {
        $courierName = strtolower(trim((string) $courierName));
        $trackingNo = strtoupper(trim((string) $trackingNo));

        // Map courier names to tracking.my slugs
        $nameMap = array(
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
            'shopee express' => 'shopee',
            'shopee' => 'shopee',
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

        // Auto-detect from tracking number prefix
        if (preg_match('/^MYJZ/i', $trackingNo)) {
            return 'dhl-ecommerce';
        }
        if (preg_match('/^SPXMY|^SPX/i', $trackingNo)) {
            return 'shopee';
        }
        if (preg_match('/^MY[A-Z]{2}\d/i', $trackingNo)) {
            // Generic MY prefix â†’ DHL eCommerce (most common)
            return 'dhl-ecommerce';
        }
        if (preg_match('/^JT/i', $trackingNo)) {
            return 'jt';
        }
        if (preg_match('/^NV/i', $trackingNo)) {
            return 'ninjavan';
        }
        if (preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $trackingNo)) {
            return 'pos'; // Pos Malaysia international format
        }
        if (preg_match('/^\d{10,}$/', $trackingNo)) {
            return 'pos'; // Pos Malaysia uses long numeric tracking numbers
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
     * Fetch actual tracking status from tracking.my via its WebSocket API.
     * 1. GET the tracking.my page to extract the pre-built WebSocket message
     *    (contains a server-computed verify hash).
     * 2. Open a WebSocket connection and send the message.
     * 3. Parse the JSON response for the latest tracking event.
     */
    function sorFetchTrackingMyWebSocket($courierName, $trackingNo, &$rawJson = null)
    {
        $trackingNo = trim((string) $trackingNo);
        if ($trackingNo === '') return '';

        $slug = sorResolveTrackingMySlug($courierName, $trackingNo);
        if ($slug === '') return '';

        $pageUrl = 'https://www.tracking.my/' . $slug . '/' . rawurlencode($trackingNo);
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36\r\n" .
                            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n",
                'timeout' => 15,
            ),
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        );
        $body = @file_get_contents($pageUrl, false, stream_context_create($opts));
        if ($body === false || $body === '') return '';

        // The page embeds: socket.send("{&quot;action&quot;:...&quot;verify&quot;:&quot;HASH&quot;}")
        if (!preg_match('/socket\.send\(\s*"([^"]+)"\s*\)/', $body, $m)) return '';

        $wsMessage = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        $wsCheck = json_decode($wsMessage, true);
        if (!is_array($wsCheck) || !isset($wsCheck['action'])) return '';

        // Open WebSocket connection
        $wsKey = base64_encode(openssl_random_pseudo_bytes(16));
        $ctx = stream_context_create(array(
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        ));
        $sock = @stream_socket_client('ssl://www.tracking.my:443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) return '';

        stream_set_timeout($sock, 10);

        // WebSocket upgrade handshake
        $handshake = "GET /websocket HTTP/1.1\r\n" .
            "Host: www.tracking.my\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Key: $wsKey\r\n" .
            "Sec-WebSocket-Version: 13\r\n" .
            "Origin: https://www.tracking.my\r\n" .
            "\r\n";
        @fwrite($sock, $handshake);

        // Read upgrade response
        $resp = '';
        while (!feof($sock)) {
            $line = @fgets($sock, 1024);
            if ($line === false) break;
            $resp .= $line;
            if ($line === "\r\n") break;
        }
        if (strpos($resp, '101') === false) {
            @fclose($sock);
            return '';
        }

        // Send the tracking request
        @fwrite($sock, sorWsEncode($wsMessage));

        // Read the tracking response
        $data = sorWsDecode($sock);
        @fclose($sock);

        if ($data === '') return '';

        $result = json_decode($data, true);
        $rawJson = $data; // expose raw response for diagnostics
        if (!is_array($result) || !isset($result['result']) || !is_array($result['result'])) return '';

        // tracking.my response: { result: [ {status, content, date, location, ...}, ... ] }
        // 'status' is a CATEGORY (e.g. "delivered", "exception", "in_transit")
        // 'content' is the human-readable description (e.g. "Parcel has been delivered", "Shipment cancelled")
        // result[0] is the LATEST event.

        // First, find the latest non-sponsored event
        $latestStatus = '';
        $latestContent = '';
        foreach ($result['result'] as $event) {
            if (!is_array($event)) continue;
            $evStatus = isset($event['status']) ? trim((string) $event['status']) : '';
            if ($evStatus === '' || $evStatus === 'sponsored') continue;
            $latestStatus = $evStatus;
            $latestContent = isset($event['content']) ? trim((string) $event['content']) : '';
            break; // first non-sponsored = latest
        }

        if ($latestStatus === '') return '';

        // Try to derive a more specific status from the content text
        $contentLower = strtolower($latestContent);
        $contentKeywords = array(
            'cancel' => 'Cancelled',
            'returned to sender' => 'Returned to Sender',
            'delivered' => 'Delivered',
            'out for delivery' => 'Out for Delivery',
            'in transit' => 'In Transit',
            'picked up' => 'Picked Up',
            'preparing' => 'Shipment Information Received',
            'information received' => 'Shipment Information Received',
        );

        $displayStatus = ucfirst($latestStatus); // default: use category name
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

        $statusText = sorFetchTrackingStatusEasyParcel($trackingNo, $courierCountryCode);
        $trackingUrl = sorBuildTrackingUrl($trackingLink, $trackingNo);

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
            // Resolve courier name for tracking.my slug (already fetched from DB above)
            $wsStatus = sorFetchTrackingMyWebSocket($courierNameForSlug, $trackingNo);
            if ($wsStatus !== '') {
                $statusText = $wsStatus;
            } else {
                // Final fallback: keyword scrape on tracking.my page
                $slug = sorResolveTrackingMySlug($courierNameForSlug, $trackingNo);
                if ($slug !== '') {
                    $altUrl = 'https://www.tracking.my/' . $slug . '/' . rawurlencode($trackingNo);
                    $altStatus = sorFetchTrackingStatus($altUrl);
                    if (stripos($altStatus, 'Detected:') !== false) {
                        $statusText = $altStatus . ' | Source: tracking.my';
                    }
                }
                // If all fallbacks failed and the current status is an error, clear it
                if ($statusIsError && stripos($statusText, 'Unable to retrieve') !== false) {
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
        $rst = mysqli_query($connect, "SELECT id, name FROM " . WHSE . " WHERE status='A' ORDER BY name ASC");
        if ($rst) {
            while ($r = mysqli_fetch_assoc($rst)) {
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
        $rst = mysqli_query($connect, "SELECT id, name FROM " . PROD . " WHERE status='A' ORDER BY name ASC");
        if ($rst) {
            while ($r = mysqli_fetch_assoc($rst)) {
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
        $rst = mysqli_query($connect, "SELECT id, name, product FROM " . PKG . " WHERE status='A' ORDER BY name ASC");
        if ($rst) {
            while ($r = mysqli_fetch_assoc($rst)) {
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
                (warehouse_id, order_number, stock_in_date, attachment, create_by, create_date, create_time, status)
                VALUES
                ('" . $warehouseId . "', '" . $safeOrderNumber . "', '" . $safeDate . "', '" . $safeAttachment . "', '" . USER_ID . "', CURDATE(), CURTIME(), 'A')";

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
                    i.product_id,
                    i.package_id,
                    i.product_quantity
                FROM `" . $orderTable . "` o
                INNER JOIN `" . $itemTable . "` i ON i.stock_in_order_id=o.id AND i.status='A'
                WHERE o.status='A'
                ORDER BY o.id DESC, i.id ASC";
        $rst = mysqli_query($financeConnect, $sql);
        if ($rst) {
            while ($r = mysqli_fetch_assoc($rst)) {
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
        $sql = "SELECT id FROM `" . $orderTable . "` WHERE status='A' AND warehouse_id='" . $warehouseId . "' AND stock_in_date='" . $safeDate . "' AND order_number='" . $safeOrderNo . "' LIMIT 1";
        $rst = mysqli_query($financeConnect, $sql);
        if ($rst && ($row = mysqli_fetch_assoc($rst))) {
            return (int) $row['id'];
        }
        return 0;
    }
}
