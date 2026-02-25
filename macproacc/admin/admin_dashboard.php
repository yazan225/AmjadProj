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
	$path_to_root = "..";

	include_once($path_to_root . "/includes/session.inc");
	include_once($path_to_root . "/includes/ui.inc");
	include_once($path_to_root . "/includes/data_checks.inc");
	include_once($path_to_root . "/reporting/includes/class.graphic.inc");
	if (file_exists("$path_to_root/themes/".user_theme()."/dashboard.inc"))
		include_once("$path_to_root/themes/".user_theme()."/dashboard.inc");
	else
		include_once("$path_to_root/includes/dashboard.inc");

	$page_security = 'SA_SETUPCOMPANY';

	if (get_post('id')) {
		dashboard("admin_all");
		exit;
	}

	$js = "";
	if ($SysPrefs->use_popup_windows)
		$js .= get_js_open_window(800, 500);

	page(_($help_context = "Admin Dashboard"), false, false, "", $js);
	dashboard("admin_all");
	end_page();
