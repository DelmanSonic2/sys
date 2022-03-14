<?php

namespace Controllers\GraphQL\Queries;

use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;

class ItemsQuery
{

    public static function get()
    {
        return [
            'type' => Types::ItemsData(),
            'description' => 'Ингридиенты',
            'args' => [
                'id' => ["type" => Type::int(), 'description' => "ID ингридиента для получения одного ингридиента"],
                'limit' => ["type" => Type::int(), 'defaultValue' => 10],
                'type' => ['type' => Types::ItemType(), 'defaultValue' => 'all'],
                'all' => ["type" => Type::boolean(), 'defaultValue' => false, 'description' => "Вернуть все без пагинации"],
                'offset' => ["type" => Type::int(), 'defaultValue' => 0],
                'archive' => ["type" => Type::boolean(), "defaultValue" => false, 'description' => "Показать архивныеы"],
                'search' => ["type" => Type::string(), 'description' => "Поиск по названию ингридиента"],
                'categories' => ["type" => Type::listOf(Type::int()), 'description' => "Фильтр по категориям, массив id категории"],
                'untils' => ["type" => Type::listOf(Type::string()), 'description' => "Фильтр по еденицам измерения"],
                'sort' => ["type" => Types::ItemSort()],

            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) {
                    throw new Error("Приватный метод");
                }

                if (isset($args['id'])) {
                    $data = DB::makeArray(DB::query("SELECT * FROM app_items WHERE (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) AND id={$args['id']}"));

                    return [
                        'data' => $data,
                        'limit' => null,
                        'offset' => null,
                        'total' => null,
                    ];
                } else {

                    $where_category = "";
                    if (isset($args['categories']) && COUNT($args['categories']) > 0) {

                        $where_category = " AND category IN (" . implode(',', $args['categories']) . ")";
                    }

                    $where_untils = "";
                    if (isset($args['untils'])) {

                        $where_untils = " AND untils IN ('" . implode("','", $args['untils']) . "')";
                    }

                    $where_search = "";
                    if (isset($args['search'])) {
                        $where_search = " AND name LIKE '%" . $args['search'] . "%' ";
                    }

                    $where_type = "";
                    if ($args['type'] == 'production') {
                        $where_type = " AND production = 1 ";
                    }

                    if ($args['type'] == 'ingredient') {
                        $where_type = " AND production = 0 ";
                    }

                    $data = DB::makeArray(
                        DB::query("SELECT * ," . ($args['archive'] ? 1 : 0) . " as archive FROM app_items WHERE del=0 AND (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) " . $where_type . " AND id " . ($args['archive'] ? '' : 'NOT') . " IN (
                        SELECT product_id
                        FROM app_archive
                        WHERE model = 'item' AND partner_id = " . Auth::$user['id'] . "
                    )" . $where_category . $where_untils . $where_search . ($args['all'] == true ? "  ORDER BY name " : " LIMIT  " . $args['offset'] . "," . $args['limit']))
                    );

                    $total = DB::getRow(DB::query("SELECT COUNT(id) as total FROM app_items WHERE  del=0 AND (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0) " . $where_type . " AND id " . ($args['archive'] ? '' : 'NOT') . " IN (
                        SELECT product_id
                        FROM app_archive
                        WHERE model = 'item' AND partner_id = " . Auth::$user['id'] . "
                    )" . $where_category . $where_untils . $where_search))['total'];

                    return [
                        'data' => $data,
                        'limit' => $args['all'] ? null : $args['limit'],
                        'offset' => $args['all'] ? null : $args['offset'],
                        'total' => $total,
                    ];
                }
            },

        ];
    }
}