<?php

if (!function_exists('shopeeOrderDetailPdfNormalizeText')) {
    function shopeeOrderDetailPdfNormalizeText($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r/u", "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', (string) $text);
        $text = preg_replace("/\n{2,}/u", "\n", (string) $text);
        return trim((string) $text);
    }
}

if (!function_exists('shopeeOrderDetailPdfNormalizeLookup')) {
    function shopeeOrderDetailPdfNormalizeLookup($text)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', shopeeOrderDetailPdfNormalizeText($text)));
    }
}

if (!function_exists('shopeeOrderDetailPdfNormalizeAmount')) {
    function shopeeOrderDetailPdfNormalizeAmount($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = str_ireplace(array('RM', 'MYR', 'SGD', 'USD'), '', $value);
        $value = preg_replace('/[^0-9\.\-]/', '', (string) $value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        return number_format(abs((float) $value), 2, '.', '');
    }
}

if (!function_exists('shopeeOrderDetailPdfLoadOptionList')) {
    function shopeeOrderDetailPdfLoadOptionList($dbConnect, $tableName, $labelField)
    {
        $list = array();
        if (!($dbConnect instanceof mysqli)) {
            return $list;
        }

        $query = "SELECT id, `" . mysqli_real_escape_string($dbConnect, $labelField) . "` AS option_label
                  FROM `" . mysqli_real_escape_string($dbConnect, $tableName) . "`
                  WHERE status = 'A'
                  ORDER BY `" . mysqli_real_escape_string($dbConnect, $labelField) . "` ASC";
        $result = mysqli_query($dbConnect, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $optionId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($optionId <= 0) {
                    continue;
                }
                $list[$optionId] = isset($row['option_label']) ? (string) $row['option_label'] : '';
            }
        }

        return $list;
    }
}

if (!function_exists('shopeeOrderDetailPdfResolveOptionId')) {
    function shopeeOrderDetailPdfResolveOptionId($rawValue, $options, $fallbacks = array())
    {
        $candidates = array_merge(array((string) $rawValue), is_array($fallbacks) ? $fallbacks : array());
        foreach ($candidates as $candidate) {
            $normalizedCandidate = shopeeOrderDetailPdfNormalizeLookup($candidate);
            if ($normalizedCandidate === '') {
                continue;
            }

            foreach ($options as $optionId => $optionLabel) {
                $normalizedLabel = shopeeOrderDetailPdfNormalizeLookup($optionLabel);
                if ($normalizedLabel === $normalizedCandidate) {
                    return (string) $optionId;
                }
            }

            foreach ($options as $optionId => $optionLabel) {
                $normalizedLabel = shopeeOrderDetailPdfNormalizeLookup($optionLabel);
                if ($normalizedLabel !== '' && (strpos($normalizedLabel, $normalizedCandidate) !== false || strpos($normalizedCandidate, $normalizedLabel) !== false)) {
                    return (string) $optionId;
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfSanitizePathSegment')) {
    function shopeeOrderDetailPdfSanitizePathSegment($value, $fallback)
    {
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim((string) $value));
        $value = trim((string) $value, '_');
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('shopeeOrderDetailPdfResolveSqlAccountFolder')) {
    function shopeeOrderDetailPdfResolveSqlAccountFolder($connect, $orderRow)
    {
        $brandIds = isset($orderRow['brand']) ? $orderRow['brand'] : '';
        $packageIds = isset($orderRow['package']) ? $orderRow['package'] : '';
        $folder = function_exists('shopeeOmsResolveSqlAccountFolderFromOrderData')
            ? shopeeOmsResolveSqlAccountFolderFromOrderData($connect, $brandIds, $packageIds)
            : '';
        $folder = strtolower(trim((string) $folder));

        if ($folder === '' || $folder === 'sqlaccount') {
            return 'unknown_sql_account';
        }

        return shopeeOrderDetailPdfSanitizePathSegment($folder, 'unknown_sql_account');
    }
}

if (!function_exists('shopeeOrderDetailPdfResolveCustomerFolder')) {
    function shopeeOrderDetailPdfResolveCustomerFolder($connect, $financeConnect, $orderRow)
    {
        $customerName = '';
        if (function_exists('shopeeOmsGetOrderCustomerNameText')) {
            $customerName = trim((string) shopeeOmsGetOrderCustomerNameText($connect, $financeConnect, $orderRow, 'shopee'));
        }
        if ($customerName === '') {
            $customerName = trim((string) (isset($orderRow['buyer']) ? $orderRow['buyer'] : ''));
        }
        return shopeeOrderDetailPdfSanitizePathSegment($customerName, 'unknown_customer');
    }
}

if (!function_exists('shopeeOrderDetailPdfResolveFilePrefix')) {
    function shopeeOrderDetailPdfResolveFilePrefix($orderRow)
    {
        $rawPrefix = trim((string) (isset($orderRow['buyer']) ? $orderRow['buyer'] : ''));
        return shopeeOrderDetailPdfSanitizePathSegment($rawPrefix, 'unknown_customer');
    }
}

if (!function_exists('shopeeOrderDetailPdfBuildRelativeDir')) {
    function shopeeOrderDetailPdfBuildRelativeDir($connect, $financeConnect, $orderRow)
    {
        return 'attachment/'
            . shopeeOrderDetailPdfResolveSqlAccountFolder($connect, $orderRow) . '/'
            . date('Y') . '/'
            . date('m') . '/'
            . date('d') . '/'
            . 'shopee_order_req/'
            . shopeeOrderDetailPdfResolveCustomerFolder($connect, $financeConnect, $orderRow) . '/';
    }
}

if (!function_exists('shopeeOrderDetailPdfStoreUpload')) {
    function shopeeOrderDetailPdfValidateUpload($fileInfo)
    {
        if (!is_array($fileInfo) || !isset($fileInfo['tmp_name']) || !isset($fileInfo['name'])) {
            return array('success' => false, 'message' => 'No PDF file uploaded.');
        }

        $uploadError = isset($fileInfo['error']) ? (int) $fileInfo['error'] : UPLOAD_ERR_OK;
        if ($uploadError !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => 'Failed to upload the Order Detail PDF.');
        }

        $originalName = basename((string) $fileInfo['name']);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            return array('success' => false, 'message' => 'Only PDF file is allowed.');
        }

        $rawContent = @file_get_contents((string) $fileInfo['tmp_name']);
        if ($rawContent === false || (string) $rawContent === '') {
            return array('success' => false, 'message' => 'The uploaded PDF could not be read.');
        }

        if (strncmp((string) $rawContent, '%PDF-', 5) !== 0) {
            return array('success' => false, 'message' => 'The uploaded file is not a valid PDF document.');
        }

        return array(
            'success' => true,
            'message' => '',
            'raw_content' => $rawContent,
            'original_name' => $originalName,
        );
    }
}

if (!function_exists('shopeeOrderDetailPdfStoreUpload')) {
    function shopeeOrderDetailPdfStoreUpload($fileInfo, $connect, $financeConnect, $orderRow)
    {
        $validationResult = shopeeOrderDetailPdfValidateUpload($fileInfo);
        if (empty($validationResult['success'])) {
            return array(
                'success' => false,
                'path' => '',
                'message' => isset($validationResult['message']) ? (string) $validationResult['message'] : 'Failed to upload the Order Detail PDF.',
            );
        }

        $relativeDir = shopeeOrderDetailPdfBuildRelativeDir($connect, $financeConnect, $orderRow);
        $targetFsDir = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($targetFsDir)) {
            @mkdir($targetFsDir, 0777, true);
        }
        if (!is_dir($targetFsDir)) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to create Order Detail PDF directory.');
        }

        $filePrefix = shopeeOrderDetailPdfResolveFilePrefix($orderRow);
        $targetName = $filePrefix . '_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.pdf';
        $targetFile = $targetFsDir . $targetName;

        if (!move_uploaded_file((string) $fileInfo['tmp_name'], $targetFile)) {
            return array('success' => false, 'path' => '', 'message' => 'Failed to save the uploaded Order Detail PDF.');
        }

        return array(
            'success' => true,
            'path' => shopeeOmsNormalizeAttachmentRelativePath($relativeDir . $targetName),
            'message' => '',
            'raw_content' => isset($validationResult['raw_content']) ? $validationResult['raw_content'] : '',
            'original_name' => isset($validationResult['original_name']) ? $validationResult['original_name'] : '',
        );
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractRawStrings')) {
    function shopeeOrderDetailPdfExtractRawStrings($rawPdfContent)
    {
        $rawPdfContent = (string) $rawPdfContent;
        if ($rawPdfContent === '') {
            return '';
        }

        $parts = array();
        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $rawPdfContent, $matches)) {
            foreach ($matches[0] as $match) {
                $match = substr((string) $match, 1, -1);
                $match = preg_replace_callback('/\\\\([0-7]{1,3})/', function ($m) {
                    return chr(octdec($m[1]));
                }, (string) $match);
                $match = str_replace(array('\n', '\r', '\t', '\(', '\)', '\\\\'), array("\n", "\n", ' ', '(', ')', '\\'), (string) $match);
                $match = shopeeOrderDetailPdfNormalizeText($match);
                if ($match !== '') {
                    $parts[] = $match;
                }
            }
        }

        return trim(implode("\n", array_unique($parts)));
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractText')) {
    function shopeeOrderDetailPdfExtractText($rawPdfContent, $clientPdfText = '')
    {
        $clientPdfText = trim((string) $clientPdfText);
        if ($clientPdfText !== '') {
            return shopeeOrderDetailPdfNormalizeText(mb_strcut($clientPdfText, 0, 524288, 'UTF-8'));
        }

        return shopeeOrderDetailPdfNormalizeText(shopeeOrderDetailPdfExtractRawStrings($rawPdfContent));
    }
}

if (!function_exists('shopeeOrderDetailPdfBuildStopPattern')) {
    function shopeeOrderDetailPdfBuildStopPattern($stopLabels)
    {
        $parts = array();
        foreach ((array) $stopLabels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $parts[] = preg_quote($label, '/');
        }

        return !empty($parts) ? '(?=(?:' . implode('|', $parts) . ')\b|$)' : '$';
    }
}

if (!function_exists('shopeeOrderDetailPdfGetTextLines')) {
    function shopeeOrderDetailPdfGetTextLines($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $normalizedLines = array();

        foreach ((array) $lines as $line) {
            $line = shopeeOrderDetailPdfNormalizeText($line);
            if ($line !== '') {
                $normalizedLines[] = $line;
            }
        }

        return $normalizedLines;
    }
}

if (!function_exists('shopeeOrderDetailPdfBuildLooseLabelPattern')) {
    function shopeeOrderDetailPdfBuildLooseLabelPattern($label)
    {
        $label = strtoupper((string) $label);
        $parts = preg_split('/[^A-Z0-9]+/', $label, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return '';
        }

        $wordPatterns = array();
        foreach ($parts as $part) {
            $wordPatterns[] = implode('[^A-Z0-9]*', str_split(preg_quote($part, '/')));
        }

        return implode('[^A-Z0-9]+', $wordPatterns);
    }
}

if (!function_exists('shopeeOrderDetailPdfBuildStrictLabelPattern')) {
    function shopeeOrderDetailPdfBuildStrictLabelPattern($label)
    {
        $label = strtoupper((string) $label);
        $parts = preg_split('/[^A-Z0-9]+/', $label, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return '';
        }

        $wordPatterns = array();
        foreach ($parts as $part) {
            $wordPatterns[] = implode('\s*', str_split(preg_quote($part, '/')));
        }

        return implode('\s+', $wordPatterns);
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractAmountList')) {
    function shopeeOrderDetailPdfExtractAmountList($text)
    {
        $results = array();
        if (preg_match_all('/-?\s*(?:RM|MYR|SGD|USD)?\s*[0-9][0-9,]*\.[0-9]{2}/i', (string) $text, $matches)) {
            foreach ((array) $matches[0] as $rawAmount) {
                $amount = shopeeOrderDetailPdfNormalizeAmount($rawAmount);
                if ($amount !== '') {
                    $results[] = $amount;
                }
            }
        }

        return $results;
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractAmountTokensWithSign')) {
    function shopeeOrderDetailPdfExtractAmountTokensWithSign($text)
    {
        $tokens = array();
        if (preg_match_all('/-?\s*(?:RM|MYR|SGD|USD)?\s*[0-9][0-9,]*\.[0-9]{2}/i', (string) $text, $matches)) {
            foreach ((array) $matches[0] as $rawAmount) {
                $rawAmount = trim((string) $rawAmount);
                $amount = shopeeOrderDetailPdfNormalizeAmount($rawAmount);
                if ($amount === '') {
                    continue;
                }

                $tokens[] = array(
                    'amount' => $amount,
                    'negative' => preg_match('/^\s*\-/', $rawAmount) === 1,
                );
            }
        }

        return $tokens;
    }
}

if (!function_exists('shopeeOrderDetailPdfChooseAmountToken')) {
    function shopeeOrderDetailPdfChooseAmountToken($tokens, $preferNegative = null)
    {
        if (empty($tokens)) {
            return '';
        }

        if (isset($tokens[0]['amount']) && (string) $tokens[0]['amount'] !== '' && (float) $tokens[0]['amount'] == 0.0) {
            return '0.00';
        }

        $wantNegative = $preferNegative === true;
        $wantPositive = $preferNegative === false;

        foreach ($tokens as $token) {
            $value = isset($token['amount']) ? (string) $token['amount'] : '';
            $isNegative = !empty($token['negative']);
            if ($value === '') {
                continue;
            }

            if ($wantNegative && $isNegative) {
                return $value;
            }
            if ($wantPositive && !$isNegative) {
                return $value;
            }
        }

        foreach ($tokens as $token) {
            if (!empty($token['amount'])) {
                return (string) $token['amount'];
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfBuildCompactText')) {
    function shopeeOrderDetailPdfBuildCompactText($text)
    {
        $text = strtoupper((string) $text);
        if ($text === '') {
            return '';
        }

        return preg_replace('/[^A-Z0-9\.\-]+/', '', $text);
    }
}

if (!function_exists('shopeeOrderDetailPdfGetMoneyBoundaryLabels')) {
    function shopeeOrderDetailPdfGetMoneyBoundaryLabels()
    {
        return array(
            'Product Price',
            'Merchandise Subtotal',
            'Deal Price',
            'Shopee Voucher',
            'Shop Voucher',
            'Seller Voucher',
            'Coins Redeemed',
            'Shopee Coins',
            'Vouchers & Rebates',
            'Shipping Fee Charged by Logistic Provider',
            'Estimated Shipping Fee Charged by Logistic Provider',
            'Shipping Fee Paid by Buyer',
            'Shipping Subtotal',
            'Estimated Shipping Subtotal',
            'Service Fee',
            'Transaction Fee',
            'Commission Fee',
            'Fees & Charges',
            'Order Income',
            'Estimated Order Income',
            'Final Amount',
        );
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractAmountByStrictLabels')) {
    function shopeeOrderDetailPdfExtractAmountByStrictLabels($text, $labels, $boundaryLabels = array(), $maxLen = 220, $preferNegative = null)
    {
        $source = strtoupper(shopeeOrderDetailPdfNormalizeText($text));
        if ($source === '' || empty($labels)) {
            return '';
        }

        $maxLen = max(80, min(600, (int) $maxLen));
        $boundaryPatterns = array();
        foreach ((array) $boundaryLabels as $boundaryLabel) {
            $pattern = shopeeOrderDetailPdfBuildStrictLabelPattern($boundaryLabel);
            if ($pattern !== '') {
                $boundaryPatterns[] = $pattern;
            }
        }

        foreach ((array) $labels as $label) {
            $labelPattern = shopeeOrderDetailPdfBuildStrictLabelPattern($label);
            if ($labelPattern === '') {
                continue;
            }

            if (!preg_match_all('/' . $labelPattern . '/i', $source, $labelMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ((array) $labelMatches[0] as $labelMatch) {
                $labelStart = isset($labelMatch[1]) ? (int) $labelMatch[1] : -1;
                $labelLen = strlen(isset($labelMatch[0]) ? (string) $labelMatch[0] : '');
                if ($labelStart < 0 || $labelLen <= 0) {
                    continue;
                }

                $segment = substr($source, $labelStart + $labelLen, $maxLen);
                if ($segment === '') {
                    continue;
                }

                $cutLen = strlen($segment);
                foreach ($boundaryPatterns as $boundaryPattern) {
                    if (preg_match('/' . $boundaryPattern . '/i', $segment, $boundaryMatch, PREG_OFFSET_CAPTURE)) {
                        $boundaryOffset = isset($boundaryMatch[0][1]) ? (int) $boundaryMatch[0][1] : -1;
                        if ($boundaryOffset > 0 && $boundaryOffset < $cutLen) {
                            $cutLen = $boundaryOffset;
                        }
                    }
                }

                $segment = substr($segment, 0, $cutLen);
                $tokens = shopeeOrderDetailPdfExtractAmountTokensWithSign($segment);
                $amount = shopeeOrderDetailPdfChooseAmountToken($tokens, $preferNegative);
                if ($amount !== '') {
                    return $amount;
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractAmountByLooseLabels')) {
    function shopeeOrderDetailPdfExtractAmountByLooseLabels($text, $labels)
    {
        $source = strtoupper((string) $text);
        if ($source === '' || empty($labels)) {
            return '';
        }

        foreach ((array) $labels as $label) {
            $labelPattern = shopeeOrderDetailPdfBuildLooseLabelPattern($label);
            if ($labelPattern === '') {
                continue;
            }

            $pattern = '/' . $labelPattern . '.{0,220}?(-?\s*(?:RM|MYR|SGD|USD)?\s*[0-9][0-9,]*\.[0-9]{2})/i';
            if (preg_match($pattern, $source, $matches)) {
                $amount = shopeeOrderDetailPdfNormalizeAmount(isset($matches[1]) ? $matches[1] : '');
                if ($amount !== '') {
                    return $amount;
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractAmountFromCompact')) {
    function shopeeOrderDetailPdfExtractAmountFromCompact($compactText, $labels)
    {
        $compactText = strtoupper((string) $compactText);
        if ($compactText === '' || empty($labels)) {
            return '';
        }

        foreach ((array) $labels as $label) {
            $labelKey = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) $label));
            if ($labelKey === '') {
                continue;
            }

            $pattern = '/' . $labelKey . '[A-Z]{0,40}?((?:RM|MYR|SGD|USD)?-?[0-9]{1,6}\.[0-9]{2})/';
            if (preg_match_all($pattern, $compactText, $matches) && !empty($matches[1])) {
                foreach ((array) $matches[1] as $rawAmount) {
                    $amount = shopeeOrderDetailPdfNormalizeAmount($rawAmount);
                    if ($amount !== '') {
                        return $amount;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractAmountByLineLabels')) {
    function shopeeOrderDetailPdfExtractAmountByLineLabels($text, $labels, $lineWindow = 1)
    {
        $lines = shopeeOrderDetailPdfGetTextLines($text);
        if (empty($lines) || empty($labels)) {
            return '';
        }

        $lineWindow = max(0, min(3, (int) $lineWindow));
        $labelPatterns = array();
        foreach ((array) $labels as $label) {
            $pattern = shopeeOrderDetailPdfBuildLooseLabelPattern($label);
            if ($pattern !== '') {
                $labelPatterns[] = $pattern;
            }
        }

        if (empty($labelPatterns)) {
            return '';
        }

        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            $lineUpper = strtoupper((string) $lines[$i]);
            $matched = false;

            foreach ($labelPatterns as $pattern) {
                if (preg_match('/' . $pattern . '/i', $lineUpper) === 1) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            $maxJ = min($lineCount - 1, $i + $lineWindow);
            for ($j = $i; $j <= $maxJ; $j++) {
                $tokens = shopeeOrderDetailPdfExtractAmountTokensWithSign($lines[$j]);
                $amount = shopeeOrderDetailPdfChooseAmountToken($tokens, null);
                if ($amount !== '') {
                    return $amount;
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfParseAmountByLabels')) {
    function shopeeOrderDetailPdfParseAmountByLabels($text, $labels)
    {
        foreach ((array) $labels as $label) {
            $currencyPattern = '/' . preg_quote($label, '/') . '.{0,220}?(-?\s*(?:RM|MYR|SGD|USD)\s*[0-9][0-9,]*\.?[0-9]*)(?!\s*%)/i';
            if (preg_match($currencyPattern, (string) $text, $matches)) {
                $amount = shopeeOrderDetailPdfNormalizeAmount(isset($matches[1]) ? $matches[1] : '');
                if ($amount !== '') {
                    return $amount;
                }
            }

            $numericPattern = '/' . preg_quote($label, '/') . '.{0,220}?(-?\s*[0-9][0-9,]*\.[0-9]{2})(?!\s*%)/i';
            if (preg_match($numericPattern, (string) $text, $matches)) {
                $amount = shopeeOrderDetailPdfNormalizeAmount(isset($matches[1]) ? $matches[1] : '');
                if ($amount !== '') {
                    return $amount;
                }
            }

            $looseLabelPattern = shopeeOrderDetailPdfBuildLooseLabelPattern($label);
            if ($looseLabelPattern === '') {
                continue;
            }

            $looseCurrencyPattern = '/' . $looseLabelPattern . '.{0,320}?(-?\s*(?:RM|MYR|SGD|USD)\s*[0-9][0-9,]*\.?[0-9]*)(?!\s*%)/i';
            if (preg_match($looseCurrencyPattern, strtoupper((string) $text), $matches)) {
                $amount = shopeeOrderDetailPdfNormalizeAmount(isset($matches[1]) ? $matches[1] : '');
                if ($amount !== '') {
                    return $amount;
                }
            }

            $looseNumericPattern = '/' . $looseLabelPattern . '.{0,320}?(-?\s*[0-9][0-9,]*\.[0-9]{2})(?!\s*%)/i';
            if (preg_match($looseNumericPattern, strtoupper((string) $text), $matches)) {
                $amount = shopeeOrderDetailPdfNormalizeAmount(isset($matches[1]) ? $matches[1] : '');
                if ($amount !== '') {
                    return $amount;
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractVoucherAmount')) {
    function shopeeOrderDetailPdfExtractVoucherAmount($text)
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return '';
        }

        $normalizedText = shopeeOrderDetailPdfNormalizeText($text);
        $labelPattern = '(?:shop|seller)\s+voucher\s+paid\s+by\s+seller';

        if (preg_match('/' . $labelPattern . '.{0,180}?-\s*(?:RM|MYR|SGD|USD)?\s*([0-9][0-9,]*\.[0-9]{2})/i', $normalizedText, $matches)) {
            return shopeeOrderDetailPdfNormalizeAmount(isset($matches[1]) ? $matches[1] : '');
        }

        $lines = shopeeOrderDetailPdfGetTextLines($text);
        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            $lineText = trim((string) $lines[$i]);
            if ($lineText === '' || !preg_match('/\b(?:shop|seller)\s+voucher\s+paid\s+by\s+seller\b/i', $lineText)) {
                continue;
            }

            $nearText = $lineText;
            for ($j = 1; $j <= 3; $j++) {
                if (isset($lines[$i + $j])) {
                    $nearText .= ' ' . trim((string) $lines[$i + $j]);
                }
            }

            if (preg_match_all('/-\s*(?:RM|MYR|SGD|USD)?\s*([0-9][0-9,]*\.[0-9]{2})/i', $nearText, $matches) && !empty($matches[1])) {
                $amount = end($matches[1]);
                return shopeeOrderDetailPdfNormalizeAmount($amount);
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractMonetaryValues')) {
    function shopeeOrderDetailPdfExtractMonetaryValues($text)
    {
        $text = (string) $text;
        $boundaries = shopeeOrderDetailPdfGetMoneyBoundaryLabels();
        $serviceFeeLabels = array(
            'Service Fee',
            'Service Fee (Incl. GST)',
            'Service Fee (Incl. Gst)',
            'Service Fee Incl GST',
            'Service Fee Incl Gst',
        );
        $transactionFeeLabels = array(
            'Transaction Fee',
            'Transaction Fee (Incl. GST)',
            'Transaction Fee (Incl. Gst)',
            'Transaction Fee Incl GST',
            'Transaction Fee Incl Gst',
        );

        $productPrice = shopeeOrderDetailPdfExtractAmountByStrictLabels($text, array('Product Price', 'Merchandise Subtotal', 'Deal Price'), $boundaries, 260, false);
        if ($productPrice === '') {
            $productPrice = shopeeOrderDetailPdfExtractAmountByLooseLabels($text, array('Product Price', 'Merchandise Subtotal', 'Deal Price'));
        }
        if ($productPrice === '') {
            $productPrice = shopeeOrderDetailPdfExtractAmountFromCompact(shopeeOrderDetailPdfBuildCompactText($text), array('Product Price', 'Merchandise Subtotal', 'Deal Price'));
        }

        $shippingFee = shopeeOrderDetailPdfExtractAmountByStrictLabels($text, array('Shipping Subtotal', 'Estimated Shipping Subtotal'), $boundaries, 260, false);
        if ($shippingFee === '') {
            $shippingFee = shopeeOrderDetailPdfExtractAmountByLooseLabels($text, array('Shipping Subtotal', 'Estimated Shipping Subtotal'));
        }

        $serviceFee = shopeeOrderDetailPdfExtractAmountByStrictLabels($text, $serviceFeeLabels, $boundaries, 220, true);
        if ($serviceFee === '') {
            $serviceFee = shopeeOrderDetailPdfExtractAmountByLineLabels($text, $serviceFeeLabels, 2);
        }
        if ($serviceFee === '') {
            $serviceFee = shopeeOrderDetailPdfExtractAmountByLooseLabels($text, $serviceFeeLabels);
        }

        $transactionFee = shopeeOrderDetailPdfExtractAmountByStrictLabels($text, $transactionFeeLabels, $boundaries, 220, true);
        if ($transactionFee === '') {
            $transactionFee = shopeeOrderDetailPdfExtractAmountByLineLabels($text, $transactionFeeLabels, 2);
        }
        if ($transactionFee === '') {
            $transactionFee = shopeeOrderDetailPdfExtractAmountByLooseLabels($text, $transactionFeeLabels);
        }

        $commissionFee = shopeeOrderDetailPdfExtractAmountByStrictLabels($text, array('Commission Fee'), $boundaries, 220, true);
        if ($commissionFee === '') {
            $commissionFee = shopeeOrderDetailPdfExtractAmountByLooseLabels($text, array('Commission Fee'));
        }

        return array(
            'product_price' => $productPrice,
            'voucher' => shopeeOrderDetailPdfExtractVoucherAmount($text),
            'act_shipping_fee' => $shippingFee,
            'service_fee' => $serviceFee,
            'trans_fee' => $transactionFee,
            'ams_fee' => $commissionFee,
            'fees' => shopeeOrderDetailPdfParseAmountByLabels($text, array('Fees & Charges', 'Fees and Charges')),
        );
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractFieldByLabels')) {
    function shopeeOrderDetailPdfExtractFieldByLabels($text, $labels, $stopLabels = array())
    {
        $text = shopeeOrderDetailPdfNormalizeText($text);
        if ($text === '') {
            return '';
        }

        $stopPattern = shopeeOrderDetailPdfBuildStopPattern($stopLabels);
        foreach ((array) $labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            $pattern = '/' . preg_quote($label, '/') . '\s*[:\-]?\s*(.{1,180}?)' . $stopPattern . '/is';
            if (preg_match($pattern, $text, $matches)) {
                $value = trim((string) $matches[1]);
                $value = preg_replace('/\s{2,}/', ' ', (string) $value);
                if ($value !== '') {
                    return shopeeOrderDetailPdfNormalizeText($value);
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractOrderDateTime')) {
    function shopeeOrderDetailPdfExtractOrderDateTime($text)
    {
        $text = shopeeOrderDetailPdfNormalizeText($text);
        if ($text === '') {
            return array('date' => '', 'time' => '');
        }

        $patterns = array(
            '/(?:new\s+order|order\s+history|order\s+time)\D{0,80}([0-9]{2})[\/\-]([0-9]{2})[\/\-]([0-9]{4})\s+([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?/iu',
            '/(?:new\s+order|order\s+history|order\s+time)\D{0,80}([0-9]{4})[\/\-]([0-9]{2})[\/\-]([0-9]{2})\s+([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?/iu',
        );

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $text, $matches) !== 1) {
                continue;
            }

            if ($index === 0) {
                $day = (int) $matches[1];
                $month = (int) $matches[2];
                $year = (int) $matches[3];
                $hour = (int) $matches[4];
                $minute = (int) $matches[5];
                $second = isset($matches[6]) && $matches[6] !== '' ? (int) $matches[6] : 0;
            } else {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
                $hour = (int) $matches[4];
                $minute = (int) $matches[5];
                $second = isset($matches[6]) && $matches[6] !== '' ? (int) $matches[6] : 0;
            }

            if (!checkdate($month, $day, $year)) {
                continue;
            }

            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
                continue;
            }

            return array(
                'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                'time' => sprintf('%02d:%02d:%02d', $hour, $minute, $second),
            );
        }

        return array('date' => '', 'time' => '');
    }
}

if (!function_exists('shopeeOrderDetailPdfNormalizeOrderIdCandidate')) {
    function shopeeOrderDetailPdfNormalizeOrderIdCandidate($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^A-Za-z0-9]+/', '', $value);
        if ($value === null || $value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length < 10 || $length > 30) {
            return '';
        }

        if (!preg_match('/\d/', $value)) {
            return '';
        }

        return $value;
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractOrderIdFromText')) {
    function shopeeOrderDetailPdfExtractOrderIdFromText($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        if (preg_match_all('/(?:Order\s*(?:ID|SN|No|Number)\s*[:#\-]?\s*)([^\r\n]{6,80})/i', $text, $labelMatches)) {
            foreach ((array) $labelMatches[1] as $rawCandidate) {
                if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9\-_]{8,40}/', (string) $rawCandidate, $tokenMatches)) {
                    foreach ((array) $tokenMatches[0] as $token) {
                        $candidate = shopeeOrderDetailPdfNormalizeOrderIdCandidate($token);
                        if ($candidate !== '') {
                            return $candidate;
                        }
                    }
                }

                $candidate = shopeeOrderDetailPdfNormalizeOrderIdCandidate($rawCandidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if (preg_match_all('/(?:Order\s*(?:ID|SN|No|Number))[\s:;#\-]*((?:[A-Za-z0-9][\s\-\/_]?){10,40})/i', $text, $spacedMatches)) {
            foreach ((array) $spacedMatches[1] as $rawCandidate) {
                $candidate = shopeeOrderDetailPdfNormalizeOrderIdCandidate($rawCandidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if (preg_match_all('/\b([A-Za-z0-9][A-Za-z0-9\-\/_]{9,35})\b/', $text, $genericMatches)) {
            foreach ((array) $genericMatches[1] as $rawCandidate) {
                $candidate = shopeeOrderDetailPdfNormalizeOrderIdCandidate($rawCandidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractOrderIdFromPdfBinary')) {
    function shopeeOrderDetailPdfExtractOrderIdFromPdfBinary($rawContent)
    {
        $rawContent = (string) $rawContent;
        if ($rawContent === '') {
            return '';
        }

        $directPatterns = array(
            '/sale\/order\/([A-Za-z0-9\-_]{8,40})/i',
            '/order[_\-]?(?:id|sn|no|number)[^A-Za-z0-9]{0,20}([A-Za-z0-9\-_]{8,40})/i',
            '/(?:orderid|ordersn)=([A-Za-z0-9\-_]{8,40})/i',
        );

        foreach ($directPatterns as $pattern) {
            if (preg_match($pattern, $rawContent, $matches) === 1) {
                $candidate = shopeeOrderDetailPdfNormalizeOrderIdCandidate(isset($matches[1]) ? $matches[1] : '');
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if (preg_match_all('/\/URI\s*\(([^\)]{1,600})\)/i', $rawContent, $uriMatches)) {
            foreach ((array) $uriMatches[1] as $uri) {
                $uri = html_entity_decode((string) $uri, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if (preg_match('/sale\/order\/([A-Za-z0-9\-_]{8,40})/i', $uri, $matches) === 1) {
                    $candidate = shopeeOrderDetailPdfNormalizeOrderIdCandidate(isset($matches[1]) ? $matches[1] : '');
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }

                if (preg_match('/(?:orderid|ordersn)=([A-Za-z0-9\-_]{8,40})/i', $uri, $matches) === 1) {
                    $candidate = shopeeOrderDetailPdfNormalizeOrderIdCandidate(isset($matches[1]) ? $matches[1] : '');
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractOrderId')) {
    function shopeeOrderDetailPdfExtractOrderId($sourceText, $rawPdfContent = '')
    {
        $orderId = shopeeOrderDetailPdfExtractFieldByLabels(
            $sourceText,
            array('Order ID', 'Order SN', 'Order No', 'Order Number'),
            array('Order Status', 'Buyer Username', 'Buyer Name', 'Payment Method')
        );
        $orderId = shopeeOrderDetailPdfNormalizeOrderIdCandidate($orderId);

        if ($orderId === '') {
            $orderId = shopeeOrderDetailPdfExtractOrderIdFromText($sourceText);
        }

        if ($orderId === '' && (string) $rawPdfContent !== '') {
            $orderId = shopeeOrderDetailPdfExtractOrderIdFromPdfBinary($rawPdfContent);
        }

        return $orderId;
    }
}

if (!function_exists('shopeeOrderDetailPdfExtractMoneyByLabels')) {
    function shopeeOrderDetailPdfExtractMoneyByLabels($text, $labels, $stopLabels = array())
    {
        $text = shopeeOrderDetailPdfNormalizeText($text);
        if ($text === '') {
            return '';
        }

        foreach ((array) $labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            $labelPattern = preg_quote($label, '/');
            $pattern = '/' . $labelPattern . '\s*[:\-]?\s*([^A-Za-z]{0,8}(?:RM|MYR|SGD|USD|\$)?\s*[-]?\s*[0-9][0-9,]*\.[0-9]{2})/is';
            if (preg_match($pattern, $text, $matches)) {
                $amount = shopeeOrderDetailPdfNormalizeAmount($matches[1]);
                if ($amount !== '') {
                    return $amount;
                }
            }

            $snippet = shopeeOrderDetailPdfExtractFieldByLabels($text, array($label), $stopLabels);
            $amount = shopeeOrderDetailPdfNormalizeAmount($snippet);
            if ($amount !== '') {
                return $amount;
            }
        }

        return '';
    }
}

if (!function_exists('shopeeOrderDetailPdfGetOptionMaps')) {
    function shopeeOrderDetailPdfGetOptionMaps($financeConnect)
    {
        return array(
            'shopee_acc' => shopeeOrderDetailPdfLoadOptionList($financeConnect, SHOPEE_ACC, 'name'),
            'buyer_pay_meth' => shopeeOrderDetailPdfLoadOptionList($financeConnect, PAY_MTHD_SHOPEE, 'name'),
        );
    }
}

if (!function_exists('shopeeOrderDetailPdfParse')) {
    function shopeeOrderDetailPdfParse($connect, $financeConnect, $rawPdfContent, $clientPdfText = '')
    {
        $sourceText = shopeeOrderDetailPdfExtractText($rawPdfContent, $clientPdfText);
        if ($sourceText === '') {
            return array('success' => false, 'message' => 'Unable to extract text from the uploaded PDF file.');
        }

        $optionMaps = shopeeOrderDetailPdfGetOptionMaps($financeConnect);
        $payMethodLabel = shopeeOrderDetailPdfExtractFieldByLabels($sourceText, array('Payment Method', 'Payment Type'), array('Shop Name', 'Shopee Account', 'Order ID', 'Order SN'));
        $shopNameLabel = shopeeOrderDetailPdfExtractFieldByLabels($sourceText, array('Shop Name', 'Shopee Account'), array('Payment Method', 'Payment Type', 'Order ID', 'Order SN'));
        $orderId = shopeeOrderDetailPdfExtractOrderId($sourceText, $rawPdfContent);
        $buyerUsername = shopeeOrderDetailPdfExtractFieldByLabels($sourceText, array('Buyer Username', 'Buyer Name', 'Username'), array('Recipient Name', 'Recipient', 'Phone Number', 'Payment Method', 'Shop Name'));
        $money = shopeeOrderDetailPdfExtractMonetaryValues($sourceText);
        $orderDateTime = shopeeOrderDetailPdfExtractOrderDateTime($sourceText);

        $parsed = array(
            'orderID' => $orderId,
            'date' => isset($orderDateTime['date']) ? (string) $orderDateTime['date'] : '',
            'time' => isset($orderDateTime['time']) ? (string) $orderDateTime['time'] : '',
            'buyer' => $buyerUsername,
            'shopee_acc' => shopeeOrderDetailPdfResolveOptionId($shopNameLabel, $optionMaps['shopee_acc']),
            'shopee_acc_label' => $shopNameLabel,
            'buyer_pay_meth' => shopeeOrderDetailPdfResolveOptionId($payMethodLabel, $optionMaps['buyer_pay_meth']),
            'buyer_pay_meth_label' => $payMethodLabel,
            'price' => isset($money['product_price']) ? $money['product_price'] : '',
            'voucher' => isset($money['voucher']) ? $money['voucher'] : '',
            'act_shipping_fee' => isset($money['act_shipping_fee']) ? $money['act_shipping_fee'] : '',
            'service_fee' => isset($money['service_fee']) ? $money['service_fee'] : '',
            'trans_fee' => isset($money['trans_fee']) ? $money['trans_fee'] : '',
            'ams_fee' => isset($money['ams_fee']) ? $money['ams_fee'] : '',
            'fees' => isset($money['fees']) ? $money['fees'] : '',
            'final_amt' => shopeeOrderDetailPdfExtractMoneyByLabels($sourceText, array('Estimated Total Income', 'Final Amount', 'Total Income', 'Total')),
        );

        if ($parsed['fees'] === '') {
            $feeTotal = 0;
            $feeFound = false;
            foreach (array('service_fee', 'trans_fee', 'ams_fee') as $feeField) {
                if ($parsed[$feeField] !== '') {
                    $feeTotal += (float) $parsed[$feeField];
                    $feeFound = true;
                }
            }
            if ($feeFound) {
                $parsed['fees'] = number_format($feeTotal, 2, '.', '');
            }
        }

        return array(
            'success' => true,
            'message' => '',
            'source_text' => $sourceText,
            'parsed' => $parsed,
            'option_maps' => $optionMaps,
        );
    }
}

if (!function_exists('shopeeOrderDetailPdfGetFieldMeta')) {
    function shopeeOrderDetailPdfGetFieldMeta($financeConnect)
    {
        $optionMaps = shopeeOrderDetailPdfGetOptionMaps($financeConnect);
        return array(
            'orderID' => array('label' => 'Order ID', 'input_type' => 'text'),
            'date' => array('label' => 'Order Date', 'input_type' => 'date'),
            'time' => array('label' => 'Order Time', 'input_type' => 'time'),
            'buyer' => array('label' => 'Buyer Username', 'input_type' => 'text'),
            'shopee_acc' => array('label' => 'Shopee Account', 'input_type' => 'select', 'options' => $optionMaps['shopee_acc']),
            'buyer_pay_meth' => array('label' => 'Buyer Payment Method', 'input_type' => 'select', 'options' => $optionMaps['buyer_pay_meth']),
            'price' => array('label' => 'Product Price', 'input_type' => 'number'),
            'voucher' => array('label' => 'Voucher', 'input_type' => 'number'),
            'act_shipping_fee' => array('label' => 'Actual Shipping Fee', 'input_type' => 'number'),
            'service_fee' => array('label' => 'Service Fee', 'input_type' => 'number'),
            'trans_fee' => array('label' => 'Transaction Fee', 'input_type' => 'number'),
            'ams_fee' => array('label' => 'AMS Fee', 'input_type' => 'number'),
            'fees' => array('label' => 'Fees & Charges', 'input_type' => 'number'),
            'final_amt' => array('label' => 'Final Amount', 'input_type' => 'number'),
        );
    }
}

if (!function_exists('shopeeOrderDetailPdfBuildFieldDisplayValue')) {
    function shopeeOrderDetailPdfBuildFieldDisplayValue($fieldName, $fieldValue, $fieldMeta, $parsedData = array())
    {
        $fieldValue = is_scalar($fieldValue) || $fieldValue === null ? (string) $fieldValue : '';
        $inputType = isset($fieldMeta['input_type']) ? (string) $fieldMeta['input_type'] : 'text';
        if ($inputType === 'number') {
            return shopeeOrderDetailPdfNormalizeAmount($fieldValue);
        }

        if ($inputType === 'date') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fieldValue) === 1) {
                return $fieldValue;
            }
            return '';
        }

        if ($inputType === 'time') {
            if (preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $fieldValue) === 1) {
                return strlen($fieldValue) === 5 ? $fieldValue . ':00' : $fieldValue;
            }
            return '';
        }

        if ($inputType === 'select') {
            $options = isset($fieldMeta['options']) && is_array($fieldMeta['options']) ? $fieldMeta['options'] : array();
            if ($fieldValue !== '' && isset($options[(int) $fieldValue])) {
                return (string) $options[(int) $fieldValue];
            }

            $labelKey = $fieldName . '_label';
            if (isset($parsedData[$labelKey]) && trim((string) $parsedData[$labelKey]) !== '') {
                return trim((string) $parsedData[$labelKey]);
            }
        }

        return trim((string) $fieldValue);
    }
}

if (!function_exists('shopeeOrderDetailPdfBuildComparisonRows')) {
    function shopeeOrderDetailPdfBuildComparisonRows($connect, $financeConnect, $orderRow, $parsedData)
    {
        $fieldMetaMap = shopeeOrderDetailPdfGetFieldMeta($financeConnect);
        $rows = array();

        foreach ($fieldMetaMap as $fieldName => $fieldMeta) {
            $currentRaw = isset($orderRow[$fieldName]) ? (string) $orderRow[$fieldName] : '';
            $pdfRaw = isset($parsedData[$fieldName]) ? (string) $parsedData[$fieldName] : '';
            $pdfLabelKey = $fieldName . '_label';
            $pdfDisplay = shopeeOrderDetailPdfBuildFieldDisplayValue($fieldName, $pdfRaw, $fieldMeta, $parsedData);
            if ($pdfDisplay === '' && isset($parsedData[$pdfLabelKey])) {
                $pdfDisplay = trim((string) $parsedData[$pdfLabelKey]);
            }
            if ($pdfRaw === '' && $pdfDisplay === '') {
                continue;
            }

            $currentDisplay = shopeeOrderDetailPdfBuildFieldDisplayValue($fieldName, $currentRaw, $fieldMeta);
            $currentCompare = shopeeOrderDetailPdfNormalizeLookup($currentDisplay);
            $pdfCompare = shopeeOrderDetailPdfNormalizeLookup($pdfDisplay);
            if ($currentCompare === $pdfCompare) {
                continue;
            }

            $rows[] = array(
                'field_name' => $fieldName,
                'field_label' => isset($fieldMeta['label']) ? (string) $fieldMeta['label'] : $fieldName,
                'current_value' => $currentDisplay,
                'pdf_value' => $pdfDisplay,
                'final_value' => $pdfRaw !== '' ? $pdfRaw : $currentRaw,
                'input_type' => isset($fieldMeta['input_type']) ? (string) $fieldMeta['input_type'] : 'text',
                'options' => isset($fieldMeta['options']) && is_array($fieldMeta['options']) ? $fieldMeta['options'] : array(),
            );
        }

        return $rows;
    }
}

if (!function_exists('shopeeOrderDetailPdfPrepareFinalUpdates')) {
    function shopeeOrderDetailPdfPrepareFinalUpdates($financeConnect, $orderRow, $postedFinalValues, $pdfPath = '')
    {
        $postedFinalValues = is_array($postedFinalValues) ? $postedFinalValues : array();
        $fieldMetaMap = shopeeOrderDetailPdfGetFieldMeta($financeConnect);
        $updates = array();
        $historyChanges = array();
        $validationError = '';
        $currentOrderId = isset($orderRow['id']) ? (int) $orderRow['id'] : 0;

        foreach ($fieldMetaMap as $fieldName => $fieldMeta) {
            if (!array_key_exists($fieldName, $postedFinalValues)) {
                continue;
            }

            $oldRaw = isset($orderRow[$fieldName]) ? (string) $orderRow[$fieldName] : '';
            $newRaw = (string) $postedFinalValues[$fieldName];
            $inputType = isset($fieldMeta['input_type']) ? (string) $fieldMeta['input_type'] : 'text';
            if ($inputType === 'number') {
                $newRaw = shopeeOrderDetailPdfNormalizeAmount($newRaw);
            } else if ($inputType === 'date') {
                $newRaw = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $newRaw)) === 1 ? trim((string) $newRaw) : $oldRaw;
            } else if ($inputType === 'time') {
                $newRaw = trim((string) $newRaw);
                if (preg_match('/^\d{2}:\d{2}$/', $newRaw) === 1) {
                    $newRaw .= ':00';
                }
                if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $newRaw) !== 1) {
                    $newRaw = $oldRaw;
                }
            } else if ($inputType === 'select') {
                $options = isset($fieldMeta['options']) && is_array($fieldMeta['options']) ? $fieldMeta['options'] : array();
                $newRaw = trim((string) $newRaw);
                if ($newRaw !== '' && !isset($options[(int) $newRaw])) {
                    $newRaw = $oldRaw;
                }
            } else {
                $newRaw = trim((string) $newRaw);
                if ($fieldName === 'orderID') {
                    $normalizedOrderId = shopeeOrderDetailPdfNormalizeOrderIdCandidate($newRaw);
                    if ($normalizedOrderId !== '') {
                        $newRaw = $normalizedOrderId;
                    }
                }
            }

            $oldDisplay = shopeeOrderDetailPdfBuildFieldDisplayValue($fieldName, $oldRaw, $fieldMeta);
            $newDisplay = shopeeOrderDetailPdfBuildFieldDisplayValue($fieldName, $newRaw, $fieldMeta);
            if (shopeeOrderDetailPdfNormalizeLookup($oldDisplay) === shopeeOrderDetailPdfNormalizeLookup($newDisplay)) {
                continue;
            }

            if ($fieldName === 'orderID' && $newRaw !== '') {
                $safeOrderId = mysqli_real_escape_string($financeConnect, $newRaw);
                $duplicateQuery = "SELECT id
                                   FROM `" . SHOPEE_SG_ORDER_REQ . "`
                                   WHERE `orderID` = '" . $safeOrderId . "'
                                     AND `id` <> " . $currentOrderId . "
                                   LIMIT 1";
                $duplicateResult = mysqli_query($financeConnect, $duplicateQuery);
                if ($duplicateResult && mysqli_num_rows($duplicateResult) > 0) {
                    $validationError = 'Duplicate Order ID found in Shopee Order Request records.';
                    break;
                }
            }

            $updates[$fieldName] = $newRaw;
            $historyChanges[] = array(
                'field_name' => $fieldName,
                'field_label' => isset($fieldMeta['label']) ? (string) $fieldMeta['label'] : $fieldName,
                'old_value' => $oldDisplay,
                'new_value' => $newDisplay,
            );
        }

        if ($pdfPath !== '') {
            $oldPdfPath = isset($orderRow['order_detail_pdf']) ? trim((string) $orderRow['order_detail_pdf']) : '';
            if ($oldPdfPath !== $pdfPath) {
                $updates['order_detail_pdf'] = $pdfPath;
                $historyChanges[] = array(
                    'field_name' => 'order_detail_pdf',
                    'field_label' => 'Order Detail PDF',
                    'old_value' => $oldPdfPath,
                    'new_value' => $pdfPath,
                );
            } else {
                $updates['order_detail_pdf'] = $pdfPath;
            }
        }

        return array(
            'updates' => $updates,
            'history_changes' => $historyChanges,
            'validation_error' => $validationError,
        );
    }
}
