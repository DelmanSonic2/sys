<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';
require_once ROOT.'api/classes/ShiftClass.php';

$date = (DB::escape($_REQUEST['date'])) ? DB::escape($_REQUEST['date']) : time();

if(!$key = DB::escape($_REQUEST['key']))
    response('error', array('msg' => 'Не передан уникальный ключ смены.'), '517');
 
switch($action){

    case 'open':

        if(!$employee = DB::escape($_REQUEST['employee']))
            response('error', array('msg' => 'Выберите сотрудника.'), '516');

        $VerifyData = DB::query('SELECT e.partner, s.id
                                        FROM '.DB_EMPLOYEES.' e
                                        LEFT JOIN '.DB_EMPLOYEE_SHIFTS.' AS s ON s.id = "'.$key.'"
                                        WHERE e.id = '.$employee.' AND  e.partner = '.$pointToken['partner'].' AND e.deleted = 0
                                        LIMIT 1');

        if(DB::getRecordCount($VerifyData) == 0)
            response('error', array('msg' => 'Сотрудник не найден.'), '518');

        $VerifyData = DB::getRow($VerifyData);

        if($VerifyData['partner'] == null)
            response('error', array('msg' => 'Сотрудник не найден.'), '518');

        if($VerifyData['id'] != null)
            response('error', array('msg' => 'Ключ является невалидным.'), '519');

        

        $openShift = DB::query('SELECT id, shift_from, point
                                        FROM '.DB_EMPLOYEE_SHIFTS.'
                                        WHERE id != "'.$key.'" AND shift_closed = 0 AND point = '.$pointToken['id'].' AND employee = '.$employee.'
                                        ORDER BY shift_from DESC
                                        LIMIT 1');

        if(DB::getRecordCount($openShift) != 0){

            $openShift = DB::getRow($openShift);

            if($openShift['point'] == $pointToken['id'])
                response('success', array('msg' => 'Смена продлена.', 'shift' => $openShift['id']), '634');

            response('error', array('msg' => 'У Вас есть незакрытая смена.'), '520');

        }

        $fields = array('id' => $key,
                        'employee' => $employee,
                        'point' => $pointToken['id'],
                        'shift_from' => $date);

        DB::insert($fields, DB_EMPLOYEE_SHIFTS);

        $error_str = DB::getLastError();
        
        if($error_str)
            response('error', '', '503');

        response('success', array('msg' => 'Смена открыта.'), '633');

    break;

    case 'close':

        $shift = new Shift(false, $pointToken['id'], $date);
        
        if($shift->close($key))
            response('success', 'Смена закрыта.', 412);
        else
            response('success', 'Смена закрыта.', 412);
            //response('error', 'Не удалось закрыть смену.', 521);


    break;

}