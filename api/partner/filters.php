<?php

use Support\Pages;
use Support\DB;


include ROOT . 'api/lib/response.php';
require ROOT . 'api/classes/FilterClass.php';

if (!$section = DB::escape($_REQUEST['section']))
    response('error', 'Выберите раздел.', 1);

if (!class_exists($section))
    response('error', 'Данный раздел ещё не добавлен.', 1);

if (!$subsection = DB::escape($_REQUEST['subsection']))
    response('error', 'Выберите подраздел.', 1);

$class = new $section();

if (!method_exists($section, $subsection))
    response('error', 'Данный подраздел ещё не добавлен.', 1);

$filters = $class->$subsection()->get();

response('success', $filters, 7);
