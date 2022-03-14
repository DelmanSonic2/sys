<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

switch($action){

    case 'change':

        if(!$old_password = DB::escape($_REQUEST['old_password']))
            response('error', array('msg' => 'Введите текущий пароль.'), 594);

        $partner_data = DB::select('id, password', DB_PARTNER, 'id = '.$userToken['id']);

        if(DB::getRecordCount($partner_data) == 0)
            response('error', array('msg' => 'Партнер не найден.'), 596);

        $partner_data = DB::getRow($partner_data);

        if(!password_verify($old_password, $partner_data['password']))
            response('error', array('msg' => 'Неверный пароль.'), 597);

        if(!$new_password = DB::escape($_REQUEST['new_password']))
            response('error', array('msg' => 'Введите новый пароль.'), 595);

        if(strlen($new_password) < 8)
            response('error', array('msg' => 'Пароль не может содержать меньше 8 символов.'), '303');

        $pattern = "/[\\\~^°\"\/`';,\.:_{\[\]}\|<>]/";
 
        if(preg_match($pattern, $new_password, $matches))
            response('error', array('msg' => 'В пароле присутствуют недопустимые символы: "'.$matches[0].'".'), '304');

        if($new_password == $old_password)
            response('error', array('msg' => 'Новый пароль не должен совпадать с текущим.'), 598);

        DB::update(array('password' => password_hash($new_password, PASSWORD_DEFAULT)), DB_PARTNER, 'id = '.$userToken['id']);

        response('success', array('msg' => 'Пароль изменен.'), 644);

    break;

}