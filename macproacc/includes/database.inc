<?php
// includes/database.inc - ??????? ??????
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
***********************************************************************/

function db_connect($dbname = null, $insert = false) {
    global $db, $db_connections;
    
    if ($db != null) return $db;
    
    // ??????? ?????? ??????????
    $company = 0; // ?????? ??????????
    if (isset($db_connections[$company])) {
        $connection = $db_connections[$company];
    } else {
        die("??????? ????? ???????? ??? ?????? ?????? $company");
    }
    
    $db_host = $connection['host'];
    $db_user = $connection['dbuser'];
    $db_pass = $connection['dbpassword'];
    $db_name = $connection['dbname'];
    
    // ??????? ?????? ????????
    $db = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    
    if (!$db) {
        die("??? ??????? ?????? ????????: " . mysqli_connect_error());
    }
    
    // ????? ???????
    mysqli_set_charset($db, 'utf8');
    
    return $db;
}

function db_query($sql, $err_msg = null) {
    global $db;
    
    if (!isset($db)) {
        db_connect();
    }
    
    $result = mysqli_query($db, $sql);
    
    if (!$result) {
        if ($err_msg === null) {
            $err_msg = "??? ?? ????? ?????????";
        }
        die("$err_msg: " . mysqli_error($db) . "<br>SQL: $sql");
    }
    
    return $result;
}

function db_fetch($result) {
    return mysqli_fetch_assoc($result);
}

function db_num_rows($result) {
    return mysqli_num_rows($result);
}

function db_escape($value, $null_to_empty = true) {
    global $db;
    
    if (!isset($db)) {
        db_connect();
    }
    
    if ($value === null) {
        return $null_to_empty ? '' : 'NULL';
    }
    
    return mysqli_real_escape_string($db, $value);
}

function db_insert_id() {
    global $db;
    return mysqli_insert_id($db);
}

function db_affected_rows() {
    global $db;
    return mysqli_affected_rows($db);
}

function db_error_no() {
    global $db;
    return mysqli_errno($db);
}

function db_error_msg() {
    global $db;
    return mysqli_error($db);
}

function db_close($link = null) {
    global $db;
    
    if ($link === null) {
        $link = $db;
    }
    
    if ($link) {
        mysqli_close($link);
    }
    
    if ($link === $db) {
        $db = null;
    }
}
?>