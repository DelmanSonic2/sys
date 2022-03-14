<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';
require_once ROOT . 'api/classes/ShiftClass.php';

$res = [];

//Получаем список партнеров
$partners = DB::query('
    SELECT p.id, c.code
    FROM ' . DB_PARTNER . ' p
    JOIN ' . DB_CITIES . ' c ON c.id = p.city
');

while ($row = DB::getRow($partners)) {

    //Устанавливаем часовой пояс партнера
    date_default_timezone_set($row['code']);

    //Если не наступило 00 часов, то пропускаем
    if (date('H', time()) != '00')
        continue;

    $arr = [];

    $shiftData = DB::query(' SELECT s.id, s.shift_from, s.shift_to, s.shift_closed, s.point, p.coefficient, p.plan_percentage, pp.plan, s.revenue, p.rate
                                    FROM ' . DB_EMPLOYEE_SHIFTS . ' s
                                    JOIN ' . DB_EMPLOYEES . ' AS e ON e.id = s.employee
                                    LEFT JOIN ' . DB_POSITIONS . ' AS p ON p.id = e.position
                                    LEFT JOIN ' . DB_POINTS_PLAN . ' AS pp ON pp.date = DATE_FORMAT(FROM_UNIXTIME(s.shift_from), "%Y-%m-%d") AND pp.point = s.point
                                    WHERE e.partner = ' . $row['id'] . ' AND s.shift_closed = 0 AND s.shift_from < ' . time());

    while ($shift = DB::getRow($shiftData)) {

        $shift_end = strtotime(date('Y-m-d', $shift['shift_from'])) + 21 * 60 * 60;

        $hours = round(($shift_end - $shift['shift_from']) / 60 / 60); //Количество часов, отработанных сотрудником за смену

        $salary = $hours * $shift['rate'];

        if ($shift['plan'] != null) { // Если план был задан
            $plan_rate = $shift['plan_percentage'] * $shift['plan'] / 100; //Норма плана для конкретного сотрудника

            if ($shift['revenue'] >= $plan_rate) { // Если сотрудник выполнил план, то начисляем премиальные
                $premium = true;
                $percent = $salary * $shift['coefficient'] / 100;
                $salary += $percent;
            }
        }

        if ($shift['plan'] == null) { // Если план не был задан, то премиальные начисляются автоматически

            $premium = true;
            $percent = $salary * $shift['coefficient'] / 100;
            $salary += $percent;
        }

        $fields = array(
            'shift_to' => $shift_end,
            'hours' => $hours,
            'salary' => $salary,
            'premium' => $premium,
            'shift_closed' => 1
        );

        DB::update($fields, DB_EMPLOYEE_SHIFTS, 'id = "' . $shift['id'] . '"');

        Shift::createIncomeOrder(false, $shift['id'], $shift['point'], $shift_end);
    }
}
