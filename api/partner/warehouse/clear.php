<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';
include ROOT . 'api/classes/CalcWarehouse.php';

class InsertTrn extends ItemsHistory
{

    private $fields;

    public function __construct($db, $fields, $type)
    {

        // $this->db = $db;
        $this->fields = $fields;
        $this->proccess = $type;
        $this->proccess_id = $fields['id'];
        $this->partner = $fields['partner'];
        $this->point = $fields['pointTo'];
        $this->date = $fields['date'];
    }

    public function supplies()
    {

        $items = DB::select('item AS id, count, price, sum, total', DB_SUPPLY_ITEMS, 'supply = ' . $this->proccess_id);

        while ($row = DB::getRow($items))
            $this->AddItem($row);

        $this->GetPointBalance();
    }
}

switch ($action) {

    case 'exec':

        DB::delete(DB_PARTNER_TRANSACTIONS, 'proccess = 0');

        $supplies = DB::select('*', DB_SUPPLIES, 'type = 0');

        while ($row = DB::getRow($supplies)) {


            $s_class = new InsertTrn(false, $row, 0);
            $s_class->supplies();
        }

        response('success', array('msg' => 'Все поставки обновлены.'), 7);

        break;
}
