<?php

namespace Controllers\GraphQL\Buffers;


use Support\DB;

class PointsBuffer
{

    private static $ids = array();

    private static $results = array();

    public static function load()
    {

        if (!empty(self::$results)) return;

        $rows = DB::makeArray(DB::query("SELECT * FROM app_partner_points WHERE id IN (" . implode(',', self::$ids) . ")"));
        foreach ($rows as $row) {
            self::$results[(int)$row['id']] = $row;
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