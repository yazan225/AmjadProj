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

// الحصول على جميع أنواع الضرائب النشطة
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
    $tax_types[] = ['id' => 1, 'name' => 'ضريبة القيمة المضافة', 'rate' => 16, 'sales_gl_code' => '21040001', 'purchasing_gl_code' => ''];
}

// تعيين المخزن الافتراضي
if (!isset($_SESSION['selected_location']) && count($locations) > 0) {
    $_SESSION['selected_location'] = $locations[0]['loc_code'];
}

// تعيين مركز التكلفة الافتراضي
if (!isset($_SESSION['selected_cost_center']) && count($cost_centers) > 0) {
    $_SESSION['selected_cost_center'] = $cost_centers[0]['id'];
}

// تعيين الضريبة الافتراضية
if (!isset($_SESSION['selected_tax_type']) && count($tax_types) > 0) {
    $_SESSION['selected_tax_type'] = $tax_types[0]['id'];
}

// التعامل مع تغيير المخزن ومركز التكلفة ونوع الضريبة
if (isset($_POST['change_location'])) {
    $_SESSION['selected_location'] = $_POST['location'];
    if (isset($_POST['cost_center'])) {
        $_SESSION['selected_cost_center'] = $_POST['cost_center'];
    }
    if (isset($_POST['tax_type'])) {
        $_SESSION['selected_tax_type'] = $_POST['tax_type'];
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$default_location = $_SESSION['selected_location'] ?? 'DEF';
$default_cost_center = $_SESSION['selected_cost_center'] ?? 0;
$default_tax_type = $_SESSION['selected_tax_type'] ?? 1;

// الحصول على بيانات الضريبة المحددة
$selected_tax_data = null;
foreach ($tax_types as $tax) {
    if ($tax['id'] == $default_tax_type) {
        $selected_tax_data = $tax;
        break;
    }
}

// حساب الضريبة الحالية
$current_tax_rate = 0;
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

// دالة للتحقق من بيانات الزبون
function validateCustomerData($conn, $prefix, $debtor_no, $branch_code) {
    $sql_check_customer = "SELECT COUNT(*) as count FROM {$prefix}debtors_master 
                          WHERE debtor_no = ? AND inactive = 0";
    $stmt = $conn->prepare($sql_check_customer);
    $stmt->bind_param("i", $debtor_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        throw new Exception("الزبون غير موجود أو غير نشط: " . $debtor_no);
    }
    
    $sql_check_branch = "SELECT COUNT(*) as count FROM {$prefix}cust_branch 
                        WHERE debtor_no = ? AND branch_code = ? AND inactive = 0";
    $stmt = $conn->prepare($sql_check_branch);
    $stmt->bind_param("is", $debtor_no, $branch_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        throw new Exception("الفرع غير موجود أو غير نشط: " . $branch_code . " للزبون: " . $debtor_no);
    }
    
    return true;
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

// التعامل مع تحديث السلة - بدون تحويل (يبقى كنسبة مئوية)
if (isset($_POST['update_cart'])) {
    foreach ($_SESSION['cart'] as &$item) {
        $id = $item['stock_id'];
        if (isset($_POST['unit_price'][$id]) && isset($_POST['discount'][$id])) {
            $price = floatval($_POST['unit_price'][$id]);
            $disc = floatval($_POST['discount'][$id]);
            if ($price >= 0) $item['unit_price'] = $price;
            // الخصم يبقى كنسبة مئوية (7 تبقى 7)
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

// حساب الضريبة
if ($current_tax_rate > 0) {
    $tax_amount = $sub_total * $current_tax_rate;
}

$total_with_tax = $sub_total + $tax_amount;

// التعامل مع حفظ الفاتورة
if (isset($_POST['save_invoice']) && count($_SESSION['cart']) > 0) {
    startNewInvoice();
    
    $tran_date = date('Y-m-d');
    
    // الحصول على رقم الحركة التالي
    $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = " . ST_SALESINVOICE);
    $row = $result->fetch_assoc();
    $trans_no = $row['max_no'] + 1;
    
    $reference = $trans_no;
    $type = ST_SALESINVOICE;
    $tpe = 1;
    $ship_via = 1;
    $payment_term = 4;
    $tax_included = 1;
    
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
    
    // #####################################################
    // ### التصحيح: حساب الضريبة بشكل صحيح ###
    // #####################################################
    
    // إذا كانت الضريبة مضمنة في الأسعار (tax_included = 1)
    $tax_amount_for_invoice = 0;
    $net_sales_amount = 0;
    
    if ($current_tax_rate > 0) {
        // الاحتمال الأول: الضريبة مضمنة في الأسعار
        $net_sales_amount = $sub_total_for_invoice / (1 + $current_tax_rate);
        $tax_amount_for_invoice = $net_sales_amount * $current_tax_rate;
        
        // أو إذا كانت الضريبة تضاف على السعر (tax_included = 0)
        // $net_sales_amount = $sub_total_for_invoice;
        // $tax_amount_for_invoice = $net_sales_amount * $current_tax_rate;
    } else {
        $net_sales_amount = $sub_total_for_invoice;
    }
    
    $total_for_invoice = $net_sales_amount + $tax_amount_for_invoice;
    
    // #####################################################
    
    try {
        validateCustomerData($conn, $prefix, $walkin_debtor_no, $branch_code);
        
        error_log("بدء حفظ الفاتورة - الزبون: " . $walkin_debtor_no . ", الفرع: " . $branch_code . ", العملة: " . $main_currency . ", الموقع: " . $default_location . ", مركز التكلفة: " . $dimension_id);
        
        // التحقق من أن الموقع صالح
        $sql_check_location = "SELECT loc_code FROM {$prefix}locations WHERE loc_code = ?";
        $stmt_check_loc = $conn->prepare($sql_check_location);
        $stmt_check_loc->bind_param("s", $default_location);
        $stmt_check_loc->execute();
        $result_check_loc = $stmt_check_loc->get_result();

        if ($result_check_loc->num_rows == 0) {
            // استخدام أول موقع متاح كبديل
            $sql_first_loc = "SELECT loc_code FROM {$prefix}locations ORDER BY loc_code LIMIT 1";
            $result_first = $conn->query($sql_first_loc);
            if ($result_first && $result_first->num_rows > 0) {
                $row_first = $result_first->fetch_assoc();
                $default_location = $row_first['loc_code'];
                error_log("تم تغيير الموقع الافتراضي إلى: " . $default_location);
            } else {
                throw new Exception("لا توجد مواقع متاحة في النظام");
            }
        }
        
        // التحقق من أن مركز التكلفة صالح
        if ($dimension_id > 0) {
            $sql_check_dimension = "SELECT id FROM {$prefix}dimensions WHERE id = ? AND closed = 0";
            $stmt_check_dim = $conn->prepare($sql_check_dimension);
            $stmt_check_dim->bind_param("i", $dimension_id);
            $stmt_check_dim->execute();
            $result_check_dim = $stmt_check_dim->get_result();
            
            if ($result_check_dim->num_rows == 0) {
                error_log("مركز التكلفة المحدد غير صالح، سيتم استخدام 0 كقيمة افتراضية");
                $dimension_id = 0;
            }
        }
        
        // الحصول على معدل الضريبة المحدد
        $tax_rate_for_calc = $current_tax_rate;
        $tax_sales_gl_code = "21040001";
        
        if ($selected_tax_data) {
            $tax_rate_for_calc = floatval($selected_tax_data['rate']) / 100;
            if (!empty($selected_tax_data['sales_gl_code'])) {
                $tax_sales_gl_code = $selected_tax_data['sales_gl_code'];
            }
        }
        
        // بدء المعاملة
        $conn->begin_transaction();

        // إنشاء طلب مبيعات - التأكد من حفظ الموقع الصحيح
        $order_no = 0;
        $result_order = $conn->query("SELECT MAX(order_no) as max_order FROM {$prefix}sales_orders WHERE trans_type = 30");
        $row_order = $result_order->fetch_assoc();
        $order_no = $row_order['max_order'] + 1;

        // التحقق من وجود حقل dimension_id في جدول sales_orders
        $sql_check_dimension_order = "SHOW COLUMNS FROM {$prefix}sales_orders LIKE 'dimension_id'";
        $result_check_dim_order = $conn->query($sql_check_dimension_order);
        $has_dimension_order = $result_check_dim_order && $result_check_dim_order->num_rows > 0;

        if ($has_dimension_order) {
            $sql_order = "INSERT INTO {$prefix}sales_orders 
                (order_no, trans_type, version, type, debtor_no, branch_code, reference, 
                customer_ref, comments, ord_date, order_type, ship_via, delivery_address, 
                contact_phone, contact_email, deliver_to, freight_cost, from_stk_loc, 
                delivery_date, payment_terms, total, prep_amount, alloc, dimension_id) 
                VALUES (?, 30, 0, 1, ?, ?, ?, '', '', ?, 1, ?, '', '', '', '', 0, ?, ?, ?, ?, 0, 0, ?)";

            $stmt_order = $conn->prepare($sql_order);
            $due_date = date('Y-m-d', strtotime('+1 days'));
            $order_ref = "SO-" . $order_no;

            $stmt_order->bind_param("issssssssdi", 
                $order_no,
                $walkin_debtor_no,
                $branch_code,
                $order_ref,
                $tran_date,
                $ship_via,
                $default_location,
                $due_date,
                $payment_term,
                $total_for_invoice,
                $dimension_id
            );
        } else {
            $sql_order = "INSERT INTO {$prefix}sales_orders 
                (order_no, trans_type, version, type, debtor_no, branch_code, reference, 
                customer_ref, comments, ord_date, order_type, ship_via, delivery_address, 
                contact_phone, contact_email, deliver_to, freight_cost, from_stk_loc, 
                delivery_date, payment_terms, total, prep_amount, alloc) 
                VALUES (?, 30, 0, 1, ?, ?, ?, '', '', ?, 1, ?, '', '', '', '', 0, ?, ?, ?, ?, 0, 0)";

            $stmt_order = $conn->prepare($sql_order);
            $due_date = date('Y-m-d', strtotime('+1 days'));
            $order_ref = "SO-" . $order_no;

            $stmt_order->bind_param("issssssssd", 
                $order_no,
                $walkin_debtor_no,
                $branch_code,
                $order_ref,
                $tran_date,
                $ship_via,
                $default_location,
                $due_date,
                $payment_term,
                $total_for_invoice
            );
        }

        if (!$stmt_order->execute()) {
            throw new Exception("فشل في إنشاء طلب المبيعات: " . $stmt_order->error);
        }
        
        // التحقق من وجود حقل curr_code في جدول debtor_trans
        $sql_check_column = "SHOW COLUMNS FROM {$prefix}debtor_trans LIKE 'curr_code'";
        $result_check = $conn->query($sql_check_column);
        $has_curr_code = $result_check && $result_check->num_rows > 0;
        
        // التحقق من وجود حقل dimension_id في جدول debtor_trans
        $sql_check_dimension_col = "SHOW COLUMNS FROM {$prefix}debtor_trans LIKE 'dimension_id'";
        $result_check_dim_col = $conn->query($sql_check_dimension_col);
        $has_dimension_id = $result_check_dim_col && $result_check_dim_col->num_rows > 0;
        
        // إدخال حركة المدين
        if ($has_curr_code && $has_dimension_id) {
            $sql = "INSERT INTO {$prefix}debtor_trans
                (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, 
                reference, tpe, ship_via, payment_terms, tax_included, ov_amount, ov_discount, order_, curr_code, dimension_id)
                VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $due_date = $tran_date;
            $discount_amount = 0;
            
            $stmt->bind_param("iiissssiisiddisi", 
                $trans_no, 
                $type, 
                $walkin_debtor_no, 
                $branch_code, 
                $tran_date, 
                $due_date, 
                $reference, 
                $tpe, 
                $ship_via, 
                $payment_term, 
                $tax_included, 
                $total_for_invoice, 
                $discount_amount,
                $order_no,
                $main_currency,
                $dimension_id
            );
        } else if ($has_curr_code) {
            $sql = "INSERT INTO {$prefix}debtor_trans
                (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, 
                reference, tpe, ship_via, payment_terms, tax_included, ov_amount, ov_discount, order_, curr_code)
                VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $due_date = $tran_date;
            $discount_amount = 0;
            
            $stmt->bind_param("iiissssiisiddis", 
                $trans_no, 
                $type, 
                $walkin_debtor_no, 
                $branch_code, 
                $tran_date, 
                $due_date, 
                $reference, 
                $tpe, 
                $ship_via, 
                $payment_term, 
                $tax_included, 
                $total_for_invoice, 
                $discount_amount,
                $order_no,
                $main_currency
            );
        } else if ($has_dimension_id) {
            $sql = "INSERT INTO {$prefix}debtor_trans
                (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, 
                reference, tpe, ship_via, payment_terms, tax_included, ov_amount, ov_discount, order_, dimension_id)
                VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $due_date = $tran_date;
            $discount_amount = 0;
            
            $stmt->bind_param("iiissssiisiddii", 
                $trans_no, 
                $type, 
                $walkin_debtor_no, 
                $branch_code, 
                $tran_date, 
                $due_date, 
                $reference, 
                $tpe, 
                $ship_via, 
                $payment_term, 
                $tax_included, 
                $total_for_invoice, 
                $discount_amount,
                $order_no,
                $dimension_id
            );
        } else {
            $sql = "INSERT INTO {$prefix}debtor_trans
                (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, 
                reference, tpe, ship_via, payment_terms, tax_included, ov_amount, ov_discount, order_)
                VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $due_date = $tran_date;
            $discount_amount = 0;
            
            $stmt->bind_param("iiissssiisiddi", 
                $trans_no, 
                $type, 
                $walkin_debtor_no, 
                $branch_code, 
                $tran_date, 
                $due_date, 
                $reference, 
                $tpe, 
                $ship_via, 
                $payment_term, 
                $tax_included, 
                $total_for_invoice, 
                $discount_amount,
                $order_no
            );
        }
        
        if (!$stmt->execute()) {
            throw new Exception("فشل في حفظ الفاتورة: " . $stmt->error);
        }
        
        // التحقق من وجود حقل curr_code في جدول debtor_trans_details
        $sql_check_column_details = "SHOW COLUMNS FROM {$prefix}debtor_trans_details LIKE 'curr_code'";
        $result_check_details = $conn->query($sql_check_column_details);
        $has_curr_code_details = $result_check_details && $result_check_details->num_rows > 0;
        
        // إدخال الأصناف
        $line_no = 1;
        foreach ($_SESSION['cart'] as $item) {
            $item_discount_decimal = $item['discount'] / 100;
            $line_total = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
            
            $unit_tax = $item['unit_price'] * $tax_rate_for_calc;
            
            if ($has_curr_code_details) {
                $sql_line = "INSERT INTO {$prefix}debtor_trans_details
                    (debtor_trans_no, debtor_trans_type, stock_id, description, unit_price, unit_tax, quantity, discount_percent, standard_cost, qty_done, src_id, curr_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)";
                $stmt_line = $conn->prepare($sql_line);
                
                $discount_percent = $item['discount'] / 100;
                
                $stmt_line->bind_param("iissddidiis",
                    $trans_no, 
                    $type, 
                    $item['stock_id'], 
                    $item['description'],
                    $item['unit_price'], 
                    $unit_tax, 
                    $item['qty'], 
                    $discount_percent,
                    $item['standard_cost'], 
                    $item['qty'],
                    $item['currency']
                );
            } else {
                $sql_line = "INSERT INTO {$prefix}debtor_trans_details
                    (debtor_trans_no, debtor_trans_type, stock_id, description, unit_price, unit_tax, quantity, discount_percent, standard_cost, qty_done, src_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
                $stmt_line = $conn->prepare($sql_line);
                
                $discount_percent = $item['discount'] / 100;
                
                $stmt_line->bind_param("iissddidii",
                    $trans_no, 
                    $type, 
                    $item['stock_id'], 
                    $item['description'],
                    $item['unit_price'], 
                    $unit_tax, 
                    $item['qty'], 
                    $discount_percent,
                    $item['standard_cost'], 
                    $item['qty']
                );
            }
            
            if (!$stmt_line->execute()) {
                throw new Exception("فشل في حفظ تفاصيل الفاتورة: " . $stmt_line->error);
            }
            
            // التحقق من وجود حقل curr_code في جدول stock_moves
            $sql_check_column_stock = "SHOW COLUMNS FROM {$prefix}stock_moves LIKE 'curr_code'";
            $result_check_stock = $conn->query($sql_check_column_stock);
            $has_curr_code_stock = $result_check_stock && $result_check_stock->num_rows > 0;
            
            // التحقق من وجود حقل dimension_id في جدول stock_moves
            $sql_check_dimension_stock = "SHOW COLUMNS FROM {$prefix}stock_moves LIKE 'dimension_id'";
            $result_check_dim_stock = $conn->query($sql_check_dimension_stock);
            $has_dimension_stock = $result_check_dim_stock && $result_check_dim_stock->num_rows > 0;
            
            // إضافة حركة المخزون - التأكد من استخدام الموقع الصحيح ومركز التكلفة
            if ($has_curr_code_stock && $has_dimension_stock) {
                $sql_stock_move = "INSERT INTO {$prefix}stock_moves
                    (trans_no, type, stock_id, loc_code, tran_date, price, reference, qty, standard_cost, curr_code, dimension_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_stock_move = $conn->prepare($sql_stock_move);
                $qty = -abs($item['qty']);
                $stmt_stock_move->bind_param("iisssdsddsi", 
                    $trans_no, 
                    $type, 
                    $item['stock_id'], 
                    $default_location,
                    $tran_date, 
                    $item['unit_price'], 
                    $reference, 
                    $qty, 
                    $item['standard_cost'],
                    $item['currency'],
                    $dimension_id
                );
            } else if ($has_curr_code_stock) {
                $sql_stock_move = "INSERT INTO {$prefix}stock_moves
                    (trans_no, type, stock_id, loc_code, tran_date, price, reference, qty, standard_cost, curr_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_stock_move = $conn->prepare($sql_stock_move);
                $qty = -abs($item['qty']);
                $stmt_stock_move->bind_param("iisssdsdds", 
                    $trans_no, 
                    $type, 
                    $item['stock_id'], 
                    $default_location,
                    $tran_date, 
                    $item['unit_price'], 
                    $reference, 
                    $qty, 
                    $item['standard_cost'],
                    $item['currency']
                );
            } else if ($has_dimension_stock) {
                $sql_stock_move = "INSERT INTO {$prefix}stock_moves
                    (trans_no, type, stock_id, loc_code, tran_date, price, reference, qty, standard_cost, dimension_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_stock_move = $conn->prepare($sql_stock_move);
                $qty = -abs($item['qty']);
                $stmt_stock_move->bind_param("iisssdsddi", 
                    $trans_no, 
                    $type, 
                    $item['stock_id'], 
                    $default_location,
                    $tran_date, 
                    $item['unit_price'], 
                    $reference, 
                    $qty, 
                    $item['standard_cost'],
                    $dimension_id
                );
            } else {
                $sql_stock_move = "INSERT INTO {$prefix}stock_moves
                    (trans_no, type, stock_id, loc_code, tran_date, price, reference, qty, standard_cost)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_stock_move = $conn->prepare($sql_stock_move);
                $qty = -abs($item['qty']);
                $stmt_stock_move->bind_param("iisssdsdd", 
                    $trans_no, 
                    $type, 
                    $item['stock_id'], 
                    $default_location,
                    $tran_date, 
                    $item['unit_price'], 
                    $reference, 
                    $qty, 
                    $item['standard_cost']
                );
            }
            
            if (!$stmt_stock_move->execute()) {
                throw new Exception("فشل في تحديث حركة المخزون: " . $stmt_stock_move->error);
            }
            
            $line_no++;
        }

        // #####################################################
        // ### التصحيح: القيود المحاسبية الصحيحة ###
        // #####################################################
        
        $sql_check_company = "SHOW TABLES LIKE '{$prefix}company'";
        $result_check = $conn->query($sql_check_company);
        
        if ($result_check && $result_check->num_rows > 0) {
            $sql_accounts = "SELECT sales_account, receivable_account, inventory_account, cogs_account 
                             FROM {$prefix}company";
            $result_accounts = $conn->query($sql_accounts);
            if ($result_accounts && $result_accounts->num_rows > 0) {
                $company_accounts = $result_accounts->fetch_assoc();
                $sales_account = $company_accounts['sales_account'];
                $receivable_account = $company_accounts['receivable_account'];
                $inventory_account = $company_accounts['inventory_account'];
                $cogs_account = $company_accounts['cogs_account'];
            } else {
                $sales_account = 41010001;
                $receivable_account = 11011001;
                $inventory_account = 1400;
                $cogs_account = 5000;
            }
        } else {
            $sales_account = 41010001;
            $receivable_account = 11011001;
            $inventory_account = 1400;
            $cogs_account = 5000;
        }

        // القيم الصحيحة للقيود
        $tax_amount_for_gl = $tax_amount_for_invoice;
        $net_sales_for_gl = $net_sales_amount;

        $sql_gl = "INSERT INTO {$prefix}gl_trans 
                  (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 0, 2, ?)";

        $stmt_gl = $conn->prepare($sql_gl);

        $memo_ = "فاتورة مبيعات رقم " . $trans_no . " - العملة: " . $main_currency . " - الموقع: " . $default_location . " - مركز التكلفة: " . $dimension_id . " - الضريبة: " . ($selected_tax_data ? $selected_tax_data['name'] . ' (' . $selected_tax_data['rate'] . '%)' : 'غير محدد');

        $type_int = (int)$type;
        $trans_no_int = (int)$trans_no;
        $receivable_account_str = (string)$receivable_account;
        $walkin_debtor_no_int = (int)$walkin_debtor_no;

        // 1. قيد المدين (مدين) - القيمة الإجمالية مع الضريبة
        $stmt_gl->bind_param("iisssdii", 
            $type_int, 
            $trans_no_int, 
            $tran_date, 
            $receivable_account_str, 
            $memo_, 
            $total_for_invoice, 
            $dimension_id,
            $walkin_debtor_no_int
        );

        if (!$stmt_gl->execute()) {
            throw new Exception("فشل في تسجيل القيد المحاسبي للذمم المدينة: " . $stmt_gl->error);
        }

        // 2. قيد المبيعات (دائن) - القيمة الصافية بدون ضريبة
        $sales_amount = -$net_sales_for_gl;
        $sales_account_str = (string)$sales_account;
        $stmt_gl->bind_param("iisssdii", 
            $type_int, 
            $trans_no_int, 
            $tran_date, 
            $sales_account_str, 
            $memo_, 
            $sales_amount, 
            $dimension_id,
            $walkin_debtor_no_int
        );

        if (!$stmt_gl->execute()) {
            throw new Exception("فشل في تسجيل القيد المحاسبي للمبيعات: " . $stmt_gl->error);
        }

        // 3. قيد الضريبة (دائن) - قيمة الضريبة فقط
        $tax_amount_negative = -$tax_amount_for_gl;
        $tax_account_str = $tax_sales_gl_code;
        $stmt_gl->bind_param("iisssdii", 
            $type_int, 
            $trans_no_int, 
            $tran_date, 
            $tax_account_str, 
            $memo_, 
            $tax_amount_negative, 
            $dimension_id,
            $walkin_debtor_no_int
        );

        if (!$stmt_gl->execute()) {
            throw new Exception("فشل في تسجيل القيد المحاسبي للضريبة: " . $stmt_gl->error);
        }

        // 4. قيود تكلفة المبيعات والمخزون (إذا كان هناك تكلفة)
        if ($total_cost > 0) {
            // تكلفة المبيعات (مدين)
            $cogs_account_str = (string)$cogs_account;
            $stmt_gl->bind_param("iisssdii", 
                $type_int, 
                $trans_no_int, 
                $tran_date, 
                $cogs_account_str, 
                $memo_, 
                $total_cost, 
                $dimension_id,
                $walkin_debtor_no_int
            );
            
            if (!$stmt_gl->execute()) {
                throw new Exception("فشل في تسجيل القيد المحاسبي لتكلفة المبيعات: " . $stmt_gl->error);
            }
            
            // المخزون (دائن)
            $inventory_amount = -$total_cost;
            $inventory_account_str = (string)$inventory_account;
            $stmt_gl->bind_param("iisssdii", 
                $type_int, 
                $trans_no_int, 
                $tran_date, 
                $inventory_account_str, 
                $memo_, 
                $inventory_amount, 
                $dimension_id,
                $walkin_debtor_no_int
            );
            
            if (!$stmt_gl->execute()) {
                throw new Exception("فشل في تسجيل القيد المحاسبي للمخزون: " . $stmt_gl->error);
            }
        }
        // #####################################################
        
        // إتمام المعاملة
        $conn->commit();
        $success = "تم حفظ الفاتورة بنجاح! رقم: $trans_no (طلب المبيعات: $order_no) - العملة: $main_currency - الموقع: $default_location - مركز التكلفة: $dimension_id - الضريبة: " . ($selected_tax_data ? $selected_tax_data['name'] . ' (' . $selected_tax_data['rate'] . '%)' : 'غير محدد') . " - الإجمالي: " . number_format($total_for_invoice, 2) . " ₪";
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
            'tax_name' => $selected_tax_data ? $selected_tax_data['name'] : 'غير محدد',
            'tax_rate' => $selected_tax_data ? $selected_tax_data['rate'] : 0
        ];
        $_SESSION['cart'] = [];
        
        // إعادة حساب المبالغ بعد تفريغ السلة
        $sub_total = 0;
        $tax_amount = 0;
        $total_with_tax = 0;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "خطأ في حفظ الفاتورة: " . $e->getMessage();
        error_log("خطأ في حفظ الفاتورة: " . $e->getMessage());
    }
}

// التعامل مع معالجة الدفع - الإصدار المصحح والمبسط
if (isset($_POST['process_payment'])) {
    if (!isset($_POST['paid_amount']) || !isset($_POST['invoice_total']) || !isset($_POST['invoice_no'])) {
        $error = "بيانات الدفع غير مكتملة!";
    } else {
        $paid_amount = floatval($_POST['paid_amount']);
        $invoice_total = floatval($_POST['invoice_total']);
        $invoice_no = intval($_POST['invoice_no']);
        
        if ($paid_amount <= 0) {
            $error = "المبلغ المدفوع يجب أن يكون أكبر من الصفر!";
        } elseif ($invoice_total <= 0) {
            $error = "إجمالي الفاتورة غير صالح!";
        } else {
            $change = $paid_amount - $invoice_total;
            
            if ($change < 0) {
                $error = "المبلغ المدفوع غير كافي. المطلوب: " . number_format($invoice_total, 2) . " ₪";
            } else {
                $payment_date = date('Y-m-d');
                $payment_type = ST_CUSTPAYMENT;
                
                $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = $payment_type");
                $row = $result->fetch_assoc();
                $payment_trans_no = $row['max_no'] + 1;
                
                $payment_ref = "PAY-" . $payment_trans_no;
                $dimension_id = $default_cost_center;
                
                try {
                    // بدء المعاملة
                    $conn->begin_transaction();

                    // ==============================================
                    // 1. إدخال حركة الدفع - بدون version
                    // ==============================================
                    // طريقة 1: التحقق من الحقول الموجودة أولاً
                    $sql_check_fields = "SHOW COLUMNS FROM {$prefix}debtor_trans";
                    $result_fields = $conn->query($sql_check_fields);
                    $existing_fields = [];
                    while ($field = $result_fields->fetch_assoc()) {
                        $existing_fields[] = $field['Field'];
                    }
                    
                    $fields_to_include = [];
                    $values_to_include = [];
                    
                    // الحقول الأساسية المطلوبة
                    $fields_to_include[] = 'trans_no';
                    $values_to_include[] = $payment_trans_no;
                    
                    $fields_to_include[] = 'type';
                    $values_to_include[] = $payment_type;
                    
                    // حذف version إذا لم يكن موجوداً
                    if (in_array('version', $existing_fields)) {
                        $fields_to_include[] = 'version';
                        $values_to_include[] = 0;
                    }
                    
                    $fields_to_include[] = 'debtor_no';
                    $values_to_include[] = $walkin_debtor_no;
                    
                    $fields_to_include[] = 'branch_code';
                    $values_to_include[] = $branch_code;
                    
                    $fields_to_include[] = 'tran_date';
                    $values_to_include[] = $payment_date;
                    
                    $fields_to_include[] = 'due_date';
                    $values_to_include[] = $payment_date;
                    
                    $fields_to_include[] = 'reference';
                    $values_to_include[] = $payment_ref;
                    
                    $fields_to_include[] = 'tpe';
                    $values_to_include[] = 0;
                    
                    $fields_to_include[] = 'ov_amount';
                    $values_to_include[] = $paid_amount;
                    
                    if (in_array('ov_discount', $existing_fields)) {
                        $fields_to_include[] = 'ov_discount';
                        $values_to_include[] = 0;
                    }
                    
                    $fields_to_include[] = 'alloc';
                    $values_to_include[] = $paid_amount;
                    
                    if (in_array('ship_via', $existing_fields)) {
                        $fields_to_include[] = 'ship_via';
                        $values_to_include[] = 0;
                    }
                    
                    if (in_array('payment_terms', $existing_fields)) {
                        $fields_to_include[] = 'payment_terms';
                        $values_to_include[] = 4;
                    }
                    
                    if (in_array('tax_included', $existing_fields)) {
                        $fields_to_include[] = 'tax_included';
                        $values_to_include[] = 0;
                    }
                    
                    if (in_array('dimension_id', $existing_fields)) {
                        $fields_to_include[] = 'dimension_id';
                        $values_to_include[] = $dimension_id;
                    }
                    
                    if (in_array('dimension2_id', $existing_fields)) {
                        $fields_to_include[] = 'dimension2_id';
                        $values_to_include[] = 0;
                    }
                    
                    // طريقة أبسط: استخدام SQL مباشر بدون prepared statement
                    $placeholders = [];
                    foreach ($values_to_include as $value) {
                        if (is_numeric($value) && !is_string($value)) {
                            $placeholders[] = $value;
                        } else if (is_int($value)) {
                            $placeholders[] = $value;
                        } else {
                            $placeholders[] = "'" . $conn->real_escape_string($value) . "'";
                        }
                    }
                    
                    $sql_payment = "INSERT INTO {$prefix}debtor_trans
                        (" . implode(', ', $fields_to_include) . ")
                        VALUES (" . implode(', ', $placeholders) . ")";
                    
                    if (!$conn->query($sql_payment)) {
                        throw new Exception("فشل في تسجيل الدفعة: " . $conn->error . 
                            "<br>SQL: " . $sql_payment);
                    }
                    
                    // ==============================================
                    // 2. تحديث الفاتورة الأصلية
                    // ==============================================
                    $sql_update = "UPDATE {$prefix}debtor_trans 
                                   SET alloc = $paid_amount 
                                   WHERE trans_no = $invoice_no AND type = " . ST_SALESINVOICE;
                    
                    if (!$conn->query($sql_update)) {
                        throw new Exception("فشل في تحديث الفاتورة: " . $conn->error);
                    }

                    // ==============================================
                    // 3. القيود المحاسبية للدفعة - استعلام مباشر
                    // ==============================================
                    $cash_account = "11010001";
                    $receivable_account = "11011001";
                    
                    // التحقق من حقل version في gl_trans
                    $sql_check_gl_fields = "SHOW COLUMNS FROM {$prefix}gl_trans LIKE 'version'";
                    $result_gl_check = $conn->query($sql_check_gl_fields);
                    $has_version_gl = $result_gl_check && $result_gl_check->num_rows > 0;
                    
                    $sql_check_order_gl = "SHOW COLUMNS FROM {$prefix}gl_trans LIKE 'order'";
                    $result_order_check = $conn->query($sql_check_order_gl);
                    $has_order_gl = $result_order_check && $result_order_check->num_rows > 0;
                    
                    // النقدية (مدين)
                    if ($has_version_gl && $has_order_gl) {
                        // الحصول على آخر order
                        $sql_last_order = "SELECT MAX(`order`) as last_order FROM {$prefix}gl_trans 
                                          WHERE type = $payment_type AND type_no = $payment_trans_no";
                        $result_order = $conn->query($sql_last_order);
                        $last_order = 0;
                        if ($result_order && $row_order = $result_order->fetch_assoc()) {
                            $last_order = $row_order['last_order'] ?? 0;
                        }
                        $order_counter = $last_order + 1;
                        
                        $memo_pay = "دفعة نقدية للفاتورة رقم " . $invoice_no;
                        
                        $sql_cash = "INSERT INTO {$prefix}gl_trans 
                                    (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id, version, `order`)
                                    VALUES ($payment_type, $payment_trans_no, '$payment_date', '$cash_account', '$memo_pay', 
                                    $paid_amount, $dimension_id, 0, 2, $walkin_debtor_no, 0, $order_counter)";
                        
                        if (!$conn->query($sql_cash)) {
                            throw new Exception("فشل في تسجيل قيد النقدية: " . $conn->error);
                        }
                        
                        $order_counter++;
                        
                        // الذمم المدينة (دائن)
                        $receivable_amount = -$paid_amount;
                        $sql_receivable = "INSERT INTO {$prefix}gl_trans 
                                          (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id, version, `order`)
                                          VALUES ($payment_type, $payment_trans_no, '$payment_date', '$receivable_account', '$memo_pay', 
                                          $receivable_amount, $dimension_id, 0, 2, $walkin_debtor_no, 0, $order_counter)";
                        
                    } else {
                        // بدون version و order
                        $memo_pay = "دفعة نقدية للفاتورة رقم " . $invoice_no;
                        
                        $sql_cash = "INSERT INTO {$prefix}gl_trans 
                                    (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                                    VALUES ($payment_type, $payment_trans_no, '$payment_date', '$cash_account', '$memo_pay', 
                                    $paid_amount, $dimension_id, 0, 2, $walkin_debtor_no)";
                        
                        if (!$conn->query($sql_cash)) {
                            throw new Exception("فشل في تسجيل قيد النقدية: " . $conn->error);
                        }
                        
                        // الذمم المدينة (دائن)
                        $receivable_amount = -$paid_amount;
                        $sql_receivable = "INSERT INTO {$prefix}gl_trans 
                                          (type, type_no, tran_date, account, memo_, amount, dimension_id, dimension2_id, person_type_id, person_id)
                                          VALUES ($payment_type, $payment_trans_no, '$payment_date', '$receivable_account', '$memo_pay', 
                                          $receivable_amount, $dimension_id, 0, 2, $walkin_debtor_no)";
                    }
                    
                    if (!$conn->query($sql_receivable)) {
                        throw new Exception("فشل في تسجيل قيد الذمم المدينة: " . $conn->error);
                    }
                    
                    // إتمام المعاملة
                    $conn->commit();
                    
                    $success_msg = "✅ تم استلام المبلغ بنجاح!<br>";
                    $success_msg .= "المبلغ المدفوع: <strong>" . number_format($paid_amount, 2) . " ₪</strong><br>";
                    if ($change > 0) {
                        $success_msg .= "الباقي: <strong style='color:green'>" . number_format($change, 2) . " ₪</strong><br>";
                    }
                    $success_msg .= "رقم القيد: <strong>$payment_trans_no</strong>";
                    
                    $success = $success_msg;
                    
                    $_SESSION['last_payment'] = [
                        'invoice_no' => $invoice_no,
                        'paid_amount' => $paid_amount,
                        'change' => $change,
                        'payment_no' => $payment_trans_no
                    ];
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "❌ خطأ في معالجة الدفع: " . $e->getMessage();
                    error_log("خطأ في معالجة الدفع: " . $e->getMessage());
                }
            }
        }
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
            padding: 20px;
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
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .panel-title i {
            font-size: 22px;
            color: var(--primary);
        }
        
        .input-group {
            display: flex;
            margin-bottom: 15px;
            gap: 8px;
        }
        
        .input-group input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background: var(--gray-light);
        }
        
        .input-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
            background: white;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
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
        
        .add-item-section {
            flex-grow: 0;
            height: auto;
            margin-bottom: 15px;
        }
        
        .add-item-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .add-item-form input[type="text"] {
            flex: 2;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            background: white;
        }
        
        .add-item-form input[type="number"] {
            width: 70px;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            background: white;
        }
        
        .add-item-form .btn {
            flex: 1;
            padding: 10px;
        }
        
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
            margin-bottom: 12px;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
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
            padding: 12px;
            text-align: center;
            position: sticky;
            top: 0;
        }
        
        .cart-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .cart-table tr:nth-child(even) {
            background: var(--gray-light);
        }
        
        .cart-table tr:hover {
            background: #e8f0fe;
        }
        
        .cart-table input {
            width: 80px;
            padding: 7px;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-align: center;
            font-size: 15px;
            background: white;
            transition: all 0.2s;
        }
        
        .cart-table input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        
        .remove-link {
            color: var(--danger);
            text-decoration: none;
            font-weight: bold;
            font-size: 18px;
            transition: color 0.3s;
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
        }
        
        .remove-link:hover {
            color: var(--danger-dark);
            background: rgba(234, 67, 53, 0.1);
        }
        
        .summary {
            background: #f0f7ff;
            border-radius: 10px;
            padding: 15px;
            margin-top: auto;
            border: 1px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 16px;
        }
        
        .summary-total {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary);
            border-top: 2px solid var(--border);
            padding-top: 12px;
            margin-top: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }
        
        .action-buttons button {
            flex: 1;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.05);
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
            padding: 30px 15px;
            color: var(--text-light);
            font-size: 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
        }
        
        .empty-cart i {
            font-size: 56px;
            margin-bottom: 15px;
            color: var(--border);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 15px;
        }
        
        .quick-actions .btn {
            font-size: 14px;
            padding: 10px 12px;
            white-space: normal;
            line-height: 1.4;
            text-align: center;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .quick-actions .btn i {
            margin-bottom: 5px;
            font-size: 18px;
        }
        
        .customer-info {
            background: var(--gray-light);
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            flex-grow: 1;
        }
        
        .customer-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 15px;
        }
        
        .customer-row span:first-child {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .total-amount {
            font-size: 22px;
            font-weight: bold;
            background: var(--primary);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            margin: 8px 0;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
        }
        
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
            padding: 25px;
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
            margin-bottom: 15px;
        }
        
        .modal-title {
            font-size: 22px;
            color: var(--primary);
        }
        
        .close-btn {
            font-size: 26px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .payment-info {
            margin: 15px 0;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 17px;
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
            padding: 12px;
            font-size: 20px;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            margin: 12px 0;
            background: var(--gray-light);
        }
        
        .payment-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }
        
        .change-display {
            background: var(--success-bg);
            padding: 12px;
            border-radius: 8px;
            font-size: 18px;
            text-align: center;
            font-weight: bold;
            margin: 12px 0;
            border: 1px solid var(--secondary);
        }
        
        .modal-print {
            display: none;
        }
        
        .modal-receipt {
            display: none;
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

        /* Stock List Modal specific styles */
        #stockListModal .stock-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 15px;
        }

        #stockListModal .stock-item {
            padding: 10px 15px;
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
            min-width: 80px;
        }

        #stockListModal .stock-item-desc {
            flex-grow: 1;
            text-align: right;
            margin: 0 10px;
        }

        #stockListModal .stock-item-price {
            color: var(--secondary-dark);
            font-weight: 600;
            min-width: 80px;
            text-align: left;
        }
        
        #stockListModal .stock-item-qty {
            color: var(--primary-dark);
            min-width: 50px;
            text-align: center;
        }
        
        .search-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        /* Print specific styles */
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
        
        /* Location selector styles */
        .location-selector {
            margin-bottom: 15px;
            background: var(--gray-light);
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .location-selector label {
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
        }
        
        .location-selector select {
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: white;
            flex-grow: 1;
        }
        
        .location-selector button {
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .location-selector button:hover {
            background: var(--primary-dark);
        }
        
        /* Cost center selector styles */
        .cost-center-selector {
            margin-bottom: 15px;
            background: var(--gray-light);
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cost-center-selector label {
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
        }
        
        .cost-center-selector select {
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: white;
            flex-grow: 1;
        }
        
        /* Tax selector styles */
        .tax-selector {
            margin-bottom: 15px;
            background: var(--gray-light);
            padding: 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tax-selector label {
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
        }
        
        .tax-selector select {
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            background: white;
            flex-grow: 1;
        }
        
        @media (max-width: 1200px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .quick-actions .btn {
                min-height: 50px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 992px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            header {
                flex-direction: column;
                gap: 12px;
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
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .panel {
                height: auto;
                min-height: 400px;
            }
            
            .cart-table {
                max-height: 300px;
            }
            
            .location-selector, .cost-center-selector, .tax-selector {
                flex-direction: column;
                align-items: stretch;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .btn {
                padding: 10px 15px;
                font-size: 14px;
            }
            
            .panel-title {
                font-size: 18px;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .input-group input {
                width: 100%;
            }
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
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= $error ?></div>
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
                    <h2 class="panel-title"><i class="fas fa-user"></i> معلومات الزبون</h2>
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
                        <span>نوع الضريبة:</span>
                        <span>
                            <?= $selected_tax_name ?>
                        </span>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button class="btn btn-warning" onclick="printInvoice('a4')">
                        <i class="fas fa-print"></i> طباعة الفاتورة (A4) <br> (F10)
                    </button>
                    <button class="btn btn-danger" onclick="cancelInvoice()">
                        <i class="fas fa-trash-alt"></i> إلغاء الفاتورة
                    </button>
                    <button class="btn btn-success" onclick="openPaymentModal()">
                        <i class="fas fa-money-bill-wave"></i> الدفع (F1)
                    </button>
                    <button class="btn btn-primary" onclick="printInvoice('receipt')">
                        <i class="fas fa-receipt"></i> طباعة إيصال <br> (F9)
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
                const taxAmount = subTotal * currentTaxRate;
                total = subTotal + taxAmount;
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
            let taxType = '<?= $selected_tax_name ?>';
            let taxRate = currentTaxRate;
            
            if (cartItems.length > 0) {
                items = cartItems;
                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    subTotal += item.unit_price * item.qty * (1 - itemDiscountDecimal);
                });
                taxAmount = subTotal * taxRate;
                totalWithTax = subTotal + taxAmount;
                invoiceNo = 'مؤقتة';
            } else if (lastInvoice && lastInvoice.items) {
                items = lastInvoice.items;
                subTotal = lastInvoice.sub_total || 0;
                taxAmount = lastInvoice.tax_amount || 0;
                totalWithTax = lastInvoice.total;
                invoiceNo = lastInvoice.trans_no;
                costCenter = lastInvoice.dimension_id ? 'مركز التكلفة: ' + lastInvoice.dimension_id : costCenter;
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