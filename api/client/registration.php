<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

if (!$phone = DB::escape($_REQUEST['phone']))
    response('error', array('msg' => 'Введите телефон в формате 9XXXXXXXXX.'), '560');

if (!$name = DB::escape($_REQUEST['name']))
    response('error', array('msg' => 'Введите ФИО.'), '560');

if (!$birthdate = DB::escape($_REQUEST['date']))
    response('error', array('msg' => 'Не указана дата рождения.'), '562');

if (!$sex = DB::escape($_REQUEST['sex']))
    response('error', array('msg' => 'Выберите пол.'), '563');


$clientData = DB::select('*', DB_CLIENTS, 'phone = "' . $phone . '"');

if (DB::getRecordCount($clientData) == 0)
    response('error', array('msg' => 'Вы не зарегистрированы в системе лояльности.'), '565');

$clientData = DB::getRow($clientData);

$clientData['group_id'] = ($clientData['group_id'] < 3) ? $clientData['group_id'] + 1 : 3;

$fields = array(
    'name' => $name,
    'birthdate' => $birthdate,
    'sex' => $sex,
    'verified' => 1,
    'group_id' => $clientData['group_id'],
    'activation_link' => ''
);


foreach (DB::escape($_REQUEST) as $key => $value) {

    if ($key == 'email' || $key == 'country' || $key == 'city' || $key == 'address')
        $fields[$key] = $value;
}

if ($clientData['verified'] == 1)
    response('success', array('msg' => 'Ваш акканут уже подтвержден! Спасибо!.'), '643');


if (DB::update($fields, DB_CLIENTS, 'phone = "' . $clientData['phone'] . '"'))
    response('success', array('msg' => 'Аккаунт подтвержден.'), '643');
else
    response('error', '', '503');
