<?php

namespace Controllers;

use Support\DB;
use Support\Utils;
use Support\Request;


class ProductionController
{
    public static function get()
    {

        $user = Request::authUser();

        if (!$user)   Utils::response("error","Не авторизован",0);

        if(!Request::has(['from','to'])) Utils::response("error","Укажите диапазон дат",3);



        $from = Request::$request['from'];
        $to = Request::$request['to'];

        $from =  strtotime(date('Y-m-d', (int)$from));
        $to =  strtotime(date('Y-m-d', (int)$to)) + (24 * 60 * 60);


        $point = '';
        if (isset(Request::$request['point']))
            $point = ' AND pr.point = ' . Request::$request['point'];

        $point_to = '';
        if (isset(Request::$request['point_to']))
            $point_to = ' AND pr.point_to = ' . Request::$request['point_to'];


        $where_category  = '';
        if (isset(Request::$request['category']))
            $where_category = ' AND i.product_category = ' . Request::$request['category'];

        $productions = DB::makeArray(DB::query('SELECT pr.id, e.name AS employee, p.name AS point, pr.comment, SUM(pi.count) as products_count, SUM(pi.cost_price) as sum, pr.date,
                                    (SELECT inv.id FROM app_inventory inv WHERE (inv.point = pr.point OR inv.point = pr.point_to) AND inv.status = 1 AND inv.date_end >= pr.date LIMIT 1) AS close,
                                    (SELECT SUM(count) FROM app_production_items WHERE production = pr.id) AS total_count
                                        FROM app_productions pr
                                        LEFT JOIN app_employees e ON e.id = pr.employee
                                        LEFT JOIN app_production_items pi ON pi.production = pr.id
                                        LEFT JOIN app_items i ON i.id = pi.product
                                        JOIN app_partner_points p ON p.id = pr.point
                                        WHERE pr.partner = ' . $user['partner'] . ' AND pr.date >= ' . $from . ' AND pr.date < ' . $to . $point . $where_category . $point_to . '
                                        GROUP BY pr.id'));


        $ids = [];
        foreach ($productions as $production) {
            $ids[] = $production['id'];
        }

        if (count($ids) > 0) {
            $items = DB::makeArray(DB::query('SELECT i.id, i.name, pri.production, pri.count, pri.cost_price, i.untils
                                                                FROM app_production_items pri
                                                                JOIN app_items i ON i.id = pri.product
                                                                WHERE pri.production IN  (' . implode(',', $ids) . ')' . $where_category));
            foreach ($productions as &$production) {
                foreach ($items as $item) {
                    if ($item['production'] == $production['id'])
                        $production['details'][] = $item;
                }
            }
        }


        Utils::responsePlain($productions);
    }
}
