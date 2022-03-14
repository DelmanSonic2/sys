<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/lib/functions.php';
require ROOT.'api/classes/OrderClass.php';

function MenuCategories($user, $category){

    

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
                $points_raw = '("'.$user.'", "'.$category.'", "'.$points[$i]['id'].'")';
            else
                $points_raw .= ', ("'.$user.'", "'.$category.'", "'.$points[$i]['id'].'")';

        }

    }


    DB::delete(DB_MENU_CATEGORIES, 'partner = '.$user.' AND category = '.$category);

    if($points_raw)
        DB::query('INSERT IGNORE INTO '.DB_MENU_CATEGORIES.' (partner, category, point) VALUES '.$points_raw);

}

switch($action){

    case 'add':

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Не передано название категории.'), '311');

        if(!$color = DB::escape($_REQUEST['color']))
            response('error', array('msg' => 'Не передан ID цвета.'), '331');

        $colorData = DB::select('id', DB_COLORS, 'id = '.$color);

        if(DB::getRecordCount($colorData) == 0)
            response('error', array('msg' => 'Такого цвета не существует.'), '332');

        $fields = array('name' => $name,
                        'color' => $color,
                        'partner' => $userToken['id']);

        if($image = $_FILES['image']){

            $fileImage = FileLoad('partner№'.$userToken['id'].'/products/categories', $image, hash('md5', 'coffeeway'.$userToken['id'].'category'.time()));
            
            $fields['image'] = $fileImage['link'];

        }

        if($parent = DB::escape($_REQUEST['parent'])){

            $parentData = DB::select('id', DB_PRODUCT_CATEGORIES, 'id = '.$parent);

            if(DB::getRecordCount($parentData) == 0)
                response('error', array('msg' => 'Такого родителя не существует.'), '333');

            $fields['parent'] = $parent;
        }

        if(!$category = DB::insert($fields, DB_PRODUCT_CATEGORIES))
            response('error', '', '503');

        $show_everywhere = DB::escape($_REQUEST['show_everywhere']);

        MenuCategories($userToken['id'], $category);

        response('success', array('msg' => 'Категория добавлена'), '602');

    break;

    case 'get':

        $result = [];

        $full = (DB::escape($_REQUEST['full'] == 'true')) ? ', pc.image, pc.partner AS my' : '';

        $partner = (DB::escape($_REQUEST['partner']) && $userToken['admin']) ? DB::escape($_REQUEST['partner']) : $userToken['id'];

        $ORDER_BY = Order::product_categories(Pages::$field, Pages::$order);

        $categories = DB::query('SELECT pc.id, pc.parent, pc.name, c.code AS color'.$full.'
                                        FROM '.DB_PRODUCT_CATEGORIES.' pc
                                        JOIN '.DB_COLORS.' AS c ON c.id = pc.color
                                        WHERE (pc.partner = '.$partner.' OR pc.partner IS NULL)
                                        '.$ORDER_BY);

        if(DB::escape($_REQUEST['full'] == 'true')){

            while($row = DB::getRow($categories)){

                $row['childs'] = [];

                if($full){

                    $row['my'] = /* ($row['my'] == null) ? false : */ true;

                    $image = ($row['image'] == '') ? PLACEHOLDER_IMAGE : $row['image'];

                    $row['image'] = ImageResize($image, 130, 130);
                }

                $result[$row['parent']][$row['id']] =  $row;
            }

            $result = CategoriesTree($result, null);
        }
        else
            $result = DB::makeArray($categories);

        response('success', $result, '7');

    break;

    case 'edit':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Не передан ID категории.'), '320');

        $fields = [];

        $categoryData = DB::select('id, partner, image', DB_PRODUCT_CATEGORIES, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $categoryData = DB::getRow($categoryData);

        $editing_allowed = ($categoryData['partner'] == null && !$userToken['admin']) ? false : true;

        if(DB::escape($_REQUEST['name']))
            $fields['name'] = DB::escape($_REQUEST['name']);

        if(DB::escape($_REQUEST['color'])){
            $fields['color'] = DB::escape($_REQUEST['color']);

            $colorData = DB::select('id', DB_COLORS, 'id = '.$fields['color']);

            if(DB::getRecordCount($colorData) == 0)
                response('error', array('msg' => 'Такого цвета не существует.'), '332');

        }

        if($editing_allowed){
            $parent = DB::escape($_REQUEST['parent']);

            if($parent){

                $parentData = DB::select('id', DB_PRODUCT_CATEGORIES, 'id = '.$parent);

                if(DB::getRecordCount($parentData) == 0)
                    response('error', array('msg' => 'Такого родителя не существует.'), '333');

                $fields['parent'] = $parent;

            }
            else
                $fields['parent'] = 'NULL';

            if($parent == $category)
                response('error', array('msg' => 'Вы не можете вложить категорию саму в себя.'), '561');

            if($_FILES['image'] && $editing_allowed){

                $fileImage = FileLoad('partner№'.$userToken['id'].'/products/categories', $_FILES['image'], hash('md5', 'coffeeway'.$userToken['id'].'category'.time()));
                
                $fields['image'] = $fileImage['link'];

                unset($categoryData['image']);

            }
        }

        MenuCategories($userToken['id'], $category);

        if($editing_allowed){

            if(sizeof($fields) == 0)
                response('success', array('msg' => 'Информация о категории обновлена.'), '609');

            DB::update($fields, DB_PRODUCT_CATEGORIES, 'id = '.$category);
        }
        
        response('success', array('msg' => 'Информация о категории обновлена.'), '609');

    break;

    case 'delete':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Не передан ID категории.'), '320');

        $fields = [];

        $categoryData = DB::select('id, partner, image', DB_PRODUCT_CATEGORIES, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $categoryData = DB::getRow($categoryData);

        $editing_allowed = ($categoryData['partner'] == null && !$userToken['admin']) ? false : true;

        if(!$editing_allowed)
            response('error', array('msg' => 'Вы не можете удалить общедоступную категорию.'), '340');

        if(DB::delete(DB_PRODUCT_CATEGORIES, 'id = '.$category))
            response('success', array('msg' => 'Категория удалена.'), '613');

    break;

    case 'info':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Не передан ID категории.'), '320');

        $fields = [];

        //$categoryData = DB::select('id, partner, image', DB_PRODUCT_CATEGORIES, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');
        $categoryData = DB::query('SELECT c.id, c.name, c.image, pc.id AS pcid, pc.name AS pcname, c.id AS cid, c.name AS cname, c.show_everywhere, c.points
                                            FROM '.DB_PRODUCT_CATEGORIES.' c
                                            LEFT JOIN '.DB_PRODUCT_CATEGORIES.' AS pc ON pc.id = c.parent
                                            LEFT JOIN '.DB_COLORS.' AS clr ON clr.id = c.color
                                            WHERE c.id = '.$category.' AND (c.partner = '.$userToken['id'].' OR c.partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $row = DB::getRow($categoryData);

        $image = ($row['image'] == '') ? PLACEHOLDER_IMAGE: $row['image'];

        $editing_allowed = ($row['partner'] == null && !$userToken['admin']) ? false : true;

        $row['image'] = ImageResize($image, 130, 130);

        $result = array('id' => $row['id'],
                        'name' => $row['name'],
                        'image' => $row['image'],
                        'show_everywhere' => (bool)$row['show_everywhere'],
                        'category' => array('id' => $row['cid'],
                                            'name' => $row['cname']),
                        'editing_allowed' => $editing_allowed,
                        'points' => []);

        if($row['pcid'] != null)
            $result['parent'] = array('id' => $row['pcid'],
                                    'name' => $row['pcname']);
        else
            $result['parent'] = null;

        $points = DB::query('SELECT p.id, p.name, m.partner AS enable
                                    FROM '.DB_PARTNER_POINTS.' p
                                    LEFT JOIN '.DB_MENU_CATEGORIES.' m ON m.point = p.id AND m.category = '.$category.' AND m.partner = '.$userToken['id'].'
                                    WHERE p.partner = '.$userToken['id'].'
                                    ORDER BY p.name');

        while($row = DB::getRow($points)){

            $row['enable'] = ($row['enable'] == null) ? false : true;

            $result['points'][] = $row;

        }    

        response('success', $result, '7');

    break;

    case 'table':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', 'Выберите категорию.', 1);

        $points_data = [];

        $points = DB::select('id, name', DB_PARTNER_POINTS, 'partner = '.$userToken['id']);

        $result = array('points' => [],
                        'products' => []);

        while($row = DB::getRow($points)){

            $result['points'][] = $row;
            $points_data[] = array( 'id' => $row['id'],
                                    'price' => 0,
                                    'hide' => false);

        }

        $archive = '
            AND tc.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "technical_card" AND partner_id = '.$userToken['id'].'
            )';

        $product_prices = DB::query('SELECT tc.id, tc.product, p.name, tc.subname, tc.bulk_value, tc.bulk_untils, pnt.id AS point, pp.price, pp.hide, tc.price AS tcprice
                                            FROM '.DB_PRODUCTS.' p
                                            JOIN '.DB_TECHNICAL_CARD.' tc ON tc.product = p.id
                                            JOIN '.DB_PARTNER_POINTS.' pnt ON pnt.partner = '.$userToken['id'].'
                                            LEFT JOIN '.DB_PRODUCT_PRICES.' pp ON pp.point = pnt.id AND pp.technical_card = tc.id AND pp.product = p.id AND pp.partner = '.$userToken['id'].'
                                            WHERE (tc.partner = '.$userToken['id'].' OR tc.partner IS NULL) AND p.category = '.$category.$archive.'
                                            GROUP BY tc.id, pnt.id
                                            ORDER BY p.name ASC, tc.subname ASC, tc.bulk_value ASC');

        while($row = DB::getRow($product_prices)){

            $add = false;

            for($i = 0; $i < sizeof($result['products']); $i++){

                if($result['products'][$i]['id'] == $row['id']){


                    for($j = 0; $j < sizeof($result['products'][$i]['points']); $j++){

                        if($row['point'] == $result['products'][$i]['points'][$j]['id']){
    
                            $result['products'][$i]['points'][$j]['price'] = ($row['price'] == null) ? round($row['tcprice'], 2) : round($row['price'], 2);
                            $result['products'][$i]['points'][$j]['hide'] = (bool)$row['hide'];
    
                            break;
    
                        }
    
                    }

                   /*  $result['products'][$i]['points'][] = array('id' => $row['point'],
                                                                'price' => round($row['price'], 2),
                                                                'hide' => (bool)$row['hide']); */

                    $add = true;
                    break;

                }

            }

            if(!$add){

                if($row['subname'])
                    $row['name'] .= ' ('.$row['subname'].')';

                $points = $points_data;

                for($j = 0; $j < sizeof($points); $j++){

                    if($row['point'] == $points[$j]['id']){

                        $points[$j]['price'] = ($row['price'] == null) ? round($row['tcprice'], 2) : round($row['price'], 2);
                        $points[$j]['hide'] = (bool)$row['hide'];

                        break;

                    }

                }

                $result['products'][] = array('id' => $row['id'],
                                            'product' => $row['product'],
                                            'name' => $row['name'].' '.$row['bulk_value'].$row['bulk_untils'],
                                            'points' => $points);

            }

        }

        response('success', $result, 7);

    break;

    case 'prices':

        $data = DB::escape($_REQUEST['products']);

        $data = stripcslashes($data);

        $new = [];
        $old = [];

        $menu_products = [];

        $data = json_decode($data, true); // Преобразуем полученные данные в массив

        for($i = 0; $i < sizeof($data); $i++){ // Проходимся по списку товаров

            for($j = 0; $j < sizeof($data[$i]['points']); $j++){ // Проходимся по списку точек

                if(!$where) // Формируем условие для поиска значений в БД
                    $where = '(point = '.$data[$i]['points'][$j]['id'].' AND technical_card = '.$data[$i]['id'].')';
                else
                    $where .= ' OR (point = '.$data[$i]['points'][$j]['id'].' AND technical_card = '.$data[$i]['id'].')';

                $new[] = array( 'technical_card' => $data[$i]['id'],
                                'product' => $data[$i]['product'],
                                'point' => $data[$i]['points'][$j]['id'],
                                'price' => $data[$i]['points'][$j]['price'],
                                'hide' => (int)$data[$i]['points'][$j]['hide']);


                $exist = false;
                
                for($k = 0; $k < sizeof($menu_products); $k++){

                    if($menu_products[$k]['partner'] == $userToken['id'] &&
                        $menu_products[$k]['product'] == $data[$i]['product'] &&
                        $menu_products[$k]['point'] == $data[$i]['points'][$j]['id']){

                        if(!$data[$i]['points'][$j]['hide'])
                            $menu_products[$k]['hide'] = 0;
                        
                        $exist = true;
                        break;
                        
                    }

                }

                if(!$exist){

                    $menu_products[] = array(
                        'partner' => $userToken['id'],
                        'product' => $data[$i]['product'],
                        'point' => $data[$i]['points'][$j]['id'],
                        'hide' => (int)$data[$i]['points'][$j]['hide']
                    );

                }

            }

        }

        unset($data);

        if(!$where) // Если ничего не было передано, то выход
            response('success', 'Изменения сохранены.', 7);

        $old_data = DB::select('*', DB_PRODUCT_PRICES, 'partner = '.$userToken['id'].' AND ('.$where.')');

        while($row = DB::getRow($old_data))
            $old[] = $row;

        for($i = 0; $i < sizeof($new); $i++){

            $exist = false;

            for($j = 0; $j < sizeof($old); $j++){

                //Если есть совпадения в списке, то ставим флаг, что не нужно добавлять новую позицию с ценой
                if($new[$i]['technical_card'] == $old[$j]['technical_card'] && $new[$i]['point'] == $old[$j]['point']){

                    //Если цена или флаг скрытия двух списков различаются, то обновляем строки
                    if($new[$i]['price'] != $old[$j]['price'] || $new[$i]['hide'] != $old[$j]['hide']){
                        if(!$update_rows)
                            $update_rows = '("'.$new[$i]['point'].'", "'.$new[$i]['product'].'", "'.$new[$i]['technical_card'].'", "'.$userToken['id'].'", "'.$new[$i]['price'].'", "'.$new[$i]['hide'].'")';
                        else
                            $update_rows .= ', ("'.$new[$i]['point'].'", "'.$new[$i]['product'].'", "'.$new[$i]['technical_card'].'", "'.$userToken['id'].'", "'.$new[$i]['price'].'", "'.$new[$i]['hide'].'")';
                    
                        if(!$update_technical_cards)
                            $update_technical_cards = 'id = '.$new[$i]['technical_card'];
                        else
                            $update_technical_cards .= ' OR id = '.$new[$i]['technical_card'];

                    }

                    $exist = true;

                }

            }

            if(!$exist){//Формирование строки для добавления строк в БД

                if(!$insert)
                    $insert = '("'.$new[$i]['point'].'", "'.$new[$i]['product'].'", "'.$new[$i]['technical_card'].'", "'.$userToken['id'].'", "'.$new[$i]['price'].'", "'.$new[$i]['hide'].'")';
                else
                    $insert .= ', ("'.$new[$i]['point'].'", "'.$new[$i]['product'].'", "'.$new[$i]['technical_card'].'", "'.$userToken['id'].'", "'.$new[$i]['price'].'", "'.$new[$i]['hide'].'")';
                
                if(!$update_technical_cards)
                    $update_technical_cards = 'id = '.$new[$i]['technical_card'];
                else
                    $update_technical_cards .= ' OR id = '.$new[$i]['technical_card'];

            }
        }

        if($insert)//Если есть новые позиции, то добавляем
            DB::query('INSERT IGNORE INTO '.DB_PRODUCT_PRICES.' (point, product, technical_card, partner, price, hide) VALUES '.$insert);

        if($update_rows){

            //Название для времменной таблицы связанное с id партнера, чтобы не было пересечений таблиц партнеров
            $tmp_table = '`app_prices_'.$userToken['id'].time().'`';

            //Создаем временную таблицу
            DB::query('CREATE TEMPORARY TABLE '.$tmp_table.' (
                                `point` int(11) NOT NULL,
                                `product` int(11) NOT NULL,
                                `technical_card` int(11) NOT NULL,
                                `partner` int(11) NOT NULL,
                                `price` double NOT NULL,
                                `hide` tinyint(1) NOT NULL
                            )');

            //Заполняем данными временную таблицу, добавляем поля, которые должны обновиться
            DB::query('INSERT IGNORE INTO '.$tmp_table.' (point, product, technical_card, partner, price, hide) VALUES '.$update_rows);

            //Обновляем настоящую таблицу данными из временной
            DB::query('  UPDATE '.DB_PRODUCT_PRICES.' p
                                JOIN '.$tmp_table.' t ON t.point = p.point
                                                        AND t.product = p.product
                                                        AND t.technical_card = p.technical_card
                                                        AND t.partner = p.partner
                                SET p.price = t.price, p.hide = t.hide');

        }

        if($update_technical_cards)
            DB::update(array('different_price' => 1), DB_TECHNICAL_CARD, $update_technical_cards);

        foreach($menu_products as $value){

            if($value['hide']){

                if(!$delete_menu_products)
                    $delete_menu_products = '(partner = '.$value['partner'].' AND product = '.$value['product'].' AND point = '.$value['point'].')';
                else
                    $delete_menu_products .= ' OR (partner = '.$value['partner'].' AND product = '.$value['product'].' AND point = '.$value['point'].')';

            }
            else{
                
                if(!$insert_menu_products)
                    $insert_menu_products = '('.$value['partner'].','.$value['product'].','.$value['point'].')';
                else
                    $insert_menu_products .= ',('.$value['partner'].','.$value['product'].','.$value['point'].')';

            }

        }

        if($delete_menu_products)
            DB::delete(DB_MENU_PRODUCTS, $delete_menu_products);

        if($insert_menu_products)
            DB::query('INSERT IGNORE INTO '.DB_MENU_PRODUCTS.' (partner, product, point) VALUES '.$insert_menu_products);

        response('success', 'Изменения сохранены.', 7);

    break;

}