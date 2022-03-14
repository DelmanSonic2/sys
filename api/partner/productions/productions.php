<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/partner/warehouse/check_inventory.php';

require_once ROOT.'api/classes/ProductionsClass.php';
require_once ROOT.'api/classes/MovingClass.php';
require ROOT.'api/classes/ProductionCostPriceClass.php';
require ROOT.'api/classes/OrderClass.php';

function DeleteProduction($production, $user){

    

    $productionData = DB::select('id, moving, date', DB_PRODUCTIONS, 'id = '.$production);

    if(DB::getRecordCount($productionData) == 0)
        response('error', 'Производство не найдено.', 1);

    $productionData = DB::getRow($productionData);

    $dyd = date('Ym', $productionData['date']);

    DB::delete(DB_PRODUCTIONS, 'id = '.$production);

    DB::delete(DB_PARTNER_TRANSACTIONS, 'partner = '.$user.' AND proccess = 5 AND proccess_id = '.$production.' AND dyd = '.$dyd);

    if($productionData['moving']){

        DB::delete(DB_SUPPLIES, 'id = '.$productionData['moving']);

        DB::delete(DB_PARTNER_TRANSACTIONS, 'proccess = 1 AND proccess_id = '.$productionData['moving'].' AND dyd = '.$dyd);

    }

}

switch($action){

    case 'create':

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', 'Выберите точку, на которой будет выполнено производство.', 1);

        if(!$point_to = DB::escape($_REQUEST['point_to']))
            response('error', 'Выберите точку куда будет осуществляться перемещение.', 1);

        $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$userToken['id'].' AND id = '.$point);

        if(DB::getRecordCount($pointData) == 0)
            response('error', 'Такой точки не существует.', 1);

        $comment = DB::escape($_REQUEST['comment']);

        $date = ($_REQUEST['date']) ? DB::escape($_REQUEST['date']) : time();

        if($date > time())
            response('error', 'Нельзя выбрать дату будущим числом.', 422);

        inventoryCheck($point, $date);
        inventoryCheck($point_to, $date);

        //Создаем экземпляр класса продукции, который сразу списывает состав продукции и добавляет продукцию на склад
        $production_class = new ProductionsClass(false, $userToken['id'], $point, $point_to, $comment, $date, $userToken['employee']);
        $production_class->create();

        /*
        Если точка, которая производит и точка, на которую производится совпадает, 
        то выполняем перемещение
        */
        if($point_to != $point){

            $fields = array('type' => 1,
                            'comment' => 'Производство№'.$production_class->proccess_id,
                            'date' => $date,
                            'partner' => $userToken['id'],
                            'pointFrom' => $point,
                            'pointTo' => $point_to,
                            'created' => time());

            if($userToken['employee'])
                $fields['employee'] = $userToken['employee'];

            $products = json_encode($production_class->products);

            $moving_class = new MovingClass(false, $fields, $products, $userToken['id'], $point_to, $date);
            $moving_class->create();//Отправляем позиции на склад-получатель

            $removal_items = new MovingClass(false, $fields, $products, $userToken['id'], $point, $date);
            $removal_items->proccess_id = $moving_class->proccess_id;
            $removal_items->remove();//Списываем позиции со склада-отправителя

            DB::update(array('moving' => $moving_class->proccess_id), DB_PRODUCTIONS, 'id = '.$production_class->proccess_id);

        }

        response('success', 'Производство выполнено.', 7);

    break;

    case 'products':

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', 'Не выбрана точка.', 1);

          
        $pr_class = new ProductionCostPrice(false, $userToken['id'], $point);

        $archive = '
            AND i.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "item" AND partner_id = '.$userToken['id'].'
            )';

        $products = DB::query('
            SELECT i.id, i.name, i.untils, i.bulk, pi.price AS cost_price
            FROM '.DB_ITEMS.' i
            LEFT JOIN '.DB_POINT_ITEMS.' pi ON pi.item = i.id AND pi.point = '.$point.'
            WHERE i.production = 1 AND (i.partner = '.$userToken['id'].' OR i.partner IS NULL)'.$archive.'
            GROUP BY i.id
            ORDER BY i.name ASC
        ');

        $result = [];

        while($row = DB::getRow($products)){

            if($row['untils'] == 'шт')
                $row['bulk'] = 1;

            $row['count'] = $row['bulk'];
            
            $row['items'] = $pr_class->subItems($row);

           
            $row['price'] = 0;

            $row['available'] = true;

            for($j = 0; $j < sizeof($row['items']); $j++){
                $row['price'] += $row['items'][$j]['count_price'];

                if($row['items'][$j]['price'] == null)
                    $row['available'] = false;

            }

            $row['cost_price'] = round($row['cost_price'], 2);

            $result[] = $row;

        }

        response('success', $result, 1);

    break;

    case 'get':

        $result = [];

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND pr.point = '.$point;

        if($point_to = DB::escape($_REQUEST['point_to']))
            $point_to = ' AND pr.point_to = '.$point_to;

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND (pr.comment LIKE "%'.$search.'%" OR e.name LIKE "%'.$search.'%" OR p.name LIKE "%'.$search.'%" OR i.name LIKE "%'.$search.'%")';

        if($where_category = DB::escape($_REQUEST['category']))
            $where_category = ' AND i.product_category = '.$where_category;

        if($employee = DB::escape($_REQUEST['employee'])){

            $employee = explode(',', $employee);

            for($i = 0; $i < count($employee); $i ++){

                if($employee[$i] == 'admin')
                    $param = ' IS NULL';
                else
                    $param = ' = '.$employee[$i];

                if(!$where)
                    $where = ' AND (pr.employee '.$param;
                else
                    $where .= ' OR pr.employee '.$param;
            }
            if($where)
                $where .= ')'; 
        }

        $sorting = Order::productions(Pages::$field, Pages::$order);

        $productions = DB::query('SELECT pr.id, e.name AS employee, p.name AS point, pr.comment, SUM(pi.count) as products_count, SUM(pi.cost_price) as sum, pr.date,
                                    (SELECT inv.id FROM '.DB_INVENTORY.' inv WHERE (inv.point = pr.point OR inv.point = pr.point_to) AND inv.status = 1 AND inv.date_end >= pr.date LIMIT 1) AS close,
                                    (SELECT SUM(count) FROM '.DB_PRODUCTION_ITEMS.' WHERE production = pr.id) AS total_count
                                        FROM '.DB_PRODUCTIONS.' pr
                                        LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = pr.employee
                                        LEFT JOIN '.DB_PRODUCTION_ITEMS.' pi ON pi.production = pr.id
                                        LEFT JOIN '.DB_ITEMS.' i ON i.id = pi.product
                                        JOIN '.DB_PARTNER_POINTS.' p ON p.id = pr.point
                                        WHERE pr.partner = '.$userToken['id'].' AND pr.date >= '.$from.' AND pr.date < '.$to.$point.$search.$where.$where_category.$point_to.'
                                        GROUP BY pr.id
                                        '.$sorting.'
                                        LIMIT '.Pages::$limit);

        while($row = DB::getRow($productions)){

            $row['employee'] = ($row['employee'] == null) ? 'Администратор' : $row['employee'];
            $row['sum'] = number_format($row['sum'], 2, ',', ' ').' '.CURRENCY;
            $row['close'] = ($row['close'] == null) ? false : true;

            $result[] = $row;
        }

        $page_query = 'SELECT COUNT(t.id) AS count, SUM(t.sum) AS sum, SUM(t.products_count) AS products_count
                        FROM (
                            SELECT pr.id, e.name AS employee, p.name AS point, pr.comment, SUM(pi.count) as products_count, SUM(pi.cost_price) as sum, pr.date
                            FROM '.DB_PRODUCTIONS.' pr
                            LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = pr.employee
                            LEFT JOIN '.DB_PRODUCTION_ITEMS.' pi ON pi.production = pr.id
                            LEFT JOIN '.DB_ITEMS.' i ON i.id = pi.product
                            JOIN '.DB_PARTNER_POINTS.' p ON p.id = pr.point
                            WHERE pr.partner = '.$userToken['id'].' AND pr.date >= '.$from.' AND pr.date < '.$to.$point.$search.$where.$where_category.$point_to.'
                            GROUP BY pr.id
                        ) t';

        $page_data = Pages::GetPageInfo($page_query, $page);
        $page_data['sum'] = number_format($page_data['sum'], 2, ',', ' ').' '.CURRENCY;
        $page_data['products_count'] = number_format($page_data['products_count'], 0, ',', ' ');

        response('success', $result, 1, $page_data);

    break;

    case 'details':

        if(!$production = DB::escape($_REQUEST['production']))
            response('error', 'Не выбрано производство.', 1);

        if($category = DB::escape($_REQUEST['category']))
            $category = ' AND i.product_category = '.$category;
        else
            $category = '';

        $productionData = DB::select('id', DB_PRODUCTIONS, 'id = '.$production.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($productionData) == 0)
            response('error', 'Производство не найдено.', 1);

        $items = DB::query('SELECT i.id, i.name, pri.count, pri.cost_price, i.untils
                                    FROM '.DB_PRODUCTION_ITEMS.' pri
                                    JOIN '.DB_ITEMS.' i ON i.id = pri.product
                                    WHERE pri.production = '.$production.$category.'
                                    LIMIT '.Pages::$limit);

        $result = [];

        while($row = DB::getRow($items)){

            $result[] = array('id' => $row['id'],
                                'name' => $row['name'],
                                'count' => number_format($row['count'], 3, ',', ' ').' '.$row['untils'],
                                'cost_price' => number_format($row['cost_price'], 2, ',', ' ').' '.CURRENCY);

        }

        $page_query = 'SELECT COUNT(i.id) AS count
                        FROM '.DB_PRODUCTION_ITEMS.' pri
                        JOIN '.DB_ITEMS.' i ON i.id = pri.product
                        WHERE pri.production = '.$production.$category;

        $page_data = Pages::GetPageInfo($page_query, $page);

        response('success', $result, 7, $page_data);

    break;

    case 'info':

        if(!$production = DB::escape($_REQUEST['production']))
            response('error', 'Не выбрано производство.', 1);

        $productionData = DB::query('SELECT pr.id, pr.point, pr.point_to, pr.comment, pr.date, p.name, pt.name AS tname
                                            FROM '.DB_PRODUCTIONS.' pr
                                            JOIN '.DB_PARTNER_POINTS.' p ON p.id = pr.point
                                            LEFT JOIN '.DB_PARTNER_POINTS.' pt ON pt.id = pr.point_to
                                            WHERE pr.id = '.$production.' AND pr.partner = '.$userToken['id']);

        if(DB::getRecordCount($productionData) == 0)
            response('error', 'Производство не найдено.', 1);

        $productionData = DB::getRow($productionData);

        $productionData['point'] = array(   'id' => $productionData['point'],
                                            'name' => $productionData['name']);

        unset($productionData['name']);

        $productionData['point_to'] = array('id' => $productionData['point_to'],
                                            'name' => $productionData['tname']);

        unset($productionData['tname']);

        if(!$productionData['point_to']['name'])
            $productionData['point_to'] = $productionData['point'];

        $productionData['products'] = [];

        $products = DB::query('  SELECT i.id, i.name, pp.count, i.bulk, i.untils, pp.cost_price AS sum, (pp.cost_price / pp.count) AS price, pi.price AS cost_price
                                        FROM '.DB_PRODUCTION_ITEMS.' pp
                                        JOIN '.DB_ITEMS.' i ON i.id = pp.product
                                        LEFT JOIN '.DB_POINT_ITEMS.' pi ON pi.item = i.id AND pi.point = '.$productionData['point']['id'].'
                                        WHERE pp.production = '.$production);

        $total_data = array(
            'total_sum' => 0,
            'total_count' => 0
        );
                                        
        while($row = DB::getRow($products)){

            if($row['untils'] == 'шт')
                $row['bulk'] = 1;

            $total_data['total_count'] += $row['count'];
            $total_data['total_sum'] += $row['sum'];
            
            $productionData['products'][] = $row;

        }

        response('success', $productionData, 7, $total_data);

    break;

    case 'edit':

        if(!$production = DB::escape($_REQUEST['production']))
            response('error', 'Не выбрано производство.', 1);
    
        $productionData = DB::select('id, employee, moving, date, point, point_to', DB_PRODUCTIONS, 'id = '.$production.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($productionData) == 0)
            response('error', 'Производство не найдено.', 1);

        $productionData = DB::getRow($productionData);
        inventoryCheck($productionData['point'], $productionData['date']);
        inventoryCheck($productionData['point_to'], $productionData['date']);

        $dyd = date('Ym', $productionData['date']);
        $partner_transactions = DB::select('id', DB_PARTNER_TRANSACTIONS,
            "((proccess_id = {$productionData['id']} AND proccess = 5) OR (proccess_id = {$productionData['moving']} AND proccess = 1)) AND dyd = {$dyd}");
        $partner_transactions = DB::getRecordCount($partner_transactions) ? DB::getColumn('id', $partner_transactions) : [];
        $partner_transactions = implode(',', $partner_transactions);

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', 'Выберите точку, на которой будет выполнено производство.', 1);

        if(!$point_to = DB::escape($_REQUEST['point_to']))
            response('error', 'Выберите точку куда будет осуществляться перемещение.', 1);

        $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$userToken['id'].' AND id = '.$point);

        if(DB::getRecordCount($pointData) == 0)
            response('error', 'Такой точки не существует.', 1);

        $comment = DB::escape($_REQUEST['comment']);

        $date = ($_REQUEST['date']) ? DB::escape($_REQUEST['date']) : time();

        if($date > time())
            response('error', 'Нельзя выбрать дату будущим числом.', 422);

        //Создаем экземпляр класса продукции, который сразу списывает состав продукции и добавляет продукцию на склад
        $production_class = new ProductionsClass(false, $userToken['id'], $point, $point_to, $comment, $date, $productionData['employee']);
        $production_class->edit($productionData['id']);

        /*Если производство выполняется для другой точки и ранее не было создано перемещение, то создаем новое перемещение*/
        if($point_to != $point && !$productionData['moving']){

            $fields = array('type' => 1,
                            'comment' => 'Производство№'.$production_class->proccess_id,
                            'date' => $date,
                            'partner' => $userToken['id'],
                            'pointFrom' => $point,
                            'pointTo' => $point_to,
                            'employee' => $productionData['employee'],
                            'created' => time());

            $products = json_encode($production_class->products);

            $moving_class = new MovingClass(false, $fields, $products, $userToken['id'], $point_to, $date);
            $moving_class->create();//Отправляем позиции на склад-получатель

            $removal_items = new MovingClass(false, $fields, $products, $userToken['id'], $point, $date);
            $removal_items->proccess_id = $moving_class->proccess_id;
            $removal_items->remove();//Списываем позиции со склада-отправителя

            DB::update(['moving' => $moving_class->proccess_id], DB_PRODUCTIONS, "id = {$productionData['id']}");

        }

        /*Если производство выполняется для другой точки и ранее было создано перемещение, то редактируем перемещение*/
        if($point_to != $point && $productionData['moving']){

            $fields = array(
                'date' => $date,
                'pointFrom' => $point,
                'pointTo' => $point_to,
                'employee' => $productionData['employee']
            );

            $products = json_encode($production_class->products);

            $moving_class = new MovingClass(false, $fields, $products, $userToken['id'], $point_to, $date);
            $moving_class->edit($productionData['moving']);

            $removal_items = new MovingClass(false, $fields, $products, $userToken['id'], $point, $date);
            $removal_items->proccess_id = $moving_class->proccess_id;
            $removal_items->remove();//Списываем позиции со склада-отправителя

        }

        /* Если при изменении производства, выбрали одну и ту же точку и при этом ранее было создано перемещение,
         * то удаляем перемещение
         * */
        if($point_to == $point && $productionData['moving']){
            DB::delete(DB_SUPPLIES, 'id = '.$productionData['moving']);
            DB::delete(DB_PARTNER_TRANSACTIONS, 'proccess = 1 AND proccess_id = '.$productionData['moving'].' AND dyd = '.$dyd);
            DB::update(['moving' => 0], DB_PRODUCTIONS, "id = {$productionData['id']}");
        }

        if(!empty($partner_transactions))
            DB::delete(DB_PARTNER_TRANSACTIONS, "id IN ($partner_transactions) AND dyd = {$dyd}");

        response('success', 'Изменения сохранены.', 7);

    break;

    case 'delete':

        if(!$production = DB::escape($_REQUEST['production']))
            response('error', 'Не выбрано производство.', 1);

        DeleteProduction($production, $userToken['id']);
        
        response('success', 'Производство удалено.', 7);

    break;

    //Создает перемещения для тех производств, для которых перемещение не было создано
    case 'repair':

        $productions = DB::select('*', DB_PRODUCTIONS, 'point_to != 0 AND point != point_to AND moving = 0');

        while($row = DB::getRow($productions)){

            $production_items = DB::select('product AS id, count, (cost_price / count) AS price', DB_PRODUCTION_ITEMS, 'production = '.$row['id'].' AND count != 0 AND cost_price != 0');

            $products = [];

            while($item = DB::getRow($production_items))
                $products[] = $item;

            $fields = array('type' => 1,
                            'comment' => 'Производство№'.$row['id'],
                            'date' => $row['date'],
                            'partner' => $row['partner'],
                            'pointFrom' => $row['point'],
                            'pointTo' => $row['point_to'],
                            'created' => time());

            if($row['employee'])
                $fields['employee'] = $row['employee'];

            if(!sizeof($products))
                continue;

            $products = json_encode($products);

            $moving_class = new MovingClass(false, $fields, $products, $row['partner'], $row['point_to'], $row['date']);
            $moving_class->create();//Отправляем позиции на склад-получатель

            $removal_items = new MovingClass(false, $fields, $products, $row['partner'], $row['point'], $row['date']);
            $removal_items->proccess_id = $moving_class->proccess_id;
            $removal_items->remove();//Списываем позиции со склада-отправителя

            DB::update(array('moving' => $moving_class->proccess_id), DB_PRODUCTIONS, 'id = '.$row['id']);

        }

        echo 'ok';
        exit;

    break;

}