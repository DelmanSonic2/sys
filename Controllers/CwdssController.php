<?php

namespace Controllers;

use Support\DB;
use Support\Request;
use Support\Utils;

function CategoriesTree($categories, $parent_id, $only_parent = false)
{

    $tree = [];

    if (is_array($categories) and isset($categories[$parent_id])) {
        if ($only_parent == false) {
            foreach ($categories[$parent_id] as $category) {
                if ($category['del']) {
                    $childs = CategoriesTree($categories, $category['id']);
                    if (!sizeof($childs)) {
                        continue;
                    }

                    for ($i = 0; $i < sizeof($childs); $i++) {
                        if ($i == 0) {
                            $category = $childs[$i];
                        } else {
                            $tree[] = $childs[$i];
                        }

                    }
                } else {
                    $category['childs'] = CategoriesTree($categories, $category['id']);
                }

                $tree[] = $category;
            }
        } elseif (is_numeric($only_parent)) {
            $category = $categories[$parent_id][$only_parent];
            $category['childs'] = CategoriesTree($categories, $category['id']);
            $tree[] = $category;
        }
    } else {
        return [];
    }

    return $tree;
}

class CwdssController
{

    private static $ApiKey = "9af3107a066f6b0defb1cafc0499f6ed";

    public static function pointMenu()
    {

        $result = [];
        $products = [];

        if (!Request::has(['point', 'apikey'])) {
            Utils::response("error", [], 3);
        }

        if (Request::$request['apikey'] != self::$ApiKey) {
            Utils::response("error", ['msg' => "apikey not valid"], 3);
        }

        $pointToken = DB::getRow(DB::select("*", 'app_partner_points', "id=" . Request::$request['point'], "", "1"));
        $archive = '
            AND p.id NOT IN (
                SELECT product_id
                FROM ' . DB_ARCHIVE . '
                WHERE model = "product" AND partner_id = ' . $pointToken['partner'] . '
            )';

        //============Получаем=список=продуктов===================
        //$productQuery = DB::select('id, name, category, image', DB_PRODUCTS, 'partner = '.$pointToken['partner'].' AND (SELECT COUNT() FROM '.DB_TECHNICAL_CARD.' WHERE product = ) > 0');
        $productQuery = DB::query('SELECT p.id, p.name, p.category, p.image
                                            FROM ' . DB_PRODUCTS . ' p
                                            JOIN ' . DB_MENU_PRODUCTS . ' m ON m.product = p.id AND m.partner = ' . $pointToken['partner'] . ' AND m.point = ' . $pointToken['id'] . '
                                            WHERE (p.partner = ' . $pointToken['partner'] . ' OR p.partner IS NULL) AND (SELECT COUNT(id)
                                                                                                FROM ' . DB_TECHNICAL_CARD . '
                                                                                                WHERE product = p.id
                                                                                                GROUP BY product) > 0' . $archive);

        while ($row = DB::getRow($productQuery)) {

            $row['cards'] = [];

            $products[] = $row;
        }

        //========================================================

        //============Получаем=список=тех.карт=и=цены=============

        /* $technical_cards_query = DB::query('SELECT tc.id, tc.code, tc.product, tc.subname, tc.bulk_value, tc.bulk_untils, tc.price, tc.different_price, pr.hide, pr.price AS difprice
        FROM '.DB_TECHNICAL_CARD.' tc
        JOIN '.DB_PRODUCT_COMPOSITION.' AS pc ON pc.technical_card = tc.id
        LEFT JOIN '.DB_PRODUCT_PRICES.' AS pr ON pr.technical_card = tc.id AND pr.point = '.$pointToken['id'].'
        WHERE (tc.partner = '.$pointToken['partner'].' OR tc.partner IS NULL) AND (tc.different_price = 0 OR (tc.different_price = 1 AND pr.point = '.$pointToken['id'].' AND (pr.hide = 0 OR pr.hide IS NULL)))
        GROUP BY tc.id
        ORDER BY tc.bulk_value ASC'); */

        $archive = '
            AND tc.id NOT IN (
                SELECT product_id
                FROM ' . DB_ARCHIVE . '
                WHERE model = "technical_card" AND partner_id = ' . $pointToken['partner'] . '
            )';

        $technical_cards_query = DB::query('SELECT tc.id, tc.code, tc.product, tc.subname, tc.bulk_value, tc.bulk_untils, tc.price, tc.different_price, pr.hide, pr.price AS difprice, tc.partner, tc.cashback_percent
                                                FROM ' . DB_TECHNICAL_CARD . ' tc
                                                JOIN ' . DB_PRODUCT_COMPOSITION . ' AS pc ON pc.technical_card = tc.id
                                                LEFT JOIN ' . DB_PRODUCT_PRICES . ' AS pr ON pr.technical_card = tc.id AND pr.point = ' . $pointToken['id'] . '
                                                WHERE (tc.partner = ' . $pointToken['partner'] . ' OR tc.partner IS NULL) AND (pr.hide = 0 OR pr.hide IS NULL)' . $archive . '
                                                GROUP BY tc.id
                                                ORDER BY tc.bulk_value ASC');

        while ($row = DB::getRow($technical_cards_query)) {

            for ($i = 0; $i < sizeof($products); $i++) {

                if ($row['product'] == $products[$i]['id']) {

                    if ($row['partner'] == null && !$pointToken['admin']) {
                        $price = (double) $row['difprice'];
                    } else {
                        $price = ($row['different_price']) ? (double) $row['difprice'] : (double) $row['price'];
                    }

                    if ((!$price || $price == null) && $row['price']) {
                        $price = (double) $row['price'];
                    }

                    $products[$i]['cards'][] = array('id' => $row['id'],
                        'subname' => $row['subname'],
                        'product' => $row['product'],
                        'bulk_value' => round($row['bulk_value'], 2),
                        'bulk_untils' => $row['bulk_untils'],
                        'code' => $row['code'],
                        //'count' => floor($row['product_count']),
                        'cashback_percent' => $row['cashback_percent'],
                        'price' => $price);

                    break;

                }

            }

        }

        //========================================================

        //============Получаем=список=категорий===================
        $categories = DB::query('SELECT c.id, c.name, c.parent, c.image
                                        FROM ' . DB_PRODUCT_CATEGORIES . ' c
                                        JOIN ' . DB_MENU_CATEGORIES . ' m ON m.category = c.id AND m.partner = ' . $pointToken['partner'] . ' AND m.point = ' . $pointToken['id'] . '
                                        WHERE (c.partner = ' . $pointToken['partner'] . ' OR c.partner IS NULL)');

        while ($row = DB::getRow($categories)) {

            $image = ($row['image'] == '') ? PLACEHOLDER_IMAGE : $row['image'];

            if (!$row['products']) {
                $row['products'] = [];
            }

            if (!$row['childs']) {
                $row['childs'] = [];
            }

            for ($i = 0; $i < sizeof($products); $i++) {

                if (sizeof($products[$i]['cards']) == 0) {
                    continue;
                }

                if ($row['id'] == $products[$i]['category']) {

                    $row['products'][] = array('id' => $products[$i]['id'],
                        'name' => $products[$i]['name'],
                        'image' => $products[$i]['image'],
                        'cards' => $products[$i]['cards']);

                }

            }

            $result[$row['parent']][$row['id']] = $row;
        }

        $result = CategoriesTree($result, null);
        //========================================================

        $without_category = [];
        foreach ($products as $product) {

            if (sizeof($product['cards']) == 0) {
                continue;
            }

            if (is_null($product['category'])) {
                $without_category[] = array(
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'image' => $product['image'],
                    'cards' => $product['cards'],
                );
            }

        }

        if (sizeof($without_category)) {
            $result[] = array(
                'id' => 0,
                'name' => 'Без категории',
                'products' => $without_category,
                'childs' => []
            );
        }

        Utils::response('success', $result, '7');

    }

}