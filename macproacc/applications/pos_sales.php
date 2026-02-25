<?php
// Ensure this file is accessed within the FrontAccounting context
// The path_to_root might need adjustment based on your FA installation if this file is moved.
$path_to_root = ".."; 
include_once($path_to_root . "/includes/db_pager.inc"); // Include if your POS script uses pagination
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");

// Define a class that extends the 'application' class to register your custom module.
class pos_sales_app extends application
{
    function pos_sales_app()
    {
        // 1. Define the main application tab (the big button at the top of FA)
        // "pos_sales" is a unique identifier for your application.
        // "_(&POS Sales)" is the display name that will appear on the tab.
        // The '&' before 'P' (or any letter) creates a keyboard shortcut (Alt+P in this case).
        $this->application("pos_sales", _($this->help_context = "&POS Sales"));

        // 2. Add menu modules/links that appear when the "POS Sales" tab is clicked.
        // The first parameter: The display name of the link in the sub-menu.
        // The second parameter: The relative path to your actual POS interface file (testpos4.php in your case).
        //    Ensure this path is correct relative to the FrontAccounting root.
        // The third parameter: The security role required for a user to see this link.
        //    'SA_SALESINVOICE' is a standard FrontAccounting permission for sales invoices.
        //    You can choose a different one or create a new custom security role if needed.

        // This line adds the link to your POS interface.
        $this->add_module(_("Create POS Sales"), "sales/testpos4.php", "SA_SALESINVOICE");

        // You can add more sub-menu items here if your POS system has other pages
        // (e.g., for reporting, settings specific to POS, etc.)
        // Example:
        // $this->add_module(_("POS Reports"), "sales/pos_reports.php", "SA_SALESANALYTIC");
        // $this->add_module(_("POS Settings"), "sales/pos_settings.php", "SA_DIMENSIONSMaint");
    }
}
?>