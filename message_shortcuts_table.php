<?php
$pageTitle = "Message Shortcuts";
$currentPagePin = 144;


include_once 'include/list_page_header.php';

$tblName = MESSAGE_SHORTCUTS;

$redirect_page = $SITEURL . '/message_shortcuts.php';
$deleteRedirectPage = $SITEURL . '/message_shortcuts_table.php';

$result = getData('*', '', '', $tblName, $connect);

if (!function_exists('messageShortcutsTablePreview')) {
    function messageShortcutsTablePreview($html, $limit = 160)
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\x{00A0}/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') . '...' : $text;
        }

        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>

<script src="<?= $SITEURL ?>/js/list_page_common.js"></script>


<style>
    

    

    .message-shortcuts-preview-cell {
        max-width: 520px;
        white-space: normal;
        overflow-wrap: anywhere;
    }
</style>

<body>
    

    <div class="page-load-cover">
        <div id="dispTable" class="container-fluid d-flex justify-content-center mt-3">
            <div class="col-12 col-md-11">
                <div class="d-flex flex-column mb-3">
                    <div class="row">
                        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a> <i
                                class="fa-solid fa-chevron-right fa-xs"></i> <?php echo $pageTitle ?></p>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-between flex-wrap">
                            <h2><?php echo $pageTitle ?></h2>
                            <div class="mt-auto mb-auto">
                                <?php if (isActionAllowed("Add", $pinAccess)): ?>
                                    <a class="btn btn-sm btn-rounded btn-primary" name="addBtn" id="addBtn"
                                        href="<?= $redirect_page . "?act=" . $act_1 ?>"><i class="fa-solid fa-plus"></i> Add
                                        <?php echo $pageTitle ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                if (!$result) {
                    echo '<div class="text-center"><h4>No Result!</h4></div>';
                } else {
                    ?>
                    <table class="table table-striped" id="table">
                        <thead>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="100px">Action</th>
                                <th scope="col">Message Shortcuts Tag</th>
                                <th scope="col">Message Shortcuts</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            while ($row = $result->fetch_assoc()) {
                                $messagePreview = messageShortcutsTablePreview(isset($row['shortcuts_message']) ? $row['shortcuts_message'] : '');
                                if (isset($row['shortcuts_tag'], $row['id']) && $row['shortcuts_tag'] !== '') { ?>
                                    <tr>
                                        <th class="hideColumn" scope="row"><?= $row['id'] ?></th>
                                        <th scope="row"><?= $num++; ?></th>
                                        <td scope="row" class="btn-container">
                                            <?php renderViewEditButton("View", $redirect_page, $row, $pinAccess); ?>
                                            <?php renderViewEditButton("Edit", $redirect_page, $row, $pinAccess, $act_2); ?>
                                            <?php renderDeleteButton($pinAccess, $row['id'], $row['shortcuts_tag'], $messagePreview, $pageTitle, $redirect_page, $deleteRedirectPage); ?>
                                        </td>
                                        <td scope="row"><?= htmlspecialchars((string) $row['shortcuts_tag'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td scope="row" class="message-shortcuts-preview-cell">
                                            <?= $messagePreview !== '' ? htmlspecialchars($messagePreview, ENT_QUOTES, 'UTF-8') : '-' ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th class="hideColumn" scope="col">ID</th>
                                <th scope="col" width="60px">S/N</th>
                                <th scope="col" id="action_col" width="100px">Action</th>
                                <th scope="col">Message Shortcuts Tag</th>
                                <th scope="col">Message Shortcuts</th>
                            </tr>
                        </tfoot>
                    </table>
                <?php } ?>
            </div>
        </div>
    </div>

    <script>
        const page = "<?= $pageTitle ?>";
        const action = "<?php echo isset($act) ? $act : ' '; ?>";

        checkCurrentPage(page, action);
        dropdownMenuDispFix();
        datatableAlignment('table');
        setButtonColor();
    </script>
</body>

</html>
