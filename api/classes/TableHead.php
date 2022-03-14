<?php

use Support\Pages;
use Support\DB;

class TableHead
{

    public static function inventory_info()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Позиция",
                "dataIndex": "name",
                "formula": ""
            },
            {
                "title": "Ед. измерения",
                "dataIndex": "untils",
                "formula": ""
            },
            {
                "title": "Остатки на начало",
                "dataIndex": "begin_balance",
                "formula": "SUM"
            },
            {
                "title": "Поступления",
                "dataIndex": "income",
                "formula": "SUM"
            },
            {
                "title": "Расход",
                "dataIndex": "consumption",
                "formula": "SUM"
            },
            {
                "title": "Списано",
                "dataIndex": "detucted",
                "formula": "SUM"
            },
            {
                "title": "Списано, ' . CURRENCY . '",
                "dataIndex": "detucted_sum",
                "formula": "SUM"
            },
            {
                "title": "Плановый остаток",
                "dataIndex": "planned_balance",
                "formula": "SUM"
            },
            {
                "title": "Фактический остаток",
                "dataIndex": "actual_balance",
                "formula": "SUM"
            },
            {
                "title": "Сумма факт. остатка",
                "dataIndex": "actual_balance_sum",
                "formula": "SUM"
            },
            {
                "title": "Разница",
                "dataIndex": "different",
                "formula": "SUM"
            },
            {
                "title": "Разница, ' . CURRENCY . '",
                "dataIndex": "different_sum",
                "formula": "SUM"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => json_decode($subcolumns, true)
        );
    }

    public static function statistics_checks()
    {

        $subcolumns = '[
            {
                "title": "Продукт",
                "dataIndex": "name"
            },
            {
                "title": "Кол-во",
                "dataIndex": "count",
                "align": "right"
            },
            {
                "title": "Цена",
                "dataIndex": "price",
                "align": "right"
            },
            {
                "title": "Скидка по акции",
                "dataIndex": "time_discount",
                "align": "right"
            },
            {
                "title": "Итого",
                "dataIndex": "total",
                "align": "right"
            },
            {
                "title": "Тип",
                "dataIndex": "type",
                "align": "right"
            }
        ]';

        $columns = '[
            {
                "title": "Бариста",
                "dataIndex": "employee",
                "formula": "",
                "key": "employee",
                "sorter": {}
            },
            {
                "title": "Создан",
                "dataIndex": "created",
                "formula": "",
                "key": "created",
                "sorter": {}
            },
            {
                "title": "Заведение",
                "dataIndex": "point",
                "formula": "",
                "key": "point",
                "sorter": {}
            },
            {
                "title": "Клиент",
                "dataIndex": "client_name",
                "formula": "",
                "key": "client_name",
                "sorter": {}
            },
            {
                "title": "Номер клиента",
                "dataIndex": "client_phone",
                "formula": "",
                "key": "client_phone",
                "sorter": {}
            },
            {
                "title": "Сумма",
                "dataIndex": "sum",
                "align": "right",
                "formula": "SUM",
                "key": "sum",
                "sorter": {}
            },
            {
                "title": "Оплачено",
                "dataIndex": "total",
                "align": "right",
                "formula": "SUM",
                "key": "total",
                "sorter": {}
            },
            {
                "title": "Скидка",
                "dataIndex": "discount",
                "align": "right",
                "formula": "",
                "key": "discount",
                "sorter": {}
            },
            {
                "title": "Списано баллов",
                "dataIndex": "minus_points",
                "align": "right",
                "formula": "SUM",
                "key": "minus_points",
                "sorter": {}
            },
            {
                "title": "Начислено баллов",
                "dataIndex": "plus_points",
                "align": "right",
                "formula": "SUM",
                "key": "plus_points",
                "sorter": {}
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "key": "profit",
                "sorter": {}
            },
            {
                "title": "Тип оплаты",
                "dataIndex": "type",
                "align": "right",
                "formula": "",
                "key": "type"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => json_decode($subcolumns, true)
        );
    }

    public static function statistics_products()
    {

        $subcolumns = '[
            {
                "title": "Товар",
                "dataIndex": "product",
                "formula": ""
            },
            {
                "title": "Кол-во",
                "dataIndex": "count",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Вес",
                "dataIndex": "weight",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Cебестоимость",
                "dataIndex": "cost_price",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Без скидки",
                "dataIndex": "without_discount",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Скидка",
                "dataIndex": "discount",
                "align": "right",
                "formula": ""
            },
            {
                "title": "Выручка",
                "dataIndex": "revenue",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM"
            }
        ]';

        $columns = '[
            {
                "title": "Товар",
                "dataIndex": "product",
                "formula": "",
                "key": "product",
                "sorter": {}
            },
            {
                "title": "Категория",
                "dataIndex": "category",
                "formula": "",
                "key": "category",
                "sorter": {}
            },
            {
                "title": "Кол-во",
                "dataIndex": "count",
                "align": "right",
                "formula": "SUM",
                "key": "count",
                "sorter": {}
            },
            {
                "title": "Вес",
                "dataIndex": "weight",
                "align": "right",
                "formula": "SUM",
                "key": "weight",
                "sorter": {}
            },
            {
                "title": "Cебестоимость",
                "dataIndex": "cost_price",
                "align": "right",
                "formula": "SUM",
                "key": "cost_price",
                "sorter": {}
            },
            {
                "title": "Без скидки",
                "dataIndex": "without_discount",
                "align": "right",
                "formula": "SUM",
                "key": "without_discount",
                "sorter": {}
            },
            {
                "title": "Скидка",
                "dataIndex": "discount",
                "align": "right",
                "formula": "",
                "key": "discount",
                "sorter": {}
            },
            {
                "title": "Выручка",
                "dataIndex": "revenue",
                "align": "right",
                "formula": "SUM",
                "key": "revenue",
                "sorter": {}
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "key": "profit",
                "sorter": {}
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => json_decode($subcolumns, true)
        );
    }

    public static function removal_report()
    {

        $subcolumns = '[
            {
                "title": "Товар",
                "dataIndex": "name",
                "formula": ""
            },
            {
                "title": "Категория",
                "dataIndex": "category",
                "formula": ""
            },
            {
                "title": "Продажи",
                "dataIndex": "sales",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Списания",
                "dataIndex": "count",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Производство",
                "dataIndex": "production",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Списания к продажам",
                "dataIndex": "removal_to_sale",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Сумма списаний",
                "dataIndex": "sum",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Списания к прибыли",
                "dataIndex": "removal_to_profit",
                "align": "right",
                "formula": "SUM"
            }
        ]';

        $columns = '[
            {
                "title": "Товар",
                "dataIndex": "name",
                "formula": "",
                "key": "name",
                "sorter": {}
            },
            {
                "title": "Категория",
                "dataIndex": "category",
                "formula": "",
                "key": "category",
                "sorter": {}
            },
            {
                "title": "Продажи",
                "dataIndex": "sales",
                "align": "right",
                "formula": "SUM",
                "key": "sales",
                "sorter": {}
            },
            {
                "title": "Списания",
                "dataIndex": "count",
                "align": "right",
                "formula": "SUM",
                "key": "count",
                "sorter": {}
            },
            {
                "title": "Производство",
                "dataIndex": "production",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Списания к продажам",
                "dataIndex": "removal_to_sale",
                "align": "right",
                "formula": "SUM",
                "key": "removal_to_sale",
                "sorter": {}
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "key": "total",
                "sorter": {}
            },
            {
                "title": "Сумма списаний",
                "dataIndex": "sum",
                "align": "right",
                "formula": "SUM",
                "key": "sum",
                "sorter": {}
            },
            {
                "title": "Списания к прибыли",
                "dataIndex": "removal_to_profit",
                "align": "right",
                "formula": "SUM",
                "key": "removal_to_profit",
                "sorter": {}
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => json_decode($subcolumns, true)
        );
    }

    public static function statistics_clients()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Клиент",
                "dataIndex": "name",
                "formula": "",
                "key": "name",
                "sorter": {}
            },
            {
                "title": "Телефон",
                "dataIndex": "phone",
                "formula": "",
                "key": "phone",
                "sorter": {}
            },
            {
                "title": "Дата регистрации",
                "dataIndex": "registration_date",
                "align": "center",
                "formula": "",
                "key": "registration_date",
                "sorter": {}
        
            },
            {
                "title": "Без скидки",
                "dataIndex": "without_discount",
                "align": "right",
                "formula": "SUM",
                "key": "without_discount",
                "sorter": {}
            },
            {
                "title": "Наличными",
                "dataIndex": "cash",
                "align": "right",
                "formula": "SUM",
                "key": "cash",
                "sorter": {}
            },
            {
                "title": "Картой",
                "dataIndex": "card",
                "align": "right",
                "formula": "SUM",
                "key": "card",
                "sorter": {}
            },
            {
                "title": "Бонусами",
                "dataIndex": "points",
                "align": "right",
                "formula": "SUM",
                "key": "points",
                "sorter": {}
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "key": "profit",
                "sorter": {}
            },
            {
                "title": "Чеки",
                "dataIndex": "check_count",
                "align": "right",
                "formula": "SUM",
                "key": "check_count",
                "sorter": {}
            },
            {
                "title": "Средний чек",
                "dataIndex": "avg_check",
                "align": "right",
                "formula": "AVERAGE",
                "key": "avg_check",
                "sorter": {}
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function statistics_payments()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Дата",
                "dataIndex": "date",
                "formula": "",
                "key": "date",
                "sorter": {}
            },
            {
                "title": "Количество чеков",
                "dataIndex": "checks",
                "align": "right",
                "formula": "SUM",
                "key": "checks",
                "sorter": {}
            },
            {
                "title": "Наличными",
                "dataIndex": "cash",
                "align": "right",
                "formula": "SUM",
                "key": "cash",
                "sorter": {}
            },
            {
                "title": "Картой",
                "dataIndex": "card",
                "align": "right",
                "formula": "SUM",
                "key": "card",
                "sorter": {}
            },
            {
                "title": "Бонусами",
                "dataIndex": "points",
                "align": "right",
                "formula": "SUM",
                "key": "points",
                "sorter": {}
            },
            {
                "title": "Всего",
                "dataIndex": "total",
                "align": "right",
                "formula": "SUM",
                "key": "total",
                "sorter": {}
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function statistics_categories()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Категория",
                "dataIndex": "category",
                "formula": "",
                "sorter": {},
                "key": "category"
            },
            {
                "title": "Кол-во",
                "dataIndex": "count",
                "align" : "right",
                "formula": "SUM",
                "sorter": {},
                "key": "count"
            },
            {
                "title": "Себестоимость",
                "dataIndex": "cost_price",
                "align": "right",
                "formula": "SUM",
                "sorter": {},
                "key": "cost_price"
            },
            {
                "title": "Выручка",
                "dataIndex": "total",
                "align": "right",
                "formula": "SUM",
                "sorter": {},
                "key": "total"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "sorter": {},
                "key": "profit"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function statistics_employees()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "ФИО",
                "dataIndex": "name",
                "formula": "",
                "sorter": {},
                "key": "name"
            },
            {
                "title": "Выручка",
                "dataIndex": "revenue",
                "align": "right",
                "formula": "SUM",
                "sorter": {},
                "key": "revenue"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "sorter": {},
                "key": "profit"
            },
            {
                "title": "Чеки",
                "dataIndex": "check_count",
                "align": "right",
                "formula": "SUM",
                "sorter": {},
                "key": "check_count"
            },
            {
                "title": "Средний чек",
                "dataIndex": "avg_check",
                "align": "right",
                "formula": "AVERAGE",
                "sorter": {},
                "key": "avg_check"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function statistics_points($admin = false)
    {

        $subcolumns = '[]';

        $admin_columns = '[
            {
                "title": "Партнер",
                "dataIndex": "partner",
                "formula": "",
                "sorter" : {},
                "key": "partner"
            },
            {
                "title": "Заведение",
                "dataIndex": "name",
                "formula": "",
                "sorter" : {},
                "key": "name"
            },
            {
                "title": "Адрес",
                "dataIndex": "address",
                "formula": "",
                "sorter" : {},
                "key": "address"
            },
            {
                "title": "Выручка",
                "dataIndex": "total",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "total"
            },
            {
                "title": "Себестоимость",
                "dataIndex": "cost_price",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "cost_price"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "profit"
            },
            {
                "title": "Чеки",
                "dataIndex": "check_count",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "check_count"
            },
            {
                "title": "Средний чек",
                "dataIndex": "avg_check",
                "align": "right",
                "formula": "AVERAGE",
                "sorter" : {},
                "key": "avg_check"
            },
            {
                "title": "Картой",
                "dataIndex": "card",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "card"
            },
            {
                "title": "Наличными",
                "dataIndex": "cash",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "cash"
            },
            {
                "title": "Сумма скидок",
                "dataIndex": "discount_sum",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "discount_sum"
            },
            {
                "title": "Баллами",
                "dataIndex": "points_sum",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "points_sum"
            }
        ]';

        $columns = '[
            {
                "title": "Заведение",
                "dataIndex": "name",
                "formula": "",
                "sorter" : {},
                "key": "name"
            },
            {
                "title": "Адрес",
                "dataIndex": "address",
                "formula": "",
                "sorter" : {},
                "key": "address"
            },
            {
                "title": "Выручка",
                "dataIndex": "total",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "total"
            },
            {
                "title": "Себестоимость",
                "dataIndex": "cost_price",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "cost_price"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "profit"
            },
            {
                "title": "Чеки",
                "dataIndex": "check_count",
                "align": "right",
                "formula": "SUM",
                "sorter" : {},
                "key": "check_count"
            },
            {
                "title": "Средний чек",
                "dataIndex": "avg_check",
                "align": "right",
                "formula": "AVERAGE",
                "sorter" : {},
                "key": "avg_check"
            }
        ]';

        return array(
            'columns' => json_decode($admin ? $admin_columns : $columns, true),
            'subcolumns' => ''
        );
    }

    public static function statistics_promotions()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Наименование",
                "dataIndex": "name",
                "formula": "",
                "key": "name",
                "sorter": {}
            },
            {
                "title": "Количество",
                "dataIndex": "count",
                "align": "right",
                "formula": "SUM",
                "key": "count",
                "sorter": {}
            },
            {
                "title": "Себестоимость",
                "dataIndex": "cost_price",
                "align": "right",
                "formula": "SUM",
                "key": "cost_price",
                "sorter": {}
        
            },
            {
                "title": "Выручка",
                "dataIndex": "total",
                "align": "right",
                "formula": "SUM",
                "key": "total",
                "sorter": {}
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "right",
                "formula": "SUM",
                "key": "profit",
                "sorter": {}
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function statistics_items()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Ингредиент",
                "dataIndex": "name",
                "formula": "",
                "key": "name",
                "sorter": {}
            },
            {
                "title": "Категория",
                "dataIndex": "category",
                "formula": "",
                "key": "category",
                "sorter": {}
            },
            {
                "title": "Количество",
                "dataIndex": "count",
                "align": "right",
                "formula": "SUM",
                "key": "count",
                "sorter": {}
            },
            {
                "title": "Цена",
                "dataIndex": "price",
                "align": "right",
                "formula": "AVERAGE",
                "key": "price",
                "sorter": {}
            },
            {
                "title": "Сумма",
                "dataIndex": "total",
                "align": "right",
                "formula": "SUM",
                "key": "total",
                "sorter": {}
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function warehouse_removals()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Дата",
                "dataIndex": "date",
                "formula": ""
            },
            {
                "title": "Склад",
                "dataIndex": "name",
                "formula": ""
            },
            {
                "title": "Сумма",
                "dataIndex": "sum",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Причина",
                "dataIndex": "cause",
                "align": "",
                "formula": ""
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function warehouse_supplies()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Дата",
                "dataIndex": "date",
                "formula": ""
            },
            {
                "title": "Вх. номер",
                "dataIndex": "in_number",
                "align": "",
                "formula": ""
            },
            {
                "title": "Склад",
                "dataIndex": "point",
                "formula": ""
            },
            {
                "title": "Поставщик",
                "dataIndex": "supplier",
                "formula": ""
            },
            {
                "title": "Плательщик",
                "dataIndex": "payer",
                "formula": ""
            },
            {
                "title": "Сумма",
                "dataIndex": "sum",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Комментарий",
                "dataIndex": "comment",
                "align": "",
                "formula": ""
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function warehouse_moving()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Дата",
                "dataIndex": "date",
                "formula": ""
            },
            {
                "title": "Склад",
                "dataIndex": "point",
                "formula": ""
            },
            {
                "title": "Поставщик",
                "dataIndex": "supplier",
                "formula": ""
            },
            {
                "title": "Сумма",
                "dataIndex": "sum",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Комментарий",
                "dataIndex": "comment",
                "align": "",
                "formula": ""
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }

    public static function client_history()
    {

        $subcolumns = '[]';

        $columns = '[
            {
                "title": "Заведение",
                "dataIndex": "point",
                "formula": ""
            },
            {
                "title": "Сотрудник",
                "dataIndex": "employee",
                "formula": ""
            },
            {
                "title": "Клиент",
                "dataIndex": "phone",
                "formula": ""
            },
            {
                "title": "Дата",
                "dataIndex": "date",
                "formula": ""
            },
            {
                "title": "Время",
                "dataIndex": "time"
            },
            {
                "title": "Без скидки",
                "dataIndex": "sum",
                "align": "right",
                "formula": "SUM"
            },
            {
                "title": "Скидка в %",
                "dataIndex": "discount"
            },
            {
                "title": "Сумма",
                "dataIndex": "total",
                "formula": "SUM"
            },
            {
                "title": "Списано/начислено баллов",
                "dataIndex": "points",
                "formula": "SUM"
            },
            {
                "title": "Тип оплаты",
                "dataIndex": "type"
            },
            {
                "title": "Акция",
                "dataIndex": "promotion"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'subcolumns' => ''
        );
    }
}

class TableFooter
{

    public static function finance_transactions($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Сумма",
                "dataIndex": "sum",
                "align": "center"
            },
            {
                "title": "Поступления",
                "dataIndex": "income",
                "align": "center"
            },
            {
                "title": "Расход",
                "dataIndex": "consumption",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_checks($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Сумма",
                "dataIndex": "sum",
                "align": "center"
            },
            {
                "title": "Оплачено",
                "dataIndex": "total",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function removal_report($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Продажи",
                "dataIndex": "sales",
                "align": "center"
            },
            {
                "title": "Списания",
                "dataIndex": "count",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            },
            {
                "title": "Сумма списаний",
                "dataIndex": "sum",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_products($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Количество",
                "dataIndex": "count",
                "align": "center"
            },
            {
                "title": "Вес",
                "dataIndex": "weight",
                "align": "center"
            },
            {
                "title": "Себестоимость",
                "dataIndex": "cost_price",
                "align": "center"
            },
            {
                "title": "Без скидки",
                "dataIndex": "without_discount",
                "align": "center"
            },
            {
                "title": "Выручка",
                "dataIndex": "revenue",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_clients($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Без скидки",
                "dataIndex": "without_discount",
                "align": "center"
            },
            {
                "title": "Наличными",
                "dataIndex": "cash",
                "align": "center"
            },
            {
                "title": "Картой",
                "dataIndex": "card",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            },
            {
                "title": "Чеки",
                "dataIndex": "check_count",
                "align": "center"
            },
            {
                "title": "Средний чек",
                "dataIndex": "avg_check",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_payments($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Количество чеков",
                "dataIndex": "checks",
                "align": "center"
            },
            {
                "title": "Наличными",
                "dataIndex": "cash",
                "align": "center"
            },
            {
                "title": "Картой",
                "dataIndex": "card",
                "align": "center"
            },
            {
                "title": "Бонусами",
                "dataIndex": "points",
                "align": "center"
            },
            {
                "title": "Всего",
                "dataIndex": "total",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_categories($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Количество товаров",
                "dataIndex": "product_count",
                "align": "center"
            },
            {
                "title": "Себестоимость",
                "dataIndex": "cost_price",
                "align": "center"
            },
            {
                "title": "Выручка",
                "dataIndex": "total",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_employees($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Выручка",
                "dataIndex": "revenue",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            },
            {
                "title": "Чеки",
                "dataIndex": "check_count",
                "align": "center"
            },
            {
                "title": "Средний чек",
                "dataIndex": "avg_check",
                "align": "center"
            }

        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_points($data)
    {
        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Выручка",
                "dataIndex": "total",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            },
            {
                "title": "Чеки",
                "dataIndex": "check_count",
                "align": "center"
            },
            {
                "title": "Средний чек",
                "dataIndex": "avg_check",
                "align": "center"
            },
            {
                "title": "Картой",
                "dataIndex": "card",
                "align": "center"
            },
            {
                "title": "Наличными",
                "dataIndex": "cash",
                "align": "center"
            },
            {
                "title": "Сумма скидок",
                "dataIndex": "discount_sum",
                "align": "center"
            },
            {
                "title": "Баллами",
                "dataIndex": "points_sum",
                "align": "center"
            }

        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_promotions($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Количество",
                "dataIndex": "count",
                "align": "center"
            },
            {
                "title": "Себестоимость",
                "dataIndex": "cost_price",
                "align": "center"
            },
            {
                "title": "Выручка",
                "dataIndex": "total",
                "align": "center"
            },
            {
                "title": "Прибыль",
                "dataIndex": "profit",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }

    public static function statistics_items($data)
    {

        if (!$data)
            return '';

        $columns = '[
            {
                "title": "Количество",
                "dataIndex": "product_count",
                "align": "center"
            },
            {
                "title": "Сумма",
                "dataIndex": "total",
                "align": "center"
            }
        ]';

        return array(
            'columns' => json_decode($columns, true),
            'data' => $data
        );
    }
}