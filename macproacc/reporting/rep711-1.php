<?php
$path_to_root = ".";
include_once($path_to_root . "/includes/session.inc");

echo "المستخدم: " . $_SESSION['wa_current_user']->username . "<br>";
echo "مجموعة الصلاحيات: " . $_SESSION['wa_current_user']->access . "<br>";

// التحقق من الصلاحيات المتاحة
echo "<h3>الصلاحيات المتاحة:</h3>";
$security_areas = array(
    'SA_OPEN' => 'Open Access',
    'SA_GLREP' => 'GL Reports', 
    'SA_GLANALYTIC' => 'GL Analytics',
    'SA_GLSETUP' => 'GL Setup',
    'SA_ITEM' => 'Items',
    'SA_SALES' => 'Sales'
);

foreach($security_areas as $area => $name) {
    $has_access = $_SESSION['wa_current_user']->can_access($area);
    echo "$name ($area): " . ($has_access ? '✅ نعم' : '❌ لا') . "<br>";
}
?>