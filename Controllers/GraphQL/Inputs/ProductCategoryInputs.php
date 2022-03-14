<?php

namespace Controllers\GraphQL\Inputs;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class ProductCategoryInputs
{


    public static function ProductCategorySort()
    {

        return new InputObjectType([
            'name' => 'ProductCategorySortInput',
            'description' => 'Сортировка категорий',
            'fields' => [
                'field' => [
                    'type' => Type::nonNull(new EnumType([
                        'name' => 'FieldProductType',
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








    public static function  ProductCategoryPointInput()
    {

        return new InputObjectType([
            'name' => 'ProductCategoryPointInput',
            'description' => 'Доступность категории на точке',
            'fields' => [
                'point_id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'ID точка',
                ],

                'enable' =>  [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'Товар доступен на точке',
                ]

            ]
        ]);
    }


    public static function ProductCategoryInput()
    {
        return new InputObjectType([
            'name' => 'ProductCategoryInput',
            'fields' => [
                'name' => [
                    "type" => Type::nonNull(Type::string()),
                    "description" => "Название товара"
                ],
                "parent_id" => [
                    "type" => Type::int(),
                    "description" => "ID родительской категории"
                ],
                "image" => [
                    "type" => Type::string(),
                    "description" => "Ссылка на фото категории"
                ],
                "color_id" => [
                    "type" => Type::int(),
                    "defaultValue" => 1,
                    "description" => "ID цвета",
                ],
                'points' => [
                    "type" => Type::listOf(Types::ProductCategoryPointInput()),
                    "description" => "Доступность на точках",
                ]

            ]
        ]);
    }
}