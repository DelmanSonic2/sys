<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

$documents_arr = ['supply', 'moving', 'removal'];

switch($action){

    case 'set':

        if(!$id = DB::escape($_REQUEST['id']))
            response('error', 'Не выбран документ.', 422);

        if((!$document = DB::escape($_REQUEST['document'])) || !in_array($document, $documents_arr))
            response('error', 'Не выбран документ.', 422);

        $table = ($document == 'removal') ? DB_REMOVALS : DB_SUPPLIES;

        if((!$status = DB::escape($_REQUEST['status'])) || $status < 2 || $status > 4)
            response('error', 'Не указан статус.', 422);

        $document_data = DB::select('id', $table, 'id = '.$id.' AND partner = '.$userToken['id'], '', 1);

        if(DB::getRecordCount($document_data) == 0)
            response('error', 'Документ не найден.', 422);

        DB::update(array('status' => $status), $table, 'id = '.$id);

        response('success', 'Статус обновлен.', 201);

    break;

}