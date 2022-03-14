<?php

use phpDocumentor\Reflection\Types\Float_;
use Support\Pages;
use Support\DB;

include ROOT . 'api/partner/tokenCheck.php';

function validateDate($date, $format = 'Y-m-d H:i:s')
{
    $d = DateTime::createFromFormat($format, $date);
    $result = $d && $d->format($format) == $date;
    return $result ? $date : '';
}

$unix_date = 1604188800;
$date = '2020-11-01 00:00:00';

if ($userToken['id'] == 11) {
    $unix_date = 1601510400;
    $date = '2020-10-01 00:00:00';
}

switch ($action) {
    case 'get_income':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']));

        $result = [];
        $list = [];
        $query = 'SELECT i.id, i.created_datetime, i.sum, p.name as point
        FROM ' . DB_PARTNER_ORDERS_INCOME . ' i
        JOIN ' . DB_PARTNER_POINTS . ' p ON p.id = i.point
        WHERE p.partner = ' . $userToken['id'] . ' AND created_datetime >= "' . $date . '"';
        if ($from != '') $query .= " AND created_datetime >= '" . $from . "'";
        if ($to != '') $query .= " AND created_datetime <= '" . $to . "'";
        $query .= " ORDER BY i.created_datetime DESC";
        $query = DB::query($query);
        while ($row = DB::getRow($query)) {
            $date = DATETIME::createFromFormat('Y-m-d H:i:s', $row['created_datetime'])->format('Y-m-d');
            $list[$date][] = $row;
        }
        foreach ($list as $key => $dateElem) {
            $new = [];
            $new['title'] = $key;
            $new['data'] = $dateElem;
            $result[] = $new;
        }
        response('success', $result, 200);
        break;

    case 'get_spending':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']));

        $result = [];
        $list = [];
        $query = 'SELECT r.id, r.date as reportDate,r.type as reportType, s.id as orderId, s.type, s.created_datetime, s.comment, s.sum, e.id as service, e.name as serviceName, s.supply, u.id as supplierId, u.name as supplierName, s.confirm_num, DATE(s.confirm_date) as confirm_date
                                       FROM ' . DB_PARTNER_ORDERS_REPORT . ' r
                                       JOIN ' . DB_PARTNER_ORDERS_SPENDING . ' s ON s.report = r.id
                                       LEFT JOIN ' . DB_SUPPLIERS . ' u ON u.id = s.supplier
                                       LEFT JOIN ' . DB_SERVICES . ' e ON e.id = s.service
                                       WHERE s.partner = ' . $userToken['id'] . ' AND r.date >= DATE("' . $date . '")';
        if ($from != '') $query .= " AND r.date >= DATE('" . $from . "')";
        if ($to != '') $query .= " AND r.date <= DATE('" . $to . "')";
        $query .= " ORDER BY r.date DESC, r.id DESC";
        $query = DB::query($query);
        while ($row = DB::getRow($query)) {
            $id = $row['id'];
            $list[$id]['date'] = $row['reportDate'];
            $list[$id]['id'] = $id;
            $list[$id]['type'] = $row['reportType'];
            unset($row['id']);
            unset($row['reportDate']);
            unset($row['reportType']);
            $list[$id]['orders'][] = $row;
        }
        $list = array_values($list);

        response('success', $list, 200);
        break;

    case 'get_open_supplies':

        if (is_null($_REQUEST['supplier'])) response('error', 'Не указан поставщик.', 346);
        else $supplier = DB::escape($_REQUEST['supplier']);
        $query = DB::query("SELECT id, FROM_UNIXTIME(date) as date, sum, items_count, comment
                                    FROM " . DB_SUPPLIES . " WHERE partner = " . $userToken['id'] . " AND supplier = " . $supplier . " AND date >= '{$unix_date}' AND id NOT IN 
                                    (SELECT DISTINCT supply FROM " . DB_PARTNER_ORDERS_SPENDING . " WHERE partner = " . $userToken['id'] . " AND supplier = " . $supplier . " AND created_datetime >= DATE('{$date}'))
                                    ORDER BY date DESC");

        /*$query = 'SELECT id, FROM_UNIXTIME(date) as date, sum, items_count, comment
        FROM ' . DB_SUPPLIES . ' WHERE partner = ' . $userToken['id'] . ' AND supplier = ' . $supplier;
        if ($from != '') $query .= " AND date >= UNIX_TIMESTAMP('" . $from . "')";
        if ($to != '') $query .= " AND date <= UNIX_TIMESTAMP('" . $to . "')";
        $query .= ' AND id NOT IN (SELECT DISTINCT s.supply FROM ' . DB_PARTNER_ORDERS_SPENDING .' s
        JOIN '.DB_PARTNER_ORDERS_REPORT.' r ON r.id = s.report
        WHERE s.partner = ' . $userToken['id'] . ' AND s.supplier = ' . $supplier;
        if ($from != '') $query .= " AND r.date >= DATE('" . $from . "')";
        if ($to != '') $query .= " AND r.date <= DATE('" . $to . "')";
        $query .= ') ORDER BY date DESC LIMIT 50';*/

        $result = [];
        while ($row = DB::getRow($query))
            $result[] = $row;
        response('success', $result, 200);
        break;

    case 'get_services':
        $query = DB::query('SELECT * FROM ' . DB_SERVICES);

        $result = [];
        while ($row = DB::getRow($query))
            $result[] = $row;

        response('success', $result, 200);
        break;

    case 'add_spending':
        $data = stripcslashes(DB::escape($_REQUEST['data'])); //прием json-массив ордеров в поле data
        $data = json_decode($data, true);

        if (is_null($_REQUEST['date'])) response('error', 'Ошибка добавления.', 346);
        else $date = DB::escape($_REQUEST['date']);
        if (is_null($_REQUEST['type'])) response('error', 'Ошибка добавления.', 346);
        else $type = DB::escape($_REQUEST['type']);
        if (empty($data)) response('error', 'Не переданы данные РКО.', 346);

        if (empty($date)) $date = date('Y-m-d H:i:s', time());

        $min_date = $userToken['id'] == 11 ? '2020-10-01' : '2020-11-01';

        if ($date < $min_date)
            response('error', 'Нельзя указать дату меньшую "' . $min_date . '".', 422);


        //Проверка на минимальную дату последнего отчета (не чаще чам 1 раз в 10 сек)
        //     $stop_date = strtotime('-10 seconds', time());
        //     $stop_row = DB::getRow(DB::select("id", DB_PARTNER_ORDERS_SPENDING, "partner=" . $userToken['id'] . " AND created_datetime > FROM_UNIXTIME(" . $stop_date . ")"));

        if (isset($stop_row["id"])) {
            response('error', 'Слишком часто', 422);
        }

        $report = DB::insert(['partner' => $userToken['id'], 'date' => $date, 'type' => $type], DB_PARTNER_ORDERS_REPORT);

        $sql = "INSERT INTO " . DB_PARTNER_ORDERS_SPENDING . " (id,partner,report,type,created_datetime,comment,sum,supplier,supply,service,confirm_num,confirm_date) VALUES ";

        foreach ($data as $k => $e) {
            if ($type) {
                $data[$k]['supplier'] = null;
                $data[$k]['supply'] = null;
                $data[$k]['service'] = null;
                $data[$k]['confirm_date'] = null;
                $data[$k]['confirm_num'] = null;
            } else {
                if (empty($data[$k]['confirm_date'])) $data[$k]['confirm_date'] = date('Y-m-d H:i:s', time());
                if ($e['type']) {
                    $data[$k]['supplier'] = null;
                    $data[$k]['supply'] = null;
                } else
                    $data[$k]['service'] = null;
            }

            if ($data[$k]['sum'] <= 0) {
                DB::query('DELETE FROM ' . DB_PARTNER_ORDERS_REPORT . ' WHERE id = ' . $report);
                return response('error', 'Нельзя указать отрицательную сумму.', 346);
            }

            $sql .= "(null,"
                . $userToken['id'] . ","
                . $report . ",'"
                . $data[$k]['type'] . "','"
                . date('Y-m-d H:i:s', time()) . "','"
                . $data[$k]['comment'] . "',"
                . $data[$k]['sum'] . ",";
            $sql .= !empty($data[$k]['supplier']) ? "'" . $data[$k]['supplier'] . "'," : 'null,';
            $sql .= !empty($data[$k]['supply']) ? "'" . $data[$k]['supply'] . "'," : 'null,';
            $sql .= !empty($data[$k]['service']) ? "'" . $data[$k]['service'] . "'," : 'null,';
            $sql .= !empty($data[$k]['confirm_num']) ? "'" . $data[$k]['confirm_num'] . "'," : 'null,';
            $sql .= !empty($data[$k]['confirm_date']) ? "'" . $data[$k]['confirm_date'] . "'" : 'null';
            $sql .= "),";
        }

        $sql = rtrim($sql, ",");                     //удаление запятой после работы цикла

        $result = DB::query($sql);

        if ($result)
            response('success', 'Отчет создан.', 200);
        else {
            DB::query('DELETE FROM ' . DB_PARTNER_ORDERS_REPORT . ' WHERE id = ' . $report);
            response('error', 'Ошибка добавления.', 346);
        }
        break;


    case 'edit_spending':
        $data = stripcslashes(DB::escape($_REQUEST['data'])); //прием json-массив ордеров в поле data
        $data = json_decode($data, true);
        if (empty($data)) response('error', 'Не переданы данные РКО.', 346);

        if (!$report = DB::escape($_REQUEST['report']))
            response('error', 'Выберите отчет, который необходимо редактировать', 346);

        $report_data = DB::select('*', DB_PARTNER_ORDERS_REPORT, 'id = ' . $report, '', 1);

        if (!DB::getRecordCount($report_data))
            response('error', 'Отчет не найден.', 346);
        $report_data = DB::getRow($report_data);

        $date = $_REQUEST['date'] ? DB::escape($_REQUEST['date']) : $report_data['date'];

        $min_date = $userToken['id'] == 11 ? '2020-10-01' : '2020-11-01';

        if ($date < $min_date)
            response('error', 'Нельзя указать дату меньшую "' . $min_date . '".', 422);
        $sql = "INSERT INTO " . DB_PARTNER_ORDERS_SPENDING . " (id,partner,report,type,created_datetime,comment,sum,supplier,supply,service,confirm_num,confirm_date)  VALUES ";

        foreach ($data as $k => $e) {
            if ($report_data['type']) {
                $data[$k]['supplier'] = null;
                $data[$k]['supply'] = null;
                $data[$k]['service'] = null;
                $data[$k]['confirm_date'] = null;
                $data[$k]['confirm_num'] = null;
            } else {
                if (empty($data[$k]['confirm_date'])) $data[$k]['confirm_date'] = date('Y-m-d H:i:s', time());
                if ($e['type']) {
                    $data[$k]['supplier'] = null;
                    $data[$k]['supply'] = null;
                } else
                    $data[$k]['service'] = null;
            }

            if ($data[$k]['sum'] <= 0) {
                DB::query('DELETE FROM ' . DB_PARTNER_ORDERS_REPORT . ' WHERE id = ' . $report);
                return response('error', 'Нельзя указать отрицательную сумму.', 346);
            }

            $sql .= "(null,"
                . $userToken['id'] . ","
                . $report . ",'"
                . $data[$k]['type'] . "','"
                . date('Y-m-d H:i:s', time()) . "','"
                . $data[$k]['comment'] . "',"
                . $data[$k]['sum'] . ",";
            $sql .= !empty($data[$k]['supplier']) ? "'" . $data[$k]['supplier'] . "'," : 'null,';
            $sql .= !empty($data[$k]['supply']) ? "'" . $data[$k]['supply'] . "'," : 'null,';
            $sql .= !empty($data[$k]['service']) ? "'" . $data[$k]['service'] . "'," : 'null,';
            $sql .= !empty($data[$k]['confirm_num']) ? "'" . $data[$k]['confirm_num'] . "'," : 'null,';
            $sql .= !empty($data[$k]['confirm_date']) ? "'" . $data[$k]['confirm_date'] . "'" : 'null';
            $sql .= "),";
        }

        $sql = rtrim($sql, ",");                     //удаление запятой после работы цикла

        DB::update(['date' => $date], DB_PARTNER_ORDERS_REPORT, 'id = ' . $report);
        DB::delete(DB_PARTNER_ORDERS_SPENDING, 'report = ' . $report);
        DB::query($sql);

        response('success', 'Изменения сохранены.', 200);

        break;

    case 'delete_spending':
        if (is_null($_REQUEST['report'])) response('error', 'Не указан id отчета.', 346);
        else $report = DB::escape($_REQUEST['report']);

        DB::delete(DB_PARTNER_ORDERS_REPORT, "id = " . $report);

        response('success', 'Отчет удален.', 200);
        break;

    case 'remain':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']));

        $total = 'SELECT (SELECT SUM(sum) FROM ' . DB_PARTNER_ORDERS_INCOME . ' i JOIN ' . DB_PARTNER_POINTS . ' p ON i.point = p.id WHERE p.partner = ' . $userToken['id'] . ' AND i.created_datetime >= "' . $date . '"';
        if ($from != '') $total .= " AND i.created_datetime >= '" . $from . "'";
        if ($to != '') $total .= " AND i.created_datetime <= '" . $to . "'";
        $total .= ') as income, (SELECT SUM(sum) FROM ' . DB_PARTNER_ORDERS_SPENDING . ' s JOIN ' . DB_PARTNER_ORDERS_REPORT . ' r ON s.report = r.id WHERE s.partner = ' . $userToken['id'] . ' AND s.created_datetime >= "' . $date . '"';
        if ($from != '') $total .= " AND r.date >= DATE('" . $from . "')";
        if ($to != '') $total .= " AND r.date <= DATE('" . $to . "')";
        $total .= ') as spending';
        $total = DB::query($total);
        $total = DB::getRow($total);

        $today = date('Y-m-d');
        $today_remain = DB::getRow(DB::query('
            SELECT (IF(t.income IS NULL, 0, t.income) - IF(t.spending IS NULL, 0, t.spending)) as val
            FROM (
                SELECT (
                    SELECT SUM(sum) FROM ' . DB_PARTNER_ORDERS_INCOME . ' WHERE point IN (SELECT id FROM ' . DB_PARTNER_POINTS . ' WHERE partner = ' . $userToken['id'] . ') AND DATE(created_datetime) BETWEEN "' . $date . '" AND "' . $today . '"
                ) AS income, (
                    SELECT SUM(sum) FROM ' . DB_PARTNER_ORDERS_SPENDING . ' WHERE partner = ' . $userToken['id'] . ' AND DATE(created_datetime) BETWEEN "' . $date . '" AND "' . $today . '"
                ) AS spending
            ) t
        '));

        if (isset($today_remain['val'])) {
            $today_remain = (float) $today_remain['val'];
        } else {
            $today_remain = 0;
        }


        response('success', [
            'name' => $userToken['name'],
            'limit' => $userToken['remain_limit'],
            'income' => $total['income'],
            'spending' => $total['spending'],
            'remain' => $total['income'] - $total['spending'],
            'today_remain' => $today_remain
        ], 200);
        break;

    case 'report':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']));

        $list = [];                     //все дни до фильтра "to"
        $result = [];                   //вывод
        $days = [];                     //дни в выбранном промежутке
        $spending = DB::query("SELECT r.date, SUM(s.sum) as spending FROM `app_partner_orders_report` r
                                        JOIN app_partner_orders_spending s ON s.report = r.id
                                        WHERE r.partner = " . $userToken['id'] . " AND r.date <= DATE('" . $to . "') AND r.date >= DATE('{$date}') AND s.created_datetime >= DATE('{$date}')
                                        GROUP BY (r.date)");
        while ($row = DB::getRow($spending))
            $list[strtotime($row['date'])]['spending'] = $row['spending'];

        $income = DB::query("SELECT DATE(created_datetime) as date, SUM(sum) as income FROM `app_partner_orders_income` i
                                        JOIN " . DB_PARTNER_POINTS . " p ON i.point = p.id
                                        WHERE p.partner = " . $userToken['id'] . " AND DATE(i.created_datetime) <= DATE('" . $to . "') AND i.created_datetime >= DATE('{$date}')
                                        GROUP BY (date)");
        while ($row = DB::getRow($income))
            $list[strtotime($row['date'])]['income'] = $row['income'];

        $from = strtotime($from);
        $to = strtotime($to);

        $result['begin_remain'] = 0;
        $result['income_sum'] = 0;
        $result['spending_sum'] = 0;
        $next_step = 0;
        foreach ($list as $k => $v) {
            if (is_null($v['income'])) $v['income'] = 0;
            if (is_null($v['spending'])) $v['spending'] = 0;
            if ($k < $from) {
                $result['begin_remain'] += $v['income'] - $v['spending'];
                $next_step = $result['begin_remain'];
            } else {
                //$v['date'] = date('Y-m-d', $k);
                $v['date'] = $k;
                $v['remain'] = $v['income'] - $v['spending'];

                $days[] = $v;
            }
            $result['end_remain'] += $v['income'] - $v['spending'];
        }
        usort($days, function ($a, $b) {
            if ($a['date'] == $b['date'])
                return 0;
            return ($a['date'] > $b['date']) ? -1 : 1;
        });

        for ($i = sizeof($days) - 1; $i >= 0; $i--) {
            $days[$i]['date'] = date('Y-m-d', $days[$i]['date']);
            $days[$i]['remain'] += $next_step;
            $next_step = $days[$i]['remain'];
            $result['income_sum'] += $days[$i]['income'];
            $result['spending_sum'] += $days[$i]['spending'];
        }
        //        foreach ($days as $k => $v) {
        //            $days[$k]['date'] = date('Y-m-d', $v['date']);
        //            $days[$k]['remain'] += $next_step;
        //            $next_step = $days[$k]['remain'];
        //        }

        $result['days'] = $days;

        response('success', $result, 200);
        break;
}