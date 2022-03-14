<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class MenuProductCategoryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Категория для терминала',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Название',
                    ],
                    'parent' => [
                        'type' => Type::int(),
                        'description' => 'Родитель',
                    ],
                    'image' => [
                        'type' => Type::string(),
                        'description' => 'Обложка категориии',
                        'resolve' => function ($root, $args) {
                            return $root['image'] == '' ? PLACEHOLDER_IMAGE : $root['image'];
                        }
                    ],
                    'products' => [
                        'type' => Type::listOf(Types::MenuProduct()),
                        'description' => 'Товары',
                    ]



                ];
            }
        ];
        parent::__construct($config);
    }
}