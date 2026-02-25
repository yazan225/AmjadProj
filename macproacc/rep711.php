<?php
// ضع هذا الملف في الدليل الرئيسي لـ FrontAccounting كـ test_root.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "الاختبار من الدليل الجذر<br>";
echo "المسار الحالي: " . __DIR__ . "<br>";

// فحص الملفات من هنا
$files = [
    "includes/session.inc",
    "includes/database.inc",
    "config.php"
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file موجود<br>";
    } else {
        echo "✗ $file غير موجود<br>";
    }
}
?>