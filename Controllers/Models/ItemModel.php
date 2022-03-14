<?php

namespace  Controllers\Models;

use GraphQL\Error\Error;
use Support\Auth;
use Support\DB;
use Support\mDB;

//Модель для mDM
class ItemModel
{

    public static function add($fields)
    {


        if (isset($fields['id'])) $fields['id'] = (int)($fields['id']);

        if (isset($fields['name'])) $fields['name'] = trim($fields['name']);
        else $fields['name'] = "";


        if (isset($fields['untils'])) $fields['untils'] = trim($fields['untils']);
        else $fields['untils'] = "";

        if (isset($fields['bulk'])) $fields['bulk'] = (float)$fields['bulk'];
        else $fields['bulk'] = 0;



        if (isset($fields['category'])) $fields['category'] = (int)($fields['category']);
        else $fields['category'] = 0;

        if (isset($fields['product_category'])) $fields['product_category'] = (int)($fields['product_category']);
        else $fields['product_category'] = 0;

        $fields['partner'] = (int)Auth::$user['id'];
        $fields['country'] = Auth::$country;

        if (isset($fields['conversion_item_id'])) $fields['conversion_item_id'] = (int)($fields['conversion_item_id']);
        else $fields['conversion_item_id'] = 0;

        if (isset($fields['production'])) $fields['production'] = is_numeric($fields['production']) ? ($fields['production'] == 1 ? true : false) : $fields['production'];
        else $fields['production'] = false;

        if (isset($fields['round'])) $fields['round'] = is_numeric($fields['round']) ? ($fields['round'] == 1 ? true : false) : $fields['round'];
        else $fields['round'] = false;

        if (isset($fields['print_name'])) $fields['print_name'] = trim($fields['print_name']);
        else $fields['print_name'] = "";

        if (isset($fields['composition_description'])) $fields['composition_description'] = trim($fields['composition_description']);
        else $fields['composition_description'] = "";

        if (isset($fields['energy_value'])) $fields['energy_value'] = trim($fields['energy_value']);
        else $fields['energy_value'] = "";

        if (isset($fields['nutrients'])) $fields['nutrients'] = trim($fields['nutrients']);
        else $fields['nutrients'] = "";

        if (isset($fields['shelf_life'])) $fields['shelf_life'] = trim($fields['shelf_life']);
        else $fields['shelf_life'] = "";


        $fields['enableForAll'] = false;
        $fields['canEdit'] = [];
        $fields['showFor'] = [];
        $fields['hideFor'] = [];
        $fields['archive'] = [];

        mDB::collection("items")->insertOne($fields);
    }


    public static function update($id, $fields)
    {



        if (isset($fields['name'])) $fields['name'] = trim($fields['name']);


        if (isset($fields['untils'])) $fields['untils'] = trim($fields['untils']);

        if (isset($fields['bulk'])) $fields['bulk'] = (float)$fields['bulk'];



        if (isset($fields['category'])) $fields['category'] = (int)($fields['category']);

        if (isset($fields['product_category'])) $fields['product_category'] = (int)($fields['product_category']);

        if (isset($fields['conversion_item_id'])) $fields['conversion_item_id'] = (int)($fields['conversion_item_id']);

        if (isset($fields['production'])) $fields['production'] = is_numeric($fields['production']) ? ($fields['production'] == 1 ? true : false) : $fields['production'];

        if (isset($fields['round'])) $fields['round'] = is_numeric($fields['round']) ? ($fields['round'] == 1 ? true : false) : $fields['round'];

        if (isset($fields['print_name'])) $fields['print_name'] = trim($fields['print_name']);

        if (isset($fields['composition_description'])) $fields['composition_description'] = trim($fields['composition_description']);

        if (isset($fields['energy_value'])) $fields['energy_value'] = trim($fields['energy_value']);

        if (isset($fields['nutrients'])) $fields['nutrients'] = trim($fields['nutrients']);

        if (isset($fields['shelf_life'])) $fields['shelf_life'] = trim($fields['shelf_life']);


        mDB::collection("items")->updateOne([
            'id' => (int)$id,
            'country' => Auth::$country
        ], ['$set' => $fields]);
    }
}