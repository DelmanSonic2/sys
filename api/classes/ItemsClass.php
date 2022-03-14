<?php
use Support\Pages;
use Support\DB;

class ItemsClass{

    private $db;
    private $partner;
    public $net_mass; //Масса НЕТТО
    public $gross; //Масса брутто
    private $create;

    public function __construct($db, $partner, $create = false){
        // $this->db = $db;
        $this->partner = $partner;
        $this->net_mass = 0;
        $this->gross = 0;
        $this->create = $create;
    }

    public function validate(){

        //Получаем состав производимой продукции
        if(!$items = DB::escape($_REQUEST['items']))
            response('error', 'Укажите состав продукции.', 1);

        //Убираем экранирование символов в полученной строке
        $items = stripslashes($items);

        //Преобразуем JSON в массив
        $items = json_decode($items, true);

        if(sizeof($items) == 0)// Проверяем на пустоту
            response('error', array('msg' => 'Заполните таблицу с ингредиентами.'), '354');

        for($i = 0; $i < sizeof($items); $i++){

            //Номер позиции для вывода текста ошибки
            $row_num = $i + 1;

            //Если с клиента приходит незаполненная строка, то удаляем
            if($items[$i]['id'] == ''){
                unset($items[$i]);
                sort($items);
                $i--;
                continue;
            }

            //Если не указан id ингредиента, то ошибка
            if(!$items[$i]['id'])
                response('error', array('msg' => 'Таблица с ингредиентами заполнена неверно. Строка '.$row_num.'.'), '355');// В массиве у каждого объекта должен быть id

            if($items[$i]['untils'] == 'шт' && !$items[$i]['count'] && $this->create)
                response('error', array('msg' => 'Укажите количество. Строка '.$row_num.'.'), 422);

/*             if($items[$i]['untils'] != 'шт' && !$items[$i]['net_mass'] && $this->create)
                response('error', array('msg' => 'Укажите массу нетто. Строка '.$row_num.'.'), 422); */

            $items[$i]['gross'] = ($items[$i]['gross']) ? $items[$i]['gross'] : 0; // Если не передана масса брутто, то 0
            $items[$i]['net_mass'] = ($items[$i]['net_mass']) ? $items[$i]['net_mass'] : 0; // Если не передана масса нетто, то 0
            $items[$i]['count'] = ($items[$i]['count']) ? $items[$i]['count'] : 0; // Если не передано количество, то 0

            $this->net_mass += $items[$i]['net_mass'];
            $this->gross += $items[$i]['gross'];

            if(!$items[$i]['mass_block'])
                $items[$i]['mass_block'] = 0; // Если не передано условие блокирования нетто, то будем его рассчитывать

            if(!$itemsWhere)
                $itemsWhere = 'i.id = '.$items[$i]['id'];
            else
                $itemsWhere .= ' OR i.id = '.$items[$i]['id'];

        }

        if(!$itemsWhere)
            response('error', array('msg' => 'Заполните таблицу с ингредиентами.'), '354');

        //Запрос, который проверяет, существуют ли такие ингредиенты, и если существуют, то какая у них цена
        $QueryItems = DB::query('SELECT i.*, AVG(pi.price) AS cost_price
                                    FROM '.DB_ITEMS.' i
                                    LEFT JOIN '.DB_POINT_ITEMS.' AS pi ON pi.item = i.id AND pi.partner = '.$this->partner.'
                                    WHERE '.$itemsWhere.'
                                    GROUP BY i.id');

        if(DB::getRecordCount($QueryItems) != sizeof($items))
            response('error', array('msg' => 'Один или более ингредиентов не существуют.'), '355');// Если запрашиваемое и полученное количество ингредиентов не сходится, то ошибка
                            

        $result = [];

        while($row = DB::getRow($QueryItems)){

            for($i = 0; $i < sizeof($items); $i++){

                if($row['id'] == $items[$i]['id']){

                    if(!$items[$i]['bulk'])
                        $items[$i]['bulk'] = 1;

                    $items[$i]['cleaning'] = ($items[$i]['cleaning_checked'] == 1 && $row['untils'] != 'шт') ? (double)$row['cleaning'] : 0;
                    $items[$i]['cooking'] = ($items[$i]['cooking_checked'] == 1 && $row['untils'] != 'шт') ? (double)$row['cooking'] : 0; 
                    $items[$i]['frying'] = ($items[$i]['frying_checked'] == 1 && $row['untils'] != 'шт') ? (double)$row['frying'] : 0; 
                    $items[$i]['stew'] = ($items[$i]['stew_checked'] == 1 && $row['untils'] != 'шт') ? (double)$row['stew'] : 0; 
                    $items[$i]['bake'] = ($items[$i]['bake_checked'] == 1 && $row['untils'] != 'шт') ? (double)$row['bake'] : 0;

                    if($row['untils'] != 'шт')
                        $items[$i]['cost_price'] = round(($items[$i]['gross'] * $row['cost_price']), 2);
                    else
                        $items[$i]['cost_price'] = round(($items[$i]['count'] * $row['cost_price']), 2);

                    $items[$i]['untils'] = $row['untils'];

                    if($items[$i]['untils'] != 'шт'){

                        $items[$i]['count'] = 0;

                        if($items[$i]['mass_block'] == 0)
                            $items[$i]['net_mass'] = $items[$i]['gross'] - ($items[$i]['gross'] * $items[$i]['cleaning'] / 100 + $items[$i]['gross'] * $items[$i]['cooking'] / 100 + $items[$i]['gross'] * $items[$i]['frying'] / 100 + $items[$i]['gross'] * $items[$i]['bake'] / 100 + $items[$i]['gross'] * $items[$i]['stew'] / 100);

                        $items[$i]['net_mass'] = $items[$i]['net_mass'];
                        
                    }
                    else{

                        if($items[$i]['mass_block'] == 0)
                            $items[$i]['net_mass'] = $row['bulk'] * $items[$i]['count'];

                        $items[$i]['gross'] = 0;

                    }

                    break;

                }

            }

        }

        return $items;

    }

}