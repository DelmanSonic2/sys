<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

$json = json_decode(file_get_contents("php://input"), true);

$token = DB::escape($_REQUEST['token']) ? DB::escape($_REQUEST['token']) : $json['token'];

if (!$token)
    response('error', array('msg' => 'Приватный метод.'), '2');

$pointToken = DB::query('SELECT p.id, p.partner, prt.admin, c.code
                                FROM ' . DB_PARTNER_POINTS . ' p
                                JOIN ' . DB_POINTS_TOKEN . ' AS t ON t.point = p.id
                                JOIN ' . DB_PARTNER . ' AS prt ON prt.id = p.partner
                                JOIN ' . DB_CITIES . ' c ON c.id = prt.city
                                WHERE t.token = "' . $token . '"');

if (DB::getRecordCount($pointToken) == 0)
    response('error', array('msg' => 'Неверный токен.'), '9');

$pointToken = DB::getRow($pointToken);

date_default_timezone_set($pointToken['code']);
