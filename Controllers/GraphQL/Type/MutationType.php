<?php

namespace Controllers\GraphQL\Type;

use Controllers\GraphQL\Mutations\ArchiveMutation;
use Controllers\GraphQL\Mutations\AutoPriceMutation;
use Controllers\GraphQL\Mutations\ItemCategoryMutation;
use Controllers\GraphQL\Mutations\ItemMutation;
use Controllers\GraphQL\Mutations\ProductCategoryMutation;
use Controllers\GraphQL\Mutations\ProductMutation;
use Controllers\GraphQL\Mutations\TechnicalCardsMutation;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;


class MutationType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => function () {
                return [
                    'technical_card' => TechnicalCardsMutation::add(),
                    'archive' => ArchiveMutation::archive(),
                    'product' => ProductMutation::add(),
                    'item' => ItemMutation::add(),
                    'product_category' => ProductCategoryMutation::add(),
                    'mass_price_edit' => TechnicalCardsMutation::massPriceEdit(),
                    "item_category" => ItemCategoryMutation::add(),
                    "auto_price" => AutoPriceMutation::add()


                ];
            }
        ];
        parent::__construct($config);
    }
}