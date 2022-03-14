<?php

namespace Controllers\GraphQL\Type;


use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class PromotionType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Акция',
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