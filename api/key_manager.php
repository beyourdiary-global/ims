<?php
/**
 * CMS API v1 - key manager (browser UI)
 * ------------------------------------------------------------------
 * Reachable from: Setting -> API Key Manager
 * Direct URL:     https://cms.beyourdiary.com/api/key_manager.php
 *
 * Access is gated twice:
 *   1. the "API Key Manager" pin group (id 170) must grant View, and
 *   2. the signed-in user must belong to user group 1 (Super Admin).
 *
 * From here you can create a key (the plaintext is shown once and never
 * stored), list existing keys and revoke one.
 *
 * Only the SHA-256 hash of a key is ever written to the database.
 */

define('CMS_API_ENTRY', 'key_manager');

require_once __DIR__ . '/lib/bootstrap.php';

// This page renders HTML, not JSON.
header('Content-Type: text/html; charset=UTF-8');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---- CMS chrome ------------------------------------------------------
// $currentPagePin must be set before checkCurrentPagePin.php is included.
$currentPagePin = 170;
$pageTitle = 'API Key Manager';
$disablePinGroupPageTitleSync = true;

// menuHeader pulls in connection.php, which sends anonymous visitors to the
// CMS login page exactly like every other admin screen.
include_once dirname(__DIR__) . '/menuHeader.php';
include_once dirname(__DIR__) . '/checkCurrentPagePin.php';

$pageTitle = 'API Key Manager';

if (!isActionAllowed('View', checkPinByGroupId($connect, 170))) {
    renderNotificationScript('You do not have permission to view API Key Manager.', 'error', '../dashboard.php', 1200, true);
    exit;
}

// The USER_GROUP constant is defined inside init.php, which runs before
// connection.php gets a chance to auto-login from the cookie. Reading the
// live session (and then the user row) keeps this correct on both paths.
$currentUserId = isset($_SESSION['userid']) ? (int) $_SESSION['userid'] : 0;
$currentUserName = isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : (string) $currentUserId;
$currentUserGroupId = isset($_SESSION['user_group']) ? (int) $_SESSION['user_group'] : 0;

if ($currentUserGroupId <= 0 && $currentUserId > 0) {
    $currentUserGroupRst = getData('access_id', "id = '" . $currentUserId . "'", 'LIMIT 1', USR_USER, $connect);
    if ($currentUserGroupRst && $currentUserGroupRst->num_rows > 0) {
        $currentUserGroupRow = $currentUserGroupRst->fetch_assoc();
        $currentUserGroupId = isset($currentUserGroupRow['access_id']) ? (int) $currentUserGroupRow['access_id'] : 0;
    }
}

if ($currentUserGroupId !== 1) {
    renderNotificationScript('Only Super Admin can access API Key Manager.', 'error', '../dashboard.php', 1200, true);
    exit;
}

// ---- Page helpers ----------------------------------------------------
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
            'page' => 'API Key Manager',
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
$submittedToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

if ($action !== '' && !hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
    $error = 'Invalid session token. Please refresh the page and try again.';
    $action = '';
}

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

$activeKeyCount = 0;
foreach ($keys as $keyRow) {
    if (isset($keyRow['status']) && $keyRow['status'] === 'A') {
        $activeKeyCount++;
    }
}

$apiBase = rtrim((string) SITEURL, '/') . '/api';
$csrfToken = (string) $_SESSION['csrf_token'];
?>
<style>
    .cms-apikey-card { background: #fff; border: 1px solid #e3e6ea; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
    .cms-apikey-alert { border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
    .cms-apikey-alert-ok { background: #e7f6ec; border: 1px solid #b7e0c4; color: #14532d; }
    .cms-apikey-alert-err { background: #fdecec; border: 1px solid #f3bdbd; color: #7f1d1d; }
    .cms-apikey-keybox { font-family: ui-monospace, Consolas, monospace; font-size: 15px; background: #0f172a; color: #d1fae5; padding: 12px 14px; border-radius: 8px; word-break: break-all; }
    .cms-apikey-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .cms-apikey-table th, .cms-apikey-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eceff2; vertical-align: top; }
    .cms-apikey-table th { background: #f8f9fb; font-weight: 600; }
    .cms-apikey-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; }
    .cms-apikey-pill-a { background: #e7f6ec; color: #14532d; }
    .cms-apikey-pill-d { background: #fdecec; color: #7f1d1d; }
    .cms-apikey-hint { font-size: 13px; color: #6c757d; line-height: 1.7; }
</style>
<div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
    <div class="col-12 col-md-11 py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="mb-1"><?= cmsApiKeyManagerEsc($pageTitle) ?></h2>
                <div class="text-muted">
                    Super Admin only. Read-only REST API keys for
                    <code><?= cmsApiKeyManagerEsc($apiBase) ?></code>
                    &mdash; <?= (int) $activeKeyCount ?> active of <?= count($keys) ?>.
                </div>
            </div>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="cms-apikey-alert cms-apikey-alert-ok"><?= cmsApiKeyManagerEsc($notice) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="cms-apikey-alert cms-apikey-alert-err"><?= cmsApiKeyManagerEsc($error) ?></div>
        <?php endif; ?>

        <?php if ($newPlainKey !== ''): ?>
            <div class="cms-apikey-card">
                <strong>Your new API key (copy it now)</strong>
                <p class="cms-apikey-hint mb-2">This value is shown once. Only its hash is stored, so it cannot be recovered later.</p>
                <div class="cms-apikey-keybox"><?= cmsApiKeyManagerEsc($newPlainKey) ?></div>
            </div>
        <?php endif; ?>

        <div class="cms-apikey-card">
            <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
                <input type="hidden" name="csrf_token" value="<?= cmsApiKeyManagerEsc($csrfToken) ?>">
                <input type="hidden" name="action" value="create">
                <input type="text" class="form-control" name="key_name" placeholder="Key name, e.g. Reporting AI" maxlength="190" style="max-width:280px">
                <button type="submit" class="btn btn-primary">Create key</button>
            </form>
        </div>

        <div class="cms-apikey-card">
            <div class="table-responsive">
                <table class="cms-apikey-table">
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
                                <td colspan="9" class="cms-apikey-hint">No keys yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($keys as $keyRow): ?>
                            <?php $isActive = (isset($keyRow['status']) && $keyRow['status'] === 'A'); ?>
                            <tr>
                                <td><?= (int) $keyRow['id'] ?></td>
                                <td><?= cmsApiKeyManagerEsc($keyRow['name']) ?></td>
                                <td><code><?= cmsApiKeyManagerEsc($keyRow['key_prefix']) ?>&hellip;</code></td>
                                <td><?= cmsApiKeyManagerEsc($keyRow['scopes']) ?></td>
                                <td>
                                    <span class="cms-apikey-pill <?= $isActive ? 'cms-apikey-pill-a' : 'cms-apikey-pill-d' ?>">
                                        <?= $isActive ? 'active' : 'revoked' ?>
                                    </span>
                                </td>
                                <td><?= cmsApiKeyManagerEsc($keyRow['created_at']) ?></td>
                                <td>
                                    <?= cmsApiKeyManagerEsc($keyRow['last_used_at']) ?>
                                    <?php if (!empty($keyRow['last_used_ip'])): ?>
                                        <br><span class="cms-apikey-hint"><?= cmsApiKeyManagerEsc($keyRow['last_used_ip']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $keyRow['request_count'] ?></td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <form method="post" onsubmit="return confirm('Revoke this key? Any AI using it will stop working immediately.');">
                                            <input type="hidden" name="csrf_token" value="<?= cmsApiKeyManagerEsc($csrfToken) ?>">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="key_id" value="<?= (int) $keyRow['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cms-apikey-card">
            <strong>How to call the API</strong>
            <p class="cms-apikey-hint mb-0">
                Base URL: <code><?= cmsApiKeyManagerEsc($apiBase) ?></code><br>
                Health check: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/ping.php</code><br>
                Customers: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/customers.php?limit=20</code><br>
                One customer: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/customer.php?id=744</code><br>
                Sales: <code>GET <?= cmsApiKeyManagerEsc($apiBase) ?>/sales.php?date_from=<?= date('Y-m-d') ?>&amp;date_to=<?= date('Y-m-d') ?></code><br>
                Send the key as the <code>X-API-Key</code> header, or append <code>&amp;api_key=&lt;key&gt;</code> for a quick browser test.
            </p>
        </div>
    </div>
</div>
</body>
</html>
