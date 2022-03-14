<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/terminal/tokenCheck.php';

switch($action){

    case 'get':

        $supplies = DB::query('SELECT s.id, s.supplier, sup.name, s.date, p.id AS pointFrom, p.name AS pname, s.type, s.status, s.items_count, s.sum,
                            (SELECT GROUP_CONCAT(DISTINCT i.name SEPARATOR ", ")
                            FROM '.DB_ITEMS.' i
                            JOIN '.DB_SUPPLY_ITEMS.' AS si ON si.item = i.id
                            WHERE si.supply = s.id) AS items
                                    FROM '.DB_SUPPLIES.' s
                                    LEFT JOIN '.DB_SUPPLIERS.' AS sup ON sup.id = s.supplier
                                    LEFT JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = s.pointFrom
                                    WHERE s.pointTo = '.$pointToken['id'].' AND s.status != 4 AND s.items_count != 0
                                    ORDER BY s.date DESC
                                    LIMIT '.Pages::$limit);

        $result = [];

        while($row = DB::getRow($supplies)){

            $row['from'] = ($row['type'] == 1) ? $row['pname'] : $row['name'];

            $result[] = array('id' => $row['id'],
                                'from' => $row['from'],
                                'date' => $row['date'],
                                'type' => $row['type'],
                                'status' => $row['status'],
                                'items' => $row['items'],
                                'items_count' => $row['items_count'],
                                'sum' => round($row['sum'], 2));

        }

        $pages = DB::select('COUNT(id) AS count', DB_SUPPLIES, 'pointTo = '.$pointToken['id'].' AND status != 4');

        $pages = DB::getRow($pages);

        //Высчитываем количество страниц
        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT);

        response('success', $result, '7', $pageData);

    break;

    case 'items':

        if(!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Выберите поставку или перемещение.'), '538');

        $supplyData = DB::query('SELECT i.id, i.name, s.count, s.price, s.sum, s.tax, s.total
                                        FROM '.DB_SUPPLY_ITEMS.' s
                                        JOIN '.DB_SUPPLIES.' AS sup ON sup.id = s.supply
                                        JOIN '.DB_ITEMS.' AS i ON i.id = s.item
                                        WHERE s.supply = '.$supply.' AND sup.pointTo = '.$pointToken['id'].'
                                        ORDER BY i.name');

        $result = [];

        while($row = DB::getRow($supplyData)){

            $result[] = array('id' => $row['id'],
                                'name' => $row['name'],
                                'count' => round($row['count']),
                                'price' => round($row['price'], 2),
                                'sum' => round($row['sum'], 2),
                                'tax' => round($row['tax'], 2),
                                'total' => round($row['total'], 2));

        }

        response('success', $result, '7');

    break;

    case 'confirm':

        if(!$supply = DB::escape($_REQUEST['supply']))
            response('error', array('msg' => 'Выберите поставку или перемещение.'), '538');

        /*
        Чтобы реализовать отказ продуктов, необходимо принять строку с ID товаров через запятую, сделать update таблицы с товарами в поставке, поставив denial=1
        */
        if($denials = DB::escape($_REQUEST['denials'])){

            $denials = explode(',', $denials);

            for($i = 0; $i < sizeof($denials); $i++){

                if(!$where)
                    $where = 'item = '.$denials[$i];
                else
                    $where .= ' OR item = '.$denials[$i];

            }

            if($where)
                DB::update(array('denial' => 1), DB_SUPPLY_ITEMS, '('.$where.') AND supply = '.$supply);

        }

        $type = (DB::escape($_REQUEST['type'])) ? 1 : 0;

        $supplyData = DB::select('id, supplier, pointFrom, type, status', DB_SUPPLIES, 'id = '.$supply.' AND partner = '.$pointToken['partner'].' AND type = '.$type);

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

        if($type == 0){

            //Получаем список ингредиентов в поставке supply, которые были приняты, то есть denial=0
            $items = DB::query('SELECT si.id, si.item, si.count, si.price, si.sum, si.tax, si.total, si.denial, s.pointTo,
                    (SUM(pi.price * pi.count) / SUM(pi.count)) AS average_price_begin, SUM(pi.count) AS balance_begin
                                FROM '.DB_SUPPLY_ITEMS.' si
                                JOIN '.DB_SUPPLIES.' AS s ON s.id = si.supply
                                LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = si.item AND pi.point = '.$pointToken['id'].'
                                WHERE si.supply = '.$supply.' AND si.denial = 0
                                GROUP BY si.item');

            if(DB::getRecordCount($items) == 0){

                DB::update(array('denial' => 0), DB_SUPPLY_ITEMS, 'supply = '.$supply);

                response('error', array('msg' => 'Вы не можете принять поставку, не приняв ни одного из товаров.'), '583');
            }

            $items = DB::makeArray($items);

            for($i = 0; $i < sizeof($items); $i++){

                $average_price_begin = ($items[$i]['average_price_begin'] == null) ? 0 : $items[$i]['average_price_begin'];
                $balance_begin = ($items[$i]['balance_begin'] == null) ? 0 : $items[$i]['balance_begin'];

                $average_price_end = ($items[$i]['average_price_begin'] + $items[$i]['price']) / 2;
                $balance_end = $items[$i]['balance_begin'] + $items[$i]['count'];

                if(!$transactionsInsert)
                    $transactionsInsert = '("'.$items[$i]['item'].'", "'.$pointToken['partner'].'", "'.$items[$i]['pointTo'].'", "0", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", "'.$supplier.'")';
                else
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$pointToken['partner'].'", "'.$items[$i]['pointTo'].'", "0", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", "'.$supplier.'")';

            }

            //Добавляем в таблицу с транзакциями информацию о товарах, которые были подтверждены в поставке, далее работу по добавлению на склад выполняет триггер "transaction_plus"
            if(DB::query('INSERT INTO '.DB_PARTNER_TRANSACTIONS.' (item, partner, point, type, count, price, sum, tax, total, operation, date, balance_begin, average_price_begin, balance_end, average_price_end, supply, supplier) VALUES '.$transactionsInsert)){
                DB::update(array('status' => 4), DB_SUPPLIES, 'id = '.$supply);
                response('success', array('msg' => 'Поставка принята.'), '619');
            }
            else
                response('error', '', '503');

        }

        
        if($type == 1){

            require ROOT.'api/classes/PointItems.php';

            //Получаем список ингредиентов в поставке supply, которые были приняты, то есть denial=0
            $items = DB::query('SELECT si.id, si.item, si.count, si.price, si.sum, si.tax, si.total, si.denial, s.pointTo, s.pointFrom,
                    (SUM(pi.price * pi.count) / SUM(pi.count)) AS average_price_begin, SUM(pi.count) AS balance_begin,
                    (SUM(fpi.price * fpi.count) / SUM(fpi.count)) AS from_average_price_begin, SUM(fpi.count) AS from_balance_begin
                                FROM '.DB_SUPPLY_ITEMS.' si
                                JOIN '.DB_SUPPLIES.' AS s ON s.id = si.supply
                                LEFT JOIN '.DB_POINT_ITEMS.' AS fpi ON fpi.item = si.item AND fpi.point = s.pointFrom
                                LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = si.item AND pi.point = '.$pointToken['id'].'
                                WHERE si.supply = '.$supply.' AND si.denial = 0
                                GROUP BY si.item');

            if(DB::getRecordCount($items) == 0){

                DB::update(array('denial' => 0), DB_SUPPLY_ITEMS, 'supply = '.$supply);

                response('error', array('msg' => 'Вы не можете принять поставку, не приняв ни одного из товаров.'), '583');
            }

            $items = DB::makeArray($items);
            
            //                 экземляр класса db| ID точки откуда перем |              ТАБЛИЦЫ                | ID текущей точки | НЕ партнер
            $pi_class = new PointItems(false, $items[0]['pointFrom'], DB_POINT_ITEMS, DB_POINT_ITEMS_TMP, $pointToken['id'], "0");

            for($i = 0; $i < sizeof($items); $i++){

                $item_minus = array('id' => $items[$i]['item'],
                                    'begin_balance' => $items[$i]['from_balance_begin'],
                                    'end_balance' => $items[$i]['from_balance_begin'] - $items[$i]['count']);

                $pi_class->setItem($item_minus);

                $average_price_begin = ($items[$i]['average_price_begin'] == null) ? 0 : $items[$i]['average_price_begin'];
                $balance_begin = ($items[$i]['balance_begin'] == null) ? 0 : $items[$i]['balance_begin'];

                $average_price_end = ($items[$i]['average_price_begin'] + $items[$i]['price']) / 2;
                $balance_end = $items[$i]['balance_begin'] + $items[$i]['count'];

                if(!$transactionsInsert){
                    $transactionsInsert = '("'.$items[$i]['item'].'", "'.$pointToken['partner'].'", "'.$supplier.'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "minus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$pointToken['partner'].'", "'.$items[$i]['pointTo'].'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                }
                else{
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$pointToken['partner'].'", "'.$supplier.'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "minus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                    $transactionsInsert .= ', ("'.$items[$i]['item'].'", "'.$pointToken['partner'].'", "'.$items[$i]['pointTo'].'", "1", "'.$items[$i]['count'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['tax'].'", "'.$items[$i]['total'].'", "plus", "'.time().'", "'.$balance_begin.'", "'.$average_price_begin.'", "'.$balance_end.'", "'.$average_price_end.'", "'.$supplyData['id'].'", 1)';
                }
            }

            $pi_class->InsertTmpValues(); // Добавляем что нужно списать во временную таблицу
            $pi_class->moveVariables(); // Перемещаем из временной таблицы в настоящую
            $pi_class->deleteTmpVariables(); // Удаляем информацию из временной таблицы

            //Добавляем в таблицу с транзакциями информацию о товарах, которые были подтверждены в поставке, далее работу по добавлению на склад выполняет триггер "transaction_plus"
            if(DB::query('INSERT INTO '.DB_PARTNER_TRANSACTIONS.' (item, partner, point, type, count, price, sum, tax, total, operation, date, balance_begin, average_price_begin, balance_end, average_price_end, supply, process) VALUES '.$transactionsInsert)){
                DB::update(array('status' => 4), DB_SUPPLIES, 'id = '.$supply);
                response('success', array('msg' => 'Перемещение принято.'), '619');
            }
            else
                response('error', '', '503');

        }

    break;

}