<?php
use Support\Pages;
use Support\DB;

include ROOT.'api/partner/tokenCheck.php';

function ValidateData($data)
{
    foreach ($data as $pos)
    {
        $price = filter_var($pos['price'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        if(filter_var($pos['tech_card'], FILTER_VALIDATE_INT) <= 0
            || filter_var($pos['point'], FILTER_VALIDATE_INT) <= 0
            || ($price === null || $price < 0))
            return false;
    }
    return true;
}

$partner = (DB::escape($_REQUEST['partner']) && $userToken['admin']) ? DB::escape($_REQUEST['partner']) : $userToken['id'];

switch ($action) {

    case "get":

        $result = [];

        $query = DB::query('
            SELECT d.id, d.exec_date, e.name AS employee, d.comment, d.status, d.created, d.updated
            FROM '.DB_AUTO_PRICE_DOCUMENT.' d
            LEFT JOIN '.DB_EMPLOYEES.' e ON e.id = d.employee
            WHERE d.partner = '.$userToken['id'].'
            ORDER BY d.exec_date DESC, id DESC
            LIMIT '.Pages::$limit.'
        ');

        $page_query = 'SELECT COUNT(id) AS count FROM '.DB_AUTO_PRICE_DOCUMENT.' WHERE partner = '.$userToken['id'];
        $page_data = Pages::GetPageInfo($page_query, $page);

        while($row = DB::getRow($query)){
            if($row['employee'] == null) $row['employee'] = "Администратор";
            $result[] = $row;
        }

        response('success', $result, 200, $page_data);
        break;

    case 'info':

        if (!$document = DB::escape($_REQUEST['document']))
            response('error', 'Не передан id документа.', 346);

        $result = DB::select('id, exec_date, comment, status', DB_AUTO_PRICE_DOCUMENT, "id = " . $document.' AND partner = '.$userToken['id'], '', 1);

        if(!DB::getRecordCount($result))
            response('error', 'Документ не найден.', 404);

        $result = DB::getRow($result);
        $data = [];

        $products = DB::query('
            SELECT tc.id, CONCAT(p.name, IF(tc.subname = "", "", CONCAT(tc.subname, " ")), ", ", tc.bulk_value, " ", tc.bulk_untils) AS name, pt.id AS point_id, pt.name AS point_name, pp.price
            FROM '.DB_AUTO_PRICE_POSITION.' pp
            JOIN '.DB_TECHNICAL_CARD.' tc ON tc.id = pp.tech_card
            JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
            JOIN '.DB_PARTNER_POINTS.' pt ON pt.id = pp.point
            WHERE pp.document = '.$document.'
        ');

        while($row = DB::getRow($products)){
            $id = $row['id'];
            $data[$id]['id'] = $row['id'];
            $data[$id]['name'] = $row['name'];
            $data[$id]['points'][] = [
                'id' => $row['point_id'],
                'name' => $row['point_name'],
                'price' => $row['price']
            ];
        }

        $result['products'] = array_values($data);

        response('success', $result, 200);

        break;

    case "add":
        $data = stripcslashes(DB::escape($_REQUEST['data'])); //прием json-массив позиций в поле data
        $data = json_decode($data, true);

        if (is_null($_REQUEST['exec_date'])) response('error', 'Не передана дата выполнения.', 346);
        else $exec_date = DB::escape($_REQUEST['exec_date']);
        if (is_null($_REQUEST['comment'])) $comment = '';
        else $comment = DB::escape($_REQUEST['comment']);
        if (empty($data)) response('error', 'Не переданы позиции автоцен.', 346);
        if(!ValidateData($data)) response('error', 'Некорректные данные в позициях.', 346);

        $fields = array(
            'partner' => $userToken['id'],
            'exec_date' => $exec_date,
            'comment' => $comment,
            'status' => 0,
            'created' => time(),
            'updated' => time()
        );

        if($userToken['employee'])
            $fields['employee'] = $userToken['employee'];

        $document = DB::insert($fields, DB_AUTO_PRICE_DOCUMENT);

        $sql = "INSERT INTO " . DB_AUTO_PRICE_POSITION . " VALUES ";

        foreach ($data as $k => $e) {
            $sql .= "(null,"
                . $document . ","
                . $data[$k]['tech_card'] . ","
                . $data[$k]['point'] . ","
                . $data[$k]['price'];
            $sql .= "),";
        }
        $sql = rtrim($sql, ",");                     //удаление запятой после работы цикла

        $result = DB::query($sql);

        if ($result)
            response('success', 'Документ создан.', 200);
        else {
            DB::query('DELETE FROM ' . DB_AUTO_PRICE_DOCUMENT . ' WHERE id = ' . $document);
            response('error', 'Ошибка добавления.', 346);
        }
        break;

    case "edit":
        if (is_null($_REQUEST['document'])) response('error', 'Не передан id документа.', 346);
        else $document = DB::escape($_REQUEST['document']);

        $query = DB::select('id, status', DB_AUTO_PRICE_DOCUMENT, "id = " . $document.' AND partner = '.$userToken['id']);
        if (DB::getRecordCount($query) == 0) response('error', 'Такого документа не существует.', 346);
        $query = DB::getRow($query);
        if ($query['status'] == '1') response('error', 'Нельзя редактировать выполненный документ.', 346);

        $data = stripcslashes(DB::escape($_REQUEST['data'])); //прием json-массив позиций в поле data
        $data = json_decode($data, true);
        if (empty($data)) response('error', 'Не переданы позиции автоцен.', 346);
        if(!ValidateData($data)) response('error', 'Некорректные данные в позициях.', 346);

        $fields = array();
        if (!is_null($_REQUEST['exec_date'])) $fields['exec_date'] = DB::escape($_REQUEST['exec_date']);
        if (!is_null($_REQUEST['comment'])) $fields['comment'] = DB::escape($_REQUEST['comment']);
        $fields['employee'] = $userToken['employee'] ? $userToken['employee'] : null;
        $fields['updated'] = time();

        DB::update($fields, DB_AUTO_PRICE_DOCUMENT, "id = " . $document);

        DB::delete(DB_AUTO_PRICE_POSITION, "document = " . $document);

        $sql = "INSERT INTO " . DB_AUTO_PRICE_POSITION . " VALUES ";

        foreach ($data as $k => $e) {
            $sql .= "(null,"
                . $document . ","
                . $data[$k]['tech_card'] . ","
                . $data[$k]['point'] . ","
                . $data[$k]['price'];
            $sql .= "),";
        }
        $sql = rtrim($sql, ",");                     //удаление запятой после работы цикла

        DB::query($sql);
        response('success', 'Изменение сохранено.', 200);
        break;

    case "delete":
        if (is_null($_REQUEST['document'])) response('error', 'Не передан id документа.', 346);
        else $document = DB::escape($_REQUEST['document']);

        $query = DB::select('id, status', DB_AUTO_PRICE_DOCUMENT, "id = " . $document.' AND partner = '.$userToken['id']);
        if (DB::getRecordCount($query) == 0) response('error', 'Такого документа не существует.', 346);
        $query = DB::getRow($query);
        if ($query['status'] == '1') response('error', 'Нельзя удалить выполненный документ.', 346);

        DB::delete(DB_AUTO_PRICE_DOCUMENT, "id = " . $document);

        response('success', 'Документ удален.', 200);
        break;
}