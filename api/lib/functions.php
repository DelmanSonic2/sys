<?php

use Support\Pages;
use Support\DB;
use \phpthumb;
use Support\Utils;

function SendSMS($phone, $message)
{ //Отправка SMS

    return file_get_contents("https://sms.ru/sms/send?api_id=ТУТ ДОЛЖЕН БЫТЬ ID" . $phone . "&msg=" . urlencode(iconv("windows-1251", "utf-8", $message)) . "&json=1");
}

function convertCurrency($amount, $from, $to)
{

    if (empty($_COOKIE['exchange_rate'])) {

        $req_url = 'https://api.exchangerate-api.com/v4/latest/' . $from;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $req_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $response_json = curl_exec($ch);
        curl_close($ch);

        $response_object = json_decode($response_json);

        $rate = $response_object->rates->$to;

        setcookie('exchange_rate', $rate);
    } else {

        $rate = $_COOKIE['exchange_rate'];
    }
    $output = $amount * $rate;

    return $output;
}

function UnixToDateRus($unix, $time = false)
{

    $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

    $format = ($time) ? 'd-m-Y H:i:s' : 'd-m-Y';

    $date = explode('-', date($format, $unix));

    return $date[0] . ' ' . $months[$date[1] - 1] . ' ' . $date[2];
}

function CategoriesTree($categories, $parent_id, $only_parent = false)
{

    /*

    Возвращает массив с бесконечной вложенностью.

    До передачи массива в функцию, необходимо правильно определить массив, например:

    while($row = DB::getRow($categories)){

        $row['childs'] = [];

        $result[$row['parent']][$row['id']] =  $row;
    }

    Пример вызова функции:

    $result = CategoriesTree($result, null);

    */

    $tree = [];

    if (is_array($categories) and isset($categories[$parent_id])) {
        if ($only_parent == false) {
            foreach ($categories[$parent_id] as $category) {
                if ($category['del']) {
                    $childs =  CategoriesTree($categories, $category['id']);
                    if (!sizeof($childs))
                        continue;
                    for ($i = 0; $i < sizeof($childs); $i++) {
                        if ($i == 0)
                            $category = $childs[$i];
                        else
                            $tree[] = $childs[$i];
                    }
                } else
                    $category['childs'] =  CategoriesTree($categories, $category['id']);
                $tree[] = $category;
            }
        } elseif (is_numeric($only_parent)) {
            $category = $categories[$parent_id][$only_parent];
            $category['childs'] =  CategoriesTree($categories, $category['id']);
            $tree[] = $category;
        }
    } else return [];
    return $tree;
}

function ConvertYouTubeTime($time)
{

    $hour = '';
    $minute = '';
    $seconds = '';

    $time = str_split($time);

    for ($i = 0; $i < sizeof($time); $i++) {
        if ($time[$i] == "H") {
            if ($time[$i - 2] == 'T')
                $hour = '0' . $time[$i - 1];
            else
                $hour = $time[$i - 2] . $time[$i - 1];
        }
        if ($time[$i] == 'M') {
            if ($time[$i - 2] == 'T' || $time[$i - 2] == 'H')
                $minute = '0' . $time[$i - 1];
            else
                $minute = $time[$i - 2] . $time[$i - 1];
        }
        if ($time[$i] == 'S') {
            if ($time[$i - 2] == 'M' || $time[$i - 2] == 'T')
                $seconds = '0' . $time[$i - 1];
            else
                $seconds = $time[$i - 2] . $time[$i - 1];
        }
    }

    if ($minute == '')
        $minute = '00';
    if ($seconds == '')
        $seconds = '00';

    if ($hour == '')
        return $minute . ':' . $seconds;
    else
        return $hour . ':' . $minute . ':' . $seconds;
}

function DirDelete($path)
{ //Удаление дирректории и файлов, находящихся в ней
    //Использовать с большой ОСТОРОЖНОСТЬЮ. МОЖНО УДАЛИТЬ ВСЕ ФАЙЛЫ В ПАПКЕ documents.

    $path = ROOT . 'documents/' . $path;

    if ($objs = glob($path . "/*")) {
        foreach ($objs as $obj) {
            is_dir($obj) ? DirDelete($obj) : unlink($obj);
        }
    }
    if (is_dir($path))
        rmdir($path);
}

function GetIP()
{ //Получить IP адресс
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else
        $ip = $_SERVER['REMOTE_ADDR'];

    return $ip;
}

function SendToEmail($email, $head, $message)
{ //Отправка письма на почтовый ящик


    $log = array();
    require_once("manager/includes/controls/phpmailer/PHPMailer.php");
    require_once("manager/includes/controls/phpmailer/Exception.php");
    $letter = new PHPMailer\PHPMailer\PHPMailer(true);
    $letter->CharSet = 'utf-8';
    $letter->IsHTML(true);
    $letter->From = 'service@cwsystem.ru';
    $letter->FromName = 'service@cwsystem.ru';
    $letter->Subject = $head; //Заголовок сообщения

    $letter->Body = $message;
    $letter->AddAddress($email);

    if (!$letter->Send())
        return 'error';
    return 'success';
}

function Push($users, $table, $head, $message, $type, $object)
{ //Отправка PUSH уведомлений


    $NORD_IMAGE = SITE_URL . 'assets/images/photo_2018-12-21_11-54-39.jpg';

    $push_token = DB::select('push_token', $table, 'user = ' . $users . ' AND push_token != ""');

    while ($row = DB::getRow($push_token))
        $ids[] = $row['push_token'];

    if (sizeof($ids) == 0)
        return;

    $content = array('en' => $message);
    $heading = array('en' => $head);

    $fields = array(
        'app_id' => "38a6868c-841d-4ca4-9f98-2d0bfa418851", //<------- ID приложения
        'successful' => 15,
        'failed' => 1,
        'include_player_ids' => $ids,
        'converted' => 3,
        'remaining' => 0,
        'queued_at' => 1415914655,
        'send_after' => 1415914655,
        'data' => array('msg' => 'Сообщение передано.'),
        'contents' => $content,
        'heading' => $heading,
        'subtitle' => $heading,
        'data' => array(
            'type' => $type,
            'id' => $object
        ),
        'big_picture' => $NORD_IMAGE,
        'adm_big_picture' => $NORD_IMAGE,
        'chrome_big_picture' => $NORD_IMAGE
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic MmY5Y2JlZGYtN2M3MS00MDU3LWE0ZWItZjQ2MmNlMjYyY2Jk'
    )); //<-------Ключ
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $response = curl_exec($ch);
    curl_close($ch);

    //response('success', $response, '7');
}

function PushAll($head, $message, $type, $object)
{



    $NORD_IMAGE = SITE_URL . 'assets/images/photo_2018-12-21_11-54-39.jpg';

    $content = array('en' => $message);
    $heading = array('en' => $head);

    $fields = array(
        'app_id' => "38a6868c-841d-4ca4-9f98-2d0bfa418851", //<------- ID приложения
        'successful' => 15,
        'failed' => 1,
        'included_segments' => ["Active Users", "Inactive Users"],
        'converted' => 3,
        'remaining' => 0,
        'queued_at' => 1415914655,
        'send_after' => 1415914655,
        'data' => array('msg' => 'Сообщение передано.'),
        'contents' => $content,
        'heading' => $heading,
        'subtitle' => $heading,
        'data' => array(
            'type' => $type,
            'id' => $object
        ),
        'big_picture' => $NORD_IMAGE,
        'adm_big_picture' => $NORD_IMAGE,
        'chrome_big_picture' => $NORD_IMAGE
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic MmY5Y2JlZGYtN2M3MS00MDU3LWE0ZWItZjQ2MmNlMjYyY2Jk'
    )); //<-------Ключ
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $response = curl_exec($ch);
    curl_close($ch);
}

function Base64load($fulldir, $file, $filename)
{ //Загрузка картинки BASE64 на сервер и получение информации о ней
    //filename передаём без расширения
    /*Пример вызова:
    $msg = Base64load('TEST/USER', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'апельсин');*/


    $link = 'documents/' . $fulldir; //Дублируем введённую ссылку, и добавляем к пути папку documents. Это нужно для того, чтобы сформировать конечную ссылку.

    $fulldir = explode('/', $fulldir);
    $dir = ROOT . 'documents/'; //Создаём папку, в которой будут храниться все фотографии, картинки, музыка и остальное.

    if (!file_exists($dir))
        mkdir($dir, 0755);

    for ($i = 0; $i < sizeof($fulldir); $i++) {
        $dir .= $fulldir[$i] . '/';
        if (!file_exists($dir))
            mkdir($dir, 0755);
    }

    $name = $filename . '.jpg';

    $file = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file));

    file_put_contents($dir . $name, $file);

    $size = getimagesize($dir . $name);

    $width = $size[0];
    $height = $size[1];
    $size = filesize($predir . $dir . $name);

    $array = array(
        'width' => $width,
        'height' => $height,
        'size' => $size,
        'type' => 'jpg',
        'name' => $filename,
        'fullname' => $name,
        'link' => $link . '/' . $name
    );

    return $array;

    /*Пример ответа:
    {
        width: 1,
        height: 1,
        size: 42,
        type: "jpg",
        name: "апельсин",
        fullname: "апельсин.jpg",
        link: "documents/TEST/USER/апельсин.jpg"
    }*/
}

function FileLoad($fulldir, $file, $name, $types = [])
{ //Загрузка файла на сервер и получение информации о нём
    //$name - необязательный параметр. Если его не передать, то файл сохранится со своим именем.
    /*Пример вызова:
    $msg = FileLoad('TEST2/FILES', $_FILES['upload'], 'Запрет на импорт табака');*/


    if ($file['size'] == 0)
        response('error', array('msg' => 'Не удалось загрузить файл.'), '13');
    $link = 'documents/' . $fulldir; //Дублируем введённую ссылку, и добавляем к пути папку documents. Это нужно для того, чтобы сформировать конечную ссылку.


    $filename = explode('.', $file['name']);
    $type = $filename[sizeof($filename) - 1];

    $flag = false;

    if (sizeof($types) > 0) {
        for ($i = 0; $i < sizeof($types); $i++) {
            if ($type == $types[$i])
                $flag = true;
        }
        if (!$flag)
            response('error', array('msg' => 'Неподдерживаемое расширение.'), '14');
    }

    $path_to_save = ROOT . 'storage/tmp/' . md5(time() . rand(1111, 9999)) . '.' . $type;

    if (move_uploaded_file($file['tmp_name'], $path_to_save)) {


        $phpThumb = new phpthumb();
        $phpThumb->setSourceFilename($path_to_save);

        $phpThumb->setParameter("w", 300);
        $phpThumb->setParameter("h", 300);
        $phpThumb->setParameter("zc", 'C');
        $phpThumb->setParameter("f", 'jpeg');
        $phpThumb->setParameter("q", '70');


        if ($phpThumb->GenerateThumbnail()) {
            $phpThumb->RenderToFile($path_to_save);
          
            $url = Utils::saveToS3($path_to_save, md5($link) . '.jpg');
            unlink($path_to_save);



            $array = array(
                'link' => $url
            );
        } else {
            $array = $file['error'];
        }
    } else {
        $array = $file['error'];
    }

    return $array;

    /*Пример ответа:
    {
        "size":223,
        "type":"html",
        "originalName":"form",
        "newName":"Запрет на импорт табака",
        "link":"documents/TEST2/FILES/Запрет на импорт табака.html"
    }*/
}

function ImageResize($link, $w, $h)
{

    return $link;

    /*
    
    if(!file_exists(ROOT.'cache'))
    mkdir(ROOT.'cache',0777);

    if(!file_exists(ROOT.'cache/images'))
    mkdir(ROOT.'cache/images',0777);

    

    if(!file_exists(ROOT.$link)){
        $link = 'http://cwsystem.ru/'.$link;
    }else{
        $link = ROOT.$link;
    }


    $name = md5($link).'.jpeg';

    if(file_exists(ROOT.'cache/images/'.$name)) return 'cache/images/'.$name;

    $phpThumb = new phpthumb();

  

    $phpThumb->setSourceFilename($link);
    
    
    $phpThumb->setParameter("w", $w);
    $phpThumb->setParameter("h", $h);
    $phpThumb->setParameter("zc", 'C');
    $phpThumb->setParameter("f", 'jpeg');
    $phpThumb->setParameter("q", '70');


    if ($phpThumb->GenerateThumbnail()) {
        $phpThumb->RenderToFile(ROOT.'cache/images/'.$name);
    }

   

    return  'cache/images/'.$name;*/
}

function translit($s)
{ //Меняем строку с русскими буквами на транслит
    $s = (string) $s;
    $s = strip_tags($s); // убираем HTML-теги
    $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
    $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
    $s = trim($s); // убираем пробелы в начале и конце строки
    $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр
    $s = strtr($s, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
    $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s);
    $s = str_replace(" ", "-", $s); // заменяем пробелы знаком тире
    return $s;
}
