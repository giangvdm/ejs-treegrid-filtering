<?php


header("Content-Type: text/xml; charset=utf-8");
header("Cache-Control: max-age=1; must-revalidate");

// --- Database parameters ---

require_once("IncDbMySQL.php");
$db = CustomPdo::make("treegrid_test", "root", 'ju$tc0diNgqwertz', "localhost");

// --- XML to PHP --- NO work here

$XML = array_key_exists("TGData", $_REQUEST) ? $_REQUEST["TGData"] : "";

if (function_exists("stripslashes")) {
   $XML = stripslashes($XML);
}

if (!$XML) $XML = "<Grid><Cfg SortCols='' SortTypes=''/><Body><B/></Body></Grid>";

$SXML = is_callable("simplexml_load_string");
if (!$SXML) require_once("Xml.php");
if ($SXML) {
   $Xml = simplexml_load_string(html_entity_decode($XML));
   $Cfg = $Xml->Cfg[0];
   $B = $Xml->Body->B[0];
} else {
   $Xml = CreateXmlFromString(html_entity_decode($XML));
   $Cfg = $Xml->getElementsByTagName($Xml->documentElement, "Cfg");
   $Cfg = $Cfg[0];
   $B = $Xml->getElementsByTagName($Xml->documentElement, "B");
   $B = $B[0];
}
$Cfg = $SXML ? $Cfg->attributes() : $Xml->attributes[$Cfg];
$B = $SXML ? $B->attributes() : $Xml->attributes[$B];
// --- end of simple xml or php xml ---     

// --- Parses sorting settings ---
$x = strtok($Cfg["SortCols"], ",");
$cnt = 0;
while ($x !== false) {
   $SC[$cnt++] = $x;
   $x = strtok(",");
}

$x = strtok($Cfg["SortTypes"], ",");
$i = 0;
while ($x !== false) {
   $ST[$i++] = $x;
   $x = strtok(",");
}

$S = "";
for ($i = 0; $i < $cnt; $i++) {
   if ($S != "") $S .= ", ";
   $S = $S . $SC[$i];
   if ($ST[$i] >= 1) $S .= " DESC";
}
if ($cnt) $S = " ORDER BY " . $S;


// --- XML to PHP --- No work here

$limit = 100;
$page = isset($_GET['page']) && $_GET['page'] != 0 && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1;

$fa = $Xml->Filters[0]->I;
$q = "SELECT * FROM transfers WHERE `Parent` = :parent AND `Def` =:Def ";

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

$offset = ($page - 1) * $limit;

$valueArr = [
   ':parent' => '0',
   ':limit' => $limit,
   ':Def' => 'Node',
   ':offset' => $offset
];

foreach ($filters as $f) {
   $filterType = is_numeric($f['filter_type']) ? $f['filter_type'] : (int) $f['filter_type']->__toString();
   $colName = $f['col_name'];
   $filterValue = is_string($f['filter_value']) ? $f['filter_value'] : $f['filter_value']->__toString();

   switch ($filterType) {
      case 1:
         // equal
         $q .= "AND `{$colName}` = :{$colName} ";
         $valueArr[":{$colName}"] = $filterValue;
         break;
      case 2:
         // not equal
         $q .= "AND `{$colName}` != :{$colName} ";
         $valueArr[":{$colName}"] = $filterValue;
         break;
      case 3:
         // less than
         $q .= "AND `{$colName}` < :{$colName} ";
         $valueArr[":{$colName}"] = $filterValue;
         break;
      case 4:
         // less than or equal
         $q .= "AND `{$colName}` <= :{$colName} ";
         $valueArr[":{$colName}"] = $filterValue;
         break;
      case 5:
         // greater than
         $q .= "AND `{$colName}` > :{$colName} ";
         $valueArr[":{$colName}"] = $filterValue;
         break;
      case 6:
         // greater than or equal
         $q .= "AND `{$colName}` >= :{$colName} ";
         $valueArr[":{$colName}"] = $filterValue;
         break;
      case 7:
         // begins with
         $q .= "AND `{$colName}` LIKE :{$colName} ";
         $valueArr[":{$colName}"] = "$filterValue%";
         break;
      case 8:
         // does not begin with
         $q .= "AND `{$colName}` NOT LIKE :{$colName} ";
         $valueArr[":{$colName}"] = "$filterValue%";
         break;
      case 9:
         // ends with
         $q .= "AND `{$colName}` LIKE :{$colName} ";
         $valueArr[":{$colName}"] = "%$filterValue";
         break;
      case 10:
         // does not ends with
         $q .= "AND `{$colName}` NOT LIKE :{$colName} ";
         $valueArr[":{$colName}"] = "%$filterValue";
         break;
      case 11:
         // contains
         $q .= "AND `{$colName}` LIKE :{$colName} ";
         $valueArr[":{$colName}"] = "%$filterValue%";
         break;
      case 12:
         // does not contains
         $q .= "AND `{$colName}` NOT LIKE :{$colName} ";
         $valueArr[":{$colName}"] = "%$filterValue%";
         break;
      case 13:
         // in array
         $q .= "AND `{$colName}` IN ({$filterValue}) ";
         break;
      case 0:
      default:
         break;
   }
}

// --- Query for Reading data from database for the current page ---
$statement = $db->prepare($q . "LIMIT :limit OFFSET :offset");

$rows = $db->execute($statement, $valueArr);

$rows = $rows->GetRows();
// --- Writes data for requested page ---

echo "<Grid>
<Body><B Pos='" . $B["Pos"] . "'>";
$cnt = count($rows);
foreach ($rows as $row) {
   echo " <I   Level='0' Def='Node' id='" . $row["grid_id"] . "'"
      . " document_type='" . $row["document_type"] . "'"
      . " document_abbrevation='" . $row["document_abbrevation"] . "'"
      . " document_no='" . $row["document_no"] . "'"
      . " posting_date='" . $row["posting_date"] . "'"
      . " document_date='" . $row["document_date"] . "'"
      . " warehouse_origin='" . $row["warehouse_origin"] . "'"
      . " warehouse_origin_code='" . $row["warehouse_origin_code"] . "'"
      . " warehouse_destination='" . $row["warehouse_destination"] . "'"
      . " warehouse_destination_code='" . $row["warehouse_destination_code"] . "'"
      . " company='" . htmlentities($row["company"], ENT_QUOTES) . "'" //this
      . " company_vat_no='" . $row["company_vat_no"] . "'"
      . " credit_quantity='" . $row["credit_quantity"] . "'"
      . " debit_quantity='" . $row["debit_quantity"] . "'"
      . " warehouseman='" . htmlentities($row["warehouseman"], ENT_QUOTES) . "'" //this
      . " warehouseman_department='" . $row["warehouseman_department"] . "'"
      . " warehouseman_approve='" . $row["warehouseman_approve"] . "'"
      . " deliveryman='" . htmlentities($row["deliveryman"], ENT_QUOTES) . "'" //this
      . " deliveryman_department='" . $row["deliveryman_department"] . "'"
      . " deliveryman_approve='" . $row["deliveryman_approve"] . "'"
      . " warehouseman_destination='" . htmlentities($row["warehouseman_destination"], ENT_QUOTES) . "'" //this
      . " warehouseman_destination_department='" . $row["warehouseman_destination_department"] . "'"
      . " warehouseman_destination_approve='" . $row["warehouseman_destination_approve"] . "'"
      . " status='" . $row["status"] . "'"
      . " note='" . $row["note"] . "'"
      . " item_uuid='" . $row["item_uuid"] . "'"
      . " warehouse_origin_uuid='" . $row["warehouse_origin_uuid"] . "'"
      . " warehouse_destination_uuid='" . $row["warehouse_destination_uuid"] . "'"
      . " />";

   if ($row['has_child']) {
      showChild($db, $row["grid_id"]);
   }
}

echo "</B></Body></Grid>";

// --- Child / Perent QUery

function showChild($db, $parentGridId = 0)
{
   $rows = $db->prepareAndExec("SELECT * FROM transfers WHERE `Parent` = :parent AND `Def` =:Def " , [
       ':parent' => $parentGridId,
       ':Def' => 'Data',
   ]);
   $rows = $rows->GetRows();
   
   foreach ($rows as $row) {
      echo  " <I Level='1' Def='Data' id='" . $row["grid_id"] . "'"
         . " name='" . $row["name"] . "'"
         . " type='" . $row["type"] . "'"
         . " code='" . $row["code"] . "'"
         . " barcode='" . $row["barcode"] . "'"
         . " brand='" . $row["brand"] . "'"
         . " category='" . $row["category"] . "'"
         . " subcategory='" . $row["subcategory"] . "'"
         . " unit='" . $row["unit"] . "'"
         . " cost='" . $row["cost"] . "'"
         . " price='" . $row["price"] . "'"
         . " credit_quantity='" . $row["credit_quantity"] . "'"
         . " debit_quantity='" . $row["debit_quantity"] . "'"
         . " note='" . $row["note"] . "'"
         . " item_uuid='" . $row["item_uuid"] . "'"
         . " warehouse_origin_uuid='" . $row["warehouse_origin_uuid"] . "'"
         . " warehouse_destination_uuid='" . $row["warehouse_destination_uuid"] . "'"
         . " />";
   }
}