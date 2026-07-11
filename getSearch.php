<?php
include "include/common.php";
include "include/common_variable.php";
include "include/connection.php";
 
$searchText = mysqli_real_escape_string($connect,post('searchText'));
$searchType = post('searchType');
$tblname = postSpaceFilter('tblname');
$path = "./data/" . "$tblname.json";

$f = fopen($path, 'r');
$c = fread($f, filesize($path));
fclose($f);

$cArr = json_decode($c,true);
$rstArr = array();

if($searchText != '')
{  if ($searchType == 'phonecode') {
    foreach ($cArr as $x) {
        $bool = stripos($x[$searchType], $searchText);
        if ($bool !== false) {
            $x[$searchType] = '+' . $x[$searchType]; // Add plus sign to the result
            array_push($rstArr, $x);
        }
    }
    }
    else{
        foreach($cArr as $x)
        {
            $bool = stripos($x[$searchType], $searchText);
            if ($bool === false && $tblname === PKG && isset($x['item_code'])) {
                $bool = stripos((string) $x['item_code'], $searchText);
            }
            if($bool !== false)
            {
                if ($tblname === PKG && isset($x['item_code']) && trim((string) $x['item_code']) !== '') {
                    $x['desc'] = (string) $x['name'] . ' (Item Code: ' . trim((string) $x['item_code']) . ')';
                    $x['val'] = isset($x['id']) ? (string) $x['id'] : '';
                    $x['select_text'] = isset($x['name']) ? (string) $x['name'] : '';
                }
                array_push($rstArr,$x);
            }
        }
    }
   
}


    if(sizeof($rstArr) == 0)
    {
        $rstArr[0]['desc'] = 'No Result';
        $rstArr[0]['val'] = "emptyValue";
    }



echo json_encode($rstArr);
?>
