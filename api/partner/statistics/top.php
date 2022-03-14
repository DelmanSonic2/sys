<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/lib/functions.php';

$end = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60))) : strtotime(date('Y-m-d', strtotime("+1 days")));
$mid = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));
$begin = $mid - ($end - $mid);

if($partner = DB::escape($_REQUEST['partner'])) {

    $regions = [];
    $regions_query = DB::select('id', DB_PARTNER, 'parent = '.$partner);
    while($row = DB::getRow($regions_query))
        $regions[] = $row['id'];

    if(sizeof($regions))
        $partner = ' AND (tr.partner = '.$partner.' OR FIND_IN_SET(tr.partner, "'.implode(',', $regions).'"))';
    else
        $partner = ' AND tr.partner = ' . $partner;
}

if(!$userToken['global_admin'])
    $partner = ' AND tr.partner = '.$userToken['id'];

if($points = DB::escape($_REQUEST['points']))
    $points = ' AND FIND_IN_SET(tr.point, "'.$points.'")';

switch($action){

    case 'total':

        $result = array(
            'percent' => array(
                'revenue' => 0,
                'profit' => 0,
                'avg' => 0,
                'count' => 0
            ),
            'days' => []
        );

        $days = DB::query('
            SELECT DATE(tr.created_datetime) AS date,
                SUM(tr.total) as revenue,
                SUM(tr.profit) as profit,
                AVG(tr.total) as avg,
                SUM(tr.id/tr.id) as count
            FROM '.DB_TRANSACTIONS.' tr
            WHERE tr.created BETWEEN '.$mid.' AND '.$end.$partner.$points.'
            GROUP BY DATE(tr.created_datetime)
            ORDER BY tr.id ASC
        ');

        while($row = DB::getRow($days)){

            $result['days'][] = array(
                'date' => $row['date'],
                'revenue' => round($row['revenue'], 2),
                'profit' => round($row['profit'], 2),
                'avg' => round($row['avg'], 2),
                'count' => round($row['count'], 2)
            );

        }

        $percent = DB::query('
            SELECT
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.total, 0)) as revenue_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.total, 0)) as revenue_end,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.profit, 0)) as profit_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.profit, 0)) as profit_end,
                AVG(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.total, null)) as avg_begin,
                AVG(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.total, null)) as avg_end,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', (tr.id/tr.id), 0)) as count_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', (tr.id/tr.id), 0)) as count_end
            FROM '.DB_TRANSACTIONS.' tr
            WHERE tr.created BETWEEN '.$begin.' AND '.$end.$partner.$points.'
        ');
        $percent = DB::getRow($percent);

        $result['percent']['revenue'] = round((double)$percent['revenue_begin'] ? ($percent['revenue_end'] * 100 / $percent['revenue_begin'] - 100) : 100, 2);
        $result['percent']['profit'] = round((double)$percent['profit_begin'] ? ($percent['profit_end'] * 100 / $percent['profit_begin'] - 100) : 100, 2);
        $result['percent']['avg'] = round((double)$percent['avg_begin'] ? ($percent['avg_end'] * 100 / $percent['avg_begin'] - 100) : 100, 2);
        $result['percent']['count'] = round((double)$percent['count_begin'] ? ($percent['count_end'] * 100 / $percent['count_begin'] - 100) : 100, 2);

        response('success', $result, 200);

    break;

    case 'points':

        $result = [];

        $points = DB::query('
            SELECT p.id, p.name,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.total, 0)) as revenue_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.total, 0)) as revenue_end,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.profit, 0)) as profit_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.profit, 0)) as profit_end,
                AVG(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.total, null)) as avg_begin,
                AVG(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.total, null)) as avg_end,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', (tr.id/tr.id), 0)) as count_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', (tr.id/tr.id), 0)) as count_end
            FROM '.DB_TRANSACTIONS.' tr
            JOIN '.DB_PARTNER_POINTS.' p ON p.id = tr.point
            WHERE tr.created BETWEEN '.$begin.' AND '.$end.$partner.$points.'
            GROUP BY tr.point
            ORDER BY revenue_end DESC
        ');

        while($row = DB::getRow($points)){

            $revenue_percent = (double)$row['revenue_begin'] ? ($row['revenue_end'] * 100 / $row['revenue_begin'] - 100) : 100;
            $profit_percent = (double)$row['profit_begin'] ? ($row['profit_end'] * 100 / $row['profit_begin'] - 100) : 100;
            $avg_percent = (double)$row['avg_begin'] ? ($row['avg_end'] * 100 / $row['avg_begin'] - 100) : 100;
            $count_percent = (double)$row['count_begin'] ? ($row['count_end'] * 100 / $row['count_begin'] - 100) : 100;

            $result[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'revenue' => round($row['revenue_end'], 2),
                'profit' => round($row['profit_end'], 2),
                'avg' => round($row['avg_end'], 2),
                'count' => round($row['count_end'], 2),
                'revenue_percent' => round($revenue_percent, 2),
                'profit_percent' => round($profit_percent, 2),
                'avg_percent' => round($avg_percent, 2),
                'count_percent' => round($count_percent, 2)
            );

        }

        response('success', $result, 200);

    break;

    case 'employees':

        $result = [];

        $points = DB::query('
            SELECT e.id, e.name,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.total, 0)) as revenue_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.total, 0)) as revenue_end,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.profit, 0)) as profit_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.profit, 0)) as profit_end,
                AVG(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', tr.total, null)) as avg_begin,
                AVG(IF(tr.created BETWEEN '.$mid.' AND '.$end.', tr.total, null)) as avg_end,
                SUM(IF(tr.created BETWEEN '.$begin.' AND '.$mid.', (tr.id/tr.id), 0)) as count_begin,
                SUM(IF(tr.created BETWEEN '.$mid.' AND '.$end.', (tr.id/tr.id), 0)) as count_end
            FROM '.DB_TRANSACTIONS.' tr
            JOIN '.DB_EMPLOYEES.' e ON e.id = tr.employee
            WHERE tr.created BETWEEN '.$begin.' AND '.$end.$partner.$points.'
            GROUP BY tr.employee
            ORDER BY revenue_end DESC
        ');

        $i = 0;

        while($row = DB::getRow($points)){

            $revenue_percent = (double)$row['revenue_begin'] ? ($row['revenue_end'] * 100 / $row['revenue_begin'] - 100) : 100;
            $profit_percent = (double)$row['profit_begin'] ? ($row['profit_end'] * 100 / $row['profit_begin'] - 100) : 100;
            $avg_percent = (double)$row['avg_begin'] ? ($row['avg_end'] * 100 / $row['avg_begin'] - 100) : 100;
            $count_percent = (double)$row['count_begin'] ? ($row['count_end'] * 100 / $row['count_begin']) : 100;

            $result[] = array(
                'id' => $row['id'],
                'name' => $row['name'],
                'revenue' => round($row['revenue_end'], 2),
                'profit' => round($row['profit_end'], 2),
                'avg' => round($row['avg_end'], 2),
                'count' => round($row['count_end'], 2),
                'revenue_percent' => round($revenue_percent, 2),
                'profit_percent' => round($profit_percent, 2),
                'avg_percent' => round($avg_percent, 2),
                'count_percent' => round($count_percent, 2)
            );

            $i++;

        }

        response('success', $result, 200);

    break;

}