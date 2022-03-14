<?php

namespace Controllers\GraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class StatisticsChartDataType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Данные для графика по дням',
            "name" => "StatisticsChartData",
            'fields' => function () {
                return [
                    'avg_check' => [
                        'type' => Type::float(),
                        'description' => 'Средний чек',
                        'resolve' => function ($root, $args) {
                            return number_format($root['avg_check'], 2, '.', '');
                        },
                    ],
                    'checks' => [
                        'type' => Type::float(),
                        'description' => 'Кол-во чеков',
                        'resolve' => function ($root, $args) {
                            return number_format($root['checks'], 2, '.', '');
                        },
                    ],
                    'sales' => [
                        'type' => Type::float(),
                        'description' => 'Выручка',
                        'resolve' => function ($root, $args) {
                            return number_format($root['sales'], 2, '.', '');
                        },
                    ],
                    'profit' => [
                        'type' => Type::float(),
                        'description' => 'Прибыль',
                        'resolve' => function ($root, $args) {
                            return !is_nan($root['profit']) ? number_format($root['profit'], 2, '.', '') : 0;
                        },
                    ],
                    'label' => [
                        'type' => Type::string(),
                    ],
                ];
            },

        ];
        parent::__construct($config);
    }
}