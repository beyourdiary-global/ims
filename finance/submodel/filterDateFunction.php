<?php

function filterDateExtractDateValue($value) {
    $value = trim((string) $value);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function filterDateExtractMonthValue($value) {
    $value = trim((string) $value);

    if (preg_match('/^(\d{4}-\d{2})(?:-\d{2})?$/', $value, $matches)) {
        return $matches[1];
    }

    return '';
}

function filterDateExtractYearValue($value) {
    $value = trim((string) $value);

    if (preg_match('/^(\d{4})(?:-\d{2})?(?:-\d{2})?$/', $value, $matches)) {
        return $matches[1];
    }

    return '';
}

function filterDateSplitRange($range) {
    $range = trim((string) $range);

    if ($range === '') {
        return array('', '');
    }

    if (strpos($range, 'to') !== false) {
        $parts = explode('to', $range, 2);
        return array(trim($parts[0]), trim($parts[1]));
    }

    return array($range, $range);
}

function generateDateQuery($groupOption3, $groupOption4, $sqlNode) {
    $sqlQuery = "";

    if ($groupOption4 == "monthly") {
        list($start, $end) = filterDateSplitRange($groupOption3);
        $startMonth = filterDateExtractMonthValue($start);
        $endMonth = filterDateExtractMonthValue($end);

        if ($startMonth !== '' && $endMonth !== '') {
            if ($startMonth > $endMonth) {
                list($startMonth, $endMonth) = array($endMonth, $startMonth);
            }

            if ($startMonth === $endMonth) {
                $sqlQuery = "DATE_FORMAT(`$sqlNode`, '%Y-%m') = '$startMonth'";
            } else {
                $sqlQuery = "DATE_FORMAT(`$sqlNode`, '%Y-%m') BETWEEN '$startMonth' AND '$endMonth'";
            }
        }
    } elseif ($groupOption4 == "yearly") {
        list($start, $end) = filterDateSplitRange($groupOption3);
        $startYear = filterDateExtractYearValue($start);
        $endYear = filterDateExtractYearValue($end);

        if ($startYear !== '' && $endYear !== '') {
            if ($startYear > $endYear) {
                list($startYear, $endYear) = array($endYear, $startYear);
            }

            if ($startYear === $endYear) {
                $sqlQuery = "YEAR(`$sqlNode`) = $startYear";
            } else {
                $sqlQuery = "YEAR(`$sqlNode`) BETWEEN $startYear AND $endYear";
            }
        }
    } elseif ($groupOption4 == "daily") {
        list($start, $end) = filterDateSplitRange($groupOption3);
        $dateValue = filterDateExtractDateValue($start);

        if ($dateValue === '') {
            $dateValue = filterDateExtractDateValue($end);
        }

        if ($dateValue !== '') {
            $sqlQuery = "`$sqlNode` = '$dateValue'";
        }
    } elseif ($groupOption4 == "weekly") {
        list($start, $end) = filterDateSplitRange($groupOption3);
        $startDate = filterDateExtractDateValue($start);
        $endDate = filterDateExtractDateValue($end);

        if ($startDate !== '' && $endDate !== '') {
            if ($startDate > $endDate) {
                list($startDate, $endDate) = array($endDate, $startDate);
            }

            $sqlQuery = "`$sqlNode` BETWEEN '$startDate' AND '$endDate'";
        }
    }

    if ($sqlQuery === "") {
        $sqlQuery = "1 = 0";
    }

    return $sqlQuery;
}

?>
