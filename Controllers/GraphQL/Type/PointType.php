<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class PointType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Точка/Заведение',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Название',
                    ]

                ];
            }
        ];
        parent::__construct($config);
    }
}
