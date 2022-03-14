<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class ItemCategoryQuery
{

    public static function get()
    {
        return  [
            'type' =>  Types::ItemCategoriesData(),
            'description' => 'Категории ингридиентов',
            'args' => [
                'id' => ["type" => Type::int(),  'description' => "ID категории для получения одной категории"],
                'limit' => ["type" => Type::int(), 'defaultValue' => 10],
                'all' => ["type" => Type::boolean(), 'defaultValue' => false, 'description' => "Вернуть все без пагинации"],
                'offset' => ["type" => Type::int(), 'defaultValue' => 0],
                'search' => ["type" => Type::string()],

            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                if (isset($args['id'])) {
                    $data = DB::makeArray(DB::query("SELECT * FROM app_items_category WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) AND id={$args['id']}"));

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
                        DB::query("SELECT * FROM app_items_category WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) "
                            . $where_search . ($args['all'] == true ? " ORDER BY name " : " LIMIT  " . $args['offset'] . "," . $args['limit']))
                    );


                    $total = DB::getRow(DB::query("SELECT COUNT(id) as total FROM app_items_category WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) "
                        . $where_search))['total'];

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