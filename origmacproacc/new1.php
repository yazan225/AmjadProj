<?php
// Include FrontAccounting config
require_once('config_db.php');

// Database connection function
function db_connect() {
    global $db_connections, $def_coy;
    $conn = new mysqli(
        $db_connections[$def_coy]['host'],
        $db_connections[$def_coy]['dbuser'],
        $db_connections[$def_coy]['dbpassword'],
        $db_connections[$def_coy]['dbname']
    );
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Fetch products from database
function get_products($category = null) {
    $conn = db_connect();
    $tbpref = $GLOBALS['db_connections'][$GLOBALS['def_coy']]['tbpref'];
    
    $sql = "SELECT stock_id, stock_name, stock_description, units_price, category_id 
            FROM {$tbpref}stock_master";
    
    if ($category) {
        $sql .= " WHERE category_id = " . (int)$category;
    }
    
    $sql .= " ORDER BY stock_name";
    
    $result = $conn->query($sql);
    $products = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    $conn->close();
    return $products;
}

// Get categories
function get_categories() {
    $conn = db_connect();
    $tbpref = $GLOBALS['db_connections'][$GLOBALS['def_coy']]['tbpref'];
    
    $sql = "SELECT category_id, category_name FROM {$tbpref}stock_category ORDER BY category_name";
    $result = $conn->query($sql);
    $categories = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    $conn->close();
    return $categories;
}

// Get current cart from session
function get_cart() {
    session_start();
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

// Add to cart
function add_to_cart($product_id, $quantity = 1) {
    $conn = db_connect();
    $tbpref = $GLOBALS['db_connections'][$GLOBALS['def_coy']]['tbpref'];
    
    $sql = "SELECT stock_name, units_price FROM {$tbpref}stock_master WHERE stock_id = " . (int)$product_id;
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        $cart = get_cart();
        
        if (isset($cart[$product_id])) {
            $cart[$product_id]['quantity'] += $quantity;
        } else {
            $cart[$product_id] = [
                'name' => $product['stock_name'],
                'price' => $product['units_price'],
                'quantity' => $quantity
            ];
        }
        
        $_SESSION['cart'] = $cart;
    }
    
    $conn->close();
}

// Remove from cart
function remove_from_cart($product_id) {
    $cart = get_cart();
    unset($cart[$product_id]);
    $_SESSION['cart'] = $cart;
}

// Calculate cart total
function get_cart_total() {
    $cart = get_cart();
    $total = 0;
    
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    return $total;
}

// Process sale
function process_sale($payment_method, $cash_tendered = 0) {
    $conn = db_connect();
    $tbpref = $GLOBALS['db_connections'][$GLOBALS['def_coy']]['tbpref'];
    $cart = get_cart();
    
    if (empty($cart)) {
        return false;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get next debtor_no (customer)
        $sql = "SELECT MAX(debtor_no) as max_id FROM {$tbpref}debtors_master";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();
        $debtor_no = $row['max_id'] + 1;
        
        // Insert customer
        $sql = "INSERT INTO {$tbpref}debtors_master 
                (debtor_no, name, address, curr_code, sales_person, tax_id, notes) 
                VALUES (?, 'POS Customer', 'Walk-in Customer', 'USD', 1, '', 'POS Sale')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $debtor_no);
        $stmt->execute();
        
        // Insert sales transaction
        $trans_type = 10; // Sales Invoice
        $trans_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+30 days'));
        $total = get_cart_total();
        
        $sql = "INSERT INTO {$tbpref}sales_orders 
                (type, trans_no, trans_date, debtor_no, delivery_address, freight_cost, 
                 document_approval, comments, order_type, reference, payment_terms) 
                VALUES (?, 0, ?, ?, '', 0, 1, 'POS Sale', 0, '', 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $trans_type, $trans_date, $debtor_no);
        $stmt->execute();
        
        $trans_id = $conn->insert_id;
        
        // Insert sales order details
        foreach ($cart as $stock_id => $item) {
            $sql = "INSERT INTO {$tbpref}sales_order_details 
                    (order_no, stock_id, quantity, unit_price, discount_percent, 
                     fulfill_date, serial_no, loc_code) 
                    VALUES (?, ?, ?, ?, 0, ?, '', '')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iidss", $trans_id, $stock_id, $item['quantity'], 
                              $item['price'], $trans_date);
            $stmt->execute();
        }
        
        // Update stock quantities
        foreach ($cart as $stock_id => $item) {
            $sql = "UPDATE {$tbpref}stock_master SET quantity = quantity - ? WHERE stock_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $item['quantity'], $stock_id);
            $stmt->execute();
        }
        
        // Insert payment
        if ($payment_method == 'cash') {
            $sql = "INSERT INTO {$tbpref}bank_trans 
                    (type, trans_no, trans_date, bank_act, amount, ref, 
                     person_type, person_id, reconcile_date) 
                    VALUES (1, 0, ?, 1, ?, ?, 1, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdsis", $trans_date, $cash_tendered, 'POS Cash Payment', 
                              $debtor_no, $trans_date);
            $stmt->execute();
        }
        
        $conn->commit();
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_to_cart'])) {
        add_to_cart($_POST['product_id'], $_POST['quantity']);
    } elseif (isset($_POST['remove_from_cart'])) {
        remove_from_cart($_POST['product_id']);
    } elseif (isset($_POST['update_quantity'])) {
        $cart = get_cart();
        if (isset($cart[$_POST['product_id']])) {
            $cart[$_POST['product_id']]['quantity'] = $_POST['quantity'];
            $_SESSION['cart'] = $cart;
        }
    } elseif (isset($_POST['process_sale'])) {
        $payment_method = $_POST['payment_method'];
        $cash_tendered = isset($_POST['cash_tendered']) ? $_POST['cash_tendered'] : 0;
        
        if (process_sale($payment_method, $cash_tendered)) {
            $success_message = "Sale processed successfully!";
        } else {
            $error_message = "Error processing sale. Please try again.";
        }
    }
}

// Get data for display
$categories = get_categories();
$products = get_products();
$cart = get_cart();
$cart_total = get_cart_total();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FrontAccounting POS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #f72585;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #ef476f;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: bold;
        }
        
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .sidebar {
            width: 280px;
            background-color: white;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
        .search-container {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .search-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .search-btn:hover {
            background-color: var(--secondary);
        }
        
        .category-list {
            padding: 15px;
        }
        
        .category-item {
            padding: 10px 15px;
            margin-bottom: 5px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.2s;
        }
        
        .category-item:hover {
            background-color: #f5f5f5;
        }
        
        .category-item.active {
            background-color: var(--primary);
            color: white;
        }
        
        .products-grid {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .product-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-img {
            height: 120px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
        }
        
        .product-info {
            padding: 12px;
        }
        
        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .product-price {
            color: var(--primary);
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .product-code {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .cart-section {
            width: 350px;
            background-color: white;
            border-left: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
        }
        
        .cart-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .cart-item-price {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quantity-btn {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: none;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .quantity-btn:hover {
            background-color: #e0e0e0;
        }
        
        .quantity-value {
            width: 30px;
            text-align: center;
            font-weight: 500;
        }
        
        .cart-item-remove {
            color: var(--danger);
            cursor: pointer;
            margin-left: 10px;
        }
        
        .cart-summary {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .summary-label {
            color: var(--gray);
        }
        
        .summary-value {
            font-weight: 500;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .payment-section {
            padding: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .payment-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .payment-btn {
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background-color 0.2s;
        }
        
        .cash-btn {
            background-color: var(--success);
            color: white;
        }
        
        .cash-btn:hover {
            background-color: #3db8dd;
        }
        
        .card-btn {
            background-color: var(--primary);
            color: white;
        }
        
        .card-btn:hover {
            background-color: var(--secondary);
        }
        
        .other-btn {
            background-color: var(--warning);
            color: white;
        }
        
        .other-btn:hover {
            background-color: #e58919;
        }
        
        .void-btn {
            background-color: var(--danger);
            color: white;
        }
        
        .void-btn:hover {
            background-color: #d63d62;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 15px;
            margin-top: 15px;
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .checkout-btn:hover {
            background-color: #e01e70;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: white;
            border-left: 4px solid var(--success);
            padding: 15px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(120%);
            transition: transform 0.3s ease-out;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            border-left-color: var(--success);
        }
        
        .notification.error {
            border-left-color: var(--danger);
        }
        
        .notification.warning {
            border-left-color: var(--warning);
        }
        
        .notification-icon {
            font-size: 1.2rem;
        }
        
        .notification.success .notification-icon {
            color: var(--success);
        }
        
        .notification.error .notification-icon {
            color: var(--danger);
        }
        
        .notification.warning .notification-icon {
            color: var(--warning);
        }
        
        .notification-message {
            font-weight: 500;
        }
        
        .payment-form {
            margin-top: 10px;
            display: none;
        }
        
        .payment-form.show {
            display: block;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 1200px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
                max-height: 200px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .cart-section {
                width: 100%;
                border-left: none;
                border-top: 1px solid #e0e0e0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <i class="fas fa-store fa-2x"></i>
            <h1>FrontAccounting POS</h1>
        </div>
        <div class="user-info">
            <div class="store-info">
                <div>Store: Main Branch</div>
                <div>Register: 01</div>
            </div>
            <div class="user-avatar">FA</div>
        </div>
    </div>
    
    <div class="main-container">
        <div class="sidebar">
            <div class="search-container">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search products..." id="searchInput">
                    <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i></button>
                </div>
            </div>
            
            <div class="category-list">
                <div class="category-item active" data-category="0">
                    <i class="fas fa-star"></i>
                    <span>All Products</span>
                </div>
                <?php foreach ($categories as $category): ?>
                <div class="category-item" data-category="<?= $category['category_id'] ?>">
                    <i class="fas fa-th-large"></i>
                    <span><?= htmlspecialchars($category['category_name']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
            <div class="product-card" data-product-id="<?= $product['stock_id'] ?>">
                <div class="product-img">
                    <i class="fas fa-box fa-3x"></i>
                </div>
                <div class="product-info">
                    <div class="product-name"><?= htmlspecialchars($product['stock_name']) ?></div>
                    <div class="product-code"><?= $product['stock_id'] ?></div>
                    <div class="product-price">$<?= number_format($product['units_price'], 2) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="cart-section">
            <div class="cart-header">
                <div class="cart-title">Shopping Cart</div>
                <div class="cart-actions">
                    <i class="fas fa-print" title="Print Receipt"></i>
                </div>
            </div>
            
            <div class="cart-items" id="cartItems">
                <?php if (empty($cart)): ?>
                <div class="empty-cart">Your cart is empty</div>
                <?php else: ?>
                <?php foreach ($cart as $product_id => $item): ?>
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="cart-item-price">$<?= number_format($item['price'], 2) ?></div>
                    </div>
                    <div class="cart-item-quantity">
                        <form method="post" action="">
                            <input type="hidden" name="update_quantity" value="1">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            <button type="submit" class="quantity-btn"><i class="fas fa-minus"></i></button>
                        </form>
                        <span class="quantity-value"><?= $item['quantity'] ?></span>
                        <form method="post" action="">
                            <input type="hidden" name="add_to_cart" value="1">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="quantity-btn"><i class="fas fa-plus"></i></button>
                        </form>
                    </div>
                    <div class="cart-item-remove">
                        <form method="post" action="">
                            <input type="hidden" name="remove_from_cart" value="1">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            <button type="submit"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="cart-summary">
                <div class="summary-row">
                    <div class="summary-label">Subtotal:</div>
                    <div class="summary-value">$<?= number_format($cart_total, 2) ?></div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Tax (8%):</div>
                    <div class="summary-value">$<?= number_format($cart_total * 0.08, 2) ?></div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Discount:</div>
                    <div class="summary-value">-$<?= number_format(2.00, 2) ?></div>
                </div>
                <div class="total-row">
                    <div class="summary-label">Total:</div>
                    <div class="summary-value">$<?= number_format($cart_total * 1.08 - 2.00, 2) ?></div>
                </div>
            </div>
            
            <div class="payment-section">
                <div class="payment-buttons">
                    <button class="payment-btn cash-btn" id="cashPaymentBtn">
                        <i class="fas fa-money-bill-wave"></i>
                        Cash
                    </button>
                    <button class="payment-btn card-btn" id="cardPaymentBtn">
                        <i class="fas fa-credit-card"></i>
                        Card
                    </button>
                    <button class="payment-btn other-btn" id="otherPaymentBtn">
                        <i class="fas fa-receipt"></i>
                        Other
                    </button>
                    <button class="payment-btn void-btn" id="voidBtn">
                        <i class="fas fa-ban"></i>
                        Void
                    </button>
                </div>
                
                <div class="payment-form" id="cashPaymentForm">
                    <div class="form-group">
                        <label class="form-label">Cash Tendered:</label>
                        <input type="number" class="form-input" id="cashTendered" step="0.01" min="0">
                    </div>
                    <button class="checkout-btn" id="cashCheckoutBtn">
                        <i class="fas fa-check-circle"></i>
                        Complete Sale
                    </button>
                </div>
                
                <button class="checkout-btn" id="checkoutBtn" style="display: none;">
                    <i class="fas fa-check-circle"></i>
                    Complete Sale
                </button>
            </div>
        </div>
    </div>
    
    <div class="notification" id="notification">
        <div class="notification-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="notification-message">
            Sale processed successfully!
        </div>
    </div>
    
    <script>
        // Product selection
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', () => {
                const productId = card.dataset.productId;
                
                // Add to cart
                const form = document.createElement('form');
                form.method = 'post';
                form.action = '';
                
                const addInput = document.createElement('input');
                addInput.type = 'hidden';
                addInput.name = 'add_to_cart';
                addInput.value = '1';
                
                const productInput = document.createElement('input');
                productInput.type = 'hidden';
                productInput.name = 'product_id';
                productInput.value = productId;
                
                const quantityInput = document.createElement('input');
                quantityInput.type = 'hidden';
                quantityInput.name = 'quantity';
                quantityInput.value = '1';
                
                form.appendChild(addInput);
                form.appendChild(productInput);
                form.appendChild(quantityInput);
                document.body.appendChild(form);
                form.submit();
            });
        });
        
        // Category selection
        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', () => {
                // Remove active class from all items
                document.querySelectorAll('.category-item').forEach(i => {
                    i.classList.remove('active');
                });
                
                // Add active class to clicked item
                item.classList.add('active');
                
                const categoryId = item.dataset.category;
                
                // Filter products by category
                if (categoryId === '0') {
                    window.location.href = 'pos.php';
                } else {
                    window.location.href = `pos.php?category=${categoryId}`;
                }
            });
        });
        
        // Payment methods
        document.getElementById('cashPaymentBtn').addEventListener('click', () => {
            document.getElementById('cashPaymentForm').classList.add('show');
            document.getElementById('checkoutBtn').style.display = 'none';
        });
        
        document.getElementById('cardPaymentBtn').addEventListener('click', () => {
            document.getElementById('cashPaymentForm').classList.remove('show');
            document.getElementById('checkoutBtn').style.display = 'block';
            
            // Process card payment
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            
            const paymentInput = document.createElement('input');
            paymentInput.type = 'hidden';
            paymentInput.name = 'process_sale';
            paymentInput.value = '1';
            
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = 'payment_method';
            methodInput.value = 'card';
            
            form.appendChild(paymentInput);
            form.appendChild(methodInput);
            document.body.appendChild(form);
            form.submit();
        });
        
        document.getElementById('otherPaymentBtn').addEventListener('click', () => {
            document.getElementById('cashPaymentForm').classList.remove('show');
            document.getElementById('checkoutBtn').style.display = 'block';
            
            // Process other payment
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            
            const paymentInput = document.createElement('input');
            paymentInput.type = 'hidden';
            paymentInput.name = 'process_sale';
            paymentInput.value = '1';
            
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = 'payment_method';
            methodInput.value = 'other';
            
            form.appendChild(paymentInput);
            form.appendChild(methodInput);
            document.body.appendChild(form);
            form.submit();
        });
        
        document.getElementById('voidBtn').addEventListener('click', () => {
            if (confirm('Are you sure you want to void this sale?')) {
                // Clear cart
                const form = document.createElement('form');
                form.method = 'post';
                form.action = '';
                
                const clearInput = document.createElement('input');
                clearInput.type = 'hidden';
                clearInput.name = 'clear_cart';
                clearInput.value = '1';
                
                form.appendChild(clearInput);
                document.body.appendChild(form);
                form.submit();
                
                showNotification('warning', 'Sale has been voided');
            }
        });
        
        document.getElementById('cashCheckoutBtn').addEventListener('click', () => {
            const cashTendered = document.getElementById('cashTendered').value;
            
            // Process cash payment
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            
            const paymentInput = document.createElement('input');
            paymentInput.type = 'hidden';
            paymentInput.name = 'process_sale';
            paymentInput.value = '1';
            
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = 'payment_method';
            methodInput.value = 'cash';
            
            const tenderedInput = document.createElement('input');
            tenderedInput.type = 'hidden';
            tenderedInput.name = 'cash_tendered';
            tenderedInput.value = cashTendered;
            
            form.appendChild(paymentInput);
            form.appendChild(methodInput);
            form.appendChild(tenderedInput);
            document.body.appendChild(form);
            form.submit();
        });
        
        document.getElementById('checkoutBtn').addEventListener('click', () => {
            // Process sale without cash
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '';
            
            const paymentInput = document.createElement('input');
            paymentInput.type = 'hidden';
            paymentInput.name = 'process_sale';
            paymentInput.value = '1';
            
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = 'payment_method';
            methodInput.value = 'card';
            
            form.appendChild(paymentInput);
            form.appendChild(methodInput);
            document.body.appendChild(form);
            form.submit();
        });
        
        // Search functionality
        document.getElementById('searchBtn').addEventListener('click', () => {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            if (searchTerm.length > 0) {
                window.location.href = `pos.php?search=${encodeURIComponent(searchTerm)}`;
            }
        });
        
        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });
        
        // Show notification
        function showNotification(type, message) {
            const notification = document.getElementById('notification');
            notification.className = `notification ${type}`;
            notification.querySelector('.notification-message').textContent = message;
            
            // Show notification
            notification.classList.add('show');
            
            // Hide after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        // Handle success/error messages from PHP
        <?php
        if (isset($_GET['success'])) {
            echo "showNotification('success', '".htmlspecialchars($_GET['success'])."');";
        } elseif (isset($_GET['error'])) {
            echo "showNotification('error', '".htmlspecialchars($_GET['error'])."');";
        }
        ?>
    </script>
</body>
</html>