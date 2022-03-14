<?php

use Controllers\Models\ItemModel;
use Support\Pages;
use Support\DB;

include ROOT . 'api/partner/tokenCheck.php';

require ROOT . 'api/classes/ItemsClass.php';
require ROOT . 'api/classes/ProductionCostPriceClass.php';
require ROOT . 'api/classes/ProductionsClass.php';
require ROOT . 'api/classes/OrderClass.php';

//После использования удалить
function editProd($product)
{



    $tc = DB::select('*', DB_PRODUCTIONS_COMPOSITION, 'item = ' . $product['id'] . ' AND mass_block = 0');

    while ($row = DB::getRow($tc)) {

        $row['new_net'] = round($product['bulk'] * $row['count'], 3);

        if ($row['new_net'] != $row['net_mass']) {
            DB::update(array('net_mass' => $row['new_net']), DB_PRODUCTIONS_COMPOSITION, 'id = ' . $row['id']);
            echo 'ПФ обновлены';
        }
    }
}

//После использования удалить
function editTechcard($product)
{



    $tc = DB::select('*', DB_PRODUCT_COMPOSITION, 'item = ' . $product['id'] . ' AND mass_block = 0');

    while ($row = DB::getRow($tc)) {

        $row['new_net'] = round($product['bulk'] * $row['count'], 3);

        if ($row['new_net'] != $row['net_mass']) {
            DB::update(array('net_mass' => $row['new_net']), DB_PRODUCT_COMPOSITION, 'id = ' . $row['id']);
            echo 'Тех.карты обновлены';
        }
    }
}

//Получает штрихкоды
function getCode($products)
{



    $ids = [];

    foreach ($products as $product)
        $ids[] = $product['id'];

    if (!sizeof($ids))
        return $products;

    $codes = DB::query('
        SELECT tc.id, tc.code, COUNT(tc.id) AS count, i.id
        FROM ' . DB_TECHNICAL_CARD . ' tc
        JOIN ' . DB_PRODUCT_COMPOSITION . ' pc ON pc.technical_card = tc.id
        JOIN ' . DB_ITEMS . ' i ON pc.item = i.id AND i.production = 1
        WHERE tc.code != ""
        GROUP BY tc.id
        HAVING count = 1 AND i.id IN (' . implode(',', $ids) . ')
    ');

    while ($row = DB::getRow($codes)) {
        for ($i = 0; $i < sizeof($products); $i++) {
            if ($products[$i]['id'] == $row['id'] && !$products[$i]['code']) {
                $products[$i]['code'] = $row['code'];
                break;
            }
        }
    }

    return $products;
}

switch ($action) {

    case 'create':

        if (!$name = trim(DB::escape($_REQUEST['name'])))
            response('error', 'Введите название полуфабриката.', 1);

        if (!$untils = DB::escape($_REQUEST['untils']))
            response('error', 'Выберите ед. измерения.', 1);

        $untils_arr = ['кг', 'шт', 'л'];

        if (!in_array($untils, $untils_arr))
            response('error', 'Выберите ед. измерения.', 1);

        $item_data = DB::select('id', DB_ITEMS, 'name = "' . $name . '" AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

        if (DB::getRecordCount($item_data) != 0)
            response('error', 'Ингредиент или полуфабрикат с таким названием уже существует.', 1);

        $items_class = new ItemsClass(false, $userToken['id'], true);
        $items = $items_class->validate();

        if (!$items_class->net_mass)
            response('error', 'Общая масса НЕТТО не может быть равна нулю!', 422);

        $fields = array(
            'name' => $name,
            'partner' => $userToken['id'],
            'untils' => $untils,
            'bulk' => $items_class->net_mass,
            'production' => 1
        );

        if ($category = DB::escape($_REQUEST['category']))
            $fields['product_category'] = $category;

        $product = DB::insert($fields, DB_ITEMS);

        $fields['id'] = $product;
        //ItemModel::add($itemData);

        //Формируем список состава производимой продукции
        for ($i = 0; $i < sizeof($items); $i++) {

            if ($items[$i]['untils'] != 'шт')
                $items[$i]['count'] = $items[$i]['gross'];

            if (!$itemsStr)
                $itemsStr = '("' . $items[$i]['untils'] . '", "' . $product . '", "' . $items[$i]['id'] . '", "' . $items[$i]['count'] . '", "' . $items[$i]['cleaning'] . '", "' . $items[$i]['cooking'] . '", "' . $items[$i]['frying'] . '", "' . $items[$i]['stew'] . '", "' . $items[$i]['bake'] . '", "' . $items[$i]['gross'] . '", "' . $items[$i]['net_mass'] . '", "' . $items[$i]['cooking_checked'] . '", "' . $items[$i]['cleaning_checked'] . '", "' . $items[$i]['frying_checked'] . '", "' . $items[$i]['stew_checked'] . '", "' . $items[$i]['bake_checked'] . '", "' . $userToken['id'] . '", "' . $items[$i]['mass_block'] . '")';
            else
                $itemsStr .= ', ("' . $items[$i]['untils'] . '", "' . $product . '", "' . $items[$i]['id'] . '", "' . $items[$i]['count'] . '", "' . $items[$i]['cleaning'] . '", "' . $items[$i]['cooking'] . '", "' . $items[$i]['frying'] . '", "' . $items[$i]['stew'] . '", "' . $items[$i]['bake'] . '", "' . $items[$i]['gross'] . '", "' . $items[$i]['net_mass'] . '", "' . $items[$i]['cooking_checked'] . '", "' . $items[$i]['cleaning_checked'] . '", "' . $items[$i]['frying_checked'] . '", "' . $items[$i]['stew_checked'] . '", "' . $items[$i]['bake_checked'] . '", "' . $userToken['id'] . '", "' . $items[$i]['mass_block'] . '")';
        }

        DB::query('INSERT INTO ' . DB_PRODUCTIONS_COMPOSITION . ' (untils, product, item, count, cleaning, cooking, frying, stew, bake, gross, net_mass, cooking_checked, cleaning_checked, frying_checked, stew_checked, bake_checked, partner, mass_block) VALUES ' . $itemsStr);

        response('success', 'Производимая продукция добавлена.', 7);

        break;

    case 'get':

        $result = [];

        if ($search = DB::escape($_REQUEST['search']))
            $search = ' AND (i.name LIKE "%' . $search . '%" OR ic.name LIKE "%' . $search . '%")';

        if ($where_category = DB::escape($_REQUEST['category']))
            $where_category = ' AND i.product_category = ' . $where_category;

        if (!$point = (int)DB::escape($_REQUEST['point']))
            response('error', 'Не выбрана точка.', 1);

        $sorting = Order::product(Pages::$field, Pages::$order);

        $archive = '
            AND i.id ' . (DB::escape($_REQUEST['archive']) ? '' : 'NOT') . ' IN (
                SELECT product_id
                FROM ' . DB_ARCHIVE . '
                WHERE model = "item" AND partner_id = ' . $userToken['id'] . '
            )';

        $products = DB::query('
            SELECT i.id, i.name, i.untils, i.bulk, i.partner,
                IF(i.print_name = "", i.name, i.print_name) AS print_name,
                IF(i.composition_description = "",
                    (SELECT GROUP_CONCAT(vi.name SEPARATOR ", ")
                    FROM ' . DB_PRODUCTIONS_COMPOSITION . ' vp
                    JOIN ' . DB_ITEMS . ' vi ON vi.id = vp.item
                    WHERE vp.product = i.id)
                , i.composition_description) AS composition_description,
                i.energy_value, i.nutrients, i.shelf_life, i.product_category, cat.name AS category_name
            FROM ' . DB_ITEMS . ' i
            LEFT JOIN ' . DB_PRODUCTIONS_COMPOSITION . ' pc ON pc.product = i.id
            LEFT JOIN ' . DB_ITEMS . ' ic ON ic.id = pc.item
            LEFT JOIN ' . DB_PRODUCT_CATEGORIES . ' cat ON cat.id = i.product_category
            WHERE i.production = 1 AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL)' . $search . $archive . $where_category . '
            GROUP BY i.id
            ' . $sorting . '
            LIMIT ' . Pages::$limit . '
        ');

        $pr_class = new ProductionCostPrice(false, $userToken['id'], $point);

        while ($row = DB::getRow($products)) {

            $bulk = number_format($row['bulk'], 3, ',', ' ');

            if ($row['untils'] == 'шт')
                $row['bulk'] = 1;

            $row['count'] = $row['bulk'];

            $row['items'] = $pr_class->subItems($row);

            $row['price'] = 0;

            $row['cost_price_calc'] = true;

            for ($j = 0; $j < sizeof($row['items']); $j++) {
                $row['price'] += $row['items'][$j]['count_price'];

                if ($row['items'][$j]['price'] == null)
                    $row['cost_price_calc'] = false;
            }

            $editing_allowed = ($row['partner'] == null && $userToken['id'] != 1) ? false : true;

            $category = $row['product_category'] ? ['id' => $row['product_category'], 'name' => $row['category_name']] : [];

            $result[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'category' => (object)$category,
                'my' => $editing_allowed,
                'can_share' =>  false, //($userToken['id'] == 1 && $row['partner'] != null) ? true : false,
                'net_mass' => number_format($row['bulk'], 3, ',', ' ') . ' ' . $row['untils'] . ($row['untils'] == 'шт' ? ' / ' . $bulk . ' кг' : ''),
                'cost_price_calc' => $row['cost_price_calc'],
                'cost_price' => number_format($row['price'], 2, ',', ' ') . ' ' . CURRENCY,
                'print_name' => $row['print_name'],
                'composition_description' => $row['composition_description'],
                'energy_value' => $row['energy_value'],
                'nutrients' => $row['nutrients'],
                'shelf_life' => $row['shelf_life'],
                'code' => false
            );
        }

        $page_query = 'SELECT COUNT(t.id) AS count
                        FROM (
                            SELECT i.id
                            FROM ' . DB_ITEMS . ' i
                            JOIN ' . DB_PRODUCTIONS_COMPOSITION . ' pc ON pc.product = i.id
                            JOIN ' . DB_ITEMS . ' ic ON ic.id = pc.item
                            LEFT JOIN ' . DB_POINT_ITEMS . ' pi ON pi.item = pc.item AND pi.point = ' . $point . '
                            WHERE i.production = 1 AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL)' . $search . $archive . $where_category . '
                            GROUP BY i.id
                        ) t';

        $page_data = Pages::GetPageInfo($page_query, $page);
        $result = getCode($result);

        response('success', $result, 7, $page_data);

        break;

    case 'validate':

        $items_class = new ItemsClass(false, $userToken['id']);
        $items = $items_class->validate();

        response('success', $items, 7);

        break;

    case 'info':

        if (!$product = DB::escape($_REQUEST['product']))
            response('error', 'Не выбрана производимая продукция.', 1);

        $productData = DB::query(
            '
            SELECT i.id, i.name, i.untils, i.product_category, cat.name AS category_name
            FROM ' . DB_ITEMS . ' i
            LEFT JOIN ' . DB_PRODUCT_CATEGORIES . ' cat ON cat.id = i.product_category
            WHERE (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL) AND i.id = ' . $product
        );

        if (DB::getRecordCount($productData) == 0)
            response('error', 'Производимая продукция не найдена.', 1);

        $productData = DB::getRow($productData);
        $category = $productData['product_category'] ? ['id' => $productData['product_category'], 'name' => $productData['category_name']] : [];

        $result = array(
            'id' => $productData['id'],
            'name' => $productData['name'],
            'untils' => $productData['untils'],
            'category' => (object)$category
        );

        $result['items'] = [];

        $items = DB::query(' SELECT pc.*, i.name, i.untils, IF(i.untils = "шт", AVG(pi.price) * pc.count, AVG(pi.price) * pc.gross) AS cost_price
                                    FROM ' . DB_PRODUCTIONS_COMPOSITION . ' pc
                                    LEFT JOIN ' . DB_POINT_ITEMS . ' pi ON pi.item = pc.item AND pi.partner = ' . $userToken['id'] . '
                                    LEFT JOIN ' . DB_ITEMS . ' i ON i.id = pc.item
                                    WHERE pc.product = ' . $product . '
                                    GROUP BY pc.id');

        while ($row = DB::getRow($items))
            $result['items'][] = array(
                'id' => $row['id'],
                'item' => array(
                    'id' => $row['item'],
                    'name' => $row['name']
                ),
                'count' => $row['count'],
                'cleaning' => $row['cleaning'],
                'cooking' => $row['cooking'],
                'frying' => $row['frying'],
                'stew' => $row['stew'],
                'bake' => $row['bake'],
                'cleaning_checked' => $row['cleaning_checked'],
                'cooking_checked' => $row['cooking_checked'],
                'frying_checked' => $row['frying_checked'],
                'stew_checked' => $row['stew_checked'],
                'bake_checked' => $row['bake_checked'],
                'untils' => $row['untils'],
                'gross' => $row['gross'],
                'net_mass' => $row['net_mass'],
                'mass_block' => $row['mass_block'],
                'cost_price' => round($row['cost_price'], 2)
            );

        response('success', $result, 7);

        break;

    case 'edit':

        if (!$product = DB::escape($_REQUEST['product']))
            response('error', 'Не выбрана производимая продукция.', 1);

        $productData = DB::query('SELECT id, name, partner, untils, bulk
                                            FROM ' . DB_ITEMS . '
                                            WHERE id = ' . $product . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL) AND production = 1');

        if (DB::getRecordCount($productData) == 0)
            response('error', 'Производимая продукция не найдена.', 1);

        $productData = DB::getRow($productData);

        $composition_class = new ProductionCostPrice(false, $userToken['id']);

        $editing_allowed = ($productData['partner'] == null && $userToken['id'] != 1) ? false : true;

        if (!$editing_allowed)
            response('error', 'Вы не можете редактировать общедоступную производимую продукцию.', 1);

        $name = trim(DB::escape($_REQUEST['name']));

        $fields = [];

        if ($productData['name'] != $name) {

            $nameExist = DB::select('id', DB_ITEMS, 'id != ' . $product . ' AND name = "' . $name . '" AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

            if (DB::getRecordCount($nameExist) != 0)
                response('error', 'Ингредиент или полуфабрикат с таким названием уже существует.', 1);

            $fields['name'] = $name;
        }

        //Получаем состав производимой продукции и производим валидацию данных
        $items_class = new ItemsClass(false, $userToken['id'], true);
        $items = $items_class->validate();

        if (!$items_class->net_mass)
            response('error', 'Общая масса НЕТТО не может быть равна нулю!', 422);

        $fields['bulk'] = $items_class->net_mass;
        if (isset($_REQUEST['category']) && $_REQUEST['category']  > 0)
            $fields['product_category'] = $_REQUEST['category'];

        //Формируем список состава производимой продукции
        for ($i = 0; $i < sizeof($items); $i++) {

            $item_is_parent = $composition_class->num_array_children($items[$i]);

            if ($product == $items[$i]['id'])
                response('error', 'Нельзя добавить "' . $productData['name'] . '".', 1);

            if (in_array($product, $item_is_parent)) {
                $item_child_name = DB::getRow(
                    DB::select('name', DB_ITEMS, 'id = ' . $items[$i]['id'], '', 1)
                )['name'];
                response('error', '"' . $productData['name'] . '" уже входит в состав "' . $item_child_name . '". Вы не можете добавить "' . $item_child_name . '".', 1);
            }

            if ($items[$i]['untils'] != 'шт')
                $items[$i]['count'] = $items[$i]['gross'];

            if (!$itemsStr)
                $itemsStr = '("' . $items[$i]['untils'] . '", "' . $product . '", "' . $items[$i]['id'] . '", "' . $items[$i]['count'] . '", "' . $items[$i]['cleaning'] . '", "' . $items[$i]['cooking'] . '", "' . $items[$i]['frying'] . '", "' . $items[$i]['stew'] . '", "' . $items[$i]['bake'] . '", "' . $items[$i]['gross'] . '", "' . $items[$i]['net_mass'] . '", "' . $items[$i]['cooking_checked'] . '", "' . $items[$i]['cleaning_checked'] . '", "' . $items[$i]['frying_checked'] . '", "' . $items[$i]['stew_checked'] . '", "' . $items[$i]['bake_checked'] . '", "' . $userToken['id'] . '", "' . $items[$i]['mass_block'] . '")';
            else
                $itemsStr .= ', ("' . $items[$i]['untils'] . '", "' . $product . '", "' . $items[$i]['id'] . '", "' . $items[$i]['count'] . '", "' . $items[$i]['cleaning'] . '", "' . $items[$i]['cooking'] . '", "' . $items[$i]['frying'] . '", "' . $items[$i]['stew'] . '", "' . $items[$i]['bake'] . '", "' . $items[$i]['gross'] . '", "' . $items[$i]['net_mass'] . '", "' . $items[$i]['cooking_checked'] . '", "' . $items[$i]['cleaning_checked'] . '", "' . $items[$i]['frying_checked'] . '", "' . $items[$i]['stew_checked'] . '", "' . $items[$i]['bake_checked'] . '", "' . $userToken['id'] . '", "' . $items[$i]['mass_block'] . '")';
        }

        DB::update($fields, DB_ITEMS, 'id = ' . $product . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

        // ItemModel::update($fields, $product);

        DB::delete(DB_PRODUCTIONS_COMPOSITION, 'product = ' . $product);

        DB::query('INSERT INTO ' . DB_PRODUCTIONS_COMPOSITION . ' (untils, product, item, count, cleaning, cooking, frying, stew, bake, gross, net_mass, cooking_checked, cleaning_checked, frying_checked, stew_checked, bake_checked, partner, mass_block) VALUES ' . $itemsStr);

        //Если при редактировании был изменен выход ПФ и ПФ в штуках, то нужно пересчитать выход всех родителей и тех. карт
        if ($productData['untils'] == 'шт' && $productData['bulk'] != $fields['bulk']) {
            $class = new ProductionsParent(false);
            $productData['new_bulk'] = $fields['bulk'];
            $class->update($productData);
        }

        response('success', 'Производимая продукция изменена.', 7);

        break;

    case 'composition':

        $element_count = 10;

        if (!$page || $page == 1) {
            $page = '1';
            $limit = '0,' . $element_count;
        } else {
            $begin = $element_count * $page - $element_count;
            $limit = $begin . ',' . $element_count;
        }

        if (!$product = DB::escape($_REQUEST['product']))
            response('error', array('msg' => 'Выберите тех. карту.'), '371');

        if (!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Выберите заведение.'), '329');

        $composition = DB::query('SELECT i.id, c.count, c.gross, i.untils, c.net_mass, i.name, SUM(pi.price * IF(c.untils = "шт", c.count, c.gross)) AS cost_price, AVG(pi.price) AS calc, i.production, i.bulk
                                        FROM ' . DB_PRODUCTIONS_COMPOSITION . ' c
                                        JOIN ' . DB_ITEMS . ' AS i ON i.id = c.item
                                        LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = c.item AND pi.point = ' . $point . '
                                        WHERE c.product = ' . $product . '
                                        GROUP BY c.id
                                        LIMIT ' . Pages::$limit);

        $result = [];

        $pr_class = new ProductionCostPrice(false, $userToken['id'], $point);

        while ($row = DB::getRow($composition)) {

            if ($row['production']) {

                $row['name'] .= ' (п/ф)';

                $row['items'] = $pr_class->subItems($row);

                $row['cost_price'] = 0;

                $row['calc'] = true;
                foreach ($row['items'] as $item) {
                    if ($item['price'] == null)
                        $row['calc'] = false;
                }

                for ($j = 0; $j < sizeof($row['items']); $j++)
                    $row['cost_price'] += $row['items'][$j]['count_price'];
            } else {

                if ($row['untils'] != 'шт') {
                    $row['count'] = '-';
                    $row['gross'] = number_format($row['gross'], 3, ',', ' ');
                } else {
                    $row['count'] = number_format($row['count'], 3, ',', ' ');
                    $row['gross'] = '-';
                }

                $row['calc'] = ($row['calc'] == null) ? false : true;

                $row['cost_price'] = (float)$row['cost_price'];
            }

            $result[] = array(
                'id' => $row['id'],
                'untils' => $row['untils'],
                'count' => $row['count'],
                'gross' => $row['gross'],
                'producion' => (bool)$row['production'],
                'net_mass' => number_format($row['net_mass'], 3, ',', ' '),
                'price' => ($row['calc'] || $row['production']) ? number_format($row['cost_price'], 2, ',', ' ') : '-',
                'calc' => $row['calc'],
                'item' => array(
                    'id' => $row['id'],
                    'name' => $row['name']
                )
            );
        }

        $pages = DB::query('SELECT COUNT(c.id) AS count
                                    FROM ' . DB_PRODUCTIONS_COMPOSITION . ' c
                                    WHERE c.product = ' . $product);

        $pages = DB::getRow($pages);

        if ($pages['count'] != null)
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        else
            $total_pages = 0;

        $pageData = array(
            'current_page' => (int)Pages::$page,
            'total_pages' => $total_pages,
            'rows_count' => (int)$pages['count'],
            'page_size' => ELEMENT_COUNT
        );

        response('success', $result, '7', $pageData);

        break;

    case 'repair':

        $sub = DB::escape($_REQUEST['sub']);

        if ($sub == 'pr') {

            $products = DB::select('*', DB_ITEMS, 'production = 1 AND untils = "шт"');

            while ($row = DB::getRow($products)) {

                editProd($row);
            }
        }

        if ($sub == 'tc') {
            $products = DB::select('*', DB_ITEMS, 'production = 1 AND untils = "шт"');

            while ($row = DB::getRow($products)) {

                editTechcard($row);
            }
        }

        if ($sub == 'i') {
            $items = DB::query('UPDATE `app_items` i
            JOIN (
                SELECT SUM(net_mass) AS netmass, product
                FROM `app_productions_composition` pc
                WHERE pc.mass_block = 0
                GROUP BY pc.product
            ) t ON t.product = i.id
            SET i.bulk = t.netmass
            WHERE i.production = 1 AND i.untils = "шт"');
        }

        echo 'Конец';

        break;

    case 'composition_description':

        if (!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не передан ID продукции.', 422);

        if (!$print_name = DB::escape($_REQUEST['print_name']))
            $print_name = '';
        if (!$description = DB::escape($_REQUEST['description']))
            $description = '';
        if (!$energy_value = DB::escape($_REQUEST['energy_value']))
            $energy_value = '';
        if (!$nutrients = DB::escape($_REQUEST['nutrients']))
            $nutrients = '';
        if (!$shelf_life = DB::escape($_REQUEST['shelf_life']))
            $shelf_life = '';

        DB::update(
            [
                'print_name' => $print_name,
                'composition_description' => $description,
                'energy_value' => $energy_value,
                'nutrients' => $nutrients,
                'shelf_life' => $shelf_life
            ],
            DB_ITEMS,
            'id = ' . $id . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)'
        );

        response('success', 'Изменения сохранены.', 201);

        break;


        /* case 'delete':

        if(!$product = DB::escape($_REQUEST['product'])) 
            response('error', 'Не выбрана производимая продукция.', 1);

        $productData = DB::select('id', DB_PRODUCTION_PRODUCTS, 'id = '.$product.' AND del = 0 AND partner = '.$userToken['id']);

        if(DB::getRecordCount($productData) == 0)
            response('error', 'Такого продукта не существует.', 1);

        DB::update(array('del' => 1), DB_PRODUCTION_PRODUCTS, 'id = '.$product);

        response('success', 'Производимая продукция удалена.', 7);

    break; */
}