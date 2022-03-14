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

class TechnicalCardsQuery
{

    public static function get()
    {
        return  [
            'type' =>  Types::TechnicalCardsData(),
            'description' => 'Тех. карты',
            'args' => [
                'id' => ["type" => Type::int(),  'description' => "ID тех. карты для получения одной тех. карты"],
                'limit' => ["type" => Type::int(), 'defaultValue' => 10],
                'offset' => ["type" => Type::int(), 'defaultValue' => 0],
                'all' => ["type" => Type::boolean(), 'defaultValue' => false, 'description' => "Вернуть все без пагинации"],
                'search' => ["type" => Type::string()],
                'archive' => ["type" => Type::boolean(), "defaultValue" => false, 'description' => "Показать архивные тех. карты"],
                'categories' => ["type" => Type::listOf(Type::int()),  'description' => "Фильтр по категориям товаров"],
                'sort' => ["type" => Types::TechnicalCardSort()]
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                if (isset($args['id'])) {

                    $filters = [
                        'id' => $args['id'],
                        'country' => Auth::$country,
                        '$or' => [
                            [
                                'enableForAll' => true,
                            ],
                            [
                                'partner' => (int)Auth::$user['id']
                            ]
                        ]
                    ];

                    $data =  mDB::collection("technical_cards")->findOne($filters); // DB::makeArray(DB::query("SELECT * FROM app_technical_card WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) AND id={$args['id']}"));

                    return [
                        'data' => [$data],
                        'limit' => null,
                        'offset' => null,
                        'total' => null
                    ];
                } else {


                    ini_set('memory_limit', '1024M');

                    $filters = [
                        'country' => Auth::$country,
                        '$or' => [
                            [
                                'enableForAll' => true,
                            ],
                            [
                                'partner' => (int)Auth::$user['id']
                            ]
                        ]
                    ];
                    $options = [];


                    if ($args['archive']) {
                        $filters['archive'] = ['$in' => [(int)Auth::$user['id']]];
                    } else {
                        $filters['archive'] = ['$nin' => [(int)Auth::$user['id']]];
                    }

                    if (isset($args['categories']) && COUNT($args['categories']) > 0) {

                        $filters['product.category'] = ['$in' => $args['categories']];
                    }

                    if (isset($args['search']) && strlen($args['search']) > 0) {


                        $filters['product.name'] = ['$regex' => $args['search'], '$options' => 'i'];
                    }




                    if (isset($args['sort'])) {
                        $options['$sort'] = [
                            $args['sort']['field'] => ($args['sort']['order'] == 'asc' ? 1 : -1)
                        ];
                    }

                    if ($args['all'] == false) {
                        if ($args['offset'] > 0)
                            $options['skip'] = $args['offset'];
                        $options['limit'] = $args['limit'];
                    }


                    $data =  mDB::collection("technical_cards")->find($filters, $options)->toArray();

                    $total = mDB::collection("technical_cards")->count($filters);


                    return [
                        'data' => $data,
                        'limit' => $args['limit'],
                        'offset' => $args['offset'],
                        'total' => $total
                    ];
                }
            }

        ];
    }
}