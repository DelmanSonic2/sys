<?php



function LC_Redirect(){

    $body_json = json_decode(file_get_contents('php://input'), true);

    $request = $_REQUEST;
    if ($body_json != null) {
        $request = array_merge($_REQUEST, $body_json);
    }
    
    
                 $url = 'https://loyalclient.apiloc.ru'. explode('/loyalclient',$_SERVER['REDIRECT_URL'])[1];
               
                 
            
    
                $options = array(
                    'http' => array(
                        'method'  => $_SERVER['REQUEST_METHOD'],
                        'content' => http_build_query($request)
                    )
                );
                $context  = stream_context_create($options);
                echo file_get_contents($url, false, $context);
                
             
         exit;
}

//Редирект 
$router->mount('/loyalclient/*', function () use ($router) {


    $router->all('/integration/{param1}/{param2}', function () { LC_Redirect(); });
 
});
