<?php
$estimatedDateModalMinDate = '';
$estimatedDateModalMaxDate = '';

if (isset($estimatedDateMin) && isset($estimatedDateMax)) {
    $estimatedDateModalMinDate = (string) $estimatedDateMin;
    $estimatedDateModalMaxDate = (string) $estimatedDateMax;
} else if (isset($estimatedDateValidation) && is_array($estimatedDateValidation)) {
    $estimatedDateModalMinDate = isset($estimatedDateValidation['min_date']) ? (string) $estimatedDateValidation['min_date'] : '';
    $estimatedDateModalMaxDate = isset($estimatedDateValidation['max_date']) ? (string) $estimatedDateValidation['max_date'] : '';
}

$estimatedDateModalSubmitName = isset($estimatedDateModalSubmitName) && trim((string) $estimatedDateModalSubmitName) !== ''
    ? trim((string) $estimatedDateModalSubmitName)
    : (isset($activePlatform) ? 'saveEstimatedDateBtn' : 'assignEstimatedReceivedDateBtn');

$estimatedDateModalPlatformSection = isset($activePlatform) ? (string) $activePlatform : '';
$estimatedDateModalCsrfToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
?>
<style>
    .estimated-date-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.45);
        padding: 16px;
    }

    .estimated-date-modal.is-open {
        display: flex;
    }

    .estimated-date-modal__dialog {
        width: 100%;
        max-width: 420px;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22);
        padding: 20px;
    }

    .estimated-date-modal__close-btn,
    .estimated-date-modal__action-btn {
        text-transform: none !important;
    }
</style>

<div id="estimatedReceivedDateModal" class="estimated-date-modal" aria-hidden="true" onclick="if (event.target === this) closeEstimatedReceivedDateModal();">
    <div class="estimated-date-modal__dialog">
        <form method="post" action="">
            <?php if ($estimatedDateModalCsrfToken !== '') { ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($estimatedDateModalCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <?php } ?>
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h5 class="mb-0" id="estimatedReceivedDateTitle">Assign Estimate Received Date</h5>
                <button type="button" class="btn btn-sm btn-light px-2 estimated-date-modal__close-btn" onclick="closeEstimatedReceivedDateModal()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <input type="hidden" name="<?= htmlspecialchars($estimatedDateModalSubmitName, ENT_QUOTES, 'UTF-8') ?>" value="1">
            <input type="hidden" name="estimated_received_platform" id="estimated_received_platform" value="">
            <input type="hidden" name="estimated_received_order_id" id="estimated_received_order_id" value="">
            <?php if ($estimatedDateModalPlatformSection !== '') { ?>
                <input type="hidden" name="platform_section" id="estimated_received_platform_section" value="<?= htmlspecialchars($estimatedDateModalPlatformSection, ENT_QUOTES, 'UTF-8') ?>">
            <?php } ?>
            <div class="mb-3">
                <label class="form-label" for="estimated_received_date">Estimate Received Date</label>
                <input type="date" class="form-control" name="estimated_received_date" id="estimated_received_date" min="<?= htmlspecialchars($estimatedDateModalMinDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars($estimatedDateModalMaxDate, ENT_QUOTES, 'UTF-8') ?>" required>
                <small class="text-muted" id="estimated_received_date_hint">Choose a date from <?= htmlspecialchars($estimatedDateModalMinDate, ENT_QUOTES, 'UTF-8') ?> until <?= htmlspecialchars($estimatedDateModalMaxDate, ENT_QUOTES, 'UTF-8') ?>.</small>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary estimated-date-modal__action-btn" onclick="closeEstimatedReceivedDateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary estimated-date-modal__action-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEstimatedReceivedDateModal(firstArg, secondArg, thirdArg, fourthArg, fifthArg) {
        var platformKey = '';
        var orderId = firstArg;
        var orderCode = secondArg;
        var minDate = thirdArg;
        var maxDate = fourthArg;

        if (arguments.length >= 5) {
            platformKey = firstArg || '';
            orderId = secondArg;
            orderCode = thirdArg;
            minDate = fourthArg;
            maxDate = fifthArg;
        }

        var modal = document.getElementById('estimatedReceivedDateModal');
        var title = document.getElementById('estimatedReceivedDateTitle');
        var platformInput = document.getElementById('estimated_received_platform');
        var orderIdInput = document.getElementById('estimated_received_order_id');
        var dateInput = document.getElementById('estimated_received_date');
        var dateHint = document.getElementById('estimated_received_date_hint');

        if (!modal || !orderIdInput || !dateInput) {
            return;
        }

        if (title) {
            title.textContent = orderCode ? 'Assign Estimate Received Date for ' + orderCode : 'Assign Estimate Received Date';
        }
        if (platformInput) {
            platformInput.value = platformKey || '';
        }

        orderIdInput.value = orderId || '';
        dateInput.value = '';
        dateInput.min = minDate || dateInput.min || '';
        dateInput.max = maxDate || dateInput.max || '';

        if (dateHint) {
            dateHint.textContent = 'Choose a date from ' + dateInput.min + ' until ' + dateInput.max + '.';
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeEstimatedReceivedDateModal() {
        var modal = document.getElementById('estimatedReceivedDateModal');
        if (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }
</script>