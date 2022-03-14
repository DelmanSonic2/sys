<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class EmployeesQuery
{

    public static function get()
    {
        return  [
            'type' =>  Type::listOf(Types::Employee()),
            "args" => [
                'partner' => ["type" => Type::int(),  'description' => "ID партнера для получения его сотрудников, только для админа"],
            ],
            'description' => 'Сотрудники',
            'resolve' => function ($root, $args) {
                if (!Auth::$user['id']) throw new Error("Приватный метод");

                $partner = isset($args['partner']) ? $args['partner'] : Auth::$user['id'];
                return DB::makeArray(DB::query("SELECT * FROM app_employees WHERE partner = $partner AND deleted = 0"));
            }

        ];
    }
}