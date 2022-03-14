<?php

namespace Controllers\GraphQL\Buffers;


use Support\DB;

class ItemPriceBuffer
{

    private static $ids = array();
    private static $point = 0;

    private static $results = array();

    public static function load()
    {

        if (!empty(self::$results)) return;

     
        
        $rows = DB::makeArray(DB::query("SELECT * FROM app_point_items WHERE item IN (" . implode(',', self::$ids) . ") AND point=".self::$point));
        foreach ($rows as $row) {
            self::$results[$row['item']] = $row;
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
