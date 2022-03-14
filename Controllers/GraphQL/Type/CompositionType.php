<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class CompositionType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Состав тех. карта',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                        'resolve' => function ($root, $args) {
                            if (isset($root['item'])) {
                                return isset($root['item']['id']) ? $root['item']['id'] : $root['item'];
                            }
                        },
                    ],
                    'technical_card' => [
                        'type' => Type::int(),
                        'description' => 'тех. карты',
                    ],
                    'item' => [
                        'type' => Types::Item(),
                        'description' => 'Ингредиент (ТЯЖЕЛОЕ ПОЛЕ)',
                        'resolve' => function ($root, $args) {
                            if (isset($root['item'])) {
                                $id = isset($root['item']['id']) ? $root['item']['id'] : $root['item'];
                                Buffer::add('app_items', 'id', $id);
                                return new Deferred(function () use ($root, $id) {
                                    Buffer::load('app_items', 'id');
                                    return Buffer::get('app_items', 'id', $id);
                                });
                            }
                        },
                    ],
                    'untils' => [
                        'type' => Type::string(),
                        'description' => 'Единицы измерения (кг,л,шт)',
                    ],
                    'count' => [
                        "type" => Type::float(),
                        "description" => "Кол-во",
                    ],
                    'price' => [
                        "type" => Type::float(),
                        'args' => [
                            'point' => ["type" => Type::nonNull(Type::int()), 'description' => "ID заведения"],
                        ],
                        "description" => "Себестоимость (ТЯЖЕЛОЕ ПОЛЕ) (Если не удалось расчитать то null)",
                        'resolve' => function ($root, $args) {
                            //TODO буффер

                            $id = isset($root['item']['id']) ? $root['item']['id'] : $root['item'];

                            $row = DB::getRow(DB::query("SELECT pi.item,  SUM(pi.price * IF(c.untils = 'шт', c.count, c.gross)) AS cost_price, AVG(pi.price) AS calc
                            FROM  `app_product_composition` c
                            JOIN  `app_items` AS i ON i.id = c.item
                            LEFT JOIN `app_point_items` AS pi ON pi.item = c.item AND pi.point = {$args['point']}
                            WHERE c.technical_card = {$root['technical_card']} AND pi.item={$id}
                            GROUP BY c.id LIMIT 1"));
                            return (float) $row['cost_price'] > 0 ? $row['cost_price'] : null;
                        },

                    ],

                    "gross" => [
                        "type" => Type::float(),
                        "description" => "Брутто",
                    ],
                    "net_mass" => [
                        "type" => Type::float(),
                        "description" => "Нетто",
                    ],
                    "mass_block" => [
                        "type" => Type::boolean(),
                        "description" => "true - cохраняется вводимое значение/ false - автоматический расчет",
                        'resolve' => function ($root, $args) {
                            return ($root['mass_block'] == 1 || $root['mass_block'] == true) ? true : false;
                        },
                    ],
                    "divide" => [
                        "type" => Type::boolean(),
                        "description" => "Разбирать при продаже",
                        'resolve' => function ($root, $args) {
                            return isset($root['divide']) ? $root['divide'] : false;
                        },
                    ],

                ];
            },
        ];
        parent::__construct($config);
    }
}

/*       "cleaning"=>[
"type" => Type::float(),
"description"=>"% потерь при чистке"
],
"cooking"=>[
"type" => Type::float(),
"description"=>"% потерь при готовке"
],
"frying"=>[
"type" => Type::float(),
"description"=>"% потерь при жарке"
],
"stew"=>[
"type" => Type::float(),
"description"=>"% потерь при тушении"
],
"bake"=>[
"type" => Type::float(),
"description"=>"% потерь при запекании"
],
'cleaning_checked'=>[
"type" => Type::boolean(),
"description"=>"Чистка",
'resolve' => function ($root, $args) {
return  $root['cleaning_checked'] == 1 ? true : false;
}
],*/
/*      'cooking_checked'=>[
"type" => Type::boolean(),
"description"=>"Готовка",
'resolve' => function ($root, $args) {
return  $root['cooking_checked'] == 1 ? true : false;
}
],
'frying_checked'=>[
"type" => Type::boolean(),
"description"=>"Жарка",
'resolve' => function ($root, $args) {
return  $root['frying_checked'] == 1 ? true : false;
}
],
'stew_checked'=>[
"type" => Type::boolean(),
"description"=>"Тушение",
'resolve' => function ($root, $args) {
return  $root['stew_checked'] == 1 ? true : false;
}
],
'bake_checked'=>[
"type" => Type::boolean(),
"description"=>"Запекание",
'resolve' => function ($root, $args) {
return  $root['bake_checked'] == 1 ? true : false;
}
],*/