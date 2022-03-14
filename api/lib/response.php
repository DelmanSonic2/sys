<?php
use Support\Pages;
use Support\DB;

function response($type, $data, $code, $pageData = null){

    

    if($data == '')
        $data = array('msg' => 'Произошла ошибка, повторите попытку позднее.');

    $data = is_array($data) ? $data : array('msg' => $data);

    $memory = memory_get_usage() - MEMORY;

    if($memory > 0){
        $kb = round((($memory) / 1024), 2).' Кб';
        $mb = round((($memory) / 1024 / 1024), 2).' Мб';
    }
    else{
        $kb = '0 Кб';
        $mb = '0 Мб';
	}
	
	if($type == 'error' && $code != 9){

		foreach($_REQUEST AS $key => $value){

			if($key == 'q')
				continue;

			if(!$request)
				$request = '?'.$key.'='.$value;
			else
				$request .= '&'.$key.'='.$value;

			if(!$params)
				$params = '"'.$key.'" : "'.$value.'"';
			else
				$params .= ', "'.$key.'" : "'.$value.'"';
		}

		$date = date('d-m-Y H:i:s', time());
		$string = $date.'	REQUEST_URI=['.$_REQUEST['q'].'] || REQUEST_METHOD=['.$_SERVER['REQUEST_METHOD'].'] || PARAMETRES={'.$params.'} || ERROR_MESSAGE={"msg" : "'.$data['msg'].'"}	||	FULL_GET_REQUEST=['.SITE_URL.$_REQUEST['q'].$request.']'.PHP_EOL;

		//сохраняем логи
		if(!file_exists('web_logs'))
			mkdir('web_logs', 0755);

		file_put_contents('web_logs/errors.log', $string, FILE_APPEND);

	}

	$response = array(
			'type'  => $type,
			'data' => $data,
            'code' => (int)$code
            );
        
  
    if($pageData)
		$response['page'] = $pageData;
		
	DB::disconnect();
        
	echo json_encode($response);
	exit;
}
