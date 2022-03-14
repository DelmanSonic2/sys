<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/classes/CategoriesClass.php';
require ROOT.'api/classes/TableHead.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

if($point = DB::escape($_REQUEST['point']))
    $point = ' AND tr.point = '.$point;

if(!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT '.Pages::$limit;

switch($action){

    case 'get':

    $result = [];

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $categories = DB::query('SELECT tr.id, tr.name, tr.parent, t.count, t.cost_price, t.total, t.profit
                                        FROM '.DB_PRODUCT_CATEGORIES.' tr
                                        LEFT JOIN ( SELECT pr.category, SUM(ti.count) AS count, SUM(ti.cost_price) AS cost_price, SUM(ti.total) AS total, SUM(ti.profit) AS profit
                                                    FROM '.DB_TRANSACTIONS.' tr
                                                    JOIN '.DB_TRANSACTION_ITEMS.' ti ON ti.transaction = tr.id
                                                    JOIN '.DB_PRODUCTS.' AS pr ON pr.id = ti.product
                                                    WHERE tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$point.'
                                                    GROUP BY pr.category
                                        ) t ON t.category = tr.id
                                        '.$WHERE_CATEGORY.'
                                        ORDER BY tr.name ASC');

        $total_data = array('product_count' => 0,
                            'cost_price' => 0,
                            'total' => 0,
                            'profit' => 0);

        $promotions = DB::query('SELECT COUNT(ti.id) AS count, SUM(ti.cost_price) AS cost_price, SUM(ti.total) AS total, SUM(ti.profit) AS profit
                                        FROM '.DB_TRANSACTIONS.' tr
                                        JOIN '.DB_TRANSACTION_ITEMS.' ti ON ti.transaction = tr.id
                                        WHERE ti.promotion IS NOT NULL AND tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$point);

        if(DB::getRecordCount($promotions) != 0){

            $promotions = DB::getRow($promotions);
            
            $total_data['product_count'] += $promotions['count'];
            $total_data['cost_price'] += $promotions['cost_price'];
            $total_data['total'] += $promotions['total'];
            $total_data['profit'] += $promotions['profit'];

            $result[$row['parent']][0] =  array('key' => 0,
                                                'id' => 0,
                                                'parent' => NULL,
                                                'category' => 'Акции',
                                                'count' => round($promotions['count'], 2),
                                                'cost_price' => round($promotions['cost_price'], 2),
                                                'total' => round($promotions['total'], 2),
                                                'profit' => round($promotions['profit'], 2),
                                                'children' => []);

        }

        while($row = DB::getRow($categories)){

            $total_data['product_count'] += $row['count'];
            $total_data['cost_price'] += $row['cost_price'];
            $total_data['total'] += $row['total'];
            $total_data['profit'] += $row['profit'];
            
            $result[$row['parent']][$row['id']] =  array('key' => $row['id'],
                                'id' => $row['id'],
                                'parent' => $row['parent'],
                                'category' => $row['name'],
                                'count' => round($row['count'], 2),
                                'cost_price' => round($row['cost_price'], 2),
                                'total' => round($row['total'], 2),
                                'profit' => round($row['profit'], 2),
                                'children' => []);
        }

        if($export = DB::escape($_REQUEST['export'])){

            $cat_class = new CategoriesClass($result, null, true);
            $cat_class->export(false, TableHead::statistics_categories(), 'Категории');

        }
        else
            $cat_class = new CategoriesClass($result, null);

        $total_data['product_count'] = number_format($total_data['product_count'], 0, ',', ' ').' шт';
        $total_data['cost_price'] = number_format($total_data['cost_price'], 2, ',', ' ').' '.CURRENCY;
        $total_data['total_data'] = $total_data['total'];
        $total_data['total'] = number_format($total_data['total'], 2, ',', ' ').' '.CURRENCY;
        $total_data['profit'] = number_format($total_data['profit'], 2, ',', ' ').' '.CURRENCY;

        $pageData = array('current_page' => 1,
                        'total_pages' => 1,
                        'rows_count' => 0,
                        'page_size' => 50,
                        'header' => TableHead::statistics_categories(),
                        'total' => TableFooter::statistics_categories($total_data));

        response('success', $cat_class->tree, 7, $pageData);

    break;

    /* case 'get1':

        $result = [];

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $categories = DB::query('SELECT pc.id, pc.name, COUNT(ti.id) AS count, SUM(ti.cost_price) AS cost_price, SUM(ti.total) AS total, SUM(ti.profit) AS profit
                                        FROM '.DB_TRANSACTION_ITEMS.' ti
                                        JOIN '.DB_PRODUCTS.' AS pr ON pr.id = ti.product
                                        JOIN '.DB_PRODUCT_CATEGORIES.' AS pc ON pc.id = pr.category
                                        JOIN '.DB_TRANSACTIONS.' AS tr ON tr.id = ti.transaction
                                        WHERE tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$point.'
                                        GROUP BY pr.category
                                        ORDER BY pc.name
                                        '.$limit_str);

        if(!$export){
            while($row = DB::getRow($categories)){

                $result[] = array('id' => $row['id'],
                                'category' => $row['name'],
                                'count' => number_format($row['count'], 0, ',', ' ').' шт.',
                                'cost_price' => number_format($row['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                'total' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($row['profit'], 2, ',', ' ').' '.CURRENCY);

            }
        }
        else{
            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_categories(), 'Категории');

            $i = 1;

            while($row = DB::getRow($categories)){

                $f_class->data[] = array('i' => $i,
                                        'category' => $row['name'],
                                        'count' => $row['count'],
                                        'cost_price' => $row['cost_price'],
                                        'total' => $row['total'],
                                        'profit' => $row['profit']);

                $i++;

            }

            $f_class->create();
        }

        $pages = DB::query('SELECT COUNT(t.id) AS count, SUM(t.total) AS total, SUM(t.profit) AS profit, SUM(t.cost_price) AS cost_price, SUM(t.count) as product_count
                                    FROM (SELECT pc.id, pc.name, COUNT(ti.id) AS count, SUM(ti.cost_price) AS cost_price, SUM(ti.total) AS total, SUM(ti.profit) AS profit
                                    FROM '.DB_TRANSACTION_ITEMS.' ti
                                    JOIN '.DB_PRODUCTS.' AS pr ON pr.id = ti.product
                                    JOIN '.DB_PRODUCT_CATEGORIES.' AS pc ON pc.id = pr.category
                                    JOIN '.DB_TRANSACTIONS.' AS tr ON tr.id = ti.transaction
                                    WHERE tr.created >= '.$from.' AND tr.created < '.$to.$where_partner.$point.'
                                    GROUP BY pr.category) AS t');

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        if($total_pages != 0)
            $total_data = array('product_count' => number_format($pages['product_count'], 0, ',', ' ').' шт.',
                                'cost_price' => number_format($pages['cost_price'], 2, ',', ' ').' '.CURRENCY,
                                'total' => number_format($pages['total'], 2, ',', ' ').' '.CURRENCY,
                                'profit' => number_format($pages['profit'], 2, ',', ' ').' '.CURRENCY);

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'header' => TableHead::statistics_categories(),
                        'total' => TableFooter::statistics_categories($total_data));

        response('success', $result, 7, $pageData);

    break; */

}