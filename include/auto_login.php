<?php

if (!function_exists('cmsAutoLoginCookieName')) {
    function cmsAutoLoginCookieName()
    {
        return 'auto_login';
    }
}

if (!function_exists('cmsAutoLoginTtlSeconds')) {
    function cmsAutoLoginTtlSeconds()
    {
        return 30 * 24 * 60 * 60;
    }
}

if (!function_exists('cmsIsSecureRequest')) {
    function cmsIsSecureRequest()
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        );
    }
}

if (!function_exists('cmsAutoLoginSecret')) {
    function cmsAutoLoginSecret()
    {
        $parts = array(
            'ims-auto-login-v1',
            defined('SITEURL') ? SITEURL : '',
            defined('dbhost') ? dbhost : '',
            defined('dbname') ? dbname : '',
            defined('dbuser') ? dbuser : '',
            defined('dbpwd') ? dbpwd : '',
        );

        return hash('sha256', implode('|', $parts));
    }
}

if (!function_exists('cmsHashEquals')) {
    function cmsHashEquals($known, $user)
    {
        if (function_exists('hash_equals')) {
            return hash_equals((string) $known, (string) $user);
        }

        $known = (string) $known;
        $user = (string) $user;

        if (strlen($known) !== strlen($user)) {
            return false;
        }

        $res = 0;
        $len = strlen($known);
        for ($i = 0; $i < $len; $i++) {
            $res |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $res === 0;
    }
}

if (!function_exists('cmsBase64UrlEncode')) {
    function cmsBase64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }
}

if (!function_exists('cmsBase64UrlDecode')) {
    function cmsBase64UrlDecode($value)
    {
        $value = strtr((string) $value, '-_', '+/');
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($value, true);
    }
}

if (!function_exists('cmsBuildAutoLoginToken')) {
    function cmsBuildAutoLoginToken($userId, $passwordHash, $expiresAt)
    {
        $userId = (int) $userId;
        $expiresAt = (int) $expiresAt;
        $passwordHash = trim((string) $passwordHash);

        if ($userId <= 0 || $expiresAt <= 0 || $passwordHash === '') {
            return '';
        }

        $payload = $userId . ':' . $expiresAt;
        $signature = hash_hmac('sha256', $payload . ':' . $passwordHash, cmsAutoLoginSecret());

        return cmsBase64UrlEncode($payload . ':' . $signature);
    }
}

if (!function_exists('cmsParseAutoLoginToken')) {
    function cmsParseAutoLoginToken($token)
    {
        $raw = cmsBase64UrlDecode($token);
        if ($raw === false || $raw === '') {
            return null;
        }

        $parts = explode(':', (string) $raw);
        if (count($parts) !== 3) {
            return null;
        }

        list($uid, $expiresAt, $signature) = $parts;

        if (!ctype_digit((string) $uid) || !ctype_digit((string) $expiresAt)) {
            return null;
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', (string) $signature)) {
            return null;
        }

        return array(
            'userId' => (int) $uid,
            'expiresAt' => (int) $expiresAt,
            'signature' => strtolower((string) $signature),
        );
    }
}

if (!function_exists('cmsSetAutoLoginCookieValue')) {
    function cmsSetAutoLoginCookieValue($token, $expiresAt)
    {
        $name = cmsAutoLoginCookieName();
        $expiresAt = (int) $expiresAt;
        $secure = cmsIsSecureRequest();

        if (PHP_VERSION_ID >= 70300) {
            return setcookie($name, (string) $token, array(
                'expires' => $expiresAt,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        }

        $path = '/; samesite=Lax';
        return setcookie($name, (string) $token, $expiresAt, $path, '', $secure, true);
    }
}

if (!function_exists('cmsClearAutoLoginCookie')) {
    function cmsClearAutoLoginCookie()
    {
        unset($_COOKIE[cmsAutoLoginCookieName()]);
        return cmsSetAutoLoginCookieValue('', time() - 3600);
    }
}

if (!function_exists('cmsSetAutoLoginCookieForUserRow')) {
    function cmsSetAutoLoginCookieForUserRow($userRow)
    {
        if (!is_array($userRow)) {
            return false;
        }

        $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
        $passwordHash = isset($userRow['password_alt']) ? (string) $userRow['password_alt'] : '';

        if ($userId <= 0 || trim($passwordHash) === '') {
            return false;
        }

        $expiresAt = time() + cmsAutoLoginTtlSeconds();
        $token = cmsBuildAutoLoginToken($userId, $passwordHash, $expiresAt);

        if ($token === '') {
            return false;
        }

        return cmsSetAutoLoginCookieValue($token, $expiresAt);
    }
}

if (!function_exists('cmsBuildUsrPinAccess')) {
    function cmsBuildUsrPinAccess($pinsRaw)
    {
        $pinsRaw = trim((string) $pinsRaw);
        if ($pinsRaw === '') {
            return array();
        }

        $entries = explode('+', $pinsRaw);
        $usrPinAccess = array();

        foreach ($entries as $entry) {
            $entry = trim($entry, "[] \t\n\r\0\x0B");
            if ($entry === '') {
                continue;
            }

            $parts = explode(':', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim((string) $parts[0]);
            if (!ctype_digit($key)) {
                continue;
            }

            $values = explode(',', (string) $parts[1]);
            $clean = array();

            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value === '' || !is_numeric($value)) {
                    continue;
                }
                $clean[] = (int) $value;
            }

            $usrPinAccess[(int) $key] = $clean;
        }

        return $usrPinAccess;
    }
}

if (!function_exists('cmsHydrateSessionFromUserRow')) {
    function cmsHydrateSessionFromUserRow($connect, $userRow)
    {
        if (!is_array($userRow)) {
            return false;
        }

        $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
        $userGroupId = isset($userRow['access_id']) ? (int) $userRow['access_id'] : 0;

        if ($userId <= 0 || $userGroupId <= 0) {
            return false;
        }

        $_SESSION['userid'] = $userId;
        $_SESSION['user_name'] = isset($userRow['name']) ? (string) $userRow['name'] : '';
        $_SESSION['user_email'] = isset($userRow['email']) ? (string) $userRow['email'] : '';
        $_SESSION['user_group'] = $userGroupId;

        $safeGroupId = mysqli_real_escape_string($connect, (string) $userGroupId);
        $pinQry = "SELECT pins FROM " . USR_GRP . " WHERE id ='" . $safeGroupId . "' LIMIT 1";
        $pinResult = mysqli_query($connect, $pinQry);

        if (!$pinResult || mysqli_num_rows($pinResult) !== 1) {
            return false;
        }

        $pinRow = mysqli_fetch_assoc($pinResult);
        $pinsRaw = isset($pinRow['pins']) ? trim((string) $pinRow['pins']) : '';

        if ($pinsRaw === '') {
            return false;
        }

        $entries = explode('+', $pinsRaw);
        $permissionGrp = array();

        foreach ($entries as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }

            $entry = str_replace(array('[', ']'), '', $entry);
            $colonPos = stripos($entry, ':');
            if ($colonPos === false) {
                continue;
            }

            $tmpPinGrp = substr($entry, 0, $colonPos);
            $tmpPin = substr($entry, $colonPos + 1);

            if (!ctype_digit(trim((string) $tmpPinGrp))) {
                continue;
            }

            $tmpPin = explode(',', (string) $tmpPin);
            $permissionGrp[(int) trim((string) $tmpPinGrp)] = array_map('trim', $tmpPin);
        }

        $_SESSION['usr_pin'] = array_keys($permissionGrp);
        $_SESSION['usr_pin_access'] = cmsBuildUsrPinAccess($pinsRaw);

        return true;
    }
}

if (!function_exists('cmsTryAutoLoginFromCookie')) {
    function cmsTryAutoLoginFromCookie($connect)
    {
        if (isset($_SESSION['userid']) && (int) $_SESSION['userid'] > 0) {
            return true;
        }

        $cookieName = cmsAutoLoginCookieName();
        if (!isset($_COOKIE[$cookieName])) {
            return false;
        }

        $token = (string) $_COOKIE[$cookieName];
        $parsed = cmsParseAutoLoginToken($token);
        if (!$parsed) {
            cmsClearAutoLoginCookie();
            return false;
        }

        if ((int) $parsed['expiresAt'] < time()) {
            cmsClearAutoLoginCookie();
            return false;
        }

        $safeUserId = mysqli_real_escape_string($connect, (string) $parsed['userId']);
        $sql = "SELECT id, name, username, email, access_id, password_alt, status, fail_count FROM " . USR_USER . " WHERE id='" . $safeUserId . "' AND status='A' LIMIT 1";
        $result = mysqli_query($connect, $sql);

        if (!$result || mysqli_num_rows($result) !== 1) {
            cmsClearAutoLoginCookie();
            return false;
        }

        $userRow = mysqli_fetch_assoc($result);
        if ((int) $userRow['fail_count'] >= 4) {
            cmsClearAutoLoginCookie();
            return false;
        }

        $expectedToken = cmsBuildAutoLoginToken((int) $userRow['id'], (string) $userRow['password_alt'], (int) $parsed['expiresAt']);
        if ($expectedToken === '' || !cmsHashEquals($expectedToken, $token)) {
            cmsClearAutoLoginCookie();
            return false;
        }

        if (!cmsHydrateSessionFromUserRow($connect, $userRow)) {
            cmsClearAutoLoginCookie();
            return false;
        }

        // Refresh cookie TTL on successful auto-login.
        cmsSetAutoLoginCookieForUserRow($userRow);

        return true;
    }
}
