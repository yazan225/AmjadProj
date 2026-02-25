<?php

/* List of installed additional extensions. If extensions are added to the list manually
	make sure they have unique and so far never used extension_ids as a keys,
	and $next_extension_id is also updated. More about format of this file yo will find in 
	FA extension system documentation.
*/

$next_extension_id = 13; // unique id for next installed extension

$installed_extensions = array (
  0 => 
  array (
    'name' => 'Arabic Egypt 8 digits COA - GAAP',
    'package' => 'chart_ar_EG-GAAP',
    'version' => '2.4.1-4',
    'type' => 'chart',
    'active' => false,
    'path' => 'sql',
    'sql' => 'ar_EG-8digits.sql',
  ),
  1 => 
  array (
    'name' => 'zen_import',
    'package' => 'zen_import',
    'version' => '2.4.0-4',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/zen_import',
  ),
  2 => 
  array (
    'name' => 'Cash Flow Statement Report',
    'package' => 'rep_cash_flow_statement',
    'version' => '2.4.0-3',
    'type' => 'extension',
    'active' => false,
    'path' => 'modules/rep_cash_flow_statement',
  ),
  4 => 
  array (
    'name' => '8 digit GAAP compatible American chart of accounts',
    'package' => 'chart_en_US-GAAP',
    'version' => '2.4.1-5',
    'type' => 'chart',
    'active' => false,
    'path' => 'sql',
    'sql' => 'en_US-GAAP.sql',
  ),
  12 => 
  array (
    'package' => 'pos_system',
    'name' => 'pos_system',
    'version' => '-',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/pos_system',
    'active' => false,
  ),
);
