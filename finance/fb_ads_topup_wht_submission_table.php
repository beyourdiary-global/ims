<?php
ob_start();
$pageTitle = 'FB-Ads WHT Submission';
$currentPagePin = 168;

include_once '../include/list_page_header.php';
include_once ROOT . '/include/fb_ads_topup_wht_submission_common.php';

$redirectPage = $SITEURL . '/finance/fb_ads_topup_wht_submission.php';
$deleteRedirectPage = $SITEURL . '/finance/fb_ads_topup_wht_submission_table.php';
$viewAccess = isActionAllowed('View', $pinAccess);
if (!$viewAccess) {
    renderNotificationScript('You do not have permission to view FB-Ads WHT Submission.', 'error', $SITEURL . '/dashboard.php');
    exit;
}
$submissionRows = array();
$submissionQuery = "SELECT
        MIN(`id`) AS `id`,
        `submission_ref`,
        MIN(`create_date`) AS `submission_date`,
        SUM(`amount`) AS `total_amount`,
        SUM(`subtotal`) AS `total_subtotal`,
        SUM(`sst`) AS `total_sst`,
        MAX(`attachment`) AS `attachment`,
        CASE WHEN SUM(CASE WHEN `submission_status` = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending' ELSE 'success' END AS `submission_status`,
        MIN(`create_by`) AS `create_by`
    FROM `" . FB_ADS_WHT_SUBMISSION . "`
    WHERE `status` = 'A'
    GROUP BY `submission_ref`
    ORDER BY MIN(`id`) DESC";
$submissionResult = mysqli_query($finance_connect, $submissionQuery);
if ($submissionResult) {
    while ($row = mysqli_fetch_assoc($submissionResult)) {
        $submissionRows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/main.css">
</head>
<script>
    $(document).ready(function () {
        createSortingTable('fb_ads_topup_wht_submission_table');
    });
</script>
<body>
<div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
    <div class="col-12 col-md-11">
        <div class="d-flex flex-column mb-3">
            <div class="row">
                <p><a href="<?= htmlspecialchars($SITEURL . '/dashboard.php', ENT_QUOTES, 'UTF-8') ?>">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="row">
                <div class="col-12 d-flex justify-content-between flex-wrap">
                    <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped" id="fb_ads_topup_wht_submission_table">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Action</th>
                        <th>Submission Reference</th>
                        <th>Submission Date</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end">Total Subtotal</th>
                        <th class="text-end">Total GST / SST</th>
                        <th>Attachment</th>
                        <th>Submission Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNumber = 1; ?>
                    <?php foreach ($submissionRows as $row): ?>
                        <tr>
                            <td><?= $rowNumber++ ?></td>
                            <td class="btn-container">
                                <div class="d-flex align-items-center">
                                    <?php renderViewEditButton('View', $redirectPage, array('id' => $row['id']), $pinAccess); ?>
                                    <?php renderViewEditButton('Edit', $redirectPage, array('id' => $row['id']), $pinAccess, 'E'); ?>
                                    <?php renderDeleteButton($pinAccess, $row['id'], $row['submission_ref'], $row['submission_status'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars((string) $row['submission_ref'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) $row['submission_date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end"><?= number_format((float) $row['total_amount'], 2, '.', '') ?></td>
                            <td class="text-end"><?= number_format((float) $row['total_subtotal'], 2, '.', '') ?></td>
                            <td class="text-end"><?= number_format((float) $row['total_sst'], 2, '.', '') ?></td>
                            <td>
                                <?php if ((string) $row['attachment'] !== ''): ?>
                                    <a href="<?= htmlspecialchars(rtrim($SITEURL, '/') . '/' . ltrim((string) $row['attachment'], '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View Attachment</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((string) $row['submission_status'] === 'success'): ?>
                                    <span class="badge bg-success">Success</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
