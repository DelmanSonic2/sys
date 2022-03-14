<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

switch($action){

    case 'add':

        if(!$date = DB::escape($_REQUEST['date']))
            response('error', array('msg' => 'Выберите дату.'), '528');
        
        if(!$plan = DB::escape($_REQUEST['plan']))
            response('error', array('msg' => 'Введите значение плана.'), '529');

        if(!$point = DB::escape($_REQUEST['point']))
            response('error', array('msg' => 'Не передан ID заведения.'), '329');

        $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$userToken['id'].' AND id = '.$point);
        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

        $fields = array('point' => $point,
                        'partner' => $userToken['id'],
                        'unix' => $date,
                        'date' => date('Y-m-d', $date),
                        'created' => time(),
                        'plan' => $plan);

        if(DB::insert($fields, DB_POINTS_PLAN))
            response('success', array('msg' => 'Позиция добавлена.'), '635');
        else
            response('error', '', '503');

    break;

    case 'get':

        if(!$dateFrom = DB::escape($_REQUEST['from'])){
            $dateFrom = date('Y-m', time());
            $dateFrom = strtotime($dateFrom);
        }

        if(!$dateTo = DB::escape($_REQUEST['to'])){
            $dateTo = strtotime('+1 month', $dateFrom);
        }

        if($point = DB::escape($_REQUEST['point']))
            $where = ' AND p.point = '.$point;

        $planData = DB::query('SELECT p.id, p.unix, p.date, p.point, p.plan, pp.name
                                        FROM '.DB_POINTS_PLAN.' p
                                        JOIN '.DB_PARTNER_POINTS.' AS pp ON pp.id = p.point
                                        WHERE p.partner = '.$userToken['id'].' AND p.unix >= '.$dateFrom.' AND p.unix < '.$dateTo.$where.'
                                        ORDER BY p.unix, pp.name');

        $result = [];

        while($row = DB::getRow($planData)){

            $row['point'] = array('id' => $row['point'],
                                    'name' => $row['name']);

            unset($row['name']);
            $result[] = $row;

        }

        response('success', $result, '7');

    break;

    case 'delete':

        if(!$position = DB::escape($_REQUEST['position']))
            response('error', array('msg' => 'Выберите позицию.'), '530');

        if(DB::delete(DB_POINTS_PLAN, 'id = '.$position.' AND partner = '.$userToken['id']))
            response('success', array('msg' => 'Позиция удалена.'), '636');
        else
            response('error', '', '503');

    break;

    case 'edit':

        $fields = [];

        if(!$position = DB::escape($_REQUEST['position']))
            response('error', array('msg' => 'Выберите позицию.'), '530');

        if($date = DB::escape($_REQUEST['date'])){
            $fields['unix'] = $date;
            $fields['date'] = date('Y-m-d', $date);
        }
        
        if($plan = DB::escape($_REQUEST['plan']))
            $fields['plan'] = $plan;

        if($point = DB::escape($_REQUEST['point'])){

            $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$userToken['id'].' AND id = '.$point);
            if(DB::getRecordCount($pointData) == 0)
                response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

            $fields['point'] = $point;

        }

        if(DB::update($fields, DB_POINTS_PLAN, 'id = '.$position.' AND partner = '.$userToken['id']))
            response('success', array('msg' => 'Информация обновлена.'), '637');
        else
            response('error', '', '503');

    break;

}