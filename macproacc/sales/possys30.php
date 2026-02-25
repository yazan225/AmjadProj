<?php 
$path_to_root = "..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

$company_index = $_SESSION['wa_current_user']->company;
global $db_connections, $tbpref;
$prefix = $db_connections[$company_index]['tbpref'];
$company_name = $db_connections[$company_index]['name'];

// إضافة تحقق من أن البادئة صحيحة
if (empty($prefix)) {
    die("<h2 style='color:red'>خطأ: لم يتم تحديد بادئة الجداول (table prefix) بشكل صحيح!</h2>");
}

include_once($path_to_root . "/sales/includes/sales_db.inc");
include_once($path_to_root . "/includes/db/sales_types_db.inc");
define('ST_SALESINVOICE', 10);
define('ST_CUSTPAYMENT', 12);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$user = '2Gusrgx123';
$pass = 'PP@!pswgh_11';
$dbname = 'ghusn_co_fa2418';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<h2 style='color:red'>فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</h2>");
}
$conn->set_charset("utf8");

// بداية الجلسة إذا لم تكن بدأت
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// عرض رسالة النجاح من الجلسة إذا وجدت
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// عرض رسالة الخطأ من الجلسة إذا وجدت
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// التأكد من أن جميع الجداول موجودة
$required_tables = ['debtor_trans', 'debtor_trans_details', 'stock_moves', 'sales_orders', 'trans_tax_details'];
foreach ($required_tables as $table) {
    $sql_check = "SHOW TABLES LIKE '{$prefix}{$table}'";
    $result = $conn->query($sql_check);
    if (!$result || $result->num_rows == 0) {
        die("<h2 style='color:red'>خطأ: الجدول {$prefix}{$table} غير موجود في قاعدة البيانات!</h2>");
    }
}

// دالة مساعدة لعرض الأخطاء بوضوح
function logError($message, $conn = null) {
    error_log("POS ERROR: " . $message);
    if ($conn) {
        error_log("MySQL Error: " . $conn->error);
    }
    return "<div class='alert alert-error'>
        <i class='fas fa-exclamation-triangle'></i>
        <div>" . htmlspecialchars($message) . "</div>
    </div>";
}

// الحصول على جميع المخازن
$locations = [];
$sql_locations = "SELECT loc_code, location_name FROM {$prefix}locations ORDER BY loc_code";
$result_locations = $conn->query($sql_locations);
if ($result_locations && $result_locations->num_rows > 0) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
}

// الحصول على جميع مراكز التكلفة (الأبعاد) - التصحيح النهائي
$cost_centers = [];
$sql_cost_centers = "SELECT id, reference, name, type_, closed FROM {$prefix}dimensions WHERE closed = 0 ORDER BY id";
$result_cost_centers = $conn->query($sql_cost_centers);
if ($result_cost_centers && $result_cost_centers->num_rows > 0) {
    while ($row = $result_cost_centers->fetch_assoc()) {
        $cost_centers[] = $row;
    }
} else {
    // إذا لم توجد مراكز تكلفة، ننشئ واحد افتراضي
    $cost_centers[] = ['id' => 0, 'reference' => 'DEF', 'name' => 'مركز التكلفة الافتراضي', 'type_' => 1, 'closed' => 0];
}

// الحصول على جميع أنواع المبيعات النشطة - من sales_types
$sales_types = [];
$sql_sales_types = "SELECT id, sales_type, tax_included, factor FROM {$prefix}sales_types WHERE inactive = 0 ORDER BY id";
$result_sales_types = $conn->query($sql_sales_types);
if ($result_sales_types && $result_sales_types->num_rows > 0) {
    while ($row = $result_sales_types->fetch_assoc()) {
        $sales_types[] = $row;
    }
}

// إذا لم توجد أنواع مبيعات، ننشئ واحدة افتراضية
if (count($sales_types) == 0) {
    $sales_types[] = [
        'id' => 1, 
        'sales_type' => 'مبيعات عامة', 
        'tax_included' => 1, // 1 = الضريبة مضمنة في السعر
        'factor' => 1.0
    ];
}

// الحصول على جميع أنواع الضرائب النشطة - من tax_types
$tax_types = [];
$sql_tax_types = "SELECT id, name, rate, sales_gl_code, purchasing_gl_code FROM {$prefix}tax_types WHERE inactive = 0 ORDER BY id";
$result_tax_types = $conn->query($sql_tax_types);
if ($result_tax_types && $result_tax_types->num_rows > 0) {
    while ($row = $result_tax_types->fetch_assoc()) {
        $tax_types[] = $row;
    }
}

// إذا لم توجد ضرائب، ننشئ واحدة افتراضية
if (count($tax_types) == 0) {
    $tax_types[] = [
        'id' => 1, 
        'name' => 'ضريبة القيمة المضافة', 
        'rate' => 16, 
        'sales_gl_code' => '21040001', 
        'purchasing_gl_code' => ''
    ];
}

// دالة للحصول على اسم الضريبة مع النسبة
function getTaxNameWithRate($tax_types, $tax_id) {
    foreach ($tax_types as $tax) {
        if ($tax['id'] == $tax_id) {
            return $tax['name'] . " (" . $tax['rate'] . "%)";
        }
    }
    return "غير محدد";
}

// تعيين المخزن الافتراضي
if (!isset($_SESSION['selected_location']) && count($locations) > 0) {
    $_SESSION['selected_location'] = $locations[0]['loc_code'];
}

// تعيين مركز التكلفة الافتراضي
if (!isset($_SESSION['selected_cost_center']) && count($cost_centers) > 0) {
    $_SESSION['selected_cost_center'] = $cost_centers[0]['id'];
}

// تعيين نوع المبيعات الافتراضي
if (!isset($_SESSION['selected_sales_type']) && count($sales_types) > 0) {
    $_SESSION['selected_sales_type'] = $sales_types[0]['id'];
}

// تعيين نوع الضريبة الافتراضي
if (!isset($_SESSION['selected_tax_type']) && count($tax_types) > 0) {
    $_SESSION['selected_tax_type'] = $tax_types[0]['id'];
}

// التعامل مع تغيير المخزن ومركز التكلفة ونوع المبيعات ونوع الضريبة
if (isset($_POST['change_location'])) {
    $_SESSION['selected_location'] = $_POST['location'];
    if (isset($_POST['cost_center'])) {
        $_SESSION['selected_cost_center'] = $_POST['cost_center'];
    }
    if (isset($_POST['sales_type'])) {
        $_SESSION['selected_sales_type'] = $_POST['sales_type'];
    }
    if (isset($_POST['tax_type'])) {
        $_SESSION['selected_tax_type'] = $_POST['tax_type'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$default_location = $_SESSION['selected_location'] ?? 'DEF';
$default_cost_center = $_SESSION['selected_cost_center'] ?? 0;
$default_sales_type = $_SESSION['selected_sales_type'] ?? 1;
$default_tax_type = $_SESSION['selected_tax_type'] ?? 1;

// الحصول على بيانات نوع المبيعات المحدد
$selected_sales_type_data = null;
foreach ($sales_types as $sales_type) {
    if ($sales_type['id'] == $default_sales_type) {
        $selected_sales_type_data = $sales_type;
        break;
    }
}

// الحصول على بيانات الضريبة المحددة
$selected_tax_data = null;
foreach ($tax_types as $tax) {
    if ($tax['id'] == $default_tax_type) {
        $selected_tax_data = $tax;
        break;
    }
}

// حساب الضريبة الحالية - مع مراعاة ما إذا كانت الضريبة مضمنة من sales_type
$current_tax_rate = 0;
$tax_included_in_price = $selected_sales_type_data ? $selected_sales_type_data['tax_included'] : 1; // من sales_type

if ($selected_tax_data && $selected_tax_data['rate'] > 0) {
    $current_tax_rate = $selected_tax_data['rate'] / 100;
}

// الحصول على زبون Walk-In
$walkin_debtor_no = null;
$branch_code = "1";

$sql = "SELECT debtor_no, debtor_ref, name FROM {$prefix}debtors_master 
        WHERE (debtor_ref = 'Walk-In' OR name LIKE '%Walk-In Customer%' OR debtor_ref = '2') 
        AND inactive = 0 
        LIMIT 1";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $walkin_debtor_no = (int)$row['debtor_no'];
} else {
    $sql = "SELECT debtor_no FROM {$prefix}debtors_master WHERE inactive = 0 ORDER BY debtor_no LIMIT 1";
    $result = $conn->query($sql);
    if ($row = $result->fetch_assoc()) {
        $walkin_debtor_no = (int)$row['debtor_no'];
    } else {
        die("<h3 style='color:red'>خطأ: لم يتم العثور على أي زبون في قاعدة البيانات!</h3>");
    }
}

// الحصول على كود الفرع
$sql_branch = "SELECT branch_code FROM {$prefix}cust_branch 
               WHERE debtor_no = ? AND inactive = 0 
               ORDER BY branch_code LIMIT 1";
$stmt_branch = $conn->prepare($sql_branch);
$stmt_branch->bind_param("i", $walkin_debtor_no);
$stmt_branch->execute();
$result_branch = $stmt_branch->get_result();
if ($row_branch = $result_branch->fetch_assoc()) {
    $branch_code = $row_branch['branch_code'];
} else {
    $sql_insert_branch = "INSERT INTO {$prefix}cust_branch 
                         (debtor_no, branch_code, br_name, inactive) 
                         VALUES (?, '1', 'الفرع الرئيسي', 0)";
    $stmt_insert = $conn->prepare($sql_insert_branch);
    $stmt_insert->bind_param("i", $walkin_debtor_no);
    if ($stmt_insert->execute()) {
        $branch_code = "1";
    }
}

// دالة للحصول على الأصناف
function getStockItems($conn, $prefix, $location) {
    $all_stock_items = [];
    
    $sql_all_items = "
        SELECT 
            sm.stock_id, 
            sm.description, 
            COALESCE(SUM(CASE WHEN stm.loc_code = ? THEN stm.qty ELSE 0 END), 0) as on_hand_qty,
            COALESCE(
                (SELECT price FROM {$prefix}prices WHERE stock_id = sm.stock_id AND curr_abrev = 'ILS' LIMIT 1),
                (SELECT price FROM {$prefix}prices WHERE stock_id = sm.stock_id AND curr_abrev = 'USD' LIMIT 1),
                sm.material_cost
            ) as sale_price,
            COALESCE(
                (SELECT curr_abrev FROM {$prefix}prices WHERE stock_id = sm.stock_id AND curr_abrev = 'ILS' LIMIT 1),
                (SELECT curr_abrev FROM {$prefix}prices WHERE stock_id = sm.stock_id AND curr_abrev = 'USD' LIMIT 1),
                'ILS'
            ) as currency
        FROM 
            {$prefix}stock_master sm
        LEFT JOIN 
            {$prefix}stock_moves stm ON sm.stock_id = stm.stock_id
        WHERE 
            sm.inactive = 0
        GROUP BY 
            sm.stock_id, sm.description
        ORDER BY 
            sm.description ASC";

    $stmt_all_items = $conn->prepare($sql_all_items);
    $stmt_all_items->bind_param("s", $location);
    $stmt_all_items->execute();
    $result_all_items = $stmt_all_items->get_result();
    
    if ($result_all_items) {
        while ($row_item = $result_all_items->fetch_assoc()) {
            $all_stock_items[] = [
                'stock_id' => $row_item['stock_id'],
                'description' => $row_item['description'],
                'unit_price' => floatval($row_item['sale_price']),
                'currency' => $row_item['currency'],
                'on_hand_qty' => intval($row_item['on_hand_qty'])
            ];
        }
    }
    $stmt_all_items->close();
    return $all_stock_items;
}

// الحصول على الأصناف الأولية
$all_stock_items = getStockItems($conn, $prefix, $default_location);

// التعامل مع طلب AJAX للأصناف المحدثة
if (isset($_GET['get_updated_stock'])) {
    header('Content-Type: application/json');
    echo json_encode(getStockItems($conn, $prefix, $default_location));
    exit;
}

// دالة لبدء فاتورة جديدة
function startNewInvoice() {
    unset($_SESSION['last_invoice']);
    unset($_SESSION['last_payment']);
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    startNewInvoice();
}

// التعامل مع تحديث السلة
if (isset($_POST['update_cart'])) {
    foreach ($_SESSION['cart'] as &$item) {
        $id = $item['stock_id'];
        if (isset($_POST['unit_price'][$id]) && isset($_POST['discount'][$id])) {
            $price = floatval($_POST['unit_price'][$id]);
            $disc = floatval($_POST['discount'][$id]);
            if ($price >= 0) $item['unit_price'] = $price;
            if ($disc >= 0 && $disc <= 100) {
                $item['discount'] = $disc;
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// التعامل مع إضافة الأصناف
if (isset($_POST['add_item'])) {
    $item_code = trim($_POST['item_code']);
    $qty = max(1, (int)$_POST['qty']);
    $cart = &$_SESSION['cart'];
    $found_index = -1;
    foreach ($cart as $index => $item) {
        if ($item['stock_id'] == $item_code) {
            $found_index = $index;
            break;
        }
    }
    if ($found_index >= 0) {
        $cart[$found_index]['qty'] += $qty;
    } else {
        $sql = "SELECT stock_id, description, material_cost FROM {$prefix}stock_master WHERE stock_id = ? OR long_description LIKE ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $like_code = "%{$item_code}%";
        $stmt->bind_param("ss", $item_code, $like_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $sql_price = "SELECT price, curr_abrev FROM {$prefix}prices WHERE stock_id = ? AND (curr_abrev = 'ILS' OR curr_abrev = 'USD') ORDER BY curr_abrev = 'ILS' DESC LIMIT 1";
            $stmt_price = $conn->prepare($sql_price);
            $stmt_price->bind_param("s", $row['stock_id']);
            $stmt_price->execute();
            $res_price = $stmt_price->get_result();
            
            if ($price_row = $res_price->fetch_assoc()) {
                $unit_price = $price_row['price'];
                $currency = $price_row['curr_abrev'];
            } else {
                $unit_price = $row['material_cost'];
                $currency = 'ILS';
            }
            $stmt_price->close();
            
            $sql_qty = "SELECT COALESCE(SUM(qty), 0) as qty FROM {$prefix}stock_moves WHERE stock_id = ? AND loc_code = ?";
            $stmt_qty = $conn->prepare($sql_qty);
            $stmt_qty->bind_param("ss", $row['stock_id'], $default_location);
            $stmt_qty->execute();
            $res_qty = $stmt_qty->get_result();
            $available_qty = $res_qty->fetch_assoc()['qty'] ?? 0;
            $stmt_qty->close();
            
            $cart[] = [
                'stock_id' => $row['stock_id'],
                'description' => $row['description'],
                'unit_price' => floatval($unit_price),
                'currency' => $currency,
                'standard_cost' => floatval($row['material_cost']),
                'qty' => $qty,
                'discount' => 0,
                'available_qty' => $available_qty
            ];
        } else {
            $error = "الصنف غير موجود";
        }
        $stmt->close();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// التعامل مع حذف الصنف
if (isset($_GET['remove'])) {
    $remove_id = $_GET['remove'];
    foreach ($_SESSION['cart'] as $k => $item) {
        if ($item['stock_id'] == $remove_id) {
            unset($_SESSION['cart'][$k]);
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            break;
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// التعامل مع إلغاء الفاتورة
if (isset($_GET['cancel_invoice'])) {
    $_SESSION['cart'] = [];
    startNewInvoice();
    $success = "تم إلغاء الفاتورة بنجاح";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ==============================================
// دالة لحساب المبالغ بشكل صحيح
// ==============================================
function calculateInvoiceTotals($cart_items, $tax_rate, $tax_included_in_price) {
    $sub_total = 0;
    $tax_amount = 0;
    $total_with_tax = 0;
    $net_sales = 0;
    
    foreach ($cart_items as $item) {
        $item_discount_decimal = $item['discount'] / 100;
        $subtotal = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
        $sub_total += $subtotal;
    }
    
    if ($tax_rate > 0) {
        if ($tax_included_in_price == 1) {
            // الضريبة مضمنة في السعر
            $net_sales = $sub_total / (1 + $tax_rate);
            $tax_amount = $net_sales * $tax_rate;
            $total_with_tax = $sub_total;
        } else {
            // الضريبة مضافة على السعر
            $net_sales = $sub_total;
            $tax_amount = $sub_total * $tax_rate;
            $total_with_tax = $sub_total + $tax_amount;
        }
    } else {
        $net_sales = $sub_total;
        $total_with_tax = $sub_total;
    }
    
    return [
        'sub_total' => $sub_total,
        'tax_amount' => $tax_amount,
        'total_with_tax' => $total_with_tax,
        'net_sales' => $net_sales
    ];
}

// دالة لحساب ضريبة كل صنف بشكل منفصل
function calculateItemTax($item_price, $item_qty, $item_discount, $tax_rate, $tax_included) {
    $item_discount_decimal = $item_discount / 100;
    $subtotal = $item_price * $item_qty * (1 - $item_discount_decimal);
    
    if ($tax_rate > 0) {
        if ($tax_included == 1) {
            // الضريبة مضمنة في السعر
            $net_sales = $subtotal / (1 + $tax_rate);
            $item_tax = $net_sales * $tax_rate;
        } else {
            // الضريبة مضافة على السعر
            $item_tax = $subtotal * $tax_rate;
        }
    } else {
        $item_tax = 0;
    }
    
    return $item_tax;
}

// حساب المبالغ الحالية للسلة
$totals = calculateInvoiceTotals($_SESSION['cart'], $current_tax_rate, $tax_included_in_price);
$sub_total = $totals['sub_total'];
$tax_amount = $totals['tax_amount'];
$total_with_tax = $totals['total_with_tax'];
$net_sales = $totals['net_sales'];

// ==============================================
// التعامل مع حفظ الفاتورة - النسخة المصححة النهائية
// ==============================================
if (isset($_POST['save_invoice']) && count($_SESSION['cart']) > 0) {
    startNewInvoice();
    
    $tran_date = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    try {
        // بدء المعاملة
        $conn->begin_transaction();
        
        // 1. الحصول على أرقام جديدة
        $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = " . ST_SALESINVOICE);
        $row = $result->fetch_assoc();
        $trans_no = (int)$row['max_no'] + 1;
        
        // 2. حساب المجاميع باستخدام الدالة الصحيحة
        $invoice_totals = calculateInvoiceTotals($_SESSION['cart'], $current_tax_rate, $tax_included_in_price);
        
        $sub_total_for_invoice = $invoice_totals['sub_total'];
        $tax_amount_for_invoice = $invoice_totals['tax_amount'];
        $total_for_invoice = $invoice_totals['total_with_tax'];
        $net_sales_amount = $invoice_totals['net_sales'];
        
        // 3. حساب تكلفة البضائع المباعة
        $total_cost = 0;
        foreach ($_SESSION['cart'] as $item) {
            $total_cost += $item['standard_cost'] * $item['qty'];
        }
        
        $reference = "SI-" . $trans_no;
        $type = ST_SALESINVOICE;
        
        // 4. إدخال في debtor_trans - الطريقة الأكيدة مع order_
        // التحقق من هيكل الجدول
        $sql_check_debtor = "DESCRIBE {$prefix}debtor_trans";
        $result_check_debtor = $conn->query($sql_check_debtor);
        $debtor_trans_fields = [];
        while ($field = $result_check_debtor->fetch_assoc()) {
            $debtor_trans_fields[] = $field['Field'];
        }
        
        // بيانات الإدراج الأساسية مع order_ = 1
        $debtor_data = [
            'trans_no' => $trans_no,
            'type' => $type,
            'version' => 1,
            'debtor_no' => $walkin_debtor_no,
            'branch_code' => $branch_code,
            'tran_date' => $tran_date,
            'due_date' => $tran_date,
            'reference' => $reference,
            'tpe' => $default_sales_type,
            'ov_amount' => $total_for_invoice,
            'tax_included' => $tax_included_in_price,
            'dimension_id' => $default_cost_center,
            'ov_discount' => 0,
            'alloc' => 0,
            'rate' => 1,
            'ship_via' => 1,
            'payment_terms' => 4,
            'ov_gst' => 0,
            'ov_freight' => 0,
            'ov_freight_tax' => 0
        ];
        
        // إضافة order_ إذا كان الحقل موجوداً في الجدول
        if (in_array('order_', $debtor_trans_fields)) {
            $debtor_data['order_'] = 1;
        }
        
        // إضافة حقول إضافية إذا كانت موجودة
        $optional_fields = ['prep_amount', 'dimension2_id'];
        foreach ($optional_fields as $field) {
            if (in_array($field, $debtor_trans_fields)) {
                $debtor_data[$field] = 0;
            }
        }
        
        // تصفية الحقول الموجودة فقط
        $insert_fields = [];
        $insert_values = [];
        $insert_placeholders = [];
        $param_types = "";
        
        foreach ($debtor_data as $field => $value) {
            if (in_array($field, $debtor_trans_fields)) {
                $insert_fields[] = $field;
                $insert_placeholders[] = "?";
                $insert_values[] = $value;
                
                // تحديد نوع المعلمة
                if (is_int($value)) {
                    $param_types .= "i";
                } elseif (is_float($value)) {
                    $param_types .= "d";
                } else {
                    $param_types .= "s";
                }
            }
        }
        
        // بناء وتنفيذ الاستعلام
        $sql_debtor = "INSERT INTO {$prefix}debtor_trans 
            (" . implode(', ', $insert_fields) . ") 
            VALUES (" . implode(', ', $insert_placeholders) . ")";
        
        $stmt_debtor = $conn->prepare($sql_debtor);
        if (!$stmt_debtor) {
            throw new Exception("خطأ في تحضير استعلام debtor_trans: " . $conn->error);
        }
        
        $stmt_debtor->bind_param($param_types, ...$insert_values);
        
        if (!$stmt_debtor->execute()) {
            throw new Exception("فشل في حفظ الفاتورة (debtor_trans): " . $stmt_debtor->error);
        }
        $stmt_debtor->close();
        
        // 5. إدخال تفاصيل الفاتورة مع حساب ضريبة كل صنف
        foreach ($_SESSION['cart'] as $item) {
            $discount_percent = $item['discount'] / 100;
            
            // حساب ضريبة الصنف الفردية
            $item_tax_amount = calculateItemTax(
                $item['unit_price'], 
                $item['qty'], 
                $item['discount'], 
                $current_tax_rate, 
                $tax_included_in_price
            );
            
            // التحقق من هيكل جدول debtor_trans_details
            $sql_check_details = "DESCRIBE {$prefix}debtor_trans_details";
            $result_check_details = $conn->query($sql_check_details);
            $details_fields = [];
            while ($field = $result_check_details->fetch_assoc()) {
                $details_fields[] = $field['Field'];
            }
            
            // تحضير بيانات التفاصيل
            $details_data = [
                'debtor_trans_no' => $trans_no,
                'debtor_trans_type' => $type,
                'stock_id' => $item['stock_id'],
                'description' => $item['description'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['qty'],
                'discount_percent' => $discount_percent,
                'standard_cost' => $item['standard_cost'],
                'qty_done' => $item['qty'],
                'unit_tax' => $item_tax_amount // إضافة قيمة unit_tax
            ];
            
            // إضافة tax_type_id إذا كان الحقل موجوداً
            if (in_array('tax_type_id', $details_fields)) {
                $details_data['tax_type_id'] = $default_tax_type;
            }
            
            // تصفية الحقول الموجودة فقط
            $details_insert_fields = [];
            $details_insert_values = [];
            $details_insert_placeholders = [];
            $details_param_types = "";
            
            foreach ($details_data as $field => $value) {
                if (in_array($field, $details_fields)) {
                    $details_insert_fields[] = $field;
                    $details_insert_placeholders[] = "?";
                    $details_insert_values[] = $value;
                    
                    // تحديد نوع المعلمة
                    if (is_int($value)) {
                        $details_param_types .= "i";
                    } elseif (is_float($value)) {
                        $details_param_types .= "d";
                    } else {
                        $details_param_types .= "s";
                    }
                }
            }
            
            // بناء وتنفيذ استعلام التفاصيل
            $sql_details = "INSERT INTO {$prefix}debtor_trans_details
                (" . implode(', ', $details_insert_fields) . ") 
                VALUES (" . implode(', ', $details_insert_placeholders) . ")";
            
            $stmt_details = $conn->prepare($sql_details);
            if (!$stmt_details) {
                throw new Exception("خطأ في تحضير استعلام التفاصيل: " . $conn->error);
            }
            
            $stmt_details->bind_param($details_param_types, ...$details_insert_values);
            
            if (!$stmt_details->execute()) {
                throw new Exception("فشل في حفظ تفاصيل الفاتورة: " . $stmt_details->error);
            }
            $stmt_details->close();
            
            // 6. حركة المخزون
            $qty = -abs($item['qty']);
            
            $sql_stock = "INSERT INTO {$prefix}stock_moves
                (trans_no, type, stock_id, loc_code, tran_date, price, reference, qty, standard_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt_stock = $conn->prepare($sql_stock);
            if (!$stmt_stock) {
                throw new Exception("خطأ في تحضير استعلام المخزون: " . $conn->error);
            }
            
            $stmt_stock->bind_param("iisssdsdd", 
                $trans_no, $type, $item['stock_id'], $default_location,
                $tran_date, $item['unit_price'], $reference, $qty,
                $item['standard_cost']
            );
            
            if (!$stmt_stock->execute()) {
                throw new Exception("فشل في تحديث المخزون: " . $stmt_stock->error);
            }
            $stmt_stock->close();
        }
        
        // 7. إدخال تفاصيل الضريبة إذا وجدت مع الحقول الجديدة
        if ($tax_amount_for_invoice > 0 && $selected_tax_data) {
            // التحقق من هيكل جدول trans_tax_details
            $sql_check_tax_details = "DESCRIBE {$prefix}trans_tax_details";
            $result_check_tax_details = $conn->query($sql_check_tax_details);
            $tax_details_fields = [];
            while ($field = $result_check_tax_details->fetch_assoc()) {
                $tax_details_fields[] = $field['Field'];
            }
            
            // إعداد البيانات الأساسية
            $tax_details_data = [
                'trans_type' => $type,
                'trans_no' => $trans_no,
                'tran_date' => $tran_date,
                'tax_type_id' => $default_tax_type,
                'rate' => $selected_tax_data['rate'],
                'amount' => $tax_amount_for_invoice,
                'net_amount' => $net_sales_amount, // قيمة net_amount (قيمة المبيعات قبل الضريبة)
                'included_in_price' => $tax_included_in_price // 1 إذا كانت الضريبة ضمنية، 0 إذا كانت مضافة
            ];
            
            // إضافة الحقول الإضافية إذا كانت موجودة
            $additional_tax_fields = ['ex_rate', 'tax_type_name'];
            foreach ($additional_tax_fields as $field) {
                if (in_array($field, $tax_details_fields)) {
                    $tax_details_data[$field] = ($field == 'ex_rate') ? 1.0 : $selected_tax_data['name'];
                }
            }
            
            // تصفية الحقول الموجودة فقط
            $tax_insert_fields = [];
            $tax_insert_values = [];
            $tax_insert_placeholders = [];
            $tax_param_types = "";
            
            foreach ($tax_details_data as $field => $value) {
                if (in_array($field, $tax_details_fields)) {
                    $tax_insert_fields[] = $field;
                    $tax_insert_placeholders[] = "?";
                    $tax_insert_values[] = $value;
                    
                    // تحديد نوع المعلمة
                    if (is_int($value)) {
                        $tax_param_types .= "i";
                    } elseif (is_float($value)) {
                        $tax_param_types .= "d";
                    } else {
                        $tax_param_types .= "s";
                    }
                }
            }
            
            // بناء وتنفيذ استعلام تفاصيل الضريبة
            $sql_tax = "INSERT INTO {$prefix}trans_tax_details 
                (" . implode(', ', $tax_insert_fields) . ") 
                VALUES (" . implode(', ', $tax_insert_placeholders) . ")";
            
            $stmt_tax = $conn->prepare($sql_tax);
            if ($stmt_tax) {
                $stmt_tax->bind_param($tax_param_types, ...$tax_insert_values);
                
                if (!$stmt_tax->execute()) {
                    error_log("ملاحظة: فشل في إدخال تفاصيل الضريبة: " . $stmt_tax->error);
                }
                $stmt_tax->close();
            } else {
                error_log("ملاحظة: فشل في تحضير استعلام تفاصيل الضريبة");
            }
        }
        
        // ==============================================
        // إضافة القيود المحاسبية - النسخة المحسنة
        // ==============================================
        
        // 1. الحصول على الحسابات من جداول الأصناف والموردين
        // محاولة الحصول على حسابات افتراضية من sales_types أو tax_types
        $default_sales_account = 41010001; // حساب افتراضي للمبيعات
        $default_receivable_account = 11011001; // حساب مدينون افتراضي
        $default_inventory_account = 1400; // حساب المخزون افتراضي
        $default_cogs_account = 5000; // حساب تكلفة البضاعة المباعة افتراضي
        
        // الحصول على حساب المبيعات من sales_types إذا وجد
        if ($selected_sales_type_data) {
            // محاولة الحصول على حساب المبيعات من sales_types
            $sql_sales_account = "SHOW COLUMNS FROM {$prefix}sales_types LIKE 'sales_account' OR LIKE 'account' OR LIKE 'gl_account'";
            $result_sales_account = $conn->query($sql_sales_account);
            if ($result_sales_account->num_rows > 0) {
                // إذا وجد الحقل، جرب الحصول على القيمة
                $sql_get_account = "SELECT COALESCE(sales_account, account, gl_account) as sales_account 
                                   FROM {$prefix}sales_types WHERE id = ?";
                $stmt_account = $conn->prepare($sql_get_account);
                $stmt_account->bind_param("i", $default_sales_type);
                $stmt_account->execute();
                $result_account = $stmt_account->get_result();
                if ($account_row = $result_account->fetch_assoc()) {
                    if (!empty($account_row['sales_account'])) {
                        $default_sales_account = $account_row['sales_account'];
                    }
                }
                $stmt_account->close();
            }
        }
        
        // 2. إدراج القيود المحاسبية لكل صنف في الفاتورة
        foreach ($_SESSION['cart'] as $item) {
            // حساب المجموع للصنف الواحد
            $item_discount_decimal = $item['discount'] / 100;
            $item_subtotal = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
            
            // حساب ضريبة الصنف
            $item_tax_amount = calculateItemTax(
                $item['unit_price'], 
                $item['qty'], 
                $item['discount'], 
                $current_tax_rate, 
                $tax_included_in_price
            );
            
            // حساب المبيعات الصافية للصنف
            if ($tax_included_in_price == 1 && $current_tax_rate > 0) {
                $item_net_sales = $item_subtotal / (1 + $current_tax_rate);
            } else {
                $item_net_sales = $item_subtotal;
            }
            
            // محاولة الحصول على حسابات الصنف من stock_master
            $item_sales_account = $default_sales_account;
            $item_cogs_account = $default_cogs_account;
            $item_inventory_account = $default_inventory_account;
            
            // التحقق من وجود الحقول في stock_master
            $sql_check_stock_fields = "SHOW COLUMNS FROM {$prefix}stock_master";
            $result_check_stock_fields = $conn->query($sql_check_stock_fields);
            $stock_master_fields = [];
            while ($field = $result_check_stock_fields->fetch_assoc()) {
                $stock_master_fields[] = $field['Field'];
            }
            
            $account_fields_to_check = [
                'sales_account' => 'sales_account',
                'account' => 'account',
                'gl_sales_account' => 'gl_sales_account',
                'cogs_account' => 'cogs_account',
                'gl_cogs_account' => 'gl_cogs_account',
                'inventory_account' => 'inventory_account',
                'gl_inventory_account' => 'gl_inventory_account'
            ];
            
            $found_sales_account = false;
            $found_cogs_account = false;
            $found_inventory_account = false;
            
            // بناء استعلام للحصول على حسابات الصنف
            $select_fields = [];
            foreach ($account_fields_to_check as $key => $field_name) {
                if (in_array($field_name, $stock_master_fields)) {
                    $select_fields[] = $field_name;
                }
            }
            
            if (!empty($select_fields)) {
                $sql_item_accounts = "SELECT " . implode(', ', $select_fields) . " 
                                      FROM {$prefix}stock_master 
                                      WHERE stock_id = ? LIMIT 1";
                
                $stmt_item_accounts = $conn->prepare($sql_item_accounts);
                $stmt_item_accounts->bind_param("s", $item['stock_id']);
                $stmt_item_accounts->execute();
                $result_item_accounts = $stmt_item_accounts->get_result();
                
                if ($item_accounts = $result_item_accounts->fetch_assoc()) {
                    // البحث عن حساب المبيعات
                    foreach (['sales_account', 'account', 'gl_sales_account'] as $field) {
                        if (isset($item_accounts[$field]) && !empty($item_accounts[$field])) {
                            $item_sales_account = $item_accounts[$field];
                            $found_sales_account = true;
                            break;
                        }
                    }
                    
                    // البحث عن حساب تكلفة البضاعة المباعة
                    foreach (['cogs_account', 'gl_cogs_account'] as $field) {
                        if (isset($item_accounts[$field]) && !empty($item_accounts[$field])) {
                            $item_cogs_account = $item_accounts[$field];
                            $found_cogs_account = true;
                            break;
                        }
                    }
                    
                    // البحث عن حساب المخزون
                    foreach (['inventory_account', 'gl_inventory_account'] as $field) {
                        if (isset($item_accounts[$field]) && !empty($item_accounts[$field])) {
                            $item_inventory_account = $item_accounts[$field];
                            $found_inventory_account = true;
                            break;
                        }
                    }
                }
                $stmt_item_accounts->close();
            }
            
            // تحويل الحسابات إلى أرقام إذا كانت نصية
            if (!is_numeric($item_sales_account)) {
                $item_sales_account = $default_sales_account;
            }
            if (!is_numeric($item_cogs_account)) {
                $item_cogs_account = $default_cogs_account;
            }
            if (!is_numeric($item_inventory_account)) {
                $item_inventory_account = $default_inventory_account;
            }
            
            $item_sales_account = (int)$item_sales_account;
            $item_cogs_account = (int)$item_cogs_account;
            $item_inventory_account = (int)$item_inventory_account;
            
            // حساب تكلفة الصنف
            $item_cost = $item['standard_cost'] * $item['qty'];
            
            // 3. قيد المبيعات (مدين)
            $gl_counter = 0;
            
            // قيد المبيعات - مبيعات الصنف
            // التحقق من هيكل جدول gl_trans
            $sql_check_gl = "DESCRIBE {$prefix}gl_trans";
            $result_check_gl = $conn->query($sql_check_gl);
            $gl_trans_fields = [];
            while ($field = $result_check_gl->fetch_assoc()) {
                $gl_trans_fields[] = $field['Field'];
            }
            
            // تحضير بيانات قيد المبيعات
            $gl_sales_data = [
                'type' => $type,
                'type_no' => $trans_no,
                'tran_date' => $tran_date,
                'account' => $item_sales_account,
                'memo_' => "مبيعات - " . $item['description'] . " (" . $item['stock_id'] . ")",
                'amount' => -$item_net_sales, // سالب لأن المبيعات دائنة (إيراد)
                'dimension_id' => $default_cost_center
            ];
            
            // إضافة حقول إضافية إذا كانت موجودة
            if (in_array('person_type_id', $gl_trans_fields)) {
                $gl_sales_data['person_type_id'] = 2; // 2 للزبائن
            }
            if (in_array('person_id', $gl_trans_fields)) {
                $gl_sales_data['person_id'] = $walkin_debtor_no;
            }
            if (in_array('dimension2_id', $gl_trans_fields)) {
                $gl_sales_data['dimension2_id'] = 0;
            }
            
            // تصفية الحقول الموجودة فقط
            $gl_sales_fields = [];
            $gl_sales_values = [];
            $gl_sales_placeholders = [];
            $gl_sales_param_types = "";
            
            foreach ($gl_sales_data as $field => $value) {
                if (in_array($field, $gl_trans_fields)) {
                    $gl_sales_fields[] = $field;
                    $gl_sales_placeholders[] = "?";
                    $gl_sales_values[] = $value;
                    
                    // تحديد نوع المعلمة
                    if (is_int($value)) {
                        $gl_sales_param_types .= "i";
                    } elseif (is_float($value)) {
                        $gl_sales_param_types .= "d";
                    } else {
                        $gl_sales_param_types .= "s";
                    }
                }
            }
            
            // بناء وتنفيذ استعلام قيد المبيعات
            $sql_gl_sales = "INSERT INTO {$prefix}gl_trans 
                (" . implode(', ', $gl_sales_fields) . ") 
                VALUES (" . implode(', ', $gl_sales_placeholders) . ")";
            
            $stmt_gl_sales = $conn->prepare($sql_gl_sales);
            if (!$stmt_gl_sales) {
                throw new Exception("خطأ في تحضير استعلام قيد المبيعات: " . $conn->error);
            }
            
            $stmt_gl_sales->bind_param($gl_sales_param_types, ...$gl_sales_values);
            
            if (!$stmt_gl_sales->execute()) {
                throw new Exception("فشل في تسجيل قيد المبيعات للصنف {$item['stock_id']}: " . $stmt_gl_sales->error);
            }
            $stmt_gl_sales->close();
            $gl_counter++;
            
            // 4. قيد تكلفة البضاعة المباعة
            $gl_cogs_data = [
                'type' => $type,
                'type_no' => $trans_no,
                'tran_date' => $tran_date,
                'account' => $item_cogs_account,
                'memo_' => "تكلفة بضاعة مباعة - " . $item['description'] . " (" . $item['stock_id'] . ")",
                'amount' => $item_cost, // موجب لأن المصروف مدين (تكلفة)
                'dimension_id' => $default_cost_center
            ];
            
            // إضافة حقول إضافية
            if (in_array('person_type_id', $gl_trans_fields)) {
                $gl_cogs_data['person_type_id'] = 0;
            }
            if (in_array('person_id', $gl_trans_fields)) {
                $gl_cogs_data['person_id'] = 0;
            }
            if (in_array('dimension2_id', $gl_trans_fields)) {
                $gl_cogs_data['dimension2_id'] = 0;
            }
            
            // تصفية الحقول
            $gl_cogs_fields = [];
            $gl_cogs_values = [];
            $gl_cogs_placeholders = [];
            $gl_cogs_param_types = "";
            
            foreach ($gl_cogs_data as $field => $value) {
                if (in_array($field, $gl_trans_fields)) {
                    $gl_cogs_fields[] = $field;
                    $gl_cogs_placeholders[] = "?";
                    $gl_cogs_values[] = $value;
                    
                    if (is_int($value)) {
                        $gl_cogs_param_types .= "i";
                    } elseif (is_float($value)) {
                        $gl_cogs_param_types .= "d";
                    } else {
                        $gl_cogs_param_types .= "s";
                    }
                }
            }
            
            $sql_gl_cogs = "INSERT INTO {$prefix}gl_trans 
                (" . implode(', ', $gl_cogs_fields) . ") 
                VALUES (" . implode(', ', $gl_cogs_placeholders) . ")";
            
            $stmt_gl_cogs = $conn->prepare($sql_gl_cogs);
            if (!$stmt_gl_cogs) {
                throw new Exception("خطأ في تحضير استعلام تكلفة البضاعة المباعة: " . $conn->error);
            }
            
            $stmt_gl_cogs->bind_param($gl_cogs_param_types, ...$gl_cogs_values);
            
            if (!$stmt_gl_cogs->execute()) {
                throw new Exception("فشل في تسجيل قيد تكلفة البضاعة المباعة للصنف {$item['stock_id']}: " . $stmt_gl_cogs->error);
            }
            $stmt_gl_cogs->close();
            $gl_counter++;
            
            // 5. قيد المخزون
            $gl_inventory_data = [
                'type' => $type,
                'type_no' => $trans_no,
                'tran_date' => $tran_date,
                'account' => $item_inventory_account,
                'memo_' => "تخفيض مخزون - " . $item['description'] . " (" . $item['stock_id'] . ")",
                'amount' => -$item_cost, // سالب لأن المخزون دائن (نقص في الأصول)
                'dimension_id' => $default_cost_center
            ];
            
            // إضافة حقول إضافية
            if (in_array('person_type_id', $gl_trans_fields)) {
                $gl_inventory_data['person_type_id'] = 0;
            }
            if (in_array('person_id', $gl_trans_fields)) {
                $gl_inventory_data['person_id'] = 0;
            }
            if (in_array('dimension2_id', $gl_trans_fields)) {
                $gl_inventory_data['dimension2_id'] = 0;
            }
            
            // تصفية الحقول
            $gl_inventory_fields = [];
            $gl_inventory_values = [];
            $gl_inventory_placeholders = [];
            $gl_inventory_param_types = "";
            
            foreach ($gl_inventory_data as $field => $value) {
                if (in_array($field, $gl_trans_fields)) {
                    $gl_inventory_fields[] = $field;
                    $gl_inventory_placeholders[] = "?";
                    $gl_inventory_values[] = $value;
                    
                    if (is_int($value)) {
                        $gl_inventory_param_types .= "i";
                    } elseif (is_float($value)) {
                        $gl_inventory_param_types .= "d";
                    } else {
                        $gl_inventory_param_types .= "s";
                    }
                }
            }
            
            $sql_gl_inventory = "INSERT INTO {$prefix}gl_trans 
                (" . implode(', ', $gl_inventory_fields) . ") 
                VALUES (" . implode(', ', $gl_inventory_placeholders) . ")";
            
            $stmt_gl_inventory = $conn->prepare($sql_gl_inventory);
            if (!$stmt_gl_inventory) {
                throw new Exception("خطأ في تحضير استعلام المخزون: " . $conn->error);
            }
            
            $stmt_gl_inventory->bind_param($gl_inventory_param_types, ...$gl_inventory_values);
            
            if (!$stmt_gl_inventory->execute()) {
                throw new Exception("فشل في تسجيل قيد المخزون للصنف {$item['stock_id']}: " . $stmt_gl_inventory->error);
            }
            $stmt_gl_inventory->close();
            $gl_counter++;
            
            // 6. قيد الضريبة إذا كانت موجودة
            if ($item_tax_amount > 0) {
                // الحصول على حساب الضريبة من tax_types
                $tax_gl_account = $selected_tax_data['sales_gl_code'] ?? '21040001';
                
                $gl_tax_data = [
                    'type' => $type,
                    'type_no' => $trans_no,
                    'tran_date' => $tran_date,
                    'account' => $tax_gl_account,
                    'memo_' => "ضريبة مبيعات - " . $item['description'] . " (" . $item['stock_id'] . ")",
                    'amount' => -$item_tax_amount, // سالب لأن ضريبة المبيعات دائنة (إلتزام)
                    'dimension_id' => $default_cost_center
                ];
                
                // إضافة حقول إضافية
                if (in_array('person_type_id', $gl_trans_fields)) {
                    $gl_tax_data['person_type_id'] = 0;
                }
                if (in_array('person_id', $gl_trans_fields)) {
                    $gl_tax_data['person_id'] = 0;
                }
                if (in_array('dimension2_id', $gl_trans_fields)) {
                    $gl_tax_data['dimension2_id'] = 0;
                }
                
                // تصفية الحقول
                $gl_tax_fields = [];
                $gl_tax_values = [];
                $gl_tax_placeholders = [];
                $gl_tax_param_types = "";
                
                foreach ($gl_tax_data as $field => $value) {
                    if (in_array($field, $gl_trans_fields)) {
                        $gl_tax_fields[] = $field;
                        $gl_tax_placeholders[] = "?";
                        $gl_tax_values[] = $value;
                        
                        if (is_int($value)) {
                            $gl_tax_param_types .= "i";
                        } elseif (is_float($value)) {
                            $gl_tax_param_types .= "d";
                        } else {
                            $gl_tax_param_types .= "s";
                        }
                    }
                }
                
                $sql_gl_tax = "INSERT INTO {$prefix}gl_trans 
                    (" . implode(', ', $gl_tax_fields) . ") 
                    VALUES (" . implode(', ', $gl_tax_placeholders) . ")";
                
                $stmt_gl_tax = $conn->prepare($sql_gl_tax);
                if ($stmt_gl_tax) {
                    $stmt_gl_tax->bind_param($gl_tax_param_types, ...$gl_tax_values);
                    
                    if (!$stmt_gl_tax->execute()) {
                        error_log("ملاحظة: فشل في تسجيل قيد الضريبة للصنف {$item['stock_id']}: " . $stmt_gl_tax->error);
                    }
                    $stmt_gl_tax->close();
                }
            }
        }
        
        // 7. قيد المدينون (المجموع الكلي للفاتورة) - مرة واحدة فقط
        $gl_receivable_data = [
            'type' => $type,
            'type_no' => $trans_no,
            'tran_date' => $tran_date,
            'account' => $default_receivable_account,
            'memo_' => "فاتورة مبيعات رقم " . $trans_no,
            'amount' => $total_for_invoice, // موجب لأن الأصول تزيد (مدينون يزيد)
            'dimension_id' => $default_cost_center
        ];
        
        // إضافة حقول إضافية
        if (in_array('person_type_id', $gl_trans_fields)) {
            $gl_receivable_data['person_type_id'] = 2; // 2 للزبائن
        }
        if (in_array('person_id', $gl_trans_fields)) {
            $gl_receivable_data['person_id'] = $walkin_debtor_no;
        }
        if (in_array('dimension2_id', $gl_trans_fields)) {
            $gl_receivable_data['dimension2_id'] = 0;
        }
        
        // تصفية الحقول
        $gl_receivable_fields = [];
        $gl_receivable_values = [];
        $gl_receivable_placeholders = [];
        $gl_receivable_param_types = "";
        
        foreach ($gl_receivable_data as $field => $value) {
            if (in_array($field, $gl_trans_fields)) {
                $gl_receivable_fields[] = $field;
                $gl_receivable_placeholders[] = "?";
                $gl_receivable_values[] = $value;
                
                if (is_int($value)) {
                    $gl_receivable_param_types .= "i";
                } elseif (is_float($value)) {
                    $gl_receivable_param_types .= "d";
                } else {
                    $gl_receivable_param_types .= "s";
                }
            }
        }
        
        $sql_gl_receivable = "INSERT INTO {$prefix}gl_trans 
            (" . implode(', ', $gl_receivable_fields) . ") 
            VALUES (" . implode(', ', $gl_receivable_placeholders) . ")";
        
        $stmt_gl_receivable = $conn->prepare($sql_gl_receivable);
        if (!$stmt_gl_receivable) {
            throw new Exception("خطأ في تحضير استعلام المدينون: " . $conn->error);
        }
        
        $stmt_gl_receivable->bind_param($gl_receivable_param_types, ...$gl_receivable_values);
        
        if (!$stmt_gl_receivable->execute()) {
            throw new Exception("فشل في تسجيل قيد المدينون: " . $stmt_gl_receivable->error);
        }
        $stmt_gl_receivable->close();

        // 8. التحقق من توازن القيود
        $sql_check_balance = "SELECT SUM(amount) as total FROM {$prefix}gl_trans WHERE type = ? AND type_no = ?";
        $stmt_check = $conn->prepare($sql_check_balance);
        $stmt_check->bind_param("ii", $type, $trans_no);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($balance_row = $result_check->fetch_assoc()) {
            $balance = $balance_row['total'] ?? 0;
            
            // يجب أن يكون المجموع صفر (القيد المزدوج)
            if (abs($balance) > 0.01) {
                error_log("تحذير: القيود غير متوازنة للفاتورة $trans_no. الفرق: " . $balance);
                
                // تصحيح الخلل بإضافة قيد تصحيح تلقائي
                $correction_amount = -$balance;
                $correction_account = 999999; // حساب تصحيح مؤقت
                
                $sql_correction = "INSERT INTO {$prefix}gl_trans 
                    (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0)";
                
                $stmt_correction = $conn->prepare($sql_correction);
                $memo_correction = "تصحيح تلقائي لفاتورة " . $trans_no;
                
                $stmt_correction->bind_param("iissdi", 
                    $type, $trans_no, $tran_date, $correction_account,
                    $memo_correction, $correction_amount, $default_cost_center
                );
                
                $stmt_correction->execute();
                $stmt_correction->close();
            }
        }
        $stmt_check->close();

        // إتمام المعاملة
        $conn->commit();
        
        // الحصول على اسم الموقع المحدد للعرض
        $selected_location_name = "غير محدد";
        $sql_location_name = "SELECT location_name FROM {$prefix}locations WHERE loc_code = ?";
        $stmt_location = $conn->prepare($sql_location_name);
        $stmt_location->bind_param("s", $default_location);
        $stmt_location->execute();
        $result_location = $stmt_location->get_result();
        if ($row_location = $result_location->fetch_assoc()) {
            $selected_location_name = $row_location['location_name'];
        }
        $stmt_location->close();
        
        // الحصول على اسم مركز التكلفة المحدد للعرض
        $selected_cost_center_name = "غير محدد";
        $sql_cost_center_name = "SELECT name FROM {$prefix}dimensions WHERE id = ?";
        $stmt_cost_center = $conn->prepare($sql_cost_center_name);
        $stmt_cost_center->bind_param("i", $default_cost_center);
        $stmt_cost_center->execute();
        $result_cost_center = $stmt_cost_center->get_result();
        if ($row_cost_center = $result_cost_center->fetch_assoc()) {
            $selected_cost_center_name = $row_cost_center['name'];
        }
        $stmt_cost_center->close();
        
        // تخزين الفاتورة في الجلسة قبل مسح السلة
        $_SESSION['last_invoice'] = [
            'trans_no' => $trans_no,
            'sub_total' => $sub_total_for_invoice,
            'tax_amount' => $tax_amount_for_invoice,
            'total' => $total_for_invoice,
            'net_sales' => $net_sales_amount,
            'items' => $_SESSION['cart'],
            'sales_type_name' => $selected_sales_type_data ? $selected_sales_type_data['sales_type'] : 'غير محدد',
            'tax_name' => $selected_tax_data ? $selected_tax_data['name'] : 'غير محدد',
            'tax_rate' => $selected_tax_data ? $selected_tax_data['rate'] : 0,
            'tax_included' => $tax_included_in_price,
            'location_name' => $selected_location_name,
            'cost_center_name' => $selected_cost_center_name,
            'date' => date('Y-m-d H:i:s'),
            'order_' => 1
        ];

        // حفظ السلة الأصلية قبل مسحها
        $saved_cart = $_SESSION['cart'];

        // مسح السلة الحالية
        $_SESSION['cart'] = [];

        // تخزين رسالة النجاح في الجلسة
        $_SESSION['success_message'] = "✅ تم حفظ الفاتورة بنجاح!<br>" . 
                                      "📋 رقم الفاتورة: <strong>{$trans_no}</strong><br>" .
                                      "💰 الإجمالي: <strong>" . number_format($total_for_invoice, 2) . " ₪</strong><br>" .
                                      "📅 التاريخ: " . date('Y-m-d H:i:s');

        // إعادة التوجيه
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "❌ خطأ في حفظ الفاتورة: " . $e->getMessage();
        error_log("خطأ في POS: " . $e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// الحصول على اسم الموقع المحدد للعرض
$selected_location_name = "غير محدد";
foreach ($locations as $loc) {
    if ($loc['loc_code'] == $default_location) {
        $selected_location_name = $loc['location_name'] . " (" . $loc['loc_code'] . ")";
        break;
    }
}

// الحصول على اسم مركز التكلفة المحدد للعرض
$selected_cost_center_name = "غير محدد";
foreach ($cost_centers as $cc) {
    if ($cc['id'] == $default_cost_center) {
        $selected_cost_center_name = $cc['name'] . " (" . $cc['id'] . ")";
        break;
    }
}

// الحصول على اسم نوع المبيعات المحدد للعرض
$selected_sales_type_name = "غير محدد";
$sales_type_tax_text = "";
foreach ($sales_types as $sales_type) {
    if ($sales_type['id'] == $default_sales_type) {
        $selected_sales_type_name = $sales_type['sales_type'];
        $sales_type_tax_text = $sales_type['tax_included'] == 1 ? "(الضريبة مضمنة في السعر)" : "(الضريبة مضافة على السعر)";
        $selected_sales_type_name .= " " . $sales_type_tax_text;
        break;
    }
}

// الحصول على اسم الضريبة المحددة للعرض مع النسبة
$selected_tax_name = getTaxNameWithRate($tax_types, $default_tax_type);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام نقطة البيع</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            z-index: 2;
        }

        .header-link {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .header-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        @media (max-width: 992px) {
            .header-actions {
                margin-top: 10px;
            }
        }

        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        :root {
            --primary: #1a73e8;
            --primary-dark: #0d62c9;
            --secondary: #34a853;
            --secondary-dark: #2a9849;
            --danger: #ea4335;
            --danger-dark: #d93025;
            --warning: #fbbc05;
            --warning-dark: #e9ab00;
            --dark: #202124;
            --darker: #17181b;
            --light: #f8f9fa;
            --gray: #dadce0;
            --gray-light: #f0f2f5;
            --border: #dfe1e5;
            --success-bg: #e6f4ea;
            --error-bg: #fce8e6;
            --text-dark: #3c4043;
            --text-light: #5f6368;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--dark);
            min-height: 100vh;
            padding: 15px;
            direction: rtl;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
            height: 100vh;
        }
        
        header {
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            pointer-events: none;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 2;
        }
        
        .logo i {
            font-size: 28px;
            color: var(--warning);
            background: rgba(0,0,0,0.2);
            padding: 8px;
            border-radius: 50%;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 2;
        }
        
        .user-details {
            text-align: right;
        }
        
        .user-details .user-name {
            font-size: 16px;
            font-weight: 600;
        }
        
        .user-details .user-role {
            font-size: 13px;
            color: var(--gray);
        }
        
        .datetime {
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 10px;
            height: calc(100% - 140px);
            flex-grow: 1;
        }
        
        .panel {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            height: 100%;
        }
        
        .panel:hover {
            transform: translateY(-2px);
        }
        
        .panel-title {
            font-size: 18px;
            color: var(--primary);
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .panel-title i {
            font-size: 20px;
            color: var(--primary);
        }
        
        /* معلومات الفاتورة في سطر واحد */
        .invoice-summary-line {
            background: linear-gradient(135deg, #e6f4ea 0%, #c6e9d7 100%);
            border: 1px solid #34a853;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .invoice-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex-grow: 1;
            font-size: 12px;
        }
        
        .invoice-label {
            font-weight: 600;
            color: #0d652d;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .invoice-no {
            background: #34a853;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            min-width: 45px;
            text-align: center;
        }
        
        .invoice-total {
            font-weight: bold;
            color: #1a73e8;
            background: white;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #1a73e8;
        }
        
        .invoice-tax {
            color: #ea4335;
            background: #fce8e6;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #ea4335;
            font-size: 11px;
            font-weight: bold;
        }
        
        .invoice-time {
            color: #5f6368;
            background: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .invoice-items {
            color: #666;
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .invoice-summary-line .btn {
            padding: 4px 8px;
            font-size: 11px;
            height: 28px;
            min-width: 40px;
            flex-shrink: 0;
        }
        
        /* تحسينات للقسم الأيمن */
        .right-panel {
            display: flex;
            flex-direction: column;
        }
        
        .settings-grid-compact {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            margin-bottom: 12px;
        }
        
        .setting-row-compact {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .setting-row-compact label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 11px;
            white-space: nowrap;
        }
        
        .setting-row-compact select {
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
            background: white;
            height: 34px;
            width: 100%;
        }
        
        .update-btn-compact {
            grid-column: span 2;
            margin-top: 3px;
        }
        
        .compact-input-group {
            display: grid;
            grid-template-columns: 1fr auto 80px;
            gap: 6px;
            margin-bottom: 12px;
        }
        
        .compact-input-group input[type="text"] {
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            height: 36px;
        }
        
        .compact-input-group input[type="number"] {
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            text-align: center;
            height: 36px;
            width: 100%;
        }
        
        .compact-input-group .btn {
            padding: 8px 12px;
            font-size: 13px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 7px rgba(0, 0, 0, 0.15);
        }
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 7px rgba(0, 0, 0, 0.15);
        }
        
        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background: var(--warning-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 7px rgba(0, 0, 0, 0.15);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 7px rgba(0, 0, 0, 0.15);
        }
        
        .customer-info-compact {
            background: var(--gray-light);
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            flex: 1;
            min-height: 150px;
            max-height: 180px;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid var(--border);
        }
        
        .customer-info-compact .panel-title {
            font-size: 14px;
            margin-bottom: 8px;
            padding-bottom: 6px;
        }
        
        .customer-row-compact {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12px;
            line-height: 1.3;
        }
        
        .customer-row-compact span:first-child {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .quick-actions-compact {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            margin-top: 12px;
        }
        
        .quick-actions-compact .btn {
            font-size: 12px;
            padding: 8px 6px;
            min-height: 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            line-height: 1.2;
        }
        
        .quick-actions-compact .btn i {
            margin-bottom: 3px;
            font-size: 13px;
        }
        
        /* سلة المشتريات */
        .cart-container {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            height: 100%;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .total-amount {
            font-size: 20px;
            font-weight: bold;
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            flex-grow: 1;
            overflow-y: auto;
            display: block;
            max-height: 40vh;
        }
        
        .cart-table thead {
            position: sticky;
            top: 0;
        }
        
        .cart-table th {
            background: var(--primary);
            color: white;
            padding: 10px;
            text-align: center;
            position: sticky;
            top: 0;
            font-size: 13px;
        }
        
        .cart-table td {
            padding: 8px;
            text-align: center;
            border-bottom: 1px solid var(--gray-light);
            font-size: 13px;
        }
        
        .cart-table tr:nth-child(even) {
            background: var(--gray-light);
        }
        
        .cart-table tr:hover {
            background: #e8f0fe;
        }
        
        .cart-table input {
            width: 70px;
            padding: 5px;
            border: 1px solid var(--border);
            border-radius: 4px;
            text-align: center;
            font-size: 13px;
            background: white;
        }
        
        .remove-link {
            color: var(--danger);
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: color 0.3s;
            display: inline-block;
            width: 25px;
            height: 25px;
            line-height: 25px;
            border-radius: 50%;
        }
        
        .remove-link:hover {
            color: var(--danger-dark);
            background: rgba(234, 67, 53, 0.1);
        }
        
        .summary {
            background: #f0f7ff;
            border-radius: 8px;
            padding: 12px;
            margin-top: auto;
            border: 1px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 14px;
        }
        
        .summary-total {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary);
            border-top: 2px solid var(--border);
            padding-top: 8px;
            margin-top: 6px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .action-buttons button {
            flex: 1;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: #0d652d;
            border: 1px solid var(--secondary);
        }
        
        .alert-error {
            background: var(--error-bg);
            color: #c5221f;
            border: 1px solid var(--danger);
        }
        
        .empty-cart {
            text-align: center;
            padding: 20px 10px;
            color: var(--text-light);
            font-size: 16px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        
        .empty-cart i {
            font-size: 48px;
            margin-bottom: 12px;
            color: var(--border);
        }
        
        /* المودالات */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 12px;
            width: 450px;
            max-width: 95%;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .modal-large {
            width: 600px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .modal-title {
            font-size: 20px;
            color: var(--primary);
        }
        
        .close-btn {
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .payment-info {
            margin: 12px 0;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 16px;
        }
        
        .payment-label {
            font-weight: 600;
        }
        
        .payment-value {
            font-weight: bold;
            color: var(--primary);
        }
        
        .payment-input {
            width: 100%;
            padding: 10px;
            font-size: 18px;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            margin: 10px 0;
            background: var(--gray-light);
        }
        
        .payment-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }
        
        .change-display {
            background: var(--success-bg);
            padding: 10px;
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
            border: 1px solid var(--secondary);
        }
        
        /* Stock List Modal */
        .search-container {
            position: relative;
            margin-bottom: 12px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        #stockListModal .stock-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 12px;
        }

        #stockListModal .stock-item {
            padding: 8px 12px;
            border-bottom: 1px solid var(--gray-light);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s;
        }

        #stockListModal .stock-item:hover {
            background-color: var(--gray-light);
        }

        #stockListModal .stock-item:last-child {
            border-bottom: none;
        }

        #stockListModal .stock-item-id {
            font-weight: bold;
            color: var(--primary-dark);
            min-width: 70px;
            font-size: 12px;
        }

        #stockListModal .stock-item-desc {
            flex-grow: 1;
            text-align: right;
            margin: 0 8px;
            font-size: 13px;
        }

        #stockListModal .stock-item-price {
            color: var(--secondary-dark);
            font-weight: 600;
            min-width: 70px;
            text-align: left;
            font-size: 12px;
        }
        
        #stockListModal .stock-item-qty {
            color: var(--primary-dark);
            min-width: 40px;
            text-align: center;
            font-size: 12px;
        }
        
        /* Print Modal Styles */
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            background-color: white;
            padding: 20px;
        }
        
        .print-title {
            font-size: 28px;
            font-weight: bold;
            color: #1a73e8;
            margin-bottom: 10px;
        }
        
        .print-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
        }
        
        .print-table th {
            background-color: #1a73e8;
            color: white;
            padding: 10px;
            text-align: center;
        }
        
        .print-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .print-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .print-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 16px;
        }
        
        .print-total {
            font-size: 22px;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
            padding: 15px;
            background-color: #f0f7ff;
            border-radius: 8px;
            border-top: 2px solid #dee2e6;
        }
        
        .print-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #ccc;
            color: #666;
            font-size: 16px;
        }
        
        .receipt-content {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            white-space: pre-line;
            padding: 20px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-align: center;
        }
        
        .receipt-content div {
            margin: 8px 0;
        }
        
        .receipt-total {
            font-size: 18px;
            font-weight: bold;
            margin-top: 15px;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 1200px) {
            .quick-actions-compact .btn {
                font-size: 11px;
            }
            
            .invoice-info {
                gap: 8px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 992px) {
            .content {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .logo {
                justify-content: center;
            }
            
            .user-info {
                justify-content: center;
            }
            
            .modal-content, .print-invoice {
                width: 95%;
            }

            .modal-large {
                width: 95%;
            }
            
            .settings-grid-compact {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .panel {
                height: auto;
                min-height: 350px;
            }
            
            .cart-table {
                max-height: 250px;
            }
            
            .settings-grid-compact {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-compact {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .invoice-summary-line {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
            
            .invoice-info {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .quick-actions-compact {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .panel-title {
                font-size: 16px;
            }
            
            .compact-input-group {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .compact-input-group input {
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* قسم معلومات الزبون المختصر */
        .customer-compact-card {
            margin-top: 15px;
            background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
            border-radius: 12px;
            padding: 12px 15px;
            border: 1px solid #e0e7ff;
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.08);
        }
        
        .customer-compact-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f7ff;
        }
        
        .customer-compact-icon {
            background: linear-gradient(135deg, #1a73e8, #4285f4);
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .customer-compact-title {
            flex: 1;
        }
        
        .customer-compact-title h4 {
            margin: 0;
            font-size: 14px;
            color: #1a73e8;
            font-weight: 600;
        }
        
        .customer-compact-title p {
            margin: 2px 0 0 0;
            color: #5f6368;
            font-size: 10px;
        }
        
        .customer-compact-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dashed #f0f0f0;
        }
        
        .customer-compact-row:last-child {
            border-bottom: none;
        }
        
        .customer-compact-item {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
        }
        
        .customer-compact-item i {
            font-size: 12px;
            color: #1a73e8;
        }
        
        .customer-compact-label {
            font-size: 11px;
            color: #5f6368;
            min-width: 70px;
        }
        
        .customer-compact-value {
            font-size: 11px;
            font-weight: 600;
            color: #202124;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }
        
        .customer-compact-time {
            font-size: 11px;
            font-weight: 600;
            color: #1a73e8;
            background: #f0f7ff;
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .customer-compact-tax-badge {
            background: #e6f4ea;
            color: #0d652d;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #34a853;
        }
        
        .customer-compact-rate-badge {
            background: #1a73e8;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            min-width: 35px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-cash-register"></i>
                <h1>نظام نقطة البيع (POS)</h1>
            </div>

            <div class="header-actions">
                <a href="addstock.php" class="header-link" target="_blank">
                    <i class="fas fa-plus"></i> إضافة صنف جديد
                </a>
                <a href="sreport1.php" class="header-link" target="_blank">
                    <i class="fas fa-chart-bar"></i> تقارير المبيعات
                </a>
            </div>

            <div class="user-info">
                <div class="user-details">
                    <div class="user-name">مستخدم النظام</div>
                    <div class="user-role">مسؤول نقطة البيع</div>
                </div>
                <div class="datetime">
                    <i class="fas fa-clock"></i>
                    <span id="currentDateTime"></span>
                </div>
            </div>
        </header>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="font-size: 22px;"></i>
                <div style="margin-right: 10px;"><?= $success ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle" style="font-size: 22px;"></i>
                <div style="margin-right: 10px;"><?= $error ?></div>
            </div>
        <?php endif; ?>
        
        <div class="content">
            <div class="panel right-panel">
                <h2 class="panel-title"><i class="fas fa-barcode"></i> إضافة صنف جديد</h2>
                
                <?php if (isset($_SESSION['last_invoice'])): ?>
                <!-- معلومات الفاتورة المحفوظة في سطر واحد -->
                <div class="invoice-summary-line">
                    <div class="invoice-info">
                        <span class="invoice-label"><i class="fas fa-file-invoice"></i> آخر فاتورة:</span>
                        <span class="invoice-no">#<?= $_SESSION['last_invoice']['trans_no'] ?></span>
                        <span class="invoice-total"><?= number_format($_SESSION['last_invoice']['total'], 2) ?> ₪</span>
                        <span class="invoice-tax"><?= $_SESSION['last_invoice']['tax_rate'] ?>%</span>
                        <span class="invoice-time"><?= date('H:i', strtotime($_SESSION['last_invoice']['date'])) ?></span>
                        <span class="invoice-items"><?= count($_SESSION['last_invoice']['items']) ?> أصناف</span>
                    </div>
                    <button class="btn btn-sm btn-outline-info" onclick="showInvoiceDetails()" title="عرض التفاصيل">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <?php endif; ?>
                
                <form method="post" class="settings-grid-compact">
                    <!-- السطر الأول -->
                    <div class="setting-row-compact">
                        <label for="location">المخزن:</label>
                        <select name="location" id="location">
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['loc_code'] ?>" <?= $loc['loc_code'] == $default_location ? 'selected' : '' ?>>
                                    <?= $loc['location_name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="setting-row-compact">
                        <label for="cost_center">مركز التكلفة:</label>
                        <select name="cost_center" id="cost_center">
                            <?php foreach ($cost_centers as $cc): ?>
                                <option value="<?= $cc['id'] ?>" <?= $cc['id'] == $default_cost_center ? 'selected' : '' ?>>
                                    <?= $cc['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- السطر الثاني -->
                    <div class="setting-row-compact">
                        <label for="sales_type">نوع المبيعات:</label>
                        <select name="sales_type" id="sales_type">
                            <?php foreach ($sales_types as $sales_type): ?>
                                <option value="<?= $sales_type['id'] ?>" <?= $sales_type['id'] == $default_sales_type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sales_type['sales_type']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="setting-row-compact">
                        <label for="tax_type">نوع الضريبة:</label>
                        <select name="tax_type" id="tax_type">
                            <?php foreach ($tax_types as $tax): ?>
                                <option value="<?= $tax['id'] ?>" <?= $tax['id'] == $default_tax_type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tax['name']) ?> (<?= $tax['rate'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- زر التحديث -->
                    <div class="update-btn-compact">
                        <button type="submit" name="change_location" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-sync-alt"></i> تحديث الإعدادات
                        </button>
                    </div>
                </form>
                
                <!-- قسم إضافة الأصناف -->
                <form method="post" class="add-item-section">
                    <div class="compact-input-group">
                        <input type="text" name="item_code" id="item_code_input" placeholder="باركود/اسم الصنف" autofocus>
                        <input type="number" name="qty" value="1" min="1">
                        <button type="submit" name="add_item" class="btn btn-primary">
                            <i class="fas fa-plus"></i> إضافة
                        </button>
                    </div>
                </form>
                
                <!-- الأزرار السريعة (المفاتيح الأربعة) -->
                <div class="quick-actions-compact">
                    <button class="btn btn-success" onclick="openPaymentModal()">
                        <i class="fas fa-money-bill-wave"></i> دفع (F1)
                    </button>
                    <button class="btn btn-warning" onclick="printInvoice('a4')">
                        <i class="fas fa-print"></i> طباعة (F10)
                    </button>
                    <button class="btn btn-primary" onclick="printInvoice('receipt')">
                        <i class="fas fa-receipt"></i> إيصال (F9)
                    </button>
                    <button class="btn btn-danger" onclick="cancelInvoice()">
                        <i class="fas fa-trash-alt"></i> إلغاء
                    </button>
                </div>
                
                <!-- قسم معلومات الزبون المختصر في سطرين -->
                <div class="customer-compact-card">
                    <!-- رأس القسم -->
                    <div class="customer-compact-header">
                        <div class="customer-compact-icon">
                            <i class="fas fa-user-tie" style="color: white; font-size: 14px;"></i>
                        </div>
                        <div class="customer-compact-title">
                            <h4>معلومات العملية</h4>
                            <p>بيانات البيع الحالية</p>
                        </div>
                    </div>
                    
                    <!-- السطر الأول: الزبون، المخزن، مركز التكلفة -->
                    <div class="customer-compact-row">
                        <div class="customer-compact-item">
                            <i class="fas fa-user" style="color: #34a853;"></i>
                            <span class="customer-compact-label">الزبون:</span>
                            <span class="customer-compact-value">Walk-In</span>
                        </div>
                        
                        <div class="customer-compact-item">
                            <i class="fas fa-warehouse" style="color: #1a73e8;"></i>
                            <span class="customer-compact-label">المخزن:</span>
                            <span class="customer-compact-value" title="<?= $selected_location_name ?>">
                                <?= htmlspecialchars(substr($selected_location_name, 0, 15)) . (strlen($selected_location_name) > 15 ? '...' : '') ?>
                            </span>
                        </div>
                        
                        <div class="customer-compact-item">
                            <i class="fas fa-bullseye" style="color: #9c27b0;"></i>
                            <span class="customer-compact-label">مركز التكلفة:</span>
                            <span class="customer-compact-value" title="<?= $selected_cost_center_name ?>">
                                <?= htmlspecialchars(substr($selected_cost_center_name, 0, 10)) . (strlen($selected_cost_center_name) > 10 ? '...' : '') ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- السطر الثاني: نوع المبيعات، الضريبة، الوقت -->
                    <div class="customer-compact-row">
                        <div class="customer-compact-item">
                            <i class="fas fa-shopping-cart" style="color: #fbbc05;"></i>
                            <span class="customer-compact-label">نوع المبيعات:</span>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <span class="customer-compact-value" title="<?= $selected_sales_type_name ?>">
                                    <?= htmlspecialchars(substr($selected_sales_type_data['sales_type'] ?? 'غير محدد', 0, 10)) . (strlen($selected_sales_type_data['sales_type'] ?? '') > 10 ? '...' : '') ?>
                                </span>
                                <?php if($tax_included_in_price == 1): ?>
                                <span class="customer-compact-tax-badge">مضمنة</span>
                                <?php else: ?>
                                <span class="customer-compact-tax-badge" style="background: #fce8e6; color: #ea4335; border-color: #ea4335;">مضافة</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="customer-compact-item">
                            <i class="fas fa-percentage" style="color: #ea4335;"></i>
                            <span class="customer-compact-label">الضريبة:</span>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <span class="customer-compact-value">
                                    <?= $selected_tax_data['name'] ?? 'غير محدد' ?>
                                </span>
                                <?php if($selected_tax_data && $selected_tax_data['rate'] > 0): ?>
                                <span class="customer-compact-rate-badge">
                                    <?= $selected_tax_data['rate'] ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="customer-compact-item">
                            <i class="fas fa-clock" style="color: #666;"></i>
                            <span class="customer-compact-label">الوقت:</span>
                            <span id="currentTimeCompact" class="customer-compact-time">--:--</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="panel">
                <div class="cart-container">
                    <div class="cart-header">
                        <h2 class="panel-title"><i class="fas fa-shopping-cart"></i> سلة المشتريات</h2>
                        <div class="total-amount">
                            <?= number_format($total_with_tax, 2) . ' ₪' ?>
                        </div>
                    </div>
   
                    <?php if (count($_SESSION['cart']) > 0): ?>
                        <form method="post">
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th width="15%">رقم الصنف</th>
                                        <th width="25%">الوصف</th>
                                        <th width="10%">الكمية</th>
                                        <th width="15%">السعر</th>
                                        <th width="15%">الخصم %</th>
                                        <th width="15%">الإجمالي</th>
                                        <th width="5%">حذف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($_SESSION['cart'] as $item):
                                        $item_discount_decimal = $item['discount'] / 100;
                                        $subtotal = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
                                        $currency_symbol = ($item['currency'] == 'USD') ? '$' : '₪';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['stock_id']) ?></td>
                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                        <td><?= $item['qty'] ?></td>
                                        <td>
                                            <input type="number" name="unit_price[<?= $item['stock_id'] ?>]" 
                                                   value="<?= $item['unit_price'] ?>" step="0.01" min="0">
                                            <span><?= $currency_symbol ?></span>
                                        </td>
                                        <td>
                                            <input type="number" name="discount[<?= $item['stock_id'] ?>]" 
                                                   value="<?= $item['discount'] ?>" step="0.1" min="0" max="100">
                                            <span>%</span>
                                        </td>
                                        <td><?= number_format($subtotal, 2) ?> <?= $currency_symbol ?></td>
                                        <td>
                                            <a href="?remove=<?= $item['stock_id'] ?>" class="remove-link">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="summary">
                                <!-- حساب وإظهار المبالغ حسب نوع الضريبة -->
                                <?php if ($tax_included_in_price == 1): ?>
                                    <!-- الضريبة مضمنة في السعر -->
                                    <div class="summary-row">
                                        <span>المجموع الفرعي (شامل الضريبة):</span>
                                        <span><?= number_format($sub_total, 2) ?> ₪</span>
                                    </div>
                                    
                                    <?php if ($tax_amount > 0 && $selected_tax_data): ?>
                                    <div class="summary-row">
                                        <span>الضريبة (<?= $selected_tax_data['rate'] ?>%):</span>
                                        <span><?= number_format($tax_amount, 2) ?> ₪</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>المبيعات الصافية:</span>
                                        <span><?= number_format($net_sales, 2) ?> ₪</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="summary-row summary-total">
                                        <span>الإجمالي:</span>
                                        <span><?= number_format($total_with_tax, 2) ?> ₪</span>
                                    </div>
                                <?php else: ?>
                                    <!-- الضريبة مضافة على السعر -->
                                    <div class="summary-row">
                                        <span>المجموع الفرعي:</span>
                                        <span><?= number_format($sub_total, 2) ?> ₪</span>
                                    </div>
                                    
                                    <?php if ($tax_amount > 0 && $selected_tax_data): ?>
                                    <div class="summary-row">
                                        <span>الضريبة (<?= $selected_tax_data['rate'] ?>%):</span>
                                        <span><?= number_format($tax_amount, 2) ?> ₪</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="summary-row summary-total">
                                        <span>الإجمالي:</span>
                                        <span><?= number_format($total_with_tax, 2) ?> ₪</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <button type="submit" name="update_cart" class="btn btn-warning">
                                        <i class="fas fa-sync-alt"></i> تحديث الأسعار
                                    </button>
                                    <button type="submit" name="save_invoice" class="btn btn-success">
                                        <i class="fas fa-save"></i> حفظ الفاتورة
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>السلة فارغة</h3>
                            <p>ابدأ بإضافة أصناف باستخدام الباركود أو اسم الصنف</p>
                            <p>(اضغط F2 لعرض قائمة الأصناف)</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- المودالات -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-money-bill-wave"></i> الدفع النقدي</h2>
                <span class="close-btn" onclick="closePaymentModal()">&times;</span>
            </div>
            
            <div class="payment-info">
                <div class="payment-row">
                    <span class="payment-label">المبلغ المستحق:</span>
                    <span class="payment-value" id="invoiceTotalDisplay">0.00 ₪</span>
                </div>
                
                <form method="post" id="paymentForm">
                    <input type="hidden" name="invoice_total" id="invoiceTotal" value="0">
                    <input type="hidden" name="invoice_no" id="invoiceNo" value="">
                    
                    <input type="number" name="paid_amount" id="paidAmount" 
                           class="payment-input" placeholder="أدخل المبلغ المدفوع" 
                           step="0.01" min="0" required
                           oninput="calculateChange()">
                    
                    <div class="change-display" id="changeDisplay">الباقي: 0.00 ₪</div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn btn-danger" onclick="closePaymentModal()">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <button type="submit" name="process_payment" class="btn btn-success">
                            <i class="fas fa-check"></i> تأكيد الدفع
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="stockListModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-list"></i> قائمة الأصناف</h2>
                <span class="close-btn" onclick="closeStockListModal()">&times;</span>
            </div>
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="stockSearchInput" class="payment-input" placeholder="ابحث عن صنف بالاسم أو الكود..." oninput="filterStockItems()" style="padding-right: 40px;">
            </div>
            <div class="stock-list" id="stockItemsContainer">
                <div style="text-align: center; padding: 20px;">
                    <div class="loading" style="margin: 0 auto;"></div>
                    <p>جاري تحميل قائمة الأصناف...</p>
                </div>
            </div>
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-danger" onclick="closeStockListModal()">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
        </div>
    </div>
    
    <!-- مودال طباعة الفاتورة A4 -->
    <div id="printModal" class="modal">
        <div class="modal-content" style="width: 800px; max-width: 90%;">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-print"></i> معاينة طباعة الفاتورة</h2>
                <span class="close-btn" onclick="closePrintModal()">&times;</span>
            </div>
            <div id="printContent" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                <div class="print-header">
                    <h1 class="print-title">فاتورة مبيعات</h1>
                    <div>نظام نقطة البيع (POS)</div>
                </div>
                
                 <div class="print-details">
                    <div>
                        <div><strong>رقم الفاتورة:</strong> <span id="printInvoiceNo">-</span></div>
                        <div><strong>التاريخ:</strong> <span id="printDate">-</span></div>
                        <div><strong>نوع المبيعات:</strong> <span id="printSalesType"><?= $selected_sales_type_name ?></span></div>
                        <div><strong>نوع الضريبة:</strong> <span id="printTaxType"><?= $selected_tax_name ?></span></div>
                    </div>
                    <div>
                        <div><strong>الزبون:</strong> Walk-In Customer</div>
                        <div><strong>رقم الزبون:</strong> <?= $walkin_debtor_no ?></div>
                        <div><strong>الشركة:</strong> <?= $prefix ?></div>
                        <div><strong>المخزن:</strong> <span id="printLocation"><?= $selected_location_name ?></span></div>
                        <div><strong>مركز التكلفة:</strong> <span id="printCostCenter"><?= $selected_cost_center_name ?></span></div>
                    </div>
                </div>
                
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>رقم الصنف</th>
                            <th>الوصف</th>
                            <th>الكمية</th>
                            <th>السعر</th>
                            <th>الخصم</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody id="printItems">
                    </tbody>
                </table>
                
                <div class="print-summary">
                    <div class="print-summary-row">
                        <span>المجموع الفرعي:</span>
                        <span id="printSubtotal">0.00 ₪</span>
                    </div>
                    <div class="print-summary-row" id="printTaxRow" style="display: none;">
                        <span>الضريبة:</span>
                        <span id="printTaxAmount">0.00 ₪</span>
                    </div>
                </div>
                
                <div class="print-total">
                    <span>المجموع الكلي: </span>
                    <span id="printTotal">0.00 ₪</span>
                </div>
                
                <div class="print-footer">
                    <p>شكراً لتعاملكم معنا</p>
                    <p>للاستفسار: 0501234567 | www.example.com</p>
                </div>
            </div>
            
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-danger" onclick="closePrintModal()">
                    <i class="fas fa-times"></i> إغلاق
                </button>
                <button class="btn btn-success" onclick="printNow('a4')">
                    <i class="fas fa-print"></i> طباعة الفاتورة
                </button>
            </div>
        </div>
    </div>
    
    <!-- مودال طباعة الإيصال -->
    <div id="receiptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-receipt"></i> طباعة إيصال مبيعات</h2>
                <span class="close-btn" onclick="closeReceiptModal()">&times;</span>
            </div>
            
            <div class="receipt-content" id="receiptContent">
                </div>
            
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-danger" onclick="closeReceiptModal()">
                    <i class="fas fa-times"></i> إغلاق
                </button>
                <button class="btn btn-success" onclick="printNow('receipt')">
                    <i class="fas fa-print"></i> طباعة الإيصال
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let allStockItems = <?= json_encode($all_stock_items) ?>;
        let currentTaxRate = <?= $current_tax_rate ?>;
        let taxIncludedInPrice = <?= $tax_included_in_price ?>;
        let lastInvoice = <?= json_encode($_SESSION['last_invoice'] ?? null) ?>;

        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = 
                now.toLocaleDateString('ar-EG', options);
        }
        
        setInterval(updateDateTime, 1000);
        updateDateTime();
        
        // تحديث الوقت الحالي في القسم المختصر
        function updateCompactTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ar-EG', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            document.getElementById('currentTimeCompact').textContent = timeString;
        }
        setInterval(updateCompactTime, 1000);
        updateCompactTime();

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('item_code_input').focus();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'F2') {
                event.preventDefault();
                openStockListModal();
            } else if (event.key === 'F1') {
                event.preventDefault();
                openPaymentModal();
            } else if (event.key === 'F9') {
                event.preventDefault();
                printInvoice('receipt');
            } else if (event.key === 'F10') {
                event.preventDefault();
                printInvoice('a4');
            }
        });
        
        function openPaymentModal() {
            const cartItems = <?= json_encode($_SESSION['cart']) ?>;
            let total = 0;
            let currentInvoiceNo = '';

            if (cartItems.length > 0) {
                const totals = calculateInvoiceTotals(cartItems, currentTaxRate, taxIncludedInPrice);
                total = totals.total_with_tax;
                currentInvoiceNo = '';
            } else if (lastInvoice && lastInvoice.total) {
                total = parseFloat(lastInvoice.total);
                currentInvoiceNo = lastInvoice.trans_no || '';
            } else {
                alert('لا توجد فاتورة للدفع! أضف أصنافاً أو احفظ الفاتورة أولاً.');
                return;
            }
            
            document.getElementById('invoiceTotal').value = total.toFixed(2);
            document.getElementById('invoiceTotalDisplay').textContent = total.toFixed(2) + ' ₪';
            document.getElementById('invoiceNo').value = currentInvoiceNo || '0';
            document.getElementById('paidAmount').value = '';
            document.getElementById('changeDisplay').textContent = 'الباقي: 0.00 ₪';
            document.getElementById('paymentModal').style.display = 'flex';
            document.getElementById('paidAmount').focus();
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        function calculateChange() {
            const total = parseFloat(document.getElementById('invoiceTotal').value);
            const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
            const change = paid - total;
            
            if (isNaN(change)) {
                document.getElementById('changeDisplay').textContent = 'الباقي: 0.00 ₪';
                return;
            }
            
            if (change >= 0) {
                document.getElementById('changeDisplay').textContent = 
                    'الباقي: ' + change.toFixed(2) + ' ₪';
                document.getElementById('changeDisplay').style.color = '#34a853';
            } else {
                document.getElementById('changeDisplay').textContent = 
                    'المبلغ غير كافي. النقص: ' + Math.abs(change).toFixed(2) + ' ₪';
                document.getElementById('changeDisplay').style.color = '#ea4335';
            }
        }
        
        function calculateInvoiceTotals(items, taxRate, taxIncluded) {
            let sub_total = 0;
            let tax_amount = 0;
            let total_with_tax = 0;
            let net_sales = 0;
            
            items.forEach(item => {
                const item_discount_decimal = item.discount / 100;
                const subtotal = item.unit_price * item.qty * (1 - item_discount_decimal);
                sub_total += subtotal;
            });
            
            if (taxRate > 0) {
                if (taxIncluded == 1) {
                    net_sales = sub_total / (1 + taxRate);
                    tax_amount = net_sales * taxRate;
                    total_with_tax = sub_total;
                } else {
                    net_sales = sub_total;
                    tax_amount = sub_total * taxRate;
                    total_with_tax = sub_total + tax_amount;
                }
            } else {
                net_sales = sub_total;
                total_with_tax = sub_total;
            }
            
            return {
                sub_total,
                tax_amount,
                total_with_tax,
                net_sales
            };
        }
        
        function calculateItemTax(itemPrice, itemQty, itemDiscount, taxRate, taxIncluded) {
            const itemDiscountDecimal = itemDiscount / 100;
            const subtotal = itemPrice * itemQty * (1 - itemDiscountDecimal);
            
            if (taxRate > 0) {
                if (taxIncluded == 1) {
                    const netSales = subtotal / (1 + taxRate);
                    return netSales * taxRate;
                } else {
                    return subtotal * taxRate;
                }
            }
            return 0;
        }
        
        function showInvoiceDetails() {
            if (lastInvoice && lastInvoice.trans_no) {
                const details = `فاتورة #${lastInvoice.trans_no}
                
المجموع: ${parseFloat(lastInvoice.total).toFixed(2)} ₪
الضريبة: ${parseFloat(lastInvoice.tax_amount || 0).toFixed(2)} ₪ (${lastInvoice.tax_rate}%)
التاريخ: ${lastInvoice.date}
عدد الأصناف: ${lastInvoice.items.length}
نوع المبيعات: ${lastInvoice.sales_type_name}
نوع الضريبة: ${lastInvoice.tax_name}`;
                
                alert(details);
            }
        }
        
        function cancelInvoice() {
            if (confirm('هل أنت متأكد من إلغاء الفاتورة؟ سيتم حذف جميع الأصناف.')) {
                window.location.href = '?cancel_invoice=1';
            }
        }
        
        function printInvoice(type) {
            const cartItems = <?= json_encode($_SESSION['cart']) ?>;
            let items = [];
            let invoiceNo = '';
            let paid = 0;
            let change = 0;
            let subTotal = 0;
            let taxAmount = 0;
            let totalWithTax = 0;
            let netSales = 0;
            let costCenter = '<?= $selected_cost_center_name ?>';
            let salesType = '<?= $selected_sales_type_name ?>';
            let taxType = '<?= $selected_tax_name ?>';
            let taxRate = currentTaxRate;
            let taxIncluded = taxIncludedInPrice;
            
            if (cartItems.length > 0) {
                items = cartItems;
                const totals = calculateInvoiceTotals(cartItems, currentTaxRate, taxIncludedInPrice);
                subTotal = totals.sub_total;
                taxAmount = totals.tax_amount;
                totalWithTax = totals.total_with_tax;
                netSales = totals.net_sales;
                invoiceNo = 'مؤقتة';
            } else if (lastInvoice && lastInvoice.items) {
                items = lastInvoice.items;
                subTotal = lastInvoice.sub_total || 0;
                taxAmount = lastInvoice.tax_amount || 0;
                totalWithTax = lastInvoice.total;
                netSales = lastInvoice.net_sales || 0;
                invoiceNo = lastInvoice.trans_no;
                costCenter = lastInvoice.cost_center_name || costCenter;
                salesType = lastInvoice.sales_type_name || salesType;
                taxType = lastInvoice.tax_name ? lastInvoice.tax_name + ' (' + lastInvoice.tax_rate + '%)' : taxType;
                taxRate = lastInvoice.tax_rate ? lastInvoice.tax_rate / 100 : taxRate;
                taxIncluded = lastInvoice.tax_included !== undefined ? lastInvoice.tax_included : taxIncluded;
            } else {
                alert('لا توجد فاتورة للطباعة! أضف أصنافاً أو احفظ الفاتورة أولاً.');
                return;
            }
            
            if (type === 'a4') {
                const now = new Date();
                document.getElementById('printDate').textContent = now.toLocaleString('ar-EG');
                document.getElementById('printInvoiceNo').textContent = invoiceNo;
                document.getElementById('printSubtotal').textContent = subTotal.toFixed(2) + ' ₪';
                document.getElementById('printSalesType').textContent = salesType;
                document.getElementById('printCostCenter').textContent = costCenter;
                document.getElementById('printTaxType').textContent = taxType;
                
                const itemsTable = document.getElementById('printItems');
                itemsTable.innerHTML = '';
                
                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    const subtotal = item.unit_price * item.qty * (1 - itemDiscountDecimal);
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.stock_id}</td>
                        <td>${item.description}</td>
                        <td>${item.qty}</td>
                        <td>${item.unit_price.toFixed(2)}</td>
                        <td>${item.discount}%</td>
                        <td>${subtotal.toFixed(2)} ₪</td>
                    `;
                    itemsTable.appendChild(row);
                });
                
                if (taxAmount > 0) {
                    document.getElementById('printTaxRow').style.display = 'flex';
                    document.getElementById('printTaxAmount').textContent = taxAmount.toFixed(2) + ' ₪';
                }
                
                if (paid > 0) {
                    const paymentRow = document.createElement('tr');
                    paymentRow.innerHTML = `
                        <td colspan="5" style="text-align: right; font-weight: bold;">المدفوع:</td>
                        <td>${paid.toFixed(2)} ₪</td>
                    `;
                    itemsTable.appendChild(paymentRow);
                }
                
                if (change > 0) {
                    const changeRow = document.createElement('tr');
                    changeRow.innerHTML = `
                        <td colspan="5" style="text-align: right; font-weight: bold;">الباقي:</td>
                        <td>${change.toFixed(2)} ₪</td>
                    `;
                    itemsTable.appendChild(changeRow);
                }
                
                document.getElementById('printTotal').textContent = totalWithTax.toFixed(2) + ' ₪';
                document.getElementById('printModal').style.display = 'flex';
            } else if (type === 'receipt') {
                const receiptContent = document.getElementById('receiptContent');
                receiptContent.innerHTML = '';

                receiptContent.innerHTML += "========================================\n";
                receiptContent.innerHTML += "            فاتورة مبيعات\n";
                receiptContent.innerHTML += "========================================\n\n";
                receiptContent.innerHTML += `التاريخ: ${new Date().toLocaleString('ar-EG')}\n`;
                receiptContent.innerHTML += `رقم الفاتورة: ${invoiceNo}\n`;
                receiptContent.innerHTML += `نوع المبيعات: ${salesType}\n`;
                receiptContent.innerHTML += `مركز التكلفة: ${costCenter}\n`;
                receiptContent.innerHTML += `نوع الضريبة: ${taxType}\n`;
                receiptContent.innerHTML += `حالة الضريبة: ${taxIncluded == 1 ? 'مضمنة' : 'مضافة'}\n\n`;
                receiptContent.innerHTML += "----------------------------------------\n";
                receiptContent.innerHTML += "رقم الصنف       الوصف          الكمية  السعر    المجموع\n";
                receiptContent.innerHTML += "----------------------------------------\n";

                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    const subtotal = item.unit_price * item.qty * (1 - itemDiscountDecimal);
                    const stockIdPadded = item.stock_id.padEnd(15);
                    const descriptionPadded = item.description.padEnd(14);
                    const qtyPadded = String(item.qty).padEnd(6);
                    const pricePadded = item.unit_price.toFixed(2).padEnd(8);
                    const subtotalPadded = subtotal.toFixed(2);

                    receiptContent.innerHTML += `${stockIdPadded}${descriptionPadded}${qtyPadded}${pricePadded}${subtotalPadded}\n`;
                });

                receiptContent.innerHTML += "\n----------------------------------------\n";
                
                if (taxIncluded == 1) {
                    receiptContent.innerHTML += `المجموع الفرعي (شامل الضريبة): ${subTotal.toFixed(2)} ₪\n`;
                    if (taxAmount > 0) {
                        receiptContent.innerHTML += `الضريبة (${(taxRate * 100)}%): ${taxAmount.toFixed(2)} ₪\n`;
                        receiptContent.innerHTML += `المبيعات الصافية: ${netSales.toFixed(2)} ₪\n`;
                    }
                } else {
                    receiptContent.innerHTML += `المجموع الفرعي: ${subTotal.toFixed(2)} ₪\n`;
                    if (taxAmount > 0) {
                        receiptContent.innerHTML += `الضريبة (${(taxRate * 100)}%): ${taxAmount.toFixed(2)} ₪\n`;
                    }
                }
                
                receiptContent.innerHTML += `المجموع الكلي: ${totalWithTax.toFixed(2)} ₪\n`;
                
                if (paid > 0) {
                    receiptContent.innerHTML += `المدفوع: ${paid.toFixed(2)} ₪\n`;
                    receiptContent.innerHTML += `الباقي: ${change.toFixed(2)} ₪\n`;
                }

                receiptContent.innerHTML += "\n========================================\n";
                receiptContent.innerHTML += "شكراً لتعاملكم\n";
                receiptContent.innerHTML += "========================================\n";

                document.getElementById('receiptModal').style.display = 'flex';
            }
        }
        
        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
        }
        
        function closeReceiptModal() {
            document.getElementById('receiptModal').style.display = 'none';
        }
        
        function printNow(type) {
            if (type === 'a4') {
                const printWindow = window.open('', '_blank');
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html dir="rtl" lang="ar">
                    <head>
                        <meta charset="UTF-8">
                        <title>فاتورة مبيعات</title>
                        <style>
                            body { 
                                font-family: Arial, sans-serif; 
                                margin: 20px; 
                                direction: rtl;
                                background-color: white !important;
                                color: black !important;
                            }
                            * {
                                background-color: white !important;
                                color: black !important;
                            }
                            .print-header {
                                text-align: center;
                                margin-bottom: 20px;
                            }
                            .print-title {
                                font-size: 28px;
                                font-weight: bold;
                                color: #1a73e8;
                                margin-bottom: 10px;
                            }
                            .print-details {
                                display: flex;
                                justify-content: space-between;
                                margin-bottom: 20px;
                                padding: 15px;
                                border-radius: 8px;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                            }
                            .print-table {
                                width: 100%;
                                border-collapse: collapse;
                                margin-bottom: 20px;
                            }
                            .print-table th {
                                background-color: #1a73e8 !important;
                                color: white !important;
                                padding: 10px;
                                text-align: center;
                            }
                            .print-table td {
                                padding: 10px;
                                border: 1px solid #ddd;
                                text-align: center;
                            }
                            .print-summary {
                                background-color: #f8f9fa;
                                border-radius: 8px;
                                padding: 15px;
                                margin-bottom: 20px;
                                border: 1px solid #dee2e6;
                            }
                            .print-summary-row {
                                display: flex;
                                justify-content: space-between;
                                padding: 8px 0;
                                font-size: 16px;
                            }
                            .print-total {
                                font-size: 22px;
                                font-weight: bold;
                                text-align: right;
                                margin-top: 20px;
                                padding: 15px;
                                background-color: #f0f7ff !important;
                                border-radius: 8px;
                                border-top: 2px solid #dee2e6;
                            }
                            .print-footer {
                                text-align: center;
                                margin-top: 30px;
                                padding-top:  20px;
                                border-top: 2px dashed #ccc;
                                color: #666;
                                font-size: 16px;
                            }
                            @media print {
                                body { margin: 0; padding: 10px; }
                                .no-print { display: none !important; }
                            }
                        </style>
                    </head>
                    <body>
                        ${document.getElementById('printContent').innerHTML}
                        <div class="no-print" style="text-align: center; margin-top: 20px; padding: 10px; border-top: 1px solid #ccc;">
                            <button onclick="window.print()" style="padding: 10px 20px; background: #1a73e8; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                طباعة الفاتورة
                            </button>
                            <button onclick="window.close()" style="padding: 10px 20px; background: #ea4335; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                                إغلاق
                            </button>
                        </div>
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                printWindow.focus();
            } else if (type === 'receipt') {
                const printWindow = window.open('', '_blank');
                
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>إيصال مبيعات</title>
                        <style>
                            body { 
                                font-family: 'Courier New', monospace; 
                                margin: 0;
                                white-space: pre-line; 
                                font-size: 14px;
                                padding: 10px;
                            }
                            pre {
                                margin: 0;
                                padding: 10px;
                            }
                            @media print {
                                body { margin: 0; padding: 5px; }
                                .no-print { display: none !important; }
                            }
                        </style>
                    </head>
                    <body>
                        <pre>${document.getElementById('receiptContent').innerText}</pre>
                        <div class="no-print" style="text-align: center; margin-top: 20px;">
                            <button onclick="window.print()" style="padding: 10px 20px; background: #1a73e8; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                طباعة الإيصال
                            </button>
                            <button onclick="window.close()" style="padding: 10px 20px; background: #ea4335; color: white; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">
                                إغلاق
                            </button>
                        </div>
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                printWindow.focus();
            }
        }

        function closeStockListModal() {
            document.getElementById('stockListModal').style.display = 'none';
            document.getElementById('item_code_input').focus();
        }

        function openStockListModal() {
            document.getElementById('stockListModal').style.display = 'flex';
            document.getElementById('stockSearchInput').focus();
            
            const stockItemsContainer = document.getElementById('stockItemsContainer');
            stockItemsContainer.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <div class="loading" style="margin: 0 auto;"></div>
                    <p>جاري تحميل أحدث بيانات المخزون...</p>
                </div>
            `;
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?get_updated_stock=1')
                .then(response => response.json())
                .then(data => {
                    allStockItems = data;
                    renderStockItems(allStockItems);
                })
                .catch(error => {
                    console.error('Error fetching stock items:', error);
                    stockItemsContainer.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--danger);">
                            <i class="fas fa-exclamation-circle" style="font-size: 24px;"></i>
                            <p>فشل في تحميل بيانات المخزون. يرجى المحاولة مرة أخرى.</p>
                    </div>
                `;
            });
        }

        function renderStockItems(items) {
            const stockItemsContainer = document.getElementById('stockItemsContainer');
            stockItemsContainer.innerHTML = '';
            
            if (items.length === 0) {
                stockItemsContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-box-open" style="font-size: 24px;"></i>
                        <p>لا توجد أصناف في المخزون</p>
                    </div>
                `;
                return;
            }
            
            items.forEach(item => {
                const itemDiv = document.createElement('div');
                itemDiv.classList.add('stock-item');
                itemDiv.innerHTML = `
                    <span class="stock-item-id">${item.stock_id}</span>
                    <span class="stock-item-desc">${item.description}</span>
                    <span class="stock-item-qty">${item.on_hand_qty}</span>
                    <span class="stock-item-price">${item.unit_price.toFixed(2)} ₪</span>
                `;
                itemDiv.onclick = () => selectStockItem(item.stock_id);
                stockItemsContainer.appendChild(itemDiv);
            });
        }

        function filterStockItems() {
            const searchTerm = document.getElementById('stockSearchInput').value.toLowerCase();
            const filteredItems = allStockItems.filter(item => 
                item.stock_id.toLowerCase().includes(searchTerm) || 
                item.description.toLowerCase().includes(searchTerm)
            );
            renderStockItems(filteredItems);
        }

        function selectStockItem(stockId) {
            document.getElementById('item_code_input').value = stockId;
            document.querySelector('input[name="qty"]').value = 1;
            closeStockListModal();
            document.querySelector('form button[name="add_item"]').click();
        }
    </script>
</body>
</html>