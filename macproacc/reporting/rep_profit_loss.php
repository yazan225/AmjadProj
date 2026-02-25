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
$page_security = 'SA_GLANALYTIC';
$path_to_root = "../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/gl/includes/gl_db.inc");
include_once($path_to_root . "/reporting/includes/class.general_ledger.inc");

$js = "";
if (user_use_date_picker())
    $js = get_js_date_picker();

page(_($help_context = "Profit and Loss Statement"), false, false, "", $js);

//----------------------------------------------------------------------------------------------------

function profit_loss_controls()
{
    global $path_to_root;
    
    start_form();
    start_table(TABLESTYLE_NOBORDER);

    $date = today();
    if (!isset($_POST['FromDate']))
        $_POST['FromDate'] = begin_fiscalyear();
    if (!isset($_POST['ToDate']))
        $_POST['ToDate'] = end_month($date);

    start_row();
    date_cells(_("From:"), 'FromDate', '', null, 0, -user_transaction_days());
    date_cells(_("To:"), 'ToDate');
    
    check_cells(_("Show Details"), 'ShowDetails', 1);
    check_cells(_("Include Zero Balances"), 'ShowZero');
    
    submit_cells('Generate', _("Generate Report"), '', '', 'default');
    end_row();
    
    end_table(1);
    end_form();
}

//----------------------------------------------------------------------------------------------------

function get_pl_accounts_balance($from_date, $to_date, $type, $show_details = false)
{
    $balances = array();
    $total = 0;
    
    // الحصول على حسابات النوع المحدد
    $accounts = get_gl_accounts(null, null, $type);
    
    while($account = db_fetch($accounts)) {
        // حساب الرصيد للحساب
        $balance = get_gl_trans_from_to($from_date, $to_date, $account['account_code']);
        
        if ($balance != 0 || check_value('ShowZero')) {
            if ($show_details) {
                $balances[] = array(
                    'code' => $account['account_code'],
                    'name' => $account['account_name'],
                    'balance' => $balance
                );
            }
            $total += $balance;
        }
    }
    
    return array('details' => $balances, 'total' => $total);
}

//----------------------------------------------------------------------------------------------------

function display_account_section($title, $accounts, $is_revenue = true)
{
    echo "<tr style='font-weight:bold; background-color:#e0e0e0;'>";
    echo "<td colspan='3'>" . $title . "</td>";
    echo "</tr>";
    
    $section_total = 0;
    
    foreach($accounts as $account) {
        $balance = $account['balance'];
        $abs_balance = abs($balance);
        
        if ($abs_balance != 0 || check_value('ShowZero')) {
            echo "<tr>";
            echo "<td width='15%'>" . $account['code'] . "</td>";
            echo "<td width='65%'>" . $account['name'] . "</td>";
            
            if ($is_revenue) {
                echo "<td width='20%' align='right'>" . number_format2($abs_balance, 2) . "</td>";
            } else {
                echo "<td width='20%' align='right'>" . number_format2($abs_balance, 2) . "</td>";
            }
            
            echo "</tr>";
            
            $section_total += $abs_balance;
        }
    }
    
    // عرض الإجمالي
    echo "<tr style='font-weight:bold; border-top:1px solid #000;'>";
    echo "<td colspan='2'>" . _("Total") . " " . $title . "</td>";
    echo "<td align='right'>" . number_format2($section_total, 2) . "</td>";
    echo "</tr>";
    
    echo "<tr><td colspan='3'>&nbsp;</td></tr>";
    
    return $section_total;
}

//----------------------------------------------------------------------------------------------------

function display_profit_loss_statement()
{
    global $path_to_root;
    
    $from = $_POST['FromDate'];
    $to = $_POST['ToDate'];
    $show_details = check_value('ShowDetails');
    
    // الحصول على بيانات الإيرادات
    $revenue_data = get_pl_accounts_balance($from, $to, ACT_INCOME, $show_details);
    $revenue_total = $revenue_data['total'];
    
    // الحصول على بيانات المصروفات
    $expense_data = get_pl_accounts_balance($from, $to, ACT_OPERATING, $show_details);
    $expense_total = $expense_data['total'];
    
    // حساب صافي الربح/الخسارة
    $net_profit = $revenue_total - $expense_total;
    
    start_table(TABLESTYLE, "width='95%' style='border:1px solid #000;'");
    
    // رأس التقرير
    echo "<tr>";
    echo "<td colspan='3' align='center' style='padding:10px;'>";
    echo "<h2 style='margin:0;'>" . _("PROFIT AND LOSS STATEMENT") . "</h2>";
    echo "<p style='margin:5px 0;'>" . _("For the period from") . " <b>" . $from . "</b> " . _("to") . " <b>" . $to . "</b></p>";
    echo "</td>";
    echo "</tr>";
    
    echo "<tr><td colspan='3' style='padding:5px;'>&nbsp;</td></tr>";
    
    // قسم الإيرادات
    if ($show_details) {
        $revenue_section_total = display_account_section(_("REVENUES"), $revenue_data['details'], true);
    } else {
        echo "<tr style='font-weight:bold; background-color:#e0e0e0;'>";
        echo "<td colspan='2'>" . _("TOTAL REVENUES") . "</td>";
        echo "<td align='right'>" . number_format2(abs($revenue_total), 2) . "</td>";
        echo "</tr>";
        echo "<tr><td colspan='3'>&nbsp;</td></tr>";
        $revenue_section_total = abs($revenue_total);
    }
    
    // قسم المصروفات
    if ($show_details) {
        $expense_section_total = display_account_section(_("EXPENSES"), $expense_data['details'], false);
    } else {
        echo "<tr style='font-weight:bold; background-color:#e0e0e0;'>";
        echo "<td colspan='2'>" . _("TOTAL EXPENSES") . "</td>";
        echo "<td align='right'>" . number_format2(abs($expense_total), 2) . "</td>";
        echo "</tr>";
        echo "<tr><td colspan='3'>&nbsp;</td></tr>";
        $expense_section_total = abs($expense_total);
    }
    
    // خط فاصل
    echo "<tr><td colspan='3'><hr style='border:1px solid #000;'></td></tr>";
    
    // صافي الربح/الخسارة
    echo "<tr style='font-weight:bold; background-color:#f0f0f0; font-size:1.1em;'>";
    echo "<td colspan='2'>" . _("NET PROFIT/LOSS") . "</td>";
    
    if ($net_profit >= 0) {
        echo "<td align='right' style='color:green;'>" . number_format2($net_profit, 2) . "</td>";
    } else {
        echo "<td align='right' style='color:red;'>(" . number_format2(abs($net_profit), 2) . ")</td>";
    }
    
    echo "</tr>";
    
    end_table(1);
    
    return $net_profit;
}

//----------------------------------------------------------------------------------------------------

function get_gl_trans_from_to($from_date, $to_date, $account_code)
{
    $from = date2sql($from_date);
    $to = date2sql($to_date);
    
    $sql = "SELECT SUM(amount) as total 
            FROM " . TB_PREF . "gl_trans 
            WHERE account = " . db_escape($account_code) . " 
            AND tran_date >= '" . $from . "' 
            AND tran_date <= '" . $to . "'";
    
    $result = db_query($sql, "Could not get gl transactions");
    $row = db_fetch($result);
    
    return $row['total'];
}

//----------------------------------------------------------------------------------------------------

// التحقق من الصلاحيات
check_page_security($page_security);

// عرض عناصر التحكم
profit_loss_controls();

if (isset($_POST['Generate'])) {
    div_start('pl_report');
    
    display_profit_loss_statement();
    
    // أزرار إضافية
    echo "<br><center>";
    echo "<input type='button' value='" . _("Print") . "' onclick='window.print();' class='button'>";
    echo "&nbsp;&nbsp;";
    echo "<input type='button' value='" . _("PDF") . "' onclick='generate_pdf();' class='button'>";
    echo "&nbsp;&nbsp;";
    echo "<input type='button' value='" . _("Excel") . "' onclick='generate_excel();' class='button'>";
    echo "</center>";
    
    // دالة JavaScript لإنشاء PDF
    echo "
    <script>
    function generate_pdf() {
        window.open('" . $path_to_root . "/reporting/reports_main.php?Class=3&report=profit_loss&FromDate=" . $_POST['FromDate'] . "&ToDate=" . $_POST['ToDate'] . "');
    }
    
    function generate_excel() {
        window.open('" . $path_to_root . "/reporting/reports_main.php?Class=3&report=profit_loss&Excel=1&FromDate=" . $_POST['FromDate'] . "&ToDate=" . $_POST['ToDate'] . "');
    }
    </script>
    ";
    
    div_end();
}

end_page();
?>