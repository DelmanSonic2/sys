<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

switch($action){

    case 'get':

        $result = [];

        $today = strtotime(date('d-m-Y', time()));

        $point = DB::escape($_REQUEST['point']) ? ' AND tr.id = '.DB::escape($_REQUEST['point']) : '';

        $row = DB::query('SELECT tr.id AS point, tr.name, SUM(t.total) AS revenue, SUM(t.profit) AS profit, COUNT(t.id) AS checks, AVG(t.total) AS average_check
                                    FROM '.DB_PARTNER_POINTS.' tr
                                    JOIN '.DB_TRANSACTIONS.' AS t ON t.point = tr.id
                                    WHERE t.created >= '.$today.$where_partner.$point);

        $row = DB::getRow($row);

        $result = array(  'point' => (int)$row['point'],
                            'name' => $point ? $row['name'] : 'Общая статистика',
                            'revenue' => (int)$row['revenue'],
                            'profit' => (int)$row['profit'],
                            'checks' => (int)$row['checks'],
                            'average_check' => (int)$row['average_check']);

        response('success', $result, 7);

    break;

}