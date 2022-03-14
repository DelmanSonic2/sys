<?php
use Support\Pages;
use Support\DB;
include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'get':

        DB::query('SET @@lc_time_names="ru_UA"');

        $ORDER_BY = Order::finances_report(Pages::$field, Pages::$order);

        if($from = DB::escape($_REQUEST['from']))
            $from = strtotime(date('Y-m-d', $from));
        else
            $from = strtotime(date('Y-m'), time());

        if($to = DB::escape($_REQUEST['to']))
            $to = strtotime('+1 day', strtotime(date('Y-m-d', $to)));
        else
            $to = strtotime('+1 month', $from);

        if(!$type = DB::escape($_REQUEST['type']))
            response('error', array('msg' => 'Не выбран критерий группировки.'), '533');

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND (t.point = '.$point.' OR t.point_second = '.$point.')';

        switch($type){

            case '1':
                $select = ', YEAR(FROM_UNIXTIME(date)) as label';
                $type = ', YEAR(FROM_UNIXTIME(date))';
            break;

            case '2':
                $select = ', CONCAT(QUARTER(FROM_UNIXTIME(date)), " квартал ", YEAR(FROM_UNIXTIME(date))) AS label';
                $type = ', YEAR(FROM_UNIXTIME(date)), QUARTER(FROM_UNIXTIME(date))';
            break;

            case '3':
                DB::query('SET @@lc_time_names="ru_UA"');
                $select = ', DATE_FORMAT( FROM_UNIXTIME(date), "%M %Y") AS label';
                $type = ', YEAR(FROM_UNIXTIME(date)), MONTH(FROM_UNIXTIME(date))';
            break;

            case '4':
                DB::query('SET @@lc_time_names="ru_RU"');
                $select = ', CONCAT(DATE_FORMAT(MIN(FROM_UNIXTIME(date)), "%d %M %Y"), " - ", DATE_FORMAT(MAX(FROM_UNIXTIME(date)), "%d %M %Y")) AS label';
                $type = ', YEAR(FROM_UNIXTIME(date)), MONTH(FROM_UNIXTIME(date)), WEEK(FROM_UNIXTIME(date))';
            break;

            case '5':
                DB::query('SET @@lc_time_names="ru_RU"');
                $select = ', DATE_FORMAT(FROM_UNIXTIME(date), "%d %M %Y") AS label';
                $type = ', YEAR(FROM_UNIXTIME(date)), MONTH(FROM_UNIXTIME(date)), DAY(FROM_UNIXTIME(date))';
            break;

            default:
                response('error', 'Неверное указан тип отчета.', 1);
        }

        $report = DB::query('SELECT c.id, c.name, SUM(t.sum) AS sum, t.type'.$select.'
                                    FROM '.DB_FINANCES_TRANSACTIONS.' t
                                    LEFT JOIN '.DB_FINANCES_CATEGORIES.' AS c ON c.id = t.category
                                    WHERE t.date >= '.$from.' AND t.date < '.$to.$point.'
                                    GROUP BY t.type, t.category'.$type.'
                                    '.$ORDER_BY);
                                    
        $report = DB::makeArray($report);

        response('success', $report, '7');

    break;

}