<?php

if (!function_exists('shopeeOrderDetailPdfRenderVerifyModal')) {
    function shopeeOrderDetailPdfRenderVerifyModal($config = array())
    {
        $config = is_array($config) ? $config : array();
        $modalId = isset($config['modal_id']) && trim((string) $config['modal_id']) !== '' ? trim((string) $config['modal_id']) : 'sorVerifyOrderModal';
        $csrfToken = isset($config['csrf_token']) ? (string) $config['csrf_token'] : '';
        $defaultPdfPath = isset($config['default_pdf_path']) ? (string) $config['default_pdf_path'] : '';
        ?>
        <style>
            #<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?> .modal-footer .btn,
            #<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?> .modal-header .btn,
            #<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?> .modal-body .btn {
                text-transform: none !important;
            }

            .shopee-airbill-preview-media {
                margin-top: 12px;
            }

            .shopee-verify-method-card {
                border: 1px solid #d9e2ef;
                border-radius: 14px;
                background: #fff;
                padding: 20px 22px;
                width: 100%;
                text-align: left;
                transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
            }

            .shopee-verify-method-card:hover {
                transform: translateY(-1px);
            }

            .shopee-verify-method-card-success {
                border-color: #31b45a;
                box-shadow: 0 0 0 2px rgba(49, 180, 90, 0.08);
            }

            .shopee-verify-method-card-primary {
                border-color: #2f6fdd;
                box-shadow: 0 0 0 2px rgba(47, 111, 221, 0.08);
            }

            .shopee-airbill-preview-media img,
            .shopee-airbill-preview-media iframe {
                width: 100%;
                max-width: 520px;
                border: 1px solid #d9e2ef;
                border-radius: 10px;
                background: #fff;
            }

            .shopee-airbill-preview-media iframe {
                min-height: 520px;
            }
        </style>
        <div class="modal fade" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Title">Verify Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-start">
                        <input type="hidden" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>PdfPath" value="<?= htmlspecialchars($defaultPdfPath, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>OrderId" value="">

                        <div id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>ChoiceView">
                            <div class="border rounded-3 p-3 bg-light mb-4">Choose verification method</div>
                            <div class="d-grid gap-3">
                                <button type="button" class="shopee-verify-method-card shopee-verify-method-card-success" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>DirectChoiceBtn">
                                    <div class="fw-bold fs-5 text-success mb-1">Verified</div>
                                    <div class="text-muted">Mark this order as verified without uploading a PDF.</div>
                                </button>
                                <button type="button" class="shopee-verify-method-card shopee-verify-method-card-primary" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>UploadChoiceBtn">
                                    <div class="fw-bold fs-5 text-primary mb-1">Upload Pdf to Verified</div>
                                    <div class="text-muted">Upload a Shopee Order Detail PDF and verify by comparing details.</div>
                                </button>
                            </div>
                        </div>

                        <div class="row g-4 d-none" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>PdfView">
                            <div class="col-lg-4">
                                <div class="border rounded-3 p-3 bg-light h-100">
                                    <h6 class="mb-3">Upload PDF</h6>
                                    <div class="mb-3">
                                        <label class="form-label" for="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>File">Shopee Order Detail PDF</label>
                                        <input type="file" class="form-control" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>File" accept=".pdf,application/pdf">
                                        <small class="text-muted d-block mt-2" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Status">Only PDF file is allowed.</small>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>CompareBtn">Upload PDF & Compare</button>
                                        <button type="button" class="btn btn-outline-secondary d-none" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>ReloadBtn">Reload PDF to Verified</button>
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label">Saved PDF Path</label>
                                        <input type="text" class="form-control" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>PathDisplay" readonly value="<?= htmlspecialchars($defaultPdfPath, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                        <h6 class="mb-0">Comparison Result</h6>
                                        <span class="badge bg-light text-dark border" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Badge">No PDF loaded</span>
                                    </div>
                                    <div id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Message" class="alert alert-light border">Upload the Shopee Order Detail PDF first, then review only the different fields before verifying.</div>
                                    <div class="table-responsive d-none" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>TableWrap">
                                        <table class="table table-bordered align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Field Name</th>
                                                    <th>Current Value</th>
                                                    <th>PDF Value</th>
                                                    <th>Editable Final Value</th>
                                                </tr>
                                            </thead>
                                            <tbody id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>Rows"></tbody>
                                        </table>
                                    </div>
                                    <div id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>PreviewEmpty" class="alert alert-light border mb-0">No Order Detail PDF uploaded.</div>
                                    <div id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>PreviewWrap" class="shopee-airbill-preview-media d-none">
                                        <iframe id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>PreviewIframe" src="" title="Order Detail PDF Preview"></iframe>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary d-none" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>BackBtn">Back</button>
                        <button type="button" class="btn btn-success d-none" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>UpdateBtn" disabled>Save Edited Info and Update to Verified</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('shopeeOrderDetailPdfRenderVerifyModalScript')) {
    function shopeeOrderDetailPdfRenderVerifyModalScript($config = array())
    {
        $config = is_array($config) ? $config : array();
        $modalId = isset($config['modal_id']) && trim((string) $config['modal_id']) !== '' ? trim((string) $config['modal_id']) : 'sorVerifyOrderModal';
        $triggerSelector = isset($config['trigger_selector']) && trim((string) $config['trigger_selector']) !== '' ? trim((string) $config['trigger_selector']) : '.sor-verify-order-trigger';
        $endpointTemplate = isset($config['endpoint_template']) && trim((string) $config['endpoint_template']) !== '' ? trim((string) $config['endpoint_template']) : '../shopee/shopee_order_req.php?id=__ORDER_ID__&act=E';
        $redirectUrl = isset($config['redirect_url']) && trim((string) $config['redirect_url']) !== '' ? trim((string) $config['redirect_url']) : '';
        $siteUrl = isset($config['site_url']) && trim((string) $config['site_url']) !== '' ? rtrim((string) $config['site_url'], '/') : '';
        ?>
        <script src="../finance/header/js/pdf.min.js"></script>
    <script src="../js/pdf_airbill_parser.js"></script>
        <script>
            (function () {
                var config = {
                    modalId: <?= json_encode($modalId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    triggerSelector: <?= json_encode($triggerSelector, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    endpointTemplate: <?= json_encode($endpointTemplate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    redirectUrl: <?= json_encode($redirectUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    siteUrl: <?= json_encode($siteUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                };

                function bindVerifyModal() {
                    var modalElement = document.getElementById(config.modalId);
                    if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                        return;
                    }

                    var modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
                    var titleNode = document.getElementById(config.modalId + 'Title');
                    var csrfField = document.getElementById(config.modalId + 'Csrf');
                    var orderIdField = document.getElementById(config.modalId + 'OrderId');
                    var pdfPathField = document.getElementById(config.modalId + 'PdfPath');
                    var choiceView = document.getElementById(config.modalId + 'ChoiceView');
                    var pdfView = document.getElementById(config.modalId + 'PdfView');
                    var fileInput = document.getElementById(config.modalId + 'File');
                    var statusNode = document.getElementById(config.modalId + 'Status');
                    var directChoiceBtn = document.getElementById(config.modalId + 'DirectChoiceBtn');
                    var uploadChoiceBtn = document.getElementById(config.modalId + 'UploadChoiceBtn');
                    var compareBtn = document.getElementById(config.modalId + 'CompareBtn');
                    var reloadBtn = document.getElementById(config.modalId + 'ReloadBtn');
                    var backBtn = document.getElementById(config.modalId + 'BackBtn');
                    var updateBtn = document.getElementById(config.modalId + 'UpdateBtn');
                    var compareBadge = document.getElementById(config.modalId + 'Badge');
                    var compareMessage = document.getElementById(config.modalId + 'Message');
                    var compareTableWrap = document.getElementById(config.modalId + 'TableWrap');
                    var compareRows = document.getElementById(config.modalId + 'Rows');
                    var pdfPathDisplay = document.getElementById(config.modalId + 'PathDisplay');
                    var previewWrap = document.getElementById(config.modalId + 'PreviewWrap');
                    var previewIframe = document.getElementById(config.modalId + 'PreviewIframe');
                    var previewEmpty = document.getElementById(config.modalId + 'PreviewEmpty');
                    var latestComparisonRows = [];
                    var localPreviewUrl = '';
                    var canFinalizeVerify = false;

                    function setStatus(message, isError) {
                        if (!statusNode) {
                            return;
                        }

                        statusNode.textContent = message || '';
                        statusNode.classList.toggle('text-danger', !!isError);
                        statusNode.classList.toggle('text-muted', !isError);
                    }

                    function setBusyState(isBusy) {
                        [directChoiceBtn, uploadChoiceBtn, compareBtn, reloadBtn, backBtn].forEach(function (button) {
                            if (button) {
                                button.disabled = !!isBusy;
                            }
                        });
                        if (updateBtn) {
                            updateBtn.disabled = !!isBusy || !canFinalizeVerify;
                        }
                    }

                    function resetLocalPreviewUrl() {
                        if (localPreviewUrl) {
                            try {
                                URL.revokeObjectURL(localPreviewUrl);
                            } catch (error) {
                            }
                            localPreviewUrl = '';
                        }
                    }

                    function showChoiceView() {
                        if (choiceView) {
                            choiceView.classList.remove('d-none');
                        }
                        if (pdfView) {
                            pdfView.classList.add('d-none');
                        }
                        if (backBtn) {
                            backBtn.classList.add('d-none');
                        }
                        if (updateBtn) {
                            updateBtn.classList.add('d-none');
                        }
                    }

                    function showPdfView() {
                        if (choiceView) {
                            choiceView.classList.add('d-none');
                        }
                        if (pdfView) {
                            pdfView.classList.remove('d-none');
                        }
                        if (backBtn) {
                            backBtn.classList.remove('d-none');
                        }
                        if (updateBtn) {
                            updateBtn.classList.remove('d-none');
                        }
                    }

                    function updatePdfPreview(pdfUrl) {
                        if (!previewWrap || !previewIframe || !previewEmpty) {
                            return;
                        }
                        if (pdfUrl) {
                            previewIframe.src = pdfUrl;
                            previewWrap.classList.remove('d-none');
                            previewEmpty.classList.add('d-none');
                        } else {
                            previewIframe.removeAttribute('src');
                            previewWrap.classList.add('d-none');
                            previewEmpty.classList.remove('d-none');
                        }
                    }

                    function resetComparisonState() {
                        latestComparisonRows = [];
                        canFinalizeVerify = false;
                        resetLocalPreviewUrl();
                        if (compareRows) {
                            compareRows.innerHTML = '';
                        }
                        if (compareTableWrap) {
                            compareTableWrap.classList.add('d-none');
                        }
                        if (compareMessage) {
                            compareMessage.className = 'alert alert-light border';
                            compareMessage.textContent = 'Upload the Shopee Order Detail PDF first, then review only the different fields before verifying.';
                        }
                        if (compareBadge) {
                            compareBadge.textContent = 'No PDF loaded';
                        }
                        if (updateBtn) {
                            updateBtn.disabled = true;
                        }
                        if (reloadBtn) {
                            reloadBtn.classList.add('d-none');
                        }
                    }

                    function renderFinalInput(row) {
                        var wrapper = document.createElement('div');
                        var inputType = row.input_type || 'text';
                        if (inputType === 'select') {
                            var select = document.createElement('select');
                            select.className = 'form-select sor-verify-final-value';
                            select.setAttribute('data-field-name', row.field_name);
                            var options = row.options || {};
                            Object.keys(options).forEach(function (optionKey) {
                                var option = document.createElement('option');
                                option.value = optionKey;
                                option.textContent = options[optionKey];
                                if (String(row.final_value) === String(optionKey)) {
                                    option.selected = true;
                                }
                                select.appendChild(option);
                            });
                            wrapper.appendChild(select);
                            return wrapper;
                        }

                        var input = document.createElement('input');
                        input.type = (inputType === 'number' || inputType === 'date' || inputType === 'time') ? inputType : 'text';
                        input.step = inputType === 'number' ? '0.01' : '';
                        input.className = 'form-control sor-verify-final-value';
                        input.setAttribute('data-field-name', row.field_name);
                        input.value = row.final_value || '';
                        wrapper.appendChild(input);
                        return wrapper;
                    }

                    function renderComparisonRows(rows) {
                        latestComparisonRows = Array.isArray(rows) ? rows : [];
                        if (!compareRows || !compareTableWrap || !compareMessage || !compareBadge) {
                            return;
                        }

                        compareRows.innerHTML = '';
                        if (!latestComparisonRows.length) {
                            compareTableWrap.classList.add('d-none');
                            compareMessage.className = 'alert alert-success border';
                            compareMessage.textContent = 'No different fields were found. You can continue with Save Edited Info and Update to Verified.';
                            compareBadge.textContent = 'No differences';
                            canFinalizeVerify = true;
                            if (updateBtn) {
                                updateBtn.disabled = false;
                            }
                            return;
                        }

                        latestComparisonRows.forEach(function (row) {
                            var tr = document.createElement('tr');

                            var fieldTd = document.createElement('td');
                            fieldTd.textContent = row.field_label || row.field_name || '';
                            tr.appendChild(fieldTd);

                            var currentTd = document.createElement('td');
                            currentTd.textContent = row.current_value == null ? '' : String(row.current_value);
                            tr.appendChild(currentTd);

                            var pdfTd = document.createElement('td');
                            pdfTd.textContent = row.pdf_value == null ? '' : String(row.pdf_value);
                            tr.appendChild(pdfTd);

                            var finalTd = document.createElement('td');
                            finalTd.appendChild(renderFinalInput(row));
                            tr.appendChild(finalTd);

                            compareRows.appendChild(tr);
                        });

                        compareTableWrap.classList.remove('d-none');
                        compareMessage.className = 'alert alert-warning border';
                        compareMessage.textContent = 'Only the different fields are shown below. Please confirm the final values before verifying.';
                        compareBadge.textContent = latestComparisonRows.length + ' difference' + (latestComparisonRows.length > 1 ? 's' : '');
                        canFinalizeVerify = true;
                        if (updateBtn) {
                            updateBtn.disabled = false;
                        }
                    }

                    function collectFinalValues() {
                        var values = {};
                        modalElement.querySelectorAll('.sor-verify-final-value').forEach(function (field) {
                            values[field.getAttribute('data-field-name') || ''] = field.value;
                        });
                        return values;
                    }

                    function getEndpointUrl(orderId) {
                        return config.endpointTemplate.replace('__ORDER_ID__', encodeURIComponent(String(orderId || '0')));
                    }

                    function sendVerifyRequest(orderId, formData) {
                        return fetch(getEndpointUrl(orderId), {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function (response) {
                            return response.json();
                        });
                    }

                    function extractPdfClientText(file) {
                        if (!file || typeof pdfjsLib === 'undefined') {
                            return Promise.resolve('');
                        }

                        pdfjsLib.GlobalWorkerOptions.workerSrc = '../finance/header/js/pdf.worker.min.js';
                        return file.arrayBuffer().then(function (buffer) {
                            return pdfjsLib.getDocument({ data: buffer }).promise;
                        }).then(function (pdfDoc) {
                            var pagePromises = [];
                            for (var pageNumber = 1; pageNumber <= pdfDoc.numPages; pageNumber++) {
                                pagePromises.push(
                                    pdfDoc.getPage(pageNumber).then(function (page) {
                                        return page.getTextContent().then(function (textContent) {
                                            return textContent.items.map(function (item) {
                                                return String(item.str || '').trim();
                                            }).filter(Boolean).join(' ');
                                        });
                                    })
                                );
                            }
                            return Promise.all(pagePromises).then(function (pages) {
                                return pages.join("\n");
                            });
                        }).catch(function () {
                            return '';
                        });
                    }

                    function handleVerifySuccess(result) {
                        modalInstance.hide();
                        window.alert(result && result.message ? result.message : 'Order verified successfully.');
                        window.location.replace(config.redirectUrl || window.location.href);
                    }

                    document.querySelectorAll(config.triggerSelector).forEach(function (triggerButton) {
                        triggerButton.addEventListener('click', function () {
                            var orderId = triggerButton.getAttribute('data-order-id') || '';
                            var orderCode = triggerButton.getAttribute('data-order-code') || '';
                            var existingPdfPath = triggerButton.getAttribute('data-existing-pdf-path') || '';

                            if (!orderId) {
                                window.alert('Invalid order.');
                                return;
                            }

                            resetComparisonState();
                            setStatus('Only PDF file is allowed.', false);
                            if (fileInput) {
                                fileInput.value = '';
                            }
                            if (titleNode) {
                                titleNode.textContent = 'Verify Order - ' + (orderCode || ('#' + orderId));
                            }
                            if (orderIdField) {
                                orderIdField.value = orderId;
                            }
                            if (pdfPathField) {
                                pdfPathField.value = existingPdfPath;
                            }
                            if (pdfPathDisplay) {
                                pdfPathDisplay.value = existingPdfPath;
                            }
                            updatePdfPreview('');
                            showChoiceView();
                            modalInstance.show();
                        });
                    });

                    if (uploadChoiceBtn) {
                        uploadChoiceBtn.addEventListener('click', function () {
                            showPdfView();
                        });
                    }

                    if (backBtn) {
                        backBtn.addEventListener('click', function () {
                            showChoiceView();
                        });
                    }

                    if (compareBtn) {
                        compareBtn.addEventListener('click', function () {
                            var selectedFile = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                            var orderId = orderIdField ? orderIdField.value : '';
                            if (!orderId) {
                                setStatus('Invalid order.', true);
                                return;
                            }
                            if (!selectedFile) {
                                setStatus('Please choose the Shopee Order Detail PDF first.', true);
                                return;
                            }
                            if (!/\.pdf$/i.test(String(selectedFile.name || ''))) {
                                setStatus('Only PDF file is allowed.', true);
                                return;
                            }

                            setBusyState(true);
                            setStatus('Extracting PDF text and comparing...', false);
                            extractPdfClientText(selectedFile).then(function (clientPdfText) {
                                var formData = new FormData();
                                formData.append('sor_verify_order_action', 'compare_pdf');
                                formData.append('shopee_order_verify_pdf_csrf', csrfField ? csrfField.value : '');
                                formData.append('sor_order_detail_pdf_client_text', clientPdfText || '');
                                formData.append('sor_verify_redirect_url', config.redirectUrl || window.location.href);
                                formData.append('sor_order_detail_pdf', selectedFile);
                                return sendVerifyRequest(orderId, formData);
                            }).then(function (result) {
                                setBusyState(false);
                                if (!result || !result.success) {
                                    setStatus(result && result.message ? result.message : 'Failed to compare the uploaded PDF.', true);
                                    return;
                                }

                                setStatus(result.message || 'PDF compared successfully.', false);
                                resetLocalPreviewUrl();
                                localPreviewUrl = URL.createObjectURL(selectedFile);
                                updatePdfPreview(localPreviewUrl);
                                renderComparisonRows(result.comparison_rows || []);
                                if (reloadBtn) {
                                    reloadBtn.classList.remove('d-none');
                                }
                            }).catch(function () {
                                setBusyState(false);
                                setStatus('Failed to compare the uploaded PDF.', true);
                            });
                        });
                    }

                    if (reloadBtn) {
                        reloadBtn.addEventListener('click', function () {
                            if (compareBtn) {
                                compareBtn.click();
                            }
                        });
                    }

                    if (directChoiceBtn) {
                        directChoiceBtn.addEventListener('click', function () {
                            var orderId = orderIdField ? orderIdField.value : '';
                            if (!orderId) {
                                setStatus('Invalid order.', true);
                                return;
                            }
                            if (!window.confirm('Are you sure you want to verify this order?')) {
                                return;
                            }

                            setBusyState(true);
                            var formData = new FormData();
                            formData.append('sor_verify_order_action', 'direct_verified');
                            formData.append('shopee_order_verify_pdf_csrf', csrfField ? csrfField.value : '');
                            formData.append('sor_verify_redirect_url', config.redirectUrl || window.location.href);
                            sendVerifyRequest(orderId, formData).then(function (result) {
                                setBusyState(false);
                                if (!result || !result.success) {
                                    setStatus(result && result.message ? result.message : 'Failed to verify the order.', true);
                                    return;
                                }
                                handleVerifySuccess(result);
                            }).catch(function () {
                                setBusyState(false);
                                setStatus('Failed to verify the order.', true);
                            });
                        });
                    }

                    if (updateBtn) {
                        updateBtn.addEventListener('click', function () {
                            var orderId = orderIdField ? orderIdField.value : '';
                        if (!orderId) {
                            setStatus('Invalid order.', true);
                            return;
                        }
                        if (!(fileInput && fileInput.files && fileInput.files[0]) && (!pdfPathField || !pdfPathField.value)) {
                            setStatus('Please upload the Order Detail PDF first.', true);
                            return;
                        }

                            setBusyState(true);
                            var formData = new FormData();
                            formData.append('sor_verify_order_action', 'finalize_pdf_verified');
                            formData.append('shopee_order_verify_pdf_csrf', csrfField ? csrfField.value : '');
                            formData.append('sor_verify_pdf_path', pdfPathField.value || '');
                            formData.append('sor_verify_final_values', JSON.stringify(collectFinalValues()));
                            formData.append('sor_verify_redirect_url', config.redirectUrl || window.location.href);
                            if (fileInput && fileInput.files && fileInput.files[0]) {
                                formData.append('sor_order_detail_pdf', fileInput.files[0]);
                            }
                            sendVerifyRequest(orderId, formData).then(function (result) {
                                setBusyState(false);
                                if (!result || !result.success) {
                                    setStatus(result && result.message ? result.message : 'Failed to update and verify the order.', true);
                                    return;
                                }
                                handleVerifySuccess(result);
                            }).catch(function () {
                                setBusyState(false);
                                setStatus('Failed to update and verify the order.', true);
                            });
                        });
                    }

                    modalElement.addEventListener('hidden.bs.modal', function () {
                        setBusyState(false);
                        resetLocalPreviewUrl();
                        updatePdfPreview('');
                        showChoiceView();
                    });
                }

                document.addEventListener('DOMContentLoaded', bindVerifyModal);
            })();
        </script>
        <?php
    }
}
