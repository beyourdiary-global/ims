<?php

if (!function_exists('supplierInvoiceQrVerifyNormalizeText')) {
    function supplierInvoiceQrVerifyNormalizeText($value)
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(array("\xC2\xA0", "\r\n", "\r", "\n"), ' ', $value);
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}

if (!function_exists('supplierInvoiceQrVerifyIsAllowedUrl')) {
    function supplierInvoiceQrVerifyIsAllowedUrl($url)
    {
        $url = trim((string) $url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        return $scheme === 'https' && in_array($host, array('myinvois.hasil.gov.my'), true);
    }
}

if (!function_exists('supplierInvoiceQrVerifyFetchHtml')) {
    function supplierInvoiceQrVerifyFetchHtml($url, &$errorMessage = '')
    {
        $errorMessage = '';
        if (!supplierInvoiceQrVerifyIsAllowedUrl($url)) {
            $errorMessage = 'Only HTTPS QR links from myinvois.hasil.gov.my can be verified.';
            return '';
        }

        if (!function_exists('curl_init')) {
            $errorMessage = 'QR verification is unavailable because cURL is not enabled on the server.';
            return '';
        }

        $request = curl_init($url);
        if ($request === false) {
            $errorMessage = 'Unable to start QR verification.';
            return '';
        }

        curl_setopt_array($request, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_USERAGENT => 'BeYourDiary-IMS-SupplierInvoice/1.0',
            CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml'),
        ));

        $html = curl_exec($request);
        $httpCode = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
        $curlError = (string) curl_error($request);
        curl_close($request);

        if (!is_string($html) || $html === '') {
            $errorMessage = $curlError !== '' ? 'Unable to retrieve the QR link: ' . $curlError : 'Unable to retrieve the QR link.';
            return '';
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = 'The QR verification website returned HTTP ' . $httpCode . '.';
            return '';
        }

        return $html;
    }
}

if (!function_exists('supplierInvoiceQrVerifyExtractShareReference')) {
    function supplierInvoiceQrVerifyExtractShareReference($url)
    {
        $path = trim((string) parse_url((string) $url, PHP_URL_PATH), '/');
        $parts = array_values(array_filter(explode('/', $path), 'strlen'));
        $shareIndex = array_search('share', array_map('strtolower', $parts), true);
        if ($shareIndex === false || $shareIndex < 1 || !isset($parts[$shareIndex + 1])) {
            return array();
        }

        $uuid = trim((string) $parts[$shareIndex - 1]);
        $shareToken = trim((string) $parts[$shareIndex + 1]);
        if (!preg_match('/^[A-Z0-9]{26}$/i', $uuid) || !preg_match('/^[A-Z0-9]+$/i', $shareToken)) {
            return array();
        }

        return array('uuid' => $uuid, 'share_token' => $shareToken);
    }
}

if (!function_exists('supplierInvoiceQrVerifyFetchShareApiDetails')) {
    function supplierInvoiceQrVerifyFetchShareApiDetails($url, &$errorMessage = '')
    {
        $errorMessage = '';
        $reference = supplierInvoiceQrVerifyExtractShareReference($url);
        if (empty($reference)) {
            $errorMessage = 'The QR URL is not a valid MyInvois share link.';
            return array();
        }
        if (!function_exists('curl_init')) {
            $errorMessage = 'QR verification is unavailable because cURL is not enabled on the server.';
            return array();
        }

        $apiUrl = 'https://api.myinvois.hasil.gov.my/admin/api/v1/public/documents/'
            . rawurlencode($reference['uuid']) . '/share/' . rawurlencode($reference['share_token']);
        $request = curl_init($apiUrl);
        if ($request === false) {
            $errorMessage = 'Unable to start MyInvois QR verification.';
            return array();
        }

        curl_setopt_array($request, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'BeYourDiary-IMS-SupplierInvoice/1.0',
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
        ));
        $response = curl_exec($request);
        $httpCode = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
        $curlError = (string) curl_error($request);
        curl_close($request);

        if (!is_string($response) || $response === '') {
            $errorMessage = $curlError !== '' ? 'Unable to retrieve MyInvois details: ' . $curlError : 'Unable to retrieve MyInvois details.';
            return array();
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = 'The MyInvois verification API returned HTTP ' . $httpCode . '.';
            return array();
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $errorMessage = 'The MyInvois verification API returned invalid data.';
            return array();
        }

        return $data;
    }
}

if (!function_exists('supplierInvoiceQrVerifyParseApiDetails')) {
    function supplierInvoiceQrVerifyParseApiDetails($data)
    {
        $currency = strtoupper(trim((string) ($data['documentCurrencyCode'] ?? '')));
        $currency = $currency === 'MYR' ? 'RM' : $currency;
        $totalPayable = isset($data['totalPayableAmount']) && is_numeric($data['totalPayableAmount'])
            ? number_format((float) $data['totalPayableAmount'], 2, '.', '')
            : '';

        return array(
            'e_invoice_no' => supplierInvoiceQrVerifyNormalizeText($data['internalId'] ?? ''),
            'uuid' => supplierInvoiceQrVerifyNormalizeText($data['uuid'] ?? ''),
            'supplier_name' => supplierInvoiceQrVerifyNormalizeText($data['supplierName'] ?? ($data['issuerName'] ?? '')),
            'total_payable_amount' => trim($currency . ($currency !== '' && $totalPayable !== '' ? ' ' : '') . $totalPayable),
            'total_payable_numeric' => $totalPayable !== '' ? $totalPayable : null,
        );
    }
}

if (!function_exists('supplierInvoiceQrVerifyBuildTextLines')) {
    function supplierInvoiceQrVerifyBuildTextLines($html)
    {
        $html = preg_replace_callback('/<input\b[^>]*>/i', function ($matches) {
            $input = $matches[0];
            if (preg_match('/\bvalue\s*=\s*(["\'])(.*?)\1/is', $input, $valueMatches)) {
                return ' ' . htmlspecialchars_decode($valueMatches[2], ENT_QUOTES) . "\n";
            }
            return "\n";
        }, (string) $html);
        $html = preg_replace('/<\/(?:div|p|section|article|li|tr|td|th|h[1-6]|label|span|button)>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawLines = preg_split('/\r\n|\r|\n/', $text);
        $lines = array();

        foreach ((array) $rawLines as $line) {
            $line = supplierInvoiceQrVerifyNormalizeText($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}

if (!function_exists('supplierInvoiceQrVerifyExtractLabelValue')) {
    function supplierInvoiceQrVerifyExtractLabelValue($lines, $labels)
    {
        $labels = array_values(array_filter(array_map('supplierInvoiceQrVerifyNormalizeText', (array) $labels)));
        foreach ((array) $lines as $index => $line) {
            foreach ($labels as $label) {
                $labelPattern = preg_quote($label, '/');
                if (preg_match('/^\s*' . $labelPattern . '\s*[:\-]?\s*(.+)$/iu', $line, $matches)) {
                    return supplierInvoiceQrVerifyNormalizeText($matches[1]);
                }
                if (strcasecmp(supplierInvoiceQrVerifyNormalizeText($line), $label) !== 0) {
                    continue;
                }

                for ($nextIndex = $index + 1; $nextIndex < count($lines) && $nextIndex <= $index + 4; $nextIndex++) {
                    $candidate = supplierInvoiceQrVerifyNormalizeText($lines[$nextIndex]);
                    if ($candidate === '' || in_array(strtolower($candidate), array_map('strtolower', $labels), true)) {
                        continue;
                    }
                    return $candidate;
                }
            }
        }

        return '';
    }
}

if (!function_exists('supplierInvoiceQrVerifyParseDetails')) {
    function supplierInvoiceQrVerifyParseDetails($html)
    {
        $lines = supplierInvoiceQrVerifyBuildTextLines($html);
        $invoiceNo = supplierInvoiceQrVerifyExtractLabelValue($lines, array('e-Invoice No.', 'e-Invoice No', 'Invoice No.'));
        $uuid = supplierInvoiceQrVerifyExtractLabelValue($lines, array('UUID'));
        $supplierName = supplierInvoiceQrVerifyExtractLabelValue($lines, array('Supplier Name'));
        $totalPayable = supplierInvoiceQrVerifyExtractLabelValue($lines, array('Total Payable Amount'));
        $totalPayableNumeric = null;

        if ($totalPayable !== '' && preg_match('/(?:RM|MYR)?\s*([\d,]+(?:\.\d{1,2})?)/i', $totalPayable, $amountMatches)) {
            $totalPayableNumeric = number_format((float) str_replace(',', '', $amountMatches[1]), 2, '.', '');
        }

        return array(
            'e_invoice_no' => $invoiceNo,
            'uuid' => $uuid,
            'supplier_name' => $supplierName,
            'total_payable_amount' => $totalPayable,
            'total_payable_numeric' => $totalPayableNumeric,
        );
    }
}

if (!function_exists('supplierInvoiceQrVerifyFetchDetails')) {
    function supplierInvoiceQrVerifyFetchDetails($url)
    {
        $apiErrorMessage = '';
        $apiData = supplierInvoiceQrVerifyFetchShareApiDetails($url, $apiErrorMessage);
        if (!empty($apiData)) {
            $details = supplierInvoiceQrVerifyParseApiDetails($apiData);
            foreach (array('e_invoice_no', 'uuid', 'supplier_name', 'total_payable_amount') as $requiredField) {
                if (trim((string) ($details[$requiredField] ?? '')) === '') {
                    return array('success' => false, 'message' => 'The MyInvois verification API did not provide all required e-invoice details.', 'data' => $details);
                }
            }
            return array('success' => true, 'message' => '', 'data' => $details);
        }

        $errorMessage = '';
        $html = supplierInvoiceQrVerifyFetchHtml($url, $errorMessage);
        if ($html === '') {
            return array('success' => false, 'message' => $apiErrorMessage !== '' ? $apiErrorMessage : $errorMessage, 'data' => array());
        }

        $details = supplierInvoiceQrVerifyParseDetails($html);
        foreach (array('e_invoice_no', 'uuid', 'supplier_name', 'total_payable_amount') as $requiredField) {
            if (trim((string) ($details[$requiredField] ?? '')) === '') {
                return array('success' => false, 'message' => 'The QR website did not provide all required e-invoice details.', 'data' => $details);
            }
        }
        if ($details['total_payable_numeric'] === null) {
            return array('success' => false, 'message' => 'The QR website Total Payable Amount is invalid.', 'data' => $details);
        }

        return array('success' => true, 'message' => '', 'data' => $details);
    }
}
