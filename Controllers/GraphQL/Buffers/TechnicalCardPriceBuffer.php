<?php

namespace Controllers\GraphQL\Buffers;


use Support\DB;

class TechnicalCardPriceBuffer
{

    private static $ids = array();
    private static $point = 0;

    private static $results = array();

    public static function load()
    {

        if (!empty(self::$results)) return;




        $rows = DB::makeArray(DB::select("*", 'app_product_prices', "point=" . self::$point . " AND `technical_card` IN (" . implode(',', self::$ids) . ")"));

        foreach ($rows as $row) {
            self::$results[$row['technical_card']] = $row['price'];
        }
    }



    public static function add($point, $id)
    {
        self::$point = $point;
        if (in_array($id, self::$ids)) return;
        self::$ids[] = $id;
    }


    public static function get($id)
    {
        if (!isset(self::$results[$id])) return null;
        return self::$results[$id];
    }
}