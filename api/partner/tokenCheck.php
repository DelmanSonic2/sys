<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

if (!$token = DB::escape($_REQUEST['token']))
    response('error', array('msg' => 'Приватный метод.'), '2');

//Инфо о партнере по токену самого партнера
$partnerData = DB::query('SELECT p.*, c.code, mc.currency
                                FROM ' . DB_PARTNERS_TOKEN . ' pt
                                JOIN ' . DB_PARTNER . ' AS p ON p.id = pt.partner
                                JOIN ' . DB_CITIES . ' AS c ON c.id = p.city
                                LEFT JOIN ' . DB_MONEY_CURRENCIES . ' mc ON mc.id = p.currency
                                WHERE pt.token = "' . $token . '"');

//Инфо о партнере по токену сотрудника
$employeeData = DB::query('SELECT p.*, e.id AS employee, ps.execute_inventory, ps.terminal, ps.statistics, ps.finance AS finances, ps.menu, ps.warehouse, ps.marketing, ps.cashbox, ps.accesses, ps.root, c.code, mc.currency
                                    FROM ' . DB_EMPLOYEES . ' e
                                    JOIN ' . DB_POSITIONS . ' ps ON ps.id = e.position
                                    JOIN ' . DB_PARTNER . ' AS p ON p.id = e.partner
                                    JOIN ' . DB_CITIES . ' AS c ON c.id = p.city
                                    LEFT JOIN ' . DB_MONEY_CURRENCIES . ' mc ON mc.id = p.currency
                                    WHERE e.token = "' . $token . '"');

if (DB::getRecordCount($partnerData) == 0 && DB::getRecordCount($employeeData) == 0)
    response('error', array('msg' => 'Неверный токен.'), '9');

//Узнаем по какому пути стучится клиент
$path = explode('/', $_REQUEST['q']);

/* $sections = ["terminal", "statistics", "finances", "menu", "warehouse", "marketing", "accesses"]; */

$partnerExist = DB::getRecordCount($partnerData);

$userToken = ($partnerExist == 0) ? DB::getRow($employeeData) :  DB::getRow($partnerData);

if ($partnerExist == 0) {

    $userToken['global_admin'] = false;
    $userToken['parent'] = $userToken['parent'] == null ? $userToken['id'] : $userToken['parent'];
    DB::update(array('last_enter' => time()), DB_EMPLOYEES, 'id = ' . $userToken['employee']);
    $userToken['execute_inventory'] = (bool)$userToken['execute_inventory'];
    /* if(in_array($path[2], $sections)){

        foreach($userToken AS $key => $value){

            if($key == $path[2] && $value != 1)
                response('error', 'У Вас недостаточно прав для просмотра данного раздела.', 1);

        }

    } */
} else {
    $userToken['global_admin'] = ($userToken['admin']) ? true : false;
    DB::update(array('last_active' => time()), DB_PARTNER, 'id = ' . $userToken['id']);
    $userToken['parent'] = $userToken['parent'] == null ? $userToken['id'] : $userToken['parent'];
    $userToken['execute_inventory'] = true;
    $userToken['employee'] = false;
}

date_default_timezone_set($userToken['code']);