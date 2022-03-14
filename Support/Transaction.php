<?php

namespace Support;

use Rakit\Validation\Validator;

class Transaction extends ItemsHistory
{

    private $promotions;           //Объект "Акции"
    private $discount;             //Процент скидки
    private $promotion;            //
    private $promotion_code;       //
    private $type;                 //0 - картой, 1 - наличными
    private $employee;             //ID сотрудника
    private $shift;                //ID кассовой смены
    private $points;               //Начислено/списано баллов
    private $sum;                  //Сумма
    private $cost_price;           //Себестоимость
    public $transaction;           //Транзакция
    public $total;                 //Сумма чека, с учетом скидок и бонусов
    public $uniqid;                //Уникальный id для оффлайн режима
    public $source;

    public function __construct($db, $point, $partner, $request)
    {
        $this->proccess = 4;        //proccess = 4 - процесс "Продажа"
        // $this->db = $db;
        $this->point = $point;
        $this->partner = $partner;




        if (isset($request['shift'])) {
            $this->shift = $request['shift'];
        } else {
            $active_shift = DB::getRow(DB::select("*", "app_employee_shifts", "point=$point AND shift_closed=0", "", "1"));
            if (isset($active_shift['id'])) {
                $this->shift = $active_shift['id'];
                $this->employee = $active_shift['employee'];
            } else {
                Utils::response('error', array('msg' => 'Нет открытой смены в заведении, не возможно создать транзакцию.'), '2');
            }
        }

        $validator = new Validator();

        $validation = $validator->make($request, [
            'point' => 'required',
            'uniqid'    => 'required',
            'date' => 'required',
            'sum' => 'required',
            'total' => 'required',
            'type' => 'required',
        ]);

        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors();
            Utils::responseValidator($errors->firstOfAll());
        }


        $this->uniqid = $request['uniqid'];
        $this->phone = $request['client_phone'];
        $this->source = $request['source'] ? $request['source'] : null;
        $this->date = $request['date'];
        $this->discount = $request['discount'];
        $this->sum = $request['sum'];
        $this->total = $request['total'];
        $this->points = isset($request['points']) ? $request['points'] : 0;
        $this->type = $request['type'];
        $this->promotion = $request['promotion'];
        $this->promotion_code = $request['promotion_code'];
        $this->products = empty($request['products']) ? [] : $request['products'];
        $this->promotions = empty($request['promotions']) ? [] : $request['promotions'];
        $this->cost_price = 0;

        if (count($this->products) == 0 && count($this->promotions) == 0) {
            Utils::response('error', array('msg' => 'В оплате должен быть хотябы один товар.'), '2');
        }

        $this->shift();
        $this->unique();

        $this->products();
        $this->promotions();
        $this->create();
    }

    public function unique()
    {

        $transaction_exists = DB::query('
            SELECT *
            FROM ' . DB_TRANSACTIONS . '
            WHERE uniqid = "' . $this->uniqid . '" AND partner = ' . $this->partner . ' AND point = ' . $this->point . '
                AND employee = ' . $this->employee . ' AND shift = "' . $this->shift . '" AND total = ' . $this->total . '
            LIMIT 1
        ');

        //Если такая транзакция уже есть, то отдаем её, не создавая новую
        if (DB::getRecordCount($transaction_exists) >= 1) {
            $transaction_exists = DB::getRow($transaction_exists);
            Utils::response('success', $transaction_exists, 201);
        }
    }

    public function getCostPrice($products)
    {

        /* PRODUCT INPUT
        	"technical_card":8
            "count":1,
            "price":100,
            "total":100
            "discount":0,
            "time_discount": 0
            "points":0
            "weighted":false
        */

        foreach ($products as &$product) {

            $cost_price_33 = round($product['price'] * 0.33, 2);
            $product['net_mass'] = 0;                      //Выход
            $product['cost_price'] = 0;                    //Себестоимость
            $product['cost_price_33'] = $cost_price_33;    //33% от стоимости товара
            $product['items'] = [];                        //Массив ингредиентов тех. карты


            $technical_card = mDB::collection("technical_cards")->findOne([
                "country" => Auth::$country,
                "id" => (int)$product['technical_card']
            ]);


            $product['product'] = $technical_card['product']['id'];
            $product['name'] = $technical_card['product']['name'];
            $product['bulk'] = $technical_card['bulk_value'] . ' ' . $technical_card['bulk_untils'];

            foreach ($technical_card['composition'] as $item) {
                $product['net_mass'] += $item['net_mass'];
            }


            $product['items'] = $technical_card['composition'];

            $items_id = [];
            foreach ($product['items']  as $item) {
                $items_id[] = $item['item']['id'];
            }
            //Цены ингридиента в тех карте
            $point_items =  Utils::ArrayToObjectKey(DB::makeArray(DB::query("SELECT * FROM app_point_items WHERE item IN (" . implode(',', $items_id) . ") AND point = " . $this->point)), "item");

            //TODO получаем тут список только для того чтобы узнать until надо подумать как убрать
            $items =  Utils::ArrayToObjectKey(DB::makeArray(DB::query("SELECT * FROM app_items WHERE id IN (" . implode(',', $items_id) . ")")));


            foreach ($product['items'] as &$item) {

                if (isset($items[$item['item']['id']]) && $items[$item['item']['id']]['untils'] != 'шт') {
                    $item['count'] = $item['gross'];
                }



                //Если есть цена для ингридиента

                if (isset($point_items[$item['item']['id']])) {

                    $price = $point_items[$item['item']['id']]['price'];
                } else {
                    $price = 0;
                }
                //Если тех карта весовая 
                if ($technical_card['weighted'] == true) {
                    $item['count'] = $item['count'] / $product['net_mass'];
                }

                $item['count'] = $item['count'] * $product['count'];



                $item['cost_price'] = round($price * $item['count'], 2);
                $item['price'] = $price;

                if (!isset($product['cost_price'])) $product['cost_price'] = 0;
                $product['cost_price'] += $item['cost_price'];

                $this->cost_price += $item['cost_price'];


                $this->AddItem([
                    'id' => $item['item']['id'],
                    'count' => $item['count'] * -1,
                    'price' => $item['price']
                ]);
            }
        }



        return $products;
    }

    public function shift()
    {
        $shift = DB::select('*', DB_EMPLOYEE_SHIFTS, 'id = "' . $this->shift . '"', '', 1);
        if (!DB::getRecordCount($shift))
            Utils::response('error', 'Смена не найдена.', 1);

        $shift = DB::getRow($shift);

        //if($shift['shift_closed'] == 1)
        //response('error', 'Эта смена была закрыта, откройте новую смену.', 1);

        $this->employee = $shift['employee'];
    }

    public function products()
    {
        $this->products = $this->getCostPrice($this->products);
    }

    public function promotions()
    {

        foreach ($this->promotions as &$promotion) {

            $promotion['cost_price'] = 0;

            //Достаем состав акции
            $promotion['promotion_composition'] = DB::makeArray(DB::query("SELECT tc.id as technical_card, ptc.count, tc.`subname`, pr.`name`, CONCAT(tc.`bulk_value`, tc.`bulk_untils`) as units FROM `app_promotion_technical_cards` ptc JOIN `app_technical_card` tc ON tc.id = ptc.`technical_card` JOIN `app_products` pr ON pr.id = tc.`product` WHERE ptc.`promotion`=" . $promotion['promotion']));


            foreach ($promotion['promotion_composition'] as &$value)
                $value['count'] = $value['count'] * $promotion['count'];



            $promotion['promotion_name'] = DB::getRow(DB::query("SELECT name FROM app_promotions WHERE id=" . $promotion['promotion']))['name'];
            $promotion['promotion_composition'] = $this->getCostPrice($promotion['promotion_composition']);

            foreach ($promotion['promotion_composition'] as $value)
                $promotion['cost_price'] += $value['cost_price'];
        }
    }

    public function create()
    {

        $insert = [];

        //Текущее время сервера (Удалить, когда появится оффлайн режим)
        $this->date = time();

        $this->transaction = array(
            'uniqid' => $this->uniqid,
            'partner' => (int)$this->partner,
            'point' => (int)$this->point,
            'client_phone' => $this->phone,
            'employee' => $this->employee,
            'shift' => $this->shift,
            'created' => (int)$this->date,
            'created_datetime' => date('Y-m-d H:i:s', $this->date),
            'sum' => (float)$this->sum,
            'discount' => (int)$this->discount,
            'total' => (float)$this->total,
            'cost_price' => (float)$this->cost_price,
            'points' => (float)$this->points,
            'profit' => (float)($this->total - $this->cost_price),
            'type' => (int)$this->type,
            'promotion' => $this->promotion,
            'promotion_code' => $this->promotion_code
        );

        $transaction = DB::insert($this->transaction, DB_TRANSACTIONS);
        $this->transaction['id'] = $transaction;
        $this->proccess_id = $transaction;




        foreach ($this->products as $product) {
            $profit = $product['total'] - $product['cost_price'];


            $count = (isset($product['weighted']) && $product['weighted'] == true) ? 1 : $product['count'];
            $weight = (isset($product['weighted']) && $product['weighted'] == true) ? $product['count'] : 0;

            $insert[] = "($transaction, {$product['technical_card']}, {$product['product']}, '{$product['name']}',
                '{$product['bulk']}', {$count}, {$product['price']}, {$product['total']},
                {$product['cost_price']}, {$product['discount']}, {$product['time_discount']}, {$product['points']},
                {$profit}, NULL, '', '', 0, {$weight})";
        }

        foreach ($this->promotions as $promotion) {
            $composition = base64_encode(json_encode($promotion['promotion_composition']));
            $profit = $promotion['total'] - $promotion['cost_price'];

            $insert[] = "($transaction, NULL, NULL, '', '', {$promotion['count']}, {$promotion['price']},
                {$promotion['total']}, {$promotion['cost_price']}, {$promotion['discount']},
                {$promotion['time_discount']}, {$promotion['points']}, $profit, {$promotion['promotion']},
                '{$promotion['promotion_name']}', '$composition', 1, 0)";
        }

        DB::query('INSERT INTO ' . DB_TRANSACTION_ITEMS . '
            (
                transaction, technical_card, product, name, bulk, count, price, total, cost_price, discount,
                time_discount, points, profit, promotion, promotion_name, promotion_composition, type, weight
            ) VALUES ' . implode(',', $insert));

        $this->GetPointBalance();

        //Добавление записи в mongodb
        mDB::collection("transactions")->insertOne(
            array_merge(
                $this->transaction,

                [
                    'source' => $this->source,
                    'country' => Request::$country,
                    'items' => $this->products,
                    'promotions' => $this->promotions
                ]
            )
        );
    }
}