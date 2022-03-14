<?php

namespace Support;



class DB
{

    protected static $MySQL;




    public static function errorInspector($sql = "")
    {


        if (self::getLastError()) {


            $file_name = "DB_" . date('Y-m-d') . '.txt';
            $log = date('Y-m-d H:i:s') . ' |URL - ' . $_SERVER['REQUEST_URI'] . '| DB ERROR - ' . self::getLastError() . ' ' . $sql;



            file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/$file_name", $log . PHP_EOL, FILE_APPEND);

            @file_get_contents("https://loger.apiloc.ru/loger/cw?msg=" . urlencode("CW DB Error - " . $log . " Request" . json_encode($_REQUEST)));

            /*  Utils::response("error",[
                "msg"=>"Ошибка сервера"
            ],3);
         */
        }
    }


    public static function  disconnect()
    {
    }

    public static function connect()
    {

        self::$MySQL = mysqli_connect($_ENV['DB_SERVER'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], $_ENV['DB_NAME']);
        mysqli_set_charset(self::$MySQL, "utf8");
        if (mysqli_connect_errno()) {
            echo 'no database connect';
            exit;
        }
    }


    public static function getValue($dsq)
    {
        if (!is_resource($dsq))
            $dsq = self::query($dsq);
        if ($dsq) {
            $r =  self::getRow($dsq, "num");
            return $r[0];
        }
    }


    public static function escape($s)
    {
        if (gettype($s) == 'string')
            $s = mysqli_real_escape_string(self::$MySQL, trim($s));
        return $s;
    }


    public static function getRow($ds, $mode = 'assoc')
    {
        if ($ds) {
            if ($mode == 'assoc') {
                return mysqli_fetch_assoc($ds);
            } elseif ($mode == 'num') {
                return mysqli_fetch_row($ds);
            }
            /* elseif ($mode == 'both') {
                return mysqli_fetch_array($ds, MYSQL_BOTH);
            }*/
        }
    }



    public static function getError()
    {
        return mysqli_errno(self::$MySQL);
    }


    public static function query($sql)
    {
        $result = mysqli_query(self::$MySQL, $sql);



        self::errorInspector($sql);


        return $result;
    }


    public static function select($fields = "*", $from = "", $where = "", $orderby = "", $limit = "")
    {
        if (!$from) {
            echo 'Ошибка при работе с Базой.';
            exit;
        } else {
            $table = $from;
            $where = ($where != "") ? "WHERE $where" : "";
            $orderby = ($orderby != "") ? "ORDER BY $orderby " : "";
            $limit = ($limit != "") ? "LIMIT $limit" : "";

            $result = self::query("select $fields FROM $table $where $orderby $limit");

            self::errorInspector("select $fields FROM $table $where $orderby $limit");

            return $result;
        }
    }

    public static function makeArray($rs = '')
    {
        if (!$rs) return false;
        $rsArray = array();
        $qty = self::getRecordCount($rs);

        if ($qty == 0) return [];

        for ($i = 0; $i < $qty; $i++) $rsArray[] = self::getRow($rs);
        return $rsArray;
    }

    public static function makeArrayWithKey($rs = '', $key_name = false)
    {
        if (!$key_name)  return false;
        if (!$rs) return false;
        $rsArray = array();
        $qty = self::getRecordCount($rs);
        for ($i = 0; $i < $qty; $i++) {
            $row = self::getRow($rs);
            $rsArray[$row[$key_name]] = $row;
        }
        return $rsArray;
    }


    public static function getLastError()
    {
        return mysqli_error(self::$MySQL);
    }


    public static function getRecordCount($result)
    {
        $row_cnt = mysqli_num_rows($result);
        return $row_cnt;
    }

    public static function update($fields, $table, $where = "")
    {

        if (!$table)
            return false;
        else {
            if (!is_array($fields))
                $flds = $fields;
            else {
                $flds = '';
                foreach ($fields as $key => $value) {
                    if (!empty($flds))
                        $flds .= ",";
                    $flds .= "`" . $key . "` =";
                    if ($value == "NULL")
                        $flds .=  $value;
                    else
                        $flds .= "'" . $value . "'";
                }
            }
            $where = ($where != "") ? "WHERE $where" : "";

            $result = self::query("UPDATE $table SET $flds $where");


            self::errorInspector("UPDATE $table SET $flds $where");



            return $result;
        }
    }


    public static function getInsertId()
    {
        return mysqli_insert_id(self::$MySQL);
    }


    public static function getTableMetaData($table)
    {
        $metadata = false;
        if (!empty($table)) {
            $sql = "SHOW FIELDS FROM $table";
            if ($ds = self::query($sql)) {
                while ($row = self::getRow($ds)) {
                    $fieldName = $row['Field'];
                    $metadata[$fieldName] = $row;
                }
            }
        }
        return $metadata;
    }

    public static function delete($from, $where = '', $fields = '')
    {
        if (!$from)
            return false;
        else {
            $table = $from;
            $where = ($where != "") ? "WHERE $where" : "";
            return self::query("DELETE $fields FROM $table $where");
        }
    }

    public static   function getColumn($name, $dsq)
    {
        $col = array();
        while ($row = self::getRow($dsq)) {
            $col[] = $row[$name];
        }
        return $col;
    }


    public static function haveField($table, $fields = [])
    {


        $db_info = (array)self::getTableMetaData($table);

        $have_count = 0;

        foreach ($db_info as $db_info_key => $db_info_item)
            if (in_array($db_info_key, $fields)) $have_count++;


        return $have_count == count($fields);
    }

    public static function insert($fields, $intotable, $fromfields = "*", $fromtable = "", $where = "", $limit = "")
    {

        if (!$intotable)
            return false;
        else {

            $sql = "";

            if (!is_array($fields))
                $flds = $fields;
            else {
                $keys = array_keys($fields);
                $values = array_map(function ($a) {
                    $a = self::escape($a);
                    if ($a != "NULL")
                        $a = "'" . $a . "'";

                    return  $a;
                }, array_values($fields));

                $flds = "(" . implode(",", $keys) . ") " . (!$fromtable && $values ? "VALUES(" . implode(",", $values) . ")" : "");

                if ($fromtable) {
                    $where = ($where != "") ? "WHERE $where" : "";
                    $limit = ($limit != "") ? "LIMIT $limit" : "";
                    $sql = "select $fromfields FROM $fromtable $where $limit";
                }
            }

            $rt = self::query("INSERT INTO $intotable $flds $sql");
            $lid = self::getInsertId();

            self::errorInspector("INSERT INTO $intotable $flds $sql");





            return $lid ? $lid : $rt;
        }
    }
}