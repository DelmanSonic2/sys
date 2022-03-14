<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

switch($action){

    case 'point':

        $row = DB::query('SELECT pp.id, pp.name AS pname, pp.address, p.id AS pid, p.name, p.surname, p.middlename, p.city, pp.fiscal_cash
                                        FROM '.DB_PARTNER_POINTS.' pp
                                        JOIN '.DB_PARTNER.' AS p ON p.id = pp.partner
                                        WHERE pp.id = '.$pointToken['id']);

        $row = DB::getRow($row);

        $pointInfo = array(
            'id' => $row['id'],
            'name' => $row['pname'],
            'fiscal_cash' => $row['fiscal_cash'],
            'address' => $row['address'],
            'currency' => CURRENCY,
            'round_price' => ROUND_PRICE
        );

        $partnerInfo = array('id' => $row['pid'],
                        'name' => $row['name'],
                        'surname' => $row['surname'],
                        'middlename' => $row['middlename'],
                        'city' => $row['city']);

        $employees = [];

        $employeesData = DB::query('SELECT e.id, e.name, e.email, e.pin_code, e.position, e.employed, p.name AS pname
                                            FROM '.DB_EMPLOYEES.' e
                                            JOIN '.DB_POSITIONS.' AS p ON p.id = e.position
                                            WHERE e.partner = '.$pointToken['partner'].' AND p.terminal = 1 AND e.pin_code != "" AND e.deleted = 0');

        while($row = DB::getRow($employeesData)){

            $row['employed'] = (bool)$row['employed'];

            $row['position'] = array('id' => $row['position'],
                                    'name' => $row['pname']);

            unset($row['pname']);

            $employees[] = $row;
        }

        $result = array('point' => $pointInfo,
                        'partner' => $partnerInfo,
                        'employees' => $employees);

        response('success', $result, '7');

    break;

    case 'shift':

        if(!$shift = DB::escape($_REQUEST['shift']))
            response('error', 'Не передан ID смены.', 1);

        $shift = DB::select('*', DB_EMPLOYEE_SHIFTS, "id LIKE '{$shift}' AND point = {$pointToken['id']}", '', 1);
        if(!DB::getRecordCount($shift)) response('error', 'Смена не найдена.', 1);
        $shift = DB::getRow($shift);

        $shift = array(
            'shift_from' => (int)$shift['shift_from'],
            'shift_to' => (int)$shift['shift_to'],
            'revenue' => number_format($shift['revenue'], 2, ',', ' ').' '.CURRENCY,
            'hours' => (int)$shift['hours'],
            'salary' => number_format($shift['salary'], 2, ',', ' ').' '.CURRENCY,
            'shift_closed' => (boolean)$shift['shift_closed'],
            'premium' => (boolean)$shift['premium']
        );

        response('success', $shift, 7);

        break;

}