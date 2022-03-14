<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';

$res = [];

//Получаем список партнеров
$partners = DB::query('
    SELECT p.id, c.code
    FROM ' . DB_PARTNER . ' p
    JOIN ' . DB_CITIES . ' c ON c.id = p.city
');

while ($row = DB::getRow($partners)) {

    //Устанавливаем часовой пояс партнера
    date_default_timezone_set($row['code']);

    $date = date('Y-m-d');

    $technical_cards = DB::query("
        SELECT GROUP_CONCAT(DISTINCT pos.tech_card) AS technical_cards
        FROM " . DB_AUTO_PRICE_DOCUMENT . " d
        JOIN " . DB_AUTO_PRICE_POSITION . " pos ON pos.document = d.id
        WHERE d.status = 0 AND d.exec_date = '{$date}' AND d.partner = {$row['id']}
    ");
    $technical_cards = DB::getRow($technical_cards)['technical_cards'];

    if (is_null($technical_cards)) continue;

    DB::query("
        UPDATE " . DB_PRODUCT_PRICES . " pr 
        JOIN " . DB_AUTO_PRICE_POSITION . " pos ON pr.technical_card = pos.tech_card AND pr.point = pos.point
        JOIN " . DB_AUTO_PRICE_DOCUMENT . " d ON pos.document = d.id
        SET pr.price = pos.price, pr.hide = 0, d.status = 1
        WHERE d.status = 0 AND d.exec_date = '{$date}' AND d.partner = {$row['id']}
    ");

    DB::query("
        UPDATE " . DB_TECHNICAL_CARD . "
        SET different_price = 1
        WHERE id IN ({$technical_cards})
    ");
}

echo 'OK';
