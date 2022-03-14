<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/lib/functions.php';
require ROOT.'api/classes/TableHead.php';
require ROOT.'api/classes/OrderClass.php';

function DeleteTransaction($id, $partner){

    

    $transaction = DB::select('*', DB_FINANCES_TRANSACTIONS, 'partner = '.$partner.' AND id = '.$id);

    if(DB::getRecordCount($transaction) == 0)
        response('error', 'Транзакция с таким ID не найдена.', 1);

    DB::delete(DB_FINANCES_TRANSACTIONS, 'partner = '.$partner.' AND id = '.$id);

}

function AddTransaction($partner){

    

    $type = DB::escape($_REQUEST['type']);
    
    $comment = DB::escape($_REQUEST['comment']);

    if($category = DB::escape($_REQUEST['category'])){

        $categoryData = DB::select('id', DB_FINANCES_CATEGORIES, 'id = '.$category.' AND (partner = '.$partner.' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

    }
    else
        $category = 'NULL';

    if(!$date = DB::escape($_REQUEST['date']))
        $date = time();

    if(!$sum = DB::escape($_REQUEST['sum']))
        response('error', array('msg' => 'Введите сумму.'), '400');

    if(!$point = DB::escape($_REQUEST['point']))
        response('error', array('msg' => 'Выберите заведение.'), '501');

    $pointData = DB::select('id, balance', DB_PARTNER_POINTS, 'id = '.$point.' AND partner = '.$partner);

    if(DB::getRecordCount($pointData) == 0)
        response('error', array('msg' => 'Заведение не найдено.'), '502');

    $pointData = DB::getRow($pointData);

    if($type != 0)
        $sum = $sum * -1;

    $pointData['balance'] += $sum;

    $sum_second = 0;
    $point_second = 'NULL';
    $pointsSecondData = array('balance' => 0);

    if($type == 2){

        if(!$point_second = DB::escape($_REQUEST['point_second']))
            response('error', array('msg' => 'Выберите точку, куда будут переведены средства.'), '505');

        $pointsSecondData = DB::select('id, balance', DB_PARTNER_POINTS, 'partner = '.$partner.' AND id = '.$point_second);

        if(!DB::getRecordCount($pointsSecondData))
            response('error', array('msg' => 'Точка-получатель не найдена.'), '506');

        $pointsSecondData = DB::getRow($pointsSecondData);

        if(!$sum_second = DB::escape($_REQUEST['sum_second']))
            $sum_second = $sum;

        $pointsSecondData['balance'] += $sum_second;

    }

    $insert_items = '("'.$partner.'", "'.$sum.'", "'.$point.'", '.$category.', "'.$type.'", "'.$date.'", "'.time().'", "'.$pointData['balance'].'", "'.$comment.'", '.$point_second.', "'.$pointsSecondData['balance'].'", "'.$sum_second.'")';

    DB::query('INSERT INTO '.DB_FINANCES_TRANSACTIONS.' (partner, sum, point, category, type, date, created, balance, comment, point_second, balance_second, sum_second) VALUES '.$insert_items);

    if(DB::getLastError())
        return false;
    else
        return true;

}

switch($action){

    case 'create':

        if(AddTransaction($userToken['id']))
            response('success', array('msg' => 'Транзакция создана.'), '632');
        else
            response('error', '', '503');

    break;

    case 'get':

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND (t.point = '.$point.' OR t.point_second = '.$point.')';

        if($from = DB::escape($_REQUEST['from'])){
            $from = strtotime(date('Y-m-d', $from));
            $from = ' AND t.date >= '.$from;
        }

        if($to = DB::escape($_REQUEST['to'])){
            $to = strtotime('+1 day', strtotime(date('Y-m-d', $to)));
            $to = ' AND t.date < '.$to;
        }

        $ORDER_BY = Order::finances_transactions(Pages::$field, Pages::$order);

        $transactions = DB::query('SELECT t.id, t.category, t.point, t.type, t.date, t.comment, t.sum, t.balance, c.name AS cname, p.name, t.point_second, ps.name AS point_second_name, t.sum_second, t.balance_second
                                        FROM '.DB_FINANCES_TRANSACTIONS.' t
                                        LEFT JOIN '.DB_FINANCES_CATEGORIES.' AS c ON c.id = t.category
                                        JOIN '.DB_PARTNER_POINTS.' AS p ON p.id = t.point
                                        LEFT JOIN '.DB_PARTNER_POINTS.' ps ON ps.id = t.point_second
                                        WHERE t.partner = '.$userToken['id'].$point.$from.$to.'
                                        '.$ORDER_BY.'
                                        LIMIT '.Pages::$limit);

        $result = [];

        $pages = DB::query('SELECT COUNT(t.id) AS count, (SUM(IF(t.sum > 0, t.sum, 0)) + SUM(IF(t.sum_second > 0, t.sum_second, 0))) AS income, (SUM(IF(t.sum < 0, t.sum, 0)) + SUM(IF(t.sum_second < 0, t.sum_second, 0))) AS consumption
                                    FROM '.DB_FINANCES_TRANSACTIONS.' t
                                    WHERE t.partner = '.$userToken['id'].$point.$from.$to);

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $columns = array(
            'sum' => number_format($pages['income'] + $pages['consumption'], 2, ',', ' ').' '.CURRENCY,
            'income' => number_format($pages['income'], 2, ',', ' ').' '.CURRENCY,
            'consumption' => number_format($pages['consumption'], 2, ',', ' ').' '.CURRENCY,
        );

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT,
                        'total' => TableFooter::finance_transactions($columns));

        while($row = DB::getRow($transactions)){

            //$row['balance'] = number_format($row['balance'], 2, ',', ' ');
            //$row['sum'] = number_format($row['sum'], 2, ',', ' ');

            $row['point_second'] = array(
                'id' => $row['point_second'],
                'name' => $row['point_second_name']
            );

            $row['point'] = array('id' => $row['point'],
                                    'name' => $row['name']);

            $row['category'] = array('id' => $row['category'],
                                    'name' => $row['cname']);

            unset($row['point_second_name']);
            unset($row['name']);
            unset($row['cname']);

            $result[] = $row;
        }

        response('success', $result, '7', $pageData);

    break;

    case 'info':

        if(!$transaction = DB::escape($_REQUEST['transaction']))
            response('error', 'Не передан ID транзакции.', 1);

        $transaction_data = DB::query('
            SELECT t.*, c.name AS cat, p.name AS point_name, ps.name AS name_second
            FROM '.DB_FINANCES_TRANSACTIONS.' t
            LEFT JOIN '.DB_FINANCES_CATEGORIES.' c ON c.id = t.category
            LEFT JOIN '.DB_PARTNER_POINTS.' ps ON ps.id = t.point_second
            JOIN '.DB_PARTNER_POINTS.' p ON p.id = t.point
            WHERE t.id = '.$transaction.'
            LIMIT 1'
        );

        if(DB::getRecordCount($transaction_data) == 0)
            response('error', 'Транзакция не найдена.', 1);

        $transaction_data = DB::getRow($transaction_data);

        $transaction_data['point_second'] = array(
            'id' => $transaction_data['point_second'],
            'name' => $transaction_data['name_second']
        );

        $transaction_data['point'] = array('id' => $transaction_data['point'],
                                            'name' => $transaction_data['point_name']);

        if($transaction_data['category'] == null)
            $transaction_data['category'] = (object)[];
        else
            $transaction_data['category'] = array('id' => $transaction_data['category'],
                                                    'name' => $transaction_data['cat']);

        unset($transaction_data['name_second']);
        unset($transaction_data['point_name']);
        unset($transaction_data['cat']);

        response('success', $transaction_data, 7);

    break;

    case 'delete':

        if(!$transaction = DB::escape($_REQUEST['transaction']))
            response('error', 'Не передан ID транзакции.', 1);

        DeleteTransaction($transaction, $userToken['id']);

        response('success', 'Транзакция удалена.', 7);

    break;
    
    case 'edit':

        if(!$transaction = DB::escape($_REQUEST['transaction']))
            response('error', 'Не передан ID транзакции.', 1);

        $transaction_data = DB::select('id', DB_FINANCES_TRANSACTIONS, 'id = '.$transaction.' AND partner = '.$userToken['id'], '', 1);

        if(DB::getRecordCount($transaction_data) == 0)
            response('error', 'Транзакция не найдена.', 1);

        if(!AddTransaction($userToken['id']))
            response('error', 'Произошла ошибка, повторите попытку позднее.', 1);

        DeleteTransaction($transaction, $userToken['id']);

        response('success', 'Изменения сохранены.', 7);

    break;

}