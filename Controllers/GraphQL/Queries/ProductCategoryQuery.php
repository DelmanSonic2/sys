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

class ProductCategoryQuery
{

    public static function get()
    {
        return  [
            'type' =>  Types::ProductCategoriesData(),
            'description' => 'Категории товаров',
            'args' => [
                'id' => ["type" => Type::int(),  'description' => "ID категории товарв для получения одной категории"],
                'limit' => ["type" => Type::int(), 'defaultValue' => 10],
                'all' => ["type" => Type::boolean(), 'defaultValue' => false, 'description' => "Вернуть все без пагинации"],
                'offset' => ["type" => Type::int(), 'defaultValue' => 0],
                'search' => ["type" => Type::string()],

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

                    $data =  mDB::collection("product_categories")->findOne($filters);

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




                    if (isset($args['search']) && strlen($args['search']) > 0) {
                        $filters['name'] = ['$regex' => $args['search'], '$options' => 'i'];
                    }



                    if ($args['all'] == false) {
                        if ($args['offset'] > 0)
                            $options['skip'] = $args['offset'];
                        $options['limit'] = $args['limit'];
                    }


                    $data =  mDB::collection("product_categories")->find($filters, $options)->toArray();

                    $total = mDB::collection("product_categories")->count($filters);


                    return [
                        'data' => $data,
                        'limit' => $args['all'] ? null : $args['limit'],
                        'offset' => $args['all'] ? null :  $args['offset'],
                        'total' => $total
                    ];
                }
                /*                if (isset($args['id'])) {
                    $data = DB::makeArray(DB::query("SELECT * FROM app_product_categories WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) AND id={$args['id']}"));

                    return [
                        'data' => $data,
                        'limit' => null,
                        'offset' => null,
                        'total' => null
                    ];
                } else {



                    $where_search = "";
                    if (isset($args['search']) && strlen($args['search']) > 0) {
                        $where_search = " AND  name LIKE '%" . $args['search'] . "%'";
                    }


                    $data = DB::makeArray(
                        DB::query("SELECT * FROM app_product_categories WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) "
                            . $where_search . ($args['all'] == true ? " ORDER BY name " : " LIMIT  " . $args['offset'] . "," . $args['limit']))
                    );


                    $total = DB::getRow(DB::query("SELECT COUNT(id) as total FROM app_product_categories WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) "
                        . $where_search))['total'];

                    return [
                        'data' => $data,
                        'limit' => $args['all'] ? null : $args['limit'],
                        'offset' => $args['all'] ? null :  $args['offset'],
                        'total' => $total
                    ];
                }*/
            }

        ];
    }
}