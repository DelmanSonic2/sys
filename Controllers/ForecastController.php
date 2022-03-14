<?php

namespace Controllers;

use Support\DB;
use Support\Utils;
use Rakit\Validation\Validator;
use Support\Auth;
use Support\mDB;
use Support\Request;





class ForecastController
{



    public static function get()
    {

        $user =  Request::authUser();

        $point =  Request::has('point') ? Request::$request['point'] : 0;
        if ($point < 1) {
            Utils::response("error", "Укажите заведение.", 3);
        }


        $categories =  Request::has('categories') ? Request::$request['categories'] : [];
        $category_where  = "";
        if (count($categories) > 0) {
            $category_where = ' AND c.id IN (' . implode(',', $categories) . ') ';
        }

        $data = DB::makeArray(DB::query("SELECT i.id, i.name,pi.count, c.name as category FROM `app_point_items` pi JOIN `app_items` i LEFT JOIN app_items_category c ON  c.id = i.category  WHERE pi.item=i.id AND pi.point={$point} {$category_where}"));


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
                        'point' => (string)$point,
                        'proccess' => 4,
                        'country' => Auth::$country
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

                if ($val['id'] == $item['_id']['item']) {
                    $val['consumption'] = round(abs($item['count']) / $max_day, 2);
                    $val['days'] = $val['consumption'] > 0 ? round($val['count'] / $val['consumption']) : 0;
                }
            }
        }

        $tmp = [];
        foreach ($data as $val) {
            if ($val['count'] != 0 || $val['consumption'] != 0) {
                $tmp[] = $val;
            }
        }

        Utils::response("success", $tmp, 7);
    }
}