<?php

namespace Controllers;

use Hamcrest\Util;
use Illuminate\Support\Facades\Auth;
use Support\DB;
use Support\Utils;
use Rakit\Validation\Validator;
use Support\mDB;
use Support\Request;





class AnalyticsController
{

    private static $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    private static  $weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

    public static function points()
    {
        $result =    DB::makeArray(DB::query('SELECT p.id, p.name, pa.name as partner_name, pa.id as partner_id, pa.`parent` as partner_parent  FROM `app_partner_points` p JOIN `app_partner` pa WHERE p.`partner`=pa.`id`'));

        Utils::responsePlain($result);
    }


    public static function today()
    {

        $from =  strtotime(date('Y-m-d'));

        $points =    DB::makeArray(DB::query('SELECT p.id, p.name, pa.name as partner_name, pa.id as partner_id, pa.`parent` as partner_parent  FROM `app_partner_points` p JOIN `app_partner` pa WHERE p.`partner`=pa.`id`'));


        $result = mDB::collection("transactions")->aggregate(
            [
                [
                    '$match' => [
                        'created' => ['$gte' => $from]
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'point' => '$point',
                        ],
                        'totalPrice' => [
                            '$sum' => '$total'
                        ],
                        'totalProfit' => [
                            '$sum' => '$profit'
                        ],
                        'avg' => ['$avg' => '$total'],
                        'count' => [
                            '$sum' => 1
                        ]

                    ]
                ]

            ]
        );

        foreach ($result as $item) {
            foreach ($points as $val) {
                if ($val['id'] == $item['_id']['point']) {
                    $item['point'] = $val;
                }
            }

            $data['points'][] = $item;
        }

        Utils::responsePlain($data);
    }


    public static function metrica()
    {

        if (!Request::has(['points', 'to', 'from'])) Utils::response("error", "Укажите диапазон дат и points", 3);



        $points = Request::$request['points'];
        foreach ($points as &$val) {
            $val = (string)$val;
        }



        //Средние значения по дням
        $from_avr =  strtotime(date('Y-m-1', strtotime("-4 month")));
        $to_avr =  strtotime(date('Y-m-1', strtotime("-1 month")));



        $result = mDB::collection("transactions")->aggregate(
            [
                [
                    '$match' => [
                        'point' => ['$in' => $points],
                        'created' => ['$lte' => $to_avr, '$gte' => $from_avr]
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'month' => ['$month' => ['$toDate' => ['$multiply' => ['$created', 1000]]]],
                            'day' => ['$dayOfMonth' => ['$toDate' => ['$multiply' => ['$created', 1000]]]]

                        ],
                        'totalPrice' => [
                            '$sum' => '$total'
                        ],
                        'totalProfit' => [
                            '$sum' => '$profit'
                        ],
                        'avgTotal' => ['$avg' => '$total'],
                        'count' => [
                            '$sum' => 1
                        ]

                    ],


                ],
                [
                    '$group' => [
                        '_id' => [
                            'day' => '$_id.day',

                        ],
                        'totalPrice' => [
                            '$avg' => '$totalPrice'
                        ],
                        'totalProfit' => [
                            '$avg' => '$totalProfit'
                        ],
                        'avg' => [
                            '$avg' => '$avgTotal'
                        ],
                        'count' => [
                            '$avg' => '$count'
                        ],


                    ],


                ]


            ]
        );

        $data = [];
        foreach ($result as $item) {
            $data['avr'][$item['_id']['day']] = $item;
        }

        //===






        $from =  strtotime(date('Y-m-d', Request::$request['from']));
        $to =  strtotime(date('Y-m-d', Request::$request['to'] + (24 * 60 * 60)));








        $result = mDB::collection("transactions")->aggregate(
            [
                [
                    '$match' => [
                        'point' => ['$in' => Request::$request['points']],
                        'created' => ['$lte' => $to, '$gte' => $from]
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'month' => ['$month' => ['$toDate' => ['$multiply' => ['$created', 1000]]]],
                            'day' => ['$dayOfMonth' => ['$toDate' => ['$multiply' => ['$created', 1000]]]],
                            'week' => ['$week' => ['$toDate' => ['$multiply' => ['$created', 1000]]]]

                        ],
                        'totalPrice' => [
                            '$sum' => '$total'
                        ],
                        'totalProfit' => [
                            '$sum' => '$profit'
                        ],
                        'avg' => ['$avg' => '$total'],
                        'count' => [
                            '$sum' => 1
                        ]

                    ]
                ],
                [
                    '$sort' => [

                        '_id.month' => 1,
                        '_id.day' => 1,
                    ]
                ]
            ]
        );



        foreach ($result as $item) {
            $data['now'][] = $item;
        }



        //Подсчет динамики

        $created_date = [];
        for ($i = 1; $i < 3; $i++) {

            $from_trand =  strtotime(date('Y-m-1', strtotime("-$i month")));
            $to_tand =  strtotime(date('Y-m-28', strtotime("-$i month"))) + (24 * 60 * 60);

            $created_date[] = ['created' => ['$lte' => $to_tand, '$gte' => $from_trand]];
        }





        $result = mDB::collection("transactions")->aggregate(
            [
                [
                    '$match' => [
                        'point' => ['$in' => Request::$request['points']],
                        '$or' => $created_date
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'point' => '$point',
                            'month' => ['$month' => ['$toDate' => ['$multiply' => ['$created', 1000]]]],

                        ],
                        'totalPrice' => [
                            '$sum' => '$total'
                        ],

                    ]
                ],
                [
                    '$sort' => [
                        "_id.month" => -1
                    ]
                ]

            ]
        );

        $tmp = [];
        $trand = [];
        foreach ($result as $item) {

            $tmp[$item['_id']['point']][] = $item['totalPrice'];
        }

        foreach ($tmp as $point => $item) {
            if (count($item) > 1 && $item[0] > 0 && $item[0] > 0) {

                $percent = getPrc($item[0], $item[1]);
                $trand[$point] = [
                    'data' => $item,
                    'percent' => Round($percent)
                ];
            }
        }


        //Топ по точкам
        $points =    DB::makeArray(DB::query('SELECT p.id, p.name, pa.name as partner_name, pa.id as partner_id, pa.`parent` as partner_parent  FROM `app_partner_points` p JOIN `app_partner` pa WHERE p.`partner`=pa.`id`'));



        $result = mDB::collection("transactions")->aggregate(
            [
                [
                    '$match' => [
                        'point' => ['$in' => Request::$request['points']],
                        'created' => ['$lte' => $to, '$gte' => $from]
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'point' => '$point',

                        ],
                        'totalPrice' => [
                            '$sum' => '$total'
                        ],
                        'totalProfit' => [
                            '$sum' => '$profit'
                        ],
                        'avg' => ['$avg' => '$total'],
                        'count' => [
                            '$sum' => 1
                        ]

                    ]
                ]

            ]
        );



        foreach ($result as $item) {
            foreach ($points as $val) {
                if ($val['id'] == $item['_id']['point']) {
                    $item['point'] = $val;
                    $item['trand'] = isset($trand[$val['id']]) ? $trand[$val['id']]['percent'] : 0;
                    $item['trandData'] = isset($trand[$val['id']]) ? $trand[$val['id']] : [];
                }
            }

            $data['points'][] = $item;
        }











        //Сотрудники

        $emps =    DB::makeArray(DB::select('*', "app_employees"));


        $result = mDB::collection("transactions")->aggregate(
            [
                [
                    '$match' => [
                        'point' => ['$in' => Request::$request['points']],
                        'created' => ['$lte' => $to, '$gte' => $from]
                    ],
                ],
                [
                    '$group' => [
                        '_id' => [
                            'employee' => '$employee',

                        ],
                        'totalPrice' => [
                            '$sum' => '$total'
                        ],
                        'totalProfit' => [
                            '$sum' => '$profit'
                        ],
                        'avg' => ['$avg' => '$total'],
                        'count' => [
                            '$sum' => 1
                        ]

                    ]
                ]


            ]
        );




        foreach ($result as $item) {
            foreach ($emps as $val) {
                if ($val['id'] == $item['_id']['employee']) {
                    $item['employee'] = $val;
                }
            }

            $data['employee'][] = $item;
        }

        Utils::responsePlain($data);
    }
    //Дополнение данных транзакции
    public static function addDataToTransaction()
    {
        exit;
        //   echo date_default_timezone_get();
        //  exit;

        $data = DB::makeArray(DB::query("SELECT p.id, c.code FROM app_partner p JOIN app_cities c WHERE p.city=c.id"));
        // var_dump($data);
        // exit;

        foreach ($data as $partner) {

            date_default_timezone_set($partner['code']);

            $result = mDB::collection("transactions")->aggregate(
                [
                    [
                        '$match' => [
                            'partner' => $partner['id'],
                            "country" => "ru",
                            "created" => ['$gt' => 1625097600]
                        ]
                    ],
                    [
                        '$group' => [
                            '_id' => [
                                'point' => '$point',
                                'partner' => '$partner',
                                'country' => '$country',
                                'dayOfWeek' => [
                                    '$dayOfWeek' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get()
                                    ]
                                ],
                                'month' => [
                                    '$month' => [
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

                                'day' => [
                                    '$dayOfMonth' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get()
                                    ]
                                ],
                                'hour' => [
                                    '$hour' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get()
                                    ]
                                ],
                            ],
                            'sales' => [
                                '$sum' => '$total'
                            ],
                            'profit' => [
                                '$sum' => '$profit'
                            ],
                            'avg_check' => ['$avg' => '$total'],
                            'checks' => [
                                '$sum' => 1
                            ],
                            'created_from' => ['$first' => '$created'],
                            'created_to' => ['$last' => '$created']


                        ]
                    ],
                ],
                [
                    //    "sort"=>['_id'=>-1],
                    //    "limit"=>10
                ]
            ); //->toArray();
            $count = 0;
            $insert = [];
            foreach ($result as $item) {
                $count++;
                $insert[] = [
                    'point' => (int)$item['_id']['point'],
                    'partner' => (int)$item['_id']['partner'],
                    'country' => $item['_id']['country'],
                    'dayOfWeek' => $item['_id']['dayOfWeek'],
                    'year' => $item['_id']['year'],
                    'day' => $item['_id']['day'],
                    'hour' => $item['_id']['hour'],
                    'sales' => $item['sales'],
                    'profit' => $item['profit'],
                    'avg_check' => $item['avg_check'],
                    'checks' => $item['checks'],
                    'created_from' => $item['created_from'],
                    'created_to' => $item['created_to'],

                ];
            }
            if (count($insert) > 0)
                mDB::collection("analytics_transactions")->insertMany($insert);

            echo 'done ' . $count . ' ';
        }
        // echo json_encode($result);
    }

    //отчет эффективности
    public static function pulse()
    {

        $user =  Request::authUser();

        $pointsData =   DB::makeArray(DB::select("id, name", "app_partner_points", "partner=" . $user['partner']));
        $ids = [];
        $points = [];
        if (count($pointsData) > 0) {
            foreach ($pointsData as $val) {
                $ids[] = (int)$val['id'];
                $points[(int)$val['id']] = $val;
            }
        }



        $data = DB::makeArray(DB::query("SELECT i.id, i.name,pi.count, pi.point, c.name as category FROM `app_point_items` pi JOIN `app_items` i LEFT JOIN app_items_category c ON  c.id = i.category  WHERE pi.item=i.id AND pi.point IN (" . implode(',', $ids) . ")"));



        //Получаем расчет в день


        $from =  strtotime(date('Y-m-d', strtotime("-31 days")));
        $to =    strtotime(date('Y-m-d')); //, strtotime("-1 days")));

        $days = 30;

        $result = mDB::collection("partner_transactions")->aggregate(
            [
                [
                    '$match' => [
                        'date' => [
                            '$gte' => $from,  '$lte' => $to,
                        ],
                        'point' => ['$in' => $ids],
                        'proccess' => 4
                    ]
                ],
                [
                    '$group' => [
                        '_id' => [
                            'day' => ['$dayOfMonth' => ['$toDate' => ['$multiply' => ['$date', 1000]]]],
                            'item' => '$item',
                            'point' => '$point'
                        ],
                        'count' => [
                            '$sum' => '$count'
                        ],

                    ]
                ],
                [
                    '$group' => [
                        '_id' =>  [
                            'item' => '$_id.item',
                            'point' => '$_id.point'
                        ],
                        'days' => [
                            '$sum' => 1
                        ],
                        'count' => [
                            '$avg' => '$count'
                        ]


                    ]
                ]
            ]
        )->toArray();

        $max_day = 0;
        foreach ($result as $item) {
            if ($max_day < $item['days']) $max_day = $item['days'];
        }



        foreach ($data as &$val) {
            $val['count'] = round((float)$val['count'], 2);
            if (!isset($val['consumption']))  $val['consumption'] = 0;
            if (!isset($val['days']))  $val['days'] = 0;
        }

        foreach ($result as $item) {
            foreach ($data as &$val) {

                if ($val['id'] == $item['_id']['item'] && $val['point'] == $item['_id']['point']) {
                    $val['consumption'] = round(abs($item['count']) / $max_day, 2);
                    $val['point'] = $points[$val['point']];
                    $val['days'] = $val['consumption'] > 0 ? round($val['count'] / $val['consumption']) : 0;
                }
            }
        }

        $tmp = [];
        foreach ($data as $val) {
            if ($val['count'] < 0 && isset($val['point']['id'])) { //&& $val['consumption'] > 0
                $tmp[] = $val;
            }
        }

        $forecast = $tmp;


        $from =  strtotime(date('Y-m-d', strtotime("-30 days")));
        //Транзакции в минус 
        $transactions =  DB::makeArray(DB::select("*", "app_transactions", "created > {$from} AND point in (" . implode(",", $ids) . ") AND profit < 0 AND points = 0 AND discount = 0 AND promotion_code = ''", "", "100"));
        if (!$transactions) $transactions = [];

        foreach ($transactions as &$trans) {
            $trans['point'] = $points[$trans['point']];
        }



        Utils::response('success', [
            "forecast" => $forecast,
            "transactions" => $transactions
        ], 7);
    }

    //Средний расход
    public static function average_consumption()
    {



        $from =  strtotime(date('Y-m-d', strtotime("-31 days")));
        $to =    strtotime(date('Y-m-d')); //, strtotime("-1 days")));

        $days = 30;

        $result = mDB::collection("partner_transactions")->aggregate(
            [
                [
                    '$match' => [
                        'date' => [
                            '$gte' => $from,   '$lte' => $to,
                        ],
                        'proccess' => 4
                    ]
                ],
                [
                    '$group' => [
                        '_id' => [
                            'day' => ['$dayOfMonth' => ['$toDate' => ['$multiply' => ['$date', 1000]]]],
                            'item' => '$item',
                            'point' => '$point'
                        ],
                        'count' => [
                            '$sum' => '$count'
                        ],

                    ]
                ],
                [
                    '$group' => [
                        '_id' =>  [
                            'item' => '$_id.item',
                            'point' => '$_id.point'
                        ],
                        'days' => [
                            '$sum' => 1
                        ],
                        'count' => [
                            '$avg' => '$count'
                        ]


                    ]
                ]
            ]
        )->toArray();

        $max_day = 0;
        foreach ($result as $item) {
            if ($max_day < $item['days']) $max_day = $item['days'];
        }

        $data = [];
        foreach ($result as $item) {
            $data[] = [
                'item' => $item['_id']['item'],
                'point' => $item['_id']['point'],
                'consumption' => $item['count'] / $max_day
            ];
        }
        echo json_encode($data);
    }


    public static function popular()
    {
        $user = Request::authUser();

        $match = [
            "country" => Request::$country,
            'items' => ['$exists' => true]
        ];

        if (Request::has('partner')) {
            $match['partner'] =  (int)Request::$request['partner'];
        } else {
            $match['partner'] =  (int)$user['partner'];
        }



        $point = false;
        if (Request::has("point")) {
            $point = (int) Request::$request['point'];
            if ($point > 0)
                $match['point'] = (int)$point;
        }


        $to = Request::has('to') ? strtotime(date('Y-m-d', Request::$request['to'])) + (24 * 60 * 60) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from =  Request::has('from') ? strtotime(date('Y-m-d', Request::$request['from'])) : strtotime(date('Y-m-d', strtotime("-1 months")));

        $match['created'] = [
            '$lte' => $to, '$gte' => $from
        ];



        $result = mDB::collection("transactions")->aggregate(
            [
                [
                    '$match' => $match

                ], [
                    '$unwind' => '$items'

                ], [
                    '$group' => [
                        '_id' => '$items.name',
                        'count' => [
                            '$sum' => 1
                        ]


                    ]
                ], [
                    '$project' => ['_id' => 0, 'count' => 1, 'title' => '$_id']
                ], [
                    '$sort' => [
                        'count' => -1
                    ]
                ]


            ]
        );



        $data = [];
        foreach ($result as $val) {



            $data[] = $val;
        }

        Utils::response("success", $data, 7);
    }

    public static function hours()
    {

        $user = Request::authUser();

        $match = [
            "country" => Request::$country
        ];

        if (Request::has('partner')) {
            $match['partner'] =  (int)Request::$request['partner'];
        } else {
            $match['partner'] =  (int)$user['partner'];
        }



        $point = false;
        if (Request::has("point")) {
            $point = (int) Request::$request['point'];
            $match['point'] = (int)$point;
        }


        $to = Request::has('to') ? strtotime(date('Y-m-d', Request::$request['to'])) + (24 * 60 * 60) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from =  Request::has('from') ? strtotime(date('Y-m-d', Request::$request['from'])) : strtotime(date('Y-m-d', strtotime("-1 months")));

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

        Utils::response("success", $data, 7);
    }

    public static function weekdays()
    {
        $user = Request::authUser();

        $match = [
            "country" => Request::$country
        ];

        if (Request::has('partner')) {
            $match['partner'] =  (int)Request::$request['partner'];
        } else {
            $match['partner'] =  (int)$user['partner'];
        }



        $point = false;
        if (Request::has("point")) {
            $point = (int) Request::$request['point'];
            $match['point'] = (int)$point;
        }


        $to = Request::has('to') ? strtotime(date('Y-m-d', Request::$request['to'])) + (24 * 60 * 60) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from =  Request::has('from') ? strtotime(date('Y-m-d', Request::$request['from'])) : strtotime(date('Y-m-d', strtotime("-1 months")));

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
                            'day' => '$day',
                            'dayOfWeek' => '$dayOfWeek'
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


            $val['label'] = self::$weekdays[$val['_id']['dayOfWeek'] - 1];


            $val['checks'] = round($val['checks']);
            $val['profit'] = round($val['profit']);
            $val['revenue'] = round($val['sales']);
            $val['average_check'] = round($val['avg_check']);

            if ($val['checks']  > 1)
                $data[] = $val;
        }

        Utils::response("success", $data, 7);
    }

    public static function mainChart()
    {


        $user = Request::authUser();

        $result = [];

        $today = strtotime(date('d-m-Y', time()));

        $match = [
            "country" => Request::$country
        ];

        if (Request::has('partner')) {
            $match['partner'] =  Request::$request['partner'];
        } else {
            $match['partner'] =  $user['partner'];
        }



        $point = false;
        if (Request::has("point")) {
            $point = (int) Request::$request['point'];
            $match['point'] = (string)$point;
        }


        $to = Request::has('to') ? strtotime(date('Y-m-d', Request::$request['to'])) + (24 * 60 * 60) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from =  Request::has('from') ? strtotime(date('Y-m-d', Request::$request['from'])) : strtotime(date('Y-m-d', strtotime("-1 months")));
        $match['created'] = [
            '$lte' => $to, '$gte' => $from
        ];


        $type = Request::has('type') ? Request::$request['type'] : 'days';

        $group = [];
        $sort = [];
        switch ($type) {
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
                $val['label'] = $val['_id']['day'] . ' ' . self::$months[$val['_id']['month'] - 1];

            if ($type == 'weeks')
                $val['label'] = $val['_id']['week'] . ' неделя ' . $val['_id']['year'] . ' года';

            if ($type == 'months')
                $val['label'] = self::$months[$val['_id']['month'] - 1] . ' ' . $val['_id']['year'];


            $val['checks'] = round($val['checks']);
            $val['profit'] = round($val['profit']);
            $val['revenue'] = round($val['revenue']);
            $val['average_check'] = round($val['average_check']);


            $data[] = $val;
        }

        Utils::response("success", $data, 7);
    }


    public static function topPanel()
    {


        $user = Request::authUser();

        $result = [];

        $today = strtotime(date('d-m-Y', time()));

        $match = [
            "country" => Request::$country
        ];

        if (Request::has('partner')) {
            $match['partner'] =  Request::$request['partner'];
        } else {
            if ($user['partner'] != 1)
                $match['partner'] =  $user['partner'];
        }


        $match['created'] = [
            '$gte' => $today
        ];

        $point = false;
        if (Request::has("point")) {
            $point = (int) Request::$request['point'];
            $match['point'] = (string)$point;
        }



        $result =  mDB::collection('transactions')->aggregate([

            [
                '$match' => $match
            ],
            [
                '$group' => [
                    '_id' => [
                        //        'partner' => '$partner',
                    ],
                    "checks" => [
                        '$sum' => 1
                    ],
                    'profit' => [
                        '$sum' => '$profit'
                    ],
                    'revenue' => [
                        '$sum' => '$total'
                    ],
                    'average_check' => [
                        '$avg' => '$total'
                    ]
                ]
            ]

        ])->toArray();


        $result = $result[0];

        $result['point'] = $point;

        $result['checks'] = round($result['checks']);
        $result['profit'] = round($result['profit']);
        $result['revenue'] = round($result['revenue']);
        $result['average_check'] = round($result['average_check']);

        if ($point) {

            $result['name'] = DB::getRow(DB::select("name", 'app_partner_points', "id=" . $point, "", "1"))['name'];
        } else {
            $result['name'] = "Общая статистика";
        }

        Utils::response("success", $result, 7);
    }
}