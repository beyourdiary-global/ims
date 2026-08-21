<?php
$pageTitle = "All Campaigns Dashboard";
$currentPagePin = 153;

include '../menuHeader.php';
include '../checkCurrentPagePin.php';
include_once ROOT . '/include/campaign2_common.php';

$pinAccess = checkCurrentPin($connect, 'Campaign');
if (!isActionAllowed('View', $pinAccess)) {
    echo '<script>location.href = "' . $SITEURL . '/dashboard.php";</script>';
    exit();
}

$searchCampaignName = trim((string) input('campaign_name'));
$searchType = trim((string) input('type')); // 'all', 'campaign', 'campaign2'
if ($searchType === '') {
    $searchType = 'all';
}

$redirectPage = $SITEURL . '/campaign2/campaign_unified_dashboard.php';
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
                        All Campaigns
                    </p>
                </div>
            </div>

            <h1>所有Campaign统一查看</h1>

            <!-- 搜索和类型过滤 -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>搜索和过滤</strong>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" name="campaign_name" class="form-control" value="<?= campaign2H($searchCampaignName) ?>" placeholder="输入Campaign名称">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Campaign Type</label>
                            <select name="type" class="form-select">
                                <option value="all" <?php echo $searchType === 'all' ? 'selected' : ''; ?>>All Campaigns</option>
                                <option value="campaign" <?php echo $searchType === 'campaign' ? 'selected' : ''; ?>>Campaign (旧系统)</option>
                                <option value="campaign2" <?php echo $searchType === 'campaign2' ? 'selected' : ''; ?>>Campaign2 (新系统)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">搜索</button>
                            <a href="<?= $redirectPage ?>" class="btn btn-secondary">重置</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab导航 -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $searchType === 'all' || $searchType === '' ? 'active' : ''; ?>" id="tab-all" data-bs-toggle="tab" data-bs-target="#content-all" type="button">All (<?= $searchType === 'all' || $searchType === '' ? 'total' : '?' ?>)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $searchType === 'campaign' ? 'active' : ''; ?>" id="tab-campaign" data-bs-toggle="tab" data-bs-target="#content-campaign" type="button">Campaign (旧系统)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $searchType === 'campaign2' ? 'active' : ''; ?>" id="tab-campaign2" data-bs-toggle="tab" data-bs-target="#content-campaign2" type="button">Campaign2 (新系统)</button>
                </li>
            </ul>

            <!-- Tab内容 -->
            <div class="tab-content">
                <!-- All Campaigns -->
                <div class="tab-pane fade <?php echo $searchType === 'all' || $searchType === '' ? 'show active' : ''; ?>" id="content-all">
                    <div class="card">
                        <div class="card-header bg-white">
                            <strong>所有Campaigns</strong>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Campaign Name</th>
                                        <th>Period Start</th>
                                        <th>Period End</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // 获取旧campaign数据
                                    $oldCampaignWhere = "c.`status`='A'";
                                    if ($searchCampaignName !== '') {
                                        $safeName = $connect->real_escape_string($searchCampaignName);
                                        $oldCampaignWhere .= " AND c.`campaign_name` LIKE '%" . $safeName . "%'";
                                    }
                                    $oldCampaignSql = "SELECT c.`id`, c.`campaign_name`, c.`period_start_date`, c.`period_end_date`, c.`create_by` FROM `campaign` c WHERE " . $oldCampaignWhere . " ORDER BY c.`create_date` DESC LIMIT 100";
                                    $oldCampaignResult = mysqli_query($connect, $oldCampaignSql);
                                    if ($oldCampaignResult) {
                                        while ($row = $oldCampaignResult->fetch_assoc()) {
                                            $isEnded = strtotime(trim((string) $row['period_end_date'])) < strtotime(date('Y-m-d'));
                                            echo '<tr>';
                                            echo '<td><span class="badge bg-secondary">旧系统</span></td>';
                                            echo '<td>' . campaign2H($row['campaign_name']) . '</td>';
                                            echo '<td>' . campaign2H($row['period_start_date']) . '</td>';
                                            echo '<td>' . campaign2H($row['period_end_date']) . '</td>';
                                            echo '<td>' . ($isEnded ? '<span class="badge bg-danger">已结束</span>' : '<span class="badge bg-success">进行中</span>') . '</td>';
                                            echo '<td>' . campaign2H($row['create_by']) . '</td>';
                                            echo '<td>';
                                            if (isActionAllowed('View', $pinAccess)) {
                                                echo '<a href="' . $SITEURL . '/campaign/campaign_table.php" class="btn btn-sm btn-info">Go to Campaign</a>';
                                            }
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    }

                                    // 获取新campaign2数据
                                    $newCampaignWhere = "c.`status`='A'";
                                    if ($searchCampaignName !== '') {
                                        $safeName = $connect->real_escape_string($searchCampaignName);
                                        $newCampaignWhere .= " AND c.`campaign_name` LIKE '%" . $safeName . "%'";
                                    }
                                    if (defined('CAMPAIGN2') && campaign2TableExists($connect, CAMPAIGN2)) {
                                        $newCampaignSql = "SELECT c.`id`, c.`campaign_name`, c.`period_start_date`, c.`period_end_date`, c.`create_by` FROM `" . CAMPAIGN2 . "` c WHERE " . $newCampaignWhere . " ORDER BY c.`create_date` DESC LIMIT 100";
                                        $newCampaignResult = mysqli_query($connect, $newCampaignSql);
                                        if ($newCampaignResult) {
                                            while ($row = $newCampaignResult->fetch_assoc()) {
                                                $isEnded = strtotime(trim((string) $row['period_end_date'])) < strtotime(date('Y-m-d'));
                                                echo '<tr>';
                                                echo '<td><span class="badge bg-primary">新系统</span></td>';
                                                echo '<td>' . campaign2H($row['campaign_name']) . '</td>';
                                                echo '<td>' . campaign2H($row['period_start_date']) . '</td>';
                                                echo '<td>' . campaign2H($row['period_end_date']) . '</td>';
                                                echo '<td>' . ($isEnded ? '<span class="badge bg-danger">已结束</span>' : '<span class="badge bg-success">进行中</span>') . '</td>';
                                                echo '<td>' . campaign2H($row['create_by']) . '</td>';
                                                echo '<td>';
                                                if (isActionAllowed('View', $pinAccess)) {
                                                    echo '<a href="' . $SITEURL . '/campaign2/campaign2_form.php?id=' . (int) $row['id'] . '&act=V" class="btn btn-sm btn-info">View</a> ';
                                                    echo '<a href="' . $SITEURL . '/campaign2/campaign2_report.php?campaign_id=' . (int) $row['id'] . '" class="btn btn-sm btn-success">Report</a>';
                                                }
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Campaign (旧系统) -->
                <div class="tab-pane fade <?php echo $searchType === 'campaign' ? 'show active' : ''; ?>" id="content-campaign">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between">
                            <strong>Campaign (旧系统)</strong>
                            <?php if (isActionAllowed('View', $pinAccess)): ?>
                                <a href="<?= $SITEURL ?>/campaign/campaign_table.php" class="btn btn-sm btn-primary">进入Campaign系统</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Campaign Name</th>
                                        <th>Period</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $oldCampaignWhere = "c.`status`='A'";
                                    if ($searchCampaignName !== '') {
                                        $safeName = $connect->real_escape_string($searchCampaignName);
                                        $oldCampaignWhere .= " AND c.`campaign_name` LIKE '%" . $safeName . "%'";
                                    }
                                    $oldCampaignSql = "SELECT c.`id`, c.`campaign_name`, c.`period_start_date`, c.`period_end_date`, c.`create_by` FROM `campaign` c WHERE " . $oldCampaignWhere . " ORDER BY c.`create_date` DESC LIMIT 100";
                                    $oldCampaignResult = mysqli_query($connect, $oldCampaignSql);
                                    if ($oldCampaignResult && $oldCampaignResult->num_rows > 0) {
                                        while ($row = $oldCampaignResult->fetch_assoc()) {
                                            $isEnded = strtotime(trim((string) $row['period_end_date'])) < strtotime(date('Y-m-d'));
                                            echo '<tr>';
                                            echo '<td>' . campaign2H($row['campaign_name']) . '</td>';
                                            echo '<td>' . campaign2H($row['period_start_date']) . ' - ' . campaign2H($row['period_end_date']) . '</td>';
                                            echo '<td>' . ($isEnded ? '<span class="badge bg-danger">已结束</span>' : '<span class="badge bg-success">进行中</span>') . '</td>';
                                            echo '<td>' . campaign2H($row['create_by']) . '</td>';
                                            echo '<td>';
                                            echo '<a href="' . $SITEURL . '/campaign/campaign_table.php" class="btn btn-sm btn-info">查看详情</a>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center text-muted">无数据</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Campaign2 (新系统) -->
                <div class="tab-pane fade <?php echo $searchType === 'campaign2' ? 'show active' : ''; ?>" id="content-campaign2">
                    <div class="card">
                        <div class="card-header bg-white d-flex justify-content-between">
                            <strong>Campaign2 (新系统)</strong>
                            <?php if (isActionAllowed('Add', $pinAccess)): ?>
                                <a href="<?= $SITEURL ?>/campaign2/campaign2_list.php" class="btn btn-sm btn-primary">进入Campaign2系统</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-striped w-100">
                                <thead>
                                    <tr>
                                        <th>Campaign Name</th>
                                        <th>Period</th>
                                        <th>Status</th>
                                        <th>Customers</th>
                                        <th>Follow-ups</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (defined('CAMPAIGN2') && campaign2TableExists($connect, CAMPAIGN2)) {
                                        $newCampaignWhere = "c.`status`='A'";
                                        if ($searchCampaignName !== '') {
                                            $safeName = $connect->real_escape_string($searchCampaignName);
                                            $newCampaignWhere .= " AND c.`campaign_name` LIKE '%" . $safeName . "%'";
                                        }
                                        $newCampaignSql = "SELECT c.`id`, c.`campaign_name`, c.`period_start_date`, c.`period_end_date`, c.`create_by`,
                                            COUNT(DISTINCT cc.`id`) AS customer_count,
                                            COUNT(DISTINCT cf.`id`) AS followup_count
                                            FROM `" . CAMPAIGN2 . "` c
                                            LEFT JOIN `" . CAMPAIGN2_CUSTOMER . "` cc ON cc.`campaign_id`=c.`id` AND cc.`status`='A'
                                            LEFT JOIN `" . CAMPAIGN2_FOLLOW_UP . "` cf ON cf.`campaign_id`=c.`id` AND cf.`status`='A'
                                            WHERE " . $newCampaignWhere . "
                                            GROUP BY c.`id`
                                            ORDER BY c.`create_date` DESC LIMIT 100";
                                        $newCampaignResult = mysqli_query($connect, $newCampaignSql);
                                        if ($newCampaignResult && $newCampaignResult->num_rows > 0) {
                                            while ($row = $newCampaignResult->fetch_assoc()) {
                                                $isEnded = strtotime(trim((string) $row['period_end_date'])) < strtotime(date('Y-m-d'));
                                                echo '<tr>';
                                                echo '<td>' . campaign2H($row['campaign_name']) . '</td>';
                                                echo '<td>' . campaign2H($row['period_start_date']) . ' - ' . campaign2H($row['period_end_date']) . '</td>';
                                                echo '<td>' . ($isEnded ? '<span class="badge bg-danger">已结束</span>' : '<span class="badge bg-success">进行中</span>') . '</td>';
                                                echo '<td>' . (int) $row['customer_count'] . '</td>';
                                                echo '<td>' . (int) $row['followup_count'] . '</td>';
                                                echo '<td>' . campaign2H($row['create_by']) . '</td>';
                                                echo '<td>';
                                                echo '<a href="' . $SITEURL . '/campaign2/campaign2_form.php?id=' . (int) $row['id'] . '&act=V" class="btn btn-sm btn-info">View</a> ';
                                                echo '<a href="' . $SITEURL . '/campaign2/campaign2_report.php?campaign_id=' . (int) $row['id'] . '" class="btn btn-sm btn-success">Report</a>';
                                                echo '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="7" class="text-center text-muted">无数据</td></tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="7" class="text-center text-muted">Campaign2 tables不存在，请运行insert_table.php创建</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        checkCurrentPage('<?= campaign2H($pageTitle) ?>', 'View');
        setButtonColor();
    </script>
</body>
</html>
