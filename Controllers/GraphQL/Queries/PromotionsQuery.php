<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class PromotionsQuery
{

    public static function get()
    {
        return  [
            'type' =>  Type::listOf(Types::Promotion()),
            'description' => 'Акции',
            "args" => [
                'partner' => ["type" => Type::int(),  'description' => "ID партнера для получения его акций, только для админа"],
            ],
            'resolve' => function ($root, $args) {
                if (!Auth::$user['id']) throw new Error("Приватный метод");

                return DB::makeArray(DB::query("SELECT * FROM app_promotions WHERE partner = " . (isset($args['partner']) ? $args['partner'] : Auth::$user['id'])));
            }

        ];
    }
}