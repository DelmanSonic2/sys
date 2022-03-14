<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

//if (!$key = DB::escape($_REQUEST['secret_key']))
//    response('error', array('msg' => 'Приватный метод.'), '2');
/*if ($key != md5('secret_word'))
    response('error', array('msg' => 'Недействительный ключ доступа.'), '2');*/

switch ($action) {

    case 'info':

        if (!$phone = DB::escape($_REQUEST['phone']))
            response('error', 'Не передан номер телефона.', 422);

        //$data = DB::select('phone, name, "" AS surname, balance', DB_CLIENTS, 'phone LIKE "%'.$phone.'%"', '', 1);

        $data = DB::query("
            SELECT c.phone, c.name, '' AS surname, IF(c.sale > 0, 0, g.percent) AS cashback, c.sale AS discount, ROUND(c.balance, 2) AS balance
            FROM " . DB_CLIENTS . " c
            LEFT JOIN " . DB_CLIENTS_GROUP . " g ON g.id = c.group_id
            WHERE c.phone LIKE '{$phone}'
        ");

        if (!DB::getRecordCount($data))
            $data = DB::query("
                SELECT c.phone, c.name, '' AS surname, IF(c.sale > 0, 0, g.percent) AS cashback, c.sale AS discount, ROUND(c.balance, 2) AS balance
                FROM " . DB_CLIENTS . " c
                LEFT JOIN " . DB_CLIENTS_GROUP . " g ON g.id = c.group_id
                WHERE c.phone LIKE '%{$phone}%'
            ");

        $data = DB::getRow($data);

        if (is_null($data))
            response('error', 'Пользователь не найден.', 404);
        response('success', $data, 200);

        break;

    case 'change_balance':
        if (empty($phone = DB::escape($_REQUEST['phone'])) || empty($count = DB::escape($_REQUEST['count'])))
            response('error', array('msg' => 'Недостаточно данных.'), '2');

        DB::query('UPDATE ' . DB_CLIENTS . ' SET balance = balance + ' . $count . '
                              WHERE phone = "' . $phone . '"');

        /*DB::insert(array('client_phone' => '888888888',
            'point' => '10',
            'cashier' => '20',
            'count' => -5.24,
            'created_datetime' => date('Y-m-d H:i:s')), DB_LOYALCLIENT_REQUEST_QUEUE);*/

        /*$requests = [];
        $ids = [];
        $query = DB::query('SELECT id, client_phone, point, cashier, count FROM ' . DB_LOYALCLIENT_REQUEST_QUEUE . '
                               WHERE done = 0 ORDER BY id ASC');

        foreach ($query as $row) {
            $requests[] = $row;
            $ids[] = $row['id'];
        }
        if (count($ids) == 0) return;

        $response = json_encode(array('msg' => 'success'));

        DB::query("UPDATE " . DB_LOYALCLIENT_REQUEST_QUEUE . " SET done = 1, response = '" . $response . "'
                              WHERE id IN (" . implode(',', $ids) . ")");*/

        response('success', 'Баланс обновлен.', 200);
        break;
}
