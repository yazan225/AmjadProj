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
	$path_to_root=".";
	if (!file_exists($path_to_root.'/config_db.php'))
		header("Location: ".$path_to_root."/install/index.php");

	$page_security = 'SA_OPEN';
	ini_set('xdebug.auto_trace',1);
	include_once("includes/session.inc");

	add_access_extensions();

	// Redirect admin users to the admin dashboard by default (no specific application requested)
	if (!isset($_GET['application'])
		&& $_SESSION["wa_current_user"]->logged_in()
		&& $_SESSION["wa_current_user"]->can_access('SA_SETUPCOMPANY')) {
		header("Location: " . $path_to_root . "/admin/admin_dashboard.php");
		exit();
	}

	$app = &$_SESSION["App"];
	if (isset($_GET['application']))
		$app->selected_application = $_GET['application'];

	$app->display();
