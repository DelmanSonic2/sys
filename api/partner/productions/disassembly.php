<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require_once ROOT.'api/classes/ProductionCostPriceClass.php';

if(!$product = DB::escape($_REQUEST['product']))
    response('error', 'Выберите производимую продукцию.', 422);

if(!$count = DB::escape($_REQUEST['count']))
    response('error', 'Укажите количество.', 422);

$product_data = DB::select('*', DB_ITEMS, 'id = '.$product.' AND production = 1 AND (partner = '.$userToken['id'].' OR partner IS NULL)');

if(!DB::getRecordCount($product_data))
    response('error', 'Продукция не найдена.', 422);

$product_data = DB::getRow($product_data);
$product_data['count'] = $count;

$class = new ProductionCostPrice(false, $userToken['id']);
$composition = $class->disassembly($product_data);

response('success', $composition, 200);