<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Queries\AutoPriceQuery;
use Controllers\GraphQL\Queries\ChartQuery;
use Controllers\GraphQL\Queries\ColorsQuery;
use Controllers\GraphQL\Queries\EmployeesQuery;
use Controllers\GraphQL\Queries\ItemCategoryQuery;
use Controllers\GraphQL\Queries\ItemsQuery;
use Controllers\GraphQL\Queries\MenuQuery;
use Controllers\GraphQL\Queries\PartnersQuery;
use Controllers\GraphQL\Queries\PointsQuery;
use Controllers\GraphQL\Queries\ProductCategoryQuery;
use Controllers\GraphQL\Queries\ProductsQuery;
use Controllers\GraphQL\Queries\ProfileQuery;
use Controllers\GraphQL\Queries\PromotionsQuery;
use Controllers\GraphQL\Queries\StatisticsQuery;
use Controllers\GraphQL\Queries\TechnicalCardsQuery;
use Controllers\GraphQL\Queries\TransactionsQuery;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class QueryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => "Query",
            'fields' =>  function () {
                return [
                    'technical_cards' => TechnicalCardsQuery::get(),
                    'products' => ProductsQuery::get(),
                    'items' => ItemsQuery::get(),
                    'item_categories' => ItemCategoryQuery::get(),
                    'colors' => ColorsQuery::get(),
                    'points' => PointsQuery::get(),
                    'product_categories' => ProductCategoryQuery::get(),
                    'profile' => ProfileQuery::get(),
                    'statistics' => StatisticsQuery::get(),
                    'chart' => ChartQuery::get(),
                    'menu' => MenuQuery::get(),
                    'partners' => PartnersQuery::get(),
                    'auto_prices' => AutoPriceQuery::get(),
                    'transactions' => TransactionsQuery::get(),
                    'employees' => EmployeesQuery::get(),
                    'promotions' => PromotionsQuery::get()
                ];
            }
        ];
        parent::__construct($config);
    }
}