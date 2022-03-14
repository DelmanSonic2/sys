<?php
use Support\Pages;
use Support\DB;

require 'CalcWarehouse.php';

class ProductionsParent{

    private $db;

    public function __construct($db){

        // $this->db = $db;

    }

    public function update($product){

        //Находим ПФ, в состав которых входит измененный
        $parents = DB::query('
            SELECT i.id, i.untils, pc.id AS position, pc.count, pc.net_mass, i.bulk
            FROM '.DB_PRODUCTIONS_COMPOSITION.' pc
            JOIN '.DB_ITEMS.' i ON i.id = pc.product
            WHERE pc.item = '.$product['id'].' AND pc.mass_block = 0
        ');
    
        while($row = DB::getRow($parents)){
    
            //Рассчитываем новую массу НЕТТО для родителей
            $row['new_bulk'] = $row['bulk'] - ($row['count'] * $product['bulk']) + ($row['count'] * $product['new_bulk']);
    
            //Обновляем таблицу состава, указывая новый состав
            DB::query('
                UPDATE '.DB_PRODUCTIONS_COMPOSITION.'
                SET net_mass = '.($row['count'] * $product['new_bulk']).'
                WHERE id = '.$row['position'].'
            ');
    
            //Обновляем родителя, указывая новый состав
            DB::query('
                UPDATE '.DB_ITEMS.'
                SET bulk = '.$row['new_bulk'].'
                WHERE id = '.$row['id'].'
            ');
    
            //Если родитель измеряется в штуках, рекурсивно ищем его родителей
            if($row['untils'] == 'шт'){
                //Обновляем состав тех. карт, в котором есть данная ПФ
                DB::query('
                    UPDATE '.DB_PRODUCT_COMPOSITION.'
                    SET net_mass = count * '.$row['new_bulk'].'
                    WHERE item = '.$row['id'].' AND mass_block = 0
                ');
                $this->update($row);
            }
    
        }
    
    }    

}

class ProductionsClass extends ItemsHistory{

    public $products;

    private $comment;
    private $employee;
    private $point_to;
    private $composition;
    private $productionCalcPrice;
    private $insert_items = [];
    private $items_moving = [];

    public function __construct($db, $partner, $point, $point_to, $comment, $date, $employee){

        if($employee)
            $this->employee = $employee;

        // $this->db = $db;
        $this->partner = $partner;
        $this->point = $point;
        $this->point_to = $point_to;
        $this->proccess = 5;
        $this->date = $date;
        $this->comment = $comment;
        $this->products = [];
        $this->sub_items = [];
        $this->productionCalcPrice = new ProductionCostPrice($db, $partner, $point);
        $this->products();
        $this->composition();

    }

    private function products(){

        //Получаем JSON производимой продукции и декодируем JSON в массив
        if(!$products = DB::escape($_REQUEST['products']))
            response('error', 'Заполните таблицу производимой продукции.', 1);

        $products = stripslashes($products);

        $this->products = json_decode($products, true);

        if(!$this->products || sizeof($this->products) == 0)
            response('error', 'Заполните таблицу производимой продукции.', 1);

    }

    private function composition(){

        //Получаем все ID производимой продукции
        for($i = 0; $i < sizeof($this->products); $i++){
            
            if($this->products[$i]['count'] <= 0 || !is_numeric($this->products[$i]['count']))
                response('error', 'Укажите количество в строке '.($i+1), 1);

            $this->products[$i]['items'] = $this->productionCalcPrice->subItems($this->products[$i]);

            $this->products[$i]['price'] = 0;

            for($j = 0; $j < sizeof($this->products[$i]['items']); $j++)
                $this->products[$i]['price'] += $this->products[$i]['items'][$j]['count_price'];

            $this->products[$i]['price'] /= $this->products[$i]['count'];

        }

    }

    public function create(){

        $fields = array('partner' => $this->partner,
                        'point' => $this->point,
                        'point_to' => $this->point_to,
                        'comment' => $this->comment,
                        'date' => $this->date,
                        'created' => time());

        if($this->employee)
            $fields['employee'] = $this->employee;

        if(!$this->proccess_id = DB::insert($fields, DB_PRODUCTIONS))
            response('error', 'Произошла ошибка, повторите попытку позднее.', 1);  

        $this->prepare_items();
        $this->insert_items();
    }

    public function edit($production){

        $this->proccess_id = $production;

        $fields = array('partner' => $this->partner,
            'point' => $this->point,
            'point_to' => $this->point_to,
            'comment' => $this->comment,
            'date' => $this->date,
            'created' => time());

        if($this->employee)
            $fields['employee'] = $this->employee;

        DB::update($fields, DB_PRODUCTIONS, "id = $production");

        $this->prepare_items();
        DB::delete(DB_PRODUCTION_ITEMS, "production=$production");
        $this->insert_items();

    }

    private function prepare_items(){

        //Проходимся по списку производимой продукции, которая должна попасть на склад
        for($i = 0; $i < sizeof($this->products); $i++){

            $item_plus = array('id' => $this->products[$i]['id'],
                'count' => $this->products[$i]['count'],
                'price' => $this->products[$i]['price'] );

            $this->AddItem($item_plus);

            $this->insert_items[] = '("'.$this->proccess_id.'", "'.$this->products[$i]['id'].'", "'.$this->products[$i]['count'].'", "'.$this->products[$i]['price'] * $this->products[$i]['count'].'", "'.base64_encode(json_encode($this->products[$i]['items'])).'")';

            //Проходимся по списку ингредиентов производимой продукции, который необходимо списать
            for($j = 0; $j < sizeof($this->products[$i]['items']); $j++){

                $this->items_moving[] = '("'.$this->proccess_id.'", "'.$this->products[$i]['id'].'", "'.$this->products[$i]['items'][$j]['id'].'", "'.$this->products[$i]['items'][$j]['price'].'", "'.$this->products[$i]['items'][$j]['count'].'")';

                $item_minus = array('id' => $this->products[$i]['items'][$j]['id'],
                    'count' => $this->products[$i]['items'][$j]['count'] * -1,
                    'price' => $this->products[$i]['items'][$j]['price']);

                $this->AddItem($item_minus);

            }

        }

    }

    private function insert_items(){

        $insert_items = implode(',', $this->insert_items);
        $items_moving = implode(',', $this->items_moving);

        if($insert_items)
            DB::query('INSERT INTO '.DB_PRODUCTION_ITEMS.' (production, product, count, cost_price, details) VALUES '.$insert_items);

        if($items_moving)
            DB::query('INSERT INTO '.DB_PRODUCTION_ITEMS_MOVING.' (production, product, item, price, count) VALUES '.$items_moving);

        //@$this->check();
        $this->GetPointBalance();
    }

    private function check(){

        $items = '';

        for($i = 0; $i < sizeof($this->products); $i++){
            if((abs($this->products[$i]['price'] - $this->products[$i]['cost_price']) > 100) && $this->products[$i]['cost_price']){
                $item = DB::select('name', DB_ITEMS, 'id = '.$this->products[$i]['id'], '', 1);
                $item = DB::getRow($item)['name'];
                if(!$items)
                    $items = 'Продукция "'.$item.'": цена изменилась с '.round($this->products[$i]['cost_price'], 2).' ₽ на '.round($this->products[$i]['price'], 2).'  ₽';
                else
                    $items .= '<br/>Продукция "'.$item.'": цена изменилась с '.round($this->products[$i]['cost_price'], 2).' ₽ на '.round($this->products[$i]['price'],2).'  ₽';

            }
        }

        if(!$items)
            return;

        $pointFrom = DB::select('name', DB_PARTNER_POINTS, 'id = '.$this->point, '', 1);
        $pointFrom = DB::getRow($pointFrom)['name'];

        $pointTo = DB::select('name', DB_PARTNER_POINTS, 'id = '.$this->point_to, '', 1);
        $pointTo = DB::getRow($pointTo)['name'];

        $text = 'Производство №'.$this->proccess_id.'<br/>
                Со склада: '.$pointFrom.'<br/>
                На склад: '.$pointTo.'<br/>
                Дата: '.date('Y-m-d H:i', $this->date).'<br>
                Были найдены следующие расхождения:<br/>'.$items;

        $params= array(
            'to'=> 'daria@apptor.ru,mrseagull@ya.ru,terrro.dinamit@yandex.ru',
            'subject'=> 'Расхождения в производстве',
            'body'=> $text
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onepush.ru/api/mail');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, urldecode(http_build_query($params)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);

    }

}