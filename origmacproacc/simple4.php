<?php
header('Content-Type: text/html; charset=utf-8');

// --- حفظ الفاتورة ---
$path_to_root = "..";
$page_security = 'SA_SALESORDER';
include_once($path_to_root . "/sales/includes/sales_db.inc");
include_once($path_to_root . "/sales/includes/db/sales_types_db.inc");
define('ST_SALESINVOICE', 10);
define('ST_CUSTPAYMENT', 12);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$user = 'usr1234';
$pass = '@@pass@@x123';
$dbname = 'fa_2418';
$prefix = '0_';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<h2 style='color:red'>فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</h2>");
}
$conn->set_charset("utf8");

session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// البحث عن زبون Walk-In تلقائيًا
$walkin_debtor_no = null;
$sql = "SELECT debtor_no FROM {$prefix}debtors_master WHERE debtor_ref = '2' OR name LIKE '%Walk-In Customer%' LIMIT 1";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $walkin_debtor_no = (int)$row['debtor_no'];
} else {
    die("<h3 style='color:red'>لم يتم العثور على زبون Walk-In في قاعدة البيانات.</h3>");
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
        $sql = "SELECT stock_id, description, purchase_cost FROM {$prefix}stock_master 
                WHERE stock_id = ? OR long_description LIKE ? OR description LIKE ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $like_code = "%{$item_code}%";
        $stmt->bind_param("sss", $item_code, $like_code, $like_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $sql_price = "SELECT price FROM {$prefix}prices 
                         WHERE stock_id = ? AND sales_type_id = 1 AND curr_abrev = 'ILS' LIMIT 1";
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
    unset($_SESSION['last_invoice']);
    unset($_SESSION['last_payment']);
    $success = "تم إلغاء الفاتورة بنجاح";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// حفظ الفاتورة
if (isset($_POST['save_invoice']) && count($_SESSION['cart']) > 0) {
    $invoice_discount = floatval($_POST['invoice_discount'] ?? 0);
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
    $stmt->bind_param("iiisssdd", $trans_no, $type, $walkin_debtor_no, $tran_date, $due_date, $reference, $total, $invoice_discount);
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
            
            // ترحيل المخزون
            $sql_stock = "UPDATE {$prefix}stock_moves
                         SET qty = qty - ?
                         WHERE stock_id = ?";
            $stmt_stock = $conn->prepare($sql_stock);
            $stmt_stock->bind_param("is", $item['qty'], $item['stock_id']);
            $stmt_stock->execute();
            
            $line_no++;
        }

        $success = "تم حفظ الفاتورة بنجاح! رقم: $trans_no";
        $_SESSION['last_invoice'] = [
            'trans_no' => $trans_no,
            'total' => $total,
            'items' => $_SESSION['cart']
        ];
        $_SESSION['cart'] = [];
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
        $payment_type = ST_CUSTPAYMENT;

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
            // إضافة سجل التوزيع
            $sql_alloc = "INSERT INTO {$prefix}debtor_trans_allocation 
                         (amt, date_alloc, trans_no_from, trans_type_from, trans_no_to, trans_type_to)
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_alloc = $conn->prepare($sql_alloc);
            $alloc_date = date('Y-m-d');
            $trans_type_from = ST_CUSTPAYMENT;
            $trans_type_to = ST_SALESINVOICE;
            $stmt_alloc->bind_param("dsiiii", 
                $paid_amount, $alloc_date, 
                $payment_trans_no, $trans_type_from,
                $invoice_no, $trans_type_to
            );
            $stmt_alloc->execute();
            
            // تحديث الفاتورة الأصلية لتسجيل المبلغ المدفوع
            $sql_update = "UPDATE {$prefix}debtor_trans 
                           SET alloc = alloc + ? 
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
        /* ... (ابقى على جميع أنماط CSS الأصلية) ... */

        /* تعديل نافذة الطباعة */
        .print-invoice {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin: 0 auto;
        }
        
        /* ... (ابقى على بقية الأنماط) ... */
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- ... (ابقى على هيكل HTML الأصلي) ... -->

    <script>
        // ... (ابقى على جميع الدوال الأصلية) ... 
        
        // طباعة الفاتورة
        function printInvoice() {
            const cartItems = <?= json_encode($_SESSION['cart']) ?>;
            const lastInvoice = <?= json_encode($_SESSION['last_invoice'] ?? []) ?>;
            const lastPayment = <?= json_encode($_SESSION['last_payment'] ?? []) ?>;
            let items = [];
            let total = 0;
            let invoiceNo = '';
            let paid = 0;
            let change = 0;
            
            if (lastInvoice && lastInvoice.items) {
                items = lastInvoice.items;
                total = lastInvoice.total;
                invoiceNo = lastInvoice.trans_no;
                if (lastPayment && lastPayment.invoice_no == invoiceNo) {
                    paid = lastPayment.paid_amount;
                    change = lastPayment.change;
                }
            } else if (cartItems.length > 0) {
                items = cartItems;
                items.forEach(item => {
                    total += item.unit_price * item.qty * (1 - item.discount / 100);
                });
                invoiceNo = 'مؤقتة';
            } else {
                alert('لا توجد فاتورة للطباعة! أضف أصنافاً أو احفظ الفاتورة أولاً.');
                return;
            }
            
            const now = new Date();
            document.getElementById('printDate').textContent = now.toLocaleString('ar-EG');
            document.getElementById('printInvoiceNo').textContent = invoiceNo;
            
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
            
            document.getElementById('printTotal').textContent = total.toFixed(2) + ' ₪';
            document.getElementById('printModal').style.display = 'flex';
        }
        
        // إغلاق نافذة الطباعة
        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
        }
        
        // طباعة الفاتورة مباشرة
        function printNow() {
            const printContent = document.getElementById('printContent').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = printContent;
            window.print();
            
            // إعادة تحميل الصفحة لاستعادة الحالة الأصلية
            location.reload();
        }
    </script>
</body>
</html>