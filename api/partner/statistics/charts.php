<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

//Тут получаем условия выборки по партнерам
include 'all_partners.php';

$to = (DB::escape($_REQUEST['to'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['to']) + (24 * 60 * 60) )) : strtotime(date('Y-m-d', strtotime("+1 days")));
$from = (DB::escape($_REQUEST['from'])) ? strtotime(date('Y-m-d', DB::escape($_REQUEST['from']))) : strtotime(date('Y-m-d', strtotime("-1 months")));

function WeekDays($user, $from, $to) {

    

    $weekdays_data = [];
    $result = [];

    if($point = DB::escape($_REQUEST['point']))
        $point = ' AND tr.point = '.$point;

    $weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

    for($i = 0; $i < 7; $i++)
        $weekdays_data[] = array('weekday' => $i,
                        'weeks_count' => 1);

    //$weeks = DB::query('CALL WeekDays('.$from.', '.$to.', '.$user.', '.(int)$point.');');

    $weeks = DB::query(' SELECT SUM(tr.total) AS sales, SUM(tr.profit) AS profit, COUNT(tr.id) AS checks, WEEKDAY(tr.created_datetime) weekday, AVG(tr.total) AS avg_check, COUNT(WEEKDAY(tr.created_datetime)) AS days, COUNT(tr.shift) AS shifts
                                FROM '.DB_TRANSACTIONS.' AS tr
                                WHERE tr.created BETWEEN '.$from.' AND '.$to.$user.$point.'
                                GROUP BY DAY(tr.created_datetime)
                                ORDER BY weekday');

    while ($row = DB::getRow($weeks)) {
        $flag = false;
        for ($i = 0; $i < sizeof($weekdays_data); $i++) {

            if ($weekdays_data[$i]['weekday'] == $row['weekday']) {
                $weekdays_data[$i]['sales'] += $row['sales'];
                $weekdays_data[$i]['profit'] += $row['profit'];
                $weekdays_data[$i]['checks'] += $row['checks'];
                 $weekdays_data[$i]['shifts'] += $row['shifts'];
                 $weekdays_data[$i]['days'] += $row['days'];
                $weekdays_data[$i]['avg_check'] += $row['avg_check'];
                $weekdays_data[$i]['weeks_count']++;

                break;

            }

        }

    }

    for ($i = 0; $i < sizeof($weekdays_data); $i++)
        $result[] = array(
        'label' => $weekdays[$weekdays_data[$i]['weekday']],
        'sales' => round($weekdays_data[$i]['sales'] / $weekdays_data[$i]['weeks_count'], 2),
        'profit' => round($weekdays_data[$i]['profit'] / $weekdays_data[$i]['weeks_count'], 2),
        'checks' => (int)$weekdays_data[$i]['checks'],
         'shifts' => (int)$weekdays_data[$i]['shifts'],
        'days' => (int)$weekdays_data[$i]['days'],
        'avg_check' => round($weekdays_data[$i]['avg_check'] / $weekdays_data[$i]['weeks_count'], 2)
    );

    return $result;

}

function PopularProducts($not, $from, $to, $user) {

    

    $not = ($not == 0) ? 'DESC' : 'ASC';

    if($point = DB::escape($_REQUEST['point']))
        $point = ' AND tr.point = '.$point;

    $popular_products = DB::query('SELECT pr.id, pr.name, SUM(ti.count) AS count
                                            FROM ' . DB_TRANSACTION_ITEMS . ' ti
                                            JOIN ' . DB_TRANSACTIONS . ' AS tr ON tr.id = ti.transaction
                                            JOIN ' . DB_PRODUCTS . ' AS pr ON pr.id = ti.product
                                            WHERE tr.created BETWEEN ' . $from .' AND '. $to.$user.$point.'
                                            GROUP BY ti.product
                                            ORDER BY count ' . $not . '
                                            LIMIT 10');

    $popular_products = DB::makeArray($popular_products);

    return $popular_products;
}

function ChartMonths($user, $from, $to){

    

    $result = [];

    if($point = DB::escape($_REQUEST['point']))
        $point = ' AND tr.point = '.$point;

    //$months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    //$data = DB::query('CALL ChartMonths('.$from.', '.$to.', '.$user.', '.$point.');');

    $data = DB::query('  SELECT SUM(tr.total) AS sales, SUM(tr.profit) AS profit, COUNT(tr.id) AS checks, AVG(tr.total) AS avg_check, MONTH(tr.created_datetime) as month, YEAR(tr.created_datetime) as year, MIN(DAY(tr.created_datetime)) AS minday, MAX(DAY(tr.created_datetime)) AS maxday
                                FROM '.DB_TRANSACTIONS.' as tr
                                WHERE tr.created BETWEEN '.$from.' AND '.$to.$user.$point.'
                                GROUP BY MONTH(tr.created_datetime), YEAR(tr.created_datetime)
                                ORDER BY YEAR(tr.created_datetime), MONTH(tr.created_datetime)');

    while($row = DB::getRow($data)){
        $month = $months[$row['month'] - 1];

        $result[] = array('label' => $row['minday'].' - '.$row['maxday'].' '.$month.' '.$row['year'],
                        'sales' => round($row['sales'] , 2),
                        'profit' => round($row['profit'] , 2),
                        'checks' => round($row['checks'] , 2),
                        'avg_check' => round($row['avg_check'] , 2));
    }

    return $result;

}

function ChartWeeks($user, $from, $to){

    

    $result = [];

    if($point = DB::escape($_REQUEST['point']))
        $point = ' AND tr.point = '.$point;
    
    //$months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    //$data = DB::query('CALL ChartWeeks('.$from.', '.$to.', '.$user.', '.$point.');');

    $data = DB::query('  SELECT SUM(tr.total) AS sales, SUM(tr.profit) AS profit, COUNT(tr.id) AS checks, AVG(tr.total) AS avg_check, WEEK(tr.created_datetime) AS week, MONTH(tr.created_datetime) as month, YEAR(tr.created_datetime) as year, MIN(DAY(tr.created_datetime)) AS minday, MAX(DAY(tr.created_datetime)) AS maxday
                                FROM '.DB_TRANSACTIONS.' as tr
                                WHERE tr.created BETWEEN '.$from.' AND '.$to.$user.$point.'
                                GROUP BY WEEK(tr.created_datetime), MONTH(tr.created_datetime), YEAR(tr.created_datetime)
                                ORDER BY MONTH(tr.created_datetime), WEEK(tr.created_datetime), YEAR(tr.created_datetime)');

    while($row = DB::getRow($data)){
        $month = $months[$row['month'] - 1];

        $result[] = array('label' => $row['minday'].' - '.$row['maxday'].' '.$month.' '.$row['year'],
                        'sales' => round($row['sales'] , 2),
                        'profit' => round($row['profit'] , 2),
                        'checks' => round($row['checks'] , 2),
                        'avg_check' => round($row['avg_check'] , 2));
    }

    return $result;

}

function ChartDays($user, $from, $to){
    

    $result = [];

    if($point = DB::escape($_REQUEST['point']))
        $point = ' AND tr.point = '.$point;

    //$months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];

    //$data = DB::query('CALL ChartDays('.$from.', '.$to.', '.$user.', '.$point.');');

    $data = DB::query('  SELECT SUM(tr.total) AS sales, SUM(tr.profit) AS profit, COUNT(tr.id) AS checks, AVG(tr.total) AS avg_check, DAY(tr.created_datetime) AS day, MONTH(tr.created_datetime) as month, YEAR(tr.created_datetime) as year
                                FROM '.DB_TRANSACTIONS.' tr
                                WHERE tr.created BETWEEN '.$from.' AND '.$to.$user.$point.'
                                GROUP BY DAY(tr.created_datetime), MONTH(tr.created_datetime), YEAR(tr.created_datetime)
                                ORDER BY YEAR(tr.created_datetime), MONTH(tr.created_datetime), DAY(tr.created_datetime)');

    while($row = DB::getRow($data)){
        $month = $months[$row['month'] - 1];

        $result[] = array('label' => $row['day'].' '.$month.' '.$row['year'],
                        'sales' => round($row['sales'] , 2),
                        'profit' => round($row['profit'] , 2),
                        'checks' => round($row['checks'] , 2),
                        'avg_check' => round($row['avg_check'] , 2));
    }

    return $result;
}

function Hours($user, $from, $to){

    

    $result = [];

    if($point = DB::escape($_REQUEST['point']))
        $point = ' AND tr.point = '.$point;

    for($i = 0; $i < 24; $i++)
        $hours[] = array('hour' => $i,
                        'hour_count' => 1);

    //$data = DB::query('CALL Hours('.$from.', '.$to.', '.$user.', '.$point.');');

    $data = DB::query('  SELECT SUM(tr.total) AS sales, SUM(tr.profit) AS profit, COUNT(tr.id) AS checks, AVG(tr.total) AS avg_check, HOUR(tr.created_datetime) AS hour,
                            DAY(tr.created_datetime) AS day,
                            MONTH(tr.created_datetime) AS month,
                            YEAR(tr.created_datetime) AS year
                                FROM `app_transactions` as tr
                                WHERE tr.created BETWEEN '.$from.' AND '.$to.$user.$point.'
                                GROUP BY hour, day, month, year
                                ORDER BY year, month, day, hour');

    while ($row = DB::getRow($data)) {

        for ($i = 0; $i < sizeof($hours); $i++) {

            if ($hours[$i]['hour'] == $row['hour']) {
                $hours[$i]['sales'] += $row['sales'];
                $hours[$i]['profit'] += $row['profit'];
                $hours[$i]['checks'] += $row['checks'];
                $hours[$i]['avg_check'] += $row['avg_check'];
                $hours[$i]['hour_count']++;

                break;

            }

        }

    }

    for ($i = 0; $i < sizeof($hours); $i++)
        $result[] = array('label' => $hours[$i]['hour'],
                        'sales' => round($hours[$i]['sales'] / $hours[$i]['hour_count'], 2),
                        'profit' => round($hours[$i]['profit'] / $hours[$i]['hour_count'], 2),
                        'checks' => (int)$hours[$i]['checks'],
                        'avg_check' => round($hours[$i]['avg_check'] / $hours[$i]['hour_count'], 2)
    );
    
    return $result;

}

switch ($action) {

    case 'weekdays':

        $result = WeekDays($where_partner, $from, $to);

        response('success', $result, '7');

        break;

    case 'popular':

        $popular = PopularProducts(0, $from, $to, $where_partner);
        $unpopular = PopularProducts(1, $from, $to, $where_partner);

        $result = array('popular' => $popular,
                        'unpopular' => $unpopular);

        response('success', $result, '7');

        break;

    case 'chart':

        $type = DB::escape($_REQUEST['type']);

        $result = [];

        switch($type){

            case 'days':
                $result = ChartDays($where_partner, $from, $to);
            break;

            case 'weeks':
                $result = ChartWeeks($where_partner, $from, $to);
            break;

            case 'months':
                $result = ChartMonths($where_partner, $from, $to);
            break;

        }

        response('success', $result, '7');

        break;

    case 'hours':

        $result = Hours($where_partner, $from, $to);

        response('success', $result, '7');

        break;
}