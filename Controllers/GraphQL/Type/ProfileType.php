<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Types;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class ProfileType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Профиль',
            'fields' => function () {
                return [
                    'partner' => [
                        'type' => Type::int(),
                        'description' => 'ID партнера',
                    ],
                    'login' => [
                        'type' => Type::string(),
                        'description' => 'Логин',
                    ],
                    'name' => [
                        'type' => Type::string(),
                        'description' => 'Имя',
                    ],
                    'phone' => [
                        'type' => Type::string(),
                        'description' => 'Номер телефона',
                    ],
                    'city' => [
                        'type' => Type::string(),
                        'description' => 'Город (часовой пояс)',
                    ],
                    'admin' => [
                        'type' => Type::boolean(),
                        'description' => 'Админстратор',
                    ],
                    'access' => [
                        'type' => new ObjectType(
                            [
                                'name' => 'AccessUser',
                                'description' => 'Доступы аккаунта',
                                'fields' => [
                                    'product_categories' => [
                                        'type' => Type::listOf(Types::AccessAction())
                                    ],

                                ]
                            ]
                        ),
                        'description' => 'Доступы',
                        'resolve' => function ($root, $args) {
                            //TODO хардкод логика
                            if (Auth::$user['id'] == 1 || Auth::$user['id'] == 22)
                                $access = [
                                    'product_categories' => ['get', 'update', 'add']
                                ];
                            else
                                $access = [
                                    'product_categories' => ['get', 'update']
                                ];

                            return $access;
                        }
                    ],
                    'related_accounts' => [
                        'type' =>  Type::listOf(new ObjectType(
                            [
                                'name' => 'RelatedAccount',
                                'description' => 'Связанный аккаунт',
                                'fields' => [
                                    'name' => [
                                        'type' => Type::string(),
                                    ],
                                    'enter' => [
                                        'type' => Type::string(),
                                        'description' => 'Ссылка для переключения',
                                    ]

                                ]
                            ]
                        )),
                        'description' => 'Связанные аккаунты, для быстрого переключения',
                        'resolve' => function ($root, $args) {
                            //TODO хардкод логика
                            if (Auth::$user['employee']  == false && Auth::$country == 'ru') {
                                if (in_array(Auth::$user['id'], [1, 4, 5, 6, 7, 8, 9, 11])) {

                                    return mDB::collection("partners")->find([
                                        'country' => Auth::$country,
                                        'id' => ['$in' => ['1', '4', '5', '6', '7', '8', '9', '11']]
                                    ]);
                                }
                            }
                        }
                    ]

                ];
            }
        ];
        parent::__construct($config);
    }
}