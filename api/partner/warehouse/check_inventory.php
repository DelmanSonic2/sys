<?php
use Support\Pages;
use Support\DB;

function inventoryCheck($point, $date){

    

    $inventory_data = DB::query('SELECT i.id
                                        FROM '.DB_INVENTORY.' i
                                        WHERE i.point = '.$point.' AND i.date_end >= '.$date.' AND status = 1
                                        LIMIT 1');

    if(DB::getRecordCount($inventory_data) != 0)
        response('error', 'Нельзя проводить работу с документами, т.к. была проведена инвентаризация.', 1);

}