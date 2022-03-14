<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

switch($action){

    case 'accesses':

        if($partnerExist == 0)
            $accesses = array(  
                'login' => $userToken['login'],
                'terminal' => (bool)$userToken['terminal'],
                'statistics' => (bool)$userToken['statistics'],
                'finance' => (bool)$userToken['finances'],
                'menu' => (bool)$userToken['menu'],
                'warehouse' => (bool)$userToken['warehouse'],
                'marketing' => (bool)$userToken['marketing'],
                'cashbox' => (bool)$userToken['cashbox'],
                'accesses' => (bool)$userToken['accesses'],
                'currency' => CURRENCY,
                'round_price' => ROUND_PRICE,
                'user_type' => ($userToken['root']) ? 'partner' : 'employee',
                'administration' => false
            ) ;
        else
            $accesses = array(
                'login' => $userToken['login'],
                'terminal' => true,
                'statistics' => true,
                'finance' => true,
                'menu' => true,
                'warehouse' => true,
                'marketing' => true,
                'cashbox' => true,
                'accesses' => true,
                'currency' => CURRENCY,
                'round_price' => ROUND_PRICE,
                'user_type' => 'partner',
                'administration' => ($userToken['admin']) ? true : false
            );

        response('success', $accesses, 7);

    break;

}