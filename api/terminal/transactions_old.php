<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

function StockBalance($employee, $products, $point, $promotions, $partner){

    

    //Если передан список акций
    if($promotions){
        //Убираем экранирование символов
        $promotions = stripslashes($promotions);

        //Преобразуем JSON в объекты
        $promotions = json_decode($promotions, true);

        //Формируем условия выборки, по которым достаем из БД состав акций и количество продуктов в этих акциях
        for($i = 0; $i < sizeof($promotions); $i++){

            if(!$promotionsWhere)
                $promotionsWhere = 'ptc.promotion = '.$promotions[$i]['id'];
            else
                $promotionsWhere .= ' OR ptc.promotion = '.$promotions[$i]['id'];

        }

        $promotionData = DB::query('SELECT ptc.technical_card AS id, ptc.count, ptc.promotion
                                            FROM '.DB_PROMOTION_TECHNICAL_CARDS.' ptc
                                            JOIN '.DB_PROMOTIONS.' AS pr ON pr.id = ptc.promotion
                                            WHERE ('.$promotionsWhere.') AND pr.partner = '.$partner.'
                                            GROUP BY pr.id');

        if(DB::getRecordCount($promotionData) != sizeof($promotions))
            response('error', array('msg' => 'Одна или более акций недействительны.'), '587');

        //Массив товаров уже может объявлен, значит, нужно сделать проверку
        if(!$products)
            $products = [];

        //В цикле высчитываем сколько всего товаров нужно списать с учетом количества акций
        while($row = DB::getRow($promotionData)){
            
            for($i = 0; $i < sizeof($promotions); $i++){
                if($promotions[$i]['id'] == $row['promotion']){
                    $row['count'] *= $promotions[$i]['count'];
                    break;
                }
            }

            //Добавляем в основной массив с товарами
            $products[] = array('id' => $row['id'],
                                'count' => (int)$row['count']);
        }

    }

    for($i = 0; $i < sizeof($products); $i++){

        if(!$products[$i]['id'] || !$products[$i]['count'])
            response('error', array('msg' => 'Объект товаров имеет неверный формат.'), '396');

        if(!$productsWhere)
            $productsWhere = 'pc.technical_card = '.$products[$i]['id'];
        else
            $productsWhere .= ' OR pc.technical_card = '.$products[$i]['id'];

    }
    
    //Получаем список ингредиентов, из которых состоит продукт
    $product_items_query = DB::query('SELECT pc.id, pc.untils, pc.count, pc.item, pc.technical_card, i.bulk, (pc.count / i.bulk * AVG(pi.price)) AS cost_price
                                            FROM '.DB_PRODUCT_COMPOSITION.' pc
                                            LEFT JOIN '.DB_ITEMS.' AS i ON i.id = pc.item
                                            LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = pc.item AND pi.point = '.$point.'
                                            WHERE '.$productsWhere.'
                                            GROUP BY pc.id');

    $product_items = [];

    while($row = DB::getRow($product_items_query)){

        for($i = 0; $i < sizeof($products); $i++){
            if($products[$i]['id'] == $row['technical_card']){
                $row['count'] *= $products[$i]['count'];
                break;
            }
        }

        $product_items[] = $row;

        if(!$items_str)
            $items_str = 'item = '.$row['item'];
        else
            $items_str .= ' OR item = '.$row['item'];
    }

    $items_minus = [];

    //Получаем список ингредиентов на складе
    $point_items = DB::query('SELECT *
                                        FROM '.DB_POINT_ITEMS.'
                                        WHERE ('.$items_str.') AND point = '.$point.' AND count > 0');

    $point_items = DB::makeArray($point_items);

    //Алгоритм, который высчитывает, сколько нужно вычесть со склада того, или иного ингредиента
    for($i = 0; $i < sizeof($product_items); $i++){

        for($j = 0; $j < sizeof($point_items); $j++){

            if($product_items[$i]['item'] == $point_items[$j]['item']){
                
                $minus = $point_items[$j]['count'] - $product_items[$i]['count'];

                if($minus < 0){

                    $minus = 0;

                    $product_items[$i]['count'] -= $point_items[$j]['count'];

                }
                else{
                    $point_items[$j]['minus'] = $minus;
                    break;
                }

                $point_items[$j]['minus'] = $minus;

            }

        }

    }

    //==============Формируем список значений, добавляемых во временную таблицу=================
    for($i = 0; $i < sizeof($point_items); $i++){

        if(isset($point_items[$i]['minus'])){
            if(!$insert_into_table)
                $insert_into_table = '("'.$point_items[$i]['id'].'", "'.$point_items[$i]['minus'].'", "'.$employee.'")';
            else
                $insert_into_table .= ', ("'.$point_items[$i]['id'].'", "'.$point_items[$i]['minus'].'", "'.$employee.'")';
        }

    }
    //==========================================================================================

    return $insert_into_table;

}

function ProductsWhere($products){

    

    for($i = 0; $i < sizeof($products); $i++){

        if(!$products[$i]['id'] || !$products[$i]['count'])
            response('error', array('msg' => 'Объект товаров имеет неверный формат.'), '396');

        if(!$productsWhere)
            $productsWhere = 'pc.technical_card = '.$products[$i]['id'];
        else
            $productsWhere .= ' OR pc.technical_card = '.$products[$i]['id'];

    }

    return $productsWhere;

}

function TransactionCreate($products, $partner, $point, $employee, $shift, $type, $balance, $sale, $percent, $phone, $promotions){

    

    $date = time();
    $date_time = date('Y-m-d H:i:s', $date);
    $sum = 0;
    $profit = 0;
    $cost_price = 0;

    //Если есть список товаров
    if($products){
        for($i = 0; $i < sizeof($products); $i++){

            if(!$products[$i]['count'] || !$products[$i]['id'] || !$products[$i]['cost_price'] || !$products[$i]['price'] || !$products[$i]['name'] || !$products[$i]['product'])
                response('error', array('msg' => 'Содержание чека имеет неверный формат.'), '397');

            $minimal_cost_price = $products[$i]['price'] * 0.33;

            if($products[$i]['cost_price'] < $minimal_cost_price)
                $products[$i]['cost_price'] = $minimal_cost_price;

            $sum += $products[$i]['price'] * $products[$i]['count'];
            $cost_price += $products[$i]['cost_price'] * $products[$i]['count'];

        }
    }

    //Если в транзакции присутстсвуют акции
    if($promotions){

        $promotions = stripslashes($promotions);

        $promotions = json_decode($promotions, true);

        if($promotions == null || sizeof($promotions) == 0)
            response('error', array('msg' => 'Содержание чека имеет неверный формат.'), '397');

        for($i = 0; $i < sizeof($promotions); $i++){

            if(!$promotions[$i]['id'] || !$promotions[$i]['count'])
                response('error', array('msg' => 'Объект акций имеет неверный формат.'), '566');

            if(!$where)
                $where = 'pr.id = '.$promotions[$i]['id'];
            else
                $where .= ' OR pr.id = '.$promotions[$i]['id'];

        }

        $promotionData = DB::query('SELECT pr.id, pr.price, pr.name, GROUP_CONCAT(CONCAT(p.name, "|",  tc.bulk_value, " ", tc.bulk_untils, "|", ptc.count) SEPARATOR "||") AS composition
                                            FROM '.DB_PROMOTIONS.' AS pr
                                            JOIN '.DB_PROMOTION_TECHNICAL_CARDS.' AS ptc ON ptc.promotion = pr.id
                                            JOIN '.DB_TECHNICAL_CARD.' AS tc ON ptc.technical_card = tc.id
                                            JOIN '.DB_PRODUCTS.' AS p ON p.id = tc.product
                                            WHERE '.$where.'
                                            GROUP BY pr.id');

        while($row = DB::getRow($promotionData)){

            for($i = 0; $i < sizeof($promotions); $i++){

                if($promotions[$i]['id'] == $row['id']){

                    $total = $promotions[$i]['count'] * $row['price'];

                    $sum += $total;

                    $promotions[$i]['price'] = $row['price'];
                    $promotions[$i]['name'] = $row['name'];
                    $promotions[$i]['composition'] = $row['composition'];

                }

            }
        }

    }

    $total = $sum;

    $total -= $total * $sale / 100;

    //Если оплата бонусами
    if($type == 2){
        //Если баланс меньше суммы покупки или равен ей, то необходимо вычесть все средства с баланса для частичного погашения покупки
        if($balance <= $sum){
            $points = $balance * -1;
            $total = $sum - $balance;
        }
        else{
            //Иначе, если баланс бонусов превышает сумму покупки, то сумма покупки становится равной нулю
            $points = $sum * -1;
            $total = 0;
        }
        //После создания транзакции, с баланса клиента баллы списываются с помощью триггеров в таблице `app_transactions`

    }
    else
        $points = $total * $percent / 100;

    //Формируем поля, для создания самой транзакции
    $fields = array('partner' => $partner,
                    'point' => $point,
                    'client_phone' => $phone,
                    'employee' => $employee,
                    'shift' => $shift,
                    'created' => $date,
                    'created_datetime' => $date_time,
                    'sum' => $sum,
                    'total' => $total,
                    'discount' => $sale,
                    'points' => $points,
                    'cost_price' => $cost_price,
                    'profit' => $total - $cost_price,
                    'type' => $type);

    if(!$transaction = DB::insert($fields, DB_TRANSACTIONS))
        response('error', '', '503');

    //Если есть список продуктов
    if($products){

        if($points < 0){
            $points *= -1;
            $item_points = $points / sizeof($products);
        }
        else
            $item_points = 0;

        for($i = 0; $i < sizeof($products); $i++){

            $total = $products[$i]['count'] * $products[$i]['price'];
            $cost_price = $products[$i]['count'] * $products[$i]['cost_price'];
            $profit = $total - $cost_price;

            if(!$items_insert)
                $items_insert = '("'.$transaction.'", "'.$products[$i]['id'].'", "'.$products[$i]['product'].'", "'.$products[$i]['name'].'", "'.$products[$i]['bulk'].'", "'.$products[$i]['count'].'", "'.$products[$i]['price'].'", "'.$total.'", "'.$cost_price.'", "'.$profit.'", NULL, "", "", 0, "'.$sale.'", "'.$item_points.'")';
            else
                $items_insert .= ', ("'.$transaction.'", "'.$products[$i]['id'].'", "'.$products[$i]['product'].'", "'.$products[$i]['name'].'", "'.$products[$i]['bulk'].'", "'.$products[$i]['count'].'", "'.$products[$i]['price'].'", "'.$total.'", "'.$cost_price.'", "'.$profit.'", NULL, "", "", 0, "'.$sale.'", "'.$item_points.'")';

        }
    }

    //Если есть список акций
    if($promotions){

        for($i = 0; $i < sizeof($promotions); $i++){
            $total = $promotions[$i]['count'] * $promotions[$i]['price'];
                    
            if(!$items_insert)
                $items_insert = '("'.$transaction.'", NULL, NULL, "", "", "'.$promotions[$i]['count'].'", "'.$promotions[$i]['price'].'", "'.$total.'", 0, 0, "'.$promotions[$i]['id'].'", "'.$promotions[$i]['name'].'", "'.$promotions[$i]['composition'].'", 1, "'.$sale.'", 0)';
            else
                $items_insert .= ',("'.$transaction.'", NULL, NULL, "", "", "'.$promotions[$i]['count'].'", "'.$promotions[$i]['price'].'", "'.$total.'", 0, 0, "'.$promotions[$i]['id'].'", "'.$promotions[$i]['name'].'", "'.$promotions[$i]['composition'].'", 1, "'.$sale.'", 0)';

        }

    }

    if(!DB::query('INSERT INTO '.DB_TRANSACTION_ITEMS.' (transaction, technical_card, product, name, bulk, count, price, total, cost_price, profit, promotion, promotion_name, promotion_composition, type, discount, points) VALUES '.$items_insert)){
        DB::delete(DB_TRANSACTIONS, 'id = '.$transaction);
        response('error', '', '503');
    }

}

switch($action){

    case 'create':

        $balance = 0;
        $sale = 0;

        if($phone = DB::escape($_REQUEST['phone'])){

            $clientData = DB::select('balance, sale', DB_CLIENTS, 'phone = "'.$phone.'"');

            if(DB::getRecordCount($clientData) == 0)
                response('error', array('msg'=>'Пользователь не зарегистрирован в программе бонусов.'), '525');

            list($balance, $sale) = DB::getRow($clientData, 'num');

            if($sale > 0)
                $balance = 0;

        }

        $percent = (DB::escape($_REQUEST['percent'])) ? DB::escape($_REQUEST['percent']) : 0;

        if(!$employee_id = DB::escape($_REQUEST['employee']))
            response('error', array('msg' => 'Не передан ID сотрудника.'), '512');

        if(!$shift_id = DB::escape($_REQUEST['shift']))
            response('error', array('msg' => 'Не передан ID смены.'), '513');

        $shiftData = DB::select('id, shift_closed', DB_EMPLOYEE_SHIFTS, 'id = "'.$shift_id.'" AND employee = '.$employee_id);

        if(DB::getRecordCount($shiftData) == 0)
            response('error', array('msg' => 'Смена не найдена.'), '514');

        if(DB::escape($_REQUEST['type']) == 0 || !DB::escape($_REQUEST['type']))
            $type = 0;
        if(DB::escape($_REQUEST['type']) == 1)
            $type = 1;
        if(DB::escape($_REQUEST['type']) == 2){
            
            $type = 2;

            if($balance <= 0)
                response('error', array('msg' => 'На балансе недостаточно баллов.'), '526');

        }

        $shiftData = DB::getRow($shiftData);

        if($shiftData['shift_closed'] == 1)
            response('error', array('msg' => 'Смена закрыта, откройте новую смену.'), '515');

        //Если пришел список товаров
        if($products = DB::escape($_REQUEST['products'])){

            $products = stripslashes($products);

            $products = json_decode($products, true);

            if($products == null || sizeof($products) == 0)
                response('error', array('msg' => 'Объект товаров имеет неверный формат.'), '396');

            $products_where = ProductsWhere($products);

            //Запрос на подсчет себестоимости товаров исходя из того, что есть на складе
            $technical_cards_query = DB::query('SELECT t.id, SUM(t.cost_price) AS cost_price, p.name, tc.product, CONCAT(tc.bulk_value, " ", tc.bulk_untils) AS bulk
                                                        FROM (SELECT (SUM(pi.count) - pc.count) AS dif, AVG(pi.price), pc.count, pc.technical_card AS id, (pc.count / i.bulk * AVG(pi.price)) AS cost_price
                                                                FROM '.DB_PRODUCT_COMPOSITION.' pc
                                                                JOIN '.DB_ITEMS.' AS i ON pc.item = i.id
                                                                LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = pc.item AND pi.point = '.$pointToken['id'].'
                                                                WHERE '.$products_where.'
                                                                GROUP BY pc.id) t
                                                        JOIN '.DB_TECHNICAL_CARD.' AS tc ON tc.id = t.id
                                                        JOIN '.DB_PRODUCTS.' AS p ON p.id = tc.product
                                                        GROUP BY t.id');

            while($row = DB::getRow($technical_cards_query)){

                for($i = 0; $i < sizeof($products); $i++){

                    if($products[$i]['id'] == $row['id']){

                        if($row['cost_price'] == null || $row['cost_price'] <= 0)
                            $row['cost_price'] = 1;
                        $products[$i]['bulk'] = $row['bulk'];
                        $products[$i]['cost_price'] = $row['cost_price'];
                        $products[$i]['product'] = $row['product'];
                        $products[$i]['name'] = $row['name'];
                        break;
                    }

                }

            }
        }

        //Получение акций
        $promotions = DB::escape($_REQUEST['promotions']) ? DB::escape($_REQUEST['promotions']) : '';

        //Создание транзакции
        TransactionCreate($products, $pointToken['partner'], $pointToken['id'], $employee_id, $shift_id, $type, $balance, $sale, $percent, $phone, $promotions);

        //Функция для рассчета списаний со склада в связи с продажей товара(ов)
        $insert_into_table = StockBalance($employee_id, $products, $pointToken['id'], $promotions, $pointToken['partner']);

        if($insert_into_table){
            //Добавляем во временную таблицу данные
            DB::query('INSERT INTO '.DB_POINT_ITEMS_TMP.' (position, count, employee) VALUES '.$insert_into_table);
            
            //Переносим из временной таблицы в текущую
            DB::query('UPDATE `app_point_items`
                            SET `app_point_items`.`count` = (SELECT '.DB_POINT_ITEMS_TMP.'.`count`
                                                            FROM '.DB_POINT_ITEMS_TMP.'
                                                            WHERE '.DB_POINT_ITEMS_TMP.'.`position` = `app_point_items`.`id`)
                            WHERE `app_point_items`.`id` = (SELECT '.DB_POINT_ITEMS_TMP.'.`position`
                                                            FROM '.DB_POINT_ITEMS_TMP.'
                                                            WHERE '.DB_POINT_ITEMS_TMP.'.`employee` = '.$employee_id.' AND '.DB_POINT_ITEMS_TMP.'.`position` = `app_point_items`.`id` AND '.DB_POINT_ITEMS_TMP.'.`type` = 0)');

            //Удаляем записи из временной таблицы
            DB::delete(DB_POINT_ITEMS_TMP, 'employee = '.$employee_id.' AND type = 0');
        }

        response('success', array('msg' => 'Транзакция создана.'), '632');

    break;

}