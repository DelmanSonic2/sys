<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

$result = [];

$prices = DB::query('SELECT tc.product, tc.id AS technical_card, tc.price, p.id AS point, p.partner
                FROM ' . DB_TECHNICAL_CARD . ' tc
                JOIN ' . DB_PARTNER_POINTS . ' p');

while ($row = DB::getRow($prices)) {

    if (!$insert)
        $insert = '("' . $row['product'] . '", "' . $row['technical_card'] . '", "' . $row['price'] . '", "' . $row['point'] . '", "' . $row['partner'] . '")';
    else
        $insert .= ', ("' . $row['product'] . '", "' . $row['technical_card'] . '", "' . $row['price'] . '", "' . $row['point'] . '", "' . $row['partner'] . '")';
}

DB::delete(DB_PRODUCT_PRICES);

DB::query('INSERT INTO ' . DB_PRODUCT_PRICES . ' (product, technical_card, price, point, partner) VALUES ' . $insert);

response('success', 'Обновлено', 7);
