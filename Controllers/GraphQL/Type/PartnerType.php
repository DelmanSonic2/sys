<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class PartnerType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Партнер',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера',
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Имя',
                    ]
                ];
            }
        ];
        parent::__construct($config);
    }
}