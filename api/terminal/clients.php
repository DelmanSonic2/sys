<?php

use Support\Pages;
use Support\DB;

include 'tokenCheck.php';
require ROOT . 'api/classes/PromotionGiftsClass.php';

$phone = DB::escape($_REQUEST['phone']);
$card_number = DB::escape($_REQUEST['card_number']);


switch ($action) {

    case 'get':

        // $old_client = integrationOldSystem($phone, $card_number);

        if ($phone && $card_number) { //Если пришел номер и карта

            $card_exists = DB::select('phone', DB_CLIENTS, 'card_number = "' . $card_number . '" OR additional_card = "' . $card_number . '"', '', 1);

            if (DB::getRecordCount($card_exists) != 0)
                response('error', 'Карта с таким номером уже приобретена другим клиентом.', 1);

            $where_str = 'c.phone = "' . $phone . '"';

            $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.balance, c.sale, c.verified, c.birthdate, c.sex, c.group_id, g.percent
                                        FROM ' . DB_CLIENTS . ' c
                                        JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                        WHERE ' . $where_str);

            if (DB::getRecordCount($clientData) == 0) {

                if (!$phone)
                    response('error', array('msg' => 'Пользователь не зарегистрирован в программе бонусов. Для регистрации необходимо указать номер мобильного телефона.'), '524');

                //Создаем уникальный ссылку активации для пользователя
                $link = hash('md5', $phone . time() . 'coffeeway');

                $card_number_data = DB::select('phone, card_number', DB_CLIENTS, 'card_number = "' . $card_number . '"');

                if (DB::getRecordCount($card_number_data) > 0)
                    response('error', array('msg' => 'Данная карта уже зарегистрирована. Используйте другую карту.'), 589);

                //Формируем сисок полей с минимальной информацией о пользователе
                $fields = array(
                    'phone' => $phone,
                    'activation_link' => $link,
                    'card_number' => $card_number,
                    'registration_date' => time(),
                    'group_id' => 2
                );


                DB::insert($fields, DB_CLIENTS);

                $activation_link = SITE_URL . 'client-form?link=' . $link;

                //   $body = file_get_contents("https://sms.ru/sms/send?api_id=77A6B351-075F-7A1F-B15E-AFF3E03C4095&to=7".$phone."&msg=".urlencode(iconv("UTF-8","UTF-8", 'Для продолжения регистрации перейдите по ссылке: '.$modx->makeUrl(246, '', '', 'full').'?phone='.$phone))."&json=1");

                $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.balance, c.sale, c.verified, c.birthdate, c.sex, c.group_id, g.percent
                                                FROM ' . DB_CLIENTS . ' c
                                                JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                                WHERE ' . $where_str);

                if (DB::getRecordCount($clientData) == 0)
                    response('error', array('msg' => 'Не получилось добавить клиента.'), '567');
            }

            $clientData = DB::getRow($clientData);

            if ($clientData['sale'] > 0)
                $clientData['balance'] = $clientData['percent'] = '0';

            if ($clientData['card_number'] != $card_number && $clientData['card_number'] != '')
                response('error', array('msg' => 'К данному телефону уже привязана другая карта.'), 590);

            if ($clientData['card_number'] == '') {

                if ($clientData['group_id'] < 3)
                    $set_percent = ', group_id = group_id + 1';

                DB::query('UPDATE ' . DB_CLIENTS . '
                                SET card_number = "' . $card_number . '"' . $set_percent . '
                                WHERE phone = "' . $phone . '"');

                $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.balance, c.sale, c.verified, c.birthdate, c.sex, g.percent
                                FROM ' . DB_CLIENTS . ' c
                                JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                WHERE ' . $where_str);

                $clientData = DB::getRow($clientData);

                if ($clientData['sale'] > 0)
                    $clientData['balance'] = $clientData['percent'] = '0';

                $clientData['balance'] = floor($clientData['balance']);
                $client_accumulation = new PromotionGifts(false, $clientData['phone']);
                $clientData['gifts'] = $client_accumulation->client()->accumulation;
                response('success', $clientData, '7');
            }

            if ($clientData['sale'] > 0)
                $clientData['balance'] = $clientData['percent'] = '0';

            $clientData['balance'] = floor($clientData['balance']);
            $client_accumulation = new PromotionGifts(false, $clientData['phone']);
            $clientData['gifts'] = $client_accumulation->client()->accumulation;
            response('success', $clientData, '7');
        }

        if (!$phone && $card_number) { //Если пришла только карта, то только получаем информацию

            $where_str = 'c.card_number = "' . $card_number . '" OR c.additional_card = "' . $card_number . '"';

            $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.additional_card, c.balance, c.sale, c.verified, c.birthdate, c.sex, g.percent, c.integration
                                        FROM ' . DB_CLIENTS . ' c
                                        JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                        WHERE ' . $where_str);

            if (DB::getRecordCount($clientData) == 0) {

                if (!$old_client || !$old_client['phone'])
                    response('error', array('msg' => 'Карта не зарегистрирована.'), 588);

                //Если клиент числится в старой системе
                $fields = array(
                    'phone' => $old_client['phone'],
                    'activation_link' => $link,
                    'card_number' => $old_client['card'],
                    'additional_card' => $old_client['additional_card'],
                    'registration_date' => time(),
                    'balance' => $old_client['balance'],
                    'integration' => 1,
                    'group_id' => 3
                );

                DB::insert($fields, DB_CLIENTS);

                $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.additional_card, c.balance, c.sale, c.verified, c.birthdate, c.sex, g.percent, c.integration
                                                FROM ' . DB_CLIENTS . ' c
                                                JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                                WHERE ' . $where_str);
            }

            $clientData = DB::getRow($clientData);

            if (!$clientData['integration'] && $old_client) {

                if ($old_client['card'] == $clientData['card_number'] || $old_client['card'] == $clientData['additional_card'] || $old_client['additional_card'] == $clientData['card_number'] || $old_client['additional_card'] == $clientData['additional_card']) {

                    if ($clientData['additional_card'] == '' && $old_client['additional_card'] != '' && $old_client['additional_card'] != $old_client['card_number'])
                        $add_card = ', additional_card = "' . $old_client['additional_card'] . '"';

                    if ($old_client['card'] != $clientData['card_number'] && $clientData['additional_card'] == '')
                        $add_card = ', additional_card = "' . $old_client['card'] . '"';

                    DB::query('UPDATE ' . DB_CLIENTS . '
                                        SET balance = balance + ' . $old_client['balance'] . ',
                                            integration = 1' . $add_card . '
                                        WHERE card_number = "' . $card_number . '" OR additional_card = "' . $card_number . '" AND integration = 0');

                    $clientData['balance'] += $old_client['balance'];
                }
            }

            if ($clientData['sale'] > 0)
                $clientData['balance'] = $clientData['percent'] = '0';

            $clientData['balance'] = floor($clientData['balance']);
            $client_accumulation = new PromotionGifts(false, $clientData['phone']);
            $clientData['gifts'] = $client_accumulation->client()->accumulation;
            response('success', $clientData, '7');
        }

        if ($phone && !$card_number) { //Если пришел номер телефона, то получаем инфу по номеру или регистрируем его

            $where_str = 'c.phone = "' . $phone . '"';

            $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.balance, c.sale, c.verified, c.birthdate, c.sex, g.percent, c.integration
                                        FROM ' . DB_CLIENTS . ' c
                                        JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                        WHERE ' . $where_str);

            if (DB::getRecordCount($clientData) == 0) {

                if (!$phone) {

                    if (!$old_client)
                        response('error', array('msg' => 'Пользователь не зарегистрирован в программе бонусов. Для регистрации необходимо указать номер мобильного телефона.'), '524');

                    //Если клиент числится в старой системе
                    $fields = array(
                        'phone' => $old_client['phone'],
                        'card_number' => $old_client['card'],
                        'additional_card' => $old_client['additional_card'],
                        'registration_date' => time(),
                        'balance' => $old_client['balance'],
                        'integration' => 1,
                        'group_id' => 3
                    );

                    DB::insert($fields, DB_CLIENTS);

                    $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.balance, c.sale, c.verified, c.birthdate, c.sex, g.percent, c.integration
                                                    FROM ' . DB_CLIENTS . ' c
                                                    JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                                    WHERE ' . $where_str . '
                                                    LIMIT 1');

                    $clientData = DB::getRow($clientData);

                    $clientData['balance'] = floor($clientData['balance']);
                    $client_accumulation = new PromotionGifts(false, $clientData['phone']);
                    $clientData['gifts'] = $client_accumulation->client()->accumulation;
                    response('success', $clientData, '7');
                }
                //Создаем уникальный ссылку активации для пользователя
                $link = hash('md5', $phone . time() . 'coffeeway');

                //Формируем сисок полей с минимальной информацией о пользователе
                $fields = array(
                    'phone' => $phone,
                    'activation_link' => $link,
                    'registration_date' => time(),
                    'group_id' => 1
                );

                DB::insert($fields, DB_CLIENTS);

                $activation_link = SITE_URL . 'client-form?link=' . $link;

                //   $body = file_get_contents("https://sms.ru/sms/send?api_id=77A6B351-075F-7A1F-B15E-AFF3E03C4095&to=7".$phone."&msg=".urlencode(iconv("UTF-8","UTF-8", 'Для продолжения регистрации перейдите по ссылке: '.$modx->makeUrl(246, '', '', 'full').'?phone='.$phone))."&json=1");

                $clientData = DB::query('SELECT c.phone, c.name, c.card_number, c.balance, c.sale, c.verified, c.birthdate, c.sex, g.percent
                                                FROM ' . DB_CLIENTS . ' c
                                                JOIN ' . DB_CLIENTS_GROUP . ' AS g ON g.id = c.group_id
                                                WHERE ' . $where_str);

                if (DB::getRecordCount($clientData) == 0)
                    response('error', array('msg' => 'Не получилось добавить клиента.'), '567');
            }

            $clientData = DB::getRow($clientData);
            $clientData['sms'] = $body;

            if (!$clientData['integration'] && $old_client) {

                if ($old_client['card'] == $clientData['card_number'] || $old_client['card'] == $clientData['additional_card'] || $old_client['additional_card'] == $clientData['card_number'] || $old_client['additional_card'] == $clientData['additional_card']) {

                    if ($clientData['additional_card'] == '' && $old_client['additional_card'] != '' && $old_client['additional_card'] != $old_client['card_number'])
                        $add_card = ', additional_card = "' . $old_client['additional_card'] . '"';

                    if ($old_client['card'] != $clientData['card_number'] && $clientData['additional_card'] == '')
                        $add_card = ', additional_card = "' . $old_client['card'] . '"';

                    if ($clientData['card_number'] == '' && $old_client['additional_card'] != '') {
                        $clientData['card_number'] = $old_client['additional_card'];
                        $add_card = ', card_number = "' . $old_client['additional_card'] . '"';
                    }

                    if ($clientData['card_number'] == '' && $old_client['card'] != '') {
                        $clientData['card_number'] = $old_client['card'];
                        $add_card = ', card_number = "' . $old_client['card'] . '"';
                    }

                    DB::query('UPDATE ' . DB_CLIENTS . '
                                        SET balance = balance + ' . $old_client['balance'] . ',
                                            integration = 1' . $add_card . '
                                        WHERE phone = "' . $phone . '" AND integration = 0');

                    $clientData['balance'] += $old_client['balance'];
                }
            }

            if ($clientData['sale'] > 0)
                $clientData['balance'] = $clientData['percent'] = '0';

            $clientData['balance'] = floor($clientData['balance']);
            $client_accumulation = new PromotionGifts(false, $clientData['phone']);
            $clientData['gifts'] = $client_accumulation->client()->accumulation;
            response('success', $clientData, '7');
        }

        response('error', array('msg' => 'Введите номер телефона или номер карты клиента.'), '522');

        break;
}