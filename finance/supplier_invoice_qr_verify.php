<?php
require_once dirname(__DIR__) . '/include/connection.php';
require_once ROOT . '/include/common.php';
require_once ROOT . '/include/supplier_invoice_qr_verify_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_SESSION['userid'])) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Access denied.', 'data' => array()));
    exit;
}

$qrUrl = isset($_POST['qr_url']) && !is_array($_POST['qr_url']) ? trim((string) $_POST['qr_url']) : '';
$result = supplierInvoiceQrVerifyFetchDetails($qrUrl);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
