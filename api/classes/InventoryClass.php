<?php
use Support\Pages;
use Support\DB;

require_once 'CalcWarehouse.php';
require 'TableHead.php';

class Inventory extends ItemsHistory{

    protected $date_begin;
    protected $date_end;
    protected $point;
    protected $items = [];
    protected $inventory;

    public function items(){

        $from_dyd = date('Ym', $this->date_begin);

        DB::query('LOCK TABLES '.DB_PARTNER_TRANSACTIONS.' READ, '.DB_POINT_ITEMS.' READ, '.DB_INVENTORY_ITEMS.' AS inv READ, '.DB_ITEMS.' AS i READ');
        $items = DB::query(' SELECT i.id, i.name, t.income, pi.price, pi.count, t.dif, t.consumption, t.detucted, inv.item
                                    FROM '.DB_ITEMS.' i
                                    LEFT JOIN (
                                        SELECT item,
                                                SUM(IF(count < 0 AND (proccess = 4 OR proccess = 5) AND date < '.$this->date_end.', count, 0)) AS consumption,
                                                SUM(IF(count < 0 AND (proccess = 1 OR proccess = 3) AND date < '.$this->date_end.', count, 0)) AS detucted,
                                                SUM(IF(count > 0 AND date < '.$this->date_end.', count, 0)) AS income,
                                                SUM(IF(date >= '.$this->date_end.', count, 0)) AS dif
                                        FROM '.DB_PARTNER_TRANSACTIONS.'
                                        WHERE date >= '.$this->date_begin.' AND dyd >= '.$from_dyd.' AND partner = '.$this->partner.' AND point = '.$this->point.' AND proccess != 2
                                        GROUP BY item) t ON i.id = t.item
                                    JOIN (
                                        SELECT AVG(price) AS price, item, SUM(count) AS count
                                        FROM '.DB_POINT_ITEMS.'
                                        WHERE partner = '.$this->partner.' AND point = '.$this->point.'
                                        GROUP BY item) pi ON pi.item = i.id
                                    LEFT JOIN '.DB_INVENTORY_ITEMS.' inv ON inv.inventory = '.$this->inventory.' AND inv.item = i.id
                                    ORDER BY i.name ASC');
        DB::query('UNLOCK TABLES');

        while($row = DB::getRow($items)){
            $planned_balance = round($row['count'] - $row['dif'], 3); //Вычисляем плановые остатки
            $balance_begin = round($planned_balance - $row['income'] - $row['consumption'] - $row['detucted'], 3); //На основе плановых остатков, вычисляем остатки на начало

            $this->items[] = $row;

            if($row['item'] == null){ // Если нет записи в БД, то добавляем

                if(!$insert)
                    $insert = '("'.$this->inventory.'", "'.round($row['price'], 2).'", "'.$row['id'].'", "'.$this->partner.'",
                                "'.$this->point.'", "'.$balance_begin.'", "'.round($row['income'], 3).'", "'.round($row['consumption'] * -1, 3).'",
                                "'.round($row['detucted'] * -1, 3).'")';
                else
                    $insert .= ', ("'.$this->inventory.'", "'.round($row['price'], 2).'", "'.$row['id'].'", "'.$this->partner.'",
                                "'.$this->point.'", "'.$balance_begin.'", "'.round($row['income'], 3).'", "'.round($row['consumption'] * -1, 3).'",
                                "'.round($row['detucted'] * -1, 3).'")';

            }
            else{ //Если запись есть, то обновляем
            
                $fields = array(
                    'price' => round($row['price'], 2),
                    'begin_balance' => $balance_begin,
                    'income' => round($row['income'], 3),
                    'consumption' => round($row['consumption'] * -1, 3),
                    'detucted' => round($row['detucted'] * -1, 3)
                );

                DB::query('LOCK TABLES '.DB_INVENTORY_ITEMS.' WRITE');
                DB::update($fields, DB_INVENTORY_ITEMS, 'item = '.$row['item'].' AND inventory = '.$this->inventory);
                DB::query('UNLOCK TABLES');

            }
            
        }

        if($insert){
            DB::query('LOCK TABLES '.DB_INVENTORY_ITEMS.' WRITE');
            DB::query('INSERT INTO '.DB_INVENTORY_ITEMS.'
                            (inventory, price, item, partner, point, begin_balance, income, consumption, detucted)
                            VALUES '.$insert);
            DB::query('UNLOCK TABLES');
        }

    }

}

class InventoryGet extends Inventory{

    private $result;
    private $export;
    private $access;

    public function __construct($db, $partner, $access){
        
        // $this->db = $db;
        $this->partner = $partner;
        $this->access = $access;

        $this->export = DB::escape($_REQUEST['export']) ? true : false;

    }

    public function info($inventory){

        $this->inventory = $inventory;

        $inventory_data = DB::query('SELECT i.id, i.sum, i.status, i.date_begin, i.date_end, i.date_completed, p.name AS point, i.today, i.point AS point_id
                                            FROM '.DB_INVENTORY.' i
                                            JOIN '.DB_PARTNER_POINTS.' p ON p.id = i.point
                                            WHERE i.id = '.$inventory.' AND i.partner = '.$this->partner.'
                                            LIMIT 1');

        if(DB::getRecordCount($inventory_data) == 0)
            response('error', 'Инвентаризация не найдена.', 1);

        $inventory_data = DB::getRow($inventory_data);
        
        $this->point = $inventory_data['point_id'];
        $this->date_begin = $inventory_data['date_begin'];
        $this->date_end = $inventory_data['today'] ? time() : $inventory_data['date_end'];

        /* if(!$inventory_data['status'])
            $this->items(); */

        $this->result = array(  'id' => (int)$inventory_data['id'],
                                'point' => $inventory_data['point'],
                                'sum' => round($inventory_data['sum'], 2),
                                'different_sum' => 0,
                                'execute_inventory' => $this->access,
                                'date_begin' => (int)$inventory_data['date_begin'],
                                'date_end' => $inventory_data['today'] ? time() : (int)$inventory_data['date_end'],
                                'today' => (bool)$inventory_data['today'],
                                'date_completed' => (int)$inventory_data['date_completed'],
                                'items' => []);

        $this->composition();

    }

    //Проведенная
    private function composition(){

        $archive = '
            AND i.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "item" AND partner_id = '.$this->partner.'
            )';

        $items = DB::query(' SELECT i.id, i.name, i.untils, ii.price, ii.begin_balance, ii.income, ii.consumption, ii.detucted, ii.planned_balance, ii.actual_balance, ii.price, ii.set_value
                                    FROM '.DB_INVENTORY_ITEMS.' ii
                                    JOIN '.DB_ITEMS.' i ON i.id = ii.item
                                    WHERE ii.inventory = '.$this->inventory.' AND (i.partner = '.$this->partner.' OR i.partner IS NULL)'.$archive.'
                                    ORDER BY i.name ASC');

        if($this->export){

            require 'ExportToFileClass.php';

            $i = 1;

            $date_end = $this->result['date_end'] ? $this->result['date_end'] : time();
            $filename = 'Инвентаризация "'.$this->result['point'].'" ('.date('d-m-Y H:i', $this->result['date_begin']).' - '.date('d-m-Y H:i', $date_end).')';

            $f_class = new ExportToFile(false, TableHead::inventory_info(), $filename);

            while($row = DB::getRow($items)){

                $planned_balance = $row['begin_balance'] - $row['detucted'] - $row['consumption'] + $row['income'];
                $different = $row['actual_balance'] - $planned_balance;

                /* if(!$row['income'] && !$row['consumption'] && !$row['detucted'])
                    continue; */

                $f_class->data[] = array(
                    'i' => $i,
                    'name' => $row['name'],
                    'untils' => $row['untils'],
                    'begin_balance' => round($row['begin_balance'], 3),
                    'income' => round($row['income'], 3),
                    'consumption' => round($row['consumption'], 3),
                    'detucted' => round($row['detucted'], 3),
                    'detucted_sum' => round($row['detucted'] * $row['price'], 2),
                    'planned_balance' => round($planned_balance, 3),
                    'actual_balance' => !$row['set_value'] ? '' : round($row['actual_balance'], 3),
                    'actual_balance_sum' => !$row['set_value'] ? '' : round($row['actual_balance'] * $row['price'], 3),
                    'different' => !$row['set_value'] ? '' : round($different, 3),
                    'different_sum' => !$row['set_value'] ? '' : round($different * $row['price'], 2)
                );

                $i++;

            }

            $f_class->create();

        }
        else{

            while($row = DB::getRow($items)){

                $planned_balance = $row['begin_balance'] - $row['detucted'] - $row['consumption'] + $row['income'];
                $different = $row['actual_balance'] - $planned_balance;

                if($row['set_value'])
                    $this->result['different_sum'] += $different * $row['price'];

                /* if(!$row['income'] && !$row['consumption'] && !$row['detucted'])
                    continue; */

                $this->result['items'][] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'untils' => $row['untils'],
                    'price' => (string)round($row['price'], 2),
                    'begin_balance' => (string)round($row['begin_balance'], 3),
                    'income' => (string)round($row['income'], 3),
                    'consumption' => (string)round($row['consumption'], 3),
                    'detucted' => (string)round($row['detucted'], 3),
                    'detucted_sum' => (string)round($row['detucted'] * $row['price'], 2),
                    'planned_balance' => (string)round($planned_balance, 3),
                    'actual_balance' => !$row['set_value'] ? '' : (string)round($row['actual_balance'], 3),
                    'different' => !$row['set_value'] ? '' : (string)round($different, 3),
                    'different_sum' => !$row['set_value'] ? '' : (string)round($different * $row['price'], 2),
                    'set_value' => (bool)$row['set_value']
                );

            }

        }

        $this->result['different_sum'] = round($this->result['different_sum'], 2);

        response('success', $this->result, 7);

    }

}

class InventoryOpen extends Inventory{

    private $employee;

    public function __construct($db, $partner, $point, $date_begin, $date_end, $employee = false){

        // $this->db = $db;
        $this->partner = $partner;
        $this->point = $point;
        $this->employee = $employee;
        $this->date_begin = $date_begin;
        $this->date_end = strtotime(date('d-m-Y', $date_end));

    }

    public function open(){

        $fields = array('partner' => $this->partner,
                        'point' => $this->point,
                        'sum' => 0,
                        'status' => 0,
                        'date_begin' => $this->date_begin,
                        'date_end' => 0,
                        'date_completed' => 0,
                        'today' => 1,
                        'created' => time());

        if($this->employee)
            $fields['employee'] = $this->employee;

        if(!$this->inventory = DB::insert($fields, DB_INVENTORY))
            response('error', 'Не удалось создать инвентаризацию.', 1);

        $this->items();

    }

}

class InventoryUpdate extends Inventory{

    private $today;

    public function __construct($db, $partner){

        // $this->db = $db;
        $this->partner = $partner;

    }

    public function update(){

        if(!$inventory = DB::escape($_REQUEST['inventory']))
            response('error', 'Не передан ID инвентаризации.', 1);

        if(!$item = DB::escape($_REQUEST['item']))
            response('error', 'Выберите товар.', 1);

        if(!$actual = DB::escape($_REQUEST['actual']))
            $actual = 0;

        $inventory_data = DB::select('id, status', DB_INVENTORY, 'id = '.$inventory.' AND partner = '.$this->partner, '', 1);

        if(DB::getRecordCount($inventory_data) == 0)
            response('error', 'Инвентаризация не найдена.', 1);

        $inventory_data = DB::getRow($inventory_data);

        if($inventory_data['status'])
            response('error', 'Нельзя изменить проведенную инвентаризацию.', 1);

        $item_data = DB::select('id', DB_INVENTORY_ITEMS, 'inventory = '.$inventory.' AND item = '.$item, '', 1);

        if(DB::getRecordCount($item_data) == 0)
            response('error', 'Такая позиция отсутствует в текущей инвентаризации.', 1);

        $item_data = DB::getRow($item_data);

        $fields = array(
            'actual_balance' => $actual,
            'set_value' => 1
        );

        DB::update($fields, DB_INVENTORY_ITEMS, 'id = '.$item_data['id']);

        response('success', 'Сохранено.', 7);

    }

    public function date(){

        $this->today = $_REQUEST['today'] ? 1 : 0;
        
        $this->date_end = $this->today ? time() : DB::escape($_REQUEST['date']);

        if(!$this->inventory = DB::escape($_REQUEST['inventory']))
            response('error', 'Не передан ID инвентаризации.', 1);

        $inventory_data = DB::select('id, point, date_begin, status, today', DB_INVENTORY, 'id = '.$this->inventory.' AND partner = '.$this->partner, '', 1);

        if(DB::getRecordCount($inventory_data) == 0)
            response('error', 'Инвентаризация не найдена.', 1);

        $inventory_data = DB::getRow($inventory_data);

        $this->date_begin = $inventory_data['date_begin'];
        $this->point = $inventory_data['point'];

        if($inventory_data['status'])
            response('error', 'Инвентаризация уже проведена.', 1);

        if($inventory_data['date_begin'] >= $this->date_end && !$this->today)
            response('error', 'Нельзя выбрать дату раньше открытия инвентаризации.', 1);

        $this->items();

        $fields = array(
            'today' => $this->today,
            'date_end' => $this->today ? 0 : $this->date_end
        );

        DB::update($fields, DB_INVENTORY, 'id = '.$this->inventory);

        response('success', 'Данные обновлены.', 7);

    }

    public function execute(){

        if(!$inventory = DB::escape($_REQUEST['inventory']))
            response('error', 'Не передан ID инвентаризации.', 1);

        $inventory_data = DB::select('id, point, date_begin, date_end, status, today', DB_INVENTORY, 'id = '.$inventory.' AND partner = '.$this->partner, '', 1);

        if(DB::getRecordCount($inventory_data) == 0)
            response('error', 'Инвентаризация не найдена.', 1);

        $inventory_data = DB::getRow($inventory_data);

        if($inventory_data['status'])
            response('error', 'Инвентаризация уже проведена.', 1);

        $this->point = $inventory_data['point'];
        $this->proccess = 2;                         //proccess = 2 - процесс "Инвентаризация"
        $this->proccess_id = $this->inventory = $inventory;
        $this->date = $inventory_data['today'] ? time() : $inventory_data['date_end'];

        $this->date_begin = $inventory_data['date_begin'];
        $this->date_end = $this->date;
        $this->point = $inventory_data['point'];

        $this->items();

        $items = DB::query('
            SELECT i.id, i.inventory, i.item, i.partner, i.point, i.begin_balance, i.income, i.consumption, i.detucted, i.planned_balance, i.actual_balance, i.set_value, pi.price
            FROM '.DB_INVENTORY_ITEMS.' i
            JOIN '.DB_POINT_ITEMS.' pi ON pi.item = i.item AND pi.point = '.$this->point.'
            WHERE i.inventory = '.$inventory.' AND i.set_value = 1
        ');

        while($row = DB::getRow($items)){

            $planned_balance = $row['begin_balance'] - $row['detucted'] - $row['consumption'] + $row['income'];

            $item = array(
                'id' => $row['item'],
                'price' => $row['price'],
                'count' => $row['actual_balance'] - $planned_balance
            );

            $this->AddItem($item);

        }

        if($inventory_data['today'])
            $date_end = ', date_end = '.time();

        $this->GetPointBalance();

        DB::query('
            UPDATE '.DB_INVENTORY_ITEMS.' i
            JOIN '.DB_POINT_ITEMS.' pi ON pi.item = i.item AND pi.point = '.$this->point.'
            SET i.price = pi.price
            WHERE i.inventory = '.$inventory.'
        ');

        DB::query('
            UPDATE '.DB_INVENTORY.'
            SET status = 1,
                date_completed = '.time().$date_end.'
            WHERE id = '.$inventory
        );

        DB::query('
            UPDATE '.DB_INVENTORY_ITEMS.'
            SET planned_balance = begin_balance - detucted - consumption + income
            WHERE inventory = '.$inventory.' AND set_value = 1
        ');


        //Открываем новую инвентаризацию
        $inv_class = new InventoryOpen($this->db, $this->partner, $this->point, $this->date, time());
        $inv_class->open();

        response('success', 'Инвентаризация выполнена.', 7);

    }

    public function repair($limit){

        $where = isset($_REQUEST['inventory']) ? 'id = '.DB::escape($_REQUEST['inventory']) : '';

        $list = DB::select('*', DB_INVENTORY, 'status = 0', $where, $limit);

        while($row = DB::getRow($list)){

            $this->inventory = $row['id'];
            $this->partner = $row['partner'];
            $this->point = $row['point'];
            $this->today = $row['today'];
            $this->date_begin = $row['date_begin'];
            $this->date_end = $row['today'] ? time() : $row['date_end'];

            $this->items();

        }

        response('success', 'Успешно.', 200);

    }

}