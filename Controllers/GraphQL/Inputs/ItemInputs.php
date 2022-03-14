<?php

namespace Controllers\GraphQL\Inputs;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class ItemInputs
{


    public static function ItemSort()
    {

        return new InputObjectType([
            'name' => 'ItemSort',
            'description' => 'Сортировка ингридиентов',
            'fields' => [
                'field' => [
                    'type' => Type::nonNull(new EnumType([
                        'name' => 'FieldItemType',
                        'description' => 'Поля',
                        'values' => ['name', 'category']
                    ]))
                ],
                'order' => [
                    'type' => Type::nonNull(Types::SortOrderType())
                ]


            ]
        ]);
    }







    public static function ItemInput()
    {
        return new InputObjectType([
            'name' => 'ItemInput',
            'fields' => [
                'name' => [
                    "type" => Type::nonNull(Type::string()),
                    "description" => "Название ингридиента"
                ],
                "category_id" => [
                    "type" => Type::nonNull(Type::int()),
                    "description" => "ID категории"
                ],
                "untils" => [
                    "type" => Type::string(),
                    "description" => "Еденицы измерения (шт,л,кг)"
                ],
                'conversion_item_id' => [
                    "type" => Type::int(),
                    "description" => "ID ингридиента для ввтоматического преобразовать при перемещении"
                ],
                'round' => [
                    "type" => Type::boolean(),
                    'description' => "Списание при производстве, только для штучных ингридиентов"
                ],
                'bulk' => [
                    "type" => Type::int(),
                    "description" => "Вес в граммах, только для штучных ингридиентов"
                ]

            ]
        ]);
    }
}