<?php

ini_set('display_errors', 'On');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
//header("Access-Control-Allow-Methods: *");
header('Content-Type: application/json;charset=utf-8');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

require __DIR__.'/vendor/autoload.php';
error_reporting(E_ERROR);

date_default_timezone_set('Europe/Moscow');

$whoops = new \Whoops\Run();
$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
$whoops->pushHandler(function ($exception, $inspector, $run) {
    @file_get_contents('https://loger.apiloc.ru/loger/cw?msg='.urlencode('CW Error -'.$exception.' Request '.json_encode($_REQUEST)));
});
$whoops->register();

$router = new \Bramus\Router\Router();

//Редирект на v2

/*$router->all('/v2/.*', function() {
   require_once('v2/index.php');
   exit;
}); */

// Require composer autoloader

use Support\DB;
use Support\mDB;
use Support\Pages;
use Support\Request;

require __DIR__.'/config/define.php';

define('ROOT', dirname(__FILE__).'/');
define('SITE_URL', 'https://'.$_SERVER['HTTP_HOST'].'/reqsys/fullsystem/cwsystem-free-main/');

$subdomain = isset($_REQUEST['subdomain']) ? $_REQUEST['subdomain'] : false;

switch ($subdomain) {
    // case 'br':
    //     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'config-by.env');
    //     break;
    // case 'kz':
    //     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'config-kz.env');
    //     break;
    // case 'dev':
    //     $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'config-dev.env');
    //     break;
    default:
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, 'config-ru.env');
        break;
}

$dotenv->load();

Request::loadRequest();

DB::connect();
mDB::connect();

Pages::init();

$params = [];
$query = DB::query('SELECT * FROM '.DB_GENERAL_INFORMATION);
while ($row = DB::getRow($query)) {
    $params[$row['param']] = $row['value'];
}
define('CURRENCY', is_null($params['currency']) ? '₽' : $params['currency']);
define('ROUND_PRICE', is_null($params['round_price']) ? 1 : $params['round_price']);

$router->set404(function () {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['status' => 'error', 'data' => ['msg' => 'This API request does not exist or it is no longer supported']]);
});

require_once 'routes/fromCW.php';
require_once 'routes/api.php';
require_once 'routes/cron.php';
require_once 'routes/loyalclient.php';

// Run it!
$router->run();
