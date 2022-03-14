<?php

namespace Support;

class ItemsHistory
{

    private $items_get_data;            //Строка для нахождения остатка на начало и остатка на конец
    public $items_array = [];          //Массив сгруппированных ингредиентов
    public $items_history_array = [];          //Массив для выгрузки 
    private $items_transaction_insert;  // Строка для добавления ингредиентов в "Отчет по движению"
    private $inventory;                 //Информация о инвентаризации
    private $date_begin;                //Дата начала открытой инвентаризации
    public $proccess_id;    //ID поставки/перемещения/списания/инвентаризации
    protected $proccess;    //Код процесса: 0 - поставки, 1 - перемещения, 2 - инвентаризация, 3 - списания, 4 - продажи, 5 - производство
    protected $db;          //Экземпляр класса modx для работы с БД
    protected $partner;     //ID партнера
    protected $point;       //ID точки с которой списываются или на которую поступают ингредиенты
    protected $date;        //Дата списания/добавления ингредиента

    public function __construct($db, $proccess, $partner, $point, $date, $proccess_id)
    {
        // $this->db = $db;
        $this->proccess = $proccess;
        $this->partner = $partner;
        $this->point = $point;
        $this->date = $date;
        $this->proccess_id = $proccess_id;
    }

    /*Получает на вход объект items, складывает в массив, объединяя ингредиенты по id*/
    public function AddItem($item)
    {

        if (!$item['price'] || $item['price'] == 0)
            return;

        $item['balance_begin'] = 0;
        $item['average_price_begin'] = null;
        $item['type'];

        if (sizeof($this->items_array) == 0) {
            $this->items_array[] = $item;
            return;
        }

        $exists = false;

        for ($i = 0; $i < sizeof($this->items_array); $i++) {

            if ($this->items_array[$i]['id'] == $item['id']) {

                if ($item['price']) { // Если есть цена (поставки), то расчитываем среднюю себестоимость между повторяющимися позициями

                    $divider = round($this->items_array[$i]['count'], 3) + round($item['count'], 3);

                    $this->items_array[$i]['price'] = ($divider) ?
                        (round($this->items_array[$i]['price'], 2) * round($this->items_array[$i]['count'], 3) + round($item['price'], 2) * round($item['count'], 3)) / $divider
                        : (round($item['price'], 2) + round($this->items_array[$i]['price'], 2)) / 2;
                }

                $this->items_array[$i]['price'] = round($this->items_array[$i]['price'], 2);
                //Если есть повторяющиеся позиции, складываем количество
                $this->items_array[$i]['count'] += $item['count'];

                $exists = true;
            }
        }

        if (!$exists)
            $this->items_array[] = $item;
    }

    /*Формирует строку для запроса, который вычисляет баланс на начало и конец и преобразует массив items_array*/
    public function GetPointBalance()
    {

        $this->getInventoryInfo();



        //Формируем строку, для условия WHERE чтобы узнать, есть ли товары на складе или нет
        for ($i = 0; $i < sizeof($this->items_array); $i++) {

            if (!$this->items_get_data)
                $this->items_get_data = ' AND (pi.item = "' . $this->items_array[$i]['id'] . '"';
            else
                $this->items_get_data .= ' OR pi.item = "' . $this->items_array[$i]['id'] . '"';
        }

        if ($this->items_get_data)
            $this->items_get_data .= ')';

        //Ищем информацию на складе о товаре
        DB::query('LOCK TABLES ' . DB_POINT_ITEMS . ' pi READ');
        $balance = DB::query(
            '
            SELECT pi.item AS id, pi.count, pi.price
            FROM ' . DB_POINT_ITEMS . ' pi
            WHERE pi.partner = ' . $this->partner . ' AND pi.point = ' . $this->point . $this->items_get_data
        );
        DB::query('UNLOCK TABLES');

        while ($row = DB::getRow($balance)) {

            for ($i = 0; $i < sizeof($this->items_array); $i++) {

                if ($row['id'] == $this->items_array[$i]['id']) {

                    //Если товар найден на складе, достаем остатки на начало и среднюю цену на начало
                    if (!$this->items_array[$i]['price'])
                        $this->items_array[$i]['price'] = $row['price'];

                    //Сохраняем информацию об остатках на начало
                    $this->items_array[$i]['balance_begin'] = $row['count'];
                    $this->items_array[$i]['average_price_begin'] = $row['price'];
                }
            }
        }

        for ($i = 0; $i < sizeof($this->items_array); $i++) {
            //Исходя из остатков на начало и средней цены на начало
            $balance_end = $this->items_array[$i]['balance_begin'] + $this->items_array[$i]['count'];
            //$average_price_end = ($this->items_array[$i]['balance_begin'] + $this->items_array[$i]['count']) == 0 ? $this->items_array[$i]['price'] : (($this->items_array[$i]['balance_begin'] * $this->items_array[$i]['average_price_begin']) + ($this->items_array[$i]['price'] * $this->items_array[$i]['count'])) / ($this->items_array[$i]['balance_begin'] + $this->items_array[$i]['count']);

            //Если количество товара на складе меньше, либо равно 0, то новая цена будет равна цене поступления
            if ($this->items_array[$i]['balance_begin'] <= 0)
                $average_price_end = $this->items_array[$i]['price'];
            else {

                $bb = abs($this->items_array[$i]['balance_begin']); //balance begin
                $bp = $this->items_array[$i]['average_price_begin']; //begin price
                $be = abs($this->items_array[$i]['count']);         //balance end
                $ep = $this->items_array[$i]['price'];              //end price

                $average_price_end = ($bb * $bp + $be * $ep) / ($bb + $be);
            }

            $this->items_array[$i]['average_price_end'] = $average_price_end;
            $total = $this->items_array[$i]['count'] * $this->items_array[$i]['price'];
            $dyd = date('Ym', $this->date);

            $this->updateInventoryItem($this->items_array[$i]);
            $this->updatePointItem($this->items_array[$i]);

            if (!$this->items_transaction_insert)
                $this->items_transaction_insert = '("' . $this->items_array[$i]['id'] . '", "' . $this->partner . '", "' . $this->point . '", "' . $this->proccess . '", "' . $this->proccess_id . '",
                                            "0", "' . $this->items_array[$i]['count'] . '", "' . $this->items_array[$i]['price'] . '", "' . $total . '",
                                            "' . $this->items_array[$i]['balance_begin'] . '", "' . $this->items_array[$i]['average_price_begin'] . '",
                                            "' . $balance_end . '", "' . $average_price_end . '", "' . time() . '", "' . $this->date . '", "' . $dyd . '")';
            else
                $this->items_transaction_insert .= ',("' . $this->items_array[$i]['id'] . '", "' . $this->partner . '", "' . $this->point . '", "' . $this->proccess . '", "' . $this->proccess_id . '",
                                            "0", "' . $this->items_array[$i]['count'] . '", "' . $this->items_array[$i]['price'] . '", "' . $total . '",
                                            "' . $this->items_array[$i]['balance_begin'] . '", "' . $this->items_array[$i]['average_price_begin'] . '",
                                            "' . $balance_end . '", "' . $average_price_end . '", "' . time() . '", "' . $this->date . '", "' . $dyd . '")';
        }

        $this->items_array = [];



        if ($this->items_transaction_insert) {

            DB::query('LOCK TABLES ' . DB_PARTNER_TRANSACTIONS . ' WRITE');
            DB::query(
                '
                INSERT INTO ' . DB_PARTNER_TRANSACTIONS . '
                (item, partner, point, proccess, proccess_id, type, count, price, total, balance_begin, average_price_begin, balance_end, average_price_end, created, date, dyd)
                VALUES ' . $this->items_transaction_insert
            );
            DB::query('UNLOCK TABLES');
        }
        echo  DB::getLastError();

        // if(count($this->items_history_array) > 0) mDB::collection("point_items_history")->insertMany($this->items_history_array);

    }

    /*Получаем информацию об открытой инвентаризации, если такая имеется*/
    private function getInventoryInfo()
    {

        DB::query('LOCK TABLES ' . DB_INVENTORY . ' READ');
        $inventory = DB::query('
            SELECT id, date_begin, date_end, today
            FROM ' . DB_INVENTORY . '
            WHERE point = ' . $this->point . ' AND status = 0 AND (today = 1 OR (today = 0 AND ' . $this->date . ' BETWEEN date_begin AND date_end ))
        ');
        DB::query('UNLOCK TABLES');

        if (DB::getRecordCount($inventory) != 0) {
            $inventory = DB::getRow($inventory);
            $this->inventory = $inventory['id'];
            $this->date_begin = $inventory['date_begin'];
        }
    }

    private function updatePointItem($item)
    {

        //Если цена на начало отсутствует, то необходимо добавить новую позицию
        if ($item['average_price_begin'] == null) {

            $fields = array(
                'point' => $this->point,
                'partner' => $this->partner,
                'item' => $item['id'],
                'count' => $item['count'],
                'price' => $item['price']
            );

            //Изменение цены в заведении
            $this->items_history_array[] = [
                "country" => Request::$country,
                "point" => (int)$this->point,
                'partner' => (int)$this->partner,
                "price" => (float)$item['price'],
                "item" => (int)$item['id'],
                "count" => (float)$item['count'],
                "datetime" => time(),
                "proccess" => (int)$this->proccess,
                "date" => (int)$this->date,
                "proccess_id" => (int)$this->proccess_id
            ];


            DB::insert($fields, DB_POINT_ITEMS);
        } //В противном случае, необходимо изменить количество товара на складе
        else {

            //Если цена на начало и цена на конец отличаются, значит меняем и цену
            if ($item['average_price_begin'] != $item['average_price_end']) {
                $edit_price = ', price = ' . $item['average_price_end'];

                //Изменение цены в заведении
                $this->items_history_array[] = [
                    "country" => Request::$country,
                    'partner' => (int)$this->partner,
                    "point" => (int)$this->point,
                    "before_price" => (float)$item['average_price_begin'],
                    "price" => (float)$item['average_price_end'],
                    "item" => (int)$item['id'],
                    "count" => (float)$item['count'],
                    "datetime" => time(),
                    "proccess" => (int)$this->proccess,
                    "date" => (int)$this->date,
                    "proccess_id" => (int)$this->proccess_id
                ];
            }

            DB::query('LOCK TABLES ' . DB_POINT_ITEMS . ' WRITE');
            DB::query(
                '
                UPDATE ' . DB_POINT_ITEMS . '
                SET count = count + ' . $item['count'] . $edit_price . '
                WHERE item = ' . $item['id'] . ' AND point = ' . $this->point
            );
            DB::query('UNLOCK TABLES');
        }
    }

    private function updateInventoryItem($item)
    {

        /*
        Если отсутствует открытая инвентаризация или процесс - инвентаризация, то выход
        Т.к. изменения при выполнении инвентаризации не должны попадать в документ инвентаризации
        */
        if (!$this->inventory || $this->proccess == 2)
            return;

        $item_inventory = DB::select('id', DB_INVENTORY_ITEMS, 'inventory = ' . $this->inventory . ' AND item = ' . $item['id'], '', 1);

        /*
        Если ингредиента нет в инвентаризации, то его необходимо добавить в инвентаризацию
        */
        if (DB::getRecordCount($item_inventory) == 0) {

            $balance_begin = 0; //Остатки на начало
            $income = 0;        //Поступления
            $consumption = 0;   //Расход
            $detucted = 0;      //Списания

            //Если дата добавление документа меньше чем дата начала инвентаризации, то обновляем остатки на начало
            if ($this->date < $this->date_begin)
                $balance_begin = $item['count'];
            //Если продажа или ушло при производстве, то расход
            elseif ($this->proccess == 4 || ($this->proccess == 5 and $item['count'] < 0))
                $consumption = $item['count'] * -1;
            //Если поставка, пришло с другого склада, или было произведенно, то поступление
            elseif ($this->proccess == 0 || ($this->proccess == 1 and $item['count'] > 0) || ($this->proccess == 5 and $item['count'] > 0))
                $income = $item['count'];
            //Если ручное списание или ушло при перемещении, то списания
            elseif ($this->proccess == 3 || ($this->proccess == 1 and $item['count'] < 0))
                $detucted = $item['count'] * -1;

            DB::query('LOCK TABLES ' . DB_INVENTORY_ITEMS . ' WRITE');
            DB::query('
                INSERT INTO ' . DB_INVENTORY_ITEMS . '
                (inventory, price, item, partner, point, begin_balance, income, consumption, detucted)
                VALUES ("' . $this->inventory . '", "' . $item['average_price_end'] . '", "' . $item['id'] . '",
                "' . $this->partner . '", "' . $this->point . '", "' . $balance_begin . '", "' . $income . '", "' . $consumption . '", "' . $detucted . '")
            ');
            DB::query('UNLOCK TABLES');
        } else {

            //Если дата добавление документа меньше чем дата начала инвентаризации, то обновляем остатки на начало
            if ($this->date < $this->date_begin)
                $edit_value = ', begin_balance = begin_balance + ' . $item['count'];
            //Если продажа или ушло при производстве, то расход
            elseif ($this->proccess == 4 || ($this->proccess == 5 and $item['count'] < 0))
                $edit_value = ', consumption = consumption - ' . $item['count'];
            //Если поставка, пришло с другого склада, или было произведенно, то поступление
            elseif ($this->proccess == 0 || ($this->proccess == 1 and $item['count'] > 0) || ($this->proccess == 5 and $item['count'] > 0))
                $edit_value = ', income = income + ' . $item['count'];
            //Если ручное списание или ушло при перемещении, то списания
            elseif ($this->proccess == 3 || ($this->proccess == 1 and $item['count'] < 0))
                $edit_value = ', detucted = detucted - ' . $item['count'];

            DB::query('LOCK TABLES ' . DB_INVENTORY_ITEMS . ' WRITE');
            DB::query('
                UPDATE ' . DB_INVENTORY_ITEMS . '
                SET price = ' . $item['average_price_end'] . $edit_value . '
                WHERE item = ' . $item['id'] . ' AND inventory = ' . $this->inventory . '
            ');
            DB::query('UNLOCK TABLES');
        }
    }
}