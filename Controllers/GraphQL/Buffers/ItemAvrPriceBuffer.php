<?php

namespace Controllers\GraphQL\Buffers;

use Support\Auth;
use Support\DB;

class ItemAvrPriceBuffer
{

    private static $ids = array();


    private static $results = array();

    public static function load()
    {

        if (!empty(self::$results)) return;


        $rows = DB::makeArray(DB::query("SELECT i.id, AVG(pi.price) AS avg_price
        FROM " . DB_ITEMS . " i
        LEFT JOIN " . DB_POINT_ITEMS . " AS pi ON pi.item = i.id AND pi.partner = " . Auth::$user['id'] . "
        AND i.id IN (" . implode(',', self::$ids) . ")
        GROUP BY i.id"));


        foreach ($rows as $row) {
            self::$results[$row['id']] = $row['avg_price'];
        }
    }



    public static function add($id)
    {
        if (in_array($id, self::$ids)) return;
        self::$ids[] = $id;
    }


    public static function get($id)
    {
        if (!isset(self::$results[$id])) return null;
        return self::$results[$id];
    }
}
