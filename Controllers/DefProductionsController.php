<?php

namespace Controllers;

use Hamcrest\Util;
use Support\DB;
use Support\Utils;
use Rakit\Validation\Validator;
use Support\mDB;
use Support\Request;




//Отложенные произвоства 
class DefProductionsController
{



    public static function create()
    {

        $user = Request::authUser();


        if (!Request::has(['point', 'point_to', 'date', 'hour_from', 'hour_to', 'products'])) {
            Utils::response('error', ['msg' => 'Заполните форму производства.'], 1);
        }

        $point = Request::$request['point'];
        $point_to = Request::$request['point_to'];
        $date =  Request::$request['date'];

        $hour_from = Request::$request['hour_from'];
        $hour_to = Request::$request['hour_to'];
        $products = Request::$request['products'];

        $pointData = DB::getRow(DB::select('*', DB_PARTNER_POINTS, 'partner = ' . $user['partner'] . ' AND id = ' . $point));
        $pointToData = DB::getRow(DB::select('*', DB_PARTNER_POINTS, 'partner = ' . $user['partner'] . ' AND id = ' . $point_to));



        $comment = Request::has('comment') ? Request::$request['comment'] : "";


        if ($date < (time() + (86400 / 2)))
            Utils::response('error', ['msg' => 'Отложенное производство нельзя создавать сегодняшним днем.'], 422);



        $ids = [];
        foreach ($products as $val) {
            $ids[] = $val['id'];
        }

        $items =  DB::makeArray(DB::query('SELECT i.id, i.name, i.`product_category` FROM `app_items` i LEFT JOIN `app_product_categories` c ON i.`product_category`=c.`id` WHERE i.id IN (' . implode(',', $ids) . ')'));

        foreach ($products as &$prod) {
            foreach ($items as $item) {
                if ($prod['id'] == $item['id']) {
                    $prod['name'] = $item['name'];
                    $prod['product_category'] = $item['product_category'];
                }
            }
        }

       
        $products_count = 0;
        $sum = 0;

        foreach ($products as $item) {
            $products_count = $products_count+$item['count'];
            $sum = $sum+$item['cost_price']*$item['count'];

        }

        $employee = [
            'name'=>"Администратор"
        ];

        if($user['employee']){
            $employee = DB::getRow(DB::select('*',"app_employees","id=".$user['employee']));
        }

        mDB::collection('deferred_productions')->insertOne([
            'country' => Request::$country,
            'partner' => (int)$user['partner'],
            'employee'=>  $employee,
            'point' => $pointData,
            'point_to' => $pointToData,
            'products_count'=>$products_count,
            'sum'=>$sum,
            'date' => (int)$date,
            'comment' => $comment,
            'hour_from' => (int)$hour_from,
            'hour_to' => (int)$hour_to,
            'products' => $products,
            'status' => "WAIT"

        ]);

        Utils::response('success', ['msg' => 'Отложенное производство созданно.'], 7);
    }


    public static function update()
    {

        $user = Request::authUser();


        if (!Request::has(['id', 'point', 'point_to', 'date', 'hour_from', 'hour_to', 'products'])) {
            Utils::response('error', ['msg' => 'Заполните форму производства.'], 1);
        }


        $result = mDB::collection('deferred_productions')->findOne([
            'partner' => (int)$user['partner'],
            'status' => "WAIT",
            '_id' => mDB::id(Request::$request['id']),
            'country' => Request::$country
        ]);

        if (!isset($result->_id))   Utils::response('error', 'Отложенное производство не найдено или не доступно для редактирования.', 422);



        $point = Request::$request['point'];
        $point_to = Request::$request['point_to'];
        $date =  Request::$request['date'];

        $hour_from = Request::$request['hour_from'];
        $hour_to = Request::$request['hour_to'];
        $products = Request::$request['products'];

        $pointData = DB::getRow(DB::select('*', DB_PARTNER_POINTS, 'partner = ' . $user['partner'] . ' AND id = ' . $point));
        $pointToData = DB::getRow(DB::select('*', DB_PARTNER_POINTS, 'partner = ' . $user['partner'] . ' AND id = ' . $point_to));

       
        $ids = [];
        foreach ($products as $val) {
            $ids[] = $val['id'];
        }

        $items =  DB::makeArray(DB::query('SELECT i.id, i.name, i.`product_category` FROM `app_items` i LEFT JOIN `app_product_categories` c ON i.`product_category`=c.`id` WHERE i.id IN (' . implode(',', $ids) . ')'));

        foreach ($products as &$prod) {
            foreach ($items as $item) {
                if ($prod['id'] == $item['id']) {
                    $prod['name'] = $item['name'];
                    $prod['product_category'] = $item['product_category'];
                }
            }
        }

       
        $products_count = 0;
        $sum = 0;

        foreach ($products as $item) {
            $products_count = $products_count+$item['count'];
            $sum = $sum+$item['cost_price']*$item['count'];

        }



        $comment = Request::has('comment') ? Request::$request['comment'] : "";


        if ($date < (time() + (86400 / 2)))
            Utils::response('error', ['msg' => 'Отложенное производство нельзя создавать сегодняшним днем.'], 422);








        mDB::collection('deferred_productions')->updateOne(
            [
                '_id' => mDB::id(Request::$request['id'])
            ],
            [
                '$set' => [
                    'country' => Request::$country,
                    'partner' => (int)$user['partner'],
                    'point' => $pointData,
                    'products_count'=>$products_count,
                    'sum'=>$sum,
                    'point_to' => $pointToData,
                    'date' => (int)$date,
                    'comment' => $comment,
                    'hour_from' => (int)$hour_from,
                    'hour_to' => (int)$hour_to,
                    'products' => $products
                ]

            ]
        );

        Utils::response('success', ['msg' => 'Отложенное производство отредактированно.'], 7);
    }


    public static function delete()
    {

        $user = Request::authUser();

        if (!Request::has("id"))
            Utils::response('error', 'Отложенное производство не найдено.', 422);

        $result = mDB::collection('deferred_productions')->findOne([
            'partner' => (int)$user['partner'],
            'status' => "WAIT",
            '_id' => mDB::id(Request::$request['id']),
            'country' => Request::$country
        ]);

        if (!isset($result->_id))   Utils::response('error', 'Отложенное производство не найдено или не доступно для отмены.', 422);



        mDB::collection('deferred_productions')->updateOne([
            'partner' => (int)$user['partner'],
            'status' => "WAIT",
            '_id' => mDB::id(Request::$request['id']),
            'country' => Request::$country
        ], [
            '$set' => [
                'status' => "CANCEL"
            ]
        ]);



        Utils::response('success', ['msg' => 'Отложенное производство отменено.'], 7);
    }


    public static function one()
    {

        $user = Request::authUser();

        if (!Request::has("id"))
            Utils::response('error', 'Отложенное производство не найдено.', 422);


        $result = mDB::collection('deferred_productions')->findOne([
            'partner' => (int)$user['partner'],
            'status' => "WAIT",
            '_id' => mDB::id(Request::$request['id']),
            'country' => Request::$country
        ]);

        if (!isset($result->_id))   Utils::response('error', 'Отложенное производство не найдено.', 422);

        $result->_id = (string) $result->_id;

        Utils::response('success', $result, 7);
    }


    public static function get()
    {

        $user = Request::authUser();

        $filter = [
            'partner' => (int)$user['partner'],
            'status' => "WAIT",
            'country' => Request::$country
        ];

        $from = Request::has('from') ? strtotime(date('Y-m-d', Request::$request['from'])) : strtotime(date('Y-m-d'));
        $to = Request::has('to') ? (strtotime(date('Y-m-d', Request::$request['to'])) + (24 * 60 * 60)) : strtotime(date('Y-m-d', strtotime("+1 months")));




        $filter['date'] = ['$gte' => $from, '$lte' => $to];

        if (Request::has('point')) {
            if ((int) Request::$request['point'] > 0)
                $filter['point.id'] = (string) Request::$request['point'];
        }

        if (Request::has('point_to')) {
            if ((int) Request::$request['point_to'] > 0)
                $filter['point_to.id'] = (string) Request::$request['point_to'];
        }



        if (Request::has('category')) {
            if ((int) Request::$request['category'] > 0)
                $filter['products.product_category'] = (string) Request::$request['category'];
        }




        $result = mDB::collection('deferred_productions')->find($filter);

        $data = [];
        foreach ($result as $item) {
            $item->_id = (string) $item->_id;
            $data[] = $item;
        }



        Utils::response('success', $data, 7);
    }
}
