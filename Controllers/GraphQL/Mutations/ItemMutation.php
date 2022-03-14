<?php

namespace  Controllers\GraphQL\Mutations;

use Controllers\GraphQL\Types;
use Controllers\Models\ItemModel;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

use Support\Auth;
use Support\DB;
use Support\EditingAllowed;
use Support\mDB;





class ItemMutation
{



    public static function add()
    {
        return   [
            'type' => Types::Item(),
            'description' => 'Добавить/обновить ',
            'args' => [
                'id' => [
                    "type" => Type::int(),
                    'description' => 'ID ингридиента для обновления, при создании передавать не надо',
                ],
                'item' => [
                    "type" => Type::nonNull(Types::ItemInput()),
                    'description' => 'Ингридиент',
                ],
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                $item = $args['item'];

                if (!isset($args['id'])) {

                    $item_exist = DB::query('
        SELECT i.id, a.id AS arch
        FROM ' . DB_ITEMS . ' i
        LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = i.id AND a.model = "item" AND a.partner_id = ' . Auth::$user['id'] . '
        WHERE i.name = "' . $args['name'] . '" AND (i.partner = ' . Auth::$user['id'] . ' OR i.partner IS NULL)
    ');

                    if (DB::getRecordCount($item_exist) > 0) {
                        $arch = DB::getRow($item_exist)['arch'];
                        if ($arch == null) throw new Error("Такой ингредиент уже существует");
                        else throw new Error("Ингредиент находится в архиве, восстановите его.");
                    }


                    $fields = array(
                        'name' => $item['name'],
                        'partner' => Auth::$user['id'],
                        'category' => $item['category_id'],
                        'untils' => $item['untils'],
                        'round' =>  isset($item['round']) ?  ($item['round'] ? 1 : 0) : 0
                    );

                    if (isset($item['conversion_item_id'])) {

                        $convertion_item = DB::select('id, untils', DB_ITEMS, "id = " . $item['conversion_item_id'], '', 1);
                        if (!DB::getRecordCount($convertion_item)) throw new Error("Ингредиент не найден.");

                        $convertion_item = DB::getRow($convertion_item);

                        if ($convertion_item['untils'] != $item['untils']) throw new Error("Единицы измерения ингредиента должны совпадать с единицами измерения конвертируемого ингредиента.");

                        $fields['conversion_item_id'] = $item['conversion_item_id'];
                    }


                    $fields['bulk'] = isset($item['bulk']) ? $item['bulk'] / 1000 : 1;

                    $id = DB::insert($fields, DB_ITEMS);

                    $fields['id'] = $id;
                    //  ItemModel::add($fields);

                    return DB::getRow(DB::select("*", "app_items", "id=" . $id, "", "1"));
                } else {

                    $itemData = DB::select('*', DB_ITEMS, 'id = ' . $args['id'] . ' AND (partner = ' . Auth::$user['id'] . ' OR partner IS NULL)');

                    if (DB::getRecordCount($itemData) == 0)  throw new Error("Такого ингредиента не существует.");

                    $itemData = DB::getRow($itemData);


                    if (!EditingAllowed::Item($itemData)) throw new Error("Вы не можете редактировать общедоступный ингредиент");

                    $fields = [];

                    $item['name'] = trim($item['name']);

                    $item_exist = DB::query('
                    SELECT i.id, a.id AS arch
                    FROM ' . DB_ITEMS . ' i
                    LEFT JOIN ' . DB_ARCHIVE . ' a ON a.product_id = i.id AND a.model = "item" AND a.partner_id = ' . Auth::$user['id'] . '
                    WHERE i.id != ' . $args['id'] . ' AND i.name = "' . $item['name'] . '" AND (i.partner = ' . Auth::$user['id'] . ' OR i.partner IS NULL)
                ');

                    if (DB::getRecordCount($item_exist) > 0) {
                        $arch = DB::getRow($item_exist)['arch'];
                        if ($arch == null) new Error("Такой ингредиент уже существует");
                        else new Error('Ингредиент находится в архиве, восстановите его.');
                    }



                    $fields = array(
                        'name' => $item['name'],
                        'category' => $item['category_id'],
                        'untils' => $item['untils'],
                        'round' =>  isset($item['round']) ?  ($item['round'] ? 1 : 0) : 0
                    );

                    if (isset($item['conversion_item_id'])) {

                        $convertion_item = DB::select('id, untils', DB_ITEMS, "id = " . $item['conversion_item_id'], '', 1);
                        if (!DB::getRecordCount($convertion_item)) throw new Error("Ингредиент не найден.");

                        $convertion_item = DB::getRow($convertion_item);

                        if ($convertion_item['untils'] != $item['untils']) throw new Error("Единицы измерения ингредиента должны совпадать с единицами измерения конвертируемого ингредиента.");

                        $fields['conversion_item_id'] = $item['conversion_item_id'];
                    }


                    //$fields['bulk'] = isset($item['bulk']) ? $item['bulk'] / 1000 : 1;

                    $id = DB::update($fields, DB_ITEMS, "id=" . $args['id']);

                    //   ItemModel::update($fields, $args['id']);

                    /*    if ($fields['bulk'] != $itemData['bulk']) {
                        $class = new ProductionsParent(false);
                        $itemData['new_bulk'] = $fields['bulk'];
                        $class->update($itemData);
                    }
                    */

                    return DB::getRow(DB::select("*", "app_items", "id=" . $args['id'], "", "1"));
                }
            }
        ];
    }
}