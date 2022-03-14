<?php
use Support\Pages;
use Support\DB;

class Filters{

    protected $filters;
    protected $operations_num;
    protected $opetation_date;

    public function __construct(){
        $this->operations_num = [['name' => 'Равно', 'operator' => '=', 'type' => 'number', 'default_value' => ''],
                                ['name' => 'Не равно', 'operator' => '!=', 'type' => 'number', 'default_value' => ''],
                                ['name' => 'Больше', 'operator' => '>', 'type' => 'number', 'default_value' => ''],
                                ['name' => 'Меньше', 'operator' => '<', 'type' => 'number', 'default_value' => ''],
                                ['name' => 'Больше или равно', 'operator' => '>=', 'type' => 'number', 'default_value' => ''],
                                ['name' => 'Меньше или равно', 'operator' => '<=', 'type' => 'number', 'default_value' => '']];

        $this->operations_date = [  ['name' => 'После', 'operator' => '>', 'type' => 'date', 'default_value' => date('Y-m-d', time())],
                                    ['name' => 'До', 'operator' => '<', 'type' => 'date', 'default_value' => date('Y-m-d', time())],
                                    ['name' => 'Менее N дней назад', 'operator' => '=', 'type' => 'number', 'default_value' => ''],
                                    ['name' => 'Более N дней назад', 'operator' => '=', 'type' => 'number', 'default_value' => ''],
                                    ['name' => 'Ровно N дней назад', 'operator' => '=', 'type' => 'number', 'default_value' => '']];

    }

    public function get(){

        return $this->filters;

    }

}

class statistics extends Filters{

    public function products(){

        $this->filters = 1;
        
        return $this;

    }

}

class warehouse extends Filters{



}

class menu extends Filters{

    public function products(){

        $this->filters = 2;

        return $this;

    }

}