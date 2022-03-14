<?php

namespace  Controllers\GraphQL\Mutations;

use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class ArchiveMutation
{

    public static function archive()
    {
        return   [
            'type' => Types::StatusData(),
            'description' => 'Архивация',
            'args' => [
                'id' => [
                    "type" => Type::nonNull(Type::int()),
                    'description' => 'ID объекта',
                ],
                'model' => [
                    "type" => Type::nonNull(Types::ModelType()),
                    'description' => 'Тип объекта',
                ],
                'action' => [
                    "type" => Type::nonNull(Types::ActionArchiveType()),
                    'description' => 'Действие',
                ],
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                if ($args['action'] == 'add') {

                    switch ($args['model']) {

                        case 'item':

                            $check = DB::query('SELECT id
                                            FROM ' . DB_ARCHIVE . '
                                            WHERE product_id = ' . $args['id'] . ' AND partner_id = ' . Auth::$user['id'] . ' AND model = "item"');

                            if (DB::getRecordCount($check) != 0)
                                throw new Error("Вы уже добавили данный ингредиент в архив.");

                            $check_add = DB::query('SELECT id
                                            FROM ' . DB_ITEMS . '
                                            WHERE id = ' . $args['id'] . ' AND (partner IS NULL OR partner = ' . Auth::$user['id'] . ')');

                            if (DB::getRecordCount($check_add) == 0)
                                throw new Error("Недостаточно прав для совершения действия.");

                            $insert = array(
                                'partner_id' => Auth::$user['id'],
                                'product_id' => $args['id'],
                                'model' => "item",
                                'created' => time()
                            );

                            DB::insert($insert, DB_ARCHIVE);

                            return [
                                'success' => true
                            ];


                            break;

                        case 'product':

                            $check = DB::query('SELECT id
                                            FROM ' . DB_ARCHIVE . '
                                            WHERE product_id = ' . $args['id'] . ' AND partner_id = ' . Auth::$user['id'] . ' AND model = "product"');

                            if (DB::getRecordCount($check) != 0)
                                throw new Error("Вы уже добавили данный товар в архив.", 1);

                            $check_add = DB::query('SELECT id
                                            FROM ' . DB_PRODUCTS . '
                                            WHERE id = ' . $args['id'] . ' AND (partner IS NULL OR partner = ' . Auth::$user['id'] . ')');

                            if (DB::getRecordCount($check_add) == 0)
                                throw new Error("Недостаточно прав для совершения действия.", 1);

                            $insert = '("' . Auth::$user['id'] . '", "' . $args['id'] . '", "product", "' . time() . '")';

                            $tech_cards = DB::query('SELECT id
                                                FROM ' . DB_TECHNICAL_CARD . '
                                                WHERE product = ' . $args['id'] . '  AND (partner IS NULL OR partner = ' . Auth::$user['id'] . ')');

                            $technical_cards_ids = [];
                            while ($row = DB::getRow($tech_cards)) {
                                $technical_cards_ids[] = (int)$row['id'];

                                $insert .= ', ("' . Auth::$user['id'] . '", "' . $row['id'] . '", "technical_card", "' . time() . '")';
                            }

                            mDB::collection("technical_cards")->updateMany([
                                "id" => ['$in' => $technical_cards_ids],
                                "country" => Auth::$country
                            ], [
                                '$push' => ['archive' => (int)Auth::$user['id']]
                            ]);

                            DB::query('INSERT INTO ' . DB_ARCHIVE . ' (partner_id, product_id, model, created) VALUES ' . $insert);

                            return [
                                'success' => true
                            ];

                            break;

                        case 'technical_card':

                            $check = DB::query('SELECT id
                                            FROM ' . DB_ARCHIVE . '
                                            WHERE product_id = ' . $args['id'] . ' AND partner_id = ' . Auth::$user['id'] . ' AND model = "technical_card"');

                            if (DB::getRecordCount($check) == 0) {


                                $check_add = DB::query('SELECT id
                                            FROM ' . DB_TECHNICAL_CARD . '
                                            WHERE id = ' . $args['id'] . ' AND (partner IS NULL OR partner = ' . Auth::$user['id'] . ')');

                                if (DB::getRecordCount($check_add) == 0)
                                    throw new Error("Недостаточно прав для совершения действия.", 1);

                                $insert = array(
                                    'partner_id' => Auth::$user['id'],
                                    'product_id' => $args['id'],
                                    'model' => "technical_card",
                                    'created' => time()
                                );

                                DB::insert($insert, DB_ARCHIVE);
                            }
                            mDB::collection("technical_cards")->updateOne([
                                'id' => (int)$args['id'],
                                'country' => Auth::$country
                            ], [
                                '$addToSet' => ['archive' => (int)Auth::$user['id']]
                            ]);

                            return [
                                'success' => true
                            ];

                            break;
                    }
                }

                if ($args['action'] == 'recovery') {



                    /*                    $data = DB::query('SELECT *
                                    FROM ' . DB_ARCHIVE . '
                                    WHERE partner_id = ' . Auth::$user['id'] . ' AND product_id = ' . $args['id'] . ' AND model = "' . $args['model'] . '"');

                    if (!DB::getRecordCount($data))
                        throw new Error('Запись не найдена.');*/

                    if ($args['model'] == 'product') {

                        DB::query('
                DELETE FROM ' . DB_ARCHIVE . '
                WHERE (model = "technical_card" AND  product_id IN (
                    SELECT id
                    FROM ' . DB_TECHNICAL_CARD . '
                    WHERE product = ' . $args['id'] . '
                )) OR (model = "item" AND product_id IN (
                    SELECT pc.item
                    FROM ' . DB_TECHNICAL_CARD . ' tc
                    JOIN ' . DB_PRODUCT_COMPOSITION . ' pc ON pc.technical_card = tc.id
                    WHERE tc.product = ' . $args['id'] . '
                ))
            ');
                    }
                    if ($args['model'] == 'technical_card') {

                        DB::query('
                DELETE FROM ' . DB_ARCHIVE . '
                WHERE (model = "product" AND  product_id IN (
                    SELECT product
                    FROM ' . DB_TECHNICAL_CARD . '
                    WHERE id = ' . $args['id'] . '
                )) OR (model = "item" AND product_id IN (
                    SELECT item
                    FROM ' . DB_PRODUCT_COMPOSITION . '
                    WHERE technical_card = ' . $args['id'] . '
                ))
            ');

                        mDB::collection("technical_cards")->updateOne([
                            'id' => (int)$args['id'],
                            'country' => Auth::$country
                        ], [
                            '$pull' => ['archive' => (int)Auth::$user['id']]
                        ]);
                    }

                    if ($args['model'] == 'item') {
                        //   throw new Error('Времено не работает');
                        /*
                        $product = ['id' => $args['id']];
                        $pc = new ProductionCostPrice(false, Auth::$user['id']);
                        $items = $pc->num_array_children($product);
                        if (sizeof($items)) {
                            $items = implode(',', $items);
                            DB::delete(DB_ARCHIVE, 'model = "' . $args['model'] . '" AND product_id IN (' . $items . ')');
                        }*/
                    }

                    DB::delete(DB_ARCHIVE, 'partner_id = ' . Auth::$user['id'] . ' AND product_id = ' . $args['id'] . ' AND model = "' . $args['model'] . '"');

                    return [
                        'success' => true
                    ];
                }
            }
        ];
    }
}