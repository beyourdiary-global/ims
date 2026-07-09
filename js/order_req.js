// export notification and report date/group filter handling
(function ($) {
    if (typeof $ === 'undefined') {
        return;
    }

    function getQueryParam(name) {
        if (typeof window.getParameterByName === 'function') {
            return window.getParameterByName(name);
        }

        try {
            return new URLSearchParams(window.location.search).get(name);
        } catch (error) {
            return null;
        }
    }

    function hasElement(selector) {
        return $(selector).length > 0;
    }

    function getValue(selector) {
        var element = $(selector);

        if (!element.length) {
            return '';
        }

        return element.val() || '';
    }

    function setValue(selector, value) {
        var element = $(selector);

        if (!element.length) {
            return;
        }

        element.val(value);
    }

    function getCurrentDateString() {
        var now = new Date();
        var year = now.getFullYear();
        var month = String(now.getMonth() + 1).padStart(2, '0');
        var day = String(now.getDate()).padStart(2, '0');

        return year + '-' + month + '-' + day;
    }

    function getCurrentMonthString() {
        return getCurrentDateString().slice(0, 7);
    }

    function getCurrentYearString() {
        return getCurrentDateString().slice(0, 4);
    }

    function getFirstMatchingValue(values, matcher) {
        for (var index = 0; index < values.length; index += 1) {
            var value = values[index];

            if (matcher.test(value || '')) {
                return value;
            }
        }

        return '';
    }

    function normalizeTimeRange(timeRange, timeInterval) {
        var rawValue = (timeRange || '').trim();
        var rangeParts = rawValue.indexOf('to') !== -1 ? rawValue.split('to') : [rawValue, rawValue];
        var leftValue = (rangeParts[0] || '').trim();
        var rightValue = (rangeParts[1] || '').trim();
        var currentDate = getCurrentDateString();
        var currentMonth = getCurrentMonthString();
        var currentYear = getCurrentYearString();

        if (timeInterval === 'daily') {
            return getFirstMatchingValue([leftValue, rightValue, currentDate], /^\d{4}-\d{2}-\d{2}$/);
        }

        if (timeInterval === 'weekly') {
            var startDate = getFirstMatchingValue([leftValue, rightValue, currentDate], /^\d{4}-\d{2}-\d{2}$/);
            var endDate = getFirstMatchingValue([rightValue, leftValue, startDate], /^\d{4}-\d{2}-\d{2}$/);

            return startDate + 'to' + endDate;
        }

        if (timeInterval === 'monthly') {
            var startMonth = getFirstMatchingValue([leftValue.slice(0, 7), rightValue.slice(0, 7), currentMonth], /^\d{4}-\d{2}$/);
            var endMonth = getFirstMatchingValue([rightValue.slice(0, 7), leftValue.slice(0, 7), startMonth], /^\d{4}-\d{2}$/);

            return startMonth + 'to' + endMonth;
        }

        if (timeInterval === 'yearly') {
            var startYear = getFirstMatchingValue([leftValue.slice(0, 4), rightValue.slice(0, 4), currentYear], /^\d{4}$/);
            var endYear = getFirstMatchingValue([rightValue.slice(0, 4), leftValue.slice(0, 4), startYear], /^\d{4}$/);

            return startYear + 'to' + endYear;
        }

        return '';
    }

    function populateDateInputs(timeRange, timeInterval) {
        var normalizedRange = normalizeTimeRange(timeRange, timeInterval);

        if (timeInterval === 'weekly') {
            var weeklyRange = normalizedRange.split('to');
            setValue('#datepicker2 input[name="start"]', weeklyRange[0] || '');
            setValue('#datepicker2 input[name="end"]', weeklyRange[1] || '');
        } else if (timeInterval === 'monthly') {
            var monthlyRange = normalizedRange.split('to');
            setValue('#datepicker3 input[name="start"]', monthlyRange[0] || '');
            setValue('#datepicker3 input[name="end"]', monthlyRange[1] || '');
        } else if (timeInterval === 'yearly') {
            var yearlyRange = normalizedRange.split('to');
            setValue('#datepicker4 input[name="start"]', yearlyRange[0] || '');
            setValue('#datepicker4 input[name="end"]', yearlyRange[1] || '');
        } else {
            setValue('#datepicker input', normalizedRange);
        }

        setValue('#timeRangeParam', normalizedRange);
        setValue('#timeIntervalParam', timeInterval);

        return normalizedRange;
    }

    function buildDateRange(timeInterval) {
        var time = getValue('#datepicker input');
        var startDate = getValue('#datepicker2 input[name="start"]');
        var endDate = getValue('#datepicker2 input[name="end"]');
        var startMonth = getValue('#datepicker3 input[name="start"]');
        var endMonth = getValue('#datepicker3 input[name="end"]');
        var startYear = getValue('#datepicker4 input[name="start"]');
        var endYear = getValue('#datepicker4 input[name="end"]');

        if (timeInterval === 'weekly') {
            return startDate + 'to' + endDate;
        }

        if (timeInterval === 'monthly') {
            return startMonth + 'to' + endMonth;
        }

        if (timeInterval === 'yearly') {
            return startYear + 'to' + endYear;
        }

        if (timeInterval === 'daily') {
            return time;
        }

        return '';
    }

    function updateWindowSearch(group, group2, timeRange, timeInterval, ids, key) {
        var queryParts = [];

        if (group) {
            queryParts.push('group=' + encodeURIComponent(group));
        }

        if (group2) {
            queryParts.push('group2=' + encodeURIComponent(group2));
        }

        if (timeRange) {
            queryParts.push('timeRange=' + encodeURIComponent(timeRange));
        }

        if (timeInterval) {
            queryParts.push('timeInterval=' + encodeURIComponent(timeInterval));
        }

        if (ids) {
            queryParts.push('ids=' + encodeURIComponent(ids));
        }

        if (key) {
            queryParts.push('key=' + encodeURIComponent(key));
        }

        if (queryParts.length > 0) {
            window.location.search = '?' + queryParts.join('&');
        }
    }

    function handleTimeIntervalChange() {
        var selectedOption = getValue('#timeInterval');

        $('#datepicker, #datepicker2, #datepicker3, #datepicker4').prop('disabled', true).hide();

        if (window.location.pathname === '/fb_order_req_income_table_summary.php') {
            $('#timeInterval').prop('disabled', true).show();

            if (selectedOption === 'daily') {
                $('#datepicker').prop('disabled', true).show();
            } else if (selectedOption === 'weekly') {
                $('#datepicker2').prop('disabled', true).show();
            } else if (selectedOption === 'monthly') {
                $('#datepicker3').prop('disabled', true).show();
            } else if (selectedOption === 'yearly') {
                $('#datepicker4').prop('disabled', true).show();
            }

            return;
        }

        if (selectedOption === 'daily') {
            $('#datepicker').prop('disabled', false).show();
        } else if (selectedOption === 'weekly') {
            $('#datepicker2').prop('disabled', false).show();
        } else if (selectedOption === 'monthly') {
            $('#datepicker3').prop('disabled', false).show();
        } else if (selectedOption === 'yearly') {
            $('#datepicker4').prop('disabled', false).show();
        }
    }

    function initDatepickers() {
        if (!$.fn || typeof $.fn.datepicker !== 'function') {
            return;
        }

        if (hasElement('#datepicker')) {
            $('#datepicker').datepicker({
                autoclose: true,
                format: 'yyyy-mm-dd',
                weekStart: 1,
                maxViewMode: 0,
                minViewMode: 0,
                todayHighlight: true,
                toggleActive: true,
                orientation: 'bottom left'
            });
        }

        if (hasElement('#datepicker2 input[name="start"]')) {
            $('#datepicker2 input[name="start"]').datepicker({
                autoclose: true,
                format: 'yyyy-mm-dd',
                weekStart: 1,
                maxViewMode: 1,
                todayHighlight: true,
                toggleActive: true,
                orientation: 'bottom'
            });
        }

        if (hasElement('#datepicker2 input[name="end"]')) {
            $('#datepicker2 input[name="end"]').datepicker({
                autoclose: true,
                format: 'yyyy-mm-dd',
                weekStart: 1,
                maxViewMode: 1,
                todayHighlight: true,
                toggleActive: true,
                orientation: 'bottom'
            });
        }

        if (hasElement('#datepicker3 input[name="start"]')) {
            $('#datepicker3 input[name="start"]').datepicker({
                format: 'yyyy-mm',
                minViewMode: 1,
                autoclose: true,
                orientation: 'bottom'
            });
        }

        if (hasElement('#datepicker3 input[name="end"]')) {
            $('#datepicker3 input[name="end"]').datepicker({
                format: 'yyyy-mm',
                minViewMode: 1,
                autoclose: true,
                orientation: 'bottom'
            });
        }

        if (hasElement('#datepicker4 input[name="start"]')) {
            $('#datepicker4 input[name="start"]').datepicker({
                format: 'yyyy',
                minViewMode: 2,
                autoclose: true,
                orientation: 'bottom'
            });
        }

        if (hasElement('#datepicker4 input[name="end"]')) {
            $('#datepicker4 input[name="end"]').datepicker({
                format: 'yyyy',
                minViewMode: 2,
                autoclose: true,
                orientation: 'bottom'
            });
        }
    }

    $(document).ready(function () {
        if (!hasElement('#timeInterval') || !hasElement('#group')) {
            return;
        }

        var timeParam = getQueryParam('timeInterval');
        var groupParam = getQueryParam('group');
        var timeRangeParam = getQueryParam('timeRange');
        var ids = getQueryParam('ids');
        var key = getQueryParam('key');
        var currentDate = getCurrentDateString();

        $('#resetButton').off('click.orderReq').on('click.orderReq', function () {
            $('#datepicker input, #datepicker2 input[name="start"], #datepicker2 input[name="end"], #datepicker3 input[name="start"], #datepicker3 input[name="end"], #datepicker4 input[name="start"], #datepicker4 input[name="end"]').val('');
            $('#group').val('');
            $('#timeInterval').val('');
            $('#datepicker input').change();
        });

        if (!getValue('#datepicker input')) {
            setValue('#datepicker input', currentDate);
        }

        if (!timeParam) {
            timeParam = 'daily';
        }

        setValue('#timeInterval', timeParam);

        if (groupParam) {
            setValue('#group', groupParam);
        }

        if (hasElement('#group2') && getValue('#group2') === '') {
            setValue('#group2', getQueryParam('group2') || '');
        }

        if (!window.location.search) {
            updateWindowSearch(getValue('#group'), '', getValue('#datepicker input'), getValue('#timeInterval'), '', '');
            return;
        }

        timeRangeParam = populateDateInputs(timeRangeParam || currentDate, timeParam);

        handleTimeIntervalChange();
        initDatepickers();

        $('#datepicker input, #datepicker2 input[name="end"], #datepicker3 input[name="end"], #datepicker4 input[name="end"]')
            .off('change.orderReq')
            .on('change.orderReq', function () {
                var group = getValue('#group');
                var group2 = getValue('#group2');
                var timeInterval = getValue('#timeInterval');
                var timeRange = buildDateRange(timeInterval);

                updateWindowSearch(group, group2, timeRange, timeInterval, ids, key);
            });

        $('#group').off('change.orderReq').on('change.orderReq', function () {
            var group = getValue('#group');
            var group2 = getValue('#group2');
            var timeInterval = getValue('#timeInterval');
            var timeRange = buildDateRange(timeInterval);

            updateWindowSearch(group, group2, timeRange, timeInterval, ids, key);
        });

        $('#group2').off('change.orderReq').on('change.orderReq', function () {
            var group = getValue('#group') || groupParam;
            var group2 = getValue('#group2');
            var timeInterval = getValue('#timeInterval');
            var timeRange = buildDateRange(timeInterval);

            updateWindowSearch(group, group2, timeRange, timeInterval, ids, key);
        });

        $('#timeInterval').off('change.orderReq').on('change.orderReq', function () {
            var group = getValue('#group') || groupParam;
            var group2 = getValue('#group2');
            var timeInterval = getValue('#timeInterval');
            var fallbackTimeRange = buildDateRange(timeParam) || timeRangeParam || getValue('#datepicker input') || currentDate;

            timeRangeParam = populateDateInputs(fallbackTimeRange, timeInterval);
            handleTimeIntervalChange();
            updateWindowSearch(group, group2, timeRangeParam, timeInterval, ids, key);
        });
    });
})(window.jQuery);
