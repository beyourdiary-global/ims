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

if (!function_exists('luckyDrawEscapeSvgText')) {
    function luckyDrawEscapeSvgText($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('luckyDrawVoucherTextLines')) {
    function luckyDrawVoucherTextLines($value, $maxCharsPerLine = 16, $maxLines = 3)
    {
        $words = preg_split('/\s+/', trim((string) $value));
        $words = is_array($words) ? $words : array();
        $lines = array();
        $currentLine = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $testLine = $currentLine === '' ? $word : ($currentLine . ' ' . $word);
            if (strlen($testLine) > $maxCharsPerLine && $currentLine !== '') {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        if (empty($lines)) {
            $lines[] = 'Voucher Prize';
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lastIndex = count($lines) - 1;
            if ($lastIndex >= 0) {
                $lines[$lastIndex] = rtrim($lines[$lastIndex], '.') . '...';
            }
        }

        return $lines;
    }
}

if (!function_exists('luckyDrawVoucherDataUrl')) {
    function luckyDrawVoucherDataUrl($prizeName)
    {
        $prizeName = trim((string) $prizeName);
        if ($prizeName === '') {
            $prizeName = 'Voucher Prize';
        }

        $lines = luckyDrawVoucherTextLines($prizeName, 16, 3);
        $startY = 220 - ((count($lines) - 1) * 18);
        $textNodes = '';

        foreach ($lines as $index => $line) {
            $textNodes .= '<text x="210" y="' . (int) ($startY + ($index * 36)) . '" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="26" font-weight="800" fill="#1f2937">' . luckyDrawEscapeSvgText($line) . '</text>';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420" viewBox="0 0 420 420">'
            . '<defs>'
            . '<linearGradient id="voucherGold" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#fff4d6"/>'
            . '<stop offset="55%" stop-color="#f7d37e"/>'
            . '<stop offset="100%" stop-color="#d89a33"/>'
            . '</linearGradient>'
            . '<linearGradient id="voucherPanel" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#ffffff"/>'
            . '<stop offset="100%" stop-color="#f8fafc"/>'
            . '</linearGradient>'
            . '<filter id="voucherShadow" x="-20%" y="-20%" width="140%" height="140%">'
            . '<feDropShadow dx="0" dy="12" stdDeviation="10" flood-color="#000000" flood-opacity="0.20"/>'
            . '</filter>'
            . '</defs>'
            . '<rect width="420" height="420" fill="transparent"/>'
            . '<g filter="url(#voucherShadow)">'
            . '<path d="M122 82 H298 Q332 82 332 116 V154 Q304 170 332 186 V304 Q332 338 298 338 H122 Q88 338 88 304 V186 Q116 170 88 154 V116 Q88 82 122 82 Z" fill="url(#voucherGold)" stroke="#9a6a14" stroke-width="6" stroke-linejoin="round"/>'
            . '<path d="M132 112 H288 Q310 112 310 134 V286 Q310 308 288 308 H132 Q110 308 110 286 V134 Q110 112 132 112 Z" fill="url(#voucherPanel)"/>'
            . '<path d="M210 120 V300" stroke="rgba(154,106,20,0.20)" stroke-width="3" stroke-dasharray="8 10"/>'
            . '<rect x="144" y="132" width="132" height="34" rx="17" fill="#1f2937"/>'
            . '<text x="210" y="154" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="15" font-weight="800" letter-spacing="2" fill="#ffffff">VOUCHER</text>'
            . $textNodes
            . '<rect x="148" y="266" width="124" height="24" rx="12" fill="rgba(216,154,51,0.18)"/>'
            . '<text x="210" y="281" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="14" font-weight="700" fill="#9a6a14">Birthday Reward</text>'
            . '</g>'
            . '</svg>';

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }
}

if (!function_exists('luckyDrawGiftIconDataUrl')) {
    function luckyDrawGiftIconDataUrl($prizeName = 'Gift Prize')
    {
        $prizeName = trim((string) $prizeName);
        if ($prizeName === '') {
            $prizeName = 'Gift Prize';
        }

        $lines = luckyDrawVoucherTextLines($prizeName, 14, 2);
        $startY = 310 - ((count($lines) - 1) * 16);
        $textNodes = '';

        foreach ($lines as $index => $line) {
            $textNodes .= '<text x="210" y="' . (int) ($startY + ($index * 32)) . '" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="800" fill="#6b3f0d">' . luckyDrawEscapeSvgText($line) . '</text>';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420" viewBox="0 0 420 420">'
            . '<defs>'
            . '<linearGradient id="giftBoxBody" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#fff7e8"/>'
            . '<stop offset="100%" stop-color="#f6d796"/>'
            . '</linearGradient>'
            . '<linearGradient id="giftRibbon" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#f0b34b"/>'
            . '<stop offset="100%" stop-color="#c57b18"/>'
            . '</linearGradient>'
            . '<filter id="giftShadow" x="-20%" y="-20%" width="140%" height="140%">'
            . '<feDropShadow dx="0" dy="12" stdDeviation="10" flood-color="#000000" flood-opacity="0.18"/>'
            . '</filter>'
            . '</defs>'
            . '<rect width="420" height="420" fill="transparent"/>'
            . '<g filter="url(#giftShadow)">'
            . '<path d="M146 126 C120 92 138 56 174 60 C195 62 207 84 210 96 C213 84 225 62 246 60 C282 56 300 92 274 126" fill="url(#giftRibbon)"/>'
            . '<rect x="88" y="136" width="244" height="66" rx="18" fill="url(#giftBoxBody)" stroke="#c58b2d" stroke-width="6"/>'
            . '<rect x="76" y="196" width="268" height="156" rx="24" fill="url(#giftBoxBody)" stroke="#c58b2d" stroke-width="6"/>'
            . '<rect x="192" y="136" width="36" height="216" rx="12" fill="url(#giftRibbon)"/>'
            . '<rect x="76" y="238" width="268" height="30" rx="14" fill="url(#giftRibbon)"/>'
            . '<circle cx="176" cy="112" r="26" fill="none" stroke="url(#giftRibbon)" stroke-width="12"/>'
            . '<circle cx="244" cy="112" r="26" fill="none" stroke="url(#giftRibbon)" stroke-width="12"/>'
            . '<rect x="114" y="282" width="192" height="56" rx="20" fill="rgba(255,255,255,0.74)"/>'
            . $textNodes
            . '</g>'
            . '</svg>';

        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
    }
}

$requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
$requestPath = trim((string) parse_url($requestUri, PHP_URL_PATH));
if ($requestPath !== '' && preg_match('#/lucky_draw/index\.php$#i', $requestPath)) {
    $redirectUrl = siteUrlPath(ROUTE_LUCKY_DRAW_HOME);
    $queryString = (string) parse_url($requestUri, PHP_URL_QUERY);
    if ($queryString !== '') {
        $redirectUrl .= '?' . $queryString;
    }

    if (!headers_sent()) {
        header('Location: ' . $redirectUrl, true, 302);
    }
    exit;
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
$logoUrl = !empty($projectRow['logo']) ? ($logoBaseUrl . rawurlencode((string) $projectRow['logo'])) : ($SITEURL . '/image/logo2.png');
$faviconUrl = !empty($projectRow['meta_logo']) ? ($logoBaseUrl . rawurlencode((string) $projectRow['meta_logo'])) : $logoUrl;
$pageTitle = $websiteName . ' Lucky Draw';

$readiness = luckyDrawReadiness($connect, $finance_connect);
$prizeRows = luckyDrawFetchPrizeRows($connect, true);
$voucherAvailableCounts = luckyDrawVoucherAvailableCounts($connect);
$voucherStateCounts = luckyDrawVoucherStateCounts($connect);
$csrfToken = luckyDrawGetCsrfToken();
$recaptchaSiteKey = luckyDrawGetRecaptchaSiteKey();
$participationState = luckyDrawGetParticipationSessionState($connect);
$hasParticipated = !empty($participationState['participated']);
$wheelNoteText = $hasParticipated ? 'You already participated the lucky draw.' : 'You have 1 verified birthday-month chance';

$heroTitle = 'Lucky Draw';
$heroSubtitle = 'Spin once during your birthday month to unlock a verified reward. Enter your full IC number, pass reCAPTCHA, and complete the claim flow if you win.';

$wheelPrizes = array_values(array_filter($prizeRows, function ($row) {
    return (float) ($row['weight'] ?? 0) > 0;
}));

$featuredPrizes = array_slice($wheelPrizes, 0, 4);
$segmentCount = count($wheelPrizes);
$wheelColors = array(
    $themeColor,
    '#f5c56f',
    '#ffffff',
    '#f1debe',
    '#e7b45c',
    '#d8c5a5',
);
$gradientParts = array();
if ($segmentCount > 0) {
    $segmentSize = 360 / $segmentCount;
    for ($i = 0; $i < $segmentCount; $i++) {
        $start = number_format($i * $segmentSize, 3, '.', '');
        $end = number_format(($i + 1) * $segmentSize, 3, '.', '');
        $gradientParts[] = $wheelColors[$i % count($wheelColors)] . ' ' . $start . 'deg ' . $end . 'deg';
    }
}
$wheelGradient = !empty($gradientParts) ? ('conic-gradient(' . implode(', ', $gradientParts) . ')') : ('conic-gradient(' . $themeColor . ' 0deg 360deg)');

$publicPrizeRows = array();
foreach ($wheelPrizes as $row) {
    $prizeId = isset($row['id']) ? (int) $row['id'] : 0;
    $voucherReservedCount = (int) ($voucherStateCounts[$prizeId]['reserved'] ?? 0);
    $voucherAssignedCount = (int) (($voucherStateCounts[$prizeId]['assigned'] ?? 0) + ($voucherStateCounts[$prizeId]['sent'] ?? 0));
    $availableCount = luckyDrawPrizeAvailableUnits(
        $row,
        (int) ($voucherAvailableCounts[$prizeId] ?? 0),
        $voucherReservedCount,
        $voucherAssignedCount
    );
    $fallbackColor = $wheelColors[count($publicPrizeRows) % count($wheelColors)];
    $publicPrizeRows[] = array(
        'id' => $prizeId,
        'name' => isset($row['prize_name']) ? (string) $row['prize_name'] : 'Prize',
        'type' => isset($row['prize_type']) ? strtoupper((string) $row['prize_type']) : '',
        'image' => strtoupper((string) ($row['prize_type'] ?? '')) === 'VOUCHER'
            ? ''
            : (
                luckyDrawPrizeImageUrl(isset($row['prize_image']) ? (string) $row['prize_image'] : '') !== ''
                    ? luckyDrawPrizeImageUrl(isset($row['prize_image']) ? (string) $row['prize_image'] : '')
                    : luckyDrawGiftIconDataUrl((string) ($row['prize_name'] ?? 'Prize'))
            ),
        'color' => luckyDrawThemeSanitizeHex(isset($row['label_color']) ? (string) $row['label_color'] : $fallbackColor, $fallbackColor),
        'available' => $availableCount,
        'weight' => max(1, (float) ($row['weight'] ?? 1)),
    );
}

$howItWorks = array(
    array(
        'title' => 'Enter Full IC',
        'description' => 'Fill in your full IC number and complete reCAPTCHA to verify the request.',
    ),
    array(
        'title' => 'Spin the Wheel',
        'description' => 'The server calculates the result with protected weighted draw logic.',
    ),
    array(
        'title' => 'See the Result',
        'description' => 'If you win, your prize is reserved immediately and the result is shown on screen.',
    ),
    array(
        'title' => 'Complete Claim',
        'description' => 'Submit the claim form so vouchers or fulfilment details can be processed correctly.',
    ),
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="theme-color" content="<?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($recaptchaSiteKey !== '') { ?>
        <script>
            window.luckyDrawRecaptchaReady = function () {
                if (typeof window.renderLuckyDrawRecaptcha === 'function') {
                    window.renderLuckyDrawRecaptcha(true);
                }
            };
        </script>
        <script src="https://www.google.com/recaptcha/api.js?onload=luckyDrawRecaptchaReady&render=explicit" async defer></script>
    <?php } ?>
    <script src="<?= htmlspecialchars($SITEURL . '/header/js/spin-wheel-iife.js', ENT_QUOTES, 'UTF-8') ?>"></script>
    <link rel="stylesheet" href="<?= htmlspecialchars($SITEURL . '/css/lucky_draw.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body style="--ld-theme: <?= htmlspecialchars($themeColor, ENT_QUOTES, 'UTF-8') ?>; --ld-theme-rgb: <?= (int) $themeRgb[0] ?>, <?= (int) $themeRgb[1] ?>, <?= (int) $themeRgb[2] ?>; --ld-button: <?= htmlspecialchars($buttonColor, ENT_QUOTES, 'UTF-8') ?>; --ld-button-rgb: <?= (int) $buttonRgb[0] ?>, <?= (int) $buttonRgb[1] ?>, <?= (int) $buttonRgb[2] ?>; --ld-wheel-gradient: <?= htmlspecialchars($wheelGradient, ENT_QUOTES, 'UTF-8') ?>;">
    <div class="ld-shell">
        <section class="ld-hero">
            <div class="ld-confetti" aria-hidden="true">
                <span style="left:8%; top:12%;"></span>
                <span style="left:24%; top:22%;"></span>
                <span style="left:39%; top:9%;"></span>
                <span style="left:61%; top:18%;"></span>
                <span style="left:81%; top:12%;"></span>
                <span style="left:92%; top:28%;"></span>
            </div>

            <div class="ld-hero-grid">
                <div class="ld-hero-copy">
                    <div class="ld-kicker">Exclusive Birthday Rewards Await You</div>
                    <h1><?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p><?= htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                </div>

                <div class="ld-wheel-column">
                    <div class="ld-wheel-wrap">
                        <div class="ld-wheel-pointer"></div>
                        <div class="ld-spin-wheel-canvas" id="luckyWheel"></div>
                        <div class="ld-wheel-center" aria-hidden="true">
                            <div>Spin</div>
                            <div>NOW</div>
                        </div>
                    </div>
                    <div class="ld-wheel-note"><?= htmlspecialchars($wheelNoteText, ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="ld-join-column">
                    <div class="ld-join-card">
                        <h2>Join the Lucky Draw</h2>
                        <?php if (empty($readiness['success'])) { ?>
                            <p>The birthday-month draw is still being prepared. Please come back after the Lucky Draw setup passes all readiness checks.</p>
                            <div class="ld-empty-state">Lucky Draw is temporarily unavailable.</div>
                        <?php } else if ($hasParticipated) { ?>
                            <p>You already participated the lucky draw. If your claim is still pending, continue below to complete it.</p>
                            <div class="ld-empty-state">You already participated the lucky draw.</div>
                        <?php } else { ?>
                            <p>Fill in your full IC number and complete the verification to spin the wheel.</p>
                            <form class="ld-form" id="luckyDrawForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <div>
                                    <label for="member_identity">IC Number</label>
                                    <input type="text" id="member_identity" name="member_identity" placeholder="e.g. 901234145678" required>
                                </div>

                                <?php if ($recaptchaSiteKey !== '') { ?>
                                    <div class="ld-recaptcha-wrap">
                                        <div id="recaptchaSlot"></div>
                                    </div>
                                <?php } else { ?>
                                    <div class="ld-empty-state">Google reCAPTCHA is not configured yet. Public draw will be available after reCAPTCHA setup is completed.</div>
                                <?php } ?>

                                <button class="ld-draw-btn" type="submit" id="drawBtn" disabled>Spin Now</button>
                                <div class="ld-form-note">You have 1 chance</div>

                                <div class="ld-mobile-wheel-anchor" id="mobileWheelAnchor"></div>

                                <div class="ld-status-note" id="statusNote"></div>
                            </form>
                        <?php } ?>

                        <div class="ld-mobile-wheel-anchor" id="mobileWheelFallbackAnchor"></div>
                    </div>

                    <div class="ld-result-card" id="resultCard">
                        <div class="ld-result-row">
                            <img class="ld-result-image" id="resultImage" src="" alt="Prize">
                            <div>
                                <div class="ld-kicker">Draw Result</div>
                                <h3 id="resultName"></h3>
                                <p id="resultMessage"></p>
                                <a id="claimLink" class="ld-result-link" href="#">Complete Claim</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="ld-win-modal" id="winResultModal" aria-hidden="true">
            <div class="ld-win-modal-backdrop"></div>
            <div class="ld-win-modal-panel" role="dialog" aria-modal="true" aria-labelledby="winResultTitle">
                <div class="ld-win-modal-body">
                    <div class="ld-win-kicker">Lucky Draw Result</div>
                    <div class="ld-win-thumb">
                        <img id="winResultImage" src="" alt="Won Prize">
                    </div>
                    <h3 class="ld-win-title" id="winResultTitle">Congratulations!</h3>
                    <p class="ld-win-text" id="winResultMessage">Your draw result is ready.</p>
                    <p class="ld-win-countdown" id="winResultCountdown"></p>
                    <div class="ld-win-actions">
                        <a href="#" class="ld-win-btn" id="winResultClaimLink">Proceed to Claim</a>
                    </div>
                </div>
            </div>
        </div>

        <section class="ld-ticker-card" id="recent-winners">
            <div class="ld-section-head">
                <div>
                    <h2>Recent Winners</h2>
                    <p>Latest claimed winners and curated public board highlights.</p>
                </div>
            </div>
            <div class="ld-ticker-window">
                <div class="ld-ticker-track" id="boardTrack">
                    <div class="ld-winner-pill"><strong>Loading</strong><span>Loading lucky winners...</span></div>
                    <div class="ld-winner-pill"><strong>Loading</strong><span>Loading lucky winners...</span></div>
                </div>
            </div>
        </section>

        <div class="ld-sections-grid">
            <section class="ld-section-card" id="how-it-works">
                <div class="ld-section-head">
                    <div>
                        <h3>How It Works</h3>
                        <p>Clear steps from verification to claim completion.</p>
                    </div>
                </div>
                <div class="ld-steps-grid">
                    <?php foreach ($howItWorks as $index => $step) { ?>
                        <article class="ld-step-card">
                            <div class="ld-step-badge"><?= (int) ($index + 1) ?></div>
                            <div class="ld-step-title"><?= htmlspecialchars((string) $step['title'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="ld-step-desc"><?= htmlspecialchars((string) $step['description'], ENT_QUOTES, 'UTF-8') ?></div>
                        </article>
                    <?php } ?>
                </div>
            </section>

            <section class="ld-section-card" id="featured-prizes">
                <div class="ld-section-head">
                    <div>
                        <h3>Featured Prizes</h3>
                        <p>Current birthday rewards available in the Lucky Draw prize pool.</p>
                    </div>
                    <?php if (count($wheelPrizes) > 4) { ?>
                        <button type="button" class="ld-view-all-btn" id="viewAllPrizesBtn">View All</button>
                    <?php } ?>
                </div>
                <?php if (empty($featuredPrizes)) { ?>
                    <div class="ld-empty-state">Featured prizes will appear here after the Lucky Draw pool is configured.</div>
                <?php } else { ?>
                    <div class="ld-prize-grid">
                        <?php foreach ($featuredPrizes as $row) { ?>
                            <?php
                            $featuredPrizeId = isset($row['id']) ? (int) $row['id'] : 0;
                            $featuredReservedCount = (int) ($voucherStateCounts[$featuredPrizeId]['reserved'] ?? 0);
                            $featuredAssignedCount = (int) (($voucherStateCounts[$featuredPrizeId]['assigned'] ?? 0) + ($voucherStateCounts[$featuredPrizeId]['sent'] ?? 0));
                            $featuredAvailable = luckyDrawPrizeAvailableUnits(
                                $row,
                                (int) ($voucherAvailableCounts[$featuredPrizeId] ?? 0),
                                $featuredReservedCount,
                                $featuredAssignedCount
                            );
                            $featuredType = strtoupper((string) ($row['prize_type'] ?? ''));
                            $featuredPhysicalImage = luckyDrawPrizeImageUrl(isset($row['prize_image']) ? (string) $row['prize_image'] : '');
                            $featuredImage = $featuredType === 'VOUCHER'
                                ? luckyDrawVoucherDataUrl((string) ($row['prize_name'] ?? 'Prize'))
                                : ($featuredPhysicalImage !== '' ? $featuredPhysicalImage : luckyDrawGiftIconDataUrl((string) ($row['prize_name'] ?? 'Prize')));
                            ?>
                            <article class="ld-prize-card">
                                <div class="ld-prize-thumb">
                                    <?php if ($featuredImage !== '') { ?>
                                        <img
                                            class="<?= $featuredType === 'VOUCHER' ? 'ld-prize-thumb-image--voucher' : '' ?>"
                                            src="<?= htmlspecialchars($featuredImage, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars((string) ($row['prize_name'] ?? 'Prize'), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php } else { ?>
                                        <span><?= htmlspecialchars((string) ($row['prize_name'] ?? 'Prize'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php } ?>
                                </div>
                                <div class="ld-prize-name"><?= htmlspecialchars((string) ($row['prize_name'] ?? 'Prize'), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="ld-prize-meta">
                                    <span><?= htmlspecialchars(strtoupper((string) ($row['prize_type'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span><?= (int) $featuredAvailable ?> left</span>
                                </div>
                            </article>
                        <?php } ?>
                    </div>
                <?php } ?>
            </section>
        </div>

        <?php if (count($wheelPrizes) > 4) { ?>
            <div class="ld-prize-modal" id="allPrizesModal" aria-hidden="true">
                <div class="ld-prize-modal-backdrop" data-close-prize-modal></div>
                <div class="ld-prize-modal-panel" role="dialog" aria-modal="true" aria-labelledby="allPrizesModalTitle">
                    <div class="ld-prize-modal-head">
                        <div>
                            <h3 id="allPrizesModalTitle">All Lucky Draw Prizes</h3>
                            <p>Browse the full birthday reward prize pool.</p>
                        </div>
                        <button type="button" class="ld-prize-modal-close" id="allPrizesCloseBtn" aria-label="Close prize list">&times;</button>
                    </div>
                    <div class="ld-prize-modal-body">
                        <div class="ld-prize-modal-grid">
                            <?php foreach ($wheelPrizes as $row) { ?>
                                <?php
                                $modalPrizeId = isset($row['id']) ? (int) $row['id'] : 0;
                                $modalReservedCount = (int) ($voucherStateCounts[$modalPrizeId]['reserved'] ?? 0);
                                $modalAssignedCount = (int) (($voucherStateCounts[$modalPrizeId]['assigned'] ?? 0) + ($voucherStateCounts[$modalPrizeId]['sent'] ?? 0));
                                $modalAvailable = luckyDrawPrizeAvailableUnits(
                                    $row,
                                    (int) ($voucherAvailableCounts[$modalPrizeId] ?? 0),
                                    $modalReservedCount,
                                    $modalAssignedCount
                                );
                                $modalType = strtoupper((string) ($row['prize_type'] ?? ''));
                                $modalPhysicalImage = luckyDrawPrizeImageUrl(isset($row['prize_image']) ? (string) $row['prize_image'] : '');
                                $modalImage = $modalType === 'VOUCHER'
                                    ? luckyDrawVoucherDataUrl((string) ($row['prize_name'] ?? 'Prize'))
                                    : ($modalPhysicalImage !== '' ? $modalPhysicalImage : luckyDrawGiftIconDataUrl((string) ($row['prize_name'] ?? 'Prize')));
                                ?>
                                <article class="ld-prize-card">
                                    <div class="ld-prize-thumb">
                                        <?php if ($modalImage !== '') { ?>
                                            <img
                                                class="<?= $modalType === 'VOUCHER' ? 'ld-prize-thumb-image--voucher' : '' ?>"
                                                src="<?= htmlspecialchars($modalImage, ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars((string) ($row['prize_name'] ?? 'Prize'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php } else { ?>
                                            <span><?= htmlspecialchars((string) ($row['prize_name'] ?? 'Prize'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php } ?>
                                    </div>
                                    <div class="ld-prize-name"><?= htmlspecialchars((string) ($row['prize_name'] ?? 'Prize'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="ld-prize-meta">
                                        <span><?= htmlspecialchars($modalType, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span><?= (int) $modalAvailable ?> left</span>
                                    </div>
                                </article>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>

        <footer class="ld-footer">
            <?= htmlspecialchars($websiteName, ENT_QUOTES, 'UTF-8') ?> Lucky Draw
        </footer>
    </div>

    <script>
        window.LuckyDrawConfig = {
            prizeRows: <?= json_encode($publicPrizeRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            storedParticipation: <?= json_encode($participationState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            wheelColors: <?= json_encode($wheelColors, JSON_UNESCAPED_SLASHES) ?>,
            themeColor: <?= json_encode($themeColor, JSON_UNESCAPED_SLASHES) ?>,
            buttonColor: <?= json_encode($buttonColor, JSON_UNESCAPED_SLASHES) ?>,
            drawEndpoint: <?= json_encode(siteUrlPath(ROUTE_LUCKY_DRAW_DRAW_SUBMIT), JSON_UNESCAPED_SLASHES) ?>,
            boardFeedEndpoint: <?= json_encode(siteUrlPath(ROUTE_LUCKY_DRAW_BOARD_FEED), JSON_UNESCAPED_SLASHES) ?>,
            recaptchaSiteKey: <?= json_encode($recaptchaSiteKey, JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="<?= htmlspecialchars($SITEURL . '/js/lucky_draw.js', ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
