<?php
$pageTitle = "Campaign Rule Setting";
$currentPagePin = 154;

include_once 'include/list_page_header.php';
include_once ROOT . '/include/campaign_common.php';


if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$redirect_page = $SITEURL . '/campaign_rule_setting.php';
$deleteRedirectPage = $SITEURL . '/campaign_rule_setting_table.php';
$ruleRows = campaignRuleSettingFetchRows($connect, array());
$userOptions = campaignFetchUsers($connect);
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
        .campaign-rule-setting-table {
            width: 100%;
        }

        .campaign-rule-setting-table th,
        .campaign-rule-setting-table td {
            vertical-align: top;
        }

        .campaign-rule-setting-table thead th,
        .campaign-rule-setting-table tfoot th {
            white-space: nowrap;
            overflow-wrap: normal;
            word-break: keep-all;
        }

        .campaign-rule-setting-table tbody td,
        .campaign-rule-setting-table tbody th {
            white-space: normal;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .campaign-rule-setting-table .btn-container {
            white-space: nowrap;
        }

        .campaign-rule-setting-table th:nth-child(2),
        .campaign-rule-setting-table td:nth-child(2) {
            min-width: 70px;
        }

        .campaign-rule-setting-table th:nth-child(9),
        .campaign-rule-setting-table td:nth-child(9) {
            min-width: 90px;
        }

        .campaign-rule-setting-table th:nth-child(11),
        .campaign-rule-setting-table td:nth-child(11) {
            min-width: 110px;
        }

        .campaign-rule-condition-cell {
            max-width: 320px;
        }

        .campaign-rule-template-cell,
        .campaign-rule-default-pic-cell,
        .campaign-rule-remark-cell {
            max-width: 220px;
        }

        .campaign-rule-last-generated-cell {
            max-width: 140px;
        }
    </style>
</head>

<script>
    

    $(document).ready(() => {
        createSortingTable('campaign_rule_setting_table', { searching: true });
    });
</script>

<body>
    

    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p>
                            <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                            <i class="fa-solid fa-chevron-right fa-xs"></i>
                            <?= campaignH($pageTitle) ?>
                        </p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= campaignH($pageTitle) ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed('Add', $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirect_page ?>?act=<?= $act_1 ?>">
                                        <i class="fa-solid fa-plus"></i> Add Rule
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="table table-striped campaign-rule-setting-table" id="campaign_rule_setting_table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Rule Name</th>
                            <th scope="col">Generate Schedule</th>
                            <th scope="col">Campaign Name Template</th>
                            <th scope="col">Customer Target Rules</th>
                            <th scope="col">Default PIC</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Generated</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $num = 1; ?>
                        <?php foreach ($ruleRows as $row): ?>
                            <?php
                            $ruleId = isset($row['id']) ? (int) $row['id'] : 0;
                            $defaultPicIds = campaignRuleDecodeJson($row['default_pic_json'] ?? '', array());
                            $scheduleText = trim((string) ($row['generate_schedule'] ?? ''));
                            $generateDay = trim((string) ($row['generate_day'] ?? ''));
                            if ($generateDay !== '') {
                                $scheduleText .= ' ' . $generateDay;
                            }
                            $conditionSummary = campaignRuleConditionSummaryText($connect, $row['customer_condition_json'] ?? '');
                            $lastGeneratedText = trim((string) ($row['last_generated_date'] ?? '') . ' ' . (string) ($row['last_generated_time'] ?? ''));
                            ?>
                            <tr>
                                <th class="hideColumn" scope="row"><?= $ruleId ?></th>
                                <th scope="row"><?= $num++; ?></th>
                                <td scope="row" class="btn-container">
                                    <?php renderViewEditButton('View', $redirect_page, $row, $pinAccess); ?>
                                    <?php renderViewEditButton('Edit', $redirect_page, $row, $pinAccess, $act_2); ?>
                                    <?php renderDeleteButton($pinAccess, $ruleId, $row['rule_name'] ?? '', $row['remark'] ?? '', $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                </td>
                                <td scope="row"><?= campaignH($row['rule_name'] ?? '') ?></td>
                                <td scope="row"><?= campaignH($scheduleText) ?></td>
                                <td scope="row" class="campaign-rule-template-cell"><?= campaignH($row['campaign_name_template'] ?? '') ?></td>
                                <td scope="row" class="campaign-rule-condition-cell"><?= campaignH($conditionSummary) ?></td>
                                <td scope="row" class="campaign-rule-default-pic-cell"><?= campaignH(campaignRuleSettingUserNames($userOptions, $defaultPicIds)) ?></td>
                                <td scope="row"><?= campaignH($row['rule_status'] ?? '') ?></td>
                                <td scope="row" class="campaign-rule-last-generated-cell"><?= campaignH($lastGeneratedText) ?></td>
                                <td scope="row" class="campaign-rule-remark-cell"><?= campaignH($row['remark'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Rule Name</th>
                            <th scope="col">Generate Schedule</th>
                            <th scope="col">Campaign Name Template</th>
                            <th scope="col">Customer Target Rules</th>
                            <th scope="col">Default PIC</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Generated</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        const page = "<?= campaignH($pageTitle) ?>";
        const action = "View";

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('campaign_rule_setting_table');
        setButtonColor();
    </script>
    <?php campaignRenderPopupScript($pageTitle, $deleteRedirectPage); ?>
</body>

</html>
