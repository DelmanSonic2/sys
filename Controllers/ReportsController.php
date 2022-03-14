<?php

namespace Controllers;

use Support\DB;
use Support\Request;
use Support\Utils;

//Отчеты
class ReportsController
{

    public static function trasactionPoints()
    {

        $user = Request::authUser();

        if (!Request::has(['from', 'to'])) {
            Utils::response("error", "Укажите диапазон дат", 3);
        }

        $from = Request::$request['from'];
        $to = Request::$request['to'];

        $from = strtotime(date('Y-m-d', $from));
        $to = strtotime(date('Y-m-d', $to)) + (24 * 60 * 60);

        $from_dyd = date('Ym', $from);

        //Точки
        $points = DB::makeArrayWithKey(DB::query("SELECT * FROM app_partner_points WHERE partner=" . $user['id']), "id");
        $points_id = array_values(array_keys($points));

        //Считаем текйщие остатвки по точке берем все itmes которе есть на точке и находим сумму умножением count+total
        $now_points_total = DB::makeArrayWithKey(DB::query("SELECT point, SUM(count*price) as total FROM app_point_items WHERE point IN (" . implode(",", $points_id) . ") GROUP BY point"), "point");

        //Находим сумму движения по точка за выбранный диапазон
        $transactions = DB::makeArray(DB::query("SELECT point,
                SUM(IF(proccess=0, total, 0)) as total_0,
                SUM(IF(proccess=1, total, 0)) as total_1,
                SUM(IF(proccess=2, total, 0)) as total_2,
                SUM(IF(proccess=3, total, 0)) as total_3,
                SUM(IF(proccess=4, total, 0)) as total_4,
                SUM(IF(proccess=5, total, 0)) as total_5,
                SUM(total) as total FROM `app_partner_transactions`
                WHERE date>$from AND date<$to  AND  dyd >= $from_dyd AND point IN (" . implode(",", $points_id) . ") GROUP BY point"));

        //Считаем сколько были движений после даты окончания для посчета остатка на конец
        $transactions_after_to = DB::makeArrayWithKey(DB::query("SELECT point, SUM(total) as total FROM `app_partner_transactions` WHERE date>$to AND dyd >= $from_dyd AND point IN (" . implode(",", $points_id) . ")  GROUP BY point"), "point");

        $data = [];

        foreach ($transactions as $row) {

            $on_end = (isset($now_points_total[$row['point']]) ? $now_points_total[$row['point']]['total'] : 0) -
                (isset($transactions_after_to[$row['point']]['total']) ? $transactions_after_to[$row['point']]['total'] : 0);
            $on_start = $on_end - $row['total'];

            $points[$row['point']]['use'] = true; //Отмечаем склады которые использовались, чтобы вывести нулевые в конце

            $data[] = [
                'name' => $points[$row['point']]['name'],
                'on_start' => round($on_start, 2),
                'total_0' => round($row['total_0'], 2),
                'total_1' => round($row['total_1'], 2),
                'total_2' => round($row['total_2'], 2),
                'total_3' => round($row['total_3'], 2),
                'total_4' => round($row['total_4'], 2),
                'total_5' => round($row['total_5'], 2),
                'total' => round($row['total'], 2),
                'on_end' => round($on_end, 2),
            ];

        }

        foreach ($points as $val) {
            if (!isset($val['use'])) {

                $on_end = (isset($now_points_total[$val['id']]) ? $now_points_total[$val['id']]['total'] : 0) -
                    (isset($transactions_after_to[$val['id']]['total']) ? $transactions_after_to[$val['id']]['total'] : 0);

                $data[] = [
                    'name' => $val['name'],
                    'on_start' => round($on_end, 2),
                    'total_0' => 0,
                    'total_1' => 0,
                    'total_2' => 0,
                    'total_3' => 0,
                    'total_4' => 0,
                    'total_5' => 0,
                    'total' => 0,
                    'on_end' => round($on_end, 2),
                ];
            }
        }

        //Utils::responsePlain($data);
        Utils::response("success", $data, 7);

    }

    public static function trasactionCategoryPoints()
    {

        $user = Request::authUser();

        if (!Request::has(['from', 'to', 'point'])) {
            Utils::response("error", "Укажите диапазон дат", 3);
        }

        $from = Request::$request['from'];
        $to = Request::$request['to'];
        $point = Request::$request['point'];

        $from = strtotime(date('Y-m-d', $from));
        $to = strtotime(date('Y-m-d', $to)) + (24 * 60 * 60);

        $from_dyd = date('Ym', $from);

        //items
        $items = DB::makeArrayWithKey(DB::query("SELECT * FROM app_items"), "id");

        //Точка
        //   $point = DB::getRow(DB::query("SELECT * FROM app_partner_points WHERE id=$point"));

        //Считаем текйщие остатвки по точке берем все itmes которе есть на точке и находим сумму умножением count+total
        $now_items_total = DB::makeArrayWithKey(DB::query("SELECT item, SUM(count*price) as total FROM app_point_items WHERE point=$point GROUP BY item"), "item");

        //Находим сумму движения по точка за выбранный диапазон
        $transactions = DB::makeArray(DB::query("SELECT item,
                SUM(IF(proccess=0, total, 0)) as total_0,
                SUM(IF(proccess=1, total, 0)) as total_1,
                SUM(IF(proccess=2, total, 0)) as total_2,
                SUM(IF(proccess=3, total, 0)) as total_3,
                SUM(IF(proccess=4, total, 0)) as total_4,
                SUM(IF(proccess=5, total, 0)) as total_5,
                SUM(total) as total FROM `app_partner_transactions`
                WHERE date>$from AND date<$to  AND  dyd >= $from_dyd AND point=$point GROUP BY item"));

        //Считаем сколько были движений после даты окончания для посчета остатка на конец
        $transactions_items_after_to = DB::makeArrayWithKey(DB::query("SELECT item, SUM(total) as total FROM `app_partner_transactions` WHERE date>$to AND dyd >= $from_dyd AND point=$point  GROUP BY item"), "item");

        $data = [];

        foreach ($transactions as $row) {

            $on_end = (isset($now_items_total[$row['item']]) ? $now_items_total[$row['item']]['total'] : 0) -
                (isset($transactions_items_after_to[$row['item']]['total']) ? $transactions_items_after_to[$row['item']]['total'] : 0);
            $on_start = $on_end - $row['total'];

            $data[] = [
                'name' => $items[$row['item']]['name'],
                'on_start' => round($on_start, 2),
                'total_0' => round($row['total_0'], 2),
                'total_1' => round($row['total_1'], 2),
                'total_2' => round($row['total_2'], 2),
                'total_3' => round($row['total_3'], 2),
                'total_4' => round($row['total_4'], 2),
                'total_5' => round($row['total_5'], 2),
                'total' => round($row['total'], 2),
                'on_end' => round($on_end, 2),
            ];

        }

        //Utils::responsePlain($data);
        Utils::response("success", $data, 7);

    }

}