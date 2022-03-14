<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'requests' :

        $to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
        $from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

        if($point = DB::escape($_REQUEST['point']))
            $point = ' AND r.point = '.$point;

        if(isset($_REQUEST['status']))
            $status = ' AND r.refunded = '.DB::escape($_REQUEST['status']);

        $result = [];

        $ORDER_BY = Order::marketing_refunds(Pages::$field, Pages::$order);

        $data = DB::query('
            SELECT r.*, e.name AS employee, p.name AS pointname, c.phone, c.name AS client_name,
                        (SELECT i.id
                        FROM '.DB_INVENTORY.' i
                        WHERE i.point = r.point AND i.date_end >= r.created AND i.status = 1
                        LIMIT 1) AS close
            FROM '.DB_REFUND_REQUESTS.' r
            JOIN '.DB_PARTNER_POINTS.' p ON p.id = r.point
            JOIN '.DB_EMPLOYEE_SHIFTS.' esh ON esh.id = r.shift
            JOIN '.DB_EMPLOYEES.' e ON e.id = esh.employee
            LEFT JOIN '.DB_CLIENTS.' c ON c.phone = r.client_phone AND r.client_phone != ""
            WHERE r.partner = '.$userToken['id'].' AND r.refund_created BETWEEN '.$from.' AND '.$to.$point.$status.'
            '.$ORDER_BY.'
            LIMIT '.Pages::$limit
        );

        $query = '
            SELECT COUNT(r.id) AS count
            FROM '.DB_REFUND_REQUESTS.' r
            WHERE r.partner = '.$userToken['id'].' AND r.refund_created BETWEEN '.$from.' AND '.$to.$point.$status.'
        ';

        $page_data = Pages::GetPageInfo($query, $page);

        while($row = DB::getRow($data)){

            if($row['phone'] == null)
                $client = (object)[];
            else
                $client = array(
                    'phone' => $row['phone'],
                    'name' => $row['client_name']
                );

            $composition = json_decode(
                base64_decode($row['composition']), 1
            );

            $composition_type = ['Товар', 'Акция'];

            for($i = 0; $i < sizeof($composition); $i++){

                $composition[$i]['discount'] = (double)$composition[$i]['discount'];
                $composition[$i]['time_discount'] = (double)$composition[$i]['time_discount'];

                $composition[$i]['name'] = $composition[$i]['type'] ? $composition[$i]['promotion_name'] : $composition[$i]['name'];

                $composition[$i]['type'] = $composition_type[$composition[$i]['type']];

                $composition[$i]['total'] = number_format($composition[$i]['total'], 2, ',', ' ').' '.CURRENCY;
                $composition[$i]['price'] = number_format($composition[$i]['price'], 2, ',', ' ').' '.CURRENCY;
                $composition[$i]['time_discount'] += $composition[$i]['discount'];
                $composition[$i]['time_discount'] = number_format($composition[$i]['time_discount'], 2, ',', ' ').' %';

            }

            $types = ['Картой','Наличными','Бонусами'];

            $result[] = array(
                'id' => (int)$row['id'],
                'created' => date('d-m-Y H:i:s', $row['refund_created']),
                'close' => ($row['close'] == null) ? false : true,
                'employee' => $row['employee'],
                'transaction' => array(
                    'id' => $row['id'],
                    'client' => $client,
                    'created' => date('d-m-Y H:i:s', $row['created']),
                    'sum' => number_format($row['sum'], 2, ',', ' ').' '.CURRENCY,
                    'discount' => number_format($row['discount'], 2, ',', ' ').' %',
                    'total' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
                    'cost_price' => $row['cost_price'],
                    'points' => $row['points'],
                    'type' => $types[$row['type']],
                    'promotion' => $row['promotion'],
                    'promotion_code' => $row['promotion_code'],
                    'point' => array(
                        'id' => $row['point'],
                        'name' => $row['pointname']
                    ),
                    'composition' => $composition
                ),
                'refunded' => (int)$row['refunded']
            );
            
        }

        response('success', $result, '7', $page_data);

    break;

    case 'accept' :
    case 'reject':

        require_once ROOT.'api/classes/TransactionClass.php';

        $execute = new Refunds(false, $userToken['id']);
        $execute->$action();
        //LoyalclientSyncClass::Revert(false, $_REQUEST['request'], "/sync/transactions");

    break;
}