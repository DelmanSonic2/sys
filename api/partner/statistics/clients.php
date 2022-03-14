<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/lib/functions.php';

require ROOT.'api/classes/TableHead.php';
require ROOT.'api/classes/OrderClass.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

if($point = DB::escape($_REQUEST['point']))
    $point = ' AND tr.point = '.$point;
else
    $point = '';

if(!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT '.Pages::$limit;

switch($action){

    case 'get':

        $result = [];

        $ORDER_BY = Order::statistics_clients(Pages::$field, Pages::$order);

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND (c.phone LIKE "%'.$search.'%" OR c.name LIKE "%'.$search.'%")';

        $clients = DB::query('SELECT c.phone, c.name, c.registration_date, SUM(IF(tr.type = 1, tr.total, 0)) AS cash, SUM(IF(tr.type = 0, tr.total, 0)) AS card,
                SUM(IF(tr.discount = 0, tr.total, 0)) AS without_discount, (SUM(IF(tr.points < 0, tr.points, 0)) * -1) AS points, SUM(tr.profit) AS profit, COUNT(tr.id) AS check_count, AVG(tr.total) AS avg_check
                                    FROM '.DB_CLIENTS.' c
                                    JOIN '.DB_TRANSACTIONS.' AS tr ON tr.client_phone = c.phone
                                    WHERE tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$search.$point.'
                                    GROUP BY c.phone
                                    '.$ORDER_BY.'
                                    '.$limit_str);

        if(!$export){
            while($row = DB::getRow($clients)){

                $result[] = array('phone' => $row['phone'],
                                'name' => $row['name'],
                                'registration_date' => ($row['registration_date'] == 0) ? '-' : UnixToDateRus((int)$row['registration_date'], true),
                                'cash' => number_format($row['cash'], 2, ',', ' ').' '.CURRENCY,
                                'card' => number_format($row['card'], 2, ',', ' ').' '.CURRENCY,
                                'without_discount' => number_format($row['without_discount'], 2, ',', ' ').' '.CURRENCY,
                                'points' => number_format($row['points'], 2, ',', ' '),
                                'profit' => number_format($row['profit'], 2, ',', ' ').' '.CURRENCY,
                                'check_count' => number_format($row['check_count'], 0, ',', ' ').' шт.',
                                'avg_check' => number_format($row['avg_check'], 2, ',', ' ').' '.CURRENCY);

            }
        }
        else{

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_clients(), 'Клиенты');

            $i = 1;

            while($row = DB::getRow($clients)){

                $f_class->data[] = array('i' => $i,
                                        'name' => $row['name'],
                                        'phone' => $row['phone'],
                                        'registration_date' => ($row['registration_date'] == 0) ? '' : UnixToDateRus((int)$row['registration_date'], true),
                                        'cash' => $row['cash'],
                                        'card' => $row['card'],
                                        'without_discount' => $row['without_discount'],
                                        'points' => $row['points'],
                                        'profit' => round($row['profit'], 2),
                                        'check_count' => $row['check_count'],
                                        'avg_check' => $row['avg_check']);

                $i++;


            }

            $f_class->create();

        }

        $pages = DB::query('SELECT COUNT(t.phone) AS count, SUM(t.check_count) AS check_count, SUM(t.cash) AS cash, SUM(t.card) AS card, SUM(t.without_discount) AS without_discount, SUM(t.profit) AS profit, AVG(t.avg_check) AS avg_check
                                    FROM (SELECT c.phone, COUNT(tr.id) AS check_count, SUM(IF(tr.type = 1, tr.total, 0)) AS cash, SUM(IF(tr.type = 0, tr.total, 0)) AS card,
                SUM(IF(tr.discount = 0, tr.total, 0)) AS without_discount, SUM(tr.profit) AS profit, AVG(tr.total) AS avg_check
                                        FROM '.DB_CLIENTS.' c
                                        JOIN '.DB_TRANSACTIONS.' AS tr ON tr.client_phone = c.phone
                                        WHERE tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$search.$point.'
                                        GROUP BY c.phone) t');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0)
            $total_data = array('without_discount' => number_format($pages['without_discount'], 2, ',', ' ').' '.CURRENCY,
                                'cash' => number_format($pages['cash'], 2, ',', ' ').' '.CURRENCY,
                                'card' => number_format($pages['card'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($pages['profit'], 2, ',', ' ').' '.CURRENCY,
                                'check_count' => number_format($pages['check_count'], 0, ',', ' ').' шт.',
                                'avg_check' => number_format($pages['avg_check'], 2, ',', ' ').' '.CURRENCY);

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_clients(),
                        'total' => TableFooter::statistics_clients($total_data));

        response('success', $result, 7, $pageData);

    break;

}