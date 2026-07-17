<?php

if (!function_exists('fbAdsWhtSubmissionNormalizeIds')) {
    function fbAdsWhtSubmissionNormalizeIds($rawIds)
    {
        if (is_array($rawIds)) {
            $rawIds = implode(',', $rawIds);
        }

        $ids = array();
        foreach (explode(',', (string) $rawIds) as $rawId) {
            $id = (int) trim((string) $rawId);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}

if (!function_exists('fbAdsWhtSubmissionIdSql')) {
    function fbAdsWhtSubmissionIdSql($ids)
    {
        $normalizedIds = fbAdsWhtSubmissionNormalizeIds($ids);
        return empty($normalizedIds) ? '' : implode(',', $normalizedIds);
    }
}

if (!function_exists('fbAdsWhtSubmissionCalculate')) {
    function fbAdsWhtSubmissionCalculate($amount)
    {
        $amount = round((float) $amount, 2);
        $subtotal = round($amount / 1.08, 2);
        $sst = round($amount - $subtotal, 2);

        return array(
            'amount' => $amount,
            'subtotal' => $subtotal,
            'sst' => $sst,
        );
    }
}

if (!function_exists('fbAdsWhtSubmissionGetSourceRows')) {
    function fbAdsWhtSubmissionGetSourceRows($financeConnect, $ids, $forUpdate = false)
    {
        $idSql = fbAdsWhtSubmissionIdSql($ids);
        if ($idSql === '' || !($financeConnect instanceof mysqli)) {
            return array();
        }

        $query = "SELECT `id`, `payment_date`, `transactionID`, `topup_amt`
            FROM `" . FB_ADS_TOPUP . "`
            WHERE `status` = 'A' AND `id` IN (" . $idSql . ")
            ORDER BY FIELD(`id`, " . $idSql . ")";
        if ($forUpdate) {
            $query .= ' FOR UPDATE';
        }

        $result = mysqli_query($financeConnect, $query);
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $calculation = fbAdsWhtSubmissionCalculate(isset($row['topup_amt']) ? $row['topup_amt'] : 0);
                $row['amount'] = $calculation['amount'];
                $row['subtotal'] = $calculation['subtotal'];
                $row['sst'] = $calculation['sst'];
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('fbAdsWhtSubmissionGetDuplicateSourceIds')) {
    function fbAdsWhtSubmissionGetDuplicateSourceIds($financeConnect, $ids, $excludeSubmissionRef = '')
    {
        $idSql = fbAdsWhtSubmissionIdSql($ids);
        if ($idSql === '' || !($financeConnect instanceof mysqli)) {
            return array();
        }

        $excludeSql = '';
        if (trim((string) $excludeSubmissionRef) !== '') {
            $safeRef = mysqli_real_escape_string($financeConnect, trim((string) $excludeSubmissionRef));
            $excludeSql = " AND `submission_ref` <> '" . $safeRef . "'";
        }

        $query = "SELECT DISTINCT `source_transaction_id`
            FROM `" . FB_ADS_WHT_SUBMISSION . "`
            WHERE `status` = 'A'
              AND `source_transaction_id` IN (" . $idSql . ")" . $excludeSql;
        $result = mysqli_query($financeConnect, $query);
        $duplicateIds = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $duplicateIds[] = (int) $row['source_transaction_id'];
            }
        }

        return $duplicateIds;
    }
}

if (!function_exists('fbAdsWhtSubmissionGetDuplicateDetails')) {
    function fbAdsWhtSubmissionGetDuplicateDetails($financeConnect, $ids, $excludeSubmissionRef = '')
    {
        $idSql = fbAdsWhtSubmissionIdSql($ids);
        if ($idSql === '' || !($financeConnect instanceof mysqli)) {
            return array();
        }

        $excludeSql = '';
        if (trim((string) $excludeSubmissionRef) !== '') {
            $safeRef = mysqli_real_escape_string($financeConnect, trim((string) $excludeSubmissionRef));
            $excludeSql = " AND `submission_ref` <> '" . $safeRef . "'";
        }

        $query = "SELECT `source_transaction_id`, `transaction_id`, `submission_ref`
            FROM `" . FB_ADS_WHT_SUBMISSION . "`
            WHERE `status` = 'A'
              AND `source_transaction_id` IN (" . $idSql . ")" . $excludeSql . "
            ORDER BY `id` ASC";
        $result = mysqli_query($financeConnect, $query);
        $duplicateDetails = array();
        $seenSourceIds = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sourceTransactionId = (int) ($row['source_transaction_id'] ?? 0);
                if ($sourceTransactionId <= 0 || isset($seenSourceIds[$sourceTransactionId])) {
                    continue;
                }

                $seenSourceIds[$sourceTransactionId] = true;
                $duplicateDetails[] = array(
                    'source_transaction_id' => $sourceTransactionId,
                    'transaction_id' => (string) ($row['transaction_id'] ?? ''),
                    'submission_ref' => (string) ($row['submission_ref'] ?? ''),
                );
            }
        }

        return $duplicateDetails;
    }
}

if (!function_exists('fbAdsWhtSubmissionFormatDuplicateMessages')) {
    function fbAdsWhtSubmissionFormatDuplicateMessages($duplicateDetails)
    {
        $messages = array();
        foreach ((array) $duplicateDetails as $duplicateDetail) {
            $transactionId = trim((string) ($duplicateDetail['transaction_id'] ?? ''));
            if ($transactionId === '') {
                $transactionId = 'source transaction ' . (int) ($duplicateDetail['source_transaction_id'] ?? 0);
            }

            $submissionRef = trim((string) ($duplicateDetail['submission_ref'] ?? ''));
            $messages[] = 'Transaction ' . $transactionId . ' already submitted, Submission Reference: ' . $submissionRef;
        }

        return $messages;
    }
}

if (!function_exists('fbAdsWhtSubmissionFormatDuplicateError')) {
    function fbAdsWhtSubmissionFormatDuplicateError($duplicateDetails)
    {
        return implode('; ', fbAdsWhtSubmissionFormatDuplicateMessages($duplicateDetails));
    }
}

if (!function_exists('fbAdsWhtSubmissionGenerateRef')) {
    function fbAdsWhtSubmissionGenerateRef()
    {
        return 'FBWHT-' . date('YmdHis') . '-' . mt_rand(100000, 999999);
    }
}

if (!function_exists('fbAdsWhtSubmissionStoreAttachment')) {
    function fbAdsWhtSubmissionStoreAttachment($fileInfo, &$errorMessage)
    {
        $errorMessage = '';
        if (!is_array($fileInfo) || !isset($fileInfo['error']) || (int) $fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ((int) $fileInfo['error'] !== UPLOAD_ERR_OK || empty($fileInfo['tmp_name']) || empty($fileInfo['name'])) {
            $errorMessage = 'Failed to upload the file.';
            return '';
        }

        $originalName = basename((string) $fileInfo['name']);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = array('png', 'jpg', 'jpeg', 'svg', 'pdf');
        if (!in_array($extension, $allowedExtensions, true)) {
            $errorMessage = 'Only PNG, JPG, JPEG, SVG or PDF file is allowed.';
            return '';
        }

        $baseName = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) pathinfo($originalName, PATHINFO_FILENAME));
        if ($baseName === '') {
            $baseName = 'fb_ads_wht_submission_attachment';
        }

        $dateValue = (string) comYMD;
        $relativeDirectory = 'attachment/' . substr($dateValue, 0, 4) . '/' . substr($dateValue, 4, 2) . '/fb_ads_topup_transaction_wth_submission/' . $dateValue . '/';
        $absoluteDirectory = rtrim((string) ROOT, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !@mkdir($absoluteDirectory, 0777, true)) {
            $errorMessage = 'Failed to create attachment directory.';
            return '';
        }

        $newFileName = $baseName . '_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $extension;
        $absolutePath = $absoluteDirectory . $newFileName;
        if (!@move_uploaded_file((string) $fileInfo['tmp_name'], $absolutePath)) {
            $errorMessage = 'Failed to upload the file.';
            return '';
        }

        return $relativeDirectory . $newFileName;
    }
}

if (!function_exists('fbAdsWhtSubmissionResolveBatchRef')) {
    function fbAdsWhtSubmissionResolveBatchRef($financeConnect, $id)
    {
        $id = (int) $id;
        if ($id <= 0 || !($financeConnect instanceof mysqli)) {
            return '';
        }

        $result = mysqli_query($financeConnect, "SELECT `submission_ref` FROM `" . FB_ADS_WHT_SUBMISSION . "` WHERE `id` = " . $id . " AND `status` = 'A' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = mysqli_fetch_assoc($result);
            return isset($row['submission_ref']) ? (string) $row['submission_ref'] : '';
        }

        return '';
    }
}

if (!function_exists('fbAdsWhtSubmissionGetBatchRows')) {
    function fbAdsWhtSubmissionGetBatchRows($financeConnect, $submissionRef, $includeDeleted = false)
    {
        if (!($financeConnect instanceof mysqli) || trim((string) $submissionRef) === '') {
            return array();
        }

        $safeRef = mysqli_real_escape_string($financeConnect, trim((string) $submissionRef));
        $statusSql = $includeDeleted ? '' : " AND `status` = 'A'";
        $result = mysqli_query($financeConnect, "SELECT * FROM `" . FB_ADS_WHT_SUBMISSION . "` WHERE `submission_ref` = '" . $safeRef . "'" . $statusSql . " ORDER BY `id` ASC");
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
