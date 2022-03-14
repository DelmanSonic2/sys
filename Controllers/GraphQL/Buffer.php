<?php

namespace Controllers\GraphQL;

use Support\Auth;
use Support\DB;

class Buffer
{

    private static $ids = array();

    private static $results = array();

    public static function load($table, $id_key, $array = false)
    {

        if (!empty(self::$results[$table . $id_key])) return;


        //Добавляем фильтр по партнеру если такой есть в таблице 
        $where = "";
        if (DB::haveField($table, ['partner'])) {
            $where = " AND (partner=" . Auth::$user['id'] . " OR partner IS NULL OR partner = 0)";
        }


        $rows = DB::makeArray(DB::select("*", $table, "$id_key IN (" . implode(',', self::$ids[$table . $id_key]) . ") {$where}"));
        foreach ($rows as $row) {
            if ($array)
                self::$results[$table . $id_key][$row[$id_key]][] = $row;
            else
                self::$results[$table . $id_key][$row[$id_key]] = $row;
        }
    }

    public static function addArray($table, $id_key, $ids)
    {
        if (count(array_diff($ids, self::$ids)) == 0) return;
        self::$ids[$table . $id_key] = array_merge(self::$ids[$table . $id_key], array_diff($ids, self::$ids));
    }

    public static function add($table, $id_key,  $id)
    {
        if (in_array($id, self::$ids)) return;
        self::$ids[$table . $id_key][] = $id;
    }

    public static function getArray($table, $id_key, $ids)
    {
        $result = [];
        foreach ($ids as $id) {
            $result[] = self::$results[$table . $id_key][$id];
        }
        return $result;
    }

    public static function get($table, $id_key, $id)
    {
        if (!isset(self::$results[$table . $id_key][$id])) return null;
        return self::$results[$table . $id_key][$id];
    }
}
