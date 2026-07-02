<?php

if (!function_exists('luckyDrawAdminFlashSet')) {
    function luckyDrawAdminFlashSet($type, $message)
    {
        $_SESSION['lucky_draw_admin_flash'] = array(
            'type' => trim((string) $type),
            'message' => trim((string) $message),
        );
    }
}

if (!function_exists('luckyDrawAdminFlashGet')) {
    function luckyDrawAdminFlashGet()
    {
        $flash = isset($_SESSION['lucky_draw_admin_flash']) && is_array($_SESSION['lucky_draw_admin_flash'])
            ? $_SESSION['lucky_draw_admin_flash']
            : array();
        unset($_SESSION['lucky_draw_admin_flash']);
        return $flash;
    }
}

if (!function_exists('luckyDrawAdminRedirect')) {
    function luckyDrawAdminRedirect($route, $params = array(), $flashType = '', $flashMessage = '')
    {
        if ($flashType !== '' && $flashMessage !== '') {
            luckyDrawAdminFlashSet($flashType, $flashMessage);
        }

        $url = siteUrlWithQuery($route, is_array($params) ? $params : array());
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<script>location.href=' . json_encode($url) . ';</script>';
        }
        exit;
    }
}

if (!function_exists('luckyDrawAdminOptionRows')) {
    function luckyDrawAdminOptionRows($connect, $tableName, $labelField = 'name', $options = array())
    {
        $rows = array();
        if (!($connect instanceof mysqli) || trim((string) $tableName) === '') {
            return $rows;
        }

        $options = is_array($options) ? $options : array();
        $valueField = isset($options['value_field']) ? trim((string) $options['value_field']) : 'id';
        $whereSql = isset($options['where_sql']) ? trim((string) $options['where_sql']) : "status = 'A'";
        $orderBy = isset($options['order_by']) ? trim((string) $options['order_by']) : $labelField . " ASC";
        $extraFields = isset($options['extra_fields']) && is_array($options['extra_fields']) ? $options['extra_fields'] : array();

        $fields = array_unique(array_merge(array($valueField, $labelField), $extraFields));
        $safeFields = array();
        foreach ($fields as $fieldName) {
            $fieldName = trim((string) $fieldName);
            if ($fieldName !== '') {
                $safeFields[] = "`" . str_replace('`', '``', $fieldName) . "`";
            }
        }

        if (empty($safeFields)) {
            return $rows;
        }

        $sql = "SELECT " . implode(', ', $safeFields) . " FROM `" . str_replace('`', '``', $tableName) . "` WHERE " . $whereSql . " ORDER BY " . $orderBy;
        $result = mysqli_query($connect, $sql);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = (array) $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('luckyDrawAdminShortHash')) {
    function luckyDrawAdminShortHash($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 12) . '...';
    }
}

if (!function_exists('luckyDrawAdminRenderPageStart')) {
    function luckyDrawAdminRenderPageStart($pageTitle, $activeKey)
    {
        $flash = luckyDrawAdminFlashGet();
        $sectionLabel = preg_replace('/^Lucky Draw\s*-\s*/i', '', (string) $pageTitle);
        $sectionLabel = trim((string) $sectionLabel);
        if ($sectionLabel === '') {
            $sectionLabel = (string) $pageTitle;
        }
        ?>
        <style>
            .lucky-draw-admin-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 16px;
            }
            .lucky-draw-stat-card {
                background: #fff;
                border: 1px solid #dee2e6;
                padding: 18px;
            }
            .lucky-draw-stat-card h3 {
                font-size: 2rem;
                margin: 4px 0 0;
                color: #000;
            }
            .lucky-draw-card {
                background: #fff;
                border: 1px solid #dee2e6;
                padding: 20px;
            }
            .lucky-draw-admin-table td,
            .lucky-draw-admin-table th {
                vertical-align: middle;
            }

        </style>
        <div class="page-load-cover">
            <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
                <div class="col-12 col-md-11">
                    <div class="d-flex flex-column mb-3">
                        <div class="row">
                            <p>
                                <a href="<?= htmlspecialchars(siteUrlPath(ROUTE_DASHBOARD), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
                                <i class="fa-solid fa-chevron-right fa-xs"></i>
                                <a href="<?= htmlspecialchars(siteUrlPath(ROUTE_LUCKY_DRAW_ADMIN_DASHBOARD), ENT_QUOTES, 'UTF-8') ?>">Lucky Draw</a>
                                <?php if ($activeKey !== 'dashboard') { ?>
                                    <i class="fa-solid fa-chevron-right fa-xs"></i>
                                    <?= htmlspecialchars($sectionLabel, ENT_QUOTES, 'UTF-8') ?>
                                <?php } ?>
                            </p>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($flash['message'])) { ?>
                        <div class="alert alert-<?= htmlspecialchars((string) (isset($flash['type']) ? $flash['type'] : 'info'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php } ?>
        <?php
    }
}

if (!function_exists('luckyDrawAdminRenderPageEnd')) {
    function luckyDrawAdminRenderPageEnd()
    {
        ?>
                </div>
            </div>
        </div>
        <?php
    }
}
