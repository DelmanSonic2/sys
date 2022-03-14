<?php
use Support\Pages;
use Support\DB;

//Пример http://localhost:8888/cw/api/partner/warehouse/inventory/create?token=1&point=1&items=[{%22id%22:412,%22count%22:80},{%22id%22:466,%22count%22:34}]

/*

ВАЖНО!!!

Если type который передан при создании экземпляра класса равен 1, то действия по добавлению и удалению товаров производятся партнером,
Тогда в employee необходимо передать ID партнера.

Если переданный type равен 0, то действия с товарами на складе производятся со стороны кассира.
В таком случае, в employee необходимо передать ID кассира.

Если необходимо списать товары со склада, то end_balance должен быть больше чем begin_balance

*/

class PointItems{

    public $db;
    public $items_minus;
    public $items_plus;
    private $items_where;
    private $point;
    private $type;
    private $employee;
    private $items_where_plus;

    public function __construct($db, $point, $employee, $type){
        
        // $this->db = $db;
        $this->items_minus = [];
        $this->items_plus = [];
        $this->items_where = '';
        $this->point = $point;
        $this->type = $type;
        $this->employee = $employee;
        $this->items_where_plus = '';

    }

    public function setItem($item){ // Переименовать

        $item['count'] = $item['end_balance'] - $item['begin_balance'];
        
        if($item['count'] >= 0){

            $this->items_plus[] = $item;

            if(!$this->items_where_plus)
                $this->items_where_plus = 'item = '.$item['id'];
            else
                $this->items_where_plus .= ' OR item = '.$item['id'];

        }
        else{

            $item['count'] *= -1;
            
            $this->items_minus[] = $item;
            
            if(!$this->items_where)
                $this->items_where = 'item = '.$item['id'];
            else
                $this->items_where .= ' OR item = '.$item['id'];

        }

    }

    private function plusItems(){

        $point_items = [];

        if(!$this->items_where_plus)
            return;

        $query = DB::query('SELECT *
                                        FROM '.DB_POINT_ITEMS.'
                                        WHERE ('.$this->items_where_plus.') AND point = '.$this->point.' AND count > 0
                                        GROUP BY item');

        while($row = DB::getRow($query)){

            for($i = 0; $i < sizeof($this->items_plus); $i++){

                if($this->items_plus[$i]['id'] == $row['item']){

                    $row['count'] += $this->items_plus[$i]['count'];
                    
                    if(!$insert_into_table)
                        $insert_into_table = '("'.$row['id'].'", "'.$row['count'].'", "'.$this->employee.'", "'.$this->type.'")';
                    else
                        $insert_into_table .= ', ("'.$row['id'].'", "'.$row['count'].'", "'.$this->employee.'", "'.$this->type.'")';
                }

            }

        }

        if($insert_into_table){
            //Добавляем во временную таблицу данные
            DB::query('INSERT INTO '.DB_POINT_ITEMS_TMP.' (position, count, employee, type) VALUES '.$insert_into_table);
            
        }

    }

    private function minusItems(){
        if($this->items_where == '')
            return;

        //Получаем список ингредиентов на складе
        $point_items = DB::query('SELECT *
                                            FROM '.DB_POINT_ITEMS.'
                                            WHERE ('.$this->items_where.') AND point = '.$this->point.' AND count > 0
                                            ORDER BY id');

        $point_items = DB::makeArray($point_items);

        //Алгоритм, который высчитывает, сколько нужно вычесть со склада того, или иного ингредиента
        for($i = 0; $i < sizeof($this->items_minus); $i++){
            for($j = 0; $j < sizeof($point_items); $j++){

                if($this->items_minus[$i]['id'] == $point_items[$j]['item']){
                    
                    $minus = $point_items[$j]['count'] - $this->items_minus[$i]['count'];

                    if($minus < 0){

                        $minus = 0;

                        $this->items_minus[$i]['count'] -= $point_items[$j]['count'];

                    }
                    else{
                        $point_items[$j]['minus'] = $minus;
                        break;
                    }

                    $point_items[$j]['minus'] = $minus;

                }

            }

        }

        //==============Формируем список значений, добавляемых во временную таблицу=================
        for($i = 0; $i < sizeof($point_items); $i++){

            if(isset($point_items[$i]['minus'])){
                if(!$insert_into_table)
                    $insert_into_table = '("'.$point_items[$i]['id'].'", "'.$point_items[$i]['minus'].'", "'.$this->employee.'", "'.$this->type.'")';
                else
                    $insert_into_table .= ', ("'.$point_items[$i]['id'].'", "'.$point_items[$i]['minus'].'", "'.$this->employee.'", "'.$this->type.'")';
            }

        }

        if($insert_into_table){
            //Добавляем во временную таблицу данные
            DB::query('INSERT INTO '.DB_POINT_ITEMS_TMP.' (position, count, employee, type) VALUES '.$insert_into_table);
        }
    }

    public function moveVariables(){
        //Перемещаем значения из временной таблицы
        DB::query('UPDATE '.DB_POINT_ITEMS.'
                        SET '.DB_POINT_ITEMS.'.`count` = (SELECT '.DB_POINT_ITEMS_TMP.'.`count`
                                                        FROM '.DB_POINT_ITEMS_TMP.'
                                                        WHERE '.DB_POINT_ITEMS_TMP.'.`position` = '.DB_POINT_ITEMS.'.`id`)
                        WHERE '.DB_POINT_ITEMS.'.`id` = (SELECT '.DB_POINT_ITEMS_TMP.'.`position`
                                                        FROM '.DB_POINT_ITEMS_TMP.'
                                                        WHERE '.DB_POINT_ITEMS_TMP.'.`employee` = '.$this->employee.' AND '.DB_POINT_ITEMS_TMP.'.`position` = '.DB_POINT_ITEMS.'.`id` AND '.DB_POINT_ITEMS_TMP.'.`type` = '.$this->type.')');
    }

    public function deleteTmpVariables(){
        //Удаляем записи из временной таблицы
        DB::delete(DB_POINT_ITEMS_TMP, 'employee = '.$this->employee.' AND type = '.$this->type);
    }

    public function InsertTmpValues(){
        
        $this->minusItems();
        $this->plusItems();
        
    }

}