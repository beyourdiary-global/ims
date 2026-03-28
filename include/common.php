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
		$query = "SELECT COUNT(*) as count FROM `$tbl` WHERE `$fieldName` = '$fieldValue' AND `status` = 'A'";
		//Help to check the query where wrong
		// If editing an existing record, exclude the current record from the duplicate check
		if ($primaryKeyValue) {
			$query .= " AND id != '$primaryKeyValue'";
		}

		$result = mysqli_query($connect, $query);

		if ($result) {
			$row = mysqli_fetch_assoc($result);
			$count = $row['count'];
			return $count > 0; // If count is greater than 0, it's a duplicate
		}
	}
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

	$whereClause = implode(' AND ', array_map(
		fn($key, $value) => "`$key` = '" . mysqli_real_escape_string($connect, $value) . "'",
		array_keys($conditions),
		$conditions
	));

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
            error_log("Query failed: $query — Error: " . mysqli_error($conn));
            echo $query;exit;
        }
	}

	if (empty($result) || !is_object($result) || $result->num_rows == 0) {
		return false;
	} else {
		return $result;
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


function audit_log($data = array())
{
	if (count($data) > 0) {
		extract($data);

		switch (strtolower($log_act)) {
			case 'view':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, user_id, action_message, create_date, create_time, create_by) VALUES ('1', '$page', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'edit':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, old_value, changes, user_id, action_message, create_date, create_time, create_by) VALUES ('2', '$page', \"$query_rec\", '$query_table', '$oldval', '$changes', '$uid', \"$act_msg\", '$cdate', '$ctime', '$cby')";
				break;
			case 'delete':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, user_id, action_message, create_date, create_time, create_by) VALUES ('3', '$page', \"$query_rec\", '$query_table', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'add':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, new_value, user_id, action_message, create_date, create_time, create_by) VALUES ('4', '$page', \"$query_rec\", '$query_table', '$newval', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'import':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, new_value, user_id, action_message, create_date, create_time, create_by) VALUES ('5', '$page', \"$query_rec\", '$query_table', '$newval', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'export':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, user_id, action_message, create_date, create_time, create_by) VALUES ('6', '$page', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'login':
				$query = "INSERT INTO " . AUDIT_LOG . " (screen_type, log_action, user_id, action_message, create_date, create_time, create_by) VALUES ('Login Screen', '7', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'logout':
				$query = "INSERT INTO " . AUDIT_LOG . " (screen_type, log_action, user_id, action_message, create_date, create_time, create_by) VALUES ('Login Screen', '8', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'check':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, user_id, action_message, create_date, create_time, create_by) VALUES ('9', '$page', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'approval':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, user_id, action_message, create_date, create_time, create_by) VALUES ('10', '$page', \"$query_rec\", '$query_table', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'declined':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, user_id, action_message, create_date, create_time, create_by) VALUES ('11', '$page', \"$query_rec\", '$query_table', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'cancel':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, user_id, action_message, create_date, create_time, create_by) VALUES ('12', '$page', \"$query_rec\", '$query_table', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'search':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, user_id, action_message, create_date, create_time, create_by) VALUES ('12', '$page', \"$query_rec\", '$query_table', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
			case 'reset':
				$query = "INSERT INTO " . AUDIT_LOG . " (log_action, screen_type, query_record, query_table, user_id, action_message, create_date, create_time, create_by) VALUES ('12', '$page', \"$query_rec\", '$query_table', '$uid', '$act_msg', '$cdate', '$ctime', '$cby')";
				break;
		}

		if (isset($query))
			mysqli_query($connect, $query);
	}
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
	return implode(",", $data);
}


function actMsgLog($id, $datafield = array(), $newvalarr = array(), $oldvalarr = array(), $chgvalarr = array(), $tblName, $action, $errorMsg)
{
	$action = strtolower($action);

	$actMsg = USER_NAME . (empty($errorMsg) ? " " : " fail to ") . $action . "  the data [ <b> ID = " . $id . " </b> ]";

	switch ($action) {
		case 'add':
			for ($i = 0; $i < sizeof($datafield); $i++) {
				if ($i == 0)
					$actMsg .= " [ <b> " . $datafield[$i] . " </b> : <b>\'" . $newvalarr[$i] . "\'</b>  ]";
				else
					$actMsg .= " , [ <b> " . $datafield[$i] . " </b> : <b>\'" . $newvalarr[$i] . "\'</b> ]";
			}
			break;
		case 'edit':

			for ($i = 0; $i < sizeof($datafield); $i++) {
				if ($i == 0)
					$actMsg .= " [ <b> " . $datafield[$i] . " </b> : <b>\'" . $oldvalarr[$i] . "\'</b> to <b>\'" . $chgvalarr[$i] . "\'</b> ]";
				else
					$actMsg .= " , [ <b> " . $datafield[$i] . " </b> : <b>\'" . $oldvalarr[$i] . "\'</b> to <b>\'" . $chgvalarr[$i] . "\'</b> ]";
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

function getOrderStatusLabel($code) {
	$normalized = strtolower(trim((string) $code));
	$normalizedKey = preg_replace('/[^a-z]/', '', $normalized);
	if ($normalizedKey === 'p' || $normalizedKey === 'pendingto' || $normalizedKey === 'pendingtopack') {
		return 'Pending To Pack';
	}

    $statuses = [
		'P'  => 'Pending To Pack',
        'SP' => 'SHIP PROCESSING (Warehouse)',
        'WP' => 'Waiting Packing',
        'OC' => 'Order Received (admin checking)',
        'V'  => 'Verified (Aster checking)',
        'C'  => 'Completed',
    ];

    return $statuses[$code] ?? $code; // fallback to code if not found
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
                'header' => "User-Agent: Mozilla/5.0 StockOrderTrackingBot\r\n",
                'timeout' => 12,
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

        $plain = strtolower(strip_tags($body));
        $keywords = array('delivered', 'out for delivery', 'in transit', 'exception', 'picked up', 'shipment information received', 'shipment canceled', 'shipment cancelled');
        $found = '';
        foreach ($keywords as $keyword) {
            if (strpos($plain, $keyword) !== false) {
                $found = $keyword;
                break;
            }
        }

        $parts = array();
        $parts[] = "HTTP $httpCode";
        if ($title !== '') {
            $parts[] = 'Title: ' . $title;
        }
        if ($found !== '') {
            $parts[] = 'Detected: ' . ucwords($found);
        }
        $parts[] = 'Synced: ' . $timestamp;

        return implode(' | ', $parts);
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

        // Typical EasyParcel success code is 0.
        $apiStatus = isset($json['api_status']) ? (string) $json['api_status'] : '';
        if ($apiStatus !== '' && $apiStatus !== '0') {
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
        if ($status === '') {
            return 'EasyParcel tracking returned no status.';
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
        $courierCountryCode = 'MY';
        $lookupConnect = $cmsConnect ? $cmsConnect : $financeConnect;
        if ($courierId > 0) {
            $courierSql = "SELECT tracking_link, country FROM " . COURIER . " WHERE id = '$courierId' LIMIT 1";
            $courierRst = mysqli_query($lookupConnect, $courierSql);
            if ($courierRst && ($courierRow = mysqli_fetch_assoc($courierRst))) {
                $trackingLink = isset($courierRow['tracking_link']) ? trim((string) $courierRow['tracking_link']) : '';
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

        // Fallback to courier tracking page scrape when API cannot provide a usable status.
        if ($statusText === '' || stripos($statusText, 'failed:') !== false || stripos($statusText, 'unavailable') !== false || stripos($statusText, 'no status') !== false) {
            if ($trackingUrl !== '') {
                $statusText = sorFetchTrackingStatus($trackingUrl);
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
