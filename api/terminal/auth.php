<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';
include ROOT . 'api/lib/functions.php';

if (!$login = DB::escape($_REQUEST['login']))
    response('error', array('msg' => 'Введите логин.'), '510');

if (!$password = DB::escape($_REQUEST['password']))
    response('error', array('msg' => 'Введите пароль.'), '511');

$ip = GetIP();

$pointData = DB::query('SELECT p.id, p.password, t.token, p.partner
                                FROM ' . DB_PARTNER_POINTS . ' p
                                LEFT JOIN ' . DB_POINTS_TOKEN . ' AS t ON t.point = p.id
                                WHERE p.login = "' . $login . '"');

if (DB::getRecordCount($pointData) == 0)
    response('error', array('msg' => 'Неверный логин или пароль.'), '310');

$pointData = DB::getRow($pointData);

if (!password_verify($password, $pointData['password']))
    response('error', array('msg' => 'Неверный логин или пароль.'), '310');

$gen_token = hash('sha512', 'coffee' . time() . $login . 'way' . date('dmy', time() + rand(10, 99)) . 'terminal');

if ($pointData['token'] != null)
    response('success', array('token' => $pointData['token'], 'currency' => CURRENCY, 'round_price' => ROUND_PRICE), '102');

$fields = array(
    'partner' => $pointData['partner'],
    'point' => $pointData['id'],
    'token' => $gen_token,
    'created' => time(),
    'ip_address' => $ip
);

if (DB::insert($fields, DB_POINTS_TOKEN))
    response('success', array('token' => $gen_token, 'currency' => CURRENCY), '102');
else
    response('error', '', '503');


/*if(!$pin_code = DB::escape($_REQUEST['pin_code']))
    response('error', array('msg' => 'Введите ПИН-код для доступа к терминалу'), '381');

if(!ctype_digit($pin_code))
    response('error', array('msg' => 'ПИН-код должен состоять только из цифр.'), '382');

if(strlen($pin_code) != 6)
    response('error', array('msg' => 'ПИН-код должен состоять из 6 символов'), '383');

if(!$point = DB::escape($_REQUEST['point']))
    response('error', array('msg' => 'Не передан ID заведения.'), '329');

$shiftData = DB::query('SELECT e.id, e.name, sh.token, sh.token_created, sh.shift_closed, sh.shift_from, sh.id AS shid
                                FROM '.DB_EMPLOYEES.' e
                                LEFT JOIN '.DB_EMPLOYEE_SHIFTS.' AS sh ON sh.employee = e.id
                                WHERE e.pin_code = '.$pin_code.'
                                ORDER BY sh.id DESC
                                LIMIT 1');

if(DB::getRecordCount($shiftData) == 0)
    response('error', array('msg' => 'Неверный ПИН-код.'), '388');

$shiftData = DB::getRow($shiftData);

//Генерируем токен с помощью ID кассира
$gen_token = hash('md5', 'coffee'.$shiftData['id'].'way'.time());

//Если последняя смена закрыта, или не существует
if($shiftData['token'] == null || $shiftData['shift_closed'] == 1){

    $fields = array('employee' => $shiftData['id'],
                    'shift_from' => time(),
                    'token' => $gen_token,
                    'point' => $point,
                    'token_created' => time(),
                    'token_updated' => time());

    if(!DB::insert($fields, DB_EMPLOYEE_SHIFTS))
                    response('error', '', '503');

    response('success', array('token' => $gen_token), '102');

}
//Если смена существует больше 24 часов
if((time() - $shiftData['shift_from'] > 60 * 60 * 24) && $shiftData['token'] != null){

    ShiftClose(DB_EMPLOYEE_SHIFTS, $shiftData['shid']);

    $fields = array('employee' => $shiftData['id'],
                    'shift_from' => time(),
                    'token' => $gen_token,
                    'point' => $point,
                    'token_created' => time(),
                    'token_updated' => time());

    if(!DB::insert($fields, DB_EMPLOYEE_SHIFTS))
        response('error', '', '503');

    response('success', array('token' => $gen_token), '102');

}
//Если смена ещё не закончилась
else{
    
    DB::update(array('token' => $gen_token, 'token_updated' => time()), DB_EMPLOYEE_SHIFTS, 'id = '.$shiftData['shid']);

    response('success', array('token' => $gen_token), '102');

}*/