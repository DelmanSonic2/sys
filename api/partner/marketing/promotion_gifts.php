<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

switch($action){

    case 'create':

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', 'Укажите название акции.', 422);

        $global = DB::escape($_REQUEST['global']);

        if(isset($global) && $global != 0 && $global != 1)
            response('error', 'Параметр global указан неверно.', 422);

        if(!$count = DB::escape($_REQUEST['count']))
            response('error', 'Укажите количество накапливаемых продуктов.', 1);

        if(!is_numeric($count))
            response('error', 'count должен быть числом.', 422);

        if(!$conditions = DB::escape($_REQUEST['conditions']))
            response('error', 'Укажите товары, учавствующие в акции.', 422);

        $conditions_exist = DB::query('
            SELECT GROUP_CONCAT(id) AS id
            FROM '.DB_TECHNICAL_CARD.'
            WHERE FIND_IN_SET(id, "'.$conditions.'") AND (partner = '.$userToken['id'].' OR partner IS NULL)
        ');

        $conditions = DB::getRow($conditions_exist)['id'];

        if(!$conditions)
            response('error', 'Укажите товары, учавствующие в акции.', 422);

        $conditions_arr = explode(',', $conditions);

        foreach($conditions_arr AS $value){

            $product_exist = DB::query('
                SELECT id
                FROM '.DB_PROMOTION_GIFTS.'
                WHERE FIND_IN_SET('.$value.', conditions)
                LIMIT 1
            ');

            if(DB::getRecordCount($product_exist)){
                $item_name = DB::getRow(
                    DB::query('
                        SELECT CONCAT(p.name, ", ", tc.bulk_value, " ", tc.bulk_untils) AS name
                        FROM '.DB_TECHNICAL_CARD.' tc
                        JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
                        WHERE tc.id = '.$value.'
                    ')
                )['name'];

                response('error', '"'.$item_name.'" уже учавствует в акции.', 422);

            }

        }

        if(!$gifts = DB::escape($_REQUEST['gifts']))
            response('error', 'Заполните список подарочных товаров.', 1);

        $gifts_exist = DB::query('
            SELECT GROUP_CONCAT(id) AS id
            FROM '.DB_TECHNICAL_CARD.'
            WHERE FIND_IN_SET(id, "'.$gifts.'") AND (partner = '.$userToken['id'].' OR partner IS NULL)
        ');
        
        $gifts = DB::getRow($gifts_exist)['id'];

        if(!$gifts)
            response('error', 'Заполните список подарочных товаров.', 1);

        $fields = array(
            'name' => trim($name),
            'partner' => $userToken['id'],
            'global' => $global,
            'count' => $count,
            'conditions' => $conditions,
            'gifts' => $gifts,
            'created' => time()
        );

        if(DB::insert($fields, DB_PROMOTION_GIFTS))
            response('success', 'Акция добавлена.', 201);

        response('error', 'Не удалось добавить акцию.', 422);


    break;

    case 'get':

        $data = DB::query('SELECT *
                                    FROM '.DB_PROMOTION_GIFTS.'
                                    WHERE partner = '.$userToken['id'].' OR global = 1');

        while($row = DB::getRow($data)){

            $result[] = $row;
        }

        response('success', $result, '7');

    break;

    case 'update' :

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбрана акциия!', 1);

        if($name = DB::escape($_REQUEST['name'])){

            if(!$update)
                $update = 'name = '.$name;
            else
                $update .= ', name = '.$name;
        }


        if($global = DB::escape($_REQUEST['global'])){

            if(isset($global) && $global != 0 && $global != 1)
                response('error', 'Параметр global указан неверно.', 422);

            if(!$update)
                $update = 'global = '.$global;
            else
                $update .= ', global = '.$global;
        }

        if($count = DB::escape($_REQUEST['count'])){

            if(!is_numeric($count))
                response('error', 'count должен быть числом.', 422);

            if(!$update)
                $update = 'count = '.$count;
            else
                $update .= ', count = '.$count;
        }

        if($conditions = DB::escape($_REQUEST['conditions'])){

            $conditions_exist = DB::query('
            SELECT GROUP_CONCAT(id) AS id
            FROM '.DB_TECHNICAL_CARD.'
            WHERE FIND_IN_SET(id, "'.$conditions.'") AND (partner = '.$userToken['id'].' OR partner IS NULL)
            ');

            $conditions = DB::getRow($conditions_exist)['id'];

            if(!$update)
                $update = 'conditions = '.$conditions;
            else
                $update .= ', conditions = '.$conditions;

            $conditions_arr = explode(',', $conditions);

            foreach($conditions_arr AS $value){

                $product_exist = DB::query('
                    SELECT id
                    FROM '.DB_PROMOTION_GIFTS.'
                    WHERE FIND_IN_SET('.$value.', conditions) AND id != '.$id.'
                    LIMIT 1
                ');

                if(DB::getRecordCount($product_exist)){
                    $item_name = DB::getRow(
                        DB::query('
                            SELECT CONCAT(p.name, ", ", tc.bulk_value, " ", tc.bulk_untils) AS name
                            FROM '.DB_TECHNICAL_CARD.' tc
                            JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
                            WHERE tc.id = '.$value.'
                        ')
                    )['name'];

                    response('error', '"'.$item_name.'" уже учавствует в акции.', 422);

                }

            }
        }

        if($gifts = DB::escape($_REQUEST['gifts'])){

            $gifts_exist = DB::query('
                SELECT GROUP_CONCAT(id) AS id
                FROM '.DB_TECHNICAL_CARD.'
                WHERE FIND_IN_SET(id, "'.$gifts.'") AND (partner = '.$userToken['id'].' OR partner IS NULL)
            ');
            
            $gifts = DB::getRow($gifts_exist)['id'];

            if(!$update)
                $update = 'gifts = '.$gifts;
            else
                $update .= ', gifts = '.$gifts;
        }

        DB::query('UPDATE '.DB_PROMOTION_GIFTS.' SET '.$update.' WHERE id = '.$id);

        response('success', 'Success!', '7');

    break;

    case 'delete' :

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбрана акциия!', 1);

        DB::delete(DB_PROMOTION_GIFTS, 'id = '.$id.' AND (partner = '.$userToken['id'].' OR global = 1)');

        response('success', 'Success!', '7');

    break;

    case 'info' :

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбрана акциия!', 1);

        $data = DB::query('SELECT *
                                    FROM '.DB_PROMOTION_GIFTS.'
                                    WHERE id = '.$id.' AND (partner = '.$userToken['id'].' OR global = 1)');

        $result = DB::getRow($data);

        response('success', $result, '7');

    break;

}