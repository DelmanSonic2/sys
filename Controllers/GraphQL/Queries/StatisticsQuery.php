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

class StatisticsQuery
{

    public static function get()
    {
        return  [
            'type' => Types::StatisticsData(),
            'description' => 'Статистика на сегодня',
            'args' => [
                'partner' => ["type" => Type::int(),  'description' => "ID партнера"],
                'points' => ["type" => Type::listOf(Type::int()),  'description' => "массив ID точек"],
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");


                $today = strtotime(date('d-m-Y 00:00:00', time()));

                $match = [
                    "country" => Auth::$country,
                    "created" => [
                        '$gte' => $today
                    ]
                ];


                if (isset($args['points']) && count($args['points']) > 0) {

                    $ids = [];
                    foreach ($args['points'] as $point) {
                        $ids[] = $point;
                    }
                    $match['point'] =  ['$in' => $ids];
                } else if (isset($args['partner'])) {
                    $match['partner'] = $args['partner'];
                } else {
                    $match['partner'] = (int)Auth::$user['id'];
                }

                $result =  mDB::collection('transactions')->aggregate([

                    [
                        '$match' => $match
                    ],
                    [
                        '$group' => [
                            '_id' => [],
                            "checks" => [
                                '$sum' => 1
                            ],
                            'profit' => [
                                '$sum' => '$profit'
                            ],
                            'sales' => [
                                '$sum' => '$total'
                            ],
                            'average_check' => [
                                '$avg' => '$total'
                            ]
                        ]
                    ]

                ])->toArray();


                return $result[0];
            }
        ];
    }
}