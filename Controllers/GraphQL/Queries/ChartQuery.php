<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class ChartQuery
{

    public static function get()
    {
        return  [
            'type' => Type::listOf(Types::StatisticsChartData()),
            'args' => [
                'points' => ["type" => Type::listOf(Type::int()),  'description' => "массив ID точек"],
                'partner' => ["type" => Type::int(),  'description' => "ID партнера"],
                'from' => ["type" => Type::nonNull(Type::int()),  'description' => "UNIX дата от"],
                'to' => ["type" => Type::nonNull(Type::int()),  'description' => "UNIX дата до"],
                'type' => ["type" => Type::nonNull(Types::ChartScale()), 'description' => "Тип графика (деление по дням/неделям/месяцам)"]
            ],
            'description' => 'График по диапазону',
            'resolve' => function ($root, $args) {


                $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
                $weekdays = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];


                $match = [
                    "country" => Auth::$country
                ];



                if (isset($args['points']) && count($args['points']) > 0) {

                    $ids = [];
                    foreach ($args['points'] as $point) {
                        $ids[] =   $point;
                    }
                    $match['point'] =  ['$in' => $ids];
                } else if (isset($args['partner'])) {

                    $match['partner'] = (int)$args['partner'];
                } else {
                    $match['partner'] = ['$in' => [(int)Auth::$user['id']]];
                }


                $to =  strtotime(date('Y-m-d 23:59:00', $args['to']));
                $from =   strtotime(date('Y-m-d 00:00:00', $args['from']));



                $type = $args['type'];

                if ($type == 'hours' || $type == 'days_week') {
                    $match['created_from'] = [
                        '$lte' => $to, '$gte' => $from
                    ];
                } else {
                    $match['created'] = [
                        '$lte' => $to, '$gte' => $from
                    ];
                }




                $group = [];
                $sort = [];

                switch ($type) {

                    case 'days_week':



                        $match['created_from'] = [
                            '$lte' => $to, '$gte' => $from
                        ];






                        $result = mDB::collection("analytics_transactions")->aggregate(
                            [
                                [
                                    '$match' => $match,
                                ],
                                [
                                    '$group' => [
                                        '_id' => [
                                            'hash' => '$hash',
                                        ],
                                        'sales' => [
                                            '$first' => '$sales'
                                        ],
                                        'day' => [
                                            '$first' => '$day'
                                        ],
                                        'dayOfWeek' => [
                                            '$first' => '$dayOfWeek'
                                        ],
                                        'profit' => [
                                            '$first' => '$profit'
                                        ],
                                        'avg_check' => ['$first' => '$avg_check'],
                                        'checks' =>  [
                                            '$first' => '$checks'
                                        ]

                                    ]
                                ],
                                [
                                    '$group' => [
                                        '_id' => [
                                            'day' => '$day',
                                            'dayOfWeek' => '$dayOfWeek',
                                        ],
                                        'sales' => [
                                            '$sum' => '$sales'
                                        ],
                                        'profit' => [
                                            '$sum' => '$profit'
                                        ],
                                        'avg_check' => ['$avg' => '$avg_check'],
                                        'checks' =>  [
                                            '$sum' => '$checks'
                                        ]

                                    ]
                                ],
                                [
                                    '$group' => [
                                        '_id' => [
                                            'dayOfWeek' => '$_id.dayOfWeek',
                                        ],
                                        'sales' => [
                                            '$avg' => '$sales'
                                        ],
                                        'profit' => [
                                            '$avg' => '$profit'
                                        ],
                                        'avg_check' => ['$avg' => '$avg_check'],
                                        'checks' => [
                                            '$avg' => '$checks'
                                        ]

                                    ]
                                ],
                                [
                                    '$sort' => [
                                        '_id.dayOfWeek' => 1,
                                    ]
                                ]
                            ]
                        );



                        $data = [];
                        foreach ($result as $val) {


                            $val['label'] = $weekdays[$val['_id']['dayOfWeek'] - 1];


                            $val['checks'] = round($val['checks']);
                            $val['profit'] = round($val['profit']);
                            $val['revenue'] = round($val['sales']);
                            $val['average_check'] = round($val['avg_check']);

                            if ($val['checks']  > 1)
                                $data[] = $val;
                        }

                        return $data;


                        break;

                    case 'hours':



                        $result = mDB::collection("analytics_transactions")->aggregate(
                            [
                                [
                                    '$match' => $match,
                                ],
                                [
                                    '$group' => [
                                        '_id' => [
                                            'hash' => '$hash',
                                        ],
                                        'sales' => [
                                            '$first' => '$sales'
                                        ],
                                        'day' => [
                                            '$first' => '$day'
                                        ],
                                        'hour' => [
                                            '$first' => '$hour'
                                        ],
                                        'profit' => [
                                            '$first' => '$profit'
                                        ],
                                        'avg_check' => ['$first' => '$avg_check'],
                                        'checks' =>  [
                                            '$first' => '$checks'
                                        ]

                                    ]
                                ],

                                [

                                    '$group' => [
                                        '_id' => [
                                            'day' => '$day',
                                            'hour' => '$hour',
                                        ],
                                        'sales' => [
                                            '$sum' => '$sales'
                                        ],
                                        'profit' => [
                                            '$sum' => '$profit'
                                        ],
                                        'avg_check' => ['$avg' => '$avg_check'],
                                        'checks' => [
                                            '$sum' => '$checks'
                                        ],

                                    ]
                                ],
                                [

                                    '$group' => [
                                        '_id' => [
                                            'hour' => '$_id.hour',
                                        ],
                                        'sales' => [
                                            '$avg' => '$sales'
                                        ],
                                        'profit' => [
                                            '$avg' => '$profit'
                                        ],
                                        'avg_check' => ['$avg' => '$avg_check'],
                                        'checks' => [
                                            '$avg' => '$checks'
                                        ]

                                    ]


                                ],
                                [
                                    '$sort' => [
                                        '_id.hour' => 1,
                                    ]
                                ]
                            ]
                        );



                        $data = [];
                        foreach ($result as $val) {


                            $val['label'] = $val['_id']['hour'];



                            $data[] = $val;
                        }

                        return $data;


                        break;


                    case "days":
                        $group = [
                            'month' => [
                                '$month' => [
                                    'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                    'timezone' => date_default_timezone_get()
                                ]
                            ],
                            'day' => [
                                '$dayOfMonth' => [
                                    'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                    'timezone' => date_default_timezone_get()
                                ]
                            ],
                            'year' => [
                                '$year' => [
                                    'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                    'timezone' => date_default_timezone_get()
                                ]
                            ],


                        ];

                        $sort = [
                            '_id.year' => 1,
                            '_id.month' => 1,
                            '_id.day' => 1,
                        ];
                        break;

                    case "weeks":
                        $group = [
                            'week' => ['$week' => ['$toDate' => ['$multiply' => ['$created', 1000]]]],
                            'year' => ['$year' => ['$toDate' => ['$multiply' => ['$created', 1000]]]]
                        ];

                        $sort = [
                            '_id.year' => 1,
                            '_id.week' => 1,
                        ];

                        break;

                    case "months":
                        $group = [
                            'month' => ['$month' => ['$toDate' => ['$multiply' => ['$created', 1000]]]],
                            'year' => ['$year' => ['$toDate' => ['$multiply' => ['$created', 1000]]]]
                        ];

                        $sort = [
                            '_id.year' => 1,
                            '_id.month' => 1,
                        ];

                        break;
                }


                $result = mDB::collection("transactions")->aggregate(
                    [
                        [
                            '$match' => $match,
                        ],
                        [
                            '$group' => [
                                '_id' => $group,
                                'sales' => [
                                    '$sum' => '$total'
                                ],
                                'profit' => [
                                    '$sum' => '$profit'
                                ],
                                'avg_check' => ['$avg' => '$total'],
                                'checks' => [
                                    '$sum' => 1
                                ]

                            ]
                        ],
                        [
                            '$sort' => $sort
                        ]
                    ]
                );

                $data = [];
                foreach ($result as $val) {

                    if ($type == 'days')
                        $val['label'] = $val['_id']['day'] . ' ' . $months[$val['_id']['month'] - 1];

                    if ($type == 'weeks')
                        $val['label'] = $val['_id']['week'] . ' неделя ' . $val['_id']['year'] . ' года';

                    if ($type == 'months')
                        $val['label'] = $months[$val['_id']['month'] - 1] . ' ' . $val['_id']['year'];


                    $val['checks'] = round($val['checks']);
                    $val['profit'] = round($val['profit']);
                    $val['sales'] = round($val['sales']);
                    $val['average_check'] = round($val['average_check']);


                    $data[] = $val;
                }
                return $data;
            }



        ];
    }
}