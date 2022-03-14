<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';
require ROOT.'api/classes/TransactionClass.php';

switch($action){

    case 'create':

        $balance = 0;
        $sale = 0;
        $promotion = false;

        $date = (DB::escape($_REQUEST['date'])) ? DB::escape($_REQUEST['date']) : time();

        if($phone = DB::escape($_REQUEST['phone'])){ //Если пришел номер телефона, то необходимо узнать, есть ли у клиента скидка или баллы

            $clientData = DB::query('SELECT c.balance, c.sale, g.percent
                                            FROM '.DB_CLIENTS.' c
                                            JOIN '.DB_CLIENTS_GROUP.' AS g ON g.id = c.group_id
                                            WHERE c.phone = "'.$phone.'"');

            if(DB::getRecordCount($clientData) == 0)
                response('error', array('msg'=>'Пользователь не зарегистрирован в программе бонусов.'), '525');

            list($balance, $sale, $points_percent) = DB::getRow($clientData, 'num');

            //Если есть скидка, то баллы обнуляются, даже если они есть, т.к. приоритет скидки выше
            if($sale > 0){
                $balance = 0;
                $points_percent = 0;
            }

        }

        if(!$employee_id = DB::escape($_REQUEST['employee']))
            response('error', array('msg' => 'Не передан ID сотрудника.'), '512');

        if(!$shift_id = DB::escape($_REQUEST['shift']))
            response('error', array('msg' => 'Не передан ID смены.'), '513');

        $shiftData = DB::select('id, shift_closed', DB_EMPLOYEE_SHIFTS, 'id = "'.$shift_id.'" AND employee = '.$employee_id);

        if(DB::getRecordCount($shiftData) == 0)
            response('error', array('msg' => 'Смена не найдена.'), '514');

        //Тип оплаты: 0 - картой, 1 - наличными
        $type = DB::escape($_REQUEST['type']);

        if(DB::escape($_REQUEST['promotion'])){
            
            $promotion = true;

            if($balance < 0)
                response('error', array('msg' => 'Не достаточно баллов для оплаты покупки.'), '526');

        }

        $shiftData = DB::getRow($shiftData);

        if($shiftData['shift_closed'] == 1)
            response('error', array('msg' => 'Смена закрыта, откройте новую смену.'), '515');

        //Список товаров
        $products = DB::escape($_REQUEST['products']);

        //Список акций
        $promotions = DB::escape($_REQUEST['promotions']);

        //Промокод
        $promotion_code = DB::escape($_REQUEST['promotion_code']);

        //Акции "в подарок"
        $promotion_gifts = DB::escape($_REQUEST['promotion_gifts']);

        //Округлять
        $not_round = DB::escape($_REQUEST['not_round']);

        $uniqid = DB::escape($_REQUEST['uniqid']);
        
        $tr_class = new TransactionClass(false, $pointToken['id'], $pointToken['partner'], $phone, $employee_id, $shift_id, $date, $products, $promotions, $balance, $sale, $points_percent, $type, $promotion, $promotion_code, $promotion_gifts, $not_round, $uniqid);
        $tr_class->create();

        response('success', array('msg' => 'Транзакция создана.', 'id' => $tr_class->transaction, 'total' => $tr_class->total, 'uniqid' => $tr_class->uniqid), 7);

    break;

    case 'fiscal':

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не передан ID транзакции', 422);

        if(DB::escape($_REQUEST['fiscal']))
            $fiscal = true;
        else
            $fiscal = false;

        $error = DB::escape($_REQUEST['error']);

        $fields = [
            'fiscal' => $fiscal,
            'fiscal_error' => $error
        ];

        DB::update($fields, DB_TRANSACTIONS, 'id = '.$id);

        response('success', 'Изменения внесены.', 201);

        break;

}