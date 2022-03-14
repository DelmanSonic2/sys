<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/classes/ProductionCostPriceClass.php';

switch($action){

    case 'create' :

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', 'Не выбрана категория.', 1);

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбран id.', 1);

        switch($category){

            case 'item' :

                $check = DB::query('SELECT id
                                            FROM '.DB_ARCHIVE.'
                                            WHERE product_id = '.$id.' AND partner_id = '.$userToken['id'].' AND model = "item"');

                if(DB::getRecordCount($check) != 0)
                    response('error', "Вы уже добавили данный ингредиент в архив.", 1);

                $check_add = DB::query('SELECT id
                                            FROM '.DB_ITEMS.'
                                            WHERE id = '.$id.' AND (partner IS NULL OR partner = '.$userToken['id'].')');
                                        
                if(DB::getRecordCount($check_add) == 0)
                    response('error', "Недостаточно прав для совершения действия.", 1);

                $insert = array('partner_id' => $userToken['id'],
                                'product_id' => $id,
                                'model' => "item",
                                'created' => time());

                DB::insert($insert, DB_ARCHIVE);

                response('success', 'Ингредиент успешно добален в архив.', 7);

            break;

            case 'product' :

                $check = DB::query('SELECT id
                                            FROM '.DB_ARCHIVE.'
                                            WHERE product_id = '.$id.' AND partner_id = '.$userToken['id'].' AND model = "product"');

                if(DB::getRecordCount($check) != 0)
                    response('error', "Вы уже добавили данный товар в архив.", 1);

                $check_add = DB::query('SELECT id
                                            FROM '.DB_PRODUCTS.'
                                            WHERE id = '.$id.' AND (partner IS NULL OR partner = '.$userToken['id'].')');
                                        
                if(DB::getRecordCount($check_add) == 0)
                    response('error', "Недостаточно прав для совершения действия.", 1);

                $insert = '("'.$userToken['id'].'", "'.$id.'", "product", "'.time().'")';

                $tech_cards = DB::query('SELECT id
                                                FROM '.DB_TECHNICAL_CARD.'
                                                WHERE product = '.$id.'  AND (partner IS NULL OR partner = '.$userToken['id'].')');

                while($row = DB::getRow($tech_cards)){

                    $insert .= ', ("'.$userToken['id'].'", "'.$row['id'].'", "technical_card", "'.time().'")';

                }

                DB::query('INSERT INTO '.DB_ARCHIVE.' (partner_id, product_id, model, created) VALUES '.$insert);

                response('success', 'Товар успешно добален в архив.', 7);

            break;

            case 'technical_card' :

                $check = DB::query('SELECT id
                                            FROM '.DB_ARCHIVE.'
                                            WHERE product_id = '.$id.' AND partner_id = '.$userToken['id'].' AND model = "technical_card"');

                if(DB::getRecordCount($check) != 0)
                    response('error', "Вы уже добавили данную техническую карту в архив.", 1);

                $check_add = DB::query('SELECT id
                                            FROM '.DB_TECHNICAL_CARD.'
                                            WHERE id = '.$id.' AND (partner IS NULL OR partner = '.$userToken['id'].')');
                                        
                if(DB::getRecordCount($check_add) == 0)
                    response('error', "Недостаточно прав для совершения действия.", 1);

                $insert = array('partner_id' => $userToken['id'],
                    'product_id' => $id,
                    'model' => "technical_card",
                    'created' => time());

                DB::insert($insert, DB_ARCHIVE);

                response('success', 'Техническая карта успешно добалена в архив.', 7);

            break;
        }

    break;

    case 'delete' :

        exit;

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбрана позиция.', 1);

        DB::delete(DB_ARCHIVE, 'id = '.$id);

        response('success', 'Товар успешно удален.', 7);

    break;

    case 'check':

        $result = [
            'technical_cards' => [],
            'production_products' => []
        ];

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбран ингредиент.', 1);

        $archive = '
            AND tc.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "technical_card" AND partner_id = '.$userToken['id'].'
            )';

        $data = DB::query('SELECT tc.id, CONCAT(p.name, IF(tc.subname = "", "", CONCAT(" (", tc.subname, ")")), ", ", tc.bulk_value, " ", tc.bulk_untils) AS name
                                    FROM '.DB_PRODUCT_COMPOSITION.' AS pc
                                    JOIN '.DB_TECHNICAL_CARD.' AS tc ON pc.technical_card = tc.id
                                    JOIN '.DB_PRODUCTS.' AS p ON p.id = tc.product
                                    WHERE pc.item = '.$id.' AND (tc.partner = '.$userToken['id'].' OR tc.partner IS NULL)'.$archive);

        while($row = DB::getRow($data))
            $result['technical_cards'][] = $row['name'];

        $archive = '
            AND i.id NOT IN (
                SELECT product_id
                FROM '.DB_ARCHIVE.'
                WHERE model = "item" AND partner_id = '.$userToken['id'].'
            )';

        $data = DB::query('SELECT i.name
                                    FROM '.DB_PRODUCTIONS_COMPOSITION.' AS pc
                                    JOIN '.DB_ITEMS.' AS i ON i.id = pc.product
                                    WHERE pc.item = '.$id.' AND (i.partner = '.$userToken['id'].' OR i.partner IS NULL)'.$archive);

        while($row = DB::getRow($data))
            $result['production_products'][] = $row['name'];

        response('success', $result, 7);

    break;

    case 'recovery' :

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', 'Не выбрана категория.', 1);

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбран id.', 1);

        $data = DB::query('SELECT *
                                    FROM '.DB_ARCHIVE.'
                                    WHERE partner_id = '.$userToken['id'].' AND product_id = '.$id.' AND model = "'.$category.'"');

        if(!DB::getRecordCount($data))
            response('error', 'Запись не найдена.', 404);

        if($category == 'product'){

            DB::query('
                DELETE FROM '.DB_ARCHIVE.'
                WHERE (model = "technical_card" AND  product_id IN (
                    SELECT id
                    FROM '.DB_TECHNICAL_CARD.'
                    WHERE product = '.$id.'
                )) OR (model = "item" AND product_id IN (
                    SELECT pc.item
                    FROM '.DB_TECHNICAL_CARD.' tc
                    JOIN '.DB_PRODUCT_COMPOSITION.' pc ON pc.technical_card = tc.id
                    WHERE tc.product = '.$id.'
                ))
            ');

        }
        if($category == 'technical_card'){

            DB::query('
                DELETE FROM '.DB_ARCHIVE.'
                WHERE (model = "product" AND  product_id IN (
                    SELECT product
                    FROM '.DB_TECHNICAL_CARD.'
                    WHERE id = '.$id.'
                )) OR (model = "item" AND product_id IN (
                    SELECT item
                    FROM '.DB_PRODUCT_COMPOSITION.'
                    WHERE technical_card = '.$id.'
                ))
            ');

        }

        if($category == 'item'){

            $product = ['id' => $id];
            $pc = new ProductionCostPrice(false, $userToken['id']);
            $items = $pc->num_array_children($product);
            if(sizeof($items)) {
                $items = implode(',', $items);
                DB::delete(DB_ARCHIVE, 'model = "' . $category . '" AND product_id IN (' . $items . ')');
            }

        }

        DB::delete(DB_ARCHIVE, 'partner_id = '.$userToken['id'].' AND product_id = '.$id.' AND model = "'.$category.'"');

        response('success', 'Позиция восстановлена из архива.', 7);

    break;
}


