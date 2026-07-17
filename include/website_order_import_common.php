<?php

if (!function_exists('websiteOrderImportNormalizeAmount')) {
    function websiteOrderImportNormalizeAmount($value)
    {
        $value = trim((string) $value);
        $value = str_replace(array(',', 'RM', 'MYR', 'SGD', 'USD', 'S$'), '', strtoupper($value));
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }
}

if (!function_exists('websiteOrderImportExtractAmount')) {
    function websiteOrderImportExtractAmount($line)
    {
        if (preg_match('/(?:MYR|RM|SGD|USD|S\$|\$)?\s*(-?[0-9][0-9,]*\.[0-9]{2})/i', (string) $line, $matches)) {
            return websiteOrderImportNormalizeAmount($matches[1]);
        }

        return '';
    }
}

if (!function_exists('websiteOrderImportFindLineIndex')) {
    function websiteOrderImportFindLineIndex($lines, $needle, $startIndex = 0, $contains = true)
    {
        $needleLookup = normalizeImportLookup($needle);
        if ($needleLookup === '') {
            return -1;
        }

        foreach ((array) $lines as $index => $line) {
            if ((int) $index < (int) $startIndex) {
                continue;
            }
            $lineLookup = normalizeImportLookup($line);
            if (($contains && strpos($lineLookup, $needleLookup) !== false) || (!$contains && $lineLookup === $needleLookup)) {
                return (int) $index;
            }
        }

        return -1;
    }
}

if (!function_exists('websiteOrderImportFindAmountNearLabel')) {
    function websiteOrderImportFindAmountNearLabel($lines, $labels, $startIndex = 0, $maxLookAhead = 8)
    {
        $labels = (array) $labels;
        foreach ($lines as $index => $line) {
            if ((int) $index < (int) $startIndex) {
                continue;
            }

            $lineLookup = normalizeImportLookup($line);
            $matched = false;
            foreach ($labels as $label) {
                $labelLookup = normalizeImportLookup($label);
                if ($labelLookup !== '' && strpos($lineLookup, $labelLookup) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                continue;
            }

            for ($lookIndex = (int) $index; $lookIndex < min(count($lines), (int) $index + (int) $maxLookAhead + 1); $lookIndex++) {
                $amount = websiteOrderImportExtractAmount($lines[$lookIndex]);
                if ($amount !== '') {
                    return array('amount' => $amount, 'index' => $lookIndex);
                }
            }
        }

        return array('amount' => '', 'index' => -1);
    }
}

if (!function_exists('websiteOrderImportGetProductText')) {
    function websiteOrderImportGetProductText($lines)
    {
        $shippingInfoIndex = websiteOrderImportFindLineIndex($lines, 'Shipping info', 0, false);
        if ($shippingInfoIndex < 0) {
            return '';
        }

        $homeIndex = websiteOrderImportFindLineIndex($lines, 'Home', $shippingInfoIndex + 1, false);
        $endIndex = $homeIndex > $shippingInfoIndex ? $homeIndex : count($lines);
        $amountIndex = -1;
        for ($index = $shippingInfoIndex + 1; $index < $endIndex; $index++) {
            if (websiteOrderImportExtractAmount($lines[$index]) !== '') {
                $amountIndex = $index;
                break;
            }
        }
        if ($amountIndex < 0) {
            return '';
        }

        $candidates = array();
        for ($index = $shippingInfoIndex + 1; $index < $amountIndex; $index++) {
            $line = normalizeImportText($lines[$index]);
            if ($line === '' || preg_match('/^(?:\+|\$|\d+)$/', $line) || preg_match('/working\s*days/i', $line)) {
                continue;
            }
            if (preg_match('/^(?:shipping|customer|contact|payment|total|subtotal|discount|notes?)$/i', $line)) {
                continue;
            }
            $candidates[] = $line;
        }

        if (empty($candidates)) {
            return '';
        }

        return normalizeImportText(implode(' ', array_slice($candidates, -2)));
    }
}

if (!function_exists('websiteOrderImportTokenize')) {
    function websiteOrderImportTokenize($value)
    {
        $value = strtolower(normalizeImportText($value));
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
        $tokens = array_filter(explode(' ', trim($value)), 'strlen');
        return array_values(array_unique($tokens));
    }
}

if (!function_exists('websiteOrderImportPackageMatchScore')) {
    function websiteOrderImportPackageMatchScore($candidate, $packageRow, $countryText = '')
    {
        $candidateLookup = normalizeImportLookup($candidate);
        $name = isset($packageRow['name']) ? (string) $packageRow['name'] : '';
        $itemCode = isset($packageRow['item_code']) ? (string) $packageRow['item_code'] : '';
        $description = isset($packageRow['item_description']) ? (string) $packageRow['item_description'] : '';
        $nameLookup = normalizeImportLookup($name);
        $itemCodeLookup = normalizeImportLookup($itemCode);
        if ($candidateLookup === '' || ($nameLookup === '' && $itemCodeLookup === '')) {
            return -1;
        }

        if ($candidateLookup === $nameLookup || $candidateLookup === $itemCodeLookup) {
            return 10000;
        }
        if ($itemCodeLookup !== '' && strpos($candidateLookup, $itemCodeLookup) !== false) {
            return 9000;
        }

        $candidateTokens = websiteOrderImportTokenize($candidate);
        $candidateDescriptionTokens = $candidateTokens;
        $packageTokens = websiteOrderImportTokenize($name . ' ' . $description . ' ' . $itemCode);
        $stopTokens = array('the', 'and', 'free', 'box', 'boxes', 'bottle', 'bottles', 'promo', 'promotion', 'web', 'my');
        $score = 0;
        $matchedTokens = 0;
        foreach ($packageTokens as $packageToken) {
            if (in_array($packageToken, $stopTokens, true)) {
                continue;
            }
            foreach ($candidateDescriptionTokens as $candidateToken) {
                if ($packageToken === $candidateToken) {
                    $score += ctype_digit($packageToken) ? 5 : 3;
                    $matchedTokens++;
                    break;
                }
                if (strlen($candidateToken) >= 2 && (strpos($packageToken, $candidateToken) === 0 || strpos($candidateToken, $packageToken) === 0)) {
                    $score += strlen($candidateToken) <= 3 ? 8 : 2;
                    $matchedTokens++;
                    break;
                }
                if (strlen($packageToken) >= 4 && strlen($candidateToken) >= 3 && levenshtein($packageToken, $candidateToken) <= 2) {
                    $score++;
                    $matchedTokens++;
                    break;
                }
            }
        }

        // Adjacent product words are more discriminating than repeated
        // quantities such as "2" and generic words such as "box". This also
        // handles PDF text that separates a name like CarbZero into Carb Zero.
        $meaningfulCandidateTokens = array_values(array_filter($candidateTokens, function ($token) use ($stopTokens) {
            return strlen($token) >= 3 && !in_array($token, $stopTokens, true);
        }));
        $packageCompactText = normalizeImportLookup($name . ' ' . $description);
        for ($index = 0; $index < count($meaningfulCandidateTokens) - 1; $index++) {
            $pair = $meaningfulCandidateTokens[$index] . $meaningfulCandidateTokens[$index + 1];
            if (strlen($pair) >= 7 && strpos($packageCompactText, $pair) !== false) {
                $score += 10;
            }
        }

        // Quantities are important for separating otherwise similar Website
        // packages, especially when the PDF drops part of "Sunz"/"Moonz".
        preg_match_all('/\d+/', normalizeImportLookup($candidate), $candidateNumbers);
        preg_match_all('/\d+/', normalizeImportLookup($name . ' ' . $description), $packageNumbers);
        $candidateNumbers = array_values(array_unique(isset($candidateNumbers[0]) ? $candidateNumbers[0] : array()));
        $packageNumbers = array_values(array_unique(isset($packageNumbers[0]) ? $packageNumbers[0] : array()));
        foreach ($candidateNumbers as $candidateNumber) {
            $score += in_array($candidateNumber, $packageNumbers, true) ? 15 : -10;
        }

        $packageNameCompactText = normalizeImportLookup($name);
        $packageDescriptionCompactText = normalizeImportLookup($description);
        preg_match_all('/(\d+)\s*(box(?:es)?|bottle(?:s)?)/i', normalizeImportText($candidate), $candidateQuantities, PREG_SET_ORDER);
        foreach ($candidateQuantities as $candidateQuantity) {
            $quantityToken = (string) $candidateQuantity[1] . (stripos($candidateQuantity[2], 'bottle') !== false ? 'bottle' : 'box');
            if (strpos($packageNameCompactText, $quantityToken) !== false) {
                $score += 25;
            } else if (strpos($packageDescriptionCompactText, $quantityToken) !== false) {
                $score += 5;
            } else {
                $score -= 12;
            }
        }

        $packageNameTokens = websiteOrderImportTokenize($name);
        $packageDescriptionTokens = websiteOrderImportTokenize($description);
        foreach ($candidateTokens as $candidateToken) {
            if (strlen($candidateToken) < 2 || strlen($candidateToken) > 3 || in_array($candidateToken, $stopTokens, true)) {
                continue;
            }
            $nameMatched = false;
            foreach ($packageNameTokens as $packageToken) {
                if (strpos($packageToken, $candidateToken) === 0 || strpos($candidateToken, $packageToken) === 0) {
                    $nameMatched = true;
                    break;
                }
            }
            if ($nameMatched) {
                $score += 12;
            } else {
                foreach ($packageDescriptionTokens as $packageToken) {
                    if (strpos($packageToken, $candidateToken) === 0 || strpos($candidateToken, $packageToken) === 0) {
                        $score += 2;
                        break;
                    }
                }
            }
        }

        if (stripos($name, '(WEB)') !== false || stripos($itemCode, 'WEB-') === 0) {
            $score += 2;
        }
        if ($countryText !== '' && stripos($name, '(' . $countryText . ')') !== false) {
            $score += 2;
        }
        return $matchedTokens >= 2 ? $score : -1;
    }
}

if (!function_exists('websiteOrderImportResolvePackage')) {
    function websiteOrderImportResolvePackage($candidate, $packageOptions, $countryText = '')
    {
        $bestRow = array();
        $bestScore = -1;
        foreach ((array) $packageOptions as $packageRow) {
            $score = websiteOrderImportPackageMatchScore($candidate, $packageRow, $countryText);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRow = $packageRow;
            }
        }
        return $bestScore >= 4 ? $bestRow : array();
    }
}

if (!function_exists('websiteOrderImportExtractOrderIdFromPdfSource')) {
    function websiteOrderImportExtractOrderIdFromPdfSource($pdfSource)
    {
        $pdfSource = (string) $pdfSource;
        if ($pdfSource === '') {
            return '';
        }

        // Shopline embeds the internal order ID in the hyperlink target as
        // /admin/orders/{numeric-id}. The visible merchant order code such
        // as UBCD1001 is not the order ID used by the Website Order table.
        if (preg_match('~admin/orders/([0-9]{12,})~i', $pdfSource, $matches)) {
            return (string) $matches[1];
        }

        return '';
    }
}

if (!function_exists('websiteOrderImportExtractPdfFields')) {
    function websiteOrderImportExtractPdfFields($pdfText, $countryOptions = array(), $pdfSource = '')
    {
        $lines = getPdfTextLines($pdfText);
        $orderId = websiteOrderImportExtractOrderIdFromPdfSource($pdfSource . "\n" . (string) $pdfText);

        // "Customer payment" is split into separate PDF text lines as
        // "Customer" and "payment". Select the Customer heading that is
        // followed closely by Gender, which is the actual customer panel.
        $customerIndex = -1;
        $genderIndex = -1;
        foreach ($lines as $candidateIndex => $candidateLine) {
            if (normalizeImportLookup($candidateLine) !== 'customer') {
                continue;
            }
            $candidateGenderIndex = websiteOrderImportFindLineIndex($lines, 'Gender', (int) $candidateIndex + 1, true);
            if ($candidateGenderIndex > (int) $candidateIndex && $candidateGenderIndex <= (int) $candidateIndex + 5) {
                $customerIndex = (int) $candidateIndex;
                $genderIndex = $candidateGenderIndex;
                break;
            }
        }
        if ($customerIndex < 0) {
            $customerIndex = websiteOrderImportFindLineIndex($lines, 'Customer', 0, false);
            $genderIndex = websiteOrderImportFindLineIndex($lines, 'Gender', max(0, $customerIndex + 1), true);
        }
        $customerName = '';
        if ($customerIndex >= 0) {
            $customerNameParts = array();
            for ($index = $customerIndex + 1; $index < ($genderIndex > $customerIndex ? $genderIndex : min(count($lines), $customerIndex + 8)); $index++) {
                $candidate = normalizeImportText($lines[$index]);
                if ($candidate !== '' && !preg_match('/^(?:payment|notes?|registered|login|total order)$/i', $candidate)) {
                    $customerNameParts[] = $candidate;
                }
            }
            $customerName = normalizeImportText(implode(' ', array_slice($customerNameParts, 0, 3)));
        }

        $contactIndex = websiteOrderImportFindLineIndex($lines, 'Contact information', 0, false);
        $email = '';
        $emailLineIndex = -1;
        $emailEndIndex = -1;
        $emailBlockStart = $customerIndex >= 0 ? $customerIndex + 1 : 0;
        $emailBlockEnd = $contactIndex > $emailBlockStart ? $contactIndex : count($lines);
        for ($index = $emailBlockStart; $index < $emailBlockEnd && $email === ''; $index++) {
            $candidate = normalizeImportText($lines[$index]);
            if (preg_match('/[A-Z][A-Z0-9._%+\-]*@[A-Z0-9.\-]+\.(?:com|net|org|my|sg|cn|tw)/i', $candidate, $matches)) {
                $email = strtolower(trim($matches[0]));
                $emailLineIndex = $index;
                $emailEndIndex = $index;
                break;
            }
            if (strpos($candidate, '@') !== false) {
                $combinedCandidate = $candidate;
                for ($continuationIndex = $index + 1; $continuationIndex < min($emailBlockEnd, $index + 3); $continuationIndex++) {
                    $combinedCandidate .= normalizeImportText($lines[$continuationIndex]);
                    if (preg_match('/[A-Z][A-Z0-9._%+\-]*@[A-Z0-9.\-]+\.(?:com|net|org|my|sg|cn|tw)/i', $combinedCandidate, $matches)) {
                        $email = strtolower(trim($matches[0]));
                        $emailLineIndex = $index;
                        $emailEndIndex = $continuationIndex;
                        break 2;
                    }
                }
            }
        }

        if ($emailLineIndex < 0 && $email !== '') {
            $emailLineIndex = websiteOrderImportFindLineIndex($lines, substr($email, 0, min(12, strlen($email))), 0, true);
            $emailEndIndex = $emailLineIndex;
        }
        $shippingName = '';
        $shippingNameIndex = -1;
        $shippingNameEndIndex = -1;
        $searchStart = $emailEndIndex >= 0 ? $emailEndIndex + 1 : max(0, $customerIndex + 1);
        $searchEnd = $contactIndex > $searchStart ? $contactIndex : min(count($lines), $searchStart + 20);

        // The recipient name is often split across separate PDF text runs,
        // for example "倩宜" and "李". Match the extracted customer name
        // across adjacent lines before falling back to one line.
        if ($customerName !== '') {
            for ($index = $searchStart; $index < $searchEnd && $shippingName === ''; $index++) {
                $combinedName = '';
                for ($nameEndIndex = $index; $nameEndIndex < min($searchEnd, $index + 4); $nameEndIndex++) {
                    $candidate = normalizeImportText($lines[$nameEndIndex]);
                    if ($candidate === '' || preg_match('/^(?:No mobile number|Contact information|Shipping info|Customer|com|net|org|my|[a-z]{1,3})$/i', $candidate)) {
                        if ($combinedName === '') {
                            continue;
                        }
                        break;
                    }
                    if (preg_match('/^\+?[0-9][0-9\s\-]{5,}$/', $candidate)) {
                        break;
                    }

                    $combinedName = $combinedName === '' ? $candidate : $combinedName . ' ' . $candidate;
                    if ($combinedName === $customerName) {
                        $shippingName = $combinedName;
                        $shippingNameIndex = $index;
                        $shippingNameEndIndex = $nameEndIndex;
                        break;
                    }
                    if (preg_match('/[0-9]/', $candidate)) {
                        break;
                    }
                }
            }
        }

        for ($index = $searchStart; $index < $searchEnd && $shippingName === ''; $index++) {
            $candidate = normalizeImportText($lines[$index]);
            if ($candidate === '' || preg_match('/^(?:No mobile number|Contact information|Shipping info|Customer|com|net|org|my|[a-z]{1,3})$/i', $candidate)) {
                continue;
            }
            if (preg_match('/^\+?[0-9][0-9\s\-]{5,}$/', $candidate)) {
                continue;
            }
            $shippingName = $candidate;
            $shippingNameIndex = $index;
            $shippingNameEndIndex = $index;
            break;
        }
        if ($shippingName === '') {
            $shippingName = $customerName;
            $shippingNameIndex = $customerIndex;
            $shippingNameEndIndex = $customerIndex;
        }
        if ($customerName === '') {
            $customerName = $shippingName;
        }

        $contact = '';
        foreach ($lines as $index => $line) {
            $candidate = normalizeImportText($line);
            if (preg_match('/^\+?[0-9][0-9\s\-]{6,}$/', $candidate) && !preg_match('/^20[0-9]{2}[\-\/]/', $candidate)) {
                $contact = $candidate;
                break;
            }
        }

        // Read the address from the text between the shipping recipient and
        // Contact information. Shopline's PDF content stream can place the
        // visible "Shipping info" heading after these address text runs, so
        // using that heading as the block start loses the real address.
        $addressParts = array();
        $addressEndIndex = $contactIndex > $shippingNameEndIndex ? $contactIndex : count($lines);
        for ($index = $shippingNameEndIndex + 1; $index < $addressEndIndex; $index++) {
            $candidate = normalizeImportText($lines[$index]);
            if ($candidate === '' || preg_match('/^(?:No mobile number|Contact information|Shipping info)$/i', $candidate)) {
                continue;
            }
            if (preg_match('/^\+?[0-9][0-9\s\-]{5,}$/', $candidate)) {
                break;
            }
            $addressLine = preg_replace('/^TheTrilinq$/i', 'The Trilinq', $candidate);
            $addressParts[] = $addressLine === null ? $candidate : $addressLine;
        }

        // Merge the short glyph runs produced by the PDF font into the
        // original street line, e.g. 街 + 11 + 巷 + 3 + 號 + 3 + 樓之 + 2.
        $mergedAddressParts = array();
        $addressFragment = '';
        foreach ($addressParts as $addressPart) {
            $addressPart = normalizeImportText($addressPart);
            $isShortCjkFragment = strlen($addressPart) <= 9 && preg_match('/^[\p{Han}0-9]+$/u', $addressPart);
            if ($isShortCjkFragment) {
                $addressFragment .= $addressPart;
                continue;
            }
            if ($addressFragment !== '') {
                $mergedAddressParts[] = $addressFragment;
                $addressFragment = '';
            }
            $mergedAddressParts[] = $addressPart;
        }
        if ($addressFragment !== '') {
            $mergedAddressParts[] = $addressFragment;
        }
        $addressParts = $mergedAddressParts;

        if ($shippingName !== '') {
            // Website order exports use the shipping recipient as the
            // customer name for this import flow.
            $customerName = $shippingName;
        }

        $subtotal = websiteOrderImportFindAmountNearLabel($lines, array('Subtotal'), 0, 8);
        $shipping = websiteOrderImportFindAmountNearLabel($lines, array('Shipping fee'), 0, 8);
        $discount = websiteOrderImportFindAmountNearLabel($lines, array('Discount codes', 'Discount code'), 0, 8);
        $total = websiteOrderImportFindAmountNearLabel($lines, array('Total'), $shipping['index'] >= 0 ? $shipping['index'] : 0, 8);

        // The order page also contains the warehouse/location country. Prefer
        // the country printed with the shipping fee, then the shipping address,
        // so the imported country is the customer's destination country.
        $shippingLabelIndex = websiteOrderImportFindLineIndex($lines, 'Shipping fee', 0, false);
        $shippingRouteText = '';
        if ($shippingLabelIndex >= 0) {
            $shippingRouteEnd = $shipping['index'] >= $shippingLabelIndex ? $shipping['index'] : $shippingLabelIndex + 6;
            $shippingRouteText = implode(' ', array_slice($lines, $shippingLabelIndex, ($shippingRouteEnd - $shippingLabelIndex) + 1));
        }
        $country = '';
        foreach (array($shippingRouteText, implode(' ', $addressParts), $pdfText) as $countrySearchText) {
            if ($country !== '') {
                break;
            }
            $normalizedCountrySearchText = normalizeImportLookup($countrySearchText);
            foreach ((array) $countryOptions as $countryName) {
                $countryName = trim((string) $countryName);
                if ($countryName !== '' && strlen($countryName) >= 3 && strpos($normalizedCountrySearchText, normalizeImportLookup($countryName)) !== false) {
                    $country = $countryName;
                    break;
                }
            }
        }

        $paymentMethod = '';
        $paymentIndex = websiteOrderImportFindLineIndex($lines, 'Customer payment', 0, false);
        if ($paymentIndex >= 0) {
            $totalPaidIndex = websiteOrderImportFindLineIndex($lines, 'Total paid', $paymentIndex + 1, false);
            $paymentEnd = $totalPaidIndex > $paymentIndex ? $totalPaidIndex : min(count($lines), $paymentIndex + 6);
            for ($index = $paymentIndex + 1; $index < $paymentEnd; $index++) {
                $candidate = normalizeImportText($lines[$index]);
                if ($candidate === '' || websiteOrderImportExtractAmount($candidate) !== '' || preg_match('/^(?:Total paid|MYR)$/i', $candidate)) {
                    continue;
                }
                $paymentMethod = $candidate;
                break;
            }
        }

        return array(
            'order_id' => $orderId,
            'package_name' => websiteOrderImportGetProductText($lines),
            'customer_name' => $customerName,
            'customer_email' => $email,
            'customer_contact' => $contact,
            'shipping_name' => $shippingName,
            'shipping_address' => implode("\n", $addressParts),
            'country_name' => $country,
            'currency_name' => 'MYR',
            'price' => $subtotal['amount'],
            'shipping_fee' => $shipping['amount'],
            'discount_price' => $discount['amount'] !== '' ? ltrim($discount['amount'], '-') : '0.00',
            'total' => $total['amount'],
            'payment_method_name' => $paymentMethod,
            'source_text' => $pdfText,
        );
    }
}
