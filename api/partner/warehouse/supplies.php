<?php

use Support\Pages;
use Support\DB;

include ROOT . 'api/partner/tokenCheck.php';
require ROOT . 'api/classes/SupplyClass.php';
require ROOT . 'api/classes/MovingClass.php';
require_once ROOT . 'api/classes/TableHead.php';
include ROOT . 'api/lib/functions.php';
include 'check_inventory.php';
require ROOT . 'api/classes/OrderClass.php';

function DeleteSupply($supply, $type, $dyd)
{



    DB::delete(DB_SUPPLIES, 'id = ' . $supply);

    DB::delete(DB_PARTNER_TRANSACTIONS, 'proccess = ' . $type . ' AND proccess_id = ' . $supply . ' AND dyd = ' . $dyd);
}

if (!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT ' . Pages::$limit;

switch ($action) {

    case 'create':

        $type = DB::escape($_REQUEST['type']);

        //Дата обязательна
        if (!$date = DB::escape($_REQUEST['date']))
            response('error', array('msg' => 'Не передана дата и время поставки.'), '346');

        if ($date > time())
            response('error', 'Нельзя выбрать дату будущим числом.', 422);

        //Указываем точку, куда будут приходить товары
        if (!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Не передан ID точки, куда будет осуществляться поставка.'), '351');

        inventoryCheck($point, $date);

        $fields = array('date' => $date);

        if ($comment = DB::escape($_REQUEST['comment']))
            $fields['comment'] = $comment;


        if ($in_number = DB::escape($_REQUEST['in_number']))
            $fields['in_number'] = $in_number;

        //Если поставка
        if (!$type || $type == 0) {

            $supplier = DB::escape($_REQUEST['supplier']);

            $payer = DB::escape($_REQUEST['payer']);

            if (!$supplier) {
                response('error', array('msg' => 'Не передан ID поставщика.'), '347');
            }

            if ($supplier) {
                $supplierData = DB::select('id', DB_SUPPLIERS, 'id = ' . $supplier . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

                if (DB::getRecordCount($supplierData) == 0)
                    response('error', array('msg' => 'Поставщик с таким ID не найден.'), '346');

                $fields['supplier'] = $supplier;
            }

            if ($payer) {
                $payerData = DB::select('id', DB_SUPPLIERS, 'id = ' . $payer . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

                if (DB::getRecordCount($payerData) == 0)
                    response('error', array('msg' => 'Платильщик с таким ID не найден.'), '346');

                $fields['payer'] = $payer;
            }

            $fields['type'] = 0;
        }

        //Если перемещение
        if ($type == 1) {

            if (!$supplier = DB::escape($_REQUEST['supplier']))
                response('error', array('msg' => 'Не передан ID склада.'), '348');

            inventoryCheck($supplier, $date);

            $warehouseData = DB::select('id', DB_PARTNER_POINTS, 'id = ' . $supplier . ' AND partner = ' . $userToken['id']);

            if (DB::getRecordCount($warehouseData) == 0)
                response('error', array('msg' => 'Склад с таким ID не найден.'), '349');

            $fields['pointFrom'] = $supplier;
            $fields['type'] = 1;
        }

        //$pointData = DB::select('id', DB_PARTNER_POINTS, 'id = '.$point.' AND partner = '.$userToken['id']);
        $pointData = DB::query('
            SELECT pp.id, pp.partner
            FROM ' . DB_PARTNER_POINTS . ' pp
            JOIN ' . DB_PARTNER . ' p ON p.id = pp.partner
            WHERE pp.id = ' . $point . ' AND (p.id = ' . $userToken['id'] . ' OR p.parent = ' . $userToken['id'] . ')
        ');

        if (DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Склад с таким ID не найден.'), '349');

        $pointData = DB::getRow($pointData);

        //Если перемещение с точки саму на себя
        if ($type == 1 && $supplier == $point)
            response('error', array('msg' => 'Вы не можете осуществить поставку на тот же склад, с которого производится поставка.'), '352');

        $fields['pointTo'] = $point;
        $fields['created'] = time();
        $fields['partner'] = $userToken['id'];

        if ($userToken['employee'])
            $fields['employee'] = $userToken['employee'];

        if (!$items = DB::escape($_REQUEST['items']))
            response('error', array('msg' => 'Заполните таблицу с ингредиентами.'), '353');

        if (!$type || $type == 0) {
            $supply = new SupplyClass(false, $fields, $items, $userToken['id'], $point, $date);
            $supply->create();

            response('success', 'Поставка добавлена.', 7);
        }

        if ($type == 1) {

            $moving = new MovingClass(false, $fields, $items, $pointData['partner'], $point, $date);
            $moving->create(); //Отправляем позиции на склад-получатель

            /* if($pointData['partner'] != $userToken['id']){

                foreach($moving->items as $item){
                    if(!$item_global)
                        $item_global = $item['id'];
                    else
                        $item_global .= ','.$item['id'];
                }

                if($item_global)
                    DB::query('UPDATE '.DB_ITEMS.' SET partner = NULL WHERE FIND_IN_SET(id, "'.$item_global.'")');

            } */

            $removal_items = new MovingClass(false, $fields, $items, $userToken['id'], $supplier, $date);
            $removal_items->proccess_id = $moving->proccess_id;
            $removal_items->remove(); //Списываем позиции со склада-отправителя

            response('success', 'Перемещение создано.', 7);
        }

        break;

    case 'get':

        $result = [];

        if ($search = DB::escape($_REQUEST['search']))
            $search = ' AND (s.comment LIKE "%' . $search . '%" OR s.in_number LIKE "%' . $search . '%" OR  i.name LIKE "%' . $search . '%" OR ic.name LIKE "%' . $search . '%" OR sp.name LIKE "%' . $search . '%" OR sp1.name LIKE "%' . $search . '%" OR e.name LIKE "%' . $search . '%")';

        if ($supplier = DB::escape($_REQUEST['supplier']))
            $supplier = ' AND sp.id = ' . $supplier;

        if ($payer = DB::escape($_REQUEST['payer']))
            $payer = ' AND sp1.id = ' . $payer;

        if ($point_to = DB::escape($_REQUEST['point_to']))
            $point_to = ' AND s.pointTo = ' . $point_to;

        if ($date_from = DB::escape($_REQUEST['date_from']))
            $date_from = ' AND s.date >= ' . strtotime(date('d-m-Y', $date_from));

        if ($date_to = DB::escape($_REQUEST['date_to']))
            $date_to = ' AND s.date < ' . (strtotime(date('d-m-Y', $date_to)) + 86400);

        $pages = DB::query('SELECT COUNT(tt.id) AS count
                                    FROM (SELECT t.id
                                    FROM (  SELECT s.id
                                            FROM ' . DB_SUPPLIES . ' s
                                            JOIN ' . DB_SUPPLY_ITEMS . ' si ON si.supply = s.id
                                            JOIN ' . DB_ITEMS . ' i ON i.id = si.item
                                            JOIN ' . DB_SUPPLIERS . ' sp ON sp.id = s.supplier
                                            LEFT JOIN ' . DB_EMPLOYEES . ' e ON e.id = s.employee
                                            LEFT JOIN ' . DB_SUPPLIERS . ' sp1 ON sp1.id = s.payer
                                            LEFT JOIN ' . DB_ITEMS_CATEGORY . ' ic ON ic.id = i.category
                                            WHERE s.type = 0 AND s.partner = ' . $userToken['id'] . $search . $supplier . $payer . $point . $date_from . $date_to . $point_to . ') t
                                    GROUP BY t.id) tt');

        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else
            $total_pages = 0;

        $total_sum = DB::getRow(DB::query('SELECT  SUM(tt.sum) AS sum
          FROM (SELECT t.sum
          FROM (  SELECT s.id, s.sum
                  FROM ' . DB_SUPPLIES . ' s
                  JOIN ' . DB_SUPPLY_ITEMS . ' si ON si.supply = s.id
                  JOIN ' . DB_ITEMS . ' i ON i.id = si.item
                  JOIN ' . DB_SUPPLIERS . ' sp ON sp.id = s.supplier
                  LEFT JOIN ' . DB_EMPLOYEES . ' e ON e.id = s.employee
                  LEFT JOIN ' . DB_SUPPLIERS . ' sp1 ON sp1.id = s.payer
                  LEFT JOIN ' . DB_ITEMS_CATEGORY . ' ic ON ic.id = i.category
                  WHERE s.type = 0 AND s.partner = ' . $userToken['id'] . $search . $supplier . $payer . $point . $date_from . $date_to . $point_to . ') t GROUP BY t.id) tt'));


        $pageData = array(
            'current_page' => (int)Pages::$page,
            'rows_count' => $pages['count'],
            'sum' => isset($total_sum['sum']) ? (int) $total_sum['sum'] : 0,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT
        );

        $sorting = Order::supplies(Pages::$field, Pages::$order);

        $supplies = DB::query(
            '
            SELECT t.id, t.date, t.employee, t.in_number,  t.supplier, t.payer, t.point, t.items_count, t.comment, t.sum, t.status, GROUP_CONCAT(DISTINCT t.item SEPARATOR ", ") AS items, GROUP_CONCAT(DISTINCT t.category SEPARATOR ", ") AS categories,
        (SELECT inv.id FROM ' . DB_INVENTORY . ' inv WHERE inv.point = t.pointTo AND inv.status = 1 AND inv.date_end >= t.date LIMIT 1) AS close
            FROM (  SELECT s.id, s.date, s.in_number, e.name AS employee, sp.name AS supplier, sp1.name AS payer, s.pointTo, p.name AS point, s.items_count, s.comment, s.sum, s.status, i.name AS item, ic.name AS category
                    FROM ' . DB_SUPPLIES . ' s
                    JOIN ' . DB_SUPPLY_ITEMS . ' si ON si.supply = s.id
                    JOIN ' . DB_ITEMS . ' i ON i.id = si.item
                    LEFT JOIN ' . DB_ITEMS_CATEGORY . ' ic ON ic.id = i.category
                    JOIN ' . DB_PARTNER_POINTS . ' p ON s.pointTo = p.id
                    JOIN ' . DB_SUPPLIERS . ' sp ON sp.id = s.supplier
                    LEFT JOIN ' . DB_SUPPLIERS . ' sp1 ON sp1.id = s.payer
                    LEFT JOIN ' . DB_EMPLOYEES . ' e ON e.id = s.employee
                    WHERE s.type = 0 AND s.partner = ' . $userToken['id'] . $search . $supplier . $payer . $point . $date_from . $date_to . $point_to . '
            ) t
            GROUP BY t.id
            ' . $sorting . '
            ' . $limit_str
        );

        if ($export) {

            require ROOT . 'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::warehouse_supplies(), 'Поставки');

            $i = 1;

            while ($row = DB::getRow($supplies)) {

                $row['payer'] = ($row['payer'] == null) ? '' : $row['payer'];

                $f_class->data[] = array(
                    'i' => $i,
                    'date' => UnixToDateRus((int)$row['date'], true),
                    'point' => $row['point'],
                    'in_number' => $row['in_number'],
                    'supplier' => $row['supplier'],
                    'payer' => $row['payer'],
                    'sum' => round($row['sum'], 2),
                    'comment' => $row['comment']
                );

                $i++;
            }

            $f_class->create();
        }

        while ($row = DB::getRow($supplies)) {

            if ($row['date'] <= time() && $row['status'] == 0)
                $row['status'] = '1';

            $row['sum'] = round($row['sum'], 2);
            $row['close'] = ($row['close'] == null) ? false : true;

            $row['employee'] = ($row['employee'] == null) ? 'Администратор' : $row['employee'];

            $result[] = $row;
        }




        $pageData['from'] = $date_from;
        $pageData['to'] = $date_to;

        response('success', $result, '7', $pageData);

        break;

    case 'moving':

        $result = [];

        if ($search = DB::escape($_REQUEST['search']))
            $search = ' AND (s.comment LIKE "%' . $search . '%" OR i.name LIKE "%' . $search . '%" OR emp.name LIKE "%' . $search . '%")';

        if ($supplier = DB::escape($_REQUEST['supplier']))
            $supplier = ' AND sp.id = ' . $supplier;

        if ($point_to = DB::escape($_REQUEST['point_to']))
            $point_to = ' AND (s.pointTo = ' . $point_to . ')';

        if ($point_from = DB::escape($_REQUEST['point_from']))
            $point_from = ' AND (s.pointFrom = ' . $point_from . ')';



        if ($date_from = DB::escape($_REQUEST['date_from']))
            $date_from = ' AND s.date >= ' . strtotime(date('d-m-Y', $date_from));

        if ($date_to = DB::escape($_REQUEST['date_to']))
            $date_to = ' AND s.date < ' . (strtotime(date('d-m-Y', $date_to)) + 86400);

        $pages = DB::query('SELECT COUNT(t.id) AS count
                                    FROM (
                                        SELECT s.id
                                        FROM ' . DB_SUPPLIES . ' s
                                        LEFT JOIN ' . DB_SUPPLY_ITEMS . ' si ON si.supply = s.id
                                        LEFT JOIN ' . DB_ITEMS . ' i ON i.id = si.item
                                        JOIN ' . DB_PARTNER_POINTS . ' AS p ON s.pointTo = p.id
                                        JOIN ' . DB_PARTNER_POINTS . ' AS pf ON s.pointFrom = pf.id 
                                        LEFT JOIN ' . DB_EMPLOYEES . ' AS emp ON emp.id = s.employee
                                        WHERE s.type = 1 AND (p.partner = ' . $userToken['id'] . ' OR pf.partner = ' . $userToken['id'] . ') AND (s.partner = ' . $userToken['parent'] . ' OR s.partner = ' . $userToken['id'] . ')' . $search . $supplier . $point_to . $date_from . $date_to . $point_from . '
                                        GROUP BY s.id) t');
        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else
            $total_pages = 0;

        $total_sum = DB::getRow(DB::query('SELECT SUM(t.sum) AS sum
        FROM (
            SELECT s.sum
            FROM ' . DB_SUPPLIES . ' s
            LEFT JOIN ' . DB_SUPPLY_ITEMS . ' si ON si.supply = s.id
            LEFT JOIN ' . DB_ITEMS . ' i ON i.id = si.item
            JOIN ' . DB_PARTNER_POINTS . ' AS p ON s.pointTo = p.id
            JOIN ' . DB_PARTNER_POINTS . ' AS pf ON s.pointFrom = pf.id 
            LEFT JOIN ' . DB_EMPLOYEES . ' AS emp ON emp.id = s.employee
            WHERE s.type = 1 AND (p.partner = ' . $userToken['id'] . ' OR pf.partner = ' . $userToken['id'] . ') AND (s.partner = ' . $userToken['parent'] . ' OR s.partner = ' . $userToken['id'] . ')' . $search . $supplier . $point_to . $date_from . $date_to . $point_from . ' 
            GROUP BY s.id
            ) t'));

        $pageData = array(
            'current_page' => (int)Pages::$page,
            'rows_count' => $pages['count'],
            'sum' => isset($total_sum['sum']) ? (int) $total_sum['sum'] : 0,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT
        );

        $sorting = Order::moving(Pages::$field, Pages::$order);

        $supplies = DB::query('SELECT s.id, s.partner, s.date, emp.name AS employee, pf.name AS pointFrom, p.name AS pointTo, s.items_count, s.comment, s.status, s.sum,
                                GROUP_CONCAT(DISTINCT i.name SEPARATOR ", ") AS items,
                                (SELECT id FROM ' . DB_PRODUCTIONS . ' WHERE moving = s.id LIMIT 1) AS production,
                                (SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ", ") FROM ' . DB_ITEMS_CATEGORY . ' c
                                                            JOIN ' . DB_ITEMS . ' AS i ON i.category = c.id
                                                            JOIN ' . DB_SUPPLY_ITEMS . ' AS si ON si.item = i.id
                                                            WHERE si.supply = s.id) AS categories,
                                (SELECT inv.id FROM ' . DB_INVENTORY . ' inv WHERE (inv.point = s.pointTo OR inv.point = s.pointFrom) AND inv.status = 1 AND inv.date_end >= s.date LIMIT 1) AS close
                                    FROM ' . DB_SUPPLIES . ' s
                                    LEFT JOIN ' . DB_SUPPLY_ITEMS . ' si ON si.supply = s.id
                                    LEFT JOIN ' . DB_ITEMS . ' i ON i.id = si.item
                                    JOIN ' . DB_PARTNER_POINTS . ' AS p ON s.pointTo = p.id
                                    JOIN ' . DB_PARTNER_POINTS . ' AS pf ON s.pointFrom = pf.id 
                                    LEFT JOIN ' . DB_EMPLOYEES . ' AS emp ON emp.id = s.employee
                                    WHERE s.type = 1 AND (p.partner = ' . $userToken['id'] . ' OR pf.partner = ' . $userToken['id'] . ') AND (s.partner = ' . $userToken['parent'] . ' OR s.partner = ' . $userToken['id'] . ')' . $search . $supplier . $point_to . $date_from . $date_to . $point_from . '
                                    GROUP BY s.id
                                    ' . $sorting . '
                                    ' . $limit_str);

        if ($export) {

            require ROOT . 'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::warehouse_moving(), 'Перемещения');

            $i = 1;

            while ($row = DB::getRow($supplies)) {

                $f_class->data[] = array(
                    'i' => $i,
                    'date' => UnixToDateRus((int)$row['date'], true),
                    'point' => $row['pointTo'],
                    'supplier' => $row['pointFrom'],
                    'sum' => round($row['sum'], 2),
                    'comment' => $row['comment']
                );

                $i++;
            }

            $f_class->create();
        }

        while ($row = DB::getRow($supplies)) {

            if ($row['date'] <= time() && $row['status'] == 0)
                $row['status'] = '1';

            $row['close'] = ($row['close'] == null) ? false : true;
            if (!is_null($row['production'])) $row['close'] = true;
            $admin = ($userToken['id'] != $row['partner']) ? true : false;

            $result[] = array(
                'id' => $row['id'],
                'admin' => $admin,
                'date' => $row['date'],
                'items' => (string)$row['items'],
                'categories' => (string)$row['categories'],
                'employee' => ($row['employee'] == null) ? 'Администратор' : $row['employee'],
                'comment' => $row['comment'],
                'sum' => round($row['sum'], 2),
                'pointFrom' => $row['pointFrom'],
                'pointTo' => $row['pointTo'],
                'status' => $row['status'],
                'close' => $row['close']
            );
        }

        response('success', $result, '7', $pageData);

        break;

    case 'info':

        if (!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        $type = (DB::escape($_REQUEST['type'])) ? 1 : 0;

        $supplyData = DB::select('id', DB_SUPPLIES, 'id = ' . $supply . ' AND partner = ' . $userToken['id'] . ' AND type = ' . $type);

        if (DB::getRecordCount($supplyData) == 0) {
            if ($type == 0)
                response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');
            else
                response('error', array('msg' => 'Перемещение с таким ID не найдено.'), '360');
        }

        if (!$type || $type == 0) {

            $supplyData = DB::query('SELECT s.id, s.date, s.in_number, s.status, s.comment, s.items_count, s.sum, s.created, sup.id AS supplierId, sup.name AS supplierName,
                                p.id AS pointId, p.name AS pointName, sup1.id AS payerId, sup1.name AS payerName
                                            FROM ' . DB_SUPPLIES . ' s
                                            JOIN ' . DB_SUPPLIERS . ' AS sup ON sup.id = s.supplier
                                            LEFT JOIN ' . DB_SUPPLIERS . ' AS sup1 ON sup1.id = s.payer
                                            JOIN ' . DB_PARTNER_POINTS . ' AS p ON p.id = s.pointTo
                                            WHERE s.id = ' . $supply);

            $row = DB::getRow($supplyData);

            $result = array(
                'id' => $row['id'],
                'date' => $row['date'],
                'status' => $row['status'],
                'comment' => $row['comment'],
                'in_number' => $row['in_number'],
                'items_count' => $row['items_count'],
                'sum' => $row['sum'],
                'created' => $row['created'],
                'supplier' => array(
                    'id' => $row['supplierId'],
                    'name' => $row['supplierName']
                ),
                'payer' => array(
                    'id' => $row['payerId'],
                    'name' => $row['payerName']
                ),
                'point' => array(
                    'id' => $row['pointId'],
                    'name' => $row['pointName']
                )
            );
        }

        if ($type == 1) {

            $supplyData = DB::query('SELECT s.id, s.date, s.status,  s.in_number, s.comment, s.items_count, s.sum, s.created, pf.id AS pfid, pf.name AS pfname, pt.id AS pointId, pt.name AS pointName
                                            FROM ' . DB_SUPPLIES . ' s
                                            JOIN ' . DB_PARTNER_POINTS . ' AS pf ON pf.id = s.pointFrom
                                            JOIN ' . DB_PARTNER_POINTS . ' AS pt ON pt.id = s.pointTo
                                            WHERE s.id = ' . $supply);

            $row = DB::getRow($supplyData);

            $result = array(
                'id' => $row['id'],
                'date' => $row['date'],
                'status' => $row['status'],
                'comment' => $row['comment'],
                'in_number' => $row['in_number'],
                'items_count' => $row['items_count'],
                'sum' => $row['sum'],
                'created' => $row['created'],
                'supplier' => array(
                    'id' => $row['pfid'],
                    'name' => $row['pfname']
                ),
                'payer' => array(
                    'id' => $row['payerId'],
                    'name' => $row['payerName']
                ),
                'point' => array(
                    'id' => $row['pointId'],
                    'name' => $row['pointName']
                )
            );
        }

        $itemsStr = DB::query('SELECT si.id, si.count, si.price, si.sum, si.tax, si.total, i.id AS itemId, i.name AS itemName, i.untils, i.conversion_item_id, pi.count AS accessCount
                                    FROM ' . DB_SUPPLY_ITEMS . ' si
                                    JOIN ' . DB_ITEMS . ' AS i ON i.id = si.item
                                    LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = si.item AND pi.point = ' . $result['supplier']['id'] . '
                                    WHERE si.supply = ' . $supply);

        $items = [];

        $total = array(
            'count' => 0,
            'total' => 0
        );




        $ids = [];
        while ($row = DB::getRow($itemsStr)) {

            $total['count'] += $row['count'];
            $total['total'] += $row['total'];

            if ($row['conversion_item_id']) $ids[] = $row['conversion_item_id'];

            $items[] = array(
                'id' => $row['itemId'],
                'position' => $row['id'],
                'conversion_item_id' => $row['conversion_item_id'],
                'count' => $row['count'],
                'accessCount' => ($type) ? round($row['accessCount'], 2) : '',
                'price' => $row['price'],
                'sum' => $row['sum'],
                'untils' => $row['untils'],
                'tax' => $row['tax'],
                'total' => $row['total'],
                'name' => $row['itemName']
            );
        }


        if (count($ids) > 0) {
            $data_conversion_items = DB::makeArray(DB::select("*", DB_ITEMS, "id IN (" . implode(',', $ids) . ")"));


            if (count($data_conversion_items) > 0) {
                foreach ($items as &$row) {
                    foreach ($data_conversion_items as $conversion_item)
                        if ($row['conversion_item_id'] == $conversion_item['id']) {
                            $row['conversion_item'] = $conversion_item;
                        }
                }
            }
        }


        $result['items'] = $items;

        response('success', $result, '7', array('total' => $total));

        break;

    case 'details':

        $element_count = 10;

        if (!$page || $page == 1) {
            $page = '1';
            $limit = '0,' . $element_count;
        } else {
            $begin = $element_count * $page - $element_count;
            $limit = $begin . ',' . $element_count;
        }

        if (!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        $supplyData = DB::select('id', DB_SUPPLIES, 'id = ' . $supply . ' AND (partner = ' . $userToken['id'] . ' OR partner = ' . $userToken['parent'] . ')');

        if (DB::getRecordCount($supplyData) == 0)
            response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');

        $pages = DB::query('SELECT COUNT(id) as count FROM ' . DB_SUPPLY_ITEMS . ' WHERE supply = ' . $supply);
        $pages = DB::getRow($pages);

        if ($pages['count'] != null) {
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        } else
            $total_pages = 0;

        $details = DB::query('SELECT si.id, i.name, si.count, si.total, si.price
                                    FROM ' . DB_SUPPLY_ITEMS . ' si
                                    LEFT JOIN ' . DB_ITEMS . ' AS i ON i.id = si.item
                                    WHERE si.supply = ' . $supply . '
                                    ORDER BY i.name
                                    LIMIT ' . Pages::$limit);

        $pageData = array(
            'current_page' => (int)Pages::$page,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT
        );

        while ($row = DB::getRow($details)) {
            $row['total'] = round($row['total'], 2);
            $row['price'] = round($row['price'], 2);
            $result[] = $row;
        }

        response('success', $result, '7', $pageData);

        break;


    case 'edit':

        if (!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        $supplyData = DB::select('id, employee, pointTo, pointFrom, type, date', DB_SUPPLIES, 'id = ' . $supply . ' AND partner = ' . $userToken['id']);

        $type = DB::escape($_REQUEST['type']);

        if (DB::getRecordCount($supplyData) == 0)
            response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');

        $supplyData = DB::getRow($supplyData);
        $dyd = date('Ym', $supplyData['date']);
        $partner_transactions = DB::select('id', DB_PARTNER_TRANSACTIONS, "proccess_id = {$supplyData['id']} AND proccess = {$supplyData['type']} AND dyd = {$dyd}");
        $partner_transactions = DB::getRecordCount($partner_transactions) ? DB::getColumn('id', $partner_transactions) : [];
        $partner_transactions = implode(',', $partner_transactions);

        //Проверяем, был ли закрыт период для точки, на которую поставляется
        inventoryCheck($supplyData['pointTo'], $supplyData['date']);
        //Если перемещение, то проверяем был ли закрыт период для точки, с которой поставляется
        if ($supplyData['type'])
            inventoryCheck($supplyData['pointFrom'], $supplyData['date']);

        //Дата обязательна
        if (!$date = DB::escape($_REQUEST['date']))
            response('error', array('msg' => 'Не передана дата и время поставки.'), '346');

        if ($date > time())
            response('error', 'Нельзя выбрать дату будущим числом.', 422);

        //Указываем точку, куда будут приходить товары
        if (!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Не передан ID точки, куда будет осуществляться поставка.'), '351');

        $fields = array(
            'date' => $date,
            'employee' => $supplyData['employee']
        );

        if ($comment = DB::escape($_REQUEST['comment']))
            $fields['comment'] = $comment;

        if ($in_number = DB::escape($_REQUEST['in_number']))
            $fields['in_number'] = $in_number;


        //Если поставка
        if (!$type || $type == 0) {

            $supplier = DB::escape($_REQUEST['supplier']);

            $payer = DB::escape($_REQUEST['payer']);

            if (!$supplier) {
                response('error', array('msg' => 'Не передан ID поставщика.'), '347');
            }

            if ($supplier) {
                $supplierData = DB::select('id', DB_SUPPLIERS, 'id = ' . $supplier . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

                if (DB::getRecordCount($supplierData) == 0)
                    response('error', array('msg' => 'Поставщик с таким ID не найден.'), '346');

                $fields['supplier'] = $supplier;
            }

            if ($payer) {
                $payerData = DB::select('id', DB_SUPPLIERS, 'id = ' . $payer . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

                if (DB::getRecordCount($payerData) == 0)
                    response('error', array('msg' => 'Платильщик с таким ID не найден.'), '346');

                $fields['payer'] = $payer;
            }

            $fields['type'] = 0;
        }

        //Если перемещение
        if ($type == 1) {

            if (!$supplier = DB::escape($_REQUEST['supplier']))
                response('error', array('msg' => 'Не передан ID склада.'), '348');

            $warehouseData = DB::select('id', DB_PARTNER_POINTS, 'id = ' . $supplier . ' AND partner = ' . $userToken['id']);

            if (DB::getRecordCount($warehouseData) == 0)
                response('error', array('msg' => 'Склад с таким ID не найден.'), '349');

            $fields['pointFrom'] = $supplier;
            $fields['type'] = 1;
        }

        $pointData = DB::query('
            SELECT pp.id, pp.partner
            FROM ' . DB_PARTNER_POINTS . ' pp
            JOIN ' . DB_PARTNER . ' p ON p.id = pp.partner
            WHERE pp.id = ' . $point . ' AND (p.id = ' . $userToken['id'] . ' OR p.parent = ' . $userToken['id'] . ')
        ');

        if (DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Склад с таким ID не найден.'), '349');

        $pointData = DB::getRow($pointData);

        //Если перемещение с точки саму на себя
        if ($type == 1 && $supplier == $point)
            response('error', array('msg' => 'Вы не можете осуществить поставку на тот же склад, с которого производится поставка.'), '352');

        $fields['pointTo'] = $point;
        $fields['created'] = time();
        $fields['partner'] = $userToken['id'];

        if (!$items = DB::escape($_REQUEST['items']))
            response('error', array('msg' => 'Заполните таблицу с ингредиентами.'), '353');

        if (!$type || $type == 0) {

            $supply = new SupplyClass(false, $fields, $items, $userToken['id'], $point, $date);
            $supply->edit($supplyData['id']);

            if (!empty($partner_transactions))
                DB::delete(DB_PARTNER_TRANSACTIONS, "id IN ($partner_transactions) AND dyd = {$dyd}");

            response('success', 'Поставка обновлена.', 7);
        }

        if ($type == 1) {

            $moving = new MovingClass(false, $fields, $items, $pointData['partner'], $point, $date);
            $moving->edit($supplyData['id']); //Отправляем позиции на склад-получатель

            $removal_items = new MovingClass(false, $fields, $items, $userToken['id'], $supplier, $date);
            $removal_items->proccess_id = $moving->proccess_id;
            $removal_items->remove(); //Списываем позиции со склада-отправителя

            if (!empty($partner_transactions))
                DB::delete(DB_PARTNER_TRANSACTIONS, "id IN ($partner_transactions) AND dyd = {$dyd}");

            response('success', 'Перемещение обновлено.', 7);
        }

        break;

    case 'delete':

        if (!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        $supplyData = DB::select('id, pointTo, pointFrom, type, date', DB_SUPPLIES, 'id = ' . $supply . ' AND partner = ' . $userToken['id']);

        $type = DB::escape($_REQUEST['type']);

        if (DB::getRecordCount($supplyData) == 0)
            response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');

        $supplyData = DB::getRow($supplyData);
        $dyd = date('Ym', $supplyData['date']);

        //Проверяем, был ли закрыт период для точки, на которую поставляется
        inventoryCheck($supplyData['pointTo'], $supplyData['date']);
        //Если перемещение, то проверяем был ли закрыт период для точки, с которой поставляется
        if ($supplyData['type'])
            inventoryCheck($supplyData['pointFrom'], $supplyData['date']);

        if (!$type || $type == 0) {
            DeleteSupply($supply, 0, $dyd);
            response('success', 'Поставка удалена.', 7);
        }
        if ($type == 1) {
            DeleteSupply($supply, 1, $dyd);
            response('success', 'Перемещение удалено.', 7);
        }

        break;

    case 'items':

        if (!$point = DB::escape($_REQUEST['point']))
            response('error', 'Не передан ID точки', 2);

        $result = [];

        $archive = '
            AND i.id NOT IN (
                SELECT product_id
                FROM ' . DB_ARCHIVE . '
                WHERE model = "item" AND partner_id = ' . $userToken['id'] . '
            )';

        $items = DB::query('SELECT i.id, i.name, pi.count, pi.price, i.untils, i.conversion_item_id
                                    FROM ' . DB_ITEMS . ' i
                                    JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = i.id
                                    WHERE pi.point = ' . $point . ' AND pi.partner = ' . $userToken['id'] . ' AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL) AND i.del = 0' . $archive . '
                                    GROUP BY i.id
                                    ORDER BY i.name');


        $ids = [];
        while ($row = DB::getRow($items)) {

            $row['count'] = round($row['count'], 2);
            $row['price'] = round($row['price'], 2);


            if ($row['conversion_item_id']) {
                $ids[] = $row['conversion_item_id'];
            }

            $result[] = $row;
        }




        if (count($ids) > 0) {
            $data_conversion_items = DB::makeArray(DB::select("*", DB_ITEMS, "id IN (" . implode(',', $ids) . ")"));


            if (count($data_conversion_items) > 0) {
                foreach ($result as &$row) {
                    foreach ($data_conversion_items as $conversion_item)
                        if ($row['conversion_item_id'] == $conversion_item['id']) {
                            $row['conversion_item'] = $conversion_item;
                        }
                }
            }
        }

        response('success', $result, 7);

        break;
}