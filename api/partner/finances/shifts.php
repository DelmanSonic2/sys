<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'get':

        $result = [];

        $ORDER_BY = Order::finances_shifts(Pages::$field, Pages::$order);

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND sh.point = '.$point;

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $shifts = DB::query('SELECT sh.id, e.name AS employee, p.name AS point, sh.shift_from, sh.shift_to, sh.revenue, sh.hours, sh.shift_closed AS closed
                                    FROM '.DB_EMPLOYEE_SHIFTS.' sh
                                    JOIN '.DB_EMPLOYEES.' AS e ON e.id = sh.employee
                                    JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = sh.point
                                    WHERE e.partner = '.$userToken['id'].' AND sh.shift_from >= '.$from.' AND sh.shift_to < '.$to.$point.' 
                                    '.$ORDER_BY.'
                                    LIMIT '.Pages::$limit);

        while($row = DB::getRow($shifts)){

            $row['shift_from'] = (int)$row['shift_from'];
            $row['shift_to'] = (int)$row['shift_to'];
            $row['revenue'] = round($row['revenue'], 2);
            $row['hours'] = (int)$row['hours'];
            $row['closed'] = (bool)$row['closed'];

            $result[] = $row;

        }

        $pages = DB::query('SELECT COUNT(sh.id) AS count
                                    FROM '.DB_EMPLOYEE_SHIFTS.' sh
                                    JOIN '.DB_EMPLOYEES.' AS e ON e.id = sh.employee
                                    JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = sh.point
                                    WHERE e.partner = '.$userToken['id'].' AND sh.shift_from >= '.$from.' AND sh.shift_to < '.$to.$point);

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT);

        response('success', $result, 7, $pageData);

    break;
}