<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام الذكاء المحاسبي المتكامل</title>
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #7c3aed;
            --accent-color: #dc2626;
            --success-color: #059669;
            --warning-color: #d97706;
            --info-color: #0891b2;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --text-color: #334155;
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .main-container {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            min-height: 90vh;
        }

        /* الشريط الجانبي */
        .sidebar {
            width: 280px;
            background: var(--dark-color);
            color: white;
            padding: 20px 0;
        }

        .user-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            font-weight: bold;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-role {
            font-size: 0.9em;
            opacity: 0.8;
        }

        .main-menu-sidebar {
            list-style: none;
        }

        .menu-item-sidebar {
            padding: 15px 25px;
            cursor: pointer;
            transition: var(--transition);
            border-right: 4px solid transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .menu-item-sidebar:hover {
            background: rgba(255, 255, 255, 0.1);
            border-right-color: var(--primary-color);
        }

        .menu-item-sidebar.active {
            background: rgba(255, 255, 255, 0.15);
            border-right-color: var(--accent-color);
        }

        .menu-icon {
            font-size: 1.2em;
            width: 25px;
            text-align: center;
        }

        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            background: var(--light-color);
            display: flex;
            flex-direction: column;
        }

        .content-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .module-title {
            font-size: 1.8em;
            color: var(--dark-color);
            font-weight: 700;
        }

        .module-subtitle {
            color: var(--text-color);
            opacity: 0.8;
            margin-top: 5px;
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-input {
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            width: 300px;
        }

        .logout-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        /* محتوى الوحدة */
        .module-content {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-top: 4px solid var(--primary-color);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-color);
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            color: white;
            font-size: 1.2em;
        }

        .section-title {
            font-size: 1.3em;
            color: var(--dark-color);
            font-weight: 600;
        }

        .menu-items-detailed {
            list-style: none;
        }

        .menu-item-detailed {
            padding: 12px 15px;
            margin: 8px 0;
            background: var(--light-color);
            border-radius: 6px;
            transition: var(--transition);
            cursor: pointer;
            border-right: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .menu-item-detailed:hover {
            background: var(--primary-color);
            color: white;
            border-right-color: var(--accent-color);
            transform: translateX(-5px);
        }

        /* ألوان الأقسام */
        .sales-section { border-top-color: var(--success-color); }
        .sales-section .section-icon { background: var(--success-color); }

        .purchases-section { border-top-color: var(--warning-color); }
        .purchases-section .section-icon { background: var(--warning-color); }

        .inventory-section { border-top-color: #8b5cf6; }
        .inventory-section .section-icon { background: #8b5cf6; }

        .manufacturing-section { border-top-color: #06b6d4; }
        .manufacturing-section .section-icon { background: #06b6d4; }

        .assets-section { border-top-color: #f59e0b; }
        .assets-section .section-icon { background: #f59e0b; }

        .costcenters-section { border-top-color: #ef4444; }
        .costcenters-section .section-icon { background: #ef4444; }

        .banking-section { border-top-color: #10b981; }
        .banking-section .section-icon { background: #10b981; }

        .system-section { border-top-color: #6b7280; }
        .system-section .section-icon { background: #6b7280; }

        /* شاشة تسجيل الدخول */
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 1.8em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .login-form {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark-color);
            font-weight: 600;
            font-size: 0.9em;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1em;
            transition: var(--transition);
            background: var(--light-color);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.3);
        }
    </style>
</head>
<body>
    <!-- شاشة تسجيل الدخول -->
    <div class="login-container" id="loginContainer">
        <div class="login-header">
            <h1>نظام الذكاء المحاسبي</h1>
            <div class="subtitle">الإصدار المتقدم 2025</div>
        </div>
        <form class="login-form" id="loginForm">
            <div class="form-group">
                <label for="username">اسم المشغل</label>
                <input type="text" id="username" class="form-input" placeholder="أدخل اسم المستخدم" required>
            </div>
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" class="form-input" placeholder="أدخل كلمة المرور" required>
            </div>
            <button type="submit" class="login-btn">تسجيل الدخول</button>
        </form>
    </div>

    <!-- القائمة الرئيسية -->
    <div class="main-container" id="mainContainer" style="display: none;">
        <!-- الشريط الجانبي -->
        <div class="sidebar">
            <div class="user-info">
                <div class="user-avatar">م</div>
                <div class="user-name" id="userDisplayName">محمد أحمد</div>
                <div class="user-role">مدير النظام</div>
            </div>
            
            <ul class="main-menu-sidebar">
                <li class="menu-item-sidebar active" onclick="showModule('sales')">
                    <span class="menu-icon">💳</span>
                    <span>المبيعات الرقمية</span>
                </li>
                <li class="menu-item-sidebar" onclick="showModule('purchases')">
                    <span class="menu-icon">🤖</span>
                    <span>المشتريات الذكية</span>
                </li>
                <li class="menu-item-sidebar" onclick="showModule('inventory')">
                    <span class="menu-icon">📊</span>
                    <span>ذكاء المخزون</span>
                </li>
                <li class="menu-item-sidebar" onclick="showModule('manufacturing')">
                    <span class="menu-icon">⚙️</span>
                    <span>الذكاء التصنيعي</span>
                </li>
                <li class="menu-item-sidebar" onclick="showModule('assets')">
                    <span class="menu-icon">🏢</span>
                    <span>إدارة الأصول</span>
                </li>
                <li class="menu-item-sidebar" onclick="showModule('costcenters')">
                    <span class="menu-icon">📈</span>
                    <span>مراكز التكلفة</span>
                </li>
                <li class="menu-item-sidebar" onclick="showModule('banking')">
                    <span class="menu-icon">🏛️</span>
                    <span>المحور المالي</span>
                </li>
                <li class="menu-item-sidebar" onclick="showModule('system')">
                    <span class="menu-icon">🎮</span>
                    <span>مركز التحكم</span>
                </li>
            </ul>
        </div>

        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <div class="content-header">
                <div>
                    <div class="module-title" id="moduleTitle">المبيعات الرقمية</div>
                    <div class="module-subtitle" id="moduleSubtitle">إدارة المبيعات والعملاء والطلبات</div>
                </div>
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="بحث في القائمة...">
                    <button class="logout-btn" onclick="logout()">تسجيل الخروج</button>
                </div>
            </div>

            <div class="module-content" id="moduleContent">
                <!-- محتوى المبيعات -->
                <div class="module-grid" id="salesModule">
                    <!-- معاملات المبيعات -->
                    <div class="section sales-section">
                        <div class="section-header">
                            <div class="section-icon">📝</div>
                            <div class="section-title">معاملات المبيعات</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_order_entry.php?NewQuotation=Yes')">
                                إدخال عرض سعر <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_order_entry.php?NewOrder=Yes')">
                                إدخال طلبات البيع <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_order_entry.php?NewDelivery=0')">
                                إشعار تسليم مباشر <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_order_entry.php?NewInvoice=0')">
                                فاتورة فورية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/point_of_sale.php')">
                                نظام نقاط البيع (POS) <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- عمليات التحويل -->
                    <div class="section sales-section">
                        <div class="section-header">
                            <div class="section-icon">🔄</div>
                            <div class="section-title">عمليات التحويل</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_order_convert.php?type=order_to_delivery')">
                                تحويل طلبات البيع إلى إشعار تسليم <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_order_convert.php?type=delivery_to_invoice')">
                                تحويل إشعار التسليم إلى فاتورة مبيعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/prepaid_invoices.php')">
                                الفواتير والطلبات المدفوعة مسبقًا <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- النماذج والمستندات -->
                    <div class="section sales-section">
                        <div class="section-header">
                            <div class="section-icon">📑</div>
                            <div class="section-title">النماذج والمستندات</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('sales/delivery_templates.php')">
                                نماذج سندات التسليم الجاهزة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/invoice_templates.php')">
                                نماذج الفواتير الجاهزة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/recurring_invoices.php')">
                                إنشاء وطباعة الفواتير المتكررة <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- المقبوضات والحسابات -->
                    <div class="section sales-section">
                        <div class="section-header">
                            <div class="section-icon">💰</div>
                            <div class="section-title">المقبوضات والحسابات</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('sales/customer_payments.php')">
                                مقبوضات الزبائن <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/customer_credit_notes.php')">
                                الإشعارات الدائنة للزبون <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/payment_allocation.php')">
                                تخصيص مقبوضات الزبون <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/credit_note_allocation.php')">
                                تخصيص الإشعارات الدائنة <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الاستعلامات والتقارير -->
                    <div class="section sales-section">
                        <div class="section-header">
                            <div class="section-icon">🔍</div>
                            <div class="section-title">الاستعلامات والتقارير</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('sales/quotation_inquiry.php')">
                                الاستعلام عن عرض سعر <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_order_inquiry.php')">
                                الاستعلام عن طلبات المبيعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/customer_transactions.php')">
                                الاستعلام عن حركات الزبون <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/payment_allocation_inquiry.php')">
                                الاستعلام عن تخصيص الدفعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_reports.php')">
                                تقارير المبيعات والزبائن <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الإعدادات الأساسية -->
                    <div class="section sales-section">
                        <div class="section-header">
                            <div class="section-icon">⚙️</div>
                            <div class="section-title">الإعدادات الأساسية</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('sales/customer_management.php')">
                                إضافة وإدارة الزبائن <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/customer_branches.php')">
                                فروع الزبون <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_groups.php')">
                                مجموعات المبيعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/recurring_invoices_setup.php')">
                                الفواتير الدورية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_types.php')">
                                أنواع المبيعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/salesmen.php')">
                                مندوبي المبيعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/sales_areas.php')">
                                مناطق البيع <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('sales/credit_status.php')">
                                إعدادات حالة الائتمان <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- محتوى المشتريات -->
                <div class="module-grid" id="purchasesModule" style="display: none;">
                    <!-- معاملات المشتريات -->
                    <div class="section purchases-section">
                        <div class="section-header">
                            <div class="section-icon">📋</div>
                            <div class="section-title">معاملات المشتريات</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('purchases/purchase_order_entry.php?NewOrder=Yes')">
                                إدخال أمر الشراء <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/delayed_orders.php')">
                                الرقابة على أوامر الشراء المتأخرة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/direct_delivery.php')">
                                إشعار تسليم مباشر <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_invoice.php')">
                                فاتورة المورد المباشر <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_payments.php')">
                                مدفوعات الموردين <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الفواتير والحسابات -->
                    <div class="section purchases-section">
                        <div class="section-header">
                            <div class="section-icon">🧾</div>
                            <div class="section-title">الفواتير والحسابات</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_invoices.php')">
                                فواتير الموردين <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_credit_notes.php')">
                                الإشعارات الدائنة للموردين <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_payment_allocation.php')">
                                تخصيص مدفوعات الموردين <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_credit_allocation.php')">
                                تخصيص الإشعارات الدائنة للموردين <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الاستعلامات والتقارير -->
                    <div class="section purchases-section">
                        <div class="section-header">
                            <div class="section-icon">🔍</div>
                            <div class="section-title">الاستعلامات والتقارير</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('purchases/purchase_order_inquiry.php')">
                                الاستعلام عن أوامر الشراء <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_transactions.php')">
                                الاستعلام عن حركات المورد <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_allocation_inquiry.php')">
                                الاستعلام عن تخصيص الدفعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('purchases/supplier_reports.php')">
                                تقارير الموردين والمشتريات <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- محتوى المخزون -->
                <div class="module-grid" id="inventoryModule" style="display: none;">
                    <!-- معاملات المخزون -->
                    <div class="section inventory-section">
                        <div class="section-header">
                            <div class="section-icon">📦</div>
                            <div class="section-title">معاملات المخزون</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('inventory/internal_transfer.php')">
                                إرسالية داخلية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/inventory_adjustment.php')">
                                قيود التعديل المخزنية <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الاستعلامات والتقارير -->
                    <div class="section inventory-section">
                        <div class="section-header">
                            <div class="section-icon">🔍</div>
                            <div class="section-title">الاستعلامات والتقارير</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('inventory/item_movements.php')">
                                حركات بنود المخزون <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/item_status.php')">
                                حالة بنود المخزون <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/inventory_reports.php')">
                                تقارير المخزون <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- النماذج الأساسية -->
                    <div class="section inventory-section">
                        <div class="section-header">
                            <div class="section-icon">🏷️</div>
                            <div class="section-title">النماذج الأساسية</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('inventory/items_management.php')">
                                البنود المخزنية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/foreign_codes.php')">
                                أكواد البنود المخزنية الأجنبية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/sales_tools.php')">
                                أدوات البيع <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/item_categories.php')">
                                تصنيفات بنود المخزون <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/stock_locations.php')">
                                مواقع التخزين <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/units_of_measure.php')">
                                وحدات القياس <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/reorder_levels.php')">
                                مستويات إعادة الطلب <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- التسعير والتكاليف -->
                    <div class="section inventory-section">
                        <div class="section-header">
                            <div class="section-icon">💰</div>
                            <div class="section-title">التسعير والتكاليف</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('inventory/sales_pricing.php')">
                                التسعير البيعي <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/purchase_pricing.php')">
                                التسعير الشرائي <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('inventory/standard_costs.php')">
                                التكاليف المعيارية <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- محتوى التصنيع -->
                <div class="module-grid" id="manufacturingModule" style="display: none;">
                    <!-- أوامر التشغيل -->
                    <div class="section manufacturing-section">
                        <div class="section-header">
                            <div class="section-icon">⚙️</div>
                            <div class="section-title">أوامر التشغيل</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/work_order_entry.php')">
                                إدخال أوامر التشغيل <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/delayed_work_orders.php')">
                                أوامر التشغيل المتأخرة <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الاستعلامات والتقارير -->
                    <div class="section manufacturing-section">
                        <div class="section-header">
                            <div class="section-icon">🔍</div>
                            <div class="section-title">الاستعلامات والتقارير</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/bom_cost_inquiry.php')">
                                الاستعلام عن تكاليف قائمة المواد <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/used_items_inquiry.php')">
                                الاستعلام عن البنود المستخدمة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/work_order_inquiry.php')">
                                الاستعلام عن أوامر التشغيل <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/manufacturing_reports.php')">
                                تقارير العمليات التصنيعية <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- النماذج الأساسية -->
                    <div class="section manufacturing-section">
                        <div class="section-header">
                            <div class="section-icon">📋</div>
                            <div class="section-title">النماذج الأساسية</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/bom_management.php')">
                                قائمة تركيبة المواد <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('manufacturing/cost_centers.php')">
                                مراكز التكلفة <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- محتوى الأصول -->
                <div class="module-grid" id="assetsModule" style="display: none;">
                    <!-- معاملات الأصول -->
                    <div class="section assets-section">
                        <div class="section-header">
                            <div class="section-icon">🏢</div>
                            <div class="section-title">معاملات الأصول</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_purchase.php')">
                                شراء أصول ثابتة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_transfer.php')">
                                مواقع ونقل الأصول الثابتة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_disposal.php')">
                                إتلاف الأصول الثابتة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_sale.php')">
                                بيع أصول <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/assets_depreciation.php')">
                                الأصول والاستهلاكات <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الاستعلامات والتقارير -->
                    <div class="section assets-section">
                        <div class="section-header">
                            <div class="section-icon">🔍</div>
                            <div class="section-title">الاستعلامات والتقارير</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_transfer_inquiry.php')">
                                نقل الأصول الثابتة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_inquiry.php')">
                                استعلام الأصول الثابتة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_reports.php')">
                                تقارير الأصول الثابتة <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- النماذج الأساسية -->
                    <div class="section assets-section">
                        <div class="section-header">
                            <div class="section-icon">📋</div>
                            <div class="section-title">النماذج الأساسية</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('assets/fixed_assets.php')">
                                الأصول الثابتة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_locations.php')">
                                موقع تخزين الأصول <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_categories.php')">
                                تصنيفات الأصول الثابتة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('assets/asset_classes.php')">
                                فئات الأصول الثابتة <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- محتوى مراكز التكلفة -->
                <div class="module-grid" id="costcentersModule" style="display: none;">
                    <!-- إدارة مراكز التكلفة -->
                    <div class="section costcenters-section">
                        <div class="section-header">
                            <div class="section-icon">📊</div>
                            <div class="section-title">إدارة مراكز التكلفة</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('costcenters/cost_center_entry.php')">
                                إدخال مراكز التكلفة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('costcenters/closed_cost_centers.php')">
                                مراكز التكلفة المنتهية <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الاستعلامات والتقارير -->
                    <div class="section costcenters-section">
                        <div class="section-header">
                            <div class="section-icon">🔍</div>
                            <div class="section-title">الاستعلامات والتقارير</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('costcenters/cost_center_inquiry.php')">
                                الاستعلام عن مراكز التكلفة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('costcenters/cost_center_reports.php')">
                                تقارير مراكز التكلفة <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- النماذج الأساسية -->
                    <div class="section costcenters-section">
                        <div class="section-header">
                            <div class="section-icon">📋</div>
                            <div class="section-title">النماذج الأساسية</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('costcenters/cost_centers_keywords.php')">
                                مراكز التكلفة والكلمات الدلالية <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- محتوى البنوك -->
                <div class="module-grid" id="bankingModule" style="display: none;">
                    <!-- المعاملات البنكية -->
                    <div class="section banking-section">
                        <div class="section-header">
                            <div class="section-icon">🏦</div>
                            <div class="section-title">المعاملات البنكية</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('banking/payments.php')">
                                الدفعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/deposits.php')">
                                الإيداعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/bank_transfer.php')">
                                التحويل بين الحسابات البنكية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/journal_entry.php')">
                                إدخال قيود اليومية العامة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/budget_entry.php')">
                                إدخال الموازنات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/bank_reconciliation.php')">
                                تسوية الحساب البنكي <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/accruals.php')">
                                الإيرادات/المصروفات المستحقة <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الاستعلامات والتقارير -->
                    <div class="section banking-section">
                        <div class="section-header">
                            <div class="section-icon">🔍</div>
                            <div class="section-title">الاستعلامات والتقارير</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('banking/journal_inquiry.php')">
                                الاستعلام عن دفتر اليومية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/gl_inquiry.php')">
                                الاستعلام عن الأستاذ العام <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/bank_accounts_inquiry.php')">
                                الاستعلام عن الحسابات البنكية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/tax_inquiry.php')">
                                الاستعلام عن الضريبة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/trial_balance.php')">
                                ميزان المراجعة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/balance_sheet.php')">
                                الميزانية العمومية المختصرة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/profit_loss.php')">
                                قائمة الأرباح والخسائر المختصرة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/bank_reports.php')">
                                التقارير البنكية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/gl_reports.php')">
                                تقارير الأستاذ العام <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- النماذج الأساسية -->
                    <div class="section banking-section">
                        <div class="section-header">
                            <div class="section-icon">📋</div>
                            <div class="section-title">النماذج الأساسية</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('banking/bank_accounts.php')">
                                الحسابات البنكية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/quick_entries.php')">
                                الإدخالات السريعة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/account_keywords.php')">
                                الكلمات الدلالية للحساب <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/currencies.php')">
                                العملات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/exchange_rates.php')">
                                معدل تبادل العملات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/gl_accounts.php')">
                                حسابات الأستاذ العام <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/gl_account_groups.php')">
                                مجموعات حسابات الأستاذ العام <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/gl_account_classes.php')">
                                التصنيفات الرئيسية للحسابات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/gl_close.php')">
                                إغلاق حركة اليومية العامة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('banking/currency_auto_update.php')">
                                تحديث قيمة العملة تلقائياً <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- محتوى النظام -->
                <div class="module-grid" id="systemModule" style="display: none;">
                    <!-- الإعدادات -->
                    <div class="section system-section">
                        <div class="section-header">
                            <div class="section-icon">⚙️</div>
                            <div class="section-title">إعدادات النظام</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('system/company_settings.php')">
                                إعدادات الشركة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/user_accounts.php')">
                                إعدادات حسابات المستخدمين <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/access_settings.php')">
                                إعدادات الوصول <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/display_settings.php')">
                                إعدادات العرض <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/transaction_reference.php')">
                                الإشارة المرجعية للحركة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/taxes.php')">
                                الضرائب <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/tax_groups.php')">
                                مجموعات الضرائب <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/item_tax_types.php')">
                                أنواع ضرائب البنود المخزنية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/system_settings.php')">
                                الإعدادات العامة للنظام <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/fiscal_years.php')">
                                السنوات المالية <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/print_settings.php')">
                                إعدادات الطباعة <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- متنوعة -->
                    <div class="section system-section">
                        <div class="section-header">
                            <div class="section-icon">🔧</div>
                            <div class="section-title">إعدادات متنوعة</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('system/payment_terms.php')">
                                شروط الدفع <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/shipping_company.php')">
                                شركة الشحن <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/point_of_sale_settings.php')">
                                نقاط البيع <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/printers.php')">
                                الطابعات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/contact_categories.php')">
                                تصنيفات الاتصالات <i>➜</i>
                            </li>
                        </ul>
                    </div>

                    <!-- الأدوات والإدارة -->
                    <div class="section system-section">
                        <div class="section-header">
                            <div class="section-icon">🛠️</div>
                            <div class="section-title">الأدوات والإدارة</div>
                        </div>
                        <ul class="menu-items-detailed">
                            <li class="menu-item-detailed" onclick="openLink('system/void_transaction.php')">
                                إلغاء حركة <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/view_print_transactions.php')">
                                عرض أو طباعة الحركات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/document_attachment.php')">
                                أرفاق المستندات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/system_diagnostics.php')">
                                تشخيص النظام <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/backup_restore.php')">
                                النسخ الاحتياطي والاسترجاع <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/company_management.php')">
                                إنشاء وتحديث الشركات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/language_management.php')">
                                تثبيت/تحديث اللغات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/extensions_management.php')">
                                تثبيت/تفعيل الامتدادات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/themes_management.php')">
                                تثبيت/تفعيل التنسيقات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/chart_of_accounts_management.php')">
                                تثبيت/تفعيل خريطة الحسابات <i>➜</i>
                            </li>
                            <li class="menu-item-detailed" onclick="openLink('system/software_update.php')">
                                تحديث البرنامج <i>➜</i>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // بيانات المستخدمين
        const users = [
            { username: "admin", password: "123456", name: "محمد أحمد", role: "مدير النظام" },
            { username: "user", password: "123456", name: "أحمد محمود", role: "مشرف مبيعات" },
            { username: "accountant", password: "123456", name: "فاطمة علي", role: "محاسب رئيسي" }
        ];

        // تسجيل الدخول
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            const user = users.find(u => u.username === username && u.password === password);
            
            if (user) {
                document.getElementById('userDisplayName').textContent = user.name;
                document.getElementById('loginContainer').style.display = 'none';
                document.getElementById('mainContainer').style.display = 'flex';
            } else {
                alert('اسم المستخدم أو كلمة المرور غير صحيحة');
            }
        });

        // تسجيل الخروج
        function logout() {
            if (confirm('هل تريد تسجيل الخروج؟')) {
                document.getElementById('mainContainer').style.display = 'none';
                document.getElementById('loginContainer').style.display = 'block';
                document.getElementById('loginForm').reset();
            }
        }

        // عرض الوحدة المحددة
        function showModule(module) {
            // إخفاء جميع الوحدات
            document.querySelectorAll('.module-grid').forEach(mod => {
                mod.style.display = 'none';
            });
            
            // إزالة النشاط من جميع عناصر القائمة
            document.querySelectorAll('.menu-item-sidebar').forEach(item => {
                item.classList.remove('active');
            });
            
            // إضافة النشاط للعنصر المحدد
            event.currentTarget.classList.add('active');
            
            // عرض الوحدة المحددة
            document.getElementById(module + 'Module').style.display = 'grid';
            
            // تحديث العنوان
            const titles = {
                sales: { title: 'المبيعات الرقمية', subtitle: 'إدارة المبيعات والعملاء والطلبات' },
                purchases: { title: 'المشتريات الذكية', subtitle: 'إدارة المشتريات والموردين' },
                inventory: { title: 'ذكاء المخزون', subtitle: 'إدارة المخزون والمواد' },
                manufacturing: { title: 'الذكاء التصنيعي', subtitle: 'إدارة العمليات الإنتاجية' },
                assets: { title: 'إدارة الأصول', subtitle: 'إدارة الأصول الثابتة والمستثمرات' },
                costcenters: { title: 'مراكز التكلفة', subtitle: 'إدارة وتحليل مراكز التكلفة' },
                banking: { title: 'المحور المالي', subtitle: 'إدارة الحسابات والبنوك' },
                system: { title: 'مركز التحكم', subtitle: 'إعدادات النظام والأمان' }
            };
            
            document.getElementById('moduleTitle').textContent = titles[module].title;
            document.getElementById('moduleSubtitle').textContent = titles[module].subtitle;
        }

        // فتح الرابط - يمكنك تعديل الدومين الأساسي هنا
        function openLink(relativePath) {
            const baseDomain = 'https://alsadiqaltaieb.org.ps/macsproacc/';
            const fullUrl = baseDomain + relativePath;
            
            // يمكنك استخدام أي من الطريقتين:
            
            // الطريقة 1: فتح في نافذة جديدة
            window.open(fullUrl, '_blank');
            
            // الطريقة 2: فتح في نفس النافذة
            // window.location.href = fullUrl;
            
            console.log('فتح الرابط:', fullUrl);
        }

        // إضافة تفاعل لعناصر القائمة
        document.querySelectorAll('.menu-item-detailed').forEach(item => {
            item.addEventListener('click', function() {
                const itemText = this.textContent.trim();
                console.log('النقر على:', itemText);
            });
        });
    </script>
</body>
</html>