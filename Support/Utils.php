<?php

namespace Support;

use Support\DB;
use Aws\S3\S3Client;



class Utils
{

    public static $idLog = false;

    //Функция делает из масства объект с ключами
    public static function ArrayToObjectKey($data, $key = "id")
    {

        $tmp = [];
        foreach ($data as $item)  $tmp[$item[$key]] = $item;

        return $tmp;
    }

    public static function setFeedLog($title)
    {



        $partner = [];

        if (Request::$request['token']) {
            $partner = DB::getRow(DB::query("SELECT p.id as partner FROM `app_partners_token` pt JOIN `app_partner` AS p ON p.id = pt.partner WHERE pt.token = '" . Request::$request['token'] . "'"));
            if (!isset($partner['partner'])) {
                $partner = DB::getRow(DB::query("SELECT `partner`, CONCAT(id,' ',name,' ',email) as employee FROM `app_employees` WHERE token = '" . Request::$request['token'] . "'"));
            }

            if (isset($partner['partner'])) {
                $result =  mDB::collection('feed')->insertOne([
                    "title" => $title,
                    "partner" => isset($partner['partner']) ? intval($partner['partner']) : null,
                    "employee" => isset($partner['employee']) ? $partner['employee'] : null,
                    "country" =>  Request::$country,
                    'url' => $_SERVER['REDIRECT_URL'],
                    "request" => Request::$request,
                    "created_at" => time()
                ], ['continueOnError' => true]);

                self::$idLog = $result->getInsertedId();
            }
        }
    }


    public static function responseValidator($data)
    {


        $msg = implode(',', array_values($data));

        $response = array(
            'type'  => 'error',
            'data' => ['msg' => $msg],
            'code' => 3
        );



        DB::disconnect();

        echo json_encode($response);
        exit;
    }


    public static function response($type, $data, $code, $pageData = null)
    {



        //     if ($data == false || $data == ''){
        //       $data = array('msg' => 'Произошла ошибка, повторите попытку позднее.');
        //      $type = 'error';
        //  }

        if (gettype($data) == 'string')  $data = ['msg' => $data];
        //  $data = is_array($data) ? $data : array('msg' => $data);


        $response = array(
            'type'  => $type,
            'data' => $data,
            'code' => (int)$code
        );


        if ($pageData)
            $response['page'] = $pageData;

        DB::disconnect();


        if (isset(self::$idLog) && self::$idLog !== false) {

            mDB::collection('feed')->updateOne([
                '_id' => self::$idLog,
            ], [
                '$set' => [
                    "response" => $response
                ]
            ]);
        }


        echo json_encode($response);
        exit;
    }


    public static function saveToS3($path, $file)
    {




        // Instantiate an Amazon S3 client.
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => 'us-west-2',
            'endpoint' => 'https://hb.bizmrg.com',
            'credentials' => [
                'key'    => 'fzk2jkrAe7699Ei7tpXcdp',
                'secret' => 'eaXeYW2mPmhqATsrLAJpMjP7HvyoByaT6bLcVsSiaPVz',
            ]
        ]);




        $result =  $s3->putObject([
            'Bucket' => 'file_storage',
            'Key'    => 'cwsystem/' . $file,
            'Body'   => fopen($path, 'r'),
            'ACL'    => 'public-read',
        ]);

        if (isset($result['ObjectURL']))
            return $result['ObjectURL'];
        else return '';
    }


    public static function responsePlain($data)
    {
        DB::disconnect();

        $response = array(
            'data' => $data,
        );

        if (isset(self::$idLog) && self::$idLog !== false) {

            mDB::collection('feed')->updateOne([
                '_id' => self::$idLog,
            ], [
                '$set' => [
                    "response" => $response
                ]
            ]);
        }


        echo json_encode($data);
        exit;
    }
}