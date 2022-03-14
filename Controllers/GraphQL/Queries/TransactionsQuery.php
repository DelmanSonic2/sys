<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class TransactionsQuery
{

    public static function get()
    {
        return  [
            'type' =>  Types::TransactionsData(),
            'description' => 'Чеки/Транзакции',
            'args' => [
                'from' => [
                    "type" => Type::int(),
                    'defaultValue' => strtotime(date('Y-m-d 00:00:00')),
                    'description' => "UNIX дата от"
                ],
                'to' => [
                    "type" => Type::int(),
                    'defaultValue' => strtotime(date('Y-m-d 23:59:59')),
                    'description' => "UNIX дата до"
                ],
                'limit' => ["type" => Type::int(), 'defaultValue' => 10],
                'offset' => ["type" => Type::int(), 'defaultValue' => 0],
                'all' => ["type" => Type::boolean(), "defaultValue" => false, "description" => "Вернуть все без пагинации"],
                'point' => ["type" => Type::int(), 'description' => "ID заведения"],
                'employee' => ["type" => Type::int(), 'description' => "ID сотрудника"],
                'product' => ["type" => Type::int(), 'description' => "ID продукта"],
                "promotion" => ["type" => Type::int(), 'description' => "ID акции"],
                'partner' => ["type" => Type::int(), 'description' => "ID партнера (для функционала админа)"],
                "sort" => ["type" => new InputObjectType([
                    'name' => 'TransactionsSort',
                    'description' => 'Сортировка ингридиентов',
                    'fields' => [
                        'field' => [
                            'type' => Type::nonNull(new EnumType([
                                'name' => 'FieldTransactionType',
                                'description' => 'Поля',
                                'values' => ['created', 'sum', 'total', 'profit']
                            ]))
                        ],
                        'order' => [
                            'type' => Type::nonNull(Types::SortOrderType())
                        ]


                    ]
                ])]
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");



                $to =  strtotime(date('Y-m-d 00:00:00', $args['to'])) + (24 * 60 * 60);
                $from =   strtotime(date('Y-m-d 00:00:00', $args['from']));



                $filter = [
                    "country" => Auth::$country,
                    "partner" => isset($args['partner']) ? $args['partner'] : (int)Auth::$user["id"],
                    'created' => [
                        '$lte' => $to, '$gte' => $from
                    ]
                ];

                if (isset($args['point'])) {
                    $filter['point'] = $args['point'];
                }

                if (isset($args['employee'])) {
                    $filter['employee'] =  ['$in' => [(int)$args['employee'], (string)$args['employee']]];
                }


                if (isset($args['product'])) {
                    $filter['items.product'] = $args['product'];
                }


                if (isset($args['promotion'])) {
                    $filter['promotions.promotion'] = $args['promotion'];
                }

                $options = [
                    'sort' => [
                        "_id" => -1
                    ]
                ];

                if ($args['all'] == false) {
                    $options['limit'] = $args['limit'];
                    $options['skip'] = $args['offset'];
                }

                if (isset($args['sort'])) {
                    $options['sort'] = [
                        $args['sort']['field'] => ($args['sort']['order'] == 'ASC') ? 1 : -1
                    ];
                }

                $data =  mDB::collection("transactions")->find($filter, $options)->toArray();

                $total = mDB::collection("transactions")->count($filter);


                $total_data = mDB::collection("transactions")->aggregate([
                    [
                        '$match' => $filter
                    ],
                    [
                        '$group' => [
                            '_id' => '$country',
                            "sum" => [
                                '$sum' => '$sum'
                            ],
                            "total" => [
                                '$sum' => '$total'
                            ],
                            "profit" => [
                                '$sum' => '$profit'
                            ]
                        ]
                    ]
                ])->toArray();

                return [
                    'data' => $data,
                    'limit' => $args['limit'],
                    'offset' => $args['offset'],
                    'total' => $total,
                    'all_sum' => $total_data[0]['sum'],
                    'all_total' => $total_data[0]['total'],
                    'all_profit' => $total_data[0]['total'],
                ];
            }

        ];
    }
}