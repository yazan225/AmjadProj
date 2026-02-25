<?php
// rep715.php
$path_to_root = "..";
$page_security = 'SA_OPEN';

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

// تضمين testdata5.php مباشرة
$page_title = "Test Data 5 Report";
page($page_title, true);

display_heading($page_title);

// تضمين محتوى testdata5.php
echo '<div style="padding: 20px;">';
include($path_to_root . '/testdata5.php');
echo '</div>';

end_page();
?>