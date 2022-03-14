<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';
require ROOT . 'api/classes/ProductionCostPriceClass.php';

$result = [];

if ($search = DB::escape($_REQUEST['search']))
    $search = ' AND (i.name LIKE "%' . $search . '%" OR ic.name LIKE "%' . $search . '%")';

$point = 60;

$products = DB::query('
    SELECT i.id, i.name, i.untils, i.bulk, i.partner
    FROM ' . DB_ITEMS . ' i
    LEFT JOIN ' . DB_PRODUCTIONS_COMPOSITION . ' pc ON pc.product = i.id
    LEFT JOIN ' . DB_ITEMS . ' ic ON ic.id = pc.item
    WHERE i.production = 1 AND (i.partner = 1 OR i.partner IS NULL)' . $search . '
    GROUP BY i.id
');

$pr_class = new ProductionCostPrice(false, 1, $point);

while ($row = DB::getRow($products)) {

    $bulk = number_format($row['bulk'], 3, ',', ' ');

    if ($row['untils'] == 'шт')
        $row['bulk'] = 1;

    $row['count'] = $row['bulk'];

    $row['items'] = $pr_class->subItems($row);

    $row['price'] = 0;

    $row['cost_price_calc'] = true;

    for ($j = 0; $j < sizeof($row['items']); $j++) {
        $row['price'] += $row['items'][$j]['count_price'];

        if ($row['items'][$j]['price'] == null)
            $row['cost_price_calc'] = false;
    }

    $editing_allowed = ($row['partner'] == null && !$userToken['admin']) ? false : true;

    if ($row['cost_price_calc']) {

        DB::update(array('price' => round($row['price'], 2)), DB_POINT_ITEMS, 'partner = 1 AND item = ' . $row['id']);
    }
    /* $result[] = array(  'id' => $row['id'],
                            'name' => $row['name'],
                            'my' => $editing_allowed,
                            'can_share' => ($userToken['admin'] && $row['partner'] != null) ? true : false,
                            'net_mass' => number_format($row['bulk'], 3, ',', ' ').' '.$row['untils'].($row['untils'] == 'шт' ? ' / '.$bulk.' кг' : ''),
                            'cost_price_calc' => $row['cost_price_calc'],
                            'cost_price' => number_format($row['price'], 2, ',', ' ').' ₽'); */
}

response('success', $result, 7);
