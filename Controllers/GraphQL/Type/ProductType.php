<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Buffers\ProductPointHideBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class ProductType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Товары (aka Продукта)',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Название товара',
                    ],
                    'image' => [
                        'type' => Type::string(),
                        'description' => 'Обложка товара',
                        'resolve' => function ($root, $args) {
                            return $root['image'] == '' ? PLACEHOLDER_IMAGE : $root['image'];
                        }
                    ],
                    'archive' => [
                        "type" => Type::boolean(),
                        "description" => "Архив",
                        'resolve' => function ($root, $args) {
                            return  $root['archive'] == 1 ? true : false;
                        }
                    ],
                    'weighted_technical_card' => [
                        'type' => Type::boolean(),
                        'description' => 'В товаре есть весовая техкарта',
                        'resolve' => function ($root, $args) {
                            $row = DB::getRow(DB::query("SELECT * FROM `app_technical_card` WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) AND `product`=" . $root['id'] . " AND `weighted`=1 LIMIT 1"));
                            return isset($row['id']) ? true : false;
                        }
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
                        }
                    ],
                    'category' => [
                        'type' => Types::ProductCategory(),
                        'description' => 'Категории товаров',
                        'resolve' => function ($root, $args) {
                            if ($root['category'] > 0) {
                                Buffer::add('app_product_categories', 'id', $root['category']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_product_categories', 'id');
                                    return Buffer::get('app_product_categories', 'id', $root['category']);
                                });
                            }
                        }
                    ],
                    'my' =>  [
                        'type' => Type::boolean(),
                        'description' => 'Мой товар',
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] == Auth::$user['id'] || ($root['partner'] == null && Auth::$user['admin'])) ? true : false;
                        }
                    ],
                    'global' => [
                        "type" => Type::boolean(),
                        "description" => "Глобальный объект",
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] == null) ? true : false;
                        }
                    ],
                    'partner' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера или NULL если общедоступный',
                    ],
                    'editing_allowed' => [
                        'type' => Type::boolean(),
                        "description" => "Доступно для полного редактирования, иначе только проставить свои точки",
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] == Auth::$user['id'] || ($root['partner'] == null && Auth::$user['admin'])) ? true : false;
                        }
                    ],
                    "exist_technical_card" => [
                        "type" => Type::boolean(),
                        "description" => "Есть ли тех карты у продукта",
                        'resolve' => function ($root, $args) {
                            return  mDB::collection("technical_cards")->count([
                                "product.id" => (int)$root['id'],
                                'country' => Auth::$country
                            ]) > 0 ? true : false;
                        }
                    ],
                    'points' => [
                        'type' => Type::listOf(Types::ProductPoint()),
                        'description' => 'Доступность на точках',
                        'resolve' => function ($root, $args) {


                            ProductPointHideBuffer::add((int)$root['id']);

                            return new Deferred(function () use ($root) {
                                ProductPointHideBuffer::load();
                                return  ProductPointHideBuffer::get((int)$root['id']);
                            });
                        }

                    ]

                ];
            }
        ];
        parent::__construct($config);
    }
}