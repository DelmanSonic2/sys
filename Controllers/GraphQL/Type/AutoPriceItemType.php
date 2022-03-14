<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Buffers\PointsBuffer;
use Controllers\GraphQL\Types;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class AutoPriceItemType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'description' => 'Параметры для автоизменения цен',
            'fields' => function () {
                return [
                    "technical_card" => [
                        "type" => Types::TechnicalCard(),
                        "description" => "тех карта",
                        'resolve' => function ($root, $args) {
                            return  mDB::collection("technical_cards")->findOne([
                                "country" => Auth::$country,
                                "id" => $root['technical_card_id']
                            ]);
                        }
                    ],
                    "point" => [
                        "type" => Types::Point(),
                        "description" => "заведение",
                        'resolve' => function ($root, $args) {
                            if (isset($root['point_id'])) {
                                PointsBuffer::add((int)$root['point_id']);

                                return new Deferred(function () use ($root) {
                                    PointsBuffer::load();
                                    return PointsBuffer::get((int)$root['point_id']);
                                });
                            }
                        }
                    ],
                    "price" => [
                        "type" => Type::int(),
                        "description" => "Цена"
                    ]
                ];
            }
        ];
        parent::__construct($config);
    }
}