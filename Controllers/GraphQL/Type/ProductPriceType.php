<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Buffers\PointsBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class ProductPriceType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Цена в заведениях',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'point' => [
                        'type' => Types::Point(),
                        'description' => 'Заведение',
                        'resolve' => function ($root, $args) {
                            if (isset($root['point'])) {
                                PointsBuffer::add((int) $root['point']);

                                return new Deferred(function () use ($root) {
                                    PointsBuffer::load();
                                    return PointsBuffer::get((int) $root['point']);
                                });
                            }
                        },
                    ],
                    'product' => [
                        'type' => Types::Product(),
                        'description' => 'Продукт',
                        'resolve' => function ($root, $args) {
                            if ($root['product'] > 0) {
                                Buffer::add('app_products', 'id', $root['product']);
                                return new Deferred(function () use ($root) {
                                    Buffer::load('app_products', 'id');
                                    return Buffer::get('app_products', 'id', $root['product']);
                                });
                            }
                        },
                    ],
                    'technical_card' => [
                        'type' => Types::TechnicalCard(),
                        'description' => 'Тех. карта',
                    ],
                    'partner' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера',
                    ],
                    'price' => [
                        'type' => Type::float(),
                        'description' => 'Цена',
                        'resolve' => function ($root, $args) {
                            return empty($root['price']) ? 0 : $root['price'];
                        },
                    ],
                    'hide' => [
                        'type' => Type::boolean(),
                        'description' => 'true - Скрыть, false - Нет',
                        /*  'resolve' => function ($root, $args) {
                    return  $root['production'] == 1 ? true : false;
                    }*/
                    ],

                ];
            },
        ];
        parent::__construct($config);
    }
}