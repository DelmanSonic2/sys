<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'get':

        $result = [];

        if($search = DB::escape($_REQUEST['search']))
            $search = '(c.phone LIKE "%'.$search.'%" OR c.name LIKE "%'.$search.'%")';
        else
            $search = 1;

        $sorting = Order::clients(Pages::$field, Pages::$order);

        $clients = DB::query('SELECT c.phone, c.name, c.card_number, c.balance, c.sale, c.verified, c.birthdate, c.sex, c.email, c.country, c.city, c.address, c.registration_date 
                                    FROM '.DB_CLIENTS.' c
                                    WHERE '.$search.'
                                    '.$sorting.'
                                    LIMIT '.Pages::$limit);

        while($row = DB::getRow($clients))
            $result[] = array(  'phone' => $row['phone'],
                                'name' => $row['name'],
                                'card_number' => (int)$row['card_number'],
                                'balance' => round($row['balance'], 2),
                                'sale' => round($row['sale'], 2),
                                'verified' => (bool)$row['verified'],
                                'birthdate' => (int)$row['birthdate'],
                                'sex' => $row['sex'],
                                'country' => $row['country'],
                                'city' => $row['city'],
                                'address' => $row['address'],
                                'registration_date' => (int)$row['registration_date']);

        $pages = DB::query('SELECT COUNT(c.phone) AS count 
                                    FROM '.DB_CLIENTS.' c
                                    WHERE '.$search);

        $pages = DB::getRow($pages);

        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;

        $pageData = array('current_page' => (int)Pages::$page,
                        'total_pages' => $total_pages,
                        'rows_count' => (int)$pages['count'],
                        'page_size' => ELEMENT_COUNT);

        response('success', $result, 7, $pageData);

    break;

}