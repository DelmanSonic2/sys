<?php

namespace Support;

use GraphQL\Error\Error;
use Support\DB;



//Метод авторизации для GraphQL
class Auth
{

    public static $request = [];
    public static $country = 'ru';
    public static $user = [];



    public static function getProfile()
    {

        $body_json = json_decode(file_get_contents('php://input'), true);

        $request = $_REQUEST;
        if ($body_json != null) {
            $request = array_merge($_REQUEST, $body_json);
        }

        foreach ($request['fields'] as $key => $val) {
            if (is_string($val))
                $request[$key] = trim($val);
        }


        $token = $request['token'];

        $headers = apache_request_headers();

        if ($headers['token']) {
            $token = $headers['token'];
        }


        //Проверям чей токен

        $partnerToken =  DB::getRow(DB::select('*', 'app_partners_token', "token='{$token}'", "", "1"));
        $employeeToken =  DB::getRow(DB::select('*', 'app_employees', "token='{$token}'", "", "1"));


        if (isset($partnerToken['partner'])) {
            $partnerData = DB::getRow(DB::query("SELECT p.id, p.admin, p.login, p.name,  c.name as city FROM `app_partner` p JOIN `app_cities` c ON c.`id` = p.`city` WHERE p.id=" . $partnerToken['partner'] . " LIMIT 1"));
            return [
                'partner' => $partnerData['id'],
                'login' =>  $partnerData['login'],
                'name' => $partnerData['name'],
                'city' => $partnerData['city'],
                'admin' => $partnerData['admin'] == 1 ? true : false
            ];
        } else if (isset($employeeToken['id'])) {

            $employeeData = DB::getRow(DB::query("SELECT p.id, e.email as login, e.name, c.name as city FROM `app_partner` p JOIN `app_cities` c ON c.`id` = p.`city` JOIN `app_employees` e ON e.`partner`=p.id WHERE e.id=" . $employeeToken['id'] . " LIMIT 1"));

            return [
                'partner' => $employeeData['id'],
                'login' =>  $employeeData['login'],
                'name' => $employeeData['name'],
                'city' => $employeeData['city'],
                'admin' => false
            ];
        }
    }



    public static function authUser()
    {

        $body_json = json_decode(file_get_contents('php://input'), true);

        $request = $_REQUEST;
        if ($body_json != null) {
            $request = array_merge($_REQUEST, $body_json);
        }

        foreach ($request['fields'] as $key => $val) {
            if (is_string($val))
                $request[$key] = trim($val);
        }


        $subdomain = isset($request['subdomain']) ? $request['subdomain'] : 'ru';
        if (!in_array($subdomain, ['kz', 'br', 'ru'])) $subdomain = 'ru';


        self::$country = $subdomain;


        $token = $request['token'];

        $headers = apache_request_headers();

        if ($headers['token']) {
            $token = $headers['token'];
        }

        if ($token) {


            //Инфо о партнере по токену самого партнера
            $partnerData = DB::query('SELECT p.*, c.code, mc.currency
                                FROM ' . DB_PARTNERS_TOKEN . ' pt
                                JOIN ' . DB_PARTNER . ' AS p ON p.id = pt.partner
                                JOIN ' . DB_CITIES . ' AS c ON c.id = p.city
                                LEFT JOIN ' . DB_MONEY_CURRENCIES . ' mc ON mc.id = p.currency
                                WHERE pt.token = "' . $token . '"');

            //Инфо о партнере по токену сотрудника
            $employeeData = DB::query('SELECT p.*, e.id AS employee, e.name as name, ps.execute_inventory, ps.terminal, ps.statistics, ps.finance AS finances, ps.menu, ps.warehouse, ps.marketing, ps.cashbox, ps.accesses, ps.root, c.code, mc.currency
                                    FROM ' . DB_EMPLOYEES . ' e
                                    JOIN ' . DB_POSITIONS . ' ps ON ps.id = e.position
                                    JOIN ' . DB_PARTNER . ' AS p ON p.id = e.partner
                                    JOIN ' . DB_CITIES . ' AS c ON c.id = p.city
                                    LEFT JOIN ' . DB_MONEY_CURRENCIES . ' mc ON mc.id = p.currency
                                    WHERE e.token = "' . $token . '"');

            if (DB::getRecordCount($partnerData) == 0 && DB::getRecordCount($employeeData) == 0) {
                return false;
            } else {



                $partnerExist = DB::getRecordCount($partnerData);

                $userToken = ($partnerExist == 0) ? DB::getRow($employeeData) :  DB::getRow($partnerData);

                if ($partnerExist == 0) {

                    $userToken['global_admin'] = false;
                    $userToken['parent'] = $userToken['parent'] == null ? $userToken['id'] : $userToken['parent'];
                    $userToken['partner'] = $userToken['parent'] == null ? $userToken['id'] : $userToken['parent'];
                    DB::update(array('last_enter' => time()), DB_EMPLOYEES, 'id = ' . $userToken['employee']);
                    $userToken['execute_inventory'] = (bool)$userToken['execute_inventory'];
                } else {
                    $userToken['global_admin'] = ($userToken['admin']) ? true : false;
                    DB::update(array('last_active' => time()), DB_PARTNER, 'id = ' . $userToken['id']);
                    $userToken['parent'] = $userToken['parent'] == null ? $userToken['id'] : $userToken['parent'];
                    $userToken['partner'] = $userToken['parent'] == null ? $userToken['id'] : $userToken['parent'];
                    $userToken['execute_inventory'] = true;
                    $userToken['employee'] = false;
                }

                if ($userToken['code']) date_default_timezone_set($userToken['code']);
                else date_default_timezone_set('Europe/Moscow');


                self::$user = $userToken;
                return true;
            }
        } else {
            return false;
        }
    }
}