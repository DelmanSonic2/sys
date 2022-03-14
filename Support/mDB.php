<?php

namespace Support;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\BulkWriteResult;

class mDB
{

    protected static $client;
    public static $collection;

    public static function connect()
    {



        $params = [];

        if ($_ENV['MONGODB_DATABASE_USERNAME']) {
            $params = [
                "username" =>  $_ENV['MONGODB_DATABASE_USERNAME'],
                "authSource" => $_ENV['MONGODB_AUTHSOURCE'],
                "password" => strrev($_ENV['MONGODB_DATABASE_PASSWORD']),
                "connectTimeoutMS" => 360000,
                "socketTimeoutMS" => 360000,
                'poolSize' => 1000
            ];
        }

        $server = "mongodb://localhost:27017";
        if (isset($_ENV['MONGODB_SERVER'])) {
            $server = $_ENV['MONGODB_SERVER'];
        }



        self::$client = new Client($server, $params);

        $db = $_ENV['MONGODB_DATABASE'];
        self::$collection = self::$client->$db;


        self::$client->listDatabases();
    }

    public static function idInArray($id, $array = [], $keyInArray = "_id")
    {
        foreach ($array as $val) if ((string)$val[$keyInArray] == (string)$id) return true;
        return false;
    }


    public static function idInArrayIds($id, $array = [])
    {
        foreach ($array as $val) if ((string)$val == (string)$id) return true;
        return false;
    }


    public static function unixToDate($val)
    {
        return new \MongoDB\BSON\UTCDateTime($val * 1000);
    }


    public static function id($val)
    {
        if (gettype($val) == 'string') return self::getId($val);
        else return $val;
    }


    public static function getId($id = false)
    {

        if (preg_match('/^[0-9a-f]{24}$/i', $id) === 1) {
            return $id ? new \MongoDB\BSON\ObjectID($id) : new \MongoDB\BSON\ObjectID();
        } else {
            return false;
        }
    }


    public static function replacingId($array)
    {
        /*  if(isset($filter['_id']) && gettype($filter['_id']) == 'string') 
        $filter['_id'] = self::getId($filter['_id']);
        return $filter;*/


        foreach ($array as $key => $element) {
            if (is_array($element)) {
                $array[$key] = self::replacingId($element);
            } else if ($key === '_id' && gettype($element) === "string") {
                $array[$key] = self::getId($element);
            }
        }

        return  $array;
    }

    public static function toArray($result)
    {
        foreach ($result as $document) {
            $tmp[] = $document;
        }
        return $tmp;
    }




    public static function collection($collection): Collection
    {

        return  self::$collection->$collection;
    }




    public static function normalization($array)
    {
        foreach ($array as $element) {
            if (is_array($element)) {
                $element = self::normalization($element);
            } else if (is_object($element) && get_class($element) == "MongoId") {
                $element = (string) $element;
            }
        }
        return $array;
    }
}