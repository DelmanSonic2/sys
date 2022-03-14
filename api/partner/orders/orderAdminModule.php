<?php

use Support\Pages;
use Support\DB;

include ROOT . 'api/partner/tokenCheck.php';

$WHERE_UNIX_DATE = 1604188800;
$WHERE_DATE = '2020-11-01 00:00:00';

if ($userToken['id'] == 11) {
    $WHERE_UNIX_DATE = 1601510400;
    $WHERE_DATE = '2020-10-01 00:00:00';
}

function validateDate($date, $format = 'Y-m-d H:i:s', $add_days = 0)
{
    $date = $date ? $date : date('Y-m-d H:i:s');
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    return $d->modify("+{$add_days} days")->format($format);
}
function PartnerReport($db, $from, $to, $partner, $WHERE_DATE)
{
    $list = [];                     //все дни до фильтра "to"
    $result = [];                   //вывод
    $days = [];                     //дни в выбранном промежутке
    $spending = DB::query("SELECT r.date, SUM(s.sum) as spending FROM `app_partner_orders_report` r
                                        JOIN app_partner_orders_spending s ON s.report = r.id
                                        WHERE r.partner = " . $partner . " AND r.date >= DATE('{$WHERE_DATE}') AND r.date < DATE('" . $to . "') AND s.created_datetime >= DATE('{$WHERE_DATE}')
                                        GROUP BY (r.date)");
    while ($row = DB::getRow($spending))
        $list[strtotime($row['date'])]['spending'] = $row['spending'];

    $income = DB::query("SELECT DATE(created_datetime) as date, SUM(sum) as income FROM `app_partner_orders_income` i
                                        JOIN " . DB_PARTNER_POINTS . " p ON i.point = p.id
                                        WHERE p.partner = " . $partner . " AND i.created_datetime >= DATE('{$WHERE_DATE}') AND DATE(i.created_datetime) < '" . $to . "'
                                        GROUP BY (date)");
    while ($row = DB::getRow($income))
        $list[strtotime($row['date'])]['income'] = $row['income'];

    $from = strtotime($from);
    $to = strtotime($to);

    $result['begin_remain'] = 0;
    $result['income_sum'] = 0;
    $result['spending_sum'] = 0;
    $result['end_remain'] = 0;
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
    return $result;
}

if ($userToken['global_admin']) {

    //Если пришел id партнера, то выборка по конкретному
    if ($partner = DB::escape($_REQUEST['partner'])) {
        $regions = [];
        $regions_query = DB::select('id', DB_PARTNER, 'parent = ' . $partner);
        while ($row = DB::getRow($regions_query))
            $regions[] = $row['id'];

        if (sizeof($regions))
            $where_partner = ' AND (r.partner = ' . $partner . ' OR FIND_IN_SET(r.partner, "' . implode(',', $regions) . '"))';
        else
            $where_partner = ' AND r.partner = ' . $partner;
    }
    //Иначе по всем
    else
        $where_partner = '';
} else
    $where_partner = ' AND r.partner = ' . $userToken['id'];

switch ($action) {
    case 'get_income':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']), 'Y-m-d H:i:s', 1);
        $point = !is_null($_REQUEST['point']) ? DB::escape($_REQUEST['point']) : '';
        $inn = !is_null($_REQUEST['inn']) ? DB::escape($_REQUEST['inn']) : '';

        $partnersQuery = DB::query('SELECT id,name,surname,middlename FROM ' . DB_PARTNER);
        $partners = [];
        $result = [];

        while ($row = DB::getRow($partnersQuery))
            $partners[$row['id']] = $row;

        $query = 'SELECT i.id, r.partner, i.created_datetime, i.sum, r.name as point, r.id as pointId, r.inn as pointInn
        FROM ' . DB_PARTNER_ORDERS_INCOME . ' i
        JOIN ' . DB_PARTNER_POINTS . ' r ON r.id = i.point WHERE i.created_datetime >= "' . $WHERE_DATE . '"' . $where_partner;
        if ($from != '') $query .= " AND created_datetime >= '" . $from . "'";
        if ($to != '') $query .= " AND created_datetime < '" . $to . "'";

        if ($point != '') $query .= " AND r.id = '" . $point . "'";
        if ($inn != '') $query .= " AND r.inn = '" . $inn . "'";
        $query .= " ORDER BY i.created_datetime DESC";
        $query .= " LIMIT " . Pages::$limit;

        $query = DB::query($query);
        while ($row = DB::getRow($query)) {
            $date = DATETIME::createFromFormat('Y-m-d H:i:s', $row['created_datetime'])->format('Y-m-d');
            $result[] = $row;
        }
        foreach ($result as $key => $val)
            $result[$key]['partner'] = $partners[$val['partner']];

        $page_query = 'SELECT COUNT(i.id) as count 
        FROM ' . DB_PARTNER_ORDERS_INCOME . ' i
        JOIN ' . DB_PARTNER_POINTS . ' r ON r.id = i.point WHERE i.created_datetime >= "' . $WHERE_DATE . '"' . $where_partner;
        if ($from != '') $page_query .= " AND created_datetime >= '" . $from . "'";
        if ($to != '') $page_query .= " AND created_datetime < '" . $to . "'";
        if ($point != '') $page_query .= " AND r.id = '" . $point . "'";
        if ($inn != '') $page_query .= " AND r.inn = '" . $inn . "'";

        $page_data = Pages::GetPageInfo($page_query, $page);

        response('success', $result, 200, $page_data);
        break;

    case 'get_spending':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']), 'Y-m-d H:i:s', 1);
        $supplier = !is_null($_REQUEST['supplier']) ? DB::escape($_REQUEST['supplier']) : '';
        $type = !is_null($_REQUEST['type']) ? DB::escape($_REQUEST['type']) : '';
        $search = !is_null($_REQUEST['search']) ? DB::escape($_REQUEST['search']) : '';

        $partnersQuery = DB::query('SELECT id,name,surname,middlename FROM ' . DB_PARTNER);
        $partners = [];

        while ($row = DB::getRow($partnersQuery))
            $partners[$row['id']] = $row;

        $query = 'SELECT DISTINCT r.*
        FROM ' . DB_PARTNER_ORDERS_REPORT . ' r
        JOIN ' . DB_PARTNER_ORDERS_SPENDING . ' s ON s.report = r.id
        LEFT JOIN ' . DB_SUPPLIERS . ' u ON u.id = s.supplier WHERE r.date >= DATE("' . $WHERE_DATE . '") AND s.created_datetime >= "' . $WHERE_DATE . '"' . $where_partner;
        if ($from != '') $query .= " AND r.date >= DATE('" . $from . "')";
        if ($to != '') $query .= " AND r.date < DATE('" . $to . "')";
        if ($supplier != '') $query .= " AND u.id = '" . $supplier . "'";
        if ($type != '') $query .= " AND r.type = '" . $type . "'";
        if ($search != '') $query .= " AND s.comment LIKE '%" . $search . "%'";
        $query .= " ORDER BY r.date DESC, r.id DESC";
        $query .= " LIMIT " . Pages::$limit;

        $ids = [];
        $reports = [];
        $query = DB::query($query);
        while ($row = DB::getRow($query)) {
            $ids[] = $row['id'];
            $reports[$row['id']] = $row;
            $reports[$row['id']]['partner'] = $partners[$row['partner']];
        }

        $page_query = 'SELECT COUNT(DISTINCT r.id) as count
        FROM ' . DB_PARTNER_ORDERS_REPORT . ' r
        JOIN ' . DB_PARTNER_ORDERS_SPENDING . ' s ON s.report = r.id
        LEFT JOIN ' . DB_SUPPLIERS . ' u ON u.id = s.supplier WHERE r.date >= DATE("' . $WHERE_DATE . '") AND s.created_datetime >= "' . $WHERE_DATE . '"' . $where_partner;
        if ($from != '') $page_query .= " AND r.date >= DATE('" . $from . "')";
        if ($to != '') $page_query .= " AND r.date < DATE('" . $to . "')";
        if ($supplier != '') $page_query .= " AND u.id = '" . $supplier . "'";
        if ($type != '') $page_query .= " AND r.type = '" . $type . "'";
        if ($search != '') $page_query .= " AND s.comment LIKE '%" . $search . "%'";
        $page_data = Pages::GetPageInfo($page_query, $page);

        if (count($ids) > 0) {
            $query = DB::query("SELECT s.id, s.report, s.type, s.created_datetime, s.comment, s.sum, e.id as service, e.name as serviceName, s.supply, u.id as supplierId, u.name as supplierName, s.confirm_num, DATE(s.confirm_date) as confirm_date
                                            FROM " . DB_PARTNER_ORDERS_SPENDING . " s
                                            LEFT JOIN " . DB_SUPPLIERS . " u ON u.id = s.supplier
                                            LEFT JOIN " . DB_SERVICES . " e ON e.id = s.service
                                        WHERE s.created_datetime >= '{$WHERE_DATE}' AND s.report IN (" . implode(',', $ids) . ")
                                         ORDER BY id ASC");
            while ($row = DB::getRow($query)) {
                $reports[$row['report']]['orders'][] = $row;
            }
        }

        $reports = array_values($reports);
        response('success', $reports, 200, $page_data);
        break;

    case 'remain':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']), 'Y-m-d H:i:s', 1);

        $point = !is_null($_REQUEST['point']) ? DB::escape($_REQUEST['point']) : '';
        $inn = !is_null($_REQUEST['inn']) ? DB::escape($_REQUEST['inn']) : '';

        $supplier = !is_null($_REQUEST['supplier']) ? DB::escape($_REQUEST['supplier']) : '';
        $type = !is_null($_REQUEST['type']) ? DB::escape($_REQUEST['type']) : '';
        $search = !is_null($_REQUEST['search']) ? DB::escape($_REQUEST['search']) : '';

        $total = 'SELECT (SELECT SUM(sum) FROM ' . DB_PARTNER_ORDERS_INCOME . ' i 
        JOIN ' . DB_PARTNER_POINTS . ' r ON i.point = r.id WHERE i.created_datetime >= "' . $WHERE_DATE . '"' . $where_partner;
        if ($from != '') $total .= " AND i.created_datetime >= '" . $from . "'";
        if ($to != '') $total .= " AND i.created_datetime < '" . $to . "'";
        if ($point != '') $total .= " AND r.id = '" . $point . "'";
        if ($inn != '') $total .= " AND r.inn = '" . $inn . "'";
        $total .= ') as income, (SELECT SUM(sum) FROM ' . DB_PARTNER_ORDERS_SPENDING . ' s 
        JOIN ' . DB_PARTNER_ORDERS_REPORT . ' r ON s.report = r.id
        LEFT JOIN ' . DB_SUPPLIERS . ' u ON u.id = s.supplier WHERE s.created_datetime >= "' . $WHERE_DATE . '" AND r.date >= DATE("' . $WHERE_DATE . '")' . $where_partner;
        if ($from != '') $total .= " AND r.date >= DATE('" . $from . "')";
        if ($to != '') $total .= " AND r.date < DATE('" . $to . "')";
        if ($supplier != '') $total .= " AND u.id = '" . $supplier . "'";
        if ($type != '') $total .= " AND r.type = '" . $type . "'";
        if ($search != '') $total .= " AND s.comment LIKE '%" . $search . "%'";
        $total .= ') as spending';
        $total = DB::query($total);
        $total = DB::getRow($total);

        response('success', ['income' => $total['income'], 'spending' => $total['spending'], 'remain' => $total['income'] - $total['spending']], 200);
        break;

    case 'set_limit':
        if (is_null($partner)) response('error', 'Не указан id партнера.', 346);

        if (is_null($_REQUEST['limit'])) response('error', 'Не указан лимит остатка.', 346);
        else $limit = DB::escape($_REQUEST['limit']);

        DB::update(["remain_limit" => $limit], DB_PARTNER, "id = " . $partner);

        response('success', 'Лимит установлен.', 200);
        break;


    case 'report':
        $from = validateDate(DB::escape($_REQUEST['from']));
        $to = validateDate(DB::escape($_REQUEST['to']), 'Y-m-d H:i:s', 1);

        $partners = [];
        $query = DB::select('id, name, remain_limit', DB_PARTNER, "id IN (2,3,4,5,6,7,8,9,11)");
        while ($row = DB::getRow($query))
            $partners[] = $row;

        $result = [];
        $result['total']['begin_remain'] = 0;
        $result['total']['income_sum'] = 0;
        $result['total']['spending_sum'] = 0;
        $result['total']['end_remain'] = 0;
        foreach ($partners as $p) {
            $WHERE_DATE = ($p['id'] == 11) ? '2020-10-01 00:00:00' : '2020-11-01 00:00:00';
            $info = PartnerReport(false, $from, $to, $p['id'], $WHERE_DATE);
            $info['id'] = $p['id'];
            $info['name'] = $p['name'];
            $info['remain_limit'] = $p['remain_limit'];
            $result['partners'][] = $info;
            $result['total']['begin_remain'] += $info['begin_remain'];
            $result['total']['income_sum'] += $info['income_sum'];
            $result['total']['spending_sum'] += $info['spending_sum'];
            $result['total']['end_remain'] += $info['end_remain'];
        }
        response('success', $result, 200);
        break;

    case 'expense_report':

        $result = [];

        $from = validateDate(DB::escape($_REQUEST['from']), 'Y-m-d');
        $to = validateDate(DB::escape($_REQUEST['to']), 'Y-m-d');

        $reports = DB::query("SELECT r.id FROM " . DB_PARTNER_ORDERS_REPORT . " r WHERE r.date BETWEEN '{$from}' AND '{$to}'{$where_partner}");
        $reports = implode(',', DB::getColumn('id', $reports));

        if (!$reports)
            response('success', [], 200, ['total' => round(0, 2)]);

        $query = DB::query("
            (
                SELECT s.id, s.name, SUM(r.sum) AS sum, NULL as supplier, '' AS supplier_name, 'services' AS type
                FROM " . DB_PARTNER_ORDERS_SPENDING . " r
                JOIN " . DB_SERVICES . " s ON s.id = r.service
                WHERE r.report IN ({$reports}) AND r.service IS NOT NULL
                GROUP BY s.id
                ORDER BY s.name ASC
            ) UNION (
                SELECT -1 AS id, 'Поставки' AS name, SUM(r.sum) AS sum, r.supplier, s.name AS supplier_name, 'supplies' AS type
                FROM " . DB_PARTNER_ORDERS_SPENDING . " r
                JOIN " . DB_SUPPLIERS . " s ON s.id = r.supplier
                WHERE r.report IN ({$reports}) AND r.supplier IS NOT NULL
                GROUP BY s.id
                ORDER BY s.name ASC
            ) UNION (
                SELECT 0 AS id, 'Инкассация' AS name, SUM(r.sum) AS sum, NULL AS supplier, '' AS supplier_name, 'collections' AS type
                FROM " . DB_PARTNER_ORDERS_SPENDING . " r
                WHERE r.report IN ({$reports}) AND r.supplier IS NULL AND r.supply IS NULL AND r.service IS NULL
                HAVING sum IS NOT NULL
            )
            ORDER BY id ASC
        ");

        $total = 0;

        while ($row = DB::getRow($query)) {

            $total += $row['sum'];

            $result[$row['id']]['id'] = $row['id'];
            $result[$row['id']]['key'] = $row['id'];
            $result[$row['id']]['name'] = $row['name'];
            $result[$row['id']]['sum'] += $row['sum'];
            $result[$row['id']]['type'] = $row['type'];

            if (!is_null($row['supplier'])) {
                $result[$row['id']]['type'] = false;
                $result[$row['id']]['children'][] = array(
                    'id' => $row['supplier'],
                    'name' => $row['supplier_name'],
                    'sum' => $row['sum'],
                    'type' => $row['type']
                );
            }
        }

        $result = array_values($result);

        response('success', $result, 200, ['total' => round($total, 2)]);

        break;

    case 'expense_report_details':

        $result = [];

        $id = DB::escape($_REQUEST['id']);

        $request_type = DB::escape($_REQUEST['type']);
        $page_data = array(
            'current_page' => 1,
            'total_pages' => 0,
            'rows_count' => 0,
            'page_size' => 50
        );

        $limit = 50;

        if (isset($request_type))
            $type = ($request_type == 'services') ? 'services' : (($request_type == 'collections') ? 'collections' : ($request_type == 'supplies' ? 'supplies' : ''));

        $from = validateDate(DB::escape($_REQUEST['from']), 'Y-m-d');
        $to = validateDate(DB::escape($_REQUEST['to']), 'Y-m-d', 1);

        $reports = DB::query("SELECT r.id FROM " . DB_PARTNER_ORDERS_REPORT . " r WHERE r.date BETWEEN '{$from}' AND '{$to}'{$where_partner}");
        $reports = implode(',', DB::getColumn('id', $reports));

        if (!$reports)
            response('success', [], 200, $page_data);

        if ($request_type == 'supplies') {
            $where_id = $id ? " AND s.id = {$id}" : '';
            $query = "
                SELECT r.id, r.status, 'Оплата поставки' AS name, r.comment, r.sum, r.confirm_num, r.confirm_date
                FROM " . DB_PARTNER_ORDERS_SPENDING . " r
                JOIN " . DB_SUPPLIERS . " s ON s.id = r.supplier
                WHERE r.report IN ({$reports}) AND r.supply IS NOT NULL{$where_id}
                ORDER BY r.confirm_date ASC";
            //        LIMIT {$limit}
        } elseif ($request_type == 'services') {
            $where_id = $id ? " AND r.service = {$id}" : '';
            $query = "
                SELECT r.id, r.status, s.name, r.comment, r.sum, r.confirm_num, r.confirm_date
                FROM " . DB_PARTNER_ORDERS_SPENDING . " r
                JOIN " . DB_SERVICES . " s ON s.id = r.service
                WHERE r.report IN ({$reports}) AND r.service IS NOT NULL{$where_id}
                ORDER BY r.confirm_date ASC";
            //    LIMIT {$limit}
        } elseif ($request_type == 'collections')
            $query = "
                SELECT r.id, r.status, 'Инкассация' AS name, r.comment, r.sum, r.confirm_num, p.date AS confirm_date
                FROM " . DB_PARTNER_ORDERS_SPENDING . " r
                JOIN " . DB_PARTNER_ORDERS_REPORT . " p ON r.report = p.id
                WHERE r.report IN ({$reports}) AND r.supplier IS NULL AND r.supply IS NULL AND r.service IS NULL
                ORDER BY r.confirm_date ASC";
        //          LIMIT {$limit}
        else
            response('error', 'Неверный тип.', 422);

        $page_query = "SELECT COUNT(t.id) AS count FROM ($query) t";
        $page_data = Pages::GetPageInfo($page_query, $page);

        $db_data =  DB::query($query);
        while ($row = DB::getRow($db_data)) {
            $result[] = $row;
        }

        response('success', $result, 200, $page_data);

        break;
}