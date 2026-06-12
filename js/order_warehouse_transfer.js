'use strict';

(function () {
    const config = window.orderWarehouseTransferConfig || {};

    function getJquery() {
        return window.jQuery || window.$ || null;
    }

    function cleanupModalState() {
        const $ = getJquery();
        if ($) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css({
                overflow: '',
                'padding-right': '',
            });
        }

        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    function hideModal(modalElement) {
        const $ = getJquery();

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
                modalInstance.hide();
                return;
            }
        }

        if ($ && $.fn.modal) {
            $(modalElement).modal('hide');
            return;
        }

        modalElement.classList.remove('show');
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.removeAttribute('aria-modal');
        modalElement.style.display = 'none';
        cleanupModalState();
    }

    function showModal(modalElement) {
        const $ = getJquery();

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
            modalInstance.show();
            return;
        }

        if ($ && $.fn.modal) {
            $(modalElement).modal('show');
            return;
        }

        modalElement.style.display = 'block';
        modalElement.removeAttribute('aria-hidden');
        modalElement.setAttribute('aria-modal', 'true');
        modalElement.classList.add('show');
    }

    function initTransferLogTable() {
        const $ = getJquery();
        if (!$ || !$.fn.DataTable) {
            return;
        }

        const table = $('#owtTransferLogTable');
        if (!table.length || $.fn.DataTable.isDataTable(table)) {
            return;
        }

        table.DataTable({
            pageLength: 10,
            order: [[6, 'desc']],
        });
    }

    function initSearchResultModal() {
        const modalElement = document.getElementById('owtSearchResultModal');
        const $ = getJquery();

        if (!modalElement) {
            cleanupModalState();
            return;
        }

        modalElement.querySelectorAll('.owt-modal-close').forEach((closeButton) => {
            closeButton.addEventListener('click', () => {
                hideModal(modalElement);
                window.setTimeout(cleanupModalState, 250);
            });
        });

        if ($) {
            $(modalElement).off('hidden.bs.modal.owt').on('hidden.bs.modal.owt', cleanupModalState);
        }

        if (config.shouldOpenSearchModal) {
            showModal(modalElement);
        } else {
            cleanupModalState();
        }
    }

    function initFlashPopup() {
        const flashPopupMessage = typeof config.flashPopupMessage === 'string' ? config.flashPopupMessage : '';
        const flashPopupAct = typeof config.flashPopupAct === 'string' ? config.flashPopupAct : '';
        const flashPopupReturnUrl = typeof config.flashPopupReturnUrl === 'string' ? config.flashPopupReturnUrl : '';

        if (flashPopupMessage === '' || flashPopupAct === '' || typeof confirmationDialog !== 'function') {
            return;
        }

        if (flashPopupAct === 'E') {
            confirmationDialog('', ['Warehouse transfer successful'], '', '', flashPopupReturnUrl, 'ErrMO');
            return;
        }

        confirmationDialog('', flashPopupMessage, '', '', flashPopupReturnUrl, 'ErrMO');
    }

        function initSearchFormValidation() {
        const searchForm = document.getElementById('owtSearchForm');
        const orderCodeInput = document.getElementById('order_code');
        const requiredMessage = document.getElementById('owtOrderCodeRequiredMsg');
        const noResultMessage = document.getElementById('owtOrderCodeNoResultMsg');

        if (!searchForm || !orderCodeInput || !requiredMessage) {
            return;
        }

        searchForm.addEventListener('submit', (event) => {
            if (orderCodeInput.value.trim() !== '') {
                requiredMessage.classList.add('d-none');
                return;
            }

            event.preventDefault();
            requiredMessage.classList.remove('d-none');

            if (noResultMessage) {
                noResultMessage.classList.add('d-none');
            }

            orderCodeInput.focus();
        });

        orderCodeInput.addEventListener('input', () => {
            if (orderCodeInput.value.trim() !== '') {
                requiredMessage.classList.add('d-none');
            }

            if (noResultMessage) {
                noResultMessage.classList.add('d-none');
            }
        });
    }

    function initPage() {
        if (typeof preloader === 'function') {
            preloader(300);
        }

        if (typeof checkCurrentPage === 'function') {
            checkCurrentPage(config.pageTitle || '', '');
        }

        if (typeof dropdownMenuDispFix === 'function') {
            dropdownMenuDispFix();
        }

        if (typeof setButtonColor === 'function') {
            setButtonColor();
        }

        initSearchFormValidation();
        initTransferLogTable();
        initSearchResultModal();
        initFlashPopup();
    }

    const $ = getJquery();
    if ($) {
        $(document).ready(initPage);
    } else {
        document.addEventListener('DOMContentLoaded', initPage);
    }
}());