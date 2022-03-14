<?php

use Support\DB;
use Support\Pages;

include ROOT . 'api/partner/tokenCheck.php';
require ROOT . 'api/classes/TableHead.php';
require ROOT . 'api/classes/OrderClass.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

switch ($action) {

    case 'get':

        $result = [];

        if ($search = DB::escape($_REQUEST['search'])) {
            $where = 'WHERE p.name LIKE "%' . $search . '%"';
        }

        if ($categories = DB::escape($_REQUEST['categories'])) {
            if (!$where) {
                $where = 'WHERE FIND_IN_SET(p.category, "' . $categories . '")';
            } else {
                $where .= ' AND FIND_IN_SET(p.category, "' . $categories . '")';
            }

        }

        if ($point = DB::escape($_REQUEST['point'])) {
            $prodution_point = " AND ((prod.point=$point AND prod.point_to=0) OR (prod.point_to=$point)) ";
            $point = ' AND tr.point = ' . $point;
        }

        if ($export = DB::escape($_REQUEST['export'])) {
            require ROOT . 'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::removal_report(), 'Отчет по списаниям');
        }

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $ORDER_BY = Order::statistics_removal_report(Pages::$field, Pages::$order);

        $to += 60 * 60 * 24;

        $sum = 0;
        $profit = 0;
        $sales = 0;
        $count = 0;

        $removals = DB::query('
            SELECT tc.id, tc.weighted,  p.name, pc.name AS category, tc.product, tc.subname, tc.bulk_value, tc.bulk_untils, t.count, t.sum, tr.profit, tr.tr_count
            FROM (
                SELECT SUM(ri.count) AS count, SUM(ri.sum) AS sum, ri.item
                FROM ' . DB_REMOVAL_ITEMS . ' ri
                JOIN ' . DB_REMOVALS . ' tr ON tr.id = ri.removal
                WHERE tr.date BETWEEN ' . $from . ' AND ' . $to . ' AND ri.type = 1' . $where_partner . $point . '
                GROUP BY ri.item
            ) t
            LEFT JOIN(
                SELECT tri.technical_card, SUM(tri.profit) AS profit, SUM(tri.count) AS tr_count
                FROM ' . DB_TRANSACTIONS . ' tr
                JOIN ' . DB_TRANSACTION_ITEMS . ' tri ON tri.transaction = tr.id
                WHERE tr.created BETWEEN ' . $from . ' AND ' . $to . $where_partner . $point . '
                GROUP BY tri.technical_card
            ) tr ON tr.technical_card = t.item
            JOIN ' . DB_TECHNICAL_CARD . ' tc ON tc.id = t.item
            JOIN ' . DB_PRODUCTS . ' p ON p.id = tc.product
            LEFT JOIN ' . DB_PRODUCT_CATEGORIES . ' pc ON pc.id = p.category
            ' . $where . '
            ' . $ORDER_BY . '
        ');

        //Производство

        $produtions_result = DB::query("SELECT tc.id, tc.`bulk_untils` as untils, SUM(prod_items.count) as count FROM `app_technical_card` tc
        JOIN `app_product_composition` cm ON cm.`technical_card`=tc.id
        JOIN `app_production_items` prod_items ON prod_items.`product`=cm.`item`
        JOIN `app_productions` prod ON prod.`id`=prod_items.`production`
        WHERE prod.`date` BETWEEN  $from AND $to " . $prodution_point . " AND prod.partner=" . $userToken['id'] . "   GROUP BY tc.id");

        $produtions = [];

        while ($row = DB::getRow($produtions_result)) {
            $produtions[$row['id']] = number_format($row['count'], 2, ',', ' ') . ' ' . $row['untils'];
        }

        $total_sales = [
            'шт' => 0,
        ];
        $total_count = [
            'шт' => 0,
        ];

        while ($row = DB::getRow($removals)) {

            $exist = false;

            $removal_to_sale = !$row['tr_count'] ? 0 : $row['count'] / ($row['count'] + $row['tr_count']) * 100;
            $removal_to_profit = !$row['profit'] ? 0 : $row['sum'] / $row['profit'] * 100;

            $sales_val = "";
            $count_val = "";

            if ((int) $row['weighted'] == 1) {

                if (!isset($total_sales[$row['bulk_untils']])) {
                    $total_sales[$row['bulk_untils']] = 0;
                }

                if (!isset($total_count[$row['bulk_untils']])) {
                    $total_count[$row['bulk_untils']] = 0;
                }

                $total_sales[$row['bulk_untils']] += $row['bulk_value'] * $row['tr_count'];
                $total_count[$row['bulk_untils']] += $row['bulk_value'] * $row['count'];

                $sales_val = round($row['bulk_value'] * $row['tr_count']) . ' ' . $row['bulk_untils'];
                $count_val = round($row['bulk_value'] * $row['count']) . ' ' . $row['bulk_untils'];
            } else {

                $total_sales['шт'] += $row['tr_count'];
                $total_count['шт'] += $row['count'];

                $sales_val = $row['tr_count'] . ' шт';
                $count_val = $row['count'] . ' шт';
            }

            $children = array(
                'id' => $row['id'],
                'parent' => $row['product'],
                'name' => $row['name'] . ' ' . ($row['subname'] == '' ? '' : '(' . $row['subname'] . ') ') . $row['bulk_value'] . ' ' . $row['bulk_untils'],
                'category' => $row['category'] == null ? '' : $row['category'],
                'sales' => $sales_val, //$export ? round($row['tr_count'], 3) : number_format($row['tr_count'], 3, ',', ' ') . ' шт',
                'count' => $count_val, //$export ? round($row['count'], 3) : number_format($row['count'], 3, ',', ' ') . ' шт',
                'production' => isset($produtions[$row['id']]) ? $produtions[$row['id']] : "",
                'removal_to_sale' => $export ? round($removal_to_sale, 2) : number_format($removal_to_sale, 2, ',', ' ') . ' %',
                'profit' => $export ? round($row['profit'], 2) : number_format($row['profit'], 2, ',', ' '),
                'sum' => $export ? round($row['sum'], 2) : number_format($row['sum'], 2, ',', ' '),
                'removal_to_profit' => $export ? round($removal_to_profit, 2) : number_format($removal_to_profit, 2, ',', ' ') . ' %',
            );
            $result[] = $children;

            /*  for ($i = 0; $i < sizeof($result); $i++) {

            if ($result[$i]['id'] == $row['product']) {

            $result[$i]['count'] += $row['count'];
            $result[$i]['sum'] += $row['sum'];
            $result[$i]['profit'] += $row['profit'];
            $result[$i]['sales'] += $row['tr_count'];

            $result[$i]['children'][] = $children;

            $exist = true;
            break;
            }
            }*/

            /*  if (!$exist) {

        $children_arr = [];
        $children_arr[] = $children;

        $result[] = array(
        'id' => $row['product'],
        'parent' => null,
        'name' => $row['name'],
        'category' => $row['category'] == null ? '' : $row['category'],
        'sales' => $row['tr_count'],
        'count' => $row['count'],
        'removal_to_sale' => 0,
        'profit' => $row['profit'],
        'sum' => $row['sum'],
        'removal_to_profit' => 0,
        'children' => $children_arr
        );
        }*/
        }

        /*    for ($i = 0; $i < sizeof($result); $i++) {

        $removal_to_sale = !$result[$i]['sales'] ? 0 : $result[$i]['count'] / ($result[$i]['count'] + $result[$i]['sales']) * 100;
        $removal_to_profit = !$result[$i]['profit'] ? 0 : $result[$i]['sum'] / $result[$i]['profit'] * 100;

        $sales += $result[$i]['sales'];
        $count += $result[$i]['count'];
        $profit += $result[$i]['profit'];
        $sum += $result[$i]['sum'];

        if (sizeof($result[$i]['children']) == 1) {
        $result[$i]['parent'] = null;
        unset($result[$i]['children']);
        }

        $result[$i]['count'] = $export ? round($result[$i]['count'], 2) : number_format($result[$i]['count'], 3, ',', ' ') . ' шт';
        $result[$i]['sum'] = $export ? round($result[$i]['sum'], 2) : number_format($result[$i]['sum'], 2, ',', ' ') . ' ' . CURRENCY;
        $result[$i]['profit'] = $export ? round($result[$i]['profit'], 2) : number_format($result[$i]['profit'], 2, ',', ' ') . ' ' . CURRENCY;
        $result[$i]['sales'] = $export ? round($result[$i]['sales'], 2) : number_format($result[$i]['sales'], 3, ',', ' ') . ' шт';
        $result[$i]['removal_to_sale'] = $export ? round($removal_to_sale, 2) : number_format($removal_to_sale, 2, ',', ' ') . ' %';
        $result[$i]['removal_to_profit'] = $export ? round($removal_to_profit, 2) : number_format($removal_to_profit, 2, ',', ' ') . ' %';

        if ($export) {
        $children = false;
        if ($result[$i]['children']) {
        $children = $result[$i]['children'];
        unset($result[$i]['children']);
        }
        $f_class->data[] = $result[$i];
        if ($children)
        $f_class->data = array_merge($f_class->data, $children);
        }
        }*/

        if ($export) {
            $f_class->create(true);
        }

        $sales = [];
        foreach ($total_sales as $key => $val) {
            $sales[] = number_format($val, 2, ',', ' ') . ' ' . $key;
        }

        $count = [];
        foreach ($total_count as $key => $val) {
            $count[] = number_format($val, 2, ',', ' ') . ' ' . $key;
        }

        $profit = 0;
        $sum = 0;
        foreach ($result as $key => $val) {
            $profit = $profit + $val['profit'];
            $sum = $sum + $val['sum'];
        }

        $total_data = array(
            'sales' => implode(', ', $sales),
            'count' => implode(', ', $count),
            'profit' => number_format($profit, 2, ',', ' ') . ' ' . CURRENCY,
            'sum' => number_format($sum, 2, ',', ' ') . ' ' . CURRENCY,
        );

        $pageData = array(
            'current_page' => (int) Pages::$page,
            'total_pages' => $total_pages,
            'rows_count' => (int) $pages['count'],
            'page_size' => ELEMENT_COUNT,
            'header' => TableHead::removal_report(),
            'total' => TableFooter::removal_report($total_data),
        );

        response('success', $result, 200, $pageData);

        break;
}