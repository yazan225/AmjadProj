��<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة المخزون </title>
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
            position: relative;
            overflow: hidden;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
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
            padding: 14px 16px;
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
            padding: 14px 25px;
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
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
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
        
        .inventory-table tr:hover {
            background: #e8f0fe;
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 500px;
        }
        
        .stock-summary {
            margin-top: 20px;
            padding: 15px;
            background: #f0f7ff;
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
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
        
        @media (max-width: 768px) {
            .form-container {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .stock-summary {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-boxes"></i>
                <h1>نظام إدارة المخزون</h1>
            </div>
        </header>
        
        <?php
        // معلومات الاتصال بقاعدة البيانات
        $host = 'localhost';
        $user = 'usr1234';
        $pass = '@@pass@@x123';
        $dbname = 'fa_2418';
        
        // إنشاء الاتصال
        $conn = new mysqli($host, $user, $pass, $dbname);
        if ($conn->connect_error) {
            die("<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</div>");
        }
        $conn->set_charset("utf8");
        
        // الحصول على البادئة من الجلسة
        session_start();
        if (isset($_SESSION['wa_current_user'])) {
            $company_index = $_SESSION['wa_current_user']->company;
            // استخدام بادئة افتراضية إذا لم تكن متوفرة
            $prefix = isset($db_connections[$company_index]['tbpref']) ? $db_connections[$company_index]['tbpref'] : '0_';
        } else {
            // استخدام بادئة افتراضية إذا لم تكن الجلسة متوفرة
            $prefix = '0_';
        }
        
        // معالجة نموذج الإضافة
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
            // استقبال البيانات من النموذج
            $stock_id = $_POST['stock_id'];
            $description = $_POST['description'];
            $long_description = $_POST['long_description'];
            $category_id = $_POST['category_id'];
            $tax_type_id = $_POST['tax_type_id'];
            $units = $_POST['units'];
            $mb_flag = $_POST['mb_flag'];
            $purchase_cost = $_POST['purchase_cost'];
            $material_cost = $purchase_cost; // نفس سعر الشراء
            $sales_price = $_POST['sales_price'];
            $initial_quantity = $_POST['initial_quantity'];
            
            // الحسابات المحددة
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
                echo "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> خطأ: رقم الصنف موجود مسبقاً في النظام!</div>";
            } else {
                // بدء transaction
                $conn->begin_transaction();
                
                try {
                    // الاستعلام لإضافة الصنف إلى stock_master
                    $sql = "INSERT INTO " . $prefix . "stock_master 
                            (stock_id, category_id, tax_type_id, description, long_description, units, mb_flag, 
                            sales_account, cogs_account, inventory_account, adjustment_account, wip_account, 
                            purchase_cost, material_cost) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("خطأ في إعداد استعلام الإضافة: " . $conn->error);
                    }
                    
                    $stmt->bind_param("siisssssssssdd", 
                        $stock_id, $category_id, $tax_type_id, $description, $long_description, 
                        $units, $mb_flag, $sales_account, $cogs_account, $inventory_account, 
                        $adjustment_account, $wip_account, $purchase_cost, $material_cost);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("فشل في إضافة الصنف: " . $stmt->error);
                    }
                    
                    // إضافة سعر البيع إلى جدول الأسعار
                    $price_sql = "INSERT INTO " . $prefix . "prices (stock_id, sales_type_id, curr_abrev, price) 
                                 VALUES (?, 1, 'ILS', ?)";
                    $price_stmt = $conn->prepare($price_sql);
                    if (!$price_stmt) {
                        throw new Exception("خطأ في إعداد استعلام إضافة السعر: " . $conn->error);
                    }
                    
                    $price_stmt->bind_param("sd", $stock_id, $sales_price);
                    
                    if (!$price_stmt->execute()) {
                        throw new Exception("حدث خطأ في إضافة سعر البيع: " . $price_stmt->error);
                    }
                    
                    // إضافة الكمية الأولية إلى المخزون
                    if ($initial_quantity > 0) {
                        // الحصول على location الافتراضي
                        $location_sql = "SELECT loc_code FROM " . $prefix . "locations WHERE inactive = 0 ORDER BY loc_code LIMIT 1";
                        $location_result = $conn->query($location_sql);
                        $location = $location_result->fetch_assoc();
                        $loc_code = $location ? $location['loc_code'] : 'DEF';
                        
                        // إضافة حركة مخزون للكمية الأولية
                        $movement_sql = "INSERT INTO " . $prefix . "stock_moves 
                                        (stock_id, trans_no, type, loc_code, tran_date, price, reference, qty, standard_cost)
                                        VALUES (?, 0, 0, ?, NOW(), ?, 'الكمية الأولية', ?, ?)";
                        $movement_stmt = $conn->prepare($movement_sql);
                        if (!$movement_stmt) {
                            throw new Exception("خطأ في إعداد استعلام إضافة الحركة: " . $conn->error);
                        }
                        
                        $movement_stmt->bind_param("ssddd", $stock_id, $loc_code, $purchase_cost, $initial_quantity, $purchase_cost);
                        
                        if (!$movement_stmt->execute()) {
                            throw new Exception("حدث خطأ في إضافة الكمية الأولية: " . $movement_stmt->error);
                        }
                        
                        $movement_stmt->close();
                    }
                    
                    // commit transaction
                    $conn->commit();
                    
                    echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> تمت إضافة الصنف والكمية الأولية بنجاح!</div>";
                    
                } catch (Exception $e) {
                    // rollback transaction في حالة خطأ
                    $conn->rollback();
                    echo "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> " . $e->getMessage() . "</div>";
                }
                
                if (isset($stmt)) $stmt->close();
                if (isset($price_stmt)) $price_stmt->close();
            }
            
            $check_stmt->close();
        }
        
        // جلب الأصناف الحالية وعرض الكمية الإجمالية
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
            // إعادة تعيين مؤشر النتائج
            $items_result->data_seek(0);
        }
        ?>
        
        <div class="panel">
            <h2 class="panel-title"><i class="fas fa-plus-circle"></i> نموذج إضافة صنف جديد</h2>
            
            <form method="POST" action="">
                <div class="form-container">
                    <div class="form-group">
                        <label for="stock_id">رقم الصنف (stock_id)*</label>
                        <input type="text" id="stock_id" name="stock_id" placeholder="أدخل رقم الصنف" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">وصف الصنف (description)*</label>
                        <input type="text" id="description" name="description" placeholder="أدخل وصف الصنف" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="long_description">الوصف الطويل (long_description)</label>
                        <textarea id="long_description" name="long_description" rows="2" placeholder="أدخل الوصف الطويل للصنف"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">رقم الفئة (category_id)</label>
                        <input type="number" id="category_id" name="category_id" placeholder="أدخل رقم الفئة" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="tax_type_id">رقم نوع الضريبة (tax_type_id)</label>
                        <input type="number" id="tax_type_id" name="tax_type_id" placeholder="أدخل رقم نوع الضريبة" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="units">الوحدة (units)*</label>
                        <input type="text" id="units" name="units" value="قطعة" placeholder="أدخل الوحدة" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="mb_flag">علامة MB (mb_flag)</label>
                        <select id="mb_flag" name="mb_flag">
                            <option value="B">كل من البيع والشراء (B)</option>
                            <option value="M">للشراء فقط (M)</option>
                            <option value="S">للبيع فقط (S)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="purchase_cost">سعر الشراء (purchase_cost)*</label>
                        <input type="number" id="purchase_cost" name="purchase_cost" placeholder="0.00" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sales_price">سعر البيع (sales_price)*</label>
                        <input type="number" id="sales_price" name="sales_price" placeholder="0.00" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="initial_quantity">الكمية الأولية*</label>
                        <input type="number" id="initial_quantity" name="initial_quantity" placeholder="0" min="0" required value="0">
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
            <h2 class="panel-title"><i class="fas fa-list"></i> الأصناف الموجودة في النظام</h2>
            
            <div class="table-container">
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>رقم الصنف</th>
                            <th>الوصف</th>
                            <th>الوحدة</th>
                            <th>سعر الشراء</th>
                            <th>سعر البيع</th>
                            <th>الكمية الحالية</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($items_result && $items_result->num_rows > 0) {
                            while($row = $items_result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $row['stock_id'] . "</td>";
                                echo "<td>" . $row['description'] . "</td>";
                                echo "<td>" . $row['units'] . "</td>";
                                echo "<td>" . number_format($row['purchase_cost'], 2) . "</td>";
                                echo "<td>" . (isset($row['sales_price']) ? number_format($row['sales_price'], 2) : 'غير محدد') . "</td>";
                                echo "<td>" . $row['current_quantity'] . "</td>";
                                echo "<td>" . (isset($row['inactive']) && $row['inactive'] == 0 ? '<span style="color:green">نشط</span>' : '<span style="color:red">غير نشط</span>') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align:center;padding:20px'>لا توجد أصناف في النظام</td></tr>";
                        }
                        ?>
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

    <script>
        // إضافة تأثيرات عند التركيز على الحقول
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', () => {
                input.parentElement.style.transform = 'translateY(-2px)';
                input.parentElement.style.transition = 'transform 0.3s ease';
            });
            
            input.addEventListener('blur', () => {
                input.parentElement.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>