<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

function Filter($filter){

    $filter = stripslashes($filter);

    $filter = json_decode($filter, true);

    if($filter == null || sizeof($filter) == 0)
        return '';

    for($i = 0; $i < sizeof($filter); $i++){

        if($filter[$i]['field'] == 'hours'){

            if(!$result)
                $result = '(hours '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';
            else
                $result .= ' OR (hours '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';

        }
        if($filter[$i]['field'] == 'count'){

            if(!$result)
                $result = '(count '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';
            else
                $result .= ' OR (count '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';

        }
        if($filter[$i]['field'] == 'total'){

            if(!$result)
                $result = '(total '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';
            else
                $result .= ' OR (total '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';

        }
        if($filter[$i]['field'] == 'revenue'){

            if(!$result)
                $result = '(revenue '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';
            else
                $result .= ' OR (revenue '.$filter[$i]['operation'].' '.$filter[$i]['value'].')';

        }

    }

    return 'HAVING '.$result;

}

switch($action){

    case 'get':

        if(!$from = DB::escape($_REQUEST['from']))
            $from = strtotime(date('Y-m', time()));

        if(!$to = DB::escape($_REQUEST['to']))
            $to = strtotime('+1 month', $from);

        if($filter = DB::escape($_REQUEST['filter']))
            $filterStr = Filter($filter);

        if($position = DB::escape($_REQUEST['position'])){
            $position = explode(',', $position);

            for($i = 0; $i < sizeof($position); $i++){
                if(!$positionStr)
                    $positionStr = ' AND (e.position = '.$position[$i];
                else
                    $positionStr .= ' OR e.position = '.$position[$i];
            }

            $positionStr .= ')';

        }

        $ORDER_BY = Order::finances_salary(Pages::$field, Pages::$order);

        $cashierData = DB::query('SELECT e.id, e.name, p.id AS position, p.name AS pname, SUM(sh.hours) AS hours,
                COUNT(sh.id) AS count, SUM(sh.salary) as total, p.rate,
                SUM(sh.revenue) AS revenue
                        FROM '.DB_EMPLOYEE_SHIFTS.' sh
                        JOIN '.DB_EMPLOYEES.' AS e ON e.id = sh.employee
                        LEFT JOIN '.DB_POSITIONS.' AS p ON p.id = e.position
                        WHERE e.partner = '.$userToken['id'].' AND sh.shift_from >= '.$from.' AND sh.shift_to < '.$to.$positionStr.'
                        GROUP BY sh.employee ASC
                        '.$ORDER_BY.'
                        '.$filterStr);

        $result = [];

        while($row = DB::getRow($cashierData)){

            $row['position'] = array('id' => $row['position'],
                                    'name' => $row['pname']);
                            
            unset($row['pname']);

            $result[] = $row;
            
        }

        response('success', $result, '7');

    break;

}