<?php
$pageTitle = "Campaign";
$currentPagePin = 153;

include_once '../include/list_page_header.php';
include_once ROOT . '/include/campaign_common.php';

$tblName = CAMPAIGN;
$redirectPage = $SITEURL . '/campaign/campaign.php';
$deleteRedirectPage = $SITEURL . '/campaign/campaign_table.php';

if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

if (empty($_SESSION['campaign_csrf_token'])) {
    $_SESSION['campaign_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['campaign_csrf_token'];

function campaignTableAudit($connect, $pageTitle, $action, $message, $query = '')
{
    audit_log(array(
        'log_act' => $action,
        'cdate' => date_dis,
        'ctime' => time_dis,
        'uid' => USER_ID,
        'cby' => USER_ID,
        'act_msg' => $message,
        'query_rec' => $query,
        'query_table' => CAMPAIGN,
        'page' => $pageTitle,
        'connect' => $connect,
    ));
}

$campaignTableDeleteRequested = post('actionBtn') === 'deleteCampaign' || post('act') === 'D';
$campaignTableDeleteUsesCommonDialog = post('act') === 'D';
if ($campaignTableDeleteRequested) {
    $deleteId = 0;
    $postedToken = '';

    if ($campaignTableDeleteUsesCommonDialog) {
        $deletePayloadParts = explode('|', trim((string) post('id')), 2);
        $deleteId = isset($deletePayloadParts[0]) ? (int) $deletePayloadParts[0] : 0;
        $postedToken = isset($deletePayloadParts[1]) ? (string) $deletePayloadParts[1] : '';
    } else {
        $deleteId = (int) post('id');
        $postedToken = (string) post('csrf_token');
    }

    if (!hash_equals($csrfToken, $postedToken) || !isActionAllowed('Delete', $pinAccess)) {
        if ($campaignTableDeleteUsesCommonDialog) {
            http_response_code(403);
            echo 'Unable to delete Campaign.';
            exit();
        }
        campaignSetPopup('Unable to delete Campaign.', $deleteRedirectPage, 'ErrMO');
    } elseif ($deleteId > 0) {
        $safeDeleteId = (int) $deleteId;
        $campaignName = '';
        $nameResult = $connect->query("SELECT `campaign_name` FROM `" . CAMPAIGN . "` WHERE `id` = " . $safeDeleteId . " AND `status` = 'A' LIMIT 1");
        if ($nameResult && $nameResult->num_rows > 0) {
            $nameRow = $nameResult->fetch_assoc();
            $campaignName = isset($nameRow['campaign_name']) ? (string) $nameRow['campaign_name'] : '';
        }

        $deleteSql = "UPDATE `" . CAMPAIGN . "` SET `status` = 'D', `update_by` = '" . $connect->real_escape_string((string) USER_ID) . "', `update_date` = CURDATE(), `update_time` = CURTIME() WHERE `id` = " . $safeDeleteId . " AND `status` = 'A'";
        if ($connect->query($deleteSql)) {
            campaignTableAudit($connect, $pageTitle, 'delete', USER_NAME . " deleted the data [<b> ID = " . $safeDeleteId . "</b> ] <b>" . campaignH($campaignName) . "</b> from <b><i>" . CAMPAIGN . " Table</i></b>.", $deleteSql);
            if ($campaignTableDeleteUsesCommonDialog) {
                echo 'OK';
                exit();
            }
            campaignSetPopup('Successful Delete Campaign', $deleteRedirectPage, 'ErrMO');
        } else {
            campaignTableAudit($connect, $pageTitle, 'delete', USER_NAME . " failed to delete the data [<b> ID = " . $safeDeleteId . "</b> ] from <b><i>" . CAMPAIGN . " Table</i></b>.", $deleteSql);
            if ($campaignTableDeleteUsesCommonDialog) {
                http_response_code(500);
                echo 'Failed to delete Campaign.';
                exit();
            }
            campaignSetPopup('Failed to delete Campaign.', $deleteRedirectPage, 'ErrMO');
        }
    }

    echo '<script>location.href = "' . $deleteRedirectPage . '";</script>';
    exit();
}

$filterCampaignName = trim((string) input('campaign_name'));
$filterPeriodStart = trim((string) input('period_start_date'));
$filterPeriodEnd = trim((string) input('period_end_date'));
$filterPic = (int) input('pic_user_id');
$filterAction = trim((string) input('filter_action'));

if ($filterAction === 'search') {
    campaignTableAudit($connect, $pageTitle, 'search', USER_NAME . " searched Campaign records.");
} elseif ($filterAction === 'reset') {
    campaignTableAudit($connect, $pageTitle, 'reset', USER_NAME . " reset Campaign filters.");
}

$where = array("c.`status` = 'A'");
$paramTypes = '';
$paramValues = array();

if ($filterCampaignName !== '') {
    $where[] = "c.`campaign_name` LIKE ?";
    $paramTypes .= 's';
    $paramValues[] = '%' . $filterCampaignName . '%';
}

if ($filterPeriodStart !== '') {
    $where[] = "c.`period_end_date` >= ?";
    $paramTypes .= 's';
    $paramValues[] = $filterPeriodStart;
}

if ($filterPeriodEnd !== '') {
    $where[] = "c.`period_start_date` <= ?";
    $paramTypes .= 's';
    $paramValues[] = $filterPeriodEnd;
}

if ($filterPic > 0) {
    $where[] = "EXISTS (SELECT 1 FROM `" . CAMPAIGN_PIC . "` cp_filter WHERE cp_filter.`campaign_id` = c.`id` AND cp_filter.`user_id` = ? AND cp_filter.`status` = 'A')";
    $paramTypes .= 'i';
    $paramValues[] = $filterPic;
}

$campaignRows = array();
// NOTE: each fan-out table (PIC/CUSTOMER/FOLLOW_UP/PURCHASE_RECORD) is aggregated
// per campaign_id in its own subquery before joining. Joining all four raw tables
// directly to `c` and then GROUP BY c.id (the previous approach) forces MySQL to
// build the full cross-product of matching rows across all four tables per
// campaign before it can COUNT(DISTINCT ...) them apart -- for a campaign with a
// few hundred customers, follow-ups, and purchase records this is a combinatorial
// explosion of intermediate rows, and is the main reason this page loads slowly.
$campaignSql = "SELECT
        c.*,
        pic.pic_names AS pic_names,
        COALESCE(cust.total_participants, 0) AS total_participants,
        COALESCE(fu.total_follow_up, 0) AS total_follow_up,
        COALESCE(fu.completed_follow_up, 0) AS completed_follow_up,
        COALESCE(pur.purchased_customers, 0) AS purchased_customers
    FROM `" . CAMPAIGN . "` c
    LEFT JOIN (
        SELECT
            cp.`campaign_id`,
            GROUP_CONCAT(DISTINCT COALESCE(NULLIF(u.`name`, ''), NULLIF(u.`username`, ''), cp.`user_id`) ORDER BY u.`name` SEPARATOR ', ') AS pic_names
        FROM `" . CAMPAIGN_PIC . "` cp
        LEFT JOIN `" . USR_USER . "` u ON u.`id` = cp.`user_id`
        WHERE cp.`status` = 'A'
        GROUP BY cp.`campaign_id`
    ) pic ON pic.`campaign_id` = c.`id`
    LEFT JOIN (
        SELECT `campaign_id`, COUNT(*) AS total_participants
        FROM `" . CAMPAIGN_CUSTOMER . "`
        WHERE `status` = 'A'
        GROUP BY `campaign_id`
    ) cust ON cust.`campaign_id` = c.`id`
    LEFT JOIN (
        SELECT
            `campaign_id`,
            COUNT(*) AS total_follow_up,
            COUNT(CASE WHEN `follow_up_status` = 'Completed' THEN 1 END) AS completed_follow_up
        FROM `" . CAMPAIGN_FOLLOW_UP . "`
        WHERE `status` = 'A'
        GROUP BY `campaign_id`
    ) fu ON fu.`campaign_id` = c.`id`
    LEFT JOIN (
        SELECT `campaign_id`, COUNT(DISTINCT `campaign_customer_id`) AS purchased_customers
        FROM `" . CAMPAIGN_PURCHASE_RECORD . "`
        WHERE `status` = 'A'
        GROUP BY `campaign_id`
    ) pur ON pur.`campaign_id` = c.`id`
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.`id` DESC";

$campaignStmt = $connect->prepare($campaignSql);
if ($campaignStmt) {
    if ($paramTypes !== '') {
        $bindParams = array($paramTypes);
        foreach ($paramValues as $paramIndex => $paramValue) {
            $bindParams[] = &$paramValues[$paramIndex];
        }
        call_user_func_array(array($campaignStmt, 'bind_param'), $bindParams);
    }
    if ($campaignStmt->execute()) {
        $campaignResult = $campaignStmt->get_result();
        while ($campaignResult && ($row = $campaignResult->fetch_assoc())) {
            $campaignRows[] = $row;
        }
    }
    $campaignStmt->close();
}

$userRows = array();
$userResult = getData('*', '', '', USR_USER, $connect);
if ($userResult instanceof mysqli_result) {
    while ($userRow = $userResult->fetch_assoc()) {
        $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
        $displayName = trim((string) (isset($userRow['name']) && $userRow['name'] !== '' ? $userRow['name'] : (isset($userRow['username']) ? $userRow['username'] : $userId)));
        if ($userId > 0) {
            $userRows[] = array('id' => $userId, 'name' => $displayName);
        }
    }
}

$picAutocompleteOptions = $userRows;
$filterPicName = '';
foreach ($picAutocompleteOptions as $picOption) {
    if ((int) $picOption['id'] === $filterPic) {
        $filterPicName = $picOption['name'];
        break;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>
    .campaign-action-btns {
        min-width: 310px;
        white-space: nowrap;
    }

    .campaign-action-users {
        background-color: #198754;
        border-color: #198754;
        color: #fff;
    }

    .campaign-action-users:hover,
    .campaign-action-users:focus {
        background-color: #157347;
        border-color: #146c43;
        color: #fff;
    }

    .campaign-action-message {
        background-color: #6f42c1;
        border-color: #6f42c1;
        color: #fff;
    }

    .campaign-action-message:hover,
    .campaign-action-message:focus {
        background-color: #5c37a6;
        border-color: #56339b;
        color: #fff;
    }

    .campaign-action-followup {
        background-color: #fd7e14;
        border-color: #fd7e14;
        color: #fff;
    }

    .campaign-action-followup:hover,
    .campaign-action-followup:focus {
        background-color: #e96b02;
        border-color: #da6502;
        color: #fff;
    }

    .campaign-action-purchase {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: #fff;
    }

    .campaign-action-purchase:hover,
    .campaign-action-purchase:focus {
        background-color: #0b5ed7;
        border-color: #0a58ca;
        color: #fff;
    }

    .campaign-action-report {
        background-color: #20c997;
        border-color: #20c997;
        color: #fff;
    }

    .campaign-action-report:hover,
    .campaign-action-report:focus {
        background-color: #1baa80;
        border-color: #19a179;
        color: #fff;
    }
    </style>
</head>

<script>
    

    $(document).ready(() => {
        createSortingTable('campaign_table', { searching: true });
    });
</script><body>
    

    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?php echo campaignH($pageTitle); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?php echo campaignH($pageTitle); ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirectPage . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add Campaign</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <table class="table table-striped" id="campaign_table">
                    <thead>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Campaign Name</th>
                            <th scope="col">Period</th>
                            <th scope="col">Person In Charges</th>
                            <th scope="col">Total Participants</th>
                            <th scope="col">Follow-Up Progress</th>
                            <th scope="col">Purchase Rate</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $num = 1; ?>
                        <?php foreach ($campaignRows as $row): ?>
                            <?php
                            $campaignId = (int) $row['id'];
                            $totalParticipants = isset($row['total_participants']) ? (int) $row['total_participants'] : 0;
                            $totalFollowUp = isset($row['total_follow_up']) ? (int) $row['total_follow_up'] : 0;
                            $completedFollowUp = isset($row['completed_follow_up']) ? (int) $row['completed_follow_up'] : 0;
                            $purchasedCustomers = isset($row['purchased_customers']) ? (int) $row['purchased_customers'] : 0;
                            $followUpPercent = $totalFollowUp > 0 ? round(($completedFollowUp / $totalFollowUp) * 100) : 0;
                            $purchaseRate = $totalParticipants > 0 ? round(($purchasedCustomers / $totalParticipants) * 100) : 0;
                            $periodText = trim((string) ($row['period_start_date'] ?? '')) . ' - ' . trim((string) ($row['period_end_date'] ?? ''));
                            $campaignStatus = trim((string) ($row['campaign_status'] ?? ''));
                            $periodEndDate = trim((string) ($row['period_end_date'] ?? ''));
                            $isEnded = ($campaignStatus === 'Completed') || ($periodEndDate !== '' && $periodEndDate < date('Y-m-d'));
                            ?>
                            <tr>
                                <th class="hideColumn" scope="row"><?= $campaignId ?></th>
                                <th scope="row"><?= $num++; ?></th>
                                <td scope="row" class="btn-container campaign-action-btns">
                                    <?php if (isActionAllowed("View", $pinAccess)): ?>
                                        <a class="btn btn-primary me-1" href="<?= $redirectPage ?>?id=<?= $campaignId ?>" title="View"><i class="fas fa-eye"></i></a>
                                    <?php endif; ?>
                                    <?php if (isActionAllowed("Edit", $pinAccess)): ?>
                                        <a class="btn btn-warning me-1" href="<?= $redirectPage ?>?id=<?= $campaignId ?>&act=<?= $act_2 ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    <?php if (isActionAllowed("Delete", $pinAccess)): ?>
                                        <button class="btn btn-danger me-1" type="button" onclick='confirmCampaignDelete(<?= campaignJson((string) $campaignId . '|' . (string) $csrfToken) ?>, <?= campaignJson((string) ($row['campaign_name'] ?? '')) ?>)' title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    <?php endif; ?>
                                    <a class="btn campaign-action-message me-1" href="<?= $SITEURL ?>/campaign/campaign_message_shortcut.php?campaign_id=<?= $campaignId ?>" title="Message Shortcut"><i class="fas fa-envelope"></i></a>
                                    <a class="btn campaign-action-followup me-1" href="<?= $SITEURL ?>/campaign/campaign_follow_up_task.php?campaign_id=<?= $campaignId ?>" title="Follow-Up Task"><i class="fas fa-bell"></i></a>
                                    <a class="btn campaign-action-purchase me-1" href="<?= $SITEURL ?>/campaign/campaign_purchase_tracking.php?campaign_id=<?= $campaignId ?>" title="Purchase Tracking"><i class="fas fa-cart-shopping"></i></a>
                                    <?php if ($isEnded): ?>
                                        <a class="btn campaign-action-report me-1" href="<?= $SITEURL ?>/campaign/campaign_report.php?campaign_id=<?= $campaignId ?>" title="Report"><i class="fas fa-chart-line"></i></a>
                                    <?php endif; ?>
                                </td>
                                <td scope="row"><?= campaignH($row['campaign_name'] ?? '') ?></td>
                                <td scope="row"><?= campaignH($periodText) ?></td>
                                <td scope="row"><?= campaignH($row['pic_names'] ?? '') ?></td>
                                <td scope="row"><?= $totalParticipants ?></td>
                                <td scope="row"><?= $completedFollowUp ?>/<?= $totalFollowUp ?> (<?= $followUpPercent ?>%)</td>
                                <td scope="row"><?= $purchaseRate ?>%</td>
                                <td scope="row"><?= campaignH($row['description'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="hideColumn" scope="col">ID</th>
                            <th scope="col">S/N</th>
                            <th scope="col" id="action_col">Action</th>
                            <th scope="col">Campaign Name</th>
                            <th scope="col">Period</th>
                            <th scope="col">Person In Charges</th>
                            <th scope="col">Total Participants</th>
                            <th scope="col">Follow-Up Progress</th>
                            <th scope="col">Purchase Rate</th>
                            <th scope="col">Remark</th>
                        </tr>
                    </tfoot>
                </table>

            </div>
        </div>
    </div>

    <form id="campaignDeleteForm" method="post" class="d-none">
        <input type="hidden" name="csrf_token" value="<?= campaignH($csrfToken) ?>">
        <input type="hidden" name="id" id="deleteCampaignId">
        <input type="hidden" name="actionBtn" value="deleteCampaign">
    </form>

    <script>
        function confirmCampaignDelete(deletePayload, name) {
            confirmationDialog(deletePayload, [name || ''], 'Campaign', '<?= campaignH($deleteRedirectPage) ?>', '<?= campaignH($deleteRedirectPage) ?>', 'D');
        }

        const page = "<?= campaignH($pageTitle) ?>";
        const action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('campaign_table');
        setButtonColor();
    </script>
    <?php
    campaignRenderPopupScript($pageTitle, $deleteRedirectPage);
    ?>
</body>

</html>
