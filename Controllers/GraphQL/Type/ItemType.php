<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Buffers\ItemAvrPriceBuffer;
use Controllers\GraphQL\Buffers\ItemPriceBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\EditingAllowed;

class ItemType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Ингридиент',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Название ингредиента',
                    ],
                    'price' => [
                        'type' => Type::float(),
                        'args' => [
                            'point' => ["type" => Type::nonNull(Type::int()), 'description' => "ID заведения"],
                        ],
                        'description' => 'Цена ингредиента на точке как ингридиента',
                        'resolve' => function ($root, $args) {

                            ItemPriceBuffer::add($args['point'], $root['id']);
                            return new Deferred(function () use ($root) {
                                ItemPriceBuffer::load();
                                $row = ItemPriceBuffer::get($root['id']);
                                return $row['price'] ?? 0;
                            });
                        },
                    ],
                    'category' => [
                        'type' => Types::ItemCategory(),
                        'description' => 'Категория ингридиента',
                        'resolve' => function ($root, $args) {
                            if ($root['category'] > 0) {
                                Buffer::add('app_items_category', 'id', $root['category']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_items_category', 'id');
                                    return Buffer::get('app_items_category', 'id', $root['category']);
                                });
                            }
                        },
                    ],
                    'avg_price' => [
                        "type" => Type::float(),
                        "description" => "Средняя цена на точках и складах партнера (Если не удалось расчитать то null)",
                        'resolve' => function ($root, $args) {

                            ItemAvrPriceBuffer::add($root['id']);
                            return new Deferred(function () use ($root) {
                                ItemAvrPriceBuffer::load();
                                $avg_price = ItemAvrPriceBuffer::get($root['id']);
                                return (float) $avg_price > 0 ? $avg_price : null;
                            });
                        },
                    ],

                    'product_category' => [
                        'type' => Types::ProductCategory(),
                        'description' => 'Категории товаров',
                        'resolve' => function ($root, $args) {
                            if ($root['product_category'] > 0) {
                                Buffer::add('app_product_categories', 'id', $root['product_category']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_product_categories', 'id');
                                    return Buffer::get('app_product_categories', 'id', $root['product_category']);
                                });
                            }
                        },
                    ],
                    'editing_allowed' => [
                        'type' => Type::boolean(),
                        "description" => "Доступно для редактирования, иначе только просмотреть",
                        'resolve' => function ($root, $args) {
                            return EditingAllowed::Item($root);
                        },
                    ],
                    'global' => [
                        "type" => Type::boolean(),
                        "description" => "Глобальный объект",
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] < 1) ? true : false;
                        },
                    ],
                    'partner' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера или NULL если общедоступный',
                    ],
                    'conversion_item_id' => [
                        'type' => Type::int(),
                        'description' => 'ID ингредиента в который будет преобразовыватья текущий при перемещении',
                    ],
                    'conversion_item' => [
                        'type' => Types::Item(),
                        'description' => 'Ингредиент в который будет преобразовыватья текущий при перемещении',
                        'resolve' => function ($root, $args) {
                            if ($root['conversion_item_id'] > 0) {
                                Buffer::add('app_items', 'id', $root['conversion_item_id']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_items', 'id');
                                    return Buffer::get('app_items', 'id', $root['conversion_item_id']);
                                });
                            }
                        },
                    ],
                    'untils' => [
                        'type' => Type::string(),
                        'description' => 'Единицы измерения (кг, л и прочее)',
                    ],
                    'bulk' => [
                        'type' => Type::float(),
                        'description' => 'Вес за 1 ед. измерения',
                    ],
                    'production' => [
                        "type" => Type::boolean(),
                        "description" => "false - ингредиент, true - полуфабрикат",
                        'resolve' => function ($root, $args) {
                            return $root['production'] == 1 ? true : false;
                        },
                    ],
                    'round' => [
                        "type" => Type::boolean(),
                        "description" => "false - не округлять, true - округлять",
                        'resolve' => function ($root, $args) {
                            return $root['round'] == 1 ? true : false;
                        },
                    ],
                    'print_name' => [
                        'type' => Type::string(),
                        'description' => 'Название для печати',
                    ],
                    'composition_description' => [
                        'type' => Type::string(),
                        'description' => 'Описание состава',
                    ],
                    'energy_value' => [
                        'type' => Type::string(),
                        'description' => 'Энергетическая ценность',
                    ],
                    'nutrients' => [
                        'type' => Type::string(),
                        'description' => 'Питательные вещества (белки, жиры, углеводы)',
                    ],
                    'shelf_life' => [
                        'type' => Type::string(),
                        'description' => 'Срок годности',
                    ],
                    'archive' => [
                        "type" => Type::boolean(),
                        "description" => "Архив",
                        'resolve' => function ($root, $args) {
                            return $root['archive'] == 1 ? true : false;
                        },
                    ],
                    'stock_balance' => [
                        'type' => Type::string(),
                        'description' => 'Остатки',
                        'resolve' => function ($root, $args) {

                            $stock_balance = DB::getRow(DB::query("SELECT sum(count) as balance FROM `app_point_items` WHERE `partner`=" . Auth::$user['id'] . " AND `item`=" . $root['id'] . " LIMIT 1"));
                            return isset($stock_balance['balance']) ? $stock_balance['balance'] : 0;
                        },

                    ],
                    'composition' => [
                        "type" => Type::listOf(Types::CompositionItem()),
                        "description" => "Состав продукции ПФ",
                        'resolve' => function ($root, $args) {
                            return DB::makeArray(DB::query("SELECT * FROM app_productions_composition WHERE product=" . $root['id']));
                        },
                    ],
                    "composition_price" => [
                        'type' => Type::float(),
                        'args' => [
                            'point' => [
                                "type" => Type::nonNull(Type::int()),
                                'description' => "ID заведения",
                            ],
                        ],
                        'description' => 'Себестоимость по ингридиентам состава',
                        'resolve' => function ($root, $args) {

                            $row = DB::getRow(DB::query("SELECT  SUM(IF(pcmp.untils = 'шт', pcmp.count, pcmp.gross) * pi.price) AS cost_price FROM
                                app_productions_composition AS pcmp JOIN app_point_items AS pi ON pi.item = pcmp.item AND pi.point = {$args['point']} AND pcmp.product={$root['id']}"));

                            return round($row['cost_price'], 2);
                        },
                    ],

                    'stock_balance_sum' => [
                        'type' => Type::string(),
                        'description' => 'Сумма остатков',
                        'resolve' => function ($root, $args) {

                            $balance_sum = DB::getRow(DB::query("SELECT sum(count*price) as balance FROM `app_point_items` WHERE `partner`=" . Auth::$user['id'] . " AND `item`=" . $root['id'] . " LIMIT 1"));
                            return isset($balance_sum['balance']) ? $balance_sum['balance'] : 0;
                        },
                    ],

                ];
            },
        ];
        parent::__construct($config);
    }
}