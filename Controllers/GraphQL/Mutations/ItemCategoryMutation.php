<?php

namespace  Controllers\GraphQL\Mutations;

use Controllers\GraphQL\Types;
use Controllers\Models\ItemModel;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

use Support\Auth;
use Support\DB;
use Support\mDB;





class ItemCategoryMutation
{



    public static function add()
    {
        return   [
            'type' => Type::boolean(),
            'description' => 'Добавить/обновить ',
            'args' => [
                'id' => [
                    "type" => Type::int(),
                    'description' => 'ID категории для обновления, при создании передавать не надо',
                ],
                'name' => [
                    "type" => Type::nonNull(Type::string()),
                    'description' => 'Название категории',
                ],
            ],
            'resolve' => function ($root, $args) {

                if (!Auth::$user['id']) throw new Error("Приватный метод");

                $name = trim($args['name']);

                if (!isset($args['id'])) {
                    $category = DB::select('id, partner', DB_ITEMS_CATEGORY, 'name = "' . $name . '"');

                    if (DB::getRecordCount($category) != 0) {

                        $category = DB::getRow($category);

                        if ($category['partner'] == null)
                            throw new Error("Такая категория уже существует в общем списке категорий.");

                        if ($category['partner'] == Auth::$user['id'])
                            throw new Error("Такая категория уже существует в Вашем списке.");
                    }

                    $fields = array(
                        'name' => $name,
                        'partner' => Auth::$user['id']
                    );

                    if (DB::insert($fields, DB_ITEMS_CATEGORY))
                        return true;
                    else
                        throw new Error("Ошибка при создании категории.");
                } else {



                    $categoryData = DB::select('*', DB_ITEMS_CATEGORY, 'id = ' . $args['id'] . ' AND (partner = ' . Auth::$user['id'] . ' OR partner IS NULL)');

                    if (DB::getRecordCount($categoryData) == 0)
                        throw new Error("Такой категории не существует.");

                    $categoryData = DB::getRow($categoryData);

                    $editing_allowed = ($categoryData['partner'] == null) ? false : true;

                    if (!$editing_allowed)
                        throw new Error("Вы не можете редактировать общую категорию.");



                    $categorySecond = DB::select('id, partner', DB_ITEMS_CATEGORY, 'name = "' . $name . '" AND id != ' . $args['id']);

                    if (DB::getRecordCount($categorySecond) != 0) {

                        $categorySecond = DB::getRow($categorySecond);

                        if ($categorySecond['partner'] == null)
                            throw new Error("Такая категория уже существует в общем списке категорий.");

                        if ($categorySecond['partner'] == Auth::$user['id'])
                            throw new Error("Такая категория уже существует в Вашем списке.");
                    }

                    if (DB::update(array('name' => $name), DB_ITEMS_CATEGORY, 'id = ' . $args['id']))
                        return true;
                    else
                        throw new Error("Ошибка при создании категории.");
                }
            }
        ];
    }
}