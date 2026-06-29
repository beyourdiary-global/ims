(() => {
    const config = window.LuckyDrawConfig || {};
    const prizeRows = Array.isArray(config.prizeRows) ? config.prizeRows : [];
    const storedParticipation = config.storedParticipation || {};
    const prizeIndexMap = prizeRows.reduce((map, row, index) => {
        map[String(row.id)] = index;
        return map;
    }, {});
    const wheelColors = Array.isArray(config.wheelColors) ? config.wheelColors : [];
    const themeColor = config.themeColor || '#4a11c9';
    const buttonColor = config.buttonColor || '#1b1b1b';
    const drawEndpoint = config.drawEndpoint || '';
    const boardFeedEndpoint = config.boardFeedEndpoint || '';
    const recaptchaSiteKey = config.recaptchaSiteKey || '';
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    const wheelContainer = document.getElementById('luckyWheel');
    const drawForm = document.getElementById('luckyDrawForm');
    const drawBtn = document.getElementById('drawBtn');
    const statusNote = document.getElementById('statusNote');
    const boardTrack = document.getElementById('boardTrack');
    const resultCard = document.getElementById('resultCard');
    const resultImage = document.getElementById('resultImage');
    const resultName = document.getElementById('resultName');
    const resultMessage = document.getElementById('resultMessage');
    const claimLink = document.getElementById('claimLink');
    const recaptchaSlot = document.getElementById('recaptchaSlot');
    const heroGrid = document.querySelector('.ld-hero-grid');
    const wheelColumn = document.querySelector('.ld-wheel-column');
    const joinColumn = document.querySelector('.ld-join-column');
    const mobileWheelAnchor = document.getElementById('mobileWheelAnchor');
    const mobileWheelFallbackAnchor = document.getElementById('mobileWheelFallbackAnchor');
    const mobileWheelMedia = window.matchMedia('(max-width: 760px)');
    const viewAllPrizesBtn = document.getElementById('viewAllPrizesBtn');
    const allPrizesModal = document.getElementById('allPrizesModal');
    const allPrizesCloseBtn = document.getElementById('allPrizesCloseBtn');
    const winResultModal = document.getElementById('winResultModal');
    const winResultImage = document.getElementById('winResultImage');
    const winResultTitle = document.getElementById('winResultTitle');
    const winResultMessage = document.getElementById('winResultMessage');
    const winResultCountdown = document.getElementById('winResultCountdown');
    const winResultClaimLink = document.getElementById('winResultClaimLink');
    let luckyWheelInstance = null;
    let recaptchaWidgetId = null;
    let recaptchaVerified = false;
    let winRedirectTimeoutId = null;
    let winCountdownIntervalId = null;

    function syncMobileWheelPosition() {
        const targetWheelAnchor = mobileWheelAnchor || mobileWheelFallbackAnchor;

        if (!heroGrid || !wheelColumn || !joinColumn || !targetWheelAnchor) {
            return;
        }

        if (mobileWheelMedia.matches) {
            if (wheelColumn.parentNode !== targetWheelAnchor) {
                targetWheelAnchor.appendChild(wheelColumn);
            }
        } else if (wheelColumn.parentNode !== heroGrid) {
            heroGrid.insertBefore(wheelColumn, joinColumn);
        }

        if (luckyWheelInstance && typeof luckyWheelInstance.resize === 'function') {
            window.setTimeout(function () {
                luckyWheelInstance.resize();
            }, 80);
        }
    }

    if (typeof mobileWheelMedia.addEventListener === 'function') {
        mobileWheelMedia.addEventListener('change', syncMobileWheelPosition);
    } else if (typeof mobileWheelMedia.addListener === 'function') {
        mobileWheelMedia.addListener(syncMobileWheelPosition);
    }

    function currentRecaptchaTheme() {
        return prefersDarkScheme.matches ? 'dark' : 'light';
    }

    function updateDrawButtonState() {
        if (!drawBtn) {
            return;
        }

        const canUseRecaptcha = Boolean(recaptchaSiteKey);
        const isParticipationLocked = Boolean(storedParticipation && storedParticipation.participated);
        drawBtn.disabled = isParticipationLocked || !canUseRecaptcha || !recaptchaVerified;
    }

    function resizeLuckyDrawRecaptcha() {
        if (!recaptchaSlot || !recaptchaSlot.parentElement) {
            return;
        }

        const normalRecaptchaWidth = 304;
        const normalRecaptchaHeight = 78;
        const wrap = recaptchaSlot.parentElement;
        const form = wrap.closest('.ld-form');
        const formWidth = form ? form.clientWidth : normalRecaptchaWidth;
        const targetWidth = Math.min(normalRecaptchaWidth, formWidth);
        const scale = Math.min(1, targetWidth / normalRecaptchaWidth);
        const scaledWidth = Math.ceil(normalRecaptchaWidth * scale);
        const scaledHeight = Math.ceil(normalRecaptchaHeight * scale);

        wrap.style.width = `${scaledWidth}px`;
        wrap.style.height = `${scaledHeight}px`;

        recaptchaSlot.style.width = `${normalRecaptchaWidth}px`;
        recaptchaSlot.style.height = `${normalRecaptchaHeight}px`;
        recaptchaSlot.style.transform = `scale(${scale})`;
        recaptchaSlot.style.transformOrigin = 'left top';
    }

    window.renderLuckyDrawRecaptcha = function(forceRender) {
        if (!recaptchaSlot || !recaptchaSiteKey || !window.grecaptcha || typeof window.grecaptcha.render !== 'function') {
            return;
        }

        if (recaptchaWidgetId !== null && !forceRender) {
            return;
        }

        recaptchaSlot.innerHTML = '';
        recaptchaVerified = false;
        recaptchaWidgetId = window.grecaptcha.render(recaptchaSlot, {
            sitekey: recaptchaSiteKey,
            theme: currentRecaptchaTheme(),
            callback: () => {
                recaptchaVerified = true;
                updateDrawButtonState();
            },
            'expired-callback': () => {
                recaptchaVerified = false;
                updateDrawButtonState();
            },
            'error-callback': () => {
                recaptchaVerified = false;
                updateDrawButtonState();
            }
        });
        updateDrawButtonState();

        window.setTimeout(resizeLuckyDrawRecaptcha, 80);
        window.setTimeout(resizeLuckyDrawRecaptcha, 300);
        window.setTimeout(resizeLuckyDrawRecaptcha, 800);
    };

    window.addEventListener('resize', resizeLuckyDrawRecaptcha);

    if (typeof prefersDarkScheme.addEventListener === 'function') {
        prefersDarkScheme.addEventListener('change', () => {
            if (recaptchaWidgetId !== null) {
                recaptchaWidgetId = null;
                window.renderLuckyDrawRecaptcha(true);
            }
        });
    } else if (typeof prefersDarkScheme.addListener === 'function') {
        prefersDarkScheme.addListener(() => {
            if (recaptchaWidgetId !== null) {
                recaptchaWidgetId = null;
                window.renderLuckyDrawRecaptcha(true);
            }
        });
    }

    function setStatus(message, isError = false) {
        if (!statusNote) return;
        statusNote.textContent = message || '';
        statusNote.style.color = isError ? 'var(--ld-danger)' : 'var(--ld-text-soft)';
    }

    function resetRecaptcha() {
        if (window.grecaptcha && typeof window.grecaptcha.reset === 'function' && recaptchaWidgetId !== null) {
            window.grecaptcha.reset(recaptchaWidgetId);
        }
        recaptchaVerified = false;
        updateDrawButtonState();
    }

    function openAllPrizesModal() {
        if (!allPrizesModal) {
            return;
        }

        allPrizesModal.classList.add('show');
        allPrizesModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ld-modal-open');
    }

    function closeAllPrizesModal() {
        if (!allPrizesModal) {
            return;
        }

        allPrizesModal.classList.remove('show');
        allPrizesModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ld-modal-open');
    }

    function clearWinRedirectTimers() {
        if (winRedirectTimeoutId !== null) {
            window.clearTimeout(winRedirectTimeoutId);
            winRedirectTimeoutId = null;
        }

        if (winCountdownIntervalId !== null) {
            window.clearInterval(winCountdownIntervalId);
            winCountdownIntervalId = null;
        }
    }

    function openWinResultModal(data) {
        if (!winResultModal) {
            return;
        }

        clearWinRedirectTimers();

        const prize = data && data.prize ? data.prize : {};
        const prizeImage = getPrizeDisplayImage(prize);
        const claimUrl = data && data.claim_url ? String(data.claim_url).trim() : '';
        const canClaim = claimUrl !== '';
        const prizeName = prize && prize.name ? String(prize.name) : 'Your Prize';
        const message = data && data.message
            ? String(data.message)
            : 'Congratulations! Your draw result is ready.';

        winResultTitle.textContent = prizeName;
        winResultMessage.textContent = message;

        if (prizeImage) {
            winResultImage.src = prizeImage;
            winResultImage.style.visibility = 'visible';
        } else {
            winResultImage.removeAttribute('src');
            winResultImage.style.visibility = 'hidden';
        }

        if (canClaim) {
            let secondsRemaining = 10;
            winResultClaimLink.style.display = 'inline-flex';
            winResultClaimLink.href = claimUrl;
            winResultCountdown.textContent = `Redirecting to the claim page in ${secondsRemaining} seconds.`;

            winCountdownIntervalId = window.setInterval(() => {
                secondsRemaining -= 1;
                if (secondsRemaining > 0) {
                    winResultCountdown.textContent = `Redirecting to the claim page in ${secondsRemaining} seconds.`;
                } else {
                    winResultCountdown.textContent = 'Redirecting to the claim page now...';
                }
            }, 1000);

            winRedirectTimeoutId = window.setTimeout(() => {
                clearWinRedirectTimers();
                window.location.href = claimUrl;
            }, 10000);
        } else {
            winResultClaimLink.style.display = 'none';
            winResultClaimLink.removeAttribute('href');
            winResultCountdown.textContent = 'Preparing your claim page...';
        }

        winResultModal.classList.add('show');
        winResultModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ld-modal-open');
    }

    if (viewAllPrizesBtn) {
        viewAllPrizesBtn.addEventListener('click', openAllPrizesModal);
    }

    if (allPrizesCloseBtn) {
        allPrizesCloseBtn.addEventListener('click', closeAllPrizesModal);
    }

    if (allPrizesModal) {
        allPrizesModal.addEventListener('click', (event) => {
            if (event.target && event.target.hasAttribute('data-close-prize-modal')) {
                closeAllPrizesModal();
            }
        });
    }

    if (winResultClaimLink) {
        winResultClaimLink.addEventListener('click', () => {
            clearWinRedirectTimers();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllPrizesModal();
        }
    });

    function renderBoardRows(rows) {
        if (!boardTrack) return;
        if (!Array.isArray(rows) || !rows.length) {
            boardTrack.innerHTML = '<div class="ld-winner-pill"><strong>Lucky Draw</strong><span>Lucky winners will appear here soon.</span></div><div class="ld-winner-pill"><strong>Lucky Draw</strong><span>Lucky winners will appear here soon.</span></div>';
            return;
        }

        const escapeHtml = (value) => String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const items = rows.map((row) => {
            const name = escapeHtml(row && row.name ? row.name : 'Lucky Member');
            const prize = escapeHtml(row && row.prize ? row.prize : 'Prize');
            return `<div class="ld-winner-pill"><strong>${name}</strong><span>won ${prize}</span></div>`;
        });
        boardTrack.innerHTML = items.concat(items).join('');
    }

    async function refreshBoard() {
        try {
            const response = await fetch(boardFeedEndpoint, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await response.json();
            renderBoardRows(data.rows || []);
        } catch (error) {
            renderBoardRows([]);
        }
    }

    function easeOutCubic(progress) {
        return 1 - Math.pow(1 - progress, 3);
    }

    function getSpinWheelClass() {
        if (window.Wheel) {
            return window.Wheel;
        }

        if (window.spinWheel && window.spinWheel.Wheel) {
            return window.spinWheel.Wheel;
        }

        if (window.SpinWheel && window.SpinWheel.Wheel) {
            return window.SpinWheel.Wheel;
        }

        return null;
    }

    function getWheelLabelColor(backgroundColor) {
        const color = String(backgroundColor || '').trim().replace('#', '');
        if (!/^[0-9a-fA-F]{6}$/.test(color)) {
            return '#ffffff';
        }

        const red = parseInt(color.slice(0, 2), 16);
        const green = parseInt(color.slice(2, 4), 16);
        const blue = parseInt(color.slice(4, 6), 16);
        const luminance = (0.299 * red) + (0.587 * green) + (0.114 * blue);
        return luminance >= 186 ? '#111827' : '#ffffff';
    }

    function getWheelPrizeLabel(value) {
        const label = String(value || 'Prize').trim();
        return label.length > 18 ? `${label.slice(0, 17)}…` : label;
    }

    function escapeSvgText(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function splitVoucherNameLines(value, maxCharsPerLine, maxLines) {
        const words = String(value || 'Voucher Prize').trim().split(/\s+/);
        const lines = [];
        let currentLine = '';

        words.forEach((word) => {
            const testLine = currentLine ? `${currentLine} ${word}` : word;
            if (testLine.length > maxCharsPerLine && currentLine) {
                lines.push(currentLine);
                currentLine = word;
            } else {
                currentLine = testLine;
            }
        });

        if (currentLine) {
            lines.push(currentLine);
        }

        const normalizedLines = lines.slice(0, maxLines);
        if (lines.length > maxLines && normalizedLines.length > 0) {
            normalizedLines[normalizedLines.length - 1] = `${normalizedLines[normalizedLines.length - 1]}…`;
        }

        return normalizedLines;
    }

    function createVoucherImageDataUrl(prizeName) {
        const safeName = String(prizeName || 'Voucher Prize').trim();
        const nameLines = splitVoucherNameLines(safeName, 16, 3);
        const startY = 220 - ((nameLines.length - 1) * 18);
        let textNodes = '';

        nameLines.forEach((line, index) => {
            textNodes += `<text x="210" y="${startY + (index * 36)}" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="26" font-weight="800" fill="#1f2937">${escapeSvgText(line)}</text>`;
        });

        const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420" viewBox="0 0 420 420">
    <defs>
        <linearGradient id="voucherGold" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#fff4d6"/>
            <stop offset="55%" stop-color="#f7d37e"/>
            <stop offset="100%" stop-color="#d89a33"/>
        </linearGradient>
        <linearGradient id="voucherPanel" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#ffffff"/>
            <stop offset="100%" stop-color="#f8fafc"/>
        </linearGradient>
        <filter id="voucherShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="12" stdDeviation="10" flood-color="#000000" flood-opacity="0.20"/>
        </filter>
    </defs>

    <rect width="420" height="420" fill="transparent"/>

    <g filter="url(#voucherShadow)">
        <path d="M122 82
                 H298
                 Q332 82 332 116
                 V154
                 Q304 170 332 186
                 V304
                 Q332 338 298 338
                 H122
                 Q88 338 88 304
                 V186
                 Q116 170 88 154
                 V116
                 Q88 82 122 82
                 Z"
              fill="url(#voucherGold)"
              stroke="#9a6a14"
              stroke-width="6"
              stroke-linejoin="round"/>
        <path d="M132 112
                 H288
                 Q310 112 310 134
                 V286
                 Q310 308 288 308
                 H132
                 Q110 308 110 286
                 V134
                 Q110 112 132 112
                 Z"
              fill="url(#voucherPanel)"/>
        <path d="M210 120 V300" stroke="rgba(154,106,20,0.20)" stroke-width="3" stroke-dasharray="8 10"/>
        <rect x="144" y="132" width="132" height="34" rx="17" fill="#1f2937"/>
        <text x="210" y="154" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="15" font-weight="800" letter-spacing="2" fill="#ffffff">VOUCHER</text>
        ${textNodes}
        <rect x="148" y="266" width="124" height="24" rx="12" fill="rgba(216,154,51,0.18)"/>
        <text x="210" y="281" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="14" font-weight="700" fill="#9a6a14">Birthday Reward</text>
    </g>
</svg>`.trim();

        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    }

    function createGiftImageDataUrl(prizeName) {
        const safeName = String(prizeName || 'Gift Prize').trim();
        const nameLines = splitVoucherNameLines(safeName, 14, 2);
        const startY = 310 - ((nameLines.length - 1) * 16);
        let textNodes = '';

        nameLines.forEach((line, index) => {
            textNodes += `<text x="210" y="${startY + (index * 32)}" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="800" fill="#6b3f0d">${escapeSvgText(line)}</text>`;
        });

        const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420" viewBox="0 0 420 420">
    <defs>
        <linearGradient id="giftBoxBody" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#fff7e8"/>
            <stop offset="100%" stop-color="#f6d796"/>
        </linearGradient>
        <linearGradient id="giftRibbon" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#f0b34b"/>
            <stop offset="100%" stop-color="#c57b18"/>
        </linearGradient>
        <filter id="giftShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="12" stdDeviation="10" flood-color="#000000" flood-opacity="0.18"/>
        </filter>
    </defs>

    <rect width="420" height="420" fill="transparent"/>

    <g filter="url(#giftShadow)">
        <path d="M146 126 C120 92 138 56 174 60 C195 62 207 84 210 96 C213 84 225 62 246 60 C282 56 300 92 274 126" fill="url(#giftRibbon)"/>
        <rect x="88" y="136" width="244" height="66" rx="18" fill="url(#giftBoxBody)" stroke="#c58b2d" stroke-width="6"/>
        <rect x="76" y="196" width="268" height="156" rx="24" fill="url(#giftBoxBody)" stroke="#c58b2d" stroke-width="6"/>
        <rect x="192" y="136" width="36" height="216" rx="12" fill="url(#giftRibbon)"/>
        <rect x="76" y="238" width="268" height="30" rx="14" fill="url(#giftRibbon)"/>
        <circle cx="176" cy="112" r="26" fill="none" stroke="url(#giftRibbon)" stroke-width="12"/>
        <circle cx="244" cy="112" r="26" fill="none" stroke="url(#giftRibbon)" stroke-width="12"/>
        <rect x="114" y="282" width="192" height="56" rx="20" fill="rgba(255,255,255,0.74)"/>
        ${textNodes}
    </g>
</svg>`.trim();

        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    }

    function getPrizeDisplayImage(row) {
        const prizeType = String(row && row.type ? row.type : '').toLowerCase();
        const prizeName = row && row.name ? String(row.name) : 'Voucher Prize';

        if (prizeType === 'voucher') {
            return createVoucherImageDataUrl(prizeName);
        }

        return row && row.image ? String(row.image) : createGiftImageDataUrl(prizeName);
    }

    function createWheelVoucherIconDataUrl() {
        const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220" viewBox="0 0 220 220">
    <defs>
        <linearGradient id="wheelVoucherGold" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#fff4d6"/>
            <stop offset="55%" stop-color="#f7d37e"/>
            <stop offset="100%" stop-color="#d89a33"/>
        </linearGradient>
        <linearGradient id="wheelVoucherPanel" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#ffffff"/>
            <stop offset="100%" stop-color="#f8fafc"/>
        </linearGradient>
        <filter id="wheelVoucherShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="8" stdDeviation="8" flood-color="#000000" flood-opacity="0.18"/>
        </filter>
    </defs>
    <rect width="220" height="220" fill="transparent"/>
    <g filter="url(#wheelVoucherShadow)">
        <path d="M58 42 H162 Q182 42 182 62 V84 Q166 96 182 108 V158 Q182 178 162 178 H58 Q38 178 38 158 V108 Q54 96 38 84 V62 Q38 42 58 42 Z" fill="url(#wheelVoucherGold)" stroke="#9a6a14" stroke-width="5" stroke-linejoin="round"/>
        <path d="M66 58 H154 Q166 58 166 70 V150 Q166 162 154 162 H66 Q54 162 54 150 V70 Q54 58 66 58 Z" fill="url(#wheelVoucherPanel)"/>
        <rect x="72" y="72" width="76" height="22" rx="11" fill="#1f2937"/>
        <text x="110" y="86" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="800" fill="#ffffff">VOUCHER</text>
        <text x="110" y="120" text-anchor="middle" dominant-baseline="middle" font-family="Segoe UI, Arial, sans-serif" font-size="30" font-weight="900" fill="#1f2937">%</text>
        <rect x="76" y="134" width="68" height="14" rx="7" fill="rgba(216,154,51,0.20)"/>
    </g>
</svg>`.trim();

        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    }

    function createWheelGiftIconDataUrl() {
        const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220" viewBox="0 0 220 220">
    <defs>
        <linearGradient id="wheelGiftBody" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#fff7e8"/>
            <stop offset="100%" stop-color="#f6d796"/>
        </linearGradient>
        <linearGradient id="wheelGiftRibbon" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#f0b34b"/>
            <stop offset="100%" stop-color="#c57b18"/>
        </linearGradient>
        <filter id="wheelGiftShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="8" stdDeviation="8" flood-color="#000000" flood-opacity="0.18"/>
        </filter>
    </defs>
    <rect width="220" height="220" fill="transparent"/>
    <g filter="url(#wheelGiftShadow)">
        <path d="M80 60 C68 44 76 28 94 30 C104 31 109 42 110 48 C111 42 116 31 126 30 C144 28 152 44 140 60" fill="url(#wheelGiftRibbon)"/>
        <rect x="54" y="66" width="112" height="34" rx="10" fill="url(#wheelGiftBody)" stroke="#c58b2d" stroke-width="4"/>
        <rect x="48" y="98" width="124" height="76" rx="14" fill="url(#wheelGiftBody)" stroke="#c58b2d" stroke-width="4"/>
        <rect x="102" y="66" width="16" height="108" rx="6" fill="url(#wheelGiftRibbon)"/>
        <rect x="48" y="118" width="124" height="16" rx="8" fill="url(#wheelGiftRibbon)"/>
        <circle cx="94" cy="54" r="14" fill="none" stroke="url(#wheelGiftRibbon)" stroke-width="8"/>
        <circle cx="126" cy="54" r="14" fill="none" stroke="url(#wheelGiftRibbon)" stroke-width="8"/>
    </g>
</svg>`.trim();

        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    }

    function getWheelDisplayImage(row) {
        const prizeType = String(row && row.type ? row.type : '').toLowerCase();

        if (prizeType === 'voucher') {
            return createWheelVoucherIconDataUrl();
        }

        return createWheelGiftIconDataUrl();
    }

    function buildWheelPrizeImage(src) {
        const imageUrl = String(src || '').trim();
        if (!imageUrl) {
            return null;
        }

        const image = new Image();
        image.decoding = 'async';
        image.loading = 'eager';
        image.onload = () => {
            if (luckyWheelInstance && typeof luckyWheelInstance.resize === 'function') {
                luckyWheelInstance.resize();
            }
        };
        image.src = imageUrl;

        return image;
    }

    function initLuckyWheel() {
        const SpinWheelClass = getSpinWheelClass();
        if (!wheelContainer || !SpinWheelClass || luckyWheelInstance) {
            return;
        }

        const fallbackWheelRows = [{ id: 0, name: 'Prize', type: '', weight: 1, image: '', color: themeColor }];
        const sourceWheelRows = prizeRows.length ? prizeRows : fallbackWheelRows;
        const useCompactWheelMode = sourceWheelRows.length >= 8;
        const useDenseWheelMode = sourceWheelRows.length > 10;

        const wheelItems = sourceWheelRows.map((row, index) => {
            const backgroundColor = String(row && row.color ? row.color : (wheelColors[index % wheelColors.length] || themeColor));
            const prizeType = String(row && row.type ? row.type : '').toLowerCase();
            const isVoucherPrize = prizeType === 'voucher';
            const prizeImage = buildWheelPrizeImage(
                useCompactWheelMode ? getWheelDisplayImage(row) : getPrizeDisplayImage(row)
            );

            return {
                label: useCompactWheelMode ? '' : (isVoucherPrize ? '' : getWheelPrizeLabel(row && row.name ? row.name : 'Prize')),
                value: row && row.id ? row.id : 0,
                weight: Math.max(1, Number(row && row.weight ? row.weight : 1)),
                backgroundColor,
                labelColor: getWheelLabelColor(backgroundColor),
                image: prizeImage,
                imageRadius: prizeImage ? (useDenseWheelMode ? 0.76 : (useCompactWheelMode ? 0.70 : (isVoucherPrize ? 0.58 : 0.60))) : 0,
                imageScale: prizeImage ? (useDenseWheelMode ? (isVoucherPrize ? 0.18 : 0.17) : (useCompactWheelMode ? (isVoucherPrize ? 0.28 : 0.26) : (isVoucherPrize ? 0.36 : 0.22))) : 0,
                imageRotation: 0,
            };
        });

        luckyWheelInstance = new SpinWheelClass(wheelContainer, {
            items: wheelItems,
            radius: 0.94,
            borderColor: 'rgba(255, 255, 255, 0.94)',
            borderWidth: useDenseWheelMode ? 12 : 14,
            lineColor: 'rgba(255, 255, 255, 0.56)',
            lineWidth: useDenseWheelMode ? 2 : 3,
            itemLabelFont: 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif',
            itemLabelFontSizeMax: useCompactWheelMode ? 10 : 14,
            itemLabelRadius: useCompactWheelMode ? 0.90 : 0.84,
            itemLabelRadiusMax: useCompactWheelMode ? 0.18 : 0.28,
            itemLabelAlign: 'center',
            itemLabelRotation: 0,
            itemLabelColors: ['#ffffff'],
            itemLabelStrokeColor: 'rgba(0, 0, 0, 0.24)',
            itemLabelStrokeWidth: useCompactWheelMode ? 1 : 2,
            pointerAngle: 0,
            isInteractive: false,
            rotationSpeedMax: 520,
        });
    }

    function spinToPrize(prizeId) {
        if (!prizeRows.length) {
            return;
        }

        const prizeIndex = Object.prototype.hasOwnProperty.call(prizeIndexMap, String(prizeId)) ? prizeIndexMap[String(prizeId)] : 0;
        if (luckyWheelInstance && typeof luckyWheelInstance.spinToItem === 'function') {
            luckyWheelInstance.spinToItem(prizeIndex, 6200, true, 7, 1, easeOutCubic);
        }
    }

    function showResult(data, options = {}) {
        if (!resultCard) return;
        const prize = data.prize || {};
        const prizeImage = getPrizeDisplayImage(prize);
        const prizeType = String(prize && prize.type ? prize.type : '').toLowerCase();
        const canClaim = Boolean(data && data.can_claim && data.claim_url);
        const shouldOpenModal = Boolean(options && options.openModal);

        resultName.textContent = prize.name || 'Your Prize';
        resultMessage.textContent = data.message || 'Please complete the claim form to secure your reward.';
        resultImage.classList.toggle('ld-result-image--voucher', prizeType === 'voucher');
        claimLink.style.display = canClaim ? 'inline-flex' : 'none';
        if (canClaim) {
            claimLink.href = data.claim_url || '#';
        } else {
            claimLink.removeAttribute('href');
        }

        if (prizeImage) {
            resultImage.src = prizeImage;
            resultImage.style.visibility = 'visible';
        } else {
            resultImage.removeAttribute('src');
            resultImage.style.visibility = 'hidden';
        }

        resultCard.classList.add('show');

        if (shouldOpenModal) {
            openWinResultModal(data);
        }
    }

    if (drawForm) {
        drawForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!drawBtn) return;
            if (!recaptchaVerified) {
                setStatus('Please complete reCAPTCHA before spinning the wheel.', true);
                updateDrawButtonState();
                return;
            }

            drawBtn.disabled = true;
            if (resultCard) {
                resultCard.classList.remove('show');
            }
            setStatus('Verifying and spinning your birthday draw...');

            try {
                const response = await fetch(drawEndpoint, {
                    method: 'POST',
                    body: new FormData(drawForm),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    resetRecaptcha();
                    setStatus(data.message || 'Unable to process your draw right now.', true);
                    drawBtn.disabled = false;
                    return;
                }

                spinToPrize(data.prize && data.prize.id ? data.prize.id : 0);
                setStatus('The wheel is spinning... prepare to claim your prize!');
                window.setTimeout(() => {
                    showResult(data, { openModal: true });
                    resetRecaptcha();
                    setStatus('Your prize is ready. Complete the claim form now.');
                    drawBtn.disabled = false;
                }, 6600);
            } catch (error) {
                resetRecaptcha();
                setStatus('Unable to reach the Lucky Draw service. Please try again later.', true);
                drawBtn.disabled = false;
            }
        });
    }

    syncMobileWheelPosition();
    initLuckyWheel();
    refreshBoard();
    window.setInterval(refreshBoard, 20000);

    if (storedParticipation && storedParticipation.participated) {
        showResult(storedParticipation);
        setStatus(storedParticipation.message || storedParticipation.status_note || 'You already participated the lucky draw.');
        if (drawBtn) {
            drawBtn.disabled = true;
        }
    }

    updateDrawButtonState();

    if (recaptchaSiteKey && window.grecaptcha && typeof window.grecaptcha.render === 'function') {
        window.renderLuckyDrawRecaptcha(true);
    }

})();