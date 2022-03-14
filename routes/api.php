<?php

//Быстрые методы  для получения списокв

use Controllers\AccessController;
use Controllers\AnalyticsController;
use Controllers\ConstraintController;
use Controllers\CwdssController;
use Controllers\DefProductionsController;
use Controllers\ExportController;
use Controllers\FileController;
use Controllers\ForecastController;
use Controllers\GraphConteroller;
use Controllers\OrderController;
use Controllers\ReportsController;
use Controllers\SelectController;
use Controllers\SyncController;
use Controllers\TerminalController;
use Support\Auth;
use Support\DB;
use Support\mDB;
use Support\Utils;

//Создать транзакцию через интеграцияю
//
$router->all('/api/integrations/transaction/create', function () {
    TerminalController::transaction();
});

//Получние чеков смены
//
$router->all('/api/integrations/transactions/get', function () {
    TerminalController::shift();
});

$router->all('migrate/sync/adduntils', function () {
    SyncController::syncAddUntilsInTechnicalCard();
});

$router->all('migrate/sync/categories', function () {
    SyncController::syncCategories();
});

$router->all('migrate/sync/technical_cards', function () {
    SyncController::syncTechnicalCards();
});

$router->all('migrate/sync/transactions', function () {
    SyncController::transactions();
});

//Отчет движения склады по точкам
$router->all('/gen/codes', function () {

    $data = [];
    for ($i = 0; $i < 50; $i++) {

        $code = rand(1111111111, 9999999999);
        if (DB::insert([
            'phone' => $code,
            'card_number' => $code,
            'group_id' => 1,
            'balance' => 500,
            'name' => "Маркетинг",
        ], 'app_clients')) {
            $data[] = $code;
        }
    }

    echo implode(',', $data);
    exit;
});

//GraphQL
$router->all('/graph', function () {

    GraphConteroller::init();
});

//Добавить тех. карту
$router->get('/playground', function () {

    require ROOT . 'playground.php';
});

$router->all('/sync/card', function () {
    echo Auth::$country;
    SyncController::ProductCategoryOnCards();

    exit;
});

//расчет эффективности

$router->all('api/statistics/pulse', function () {

    AnalyticsController::pulse();
});

$router->all('api/statistics/test', function () {

    AnalyticsController::addDataToTransaction();
});

$router->all('api/statistics/average_consumption', function () {

    AnalyticsController::average_consumption();
});

$router->all('api/statistics/popular', function () {

    AnalyticsController::popular();
});

$router->all('api/partner/statistics/hours', function () {

    AnalyticsController::hours();
});

$router->all('api/partner/statistics/weekdays', function () {

    AnalyticsController::weekdays();
});

$router->all('/api/select/partners', function () {
    SelectController::partners();
});

$router->all('/api/select/technical_cards', function () {
    SelectController::technicalCards();
});

$router->all('/api/select/product_categories', function () {
    SelectController::productCategories();
});

$router->all('/api/select/items_category', function () {
    SelectController::itemsCategory();
});

$router->all('/api/select/points', function () {
    SelectController::points();
});

$router->all('/api/select/removal_causes', function () {
    SelectController::removalCauses();
});

//Изменения статуса в детализации отчета кассового модуля
$router->all('/api/partner/orders/status/change', function () {
    OrderController::changeStatus();
});

//Ограничения на скидки

$router->mount('/api/constraint', function () use ($router) {

    $router->all('/get', function () {
        ConstraintController::get();
    });

    $router->all('/add', function () {
        Utils::setFeedLog("Создание ограничения по системе лояльности");
        ConstraintController::add();
    });

    $router->all('/update', function () {
        Utils::setFeedLog("Обновление ограничения по системе лояльности");
        ConstraintController::update();
    });

    $router->all('/delete', function () {
        Utils::setFeedLog("Удаление ограничения по системе лояльности");
        ConstraintController::delete();
    });
});

//Отложеное производство

//Создать производство
$router->all('/api/partner/deferred_productions/create', function () {
    DefProductionsController::create();
});

//Изменить производство
$router->all('/api/partner/deferred_productions/update', function () {
    DefProductionsController::update();
});

//Получить список производств
$router->all('/api/partner/deferred_productions/get', function () {
    DefProductionsController::get();
});
//Детали производства
$router->all('/api/partner/deferred_productions/details', function () {
    DefProductionsController::one();
});
//Удалить производство
$router->all('/api/partner/deferred_productions/delete', function () {
    DefProductionsController::delete();
});

//Остаток по складу расчет на сколько хватит
$router->all('/api/partner/forecast/items', function () {
    ForecastController::get();
});

//===Терминал

//Получение списка ограничения
$router->all('/api/partner/point/constraints', function () {
    TerminalController::constraints();;
});

$router->all('/api/partner/point/fiscal', function () {
    TerminalController::fiscal();;
});

//Промокод
$router->all('/api/terminal/promotion/code', function () {
    TerminalController::promotion();;
});

//Меню
$router->all('api/terminal/menu', function () {
    TerminalController::menu();
});

//Отчеты
/*
//Отчет движения склады по точкам
$router->all('/reports/transactions/points', function () {
ReportsController::trasactionPoints();
});

//Отчет движения склады по точкам
$router->all('/reports/transactions/points', function () {
ReportsController::trasactionPoints();
});

 */

//Методы для Антона
$router->all('/cwdss/menu/get', function () {
    CwdssController::pointMenu();
});

$router->all('/data/export', function () {
    ExportController::export();
});

$router->all('/reports/transactions/points', function () {
    ReportsController::trasactionPoints();
});

$router->all('/reports/transactions/items', function () {
    ReportsController::trasactionCategoryPoints();
});

$router->all('/cp/lists/point-items', function () {
    SelectController::pointItems();
});

//Доступы и должности

$router->all('/api/access/collecting', function () {
    AccessController::collecting();
});

//Загрузка файла

$router->all('/api/file/upload', function () {
    FileController::upload();
});

$router->all('/test/curl', function () {

    for ($i = 0; $i < 1000; $i++) {
        echo $i . ' ';
        mDB::connect();
    }
});

/*

$router->post('/flow/metrica', function () {
AnalyticsController::metrica();
});

$router->post('/flow/points', function () {
AnalyticsController::points();
});

$router->post('/flow/today', function () {
AnalyticsController::today();
});

$router->post('/production/get', function () {
ProductionController::get();
});

$router->get('/production/get', function () {
ProductionController::get();
});

 */