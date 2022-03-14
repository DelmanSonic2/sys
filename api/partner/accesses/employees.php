<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';
require_once ROOT.'api/classes/ShiftClass.php';
require ROOT.'api/classes/OrderClass.php';

switch($action){

    case 'add':

        $fields = [];

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите имя сотрудника.'), '378');

        $fields['name'] = $name;

        $fields['email'] = DB::escape($_REQUEST['email']);

        $loginEmployee = DB::select('id', DB_EMPLOYEES, 'email = "'.$fields['email'].'"', '', 1);
        $loginPartner = DB::select('id', DB_PARTNER, 'login = "'.$fields['email'].'"', '', 1);

        if((DB::getRecordCount($loginEmployee) != 0 || DB::getRecordCount($loginPartner) != 0) && $fields['email'] != "")
            response('error', 'Логин/email "'.$fields['email'].'" уже используется.', 1);

        if(!$position = DB::escape($_REQUEST['position']))
            response('error', array('msg' => 'Выберите должность.'), '384');

        $positionData = DB::select('id, statistics, finance, menu, warehouse, marketing, terminal', DB_POSITIONS, 'id = '.$position.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($positionData) == 0)
            response('error', array('msg' => 'Должность не найдена.'), '377');

        $positionData = DB::getRow($positionData);

        //Если у должности есть доступ хотя бы к одному из пунктов админ панели, то необходимо запросить пароль для доступа к этой панели
        if($positionData['statistics'] == 1 || $positionData['finance'] == 1 || $positionData['menu'] == 1 || $positionData['warehouse'] == 1 || $positionData['marketing'] == 1){
            if(!$password = DB::escape($_REQUEST['password']))
                response('error', array('msg' => 'Введите пароль для доступа к админ-панели.'), '380');

            if(strlen($password) < 8)
                response('error', array('msg' => 'Пароль не может содержать меньше 8 символов.'), '303');

            $pattern = "/[\\\~^°\"\/`';,\.:_{\[\]}\|<>]/";
    
            if(preg_match($pattern, $password, $matches))
                response('error', array('msg' => 'В пароле присутствуют недопустимые символы: "'.$matches[0].'".'), '304');

            $fields['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        //Если у должности есть доступ к терминалу
        if($positionData['terminal'] == 1 || DB::escape($_REQUEST['pin_code'])){
            if(!$pin_code = DB::escape($_REQUEST['pin_code']))
                response('error', array('msg' => 'Введите ПИН-код для доступа к терминалу'), '381');

            if(!ctype_digit($pin_code))
                response('error', array('msg' => 'ПИН-код должен состоять только из цифр.'), '382');

            if(strlen($pin_code) != 6)
                response('error', array('msg' => 'ПИН-код должен состоять из 6 символов'), '383');

            $pin_code_repeat = DB::select('id', DB_EMPLOYEES, 'pin_code = '.$pin_code.' AND partner = '.$userToken['id']);
            if(DB::getRecordCount($pin_code_repeat) != 0)
                response('error', array('msg' => 'Такой ПИН-код уже используется.'), '385');

            $fields['pin_code'] = $pin_code;
        }

        $fields['position'] = $position;
        $fields['partner'] = $userToken['id'];

        $fields['employed'] = (DB::escape($_REQUEST['employed'])) ? 1 : 0;

        if(DB::insert($fields, DB_EMPLOYEES))
            response('success', array('msg' => 'Сотрудник добавлен.'), '624');
        else
            response('error', '', '503');

    break;

    case 'get':

        $result = [];

        $ORDER_BY = Order::accesses_employees($field, $order);

        $employees = DB::query('SELECT e.id, e.name, e.email, e.pin_code, e.last_enter, p.id AS pid, p.name AS pname, e.employed
                                        FROM '.DB_EMPLOYEES.' e
                                        LEFT JOIN '.DB_POSITIONS.' AS p ON p.id = e.position
                                        WHERE e.partner = '.$userToken['id'].' AND e.deleted = 0
                                        '.$ORDER_BY.'
                                        LIMIT '.Pages::$limit);

        while($row = DB::getRow($employees))
            $result[] = array('id' => $row['id'],
                                'name' => $row['name'],
                                'email' => $row['email'],
                                'employed' => (bool)$row['employed'],
                                'pin_code' => $row['pin_code'],
                                'last_enter' => $row['last_enter'],
                                'position' => array('id' => $row['pid'],
                                                    'name' => $row['pname']));

        $pages = DB::query('SELECT COUNT(id) AS count FROM '.DB_EMPLOYEES.' WHERE partner = '.$userToken['id'].' AND deleted = 0');
        $pages = DB::getRow($pages);
                                            
        if($pages['count'] != null){
            $total_pages = ceil($pages['count'] / ELEMENT_COUNT);
        }
        else
            $total_pages = 0;
                                            
        $pageData = array('current_page' => (int)Pages::$page,
                            'total_pages' => $total_pages,
                            'page_size' => ELEMENT_COUNT,
                            'total_count' => (int)$pages['count']);

        response('success', $result, '7', $pageData);

    break;

    case 'delete':

        if(!$employee = DB::escape($_REQUEST['employee']))
            response('error', array('msg' => 'Выберите сотрудника.'), '386');

        $employeeData = DB::select('id', DB_EMPLOYEES, 'id = '.$employee.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($employeeData) == 0)
            response('error', array('msg' => 'Сотрудник не найден.'), '387');

        $shift = DB::select('*', DB_EMPLOYEE_SHIFTS, 'employee = '.$employee.' AND shift_closed = 0', '', 1);

        if(DB::getRecordCount($shift) != 0){

            $shift =  DB::getRow($shift);

            $shift_close = new Shift(false, $shift['point'], time());
            $shift_close->close($shift['id']);

        }

        if(DB::update(array('deleted' => 1, 'token' => ''), DB_EMPLOYEES, 'id = '.$employee))
            response('success', array('msg' => 'Сотрудник удален.'), '625');
        else
            response('error', '', '503');

    break;

    case 'edit':

        $fields = [];

        if(!$employee = DB::escape($_REQUEST['employee']))
            response('error', array('msg' => 'Выберите сотрудника.'), '386');

        $employeeData = DB::select('id', DB_EMPLOYEES, 'id = '.$employee.' AND partner = '.$userToken['id']);

        if(DB::getRecordCount($employeeData) == 0)
            response('error', array('msg' => 'Сотрудник не найден.'), '387');

        if(!$name = DB::escape($_REQUEST['name']))
            response('error', array('msg' => 'Введите имя сотрудника.'), '378');

        $fields['name'] = $name;

        $fields['email'] = DB::escape($_REQUEST['email']);


        $loginEmployee = DB::select('id', DB_EMPLOYEES, 'email = "'.$fields['email'].'" AND id != '.$employee, '', 1);
        $loginPartner = DB::select('id', DB_PARTNER, 'login = "'.$fields['email'].'"', '', 1);

        if((DB::getRecordCount($loginEmployee) != 0 || DB::getRecordCount($loginPartner) != 0) && $fields['email'])
            response('error', 'Логин/email "'.$fields['email'].'" уже используется.', 1);

        $fields['employed'] = (DB::escape($_REQUEST['employed'])) ? 1 : 0;

        if($password = DB::escape($_REQUEST['password'])){

            if(strlen($password) < 8)
                response('error', array('msg' => 'Пароль не может содержать меньше 8 символов.'), '303');

            $pattern = "/[\\\~^°\"\/`';,\.:_{\[\]}\|<>]/";
    
            if(preg_match($pattern, $password, $matches))
                response('error', array('msg' => 'В пароле присутствуют недопустимые символы: "'.$matches[0].'".'), '304');

            $fields['password'] = password_hash($password, PASSWORD_DEFAULT);

        }

        if($pin_code = DB::escape($_REQUEST['pin_code'])){

            if(!ctype_digit($pin_code))
                response('error', array('msg' => 'ПИН-код должен состоять только из цифр.'), '382');

            if(strlen($pin_code) != 6)
                response('error', array('msg' => 'ПИН-код должен состоять из 6 символов'), '383');

            $pin_code_repeat = DB::select('id', DB_EMPLOYEES, 'id != '.$employee.' AND pin_code = '.$pin_code.' AND partner = '.$userToken['id']);
            if(DB::getRecordCount($pin_code_repeat) != 0)
                response('error', array('msg' => 'Такой ПИН-код уже используется.'), '385');

            $fields['pin_code'] = $pin_code;

        }

        if($position = DB::escape($_REQUEST['position'])){

            $positionData = DB::select('id', DB_POSITIONS, 'id = '.$position.' AND partner = '.$userToken['id']);

            if(DB::getRecordCount($positionData) == 0)
                response('error', array('msg' => 'Должность не найдена.'), '377');

            $fields['position'] = $position;

        }

        if(sizeof($fields) == 0)
            response('error', array('msg' => 'Не передано ни одного параметра.'), '330');

        if(DB::update($fields, DB_EMPLOYEES, 'id = '.$employee))
            response('success', array('msg' => 'Информация о сотруднике изменена.'), '626');
        else
            response('error', '', '503');

    break;

    case 'info':

        if(!$employee = DB::escape($_REQUEST['employee']))
            response('error', array('msg' => 'Выберите сотрудника.'), '386');

        $employeeData = DB::query('SELECT e.name, e.email, e.pin_code, e.position, e.employed, p.name AS pname
                                        FROM '.DB_EMPLOYEES.' e
                                        LEFT JOIN '.DB_POSITIONS.' AS p ON p.id = e.position
                                        WHERE e.id = '.$employee.' AND e.partner = '.$userToken['id']);

        if(DB::getRecordCount($employeeData) == 0)
            response('error', array('msg' => 'Сотрудник не найден.'), '387');

        $employeeData = DB::getRow($employeeData);

        $employeeData['employed'] = (bool)$employeeData['employed'];

        $employeeData['position'] = array('id' => $employeeData['position'],
                                            'name' => $employeeData['pname']);

        unset($employeeData['pname']);

        response('success', $employeeData, '7');

    break;

}