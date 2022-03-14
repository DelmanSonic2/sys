<?php

//Методы выравнивания данных

use Controllers\LevelerController;
use Controllers\SyncController;

$router->mount('/cron/leveler', function () use ($router) {

    $router->all('/partner_transactions/double', function () {
        LevelerController::removeDoubleInPartnerTranslation();
    });

    $router->all('/partner_transactions', function () {
        LevelerController::partner_transactions();
    });

    $router->all('/shifts', function () {
        LevelerController::shifts();
    });

    $router->all('/shifts_total', function () {
        LevelerController::reCalcShift();
    });

    $router->all('/income', function () {
        LevelerController::income();
    });
});

//Синхронизация partner_transactions
$router->all('/cron/sync/partner_transactions', function () {
    SyncController::partner_transactions();
});

//Изменение цен
$router->all('/cron/sync/auto_prices', function () {
    SyncController::autoChangePrices();
});

//Добавление данных в статистику
$router->all('/cron/sync/transactions/statistic', function () {
    SyncController::addDatatransactionForStatistic();
});

//Синхронизация возвратов
$router->all('/cron/sync/refund', function () {
    SyncController::refund();
});

//Синхронизатор транзакций
$router->all('/cron/sync/day_transactions', function () {
    SyncController::controlSyncTransactionOnDay();
});

//Синхронизатор транзакций
$router->all('/cron/sync/transactions', function () {
    SyncController::transactions();
});

//Синхронизатор позиций транзакций
$router->all('/cron/sync/transactions_items', function () {
    SyncController::transactionsItems();
});

/*
$router->get('/cron/transaction_items/sync', function () {
SyncController::new_transactions();
});

$router->get('/cron/transactions/sync', function () {
SyncController::new_transaction_items();
});
 */

$router->get('/cron/sync/partners', function () {
    SyncController::syncPartners();
});
