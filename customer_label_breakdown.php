<?php
$pageTitle = 'Customer Label Breakdown';
$currentPagePin = 29;

include 'menuHeader.php';

$labelType = customerLabelNormalizeType(input('label_type'));
$labelId = customerLabelResolveInt(input('label_id'));
$typeConfig = customerLabelGetTypeConfig($labelType);
$currentPagePin = isset($typeConfig['pin']) ? (int) $typeConfig['pin'] : 29;
$sourceTableUrlMap = array(
    'segmentation' => $SITEURL . '/cus_segmentation_table.php',
    'level' => $SITEURL . '/cus_level_table.php',
    'repeat' => $SITEURL . '/cus_repeat_table.php',
);

include 'checkCurrentPagePin.php';

$accessPageTitle = !empty($typeConfig) ? getPinGroupNameById($connect, $currentPagePin) : '';
if ($accessPageTitle === '' && isset($typeConfig['title'])) {
    $accessPageTitle = $typeConfig['title'];
}

$pinAccess = checkCurrentPin($connect, $accessPageTitle);
if ($labelType === '' || $labelId <= 0 || !isActionAllowed('View', $pinAccess)) {
    echo "<script>location.href='" . $SITEURL . "/dashboard.php';</script>";
    exit;
}

$pageTitle = $accessPageTitle . ' Breakdown';
$labelMeta = customerLabelGetLabelMeta($connect, $labelType, $labelId);
$breakdownCounts = customerLabelGetBreakdownCounts($connect, $labelType, $labelId);
$selectedLabelName = isset($labelMeta['name']) ? (string) $labelMeta['name'] : '';
$selectedTotalCount = array_sum($breakdownCounts);
$sourceTableUrl = isset($sourceTableUrlMap[$labelType]) ? $sourceTableUrlMap[$labelType] : ($SITEURL . '/dashboard.php');

if (post('actionBtn') === 'back') {
    echo "<script>location.href='" . $sourceTableUrl . "';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script>
    preloader(300);
</script>

<body>
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">
        <div class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= htmlspecialchars($sourceTableUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($accessPageTitle, ENT_QUOTES, 'UTF-8') ?></a> <i
                                class="fa-solid fa-chevron-right fa-xs"></i> <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <strong>Selected Label:</strong>
                            <span><?= htmlspecialchars($selectedLabelName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?= customerLabelRenderBadge($labelMeta) ?>
                        </div>
                        <div class="mt-2">
                            <strong>Total Customer Count:</strong> <?= (int) $selectedTotalCount ?>
                        </div>
                    </div>
                </div>

                <table class="table table-striped" id="customer_label_breakdown_table">
                    <thead>
                        <tr>
                            <th scope="col" width="60px">S/N</th>
                            <th scope="col">Platform</th>
                            <th scope="col">Customer Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $platformNumber = 1;
                        foreach (customerLabelGetPlatformConfigs() as $platform => $platformConfig) {
                            $platformCount = isset($breakdownCounts[$platform]) ? (int) $breakdownCounts[$platform] : 0;
                            $platformUrl = customerLabelBuildRecordFilterUrl($platform, $labelType, $labelId);
                            ?>
                            <tr>
                                <td><?= $platformNumber++ ?></td>
                                <td><?= htmlspecialchars(customerLabelGetPlatformLabel($platform), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($platformCount > 0 && $platformUrl !== '') { ?>
                                        <a href="<?= htmlspecialchars($platformUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $platformCount ?></a>
                                    <?php } else { ?>
                                        <?= $platformCount ?>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <div class="col-12 text-center mt-5">
                    <form method="post">
                        <button class="btn btn-rounded btn-primary mx-2 mb-2" name="actionBtn" id="actionBtn" value="back">Back</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        dropdownMenuDispFix();
        datatableAlignment('customer_label_breakdown_table');
        setButtonColor();
    </script>
</body>

</html>
