<?php
use Support\Pages;
use Support\DB;

require_once ROOT.'api/classes/RemovalClass.php';
require_once ROOT.'api/classes/TableHead.php';
include ROOT.'api/lib/functions.php';
include 'check_inventory.php';
require ROOT.'api/classes/OrderClass.php';

$access = DB::escape($_REQUEST['access']);

function RemovalDelete($removal, $dyd){

    

    DB::delete(DB_REMOVALS, 'id = '.$removal);

    DB::delete(DB_PARTNER_TRANSACTIONS, 'proccess = 3 AND proccess_id = '.$removal.' AND dyd = '.$dyd);

}

if($access == 'terminal'){

    include ROOT.'api/terminal/tokenCheck.php';

    if(!$EMPLOYEE_ID = DB::escape($_REQUEST['employee']))
        response('error', 'Не передан ID сотрудника.', 599);

    $POINT_ID = $pointToken['id'];
    $PARTNER_ID = $pointToken['partner'];

}
else{

    include ROOT.'api/partner/tokenCheck.php';

    $PARTNER_ID = $userToken['id'];
    $EMPLOYEE_ID = $userToken['employee_id'];

    $POINT_ID = DB::escape($_REQUEST['point']);

}

if(!$export = DB::escape($_REQUEST['export']))
    $limit_str = 'LIMIT '.Pages::$limit;

switch($action){

    case 'create':

        $fields = [];

        $fields['date'] = (DB::escape($_REQUEST['date'])) ? DB::escape($_REQUEST['date']) : time();

        if($fields['date'] > time())
            response('error', 'Нельзя выбрать дату будущим числом.', 422);

        $fields['created'] = time();

        if(!$POINT_ID && $access != 'terminal')
            response('error', array('msg' => 'Не передан ID заведения.'), '329');

        inventoryCheck($POINT_ID, $fields['date']);

        $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$PARTNER_ID.' AND id = '.$POINT_ID);
        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

        $fields['point'] = $POINT_ID;
        $fields['partner'] = $PARTNER_ID;
        $fields['status'] = 4;

        if($userToken['employee'])
            $fields['employee'] = $userToken['employee'];

        if($EMPLOYEE_ID)
            $fields['employee'] = $EMPLOYEE_ID;

        if(!$items = DB::escape($_REQUEST['items']))
            response('error', array('msg' => 'Не передан объект с ингредиентами.'), '353');

        $removal = 0;

        if(!$cause = DB::escape($_REQUEST['cause']))
            response('error', array('msg' => 'Не выбрана причина списания.'), '391');

        $new_cause = DB::escape($_REQUEST['new_cause']);

        //Если новая причина, то добавляем в таблицу
        if($new_cause === 'true'){
            $cause_exists = DB::select('id', DB_REMOVAL_CAUSES, "name LIKE '$cause'", '', 1);
            if(DB::getRecordCount($cause_exists))
                $cause = DB::getRow($cause_exists)['id'];
            else
                $cause = DB::insert(array('partner' => $PARTNER_ID, 'name' => $cause), DB_REMOVAL_CAUSES);
        }
        else{
            //Иначе проверяем, существует ли такая причина

            $causeData = DB::select('id', DB_REMOVAL_CAUSES, 'id = '.$cause.' AND partner = '.$PARTNER_ID);

            if(DB::getRecordCount($causeData) == 0)
                response('error', array('msg' => 'Причина не найдена.'), '392');

        }

        $fields['cause'] = $cause;

        $rm_class = new RemovalClass(false, $fields, $items, $PARTNER_ID, $POINT_ID, $fields['date']);
        $rm_class->create();

        response('success', 'Списание создано.', 7);


        break;

    case 'get':

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND r.point = '.$point;

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $removal_cause = "";
        if(isset($_REQUEST['removal_cause']) && $_REQUEST['removal_cause'] > 0)
        $removal_cause = " AND  r.cause =".$_REQUEST['removal_cause'];

        $sorting = Order::removal(Pages::$field, Pages::$order);

        $removals = DB::query('SELECT r.id, p.id AS pid, p.name AS pname, r.date, r.status, r.total_sum, rc.id AS rcid, rc.name AS rcname, e.id AS eid, e.name AS ename, t.products, t.categories,
                                (SELECT i.id
                                FROM '.DB_INVENTORY.' i
                                WHERE i.point = r.point AND i.date_end >= r.date AND i.status = 1
                                LIMIT 1) AS close
                                    FROM '.DB_REMOVALS.' r
                                    LEFT JOIN '.DB_EMPLOYEES.' AS e ON e.id = r.employee
                                    LEFT JOIN '.DB_REMOVAL_CAUSES.' AS rc ON rc.id = r.cause
                                    LEFT JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = r.point
                                    LEFT JOIN (
                                        SELECT t.id, GROUP_CONCAT(DISTINCT t.name SEPARATOR ", ") AS products, GROUP_CONCAT(DISTINCT t.category SEPARATOR ", ") AS categories
                                        FROM (
                                            SELECT r.id, IF(ri.type = 1, CONCAT(p.name, IF(tc.subname != "", CONCAT(" (", tc.subname, ")"), ""), " ", tc.bulk_value, " ", tc.bulk_untils), i.name) AS name, IF(ri.type = 1, pc.name, ic.name) AS category
                                            FROM '.DB_REMOVALS.' r
                                            JOIN '.DB_REMOVAL_ITEMS.' ri ON r.id = ri.removal
                                            LEFT JOIN '.DB_ITEMS.' i ON i.id = ri.item AND ri.type != 1
                                            LEFT JOIN '.DB_TECHNICAL_CARD.' tc ON tc.id = ri.item AND ri.type = 1
                                            LEFT JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
                                            LEFT JOIN '.DB_PRODUCT_CATEGORIES.' pc ON pc.id = p.category
                                            LEFT JOIN '.DB_ITEMS_CATEGORY.' ic ON ic.id = i.category
                                            WHERE r.date BETWEEN '.$from.' AND '.$to.'
                                        ) t
                                        GROUP BY t.id
                                    ) t ON t.id = r.id
                                    WHERE r.partner = '.$PARTNER_ID.' AND r.date BETWEEN '.$from.' AND '.$to.$point.$removal_cause.'
                                    '.$sorting.'
                                    '.$limit_str);

        if($export){

            require ROOT.'api/classes/ExportToFileClass.php';
            $f_class = new ExportToFile(false, TableHead::warehouse_removals(), 'Списания');

            $i = 1;

            while($row = DB::getRow($removals)){

                $f_class->data[] = array('i' => $i,
                    'date' => UnixToDateRus((int)$row['date'], true),
                    'name' => $row['pname'],
                    'sum' => round($row['total_sum'], 2),
                    'cause' => $row['rcname']);

                $i++;

            }

            $f_class->create();

        }

        $pages = DB::query('SELECT COUNT(r.id) AS count, SUM(r.total_sum) AS sum
                                    FROM '.DB_REMOVALS.' r
                                    WHERE r.partner = '.$PARTNER_ID.' AND r.date >= '.$from.' AND r.date < '.$to.$point.$removal_cause);
        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT,
            'rows_count' => (int)$pages['count'],
            'sum' => number_format($pages['sum'], 2, ',', ' '));

        $result = [];

        while($row = DB::getRow($removals)){

            /* $products = DB::query('
                SELECT GROUP_CONCAT(DISTINCT t.name SEPARATOR ", ") AS products, GROUP_CONCAT(DISTINCT t.category SEPARATOR ", ") AS categories
                FROM (
                    SELECT IF(ri.type = 1, CONCAT(p.name, IF(tc.subname != "", CONCAT(" (", tc.subname, ")"), ""), " ", tc.bulk_value, " ", tc.bulk_untils), i.name) AS name, IF(ri.type = 1, pc.name, ic.name) AS category
                    FROM '.DB_REMOVAL_ITEMS.' ri
                    LEFT JOIN '.DB_ITEMS.' i ON i.id = ri.item AND ri.type != 1
                    LEFT JOIN '.DB_TECHNICAL_CARD.' tc ON tc.id = ri.item AND ri.type = 1
                    LEFT JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
                    LEFT JOIN '.DB_PRODUCT_CATEGORIES.' pc ON pc.id = p.category
                    LEFT JOIN '.DB_ITEMS_CATEGORY.' ic ON ic.id = i.category
                    WHERE ri.removal = '.$row['id'].'
                    LIMIT 10
                ) t
            '); */

            //$products = DB::getRow($products);

            $result[] = array('id' => $row['id'],
                'date' => $row['date'],
                'status' => $row['status'],
                'close' => ($row['close'] == null) ? false : true,
                'point' => array('id' => $row['pid'],
                    'name' => $row['pname']),
                'sum' => round($row['total_sum'], 2),
                'products' => $row['products'],
                'categories' => $row['categories'],
                'cause' => array('id' => $row['rcid'],
                    'name' => $row['rcname']),
                'employee' => array('id' => $row['eid'],
                    'name' => ($row['eid'] == null) ? 'Администратор' : $row['ename']));
        }

        response('success', $result, '7', $pageData);

        break;

    case 'causes':

        $causes = DB::select('id, name', DB_REMOVAL_CAUSES, 'partner = '.$PARTNER_ID.' AND deleted_at IS NULL', 'name');
        $causes = DB::makeArray($causes);

        response('success', $causes, '7');

        break;

    case 'items':

        $result = array(
            [
                'type' => 0,
                'title' => 'Ингредиенты',
                'items' => []
            ],
            [
                'type' => 1,
                'title' => 'Тех. карты',
                'items' => []
            ],
            [
                'type' => 2,
                'title' => 'Продукция',
                'items' => []
            ]
        );

        if(!$POINT_ID && $access != 'terminal')
            response('error', array('msg' => 'Не передан ID склада.'), '348');

        $archive_i = '
            AND i.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "item" AND partner_id = '.$PARTNER_ID.'
            )';

        //Ингредиенты
        $items = DB::query('
            SELECT i.id, i.name, i.untils, p.price, p.count
            FROM '.DB_POINT_ITEMS.' p
            JOIN '.DB_ITEMS.' AS i ON i.id = p.item
            WHERE (i.partner = '.$PARTNER_ID.' OR i.partner IS NULL) AND p.point = '.$POINT_ID.' AND i.del = 0'.$archive_i.'
            ORDER BY i.name
        ');

        while($row = DB::getRow($items)){

            $row['type'] = 0;

            $result[0]['items'][] = $row;
        }

        $archive_tc = '
            AND tc.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "technical_card" AND partner_id = '.$PARTNER_ID.'
            )';

        //Тех. карты
        $technical_cards = DB::query('
            SELECT tc.id, p.name, tc.subname, tc.bulk_untils, tc.bulk_value, tc.weighted
            FROM '.DB_TECHNICAL_CARD.' tc
            JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
            WHERE (tc.partner = '.$PARTNER_ID.' OR tc.partner IS NULL)'.$archive_tc.'
        ');

        while($row = DB::getRow($technical_cards)){

            if($row['subname'])
                $row['name'] .= ' ('.$row['subname'].')';

            $row['name'] .= ' '.$row['bulk_value'].' '.$row['bulk_untils'];

            $result[1]['items'][] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'type' => 1,
                'weighted' => (boolean)$row['weighted']
            );
        }

        //Полуфабрикаты
        $items = DB::query('
            SELECT i.id, i.name, i.untils, i.bulk
            FROM '.DB_ITEMS.' AS i
            WHERE (i.partner = '.$PARTNER_ID.' OR i.partner IS NULL) AND i.del = 0 AND i.production = 1'.$archive_i.'
            ORDER BY i.name
        ');

        while($row = DB::getRow($items)){
            $row['type'] = 2;

            $result[2]['items'][] = $row;
        }

        response('success', $result, '7');

        break;

    case 'details':

        $result = [];

        $element_count = 10;

        if(!$page || $page == 1){
            $page = '1';
            $limit = '0,'.$element_count;
        }
        else{
            $begin = $element_count*$page - $element_count;
            $limit = $begin.','.$element_count;
        }

        if(!$removal = DB::escape($_REQUEST['removal']))
            response('error', array('msg' => 'Выберите списание.'), '393');

        $items = DB::query('SELECT ri.id, i.name AS item, ri.count, ri.price, i.untils, ri.sum, ri.comment, ri.type, p.name AS pname, tc.subname, tc.bulk_value, tc.bulk_untils
                                    FROM '.DB_REMOVAL_ITEMS.' ri
                                    LEFT JOIN '.DB_ITEMS.' i ON i.id = ri.item
                                    LEFT JOIN '.DB_TECHNICAL_CARD.' tc ON tc.id = ri.item
                                    LEFT JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
                                    JOIN '.DB_REMOVALS.' r ON r.id = ri.removal
                                    WHERE r.partner = '.$PARTNER_ID.' AND ri.removal = '.$removal.'
                                    LIMIT '.Pages::$limit);

        while($row = DB::getRow($items)){

            $row['sum'] = round($row['sum'], 2);
            $row['price'] = round($row['price'], 2);

            if($row['type'] == 1){

                if($row['subname'])
                    $row['pname'] .= ' ('.$row['subname'].')';

                $row['item'] = $row['pname'];
                $row['untils'] = $row['bulk_value'].' '.$row['bulk_untils'];

            }

            $result[] = $row;

        }

        $pages = DB::query('SELECT COUNT(ri.id) AS count FROM '.DB_REMOVAL_ITEMS.' ri
                                    JOIN '.DB_REMOVALS.' AS r ON r.id = ri.removal
                                    WHERE r.partner = '.$PARTNER_ID.' AND ri.removal = '.$removal);
        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
            'total_pages' => $total_pages,
            'page_size' => ELEMENT_COUNT,
            'rows_count' => (int)$pages['count']);

        response('success', $result, '7', $pageData);

        break;

    case 'info':

        if(!$removal = DB::escape($_REQUEST['removal']))
            response('error', array('msg' => 'Выберите списание.'), '393');

        $removalData = DB::query('SELECT r.id, r.date, r.point, r.cause, p.name AS pname, rc.name AS cname
                                        FROM '.DB_REMOVALS.' r
                                        LEFT JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = r.point
                                        LEFT JOIN '.DB_REMOVAL_CAUSES.' AS rc ON rc.id = r.cause
                                        WHERE r.id = '.$removal.' AND r.partner = '.$PARTNER_ID);

        $removalData = DB::getRow($removalData);
        $removalData['point'] = array('id' => $removalData['point'],
            'name' => $removalData['pname']);

        unset($removalData['pname']);

        $removalData['cause'] = array('id' => $removalData['cause'],
            'name' => $removalData['cname']);

        unset($removalData['cname']);

        $removalData['items'] = [];

        $details = DB::query('SELECT ri.type, i.bulk, ri.price AS doc_price, i.id AS item_id, i.name AS item, ri.count, i.untils, ri.comment, pi.count AS accessCount, pi.price, p.name AS product, tc.id AS tc_id, tc.subname, tc.bulk_untils, tc.bulk_value, tc.weighted
                                    FROM '.DB_REMOVAL_ITEMS.' ri
                                    LEFT JOIN '.DB_TECHNICAL_CARD.' tc ON tc.id = ri.item AND ri.type = 1
                                    LEFT JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
                                    LEFT JOIN '.DB_ITEMS.' AS i ON i.id = ri.item AND (ri.type = 0 OR ri.type = 2)
                                    LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = ri.item AND pi.point = ri.point
                                    WHERE ri.removal = '.$removal);

        $total_data = array(
            'total_sum' => 0,
            'total_count' => 0
        );

        while($row = DB::getRow($details)){

            if($row['subname'])
                $row['product'] .= ' ('.$row['subname'].')';

            $row['product'] .= ' '.$row['bulk_value'].' '.$row['bulk_untils'];

            $sum = $row['doc_price'] * $row['count'];

            $total_data['total_count'] += $row['count'];
            $total_data['total_sum'] += $sum;

            $removalData['items'][] = array(
                'id' => $row['type'] == 1 ? $row['tc_id'] : $row['item_id'],
                'type' => $row['type'],
                'bulk' => $row['type'] == 1 ? 1 : $row['bulk'],
                'item' => $row['type'] == 1 ? $row['product'] : $row['item'],
                'count' => $row['count'],
                'untils' => $row['type'] == 1 ? '' : $row['untils'],
                'accessCount' => $row['type'] == 1 ? '' : $row['accessCount'],
                'comment' => $row['comment'],
                'price' => $row['type'] == 1 ? '' : $row['price'],
                'doc_price' => $row['doc_price'],
                'sum' => $sum,
                'weighted' => is_null($row['weighted']) ? false : (boolean)$row['weighted']
            );
        }

        response('success', $removalData, '7', $total_data);

        break;

    case 'edit':

         
        //Проверяем передано ли списание
        if(!$removal = DB::escape($_REQUEST['removal']))
            response('error', array('msg' => 'Выберите списание.'), '393');



        //Получаем документ списания
        $removalData = DB::query('SELECT id, employee, point, date FROM '.DB_REMOVALS.' WHERE partner = '.$PARTNER_ID.' AND id = '.$removal);

        if(DB::getRecordCount($removalData) == 0)
            response('error', array('msg' => 'Списание не найдено.'), '394');

        $removalData = DB::getRow($removalData);
        $dyd = date('Ym', $removalData['date']);
      
        //Получаем текущии записи в движении остатков по текущему списанию
        $partner_transactions = DB::select('id', DB_PARTNER_TRANSACTIONS, "proccess_id = {$removalData['id']} AND proccess = 3 AND dyd = {$dyd}");
        $partner_transactions = DB::getRecordCount($partner_transactions) ? DB::getColumn('id', $partner_transactions) : [];
        $partner_transactions = implode(',', $partner_transactions);

        //Проверям что не было инвентаризации до этого, если была то выдаем ошибку
        inventoryCheck($removalData['point'], $removalData['date']);

        $fields = [];

        //Получаем дату из поля
        $fields['date'] = (DB::escape($_REQUEST['date'])) ? DB::escape($_REQUEST['date']) : time();

        if($fields['date'] > time())
            response('error', 'Нельзя выбрать дату будущим числом.', 422);

        $fields['created'] = time();
        if($removalData['employee'] != null)
            $fields['employee'] = $removalData['employee'];

        
        if(!$POINT_ID && $access != 'terminal')
            response('error', array('msg' => 'Не передан ID заведения.'), '329');

        //Проверям что такая точка есть    
       $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$PARTNER_ID.' AND id = '.$POINT_ID);
        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

        $fields['point'] = $POINT_ID;
        $fields['partner'] = $PARTNER_ID;
        $fields['status'] = 4;
       
        if($EMPLOYEE_ID)
            $fields['employee'] = $EMPLOYEE_ID;

        //Получаем список списания 
        if(!$items = DB::escape($_REQUEST['items']))
            response('error', array('msg' => 'Не передан объект с ингредиентами.'), '353');

        //Получаем причину списания
        if(!$cause = DB::escape($_REQUEST['cause']))
            response('error', array('msg' => 'Не выбрана причина списания.'), '391');

        $new_cause = DB::escape($_REQUEST['new_cause']);

        //Если новая причина, то добавляем в таблицу
        if($new_cause === 'true'){
            $cause_exists = DB::select('id', DB_REMOVAL_CAUSES, "name LIKE '$cause'", '', 1);
            if(DB::getRecordCount($cause_exists))
                $cause = DB::getRow($cause_exists)['id'];
            else
                $cause = DB::insert(array('partner' => $PARTNER_ID, 'name' => $cause), DB_REMOVAL_CAUSES);
        }
        else{
            //Иначе проверяем, существует ли такая причина

            $causeData = DB::select('id', DB_REMOVAL_CAUSES, 'id = '.$cause.' AND partner = '.$PARTNER_ID);

            if(DB::getRecordCount($causeData) == 0)
                response('error', array('msg' => 'Причина не найдена.'), '392');

        }

        $fields['cause'] = $cause;

        //Создаем класс списания
        $rm_class = new RemovalClass(false, $fields, $items, $userToken['id'], $POINT_ID, $fields['date']);
        $rm_class->edit($removalData['id']);

        if(!empty($partner_transactions)) 
            DB::delete(DB_PARTNER_TRANSACTIONS, "id IN ($partner_transactions) AND dyd = {$dyd}");

        response('success', array('msg' => 'Списание обновлено.'), '631');


        break;

    case 'report':

        /*
            SELECT r.id, p.name AS partner, IFNULL(e.name, "Администратор") AS employee, i.name AS item, SUM(tr.count) AS count
            FROM `app_removals` r
            JOIN `app_partner` p ON p.id = r.partner
            LEFT JOIN `app_employees` e ON e.id = r.employee
            JOIN `app_partner_transactions` tr ON tr.proccess_id = r.id AND tr.proccess = 3
            JOIN `app_items` i ON i.id = tr.item
            WHERE tr.date BETWEEN 1572566400 AND 1575158400
            GROUP BY tr.item
        */

        break;

    case 'delete':

        if(!$removal = DB::escape($_REQUEST['removal']))
            response('error', array('msg' => 'Выберите списание.'), '393');

        $removalData = DB::query('SELECT id, point, date FROM '.DB_REMOVALS.' WHERE partner = '.$PARTNER_ID.' AND id = '.$removal);
        if(DB::getRecordCount($removalData) == 0)
            response('error', array('msg' => 'Списание не найдено.'), '394');

        $removalData = DB::getRow($removalData);
        $dyd = date('Ym', $removalData['date']);

        inventoryCheck($removalData['point'], $removalData['date']);

        RemovalDelete($removal, $dyd);

        response('success', 'Списание удалено.', 7);

        break;

}