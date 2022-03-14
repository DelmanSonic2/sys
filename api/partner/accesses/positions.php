<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'create':

        $fields = [];

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите название должности.'), '373');

        $fields['name'] = $name;

        $fields['root'] = DB::escape($_REQUEST['root']) ? 1 : 0;
        $fields['execute_inventory'] = DB::escape($_REQUEST['execute_inventory']) ? 1 : 0;
        $fields['terminal'] = DB::escape($_REQUEST['terminal']) ? 1 : 0;
        $fields['statistics'] = DB::escape($_REQUEST['statistics']) ? 1 : 0;
        $fields['finance'] = DB::escape($_REQUEST['finance']) ? 1 : 0;
        $fields['menu'] = DB::escape($_REQUEST['menu']) ? 1 : 0;
        $fields['warehouse'] = DB::escape($_REQUEST['warehouse']) ? 1 : 0;
        $fields['cashbox'] = DB::escape($_REQUEST['cashbox']) ? 1 : 0;
        $fields['marketing'] = DB::escape($_REQUEST['marketing']) ? 1 : 0;
        $fields['accesses'] = DB::escape($_REQUEST['accesses']) ? 1 : 0;

        if(!$coefficient = DB::escape($_REQUEST['coefficient']))
            $coefficient = 0;

        if(!$rate = DB::escape($_REQUEST['rate']))
            $rate = 60;

        if($coefficient < 0 || $coefficient > 100)
            response('error', array('msg' => 'Коэффициент не может быть меньше 0 или больше 100.'), '374');

        $fields['coefficient'] = $coefficient;
        $fields['rate'] = $rate;

        if(!$plan_percentage = DB::escape($_REQUEST['plan_percentage']))
            $plan_percentage = 0;

        if($plan_percentage < 0 || $plan_percentage > 100)
            response('error', array('msg' => 'Процент от плана не может быть меньше 0 или больше 100.'), '375');

        $fields['plan_percentage'] = $plan_percentage;

        $fields['partner'] = $userToken['id'];

        if(DB::insert($fields, DB_POSITIONS))
            response('success', array('msg' => 'Должность создана.'), '621');
        else
            response('error', '', '503');
        

    break;

    case 'get':

        $result = [];
      
        $ORDER_BY = Order::positions(Pages::$field, Pages::$order);

        $positions = DB::select('*', DB_POSITIONS, 'partner = '.$userToken['id'], $ORDER_BY);

        $sections = [["terminal", "терминал"], ["statistics", "статистика"], ["finance", "финансы"], ["menu", "меню"], ["warehouse", "склад"], ["marketing", "маркетинг"], ["cashbox", "Кассовый модуль"], ["accesses", "доступы"]];

        $fields['access_right'] = $access_right;

        while($row = DB::getRow($positions)){

            foreach($row AS $key => $value){

                for($i = 0; $i < sizeof($sections); $i++){
    
                    if($sections[$i][0] == $key && $value == 1){
    
                        if(!$row['access_right'])
                            $row['access_right'] = $sections[$i][1];
                        else
                            $row['access_right'] .= ', '.$sections[$i][1];
    
                    }
    
                }
    
            }

            $result[] = $row;

        }

        response('success', $result, '7');

    break;

    case 'delete':

        if(!$position = DB::escape($_REQUEST['position']))
            response('error', array('msg' => 'Выберите должность.'), '376');

        if(DB::delete(DB_POSITIONS, 'id = '.$position.' AND partner = '.$userToken['id']))
            response('success', array('msg' => 'Должность удалена.'), '622');
        else
            response('error', '', '503');

    break;

    case 'edit':

        if(!$position = DB::escape($_REQUEST['position']))
            response('error', array('msg' => 'Выберите должность.'), '376');

        $positionData = DB::select('id', DB_POSITIONS, 'id = '.$position.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($positionData) == 0)
            response('error', array('msg' => 'Должность не найдена.'), '377');

        $fields = [];

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите название должности.'), '373');

        $fields['name'] = $name;

        $fields['root'] = DB::escape($_REQUEST['root']) ? 1 : 0;
        $fields['execute_inventory'] = DB::escape($_REQUEST['execute_inventory']) ? 1 : 0;
        $fields['terminal'] = DB::escape($_REQUEST['terminal']) ? 1 : 0;
        $fields['statistics'] = DB::escape($_REQUEST['statistics']) ? 1 : 0;
        $fields['finance'] = DB::escape($_REQUEST['finance']) ? 1 : 0;
        $fields['menu'] = DB::escape($_REQUEST['menu']) ? 1 : 0;
        $fields['warehouse'] = DB::escape($_REQUEST['warehouse']) ? 1 : 0;
        $fields['cashbox'] = DB::escape($_REQUEST['cashbox']) ? 1 : 0;
        $fields['marketing'] = DB::escape($_REQUEST['marketing']) ? 1 : 0;
        $fields['accesses'] = DB::escape($_REQUEST['accesses']) ? 1 : 0;
    
        if($coefficient = DB::escape($_REQUEST['coefficient'])){
        
            if($coefficient < 0 || $coefficient > 100)
                response('error', array('msg' => 'Коэффициент не может быть меньше 0 или больше 100.'), '374');
        
            $fields['coefficient'] = $coefficient;

        }
        else
            $fields['coefficient'] = 0;

        if($rate = DB::escape($_REQUEST['rate']))
            $fields['rate'] = $rate;
        else
            $fields['rate'] = 60;

        if($plan_percentage = DB::escape($_REQUEST['plan_percentage'])){
        
            if($plan_percentage < 0 || $plan_percentage > 100)
                response('error', array('msg' => 'Процент от плана не может быть меньше 0 или больше 100.'), '375');
        
            $fields['plan_percentage'] = $plan_percentage;

        }
        else
            $fields['plan_percentage'] = 0;

        if(sizeof($fields) == 0)
            response('error', array('msg' => 'Не передано ни одного параметра.'), '330');

        if(DB::update($fields, DB_POSITIONS, 'id = '.$position))
            response('success', array('msg' => 'Должность изменена.'), '623');
        else
            response('error', '', '503');

    break;

    case 'info':

        if(!$position = DB::escape($_REQUEST['position']))
            response('error', array('msg' => 'Выберите должность.'), '376');

        $positionData = DB::select('*', DB_POSITIONS, 'id = '.$position.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($positionData) == 0)
            response('error', array('msg' => 'Должность не найдена.'), '377');

        $positionData = DB::getRow($positionData);

        response('success', $positionData, '7');

    break;

}