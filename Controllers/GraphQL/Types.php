<?php

namespace Controllers\GraphQL;

use Controllers\GraphQL\Inputs\AutoPriceInputs;
use Controllers\GraphQL\Inputs\ItemInputs;
use Controllers\GraphQL\Inputs\ProductCategoryInputs;
use Controllers\GraphQL\Inputs\ProductInputs;
use Controllers\GraphQL\Inputs\TechnicalCardInputs;
use Controllers\GraphQL\Type\AutoPriceItemType;
use Controllers\GraphQL\Type\AutoPriceType;
use Controllers\GraphQL\Type\ColorType;
use Controllers\GraphQL\Type\CompositionItemType;
use Controllers\GraphQL\Type\CompositionType;
use Controllers\GraphQL\Type\EmployeeType;
use Controllers\GraphQL\Type\ItemCategoryType;
use Controllers\GraphQL\Type\ItemType;
use Controllers\GraphQL\Type\MenuCardType;
use Controllers\GraphQL\Type\MenuProductCategoryType;
use Controllers\GraphQL\Type\MenuProductType;
use Controllers\GraphQL\Type\MutationType;
use Controllers\GraphQL\Type\PartnerType;
use Controllers\GraphQL\Type\PointType;
use Controllers\GraphQL\Type\ProductCategoryPointType;
use Controllers\GraphQL\Type\ProductCategoryType;
use Controllers\GraphQL\Type\ProductPointType;
use Controllers\GraphQL\Type\ProductPriceType;
use Controllers\GraphQL\Type\ProductType;
use Controllers\GraphQL\Type\ProfileType;
use Controllers\GraphQL\Type\PromotionType;
use Controllers\GraphQL\Type\QueryType;
use Controllers\GraphQL\Type\StatisticsChartDataType;
use Controllers\GraphQL\Type\TechnicalCardType;
use Controllers\GraphQL\Type\TransactionItemType;
use Controllers\GraphQL\Type\TransactionType;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class Types
{
    private static $query;
    private static $objects;
    private static $mutation;
    private static $email;
    private static $enums;

    public static function query()
    {
        return self::$query ?: (self::$query = new QueryType());
    }

    public static function mutation()
    {
        return self::$mutation ?: (self::$mutation = new MutationType());
    }

    public static function Partner()
    {
        return isset(self::$objects['Partner']) ? self::$objects['Partner'] : (self::$objects['Partner'] = new PartnerType());
    }

    public static function MenuProduct()
    {
        return isset(self::$objects['MenuProduct']) ? self::$objects['MenuProduct'] : (self::$objects['MenuProduct'] = new MenuProductType());
    }

    public static function MenuProductCategory()
    {
        return isset(self::$objects['MenuProductCategory']) ? self::$objects['MenuProductCategory'] : (self::$objects['MenuProductCategory'] = new MenuProductCategoryType());
    }

    public static function TechnicalCard()
    {
        return isset(self::$objects['TechnicalCard']) ? self::$objects['TechnicalCard'] : (self::$objects['TechnicalCard'] = new TechnicalCardType());
    }

    public static function Composition()
    {
        return isset(self::$objects['Composition']) ? self::$objects['Composition'] : (self::$objects['Composition'] = new CompositionType());
    }

    public static function CompositionItem()
    {
        return isset(self::$objects['CompositionItem']) ? self::$objects['CompositionItem'] : (self::$objects['CompositionItem'] = new CompositionItemType());
    }

    public static function Profile()
    {
        return isset(self::$objects['Profile']) ? self::$objects['Profile'] : (self::$objects['Profile'] = new ProfileType());
    }

    public static function Item()
    {
        return isset(self::$objects['Item']) ? self::$objects['Item'] : (self::$objects['Item'] = new ItemType());
    }

    public static function StatisticsChartData()
    {
        return isset(self::$objects['StatisticsChartData']) ? self::$objects['StatisticsChartData'] : (self::$objects['StatisticsChartData'] = new StatisticsChartDataType());
    }

    public static function Color()
    {
        return isset(self::$objects['Color']) ? self::$objects['Color'] : (self::$objects['Color'] = new ColorType());
    }

    public static function Point()
    {
        return isset(self::$objects['Point']) ? self::$objects['Point'] : (self::$objects['Point'] = new PointType());
    }

    public static function ProductPoint()
    {
        return isset(self::$objects['ProductPoint']) ? self::$objects['ProductPoint'] : (self::$objects['ProductPoint'] = new ProductPointType());
    }

    public static function Transaction()
    {
        return isset(self::$objects['Transaction']) ? self::$objects['Transaction'] : (self::$objects['Transaction'] = new TransactionType());
    }

    public static function TransactionItem()
    {
        return isset(self::$objects['TransactionItem']) ? self::$objects['TransactionItem'] : (self::$objects['TransactionItem'] = new TransactionItemType());
    }

    public static function Promotion()
    {
        return isset(self::$objects['Promotion']) ? self::$objects['Promotion'] : (self::$objects['Promotion'] = new PromotionType());
    }

    public static function Employee()
    {
        return isset(self::$objects['Employee']) ? self::$objects['Employee'] : (self::$objects['Employee'] = new EmployeeType());
    }

    public static function ProductCategoryPoint()
    {
        return isset(self::$objects['ProductCategoryPoint']) ? self::$objects['ProductCategoryPoint'] : (self::$objects['ProductCategoryPoint'] = new ProductCategoryPointType());
    }

    public static function ItemCategory()
    {
        return isset(self::$objects['ItemCategory']) ? self::$objects['ItemCategory'] : (self::$objects['ItemCategory'] = new ItemCategoryType());
    }

    public static function ProductCategory()
    {
        return isset(self::$objects['ProductCategory']) ? self::$objects['ProductCategory'] : (self::$objects['ProductCategory'] = new ProductCategoryType());
    }

    public static function MenuCard()
    {
        return isset(self::$objects['MenuCard']) ? self::$objects['MenuCard'] : (self::$objects['MenuCard'] = new MenuCardType());
    }

    public static function ProductPrice()
    {
        return isset(self::$objects['ProductPrice']) ? self::$objects['ProductPrice'] : (self::$objects['ProductPrice'] = new ProductPriceType());
    }

    public static function AutoPrice()
    {
        return isset(self::$objects['AutoPrice']) ? self::$objects['AutoPrice'] : (self::$objects['AutoPrice'] = new AutoPriceType());
    }

    public static function Product()
    {
        return isset(self::$objects['Product']) ? self::$objects['Product'] : (self::$objects['Product'] = new ProductType());
    }

    public static function AutoPriceItem()
    {
        return isset(self::$objects['AutoPriceItem']) ? self::$objects['AutoPriceItem'] : (self::$objects['AutoPriceItem'] = new AutoPriceItemType());
    }

    public static function TechnicalCardsData()
    {

        return isset(self::$objects['TechnicalCardsData']) ? self::$objects['TechnicalCardsData'] : (self::$objects['TechnicalCardsData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "TechnicalCardsData",
            'fields' => function () {
                return [
                    'data' => Type::listOf(Types::TechnicalCard()),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'total' => Type::int(),
                ];
            },
        ]));
    }

    public static function StatusData()
    {

        return isset(self::$objects['StatusData']) ? self::$objects['StatusData'] : (self::$objects['StatusData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "Status",
            'fields' => function () {
                return [
                    'success' => Type::boolean(),
                ];
            },
        ]));
    }

    public static function StatisticsData()
    {

        return isset(self::$objects['StatisticsData']) ? self::$objects['StatisticsData'] : (self::$objects['StatisticsData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "StatisticsData",
            'fields' => function () {
                return [
                    'sales' => [
                        'type' => Type::float(),
                        'description' => 'Выручка (на сегодня)',
                    ],
                    'average_check' => [
                        'type' => Type::float(),
                        'description' => 'Средний чек (на сегодня)',
                    ],
                    'checks' => [
                        'type' => Type::float(),
                        'description' => 'Кол-во чеков (на сегодня)',
                    ],
                    'profit' => [
                        'type' => Type::float(),
                        'description' => 'Прибыль (на сегодня)',
                    ],
                    'points' => [
                        'type' => Type::listOf(Types::Point()),
                        'description' => 'Заведения по которым  статистика',

                    ],
                ];
            },
        ]));
    }

    public static function ProductsData()
    {

        return isset(self::$objects['ProductsData']) ? self::$objects['ProductsData'] : (self::$objects['ProductsData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "ProductsData",
            'fields' => function () {
                return [
                    'data' => Type::listOf(Types::Product()),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'total' => Type::int(),
                ];
            },
        ]));
    }

    public static function ItemCategoriesData()
    {

        return isset(self::$objects['ItemCategoriesData']) ? self::$objects['ItemCategoriesData'] : (self::$objects['ItemCategoriesData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "ItemCategoriesData",
            'fields' => function () {
                return [
                    'data' => Type::listOf(Types::ItemCategory()),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'total' => Type::int(),
                ];
            },
        ]));
    }

    public static function ProductCategoriesData()
    {

        return isset(self::$objects['ProductCategoriesData']) ? self::$objects['ProductCategoriesData'] : (self::$objects['ProductCategoriesData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "ProductCategoriesData",
            'fields' => function () {
                return [
                    'data' => Type::listOf(Types::ProductCategory()),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'total' => Type::int(),
                ];
            },
        ]));
    }

    public static function PointsData()
    {

        return isset(self::$objects['PointsData']) ? self::$objects['PointsData'] : (self::$objects['v'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "PointsData",
            'fields' => function () {
                return [
                    'data' => Type::listOf(Types::Point()),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'total' => Type::int(),
                ];
            },
        ]));
    }

    public static function ItemsData()
    {

        return isset(self::$objects['ItemsData']) ? self::$objects['ItemsData'] : (self::$objects['ItemsData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "ItemsData",
            'fields' => function () {
                return [
                    'data' => Type::listOf(Types::Item()),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'total' => Type::int(),
                ];
            },
        ]));
    }

    public static function TransactionsData()
    {

        return isset(self::$objects['TransactionsData']) ? self::$objects['TransactionsData'] : (self::$objects['TransactionsData'] = new ObjectType([
            'description' => 'Результат запроса',
            "name" => "TransactionsData",
            'fields' => function () {
                return [
                    'data' => Type::listOf(Types::Transaction()),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'total' => Type::int(),
                    'all_sum' => Type::float(),
                    'all_profit' => Type::float(),
                    'all_total' => Type::float(),
                ];
            },
        ]));
    }

    public static function AccessAction()
    {

        return isset(self::$enums['AccessAction']) ? self::$enums['AccessAction'] : (self::$enums['AccessAction'] = new EnumType([
            'name' => 'AccessAction',
            'description' => 'Доступ',
            'values' => [
                'add' => ['value' => 'add', 'description' => 'Создавать/Добавлять'],
                'update' => ['value' => 'update', 'description' => 'Обновлять'],
                'get' => ['value' => 'get', 'description' => 'Получать/Просматривать'],
            ],
        ]));
    }

    public static function PayType()
    {

        return isset(self::$enums['PayType']) ? self::$enums['PayType'] : (self::$enums['PayType'] = new EnumType([
            'name' => 'PayType',
            'description' => 'Тип оплаты',
            'values' => [
                'cash', 'card',
            ],
        ]));
    }

    public static function ItemType()
    {

        return isset(self::$enums['ItemType']) ? self::$enums['ItemType'] : (self::$enums['ItemType'] = new EnumType([
            'name' => 'ItemType',
            'description' => 'Тип ингредиента',
            'values' => [
                'all', 'production', 'ingredient',
            ],
        ]));
    }

    public static function ResultTechnicalCard()
    {

        return isset(self::$objects['ResultTechnicalCard']) ? self::$objects['ResultTechnicalCard'] : (self::$objects['ResultTechnicalCard'] = new ObjectType([
            'description' => 'Результат мутации',
            "name" => "ResultTechnicalCard",
            'fields' => function () {
                return [
                    'technical_card' => [
                        'type' => Types::TechnicalCard(),
                    ],
                    'success' => [
                        'type' => Type::string(),
                        'description' => 'Результат выполнения',
                    ],
                ];
            },
        ]));
    }

    public static function ProductPriceInput()
    {
        return isset(self::$objects['ProductPriceInput']) ? self::$objects['ProductPriceInput'] : (self::$objects['ProductPriceInput'] = TechnicalCardInputs::ProductPrice());
    }

    public static function ProductCategoryInput()
    {
        return isset(self::$objects['ProductCategoryInput']) ? self::$objects['ProductCategoryInput'] : (self::$objects['ProductCategoryInput'] = ProductCategoryInputs::ProductCategoryInput());
    }

    public static function ProductIngredient()
    {
        return isset(self::$objects['ProductIngredient']) ? self::$objects['ProductIngredient'] : (self::$objects['ProductIngredient'] = ProductInputs::ProductIngredient());
    }

    public static function AutoPriceItemInput()
    {
        return isset(self::$objects['AutoPriceItemInput']) ? self::$objects['AutoPriceItemInput'] : (self::$objects['AutoPriceItemInput'] = AutoPriceInputs::AutoPriceItemInput());
    }

    public static function AutoPriceInput()
    {
        return isset(self::$objects['AutoPriceInput']) ? self::$objects['AutoPriceInput'] : (self::$objects['AutoPriceInput'] = AutoPriceInputs::AutoPriceInput());
    }

    public static function ItemInput()
    {
        return isset(self::$objects['ItemInput']) ? self::$objects['ItemInput'] : (self::$objects['ItemInput'] = ItemInputs::ItemInput());
    }

    public static function ProductInput()
    {
        return isset(self::$objects['ProductInput']) ? self::$objects['ProductInput'] : (self::$objects['ProductInput'] = ProductInputs::ProductInput());
    }

    public static function ProductCategoryPointInput()
    {
        return isset(self::$objects['ProductCategoryPointInput']) ? self::$objects['ProductCategoryPointInput'] : (self::$objects['ProductCategoryPointInput'] = ProductCategoryInputs::ProductCategoryPointInput());
    }

    public static function ProductPointInput()
    {
        return isset(self::$objects['ProductPointInput']) ? self::$objects['ProductPointInput'] : (self::$objects['ProductPointInput'] = ProductInputs::ProductPointInput());
    }

    public static function TechnicalCardInput()
    {
        return isset(self::$objects['TechnicalCardInput']) ? self::$objects['TechnicalCardInput'] : (self::$objects['TechnicalCardInput'] = TechnicalCardInputs::TechnicalCard());
    }

    public static function TechnicalCardPriceInput()
    {
        return isset(self::$objects['TechnicalCardPriceInput']) ? self::$objects['TechnicalCardPriceInput'] : (self::$objects['TechnicalCardPriceInput'] = TechnicalCardInputs::TechnicalCardPrice());
    }

    public static function CompositionInput()
    {
        return isset(self::$objects['CompositionInput']) ? self::$objects['CompositionInput'] : (self::$objects['CompositionInput'] = TechnicalCardInputs::Composition());
    }

    public static function ChartScale()
    {
        return isset(self::$enums['ChartScaleType']) ? self::$enums['ChartScaleType'] : (self::$enums['ChartScaleType'] = new EnumType([
            'name' => 'ChartScaleType',
            'description' => 'Тип деления графика',
            'values' => ['days', 'weeks', 'months', 'hours', 'days_week'],

        ]));
    }

    public static function MenuItemType()
    {
        return isset(self::$enums['MenuItemType']) ? self::$enums['MenuItemType'] : (self::$enums['MenuItemType'] = new EnumType([
            'name' => 'MenuItemType',
            'description' => 'Тип элемента меню',
            'values' => ['category', 'product'],

        ]));
    }

    public static function Until()
    {
        return isset(self::$enums['Until']) ? self::$enums['Until'] : (self::$enums['Until'] = new EnumType([
            'name' => 'Until',
            'description' => 'Еденицы измеения',
            'values' => ['г', 'мл', 'шт'],

        ]));
    }

    public static function CompositionUntil()
    {
        return isset(self::$enums['CompositionUntil']) ? self::$enums['CompositionUntil'] : (self::$enums['CompositionUntil'] = new EnumType([
            'name' => 'CompositionUntil',
            'description' => 'Еденицы измеения для состава',
            'values' => [
                'кг' => ['value' => 'кг'],
                'л' => ['value' => 'л'],
                'шт' => ['value' => 'шт'],
            ],
        ]));
    }

    public static function ModelType()
    {
        return isset(self::$enums['ModelType']) ? self::$enums['ModelType'] : (self::$enums['ModelType'] = new EnumType([
            'name' => 'Model',
            'description' => 'Объект архивации',
            'values' => [
                'item' => ['value' => 'item'],
                'technical_card' => ['value' => 'technical_card'],
                'product' => ['value' => 'product'],
            ],
        ]));
    }

    public static function ItemSort()
    {
        return isset(self::$objects['ItemSort']) ? self::$objects['ItemSort'] : (self::$objects['ItemSort'] = ItemInputs::ItemSort());
    }

    public static function ProductSort()
    {
        return isset(self::$objects['ProductSort']) ? self::$objects['ProductSort'] : (self::$objects['ProductSort'] = ProductInputs::ProductSort());
    }

    public static function TechnicalCardSort()
    {
        return isset(self::$objects['TechnicalCardSort']) ? self::$objects['TechnicalCardSort'] : (self::$objects['TechnicalCardSort'] = TechnicalCardInputs::TechnicalCardSort());
    }

    public static function ActionArchiveType()
    {
        return isset(self::$enums['ActionArchive']) ? self::$enums['ActionArchive'] : (self::$enums['ActionArchive'] = new EnumType([
            'name' => 'ActionArchive',
            'description' => 'Действие',
            'values' => ['add', 'recovery'],
        ]));
    }

    public static function SortOrderType()
    {
        return isset(self::$enums['SortOrderType']) ? self::$enums['SortOrderType'] : (self::$enums['SortOrderType'] = new EnumType([
            'name' => 'SortOrderType',
            'description' => 'Направление сортировки',
            'values' => ['ASC', 'DESC'],
        ]));
    }
}