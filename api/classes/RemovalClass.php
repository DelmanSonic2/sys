<?php
use Support\Pages;
use Support\DB;

require_once 'CalcWarehouse.php';
require_once 'ProductionCostPriceClass.php';

class RemovalClass extends ItemsHistory{ // Класс для работы с поставками

    private $fields;
    private $removal;
    private $get_item_price;
    private $removal_items;
    private $get_technical_card_composition;
    private $productionCalcPrice;
    private $insert_items = [];
    public $edit_mode = false;
                               
    public function __construct($db, $fields, $items, $partner, $point, $date){

        $this->removal_items = [];
        $this->point = $point;
        $this->partner = $partner;
        // $this->db = $db;                            //Экземпляр класса modx для работы с БД
        $this->items = $this->JsonToArray($items);  //Преобразуем JSON в массив
        $this->fields = $fields;                    //Получаем поля для создания транзакции
        $this->proccess = 3;                         //proccess = 3 - процесс "Списания"
        $this->date = $date;
        $this->productionCalcPrice = new ProductionCostPrice($db, $partner, $point);

    }

  
    /*$item - объект "ингредиент", $i - номер итерации*/
    private function ItemValidate($item, $i){ // Общая функция, которая проверяет одну позицию

        if(!$item['type']){
            if(!$item['id'] || !$item['count'] || !$item['price']){

                if(!$this->edit_mode)
                DB::delete(DB_REMOVALS, 'id = '.$this->removal);

                if($item['name'])
                    response('error', 'Неверно заполнен ингредиент: "'.$item['name'].'".', 1);
                else
                    response('error', 'Неверно заполнена таблица ингредиентов. Строка '.($i + 1).'.', 1);

            }

            if(!$this->get_item_price)
                $this->get_item_price = 'item = '.$item['id'];
            else
                $this->get_item_price .= ' OR item = '.$item['id'];
        }
        elseif($item['type'] == 1){

            if(!$item['id'] || !$item['count']){

                if(!$this->edit_mode)
                DB::delete(DB_REMOVALS, 'id = '.$this->removal);

                if($item['name'])
                    response('error', 'Неверно заполнена тех. карта: "'.$item['name'].'".', 1);
                else
                    response('error', 'Неверно заполнена тех. карта. Строка '.($i + 1).'.', 1);

            }

            if(!$this->get_technical_card_composition)
                $this->get_technical_card_composition = $item['id'];
            else
                $this->get_technical_card_composition .= ','.$item['id'];

        }
        elseif($item['type'] == 2){

            if(!$item['id'] || !$item['count'] || !$item['bulk']){

                if(!$this->edit_mode)
                DB::delete(DB_REMOVALS, 'id = '.$this->removal);

                if($item['name'])
                    response('error', 'Неверно заполнена производимая продукция: "'.$item['name'].'".', 1);
                else
                    response('error', 'Неверно заполнена производимая продукция. Строка '.($i + 1).'.', 1);

            }

        }

    }

    /*Создание списания*/
    public function create(){

        if(!$removal = DB::insert($this->fields, DB_REMOVALS))
            response('error', 'Не удалось создать списание.', 1);

        $this->removal = $removal;
        $this->proccess_id = $removal;

        $this->prepare_items();
        $this->insert_items();

    }

    public function edit($removal){
        
        //Установка режима редактирования, чтобы не удалять списание!
        $this->edit_mode = true;

        $this->removal = $removal;
        $this->proccess_id = $removal;
        DB::update($this->fields, DB_REMOVALS, "id = $removal");
        $this->prepare_items();
        DB::delete(DB_REMOVAL_ITEMS, "removal = $removal");
        $this->insert_items();
    }

    private function getItemsPrices(){

        if(!$this->get_item_price)
            return;

        $prices = DB::select('item, price', DB_POINT_ITEMS, '('.$this->get_item_price.') AND point = '.$this->point);

        while($row = DB::getRow($prices)){

            for($i = 0; $i < sizeof($this->items); $i++){

                if($this->items[$i]['type'] != 0)
                    continue;

                if($this->items[$i]['id'] == $row['item'])
                    $this->items[$i]['price'] = $row['price'];

            }

        }

    }

    private function getTechnicalCardComposition(){

        if(!$this->get_technical_card_composition)
            return;

        //Получаем состав тех. карты и цены на складе
        $composition = DB::query('
            SELECT pc.item AS id, pc.technical_card, IF(pc.untils = "шт", pc.count, pc.gross) AS count, pc.untils, p.price, pc.net_mass
            FROM '.DB_PRODUCT_COMPOSITION.' pc
            LEFT JOIN '.DB_POINT_ITEMS.' p ON p.item = pc.item AND p.point = '.$this->point.'
            WHERE FIND_IN_SET(pc.technical_card, "'.$this->get_technical_card_composition.'")
        ');

        //Складываем ингредиенты в тех. карты
        while($row = DB::getRow($composition)){
            for($i = 0; $i < sizeof($this->items); $i++){

                if($this->items[$i]['type'] != 1)
                    continue;

                if($this->items[$i]['id'] == $row['technical_card']) {
                    $this->items[$i]['net_mass'] += $row['net_mass'];
                    $this->items[$i]['composition'][] = array(
                        'id' => $row['id'],
                        'count' => round($row['count'] * -1, 3),
                        'price' => round($row['price'], 2)
                    );
                    break;

                }
            }
        }

        for($i = 0; $i < sizeof($this->items); $i++){

            //Если не тех. карта, то следующая итерация
            if($this->items[$i]['type'] != 1)
                continue;

            for($j = 0; $j < sizeof($this->items[$i]['composition']); $j++){

                if(isset($this->items[$i]['weighted']) && $this->items[$i]['weighted'] == true)
                    $this->items[$i]['composition'][$j]['count'] = $this->items[$i]['composition'][$j]['count'] / $this->items[$i]['net_mass'];

                //Рассчитываем себестоимость тех. карты
                $this->items[$i]['price'] += abs($this->items[$i]['composition'][$j]['count'] * $this->items[$i]['composition'][$j]['price']);

                $this->items[$i]['composition'][$j]['count'] = $this->items[$i]['composition'][$j]['count'] * $this->items[$i]['count'] * -1;

            }

        }

    }

    private function getProductionComposition(){

        //Получаем все ID производимой продукции
        for($i = 0; $i < sizeof($this->items); $i++){

            if($this->items[$i]['type'] != 2)
                continue;
            
            $this->items[$i]['composition'] = $this->productionCalcPrice->subItems($this->items[$i]);

            $this->items[$i]['price'] = 0;

            for($j = 0; $j < sizeof($this->items[$i]['composition']); $j++)
                $this->items[$i]['price'] += $this->items[$i]['composition'][$j]['count_price'];

            $this->items[$i]['price'] /= $this->items[$i]['count'];

        }

    }

    /*Создание списка ингредиентов в списании*/
    private function prepare_items(){

    
        for($i = 0; $i < sizeof($this->items); $i++){

            //Если это не ингредиент, то указываем, что у позиции будет иметься состав
            if($this->items[$i]['type'] != 0){
                $this->items[$i]['price'] = 0;
                $this->items[$i]['composition'] = [];
            }

            $this->items[$i]['count'] *= -1;

            $this->ItemValidate($this->items[$i], $i);
        }

        $this->getItemsPrices();
        $this->getTechnicalCardComposition();
        $this->getProductionComposition();

        for($i = 0; $i < sizeof($this->items); $i++){
            
            if($this->items[$i]['type'] == 0)
                $this->AddItem($this->items[$i]);
            else{

                for($j = 0; $j < sizeof($this->items[$i]['composition']); $j++)
                    $this->AddItem($this->items[$i]['composition'][$j]);

            }

            $this->items[$i]['count'] *= -1;
            $sum =  $this->items[$i]['price'] * $this->items[$i]['count'];

            $this->insert_items[] = '("'.$this->removal.'", "'.$this->point.'", "'.$this->items[$i]['id'].'", "'.$this->items[$i]['price'].'", "'.$this->items[$i]['count'].'", "'.$sum.'", "'.$this->items[$i]['comment'].'", "'.$this->items[$i]['type'].'")';

        }

    }

    private function insert_items(){
        $insert_items = implode(',', $this->insert_items);
        if($insert_items) {
            DB::query('INSERT INTO ' . DB_REMOVAL_ITEMS . ' (removal, point, item, price, count, sum, comment, type) VALUES ' . $insert_items);
            $this->GetPointBalance();
        }
    }

}