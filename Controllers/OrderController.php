<?php

namespace Controllers;

use Support\DB;
use Support\Utils;
use Rakit\Validation\Validator;
use Support\Request;

//Кассовый модуль
//Чать логики в старом коде
class OrderController{

public static function changeStatus(){

    $user = Request::authUser();

    
    $validator = new Validator;

    $validation = $validator->make(Request::$request, [
        'id'=> 'required',
        'status'    => 'required'
    ]);
   
     $validation->validate();

    if ($validation->fails()) {
        $errors = $validation->errors();
        Utils::responseValidator($errors->firstOfAll());
    }
    DB::update([
        'status'=> Request::$request['status'],
    ],'app_partner_orders_spending',"id=".Request::$request['id']);


    Utils::response("success", [], 7);




}



}



?>