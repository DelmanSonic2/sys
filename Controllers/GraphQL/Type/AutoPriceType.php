<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class AutoPriceType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Автоизменения цен',
            'fields' => function () {
                return [
                    '_id' => [
                        "type" => Type::string(),
                    ],
                    'date' => [
                        "type" => Type::int(),
                        "description" => "Дата изменения"
                    ],
                    "done" => [
                        "type" => Type::boolean(),
                        "description" => "Выполнен",
                        'resolve' => function ($root, $args) {
                            if ($root['date'] < time()) return true;
                            else return false;
                        }
                    ],
                    'created_at' => [
                        "type" => Type::int(),
                        "description" => "Дата создания"
                    ],
                    "creator" => [
                        "type" => Type::string(),
                        "description" => "Создатель",
                    ],
                    "prices" => [
                        "type" => Type::listOf(Types::AutoPriceItem()),
                        "description" => "Список цен"
                    ],
                    "comment" => [
                        "type" => Type::string(),
                        "description" => "Комментарий"
                    ]


                ];
            }
        ];
        parent::__construct($config);
    }
}