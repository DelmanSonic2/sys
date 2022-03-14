<?php
use Support\Pages;
use Support\DB;

require_once 'CalcWarehouse.php';

class PromotionGifts extends ItemsHistory{

    protected $phone;
    protected $products;
    protected $gifts;
    protected $gift_products = [];
    protected $used_promotions = [];
    public $accumulation = [];
    public $result;

    public function __construct($db, $phone, $products = NULL, $gifts = NULL){
        
        // $this->db = $db;
        $this->phone = $phone;
        $this->products = $products;
        $this->gifts = $gifts;
        $this->result = array(
            'status' => 'success',
            'msg' => 'Акции доступны.',
            'code' => 200
        );

    }

    public function parse($property){

        $this->$property = stripcslashes($this->$property);
        $this->$property = json_decode($this->$property, true);

        if($this->$property === null)
            $this->result = array(
                'status' => 'error',
                'msg' => 'Не удалось обработать данные.',
                'code' => 422
            );

        return $this;

    }

    function client($get_gifts = true){
    
        //Получаем все акции и накопления пользователя
        $promotion_gifts = DB::query('
            SELECT p.*, c.count AS user_count
            FROM '.DB_PROMOTION_GIFTS.' p
            LEFT JOIN '.DB_CLIENT_PROMOTION_GIFTS.' c ON p.id = c.promotion_gift AND c.client_phone = "'.$this->phone.'"
        ');
    
        while($row = DB::getRow($promotion_gifts)){
    
            $row['conditions'] = explode(',', $row['conditions']);
            $row['gifts'] = $get_gifts ? $this->getGifts($row['gifts']) : explode(',', $row['gifts']);

            if($row['user_count'] == null)
                $row['user_count'] = 0;

            $row['user_count'] = (int)$row['user_count'];
    
            $this->accumulation[] = $row;
        }
    
        return $this;
    
    }

    function getGifts($gifts){
        
        $result = [];

        $technical_cards = DB::query('
            SELECT p.id, p.name, tc.id AS tid, tc.subname, tc.bulk_value, tc.bulk_untils, tc.code, tc.product
            FROM '.DB_TECHNICAL_CARD.' tc
            JOIN '.DB_PRODUCTS.' p ON p.id = tc.product
            WHERE FIND_IN_SET(tc.id, "'.$gifts.'")
        ');

        while($row = DB::getRow($technical_cards)){

            $exist = false;

            $card = array(
                'id' => $row['tid'],
                'subname' => $row['subname'],
                'product' => $row['product'],
                'bulk_value' => round($row['bulk_value'], 2),
                'bulk_untils' => $row['bulk_untils'],
                'code' => $row['code'],
                'price' => 0
            );

            for($i = 0; $i < sizeof($result); $i++){

                if($card['product'] == $result[$i]['id']){

                    $result[$i]['cards'][] = $card;

                    $exist = true;
                    break;

                }

            }

            if(!$exist){

                $cards = [];
                $cards[] = $card;

                $result[] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'cards' => $cards
                );
            }

        }

        return $result;

    }

    public function gifts(){

        //Обходим массив с существующими акциями
        for($i = 0; $i < sizeof($this->accumulation); $i++){

            $this->accumulation[$i]['new_user_count'] = $this->accumulation[$i]['user_count'];

            //Инициализируем количество подарков, приобретаемых пользователем
            $this->accumulation[$i]['gifts_count'] = 0;

            //Проходимся по массиву подарков, выбранных пользователем
            for($j = 0; $j < sizeof($this->gifts); $j++){

                if($this->accumulation[$i]['id'] == $this->gifts[$j]['promotion']){

                    $this->used_promotions[] = $this->gifts[$j]['promotion'];

                    //Проходимся по подаркам
                    foreach($this->gifts[$j]['items'] as $gift){

                        $this->gift_products[] = array(
                            'id' => $gift['id'],
                            'count' => $gift['count'],
                            'promotion' => $this->gifts[$j]['promotion']
                        );

                        //Если подарок, выбранный пользователем не учавствует в акции, то завершение работы алгоритма
                        if(!in_array($gift['id'], $this->accumulation[$i]['gifts'])){
                            $this->result = array(
                                'status' => 'error',
                                'msg' => 'Вы не можете выбрать подарок.',
                                'code' => 422
                            );
                            return $this;
                        }

                        //Если подарок есть в акции, то указываем, сколько хочет приобрести пользователь
                        $this->accumulation[$i]['gifts_count'] += $gift['count'];

                    }

                }

            }

        }

        //Проходимся по массиву накоплений клиента
        for($i = 0; $i < sizeof($this->accumulation); $i++){

            //И по товарам, которые покупает клиент
            for($j = 0; $j < sizeof($this->products); $j++){

                //Если товар учавствует в акции, то увеличиваем счетчик накопления на количество приобретаемых позиций
                if(in_array($this->products[$j]['id'], $this->accumulation[$i]['conditions']))
                    $this->accumulation[$i]['new_user_count'] += $this->products[$j]['count'];

            }

            if($this->accumulation[$i]['gifts_count'])
                $this->accumulation[$i]['new_user_count'] = $this->accumulation[$i]['new_user_count'] - $this->accumulation[$i]['count'];

            //Если акция используется, и накопления ушли в минус, то клиент не может использовать акцию
            if($this->accumulation[$i]['gifts_count'] && $this->accumulation[$i]['new_user_count'] < 0 && in_array($this->accumulation[$i]['id'], $this->used_promotions)){

                $this->result = array(
                    'status' => 'error',
                    'msg' => 'Вы не можете использовать акцию "'.$this->accumulation[$i]['name'].'". Необходимо приобрести ещё '.abs($this->accumulation[$i]['new_user_count']).' позиции из этой акции.',
                    'code' => 422
                );
                return $this;

            }

        }

        return $this;

    }

    private function getGiftProducts(){

        $result = [];

        foreach($this->gift_products as $gift){

            if(!$where)
                $where = $gift['id'];
            else
                $where .= ','.$gift['id'];

        }

        $technical_cards = DB::query('
            SELECT tc.id, tc.subname, tc.product, tc.bulk_value, tc.bulk_untils, tc.code, p.name
            FROM '.DB_TECHNICAL_CARD.' tc
            JOIN '.DB_PRODUCTS.' p ON p.id = tc.product 
            WHERE FIND_IN_SET(tc.id, "'.$where.'")');

        while($row = DB::getRow($technical_cards)){

            foreach($this->gift_products as $gift){

                if($gift['id'] == $row['id']){
                    $row['price'] = 0;
                    $row['count'] = $gift['count'];
                    $row['time_discount_percent'] = false;
                    $row['promotion_gift'] = $gift['promotion'];
                    break;
                }

            }

            $this->products[] = $row;

        }

    }

    public function execute(){

        if($this->result['status'] === 'error')
            return;

        $this->getGiftProducts();

        for($i = 0; $i < sizeof($this->accumulation); $i++){

            if($this->accumulation[$i]['new_user_count'] > $this->accumulation[$i]['count'])
                $this->accumulation[$i]['new_user_count'] = $this->accumulation[$i]['count'];

            if($this->accumulation[$i]['new_user_count'] < 0)
                $this->accumulation[$i]['new_user_count'] = 0;

            if($this->accumulation[$i]['new_user_count'] == $this->accumulation[$i]['user_count'])
                continue;

            $promotion_accumulation = DB::select('*', DB_CLIENT_PROMOTION_GIFTS, 'client_phone = "'.$this->phone.'" AND promotion_gift = '.$this->accumulation[$i]['id']);

            if(!DB::getRecordCount($promotion_accumulation)){
                $fields = array(
                    'client_phone' => $this->phone,
                    'promotion_gift' => $this->accumulation[$i]['id'],
                    'count' => $this->accumulation[$i]['new_user_count'],
                    'created' => time(),
                    'updated' => time()
                );

                DB::insert($fields, DB_CLIENT_PROMOTION_GIFTS);

            }
            else
                DB::update(array('count' => $this->accumulation[$i]['new_user_count'], 'updated' => time()), DB_CLIENT_PROMOTION_GIFTS, 'client_phone = "'.$this->phone.'" AND promotion_gift = '.$this->accumulation[$i]['id']);

        }

    }

}