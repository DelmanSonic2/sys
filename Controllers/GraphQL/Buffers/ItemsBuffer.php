<?php

namespace Controllers\GraphQL\Buffers;

use Support\DB;

class ItemsBuffer
{

    private static $ids = array();
    public static $table = "app_items";
    private static $results = array();

    public static function load($id_key, $array = false)
    {

        if (!empty(self::$results[self::$table . $id_key])) {
            return;
        }

        $rows = DB::makeArray(DB::select("*", self::$table, "$id_key IN (" . implode(',', self::$ids[self::$table . $id_key]) . ")"));
        foreach ($rows as $row) {
            if ($array) {
                self::$results[self::$table . $id_key][$row[$id_key]][] = $row;
            } else {
                self::$results[self::$table . $id_key][$row[$id_key]] = $row;
            }

        }
    }

    public static function addArray($id_key, $ids)
    {
        if (count(array_diff($ids, self::$ids)) == 0) {
            return;
        }

        self::$ids[self::$table . $id_key] = array_merge(self::$ids[self::$table . $id_key], array_diff($ids, self::$ids));
    }

    public static function add($id_key, $id)
    {
        if (in_array($id, self::$ids)) {
            return;
        }

        self::$ids[self::$table . $id_key][] = $id;
    }

    public static function getArray($id_key, $ids)
    {
        $result = [];
        foreach ($ids as $id) {
            $result[] = self::$results[self::$table . $id_key][$id];
        }
        return $result;
    }

    public static function get($id_key, $id)
    {
        if (!isset(self::$results[self::$table . $id_key][$id])) {
            return null;
        }

        return self::$results[self::$table . $id_key][$id];
    }
}