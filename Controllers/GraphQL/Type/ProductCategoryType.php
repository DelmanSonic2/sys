<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class ProductCategoryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Категория товара',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Название категории товара',
                    ],
                    'image' => [
                        'type' => Type::string(),
                        'description' => 'Обложка категориии',
                        'resolve' => function ($root, $args) {
                            return $root['image'] == '' ? PLACEHOLDER_IMAGE : $root['image'];
                        },
                    ],
                    'partner' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера или NULL если общедоступный',
                    ],
                    'color' => [
                        'type' => Types::Color(),
                        'description' => 'Цвет',
                        'resolve' => function ($root, $args) {
                            if ($root['color'] > 0) {
                                Buffer::add('app_colors', 'id', $root['color']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_colors', 'id');
                                    return Buffer::get('app_colors', 'id', $root['color']);
                                });
                            }
                        },
                    ],
                    'global' => [
                        "type" => Type::boolean(),
                        "description" => "Глобальный объект",
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] < 1) ? true : false;
                        },
                    ],
                    'ability_add' => [
                        "type" => Type::boolean(),
                        "description" => "Категория доступна для добавления своих товаров",
                        'resolve' => function ($root, $args) {
                            return true; // ($root['partner'] > 0) ? true : false;
                        },
                    ],
                    'editing_allowed' => [
                        'type' => Type::boolean(),
                        "description" => "Доступно для полного редактирования, иначе только проставить свои точки",
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] == Auth::$user['id'] || ($root['partner'] == null && Auth::$user['admin'])) ? true : false;
                        },
                    ],
                    'parent_id' => [
                        'type' => Type::int(),
                        'description' => 'ID родительской категории',
                        'resolve' => function ($root, $args) {
                            if ($root['parent'] > 0) {
                                return $root['parent'];
                            } else {
                                return null;
                            }

                        },

                    ],
                    'parent' => [
                        'type' => Types::ProductCategory(),
                        'description' => 'Родительская категория',
                        'resolve' => function ($root, $args) {
                            if ($root['parent'] > 0) {
                                Buffer::add('app_product_categories', 'id', $root['parent']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_product_categories', 'id');
                                    return Buffer::get('app_product_categories', 'id', $root['parent']);
                                });
                            }
                        },
                    ],
                    'points' => [
                        'type' => Type::listOf(Types::ProductCategoryPoint()),
                        'description' => 'Доступность на точках (ТЯЖЕЛОЕ ПОЛЕ)',
                        'resolve' => function ($root, $args) {

                            if (isset($root['points'])) {
                                $points = $root['points'];

                                $all_points = explode(",", DB::getRow(DB::query("SELECT GROUP_CONCAT(id) as points FROM `app_partner_points` WHERE partner=" . Auth::$user['id']))['points']);

                                $data = [];

                                foreach ($all_points as $point) {
                                    if (isset($points[(int) $point])) {
                                        $data[] = $points[(int) $point];
                                    } else {
                                        $data[] = [
                                            'point' => (int) $point,
                                            'enable' => false,
                                        ];
                                    }
                                }

                                return $data;
                            } else {
                                return DB::makeArray(DB::query("SELECT point.id as point, IF(menu.category> 0, 1, 0) as enable  FROM `app_partner_points` point LEFT JOIN (SELECT * FROM `app_menu_categories`WHERE `category` = " . $root['id'] . ") menu ON menu.`point` = point.id WHERE point.`partner` = " . Auth::$user['id']));
                            }
                        }

                    ]

                ];
            },
        ];
        parent::__construct($config);
    }
}