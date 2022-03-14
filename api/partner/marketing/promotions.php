<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/lib/functions.php';
require ROOT.'api/classes/OrderClass.php';

//Проверяет массив товаров на привязку к партнеру, возвращает строку для добавление товаров, участвующих в акции
function ProductsValidate($technical_cards, $promotion, $user){

    

    for($i = 0; $i < sizeof($technical_cards); $i++){

        if(!$technical_cards[$i]['id'] || !$technical_cards[$i]['count'])
            return false;

        if(!$where){
            $where = 'id = '.$technical_cards[$i]['id'];
            $insert = '("'.$promotion.'", "'.$technical_cards[$i]['id'].'", "'.$technical_cards[$i]['count'].'")';
        }
        else{
            $where .= ' OR id = '.$technical_cards[$i]['id'];
            $insert .= ', ("'.$promotion.'", "'.$technical_cards[$i]['id'].'", "'.$technical_cards[$i]['count'].'")';
        }

    }

    $technical_cards_data = DB::select('id', DB_TECHNICAL_CARD, '('.$where.') AND (partner = '.$user.' OR partner IS NULL)');

    if(DB::getRecordCount($technical_cards_data) != sizeof($technical_cards))
        return false;

    return $insert;

}

function MenuPromotions($show_everywhere){

    

    $points = DB::escape($_REQUEST['points']);

    $points = stripslashes($points);

    $points = json_decode($points, true);

    if($points == null || sizeof($points) == 0)
        $points_raw = '';
    else{

        for($i = 0; $i < sizeof($points); $i++){

            if($points[$i]['enable'] === false)
                continue;

            if(!$points_raw)
                $points_raw = $points[$i]['id'];
            else
                $points_raw .= ','.$points[$i]['id'];

        }

    }

    return $points_raw;

}

switch($action){

    case 'create':

        //Получаем параметры для создания акции        
        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите название акции.'), '534');

        $fields = array('name' => $name,
                        'created' => time(),
                        'partner' => $userToken['id']);

        if($description = DB::escape($_REQUEST['description']))
            $fields['description'] = $description;

        //$fields['enable'] = (DB::escape($_REQUEST['enable'])) ? 1 : 0;

        $price = DB::escape($_REQUEST['price']);

        if($price < 0)
            response('error', array('msg' => 'Цена не может быть отрицательной.'), '538');

        $fields['price'] = $price;

        if(!$technical_cards = DB::escape($_REQUEST['technical_cards']))
            response('error', array('msg' => 'Выберите товары, которые учавствуют в акции.'), '536');

        //Убираем экранирование символов
        $technical_cards = stripslashes($technical_cards);

        //Преобразуем JSON строку в объекты
        $technical_cards = json_decode($technical_cards, true);

        if($technical_cards == null || sizeof($technical_cards) == 0)
            response('error', array('msg' => 'Объект с тех. картами имеет неверный формат.'), '537');

        if($image = $_FILES['image']){

            $file = FileLoad('promotions/'.hash('md5', $userToken['id'].'promotions'), $image, hash('md5', $userToken['id'].'image'.time()));

            $fields['image'] = $file['link'];

        }

        if(!$promotion = DB::insert($fields, DB_PROMOTIONS))
            response('error', '', '503');

        //Получаем флаг о том, что она активна везде или нет
        $show_everywhere = DB::escape($show_everywhere);
        //Формируем список точек, где она активна
        $points = MenuPromotions($show_everywhere);
        //Обновляем информацию о точках
        DB::update(array('points' => $points, 'show_everywhere' => $show_everywhere), DB_PROMOTIONS, 'id = '.$promotion);

        //Проверяем тех. карты на наличие, формируем запрос на добавление продуктов в акцию
        $insert = ProductsValidate($technical_cards, $promotion, $userToken['id']);

        //Если одна или болле тех. карт отсутствуют, возвращаем ошибку, удаляем акцию
        if(!$insert){
            DB::delete(DB_PROMOTIONS, 'id = '.$promotion);
            response('error', array('msg' => 'Проверьте составленный Вами список товаров.'), '556');
        }

        if($promotion_items = DB::query('INSERT INTO '.DB_PROMOTION_TECHNICAL_CARDS.' (promotion, technical_card, count) VALUES '.$insert))
            response('success', array('msg' => 'Акция создана.'), '640');

        else{
            DB::delete(DB_PROMOTIONS, 'id = '.$promotion);
            response('error', '', '503');
        }

    break;

    case 'get':

        //Поиск по названию и описанию акции
        if($search = DB::escape($_REQUEST['search']))
            $search = ' AND (p.name LIKE "%'.$search.'%" OR p.description LIKE "%'.$search.'%")';

        //Если active равен 2, то формируем условие, что должны вернуться только активные акции, иначе неактивные
        if($active = DB::escape($_REQUEST['active'])){
            $active = ($active == 2) ? ' AND p.points != ""' : ' AND p.points = ""';
            //$active = ' AND p.enable = '.$enable;
        }

        $ORDER_BY = Order::marketing_promotions(Pages::$field, Pages::$order);

        //$promotions = DB::select('id, name, description, image, enable, price, created', DB_PROMOTIONS, 'partner = '.$userToken['id'].$search.$active, 'created DESC', $limit);
        $promotions = DB::query('SELECT p.id, p.name, p.description, p.image, p.points AS enable, p.price, p.created
                                        FROM '.DB_PROMOTIONS.' p
                                        WHERE p.partner = '.$userToken['id'].$search.$active.'
                                        '.$ORDER_BY.'
                                        LIMIT '.Pages::$limit);
        $result = [];

        while($row = DB::getRow($promotions)){

            //Если изображения нет, то ставим заглушку
            $image = ($row['image'] == '') ? PLACEHOLDER_IMAGE: $row['image'];

            $row['enable'] = ($row['enable'] == '') ? 0 : 1;

            //Обрезка фото
            $row['image'] = ImageResize($image, 130, 130);

            $result[] = $row;

        }

        //Получаем информацию о том, сколько акций у текущего партнера всего
        $pages = DB::query('SELECT COUNT(p.id) AS count
                                    FROM '.DB_PROMOTIONS.' p
                                    WHERE p.partner = '.$userToken['id'].$search.$active);

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

    case 'products':

        $result = [];

        //Получаем список тех. карт
        $technical_cards = DB::query('SELECT tc.id, CONCAT(p.name, ", ", tc.bulk_value, " ", tc.bulk_untils) AS name
                                            FROM '.DB_TECHNICAL_CARD.' tc
                                            JOIN '.DB_PRODUCTS.' AS p ON p.id = tc.product
                                            WHERE tc.partner = '.$userToken['id'].' OR tc.partner IS NULL
                                            ORDER BY p.name, tc.bulk_value');

        $technical_cards = DB::makeArray($technical_cards);

        /*
        //Получаем список категорий и подкатегорий
        $categories = DB::select('id, name, parent', DB_PRODUCT_CATEGORIES, 'partner = '.$userToken['id'].' OR partner IS NULL', 'name');

        while($row = DB::getRow($categories)){

            $row['items'] = [];

            //Если тех. карта есть в такой-то категории, добавляем в массив элементов тех.карту
            for($i = 0; $i < sizeof($technical_cards); $i++){
                if($technical_cards[$i]['category'] == $row['id'])
                    $row['items'][] = $technical_cards[$i];
            }

            $row['childs'] = [];
    
            $result[$row['parent']][$row['id']] =  $row;

        }
    
        //Формируем дерево
        $result = CategoriesTree($result, null);*/

        response('success', $technical_cards, '7');
    break;

    case 'info':

        if(!$promotion = DB::escape($_REQUEST['promotion']))
            response('error', array('msg' => 'Акция не выбрана.'), '557');

        $promotionData = DB::select('id, name, description, image, enable, price, show_everywhere, points', DB_PROMOTIONS, 'partner = '.$userToken['id'].' AND id = '.$promotion);

        if(DB::getRecordCount($promotionData) == 0)
            response('error', array('msg' => 'Акция не найдена.'), '558');

        $result = DB::getRow($promotionData);

        $result['show_everywhere'] = (bool)$result['show_everywhere'];

        $points = DB::select('id, name', DB_PARTNER_POINTS, 'partner = '.$userToken['id']);

        $result['points'] = ($result['points'] == '') ? array() : explode(',', $result['points']);
        
        //Ищем какие точки были добавлены
        while($row = DB::getRow($points)){

            $flag = false;

            for($i = 0; $i < sizeof($result['points']); $i++){

                if($result['points'][$i] == $row['id']){

                    $result['points'][$i] = array(  'id' => $row['id'],
                                                    'name' => $row['name'],
                                                    'enable' => true);

                    $flag = true;
                }

            }

            if(!$flag)
                $result['points'][] = array('id' => $row['id'],
                                            'name' => $row['name'],
                                            'enable' => false);

        }

        for($i = 0; $i < sizeof($result['points']); $i++){

            if(!is_array($result['points'][$i]))
                array_splice($result['points'], $i);

        }

        //Сортируем массив по возрастанию
        $points = array_column($result['points'], 'name');
        array_multisort($result['points'], SORT_ASC, SORT_STRING, $points);

        $result['image'] = ($result['image'] == '') ? PLACEHOLDER_IMAGE : $result['image'];
        $result['image'] = ImageResize($result['image'], 130, 130);

        $technical_cards = DB::query('SELECT tc.id, ptc.count, CONCAT(p.name, ", ", tc.bulk_value, " ", tc.bulk_untils) AS name
                                            FROM '.DB_PROMOTION_TECHNICAL_CARDS.' ptc
                                            JOIN '.DB_TECHNICAL_CARD.' AS tc ON tc.id = ptc.technical_card
                                            JOIN '.DB_PRODUCTS.' AS p ON p.id = tc.product
                                            WHERE ptc.promotion = '.$promotion);
                                    
        $result['technical_cards'] = DB::makeArray($technical_cards);

        response('success', $result, '7');

    break;

    case 'edit':

        $fields = [];

        if(!$promotion = DB::escape($_REQUEST['promotion']))
            response('error', array('msg' => 'Акция не выбрана.'), '557');

        $promotionData = DB::select('id', DB_PROMOTIONS, 'partner = '.$userToken['id'].' AND id = '.$promotion);

        if(DB::getRecordCount($promotionData) == 0)
            response('error', array('msg' => 'Акция не найдена.'), '558');

        //Получаем параметры для создания акции        
        if($name = DB::escape($_REQUEST['name']))
            $fields['name'] = $name;

        if($description = DB::escape($_REQUEST['description']))
            $fields['description'] = $description;

        //$fields['enable'] = (DB::escape($_REQUEST['enable'])) ? 1 : 0;

        $price = DB::escape($_REQUEST['price']);

        if($price < 0)
            response('error', array('msg' => 'Цена не может быть отрицательной.'), '538');

        $fields['price'] = $price;

        //Получаем флаг о том, что она активна везде или нет
        $show_everywhere = DB::escape($_REQUEST['show_everywhere']);
        //Формируем список точек, где она активна
        $points = MenuPromotions($show_everywhere);

        $fields['points'] = $points;
        $fields['show_everywhere'] = $show_everywhere;

        if($technical_cards = DB::escape($_REQUEST['technical_cards'])){

            //Убираем экранирование символов
            $technical_cards = stripslashes($technical_cards);

            //Преобразуем JSON строку в объекты
            $technical_cards = json_decode($technical_cards, true);

            if($technical_cards == null || sizeof($technical_cards) == 0)
                response('error', array('msg' => 'Объект с тех. картами имеет неверный формат.'), '537');

            //Проверяем тех. карты на наличие, формируем запрос на добавление продуктов в акцию
            $insert = ProductsValidate($technical_cards, $promotion, $userToken['id']);

            if($insert){
                //Удаляем прошлую информацию о тех. картах
                DB::delete(DB_PROMOTION_TECHNICAL_CARDS, 'promotion = '.$promotion);

                if(!$promotion_items = DB::query('INSERT INTO '.DB_PROMOTION_TECHNICAL_CARDS.' (promotion, technical_card, count) VALUES '.$insert))
                    response('error', '', '503');

            }

        }

        //Если пришло изображение
        if($image = $_FILES['image']){

            $file = FileLoad('promotions/'.hash('md5', $userToken['id'].'promotions'), $image, hash('md5', $userToken['id'].'image'.time()));

            $fields['image'] = $file['link'];

        }

        if(sizeof($fields) == 0)
            response('success', array('msg' => 'Информация об акции обновлена.'), '641');

        if(DB::update($fields, DB_PROMOTIONS, 'id = '.$promotion))
            response('success', array('msg' => 'Информация об акции обновлена.'), '641');
        else
            response('error', '', '503');

    break;

    case 'delete':

        if(!$promotion = DB::escape($_REQUEST['promotion']))
            response('error', array('msg' => 'Акция не выбрана.'), '557');

        if(DB::delete(DB_PROMOTIONS, 'id = '.$promotion.' AND partner = '.$userToken['id']))
            response('success', array('msg' => 'Акция удалена.'), '642');
        else
            response('error', '' , '503');

    break;

}