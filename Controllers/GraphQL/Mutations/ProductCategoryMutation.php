<?php

namespace  Controllers\GraphQL\Mutations;

use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;





class ProductCategoryMutation
{






    public static function add()
    {
        return   [
            'type' => Types::ProductCategory(),
            'description' => 'Добавить/обновить категории',
            'args' => [
                'id' => [
                    "type" => Type::int(),
                    'description' => 'ID категории для обновления, при создании передавать не надо',
                ],
                'product_category' => [
                    "type" => Type::nonNull(Types::ProductCategoryInput()),
                    'description' => 'Категория',
                ],
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                $product_category = $args['product_category'];

                //Редактирование
                if (isset($args['id'])) {

                    $category_data = DB::getRow(DB::select("*", DB_PRODUCT_CATEGORIES, 'id=' .  $args['id']));

                    $set = [];

                    if ($category_data['partner'] == Auth::$user['id']) {
                        //Можно редактировать если тех карта партнера


                        $fields = [
                            'name' => $product_category['name'],
                            'color' => $product_category['color_id'],
                            'image' => $product_category['image'],
                        ];



                        if (isset($product_category['parent_id'])) {
                            $fields['parent'] = $product_category['parent_id'];
                        } else {
                            $fields['parent'] = 'NULL';
                        }

                        $set = $fields;

                        DB::update($fields, DB_PRODUCT_CATEGORIES, 'id=' .  $args['id']);
                    }

                    DB::delete(DB_MENU_CATEGORIES, 'partner = ' . Auth::$user['id'] . ' AND category = ' . $args['id']);

                    $points  = [];
                    foreach ($product_category['points'] as $point) {

                        $set['points.' . $point['point_id'] . '.point'] = $point['point_id'];
                        $set['points.' . $point['point_id'] . '.enable'] = $point['enable'];

                        if ($point['enable'] == true) {
                            DB::insert([
                                'partner' => Auth::$user['id'],
                                'category' => $args['id'],
                                'point' => $point['point_id'],
                            ], DB_MENU_CATEGORIES);
                        }
                    }

                    if ($set['parent'] == "NULL") $set['parent'] = 0;
                    mDB::collection("product_categories")->updateOne([
                        'id' => (int)$args['id'],
                        'country' => Auth::$country
                    ], ['$set' => $set]);

                    return DB::getRow(DB::select("*", DB_PRODUCT_CATEGORIES, 'id=' . $args['id']));
                } else {



                    $fields = [
                        'name' => $product_category['name'],
                        'color' => $product_category['color_id'],
                        'partner' => Auth::$user['id'],
                        'image' => $product_category['image'],
                    ];

                    if (isset($product_category['parent_id']) && $product_category['parent_id'] > 0) {
                        $fields['parent'] = $product_category['parent_id'];
                    } else {
                        $fields['parent'] = "NULL";
                    }

                    $category = DB::insert($fields, DB_PRODUCT_CATEGORIES);
                    $points = [];
                    foreach ($product_category['points'] as $point) {
                        $points[(int)$point['point_id']] = [
                            'point' => (int)$point['point_id'],
                            'enable' => $point['enable']
                        ];

                        if ($point['enable'] == true) {
                            DB::insert([
                                'partner' => Auth::$user['id'],
                                'category' => $category,
                                'point' => $point['point_id'],
                            ], DB_MENU_CATEGORIES);
                        }
                    }


                    mDB::collection("product_categories")->insertOne([
                        "id" => (int)$category,
                        "country" => Auth::$country,
                        'name' => $product_category['name'],
                        'color' => $product_category['color_id'],
                        'partner' => (int)Auth::$user['id'],
                        'image' => $product_category['image'],
                        'parent' => $product_category['parent_id'] ?? 0,
                        'points' => $points
                    ]);

                    return DB::getRow(DB::select("*", DB_PRODUCT_CATEGORIES, 'id=' . $category));
                }
            }
        ];
    }
}