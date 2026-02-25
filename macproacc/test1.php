<?php
// final_test.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>الاختبار النهائي بعد تصحيح database.inc</h1>";

// 1. تحميل الملفات بالترتيب الصحيح
include_once('config.php');
include_once('includes/session.inc');
include_once('includes/database.inc'); // الملف المصحح

echo "✓ تم تحميل جميع الملفات<br>";

// 2. اختبار الاتصال
$db = db_connect();
if ($db) {
    echo "✓ الاتصال بنجاح<br>";
    
    // 3. اختبار الحسابات
    $result = db_query("SELECT COUNT(*) as count FROM 0_chart_master");
    $row = db_fetch($result);
    echo "✓ عدد الحسابات: " . $row['count'] . "<br>";
    
    // 4. اختبار أنواع الحسابات
    $result = db_query("SELECT DISTINCT account_type FROM 0_chart_master LIMIT 10");
    echo "أنواع الحسابات الموجودة:<br>";
    while ($row = db_fetch($result)) {
        echo "→ " . $row['account_type'] . "<br>";
    }
    
    // 5. اختبار الحركات
    $result = db_query("SELECT COUNT(*) as count FROM 0_gl_trans");
    $row = db_fetch($result);
    echo "✓ عدد الحركات: " . $row['count'] . "<br>";
    
} else {
    echo "✗ فشل الاتصال<br>";
}

echo "انتهى الاختبار<br>";
?>