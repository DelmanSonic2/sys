<?php
use Support\Pages;
use Support\DB;

include 'setting.php';
include 'response.php';

switch($action){

    case 'colors':

        $colors = DB::select('*', DB_COLORS);

        $colors = DB::makeArray($colors);

        response('success', $colors, '7');

    break;

    case 'update':

        $from = strtotime(date('Y-m-d', time()));

        $update = DB::select('*', DB_SYSTEM_UPDATES, 'date_start >= '.$from.' AND complited = 0', 'date_start ASC');

        if(DB::getRecordCount($update) == 0)
            response('success', [], '7');

        $update = DB::getRow($update);

        $update['complited'] = (bool)$update['complited'];
        $update['lock'] = ($update['complited'] == 0 && $update['date_start'] <= time()) ? true : false;

        response('success', $update, '7');

    break;
}