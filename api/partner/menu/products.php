<?php

use Controllers\Models\ItemModel;
use Support\Pages;
use Support\DB;

include ROOT . 'api/partner/tokenCheck.php';
include ROOT . 'api/lib/functions.php';
require ROOT . 'api/classes/OrderClass.php';

$page = DB::escape($_REQUEST['page']);

function MenuProducts($user, $product)
{



    $points = DB::escape($_REQUEST['points']);

    $points = stripslashes($points);

    $points = json_decode($points, true);

    if ($points == null || sizeof($points) == 0)
        $points_raw = '';
    else {

        for ($i = 0; $i < sizeof($points); $i++) {

            if ($points[$i]['enable'] === true) {

                if (!$points_raw)
                    $points_raw = '("' . $user . '", "' . $product . '", "' . $points[$i]['id'] . '")';
                else
                    $points_raw .= ', ("' . $user . '", "' . $product . '", "' . $points[$i]['id'] . '")';

                if (!$enable_techcards)
                    $enable_techcards = '(partner = ' . $user . ' AND product = ' . $product . ' AND point = ' . $points[$i]['id'] . ')';
                else
                    $enable_techcards .= ' OR (partner = ' . $user . ' AND product = ' . $product . ' AND point = ' . $points[$i]['id'] . ')';
            } else {
                if (!$hide_techcards)
                    $hide_techcards = '(partner = ' . $user . ' AND product = ' . $product . ' AND point = ' . $points[$i]['id'] . ')';
                else
                    $hide_techcards .= ' OR (partner = ' . $user . ' AND product = ' . $product . ' AND point = ' . $points[$i]['id'] . ')';
            }
        }
    }

    if ($enable_techcards)
        DB::query('
            UPDATE ' . DB_PRODUCT_PRICES . '
            SET hide = 0
            WHERE ' . $enable_techcards . '
        ');

    if ($hide_techcards)
        DB::query('
            UPDATE ' . DB_PRODUCT_PRICES . '
            SET hide = 1
            WHERE ' . $hide_techcards . '
        ');

    DB::delete(DB_MENU_PRODUCTS, 'partner = ' . $user . ' AND product = ' . $product);

    if ($points_raw)
        DB::query('INSERT IGNORE INTO ' . DB_MENU_PRODUCTS . ' (partner, product, point) VALUES ' . $points_raw);
}


function CategoriesFilter($categories)
{

    $categories = explode(',', $categories);

    for ($i = 0; $i < sizeof($categories); $i++) {

        if (!$result)
            $result = ' AND (p.category = ' . $categories[$i];
        else
            $result .= ' OR p.category = ' . $categories[$i];
    }

    $result .= ')';

    return $result;
}

function ProductTechCard($product, $user, $name)
{



    $create_item = DB::escape($_REQUEST['create_item']);

    if (!$price = DB::escape($_REQUEST['price'])) {
        DB::delete(DB_PRODUCTS, 'id = ' . $product);
        response('error', array('msg' => 'Укажите цену.'), '584');
    }

    if ((!$item = DB::escape($_REQUEST['item'])) && !$create_item) {
        DB::delete(DB_PRODUCTS, 'id = ' . $product);
        response('error', array('msg' => 'Выберите ингредиент.'), '585');
    }

    //Если выбран существующий ингредиент
    if ($item) {
        $itemData = DB::select('id, untils, bulk', DB_ITEMS, 'id = ' . $item . ' AND (partner = ' . $user . ' OR partner IS NULL)');

        if (DB::getRecordCount($itemData) == 0) {
            DB::delete(DB_PRODUCTS, 'id = ' . $product);
            response('error', array('msg' => 'Ингредиент не найден.'), '586');
        }

        $itemData = DB::getRow($itemData);
    }

    //Если создается по названию товара
    if ($create_item) {

        $item_exist = DB::select('id', DB_ITEMS, 'name = "' . $name . '" AND (partner = ' . $user . ' OR partner IS NULL)');
        $category_id = DB::escape($_REQUEST['item_category']);

        if (DB::getRecordCount($item_exist) == 0) {

            $itemData = array(
                'name' => $name,
                'bulk' => 1,
                'untils' => 'шт',
                'category' => $category_id,
                'partner' => $user
            );

            if ($item = DB::insert($itemData, DB_ITEMS)) {

                $itemData['id'] = $item;
                //   ItemModel::add($itemData);
            } else
                response('error', array('msg' => 'Не удалось добавить ингредиент.'), '560');
        } else
            $item = DB::getRow($item_exist)['id'];
    }

    $fields_tech_card = array(
        'partner' => $user,
        'product' => $product,
        'bulk_value' => 1,
        'bulk_untils' => 'шт',
        'color' => 1,
        'price' => $price
    );

    if ($code = DB::escape($_REQUEST['code'])) {
        if (strlen($code) > 32)
            response('error', array('msg' => 'Штрих-код не может превышать больше 32-х символов.'), '588');
        $fields_tech_card['code'] = $code;
    }

    if ($technical_card = DB::insert($fields_tech_card, DB_TECHNICAL_CARD)) {
        $fields_composition = array(
            'item' => $item,
            'technical_card' => $technical_card,
            'untils' => $itemData['untils'],
            'count' => $itemData['bulk']
        );

        if ($composition = DB::insert($fields_composition, DB_PRODUCT_COMPOSITION))
            return 0; //Если всё успешно добавлено, то просто выходим из функции
    }
    DB::delete(DB_PRODUCTS, 'id = ' . $product); //Если что-то не удалось добавить, то удаляем товар, он в свою очередь каскадно удаляет тех.карту
    response('error', '', '503');
}

function EditProductTechCard($product, $user)
{



    $create_item = DB::escape($_REQUEST['create_item']);

    if (!$price = DB::escape($_REQUEST['price'])) {
        DB::delete(DB_PRODUCTS, 'id = ' . $product);
        response('error', array('msg' => 'Укажите цену.'), '584');
    }

    $technical_card = DB::select('id', DB_TECHNICAL_CARD, 'product = ' . $product);

    if (DB::getRecordCount($technical_card) == 0)
        response('error', '', '503');

    if ((!$item = DB::escape($_REQUEST['item'])) && !$create_item) {
        DB::delete(DB_PRODUCTS, 'id = ' . $product);
        response('error', array('msg' => 'Выберите ингредиент.'), '585');
    }

    //Если выбран существующий ингредиент
    if ($item) {
        $itemData = DB::select('id, untils, bulk', DB_ITEMS, 'id = ' . $item . ' AND (partner = ' . $user . ' OR partner IS NULL)');

        if (DB::getRecordCount($itemData) == 0) {
            DB::delete(DB_PRODUCTS, 'id = ' . $product);
            response('error', array('msg' => 'Ингредиент не найден.'), '586');
        }

        $itemData = DB::getRow($itemData);
    }

    //Если создается по названию товара
    if ($create_item) {

        $itemData = array(
            'name' => $name,
            'bulk' => 1,
            'untils' => 'шт',
            'partner' => $user
        );

        if ($item = DB::insert($itemData, DB_ITEMS)) {
            $itemData['id'] = $item;
            //     ItemModel::add($itemData);
        } else
            response('error', array('msg' => 'Не удалось добавить ингредиент.'), '560');
    }

    DB::update(array('price' => $price), DB_TECHNICAL_CARD, 'id = ' . $technical_card['id']);

    $fields = array(
        'item' => $item,
        'untils' => $itemData['untils'],
        'count' => $itemData['bulk']
    );

    DB::update($fields, DB_PRODUCT_COMPOSITION, 'technical_card = ' . $technical_card['id']);
}

switch ($action) {

    case 'add':

        $fields = [];

        if (!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите название товара.'), '335');

        if (DB::escape($_REQUEST['create_item'])) {

            if (!DB::escape($_REQUEST['item_category']))
                response('error', ['msg' => 'Выберите категорию ингредиента.'], 422);
            else {
                $item_category_exists = DB::select('*', DB_ITEMS_CATEGORY, 'id = ' . DB::escape($_REQUEST['item_category']));
                if (!DB::getRecordCount($item_category_exists))
                    response('error', ['msg' => 'Выбранная категория ингредиента не найдена.'], 404);
            }
        }

        $product_exist = DB::query('
            SELECT i.id, a.id AS arch
            FROM ' . DB_PRODUCTS . ' i
            LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = i.id AND a.model = "product" AND a.partner_id = ' . $userToken['id'] . '
            WHERE i.name = "' . $name . '" AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL)
        ');

        if (DB::getRecordCount($product_exist) > 0) {
            $arch = DB::getRow($product_exist)['arch'];
            if ($arch == null)
                response('error', 'Товар с таким названием уже существует.', 1);
            else
                response('error', 'Товар с таким названием находится в архиве, восстановите его.', 1);
        }

        $fields['name'] = $name;

        if ($category = DB::escape($_REQUEST['category'])) {

            $categoryData = DB::select('*', DB_PRODUCT_CATEGORIES, 'id = ' . $category . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

            if (DB::getRecordCount($categoryData) == 0)
                response('error', array('msg' => 'Такой категории не существует.'), '316');

            $fields['category'] = $category;
        }

        if (!$color = DB::escape($_REQUEST['color']))
            response('error', array('msg' => 'Выберите цвет.'), '331');

        $colorData = DB::select('id', DB_COLORS, 'id = ' . $color);

        if (DB::getRecordCount($colorData) == 0)
            response('error', array('msg' => 'Такого цвета не существует.'), '332');

        $fields['color'] = $color;

        if ($image = $_FILES['image']) {

            $fileImage = FileLoad('partner№' . $userToken['id'] . '/products', $image, hash('md5', 'coffeeway' . $userToken['id'] . 'product' . time()));

            $fields['image'] = $fileImage['link'];
        }

        $fields['partner'] = $userToken['id'];
        $fields['created'] = time();
        $fields['updated'] = time();
        $fields['technical_card'] = (DB::escape($_REQUEST['technical_card'])) ? 1 : 0;

        if ($product = DB::insert($fields, DB_PRODUCTS)) {


            $show_everywhere = DB::escape($_REQUEST['show_everywhere']);

            MenuProducts($userToken['id'], $product);

            DB::update(array('points' => $points, 'show_everywhere' => $show_everywhere), DB_PRODUCTS, 'id = ' . $product);

            //Если продукт создается как отдельный товар
            if (DB::escape($_REQUEST['technical_card']))
                ProductTechCard($product, $userToken['id'], $name);

            response('success', array('msg' => 'Продукт добавлен.'), '610');
        } else
            response('error', '', '503');

        break;

    case 'items':

        $items = DB::select('id, name', DB_ITEMS, '(partner = ' . $userToken['id'] . ' OR partner IS NULL) AND untils = "шт"', 'name');
        $items = DB::makeArray($items);

        response('success', $items, '7');

        break;

    case 'get':

        if ($search = DB::escape($_REQUEST['search']))
            $searchStr = ' AND (p.name LIKE "%' . $search . '%" OR pc.name LIKE "%' . $search . '%")';

        //Фильтрация по категориям
        if ($categories = DB::escape($_REQUEST['categories']))
            $categories = CategoriesFilter($categories);

        $archive = '
            AND p.id ' . (DB::escape($_REQUEST['archive']) ? '' : 'NOT') . ' IN (
                SELECT product_id
                FROM ' . DB_ARCHIVE . '
                WHERE model = "product" AND partner_id = ' . $userToken['id'] . '
            )';

        $result = [];
        if ($full = DB::escape($_REQUEST['full'])) {

            $products = DB::query('SELECT p.id, p.name
                                        FROM ' . DB_PRODUCTS . ' p
                                        LEFT JOIN ' . DB_PRODUCT_CATEGORIES . ' AS pc ON pc.id = p.category
                                        LEFT JOIN ' . DB_COLORS . ' AS c ON c.id = p.color
                                        WHERE (p.partner = ' . $userToken['id'] . ' OR p.partner IS NULL)' . $categories . $searchStr . $archive . '
                                        ORDER BY p.name ASC');

            $result = DB::makeArray($products);

            response('success', $result, '7');
        } else {

            $ORDER_BY = Order::products(Pages::$field, Pages::$order);

            $products_q = DB::query('SELECT p.id, p.name, p.image, p.partner, p.category, c.code AS color, pc.name AS catname
                                            FROM ' . DB_PRODUCTS . ' p
                                            LEFT JOIN ' . DB_PRODUCT_CATEGORIES . ' AS pc ON pc.id = p.category
                                            LEFT JOIN ' . DB_COLORS . ' AS c ON c.id = p.color
                                            WHERE (p.partner = ' . $userToken['id'] . ' OR p.partner IS NULL)' . $categories . $searchStr . $archive . '
                                            ' . $ORDER_BY . '
                                            LIMIT ' . Pages::$limit);
            $products = [];
            $products_ids = [];
            while ($row = DB::getRow($products_q)) {
                $products[] = $row;
                $products_ids[] = $row['id'];
            }
            $products_ids = array_unique($products_ids);
            if (count($products_ids) > 0) {
                $points_q = DB::query('SELECT pr.product, pr.point as id, p.name, MAX(hide) as hide FROM ' . DB_PRODUCT_PRICES . ' pr 
                JOIN ' . DB_PARTNER_POINTS . ' p ON pr.point = p.id 
                WHERE product IN (' . implode(',', $products_ids) . ') AND pr.partner = ' . $userToken['id'] . ' GROUP BY `product`, `point` ORDER BY name');

                $points = [];
                while ($row = DB::getRow($points_q))
                    $points[$row['product']][] = $row;
            }
            foreach ($products as $row) {
                $image = ($row['image'] == '') ? PLACEHOLDER_IMAGE : $row['image'];

                $row['image'] = ImageResize($image, 130, 130);

                $result[] =  array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'category' => array(
                        'id' => $row['category'],
                        'name' => $row['catname']
                    ),
                    'points' => $points[$row['id']],
                    'image' => $row['image'],
                    //'my' => ($row['my'] == null) ? false : true,
                    'my' => ($row['partner'] == $userToken['id'] || ($row['partner'] == null && $userToken['admin'])) ? true : false,
                    'can_share' => ($userToken['admin'] && $row['partner'] != null) ? true : false,
                    'color' => $row['color']
                );
            }

            $pages = DB::query('SELECT COUNT(p.id) AS count
                                        FROM ' . DB_PRODUCTS . ' p
                                        LEFT JOIN ' . DB_PRODUCT_CATEGORIES . ' AS pc ON pc.id = p.category
                                        LEFT JOIN ' . DB_COLORS . ' AS c ON c.id = p.color
                                        WHERE (p.partner = ' . $userToken['id'] . ' OR p.partner IS NULL)' . $categories . $searchStr . $archive);
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

    case 'edit':

        if (!$product = DB::escape($_REQUEST['product']))
            response('error', array('msg' => 'Выберите товар.'), '336');

        $productData = DB::select('id, partner, image, points', DB_PRODUCTS, 'id = ' . $product . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

        if (DB::getRecordCount($productData) == 0)
            response('error', array('msg' => 'Товара с таким ID не существует.'), '337');

        $productData = DB::getRow($productData);

        /* if($productData['partner'] == null)
            response('error', array('msg' => 'Вы не можете редактировать общедоступный товар.'), '338'); */

        $editing_allowed = ($productData['partner'] == null && !$userToken['admin']) ? false : true;

        $fields = [];

        if ($name = DB::escape($_REQUEST['name']))
            $fields['name'] = $name;

        $product_exist = DB::query('
            SELECT i.id, a.id AS arch
            FROM ' . DB_PRODUCTS . ' i
            LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = i.id AND a.model = "product" AND a.partner_id = ' . $userToken['id'] . '
            WHERE i.id != ' . $product . ' AND i.name = "' . $name . '" AND (i.partner = ' . $userToken['id'] . ' OR i.partner IS NULL)
        ');

        if (DB::getRecordCount($product_exist) > 0) {
            $arch = DB::getRow($product_exist)['arch'];
            if ($arch == null)
                response('error', 'Товар с таким названием уже существует.', 1);
            else
                response('error', 'Товар с таким названием находится в архиве, восстановите его.', 1);
        }

        if ($category = DB::escape($_REQUEST['category'])) {

            $categoryData = DB::select('id', DB_PRODUCT_CATEGORIES, 'id = ' . $category . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

            if (DB::getRecordCount($categoryData) == 0)
                response('success', array('msg' => 'Такой категории не существует.'), '321');

            $categoryData = DB::getRow($categoryData);

            $fields['category'] = $category;
        }

        $show_everywhere = DB::escape($_REQUEST['show_everywhere']);

        MenuProducts($userToken['id'], $product);

        $fields['show_everywhere'] = $show_everywhere;

        if ($color = DB::escape($_REQUEST['color'])) {

            $colorData = DB::select('id', DB_COLORS, 'id = ' . $color);

            if (DB::getRecordCount($colorData) == 0)
                response('error', array('msg' => 'Такого цвета не существует.'), '332');

            $fields['color'] = $color;
        }

        if ($editing_allowed) {
            if ($image = $_FILES['image']) {

                $fileImage = FileLoad('partner№' . $userToken['id'] . '/products', $image, hash('md5', 'coffeeway' . $userToken['id'] . 'product' . time()));

                $fields['image'] = $fileImage['link'];

                unset($categoryData['image']);
            }
        }

        $fields['updated'] = time();

        //Если разрешено редактирование, то редактируем
        if ($editing_allowed)
            DB::update($fields, DB_PRODUCTS, 'id = ' . $product);

        response('success', array('msg' => 'Информация о товаре обновлена.'), '611');

        break;

    case 'delete':

        if (!$product = DB::escape($_REQUEST['product']))
            response('error', array('msg' => 'Выберите товар.'), '336');

        $productData = DB::select('id, partner', DB_PRODUCTS, 'id = ' . $product . ' AND (partner = ' . $userToken['id'] . ' OR partner IS NULL)');

        if (DB::getRecordCount($productData) == 0)
            response('error', array('msg' => 'Товара с таким ID не существует.'), '337');

        $productData = DB::getRow($productData);

        if ($productData['partner'] == null)
            response('error', array('msg' => 'Вы не можете удалить общедоступный товар.'), '339');

        if (DB::delete(DB_PRODUCTS, 'id = ' . $product))
            response('success', array('msg' => 'Товар удален.'), '612');
        else
            response('error', '', '503');

        break;

    case 'info':

        if (!$product = DB::escape($_REQUEST['product']))
            response('error', array('msg' => 'Выберите товар.'), '336');

        $productData = DB::query('SELECT p.id, p.partner, p.name, p.image, cat.id AS catid, cat.name AS catname, c.id AS cid, c.code, p.technical_card, p.create_item, tc.id AS change_enable, p.show_everywhere
                                        FROM ' . DB_PRODUCTS . ' p
                                        LEFT JOIN ' . DB_PRODUCT_CATEGORIES . ' AS cat ON cat.id = p.category
                                        LEFT JOIN ' . DB_COLORS . ' AS c ON c.id = p.color
                                        LEFT JOIN ' . DB_TECHNICAL_CARD . ' AS tc ON tc.product = p.id
                                        WHERE p.id = ' . $product . ' AND (p.partner = ' . $userToken['id'] . ' OR p.partner IS NULL)
                                        GROUP BY p.id');

        if (DB::getRecordCount($productData) == 0)
            response('error', array('msg' => 'Товара с таким ID не существует.'), '337');

        $row = DB::getRow($productData);

        $image = ($row['image'] == '') ? PLACEHOLDER_IMAGE : $row['image'];

        $row['image'] = ImageResize($image, 130, 130);

        $row['change_enable'] = ($row['change_enable'] == null) ? true : false;

        $editing_allowed = ($row['partner'] == null && !$userToken['admin']) ? false : true;

        //Если change_enable = true, то можно создать как отельный товар, иначе запрет
        $change_enable = ($row['change_enable'] && $row['technical_card'] == 0) ? true : false;

        $result = array(
            'id' => $row['id'],
            'name' => $row['name'],
            'image' => $row['image'],
            'change_enable' => $change_enable,
            'show_everywhere' => (bool)$row['show_everywhere'],
            'points' => [],
            'category' => array(
                'id' => $row['catid'],
                'name' => $row['catname']
            ),
            'color' => array(
                'id' => $row['cid'],
                'code' => $row['code']
            ),
            //'technical_card' => (bool)$row['technical_card'],
            //'create_item' => (bool)$row['create_item'],
            'editing_allowed' => $editing_allowed
        );

        $points = DB::query('SELECT p.id, p.name, m.partner AS enable
                                    FROM ' . DB_PARTNER_POINTS . ' p
                                    LEFT JOIN ' . DB_MENU_PRODUCTS . ' m ON m.point = p.id AND m.product = ' . $product . ' AND m.partner = ' . $userToken['id'] . '
                                    WHERE p.partner = ' . $userToken['id'] . '
                                    ORDER BY p.name');

        while ($row = DB::getRow($points)) {

            $row['enable'] = ($row['enable'] == null) ? false : true;

            $result['points'][] = $row;
        }

        if ($row['technical_card'] == 1) {

            $item = DB::query('SELECT tc.price, i.id, i.name, tc.code
                                    FROM ' . DB_TECHNICAL_CARD . ' tc
                                    JOIN ' . DB_PRODUCT_COMPOSITION . ' AS pc ON pc.technical_card = tc.id
                                    JOIN ' . DB_ITEMS . ' AS i ON i.id = pc.item
                                    WHERE tc.product = ' . $row['id'] . '
                                    LIMIT 1');


            $item = DB::getRow($item);

            $result['price'] = $item['price'];
            $result['code'] = $item['code'];
            $result['item'] = array(
                'id' => $item['id'],
                'name' => $item['name']
            );
        }

        response('success', $result, '7');

        break;
}