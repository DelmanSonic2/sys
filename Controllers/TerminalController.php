<?php

namespace Controllers;

use Support\Auth;
use Support\DB;
use Support\mDB;
use Support\Request;
use Support\Transaction;
use Support\Utils;

//Методы для терминала бариста
class TerminalController
{

    public static function promotion()
    {

        $point = Request::authPoint();

        $where = [];

        if (!Request::has('code')) {
            Utils::response('error', 'Введите промокод.', 1);
        }

        $code = Request::$request['code'];

        $code_data = mDB::collection("promocodes")->findOne([
            "code" => (string) $code,
        ]);

        if (!isset($code_data->_id)) {
            Utils::response('error', 'Введен несуществующий промокод.', 1);
        }

        $weekdays_names = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

        //Получаем текущий день недели
        $weekday = date('N', time()) - 1;

        $weekdays = isset($code_data['weekdays']) ? $code_data['weekdays'] : [];

        if (count($weekdays) > 0) {
            $promotion_enable = false;
            for ($i = 0; $i < sizeof($weekdays); $i++) {
                if ($weekday == $weekdays[$i]) {
                    $promotion_enable = true;
                }

            }
            if (!$promotion_enable) {
                Utils::response('error', 'Промокод на сегодня отключен.', 1);
            }

        }

        $result = array(
            'code' => $code_data['code'],
            'percent' => $code_data['percent'],
            'coupon_amount' => 0, // $code_data['coupon_amount'],
            'technical_cards' => [],
            'promotions' => []
        );

        $code_categories = [];

        $category = isset($code_data->category) ? (array) $code_data->category : [];

        if (count($category) > 0) {
            $data_code_categories = mDB::collection("product_categories")->find([
                "_id" => ['$in' => $code_data->category],
                "country" => Request::$country,
            ]);

            foreach ($data_code_categories as $category) {
                $code_categories[] = $category->id;
            }

            if (count($code_categories) == 0) {
                Utils::response('error', 'Введен несуществующий промокод.', 1);
            }

        }

        if (count($code_categories) > 0) {

            $categories = [];

            $categories_data = DB::query('
                SELECT *
                FROM ' . DB_PRODUCT_CATEGORIES . '
                WHERE (partner = ' . $point['partner'] . ' OR partner IS NULL) AND FIND_IN_SET(id, (
                SELECT GROUP_CONCAT(Level SEPARATOR ",") FROM (
                    SELECT @Ids := (
                        SELECT GROUP_CONCAT(id SEPARATOR ",")
                        FROM ' . DB_PRODUCT_CATEGORIES . '
                        WHERE (partner = ' . $point['partner'] . ' OR partner IS NULL) AND (FIND_IN_SET(parent, @Ids) OR FIND_IN_SET(id, @Ids))
                    ) Level
                    FROM ' . DB_PRODUCT_CATEGORIES . '
                    JOIN (SELECT @Ids := "' . (implode(',', $code_categories)) . '") temp1
                ) temp2
                ))
            ');

            while ($row = DB::getRow($categories_data)) {
                $categories[] = $row['id'];
            }

            $where[] = 'FIND_IN_SET(p.category, "' . implode(',', $categories) . '")';
        }

        /*   if($code_data['products']) $where[] = 'FIND_IN_SET(tc.product, "'.$code_data['products'].'")';
        if($code_data['technical_cards']) $where[] = 'FIND_IN_SET(tc.id, "'.$code_data['technical_cards'].'")';*/

        $where = sizeof($where) ? ' AND (' . implode(' OR ', $where) . ')' : '';

        $data = DB::query(
            '
            SELECT tc.id, p.name, tc.subname, tc.bulk_value, tc.bulk_untils
            FROM ' . DB_PRODUCTS . ' p
            JOIN ' . DB_TECHNICAL_CARD . ' tc ON tc.product = p.id
            WHERE (tc.partner = ' . $point['partner'] . ' OR tc.partner IS NULL)' . $where
        );

        while ($row = DB::getRow($data)) {

            $name = ($row['subname'] == '') ? ($row['name'] . ' ' . $row['bulk_value'] . ' ' . $row['bulk_untils']) : ($row['name'] . ' (' . $row['subname'] . ') ' . $row['bulk_value'] . ' ' . $row['bulk_untils']);
            $result['technical_cards'][] = array(
                'id' => $row['id'],
                'name' => $name,
            );
        }

        Utils::response('success', $result, 7);
    }

    public static function fiscal()
    {
        $point = Request::authPoint();

        mDB::collection("fiscals")->insertOne([
            'point' => $point,
            'json_task' => Request::$request['json_task'],
            'result' => Request::$request['data'],
            "created" => time(),
        ]);

        Utils::response("success", [], 7);
    }

    public static function shift()
    {

        $point = Request::authPoint();

        $result = [];

        $shift = Request::$request['shift'];

        $from = strtotime(date('Y-m-d', time()));

        $result = mDB::collection("transactions")->find([
            "country" => Auth::$country,
            "shift" => $shift,
            "point" => (int) $point['id'],
            "created" => [
                '$gt' => $from,
            ],
        ])->toArray();

        Utils::response('success', $result, 7);
    }

    public static function menu()
    {

        if (isset(Request::$request['point'])) {
            $point = DB::getRow(DB::query("SELECT * FROM `app_partner_points` WHERE id=" . Request::$request['point'] . " LIMIT 1"));
        } else {
            $point = Request::authPoint();
        }

        $product_categories = mDB::collection("product_categories")->find([
            "points." . $point['id'] . '.enable' => true,
            "country" => Auth::$country,
            "archive" => ['$nin' => [(int) $point['partner']]],
            '$or' => [
                ['enableForAll' => true],
                ['partner' => (int) $point['partner']],
            ],
        ]);

        $categories = [];
        $ids_categories = [];
        foreach ($product_categories as $category) {
            $ids_categories[] = (int) $category->id;
            $categories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'parent' => $category->parent > 0 ? $category->parent : null,
                'image' => $category->image ? $category->image : PLACEHOLDER_IMAGE,
                'type' => 'category',
            ];
        }

        $categories[] = [
            "id" => -1,
            "name" => "Акции",
            "parent" => null,
            "image" => PLACEHOLDER_IMAGE,
            "type" => "category",
        ];

        $product_archive = DB::makeArray(DB::query("SELECT product_id FROM `app_archive` WHERE `partner_id`=" . $point['partner'] . " AND model='product'"));

        $product_archive_ids = [];
        foreach ($product_archive as $product_archive_id) {
            $product_archive_ids[] = (int) $product_archive_id['product_id'];
        }

        $technical_cards_filter = [
            //  "prices." . $point['id'] . '.hide' => false,
            "country" => Auth::$country,
            "product.category" => ['$in' => $ids_categories],
            "archive" => ['$nin' => [(int) $point['partner']]],
            '$or' => [
                ['enableForAll' => true],
                ['partner' => (int) $point['partner']],
            ],
        ];

        if (count($product_archive_ids) > 0) {
            $technical_cards_filter['product.id'] = ['$nin' => $product_archive_ids];
        }

        $technical_cards = mDB::collection("technical_cards")->find($technical_cards_filter);

        $products = [];

        foreach ($technical_cards as $card) {

            $hide = false;
            if (isset($card->prices[$point['id']])) {
                if ($card->prices[$point['id']]['hide'] == true) {
                    $hide = true;
                }

                if (isset($card->prices[$point['id']]['price'])) {
                    $card->price = $card->prices[$point['id']]['price'];
                }
            }

            if (!$hide) {
                if (!isset($products[$card->product->id])) {
                    $products[$card->product->id] = [
                        "id" => $card->product->id,
                        "name" => $card->product->name,
                        "parent" => $card->product->category > 0 ? $card->product->category : null,
                        "image" => $card->product->image ? $card->product->image : PLACEHOLDER_IMAGE,
                        "type" => "product",
                        "cards" => [],
                    ];
                }

                $products[$card->product->id]['cards'][] = [
                    "id" => $card->id,
                    "code" => $card->code,
                    "product" => $card->product->id,
                    "subname" => $card->subname,
                    "weighted" => $card->weighted,
                    "bulk_value" => $card->bulk_value,
                    "bulk_untils" => $card->bulk_untils,
                    "cashback_percent" => $card->cashback_percent,
                    "price" => $card->price,
                ];
            }
        }

        foreach ($products as &$product) {

            usort($product['cards'], function ($card1, $card2) {
                if ($card1['price'] == $card2['price']) {
                    return 0;
                }

                return ($card1['price'] < $card2['price']) ? -1 : 1;
            });
        }

        $promotions = DB::makeArray(DB::query('SELECT pr.id, pr.name, pr.description, pr.image, pr.price, pr.created
        FROM ' . DB_PROMOTIONS . ' pr
        WHERE partner = ' . $point['partner'] . ' AND FIND_IN_SET("' . $point['id'] . '", points) AND (SELECT COUNT(ptc.id) FROM ' . DB_PROMOTION_TECHNICAL_CARDS . ' ptc WHERE ptc.promotion = pr.id) > 0
        ORDER BY created DESC'));

        foreach ($promotions as &$promotion) {
            $promotion['id'] = (int) $promotion['id'];

            $promotion['price'] = (float) $promotion['price'];

            $promotion['enable'] = true;

            $promotion['parent'] = -1;
            $promotion['type'] = 'promotion';
            $promotion['products'] = DB::makeArray(DB::query("SELECT tc.id, ptc.count, tc.`subname`, pr.`name`, CONCAT(tc.`bulk_value`, tc.`bulk_untils`) as units FROM `app_promotion_technical_cards` ptc JOIN `app_technical_card` tc ON tc.id = ptc.`technical_card` JOIN `app_products` pr ON pr.id = tc.`product` WHERE ptc.`promotion`=" . $promotion['id']));

            unset($promotion['created']);

            if (!$promotion['image']) {
                $promotion['image'] = PLACEHOLDER_IMAGE;
            }

        }

        Utils::response("success", array_merge($categories, array_values($products), $promotions), 7);
    }

    public static function constraints()
    {

        $point = Request::authPoint();

        $constraints = DB::makeArray(DB::query("SELECT * FROM `app_constraints` WHERE FIND_IN_SET(" . $point['id'] . ", points)"));

        //     var_dump( $constraints);
        //   exit;
        $data = [];
        foreach ($constraints as $val) {
            $where = [];

            if ($val['technical_cards']) {
                $tmp = array_diff(explode(',', $val['technical_cards']), [""]);
                if (count($tmp) > 0) {
                    $where[] = " t.id IN (" . implode(',', $tmp) . ") ";
                }

            }

            if ($val['product_categories']) {
                $tmp = array_diff(explode(',', $val['product_categories']), [""]);
                if (count($tmp) > 0) {
                    $where[] = " c.id IN (" . implode(',', $tmp) . ") ";
                }

            }

            $cards = DB::makeArray(DB::query("SELECT  t.id FROM app_products p
           JOIN app_product_categories c ON p.category = c.id
           JOIN `app_technical_card` t ON t.product=p.id WHERE " . (implode(' OR ', $where)) . " "));

            foreach ($cards as $card) {
                $data[$card['id']][] = [
                    'type' => (int) $val['type'],
                    'from' => (int) $val['from_val'],
                    'to' => (int) $val['to_val'],
                ];
            }
        }

        Utils::response("success", $data, 7);
    }

    public static function transaction()
    {

        if (isset(Request::$request['point'])) {
            $point = DB::getRow(DB::query("SELECT * FROM `app_partner_points` WHERE id=" . Request::$request['point'] . " LIMIT 1"));
        } else {
            $point = Request::authPoint();
            //  Utils::response('error', array('msg' => 'Не достаточно параметров для операции.', 'data' => Request::$request), '2');
        }

        $tr_class = new Transaction(false, $point['id'], (int) $point['partner'], Request::$request);

        Utils::response("success", [], 7);
    }
}