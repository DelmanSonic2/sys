<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/lib/functions.php';
require ROOT.'api/classes/InventoryClass.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'add':

        $fields = [];

        if(!$fields['name'] = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Не передано название заведения.'), '327');

        if(!$fields['address'] = DB::escape($_REQUEST['address']))
            response('error', array('msg' => 'Не передан адрес заведения.'), '328');

        if($login = DB::escape($_REQUEST['login'])){

            $pointAccessData = DB::select('id', DB_PARTNER_POINTS, 'login = "'.$login.'"');

            if(DB::getRecordCount($pointAccessData) != 0)
                response('error', array('msg' => 'Логин "'.$login.'" уже занят.'), '509');

            $fields['login'] = $login;

        }

        if($password = DB::escape($_REQUEST['password'])){

            if (strlen($password) < 8)
                response('error', array('msg' => 'Пароль не может содержать меньше 8 символов.'), '303');

            $pattern = "/[\\\~^°\"\/`';,\.:_{\[\]}\|<>]/";

            if (preg_match($pattern, $password, $matches))
                response('error', array('msg' => 'В пароле присутствуют недопустимые символы: "' . $matches[0] . '".'), '304');

            $password = password_hash($password, PASSWORD_DEFAULT);

            $fields['password'] = $password;

        }

        $fields['partner'] = $userToken['id'];

        if(!$point = DB::insert($fields, DB_PARTNER_POINTS))
            response('error', '', '503');

        //Выбираем тех. карты с галочкой "Разная цена на разных точках"
        $technical_cards = DB::select('id, product, price', DB_TECHNICAL_CARD, '(different_price = 1 AND partner = '.$userToken['id'].') OR partner IS NULL');

        while($row = DB::getRow($technical_cards)){

            if(!$product_prices)
                $product_prices = '("'.$point.'", "'.$row['product'].'", "'.$row['id'].'", "'.$userToken['id'].'", "'.$row['price'].'")';
            else
                $product_prices .= ',("'.$point.'", "'.$row['product'].'", "'.$row['id'].'", "'.$userToken['id'].'", "'.$row['price'].'")';

        }

        //Добавляем цены продуктов на точку
        if($product_prices)
            DB::query('INSERT INTO '.DB_PRODUCT_PRICES.' (point, product, technical_card, partner, price) VALUES '.$product_prices);

        //Достаем общедоступные продукты
        $menu_products = DB::select('id', DB_PRODUCTS, 'partner IS NULL OR partner = '.$userToken['id']);

        //Формируем список продуктов для отображения на новой точке
        while($row = DB::getRow($menu_products)){

            if(!$menu_products_insert)
                $menu_products_insert = '("'.$userToken['id'].'", "'.$point.'", "'.$row['id'].'")';
            else
                $menu_products_insert .= ', ("'.$userToken['id'].'", "'.$point.'", "'.$row['id'].'")';

        }

        //Добавляем продукты на точку для отображения в меню
        if($menu_products_insert)
            DB::query('INSERT IGNORE INTO '.DB_MENU_PRODUCTS.' (partner, point, product) VALUES '.$menu_products_insert);

        //Достаем общедоступные категории
        $menu_categories = DB::select('id', DB_PRODUCT_CATEGORIES, 'partner IS NULL OR partner = '.$userToken['id']);

        //Формируем список категорий для отображения на новой точке
        while($row = DB::getRow($menu_categories)){

            if(!$menu_category_insert)
                $menu_category_insert = '("'.$userToken['id'].'", "'.$point.'", "'.$row['id'].'")';
            else
                $menu_category_insert .= ', ("'.$userToken['id'].'", "'.$point.'", "'.$row['id'].'")';

        }

        //Открываем инвентаризацию с открытием точки
        $inv_class = new InventoryOpen(false, $userToken['id'], $point, time(), time(), $userToken['employee']);
        $inv_class->open();

        //Добавляем категории на точку для отображения в меню
        if($menu_category_insert)
            DB::query('INSERT IGNORE INTO '.DB_MENU_CATEGORIES.' (partner, point, category) VALUES '.$menu_category_insert);

        response('success', array('msg' => 'Заведение добавлено.'), '607');

    break;

    case 'get':

        $full = DB::escape($_REQUEST['full']);

        $result = [];

        if(!$full){

            $partner = (DB::escape($_REQUEST['partner']) && $userToken['admin']) ? DB::escape($_REQUEST['partner']) : $userToken['id'];

            if(DB::escape($_REQUEST['regions'])){
                $regions_query = DB::select('id', DB_PARTNER, 'parent = '.$partner);
                while($row = DB::getRow($regions_query))
                    $regions[] = $row['id'];
                if(!empty($regions) && sizeof($regions))
                    $regions = ' OR FIND_IN_SET(partner, "'.implode(',', $regions).'")';
                else
                    $regions = '';
            }

            $points = DB::select('id, name, inn', DB_PARTNER_POINTS, 'partner = '.$partner.$regions, 'name ASC');

            $points = DB::makeArray($points);

            response('success', $points, '7');

        }
        elseif($full == 'moving'){

            $result = array(
                'points' => [],
                'all_points' => []
            );

            $points = DB::query('
                SELECT pp.id, pp.name, c.name AS city, p.id AS partner
                FROM '.DB_PARTNER_POINTS.' pp
                JOIN '.DB_PARTNER.' p ON p.id = pp.partner
                JOIN '.DB_CITIES.' c ON c.id = p.city
                WHERE p.parent = '.$userToken['id'].' OR p.id = '.$userToken['id'].'
                ORDER BY p.id ASC, c.name ASC, pp.name ASC
            ');

            while($row = DB::getRow($points)){

                if($row['partner'] == $userToken['id'])
                    $result['points'][] = array(
                        'id' => $row['id'],
                        'name' => $row['name']
                    );

                $result['all_points'][] = array(
                    'id' => $row['id'],
                    'name' => $row['name'].' ('.$row['city'].')'
                );

            }

            response('success', $result, '7');

        }
        else{

         

            $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
            $to_dyd = date('Ym', $to);

            /* $points = DB::query('SELECT pp.id, pp.name, pp.address, pp.login, SUM(IF(t.untils = "шт", t.balance_end, 0)) AS balance_count, SUM(IF(t.untils = "л", t.balance_end, 0)) AS balance_volume, SUM(IF(t.untils = "кг", t.balance_end, 0)) AS balance_weight, SUM(t.balance_end * t.average_price_end) AS sum
                                        FROM '.DB_PARTNER_POINTS.' pp
                                        LEFT JOIN ( SELECT i.id, i.name, i.untils, tr.balance_end, tr.average_price_end, tr.point
                                                    FROM '.DB_PARTNER_TRANSACTIONS.' tr
                                                    INNER JOIN (SELECT item, MAX(id) AS maxid
                                                                FROM '.DB_PARTNER_TRANSACTIONS.'
                                                                WHERE date < '.$to.' AND partner = '.$userToken['id'].'
                                                                GROUP BY item, point) AS tr2 ON tr.id = tr2.maxid
                                                    INNER JOIN '.DB_ITEMS.' AS i ON i.id = tr.item
                                                    GROUP BY tr.item, tr.point) t ON t.point = pp.id
                                        WHERE pp.partner = '.$userToken['id'].'
                                        GROUP BY pp.id
                                        ORDER BY pp.name'); */

            $sorting = Order::point(Pages::$field, Pages::$order);


           

            $points = DB::query('SELECT pp.id, pp.name, pp.address, pp.login, SUM(IF(t.untils = "шт", t.count, 0)) AS balance_count, SUM(IF(t.untils = "л", t.count, 0)) AS balance_volume, SUM(IF(t.untils = "кг", t.count, 0)) AS balance_weight, SUM(t.total_sum) AS sum, pp.balance AS bill_sum
                                        FROM '.DB_PARTNER_POINTS.' pp
                                        LEFT JOIN (
                                            SELECT pi.point, i.id, i.name, i.untils, (pi.count - IFNULL(t.dif, 0)) AS count, ((pi.price * pi.count) - IFNULL(t.r_total, 0)) AS total_sum
                                            FROM '.DB_POINT_ITEMS.' pi
                                            JOIN '.DB_ITEMS.' i ON pi.item = i.id
                                            LEFT JOIN (
                                                SELECT item, SUM(count) AS dif, SUM(total) AS r_total, point
                                                FROM '.DB_PARTNER_TRANSACTIONS.'
                                                WHERE date >= '.$to.' AND dyd >= '.$to_dyd.' AND partner = '.$userToken['id'].'
                                                GROUP BY item, point
                                            ) t ON t.item = pi.item AND t.point = pi.point
                                            WHERE pi.partner = '.$userToken['id'].'
                                        ) t ON t.point = pp.id
                                        WHERE pp.partner = '.$userToken['id'].'
                                        GROUP BY pp.id
                                        '.$sorting);

            while($row = DB::getRow($points)){

                if($row['balance_count'] != 0)
                    $balance = number_format($row['balance_count'], 0, ',', ' ').' шт';
                if($row['balance_volume'] != 0){

                    if(!$balance)
                        $balance = number_format($row['balance_volume'], 3, ',', ' ').' л';
                    else
                        $balance .= ', '.number_format($row['balance_volume'], 3, ',', ' ').' л';

                }
                if($row['balance_weight'] != 0){

                    if(!$balance)
                        $balance = number_format($row['balance_weight'], 3, ',', ' ').' кг';
                    else
                        $balance .= ', '.number_format($row['balance_weight'], 3, ',', ' ').' кг';

                }

                if($row['balance_count'] == 0 && $row['balance_volume'] == 0 && $row['balance_weight'] == 0)
                    $balance = 0;

                $result[] = array('id' => $row['id'],
                                'name' => $row['name'],
                                'address' => $row['address'],
                                'sum' => number_format($row['sum'], 2, ',', ' ').' '.CURRENCY,
                                'sum_origin' => round($row['sum'],2),
                                'balance' => $balance,
                                'login' => $row['login'],
                                'bill_sum_origin' => round($row['bill_sum'],2),
                                'bill_sum' => number_format($row['bill_sum'], 2, ',', ' ').' '.CURRENCY);

            }

            response('success', $result, '7');
        }

    break;

    case 'edit':

        $fields = [];

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Не передан ID заведения.'), '329');

        $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$userToken['id'].' AND id = '.$point);
        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

        if($name = DB::escape($_REQUEST['name']))
            $fields['name'] = $name;

        if($address = DB::escape($_REQUEST['address']))
            $fields['address'] = $address;

        if($login = DB::escape($_REQUEST['login'])){

            $pointAccessData = DB::select('id', DB_PARTNER_POINTS, 'login = "'.$login.'" AND id != '.$point);

            if(DB::getRecordCount($pointAccessData) != 0)
                response('error', array('msg' => 'Логин "'.$login.'" уже занят.'), '509');

            $fields['login'] = $login;

        }

        if($password = DB::escape($_REQUEST['password'])){

            if (strlen($password) < 8)
                response('error', array('msg' => 'Пароль не может содержать меньше 8 символов.'), '303');

            $pattern = "/[\\\~^°\"\/`';,\.:_{\[\]}\|<>]/";

            if (preg_match($pattern, $password, $matches))
                response('error', array('msg' => 'В пароле присутствуют недопустимые символы: "' . $matches[0] . '".'), '304');

            $password = password_hash($password, PASSWORD_DEFAULT);

            $fields['password'] = $password;

        }

        if(sizeof($fields) == 0)
            response('error', array('msg' => 'Не передано ни одного параметра.'), '330');

        if(DB::update($fields, DB_PARTNER_POINTS, 'id = '.$point))
            response('success', array('msg' => 'Информация о заведении изменена.'), '608');
        else
            response('error', array('msg' => 'Произошла ошибка.'), '503');

    break;

    case 'delete':

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Не передан ID заведения.'), '329');

        $pointData = DB::select('*', DB_PARTNER_POINTS, 'partner = '.$userToken['id'].' AND id = '.$point);
        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

        if(DB::delete(DB_PARTNER_POINTS, 'id = '.$point))
            response('success', array('msg' => 'Заведение удалено.'), '638');
        else
            response('error', '', '503');

    break;

    case 'info':

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Не передан ID заведения.'), '329');

        $pointData = DB::select('*', DB_PARTNER_POINTS, 'partner = '.$userToken['id'].' AND id = '.$point);
        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

        $pointData = DB::getRow($pointData);

        response('success', $pointData, '7');

    break;

}