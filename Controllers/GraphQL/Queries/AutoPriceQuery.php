<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class AutoPriceQuery
{

    public static function get()
    {
        return  [
            'type' =>  Type::listOf(Types::AutoPrice()),
            'description' => 'Получить автоизменения цен',
            "args" => [
                '_id' => ["type" => Type::string(),  'description' => "ID для получения одной записи"],
            ],
            'resolve' => function ($root, $args) {

                $filter = [
                    "partner" => (int)Auth::$user['id'],
                    "country" => Auth::$country
                ];

                if (isset($args['_id'])) {
                    $filter['_id'] = mDB::id($args['_id']);
                }

                $data =  mDB::collection("auto_prices")->find($filter)->toArray();



                return $data;
            }


        ];
    }
}