<?php

namespace Support;

use Support\DB;




class Request
{

    public static $request = [];
    public static $country = 'ru';


    public static function has($key)
    {
        $has = false;

        if (gettype($key) == 'array') {
            $has = true;
            foreach ($key as $val) {
                if (!isset(self::$request[$val])) $has = false;
            }
        } else {
            $has = isset(self::$request[$key]);
        }
        return $has;
    }

    public static function loadRequest()
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

        self::$request = $request;

        $subdomain = isset(self::$request['subdomain']) ? self::$request['subdomain'] : 'ru';
        if (!in_array($subdomain, ['kz', 'br', 'ru'])) $subdomain = 'ru';


        self::$country = $subdomain;
        Auth::$country = $subdomain;
    }


    public static function authPoint()
    {


        $token = self::$request['token'];

        $headers = apache_request_headers();

        if ($headers['token']) {
            $token = $headers['token'];
        }

        if (!$token)
            Utils::response('error', array('msg' => 'Приватный метод.'), '2');

        //Инфо о партнере по токену самого партнера


        $point =  DB::getRow(DB::query("SELECT p.* FROM `app_partner_points` p JOIN `app_points_token` t WHERE t.point=p.id AND t.token = '{$token}' LIMIT 1"));
        if (!isset($point['id']))    Utils::response('error', array('msg' => 'Приватный метод.'), '2');

        return $point;
    }


    public static function authUser()
    {


        $token = self::$request['token'];

        $headers = apache_request_headers();

        if ($headers['token']) {
            $token = $headers['token'];
        }

        if (!$token)
            Utils::response('error', array('msg' => 'Приватный метод.'), '2');

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
            Utils::response('error', array('msg' => 'Неверный токен.'), '9');



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

        return $userToken;
    }
}