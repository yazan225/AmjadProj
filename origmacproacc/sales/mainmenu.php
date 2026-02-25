<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام نقطة البيع - FrontAccounting POS</title>
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
            padding: 15px;
            direction: rtl;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
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
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 15px;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
            flex: 1;
        }
        
        .tab.active {
            background: var(--primary);
            color: white;
        }
        
        .tab:hover:not(.active) {
            background: var(--gray-light);
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
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
        
        .database-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .database-table th {
            background: var(--primary);
            color: white;
            padding: 12px;
            text-align: right;
        }
        
        .database-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--gray-light);
        }
        
        .database-table tr:nth-child(even) {
            background: var(--gray-light);
        }
        
        .database-table tr:hover {
            background: #e8f0fe;
        }
        
        .connection-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-card {
            background: var(--gray-light);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .info-card h3 {
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .info-card p {
            font-size: 16px;
            font-weight: 500;
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
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            border-radius: 8px;
            padding: 15px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            margin-top: 15px;
        }
        
        .keyword {
            color: #f92672;
        }
        
        .function {
            color: #66d9ef;
        }
        
        .string {
            color: #a6e22e;
        }
        
        .comment {
            color: #75715e;
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            .connection-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-database"></i>
                <h1>نظام نقطة البيع - ملفات قاعدة البيانات</h1>
            </div>
            <div class="user-info">
                <div class="datetime">
                    <i class="fas fa-clock"></i>
                    <span id="currentDateTime"></span>
                </div>
            </div>
        </header>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('database')">هيكل قاعدة البيانات</div>
            <div class="tab" onclick="showTab('connection')">إعدادات الاتصال</div>
            <div class="tab" onclick="showTab('queries')">الاستعلامات المستخدمة</div>
        </div>
        
        <div class="content">
            <div class="panel full-width" id="database">
                <h2 class="panel-title"><i class="fas fa-table"></i> الجداول المستخدمة في النظام</h2>
                
                <table class="database-table">
                    <thead>
                        <tr>
                            <th width="20%">اسم الجدول</th>
                            <th width="40%">الوصف</th>
                            <th width="40%">الاستخدام في النظام</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>debtors_master</td>
                            <td>يحتوي على معلومات العملاء والدائنين</td>
                            <td>البحث عن عميل "Walk-In" وعرض معلومات العملاء</td>
                        </tr>
                        <tr>
                            <td>stock_master</td>
                            <td>يخزن المعلومات الأساسية للمنتجات والأصناف</td>
                            <td>جلب معلومات المنتجات وأسعارها وكمياتها</td>
                        </tr>
                        <tr>
                            <td>stock_moves</td>
                            <td>يسجل حركة المخزون من عمليات البيع والشراء</td>
                            <td>حساب الكميات المتاحة في المخزون</td>
                        </tr>
                        <tr>
                            <td>prices</td>
                            <td>يحتوي على أسعار البيع للمنتجات بأنواع مختلفة</td>
                            <td>جلب سعر البيع الحالي لكل منتج</td>
                        </tr>
                        <tr>
                            <td>debtor_trans</td>
                            <td>يسجل معاملات البيع والدفعات من العملاء</td>
                            <td>تسجيل فواتير البيع والمدفوعات</td>
                        </tr>
                        <tr>
                            <td>debtor_trans_details</td>
                            <td>يحتوي على تفاصيل بنود فواتير البيع</td>
                            <td>تسجيل الأصناف المباعة في كل فاتورة</td>
                        </tr>
                    </tbody>
                </table>
                
                <h2 class="panel-title" style="margin-top: 25px;"><i class="fas fa-list"></i> الحقول الأساسية في الجداول</h2>
                
                <div class="connection-info">
                    <div class="info-card">
                        <h3>debtors_master</h3>
                        <p>debtor_no, debtor_ref, name, address, tax_id, curr_code, sales_type, credit_status, payment_terms</p>
                    </div>
                    <div class="info-card">
                        <h3>stock_master</h3>
                        <p>stock_id, description, long_description, category_id, tax_type_id, units, mb_flag, sales_account, inventory_account</p>
                    </div>
                    <div class="info-card">
                        <h3>debtor_trans</h3>
                        <p>trans_no, type, version, debtor_no, branch_code, tran_date, due_date, reference, tpe, ov_amount, ov_discount, alloc</p>
                    </div>
                    <div class="info-card">
                        <h3>debtor_trans_details</h3>
                        <p>id, debtor_trans_no, debtor_trans_type, stock_id, description, unit_price, unit_tax, quantity, discount_percent, standard_cost, qty_done</p>
                    </div>
                </div>
            </div>
            
            <div class="panel full-width" id="connection" style="display: none;">
                <h2 class="panel-title"><i class="fas fa-network-wired"></i> إعدادات اتصال قاعدة البيانات</h2>
                
                <div class="connection-info">
                    <div class="info-card">
                        <h3>اسم المضيف (Host)</h3>
                        <p>localhost</p>
                    </div>
                    <div class="info-card">
                        <h3>اسم قاعدة البيانات</h3>
                        <p>fa_2418</p>
                    </div>
                    <div class="info-card">
                        <h3>اسم المستخدم</h3>
                        <p>usr1234</p>
                    </div>
                    <div class="info-card">
                        <h3>كلمة المرور</h3>
                        <p>@@pass@@x123</p>
                    </div>
                    <div class="info-card">
                        <h3>بادئة الجداول (Prefix)</h3>
                        <p>يتم جلبها من إعدادات الشركة في FrontAccounting</p>
                    </div>
                    <div class="info-card">
                        <h3>نظام إدارة القاعدة</h3>
                        <p>MySQL</p>
                    </div>
                </div>
                
                <h2 class="panel-title" style="margin-top: 25px;"><i class="fas fa-code"></i> كود الاتصال بقاعدة البيانات</h2>
                
                <div class="code-block">
                    <span class="keyword">$host</span> = <span class="string">'localhost'</span>;<br>
                    <span class="keyword">$user</span> = <span class="string">'usr1234'</span>;<br>
                    <span class="keyword">$pass</span> = <span class="string">'@@pass@@x123'</span>;<br>
                    <span class="keyword">$dbname</span> = <span class="string">'fa_2418'</span>;<br><br>
                    
                    <span class="keyword">$conn</span> = <span class="function">new mysqli</span>($host, $user, $pass, $dbname);<br>
                    <span class="keyword">if</span> ($conn->connect_error) {<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;<span class="function">die</span>(<span class="string">"فشل الاتصال بقاعدة البيانات: "</span> . $conn->connect_error);<br>
                    }<br>
                    $conn->set_charset(<span class="string">"utf8"</span>);
                </div>
            </div>
            
            <div class="panel full-width" id="queries" style="display: none;">
                <h2 class="panel-title"><i class="fas fa-search"></i> الاستعلامات الأساسية في النظام</h2>
                
                <h3 style="margin: 15px 0 10px 0; color: var(--primary);">استعلام جلب عميل Walk-In</h3>
                <div class="code-block">
                    <span class="keyword">SELECT</span> debtor_no <span class="keyword">FROM</span> {$prefix}debtors_master<br>
                    <span class="keyword">WHERE</span> debtor_ref = <span class="string">'2'</span> <span class="keyword">OR</span> name <span class="keyword">LIKE</span> <span class="string">'%Walk-In Customer%'</span><br>
                    <span class="keyword">LIMIT</span> 1
                </div>
                
                <h3 style="margin: 15px 0 10px 0; color: var(--primary);">استعلام جلب الأصناف والكميات</h3>
                <div class="code-block">
                    <span class="keyword">SELECT</span><br>
                    &nbsp;&nbsp;&nbsp;&nbsp;sm.stock_id,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;sm.description,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;sm.purchase_cost,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;<span class="function">COALESCE</span>(<span class="function">SUM</span>(stm.qty), 0) <span class="keyword">as</span> on_hand_qty<br>
                    <span class="keyword">FROM</span> {$prefix}stock_master sm<br>
                    <span class="keyword">LEFT JOIN</span> {$prefix}stock_moves stm <span class="keyword">ON</span> sm.stock_id = stm.stock_id<br>
                    <span class="keyword">GROUP BY</span> sm.stock_id, sm.description, sm.purchase_cost<br>
                    <span class="keyword">ORDER BY</span> sm.description <span class="keyword">ASC</span>
                </div>
                
                <h3 style="margin: 15px 0 10px 0; color: var(--primary);">استعلام إضافة فاتورة جديدة</h3>
                <div class="code-block">
                    <span class="keyword">INSERT INTO</span> {$prefix}debtor_trans<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;(trans_no, type, version, debtor_no, branch_code, tran_date, due_date, reference, tpe, ov_amount, ov_discount)<br>
                    <span class="keyword">VALUES</span> (?, ?, 0, ?, '', ?, ?, ?, 0, ?, ?)
                </div>
                
                <h3 style="margin: 15px 0 10px 0; color: var(--primary);">استعلام إضافة تفاصيل الفاتورة</h3>
                <div class="code-block">
                    <span class="keyword">INSERT INTO</span> {$prefix}debtor_trans_details<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;(debtor_trans_no, debtor_trans_type, stock_id, description, unit_price, unit_tax, quantity, discount_percent, standard_cost, qty_done, src_id)<br>
                    <span class="keyword">VALUES</span> (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 0)
                </div>
            </div>
        </div>
        
        <div class="panel">
            <h2 class="panel-title"><i class="fas fa-info-circle"></i> معلومات حول النظام</h2>
            <p>هذا النظام يعتمد على FrontAccounting ويستخدم الجداول الأساسية الخاصة به لإدارة المبيعات والمخزون والعملاء.</p>
            <p>يتم الاتصال بقاعدة البيانات باستخدام إعدادات محددة ويتم استخدام بادئة الجداول الخاصة بكل شركة.</p>
            
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>الاتصال بقاعدة البيانات يعمل بشكل صحيح وجميع الجداول المطلوبة متوفرة</div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.panel').forEach(panel => {
                panel.style.display = 'none';
            });
            
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabName).style.display = 'block';
            event.currentTarget.classList.add('active');
        }
        
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
    </script>
</body>
</html>