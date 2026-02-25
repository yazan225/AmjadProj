<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
//-----------------------------------------------------------------------------
//
//	Entry/Modify Sales Quotations
//	Entry/Modify Sales Order
//	Entry Direct Delivery
//	Entry Direct Invoice
//

$path_to_root = "..";
$page_security = 'SA_SALESORDER';

include_once($path_to_root . "/sales/includes/cart_class.inc");
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/sales/includes/sales_ui.inc");
include_once($path_to_root . "/sales/includes/ui/sales_order_ui.inc");
include_once($path_to_root . "/sales/includes/sales_db.inc");
include_once($path_to_root . "/sales/includes/db/sales_types_db.inc");
include_once($path_to_root . "/reporting/includes/reporting.inc");

set_page_security( @$_SESSION['Items']->trans_type,
	array(	ST_SALESORDER=>'SA_SALESORDER',
			ST_SALESQUOTE => 'SA_SALESQUOTE',
			ST_CUSTDELIVERY => 'SA_SALESDELIVERY',
			ST_SALESINVOICE => 'SA_SALESINVOICE'),
	array(	'NewOrder' => 'SA_SALESORDER',
			'ModifyOrderNumber' => 'SA_SALESORDER',
			'AddedID' => 'SA_SALESORDER',
			'UpdatedID' => 'SA_SALESORDER',
			'NewQuotation' => 'SA_SALESQUOTE',
			'ModifyQuotationNumber' => 'SA_SALESQUOTE',
			'NewQuoteToSalesOrder' => 'SA_SALESQUOTE',
			'AddedQU' => 'SA_SALESQUOTE',
			'UpdatedQU' => 'SA_SALESQUOTE',
			'NewDelivery' => 'SA_SALESDELIVERY',
			'AddedDN' => 'SA_SALESDELIVERY', 
			'NewInvoice' => 'SA_SALESINVOICE',
			'AddedDI' => 'SA_SALESINVOICE'
			)
);

$js = '';

if ($SysPrefs->use_popup_windows) {
	$js .= get_js_open_window(900, 500);
}

if (user_use_date_picker()) {
	$js .= get_js_date_picker();
}

if (isset($_GET['NewDelivery']) && is_numeric($_GET['NewDelivery'])) {

	$_SESSION['page_title'] = _($help_context = "Direct Sales Delivery");
	create_cart(ST_CUSTDELIVERY, $_GET['NewDelivery']);

} elseif (isset($_GET['NewInvoice']) && is_numeric($_GET['NewInvoice'])) {

	create_cart(ST_SALESINVOICE, $_GET['NewInvoice']);

	if (isset($_GET['FixedAsset'])) {
		$_SESSION['page_title'] = _($help_context = "Fixed Assets Sale");
		$_SESSION['Items']->fixed_asset = true;
  	} else
		$_SESSION['page_title'] = _($help_context = "Direct Sales Invoice");

} elseif (isset($_GET['ModifyOrderNumber']) && is_numeric($_GET['ModifyOrderNumber'])) {

	$help_context = 'Modifying Sales Order';
	$_SESSION['page_title'] = sprintf( _("Modifying Sales Order # %d"), $_GET['ModifyOrderNumber']);
	create_cart(ST_SALESORDER, $_GET['ModifyOrderNumber']);

} elseif (isset($_GET['ModifyQuotationNumber']) && is_numeric($_GET['ModifyQuotationNumber'])) {

	$help_context = 'Modifying Sales Quotation';
	$_SESSION['page_title'] = sprintf( _("Modifying Sales Quotation # %d"), $_GET['ModifyQuotationNumber']);
	create_cart(ST_SALESQUOTE, $_GET['ModifyQuotationNumber']);

} elseif (isset($_GET['NewOrder'])) {

	$_SESSION['page_title'] = _($help_context = "New Sales Order Entry");
	create_cart(ST_SALESORDER, 0);
} elseif (isset($_GET['NewQuotation'])) {

	$_SESSION['page_title'] = _($help_context = "New Sales Quotation Entry");
	create_cart(ST_SALESQUOTE, 0);
} elseif (isset($_GET['NewQuoteToSalesOrder'])) {
	$_SESSION['page_title'] = _($help_context = "Sales Order Entry");
	create_cart(ST_SALESQUOTE, $_GET['NewQuoteToSalesOrder']);
}

page($_SESSION['page_title'], false, false, "", $js);

if (isset($_GET['ModifyOrderNumber']) && is_prepaid_order_open($_GET['ModifyOrderNumber']))
{
	display_error(_("This order cannot be edited because there are invoices or payments related to it, and prepayment terms were used."));
	end_page(); exit;
}
if (isset($_GET['ModifyOrderNumber']))
	check_is_editable(ST_SALESORDER, $_GET['ModifyOrderNumber']);
elseif (isset($_GET['ModifyQuotationNumber']))
	check_is_editable(ST_SALESQUOTE, $_GET['ModifyQuotationNumber']);

//-----------------------------------------------------------------------------

if (list_updated('branch_id')) {
	// when branch is selected via external editor also customer can change
	$br = get_branch(get_post('branch_id'));
	$_POST['customer_id'] = $br['debtor_no'];
	$Ajax->activate('customer_id');
}

if (isset($_GET['AddedID'])) {
	$order_no = $_GET['AddedID'];
	display_notification_centered(sprintf( _("Order # %d has been entered."),$order_no));

	submenu_view(_("&View This Order"), ST_SALESORDER, $order_no);

	submenu_print(_("&Print This Order"), ST_SALESORDER, $order_no, 'prtopt');
	submenu_print(_("&Email This Order"), ST_SALESORDER, $order_no, null, 1);
	set_focus('prtopt');
	
	submenu_option(_("Make &Delivery Against This Order"),
		"/sales/customer_delivery.php?OrderNumber=$order_no");

	submenu_option(_("Work &Order Entry"),	"/manufacturing/work_order_entry.php?");

	submenu_option(_("Enter a &New Order"),	"/sales/sales_order_entry.php?NewOrder=0");

	submenu_option(_("Add an Attachment"), "/admin/attachments.php?filterType=".ST_SALESORDER."&trans_no=$order_no");

	display_footer_exit();

} elseif (isset($_GET['UpdatedID'])) {
	$order_no = $_GET['UpdatedID'];

	display_notification_centered(sprintf( _("Order # %d has been updated."),$order_no));

	submenu_view(_("&View This Order"), ST_SALESORDER, $order_no);

	submenu_print(_("&Print This Order"), ST_SALESORDER, $order_no, 'prtopt');
	submenu_print(_("&Email This Order"), ST_SALESORDER, $order_no, null, 1);
	set_focus('prtopt');

	submenu_option(_("Confirm Order Quantities and Make &Delivery"),
		"/sales/customer_delivery.php?OrderNumber=$order_no");

	submenu_option(_("Select A Different &Order"),
		"/sales/inquiry/sales_orders_view.php?OutstandingOnly=1");

	display_footer_exit();

} elseif (isset($_GET['AddedQU'])) {
	$order_no = $_GET['AddedQU'];
	display_notification_centered(sprintf( _("Quotation # %d has been entered."),$order_no));

	submenu_view(_("&View This Quotation"), ST_SALESQUOTE, $order_no);

	submenu_print(_("&Print This Quotation"), ST_SALESQUOTE, $order_no, 'prtopt');
	submenu_print(_("&Email This Quotation"), ST_SALESQUOTE, $order_no, null, 1);
	set_focus('prtopt');
	
	submenu_option(_("Make &Sales Order Against This Quotation"),
		"/sales/sales_order_entry.php?NewQuoteToSalesOrder=$order_no");

	submenu_option(_("Enter a New &Quotation"),	"/sales/sales_order_entry.php?NewQuotation=0");

	submenu_option(_("Add an Attachment"), "/admin/attachments.php?filterType=".ST_SALESQUOTE."&trans_no=$order_no");

	display_footer_exit();

} elseif (isset($_GET['UpdatedQU'])) {
	$order_no = $_GET['UpdatedQU'];

	display_notification_centered(sprintf( _("Quotation # %d has been updated."),$order_no));

	submenu_view(_("&View This Quotation"), ST_SALESQUOTE, $order_no);

	submenu_print(_("&Print This Quotation"), ST_SALESQUOTE, $order_no, 'prtopt');
	submenu_print(_("&Email This Quotation"), ST_SALESQUOTE, $order_no, null, 1);
	set_focus('prtopt');

	submenu_option(_("Make &Sales Order Against This Quotation"),
		"/sales/sales_order_entry.php?NewQuoteToSalesOrder=$order_no");

	submenu_option(_("Select A Different &Quotation"),
		"/sales/inquiry/sales_orders_view.php?type=".ST_SALESQUOTE);

	display_footer_exit();
} elseif (isset($_GET['AddedDN'])) {
	$delivery = $_GET['AddedDN'];

	display_notification_centered(sprintf(_("Delivery # %d has been entered."),$delivery));

	submenu_view(_("&View This Delivery"), ST_CUSTDELIVERY, $delivery);

	submenu_print(_("&Print Delivery Note"), ST_CUSTDELIVERY, $delivery, 'prtopt');
	submenu_print(_("&Email Delivery Note"), ST_CUSTDELIVERY, $delivery, null, 1);
	submenu_print(_("P&rint as Packing Slip"), ST_CUSTDELIVERY, $delivery, 'prtopt', null, 1);
	submenu_print(_("E&mail as Packing Slip"), ST_CUSTDELIVERY, $delivery, null, 1, 1);
	set_focus('prtopt');

	display_note(get_gl_view_str(ST_CUSTDELIVERY, $delivery, _("View the GL Journal Entries for this Dispatch")),0, 1);

	submenu_option(_("Make &Invoice Against This Delivery"),
		"/sales/customer_invoice.php?DeliveryNumber=$delivery");

	if ((isset($_GET['Type']) && $_GET['Type'] == 1))
		submenu_option(_("Enter a New Template &Delivery"),
			"/sales/inquiry/sales_orders_view.php?DeliveryTemplates=Yes");
	else
		submenu_option(_("Enter a &New Delivery"), 
			"/sales/sales_order_entry.php?NewDelivery=0");

	submenu_option(_("Add an Attachment"), "/admin/attachments.php?filterType=".ST_CUSTDELIVERY."&trans_no=$delivery");

	display_footer_exit();

} elseif (isset($_GET['AddedDI'])) {
	$invoice = $_GET['AddedDI'];

	display_notification_centered(sprintf(_("Invoice # %d has been entered."), $invoice));

	submenu_view(_("&View This Invoice"), ST_SALESINVOICE, $invoice);

	submenu_print(_("&Print Sales Invoice"), ST_SALESINVOICE, $invoice."-".ST_SALESINVOICE, 'prtopt');
	submenu_print(_("&Email Sales Invoice"), ST_SALESINVOICE, $invoice."-".ST_SALESINVOICE, null, 1);
	set_focus('prtopt');

	$row = db_fetch(get_allocatable_from_cust_transactions(null, $invoice, ST_SALESINVOICE));
	if ($row !== false)
		submenu_print(_("Print &Receipt"), $row['type'], $row['trans_no']."-".$row['type'], 'prtopt');

	display_note(get_gl_view_str(ST_SALESINVOICE, $invoice, _("View the GL &Journal Entries for this Invoice")),0, 1);

	if ((isset($_GET['Type']) && $_GET['Type'] == 1))
		submenu_option(_("Enter a &New Template Invoice"), 
			"/sales/inquiry/sales_orders_view.php?InvoiceTemplates=Yes");
	else
		submenu_option(_("Enter a &New Direct Invoice"),
			"/sales/sales_order_entry.php?NewInvoice=0");

	if ($row === false)
		submenu_option(_("Entry &customer payment for this invoice"), "/sales/customer_payments.php?SInvoice=".$invoice);

	submenu_option(_("Add an Attachment"), "/admin/attachments.php?filterType=".ST_SALESINVOICE."&trans_no=$invoice");

	display_footer_exit();
} else
	check_edit_conflicts(get_post('cart_id'));
//-----------------------------------------------------------------------------

function copy_to_cart()
{
	$cart = &$_SESSION['Items'];

	$cart->reference = get_post('ref');

	$cart->Comments =  $_POST['Comments'];

	$cart->document_date = $_POST['OrderDate'];

	$newpayment = false;

	if (isset($_POST['payment']) && ($cart->payment != $_POST['payment'])) {
		$cart->payment = $_POST['payment'];
		$cart->payment_terms = get_payment_terms($_POST['payment']);
		$newpayment = true;
	}
	if ($cart->payment_terms['cash_sale']) {
		if ($newpayment) {
			$cart->due_date = $cart->document_date;
			$cart->phone = $cart->cust_ref = $cart->delivery_address = '';
			$cart->ship_via = 0;
			$cart->deliver_to = '';
			$cart->prep_amount = 0;
		}
	} else {
		$cart->due_date = $_POST['delivery_date'];
		$cart->cust_ref = $_POST['cust_ref'];
		$cart->deliver_to = $_POST['deliver_to'];
		$cart->delivery_address = $_POST['delivery_address'];
		$cart->phone = $_POST['phone'];
		$cart->ship_via = $_POST['ship_via'];
		if (!$cart->trans_no || ($cart->trans_type == ST_SALESORDER && !$cart->is_started()))
			$cart->prep_amount = input_num('prep_amount', 0);
	}
	$cart->Location = $_POST['Location'];
	$cart->freight_cost = input_num('freight_cost');
	if (isset($_POST['email']))
		$cart->email =$_POST['email'];
	else
		$cart->email = '';
	$cart->customer_id	= $_POST['customer_id'];
	$cart->Branch = $_POST['branch_id'];
	$cart->sales_type = $_POST['sales_type'];

	if ($cart->trans_type!=ST_SALESORDER && $cart->trans_type!=ST_SALESQUOTE) { // 2008-11-12 Joe Hunt
		$cart->dimension_id = $_POST['dimension_id'];
		$cart->dimension2_id = $_POST['dimension2_id'];
	}
	$cart->ex_rate = input_num('_ex_rate', null);
}

//-----------------------------------------------------------------------------

function copy_from_cart()
{
	$cart = &$_SESSION['Items'];
	$_POST['ref'] = $cart->reference;
	$_POST['Comments'] = $cart->Comments;

	$_POST['OrderDate'] = $cart->document_date;
	$_POST['delivery_date'] = $cart->due_date;
	$_POST['cust_ref'] = $cart->cust_ref;
	$_POST['freight_cost'] = price_format($cart->freight_cost);

	$_POST['deliver_to'] = $cart->deliver_to;
	$_POST['delivery_address'] = $cart->delivery_address;
	$_POST['phone'] = $cart->phone;
	$_POST['Location'] = $cart->Location;
	$_POST['ship_via'] = $cart->ship_via;

	$_POST['customer_id'] = $cart->customer_id;

	$_POST['branch_id'] = $cart->Branch;
	$_POST['sales_type'] = $cart->sales_type;
	$_POST['prep_amount'] = price_format($cart->prep_amount);
	// POS 
	$_POST['payment'] = $cart->payment;
	if ($cart->trans_type!=ST_SALESORDER && $cart->trans_type!=ST_SALESQUOTE) { // 2008-11-12 Joe Hunt
		$_POST['dimension_id'] = $cart->dimension_id;
		$_POST['dimension2_id'] = $cart->dimension2_id;
	}
	$_POST['cart_id'] = $cart->cart_id;
	$_POST['_ex_rate'] = $cart->ex_rate;
}
//--------------------------------------------------------------------------------

function line_start_focus() {
  	global 	$Ajax;

  	$Ajax->activate('items_table');
  	set_focus('_stock_id_edit');
}

//--------------------------------------------------------------------------------
function can_process() {

	global $Refs, $SysPrefs;

	copy_to_cart();

	if (!get_post('customer_id')) 
	{
		display_error(_("There is no customer selected."));
		set_focus('customer_id');
		return false;
	} 
	
	if (!get_post('branch_id')) 
	{
		display_error(_("This customer has no branch defined."));
		set_focus('branch_id');
		return false;
	} 
	
	if (!is_date($_POST['OrderDate'])) {
		display_error(_("The entered date is invalid."));
		set_focus('OrderDate');
		return false;
	}
	if ($_SESSION['Items']->trans_type!=ST_SALESORDER && $_SESSION['Items']->trans_type!=ST_SALESQUOTE && !is_date_in_fiscalyear($_POST['OrderDate'])) {
		display_error(_("The entered date is out of fiscal year or is closed for further data entry."));
		set_focus('OrderDate');
		return false;
	}
	if (count($_SESSION['Items']->line_items) == 0)	{
		display_error(_("You must enter at least one non empty item line."));
		set_focus('AddItem');
		return false;
	}
	if (!$SysPrefs->allow_negative_stock() && ($low_stock = $_SESSION['Items']->check_qoh()))
	{
		display_error(_("This document cannot be processed because there is insufficient quantity for items marked."));
		return false;
	}
	if ($_SESSION['Items']->payment_terms['cash_sale'] == 0) {
		if (!$_SESSION['Items']->is_started() && ($_SESSION['Items']->payment_terms['days_before_due'] == -1) && ((input_num('prep_amount')<=0) ||
			input_num('prep_amount')>$_SESSION['Items']->get_trans_total())) {
			display_error(_("Pre-payment required have to be positive and less than total amount."));
			set_focus('prep_amount');
			return false;
		}
		if (strlen($_POST['deliver_to']) <= 1) {
			display_error(_("You must enter the person or company to whom delivery should be made to."));
			set_focus('deliver_to');
			return false;
		}

		if ($_SESSION['Items']->trans_type != ST_SALESQUOTE && strlen($_POST['delivery_address']) <= 1) {
			display_error( _("You should enter the street address in the box provided. Orders cannot be accepted without a valid street address."));
			set_focus('delivery_address');
			return false;
		}

		if ($_POST['freight_cost'] == "")
			$_POST['freight_cost'] = price_format(0);

		if (!check_num('freight_cost',0)) {
			display_error(_("The shipping cost entered is expected to be numeric."));
			set_focus('freight_cost');
			return false;
		}
		if (!is_date($_POST['delivery_date'])) {
			if ($_SESSION['Items']->trans_type==ST_SALESQUOTE)
				display_error(_("The Valid date is invalid."));
			else	
				display_error(_("The delivery date is invalid."));
			set_focus('delivery_date');
			return false;
		}
		if (date1_greater_date2($_POST['OrderDate'], $_POST['delivery_date'])) {
			if ($_SESSION['Items']->trans_type==ST_SALESQUOTE)
				display_error(_("The requested valid date is before the date of the quotation."));
			else	
				display_error(_("The requested delivery date is before the date of the order."));
			set_focus('delivery_date');
			return false;
		}
	}
	else
	{
		if (!db_has_cash_accounts())
		{
			display_error(_("You need to define a cash account for your Sales Point."));
			return false;
		}	
	}	
	if (!$Refs->is_valid($_POST['ref'], $_SESSION['Items']->trans_type)) {
		display_error(_("You must enter a reference."));
		set_focus('ref');
		return false;
	}
	if (!db_has_currency_rates($_SESSION['Items']->customer_currency, $_POST['OrderDate']))
		return false;
	
   	if ($_SESSION['Items']->get_items_total() < 0) {
		display_error("Invoice total amount cannot be less than zero.");
		return false;
	}

	if ($_SESSION['Items']->payment_terms['cash_sale'] && 
		($_SESSION['Items']->trans_type == ST_CUSTDELIVERY || $_SESSION['Items']->trans_type == ST_SALESINVOICE)) 
		$_SESSION['Items']->due_date = $_SESSION['Items']->document_date;
	return true;
}

//-----------------------------------------------------------------------------

if (isset($_POST['update'])) {
	copy_to_cart();
	$Ajax->activate('items_table');
}

if (isset($_POST['ProcessOrder']) && can_process()) {

	$modified = ($_SESSION['Items']->trans_no != 0);
	$so_type = $_SESSION['Items']->so_type;

	$ret = $_SESSION['Items']->write(1);
	if ($ret == -1)
	{
		display_error(_("The entered reference is already in use."));
		$ref = $Refs->get_next($_SESSION['Items']->trans_type, null, array('date' => Today()));
		if ($ref != $_SESSION['Items']->reference)
		{
			unset($_POST['ref']); // force refresh reference
			display_error(_("The reference number field has been increased. Please save the document again."));
		}
		set_focus('ref');
	}
	else
	{
		if (count($messages)) { // abort on failure or error messages are lost
			$Ajax->activate('_page_body');
			display_footer_exit();
		}
		$trans_no = key($_SESSION['Items']->trans_no);
		$trans_type = $_SESSION['Items']->trans_type;
		new_doc_date($_SESSION['Items']->document_date);
		processing_end();
		if ($modified) {
			if ($trans_type == ST_SALESQUOTE)
				meta_forward($_SERVER['PHP_SELF'], "UpdatedQU=$trans_no");
			else	
				meta_forward($_SERVER['PHP_SELF'], "UpdatedID=$trans_no");
		} elseif ($trans_type == ST_SALESORDER) {
			meta_forward($_SERVER['PHP_SELF'], "AddedID=$trans_no");
		} elseif ($trans_type == ST_SALESQUOTE) {
			meta_forward($_SERVER['PHP_SELF'], "AddedQU=$trans_no");
		} elseif ($trans_type == ST_SALESINVOICE) {
			meta_forward($_SERVER['PHP_SELF'], "AddedDI=$trans_no&Type=$so_type");
		} else {
			meta_forward($_SERVER['PHP_SELF'], "AddedDN=$trans_no&Type=$so_type");
		}
	}	
}

//--------------------------------------------------------------------------------

function check_item_data()
{
	global $SysPrefs;
	
	$is_inventory_item = is_inventory_item(get_post('stock_id'));
	if(!get_post('stock_id_text', true)) {
		display_error( _("Item description cannot be empty."));
		set_focus('stock_id_edit');
		return false;
	}
	elseif (!check_num('qty', 0) || !check_num('Disc', 0, 100)) {
		display_error( _("The item could not be updated because you are attempting to set the quantity ordered to less than 0, or the discount percent to more than 100."));
		set_focus('qty');
		return false;
	} elseif (!check_num('price', 0) && (!$SysPrefs->allow_negative_prices() || $is_inventory_item)) {
		display_error( _("Price for inventory item must be entered and can not be less than 0"));
		set_focus('price');
		return false;
	} elseif (isset($_POST['LineNo']) && isset($_SESSION['Items']->line_items[$_POST['LineNo']])
	    && !check_num('qty', $_SESSION['Items']->line_items[$_POST['LineNo']]->qty_done)) {

		set_focus('qty');
		display_error(_("You attempting to make the quantity ordered a quantity less than has already been delivered. The quantity delivered cannot be modified retrospectively."));
		return false;
	}

	$cost_home = get_unit_cost(get_post('stock_id')); // Added 2011-03-27 Joe Hunt
	$cost = $cost_home / get_exchange_rate_from_home_currency($_SESSION['Items']->customer_currency, $_SESSION['Items']->document_date);
	if (input_num('price') < $cost)
	{
		$dec = user_price_dec();
		$curr = $_SESSION['Items']->customer_currency;
		$price = number_format2(input_num('price'), $dec);
		if ($cost_home == $cost)
			$std_cost = number_format2($cost_home, $dec);
		else
		{
			$price = $curr . " " . $price;
			$std_cost = $curr . " " . number_format2($cost, $dec);
		}
		display_warning(sprintf(_("Price %s is below Standard Cost %s"), $price, $std_cost));
	}	
	return true;
}

//--------------------------------------------------------------------------------

function handle_update_item()
{
	if ($_POST['UpdateItem'] != '' && check_item_data()) {
		$_SESSION['Items']->update_cart_item($_POST['LineNo'],
		 input_num('qty'), input_num('price'),
		 input_num('Disc') / 100, $_POST['item_description'] );
	}
	page_modified();
  line_start_focus();
}

//--------------------------------------------------------------------------------

function handle_delete_item($line_no)
{
    if ($_SESSION['Items']->some_already_delivered($line_no) == 0) {
	    $_SESSION['Items']->remove_from_cart($line_no);
    } else {
		display_error(_("This item cannot be deleted because some of it has already been delivered."));
    }
    line_start_focus();
}

//--------------------------------------------------------------------------------

function handle_new_item()
{

	if (!check_item_data()) {
			return;
	}
	add_to_order($_SESSION['Items'], get_post('stock_id'), input_num('qty'),
		input_num('price'), input_num('Disc') / 100, get_post('stock_id_text'));

	unset($_POST['_stock_id_edit'], $_POST['stock_id']);
	page_modified();
	line_start_focus();
}

//--------------------------------------------------------------------------------

function  handle_cancel_order()
{
	global $path_to_root, $Ajax;


	if ($_SESSION['Items']->trans_type == ST_CUSTDELIVERY) {
		display_notification(_("Direct delivery entry has been cancelled as requested."), 1);
		submenu_option(_("Enter a New Sales Delivery"),	"/sales/sales_order_entry.php?NewDelivery=1");
	} elseif ($_SESSION['Items']->trans_type == ST_SALESINVOICE) {
		display_notification(_("Direct invoice entry has been cancelled as requested."), 1);
		submenu_option(_("Enter a New Sales Invoice"),	"/sales/sales_order_entry.php?NewInvoice=1");
	} elseif ($_SESSION['Items']->trans_type == ST_SALESQUOTE)
	{
		if ($_SESSION['Items']->trans_no != 0) 
			delete_sales_order(key($_SESSION['Items']->trans_no), $_SESSION['Items']->trans_type);
		display_notification(_("This sales quotation has been cancelled as requested."), 1);
		submenu_option(_("Enter a New Sales Quotation"), "/sales/sales_order_entry.php?NewQuotation=Yes");
	} else { // sales order
		if ($_SESSION['Items']->trans_no != 0) {
			$order_no = key($_SESSION['Items']->trans_no);
			if (sales_order_has_deliveries($order_no))
			{
				close_sales_order($order_no);
				display_notification(_("Undelivered part of order has been cancelled as requested."), 1);
				submenu_option(_("Select Another Sales Order for Edition"), "/sales/inquiry/sales_orders_view.php?type=".ST_SALESORDER);
			} else {
				delete_sales_order(key($_SESSION['Items']->trans_no), $_SESSION['Items']->trans_type);

				display_notification(_("This sales order has been cancelled as requested."), 1);
				submenu_option(_("Enter a New Sales Order"), "/sales/sales_order_entry.php?NewOrder=Yes");
			}
		} else {
			processing_end();
			meta_forward($path_to_root.'/index.php','application=orders');
		}
	}
	processing_end();
	display_footer_exit();
}

//--------------------------------------------------------------------------------

function create_cart($type, $trans_no)
{ 
	global $Refs, $SysPrefs;

	if (!$SysPrefs->db_ok) // create_cart is called before page() where the check is done
		return;

	processing_start();

	if (isset($_GET['NewQuoteToSalesOrder']))
	{
		$trans_no = $_GET['NewQuoteToSalesOrder'];
		$doc = new Cart(ST_SALESQUOTE, $trans_no, true);
		$doc->Comments = _("Sales Quotation") . " # " . $trans_no;
		$_SESSION['Items'] = $doc;
	}	
	elseif($type != ST_SALESORDER && $type != ST_SALESQUOTE && $trans_no != 0) { // this is template

		$doc = new Cart(ST_SALESORDER, array($trans_no));
		$doc->trans_type = $type;
		$doc->trans_no = 0;
		$doc->document_date = new_doc_date();
		if ($type == ST_SALESINVOICE) {
			$doc->due_date = get_invoice_duedate($doc->payment, $doc->document_date);
			$doc->pos = get_sales_point(user_pos());
		} else
			$doc->due_date = $doc->document_date;
		$doc->reference = $Refs->get_next($doc->trans_type, null, array('date' => Today()));
		//$doc->Comments='';
		foreach($doc->line_items as $line_no => $line) {
			$doc->line_items[$line_no]->qty_done = 0;
		}
		$_SESSION['Items'] = $doc;
	} else
		$_SESSION['Items'] = new Cart($type, array($trans_no));
	copy_from_cart();
}

//--------------------------------------------------------------------------------

if (isset($_POST['CancelOrder']))
	handle_cancel_order();

$id = find_submit('Delete');
if ($id!=-1)
	handle_delete_item($id);

if (isset($_POST['UpdateItem']))
	handle_update_item();

if (isset($_POST['AddItem']))
	handle_new_item();

if (isset($_POST['CancelItemChanges'])) {
	line_start_focus();
}

//--------------------------------------------------------------------------------
if ($_SESSION['Items']->fixed_asset)
	check_db_has_disposable_fixed_assets(_("There are no fixed assets defined in the system."));
else
	check_db_has_stock_items(_("There are no inventory items defined in the system."));

check_db_has_customer_branches(_("There are no customers, or there are no customers with branches. Please define customers and customer branches."));

if ($_SESSION['Items']->trans_type == ST_SALESINVOICE) {
	$idate = _("Invoice Date:");
	$orderitems = _("Sales Invoice Items");
	$deliverydetails = _("Enter Delivery Details and Confirm Invoice");
	$cancelorder = _("Cancel Invoice");
	$porder = _("Place Invoice");
} elseif ($_SESSION['Items']->trans_type == ST_CUSTDELIVERY) {
	$idate = _("Delivery Date:");
	$orderitems = _("Delivery Note Items");
	$deliverydetails = _("Enter Delivery Details and Confirm Dispatch");
	$cancelorder = _("Cancel Delivery");
	$porder = _("Place Delivery");
} elseif ($_SESSION['Items']->trans_type == ST_SALESQUOTE) {
	$idate = _("Quotation Date:");
	$orderitems = _("Sales Quotation Items");
	$deliverydetails = _("Enter Delivery Details and Confirm Quotation");
	$cancelorder = _("Cancel Quotation");
	$porder = _("Place Quotation");
	$corder = _("Commit Quotations Changes");
} else {
	$idate = _("Order Date:");
	$orderitems = _("Sales Order Items");
	$deliverydetails = _("Enter Delivery Details and Confirm Order");
	$cancelorder = _("Cancel Order");
	$porder = _("Place Order");
	$corder = _("Commit Order Changes");
}
<?php
// ... [existing PHP code remains unchanged until the form section] ...

start_form();

hidden('cart_id');
$customer_error = display_order_header($_SESSION['Items'], !$_SESSION['Items']->is_started(), $idate);

// Add modern POS styling with functional stock items
echo '
<style>
/* Modern POS Styling */
.pos-container {
    display: flex;
    height: calc(100vh - 150px);
    font-family: "Segoe UI", sans-serif;
    background: #f5f7fa;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}

.pos-left-panel {
    width: 70%;
    background: #fff;
    padding: 15px;
    display: flex;
    flex-direction: column;
}

.pos-right-panel {
    width: 30%;
    background: #2c3e50;
    color: white;
    padding: 15px;
    display: flex;
    flex-direction: column;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
    margin-top: 15px;
    flex-grow: 1;
    overflow-y: auto;
}

.product-card {
    background: #fff;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border: 1px solid #eee;
}

.product-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.product-card img {
    width: 60px;
    height: 60px;
    object-fit: contain;
    margin-bottom: 5px;
}

.product-card .name {
    font-size: 12px;
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-card .price {
    font-size: 14px;
    font-weight: bold;
    color: #2ecc71;
}

.search-box {
    padding: 12px 15px;
    border: none;
    border-radius: 8px;
    background: #f1f5f9;
    font-size: 16px;
    margin-bottom: 15px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}

.order-summary {
    flex-grow: 1;
    overflow-y: auto;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.order-table {
    width: 100%;
    border-collapse: collapse;
}

.order-table th {
    text-align: left;
    padding: 8px 5px;
    font-weight: 600;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.order-table td {
    padding: 8px 5px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.totals-box {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 16px;
}

.grand-total {
    font-size: 20px;
    font-weight: bold;
    border-top: 1px solid rgba(255,255,255,0.3);
    padding-top: 10px;
}

.pos-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.pos-button {
    padding: 15px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s;
}

.pos-button.primary {
    background: #3498db;
    color: white;
}

.pos-button.success {
    background: #2ecc71;
    color: white;
}

.pos-button.danger {
    background: #e74c3c;
    color: white;
}

.pos-button:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Hide the old item entry form */
#item_add {
    display: none;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .pos-container {
        flex-direction: column;
        height: auto;
    }
    .pos-left-panel, .pos-right-panel {
        width: 100%;
    }
}
</style>
';

if ($customer_error == "") {
    // Get real stock items from database
    $sql = "SELECT stock_id, description, sales_price, units 
            FROM ".TB_PREF."stock_master 
            WHERE !inactive AND !no_sale";
    $result = db_query($sql, "could not get stock items");
    
    // Start POS container
    echo '<div class="pos-container">';
    
    // Left Panel - Products
    echo '<div class="pos-left-panel">';
    
    // Search box
    echo '<input type="text" id="productSearch" class="search-box" placeholder="🔍 Search products..." onkeyup="filterProducts()">';
    
    // Product grid
    echo '<div class="product-grid" id="productGrid">';
    
    // Display real stock items
    while ($myrow = db_fetch($result)) {
        $image = '../sales/images/product.png'; // Default image
        // In a real system, you might have: 
        // $image = get_stock_image_path($myrow['stock_id']);
        
        echo '<div class="product-card" onclick="addProduct(\''.$myrow['stock_id'].'\', \''.htmlspecialchars($myrow['description'], ENT_QUOTES).'\', '.$myrow['sales_price'].')">
                <img src="'.$image.'" alt="'.$myrow['description'].'">
                <div class="name">'.$myrow['description'].'</div>
                <div class="price">'.price_format($myrow['sales_price']).'</div>
              </div>';
    }
    
    echo '</div>'; // end product-grid
    echo '</div>'; // end pos-left-panel
    
    // Right Panel - Order Summary
    echo '<div class="pos-right-panel">';
    echo '<h2 style="margin-top:0; border-bottom:1px solid rgba(255,255,255,0.3); padding-bottom:10px;">Order Summary</h2>';
    
    echo '<div class="order-summary">';
    start_table(TABLESTYLE, "class='order-table'");
    $th = array(_("Item"), _("Qty"), _("Price"), _("Disc."), _("Total"), "");
    table_header($th);
    
    $total = 0;
    foreach ($_SESSION['Items']->line_items as $line_no => $line) {
        start_row();
        label_cell($line->stock_id);
        qty_cell($line->quantity, false, get_qty_dec($line->stock_id));
        amount_cell($line->price);
        percent_cell($line->discount_percent * 100);
        $line_total = $line->quantity * $line->price * (1 - $line->discount_percent);
        amount_cell($line_total);
        delete_button_cell("Delete$line_no", _("Delete"), _('Remove line from document'));
        end_row();
        $total += $line_total;
    }
    
    end_table();
    echo '</div>'; // end order-summary
    
    // Totals box
    echo '<div class="totals-box">';
    echo '<div class="total-row"><span>Subtotal:</span><span>'.price_format($_SESSION['Items']->get_items_total()).'</span></div>';
    echo '<div class="total-row"><span>Tax:</span><span>'.price_format($_SESSION['Items']->get_tax_total()).'</span></div>';
    echo '<div class="total-row"><span>Shipping:</span><span>'.price_format($_SESSION['Items']->freight_cost).'</span></div>';
    echo '<div class="total-row grand-total"><span>TOTAL:</span><span>'.price_format($_SESSION['Items']->get_trans_total()).'</span></div>';
    echo '</div>';
    
    // Payment buttons
    echo '<div class="pos-buttons">';
    echo '<button type="submit" class="pos-button danger" name="CancelOrder" value="1">'. _("Cancel").'</button>';
    echo '<button type="submit" class="pos-button success" name="ProcessOrder" value="1">'. _("Complete Sale").'</button>';
    echo '</div>';
    
    echo '</div>'; // end pos-right-panel
    echo '</div>'; // end pos-container
    
    // Hidden form fields for product selection
    hidden('stock_id', @$_POST['stock_id']);
    hidden('stock_id_text', @$_POST['stock_id_text']);
    hidden('price', @$_POST['price']);
    hidden('qty', 1, false); // Default quantity
    hidden('Disc', 0, false); // Default discount
    submit('AddItem', _("Add Item"), true, false, true); // Hidden button
    
    // JavaScript for product selection and search
    echo '
    <script>
    function addProduct(stock_id, description, price) {
        // Set form values
        document.getElementsByName("stock_id")[0].value = stock_id;
        document.getElementsByName("stock_id_text")[0].value = description;
        document.getElementsByName("price")[0].value = price;
        
        // Trigger the hidden "AddItem" button
        document.getElementsByName("AddItem")[0].click();
    }
    
    function filterProducts() {
        const input = document.getElementById("productSearch");
        const filter = input.value.toUpperCase();
        const grid = document.getElementById("productGrid");
        const cards = grid.getElementsByClassName("product-card");
        
        for (let i = 0; i < cards.length; i++) {
            const name = cards[i].getElementsByClassName("name")[0];
            if (name) {
                const txtValue = name.textContent || name.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    cards[i].style.display = "";
                } else {
                    cards[i].style.display = "none";
                }
            }
        }
    }
    
    // Initialize product search
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("productSearch").focus();
    });
    </script>
    ';
} else {
    display_error($customer_error);
}

end_form();
end_page();