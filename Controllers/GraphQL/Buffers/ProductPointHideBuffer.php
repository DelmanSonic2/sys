<?php

namespace Controllers\GraphQL\Buffers;

use Support\Auth;
use Support\DB;
use Support\mDB;

class ProductPointHideBuffer
{

    private static $ids = array();
    private static $point = 0;

    private static $results = array();

    public static function load()
    {


        if (!empty(self::$results)) return;

        $ids = explode(',', DB::getRow(DB::query("SELECT GROUP_CONCAT(p.id) as ids FROM `app_partner_points` p  WHERE p.partner = " . Auth::$user['id']))['ids']);
        foreach ($ids as &$id) $id = (int)$id;

        $prices = mDB::collection("technical_cards")->aggregate([
            [
                '$match' => [
                    "product.id" => ['$in' => self::$ids],

                    'prices' => ['$exists' => true],
                    "country" => Auth::$country,
                    '$or' => [
                        ["partner" => (int)Auth::$user['id']],
                        ["enableForAll" => true]
                    ]
                ]
            ],
            [
                '$project' => [
                    'prices' => '$prices',
                    'product' => '$product.id',
                    "pricesArray" => ['$isArray' => '$prices']
                ]
            ],
            [
                '$match' => [
                    'pricesArray' => false
                ]
            ],
            [
                '$project' => [
                    'prices' => ['$objectToArray' => '$prices'],
                    'product' => '$product'
                ]
            ],
            [
                '$unwind' => [
                    'path' => '$prices',
                ]
            ],
            [
                '$match' => [
                    'prices.v.point' => ['$in' => $ids]
                ]
            ],
            [
                '$group' => [
                    '_id' => [
                        'point' => '$prices.v.point',
                        'product' => '$product'
                    ],
                    'hide' => [
                        '$addToSet' => '$prices.v.hide'
                    ],
                ]
            ]
        ]);



        $ids = [];

        foreach ($prices as $price) {
            if ($price['_id']['point']) {
                if (!isset(self::$results[$price['_id']['product']])) self::$results[$price['_id']['product']] = [];
                $ids[$price['_id']['product']][] = $price['_id']['point'];
                self::$results[$price['_id']['product']][] = [
                    "id" => $price['_id']['point'],
                    'hide' => in_array(false, (array)$price['hide']) ? false : true
                ];
            }
        }

        foreach ($ids as $product => $idVal) {
            $points = DB::makeArray(DB::query("SELECT p.id as id, p.name, 0 as hide FROM `app_partner_points` p  WHERE p.partner = " . Auth::$user['id'] . " AND p.id NOT IN (" . implode(',', $idVal) . ")"));
            if (!$points) $points = [];
            self::$results[$product] = array_merge(self::$results[$product], $points);
        }
    }



    public static function add($id)
    {
        if (in_array((int)$id, self::$ids)) return;
        self::$ids[] = (int)$id;
    }


    public static function get($id)
    {
        if (!isset(self::$results[$id])) {
            return DB::makeArray(DB::query("SELECT p.id as id, p.name, 0 as hide FROM `app_partner_points` p  WHERE p.partner = " . Auth::$user['id']));
        }
        return self::$results[$id];
    }
}