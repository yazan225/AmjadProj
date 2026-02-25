<?php
$path_to_root = "..";
$page_security = 'SA_OPEN';

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");

// دالة لإنشاء استعلام SQL مع فلتر مركز التكلفة
function build_sql_query($from_sql, $to_sql, $cost_center = '') {
    $where_conditions = "tran_date BETWEEN '$from_sql' AND '$to_sql'";
    
    if (!empty($cost_center)) {
        $where_conditions .= " AND dimension_id = " . db_escape($cost_center);
    }
    
    return $where_conditions;
}

// دالة للحصول على اسم مركز التكلفة
function get_cost_center_name($id) {
    $sql = "SELECT name FROM " . TB_PREF . "dimensions WHERE id = " . db_escape($id);
    $result = db_query($sql);
    $row = db_fetch($result);
    return $row ? $row['name'] : 'غير معروف';
}

// دالة للحصول على اسم الحساب
function get_account_name($account_code) {
    $sql = "SELECT account_name FROM " . TB_PREF . "chart_master WHERE account_code = " . db_escape($account_code);
    $result = db_query($sql);
    $row = db_fetch($result);
    return $row ? $row['account_name'] : $account_code;
}

// دالة للحصول على الرصيد الافتتاحي للحساب حتى تاريخ معين
function get_opening_balance($account_code, $before_date) {
    $sql = "SELECT SUM(amount) as opening_balance 
            FROM " . TB_PREF . "gl_trans 
            WHERE account = " . db_escape($account_code) . " 
            AND tran_date < '$before_date'";
    
    $result = db_query($sql);
    $row = db_fetch($result);
    return $row ? $row['opening_balance'] : 0;
}

// دالة للحصول على حركات الحساب خلال الفترة
function get_period_movements($account_code, $from_date, $to_date) {
    $sql = "SELECT 
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as total_credit
            FROM " . TB_PREF . "gl_trans 
            WHERE account = " . db_escape($account_code) . " 
            AND tran_date BETWEEN '$from_date' AND '$to_date'";
    
    $result = db_query($sql);
    $row = db_fetch($result);
    return $row ? $row : array('total_debit' => 0, 'total_credit' => 0);
}

// دالة محسنة لحساب صافي الدخل بنفس طريقة تقرير الأرباح والخسائر
function calculate_net_income($from_date, $to_date, $cost_center = '') {
    $where_conditions = "tran_date BETWEEN '$from_date' AND '$to_date'";
    
    if (!empty($cost_center)) {
        $where_conditions .= " AND dimension_id = " . db_escape($cost_center);
    }
    
    // حساب الإيرادات (حسابات 41)
    $revenue_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                    WHERE $where_conditions AND account LIKE '41%'";
    $result = db_query($revenue_sql);
    $revenue_row = db_fetch($result);
    $total_revenue = $revenue_row['total'] ? abs($revenue_row['total']) : 0;
    
    // حساب التكاليف (حسابات 42)
    $cogs_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                 WHERE $where_conditions AND account LIKE '42%'";
    $result = db_query($cogs_sql);
    $cogs_row = db_fetch($result);
    $total_cogs = $cogs_row['total'] ? abs($cogs_row['total']) : 0;
    
    // حساب مصاريف المبيعات (حسابات 43)
    $sales_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                      WHERE $where_conditions AND account LIKE '43%'";
    $result = db_query($sales_exp_sql);
    $sales_exp_row = db_fetch($result);
    $total_sales_exp = $sales_exp_row['total'] ? abs($sales_exp_row['total']) : 0;
    
    // حساب المصاريف الإدارية (حسابات 44)
    $admin_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                      WHERE $where_conditions AND account LIKE '44%'";
    $result = db_query($admin_exp_sql);
    $admin_exp_row = db_fetch($result);
    $total_admin_exp = $admin_exp_row['total'] ? abs($admin_exp_row['total']) : 0;
    
    // حساب المصاريف الأخرى (حسابات 52)
    $other_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                      WHERE $where_conditions AND account LIKE '52%'";
    $result = db_query($other_exp_sql);
    $other_exp_row = db_fetch($result);
    $total_other_exp = $other_exp_row['total'] ? abs($other_exp_row['total']) : 0;
    
    // حساب الإيرادات الأخرى (حسابات 51)
    $other_rev_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                      WHERE $where_conditions AND account LIKE '51%'";
    $result = db_query($other_rev_sql);
    $other_rev_row = db_fetch($result);
    $total_other_rev = $other_rev_row['total'] ? abs($other_rev_row['total']) : 0;
    
    // نفس حسابات تقرير الأرباح والخسائر
    $gross_profit = $total_revenue - $total_cogs;
    $total_operating_expenses = $total_sales_exp + $total_admin_exp + $total_other_exp;
    $operating_profit = $gross_profit - $total_operating_expenses;
    $profit_before_tax = $operating_profit + $total_other_rev;
    $net_income = $profit_before_tax;
    
    return array(
        'revenue' => $total_revenue,
        'cogs' => $total_cogs,
        'sales_expenses' => $total_sales_exp,
        'admin_expenses' => $total_admin_exp,
        'other_expenses' => $total_other_exp,
        'other_revenue' => $total_other_rev,
        'gross_profit' => $gross_profit,
        'operating_expenses' => $total_operating_expenses,
        'operating_profit' => $operating_profit,
        'net_income' => $net_income
    );
}

// دالة للحصول على بيانات الميزانية العمومية
function get_balance_sheet_data($where_conditions, $from_date, $to_date, $report_type = 'summary') {
    if ($report_type == 'summary') {
        $sql = "SELECT 
                    account,
                    CASE 
                        WHEN account LIKE '1%' THEN 'الأصول'
                        WHEN account LIKE '2%' THEN 'الخصوم'
                        WHEN account LIKE '3%' THEN 'حقوق الملكية'
                        ELSE 'حقوق الملكية'
                    END as account_group,
                    CASE 
                        WHEN account LIKE '1%' THEN '1'
                        WHEN account LIKE '2%' THEN '2'
                        WHEN account LIKE '3%' THEN '3'
                        ELSE '3'
                    END as group_order
                FROM " . TB_PREF . "gl_trans 
                WHERE $where_conditions
                GROUP BY account, account_group, group_order
                ORDER BY group_order, account";
    } else {
        $sql = "SELECT DISTINCT account
                FROM " . TB_PREF . "gl_trans 
                WHERE $where_conditions
                ORDER BY account";
    }
    
    $result = db_query($sql);
    $data = array();
    
    while ($row = db_fetch($result)) {
        $account_code = $row['account'];
        $account_name = get_account_name($account_code);
        
        // الرصيد الافتتاحي
        $opening_balance = get_opening_balance($account_code, $from_date);
        
        // حركات الفترة
        $movements = get_period_movements($account_code, $from_date, $to_date);
        
        // الرصيد الختامي
        $closing_balance = $opening_balance + $movements['total_debit'] + $movements['total_credit'];
        
        // تحديد نوع الحساب
        if (preg_match('/^1/', $account_code)) {
            $group = 'الأصول';
            $group_order = '1';
        } elseif (preg_match('/^2/', $account_code)) {
            $group = 'الخصوم';
            $group_order = '2';
        } else {
            $group = 'حقوق الملكية';
            $group_order = '3';
        }
        
        $account_data = array(
            'account_code' => $account_code,
            'account_name' => $account_name,
            'account_group' => $group,
            'group_order' => $group_order,
            'opening_balance' => $opening_balance,
            'period_debit' => $movements['total_debit'],
            'period_credit' => $movements['total_credit'],
            'closing_balance' => $closing_balance
        );
        
        $data[] = $account_data;
    }
    
    return $data;
}

// إذا كان طلب تصدير Excel
if (isset($_GET['action']) && $_GET['action'] == 'excel') {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $cost_center = $_GET['cost_center'] ?? '';
    $report_type = $_GET['report_type'] ?? 'summary';
    
    if (!empty($from) && !empty($to)) {
        $from_sql = date2sql($from);
        $to_sql = date2sql($to);
        
        $where_conditions = build_sql_query($from_sql, $to_sql, $cost_center);
        
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"balance_sheet_" . date('Y-m-d') . ".xls\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo "<html dir='rtl'>";
        echo "<head>";
        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">";
        echo "<style>";
        echo "body { font-family: Arial; direction: rtl; }";
        echo "table { border-collapse: collapse; width: 100%; }";
        echo "th, td { border: 1px solid #000; padding: 8px; text-align: right; }";
        echo "th { background-color: #f2f2f2; }";
        echo ".debit { color: #006400; }";
        echo ".credit { color: #8B0000; }";
        echo ".total { font-weight: bold; background-color: #e6f3ff; }";
        echo ".assets { background-color: #e8f5e8; }";
        echo ".liabilities { background-color: #ffe8e8; }";
        echo ".equity { background-color: #e8f0ff; }";
        echo ".income-statement { background-color: #fff8e1; }";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        
        echo "<h1 style='text-align: center;'>الميزانية العمومية - Balance Sheet</h1>";
        echo "<h3 style='text-align: center;'>" . ($report_type == 'summary' ? 'تقرير ملخص' : 'تقرير مفصل') . "</h3>";
        echo "<h4 style='text-align: center;'>الفترة: $from إلى $to</h4>";
        
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4 style='text-align: center;'>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<p style='text-align: center;'>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</p>";
        
        $balance_data = get_balance_sheet_data($where_conditions, $from_sql, $to_sql, $report_type);
        $net_income_data = calculate_net_income($from_sql, $to_sql, $cost_center);
        
        echo "<table>";
        
        if ($report_type == 'detailed') {
            echo "<tr>";
            echo "<th style='background: #2c3e50; color: white;'>رقم الحساب</th>";
            echo "<th style='background: #2c3e50; color: white;'>اسم الحساب</th>";
            echo "<th style='background: #8e44ad; color: white;'>الرصيد الافتتاحي</th>";
            echo "<th style='background: #27ae60; color: white;'>مدين الفترة</th>";
            echo "<th style='background: #c0392b; color: white;'>دائن الفترة</th>";
            echo "<th style='background: #2980b9; color: white;'>الرصيد الختامي</th>";
            echo "</tr>";
            
            $current_group = '';
            
            foreach ($balance_data as $account) {
                if ($current_group != $account['account_group']) {
                    $current_group = $account['account_group'];
                    echo "<tr class='" . strtolower(str_replace(' ', '_', $current_group)) . "'>";
                    echo "<td colspan='6' style='background: #34495e; color: white; font-weight: bold; text-align: center;'>" . $current_group . "</td>";
                    echo "</tr>";
                }
                
                echo "<tr>";
                echo "<td>" . $account['account_code'] . "</td>";
                echo "<td>" . $account['account_name'] . "</td>";
                echo "<td>" . number_format($account['opening_balance'], 2) . "</td>";
                echo "<td class='debit'>" . number_format(abs($account['period_debit']), 2) . "</td>";
                echo "<td class='credit'>" . number_format(abs($account['period_credit']), 2) . "</td>";
                echo "<td>" . number_format($account['closing_balance'], 2) . "</td>";
                echo "</tr>";
            }
            
        } else {
            echo "<tr>";
            echo "<th style='background: #2c3e50; color: white;'>مجموعة الحسابات</th>";
            echo "<th style='background: #8e44ad; color: white;'>الرصيد الافتتاحي</th>";
            echo "<th style='background: #27ae60; color: white;'>مدين الفترة</th>";
            echo "<th style='background: #c0392b; color: white;'>دائن الفترة</th>";
            echo "<th style='background: #2980b9; color: white;'>الرصيد الختامي</th>";
            echo "</tr>";
            
            $group_totals = array();
            
            foreach ($balance_data as $account) {
                $group = $account['account_group'];
                
                if (!isset($group_totals[$group])) {
                    $group_totals[$group] = array(
                        'opening' => 0,
                        'debit' => 0,
                        'credit' => 0,
                        'closing' => 0
                    );
                }
                
                $group_totals[$group]['opening'] += $account['opening_balance'];
                $group_totals[$group]['debit'] += abs($account['period_debit']);
                $group_totals[$group]['credit'] += abs($account['period_credit']);
                $group_totals[$group]['closing'] += $account['closing_balance'];
            }
            
            // إضافة صافي الدخل إلى حقوق الملكية (بنفس الرقم من تقرير الأرباح والخسائر)
            $group_totals['حقوق الملكية']['closing'] += $net_income_data['net_income'];
            
            $grand_total_opening = 0;
            $grand_total_debit = 0;
            $grand_total_credit = 0;
            $grand_total_closing = 0;
            
            $groups_order = array('الأصول', 'الخصوم', 'حقوق الملكية');
            foreach ($groups_order as $group) {
                if (isset($group_totals[$group])) {
                    $totals = $group_totals[$group];
                    $css_class = strtolower(str_replace(' ', '_', $group));
                    
                    echo "<tr class='$css_class'>";
                    echo "<td>" . $group . "</td>";
                    echo "<td>" . number_format($totals['opening'], 2) . "</td>";
                    echo "<td class='debit'>" . number_format($totals['debit'], 2) . "</td>";
                    echo "<td class='credit'>" . number_format($totals['credit'], 2) . "</td>";
                    echo "<td>" . number_format($totals['closing'], 2) . "</td>";
                    echo "</tr>";
                    
                    $grand_total_opening += $totals['opening'];
                    $grand_total_debit += $totals['debit'];
                    $grand_total_credit += $totals['credit'];
                    $grand_total_closing += $totals['closing'];
                }
            }
            
            echo "<tr class='total'>";
            echo "<td><strong>الإجمالي العام</strong></td>";
            echo "<td><strong>" . number_format($grand_total_opening, 2) . "</strong></td>";
            echo "<td class='debit'><strong>" . number_format($grand_total_debit, 2) . "</strong></td>";
            echo "<td class='credit'><strong>" . number_format($grand_total_credit, 2) . "</strong></td>";
            echo "<td><strong>" . number_format($grand_total_closing, 2) . "</strong></td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // إضافة قسم الأرباح والخسائر بنفس طريقة التقرير الأصلي
        echo "<br><table class='income-statement'>";
        echo "<tr><th colspan='3' style='background: #8e44ad; color: white;'>حساب الأرباح والخسائر (قائمة الدخل)</th></tr>";
        
        // الإيرادات
        echo "<tr><td colspan='3' style='background: #2c3e50; color: white; text-align: center; font-weight: bold;'>الإيرادات</td></tr>";
        echo "<tr><td style='width: 60%;'>المبيعات (41)</td><td style='width: 30%;' class='credit'>" . number_format($net_income_data['revenue'], 2) . "</td><td style='width: 10%;'></td></tr>";
        
        if ($net_income_data['other_revenue'] > 0) {
            echo "<tr><td>الإيرادات الأخرى (51)</td><td class='credit'>" . number_format($net_income_data['other_revenue'], 2) . "</td><td></td></tr>";
        }
        
        echo "<tr style='background: #e8f5e8;'><td><strong>إجمالي الإيرادات</strong></td><td class='credit'><strong>" . number_format($net_income_data['revenue'] + $net_income_data['other_revenue'], 2) . "</strong></td><td></td></tr>";
        
        // التكاليف
        echo "<tr><td colspan='3' style='background: #c0392b; color: white; text-align: center; font-weight: bold;'>التكاليف</td></tr>";
        echo "<tr><td>تكلفة البضاعة المباعة (42)</td><td class='debit'>(" . number_format($net_income_data['cogs'], 2) . ")</td><td></td></tr>";
        echo "<tr style='background: #fdeaea;'><td><strong>إجمالي التكاليف</strong></td><td class='debit'><strong>(" . number_format($net_income_data['cogs'], 2) . ")</strong></td><td></td></tr>";
        
        // إجمالي الربح
        $gross_profit_color = $net_income_data['gross_profit'] >= 0 ? '#27ae60' : '#c0392b';
        echo "<tr style='background: " . ($net_income_data['gross_profit'] >= 0 ? '#d4edda' : '#f8d7da') . ";'>";
        echo "<td><strong>إجمالي الربح</strong></td>";
        echo "<td style='color: $gross_profit_color;'><strong>" . number_format($net_income_data['gross_profit'], 2) . "</strong></td>";
        echo "<td></td></tr>";
        
        // المصاريف التشغيلية
        if ($net_income_data['operating_expenses'] > 0) {
            echo "<tr><td colspan='3' style='background: #e67e22; color: white; text-align: center; font-weight: bold;'>المصاريف التشغيلية</td></tr>";
            
            if ($net_income_data['sales_expenses'] > 0) {
                echo "<tr><td>مصاريف المبيعات (43)</td><td class='debit'>(" . number_format($net_income_data['sales_expenses'], 2) . ")</td><td></td></tr>";
            }
            
            if ($net_income_data['admin_expenses'] > 0) {
                echo "<tr><td>المصاريف الإدارية (44)</td><td class='debit'>(" . number_format($net_income_data['admin_expenses'], 2) . ")</td><td></td></tr>";
            }
            
            if ($net_income_data['other_expenses'] > 0) {
                echo "<tr><td>المصاريف الأخرى (52)</td><td class='debit'>(" . number_format($net_income_data['other_expenses'], 2) . ")</td><td></td></tr>";
            }
            
            echo "<tr style='background: #fdeaea;'><td><strong>إجمالي المصاريف التشغيلية</strong></td><td class='debit'><strong>(" . number_format($net_income_data['operating_expenses'], 2) . ")</strong></td><td></td></tr>";
            
            // الربح التشغيلي
            echo "<tr style='background: #d1ecf1;'>";
            echo "<td><strong>الربح التشغيلي</strong></td>";
            echo "<td><strong>" . number_format($net_income_data['operating_profit'], 2) . "</strong></td>";
            echo "<td></td></tr>";
        }
        
        // صافي الدخل
        echo "<tr class='total' style='background: " . ($net_income_data['net_income'] >= 0 ? '#d4edda' : '#f8d7da') . ";'>";
        echo "<td><strong>" . ($net_income_data['net_income'] >= 0 ? 'صافي الربح' : 'صافي الخسارة') . "</strong></td>";
        if ($net_income_data['net_income'] >= 0) {
            echo "<td class='credit'><strong>" . number_format($net_income_data['net_income'], 2) . "</strong></td>";
        } else {
            echo "<td class='debit'><strong>(" . number_format(abs($net_income_data['net_income']), 2) . ")</strong></td>";
        }
        echo "<td><strong>" . ($net_income_data['net_income'] >= 0 ? 'ربح' : 'خسارة') . "</strong></td></tr>";
        echo "</table>";
        
        echo "</body></html>";
        exit();
    }
}

// باقي الكود (الطباعة والعرض الرئيسي) يبقى كما هو مع استخدام الدالة المحسنة
// [يتبع بنفس الطريقة مع التأكد من استخدام calculate_net_income() المحسنة]

// باقي الكود يبقى كما هو (الطباعة والعرض الرئيسي)...
// [يتبع بنفس الطريقة مع التأكد من استخدام الدالة المحسنة]

// إذا كان طلب طباعة
if (isset($_GET['action']) && $_GET['action'] == 'print') {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $cost_center = $_GET['cost_center'] ?? '';
    $report_type = $_GET['report_type'] ?? 'summary';
    
    if (!empty($from) && !empty($to)) {
        $from_sql = date2sql($from);
        $to_sql = date2sql($to);
        
        $where_conditions = build_sql_query($from_sql, $to_sql, $cost_center);
        
        // الحصول على بيانات الميزانية العمومية
        $balance_data = get_balance_sheet_data($where_conditions, $from_sql, $to_sql, $report_type);
        
        // حساب صافي الدخل
        $net_income_data = calculate_net_income($from_sql, $to_sql, $cost_center);
        
        // صفحة الطباعة المخصصة
        echo "<!DOCTYPE html>
        <html dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <title>طباعة الميزانية العمومية</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    direction: rtl; 
                    margin: 0; 
                    padding: 20px; 
                    background: white; 
                    color: black;
                }
                .header { 
                    text-align: center; 
                    margin-bottom: 30px; 
                    border-bottom: 2px solid #000; 
                    padding-bottom: 15px;
                }
                h1 { 
                    color: #000; 
                    margin: 0 0 10px 0; 
                    font-size: 24px;
                }
                h3 { 
                    color: #333; 
                    margin: 5px 0; 
                    font-size: 16px;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 20px 0; 
                    border: 2px solid #000;
                }
                th, td { 
                    border: 1px solid #000; 
                    padding: 10px; 
                    text-align: right;
                }
                th { 
                    background-color: #f0f0f0; 
                    font-weight: bold;
                }
                .debit { color: #006400; font-weight: bold; }
                .credit { color: #8B0000; font-weight: bold; }
                .opening { color: #8e44ad; font-weight: bold; }
                .closing { color: #2980b9; font-weight: bold; }
                .total { 
                    background-color: #e6f3ff; 
                    font-weight: bold;
                }
                .assets { background-color: #e8f5e8; }
                .liabilities { background-color: #ffe8e8; }
                .equity { background-color: #e8f0ff; }
                .income-statement { background-color: #fff8e1; }
                .no-print { display: none; }
                @media print {
                    body { margin: 0; padding: 15px; }
                    .no-print { display: none; }
                    table { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body onload='window.print();'>
            <div class='header'>
                <h1>الميزانية العمومية - Balance Sheet</h1>
                <h3>" . ($report_type == 'summary' ? 'تقرير ملخص' : 'تقرير مفصل') . "</h3>
                <h4>الفترة: $from إلى $to</h4>";
        
        // إضافة اسم مركز التكلفة إذا تم اختياره
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<p>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</p>
            </div>";
        
        // جدول التقرير
        if ($report_type == 'detailed') {
            // التقرير المفصل
            echo "<table>
                    <tr>
                        <th style='background:#2c3e50; color:white;'>رقم الحساب</th>
                        <th style='background:#2c3e50; color:white;'>اسم الحساب</th>
                        <th style='background:#8e44ad; color:white;'>الرصيد الافتتاحي</th>
                        <th style='background:#27ae60; color:white;'>مدين الفترة</th>
                        <th style='background:#c0392b; color:white;'>دائن الفترة</th>
                        <th style='background:#2980b9; color:white;'>الرصيد الختامي</th>
                    </tr>";
            
            $current_group = '';
            $grand_total_opening = 0;
            $grand_total_debit = 0;
            $grand_total_credit = 0;
            $grand_total_closing = 0;
            
            foreach ($balance_data as $account) {
                if ($current_group != $account['account_group']) {
                    $current_group = $account['account_group'];
                    $css_class = strtolower(str_replace(' ', '_', $current_group));
                    echo "<tr class='$css_class'>
                            <td colspan='6' style='background: #34495e; color: white; font-weight: bold; text-align: center;'>" . $current_group . "</td>
                          </tr>";
                }
                
                echo "<tr>
                        <td>" . $account['account_code'] . "</td>
                        <td>" . $account['account_name'] . "</td>
                        <td class='opening'>" . number_format($account['opening_balance'], 2) . " ر.س</td>
                        <td class='debit'>" . number_format(abs($account['period_debit']), 2) . " ر.س</td>
                        <td class='credit'>" . number_format(abs($account['period_credit']), 2) . " ر.س</td>
                        <td class='closing'>" . number_format($account['closing_balance'], 2) . " ر.س</td>
                      </tr>";
                
                $grand_total_opening += $account['opening_balance'];
                $grand_total_debit += abs($account['period_debit']);
                $grand_total_credit += abs($account['period_credit']);
                $grand_total_closing += $account['closing_balance'];
            }
            
            // الإجماليات
            echo "<tr class='total'>
                    <td colspan='2'><strong>الإجمالي</strong></td>
                    <td class='opening'><strong>" . number_format($grand_total_opening, 2) . " ر.س</strong></td>
                    <td class='debit'><strong>" . number_format($grand_total_debit, 2) . " ر.س</strong></td>
                    <td class='credit'><strong>" . number_format($grand_total_credit, 2) . " ر.س</strong></td>
                    <td class='closing'><strong>" . number_format($grand_total_closing, 2) . " ر.س</strong></td>
                  </tr>";
            
            echo "</table>";
        } else {
            // التقرير الملخص
            echo "<table>
                    <tr>
                        <th style='background:#2c3e50; color:white;'>مجموعة الحسابات</th>
                        <th style='background:#8e44ad; color:white;'>الرصيد الافتتاحي</th>
                        <th style='background:#27ae60; color:white;'>مدين الفترة</th>
                        <th style='background:#c0392b; color:white;'>دائن الفترة</th>
                        <th style='background:#2980b9; color:white;'>الرصيد الختامي</th>
                    </tr>";
            
            $group_totals = array();
            
            foreach ($balance_data as $account) {
                $group = $account['account_group'];
                
                if (!isset($group_totals[$group])) {
                    $group_totals[$group] = array(
                        'opening' => 0,
                        'debit' => 0,
                        'credit' => 0,
                        'closing' => 0
                    );
                }
                
                $group_totals[$group]['opening'] += $account['opening_balance'];
                $group_totals[$group]['debit'] += abs($account['period_debit']);
                $group_totals[$group]['credit'] += abs($account['period_credit']);
                $group_totals[$group]['closing'] += $account['closing_balance'];
            }
            
            // إضافة صافي الدخل إلى حقوق الملكية
            $group_totals['حقوق الملكية']['closing'] += $net_income_data['net_income'];
            
            $grand_total_opening = 0;
            $grand_total_debit = 0;
            $grand_total_credit = 0;
            $grand_total_closing = 0;
            
            // عرض المجموعات بالترتيب الصحيح
            $groups_order = array('الأصول', 'الخصوم', 'حقوق الملكية');
            foreach ($groups_order as $group) {
                if (isset($group_totals[$group])) {
                    $totals = $group_totals[$group];
                    $css_class = strtolower(str_replace(' ', '_', $group));
                    
                    echo "<tr class='$css_class'>
                            <td>" . $group . "</td>
                            <td class='opening'>" . number_format($totals['opening'], 2) . " ر.س</td>
                            <td class='debit'>" . number_format($totals['debit'], 2) . " ر.س</td>
                            <td class='credit'>" . number_format($totals['credit'], 2) . " ر.س</td>
                            <td class='closing'>" . number_format($totals['closing'], 2) . " ر.س</td>
                          </tr>";
                    
                    $grand_total_opening += $totals['opening'];
                    $grand_total_debit += $totals['debit'];
                    $grand_total_credit += $totals['credit'];
                    $grand_total_closing += $totals['closing'];
                }
            }
            
            // الإجماليات
            echo "<tr class='total'>
                    <td><strong>الإجمالي العام</strong></td>
                    <td class='opening'><strong>" . number_format($grand_total_opening, 2) . " ر.س</strong></td>
                    <td class='debit'><strong>" . number_format($grand_total_debit, 2) . " ر.س</strong></td>
                    <td class='credit'><strong>" . number_format($grand_total_credit, 2) . " ر.س</strong></td>
                    <td class='closing'><strong>" . number_format($grand_total_closing, 2) . " ر.س</strong></td>
                  </tr>";
            
            echo "</table>";
        }
        
        // إضافة قسم الأرباح والخسائر
        echo "<table class='income-statement'>
                <tr>
                    <th colspan='3' style='background: #8e44ad; color: white; text-align: center;'>
                        حساب الأرباح والخسائر (قائمة الدخل)
                    </th>
                </tr>
                <tr>
                    <td style='width: 60%;'>إجمالي الإيرادات:</td>
                    <td class='credit' style='width: 30%;'>" . number_format($net_income_data['revenue'], 2) . " ر.س</td>
                    <td style='width: 10%;'></td>
                </tr>
                <tr>
                    <td>إجمالي المصاريف:</td>
                    <td class='debit'>" . number_format($net_income_data['expenses'], 2) . " ر.س</td>
                    <td></td>
                </tr>
                <tr class='total'>
                    <td><strong>صافي الدخل:</strong></td>";
        
        if ($net_income_data['net_income'] >= 0) {
            echo "<td class='credit'><strong>" . number_format($net_income_data['net_income'], 2) . " ر.س</strong></td>";
            echo "<td><strong>ربح</strong></td>";
        } else {
            echo "<td class='debit'><strong>(" . number_format(abs($net_income_data['net_income']), 2) . ") ر.س</strong></td>";
            echo "<td><strong>خسارة</strong></td>";
        }
        
        echo "  </tr>
              </table>";
        
        echo "<div class='no-print' style='text-align:center; margin-top:20px;'>
                <button onclick='window.print()' style='padding:10px 20px; background:#27ae60; color:white; border:none; border-radius:5px; cursor:pointer; margin:5px;'>🖨️ طباعة مرة أخرى</button>
                <button onclick='window.close()' style='padding:10px 20px; background:#e74c3c; color:white; border:none; border-radius:5px; cursor:pointer; margin:5px;'>❌ إغلاق</button>
              </div>
            </body>
            </html>";
        exit();
    }
}

// التحقق من إرسال النموذج
if (isset($_POST['Generate'])) {
    $from = $_POST['PARAM_0'] ?? '';
    $to = $_POST['PARAM_1'] ?? '';
    $cost_center = $_POST['PARAM_2'] ?? '';
    $report_type = $_POST['PARAM_3'] ?? 'summary';
    
    if (!empty($from) && !empty($to)) {
        // تحويل التواريخ
        $from_sql = date2sql($from);
        $to_sql = date2sql($to);
        
        $where_conditions = build_sql_query($from_sql, $to_sql, $cost_center);
        
        page("الميزانية العمومية");
        
        echo "<div style='padding: 20px; direction: rtl; font-family: Arial, sans-serif; background: #f8f9fa; min-height: 100vh;'>";
        
        // أزرار التحكم في أعلى الصفحة
        echo "<div style='text-align: center; margin-bottom: 20px; padding: 15px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";
        echo "<a href='balancesheet2.php' style='padding: 12px 25px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; margin: 5px; display: inline-block;'>🔄 تقرير جديد</a>";
        echo "<a href='balancesheet2.php?action=print&from=" . urlencode($from) . "&to=" . urlencode($to) . "&cost_center=" . urlencode($cost_center) . "&report_type=" . urlencode($report_type) . "' target='_blank' style='padding: 12px 25px; background: #27ae60; color: white; text-decoration: none; border-radius: 6px; margin: 5px; display: inline-block;'>🖨️ طباعة التقرير</a>";
        echo "<a href='balancesheet2.php?action=excel&from=" . urlencode($from) . "&to=" . urlencode($to) . "&cost_center=" . urlencode($cost_center) . "&report_type=" . urlencode($report_type) . "' style='padding: 12px 25px; background: #f39c12; color: white; text-decoration: none; border-radius: 6px; margin: 5px; display: inline-block;'>📊 تصدير Excel</a>";
        echo "</div>";
        
        echo "<div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px;'>";
        
        // رأس التقرير
        echo "<div style='text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #3498db;'>";
        echo "<h1 style='color: #2c3e50; margin: 0; font-size: 28px;'>الميزانية العمومية - Balance Sheet</h1>";
        echo "<h3 style='color: #7f8c8d; margin: 10px 0;'>" . ($report_type == 'summary' ? 'تقرير ملخص' : 'تقرير مفصل') . "</h3>";
        echo "<h4 style='color: #7f8c8d; margin: 10px 0;'>الفترة: $from إلى $to</h4>";
        
        // إضافة اسم مركز التكلفة إذا تم اختياره
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4 style='color: #2980b9; margin: 5px 0;'>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<p style='color: #95a5a6; margin: 5px 0;'>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</p>";
        echo "</div>";
        
        // الحصول على بيانات الميزانية العمومية
        $balance_data = get_balance_sheet_data($where_conditions, $from_sql, $to_sql, $report_type);
        
        // حساب صافي الدخل
        $net_income_data = calculate_net_income($from_sql, $to_sql, $cost_center);
        
        // عرض الميزانية العمومية
        echo "<div style='max-width: 1200px; margin: 0 auto;'>";
        
        if ($report_type == 'detailed') {
            // التقرير المفصل
            echo "<table border='0' cellpadding='10' style='border-collapse: collapse; width: 100%; margin: 20px 0; border: 1px solid #ddd;'>";
            
            echo "<tr style='background: #2c3e50; color: white;'>";
            echo "<th style='padding: 12px; text-align: center;'>رقم الحساب</th>";
            echo "<th style='padding: 12px; text-align: center;'>اسم الحساب</th>";
            echo "<th style='padding: 12px; text-align: center; background: #8e44ad;'>الرصيد الافتتاحي</th>";
            echo "<th style='padding: 12px; text-align: center; background: #27ae60;'>مدين الفترة</th>";
            echo "<th style='padding: 12px; text-align: center; background: #c0392b;'>دائن الفترة</th>";
            echo "<th style='padding: 12px; text-align: center; background: #2980b9;'>الرصيد الختامي</th>";
            echo "</tr>";
            
            $current_group = '';
            $grand_total_opening = 0;
            $grand_total_debit = 0;
            $grand_total_credit = 0;
            $grand_total_closing = 0;
            
            foreach ($balance_data as $account) {
                if ($current_group != $account['account_group']) {
                    $current_group = $account['account_group'];
                    $css_class = strtolower(str_replace(' ', '_', $current_group));
                    echo "<tr style='background: #34495e; color: white;'>";
                    echo "<td colspan='6' style='padding: 15px; text-align: center; font-weight: bold; font-size: 16px;'>" . $current_group . "</td>";
                    echo "</tr>";
                }
                
                echo "<tr style='background: #f8f9fa;'>";
                echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>" . $account['account_code'] . "</td>";
                echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>" . $account['account_name'] . "</td>";
                echo "<td style='text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6; color: #8e44ad; font-weight: bold;'>" . number_format($account['opening_balance'], 2) . " ر.س</td>";
                echo "<td style='text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6; color: #27ae60; font-weight: bold;'>" . number_format(abs($account['period_debit']), 2) . " ر.س</td>";
                echo "<td style='text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6; color: #e74c3c; font-weight: bold;'>" . number_format(abs($account['period_credit']), 2) . " ر.س</td>";
                echo "<td style='text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6; color: #2980b9; font-weight: bold;'>" . number_format($account['closing_balance'], 2) . " ر.س</td>";
                echo "</tr>";
                
                $grand_total_opening += $account['opening_balance'];
                $grand_total_debit += abs($account['period_debit']);
                $grand_total_credit += abs($account['period_credit']);
                $grand_total_closing += $account['closing_balance'];
            }
            
            // الإجماليات
            echo "<tr style='background: #e6f3ff; font-weight: bold;'>";
            echo "<td colspan='2' style='padding: 15px; text-align: center;'>الإجمالي</td>";
            echo "<td style='text-align: left; padding: 15px; color: #8e44ad;'>" . number_format($grand_total_opening, 2) . " ر.س</td>";
            echo "<td style='text-align: left; padding: 15px; color: #27ae60;'>" . number_format($grand_total_debit, 2) . " ر.س</td>";
            echo "<td style='text-align: left; padding: 15px; color: #e74c3c;'>" . number_format($grand_total_credit, 2) . " ر.س</td>";
            echo "<td style='text-align: left; padding: 15px; color: #2980b9;'>" . number_format($grand_total_closing, 2) . " ر.س</td>";
            echo "</tr>";
            
            echo "</table>";
        } else {
            // التقرير الملخص
            echo "<table border='0' cellpadding='10' style='border-collapse: collapse; width: 100%; margin: 20px 0; border: 1px solid #ddd;'>";
            
            echo "<tr style='background: #2c3e50; color: white;'>";
            echo "<th style='padding: 12px; text-align: center;'>مجموعة الحسابات</th>";
            echo "<th style='padding: 12px; text-align: center; background: #8e44ad;'>الرصيد الافتتاحي</th>";
            echo "<th style='padding: 12px; text-align: center; background: #27ae60;'>مدين الفترة</th>";
            echo "<th style='padding: 12px; text-align: center; background: #c0392b;'>دائن الفترة</th>";
            echo "<th style='padding: 12px; text-align: center; background: #2980b9;'>الرصيد الختامي</th>";
            echo "</tr>";
            
            $group_totals = array();
            
            foreach ($balance_data as $account) {
                $group = $account['account_group'];
                
                if (!isset($group_totals[$group])) {
                    $group_totals[$group] = array(
                        'opening' => 0,
                        'debit' => 0,
                        'credit' => 0,
                        'closing' => 0
                    );
                }
                
                $group_totals[$group]['opening'] += $account['opening_balance'];
                $group_totals[$group]['debit'] += abs($account['period_debit']);
                $group_totals[$group]['credit'] += abs($account['period_credit']);
                $group_totals[$group]['closing'] += $account['closing_balance'];
            }
            
            // إضافة صافي الدخل إلى حقوق الملكية
            $group_totals['حقوق الملكية']['closing'] += $net_income_data['net_income'];
            
            $grand_total_opening = 0;
            $grand_total_debit = 0;
            $grand_total_credit = 0;
            $grand_total_closing = 0;
            
            // عرض المجموعات بالترتيب الصحيح
            $groups_order = array('الأصول', 'الخصوم', 'حقوق الملكية');
            foreach ($groups_order as $group) {
                if (isset($group_totals[$group])) {
                    $totals = $group_totals[$group];
                    $css_class = strtolower(str_replace(' ', '_', $group));
                    $bg_color = $group == 'الأصول' ? '#e8f5e8' : ($group == 'الخصوم' ? '#ffe8e8' : '#e8f0ff');
                    
                    echo "<tr style='background: $bg_color;'>";
                    echo "<td style='padding: 12px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>" . $group . "</td>";
                    echo "<td style='text-align: left; padding: 12px; border-bottom: 1px solid #dee2e6; color: #8e44ad; font-weight: bold;'>" . number_format($totals['opening'], 2) . " ر.س</td>";
                    echo "<td style='text-align: left; padding: 12px; border-bottom: 1px solid #dee2e6; color: #27ae60; font-weight: bold;'>" . number_format($totals['debit'], 2) . " ر.س</td>";
                    echo "<td style='text-align: left; padding: 12px; border-bottom: 1px solid #dee2e6; color: #e74c3c; font-weight: bold;'>" . number_format($totals['credit'], 2) . " ر.س</td>";
                    echo "<td style='text-align: left; padding: 12px; border-bottom: 1px solid #dee2e6; color: #2980b9; font-weight: bold;'>" . number_format($totals['closing'], 2) . " ر.س</td>";
                    echo "</tr>";
                    
                    $grand_total_opening += $totals['opening'];
                    $grand_total_debit += $totals['debit'];
                    $grand_total_credit += $totals['credit'];
                    $grand_total_closing += $totals['closing'];
                }
            }
            
            // الإجماليات
            echo "<tr style='background: #e6f3ff; font-weight: bold;'>";
            echo "<td style='padding: 15px; text-align: center;'>الإجمالي العام</td>";
            echo "<td style='text-align: left; padding: 15px; color: #8e44ad;'>" . number_format($grand_total_opening, 2) . " ر.س</td>";
            echo "<td style='text-align: left; padding: 15px; color: #27ae60;'>" . number_format($grand_total_debit, 2) . " ر.س</td>";
            echo "<td style='text-align: left; padding: 15px; color: #e74c3c;'>" . number_format($grand_total_credit, 2) . " ر.س</td>";
            echo "<td style='text-align: left; padding: 15px; color: #2980b9;'>" . number_format($grand_total_closing, 2) . " ر.س</td>";
            echo "</tr>";
            
            echo "</table>";
        }
        
        // إضافة قسم الأرباح والخسائر
        echo "<div style='background: #fff8e1; padding: 20px; border-radius: 8px; margin: 25px 0; border: 2px solid #8e44ad;'>";
        echo "<h3 style='color: #8e44ad; margin-top: 0; text-align: center;'>حساب الأرباح والخسائر (قائمة الدخل)</h3>";
        echo "<div style='display: flex; justify-content: space-around; text-align: center;'>";
        
        echo "<div style='padding: 15px;'>";
        echo "<div style='font-size: 20px; color: #27ae60; font-weight: bold;'>" . number_format($net_income_data['revenue'], 2) . " ر.س</div>";
        echo "<div style='color: #7f8c8d;'>إجمالي الإيرادات</div>";
        echo "</div>";
        
        echo "<div style='padding: 15px;'>";
        echo "<div style='font-size: 20px; color: #e74c3c; font-weight: bold;'>" . number_format($net_income_data['expenses'], 2) . " ر.س</div>";
        echo "<div style='color: #7f8c8d;'>إجمالي المصاريف</div>";
        echo "</div>";
        
        echo "<div style='padding: 15px;'>";
        $net_income_color = $net_income_data['net_income'] >= 0 ? '#27ae60' : '#e74c3c';
        $net_income_text = $net_income_data['net_income'] >= 0 ? 'ربح' : 'خسارة';
        echo "<div style='font-size: 24px; color: " . $net_income_color . "; font-weight: bold;'>" . number_format(abs($net_income_data['net_income']), 2) . " ر.س</div>";
        echo "<div style='color: #7f8c8d;'>صافي الدخل (" . $net_income_text . ")</div>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        
        end_page();
        exit();
    }
}

// النموذج البسيط - الصفحة الرئيسية
page("الميزانية العمومية المخصصة");

echo "<div style='padding: 20px; direction: rtl; text-align: center; background: #f8f9fa; min-height: 100vh;'>";
echo "<div style='max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);'>";
echo "<h1 style='color: #2c3e50; margin-bottom: 10px;'>الميزانية العمومية - Balance Sheet</h1>";
echo "<p style='color: #7f8c8d; margin-bottom: 30px;'>اختر الفترة الزمنية ومركز التكلفة ونوع التقرير</p>";

start_form();
start_table(TABLESTYLE2);

date_cells("من تاريخ:", "PARAM_0");
date_cells("إلى تاريخ:", "PARAM_1");

// إضافة خيار فلتر مركز التكلفة
echo "<tr><td>مركز التكلفة:</td><td>";
$cost_centers = array('' => 'جميع مراكز التكلفة');
$sql = "SELECT id, name FROM " . TB_PREF . "dimensions WHERE type_ = 1 ORDER BY name";
$result = db_query($sql);
while ($row = db_fetch($result)) {
    $cost_centers[$row['id']] = $row['name'];
}
echo array_selector('PARAM_2', null, $cost_centers, array(
    'spec_option' => 'جميع مراكز التكلفة',
    'spec_id' => ''
));
echo "</td></tr>";

// إضافة خيار نوع التقرير
echo "<tr><td>نوع التقرير:</td><td>";
$report_types = array(
    'summary' => 'ملخص',
    'detailed' => 'مفصل'
);
echo array_selector('PARAM_3', 'summary', $report_types);
echo "</td></tr>";

end_table(1);
submit_center('Generate', "إنشاء التقرير", 'style="background: #3498db; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; margin-top: 20px;"');

end_form();

echo "<div style='margin-top: 30px; padding: 20px; background: #ecf0f1; border-radius: 8px;'>";
echo "<h4 style='color: #2980b9; margin-top: 0;'>المميزات المتاحة:</h4>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 📋 نوعين من التقارير: ملخص ومفصل</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 🏦 رصيد افتتاحي + حركات الفترة + رصيد ختامي</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 💰 حساب الأرباح والخسائر (قائمة الدخل)</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 🖨️ طباعة التقرير (نافذة جديدة)</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 📊 تصدير إلى Excel (ملف حقيقي)</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 📈 تحليل المجموعات الرئيسية (أصول، خصوم، حقوق ملكية)</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 🏢 فلترة حسب مركز التكلفة</p>";
echo "</div>";

echo "</div>";
echo "</div>";

end_page();