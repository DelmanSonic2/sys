<?php
use Support\Pages;
use Support\DB;

class Shift{

    private $db;
    private $premium;
    private $point;
    private $date_end;

    public function __construct($db, $point, $date_end){

        // $this->db = $db;
        $this->point = $point;
        $this->premium = false;
        $this->date_end = $date_end;

    }

    public function close($key){

        $shiftData = DB::query('
            SELECT s.id, s.shift_from, p.coefficient, p.plan_percentage, pp.plan, s.revenue, p.rate
            FROM '.DB_EMPLOYEE_SHIFTS.' s
            LEFT JOIN '.DB_EMPLOYEES.' AS e ON e.id = s.employee
            LEFT JOIN '.DB_POSITIONS.' AS p ON p.id = e.position
            LEFT JOIN '.DB_POINTS_PLAN.' AS pp ON pp.date = "'.date('Y-m-d', time()).'" AND pp.point = "'.$this->point.'"
            WHERE s.id = "'.$key.'" AND s.shift_closed = 0 AND s.point = '.$this->point
        );

        if(DB::getRecordCount($shiftData) == 0)
            return false;

        $shiftData = DB::getRow($shiftData);

        $hours = round(($this->date_end - $shiftData['shift_from']) / 60 / 60); //Количество часов, отработанных сотрудником за смену

        $salary = $hours * $shiftData['rate'];

        if($shiftData['plan'] != null){ // Если план был задан
            $plan_rate = $shiftData['plan_percentage'] * $shiftData['plan'] / 100; //Норма плана для конкретного сотрудника

            if($shiftData['revenue'] >= $plan_rate){ // Если сотрудник выполнил план, то начисляем премиальные
                $this->premium = true;
                $percent = $salary * $shiftData['coefficient'] / 100;
                $salary += $percent;
            }
        }

        if($shiftData['plan'] == null){ // Если план не был задан, то премиальные начисляются автоматически

            $this->premium = true;
            $percent = $salary * $shiftData['coefficient'] / 100;
            $salary += $percent;

        }

        $fields = array('shift_to' => $this->date_end,
                        'hours' => $hours,
                        'salary' => $salary,
                        'premium' => $this->premium,
                        'shift_closed' => 1);

        DB::update($fields, DB_EMPLOYEE_SHIFTS, 'id = "'.$key.'"');

        $error_str = DB::getLastError();

        self::createIncomeOrder($this->db, $shiftData['id'], $this->point);

        if($error_str)
            return false;

        return true;

    }

    public static function createIncomeOrder($db, $shiftId, $pointId, $shift_end = false){

        $shift_end = $shift_end ? $shift_end : time();

        $income = DB::getRow(DB::query('SELECT SUM(t.total) as val
                                    FROM '.DB_TRANSACTIONS.' t
                                    WHERE t.type = 1 AND t.shift = "'.$shiftId.'"
                                    '));
        
        if(isset($income['val']) && $income['val']){
        
            $fields = array(
                'point' => $pointId,
                'created_datetime' => date('Y-m-d H:i:s', $shift_end),
                'sum' => $income['val'],
                'shift_id' => $shiftId
            );

            DB::insert($fields, DB_PARTNER_ORDERS_INCOME);
        }
    }

}