<?php
function findPinGroupRowByPage($connect, $currentPage)
{
    $currentPage = trim((string) $currentPage);

    if ($currentPage !== '') {
        $safePage = mysqli_real_escape_string($connect, $currentPage);
        $exactResult = getData('*', "name = '$safePage'", '', PIN_GRP, $connect);

        if ($exactResult && $exactResult->num_rows > 0) {
            return $exactResult->fetch_assoc();
        }
    }

    // Build a conservative token set for fallback matching when a pin group was renamed.
    $scriptName = isset($_SERVER['PHP_SELF']) ? pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME) : '';
    $tokenSource = strtolower(trim($currentPage . ' ' . str_replace(array('_', '-'), ' ', $scriptName)));
    $rawTokens = preg_split('/\s+/', $tokenSource);

    $stopWords = array('table', 'page', 'list', 'form', 'request', 'data', 'record');
    $tokens = array();

    foreach ($rawTokens as $token) {
        $token = preg_replace('/[^a-z0-9]/', '', $token);
        if ($token === '' || strlen($token) < 3 || in_array($token, $stopWords, true)) {
            continue;
        }
        $tokens[$token] = true;
    }

    if (empty($tokens)) {
        return null;
    }

    $allGroups = getData('*', '', '', PIN_GRP, $connect);
    if (!$allGroups) {
        return null;
    }

    $bestRow = null;
    $bestScore = 0;
    $isTie = false;

    while ($groupRow = $allGroups->fetch_assoc()) {
        $groupName = strtolower($groupRow['name']);
        $score = 0;

        foreach (array_keys($tokens) as $token) {
            if (strpos($groupName, $token) !== false) {
                $score++;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestRow = $groupRow;
            $isTie = false;
        } else if ($score > 0 && $score === $bestScore) {
            $isTie = true;
        }
    }

    if ($bestScore > 0 && !$isTie) {
        return $bestRow;
    }

    return null;
}

function syncPageTitleWithPinGroupName($connect, $currentPage)
{
    $pinGroup = findPinGroupRowByPage($connect, $currentPage);

    if (!empty($pinGroup['name'])) {
        $GLOBALS['pageTitle'] = $pinGroup['name'];
        return $pinGroup['name'];
    }

    return $currentPage;
}

function getUserPinGroup($connect) //check if user pin is in pin group, if yes, return, if no, skip
{
    if (isset($_SESSION['userid'])) {
        $resultUser = getData('*', "id = '" . $_SESSION['userid'] . "'",'', 'user', $connect);

        if ($resultUser != false) {
            $rowUser = $resultUser->fetch_assoc();

            $pinResult = getData('pins', "id = '" . $rowUser['access_id'] . "'", '', 'user_group', $connect);

            if ($pinResult !== false) {
                $pinArray = $pinResult->fetch_assoc();
            }
        }
    }

    if (!isset($pinArray["pins"]) || empty($pinArray["pins"])) {
        echo '<script>';
        echo 'window.location.href = "logout.php";';
        echo '</script>';
    }

    return $pinArray;
}

function getValuesByPinAssocIndex($data, $pin)
{
    // Extract individual entries
    $entries = explode('+', $data['pins']);

    foreach ($entries as $entry) {
        // Remove brackets and split by colon
        $split = explode(':', trim($entry, '[]'));

        if (count($split) == 2 && $split[0] == $pin) {
            // Values are in $split[1]
            $values = explode(',', $split[1]);
            return $values;
        }
    }

    return [];
}

function getPin($connect)
{
    $result = getData('*', "", '',PIN, $connect);
    $actionMapping = [];

    while ($resultPin = $result->fetch_assoc()) {
        $actionMapping[$resultPin['id']] = $resultPin['name'];
    }

    return $actionMapping;
}

function getPinAccessFromPinGroupRow($connect, $resultPin)
{
    if (!$resultPin || !isset($resultPin['id'])) {
        return [];
    }

    $currentPin = $resultPin['id'];
    $resultPinArray = explode(',', (string) $resultPin['pins']);
    $pinArray = getUserPinGroup($connect);
    $userPinArray = getValuesByPinAssocIndex($pinArray, $currentPin);
    $filteredResultArray = array_intersect($userPinArray, $resultPinArray);

    $actionMapping = getPin($connect);

    $result = array();
    foreach ($filteredResultArray as $permission) {
        if (isset($actionMapping[$permission])) {
            $result[] = $actionMapping[$permission];
        }
    }

    return $result;
}

function checkCurrentPin($connect, $currentPage)
{
    $resultPin = findPinGroupRowByPage($connect, $currentPage);

    if ($resultPin) {
        if (!empty($resultPin['name'])) {
            $GLOBALS['pageTitle'] = $resultPin['name'];
        }

        return getPinAccessFromPinGroupRow($connect, $resultPin);
    }

    return [];
}
function checkPin($connect, $currentPage)
{
    $resultPin = findPinGroupRowByPage($connect, $currentPage);

    if ($resultPin) {
        return getPinAccessFromPinGroupRow($connect, $resultPin);
    }
    
    return [];
}

function checkPinByGroupId($connect, $pinGroupId)
{
    $pinGroupId = (int) $pinGroupId;
    if ($pinGroupId <= 0) {
        return [];
    }

    $result = getData('*', "id = '$pinGroupId'", '', PIN_GRP, $connect);
    if ($result && $result->num_rows > 0) {
        $resultPin = $result->fetch_assoc();
        return getPinAccessFromPinGroupRow($connect, $resultPin);
    }

    return [];
}

function isActionAllowed($action, $allowedActions)
{
    // Ensure $allowedActions is an array
    if (!is_array($allowedActions)) {
        return false; // Or handle as per your requirements
    }

    $action = strtolower($action);

    foreach ($allowedActions as &$value) {
        $value = strtolower($value);
    }

    return in_array($action, $allowedActions);
}

function getPageAction($act)
{
    $validActions = ['I' => 'Add', 'E' => 'Edit', 'D' => 'Delete'];
    return $validActions[$act] ?? 'View';
}

function auditCurrentTableView($connect)
{
    static $hasLoggedTableView = false;
    if ($hasLoggedTableView) {
        return;
    }

    if (!($connect instanceof mysqli) || !defined('USER_ID') || !USER_ID) {
        return;
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return;
    }

    $scriptName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '' || stripos($scriptName, 'table') === false) {
        return;
    }

    if (in_array(strtolower($scriptName), array('insert_table.php'), true)) {
        return;
    }

    $resolvedPage = isset($GLOBALS['pageTitle']) && is_string($GLOBALS['pageTitle']) && trim($GLOBALS['pageTitle']) !== ''
        ? trim($GLOBALS['pageTitle'])
        : $scriptName;

    $viewActMsg = USER_NAME . " viewed the table page <b>" . $resolvedPage . "</b>.";

    $log = array(
        'log_act' => 'view',
        'cdate' => date('Y-m-d'),
        'ctime' => date('H:i:s'),
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $viewActMsg,
        'page' => $resolvedPage,
        'connect' => $connect,
    );

    audit_log($log);
    $hasLoggedTableView = true;
}

if (isset($connect) && isset($GLOBALS['pageTitle']) && is_string($GLOBALS['pageTitle'])) {
    $resolvedPageTitle = syncPageTitleWithPinGroupName($connect, $GLOBALS['pageTitle']);

    auditCurrentTableView($connect);

    // Some pages render <title> before this file is included; keep browser tab title in sync.
    if ($resolvedPageTitle !== '') {
        $safeTitleForJs = json_encode($resolvedPageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script>if (typeof document !== "undefined") { document.title = ' . $safeTitleForJs . '; }</script>';
    }
}

?>