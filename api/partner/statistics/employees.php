<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/TableHead.php';
require ROOT.'api/classes/OrderClass.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

if($point = DB::escape($_REQUEST['point']))
    $point = ' AND tr.point = '.$point;

if(!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT '.Pages::$limit;

switch($action){

    case 'get':

        $result = [];

        $ORDER_BY = Order::statistics_employees(Pages::$field, Pages::$order);

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND (e.name LIKE "'.$search.'%" OR e.email LIKE "'.$search.'%")';

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $employees = DB::query('SELECT e.id, e.name, e.email, SUM(tr.total) AS revenue, SUM(tr.profit) AS profit, COUNT(tr.id) AS check_count, AVG(tr.total) AS avg_check
                                    FROM '.DB_EMPLOYEES.' e
                                    JOIN '.DB_TRANSACTIONS.' AS tr ON tr.employee = e.id
                                    WHERE tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$search.$point.'
                                    GROUP BY e.id
                                    '.$ORDER_BY.'
                                    '.$limit_str);

        if(!$export){
            while($row = DB::getRow($employees)){

                $result[] = array('id' => $row['id'],
                                'name' => $row['name'],
                                'email' => $row['email'],
                                'revenue' => number_format($row['revenue'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($row['profit'], 2, ',', ' ').' '.CURRENCY,
                                'check_count' => number_format($row['check_count'], 0, ',', ' ').' шт.',
                                'avg_check' => number_format($row['avg_check'], 2, ',', ' ').' '.CURRENCY);

            }
        }
        else{

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_employees(), 'Сотрудники');

            $i = 1;

            while($row = DB::getRow($employees)){

                $f_class->data[] = array('i' => $i,
                                        'name' => $row['name'],
                                        'revenue' => $row['revenue'],
                                        'profit' => $row['profit'],
                                        'check_count' => $row['check_count'],
                                        'avg_check' => $row['avg_check']);

                $i++;

            }

            $f_class->create();
        }

        $pages = DB::query('SELECT COUNT(t.id) AS count, SUM(t.revenue) AS revenue, SUM(t.profit) AS profit, SUM(t.check_count) AS check_count, AVG(t.avg_check) AS avg_check
                                    FROM (SELECT e.id, SUM(tr.total) AS revenue, SUM(tr.profit) AS profit, COUNT(tr.id) AS check_count, AVG(tr.total) AS avg_check
                                        FROM '.DB_EMPLOYEES.' e
                                        JOIN '.DB_TRANSACTIONS.' AS tr ON tr.employee = e.id
                                        WHERE tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$search.$point.'
                                        GROUP BY e.id) t');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0)
            $total_data = array('revenue' => number_format($pages['revenue'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($pages['profit'], 2, ',', ' ').' '.CURRENCY,
                                'check_count' => number_format($pages['check_count'], 0, ',', ' ').' шт.',
                                'avg_check' => number_format($pages['avg_check'], 2, ',', ' ').' '.CURRENCY);

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_employees(),
                        'total' => TableFooter::statistics_employees($total_data));

        response('success', $result, 7, $pageData);

    break;

}