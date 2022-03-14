<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

if (!$pin_code = DB::escape($_REQUEST['pin_code']))
    response('error', array('msg' => 'Введите ПИН-код для доступа к терминалу'), '381');

if (!ctype_digit($pin_code))
    response('error', array('msg' => 'ПИН-код должен состоять только из цифр.'), '382');

if (strlen($pin_code) != 6)
    response('error', array('msg' => 'ПИН-код должен состоять из 6 символов'), '383');

$shiftData = DB::query('SELECT e.id, e.name, sh.token, sh.token_created
                                FROM ' . DB_EMPLOYEES . ' e
                                LEFT JOIN ' . DB_EMPLOYEE_SHIFTS . ' AS sh ON sh.employee = e.id
                                WHERE e.pin_code = ' . $pin_code);

$shiftData = DB::getRow($shiftData);

echo json_encode($shiftData);
