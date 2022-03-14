<?php

namespace  Controllers\GraphQL\Mutations;

use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;



class AutoPriceMutation
{

    public static function add()
    {
        return   [
            'type' => Type::boolean(),
            'description' => 'Добавить/обновить автоизменения цен',
            'args' => [
                '_id' => [
                    "type" => Type::string(),
                    'description' => '_id  для обновления, при создании передавать не надо',
                ],
                'date' => [
                    "type" => Type::nonNull(Type::int()),
                    "description" => "Дата изменения"
                ],
                'comment' => [
                    "type" => Type::string(),
                    "description" => "Комментарий"
                ],

                "prices" => [
                    "type" => Type::nonNull(Type::listOf(Types::AutoPriceItemInput())),
                    "description" => "Список цен"
                ]
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");


                if (isset($args['_id'])) {

                    $fields = [
                        "date" => $args['date'],
                        "prices" => $args['prices'],
                    ];

                    if (isset($args['comment'])) {
                        $fields['comment'] = $args['comment'];
                    }


                    mDB::collection("auto_prices")->updateOne([
                        "partner" => (int)Auth::$user['id'],
                        "_id" => mDB::id($args['_id']),
                        "country" => Auth::$country,
                    ], ['$set' => $fields]);

                    return true;
                } else {

                    $resultInsert = mDB::collection("auto_prices")->insertOne([
                        "date" => $args['date'],
                        "partner" => (int)Auth::$user['id'],
                        "country" => Auth::$country,
                        "prices" => $args['prices'],
                        "comment" => $args['comment'] ?? null,
                        "creator" => Auth::$user['name'],
                        "created_at" => time()
                    ]);

                    return true;
                }
            }
        ];
    }
}