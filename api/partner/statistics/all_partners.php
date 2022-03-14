<?php
use Support\Pages;
use Support\DB;

if($userToken['global_admin']){

    //Если пришел id партнера, то выборка по конкретному
    if($partner = DB::escape($_REQUEST['partner'])){
        
        $regions = [];
        $regions_query = DB::select('id', DB_PARTNER, "parent = $partner");
        while($row = DB::getRow($regions_query))
            $regions[] = $row['id'];
        
        if(sizeof($regions) && stripos($_SERVER['REQUEST_URI'], 'statistics/points/get')){
            $regions = implode(',', $regions);
            $where_partner = " AND (tr.partner = $partner OR tr.partner IN ($regions))";
        }
        else
            $where_partner = " AND tr.partner = $partner";

    }
    //Иначе по всем
    else
        $where_partner = '';

}
else
    $where_partner = ' AND tr.partner = '.$userToken['id'];