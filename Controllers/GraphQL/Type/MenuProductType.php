<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class MenuProductType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Товар для точки продаж',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Название товара',
                        'resolve' => function ($root, $args) {
                            return trim($root['name']);
                        }
                    ],
                    'image' => [
                        'type' => Type::string(),
                        'description' => 'Обложка товара',
                        'resolve' => function ($root, $args) {
                            return $root['image'] == '' ? PLACEHOLDER_IMAGE : $root['image'];
                        }
                    ],
                    'cards' => [
                        'type' => Type::listOf(Types::MenuCard()),
                        'description' => 'Тех карты товара',
                    ]



                ];
            }
        ];
        parent::__construct($config);
    }
}