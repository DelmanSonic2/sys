<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

$type = DB::escape($_REQUEST['type']);

switch($action){

    case 'create':

        if(!$date = DB::escape($_REQUEST['date']))
            response('error', array('msg' => 'Не передана дата и время поставки.'), '346');

        //if($date <= time())
            //response('error', array('msg' => 'Нельзя создать поставку на прошедшую дату.'), '350');

        $fields = array('date' => $date);

        if($comment = DB::escape($_REQUEST['comment']))
            $fields['comment'] = $comment;

        if(!$type || $type == 0){

            if(!$supplier = DB::escape($_REQUEST['supplier']))
                response('error', array('msg' => 'Не передан ID поставщика.'), '347');

            $supplierData = DB::select('id', $DB_SUPPLIERS, 'id = '.$supplier.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

            if(DB::getRecordCount($supplierData) == 0)
                response('error', array('msg' => 'Поставщик с таким ID не найден.'), '346');

            $fields['supplier'] = $supplier;
            $fields['type'] = 0;

        }

        if($type == 1){
        
            if(!$supplier = DB::escape($_REQUEST['supplier']))
                response('error', array('msg' => 'Не передан ID склада.'), '348');

            $warehouseData = DB::select('id', $DB_PARTNER_POINTS, 'id = '.$supplier.' AND partner = '.$userToken['id']);

            if(DB::getRecordCount($warehouseData) == 0)
                response('error', array('msg' => 'Склад с таким ID не найден.'), '349');

            $fields['pointFrom'] = $supplier;
            $fields['type'] = 1;

        }

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Не передан ID точки, куда будет осуществляться поставка.'), '351');

        $pointData = DB::select('id', $DB_PARTNER_POINTS, 'id = '.$point.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Склад с таким ID не найден.'), '349');
        
        if($type == 1 && $supplier == $point)
            response('error', array('msg' => 'Вы не можете осуществить поставку на тот же склад, с которого производится поставка.'), '352');

        $fields['pointTo'] = $point;
        $fields['created'] = time();
        $fields['partner'] = $userToken['id'];

        if(!$items = DB::escape($_REQUEST['items']))
            response('error', array('msg' => 'Заполните таблицу с ингредиентами.'), '353');

        $items = stripcslashes($items);

        if(!$items = json_decode($items, true))
            response('error', array('msg' => 'Объект с ингредиентами имеет неверный формат.'), '354');

        if(!$supply = DB::insert($fields, $DB_SUPPLIES))
            response('error', '', '503');

        $SUPPLY_TOTAL = 0;

        for($i = 0; $i < sizeof($items); $i++){

            $row_num = $i + 1;

            if(!$items[$i]['id'] || !$items[$i]['count'] || !$items[$i]['price'])
                response('error', array('msg' => 'Неверно заполнена таблица ингредиентов. Строка '.$row_num.'.'), '355');

            if(!$items[$i]['tax'])
                $items[$i]['tax'] = 0;

            $sum = $items[$i]['price'] * $items[$i]['count'];
            $total = ($sum * $items[$i]['tax'] / 100) + $sum;
            
            $SUPPLY_TOTAL += $total;

            if(!$supplyValues){
                $itemsWhere = 'id = '.$items[$i]['id'];
                $supplyValues = '('.$items[$i]['id'].', '.$items[$i]['count'].','.$items[$i]['price'].', '.$items[$i]['tax'].', '.$sum.', '.$total.', '.$supply.')';
            }
            else{
                $itemsWhere .= ' OR id = '.$items[$i]['id'];
                $supplyValues .= ', ('.$items[$i]['id'].', '.$items[$i]['count'].','.$items[$i]['price'].', '.$items[$i]['tax'].', '.$sum.', '.$total.', '.$supply.')';
            }

        }

        $itemsCount = DB::select('id', $DB_ITEMS, $itemsWhere);

        if(DB::getRecordCount($itemsCount) != sizeof($items))
            response('error', array('msg' => 'Один или более ингредиентов не существуют.'), '355');

        if(DB::query('INSERT INTO '.$DB_SUPPLY_ITEMS.' (item, count, price, tax, sum, total, supply) VALUES '.$supplyValues)){

            $supplyUpdateFields = array('items_count' => sizeof($items),
                                        'sum' => $SUPPLY_TOTAL);

            $message = ($type == 1) ? 'Перемещение создано.' : 'Поставка создана.';
            $code = ($type == 1) ? '618' : '617';

            if(DB::update($supplyUpdateFields, $DB_SUPPLIES, 'id = '.$supply))
                response('success', array('msg' => $message), $code);

        }

        response('error', '', '503');

    break;

    case 'get':

        $result = [];

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND s.comment LIKE "%'.$search.'%"';

        if($supplier = DB::escape($_REQUEST['supplier']))
            $supplier = ' AND sp.id = '.$supplier;

        if($point_to = DB::escape($_REQUEST['point_to']))
            $point_to = ' AND s.pointTo = '.$point_to;

        if($date_from = DB::escape($_REQUEST['date_from']))
            $date_from = ' AND s.date >= '.strtotime(date('d-m-Y', $date_from));

        if($date_to = DB::escape($_REQUEST['date_to']))
            $date_to = ' AND s.date < '.strtotime(date('d-m-Y', $date_to), '+1 days');

        $pages = DB::query('SELECT (s.id) AS count
                                    FROM '.$DB_SUPPLIES.' s
                                    JOIN '.$DB_SUPPLIERS.' AS sp ON sp.id = s.supplier
                                    WHERE s.type = 0 AND s.partner = '.$userToken['id'].$search.$supplier.$point.$date_from.$date_to.$point_to);
        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'page_size' => ELEMENT_COUNT);

        $supplies = DB::query('SELECT s.id, s.date, sp.name AS supplier, p.name AS point, s.items_count, s.comment, s.sum, s.status,
                                (SELECT GROUP_CONCAT(DISTINCT i.name SEPARATOR ", ") FROM '.$DB_ITEMS.' i
                                                            JOIN '.$DB_SUPPLY_ITEMS.' AS si ON si.item = i.id
                                                            WHERE si.supply = s.id) AS items,
                                (SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ", ") FROM '.$DB_ITEMS_CATEGORY.' c
                                                            JOIN '.$DB_ITEMS.' AS i ON i.category = c.id
                                                            JOIN '.$DB_SUPPLY_ITEMS.' AS si ON si.item = i.id
                                                            WHERE si.supply = s.id) AS categories
                                    FROM '.$DB_SUPPLIES.' s
                                    JOIN '.$DB_PARTNER_POINTS.' AS p ON s.pointTo = p.id
                                    JOIN '.$DB_SUPPLIERS.' AS sp ON sp.id = s.supplier
                                    WHERE s.type = 0 AND s.partner = '.$userToken['id'].$search.$supplier.$point.$date_from.$date_to.$point_to.'
                                    ORDER BY s.id DESC
                                    LIMIT '.Pages::$limit);

        while($row = DB::getRow($supplies)){

            if($row['date'] <= time() && $row['status'] == 0)
                $row['status'] = '1';

            $result[] = $row;
        }

        response('success', $result, '7', $pageData);

    break;

    case 'moving':

        $result = [];

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND s.comment LIKE "%'.$search.'%"';

        if($supplier = DB::escape($_REQUEST['supplier']))
            $supplier = ' AND sp.id = '.$supplier;

        if($point_to = DB::escape($_REQUEST['point_to']))
            $point_to = ' AND s.pointTo = '.$point_to;

        if($point_from = DB::escape($_REQUEST['point_from']))
            $point_from = ' AND s.pointFrom = '.$point_from;

        if($date_from = DB::escape($_REQUEST['date_from']))
            $date_from = ' AND s.date >= '.strtotime(date('d-m-Y', $date_from));

        if($date_to = DB::escape($_REQUEST['date_to']))
            $date_to = ' AND s.date < '.strtotime(date('d-m-Y', $date_to), '+1 days');

        $pages = DB::query('SELECT s.id, s.date, pf.name AS pointFrom, p.name AS pointTo, s.items_count, s.comment, s.status, s.sum
                                    FROM '.$DB_SUPPLIES.' s
                                    JOIN '.$DB_PARTNER_POINTS.' AS pf ON s.pointFrom = pf.id 
                                    WHERE s.type = 1 AND s.partner = '.$userToken['id'].$search.$supplier.$point_to.$date_from.$date_to.$point_from);
        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'page_size' => ELEMENT_COUNT);

        $supplies = DB::query('SELECT s.id, s.date, pf.name AS pointFrom, p.name AS pointTo, s.items_count, s.comment, s.status, s.sum,
                                (SELECT GROUP_CONCAT(DISTINCT i.name SEPARATOR ", ") FROM '.$DB_ITEMS.' i
                                                            JOIN '.$DB_SUPPLY_ITEMS.' AS si ON si.item = i.id
                                                            WHERE si.supply = s.id) AS items,
                                (SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ", ") FROM '.$DB_ITEMS_CATEGORY.' c
                                                            JOIN '.$DB_ITEMS.' AS i ON i.category = c.id
                                                            JOIN '.$DB_SUPPLY_ITEMS.' AS si ON si.item = i.id
                                                            WHERE si.supply = s.id) AS categories
                                    FROM '.$DB_SUPPLIES.' s
                                    JOIN '.$DB_PARTNER_POINTS.' AS p ON s.pointTo = p.id
                                    JOIN '.$DB_PARTNER_POINTS.' AS pf ON s.pointFrom = pf.id 
                                    WHERE s.type = 1 AND s.partner = '.$userToken['id'].$search.$supplier.$point_to.$date_from.$date_to.$point_from.'
                                    ORDER BY s.id DESC
                                    LIMIT '.Pages::$limit);

        while($row = DB::getRow($supplies)){

            //$row['items'] = 'Колбаска, молочко, зефирки, печенюшки, борщик с капустой, но не красный, чай зелененький, кофе горьковатый, сахар сладковатый.';
            //$row['categories'] = 'Стадо печенюх, гроздь пельменей, стая орешков, лужа прохладительных напитков, море алкоголя';
            
            if($row['date'] <= time() && $row['status'] == 0)
                $row['status'] = '1';

            $result[] = array('id' => $row['id'],
                                'date' => $row['date'],
                                'items' => (string)$row['items'],
                                'categories' => (string)$row['categories'],
                                'comment' => $row['comment'],
                                'sum' => $row['sum'],
                                'employee' => 'Root',
                                'pointFrom' => $row['pointFrom'],
                                'pointTo' => $row['pointTo'],
                                'status' => $row['status']);

        }

        response('success', $result, '7', $pageData);

    break;

    case 'edit':

        if(!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        $supplyData = DB::select('id, status', $DB_SUPPLIES, 'id = '.$supply.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($supplyData) == 0)
            response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');

        $supplyData = DB::getRow($supplyData);

        if($supplyData['status'] == 4)
            response('error', array('msg' => 'Невозможно редактировать поставку или перемещение, которое уже было произведено.'), '357');

        if($supplyData['status'] == 2)
            response('error', array('msg' => 'Невозможно редактировать поставку или перемещение, которое было отменено.'), '358');

        if($supplyData['status'] == 3)
            response('error', array('msg' => 'В данный момент поставка или перемещение в процессе выполнения. Редактирование невозможно.'), '359');

        if($date = DB::escape($_REQUEST['date']))
            $fields['date'] = $date;

        $fields['comment'] = DB::escape($_REQUEST['comment']);

        if(!$type || $type == 0){

            if($supplier = DB::escape($_REQUEST['supplier'])){
                $fields['supplier'] = $supplier;

                $supplierData = DB::select('id', $DB_SUPPLIERS, 'id = '.$supplier.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

                if(DB::getRecordCount($supplierData) == 0)
                    response('error', array('msg' => 'Поставщик с таким ID не найден.'), '346');
            }

        }

        if($type == 1){

            if(!$supplier = DB::escape($_REQUEST['supplier'])){
                $fields['pointFrom'] = $supplier;

                $warehouseData = DB::select('id', $DB_PARTNER_POINTS, 'id = '.$supplier.' AND partner = '.$userToken['id']);

                if(DB::getRecordCount($warehouseData) == 0)
                    response('error', array('msg' => 'Склад с таким ID не найден.'), '349');

            }

        }

        if($point = DB::escape($_REQUEST['point'])){

            $pointData = DB::select('id', $DB_PARTNER_POINTS, 'id = '.$point.' AND partner = '.$userToken['id']);

            if(DB::getRecordCount($pointData) == 0)
                response('error', array('msg' => 'Склад с таким ID не найден.'), '349');

            if($type == 1 && $supplier == $point)
                response('error', array('msg' => 'Вы не можете осуществить поставку на тот же склад, с которого производится поставка.'), '352');

            $fields['pointTo'] = $point;
        }

        if($items = DB::escape($_REQUEST['items'])){

            $items = stripcslashes($items);

            if(!$items = json_decode($items, true))
                response('error', array('msg' => 'Объект с ингредиентами имеет неверный формат.'), '354');

            $SUPPLY_TOTAL = 0;

            for($i = 0; $i < sizeof($items); $i++){

                $row_num = $i + 1;
                
                if(!$items[$i]['id'] || !$items[$i]['count'] || !$items[$i]['price'])
                    response('error', array('msg' => 'Неверно заполнена таблица ингредиентов. Строка '.$row_num.'.'), '355');

                if(!$items[$i]['tax'])
                    $items[$i]['tax'] = 0;

                $sum = $items[$i]['price'] * $items[$i]['count'];
                $total = ($sum * $items[$i]['tax'] / 100) + $sum;
                
                $SUPPLY_TOTAL += $total;

                if(!$supplyValues){
                    $itemsWhere = 'id = '.$items[$i]['id'];
                    $supplyValues = '('.$items[$i]['id'].', '.$items[$i]['count'].','.$items[$i]['price'].', '.$items[$i]['tax'].', '.$sum.', '.$total.', '.$supply.')';
                }
                else{
                    $itemsWhere .= ' OR id = '.$items[$i]['id'];
                    $supplyValues .= ', ('.$items[$i]['id'].', '.$items[$i]['count'].','.$items[$i]['price'].', '.$items[$i]['tax'].', '.$sum.', '.$total.', '.$supply.')';
                }

            }

            $itemsCount = DB::select('id', $DB_ITEMS, $itemsWhere);

            if(DB::getRecordCount($itemsCount) != sizeof($items))
                response('error', array('Один или более ингредиентов не существуют.'), '355');

            DB::delete($DB_SUPPLY_ITEMS, 'supply = '.$supply);

            if(DB::query('INSERT INTO '.$DB_SUPPLY_ITEMS.' (item, count, price, tax, sum, total, supply) VALUES '.$supplyValues)){

                $fields['items_count'] = sizeof($items);
                $fields['sum'] = $SUPPLY_TOTAL;
        
            }
        }

        if(sizeof($fields) == 0)
            response('error', array('msg' => 'Не передано ни одного параметра.'), '345');

        $message = ($type == 1) ? 'Перемещение обновлено.' : 'Поставка обновлена.';
        $code = ($type == 1) ? '618' : '617';

        if(DB::update($fields, $DB_SUPPLIES, 'id = '.$supply))
            response('success', array('msg' => $message), $code);
        else
            response('error', '', '503');

    break;

    case 'details':

        $element_count = 10;

        if(!$page || $page == 1){
            $page = '1';
            $limit = '0,'.$element_count;
        }
        else{
            $begin = $element_count*$page - $element_count;
            $limit = $begin.','.$element_count; 
        }

        if(!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        $supplyData = DB::select('id, status', $DB_SUPPLIES, 'id = '.$supply.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($supplyData) == 0)
            response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');

        $pages = DB::query('SELECT COUNT(id) as count FROM '.$DB_SUPPLY_ITEMS.' WHERE supply = '.$supply);
        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $details = DB::query('SELECT si.id, i.name, si.count, si.total, si.denial
                                    FROM '.$DB_SUPPLY_ITEMS.' si
                                    LEFT JOIN '.$DB_ITEMS.' AS i ON i.id = si.item
                                    WHERE si.supply = '.$supply.'
                                    ORDER BY i.name
                                    LIMIT '.Pages::$limit);

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'page_size' => ELEMENT_COUNT);

        while($row = DB::getRow($details))
            $result[] = $row;

        response('success', $result, '7', $pageData);

    break;

    case 'items':

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', 'Не передан ID точки', 2);

        $result = [];

        $items = DB::query('SELECT i.id, i.name, SUM(pi.count) AS count, (SUM(pi.price * pi.count) / SUM(pi.count)) AS price, i.untils
                                    FROM '.$DB_ITEMS.' i
                                    JOIN '.$DB_POINT_ITEMS.' AS pi ON pi.item = i.id
                                    WHERE pi.point = '.$point.' AND pi.partner = '.$userToken['id'].'
                                    GROUP BY i.id
                                    HAVING count > 0
                                    ORDER BY i.name');

        while($row = DB::getRow($items)){

            $row['count'] = round($row['count'], 2);
            $row['price'] = round($row['price'], 2);

            $result[] = $row;

        }

        response('success', $result, 7);

    break;

    case 'info':

        if(!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        $type = (DB::escape($_REQUEST['type'])) ? 1 : 0;

        $supplyData = DB::select('id', $DB_SUPPLIES, 'id = '.$supply.' AND partner = '.$userToken['id'].' AND type = '.$type);

        if(DB::getRecordCount($supplyData) == 0){
            if($type == 0)
                response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');
            else
                response('error', array('msg' => 'Перемещение с таким ID не найдено.'), '360');
        }

        if(!$type || $type == 0){

            $supplyData = DB::query('SELECT s.id, s.date, s.status, s.comment, s.items_count, s.sum, s.created, sup.id AS supplierId, sup.name AS supplierName,
                                p.id AS pointId, p.name AS pointName
                                            FROM '.$DB_SUPPLIES.' s
                                            JOIN '.$DB_SUPPLIERS.' AS sup ON sup.id = s.supplier
                                            JOIN '.$DB_PARTNER_POINTS.' AS p ON p.id = s.pointTo
                                            WHERE s.id = '.$supply);

            $row = DB::getRow($supplyData);

            $result = array('id' => $row['id'],
                            'date' => $row['date'],
                            'status' => $row['status'],
                            'comment' => $row['comment'],
                            'items_count' => $row['items_count'],
                            'sum' => $row['sum'],
                            'created' => $row['created'],
                            'supplier' => array('id' => $row['supplierId'],
                                                'name' => $row['supplierName']),
                            'point' => array('id' => $row['pointId'],
                                            'name' => $row['pointName']));

        }

        if($type == 1){

            $supplyData = DB::query('SELECT s.id, s.date, s.status, s.comment, s.items_count, s.sum, s.created, pf.id AS pfid, pf.name AS pfname, pt.id AS pointId, pt.name AS pointName
                                            FROM '.$DB_SUPPLIES.' s
                                            JOIN '.$DB_PARTNER_POINTS.' AS pf ON pf.id = s.pointFrom
                                            JOIN '.$DB_PARTNER_POINTS.' AS pt ON pt.id = s.pointTo
                                            WHERE s.id = '.$supply);

            $row = DB::getRow($supplyData);

            $result = array('id' => $row['id'],
                            'date' => $row['date'],
                            'status' => $row['status'],
                            'comment' => $row['comment'],
                            'items_count' => $row['items_count'],
                            'sum' => $row['sum'],
                            'created' => $row['created'],
                            'supplier' => array('id' => $row['pfid'],
                                                'name' => $row['pfname']),
                            'point' => array('id' => $row['pointId'],
                                            'name' => $row['pointName']));

        }

        $itemsStr = DB::query('SELECT si.id, si.count, si.price, si.sum, si.tax, si.total, si.denial, i.id AS itemId, i.name AS itemName, i.untils
                                    FROM '.$DB_SUPPLY_ITEMS.' si
                                    JOIN '.$DB_ITEMS.' AS i ON i.id = si.item
                                    WHERE si.supply = '.$supply.'
                                    ORDER BY i.name');

        $items = [];

        while($row = DB::getRow($itemsStr)){

            $items[] = array('id' => $row['itemId'],
                            'count' => $row['count'],
                            'price' => $row['price'],
                            'sum' => $row['sum'],
                            'untils' => $row['untils'],
                            'tax' => $row['tax'],
                            'total' => $row['total'],
                            'denial' => $row['denial'],
                            'name' => $row['itemName']);

        }

        $result['items'] = $items;

        response('success', $result, '7');

    break;

    case 'confirm':

        if(!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Не передан ID поставки.'), '356');

        /*
        Чтобы реализовать отказ продуктов, необходимо принять строку с ID товаров через запятую, сделать update таблицы с товарами в поставке, поставив denial=1
        */

        $type = (DB::escape($_REQUEST['type'])) ? 1 : 0;

        $supplyData = DB::select('id, supplier, pointFrom, type, status', $DB_SUPPLIES, 'id = '.$supply.' AND partner = '.$userToken['id'].' AND type = '.$type);

        if(DB::getRecordCount($supplyData) == 0){
            if($type == 0)
                response('error', array('msg' => 'Поставка с таким ID не найдена.'), '357');
            else
                response('error', array('msg' => 'Перемещение с таким ID не найдено.'), '360');
        }

        $supplyData = DB::getRow($supplyData);

        $supplier = ($supplyData['type'] == 1) ? $supplyData['pointFrom'] : $supplyData['supplier'];

        if($supplyData['status'] == 4){
            if($type == 0)
                response('error', array('msg' => 'Данная поставка уже была принята.'), '362');
            else
                response('error', array('msg' => 'Данное перемещение уже было принято.'), '363');
        }

        //Получаем список ингредиентов в поставке supply, которые были приняты, то есть denial=0
        $items = DB::query('SELECT si.id, si.item, si.count, si.price, si.sum, si.tax, si.total, si.denial, s.pointTo,
                        (SELECT AVG(price) FROM '.$DB_POINT_ITEMS.' WHERE item = si.item GROUP BY item) AS average_price_begin,
                        (SELECT SUM(count) FROM '.$DB_POINT_ITEMS.' WHERE item = si.item GROUP BY item) AS balance_begin
                                    FROM '.$DB_SUPPLY_ITEMS.' si
                                    JOIN '.$DB_SUPPLIES.' AS s ON s.id = si.supply
                                    WHERE si.supply = '.$supply.' AND si.denial = 0');

        if(DB::getRecordCount($items) == 0)
            response('error', '', '503');

        $items = DB::makeArray($items);

        if($type == 0){

            for($i = 0; $i < sizeof($items); $i++){

                $average_price_begin = ($items[$i]['average_price_begin'] == null) ? 0 : $items[$i]['average_price_begin'];
                $balance_begin = ($items[$i]['balance_begin'] == null) ? 0 : $items[$i]['balance_begin'];

                $average_price_end = ($items[$i]['average_price_begin'] + $items[$i]['price']) / 2;
                $balance_end = $items[$i]['balance_begin'] + $items[$i]['count'];

                if(!$transactionsInsert)
                    $transactionsInsert = '("'.$items[$i]['item'].'", "'.$userToken['id'].'", "'.$items[$i]['pointTo'].'", "0", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", "'.$supplier.'")';
                else
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$userToken['id'].'", "'.$items[$i]['pointTo'].'", "0", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", "'.$supplier.'")';

            }

            //Добавляем в таблицу с транзакциями информацию о товарах, которые были подтверждены в поставке, далее работу по добавлению на склад выполняет триггер "transaction_plus"
            if(DB::query('INSERT INTO '.$DB_PARTNER_TRANSACTIONS.' (item, partner, point, type, count, price, sum, tax, total, operation, date, balance_begin, average_price_begin, balance_end, average_price_end, supply, supplier) VALUES '.$transactionsInsert)){
                DB::update(array('status' => 4), $DB_SUPPLIES, 'id = '.$supply);
                response('success', array('msg' => 'Поставка принята.'), '619');
            }
            else
                response('error', '', '503');

        }

        
        if($type == 1){

            for($i = 0; $i < sizeof($items); $i++){

                $average_price_begin = ($items[$i]['average_price_begin'] == null) ? 0 : $items[$i]['average_price_begin'];
                $balance_begin = ($items[$i]['balance_begin'] == null) ? 0 : $items[$i]['balance_begin'];

                $average_price_end = ($items[$i]['average_price_begin'] + $items[$i]['price']) / 2;
                $balance_end = $items[$i]['balance_begin'] + $items[$i]['count'];

                if(!$transactionsInsert){
                    $transactionsInsert = '("'.$items[$i]['item'].'", "'.$userToken['id'].'", "'.$supplier.'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "minus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$userToken['id'].'", "'.$items[$i]['pointTo'].'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                }
                else{
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$userToken['id'].'", "'.$supplier.'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "minus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$userToken['id'].'", "'.$items[$i]['pointTo'].'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                }
            }

            //Триггер в таблице partner_transactions работает неверно когда minus 

            //Добавляем в таблицу с транзакциями информацию о товарах, которые были подтверждены в поставке, далее работу по добавлению на склад выполняет триггер "transaction_plus"
            if(DB::query('INSERT INTO '.$DB_PARTNER_TRANSACTIONS.' (item, partner, point, type, count, price, sum, tax, total, operation, date, balance_begin, average_price_begin, balance_end, average_price_end, supply, process) VALUES '.$transactionsInsert)){
                DB::update(array('status' => 4), $DB_SUPPLIES, 'id = '.$supply);
                response('success', array('msg' => 'Перемещение принято.'), '619');
            }
            else
                response('error', '', '503');

        }
    break;

}
