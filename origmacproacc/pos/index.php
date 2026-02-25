<?php
$path_to_root = "..";
$page_security = 'SA_SALESORDER';
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/sales/includes/cart_class.inc");
include_once($path_to_root . "/sales/includes/sales_db.inc");
include_once($path_to_root . "/sales/includes/sales_ui.inc");

page(_("Simple POS Screen"));

if (!isset($_SESSION['pos_cart']))
    $_SESSION['pos_cart'] = [];

// إضافة صنف للعربة
if (isset($_POST['AddItem'])) {
    $stock_id = $_POST['stock_id'];
    $description = $_POST['description'];
    $qty = input_num('qty', 1);
    $price = input_num('price', 0);

    if ($stock_id == '') {
        display_error(_("Please enter Stock ID."));
    } elseif ($qty <= 0) {
        display_error(_("Quantity must be greater than zero."));
    } else {
        $_SESSION['pos_cart'][] = [
            'stock_id' => $stock_id,
            'description' => $description,
            'qty' => $qty,
            'price' => $price
        ];
        display_notification(_("Item added to cart."));
    }
}

// تنظيف العربة
if (isset($_POST['ClearCart'])) {
    $_SESSION['pos_cart'] = [];
    display_notification(_("Cart cleared."));
}

// حفظ الفاتورة
if (isset($_POST['SaveInvoice'])) {
    if (count($_SESSION['pos_cart']) == 0) {
        display_error(_("Cart is empty, cannot save invoice."));
    } else {
        $trans_type = ST_SALESINVOICE;
        $cart = new Cart($trans_type);

        // مثال: نفترض العميل رقم 1 (يمكن تعديلها لاحقًا)
        $cart->customer_id = 1;
        $cart->Branch = 1;
        $cart->reference = get_next_reference($trans_type);
        $cart->document_date = Today();
        $cart->due_date = Today();
        $cart->Comments = "POS Invoice";
        $cart->payment = 1; // مثال: رقم الدفع (1 = نقدي مثلاً)
        $cart->sales_type = 0;
        $cart->freight_cost = 0;

        // إضافة الأصناف
        foreach ($_SESSION['pos_cart'] as $item) {
            add_to_order(
                $cart,
                $item['stock_id'],
                $item['qty'],
                $item['price'],
                0, // خصم 0%
                $item['description']
            );
        }

        $write_result = $cart->write(1); // 1 = check duplicates reference
        if ($write_result == -1) {
            display_error(_("The reference is already in use."));
        } else {
            display_notification(_("Invoice has been saved with number: ") . $write_result);
            $_SESSION['pos_cart'] = []; // تفريغ العربة بعد الحفظ
        }
    }
}

// حساب المجموع
function cart_total() {
    $total = 0;
    foreach ($_SESSION['pos_cart'] as $item) {
        $total += $item['qty'] * $item['price'];
    }
    return $total;
}

// بدء النموذج
start_form();

table_section(1);
start_table(TABLESTYLE);

text_row(_("Stock ID:"), 'stock_id', '', 20, 30);
text_row(_("Description:"), 'description', '', 40, 100);
small_amount_row(_("Quantity:"), 'qty', 1);
small_amount_row(_("Price:"), 'price', 0);

end_table();

submit_center('AddItem', _("Add Item to Cart"));

end_form();

br();

if (count($_SESSION['pos_cart']) > 0) {
    start_table(TABLESTYLE, "width=60%");
    echo "<tr><th>" . _("Stock ID") . "</th><th>" . _("Description") . "</th><th>" . _("Quantity") . "</th><th>" . _("Price") . "</th><th>" . _("Total") . "</th></tr>";
    foreach ($_SESSION['pos_cart'] as $item) {
        echo "<tr>";
        echo "<td>" . $item['stock_id'] . "</td>";
        echo "<td>" . $item['description'] . "</td>";
        echo "<td align='right'>" . number_format2($item['qty'], 2) . "</td>";
        echo "<td align='right'>" . number_format2($item['price'], 2) . "</td>";
        echo "<td align='right'>" . number_format2($item['qty'] * $item['price'], 2) . "</td>";
        echo "</tr>";
    }
    echo "<tr><td colspan='4' align='right'><b>" . _("Total") . "</b></td><td align='right'><b>" . number_format2(cart_total(), 2) . "</b></td></tr>";
    end_table();

    start_form();
    submit_center('SaveInvoice', _("Save Invoice"), true);
    submit_center('ClearCart', _("Clear Cart"), true);
    end_form();
} else {
    display_note(_("Cart is empty."));
}

end_page();
?>
