<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Buffers\PointsBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class ProductCategoryPointType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Доступность категории на точке',
            'fields' => function () {
                return [

                    'point' => [
                        'type' => Types::Point(),
                        'description' => 'Точка',
                        'resolve' => function ($root, $args) {
                            if (isset($root['point'])) {
                                PointsBuffer::add((int)$root['point']);

                                return new Deferred(function () use ($root) {
                                    PointsBuffer::load();
                                    return PointsBuffer::get((int)$root['point']);
                                });
                            }
                        }
                    ],

                    'enable' =>  [
                        'type' => Type::boolean(),
                        'description' => 'Отображать',
                        'resolve' => function ($root, $args) {
                            return $root['enable'] == 1 ? true : false;
                        }
                    ]


                ];
            }
        ];
        parent::__construct($config);
    }
}