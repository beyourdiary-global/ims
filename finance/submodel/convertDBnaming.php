<?php

function convertDbNaming($key,$value){
   
    global $connect, $finance_connect;
    if($key == 'order_status'){
        if ($value == 'P') {
            return 'Processing';
        }else  if ($value == 'SP') {
           return 'Shipped';
        }else  if ($value == 'WP') {
            return 'Waiting Packing';
        }
        return;
    }else{
        $columnName='name';
        $tblName='';
        $connectDB = $connect;
        switch ($key) {
            case 'pic':
                $tblName = USR_USER;
                break;
            case 'brand':
                $tblName = BRAND;
                break;
            case 'package':
                $tblName = PKG;
                break;
            case 'shopee_acc':
                $tblName = SHOPEE_ACC;
                $connectDB = $finance_connect;
                break;
            case 'currency':
                $tblName = CUR_UNIT;
                $columnName ='unit';
                break;
            case 'buyer':
                $tblName = SHOPEE_CUST_INFO;
                $columnName = 'buyer_username';
                $connectDB = $finance_connect;
                break;
            default:
                $tblName = 'brand';
                break;
        }
        $result = getData($columnName, "id='" . $value . "'", '', $tblName, $connectDB);
        $row = mysqli_fetch_assoc($result);
        return $row[$columnName];
    }
     
    
}
?>