<?php

namespace Controllers\GraphQL\Inputs;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class TechnicalCardInputs
{

    public static function TechnicalCardSort()
    {
        return new InputObjectType([
            'name' => 'TechnicalCardSortInputs',
            'description' => 'Сортировка тех. карт',
            'fields' => [
                'field' => [
                    'type' => Type::nonNull(new EnumType([
                        'name' => 'FieldTechnicalCardType',
                        'description' => 'Поля',
                        'values' => ['name', 'category', 'weighted', 'price'],
                    ])),
                ],
                'order' => [
                    'type' => Type::nonNull(Types::SortOrderType()),
                ],
            ],
        ]);
    }

    public static function Composition()
    {
        return new InputObjectType([
            'name' => 'CompositionInput',
            'fields' => [
                'item_id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'ID ингредиент',
                ],
                /*   'untils' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'Единицы измерения'
                ],*/
                'count' => [
                    "type" => Type::nonNull(Type::float()),
                    "description" => "Кол-во",
                ],
                "gross" => [
                    "type" => Type::nonNull(Type::float()),
                    "description" => "Брутто",
                ],
                "net_mass" => [
                    "type" => Type::nonNull(Type::float()),
                    "description" => "Нетто",
                ],
                "mass_block" => [
                    "type" => Type::boolean(),
                    "description" => "true - cохраняется вводимое значение/ false - автоматический расчет",
                    "defaultValue" => false,
                ],
                "divide" => [
                    "type" => Type::boolean(),
                    "description" => "Разбирать при продаже ПФ, только для item c production - true",
                    "defaultValue" => false,
                ],

            ],
        ]);
    }

    public static function ProductPrice()
    {
        return new InputObjectType([
            'name' => 'ProductPriceInput',
            'fields' => [
                'point_id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'ID точки',
                ],
                'price' => [
                    'type' => Type::nonNull(Type::float()),
                    'description' => 'Цена',
                ],
                'hide' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'defaultValue' => false,
                    'description' => 'true - Скрыть, false - Нет',
                ],
            ],
        ]);
    }

    public static function TechnicalCard()
    {
        return new InputObjectType([
            'name' => 'TechnicalCardInput',
            'fields' => [
                'product_id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'ID продукта',
                ],
                'subname' => [
                    'type' => Type::string(),
                    'defaultValue' => "",
                    'description' => 'Название тех. карты',
                ],
                'name_price' => [
                    'type' => Type::string(),
                    'defaultValue' => "",
                    'description' => 'Название для ценника',
                ],
                'bulk_value' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'Объем/вес',
                ],
                'bulk_untils' => [
                    'type' => Type::nonNull(Type::string()), //TODO Enum
                    'description' => 'Единицы измерения',
                ],
                'price' => [
                    'type' => Type::float(),
                    'description' => 'Цена',
                ],
                'preparing_minutes' => [
                    'type' => Type::int(),
                    'description' => 'Минуты - Время приготовления',
                    'defaultValue' => 0,
                ],
                'preparing_seconds' => [
                    'type' => Type::int(),
                    'description' => 'Секунды - Время приготовления',
                    'defaultValue' => 0,
                ],
                'color_id' => [
                    'type' => Type::int(),
                    'description' => 'ID цвета',
                    'defaultValue' => 1,
                ],
                'different_price' => [
                    "type" => Type::boolean(),
                    "description" => "Разная цена в заведениях",
                    'defaultValue' => false,
                ],
                "not_promotion" => [
                    "type" => Type::boolean(),
                    "description" => "Не участвует в акциях",
                    "defaultValue" => false,
                ],
                "cooking_method" => [
                    "type" => Type::string(),
                    "description" => "Способ приготовления",
                ],
                "cashback_percent" => [
                    "type" => Type::int(),
                    "description" => "Процент кешбэка (если отличается от того что по карте)",
                    'defaultValue' => 0,
                ],
                "composition_description" => [
                    "type" => Type::string(),
                    "description" => "Описание состава",
                    'defaultValue' => "",
                ],
                "weighted" => [
                    "type" => Type::boolean(),
                    "description" => "Весовой товар",
                    'defaultValue' => false,
                ],
                'composition' => [
                    "type" => Type::listOf(Types::CompositionInput()),
                    "description" => "Состав тех. каты",
                ],
                "price_on_points" => [
                    "type" => Type::listOf(Types::ProductPriceInput()),
                    "description" => "Цены на точках",
                ],

            ],

        ]);
    }

    public static function TechnicalCardPrice()
    {
        return new InputObjectType([
            'name' => 'TechnicalCardPriceInput',
            'fields' => [
                'technical_card_id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'ID тех карты',
                ],
                "price_on_points" => [
                    "type" => Type::nonNull(Type::listOf(Types::ProductPriceInput())),
                    "description" => "Цены на точках",
                ],

            ],

        ]);
    }
}