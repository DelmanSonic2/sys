<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffers\EmployeesBuffer;
use Controllers\GraphQL\Buffers\PointsBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class TransactionType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Чек',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'point' => [
                        'type' => Types::Point(),
                        'description' => 'Заведение',
                        'resolve' => function ($root, $args) {
                            if (isset($root['point'])) {
                                PointsBuffer::add((int) $root['point']);

                                return new Deferred(function () use ($root) {
                                    PointsBuffer::load();
                                    return PointsBuffer::get((int) $root['point']);
                                });
                            }
                        },
                    ],
                    "client_phone" => [
                        "type" => Type::string(),
                        "description" => "Телефон клиента",
                    ],
                    "employee" => [
                        'type' => Types::Employee(),
                        'description' => 'Сотрудник',
                        'resolve' => function ($root, $args) {
                            if (isset($root['employee'])) {
                                EmployeesBuffer::add((int) $root['employee']);

                                return new Deferred(function () use ($root) {
                                    EmployeesBuffer::load();
                                    return EmployeesBuffer::get((int) $root['employee']);
                                });
                            }
                        },
                    ],
                    "created" => [
                        "type" => Type::int(),
                        "description" => "Дата и время создания",
                    ],
                    "sum" => [
                        "type" => Type::float(),
                        "description" => "Сумма чека без скидок и бонусов",
                    ],
                    "total" => [
                        "type" => Type::float(),
                        "description" => "Оплачено с учетом скидок и бонусов",
                    ],
                    "points" => [
                        "type" => Type::float(),
                        "description" => "Баллов начислено/списанно",
                    ],
                    "profit" => [
                        "type" => Type::float(),
                        "description" => "Прибыль",
                        'resolve' => function ($root, $args) {
                            return !is_nan($root['profit']) ? number_format($root['profit'], 2, '.', '') : 0;
                        },
                    ],
                    "discount" => [
                        "type" => Type::int(),
                        "description" => "Скидка по чеку",
                        "resolve" => function ($root, $args) {
                            $discounts = [];
                            $items = array_merge((array) $root['items'], (array) $root['promotions']);
                            foreach ($items as $item) {
                                $discounts[] = $item['discount'];
                                $discounts[] = $item['time_discount'];

                            }

                            return max($discounts);
                        },
                    ],
                    "type" => [
                        "type" => Type::string(),
                        'description' => 'Тип оплаты',
                        "resolve" => function ($root, $args) {
                            return $root['type'] == 0 ? "Картой" : "Наличными";
                        },
                    ],
                    "promotion_code" => [
                        "type" => Type::string(),
                        "description" => "Промокод",
                    ],
                    "items" => [
                        "type" => Type::listOf(Types::TransactionItem()),
                        "description" => "Позиции чека",
                        "resolve" => function ($root, $args) {
                            return array_merge((array) $root['items'], (array) $root['promotions']);
                        },
                    ],

                ];
            },
        ];
        parent::__construct($config);
    }
}