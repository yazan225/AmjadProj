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

$host = 'localhost';
$user = '2Gusrgx123';
$pass = 'PP@!pswgh_11';
$dbname = 'ghusn_co_fa2418';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<h2 style='color:red'>فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</h2>");
}
$conn->set_charset("utf8");

// الحصول على معدل الضريبة من جدول tax_types
$tax_rate = 0.05;
$tax_account = "21040001";

$sql_tax = "SELECT rate, sales_gl_code, name FROM {$prefix}tax_types WHERE inactive = 0 ORDER BY id LIMIT 1";
$result_tax = $conn->query($sql_tax);
if ($result_tax && $result_tax->num_rows > 0) {
    $tax_row = $result_tax->fetch_assoc();
    $tax_rate = $tax_row['rate'] / 100;
    $tax_account = $tax_row['sales_gl_code'];
    $tax_name = $tax_row['name'];
} else {
    $tax_rate = 0.05;
    $tax_account = "21040001";
    $tax_name = "ضريبة";
}

// الحصول على جميع مراكز التكلفة
$cost_centers = [];
$sql_cost_centers = "SELECT id, reference, name, type_, closed FROM {$prefix}dimensions WHERE closed = 0 ORDER BY name";
$result_cost_centers = $conn->query($sql_cost_centers);

if ($result_cost_centers) {
    if ($result_cost_centers->num_rows > 0) {
        while ($row = $result_cost_centers->fetch_assoc()) {
            $cost_centers[] = $row;
        }
    }
}

if (!isset($_SESSION['selected_cost_center'])) {
    if (count($cost_centers) > 0) {
        $_SESSION['selected_cost_center'] = $cost_centers[0]['id'];
    } else {
        $_SESSION['selected_cost_center'] = 0;
    }
}

if (isset($_POST['change_cost_center'])) {
    $_SESSION['selected_cost_center'] = $_POST['cost_center'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$default_cost_center = $_SESSION['selected_cost_center'] ?? 0;

// الحصول على جميع المخازن
$locations = [];
$sql_locations = "SELECT loc_code, location_name FROM {$prefix}locations ORDER BY loc_code";
$result_locations = $conn->query($sql_locations);
if ($result_locations && $result_locations->num_rows > 0) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
}

if (!isset($_SESSION['selected_location']) && count($locations) > 0) {
    $_SESSION['selected_location'] = $locations[0]['loc_code'];
}

if (isset($_POST['change_location'])) {
    $_SESSION['selected_location'] = $_POST['location'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$default_location = $_SESSION['selected_location'] ?? 'DEF';

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

$all_stock_items = getStockItems($conn, $prefix, $default_location);

if (isset($_GET['get_updated_stock'])) {
    header('Content-Type: application/json');
    echo json_encode(getStockItems($conn, $prefix, $default_location));
    exit;
}

function startNewInvoice() {
    unset($_SESSION['last_invoice']);
    unset($_SESSION['last_payment']);
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    startNewInvoice();
}

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
        }
        $stmt->close();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

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

if (isset($_GET['cancel_invoice'])) {
    $_SESSION['cart'] = [];
    startNewInvoice();
    $success = "تم إلغاء الفاتورة بنجاح";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ==============================================
// الحل النهائي لحفظ الفاتورة - الإصدار المصحح
// ==============================================
if (isset($_POST['save_invoice']) && count($_SESSION['cart']) > 0) {
    try {
        startNewInvoice();
        
        $tran_date = date('Y-m-d');
        
        // الحصول على رقم الحركة التالي
        $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = " . ST_SALESINVOICE);
        if (!$result) {
            throw new Exception("فشل في الحصول على رقم الحركة: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $trans_no = $row['max_no'] + 1;
        
        $reference = "INV-" . $trans_no;
        $type = ST_SALESINVOICE;
        
        // حساب المجاميع
        $sub_total = 0;
        $total_cost = 0;
        
        foreach ($_SESSION['cart'] as $item) {
            $item_discount_decimal = $item['discount'] / 100;
            $line_total = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
            $sub_total += $line_total;
            $total_cost += $item['standard_cost'] * $item['qty'];
        }
        $total = $sub_total;
        
        // حساب الضريبة والمبالغ الصافية
        $net_amount = $total / (1 + $tax_rate);
        $tax_amount = $net_amount * $tax_rate;
        
        // بدء المعاملة
        $conn->begin_transaction();

        // ==============================================
        // 1. إدخال حركة المدين - الطريقة الآمنة
        // ==============================================
        // طريقة 1: استخدام استعلام مباشر
        $sql_debtor = "INSERT INTO {$prefix}debtor_trans
            (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, 
            reference, tpe, order_, ov_amount, ov_discount, alloc, ship_via, payment_terms, tax_included, dimension_id, dimension2_id)
            VALUES ($trans_no, $type, 1, $walkin_debtor_no, '$branch_code', '$tran_date', '$tran_date', 
            '$reference', 1, 0, $total, 0, 0, 1, 4, 1, $default_cost_center, 0)";
        
        // طريقة 2: استخدام prepared statement بطريقة أبسط
        $sql_debtor = "INSERT INTO {$prefix}debtor_trans
            (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, 
            reference, tpe, order_, ov_amount, ov_discount, alloc, ship_via, payment_terms, tax_included, dimension_id, dimension2_id)
            VALUES (?, ?, 1, ?, ?, ?, ?, ?, 1, 0, ?, 0, 0, 1, 4, 1, ?, 0)";
        
        $stmt_debtor = $conn->prepare($sql_debtor);
        
        // التحقق من وجود خطأ في التحضير
        if (!$stmt_debtor) {
            throw new Exception("فشل في تحضير الاستعلام: " . $conn->error);
        }
        
        // استخدام bind_param بطريقة أبسط
        $stmt_debtor->bind_param("iiissssdi", 
            $trans_no,              // trans_no
            $type,                  // type
            $walkin_debtor_no,      // debtor_no
            $branch_code,           // branch_code
            $tran_date,             // tran_date
            $tran_date,             // due_date
            $reference,             // reference
            $total,                 // ov_amount
            $default_cost_center    // dimension_id
        );

        if (!$stmt_debtor->execute()) {
            throw new Exception("فشل في حفظ الفاتورة (debtor_trans): " . $stmt_debtor->error . 
                "<br>SQL: " . $sql_debtor .
                "<br>Params: trans_no=$trans_no, type=$type, debtor_no=$walkin_debtor_no, branch_code=$branch_code, " .
                "tran_date=$tran_date, due_date=$tran_date, reference=$reference, total=$total, cost_center=$default_cost_center");
        }

        // ==============================================
        // 2. إدخال الأصناف
        // ==============================================
        foreach ($_SESSION['cart'] as $item) {
            $sql_line = "INSERT INTO {$prefix}debtor_trans_details
                (debtor_trans_no, debtor_trans_type, stock_id, description, unit_price, quantity, discount_percent, standard_cost, qty_done)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_line = $conn->prepare($sql_line);
            
            if (!$stmt_line) {
                throw new Exception("فشل في تحضير استعلام تفاصيل الفاتورة: " . $conn->error);
            }
            
            $discount_percent = $item['discount'] / 100;
            
            $stmt_line->bind_param("iissddidi",
                $trans_no,
                $type,
                $item['stock_id'],
                $item['description'],
                $item['unit_price'],
                $item['qty'],
                $discount_percent,
                $item['standard_cost'],
                $item['qty']
            );
            
            if (!$stmt_line->execute()) {
                throw new Exception("فشل في حفظ تفاصيل الفاتورة: " . $stmt_line->error);
            }
            
            // ==============================================
            // 3. حركة المخزون
            // ==============================================
            $sql_stock_move = "INSERT INTO {$prefix}stock_moves
                (trans_no, type, stock_id, loc_code, tran_date, price, reference, qty, standard_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_stock_move = $conn->prepare($sql_stock_move);
            
            if (!$stmt_stock_move) {
                throw new Exception("فشل في تحضير استعلام حركة المخزون: " . $conn->error);
            }
            
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
            
            if (!$stmt_stock_move->execute()) {
                throw new Exception("فشل في تحديث حركة المخزون: " . $stmt_stock_move->error);
            }
        }

        // ==============================================
        // 4. القيود المحاسبية
        // ==============================================
        $receivable_account = "11011001";
        $sales_account = "41010001";
        $cogs_account = "50010001";
        $inventory_account = "14010001";

        // إدخال القيود المحاسبية بطريقة مبسطة
        $sql_gl = "INSERT INTO {$prefix}gl_trans 
                  (type, type_no, tran_date, account, memo_, amount, dimension_id, person_type_id, person_id, version)
                  VALUES (?, ?, ?, ?, ?, ?, ?, 2, ?, 1)";

        $stmt_gl = $conn->prepare($sql_gl);
        
        if (!$stmt_gl) {
            throw new Exception("فشل في تحضير استعلام القيود المحاسبية: " . $conn->error);
        }
        
        $memo_ = "فاتورة مبيعات رقم " . $trans_no;

        // 1. الذمم المدينة (مدين)
        $stmt_gl->bind_param("iisssdi", 
            $type,
            $trans_no,
            $tran_date,
            $receivable_account,
            $memo_,
            $total,
            $default_cost_center,
            $walkin_debtor_no
        );

        if (!$stmt_gl->execute()) {
            throw new Exception("فشل في تسجيل قيد الذمم المدينة: " . $stmt_gl->error);
        }

        // 2. المبيعات (دائن)
        $sales_amount = -$net_amount;
        $stmt_gl->bind_param("iisssdi", 
            $type,
            $trans_no,
            $tran_date,
            $sales_account,
            $memo_,
            $sales_amount,
            $default_cost_center,
            $walkin_debtor_no
        );

        if (!$stmt_gl->execute()) {
            throw new Exception("فشل في تسجيل قيد المبيعات: " . $stmt_gl->error);
        }

        // 3. الضريبة (دائن)
        $tax_amount_negative = -$tax_amount;
        $stmt_gl->bind_param("iisssdi", 
            $type,
            $trans_no,
            $tran_date,
            $tax_account,
            $memo_,
            $tax_amount_negative,
            $default_cost_center,
            $walkin_debtor_no
        );

        if (!$stmt_gl->execute()) {
            throw new Exception("فشل في تسجيل قيد الضريبة: " . $stmt_gl->error);
        }

        // تكلفة المبيعات إذا كانت هناك تكلفة
        if ($total_cost > 0) {
            // 4. تكلفة المبيعات (مدين)
            $stmt_gl->bind_param("iisssdi", 
                $type,
                $trans_no,
                $tran_date,
                $cogs_account,
                $memo_,
                $total_cost,
                $default_cost_center,
                $walkin_debtor_no
            );
            
            if (!$stmt_gl->execute()) {
                throw new Exception("فشل في تسجيل قيد تكلفة المبيعات: " . $stmt_gl->error);
            }
            
            // 5. المخزون (دائن)
            $inventory_amount = -$total_cost;
            $stmt_gl->bind_param("iisssdi", 
                $type,
                $trans_no,
                $tran_date,
                $inventory_account,
                $memo_,
                $inventory_amount,
                $default_cost_center,
                $walkin_debtor_no
            );
            
            if (!$stmt_gl->execute()) {
                throw new Exception("فشل في تسجيل قيد المخزون: " . $stmt_gl->error);
            }
        }

        // إتمام المعاملة
        $conn->commit();
        $tax_percent = $tax_rate * 100;
        $success = "✅ تم حفظ الفاتورة بنجاح!<br>رقم الفاتورة: <strong>$trans_no</strong><br>المبلغ الإجمالي: <strong>" . number_format($total, 2) . " ₪</strong><br>الضريبة: <strong>$tax_percent%</strong>";
        
        $_SESSION['last_invoice'] = [
            'trans_no' => $trans_no,
            'total' => $total,
            'tax_rate' => $tax_percent,
            'tax_amount' => $tax_amount,
            'net_amount' => $net_amount,
            'items' => $_SESSION['cart']
        ];
        $_SESSION['cart'] = [];
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "❌ خطأ في حفظ الفاتورة: " . $e->getMessage();
    }
}

// ==============================================
// الحل النهائي لمعالجة الدفع - مع تصحيح الأخطاء
// ==============================================
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
                
                // الحصول على رقم الحركة التالي للدفع
                $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = $payment_type");
                $row = $result->fetch_assoc();
                $payment_trans_no = $row['max_no'] + 1;
                
                $payment_ref = "PAY-" . $payment_trans_no;
                
                try {
                    // بدء المعاملة
                    $conn->begin_transaction();

                    // ==============================================
                    // 1. إدخال حركة الدفع - الطريقة الآمنة
                    // ==============================================
                    $sql_payment = "INSERT INTO {$prefix}debtor_trans
                        (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, 
                        reference, tpe, order_, ov_amount, ov_discount, alloc, ship_via, payment_terms, tax_included, dimension_id, dimension2_id)
                        VALUES (?, ?, 1, ?, ?, ?, ?, ?, 1, ?, ?, 0, ?, 1, 4, 1, ?, 0)";
                    
                    $stmt_payment = $conn->prepare($sql_payment);
                    
                    if (!$stmt_payment) {
                        throw new Exception("فشل في تحضير استعلام الدفع: " . $conn->error);
                    }
                    
                    // استخدام bind_param بطريقة أبسط
                    $stmt_payment->bind_param("iiisssssiddi", 
                        $payment_trans_no,   // trans_no
                        $payment_type,       // type
                        $walkin_debtor_no,   // debtor_no
                        $branch_code,        // branch_code
                        $payment_date,       // tran_date
                        $payment_date,       // due_date
                        $payment_ref,        // reference
                        $invoice_no,         // order_ = رقم الفاتورة الأصلية
                        $paid_amount,        // ov_amount
                        $paid_amount,        // alloc
                        $default_cost_center // dimension_id
                    );
                    
                    if (!$stmt_payment->execute()) {
                        throw new Exception("فشل في تسجيل الدفعة: " . $stmt_payment->error . 
                            "<br>SQL: " . $sql_payment .
                            "<br>Params: payment_trans_no=$payment_trans_no, type=$payment_type, debtor_no=$walkin_debtor_no, " .
                            "branch_code=$branch_code, payment_date=$payment_date, reference=$payment_ref, " .
                            "order_=$invoice_no, ov_amount=$paid_amount, alloc=$paid_amount, cost_center=$default_cost_center");
                    }
                    
                    // ==============================================
                    // 2. تحديث الفاتورة الأصلية
                    // ==============================================
                    $sql_update = "UPDATE {$prefix}debtor_trans 
                                   SET alloc = ? 
                                   WHERE trans_no = ? AND type = " . ST_SALESINVOICE;
                    $stmt_update = $conn->prepare($sql_update);
                    
                    if (!$stmt_update) {
                        throw new Exception("فشل في تحضير استعلام تحديث الفاتورة: " . $conn->error);
                    }
                    
                    $stmt_update->bind_param("di", $paid_amount, $invoice_no);
                    
                    if (!$stmt_update->execute()) {
                        throw new Exception("فشل في تحديث الفاتورة: " . $stmt_update->error);
                    }

                    // ==============================================
                    // 3. القيود المحاسبية للدفعة
                    // ==============================================
                    $cash_account = "11010001";
                    $receivable_account = "11011001";
                    
                    $sql_gl_pay = "INSERT INTO {$prefix}gl_trans 
                                  (type, type_no, tran_date, account, memo_, amount, dimension_id, person_type_id, person_id, version)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 2, ?, 1)";

                    $stmt_gl_pay = $conn->prepare($sql_gl_pay);
                    
                    if (!$stmt_gl_pay) {
                        throw new Exception("فشل في تحضير استعلام قيود الدفع: " . $conn->error);
                    }
                    
                    $memo_pay = "دفعة نقدية للفاتورة رقم " . $invoice_no;

                    // 1. النقدية (مدين)
                    $stmt_gl_pay->bind_param("iisssdi", 
                        $payment_type,
                        $payment_trans_no,
                        $payment_date,
                        $cash_account,
                        $memo_pay,
                        $paid_amount,
                        $default_cost_center,
                        $walkin_debtor_no
                    );
                    
                    if (!$stmt_gl_pay->execute()) {
                        throw new Exception("فشل في تسجيل قيد النقدية: " . $stmt_gl_pay->error);
                    }
                    
                    // 2. الذمم المدينة (دائن)
                    $receivable_amount = -$paid_amount;
                    $stmt_gl_pay->bind_param("iisssdi", 
                        $payment_type,
                        $payment_trans_no,
                        $payment_date,
                        $receivable_account,
                        $memo_pay,
                        $receivable_amount,
                        $default_cost_center,
                        $walkin_debtor_no
                    );
                    
                    if (!$stmt_gl_pay->execute()) {
                        throw new Exception("فشل في تسجيل قيد الذمم المدينة: " . $stmt_gl_pay->error);
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
        $selected_cost_center_name = $cc['name'] . " (" . $cc['reference'] . ")";
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

        /* Cost Center selector styles */
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
        
        .cost-center-selector button {
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .cost-center-selector button:hover {
            background: var(--primary-dark);
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
            
            .location-selector, .cost-center-selector {
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
                
                <form method="post" class="location-selector">
                    <label for="location">المخزن:</label>
                    <select name="location" id="location">
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['loc_code'] ?>" <?= $loc['loc_code'] == $default_location ? 'selected' : '' ?>>
                                <?= $loc['location_name'] ?> (<?= $loc['loc_code'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="change_location">تغيير</button>
                </form>

                <form method="post" class="cost-center-selector">
                    <label for="cost_center">مركز التكلفة:</label>
                    <select name="cost_center" id="cost_center">
                        <?php if (count($cost_centers) > 0): ?>
                            <?php foreach ($cost_centers as $cc): ?>
                                <option value="<?= $cc['id'] ?>" <?= $cc['id'] == $default_cost_center ? 'selected' : '' ?>>
                                    <?= $cc['name'] ?> (<?= $cc['reference'] ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="0">لا توجد مراكز تكلفة</option>
                        <?php endif; ?>
                    </select>
                    <button type="submit" name="change_cost_center">تغيير</button>
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
                        <span><?= $selected_location_name ?></span>
                    </div>
                    <div class="customer-row">
                        <span>مركز التكلفة المحدد:</span>
                        <span><?= $selected_cost_center_name ?></span>
                    </div>
                    <div class="customer-row">
                        <span>معدل الضريبة:</span>
                        <span><?= ($tax_rate * 100) ?>% (<?= $tax_name ?>)</span>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button class="btn btn-warning" onclick="printInvoice('a4')">
                        <i class="fas fa-print"></i> طباعة الفاتورة (A4) <br> (Ctrl+0)
                    </button>
                    <button class="btn btn-danger" onclick="cancelInvoice()">
                        <i class="fas fa-trash-alt"></i> إلغاء الفاتورة
                    </button>
                    <button class="btn btn-success" onclick="openPaymentModal()">
                        <i class="fas fa-money-bill-wave"></i> الدفع (Ctrl+1)
                    </button>
                    <button class="btn btn-primary" onclick="printInvoice('receipt')">
                        <i class="fas fa-receipt"></i> طباعة إيصال <br> (Ctrl+9)
                    </button>
                </div>
            </div>
            
            <div class="panel">
                <div class="cart-container">
                    <div class="cart-header">
                        <h2 class="panel-title"><i class="fas fa-shopping-cart"></i> سلة المشتريات</h2>
                        <div class="total-amount">
                            <?php 
                                $sub_total = 0; 
                                foreach ($_SESSION['cart'] as $item) {
                                    $item_discount_decimal = $item['discount'] / 100;
                                    $subtotal = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
                                    $sub_total += $subtotal;
                                }
                                $total = $sub_total;
                                echo number_format($total, 2) . ' ₪';
                            ?>
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
                                    <?php $sub_total = 0; foreach ($_SESSION['cart'] as $item):
                                        $item_discount_decimal = $item['discount'] / 100;
                                        $subtotal = $item['unit_price'] * $item['qty'] * (1 - $item_discount_decimal);
                                        $sub_total += $subtotal;
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
                                <div class="summary-row">
                                    <span>المجموع الفرعي:</span>
                                    <span><?= number_format($sub_total, 2) ?> ₪</span>
                                </div>
                                
                                <div class="summary-row summary-total">
                                    <span>الإجمالي:</span>
                                    <span><?= number_format($sub_total, 2) ?> ₪</span>
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
                            <p>(اضغط F2 أو Ctrl+2 لعرض قائمة الأصناف)</p>
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

    <script>
        let allStockItems = <?= json_encode($all_stock_items) ?>;
        let taxRate = <?= $tax_rate ?>;

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
            // استخدام Ctrl + رقم
            if (event.ctrlKey) {
                switch(event.key) {
                    case '1':
                        event.preventDefault();
                        openPaymentModal();
                        break;
                    case '2':
                        event.preventDefault();
                        openStockListModal();
                        break;
                    case '9':
                        event.preventDefault();
                        printInvoice('receipt');
                        break;
                    case '0':
                        event.preventDefault();
                        printInvoice('a4');
                        break;
                }
            }
            
            // F2 لا يزال يعمل
            if (event.key === 'F2') {
                event.preventDefault();
                openStockListModal();
            }
            
            // Escape لإلغاء الفاتورة
            if (event.key === 'Escape') {
                if (confirm('هل تريد إلغاء الفاتورة الحالية؟')) {
                    cancelInvoice();
                }
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
                total = subTotal;
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
            let total = 0;
            let invoiceNo = '';
            let paid = 0;
            let change = 0;
            let subTotal = 0;
            let taxAmount = 0;
            let netAmount = 0;
            
            if (cartItems.length > 0) {
                items = cartItems;
                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    subTotal += item.unit_price * item.qty * (1 - itemDiscountDecimal);
                });
                total = subTotal;
                netAmount = total / (1 + taxRate);
                taxAmount = netAmount * taxRate;
                invoiceNo = 'مؤقتة';
            } else if (lastInvoice && lastInvoice.items) {
                items = lastInvoice.items;
                subTotal = 0;
                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    subTotal += item.unit_price * item.qty * (1 - itemDiscountDecimal);
                });
                total = lastInvoice.total;
                netAmount = lastInvoice.net_amount || (total / (1 + taxRate));
                taxAmount = lastInvoice.tax_amount || (netAmount * taxRate);
                invoiceNo = lastInvoice.trans_no;
                
                if (lastPayment && lastPayment.invoice_no == invoiceNo) {
                    paid = lastPayment.paid_amount;
                    change = lastPayment.change;
                }
            } else {
                alert('لا توجد فاتورة للطباعة! أضف أصنافاً أو احفظ الفاتورة أولاً.');
                return;
            }
            
            if (type === 'a4') {
                // طباعة A4 - فاتورة كاملة
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                
                let content = `
                    <!DOCTYPE html>
                    <html dir="rtl" lang="ar">
                    <head>
                        <meta charset="UTF-8">
                        <title>فاتورة مبيعات - ${invoiceNo}</title>
                        <style>
                            * {
                                margin: 0;
                                padding: 0;
                                box-sizing: border-box;
                                font-family: 'Arial', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            }
                            body {
                                padding: 20px;
                                background: white;
                                color: #333;
                                line-height: 1.6;
                            }
                            .invoice-container {
                                max-width: 800px;
                                margin: 0 auto;
                                background: white;
                                padding: 30px;
                                border: 2px solid #1a73e8;
                                border-radius: 15px;
                                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                            }
                            .header {
                                text-align: center;
                                margin-bottom: 30px;
                                padding-bottom: 20px;
                                border-bottom: 3px double #1a73e8;
                            }
                            .company-name {
                                font-size: 32px;
                                font-weight: bold;
                                color: #1a73e8;
                                margin-bottom: 10px;
                            }
                            .invoice-title {
                                font-size: 28px;
                                color: #2c3e50;
                                margin-bottom: 5px;
                            }
                            .invoice-subtitle {
                                font-size: 18px;
                                color: #7f8c8d;
                                margin-bottom: 15px;
                            }
                            .details-grid {
                                display: grid;
                                grid-template-columns: 1fr 1fr;
                                gap: 20px;
                                margin-bottom: 25px;
                                background: #f8f9fa;
                                padding: 20px;
                                border-radius: 10px;
                                border: 1px solid #e9ecef;
                            }
                            .detail-item {
                                margin-bottom: 8px;
                            }
                            .detail-label {
                                font-weight: bold;
                                color: #2c3e50;
                                display: inline-block;
                                width: 120px;
                            }
                            .detail-value {
                                color: #34495e;
                            }
                            .items-table {
                                width: 100%;
                                border-collapse: collapse;
                                margin-bottom: 25px;
                                background: white;
                                border-radius: 8px;
                                overflow: hidden;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                            }
                            .items-table th {
                                background: linear-gradient(135deg, #1a73e8, #0d62c9);
                                color: white;
                                padding: 15px 10px;
                                text-align: center;
                                font-weight: 600;
                                font-size: 16px;
                            }
                            .items-table td {
                                padding: 12px 10px;
                                text-align: center;
                                border-bottom: 1px solid #e9ecef;
                            }
                            .items-table tr:nth-child(even) {
                                background: #f8f9fa;
                            }
                            .items-table tr:hover {
                                background: #e3f2fd;
                            }
                            .summary-section {
                                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                                padding: 25px;
                                border-radius: 10px;
                                margin-bottom: 25px;
                                border: 1px solid #dee2e6;
                            }
                            .summary-row {
                                display: flex;
                                justify-content: space-between;
                                padding: 10px 0;
                                font-size: 17px;
                                border-bottom: 1px dashed #ced4da;
                            }
                            .summary-row:last-child {
                                border-bottom: none;
                            }
                            .summary-label {
                                font-weight: 600;
                                color: #2c3e50;
                            }
                            .summary-value {
                                font-weight: 600;
                                color: #1a73e8;
                            }
                            .total-section {
                                background: linear-gradient(135deg, #1a73e8, #0d62c9);
                                color: white;
                                padding: 20px;
                                border-radius: 10px;
                                text-align: center;
                                margin-bottom: 25px;
                            }
                            .total-amount {
                                font-size: 32px;
                                font-weight: bold;
                                margin: 10px 0;
                            }
                            .footer {
                                text-align: center;
                                margin-top: 30px;
                                padding-top: 20px;
                                border-top: 2px dashed #bdc3c7;
                                color: #7f8c8d;
                                font-size: 14px;
                            }
                            .footer p {
                                margin: 5px 0;
                            }
                            .action-buttons {
                                text-align: center;
                                margin-top: 20px;
                            }
                            .print-btn, .close-btn {
                                padding: 12px 25px;
                                border: none;
                                border-radius: 8px;
                                font-size: 16px;
                                font-weight: 600;
                                cursor: pointer;
                                margin: 0 10px;
                                transition: all 0.3s ease;
                            }
                            .print-btn {
                                background: #34a853;
                                color: white;
                            }
                            .print-btn:hover {
                                background: #2a9849;
                                transform: translateY(-2px);
                            }
                            .close-btn {
                                background: #ea4335;
                                color: white;
                            }
                            .close-btn:hover {
                                background: #d93025;
                                transform: translateY(-2px);
                            }
                            @media print {
                                .action-buttons { display: none; }
                                body { margin: 0; padding: 15px; }
                                .invoice-container { border: none; box-shadow: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="invoice-container">
                            <div class="header">
                                <div class="company-name"><?= $company_name ?></div>
                                <h1 class="invoice-title">فاتورة مبيعات</h1>
                                <div class="invoice-subtitle">نظام نقطة البيع المتقدم</div>
                            </div>
                            
                            <div class="details-grid">
                                <div>
                                    <div class="detail-item">
                                        <span class="detail-label">رقم الفاتورة:</span>
                                        <span class="detail-value">${invoiceNo}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">التاريخ:</span>
                                        <span class="detail-value">${new Date().toLocaleString('ar-EG')}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">الوقت:</span>
                                        <span class="detail-value">${new Date().toLocaleTimeString('ar-EG')}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="detail-item">
                                        <span class="detail-label">الزبون:</span>
                                        <span class="detail-value">Walk-In Customer</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">رقم الزبون:</span>
                                        <span class="detail-value"><?= $walkin_debtor_no ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">المخزن:</span>
                                        <span class="detail-value"><?= $selected_location_name ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">مركز التكلفة:</span>
                                        <span class="detail-value"><?= $selected_cost_center_name ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th width="12%">رقم الصنف</th>
                                        <th width="30%">الوصف</th>
                                        <th width="10%">الكمية</th>
                                        <th width="12%">السعر</th>
                                        <th width="10%">الخصم %</th>
                                        <th width="16%">الإجمالي</th>
                                        <th width="10%">العملة</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    const subtotal = item.unit_price * item.qty * (1 - itemDiscountDecimal);
                    const currencySymbol = item.currency === 'USD' ? '$' : '₪';
                    
                    content += `
                                    <tr>
                                        <td>${item.stock_id}</td>
                                        <td>${item.description}</td>
                                        <td>${item.qty}</td>
                                        <td>${item.unit_price.toFixed(2)}</td>
                                        <td>${item.discount}%</td>
                                        <td>${subtotal.toFixed(2)}</td>
                                        <td>${currencySymbol}</td>
                                    </tr>
                    `;
                });
                
                content += `
                                </tbody>
                            </table>
                            
                            <div class="summary-section">
                                <div class="summary-row">
                                    <span class="summary-label">المجموع الفرعي:</span>
                                    <span class="summary-value">${subTotal.toFixed(2)} ₪</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">صافي المبيعات:</span>
                                    <span class="summary-value">${netAmount.toFixed(2)} ₪</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">الضريبة (${(taxRate * 100).toFixed(0)}%):</span>
                                    <span class="summary-value">${taxAmount.toFixed(2)} ₪</span>
                                </div>
                `;
                
                if (paid > 0) {
                    content += `
                                <div class="summary-row">
                                    <span class="summary-label">المبلغ المدفوع:</span>
                                    <span class="summary-value" style="color: #34a853;">${paid.toFixed(2)} ₪</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">الباقي:</span>
                                    <span class="summary-value" style="color: #fbbc05;">${change.toFixed(2)} ₪</span>
                                </div>
                    `;
                }
                
                content += `
                            </div>
                            
                            <div class="total-section">
                                <div style="font-size: 18px; margin-bottom: 5px;">المجموع الكلي</div>
                                <div class="total-amount">${total.toFixed(2)} ₪</div>
                                <div style="font-size: 16px; opacity: 0.9;">${total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")} ريال سعودي</div>
                            </div>
                            
                            <div class="footer">
                                <p>شكراً لتعاملكم معنا - نعتز بثقتكم</p>
                                <p>للاستفسار: 0501234567 | البريد الإلكتروني: info@company.com</p>
                                <p>www.company.com</p>
                                <p>هذه الفاتورة صادرة من نظام نقطة البيع المتقدم</p>
                            </div>
                            
                            <div class="action-buttons">
                                <button class="print-btn" onclick="window.print()">
                                    <i class="fas fa-print"></i> طباعة الفاتورة
                                </button>
                                <button class="close-btn" onclick="window.close()">
                                    <i class="fas fa-times"></i> إغلاق النافذة
                                </button>
                            </div>
                        </div>
                        
                        <script>
                            // طباعة تلقائية بعد تحميل الصفحة
                            window.onload = function() {
                                setTimeout(function() {
                                    window.print();
                                }, 1000);
                            }
                        <\/script>
                    </body>
                    </html>
                `;
                
                printWindow.document.write(content);
                printWindow.document.close();
                
            } else if (type === 'receipt') {
                // طباعة إيصال حراري - مصمم بشكل احترافي
                const printWindow = window.open('', '_blank', 'width=320,height=500');
                
                let receiptContent = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>إيصال مبيعات</title>
                        <meta charset="UTF-8">
                        <style>
                            * {
                                margin: 0;
                                padding: 0;
                                box-sizing: border-box;
                                font-family: 'Courier New', Courier, monospace;
                            }
                            body {
                                width: 80mm;
                                min-height: 100vh;
                                padding: 10px 5px;
                                background: white;
                                font-size: 14px;
                                line-height: 1.3;
                            }
                            .receipt-container {
                                width: 100%;
                                text-align: center;
                            }
                            .header {
                                margin-bottom: 15px;
                                padding-bottom: 10px;
                                border-bottom: 1px dashed #000;
                            }
                            .company-name {
                                font-weight: bold;
                                font-size: 16px;
                                margin-bottom: 5px;
                                text-transform: uppercase;
                            }
                            .receipt-title {
                                font-size: 18px;
                                font-weight: bold;
                                margin-bottom: 8px;
                            }
                            .details {
                                margin-bottom: 15px;
                                text-align: right;
                                font-size: 12px;
                            }
                            .detail-row {
                                margin-bottom: 3px;
                            }
                            .items-table {
                                width: 100%;
                                border-collapse: collapse;
                                margin-bottom: 15px;
                                font-size: 12px;
                                }
                            .items-table th {
                                padding: 5px 2px;
                                border-bottom: 1px dashed #000;
                                font-weight: bold;
                            }
                            .items-table td {
                                padding: 4px 2px;
                                border-bottom: 1px dotted #ccc;
                            }
                            .summary {
                                margin-bottom: 15px;
                                text-align: right;
                                font-size: 13px;
                            }
                            .summary-row {
                                margin-bottom: 4px;
                                display: flex;
                                justify-content: space-between;
                            }
                            .total-section {
                                border-top: 2px solid #000;
                                border-bottom: 2px solid #000;
                                padding: 8px 0;
                                margin-bottom: 15px;
                                font-weight: bold;
                            }
                            .footer {
                                margin-top: 20px;
                                padding-top: 10px;
                                border-top: 1px dashed #000;
                                font-size: 11px;
                                text-align: center;
                            }
                            .barcode {
                                margin: 10px 0;
                                font-family: 'Libre Barcode 128', cursive;
                                font-size: 24px;
                            }
                            @media print {
                                body { 
                                    width: 80mm !important;
                                    margin: 0 !important;
                                    padding: 5px !important;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="receipt-container">
                            <div class="header">
                                <div class="company-name"><?= $company_name ?></div>
                                <div class="receipt-title">إيصال مبيعات</div>
                                <div>نقطة البيع المتقدم</div>
                            </div>
                            
                            <div class="details">
                                <div class="detail-row"><strong>التاريخ:</strong> ${new Date().toLocaleDateString('ar-EG')}</div>
                                <div class="detail-row"><strong>الوقت:</strong> ${new Date().toLocaleTimeString('ar-EG')}</div>
                                <div class="detail-row"><strong>الفاتورة:</strong> ${invoiceNo}</div>
                                <div class="detail-row"><strong>الزبون:</strong> Walk-In</div>
                                <div class="detail-row"><strong>المخزن:</strong> <?= $default_location ?></div>
                            </div>
                            
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th width="40%">الصنف</th>
                                        <th width="15%">الكمية</th>
                                        <th width="25%">السعر</th>
                                        <th width="20%">المجموع</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                items.forEach(item => {
                    const itemDiscountDecimal = item.discount / 100;
                    const subtotal = item.unit_price * item.qty * (1 - itemDiscountDecimal);
                    const shortDescription = item.description.length > 20 ? item.description.substring(0, 20) + '...' : item.description;
                    
                    receiptContent += `
                                    <tr>
                                        <td style="text-align: right;">${shortDescription}</td>
                                        <td>${item.qty}</td>
                                        <td>${item.unit_price.toFixed(2)}</td>
                                        <td>${subtotal.toFixed(2)}</td>
                                    </tr>
                    `;
                });
                
                receiptContent += `
                                </tbody>
                            </table>
                            
                            <div class="summary">
                                <div class="summary-row">
                                    <span>المجموع الفرعي:</span>
                                    <span>${subTotal.toFixed(2)} ₪</span>
                                </div>
                                <div class="summary-row">
                                    <span>الضريبة (${(taxRate * 100).toFixed(0)}%):</span>
                                    <span>${taxAmount.toFixed(2)} ₪</span>
                                </div>
                `;
                
                if (paid > 0) {
                    receiptContent += `
                                <div class="summary-row">
                                    <span>المدفوع:</span>
                                    <span>${paid.toFixed(2)} ₪</span>
                                </div>
                                <div class="summary-row">
                                    <span>الباقي:</span>
                                    <span>${change.toFixed(2)} ₪</span>
                                </div>
                    `;
                }
                
                receiptContent += `
                            </div>
                            
                            <div class="total-section">
                                <div class="summary-row">
                                    <span>المجموع الكلي:</span>
                                    <span>${total.toFixed(2)} ₪</span>
                                </div>
                            </div>
                            
                            <div class="barcode">*${invoiceNo}*</div>
                            
                            <div class="footer">
                                <div>شكراً لزيارتكم</div>
                                <div>نرحب باقتراحاتكم واستفساراتكم</div>
                                <div>Tel: 0501234567</div>
                                <div>${new Date().toLocaleString('ar-EG')}</div>
                            </div>
                        </div>
                        
                        <script>
                            window.onload = function() {
                                setTimeout(function() {
                                    window.print();
                                    setTimeout(function() {
                                        window.close();
                                    }, 500);
                                }, 1000);
                            }
                        <\/script>
                    </body>
                    </html>
                `;
                
                printWindow.document.write(receiptContent);
                printWindow.document.close();
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