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

// دالة للحصول على بيانات الحسابات التفصيلية
function get_detailed_accounts($where_conditions, $account_pattern) {
    $sql = "SELECT account, SUM(amount) as total 
            FROM " . TB_PREF . "gl_trans 
            WHERE $where_conditions AND account LIKE '$account_pattern%'
            GROUP BY account 
            ORDER BY account";
    $result = db_query($sql);
    
    $accounts = array();
    while ($row = db_fetch($result)) {
        if ($row['total'] != 0) {
            $accounts[] = $row;
        }
    }
    return $accounts;
}

// دالة للحصول على اسم الحساب
function get_account_name($account_code) {
    $sql = "SELECT account_name FROM " . TB_PREF . "chart_master WHERE account_code = " . db_escape($account_code);
    $result = db_query($sql);
    $row = db_fetch($result);
    return $row ? $row['account_name'] : $account_code;
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
        
        // إرسال رأس Excel
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"profit_loss_report_" . date('Y-m-d') . ".xls\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        // محتوى Excel
        echo "<html dir='rtl'>";
        echo "<head>";
        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">";
        echo "<style>";
        echo "body { font-family: Arial; direction: rtl; }";
        echo "table { border-collapse: collapse; width: 100%; }";
        echo "th, td { border: 1px solid #000; padding: 8px; text-align: right; }";
        echo "th { background-color: #f2f2f2; }";
        echo ".positive { color: green; }";
        echo ".negative { color: red; }";
        echo ".sub-account { padding-right: 20px; }";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        
        echo "<h1 style='text-align: center;'>تقرير الأرباح والخسائر - " . ($report_type == 'summary' ? 'ملخص' : 'مفصل') . "</h1>";
        echo "<h3 style='text-align: center;'>الفترة: $from إلى $to</h3>";
        
        // إضافة اسم مركز التكلفة إذا تم اختياره
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4 style='text-align: center;'>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<p style='text-align: center;'>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</p>";
        
        // حسابات SQL مع فلتر مركز التكلفة
        $revenue_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                        WHERE $where_conditions AND account LIKE '41%'";
        $result = db_query($revenue_sql);
        $revenue_row = db_fetch($result);
        $total_revenue = $revenue_row['total'] ? abs($revenue_row['total']) : 0;
        
        $cogs_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                     WHERE $where_conditions AND account LIKE '42%'";
        $result = db_query($cogs_sql);
        $cogs_row = db_fetch($result);
        $total_cogs = $cogs_row['total'] ? abs($cogs_row['total']) : 0;
        
        $sales_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '43%'";
        $result = db_query($sales_exp_sql);
        $sales_exp_row = db_fetch($result);
        $total_sales_exp = $sales_exp_row['total'] ? abs($sales_exp_row['total']) : 0;
        
        $admin_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '44%'";
        $result = db_query($admin_exp_sql);
        $admin_exp_row = db_fetch($result);
        $total_admin_exp = $admin_exp_row['total'] ? abs($admin_exp_row['total']) : 0;
        
        $other_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '52%'";
        $result = db_query($other_exp_sql);
        $other_exp_row = db_fetch($result);
        $total_other_exp = $other_exp_row['total'] ? abs($other_exp_row['total']) : 0;
        
        $other_rev_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '51%'";
        $result = db_query($other_rev_sql);
        $other_rev_row = db_fetch($result);
        $total_other_rev = $other_rev_row['total'] ? abs($other_rev_row['total']) : 0;
        
        $gross_profit = $total_revenue - $total_cogs;
        $total_operating_expenses = $total_sales_exp + $total_admin_exp + $total_other_exp;
        $operating_profit = $gross_profit - $total_operating_expenses;
        $profit_before_tax = $operating_profit + $total_other_rev;
        $net_profit = $profit_before_tax;
        
        // جدول Excel
        echo "<table>";
        
        if ($report_type == 'detailed') {
            // التقرير المفصل - الإيرادات
            echo "<tr><th colspan='3' style='background: #2c3e50; color: white;'>الإيرادات</th></tr>";
            $revenue_accounts = get_detailed_accounts($where_conditions, '41');
            if (count($revenue_accounts) > 0) {
                foreach ($revenue_accounts as $account) {
                    $account_name = get_account_name($account['account']);
                    echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td>" . number_format(abs($account['total']), 2) . "</td></tr>";
                }
            }
            echo "<tr style='background: #e8f5e8;'><td colspan='2'><strong>إجمالي الإيرادات</strong></td><td><strong>" . number_format($total_revenue, 2) . "</strong></td></tr>";
            
            // الإيرادات الأخرى
            if ($total_other_rev > 0) {
                echo "<tr><th colspan='3' style='background: #27ae60; color: white;'>الإيرادات الأخرى</th></tr>";
                $other_rev_accounts = get_detailed_accounts($where_conditions, '51');
                if (count($other_rev_accounts) > 0) {
                    foreach ($other_rev_accounts as $account) {
                        $account_name = get_account_name($account['account']);
                        echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td>" . number_format(abs($account['total']), 2) . "</td></tr>";
                    }
                }
                echo "<tr style='background: #e8f5e8;'><td colspan='2'><strong>إجمالي الإيرادات الأخرى</strong></td><td><strong>" . number_format($total_other_rev, 2) . "</strong></td></tr>";
            }
            
            // التكاليف
            echo "<tr><th colspan='3' style='background: #c0392b; color: white;'>التكاليف</th></tr>";
            $cogs_accounts = get_detailed_accounts($where_conditions, '42');
            if (count($cogs_accounts) > 0) {
                foreach ($cogs_accounts as $account) {
                    $account_name = get_account_name($account['account']);
                    echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td>(" . number_format(abs($account['total']), 2) . ")</td></tr>";
                }
            }
            echo "<tr style='background: #fdeaea;'><td colspan='2'><strong>إجمالي التكاليف</strong></td><td><strong>(" . number_format($total_cogs, 2) . ")</strong></td></tr>";
            
            // إجمالي الربح
            echo "<tr style='background: " . ($gross_profit >= 0 ? '#d4edda' : '#f8d7da') . ";'>";
            echo "<td colspan='2'><strong>إجمالي الربح</strong></td>";
            echo "<td><strong>" . number_format($gross_profit, 2) . "</strong></td>";
            echo "</tr>";
            
            // المصاريف التشغيلية
            if ($total_operating_expenses > 0) {
                echo "<tr><th colspan='3' style='background: #e67e22; color: white;'>المصاريف التشغيلية</th></tr>";
                
                if ($total_sales_exp > 0) {
                    echo "<tr><td colspan='3' style='background: #f5f5f5;'><strong>مصاريف المبيعات</strong></td></tr>";
                    $sales_exp_accounts = get_detailed_accounts($where_conditions, '43');
                    if (count($sales_exp_accounts) > 0) {
                        foreach ($sales_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td>(" . number_format(abs($account['total']), 2) . ")</td></tr>";
                        }
                    }
                    echo "<tr style='background: #fdeaea;'><td colspan='2'><strong>إجمالي مصاريف المبيعات</strong></td><td><strong>(" . number_format($total_sales_exp, 2) . ")</strong></td></tr>";
                }
                
                if ($total_admin_exp > 0) {
                    echo "<tr><td colspan='3' style='background: #f5f5f5;'><strong>المصاريف الإدارية</strong></td></tr>";
                    $admin_exp_accounts = get_detailed_accounts($where_conditions, '44');
                    if (count($admin_exp_accounts) > 0) {
                        foreach ($admin_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td>(" . number_format(abs($account['total']), 2) . ")</td></tr>";
                        }
                    }
                    echo "<tr style='background: #fdeaea;'><td colspan='2'><strong>إجمالي المصاريف الإدارية</strong></td><td><strong>(" . number_format($total_admin_exp, 2) . ")</strong></td></tr>";
                }
                
                if ($total_other_exp > 0) {
                    echo "<tr><td colspan='3' style='background: #f5f5f5;'><strong>المصاريف الأخرى</strong></td></tr>";
                    $other_exp_accounts = get_detailed_accounts($where_conditions, '52');
                    if (count($other_exp_accounts) > 0) {
                        foreach ($other_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td>(" . number_format(abs($account['total']), 2) . ")</td></tr>";
                        }
                    }
                    echo "<tr style='background: #fdeaea;'><td colspan='2'><strong>إجمالي المصاريف الأخرى</strong></td><td><strong>(" . number_format($total_other_exp, 2) . ")</strong></td></tr>";
                }
                
                echo "<tr style='background: #fdeaea;'><td colspan='2'><strong>إجمالي المصاريف التشغيلية</strong></td><td><strong>(" . number_format($total_operating_expenses, 2) . ")</strong></td></tr>";
                
                // الربح التشغيلي
                echo "<tr style='background: #d1ecf1;'>";
                echo "<td colspan='2'><strong>الربح التشغيلي</strong></td>";
                echo "<td><strong>" . number_format($operating_profit, 2) . "</strong></td>";
                echo "</tr>";
            }
        } else {
            // التقرير الملخص - نفس الكود الأصلي
            echo "<tr><th colspan='2' style='background: #2c3e50; color: white;'>الإيرادات</th></tr>";
            echo "<tr><td>المبيعات (41)</td><td>" . number_format($total_revenue, 2) . "</td></tr>";
            if ($total_other_rev > 0) {
                echo "<tr><td>الإيرادات الأخرى (51)</td><td>" . number_format($total_other_rev, 2) . "</td></tr>";
            }
            
            echo "<tr><th colspan='2' style='background: #c0392b; color: white;'>التكاليف</th></tr>";
            echo "<tr><td>تكلفة البضاعة المباعة (42)</td><td>(" . number_format($total_cogs, 2) . ")</td></tr>";
            
            echo "<tr style='background: " . ($gross_profit >= 0 ? '#d4edda' : '#f8d7da') . ";'>";
            echo "<td><strong>إجمالي الربح</strong></td>";
            echo "<td><strong>" . number_format($gross_profit, 2) . "</strong></td>";
            echo "</tr>";
            
            if ($total_operating_expenses > 0) {
                echo "<tr><th colspan='2' style='background: #e67e22; color: white;'>المصاريف التشغيلية</th></tr>";
                if ($total_sales_exp > 0) {
                    echo "<tr><td>مصاريف المبيعات (43)</td><td>(" . number_format($total_sales_exp, 2) . ")</td></tr>";
                }
                if ($total_admin_exp > 0) {
                    echo "<tr><td>المصاريف الإدارية (44)</td><td>(" . number_format($total_admin_exp, 2) . ")</td></tr>";
                }
                if ($total_other_exp > 0) {
                    echo "<tr><td>المصاريف الأخرى (52)</td><td>(" . number_format($total_other_exp, 2) . ")</td></tr>";
                }
                
                echo "<tr style='background: #d1ecf1;'>";
                echo "<td><strong>الربح التشغيلي</strong></td>";
                echo "<td><strong>" . number_format($operating_profit, 2) . "</strong></td>";
                echo "</tr>";
            }
        }
        
        // صافي الربح
        echo "<tr style='background: " . ($net_profit >= 0 ? '#d4edda' : '#f8d7da') . ";'>";
        echo "<td colspan='" . ($report_type == 'detailed' ? '3' : '2') . "' style='text-align: center; font-size: 1.2em;'>";
        echo "<strong>" . ($net_profit >= 0 ? 'صافي الربح' : 'صافي الخسارة') . ": " . number_format(abs($net_profit), 2) . "</strong>";
        echo "</td></tr>";
        
        echo "</table>";
        
        // نسب الأداء
        if ($total_revenue > 0) {
            $gross_margin = ($gross_profit / $total_revenue) * 100;
            $net_margin = ($net_profit / $total_revenue) * 100;
            
            echo "<br><table>";
            echo "<tr><th colspan='2'>مؤشرات الأداء</th></tr>";
            echo "<tr><td>هامش الربح الإجمالي</td><td>" . number_format($gross_margin, 1) . "%</td></tr>";
            echo "<tr><td>هامش الربح الصافي</td><td>" . number_format($net_margin, 1) . "%</td></tr>";
            echo "</table>";
        }
        
        echo "</body></html>";
        exit();
    }
}

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
        
        // حسابات SQL
        $revenue_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                        WHERE $where_conditions AND account LIKE '41%'";
        $result = db_query($revenue_sql);
        $revenue_row = db_fetch($result);
        $total_revenue = $revenue_row['total'] ? abs($revenue_row['total']) : 0;
        
        $cogs_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                     WHERE $where_conditions AND account LIKE '42%'";
        $result = db_query($cogs_sql);
        $cogs_row = db_fetch($result);
        $total_cogs = $cogs_row['total'] ? abs($cogs_row['total']) : 0;
        
        $sales_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '43%'";
        $result = db_query($sales_exp_sql);
        $sales_exp_row = db_fetch($result);
        $total_sales_exp = $sales_exp_row['total'] ? abs($sales_exp_row['total']) : 0;
        
        $admin_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '44%'";
        $result = db_query($admin_exp_sql);
        $admin_exp_row = db_fetch($result);
        $total_admin_exp = $admin_exp_row['total'] ? abs($admin_exp_row['total']) : 0;
        
        $other_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '52%'";
        $result = db_query($other_exp_sql);
        $other_exp_row = db_fetch($result);
        $total_other_exp = $other_exp_row['total'] ? abs($other_exp_row['total']) : 0;
        
        $other_rev_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '51%'";
        $result = db_query($other_rev_sql);
        $other_rev_row = db_fetch($result);
        $total_other_rev = $other_rev_row['total'] ? abs($other_rev_row['total']) : 0;
        
        $gross_profit = $total_revenue - $total_cogs;
        $total_operating_expenses = $total_sales_exp + $total_admin_exp + $total_other_exp;
        $operating_profit = $gross_profit - $total_operating_expenses;
        $profit_before_tax = $operating_profit + $total_other_rev;
        $net_profit = $profit_before_tax;
        
        // صفحة الطباعة المخصصة
        echo "<!DOCTYPE html>
        <html dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <title>طباعة تقرير الأرباح والخسائر</title>
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
                .positive { color: #006400; font-weight: bold; }
                .negative { color: #8B0000; font-weight: bold; }
                .summary { 
                    background-color: #f8f9fa; 
                    padding: 15px; 
                    border: 1px solid #000; 
                    margin: 20px 0;
                }
                .sub-account { padding-right: 20px; }
                .account-group { background: #f5f5f5; font-weight: bold; }
                @media print {
                    body { margin: 0; padding: 15px; }
                    .no-print { display: none; }
                    table { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body onload='window.print();'>
            <div class='header'>
                <h1>تقرير الأرباح والخسائر - " . ($report_type == 'summary' ? 'ملخص' : 'مفصل') . "</h1>
                <h3>الفترة: $from إلى $to</h3>";
        
        // إضافة اسم مركز التكلفة إذا تم اختياره
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<p>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</p>
            </div>";
        
        // جدول التقرير
        if ($report_type == 'detailed') {
            echo "<table>";
            
            // التقرير المفصل - الإيرادات
            echo "<tr><th colspan='3' style='background:#2c3e50; color:white;'>الإيرادات</th></tr>";
            $revenue_accounts = get_detailed_accounts($where_conditions, '41');
            if (count($revenue_accounts) > 0) {
                foreach ($revenue_accounts as $account) {
                    $account_name = get_account_name($account['account']);
                    echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td class='positive'>" . number_format(abs($account['total']), 2) . " ر.س</td></tr>";
                }
            }
            echo "<tr style='background:#e8f5e8;'><td colspan='2'><strong>إجمالي الإيرادات</strong></td><td class='positive'><strong>" . number_format($total_revenue, 2) . " ر.س</strong></td></tr>";
            
            // الإيرادات الأخرى
            if ($total_other_rev > 0) {
                echo "<tr><th colspan='3' style='background:#27ae60; color:white;'>الإيرادات الأخرى</th></tr>";
                $other_rev_accounts = get_detailed_accounts($where_conditions, '51');
                if (count($other_rev_accounts) > 0) {
                    foreach ($other_rev_accounts as $account) {
                        $account_name = get_account_name($account['account']);
                        echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td class='positive'>" . number_format(abs($account['total']), 2) . " ر.س</td></tr>";
                    }
                }
                echo "<tr style='background:#e8f5e8;'><td colspan='2'><strong>إجمالي الإيرادات الأخرى</strong></td><td class='positive'><strong>" . number_format($total_other_rev, 2) . " ر.س</strong></td></tr>";
            }
            
            // التكاليف
            echo "<tr><th colspan='3' style='background:#c0392b; color:white;'>التكاليف</th></tr>";
            $cogs_accounts = get_detailed_accounts($where_conditions, '42');
            if (count($cogs_accounts) > 0) {
                foreach ($cogs_accounts as $account) {
                    $account_name = get_account_name($account['account']);
                    echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td class='negative'>(" . number_format(abs($account['total']), 2) . ") ر.س</td></tr>";
                }
            }
            echo "<tr style='background:#fdeaea;'><td colspan='2'><strong>إجمالي التكاليف</strong></td><td class='negative'><strong>(" . number_format($total_cogs, 2) . ") ر.س</strong></td></tr>";
            
            // إجمالي الربح
            echo "<tr style='background:" . ($gross_profit >= 0 ? '#d4edda' : '#f8d7da') . ";'>
                    <td colspan='2'><strong>إجمالي الربح</strong></td>
                    <td><strong class='" . ($gross_profit >= 0 ? 'positive' : 'negative') . "'>" . number_format($gross_profit, 2) . " ر.س</strong></td>
                  </tr>";
            
            // المصاريف التشغيلية
            if ($total_operating_expenses > 0) {
                echo "<tr><th colspan='3' style='background:#e67e22; color:white;'>المصاريف التشغيلية</th></tr>";
                
                if ($total_sales_exp > 0) {
                    echo "<tr class='account-group'><td colspan='3'>مصاريف المبيعات</td></tr>";
                    $sales_exp_accounts = get_detailed_accounts($where_conditions, '43');
                    if (count($sales_exp_accounts) > 0) {
                        foreach ($sales_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td class='negative'>(" . number_format(abs($account['total']), 2) . ") ر.س</td></tr>";
                        }
                    }
                    echo "<tr style='background:#fdeaea;'><td colspan='2'><strong>إجمالي مصاريف المبيعات</strong></td><td class='negative'><strong>(" . number_format($total_sales_exp, 2) . ") ر.س</strong></td></tr>";
                }
                
                if ($total_admin_exp > 0) {
                    echo "<tr class='account-group'><td colspan='3'>المصاريف الإدارية</td></tr>";
                    $admin_exp_accounts = get_detailed_accounts($where_conditions, '44');
                    if (count($admin_exp_accounts) > 0) {
                        foreach ($admin_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td class='negative'>(" . number_format(abs($account['total']), 2) . ") ر.س</td></tr>";
                        }
                    }
                    echo "<tr style='background:#fdeaea;'><td colspan='2'><strong>إجمالي المصاريف الإدارية</strong></td><td class='negative'><strong>(" . number_format($total_admin_exp, 2) . ") ر.س</strong></td></tr>";
                }
                
                if ($total_other_exp > 0) {
                    echo "<tr class='account-group'><td colspan='3'>المصاريف الأخرى</td></tr>";
                    $other_exp_accounts = get_detailed_accounts($where_conditions, '52');
                    if (count($other_exp_accounts) > 0) {
                        foreach ($other_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr><td class='sub-account'>" . $account['account'] . "</td><td>" . $account_name . "</td><td class='negative'>(" . number_format(abs($account['total']), 2) . ") ر.س</td></tr>";
                        }
                    }
                    echo "<tr style='background:#fdeaea;'><td colspan='2'><strong>إجمالي المصاريف الأخرى</strong></td><td class='negative'><strong>(" . number_format($total_other_exp, 2) . ") ر.س</strong></td></tr>";
                }
                
                echo "<tr style='background:#fdeaea;'><td colspan='2'><strong>إجمالي المصاريف التشغيلية</strong></td><td class='negative'><strong>(" . number_format($total_operating_expenses, 2) . ") ر.س</strong></td></tr>";
                
                // الربح التشغيلي
                echo "<tr style='background:#d1ecf1;'>
                        <td colspan='2'><strong>الربح التشغيلي</strong></td>
                        <td><strong class='" . ($operating_profit >= 0 ? 'positive' : 'negative') . "'>" . number_format($operating_profit, 2) . " ر.س</strong></td>
                      </tr>";
            }
            
            // صافي الربح
            echo "<tr style='background:" . ($net_profit >= 0 ? '#d4edda' : '#f8d7da') . ";'>
                    <td colspan='3' style='text-align:center; font-size:18px;'>
                      <strong>" . ($net_profit >= 0 ? 'صافي الربح' : 'صافي الخسارة') . ": " . number_format(abs($net_profit), 2) . " ر.س</strong>
                    </td>
                  </tr>";
            
            echo "</table>";
        } else {
            // التقرير الملخص - نفس الكود الأصلي
            echo "<table>
                    <tr><th colspan='2' style='background:#2c3e50; color:white;'>الإيرادات</th></tr>
                    <tr><td>المبيعات (41)</td><td class='positive'>" . number_format($total_revenue, 2) . " ر.س</td></tr>";
            
            if ($total_other_rev > 0) {
                echo "<tr><td>الإيرادات الأخرى (51)</td><td class='positive'>" . number_format($total_other_rev, 2) . " ر.س</td></tr>";
            }
            
            echo "<tr><th colspan='2' style='background:#c0392b; color:white;'>التكاليف</th></tr>
                  <tr><td>تكلفة البضاعة المباعة (42)</td><td class='negative'>(" . number_format($total_cogs, 2) . ") ر.س</td></tr>
                  <tr style='background:" . ($gross_profit >= 0 ? '#d4edda' : '#f8d7da') . ";'>
                    <td><strong>إجمالي الربح</strong></td>
                    <td><strong class='" . ($gross_profit >= 0 ? 'positive' : 'negative') . "'>" . number_format($gross_profit, 2) . " ر.س</strong></td>
                  </tr>";
            
            if ($total_operating_expenses > 0) {
                echo "<tr><th colspan='2' style='background:#e67e22; color:white;'>المصاريف التشغيلية</th></tr>";
                if ($total_sales_exp > 0) {
                    echo "<tr><td>مصاريف المبيعات (43)</td><td class='negative'>(" . number_format($total_sales_exp, 2) . ") ر.س</td></tr>";
                }
                if ($total_admin_exp > 0) {
                    echo "<tr><td>المصاريف الإدارية (44)</td><td class='negative'>(" . number_format($total_admin_exp, 2) . ") ر.س</td></tr>";
                }
                if ($total_other_exp > 0) {
                    echo "<tr><td>المصاريف الأخرى (52)</td><td class='negative'>(" . number_format($total_other_exp, 2) . ") ر.س</td></tr>";
                }
                
                echo "<tr style='background:#d1ecf1;'>
                        <td><strong>الربح التشغيلي</strong></td>
                        <td><strong class='" . ($operating_profit >= 0 ? 'positive' : 'negative') . "'>" . number_format($operating_profit, 2) . " ر.س</strong></td>
                      </tr>";
            }
            
            echo "<tr style='background:" . ($net_profit >= 0 ? '#d4edda' : '#f8d7da') . ";'>
                    <td colspan='2' style='text-align:center; font-size:18px;'>
                      <strong>" . ($net_profit >= 0 ? 'صافي الربح' : 'صافي الخسارة') . ": " . number_format(abs($net_profit), 2) . " ر.س</strong>
                    </td>
                  </tr>
                </table>";
        }
        
        // نسب الأداء
        if ($total_revenue > 0) {
            $gross_margin = ($gross_profit / $total_revenue) * 100;
            $net_margin = ($net_profit / $total_revenue) * 100;
            
            echo "<div class='summary'>
                    <h4 style='text-align:center; margin-top:0;'>مؤشرات الأداء</h4>
                    <div style='display:flex; justify-content:space-around; text-align:center;'>
                      <div>
                        <div style='font-size:20px; color:" . ($gross_margin >= 20 ? '#006400' : '#8B0000') . "; font-weight:bold;'>" . number_format($gross_margin, 1) . "%</div>
                        <div>هامش الربح الإجمالي</div>
                      </div>
                      <div>
                        <div style='font-size:20px; color:" . ($net_margin >= 10 ? '#006400' : '#8B0000') . "; font-weight:bold;'>" . number_format($net_margin, 1) . "%</div>
                        <div>هامش الربح الصافي</div>
                      </div>
                    </div>
                  </div>";
        }
        
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
        
        page("تقرير الأرباح والخسائر");
        
        echo "<div style='padding: 20px; direction: rtl; font-family: Arial, sans-serif; background: #f8f9fa; min-height: 100vh;'>";
        
        // أزرار التحكم في أعلى الصفحة
        echo "<div style='text-align: center; margin-bottom: 20px; padding: 15px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";
        echo "<a href='profloss1.php' style='padding: 12px 25px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; margin: 5px; display: inline-block;'>🔄 تقرير جديد</a>";
        echo "<a href='profloss1.php?action=print&from=" . urlencode($from) . "&to=" . urlencode($to) . "&cost_center=" . urlencode($cost_center) . "&report_type=" . urlencode($report_type) . "' target='_blank' style='padding: 12px 25px; background: #27ae60; color: white; text-decoration: none; border-radius: 6px; margin: 5px; display: inline-block;'>🖨️ طباعة التقرير</a>";
        echo "<a href='profloss1.php?action=excel&from=" . urlencode($from) . "&to=" . urlencode($to) . "&cost_center=" . urlencode($cost_center) . "&report_type=" . urlencode($report_type) . "' style='padding: 12px 25px; background: #f39c12; color: white; text-decoration: none; border-radius: 6px; margin: 5px; display: inline-block;'>📊 تصدير Excel</a>";
        echo "</div>";
        
        echo "<div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 20px;'>";
        
        // رأس التقرير
        echo "<div style='text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #3498db;'>";
        echo "<h1 style='color: #2c3e50; margin: 0; font-size: 28px;'>تقرير الأرباح والخسائر - " . ($report_type == 'summary' ? 'ملخص' : 'مفصل') . "</h1>";
        echo "<h3 style='color: #7f8c8d; margin: 10px 0;'>الفترة: $from إلى $to</h3>";
        
        // إضافة اسم مركز التكلفة إذا تم اختياره
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4 style='color: #2980b9; margin: 5px 0;'>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<p style='color: #95a5a6; margin: 5px 0;'>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</p>";
        echo "</div>";
        
        // حسابات SQL مع فلتر مركز التكلفة
        $revenue_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                        WHERE $where_conditions AND account LIKE '41%'";
        $result = db_query($revenue_sql);
        $revenue_row = db_fetch($result);
        $total_revenue = $revenue_row['total'] ? abs($revenue_row['total']) : 0;
        
        $cogs_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                     WHERE $where_conditions AND account LIKE '42%'";
        $result = db_query($cogs_sql);
        $cogs_row = db_fetch($result);
        $total_cogs = $cogs_row['total'] ? abs($cogs_row['total']) : 0;
        
        $sales_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '43%'";
        $result = db_query($sales_exp_sql);
        $sales_exp_row = db_fetch($result);
        $total_sales_exp = $sales_exp_row['total'] ? abs($sales_exp_row['total']) : 0;
        
        $admin_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '44%'";
        $result = db_query($admin_exp_sql);
        $admin_exp_row = db_fetch($result);
        $total_admin_exp = $admin_exp_row['total'] ? abs($admin_exp_row['total']) : 0;
        
        $other_exp_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '52%'";
        $result = db_query($other_exp_sql);
        $other_exp_row = db_fetch($result);
        $total_other_exp = $other_exp_row['total'] ? abs($other_exp_row['total']) : 0;
        
        $other_rev_sql = "SELECT SUM(amount) as total FROM " . TB_PREF . "gl_trans 
                          WHERE $where_conditions AND account LIKE '51%'";
        $result = db_query($other_rev_sql);
        $other_rev_row = db_fetch($result);
        $total_other_rev = $other_rev_row['total'] ? abs($other_rev_row['total']) : 0;
        
        $gross_profit = $total_revenue - $total_cogs;
        $total_operating_expenses = $total_sales_exp + $total_admin_exp + $total_other_exp;
        $operating_profit = $gross_profit - $total_operating_expenses;
        $profit_before_tax = $operating_profit + $total_other_rev;
        $net_profit = $profit_before_tax;
        
        // عرض تقرير الأرباح والخسائر
        echo "<div style='max-width: 800px; margin: 0 auto;'>";
        
        if ($report_type == 'detailed') {
            // التقرير المفصل
            echo "<table border='0' cellpadding='10' style='border-collapse: collapse; width: 100%; margin: 20px 0; border: 1px solid #ddd;'>";
            
            // الإيرادات
            echo "<tr style='background: #2c3e50; color: white;'>";
            echo "<td colspan='3' style='text-align: center; font-size: 18px; padding: 15px; font-weight: bold;'>الإيرادات</td>";
            echo "</tr>";
            
            $revenue_accounts = get_detailed_accounts($where_conditions, '41');
            if (count($revenue_accounts) > 0) {
                foreach ($revenue_accounts as $account) {
                    $account_name = get_account_name($account['account']);
                    echo "<tr style='background: #f8f9fa;'>";
                    echo "<td style='padding: 8px; width: 15%; border-bottom: 1px solid #dee2e6;'>" . $account['account'] . "</td>";
                    echo "<td style='padding: 8px; width: 55%; border-bottom: 1px solid #dee2e6;'>" . $account_name . "</td>";
                    echo "<td style='text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; color: #27ae60;'>" . number_format(abs($account['total']), 2) . " ر.س</td>";
                    echo "</tr>";
                }
            }
            echo "<tr style='background: #e8f5e8;'>";
            echo "<td colspan='2' style='padding: 12px; font-weight: bold;'>إجمالي الإيرادات</td>";
            echo "<td style='text-align: left; padding: 12px; color: #27ae60; font-weight: bold;'>" . number_format($total_revenue, 2) . " ر.س</td>";
            echo "</tr>";
            
            // الإيرادات الأخرى
            if ($total_other_rev > 0) {
                echo "<tr style='background: #27ae60; color: white;'>";
                echo "<td colspan='3' style='text-align: center; font-size: 18px; padding: 15px; font-weight: bold;'>الإيرادات الأخرى</td>";
                echo "</tr>";
                
                $other_rev_accounts = get_detailed_accounts($where_conditions, '51');
                if (count($other_rev_accounts) > 0) {
                    foreach ($other_rev_accounts as $account) {
                        $account_name = get_account_name($account['account']);
                        echo "<tr style='background: #f8f9fa;'>";
                        echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account['account'] . "</td>";
                        echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account_name . "</td>";
                        echo "<td style='text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; color: #27ae60;'>" . number_format(abs($account['total']), 2) . " ر.س</td>";
                        echo "</tr>";
                    }
                }
                echo "<tr style='background: #e8f5e8;'>";
                echo "<td colspan='2' style='padding: 12px; font-weight: bold;'>إجمالي الإيرادات الأخرى</td>";
                echo "<td style='text-align: left; padding: 12px; color: #27ae60; font-weight: bold;'>" . number_format($total_other_rev, 2) . " ر.س</td>";
                echo "</tr>";
            }
            
            // التكاليف
            echo "<tr style='background: #c0392b; color: white;'>";
            echo "<td colspan='3' style='text-align: center; font-size: 18px; padding: 15px; font-weight: bold;'>التكاليف</td>";
            echo "</tr>";
            
            $cogs_accounts = get_detailed_accounts($where_conditions, '42');
            if (count($cogs_accounts) > 0) {
                foreach ($cogs_accounts as $account) {
                    $account_name = get_account_name($account['account']);
                    echo "<tr style='background: #f8f9fa;'>";
                    echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account['account'] . "</td>";
                    echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account_name . "</td>";
                    echo "<td style='text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; color: #e74c3c;'>(" . number_format(abs($account['total']), 2) . ") ر.س</td>";
                    echo "</tr>";
                }
            }
            echo "<tr style='background: #fdeaea;'>";
            echo "<td colspan='2' style='padding: 12px; font-weight: bold;'>إجمالي التكاليف</td>";
            echo "<td style='text-align: left; padding: 12px; color: #e74c3c; font-weight: bold;'>(" . number_format($total_cogs, 2) . ") ر.س</td>";
            echo "</tr>";
            
            // إجمالي الربح
            $gross_profit_color = $gross_profit >= 0 ? '#27ae60' : '#c0392b';
            echo "<tr style='background: $gross_profit_color; color: white;'>";
            echo "<td colspan='2' style='padding: 15px; font-size: 16px; font-weight: bold;'>إجمالي الربح</td>";
            echo "<td style='text-align: left; padding: 15px; font-size: 16px; font-weight: bold;'>" . number_format($gross_profit, 2) . " ر.س</td>";
            echo "</tr>";
            
            // المصاريف التشغيلية
            if ($total_operating_expenses > 0) {
                echo "<tr style='background: #e67e22; color: white;'>";
                echo "<td colspan='3' style='text-align: center; font-size: 18px; padding: 15px; font-weight: bold;'>المصاريف التشغيلية</td>";
                echo "</tr>";
                
                if ($total_sales_exp > 0) {
                    echo "<tr style='background: #f5f5f5;'>";
                    echo "<td colspan='3' style='padding: 10px; font-weight: bold;'>مصاريف المبيعات</td>";
                    echo "</tr>";
                    
                    $sales_exp_accounts = get_detailed_accounts($where_conditions, '43');
                    if (count($sales_exp_accounts) > 0) {
                        foreach ($sales_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr style='background: #f8f9fa;'>";
                            echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account['account'] . "</td>";
                            echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account_name . "</td>";
                            echo "<td style='text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; color: #e74c3c;'>(" . number_format(abs($account['total']), 2) . ") ر.س</td>";
                            echo "</tr>";
                        }
                    }
                    echo "<tr style='background: #fdeaea;'>";
                    echo "<td colspan='2' style='padding: 10px; font-weight: bold;'>إجمالي مصاريف المبيعات</td>";
                    echo "<td style='text-align: left; padding: 10px; color: #e74c3c; font-weight: bold;'>(" . number_format($total_sales_exp, 2) . ") ر.س</td>";
                    echo "</tr>";
                }
                
                if ($total_admin_exp > 0) {
                    echo "<tr style='background: #f5f5f5;'>";
                    echo "<td colspan='3' style='padding: 10px; font-weight: bold;'>المصاريف الإدارية</td>";
                    echo "</tr>";
                    
                    $admin_exp_accounts = get_detailed_accounts($where_conditions, '44');
                    if (count($admin_exp_accounts) > 0) {
                        foreach ($admin_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr style='background: #f8f9fa;'>";
                            echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account['account'] . "</td>";
                            echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account_name . "</td>";
                            echo "<td style='text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; color: #e74c3c;'>(" . number_format(abs($account['total']), 2) . ") ر.س</td>";
                            echo "</tr>";
                        }
                    }
                    echo "<tr style='background: #fdeaea;'>";
                    echo "<td colspan='2' style='padding: 10px; font-weight: bold;'>إجمالي المصاريف الإدارية</td>";
                    echo "<td style='text-align: left; padding: 10px; color: #e74c3c; font-weight: bold;'>(" . number_format($total_admin_exp, 2) . ") ر.س</td>";
                    echo "</tr>";
                }
                
                if ($total_other_exp > 0) {
                    echo "<tr style='background: #f5f5f5;'>";
                    echo "<td colspan='3' style='padding: 10px; font-weight: bold;'>المصاريف الأخرى</td>";
                    echo "</tr>";
                    
                    $other_exp_accounts = get_detailed_accounts($where_conditions, '52');
                    if (count($other_exp_accounts) > 0) {
                        foreach ($other_exp_accounts as $account) {
                            $account_name = get_account_name($account['account']);
                            echo "<tr style='background: #f8f9fa;'>";
                            echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account['account'] . "</td>";
                            echo "<td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $account_name . "</td>";
                            echo "<td style='text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; color: #e74c3c;'>(" . number_format(abs($account['total']), 2) . ") ر.س</td>";
                            echo "</tr>";
                        }
                    }
                    echo "<tr style='background: #fdeaea;'>";
                    echo "<td colspan='2' style='padding: 10px; font-weight: bold;'>إجمالي المصاريف الأخرى</td>";
                    echo "<td style='text-align: left; padding: 10px; color: #e74c3c; font-weight: bold;'>(" . number_format($total_other_exp, 2) . ") ر.س</td>";
                    echo "</tr>";
                }
                
                echo "<tr style='background: #fdeaea;'>";
                echo "<td colspan='2' style='padding: 12px; font-weight: bold;'>إجمالي المصاريف التشغيلية</td>";
                echo "<td style='text-align: left; padding: 12px; color: #e74c3c; font-weight: bold;'>(" . number_format($total_operating_expenses, 2) . ") ر.س</td>";
                echo "</tr>";
                
                // الربح التشغيلي
                echo "<tr style='background: #2980b9; color: white;'>";
                echo "<td colspan='2' style='padding: 12px; font-weight: bold;'>الربح التشغيلي</td>";
                echo "<td style='text-align: left; padding: 12px; font-weight: bold;'>" . number_format($operating_profit, 2) . " ر.س</td>";
                echo "</tr>";
            }
            
            // صافي الربح
            $net_profit_color = $net_profit >= 0 ? '#27ae60' : '#c0392b';
            echo "<tr style='background: $net_profit_color; color: white;'>";
            echo "<td colspan='3' style='padding: 20px; font-size: 20px; text-align: center; font-weight: bold;'>";
            echo ($net_profit >= 0 ? 'صافي الربح' : 'صافي الخسارة') . ": " . number_format(abs($net_profit), 2) . " ر.س";
            echo "</td>";
            echo "</tr>";
            
            echo "</table>";
        } else {
            // التقرير الملخص - نفس الكود الأصلي
            echo "<table border='0' cellpadding='10' style='border-collapse: collapse; width: 100%; margin: 20px 0; border: 1px solid #ddd;'>";
            
            // الإيرادات
            echo "<tr style='background: #2c3e50; color: white;'>";
            echo "<td colspan='2' style='text-align: center; font-size: 18px; padding: 15px; font-weight: bold;'>الإيرادات</td>";
            echo "</tr>";
            
            echo "<tr style='background: #f8f9fa;'>";
            echo "<td style='padding: 12px; width: 70%; border-bottom: 1px solid #dee2e6;'>المبيعات (41)</td>";
            echo "<td style='text-align: left; padding: 12px; border-bottom: 1px solid #dee2e6; color: #27ae60; font-weight: bold;'>" . number_format($total_revenue, 2) . " ر.س</td>";
            echo "</tr>";
            
            if ($total_other_rev > 0) {
                echo "<tr style='background: #f8f9fa;'>";
                echo "<td style='padding: 12px; border-bottom: 1px solid #dee2e6;'>الإيرادات الأخرى (51)</td>";
                echo "<td style='text-align: left; padding: 12px; border-bottom: 1px solid #dee2e6; color: #27ae60;'>" . number_format($total_other_rev, 2) . " ر.س</td>";
                echo "</tr>";
            }
            
            // التكاليف
            echo "<tr style='background: #c0392b; color: white;'>";
            echo "<td colspan='2' style='text-align: center; font-size: 18px; padding: 15px; font-weight: bold;'>التكاليف</td>";
            echo "</tr>";
            
            echo "<tr style='background: #f8f9fa;'>";
            echo "<td style='padding: 12px; border-bottom: 1px solid #dee2e6;'>تكلفة البضاعة المباعة (42)</td>";
            echo "<td style='text-align: left; padding: 12px; border-bottom: 1px solid #dee2e6; color: #e74c3c; font-weight: bold;'>(" . number_format($total_cogs, 2) . ") ر.س</td>";
            echo "</tr>";
            
            // إجمالي الربح
            $gross_profit_color = $gross_profit >= 0 ? '#27ae60' : '#c0392b';
            echo "<tr style='background: $gross_profit_color; color: white;'>";
            echo "<td style='padding: 15px; font-size: 16px; font-weight: bold;'>إجمالي الربح</td>";
            echo "<td style='text-align: left; padding: 15px; font-size: 16px; font-weight: bold;'>" . number_format($gross_profit, 2) . " ر.س</td>";
            echo "</tr>";
            
            // المصاريف التشغيلية
            if ($total_operating_expenses > 0) {
                echo "<tr style='background: #e67e22; color: white;'>";
                echo "<td colspan='2' style='text-align: center; font-size: 18px; padding: 15px; font-weight: bold;'>المصاريف التشغيلية</td>";
                echo "</tr>";
                
                if ($total_sales_exp > 0) {
                    echo "<tr style='background: #f8f9fa;'>";
                    echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>مصاريف المبيعات (43)</td>";
                    echo "<td style='text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6; color: #e74c3c;'>(" . number_format($total_sales_exp, 2) . ") ر.س</td>";
                    echo "</tr>";
                }
                
                if ($total_admin_exp > 0) {
                    echo "<tr style='background: #f8f9fa;'>";
                    echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>المصاريف الإدارية (44)</td>";
                    echo "<td style='text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6; color: #e74c3c;'>(" . number_format($total_admin_exp, 2) . ") ر.س</td>";
                    echo "</tr>";
                }
                
                if ($total_other_exp > 0) {
                    echo "<tr style='background: #f8f9fa;'>";
                    echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>المصاريف الأخرى (52)</td>";
                    echo "<td style='text-align: left; padding: 10px; border-bottom: 1px solid #dee2e6; color: #e74c3c;'>(" . number_format($total_other_exp, 2) . ") ر.س</td>";
                    echo "</tr>";
                }
                
                // الربح التشغيلي
                echo "<tr style='background: #2980b9; color: white;'>";
                echo "<td style='padding: 12px; font-weight: bold;'>الربح التشغيلي</td>";
                echo "<td style='text-align: left; padding: 12px; font-weight: bold;'>" . number_format($operating_profit, 2) . " ر.س</td>";
                echo "</tr>";
            }
            
            // صافي الربح
            $net_profit_color = $net_profit >= 0 ? '#27ae60' : '#c0392b';
            echo "<tr style='background: $net_profit_color; color: white;'>";
            echo "<td colspan='2' style='padding: 20px; font-size: 20px; text-align: center; font-weight: bold;'>";
            echo ($net_profit >= 0 ? 'صافي الربح' : 'صافي الخسارة') . ": " . number_format(abs($net_profit), 2) . " ر.س";
            echo "</td>";
            echo "</tr>";
            
            echo "</table>";
        }
        
        // نسب الأداء
        if ($total_revenue > 0) {
            $gross_margin = ($gross_profit / $total_revenue) * 100;
            $net_margin = ($net_profit / $total_revenue) * 100;
            
            echo "<div style='background: #ecf0f1; padding: 20px; border-radius: 8px; margin: 25px 0;'>";
            echo "<h4 style='color: #2c3e50; margin-top: 0; text-align: center;'>مؤشرات الأداء</h4>";
            echo "<div style='display: flex; justify-content: space-around; text-align: center;'>";
            
            echo "<div style='padding: 15px;'>";
            echo "<div style='font-size: 24px; color: " . ($gross_margin >= 20 ? '#27ae60' : '#e74c3c') . "; font-weight: bold;'>" . number_format($gross_margin, 1) . "%</div>";
            echo "<div style='color: #7f8c8d;'>هامش الربح الإجمالي</div>";
            echo "</div>";
            
            echo "<div style='padding: 15px;'>";
            echo "<div style='font-size: 24px; color: " . ($net_margin >= 10 ? '#27ae60' : '#e74c3c') . "; font-weight: bold;'>" . number_format($net_margin, 1) . "%</div>";
            echo "<div style='color: #7f8c8d;'>هامش الربح الصافي</div>";
            echo "</div>";
            
            echo "</div>";
            echo "</div>";
        }
        
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        
        end_page();
        exit();
    }
}

// النموذج البسيط - الصفحة الرئيسية
page("تقرير الأرباح والخسائر المخصص");

echo "<div style='padding: 20px; direction: rtl; text-align: center; background: #f8f9fa; min-height: 100vh;'>";
echo "<div style='max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);'>";
echo "<h1 style='color: #2c3e50; margin-bottom: 10px;'>تقرير الأرباح والخسائر</h1>";
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
echo "<p style='color: #34495e; margin: 8px 0;'>• 🖨️ طباعة التقرير (نافذة جديدة)</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 📊 تصدير إلى Excel (ملف حقيقي)</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 📈 تحليل الأداء المالي</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 💰 حساب نسب الربحية</p>";
echo "<p style='color: #34495e; margin: 8px 0;'>• 🏢 فلترة حسب مركز التكلفة</p>";
echo "</div>";

echo "</div>";
echo "</div>";

end_page();