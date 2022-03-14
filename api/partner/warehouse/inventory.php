<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/InventoryClass.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'get':
        
        $result = [];

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND i.point = '.$point;

        $sorting = Order::inventory(Pages::$field, Pages::$order);

        $inventories = DB::query('SELECT i.id, p.name AS point, i.sum, i.status, i.date_begin, i.date_end, i.date_completed
                                        FROM '.DB_INVENTORY.' i
                                        JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = i.point
                                        WHERE i.partner = '.$userToken['id'].' AND ((i.date_end >= '.$from.' AND i.date_end < '.$to.') OR i.status = 0)'.$point.'
                                        '.$sorting.'
                                        LIMIT '.Pages::$limit);

        while($row = DB::getRow($inventories)){

            $row['sum'] = number_format($row['sum'], 2, ',', ' ').' '.CURRENCY;
            $result[] = $row;

        }

        $pages = DB::query('
            SELECT COUNT(i.id) AS count
            FROM '.DB_INVENTORY.' i
            JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = i.point
            WHERE i.partner = '.$userToken['id'].' AND ((i.date_end >= '.$from.' AND i.date_end < '.$to.') OR i.status = 0)'.$point
        );

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT);

        response('success', $result, 7, $pageData);

    break;

    case 'info':

        if(!$inventory = DB::escape($_REQUEST['inventory']))
            response('error', 'Выберите инвентаризацию', 1);

        $inv_class = new InventoryGet(false, $userToken['id'], $userToken['execute_inventory']);
        $inv_class->info($inventory);

    break;

    case 'update':

        $inv_class = new InventoryUpdate(false, $userToken['id']);
        $inv_class->update();

    break;

    case 'execute':

        $inv_class = new InventoryUpdate(false, $userToken['id']);
        $inv_class->execute();

    break;

    case 'date':

        $inv_class = new InventoryUpdate(false, $userToken['id']);
        $inv_class->date();

    break;

    case 'details':

        $result = [];

        $month_arr = [
            'января',
            'февраля',
            'марта',
            'апреля',
            'мая',
            'июня',
            'июля',
            'августа',
            'сентября',
            'октября',
            'ноября',
            'декабря'
        ];

        if(!$inventory = DB::escape($_REQUEST['inventory'])) 
            response('error', 'Не передан ID инвентаризации.', 1);

        if(!$item = DB::escape($_REQUEST['item']))
            response('error', 'Не передан ID ингредиента.', 1);

        $inventory_data = DB::select('id, point, date_begin, date_end, today', DB_INVENTORY, 'id = '.$inventory.' AND partner = '.$userToken['id'], '', 1);

        if(DB::getRecordCount($inventory_data) == 0)
            response('error', 'Инвентаризация не найдена.', 1);

        $inventory_data = DB::getRow($inventory_data);

        $from = $inventory_data['date_begin'];
        $to = $inventory_data['today'] ? time() : $inventory_data['date_end'];
        $point = $inventory_data['point'];

        $dydb = date('Ym', $from);
        $dyde = date('Ym', $to);

        $type = DB::escape($_REQUEST['type']);

        switch($type){

            //Поступления
            case 'income':

                $result = array(
                    ['title' => 'Поставки', 'list' => []],
                    ['title' => 'Перемещения между складами', 'list' => []],
                    ['title' => 'Производства', 'list' => []]
                );

                $query = DB::query('
                    SELECT s.id, i.name, si.count, si.sum, s.date, e.name AS employee, s.type, i.untils
                    FROM '.DB_SUPPLIES.' s
                    JOIN '.DB_SUPPLY_ITEMS.' si ON si.supply = s.id
                    JOIN '.DB_ITEMS.' i ON i.id = IF(si.conversion_item_id IS NULL, si.item, si.conversion_item_id)
                    LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = s.employee
                    WHERE s.pointTo = '.$point.' AND s.date BETWEEN '.$from.' AND '.$to.' AND IF(si.conversion_item_id IS NULL, si.item, si.conversion_item_id) = '.$item.'
                    ORDER BY s.date DESC
                ');

                while($row = DB::getRow($query)){

                    $month = date('m', $row['date']);
        
                    $date = date('d m Y, H:i', $row['date']);
        
                    $date = str_replace(
                        ' '.$month.' ',
                        ' '.$month_arr[$month - 1].' ',
                        $date
                    );

                    $case = $row['type'] ? 1 : 0;
        
                    $result[$case]['list'][] = array(
                        'name' => $row['name'],
                        'count' => number_format($row['count'], 3, ',', ' ').' '.$row['untils'],
                        'sum' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
                        'date' => $date,
                        'employee' => $row['employee'] == null ? 'Администратор' : $row['employee']
                    );
        
                }

                $query = DB::query('
                    SELECT i.name, pt.count, pt.total, pt.date, e.name AS employee, i.untils
                    FROM '.DB_PARTNER_TRANSACTIONS.' pt
                    JOIN '.DB_ITEMS.' i ON pt.item = i.id
                    JOIN '.DB_PRODUCTIONS.' p ON p.id = pt.proccess_id
                    LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = p.employee
                    WHERE pt.proccess = 5 AND pt.item = '.$item.' AND pt.point = '.$point.' AND pt.date BETWEEN '.$from.' AND '.$to.' AND pt.count > 0 AND pt.dyd BETWEEN '.$dydb.' AND '.$dyde.'
                    ORDER BY pt.date DESC
                ');

                while($row = DB::getRow($query)){

                    $month = date('m', $row['date']);
        
                    $date = date('d m Y, H:i', $row['date']);
        
                    $date = str_replace(
                        ' '.$month.' ',
                        ' '.$month_arr[$month - 1].' ',
                        $date
                    );
        
                    $result[2]['list'][] = array(
                        'name' => $row['name'],
                        'count' => number_format($row['count'], 3, ',', ' ').' '.$row['untils'],
                        'sum' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
                        'date' => $date
                    );

                }

            break;

            //Расход
            case 'consumption':

                $result = array(
                    ['title' => 'Продажи', 'list' => []],
                    ['title' => 'Производства', 'list' => []]
                );

                $query = DB::query('
                    SELECT i.name, pt.count, pt.total, pt.date, e.name AS employee, i.untils
                    FROM '.DB_PARTNER_TRANSACTIONS.' pt
                    JOIN '.DB_ITEMS.' i ON i.id = pt.item
                    LEFT JOIN '.DB_TRANSACTIONS.' tr ON tr.id = pt.proccess_id
                    LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = tr.employee
                    WHERE pt.proccess = 4 AND pt.point = '.$point.' AND pt.date BETWEEN '.$from.' AND '.$to.' AND pt.item = '.$item.' AND pt.dyd BETWEEN '.$dydb.' AND '.$dyde.'
                    ORDER BY pt.date DESC
                ');

                while($row = DB::getRow($query)){

                    $month = date('m', $row['date']);
        
                    $date = date('d m Y, H:i', $row['date']);
        
                    $date = str_replace(
                        ' '.$month.' ',
                        ' '.$month_arr[$month - 1].' ',
                        $date
                    );
        
                    $result[0]['list'][] = array(
                        'name' => $row['name'],
                        'count' => number_format($row['count'] * -1, 3, ',', ' ').' '.$row['untils'],
                        'sum' => number_format($row['total'] * -1, 2, ',', ' ').' '.CURRENCY,
                        'date' => $date,
                        'employee' => $row['employee'] == null ? 'Администратор' : $row['employee']
                    );
        
                }

                $query = DB::query('
                    SELECT i.name, pt.count, pt.total, pt.date, e.name AS employee, i.untils
                    FROM '.DB_PARTNER_TRANSACTIONS.' pt
                    JOIN '.DB_ITEMS.' i ON pt.item = i.id
                    JOIN '.DB_PRODUCTIONS.' p ON p.id = pt.proccess_id
                    LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = p.employee
                    WHERE pt.proccess = 5 AND pt.item = '.$item.' AND pt.point = '.$point.' AND pt.date BETWEEN '.$from.' AND '.$to.' AND pt.count < 0 AND pt.dyd BETWEEN '.$dydb.' AND '.$dyde.'
                    ORDER BY pt.date DESC
                ');

                while($row = DB::getRow($query)){
                    
                    $month = date('m', $row['date']);
        
                    $date = date('d m Y, H:i', $row['date']);
        
                    $date = str_replace(
                        ' '.$month.' ',
                        ' '.$month_arr[$month - 1].' ',
                        $date
                    );
        
                    $result[1]['list'][] = array(
                        'name' => $row['name'],
                        'count' => number_format($row['count'] * -1, 3, ',', ' ').' '.$row['untils'],
                        'sum' => number_format($row['total'] * -1, 2, ',', ' ').' '.CURRENCY,
                        'date' => $date
                    );

                }

            break;

            //Списано
            case 'detucted':

                $result = array(
                    ['title' => 'Ручные списания', 'list' => []],
                    ['title' => 'Перемещение между складами', 'list' => []]
                );

                $query = DB::query('
                    SELECT i.name, tr.count, tr.total, r.date, e.name AS employee, i.untils
                    FROM '.DB_PARTNER_TRANSACTIONS.' tr
                    JOIN '.DB_REMOVALS.' r ON r.id = tr.proccess_id
                    JOIN '.DB_ITEMS.' i ON i.id = tr.item
                    LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = r.employee
                    WHERE tr.proccess = 3 AND tr.point = '.$point.' AND tr.date BETWEEN '.$from.' AND '.$to.' AND tr.item = '.$item.'
                    ORDER BY tr.date DESC
                ');

                while($row = DB::getRow($query)){

                    $month = date('m', $row['date']);
        
                    $date = date('d m Y, H:i', $row['date']);
        
                    $date = str_replace(
                        ' '.$month.' ',
                        ' '.$month_arr[$month - 1].' ',
                        $date
                    );
        
                    $result[0]['list'][] = array(
                        'name' => $row['name'],
                        'count' => number_format($row['count'] * -1, 3, ',', ' ').' '.$row['untils'],
                        'sum' => number_format($row['total'] * -1, 2, ',', ' ').' '.CURRENCY,
                        'date' => $date,
                        'employee' => $row['employee'] == null ? 'Администратор' : $row['employee']
                    );
        
                }

                $query = DB::query('
                    SELECT pt.id, i.name, pt.count, pt.total, pt.date, e.name AS employee, s.type, i.untils
                    FROM '.DB_PARTNER_TRANSACTIONS.' pt
                    LEFT JOIN '.DB_SUPPLIES.' s ON s.id = pt.proccess_id AND s.type = 1
                    JOIN '.DB_ITEMS.' i ON i.id = pt.item
                    LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = s.employee
                    WHERE pt.proccess = 1 AND pt.count < 0 AND pt.point = '.$point.' AND pt.date BETWEEN '.$from.' AND '.$to.' AND pt.item = '.$item.'
                    ORDER BY pt.date DESC
                ');

                while($row = DB::getRow($query)){

                    $month = date('m', $row['date']);
        
                    $date = date('d m Y, H:i', $row['date']);
        
                    $date = str_replace(
                        ' '.$month.' ',
                        ' '.$month_arr[$month - 1].' ',
                        $date
                    );
        
                    $result[1]['list'][] = array(
                        'name' => $row['name'],
                        'count' => number_format($row['count'] * -1, 3, ',', ' ').' '.$row['untils'],
                        'sum' => number_format($row['total'] * -1, 2, ',', ' ').' '.CURRENCY,
                        'date' => $date,
                        'employee' => $row['employee'] == null ? 'Администратор' : $row['employee']
                    );
        
                }

            break;

            default:
                response('error', 'Передан неверный тип отчета.', 1);

        }

        for($i = 0; $i < sizeof($result); $i++){
            if(sizeof($result[$i]['list']) == 0){
                unset($result[$i]);
                sort($result);
                $i--;
            }
        }

        response('success', $result, 7);

    break;

}