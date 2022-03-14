<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/TableHead.php';
require ROOT.'api/classes/OrderClass.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

$months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

/*if($point = DB::escape($_REQUEST['point']))
    $point = ' AND tr.point = '.$point;*/
if(isset($_REQUEST['point']))
    $point = DB::escape($_REQUEST['point']);

if(isset($_REQUEST['inn']))
    $inn_points = implode(',',array_column(DB::makeArray(DB::query("
            SELECT id FROM ".DB_PARTNER_POINTS." tr WHERE tr.inn LIKE '".DB::escape($_REQUEST['inn'])."'". $where_partner)),'id'));

if($point) $point = ' AND tr.point = '.$point;
else if ($inn_points) $point = ' AND tr.point IN ('.$inn_points.')';

$to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
$from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

if(!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT '.Pages::$limit;

switch($action){

    case 'get':

        $result = [];

        $ORDER_BY = Order::statistics_payments(Pages::$field, Pages::$order);

        $payment = DB::query('
            SELECT YEAR(tr.created_datetime) AS year, MONTH(tr.created_datetime) AS month, DAY(tr.created_datetime) AS day, COUNT(tr.id) AS checks,
                SUM(IF(tr.type = 1, tr.total, 0)) AS cash, SUM(IF(tr.type = 0, tr.total, 0)) AS card, (SUM(IF(tr.points < 0, tr.points, 0)) * -1) AS points, SUM(tr.total) AS total
            FROM '.DB_TRANSACTIONS.' tr
            WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$point.'
            GROUP BY YEAR(tr.created_datetime), MONTH(tr.created_datetime), DAY(tr.created_datetime)
            '.$ORDER_BY.'
            '.$limit_str
        );

        if(!$export){
            while($row = DB::getRow($payment)){

                $total = $row['cash'] + $row['card'] + $row['points'];

                $result[] = array('date' => $row['day'].' '.$months[$row['month'] - 1].' '.$row['year'],
                                'checks' => number_format($row['checks'],0,',',' ').' шт.',
                                'cash' => number_format($row['cash'], 2, ',', ' ').' '.CURRENCY,
                                'card' => number_format($row['card'], 2, ',', ' ').' '.CURRENCY,
                                'points' => number_format($row['points'], 2, ',', ' '),
                                'total' => number_format($total, 2, ',', ' ').' '.CURRENCY);

            }
        }
        else{

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_payments(), 'Оплаты');

            $i = 1;

            while($row = DB::getRow($payment)){

                $total = $row['cash'] + $row['card'] + $row['points'];
                //$total = $row['cash'] + $row['card'] + ($row['points'] * -1);

                $f_class->data[] = array('i' => $i,
                                        'date' => $row['day'].' '.$months[$row['month'] - 1].' '.$row['year'],
                                        'checks' => (int)$row['checks'],
                                        'cash' => round($row['cash'], 2),
                                        'card' => round($row['card'], 2),
                                        'points' => round($row['points'], 2),
                                        'total' => round($total, 2));

                $i++;

            }

            $f_class->create();
        }

        $pages = DB::query('SELECT COUNT(t.year) AS count, SUM(t.total) AS total, SUM(t.cash) AS cash, SUM(t.card) AS card, SUM(checks) AS checks, SUM(points) AS points
                                    FROM (SELECT COUNT(tr.id) AS checks, YEAR(tr.created_datetime) AS year, SUM(IF(tr.points < 0, tr.points, 0)) AS points, SUM(IF(tr.type = 1, tr.total, 0)) AS cash, SUM(IF(tr.type = 0, tr.total, 0)) AS card, SUM(tr.total) AS total
                                        FROM '.DB_TRANSACTIONS.' tr
                                        WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$point.'
                                        GROUP BY YEAR(tr.created_datetime), MONTH(tr.created_datetime), DAY(tr.created_datetime)) t');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0){

            $total = $pages['cash'] + $pages['card'] + ($pages['points'] * -1);

            $total_data = array('checks' => number_format($pages['checks'],0,',',' ').' шт.',
                                'cash' => number_format($pages['cash'], 2, ',', ' ').' '.CURRENCY,
                                'card' => number_format($pages['card'], 2, ',', ' ').' '.CURRENCY,
                                'points' => number_format($pages['points'] * -1, 2, ',', ' '),
                                'total' => number_format($total, 2, ',', ' ').' '.CURRENCY);
        }

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_payments(),
                        'total' => TableFooter::statistics_payments($total_data));

        response('success', $result, 7, $pageData);

    break;

}