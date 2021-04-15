<?php
header("Content-Type: text/xml; charset=utf-8");
header("Cache-Control: max-age=1; must-revalidate");

require_once("IncDbMySQL.php");
$db = CustomPdo::make("treegrid_test", "root", 'ju$tc0diNgqwertz', "localhost");

$perPageRow = 100;

/** Process request data: XML -> PHP */
// Same process as in transfers_page.php, with a bit detail removed
$XML = array_key_exists("Data", $_REQUEST) ? $_REQUEST["Data"] : "";

if (function_exists("stripslashes")) {
	$XML = stripslashes($XML);
}

$SXML = is_callable("simplexml_load_string");
if (!$SXML) require_once("Xml.php");
if ($SXML) {
	$Xml = simplexml_load_string(html_entity_decode($XML));
} else {
	$Xml = CreateXmlFromString(html_entity_decode($XML));
}

/** Extract filters */
$fa = $Xml->Filters[0]->I;
$filters = array();

foreach ($fa->attributes() as $k => $v) {
	if ($pos = strpos($k, 'Filter')) {
		$tmp = array();
		$tmp['filter_type'] = $v;

		$colSearch = substr($k, 0, $pos);
		if (isset($fa[$colSearch])) {
			$tmp['filter_value'] = $fa[$colSearch];
			$tmp['col_name'] = $colSearch;
			
			// Check input
			$valCheck = explode(';', $tmp['filter_value']);
			// if input as array (separated by ";")
			if (is_array($valCheck) && count($valCheck) > 1) {
				foreach ($valCheck as &$val) {
				$val = "'" . trim($val) . "'";
				}

				$tmp['filter_value'] = implode(',', $valCheck);
				$tmp['filter_type'] = 13; // a new type of filter
			}

			$filters[] = $tmp;
		}
	}
}

/** (Partially) Construct WHERE clause for the query */
$whereFilter = "";
$valueArr = array(0, 1); // For `parent and `status`

foreach ($filters as $f) {
	$filterType = is_numeric($f['filter_type']) ? $f['filter_type'] : (int) $f['filter_type']->__toString();
	$colName = $f['col_name'];
	$filterValue = is_string($f['filter_value']) ? $f['filter_value'] : $f['filter_value']->__toString();

	switch ($filterType) {
		case 1:
			// equal
			$whereFilter .= "AND `{$colName}` = ? ";
			$valueArr[] = $filterValue;
			break;
		case 2:
			// not equal
			$whereFilter .= "AND `{$colName}` != ? ";
			$valueArr[] = $filterValue;
			break;
		case 3:
			// less than
			$whereFilter .= "AND `{$colName}` < ? ";
			$valueArr[] = $filterValue;
			break;
		case 4:
			// less than or equal
			$whereFilter .= "AND `{$colName}` <= ? ";
			$valueArr[] = $filterValue;
			break;
		case 5:
			// greater than
			$whereFilter .= "AND `{$colName}` > ? ";
			$valueArr[] = $filterValue;
			break;
		case 6:
			// greater than or equal
			$whereFilter .= "AND `{$colName}` >= ? ";
			$valueArr[] = $filterValue;
			break;
		case 7:
			// begins with
			$whereFilter .= "AND `{$colName}` LIKE ? ";
			$valueArr[] = "$filterValue%";
			break;
		case 8:
			// does not begin with
			$whereFilter .= "AND `{$colName}` NOT LIKE ? ";
			$valueArr[] = "$filterValue%";
			break;
		case 9:
			// ends with
			$whereFilter .= "AND `{$colName}` LIKE ? ";
			$valueArr[] = "%$filterValue";
			break;
		case 10:
			// does not ends with
			$whereFilter .= "AND `{$colName}` NOT LIKE ? ";
			$valueArr[] = "%$filterValue";
			break;
		case 11:
			// contains
			$whereFilter .= "AND `{$colName}` LIKE ? ";
			$valueArr[] = "%$filterValue%";
			break;
		case 12:
			// does not contains
			$whereFilter .= "AND `{$colName}` NOT LIKE ? ";
			$valueArr[] = "%$filterValue%";
			break;
		case 13:
			// in array
			$whereFilter .= "AND `{$colName}` IN ({$filterValue}) ";
			break;
		case 0:
		default:
			break;
	}
}

// Put credit_quantity column in variable and inside the loop to see the results for each page 
// --------------------------------------------------------------------------
$rs = $db->prepareAndExec(
	"SELECT COUNT(id) as count, MAX(grid_id) as last_grid_id, SUM(credit_quantity) as credit
	FROM transfers
	WHERE `Parent` = ? AND `status` = ? $whereFilter",
	$valueArr
);

$cnt = $rs->Get(0);
$total_credit = $rs->Get(2);
echo "<Grid><Cfg LastId='" . $rs->Get(1) . "' RootCount='" . $cnt . "'/><Body>";
$cnt = ceil($cnt / $perPageRow);
print_r($cnt);
for ($i = 0; $i < $cnt; $i++) {
	$credit = $rs->Get("credit");
    echo ("<B credit_quantity='$credit'/> ");// put credit_quantity column inside the loop to see the sum for each page and put the results in $credit variable
}
echo "</Body></Grid>";
// --------------------------------------------------------------------------
