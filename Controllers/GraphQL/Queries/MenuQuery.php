<?php

namespace Controllers\GraphQL\Queries;


use Controllers\GraphQL\Types;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Support\Auth;
use Support\DB;
use Support\mDB;

class MenuQuery
{

    public static function get()
    {
        return  [
            'type' => Type::listOf(Types::MenuProductCategory()),
            'args' => [
                'point' => ["type" => Type::nonNull(Type::int()),  'description' => "ID точки продаж"],
            ],
            'description' => 'Меню для точки продаж',
            'resolve' => function ($root, $args) {

                $partner_point =  DB::getRow(DB::query("SELECT * FROM app_partner_points WHERE id=" . $args['point']));

                if (!$partner_point) return [];

                $partner = $partner_point['partner'];
                $point = $partner_point['id'];

                $data = DB::makeArray(DB::query("SELECT pc.id as pc_id, pc.name as pc_name, pc.image as pc_image, pc.parent as pc_parent,
                ppc.id as ppc_id, ppc.name as ppc_name, ppc.image as ppc_image, ppc.parent as ppc_parent,
                                pr.id as pr_id, pr.name as pr_name, pr.image as pr_image,
                                tc.id as tc_id, tc.code as tc_code, tc.product as tc_product, tc.`subname` as tc_subname, tc.weighted as tc_weighted, 
                                tc.bulk_value as tc_bulk_value, tc.bulk_untils as tc_bulk_untils, tc.cashback_percent as tc_cashback_percent, ps.price as ps_price
                                 FROM `app_product_categories` pc JOIN `app_products` pr ON pr.`category` = pc.id JOIN `app_technical_card` tc ON tc.`product` = pr.id JOIN app_product_prices ps ON ps.`technical_card`=tc.id JOIN  app_product_categories ppc ON ppc.id = pc.`parent` 
                                WHERE (pc.`partner` = $partner OR pc.`partner` IS NULL) AND  (tc.`partner` = $partner OR tc.`partner` IS NULL)  AND (pc.`partner` = $partner OR pc.`partner` IS NULL) AND ps.`point` = $point AND ps.`hide` = 0 
                                AND tc.id NOT IN (
                SELECT product_id
                FROM `app_archive`
                WHERE model = 'technical_card' AND partner_id = $partner) 
                AND pr.id NOT IN (
                SELECT product_id
                FROM `app_archive`
                WHERE model = 'product' AND partner_id = $partner) 
                                ORDER BY pr_id"));

                $result = [];

                $parent_ids  = [];

                foreach ($data as $item) {
                    if (!isset($result[$item['pc_id']])) {

                        if ($item['pc_parent'] > 0) $parent_ids[] = $item['pc_parent'];

                        $result[$item['pc_id']] = [
                            'id' => $item['pc_id'],
                            'name' => $item['pc_name'],
                            'image' => $item['pc_image'],
                            'parent' => $item['pc_parent'],
                            'products' => []
                        ];
                    }

                    if (!isset($result[$item['ppc_id']])) {

                        $result[$item['ppc_id']] = [
                            'id' => $item['ppc_id'],
                            'name' => $item['ppc_name'],
                            'image' => $item['ppc_image'],
                            'parent' => $item['ppc_parent'],
                            'products' => []
                        ];
                    }

                    if (!isset($result[$item['pc_id']]['products'][$item['pr_id']]))
                        $result[$item['pc_id']]['products'][$item['pr_id']] = [
                            'id' => $item['pr_id'],
                            'name' => $item['pr_name'],
                            'image' => $item['pr_image'],
                            'cards' => []
                        ];


                    $result[$item['pc_id']]['products'][$item['pr_id']]['cards'][] = [
                        'id' => $item['tc_id'],
                        'code' => $item['tc_code'],
                        'product' => $item['tc_product'],
                        'subname' => $item['tc_subname'],
                        'weighted' => $item['tc_weighted'],
                        'bulk_value' => $item['tc_bulk_value'],
                        'bulk_untils' => $item['tc_bulk_untils'],
                        'cashback_percent' => $item['tc_cashback_percent'],
                        'price' => $item['ps_price']
                    ];
                }



                return $result;

                //    return DB::makeArray(DB::query("SELECT id, name, parent, image FROM `app_product_categories` WHERE partner = 1 OR partner IS NULL"));
            }

        ];
    }
}