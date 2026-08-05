<?php
$pageTitle = 'Lucky Draw - Prize Management';
$currentPagePin = 159;
$disablePinGroupPageTitleSync = true;

include_once '../include/connection.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/checkCurrentPagePin.php';
include_once ROOT . '/include/lucky_draw_admin_common.php';

if (!function_exists('luckyDrawAdminUserOptions')) {
    function luckyDrawAdminUserOptions($connect)
    {
        $rows = array();
        if (!($connect instanceof mysqli)) {
            return $rows;
        }

        $result = mysqli_query($connect, "SELECT id, name, username FROM `" . USR_USER . "` WHERE status = 'A' ORDER BY username ASC");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $label = trim((string) ($row['name'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($row['username'] ?? ''));
                }
                $rows[] = array(
                    'id' => isset($row['id']) ? (int) $row['id'] : 0,
                    'label' => $label,
                );
            }
        }

        return $rows;
    }
}

if (!function_exists('luckyDrawPrizeCrudParams')) {
    function luckyDrawPrizeCrudParams($act, $prizeId = 0)
    {
        $params = array('act' => strtoupper(trim((string) $act)));
        if ((int) $prizeId > 0) {
            $params['id'] = (int) $prizeId;
        }
        return $params;
    }
}

if (!function_exists('luckyDrawPrizeSanitizeHexColor')) {
    function luckyDrawPrizeSanitizeHexColor($value, $fallback = '#4a11c9')
    {
        $value = trim((string) $value);
        $fallback = trim((string) $fallback);

        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return '#' . strtolower(ltrim($value, '#'));
        }

        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $fallback)) {
            return '#' . strtolower(ltrim($fallback, '#'));
        }

        return '#4a11c9';
    }
}

if (!function_exists('luckyDrawPrizeColorExists')) {
    function luckyDrawPrizeColorExists($connect, $labelColor, $excludePrizeId = 0)
    {
        if (!($connect instanceof mysqli)) {
            return false;
        }

        $labelColor = luckyDrawPrizeSanitizeHexColor($labelColor);
        $excludePrizeId = (int) $excludePrizeId;
        $sql = "SELECT id FROM `" . LUCKY_DRAW_PRIZE . "`
            WHERE status = 'A'
              AND label_color = '" . mysqli_real_escape_string($connect, $labelColor) . "'";
        if ($excludePrizeId > 0) {
            $sql .= " AND id != " . $excludePrizeId;
        }
        $sql .= " LIMIT 1";
        $result = mysqli_query($connect, $sql);

        return $result instanceof mysqli_result && mysqli_num_rows($result) > 0;
    }
}

if (!function_exists('luckyDrawPrizeHexToHsl')) {
    function luckyDrawPrizeHexToHsl($hexColor)
    {
        $hexColor = ltrim(luckyDrawPrizeSanitizeHexColor($hexColor), '#');
        $red = hexdec(substr($hexColor, 0, 2)) / 255;
        $green = hexdec(substr($hexColor, 2, 2)) / 255;
        $blue = hexdec(substr($hexColor, 4, 2)) / 255;

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $lightness = ($max + $min) / 2;
        $hue = 0.0;
        $saturation = 0.0;

        if ($max !== $min) {
            $delta = $max - $min;
            $saturation = $lightness > 0.5 ? ($delta / (2 - $max - $min)) : ($delta / ($max + $min));

            if ($max === $red) {
                $hue = fmod((($green - $blue) / $delta), 6);
            } elseif ($max === $green) {
                $hue = (($blue - $red) / $delta) + 2;
            } else {
                $hue = (($red - $green) / $delta) + 4;
            }

            $hue *= 60;
            if ($hue < 0) {
                $hue += 360;
            }
        }

        return array($hue, $saturation, $lightness);
    }
}

if (!function_exists('luckyDrawPrizeHueToRgb')) {
    function luckyDrawPrizeHueToRgb($p, $q, $t)
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * ((2 / 3) - $t) * 6;
        }

        return $p;
    }
}

if (!function_exists('luckyDrawPrizeHslToHex')) {
    function luckyDrawPrizeHslToHex($hue, $saturation, $lightness)
    {
        $hue = fmod((float) $hue, 360.0);
        if ($hue < 0) {
            $hue += 360.0;
        }

        $saturation = max(0.0, min(1.0, (float) $saturation));
        $lightness = max(0.0, min(1.0, (float) $lightness));

        if ($saturation == 0.0) {
            $red = $green = $blue = $lightness;
        } else {
            $q = $lightness < 0.5
                ? ($lightness * (1 + $saturation))
                : ($lightness + $saturation - ($lightness * $saturation));
            $p = (2 * $lightness) - $q;
            $normalizedHue = $hue / 360.0;

            $red = luckyDrawPrizeHueToRgb($p, $q, $normalizedHue + (1 / 3));
            $green = luckyDrawPrizeHueToRgb($p, $q, $normalizedHue);
            $blue = luckyDrawPrizeHueToRgb($p, $q, $normalizedHue - (1 / 3));
        }

        return sprintf(
            '#%02x%02x%02x',
            (int) round($red * 255),
            (int) round($green * 255),
            (int) round($blue * 255)
        );
    }
}

if (!function_exists('luckyDrawPrizeGenerateNextUniqueColor')) {
    function luckyDrawPrizeGenerateNextUniqueColor($baseColor, $existingColors)
    {
        $normalizedExistingColors = array();
        foreach ((array) $existingColors as $existingColor) {
            $normalizedExistingColors[] = luckyDrawPrizeSanitizeHexColor((string) $existingColor);
        }
        $normalizedExistingColors = array_values(array_unique($normalizedExistingColors));

        $baseColor = luckyDrawPrizeSanitizeHexColor($baseColor);
        if (!in_array($baseColor, $normalizedExistingColors, true)) {
            return $baseColor;
        }

        list($baseHue, $baseSaturation, $baseLightness) = luckyDrawPrizeHexToHsl($baseColor);
        for ($step = 1; $step <= 24; $step++) {
            $candidateHue = fmod($baseHue + ($step * 29), 360);
            $candidateSaturation = max(0.58, min(0.84, $baseSaturation + (($step % 2 === 0) ? 0.05 : -0.03)));
            $candidateLightness = max(0.42, min(0.60, $baseLightness + (($step % 3 === 0) ? 0.04 : -0.02)));
            $candidateColor = luckyDrawPrizeSanitizeHexColor(
                luckyDrawPrizeHslToHex($candidateHue, $candidateSaturation, $candidateLightness)
            );

            if (!in_array($candidateColor, $normalizedExistingColors, true)) {
                return $candidateColor;
            }
        }

        for ($hue = 0; $hue < 360; $hue += 5) {
            $candidateColor = luckyDrawPrizeSanitizeHexColor(
                luckyDrawPrizeHslToHex($hue, 0.70, 0.50)
            );
            if (!in_array($candidateColor, $normalizedExistingColors, true)) {
                return $candidateColor;
            }
        }

        return luckyDrawPrizeSanitizeHexColor(
            luckyDrawPrizeHslToHex($baseHue + 180, max(0.60, $baseSaturation), min(0.58, max(0.44, $baseLightness)))
        );
    }
}

$pinAccess = luckyDrawAdminPinAccess($connect);
luckyDrawRequireAdminAction($connect, 'View', $pinAccess);

$requestedPrizeId = (int) numberInput('id');
if ($requestedPrizeId <= 0) {
    $requestedPrizeId = (int) numberInput('prize_id');
}
$mode = strtoupper(trim((string) input('act')));
if ($mode === 'A') {
    $mode = 'I';
}
if ($mode === 'VI') {
    if (!headers_sent()) {
        header('Location: ' . $SITEURL . '/import/lucky_draw_voucher_import.php', true, 302);
    }
    exit;
}
$resultDialogAct = strtoupper(trim((string) input('result_act')));
if (!in_array($resultDialogAct, array('I', 'E', 'NC'), true)) {
    $resultDialogAct = '';
}

$isAdd = $mode === 'I';
$isEdit = $mode === 'E';
$isView = !$isAdd && !$isEdit;

if (!$isAdd && $requestedPrizeId <= 0) {
    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE, array(), 'warning', 'Please select a valid Lucky Draw prize action.');
}

$editingPrize = array();
if ($requestedPrizeId > 0) {
    $editingPrizeResult = mysqli_query($connect, "SELECT * FROM `" . LUCKY_DRAW_PRIZE . "` WHERE id = " . $requestedPrizeId . " AND status = 'A' LIMIT 1");
    if ($editingPrizeResult && ($editingPrizeRow = mysqli_fetch_assoc($editingPrizeResult))) {
        $editingPrize = (array) $editingPrizeRow;
    }
}

if ($isAdd) {
    luckyDrawRequireAdminAction($connect, 'Add', $pinAccess);
} elseif ($isEdit) {
    luckyDrawRequireAdminAction($connect, 'Edit', $pinAccess);
    if (empty($editingPrize)) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE, array(), 'danger', 'Lucky Draw prize not found.');
    }
} else {
    luckyDrawRequireAdminAction($connect, 'View', $pinAccess);
    if (empty($editingPrize)) {
        luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE, array(), 'danger', 'Lucky Draw prize not found.');
    }
}

if ($isView && !empty($editingPrize)) {
    luckyDrawInsertAdminLog($connect, 'view_prize', LUCKY_DRAW_PRIZE, $requestedPrizeId, (string) ($editingPrize['prize_name'] ?? ''), USER_ID, array(
        'page_title' => $pageTitle,
        'audit_action' => 'view',
        'entity_label' => 'prize',
        'use_standard_crud_message' => true,
    ));
}

$formError = '';
$submittedFormValues = array();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('save_prize') !== '') {
    $prizeId = (int) post('prize_id');
    $isEditSave = $prizeId > 0;
    $saveMode = strtoupper(trim((string) (post('form_mode') !== '' ? post('form_mode') : ($isEditSave ? 'E' : 'I'))));
    luckyDrawRequireAdminAction($connect, $prizeId > 0 ? 'Edit' : 'Add', $pinAccess);

    $prizeName = luckyDrawSafePublicText(post('prize_name'), 190);
    $prizeType = strtolower(trim((string) (post('prize_type') !== '' ? post('prize_type') : 'voucher')));
    $prizeType = in_array($prizeType, array('voucher', 'physical'), true) ? $prizeType : 'voucher';
    $voucherCode = $prizeType === 'voucher'
        ? luckyDrawSafePublicText(post('voucher_code'), 255)
        : '';
    $rawWeightInput = trim((string) post('weight'));
    $weight = luckyDrawNormalizePositiveFloat(post('weight'), 0);
    $displayOrder = max(0, luckyDrawNormalizePositiveInt(post('display_order'), 0));
    $totalStock = max(0, luckyDrawNormalizePositiveInt(post('total_stock'), 0));
    $isEnabled = luckyDrawNormalizeFlag(post('is_enabled'), 'N');
    $rawPriceInput = trim((string) post('price'));
    $price = max(0, luckyDrawNormalizePositiveFloat(post('price'), 0));
    $labelColor = luckyDrawPrizeSanitizeHexColor(post('label_color') !== '' ? post('label_color') : '#4a11c9');
    $remark = luckyDrawSafePublicText(post('remark'), 1000);
    $postedPackageId = (int) post('package_id');
    $postedStockOutWarehouseId = (int) post('stock_out_warehouse_id');

    // Re-render the same form (with whatever the user typed preserved) instead of
    // redirecting through session-based flash on validation failure -- a redirect
    // depends on the session surviving the round trip, and if it doesn't the user
    // just sees a blank form with zero explanation of what went wrong.
    $submittedFormValues = array(
        'id' => $prizeId,
        'prize_name' => $prizeName,
        'prize_type' => $prizeType,
        'voucher_code' => $voucherCode,
        'weight' => $rawWeightInput !== '' ? $rawWeightInput : $weight,
        'display_order' => $displayOrder,
        'total_stock' => $totalStock,
        'price' => $rawPriceInput !== '' ? $rawPriceInput : $price,
        'is_enabled' => $isEnabled,
        'label_color' => $labelColor,
        'package_id' => $postedPackageId,
        'stock_out_warehouse_id' => $postedStockOutWarehouseId,
        'remark' => $remark,
    );

    if ($prizeName === '') {
        $formError = 'Prize name is required.';
    } elseif ($rawWeightInput === '' || !is_numeric($rawWeightInput) || (float) $rawWeightInput < 0) {
        $formError = 'Weight must be numeric and 0 or greater.';
    } elseif ($rawPriceInput === '') {
        $formError = 'Price is required.';
    } elseif ($prizeType === 'voucher' && $voucherCode === '') {
        $formError = 'Voucher code is required for voucher prizes.';
    } elseif ($prizeType === 'physical' && $postedPackageId <= 0) {
        $formError = 'Package is required for physical prizes.';
    } elseif (luckyDrawPrizeColorExists($connect, $labelColor, $prizeId)) {
        $formError = 'Prize label color cannot be duplicated.';
    }

    if ($formError !== '') {
        $mode = $saveMode;
        $isAdd = $mode === 'I';
        $isEdit = $mode === 'E';
        $isView = false;
        $editingPrize = array_merge($editingPrize, $submittedFormValues);
        goto renderPrizeForm;
    }

    $physicalFields = array(
        'package_id' => $prizeType === 'physical' ? $postedPackageId : 0,
        'country_id' => 0,
        'brand_id' => 0,
        'series_id' => 0,
        'fb_page_id' => 0,
        'channel_id' => 0,
        'pay_method_id' => 0,
        'sales_pic_user_id' => 0,
    );
    $stockOutWarehouseId = $prizeType === 'physical' ? $postedStockOutWarehouseId : 0;

    $prizeImagePath = '';
    $hasNewPrizeImage = false;
    if ($prizeType === 'physical' && isset($_FILES['prize_image']) && is_array($_FILES['prize_image']) && (int) $_FILES['prize_image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = luckyDrawStorePrizeImageUpload($_FILES['prize_image']);
        if (empty($uploadResult['success'])) {
            $formError = isset($uploadResult['message']) ? (string) $uploadResult['message'] : 'Unable to upload the prize image.';
            $mode = $saveMode;
            $isAdd = $mode === 'I';
            $isEdit = $mode === 'E';
            $isView = false;
            $editingPrize = array_merge($editingPrize, $submittedFormValues);
            goto renderPrizeForm;
        }
        $prizeImagePath = isset($uploadResult['path']) ? (string) $uploadResult['path'] : '';
        $hasNewPrizeImage = $prizeImagePath !== '';
    }

    $safePrizeName = mysqli_real_escape_string($connect, $prizeName);
    $safePrizeType = mysqli_real_escape_string($connect, $prizeType);
    $safeRemark = mysqli_real_escape_string($connect, $remark);
    $safeIsEnabled = mysqli_real_escape_string($connect, $isEnabled);
    $safeLabelColor = mysqli_real_escape_string($connect, $labelColor);
    $safeVoucherCode = mysqli_real_escape_string($connect, $voucherCode);
    $safeActor = mysqli_real_escape_string($connect, (string) USER_ID);

    if ($prizeId > 0) {
        $normalizedCurrentPrize = array(
            'prize_name' => trim((string) ($editingPrize['prize_name'] ?? '')),
            'prize_type' => strtolower(trim((string) ($editingPrize['prize_type'] ?? ''))),
            'voucher_code' => trim((string) ($editingPrize['voucher_code'] ?? '')),
            'weight' => number_format((float) ($editingPrize['weight'] ?? 0), 4, '.', ''),
            'total_stock' => (int) ($editingPrize['total_stock'] ?? 0),
            'display_order' => (int) ($editingPrize['display_order'] ?? 0),
            'is_enabled' => luckyDrawNormalizeFlag($editingPrize['is_enabled'] ?? 'N', 'N'),
            'label_color' => luckyDrawPrizeSanitizeHexColor((string) ($editingPrize['label_color'] ?? '#4a11c9')),
            'package_id' => (int) ($editingPrize['package_id'] ?? 0),
            'country_id' => (int) ($editingPrize['country_id'] ?? 0),
            'brand_id' => (int) ($editingPrize['brand_id'] ?? 0),
            'series_id' => (int) ($editingPrize['series_id'] ?? 0),
            'fb_page_id' => (int) ($editingPrize['fb_page_id'] ?? 0),
            'channel_id' => (int) ($editingPrize['channel_id'] ?? 0),
            'pay_method_id' => (int) ($editingPrize['pay_method_id'] ?? 0),
            'stock_out_warehouse_id' => (int) ($editingPrize['stock_out_warehouse_id'] ?? 0),
            'sales_pic_user_id' => (int) ($editingPrize['sales_pic_user_id'] ?? 0),
            'price' => number_format((float) ($editingPrize['price'] ?? 0), 2, '.', ''),
            'remark' => trim((string) ($editingPrize['remark'] ?? '')),
        );
        $normalizedSubmittedPrize = array(
            'prize_name' => $prizeName,
            'prize_type' => $prizeType,
            'voucher_code' => $voucherCode,
            'weight' => number_format($weight, 4, '.', ''),
            'total_stock' => $totalStock,
            'display_order' => $displayOrder,
            'is_enabled' => $isEnabled,
            'label_color' => $labelColor,
            'package_id' => (int) $physicalFields['package_id'],
            'country_id' => (int) $physicalFields['country_id'],
            'brand_id' => (int) $physicalFields['brand_id'],
            'series_id' => (int) $physicalFields['series_id'],
            'fb_page_id' => (int) $physicalFields['fb_page_id'],
            'channel_id' => (int) $physicalFields['channel_id'],
            'pay_method_id' => (int) $physicalFields['pay_method_id'],
            'stock_out_warehouse_id' => (int) $stockOutWarehouseId,
            'sales_pic_user_id' => (int) $physicalFields['sales_pic_user_id'],
            'price' => number_format($price, 2, '.', ''),
            'remark' => $remark,
        );

        if ($normalizedCurrentPrize === $normalizedSubmittedPrize && !$hasNewPrizeImage) {
            luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_PRIZES, array(
                'act' => 'E',
                'id' => $prizeId,
                'result_act' => 'NC',
            ));
        }

        $prizeImageSql = '';
        if ($prizeType === 'voucher') {
            $prizeImageSql = ", prize_image = NULL";
        } elseif ($prizeImagePath !== '') {
            $prizeImageSql = ", prize_image = '" . mysqli_real_escape_string($connect, $prizeImagePath) . "'";
        }

        $sql = "UPDATE `" . LUCKY_DRAW_PRIZE . "`
            SET prize_name = '" . $safePrizeName . "',
                prize_type = '" . $safePrizeType . "',
                voucher_code = " . ($voucherCode !== '' ? ("'" . $safeVoucherCode . "'") : 'NULL') . ",
                weight = " . number_format($weight, 4, '.', '') . ",
                total_stock = " . $totalStock . ",
                display_order = " . $displayOrder . ",
                is_enabled = '" . $safeIsEnabled . "',
                label_color = '" . $safeLabelColor . "',
                package_id = " . ($physicalFields['package_id'] > 0 ? $physicalFields['package_id'] : 'NULL') . ",
                country_id = " . ($physicalFields['country_id'] > 0 ? $physicalFields['country_id'] : 'NULL') . ",
                brand_id = " . ($physicalFields['brand_id'] > 0 ? $physicalFields['brand_id'] : 'NULL') . ",
                series_id = " . ($physicalFields['series_id'] > 0 ? $physicalFields['series_id'] : 'NULL') . ",
                fb_page_id = " . ($physicalFields['fb_page_id'] > 0 ? $physicalFields['fb_page_id'] : 'NULL') . ",
                channel_id = " . ($physicalFields['channel_id'] > 0 ? $physicalFields['channel_id'] : 'NULL') . ",
                pay_method_id = " . ($physicalFields['pay_method_id'] > 0 ? $physicalFields['pay_method_id'] : 'NULL') . ",
                stock_out_warehouse_id = " . ($stockOutWarehouseId > 0 ? $stockOutWarehouseId : 'NULL') . ",
                sales_pic_user_id = " . ($physicalFields['sales_pic_user_id'] > 0 ? $physicalFields['sales_pic_user_id'] : 'NULL') . ",
                price = " . number_format($price, 2, '.', '') . ",
                remark = '" . $safeRemark . "',
                update_by = '" . $safeActor . "',
                update_date = CURDATE(),
                update_time = CURTIME()" . $prizeImageSql . "
            WHERE id = " . $prizeId . "
              AND status = 'A'
            LIMIT 1";
        $updateResult = mysqli_query($connect, $sql);
        if (!$updateResult) {
            $formError = 'Unable to update this Lucky Draw prize. DB error: ' . mysqli_error($connect);
            $mode = $saveMode;
            $isAdd = false;
            $isEdit = true;
            $isView = false;
            $editingPrize = array_merge($editingPrize, $submittedFormValues);
            goto renderPrizeForm;
        }
    } else {
        $sql = "INSERT INTO `" . LUCKY_DRAW_PRIZE . "`
            (prize_name, prize_type, voucher_code, prize_image, weight, total_stock, reserved_stock, assigned_stock, display_order, is_enabled, label_color, package_id, country_id, brand_id, series_id, fb_page_id, channel_id, pay_method_id, stock_out_warehouse_id, sales_pic_user_id, price, remark, create_by, create_date, create_time, status)
            VALUES
            ('" . $safePrizeName . "', '" . $safePrizeType . "', " . ($voucherCode !== '' ? ("'" . $safeVoucherCode . "'") : 'NULL') . ", " . ($prizeType === 'physical' && $prizeImagePath !== '' ? ("'" . mysqli_real_escape_string($connect, $prizeImagePath) . "'") : 'NULL') . ", " . number_format($weight, 4, '.', '') . ", " . $totalStock . ", 0, 0, " . $displayOrder . ", '" . $safeIsEnabled . "', '" . $safeLabelColor . "', " . ($physicalFields['package_id'] > 0 ? $physicalFields['package_id'] : 'NULL') . ", " . ($physicalFields['country_id'] > 0 ? $physicalFields['country_id'] : 'NULL') . ", " . ($physicalFields['brand_id'] > 0 ? $physicalFields['brand_id'] : 'NULL') . ", " . ($physicalFields['series_id'] > 0 ? $physicalFields['series_id'] : 'NULL') . ", " . ($physicalFields['fb_page_id'] > 0 ? $physicalFields['fb_page_id'] : 'NULL') . ", " . ($physicalFields['channel_id'] > 0 ? $physicalFields['channel_id'] : 'NULL') . ", " . ($physicalFields['pay_method_id'] > 0 ? $physicalFields['pay_method_id'] : 'NULL') . ", " . ($stockOutWarehouseId > 0 ? $stockOutWarehouseId : 'NULL') . ", " . ($physicalFields['sales_pic_user_id'] > 0 ? $physicalFields['sales_pic_user_id'] : 'NULL') . ", " . number_format($price, 2, '.', '') . ", '" . $safeRemark . "', '" . $safeActor . "', CURDATE(), CURTIME(), 'A')";
        $insertResult = mysqli_query($connect, $sql);
        if (!$insertResult) {
            $formError = 'Unable to add this Lucky Draw prize. DB error: ' . mysqli_error($connect);
            $mode = $saveMode;
            $isAdd = true;
            $isEdit = false;
            $isView = false;
            $editingPrize = array_merge($editingPrize, $submittedFormValues);
            goto renderPrizeForm;
        }
        $prizeId = (int) mysqli_insert_id($connect);
        if ($prizeId > 0) {
            mysqli_query($connect, "UPDATE `" . LUCKY_DRAW_PRIZE . "`
                SET total_stock = " . $totalStock . "
                WHERE id = " . $prizeId . "
                  AND status = 'A'
                LIMIT 1");
        } else {
            $formError = 'Unable to add this Lucky Draw prize (no insert id returned).';
            $mode = $saveMode;
            $isAdd = true;
            $isEdit = false;
            $isView = false;
            $editingPrize = array_merge($editingPrize, $submittedFormValues);
            goto renderPrizeForm;
        }
    }

    luckyDrawInsertAdminLog($connect, 'save_prize', LUCKY_DRAW_PRIZE, $prizeId, $prizeName, USER_ID, array(
        'page_title' => $pageTitle,
        'audit_action' => $isEditSave ? 'edit' : 'add',
        'entity_label' => 'prize',
        'use_standard_crud_message' => true,
    ));
    luckyDrawAdminRedirect(ROUTE_LUCKY_DRAW_ADMIN_PRIZES, array(
        'act' => $isEditSave ? 'E' : 'I',
        'id' => $isEditSave ? $prizeId : null,
        'result_act' => $isEditSave ? 'E' : 'I',
    ));
}

renderPrizeForm:
$countryOptions = luckyDrawAdminOptionRows($connect, COUNTRIES, 'nicename');
$brandOptions = luckyDrawAdminOptionRows($connect, BRAND, 'name');
$seriesOptions = luckyDrawAdminOptionRows($connect, BRD_SERIES, 'name');
$packageOptions = luckyDrawAdminOptionRows($connect, PKG, 'name');
$warehouseOptions = luckyDrawAdminOptionRows($connect, WHSE, 'name');
$fbPageOptions = luckyDrawAdminOptionRows($finance_connect, FB_PAGE_ACC, 'name');
$channelOptions = luckyDrawAdminOptionRows($finance_connect, CHANEL_SC_MD, 'name');
$payMethodOptions = luckyDrawAdminOptionRows($finance_connect, FIN_PAY_METH, 'name');
$salesPicOptions = luckyDrawAdminUserOptions($connect);
$usedLabelColors = array();
$usedLabelColorResult = mysqli_query(
    $connect,
    "SELECT label_color FROM `" . LUCKY_DRAW_PRIZE . "`
     WHERE status = 'A'
       AND label_color IS NOT NULL
       AND label_color != ''
       " . ($requestedPrizeId > 0 ? ("AND id != " . (int) $requestedPrizeId) : '') . "
     ORDER BY id ASC"
);
if ($usedLabelColorResult instanceof mysqli_result) {
    while ($usedLabelColorRow = mysqli_fetch_assoc($usedLabelColorResult)) {
        $usedLabelColor = luckyDrawPrizeSanitizeHexColor((string) ($usedLabelColorRow['label_color'] ?? ''));
        if ($usedLabelColor !== '') {
            $usedLabelColors[] = $usedLabelColor;
        }
    }
}

$latestPrizeColor = '#4a11c9';
$latestPrizeColorResult = mysqli_query(
    $connect,
    "SELECT label_color
     FROM `" . LUCKY_DRAW_PRIZE . "`
     WHERE status = 'A'
       AND label_color IS NOT NULL
       AND label_color != ''
     ORDER BY
       COALESCE(update_date, create_date) DESC,
       COALESCE(update_time, create_time) DESC,
       id DESC
     LIMIT 1"
);
if ($latestPrizeColorResult instanceof mysqli_result && ($latestPrizeColorRow = mysqli_fetch_assoc($latestPrizeColorResult))) {
    $latestPrizeColor = luckyDrawPrizeSanitizeHexColor((string) ($latestPrizeColorRow['label_color'] ?? '#4a11c9'));
}

$defaultPrizeLabelColor = luckyDrawPrizeGenerateNextUniqueColor($latestPrizeColor, $usedLabelColors);
$defaultPrize = array(
    'id' => 0,
    'prize_name' => '',
    'prize_type' => 'voucher',
    'voucher_code' => '',
    'weight' => '1.0000',
    'display_order' => 0,
    'total_stock' => 0,
    'price' => '0.00',
    'is_enabled' => 'Y',
    'label_color' => $defaultPrizeLabelColor,
    'package_id' => 0,
    'country_id' => 0,
    'brand_id' => 0,
    'series_id' => 0,
    'fb_page_id' => 0,
    'channel_id' => 0,
    'pay_method_id' => 0,
    'stock_out_warehouse_id' => 0,
    'sales_pic_user_id' => 0,
    'remark' => '',
);
$editingPrize = !empty($editingPrize) ? array_merge($defaultPrize, $editingPrize) : $defaultPrize;

$pageHeading = $isAdd ? 'Add Prize' : ($isEdit ? 'Edit Prize' : 'View Prize');
$flash = luckyDrawAdminFlashGet();
$readonlyAttr = $isView ? 'readonly' : '';
$disabledAttr = $isView ? 'disabled' : '';
$currentPrizeImageUrl = luckyDrawPrizeImageUrl((string) ($editingPrize['prize_image'] ?? ''));
$editingPrize['label_color'] = luckyDrawPrizeSanitizeHexColor(
    isset($editingPrize['label_color']) ? $editingPrize['label_color'] : $defaultPrizeLabelColor,
    $defaultPrizeLabelColor
);

include_once '../menuHeader.php';
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
    <script>
        console.log('%c[prizes.php] DEBUG BUILD MARKER 2026-08-05-B -- if you do not see this exact string, the server is still running an OLD deployment.', 'background:#222;color:#0f0;font-weight:bold;padding:2px 6px;');
        console.log('[prizes.php] PHP-side state this page load -> formError:', <?= json_encode($formError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, ', mode:', <?= json_encode($mode, JSON_UNESCAPED_UNICODE) ?>, ', REQUEST_METHOD:', <?= json_encode($_SERVER['REQUEST_METHOD'] ?? '', JSON_UNESCAPED_UNICODE) ?>, ', full URL:', window.location.href);
    </script>
    <div style="background:#111;color:#0f0;font-family:monospace;font-size:12px;padding:6px 10px;">
        DEBUG BUILD 2026-08-05-B | REQUEST_METHOD=<?= htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? '', ENT_QUOTES, 'UTF-8') ?> | formError=<?= htmlspecialchars($formError !== '' ? $formError : '(empty)', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="page-load-cover">
        <div class="d-flex flex-column my-3 ms-3">
            <p>
                <a href="<?= htmlspecialchars(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE), ENT_QUOTES, 'UTF-8') ?>">Prize Management</a>
                <i class="fa-solid fa-chevron-right fa-xs"></i>
                <?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <div id="formContainer" class="container d-flex justify-content-center">
            <div class="col-12 col-md-10 formWidthAdjust">
                <?php if ($formError !== '') { ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php } elseif (!empty($flash['message'])) { ?>
                    <div class="alert alert-<?= htmlspecialchars((string) ($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php } ?>

                    <form method="post" enctype="multipart/form-data" id="luckyDrawPrizeForm" novalidate>
                        <input type="hidden" name="form_mode" value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="prize_id" value="<?= (int) ($editingPrize['id'] ?? 0) ?>">

                        <div class="form-group mb-4">
                            <h2><?= htmlspecialchars($pageHeading, ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>

                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label form_lbl" for="prize_name">Prize Name*</label>
                                    <input type="text" class="form-control" id="prize_name" name="prize_name" value="<?= htmlspecialchars((string) ($editingPrize['prize_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $readonlyAttr ?> required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label form_lbl" for="prize_type">Prize Type*</label>
                                    <select class="form-select" name="prize_type" id="prize_type" <?= $disabledAttr ?>>
                                        <option value="voucher" <?= strtolower((string) ($editingPrize['prize_type'] ?? 'voucher')) === 'voucher' ? 'selected' : '' ?>>Voucher</option>
                                        <option value="physical" <?= strtolower((string) ($editingPrize['prize_type'] ?? 'voucher')) === 'physical' ? 'selected' : '' ?>>Physical</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label form_lbl" for="weight">Weight*</label>
                                    <input type="number" class="form-control" id="weight" step="0.0001" min="0" name="weight" value="<?= htmlspecialchars((string) ($editingPrize['weight'] ?? '1.0000'), ENT_QUOTES, 'UTF-8') ?>" <?= $readonlyAttr ?> required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label form_lbl" for="label_color">Prize Label Color*</label><br>
                                    <div class="d-flex justify-content-start align-items-center gap-2">
                                        <input type="color" class="form-control" id="label_color" style="height:40px;" name="label_color" value="<?= htmlspecialchars((string) ($editingPrize['label_color'] ?? '#4a11c9'), ENT_QUOTES, 'UTF-8') ?>" <?= $disabledAttr ?> required>
                                    </div>
                                    <span id="labelColorError" class="text-danger d-block mt-1" style="display:none;"></span>
                                </div>
                                <div class="col-md-6 mb-3" id="voucherCodeField">
                                    <label class="form-label form_lbl" for="voucher_code">Voucher Code*</label>
                                    <input type="text" class="form-control" name="voucher_code" id="voucher_code" value="<?= htmlspecialchars((string) ($editingPrize['voucher_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $readonlyAttr ?>>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label form_lbl" for="display_order">Display Order</label>
                                    <input type="number" class="form-control" id="display_order" min="0" name="display_order" value="<?= (int) ($editingPrize['display_order'] ?? 0) ?>" <?= $readonlyAttr ?>>
                                </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form_lbl" for="total_stock">Total Stock</label>
                                        <input type="number" class="form-control" id="total_stock" min="0" name="total_stock" value="<?= (int) ($editingPrize['total_stock'] ?? 0) ?>" <?= $readonlyAttr ?>>
                                    </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label form_lbl" for="price">Price*</label>
                                    <input type="number" class="form-control" id="price" step="0.01" min="0" name="price" value="<?= htmlspecialchars((string) ($editingPrize['price'] ?? '0.00'), ENT_QUOTES, 'UTF-8') ?>" <?= $readonlyAttr ?> required>
                                </div>
                                <div class="col-md-6 mb-3" id="prizeImageField">
                                    <label class="form-label form_lbl" for="prize_image">Prize Image</label>
                                    <input type="file" class="form-control" id="prize_image" name="prize_image" accept=".png,.jpg,.jpeg,.webp,.gif" <?= $disabledAttr ?>>
                                    <?php if ($currentPrizeImageUrl !== '') { ?>
                                        <div class="mt-2">
                                            <img src="<?= htmlspecialchars($currentPrizeImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Prize Image" style="max-width:120px;max-height:120px;object-fit:cover;border:1px solid #dee2e6;">
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="Y" name="is_enabled" id="is_enabled" <?= luckyDrawNormalizeFlag($editingPrize['is_enabled'] ?? 'Y') === 'Y' ? 'checked' : '' ?> <?= $disabledAttr ?>>
                                <label class="form-check-label" for="is_enabled">Enable this prize in the birthday draw</label>
                            </div>
                        </div>

                        <div id="physicalPrizeFields" style="<?= strtolower((string) ($editingPrize['prize_type'] ?? 'voucher')) === 'physical' ? '' : 'display:none;' ?>">
                            <div class="form-group">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form_lbl" for="package_id">Package*</label>
                                        <select class="form-select" id="package_id" name="package_id" <?= $disabledAttr ?> <?= $isView ? '' : 'required' ?>>
                                            <option value="0">Select Package</option>
                                            <?php foreach ($packageOptions as $option) { ?>
                                                <option value="<?= (int) $option['id'] ?>" <?= (int) ($editingPrize['package_id'] ?? 0) === (int) $option['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $option['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label form_lbl" for="stock_out_warehouse_id">Stock Out Warehouse</label>
                                        <select class="form-select" id="stock_out_warehouse_id" name="stock_out_warehouse_id" <?= $disabledAttr ?>>
                                            <option value="0">Select Warehouse</option>
                                            <?php foreach ($warehouseOptions as $option) { ?>
                                                <option value="<?= (int) $option['id'] ?>" <?= (int) ($editingPrize['stock_out_warehouse_id'] ?? 0) === (int) $option['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $option['name'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label form_lbl" for="remark">Remark</label>
                            <textarea class="form-control" id="remark" name="remark" rows="4" <?= $readonlyAttr ?>><?= htmlspecialchars((string) ($editingPrize['remark'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <?php echo commonRenderCreateUpdateInfo($editingPrize, $connect, $mode); ?>

                        <div class="form-group mt-5 d-flex justify-content-center flex-md-row flex-column">
                            <?php if (!$isView) { ?>
                                <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 submitBtn" type="submit" name="save_prize" id="actionBtn"><?= $isAdd ? 'Add Prize' : 'Edit Prize' ?></button>
                            <?php } ?>
                            <button class="btn btn-lg btn-rounded btn-primary mx-2 mb-2 cancel" type="button" id="backBtn" onclick='location.href=<?= json_encode(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE)) ?>'>Back</button>
                        </div>
                    </form>
            </div>
        </div>
    </div>

<script>
    (function () {
        const prizeForm = document.getElementById('luckyDrawPrizeForm');
        const actionBtn = document.getElementById('actionBtn');
        const prizeType = document.getElementById('prize_type');
        const physicalFields = document.getElementById('physicalPrizeFields');
        const voucherCodeField = document.getElementById('voucherCodeField');
        const voucherCodeInput = document.getElementById('voucher_code');
        const prizeImageField = document.getElementById('prizeImageField');
        const labelColorInput = document.getElementById('label_color');
        const labelColorError = document.getElementById('labelColorError');
        const packageSelect = document.querySelector('select[name="package_id"]');
        const usedLabelColors = new Set(<?= json_encode(array_values(array_unique($usedLabelColors)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>.map((value) => String(value || '').toLowerCase()));
        console.log('[prizes.php] script loaded. prizeForm:', prizeForm, 'actionBtn:', actionBtn, 'prizeType el:', prizeType, 'physicalFields el:', physicalFields, 'usedLabelColors:', Array.from(usedLabelColors));
        if (!prizeType || !physicalFields) {
            console.log('[prizes.php] BAILING OUT EARLY: prizeType or physicalFields element missing from DOM. No submit handlers attached!');
            return;
        }

        if (prizeForm) {
            prizeForm.addEventListener('submit', () => {
                console.log('[prizes.php] form submit EVENT FIRED (this only logs if nothing called preventDefault before this listener ran).');
            }, { capture: true });
        }

        const clearLabelColorError = () => {
            if (!labelColorError) {
                return;
            }

            labelColorError.textContent = '';
            labelColorError.style.display = 'none';
        };

        const setLabelColorError = (message) => {
            if (!labelColorError) {
                return;
            }

            labelColorError.textContent = message;
            labelColorError.style.display = message ? 'block' : 'none';
        };

        const validateUniqueLabelColor = () => {
            if (!labelColorInput || labelColorInput.disabled) {
                clearLabelColorError();
                return true;
            }

            const normalizedColor = String(labelColorInput.value || '').trim().toLowerCase();
            console.log('[prizes.php] validateUniqueLabelColor: current color =', normalizedColor, ', usedLabelColors =', Array.from(usedLabelColors), ', isDuplicate =', usedLabelColors.has(normalizedColor));
            if (normalizedColor !== '' && usedLabelColors.has(normalizedColor)) {
                setLabelColorError('Prize Label Color cannot be duplicated.');
                return false;
            }

            clearLabelColorError();
            return true;
        };

        const sync = () => {
            const isPhysical = prizeType.value === 'physical';
            physicalFields.style.display = isPhysical ? '' : 'none';
            if (voucherCodeField) {
                voucherCodeField.style.display = isPhysical ? 'none' : '';
            }
            if (prizeImageField) {
                prizeImageField.style.display = isPhysical ? '' : 'none';
            }
            if (voucherCodeInput) {
                voucherCodeInput.required = !isPhysical;
            }
            if (packageSelect) {
                packageSelect.required = isPhysical;
            }
        };

        if (labelColorInput) {
            labelColorInput.addEventListener('input', validateUniqueLabelColor);
            labelColorInput.addEventListener('change', validateUniqueLabelColor);
        }

        const blockSubmitOnDuplicateColor = (event) => {
            console.log('[prizes.php] blockSubmitOnDuplicateColor triggered by event type:', event.type);
            if (!validateUniqueLabelColor()) {
                console.log('[prizes.php] BLOCKED here: duplicate label color. preventDefault() called.');
                event.preventDefault();
                if (typeof showNotification === 'function') {
                    showNotification('Prize Label Color cannot be duplicated. Please pick a different color.', 'error');
                }
                if (labelColorInput) {
                    labelColorInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    labelColorInput.focus();
                }
                return false;
            }
            console.log('[prizes.php] color check passed, not blocking here.');
            return true;
        };

        if (prizeForm) {
            prizeForm.addEventListener('submit', blockSubmitOnDuplicateColor);
        }

        if (actionBtn) {
            actionBtn.addEventListener('click', blockSubmitOnDuplicateColor);
        }

        prizeType.addEventListener('change', sync);
        sync();
        validateUniqueLabelColor();
    }());

    const page = <?= json_encode($pageTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const action = <?= json_encode($mode === '' ? ' ' : $mode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const resultDialogAct = <?= json_encode($resultDialogAct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const resultDialogReturnUrl = <?= json_encode(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_PRIZES_TABLE), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    checkCurrentPage(page, action);
    centerAlignment('formContainer');
    setButtonColor();
    preloader(300, action);
    if (resultDialogAct !== '' && typeof confirmationDialog === 'function') {
        window.setTimeout(() => {
            confirmationDialog("", "", page, "", resultDialogReturnUrl, resultDialogAct);
        }, 0);
    }
</script>
</body>
</html>
