<?php
/**
 * CMS API v1 - key manager (browser UI)
 * ------------------------------------------------------------------
 * Reachable at: https://cms.beyourdiary.com/api/key_manager.php
 *
 * Requires an authenticated CMS session, exactly like every other admin
 * page. From here you can create a key (the plaintext is shown once and
 * never stored), list existing keys and revoke one.
 *
 * Only the SHA-256 hash of a key is ever written to the database.
 */

define('CMS_API_ENTRY', 'key_manager');

require_once __DIR__ . '/lib/bootstrap.php';

// This page renders HTML, not JSON.
header('Content-Type: text/html; charset=UTF-8');

// ---- Require a CMS login (same rule as the rest of the admin) ----
if (!isset($_SESSION['userid'])) {
    require_once ROOT . '/include/auto_login.php';
    if (function_exists('cmsTryAutoLoginFromCookie')) {
        cmsTryAutoLoginFromCookie($connect);
    }
}

if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CMS API keys</title></head><body style="font-family:system-ui,sans-serif;padding:40px">';
    echo '<h2>Please sign in to the CMS first</h2>';
    echo '<p>Open <a href="' . htmlspecialchars(rtrim((string) SITEURL, '/') . '/index.php', ENT_QUOTES, 'UTF-8') . '">the CMS</a>, log in, then come back to this page.</p>';
    echo '</body></html>';
    exit;
}

$currentUserId = (int) $_SESSION['userid'];
$currentUserName = isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : (isset($_SESSION['userid']) ? (string) $_SESSION['userid'] : '');

$table = CMS_API_KEY_TABLE;
$notice = '';
$error = '';
$newPlainKey = '';

// Declared before use - PHP does not hoist conditional declarations.
if (!function_exists('cmsApiKeyManagerAudit')) {
    function cmsApiKeyManagerAudit($connect, $logAct, $message)
    {
        if (!function_exists('audit_log')) {
            return;
        }
        audit_log(array(
            'log_act' => $logAct,
            'cdate' => date('Y-m-d'),
            'ctime' => date('H:i:s'),
            'uid' => isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : 0,
            'cby' => isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : 0,
            'act_msg' => htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'),
            'page' => 'CMS API keys',
            'connect' => $connect,
        ));
    }
}

if (!function_exists('cmsApiKeyManagerEsc')) {
    function cmsApiKeyManagerEsc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!cmsApiEnsureKeyTable($connect)) {
    $error = 'Could not create the api_key table. Check the database user permissions.';
}

$action = isset($_POST['action']) && is_string($_POST['action']) ? trim($_POST['action']) : '';

if ($error === '' && $action === 'create') {
    $keyName = isset($_POST['key_name']) && is_string($_POST['key_name']) ? trim($_POST['key_name']) : '';
    $keyName = $keyName === '' ? 'API key' : mb_substr($keyName, 0, 190);

    $plainKey = 'cms_' . bin2hex(random_bytes(24));
    $hash = hash('sha256', $plainKey);
    $prefix = substr($plainKey, 0, 12);

    $safeName = mysqli_real_escape_string($connect, $keyName);
    $safeHash = mysqli_real_escape_string($connect, $hash);
    $safePrefix = mysqli_real_escape_string($connect, $prefix);
    $safeBy = mysqli_real_escape_string($connect, (string) $currentUserId);

    $ok = mysqli_query(
        $connect,
        "INSERT INTO `" . $table . "`
            (`name`, `key_hash`, `key_prefix`, `scopes`, `status`, `created_at`, `created_by`)
         VALUES
            ('" . $safeName . "', '" . $safeHash . "', '" . $safePrefix . "', 'read', 'A', NOW(), '" . $safeBy . "')"
    );

    if ($ok) {
        $newPlainKey = $plainKey;
        $notice = 'New key created. Copy it now - it is shown only once.';
        cmsApiKeyManagerAudit($connect, 'add', 'created API key "' . $keyName . '"');
    } else {
        $error = 'Insert failed: ' . mysqli_error($connect);
    }
}

if ($error === '' && $action === 'revoke') {
    $revokeId = isset($_POST['key_id']) ? (int) $_POST['key_id'] : 0;
    if ($revokeId > 0) {
        if (mysqli_query($connect, "UPDATE `" . $table . "` SET `status` = 'D' WHERE `id` = " . $revokeId)) {
            $notice = 'Key #' . $revokeId . ' revoked.';
            cmsApiKeyManagerAudit($connect, 'edit', 'revoked API key #' . $revokeId);
        } else {
            $error = 'Revoke failed: ' . mysqli_error($connect);
        }
    }
}

$keys = array();
$keyResult = mysqli_query($connect, "SELECT * FROM `" . $table . "` ORDER BY `id` DESC");
if ($keyResult instanceof mysqli_result) {
    while ($keyRow = $keyResult->fetch_assoc()) {
        $keys[] = $keyRow;
    }
}

$apiBase = rtrim((string) SITEURL, '/') . '/api';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CMS API keys</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f6f9; margin: 0; padding: 32px 16px; color: #212529; }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .sub { color: #6c757d; margin: 0 0 24px; font-size: 14px; }
        .card { background: #fff; border: 1px solid #e3e6ea; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .alert { border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
        .alert-ok { background: #e7f6ec; border: 1px solid #b7e0c4; color: #14532d; }
        .alert-err { background: #fdecec; border: 1px solid #f3bdbd; color: #7f1d1d; }
        .keybox { font-family: ui-monospace, Consolas, monospace; font-size: 15px; background: #0f172a; color: #d1fae5; padding: 12px 14px; border-radius: 8px; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eceff2; vertical-align: top; }
        th { background: #f8f9fb; font-weight: 600; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; }
        .pill-a { background: #e7f6ec; color: #14532d; }
        .pill-d { background: #fdecec; color: #7f1d1d; }
        input[type=text] { padding: 8px 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; width: 260px; }
        button { padding: 8px 14px; border: 0; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-danger { background: #dc3545; color: #fff; }
        code { background: #f1f3f5; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        .hint { font-size: 13px; color: #6c757d; line-height: 1.7; }
    </style>
</head>

<body>
    <div class="wrap">
        <h1>CMS API keys</h1>
        <p class="sub">Read-only access to Shopee customer records and the Shopee order report.</p>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-ok"><?= cmsApiKeyManagerEsc($notice) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-err"><?= cmsApiKeyManagerEsc($error) ?></div>
        <?php endif; ?>

        <?php if ($newPlainKey !== ''): ?>
            <div class="card">
                <strong>Your new API key (copy it now)</strong>
                <p class="hint">This value is shown once. Only its hash is stored, so it cannot be recovered later.</p>
                <div class="keybox"><?= cmsApiKeyManagerEsc($newPlainKey) ?></div>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <input type="text" name="key_name" placeholder="Key name, e.g. Reporting AI" maxlength="190">
                <button type="submit" class="btn-primary">Create key</button>
            </form>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Prefix</th>
                        <th>Scopes</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last used</th>
                        <th>Calls</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($keys)): ?>
                        <tr>
                            <td colspan="9" class="hint">No keys yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($keys as $keyRow): ?>
                        <?php $isActive = (isset($keyRow['status']) && $keyRow['status'] === 'A'); ?>
                        <tr>
                            <td><?= (int) $keyRow['id'] ?></td>
                            <td><?= cmsApiKeyManagerEsc($keyRow['name']) ?></td>
                            <td><code><?= cmsApiKeyManagerEsc($keyRow['key_prefix']) ?>…</code></td>
                            <td><?= cmsApiKeyManagerEsc($keyRow['scopes']) ?></td>
                            <td>
                                <span class="pill <?= $isActive ? 'pill-a' : 'pill-d' ?>">
                                    <?= $isActive ? 'active' : 'revoked' ?>
                                </span>
                            </td>
                            <td><?= cmsApiKeyManagerEsc($keyRow['created_at']) ?></td>
                            <td>
                                <?= cmsApiKeyManagerEsc($keyRow['last_used_at']) ?>
                                <?php if (!empty($keyRow['last_used_ip'])): ?>
                                    <br><span class="hint"><?= cmsApiKeyManagerEsc($keyRow['last_used_ip']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $keyRow['request_count'] ?></td>
                            <td>
                                <?php if ($isActive): ?>
                                    <form method="post" onsubmit="return confirm('Revoke this key? Any AI using it will stop working immediately.');">
                                        <input type="hidden" name="action" value="revoke">
                                        <input type="hidden" name="key_id" value="<?= (int) $keyRow['id'] ?>">
                                        <button type="submit" class="btn-danger">Revoke</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <strong>How to call the API</strong>
            <p class="hint">
                Base URL: <code><?= cmsApiKeyManagerEsc($apiBase) ?></code><br>
                Health check: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/ping.php</code><br>
                Customers: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/customers.php?limit=20</code><br>
                One customer: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/customer.php?id=744</code><br>
                Sales: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/sales.php?date_from=<?= date('Y-m-d') ?>&amp;date_to=<?= date('Y-m-d') ?></code><br>
                Send the key as the <code>X-API-Key</code> header, or append <code>&amp;api_key=&lt;key&gt;</code> for a quick browser test.
            </p>
        </div>
    </div>
</body>

</html>
