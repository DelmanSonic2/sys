<?php

namespace Controllers\GraphQL\Buffers;

use Support\DB;

class ItemProductPriceOnPointBuffer
{

    private static $points = array();
    private static $items = array();
    private static $products = array();
    private static $results = array();

    public static function load()
    {

        if (!empty(self::$results)) {
            return;
        }

        $rows = DB::makeArray(DB::query("SELECT CONCAT(pi.point,'-',c.product,'-',pi.item) as ppi, pi.point, c.product, pi.item,  SUM(pi.price * IF(c.untils = 'шт', c.count, c.gross)) AS cost_price
        FROM  `app_productions_composition` c
        JOIN  `app_items` AS i ON i.id = c.item
        LEFT JOIN `app_point_items` AS pi ON pi.item = c.item AND pi.point IN (" . implode(',', self::$points) . ")
        WHERE c.product IN (" . implode(',', self::$products) . ") AND pi.item IN (" . implode(',', self::$items) . ")
        GROUP BY pi.point, c.product, pi.item"));

        foreach ($rows as $row) {
            self::$results[$row['ppi']] = $row['cost_price'];
        }
    }

    public static function add($id_point, $id_product, $id_item)
    {
        self::$products[] = $id_product;
        self::$points[] = $id_point;
        self::$items[] = $id_item;

    }

    public static function get($id_point, $id_product, $id_item)
    {
        $ppi = $id_point . "-" . $id_product . "-" . $id_item;

        if (!isset(self::$results[$ppi])) {
            return null;
        }

        return self::$results[$ppi];
    }
}