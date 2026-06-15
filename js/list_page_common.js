'use strict';

(function () {
    function runWhenReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }

        callback();
    }

    function initDefaultSortingTable() {
        var table = document.getElementById('table');

        if (!table) {
            return;
        }

        if (table.dataset.listPageSortingInitialized === '1') {
            return;
        }

        if (typeof window.createSortingTable !== 'function') {
            return;
        }

        table.dataset.listPageSortingInitialized = '1';
        window.createSortingTable('table');
    }

    runWhenReady(function () {
        initDefaultSortingTable();
    });
})();