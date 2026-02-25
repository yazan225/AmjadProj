<?php
$path_to_root = "..";
$page_security = 'SA_GLANALYTIC';

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/reporting/includes/pdf_report.inc");

$from = $_POST['PARAM_0'];
$to = $_POST['PARAM_1'];
$show_details = $_POST['PARAM_2'];
$include_zeros = $_POST['PARAM_3'];

$rep = new FrontReport('تقرير الأرباح والخسائر', "ProfitLoss", user_pagesize(), 9, 'P');
$rep->Font();
$rep->NewPage();

$fromdate = date2sql($from);
$todate = date2sql($to);

$rep->TextCol(0, 5, 'تقرير الأرباح والخسائر');
$rep->NewLine();
$rep->TextCol(0, 5, 'الفترة: ' . $from . ' إلى ' . $to);
$rep->NewLine(2);

// الإيرادات التشغيلية (41)
$rep->Font('bold');
$rep->TextCol(0, 2, 'الإيرادات التشغيلية - إيرادات البيع');
$rep->Font();
$rep->NewLine(1.5);

$total_41 = calculate_section($rep, '41', $fromdate, $todate, $show_details, $include_zeros, '41 - إيرادات البيع');

// الإيرادات غير التشغيلية (51)
$rep->Font('bold');
$rep->TextCol(0, 2, 'الإيرادات غير التشغيلية - الإيرادات الأخرى');
$rep->Font();
$rep->NewLine(1.5);

$total_51 = calculate_section($rep, '51', $fromdate, $todate, $show_details, $include_zeros, '51 - الإيرادات الأخرى');

// إجمالي الإيرادات
$total_revenue = $total_41 + $total_51;
$rep->Line($rep->row - 2);
$rep->Font('bold');
$rep->TextCol(0, 2, 'إجمالي الإيرادات:');
$rep->AmountCol(2, 3, $total_revenue, 2);
$rep->Font();
$rep->NewLine(2);

// التكاليف والمصاريف
$rep->Font('bold');
$rep->TextCol(0, 2, 'التكاليف والمصاريف');
$rep->Font();
$rep->NewLine(1.5);

// تكلفة البضاعة المباعة (42)
$rep->Font('bold');
$rep->TextCol(0, 2, 'تكلفة شراء البضاعة المباعة');
$rep->Font();
$rep->NewLine(1.5);

$total_42 = calculate_section($rep, '42', $fromdate, $todate, $show_details, $include_zeros, '42 - تكلفة البضاعة');

// مصاريف المبيعات (43)
$rep->Font('bold');
$rep->TextCol(0, 2, 'مصاريف قسم المبيعات');
$rep->Font();
$rep->NewLine(1.5);

$total_43 = calculate_section($rep, '43', $fromdate, $todate, $show_details, $include_zeros, '43 - مصاريف المبيعات');

// المصاريف الإدارية (44)
$rep->Font('bold');
$rep->TextCol(0, 2, 'المصاريف الإدارية والعمومية');
$rep->Font();
$rep->NewLine(1.5);

$total_44 = calculate_section($rep, '44', $fromdate, $todate, $show_details, $include_zeros, '44 - مصاريف إدارية');

// المصاريف الأخرى (52)
$rep->Font('bold');
$rep->TextCol(0, 2, 'المصاريف الأخرى');
$rep->Font();
$rep->NewLine(1.5);

$total_52 = calculate_section($rep, '52', $fromdate, $todate, $show_details, $include_zeros, '52 - مصاريف أخرى');

// ضريبة الدخل (6)
$rep->Font('bold');
$rep->TextCol(0, 2, 'ضريبة الدخل');
$rep->Font();
$rep->NewLine(1.5);

$total_6 = calculate_section($rep, '6', $fromdate, $todate, $show_details, $include_zeros, '6 - ضريبة الدخل');

// إجمالي المصاريف
$total_expenses = $total_42 + $total_43 + $total_44 + $total_52 + $total_6;
$rep->Line($rep->row - 2);
$rep->Font('bold');
$rep->TextCol(0, 2, 'إجمالي المصاريف:');
$rep->AmountCol(2, 3, $total_expenses, 2);
$rep->Font();
$rep->NewLine(2);

// صافي الربح قبل الضرائب
$gross_profit = $total_revenue - $total_42;
$rep->Font('bold');
$rep->TextCol(0, 2, 'إجمالي الربح:');
$rep->AmountCol(2, 3, $gross_profit, 2);
$rep->Font();
$rep->NewLine();

// صافي الربح التشغيلي
$operating_profit = $gross_profit - $total_43 - $total_44;
$rep->Font('bold');
$rep->TextCol(0, 2, 'الربح التشغيلي:');
$rep->AmountCol(2, 3, $operating_profit, 2);
$rep->Font();
$rep->NewLine();

// صافي الربح قبل الضرائب
$profit_before_tax = $operating_profit + $total_51 - $total_52;
$rep->Font('bold');
$rep->TextCol(0, 2, 'الربح قبل الضرائب:');
$rep->AmountCol(2, 3, $profit_before_tax, 2);
$rep->Font();
$rep->NewLine();

// صافي الربح النهائي
$net_profit = $profit_before_tax - $total_6;
$rep->Line($rep->row - 2);
$rep->Font('bold');
if ($net_profit >= 0) {
    $rep->TextCol(0, 2, 'صافي الربح:');
    $rep->AmountCol(2, 3, $net_profit, 2);
} else {
    $rep->TextCol(0, 2, 'صافي الخسارة:');
    $rep->AmountCol(2, 3, abs($net_profit), 2);
}
$rep->Font();

$rep->End();

// دالة حساب القسم
function calculate_section($rep, $account_prefix, $fromdate, $todate, $show_details, $include_zeros, $section_name) {
    $sql = "SELECT account, SUM(amount) as total
            FROM gl_trans 
            WHERE tran_date BETWEEN '$fromdate' AND '$todate'
              AND account LIKE '$account_prefix%'
            GROUP BY account
            ORDER BY account";
    
    $result = db_query($sql, "جلب بيانات $account_prefix");
    
    $section_total = 0;
    $has_data = false;
    
    if ($show_details) {
        while ($row = db_fetch($result)) {
            $amount = abs($row['total']);
            if ($amount > 0 || $include_zeros) {
                // جلب اسم الحساب
                $sql_name = "SELECT account_name FROM 0_chart_master WHERE account_code = '" . $row['account'] . "'";
                $result_name = db_query($sql_name, "جلب اسم الحساب");
                $name_row = db_fetch($result_name);
                $account_name = $name_row ? $name_row['account_name'] : $section_name;
                
                $rep->TextCol(0, 1, $row['account']);
                $rep->TextCol(1, 2, $account_name);
                $rep->AmountCol(2, 3, $amount, 2);
                $rep->NewLine();
                $section_total += $amount;
                $has_data = true;
            }
        }
        
        if (!$has_data) {
            $rep->TextCol(0, 2, 'لا توجد حركات');
            $rep->AmountCol(2, 3, 0, 2);
            $rep->NewLine();
        }
    } else {
        // حساب الإجمالي فقط
        $sql_total = "SELECT SUM(ABS(amount)) as total_sum
                      FROM gl_trans 
                      WHERE tran_date BETWEEN '$fromdate' AND '$todate'
                        AND account LIKE '$account_prefix%'";
        
        $result_total = db_query($sql_total, "جلب الإجمالي");
        $row_total = db_fetch($result_total);
        $section_total = $row_total['total_sum'] ? $row_total['total_sum'] : 0;
    }
    
    // إجمالي القسم
    $rep->Line($rep->row - 2);
    $rep->Font('bold');
    $rep->TextCol(0, 2, 'إجمالي ' . $section_name . ':');
    $rep->AmountCol(2, 3, $section_total, 2);
    $rep->Font();
    $rep->NewLine(1.5);
    
    return $section_total;
}
?>