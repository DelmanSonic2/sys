<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';
include ROOT.'api/lib/functions.php';

switch ($action) {

    case 'get':

        $result = [];

        $discounts = DB::query('SELECT time_from, time_to, weekdays, discount, type, technical_cards, categories
                                        FROM '.DB_TECHNICAL_CARD_DISCOUNT.'
                                        WHERE partner = '.$pointToken['partner'].' AND enable = 1 AND FIND_IN_SET("'.$pointToken['id'].'", points)');

        $ids = [];
        while ($row = DB::getRow($discounts)) {
            $row['weekdays'] = explode(',', $row['weekdays']);
            if ($row['type'] == 0) $row['technical_cards'] = explode(',', $row['technical_cards']);
            else {
                $cat = explode(',', $row['categories']);
                $row['categories'] = $cat;
                foreach ($cat as $c)
                    $ids[] = $c;
            }
            $result[] = $row;
        }

        if (count($ids) > 0) {
            $cardList = [];
            $categoryQuery = DB::query('SELECT c.id, p.category FROM ' . DB_TECHNICAL_CARD . ' c
                                                JOIN ' . DB_PRODUCTS . ' p ON c.product = p.id
                                                WHERE FIND_IN_SET(p.category, "' . implode(',', $ids) . '")');

            while ($row = DB::getRow($categoryQuery))
                $cardList[$row['category']][] = $row['id'];

            foreach ($result as $key => $val) {
                if ($val['categories'] == null) continue;
                $result[$key]['technical_cards'] = [];
                foreach ($val['categories'] as $c){
                	if($cardList[$c])
                    foreach ($cardList[$c] as $e)
                        $result[$key]['technical_cards'][] = $e;
                        }
            }
        }
        response('success', $result, 7);
        break;
}