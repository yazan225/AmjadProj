<?php
$path_to_root = "..";
$page_security = 'SA_GLREP';

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/reporting/includes/reporting.inc");
include_once($path_to_root . "/reporting/includes/pdf_report.inc");

if (!isset($_POST['PARAM_0']) || !isset($_POST['PARAM_1'])) {
    // عرض نموذج الإدخال
    $js = "";
    if ($SysPrefs->use_popup_windows)
        $js .= get_js_open_window(900, 500);
    
    page(_("تقرير الأرباح والخسائر"), true, false, "", $js);

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

// معالجة التقرير
$from = $_POST['PARAM_0'];
$to = $_POST['PARAM_1'];
$show_details = $_POST['PARAM_2'];
$include_zeros = $_POST['PARAM_3'];

$rep = new FrontReport(_("تقرير الأرباح والخسائر"), "ProfitLoss", user_pagesize(), 9, 'P');
$rep->Font();
$rep->Info(array(0 => $from, 1 => $to, 2 => $show_details, 3 => $include_zeros));
$rep->NewPage();

$fromdate = date2sql($from);
$todate = date2sql($to);

// عنوان التقرير
$rep->Font('bold');
$rep->TextCol(0, 5, _("تقرير الأرباح والخسائر"));
$rep->Font();
$rep->NewLine();
$rep->TextCol(0, 5, _("الفترة: ") . $from . _(" إلى ") . $to);
$rep->NewLine(2);

// الإيرادات التشغيلية (41)
$rep->Font('bold');
$rep->TextCol(0, 2, _("الإيرادات التشغيلية - إيرادات البيع"));
$rep->Font();
$rep->NewLine(1.5);

$total_41 = calculate_section($rep, '41', $fromdate, $todate, $show_details, $include_zeros, _("إيرادات البيع"));

// الإيرادات غير التشغيلية (51)
$rep->Font('bold');
$rep->TextCol(0, 2, _("الإيرادات غير التشغيلية - الإيرادات الأخرى"));
$rep->Font();
$rep->NewLine(1.5);

$total_51 = calculate_section($rep, '51', $fromdate, $todate, $show_details, $include_zeros, _("الإيرادات الأخرى"));

// إجمالي الإيرادات
$total_revenue = $total_41 + $total_51;
$rep->Line($rep->row - 2);
$rep->Font('bold');
$rep->TextCol(0, 2, _("إجمالي الإيرادات:"));
$rep->AmountCol(2, 3, $total_revenue, 2);
$rep->Font();
$rep->NewLine(2);

// التكاليف والمصاريف
$rep->Font('bold');
$rep->TextCol(0, 2, _("التكاليف والمصاريف"));
$rep->Font();
$rep->NewLine(1.5);

// تكلفة البضاعة المباعة (42)
$total_42 = calculate_section($rep, '42', $fromdate, $todate, $show_details, $include_zeros, _("تكلفة البضاعة المباعة"));

// مصاريف المبيعات (43)
$total_43 = calculate_section($rep, '43', $fromdate, $todate, $show_details, $include_zeros, _("مصاريف المبيعات"));

// المصاريف الإدارية (44)
$total_44 = calculate_section($rep, '44', $fromdate, $todate, $show_details, $include_zeros, _("المصاريف الإدارية"));

// المصاريف الأخرى (52)
$total_52 = calculate_section($rep, '52', $fromdate, $todate, $show_details, $include_zeros, _("المصاريف الأخرى"));

// ضريبة الدخل (6)
$total_6 = calculate_section($rep, '6', $fromdate, $todate, $show_details, $include_zeros, _("ضريبة الدخل"));

// إجمالي المصاريف
$total_expenses = $total_42 + $total_43 + $total_44 + $total_52 + $total_6;
$rep->Line($rep->row - 2);
$rep->Font('bold');
$rep->TextCol(0, 2, _("إجمالي المصاريف:"));
$rep->AmountCol(2, 3, $total_expenses, 2);
$rep->Font();
$rep->NewLine(2);

// حسابات الربح
$gross_profit = $total_revenue - $total_42;
$rep->Font('bold');
$rep->TextCol(0, 2, _("إجمالي الربح:"));
$rep->AmountCol(2, 3, $gross_profit, 2);
$rep->Font();
$rep->NewLine();

$operating_profit = $gross_profit - $total_43 - $total_44;
$rep->Font('bold');
$rep->TextCol(0, 2, _("الربح التشغيلي:"));
$rep->AmountCol(2, 3, $operating_profit, 2);
$rep->Font();
$rep->NewLine();

$profit_before_tax = $operating_profit + $total_51 - $total_52;
$rep->Font('bold');
$rep->TextCol(0, 2, _("الربح قبل الضرائب:"));
$rep->AmountCol(2, 3, $profit_before_tax, 2);
$rep->Font();
$rep->NewLine();

$net_profit = $profit_before_tax - $total_6;
$rep->Line($rep->row - 2);
$rep->Font('bold');
if ($net_profit >= 0) {
    $rep->TextCol(0, 2, _("صافي الربح:"));
    $rep->AmountCol(2, 3, $net_profit, 2);
} else {
    $rep->TextCol(0, 2, _("صافي الخسارة:"));
    $rep->AmountCol(2, 3, abs($net_profit), 2);
}
$rep->Font();

$rep->End();

function calculate_section($rep, $account_prefix, $fromdate, $todate, $show_details, $include_zeros, $section_name) {
    
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
        while ($myrow = db_fetch($result)) {
            $amount = $myrow['total'];
            
            if (substr($account_prefix, 0, 2) == '41' || substr($account_prefix, 0, 2) == '51') {
                $amount = abs($amount);
            } else {
                $amount = -$amount;
            }
            
            if ($amount != 0 || $include_zeros) {
                $account_name = get_account_name($myrow['account']);
                
                $rep->TextCol(0, 1, $myrow['account']);
                $rep->TextCol(1, 2, $account_name);
                $rep->AmountCol(2, 3, $amount, 2);
                $rep->NewLine();
                $section_total += $amount;
                $has_data = true;
            }
        }
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
    }
    
    if ($show_details && $has_data) {
        $rep->Line($rep->row - 2);
    }
    $rep->Font('bold');
    $rep->TextCol(0, 2, _("إجمالي ") . $section_name . ":");
    $rep->AmountCol(2, 3, $section_total, 2);
    $rep->Font();
    $rep->NewLine(1.5);
    
    return $section_total;
}
?>