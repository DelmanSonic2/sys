<?php
use Support\Pages;

use Support\DB;

include 'tokenCheck.php';
include ROOT.'api/lib/functions.php';

require ROOT.'api/classes/PromotionGiftsClass.php';

switch($action){

    case 'get':

        $promotions = DB::query('SELECT pr.id, pr.name, pr.description, pr.image, pr.enable, pr.price, pr.created
                                        FROM '.DB_PROMOTIONS.' pr
                                        WHERE partner = '.$pointToken['partner'].' AND FIND_IN_SET("'.$pointToken['id'].'", points) AND (SELECT COUNT(ptc.id) FROM '.DB_PROMOTION_TECHNICAL_CARDS.' ptc WHERE ptc.promotion = pr.id) > 0
                                        ORDER BY created DESC');

        $result = [];

        while($row = DB::getRow($promotions)){

            if(!$where)
                $where = 'pr.promotion = '.$row['id'];
            else
                $where .= ' OR pr.promotion = '.$row['id'];

            //Если изображения нет, то ставим заглушку
            $image = ($row['image'] == '') ? PLACEHOLDER_IMAGE: $row['image'];

            //Обрезка фото
            $row['image'] = ImageResize($image, 130, 130);
            $row['products'] = [];

            $result[] = $row;

        }

        if($where){
            $promotion_items = DB::query('SELECT tc.id, p.name, tc.subname, tc.bulk_value, tc.bulk_untils, pr.count, pr.promotion
                                                FROM '.DB_PROMOTION_TECHNICAL_CARDS.' pr
                                                JOIN '.DB_TECHNICAL_CARD.' AS tc ON pr.technical_card = tc.id
                                                JOIN '.DB_PRODUCTS.' AS p ON p.id = tc.product
                                                WHERE '.$where);
                                 
            while($row = DB::getRow($promotion_items)){
                for($i = 0; $i < sizeof($result); $i++){

                    if($result[$i]['id'] == $row['promotion'])
                        $result[$i]['products'][] = array('id' => $row['id'],
                                                        'technical_card' => $row['id'],
                                                        'name' => $row['name'],
                                                        'subname' => $row['subname'],
                                                        'untils' => $row['bulk_value'].' '.$row['bulk_untils'],
                                                        'count' => $row['count']);

                }
            }

        }

        response('success', $result, '7');

    break;

    case 'code':

        $where = [];

        if(!$code = DB::escape($_REQUEST['code']))
            response('error', 'Введите промокод.', 1);

        $code_data = DB::select('code, percent, coupon_amount, weekdays, used, categories, products, technical_cards, promotions', DB_PROMOTIONAL_CODES, 'code LIKE "'.$code.'"');

        if(DB::getRecordCount($code_data) == 0)
            response('error', 'Введен несуществующий промокод.', 1);

        $code_data = DB::getRow($code_data);

        if($code_data['used'] == 1)
            response('error', 'Введенный промокод уже был использован.', 1);

        $weekdays_names = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];

        //Получаем текущий день недели
        $weekday = date('N', time()) - 1;

        $weekdays = ($code_data['weekdays'] == '') ? [] : explode(',', $code_data['weekdays']);
        $error_msg = 'Промокод отключен.';

        $promotion_enable = false;

        for($i = 0; $i < sizeof($weekdays); $i++){

            if($weekday == $weekdays[$i])
                $promotion_enable = true;

            for($j = 0; $j < sizeof($weekdays_names); $j++){

                if($j == $weekdays[$i]){
                    if(!$error_msg)
                        $error_msg = 'Промокод действует в следующие дни недели: '.$weekdays_names[$j];
                    else
                        $error_msg .= ', '.$weekdays_names[$j];
                }

            }

        }

        if(!$promotion_enable)
            response('error', $error_msg, 1);

        $result = array('code' => $code_data['code'],
                        'percent' => $code_data['percent'],
                        'coupon_amount' => $code_data['coupon_amount'],
                        'technical_cards' => [],
                        'promotions' => []);

        if($code_data['categories']){

            $categories = [];

            $categories_data = DB::query('
                SELECT *
                FROM '.DB_PRODUCT_CATEGORIES.'
                WHERE (partner = '.$pointToken['partner'].' OR partner IS NULL) AND FIND_IN_SET(id, (
                SELECT GROUP_CONCAT(Level SEPARATOR ",") FROM (
                    SELECT @Ids := (
                        SELECT GROUP_CONCAT(id SEPARATOR ",")
                        FROM '.DB_PRODUCT_CATEGORIES.'
                        WHERE (partner = '.$pointToken['partner'].' OR partner IS NULL) AND (FIND_IN_SET(parent, @Ids) OR FIND_IN_SET(id, @Ids))
                    ) Level
                    FROM '.DB_PRODUCT_CATEGORIES.'
                    JOIN (SELECT @Ids := "'.$code_data['categories'].'") temp1
                ) temp2
                ))
            ');

            while($row = DB::getRow($categories_data))
                $categories[] = $row['id'];

            $where[] = 'FIND_IN_SET(p.category, "'.implode(',', $categories).'")';

        }

        if($code_data['products']) $where[] = 'FIND_IN_SET(tc.product, "'.$code_data['products'].'")';
        if($code_data['technical_cards']) $where[] = 'FIND_IN_SET(tc.id, "'.$code_data['technical_cards'].'")';

        $where = sizeof($where) ? ' AND ('.implode(' OR ', $where).')' : '';

        $data = DB::query('
            SELECT tc.id, p.name, tc.subname, tc.bulk_value, tc.bulk_untils
            FROM '.DB_PRODUCTS.' p
            JOIN '.DB_TECHNICAL_CARD.' tc ON tc.product = p.id
            WHERE (tc.partner = '.$pointToken['partner'].' OR tc.partner IS NULL)'.$where
        );

        while($row = DB::getRow($data)){
            
            $name = ($row['subname'] == '') ? ($row['name'].' '.$row['bulk_value'].' '.$row['bulk_untils']) : ($row['name'].' ('.$row['subname'].') '.$row['bulk_value'].' '.$row['bulk_untils']);
            $result['technical_cards'][] = array('id' => $row['id'],
                                                'name' => $name);

        }

        response('success', $result, 7);

    break;

    case 'gifts':

        if(!$phone = DB::escape($_REQUEST['phone']))
            response('error', 'Укажите номер клиента.', 422);

        if(!$promotion_gifts = DB::escape($_REQUEST['promotion_gifts']))
            response('error', 'Выберите подарочные позиции.', 422);
        
        if(!$products = DB::escape($_REQUEST['products']))
            response('error', 'Укажите товары.', 422);

        $promotion_class = new PromotionGifts(false, $phone, $products, $promotion_gifts);

        $result = $promotion_class->parse('gifts')->parse('products')->client(false)->gifts()->result;

        response($result['status'], $result['msg'], $result['code']);

    break;

}