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

function CategoriesFilter($categories){

    $categories = explode(',', $categories);

    for($i = 0; $i < sizeof($categories); $i++){

        if(!$result)
            $result = ' AND (p.category = '.$categories[$i];
        else
            $result .= ' OR p.category = '.$categories[$i];

    }

    $result .= ')';

    return $result;

}

switch($action){

    case 'get':

        $result = [];

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND p.name LIKE "%'.$search.'%"';

        if($categories = DB::escape($_REQUEST['categories']))
            $categories = CategoriesFilter($categories);

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $ORDER_BY = Order::statistics_products(Pages::$field, Pages::$order);

        $products = DB::query('
            SELECT p.id, p.name, c.name AS category, SUM(tri.count) AS count, SUM(tri.weight) AS weight,
        SUM(FLOOR(IF(tri.weight > 0, tri.weight, tri.count) * tri.price)) AS without_discount,
        SUM(tri.total) AS revenue, SUM(tri.profit) AS profit, SUM(tri.cost_price) AS cost_price,
        (100 - (100 * SUM(tri.total) / SUM(FLOOR(IF(tri.weight > 0, tri.weight, tri.count) * tri.price)))) AS discount_percent
            FROM '.DB_TRANSACTION_ITEMS.' tri
            JOIN '.DB_TRANSACTIONS.' AS tr ON tr.id = tri.transaction
            JOIN '.DB_PRODUCTS.' AS p ON p.id = tri.product
            LEFT JOIN '.DB_PRODUCT_CATEGORIES.' AS c ON c.id = p.category
            WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$search.$point.$categories.'
            GROUP BY p.id
            '.$ORDER_BY.'
            '.$limit_str
        );

        if(!$export){
                                    
            while($row = DB::getRow($products)){

                if(!$where)
                    $where = ' AND (tc.product = '.$row['id'];
                else
                    $where .= ' OR tc.product = '.$row['id'];

                $result[] = array('id' => $row['id'],
                                'category' => $row['category'],
                                'product' => $row['name'],
                                'count' => number_format($row['count'], 0, ',', ' ').' шт.',
                                'weight' => number_format($row['weight'], 3, ',', ' ').' кг.',
                                'cost_price' => number_format($row['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                'without_discount' => number_format($row['without_discount'],0,',',' ').' '.CURRENCY,
                                'revenue' => number_format($row['revenue'], 2, ',', ' ').' '.CURRENCY,
                                'discount' => number_format($row['discount_percent'], 2, ',', ' ').' %',
                                'profit' => number_format($row['profit'], 2, ',', ' ').' '.CURRENCY,
                                'points' => number_format($row['points'], 2, ',', ' '),
                                'cards' => $row['cards'],
                                'children' => []);

            }

            if($where)
                    $where .= ')';
        }
        else{

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_products(), 'Товары');

            $i = 1;

            while($row = DB::getRow($products)){

                $f_class->data[] = array('i'=> $i,
                                        'product' => $row['name'],
                                        'category' => $row['category'],
                                        'count' => $row['count'],
                                        'weight' => $row['weight'],
                                        'cost_price' => $row['cost_price'],
                                        'without_discount' => $row['without_discount'],
                                        'discount' => $row['discount_percent'],
                                        'revenue' => $row['revenue'],
                                        'profit' => $row['profit']);

                $i++;

            }

            $f_class->create();

        }

        $sub_products = DB::query('
            SELECT tc.id, tc.product, CONCAT(IF(tc.subname != "", CONCAT("(", tc.subname, ") "), ""), tc.bulk_value, " ", tc.bulk_untils) AS name, SUM(tri.count) AS count, SUM(tri.weight) AS weight,
        SUM(FLOOR(IF(tri.weight > 0, tri.weight, tri.count) * tri.price)) AS without_discount,
        SUM(tri.total) AS revenue, SUM(tri.profit) AS profit, SUM(tri.cost_price) AS cost_price,
        (100 - (100 * SUM(tri.total) / SUM(FLOOR(IF(tri.weight > 0, tri.weight, tri.count) * tri.price)))) AS discount_percent
            FROM '.DB_TRANSACTION_ITEMS.' tri
            JOIN '.DB_TRANSACTIONS.' AS tr ON tr.id = tri.transaction
            JOIN '.DB_TECHNICAL_CARD.' tc ON tri.technical_card = tc.id
            WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$point.$where.'
            GROUP BY tc.id
        ');

        while($row = DB::getRow($sub_products)){

            for($i = 0; $i < count($result); $i++){

                if($row['product'] == $result[$i]['id']){

                    $result[$i]['children'][] = array('id' => $row['id'],
                                                    'product' => $row['name'],
                                                    'count' => number_format($row['count'], 0, ',', ' ').' шт.',
                                                    'weight' => number_format($row['weight'], 3, ',', ' ').' кг.',
                                                    'cost_price' => number_format($row['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                                    'without_discount' => number_format($row['without_discount'],0,',',' ').' '.CURRENCY,
                                                    'revenue' => number_format($row['revenue'], 2, ',', ' ').' '.CURRENCY,
                                                    'discount' => number_format($row['discount_percent'], 2, ',', ' ').' %',
                                                    'profit' => number_format($row['profit'], 2, ',', ' ').' '.CURRENCY,
                                                    'points' => number_format($row['points'], 2, ',', ' '));

                    break;
                }
            }
            
        }

        /* for($i = 0; $i < count($result); $i ++){

            if(count($result[$i]['children']) == 1){
                unset($result[$i]['children']);
            } 
        } */

        $pages = DB::query('
            SELECT COUNT(*) AS count, SUM(t.pr_count) AS pr_count, SUM(t.pr_weight) AS pr_weight, SUM(t.without_discount) AS without_discount, SUM(t.revenue) AS revenue, SUM(t.profit) AS profit, SUM(t.cost_price) AS cost_price
            FROM (
                SELECT SUM(tri.count) AS pr_count, SUM(tri.weight) AS pr_weight, SUM(FLOOR(IF(tri.weight > 0, tri.weight, tri.count) * tri.price)) AS without_discount,
            SUM(tri.total) AS revenue, SUM(tri.profit) AS profit, SUM(tri.cost_price) AS cost_price
                FROM '.DB_TRANSACTION_ITEMS.' tri
                JOIN '.DB_TRANSACTIONS.' AS tr ON tr.id = tri.transaction
                JOIN '.DB_PRODUCTS.' AS p ON p.id = tri.product
                LEFT JOIN '.DB_PRODUCT_CATEGORIES.' AS c ON c.id = p.category
                WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$search.$point.$categories.'
                GROUP BY p.id
            ) t
        ');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0){

            $total_data = array('count' => number_format($pages['pr_count'], 0, ',', ' ').' шт.',
                                'weight' => number_format($pages['pr_weight'], 3, ',', ' ').' кг.',
                                'without_discount' => number_format($pages['without_discount'], 2, ',', ' ').' '.CURRENCY,
                                'cost_price' => number_format($pages['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                'revenue' => number_format($pages['revenue'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($pages['profit'], 2, ',', ' ').' '.CURRENCY);
        }

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_products(),
                        'total' => TableFooter::statistics_products($total_data));
        
        response('success', $result, 7, $pageData);

    break;

}