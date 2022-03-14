<?php

namespace Controllers;

use Support\Auth;
use Support\DB;
use Support\mDB;
use Support\Request;

class SyncController
{

    //Крон для автоизменения мены
    public static function autoChangePrices()
    {

        $auto_prices = mDB::collection("auto_prices")->find([
            "done" => ['$ne' => true],
            "deletet_at" => [
                '$exists' => false,
            ],
        ]);

        foreach ($auto_prices as $auto_price) {
            $count = 0;
            $logs = [];
            foreach ($auto_price['prices'] as $price) {
                $count++;

                if ($price['point_id'] > 0 && isset($price['price'])) {

                    mDB::collection("technical_cards")->updateOne([
                        "id" => (int) $price['technical_card_id'],
                        "country" => Auth::$country,
                    ], [
                        '$set' => [
                            'prices.' . $price['point_id'] . '.price' => $price['price'],
                        ],
                    ]);

                    $logs[] = 'id ' . $price['technical_card_id'] . ' prices.' . $price['point_id'] . '.price = ' . $price['price'];

                }

            }

            mDB::collection('auto_prices')->updateOne([
                "_id" => $auto_price->_id,
            ], [
                '$set' => [
                    "logs" => $logs,
                    "done" => true,
                ],
            ]);
        }
    }

    public static function syncAddUntilsInTechnicalCard()
    {
        ini_set('max_execution_time', '3000');
        set_time_limit(3000);

        $technical_cards = mDB::collection("technical_cards")->find([

            "country" => Auth::$country,
            "composition.untils" => ['$exists' => false],

        ], [
            'limit' => 100,
        ]);

        $ids = [];
        foreach ($technical_cards as $technical_card) {

            $composition = (array) $technical_card->composition;
            $tmp = [];
            foreach ($composition as $composition_item) {
                $composition_item = (array) $composition_item;
                $composition_item['untils'] = DB::getRow(DB::query("SELECT * FROM app_items WHERE id=" . $composition_item['item']['id']))['untils'];
                $composition_item['item'] = (array) $composition_item['item'];
                $tmp[] = $composition_item;
            }

            mDB::collection("technical_cards")->updateOne([
                "_id" => $technical_card->_id,
            ], [
                '$set' => [
                    'composition' => $tmp,
                ],
            ]);
            $ids[] = (string) $technical_card->_id . ' ';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo 'done - ' . json_encode($ids);
        if (count($ids) > 0) {
            echo '<script>setTimeout(function(){
        location.reload();
        },0);</script>';
        }
    }

    public static function ProductCategoryOnCards()
    {

        $all_ids = mDB::collection("technical_cards")->distinct("product.id", [
            "product.category" => 0, //['$exists' => false],
            "country" => Auth::$country,
        ]);
        $products = DB::makeArray(DB::query("SELECT * FROM app_products WHERE id IN (" . implode(", ", $all_ids) . ")"));

        foreach ($products as $product) {

            mDB::collection("technical_cards")->updateMany([
                "product.id" => (int) $product['id'],
                "country" => Auth::$country,
            ], [
                '$set' => [
                    "product.category" => (int) $product['category'],
                ],
            ]);
            //  var_dump($product);
            //exit;
        }
        echo 'done';
    }

    //Перенос тех карт в монга
    public static function syncTechnicalCards()
    {

        //   echo Auth::$country;
        //   exit;
        ini_set('max_execution_time', '3000');
        set_time_limit(3000);

        $technical_cards = DB::makeArray(DB::query('SELECT tc.*, pr.id as "pr_id", pr.name as "pr_name", pr.image as "pr_image", pr.color as "pr_color"  FROM  `app_technical_card` tc JOIN `app_products` pr ON pr.id = tc.`product`'));

        foreach ($technical_cards as $technical_card) {

            /*    $compositions = DB::makeArray(DB::query('SELECT c.*, i.name as "item_name" FROM app_product_composition c JOIN `app_items` i ON i.id=c.`item`  WHERE c.technical_card=' . $technical_card['id']));
            $compositionData = [];

            $composition_text = [];
            foreach ($compositions as $composition) {
            $composition_text[] = trim($composition['item_name']);
            $compositionData[] = [
            'item' => [
            'id' => (int)$composition['item'],
            'name' => $composition['item_name'],
            ],
            'count' => (float)$composition['count'],
            'gross' => (float)$composition['gross'],
            'net_mass' => (float)$composition['net_mass'],
            'mass_block' => $composition['mass_block'] == 1 ? true : false
            ];
            }

            $existItem = mDB::collection("technical_cards")->findOne([
            "id" => (int)$technical_card['id'],
            "country" => Auth::$country
            ]);

             */
            $productPrices = DB::makeArray(DB::query("SELECT * FROM `app_product_prices` WHERE `technical_card`=" . $technical_card['id']));

            $prices = [];

            foreach ($productPrices as $price) {
                $prices[(int) $price['point']] = [
                    'point' => (int) $price['point'],
                    'price' => (float) $price['price'],
                    'hide' => $price['hide'] == 1 ? true : false,
                ];
            }

            /*   $row = DB::getRow(DB::query("SELECT product_id, GROUP_CONCAT(partner_id) as partners FROM `app_archive` WHERE model='technical_card' AND `product_id`=" . $technical_card['id'] . "   GROUP BY model, product_id"));
            $archive = [];

            if (isset($row['partners'])) {
            $partners = explode(",", $row['partners']);
            foreach ($partners as &$partner) $partner = (int)$partner;
            $archive = $partners;
            }
             */

            /*  if (!isset($existItem->_id)) {
            mDB::collection("technical_cards")->insertOne([
            'product' => [
            'id' => (int)$technical_card['product'],
            'name' => (string)$technical_card['pr_name'],
            'image' => (string)$technical_card['pr_image'],
            'color' => (int)$technical_card['pr_color']
            ],
            "name" => (string)$technical_card['pr_name'] . ' ' . $technical_card['subname'] . ' ' . $technical_card['bulk_value'] . ' ' . $technical_card['bulk_untils'],
            'bulk_untils' => $technical_card['bulk_untils'],
            'bulk_value' => (float)$technical_card['bulk_value'],
            'subname' => $technical_card['subname'],
            'name_price' => $technical_card['name_price'],
            'cashback_percent' => (int)$technical_card['cashback_percent'],
            'preparing_minutes' => (int)$technical_card['preparing_minutes'],
            'preparing_seconds' => (int)$technical_card['preparing_seconds'],
            'cooking_method' => $technical_card['cooking_method'],
            'color' => (int)$technical_card['color'],
            'price' => (float)$technical_card['price'],
            'not_promotion' => $technical_card['not_promotion'] == 1 ? true : false,
            'weighted' => $technical_card['weighted'] == 1 ? true : false,
            'partner' => (int)$technical_card['partner'],
            'code' => (int)$technical_card['code'],
            'id' => (int)$technical_card['id'],
            'country' => Auth::$country,
            'composition_text' => implode(', ', $composition_text),
            'composition' => $compositionData,
            'different_price' => $technical_card['different_price'] == 1 ? true : false,
            'archive' => $archive,
            'prices' => $prices,
            'enableForAll' => (int)$technical_card['partner'] == 0 ? true : false,
            'canEdit' => [],
            'showFor' => [],
            'hideFor' => [],
            ]);
            } else {*/

            mDB::collection("technical_cards")->updateOne([
                'id' => (int) $technical_card['id'],
                'country' => Auth::$country,
            ], [

                '$set' => [
                    /*     'product' => [
                    'id' => (int)$technical_card['product'],
                    'name' => (string)$technical_card['pr_name'],
                    'image' => (string)$technical_card['pr_image'],
                    'color' => (int)$technical_card['pr_color']
                    ],
                    "name" => (string)$technical_card['pr_name'] . ' ' . $technical_card['subname'] . ' ' . $technical_card['bulk_value'] . ' ' . $technical_card['bulk_untils'],
                    'bulk_untils' => $technical_card['bulk_untils'],
                    'bulk_value' => $technical_card['bulk_value'],
                    'subname' => $technical_card['subname'],
                    'name_price' => $technical_card['name_price'],
                    'cashback_percent' => (int)$technical_card['cashback_percent'],
                    'preparing_minutes' => (int)$technical_card['preparing_minutes'],
                    'preparing_seconds' => (int)$technical_card['preparing_seconds'],
                    'cooking_method' => $technical_card['cooking_method'],
                    'color' => (int)$technical_card['color'],
                    'not_promotion' => $technical_card['not_promotion'] == 1 ? true : false,
                    'weighted' => $technical_card['weighted'] == 1 ? true : false,
                    'partner' => (int)$technical_card['partner'],
                    'code' => (int)$technical_card['code'],
                    'archive' => $archive,*/
                    'price' => (float) $technical_card['price'],
                    'prices' => $prices,
                    /*'composition_text' => implode(', ', $composition_text),
                'composition's => $compositionData,*/
                ],

            ]);
            //   }
        }
    }

    public static function syncCategories()
    {

        $data = DB::makeArray(DB::query("SELECT id,name,parent,partner,image,color FROM app_product_categories"));

        foreach ($data as $item) {
            $itemFind = mDB::collection("product_categories")->findOne([
                '$or' => [
                    ['id' => (int) $item['id']],
                    ['id' => (string) $item['id']],
                ],
                'country' => Request::$country,
            ]);

            $item['country'] = Request::$country;
            $item['id'] = (int) $item['id'];
            $item['parent'] = (int) $item['parent'];
            $item['partner'] = (int) $item['partner'];
            $item['color'] = (int) $item['color'];
            $item['points'] = [];

            $item['enableForAll'] = (int) $item['partner'] == 0 ? true : false;
            $item['canEdit'] = [];
            $item['showFor'] = [];
            $item['hideFor'] = [];

            $points = DB::makeArray(DB::query("SELECT point.id as point, IF(menu.category> 0, 1, 0) as enable  FROM `app_partner_points` point LEFT JOIN (SELECT * FROM `app_menu_categories`WHERE `category` = " . $item['id'] . ") menu ON menu.`point` = point.id"));

            foreach ($points as $point) {
                $item['points'][(int) $point['point']] = [
                    'point' => (int) $point['point'],
                    'enable' => $point['enable'] == 1 ? true : false,
                ];
            }

            if (isset($itemFind->id)) {
                mDB::collection("product_categories")->updateOne([
                    "_id" => $itemFind->_id,
                ], [
                    '$set' => $item,
                ]);
            } else {
                mDB::collection("product_categories")->insertOne($item);
            }
        }
    }

    public static function syncPartners()
    {

        $country = Request::$country;

        mDB::collection("partners")->deleteMany([
            "country" => $country,
        ]);

        $partners = DB::makeArray(DB::query('SELECT p.*, t.token FROM `app_partner` p JOIN `app_partners_token` t WHERE t.`partner` = p.id AND LENGTH(t.token) > 3 GROUP BY p.id'));

        $domains = [
            'by' => "br",
            "kz" => "kz",
            "ru" => "cp",
        ];

        foreach ($partners as &$partner) {

            $partner['id'] = (int) $partner['id'];
            $partner['currency'] = (int) $partner['currency'];
            $partner['city'] = (int) $partner['city'];

            $partner['country'] = $country;
            $partner['enter'] = 'https://' . $domains[$country] . '.cwflow.ru/authlink?token=' . $partner['token'];
        }

        mDB::collection("partners")->insertMany($partners);
        echo 'done';
        exit;
        /*
        $data = DB::makeArray(DB::query("SELECT c.id, concat(c.name, ' (',IFNULL(p.name,'Глобальное'),')') as name FROM `app_product_categories` c LEFT JOIN `app_partner` p ON c.`partner`=p.id"));

        foreach ($data as $item){
        $itemFind = mDB::collection("product_categories")->findOne([
        'id'=>$item['id'],
        'country'=>Request::$country
        ]);

        $item['country'] =  Request::$country;

        if(isset($itemFind->id)){
        mDB::collection("product_categories")->updateOne([
        "_id"=>$itemFind->_id
        ],[
        '$set'=>$item
        ]);
        }else{
        mDB::collection("product_categories")->insertOne($item);
        }
        }

        echo 'done';*/
        exit;
        // mDB::collection("product_categories")->inserOn

    }

    //Добавление данных статистики
    public static function addDatatransactionForStatistic()
    {
        set_time_limit(0);

        $data = DB::makeArray(DB::query("SELECT GROUP_CONCAT(p.id) as ids, c.code FROM app_partner p JOIN app_cities c ON p.city=c.id group by code"));

        $now = strtotime(date('Y-m-d'));

        $from = strtotime("-1 days", $now);

        foreach ($data as $partner) {

            date_default_timezone_set($partner['code']);

            $partner['ids'] = explode(',', $partner['ids']);
            foreach ($partner['ids'] as &$one_id) {
                $one_id = (int) $one_id;
            }

            $result = mDB::collection("transactions")->aggregate(
                [
                    [
                        '$match' => [
                            'partner' => ['$in' => $partner['ids']],
                            "country" => "ru",
                            "created" => ['$gt' => $from],
                        ],
                    ],
                    [
                        '$group' => [
                            '_id' => [
                                'point' => '$point',
                                'partner' => '$partner',
                                'country' => '$country',
                                'dayOfWeek' => [
                                    '$dayOfWeek' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get(),
                                    ],
                                ],
                                'month' => [
                                    '$month' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get(),
                                    ],
                                ],

                                'year' => [
                                    '$year' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get(),
                                    ],
                                ],

                                'day' => [
                                    '$dayOfMonth' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get(),
                                    ],
                                ],
                                'hour' => [
                                    '$hour' => [
                                        'date' => ['$toDate' => ['$multiply' => ['$created', 1000]]],
                                        'timezone' => date_default_timezone_get(),
                                    ],
                                ],
                            ],
                            'sales' => [
                                '$sum' => '$total',
                            ],
                            'profit' => [
                                '$sum' => '$profit',
                            ],
                            'avg_check' => ['$avg' => '$total'],
                            'checks' => [
                                '$sum' => 1,
                            ],
                            'created_from' => ['$first' => '$created'],
                            'created_to' => ['$last' => '$created'],

                        ],
                    ],
                ],
                [
                    //    "sort"=>['_id'=>-1],
                    //    "limit"=>10
                ]
            ); //->toArray();
            $count = 0;
            $insert = [];
            foreach ($result as $item) {

                $hash = md5($item['_id']['point'] . $item['created_from'] . $item['_id']['country']);

                $dubleItem = mDB::collection('analytics_transactions')->findOne([
                    "hash" => $hash,
                ]);

                if (!isset($dubleItem->_id)) {
                    $count++;
                    $insert[] = [
                        'point' => (int) $item['_id']['point'],
                        'hash' => $hash,
                        'partner' => (int) $item['_id']['partner'],
                        'country' => $item['_id']['country'],
                        'dayOfWeek' => $item['_id']['dayOfWeek'],
                        'year' => $item['_id']['year'],
                        'day' => $item['_id']['day'],
                        'hour' => $item['_id']['hour'],
                        'sales' => $item['sales'],
                        'profit' => $item['profit'],
                        'avg_check' => $item['avg_check'],
                        'checks' => $item['checks'],
                        'created_from' => $item['created_from'],
                        'created_to' => $item['created_to'],
                        'country' => Auth::$country,

                    ];
                }
            }

            if (count($insert) > 0) {
                mDB::collection("analytics_transactions")->insertMany($insert);
            }

            echo 'done ' . $count . ' ';
        }
    }

    public static function refund()
    {
        set_time_limit(0);

        $result = DB::makeArray(DB::select("*", "app_refund_requests", "refunded=1", "id DESC", "100"));

        $ids = [];
        foreach ($result as $val) {
            $ids[] = $val['id'];
        }

        if (count($ids) > 0) {
            mDB::collection('transactions')->deleteMany([
                "id" => ['$in' => $ids],
            ]);
        }

        echo 'ok';
    }

    public static function partner_transactions()
    {

        set_time_limit(0);
        $data = mDB::collection("partner_transactions")->findOne([
            'country' => Auth::$country,
        ], [
            'sort' => ['id' => 1],
            'limit' => 1,
        ]);

        $last_id = $data->id ? $data->id : 9999999999999;

        $result = DB::select("*", "app_partner_transactions", "id<$last_id AND dyd>202107", "id DESC", "50000");

        if (DB::getRecordCount($result) >= 1) {
            $input = [];
            while ($item = DB::getRow($result)) {

                $item['id'] = (int) $item['id'];

                $item['proccess'] = (int) $item['proccess'];
                $item['proccess_id'] = (int) $item['proccess_id'];
                $item['date'] = (int) $item['date'];
                $item['count'] = (float) $item['count'];
                $item['price'] = (float) $item['price'];
                $item['total'] = (float) $item['total'];

                $item['type'] = (int) $item['type'];
                $item['item'] = (int) $item['item'];

                $item['partner'] = (int) $item['partner'];
                $item['point'] = (int) $item['point'];

                $item['balance_begin'] = (float) $item['balance_begin'];
                $item['average_price_begin'] = (float) $item['average_price_begin'];
                $item['average_price_end'] = (float) $item['average_price_end'];

                $item['balance_end'] = (float) $item['balance_end'];

                $item['created'] = (int) $item['created'];
                $item['dyd'] = (int) $item['dyd'];

                $item['country'] = Request::$country;

                $input[] = $item;
                //  $tmp = mDB::collection("partner_transactions")->findOne(['id' => $item['id']]);
                //  if (!isset($tmp->_id))
                mDB::collection("partner_transactions")->insertOne($item, ['continueOnError' => true, 'ordered' => false]);
            }
        } else {
            echo 'Не подходящих записей.';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo 'done';

        /*   echo '<script>setTimeout(function(){
        location.reload();
        },0);</script>';*/
        //  var_dump($result);
        exit;
    }

    public static function transactions2()
    {

        set_time_limit(0);
        $data = mDB::collection("transactions")->findOne([], [
            'sort' => ['id' => -1],
            'limit' => 1,
        ]);

        $last_id = $data->id ? $data->id : 0;

        $result = DB::select("*", "app_transactions", "id>$last_id", "", "20000");

        if (DB::getRecordCount($result) >= 1) {
            $input = [];
            while ($item = DB::getRow($result)) {

                $item['id'] = (int) $item['id'];
                $item['sum'] = (int) $item['sum'];
                $item['total'] = (int) $item['total'];
                $item['discount'] = (int) $item['discount'];
                $item['points'] = (int) $item['points'];
                $item['cost_price'] = (float) $item['cost_price'];
                $item['profit'] = (float) $item['profit'];
                $item['created'] = (int) $item['created'];
                $item['country'] = Request::$country;
                $input[] = $item;
            }

            mDB::collection("transactions")->insertMany($input, ['continueOnError' => true, 'ordered' => false]);
        } else {
            echo 'Не подходящих записей.';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo 'done';
        echo '<script>setTimeout(function(){
  location.reload();
},3000);</script>';
        //  var_dump($result);
        exit;
    }

    public static function transactions()
    {

        $start_time = time();

        set_time_limit(0);
        $result = mDB::collection("transactions")->findOne([
            'country' => Request::$country,
            'id' => ['$lt' => 7929454],
        ], [
            'sort' => ['id' => -1],
            'limit' => 1,
        ]);

        $last_id = $result ? $result->id : 0;

        $items_result = DB::select("*", "app_transactions", "id>$last_id AND created > 1577836800 AND id < 7929454", "", "8000");

        $items = [];
        $ids = [];
        while ($row = DB::getRow($items_result)) {
            $items[] = $row;
            $ids[] = $row['id'];
        }

        if (count($items) == 0) {
            echo 'all';
            exit;
        }

        $transaction_items_result = DB::select('*', 'app_transaction_items', "transaction IN (" . implode(',', $ids) . ")");
        $insert = [];

        $group_on_transactions = [];
        while ($val2 = DB::getRow($transaction_items_result)) {
            if (!isset($group_on_transactions[$val2['transaction']])) {
                $group_on_transactions[$val2['transaction']] = [];
            }

            $val2['id'] = (int) $val2['id'];
            $val2['count'] = (int) $val2['count'];
            $val2['price'] = (float) $val2['price'];
            $val2['transaction'] = (int) $val2['transaction'];
            $val2['points'] = (int) $val2['points'];
            $val2['time_discount'] = (int) $val2['time_discount'];
            $val2['discount'] = (int) $val2['discount'];
            $val2['weight'] = (float) $val2['weight'];
            $val2['product'] = (int) $val2['product'];
            $val2['total'] = (int) $val2['total'];
            $val2['cost_price'] = (float) $val2['cost_price'];
            $val2['profit'] = (float) $val2['profit'];

            $group_on_transactions[$val2['transaction']][] = $val2;
        }

        foreach ($items as $item) {

            $item['id'] = (int) $item['id'];
            $item['sum'] = (int) $item['sum'];
            $item['partner'] = (int) $item['partner'];
            $item['total'] = (int) $item['total'];
            $item['discount'] = (int) $item['discount'];
            $item['points'] = (int) $item['points'];
            $item['point'] = (int) $item['point'];
            $item['promotion'] = (int) $item['promotion'];
            $item['employee'] = (int) $item['employee'];
            $item['cost_price'] = (float) $item['cost_price'];
            $item['profit'] = (float) $item['profit'];
            $item['created'] = (int) $item['created'];
            $item['type'] = (int) $item['type'];
            $item['country'] = Request::$country;
            $item['items'] = [];

            if (isset($group_on_transactions[(int) $item['id']])) {
                $item['items'] = $group_on_transactions[(int) $item['id']];
            }

            $insert[] = $item;
        }

        echo ' Сбор данных ' . ((time() - $start_time) / 60) . ' сек. ';

        if (count($insert) > 0) {
            mDB::collection("transactions")->insertMany($insert, ['continueOnError' => true]);
        } else {

            echo 'all';
            exit;
        }

        echo 'done id - ' . $item['id'] . ' ' . ((7929446 - $item['id']) / 8000) . '  ' . ((time() - $start_time) / 60) . ' сек.';

        //echo json_encode($insert);

        header('Content-Type: text/html; charset=utf-8');
        echo 'done';
        echo '<script>setTimeout(function(){
            location.reload();
        },100);</script>';
        exit;
        //  var_dump($result);

    }

    public static function transactionsItems()
    {

        set_time_limit(0);
        $result = mDB::collection("transactions")->find([
            'country' => Request::$country,
            'itmes' => ['$exists' => false],
            'created' => ['$gte' => 1627776000],
            'country' => "ru",
            "partner" => "1",
            "point" => "372",
        ], [
            'limit' => 100,
            'sort' => [
                "_id" => -1,
            ],
        ])->toArray();

        $ids = [];
        foreach ($result as $val) {
            $ids[] = $val->id;
        }

        if (count($ids) == 0) {
            echo 'not ids';
            exit;
        }

        $transaction_items = DB::makeArray(DB::select('*', 'app_transaction_items', "transaction IN (" . implode(',', $ids) . ")"));

        foreach ($result as $item) {

            $items = [];
            foreach ($transaction_items as $val) {
                if ($item->id == $val['transaction']) {

                    $val['id'] = (int) $val['id'];
                    $val['count'] = (int) $val['count'];
                    $val['price'] = (float) $val['price'];
                    $val['total'] = (int) $val['total'];
                    $val['cost_price'] = (float) $val['cost_price'];
                    $val['profit'] = (float) $val['profit'];

                    $items[] = $val;
                }
            }

            mDB::collection('transactions')->updateOne([
                '_id' => $item->_id,
            ], [
                '$set' => [
                    "items" => $items,
                ],
            ]);
        }

        echo 'done';

        header('Content-Type: text/html; charset=utf-8');
        //echo 'done';
        echo '<script>setTimeout(function(){
    location.reload();
});</script>';
    }

    public static function new_transaction_items()
    {

        set_time_limit(0);
        $result = mDB::find("transaction_items", [], [
            'sort' => ['id' => -1],
            'limit' => 1,
        ]);

        $last_id = $result ? $result[0]['id'] : 0;

        $result = DB::select("*", "app_transaction_items", "id>$last_id", "", "10000");

        if (DB::getRecordCount($result) >= 1) {

            while ($item = DB::getRow($result)) {

                $item['id'] = (int) $item['id'];
                $item['count'] = (int) $item['count'];
                $item['transaction'] = (int) $item['transaction'];
                //    $item['name'] = utf8_decode($item['name']);
                //    $item['bulk'] = utf8_decode($item['bulk']);

                $item['total'] = (int) $item['total'];
                $item['price'] = (int) $item['price'];
                $item['cost_price'] = (float) $item['cost_price'];
                $item['profit'] = (float) $item['profit'];
                $item['time_discount'] = (int) $item['time_discount'];
                $item['discount'] = (int) $item['discount'];

                mDB::insertOne("transaction_items", $item, ['continueOnError' => true]);
            }
        } else {
            echo 'Не подходящих записей.';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo 'done';
        echo '<script>setTimeout(function(){
    location.reload();
},3000);</script>';
        //  var_dump($result);
        exit;
    }

    public static function new_transactions()
    {

        set_time_limit(0);
        $result = mDB::find("transactions", [], [
            'sort' => ['id' => -1],
            'limit' => 1,
        ]);

        $last_id = $result ? $result[0]['id'] : 0;

        $result = DB::select("*", "app_transactions", "id>$last_id", "", "10000");

        if (DB::getRecordCount($result) >= 1) {

            while ($item = DB::getRow($result)) {
                //echo $item['id'].'<br/>';
                $item['id'] = (int) $item['id'];
                $item['sum'] = (int) $item['sum'];
                $item['total'] = (int) $item['total'];
                $item['discount'] = (int) $item['discount'];
                $item['points'] = (int) $item['points'];
                $item['cost_price'] = (float) $item['cost_price'];
                $item['profit'] = (float) $item['profit'];
                $item['created'] = (int) $item['created'];

                mDB::insertOne("transactions", $item, ['continueOnError' => true]);
            }
        } else {
            echo 'Не подходящих записей.';
            exit;
        }

        // header('Content-Type: text/html; charset=utf-8');
        echo 'done';
        echo '<script>setTimeout(function(){
    location.reload();
},3000);</script>';
        //  var_dump($result);
        exit;
    }

    public static function controlSyncTransactionOnDay()
    {

        $start_time = time();

        $from = strtotime(date('Y-m-d', strtotime("-1 day")));

        set_time_limit(0);

        $ids = DB::getRow(DB::query("SELECT GROUP_CONCAT(tmp.id) as ids FROM (SELECT tr.id FROM `app_transactions` tr  LEFT JOIN `app_transaction_items` ti ON ti.`transaction`=tr.id WHERE tr.`created` > 1642465740 AND ti.id IS NULL GROUP BY tr.id) tmp"))['ids'];

        $items_result = DB::select("*", "app_transactions", "created > $from AND id IN (" . $ids . ")", "", "");

        $items = [];
        $ids = [];
        while ($row = DB::getRow($items_result)) {
            $duble = mDB::collection("transactions")->findOne([
                "id" => (int) $row["id"],
                "country" => Auth::$country,
            ]);
            if (!isset($duble->_id)) {
                $items[] = $row;
                $ids[] = $row['id'];
            }
        }

        if (count($items) == 0) {
            echo 'all';
            exit;
        }

        $transaction_items_result = DB::select('*', 'app_transaction_items', "transaction IN (" . implode(',', $ids) . ")");
        $insert = [];

        $group_on_transactions = [];
        while ($val2 = DB::getRow($transaction_items_result)) {
            if (!isset($group_on_transactions[$val2['transaction']])) {
                $group_on_transactions[$val2['transaction']] = [];
            }

            $val2['id'] = (int) $val2['id'];
            $val2['count'] = (int) $val2['count'];
            $val2['price'] = (float) $val2['price'];
            $val2['transaction'] = (int) $val2['transaction'];
            $val2['points'] = (int) $val2['points'];
            $val2['time_discount'] = (int) $val2['time_discount'];
            $val2['discount'] = (int) $val2['discount'];
            $val2['weight'] = (float) $val2['weight'];
            $val2['product'] = (int) $val2['product'];
            $val2['total'] = (int) $val2['total'];
            $val2['cost_price'] = (float) $val2['cost_price'];
            $val2['profit'] = (float) $val2['profit'];

            $group_on_transactions[$val2['transaction']][] = $val2;
        }

        foreach ($items as $item) {

            $item['id'] = (int) $item['id'];
            $item['sum'] = (int) $item['sum'];
            $item['sync-transaction'] = true;
            $item['partner'] = (int) $item['partner'];
            $item['total'] = (int) $item['total'];
            $item['discount'] = (int) $item['discount'];
            $item['points'] = (int) $item['points'];
            $item['point'] = (int) $item['point'];
            $item['promotion'] = (int) $item['promotion'];
            $item['employee'] = (int) $item['employee'];
            $item['cost_price'] = (float) $item['cost_price'];
            $item['profit'] = (float) $item['profit'];
            $item['created'] = (int) $item['created'];
            $item['type'] = (int) $item['type'];
            $item['country'] = Request::$country;
            $item['items'] = [];

            if (isset($group_on_transactions[(int) $item['id']])) {
                $item['items'] = $group_on_transactions[(int) $item['id']];
            }

            $insert[] = $item;
        }

        echo ' Сбор данных ' . ((time() - $start_time) / 60) . ' сек. ';

        if (count($insert) > 0) {
            mDB::collection("transactions")->insertMany($insert, ['continueOnError' => true]);
        } else {

            echo 'all';
            exit;
        }

        echo 'done id - ' . $item['id'] . ' ' . ((7929446 - $item['id']) / 8000) . '  ' . ((time() - $start_time) / 60) . ' сек.';

        //echo json_encode($insert);

        header('Content-Type: text/html; charset=utf-8');
        echo 'done';
        echo '<script>setTimeout(function(){
                location.reload();
            },100);</script>';
        exit;
        //  var_dump($result);

    }
}