<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
***********************************************************************/
$path_to_root = "..";
$page_security = 'SA_GLANALYTIC';

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");

//----------------------------------------------------------------------------------------------------

function print_profit_loss_report()
{
	global $path_to_root;

	// الحصول على المعلمات من النظام
	$from = $_POST['PARAM_0'];
	$to = $_POST['PARAM_1'];
	$show_details = $_POST['PARAM_2'];
	$include_zeros = $_POST['PARAM_3'];
	$decimal_values = $_POST['PARAM_4'];
	$comments = $_POST['PARAM_5'];
	$orientation = $_POST['PARAM_6'];
	$destination = $_POST['PARAM_7'];

	// تحديد نوع التقرير
	if ($destination)
		include_once($path_to_root . "/reporting/includes/excel_report.inc");
	else
		include_once($path_to_root . "/reporting/includes/pdf_report.inc");

	$orientation = ($orientation ? 'L' : 'P');
	$dec = user_price_dec();

	// إنشاء التقرير
	$rep = new FrontReport(_('Profit and Loss Statement'), "ProfitLoss", user_pagesize(), 9, $orientation);
	
	// معلمات التقرير
	$params = array( 
		0 => $comments,
		1 => array('text' => _('Period'), 'from' => $from, 'to' => $to)
	);

	// إعداد الأعمدة
	$cols = array(0, 100, 250, 350, 450);
	$headers = array(_('Type'), _('Description'), _('Amount'), '');
	$aligns = array('left', 'left', 'right', 'right');
	
	if ($orientation == 'L')
		recalculate_cols($cols);

	$rep->Font();
	$rep->Info($params, $cols, $headers, $aligns);
	
	// قسم الإيرادات
	$rep->NewPage();
	$rep->Font('bold');
	$rep->TextCol(0, 2, _('REVENUES'));
	$rep->Font();
	$rep->NewLine(1.5);

	// بيانات افتراضية للإيرادات
	$rep->TextCol(0, 1, _('Sales'));
	$rep->TextCol(1, 2, _('Product Sales'));
	$rep->AmountCol(2, 3, 150000, $dec);
	$rep->NewLine();
	
	$rep->TextCol(0, 1, _('Services'));
	$rep->TextCol(1, 2, _('Service Income'));
	$rep->AmountCol(2, 3, 50000, $dec);
	$rep->NewLine();

	// خط الإجمالي للإيرادات
	$rep->Line($rep->row - 2);
	$rep->Font('bold');
	$rep->TextCol(0, 2, _('Total Revenues'));
	$rep->AmountCol(2, 3, 200000, $dec);
	$rep->Font();
	$rep->NewLine(2);

	// قسم المصروفات
	$rep->Font('bold');
	$rep->TextCol(0, 2, _('EXPENSES'));
	$rep->Font();
	$rep->NewLine(1.5);

	// بيانات افتراضية للمصروفات
	$rep->TextCol(0, 1, _('Salaries'));
	$rep->TextCol(1, 2, _('Employee Salaries'));
	$rep->AmountCol(2, 3, 80000, $dec);
	$rep->NewLine();
	
	$rep->TextCol(0, 1, _('Rent'));
	$rep->TextCol(1, 2, _('Office Rent'));
	$rep->AmountCol(2, 3, 20000, $dec);
	$rep->NewLine();
	
	$rep->TextCol(0, 1, _('Utilities'));
	$rep->TextCol(1, 2, _('Electricity, Water, etc.'));
	$rep->AmountCol(2, 3, 5000, $dec);
	$rep->NewLine();

	// خط الإجمالي للمصروفات
	$rep->Line($rep->row - 2);
	$rep->Font('bold');
	$rep->TextCol(0, 2, _('Total Expenses'));
	$rep->AmountCol(2, 3, 105000, $dec);
	$rep->Font();
	$rep->NewLine(2);

	// خط صافي الربح
	$rep->Line($rep->row - 2);
	$rep->Font('bold');
	$rep->TextCol(0, 2, _('NET PROFIT'));
	$rep->AmountCol(2, 3, 95000, $dec);
	$rep->Font();

	$rep->End();
}

// تشغيل التقرير
print_profit_loss_report();
?>