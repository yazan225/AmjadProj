<?php
// Sales by Item Summary Report - Fixed Formatting
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// استخدام نظام FrontAccounting للتحقق من الدخول
$path_to_root = "..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

// التحقق من تسجيل الدخول بنفس طريقة FrontAccounting
if (!isset($_SESSION['wa_current_user']) || !$_SESSION['wa_current_user']->logged_in()) {
    header('Location: ' . $path_to_root . '/index.php');
    exit;
}

// الحصول على معلومات الشركة من الجلسة (بنفس طريقة possys.php)
$company_index = $_SESSION['wa_current_user']->company;
global $db_connections, $tbpref;
$prefix = $db_connections[$company_index]['tbpref'];
$company_name = $db_connections[$company_index]['name'];

// معلومات الاتصال من إعدادات الشركة (بنفس طريقة possys.php)
$host = $db_connections[$company_index]['host'];
$user = $db_connections[$company_index]['dbuser'];
$pass = $db_connections[$company_index]['dbpassword'];
$dbname = $db_connections[$company_index]['dbname'];

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<div style='color:red; padding:20px; font-family:Arial;'>فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</div>");
}
$conn->set_charset("utf8");

// المستخدم مسجل دخول، متابعة تنفيذ التقرير
// Get locations from database
$locations = [];
$location_sql = "SELECT loc_code, location_name FROM " . $prefix . "locations ORDER BY location_name";
$location_result = $conn->query($location_sql);

if ($location_result && $location_result->num_rows > 0) {
    while($loc = $location_result->fetch_assoc()) {
        $locations[] = $loc;
    }
}

// Get parameters from form
$from_date = isset($_POST['FromDate']) ? $_POST['FromDate'] : date('Y-m-d', strtotime('-1 month'));
$to_date = isset($_POST['ToDate']) ? $_POST['ToDate'] : date('Y-m-d');
$show_details = isset($_POST['show_details']) ? $_POST['show_details'] : 0;
$order_by = isset($_POST['order_by']) ? $_POST['order_by'] : 'sales_amount';
$sales_type = isset($_POST['sales_type']) ? $_POST['sales_type'] : 'all';
$customer_id = isset($_POST['customer_id']) ? $_POST['customer_id'] : '';
$warehouse_id = isset($_POST['warehouse_id']) ? $_POST['warehouse_id'] : '';
$item_code = isset($_POST['item_code']) ? $_POST['item_code'] : '';
$location = isset($_POST['location']) ? $_POST['location'] : (isset($locations[0]['loc_code']) ? $locations[0]['loc_code'] : '');

// دالة مساعدة بسيطة لتنسيق الأرقام إذا كانت number_format2 غير متوفرة
if (!function_exists('format_number')) {
    function format_number($number, $decimals = 2) {
        return number_format($number, $decimals, '.', ',');
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير مبيعات حسب الصنف - <?php echo $company_name; ?></title>
    <style>
        /* Screen Styles */
        @media screen {
            body {
                font-family: 'Tahoma', 'Arial', sans-serif;
                margin: 20px;
                background: #f5f5f5;
                direction: rtl;
            }
            .container {
                max-width: 1600px;
                margin: 0 auto;
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .form-container {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .no-print {
                display: block;
            }
            .print-only {
                display: none;
            }
            .filter-row {
                margin-bottom: 10px;
            }
            .filter-row td {
                padding: 8px;
            }
            .details-container {
                background: #f8f9fa;
                margin: 10px 0;
                padding: 15px;
                border-radius: 8px;
                border-right: 4px solid #007cba;
            }
            .details-header {
                background: #d1ecf1;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 10px;
                font-weight: bold;
                text-align: center;
            }
            /* أنماط الجدول المخصصة للتقرير */
            .report-table {
                border: 1px solid #ddd;
                border-collapse: collapse;
                width: 100%;
                font-family: Tahoma;
                font-size: 13px;
            }
            .report-table th {
                background: #4CAF50;
                color: white;
                padding: 10px;
                text-align: center;
                border: 1px solid #ddd;
                font-size: 12px;
            }
            .report-table td {
                padding: 6px;
                border-bottom: 1px solid #ddd;
                border: 1px solid #ddd;
                font-size: 12px;
            }
            .alt-row {
                background: #f9f9f9;
            }
            .summary-row {
                background: #e9ecef;
                font-weight: bold;
                font-size: 110%;
            }
            .details-table {
                width: 100%;
                border-collapse: collapse;
                margin: 5px 0;
                font-size: 11px;
            }
            .details-table th {
                background: #b8d4e0;
                padding: 6px;
                text-align: center;
                border: 1px solid #ccc;
                font-size: 10px;
            }
            .details-table td {
                padding: 5px;
                text-align: center;
                border: 1px solid #ddd;
                font-size: 10px;
            }
            .details-alt-row {
                background: #f8f9fa;
            }
        }

        /* Print Styles */
        @media print {
            body {
                font-family: 'Tahoma', 'Arial', sans-serif;
                margin: 0;
                padding: 15px;
                background: white;
                direction: rtl;
                font-size: 10px;
            }
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                background: white;
                box-shadow: none;
                border: none;
            }
            .page-break {
                page-break-after: always;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            thead {
                display: table-header-group;
            }
            tfoot {
                display: table-footer-group;
            }
            .alert, .form-container, .btn {
                display: none;
            }
            .details-table {
                font-size: 9px;
                margin: 3px 0;
            }
            .details-table td {
                padding: 3px;
            }
            .company-info-alert {
                display: none !important;
            }
            .details-container {
                background: #f8f9fa;
                margin: 3px 0;
                padding: 5px;
                border: 1px solid #ddd;
            }
        }

        /* Common Styles */
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input, select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        .btn {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-print {
            background: #28a745;
        }
        .btn-print:hover {
            background: #218838;
        }
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .company-info-alert {
            background: #e8f4fd;
            color: #0c5460;
            border: 2px solid #007cba;
            text-align: center;
            font-weight: bold;
        }
        .summary-stats {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid #4CAF50;
        }
        .stat-item {
            display: inline-block;
            margin: 0 15px;
            text-align: center;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #2E7D32;
        }
        .stat-label {
            font-size: 12px;
            color: #555;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .report-title {
            font-size: 20px;
            color: #34495e;
            margin-bottom: 8px;
        }
        .report-period {
            font-size: 16px;
            color: #7f8c8d;
        }
        .footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            color: #7f8c8d;
            font-size: 11px;
        }
        .details-header {
            background: #d1ecf1;
            font-weight: bold;
            padding: 6px;
            font-size: 12px;
        }
        /* Fix for table layout */
        .main-table {
            width: 100%;
            table-layout: fixed;
        }
        .main-table th:nth-child(1) { width: 8%; }  /* كود الصنف */
        .main-table th:nth-child(2) { width: 15%; } /* اسم الصنف */
        .main-table th:nth-child(3) { width: 6%; }  /* الكمية */
        .main-table th:nth-child(4) { width: 7%; }  /* متوسط السعر */
        .main-table th:nth-child(5) { width: 7%; }  /* متوسط التكلفة */
        .main-table th:nth-child(6) { width: 8%; }  /* إجمالي المبيعات */
        .main-table th:nth-child(7) { width: 7%; }  /* نسبة الخصم % */
        .main-table th:nth-child(8) { width: 8%; }  /* قيمة الخصم */
        .main-table th:nth-child(9) { width: 8%; }  /* صافي المبيعات */
        .main-table th:nth-child(10) { width: 8%; } /* قيمة التكلفة */
        .main-table th:nth-child(11) { width: 8%; } /* صافي الربح */
        .main-table th:nth-child(12) { width: 8%; } /* نسبة الربح % */
    </style>
</head>
<body>
    <div class="container">
        <!-- Report Header -->
        <div class="header">
            <div class="company-name"><?php echo $company_name; ?></div>
            <div class="report-title">تقرير مبيعات حسب الصنف - ملخص</div>
            <div class="report-period">
                الفترة: من <?php echo $from_date; ?> إلى <?php echo $to_date; ?>
            </div>
            <div class="report-period">
                تاريخ الطباعة: <?php echo date('Y-m-d H:i:s'); ?>
            </div>
        </div>

        <!-- Company Information Alert -->
        <div class="alert company-info-alert no-print">
            <i class="fas fa-building"></i>
            <strong>جاري استخدام الشركة:</strong> <?php echo $company_name; ?> 
            | <strong>بادئة الجداول:</strong> <?php echo $prefix; ?>
            | <strong>حالة الدخول:</strong> ✅ مسجل دخول
        </div>

        <!-- Filter Form - Hidden when printing -->
        <div class="form-container no-print">
            <form method="POST" action="">
                <table style="width:100%; border-collapse:collapse;">
                    <tr class="filter-row">
                        <td style="padding:10px;">
                            <label for="FromDate">من تاريخ:</label>
                            <input type="date" id="FromDate" name="FromDate" value="<?php echo $from_date; ?>" required>
                        </td>
                        <td style="padding:10px;">
                            <label for="ToDate">إلى تاريخ:</label>
                            <input type="date" id="ToDate" name="ToDate" value="<?php echo $to_date; ?>" required>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td style="padding:10px;">
                            <label for="sales_type">نوع الحركات:</label>
                            <select name="sales_type" id="sales_type" style="width:200px;">
                                <option value="all" <?php echo $sales_type == 'all' ? 'selected' : ''; ?>>جميع أنواع المبيعات</option>
                                <option value="10" <?php echo $sales_type == '10' ? 'selected' : ''; ?>>المبيعات فقط (النوع 10)</option>
                                <option value="all_positive" <?php echo $sales_type == 'all_positive' ? 'selected' : ''; ?>>جميع الحركات الموجبة</option>
                            </select>
                        </td>
                        <td style="padding:10px;">
                            <label for="order_by">ترتيب حسب:</label>
                            <select name="order_by" id="order_by" style="width:200px;">
                                <option value="sales_amount" <?php echo $order_by == 'sales_amount' ? 'selected' : ''; ?>>قيمة المبيعات</option>
                                <option value="stock_id" <?php echo $order_by == 'stock_id' ? 'selected' : ''; ?>>كود الصنف</option>
                                <option value="description" <?php echo $order_by == 'description' ? 'selected' : ''; ?>>اسم الصنف</option>
                                <option value="qty_sold" <?php echo $order_by == 'qty_sold' ? 'selected' : ''; ?>>الكمية المباعة</option>
                                <option value="net_profit" <?php echo $order_by == 'net_profit' ? 'selected' : ''; ?>>صافي الربح</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td style="padding:10px;">
                            <label for="customer_id">العميل:</label>
                            <select name="customer_id" id="customer_id" style="width:200px;">
                                <option value="">جميع العملاء</option>
                                <?php
                                $customers_sql = "SELECT debtor_no, name FROM " . $prefix . "debtors_master ORDER BY name";
                                $customers_result = $conn->query($customers_sql);
                                if ($customers_result) {
                                    while ($customer = $customers_result->fetch_assoc()) {
                                        $selected = ($customer_id == $customer['debtor_no']) ? 'selected' : '';
                                        echo "<option value='{$customer['debtor_no']}' $selected>{$customer['name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </td>
                        <td style="padding:10px;">
                            <label for="location">المستودع:</label>
                            <select name="location" id="location" style="width:200px;">
                                <?php if (!empty($locations)): ?>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['loc_code'] ?>" 
                                            <?= $loc['loc_code'] == $location ? 'selected' : '' ?>>
                                            <?= $loc['location_name'] ?> (<?= $loc['loc_code'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">لا توجد مستودعات</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td style="padding:10px;">
                            <label for="item_code">كود الصنف:</label>
                            <select name="item_code" id="item_code" style="width:200px;">
                                <option value="">جميع الأصناف</option>
                                <?php
                                $items_sql = "SELECT stock_id, description FROM " . $prefix . "stock_master WHERE inactive=0 ORDER BY stock_id";
                                $items_result = $conn->query($items_sql);
                                if ($items_result) {
                                    while ($item = $items_result->fetch_assoc()) {
                                        $selected = ($item_code == $item['stock_id']) ? 'selected' : '';
                                        echo "<option value='{$item['stock_id']}' $selected>{$item['stock_id']} - {$item['description']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </td>
                        <td style="padding:10px; vertical-align:bottom;">
                            <label for="show_details" style="display:inline;">
                                <input type="checkbox" id="show_details" name="show_details" value="1" <?php echo $show_details ? 'checked' : ''; ?>>
                                عرض التفاصيل والحركات
                            </label>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td colspan="2" style="padding:10px; text-align:center;">
                            <button type="submit" name="Refresh" class="btn">عرض التقرير</button>
                            <button type="button" class="btn btn-print" onclick="window.print()">🖨️ طباعة التقرير</button>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <?php
        // Display report if form submitted
        if (isset($_POST['Refresh'])) {
            
            // أولاً: اختبار الاتصال وعرض معلومات التصحيح
            echo "<div class='alert alert-info no-print'>";
            echo "<strong>معلومات التصحيح:</strong><br>";
            echo "الاتصال بقاعدة البيانات: ✅ ناجح<br>";
            echo "الشركة: " . $company_name . "<br>";
            echo "بادئة الجداول: " . $prefix . "<br>";
            echo "الفترة: " . $from_date . " إلى " . $to_date . "<br>";
            echo "</div>";

            // بناء الاستعلام الأساسي أولاً للتأكد من وجود بيانات
            $test_sql = "SELECT COUNT(*) as total_count FROM " . $prefix . "stock_moves 
                        WHERE tran_date BETWEEN '$from_date' AND '$to_date'";
            
            $test_result = $conn->query($test_sql);
            if ($test_result) {
                $test_row = $test_result->fetch_assoc();
                echo "<div class='alert alert-info no-print'>";
                echo "<strong>اختبار البيانات الأساسية:</strong><br>";
                echo "إجمالي الحركات في الفترة: " . $test_row['total_count'] . "<br>";
                echo "</div>";
            }

            // بناء الاستعلام الرئيسي بشكل مبسط أولاً
            $base_where = "sm.tran_date BETWEEN '$from_date' AND '$to_date' 
                          AND stk.inactive = 0 
                          AND sm.qty > 0";
            
            // إضافة فلتر نوع الحركات
            if ($sales_type == '10') {
                $base_where .= " AND sm.type = 10";
                $report_type_note = "عرض حركات المبيعات فقط (النوع 10)";
            } elseif ($sales_type == 'all_positive') {
                $base_where .= " AND sm.qty > 0";
                $report_type_note = "عرض جميع الحركات الموجبة (كمية > 0)";
            } else {
                $report_type_note = "عرض جميع أنواع الحركات";
            }
            
            // إضافة فلتر المستودع
            if (!empty($location)) {
                $base_where .= " AND sm.loc_code = '$location'";
                $location_name = "";
                foreach ($locations as $loc) {
                    if ($loc['loc_code'] == $location) {
                        $location_name = $loc['location_name'];
                        break;
                    }
                }
                $report_type_note .= " | المستودع: " . $location_name;
            }
            
            // إضافة فلتر الصنف
            if (!empty($item_code)) {
                $base_where .= " AND sm.stock_id = '$item_code'";
                $report_type_note .= " | الصنف: " . $item_code;
            }

            // الاستعلام الرئيسي مع بيانات الخصم من debtor_trans_details
            $sql = "SELECT 
                        sm.stock_id,
                        stk.description,
                        SUM(sm.qty) as qty_sold,
                        SUM(sm.qty * sm.price) as gross_sales_amount,
                        SUM(sm.qty * sm.standard_cost) as cost_amount,
                        AVG(sm.price) as avg_price,
                        AVG(sm.standard_cost) as avg_cost,
                        COALESCE(SUM(sm.qty * sm.price * dtd.discount_percent), 0) as total_discount_amount,
                        CASE 
                            WHEN SUM(sm.qty * sm.price) > 0 
                            THEN (COALESCE(SUM(sm.qty * sm.price * dtd.discount_percent), 0) / SUM(sm.qty * sm.price)) * 100 
                            ELSE 0 
                        END as discount_percentage
                    FROM " . $prefix . "stock_moves sm
                    INNER JOIN " . $prefix . "stock_master stk ON sm.stock_id = stk.stock_id
                    LEFT JOIN " . $prefix . "debtor_trans_details dtd ON sm.reference = dtd.debtor_trans_no AND sm.stock_id = dtd.stock_id
                    WHERE $base_where
                    GROUP BY sm.stock_id, stk.description
                    HAVING qty_sold > 0";

            // إضافة الترتيب
            switch ($order_by) {
                case 'stock_id':
                    $sql .= " ORDER BY sm.stock_id";
                    break;
                case 'description':
                    $sql .= " ORDER BY stk.description";
                    break;
                case 'qty_sold':
                    $sql .= " ORDER BY qty_sold DESC";
                    break;
                case 'net_profit':
                    $sql .= " ORDER BY (SUM(sm.qty * sm.price) - COALESCE(SUM(sm.qty * sm.price * dtd.discount_percent), 0) - SUM(sm.qty * sm.standard_cost)) DESC";
                    break;
                case 'sales_amount':
                default:
                    $sql .= " ORDER BY SUM(sm.qty * sm.price) DESC";
                    break;
            }

            echo "<div class='alert alert-info no-print'>";
            echo "<strong>الاستعلام الرئيسي:</strong><br>";
            echo htmlspecialchars($sql) . "<br>";
            echo "</div>";

            $result = $conn->query($sql);
            
            if (!$result) {
                echo "<div class='alert alert-error'>خطأ في الاستعلام: " . $conn->error . "</div>";
                echo "<div class='alert alert-info no-print'>";
                echo "<strong>تفاصيل الخطأ:</strong><br>";
                echo "الاستعلام: " . htmlspecialchars($sql) . "<br>";
                echo "</div>";
            } else {
                $row_count = $result->num_rows;
                
                echo "<div class='alert alert-info no-print'>";
                echo "<strong>نتيجة الاستعلام:</strong> " . $row_count . " سجل<br>";
                echo "</div>";
                
                if ($row_count > 0) {
                    // Summary totals
                    $total_qty = 0;
                    $total_gross_sales = 0;
                    $total_discount = 0;
                    $total_net_sales = 0;
                    $total_cost = 0;
                    $total_net_profit = 0;
                    
                    // Display summary statistics
                    echo "<div class='summary-stats'>";
                    echo "<h3 style='text-align:center; color:#2E7D32;'>إحصائيات التقرير</h3>";
                    echo "<div style='text-align:center;'>";
                    echo "<div class='stat-item'>";
                    echo "<div class='stat-value'>" . $row_count . "</div>";
                    echo "<div class='stat-label'>عدد الأصناف</div>";
                    echo "</div>";
                    
                    // حساب الإجماليات أولاً
                    $temp_result = $conn->query($sql);
                    if ($temp_result) {
                        while ($myrow = $temp_result->fetch_assoc()) {
                            $total_qty += $myrow['qty_sold'];
                            $total_gross_sales += $myrow['gross_sales_amount'];
                            $total_discount += $myrow['total_discount_amount'];
                            $total_cost += $myrow['cost_amount'];
                        }
                    }
                    
                    // حساب صافي المبيعات والربح حسب المعادلة الجديدة
                    $total_net_sales = $total_gross_sales - $total_discount;
                    $total_net_profit = $total_net_sales - $total_cost; // إجمالي البيع - قيمة الخصم - التكلفة
                    
                    echo "<div class='stat-item'>";
                    echo "<div class='stat-value'>" . format_number($total_qty, 0) . "</div>";
                    echo "<div class='stat-label'>إجمالي الكمية</div>";
                    echo "</div>";
                    
                    echo "<div class='stat-item'>";
                    echo "<div class='stat-value'>" . format_number($total_gross_sales, 2) . " ₪</div>";
                    echo "<div class='stat-label'>إجمالي المبيعات</div>";
                    echo "</div>";
                    
                    echo "<div class='stat-item'>";
                    echo "<div class='stat-value' style='color:#D32F2F'>" . format_number($total_discount, 2) . " ₪</div>";
                    echo "<div class='stat-label'>إجمالي الخصم</div>";
                    echo "</div>";
                    
                    echo "<div class='stat-item'>";
                    echo "<div class='stat-value'>" . format_number($total_net_sales, 2) . " ₪</div>";
                    echo "<div class='stat-label'>صافي المبيعات</div>";
                    echo "</div>";
                    
                    $net_profit_color = ($total_net_profit >= 0) ? '#2E7D32' : '#D32F2F';
                    echo "<div class='stat-item'>";
                    echo "<div class='stat-value' style='color:$net_profit_color'>" . format_number($total_net_profit, 2) . " ₪</div>";
                    echo "<div class='stat-label'>صافي الربح</div>";
                    echo "</div>";
                    
                    $profit_margin = ($total_net_sales > 0) ? ($total_net_profit / $total_net_sales) * 100 : 0;
                    echo "<div class='stat-item'>";
                    $margin_color = ($profit_margin >= 0) ? '#2E7D32' : '#D32F2F';
                    echo "<div class='stat-value' style='color:$margin_color'>" . format_number($profit_margin, 1) . "%</div>";
                    echo "<div class='stat-label'>هامش الربح</div>";
                    echo "</div>";
                    
                    echo "</div>";
                    echo "<div style='text-align:center; margin-top:10px; color:#666;'>$report_type_note</div>";
                    if ($show_details) {
                        echo "<div style='text-align:center; margin-top:5px; color:#007cba;'><strong>✓ تم تفعيل عرض التفاصيل</strong></div>";
                    }
                    echo "</div>";
                    
                    // Display report table
                    echo "<div class='print-only' style='text-align:center; margin:20px 0; font-weight:bold;'>تفاصيل المبيعات حسب الصنف</div>";
                    
                    // استخدام HTML مباشرة لعرض الجدول
                    echo "<table class='report-table main-table'>";
                    echo "<thead><tr>";
                    $headers = array(
                        "كود الصنف", 
                        "اسم الصنف", 
                        "الكمية", 
                        "متوسط السعر",
                        "متوسط التكلفة",
                        "إجمالي المبيعات", 
                        "نسبة الخصم %",
                        "قيمة الخصم",
                        "صافي المبيعات", 
                        "قيمة التكلفة", 
                        "صافي الربح", 
                        "نسبة الربح %"
                    );
                    foreach($headers as $header) {
                        echo "<th>$header</th>";
                    }
                    echo "</tr></thead><tbody>";
                    
                    $k = 0;
                    
                    // إعادة تعيين المؤشر لقراءة البيانات مرة أخرى للعرض
                    $result->data_seek(0);
                    
                    while ($myrow = $result->fetch_assoc()) {
                        $gross_sales = $myrow['gross_sales_amount'];
                        $discount_amount = $myrow['total_discount_amount'];
                        $discount_percentage = $myrow['discount_percentage'];
                        $net_sales = $gross_sales - $discount_amount;
                        $cost_amount = $myrow['cost_amount'];
                        $net_profit = $net_sales - $cost_amount; // إجمالي البيع - قيمة الخصم - التكلفة
                        $profit_percent = ($net_sales != 0) ? ($net_profit / $net_sales) * 100 : 0;
                        
                        // Format numbers
                        $qty_sold = format_number($myrow['qty_sold'], 0);
                        $avg_price = format_number($myrow['avg_price'], 2);
                        $avg_cost = format_number($myrow['avg_cost'], 2);
                        $gross_sales_fmt = format_number($gross_sales, 2);
                        $discount_percentage_fmt = format_number($discount_percentage, 1) . '%';
                        $discount_amount_fmt = format_number($discount_amount, 2);
                        $net_sales_fmt = format_number($net_sales, 2);
                        $cost_fmt = format_number($cost_amount, 2);
                        $net_profit_fmt = format_number($net_profit, 2);
                        $profit_percent_fmt = format_number($profit_percent, 1) . '%';
                        
                        // Color code profit
                        $net_profit_color = ($net_profit >= 0) ? 'color: #006400; font-weight:bold;' : 'color: #8B0000; font-weight:bold;';
                        $profit_percent_color = ($profit_percent >= 0) ? 'color: #006400; font-weight:bold;' : 'color: #8B0000; font-weight:bold;';
                        
                        $row_class = $k % 2 == 0 ? '' : 'class="alt-row"';
                        echo "<tr $row_class>";
                        echo "<td>{$myrow['stock_id']}</td>";
                        echo "<td>{$myrow['description']}</td>";
                        echo "<td style='text-align:center;'>{$qty_sold}</td>";
                        echo "<td style='text-align:center;'>{$avg_price} ₪</td>";
                        echo "<td style='text-align:center;'>{$avg_cost} ₪</td>";
                        echo "<td style='text-align:center;'>{$gross_sales_fmt} ₪</td>";
                        echo "<td style='text-align:center; color:#D32F2F;'>{$discount_percentage_fmt}</td>";
                        echo "<td style='text-align:center; color:#D32F2F;'>{$discount_amount_fmt} ₪</td>";
                        echo "<td style='text-align:center; font-weight:bold;'>{$net_sales_fmt} ₪</td>";
                        echo "<td style='text-align:center;'>{$cost_fmt} ₪</td>";
                        echo "<td style='text-align:center;'><span style='$net_profit_color'>{$net_profit_fmt} ₪</span></td>";
                        echo "<td style='text-align:center;'><span style='$profit_percent_color'>{$profit_percent_fmt}</span></td>";
                        echo "</tr>";
                        
                        // Show detailed transactions if requested
                        if ($show_details) {
                            // استعلام مبسط للحصول على تفاصيل الحركات مع الخصم
                            $detail_sql = "SELECT 
                                        sm.tran_date, 
                                        sm.reference, 
                                        sm.type,
                                        sm.qty, 
                                        sm.price, 
                                        sm.standard_cost,
                                        (sm.qty * sm.price) as gross_line_total,
                                        (sm.qty * sm.standard_cost) as line_cost,
                                        COALESCE(dtd.discount_percent, 0) as discount_rate,
                                        (sm.qty * sm.price * COALESCE(dtd.discount_percent, 0)) as line_discount,
                                        dt.debtor_no,
                                        dm.name as customer_name,
                                        loc.location_name as warehouse_name
                                    FROM " . $prefix . "stock_moves sm
                                    LEFT JOIN " . $prefix . "debtor_trans_details dtd ON sm.reference = dtd.debtor_trans_no AND sm.stock_id = dtd.stock_id
                                    LEFT JOIN " . $prefix . "debtor_trans dt ON sm.reference = dt.reference AND sm.tran_date = dt.tran_date
                                    LEFT JOIN " . $prefix . "debtors_master dm ON dt.debtor_no = dm.debtor_no
                                    LEFT JOIN " . $prefix . "locations loc ON sm.loc_code = loc.loc_code
                                    WHERE sm.stock_id = '" . $myrow['stock_id'] . "' 
                                    AND sm.tran_date BETWEEN '$from_date' AND '$to_date'";
                            
                            // Add filters to detail query
                            if (!empty($customer_id)) {
                                $detail_sql .= " AND (dt.debtor_no = '$customer_id' OR dt.debtor_no IS NULL)";
                            }
                            if (!empty($location)) {
                                $detail_sql .= " AND sm.loc_code = '$location'";
                            }
                            
                            $detail_sql .= " ORDER BY sm.tran_date DESC";
                            
                            $detail_result = $conn->query($detail_sql);
                            
                            if ($detail_result && $detail_result->num_rows > 0) {
                                // استخدام div منظم لعرض التفاصيل
                                echo "<tr><td colspan='12' style='padding:0;'>";
                                echo "<div class='details-container'>";
                                echo "<div class='details-header'>";
                                echo "حركات الصنف: <strong>" . $myrow['stock_id'] . " - " . $myrow['description'] . "</strong>";
                                echo " (عدد الحركات: " . $detail_result->num_rows . ")";
                                echo "</div>";
                                
                                // جدول التفاصيل المنظم
                                echo "<table class='details-table'>";
                                echo "<tr>";
                                echo "<th style='width:8%;'>التاريخ</th>";
                                echo "<th style='width:10%;'>المرجع</th>";
                                echo "<th style='width:5%;'>النوع</th>";
                                echo "<th style='width:5%;'>الكمية</th>";
                                echo "<th style='width:7%;'>سعر الوحدة</th>";
                                echo "<th style='width:8%;'>إجمالي السعر</th>";
                                echo "<th style='width:6%;'>نسبة الخصم</th>";
                                echo "<th style='width:8%;'>قيمة الخصم</th>";
                                echo "<th style='width:8%;'>صافي السعر</th>";
                                echo "<th style='width:7%;'>تكلفة الوحدة</th>";
                                echo "<th style='width:8%;'>إجمالي التكلفة</th>";
                                echo "<th style='width:10%;'>الربح</th>";
                                echo "<th style='width:10%;'>العميل</th>";
                                echo "</tr>";
                                
                                $detail_k = 0;
                                $detail_total_qty = 0;
                                $detail_total_gross_sales = 0;
                                $detail_total_discount = 0;
                                $detail_total_net_sales = 0;
                                $detail_total_cost = 0;
                                $detail_total_profit = 0;
                                
                                while ($detail = $detail_result->fetch_assoc()) {
                                    $detail_bg = $detail_k % 2 == 0 ? '' : 'class="details-alt-row"';
                                    $line_net_sales = $detail['gross_line_total'] - $detail['line_discount'];
                                    $line_profit = $line_net_sales - $detail['line_cost'];
                                    
                                    echo "<tr $detail_bg>";
                                    echo "<td>" . $detail['tran_date'] . "</td>";
                                    echo "<td>" . $detail['reference'] . "</td>";
                                    echo "<td>" . $detail['type'] . "</td>";
                                    echo "<td>" . format_number($detail['qty'], 0) . "</td>";
                                    echo "<td>" . format_number($detail['price'], 2) . " ₪</td>";
                                    echo "<td>" . format_number($detail['gross_line_total'], 2) . " ₪</td>";
                                    echo "<td style='color:#D32F2F;'>" . format_number($detail['discount_rate'] * 100, 1) . "%</td>";
                                    echo "<td style='color:#D32F2F;'>" . format_number($detail['line_discount'], 2) . " ₪</td>";
                                    echo "<td style='font-weight:bold;'>" . format_number($line_net_sales, 2) . " ₪</td>";
                                    echo "<td>" . format_number($detail['standard_cost'], 2) . " ₪</td>";
                                    echo "<td>" . format_number($detail['line_cost'], 2) . " ₪</td>";
                                    
                                    $line_profit_color = ($line_profit >= 0) ? '#006400' : '#8B0000';
                                    echo "<td style='color:$line_profit_color; font-weight:bold;'>" . format_number($line_profit, 2) . " ₪</td>";
                                    echo "<td>" . ($detail['customer_name'] ?: 'Walk-In Customer') . "</td>";
                                    echo "</tr>";
                                    
                                    // حساب الإجماليات للتفاصيل
                                    $detail_total_qty += $detail['qty'];
                                    $detail_total_gross_sales += $detail['gross_line_total'];
                                    $detail_total_discount += $detail['line_discount'];
                                    $detail_total_net_sales += $line_net_sales;
                                    $detail_total_cost += $detail['line_cost'];
                                    $detail_total_profit += $line_profit;
                                    $detail_k++;
                                }
                                
                                // صف الإجمالي للتفاصيل
                                echo "<tr style='background:#e8f5e8; font-weight:bold;'>";
                                echo "<td colspan='3'>إجمالي الحركات</td>";
                                echo "<td>" . format_number($detail_total_qty, 0) . "</td>";
                                echo "<td>-</td>";
                                echo "<td>" . format_number($detail_total_gross_sales, 2) . " ₪</td>";
                                echo "<td>-</td>";
                                echo "<td>" . format_number($detail_total_discount, 2) . " ₪</td>";
                                echo "<td>" . format_number($detail_total_net_sales, 2) . " ₪</td>";
                                echo "<td>-</td>";
                                echo "<td>" . format_number($detail_total_cost, 2) . " ₪</td>";
                                
                                $total_profit_color = ($detail_total_profit >= 0) ? '#006400' : '#8B0000';
                                echo "<td style='color:$total_profit_color;'>" . format_number($detail_total_profit, 2) . " ₪</td>";
                                echo "<td>-</td>";
                                echo "</tr>";
                                
                                echo "</table>";
                                echo "</div>";
                                echo "</td></tr>";
                            }
                        }
                        
                        $k++;
                    }
                    
                    // Display summary row
                    $total_discount_percentage = ($total_gross_sales != 0) ? ($total_discount / $total_gross_sales) * 100 : 0;
                    $total_profit_percent = ($total_net_sales != 0) ? ($total_net_profit / $total_net_sales) * 100 : 0;
                    
                    echo "<tr class='summary-row'>";
                    echo "<td colspan='2' style='text-align:center;'>الإجمالي العام</td>";
                    echo "<td style='text-align:center;'>" . format_number($total_qty, 0) . "</td>";
                    echo "<td style='text-align:center;'></td>"; // متوسط السعر
                    echo "<td style='text-align:center;'></td>"; // متوسط التكلفة
                    echo "<td style='text-align:center;'>" . format_number($total_gross_sales, 2) . " ₪</td>";
                    echo "<td style='text-align:center; color:#D32F2F;'>" . format_number($total_discount_percentage, 1) . "%</td>";
                    echo "<td style='text-align:center; color:#D32F2F;'>" . format_number($total_discount, 2) . " ₪</td>";
                    echo "<td style='text-align:center;'>" . format_number($total_net_sales, 2) . " ₪</td>";
                    echo "<td style='text-align:center;'>" . format_number($total_cost, 2) . " ₪</td>";
                    
                    $total_net_profit_color = ($total_net_profit >= 0) ? 'color: #006400;' : 'color: #8B0000;';
                    $total_profit_percent_color = ($total_profit_percent >= 0) ? 'color: #006400;' : 'color: #8B0000;';
                    
                    echo "<td style='text-align:center;'><span style='$total_net_profit_color'>" . format_number($total_net_profit, 2) . " ₪</span></td>";
                    echo "<td style='text-align:center;'><span style='$total_profit_percent_color'>" . format_number($total_profit_percent, 1) . "%</span></td>";
                    echo "</tr>";
                    
                    echo "</tbody></table>";
                    
                    // Print button
                    echo "<div class='no-print' style='text-align:center; margin:20px 0;'>";
                    echo "<button class='btn btn-print' onclick='window.print()'>🖨️ طباعة التقرير</button>";
                    echo "</div>";
                    
                    // Footer
                    echo "<div class='footer'>";
                    echo "تم إنشاء التقرير في: " . date('Y-m-d H:i:s') . " | ";
                    echo "عدد السجلات: " . $row_count . " | ";
                    echo "نوع التقرير: " . $report_type_note;
                    if ($show_details) {
                        echo " | مع التفاصيل";
                    }
                    echo "</div>";
                    
                } else {
                    echo "<div class='alert alert-info'>لا توجد مبيعات للأصناف في الفترة المحددة.</div>";
                    echo "<div class='alert alert-info no-print'>";
                    echo "<strong>تفاصيل البحث:</strong><br>";
                    echo "الفترة: $from_date إلى $to_date<br>";
                    echo "المستودع: $location<br>";
                    echo "العميل: " . ($customer_id ?: 'جميع العملاء') . "<br>";
                    echo "الصنف: " . ($item_code ?: 'جميع الأصناف') . "<br>";
                    echo "نوع الحركات: $report_type_note<br>";
                    echo "</div>";
                }
            }
        } else {
            // Show instructions when no report is generated
            echo "<div class='alert alert-info no-print'>";
            echo "<h3>تعليمات استخدام التقرير:</h3>";
            echo "<p>1. اختر الفترة الزمنية المطلوبة</p>";
            echo "<p>2. اختر نوع الحركات المراد عرضها</p>";
            echo "<p>3. اختر طريقة ترتيب النتائج</p>";
            echo "<p>4. استخدم الفلاتر الإضافية (العميل، المستودع، الصنف) لتصفية النتائج</p>";
            echo "<p>5. اختر 'عرض التفاصيل والحركات' لرؤية جميع الحركات</p>";
            echo "<p>6. اضغط على 'عرض التقرير'</p>";
            echo "<p>7. استخدم زر 'طباعة التقرير' لطباعة نسخة ورقية</p>";
            echo "</div>";
        }
        
        // Close connection
        $conn->close();
        ?>
    </div>

    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>