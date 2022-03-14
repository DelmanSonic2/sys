<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\EditingAllowed;

class TechnicalCardType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Тех. карта',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'product' => [
                        'type' => Types::Product(),
                        'description' => 'Продукт',
                        'resolve' => function ($root, $args) {

                            if (is_numeric($root['product'])) {
                                Buffer::add('app_products', 'id', $root['product']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_products', 'id');
                                    return Buffer::get('app_products', 'id', $root['product']);
                                });
                            } else if (isset($root['product']['id'])) {
                                Buffer::add('app_products', 'id', $root['product']['id']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_products', 'id');
                                    return Buffer::get('app_products', 'id', $root['product']['id']);
                                });
                            } else {
                                return $root['product'];
                            }
                        },
                    ],
                    "name" => [
                        "type" => Type::string(),
                        "description" => "Полное имя как оно должно выглядеть на пользователя (Продукт+тех карта)",
                        'resolve' => function ($root, $args) {
                            $fullName = "";
                            if (isset($root['product']['name'])) {
                                $fullName = $root['product']['name'];
                            }

                            if (isset($root['bulk_value'])) {
                                $fullName = $fullName . ' ' . $root['bulk_value'];
                            }

                            if (isset($root['bulk_untils'])) {
                                $fullName = $fullName . ' ' . $root['bulk_untils'];
                            }

                            return $fullName;
                        },
                    ],
                    'subname' => [
                        'type' => Type::string(),
                        'description' => 'Название тех. карты',

                    ],
                    'name_price' => [
                        'type' => Type::string(),
                        'description' => 'Название для ценника',
                    ],
                    'code' => [
                        'type' => Type::string(),
                        'description' => 'Штрих-код',
                    ],
                    'partner' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера или NULL если общедоступный',
                    ],
                    'global' => [
                        "type" => Type::boolean(),
                        "description" => "Глобальный объект",
                        'resolve' => function ($root, $args) {
                            return ($root['partner'] < 1) ? true : false;
                        },
                    ],
                    'bulk_value' => [
                        'type' => Type::int(),
                        'description' => 'Объем/вес',
                    ],
                    'bulk_untils' => [
                        'type' => Type::string(),
                        'description' => 'Единицы измерения (г,мл,шт)',
                    ],
                    'price' => [
                        'type' => Type::float(),
                        'args' => [
                            'point' => [
                                "type" => Type::int(),
                                'description' => "Если передать ID заведения, то цена заведения, без заведения цена по умоланию",
                            ],
                        ],
                        'description' => 'Цена',
                        'resolve' => function ($root, $args) {
                            $prices = (array) $root['prices'];

                            if (isset($args['point'])) {

                                if (isset($prices[(int) $args['point']]['price'])) {
                                    return $prices[(int) $args['point']]['price'];
                                } else {
                                    return $root['price'];
                                }

                            } else {
                                return $root['price'];
                            }
                        },
                    ],
                    'preparing_minutes' => [
                        'type' => Type::int(),
                        'description' => 'Минуты - Время приготовления',
                    ],
                    'preparing_seconds' => [
                        'type' => Type::int(),
                        'description' => 'Секунды - Время приготовления',
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
                    "cost_price" => [
                        'type' => Type::float(),
                        'args' => [
                            'point' => [
                                "type" => Type::nonNull(Type::int()),
                                'description' => "ID заведения",
                            ],
                        ],
                        'description' => 'Себестоимость (на определенной точке)',
                        "deprecationReason" => "ТЯЖЕЛОЕ ПОЛЕ",
                        'resolve' => function ($root, $args) {

                            if (isset($root['price'])) {
                                $row = DB::getRow(DB::query("SELECT  SUM(IF(pcmp.untils = 'шт', pcmp.count, pcmp.gross) * pi.price) AS cost_price FROM
                             app_product_composition AS pcmp JOIN app_point_items AS pi ON pi.item = pcmp.item AND pi.point = {$args['point']} AND pcmp.technical_card={$root['id']}"));
                            }

                            return round($row['cost_price'], 2);
                        },
                    ],
                    "cost_price_calc" => [
                        'type' => Type::boolean(),
                        'args' => [
                            'point' => [
                                "type" => Type::nonNull(Type::int()),
                                'description' => "ID заведения",
                            ],
                        ],
                        "deprecationReason" => "ТЯЖЕЛОЕ ПОЛЕ",
                        'description' => 'Не удалось расчитать себестоимость (не хватает ингридиентов)',
                        'resolve' => function ($root, $args) {

                            $row = DB::getRow(DB::query("SELECT MIN(IFNULL(pi.price, 0)) AS cost_price_calc FROM `app_technical_card` tr JOIN `app_product_composition` cmp JOIN `app_point_items` pi WHERE tr.id = cmp.`technical_card` AND cmp.`item`= pi.`item` AND tr.id  = {$root['id']} AND pi.`point`={$args['point']} LIMIT 1"));

                            return $row['cost_price_calc'] > 0 ? true : false;
                        },
                    ],
                    "markup" => [
                        'type' => Type::float(),
                        'args' => [
                            'point' => ["type" => Type::nonNull(Type::int()), 'description' => "ID заведения"],
                        ],
                        'description' => 'Наценка (на определенной точке)',
                        "deprecationReason" => "ТЯЖЕЛОЕ ПОЛЕ",
                        'resolve' => function ($root, $args) {

                            $row = DB::getRow(DB::query("SELECT  SUM(IF(pcmp.untils = 'шт', pcmp.count, pcmp.gross) * pi.price) AS cost_price FROM
                            app_product_composition AS pcmp JOIN app_point_items AS pi ON pi.item = pcmp.item AND pi.point = {$args['point']} AND pcmp.technical_card={$root['id']}"));

                            $diff_price = $root['price'] - ($row['cost_price'] ?? 0);

                            if ($row['cost_price'] > 0) {
                                return $diff_price / $row['cost_price'] * 100;
                            } else {
                                return 0;
                            }

                        },
                    ],

                    'my' => [
                        'type' => Type::boolean(),
                        "description" => "Моя тех. карта ТОЧНО НЕ ЗНАЮ ЗАЧЕМ НАДО!",
                        'resolve' => function ($root, $args) {
                            return false;
                        },
                    ],
                    'can_share' => [
                        'type' => Type::boolean(),
                        "description" => "НЕ ЗНАЮ ЧТО ЗНАЧИТ ЭТО ПОЛЕ!",
                        'resolve' => function ($root, $args) {
                            return false;
                        },
                    ],
                    'editing_allowed' => [
                        'type' => Type::boolean(),
                        "description" => "Доступно для редактирования",
                        'resolve' => function ($root, $args) {
                            return EditingAllowed::TechnicalCard($root);
                        },
                    ],

                    'net_mass' => [
                        "type" => Type::float(),
                        "description" => "Масса Нетто / Выход",
                        'resolve' => function ($root, $args) {

                            $row = DB::getRow(DB::query("SELECT SUM(net_mass) as net_mass FROM app_product_composition  WHERE technical_card={$root['id']} AND untils != 'шт'"));

                            return round($row['net_mass'], 2);
                        },

                    ],
                    'archive' => [
                        "type" => Type::boolean(),
                        "description" => "Архив",
                        'resolve' => function ($root, $args) {
                            if (isset($root['archive'])) {

                                return in_array((int) Auth::$user['id'], (array) $root['archive']);
                            } else {
                                return false;
                            }
                        },
                    ],
                    'different_price' => [
                        "type" => Type::boolean(),
                        "description" => "Разная цена в заведениях",
                        'resolve' => function ($root, $args) {
                            if ($root['enableForAll']) {
                                return true;
                            } else {
                                return isset($root['different_price']) ? $root['different_price'] : true;
                            }

                        },
                    ],
                    "not_promotion" => [
                        "type" => Type::boolean(),
                        "description" => "Не участвует в акциях",
                        'resolve' => function ($root, $args) {
                            return $root['not_promotion'];
                        },
                    ],
                    "cooking_method" => [
                        "type" => Type::string(),
                        "description" => "Способ приготовления",
                    ],
                    "cashback_percent" => [
                        "type" => Type::int(),
                        "description" => "Процент кешбэка (если отличается от того что по карте)",
                    ],
                    "composition_description" => [
                        "type" => Type::string(),
                        "description" => "Описание состава",
                    ],
                    "count" => [
                        "type" => Type::int(),
                        "description" => "Кол-во",
                    ],
                    "weighted" => [
                        "type" => Type::boolean(),
                        "description" => "Весовой товар",
                        'resolve' => function ($root, $args) {
                            return $root['weighted'] == 1 ? true : false;
                        },
                    ],
                    'composition' => [
                        "type" => Type::listOf(Types::Composition()),
                        "description" => "Состав тех. каты",
                        'resolve' => function ($root, $args) {

                            Buffer::add('app_product_composition', 'technical_card', $root['id']);

                            return new Deferred(function () use ($root) {
                                Buffer::load('app_product_composition', 'technical_card', true);
                                return Buffer::get('app_product_composition', 'technical_card', $root['id']);
                            });

                            /*    $data = [];
                        foreach ($root['composition'] as &$composition) {
                        $composition['technical_card'] = $root['id'];
                        $data[$composition['item']['id']] = $composition;
                        }
                        return $data;*/
                        },
                    ],
                    "price_on_points" => [
                        "type" => Type::listOf(Types::ProductPrice()),
                        "description" => "Цены на точках",
                        "deprecationReason" => "ТЯЖЕЛОЕ ПОЛЕ",
                        'resolve' => function ($root, $args) {

                            //TODO может сделать буфферр

                            $prices = $root['prices'];

                            $points = explode(",", DB::getRow(DB::query("SELECT GROUP_CONCAT(id) as points FROM `app_partner_points` WHERE partner=" . Auth::$user['id']))['points']);

                            $product_prices = [];

                            foreach ($points as $point) {
                                if (isset($prices[(int) $point])) {
                                    $product_prices[] = [
                                        'id' => (int) $point,
                                        'point' => $prices[(int) $point]['point'],
                                        'product' => $root['product']['id'],
                                        'technical_card' => $root['id'],
                                        'partner' => Auth::$user['id'],
                                        'price' => $prices[(int) $point]['price'],
                                        'hide' => $prices[(int) $point]['hide'],
                                    ];
                                } else {
                                    $product_prices[] = [
                                        'id' => (int) $point,
                                        'point' => (int) $point,
                                        'product' => $root['product']['id'],
                                        'technical_card' => $root['id'],
                                        'partner' => Auth::$user['id'],
                                        'price' => $root['price'],
                                        'hide' => false,
                                    ];
                                }
                            }

                            return $product_prices;
                        }
                    ]

                ];
            },
        ];
        parent::__construct($config);
    }
}