<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/terminal/tokenCheck.php';
require ROOT.'api/classes/TransactionClassV2.php';
require ROOT.'api/classes/LoyalclientSyncClass.php';

switch ($action) {

    case 'create':

        $tr_class = new TransactionClassV2(false, $pointToken['id'], $pointToken['partner'], $json);
        if (
            ((isset($json["points"]) && $json["points"] != 0) || (isset($json["discount"]) && $json["discount"] != 0))
            && isset($json["loyalclient"])
        ) {


            $point = DB::getRow(DB::select('lc_id', 'app_partner_points', "id=" . $pointToken['id']));

        
            $url = 'https://loyalclient.apiloc.ru/integration/transactions/add';
            $data = [
                "uniq_id" => isset($tr_class->transaction["id"]) ? $tr_class->transaction["id"] : 0,
                "user" => isset($json["loyalclient"]["client"]["phone"]) ? $json["loyalclient"]["client"]["phone"] : 0,
                "point_id" => isset($point['lc_id']) ? $point['lc_id'] : 0,
                "amount" => isset($json["sum"]) ? $json["sum"] : 0,
                "discount" => isset($json["discount"]) ? $json["discount"] : 0,
                "bonuses" => isset($json["points"]) ? $json["points"] : 0,
                "api_key" => LC_API_KEY
            ];

         

            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data)
                )
            );
            $context  = stream_context_create($options);
            @file_get_contents($url, false, $context);

            /*       $ch = curl_init('https://loyalclient.apiloc.ru/integration/transactions/add');
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "uniq_id" => isset($tr_class->transaction["id"]) ? $tr_class->transaction["id"] : 0,
                "user" => isset($json["loyalclient"]["client"]) ? $json["loyalclient"]["client"] : 0,
                "point_id" => $point['lc_id'],
                "amount" => isset($json["sum"]) ? $json["sum"] : 0,
                "discount" => isset($json["discount"]) ? $json["discount"] : 0,
                "bonuses" => isset($json["points"]) ? $json["points"] : 0,
                "api_key"=>LC_API_KEY
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);*/


            //  $lc_class = new LoyalclientSyncClass(false, $pointToken['id'], $tr_class->transaction["id"], $json, "/sync/transactions");
            //  $lc_class->Request();
        }
        response('success', $tr_class->transaction, 201);

        break;
}
