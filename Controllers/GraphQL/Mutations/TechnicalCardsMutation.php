<?php

namespace Controllers\GraphQL\Mutations;

use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\EditingAllowed;
use Support\mDB;

class TechnicalCardsMutation
{

    public static function massPriceEdit()
    {
        return [
            'type' => Type::boolean(),
            'description' => 'Установка цен на тех карты',
            'args' => [
                'technical_cards' => [
                    "type" => Type::nonNull(Type::listOf(Types::TechnicalCardPriceInput())),
                    'description' => 'Тех. карты и цены',
                ],
            ],
            'resolve' => function ($root, $args) {

                //    throw new Error("Массовое редактирование недоступно");

                if (!Auth::$user['id']) {
                    throw new Error("Приватный метод");
                }

                $technical_cards = $args['technical_cards'];

                foreach ($technical_cards as $technical_card) {

                    $price_on_points = $technical_card['price_on_points'];

                    $set = [];

                    foreach ($price_on_points as $item) {

                        $set['prices.' . $item['point_id'] . '.point'] = (int) $item['point_id'];
                        $set['prices.' . $item['point_id'] . '.price'] = (float) $item['price'];
                        $set['prices.' . $item['point_id'] . '.hide'] = $item['hide'];
                    }

                    mDB::collection("technical_cards")->updateOne([
                        'id' => (int) $technical_card['technical_card_id'],
                        'country' => Auth::$country,
                    ], [
                        '$set' => $set,
                    ]);
                }

                return true;
            },
        ];
    }

    public static function add()
    {
        return [
            'type' => Types::TechnicalCard(),
            'description' => 'Добавить/обновить тех карты',
            'args' => [
                'id' => [
                    "type" => Type::int(),
                    'description' => 'ID тех. карты для обновления, при создании передавать не надо',
                ],
                'technical_card' => [
                    "type" => Type::nonNull(Types::TechnicalCardInput()),
                    'description' => 'Тех. карта',
                ],
            ],
            'resolve' => function ($root, $args) {

                //  throw new Error("Редактирование недоступно");

                if (!Auth::$user['id']) {
                    throw new Error("Приватный метод");
                }

                if (isset($args['id'])) {

                    //Проверка доступности тех карты
                    $technical_card_data = DB::select('id, partner', DB_TECHNICAL_CARD, 'id = ' . $args['id']); // AND (partner = ' . Auth::$user['id'] . ' OR partner IS NULL)');

                    if (DB::getRecordCount($technical_card_data) == 0) {
                        throw new Error("Тех. карта не найдена.");
                    }

                    $technical_card = $args['technical_card'];

                    $productData = DB::select('id, partner, name, category, image', DB_PRODUCTS, "id = {$technical_card['product_id']} AND (partner = " . Auth::$user['id'] . " OR partner IS NULL)");

                    if (DB::getRecordCount($productData) == 0) {
                        throw new Error("Такого товара не существует.");
                    }

                    $productData = DB::getRow($productData);

                    if ($technical_card['different_price']) {
                        if (!isset($technical_card['price_on_points']) || count($technical_card['price_on_points']) == 0) {
                            throw new Error("Укажите цену на точках.");
                        }
                    }

                    //Получаем данные о тех. карте
                    $technical_card_data = DB::getRow($technical_card_data);

                    $technical_card_mdb = mDB::collection("technical_cards")->findOne([
                        "country" => Auth::$country,
                        "id" => $args['id'],
                    ]);

                    $editing_allowed = EditingAllowed::TechnicalCard($technical_card_mdb);

                    //Можно ли редактировать
                    if ($editing_allowed) {

                        $fields = [
                            'product' => $technical_card['product_id'],
                            'bulk_untils' => $technical_card['bulk_untils'],
                            'bulk_value' => $technical_card['bulk_value'],
                            'subname' => $technical_card['subname'],
                            'name_price' => $technical_card['name_price'],
                            'cashback_percent' => $technical_card['cashback_percent'],
                            'preparing_minutes' => $technical_card['preparing_minutes'],
                            'preparing_seconds' => $technical_card['preparing_seconds'],
                            'cooking_method' => $technical_card['cooking_method'],
                            'color' => $technical_card['color_id'],
                            'different_price' => $technical_card['different_price'] ? 1 : 0,
                            'not_promotion' => $technical_card['not_promotion'] ? 1 : 0,
                            'weighted' => $technical_card['weighted'] ? 1 : 0,
                            'price' => $technical_card['price'],
                        ];

                        $technical_card_data = DB::query('
                        SELECT tc.id, a.id AS arch
                        FROM ' . DB_TECHNICAL_CARD . ' tc
                        LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = tc.id AND a.model = "technical_card" AND partner_id = ' . Auth::$user['id'] . '
                        WHERE (tc.partner IS NULL OR tc.partner = ' . Auth::$user['id'] . ') AND tc.id != ' . $args['id'] . ' AND tc.product = ' . $technical_card['product_id'] . ' AND tc.bulk_value = "' . $technical_card['bulk_value'] . '" AND tc.bulk_untils = "' . $technical_card['bulk_untils'] . '" AND tc.subname = "' . $technical_card['subname'] . '"');

                        if (DB::getRecordCount($technical_card_data) != 0) {
                            $arch = DB::getRow($technical_card_data)['arch'];
                            if ($arch == null) {
                                throw new Error("Такая тех. карта уже существует.");
                            } else {
                                throw new Error("Данная тех. карта находится в архиве, восстановите её.");
                            }

                        }

                        //Обновление технической карты
                        if (!DB::update($fields, DB_TECHNICAL_CARD, 'id = ' . $args['id'])) {
                            throw new Error("Ошибка при обновлении тех. карты.");
                        }

                        //После обновления тех карты, необходимо добавить в БД ингредиенты из которых состоит продукт

                        $ids = [];
                        foreach ($technical_card['composition'] as $composition) {
                            $ids[] = $composition['item_id'];
                        }

                        $items = DB::makeArray(DB::select("*", "app_items", "id IN (" . implode(",", $ids) . ")"));

                        //Удаляем старый состав
                        DB::delete(DB_PRODUCT_COMPOSITION, 'technical_card = ' . $args['id']);

                        $compositionText = [];
                        $compositionData = [];

                        foreach ($technical_card['composition'] as $composition) {
                            foreach ($items as $item) {

                                if ($composition['item_id'] == $item['id']) {
                                    $compositionText[] = trim($item['name']);

                                    $compositionData[] = [
                                        'item' => [
                                            'id' => (int) $item['id'],
                                            'name' => $item['name'],
                                        ],
                                        "untils" => $item['untils'],
                                        'count' => (float) $composition['count'],
                                        'gross' => (float) $composition['gross'],
                                        'net_mass' => (float) $composition['net_mass'],
                                        'mass_block' => $composition['mass_block'],
                                        'divide' => isset($composition['divide']) ? $composition['divide'] : false,
                                    ];

                                    DB::insert([
                                        "item" => $composition['item_id'],
                                        "technical_card" => $args['id'],
                                        "untils" => $item['untils'],
                                        "count" => $composition['count'],
                                        "gross" => $composition['gross'],
                                        "net_mass" => $composition['net_mass'],
                                        "mass_block" => $composition['mass_block'] ? 1 : 0,
                                    ], 'app_product_composition');
                                }
                            }
                        }

                        //Еще в mongodb на случай миграциии может
                        mDB::collection("technical_cards")->updateOne([
                            'id' => (int) $args['id'],
                            'country' => Auth::$country,
                        ], [
                            '$set' => [
                                'product' => [
                                    'id' => (int) $productData['id'],
                                    'name' => (string) $productData['name'],
                                    'category' => (int) $productData['category'],
                                    'image' => (string) $productData['image'],

                                ],
                                "name" => (string) $productData['name'] . ' ' . $technical_card['subname'] . ' ' . $technical_card['bulk_value'] . ' ' . $technical_card['bulk_untils'],
                                'bulk_untils' => $technical_card['bulk_untils'],
                                'bulk_value' => (float) $technical_card['bulk_value'],
                                'subname' => $technical_card['subname'],
                                'name_price' => $technical_card['name_price'],
                                'cashback_percent' => (int) $technical_card['cashback_percent'],
                                'preparing_minutes' => (int) $technical_card['preparing_minutes'],
                                'preparing_seconds' => (int) $technical_card['preparing_seconds'],
                                'cooking_method' => $technical_card['cooking_method'],
                                'color' => (int) $technical_card['color_id'],
                                'price' => (float) $technical_card['price'],
                                'not_promotion' => $technical_card['not_promotion'],
                                'weighted' => $technical_card['weighted'],
                                'composition_text' => implode(', ', $compositionText),
                                'composition' => $compositionData,
                                'different_price' => $technical_card['different_price'],
                            ],
                        ]);
                    }

                    foreach ($technical_card['price_on_points'] as $price_on_point) {

                        DB::delete('app_product_prices', "point = " . $price_on_point['point_id'] . " AND technical_card = " . $args['id']);

                        mDB::collection("technical_cards")->updateOne([
                            'id' => (int) $args['id'],
                            'country' => Auth::$country,
                        ], [

                            '$set' => [

                                'prices.' . $price_on_point['point_id'] . '.point' => (int) $price_on_point['point_id'],
                                'prices.' . $price_on_point['point_id'] . '.price' => (float) $price_on_point['price'],
                                'prices.' . $price_on_point['point_id'] . '.hide' => isset($price_on_point['hide']) ? $price_on_point['hide'] : false,

                            ],
                        ]);

                        DB::insert([
                            'point' => $price_on_point['point_id'],
                            'product' => $technical_card['product_id'],
                            'technical_card' => $args['id'],
                            'partner' => Auth::$user['id'],
                            'price' => $price_on_point['price'],
                            'hide' => $price_on_point['hide'] ? 1 : 0,
                        ], 'app_product_prices');
                    }

                    return DB::getRow(DB::select('*', "app_technical_card", "id=" . $args['id']));
                } else if (!isset($args['id'])) {

                    $technical_card = $args['technical_card'];

                    $productData = DB::select('id, partner,name,category,  image', DB_PRODUCTS, "id = {$technical_card['product_id']} AND (partner = " . Auth::$user['id'] . " OR partner IS NULL)");

                    if (DB::getRecordCount($productData) == 0) {
                        throw new Error("Такого товара не существует.");
                    }

                    $productData = DB::getRow($productData);

                    $fields = [
                        'product' => $technical_card['product_id'],
                        'bulk_untils' => $technical_card['bulk_untils'],
                        'bulk_value' => $technical_card['bulk_value'],
                        'subname' => $technical_card['subname'],
                        'name_price' => $technical_card['name_price'],
                        'cashback_percent' => $technical_card['cashback_percent'],
                        'preparing_minutes' => $technical_card['preparing_minutes'],
                        'preparing_seconds' => $technical_card['preparing_seconds'],
                        'cooking_method' => $technical_card['cooking_method'],
                        'color' => $technical_card['color_id'],
                        'different_price' => $technical_card['different_price'] ? 1 : 0,
                        'not_promotion' => $technical_card['not_promotion'] ? 1 : 0,
                        'weighted' => $technical_card['weighted'] ? 1 : 0,
                        'price' => (float) $technical_card['price'],
                        'partner' => Auth::$user['id'],
                    ];

                    $technical_card_data = DB::query('
                            SELECT tc.id, a.id AS arch
                            FROM app_technical_card tc
                            LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = tc.id AND a.model = "technical_card" AND partner_id = ' . Auth::$user['id'] . '
                            WHERE tc.product = ' . $technical_card['product_id'] . ' AND tc.bulk_value = "' . $technical_card['bulk_value'] . '" AND tc.bulk_untils = "' . $technical_card['bulk_untils'] . '" AND tc.subname = "' . $technical_card['subname'] . '"');

                    if (DB::getRecordCount($technical_card_data) != 0) {
                        $arch = DB::getRow($technical_card_data)['arch'];
                        if ($arch == null) {
                            throw new Error("Такая тех. карта уже существует");
                        } else {
                            throw new Error("Данная тех. карта находится в архиве, восстановите её.");
                        }

                    }

                    //Добавление технической карты
                    $id = DB::insert($fields, 'app_technical_card');

                    DB::update(['code' => 5000 + $id], 'app_technical_card', 'id = ' . $id);

                    //Еще в mongodb на случай миграциии может

                    $ids = [];
                    foreach ($technical_card['composition'] as $composition) {
                        $ids[] = $composition['item_id'];
                    }

                    $items = DB::makeArray(DB::select("*", "app_items", "id IN (" . implode(",", $ids) . ")"));

                    $compositionData = [];
                    $compositionText = [];

                    foreach ($technical_card['composition'] as $composition) {
                        foreach ($items as $item) {

                            if ($composition['item_id'] == $item['id']) {

                                $compositionText[] = trim($item['name']);

                                $compositionData[] = [
                                    'item' => [
                                        'id' => (int) $item['id'],
                                        'name' => $item['name'],
                                    ],
                                    "untils" => $item['untils'],
                                    'count' => (float) $composition['count'],
                                    'gross' => (float) $composition['gross'],
                                    'net_mass' => (float) $composition['net_mass'],
                                    'mass_block' => $composition['mass_block'],
                                    'divide' => isset($composition['divide']) ? $composition['divide'] : false,
                                ];

                                DB::insert([
                                    "item" => $composition['item_id'],
                                    "technical_card" => $id,
                                    "untils" => $item['untils'],
                                    "count" => $composition['count'],
                                    "gross" => $composition['gross'],
                                    "net_mass" => $composition['net_mass'],
                                    "mass_block" => $composition['mass_block'] ? 1 : 0,
                                ], 'app_product_composition');
                            }
                        }
                    }

                    $prices = [];

                    //  if ($technical_card['different_price']) {
                    foreach ($technical_card['price_on_points'] as $price_on_point) {

                        $prices[(int) $price_on_point['point_id']] = [
                            'point' => (int) $price_on_point['point_id'],
                            'price' => (float) $price_on_point['price'],
                            'hide' => $price_on_point['hide'],
                        ];

                        DB::insert([
                            'point' => $price_on_point['point_id'],
                            'product' => $technical_card['product_id'],
                            'technical_card' => $id,
                            'partner' => Auth::$user['id'],
                            'price' => $price_on_point['price'],
                            'hide' => $price_on_point['hide'] ? 1 : 0,
                        ], 'app_product_prices');
                    }

                    mDB::collection("technical_cards")->insertOne([
                        'product' => [
                            'id' => (int) $productData['id'],
                            'name' => (string) $productData['name'],
                            'category' => (int) $productData['category'],
                            'image' => (string) $productData['image'],
                        ],
                        "name" => (string) $productData['name'] . ' ' . $technical_card['subname'] . ' ' . $technical_card['bulk_value'] . ' ' . $technical_card['bulk_untils'],
                        'bulk_untils' => $technical_card['bulk_untils'],
                        'bulk_value' => (float) $technical_card['bulk_value'],
                        'subname' => $technical_card['subname'],
                        'name_price' => $technical_card['name_price'],
                        'cashback_percent' => (int) $technical_card['cashback_percent'],
                        'preparing_minutes' => (int) $technical_card['preparing_minutes'],
                        'preparing_seconds' => (int) $technical_card['preparing_seconds'],
                        'cooking_method' => $technical_card['cooking_method'],
                        'color' => (int) $technical_card['color_id'],
                        'price' => (int) $technical_card['price'],
                        'not_promotion' => $technical_card['not_promotion'],
                        'weighted' => $technical_card['weighted'],
                        'partner' => (int) Auth::$user['id'],
                        'code' => (int) (5000 + $id),
                        'id' => (int) $id,
                        'country' => Auth::$country,
                        'composition_text' => implode(', ', $compositionText),
                        'composition' => $compositionData,
                        'prices' => $prices,
                        'enableForAll' => false,
                        'canEdit' => [],
                        'archive' => [],
                        'showFor' => [],
                        'hideFor' => [],
                        'different_price' => $technical_card['different_price'],
                    ]);

                    // }

                    return DB::getRow(DB::select('*', "app_technical_card", "id=" . $id));
                }
            },
        ];
    }
}