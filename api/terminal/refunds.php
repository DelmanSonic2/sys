<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

switch ($action) {

    case 'create':

        require_once ROOT.'api/classes/TransactionClass.php';

        $refund = new Refunds(false, $pointToken['partner'], $pointToken['id']);
        $refund->request();

    break;

    case 'get':
        $from = strtotime(date('Y-m-d', time()));
        $result = [];
        $refunds = DB::query('SELECT r.id, r.uniqid, r.shift, r.client_phone, r.created, r.fiscal, r.fiscal_error, r.sum, r.discount, r.total, r.points, r.type, r.refunded
                                    FROM ' . DB_REFUND_REQUESTS . ' r
                                    WHERE r.created >= ' . $from . ' AND r.point = ' . $pointToken['id'] . '
                                    ORDER BY r.id DESC');
        while($row = DB::getRow($refunds))
            $result[] = $row;
        response('success', $result, 200);
        break;
}