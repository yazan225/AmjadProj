<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
    Released under the terms of the GNU General Public License, GPL.
***********************************************************************/

class profit_loss_report extends report 
{
    function __construct($params, $options)
    {
        $this->name = _("Profit and Loss Statement");
        $this->description = _("Shows company profitability for a period");
        $this->type = 3; // GL reports
        
        // ãÊØáÈÇÊ ÇáãÚáãÇÊ
        $this->params = $params;
        
        // ÊÍÏíÏ ÇáÃÚãÏÉ
        $this->columns = array(
            array('width' => 15, 'title' => _('Account Code')),
            array('width' => 50, 'title' => _('Account Name')), 
            array('width' => 25, 'title' => _('Amount'))
        );
    }
    
    function run()
    {
        // ÇáÍÕæá Úáì ÇáãÚáãÇÊ
        $from = $this->params['FromDate'];
        $to = $this->params['ToDate'];
        $show_details = $this->params['ShowDetails'];
        $include_zeros = $this->params['IncludeZeroBalances'];
        
        // ÈíÇäÇÊ ÇáÊÞÑíÑ
        $report_data = array();
        
        // ÞÓã ÇáÅíÑÇÏÇÊ
        $report_data['revenues'] = $this->get_accounts_section(ACT_INCOME, $from, $to, $include_zeros);
        $report_data['total_revenue'] = $this->calculate_section_total($report_data['revenues']);
        
        // ÞÓã ÇáãÕÑæÝÇÊ  
        $report_data['expenses'] = $this->get_accounts_section(ACT_OPERATING, $from, $to, $include_zeros);
        $report_data['total_expense'] = $this->calculate_section_total($report_data['expenses']);
        
        // ÕÇÝí ÇáÑÈÍ/ÇáÎÓÇÑÉ
        $report_data['net_profit'] = $report_data['total_revenue'] - $report_data['total_expense'];
        $report_data['show_details'] = $show_details;
        
        return $report_data;
    }
    
    function get_accounts_section($type, $from, $to, $include_zeros)
    {
        $from_sql = date2sql($from);
        $to_sql = date2sql($to);
        
        $sql = "SELECT 
                    c.account_code, 
                    c.account_name, 
                    ABS(SUM(g.amount)) as balance
                FROM " . TB_PREF . "chart_master c
                LEFT JOIN " . TB_PREF . "gl_trans g ON c.account_code = g.account
                WHERE c.account_type = " . db_escape($type) . "
                    AND g.tran_date >= '" . $from_sql . "'
                    AND g.tran_date <= '" . $to_sql . "'
                GROUP BY c.account_code, c.account_name";
        
        if (!$include_zeros) {
            $sql .= " HAVING balance != 0";
        }
        
        $sql .= " ORDER BY c.account_code";
        
        $result = db_query($sql, "Could not get accounts data");
        $accounts = array();
        
        while($row = db_fetch($result)) {
            $accounts[] = $row;
        }
        
        return $accounts;
    }
    
    function calculate_section_total($accounts)
    {
        $total = 0;
        foreach($accounts as $account) {
            $total += $account['balance'];
        }
        return $total;
    }
}
?>