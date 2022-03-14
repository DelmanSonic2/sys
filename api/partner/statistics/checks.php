<?php

use Support\Pages;
use Support\DB;

include ROOT . 'api/lib/functions.php';
include ROOT . 'api/partner/tokenCheck.php';
require ROOT . 'api/classes/TableHead.php';
require ROOT . 'api/classes/OrderClass.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

if ($point = DB::escape($_REQUEST['point']))
    $point = ' AND tr.point = ' . $point;

if (!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT ' . Pages::$limit;

switch ($action) {

    case 'get':

        $result = [];

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $ORDER_BY = Order::statistics_checks(Pages::$field, Pages::$order);

        $checks = DB::query('SELECT tr.id, e.name AS employee, tr.created, tr.fiscal, tr.fiscal_error, p.name AS point, tr.client_phone, c.name AS client_name,
                            c.group_id AS client_group, tr.sum, tr.total, tr.profit, tr.type, tr.discount, tr.points
                                    FROM ' . DB_TRANSACTIONS . ' tr
                                    LEFT JOIN ' . DB_EMPLOYEES . ' AS e ON e.id = tr.employee
                                    LEFT JOIN ' . DB_PARTNER_POINTS . ' AS p ON p.id = tr.point
                                    LEFT JOIN ' . DB_CLIENTS . ' AS c ON c.phone = tr.client_phone
                                    WHERE tr.created >= ' . $from . ' AND tr.created < ' . $to . $where_partner . $point . '
                                    ' . $ORDER_BY . '
                                    ' . $limit_str);



        if (!$export) {
            while ($row = DB::getRow($checks)) {

                if ($row['type'] == 0)
                    $row['type'] = 'Картой';
                if ($row['type'] == 1)
                    $row['type'] = 'Наличными';
                if ($row['type'] == 2)
                    $row['type'] = 'Бонусами';

                $fiscal = ['Нет', 'Да'];

                $minus_points = $plus_points = '-';

                if ($row['points'] > 0)
                    $plus_points = '+' . number_format($row['points'], 2, ',', ' ');

                if ($row['points'] < 0)
                    $minus_points = number_format($row['points'], 2, ',', ' ');

                $phone = ($row['client_phone'] == '') ? '' : $row['client_phone'];

                if ($phone) {
                    if ($phone[0] == '7') {
                        $phone = '+' . $phone;
                    } else {
                        $phone = '+7' . $phone;
                    }
                }

                /* if ($row['sum'] != $row['total'] && $row['discount'] == 0)
                    $row['discount'] = 100 - ($row['total'] * 100 / $row['sum']);*/

                $result[] = array(
                    'id' => $row['id'],
                    'employee' => $row['employee'],
                    'created' => UnixToDateRus($row['created'], true),
                    'point' => $row['point'],
                    'client_phone' => $phone,
                    'client_name' => ($phone == '') ? '' : $row['client_name'],
                    'sum' => number_format($row['sum'], 2, ',', ' ') . ' ' . CURRENCY,
                    'total' => number_format($row['total'], 2, ',', ' ') . ' ' . CURRENCY,
                    'discount' => number_format($row['discount'], 2, ',', ' ') . ' %',
                    'minus_points' => $minus_points,
                    'plus_points' => $plus_points,
                    'profit' => number_format($row['profit'], 2, ',', ' ') . ' ' . CURRENCY,
                    'type' => $row['type'],
                    'fiscal' => $fiscal[$row['fiscal']],
                    'fiscal_error' => $row['fiscal_error'],
                    'items' => []
                );

                if (!$where)
                    $where = 'transaction = ' . $row['id'];
                else
                    $where .= ' OR transaction = ' . $row['id'];
            }
        } else {
            require ROOT . 'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::statistics_checks(), 'Чеки');

            $i = 1;

            while ($row = DB::getRow($checks)) {

                if ($row['type'] == 0)
                    $row['type'] = 'Картой';
                if ($row['type'] == 1)
                    $row['type'] = 'Наличными';
                if ($row['type'] == 2)
                    $row['type'] = 'Бонусами';

                $minus_points = $plus_points = 0;

                if ($row['points'] > 0)
                    $plus_points = $row['points'];

                if ($row['points'] < 0)
                    $minus_points = $row['points'];

                $phone = ($row['client_phone'] == '') ? '' : $row['client_phone'];

                if ($phone) {
                    if ($phone[0] == '7') {
                        $phone = '+' . $phone;
                    } else {
                        $phone = '+7' . $phone;
                    }
                }

                $f_class->data[] = array(
                    'i' => $i,
                    'employee' => $row['employee'],
                    'created' => UnixToDateRus($row['created'], true),
                    'point' => $row['point'],
                    'client_name' => $row['client_name'],
                    'client_phone' => $phone,
                    'sum' => $row['sum'],
                    'total' => $row['total'],
                    'discount' => $row['discount'],
                    'minus_points' => $minus_points,
                    'plus_points' => $plus_points,
                    'profit' => $row['profit'],
                    'type' => $row['type']
                );

                $i++;
            }

            $f_class->create();
        }

        if ($where)
            $transaction_items = DB::select('transaction, name, count, price, total, cost_price, time_discount, promotion_name, type', DB_TRANSACTION_ITEMS, $where);

        while ($row = DB::getRow($transaction_items)) {

            for ($i = 0; $i < sizeof($result); $i++) {

                if ($result[$i]['id'] == $row['transaction']) {

                    $row['type'] = (int) $row['type'];

                    if ($row['type'] == 0)
                        $row['type'] = "Товар";
                    if ($row['type'] == 1)
                        $row['type'] = "Акция";

                    $result[$i]['items'][] = array(
                        'name' =>  $row['name'] . ' ' . $row['promotion_name'],
                        'count' => (int)$row['count'] . ' шт.',
                        'price' => number_format($row['price'], 2, ',', ' ') . ' ' . CURRENCY,
                        'time_discount' => number_format($row['time_discount'], 2, ',', ' ') . ' %',
                        'total' => number_format($row['total'], 2, ',', ' ') . ' ' . CURRENCY,
                        'cost_price' => number_format($row['cost_price'], 2, ',', ' ') . ' ' . CURRENCY,
                        'type' => $row['type']
                    );

                    break;
                }
            }
        }

        $pages = DB::query('SELECT COUNT(tr.id) AS count, SUM(tr.sum) AS sum, SUM(tr.total) AS total, SUM(tr.profit) AS profit
                                    FROM ' . DB_TRANSACTIONS . ' tr
                                    WHERE tr.created >= ' . $from . ' AND tr.created < ' . $to . $where_partner . $point);

        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else
            $total_pages = 0;

        if ($total_pages != 0)
            $total_data = array(
                'sum' => number_format($pages['sum'], 2, ',', ' ') . ' ' . CURRENCY,
                'total' => number_format($pages['total'], 2, ',', ' ') . ' ' . CURRENCY,
                'profit' => number_format($pages['profit'], 2, ',', ' ') . ' ' . CURRENCY
            );

        $pageData = array(
            'current_page' => (int)Pages::$page,
            'total_pages' => $total_pages,
            'rows_count' => (int)$pages['count'],
            'page_size' => ELEMENT_COUNT,
            'header' => TableHead::statistics_checks(),
            'total' => TableFooter::statistics_checks($total_data)
        );

        response('success', $result, 7, $pageData);

        break;
}