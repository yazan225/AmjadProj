<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
***********************************************************************/

//-----------------------------------------------------------------------------
//	Establish database connection
//
function db_connect($dbname = null, $insert = false)
{
	global $db, $db_connections, $db_type, $db_host, $db_user, $db_password;
	global $tb_pref, $db_server, $db_encoding, $db_port, $db_socket;

	// in case of repeat call return active link
	if ($db != null) return $db;

	if ($dbname == null) {
		$company = user_company();
		if (isset($db_connections[$company])) {
			$connection = $db_connections[$company];
		} else {
			$connection = $db_connections[0]; // default company
		}
	} else {
		$connection = $db_connections[0];
	}

	$db_type = 'mysql'; // assuming MySQL
	$db_server = $connection["host"];
	$db_port = $connection["port"];
	$db_socket = $connection["socket"] ?? '';
	$db_user = $connection["dbuser"];
	$db_password = $connection["dbpassword"];
	$db_encoding = $connection["collation"] ?? 'utf8mb4';

	if ($db_socket) {
		$db_host = $db_server . ':' . $db_socket;
	} elseif ($db_port) {
		$db_host = $db_server . ':' . $db_port;
	} else {
		$db_host = $db_server;
	}

	if ($insert) {
		$db = @mysqli_connect($db_host, $db_user, $db_password);
	} else {
		$db = mysqli_connect($db_host, $db_user, $db_password);
	}

	if (!$db) {
		if ($insert) {
			return null;
		} else {
			display_db_error("Could not connect to database server", mysqli_connect_error());
		}
	}

	if (!mysqli_select_db($db, $connection["dbname"])) {
		if ($insert) {
			return null;
		} else {
			display_db_error("Could not select database", mysqli_error($db));
		}
	}

	// set encoding
	if ($db_encoding) {
		mysqli_set_charset($db, $db_encoding);
	}

	return $db;
}

//-----------------------------------------------------------------------------
//	Execute SQL query
//
function db_query($sql, $err_msg = null)
{
	global $db, $sql_queries, $show_sql;

	if (!mysqli_ping($db)) {
		db_close($db);
		db_connect();
	}

	if (isset($show_sql) && $show_sql) {
		$sql_queries .= "<pre>$sql</pre>\n";
	}

	$result = mysqli_query($db, $sql);

	if (!$result) {
		if ($err_msg == null) {
			$err_msg = "Database query failed";
		}
		display_db_error($err_msg, mysqli_error($db), $sql);
	}

	return $result;
}

//-----------------------------------------------------------------------------
//	Fetch associative array
//
function db_fetch($result)
{
	return mysqli_fetch_assoc($result);
}

//-----------------------------------------------------------------------------
//	Fetch numeric array
//
function db_fetch_row($result)
{
	return mysqli_fetch_row($result);
}

//-----------------------------------------------------------------------------
//	Get number of rows
//
function db_num_rows($result)
{
	return mysqli_num_rows($result);
}

//-----------------------------------------------------------------------------
//	Get number of fields
//
function db_num_fields($result)
{
	return mysqli_num_fields($result);
}

//-----------------------------------------------------------------------------
//	Seek to specific row
//
function db_data_seek(&$result, $record)
{
	return mysqli_data_seek($result, $record);
}

//-----------------------------------------------------------------------------
//	Free result memory
//
function db_free_result($result)
{
	if (!is_bool($result)) {
		mysqli_free_result($result);
	}
}

//-----------------------------------------------------------------------------
//	Escape string for SQL
//
function db_escape($value, $null_to_empty = true)
{
	global $db;

	if (!$db) {
		db_connect();
	}

	if ($value === null) {
		return $null_to_empty ? '' : 'NULL';
	}

	return mysqli_real_escape_string($db, $value);
}

//-----------------------------------------------------------------------------
//	Get last error number
//
function db_error_no()
{
	global $db;
	return mysqli_errno($db);
}

//-----------------------------------------------------------------------------
//	Get last error message
//
function db_error_msg()
{
	global $db;
	return mysqli_error($db);
}

//-----------------------------------------------------------------------------
//	Get last insert ID
//
function db_insert_id()
{
	global $db;
	return mysqli_insert_id($db);
}

//-----------------------------------------------------------------------------
//	Get number of affected rows
//
function db_affected_rows()
{
	global $db;
	return mysqli_affected_rows($db);
}

//-----------------------------------------------------------------------------
//	Close database connection
//
function db_close($link = null)
{
	global $db;

	if ($link == null) {
		$link = $db;
	}

	if ($link) {
		mysqli_close($link);
	}

	if ($link == $db) {
		$db = null;
	}
}

//-----------------------------------------------------------------------------
//	Get field name
//
function db_field_name($result, $n)
{
	$finfo = mysqli_fetch_field_direct($result, $n);
	return $finfo->name;
}

//-----------------------------------------------------------------------------
//	Display database error
//
function display_db_error($msg, $sql_err = '', $sql = '')
{
	global $db, $show_sql, $sql_queries, $path_to_root;

	include_once($path_to_root . "/includes/errors.inc");
	$class = 'db';
	$type = 'error';

	$title = _('Database Error');

	$message = $msg;
	if ($sql_err != '')
		$message .= '<br>' . $sql_err;

	if (isset($show_sql) && $show_sql && $sql != '')
		$message .= "<br><pre>$sql</pre>";

	if (isset($show_sql) && $show_sql && isset($sql_queries) && $sql_queries != '')
		$message .= "<br>" . _('Previous SQL') . ":\n<pre>$sql_queries</pre>";

	display_error($title, $message, $class, $type);
}
?>