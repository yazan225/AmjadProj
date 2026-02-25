<?php
class profit_loss_report extends report
{
    function __construct()
    {
        $this->name = _("Profit and Loss Statement");
        $this->description = _("Shows company profitability for a period");
        $this->type = 3; // GL reports
    }
    
    function get_data($params)
    {
        $from = $params['FromDate'];
        $to = $params['ToDate'];
        
        $data = array();
        
        // الحصول على بيانات الإيرادات
        $revenues = $this->get_accounts_data(ACT_INCOME, $from, $to);
        $data['revenues'] = $revenues;
        $data['total_revenue'] = array_sum(array_column($revenues, 'balance'));
        
        // الحصول على بيانات المصروفات
        $expenses = $this->get_accounts_data(ACT_OPERATING, $from, $to);
        $data['expenses'] = $expenses;
        $data['total_expense'] = array_sum(array_column($expenses, 'balance'));
        
        $data['net_profit'] = $data['total_revenue'] - $data['total_expense'];
        
        return $data;
    }
    
    function get_accounts_data($type, $from, $to)
    {
        $sql = "SELECT c.account_code, c.account_name, 
                SUM(g.amount) as balance
                FROM " . TB_PREF . "chart_master c
                LEFT JOIN " . TB_PREF . "gl_trans g ON c.account_code = g.account
                WHERE c.account_type = " . db_escape($type) . "
                AND g.tran_date >= '" . date2sql($from) . "'
                AND g.tran_date <= '" . date2sql($to) . "'
                GROUP BY c.account_code, c.account_name
                HAVING balance != 0
                ORDER BY c.account_code";
        
        $result = db_query($sql, "Could not get accounts data");
        $data = array();
        
        while($row = db_fetch($result)) {
            $data[] = $row;
        }
        
        return $data;
    }
}
?>