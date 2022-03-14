<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

$to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
$from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

if($point = DB::escape($_REQUEST['point']))
    $point = ' AND tr.point = '.$point;

$total_info = DB::query('SELECT SUM(ti.total) AS total, SUM(ti.profit) AS profit, SUM(ti.count) AS count
                                    FROM '.DB_PRODUCTS.' p
                                    JOIN '.DB_TRANSACTION_ITEMS.' AS ti ON ti.product = p.id
                                    JOIN '.DB_TRANSACTIONS.' AS tr ON tr.id = ti.transaction
                                    WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$point);

$total_info = DB::getRow($total_info);

if($total_info['total'] == null || $total_info['profit'] == null || $total_info['count'] == null)
    response('success', [], '7');

switch($action){

    case 'abc':

        $result = [];

        $transactions = DB::query('SELECT p.id, p.name, SUM(ti.total) AS total, (SUM(ti.total) * 100 / '.$total_info['total'].') AS total_percent, SUM(ti.profit) AS profit, (SUM(ti.profit) * 100 / '.$total_info['profit'].') AS profit_percent, SUM(ti.count) AS count, (SUM(ti.count) * 100 / '.$total_info['count'].') AS count_percent
                                        FROM '.DB_PRODUCTS.' p
                                        JOIN '.DB_TRANSACTION_ITEMS.' AS ti ON ti.product = p.id
                                        JOIN '.DB_TRANSACTIONS.' AS tr ON tr.id = ti.transaction
                                        WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$point.'
                                        GROUP BY p.id
                                        ORDER BY total DESC, profit DESC, count DESC');

        for($i = 0; $i < DB::getRecordCount($transactions); $i++){
            $row = DB::getRow($transactions);

            $row['total_percent'] = round($row['total_percent'], 2);
            $row['profit_percent'] = round($row['profit_percent'], 2);
            $row['count_percent'] = round($row['count_percent'], 2);
            $row['total'] = round($row['total'], 2);
            $row['profit'] = round($row['profit'], 2);
            $row['count'] = round($row['count'], 2);

            if($i == 0){
                $row['total_acc'] = $row['total_percent'];
                $row['profit_acc'] = $row['profit_percent'];
                $row['count_acc'] = $row['count_percent'];
            }
            else{
                $row['total_acc'] = $result[$i - 1]['total_acc'] + $row['total_percent'];
                $row['profit_acc'] = $result[$i - 1]['profit_acc'] + $row['profit_percent'];
                $row['count_acc'] = $result[$i - 1]['count_acc'] + $row['count_percent'];
            }

            if($row['total_acc'] < 80)
                $row['total_group'] = "A";
            if($row['total_acc'] >= 80 && $row['total_acc'] < 95)
                $row['total_group'] = "B";
            if($row['total_acc'] >= 95)
                $row['total_group'] = "C";

            if($row['profit_acc'] < 80)
                $row['profit_group'] = "A";
            if($row['profit_acc'] >= 80 && $row['profit_acc'] < 95)
                $row['profit_group'] = "B";
            if($row['profit_acc'] >= 95)
                $row['profit_group'] = "C";

            if($row['count_acc'] < 80)
                $row['count_group'] = "A";
            if($row['count_acc'] >= 80 && $row['count_acc'] < 95)
                $row['count_group'] = "B";
            if($row['count_acc'] >= 95)
                $row['count_group'] = "C";

            $result[$i] = $row;
        }

        response('success', $result, '7');

    break;

    case 'xyz':

        $type = DB::escape($_REQUEST['type']);

        $result = [];

        $data = DB::query('SELECT p.id, p.name, SUM(ti.total) AS total, SUM(ti.profit) AS profit, SUM(ti.count) AS count,
                                (SUM(ti.total) * 100 / '.$total_info['total'].') AS total_percent,
                                (SUM(ti.profit) * 100 / '.$total_info['profit'].') AS profit_percent,
                                (SUM(ti.count) * 100 / '.$total_info['count'].') AS count_percent,
                                    (STDDEV(ti.total) / AVG(ti.total) * 100) AS total_dev,
                                    (STDDEV(ti.profit) / AVG(ti.profit) * 100) AS profit_dev,
                                    (STDDEV(ti.count) / AVG(ti.count) * 100) AS count_dev
                                FROM '.DB_TRANSACTION_ITEMS.' ti
                                LEFT JOIN '.DB_TRANSACTIONS.' tr ON ti.transaction = tr.id
                                JOIN '.DB_PRODUCTS.' p ON p.id = ti.product
                                WHERE tr.created BETWEEN '.$from.' AND '.$to.$where_partner.$point.'
                                GROUP BY p.id
                                ORDER BY count_dev ASC, total_dev ASC, profit_dev ASC');

        while($row = DB::getRow($data)){

            $row['total_percent'] = round($row['total_percent'], 2);
            $row['profit_percent'] = round($row['profit_percent'], 2);
            $row['count_percent'] = round($row['count_percent'], 2);
            $row['total'] = round($row['total'], 2);
            $row['profit'] = round($row['profit'], 2);
            $row['count'] = round($row['count'], 2);

            if($row['total_dev'] < 10)
                $row['total_group'] = "X";
            if($row['total_dev'] >= 10 && $row['total_dev'] < 25)
                $row['total_group'] = "Y";
            if($row['total_dev'] >= 25)
                $row['total_group'] = "Z";

            if($row['profit_dev'] < 10)
                $row['profit_group'] = "X";
            if($row['profit_dev'] >= 10 && $row['profit_dev'] < 25)
                $row['profit_group'] = "Y";
            if($row['profit_dev'] >= 25)
                $row['profit_group'] = "Z";

            if($row['count_dev'] < 10)
                $row['count_group'] = "X";
            if($row['count_dev'] >= 10 && $row['count_dev'] < 25)
                $row['count_group'] = "Y";
            if($row['count_dev'] >= 25)
                $row['count_group'] = "Z";

            $result[] = $row;

        }

        response('success', $result, '7');

    break;
}