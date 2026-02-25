<?php
$path_to_root = "..";

// إعداد الأمان
$page_security = 'SA_SALESINVOICE';

// تحميل بيئة النظام
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

// رقم الشركة الحالية
$company_index = $_SESSION['wa_current_user']->company;

// الوصول إلى قائمة الشركات
global $db_connections, $tbpref;

// استخراج البادئة
$prefix = $db_connections[$company_index]['tbpref'];
$company_name = $db_connections[$company_index]['name'];

// الآن لدينا البادئة الصحيحة للشركة الحالية ($prefix)

// --- حفظ الفاتورة ---
include_once($path_to_root . "/sales/includes/sales_db.inc");
include_once($path_to_root . "/includes/db/sales_types_db.inc");
define('ST_SALESINVOICE', 10); // تعريف ثابت فاتورة المبيعات
define('ST_CUSTPAYMENT', 12); // تعريف ثابت الدفعات

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$user = 'usr1234';
$pass = '@@pass@@x123';
$dbname = 'fa_2418';

// إنشاء اتصال بقاعدة البيانات
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<h2 style='color:red'>فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</h2>");
}
$conn->set_charset("utf8");

// البحث عن زبون Walk-In تلقائيًا
$walkin_debtor_no = null;
$sql = "SELECT debtor_no FROM {$prefix}debtors_master WHERE debtor_ref = '2' OR name LIKE '%Walk-In Customer%' LIMIT 1";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $walkin_debtor_no = (int)$row['debtor_no'];
} else {
    die("<h3 style='color:red'>لم يتم العثور على زبون Walk-In في قاعدة البيانات.</h3>");
}

// تهيئة السلة والخصم في الجلسة
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['invoice_discount'])) {
    $_SESSION['invoice_discount'] = 0;
}

// تحديث عناصر السلة
if (isset($_POST['update_cart'])) {
    foreach ($_SESSION['cart'] as &$item) {
        $id = $item['stock_id'];
        if (isset($_POST['unit_price'][$id]) && isset($_POST['discount'][$id])) {
            $price = floatval($_POST['unit_price'][$id]);
            $disc = floatval($_POST['discount'][$id]);
            if ($price >= 0) $item['unit_price'] = $price;
            if ($disc >= 0) $item['discount'] = $disc;
        }
    }
    // حفظ الخصم الكلي في الجلسة
    $_SESSION['invoice_discount'] = floatval($_POST['invoice_discount'] ?? 0);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// إضافة صنف
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
        $sql = "SELECT stock_id, description, purchase_cost FROM {$prefix}stock_master WHERE stock_id = ? OR long_description LIKE ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $like_code = "%{$item_code}%";
        $stmt->bind_param("ss", $item_code, $like_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $sql_price = "SELECT price FROM {$prefix}prices WHERE stock_id = ? AND sales_type_id = 1 AND curr_abrev = 'ILS' LIMIT 1";
            $stmt_price = $conn->prepare($sql_price);
            $stmt_price->bind_param("s", $row['stock_id']);
            $stmt_price->execute();
            $res_price = $stmt_price->get_result();
            $unit_price = $res_price->fetch_assoc()['price'] ?? $row['purchase_cost'];
            $stmt_price->close();

            $cart[] = [
                'stock_id' => $row['stock_id'],
                'description' => $row['description'],
                'unit_price' => floatval($unit_price),
                'standard_cost' => floatval($row['purchase_cost']),
                'qty' => $qty,
                'discount' => 0
            ];
        } else {
            $error = "الصنف غير موجود";
        }
        $stmt->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// حذف صنف
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

// إلغاء الفاتورة
if (isset($_GET['cancel_invoice'])) {
    $_SESSION['cart'] = [];
    $_SESSION['invoice_discount'] = 0; // إعادة تعيين الخصم الكلي
    unset($_SESSION['last_invoice']);
    unset($_SESSION['last_payment']);
    $success = "تم إلغاء الفاتورة بنجاح";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// حفظ الفاتورة
if (isset($_POST['save_invoice']) && count($_SESSION['cart']) > 0) {
    $invoice_discount = $_SESSION['invoice_discount']; // استخدام الخصم من الجلسة
    $tran_date = date('Y-m-d');
    $reference = "POS-" . date('Ymd-His');
    $type = ST_SALESINVOICE;

    // حساب المجاميع
    $sub_total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $line_total = $item['unit_price'] * $item['qty'] * (1 - $item['discount'] / 100);
        $sub_total += $line_total;
    }
    $total = $sub_total * (1 - $invoice_discount / 100);

    // إنشاء trans_no تلقائياً
    $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = $type");
    $row = $result->fetch_assoc();
    $trans_no = $row['max_no'] + 1;

    // إدخال الفاتورة في debtor_trans
    $sql = "INSERT INTO {$prefix}debtor_trans
        (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, reference, tpe, ov_amount, ov_discount)
        VALUES (?, ?, 0, ?, '', ?, ?, ?, 0, ?, ?)";
    $stmt = $conn->prepare($sql);
    $due_date = $tran_date;

    // حساب قيمة الخصم النقدي (sub_total * invoice_discount / 100)
    $discount_amount = $sub_total * ($invoice_discount / 100);

    $stmt->bind_param("iiisssdd", $trans_no, $type, $walkin_debtor_no, $tran_date, $due_date, $reference, $total, $discount_amount);

    if (!$stmt->execute()) {
        $error = "فشل في حفظ الفاتورة: " . $stmt->error;
    } else {
        // إدخال تفاصيل الأصناف
        $line_no = 1;
        foreach ($_SESSION['cart'] as $item) {
            $line_total = $item['unit_price'] * $item['qty'] * (1 - $item['discount'] / 100);
            $sql_line = "INSERT INTO {$prefix}debtor_trans_details
                (debtor_trans_no, debtor_trans_type, stock_id, description, unit_price, unit_tax, quantity, discount_percent, standard_cost, qty_done, src_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 0)";
            $stmt_line = $conn->prepare($sql_line);
            $stmt_line->bind_param("iissdddii",
                $trans_no, $type, $item['stock_id'], $item['description'],
                $item['unit_price'], $item['qty'], $item['discount'],
                $item['standard_cost'], $item['qty']
            );
            $stmt_line->execute();
            $line_no++;
        }

        $success = "تم حفظ الفاتورة بنجاح! رقم: $trans_no";
        $_SESSION['last_invoice'] = [
            'trans_no' => $trans_no,
            'total' => $total,
            'items' => $_SESSION['cart'],
            'discount' => $invoice_discount // تخزين الخصم الكلي
        ];
        $_SESSION['cart'] = [];
        $_SESSION['invoice_discount'] = 0; // إعادة تعيين الخصم بعد الحفظ
    }
}

// معالجة الدفع
if (isset($_POST['process_payment'])) {
    $paid_amount = floatval($_POST['paid_amount']);
    $invoice_total = floatval($_POST['invoice_total']);
    $invoice_no = intval($_POST['invoice_no']);
    $change = $paid_amount - $invoice_total;
    
    if ($change < 0) {
        $error = "المبلغ المدفوع غير كافي. المطلوب: " . number_format($invoice_total, 2) . " ₪";
    } else {
        // تسجيل الدفعة في جدول debtor_trans
        $payment_ref = "PAY-" . date('Ymd-His');
        $payment_date = date('Y-m-d');
        $payment_type = ST_CUSTPAYMENT; // 12

        // إنشاء trans_no للدفعة
        $result = $conn->query("SELECT MAX(trans_no) as max_no FROM {$prefix}debtor_trans WHERE type = $payment_type");
        $row = $result->fetch_assoc();
        $payment_trans_no = $row['max_no'] + 1;

        // إدخال الدفعة في debtor_trans
        $sql_payment = "INSERT INTO {$prefix}debtor_trans
            (trans_no, type, version, debtor_no, branch_code, tran_date, due_date, reference, tpe, ov_amount, ov_discount, alloc)
            VALUES (?, ?, 0, ?, '', ?, ?, ?, 0, ?, 0, ?)";
        $stmt_payment = $conn->prepare($sql_payment);
        $stmt_payment->bind_param("iiisssdd", 
            $payment_trans_no, $payment_type, $walkin_debtor_no, 
            $payment_date, $payment_date, $payment_ref, 
            $paid_amount, $paid_amount
        );
        
        if (!$stmt_payment->execute()) {
            $error = "فشل في تسجيل الدفعة: " . $stmt_payment->error;
        } else {
            // تحديث الفاتورة الأصلية لتسجيل المبلغ المدفوع
            $sql_update = "UPDATE {$prefix}debtor_trans 
                           SET alloc = ? 
                           WHERE trans_no = ? AND type = " . ST_SALESINVOICE;
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("di", $paid_amount, $invoice_no);
            
            if (!$stmt_update->execute()) {
                $error = "فشل في تحديث الفاتورة: " . $stmt_update->error;
            } else {
                $success = "تم استلام المبلغ بنجاح. الباقي: " . number_format($change, 2) . " ₪";
                $_SESSION['last_payment'] = [
                    'invoice_no' => $invoice_no,
                    'paid_amount' => $paid_amount,
                    'change' => $change
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام نقطة البيع</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            padding: 20px;
            direction: rtl;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 10px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
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
            gap: 15px;
            z-index: 2;
        }
        
        .logo i {
            font-size: 32px;
            color: var(--warning);
            background: rgba(0,0,0,0.2);
            padding: 10px;
            border-radius: 50%;
        }
        
        .logo h1 {
            font-size: 28px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 2;
        }
        
        .user-details {
            text-align: right;
        }
        
        .user-details .user-name {
            font-size: 18px;
            font-weight: 600;
        }
        
        .user-details .user-role {
            font-size: 14px;
            color: var(--gray);
        }
        
        .datetime {
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1.8fr;
            gap: 10px;
            height: calc(100vh - 180px);
        }
        
        .panel {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .panel:hover {
            transform: translateY(-3px);
        }
        
        .panel-title {
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .panel-title i {
            font-size: 26px;
        }
        
        .input-group {
            display: flex;
            margin-bottom: 20px;
            gap: 10px;
        }
        
        .input-group input {
            flex: 1;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 18px;
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
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-warning {
            background: var(--warning);
            color: var(--dark);
        }
        
        .btn-warning:hover {
            background: var(--warning-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }
        
        .cart-container {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            flex-grow: 1;
            overflow-y: auto;
            display: block;
            max-height: 350px;
        }
        
        .cart-table thead {
            position: sticky;
            top: 0;
        }
        
        .cart-table th {
            background: var(--primary);
            color: white;
            padding: 15px;
            text-align: center;
            position: sticky;
            top: 0;
        }
        
        .cart-table td {
            padding: 14px;
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
            width: 90px;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            text-align: center;
            font-size: 16px;
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
            font-size: 20px;
            transition: color 0.3s;
            display: inline-block;
            width: 32px;
            height: 32px;
            line-height: 32px;
            border-radius: 50%;
        }
        
        .remove-link:hover {
            color: var(--danger-dark);
            background: rgba(234, 67, 53, 0.1);
        }
        
        .summary {
            background: #f0f7ff;
            border-radius: 12px;
            padding: 20px;
            margin-top: auto;
            border: 1px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 18px;
        }
        
        .summary-total {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
            border-top: 2px solid var(--border);
            padding-top: 15px;
            margin-top: 10px;
        }
        
        .discount-input {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            background: white;
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        .discount-input label {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .discount-input input {
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 10px;
            width: 120px;
            font-size: 18px;
            text-align: center;
            background: var(--gray-light);
        }
        
        .discount-input input:focus {
            background: white;
            border-color: var(--primary);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .action-buttons button {
            flex: 1;
        }
        
        .alert {
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
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
            padding: 40px 20px;
            color: var(--text-light);
            font-size: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .empty-cart i {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--border);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .customer-info {
            background: var(--gray-light);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .customer-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 17px;
        }
        
        .customer-row span:first-child {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .total-amount {
            font-size: 26px;
            font-weight: bold;
            background: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            display: inline-block;
            margin: 10px 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* نافذة الدفع */
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
            padding: 30px;
            border-radius: 15px;
            width: 450px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 24px;
            color: var(--primary);
        }
        
        .close-btn {
            font-size: 28px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .payment-info {
            margin: 20px 0;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 18px;
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
            padding: 15px;
            font-size: 22px;
            border: 2px solid var(--border);
            border-radius: 10px;
            text-align: center;
            margin: 15px 0;
            background: var(--gray-light);
        }
        
        .payment-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }
        
        .change-display {
            background: var(--success-bg);
            padding: 15px;
            border-radius: 10px;
            font-size: 20px;
            text-align: center;
            font-weight: bold;
            margin: 15px 0;
            border: 1px solid var(--secondary);
        }
        
        /* نافذة الطباعة */
        .print-invoice {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: none;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }
        
        .print-title {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary);
        }
        
        .print-details {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }
        
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .print-table th {
            background: var(--primary);
            color: white;
            padding: 12px;
            text-align: center;
        }
        
        .print-table td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        
        .print-total {
            font-size: 22px;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid var(--border);
        }
        
        .print-footer {
            margin-top: 40px;
            text-align: center;
            color: var(--text-light);
            padding-top: 20px;
            border-top: 1px dashed var(--border);
        }
        
        @media (max-width: 992px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
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
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-cash-register"></i>
                <h1>نظام نقطة البيع (POS)</h1>
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
                <h2 class="panel-title"><i class="fas fa-barcode"></i> إضافة أصناف جديدة</h2>
                
                <form method="post">
                    <div class="input-group">
                        <input type="text" name="item_code" placeholder="أدخل الباركود/ أو اسم الصنف" autofocus>
                        <input type="number" name="qty" value="1" min="1" style="width: 100px;">
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
                        <span>الشركة الحالية:</span>
                        <span><?= $prefix ?></span>
                    </div>
                    <div class="customer-row">
                        <span>إسم الشركة:</span>
                        <span><?= $company_name ?></span>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button class="btn btn-warning" onclick="printInvoice()">
                        <i class="fas fa-print"></i> طباعة الفاتورة
                    </button>
                    <button class="btn btn-danger" onclick="cancelInvoice()">
                        <i class="fas fa-trash-alt"></i> إلغاء الفاتورة
                    </button>
                    <button class="btn btn-success" onclick="openPaymentModal()">
                        <i class="fas fa-money-bill-wave"></i> الدفع
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
                                    $subtotal = $item['unit_price'] * $item['qty'] * (1 - $item['discount'] / 100);
                                    $sub_total += $subtotal;
                                }
                                $discounted_total = $sub_total * (1 - $_SESSION['invoice_discount'] / 100);
                                echo number_format($discounted_total, 2) . ' ₪';
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
                                        $subtotal = $item['unit_price'] * $item['qty'] * (1 - $item['discount'] / 100);
                                        $sub_total += $subtotal;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['stock_id']) ?></td>
                                        <td><?= htmlspecialchars($item['description']) ?></td>
                                        <td><?= $item['qty'] ?></td>
                                        <td>
                                            <input type="number" name="unit_price[<?= $item['stock_id'] ?>]" 
                                                   value="<?= $item['unit_price'] ?>" step="0.01" min="0">
                                        </td>
                                        <td>
                                            <input type="number" name="discount[<?= $item['stock_id'] ?>]" 
                                                   value="<?= $item['discount'] ?>" step="0.1" min="0" max="100">
                                        </td>
                                        <td><?= number_format($subtotal, 2) ?> ₪</td>
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
                                
                                <div class="summary-row">
                                    <span>الخصم الكلي:</span>
                                    <span><?= $_SESSION['invoice_discount'] ?>%</span>
                                </div>
                                
                                <div class="summary-row summary-total">
                                    <span>الإجمالي بعد الخصم:</span>
                                    <span><?= number_format($discounted_total, 2) ?> ₪</span>
                                </div>
                                
                                <div class="discount-input">
                                    <label for="invoice_discount">خصم على الفاتورة:</label>
                                    <input type="number" name="invoice_discount" step="0.1" 
                                           value="<?= $_SESSION['invoice_discount'] ?>" min="0" max="100">
                                    <span>%</span>
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
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- نافذة الدفع -->
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
    
    <!-- نافذة طباعة الفاتورة -->
    <div id="printModal" class="modal">
        <div class="print-invoice" id="printContent">
            <div class="print-header">
                <h1 class="print-title">فاتورة مبيعات</h1>
                <div>نظام نقطة البيع (POS)</div>
            </div>
            
            <div class="print-details">
                <div>
                    <div><strong>رقم الفاتورة:</strong> <span id="printInvoiceNo">-</span></div>
                    <div><strong>التاريخ:</strong> <span id="printDate">-</span></div>
                </div>
                <div>
                    <div><strong>الزبون:</strong> Walk-In Customer</div>
                    <div><strong>رقم الزبون:</strong> <?= $walkin_debtor_no ?></div>
                    <div><strong>الشركة:</strong> <?= $prefix ?></div>
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
                    <!-- سيتم ملء هذا القسم بالبيانات -->
                </tbody>
            </table>
            
            <div class="print-total">
                <span>المجموع الكلي: </span>
                <span id="printTotal">0.00 ₪</span>
            </div>
            
            <div class="print-footer">
                <p>شكراً لتعاملكم معنا</p>
                <p>للاستفسار: 0501234567 | www.example.com</p>
            </div>
            
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-danger" onclick="closePrintModal()">
                    <i class="fas fa-times"></i> إغلاق
                </button>
                <button class="btn btn-success" onclick="printNow()">
                    <i class="fas fa-print"></i> طباعة الفاتورة
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // تحديث التاريخ والوقت
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
        
        // التركيز على حقل الباركود عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="item_code"]').focus();
        });
        
        // فتح نافذة الدفع
        function openPaymentModal() {
            const cartItems = <?= json_encode($_SESSION['cart']) ?>;
            const invoiceDiscount = <?= $_SESSION['invoice_discount'] ?? 0 ?>;
            const invoiceTotal = parseFloat("<?= isset($_SESSION['last_invoice']['total']) ? $_SESSION['last_invoice']['total'] : 0 ?>");
            let total = 0;
            
            // حساب المجموع من السلة إذا كانت غير فارغة
            if (cartItems.length > 0) {
                let subTotal = 0;
                cartItems.forEach(item => {
                    subTotal += item.unit_price * item.qty * (1 - item.discount / 100);
                });
                total = subTotal * (1 - invoiceDiscount / 100);
            } else if (invoiceTotal > 0) {
                total = invoiceTotal;
            } else {
                alert('لا توجد فاتورة للدفع! أضف أصنافاً أو احفظ الفاتورة أولاً.');
                return;
            }
            
            document.getElementById('invoiceTotal').value = total.toFixed(2);
            document.getElementById('invoiceTotalDisplay').textContent = total.toFixed(2) + ' ₪';
            document.getElementById('invoiceNo').value = "<?= isset($_SESSION['last_invoice']['trans_no']) ? $_SESSION['last_invoice']['trans_no'] : '' ?>";
            document.getElementById('paidAmount').value = '';
            document.getElementById('changeDisplay').textContent = 'الباقي: 0.00 ₪';
            document.getElementById('paymentModal').style.display = 'flex';
            document.getElementById('paidAmount').focus();
        }
        
        // إغلاق نافذة الدفع
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        // حساب الباقي
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
        
        // إلغاء الفاتورة
        function cancelInvoice() {
            if (confirm('هل أنت متأكد من إلغاء الفاتورة؟ سيتم حذف جميع الأصناف.')) {
                window.location.href = '?cancel_invoice=1';
            }
        }
        
        // طباعة الفاتورة
        function printInvoice() {
            const cartItems = <?= json_encode($_SESSION['cart']) ?>;
            const invoiceDiscount = <?= $_SESSION['invoice_discount'] ?? 0 ?>;
            const lastInvoice = <?= json_encode($_SESSION['last_invoice'] ?? []) ?>;
            const lastPayment = <?= json_encode($_SESSION['last_payment'] ?? []) ?>;
            let items = [];
            let total = 0;
            let invoiceNo = '';
            let paid = 0;
            let change = 0;
            
            // استخدام الفاتورة المحفوظة إذا كانت موجودة
            if (lastInvoice && lastInvoice.items) {
                items = lastInvoice.items;
                total = lastInvoice.total;
                invoiceNo = lastInvoice.trans_no;
                // إذا كان هناك دفع، نأخذ بياناته
                if (lastPayment && lastPayment.invoice_no == invoiceNo) {
                    paid = lastPayment.paid_amount;
                    change = lastPayment.change;
                }
            } 
            // استخدام السلة الحالية إذا لم توجد فاتورة محفوظة
            else if (cartItems.length > 0) {
                items = cartItems;
                let subTotal = 0;
                items.forEach(item => {
                    subTotal += item.unit_price * item.qty * (1 - item.discount / 100);
                });
                total = subTotal * (1 - invoiceDiscount / 100);
                invoiceNo = 'مؤقتة';
            } 
            // لا توجد بيانات للطباعة
            else {
                alert('لا توجد فاتورة للطباعة! أضف أصنافاً أو احفظ الفاتورة أولاً.');
                return;
            }
            
            // تحديث تاريخ الطباعة
            const now = new Date();
            document.getElementById('printDate').textContent = now.toLocaleString('ar-EG');
            document.getElementById('printInvoiceNo').textContent = invoiceNo;
            
            // تحديث جدول الأصناف
            const itemsTable = document.getElementById('printItems');
            itemsTable.innerHTML = '';
            
            items.forEach(item => {
                const subtotal = item.unit_price * item.qty * (1 - item.discount / 100);
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
            
            // إضافة سطور للدفع والباقي إذا وجد
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
            
            // تحديث المجموع الكلي
            document.getElementById('printTotal').textContent = total.toFixed(2) + ' ₪';
            
            // عرض نافذة الطباعة
            document.getElementById('printModal').style.display = 'flex';
        }
        
        // إغلاق نافذة الطباعة
        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
        }
        
        // طباعة الفاتورة مباشرة
        function printNow() {
            const printContent = document.getElementById('printContent');
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl" lang="ar">
                <head>
                    <meta charset="UTF-8">
                    <title>فاتورة مبيعات</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #000; padding: 8px; text-align: center; }
                        .total { font-size: 18px; font-weight: bold; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    ${printContent.innerHTML}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
            printWindow.close();
        }
    </script>
</body>
</html>