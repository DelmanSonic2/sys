<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class ItemCategoryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Категория ингридиента',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Название категории ингредиента',
                    ],
                    'partner' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера или NULL если общедоступный',
                    ],
                    "ingredient_count" => [
                        "type" => Type::int(),
                        "description" => "Кол-во ингредиентов",
                        "resolve" => function ($root, $args) {
                            return  DB::getRow(DB::query("SELECT COUNT(id) as count FROM `app_items` WHERE `category`=" . $root['id']))["count"];
                        }
                    ],
                    "stock_balance_sum" => [
                        "type" => Type::float(),
                        "description" => "Сумма остатков",
                        "resolve" => function ($root, $args) {
                            return  DB::getRow(DB::query("SELECT SUM(pi.count*pi.price) as sum FROM `app_items` i JOIN `app_point_items` pi ON pi.`item`=i.id WHERE i.category=" . $root['id'] . " AND pi.`partner`=" . Auth::$user['id']))['sum'];
                        }
                    ],
                    "count_num" => [
                        "type" => Type::float(),
                        "description" => "Остатков шт",
                        "resolve" => function ($root, $args) {
                            return  DB::getRow(DB::query("SELECT SUM(pi.count) as count FROM `app_items` i JOIN `app_point_items` pi ON pi.`item`=i.id WHERE i.category=" . $root['id'] . " AND i.`untils`='шт' AND pi.`partner`=" . Auth::$user['id']))['count'];
                        }
                    ],
                    "count_weight" => [
                        "type" => Type::float(),
                        "description" => "Остатков кг",
                        "resolve" => function ($root, $args) {
                            return  DB::getRow(DB::query("SELECT SUM(pi.count) as count FROM `app_items` i JOIN `app_point_items` pi ON pi.`item`=i.id WHERE i.category=" . $root['id'] . " AND i.`untils`='кг' AND pi.`partner`=" . Auth::$user['id']))['count'];
                        }
                    ],
                    "count_vol" => [
                        "type" => Type::float(),
                        "description" => "Остатков л",
                        "resolve" => function ($root, $args) {
                            return  DB::getRow(DB::query("SELECT SUM(pi.count) as count FROM `app_items` i JOIN `app_point_items` pi ON pi.`item`=i.id WHERE i.category=" . $root['id'] . " AND i.`untils`='л' AND pi.`partner`=" . Auth::$user['id']))['count'];
                        }
                    ],
                    'editing_allowed' => [
                        'type' => Type::boolean(),
                        "description" => "Доступно для редактирования, иначе только просмотреть",
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] == Auth::$user['id']) ? true : false;
                        }
                    ]

                ];
            }
        ];
        parent::__construct($config);
    }
}