<?php
include_once dirname(__DIR__) . '/init.php';
include_once ROOT . '/include/common.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if (!headers_sent()) {
        header('Location: ' . siteUrlPath(ROUTE_LUCKY_DRAW_HOME));
    }
    exit;
}

$readiness = luckyDrawReadiness($connect, $finance_connect);
if (empty($readiness['success'])) {
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => 'Lucky Draw is being prepared. Please try again later.',
    ), 503);
}

$csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
if (!luckyDrawValidateCsrfToken($csrfToken)) {
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => 'Your session has expired. Please refresh the page and try again.',
    ), 419);
}

$identityInput = luckyDrawNormalizeFullId(isset($_POST['member_identity']) ? $_POST['member_identity'] : '');
$submittedYymmdd = luckyDrawExtractYymmddFromId($identityInput);
$remoteIp = luckyDrawGetRemoteIp();
$ipHmac = luckyDrawIpHmac($remoteIp);
$requestMemberHmac = $identityInput !== '' ? luckyDrawMemberIdHmac($identityInput) : '';

if ($submittedYymmdd === '') {
    luckyDrawRecordRequestLog($connect, 'draw_attempt', $requestMemberHmac, $ipHmac, 'invalid_identity');
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => 'Please enter your full IC number.',
    ), 422);
}

$rateLimit = luckyDrawCheckRateLimit($connect, $requestMemberHmac, $ipHmac, 'draw_attempt');
if (empty($rateLimit['success'])) {
    luckyDrawRecordRequestLog($connect, 'draw_attempt', $requestMemberHmac, $ipHmac, 'rate_limited');
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => isset($rateLimit['message']) ? (string) $rateLimit['message'] : 'Too many attempts. Please try again later.',
    ), 429);
}

$recaptchaToken = trim((string) (isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : ''));
$recaptchaResult = luckyDrawValidateRecaptchaToken($recaptchaToken, $remoteIp);
if (empty($recaptchaResult['success'])) {
    luckyDrawRecordRequestLog($connect, 'draw_attempt', $requestMemberHmac, $ipHmac, 'recaptcha_failed');
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => isset($recaptchaResult['message']) ? (string) $recaptchaResult['message'] : 'Human verification failed.',
    ), 422);
}

$memberLookup = luckyDrawLookupUrbanMemberByIdentity($connect, $identityInput);
if (empty($memberLookup['success']) || empty($memberLookup['member'])) {
    luckyDrawRecordRequestLog($connect, 'draw_attempt', $requestMemberHmac, $ipHmac, 'member_not_found');
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => isset($memberLookup['message']) ? (string) $memberLookup['message'] : 'This member is not eligible for the birthday draw.',
    ), 403);
}

$memberRow = (array) $memberLookup['member'];
$eligibility = luckyDrawValidateEligibility($memberRow, $submittedYymmdd);
if (empty($eligibility['success'])) {
    luckyDrawRecordRequestLog($connect, 'draw_attempt', (string) $memberRow['member_id_hmac'], $ipHmac, 'eligibility_failed');
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => isset($eligibility['message']) ? (string) $eligibility['message'] : 'This member is not eligible for the birthday draw.',
    ), 403);
}

luckyDrawRecordRequestLog($connect, 'draw_attempt', (string) $memberRow['member_id_hmac'], $ipHmac, 'validated');
$reservationResult = luckyDrawCreateReservation(
    $connect,
    $finance_connect,
    $memberRow,
    (string) $memberRow['member_id_hmac'],
    $submittedYymmdd,
    $ipHmac
);

if (empty($reservationResult['success'])) {
    luckyDrawRecordRequestLog($connect, 'draw_attempt', (string) $memberRow['member_id_hmac'], $ipHmac, 'draw_failed');
    luckyDrawJsonResponse(array(
        'success' => false,
        'message' => isset($reservationResult['message']) ? (string) $reservationResult['message'] : 'Unable to complete the draw right now.',
    ), 409);
}

$prizeRow = isset($reservationResult['prize']) && is_array($reservationResult['prize']) ? $reservationResult['prize'] : array();
luckyDrawRememberParticipationSession(
    isset($reservationResult['claim_token']) ? (string) $reservationResult['claim_token'] : '',
    isset($reservationResult['claim_url']) ? (string) $reservationResult['claim_url'] : '',
    $prizeRow,
    'Your previous draw is still available. Complete your claim now.'
);
luckyDrawRecordRequestLog($connect, 'draw_attempt', (string) $memberRow['member_id_hmac'], $ipHmac, 'won');
luckyDrawJsonResponse(array(
    'success' => true,
    'can_claim' => !empty($reservationResult['claim_url']),
    'message' => 'Congratulations! Your draw result is ready.',
    'claim_url' => isset($reservationResult['claim_url']) ? (string) $reservationResult['claim_url'] : '',
    'prize' => array(
        'id' => isset($prizeRow['id']) ? (int) $prizeRow['id'] : 0,
        'name' => isset($prizeRow['prize_name']) ? (string) $prizeRow['prize_name'] : 'Prize',
        'type' => isset($prizeRow['prize_type']) ? (string) $prizeRow['prize_type'] : '',
        'image' => luckyDrawPrizeImageUrl(isset($prizeRow['prize_image']) ? $prizeRow['prize_image'] : ''),
    ),
));
