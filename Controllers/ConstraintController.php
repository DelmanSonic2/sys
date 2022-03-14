<?php

namespace Controllers;

use Support\DB;
use Support\Utils;
use Rakit\Validation\Validator;
use Support\Request;

//Ограничения скидки
class ConstraintController
{

  

    public static function get()
    {

        $user = Request::authUser();


        $data = DB::makeArray(DB::select("*", "app_constraints", "`partner`=" . $user['partner']));

        foreach($data as &$val){
            
            
          
            $val['technical_cards'] =  array_diff(explode(',',$val['technical_cards']),[""]);
            
            $val['technical_cards_data'] = [];
            if(count($val['technical_cards']) > 0)
            $val['technical_cards_data'] = DB::makeArray(DB::query("SELECT t.id, CONCAT(p.`name`,' ', t.`subname`, ' ',t.`bulk_value`, t.`bulk_untils`) as name FROM `app_technical_card` t JOIN `app_products` p WHERE t.`product`=p.id AND t.id IN (".implode(',',$val['technical_cards']).")"));
        



            $val['product_categories'] =  array_diff(explode(',',$val['product_categories']),[""]);
          

            $val['product_categories_data']  = [];
            if(count($val['product_categories']) > 0)
            $val['product_categories_data'] = DB::makeArray(DB::select("id,name","app_product_categories","id IN (".implode(',',$val['product_categories']).")"));
        

            $val['points'] =   array_diff(explode(',',$val['points']),[""]); 
            

          

            $val['points_data']  = [];
            if(count($val['points']) > 0)
            $val['points_data'] = DB::makeArray(DB::select("id,name","app_partner_points","id IN (".implode(',',$val['points']).")"));
        


          
            
           
        }

        Utils::response("success", $data, 7);
    }


    public static function  update()
    {

        $user = Request::authUser();

        $validator = new Validator;


    
        $validation = $validator->make(Request::$request, [
            'id'=> 'required',
            'type'              => 'required',
            'from_val'              => 'required',
            'to_val'              => 'required',
        ]);

        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors();
            Utils::responseValidator($errors->firstOfAll());
        }

        $request = Request::$request;
        if(!isset($request['technical_cards'])) $request['technical_cards'] = [];
        if(!isset($request['product_categories'])) $request['product_categories'] = [];
        if(!isset($request['points'])) $request['points'] = [];



        DB::update([
            'technical_cards'=> implode(',', $request['technical_cards']),
            'product_categories'=> implode(',', $request['product_categories']),
            'points'=> implode(',', $request['points']),
            "type"=>$request['type'],
            "from_val"=>$request['from_val'],
            "to_val"=>$request['to_val'],
        ],'app_constraints',"id=".$request['id']." AND partner=".$user['partner']);


        Utils::response("success", [], 7);

    }


    public static function  delete()
    {

        $user = Request::authUser();

        $validator = new Validator;

        $validation = $validator->make(Request::$request, [
            'id'=> 'required',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors();
            Utils::responseValidator($errors->firstOfAll());
        }
        DB::delete('app_constraints',"id=".Request::$request['id']." AND partner=".$user['partner']);


        Utils::response("success", [], 7);

    }


    public static function  add()
    {

        $user = Request::authUser();


        if($user['partner'] != 1)  Utils::response("error", [
            "msg"=>"Вам временно недоступна функция ограничения."
        ], 3); 

        $validator = new Validator;

        
        $validation = $validator->make(Request::$request, [
            'type'              => 'required',
            'from_val'              => 'required',
            'to_val'              => 'required',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            $errors = $validation->errors();
            Utils::responseValidator($errors->firstOfAll());
        }


        $request = Request::$request;
        if(!isset($request['technical_cards'])) $request['technical_cards'] = [];
        if(!isset($request['product_categories'])) $request['product_categories'] = [];
        if(!isset($request['points'])) $request['points'] = [];


        DB::insert([
            'technical_cards'=> implode(',', $request['technical_cards']),
            'product_categories'=> implode(',', $request['product_categories']),
            'points'=> implode(',', $request['points']),
            'partner'=>$user['partner'],
            "type"=>$request['type'],
            "from_val"=>$request['from_val'],
            "to_val"=>$request['to_val']
        ],"app_constraints");

        Utils::response("success", [], 7);
       
    }
}
