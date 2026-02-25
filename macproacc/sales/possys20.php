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

// حساب المبالغ الحالية للسلة
$sub_total = 0;
$tax_amount = 0;
$total_with_tax = 0;

foreach ($_SESSION['cart'] as $item) {
    $item_discount_decimal = $item['discount'] / 100;
    $subtotal = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
    $sub_total += $subtotal;
}

// حساب الضريبة بناءً على ما إذا كانت مضمنة أو مضافة
if ($current_tax_rate > 0) {
    if ($tax_included_in_price == 1) {
        // الضريبة مضمنة في السعر
        $net_sales = $sub_total / (1 + $current_tax_rate);
        $tax_amount = $net_sales * $current_tax_rate;
        $total_with_tax = $sub_total; // السعر الإجمالي يحتوي على الضريبة
    } else {
        // الضريبة مضافة على السعر
        $tax_amount = $sub_total * $current_tax_rate;
        $total_with_tax = $sub_total + $tax_amount;
    }
} else {
    $total_with_tax = $sub_total;
}

// دالة لعرض الأخطاء بشكل مفصل
function debugSQL($conn, $sql, $params = []) {
    $full_sql = $sql;
    foreach ($params as $key => $value) {
        if (is_string($value)) {
            $value = "'" . $conn->real_escape_string($value) . "'";
        } elseif (is_null($value)) {
            $value = 'NULL';
        }
        $full_sql = str_replace($key, $value, $full_sql);
    }
    return $full_sql;
}

// التعامل مع حفظ الفاتورة - النسخة المصححة
if (isset($_POST['save_invoice']) && count($_SESSION['cart']) > 0) {
    startNewInvoice();
    
    $tran_date = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    try {
        // بدء المعاملة
        $conn->begin_transaction();
        
        // الحصول على رقم الحركة التالي لفاتورة المبيعات
        $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = " . ST_SALESINVOICE);
        $row = $result->fetch_assoc();
        $trans_no = (int)$row['max_no'] + 1;
        
        // الحصول على رقم طلب المبيعات التالي
        $result_order = $conn->query("SELECT MAX(order_no) as max_order FROM {$prefix}sales_orders WHERE trans_type = 30");
        $row_order = $result_order->fetch_assoc();
        $order_no = (int)$row_order['max_order'] + 1;
        
        // الحصول على معرف تفاصيل الضريبة التالي
        $result_tax_id = $conn->query("SELECT MAX(id) as max_id FROM {$prefix}trans_tax_details");
        $row_tax_id = $result_tax_id->fetch_assoc();
        $tax_detail_id = (int)$row_tax_id['max_id'] + 1;
        
        $reference = "SI-" . $trans_no;
        $type = ST_SALESINVOICE;
        $tpe = $default_sales_type; // استخدام نوع المبيعات المحدد
        $ship_via = 1;
        $payment_term = 4;
        $tax_included = $tax_included_in_price; // استخدام القيمة من sales_type
        
        // استخدام مركز التكلفة المحدد
        $dimension_id = $default_cost_center;
        
        // حساب المجاميع والعملة السائدة
        $sub_total_for_invoice = 0;
        $total_cost = 0;
        $main_currency = 'ILS';
        
        foreach ($_SESSION['cart'] as $item) {
            $item_discount_decimal = $item['discount'] / 100;
            $line_total = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
            $sub_total_for_invoice += $line_total;
            $total_cost += $item['standard_cost'] * $item['qty'];
            
            if ($item['currency'] == 'USD') {
                $main_currency = 'USD';
            }
        }
        
        // حساب الضريبة بشكل صحيح
        $tax_amount_for_invoice = 0;
        $net_sales_amount = 0;
        
        if ($current_tax_rate > 0) {
            if ($tax_included_in_price == 1) {
                // الضريبة مضمنة في الأسعار
                $net_sales_amount = $sub_total_for_invoice / (1 + $current_tax_rate);
                $tax_amount_for_invoice = $net_sales_amount * $current_tax_rate;
                $total_for_invoice = $sub_total_for_invoice; // الإجمالي يحتوي على الضريبة
            } else {
                // الضريبة تضاف على الأسعار
                $net_sales_amount = $sub_total_for_invoice;
                $tax_amount_for_invoice = $net_sales_amount * $current_tax_rate;
                $total_for_invoice = $net_sales_amount + $tax_amount_for_invoice;
            }
        } else {
            $net_sales_amount = $sub_total_for_invoice;
            $total_for_invoice = $sub_total_for_invoice;
        }
        
        $discount_amount = 0;
        $due_date = $tran_date;
        
        // ==============================================
        // 1. إنشاء طلب مبيعات (sales_orders)
        // ==============================================
        $order_ref = "SO-" . $order_no;
        $due_date_order = date('Y-m-d', strtotime('+1 days'));
        
        // التحقق من وجود حقل dimension_id في جدول sales_orders
        $sql_check_dimension_order = "SHOW COLUMNS FROM {$prefix}sales_orders LIKE 'dimension_id'";
        $result_check_dim_order = $conn->query($sql_check_dimension_order);
        $has_dimension_order = $result_check_dim_order && $result_check_dim_order->num_rows > 0;
        
        // تجربة أبسط طريقة بدون prepared statement
        if ($has_dimension_order) {
            $sql_order = "INSERT INTO {$prefix}sales_orders 
                (order_no, trans_type, version, type, debtor_no, branch_code, reference, 
                customer_ref, comments, ord_date, order_type, ship_via, delivery_address, 
                contact_phone, contact_email, deliver_to, freight_cost, from_stk_loc, 
                delivery_date, payment_terms, total, prep_amount, alloc, dimension_id) 
                VALUES ($order_no, 30, 0, 1, $walkin_debtor_no, '$branch_code', '$order_ref', 
                '', '', '$tran_date', 1, $ship_via, '', '', '', '', 0, '$default_location', 
                '$due_date_order', $payment_term, $total_for_invoice, 0, 0, $dimension_id)";
        } else {
            $sql_order = "INSERT INTO {$prefix}sales_orders 
                (order_no, trans_type, version, type, debtor_no, branch_code, reference, 
                customer_ref, comments, ord_date, order_type, ship_via, delivery_address, 
                contact_phone, contact_email, deliver_to, freight_cost, from_stk_loc, 
                delivery_date, payment_terms, total, prep_amount, alloc) 
                VALUES ($order_no, 30, 0, 1, $walkin_debtor_no, '$branch_code', '$order_ref', 
                '', '', '$tran_date', 1, $ship_via, '', '', '', '', 0, '$default_location', 
                '$due_date_order', $payment_term, $total_for_invoice, 0, 0)";
        }
        
        error_log("SQL sales_orders: " . $sql_order);
        
        if (!$conn->query($sql_order)) {
            throw new Exception("فشل في إنشاء طلب المبيعات: " . $conn->error . 
                "<br>SQL: " . $sql_order);
        }
        
        // ==============================================
        // 2. إدخال حركة المدين (debtor_trans)
        // ==============================================
        // التحقق من الحقول الموجودة
        $sql_check_fields = "SHOW COLUMNS FROM {$prefix}debtor_trans";
        $result_fields = $conn->query($sql_check_fields);
        $existing_fields = [];
        while ($field = $result_fields->fetch_assoc()) {
            $existing_fields[] = $field['Field'];
        }
        
        // استخدام SQL مباشرة بدون prepared statement
        $fields_to_include = [];
        $values_to_include = [];
        
        $fields_to_include[] = 'trans_no';
        $values_to_include[] = $trans_no;
        
        $fields_to_include[] = 'type';
        $values_to_include[] = $type;
        
        if (in_array('version', $existing_fields)) {
            $fields_to_include[] = 'version';
            $values_to_include[] = 0;
        }
        
        $fields_to_include[] = 'debtor_no';
        $values_to_include[] = $walkin_debtor_no;
        
        $fields_to_include[] = 'branch_code';
        $values_to_include[] = "'$branch_code'";
        
        $fields_to_include[] = 'tran_date';
        $values_to_include[] = "'$tran_date'";
        
        $fields_to_include[] = 'due_date';
        $values_to_include[] = "'$due_date'";
        
        $fields_to_include[] = 'reference';
        $values_to_include[] = "'$reference'";
        
        $fields_to_include[] = 'tpe';
        $values_to_include[] = $tpe;
        
        $fields_to_include[] = 'ov_amount';
        $values_to_include[] = $total_for_invoice;
        
        if (in_array('ov_discount', $existing_fields)) {
            $fields_to_include[] = 'ov_discount';
            $values_to_include[] = $discount_amount;
        }
        
        if (in_array('alloc', $existing_fields)) {
            $fields_to_include[] = 'alloc';
            $values_to_include[] = 0;
        }
        
        if (in_array('ship_via', $existing_fields)) {
            $fields_to_include[] = 'ship_via';
            $values_to_include[] = $ship_via;
        }
        
        if (in_array('payment_terms', $existing_fields)) {
            $fields_to_include[] = 'payment_terms';
            $values_to_include[] = $payment_term;
        }
        
        if (in_array('tax_included', $existing_fields)) {
            $fields_to_include[] = 'tax_included';
            $values_to_include[] = $tax_included;
        }
        
        if (in_array('order_', $existing_fields)) {
            $fields_to_include[] = 'order_';
            $values_to_include[] = $order_no;
        }
        
        if (in_array('dimension_id', $existing_fields)) {
            $fields_to_include[] = 'dimension_id';
            $values_to_include[] = $dimension_id;
        }
        
        if (in_array('dimension2_id', $existing_fields)) {
            $fields_to_include[] = 'dimension2_id';
            $values_to_include[] = 0;
        }
        
        // بناء SQL query
        $sql_debtor_trans = "INSERT INTO {$prefix}debtor_trans
            (" . implode(', ', $fields_to_include) . ")
            VALUES (" . implode(', ', $values_to_include) . ")";
        
        error_log("SQL debtor_trans: " . $sql_debtor_trans);
        
        if (!$conn->query($sql_debtor_trans)) {
            throw new Exception("فشل في حفظ الفاتورة: " . $conn->error . 
                "<br>SQL: " . $sql_debtor_trans);
        }
        
        // ==============================================
        // 3. إدخال تفاصيل الفاتورة (debtor_trans_details)
        // ==============================================
        $line_no = 1;
        foreach ($_SESSION['cart'] as $item) {
            $item_discount_decimal = $item['discount'] / 100;
            $line_total = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
            
            // حساب ضريبة الصنف
            $unit_tax = 0;
            if ($current_tax_rate > 0) {
                if ($tax_included_in_price == 1) {
                    // الضريبة مضمنة في السعر
                    $net_unit_price = $item['unit_price'] / (1 + $current_tax_rate);
                    $unit_tax = $net_unit_price * $current_tax_rate;
                } else {
                    // الضريبة مضافة على السعر
                    $unit_tax = $item['unit_price'] * $current_tax_rate;
                }
            }
            
            $discount_percent = $item['discount'] / 100;
            
            // التحقق من الحقول في debtor_trans_details
            $sql_check_fields_details = "SHOW COLUMNS FROM {$prefix}debtor_trans_details";
            $result_fields_details = $conn->query($sql_check_fields_details);
            $existing_fields_details = [];
            while ($field = $result_fields_details->fetch_assoc()) {
                $existing_fields_details[] = $field['Field'];
            }
            
            // استخدام SQL مباشرة
            $fields_details = [];
            $values_details = [];
            
            $fields_details[] = 'debtor_trans_no';
            $values_details[] = $trans_no;
            
            $fields_details[] = 'debtor_trans_type';
            $values_details[] = $type;
            
            $fields_details[] = 'stock_id';
            $values_details[] = "'" . $conn->real_escape_string($item['stock_id']) . "'";
            
            if (in_array('description', $existing_fields_details)) {
                $fields_details[] = 'description';
                $values_details[] = "'" . $conn->real_escape_string($item['description']) . "'";
            }
            
            if (in_array('unit_price', $existing_fields_details)) {
                $fields_details[] = 'unit_price';
                $values_details[] = $item['unit_price'];
            }
            
            if (in_array('unit_tax', $existing_fields_details)) {
                $fields_details[] = 'unit_tax';
                $values_details[] = $unit_tax;
            }
            
            if (in_array('quantity', $existing_fields_details)) {
                $fields_details[] = 'quantity';
                $values_details[] = $item['qty'];
            }
            
            if (in_array('discount_percent', $existing_fields_details)) {
                $fields_details[] = 'discount_percent';
                $values_details[] = $discount_percent;
            }
            
            if (in_array('standard_cost', $existing_fields_details)) {
                $fields_details[] = 'standard_cost';
                $values_details[] = $item['standard_cost'];
            }
            
            if (in_array('qty_done', $existing_fields_details)) {
                $fields_details[] = 'qty_done';
                $values_details[] = $item['qty'];
            }
            
            if (in_array('src_id', $existing_fields_details)) {
                $fields_details[] = 'src_id';
                $values_details[] = 0;
            }
            
            // بناء SQL query للتفاصيل
            $sql_details = "INSERT INTO {$prefix}debtor_trans_details
                (" . implode(', ', $fields_details) . ")
                VALUES (" . implode(', ', $values_details) . ")";
            
            error_log("SQL debtor_trans_details للصنف {$item['stock_id']}: " . $sql_details);
            
            if (!$conn->query($sql_details)) {
                throw new Exception("فشل في حفظ تفاصيل الفاتورة للصنف {$item['stock_id']}: " . $conn->error . 
                    "<br>SQL: " . $sql_details);
            }
            
            // ==============================================
            // 4. حركة المخزون (stock_moves)
            // ==============================================
            $qty = -abs($item['qty']);
            
            // التحقق من الحقول في stock_moves
            $sql_check_fields_stock = "SHOW COLUMNS FROM {$prefix}stock_moves";
            $result_fields_stock = $conn->query($sql_check_fields_stock);
            $existing_fields_stock = [];
            while ($field = $result_fields_stock->fetch_assoc()) {
                $existing_fields_stock[] = $field['Field'];
            }
            
            // استخدام SQL مباشرة
            $fields_stock = [];
            $values_stock = [];
            
            $fields_stock[] = 'trans_no';
            $values_stock[] = $trans_no;
            
            $fields_stock[] = 'type';
            $values_stock[] = $type;
            
            $fields_stock[] = 'stock_id';
            $values_stock[] = "'" . $conn->real_escape_string($item['stock_id']) . "'";
            
            if (in_array('loc_code', $existing_fields_stock)) {
                $fields_stock[] = 'loc_code';
                $values_stock[] = "'" . $conn->real_escape_string($default_location) . "'";
            }
            
            if (in_array('tran_date', $existing_fields_stock)) {
                $fields_stock[] = 'tran_date';
                $values_stock[] = "'$tran_date'";
            }
            
            if (in_array('price', $existing_fields_stock)) {
                $fields_stock[] = 'price';
                $values_stock[] = $item['unit_price'];
            }
            
            if (in_array('reference', $existing_fields_stock)) {
                $fields_stock[] = 'reference';
                $values_stock[] = "'$reference'";
            }
            
            if (in_array('qty', $existing_fields_stock)) {
                $fields_stock[] = 'qty';
                $values_stock[] = $qty;
            }
            
            if (in_array('standard_cost', $existing_fields_stock)) {
                $fields_stock[] = 'standard_cost';
                $values_stock[] = $item['standard_cost'];
            }
            
            // بناء SQL query للمخزون
            $sql_stock = "INSERT INTO {$prefix}stock_moves
                (" . implode(', ', $fields_stock) . ")
                VALUES (" . implode(', ', $values_stock) . ")";
            
            error_log("SQL stock_moves للصنف {$item['stock_id']}: " . $sql_stock);
            
            if (!$conn->query($sql_stock)) {
                throw new Exception("فشل في تحديث حركة المخزون للصنف {$item['stock_id']}: " . $conn->error . 
                    "<br>SQL: " . $sql_stock);
            }
            
            $line_no++;
        }

        // ==============================================
        // 5. إدخال تفاصيل الضريبة (trans_tax_details)
        // ==============================================
        if ($tax_amount_for_invoice > 0 && $selected_tax_data) {
            // التحقق من جدول trans_tax_details
            $sql_check_tax_table = "SHOW TABLES LIKE '{$prefix}trans_tax_details'";
            $result_check_tax = $conn->query($sql_check_tax_table);
            
            if ($result_check_tax && $result_check_tax->num_rows > 0) {
                // جدول موجود
                $rate = (float)$selected_tax_data['rate'];
                $memo_text = "ضريبة مبيعات - " . $selected_tax_data['name'];
                
                $sql_tax_details = "INSERT INTO {$prefix}trans_tax_details 
                    (id, trans_type, trans_no, tran_date, tax_type_id, rate, ex_rate, included_in_price, net_amount, amount, memo, reg_type)
                    VALUES ($tax_detail_id, $type, $trans_no, '$tran_date', $default_tax_type, $rate, 1, $tax_included_in_price, $net_sales_amount, $tax_amount_for_invoice, 'auto', 1)";
                
                error_log("SQL trans_tax_details: " . $sql_tax_details);
                
                if (!$conn->query($sql_tax_details)) {
                    error_log("ملاحظة: فشل في إدخال تفاصيل الضريبة: " . $conn->error);
                    // لا نوقف العملية إذا فشل إدخال تفاصيل الضريبة
                }
            } else {
                error_log("ملاحظة: جدول trans_tax_details غير موجود");
            }
        }
        
        // ==============================================
        // 6. القيود المحاسبية (gl_trans) - مبسطة
        // ==============================================
        // التحقق من وجود جدول gl_trans
        $sql_check_gl = "SHOW TABLES LIKE '{$prefix}gl_trans'";
        $result_check_gl = $conn->query($sql_check_gl);
        
        if ($result_check_gl && $result_check_gl->num_rows > 0) {
            $memo_ = "فاتورة مبيعات رقم " . $trans_no . " - الزبون: " . $walkin_debtor_no;
            
            // الحصول على الحسابات من جدول الشركة
            $sales_account = 41010001;
            $receivable_account = 11011001;
            $inventory_account = 1400;
            $cogs_account = 5000;
            $tax_sales_gl_code = $selected_tax_data ? $selected_tax_data['sales_gl_code'] : '21040001';
            
            // 1. قيد المدين (مدين) - القيمة الإجمالية مع الضريبة
            $sql_receivable = "INSERT INTO {$prefix}gl_trans 
                (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                VALUES ($type, $trans_no, '$tran_date', $receivable_account, '$memo_', $total_for_invoice, $dimension_id, 0, 2, $walkin_debtor_no)";
            
            error_log("SQL gl_trans (receivable): " . $sql_receivable);
            
            if (!$conn->query($sql_receivable)) {
                error_log("ملاحظة: فشل في تسجيل قيد الذمم المدينة: " . $conn->error);
            }
            
            // 2. قيد المبيعات (دائن) - القيمة الصافية بدون ضريبة
            $sales_amount = -$net_sales_amount;
            $sql_sales = "INSERT INTO {$prefix}gl_trans 
                (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                VALUES ($type, $trans_no, '$tran_date', $sales_account, '$memo_', $sales_amount, $dimension_id, 0, 2, $walkin_debtor_no)";
            
            error_log("SQL gl_trans (sales): " . $sql_sales);
            
            if (!$conn->query($sql_sales)) {
                error_log("ملاحظة: فشل في تسجيل قيد المبيعات: " . $conn->error);
            }
            
            // 3. قيد الضريبة (دائن) - قيمة الضريبة فقط
            if ($tax_amount_for_invoice > 0) {
                $tax_amount_negative = -$tax_amount_for_invoice;
                $sql_tax = "INSERT INTO {$prefix}gl_trans 
                    (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                    VALUES ($type, $trans_no, '$tran_date', $tax_sales_gl_code, '$memo_', $tax_amount_negative, $dimension_id, 0, 2, $walkin_debtor_no)";
                
                error_log("SQL gl_trans (tax): " . $sql_tax);
                
                if (!$conn->query($sql_tax)) {
                    error_log("ملاحظة: فشل في تسجيل قيد الضريبة: " . $conn->error);
                }
            }
            
            // 4. قيود تكلفة المبيعات والمخزون (إذا كان هناك تكلفة)
            if ($total_cost > 0) {
                // تكلفة المبيعات (مدين)
                $sql_cogs = "INSERT INTO {$prefix}gl_trans 
                    (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                    VALUES ($type, $trans_no, '$tran_date', $cogs_account, '$memo_', $total_cost, $dimension_id, 0, 2, $walkin_debtor_no)";
                
                error_log("SQL gl_trans (cogs): " . $sql_cogs);
                
                if (!$conn->query($sql_cogs)) {
                    error_log("ملاحظة: فشل في تسجيل قيد تكلفة المبيعات: " . $conn->error);
                }
                
                // المخزون (دائن)
                $inventory_amount = -$total_cost;
                $sql_inventory = "INSERT INTO {$prefix}gl_trans 
                    (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                    VALUES ($type, $trans_no, '$tran_date', $inventory_account, '$memo_', $inventory_amount, $dimension_id, 0, 2, $walkin_debtor_no)";
                
                error_log("SQL gl_trans (inventory): " . $sql_inventory);
                
                if (!$conn->query($sql_inventory)) {
                    error_log("ملاحظة: فشل في تسجيل قيد المخزون: " . $conn->error);
                }
            }
        } else {
            error_log("ملاحظة: جدول gl_trans غير موجود");
        }
        
        // إتمام المعاملة
        $conn->commit();
        
        $success = "تم حفظ الفاتورة بنجاح! رقم الفاتورة: $trans_no | رقم الطلب: $order_no | الإجمالي: " . number_format($total_for_invoice, 2) . " ₪";
        
        $_SESSION['last_invoice'] = [
            'trans_no' => $trans_no,
            'order_no' => $order_no,
            'sub_total' => $sub_total_for_invoice,
            'net_sales' => $net_sales_amount,
            'tax_amount' => $tax_amount_for_invoice,
            'total' => $total_for_invoice,
            'currency' => $main_currency,
            'location' => $default_location,
            'dimension_id' => $dimension_id,
            'items' => $_SESSION['cart'],
            'sales_type_name' => $selected_sales_type_data ? $selected_sales_type_data['sales_type'] : 'غير محدد',
            'tax_name' => $selected_tax_data ? $selected_tax_data['name'] : 'غير محدد',
            'tax_rate' => $selected_tax_data ? $selected_tax_data['rate'] : 0,
            'tax_included' => $tax_included_in_price
        ];
        
        $_SESSION['cart'] = [];
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "خطأ في حفظ الفاتورة: " . $e->getMessage();
        error_log("خطأ في حفظ الفاتورة: " . $e->getMessage());
        
        // إظهار تفاصيل أكثر للتصحيح
        $debug_info = "<div class='debug-info'>";
        $debug_info .= "<strong>تفاصيل التصحيح:</strong><br>";
        $debug_info .= "البادئة: " . $prefix . "<br>";
        $debug_info .= "رقم الفاتورة: " . ($trans_no ?? 'غير محدد') . "<br>";
        $debug_info .= "المخزن: " . $default_location . "<br>";
        $debug_info .= "مركز التكلفة: " . $default_cost_center . "<br>";
        $debug_info .= "نوع المبيعات: " . $default_sales_type . "<br>";
        $debug_info .= "نوع الضريبة: " . $default_tax_type . "<br>";
        $debug_info .= "عدد الأصناف: " . count($_SESSION['cart']) . "<br>";
        $debug_info .= "تاريخ العملية: " . $tran_date . "<br>";
        $debug_info .= "</div>";
        
        $error .= $debug_info;
    }
    
    // لا نعيد التوجيه لرؤية الرسالة الخطأ
    // header("Location: " . $_SERVER['PHP_SELF']);
    // exit;
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

// الحصول على اسم الضريبة المحددة للعرض
$selected_tax_name = "غير محدد";
foreach ($tax_types as $tax) {
    if ($tax['id'] == $default_tax_type) {
        $selected_tax_name = $tax['name'] . " (" . $tax['rate'] . "%)";
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام نقطة البيع</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* جميع الأنماط السابقة تبقى كما هي، أضفت فقط */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 12px;
            color: #666;
            direction: ltr;
            text-align: left;
        }
        
        .sql-debug {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
            font-size: 11px;
            color: #856404;
            direction: ltr;
            text-align: left;
            white-space: pre-wrap;
            word-break: break-all;
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
                    <i class="fas fa-plus"></i> تقارير المبيعات ملخص/مفصل
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
                <i class="fas fa-check-circle"></i>
                <div><?= $success ?></div>
                <button class="btn btn-sm btn-primary" onclick="window.location.reload()">
                    <i class="fas fa-sync"></i> تحديث الصفحة
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= $error ?></div>
                <div class="sql-debug">
                    <strong>تفاصيل SQL (من سجلات الخادم):</strong><br>
                    <?php 
                    // قراءة سجلات الأخطاء الأخيرة
                    $log_file = '/tmp/php_errors.log';
                    if (file_exists($log_file)) {
                        $log_content = tailCustom($log_file, 20);
                        echo nl2br(htmlspecialchars($log_content));
                    } else {
                        echo "لم يتم العثور على سجلات الأخطاء";
                    }
                    
                    function tailCustom($filepath, $lines = 1) {
                        $f = fopen($filepath, "rb");
                        if ($f === false) return false;
                        
                        fseek($f, -1, SEEK_END);
                        if (fread($f, 1) != "\n") $lines -= 1;
                        
                        $output = '';
                        $chunk = '';
                        
                        while (ftell($f) > 0 && $lines >= 0) {
                            $seek = min(ftell($f), 4096);
                            fseek($f, -$seek, SEEK_CUR);
                            $output = ($chunk = fread($f, $seek)) . $output;
                            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
                            $lines -= substr_count($chunk, "\n");
                        }
                        
                        while ($lines++ < 0) {
                            $output = substr($output, strpos($output, "\n") + 1);
                        }
                        
                        fclose($f);
                        return $output;
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="content">
            <div class="panel">
                <h2 class="panel-title"><i class="fas fa-barcode"></i> إضافة صنف جديد</h2>
                
                <form method="post">
                    <div class="location-selector">
                        <label for="location">المخزن:</label>
                        <select name="location" id="location">
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $loc['loc_code'] ?>" <?= $loc['loc_code'] == $default_location ? 'selected' : '' ?>>
                                    <?= $loc['location_name'] ?> (<?= $loc['loc_code'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cost-center-selector">
                        <label for="cost_center">مركز التكلفة:</label>
                        <select name="cost_center" id="cost_center">
                            <?php foreach ($cost_centers as $cc): ?>
                                <option value="<?= $cc['id'] ?>" <?= $cc['id'] == $default_cost_center ? 'selected' : '' ?>>
                                    <?= $cc['name'] ?> (<?= $cc['id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="sales-type-selector">
                        <label for="sales_type">نوع المبيعات:</label>
                        <select name="sales_type" id="sales_type">
                            <?php foreach ($sales_types as $sales_type): ?>
                                <option value="<?= $sales_type['id'] ?>" <?= $sales_type['id'] == $default_sales_type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sales_type['sales_type']) ?> (ضريبة: <?= $sales_type['tax_included'] == 1 ? 'مضمنة' : 'مضافة' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="tax-selector">
                        <label for="tax_type">نوع الضريبة:</label>
                        <select name="tax_type" id="tax_type">
                            <?php foreach ($tax_types as $tax): ?>
                                <option value="<?= $tax['id'] ?>" <?= $tax['id'] == $default_tax_type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tax['name']) ?> (<?= $tax['rate'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="change_location" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> تحديث
                        </button>
                    </div>
                </form>
                
                <form method="post">
                    <div class="input-group">
                        <input type="text" name="item_code" id="item_code_input" placeholder="أدخل الباركود/ أو اسم الصنف" autofocus>
                        <input type="number" name="qty" value="1" min="1" style="width: 80px;">
                        <button type="submit" name="add_item" class="btn btn-primary">
                            <i class="fas fa-plus"></i> إضافة
                        </button>
                    </div>
                </form>
                
                <div class="customer-info">
                    <h3 class="panel-title"><i class="fas fa-user"></i> معلومات الزبون</h3>
                    <div class="customer-row">
                        <span>الزبون:</span>
                        <span>Walk-In Customer</span>
                    </div>
                    <div class="customer-row">
                        <span>رقم الزبون:</span>
                        <span><?= $walkin_debtor_no ?></span>
                    </div>
                    <div class="customer-row">
                        <span>الفرع:</span>
                        <span><?= $branch_code ?></span>
                    </div>
                    <div class="customer-row">
                        <span>الشركة الحالية:</span>
                        <span><?= $prefix ?></span>
                    </div>
                    <div class="customer-row">
                        <span>إسم الشركة:</span>
                        <span><?= $company_name ?></span>
                    </div>
                    <div class="customer-row">
                        <span>المخزن المحدد:</span>
                        <span>
                            <?= $selected_location_name ?>
                        </span>
                    </div>
                    <div class="customer-row">
                        <span>مركز التكلفة المحدد:</span>
                        <span>
                            <?= $selected_cost_center_name ?>
                        </span>
                    </div>
                    <div class="customer-row">
                        <span>نوع المبيعات:</span>
                        <span>
                            <?= $selected_sales_type_name ?>
                        </span>
                    </div>
                    <div class="customer-row">
                        <span>نوع الضريبة:</span>
                        <span>
                            <?= $selected_tax_name ?>
                        </span>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button class="btn btn-warning" onclick="printInvoice('a4')">
                        <i class="fas fa-print"></i> طباعة الفاتورة (A4)<br>(F10)
                    </button>
                    <button class="btn btn-danger" onclick="cancelInvoice()">
                        <i class="fas fa-trash-alt"></i> إلغاء الفاتورة
                    </button>
                    <button class="btn btn-success" onclick="openPaymentModal()">
                        <i class="fas fa-money-bill-wave"></i> الدفع (F1)
                    </button>
                    <button class="btn btn-primary" onclick="printInvoice('receipt')">
                        <i class="fas fa-receipt"></i> طباعة إيصال<br>(F9)
                    </button>
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
                                            <!-- العرض والتخزين كنسبة مئوية -->
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
            const lastInvoice = <?= json_encode($_SESSION['last_invoice'] ?? []) ?>;

            let total = 0;
            let currentInvoiceNo = '';

            if (cartItems.length > 0) {
                let subTotal = 0;
                cartItems.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    subTotal += item.unit_price * item.qty * (1 - itemDiscountDecimal);
                });
                
                // حساب الضريبة بناءً على ما إذا كانت مضمنة أو مضافة
                let taxAmount = 0;
                let totalWithTax = 0;
                
                if (currentTaxRate > 0) {
                    if (taxIncludedInPrice == 1) {
                        // الضريبة مضمنة في السعر
                        const netSales = subTotal / (1 + currentTaxRate);
                        taxAmount = netSales * currentTaxRate;
                        totalWithTax = subTotal;
                    } else {
                        // الضريبة مضافة على السعر
                        taxAmount = subTotal * currentTaxRate;
                        totalWithTax = subTotal + taxAmount;
                    }
                } else {
                    totalWithTax = subTotal;
                }
                
                total = totalWithTax;
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
        
        function cancelInvoice() {
            if (confirm('هل أنت متأكد من إلغاء الفاتورة؟ سيتم حذف جميع الأصناف.')) {
                window.location.href = '?cancel_invoice=1';
            }
        }
        
        function printInvoice(type) {
            const cartItems = <?= json_encode($_SESSION['cart']) ?>;
            const lastInvoice = <?= json_encode($_SESSION['last_invoice'] ?? []) ?>;
            const lastPayment = <?= json_encode($_SESSION['last_payment'] ?? []) ?>;
            let items = [];
            let invoiceNo = '';
            let paid = 0;
            let change = 0;
            let subTotal = 0;
            let taxAmount = 0;
            let totalWithTax = 0;
            let costCenter = '<?= $selected_cost_center_name ?>';
            let salesType = '<?= $selected_sales_type_name ?>';
            let taxType = '<?= $selected_tax_name ?>';
            let taxRate = currentTaxRate;
            
            if (cartItems.length > 0) {
                items = cartItems;
                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    subTotal += item.unit_price * item.qty * (1 - itemDiscountDecimal);
                });
                
                // حساب الضريبة بناءً على ما إذا كانت مضمنة أو مضافة
                if (taxRate > 0) {
                    if (taxIncludedInPrice == 1) {
                        // الضريبة مضمنة في السعر
                        const netSales = subTotal / (1 + taxRate);
                        taxAmount = netSales * taxRate;
                        totalWithTax = subTotal;
                    } else {
                        // الضريبة مضافة على السعر
                        taxAmount = subTotal * taxRate;
                        totalWithTax = subTotal + taxAmount;
                    }
                } else {
                    totalWithTax = subTotal;
                }
                
                invoiceNo = 'مؤقتة';
            } else if (lastInvoice && lastInvoice.items) {
                items = lastInvoice.items;
                subTotal = lastInvoice.sub_total || 0;
                taxAmount = lastInvoice.tax_amount || 0;
                totalWithTax = lastInvoice.total;
                invoiceNo = lastInvoice.trans_no;
                costCenter = lastInvoice.dimension_id ? 'مركز التكلفة: ' + lastInvoice.dimension_id : costCenter;
                salesType = lastInvoice.sales_type_name ? lastInvoice.sales_type_name : salesType;
                taxType = lastInvoice.tax_name ? lastInvoice.tax_name + ' (' + lastInvoice.tax_rate + '%)' : taxType;
                taxRate = lastInvoice.tax_rate ? lastInvoice.tax_rate / 100 : taxRate;
                
                if (lastPayment && lastPayment.invoice_no == invoiceNo) {
                    paid = lastPayment.paid_amount;
                    change = lastPayment.change;
                }
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
                receiptContent.innerHTML += `نوع الضريبة: ${taxType}\n\n`;
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
                receiptContent.innerHTML += `المجموع الفرعي: ${subTotal.toFixed(2)} ₪\n`;
                
                if (taxAmount > 0) {
                    receiptContent.innerHTML += `الضريبة (${(taxRate * 100)}%): ${taxAmount.toFixed(2)} ₪\n`;
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
                        </style>
                    </head>
                    <body>
                        ${document.getElementById('printContent').innerHTML}
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                printWindow.print();
                printWindow.close();
                closePrintModal();
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
                            }
                            pre {
                                margin: 0;
                                padding: 10px;
                            }
                        </style>
                    </head>
                    <body>
                        <pre>${document.getElementById('receiptContent').innerText}</pre>
                    </body>
                    </html>
                `);
                
                printWindow.document.close();
                printWindow.print();
                printWindow.close();
                closeReceiptModal();
            }
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

        function closeStockListModal() {
            document.getElementById('stockListModal').style.display = 'none';
            document.getElementById('item_code_input').focus();
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