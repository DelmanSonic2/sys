<?php

use Support\Pages;
use Support\DB;



include ROOT . 'api/lib/response.php';
include ROOT . 'api/lib/functions.php';

if (!$login = DB::escape($_REQUEST['login']))
    response('error', array('msg' => 'Введите логин.'), '301');

if (!$password = DB::escape($_REQUEST['password']))
    response('error', array('msg' => 'Введите пароль.'), '302');

if (strlen($password) < 8)
    response('error', array('msg' => 'Пароль не может содержать меньше 8 символов.'), '303');

$pattern = "/[\\\~^°\"\/`';,\.:_{\[\]}\|<>]/";

if (preg_match($pattern, $password, $matches))
    response('error', array('msg' => 'В пароле присутствуют недопустимые символы: "' . $matches[0] . '".'), '304');

//Получаем информацию о партнере
$partner_info = DB::select('*', DB_PARTNER, 'login = "' . $login . '"');

//Если партнер с таким логином найден
if (DB::getRecordCount($partner_info) != 0) {

    $partner_info = DB::getRow($partner_info);

    if (password_verify($password, $partner_info['password'])) {

        $fields = array(
            'token' => hash('sha512', 'coffee' . $partner_info['login'] . 'way' . time()),
            'ip_address' => GetIP(),
            'partner' => $partner_info['id'],
            'createdon' => time()
        );

        /*         $token_data = DB::select('*', DB_PARTNERS_TOKEN, 'partner = ' . $partner_info['id'] . ' AND ip_address = "' . $fields['ip_address'] . '"');

        if (DB::getRecordCount($token_data) == 0) */
        DB::insert($fields, DB_PARTNERS_TOKEN);
        /*         else {

            $token_data = DB::getRow($token_data);

            DB::update(array('createdon' => time(), 'token' => $fields['token']), DB_PARTNERS_TOKEN, 'partner = ' . $partner_info['id'] . ' AND ip_address = "' . $fields['ip_address'] . '"');

        } */

        response(
            'success',
            array(
                'login' => $partner_info['login'],
                'token' => $fields['token'],
                'partner' => true,
                'terminal' => true,
                'statistics' => true,
                'finance' => true,
                'menu' => true,
                'warehouse' => true,
                'marketing' => true,
                'cashbox' => true,
                'accesses' => true,
                'currency' => CURRENCY,
                'round_price' => ROUND_PRICE,
                'user_type' => 'partner',
                'administration' => (bool)$partner_info['admin']
            ),
            '102'
        );
    }
}

//Получаем информацию о сотруднике
$employeeData = DB::query('SELECT e.id, e.password, prt.login, p.terminal, p.statistics, p.finance, p.menu, p.warehouse, p.marketing, p.cashbox, p.accesses
                                FROM ' . DB_EMPLOYEES . ' e
                                JOIN ' . DB_PARTNER . ' prt ON prt.id = e.partner
                                JOIN ' . DB_POSITIONS . ' AS p ON p.id = e.position
                                WHERE e.email = "' . $login . '" AND e.deleted = 0');

//Если сотрудник найден
if (DB::getRecordCount($employeeData) != 0) {

    $employeeData = DB::getRow($employeeData);

    if (password_verify($password, $employeeData['password'])) {

        $token = hash('sha512', 'coffee' . $employeeData['login'] . 'way' . time());

        if (DB::update(array('token' => $token), DB_EMPLOYEES, 'id = "' . $employeeData['id'] . '"')) {

            response(
                'success',
                array(
                    'login' => $employeeData['login'],
                    'token' => $token,
                    'partner' => false,
                    'terminal' => (bool)$employeeData['terminal'],
                    'statistics' => (bool)$employeeData['statistics'],
                    'finance' => (bool)$employeeData['finance'],
                    'menu' => (bool)$employeeData['menu'],
                    'warehouse' => (bool)$employeeData['warehouse'],
                    'marketing' => (bool)$employeeData['marketing'],
                    'cashbox' => (bool)$employeeData['cashbox'],
                    'accesses' => (bool)$employeeData['accesses'],
                    'currency' => CURRENCY,
                    'round_price' => ROUND_PRICE,
                    'root' => (bool)$employeeData['root'],
                    'user_type' => ($employeeData['root']) ? 'partner' : 'employee',
                    'administration' => false
                ),
                '102'
            );
        }
    }
}


response('error', array('msg' => 'Неверный логин или пароль.'), '310');
