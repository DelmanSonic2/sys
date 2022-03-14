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

class EmployeeType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Сотрудник',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    "name" => [
                        "type" => Type::string(),
                        "description" => "Имя"
                    ]
                ];
            }
        ];
        parent::__construct($config);
    }
}