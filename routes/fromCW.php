<?php

use Support\Utils;

function includeWrapper($path, $action)
{


    require ROOT . $path;
}

//Авторизация
$router->all('/api/partner/auth', function () {
    includeWrapper('api/partner/auth.php', false);
});
//Добавить категорию
$router->all('/api/partner/menu/ingredients/categories/add', function () {
    Utils::setFeedLog("Добавить категорию");
    includeWrapper('api/partner/menu/ingredient_categories.php', 'add');
});
//Удалить категорию
$router->all('/api/partner/menu/ingredients/categories/delete', function () {
    Utils::setFeedLog("Удалить категорию");
    includeWrapper('api/partner/menu/ingredient_categories.php', 'delete');
});
//Редактировать категорию
$router->all('/api/partner/menu/ingredients/categories/edit', function () {
    Utils::setFeedLog("Редактировать категорию");
    includeWrapper('api/partner/menu/ingredient_categories.php', 'edit');
});
//Получить категории
$router->all('/api/partner/menu/ingredients/categories/get', function () {
    includeWrapper('api/partner/menu/ingredient_categories.php', 'get');
});
//Подробная информация о категории
$router->all('/api/partner/menu/ingredients/categories/info', function () {
    includeWrapper('api/partner/menu/ingredient_categories.php', 'info');
});
//Добавить ингредиент
$router->all('/api/partner/menu/ingredients/add', function () {
    Utils::setFeedLog("Добавить категорию");
    includeWrapper('api/partner/menu/ingredients.php', 'add');
});
//Редактировать ингредиент
$router->all('/api/partner/menu/ingredients/edit', function () {
    Utils::setFeedLog("Редактировать категорию");
    includeWrapper('api/partner/menu/ingredients.php', 'edit');
});
//Получение списка ингредиентов
$router->all('/api/partner/menu/ingredients/get', function () {
    includeWrapper('api/partner/menu/ingredients.php', 'get');
});
//Удалить ингредиент
$router->all('/api/partner/menu/ingredients/delete', function () {
    Utils::setFeedLog("Удалить ингредиент");
    includeWrapper('api/partner/menu/ingredients.php', 'delete');
});
//Получить подробную информацию о ингредиенте
$router->all('/api/partner/menu/ingredients/info', function () {
    includeWrapper('api/partner/menu/ingredients.php', 'info');
});
//Добавить точку
$router->all('/api/partner/menu/points/add', function () {
    Utils::setFeedLog("Добавить заведение");
    includeWrapper('api/partner/menu/points.php', 'add');
});
//Редактировать информацию
$router->all('/api/partner/menu/points/edit', function () {
    Utils::setFeedLog("Редактировать информацию заведения");
    includeWrapper('api/partner/menu/points.php', 'edit');
});
//Удалить точку
$router->all('/api/partner/menu/points/delete', function () {
    Utils::response("error", ['msg' => "Удалить запрещено"], 3);
    //   Utils::setFeedLog("Удалить заведение");
    //    includeWrapper('api/partner/menu/points.php', 'delete');
});
//Получить список точек
$router->all('/api/partner/menu/points/get', function () {
    includeWrapper('api/partner/menu/points.php', 'get');
});
//Подробная информация о точке
$router->all('/api/partner/menu/points/info', function () {
    includeWrapper('api/partner/menu/points.php', 'info');
});

//Получение списка ингредиентов для технической карты
$router->all('/api/partner/menu/technical_cards/items', function () {
    includeWrapper('api/partner/menu/technical_cards.php', 'items');
});

/*
//Добавить тех. карту
$router->all('/api/partner/menu/technical_cards/add', function () {
    Utils::setFeedLog("Создание тех-карты");
    includeWrapper('api/partner/menu/technical_cards.php', 'add');
});
//Валидация списка ингредиентов
$router->all('/api/partner/menu/technical_cards/validation', function () {
    includeWrapper('api/partner/menu/technical_cards.php', 'validation');
});

//Получение списка технических карт
$router->all('/api/partner/menu/technical_cards/get', function () {
    includeWrapper('api/partner/menu/technical_cards.php', 'get');
});
//Состав тех.карты
$router->all('/api/partner/menu/technical_cards/composition', function () {
    includeWrapper('api/partner/menu/technical_cards.php', 'composition');
});
//Получение подробной информации
$router->all('/api/partner/menu/technical_cards/info', function () {
    includeWrapper('api/partner/menu/technical_cards.php', 'info');
});
//Удалить тех. карту
$router->all('/api/partner/menu/technical_cards/delete', function () {
    Utils::setFeedLog("Удалить тех-карту");
    includeWrapper('api/partner/menu/technical_cards.php', 'delete');
});
//Редактирование тех. карты
$router->all('/api/partner/menu/technical_cards/edit', function () {
    Utils::setFeedLog("Редактировать тех-карту");
    includeWrapper('api/partner/menu/technical_cards.php', 'edit');
});
//Изменить описание состава
$router->all('/api/partner/menu/technical_cards/composition_description', function () {
    Utils::setFeedLog("Изменить описание состава");
    includeWrapper('api/partner/menu/technical_cards.php', 'composition_description');
});*/


//Получить таблицу с ценами в категории
$router->all('/api/partner/menu/technical_cards/table', function () {
    includeWrapper('api/partner/menu/product_categories.php', 'table');
});
//Изменить цены товаров
$router->all('/api/partner/menu/technical_cards/prices', function () {
    Utils::setFeedLog("Изменить цены товаров");
    includeWrapper('api/partner/menu/product_categories.php', 'prices');
});

//Добавить категорию
$router->all('/api/partner/menu/products/categories/add', function () {
    Utils::setFeedLog("Добавить категорию");
    includeWrapper('api/partner/menu/product_categories.php', 'add');
});
//Получить список категорий
$router->all('/api/partner/menu/products/categories/get', function () {
    includeWrapper('api/partner/menu/product_categories.php', 'get');
});
//Редактирование категории
$router->all('/api/partner/menu/products/categories/edit', function () {
    Utils::setFeedLog("Редактирование категорию");
    includeWrapper('api/partner/menu/product_categories.php', 'edit');
});
//Удалить категорию
$router->all('/api/partner/menu/products/categories/delete', function () {
    Utils::setFeedLog("Удалить категорию");
    includeWrapper('api/partner/menu/product_categories.php', 'delete');
});
//Получить подробную информацию о категории
$router->all('/api/partner/menu/products/categories/info', function () {
    includeWrapper('api/partner/menu/product_categories.php', 'info');
});
//Добавить товар
$router->all('/api/partner/menu/products/add', function () {
    Utils::setFeedLog("Создание товара");
    includeWrapper('api/partner/menu/products.php', 'add');
});
//Получить товары
$router->all('/api/partner/menu/products/get', function () {
    includeWrapper('api/partner/menu/products.php', 'get');
});
//Редактировать товар
$router->all('/api/partner/menu/products/edit', function () {
    Utils::setFeedLog("Редактирование товара");
    includeWrapper('api/partner/menu/products.php', 'edit');
});
//Удалить товар
$router->all('/api/partner/menu/products/delete', function () {
    Utils::setFeedLog("Удаление товара");
    includeWrapper('api/partner/menu/products.php', 'delete');
});
//Подробная информация о товаре
$router->all('/api/partner/menu/products/info', function () {
    includeWrapper('api/partner/menu/products.php', 'info');
});
//Создать
$router->all('/api/partner/menu/auto_price_change/add', function () {
    Utils::setFeedLog("Создание записи отложенного изменения цены");
    includeWrapper('api/partner/menu/auto_price_change.php', 'add');
});
//Получить список
$router->all('/api/partner/menu/auto_price_change/get', function () {
    includeWrapper('api/partner/menu/auto_price_change.php', 'get');
});
//Получить конкретный документ
$router->all('/api/partner/menu/auto_price_change/info', function () {
    includeWrapper('api/partner/menu/auto_price_change.php', 'info');
});
//Редактировать
$router->all('/api/partner/menu/auto_price_change/edit', function () {
    Utils::setFeedLog("Редактирование записи отложенного изменения цены");
    includeWrapper('api/partner/menu/auto_price_change.php', 'edit');
});
//Удалить
$router->all('/api/partner/menu/auto_price_change/delete', function () {
    Utils::setFeedLog("Удаление записи отложенного изменения цены");
    includeWrapper('api/partner/menu/auto_price_change.php', 'delete');
});
//Добавить в архив
$router->all('/api/partner/menu/add_archive', function () {
    Utils::setFeedLog("Архивирование товара/ингредиента");
    includeWrapper('api/partner/menu/archive.php', 'create');
});
//Проверка ингредиента перед архивацией
$router->all('/api/partner/menu/check', function () {
    includeWrapper('api/partner/menu/archive.php', 'check');
});
//Восстановить из архива
$router->all('/api/partner/menu/recovery_archive', function () {
    Utils::setFeedLog("Восстановить из архива товара/ингредиента");
    includeWrapper('api/partner/menu/archive.php', 'recovery');
});
//Создать поставку
$router->all('/api/partner/warehouse/supplies/create', function () {
    Utils::setFeedLog("Создание поставки");
    includeWrapper('api/partner/warehouse/supplies.php', 'create');
});
//Получить список перемещений
$router->all('/api/partner/warehouse/supplies/moving', function () {
    includeWrapper('api/partner/warehouse/supplies.php', 'moving');
});
//Получение поставок
$router->all('/api/partner/warehouse/supplies/get', function () {
    includeWrapper('api/partner/warehouse/supplies.php', 'get');
});
//Редактирование поставки или перемещения
$router->all('/api/partner/warehouse/supplies/edit', function () {
    Utils::setFeedLog("Редактирование поставки или перемещения");
    includeWrapper('api/partner/warehouse/supplies.php', 'edit');
});
//Детали
$router->all('/api/partner/warehouse/supplies/details', function () {
    includeWrapper('api/partner/warehouse/supplies.php', 'details');
});
//Получение полной информации о поставке
$router->all('/api/partner/warehouse/supplies/info', function () {
    includeWrapper('api/partner/warehouse/supplies.php', 'info');
});
//Подтверждение поставки
$router->all('/api/partner/warehouse/supplies/confirm', function () {
    includeWrapper('api/partner/warehouse/supplies.php', 'confirm');
});
//Товары доступные для перемещения
$router->all('/api/partner/warehouse/supplies/items', function () {
    includeWrapper('api/partner/warehouse/supplies.php', 'items');
});
//Удалить поставку
$router->all('/api/partner/warehouse/supplies/delete', function () {
    Utils::setFeedLog("Удаление поставки или перемещения");
    includeWrapper('api/partner/warehouse/supplies.php', 'delete');
});
//Добавить поставщика
$router->all('/api/partner/warehouse/suppliers/add', function () {
    Utils::setFeedLog("Добавие поставщика");
    includeWrapper('api/partner/warehouse/suppliers.php', 'add');
});
//Изменить информацию о поставщике
$router->all('/api/partner/warehouse/suppliers/edit', function () {
    includeWrapper('api/partner/warehouse/suppliers.php', 'edit');
});
//Удалить пользователя
$router->all('/api/partner/warehouse/suppliers/delete', function () {
    Utils::setFeedLog("Удаление поставщика");
    includeWrapper('api/partner/warehouse/suppliers.php', 'delete');
});
//Получить список
$router->all('/api/partner/warehouse/suppliers/get', function () {
    includeWrapper('api/partner/warehouse/suppliers.php', 'get');
});
//Получение информации о поставщике
$router->all('/api/partner/warehouse/suppliers/info', function () {
    includeWrapper('api/partner/warehouse/suppliers.php', 'info');
});
//Остатки
$router->all('/api/partner/warehouse/info/balance', function () {
    includeWrapper('api/partner/warehouse/info.php', 'balance');
});
//Отчет по движениям
$router->all('/api/partner/warehouse/info/transactions', function () {
    includeWrapper('api/partner/warehouse/info.php', 'transactions');
});
//Поставки
$router->all('/api/partner/warehouse/info/supplies', function () {
    includeWrapper('api/partner/warehouse/info.php', 'supplies');
});
//Создать списание
$router->all('/api/partner/warehouse/removals/create', function () {
    Utils::setFeedLog("Создание списания");
    includeWrapper('api/partner/warehouse/removal.php', 'create');
});
//Получить списания
$router->all('/api/partner/warehouse/removals/get', function () {
    includeWrapper('api/partner/warehouse/removal.php', 'get');
});
//Причины списания
$router->all('/api/partner/warehouse/removals/causes', function () {
    includeWrapper('api/partner/warehouse/removal.php', 'causes');
});
//Доступные товары для списания
$router->all('/api/partner/warehouse/removals/items', function () {
    includeWrapper('api/partner/warehouse/removal.php', 'items');
});
//Детали списания
$router->all('/api/partner/warehouse/removals/details', function () {
    includeWrapper('api/partner/warehouse/removal.php', 'details');
});
//Подробная информация о списании
$router->all('/api/partner/warehouse/removals/info', function () {
    includeWrapper('api/partner/warehouse/removal.php', 'info');
});
//Удаление списания
$router->all('/api/partner/warehouse/removals/delete', function () {
    Utils::setFeedLog("Удаление списания");
    includeWrapper('api/partner/warehouse/removal.php', 'delete');
});
//Редактирование списания
$router->all('/api/partner/warehouse/removals/edit', function () {
    Utils::setFeedLog("Редактирование списания");
    includeWrapper('api/partner/warehouse/removal.php', 'edit');
});
//Получить
$router->all('/api/partner/warehouse/report/get', function () {
    includeWrapper('api/partner/warehouse/report_moving.php', 'get');
});
//Расходы
$router->all('/api/partner/warehouse/report/costs', function () {
    includeWrapper('api/partner/warehouse/report_moving.php', 'costs');
});
//Поступления
$router->all('/api/partner/warehouse/report/receipts', function () {
    includeWrapper('api/partner/warehouse/report_moving.php', 'receipts');
});
//Детализация
$router->all('/api/partner/warehouse/report/details', function () {
    includeWrapper('api/partner/warehouse/report_moving.php', 'details');
});
//Получить список
$router->all('/api/partner/warehouse/inventory/get', function () {
    includeWrapper('api/partner/warehouse/inventory.php', 'get');
});
//Получение информации об инвентаризации
$router->all('/api/partner/warehouse/inventory/info', function () {
    includeWrapper('api/partner/warehouse/inventory.php', 'info');
});
//Обновить строку
$router->all('/api/partner/warehouse/inventory/update', function () {
    includeWrapper('api/partner/warehouse/inventory.php', 'update');
});
//Выполнить инвентаризацию
$router->all('/api/partner/warehouse/inventory/execute', function () {
    Utils::setFeedLog("Выполнение инвентаризации");
    includeWrapper('api/partner/warehouse/inventory.php', 'execute');
});
//Изменить дату
$router->all('/api/partner/warehouse/inventory/date', function () {
    Utils::setFeedLog("Измение дату инвентаризации");
    includeWrapper('api/partner/warehouse/inventory.php', 'date');
});
//Починить
$router->all('/api/partner/warehouse/inventory/repair', function () {
    includeWrapper('api/partner/warehouse/inventory.php', 'repair');
});
//Детализация
$router->all('/api/partner/warehouse/inventory/details', function () {
    includeWrapper('api/partner/warehouse/inventory.php', 'details');
});
//Создать должность
$router->all('/api/partner/accesses/positions/create', function () {
    Utils::setFeedLog("Создание должности");
    includeWrapper('api/partner/accesses/positions.php', 'create');
});
//Получить список должностей
$router->all('/api/partner/accesses/positions/get', function () {
    includeWrapper('api/partner/accesses/positions.php', 'get');
});
//Удалить должность
$router->all('/api/partner/accesses/positions/delete', function () {
    Utils::setFeedLog("Удаление должности");
    includeWrapper('api/partner/accesses/positions.php', 'delete');
});
//Редактирование должности
$router->all('/api/partner/accesses/positions/edit', function () {
    Utils::setFeedLog("Редактирование должности");
    includeWrapper('api/partner/accesses/positions.php', 'edit');
});
//Получение подробной информации
$router->all('/api/partner/accesses/positions/info', function () {
    includeWrapper('api/partner/accesses/positions.php', 'info');
});
//Добавить сотрудника
$router->all('/api/partner/accesses/employees/add', function () {
    Utils::setFeedLog("Добавление сотрудника");
    includeWrapper('api/partner/accesses/employees.php', 'add');
});
//Получить список сотрудников
$router->all('/api/partner/accesses/employees/get', function () {
    includeWrapper('api/partner/accesses/employees.php', 'get');
});
//Удалить сотрудника
$router->all('/api/partner/accesses/employees/delete', function () {
    Utils::setFeedLog("Удаление сотрудника");
    includeWrapper('api/partner/accesses/employees.php', 'delete');
});
//Редактирование информации о сотруднике
$router->all('/api/partner/accesses/employees/edit', function () {
    Utils::setFeedLog("Редатирование информацию о сотруднике");
    includeWrapper('api/partner/accesses/employees.php', 'edit');
});
//Получение подробной информации
$router->all('/api/partner/accesses/employees/info', function () {
    includeWrapper('api/partner/accesses/employees.php', 'info');
});
//ABC анализ
$router->all('/api/partner/statistics/analysis/abc', function () {
    includeWrapper('api/partner/statistics/analysis.php', 'abc');
});
//XYZ анализ
$router->all('/api/partner/statistics/analysis/xyz', function () {
    includeWrapper('api/partner/statistics/analysis.php', 'xyz');
});
//Получить список
$router->all('/api/partner/statistics/categories/get', function () {
    includeWrapper('api/partner/statistics/categories.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/products/get', function () {
    includeWrapper('api/partner/statistics/products.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/clients/get', function () {
    includeWrapper('api/partner/statistics/clients.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/employees/get', function () {
    includeWrapper('api/partner/statistics/employees.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/checks/get', function () {
    includeWrapper('api/partner/statistics/checks.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/payment/get', function () {
    includeWrapper('api/partner/statistics/payment.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/points/get', function () {
    includeWrapper('api/partner/statistics/points.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/promotions/get', function () {
    includeWrapper('api/partner/statistics/promotions.php', 'get');
});
//Получить список
$router->all('/api/partner/statistics/items/get', function () {
    includeWrapper('api/partner/statistics/items.php', 'get');
});
//Получить
$router->all('/api/partner/statistics/removal-report/get', function () {
    includeWrapper('api/partner/statistics/removal_report.php', 'get');
});
//Заведения
$router->all('/api/partner/statistics/top/points', function () {
    includeWrapper('api/partner/statistics/top.php', 'points');
});
//Сотрудники
$router->all('/api/partner/statistics/top/employees', function () {
    includeWrapper('api/partner/statistics/top.php', 'employees');
});
//Общая информация
$router->all('/api/partner/statistics/top/total', function () {
    includeWrapper('api/partner/statistics/top.php', 'total');
});
//По дням недели
$router->all('/api/partner/statistics/weekdays-old', function () {
    includeWrapper('api/partner/statistics/charts.php', 'weekdays');
});
//Популярные товары
$router->all('/api/partner/statistics/popular', function () {
    includeWrapper('api/partner/statistics/charts.php', 'popular');
});
//Графики
$router->all('/api/partner/statistics/chart', function () {
    includeWrapper('api/partner/statistics/charts.php', 'chart');
});
//По часам
$router->all('/api/partner/statistics/hours-old', function () {
    includeWrapper('api/partner/statistics/charts.php', 'hours');
});
//Верхняя панель
//Устарело
$router->all('/api/partner/statistics/top-panel', function () {
    includeWrapper('api/partner/statistics/top-panel.php', 'get');
});
//Добавить категорию
$router->all('/api/partner/finances/categories/add', function () {
    includeWrapper('api/partner/finances/categories.php', 'add');
});
//Получить список категорий
$router->all('/api/partner/finances/categories/get', function () {
    includeWrapper('api/partner/finances/categories.php', 'get');
});
//Редактирование категории
$router->all('/api/partner/finances/categories/edit', function () {
    includeWrapper('api/partner/finances/categories.php', 'edit');
});
//Удалить категорию
$router->all('/api/partner/finances/categories/delete', function () {
    includeWrapper('api/partner/finances/categories.php', 'delete');
});
//Получить подробную информацию о категории
$router->all('/api/partner/finances/categories/info', function () {
    includeWrapper('api/partner/finances/categories.php', 'info');
});
//Создать транзакцию
$router->all('/api/partner/finances/transactions/create', function () {
    includeWrapper('api/partner/finances/transactions.php', 'create');
});
//Получить список транзакций
$router->all('/api/partner/finances/transactions/get', function () {
    includeWrapper('api/partner/finances/transactions.php', 'get');
});
//Получение подробной информации о транзакции
$router->all('/api/partner/finances/transactions/info', function () {
    includeWrapper('api/partner/finances/transactions.php', 'info');
});
//Удалить
$router->all('/api/partner/finances/transactions/delete', function () {
    includeWrapper('api/partner/finances/transactions.php', 'delete');
});
//Редактировать
$router->all('/api/partner/finances/transactions/edit', function () {
    includeWrapper('api/partner/finances/transactions.php', 'edit');
});
//Получить отчет
$router->all('/api/partner/finances/reports/get', function () {
    includeWrapper('api/partner/finances/reports.php', 'get');
});
//Получить список
$router->all('/api/partner/finances/salary/get', function () {
    includeWrapper('api/partner/finances/salary.php', 'get');
});
//Получить список смен
$router->all('/api/partner/finances/shifts/get', function () {
    includeWrapper('api/partner/finances/shifts.php', 'get');
});
//Получить список
$router->all('/api/partner/marketing/plan/get', function () {
    includeWrapper('api/partner/marketing/plan.php', 'get');
});
//Добавить позицию
$router->all('/api/partner/marketing/plan/add', function () {
    includeWrapper('api/partner/marketing/plan.php', 'add');
});
//Удалить позицию
$router->all('/api/partner/marketing/plan/delete', function () {
    includeWrapper('api/partner/marketing/plan.php', 'delete');
});
//Редактировать позицию
$router->all('/api/partner/marketing/plan/edit', function () {
    includeWrapper('api/partner/marketing/plan.php', 'edit');
});
//Создать акцию
$router->all('/api/partner/marketing/promotions/create', function () {
    includeWrapper('api/partner/marketing/promotions.php', 'create');
});
//Получить список
$router->all('/api/partner/marketing/promotions/get', function () {
    includeWrapper('api/partner/marketing/promotions.php', 'get');
});
//Получить список продуктов
$router->all('/api/partner/marketing/promotions/products', function () {
    includeWrapper('api/partner/marketing/promotions.php', 'products');
});
//Получить подробную информацию
$router->all('/api/partner/marketing/promotions/info', function () {
    includeWrapper('api/partner/marketing/promotions.php', 'info');
});
//Редактирование акции
$router->all('/api/partner/marketing/promotions/edit', function () {
    includeWrapper('api/partner/marketing/promotions.php', 'edit');
});
//Удалить
$router->all('/api/partner/marketing/promotions/delete', function () {
    includeWrapper('api/partner/marketing/promotions.php', 'delete');
});
//Получение списка
$router->all('/api/partner/marketing/clients/get', function () {
    includeWrapper('api/partner/marketing/clients.php', 'get');
});
//Заявки на возврат
$router->all('/api/partner/marketing/refunds/requests', function () {
    includeWrapper('api/partner/marketing/refunds.php', 'requests');
});
//Принять
$router->all('/api/partner/marketing/refunds/accept', function () {
    includeWrapper('api/partner/marketing/refunds.php', 'accept');
});
//Отклонить
$router->all('/api/partner/marketing/refunds/reject', function () {
    includeWrapper('api/partner/marketing/refunds.php', 'reject');
});
//Добавить
$router->all('/api/partner/marketing/time-discount/add', function () {
    includeWrapper('api/partner/marketing/time_discount.php', 'add');
});
//Получить список
$router->all('/api/partner/marketing/time-discount/get', function () {
    includeWrapper('api/partner/marketing/time_discount.php', 'get');
});
//Получить подробную информацию
$router->all('/api/partner/marketing/time-discount/info', function () {
    includeWrapper('api/partner/marketing/time_discount.php', 'info');
});
//Редактировать
$router->all('/api/partner/marketing/time-discount/edit', function () {
    includeWrapper('api/partner/marketing/time_discount.php', 'edit');
});
//Изменить пароль
$router->all('/api/partner/password/change', function () {
    includeWrapper('api/partner/password.php', 'change');
});
//Закрытие смен
$router->all('/api/partner/cron/shift_close', function () {
    includeWrapper('api/partner/cron/shift_close.php', false);
});
//Атоизменение цен
$router->all('/api/partner/cron/auto_price_change', function () {
    includeWrapper('api/partner/cron/auto_price.php', false);
});
//Доступы
$router->all('/api/partner/profile/accesses', function () {
    includeWrapper('api/partner/profile.php', 'accesses');
});
//Создать новый продукт
$router->all('/api/partner/productions/products/create', function () {
    includeWrapper('api/partner/productions/products.php', 'create');
});
//Получить список
$router->all('/api/partner/productions/products/get', function () {
    includeWrapper('api/partner/productions/products.php', 'get');
});
//Валидация состава производимой продукции
$router->all('/api/partner/productions/products/validate', function () {
    includeWrapper('api/partner/productions/products.php', 'validate');
});
//Информация по производимой продукции
$router->all('/api/partner/productions/products/info', function () {
    includeWrapper('api/partner/productions/products.php', 'info');
});
//Редактирование производимой продукции
$router->all('/api/partner/productions/products/edit', function () {
    includeWrapper('api/partner/productions/products.php', 'edit');
});
//Удалить продукцию
$router->all('/api/partner/productions/products/delete', function () {
    includeWrapper('api/partner/productions/products.php', 'delete');
});
//Получить состав
$router->all('/api/partner/productions/products/composition', function () {
    includeWrapper('api/partner/productions/products.php', 'composition');
});
//Починить нетто
$router->all('/api/partner/productions/products/repair', function () {
    includeWrapper('api/partner/productions/products.php', 'repair');
});
//Изменить описание состава
$router->all('/api/partner/productions/products/composition_description', function () {
    includeWrapper('api/partner/productions/products.php', 'composition_description');
});
//Создать производство
$router->all('/api/partner/productions/productions/create', function () {
    includeWrapper('api/partner/productions/productions.php', 'create');
});
//Доступные для производства продукты
$router->all('/api/partner/productions/productions/products', function () {
    includeWrapper('api/partner/productions/productions.php', 'products');
});
//Получить список производств
$router->all('/api/partner/productions/productions/get', function () {
    includeWrapper('api/partner/productions/productions.php', 'get');
});
//Детали производства
$router->all('/api/partner/productions/productions/details', function () {
    includeWrapper('api/partner/productions/productions.php', 'details');
});
//Удалить производство
$router->all('/api/partner/productions/productions/delete', function () {
    includeWrapper('api/partner/productions/productions.php', 'delete');
});
//Подробная информация
$router->all('/api/partner/productions/productions/info', function () {
    includeWrapper('api/partner/productions/productions.php', 'info');
});
//Редактирование производства
$router->all('/api/partner/productions/productions/edit', function () {
    includeWrapper('api/partner/productions/productions.php', 'edit');
});
//Починить
$router->all('/api/partner/productions/productions/repair', function () {
    includeWrapper('api/partner/productions/productions.php', 'repair');
});
//Разборка
$router->all('/api/partner/productions/disassembly', function () {
    includeWrapper('api/partner/productions/disassembly.php', false);
});
//Отчет по движению
$router->all('/api/partner/productions/report-moving', function () {
    includeWrapper('api/partner/productions/report_moving.php', false);
});
//Установить статус документа
$router->all('/api/partner/common/status', function () {
    includeWrapper('api/partner/common/status.php', 'set');
});
//restore
$router->all('/api/partner/restore', function () {
    includeWrapper('api/partner/restore.php', false);
});
//Показать приходные ордера
$router->all('/api/partner/orders/get_income', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'get_income');
});
//Показать расходные ордера
$router->all('/api/partner/orders/get_spending', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'get_spending');
});
//Добавить расходный ордер
$router->all('/api/partner/orders/add_spending', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'add_spending');
});
//Изменить расходный ордер
$router->all('/api/partner/orders/edit_spending', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'edit_spending');
});
//Показать остаток
$router->all('/api/partner/orders/remain', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'remain');
});
//Сформировать отчет
$router->all('/api/partner/orders/report', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'report');
});
//Показать незакрытые поставки
$router->all('/api/partner/orders/get_open_supplies', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'get_open_supplies');
});
//Показать услуги
$router->all('/api/partner/orders/get_services', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'get_services');
});
//Удалить расходный ордер
$router->all('/api/partner/orders/delete_spending', function () {
    includeWrapper('api/partner/orders/orderModule.php', 'delete_spending');
});
//Показать приходные ордера
$router->all('/api/partner/orders/admin/get_income', function () {
    includeWrapper('api/partner/orders/orderAdminModule.php', 'get_income');
});
//Показать расходные ордера
$router->all('/api/partner/orders/admin/get_spending', function () {
    includeWrapper('api/partner/orders/orderAdminModule.php', 'get_spending');
});
//Показать остаток
$router->all('/api/partner/orders/admin/remain', function () {
    includeWrapper('api/partner/orders/orderAdminModule.php', 'remain');
});
//Сводный отчет
$router->all('/api/partner/orders/admin/report', function () {
    includeWrapper('api/partner/orders/orderAdminModule.php', 'report');
});
//Установить лимит
$router->all('/api/partner/orders/admin/set_limit', function () {
    includeWrapper('api/partner/orders/orderAdminModule.php', 'set_limit');
});
//Отчет о затратах
$router->all('/api/partner/orders/admin/expense_report', function () {
    includeWrapper('api/partner/orders/orderAdminModule.php', 'expense_report');
});
//Детали отчета о затратах
$router->all('/api/partner/orders/admin/expense-report-details', function () {
    includeWrapper('api/partner/orders/orderAdminModule.php', 'expense_report_details');
});

//Получить цвета
$router->all('/api/additional/colors', function () {
    includeWrapper('api/lib/tables.php', 'colors');
});
//Получить информацию об обновлении системы
$router->all('/api/additional/update', function () {
    includeWrapper('api/lib/tables.php', 'update');
});
//Авторизация
$router->all('/api/terminal/auth', function () {
    includeWrapper('api/terminal/auth.php', false);
});
//Получить
$router->all('/api/terminal/menu/get', function () {
    includeWrapper('api/terminal/menu.php', 'get');
});
//Создать транзакцию
$router->all('/api/terminal/transactions/create', function () {
    includeWrapper('api/terminal/transactions.php', 'create');
});
//Пометить чек как фискальный
$router->all('/api/terminal/transactions/fiscal', function () {
    includeWrapper('api/terminal/transactions.php', 'fiscal');
});
//Информация по точке
$router->all('/api/terminal/info/point', function () {
    includeWrapper('api/terminal/info.php', 'point');
});
//Смена
$router->all('/api/terminal/info/shift', function () {
    includeWrapper('api/terminal/info.php', 'shift');
});
//Открыть
$router->all('/api/terminal/shift/open', function () {
    includeWrapper('api/terminal/shifts.php', 'open');
});
//Закрыть
$router->all('/api/terminal/shift/close', function () {
    includeWrapper('api/terminal/shifts.php', 'close');
});
//Получить информацию по номеру телефона или номеру карты
$router->all('/api/terminal/clients/get', function () {
    includeWrapper('api/terminal/clients.php', 'get');
});
//Получить список
$router->all('/api/terminal/supplies/get', function () {
    includeWrapper('api/terminal/supplies.php', 'get');
});
//Список товаров в поставке
$router->all('/api/terminal/supplies/items', function () {
    includeWrapper('api/terminal/supplies.php', 'items');
});
//Подтверждение поставки или перемещения
$router->all('/api/terminal/supplies/confirm', function () {
    includeWrapper('api/terminal/supplies.php', 'confirm');
});
//Получить список
$router->all('/api/terminal/promotion/get', function () {
    includeWrapper('api/terminal/promotion.php', 'get');
});

//Акция в подарок
$router->all('/api/terminal/promotion/gifts', function () {
    includeWrapper('api/terminal/promotion.php', 'gifts');
});
//За смену
$router->all('/api/terminal/checks/shift', function () {
    includeWrapper('api/terminal/checks.php', 'shift');
});
//Получить
$router->all('/api/terminal/discounts/get', function () {
    includeWrapper('api/terminal/discounts.php', 'get');
});
//Создать возврат
$router->all('/api/terminal/refunds/create', function () {
    includeWrapper('api/terminal/refunds.php', 'create');
});
//Получить список
$router->all('/api/terminal/refunds/get', function () {
    includeWrapper('api/terminal/refunds.php', 'get');
});
//Чеклист
$router->all('/api/terminal/checklist', function () {
    includeWrapper('api/terminal/checklist.php', false);
});
//Создать
$router->all('/api/terminal-v2/transactions/create', function () {
    includeWrapper('api/terminal-v2/transactions.php', 'create');
});
//Завершение регистрации клиента
$router->all('/api/clients/registration', function () {
    includeWrapper('api/client/registration.php', false);
});

//Подстановка цен как в ПФ
$router->all('/api/pf', function () {
    includeWrapper('api/pf.php', false);
});
//Получить подробную информацию о клиенте
$router->all('/api/loyalclient/clients/info', function () {
    includeWrapper('api/loyalclient/clients.php', 'info');
});
//Пополнить баланс
$router->all('/api/loyalclient/clients/change_balance', function () {
    includeWrapper('api/loyalclient/clients.php', 'change_balance');
});