<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffers\ItemProductPriceOnPointBuffer;
use Controllers\GraphQL\Buffers\ItemsBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class CompositionItemType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Состав продукта',
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
                    'item' => [
                        'type' => Types::Item(),
                        'description' => 'Ингредиент',
                        'resolve' => function ($root, $args) {
                            if (isset($root['item'])) {
                                $id = isset($root['item']['id']) ? $root['item']['id'] : $root['item'];
                                ItemsBuffer::add('id', $id);
                                return new Deferred(function () use ($root, $id) {
                                    ItemsBuffer::load('id');
                                    return ItemsBuffer::get('id', $id);
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
                        "description" => "Себестоимость",
                        'resolve' => function ($root, $args) {

                            ItemProductPriceOnPointBuffer::add($args['point'], $root['product'], $root['item']);

                            return new Deferred(function () use ($root, $args) {
                                ItemProductPriceOnPointBuffer::load();
                                return ItemProductPriceOnPointBuffer::get($args['point'], $root['product'], $root['item']);
                            });

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
                    "nodivide" => [
                        "type" => Type::boolean(),
                        "description" => "Не разбирать при производстве",
                        'resolve' => function ($root, $args) {
                            return isset($root['nodivide']) ? $root['nodivide'] : false;
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