<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Buffers\EmployeesBuffer;
use Controllers\GraphQL\Buffers\PointsBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class TransactionItemType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Позиция транзакции',
            'fields' => function () {
                return [


                    "name" => [
                        "type" => Type::string(),
                        "description" => "Название позиции",
                        "resolve" => function ($root, $args) {
                            return isset($root['promotion_name']) ? $root['promotion_name'] : $root['name'];
                        }
                    ],

                    "count" => [
                        "type" => Type::float(),
                        "description" => "Кол-во"
                    ],
                    "price" => [
                        "type" => Type::float(),
                        "description" => "Цена"
                    ],
                    "total" => [
                        "type" => Type::float(),
                        "description" => "Итого"
                    ],
                    "discount" => [
                        "type" => Type::int(),
                        "description" => "Скидка"
                    ],
                    "time_discount" => [
                        "type" => Type::int(),
                        "description" => "Скидка по времени"
                    ],
                    "type" => [
                        "type" => Type::string(),
                        "resolve" => function ($root, $args) {
                            return isset($root['promotion_name']) ? "Акция" : "Товар";
                        }
                    ]


                ];
            }
        ];
        parent::__construct($config);
    }
}