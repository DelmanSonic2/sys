<?php

namespace Controllers\GraphQL\Inputs;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class ProductInputs
{


    public static function ProductSort()
    {

        return new InputObjectType([
            'name' => 'ProductSortInputs',
            'description' => 'Сортировка продуктов',
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




    public static function  ProductIngredient()
    {

        return new InputObjectType([
            'name' => 'ProductIngredient',
            'description' => 'Ингридиент для товара (только при создании). Принает пару (item_id+price) или (price+category_id), во втором случае ингридиент создаеться автоматически',
            'fields' => [
                'item_id' => [
                    'type' => Type::int(),
                    'description' => 'ID ингридиента',
                ],
                'price' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'Цена для тех карты',
                ],

                'category_id' =>  [
                    'type' => Type::int(),
                    'description' => 'ID категории ингридинета',
                ]

            ]
        ]);
    }



    public static function  ProductPointInput()
    {

        return new InputObjectType([
            'name' => 'ProductPointInputs',
            'description' => 'Доступность товара на точке',
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


    public static function ProductInput()
    {
        return new InputObjectType([
            'name' => 'ProductInput',
            'fields' => [
                'name' => [
                    "type" => Type::nonNull(Type::string()),
                    "description" => "Название товара"
                ],
                "category_id" => [
                    "type" => Type::nonNull(Type::int()),
                    "description" => "ID категории"
                ],
                'product_ingredient' => [
                    "type" => Types::ProductIngredient(),
                    "description" => "Автоматическое создание тех карты и ингридиента"
                ],
                "image" => [
                    "type" => Type::string(),
                    "description" => "Ссылка на фото продукта"
                ],
                "color_id" => [
                    "type" => Type::int(),
                    "defaultValue" => 1,
                    "description" => "ID цвета",
                ],
                'points' => [
                    "type" => Type::listOf(Types::ProductPointInput()),
                    "description" => "Доступность на точках",
                ]

            ]
        ]);
    }
}