<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

$to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
$from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

if($point = DB::escape($_REQUEST['point']))
    $point = ' AND pr.point = '.$point;

$result = [];

$sorting = Order::reports_moving(Pages::$field, Pages::$order);

$report = DB::query('
    SELECT t.pid, t.pname, t.puntils, t.pcount, t.total, i.id, i.name, i.untils, SUM(m.count) AS count, SUM(m.count * m.price) AS price
    FROM (
        SELECT p.id AS pid, p.name AS pname, p.untils AS puntils, SUM(pri.count) AS pcount, SUM(pri.cost_price) AS total, GROUP_CONCAT(DISTINCT pr.id) AS productions
        FROM '.DB_PRODUCTIONS.' pr
        JOIN '.DB_PRODUCTION_ITEMS.' pri ON pri.production = pr.id
        JOIN '.DB_ITEMS.' p ON p.id = pri.product
        WHERE pr.partner = '.$userToken['id'].' AND pr.date BETWEEN '.$from.' AND '.$to.$point.' AND pr.id IN (
            SELECT production
            FROM '.DB_PRODUCTION_ITEMS_MOVING.')
        GROUP BY p.id
        ORDER BY p.name ASC
    )t
    JOIN '.DB_PRODUCTION_ITEMS_MOVING.' m ON FIND_IN_SET(m.production, t.productions) AND m.product = t.pid
    JOIN '.DB_ITEMS.' i ON i.id = m.item
    GROUP BY t.pid, m.item
    '.$sorting.'
');

while($row = DB::getRow($report)){
    $result[] = array(
        'id' => (int)$row['pid'],
        'name' => $row['pname'],
        'production_count' => number_format($row['pcount'], 3, ',', ' ').' '.$row['puntils'],
        'total_price' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
        'item' => $row['name'],
        'count' => number_format($row['count'], 3, ',', ' ').' '.$row['untils'],
        'price' => number_format($row['price'], 2, ',', ' ').' '.CURRENCY
    );
}

/* while($row = DB::getRow($report)){

    $exist = false;

    $children = array(
        'id' => $row['iid'],
        'name' => $row['iname'],
        'count' => $row['count'] == null ? '' : number_format($row['count'], 3, ',', ' ').' '.$row['iuntils'],
        'price' => $row['price'] == null ? '' : number_format($row['price'], 2, ',', ' ').' '.CURRENCY
    );

    for($i = 0; $i < sizeof($result); $i++){

        if($result[$i]['id'] == $row['id']){

            $result[$i]['children'][] = $children;

            $exist = true;
            break;

        }

    }

    if(!$exist){

        $children_arr = [];

        $children_arr[] = $children;

        $result[] = array(
            'id' => $row['id'],
            'name' => trim($row['name']),
            'count' => number_format($row['production_count'], 3, ',', ' ').' '.$row['untils'],
            'price' => number_format($row['total'], 2, ',', ' ').' '.CURRENCY,
            'children' => $children_arr
        );

    }

} */

response('success', $result, $header, 7);