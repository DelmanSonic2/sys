<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'add':

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', 'Укажите название акции.', 422);

        if(!isset($_REQUEST['type']))
            //response('error', 'Укажите тип акции (по тех. картам / по категориям).', 422);
            $type = 0;
        else
            $type = DB::escape($_REQUEST['type']);

        if((!$type && !$technical_cards = DB::escape($_REQUEST['technical_cards']))
        || ($type && !$categories = DB::escape($_REQUEST['categories'])))
            response('error', 'Заполните данные.', 422);

        if(!isset($_REQUEST['weekdays']))
            response('error', 'Укажите дни недели, в которые работает акция.', 422);

        $weekdays = DB::escape($_REQUEST['weekdays']);

        $weekdays_arr = explode(',', $weekdays);

        foreach($weekdays_arr as $weekday){
            if($weekday < 0 || $weekday > 6)
                response('error', 'Указан несуществующий день недели.', 1);
        }

        if(!$discount = DB::escape($_REQUEST['discount']))
            response('error', 'Укажите процент скидки.', 422);

        if($discount < 0 || $discount > 100)
            response('error', 'Размер скидки должен быть в диапазоне от 0 до 100.', 422);

        if(!$points = DB::escape($_REQUEST['points']))
            response('error', 'Укажите заведения, на которых должны работать скидки.', 422);

        $points_exist = DB::select('GROUP_CONCAT(id) AS id', DB_PARTNER_POINTS, 'FIND_IN_SET(id, "'.$points.'") AND partner = '.$userToken['id']);
        $points_exist = DB::getRow($points_exist);
        
        if(!$time_from = DB::escape($_REQUEST['time_from']))
            response('error', 'Укажите время, с которого начинает работать скидка.', 422);

        if(!$time_to = DB::escape($_REQUEST['time_to']))
            response('error', 'Укажите время, до которого начинает работать скидка.', 422);

        if (!preg_match('/^([0-1][0-9]|[2][0-3]):([0-5][0-9])$/', $time_from) || !preg_match('/^([0-1][0-9]|[2][0-3]):([0-5][0-9])$/', $time_to))
            response('error', 'Время введено неправильно.', 422);

        if(!isset($_REQUEST['enable']))
            response('error', 'Не передано поле enable.', 422);

        $fields = array(
            'name' => $name,
            'points' => $points_exist['id'],
            'partner' => $userToken['id'],
            'technical_cards' => $technical_cards,
            'categories' => $categories,
            'type' => $type,
            'time_from' => $time_from,
            'time_to' => $time_to,
            'weekdays' => $weekdays,
            'discount' => $discount,
            'updated' => time(),
            'created' => time(),
            'enable' => DB::escape($_REQUEST['enable'])
        );

        if(DB::insert($fields, DB_TECHNICAL_CARD_DISCOUNT))
            response('success', 'Скидка добавлена.', 201);

        response('error', '', 422);

    break;

    case 'get':

        $result = [];

        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND name LIKE "%'.$search.'%"';

        if(isset($_REQUEST['active']))
            $active = ' AND enable = '.DB::escape($_REQUEST['active']);

        $ORDER_BY = Order::marketing_time_discount(Pages::$field, Pages::$order);

        $time_discounts = DB::select('id, name, time_from, time_to, discount, enable, updated, created', DB_TECHNICAL_CARD_DISCOUNT, 'partner = '.$userToken['id'].$search.$active, $ORDER_BY, $limit);

        while($row = DB::getRow($time_discounts)){
            $row['enable'] = (bool)$row['enable'];
            $result[] = $row;
        }

        $page_query = '
            SELECT COUNT(id) AS count
            FROM '.DB_TECHNICAL_CARD_DISCOUNT.'
            WHERE partner = '.$userToken['id'].$search.$active.'
        ';

        $page_data = Pages::GetPageInfo($page_query, $page);

        response('success', $result, 200, $page_data);

    break;

    case 'info':

        if(!$promotion = DB::escape($_REQUEST['promotion']))
            response('error', 'Не передан ID скидки.', 422);

        $promotion_data = DB::query('
            SELECT id, name, points, type, technical_cards, categories, time_from, time_to, weekdays, discount, enable, updated, created
            FROM '.DB_TECHNICAL_CARD_DISCOUNT.'
            WHERE id = '.$promotion.' AND partner = '.$userToken['id'].'
            LIMIT 1
        ');
        
        if(DB::getRecordCount($promotion_data) == 0)
            response('error', 'Скидка не найдена.', 422);

        $promotion_data = DB::getRow($promotion_data);

        $promotion_data['enable'] = (bool)$promotion_data['enable'];

        $points = DB::select('id, name', DB_PARTNER_POINTS, 'FIND_IN_SET(id, "'.$promotion_data['points'].'")');
        $promotion_data['points'] = DB::makeArray($points);

        $technical_cards = DB::query('
            SELECT tc.id
            FROM '.DB_PRODUCTS.' p
            JOIN '.DB_TECHNICAL_CARD.' tc ON tc.product = p.id
            WHERE FIND_IN_SET(tc.id, "'.$promotion_data['technical_cards'].'")
        ');
        $promotion_data['technical_cards'] = [];
        while($row = DB::getRow($technical_cards))
            $promotion_data['technical_cards'][] = $row['id'];

        $categories = DB::query('
            SELECT id
            FROM '.DB_PRODUCT_CATEGORIES.'
            WHERE FIND_IN_SET(id, "'.$promotion_data['categories'].'")
        ');
        $promotion_data['categories'] = [];
        while($row = DB::getRow($categories))
            $promotion_data['categories'][] = $row['id'];

        response('success', $promotion_data, 200);

    break;

    case 'edit':

        $fields = array(
            'updated' => time()
        );

        if(!$promotion = DB::escape($_REQUEST['promotion']))
            response('error', 'Не передан ID скидки.', 422);

        $promotion_data = DB::select('id', DB_TECHNICAL_CARD_DISCOUNT, 'id = '.$promotion.' AND partner = '.$userToken['id'], '', 1);

        if(!DB::getRecordCount($promotion_data))
            response('error', 'Скидка не найдена.', 422);

        if(isset($_REQUEST['name']))
            $fields['name'] = DB::escape($_REQUEST['name']);

        if(!isset($_REQUEST['type']))
            $fields['type'] = 0;
        else
            $fields['type']  = DB::escape($_REQUEST['type']);

        if(!$fields['type'] && !isset($_REQUEST['technical_cards']))
            response('error', 'Укажите тех. карты.', 422);

        if(($fields['type'] && !isset($_REQUEST['categories'])))
            response('error', 'Укажите категории.', 422);

        if(isset($_REQUEST['technical_cards']) && !$fields['type']){
            $fields['technical_cards']  = DB::escape($_REQUEST['technical_cards']);
            $fields['categories'] = null;
        }
        else if(isset($_REQUEST['categories']) && $fields['type']){
            $fields['categories']  = DB::escape($_REQUEST['categories']);
            $fields['technical_cards'] = null;
        }

        if(isset($_REQUEST['weekdays'])){

            $weekdays = DB::escape($_REQUEST['weekdays']);
            $weekdays_arr = explode(',', $weekdays);

            foreach($weekdays_arr as $weekday){
                if($weekday < 0 || $weekday > 6)
                    response('error', 'Указан несуществующий день недели.', 1);
            }

            $fields['weekdays'] = $weekdays;

        }

        if(isset($_REQUEST['discount'])){
            $discount = DB::escape($_REQUEST['discount']);
            if($discount < 0 || $discount > 100)
                response('error', 'Размер скидки должен быть в диапазоне от 0 до 100.', 422);
            
            $fields['discount'] = $discount;

        }

        if(isset($_REQUEST['points'])){
            $points = DB::escape($_REQUEST['points']);
            $points_exist = DB::select('GROUP_CONCAT(id) AS id', DB_PARTNER_POINTS, 'FIND_IN_SET(id, "'.$points.'") AND partner = '.$userToken['id']);
            $fields['points'] = DB::getRow($points_exist)['id'];

        }

        if(isset($_REQUEST['time_from'])){

            $time_from = DB::escape($_REQUEST['time_from']);
            if(!preg_match('/^([0-1][0-9]|[2][0-3]):([0-5][0-9])$/', $time_from))
                response('error', 'Время введено неправильно.', 422);

            $fields['time_from'] = $time_from;

        }

        if(isset($_REQUEST['time_to'])){

            $time_to = DB::escape($_REQUEST['time_to']);
            if(!preg_match('/^([0-1][0-9]|[2][0-3]):([0-5][0-9])$/', $time_to))
                response('error', 'Время введено неправильно.', 422);

            $fields['time_to'] = $time_to;

        }

        if(isset($_REQUEST['enable']))
            $fields['enable'] = (bool)DB::escape($_REQUEST['enable']);
        
        DB::update($fields, DB_TECHNICAL_CARD_DISCOUNT, 'id = '.$promotion);

        response('success', 'Изменения сохранены.', 200);

    break;

}