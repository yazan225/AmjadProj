<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$path_to_root = "..";
$page_security = 'SA_OPEN';

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");

// التحقق من وجود المعلمات
if (!isset($_POST['PARAM_0']) || !isset($_POST['PARAM_1'])) {
    // عرض نموذج الإدخال
    $js = "";
    if ($SysPrefs->use_popup_windows)
        $js .= get_js_open_window(900, 500);
    
    page(_("تقرير الأرباح والخسائر المخصص"), true, false, "", $js);
    start_form();
    start_table(TABLESTYLE2);

    date_cells(_("من تاريخ:"), "PARAM_0", "", null, 0, -1, 0, null, true);
    date_cells(_("إلى تاريخ:"), "PARAM_1", "", null, 0, -1, 0, null, true);
    check_cells(_("عرض التفاصيل:"), "PARAM_2", 1);
    check_cells(_("ضمن الأصفار:"), "PARAM_3", 0);
    
    end_table(1);
    submit_center('Submit', _("عرض التقرير"), true, '', 'default');
    end_form();
    end_page(true);
    exit();
}

// معالجة التقرير كـ HTML
$from = $_POST['PARAM_0'];
$to = $_POST['PARAM_1'];
$show_details = $_POST['PARAM_2'];
$include_zeros = $_POST['PARAM_3'];

$fromdate = date2sql($from);
$todate = date2sql($to);

// بداية صفحة HTML
page(_("تقرير الأرباح والخسائر المخصص - HTML"), true, false, "", "");
echo "<div style='direction: rtl; text-align: right; padding: 20px;'>";
echo "<h1>تقرير الأرباح والخسائر المخصص</h1>";
echo "<h3>الفترة: $from إلى $to</h3>";
echo "<hr>";

// حساب الأقسام
$total_41 = calculate_section_html('41', $fromdate, $todate, $show_details, $include_zeros, "الإيرادات التشغيلية - إيرادات البيع");
$total_51 = calculate_section_html('51', $fromdate, $todate, $show_details, $include_zeros, "الإيرادات غير التشغيلية - الإيرادات الأخرى");

// إجمالي الإيرادات
$total_revenue = $total_41 + $total_51;
echo "<div style='border-top: 2px solid #000; padding: 10px; margin: 10px 0;'>";
echo "<strong>إجمالي الإيرادات: " . number_format($total_revenue, 2) . "</strong>";
echo "</div>";

// المصاريف
$total_42 = calculate_section_html('42', $fromdate, $todate, $show_details, $include_zeros, "تكلفة البضاعة المباعة");
$total_43 = calculate_section_html('43', $fromdate, $todate, $show_details, $include_zeros, "مصاريف المبيعات");
$total_44 = calculate_section_html('44', $fromdate, $todate, $show_details, $include_zeros, "المصاريف الإدارية");
$total_52 = calculate_section_html('52', $fromdate, $todate, $show_details, $include_zeros, "المصاريف الأخرى");
$total_6 = calculate_section_html('6', $fromdate, $todate, $show_details, $include_zeros, "ضريبة الدخل");

// إجمالي المصاريف
$total_expenses = $total_42 + $total_43 + $total_44 + $total_52 + $total_6;
echo "<div style='border-top: 2px solid #000; padding: 10px; margin: 10px 0;'>";
echo "<strong>إجمالي المصاريف: " . number_format($total_expenses, 2) . "</strong>";
echo "</div>";

// حسابات الربح
$gross_profit = $total_revenue - $total_42;
echo "<p><strong>إجمالي الربح: " . number_format($gross_profit, 2) . "</strong></p>";

$operating_profit = $gross_profit - $total_43 - $total_44;
echo "<p><strong>الربح التشغيلي: " . number_format($operating_profit, 2) . "</strong></p>";

$profit_before_tax = $operating_profit + $total_51 - $total_52;
echo "<p><strong>الربح قبل الضرائب: " . number_format($profit_before_tax, 2) . "</strong></p>";

$net_profit = $profit_before_tax - $total_6;
echo "<div style='border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 10px; margin: 10px 0;'>";
if ($net_profit >= 0) {
    echo "<h3>صافي الربح: " . number_format($net_profit, 2) . "</h3>";
} else {
    echo "<h3>صافي الخسارة: " . number_format(abs($net_profit), 2) . "</h3>";
}
echo "</div>";

echo "</div>";
end_page();

function calculate_section_html($account_prefix, $fromdate, $todate, $show_details, $include_zeros, $section_title) {
    
    echo "<div style='margin: 15px 0;'>";
    echo "<h4>$section_title</h4>";
    
    $sql = "SELECT account, SUM(amount) as total
            FROM " . TB_PREF . "gl_trans 
            WHERE tran_date BETWEEN '$fromdate' AND '$todate'
              AND account LIKE '" . db_escape($account_prefix) . "%'
            GROUP BY account
            ORDER BY account";
    
    $result = db_query($sql, "جلب بيانات $account_prefix");
    
    $section_total = 0;
    $has_data = false;
    
    if ($show_details && db_num_rows($result) > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>رقم الحساب</th><th>اسم الحساب</th><th>المبلغ</th></tr>";
        
        while ($myrow = db_fetch($result)) {
            $amount = $myrow['total'];
            
            if (substr($account_prefix, 0, 2) == '41' || substr($account_prefix, 0, 2) == '51') {
                $amount = abs($amount);
            } else {
                $amount = -$amount;
            }
            
            if ($amount != 0 || $include_zeros) {
                $account_name = get_account_name($myrow['account']);
                if (!$account_name) $account_name = $myrow['account'];
                
                echo "<tr>";
                echo "<td>" . $myrow['account'] . "</td>";
                echo "<td>" . $account_name . "</td>";
                echo "<td style='text-align: left;'>" . number_format($amount, 2) . "</td>";
                echo "</tr>";
                
                $section_total += $amount;
                $has_data = true;
            }
        }
        echo "</table>";
    } else {
        $sql_total = "SELECT SUM(amount) as total_sum
                      FROM " . TB_PREF . "gl_trans 
                      WHERE tran_date BETWEEN '$fromdate' AND '$todate'
                        AND account LIKE '" . db_escape($account_prefix) . "%'";
        
        $result_total = db_query($sql_total, "جلب الإجمالي");
        $row_total = db_fetch($result_total);
        $section_total = $row_total['total_sum'] ? $row_total['total_sum'] : 0;
        
        if (substr($account_prefix, 0, 2) == '41' || substr($account_prefix, 0, 2) == '51') {
            $section_total = abs($section_total);
        } else {
            $section_total = -$section_total;
        }
        
        if ($show_details) {
            echo "<p>لا توجد حركات</p>";
        }
    }
    
    echo "<p><strong>إجمالي $section_title: " . number_format($section_total, 2) . "</strong></p>";
    echo "</div>";
    
    return $section_total;
}
?>