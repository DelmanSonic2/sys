<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

function ItemsValidate($items, $user, $point){

    

    $items = stripcslashes($items);
    $items = json_decode($items, true);

    if($items == null || sizeof($items) == 0)
        response('error', array('msg' => 'Объект с ингредиентами имеет неверный формат.'), '354');

    for($i = 0; $i < sizeof($items); $i++){

        if(!$items[$i]['id'] || !$items[$i]['count'])
            response('error', array('msg' => 'Неверное содержание объекта ингредиентов.'), '355');// В массиве у каждого объекта должен быть id

        if(!$itemsWhere)
            $itemsWhere = '(p.id = '.$items[$i]['id'].' AND p.count >= '.$items[$i]['count'].')';
        else
            $itemsWhere .= ' OR (p.id = '.$items[$i]['id'].' AND p.count >= '.$items[$i]['count'].')';

    }
    
    $QueryItems = DB::query('SELECT p.id, p.item, p.count, p.price, i.bulk, i.untils
                                    FROM '.DB_POINT_ITEMS.' p
                                    JOIN '.DB_ITEMS.' AS i ON i.id = p.item
                                    WHERE ('.$itemsWhere.') AND p.partner = '.$user.' AND p.point = '.$point.'
                                    GROUP BY p.id');
                                    // Проверяем, есть ли ингредиенты на складе
    
    if(DB::getRecordCount($QueryItems) != sizeof($items))
        response('error', array('msg' => 'Одна или более позиций отсутствуют на складе.'), '390');// Если запрашиваемой и полученное количество ингредиентов не сходится, то ошибка

    while($row = DB::getRow($QueryItems)){

        for($i = 0; $i < sizeof($items); $i++){

            if($items[$i]['id'] == $row['id']){
                $items[$i]['price'] = $row['price'];
                $items[$i]['bulk'] = $row['bulk'];
                $items[$i]['item'] = $row['item'];
                $items[$i]['untils'] = $row['untils'];
                if($row['untils'] != 'шт')
                    $items[$i]['sum'] = $items[$i]['count'] / $row['bulk'] * $row['price'];
                else
                    $items[$i]['sum'] = $items[$i]['count'] * $row['price'];
            }

        }

    }

    return $items;

}

function RemovalItems($items, $removal, $point){

    

    $sum = 0;

    for($i = 0; $i < sizeof($items); $i++){

        if(!$itemsStr)
            $itemsStr = '("'.$removal.'", "'.$items[$i]['item'].'", "'.$items[$i]['count'].'", "'.$items[$i]['untils'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['comment'].'", "'.$point.'")';
        else
            $itemsStr .= ', ("'.$removal.'", "'.$items[$i]['item'].'", "'.$items[$i]['count'].'", "'.$items[$i]['untils'].'", "'.$items[$i]['price'].'", "'.$items[$i]['sum'].'", "'.$items[$i]['comment'].'", "'.$point.'")';

        $sum += $items[$i]['sum'];

    }

    if(!DB::query('INSERT INTO '.DB_REMOVAL_ITEMS.' (removal, item, bulk_value, bulk_untils, price, sum, comment, point) VALUES '.$itemsStr))
        response('error', '', '503');

    return $sum;

}

switch($action){

    case 'create':

        $fields = [];

        $fields['date'] = (DB::escape($_REQUEST['date'])) ? DB::escape($_REQUEST['date']) : time();
        $fields['created'] = time();

        $pointData = DB::select('id', DB_PARTNER_POINTS, 'partner = '.$pointToken['partner'].' AND id = '.$pointToken['partner']);
        if(DB::getRecordCount($pointData) == 0)
            response('error', array('msg' => 'Заведение с таким ID не найдено.'), '361');

        $fields['point'] = $pointToken['id'];
        $fields['partner'] = $userToken['id'];
        if($userToken['employee_id'])
            $fields['employee'] = $userToken['employee_id'];

        if(!$items = DB::escape($_REQUEST['items']))
            response('error', array('msg' => 'Не передан объект с ингредиентами.'), '353');

        $removal = 0;

        $items = ItemsValidate($items, $pointToken['partner'], $pointToken['id']);

        if(!$cause = DB::escape($_REQUEST['cause']))
                response('error', array('msg' => 'Не выбрана причина списания.'), '391');

        $new_cause = DB::escape($_REQUEST['new_cause']);

        //Если новая причина, то добавляем в таблицу
        if($new_cause === 'true')
            $cause = DB::insert(array('partner' => $userToken['id'], 'name' => $cause), DB_REMOVAL_CAUSES);
        else{
        //Иначе проверяем, существует ли такая причина

            $causeData = DB::select('id', DB_REMOVAL_CAUSES, 'id = '.$cause.' AND partner = '.$userToken['id']);

            if(DB::getRecordCount($causeData) == 0)
                response('error', array('msg' => 'Причина не найдена.'), '392');

        }

        $fields['cause'] = $cause;

        $removal = DB::insert($fields, DB_REMOVALS);

        $sum = RemovalItems($items, $removal, $pointToken['id']);

        DB::update(array('total_sum' => $sum), DB_REMOVALS, 'id = '.$removal);

        response('success', array('msg' => 'Списание создано.'), '629');

    break;

    case 'items':

        $result = [];

        $items = DB::query('SELECT p.id, i.name, i.untils, p.price, p.count
                                    FROM '.DB_POINT_ITEMS.' p
                                    JOIN '.DB_ITEMS.' AS i ON i.id = p.item
                                    WHERE p.partner = '.$pointToken['partner'].' AND p.point = '.$pointToken['id'].' AND p.count > 0
                                    ORDER BY i.name');

        while($row = DB::getRow($items))
            $result[] = $row;

        response('success', $result, '7');

    break;

}