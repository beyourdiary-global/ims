<?php
include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';
include_once ROOT . '/include/common_variable.php';

if (!function_exists('luckyDrawThemeSanitizeHex')) {
    function luckyDrawThemeSanitizeHex($value, $fallback = '#4a11c9')
    {
        $value = trim((string) $value);
        $fallback = trim((string) $fallback);

        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return '#' . ltrim($value, '#');
        }

        return preg_match('/^#?[0-9a-fA-F]{6}$/', $fallback) ? ('#' . ltrim($fallback, '#')) : '#4a11c9';
    }
}

if (!function_exists('luckyDrawThemeHexToRgb')) {
    function luckyDrawThemeHexToRgb($hexColor)
    {
        $hexColor = ltrim(luckyDrawThemeSanitizeHex($hexColor), '#');
        return array(
            hexdec(substr($hexColor, 0, 2)),
            hexdec(substr($hexColor, 2, 2)),
            hexdec(substr($hexColor, 4, 2)),
        );
    }
}

$projectRow = array();
$projectResult = getData('*', "id = '1'", '', PROJ, $connect);
if ($projectResult instanceof mysqli_result && $projectResult->num_rows > 0) {
    $projectRow = (array) $projectResult->fetch_assoc();
}

$themeColor = luckyDrawThemeSanitizeHex(isset($projectRow['themesColor']) ? $projectRow['themesColor'] : '#4a11c9');
$buttonColor = luckyDrawThemeSanitizeHex(isset($projectRow['buttonColor']) ? $projectRow['buttonColor'] : '#1b1b1b');
$themeRgb = luckyDrawThemeHexToRgb($themeColor);
$buttonRgb = luckyDrawThemeHexToRgb($buttonColor);
$websiteName = trim((string) (isset($projectRow['project_title']) ? $projectRow['project_title'] : 'BeYourDiary'));
$logoBaseUrl = rtrim((string) $SITEURL, '/') . '/' . trim((string) img_server, '/') . '/themes/';
$faviconUrl = !empty($projectRow['meta_logo']) ? ($logoBaseUrl . rawurlencode((string) $projectRow['meta_logo'])) : ($SITEURL . '/image/logo2.png');

$token = trim((string) input('token'));
$claimRow = $token !== '' ? luckyDrawFindClaimByToken($connect, $token) : array();
$csrfToken = luckyDrawGetCsrfToken('lucky_draw_claim_csrf');
$statusMessage = '';
$statusType = 'info';
$fieldErrors = array();
$formValues = array(
    'email' => '',
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $token = trim((string) post('token'));
    $formValues['email'] = trim((string) post('email'));
    if (!luckyDrawValidateCsrfToken((string) post('csrf_token'), 'lucky_draw_claim_csrf')) {
        $statusType = 'danger';
        $statusMessage = 'Your session expired. Please refresh the claim page and try again.';
    } else {
        $submitResult = luckyDrawSubmitClaim($connect, $finance_connect, $token, array(
            'email' => post('email'),
        ));
        $statusType = !empty($submitResult['success']) ? 'success' : 'danger';
        $statusMessage = isset($submitResult['message']) ? (string) $submitResult['message'] : 'Unable to submit the claim.';
        $fieldErrors = isset($submitResult['field_errors']) && is_array($submitResult['field_errors']) ? $submitResult['field_errors'] : array();
        if (!$submitResult['success'] && !empty($fieldErrors)) {
            $statusMessage = '';
        }
        $claimRow = $token !== '' ? luckyDrawFindClaimByToken($connect, $token) : array();
        if (!empty($submitResult['success'])) {
            $formValues['email'] = '';
        }
    }
}

$claimExpired = !empty($claimRow['reservation_expires_at']) && strtotime((string) $claimRow['reservation_expires_at']) < time();
$claimState = isset($claimRow['claim_state']) ? (string) $claimRow['claim_state'] : '';
$pageTitle = $websiteName . ' Lucky Draw Claim';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        :root {
            color-scheme: light;
            --ld-theme: <?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') ?>;
            --ld-theme-rgb: <?= (int) $themeRgb[0] ?>, <?= (int) $themeRgb[1] ?>, <?= (int) $themeRgb[2] ?>;
            --ld-button: <?= htmlspecialchars($buttonColor, ENT_QUOTES, 'UTF-8') ?>;
            --ld-button-rgb: <?= (int) $buttonRgb[0] ?>, <?= (int) $buttonRgb[1] ?>, <?= (int) $buttonRgb[2] ?>;
            --ld-bg: #f7f8fc;
            --ld-surface: rgba(255, 255, 255, 0.92);
            --ld-surface-strong: #ffffff;
            --ld-border: rgba(15, 23, 42, 0.08);
            --ld-text: #111827;
            --ld-text-soft: #4b5563;
            --ld-text-faint: #6b7280;
            --ld-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
            --ld-hero-shadow: 0 30px 70px rgba(var(--ld-theme-rgb), 0.14);
            --ld-success-bg: #e8f6ee;
            --ld-success-text: #166534;
            --ld-danger-bg: #fdecec;
            --ld-danger-text: #b91c1c;
            --ld-info-bg: #eef2ff;
            --ld-info-text: #3730a3;
            --ld-font: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --ld-bg: #0f1118;
                --ld-surface: rgba(24, 28, 39, 0.9);
                --ld-surface-strong: #181c27;
                --ld-border: rgba(255, 255, 255, 0.08);
                --ld-text: #f3f4f6;
                --ld-text-soft: #d1d5db;
                --ld-text-faint: #9ca3af;
                --ld-shadow: 0 26px 50px rgba(0, 0, 0, 0.34);
                --ld-hero-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
                --ld-success-bg: rgba(34, 197, 94, 0.18);
                --ld-success-text: #bbf7d0;
                --ld-danger-bg: rgba(248, 113, 113, 0.18);
                --ld-danger-text: #fecaca;
                --ld-info-bg: rgba(99, 102, 241, 0.18);
                --ld-info-text: #c7d2fe;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--ld-font);
            color: var(--ld-text);
            background-color: var(--ld-bg);
            background:
                radial-gradient(circle at top left, rgba(var(--ld-theme-rgb), 0.18), transparent 32%),
                radial-gradient(circle at right 12% top 18%, rgba(var(--ld-button-rgb), 0.16), transparent 24%),
                linear-gradient(180deg, var(--ld-bg) 0%, color-mix(in srgb, var(--ld-bg) 84%, #ffffff 16%) 100%);
        }

        img {
            display: block;
            max-width: 100%;
        }

        .claim-shell {
            width: min(960px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 40px;
        }

        .claim-card {
            position: relative;
            overflow: hidden;
            padding: 28px;
            border-radius: 30px;
            background:
                linear-gradient(180deg, color-mix(in srgb, var(--ld-surface) 94%, transparent), var(--ld-surface-strong)),
                linear-gradient(135deg, rgba(var(--ld-theme-rgb), 0.08), transparent 46%);
            border: 1px solid var(--ld-border);
            box-shadow: var(--ld-hero-shadow);
        }

        .claim-card::before,
        .claim-card::after {
            content: "";
            position: absolute;
            pointer-events: none;
            opacity: 0.72;
        }

        .claim-card::before {
            display: none;
        }

        .claim-card::after {
            inset: 8% -4% auto auto;
            width: 170px;
            height: 170px;
            background: radial-gradient(circle at center, rgba(var(--ld-button-rgb), 0.16), transparent 72%);
        }

        .claim-content {
            position: relative;
            z-index: 1;
        }

        .claim-kicker {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(var(--ld-theme-rgb), 0.08);
            color: var(--ld-theme);
            font-size: 0.85rem;
            font-weight: 700;
        }

        h1 {
            margin: 14px 0 10px;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .claim-subtitle {
            max-width: 680px;
            margin: 0 0 24px;
            color: var(--ld-text-soft);
            font-size: 1rem;
            line-height: 1.7;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid var(--ld-border);
            line-height: 1.6;
        }

        .alert-success {
            background: var(--ld-success-bg);
            color: var(--ld-success-text);
        }

        .alert-danger {
            background: var(--ld-danger-bg);
            color: var(--ld-danger-text);
        }

        .alert-info {
            background: var(--ld-info-bg);
            color: var(--ld-info-text);
        }

        .claim-panel,
        .claim-form-card {
            background: color-mix(in srgb, var(--ld-surface-strong) 92%, transparent);
            border: 1px solid var(--ld-border);
            border-radius: 24px;
            box-shadow: var(--ld-shadow);
        }

        .claim-panel {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 18px;
            align-items: center;
            padding: 20px;
            margin-bottom: 22px;
        }

        .claim-panel-thumb {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            overflow: hidden;
            background: rgba(var(--ld-theme-rgb), 0.08);
            border: 1px solid var(--ld-border);
            display: grid;
            place-items: center;
        }

        .claim-panel-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .claim-panel-thumb span {
            padding: 0 12px;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .claim-panel h2 {
            margin: 0 0 8px;
            font-size: 1.8rem;
        }

        .claim-meta {
            margin: 0;
            color: var(--ld-text-soft);
            line-height: 1.7;
        }

        .claim-meta strong {
            color: var(--ld-text);
        }

        .claim-form-card {
            padding: 24px;
        }

        .claim-form-card h3 {
            margin: 0 0 8px;
            font-size: 1.4rem;
        }

        .claim-form-card p {
            margin: 0 0 18px;
            color: var(--ld-text-soft);
            line-height: 1.65;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.94rem;
            font-weight: 700;
        }

        input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--ld-border);
            border-radius: 16px;
            background: color-mix(in srgb, var(--ld-surface-strong) 92%, transparent);
            color: var(--ld-text);
            font: inherit;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        input[type="email"]:focus {
            border-color: rgba(var(--ld-theme-rgb), 0.45);
            box-shadow: 0 0 0 4px rgba(var(--ld-theme-rgb), 0.12);
        }

        .claim-field {
            display: grid;
            gap: 8px;
        }

        .claim-field input.has-error {
            border-color: rgba(239, 68, 68, 0.72);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
        }

        .claim-field-error {
            color: var(--ld-danger-text);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .claim-btn {
            width: 100%;
            margin-top: 18px;
            border: none;
            border-radius: 999px;
            padding: 16px 18px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--ld-button) 88%, #ffffff 12%), var(--ld-button));
            color: #ffffff;
            font: inherit;
            font-size: 1.02rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 16px 28px rgba(var(--ld-button-rgb), 0.2);
        }

        .claim-btn:hover {
            transform: translateY(-1px);
        }

        .claim-footer {
            padding: 20px 4px 6px;
            color: var(--ld-text-faint);
            font-size: 0.92rem;
            text-align: center;
        }

        @media (max-width: 760px) {
            .claim-shell {
                width: calc(100% - 20px);
                padding-top: 18px;
            }

            .claim-card {
                padding: 18px;
                border-radius: 24px;
            }

            .claim-panel {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .claim-panel-thumb {
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="claim-shell">
        <div class="claim-card">
            <div class="claim-content">
                <div class="claim-kicker">Lucky Draw Claim</div>
                <h1>Complete Your Prize Claim</h1>
                <p class="claim-subtitle">Enter your email address to confirm this birthday reward claim. Voucher delivery updates and any follow-up communication will use this email.</p>

                <?php if ($statusMessage !== '') { ?>
                    <div class="alert alert-<?= htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></div>
                <?php } ?>

                <?php if (empty($claimRow)) { ?>
                    <div class="alert alert-info">This claim link is invalid or has already been closed.</div>
                <?php } else { ?>
                    <div class="claim-panel">
                        <div class="claim-panel-thumb">
                            <?php if (trim((string) ($claimRow['prize_image'] ?? '')) !== '') { ?>
                                <img src="<?= htmlspecialchars(luckyDrawPrizeImageUrl((string) $claimRow['prize_image']), ENT_QUOTES, 'UTF-8') ?>" alt="Prize">
                            <?php } else { ?>
                                <span><?= htmlspecialchars((string) ($claimRow['prize_name'] ?? 'Prize'), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php } ?>
                        </div>
                        <div>
                            <h2><?= htmlspecialchars((string) ($claimRow['prize_name'] ?? 'Lucky Draw Prize'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <p class="claim-meta"><strong>Reference:</strong> <?= htmlspecialchars((string) ($claimRow['redeem_reference'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php if (!empty($claimRow['reservation_expires_at'])) { ?>
                                <p class="claim-meta"><strong>Claim expires at:</strong> <?= htmlspecialchars((string) $claimRow['reservation_expires_at'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php } ?>
                        </div>
                    </div>

                    <?php if ($claimExpired) { ?>
                        <div class="alert alert-info">This claim link has expired. If the campaign still allows it, please contact the team for assistance.</div>
                    <?php } else if ($claimState !== 'awaiting_claim') { ?>
                        <div class="alert alert-info">This claim has already been submitted.</div>
                    <?php } else { ?>
                        <div class="claim-form-card">
                            <h3>Claim Submission</h3>
                            <p>Use one valid email only. This email will be used for voucher delivery and Lucky Draw claim follow-up.</p>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="claim-field">
                                    <label for="email">Email</label>
                                    <input
                                        type="email"
                                        name="email"
                                        id="email"
                                        autocomplete="email"
                                        value="<?= htmlspecialchars((string) $formValues['email'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="<?= !empty($fieldErrors['email']) ? 'has-error' : '' ?>"
                                        required>
                                    <?php if (!empty($fieldErrors['email'])) { ?>
                                        <div class="claim-field-error"><?= htmlspecialchars((string) $fieldErrors['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php } ?>
                                </div>
                                <button class="claim-btn" type="submit">Submit Claim</button>
                            </form>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>

        <div class="claim-footer"><?= htmlspecialchars($websiteName, ENT_QUOTES, 'UTF-8') ?> Lucky Draw</div>
    </div>
</body>
</html>
