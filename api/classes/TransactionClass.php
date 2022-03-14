<?php

use Support\Auth;
use Support\Pages;
use Support\DB;
use Support\mDB;

//require_once 'CalcWarehouse.php';
require 'PromotionGiftsClass.php';
include ROOT . 'api/partner/warehouse/check_inventory.php';

class TransactionClass extends PromotionGifts
{

    private $promotions;           //Объект "Акции"
    private $promotion_flag;       //false - скидка или бонусы не используется, true - используются скидка или бонусы
    private $sale;                 //Процент скидки
    private $points_percent;       //Процент накопления
    private $type;                 //0 - картой, 1 - наличными
    private $employee;             //ID сотрудника
    private $shift;                //ID кассовой смены
    private $balance;              //Баланс бонусов клиента
    private $sum;                  //Сумма чека, без учета скидок и бонусов
    private $products_insert;      //Строки, добавляемые в app_transaction_items
    private $partner_transaction;  //Строки, добавляемые в app_partner_transactions
    private $not_round;            //Округлять или нет
    public $transaction;           //ID транзакции
    private $minus_points;         //Списываемые баллы распределяем на количество позиций в чеке
    public $total;                 //Сумма чека, с учетом скидок и бонусов
    public $uniqid;
    private $cashback_percent;     //Кэшбэк
    public $PROMOTION_CODE;

    public function __construct($db, $point, $partner, $phone, $employee, $shift, $date, $products, $promotions, $balance, $sale, $points_percent, $type, $promotion_flag, $promotion_code, $promotion_gifts = [], $not_round = false, $uniqid = '')
    {

        // $this->db = $db;
        $this->point = $point;
        $this->partner = $partner;
        $this->phone = $phone;
        $this->employee = $employee;
        $this->shift = $shift;
        $this->date = $date;
        $this->balance = floor($balance);
        $this->sale = $sale;
        $this->points_percent = $points_percent;
        $this->type = $type;
        $this->promotion_flag = $promotion_flag;
        $this->proccess = 4;        //proccess = 4 - процесс "Продажа"
        $this->sum = 0;
        $this->total = 0;
        $this->minus_points = 0;
        $this->PROMOTION_CODE = $promotion_code;
        $this->gifts = $promotion_gifts ? $promotion_gifts : '[]';
        $this->not_round = $not_round;
        $this->cashback_percent = 0;
        $this->uniqid = $uniqid;

        if ($promotions) {
            $this->promotions = $this->JsonToArray($promotions);  //Акции
            $this->getPromotions();
        }

        $this->products = ($products) ? $this->JsonToArray($products) : [];    //Товары
        //Получаем информацию о накоплениях пользователя
        if ($this->phone) $this->parse('gifts')->client(false)->gifts()->execute();
        $this->getProducts();
    }

    //Обрабатываем список акций
    private function getPromotions()
    {

        for ($i = 0; $i < sizeof($this->promotions); $i++) {

            if (!$where)
                $where = 'pr.promotion = ' . $this->promotions[$i]['id'];
            else
                $where .= ' OR pr.promotion = ' . $this->promotions[$i]['id'];

            $this->promotions[$i]['cost_price'] = 0;
            $this->promotions[$i]['calc'] = false;
            $this->promotions[$i]['removal_items'] = [];

            $this->sum += ($this->promotions[$i]['count'] * $this->promotions[$i]['price']);
            //$this->total += floor($this->promotions[$i]['count'] * $this->promotions[$i]['price'] - ($this->promotions[$i]['count'] * $this->promotions[$i]['price']) * $this->sale / 100);
            $this->total += floor($this->promotions[$i]['price'] * (100 - $this->sale) / 100) * $this->promotions[$i]['count'];
        }

        //DB::query('LOCK TABLES '.DB_POINT_ITEMS.' pi2 READ, '.DB_POINT_ITEMS.' pi3 READ, '.DB_PRODUCT_COMPOSITION.' pc READ, '.DB_POINT_ITEMS.' pi READ, '.DB_PROMOTION_TECHNICAL_CARDS.' pr READ, '.DB_TECHNICAL_CARD.' tc READ');
        //Получаем список ингредиентов, которые учавствуют в акции
        $items = DB::query('SELECT pc.item AS id, pi.price, (pr.count * IF(pc.untils = "шт", pc.count, pc.gross)) AS count, pr.promotion, MIN(IFNULL(pi.price, 0)) AS calc,
                                        (SELECT AVG(pi2.price)
                                        FROM ' . DB_POINT_ITEMS . ' pi2
                                        WHERE pi2.item = pc.item AND pi2.partner = ' . $this->partner . '
                                        GROUP BY pi2.item) AS avg_price,
                                        (SELECT AVG(pi3.price)
                                        FROM ' . DB_POINT_ITEMS . ' pi3
                                        WHERE pi3.item = pc.item
                                        GROUP BY pi3.item) AS oth_avg_price
                                                FROM ' . DB_PROMOTION_TECHNICAL_CARDS . ' pr
                                                JOIN ' . DB_TECHNICAL_CARD . ' AS tc ON pr.technical_card = tc.id
                                                JOIN ' . DB_PRODUCT_COMPOSITION . ' AS pc ON pc.technical_card = tc.id
                                                LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = pc.item AND pi.point = ' . $this->point . '
                                                WHERE ' . $where . '
                                                GROUP BY pc.technical_card, pc.item');
        //DB::query('UNLOCK TABLES');

        while ($row = DB::getRow($items)) {
            //Если ингредиента нет на складе и никогда не было, то цена будет null
            if ($row['price'] == null)
                $row['price'] = 0;

            for ($i = 0; $i < sizeof($this->promotions); $i++) {
                //В чеке может быть разное количество одной и той же акции
                if ($this->promotions[$i]['id'] == $row['promotion']) {

                    $count = $row['count'] * $this->promotions[$i]['count'];

                    //Если на складе нет позиции, то невозможно рассчитать себестоимость
                    if ($row['calc'])
                        $this->promotions[$i]['calc'] = true;

                    if (!$row['price'] && $row['avg_price'])
                        $row['price'] = $row['avg_price'];
                    elseif (!$row['price'] && !$row['avg_price'] && $row['oth_avg_price'])
                        $row['price'] = $row['oth_avg_price'];

                    //Себестоимость рассчитывается исходя из состава тех.карт, участвующих в акции
                    $this->promotions[$i]['cost_price'] += $count * $row['price'];

                    $removal_item = array(
                        'id' => $row['id'],
                        'price' => $row['price'],
                        'count' => $count * -1
                    );

                    //Подготавливаем ингредиенты для списывания
                    $this->promotions[$i]['removal_items'][] = $removal_item;
                }
            }
        }
    }

    private function getProducts()
    {

        if (!sizeof($this->products))
            return;

        for ($i = 0; $i < sizeof($this->products); $i++) {

            if (!$where)
                $where = 'pc.technical_card = ' . $this->products[$i]['id'];
            else
                $where .= ' OR pc.technical_card = ' . $this->products[$i]['id'];

            $this->products[$i]['cost_price'] = 0;
            $this->products[$i]['avg_price'] = 0;
            $this->products[$i]['calc'] = true;
            $this->products[$i]['removal_items'] = [];

            $this->sum += ($this->products[$i]['count'] * $this->products[$i]['price']);

            //Если нет скидки по времени, то считаем с общей скидкой
            if (!$this->products[$i]['time_discount_flag'] && !$this->PROMOTION_CODE) {
                $this->products[$i]['time_discount_percent'] = 0;
                $this->total += floor($this->products[$i]['price'] * (100 - $this->sale) / 100) * $this->products[$i]['count'];
            } else //Если есть скидка по времени, то считаем с данной скидкой
                $this->total += floor($this->products[$i]['price'] * (100 - $this->products[$i]['time_discount_percent']) / 100) * $this->products[$i]['count'];
        }

        //DB::query('LOCK TABLES '.DB_POINT_ITEMS.' pi2 READ, '.DB_POINT_ITEMS.' pi3 READ, '.DB_PRODUCT_COMPOSITION.' pc READ, '.DB_POINT_ITEMS.' pi READ');
        //Получаем список ингредиентов, которых входят в состав тех.карты
        $items = DB::query('SELECT pc.item AS id, pi.price, IF(pc.untils = "шт", pc.count, pc.gross) AS count, pc.technical_card, MIN(IFNULL(pi.price, 0)) AS calc,
                                        (SELECT AVG(pi2.price)
                                        FROM ' . DB_POINT_ITEMS . ' pi2
                                        WHERE pi2.item = pc.item AND pi2.partner = ' . $this->partner . '
                                        GROUP BY pi2.item) AS avg_price,
                                        (SELECT AVG(pi3.price)
                                        FROM ' . DB_POINT_ITEMS . ' pi3
                                        WHERE pi3.item = pc.item
                                        GROUP BY pi3.item) AS oth_avg_price
                                                FROM ' . DB_PRODUCT_COMPOSITION . ' pc
                                                LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = pc.item AND pi.point = ' . $this->point . '
                                                WHERE ' . $where . '
                                                GROUP BY pc.technical_card, pc.item');
        //DB::query('UNLOCK TABLES');

        while ($row = DB::getRow($items)) {
            //Если ингредиента нет на складе и никогда не было, то цена будет null
            if ($row['price'] == null)
                $row['price'] = 0;

            for ($i = 0; $i < sizeof($this->products); $i++) {
                //В чеке может быть разное количество одной и той же позиции
                if ($this->products[$i]['id'] == $row['technical_card']) {
                    $count = $this->products[$i]['count'] * $row['count'];

                    //Если на складе нет позиции, то невозможно рассчитать себестоимость
                    if ($row['calc'] == 0 || $row['calc'] == null)
                        $this->products[$i]['calc'] = false;

                    //Если известна средня себестоимость
                    if ($row['avg_price'])
                        $this->products[$i]['avg_price'] += $count * $row['avg_price'];
                    elseif ($row['oth_avg_price'])
                        $this->products[$i]['avg_price'] += $count * $row['oth_avg_price'];

                    if (!$row['price'] && $row['avg_price'])
                        $row['price'] = $row['avg_price'];
                    elseif (!$row['price'] && !$row['avg_price'] && $row['oth_avg_price'])
                        $row['price'] = $row['oth_avg_price'];

                    //Рассчитываем себестоимость
                    $this->products[$i]['cost_price'] += $count * $row['price'];

                    $removal_item = array(
                        'id' => $row['id'],
                        'price' => $row['price'],
                        'count' => $count * -1
                    );

                    //Подготавливаем ингредиенты к списанию
                    $this->products[$i]['removal_items'][] = $removal_item;
                }
            }
        }
    }

    public function create()
    {

        if ($this->promotion_flag) {

            //Если баланс меньше суммы покупки или равен ей, то необходимо вычесть все средства с баланса для частичного погашения покупки
            if ($this->balance <= $this->sum) {
                $points = $this->balance * -1;
                $this->total = $this->sum - $this->balance;
            } else {
                //Иначе, если баланс бонусов превышает сумму покупки, то сумма покупки становится равной нулю
                $points = $this->sum * -1;
                $this->total = 0;
            }

            $this->minus_points = $points;
        }

        $fields = array(
            'uniqid' => $this->uniqid,
            'partner' => $this->partner,
            'point' => $this->point,
            'client_phone' => $this->phone,
            'employee' => $this->employee,
            'shift' => $this->shift,
            'created' => $this->date,
            'created_datetime' => date('Y-m-d H:i:s', $this->date),
            'fiscal' => 0,
            'sum' => $this->sum,
            'discount' => $this->sale,
            'total' => $this->total,
            'points' => $points,
            'type' => $this->type,
            'promotion' => $this->promotion_flag,
            'promotion_code' => $this->PROMOTION_CODE
        );

        if (!$this->transaction = DB::insert($fields, DB_TRANSACTIONS))
            response('error', 'Не удалось создать транзакцию.', 1);

        $this->proccess_id = $this->transaction;

        $this->insertTransactionItems();
    }

    private function productCostPrice($product, $cost_price)
    {

        //DB::query('LOCK TABLES '.DB_PRODUCT_COMPOSITION.' pc READ');
        $composition = DB::query('   SELECT pc.id, pc.item, pc.count
                                            FROM ' . DB_PRODUCT_COMPOSITION . ' pc
                                            WHERE pc.technical_card = ' . $product['id'] . ' AND pc.count != 0');
        //DB::query('UNLOCK TABLES');

        $items_count = DB::getRecordCount($composition);

        if (!$items_count)
            return 0;

        $product['cost_price'] = $cost_price;

        while ($row = DB::getRow($composition)) {

            $item = array(
                'id' => $row['item'],
                'price' => $product['cost_price'] / $items_count / $product['count'] / $row['count'],
                'count' => $product['count'] * $row['count'] * -1
            );

            $this->AddItem($item);
        }

        return $cost_price;
    }

    private function promotionCostPrice($promotion, $cost_price)
    {

        //DB::query('LOCK TABLES '.DB_PROMOTION_TECHNICAL_CARDS.' ptc READ, '.DB_PRODUCT_COMPOSITION.' pc READ');
        $composition = DB::query('SELECT pc.item, (pc.count * ptc.count) AS count
                                        FROM ' . DB_PROMOTION_TECHNICAL_CARDS . ' ptc
                                        JOIN ' . DB_PRODUCT_COMPOSITION . ' pc ON pc.technical_card = ptc.technical_card
                                        WHERE ptc.promotion = ' . $promotion['id'] . ' AND pc.count != 0');
        //DB::query('UNLOCK TABLES');

        $items_count = DB::getRecordCount($composition);

        if (!$items_count)
            return 0;

        $promotion['cost_price'] = $cost_price;

        while ($row = DB::getRow($composition)) {

            $item = array(
                'id' => $row['item'],
                'price' => $promotion['cost_price'] / $items_count / $promotion['count'] / $row['count'],
                'count' => $promotion['count'] * $row['count'] * -1
            );

            $this->AddItem($item);
        }

        return $cost_price;
    }

    private function insertTransactionItems()
    {

        $total_cost_price = 0;
        $this->total = 0;

        //Если есть массив с акциями
        if (is_array($this->promotions)) {
            //Добавляем акции
            for ($i = 0; $i < sizeof($this->promotions); $i++) {

                $point = 0;

                //Получаем цену с учетом скидки * количество
                if (!$this->not_round)
                    $total_price = floor($this->promotions[$i]['price'] * (100 - $this->sale) / 100) * $this->promotions[$i]['count'];
                else
                    $total_price = $this->promotions[$i]['price'] * (100 - $this->sale) / 100 * $this->promotions[$i]['count'];

                //Вычисляем сколько бонусов нужно списать
                if (abs($this->minus_points) <= $total_price)
                    $minus_points = $this->minus_points;
                else
                    $minus_points = $total_price * -1;
                $this->minus_points -= $minus_points;

                //Итоговая цена с учетом бонусов
                $total = $total_price + $minus_points;
                $this->total += $total;

                //Накапливаем бонусы
                if ($this->points_percent && !$this->promotion_flag)
                    $this->cashback_percent += $total_price * $this->points_percent / 100;

                if (!$this->promotions[$i]['calc'])
                    $this->promotions[$i]['cost_price'] = $this->promotionCostPrice($this->promotions[$i], $total * 0.33);
                else {
                    foreach ($this->promotions[$i]['removal_items'] as $value)
                        $this->AddItem($value);
                }


                $profit = $total - $this->promotions[$i]['cost_price'];

                if (!$insert)
                    $insert = '("' . $this->transaction . '", NULL, NULL, "", "", "' . $this->promotions[$i]['count'] . '",
                                "' . $this->promotions[$i]['price'] . '", "' . $total . '", "' . $this->promotions[$i]['cost_price'] . '",
                                "' . $this->sale . '", "0", "' . $minus_points . '", "' . $profit . '", "' . $this->promotions[$i]['id'] . '",
                                "' . str_replace('"', "'", $this->promotions[$i]['name']) . '", "' . base64_encode(json_encode($this->promotions[$i]['products'])) . '", "1")';
                else
                    $insert .= ', ("' . $this->transaction . '", NULL, NULL, "", "", "' . $this->promotions[$i]['count'] . '",
                                "' . $this->promotions[$i]['price'] . '", "' . $total . '", "' . $this->promotions[$i]['cost_price'] . '",
                                "' . $this->sale . '", "0", "' . $minus_points . '", "' . $profit . '", "' . $this->promotions[$i]['id'] . '",
                                "' . str_replace('"', "'", $this->promotions[$i]['name']) . '", "' . base64_encode(json_encode($this->promotions[$i]['products'])) . '", "1")';

                $total_cost_price += $this->promotions[$i]['cost_price'];
            }
        }

        //Если есть массив с продуктами
        if (is_array($this->products)) {
            //Добавляем товары
            for ($i = 0; $i < sizeof($this->products); $i++) {

                if (!$this->products[$i]['time_discount_percent'])
                    $sale = $this->sale; // Обычная скидка
                else {
                    $sale = $this->products[$i]['time_discount_percent']; // Скидка по времени
                    //$sale = 0;
                }

                //Получаем цену с учетом скидки * количество
                if (!$this->not_round)
                    $total_price = floor($this->products[$i]['price'] * (100 - $sale) / 100) * $this->products[$i]['count'];
                else
                    $total_price = $this->products[$i]['price'] * (100 - $sale) / 100 * $this->products[$i]['count'];

                //Рассчитываем бонусы, которые необходимо списать
                if (abs($this->minus_points) <= $total_price)
                    $minus_points = $this->minus_points;
                else
                    $minus_points = $total_price * -1;
                $this->minus_points -= $minus_points;

                //Вычисляем итоговую цену, с учетом бонусов
                $total = $total_price + $minus_points;
                $this->total += $total;

                //Накапливаем бонусы
                if ($this->points_percent && !$this->promotion_flag) {
                    //Если есть кэшбэк, то по нему
                    if ($this->products[$i]['cashback_percent'] > 0)
                        $this->cashback_percent += $this->products[$i]['cashback_percent'] * $total_price / 100;
                    //Иначе по программе лояльности
                    else
                        $this->cashback_percent += $total_price * $this->points_percent / 100;
                }

                //Если удалось рассчитать среднюю себестоимость, но не удалось рассчитать себестоимость продукта на складе
                if (!$this->products[$i]['calc'] && $this->products[$i]['avg_price'])
                    $this->products[$i]['cost_price'] = $this->productCostPrice($this->products[$i], $this->products[$i]['avg_price']);
                //Если не удалось расчитать себестоимость вовсе, то себестоимость по формуле
                elseif (!$this->products[$i]['calc'])
                    $this->products[$i]['cost_price'] = $this->productCostPrice($this->products[$i], $total * 0.33);
                else {
                    foreach ($this->products[$i]['removal_items'] as $value)
                        $this->AddItem($value);
                }

                $profit = $total - $this->products[$i]['cost_price'];

                $name = $this->products[$i]['name'];

                if ($this->products[$i]['subname'])
                    $name .= ' (' . $this->products[$i]['subname'] . ')';

                $name = str_replace('"', "'", $name);

                $bulk = $this->products[$i]['bulk_value'] . ' ' . $this->products[$i]['bulk_untils'];

                if (!$insert)
                    $insert = '("' . $this->transaction . '", "' . $this->products[$i]['id'] . '", "' . $this->products[$i]['product'] . '",
                                "' . $name . '", "' . $bulk . '", "' . $this->products[$i]['count'] . '",
                                "' . $this->products[$i]['price'] . '", "' . $total . '", "' . $this->products[$i]['cost_price'] . '",
                                "' . $this->sale . '", "' . $this->products[$i]['time_discount_percent'] . '", "' . $minus_points . '", "' . $profit . '", NULL, "", "", "0")';
                else
                    $insert .= ', ("' . $this->transaction . '", "' . $this->products[$i]['id'] . '", "' . $this->products[$i]['product'] . '",
                                "' . $name . '", "' . $bulk . '", "' . $this->products[$i]['count'] . '",
                                "' . $this->products[$i]['price'] . '", "' . $total . '", "' . $this->products[$i]['cost_price'] . '",
                                "' . $this->sale . '", "' . $this->products[$i]['time_discount_percent'] . '", "' . $minus_points . '", "' . $profit . '", NULL, "", "", "0")';

                $total_cost_price += $this->products[$i]['cost_price'];
            }
        }

        //DB::query('LOCK TABLES '.DB_TRANSACTION_ITEMS.' WRITE');
        DB::query('INSERT INTO ' . DB_TRANSACTION_ITEMS . ' (transaction, technical_card, product, name, bulk, count, price, total, cost_price, discount, time_discount, points, profit, promotion, promotion_name, promotion_composition, type) VALUES ' . $insert);
        //DB::query('UNLOCK TABLES');

        //DB::query('LOCK TABLES '.DB_TRANSACTIONS.' WRITE');
        DB::query('UPDATE ' . DB_TRANSACTIONS . '
                        SET cost_price = ' . $total_cost_price . ',
                            profit = total - cost_price,
                            points = points + ' . $this->cashback_percent . ',
                            total = ' . $this->total . '
                            WHERE id = ' . $this->transaction);
        //DB::query('UNLOCK TABLES');

        if ($this->points_percent && !$this->promotion_flag)
            DB::query(
                'UPDATE ' . DB_CLIENTS . '
                SET balance = balance + ' . $this->cashback_percent . '
                WHERE phone = ' . $this->phone
            );

        $this->GetPointBalance();
    }
}

class Refunds
{

    private $db;
    private $partner;
    private $point;

    public function __construct($db, $partner, $point = false)
    {

        // $this->db = $db;
        $this->partner = $partner;
        $this->point = $point;
    }

    public function request()
    {

        if (!$transaction = DB::escape($_REQUEST['transaction']))
            response('error', 'Не передан ID транзакции.', 1);

        $request = DB::select('id', DB_REFUND_REQUESTS, 'id = ' . $transaction, '', 1);

        if (DB::getRecordCount($request) != 0)
            response('error', 'Заявка уже существует.', 1);

        $transaction_data = DB::select('*', DB_TRANSACTIONS, 'id = ' . $transaction . ' AND partner = ' . $this->partner . ' AND point = ' . $this->point, '', 1);

        if (DB::getRecordCount($transaction_data) == 0)
            response('error', 'Транзакция не найдена.', 1);

        $transaction_data = DB::getRow($transaction_data);

        inventoryCheck($transaction_data['point'], $transaction_data['created']);

        /* if((time() - $transaction_data['created']) >= (24 * 60 * 60))
            response('error', 'Невозможно осуществить возврат, т.к. прошло больше дня с момента создания чека.', 1);
 */
        $transaction_items = DB::select('*', DB_TRANSACTION_ITEMS, 'transaction = ' . $transaction);
        $transaction_items = DB::makeArray($transaction_items);

        $transaction_data['composition'] = base64_encode(
            json_encode(
                $transaction_items
            )
        );
        $transaction_data['refunded'] = 0;
        $transaction_data['refund_created'] = time();

        DB::insert($transaction_data, DB_REFUND_REQUESTS);

        response('success', 'Заявка создана.', 201);
    }

    public function accept()
    {

        if (!$request = DB::escape($_REQUEST['request']))
            response('error', 'Не передан ID заявки.', 1);

        //DB::query('LOCK TABLES '.DB_REFUND_REQUESTS.' r READ');
        $request_data = DB::query('
            SELECT r.id, r.created, r.point, r.refunded, r.client_phone, r.employee, r.points
            FROM ' . DB_REFUND_REQUESTS . ' r
            WHERE r.id = ' . $request . ' AND r.partner = ' . $this->partner . '
        ');
        //DB::query('UNLOCK TABLES');

        if (DB::getRecordCount($request_data) == 0)
            response('error', 'Заявка не найдена.', 1);

        $request_data = DB::getRow($request_data);

        if ($request_data['refunded'] == 1)
            response('error', 'Заявка уже была подтверждена.', 422);
        if ($request_data['refunded'] == 2)
            response('error', 'Заявка уже была отменена.', 422);

        inventoryCheck($request_data['point'], $request_data['created']);

        $dyd = date('Ym', (int)$request_data['created']);
        //Расскоментируйте строку ниже, для осуществления возврата ингредиентов
        DB::delete(DB_PARTNER_TRANSACTIONS, 'proccess_id = ' . $request . ' AND proccess = 4 AND dyd=' . $dyd);
        DB::delete(DB_TRANSACTIONS, 'id = ' . $request);
        DB::update(array('refunded' => 1), DB_REFUND_REQUESTS, 'id = ' . $request);


        mDB::collection("transactions")->deleteOne([
            'id' => (int)$request,
            'country' => Auth::$country
        ]);


        response('success', 'Чек отменен.', 200);
    }

    public function reject()
    {

        if (!$request = DB::escape($_REQUEST['request']))
            response('error', 'Не передан ID заявки.', 1);

        $request_data = DB::query('
            SELECT r.id, r.created, r.point, r.refunded
            FROM ' . DB_REFUND_REQUESTS . ' r
            WHERE r.id = ' . $request . ' AND r.partner = ' . $this->partner . '
        ');

        if (DB::getRecordCount($request_data) == 0)
            response('error', 'Заявка не найдена.', 1);

        $request_data = DB::getRow($request_data);

        if ($request_data['refunded'] == 1)
            response('error', 'Заявка уже была подтверждена.', 422);
        if ($request_data['refunded'] == 2)
            response('error', 'Заявка уже была отменена.', 422);

        DB::update(array('refunded' => 2), DB_REFUND_REQUESTS, 'id = ' . $request);

        response('success', 'Заявка на возврат отклонена.', 200);
    }
}