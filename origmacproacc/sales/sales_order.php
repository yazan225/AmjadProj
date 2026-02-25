<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System - Sales Management</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            display: grid;
            grid-template-columns: 300px 1fr;
            grid-template-rows: 70px 1fr 60px;
            grid-template-areas: 
                "header header"
                "sidebar main"
                "footer footer";
            height: 100vh;
        }
        
        header {
            grid-area: header;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        aside {
            grid-area: sidebar;
            background-color: white;
            padding: 20px;
            border-right: 1px solid #ddd;
            overflow-y: auto;
        }
        
        .nav-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-item:hover {
            background-color: var(--light);
        }
        
        .nav-item.active {
            background-color: var(--secondary);
            color: white;
        }
        
        main {
            grid-area: main;
            padding: 20px;
            overflow-y: auto;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-completed {
            background-color: #e7f6e9;
            color: #2ecc71;
        }
        
        .status-pending {
            background-color: #fef5e6;
            color: #f39c12;
        }
        
        footer {
            grid-area: footer;
            background-color: var(--dark);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            background-color: var(--success);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .tab-container {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--secondary);
            color: var(--secondary);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .search-box {
            display: flex;
            margin-bottom: 20px;
        }
        
        .search-box input {
            flex: 1;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .search-box button {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
            color: var(--secondary);
        }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">POS System</div>
            <div class="user-info">
                <div class="avatar">JS</div>
                <div>John Smith</div>
            </div>
        </header>
        
        <aside>
            <div class="nav-item active">
                <i class="icon">📊</i> Dashboard
            </div>
            <div class="nav-item">
                <i class="icon">🛒</i> Sales Orders
            </div>
            <div class="nav-item">
                <i class="icon">📋</i> Quotations
            </div>
            <div class="nav-item">
                <i class="icon">🚚</i> Deliveries
            </div>
            <div class="nav-item">
                <i class="icon">🧾</i> Invoices
            </div>
            <div class="nav-item">
                <i class="icon">👥</i> Customers
            </div>
            <div class="nav-item">
                <i class="icon">📦</i> Products
            </div>
            <div class="nav-item">
                <i class="icon">📈</i> Reports
            </div>
            <div class="nav-item">
                <i class="icon">⚙️</i> Settings
            </div>
        </aside>
        
        <main>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">Today's Sales</div>
                    <div class="stat-value">$3,458</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Orders</div>
                    <div class="stat-value">12</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">New Customers</div>
                    <div class="stat-value">5</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Inventory Alert</div>
                    <div class="stat-value">3</div>
                </div>
            </div>
            
            <div class="tab-container">
                <div class="tab active" data-tab="orders">Orders</div>
                <div class="tab" data-tab="quotations">Quotations</div>
                <div class="tab" data-tab="deliveries">Deliveries</div>
                <div class="tab" data-tab="invoices">Invoices</div>
            </div>
            
            <div class="tab-content active" id="orders">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Sales Orders</div>
                        <button class="btn btn-primary">New Order</button>
                    </div>
                    
                    <div class="search-box">
                        <input type="text" placeholder="Search orders...">
                        <button class="btn btn-primary">Search</button>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>SO-10025</td>
                                <td>John Doe</td>
                                <td>2023-10-15</td>
                                <td>$1,245.00</td>
                                <td><span class="status status-completed">Completed</span></td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary">View</button>
                                    <button class="btn btn-danger">Cancel</button>
                                </td>
                            </tr>
                            <tr>
                                <td>SO-10024</td>
                                <td>Jane Smith</td>
                                <td>2023-10-14</td>
                                <td>$845.50</td>
                                <td><span class="status status-pending">Pending</span></td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary">View</button>
                                    <button class="btn btn-danger">Cancel</button>
                                </td>
                            </tr>
                            <tr>
                                <td>SO-10023</td>
                                <td>Robert Johnson</td>
                                <td>2023-10-13</td>
                                <td>$2,120.00</td>
                                <td><span class="status status-completed">Completed</span></td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary">View</button>
                                    <button class="btn btn-danger">Cancel</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-content" id="quotations">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Sales Quotations</div>
                        <button class="btn btn-primary">New Quotation</button>
                    </div>
                    <!-- Quotation content would go here -->
                </div>
            </div>
            
            <div class="tab-content" id="deliveries">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Delivery Notes</div>
                        <button class="btn btn-primary">New Delivery</button>
                    </div>
                    <!-- Delivery content would go here -->
                </div>
            </div>
            
            <div class="tab-content" id="invoices">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Sales Invoices</div>
                        <button class="btn btn-primary">New Invoice</button>
                    </div>
                    <!-- Invoice content would go here -->
                </div>
            </div>
        </main>
        
        <footer>
            <div>POS System v2.0</div>
            <div>© 2023 FrontAccounting LLC. All rights reserved.</div>
        </footer>
    </div>
    
    <div class="notification" id="notification">
        Order has been successfully processed!
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
        
        // Simulate a successful order placement
        function showNotification() {
            const notification = document.getElementById('notification');
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        // Simulate adding a new order
        document.querySelector('.card-header .btn').addEventListener('click', () => {
            showNotification();
        });
    </script>
</body>
</html>