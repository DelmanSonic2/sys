<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class ProductsQuery
{

    public static function get()
    {
        return  [
            'type' =>  Types::ProductsData(),
            'description' => 'Товары',
            'args' => [
                'id' => ["type" => Type::int(),  'description' => "ID товара для получения одного товара"],
                'limit' => ["type" => Type::int(), 'defaultValue' => 10],
                'all' => ["type" => Type::boolean(), 'defaultValue' => false, 'description' => "Вернуть все без пагинации"],
                'offset' => ["type" => Type::int(), 'defaultValue' => 0],
                'archive' => ["type" => Type::boolean(), "defaultValue" => false, 'description' => "Показать архивные"],
                'categories' => ["type" => Type::listOf(Type::int()),  'description' => "Фильтр по категориям"],
                'search' => ["type" => Type::string()],
                'sort' => ["type" => Types::ProductSort()]

            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                if (isset($args['id'])) {
                    $data = DB::makeArray(DB::query("SELECT * FROM app_products WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) AND id={$args['id']}"));

                    return [
                        'data' => $data,
                        'limit' => null,
                        'offset' => null,
                        'total' => null
                    ];
                } else {


                    $where_category = "";
                    if (isset($args['categories']) && COUNT($args['categories']) > 0) {

                        $where_category = " AND category IN (" . implode(',', $args['categories']) . ")";
                    }

                    $where_search = "";
                    if (isset($args['search']) && strlen($args['search']) > 0) {
                        $where_search = " AND  name LIKE '%" . $args['search'] . "%'";
                    }

                    $order_by = "";
                    if (isset($args['sort'])) {

                        $order_by = " ORDER BY " . $args['sort']['field'] . " " . $args['sort']['order'] . " ";
                    }

                    $data = DB::makeArray(
                        DB::query("SELECT *, " . ($args['archive'] ? 1 : 0) . " as archive FROM app_products WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0)  AND id " . ($args['archive'] ? '' : 'NOT') . " IN (
                        SELECT product_id
                        FROM app_archive
                        WHERE model = 'product' AND partner_id = " . Auth::$user['id'] . "
                    )" . $where_category . $where_search . $order_by . ($args['all'] == true ? "" : " LIMIT  " . $args['offset'] . "," . $args['limit']))
                    );


                    $total = DB::getRow(DB::query("SELECT COUNT(id) as total FROM app_products WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0)  AND id " . ($args['archive'] ? '' : 'NOT') . " IN (
                        SELECT product_id
                        FROM app_archive
                        WHERE model = 'product' AND partner_id = " . Auth::$user['id'] . "
                    )" . $where_category . $where_search))['total'];

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