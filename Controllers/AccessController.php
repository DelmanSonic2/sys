<?php

namespace Controllers;

use Support\DB;
use Support\Utils;
use Rakit\Validation\Validator;
use Support\mDB;
use Support\Request;

//Доступы сотрудниокв
class AccessController
{


    public static function collecting()
    {

        // $user = Request::authUser();

        //  if (!$user)   Utils::response("error","Не авторизован",0);


        $validator = new Validator;

        $validation = $validator->make(Request::$request, [
            'url' => 'required',
            'title'   => 'required'
        ]);

        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors();
            Utils::responseValidator($errors->firstOfAll());
        }

        $url =  Request::$request['url']; //implode(array_slice(array_diff(explode("/",Request::$request['url']),['']),0,2).'/');

        $private_section = mDB::collection("private_sections")->findOne([
            "url" => $url
        ]);

        if (!isset($private_section->_id)) {
            mDB::collection("private_sections")->insertOne([
                "url" => $url,
                "title" => Request::$request['title'],
            ]);
        }


        Utils::response("success", "", 7);
    }
}