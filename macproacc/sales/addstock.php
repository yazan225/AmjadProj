<?php
// addstock.php - نظام إدارة المخزون المبسط

$path_to_root = "..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

// بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// الحصول على معلومات الشركة
$company_index = $_SESSION['wa_current_user']->company;
global $db_connections, $tbpref;
$prefix = $db_connections[$company_index]['tbpref'];
$company_name = $db_connections[$company_index]['name'];

// معلومات الاتصال
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

// تبسيط جلب المخازن
$locations = [];
$sql_locations = "SELECT loc_code, location_name FROM " . $prefix . "locations WHERE inactive = 0";
$result_locations = $conn->query($sql_locations);
if ($result_locations && $result_locations->num_rows > 0) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
}

// تعيين المخزن الافتراضي
if (!isset($_SESSION['selected_location'])) {
    $_SESSION['selected_location'] = count($locations) > 0 ? $locations[0]['loc_code'] : 'DEF';
}

// تغيير المخزن
if (isset($_POST['change_location'])) {
    $_SESSION['selected_location'] = $_POST['location'];
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// جلب مراكز التكلفة من جدول dimensions
$cost_centers = [];
$cost_centers_sql = "SELECT id, name, reference FROM " . $prefix . "dimensions WHERE closed = 0";
$cost_centers_result = $conn->query($cost_centers_sql);
if ($cost_centers_result && $cost_centers_result->num_rows > 0) {
    while($row = $cost_centers_result->fetch_assoc()) {
        $cost_centers[] = $row;
    }
}

// جلب الفئات
$categories = [];
$categories_sql = "SELECT category_id, description FROM " . $prefix . "stock_category";
$categories_result = $conn->query($categories_sql);
if ($categories_result && $categories_result->num_rows > 0) {
    while($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// جلب أنواع الضرائب
$tax_types = [];
$tax_types_sql = "SELECT id, name FROM " . $prefix . "tax_types WHERE inactive = 0";
$tax_types_result = $conn->query($tax_types_sql);
if ($tax_types_result && $tax_types_result->num_rows > 0) {
    while($row = $tax_types_result->fetch_assoc()) {
        $tax_types[] = $row;
    }
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
    $sales_price = floatval($_POST['sales_price']);
    $initial_quantity = intval($_POST['initial_quantity']);
    
    $location = isset($_POST['location']) ? trim($_POST['location']) : $_SESSION['selected_location'];
    $cost_center = isset($_POST['cost_center']) ? intval($_POST['cost_center']) : 0;
    
    // التحقق من عدم وجود الصنف مسبقاً
    $check_sql = "SELECT * FROM " . $prefix . "stock_master WHERE stock_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    
    if ($check_stmt) {
        $check_stmt->bind_param("s", $stock_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "خطأ: رقم الصنف موجود مسبقاً في النظام!";
        } else {
            // بدء المعاملة
            $conn->begin_transaction();
            
            try {
                // 1. إضافة الصنف إلى stock_master
                $sql1 = "INSERT INTO " . $prefix . "stock_master 
                        (stock_id, category_id, tax_type_id, description, long_description, units, mb_flag, 
                        sales_account, cogs_account, inventory_account, adjustment_account, wip_account, 
                        purchase_cost, material_cost) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, '41010001', '42090001', '11040001', '42050001', '11040002', ?, ?)";
                
                $stmt1 = $conn->prepare($sql1);
                if (!$stmt1) throw new Exception("فشل في إعداد استعلام stock_master: " . $conn->error);
                
                $stmt1->bind_param("siissssdd", $stock_id, $category_id, $tax_type_id, $description, 
                                 $long_description, $units, $mb_flag, $purchase_cost, $purchase_cost);
                
                if (!$stmt1->execute()) throw new Exception("فشل في إضافة الصنف: " . $stmt1->error);
                
                // 2. إضافة سعر البيع
                $sql2 = "INSERT INTO " . $prefix . "prices (stock_id, sales_type_id, curr_abrev, price) 
                        VALUES (?, 1, 'ILS', ?)";
                $stmt2 = $conn->prepare($sql2);
                if (!$stmt2) throw new Exception("فشل في إعداد استعلام السعر: " . $conn->error);
                
                $stmt2->bind_param("sd", $stock_id, $sales_price);
                if (!$stmt2->execute()) throw new Exception("فشل في إضافة السعر: " . $stmt2->error);
                
                // 3. إضافة الكمية الأولية إذا كانت أكبر من 0
                if ($initial_quantity > 0) {
                    $sql3 = "INSERT INTO " . $prefix . "stock_moves 
                            (stock_id, trans_no, type, loc_code, tran_date, price, reference, qty, standard_cost)
                            VALUES (?, 0, 0, ?, NOW(), ?, 'الكمية الأولية', ?, ?)";
                    $stmt3 = $conn->prepare($sql3);
                    if (!$stmt3) throw new Exception("فشل في إعداد استعلام المخزون: " . $conn->error);
                    
                    $stmt3->bind_param("ssddd", $stock_id, $location, $purchase_cost, $initial_quantity, $purchase_cost);
                    if (!$stmt3->execute()) throw new Exception("فشل في إضافة الكمية: " . $stmt3->error);
                }
                
                // 4. إضافة مركز التكلفة إذا تم اختياره
                if ($cost_center > 0) {
                    // جرب stock_dimensions أولاً
                    $table_check = $conn->query("SHOW TABLES LIKE '" . $prefix . "stock_dimensions'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $sql4 = "INSERT INTO " . $prefix . "stock_dimensions 
                                (stock_id, dimension_id, dimension2_id, loc_code)
                                VALUES (?, ?, 0, ?)";
                        $stmt4 = $conn->prepare($sql4);
                        if ($stmt4) {
                            $stmt4->bind_param("sis", $stock_id, $cost_center, $location);
                            $stmt4->execute(); // لا نرمي خطأ هنا لأنه غير حرج
                        }
                    }
                }
                
                $conn->commit();
                $success = "تمت إضافة الصنف بنجاح!";
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "خطأ: " . $e->getMessage();
            }
        }
        $check_stmt->close();
    } else {
        $error = "خطأ في إعداد الاستعلام: " . $conn->error;
    }
}

// جلب الأصناف لعرضها (استعلام مبسط)
$items_sql = "SELECT stock_id, description, units, purchase_cost FROM " . $prefix . "stock_master ORDER BY stock_id DESC LIMIT 20";
$items_result = $conn->query($items_sql);

// حساب الإجماليات
$total_items = 0;
$total_value = 0;

if ($items_result && $items_result->num_rows > 0) {
    $total_items = $items_result->num_rows;
    while($row = $items_result->fetch_assoc()) {
        $total_value += $row['purchase_cost'];
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        :root {
            --primary: #1a73e8; --primary-dark: #0d62c9; --secondary: #34a853; --secondary-dark: #2a9849;
            --danger: #ea4335; --danger-dark: #d93025; --warning: #fbbc05; --warning-dark: #e9ab00;
            --dark: #202124; --darker: #17181b; --light: #f8f9fa; --gray: #dadce0; --gray-light: #f0f2f5;
            --border: #dfe1e5; --success-bg: #e6f4ea; --error-bg: #fce8e6; --text-dark: #3c4043; --text-light: #5f6368;
        }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%); color: var(--dark); min-height: 100vh; padding: 20px; direction: rtl; }
        .container { max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        header { background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 100%); color: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo i { font-size: 28px; color: var(--warning); }
        .logo h1 { font-size: 24px; font-weight: 700; }
        .user-info { background: rgba(255,255,255,0.1); padding: 8px 15px; border-radius: 6px; font-size: 14px; }
        .location-selector { background: white; border-radius: 12px; padding: 15px 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); margin-bottom: 10px; }
        .location-form { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .location-form label { font-weight: 600; color: var(--text-dark); }
        .location-form select { padding: 8px 12px; border: 2px solid var(--border); border-radius: 6px; min-width: 200px; }
        .location-form .btn { padding: 8px 16px; }
        .panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
        .panel-title { font-size: 22px; color: var(--primary); margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--gray-light); display: flex; align-items: center; gap: 10px; }
        .form-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-dark); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px 15px; border: 2px solid var(--border); border-radius: 8px; font-size: 16px; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2); }
        .btn { padding: 12px 20px; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--secondary); color: white; }
        .btn-success:hover { background: var(--secondary-dark); }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: var(--warning-dark); }
        .btn-block { width: 100%; }
        .action-buttons { display: flex; gap: 15px; margin-top: 25px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 16px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: var(--success-bg); color: #0d652d; border: 1px solid var(--secondary); }
        .alert-error { background: var(--error-bg); color: #c5221f; border: 1px solid var(--danger); }
        .inventory-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .inventory-table th { background: var(--primary); color: white; padding: 12px; text-align: center; }
        .inventory-table td { padding: 10px; text-align: center; border-bottom: 1px solid var(--gray-light); }
        .inventory-table tr:nth-child(even) { background: var(--gray-light); }
        .table-container { overflow-x: auto; }
        .stock-summary { margin-top: 20px; padding: 15px; background: #f0f7ff; border-radius: 8px; border: 1px solid var(--border); display: flex; justify-content: space-around; }
        .stock-item { display: flex; flex-direction: column; align-items: center; }
        .stock-value { font-size: 24px; font-weight: bold; color: var(--primary); }
        .stock-label { font-size: 14px; color: var(--text-light); }
        .required::after { content: " *"; color: var(--danger); }
        .current-location { background: var(--warning); color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .form-section { background: var(--gray-light); padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .section-title { font-size: 18px; color: var(--primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
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
        
        <!-- مخزن التحديد -->
        <?php if (count($locations) > 0): ?>
        <div class="location-selector">
            <form method="POST" class="location-form">
                <label for="location">اختر المخزن الافتراضي:</label>
                <select id="location" name="location">
                    <?php foreach($locations as $loc): ?>
                        <option value="<?php echo $loc['loc_code']; ?>" 
                            <?php echo ($_SESSION['selected_location'] == $loc['loc_code']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc['location_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="change_location" class="btn btn-warning">
                    <i class="fas fa-sync-alt"></i> تغيير المخزن
                </button>
                <span class="current-location">
                    <i class="fas fa-warehouse"></i>
                    المخزن الحالي: 
                    <?php 
                    $current_location_name = "غير معروف";
                    foreach($locations as $loc) {
                        if ($loc['loc_code'] == $_SESSION['selected_location']) {
                            $current_location_name = $loc['location_name'];
                            break;
                        }
                    }
                    echo htmlspecialchars($current_location_name);
                    ?>
                </span>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="panel">
            <h2 class="panel-title"><i class="fas fa-plus-circle"></i> إضافة صنف جديد</h2>
            
            <form method="POST" action="">
                <div class="form-container">
                    <!-- المعلومات الأساسية -->
                    <div class="form-section" style="grid-column: 1 / -1;">
                        <h3 class="section-title"><i class="fas fa-info-circle"></i> المعلومات الأساسية</h3>
                        <div class="form-container">
                            <div class="form-group">
                                <label for="stock_id" class="required">رقم الصنف</label>
                                <input type="text" id="stock_id" name="stock_id" placeholder="أدخل رقم الصنف" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="required">وصف الصنف</label>
                                <input type="text" id="description" name="description" placeholder="أدخل وصف الصنف" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="long_description">الوصف الطويل</label>
                                <textarea id="long_description" name="long_description" rows="2" placeholder="أدخل الوصف الطويل"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="category_id">الفئة</label>
                                <select id="category_id" name="category_id">
                                    <option value="1">-- اختر الفئة --</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>">
                                            <?php echo htmlspecialchars($cat['description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="tax_type_id">نوع الضريبة</label>
                                <select id="tax_type_id" name="tax_type_id">
                                    <option value="1">-- اختر نوع الضريبة --</option>
                                    <?php foreach($tax_types as $tax): ?>
                                        <option value="<?php echo $tax['id']; ?>">
                                            <?php echo htmlspecialchars($tax['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="units" class="required">الوحدة</label>
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
                        </div>
                    </div>
                    
                    <!-- الأسعار والتكاليف -->
                    <div class="form-section" style="grid-column: 1 / -1;">
                        <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> الأسعار والتكاليف</h3>
                        <div class="form-container">
                            <div class="form-group">
                                <label for="purchase_cost" class="required">سعر الشراء</label>
                                <input type="number" id="purchase_cost" name="purchase_cost" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="sales_price" class="required">سعر البيع</label>
                                <input type="number" id="sales_price" name="sales_price" step="0.01" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- المخزون والكميات -->
                    <div class="form-section" style="grid-column: 1 / -1;">
                        <h3 class="section-title"><i class="fas fa-boxes"></i> المخزون والكميات</h3>
                        <div class="form-container">
                            <div class="form-group">
                                <label for="initial_quantity">الكمية الأولية</label>
                                <input type="number" id="initial_quantity" name="initial_quantity" value="0" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="item_location">المستودع</label>
                                <select id="item_location" name="location">
                                    <option value="">-- اختر المستودع --</option>
                                    <?php foreach($locations as $loc): ?>
                                        <option value="<?php echo $loc['loc_code']; ?>" 
                                            <?php echo ($_SESSION['selected_location'] == $loc['loc_code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['location_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cost_center">مركز التكلفة</label>
                                <select id="cost_center" name="cost_center">
                                    <option value="0">-- اختر مركز التكلفة --</option>
                                    <?php foreach($cost_centers as $cc): ?>
                                        <option value="<?php echo $cc['id']; ?>">
                                            <?php echo htmlspecialchars($cc['name']); ?>
                                            <?php if (!empty($cc['reference'])): ?>
                                                (<?php echo htmlspecialchars($cc['reference']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
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
            <h2 class="panel-title"><i class="fas fa-list"></i> الأصناف المضافة حديثاً</h2>
            
            <div class="table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>رقم الصنف</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th>سعر الشراء</th>
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
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px;">لا توجد أصناف في النظام</td>
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
                    <div class="stock-value"><?php echo number_format($total_value, 2); ?></div>
                    <div class="stock-label">القيمة الإجمالية</div>
                </div>
            </div>
        </div>
    </div>

    <?php $conn->close(); ?>
</body>
</html>