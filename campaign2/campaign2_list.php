<?php
$pageTitle = "Campaign2";
$currentPagePin = 153;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign2_common.php';

$pinAccess = checkCurrentPin($connect, 'Campaign');
if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$csrfToken = campaign2CsrfToken('campaign2_table');
$redirectPage = $SITEURL . '/campaign2/campaign2_list.php';

// 获取搜索参数
$searchCampaignName = trim((string) input('campaign_name'));
$searchPeriodStart = trim((string) input('period_start'));
$searchPeriodEnd = trim((string) input('period_end'));

// 构建WHERE条件
$whereConditions = array("c.`status`='A'");

if ($searchCampaignName !== '') {
    $safeName = $connect->real_escape_string($searchCampaignName);
    $whereConditions[] = "c.`campaign_name` LIKE '%" . $safeName . "%'";
}

if ($searchPeriodStart !== '') {
    $safeStart = $connect->real_escape_string(campaign2DateValue($searchPeriodStart));
    if ($safeStart !== '') {
        $whereConditions[] = "c.`period_start_date` >= '" . $safeStart . "'";
    }
}

if ($searchPeriodEnd !== '') {
    $safeEnd = $connect->real_escape_string(campaign2DateValue($searchPeriodEnd));
    if ($safeEnd !== '') {
        $whereConditions[] = "c.`period_end_date` <= '" . $safeEnd . "'";
    }
}

$whereSQL = implode(' AND ', $whereConditions);

// 处理删除操作
if (post('act') === 'D' && !empty(post('id'))) {
    $payload = trim((string) post('id'));
    $postedToken = trim((string) post('csrf_token'));

    if (!campaign2VerifyCsrf('campaign2_table', $postedToken)) {
        campaign2SetPopup('无效的安全令牌', $redirectPage, 'ErrMO');
        echo '<script>location.href = "' . $redirectPage . '";</script>';
        exit();
    }

    $deleteId = (int) $payload;
    if ($deleteId > 0) {
        $safeUserId = $connect->real_escape_string((string) USER_ID);
        $stmt = $connect->prepare("UPDATE `" . CAMPAIGN2 . "` SET `status`='D', `update_by`=?, `update_date`=CURDATE(), `update_time`=CURTIME() WHERE `id`=? AND `status`='A'");
        if ($stmt) {
            $stmt->bind_param('si', $safeUserId, $deleteId);
            if ($stmt->execute()) {
                campaign2Audit($connect, $pageTitle, 'delete', USER_NAME . ' deleted Campaign2 ID=' . $deleteId, 'DELETE FROM ' . CAMPAIGN2 . ' WHERE id=' . $deleteId);
                campaign2SetPopup('Campaign2已删除', $redirectPage, 'ErrMO');
            } else {
                campaign2SetPopup('删除失败: ' . $stmt->error, $redirectPage, 'ErrMO');
            }
            $stmt->close();
        }
    }

    echo '<script>location.href = "' . $redirectPage . '";</script>';
    exit();
}

// 获取列表数据
$sql = "SELECT c.`id`, c.`campaign_name`, c.`period_start_date`, c.`period_end_date`,
        c.`description`, c.`create_by`, c.`create_date`,
        COUNT(DISTINCT cc.`id`) AS customer_count,
        COUNT(DISTINCT cf.`id`) AS followup_count
        FROM `" . CAMPAIGN2 . "` c
        LEFT JOIN `" . CAMPAIGN2_CUSTOMER . "` cc ON cc.`campaign_id`=c.`id` AND cc.`status`='A'
        LEFT JOIN `" . CAMPAIGN2_FOLLOW_UP . "` cf ON cf.`campaign_id`=c.`id` AND cf.`status`='A'
        WHERE " . $whereSQL . "
        GROUP BY c.`id`
        ORDER BY c.`create_date` DESC, c.`id` DESC";

$result = mysqli_query($connect, $sql);
$campaigns = array();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $campaigns[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
<body>
    <div class="page-load-cover">
        <div class="container-fluid px-4">
            <div class="row mt-3">
                <div class="col-12">
                    <p>
                        <a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
                        <i class="fa-solid fa-chevron-right fa-xs"></i>
                        Campaign2
                    </p>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
                <h1>Campaign2列表</h1>
                <?php if (isActionAllowed('Add', $pinAccess)): ?>
                    <a href="<?= $SITEURL ?>/campaign2/campaign2_form.php?act=A" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> 新建Campaign
                    </a>
                <?php endif; ?>
            </div>

            <!-- 搜索表单 -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>搜索</strong>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" name="campaign_name" class="form-control" value="<?= campaign2H($searchCampaignName) ?>" placeholder="输入Campaign名称">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Period Start Date</label>
                            <input type="date" name="period_start" class="form-control" value="<?= campaign2H($searchPeriodStart) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Period End Date</label>
                            <input type="date" name="period_end" class="form-control" value="<?= campaign2H($searchPeriodEnd) ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">搜索</button>
                            <a href="<?= $redirectPage ?>" class="btn btn-secondary">重置</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 列表 -->
            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-striped w-100" id="campaign2_table">
                        <thead>
                            <tr>
                                <th>Campaign Name</th>
                                <th>Period Start</th>
                                <th>Period End</th>
                                <th>Customers</th>
                                <th>Follow-ups</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td><?= campaign2H($campaign['campaign_name']) ?></td>
                                    <td><?= campaign2H($campaign['period_start_date']) ?></td>
                                    <td><?= campaign2H($campaign['period_end_date']) ?></td>
                                    <td><?= (int) $campaign['customer_count'] ?></td>
                                    <td><?= (int) $campaign['followup_count'] ?></td>
                                    <td><?= campaign2H($campaign['create_by']) ?></td>
                                    <td>
                                        <?php if (isActionAllowed('View', $pinAccess)): ?>
                                            <a href="<?= $SITEURL ?>/campaign2/campaign2_form.php?id=<?= (int) $campaign['id'] ?>&act=V" class="btn btn-sm btn-info">View</a>
                                        <?php endif; ?>
                                        <?php if (isActionAllowed('Edit', $pinAccess)): ?>
                                            <a href="<?= $SITEURL ?>/campaign2/campaign2_form.php?id=<?= (int) $campaign['id'] ?>&act=E" class="btn btn-sm btn-warning">Edit</a>
                                        <?php endif; ?>
                                        <?php if (isActionAllowed('Delete', $pinAccess)): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteConfirm(<?= (int) $campaign['id'] ?>)">Delete</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        checkCurrentPage('<?= campaign2H($pageTitle) ?>', 'View');
        setButtonColor();

        function deleteConfirm(id) {
            confirmationDialog("Delete Campaign", "Are you sure want to delete this campaign?", "<?= campaign2H($pageTitle) ?>", "delete_id_" + id, "<?= campaign2H($redirectPage) ?>", "ErrMO");

            // 处理确认删除
            var originalFunc = window.confirmationDialogConfirm;
            window.confirmationDialogConfirm = function(buttonId) {
                if (buttonId === 'delete_id_' + id) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?= campaign2H($redirectPage) ?>';

                    var input1 = document.createElement('input');
                    input1.type = 'hidden';
                    input1.name = 'id';
                    input1.value = id;

                    var input2 = document.createElement('input');
                    input2.type = 'hidden';
                    input2.name = 'act';
                    input2.value = 'D';

                    var input3 = document.createElement('input');
                    input3.type = 'hidden';
                    input3.name = 'csrf_token';
                    input3.value = '<?= campaign2H($csrfToken) ?>';

                    form.appendChild(input1);
                    form.appendChild(input2);
                    form.appendChild(input3);
                    document.body.appendChild(form);
                    form.submit();
                }
            };
        }
    </script>
    <?php campaign2RenderPopupScript($pageTitle, $redirectPage); ?>
</body>
</html>
