<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

switch($action){

    case 'shift':

        $result = [];

        if(!$shift = DB::escape($_REQUEST['shift']))
            response('error', 'Не передан ID смены.', 1);

       
      // 	if($pointToken['id'] == 5)
       // $from = strtotime(date('Y-m-14', time()));
       // else
        $from = strtotime(date('Y-m-d', time()));
        
       

        $checks = DB::query('SELECT tr.id, tr.uniqid, tr.shift, tr.client_phone, tr.created, tr.fiscal, tr.fiscal_error, tr.sum, tr.discount, tr.total, tr.points, tr.type, r.refunded,
                            (SELECT i.id
                            FROM '.DB_INVENTORY.' i
                            WHERE i.point = tr.point AND i.date_end >= tr.created AND i.status = 1
                            LIMIT 1) AS close
                                    FROM '.DB_TRANSACTIONS.' tr
                                    LEFT JOIN '.DB_REFUND_REQUESTS.' r ON r.id = tr.id
                                    WHERE tr.created >= '.$from.' AND tr.point = '.$pointToken['id'].'
                                    ORDER BY tr.id DESC');

        while($row = DB::getRow($checks)){

            if(!$row['uniqid'])
                $row['uniqid'] = $row['id'];

            if($row['close'] != null || $row['refunded'] != null)
                $row['close'] = true;
            else
                $row['close'] = false;

            if($row['refunded'] == null)
                $row['refunded'] = false;

            if(!$transaction_where)
                $transaction_where = 'transaction = '.$row['id'];
            else
                $transaction_where .= ' OR transaction = '.$row['id'];

            $row['items'] = [];
            $result[] = $row;

        }

        if($transaction_where){
            $items = DB::query(' SELECT tr.name, tr.bulk, tr.count, tr.price, tr.total, tr.type, tr.promotion_name, tr.transaction, pc.name AS category
                                        FROM '.DB_TRANSACTION_ITEMS.' tr
                                        LEFT JOIN '.DB_PRODUCTS.' AS pr ON pr.id = tr.product
                                        LEFT JOIN '.DB_PRODUCT_CATEGORIES.' AS pc ON pc.id = pr.category
                                        WHERE '.$transaction_where);

            while($row = DB::getRow($items)){

                for($i = 0; $i < sizeof($result); $i++){

                    if($result[$i]['id'] == $row['transaction']){

                        $result[$i]['items'][] = array('name' => ($row['type'] == 0) ? $row['name'].' '.$row['bulk'] : $row['promotion_name'],
                                                        'count' => $row['count'],
                                                        'price' => $row['price'],
                                                        'total' => $row['total'],
                                                        'type' => $row['type'],
                                                        'category' => ($row['type'] == 0) ? $row['category'] : "Акция");

                        break;

                    }

                }

            }

        }

        response('success', $result, 7);

    break;

}