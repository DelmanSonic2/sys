<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

require ROOT.'api/classes/OrderClass.php';

function f_rand($min=0,$max=1,$mul=1000000){
    if ($min>$max) return false;
    return mt_rand($min*$mul,$max*$mul)/$mul;
}
switch($action){
    
    case 'add':

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите название категории.'), '311');

        $category = DB::select('id, partner', DB_ITEMS_CATEGORY, 'name = "'.$name.'"');

        if(DB::getRecordCount($category) != 0){

            $category = DB::getRow($category);

            if($category['partner'] == null)
                response('error', array('msg' => 'Такая категория уже существует в общем списке категорий.'), '312');

            if($category['partner'] == $userToken['id'])
                response('error', array('msg' => 'Такая категория уже существует в Вашем списке.'), '313');

        }

        $fields = array('name' => $name,
                        'partner' => $userToken['id']);

        if(DB::insert($fields, DB_ITEMS_CATEGORY))
            response('success', array('msg' => 'Категория добавлена.'), '602');
        else
            response('error', '', '503');

    break;

    case 'get':

        $full = DB::escape($_REQUEST['full']);

        $result = [];

        if($full == 'true'){ // Получение списка категорий с подробной информацией

            $ORDER_BY = Order::ingredient_categories(Pages::$field, Pages::$order);

            $categories = DB::query('
                SELECT ic.id, ic.name, ic.partner AS my, COUNT(DISTINCT i.id) AS ingredient_count, SUM(pi.count) AS count, i.untils, SUM(pi.count * pi.price) AS stock_balance_sum,
                    SUM(IF(i.untils = "шт", pi.count, 0)) AS count_num,
                    SUM(IF(i.untils = "кг", pi.count, 0)) AS count_weight,
                    SUM(IF(i.untils = "л", pi.count, 0)) AS count_vol
                FROM '.DB_ITEMS_CATEGORY.' ic
                LEFT JOIN '.DB_ITEMS.' i ON i.category = ic.id AND (i.partner = '.$userToken['id'].' OR i.partner IS NULL) AND i.del = 0
                LEFT JOIN '.DB_POINT_ITEMS.' pi ON pi.item = i.id AND pi.partner = '.$userToken['id'].'
                WHERE ic.partner = '.$userToken['id'].' OR ic.partner IS NULL
                GROUP BY ic.id
                '.$ORDER_BY.'
            ');

            while($row = DB::getRow($categories)){

                $row['my'] = ($row['my'] == null && !$userToken['admin']) ? false : true;
                
                $count = [];

                if($row['count_num'])
                    $count[] = number_format($row['count_num'], 3, ',', ' ').' шт';
                if($row['count_weight'])
                    $count[] = number_format($row['count_weight'], 3, ',', ' ').' кг';
                if($row['count_vol'])
                    $count[] = number_format($row['count_vol'], 3, ',', ' ').' л';

                $row['stock_balance'] = implode(', ', $count);

                $row['stock_balance_sum'] = number_format($row['stock_balance_sum'], 2, ',', ' ').' '.CURRENCY;

                $result[] = $row;
            }

            response('success', $result, '7');

        }
        else{ // Получение списка категорий с краткой информацией

            $categories = DB::select('id, name', DB_ITEMS_CATEGORY, 'partner = '.$userToken['id'].' OR partner IS NULL', 'name ASC');

            while($row = DB::getRow($categories))
                $result[] = $row;

            response('success', $result, '7');

        }

    break;

    case 'delete':
        
        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Выберите категорию.'), '320');

        $categoryData = DB::select('*', DB_ITEMS_CATEGORY, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $categoryData = DB::getRow($categoryData);

        $editing_allowed = ($categoryData['partner'] == null && !$userToken['admin']) ? false : true;

        if(!$editing_allowed)
            response('error', array('msg' => 'Вы не можете удалить общую категорию.'), '322');

        if(DB::delete(DB_ITEMS_CATEGORY, 'id = '.$category))
            response('success', array('msg' => 'Категория удалена, все товары с данной категорией теперь не сгруппированы.'), '605');
        else
            response('error', '', '503');

    break;

    case 'edit':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Выберите категорию.'), '320');

        $categoryData = DB::select('*', DB_ITEMS_CATEGORY, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');
    
        $categoryData = DB::getRow($categoryData);

        $editing_allowed = ($categoryData['partner'] == null && !$userToken['admin']) ? false : true;

        if(!$editing_allowed)
            response('error', array('msg' => 'Вы не можете редактировать общую категорию.'), '323');

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите название категории.'), '311');

        $categorySecond = DB::select('id, partner', DB_ITEMS_CATEGORY, 'name = "'.$name.'" AND id != '.$category);

        if(DB::getRecordCount($categorySecond) != 0){

            $categorySecond = DB::getRow($categorySecond);

            if($categorySecond['partner'] == null)
                response('error', array('msg' => 'Такая категория уже существует в общем списке категорий.'), '312');

            if($categorySecond['partner'] == $userToken['id'])
                response('error', array('msg' => 'Такая категория уже существует в Вашем списке.'), '313');

        }

        if(DB::update(array('name' => $name), DB_ITEMS_CATEGORY, 'id = '.$category))
            response('success', array('msg' => 'Информация о категории изменена.'), '604');
        else
            response('error', '', '503');

    break;

    case 'info':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Выберите категорию.'), '320');

        $categoryData = DB::select('id, name', DB_ITEMS_CATEGORY, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $categoryData = DB::getRow($categoryData);

        response('success', $categoryData, '7');

    break;

}