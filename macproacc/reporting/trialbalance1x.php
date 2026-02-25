<?php
$path_to_root = "..";
$page_security = 'SA_OPEN';

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");

// دالة للحصول على بيانات ميزان المراجعة
function get_trial_balance_data($from_date, $to_date, $cost_center = '') {
    $from_sql = date2sql($from_date);
    $to_sql = date2sql($to_date);
    
    $classifications = [
        '1' => 'الأصول',
        '2' => 'الخصوم', 
        '3' => 'حقوق الملكية',
        '41' => 'قسم الأعمال التشغيلية - إيرادات البيع',
        '51' => 'قسم الأعمال الغير تشغيلية - الإيرادات الآخرى',
        '42' => 'قسم الأعمال التشغيلية - تكلفة شراء البضاعة المباعة',
        '43' => 'قسم الأعمال التشغيلية - مصاريف قسم المبيعات',
        '44' => 'قسم الأعمال التشغيلية - المصاريف الإدارية و العمومية',
        '52' => 'قسم الأعمال الغير تشغيلية - المصاريف الآخرى',
        '6' => 'ضريبة الدخل',
        '7' => 'العمليات المتوقفة',
        '8' => 'البنود الغير عادية'
    ];
    
    $results = [];
    $total_current_debit = 0;
    $total_current_credit = 0;
    
    foreach ($classifications as $code => $name) {
        // بناء الاستعلام مع فلتر مركز التكلفة
        $where_conditions = "tran_date BETWEEN '$from_sql' AND '$to_sql'";
        if (!empty($cost_center)) {
            $where_conditions .= " AND dimension_id = " . db_escape($cost_center);
        }
        
        if (strlen($code) == 1) {
            $sql = "SELECT 
                        SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debit,
                        SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credit,
                        SUM(amount) as net_balance
                    FROM " . TB_PREF . "gl_trans 
                    WHERE $where_conditions 
                    AND account LIKE '$code%'";
        } else {
            $sql = "SELECT 
                        SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debit,
                        SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credit,
                        SUM(amount) as net_balance
                    FROM " . TB_PREF . "gl_trans 
                    WHERE $where_conditions 
                    AND account LIKE '$code%'";
        }
        
        $result = db_query($sql);
        $row = db_fetch($result);
        
        $current_debit = $row ? $row['total_debit'] : 0;
        $current_credit = $row ? $row['total_credit'] : 0;
        $net_balance = $row ? $row['net_balance'] : 0;
        
        $results[] = [
            'type' => 'classification',
            'code' => $code,
            'name' => $name,
            'prev_debit' => 0.00,
            'prev_credit' => 0.00,
            'current_debit' => $current_debit,
            'current_credit' => $current_credit,
            'balance_debit' => $net_balance > 0 ? $net_balance : 0,
            'balance_credit' => $net_balance < 0 ? abs($net_balance) : 0
        ];
        
        $total_current_debit += $current_debit;
        $total_current_credit += $current_credit;
    }
    
    return [
        'data' => $results,
        'totals' => [
            'current_debit' => $total_current_debit,
            'current_credit' => $total_current_credit,
            'difference' => abs($total_current_debit - $total_current_credit)
        ]
    ];
}

// دالة لتحليل المصاريف بشكل مفصل
function analyze_expenses($from_date, $to_date, $cost_center = '') {
    $from_sql = date2sql($from_date);
    $to_sql = date2sql($to_date);
    
    $expense_categories = [
        '42' => 'تكلفة شراء البضاعة المباعة',
        '43' => 'مصاريف قسم المبيعات', 
        '44' => 'المصاريف الإدارية و العمومية',
        '52' => 'المصاريف الآخرى'
    ];
    
    $analysis = [];
    $total_expenses = 0;
    
    foreach ($expense_categories as $code => $name) {
        $where_conditions = "tran_date BETWEEN '$from_sql' AND '$to_sql'";
        if (!empty($cost_center)) {
            $where_conditions .= " AND dimension_id = " . db_escape($cost_center);
        }
        
        $sql = "SELECT 
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debit,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credit,
                    COUNT(*) as transaction_count
                FROM " . TB_PREF . "gl_trans 
                WHERE $where_conditions 
                AND account LIKE '$code%'";
        
        $result = db_query($sql);
        $row = db_fetch($result);
        
        $expense_debit = $row ? $row['total_debit'] : 0;
        $expense_credit = $row ? $row['total_credit'] : 0;
        $transaction_count = $row ? $row['transaction_count'] : 0;
        
        $analysis[] = [
            'code' => $code,
            'name' => $name,
            'debit' => $expense_debit,
            'credit' => $expense_credit,
            'net' => $expense_debit - $expense_credit,
            'transactions' => $transaction_count
        ];
        
        $total_expenses += $expense_debit;
    }
    
    return [
        'details' => $analysis,
        'total' => $total_expenses
    ];
}

// دالة للحصول على اسم مركز التكلفة
function get_cost_center_name($id) {
    if (empty($id)) return '';
    $sql = "SELECT name FROM " . TB_PREF . "dimensions WHERE id = " . db_escape($id);
    $result = db_query($sql);
    $row = db_fetch($result);
    return $row ? $row['name'] : 'غير معروف';
}

// إذا كان طلب تصدير Excel
if (isset($_GET['action']) && $_GET['action'] == 'excel') {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $cost_center = $_GET['cost_center'] ?? '';
    
    if (!empty($from) && !empty($to)) {
        $report_data = get_trial_balance_data($from, $to, $cost_center);
        $balance_data = $report_data['data'];
        $totals = $report_data['totals'];
        $expense_analysis = analyze_expenses($from, $to, $cost_center);
        
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"ميزان_المراجعة_$from_الى_$to.xls\"");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo "<html dir='rtl'>";
        echo "<head>";
        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">";
        echo "<style>";
        echo "body { font-family: Arial; direction: rtl; }";
        echo "table { border-collapse: collapse; width: 100%; margin: 10px 0; }";
        echo "th, td { border: 1px solid #000; padding: 8px; text-align: right; }";
        echo "th { background-color: #f2f2f2; }";
        echo ".classification { background-color: #e6f3ff; }";
        echo ".closing { background-color: #d4edda; font-weight: bold; }";
        echo ".expense-analysis { background-color: #fff3cd; }";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        
        echo "<h2 style='text-align: center;'>ميزان المراجعة</h2>";
        echo "<h3 style='text-align: center;'>الفترة: $from إلى $to</h3>";
        
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4 style='text-align: center;'>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<table>";
        echo "<tr>";
        echo "<th>الحساب</th>";
        echo "<th>أسم الحساب</th>";
        echo "<th colspan='2'>ما قبله</th>";
        echo "<th colspan='2'>الفترة الحالية</th>";
        echo "<th colspan='2'>الرصيد</th>";
        echo "</tr>";
        echo "<tr>";
        echo "<th></th>";
        echo "<th></th>";
        echo "<th>مدين</th>";
        echo "<th>دائن</th>";
        echo "<th>مدين</th>";
        echo "<th>دائن</th>";
        echo "<th>مدين</th>";
        echo "<th>دائن</th>";
        echo "</tr>";
        
        foreach ($balance_data as $item) {
            echo "<tr class='classification'>";
            echo "<td>التصنيف الرئيسي - " . $item['code'] . "</td>";
            echo "<td>" . $item['name'] . "</td>";
            echo "<td>" . number_format($item['prev_debit'], 2) . "</td>";
            echo "<td>" . number_format($item['prev_credit'], 2) . "</td>";
            echo "<td>" . number_format($item['current_debit'], 2) . "</td>";
            echo "<td>" . number_format($item['current_credit'], 2) . "</td>";
            echo "<td>" . number_format($item['balance_debit'], 2) . "</td>";
            echo "<td>" . number_format($item['balance_credit'], 2) . "</td>";
            echo "</tr>";
        }
        
        echo "<tr class='closing'>";
        echo "<td>الرصيد الختامي - " . $to . "</td>";
        echo "<td></td>";
        echo "<td>0.00</td>";
        echo "<td>0.00</td>";
        echo "<td>" . number_format($totals['current_debit'], 2) . "</td>";
        echo "<td>" . number_format($totals['current_credit'], 2) . "</td>";
        echo "<td>0.00</td>";
        echo "<td>0.00</td>";
        echo "</tr>";
        
        echo "</table>";
        
        // تحليل المصاريف في Excel
        echo "<br><table class='expense-analysis'>";
        echo "<tr><th colspan='6'>تحليل المصاريف التفصيلي</th></tr>";
        echo "<tr><th>كود المصروف</th><th>اسم المصروف</th><th>مدين</th><th>دائن</th><th>صافي</th><th>عدد القيود</th></tr>";
        
        foreach ($expense_analysis['details'] as $expense) {
            echo "<tr>";
            echo "<td>" . $expense['code'] . "</td>";
            echo "<td>" . $expense['name'] . "</td>";
            echo "<td>" . number_format($expense['debit'], 2) . "</td>";
            echo "<td>" . number_format($expense['credit'], 2) . "</td>";
            echo "<td>" . number_format($expense['net'], 2) . "</td>";
            echo "<td>" . $expense['transactions'] . "</td>";
            echo "</tr>";
        }
        
        echo "<tr style='font-weight: bold;'>";
        echo "<td colspan='2'>إجمالي المصاريف</td>";
        echo "<td colspan='4'>" . number_format($expense_analysis['total'], 2) . " ر.س</td>";
        echo "</tr>";
        echo "</table>";
        
        // ملخص التوازن
        echo "<br><table>";
        echo "<tr><th colspan='4'>ملخص التوازن</th></tr>";
        echo "<tr><td>إجمالي المدين:</td><td>" . number_format($totals['current_debit'], 2) . " ر.س</td><td>إجمالي الدائن:</td><td>" . number_format($totals['current_credit'], 2) . " ر.س</td></tr>";
        echo "<tr><td>الفرق:</td><td colspan='3'>" . number_format($totals['difference'], 2) . " ر.س</td></tr>";
        if ($totals['difference'] == 0) {
            echo "<tr style='background: #d4edda;'><td colspan='4' style='text-align: center; color: #155724;'><strong>✓ الميزان متوازن</strong></td></tr>";
        } else {
            echo "<tr style='background: #f8d7da;'><td colspan='4' style='text-align: center; color: #721c24;'><strong>✗ الميزان غير متوازن</strong></td></tr>";
        }
        echo "</table>";
        
        echo "</body></html>";
        exit();
    }
}

// إذا كان طلب طباعة
if (isset($_GET['action']) && $_GET['action'] == 'print') {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $cost_center = $_GET['cost_center'] ?? '';
    
    if (!empty($from) && !empty($to)) {
        $report_data = get_trial_balance_data($from, $to, $cost_center);
        $balance_data = $report_data['data'];
        $totals = $report_data['totals'];
        $expense_analysis = analyze_expenses($from, $to, $cost_center);
        
        echo "<!DOCTYPE html>
        <html dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <title>طباعة ميزان المراجعة</title>
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
                h2 { 
                    color: #000; 
                    margin: 0 0 10px 0; 
                }
                h3 { 
                    color: #333; 
                    margin: 5px 0; 
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 20px 0; 
                    border: 2px solid #000;
                }
                th, td { 
                    border: 1px solid #000; 
                    padding: 8px; 
                    text-align: right;
                }
                th { 
                    background-color: #f0f0f0; 
                    font-weight: bold;
                }
                .classification { 
                    background-color: #e6f3ff; 
                }
                .closing { 
                    background-color: #d4edda; 
                    font-weight: bold;
                }
                .expense-analysis { 
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                }
                .balance-info {
                    background: #e8f5e8;
                    padding: 15px;
                    border: 1px solid #d4edda;
                    margin: 20px 0;
                    border-radius: 5px;
                }
                @media print {
                    body { margin: 0; padding: 15px; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body onload='window.print();'>
            <div class='header'>
                <h2>ميزان المراجعة</h2>
                <h3>الفترة: $from إلى $to</h3>";
        
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "<p>تاريخ الإنشاء: " . date('Y-m-d H:i:s') . "</p>
            </div>";
        
        echo "<table>
                <tr>
                    <th>الحساب</th>
                    <th>أسم الحساب</th>
                    <th colspan='2'>ما قبله</th>
                    <th colspan='2'>الفترة الحالية</th>
                    <th colspan='2'>الرصيد</th>
                </tr>
                <tr>
                    <th></th>
                    <th></th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>مدين</th>
                    <th>دائن</th>
                </tr>";
        
        foreach ($balance_data as $item) {
            echo "<tr class='classification'>
                    <td>التصنيف الرئيسي - " . $item['code'] . "</td>
                    <td>" . $item['name'] . "</td>
                    <td>" . number_format($item['prev_debit'], 2) . "</td>
                    <td>" . number_format($item['prev_credit'], 2) . "</td>
                    <td>" . number_format($item['current_debit'], 2) . "</td>
                    <td>" . number_format($item['current_credit'], 2) . "</td>
                    <td>" . number_format($item['balance_debit'], 2) . "</td>
                    <td>" . number_format($item['balance_credit'], 2) . "</td>
                  </tr>";
        }
        
        echo "<tr class='closing'>
                <td>الرصيد الختامي - " . $to . "</td>
                <td></td>
                <td>0.00</td>
                <td>0.00</td>
                <td>" . number_format($totals['current_debit'], 2) . "</td>
                <td>" . number_format($totals['current_credit'], 2) . "</td>
                <td>0.00</td>
                <td>0.00</td>
              </tr>";
        
        echo "</table>";
        
        // تحليل المصاريف
        echo "<table class='expense-analysis'>
                <tr><th colspan='6'>تحليل المصاريف التفصيلي</th></tr>
                <tr>
                    <th>كود المصروف</th>
                    <th>اسم المصروف</th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>صافي</th>
                    <th>عدد القيود</th>
                </tr>";
        
        foreach ($expense_analysis['details'] as $expense) {
            echo "<tr>
                    <td>" . $expense['code'] . "</td>
                    <td>" . $expense['name'] . "</td>
                    <td>" . number_format($expense['debit'], 2) . "</td>
                    <td>" . number_format($expense['credit'], 2) . "</td>
                    <td>" . number_format($expense['net'], 2) . "</td>
                    <td>" . $expense['transactions'] . "</td>
                  </tr>";
        }
        
        echo "<tr style='font-weight: bold;'>
                <td colspan='2'>إجمالي المصاريف</td>
                <td colspan='4'>" . number_format($expense_analysis['total'], 2) . " ر.س</td>
              </tr>
            </table>";
        
        // معلومات التوازن
        echo "<div class='balance-info'>";
        echo "<h4>معلومات التوازن:</h4>";
        echo "<p><strong>إجمالي المدين:</strong> " . number_format($totals['current_debit'], 2) . " ر.س</p>";
        echo "<p><strong>إجمالي الدائن:</strong> " . number_format($totals['current_credit'], 2) . " ر.س</p>";
        echo "<p><strong>الفرق:</strong> " . number_format($totals['difference'], 2) . " ر.س</p>";
        if ($totals['difference'] == 0) {
            echo "<p style='color: green;'><strong>✓ الميزان متوازن</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>✗ الميزان غير متوازن</strong></p>";
        }
        echo "</div>";
        
        echo "<div class='no-print' style='text-align:center; margin-top:20px;'>
                <button onclick='window.print()' style='padding:10px 20px; background:#27ae60; color:white; border:none; border-radius:5px; cursor:pointer; margin:5px;'>🖨️ طباعة مرة أخرى</button>
                <button onclick='window.close()' style='padding:10px 20px; background:#e74c3c; color:white; border:none; border-radius:5px; cursor:pointer; margin:5px;'>❌ إغلاق</button>
              </div>
            </body>
            </html>";
        exit();
    }
}

// إذا كان طلب إنشاء التقرير
if (isset($_POST['Generate'])) {
    $from = $_POST['PARAM_0'] ?? '';
    $to = $_POST['PARAM_1'] ?? '';
    $cost_center = $_POST['PARAM_2'] ?? '';
    
    if (!empty($from) && !empty($to)) {
        page("ميزان المراجعة");
        
        $report_data = get_trial_balance_data($from, $to, $cost_center);
        $balance_data = $report_data['data'];
        $totals = $report_data['totals'];
        $expense_analysis = analyze_expenses($from, $to, $cost_center);
        
        echo "<div style='padding: 20px; direction: rtl; font-family: Arial, sans-serif;'>";
        
        // أزرار التحكم
        echo "<div style='text-align: center; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>";
        echo "<a href='trialbalance1.php' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🔄 تقرير جديد</a>";
        echo "<a href='trialbalance1.php?action=print&from=" . urlencode($from) . "&to=" . urlencode($to) . "&cost_center=" . urlencode($cost_center) . "' target='_blank' style='padding: 10px 20px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>🖨️ طباعة التقرير</a>";
        echo "<a href='trialbalance1.php?action=excel&from=" . urlencode($from) . "&to=" . urlencode($to) . "&cost_center=" . urlencode($cost_center) . "' style='padding: 10px 20px; background: #f39c12; color: white; text-decoration: none; border-radius: 5px; margin: 5px; display: inline-block;'>📊 تصدير Excel</a>";
        echo "</div>";
        
        // رأس التقرير
        echo "<div style='text-align: center; margin-bottom: 30px;'>";
        echo "<h1 style='color: #2c3e50;'>ميزان المراجعة</h1>";
        echo "<h3 style='color: #7f8c8d;'>الفترة: $from إلى $to</h3>";
        
        if (!empty($cost_center)) {
            $cost_center_name = get_cost_center_name($cost_center);
            echo "<h4 style='color: #2980b9;'>مركز التكلفة: " . $cost_center_name . "</h4>";
        }
        
        echo "</div>";
        
        // معلومات التوازن
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #d4edda;'>";
        echo "<h4 style='margin: 0 0 10px 0; color: #155724;'>⚖️ معلومات التوازن</h4>";
        echo "<div style='display: flex; justify-content: space-around; flex-wrap: wrap;'>";
        echo "<div style='margin: 5px;'><strong>إجمالي المدين:</strong><br><span style='color: #27ae60; font-size: 18px;'>" . number_format($totals['current_debit'], 2) . " ر.س</span></div>";
        echo "<div style='margin: 5px;'><strong>إجمالي الدائن:</strong><br><span style='color: #e74c3c; font-size: 18px;'>" . number_format($totals['current_credit'], 2) . " ر.س</span></div>";
        echo "<div style='margin: 5px;'><strong>الفرق:</strong><br><span style='color: #2980b9; font-size: 18px;'>" . number_format($totals['difference'], 2) . " ر.س</span></div>";
        echo "</div>";
        if ($totals['difference'] == 0) {
            echo "<div style='color: green; text-align: center; margin-top: 10px; font-weight: bold; font-size: 18px;'>✓ الميزان متوازن</div>";
        } else {
            echo "<div style='color: red; text-align: center; margin-top: 10px; font-weight: bold; font-size: 18px;'>✗ الميزان غير متوازن - الفرق: " . number_format($totals['difference'], 2) . " ر.س</div>";
        }
        echo "</div>";
        
        // عرض الجدول الرئيسي
        echo "<div style='overflow-x: auto;'>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; margin: 20px 0; border: 2px solid #2c3e50;'>";
        
        // رأس الجدول الرئيسي
        echo "<tr style='background: #2c3e50; color: white;'>";
        echo "<th style='padding: 12px; text-align: center;'>الحساب</th>";
        echo "<th style='padding: 12px; text-align: center;'>أسم الحساب</th>";
        echo "<th colspan='2' style='padding: 12px; text-align: center; background: #34495e;'>ما قبله</th>";
        echo "<th colspan='2' style='padding: 12px; text-align: center; background: #16a085;'>الفترة الحالية</th>";
        echo "<th colspan='2' style='padding: 12px; text-align: center; background: #2980b9;'>الرصيد</th>";
        echo "</tr>";
        
        // رأس الجدول الفرعي
        echo "<tr style='background: #34495e; color: white;'>";
        echo "<th></th>";
        echo "<th></th>";
        echo "<th style='padding: 10px; text-align: center; background: #27ae60;'>مدين</th>";
        echo "<th style='padding: 10px; text-align: center; background: #c0392b;'>دائن</th>";
        echo "<th style='padding: 10px; text-align: center; background: #27ae60;'>مدين</th>";
        echo "<th style='padding: 10px; text-align: center; background: #c0392b;'>دائن</th>";
        echo "<th style='padding: 10px; text-align: center; background: #27ae60;'>مدين</th>";
        echo "<th style='padding: 10px; text-align: center; background: #c0392b;'>دائن</th>";
        echo "</tr>";
        
        foreach ($balance_data as $item) {
            echo "<tr style='background: #e6f3ff;'>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>التصنيف الرئيسي - " . $item['code'] . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6;'>" . $item['name'] . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6; text-align: left;'>" . number_format($item['prev_debit'], 2) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6; text-align: left;'>" . number_format($item['prev_credit'], 2) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6; text-align: left; color: #27ae60; font-weight: bold;'>" . number_format($item['current_debit'], 2) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6; text-align: left; color: #e74c3c; font-weight: bold;'>" . number_format($item['current_credit'], 2) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6; text-align: left; color: #27ae60; font-weight: bold;'>" . number_format($item['balance_debit'], 2) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #dee2e6; text-align: left; color: #e74c3c; font-weight: bold;'>" . number_format($item['balance_credit'], 2) . "</td>";
            echo "</tr>";
        }
        
        // الرصيد الختامي
        echo "<tr style='background: #d4edda; font-weight: bold;'>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60;'>الرصيد الختامي - " . $to . "</td>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60;'></td>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60; text-align: left;'>0.00</td>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60; text-align: left;'>0.00</td>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60; text-align: left; color: #27ae60;'>" . number_format($totals['current_debit'], 2) . "</td>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60; text-align: left; color: #e74c3c;'>" . number_format($totals['current_credit'], 2) . "</td>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60; text-align: left;'>0.00</td>";
        echo "<td style='padding: 15px; border-top: 2px solid #27ae60; text-align: left;'>0.00</td>";
        echo "</tr>";
        
        echo "</table>";
        echo "</div>";
        
        // تحليل المصاريف
        echo "<div style='background: #fff3cd; padding: 20px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7;'>";
        echo "<h4 style='color: #856404; margin: 0 0 15px 0;'>📊 تحليل المصاريف التفصيلي</h4>";
        
        foreach ($expense_analysis['details'] as $expense) {
            if ($expense['debit'] > 0 || $expense['credit'] > 0) {
                echo "<div style='margin: 10px 0; padding: 10px; background: white; border-radius: 3px; border-left: 4px solid #f39c12;'>";
                echo "<strong>{$expense['name']} ({$expense['code']})</strong><br>";
                echo "<span style='color: #27ae60;'>مدين: " . number_format($expense['debit'], 2) . " ر.س</span> | ";
                echo "<span style='color: #e74c3c;'>دائن: " . number_format($expense['credit'], 2) . " ر.س</span> | ";
                echo "<span style='color: #2980b9;'>صافي: " . number_format($expense['net'], 2) . " ر.س</span> | ";
                echo "<span style='color: #7f8c8d;'>عدد القيود: " . $expense['transactions'] . "</span>";
                echo "</div>";
            }
        }
        
        echo "<div style='margin-top: 15px; padding: 10px; background: #f39c12; color: white; border-radius: 3px; font-weight: bold;'>";
        echo "إجمالي المصاريف: " . number_format($expense_analysis['total'], 2) . " ر.س";
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        
        end_page();
        exit();
    }
}

// الصفحة الرئيسية
page("ميزان المراجعة");

echo "<div style='padding: 20px; text-align: center;'>";
echo "<h1>ميزان المراجعة</h1>";
echo "<p>اختر الفترة الزمنية ومركز التكلفة</p>";

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

end_table(1);
submit_center('Generate', "إنشاء التقرير");

end_form();
echo "</div>";

end_page();