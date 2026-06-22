<?php
$pageTitle = "Label";
$currentPagePin = 145;


include_once '../include/list_page_header.php';

$tblName = LABEL;

$redirectPage = $SITEURL . '/customer/label.php';
$deleteRedirectPage = $SITEURL . '/customer/label_table.php';

$result = getData('*', '', '', $tblName, $connect);
$parentLabelMap = array();
$hasRows = false;

if ($result) {
    $hasRows = true;
    $parentResult = mysqli_query($connect, "SELECT id, name FROM " . $tblName . " WHERE status = 'A'");
    if ($parentResult) {
        while ($parentRow = $parentResult->fetch_assoc()) {
            $parentLabelMap[(string) $parentRow['id']] = (string) $parentRow['name'];
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script>
    
    window.labelTableConfig = {
        pageTitle: <?= json_encode($pageTitle, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        action: <?= json_encode(isset($act) ? $act : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
</script>
<body>
    

    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?php echo $pageTitle ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)) : ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn" href="<?= $redirectPage . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add <?php echo $pageTitle ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$hasRows) { ?>
                    <div class="text-center"><h4>No Result!</h4></div>
                <?php } else { ?>
                    <table class="table table-striped" id="table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="100px">Action</th>
                                <th scope="col">Label Name</th>
                                <th scope="col">Parent Label</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                if (!isset($row['name'], $row['id']) || $row['name'] === '') {
                                    continue;
                                }

                                $parentLabelName = '';
                                if (!empty($row['parent_label'])) {
                                    $parentKey = (string) $row['parent_label'];
                                    $parentLabelName = isset($parentLabelMap[$parentKey]) ? $parentLabelMap[$parentKey] : $parentKey;
                                }
                            ?>
                                <tr>
                                    <th class="hideColumn" scope="row"><?= (int) $row['id'] ?></th>
                                    <th scope="row"><?= $num++; ?></th>
                                    <td scope="row" class="btn-container">
                                        <?php renderViewEditButton("View", $redirectPage, $row, $pinAccess); ?>
                                        <?php renderViewEditButton("Edit", $redirectPage, $row, $pinAccess, $act_2); ?>
                                        <?php renderDeleteButton($pinAccess, $row['id'], $row['name'], $row['remark'], $pageTitle, $redirectPage, $deleteRedirectPage); ?>
                                    </td>
                                    <td scope="row"><?= htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) $parentLabelName, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td scope="row"><?= htmlspecialchars((string) (isset($row['remark']) ? $row['remark'] : ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="100px">Action</th>
                                <th scope="col">Label Name</th>
                                <th scope="col">Parent Label</th>
                                <th scope="col">Remark</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <script src="<?= $SITEURL ?>/js/label_table.js"></script>
</body>

</html>
