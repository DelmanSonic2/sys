<?php

namespace  Controllers\GraphQL\Mutations;

use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;





class ProductMutation
{

    private static function MenuProducts($points, $product)
    {



        $points_raw = [];
        $enable_techcards = [];
        $hide_techcards = [];

        $setEnable = [];

        if (sizeof($points) > 0) {




            for ($i = 0; $i < sizeof($points); $i++) {

                if ($points[$i]['enable'] === true) {

                    $setEnable['prices.' . $points[$i]['point_id'] . '.hide'] = false;
                    $setEnable['prices.' . $points[$i]['point_id'] . '.point'] = (int)$points[$i]['point_id'];


                    $points_raw[] = '("' . Auth::$user['id'] . '", "' . $product . '", "' . $points[$i]['point_id'] . '")';

                    $enable_techcards[] = '(partner = ' . Auth::$user['id'] . ' AND product = ' . $product . ' AND point = ' . $points[$i]['point_id'] . ')';
                } else {

                    $setEnable['prices.' . $points[$i]['point_id'] . '.hide'] = true;
                    $setEnable['prices.' . $points[$i]['point_id'] . '.point'] = (int)$points[$i]['point_id'];

                    $hide_techcards[] = '(partner = ' . Auth::$user['id'] . ' AND product = ' . $product . ' AND point = ' . $points[$i]['point_id'] . ')';
                }
            }
        }


        if (sizeof($setEnable) > 0) {
            mDB::collection("technical_cards")->updateMany([
                'country' => Auth::$country,
                'product.id' => (int)$product,
            ], [
                '$set' => $setEnable
            ]);
        }


        if (count($enable_techcards) > 0)
            DB::query('
                UPDATE ' . DB_PRODUCT_PRICES . '
                SET hide = 0
                WHERE ' . implode(' OR ', $enable_techcards) . '
            ');

        if (count($hide_techcards) > 0)
            DB::query('
                UPDATE ' . DB_PRODUCT_PRICES . '
                SET hide = 1
                WHERE ' . implode(' OR ', $hide_techcards) . '
            ');

        DB::delete(DB_MENU_PRODUCTS, 'partner = ' .  Auth::$user['id'] . ' AND product = ' . $product);

        if (count($points_raw) > 0)
            DB::query('INSERT IGNORE INTO ' . DB_MENU_PRODUCTS . ' (partner, product, point) VALUES ' . implode(',', $points_raw));
    }

    public static function add()
    {
        return   [
            'type' => Types::Product(),
            'description' => 'Добавить/обновить продукт',
            'args' => [
                'id' => [
                    "type" => Type::int(),
                    'description' => 'ID продукта для обновления, при создании передавать не надо',
                ],
                'product' => [
                    "type" => Type::nonNull(Types::ProductInput()),
                    'description' => 'Продукт',
                ],
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                $product = $args['product'];

                //Редактирование
                if (isset($args['id'])) {

                    //Поверяем что это не глобалка
                    $productData = DB::getRow(DB::select("*", DB_PRODUCTS, 'id=' . $args['id']));

                    if ($productData['partner'] == Auth::$user['id'] || Auth::$user['admin']) {


                        //Изменяем товар в тех карте
                        mDB::collection("technical_cards")->updateMany([
                            "product.id" => (int)$args['id'],
                            "country" => Auth::$country
                        ], [
                            '$set' => [
                                'product.name' => $product['name'],
                                'product.category' => (int)$product['category_id'],
                                'product.color' => $product['color_id'],
                                'product.image' => isset($product['image']) ? $product['image'] : "",
                            ]
                        ]);


                        //Можно редактировать только свои и админу все
                        $fields = [
                            'name' => $product['name'],
                            'category' => $product['category_id'],
                            'color' => $product['color_id'],
                            'image' => isset($product['image']) ? $product['image'] : "",
                            'updated' => time(),
                        ];
                    }

                    $productId = $args['id'];
                    if (isset($fields)) DB::update($fields, DB_PRODUCTS, "id =" . $productId);
                    self::MenuProducts($product['points'], $productId);

                    return DB::getRow(DB::select("*", DB_PRODUCTS, "id=" . $productId));
                } else {

                    $product_exist = DB::query('
                    SELECT i.id, a.id AS arch
                    FROM ' . DB_PRODUCTS . ' i
                    LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = i.id AND a.model = "product" AND a.partner_id = ' . Auth::$user['id'] . '
                    WHERE i.name = "' . $product['name'] . '" AND (i.partner = ' . Auth::$user['id'] . ' OR i.partner IS NULL)
                ');

                    if (DB::getRecordCount($product_exist) > 0) {
                        $arch = DB::getRow($product_exist)['arch'];
                        if ($arch == null) throw new Error("Товар с таким названием уже существует.");
                        else throw new Error("Товар с таким названием находится в архиве, восстановите его.");
                    }
                    //Если создаем
                    $fields = [
                        'name' => $product['name'],
                        'category' => $product['category_id'],
                        'color' => $product['color_id'],
                        'image' => isset($product['image']) ? $product['image'] : "",
                        'partner' => Auth::$user['id'],
                        'created' => time(),
                        'updated' => time(),
                    ];

                    if ($productId = DB::insert($fields, DB_PRODUCTS)) {



                        //Создание ингридиента или(и) тех карты
                        if (isset($product['product_ingredient'])) {
                            $product_ingredient = $product['product_ingredient'];

                            if (isset($product_ingredient['item_id'])) {
                                $item_id = $product_ingredient['item_id'];
                            } else {

                                //Создаем ингридиент 

                                $item_id = DB::insert([
                                    'name' => $product['name'],
                                    'category' => isset($product_ingredient['category_id']) ? $product_ingredient['category_id'] : 1,
                                    'partner' => Auth::$user['id'],
                                    'untils' => 'шт',
                                ], 'app_items');
                            }


                            //Создаем тех карту 

                            $fieldsTechnicalCard = [
                                'product' => $productId,
                                'bulk_untils' => 'шт',
                                'bulk_value' => 1,
                                'subname' => "",
                                'name_price' => "",
                                'cashback_percent' => 0,
                                'preparing_minutes' => 0,
                                'preparing_seconds' => 0,
                                'cooking_method' => "",
                                'color' => 1,
                                'different_price' => 0,
                                'not_promotion' => 0,
                                'weighted' => 0,
                                'price' => $product_ingredient['price'],
                                'partner' => Auth::$user['id']
                            ];

                            //Добавление технической карты
                            $id = DB::insert($fieldsTechnicalCard, 'app_technical_card');
                            DB::update(['code' => 5000 + $id], 'app_technical_card', 'id = ' . $id);

                            //Состав тех карты 

                            DB::insert([
                                "item" => $item_id,
                                "technical_card" => $id,
                                "untils" => 'шт',
                                "count" => 1,
                                "gross" => 0,
                                "net_mass" => 0,
                                "mass_block" => 0
                            ], 'app_product_composition');


                            $points = DB::makeArray(DB::select("*", 'app_partner_points', "partner=" . Auth::$user['id']));

                            $prices = [];




                            foreach ($points as $point) {

                                $prices[(int)$point['id']] = [
                                    'point' => $point['id'],
                                    'price' => (float)$product_ingredient['price'],
                                    'hide' => false
                                ];


                                DB::insert([
                                    'point' => $point['id'],
                                    'product' => $productId,
                                    'technical_card' => $id,
                                    'partner' => Auth::$user['id'],
                                    'price' => $product_ingredient['price'],
                                    'hide' =>  0
                                ], 'app_product_prices');
                            }


                            $technical_card = $fieldsTechnicalCard;
                            mDB::collection("technical_cards")->insertOne([
                                'product' => [
                                    'id' => (int)$productId,
                                    'name' => $product['name'],
                                    'category' => $product['category_id'],
                                    'color' => $product['color_id'],
                                    'image' => isset($product['image']) ? $product['image'] : ""
                                ],
                                "name" => (string)$product['name'] . ' ' . $technical_card['subname'] . ' ' . $technical_card['bulk_value'] . ' ' . $technical_card['bulk_untils'],
                                'bulk_untils' => $technical_card['bulk_untils'],
                                'bulk_value' => (float)$technical_card['bulk_value'],
                                'subname' => $technical_card['subname'],
                                'name_price' => $technical_card['name_price'],
                                'cashback_percent' => (int)$technical_card['cashback_percent'],
                                'preparing_minutes' => (int)$technical_card['preparing_minutes'],
                                'preparing_seconds' => (int)$technical_card['preparing_seconds'],
                                'cooking_method' => $technical_card['cooking_method'],
                                'color' => (int)$technical_card['color_id'],
                                'price' => (int)$technical_card['price'],
                                'not_promotion' => $technical_card['not_promotion'],
                                'weighted' => $technical_card['weighted'],
                                'partner' => (int)Auth::$user['id'],
                                'code' => (int)(5000 + $id),
                                'id' => (int)$id,
                                'country' => Auth::$country,
                                'composition_text' => $product['name'],
                                'composition' => [[
                                    'item' => [
                                        'id' => (int)$item_id,
                                        'name' => $product['name']
                                    ],
                                    "untils" => 'шт',
                                    "count" => 1,
                                    "gross" => 0,
                                    "net_mass" => 0,
                                    "mass_block" => false
                                ]],
                                'prices' => $prices,
                                'enableForAll' => false,
                                'canEdit' => [],
                                'showFor' => [],
                                'hideFor' => [],
                                'archive' => [],
                                'different_price' => false
                            ]);
                        }


                        self::MenuProducts($product['points'], $productId);

                        return DB::getRow(DB::select("*", DB_PRODUCTS, "id=" . $productId));
                    } else throw new Error("Ошибка при создании товара.");
                }
            }
        ];
    }
}