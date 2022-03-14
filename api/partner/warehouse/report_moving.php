<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

function FilterItems(){

    

    $items = DB::escape($_REQUEST['items']);
    $items = stripslashes($items);
    $items = json_decode($items, true);

    if($items && sizeof($items) != 0){
        for($i = 0; $i < sizeof($items); $i++){

            if(!$result)
                $result = ' AND (item = '.$items[$i];
            else
                $result .= ' OR item = '.$items[$i];

        }

        if($result)
            $result .= ')';

    }

    return $result;

}

switch($action){

    case 'get':

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        if(($to - $from) > (63 * 24 * 60 * 60))
            response('error', 'Нельзя сформировать отчет за период более чем 2 месяца.', 422);

        $to_dyd = date('Ym', $to);
        $from_dyd = date('Ym', $from);

        $items = FilterItems();

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND point = '.$point;

        DB::query('LOCK TABLES '.DB_PARTNER_TRANSACTIONS.' READ, '.DB_ITEMS.' AS i READ, '.DB_POINT_ITEMS.' READ');

        $ORDER_BY = Order::report_moving(Pages::$field, Pages::$order);

        $balance = DB::query('
            SELECT i.id, i.name, t.item, t.costs, t.receipts, pi.price, pi.count, t.dif, t.average_price_begin, t.average_price_end, (pi.count - t.dif) AS balance_end, (pi.count - t.dif - t.costs - t.receipts) AS balance_begin
            FROM (
                SELECT AVG(average_price_begin) AS average_price_begin, AVG(average_price_end) AS average_price_end, item, SUM(IF(count < 0 AND date < '.$to.', count, 0)) AS costs, SUM(IF(count > 0 AND date < '.$to.', count, 0)) AS receipts, SUM(IF(date >= '.$to.', count, 0)) AS dif
                FROM '.DB_PARTNER_TRANSACTIONS.'
                WHERE date >= '.$from.' AND dyd >= '.$from_dyd.' AND partner = '.$userToken['id'].$point.$items.'
                GROUP BY item
                HAVING costs != 0 OR receipts != 0) t
            JOIN (
                SELECT AVG(price) AS price, item, SUM(count) AS count
                FROM '.DB_POINT_ITEMS.'
                WHERE partner = '.$userToken['id'].$point.$items.'
                GROUP BY item) pi ON pi.item = t.item
            JOIN '.DB_ITEMS.' i ON i.id = t.item
            WHERE i.partner = '.$userToken['id'].' OR i.partner IS NULL
            '.$ORDER_BY.'
            LIMIT '.Pages::$limit
        );

        $page_query = '
            SELECT COUNT(t.item) AS count
            FROM (
                SELECT item, SUM(IF(count < 0 AND date < '.$to.', count, 0)) AS costs, SUM(IF(count > 0 AND date < '.$to.', count, 0)) AS receipts
                FROM '.DB_PARTNER_TRANSACTIONS.'
                WHERE date >= '.$from.' AND dyd >= '.$from_dyd.' AND partner = '.$userToken['id'].$items.'
                GROUP BY item
                HAVING costs != 0 OR receipts != 0
            ) t
            JOIN (
                SELECT AVG(price) AS price, item, SUM(count) AS count
                FROM '.DB_POINT_ITEMS.'
                WHERE partner = '.$userToken['id'].$point.$items.'
                GROUP BY item) pi ON pi.item = t.item
        ';
        DB::query('UNLOCK TABLES');

        $page_data = Pages::GetPageInfo($page_query, $page);

        $result = [];

        while($row = DB::getRow($balance)){

            /* $balance_end = $row['count'] - $row['dif'];
            $balance_begin = $balance_end - $row['receipts'] - $row['costs']; */

            $result[] = array('id' => $row['id'],
                            'name' => $row['name'],
                            'balance_begin' => number_format($row['balance_begin'], 3, ',', ' ').' '.$row['untils'],
                            'average_price_begin' => number_format($row['average_price_begin'], 2, ',', ' ').' '.' '.CURRENCY,
                            'receipts' => number_format($row['receipts'], 3, ',', ' ').' '.$row['untils'],
                            'costs' => number_format($row['costs'], 3, ',', ' ').' '.$row['untils'],
                            'balance_end' => number_format($row['balance_end'], 3, ',', ' ').' '.$row['untils'],
                            'average_price_end' => number_format($row['average_price_end'], 2, ',', ' ').' '.' '.CURRENCY);

            if(!$where)
                $where = 'item = '.$row['id'];
            else
                $where .= ' OR item = '.$row['id'];

        }

        response('success', $result, 7, $page_data);

    break;

    case 'costs':

        if(!$item = DB::escape($_REQUEST['item']))
            response('error', 'Не передан ID товара.', 1);

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND pt.point = '.$point;

        $costs = DB::query('SELECT SUM(IF((pt.proccess = 1 AND pt.count < 0), pt.count, 0)) AS moving_count, SUM(IF((pt.proccess = 2 AND pt.count < 0), pt.count, 0)) AS inventory_count, SUM(IF((pt.proccess = 3 AND pt.count < 0), pt.count, 0)) AS removals_count, SUM(IF((pt.proccess = 4 AND pt.count < 0), pt.count, 0)) AS sales_count, SUM(IF((pt.proccess = 5 AND pt.count < 0), pt.count, 0)) AS production_count, i.untils,
                                            SUM(IF((pt.proccess = 1 AND pt.total < 0), pt.total, 0)) AS moving_total, SUM(IF((pt.proccess = 2 AND pt.total < 0), pt.total, 0)) AS inventory_total, SUM(IF((pt.proccess = 3 AND pt.total < 0), pt.total, 0)) AS removals_total, SUM(IF((pt.proccess = 4 AND pt.total < 0), pt.total, 0)) AS sales_total, SUM(IF((pt.proccess = 5 AND pt.total < 0), pt.total, 0)) AS production_total
                                    FROM '.DB_PARTNER_TRANSACTIONS.' pt
                                    JOIN '.DB_ITEMS.' AS i ON i.id = pt.item
                                    WHERE pt.item = '.$item.' AND pt.date >= '.$from.' AND pt.date < '.$to.' AND pt.partner = '.$userToken['id'].$point);

        $costs = DB::getRow($costs);


        $result = array(

            'moving' => array(  'name' => 'Перемещения',
                                'count' => number_format($costs['moving_count'], 3, ',', ' ').' '.$costs['untils'],
                                'total' => number_format($costs['moving_total'], 2, ',', ' ').' '.CURRENCY),
            'inventory' => array(   'name' => 'Инвентаризации',
                                    'count' => number_format($costs['inventory_count'], 3, ',', ' ').' '.$costs['untils'],
                                    'total' => number_format($costs['inventory_total'], 2, ',', ' ').' '.CURRENCY),
            'sales' => array(   'name' => 'Продажи',
                                'count' => number_format($costs['sales_count'], 3, ',', ' ').' '.$costs['untils'],
                                'total' => number_format($costs['sales_total'], 2, ',', ' ').' '.CURRENCY),
            'removals' => array(    'name' => 'Ручные списания',
                                    'count' => number_format($costs['removals_count'], 3, ',', ' ').' '.$costs['untils'],
                                    'total' => number_format($costs['removals_total'], 2, ',', ' ').' '.CURRENCY),
            'productions' => array( 'name' => 'Производство',
                                    'count' => number_format($costs['production_count'], 3, ',', ' ').' '.$costs['untils'],
                                    'total' => number_format($costs['production_total'], 2, ',', ' ').' '.CURRENCY)

        );

        response('success', $result, 7);


    break;

    case 'details':

        if(!$item = DB::escape($_REQUEST['item']))
            response('error', 'Не передан ID товара.', 1);

        if($filter = DB::escape($_REQUEST['filter'])){

            $posArr = array('supply' => 0,
                        'moving' => 1,
                        'inventory' => 2,
                        'removal' => 3,
                        'sale' => 4,
                        'production' => 5);

            $tab = $posArr[$filter];

            if(!isset($posArr[$filter])) $tab = false;

        }
        else $tab = false;

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $to_dyd = date('Ym', $to);
        $from_dyd = date('Ym', $from);

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND pt.point = '.$point;

        //Получаем текущие остатки
        $balance_end = DB::query('
            SELECT i.untils, (SUM(pt.count) - IFNULL(t.dif, 0)) AS count
            FROM '.DB_POINT_ITEMS.' pt
            JOIN '.DB_ITEMS.' i ON pt.item = i.id
            LEFT JOIN (
                SELECT SUM(pt.count) AS dif, pt.item
                FROM '.DB_PARTNER_TRANSACTIONS.' pt
                WHERE pt.item = '.$item.' AND pt.date > '.$to.' AND pt.partner = '.$userToken['id'].$point.'
            ) t ON t.item = pt.item
            WHERE pt.item = '.$item.' AND pt.partner = '.$userToken['id'].$point.'
            LIMIT 1
        ');

        $row = DB::getRow($balance_end);

        $untils = $row['untils'];
        $balance_end = $row['count'];

        $details = DB::query('
            SELECT pt.id, pt.count, pt.proccess_id, pt.price, pt.average_price_begin, pt.average_price_end, p.name AS point_to, pt.proccess, sup.name AS supplier, pf.name AS point_from, s.type, pt.date, spt.name AS pointTo, prp.name AS prp_name
            FROM '.DB_PARTNER_TRANSACTIONS.' pt
            JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = pt.point
            LEFT JOIN '.DB_SUPPLIES.' AS s ON s.id = pt.proccess_id
            LEFT JOIN '.DB_SUPPLIERS.' AS sup ON sup.id = s.supplier
            LEFT JOIN '.DB_PARTNER_POINTS.' AS pf ON pf.id = s.pointFrom
            LEFT JOIN '.DB_PARTNER_POINTS.' AS spt ON spt.id = s.pointTo
            LEFT JOIN '.DB_PRODUCTIONS.' AS pr ON pr.id = pt.proccess_id AND pr.point != pr.point_to
            LEFT JOIN '.DB_PARTNER_POINTS.' AS prp ON prp.id = pr.point_to AND pt.proccess = 5
            WHERE pt.item = '.$item.' AND pt.partner = '.$userToken['id'].' AND pt.date BETWEEN '.$from.' AND '.$to.' AND pt.dyd BETWEEN '.$from_dyd.' AND '.$to_dyd.$point.'
            ORDER BY pt.date DESC, pt.id DESC
        ');

        $result = [];

        $process_name = ["Поставка","Перемещение","Инвентаризация","Списание","Продажа","Производство"];

        while($row = DB::getRow($details)){

            //Если процесс - поставка или перемещение, то указываем поставщика
            $supplier = ($row['proccess'] == 0) ? $row['supplier'].' -> ' :
                                                (($row['proccess'] == 1) ? $row['point_from'].' -> ' : '');

            $point_to = ($row['proccess'] == 1) ? $row['pointTo'] :  $row['point_to'];

            //Если при производстве была указана точка, на которую производим, то выводим её наименование
            if($row['prp_name'] != null)
                $point_to .= ' -> '.$row['prp_name'];

            //По умолчанию прочерк
            $costs = $receipts = '-';

            if($row['count'] > 0)
                $receipts = number_format($row['count'], 3, ',', ' ').' '.$untils;
            else
                $costs = number_format($row['count'], 3, ',', ' ').' '.$untils;

            if($tab === false || $tab == $row['proccess']){
                $fields = array(  'id' => $row['id'],
                                    'supplier' => $row['supplier'],
                                    'process_name' => $process_name[$row['proccess']].(($row['count'] < 0 && $row['proccess'] == 5) ? ' (Расход)' : ''),
                                    'process_id' => $row['proccess_id'],
                                    'process' => $row['proccess'],
                                    'point' => $supplier.$point_to,
                                    'balance_begin' => number_format($balance_end - $row['count'], 2, ',', ' ').' '.$untils,
                                    'average_price_begin' => number_format($row['average_price_begin'], 2, ',', ' ').' '.CURRENCY,
                                    'receipts' => $receipts,
                                    'costs' => $costs,
                                    'balance_end' => number_format($balance_end, 2, ',', ' ').' '.$untils,
                                    'average_price_end' => number_format($row['average_price_end'], 2, ',', ' ').' '.CURRENCY,
                                    'date' => date('d-m-Y H:i', $row['date']));

                array_unshift($result, $fields);
            }

            $balance_end = $balance_end - $row['count'];

        }

        response('success', $result, 7);

    break;

    case 'receipts':
    
        if(!$item = DB::escape($_REQUEST['item']))
            response('error', 'Не передан ID товара.', 1);

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND pt.point = '.$point;

        $costs = DB::query('SELECT SUM(IF((pt.proccess = 0 AND pt.count > 0), pt.count, 0)) AS supply_count, SUM(IF((pt.proccess = 1 AND pt.count > 0), pt.count, 0)) AS moving_count, SUM(IF((pt.proccess = 2 AND pt.count > 0), pt.count, 0)) AS inventory_count, SUM(IF((pt.proccess = 5 AND pt.count > 0), pt.count, 0)) AS production_count, i.untils,
                                            SUM(IF((pt.proccess = 0 AND pt.total > 0), pt.total, 0)) AS supply_total, SUM(IF((pt.proccess = 1 AND pt.total > 0), pt.total, 0)) AS moving_total, SUM(IF((pt.proccess = 2 AND pt.total > 0), pt.total, 0)) AS inventory_total, SUM(IF((pt.proccess = 5 AND pt.total > 0), pt.total, 0)) AS production_total
                                    FROM '.DB_PARTNER_TRANSACTIONS.' pt
                                    JOIN '.DB_ITEMS.' AS i ON i.id = pt.item
                                    WHERE pt.item = '.$item.' AND pt.date >= '.$from.' AND pt.date < '.$to.' AND pt.partner = '.$userToken['id'].$point);

        $costs = DB::getRow($costs);

        $result = array(

            'moving' => array(  'name' => 'Перемещения',
                                'count' => number_format($costs['moving_count'], 3, ',', ' ').' '.$costs['untils'],
                                'total' => number_format($costs['moving_total'], 2, ',', ' ').' '.CURRENCY),
            'inventory' => array(   'name' => 'Инвентаризации',
                                    'count' => number_format($costs['inventory_count'], 3, ',', ' ').' '.$costs['untils'],
                                    'total' => number_format($costs['inventory_total'], 2, ',', ' ').' '.CURRENCY),
            'supply' => array(   'name' => 'Поставки',
                                'count' => number_format($costs['supply_count'], 3, ',', ' ').' '.$costs['untils'],
                                'total' => number_format($costs['supply_total'], 2, ',', ' ').' '.CURRENCY),
            'productions' => array( 'name' => 'Производство',
                                    'count' => number_format($costs['production_count'], 3, ',', ' ').' '.$costs['untils'],
                                    'total' => number_format($costs['production_total'], 2, ',', ' ').' '.CURRENCY),

        );

        response('success', $result, 7);

    break;

}