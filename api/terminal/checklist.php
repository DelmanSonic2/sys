<?php
use Support\Pages;
use Support\DB;

include 'tokenCheck.php';

$result = [];

$check = DB::query('SELECT questions
                            FROM '.DB_CHECKLIST.'
                            WHERE FIND_IN_SET('.$pointToken['id'].', points) AND disable = 0');

while($row = DB::getRow($check)){

    $result = explode("||", $row['questions']);

}

response('success', array('questions' => $result), '7');
