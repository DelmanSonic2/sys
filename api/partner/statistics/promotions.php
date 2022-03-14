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

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $ORDER_BY = Order::statistics_promotions(Pages::$field, Pages::$order);

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND ti.promotion_name LIKE "'.$search.'%"';

        $promotions = DB::query('SELECT ti.promotion AS id, ti.promotion_name, SUM(ti.count) AS count, SUM(ti.cost_price) AS cost_price,
                        SUM(ti.price) AS price, SUM(ti.total) AS total, SUM(ti.discount) AS discount,
                        SUM(ti.time_discount) AS time_discount, SUM(ti.points) AS points, SUM(ti.profit) AS profit
                                        FROM '.DB_TRANSACTION_ITEMS.' ti
                                        JOIN '.DB_TRANSACTIONS.' tr ON tr.id = ti.transaction
                                        WHERE ti.type = 1 AND tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$search.$point.'
                                        GROUP BY ti.promotion
                                        '.$ORDER_BY.'
                                        '.$limit_str);

        if(!$export){

            while($row = DB::getRow($promotions))
                $result[] = array(  'id' => $row['id'],
                                    'name' => $row['promotion_name'],
                                    'count' => number_format($row['count'], 0, ',', ' ').' шт.',
                                    'cost_price' => number_format($row['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                    'total' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
                                    'profit' => number_format($row['profit'], 2, ',', ' ').' '.CURRENCY);

        }
        else{

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_promotions(), 'Акции');

            $i = 1;

            while($row = DB::getRow($promotions)){

                $f_class->data[] = array('i' => $i,
                                        'name' => $row['promotion_name'],
                                        'count' => (int)$row['count'],
                                        'cost_price' => round($row['cost_price'], 2),
                                        'total' => round($row['total'], 2),
                                        'profit' => round($row['profit'], 2));

                $i++;


            }

            $f_class->create();

        }

        $pages = DB::query('SELECT COUNT(t.promotion) AS count, SUM(t.pr_count) AS pr_count, SUM(t.cost_price) AS cost_price, SUM(t.total) AS total, SUM(t.profit) AS profit
                                    FROM (SELECT ti.promotion, SUM(ti.count) AS pr_count, SUM(ti.cost_price) AS cost_price, SUM(ti.total) AS total, SUM(ti.profit) AS profit
                                        FROM '.DB_TRANSACTION_ITEMS.' ti
                                        JOIN '.DB_TRANSACTIONS.' tr ON tr.id = ti.transaction
                                        WHERE ti.type = 1 AND tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$search.$point.'
                                        GROUP BY ti.promotion) t');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0){

            $total_data = array('count' => number_format($pages['pr_count'], 0, ',', ' ').' шт.',
                                'cost_price' => number_format($pages['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                'total' => number_format($pages['total'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($pages['profit'], 2, ',', ' ').' '.CURRENCY);
        }

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_promotions(),
                        'total' => TableFooter::statistics_promotions($total_data));

        response('success', $result, 7, $pageData);

    break;

}