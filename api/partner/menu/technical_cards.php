<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

require ROOT.'api/classes/ItemsClass.php';

require ROOT.'api/classes/OrderClass.php';

$bool_string = array('false' => 0,
                    'true' => 1);

function SelectionCriteria($filter){

    $result = [];

    $filter = stripslashes($filter);

    $filter = json_decode($filter, true);

    if($filter == null || sizeof($filter) == 0)
        response('error', array('msg' => 'Фильтр имеет неверный формат.'), '395');

    for($i = 0; $i < sizeof($filter); $i++) {

        if($filter[$i]['field'] == 'cost_price') $filter[$i]['field'] = 'ROUND(cost_price, 2)';
        if($filter[$i]['field'] == 'net_mass') $filter[$i]['field'] = 'ROUND(net_mass, 3)';
        if($filter[$i]['field'] == 'markup') $filter[$i]['field'] = 'ROUND(markup, 2)';
        if($filter[$i]['field'] == 'price') $filter[$i]['field'] = 'ROUND(pcprice, 2)';
        if($filter[$i]['field'] == 'cashback') $filter[$i]['field'] = 'ROUND(cashback_percent, 2)';

        $result[] = "({$filter[$i]['field']} {$filter[$i]['operation']} {$filter[$i]['value']})";
    }

    return sizeof($result) ? 'HAVING '.implode(' OR ', $result) : '';

}

function CategoriesFilter($categories){

    $categories = explode(',', $categories);

    for($i = 0; $i < sizeof($categories); $i++){

        if(!$result)
            $result = ' AND (cat.id = '.$categories[$i];
        else
            $result .= ' OR cat.id = '.$categories[$i];

    }

    $result .= ')';

    return $result;

}

//Возвращает запрос на добавление ингредеинтов
function ItemsInsertQuery($items, $technical_card = 0){

    $query = '';

    for($i = 0; $i < sizeof($items); $i++){

        if($items[$i]['untils'] != 'шт')
            $items[$i]['count'] = $items[$i]['gross'];

        if(!$itemsStr)
            $itemsStr = '("'.$items[$i]['untils'].'", "'.$technical_card.'", "'.$items[$i]['id'].'", "'.$items[$i]['count'].'", "'.$items[$i]['cleaning'].'", "'.$items[$i]['cooking'].'", "'.$items[$i]['frying'].'", "'.$items[$i]['stew'].'", "'.$items[$i]['bake'].'", "'.$items[$i]['gross'].'", "'.$items[$i]['net_mass'].'", "'.$items[$i]['cooking_checked'].'", "'.$items[$i]['cleaning_checked'].'", "'.$items[$i]['frying_checked'].'", "'.$items[$i]['stew_checked'].'", "'.$items[$i]['bake_checked'].'", "'.$items[$i]['mass_block'].'")';
        else
            $itemsStr .= ', ("'.$items[$i]['untils'].'", "'.$technical_card.'", "'.$items[$i]['id'].'", "'.$items[$i]['count'].'", "'.$items[$i]['cleaning'].'", "'.$items[$i]['cooking'].'", "'.$items[$i]['frying'].'", "'.$items[$i]['stew'].'", "'.$items[$i]['bake'].'", "'.$items[$i]['gross'].'", "'.$items[$i]['net_mass'].'", "'.$items[$i]['cooking_checked'].'", "'.$items[$i]['cleaning_checked'].'", "'.$items[$i]['frying_checked'].'", "'.$items[$i]['stew_checked'].'", "'.$items[$i]['bake_checked'].'", "'.$items[$i]['mass_block'].'")';

    }

    $query = 'INSERT INTO '.DB_PRODUCT_COMPOSITION.' (untils, technical_card, item, count, cleaning, cooking, frying, stew, bake, gross, net_mass, cooking_checked, cleaning_checked, frying_checked, stew_checked, bake_checked, mass_block) VALUES '.$itemsStr;
    return $query;

}

//Задает разную цену на определенные товары на разных точках [УСТАРЕВШЕЕ]
function DifferentPrice($product, $technical_card, $user, $different_price, $global_price, $create = false){

    

    if(!$points = DB::escape($_REQUEST['points'])){
        if($create){
            DB::delete(DB_PRODUCT_COMPOSITION, 'technical_card = '.$technical_card);
            DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card);
        }
        response('error', array('msg' => 'На одной или более точках не указана цена.'), '365');
    }

    $points = stripcslashes($points);

    $points = json_decode($points, true);

    if($points == null || sizeof($points) == 0){
        if($create){
            DB::delete(DB_PRODUCT_COMPOSITION, 'technical_card = '.$technical_card);
            DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card);
        }
        response('error', array('msg' => 'На одной или более точках не указана цена.'), '366');
    }

    for($i = 0; $i < sizeof($points); $i++){

        if(!$points[$i]['id']){
            if($create){
                DB::delete(DB_PRODUCT_COMPOSITION, 'technical_card = '.$technical_card);
                DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card);
            }
            response('error', array('msg' => 'На одной или более точках не указана цена.'), '366');
        }

        if(!$points[$i]['hide'] || !$different_price)
            $points[$i]['hide'] = 0;
        if(!$points[$i]['price'])
            $points[$i]['price'] = 0;

        $price = !$different_price && $global_price ? $global_price : $points[$i]['price'];

        if(!$point_prices){
            $pointsWhere = 'id = '.$points[$i]['id'];
            $point_prices = '("'.$points[$i]['id'].'", "'.$product.'", "'.$technical_card.'", "'.$user.'", "'.$price.'", '.$points[$i]['hide'].')';
        }
        else{
            $pointsWhere .= ' OR id = '.$points[$i]['id'];
            $point_prices .= ', ("'.$points[$i]['id'].'", "'.$product.'", "'.$technical_card.'", "'.$user.'", "'.$price.'", '.$points[$i]['hide'].')';
        }
    }

    /* $pointsData = DB::select('id', DB_PARTNER_POINTS, $pointsWhere);

    if(DB::getRecordCount($pointsData) != sizeof($points)){
        if($create){
            DB::delete(DB_PRODUCT_COMPOSITION, 'technical_card = '.$technical_card);
            DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card);
        }
        response('error', array('msg' => 'На одной или более точках не указана цена.'), '366');
    } */

    if(!DB::query('INSERT INTO '.DB_PRODUCT_PRICES.' (point, product, technical_card, partner, price, hide) VALUES '.$point_prices)){
        if($create){
            DB::delete(DB_PRODUCT_COMPOSITION, 'technical_card = '.$technical_card);
            DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card);
        }
        response('error', '', '503');
    }

    for($i = 0; $i < sizeof($points); $i++){
        $product_hide = DB::select('id', DB_PRODUCT_PRICES, 'partner = '.$user.' AND technical_card = '.$technical_card.' AND point = '.$points[$i]['id'].' AND hide = 0', '', 1);

        //Если есть хоть одна не скрытая тех. карта, то отображаем товар в меню, иначе скрываем
        if(DB::getRecordCount($product_hide))
            DB::query('INSERT IGNORE INTO '.DB_MENU_PRODUCTS.' (partner, point, product) VALUES ('.$user.', '.$points[$i]['id'].', '.$product.')');
        else
            DB::delete(DB_MENU_PRODUCTS, 'partner = '.$user.' AND point = '.$points[$i]['id'].' AND product = '.$product);
    }

}

//Не используется
function TotalPrice($product, $technical_card, $user, $price){

    

    $points = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$user);
    $points = DB::makeArray($points);

    for($i = 0; $i < sizeof($points); $i++){

        if(!$point_prices)
            $point_prices = '("'.$points[$i]['id'].'", "'.$product.'", "'.$technical_card.'", "'.$user.'", "'.$price.'")';
        else
            $point_prices .= ', ("'.$points[$i]['id'].'", "'.$product.'", "'.$technical_card.'", "'.$user.'", "'.$price.'")';
    }

    if(!DB::query('INSERT INTO '.DB_PRODUCT_PRICES.' (point, product, technical_card, partner, price) VALUES '.$point_prices))
        return false;

    return true;

}

switch($action){

    case 'add':

        if(!$product = DB::escape($_REQUEST['product'])) 
            response('error', array('msg' => 'Выберите продукт, для которого хотите создать тех. карту.'), '336');

        $productData = DB::select('id, partner, image', DB_PRODUCTS, 'id = '.$product.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');
        
        if(DB::getRecordCount($productData) == 0)
            response('error', array('msg' => 'Такого товара не существует.'), '337');

        $productData = DB::getRow($productData);

        $fields['product'] = $product;

        if(!$bulk_untils = DB::escape($_REQUEST['bulk_untils']))
            response('error', array('msg' => 'Выберите единицы измерения.'), '340');

        if($bulk_untils == 'шт')
            $untils = 'количество.';

        if($bulk_untils == 'л' || $bulk_untils == 'мл')
            $untils = 'объем.';

        if($bulk_untils == 'кг' || $bulk_untils == 'г')
            $untils = 'массу.';

        if(!$bulk_value = DB::escape($_REQUEST['bulk_value']))
            response('error', array('msg' => 'Укажите '.$untils), '339');

        if($subname = DB::escape($_REQUEST['subname']))
            $fields['subname'] = $subname;

        if($name_price = DB::escape($_REQUEST['name_price']))
            $fields['name_price'] = $name_price;

        $cashback_percent = DB::escape($_REQUEST['cashback_percent']);
        if($cashback_percent < 0 || $cashback_percent > 100)
            response('error', 'Процент кэшбэка должен находиться в пределах от 0 до 100.', 422);

        $fields['bulk_value'] = $bulk_value;
        $fields['bulk_untils'] = $bulk_untils;
        $fields['cashback_percent'] = $cashback_percent;

        $fields['preparing_seconds'] = 0;

        /*if($code = DB::escape($_REQUEST['code'])){
            if(strlen($code) > 32)
                response('error', array('msg' => 'Штрих-код не может превышать больше 32-х символов.'), '588');
            $fields['code'] = $code;
        }*/

        if($minutes = DB::escape($_REQUEST['minutes'])){
            if($minutes < 0)
                $minutes = 0;
            $fields['preparing_minutes'] = $minutes;
        }
        else
            $fields['preparing_minutes'] = 0;

        if($seconds = DB::escape($_REQUEST['seconds'])){

            if($seconds < 0)
                $seconds = 0;

            if($seconds > 60){

                $fields['preparing_minutes'] += floor($seconds / 60);

                $seconds -= floor($seconds / 60) * 60;

            }

            $fields['preparing_seconds'] = $seconds;

        }
        else
            $fields['preparing_seconds'] = 0;

        if($cooking_method = DB::escape($_REQUEST['cooking_method']))
            $fields['cooking_method'] = $cooking_method;

        if($color = DB::escape($_REQUEST['color'])){
            $colorData = DB::select('id', DB_COLORS, 'id = '.$color);

            if(DB::getRecordCount($colorData) == 0)
                response('error', array('msg' => 'Такого цвета не существует.'), '332');

            $fields['color'] = $color;

        }

        if($different_price = DB::escape($_REQUEST['different_price']))
            $fields['different_price'] = $bool_string[$different_price];

        if($not_promotion = DB::escape($_REQUEST['not_promotion']))
            $fields['not_promotion'] = $bool_string[$not_promotion];

        $fields['price'] = (DB::escape($_REQUEST['price'])) ? DB::escape($_REQUEST['price']) : 0;
        $fields['partner'] = $userToken['id'];

        $technical_card_data = DB::query('
            SELECT tc.id, a.id AS arch
            FROM '.DB_TECHNICAL_CARD.' tc
            LEFT JOIN '.DB_ARCHIVE.' a ON a.product_id = tc.id AND a.model = "technical_card" AND partner_id = '.$userToken['id'].'
            WHERE tc.product = '.$product.' AND tc.bulk_value = "'.$bulk_value.'" AND tc.bulk_untils = "'.$bulk_untils.'" AND tc.subname = "'.$subname.'"');

        if(DB::getRecordCount($technical_card_data) != 0) {
            $arch = DB::getRow($technical_card_data)['arch'];
            if($arch == null)
                response('error', array('msg' => 'Такая тех. карта уже существует.'), '538');
            else
                response('error', array('msg' => 'Данная тех. карта находится в архиве, восстановите её.'), '538');
        }
            
        //Получаем состав тех. карты
        $items_class = new ItemsClass(false, $userToken['id'], true);
        $newItems = $items_class->validate();

        //Добавление технической карты
        if(!$technical_card = DB::insert($fields, DB_TECHNICAL_CARD))
            response('error', '', '503');

        //Формирование запроса на добавление ингредиентов
        $itemsQuery = ItemsInsertQuery($newItems, $technical_card);

        if(!$itemsInsert = DB::query($itemsQuery)){
            DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card);
            response('error', '', '503');
        }

        DifferentPrice($product, $technical_card, $userToken['id'], $fields['different_price'], $fields['price'], true);
        DB::update(['code' => 5000 + $technical_card],DB_TECHNICAL_CARD, 'id = '.$technical_card);

        response('success', array('msg' => 'Техническая карта создана.'), '620');

    break;

    case 'validation':

        //Получаем состав тех. карты
        $items_class = new ItemsClass(false, $userToken['id']);
        $newItems = $items_class->validate();

        response('success', $newItems, '7');

    break;

    case 'items':

        $result = [];

        $archive = '
            AND i.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "item" AND partner_id = '.$userToken['id'].'
            )';

        $items = DB::query('SELECT i.id, i.name, i.untils, i.production, i.bulk
                                    FROM '.DB_ITEMS.' i
                                    WHERE ( i.partner = '.$userToken['id'].' OR i.partner IS NULL ) AND i.del = 0'.$archive.'
                                    GROUP BY i.id
                                    ORDER BY i.name');

        while($row = DB::getRow($items)){

            if($row['production'])
                $row['name'] .= ' (п/ф)';

            $row['name'] .= ', '.$row['untils'];

            $result[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'bulk' => $row['bulk'],
                'untils' => $row['untils']
            );

        }

        response('success', $result, '7');

    break;

    case 'get':

        $sorting = Order::technical_cards(Pages::$field, Pages::$order);

        //Критерии выборки
        if($filter = DB::escape($_REQUEST['filter']))
            $filter = SelectionCriteria($filter);

        //Поисковый запрос
        if($search = DB::escape($_REQUEST['search']))
            $searchStr = ' AND (p.name LIKE "%'.$search.'%" OR cat.name LIKE "%'.$search.'%" OR
            (SELECT GROUP_CONCAT(vi.name)
                    FROM '.DB_PRODUCT_COMPOSITION.' vp
                    JOIN '.DB_ITEMS.' vi ON vi.id = vp.item
                    WHERE vp.technical_card = tc.id) LIKE "%'.$search.'%")';

        //Фильтрация по категориям
        if($categories = DB::escape($_REQUEST['categories']))
            $categories = CategoriesFilter($categories);

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Выберите заведение.'), '329');

        $archive = '
            AND tc.id '.(DB::escape($_REQUEST['archive']) ? '' : 'NOT').' IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "technical_card" AND partner_id = '.$userToken['id'].'
            )';

        $query = 'SELECT tc.id, tc.code, p.name, tc.subname, tc.name_price, tc.bulk_value, tc.bulk_untils, cat.id AS catid, cat.name AS catname, tc.different_price, tc.price AS tcprice, tc.partner, tc.weighted,
                SUM(pcmp.net_mass) AS net_mass, tc.bulk_untils AS untils, pc.price AS pcprice,
                SUM(IF(pcmp.untils = "шт", pcmp.count, pcmp.gross) * pi.price) AS cost_price, MIN(IFNULL(pi.price, 0)) AS cost_price_calc,
                ((pc.price / SUM(IF(pcmp.untils = "шт", pcmp.count, pcmp.gross) * pi.price) - 1) * 100) AS markup,
                tc.cashback_percent,
                IF(tc.composition_description = "",
                    (SELECT GROUP_CONCAT(CONCAT(vi.name, " ", vi.untils, " - ", vp.count) SEPARATOR ", ")
                    FROM '.DB_PRODUCT_COMPOSITION.' vp
                    JOIN '.DB_ITEMS.' vi ON vi.id = vp.item
                    WHERE vp.technical_card = tc.id)
                , tc.composition_description) AS composition_description
            FROM '.DB_TECHNICAL_CARD.' tc
            JOIN '.DB_PRODUCTS.' AS p ON p.id = tc.product
            JOIN '.DB_PRODUCT_COMPOSITION.' AS pcmp ON pcmp.technical_card = tc.id
            JOIN '.DB_ITEMS.' i ON i.id = pcmp.item
            LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = pcmp.item AND pi.point = '.$point.'
            LEFT JOIN '.DB_PRODUCT_CATEGORIES.' AS cat ON cat.id = p.category
            LEFT JOIN '.DB_PRODUCT_PRICES.' AS pc ON pc.technical_card = tc.id AND pc.point = '.$point.' AND pc.point = '.$point.'
            WHERE (tc.partner = '.$userToken['id'].' OR tc.partner IS NULL)'.$searchStr.$categories.$archive.'
            GROUP BY tc.id
            '.$filter.'
            '.$sorting;

        $technical_cards = DB::query("$query LIMIT ".Pages::$limit);

        $result = [];

        $pages = DB::query("SELECT COUNT(t.id) AS count FROM ($query) AS t");

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

        while($row = DB::getRow($technical_cards)){

            $price = $row['pcprice'] ?: $row['tcprice'];

            $diff_price = $price - $row['cost_price'];

            $cost_price_calc = ($row['cost_price_calc'] == null || $row['cost_price_calc'] == 0) ? false : true;

            if($row['cost_price'] == null)
                $row['cost_price'] = 0;
            
            if($row['cost_price'] > 0)
                $markup = $diff_price / $row['cost_price'] * 100;
            else
                $markup = 0;

            $name = $row['name'];
            if($row['subname'] != '')
                $name .= ' ('.$row['subname'].')';
            $name .= ', '.$row['bulk_value'].' '.$row['bulk_untils'];

            //$row['net_mass'] *= 1000;
            $untils = 'кг';

            $result[] = array(
                'id' => $row['id'],
                'name' => $name,
                'name_price' => $row['name_price'],
                'code' => $row['code'],
                'cost_price_calc' => true,
                'untils' => $untils,
                'cost_price_calc' => $cost_price_calc,
                'category' => array('id' => $row['catid'],
                                    'name' => $row['catname']),
                'net_mass' => number_format($row['net_mass'], 3, ',', ' '),
                'cost_price' => number_format($row['cost_price'], 2, ',', ' '),
                'price' => number_format($price, 2, ',', ' '),
                'markup' => number_format($row['markup'], 2, ',', ' '),
                'my' => ($row['partner'] == $userToken['id'] || ($row['partner'] == null && $userToken['admin'])) ? true : false,
                'can_share' => ($userToken['admin'] && $row['partner'] != null) ? true : false,
                'cashback_percent' => $row['cashback_percent'].' %',
                'composition_description' => $row['composition_description'],
                'weighted' => (bool)$row['weighted']
            );

        }

        response('success', $result, '7', $pageData);

    break;

    case 'composition':

        $element_count = 10;

        if(!$page || $page == 1){
            $page = '1';
            $limit = '0,'.$element_count;
        }
        else{
            $begin = $element_count*$page - $element_count;
            $limit = $begin.','.$element_count; 
        }

        if(!$technical_card = DB::escape($_REQUEST['technical_card']))
            response('error', array('msg' => 'Выберите тех. карту.'), '371');

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Выберите заведение.'), '329');

        $composition = DB::query('SELECT c.id, c.count, c.gross, i.untils, c.net_mass, i.id AS iid, i.name, SUM(pi.price * IF(c.untils = "шт", c.count, c.gross)) AS cost_price, AVG(pi.price) AS calc
                                        FROM '.DB_PRODUCT_COMPOSITION.' c
                                        JOIN '.DB_ITEMS.' AS i ON i.id = c.item
                                        LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = c.item AND pi.point = '.$point.'
                                        WHERE c.technical_card = '.$technical_card.'
                                        GROUP BY c.id
                                        LIMIT '.Pages::$limit);

        $result = [];

        while($row = DB::getRow($composition)){

            if($row['untils'] != 'шт')
                $row['count'] = '-';

            $row['calc'] = ($row['calc'] == null) ? false : true;

            $row['cost_price'] = (double)$row['cost_price'];

            $result[] = array('id' => $row['id'],
                                'untils' => $row['untils'],
                                'count' => $row['count'],
                                'gross' => number_format($row['gross'], 3, ',', ' '),
                                'net_mass' => number_format($row['net_mass'], 3, ',', ' '),
                                'price' => ($row['calc']) ? number_format($row['cost_price'], 2, ',', ' ') : '-',
                                'calc' => $row['calc'],
                                'item' => array('id' => $row['iid'],
                                                'name' => $row['name']));
        }

        $pages = DB::query('SELECT COUNT(c.id) AS count
                                    FROM '.DB_PRODUCT_COMPOSITION.' c
                                    WHERE c.technical_card = '.$technical_card);

        $pages = DB::getRow($pages);

        if($pages['count'] != null)
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
                            'total_pages' => $total_pages,
                            'rows_count' => (int)$pages['count'],
                            'page_size' => ELEMENT_COUNT);

        response('success', $result, '7', $pageData);

    break;

    case 'info':

        if(!$technical_card = DB::escape($_REQUEST['technical_card']))
            response('error', array('msg' => 'Выберите тех. карту.'), '371');

        $technical_card_data = DB::query('SELECT tc.id, tc.code, tc.product, pr.name, tc.subname, tc.name_price, tc.bulk_value, tc.bulk_untils, tc.price, tc.preparing_minutes, tc.preparing_seconds, tc.color, c.code AS color_code, tc.different_price, tc.not_promotion, tc.cooking_method, tc.partner, tc.cashback_percent
                                            FROM '.DB_TECHNICAL_CARD.' tc
                                            JOIN '.DB_PRODUCTS.' AS pr ON pr.id = tc.product
                                            LEFT JOIN '.DB_COLORS.' AS c ON c.id = tc.color
                                            WHERE tc.id = '.$technical_card.' AND (tc.partner = '.$userToken['id'].' OR tc.partner IS NULL)');

        if(DB::getRecordCount($technical_card_data) == 0)
            response('error', array('msg' => 'Тех. карта не найдена.'), '389');

        $row = DB::getRow($technical_card_data);

        $editing_allowed = ($row['partner'] == null && !$userToken['admin']) ? false : true;

        $result = array('id' => $row['id'],
                        'product' => array('id' => $row['product'],
                                            'name' => $row['name']),
                        'code' => $row['code'],
                        'subname' => $row['subname'],
                        'name_price' => $row['name_price'],
                        'bulk_value' => $row['bulk_value'],
                        'bulk_untils' => $row['bulk_untils'],
                        'price' => $row['price'],
                        'cashback_percent' => $row['cashback_percent'],
                        'minutes' => $row['preparing_minutes'],
                        'seconds' => $row['preparing_seconds'],
                        'color' => array('id' => $row['color'],
                                        'code' => $row['color_code']),
                        'different_price' => $editing_allowed ? $row['different_price'] : 1,
                        'editing_allowed' => $editing_allowed,
                        'not_promotion' => $row['not_promotion'],
                        'cooking_method' => $row['cooking_method'],
                        'items' => [],
                        'points' => []);

        /* $items = DB::query('SELECT pc.*, i.name, i.untils
                                    FROM '.DB_PRODUCT_COMPOSITION.' pc
                                    LEFT JOIN '.DB_ITEMS.' AS i ON i.id = pc.item
                                    WHERE pc.technical_card = '.$technical_card); */

        $items = DB::query('SELECT pc.*, i.name, i.untils, IF(i.untils = "шт", AVG(pi.price) * pc.count, AVG(pi.price) * pc.gross) AS cost_price, i.bulk
                                    FROM '.DB_PRODUCT_COMPOSITION.' pc
                                    LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = pc.item AND pi.partner = '.$userToken['id'].'
                                    LEFT JOIN '.DB_ITEMS.' AS i ON i.id = pc.item
                                    WHERE pc.technical_card = '.$technical_card.'
                                    GROUP BY pc.id');

        while($row = DB::getRow($items))
            $result['items'][] = array('id' => $row['id'],
                                        'item' => array('id' => $row['item'],
                                                        'name' => $row['name']),
                                        'count' => $row['count'],
                                        'cleaning' => $row['cleaning'],
                                        'cooking' => $row['cooking'],
                                        'frying' => $row['frying'],
                                        'bulk' => $row['bulk'],
                                        'stew' => $row['stew'],
                                        'bake' => $row['bake'],
                                        'cleaning_checked' => $row['cleaning_checked'],
                                        'cooking_checked' => $row['cooking_checked'],
                                        'frying_checked' => $row['frying_checked'],
                                        'stew_checked' => $row['stew_checked'],
                                        'bake_checked' => $row['bake_checked'],
                                        'mass_block' => $row['mass_block'],
                                        'untils' => $row['untils'],
                                        'gross' => $row['gross'],
                                        'net_mass' => $row['net_mass'],
                                        'cost_price' => round($row['cost_price'], 2));

        //$points = DB::select('point AS id, price, hide', DB_PRODUCT_PRICES, 'technical_card = '.$technical_card.' AND partner = '.$userToken['id']);
        $points = DB::query('SELECT p.id, pp.price, pp.hide
                                    FROM '.DB_PARTNER_POINTS.' p
                                    LEFT JOIN '.DB_PRODUCT_PRICES.' pp ON pp.point = p.id AND pp.technical_card = '.$technical_card.' AND pp.partner = '.$userToken['id'].'
                                    WHERE p.partner = '.$userToken['id']);

        while($row = DB::getRow($points)){
            $row['price'] = ($row['price'] == null) ? $result['price'] : $row['price'];
            $row['hide'] = (bool)$row['hide'];
            $result['points'][] = $row;
        }

        response('success', $result, '7');

    break;

    case 'delete':

        if(!$technical_card = DB::escape($_REQUEST['technical_card']))
            response('error', array('msg' => 'Выберите тех. карту.'), '371');

        $technical_card_data = DB::select('id', DB_TECHNICAL_CARD, 'id = '.$technical_card.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($technical_card_data) == 0)
            response('error', array('msg' => 'Тех. карта не найдена.'), '389');

        if(DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card))
            response('success', array('msg' => 'Тех. карта удалена.'), '627');
        else
            response('error', '', '503');

    break;

    case 'edit':

        if(!$technical_card = DB::escape($_REQUEST['technical_card']))
            response('error', array('msg' => 'Выберите тех. карту.'), '371');

        $technical_card_data = DB::select('id, partner', DB_TECHNICAL_CARD, 'id = '.$technical_card.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($technical_card_data) == 0)
            response('error', array('msg' => 'Тех. карта не найдена.'), '389');

        if(!$product = DB::escape($_REQUEST['product'])) 
            response('error', array('msg' => 'Выберите Товар.'), '336');

        $productData = DB::select('id, partner, image', DB_PRODUCTS, 'id = '.$product.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($productData) == 0)
            response('error', array('msg' => 'Товар не найден.'), '337');

        if($code = DB::escape($_REQUEST['code'])){
            if(strlen($code) > 32)
                response('error', array('msg' => 'Штрих-код не может превышать больше 32-х символов.'), '588');
            $fields['code'] = $code;
        }

        $productData = DB::getRow($productData);

        //Получаем данные о тех. карте
        $technical_card_data = DB::getRow($technical_card_data);
        
        //Если партнер не указан и текущий пользователь не администратор, то редактирование блокируется
        $editing_allowed = ($technical_card_data['partner'] == null && !$userToken['admin']) ? false : true;

        if(!$bulk_untils = DB::escape($_REQUEST['bulk_untils']))
            response('error', array('msg' => 'Выберите единицы измерения.'), '340');

        if($bulk_untils == 'шт')
            $untils = 'количество.';

        if($bulk_untils == 'л')
            $untils = 'объем.';

        if($bulk_untils == 'кг')
            $untils = 'массу.';

        if(!$bulk_value = DB::escape($_REQUEST['bulk_value']))
            response('error', array('msg' => 'Укажите '.$untils), '339');

        if($editing_allowed){

            $cashback_percent = DB::escape($_REQUEST['cashback_percent']);
            if($cashback_percent < 0 || $cashback_percent > 100)
                response('error', 'Процент кэшбэка должен находиться в пределах от 0 до 100.', 422);

            $fields['product'] = $product;
            $fields['subname'] = DB::escape($_REQUEST['subname']);
            $fields['name_price'] = DB::escape($_REQUEST['name_price']);
            $fields['cashback_percent'] = $cashback_percent;
            $fields['bulk_value'] = $bulk_value;
            $fields['bulk_untils'] = $bulk_untils;
            $fields['preparing_seconds'] = 0;

            //минуты приготовления
            if($minutes = DB::escape($_REQUEST['minutes'])){
                if($minutes < 0)
                    $minutes = 0;
                $fields['preparing_minutes'] = $minutes;
            }
            else
                $fields['preparing_minutes'] = 0;

            //Секунды приготовления
            if($seconds = DB::escape($_REQUEST['seconds'])){

                if($seconds < 0)
                    $seconds = 0;

                if($seconds > 60){

                    $fields['preparing_minutes'] += floor($seconds / 60);

                    $seconds -= floor($seconds / 60) * 60;

                }

                $fields['preparing_seconds'] = $seconds;

            }
            else
                $fields['preparing_seconds'] = 0;

            if($cooking_method = DB::escape($_REQUEST['cooking_method']))
                $fields['cooking_method'] = $cooking_method;

            if($color = DB::escape($_REQUEST['color'])){
                $colorData = DB::select('id', DB_COLORS, 'id = '.$color);

                if(DB::getRecordCount($colorData) == 0)
                    response('error', array('msg' => 'Такого цвета не существует.'), '332');

                $fields['color'] = $color;

            }

            //$fields['different_price'] = (DB::escape($_REQUEST['different_price'])) ? 1 : 0;
            $different_price = DB::escape($_REQUEST['different_price']);
            $fields['different_price'] = $bool_string[$different_price];

            if($not_promotion = DB::escape($_REQUEST['not_promotion']))
                $fields['not_promotion'] = $bool_string[$not_promotion];

            if($subname = DB::escape($_REQUEST['subname']))
                $fields['subname'] = $subname;

            $technical_card_data = DB::query('
                SELECT tc.id, a.id AS arch
                FROM '.DB_TECHNICAL_CARD.' tc
                LEFT JOIN '.DB_ARCHIVE.' a ON a.product_id = tc.id AND a.model = "technical_card" AND partner_id = '.$userToken['id'].'
                WHERE (tc.partner IS NULL OR tc.partner = '.$userToken['id'].') AND tc.id != '.$technical_card.' AND tc.product = '.$product.' AND tc.bulk_value = "'.$bulk_value.'" AND tc.bulk_untils = "'.$bulk_untils.'" AND tc.subname = "'.$subname.'"');

            if(DB::getRecordCount($technical_card_data) != 0) {
                $arch = DB::getRow($technical_card_data)['arch'];
                if($arch == null)
                    response('error', array('msg' => 'Такая тех. карта уже существует.'), '538');
                else
                    response('error', array('msg' => 'Данная тех. карта находится в архиве, восстановите её.'), '538');
            }

            //$fields['not_promotion'] = (DB::escape($_REQUEST['not_promotion'])) ? 1 : 0;
            $fields['price'] = (DB::escape($_REQUEST['price'])) ? DB::escape($_REQUEST['price']) : 0;
            //Обновление технической карты
            if(!DB::update($fields, DB_TECHNICAL_CARD, 'id = '.$technical_card))
                response('error', '', '503');
            //После добавления тех карты, необходимо добавить в БД ингредиенты из которых состоит продукт
            $items_class = new ItemsClass(false, $userToken['id'], true);
            $newItems = $items_class->validate();

            DB::delete(DB_PRODUCT_COMPOSITION, 'technical_card = '.$technical_card);

            //Формирование запроса на добавление ингредиентов
            $itemsQuery = ItemsInsertQuery($newItems, $technical_card);

            if(!$itemsInsert = DB::query($itemsQuery)){
                DB::delete(DB_TECHNICAL_CARD, 'id = '.$technical_card);
                response('error', '', '503');
            }
        }

        //Создание записей в БД с разными ценами на каждую точку
        DB::delete(DB_PRODUCT_PRICES, 'technical_card = '.$technical_card.' AND partner = '.$userToken['id']);
        $insertInfo = DifferentPrice($product, $technical_card, $userToken['id'], $fields['different_price'], $fields['price']);
        /*else
            $insertInfo = TotalPrice($product, $technical_card, $userToken['id'], DB_PRODUCT_PRICES, DB_PARTNER_POINTS, $price);
        */

        response('success', array('msg' => 'Техническая карта изменена.'), '628');

    break;

    case 'composition_description':

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не передан ID тех. карты.', 422);

        if(!$description = DB::escape($_REQUEST['description']))
            $description = '';

        DB::update(
            ['composition_description' => $description],
            DB_TECHNICAL_CARD,
            'id = '.$id.' AND (partner = '.$userToken['id'].' OR partner IS NULL)'
        );

        response('success', 'Изменения сохранены.', 201);

        break;

}