<?php
// addstock.php - يستخدم نفس اتصال الشركة مثل possys.php

$path_to_root = "..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

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

// إنشاء الاتصال
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("<h2 style='color:red'>فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</h2>");
}
$conn->set_charset("utf8");

// التأكد من أن المستخدم مسجل الدخول
if (!isset($_SESSION['wa_current_user']) || !$_SESSION['wa_current_user']->logged_in()) {
    header('Location: ' . $path_to_root . '/index.php');
    exit;
}

// معالجة إضافة الصنف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $stock_id = trim($_POST['stock_id']);
    $description = trim($_POST['description']);
    $long_description = trim($_POST['long_description']);
    $category_id = intval($_POST['category_id']);
    $tax_type_id = intval($_POST['tax_type_id']);
    $units = trim($_POST['units']);
    $mb_flag = $_POST['mb_flag'];
    $purchase_cost = floatval($_POST['purchase_cost']);
    $material_cost = $purchase_cost;
    $sales_price = floatval($_POST['sales_price']);
    $initial_quantity = intval($_POST['initial_quantity']);
    
    // الحسابات المحددة (نفس إعدادات FrontAccounting)
    $sales_account = "41010001";
    $cogs_account = "42090001";
    $inventory_account = "11040001";
    $adjustment_account = "42050001";
    $wip_account = "11040002";
    
    // التحقق من عدم وجود الصنف مسبقاً
    $check_sql = "SELECT * FROM " . $prefix . "stock_master WHERE stock_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $stock_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "خطأ: رقم الصنف موجود مسبقاً في النظام!";
    } else {
        // بدء transaction
        $conn->begin_transaction();
        
        try {
            // إضافة الصنف إلى stock_master
            $sql = "INSERT INTO " . $prefix . "stock_master 
                    (stock_id, category_id, tax_type_id, description, long_description, units, mb_flag, 
                    sales_account, cogs_account, inventory_account, adjustment_account, wip_account, 
                    purchase_cost, material_cost) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siisssssssssdd", 
                $stock_id, $category_id, $tax_type_id, $description, $long_description, 
                $units, $mb_flag, $sales_account, $cogs_account, $inventory_account, 
                $adjustment_account, $wip_account, $purchase_cost, $material_cost);
            
            if (!$stmt->execute()) {
                throw new Exception("فشل في إضافة الصنف: " . $stmt->error);
            }
            
            // إضافة سعر البيع
            $price_sql = "INSERT INTO " . $prefix . "prices (stock_id, sales_type_id, curr_abrev, price) 
                         VALUES (?, 1, 'ILS', ?)";
            $price_stmt = $conn->prepare($price_sql);
            $price_stmt->bind_param("sd", $stock_id, $sales_price);
            
            if (!$price_stmt->execute()) {
                throw new Exception("حدث خطأ في إضافة سعر البيع: " . $price_stmt->error);
            }
            
            // إضافة الكمية الأولية
            if ($initial_quantity > 0) {
                // الحصول على location الافتراضي
                $location_sql = "SELECT loc_code FROM " . $prefix . "locations WHERE inactive = 0 ORDER BY loc_code LIMIT 1";
                $location_result = $conn->query($location_sql);
                $location = $location_result->fetch_assoc();
                $loc_code = $location ? $location['loc_code'] : 'DEF';
                
                // إضافة حركة مخزون
                $movement_sql = "INSERT INTO " . $prefix . "stock_moves 
                                (stock_id, trans_no, type, loc_code, tran_date, price, reference, qty, standard_cost)
                                VALUES (?, 0, 0, ?, NOW(), ?, 'الكمية الأولية', ?, ?)";
                $movement_stmt = $conn->prepare($movement_sql);
                $movement_stmt->bind_param("ssddd", $stock_id, $loc_code, $purchase_cost, $initial_quantity, $purchase_cost);
                
                if (!$movement_stmt->execute()) {
                    throw new Exception("حدث خطأ في إضافة الكمية الأولية: " . $movement_stmt->error);
                }
                
                $movement_stmt->close();
            }
            
            // commit transaction
            $conn->commit();
            $success = "تمت إضافة الصنف والكمية الأولية بنجاح!";
            
        } catch (Exception $e) {
            // rollback transaction في حالة خطأ
            $conn->rollback();
            $error = $e->getMessage();
        }
        
        if (isset($stmt)) $stmt->close();
        if (isset($price_stmt)) $price_stmt->close();
    }
    
    $check_stmt->close();
}

// جلب الأصناف الحالية لعرضها
$items_sql = "SELECT sm.*, p.price as sales_price, 
             COALESCE(SUM(sm2.qty), 0) as current_quantity
             FROM " . $prefix . "stock_master sm 
             LEFT JOIN " . $prefix . "prices p ON sm.stock_id = p.stock_id AND p.sales_type_id = 1 
             LEFT JOIN " . $prefix . "stock_moves sm2 ON sm.stock_id = sm2.stock_id 
             GROUP BY sm.stock_id
             ORDER BY sm.stock_id LIMIT 20";
$items_result = $conn->query($items_sql);

// حساب الإجماليات
$total_items = 0;
$total_quantity = 0;
$total_value = 0;

if ($items_result && $items_result->num_rows > 0) {
    $total_items = $items_result->num_rows;
    while($row = $items_result->fetch_assoc()) {
        $total_quantity += $row['current_quantity'];
        $total_value += $row['current_quantity'] * $row['purchase_cost'];
    }
    $items_result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة أصناف المخزون - <?php echo $company_name; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            border-radius: 12px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo i {
            font-size: 28px;
            color: var(--warning);
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 700;
        }
        
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .panel {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .panel-title {
            font-size: 22px;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
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
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--secondary-dark);
        }
        
        .btn-block {
            width: 100%;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
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
        
        .alert-info {
            background: #e8f4fd;
            color: #1a73e8;
            border: 1px solid #1a73e8;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .inventory-table th {
            background: var(--primary);
            color: white;
            padding: 12px;
            text-align: center;
        }
        
        .inventory-table td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .inventory-table tr:nth-child(even) {
            background: var(--gray-light);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .stock-summary {
            margin-top: 20px;
            padding: 15px;
            background: #f0f7ff;
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-around;
        }
        
        .stock-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .stock-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
        }
        
        .stock-label {
            font-size: 14px;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-boxes"></i>
                <h1>إدارة المخزون - <?php echo $company_name; ?></h1>
            </div>
            <div class="user-info">
                <i class="fas fa-user"></i>
                <?php echo $_SESSION['wa_current_user']->username; ?> | 
                الشركة: <?php echo $company_name; ?> | 
                البادئة: <?php echo $prefix; ?>
            </div>
        </header>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>جاري استخدام الشركة:</strong> <?php echo $company_name; ?> 
            | <strong>بادئة الجداول:</strong> <?php echo $prefix; ?>
        </div>
        
        <div class="panel">
            <h2 class="panel-title"><i class="fas fa-plus-circle"></i> إضافة صنف جديد</h2>
            
            <form method="POST" action="">
                <div class="form-container">
                    <div class="form-group">
                        <label for="stock_id">رقم الصنف *</label>
                        <input type="text" id="stock_id" name="stock_id" placeholder="أدخل رقم الصنف" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">وصف الصنف *</label>
                        <input type="text" id="description" name="description" placeholder="أدخل وصف الصنف" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="long_description">الوصف الطويل</label>
                        <textarea id="long_description" name="long_description" rows="2" placeholder="أدخل الوصف الطويل"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">رقم الفئة</label>
                        <input type="number" id="category_id" name="category_id" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="tax_type_id">رقم نوع الضريبة</label>
                        <input type="number" id="tax_type_id" name="tax_type_id" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="units">الوحدة *</label>
                        <input type="text" id="units" name="units" value="قطعة" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="mb_flag">نوع الصنف</label>
                        <select id="mb_flag" name="mb_flag">
                            <option value="B">للبيع والشراء</option>
                            <option value="M">للشراء فقط</option>
                            <option value="S">للبيع فقط</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="purchase_cost">سعر الشراء *</label>
                        <input type="number" id="purchase_cost" name="purchase_cost" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sales_price">سعر البيع *</label>
                        <input type="number" id="sales_price" name="sales_price" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="initial_quantity">الكمية الأولية</label>
                        <input type="number" id="initial_quantity" name="initial_quantity" value="0" min="0">
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" name="add_item" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> حفظ الصنف
                    </button>
                    <button type="reset" class="btn btn-success">
                        <i class="fas fa-eraser"></i> مسح الحقول
                    </button>
                </div>
            </form>
        </div>
        
        <div class="panel">
            <h2 class="panel-title"><i class="fas fa-list"></i> الأصناف الموجودة</h2>
            
            <div class="table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>رقم الصنف</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th>سعر الشراء</th>
                            <th>سعر البيع</th>
                            <th>الكمية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items_result && $items_result->num_rows > 0): ?>
                            <?php while($row = $items_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['stock_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo htmlspecialchars($row['units']); ?></td>
                                    <td><?php echo number_format($row['purchase_cost'], 2); ?></td>
                                    <td><?php echo isset($row['sales_price']) ? number_format($row['sales_price'], 2) : 'غير محدد'; ?></td>
                                    <td><?php echo $row['current_quantity']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">لا توجد أصناف في النظام</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="stock-summary">
                <div class="stock-item">
                    <div class="stock-value"><?php echo $total_items; ?></div>
                    <div class="stock-label">عدد الأصناف</div>
                </div>
                <div class="stock-item">
                    <div class="stock-value"><?php echo $total_quantity; ?></div>
                    <div class="stock-label">الكمية الإجمالية</div>
                </div>
                <div class="stock-item">
                    <div class="stock-value"><?php echo number_format($total_value, 2); ?></div>
                    <div class="stock-label">القيمة الإجمالية</div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // إغلاق الاتصال
    $conn->close();
    ?>
</body>
</html>