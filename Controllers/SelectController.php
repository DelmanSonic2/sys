<?php

namespace Controllers;

use Support\DB;
use Support\Request;
use Support\Utils;

class SelectController
{

    public static function technicalCards()
    {

        $user = Request::authUser();


        $data = DB::makeArray(DB::query("SELECT t.id, CONCAT(p.`name`,' ', t.`subname`, ' ',t.`bulk_value`, t.`bulk_untils`) as name FROM `app_technical_card` t JOIN `app_products` p WHERE t.`product`=p.id AND (t.`partner`=" . $user['partner'] . " OR t.`partner`=0 OR t.`partner` IS NULL)"));

        Utils::response("success", $data, 7);
    }


    //причины списания
    public static function removalCauses()
    {

        $user = Request::authUser();

        $data = DB::makeArray(DB::select("id,name", "app_removal_causes", "(`partner`=" . $user['partner'] . " OR `partner`=0 OR `partner` IS NULL) AND deleted_at IS NULL"));

        Utils::response("success", $data, 7);
    }



    public static function itemsCategory()
    {

        $user = Request::authUser();



        $data = DB::makeArray(DB::select("id,name", "app_items_category", "(`partner`=" . $user['partner'] . " OR `partner`=0 OR `partner` IS NULL)"));

        Utils::response("success", $data, 7);
    }



    public static function points()
    {

        $user = Request::authUser();



        $data = DB::makeArray(DB::select("id,name", "app_partner_points", "`partner`=" . $user['partner']));

        Utils::response("success", $data, 7);
    }


    public static function  productCategories()
    {

        $user = Request::authUser();


        $data = DB::makeArray(DB::select("id,name", "app_product_categories", "`partner`=" . $user['partner'] . " OR `partner`=0 OR `partner` IS NULL"));

        Utils::response("success", $data, 7);
    }


    public static function partners()
    {

        $user = Request::authUser();


        $data = DB::makeArray(DB::select("id,name", "app_partner", "", "name"));

        Utils::response("success", $data, 7);
    }


    public static function pointItems()
    {

        $user = Request::authUser();

        $point = (int)Request::$request['point'];

        $data = [];

        if ($point > 0)
            $data = DB::makeArray(DB::query("SELECT i.id,i.name,i.untils,pi.price,pi.count,i.conversion_item_id FROM `app_items` i JOIN `app_point_items` pi WHERE i.id = pi.item AND pi.point={$point} ORDER BY i.name"));


        if ($user['partner'] == 22 || $user['partner'] == 187) {
            foreach ($data as &$val) {
                $val['conversion_item_id'] = null;
            }
        }


        Utils::response("success", $data, 7);
    }
}