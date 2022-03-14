<?php
use Support\Pages;
use Support\DB;

require_once 'CalcWarehouse.php';

class MovingClass extends ItemsHistory{

    public $items;
    private $fields;
    private $moving;
    private $insert_items = [];

    public function __construct($db, $fields, $items, $partner, $point, $date){
        
        // $this->db = $db;
        $this->fields = $fields;
        $this->items = $this->JsonToArray($items);
        $this->point = $point;
        $this->partner = $partner;
        $this->proccess = 1;                         //proccess = 1 - процесс "Перемещение"
        $this->date = $date;
        
    }

    /*$item - объект "ингредиент", $i - итерация*/
    private function ItemValidate($item, $i){ // Общая функция, которая проверяет одну позицию

        if(!$item['id'] || !$item['count'] || !$item['price']){

            DB::delete(DB_SUPPLIES, 'id = '.$this->moving);

            if($item['name'])
                response('error', 'Неверно заполнен ингредиент: "'.$item['name'].'".', 1);
            else
                response('error', 'Неверно заполнена таблица ингредиентов. Строка '.($i + 1).'.', 1);

        }

    }

    /*Создание перемещения*/
    public function create(){

        $this->fields['status'] = 4;

        if(!$moving = DB::insert($this->fields, DB_SUPPLIES))
            response('error', 'Не удалось создать перемещение.', 1);

        $this->moving = $moving;
        $this->proccess_id = $moving;

        $this->prepare_items();
        $this->insert_items();

    }

    public function edit($moving){
        $this->moving = $moving;
        $this->proccess_id = $moving;
        DB::update($this->fields, DB_SUPPLIES, "id = $moving");
        $this->prepare_items();
        DB::delete(DB_SUPPLY_ITEMS, "supply = $moving");
        $this->insert_items();
    }

    /*Списание позиций со склада откуда перемещаем*/
    public function remove(){

        foreach($this->items as $key => $item){
            $this->ItemValidate($item, $key);
            $item['count'] *= -1;
            $this->AddItem($item);
        }

        $this->GetPointBalance();

    }

    /*Создание списка ингредиентов в перемещении*/
    private function prepare_items(){

        foreach($this->items as $key => $item){
            $this->ItemValidate($item, $key);

            $supply_item = array(
                'id' => $item['conversion_item_id'] ?: $item['id'],
                'count' => $item['count'],
                'price' => $item['price']
            );

            $this->AddItem($supply_item);
            $sum = $item['price'] * $item['count'];
            $item['conversion_item_id'] = $item['conversion_item_id'] ?: 'NULL';
            $this->insert_items[] = "({$this->moving}, {$item['id']}, {$item['conversion_item_id']}, {$item['count']}, {$item['price']}, {$sum}, 0, {$sum})";
        }

    }

    private function insert_items(){
        $insert_items = implode(',', $this->insert_items);
        if($insert_items) {
            DB::query('INSERT INTO ' . DB_SUPPLY_ITEMS . ' (supply, item, conversion_item_id, count, price, sum, tax, total) VALUES ' . $insert_items);
            $this->GetPointBalance();
        }
    }

}