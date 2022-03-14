<?php


use Support\Pages;
use Support\DB;
use Support\mDB;
use Support\Request;
use Support\Utils;

require 'PromotionGiftsClass.php';
include ROOT . 'api/partner/warehouse/check_inventory.php';

class TransactionClassV2 extends PromotionGifts
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
    private $fiscal;               //1 - чек был напечатан, 0 - нет
    public $transaction;           //Транзакция
    public $total;                 //Сумма чека, с учетом скидок и бонусов
    public $uniqid;                //Уникальный id для оффлайн режима

    public function __construct($db, $point, $partner, $request)
    {
        $this->proccess = 4;        //proccess = 4 - процесс "Продажа"
        // $this->db = $db;
        $this->point = $point;
        $this->partner = $partner;

        $this->shift = $request['shift'];
        $this->uniqid = $request['uniqid'];
        $this->phone = $request['client_phone'];
        $this->date = $request['date'];
        $this->discount = $request['discount'];
        $this->sum = $request['sum'];
        $this->total = $request['total'];
        $this->points = $request['points'];
        $this->type = $request['type'];
        $this->fiscal = $request['fiscal'];
        $this->promotion = $request['promotion'];
        $this->promotion_code = $request['promotion_code'];
        $this->gifts = empty($request['promotion_gifts']) ? [] : $request['promotion_gifts'];
        $this->products = empty($request['products']) ? [] : $request['products'];
        $this->promotions = empty($request['promotions']) ? [] : $request['promotions'];
        $this->cost_price = 0;

        $this->shift();
        $this->unique();

        //Получаем информацию о накоплениях пользователя
        if ($this->phone) $this->client(false)
            ->gifts()
            ->execute();

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
            response('success', $transaction_exists, 201);
        }
    }

    public function getCostPrice($products)
    {

        $products_id = [];

        foreach ($products as $key => $product) {

            $cost_price_33 = round($products[$key]['price'] * 0.33, 2);

            $products[$key]['net_mass'] = 0;                      //Выход
            $products[$key]['cost_price'] = 0;                    //Себестоимость
            $products[$key]['cost_price_33'] = $cost_price_33;    //33% от стоимости товара
            $products[$key]['items'] = [];                        //Массив ингредиентов тех. карты

            if (!isset($product['technical_card']) || empty($product['technical_card']))
                $products[$key]['technical_card'] = $product['id'];

            $products_id[] = $products[$key]['technical_card'];          //Массив ID тех. карт

        }

        //Подзапросы на получение средней цены по партнеру/региону и получение средней цены по всей сети
        $partner_avg = '@partner_avg := (SELECT ROUND(AVG(price), 2) FROM ' . DB_POINT_ITEMS . ' WHERE item = pc.item AND partner = ' . $this->partner . ') AS partner_avg';
        $total_avg = '@total_avg := (SELECT ROUND(AVG(price), 2) FROM ' . DB_POINT_ITEMS . ' WHERE item = pc.item) AS total_avg';

        $query = DB::query('
            SELECT pc.item, ' . $partner_avg . ', ' . $total_avg . ', pc.technical_card,
            IF(pc.untils = "шт", pc.count, pc.gross) AS count,
            pc.net_mass,
            CASE
                WHEN pi.price IS NOT NULL THEN
                    ROUND(pi.price, 2)
                WHEN @partner_avg IS NOT NULL AND pi.price IS NULL THEN
                    ROUND(@partner_avg, 2)
                WHEN @total_avg IS NOT NULL AND @partner_avg IS NULL AND pi.price IS NULL THEN
                    ROUND(@total_avg, 2)
                ELSE
                    "none"
            END AS price
            FROM ' . DB_PRODUCT_COMPOSITION . ' pc
            LEFT JOIN ' . DB_POINT_ITEMS . ' pi ON pc.item = pi.item AND pi.point = ' . $this->point . '
            WHERE FIND_IN_SET(pc.technical_card, "' . implode(',', $products_id) . '")
        ');

        //Складываем ингредиенты в тех. карты
        while ($row = DB::getRow($query)) {
            for ($i = 0; $i < sizeof($products); $i++) {
                if ($products[$i]['technical_card'] == $row['technical_card']) {
                    $products[$i]['net_mass'] += $row['net_mass'];
                    $products[$i]['items'][] = $row;
                }
            }
        }

        //Определяем себестоимость тех. карты, себестоимость ингредиента без цены и отправляем ингредиенты в отчет по движению
        for ($i = 0; $i < sizeof($products); $i++) {
            for ($j = 0; $j < sizeof($products[$i]['items']); $j++) {

                if ($products[$i]['items'][$j]['price'] == 'none')
                    $products[$i]['items'][$j]['price'] =
                        round($products[$i]['cost_price_33'] / sizeof($products[$i]['items']), 2);

                if (isset($products[$i]['weighted']) && $products[$i]['weighted'] == true)
                    $products[$i]['items'][$j]['count'] = $products[$i]['items'][$j]['count'] / $products[$i]['net_mass'];

                $products[$i]['items'][$j]['count'] = $products[$i]['items'][$j]['count'] * $products[$i]['count'];

                if (!$products[$i]['items'][$j]['count'])
                    continue;

                $products[$i]['items'][$j]['cost_price'] =
                    round($products[$i]['items'][$j]['price'] * $products[$i]['items'][$j]['count'], 2);

                $products[$i]['cost_price'] += $products[$i]['items'][$j]['cost_price'];
                $this->cost_price += $products[$i]['items'][$j]['cost_price'];

                //Отправляем ингредиент в отчет по движению
                $item = array(
                    'id' => $products[$i]['items'][$j]['item'],
                    'count' => $products[$i]['items'][$j]['count'] * -1,
                    'price' => $products[$i]['items'][$j]['price']
                );
                $this->AddItem($item);
            }
        }

        return $products;
    }

    public function shift()
    {
        $shift = DB::select('*', DB_EMPLOYEE_SHIFTS, 'id = "' . $this->shift . '"', '', 1);
        if (!DB::getRecordCount($shift))
            response('error', 'Смена не найдена.', 1);

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



        for ($i = 0; $i < sizeof($this->promotions); $i++) {
            $this->promotions[$i]['cost_price'] = 0;

            foreach ($this->promotions[$i]['promotion_composition'] as &$value)
                $value['count'] = $value['count'] * $this->promotions[$i]['count'];

            $this->promotions[$i]['promotion_composition'] = $this->getCostPrice($this->promotions[$i]['promotion_composition']);

            foreach ($this->promotions[$i]['promotion_composition'] as $promotion)
                $this->promotions[$i]['cost_price'] += $promotion['cost_price'];
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
            'fiscal' => $this->fiscal,
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

                ['country' => Request::$country, 'items' => $this->products, 'promotions' => $this->promotions]
            )
        );
    }
}