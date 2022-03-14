<?php
use Support\Pages;
use Support\DB;

require_once 'CalcWarehouse.php';

class SupplyClass extends ItemsHistory{ // Класс для работы с поставками

    private $fields;
    public $supply;
    private $insert_items = [];

    public function __construct($db, $fields, $items, $partner, $point, $date){
        
        $this->point = $point;
        $this->partner = $partner;
        // $this->db = $db;                            //Экземпляр класса modx для работы с БД
        $this->items = $this->JsonToArray($items);  //Преобразуем JSON в массив
        $this->fields = $fields;                    //Получаем поля для создания транзакции
        $this->proccess = 0;                         //proccess = 0 - процесс "Поставка"
        $this->date = $date;

    }

    /*$item - объект "ингредиент", $i - итерация*/
    private function ItemValidate($item, $i){ // Общая функция, которая проверяет одну позицию

        if(!$item['id'] || $item['count'] <= 0 || $item['price'] <= 0){


            DB::delete(DB_SUPPLIES, 'id = '.$this->supply);

            if($item['name'])
                response('error', 'Неверно заполнен ингредиент: "'.$item['name'].'".', 1);
            else
                response('error', 'Неверно заполнена таблица ингредиентов. Строка '.($i + 1).'.', 1);

        }

    }

    /*Создание поставки*/
    public function create(){

        $this->fields['status'] = 4;

        if(!$supply = DB::insert($this->fields, DB_SUPPLIES))
            response('error', 'Не удалось создать поставку.', 1);

        $this->supply = $supply;
        $this->proccess_id = $supply;

        $this->prepare_items();
        $this->insert_items();

    }

    public function edit($supply){
        $this->supply = $supply;
        $this->proccess_id = $supply;
        DB::update($this->fields, DB_SUPPLIES, "id = $supply");
        $this->prepare_items();
        DB::delete(DB_SUPPLY_ITEMS, "supply = $supply");
        $this->insert_items();
    }

    /*Создание списка ингредиентов в списании*/
    private function prepare_items(){

        for($i = 0; $i < sizeof($this->items); $i++){

            $this->ItemValidate($this->items[$i], $i);

            $this->AddItem($this->items[$i]);

            $this->items[$i]['sum'] = $this->items[$i]['count'] * $this->items[$i]['price'];

            $this->insert_items[] = '("'.$this->supply.'", "'.$this->items[$i]['id'].'", "'.$this->items[$i]['count'].'", "'.$this->items[$i]['price'].'", "'.$this->items[$i]['sum'].'", "0", "'.$this->items[$i]['sum'].'")';
        }

    }

    private function insert_items(){
        $insert_items = implode(',', $this->insert_items);
        if($insert_items) {
            DB::query('INSERT INTO ' . DB_SUPPLY_ITEMS . ' (supply, item, count, price, sum, tax, total) VALUES ' . $insert_items);
            $this->GetPointBalance();
        }
    }

}