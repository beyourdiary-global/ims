<?php
/**
 * CMS API v1 - discovery / documentation endpoint.
 *
 * Public on purpose: it only advertises which endpoints exist. Every data
 * endpoint requires a valid API key.
 */

define('CMS_API_ENTRY', 'index');

require_once __DIR__ . '/lib/bootstrap.php';

$base = rtrim((string) SITEURL, '/') . '/api';

cmsApiOk(array(
    'service' => 'Beyourdiary CMS API',
    'api_version' => CMS_API_VERSION,
    'base_url' => $base,
    'authentication' => array(
        'type' => 'api_key',
        'send_as' => array(
            'header' => 'X-API-Key: <your-key>',
            'bearer' => 'Authorization: Bearer <your-key>',
            'query' => '?api_key=<your-key>  (convenient, but easiest to leak - prefer the header)',
        ),
        'key_management' => $base . '/key_manager.php',
    ),
    'endpoints' => array(
        array(
            'path' => '/ping.php',
            'method' => 'GET',
            'description' => 'Verify the key works. Returns the key name and scopes.',
            'params' => array(),
        ),
        array(
            'path' => '/customers.php',
            'method' => 'GET',
            'description' => 'Shopee customer records (the "Customer" side of the Daily Sales & Customer Report).',
            'params' => array(
                'q' => 'free-text search across buyer username / contact / remark',
                'limit' => 'page size, default 50, max 500',
                'offset' => 'pagination offset, default 0',
                'tag_id' => 'only customers carrying this tag id',
                'label_type' => 'segmentation | level | repeat',
                'label_id' => 'label id used together with label_type',
            ),
        ),
        array(
            'path' => '/customer.php',
            'method' => 'GET',
            'description' => 'One Shopee customer: profile + tags + labels + user record log.',
            'params' => array(
                'id' => 'customer id (preferred)',
                'username' => 'buyer_username, used when id is not given',
                'log_limit' => 'max user-record-log entries, default 20, max 200',
            ),
        ),
        array(
            'path' => '/sales.php',
            'method' => 'GET',
            'description' => 'Shopee order report (the "Sales" side): rows, totals and breakdowns.',
            'params' => array(
                'platform' => 'shopee (default) | facebook | website | lazada',
                'date_from' => 'YYYY-MM-DD (inclusive)',
                'date_to' => 'YYYY-MM-DD (inclusive)',
                'include_rows' => '1 (default) returns order rows, 0 returns totals/breakdowns only',
                'limit' => 'max rows to return, default 100, max 1000',
                'offset' => 'row offset, default 0',
            ),
        ),
    ),
));
