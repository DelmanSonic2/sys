<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class ColorType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Цвета',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'code' => [
                        'type' => Type::string(),
                        'description' => 'Код цвета',
                    ]

                ];
            }
        ];
        parent::__construct($config);
    }
}
