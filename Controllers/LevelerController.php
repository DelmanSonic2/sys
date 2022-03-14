<?php

namespace Controllers;

use Support\DB;
use Support\mDB;

//Выравниватель данных
class LevelerController
{

    //Удаление дублей
    public static function removeDoubleInPartnerTranslation()
    {

        set_time_limit(0);

        $dyd = date("Ym");

        $items = DB::makeArray(DB::query("SELECT * FROM (SELECT
        *, COUNT(id) as count_tr,
        group_concat(`count`) as counts,
        group_concat(`balance_begin`) as balance_begins,
        group_concat(id) as ids,
        group_concat(total) as totals,
        group_concat(average_price_end) as average_price_ends
       FROM
        app_partner_transactions WHERE dyd=$dyd GROUP BY point,item,proccess_id,proccess,type) tmp WHERE tmp.count_tr > 1 LIMIT 500"));
        if (count($items) == 0) {
            echo 'not found double transations';
            exit;
        }
        //Берем за основу последнюю запись в ней обновяем поля balance_begin = balance_begins[0]
        foreach ($items as $item) {

            if (!isset($item['id'])) {
                echo 'not found double transations';
                exit;
            }

            $item['ids'] = explode(",", $item['ids']);
            $item['counts'] = explode(",", $item['counts']);
            $item['balance_begins'] = explode(",", $item['balance_begins']);

            $id = $item['ids'][count($item['ids']) - 1];
            $ids = array_slice($item['ids'], 0, count($item['ids']) - 1);
            $count = $item['counts'][count($item['counts']) - 1];
            $balance_begin = $item['balance_begins'][0];
            $fields = [
                "count" => $count,
                "total" => $item['price'] * $item['count'],
                "balance_begin" => $balance_begin,
                "balance_end" => $balance_begin + $count,
            ];

            $itmes_was = DB::makeArray(DB::select("*", "app_partner_transactions", "id IN (" . implode(", ", $item['ids']) . ")"));

            if (!DB::query("DELETE FROM app_partner_transactions WHERE id IN (" . implode(", ", $ids) . ") AND proccess_id=" . $item['proccess_id'] . " AND partner=" . $item['partner'] . " AND dyd=" . $item['dyd'] . " ")) {

                $error = DB::getLastError();

                mDB::collection("leveler_log")->insertOne([
                    "type" => "removeDoubleInPartnerTranslation",
                    "items" => $itmes_was,
                    "fields" => $fields,
                    "query_error" => "DELETE FROM app_partner_transactions WHERE id IN (" . implode(", ", $ids) . ") AND proccess_id=" . $item['proccess_id'] . " AND partner=" . $item['partner'] . " AND dyd=" . $item['dyd'] . " ",
                    'error' => $error,
                ]);

                echo $error;

                exit;
            }

            if (!DB::update($fields, "app_partner_transactions", "id=" . $id . " AND proccess_id=" . $item['proccess_id'] . " AND partner=" . $item['partner'] . " AND dyd=" . $item['dyd'])) {
                $error = DB::getLastError();

                mDB::collection("leveler_log")->insertOne([
                    "type" => "removeDoubleInPartnerTranslation",
                    "items" => $itmes_was,
                    "fields" => $fields,
                    "query_error" => "id=" . $id . " AND proccess_id=" . $item['proccess_id'] . " AND partner=" . $item['partner'] . " AND dyd=" . $item['dyd'],
                    'error' => $error,
                ]);

                echo $error;

                exit;
            }

            mDB::collection("leveler_log")->insertOne([
                "type" => "removeDoubleInPartnerTranslation",
                "items" => $itmes_was,
                "fields" => $fields,
                "result" => DB::getRow(DB::select("*", "app_partner_transactions", "id = " . $id)),
            ]);
            // DB::delete("")

            //   echo $id;
            echo json_encode($fields);
        }
        exit;
    }

    //Перерасчет итогово смены
    public function reCalcShift()
    {

        $from = strtotime(date('Y-m-d 00:00:00', strtotime("-3 days")));
        $to = strtotime(date('Y-m-d 23:59:59', strtotime("-1 days")));

        $data = DB::makeArray(DB::query("SELECT * FROM
        (SELECT sh.id, sh.`revenue`, SUM(tr.`total`) as total, tr.`partner`, tr.`point`, tr.`created_datetime`  FROM `app_employee_shifts` sh JOIN `app_transactions` tr ON tr.`shift`=sh.id WHERE sh.`shift_from` > $from AND  sh.`shift_from` < $to  GROUP BY sh.id) tmp
        WHERE tmp.`revenue` != tmp.total"));

        foreach ($data as $item) {

            DB::update([
                "revenue" => $item["total"],
            ], "app_employee_shifts", "id='" . $item['id'] . "'");
        }

        echo 'done';

    }

    //Функция которая выравнивает смены, которые могли не создать
    public static function shifts()
    {

        $from = strtotime(date('Y-m-d 00:00:00', strtotime("-2 days")));
        $to = strtotime(date('Y-m-d 23:59:59', strtotime("-1 days")));

        $data = DB::makeArray(DB::query("SELECT * FROM (SELECT sh2.`employee`, tr.partner, SUM(tr.total) as revenue, tr.`point`,GROUP_CONCAT(tr.id) as ids, sh.id, COUNT(tr.id) as count, from_unixtime(tr.`created`, '%Y %D %M') as tr_date,
        MIN(tr.`created`) as shift_from, MAX(tr.`created`) as shift_to,
        from_unixtime(sh.`shift_to`, '%Y %D %M') as sh_date  FROM `app_transactions` tr
        LEFT JOIN `app_employee_shifts` sh ON sh.`id`=tr.`shift` AND from_unixtime(tr.`created`, '%Y %D %M')=from_unixtime(sh.`shift_to`, '%Y %D %M')
        LEFT JOIN app_employee_shifts sh2 ON sh2.id = tr.`shift`
        WHERE tr.created > $from AND  tr.created < $to   GROUP BY from_unixtime(tr.`created`, '%Y %D %M'), tr.`point`) tmp WHERE tmp.id IS NULL"));

        foreach ($data as $item) {

            if ($item['count'] > 0) {
                //Создаем смену

                $fields = array(
                    'id' => 'auto-' . hash('sha512', $item['ids']),
                    'point' => $item['point'],
                    'employee' => $item['employee'],
                    'shift_from' => $item['shift_from'],
                    'shift_to' => $item['shift_to'],
                    'revenue' => $item['revenue'],
                    'hours' => round(($item['shift_to'] - $item['shift_from']) / 3600),
                    'shift_closed' => 1,
                );

                //var_dump($item['ids']);
                if (DB::insert($fields, DB_EMPLOYEE_SHIFTS)) {

                    //Обновляем транзакции
                    if (!DB::update([
                        "shift" => $fields['id'],
                    ], 'app_transactions', 'id IN (' . $item['ids'] . ') AND point=' . $item['point'])) {
                        echo DB::getLastError();
                    }
                } else {
                    echo DB::getLastError();
                }
            }

            echo 'done';
            // echo hash('sha512', '123456');
        }

        $orders_income = DB::makeArray(DB::query("SELECT * FROM (SELECT sh.id as shift_id, sh.`point`, SUM(t.`total`) as sum,
        from_unixtime(sh.`shift_to`, '%Y-%m-%d %H:%i:%s') as created_datetime,
        inc.`shift_id` as sh_id FROM `app_employee_shifts` sh LEFT JOIN app_partner_orders_income inc ON inc.`shift_id`=sh.id JOIN `app_partner_points` p ON p.id=sh.`point` JOIN `app_transactions` t  WHERE  sh.`id` = t.`shift` AND t.`type`=1   AND sh.`shift_from` > $from  GROUP BY sh.id) tmp WHERE  tmp.sh_id IS NULL"));

        foreach ($orders_income as $item) {
            DB::insert([
                'point' => $item['point'],
                'created_datetime' => $item['created_datetime'],
                'sum' => $item['sum'],
                'shift_id' => $item['shift_id'],
            ], "app_partner_orders_income");
            echo 'done';
        }
    }

    public static function income()
    {

        $start = date('Y-m-t', strtotime("-2 months"));
        $end = date('Y-m-01');

        $data = DB::makeArray(
            DB::query(
                "SELECT i.`id`, i.`point`, i.`sum`, SUM(t.`total`) as sum_cal, i.sum-SUM(t.`total`) as delt, i.`created_datetime`
               FROM `app_partner_orders_income` i JOIN `app_transactions` t
               WHERE  i.`shift_id` = t.`shift` AND t.`type`=1 AND i.created_datetime > '$start' AND i.created_datetime < '$end'
               GROUP BY t.`shift`"
            )
        );

        foreach ($data as $val) {
            if ($val['delt'] != 0) {

                DB::update([
                    "sum" => $val['sum_cal'],
                ], 'app_partner_orders_income', "id={$val['id']}");
                var_dump($val);
            }
        }
        echo 'ok';
    }

}