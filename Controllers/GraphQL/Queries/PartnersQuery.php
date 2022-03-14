<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class PartnersQuery
{

    public static function get()
    {
        return  [
            'type' =>  Type::listOf(Types::Partner()),
            'description' => 'Партнеры',
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");



                $data = DB::makeArray(
                    DB::query("SELECT * FROM app_partner ORDER BY name")
                );

                return $data;
            }

        ];
    }
}