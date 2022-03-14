<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\DB;

class MenuCardType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Карточка в меню (товар)',
            'fields' => function () {
                return [
                    'id' => [
                        'type' => Type::int(),
                    ],
                    'code' => [
                        "description" => "Штрих код",
                        'type' => Type::int(),
                    ],
                    'product' => [
                        'type' => Type::int(),
                        'description' => 'ID продукта',
                    ],
                    'subname' => [
                        'type' => Type::string(),
                        'description' => 'Подназвание',
                    ],
                    'weighted' => [
                        'type' => Type::boolean(),
                        'description' => 'Весовой товар',
                    ],
                    'bulk_value' => [
                        'type' => Type::float(),
                        'description' => 'Кол-во/Объем',
                    ],
                    'bulk_untils' => [
                        'type' => Type::string(),
                        'description' => 'Еденица измерения',
                    ],
                    'cashback_percent' => [
                        'type' => Type::float(),
                        'description' => 'Кастоный кешбек',
                    ],
                    'price' => [
                        'type' => Type::float(),
                        'description' => 'Цена',
                    ]




                ];
            }
        ];
        parent::__construct($config);
    }
}