<?php

use Controllers\Models\ItemModel;
use Support\Pages;
use Support\DB;

include ROOT . 'api/partner/tokenCheck.php';
require ROOT . 'api/classes/ProductionsClass.php';
require ROOT . 'api/classes/OrderClass.php';

function CategoriesFilter($categories)
{

    $categories = explode(',', $categories);

    for ($i = 0; $i < sizeof($categories); $i++) {

        if (!$result)
            $result = ' AND (i.category = ' . $categories[$i];
        else
            $result .= ' OR i.category = ' . $categories[$i];
    }

    $result .= ')';

    return $result;
}

function UntilsFilter($untils)
{

    $untils = explode(',', $untils);

    for ($i = 0; $i < sizeof($untils); $i++) {

        if (!$result)
            $result = ' AND (i.untils = "' . $untils[$i] . '"';
        else
            $result .= ' OR i.untils = "' . $untils[$i] . '"';
    }

    $result .= ')';

    return $result;
}

switch ($action) {

    case 'add':

        if (!$name = trim(DB::escape($_REQUEST['name'])))
            response('error', array('msg' => 'Введите наименование ингредиента.'), '314');

        $item_exist = DB::query('
            SELECT i.id, a.id AS arch
            FROM ' . DB_ITEMS . ' i
            LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = i.id AND a.model = "item" AND a.partner_id = ' . $userToken['id'] . '
            WHERE i.name = "' . $name . '" AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL)
        ');

        if (DB::getRecordCount($item_exist) > 0) {
            $arch = DB::getRow($item_exist)['arch'];
            if ($arch == null)
                response('error', 'Такой ингредиент уже существует.', 1);
            else
                response('error', 'Ингредиент находится в архиве, восстановите его.', 1);
        }

        $category = DB::escape($_REQUEST['category']);

        if ($category) {

            $categoryData = DB::select('id', DB_ITEMS_CATEGORY, 'id = ' . $category . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

            if (DB::getRecordCount($categoryData) == 0)
                response('error', array('msg' => 'Такой категории не существует.'), '316');
        }

        if (!$untils = DB::escape($_REQUEST['untils']))
            response('error', array('msg' => 'Выберите единицы измерения.'), '317');

        if ($untils != 'шт' && $untils != 'л' && $untils != 'кг')
            response('error', array('msg' => 'Неверные данные в untils. Ожидается: "шт", "л" или "кг".'), '318');

        if (!$cleaning = DB::escape($_REQUEST['cleaning']))
            $cleaning = 0;
        if (!$cooking = DB::escape($_REQUEST['cooking']))
            $cooking = 0;
        if (!$frying = DB::escape($_REQUEST['frying']))
            $frying = 0;
        if (!$stew = DB::escape($_REQUEST['stew']))
            $stew = 0;
        if (!$bake = DB::escape($_REQUEST['bake']))
            $bake = 0;
        if (!$untils = DB::escape($_REQUEST['untils']))
            $untils = 0;


        if ($untils != 'шт') {

            if ($cleaning < 0 || $cleaning > 100)
                response('error', array('msg' => 'Процент потерь не может быть меньше 0% или больше 100%.'), '319');


            if ($cooking < 0 || $cooking > 100)
                response('error', array('msg' => 'Процент потерь не может быть меньше 0% или больше 100%.'), '319');

            if ($frying < 0 || $frying > 100)
                response('error', array('msg' => 'Процент потерь не может быть меньше 0% или больше 100%.'), '319');


            if ($stew < 0 || $stew > 100)
                response('error', array('msg' => 'Процент потерь не может быть меньше 0% или больше 100%.'), '319');


            if ($bake < 0 || $bake > 100)
                response('error', array('msg' => 'Процент потерь не может быть меньше 0% или больше 100%.'), '319');


            if ($untils < 0 || $untils > 100)
                response('error', array('msg' => 'Процент потерь не может быть меньше 0% или больше 100%.'), '319');
        }

        $fields = array(
            'name' => $name,
            'partner' => $userToken['id'],
            'untils' => $untils,
            'cleaning' => $cleaning,
            'cooking' => $cooking,
            'frying' => $frying,
            'stew' => $stew,
            'bake' => $bake,
            'untils' => $untils,
            'round' => DB::escape($_REQUEST['round'] ? 1 : 0)
        );

        if (($conversion_item_id = DB::escape($_REQUEST['conversion_item_id'])) && !is_null($conversion_item_id) && !empty($conversion_item_id) && $conversion_item_id != "null" && $conversion_item_id != "false") {

            $convertion_item = DB::select('id, untils', DB_ITEMS, "id = {$conversion_item_id}", '', 1);
            if (!DB::getRecordCount($convertion_item))
                response('error', 'Ингредиент не найден.', 422);

            $convertion_item = DB::getRow($convertion_item);

            if ($convertion_item['untils'] != $untils)
                response('error', 'Единицы измерения ингредиента должны совпадать с единицами измерения конвертируемого ингредиента.', 422);

            $fields['conversion_item_id'] = $conversion_item_id;
        }

        if ($category)
            $fields['category'] = $category;

        $fields['bulk'] = (DB::escape($_REQUEST['bulk'])) ? DB::escape($_REQUEST['bulk']) / 1000 : 1;

        if ($id = DB::insert($fields, DB_ITEMS)) {
            $fields['id'] = $id;
            // ItemModel::add($fields);

            response('success', array('msg' => 'Ингредиент добавлен.'), '603');
        } else
            response('error', '', '503');

        break;

    case 'edit':

        if (!$item = DB::escape($_REQUEST['item']))
            response('error', array('msg' => 'Выберите ингредиент.'), '324');

        $itemData = DB::select('*', DB_ITEMS, 'id = ' . $item . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

        if (DB::getRecordCount($itemData) == 0)
            response('error', array('msg' => 'Такого ингредиента не существует.'), '325');

        $itemData = DB::getRow($itemData);

        $editing_allowed = ($itemData['partner'] == null && !$userToken['admin']) ? false : true;

        if (!$editing_allowed)
            response('error', array('msg' => 'Вы не можете редактировать общедоступный ингредиент.'), '326');

        $fields = [];

        if ($name = trim(DB::escape($_REQUEST['name'])))
            $fields['name'] = $name;

        $item_exist = DB::query('
            SELECT i.id, a.id AS arch
            FROM ' . DB_ITEMS . ' i
            LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = i.id AND a.model = "item" AND a.partner_id = ' . $userToken['id'] . '
            WHERE i.id != ' . $item . ' AND i.name = "' . $name . '" AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL)
        ');

        if (DB::getRecordCount($item_exist) > 0) {
            $arch = DB::getRow($item_exist)['arch'];
            if ($arch == null)
                response('error', 'Такой ингредиент уже существует.', 1);
            else
                response('error', 'Ингредиент находится в архиве, восстановите его.', 1);
        }

        if ($category = (int)DB::escape($_REQUEST['category'])) {

            $categoryData = DB::select('id', DB_ITEMS_CATEGORY, 'id = ' . $category . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

            if (DB::getRecordCount($categoryData) == 0)
                response('error', array('msg' => 'Такой категории не существует.'), '316');

            $fields['category'] = $category;
        }

        if ($untils = DB::escape($_REQUEST['untils'])) {

            if ($untils != 'шт' && $untils != 'л' && $untils != 'кг')
                response('error', array('msg' => 'Неверные данные в untils. Ожидается: "шт", "л" или "кг".'), '318');

            $fields['untils'] = $untils;
        }

        foreach (DB::escape($_REQUEST) as $key => $value) {

            if ($key == 'cleaning' || $key == 'cooking' || $key == 'frying' || $key == 'stew' || $key == 'bake') {

                if ($value < 0 || $value > 100)
                    response('error', array('msg' => 'Процент потерь не может быть меньше 0% или больше 100%.'), '319');

                $fields[$key] = $value;
            }
        }

        $fields['round'] = DB::escape($_REQUEST['round']) ? 1 : 0;
        $fields['bulk'] = DB::escape($_REQUEST['bulk']) ? DB::escape($_REQUEST['bulk']) / 1000 : 1;

        if (($conversion_item_id = DB::escape($_REQUEST['conversion_item_id'])) && !is_null($conversion_item_id) && !empty($conversion_item_id) && $conversion_item_id != "null" && $conversion_item_id != "false") {

            $convertion_item = DB::select('id, untils', DB_ITEMS, "id = {$conversion_item_id}", '', 1);
            if (!DB::getRecordCount($convertion_item))
                response('error', 'Ингредиент не найден.', 422);

            $convertion_item = DB::getRow($convertion_item);

            if ($convertion_item['untils'] != $untils)
                response('error', 'Единицы измерения ингредиента должны совпадать с единицами измерения конвертируемого ингредиента.', 422);

            $fields['conversion_item_id'] = $conversion_item_id;
        }
        //else
        // $fields['conversion_item_id'] = NULL;

        if ($fields['bulk'] != $itemData['bulk']) {
            $class = new ProductionsParent(false);
            $itemData['new_bulk'] = $fields['bulk'];
            $class->update($itemData);
        }

        if (sizeof($fields) == 0)
            response('success', array('msg' => 'Информация об ингредиенте изменена.'), '606');

        if (DB::update($fields, DB_ITEMS, 'id = ' . $item)) {
            // ItemModel::update($item, $fields);
            response('success', array('msg' => 'Информация об ингредиенте изменена.'), '606');
        } else
            response('error', '', '503');

        break;

    case 'get':

        $full = DB::escape($_REQUEST['full']);

        $result = [];

        $page = DB::escape($_REQUEST['page']);

        $element_count = 50;
        if (!$page || $page == 1)
            $limit = '0,' . $element_count;
        else {
            $begin = $element_count * $page - $element_count;
            $limit = $begin . ',' . $element_count;
        }

        if ($search = DB::escape($_REQUEST['search']))
            $searchStr = ' AND (i.name LIKE "%' . $search . '%" OR ic.name LIKE "%' . $search . '%")';

        //Фильтрация по категориям
        if ($categories = DB::escape($_REQUEST['categories']))
            $categories = CategoriesFilter($categories);

        if ($untils = DB::escape($_REQUEST['untils']))
            $untils = UntilsFilter($untils);

        $archive = '
            AND i.id ' . (DB::escape($_REQUEST['archive']) ? '' : 'NOT') . ' IN (
                SELECT product_id
                FROM ' . DB_ARCHIVE . '
                WHERE model = "item" AND partner_id = ' . $userToken['id'] . '
            )';

        if (!$full) {

            $items = DB::query('SELECT i.id, i.partner, i.production, i.name, i.untils, i.bulk, ts.price
                                        FROM ' . DB_ITEMS . ' i
                                        LEFT JOIN ( SELECT s.item, s.price
                                                    FROM ' . DB_SUPPLY_ITEMS . ' s
                                                    INNER JOIN (SELECT MAX(s2.id) AS id, s2.item, s2.price
                                                                FROM ' . DB_SUPPLY_ITEMS . ' s2
                                                                JOIN ' . DB_SUPPLIES . ' sp ON sp.id = s2.supply
                                                                WHERE sp.partner = ' . $userToken['id'] . '
                                                                GROUP BY s2.item) t ON t.id = s.id) ts ON ts.item = i.id
                                        WHERE (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL) AND i.del = 0' . $searchStr . $archive . '
                                        ORDER BY i.name ASC');


            while ($row = DB::getRow($items)) {

                $row['can_share'] = ($userToken['admin'] && $row['partner'] != null) ? true : false;

                $row['price'] = round($row['price'], 2);

                if ($row['production'])
                    $row['name'] .= ' (п/ф)';

                $row['name'] .= ', ' . $row['untils'];

                $result[] = $row;
            }

            response('success', $result, '7');
        } else {

            $sorting = Order::ingredients(Pages::$field, Pages::$order);

            $items = DB::query('SELECT i.*, ic.name AS category_name, IFNULL(SUM(pi.count), 0) AS count, IFNULL(AVG(pi.price), 0) AS price, IFNULL(SUM(pi.count * pi.price), 0) AS sum
                                        FROM ' . DB_ITEMS . ' i
                                        LEFT JOIN ' . DB_ITEMS_CATEGORY . ' AS ic ON ic.id = i.category
                                        LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = i.id AND pi.partner = ' . $userToken['id'] . '
                                        WHERE (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL) AND i.production = 0 AND i.del = 0' . $searchStr . $categories . $untils . $archive . '
                                        GROUP BY i.id
                                        ' . $sorting . '
                                        LIMIT ' . Pages::$limit);

            while ($row = DB::getRow($items)) {

                $editing_allowed = ($row['partner'] == null && !$userToken['admin']) ? false : true;

                $object = array(
                    'id' => $row['id'],
                    'name' => $row['name'] . ', ' . $row['untils'],
                    'my' => $editing_allowed,
                    'can_share' => ($userToken['admin'] && $row['partner'] != null) ? true : false,
                    'category' => array(
                        'id' => $row['category'],
                        'name' => $row['category_name']
                    ),
                    'cleaning' => $row['cleaning'],
                    'cooking' => $row['cooking'],
                    'frying' => $row['frying'],
                    'stew' => $row['stew'],
                    'bake' => $row['bake'],
                    'untils' => $row['untils'],
                    'bulk' => (float)$row['bulk'],
                    'stock_balance' => number_format($row['count'], 2, ',', ' ') . ' ' . $row['untils'],
                    'cost_price' => number_format($row['price'], 2, ',', ' ') . ' ' . CURRENCY,
                    'stock_balance_sum' => number_format($row['sum'], 2, ',', ' ') . ' ' . CURRENCY
                );

                $result[] = $object;
            }

            $pages = DB::query('SELECT COUNT(t.id) AS count
                                        FROM (SELECT i.id
                                            FROM ' . DB_ITEMS . ' i
                                            LEFT JOIN ' . DB_ITEMS_CATEGORY . ' AS ic ON ic.id = i.category
                                            LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = i.id AND pi.partner = ' . $userToken['id'] . '
                                            WHERE (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL) AND i.production = 0 AND i.del = 0' . $searchStr . $categories . $untils . $archive . '
                                            GROUP BY i.id) t');

            $pages = DB::getRow($pages);

            if ($pages['count'] != null) {
                $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
            } else
                $total_pages = 0;

            if (!$page)
                $page = 1;

            $pageData = array(
                'current_page' => (int)Pages::$page,
                'total_pages' => $total_pages,
                'rows_count' => (int)$pages['count'],
                'page_size' => ELEMENT_COUNT
            );

            response('success', $result, '7', $pageData);
        }

        break;

    case 'delete':

        if (!$item = DB::escape($_REQUEST['item']))
            response('error', array('msg' => 'Выберите ингредиент.'), '324');

        $itemData = DB::select('*', DB_ITEMS, 'id = ' . $item . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

        if (DB::getRecordCount($itemData) == 0)
            response('error', array('msg' => 'Такого ингредиента не существует.'), '325');

        $itemData = DB::getRow($itemData);

        /* $supply_item = DB::select('id', DB_SUPPLY_ITEMS, 'item = '.$item, '', 1);

        if(DB::getRecordCount($supply_item) != 0)
            response('error', 'Нельзя удалить ингредиент, т.к. он участвует в поставке.', 599); */

        /* if($itemData['partner'] == null)
            response('error', array('msg' => 'Вы не можете удалить общедоступный ингредиент.'), '341'); */

        if ($itemData['partner'] == null && !$userToken['admin'])
            response('error', array('msg' => 'Вы не можете удалить общедоступный ингредиент.'), '341');

        //if(DB::delete(DB_ITEMS, 'id = '.$item))
        if (DB::update(array('del' => 1), DB_ITEMS, 'id = ' . $item))
            response('success', array('msg' => 'Ингредиент удален.'), '614');

        response('error', '', '503');

        break;

    case 'info':

        if (!$item = DB::escape($_REQUEST['item']))
            response('error', array('msg' => 'Выберите ингредиент.'), '324');

        $itemData = DB::query('SELECT i.*, c.name AS cname
                                    FROM ' . DB_ITEMS . ' i
                                    LEFT JOIN ' . DB_ITEMS_CATEGORY . ' AS c ON c.id = i.category
                                    WHERE i.id = ' . $item . ' AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL)');

        if (DB::getRecordCount($itemData) == 0)
            response('error', array('msg' => 'Такого ингредиента не существует.'), '325');

        $itemData = DB::getRow($itemData);

        $itemData['bulk'] *= 1000;
        $itemData['round'] = (int)$itemData['round'];

        $itemData['category'] = array(
            'id' => $itemData['category'],
            'name' => $itemData['cname']
        );

        unset($itemData['partner']);
        unset($itemData['cname']);

        response('success', $itemData, '7');

        break;
}
