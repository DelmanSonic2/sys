<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/TableHead.php';
require ROOT.'api/classes/OrderClass.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

if(!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT '.Pages::$limit;

switch($action){

    case 'get':

        $result = [];

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $ORDER_BY = Order::statistics_points(Pages::$field, Pages::$order);

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND (tr.name LIKE "%'.$search.'%" OR tr.address LIKE "%'.$search.'%")';

        $points = DB::query('
            SELECT tr.id, tr.name, tr.address, SUM(t.total) AS total, SUM(t.profit) AS profit,
                COUNT(t.id) AS check_count, AVG(t.total) AS avg_check, SUM(t.cost_price) AS cost_price,
                CONCAT(p.name, " ", p.surname, " ", p.middlename) AS partner,
                SUM(IF(t.type = 0, t.total, 0)) AS card, SUM(IF(t.type = 1, t.total, 0)) AS cash,
                SUM(t.sum - t.total) AS discount_sum, SUM(IF(t.points < 0, t.points, 0)) * -1 AS points_sum
            FROM '.DB_PARTNER_POINTS.' tr
            JOIN '.DB_TRANSACTIONS.' AS t ON t.point = tr.id
            JOIN '.DB_PARTNER.' p ON p.id = tr.partner
            WHERE t.created >= '.$from.' AND t.created < '.$to.$where_partner.$search.'
            GROUP BY tr.id
            '.$ORDER_BY.'
            '.$limit_str
        );
                                    
        if(!$export){
            while($row = DB::getRow($points)){

                $result[] = array('id' => $row['id'],
                                'partner' => $row['partner'],
                                'name' => $row['name'],
                                'address' => $row['address'],
                                'total' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($row['profit'], 2, ',', ' ').' '.CURRENCY,
                                'cost_price' => number_format($row['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                'check_count' => number_format($row['check_count'], 0, ',', ' ').' шт.',
                                'avg_check' => number_format($row['avg_check'], 2, ',', ' ').' '.CURRENCY,
                                'card' => number_format($row['card'], 2, ',', ' ').' '.CURRENCY,
                                'cash' => number_format($row['cash'], 2, ',', ' ').' '.CURRENCY,
                                'discount_sum' => number_format($row['discount_sum'], 2, ',', ' ').' '.CURRENCY,
                                'points_sum' => number_format($row['points_sum'], 2, ',', ' ').' '.CURRENCY
                    );

            }
        }
        else{

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_points($userToken['global_admin']), 'Заведения');

            $i = 1;

            while($row = DB::getRow($points)){

                $f_class->data[] = array('i' => $i,
                                        'partner' => $row['partner'],
                                        'name' => $row['name'],
                                        'address' => $row['address'],
                                        'total' => $row['total'],
                                        'cost_price' => $row['cost_price'],
                                        'profit' => $row['profit'],
                                        'check_count' => $row['check_count'],
                                        'avg_check' => $row['avg_check'],
                                        'card' => $row['card'],
                                        'cash' => $row['cash'],
                                        'discount_sum' => $row['discount_sum'],
                                        'points_sum' => $row['points_sum']
                );

                $i++;

            }

            $f_class->create();
        }

        $pages = DB::query('SELECT COUNT(t.id) AS count, SUM(t.total) AS total, SUM(t.profit) AS profit, SUM(t.check_count) AS check_count, AVG(t.avg_check) as avg_check,
                                        SUM(t.card) AS card, SUM(t.cash) AS cash, SUM(t.discount_sum) AS discount_sum, SUM(t.points_sum) AS points_sum
                                    FROM (  SELECT tr.id, tr.name, tr.address, SUM(t.total) AS total, SUM(t.profit) AS profit, COUNT(t.id) AS check_count, AVG(t.total) AS avg_check,
                                        SUM(IF(t.type = 0, t.total, 0)) AS card, SUM(IF(t.type = 1, t.total, 0)) AS cash,
                                        SUM(t.sum - t.total) AS discount_sum, SUM(IF(t.points < 0, t.points, 0)) * -1 AS points_sum
                                            FROM '.DB_PARTNER_POINTS.' tr
                                            JOIN '.DB_TRANSACTIONS.' AS t ON t.point = tr.id
                                            WHERE t.created >= '.$from.' AND t.created < '.$to.$where_partner.$search.'
                                            GROUP BY t.point) AS t');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0)
            $total_data = array('total' => number_format($pages['total'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($pages['profit'], 2, ',', ' ').' '.CURRENCY,
                                'check_count' => number_format($pages['check_count'], 0, ',', ' ').' шт.',
                                'avg_check' => number_format($pages['avg_check'], 2, ',', ' ').' '.CURRENCY,
                                'card' => number_format($pages['card'], 2, ',', ' ').' '.CURRENCY,
                                'cash' => number_format($pages['cash'], 2, ',', ' ').' '.CURRENCY,
                                'discount_sum' => number_format($pages['discount_sum'], 2, ',', ' ').' '.CURRENCY,
                                'points_sum' => number_format($pages['points_sum'], 2, ',', ' ').' '.CURRENCY
            );

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_points($userToken['global_admin']),
                        'total' => TableFooter::statistics_points($total_data));

        response('success', $result, 7, $pageData);


    break;

}