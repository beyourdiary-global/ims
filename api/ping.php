<?php
/**
 * GET /api/ping.php
 * Verifies that the API key is valid. Nothing else.
 */

define('CMS_API_ENTRY', 'ping');

require_once __DIR__ . '/lib/bootstrap.php';

$keyRow = cmsApiAuthenticate($connect, 'read');

cmsApiOk(
    array(
        'pong' => true,
        'key_name' => isset($keyRow['name']) ? (string) $keyRow['name'] : '',
        'scopes' => isset($keyRow['scopes_list']) ? $keyRow['scopes_list'] : array(),
        'server_time' => date('c'),
    ),
    cmsApiMeta('ping', $keyRow)
);
