<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class PointsQuery
{

    public static function get()
    {
        return  [
            'type' =>  Types::PointsData(),
            'description' => 'Точки',
            'args' => [
                'id' => ["type" => Type::int(),  'description' => "ID ингридиента для получения одной записи"],
                'partner' => ["type" => Type::int(),  'description' => "ID партнера"],
                'limit' => ["type" => Type::int(), 'defaultValue' => 100],
                'all' => ["type" => Type::boolean(), 'defaultValue' => false, 'description' => "Вернуть все без пагинации"],
                'offset' => ["type" => Type::int(), 'defaultValue' => 0],

            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");





                if (isset($args['id'])) {
                    $data = DB::makeArray(DB::query("SELECT * FROM app_partner_points WHERE (partner=" . Auth::$user['id'] . ") AND id={$args['id']}"));

                    return [
                        'data' => $data,
                        'limit' => null,
                        'offset' => null,
                        'total' => null
                    ];
                } else {



                    $data = DB::makeArray(
                        DB::query("SELECT * FROM app_partner_points WHERE (partner=" . (isset($args['partner']) ? $args['partner'] : Auth::$user['id']) . ")" .  ($args['all'] == true ? "  ORDER BY name " : " LIMIT  " . $args['offset'] . "," . $args['limit']))
                    );


                    $total = DB::getRow(DB::query("SELECT COUNT(id) as total FROM app_partner_points WHERE (partner=" . Auth::$user['id'] . ")"))['total'];

                    return [
                        'data' => $data,
                        'limit' => $args['all'] ? null : $args['limit'],
                        'offset' => $args['all'] ? null :  $args['offset'],
                        'total' => $total
                    ];
                }
            }

        ];
    }
}