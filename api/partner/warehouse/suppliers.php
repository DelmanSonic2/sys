<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'add':

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Не передано имя поставщика.'), '342');

        $fields = array('name' => $name,
                        'partner' => $userToken['id']);

        if($phone = DB::escape($_REQUEST['phone']))
            $fields['phone'] = $phone;

        if($address = DB::escape($_REQUEST['address']))
            $fields['address'] = $address;

        if($comment = DB::escape($_REQUEST['comment']))
            $fields['comment'] = $comment;
        
        if($USREOU = DB::escape($_REQUEST['USREOU']))
            $fields['USREOU'] = $USREOU;

        if($taxpayer_number = DB::escape($_REQUEST['taxpayer_number']))
            $fields['taxpayer_number'] = $taxpayer_number;

        if($type = DB::escape($_REQUEST['type']))
            $fields['type'] = $type;

        if(DB::insert($fields, DB_SUPPLIERS))
            response('success', array('msg' => 'Поставщик добавлен.'), '615');
        else
            response('error', '', '503');

    break;

    case 'edit':

        if(!$supplier = DB::escape($_REQUEST['supplier']))
            response('error', array('msg' => 'Не передан ID поставщика.'), '343');

        $supplierData = DB::select('id, partner', DB_SUPPLIERS, 'id = '.$supplier.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($supplierData) == 0)
            response('error', array('msg' => 'Поставщик с таким ID не найден.'), '346');

        $supplierData = DB::getRow($supplierData);

        if($supplierData['partner'] == null)
            response('error', array('msg' => 'Вы не можете редактировать информацию о общедоступном поставщике.'), '344');

        $fields = [];

        if($name = DB::escape($_REQUEST['name']))
            $fields['name'] = $name;

        if($address = DB::escape($_REQUEST['address']))
            $fields['address'] = $address;

        if($phone = DB::escape($_REQUEST['phone']))
            $fields['phone'] = $phone;
        
        if($comment = DB::escape($_REQUEST['comment']))
            $fields['comment'] = $comment;

        if($USREOU = DB::escape($_REQUEST['USREOU']))
            $fields['USREOU'] = $USREOU;

        if($taxpayer_number = DB::escape($_REQUEST['taxpayer_number']))
            $fields['taxpayer_number'] = $taxpayer_number;

        if(sizeof($fields) == 0)
            response('error', array('msg' => 'Не передано ни одного параметра.'), '345');

        if(DB::update($fields, DB_SUPPLIERS, 'id = '.$supplier))
            response('success', array('msg' => 'Информация о поставщике изменена.'), '615');
        else
            response('error', '', '503');

    break;

    case 'delete':

        if(!$supplier = DB::escape($_REQUEST['supplier']))
            response('error', array('msg' => 'Не передан ID поставщика.'), '343');

        $supplierData = DB::select('id, partner', DB_SUPPLIERS, 'id = '.$supplier.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($supplierData) == 0)
            response('error', array('msg' => 'Поставщик с таким ID не найден.'), '343');

        $supplierData = DB::getRow($supplierData);

        if($supplierData['partner'] == null)
            response('error', array('msg' => 'Вы не можете удалить общедоступного поставщика.'), '344');

        if(DB::delete(DB_SUPPLIERS, 'id = '.$supplier))
            response('success', array('msg' => 'Поставщик удален.'), '616');
        else
            response('error', '', '503');

    break;

    case 'get':

        $page = DB::escape($_REQUEST['page']);

        $full = DB::escape($_REQUEST['full']);

        if($type = DB::escape($_REQUEST['type']))
            $type = 1;
        else
            $type = 0;

        $result = [];

        if($full)
            $select = ', address, phone, comment, supplies_count, supplies_sum, taxpayer_number, USREOU';

        $element_count = 50;
        if(!$page || $page == 1)
            $limit = '0,'.$element_count;
        else{
            $begin = $element_count*$page - $element_count;
            $limit = $begin.','.$element_count; 
        }

        $sorting = Order::suppliers(Pages::$field, Pages::$order);
        
        $suppliers = DB::select('id, name, partner AS my'.$select, DB_SUPPLIERS, '(partner = '.$userToken['id'].' OR partner IS NULL) AND type = '.$type, $sorting);

        while($row = DB::getRow($suppliers)){

            $row['my'] = ($row['my'] == null) ? false : true;
            $row['supplies_sum'] = number_format($row['supplies_sum'], 2, ',', ' ').' '.CURRENCY;

            $result[] = $row;

        }

        response('success', $result, '7');

        case 'info':

            if(!$supplier = DB::escape($_REQUEST['supplier']))
                response('error', array('msg' => 'Не передан ID поставщика.'), '343');

            $supplierData = DB::select('id, name, phone, address, comment, USREOU, taxpayer_number', DB_SUPPLIERS, 'id = '.$supplier.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

            if(DB::getRecordCount($supplierData) == 0)
                response('error', array('msg' => 'Поставщик с таким ID не найден.'), '343');

            $supplierData = DB::getRow($supplierData);

            response('success', $supplierData, '7');

        break;

    break;

}