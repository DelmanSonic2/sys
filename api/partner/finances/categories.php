<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
include ROOT.'api/lib/functions.php';

switch($action){

    case 'add':

        $income = DB::escape($_REQUEST['income']);
        $costs = DB::escape($_REQUEST['costs']);

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите название категории.'), '311');

        $categoryData = DB::select('id, partner', DB_FINANCES_CATEGORIES, 'name = "'.$name.'" AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) != 0){

            $categoryData = DB::getRow($categoryData);

            if($categoryData['partner'] == $userToken['id'])
                response('error', array('msg' => 'Такая категория уже существует в Вашем списке.'), '313');
            else
                response('error', array('msg' => 'Такая категория уже существует в общем списке категорий.'), '312');

        }

        $fields = array('name' => $name,
                        'partner' => $userToken['id']);

        if($parent = DB::escape($_REQUEST['parent'])){

            $categoryData = DB::select('id', DB_FINANCES_CATEGORIES, 'id = "'.$parent.'" AND (partner = '.$userToken['id'].' OR partner IS NULL)');

            if(DB::getRecordCount($categoryData) == 0)
                response('error', array('msg' => 'Такой категории не существует.'), '321');

            $fields['parent'] = $parent;

        }

        if($income == true)
            $fields['income'] = true;
        
        if($costs == true)
            $fields['costs'] = true;

        if(!DB::insert($fields, DB_FINANCES_CATEGORIES))
            response('error', '', '503');
        else
            response('success', array('msg' => 'Категория добавлена.'), '602');


    break;

    case 'get':

        $result = [];

        $categories = DB::select('*', DB_FINANCES_CATEGORIES, 'partner = '.$userToken['id'].' OR partner IS NULL');

        while($row = DB::getRow($categories)){

            $row['my'] = ($row['partner'] == null) ? false : true;
            $row['costs'] = ($row['costs'] == 1) ? true : false;
            $row['income'] = ($row['income'] == 1) ? true : false;

            $row['childs'] = [];
    
            $result[$row['parent']][$row['id']] =  $row;
        }
    
        $result = CategoriesTree($result, null);

        response('success', $result, '7');

    break;

    case 'edit':

        $fields = [];

        $income = DB::escape($_REQUEST['income']);
        $costs = DB::escape($_REQUEST['costs']);

        if($name = DB::escape($_REQUEST['name']))
            $fields['name'] = $name;

        if($parent = DB::escape($_REQUEST['parent'])){
            $categoryData = DB::select('id', DB_FINANCES_CATEGORIES, 'id = "'.$parent.'" AND (partner = '.$userToken['id'].' OR partner IS NULL)');

            if(DB::getRecordCount($categoryData) == 0)
                response('error', array('msg' => 'Такой категории не существует.'), '321');

            $fields['parent'] = $parent;
        }

        if($income == 'true')
            $fields['income'] = 1;
        
        if($costs == 'true')
            $fields['costs'] = 1;

        if($income == 'false')
            $fields['income'] = 0;
        
        if($costs == 'false')
            $fields['costs'] = 0;

        if(sizeof($fields) == 0)
            response('success', array('msg' => 'Информация о категории обновлена.'), '609');

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Выберите категорию.'), '398');

        if($parent == $category)
            response('error', array('msg' => 'Вы не можете вложить категорию саму в себя.'), '561');

        $categoryData = DB::select('id, partner', DB_FINANCES_CATEGORIES, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $categoryData = DB::getRow($categoryData);

        if($categoryData['partner'] != $userToken['id'])
            response('error', array('msg' => 'Вы не можете редактировать общую категорию.'), '323');

        if(DB::update($fields, DB_FINANCES_CATEGORIES, 'id = '.$category))
            response('success', array('msg' => 'Информация о категории обновлена.'), '609');
        else
            response('error', '', '503');

    break;

    case 'delete':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Выберите категорию.'), '398');

        $categoryData = DB::select('id, partner', DB_FINANCES_CATEGORIES, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $categoryData = DB::getRow($categoryData);

        if($categoryData['partner'] != $userToken['id'])
            response('error', array('msg' => 'Вы не можете удалить общую категорию.'), '322');

        if(DB::delete(DB_FINANCES_CATEGORIES, 'id = '.$category))
            response('success', array('msg' => 'Категория удалена, все товары с данной категорией теперь не сгруппированы.'), '605');
        else
            response('error', '', '503');

    break;

    case 'info':

        if(!$category = DB::escape($_REQUEST['category']))
            response('error', array('msg' => 'Выберите категорию.'), '398');

        $categoryData = DB::select('*', DB_FINANCES_CATEGORIES, 'id = '.$category.' AND (partner = '.$userToken['id'].' OR partner IS NULL)');

        if(DB::getRecordCount($categoryData) == 0)
            response('error', array('msg' => 'Такой категории не существует.'), '321');

        $categoryData = DB::getRow($categoryData);
        $categoryData['income'] = ($categoryData['income'] == 0) ? false : true;
        $categoryData['costs'] = ($categoryData['costs'] == 0) ? false : true;

        response('success', $categoryData, '7');

    break;

}