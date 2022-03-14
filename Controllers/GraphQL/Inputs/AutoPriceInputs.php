<?php

namespace Controllers\GraphQL\Inputs;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class AutoPriceInputs
{



    public static function AutoPriceItemInput()
    {
        return new InputObjectType([
            'name' => 'AutoPriceItemInput',
            'fields' => [
                "technical_card_id" => [
                    "type" => Type::nonNull(Type::int()),
                    "description" => "id тех карты"
                ],
                "point_id" => [
                    "type" => Type::nonNull(Type::int()),
                    "description" => "id заведения"
                ],
                "price" => [
                    "type" => Type::nonNull(Type::int()),
                    "description" => "Новая цена"
                ]
            ]
        ]);
    }




    public static function AutoPriceInput()
    {
        return new InputObjectType([
            'name' => 'AutoPriceInput',
            'fields' => [
                'date' => [
                    "type" => Type::nonNull(Type::int()),
                    "description" => "Дата изменения"
                ],

                "prices" => [
                    "type" => Type::nonNull(Type::listOf(Types::AutoPriceItemInput())),
                    "description" => "Список цен"
                ]

            ]
        ]);
    }
}