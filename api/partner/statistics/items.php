<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/TableHead.php';
require ROOT.'api/classes/OrderClass.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

switch($action){

    case 'get':

        $result = [];

        if(!$export = DB::escape($_REQUEST['export']))
            $limit_str = 'LIMIT '.Pages::$limit;

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND tr.point = '.$point;

        if($category = DB::escape($_REQUEST['category']))
            $category = ' AND i.category = '.$category;

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND i.name LIKE "%'.$search.'%"';

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $ORDER_BY = Order::statistics_items(Pages::$field, Pages::$order);

        $to_dyd = date('Ym', $to);
        $from_dyd = date('Ym', $from);

        $items = DB::query('SELECT i.id, i.name, i.untils, ic.name AS category, SUM(tr.count) AS count, AVG(tr.price) AS price, SUM(tr.total) AS total
                                    FROM '.DB_PARTNER_TRANSACTIONS.' tr
                                    JOIN '.DB_ITEMS.' i ON i.id = tr.item
                                    LEFT JOIN '.DB_ITEMS_CATEGORY.' ic ON ic.id = i.category
                                    WHERE tr.proccess = 4 AND tr.date BETWEEN '.$from.' AND '.$to.' AND dyd BETWEEN '.$from_dyd.' AND '.$to_dyd.$point.$where_partner.$category.$search.'
                                    GROUP BY tr.item
                                    '.$ORDER_BY.'
                                    '.$limit_str);

        if(!$export){
            while($row = DB::getRow($items)){

                $result[] = array(  'id' => $row['id'],
                                    'name' => $row['name'],
                                    'category' => ($row['category'] == null) ? '' : $row['category'],
                                    'count' => number_format($row['count'] * -1, 3, ',', ' ').' '.$row['untils'],
                                    'price' => number_format($row['price'], 2, ',', ' ').' '.CURRENCY,
                                    'total' => number_format($row['total'] * -1, 2, ',', ' ').' '.CURRENCY);

            }
        }
        else{

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_items(), 'Ингредиенты');

            $i = 1;

            while($row = DB::getRow($items)){

                $f_class->data[] = array('i' => $i,
                                        'name' => $row['name'],
                                        'category' => $row['category'],
                                        'count' => round($row['count'] * -1, 2),
                                        'price' => round($row['price'], 2),
                                        'total' => round($row['total'] * -1, 2));

                $i++;

            }

            $f_class->create();
        }

        $pages = DB::query(' SELECT COUNT(t.id) AS count, SUM(IF(t.untils = "шт", t.count, 0)) AS pr_count, SUM(IF(t.untils = "л", t.count, 0)) AS pr_volume, SUM(IF(t.untils = "кг", t.count, 0)) AS pr_weight, SUM(t.total) AS total
                                    FROM (   SELECT i.id, i.untils, SUM(tr.count) AS count, AVG(tr.price) AS price, SUM(tr.total) AS total
                                            FROM '.DB_PARTNER_TRANSACTIONS.' tr
                                            JOIN '.DB_ITEMS.' i ON i.id = tr.item
                                            WHERE tr.proccess = 4 AND tr.date BETWEEN '.$from.' AND '.$to.' AND dyd BETWEEN '.$from_dyd.' AND '.$to_dyd.$point.$where_partner.$category.$search.'
                                            GROUP BY tr.item) t');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0){

            if($pages['pr_count'] != 0)
                $balance = number_format($pages['pr_count'] * -1, 0, ',', ' ').' шт';
            if($pages['pr_volume'] != 0){

                if(!$balance)
                    $balance = number_format($pages['pr_volume'] * -1, 3, ',', ' ').' л';
                else
                    $balance .= ', '.number_format($pages['pr_volume'] * -1, 3, ',', ' ').' л';

            }
            if($pages['pr_weight'] != 0){

                if(!$balance)
                    $balance = number_format($pages['pr_weight'] * -1, 3, ',', ' ').' кг';
                else
                    $balance .= ', '.number_format($pages['pr_weight'] * -1, 3, ',', ' ').' кг';

            }

            if($pages['pr_count'] == 0 && $pages['pr_volume'] == 0 && $pages['pr_weight'] == 0)
                $balance = 0;

            $total_data = array('product_count' => $balance,
                                'total' => number_format($pages['total'] * -1, 2, ',', ' ').' '.CURRENCY);
        }

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_items(),
                        'total' => TableFooter::statistics_items($total_data));

        response('success', $result, 7, $pageData);

    break;

}