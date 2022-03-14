<?php
use Support\DB;
use Support\Pages;

include ROOT . 'api/partner/tokenCheck.php';
require ROOT . 'api/classes/OrderClass.php';

switch ($action) {

    case 'balance':

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $to_dyd = date('Ym', $to);

        if ($point = DB::escape($_REQUEST['point'])) {
            $point_where = ' AND pi.point = ' . $point;
            $point_subwhere = ' AND point = ' . $point;
        }

        if ($categories = DB::escape($_REQUEST['categories'])) {
            $categories_where = ' AND i.category IN (' . $categories . ')';
        }

        if ($search = DB::escape($_REQUEST['search'])) {
            $search = ' AND (i.name LIKE "%' . $search . '%" OR p.name LIKE "%' . $search . '%" OR ic.name LIKE "%' . $search . '%")';
        }

        $ORDER_BY = Order::balance(Pages::$field, Pages::$order);

        //Без лимита
        if ($_REQUEST['all'] == true) {
            $limit = "9999";
        } else {
            $limit = Pages::$limit;
        }

        $balanceData = DB::query('
            SELECT pi.id, i.id AS itemId, i.name AS itemName, p.id AS pointId, p.name AS pointName, ic.id AS catId, ic.name AS catName, i.untils, (pi.count - IFNULL(t.dif, 0)) AS count, ((pi.count - IFNULL(t.dif, 0)) * pi.price) AS sum, pi.price
            FROM ' . DB_POINT_ITEMS . ' pi
            JOIN ' . DB_ITEMS . ' i ON i.id = pi.item
            LEFT JOIN ' . DB_ITEMS_CATEGORY . ' AS ic ON ic.id = i.category
            JOIN ' . DB_PARTNER_POINTS . ' AS p ON p.id = pi.point
            LEFT JOIN (
                SELECT item, SUM(count) AS dif, point
                FROM ' . DB_PARTNER_TRANSACTIONS . '
                WHERE date >= ' . $to . ' AND dyd >= ' . $to_dyd . ' AND partner = ' . $userToken['id'] . $point_subwhere . '
                GROUP BY item, point
            ) t ON t.item = pi.item AND t.point = pi.point
            WHERE (pi.count != 0 OR t.dif != 0) AND (ROUND(pi.count, 2) != IF(t.dif IS NULL, 0, ROUND(t.dif, 2))) AND pi.partner = ' . $userToken['id'] . $point_where . $categories_where . $search . '
            ' . $ORDER_BY . '
            LIMIT ' . $limit
        );

        $result = [];

        while ($row = DB::getRow($balanceData)) {

            $result[] = array('id' => $row['id'],
                'item' => array('id' => $row['itemId'],
                    'name' => $row['itemName'],
                    'untils' => $row['untils']),
                'point' => array('id' => $row['pointId'],
                    'name' => $row['pointName']),
                'category' => array('id' => $row['catId'],
                    'name' => $row['catName']),
                'count' => number_format($row['count'], 3, ',', ' '),
                'count_origin' => round($row['count'], 2),
                'price' => number_format($row['price'], 2, ',', ' ') . ' ' . CURRENCY,
                'price_origin' => round($row['price'], 2),
                'sum' => number_format($row['sum'], 2, ',', ' ') . ' ' . CURRENCY,
                'sum_origin' => round($row['sum'], 2),

            );

        }

        $pages = DB::query('SELECT COUNT(t.id) AS count
                                    FROM (SELECT pi.id
                                        FROM ' . DB_POINT_ITEMS . ' pi
                                        JOIN ' . DB_ITEMS . ' i ON i.id = pi.item
                                        LEFT JOIN ' . DB_ITEMS_CATEGORY . ' AS ic ON ic.id = i.category
                                        JOIN ' . DB_PARTNER_POINTS . ' AS p ON p.id = pi.point
                                        LEFT JOIN (
                                            SELECT item, SUM(count) AS dif, point
                                            FROM ' . DB_PARTNER_TRANSACTIONS . '
                                            WHERE date >= ' . $to . ' AND dyd >= ' . $to_dyd . ' AND partner = ' . $userToken['id'] . $point_subwhere . '
                                            GROUP BY item, point
                                        ) t ON t.item = pi.item AND t.point = pi.point
                                        WHERE (pi.count != 0 OR t.dif != 0) AND (ROUND(pi.count, 2) != IF(t.dif IS NULL, 0, ROUND(t.dif, 2))) AND pi.partner = ' . $userToken['id'] . $point_where . $categories_where . $search . ') t');

        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else {
            $total_pages = 0;
        }

        $pageData = array('current_page' => (int) Pages::$page,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT,
            'rows_count' => (int) $pages['count']);

        response('success', $result, '7', $pageData);

        break;

    case 'transactions':

        $result = [];

        $transactions = DB::query('SELECT tr.id, i.id AS itemId, i.name AS itemName, tr.balance_begin, tr.average_price_begin, tr.count, tr.balance_end, tr.average_price_end
                                            FROM ' . DB_PARTNER_TRANSACTIONS . ' tr
                                            JOIN ' . DB_ITEMS . ' AS i ON i.id = tr.item
                                            WHERE tr.partner = ' . $userToken['id'] . '
                                            ORDER BY tr.id DESC
                                            LIMIT ' . Pages::$limit);

        while ($row = DB::getRow($transactions)) {
            $result[] = array('id' => $row['id'],
                'item' => array('id' => $row['itemId'],
                    'name' => $row['itemName']),
                'balance_begin' => $row['balance_begin'],
                'average_price_begin' => $row['average_price_begin'],
                'count' => $row['count'],
                'balance_end' => $row['balance_end'],
                'average_price_end' => $row['average_price_end']);
        }

        $pages = DB::select('COUNT(id) as count', DB_PARTNER_TRANSACTIONS, 'partner = ' . $userToken['id']);
        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else {
            $total_pages = 0;
        }

        $pageData = array('current_page' => (int) Pages::$page,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT,
            'total_count' => (int) $pages['count']);

        response('success', $result, '7', $pageData);

        break;

    case 'supplies':

        $element_count = 10;

        if (!$page || $page == 1) {
            $page = '1';
            $limit = '0,' . $element_count;
        } else {
            $begin = $element_count * $page - $element_count;
            $limit = $begin . ',' . $element_count;
        }

        if (!$position = DB::escape($_REQUEST['position'])) {
            response('error', array('msg' => 'Не передан ID позиции на складе.'), '372');
        }

        $positionData = DB::query('SELECT s.id, pi.point, s.date, si.count, i.untils, si.price, si.total, sup.id AS sid, sup.name AS sname
                                            FROM ' . DB_SUPPLY_ITEMS . ' si
                                            JOIN ' . DB_SUPPLIES . ' AS s ON si.supply = s.id
                                            JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = si.item AND s.pointTo = pi.point
                                            JOIN ' . DB_ITEMS . ' AS i ON i.id = si.item
                                            JOIN ' . DB_SUPPLIERS . ' AS sup ON sup.id = s.supplier
                                            WHERE s.partner = ' . $userToken['id'] . ' AND pi.id = ' . $position . ' AND s.type = 0 AND s.pointTo = pi.point
                                            ORDER BY s.date DESC');

        $result = [];

        if (DB::getRecordCount($positionData) == 0) {
            response('success', $result, '7');
        }

        while ($row = DB::getRow($positionData)) {

            $result[] = array('id' => $row['id'],
                'date' => $row['date'],
                'supplier' => array('id' => $row['sid'],
                    'name' => $row['sname']),
                'count' => $row['count'],
                'untils' => $row['untils'],
                'price' => $row['price'],
                'total' => $row['total']);
        }

        $pages = DB::query('SELECT COUNT(pi.id) AS count
                                    FROM ' . DB_SUPPLY_ITEMS . ' si
                                    JOIN ' . DB_SUPPLIES . ' AS s ON si.supply = s.id
                                    JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = si.item
                                    JOIN ' . DB_ITEMS . ' AS i ON i.id = si.item
                                    JOIN ' . DB_SUPPLIERS . ' AS sup ON sup.id = s.supplier
                                    WHERE s.partner = ' . $userToken['id'] . ' AND pi.id = ' . $position . ' AND s.type = 0 AND s.pointTo = pi.point');

        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else {
            $total_pages = 0;
        }

        $pageData = array('current_page' => (int) Pages::$page,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT,
            'total_count' => (int) $pages['count']);

        response('success', $result, '7', $pageData);

        break;

}