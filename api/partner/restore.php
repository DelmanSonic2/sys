<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';
require ROOT . 'api/classes/CalcWarehouse.php';

ini_set('max_execution_time', 60000);
ini_set('memory_limit', -1);

require ROOT . 'api/classes/TransactionClass.php';

function compareProducts($products, $point, $partner, $transaction)
{



    foreach ($products as $product) {

        $old_cost_price = round($product['old_cost_price'], 2);
        $new_cost_price = round($product['cost_price'], 2);

        if ($old_cost_price != $new_cost_price) {
            DB::query(
                '
                UPDATE ' . DB_TRANSACTION_ITEMS . '
                SET cost_price = ' . $product['cost_price'] . ', profit = total - cost_price
                WHERE id = ' . $product['trid']
            );
            DB::query('
                UPDATE ' . DB_TRANSACTIONS . '
                SET cost_price = cost_price - ' . $old_cost_price . ' + ' . $new_cost_price . ', profit = total - cost_price
                WHERE id = ' . $transaction . '
            ');
        }
    }
}

function comparePromotions($promotions, $point, $partner, $transaction)
{



    foreach ($promotions as $promotion) {

        $old_cost_price = round($promotion['old_cost_price'], 2);
        $new_cost_price = round($promotion['cost_price'], 2);

        if ($old_cost_price != $new_cost_price) {
            DB::query(
                '
                UPDATE ' . DB_TRANSACTION_ITEMS . '
                SET cost_price = ' . $promotion['cost_price'] . ', profit = total - cost_price
                WHERE id = ' . $promotion['trid']
            );
            DB::query('
                UPDATE ' . DB_TRANSACTIONS . '
                SET cost_price = cost_price - ' . $old_cost_price . ' + ' . $new_cost_price . ', profit = total - cost_price
                WHERE id = ' . $transaction . '
            ');
        }
    }
}

function getProducts($products, $partner, $point, $cw)
{



    if (!sizeof($products))
        return;

    for ($i = 0; $i < sizeof($products); $i++) {

        if (!$where)
            $where = 'pc.technical_card = ' . $products[$i]['id'];
        else
            $where .= ' OR pc.technical_card = ' . $products[$i]['id'];

        $products[$i]['cost_price'] = 0;
        $products[$i]['avg_price'] = 0;
        $products[$i]['calc'] = true;
    }

    $items = DB::query('SELECT pc.item AS id, pi.price, IF(pc.untils = "шт", pc.count, pc.gross) AS count, pc.technical_card, MIN(IFNULL(pi.price, 0)) AS calc,
                                    (SELECT AVG(pi2.price)
                                    FROM ' . DB_POINT_ITEMS . ' pi2
                                    WHERE pi2.item = pc.item AND pi2.partner = ' . $partner . '
                                    GROUP BY pi2.item) AS avg_price,
                                    (SELECT AVG(pi3.price)
                                    FROM ' . DB_POINT_ITEMS . ' pi3
                                    WHERE pi3.item = pc.item
                                    GROUP BY pi3.item) AS oth_avg_price
                                            FROM ' . DB_PRODUCT_COMPOSITION . ' pc
                                            LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = pc.item AND pi.point = ' . $point . '
                                            WHERE ' . $where . '
                                            GROUP BY pc.technical_card, pc.item');
    //DB::query('UNLOCK TABLES');

    while ($row = DB::getRow($items)) {

        //Если ингредиента нет на складе и никогда не было, то цена будет null
        if ($row['price'] == null)
            $row['price'] = 0;

        for ($i = 0; $i < sizeof($products); $i++) {
            //В чеке может быть разное количество одной и той же позиции
            if ($products[$i]['id'] == $row['technical_card']) {
                $count = $products[$i]['count'] * $row['count'];

                //Если на складе нет позиции, то невозможно рассчитать себестоимость
                if ($row['calc'] == 0 || $row['calc'] == null)
                    $products[$i]['calc'] = false;

                //Если известна средня себестоимость
                if ($row['avg_price'])
                    $products[$i]['avg_price'] += $count * $row['avg_price'];
                elseif ($row['oth_avg_price'])
                    $products[$i]['avg_price'] += $count * $row['oth_avg_price'];

                if (!$row['price'] && $row['avg_price'])
                    $row['price'] = $row['avg_price'];
                elseif (!$row['price'] && !$row['avg_price'] && $row['oth_avg_price'])
                    $row['price'] = $row['oth_avg_price'];

                //Рассчитываем себестоимость
                $products[$i]['cost_price'] += $count * $row['price'];

                $removal_item = array(
                    'id' => $row['id'],
                    'price' => $row['price'],
                    'count' => $count * -1
                );

                //Добавляем ингредиент в таблицу списаний, далее триггер сам произведет списание со склада
                $cw->AddItem($removal_item);
            }
        }
    }

    return $products;
}

function getPromotions($promotions, $partner, $point, $cw)
{



    for ($i = 0; $i < sizeof($promotions); $i++) {

        if (!$where)
            $where = 'pr.promotion = ' . $promotions[$i]['id'];
        else
            $where .= ' OR pr.promotion = ' . $promotions[$i]['id'];

        $promotions[$i]['cost_price'] = 0;
        $promotions[$i]['calc'] = false;
    }

    //DB::query('LOCK TABLES '.DB_POINT_ITEMS.' pi2 READ, '.DB_POINT_ITEMS.' pi3 READ, '.DB_PRODUCT_COMPOSITION.' pc READ, '.DB_POINT_ITEMS.' pi READ, '.DB_PROMOTION_TECHNICAL_CARDS.' pr READ, '.DB_TECHNICAL_CARD.' tc READ');
    //Получаем список ингредиентов, которые учавствуют в акции
    $items = DB::query('SELECT pc.item AS id, pi.price, (pr.count * IF(pc.untils = "шт", pc.count, pc.gross)) AS count, pr.promotion, MIN(IFNULL(pi.price, 0)) AS calc,
                                    (SELECT AVG(pi2.price)
                                    FROM ' . DB_POINT_ITEMS . ' pi2
                                    WHERE pi2.item = pc.item AND pi2.partner = ' . $partner . '
                                    GROUP BY pi2.item) AS avg_price,
                                    (SELECT AVG(pi3.price)
                                    FROM ' . DB_POINT_ITEMS . ' pi3
                                    WHERE pi3.item = pc.item
                                    GROUP BY pi3.item) AS oth_avg_price
                                            FROM ' . DB_PROMOTION_TECHNICAL_CARDS . ' pr
                                            JOIN ' . DB_TECHNICAL_CARD . ' AS tc ON pr.technical_card = tc.id
                                            JOIN ' . DB_PRODUCT_COMPOSITION . ' AS pc ON pc.technical_card = tc.id
                                            LEFT JOIN ' . DB_POINT_ITEMS . ' AS pi ON pi.item = pc.item AND pi.point = ' . $point . '
                                            WHERE ' . $where . '
                                            GROUP BY pc.technical_card, pc.item');
    //DB::query('UNLOCK TABLES');

    while ($row = DB::getRow($items)) {
        //Если ингредиента нет на складе и никогда не было, то цена будет null
        if ($row['price'] == null)
            $row['price'] = 0;

        for ($i = 0; $i < sizeof($promotions); $i++) {
            //В чеке может быть разное количество одной и той же акции
            if ($promotions[$i]['id'] == $row['promotion']) {

                $count = $row['count'] * $promotions[$i]['count'];

                //Если на складе нет позиции, то невозможно рассчитать себестоимость
                if ($row['calc'])
                    $promotions[$i]['calc'] = true;

                if (!$row['price'] && $row['avg_price'])
                    $row['price'] = $row['avg_price'];
                elseif (!$row['price'] && !$row['avg_price'] && $row['oth_avg_price'])
                    $row['price'] = $row['oth_avg_price'];

                //Себестоимость рассчитывается исходя из состава тех.карт, участвующих в акции
                $promotions[$i]['cost_price'] += $count * $row['price'];

                $removal_item = array(
                    'id' => $row['id'],
                    'price' => $row['price'],
                    'count' => $count * -1
                );

                //Добавляем ингредиент в таблицу списаний, далее триггер сам произведет списание со склада
                $cw->AddItem($removal_item);
            }
        }
    }

    return $promotions;
}

function productCostPrice($product, $cw)
{



    $composition = DB::query('   SELECT pc.id, pc.item, pc.count
                                        FROM ' . DB_PRODUCT_COMPOSITION . ' pc
                                        WHERE pc.technical_card = ' . $product['id']);

    $items_count = DB::getRecordCount($composition);

    while ($row = DB::getRow($composition)) {

        if (!$items_count || !$row['count'])
            continue;

        $item = array(
            'id' => $row['item'],
            'price' => $product['cost_price'] / $items_count / $product['count'] / $row['count'],
            'count' => $product['count'] * $row['count'] * -1
        );

        $cw->AddItem($item);
    }
}

function promotionCostPrice($promotion, $cw)
{



    $composition = DB::query('SELECT pc.item, (pc.count * ptc.count) AS count
                                    FROM ' . DB_PROMOTION_TECHNICAL_CARDS . ' ptc
                                    JOIN ' . DB_PRODUCT_COMPOSITION . ' pc ON pc.technical_card = ptc.technical_card
                                    WHERE ptc.promotion = ' . $promotion['id']);

    $items_count = DB::getRecordCount($composition);

    while ($row = DB::getRow($composition)) {

        if (!$items_count || !$row['count'])
            continue;

        $item = array(
            'id' => $row['item'],
            'price' => $promotion['cost_price'] / $items_count / $promotion['count'] / $row['count'],
            'count' => $promotion['count'] * $row['count'] * -1
        );

        $cw->AddItem($item);
    }
}

$result = [];

if ($check = DB::escape($_REQUEST['check'])) {

    $transactions = DB::query('
        SELECT COUNT(tr.id)
        FROM ' . DB_TRANSACTIONS . ' tr
        WHERE tr.id NOT IN (
            SELECT proccess_id
            FROM ' . DB_PARTNER_TRANSACTIONS . '
            WHERE proccess = 4
        )
    ');

    echo json_encode(DB::getRow($transactions));
    exit;
}

$transactions = DB::query('
    SELECT tr.id, tr.point, tr.partner, tr.created
    FROM ' . DB_TRANSACTIONS . ' tr
    WHERE tr.id NOT IN (
        SELECT proccess_id
        FROM ' . DB_PARTNER_TRANSACTIONS . '
        WHERE proccess = 4
    )
    ORDER BY id ASC
');

$counter = 0;

while ($row = DB::getRow($transactions)) {

    $promotions = [];
    $products = [];

    $calc_warehouse = new ItemsHistory(false, 4, $row['partner'], $row['point'], $row['created'], $row['id']);

    $query = DB::select('id AS trid, total, technical_card AS id, name, product, cost_price AS old_cost_price, price, count, type, promotion, promotion_name', DB_TRANSACTION_ITEMS, 'transaction = ' . $row['id'] . ' AND (technical_card IS NOT NULL OR promotion IS NOT NULL)');

    while ($item = DB::getRow($query)) {

        if ($item['type'])
            $promotions[] = array(
                'id' => $item['promotion'],
                'name' => $item['promotion_name'],
                'price' => $item['price'],
                'count' => $item['count'],
                'old_cost_price' => $item['old_cost_price'],
                'trid' => $item['trid'],
                'total' => $item['total']
            );
        else
            $products[] = $item;
    }

    if (sizeof($products)) $products = getProducts($products, $row['partner'], $row['point'], $calc_warehouse);
    if (sizeof($promotions)) $promotions = getPromotions($promotions, $row['partner'], $row['point'], $calc_warehouse);

    //==============================================================================================================================================================================================================================================
    //Если есть массив с акциями
    if (is_array($promotions)) {
        //Добавляем акции
        for ($i = 0; $i < sizeof($promotions); $i++) {

            $point = 0;

            if (!$promotions[$i]['calc']) {
                $promotions[$i]['cost_price'] = $promotions[$i]['total'] * 0.33;
                promotionCostPrice($promotions[$i], $calc_warehouse);
            }
        }
    }

    //Если есть массив с продуктами
    if (is_array($products)) {
        //Добавляем товары
        for ($i = 0; $i < sizeof($products); $i++) {

            //Если удалось рассчитать среднюю себестоимость, но не удалось рассчитать себестоимость продукта на складе
            if (!$products[$i]['calc'] && $products[$i]['avg_price'])
                $products[$i]['cost_price'] = $products[$i]['avg_price'];
            //Если не удалось расчитать себестоимость вовсе, то себестоимость по формуле
            elseif (!$products[$i]['calc']) {
                $products[$i]['cost_price'] = $products[$i]['total'] * 0.33;
                productCostPrice($products[$i], $calc_warehouse);
            }
        }
    }
    //==============================================================================================================================================================================================================================================

    compareProducts($products, $row['point'], $row['partner'], $row['id']);
    comparePromotions($promotions, $row['point'], $row['partner'], $row['id']);

    $calc_warehouse->GetPointBalance();

    $counter++;
}

response('success', array('message' => 'Итераций выполнено ' . $counter), 200);
