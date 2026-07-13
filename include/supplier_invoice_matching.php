<?php

if (!function_exists('supplierInvoiceNormalizeOdrMatchKey')) {
    function supplierInvoiceNormalizeOdrMatchKey($value)
    {
        $value = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim((string) $value)));
        if (!preg_match('/^ODR([A-Z0-9]+)$/', $value, $matches)) {
            return '';
        }
        return $matches[1];
    }
}

if (!function_exists('supplierInvoiceNormalizeStockInvoiceMatchKey')) {
    function supplierInvoiceNormalizeStockInvoiceMatchKey($value)
    {
        $value = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim((string) $value)));
        if (!preg_match('/^INV([A-Z0-9]+)$/', $value, $matches)) {
            return '';
        }
        return $matches[1];
    }
}

if (!function_exists('supplierInvoiceRefreshEInvoicingStatuses')) {
    function supplierInvoiceRefreshEInvoicingStatuses($financeConnect)
    {
        if (!($financeConnect instanceof mysqli)) {
            return 0;
        }

        $supplierInvoiceTable = defined('SUPPLIER_INVOICE') ? SUPPLIER_INVOICE : 'supplier_invoice';
        $stockOrderRequestTable = defined('STOCK_ORDER_REQ') ? STOCK_ORDER_REQ : 'stock_order_request';
        $matchedOdrKeys = array();
        $supplierResult = mysqli_query($financeConnect, "SELECT odr FROM `" . $supplierInvoiceTable . "` WHERE status = 'A'");
        if ($supplierResult) {
            while ($supplierRow = mysqli_fetch_assoc($supplierResult)) {
                $odrKey = supplierInvoiceNormalizeOdrMatchKey($supplierRow['odr'] ?? '');
                if ($odrKey !== '') {
                    $matchedOdrKeys[$odrKey] = true;
                }
            }
        }

        $requestResult = mysqli_query($financeConnect, "SELECT id, invoice_no, e_invoicing_status FROM `" . $stockOrderRequestTable . "` WHERE status = 'A'");
        if (!$requestResult) {
            return 0;
        }

        $updatedCount = 0;
        while ($requestRow = mysqli_fetch_assoc($requestResult)) {
            $invoiceKey = supplierInvoiceNormalizeStockInvoiceMatchKey($requestRow['invoice_no'] ?? '');
            $nextStatus = ($invoiceKey !== '' && isset($matchedOdrKeys[$invoiceKey])) ? 1 : 0;
            $currentStatus = !empty($requestRow['e_invoicing_status']) ? 1 : 0;
            if ($currentStatus === $nextStatus) {
                continue;
            }

            $requestId = (int) ($requestRow['id'] ?? 0);
            if ($requestId > 0 && mysqli_query($financeConnect, "UPDATE `" . $stockOrderRequestTable . "` SET e_invoicing_status = '" . $nextStatus . "' WHERE id = '" . $requestId . "'")) {
                $updatedCount++;
            }
        }

        return $updatedCount;
    }
}
